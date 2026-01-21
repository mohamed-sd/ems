# Copilot Instructions for EMS (Equipment Management System)

## Project Overview
EMS is an Arabic-language equipment management system built with PHP, MySQL, and Bootstrap 5. It manages equipment rentals, drivers, suppliers, projects, and work hours with role-based access control.

**Database:** `equipation_manage` (MySQL via MySQLi)  
**Environment:** XAMPP/PHP 5.6+ | RTL Arabic UI

## Architecture & Core Patterns

### 1. Session-Based Authentication & Authorization
- **Auth entry point:** [index.php](index.php) - Login with brute-force protection (5 attempts, 15-min lockout)
- **Session check pattern:** All pages verify `$_SESSION['user']` exists; redirect to `index.php` if missing
- **Role-based access:** Role IDs: `-1` (admin), `1` (project mgr), `2` (supplier mgr), `3` (operator mgr), `4` (fleet mgr), `5` (project user)
- **Navigation filtering:** [sidebar.php](sidebar.php) - Conditionally shows menu items based on `$_SESSION['user']['role']`

### 2. Database Interactions
- **Connection:** [config.php](config.php) - Global `$conn` (MySQLi object) - Always `include` or `require_once` at page top
- **Query pattern:** Direct SQL with `mysqli_real_escape_string()` for inputs (note: not prepared statements)
- **Data flow:** Fetch with `mysqli_query()`, iterate with `mysqli_fetch_assoc()` or `mysqli_num_rows()`
- **CRUD operations:** Forms POST to same `.php` file; check `$_SERVER['REQUEST_METHOD'] === 'POST'` at top, then `header()` redirect on success

### 3. Module Organization
Each major entity has its own directory with consistent structure:
- **[Drivers/drivers.php](Drivers/drivers.php)** - List/edit, form toggles with `<form>` POST handling
- **[Suppliers/suppliers.php](Suppliers/suppliers.php)** - Suppliers CRUD
- **[Projects/projects.php](Projects/projects.php)** - Projects CRUD with client, location, total cost
- **[Equipments/equipments.php](Equipments/equipments.php)** - Equipment assignments to drivers
- **[Timesheet/](Timesheet/)** - Work hour tracking; [timesheet.php](Timesheet/timesheet.php) creates entries linked to operators/projects

**Cross-module joins** (see [Reports/reports.php](Reports/reports.php#L13)):
```
timesheet → operations → equipments → suppliers/projects
```

### 4. Template & Header Includes
- **Header:** [inheader.php](inheader.php) - HTML boilerplate + CSS (Bootstrap, DataTables, FontAwesome, custom style.css)
- **Sidebar:** [insidebar.php](insidebar.php) - Includes [sidebar.php](sidebar.php) for navigation
- **Usage:** Include at top of page content, set `$page_title` before including
- **Assets:** CSS in [assets/css/](assets/css/), FontAwesome webfonts in [assets/webfonts/](assets/webfonts/)

### 5. AJAX & Dynamic Data Loading
- **Pattern:** jQuery `$.ajax()` for dependent dropdowns and form pre-loading
- **Examples:** [Timesheet/get_drivers.php](Timesheet/get_drivers.php) - GET request returns HTML `<option>` elements (no JSON)
- **Form editing:** Use `$.getJSON()` to load record data; see [Timesheet/timesheet.php](Timesheet/timesheet.php#L942) for pattern
- **Data flow:** JavaScript triggers AJAX on `change()` event → PHP helper endpoint returns HTML/data → JavaScript injects into DOM
- **Note:** Use inline `<script>` blocks in PHP files; jQuery included via CDN in [inheader.php](inheader.php)

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

### Modifying Database Queries
- Always use `mysqli_real_escape_string()` for user inputs in WHERE/INSERT/UPDATE clauses
- Example: `$_POST['name']` → escaped before SQL: `$name = mysqli_real_escape_string($conn, $_POST['name']);`
- Check for SQL errors: `if (!$result) { echo "Error: " . mysqli_error($conn); }`

### Adding Role-Based Features
1. Determine role ID needed
2. Add conditional menu link in [sidebar.php](sidebar.php): `if ($_SESSION['user']['role'] == "X") { ... }`
3. Check role in page with: `if ($_SESSION['user']['role'] != "X") { die("Unauthorized"); }`

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
| [Projects/projects.php](Projects/projects.php) | Project CRUD (client, location, cost tracking) |
| [Equipments/equipments.php](Equipments/equipments.php) | Equipment-to-driver assignments |
| [Timesheet/timesheet.php](Timesheet/timesheet.php) | Complex work-hour tracking (dependent dropdowns, AJAX patterns) |
| [Timesheet/get_drivers.php](Timesheet/get_drivers.php) | AJAX helper for dependent dropdown (returns `<option>` HTML) |
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

## Common Pitfalls & Patterns
- **Include ordering:** Always `include '../config.php'` AFTER session checks but BEFORE any SQL queries
- **Input escaping:** Every `$_POST` or `$_GET` must be escaped with `mysqli_real_escape_string()` before SQL
- **AJAX response format:** Helper endpoints return raw HTML (`<option>` tags) or plain JSON, NOT wrapped in other markup
- **Form toggle state:** Forms use `display:none` CSS (via JavaScript) - never reload the page for show/hide
- **Time-dependent AJAX:** After loading dependent data, use `setTimeout(300)` before setting values from that data
- **Role checking:** Always check `$_SESSION['user']['role']` before processing; fail early if unauthorized
