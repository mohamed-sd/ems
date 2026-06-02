const { chromium } = require('playwright');
const BASE = 'http://localhost/ems/';
const pages = [
  'Approvals/requests.php','Drivers/showcontractdriver.php','Equipments/equipments.php',
  'Equipments/equipments_drivers.php','Projects/projects.php','Reports/contract_report.php',
  'Reports/contractall.php','Reports/driverAndsupplerscontract.php','Settings/change_password.php',
  'Settings/modules.php','Settings/roles.php','Suppliers/showcontractsuppliers.php'
];
(async () => {
  const b = await chromium.launch({ channel: 'chrome' });
  const c = await b.newContext();
  const p = await c.newPage();
  await p.goto(BASE + 'login.php'); await p.fill('#username','ssbot'); await p.fill('#password','Test@12345');
  await Promise.all([p.waitForLoadState('domcontentloaded'), p.click('#submitBtn')]);
  for (const route of pages) {
    try {
      await p.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await p.waitForTimeout(1600);
      const r = await p.evaluate(() => ({
        redirected: /login\.php/i.test(location.href),
        unifiedHeaders: document.querySelectorAll('.main .header[data-ems-unified-header], .main .page-header[data-ems-unified-header]').length,
        rawHeaders: document.querySelectorAll('.main .header:not([data-ems-unified-header]), .main .page-header:not([data-ems-unified-header])').length,
      }));
      console.log(`${r.redirected?'REDIR':'  OK '}  unified=${r.unifiedHeaders} raw=${r.rawHeaders}  ${route}`);
    } catch (e) { console.log(`  ERR  ${route}: ${e.message}`); }
  }
  await b.close();
})();
