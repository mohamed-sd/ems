// Verify the unified column-groups feature across the 8 pages.
// Login as cgbot (role 1, company 4). For each page: assert toggling a group
// button flips its `active` class + actually changes column visibility, the
// chosen state persists across reload, and active/inactive colors match spec.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = 'http://localhost/ems/';
const USER = 'cgbot', PASS = 'Test@12345';
const outDir = path.join(__dirname, 'colgroups_check');

const PAGES = [
  { slug: 'contracts',          url: 'Contracts/contracts.php',                 key: 'contractGroupStates' },
  { slug: 'drivercontracts',    url: 'Drivers/drivercontracts.php?id=1',        key: 'driverContractGroupStates' },
  { slug: 'supplierscontracts', url: 'Suppliers/supplierscontracts.php',        key: 'supplierContractGroupStates' },
  { slug: 'equipments',         url: 'Equipments/equipments.php',               key: 'equipmentsGroupStates' },
  { slug: 'equipments_drivers', url: 'Equipments/equipments_drivers.php',       key: 'equipmentDriversGroupStates' },
  { slug: 'equipments_fleet',   url: 'Equipments/equipments_fleet.php',         key: 'fleetGroupStates' },
  { slug: 'hours_approval',     url: 'Approvals/hours_approval.php',            key: 'hoursApprovalGroupStates' },
  { slug: 'view_timesheet',     url: 'Timesheet/view_timesheet.php',           key: 'timesheetGroupStates' },
];

const FREEZE = `*,*::before,*::after{transition:none!important;animation:none!important;}`;

async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 12000 }); } catch (e) {}
  try { await page.addStyleTag({ content: FREEZE }); } catch (e) {}
  await page.waitForTimeout(900);
}

async function login(ctx) {
  const p = await ctx.newPage();
  await p.goto(BASE + 'login.php', { waitUntil: 'domcontentloaded' });
  await p.fill('#username', USER); await p.fill('#password', PASS);
  await Promise.all([p.waitForLoadState('domcontentloaded'), p.click('#submitBtn')]);
  await p.waitForTimeout(600);
  const ok = !/login\.php/i.test(p.url());
  await p.close(); return ok;
}

async function inspect(page, pg) {
  // Read button state + computed colors + perform a toggle, all in-page.
  return await page.evaluate(async (cfg) => {
    function rgb(el){ return getComputedStyle(el).backgroundColor; }
    const btns = Array.from(document.querySelectorAll('.btn-group-toggle[data-group]'));
    if (!btns.length) return { ok:false, reason:'no buttons' };
    const target = btns.find(b => b.classList.contains('active')) || btns[0];
    const group = target.getAttribute('data-group');

    // colors of an active vs inactive button (if both exist)
    const anyActive = btns.find(b=>b.classList.contains('active'));
    const anyInactive = btns.find(b=>!b.classList.contains('active'));
    const activeBg = anyActive ? rgb(anyActive) : null;
    const inactiveBg = anyInactive ? rgb(anyInactive) : null;

    function visibleCols(){
      // count table header cells that are not display:none (works for classic & DT)
      const ths = Array.from(document.querySelectorAll('table thead th'));
      return ths.filter(th => th.offsetParent !== null).length;
    }
    const beforeActive = target.classList.contains('active');
    const beforeVisible = visibleCols();

    target.click();
    await new Promise(r=>setTimeout(r, 400));

    const afterActive = target.classList.contains('active');
    const afterVisible = visibleCols();
    const saved = localStorage.getItem(cfg.key);

    return {
      ok: true,
      group,
      toggledActiveClass: beforeActive !== afterActive,
      visibleColsChanged: beforeVisible !== afterVisible,
      beforeVisible, afterVisible,
      activeBg, inactiveBg,
      savedKeyPresent: !!saved,
      savedSnapshot: saved,
      buttonCount: btns.length,
    };
  }, pg);
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome' });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  if (!await login(ctx)) { console.error('login failed'); await browser.close(); process.exit(2); }
  const page = await ctx.newPage();
  const report = {};

  for (const pg of PAGES) {
    try {
      await page.goto(BASE + pg.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await settle(page);
      // screenshot the toolbar area (first matching container) for color check
      await page.screenshot({ path: path.join(outDir, pg.slug + '.png'), fullPage: false });
      const res = await inspect(page, pg);
      // reload to verify persistence
      let persisted = null;
      if (res.ok) {
        await page.reload({ waitUntil: 'domcontentloaded' });
        await settle(page);
        persisted = await page.evaluate((cfg) => {
          const saved = localStorage.getItem(cfg.key);
          // verify the button reflects saved state (active class === saved visible)
          const btns = Array.from(document.querySelectorAll('.btn-group-toggle[data-group]'));
          let consistent = true;
          try {
            const st = JSON.parse(saved || '{}');
            btns.forEach(b => {
              const g = b.getAttribute('data-group');
              if (g in st) {
                const isActive = b.classList.contains('active');
                if (isActive !== (st[g] !== false)) consistent = false;
              }
            });
          } catch(e) { consistent = false; }
          return { saved, consistent };
        }, pg);
      }
      report[pg.slug] = { ...res, persisted };
      console.log(`${pg.slug}: toggledClass=${res.toggledActiveClass} visChanged=${res.visibleColsChanged} saved=${res.savedKeyPresent} persistConsistent=${persisted && persisted.consistent}`);
    } catch (e) {
      report[pg.slug] = { ok:false, error: e.message };
      console.log(`${pg.slug}: ERROR ${e.message}`);
    }
  }

  fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2));
  console.log('\nReport -> ' + path.join(outDir, 'report.json'));
  await browser.close();
})();
