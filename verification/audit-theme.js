const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const PNG = require('pngjs').PNG;
const pixelmatch = require('pixelmatch');
require('dotenv').config();

const BASELINE_DIR = path.join(__dirname, 'baseline');
const PAGES_JSON = path.join(__dirname, 'pages.json');
const REPORTS_DIR = path.join(__dirname, '../.project/reports');
const CURRENT_DIR = path.join(REPORTS_DIR, 'current-screenshots');
const DIFF_DIR = path.join(REPORTS_DIR, 'diff-screenshots');
const SITE_URL = process.env.SITE_URL || 'https://tkmsoluciones.com';
const WP_URL = process.env.WP_URL || SITE_URL;
const WP_USER = process.env.WP_USER;
const WP_PASS = process.env.WP_PASS;

(async () => {
    if (!fs.existsSync(PAGES_JSON)) {
        console.error('❌ pages.json no existe. Ejecutá primero: node discover-pages.js');
        process.exit(1);
    }

    if (!fs.existsSync(BASELINE_DIR) || fs.readdirSync(BASELINE_DIR).length === 0) {
        console.error('❌ No hay baseline. Ejecutá primero: npm run baseline');
        process.exit(1);
    }

    if (!WP_USER || !WP_PASS) {
        console.error('❌ WP_USER/WP_PASS no están en verification/.env');
        process.exit(1);
    }

    [REPORTS_DIR, CURRENT_DIR, DIFF_DIR].forEach(d => {
        if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });
    });

    const PAGES = JSON.parse(fs.readFileSync(PAGES_JSON, 'utf8'));
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });

    const report = {
        timestamp: new Date().toISOString(),
        pages: [],
        consoleErrors: [],
        networkErrors: [],
        verdict: 'PASS',
        loginSuccessful: false
    };

    // LOGIN
    try {
        const loginPage = await context.newPage();
        console.log('🔐 Iniciando login en WordPress...');
        await loginPage.goto(`${WP_URL}/wp-login.php`, { waitUntil: 'networkidle' });
        await loginPage.fill('#user_login', WP_USER);
        await loginPage.fill('#user_pass', WP_PASS);
        await loginPage.click('#wp-submit');
        await loginPage.waitForLoadState('networkidle');
        await loginPage.close();
        report.loginSuccessful = true;
        console.log('✅ Login exitoso');
    } catch (e) {
        console.error('❌ Login falló:', e.message);
        report.verdict = 'FAIL';
    }

    // AUDITORÍA
    for (const p of PAGES) {
        const page = await context.newPage();
        if (p.viewport) await page.setViewportSize(p.viewport);

        page.on('console', msg => {
            if (msg.type() === 'error') {
                report.consoleErrors.push({ page: p.name, text: msg.text() });
            }
        });
        page.on('response', r => {
            if (r.status() >= 400) {
                report.networkErrors.push({ page: p.name, url: r.url(), status: r.status() });
            }
        });

        try {
            await page.goto(`${SITE_URL}${p.path}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);

            const currentPath = path.join(CURRENT_DIR, `${p.name}.png`);
            await page.screenshot({ path: currentPath, fullPage: true });

            const baseline = PNG.sync.read(fs.readFileSync(path.join(BASELINE_DIR, `${p.name}.png`)));
            const current = PNG.sync.read(fs.readFileSync(currentPath));

            const width = Math.max(baseline.width, current.width);
            const height = Math.max(baseline.height, current.height);
            const baselineResized = resize(baseline, width, height);
            const currentResized = resize(current, width, height);

            const diff = new PNG({ width, height });
            const numDiffPixels = pixelmatch(
                baselineResized.data, currentResized.data, diff.data,
                width, height, { threshold: 0.1 }
            );

            const totalPixels = width * height;
            const diffPercent = (numDiffPixels / totalPixels) * 100;

            const diffPath = path.join(DIFF_DIR, `${p.name}.png`);
            if (diffPercent > 0.01) fs.writeFileSync(diffPath, PNG.sync.write(diff));

            report.pages.push({
                name: p.name,
                diffPercent: diffPercent.toFixed(3),
                diffPixels: numDiffPixels,
                hasDiffFile: diffPercent > 0.01
            });

            if (diffPercent > 2) {
                report.verdict = 'FAIL';
                console.log(`❌ ${p.name}: ${diffPercent.toFixed(2)}% diff`);
            } else if (diffPercent > 0.5) {
                if (report.verdict === 'PASS') report.verdict = 'WARNING';
                console.log(`⚠️ ${p.name}: ${diffPercent.toFixed(2)}% diff`);
            } else {
                console.log(`✅ ${p.name}: OK`);
            }
        } catch (e) {
            report.pages.push({ name: p.name, error: e.message });
            report.verdict = 'FAIL';
        } finally {
            await page.close();
        }
    }

    await browser.close();

    const reportPath = path.join(REPORTS_DIR, `theme-audit-${Date.now()}.json`);
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));

    console.log(`\n========== RESUMEN ==========`);
    console.log(`Verdict: ${report.verdict}`);
    console.log(`Login: ${report.loginSuccessful ? 'OK' : 'FAIL'}`);
    console.log(`Console errors: ${report.consoleErrors.length}`);
    console.log(`Network errors: ${report.networkErrors.length}`);
    console.log(`Report: ${reportPath}`);
    console.log(`=============================\n`);

    process.exit(report.verdict === 'FAIL' ? 1 : 0);
})();

function resize(img, w, h) {
    if (img.width === w && img.height === h) return img;
    const out = new PNG({ width: w, height: h });
    out.data.fill(0);
    for (let y = 0; y < Math.min(img.height, h); y++) {
        for (let x = 0; x < Math.min(img.width, w); x++) {
            const srcIdx = (y * img.width + x) * 4;
            const dstIdx = (y * w + x) * 4;
            out.data[dstIdx] = img.data[srcIdx];
            out.data[dstIdx+1] = img.data[srcIdx+1];
            out.data[dstIdx+2] = img.data[srcIdx+2];
            out.data[dstIdx+3] = img.data[srcIdx+3];
        }
    }
    return out;
}
