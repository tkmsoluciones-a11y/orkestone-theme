const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
require('dotenv').config();

const BASELINE_DIR = path.join(__dirname, 'baseline');
const PAGES_JSON = path.join(__dirname, 'pages.json');
const SITE_URL = process.env.SITE_URL || 'https://tkmsoluciones.com';
const WP_URL = process.env.WP_URL || SITE_URL;
const WP_USER = process.env.WP_USER;
const WP_PASS = process.env.WP_PASS;

(async () => {
    if (!fs.existsSync(PAGES_JSON)) {
        console.error('❌ pages.json no existe. Ejecutá primero: node discover-pages.js');
        process.exit(1);
    }

    const PAGES = JSON.parse(fs.readFileSync(PAGES_JSON, 'utf8'));
    if (!fs.existsSync(BASELINE_DIR)) fs.mkdirSync(BASELINE_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });

    // LOGIN una sola vez
    if (WP_USER && WP_PASS) {
        try {
            const loginPage = await context.newPage();
            console.log('🔐 Iniciando login en WordPress...');
            await loginPage.goto(`${WP_URL}/wp-login.php`, { waitUntil: 'networkidle' });
            await loginPage.fill('#user_login', WP_USER);
            await loginPage.fill('#user_pass', WP_PASS);
            await loginPage.click('#wp-submit');
            await loginPage.waitForLoadState('networkidle');
            await loginPage.close();
            console.log('✅ Login exitoso');
        } catch (e) {
            console.error('❌ Login falló:', e.message);
        }
    }

    for (const p of PAGES) {
        const page = await context.newPage();
        if (p.viewport) await page.setViewportSize(p.viewport);

        try {
            console.log(`📸 Capturando ${p.name}...`);
            await page.goto(`${SITE_URL}${p.path}`, { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await page.screenshot({
                path: path.join(BASELINE_DIR, `${p.name}.png`),
                fullPage: true
            });
            console.log(`   ✅ ${p.name}.png`);
        } catch (e) {
            console.log(`   ❌ ${p.name}: ${e.message}`);
        } finally {
            await page.close();
        }
    }

    await browser.close();
    console.log(`\n✅ Baseline capturado en ${BASELINE_DIR}`);
})();
