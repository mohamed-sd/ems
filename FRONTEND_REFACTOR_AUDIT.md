# Front-End Code-Quality Audit & Refactoring Report

**Scope:** Entire `ems` project front end (CSS / HTML-in-PHP / JS).
**Hard constraint:** Zero visual change — identical layout, colours, typography, spacing, animations, responsive behaviour.
**Date:** 2026-06-02
**Verification method:** Pixel screenshot-diff harness (`.ssdiff/`) driving the real app via Chrome, authenticated, at desktop (1440) + mobile (390).

---

## 0. How this is being done safely

A screenshot-diff harness was built **before any edit** and a baseline of the current look was captured:

- `.ssdiff/shoot.js` — logs in, screenshots 35 routes × 2 viewports (70 shots), freezing CSS animation and letting Chart.js settle for deterministic output.
- `.ssdiff/compare.js` — pixel-compares `baseline/` vs `current/` (pixelmatch), emits `diff/` images + `report.html`.
- **Validated noise floor:** a no-op capture (no code change) produced **67/70 pixel-identical**. The 3 noisy shots are *dynamic data/time*, not rendering instability:
  - `Approvals/hours_approval` (5.1%) — DataTables row content reorders; filter/header area is pixel-identical.
  - `ActivityLogs/activity_logs` desktop/mobile (<0.01%) — relative timestamps.

**Workflow per refactor slice:** edit → `node shoot.js current` → `node compare.js` → inspect `report.html`. A slice passes only when every diff is either 0% or confined to those known dynamic regions.

> The harness, test user, and baseline live under `.ssdiff/` (git-ignored). A throwaway DB user `ssbot` (role 1, company 4) was created to drive it and will be removed at the end.

---

## 1. Inventory & scale

| Asset | Count / size |
|---|---|
| PHP files | 228 |
| Inline `style="…"` occurrences | **1,309** |
| `<style>` blocks (in 60+ PHP files) | **71** |
| Project CSS files (non-vendor) | 14 |
| Project JS files (non-vendor) | 5 |

### Project CSS files
| File | Size | Active `<link>`s | Status |
|---|---|---|---|
| `ems.main.all.style.css` | 314 KB | 10 | **Theme source** (live yellow `#f3be00`). Off-limits except careful internal dedupe. |
| `main_admin_style.css` | 51 KB | 18 | Admin portal main stylesheet. |
| `admin.css` | 45 KB | **0** | **DEAD** — older renamed copy of `admin-style.css`. Unreferenced anywhere (PHP/JS/HTML/CSS). |
| `style.css` | 18 KB | 12 | Used by Reports/Drivers/Settings pages. |
| `allstyle.css` | 15 KB | **0** | **DEAD** — `@import` aggregator (style+admin-style+main_admin_style+brand-identity). Unreferenced. |
| `alltables.css` | 13 KB | 1 (in `inheader.php`) | Loaded app-wide via `inheader.php`. |
| `site-identity.css` | 11 KB | 10 | |
| `brand-identity.css` | 7 KB | 1 active / 1 commented | |
| `design-tokens.css` | 5 KB | 3 | Clean token system — **not** the live theme (overridden by `ems.main.all.style.css`). |
| `local-fonts.css` | 4 KB | 39 | Font-face declarations, loaded almost everywhere. |
| `admin-style.css` | 2 KB | 10 | |
| `main_admin_style.css`, vendor (bootstrap, datatables, fontawesome) | — | — | Vendor — do not touch. |

---

## 2. Architectural problems found

1. **Three competing page-assembly mechanisms** load different stylesheet sets:
   - `inheader.php` (main app, ~50 pages) → `all.min.css`, `bootstrap.min.css`, datatables, `alltables.css`, `local-fonts.css`, `design-tokens.css`, `ems.main.all.style.css`.
   - `includes/layout_head.php` (some modules).
   - `admin/includes/layout_head.php` (admin portal) → admin stylesheets.

2. **Fragmented / duplicated CSS-variable systems** that disagree:
   - `design-tokens.css` → `--brand-orange #F7931A`, full `--space-*`, `--radius-*`, `--shadow-*` scale.
   - `ems.main.all.style.css` → live `--gold #f3be00` (wins on app pages), **3 separate `:root` blocks** in one file.
   - `admin.css` / `allstyle.css` / `main_admin_style.css` → `--navy`, `--ease`, `--red-soft` etc.
   - `style.css` → 2 `:root` blocks.
   - **Consequence:** some inline styles on app pages use `var(--navy)`, `var(--ease)`, `var(--red-soft)` that are **not defined in any stylesheet those pages load** → they resolve to inherited/initial. This is fragile *current* behaviour that must be preserved exactly.

3. **Inline `<style>` blocks duplicated across pages** — e.g. `.filters-container` / `.filters-header` / `.btn-clear-filters` defined inline in both `Equipments/equipments.php` and `Equipments/equipments_drivers.php` (and the filter pattern recurs across modules). Prime extraction candidates.

4. **JS-driven styling** — `assets/js/ui-unification.js` **adds classes at runtime** (`alltables`, `alltable`, `header-actions-group`, `title-content`, `back-btn`) and toggles `.style.display`. ⇒ **Dead-class detection must grep JS as well as PHP**, or live classes will be wrongly deleted.

---

## 3. Dead CSS (safe to remove)

| Item | Evidence |
|---|---|
| `assets/css/admin.css` | 0 references in any `.php/.js/.html/.css`. Superseded by `admin-style.css`. |
| `assets/css/allstyle.css` | 0 references. `@import` aggregator never linked. |
| Commented-out `<link>`s in `inheader.php` (`brand-identity`, `style.css`, `site-identity`) | Dead markup lines 28/30/31. |

> Class-level dead-CSS pruning inside the large files is deferred to per-module slices, each grep-checked against **PHP + JS** and screenshot-verified, because of runtime class injection (§2.4).

---

## 4. Inline-style report (top repeated values → utility-class candidates)

| Count | Inline value | Proposed class |
|---|---|---|
| 54 | `margin-left:0.5rem` | `.ms-2` (already in Bootstrap) / `.u-ms-2` |
| 29 | `color:red` | `.text-danger` (Bootstrap) / `.u-text-error` |
| 23 | `padding:6px;border:1px solid #e9ecef` | `.u-cell-box` |
| 19 | `margin:0` | `.u-m-0` |
| 19 | `display:none` | `.u-hidden` / Bootstrap `.d-none` |
| 16 | `text-align:center` | `.u-text-center` / `.text-center` |
| 14 | `padding:8px 12px` | `.u-pad-sm` |
| 13 + 8 | `width:100%` | `.u-w-100` / `.w-100` |
| 12 | `font-size:0.85rem;font-weight:600` | `.u-label-strong` |
| 11 | `text-align:center;color:#999` | `.u-empty-note` |

Full per-file inline counts (top): `Timesheet/timesheet.php` 138, `Drivers/drivercontracts_details.php` 121, `Suppliers/supplierscontracts_details.php` 79, `Suppliers/supplierscontracts.php` 78, `Approvals/hours_approval.php` 70, `index.php` 60, `admin/companies/view.php` 49, `Timesheet/timesheet_details.php` 43, `Equipments/equipments_drivers.php` 41, `Equipments/equipments.php` 41 …

---

## 5. `<style>`-block burden (refactor priority by lines)

`movement/add_drivers.php` 849 · `main/dashboard.php` 823 *(off-limits — project memory)* · `index.php` 777 · `Timesheet/timesheet_details.php` 433 · `admin/includes/layout_head.php` 367 · `Timesheet/view_timesheet.php` 355 · `Drivers/drivercontracts_details.php` 349 · `movement/movement_operations.php` 323 · `login.php` 311 · `emsreports/reports/_report_template.php` 310 · `Settings/role_permissions.php` 306 …

---

## 6. Proposed refactor sequence (each slice screenshot-verified)

1. **Slice 1 — safe wins (no risk):** delete `admin.css` + `allstyle.css`; remove commented-out `<link>`s in `inheader.php`. *(in progress)*
2. **Slice 2 — CSS file housekeeping:** within `ems.main.all.style.css` merge its 3 `:root` blocks; within `style.css` merge its 2 `:root` blocks (no value changes, just consolidation).
3. **Slice 3 — shared component extraction:** move duplicated inline `.filters-*` blocks (Equipments pages first) into one shared stylesheet; replace per-page copies.
4. **Slices 4…N — module-by-module inline-style reduction:** Equipments → Drivers → Suppliers → Timesheet → Reports → Settings → admin. Convert high-frequency inline styles (§4) to utility/component classes; verify each module's screenshots before moving on.

---

## 7. Refactoring change-log

| # | File(s) | Change | Reason | Verified |
|---|---|---|---|---|
| 1 | `assets/css/admin.css`, `assets/css/allstyle.css` | **Deleted** | Provably dead — 0 references in any PHP/JS/HTML/CSS. | ✅ 70/70 unchanged (only the 3 known dynamic-data shots differ, same as noise floor). |
| 1 | `inheader.php` | Removed 3 commented-out `<link>` lines + a stale comment | Dead markup. | ✅ (same run as above). |
| 3 | `Equipments/equipments.php` | **Attempted** removal of inline `.filters-*` `<style>` block; **reverted**. | Block looked like a duplicate of `ems.main.all.style.css`, but harness showed it is a **load-bearing override** (removal changed layout 2–6% + page height). Kept as-is. | ✅ Harness correctly rejected the change; file reverted to baseline. |
| 4 | `Settings/modules.php` | **Attempted** full inline-style → semantic-class conversion (19 → 2 inline); **reverted**. | (a) Two chosen class names (`btn-action-edit/delete`) **collided** with existing rules in `main_admin_style.css`; (b) even after renaming, converting the table-cell inline styles **broke the DataTables Responsive layout** (all data columns collapsed to expand-arrows). Harness caught it (page rendered with empty table). | ✅ Reverted to baseline (verified pixel-clean). |
| 5 | **NEW** `includes/page_header.php` + `Clients/clients.php` | Created a reusable header component that emits the `.main_head` structure declaratively; migrated `clients.php`'s hand-written header (≈47 lines) to a short data array + `include`. | Header structure now lives in **one** file (styling stays in `ems.main.all.style.css`). This is the professional pattern for retiring the runtime `ui-unification.js` header rebuild. **First conversion to pass the zero-visual bar.** | ✅ **Pixel-identical** (no diff image produced for either viewport). |

| 6 | **All 36 `main_head` pages** + `includes/page_header.php` | Migrated every hand-written `.main_head` header to the shared component (declarative actions/title/back arrays + `include`). Component grew (backward-compatibly) to support: `<button>`/`<a>`, `disabled`, `style`/`attrs` passthrough, `label_class`, raw title HTML, `span` icon tag, multi-item & omittable back area, and a `raw` escape hatch. | Header **structure** now lives in ONE file; styling stays in `ems.main.all.style.css`. Zero hand-written `.main_head` blocks remain (only the component has it). | ✅ Each batch screenshot-verified pixel-identical (renderers); redirect-gated pages verified via before/after stash (no regression). |

| 7 | `Approvals/requests.php`, `Equipments/equipments.php`, `Settings/modules.php`, `Settings/roles.php` + `assets/js/ui-unification.js` | Baked the final `.header` structure (actions/title/back, `data-ems-unified-header="1"`) into the only 4 pages that actually depended on the JS header rebuild; then **removed the header-normalization logic from `ui-unification.js`** (kept its table-unification + auto-DataTables logic). | The runtime DOM header rebuild is gone; those pages now ship their final header in markup, styled purely by CSS. | ✅ Headers verified pixel-identical (data-table bodies show only pre-existing DataTables reflow noise); full sweep re-run after JS removal. |

| 8 | `assets/css/alltables.css` → **`assets/css/ems-tables.css`** + `inheader.php`, `insidebar.php`, comment refs in `ems.main.all.style.css` & `main_admin_style.css` | **Renamed** the app-wide table stylesheet to `ems-tables.css` via `git mv` (history preserved), repointed both `<link>` loaders and their cache-buster calls, and updated the 5 stale comment references. **Same cascade position (loaded just before `ems.main.all.style.css`)** → no value/order change. Added a header comment to `ems-tables.css` documenting its role + what table CSS intentionally stays elsewhere. | Establish a clearly-named single central table stylesheet (user request, 2026-06-10). "Safe staged" scope: only the cascade-preserving rename was applied. Moving the theme-level table overrides (entangled 4-block `!important` winners in `ems.main.all.style.css`), `style.css` table rules (separate stack, mostly outside screenshot coverage), admin-portal table CSS (`main_admin_style.css`, separate stack) or load-bearing inline `<style>` blocks would **violate full cascade preservation** and was deliberately deferred. | ✅ Screenshot harness (role-12 `ssbot`, 70 shots): **66/70 pixel-identical**. The other 4 = known noise: 2× `ActivityLogs` timestamps (<0.02%) + `Settings/modules`/`roles` flaky DataTables-Responsive collapse (proven flaky — `roles` flipped DIFF→OK across identical runs; probe rendered all 7 columns 12/12; rename changes no CSS value). |

| 9 | `assets/css/ems-tables.css`, `inheader.php`, `insidebar.php` | **Attempted** making `ems-tables.css` the sole authoritative table source (copy the dominant "FINAL UNIFIED THEME" table block into it + load it LAST); **reverted**. | User wanted one file to control all table design (the `thead{background:red}` probe proved `ems-tables.css` was NOT authoritative — overridden by `ems.main`). Diagnosis: table look is a **7-layer `!important` lasagna** in `ems.main.all.style.css` across 3 scopes (`body.ems-site .main :is(table,…)` dominant winner fused with the header theme; `.ems-site table`; bare `table`), plus `ems-tables.css`'s own competing gold system, plus page-specific blocks + `style.css`/`site-identity.css`/admin + ~70 inline `<style>` blocks. Loading the whole `ems-tables.css` last **regressed 6 pages** (Equipments/equipments_drivers/Contracts/equipments_fleet) — its old gold table-core surfaced and beat page-specific rules. | ✅ Harness (same-bot baseline): attempt showed 6 real diffs; **revert returns to 67/70 clean** (only flaky modules/roles Responsive collapse + ActivityLogs timestamps). True single-source = a dedicated untangle project (prune `ems-tables.css` core + delete all `ems.main` layers + cover non-`.main` tables + de-fuse from header theme), screenshot-gated. |

### Reality check on the "62 header pages"
The original estimate of 62 was wrong — that grep matched `card-header`/`modal-header` substrings. A rendered-DOM probe showed the JS header rebuild only ever affected **4 pages** (the rest either use a separate `style.css` header system that never loads `ui-unification.js`, or had commented-out `.header` blocks). `ui-unification.js` is loaded **only** by `inheader.php`; admin pages use their own layout.

### Header-unification path (started in Slice 5)
There are two header systems: **`main_head`** (38 pages, CSS-only, *not* touched by JS) and **`.header`/`.page-header`** (62 pages, restructured at runtime by `assets/js/ui-unification.js` — 22 DOM ops). The JS cannot move to CSS (it creates wrapper elements, regroups scattered buttons, detects the back button by Arabic text — none of which CSS can do). The professional fix is to bake the unified structure into the markup at the source via `includes/page_header.php`, then retire the JS **header** logic last (keep its table-unification logic). Migrating each page is screenshot-gated. `clients.php` (a `main_head` page) is the proven first migration.

### Key learning that reshapes the plan
Two careful conversion attempts (Equipments §3, Settings §4) both regressed and were reverted. The pattern is now clear and consistent:

1. **Inline styles here are systematically load-bearing.** They win by specificity (1000) over an entangled stack of conflicting stylesheets (`ems.main.all.style.css`, `main_admin_style.css`, `admin-style.css`, Bootstrap). Lowering them to normal classes (specificity 10–20) lets the underlying conflicting rules re-emerge → colours/layout change.
2. **Generic class names collide** with names already defined in the admin stylesheets (e.g. `btn-action-edit`), silently importing foreign styles.
3. **DataTables Responsive** recomputes column visibility from layout; small CSS deltas can flip it into collapsing every column.

**Consequence for the spec:** "remove all inline styles / merge identical styles / drop `!important`" **cannot** be met at scale without first *untangling the underlying stylesheet conflicts* — otherwise every removal is a visual regression. Recommended real path (a larger architectural effort, each step screenshot-gated):

1. **Consolidate the competing CSS systems** into one coherent layer (reconcile `design-tokens.css` ↔ `ems.main.all.style.css` ↔ admin set) so that a single source defines each component once.
2. *Only then* do inline styles become genuinely redundant and removable safely.

The safe, repeatable workflow (edit → `shoot current` → `compare` → inspect `report.html` → keep/revert) is established and **proven in both directions**: it accepted the safe dead-file deletes (Slice 1) and rejected both regressions (Slices 3 & 4). Net inline-style count is unchanged from baseline because no conversion has yet passed the zero-visual-change bar; the dead-CSS removal (Slice 1) is the only landed change.

*(updated as each slice lands)*
