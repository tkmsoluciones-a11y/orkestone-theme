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

const LAUNCH_ARGS = ['--disable-dev-shm-usage', '--disable-gpu'];

async function launchAndLogin() {
    const browser = await chromium.launch({ headless: true, args: LAUNCH_ARGS });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    if (WP_USER && WP_PASS) {
        const lp = await context.newPage();
        await lp.goto(WP_URL + '/wp-login.php', { waitUntil: 'networkidle' });
        await lp.fill('#user_login', WP_USER);
        await lp.fill('#user_pass', WP_PASS);
        await lp.click('#wp-submit');
        await lp.waitForLoadState('networkidle');
        await lp.close();
        console.log('Login OK');
    }
    return { browser, context };
}

(async () => {
    if (!fs.existsSync(PAGES_JSON)) {
        console.error('pages.json no existe. Ejecuta: node discover-pages.js');
        process.exit(1);
    }
    const PAGES = JSON.parse(fs.readFileSync(PAGES_JSON, 'utf8'));
    if (!fs.existsSync(BASELINE_DIR)) fs.mkdirSync(BASELINE_DIR, { recursive: true });

    let session = await launchAndLogin();
    let browser = session.browser;
    let context = session.context;
    const failed = [];

    for (const p of PAGES) {
        if (!browser.isConnected()) {
            console.log('Browser cerrado. Relanzando y re-logueando...');
            try {
                session = await launchAndLogin();
                browser = session.browser;
                context = session.context;
            } catch (e) {
                console.log('Relanzamiento fallo: ' + e.message.split('\n')[0]);
                failed.push(p.name);
                continue;
            }
        }

        let ok = false;
        let fullPage = true;
        for (let attempt = 1; attempt <= 2 && !ok; attempt++) {
            let page = null;
            try {
                page = await context.newPage();
                if (p.viewport) await page.setViewportSize(p.viewport);
                console.log('Capturando ' + p.name + (attempt > 1 ? ' (intento ' + attempt + ', sin fullPage)' : '') + '...');
                await page.goto(SITE_URL + p.path, { waitUntil: 'networkidle', timeout: 60000 });
                await page.waitForTimeout(1000);
                await page.screenshot({ path: path.join(BASELINE_DIR, p.name + '.png'), fullPage: fullPage });
                console.log('   OK ' + p.name + '.png');
                ok = true;
            } catch (e) {
                console.log('   ERROR ' + p.name + ': ' + e.message.split('\n')[0]);
                fullPage = false;
            } finally {
                if (page) { try { await page.close(); } catch (e) {} }
            }
        }
        if (!ok) failed.push(p.name);
    }

    try { await browser.close(); } catch (e) {}

    if (failed.length) {
        console.log('');
        console.log('Baseline COMPLETO CON ERRORES. Paginas fallidas: ' + failed.join(', '));
        process.exit(2);
    }
    console.log('');
    console.log('Baseline completo capturado en ' + BASELINE_DIR);
})();