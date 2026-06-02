const { chromium } = require('playwright');
const BASE = 'http://localhost/ems/';
const pages = ['Approvals/requests.php','Equipments/equipments.php','Settings/modules.php','Settings/roles.php'];
(async () => {
  const b = await chromium.launch({ channel: 'chrome' });
  const c = await b.newContext(); const p = await c.newPage();
  await p.goto(BASE+'login.php'); await p.fill('#username','ssbot'); await p.fill('#password','Test@12345');
  await Promise.all([p.waitForLoadState('domcontentloaded'), p.click('#submitBtn')]);
  for (const route of pages) {
    await p.goto(BASE+route,{waitUntil:'domcontentloaded'}); await p.waitForTimeout(1700);
    const html = await p.evaluate(() => { const h=document.querySelector('.main .header[data-ems-unified-header]'); return h?h.outerHTML:'(none)'; });
    console.log('\n===== '+route+' =====\n'+html);
  }
  await b.close();
})();
