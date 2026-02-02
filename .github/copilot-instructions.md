# Copilot Instructions for EMS (Equipment Management System)

## Project Overview
EMS is an Arabic-language equipment management system built with PHP, MySQL, and Bootstrap 5. It manages equipment rentals, drivers, suppliers, projects, and work hours with role-based access control.

**Database:** `equipation_manage` (MySQL via MySQLi)  
**Environment:** XAMPP/PHP 5.6+ | RTL Arabic UI

## Architecture & Core Patterns

### 1. Session-Based Authentication & Authorization
- **Auth entry point:** [index.php](index.php) - Login with brute-force protection (5 attempts, 15-min lockout), CSRF tokens, security headers
- **Session check pattern:** All pages verify `$_SESSION['user']` exists; redirect to `index.php` if missing
- **Role-based access:** Role IDs: `-1` (admin), `1` (project mgr), `2` (supplier mgr), `3` (operator mgr), `4` (fleet mgr), `5` (project user), `6` (timesheet entry), `7`/`8` (supplier/operator reviewer), `9` (breakdown reviewer)
- **Navigation filtering:** [sidebar.php](sidebar.php) - Conditionally shows menu items based on `$_SESSION['user']['role']`
- **CSRF protection:** Login uses `$_SESSION['csrf_token']` generated with `bin2hex(openssl_random_pseudo_bytes(32))`
- **Security headers:** CSP, X-Frame-Options (DENY), X-Content-Type-Options (nosniff), Referrer-Policy set in index.php

### 2. Database Interactions
- **Connection:** [config.php](config.php) - Global `$conn` (MySQLi object) - Always `include` or `require_once` at page top
- **Query pattern:** Direct SQL with `mysqli_real_escape_string()` for inputs (note: not prepared statements, except login page uses `mysqli_prepare()`)
- **Data flow:** Fetch with `mysqli_query()`, iterate with `mysqli_fetch_assoc()` or `mysqli_num_rows()`
- **CRUD operations:** Forms POST to same `.php` file; check `$_SERVER['REQUEST_METHOD'] === 'POST'` at top, then `header()` redirect on success
- **Error handling:** Check query success with `if (!$result)` then log/display with `mysqli_error($conn)`
- **Type casting:** Use `intval()` for numeric POST/GET data, `floatval()` for decimals before queries

#### Database Schema Overview (equipation_manage)

**Core Entity Tables:**
- `company_clients` - العملاء (clients with code, name, sector, contact info)
- `company_project` - المشاريع الرئيسية (main projects with location, coordinates, category)
- `operationproject` - المشاريع التشغيلية (operational projects linking company_project + company_clients)
- `suppliers` - الموردين (equipment suppliers)
- `equipments` - المعدات (equipment/machinery linked to suppliers)
- `drivers` - المشغلين (equipment operators)
- `users` - المستخدمين (system users with roles and project assignments)

**Contract Management:**
- `contracts` - عقود المشاريع (project contracts with duration, dates, equipment hours)
- `contractequipments` - معدات العقد (contract equipment details: type, count, shifts, pricing)
- `contract_notes` - سجل التدقيق (audit trail for contract actions: renewal, settlement, pause, resume, terminate, merge)
- `supplierscontracts` - عقود الموردين (supplier contracts with project_id field)
- `suppliercontractequipments` - معدات عقد المورد (supplier contract equipment details: mirrors contractequipments structure)
- `supplier_contract_notes` - ملاحظات عقود الموردين (supplier contract audit trail)
- `drivercontracts` - عقود المشغلين (driver contracts)

**Operations & Tracking:**
- `operations` - التشغيل (equipment assignments to projects with start/end dates)
- `equipment_drivers` - ربط المعدات بالسائقين (junction table: equipments ↔ drivers)
- `timesheet` - ساعات العمل (work hours tracking per operation/driver with shift details, faults, notes)

**Payment & Financial Fields (added to contracts table):**
- `price_currency_contract` - عملة العقد (currency: دولار/جنيه)
- `paid_contract` - المبلغ المدفوع (paid amount)
- `payment_time` - وقت الدفع (payment timing: مقدم/مؤخر)
- `guarantees` - الضمانات (guarantee details)
- `payment_date` - تاريخ الدفع (payment date)

**Critical Relationships:**
```
company_clients ←→ operationproject ←→ company_project
operationproject ← contracts ← contractequipments
                            ← contract_notes
suppliers ← equipments ← operations → operationproject
equipments ← equipment_drivers → drivers
operations ← timesheet → drivers
users → operationproject (project_id assignment)
```

**Status Fields Pattern:**
- Most tables have `status` TINYINT(1): `1` = active/valid, `0` = inactive/archived
- `contracts.status`: `1` = نشط (active), `0` = غير ساري (invalid) - auto-set to `0` when merged
- `contracts.contract_status`: tracks lifecycle (NULL/active/paused/terminated/merged)
- Always check status in WHERE clauses for active records: `WHERE status = 1`

### 3. Module Organization
Each major entity has its own directory with consistent structure:
- **[Drivers/drivers.php](Drivers/drivers.php)** - List/edit, form toggles with `<form>` POST handling
- **[Suppliers/suppliers.php](Suppliers/suppliers.php)** - Suppliers CRUD
- **[Projects/oprationprojects.php](Projects/oprationprojects.php)** - Operational projects CRUD (links `company_project` + `company_clients`)
- **[Projects/view_projects.php](Projects/view_projects.php)** - Company projects CRUD (JSON API pattern with `action` parameter)
- **[Projects/view_clients.php](Projects/view_clients.php)** - Company clients CRUD (JSON API pattern with `action` parameter)
- **[Equipments/equipments.php](Equipments/equipments.php)** - Equipment assignments to drivers
- **[Oprators/oprators.php](Oprators/oprators.php)** - Operations (equipment-to-project assignments) with dependent dropdown pattern for equipment type filtering
- **[Timesheet/](Timesheet/)** - Work hour tracking; [timesheet.php](Timesheet/timesheet.php) creates entries linked to operators/projects
- **[Contracts/](Contracts/)** - Contract lifecycle management with action handlers ([contract_actions_handler.php](Contracts/contract_actions_handler.php)), equipment assignments ([contractequipments](database/)), and audit trail via `contract_notes` table

**Cross-module joins** (see [Reports/reports.php](Reports/reports.php#L13)):
```
timesheet → operations → equipments → suppliers/projects
```

**Contract lifecycle workflow:**
- Create contract → Add equipment via [contractequipments_handler.php](Contracts/contractequipments_handler.php) → Perform actions (renewal, settlement, pause, resume, terminate, merge) → Track history in `contract_notes` table

**Supplier Contracts Workflow (عقود الموردين):**
- **Entry Point:** [Suppliers/supplierscontracts.php](Suppliers/supplierscontracts.php?id=SUPPLIER_ID) - accessed from supplier details page
- **Key Difference:** Supplier contracts mirror project contract structure but add `project_id` field - each supplier can have multiple contracts (one per project)
- **Equipment Management:** Uses `suppliercontractequipments` table (mirrors `contractequipments` structure) for tracking equipment assigned to supplier contracts
- **Action Handler:** [Suppliers/supplier_contract_actions_handler.php](Suppliers/supplier_contract_actions_handler.php) - handles all supplier contract lifecycle operations (renewal, settlement, pause, resume, terminate, merge)
- **Required Fields:** `supplier_id` (from URL), `project_id` (selected from operationproject dropdown), all standard contract fields
- **Contract Fields:** contract_signing_date, grace_period_days, contract_duration_months, actual_start, actual_end, transportation, accommodation, place_for_living, workshop, equip_type, equip_size, equip_count, equip_target_per_month, mach_type, mach_size, mach_count, daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two, hours_monthly_target, forecasted_contracted_hours
- **Hours Tracking System:**
  - `hours_monthly_target` - الساعات المستهدفة شهرياً (monthly target hours for equipment + machinery)
  - `forecasted_contracted_hours` - ساعات العقد المستهدفة (total contracted hours for entire contract duration)
  - **Display Pattern:** Shows project's total hours, then displays supplier's contracted portion from that total
  - **Calculation:** System auto-calculates based on equipment/machinery counts, targets, and contract duration
  - **Aggregation:** Suppliers page shows sum of all contracted hours across all supplier contracts for a project
- **View Details:** [Suppliers/showcontractsuppliers.php](Suppliers/showcontractsuppliers.php?id=CONTRACT_ID) - displays full contract information
- **View/Edit Details:** [Suppliers/supplierscontracts_details.php](Suppliers/supplierscontracts_details.php?id=CONTRACT_ID) - full contract details page with action buttons
- **Pattern:** One supplier → many contracts (across different projects) vs. One project → one contract

**Operational projects workflow (linking company projects + clients):**
- Select from `company_project` (main projects) → Select from `company_clients` (clients) → Auto-populate `name` and `client` fields from selected IDs → Duplicate prevention checks (`company_project_id` + `company_client_id` combination must be unique)

### 4. Template & Header Includes
- **Header:** [inheader.php](inheader.php) - HTML boilerplate + CSS (Bootstrap, DataTables, FontAwesome, custom style.css)
- **Sidebar:** [insidebar.php](insidebar.php) - Includes [sidebar.php](sidebar.php) for navigation
- **Usage:** Include at top of page content, set `$page_title` before including
- **Assets:** CSS in [assets/css/](assets/css/), FontAwesome webfonts in [assets/webfonts/](assets/webfonts/)
- **CDN dependencies:** jQuery, DataTables (with responsive/buttons), Bootstrap 5 loaded via CDN in inheader.php

### 5. AJAX & Dynamic Data Loading
- **Pattern:** jQuery `$.ajax()` for dependent dropdowns and form pre-loading
- **Examples:** [Timesheet/get_drivers.php](Timesheet/get_drivers.php) - GET request returns HTML `<option>` elements (no JSON)
- **Form editing:** Use `$.getJSON()` to load record data; see [Timesheet/timesheet.php](Timesheet/timesheet.php#L942) for pattern
- **Data flow:** JavaScript triggers AJAX on `change()` event → PHP helper endpoint returns HTML/data → JavaScript injects into DOM
- **Note:** Use inline `<script>` blocks in PHP files; jQuery included via CDN in [inheader.php](inheader.php)

### 6. JSON API Endpoints (AJAX Handlers)
- **Pattern:** POST-only endpoints that return JSON responses with `{'success': bool, 'message': string}`
- **Example:** [Contracts/contract_actions_handler.php](Contracts/contract_actions_handler.php) - Handles contract actions (renewal, settlement, pause, resume, terminate, merge)
- **Request validation:** Check `$_SERVER['REQUEST_METHOD'] === 'POST'` first, validate with `die(json_encode(...))` on error
- **Action routing:** Use `if/else if` chain based on `$_POST['action']` parameter
- **Response format:** Always `json_encode(['success' => true/false, 'message' => 'Arabic message'])` with `exit;` at end
- **Header setting:** Set `header('Content-Type: application/json; charset=utf-8');` for JSON endpoints
- **Client-side handling:** Use jQuery `$.ajax()` with `dataType: 'json'`, check `response.success` in callback

## Developer Workflows

### Adding a New Feature (CRUD Page)
1. Create PHP file in appropriate directory (or root for global pages)
2. Add session check + `include '../config.php'` at top
3. Handle POST submission (validation → escape strings → `mysqli_query()` → redirect on success)
4. Include header template with unique `$page_title`
5. Build HTML form + list table using DataTables
6. Add navigation link to [sidebar.php](sidebar.php) if needed (within correct role `if` block)

### Implementing Dynamic Form Behavior
- **Form visibility toggle:** Use JavaScript with `#toggleForm` button to show/hide form (e.g., `display:none` CSS toggle)
- **Dependent dropdowns:** Attach `change()` event listener to dropdown → trigger `$.ajax()` to helper endpoint → populate dependent dropdown
- **Edit mode:** Include `editBtn` class with `data-id` attribute; on click, load full record with `$.getJSON()` and populate all form fields
- **Timing consideration:** Use `setTimeout()` (300ms) after AJAX loads data before setting dependent values (e.g., [Timesheet/timesheet.php](Timesheet/timesheet.php#L942))

### Working with DataTables
- DataTables initialized with `$('#tableId').DataTable()` after table renders
- Common features: Export to PDF/Excel (via pdfmake/jszip), responsive columns, search/sort built-in
- Action buttons (Edit/Delete) added in table rows; use `data-id` to pass record ID to handlers
- Arabic language support: Set `language.url` to `"//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"`

### Date/Time Handling
- **Validation:** Use `DateTime::createFromFormat('Y-m-d', $date)` to validate date format
- **Calculation:** Create `DateTime` objects and use `diff()` for intervals (see [contract_actions_handler.php](Contracts/contract_actions_handler.php#L70))
- **Duration:** Calculate months with `$interval->m + ($interval->y * 12)`, days with `$interval->days`
- **Comparison:** Use `strtotime()` for date comparison: `if (strtotime($start) >= strtotime($end))`

### PHP Helper Functions Pattern
- **Reusable functions:** Define helper functions at top of PHP files (e.g., `getContractData()`, `addNote()`, `saveContractEquipments()`)
- **Function checks:** Wrap with `if (!function_exists('functionName'))` to prevent redeclaration errors when file is included multiple times
- **Parameter pattern:** Pass `$conn` as last parameter to all database-related functions
- **Return values:** Boolean for success/fail, or associative array for data fetches
- **Example:** See [contractequipments_handler.php](Contracts/contractequipments_handler.php) for complete pattern

### Modifying Database Queries
- Always use `mysqli_real_escape_string()` for user inputs in WHERE/INSERT/UPDATE clauses
- Example: `$_POST['name']` → escaped before SQL: `$name = mysqli_real_escape_string($conn, $_POST['name']);`
- Check for SQL errors: `if (!$result) { echo "Error: " . mysqli_error($conn); }`

### Adding Role-Based Features
1. Determine role ID needed
2. Add conditional menu link in [sidebar.php](sidebar.php): `if ($_SESSION['user']['role'] == "X") { ... }`
3. Check role in page with: `if ($_SESSION['user']['role'] != "X") { die("Unauthorized"); }`

### Creating JSON API Action Handlers
Pattern for endpoints that handle multiple related actions (see [contract_actions_handler.php](Contracts/contract_actions_handler.php)):
1. **Start with validation:**
   ```php
   if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
       die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
   }
   $action = isset($_POST['action']) ? $_POST['action'] : '';
   ```
2. **Route actions with if/elseif chain:** Each action block validates inputs, performs database operations, optionally logs to audit table
3. **Use helper functions:** Define reusable functions at top (e.g., `getContractData()`, `addNote()`)
4. **Always escape strings:** Use `mysqli_real_escape_string()` before any SQL insertion
5. **Return JSON consistently:** Success returns `['success' => true, 'message' => '...']`, errors use `die(json_encode(['success' => false, 'message' => '...']))`
6. **End with exit:** Always terminate with `exit;` after final response
7. **Handle unknown actions:** Use `else` at end: `die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));`

## Key Files Reference
| File | Purpose |
|------|---------|
| [config.php](config.php) | Database connection (`$conn` MySQLi object) - Always include first |
| [index.php](index.php) | Login + brute-force lockout (5 attempts, 15min lockout) + security headers |
| [dashbourd.php](dashbourd.php) | Dashboard (welcome page after login) |
| [sidebar.php](sidebar.php) | Navigation (role-filtered menu with icon + Arabic labels) |
| [inheader.php](inheader.php) | HTML boilerplate + CSS/JS includes (Bootstrap, DataTables, FontAwesome, jQuery) |
| [insidebar.php](insidebar.php) | Includes both header and sidebar (template wrapper) |
| [Drivers/drivers.php](Drivers/drivers.php) | Driver CRUD (form toggle pattern + DataTable list) |
| [Suppliers/suppliers.php](Suppliers/suppliers.php) | Supplier CRUD with contract management |
| [Suppliers/supplierscontracts.php](Suppliers/supplierscontracts.php) | Supplier contracts CRUD - accessed via supplier details, requires project_id |
| [Suppliers/showcontractsuppliers.php](Suppliers/showcontractsuppliers.php) | Supplier contract details view |
| [Suppliers/supplierscontracts_details.php](Suppliers/supplierscontracts_details.php) | Supplier contract full details page with action buttons |
| [Suppliers/supplier_contract_actions_handler.php](Suppliers/supplier_contract_actions_handler.php) | JSON API endpoint for supplier contract lifecycle operations (mirrors contract_actions_handler.php) |
| [Projects/oprationprojects.php](Projects/oprationprojects.php) | Operational projects CRUD (links company_project + company_clients) |
| [Projects/view_projects.php](Projects/view_projects.php) | Company projects CRUD (JSON API pattern with `action` parameter) |
| [Projects/view_clients.php](Projects/view_clients.php) | Company clients CRUD (JSON API pattern with `action` parameter) |
| [Projects/import_clients_excel.php](Projects/import_clients_excel.php) | Excel/CSV import handler for bulk client import (requires PHPSpreadsheet via Composer) |
| [Projects/download_clients_template.php](Projects/download_clients_template.php) | Excel template generator for client import |
| [Equipments/equipments.php](Equipments/equipments.php) | Equipment-to-driver assignments |
| [Timesheet/timesheet.php](Timesheet/timesheet.php) | Complex work-hour tracking (dependent dropdowns, AJAX patterns) |
| [Timesheet/get_drivers.php](Timesheet/get_drivers.php) | AJAX helper for dependent dropdown (returns `<option>` HTML) |
| [Contracts/contracts.php](Contracts/contracts.php) | Contract listing with status tracking |
| [Contracts/contracts_details.php](Contracts/contracts_details.php) | Contract detail page with action buttons (renewal, settlement, pause, resume, terminate, merge) |
| [Contracts/contract_actions_handler.php](Contracts/contract_actions_handler.php) | JSON API endpoint for all contract lifecycle operations - reference implementation for API handlers |
| [database/*.sql](database/) | Schema dumps (users, contracts, timesheets) |
| [assets/css/style.css](assets/css/style.css) | Custom styling (RTL adjustments, layout refinements) |

## Important Conventions
- **Language:** Arabic UI; English PHP comments or Arabic `// تعليق عربي`
- **Redirects:** Use `header()` after POST success, no rendering after redirect
- **URL paths:** Relative paths vary by directory depth (`../config.php` from subdirectory vs `./config.php` from root)
- **Form toggles:** Use JavaScript to show/hide forms instead of page reloads where possible
- **Security headers:** Set in login (CSP, X-Frame-Options, HttpOnly cookies) via [index.php](index.php)
- **RTL layout:** HTML `dir="rtl"` on all pages (set in [inheader.php](inheader.php)), CSS handles text alignment automatically
- **POST without redirect:** Only use when returning HTML/JSON for AJAX; normal CRUD must redirect to prevent resubmission
- **Bootstrap grid:** Use Bootstrap classes for responsive form layout (not custom CSS grid)

## Testing & Debugging
- Database: Ensure `equipation_manage` exists with tables from [database/](database/) `.sql` files
- Sessions: Check `$_SESSION['user']` structure after login (at least `['role']` key)
- Queries: Use `mysqli_error()` to debug SQL issues
- Redirects: Verify no output before `header()` calls
- Composer: Run `composer install` in project root to install dependencies (PHPSpreadsheet for Excel import/export)

## Common Pitfalls & Patterns
- **Include ordering:** Always `include '../config.php'` AFTER session checks but BEFORE any SQL queries
- **Input escaping:** Every `$_POST` or `$_GET` must be escaped with `mysqli_real_escape_string()` before SQL
- **AJAX response format:** Helper endpoints return raw HTML (`<option>` tags) or plain JSON, NOT wrapped in other markup
- **Form toggle state:** Forms use `display:none` CSS (via JavaScript) - never reload the page for show/hide
- **Time-dependent AJAX:** After loading dependent data, use `setTimeout(300)` before setting values from that data
- **Role checking:** Always check `$_SESSION['user']['role']` before processing; fail early if unauthorized
- **JSON API pattern:** For action handlers, use `die(json_encode(...))` for early returns on errors, `echo json_encode(...); exit;` for success
- **Action routing in APIs:** Use `if/elseif` chains based on `$_POST['action']` parameter; validate action at end with `else { die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف'])); }`
- **Status field filtering:** Always add `AND status = 1` to WHERE clauses when querying active records
- **Foreign key references:** Use field names as documented (e.g., `company_project_id` in operationproject, not `project_id`)
- **Duplicate prevention:** Check for unique combinations before INSERT (e.g., `company_project_id` + `company_client_id` in operationproject)
- **Equipment type filtering:** Use dependent dropdowns where equipment list depends on selected type (e.g., حفار/قلاب in operations)

### Common Query Patterns

**Get timesheet with full details (cross-module join):**
```sql
SELECT t.*, o.*, e.code, e.name AS eq_name, s.name AS supplier_name, 
       p.name AS project_name, d.name AS driver_name
FROM timesheet t
JOIN operations o ON t.operator = o.id
JOIN equipments e ON o.equipment = e.id
JOIN suppliers s ON e.suppliers = s.id
JOIN operationproject p ON o.project = p.id
JOIN drivers d ON t.driver = d.id
WHERE t.status = 1
```

**Get contract with equipment details:**
```sql
SELECT c.*, ce.*, op.name AS project_name, op.client, op.location
FROM contracts c
LEFT JOIN contractequipments ce ON c.id = ce.contract_id
JOIN operationproject op ON c.project = op.id
WHERE c.id = $contract_id
```

**Get operational project with client and company project:**
```sql
SELECT op.*, cc.client_name, cp.project_name AS company_project_name
FROM operationproject op
LEFT JOIN company_clients cc ON op.company_client_id = cc.id
LEFT JOIN company_project cp ON op.company_project_id = cp.id
WHERE op.status = 1
```

**Get supplier contracts for a specific project:**
```sql
SELECT sc.*, s.name AS supplier_name, op.name AS project_name
FROM supplierscontracts sc
JOIN suppliers s ON sc.supplier_id = s.id
JOIN operationproject op ON sc.project_id = op.id
WHERE sc.project_id = $project_id
```

**Get all contracts for a supplier (across multiple projects):**
```sql
SELECT sc.*, op.name AS project_name
FROM supplierscontracts sc
JOIN operationproject op ON sc.project_id = op.id
WHERE sc.supplier_id = $supplier_id
ORDER BY sc.contract_signing_date DESC
```

**Get total contracted hours for a project (sum all supplier contracts):**
```sql
SELECT 
    op.name AS project_name,
    SUM(sc.forecasted_contracted_hours) AS total_supplier_hours,
    COUNT(sc.id) AS supplier_contracts_count
FROM supplierscontracts sc
JOIN operationproject op ON sc.project_id = op.id
WHERE sc.project_id = $project_id
GROUP BY op.id
```
