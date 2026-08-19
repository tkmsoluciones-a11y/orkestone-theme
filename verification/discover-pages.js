const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const SITE_URL = process.env.SITE_URL || 'https://tkmsoluciones.com';
const WP_URL = process.env.WP_URL || SITE_URL;
const WP_USER = process.env.WP_USER;
const WP_PASS = process.env.WP_PASS;

function nameFor(p, priv) {
    if (p === '/') return 'home';
    const m = p.match(/admin\.php\?page=([^&]+)/);
    const raw = m ? m[1] : p;
    const s = raw.replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
    return (priv ? 'admin-' : '') + (s || 'home');
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });

    if (WP_USER && WP_PASS) {
        try {
            const lp = await context.newPage();
            console.log('🔐 Login...');
            await lp.goto(`${WP_URL}/wp-login.php`, { waitUntil: 'networkidle' });
            await lp.fill('#user_login', WP_USER);
            await lp.fill('#user_pass', WP_PASS);
            await lp.click('#wp-submit');
            await lp.waitForLoadState('networkidle');
            await lp.close();
            console.log('✅ Login OK');
        } catch (e) { console.error('❌ Login falló:', e.message); }
    }

    const pages = [];
    const seenPaths = new Set();
    const seenNames = new Set();
    const skip = /\.(png|jpe?g|webp|gif|svg|pdf|zip|css|js)(\?|$)/i;

    const addPage = (href, base, priv) => {
        try {
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            if (skip.test(href)) return;
            const u = new URL(href, base);
            if (u.origin !== new URL(SITE_URL).origin) return;
            let p = u.pathname;
            if (p.includes('admin.php') && u.search) p += u.search;
            if (seenPaths.has(p)) return;
            const name = nameFor(p, priv);
            if (seenNames.has(name)) return;
            seenPaths.add(p); seenNames.add(name);
            pages.push({ name, path: p, private: priv });
        } catch (e) {}
    };

    // 1) Links públicos del home (menús, header, footer)
    const home = await context.newPage();
    await home.goto(`${SITE_URL}/`, { waitUntil: 'networkidle' });
    const publicLinks = await home.$$eval('a[href]', as => as.map(a => a.getAttribute('href')));
    await home.close();
    for (const h of publicLinks) {
        if (h && (h.includes('/wp-admin/') || h.includes('wp-login'))) continue;
        addPage(h, SITE_URL + '/', false);
    }

    // 2) Links del menú de WP Admin (páginas reales del theme/plugin)
    const admin = await context.newPage();
    try {
        await admin.goto(`${WP_URL}/wp-admin/`, { waitUntil: 'networkidle' });
        const adminLinks = await admin.$$eval('#adminmenu a[href]', as => as.map(a => a.getAttribute('href')));
        for (const h of adminLinks) {
            if (h && h.includes('admin.php?page=')) addPage(h, WP_URL + '/wp-admin/', true);
        }
    } catch (e) { console.error('⚠️ No se pudo leer el menú admin:', e.message); }
    await admin.close();

    // Home primero + mobile
    const final = [
        { name: 'home', path: '/', private: false },
        { name: 'home-mobile', path: '/', viewport: { width: 375, height: 812 }, private: false },
        ...pages.filter(p => p.path !== '/')
    ].slice(0, 20);

    fs.writeFileSync(path.join(__dirname, 'pages.json'), JSON.stringify(final, null, 2));
    console.log(`\n✅ pages.json con ${final.length} páginas reales:`);
    final.forEach(p => console.log(`   ${p.private ? '🔒' : '🌐'} ${p.name} → ${p.path}`));

    await browser.close();
})();