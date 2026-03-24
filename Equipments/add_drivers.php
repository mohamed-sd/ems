<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die('معرّف الشركة غير متوفر');
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$drivers_has_company = db_table_has_column($conn, 'drivers', 'company_id');
$drivers_has_supplier = db_table_has_column($conn, 'drivers', 'supplier_id');
$suppliers_has_company = db_table_has_column($conn, 'suppliers', 'company_id');

$equipment_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($equipments_has_company) {
        $equipment_scope_sql = "e.company_id = $company_id";
    } else {
        $equipment_scope_sql = "EXISTS (
            SELECT 1
            FROM operations so
            JOIN project sp ON sp.id = so.project_id
            WHERE so.equipment = e.id
              AND (
                  EXISTS (SELECT 1 FROM users su WHERE su.id = sp.created_by AND su.company_id = $company_id)
                  OR EXISTS (
                      SELECT 1
                      FROM clients sc
                      JOIN users scu ON scu.id = sc.created_by
                      WHERE sc.id = sp.company_client_id AND scu.company_id = $company_id
                  )
              )
        )";
    }
}

$driver_scope_sql = '1=1';
if (!$is_super_admin) {
    if ($drivers_has_company) {
        $driver_scope_sql = "d.company_id = $company_id";
    } elseif ($drivers_has_supplier && $suppliers_has_company) {
        $driver_scope_sql = "EXISTS (
            SELECT 1
            FROM suppliers ds
            WHERE ds.id = d.supplier_id
              AND ds.company_id = $company_id
        )";
    } else {
        $driver_scope_sql = "0=1";
    }
}

$equipment_id = intval($_GET['equipment_id']);

// Ø¬Ù„Ø¨ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ù…Ø¹Ø¯Ø©
$equipment_query = "SELECT e.*, s.name as supplier_name 
                    FROM equipments e 
                    LEFT JOIN suppliers s ON e.suppliers = s.id 
                    WHERE e.id = $equipment_id AND $equipment_scope_sql";
$equipment_result = mysqli_query($conn, $equipment_query);
$equipment = mysqli_fetch_assoc($equipment_result);
if (!$equipment) {
    die('المعدة غير موجودة أو خارج نطاق الشركة');
}

// Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ø§Ù„Ù…Ø±ØªØ¨Ø·ÙŠÙ† Ù…Ø³Ø¨Ù‚Ù‹Ø§
$current = [];
$linked = [];
$res = mysqli_query($conn, "SELECT ed.id, ed.start_date, ed.end_date, d.id AS driver_id, d.name, d.phone, ed.status
                             FROM equipment_drivers ed
                             JOIN drivers d ON ed.driver_id = d.id
                                                         WHERE ed.equipment_id = $equipment_id
                                                             AND $driver_scope_sql" . (($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND ed.company_id = $company_id"));
while ($r = mysqli_fetch_assoc($res)) {
    $current[] = $r['driver_id'];
    $linked[] = $r;
}

$page_title = "Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø¥Ø¯Ø§Ø±Ø© Ù…Ø´ØºÙ„ÙŠ Ø§Ù„Ù…Ø¹Ø¯Ø©";
include("../inheader.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<style>
/* Ø§Ø³ØªØ®Ø¯Ø§Ù… Ù†ÙØ³ Ù†Ø¸Ø§Ù… Ø§Ù„Ø£Ù„ÙˆØ§Ù† Ø§Ù„Ù…ÙˆØ­Ø¯ Ù„Ù„Ù†Ø¸Ø§Ù… */
:root {
    --navy:        #0c1c3e;
    --navy-m:      #132050;
    --navy-l:      #1b2f6e;
    --gold:        #e8b800;
    --gold-l:      #ffd740;
    --gold-soft:   rgba(232,184,0,.13);
    --bg:          #f0f2f8;
    --surface:     #ffffff;
    --border:      rgba(12,28,62,.07);
    --txt:         #0c1c3e;
    --sub:         #64748b;
    --green:       #16a34a;
    --green-soft:  rgba(22,163,74,.11);
    --red:         #dc2626;
    --red-soft:    rgba(220,38,38,.10);
    --blue:        #2563eb;
    --blue-soft:   rgba(37,99,235,.10);
    --orange:      #ea6f00;
    --orange-soft: rgba(234,111,0,.10);
    --shadow-sm:   0 1px 5px rgba(12,28,62,.06);
    --shadow-md:   0 5px 20px rgba(12,28,62,.09);
    --shadow-lg:   0 14px 44px rgba(12,28,62,.13);
    --radius:      12px;
    --radius-lg:   18px;
    --ease:        .22s cubic-bezier(.4,0,.2,1);
}

body {
    font-family: 'Cairo', sans-serif;
    background: var(--bg);
}

.main {
    padding: 20px;
    transition: all 0.4s ease;
    width: 100%;
    background: var(--bg);
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
    animation: slideDown 0.5s cubic-bezier(.4,0,.2,1);
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-12px); }
    to   { opacity:1; transform:translateY(0); }
}

.page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.35rem;
    font-weight: 900;
    color: var(--txt);
    margin: 0;
    font-family: 'Cairo', sans-serif;
}

.title-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    color: var(--gold);
    box-shadow: var(--shadow-md);
    flex-shrink: 0;
}

.equipment-info {
    background: var(--gold-soft);
    padding: 12px 20px;
    border-radius: var(--radius);
    border: 1.5px solid rgba(232,184,0,.28);
}

.equipment-info h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
}

.equipment-info p {
    margin: 5px 0 0;
    font-size: 0.88rem;
    color: var(--sub);
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 50px;
    font-weight: 700;
    font-size: .82rem;
    transition: all var(--ease);
    border: 1.5px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    font-family: 'Cairo', sans-serif;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-primary,
.btn-success {
    background: var(--gold-soft);
    color: var(--navy);
    border-color: rgba(232,184,0,.28);
}

.btn-primary:hover,
.btn-success:hover {
    background: var(--gold);
    color: var(--navy);
    box-shadow: 0 5px 16px rgba(232,184,0,.35);
}

.btn-secondary {
    background: var(--blue-soft);
    color: var(--blue);
    border-color: rgba(37,99,235,.18);
}

.btn-secondary:hover {
    background: var(--blue);
    color: white;
    box-shadow: 0 5px 16px rgba(37,99,235,.35);
}

.btn-danger {
    background: var(--red-soft);
    color: var(--red);
    border-color: rgba(220,38,38,.18);
}

.btn-danger:hover {
    background: var(--red);
    color: white;
    box-shadow: 0 5px 16px rgba(220,38,38,.35);
}

.card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    margin-bottom: 22px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.card-header {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: white;
    padding: 16px 22px;
    font-weight: 700;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-body {
    padding: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 700;
    color: var(--txt);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
}

.form-group input,
.form-group select {
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.92rem;
    font-family: 'Cairo', sans-serif;
    transition: all var(--ease);
    background: var(--surface);
    color: var(--txt);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-soft);
}

.form-group select[multiple] {
    min-height: 200px;
    padding: 10px;
}

.form-group select[multiple] option {
    padding: 10px;
    margin: 3px 0;
    border-radius: var(--radius);
    cursor: pointer;
}

.form-group select[multiple] option:hover {
    background: var(--navy);
    color: var(--gold);
}

/* Ù†Ø¸Ø§Ù… Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ† Ø§Ù„Ø§Ø­ØªØ±Ø§ÙÙŠ */
.drivers-selection-container {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px;
    margin-top: 12px;
}

.selection-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 12px;
}

.search-box {
    flex: 1;
    min-width: 280px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 11px 44px 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 50px;
    font-size: 0.9rem;
    font-family: 'Cairo', sans-serif;
    transition: all var(--ease);
    background: var(--surface);
}

.search-box input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-soft);
}

.search-box i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--sub);
    pointer-events: none;
}

.selection-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.selection-controls .btn {
    padding: 8px 16px;
    font-size: 0.8rem;
}

.selected-count {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    background: var(--gold-soft);
    color: var(--navy);
    border: 1.5px solid rgba(232,184,0,.28);
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.85rem;
    animation: pulse 0.3s ease;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.drivers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    max-height: 450px;
    overflow-y: auto;
    padding: 8px;
    margin-top: 14px;
}

.drivers-grid::-webkit-scrollbar {
    width: 8px;
}

.drivers-grid::-webkit-scrollbar-track {
    background: var(--bg);
    border-radius: var(--radius);
}

.drivers-grid::-webkit-scrollbar-thumb {
    background: var(--gold);
    border-radius: var(--radius);
}

.driver-card {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    cursor: pointer;
    transition: all var(--ease);
    position: relative;
    overflow: hidden;
}

.driver-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 4px;
    height: 100%;
    background: var(--border);
    transition: all var(--ease);
}

.driver-card:hover {
    border-color: var(--gold);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.driver-card:hover::before {
    background: var(--gold);
    width: 6px;
}

.driver-card.selected {
    border-color: var(--gold);
    background: var(--gold-soft);
    box-shadow: 0 0 0 3px rgba(232,184,0,.15);
}

.driver-card.selected::before {
    background: var(--gold);
    width: 6px;
}

.driver-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.driver-checkbox {
    width: 22px;
    height: 22px;
    border: 2px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
    transition: all var(--ease);
    flex-shrink: 0;
    position: relative;
    background: var(--surface);
}

.driver-card.selected .driver-checkbox {
    background: var(--gold);
    border-color: var(--gold);
}

.driver-card.selected .driver-checkbox::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--navy);
    font-size: 0.75rem;
}

.driver-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: var(--shadow-sm);
}

.driver-info {
    flex: 1;
    overflow: hidden;
}

.driver-name {
    font-weight: 700;
    color: var(--navy);
    font-size: 0.95rem;
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.driver-phone {
    color: var(--sub);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.no-drivers-message {
    text-align: center;
    padding: 40px 20px;
    color: var(--sub);
}

.no-drivers-message i {
    font-size: 3rem;
    margin-bottom: 14px;
    color: var(--gold);
    opacity: 0.4;
}

.no-drivers-message p {
    font-size: 0.95rem;
    margin: 0;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1.5px solid var(--border);
}

.alert {
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    animation: slideDown 0.4s ease;
}

.alert-info {
    background: var(--blue-soft);
    color: var(--blue);
    border: 1.5px solid rgba(37,99,235,.25);
}

.alert-success {
    background: var(--green-soft);
    color: var(--green);
    border: 1.5px solid rgba(22,163,74,.25);
}

.table-container {
    overflow-x: auto;
}

table.dataTable {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0;
}

table.dataTable thead th {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: white;
    padding: 14px;
    font-weight: 700;
    text-align: center;
    border: none;
    font-size: 0.9rem;
}

 table.dataTable tbody td {
    padding: 14px;
    text-align: center;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--txt);
}

table.dataTable tbody tr:hover {
    background: var(--gold-soft);
    transition: all var(--ease);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
}

.status-active {
    background: var(--green-soft);
    color: var(--green);
    border: 1.5px solid rgba(22,163,74,.22);
}

.status-inactive {
    background: var(--red-soft);
    color: var(--red);
    border: 1.5px solid rgba(220,38,38,.22);
}

.action-btns {
    display: flex;
    gap: 7px;
    justify-content: center;
    align-items: center;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: var(--radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all var(--ease);
    font-size: 0.9rem;
    border: 1.5px solid transparent;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.action-btn.edit {
    background: var(--blue-soft);
    color: var(--blue);
    border-color: rgba(37,99,235,.2);
}

.action-btn.edit:hover {
    background: var(--blue);
    color: white;
}

.action-btn.delete {
    background: var(--red-soft);
    color: var(--red);
    border-color: rgba(220,38,38,.2);
}

.action-btn.delete:hover {
    background: var(--red);
    color: white;
}

.action-btn.activate {
    background: var(--green-soft);
    color: var(--green);
    border-color: rgba(22,163,74,.2);
}

.action-btn.activate:hover {
    background: var(--green);
    color: white;
}

.dt-buttons {
    margin-bottom: 14px;
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.dt-button {
    background: linear-gradient(135deg, var(--navy), var(--navy-l)) !important;
    color: white !important;
    border: 1.5px solid rgba(232,184,0,.28) !important;
    padding: 8px 16px !important;
    border-radius: 50px !important;
    font-family: 'Cairo', sans-serif !important;
    font-weight: 700 !important;
    font-size: 0.82rem !important;
    cursor: pointer !important;
    transition: all var(--ease) !important;
}

.dt-button:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md) !important;
    background: linear-gradient(135deg, var(--navy-l), var(--navy)) !important;
    border-color: var(--gold) !important;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--sub);
}

.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 18px;
    color: var(--gold);
    opacity: 0.4;
}

.empty-state h3 {
    font-size: 1.3rem;
    margin-bottom: 10px;
    color: var(--txt);
    font-weight: 700;
}

.empty-state p {
    font-size: 0.95rem;
    margin-bottom: 20px;
    color: var(--sub);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        text-align: center;
        align-items: stretch;
    }
    
    .btn-group {
        width: 100%;
        justify-content: center;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .equipment-info {
        text-align: center;
    }
    
    .page-title {
        justify-content: center;
    }
}
</style>

<?php include('../insidebar.php'); ?>

<div class="main">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <div class="title-icon"><i class="fas fa-users-cog"></i></div>
                Ø¥Ø¯Ø§Ø±Ø© Ù…Ø´ØºÙ„ÙŠ Ø§Ù„Ù…Ø¹Ø¯Ø©
            </h1>
            <?php if ($equipment): ?>
            <div class="equipment-info">
                <h3><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($equipment['name']); ?></h3>
                <p><i class="fas fa-barcode"></i> Ø§Ù„ÙƒÙˆØ¯: <strong><?php echo htmlspecialchars($equipment['code']); ?></strong> | 
                   <i class="fas fa-building"></i> Ø§Ù„Ù…ÙˆØ±Ø¯: <strong><?php echo htmlspecialchars($equipment['supplier_name'] ?: 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯'); ?></strong></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <a href="equipments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Ø¥Ø³Ù†Ø§Ø¯ Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© Ù…Ø´ØºÙ„ -->
    <div class="card" id="projectForm" style="display: none;">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> Ø¥Ø³Ù†Ø§Ø¯ Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯ Ù„Ù„Ù…Ø¹Ø¯Ø©
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Ù…Ù„Ø§Ø­Ø¸Ø©:</strong> ÙŠÙ…ÙƒÙ†Ùƒ Ø§Ø®ØªÙŠØ§Ø± Ø£ÙƒØ«Ø± Ù…Ù† Ù…Ø´ØºÙ„ Ø¨Ø§Ù„Ù†Ù‚Ø± Ø¹Ù„Ù‰ Ø§Ù„Ø¨Ø·Ø§Ù‚Ø§Øª.
                    <br>Ø§Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø¨Ø­Ø« Ù„Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ù…Ø´ØºÙ„ Ù…Ø­Ø¯Ø¯ØŒ Ø£Ùˆ Ø§Ø³ØªØ®Ø¯Ù… "ØªØ­Ø¯ÙŠØ¯ Ø§Ù„ÙƒÙ„" Ù„Ø§Ø®ØªÙŠØ§Ø± Ø¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ø§Ù„Ù…ØªØ§Ø­ÙŠÙ†.
                    <br>Ø§Ù„Ù…Ø´ØºÙ„ÙˆÙ† Ø§Ù„Ù…Ø¹Ø±ÙˆØ¶ÙˆÙ† Ù‡Ù… Ø§Ù„Ø°ÙŠÙ† Ù„Ù… ÙŠØªÙ… Ø¥Ø³Ù†Ø§Ø¯Ù‡Ù… Ù„Ø£ÙŠ Ù…Ø¹Ø¯Ø© Ù†Ø´Ø·Ø© Ø­Ø§Ù„ÙŠØ§Ù‹.
                </div>
            </div>
            
            <form method="POST" action="save_equipment_drivers.php">
                <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-check"></i> ØªØ§Ø±ÙŠØ® Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø© <span style="color: red;">*</span>
                        </label>
                        <input type="date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-times"></i> ØªØ§Ø±ÙŠØ® Ù†Ù‡Ø§ÙŠØ© Ø§Ù„Ù‚ÙŠØ§Ø¯Ø© (Ø§Ø®ØªÙŠØ§Ø±ÙŠ)
                        </label>
                        <input type="date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-users"></i> Ø§Ø®ØªØ± Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† <span style="color: red;">*</span>
                    </label>
                    
                    <div class="drivers-selection-container">
                        <div class="selection-header">
                            <div class="search-box">
                                <input type="text" id="driverSearch" placeholder="ðŸ” Ø§Ø¨Ø­Ø« Ø¹Ù† Ù…Ø´ØºÙ„ Ø¨Ø§Ù„Ø§Ø³Ù… Ø£Ùˆ Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ..." autocomplete="off">
                                <i class="fas fa-search"></i>
                            </div>
                            
                            <div class="selection-controls">
                                <button type="button" class="btn btn-secondary" id="selectAllDrivers">
                                    <i class="fas fa-check-double"></i> ØªØ­Ø¯ÙŠØ¯ Ø§Ù„ÙƒÙ„
                                </button>
                                <button type="button" class="btn btn-secondary" id="clearAllDrivers">
                                    <i class="fas fa-times-circle"></i> Ø¥Ù„ØºØ§Ø¡ Ø§Ù„ÙƒÙ„
                                </button>
                                <span class="selected-count" id="selectedCount">
                                    <i class="fas fa-user-check"></i>
                                    <span id="countNumber">0</span> Ù…Ø­Ø¯Ø¯
                                </span>
                            </div>
                        </div>
                        
                        <div class="drivers-grid" id="driversGrid">
                            <?php
                            $drivers = mysqli_query($conn, "SELECT d.id, d.name, d.phone
                                                            FROM drivers d
                                                            WHERE d.id NOT IN (
                                                                SELECT driver_id 
                                                                FROM equipment_drivers 
                                                                WHERE status = 1" . (($is_super_admin || !$equipment_drivers_has_company) ? "" : " AND company_id = $company_id") . "
                                                            ) AND d.status = 1
                                                            AND $driver_scope_sql
                                                            ORDER BY d.name");
                            
                            if (mysqli_num_rows($drivers) > 0) {
                                while ($d = mysqli_fetch_assoc($drivers)) {
                                    $driverName = htmlspecialchars($d['name']);
                                    $driverPhone = htmlspecialchars($d['phone'] ?: 'Ù„Ø§ ÙŠÙˆØ¬Ø¯');
                                    $driverInitial = mb_substr($driverName, 0, 1);
                                    echo "
                                    <div class='driver-card' data-driver-id='{$d['id']}' data-driver-name='$driverName' data-driver-phone='$driverPhone'>
                                        <input type='checkbox' name='drivers[]' value='{$d['id']}' style='display: none;' class='driver-checkbox-input'>
                                        <div class='driver-card-header'>
                                            <div class='driver-checkbox'></div>
                                            <div class='driver-avatar'>$driverInitial</div>
                                            <div class='driver-info'>
                                                <h4 class='driver-name'>$driverName</h4>
                                                <div class='driver-phone'>
                                                    <i class='fas fa-phone'></i>
                                                    $driverPhone
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ";
                                }
                            } else {
                                echo "
                                <div class='no-drivers-message'>
                                    <i class='fas fa-user-slash'></i>
                                    <p>Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ù…Ø´ØºÙ„ÙˆÙ† Ù…ØªØ§Ø­ÙˆÙ† Ø­Ø§Ù„ÙŠØ§Ù‹</p>
                                </div>
                                ";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <input type="hidden" name="drivers_selected" id="driversSelected" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ø¥Ø³Ù†Ø§Ø¯
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('projectForm').style.display='none'">
                        <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ø§Ù„Ù…Ø±ØªØ¨Ø·ÙŠÙ† -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list-alt"></i> Ø§Ù„Ù…Ø´ØºÙ„ÙˆÙ† Ø§Ù„Ù…Ø±ØªØ¨Ø·ÙˆÙ† Ø¨Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¹Ø¯Ø©
            <?php if (count($linked) > 0): ?>
                <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; margin-right: auto;">
                    <?php echo count($linked); ?> Ù…Ø´ØºÙ„
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (count($linked) > 0): ?>
                <div class="table-container">
                    <table id="projectsTable" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ø§Ø³Ù… Ø§Ù„Ù…Ø´ØºÙ„</th>
                                <th>Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ</th>
                                <th>ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©</th>
                                <th>ØªØ§Ø±ÙŠØ® Ø§Ù„Ù†Ù‡Ø§ÙŠØ©</th>
                                <th>Ø§Ù„Ø­Ø§Ù„Ø©</th>
                                <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($linked as $row): 
                                $statusClass = $row['status'] ? 'status-active' : 'status-inactive';
                                $statusIcon = $row['status'] ? 'check-circle' : 'times-circle';
                                $statusText = $row['status'] ? 'Ù†Ø´Ø·' : 'ØºÙŠØ± Ù†Ø´Ø·';
                                $actionIcon = $row['status'] ? 'ban' : 'check';
                                $actionText = $row['status'] ? 'ØªØ¹Ø·ÙŠÙ„' : 'ØªÙØ¹ÙŠÙ„';
                                $actionClass = $row['status'] ? 'delete' : 'activate';
                            ?>
                            <tr>
                                <td><strong><?php echo $i++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo $row['start_date'] ? date('Y-m-d', strtotime($row['start_date'])) : '-'; ?></td>
                                <td><?php echo $row['end_date'] ? date('Y-m-d', strtotime($row['end_date'])) : '-'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="delete_equipment_driver.php?id=<?php echo $row['id']; ?>&equipment_id=<?php echo $equipment_id; ?>" 
                                           class="action-btn <?php echo $actionClass; ?>"
                                           onclick="return confirm('Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† <?php echo $actionText; ?> Ù‡Ø°Ø§ Ø§Ù„Ù…Ø´ØºÙ„ØŸ')"
                                           title="<?php echo $actionText; ?>">
                                            <i class="fas fa-<?php echo $actionIcon; ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ù…Ø´ØºÙ„ÙˆÙ† Ù…Ø³Ù†Ø¯ÙˆÙ† Ù„Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø¹Ø¯Ø©</h3>
                    <p>Ø§Ø¨Ø¯Ø£ Ø¨Ø¥Ø¶Ø§ÙØ© Ù…Ø´ØºÙ„ÙŠÙ† Ù„Ù„Ù…Ø¹Ø¯Ø© Ø¨Ø§Ø³ØªØ®Ø¯Ø§Ù… Ø§Ù„Ø²Ø± Ø£Ø¹Ù„Ø§Ù‡</p>
                    <button onclick="document.getElementById('toggleForm').click()" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Ø¥Ø³Ù†Ø§Ø¯ Ù…Ø´ØºÙ„ Ø§Ù„Ø¢Ù†
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- jQuery (ÙˆØ§Ø­Ø¯ ÙÙ‚Ø·) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables core -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Responsive extension -->
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<!-- Export dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Buttons extension -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- ØªÙ‡ÙŠØ¦Ø© DataTable ÙˆØ¬Ø§ÙØ§Ø³ÙƒØ±Ø¨Øª Ø§Ù„ÙˆØ§Ø¬Ù‡Ø© -->
<script>
$(document).ready(function() {
    // ØªÙ‡ÙŠØ¦Ø© DataTable
    <?php if (count($linked) > 0): ?>
    $('#projectsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: '<i class="fas fa-copy"></i> Ù†Ø³Ø®' },
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> ØªØµØ¯ÙŠØ± Excel' },
            { extend: 'csv', text: '<i class="fas fa-file-csv"></i> ØªØµØ¯ÙŠØ± CSV' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> ØªØµØ¯ÙŠØ± PDF' },
            { extend: 'print', text: '<i class="fas fa-print"></i> Ø·Ø¨Ø§Ø¹Ø©' }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        order: [[0, 'asc']],
        pageLength: 25
    });
    <?php endif; ?>

    // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
    $('#toggleForm').on('click', function(e) {
        e.preventDefault();
        const form = $('#projectForm');
        
        if (form.is(':visible')) {
            form.slideUp(300);
            $(this).html('<i class="fas fa-user-plus"></i> Ø¥Ø³Ù†Ø§Ø¯ Ù…Ø´ØºÙ„ Ø¬Ø¯ÙŠØ¯');
        } else {
            form.slideDown(300);
            $(this).html('<i class="fas fa-times"></i> Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…');
            $('html, body').animate({ scrollTop: form.offset().top - 100 }, 500);
        }
    });

    // Ù†Ø¸Ø§Ù… Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ† Ø§Ù„Ø§Ø­ØªØ±Ø§ÙÙŠ
    let selectedDrivers = [];

    // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¹Ø¯Ø§Ø¯ ÙˆØ§Ù„Ù€ hidden input
    function updateSelectedCount() {
        $('#countNumber').text(selectedDrivers.length);
        $('#driversSelected').val(selectedDrivers.join(','));
        
        // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù„ÙˆÙ† Ø¨Ù†Ø§Ø¡ Ø¹Ù„Ù‰ Ø§Ù„Ø¹Ø¯Ø¯
        if (selectedDrivers.length > 0) {
            $('#selectedCount').css({
                'background': 'var(--gold-soft)',
                'border-color': 'rgba(232,184,0,.28)'
            });
        } else {
            $('#selectedCount').css({
                'background': 'var(--red-soft)',
                'border-color': 'rgba(220,38,38,.18)'
            });
        }
    }

    // Ø§Ù„Ù†Ù‚Ø± Ø¹Ù„Ù‰ Ø§Ù„Ø¨Ø·Ø§Ù‚Ø©
    $('.driver-card').on('click', function() {
        const driverId = $(this).data('driver-id');
        const index = selectedDrivers.indexOf(driverId);
        
        if (index > -1) {
            // Ø¥Ù„ØºØ§Ø¡ Ø§Ù„ØªØ­Ø¯ÙŠØ¯
            selectedDrivers.splice(index, 1);
            $(this).removeClass('selected');
            $(this).find('.driver-checkbox-input').prop('checked', false);
        } else {
            // ØªØ­Ø¯ÙŠØ¯
            selectedDrivers.push(driverId);
            $(this).addClass('selected');
            $(this).find('.driver-checkbox-input').prop('checked', true);
        }
        
        updateSelectedCount();
    });

    // ØªØ­Ø¯ÙŠØ¯ Ø§Ù„ÙƒÙ„
    $('#selectAllDrivers').on('click', function() {
        selectedDrivers = [];
        $('.driver-card:visible').each(function() {
            const driverId = $(this).data('driver-id');
            selectedDrivers.push(driverId);
            $(this).addClass('selected');
            $(this).find('.driver-checkbox-input').prop('checked', true);
        });
        updateSelectedCount();
    });

    // Ø¥Ù„ØºØ§Ø¡ Ø§Ù„ÙƒÙ„
    $('#clearAllDrivers').on('click', function() {
        selectedDrivers = [];
        $('.driver-card').removeClass('selected');
        $('.driver-checkbox-input').prop('checked', false);
        updateSelectedCount();
    });

    // Ø§Ù„Ø¨Ø­Ø« Ø¹Ù† Ø§Ù„Ø³Ø§Ø¦Ù‚ÙŠÙ†
    $('#driverSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        
        $('.driver-card').each(function() {
            const driverName = $(this).data('driver-name').toLowerCase();
            const driverPhone = $(this).data('driver-phone').toLowerCase();
            
            if (driverName.includes(searchTerm) || driverPhone.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Ø±Ø³Ø§Ù„Ø© Ø¥Ø°Ø§ Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ù†ØªØ§Ø¦Ø¬
        const visibleCards = $('.driver-card:visible').length;
        const noResultsMsg = $('#noResultsMsg');
        
        if (visibleCards === 0 && searchTerm !== '') {
            if (noResultsMsg.length === 0) {
                $('#driversGrid').append(`
                    <div id="noResultsMsg" class="no-drivers-message" style="grid-column: 1 / -1;">
                        <i class="fas fa-search"></i>
                        <p>Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ù†ØªØ§Ø¦Ø¬ Ù„Ù„Ø¨Ø­Ø«: "${searchTerm}"</p>
                    </div>
                `);
            }
        } else {
            noResultsMsg.remove();
        }
    });

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø§Ù„ØµØ­Ø© Ù‚Ø¨Ù„ Ø§Ù„Ø¥Ø±Ø³Ø§Ù„
    $('form').on('submit', function(e) {
        if (selectedDrivers.length === 0) {
            e.preventDefault();
            alert('âš ï¸ ÙŠØ¬Ø¨ Ø§Ø®ØªÙŠØ§Ø± Ù…Ø´ØºÙ„ ÙˆØ§Ø­Ø¯ Ø¹Ù„Ù‰ Ø§Ù„Ø£Ù‚Ù„');
            $('#driverSearch').focus();
            return false;
        }
    });

    // ØªØ­Ø³ÙŠÙ† ØªØ¬Ø±Ø¨Ø© Ø§Ø®ØªÙŠØ§Ø± Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ø§Ù„Ù…ØªØ¹Ø¯Ø¯ÙŠÙ† - ØªÙ… Ø¥Ø²Ø§Ù„Ø© Ø§Ù„Ù€ select Ø§Ù„Ù‚Ø¯ÙŠÙ…
    // ØªÙ… Ø§Ø³ØªØ¨Ø¯Ø§Ù„Ù‡ Ø¨Ù†Ø¸Ø§Ù… Ø§Ù„Ù€ cards

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„ØªÙˆØ§Ø±ÙŠØ®
    $('input[name="end_date"]').on('change', function() {
        const startDate = new Date($('input[name="start_date"]').val());
        const endDate = new Date($(this).val());
        if (endDate < startDate) {
            alert('âš ï¸ ØªØ§Ø±ÙŠØ® Ø§Ù„Ù†Ù‡Ø§ÙŠØ© Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø£Ù† ÙŠÙƒÙˆÙ† Ù‚Ø¨Ù„ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©');
            $(this).val('');
        }
    });

    // Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ù†Ø¬Ø§Ø­ Ø§Ù„ØªÙ„Ù‚Ø§Ø¦ÙŠØ©
    <?php if (isset($_GET['msg'])): ?>
    setTimeout(function() { $('.alert-success').fadeOut(500); }, 5000);
    <?php endif; ?>

    // ØªØ­Ø³ÙŠÙ† Ø±Ø³Ø§Ø¦Ù„ Ø§Ù„ØªØ£ÙƒÙŠØ¯
    $('.action-btn').on('click', function(e) {
        const actionType = $(this).hasClass('delete') ? 'ØªØ¹Ø·ÙŠÙ„' : 'ØªÙØ¹ÙŠÙ„';
        const driverName = $(this).closest('tr').find('td:eq(1)').text();
        if (!confirm(`Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† ${actionType} Ø§Ù„Ù…Ø´ØºÙ„: ${driverName}ØŸ`)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

</body>

</html>
