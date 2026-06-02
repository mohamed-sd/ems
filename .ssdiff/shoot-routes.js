// Re-shoot specific authenticated routes into current/.
// Usage: node shoot-routes.js Contracts/contracts.php Projects/projects.php ...
const path = require('path');
const { chromium } = require('playwright');

const BASE = process.env.BASE_URL || 'http://localhost/ems/';
const USER = process.env.SS_USER || 'ssbot';
const PASS = process.env.SS_PASS || 'Test@12345';
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];
const FREEZE = '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important;scroll-behavior:auto!important}';
const routes = process.argv.slice(2);
const outDir = path.join(__dirname, 'current');

function slug(route) {
  return route.replace(/[\/\\]/g, '__').replace(/\.php.*$/, '').replace(/[?=&]/g, '_');
}

(async () => {
  const b = await chromium.launch({ channel: 'chrome' });
  const c = await b.newContext({ deviceScaleFactor: 1 });
  const p = await c.newPage();
  await p.goto(BASE + 'login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#username', USER); await p.fill('#password', PASS);
  await Promise.all([p.waitForLoadState('domcontentloaded'), p.click('#submitBtn')]);
  await p.waitForTimeout(600);
  if (/login\.php/i.test(p.url())) { console.error('LOGIN FAILED'); await b.close(); process.exit(2); }

  for (const route of routes) {
    for (const vp of VIEWPORTS) {
      await p.setViewportSize({ width: vp.width, height: vp.height });
      let status = 0;
      try { const r = await p.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 30000 }); status = r ? r.status() : 0; } catch (e) {}
      try { await p.waitForLoadState('networkidle', { timeout: 15000 }); } catch (e) {}
      try { await p.evaluate(() => document.fonts && document.fonts.ready); } catch (e) {}
      try { await p.addStyleTag({ content: FREEZE }); } catch (e) {}
      // Neutralise hover/focus state so captures are deterministic across routes.
      try { await p.mouse.move(0, 0); } catch (e) {}
      try { await p.evaluate(() => { if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); }); } catch (e) {}
      await p.waitForTimeout(1500);
      const redirected = /login\.php/i.test(p.url());
      await p.screenshot({ path: path.join(outDir, `${slug(route)}@${vp.name}.png`), fullPage: true });
      console.log(`  ${status} ${route} @${vp.name}${redirected ? ' [REDIRECT->login]' : ''}`);
    }
  }
  await b.close();
})();
