// Capture screenshots of all routes into a target directory.
// Usage: node shoot.js baseline   (or)   node shoot.js current
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { ROUTES, PUBLIC_ROUTES, VIEWPORTS } = require('./routes');

const BASE = process.env.BASE_URL || 'http://localhost/ems/';
const USER = process.env.SS_USER || 'ssbot';
const PASS = process.env.SS_PASS || 'Test@12345';
const target = process.argv[2] || 'current';
const outDir = path.join(__dirname, target);

// Freeze animations / transitions / caret so diffs are deterministic.
const FREEZE_CSS = `*, *::before, *::after {
  transition: none !important;
  animation: none !important;
  caret-color: transparent !important;
  scroll-behavior: auto !important;
}`;

function fileFor(route, vp) {
  const slug = route.replace(/[\/\\]/g, '__').replace(/\.php.*$/, '').replace(/[?=&]/g, '_');
  return path.join(outDir, `${slug}@${vp.name}.png`);
}

async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch (e) {}
  try { await page.evaluate(() => document.fonts && document.fonts.ready); } catch (e) {}
  try { await page.addStyleTag({ content: FREEZE_CSS }); } catch (e) {}
  // Neutralise hover/focus so captures are deterministic across routes.
  try { await page.mouse.move(0, 0); } catch (e) {}
  try { await page.evaluate(() => { if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); }); } catch (e) {}
  // Let JS-driven canvas charts (Chart.js) finish their entry animation so
  // diffs are deterministic, then settle.
  await page.waitForTimeout(1500);
}

async function shoot(page, route, isPublic) {
  for (const vp of VIEWPORTS) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    const url = BASE + route;
    let status = 0;
    try {
      const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      status = resp ? resp.status() : 0;
    } catch (e) {
      console.log(`  ! goto failed ${route} @${vp.name}: ${e.message}`);
    }
    await settle(page);
    const finalUrl = page.url();
    const redirected = isPublic ? false : /login\.php/i.test(finalUrl);
    try {
      await page.screenshot({ path: fileFor(route, vp), fullPage: true });
    } catch (e) {
      console.log(`  ! shot failed ${route} @${vp.name}: ${e.message}`);
    }
    const flag = redirected ? ' [REDIRECT->login]' : '';
    console.log(`  ${status}  ${route} @${vp.name}${flag}`);
  }
}

async function login(context) {
  const page = await context.newPage();
  await page.goto(BASE + 'login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#username', USER);
  await page.fill('#password', PASS);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.click('#submitBtn'),
  ]);
  await page.waitForTimeout(800);
  const ok = !/login\.php/i.test(page.url());
  console.log(ok ? `Login OK -> ${page.url()}` : `Login FAILED -> ${page.url()}`);
  await page.close();
  return ok;
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  // Use the system Chrome via channel (no chromium download needed).
  const browser = await chromium.launch({ channel: 'chrome' });

  // Public pages in a clean (unauthenticated) context.
  console.log(`\n== ${target}: public routes ==`);
  const pubCtx = await browser.newContext({ deviceScaleFactor: 1 });
  const pubPage = await pubCtx.newPage();
  for (const r of PUBLIC_ROUTES) await shoot(pubPage, r, true);
  await pubCtx.close();

  // Authenticated pages.
  console.log(`\n== ${target}: authenticated routes ==`);
  const ctx = await browser.newContext({ deviceScaleFactor: 1 });
  const ok = await login(ctx);
  if (!ok) { console.error('Aborting: login failed'); await browser.close(); process.exit(2); }
  const page = await ctx.newPage();
  for (const r of ROUTES) await shoot(page, r, false);
  await ctx.close();

  await browser.close();
  console.log(`\nDone -> ${outDir}`);
})();
