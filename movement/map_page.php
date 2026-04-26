<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$project_has_company_id = db_table_has_column($conn, 'project', 'company_id');
$project_client_column = db_table_has_column($conn, 'project', 'client_id') ? 'client_id' : 'company_client_id';

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة");
    exit();
}

// تحديد المشروع الحالي من الجلسة
$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$selected_project_id = 0;

if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id']) && intval($_SESSION['operations_project_id']) > 0) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

$project_scope_sql = "1=1";
if (!$is_super_admin) {
    if ($project_has_company_id) {
        $project_scope_sql = "p.company_id = $company_id";
    } else {
        $project_scope_sql = "1=1";
    }
}

// جلب بيانات المشروع
$selected_project = null;
if ($selected_project_id > 0) {
    $pq = mysqli_query($conn, "SELECT * FROM project p WHERE p.id = $selected_project_id AND p.status = 1 AND p.is_deleted = 0 AND $project_scope_sql");
    if ($pq && mysqli_num_rows($pq) > 0) {
        $selected_project = mysqli_fetch_assoc($pq);
    }
}

if (!$selected_project) {
    echo "<script>alert('❌ لا يوجد مشروع مرتبط بهذه الجلسة'); window.location.href='../main/dashboard.php';</script>";
    exit();
}

// ============================================================
// جلب المناجم الخاصة بالمشروع
// ============================================================
$mines_data = [];
$mines_has_company = db_table_has_column($conn, 'mines', 'company_id');
$company_mine_clause = ($mines_has_company && !$is_super_admin) ? " AND m.company_id = $company_id" : "";

$mines_q = mysqli_query($conn, "
    SELECT m.id, m.mine_name, m.mine_code, m.manager_name, m.mineral_type,
           m.mine_type, m.ownership_type, m.mine_area, m.mine_area_unit,
           m.mining_depth, m.contract_nature, m.status, m.notes
    FROM mines m
    WHERE m.project_id = $selected_project_id AND m.status = 1 AND m.is_deleted = 0
    $company_mine_clause
    ORDER BY m.id ASC
");

if ($mines_q) {
    while ($mine = mysqli_fetch_assoc($mines_q)) {
        $mines_data[$mine['id']] = $mine;
        $mines_data[$mine['id']]['equipments'] = [];
    }
}

// ============================================================
// جلب المعدات (الشاحنات) لكل منجم في المشروع
// ============================================================
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$ops_company_clause = ($operations_has_company && !$is_super_admin) ? " AND o.company_id = $company_id" : "";

$ops_q = mysqli_query($conn, "
    SELECT o.id AS op_id, o.mine_id, o.status AS op_status,
           o.start, o.end, o.equipment_category,
           e.id AS eq_id, e.code AS eq_code, e.name AS eq_name,
           e.type AS eq_type_id, e.serial_number, e.chassis_number,
           e.manufacturer, e.model, e.manufacturing_year,
           e.equipment_condition, e.availability_status,
           e.engine_condition, e.operating_hours, e.general_notes AS eq_notes,
           COALESCE(et.type, '') AS type_name,
           s.name AS supplier_name
    FROM operations o
    JOIN equipments e ON o.equipment = e.id
    LEFT JOIN equipments_types et ON CAST(e.type AS UNSIGNED) = et.id
    LEFT JOIN suppliers s ON CAST(o.supplier_id AS UNSIGNED) = s.id
    WHERE CAST(o.project_id AS UNSIGNED) = $selected_project_id
      AND o.status = 1
      $ops_company_clause
    ORDER BY o.mine_id ASC, e.code ASC
");

// معدات بدون منجم محدد
$no_mine_equipments = [];

if ($ops_q) {
    while ($op = mysqli_fetch_assoc($ops_q)) {
        $mine_id = intval($op['mine_id']);
        // تحديد حالة الشاحنة: عاملة / متوقفة
        $avail = $op['availability_status'] ?? '';
        $is_working = !in_array($avail, ['معطلة', 'مبيعة/مسحوبة', 'خارج الخدمة', 'تحت الصيانة', 'موقوفة']);

        $op['is_working'] = $is_working;
        $op['drivers'] = [];

        if ($mine_id > 0 && isset($mines_data[$mine_id])) {
            $mines_data[$mine_id]['equipments'][$op['op_id']] = $op;
        } else {
            $no_mine_equipments[$op['op_id']] = $op;
        }
    }
}

// ============================================================
// جلب المشغلين لكل معدة
// ============================================================
$eq_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');
$drivers_company_clause = ($eq_drivers_has_company && !$is_super_admin) ? " AND ed.company_id = $company_id" : "";

// جمع كل معرفات المعدات
$all_eq_ids = [];
foreach ($mines_data as $mine) {
    foreach ($mine['equipments'] as $op) {
        $all_eq_ids[] = intval($op['eq_id']);
    }
}
foreach ($no_mine_equipments as $op) {
    $all_eq_ids[] = intval($op['eq_id']);
}

if (!empty($all_eq_ids)) {
    $eq_ids_str = implode(',', array_unique($all_eq_ids));
    $drv_q = mysqli_query($conn, "
        SELECT ed.equipment_id, ed.start_date, ed.end_date,
               d.id AS driver_id, d.name AS driver_name, d.driver_code,
               d.phone, d.skill_level, d.license_type, d.years_in_field,
               d.years_on_equipment, d.driver_status, d.employment_affiliation,
               d.specialized_equipment
        FROM equipment_drivers ed
        JOIN drivers d ON ed.driver_id = d.id
        WHERE ed.equipment_id IN ($eq_ids_str)
          AND ed.status = 1
          AND d.status = 1
          $drivers_company_clause
        ORDER BY ed.equipment_id ASC, d.name ASC
    ");

    // بناء خريطة المعدة -> قائمة المشغلين
    $eq_drivers_map = [];
    if ($drv_q) {
        while ($dr = mysqli_fetch_assoc($drv_q)) {
            $eq_id = intval($dr['equipment_id']);
            if (!isset($eq_drivers_map[$eq_id])) $eq_drivers_map[$eq_id] = [];
            $eq_drivers_map[$eq_id][] = $dr;
        }
    }

    // إسناد المشغلين للمعدات في المناجم
    foreach ($mines_data as $mine_id => &$mine) {
        foreach ($mine['equipments'] as $op_id => &$op) {
            $eq_id = intval($op['eq_id']);
            $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
        }
        unset($op);
    }
    unset($mine);

    foreach ($no_mine_equipments as $op_id => &$op) {
        $eq_id = intval($op['eq_id']);
        $op['drivers'] = $eq_drivers_map[$eq_id] ?? [];
    }
    unset($op);
}

// ============================================================
// جلب ساعات التشغيل من التايم شيت
// ============================================================
$ts_has_company = db_table_has_column($conn, 'timesheet', 'company_id');
$ts_company_clause = ($ts_has_company && !$is_super_admin) ? " AND t.company_id = $company_id" : "";

// تهيئة الساعات بالصفر وتجميع معرّفات التشغيل
$all_op_ids_ts = [];
foreach ($mines_data as $mine_id => &$mine) {
    foreach ($mine['equipments'] as $op_id => &$op) {
        $op['ts_total'] = 0.0;
        $op['ts_today'] = 0.0;
        $all_op_ids_ts[] = intval($op_id);
    }
    unset($op);
}
unset($mine);
foreach ($no_mine_equipments as $op_id => &$op) {
    $op['ts_total'] = 0.0;
    $op['ts_today'] = 0.0;
    $all_op_ids_ts[] = intval($op_id);
}
unset($op);

if (!empty($all_op_ids_ts)) {
    $op_ids_ts_str = implode(',', array_unique($all_op_ids_ts));
    $ts_q = mysqli_query($conn, "
        SELECT CAST(t.operator AS UNSIGNED) AS op_id,
               SUM(t.total_work_hours) AS total_hours,
               SUM(CASE WHEN t.date = CURDATE() THEN t.total_work_hours ELSE 0 END) AS today_hours
        FROM timesheet t
        WHERE CAST(t.operator AS UNSIGNED) IN ($op_ids_ts_str)
          AND t.status = 1
          $ts_company_clause
        GROUP BY t.operator
    ");
    if ($ts_q) {
        $ts_map = [];
        while ($ts_row = mysqli_fetch_assoc($ts_q)) {
            $ts_map[intval($ts_row['op_id'])] = [
                'total' => floatval($ts_row['total_hours']),
                'today' => floatval($ts_row['today_hours']),
            ];
        }
        foreach ($mines_data as $mine_id => &$mine) {
            foreach ($mine['equipments'] as $op_id => &$op) {
                if (isset($ts_map[$op_id])) {
                    $op['ts_total'] = $ts_map[$op_id]['total'];
                    $op['ts_today'] = $ts_map[$op_id]['today'];
                }
            }
            unset($op);
        }
        unset($mine);
        foreach ($no_mine_equipments as $op_id => &$op) {
            if (isset($ts_map[$op_id])) {
                $op['ts_total'] = $ts_map[$op_id]['total'];
                $op['ts_today'] = $ts_map[$op_id]['today'];
            }
        }
        unset($op);
    }
}

// ============================================================
// إحصائيات إجمالية
// ============================================================
$total_mines = count($mines_data);
$total_equip = 0;
$total_working = 0;
$total_stopped = 0;
$total_operators = 0;

foreach ($mines_data as $mine) {
    foreach ($mine['equipments'] as $op) {
        $total_equip++;
        if ($op['is_working']) $total_working++; else $total_stopped++;
        $total_operators += count($op['drivers']);
    }
}

$page_title = "خريطة الموقع | " . htmlspecialchars($selected_project['name']);
?>
<?php include '../inheader.php'; ?>
<?php include '../insidebar.php'; ?>

<style>
/* ===================================================
   خريطة الموقع — نسخة محسّنة
   =================================================== */

/* ── وعاء الصفحة ── */
.map-page {
    padding: 26px 30px 56px;
    font-family: 'Cairo', sans-serif;
    min-height: 100vh;
    width: 100%;
    max-width: none;
    box-sizing: border-box;
    background: #f0f2f5;
}

.main-content#main-content {
    width: 100% !important;
    max-width: none !important;
}

/* ── رأس الصفحة ── */
.map-header {
    background: linear-gradient(135deg, #000022 0%, #0d1a5c 60%, #1a0a3e 100%);
    border-radius: 18px;
    padding: 22px 30px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 6px 28px rgba(0,0,34,.45);
    position: relative;
    overflow: hidden;
}
.map-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(255,204,0,.08) 0%, transparent 70%);
    pointer-events: none;
}
.map-header-icon {
    width: 62px; height: 62px;
    background: rgba(255,204,0,.15);
    border: 2px solid rgba(255,204,0,.3);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
    color: #ffcc00;
    flex-shrink: 0;
    box-shadow: 0 0 20px rgba(255,204,0,.2);
}
.map-header-text h1 {
    margin: 0; font-size: 22px; font-weight: 800; color: #ffcc00; line-height: 1.2;
}
.map-header-text .sub {
    font-size: 13px; color: rgba(255,255,210,.65); margin-top: 5px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.map-header-text .sub span { display: flex; align-items: center; gap: 5px; }
.map-live {
    margin-right: auto;
    background: rgba(40,167,69,.2);
    border: 1px solid rgba(40,167,69,.5);
    border-radius: 24px;
    padding: 7px 20px;
    color: #6de890;
    font-size: 13px;
    font-weight: 700;
    display: flex; align-items: center; gap: 8px;
}
.live-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    background: #28a745;
    animation: livePulse 1.6s ease-in-out infinite;
}
@keyframes livePulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.3; transform:scale(.75); }
}

/* ── شريط الإحصائيات ── */
.stats-row {
    display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
}
.stat-tile {
    flex: 1 1 170px;
    background: #fff;
    border-radius: 16px;
    padding: 18px 22px;
    box-shadow: 0 3px 14px rgba(0,0,0,.07);
    display: flex; align-items: center; gap: 16px;
    border-right: 5px solid var(--equipation-gold, #ffcc00);
    transition: transform .2s, box-shadow .2s;
}
.stat-tile:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.stat-tile-icon {
    width: 54px; height: 54px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
}
.stat-tile-icon.c-gold   { background: rgba(255,204,0,.13); color: #a07700; }
.stat-tile-icon.c-blue   { background: rgba(0,100,220,.10); color: #0064dc; }
.stat-tile-icon.c-green  { background: rgba(40,167,69,.10); color: #1a7a35; }
.stat-tile-icon.c-red    { background: rgba(220,53,69,.10); color: #b52535; }
.stat-tile-icon.c-purple { background: rgba(111,66,193,.10); color: #5a1099; }
.stat-tile-val  { font-size: 32px; font-weight: 800; color: #000022; line-height: 1; }
.stat-tile-lbl  { font-size: 12px; color: #777; margin-top: 4px; }

/* ── مؤشر الألوان ── */
.legend-row {
    display: flex; gap: 22px; flex-wrap: wrap; align-items: center;
    font-size: 12.5px; color: #555; margin-bottom: 26px;
    background: #fff; border-radius: 12px; padding: 12px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
.legend-item { display: flex; align-items: center; gap: 7px; }
.ldot { width: 13px; height: 13px; border-radius: 50%; flex-shrink: 0; }
.ldot.green  { background: #28a745; }
.ldot.red    { background: #dc3545; }
.ldot.blue   { background: #1565c0; }
.ldot.gray   { background: #b0bec5; }
.leq-icon { width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }

/* ── شبكة المناجم ── */
.mines-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 26px;
    width: 100%;
}

/* ── بطاقة المنجم ── */
.mine-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 22px rgba(0,0,34,.09);
    border: 2px solid transparent;
    transition: border-color .3s, box-shadow .3s;
    width: 100%;
}
.mine-card:hover {
    border-color: rgba(255,204,0,.5);
    box-shadow: 0 8px 36px rgba(255,204,0,.18);
}

/* رأس بطاقة المنجم */
.mine-hdr {
    background: linear-gradient(135deg, #07081e 0%, #1a1660 100%);
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    border-radius: 18px 18px 0 0;
}
.mine-hdr::after {
    content: '';
    position: absolute;
    bottom: 0; right: 0;
    width: 120px; height: 60px;
    background: radial-gradient(ellipse at bottom right, rgba(255,204,0,.12), transparent 70%);
    pointer-events: none;
}
.mine-hdr-icon {
    width: 50px; height: 50px;
    background: rgba(255,204,0,.15);
    border: 1.5px solid rgba(255,204,0,.3);
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: #ffcc00; flex-shrink: 0;
}
.mine-hdr-name {
    font-size: 17px; font-weight: 800; color: #fff; cursor: default;
}
.mine-hdr-name:hover { color: #ffcc00; }
.mine-hdr-code { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 3px; }
.mine-hdr-badges { margin-right: auto; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.m-badge {
    font-size: 11px; border-radius: 20px; padding: 4px 12px; font-weight: 700;
    display: flex; align-items: center; gap: 5px;
}
.m-badge.gold  { background: rgba(255,204,0,.2); color: #ffd700; border: 1px solid rgba(255,204,0,.35); }
.m-badge.green { background: rgba(40,167,69,.2); color: #6de870; border: 1px solid rgba(40,167,69,.35); }
.m-badge.red   { background: rgba(220,53,69,.2); color: #ff8a94; border: 1px solid rgba(220,53,69,.35); }

/* شريط إحصائيات المنجم */
.mine-stats-strip {
    display: flex;
    background: #f7f8fa;
    border-bottom: 1px solid #eaeef2;
}
.mss-item {
    flex: 1; text-align: center;
    padding: 12px 8px;
    border-left: 1px solid #eaeef2;
    font-size: 11.5px; color: #666;
}
.mss-item:last-child { border-left: none; }
.mss-item .mss-n {
    font-size: 22px; font-weight: 800; display: block; line-height: 1.1; color: #000022;
}
.mss-item .mss-n.green  { color: #1a7a35; }
.mss-item .mss-n.red    { color: #b52535; }
.mss-item .mss-n.blue   { color: #0a60cc; }
.mss-item .mss-n.purple { color: #5a1099; }
.mss-item .mss-n.gold   { color: #a07000; }

/* جسم بطاقة المنجم */
.mine-body {
    padding: 20px;
}

/* تقسيم الأقسام */
.section-sep {
    display: flex; align-items: center; gap: 10px;
    font-size: 12px; font-weight: 700; color: #777;
    margin-bottom: 14px; margin-top: 4px;
}
.section-sep::after { content: ''; flex: 1; height: 1px; background: #eaeef2; }
.section-sep.working-sep { color: #1a7a35; }
.section-sep.stopped-sep { color: #b52535; margin-top: 18px; }
.section-sep i { font-size: 13px; }

/* ── شبكة المعدات ── */
.eq-grid {
    display: flex; flex-wrap: wrap; gap: 12px;
}

/* ── بطاقة المعدة ── */
.eq-card {
    width: 120px;
    border-radius: 14px;
    overflow: visible;
    cursor: default;
    transition: transform .22s, z-index 0s;
    position: relative;
}
.eq-card:hover { transform: translateY(-5px); z-index: 20; }
.eq-inner {
    border-radius: 14px;
    border: 2px solid transparent;
    box-shadow: 0 3px 12px rgba(0,0,0,.1);
    transition: box-shadow .2s;
    overflow: visible;
}
.eq-card:hover .eq-inner { box-shadow: 0 8px 28px rgba(0,0,0,.2); }

/* الشريط العلوي للمعدة */
.eq-top-bar {
    height: 6px;
    width: 100%;
    border-radius: 12px 12px 0 0;
}
.eq-card.working .eq-inner  { border-color: #43a047; background: linear-gradient(175deg,#edfced,#d4f5d4); }
.eq-card.stopped .eq-inner  { border-color: #e53935; background: linear-gradient(175deg,#fdf0f0,#fad7d7); }
.eq-card.working .eq-top-bar { background: linear-gradient(90deg,#43a047,#66bb6a); }
.eq-card.stopped .eq-top-bar { background: linear-gradient(90deg,#e53935,#ef5350); }

/* داخل البطاقة */
.eq-content {
    padding: 10px 8px 8px;
    text-align: center;
}

/* أيقونة المعدة */
.eq-machine-icon {
    width: 54px; height: 54px;
    border-radius: 12px;
    margin: 0 auto 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
    transition: transform .2s;
}
.eq-card.working .eq-machine-icon { background: rgba(67,160,71,.15); color: #2e7d32; }
.eq-card.stopped .eq-machine-icon { background: rgba(229,57,53,.12); color: #b71c1c; filter: grayscale(30%); }
.eq-card:hover .eq-machine-icon { transform: scale(1.08); }

.eq-code {
    font-size: 11px; font-weight: 800; color: #1a1a2e;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.eq-type-lbl {
    font-size: 9.5px; color: #888; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* مؤشر حالة الشاحنة */
.eq-status-dot {
    position: absolute; top: 10px; left: 8px;
    width: 10px; height: 10px;
    border-radius: 50%; border: 2px solid #fff;
    z-index: 2;
}
.eq-card.working .eq-status-dot { background: #28a745; box-shadow: 0 0 6px rgba(40,167,69,.6); }
.eq-card.stopped .eq-status-dot { background: #dc3545; }

/* ── صف المشغلين ── */
.op-row {
    display: flex; justify-content: center; flex-wrap: wrap;
    gap: 4px;
    padding: 7px 6px 9px;
    background: rgba(0,0,0,.03);
    border-top: 1px solid rgba(0,0,0,.06);
    min-height: 38px;
}
.no-op-label {
    font-size: 9.5px; color: #aaa; align-self: center;
    display: flex; align-items: center; gap: 4px;
}

/* أفاتار المشغل */
.op-av {
    width: 24px; height: 24px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    cursor: default;
    transition: transform .15s, box-shadow .15s;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.8);
    position: relative;
}
.op-av.active  { background: linear-gradient(135deg,#1565c0,#42a5f5); color: #fff; box-shadow: 0 2px 6px rgba(21,101,192,.35); }
.op-av.reserve { background: #e0e0e0; color: #999; }
.op-av:hover   { transform: scale(1.35); z-index: 50; }

/* ── ساعات التشغيل في البطاقة ── */
.eq-hours-row {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 5px 8px 7px;
    background: rgba(0,0,0,.04);
    border-top: 1px dashed rgba(0,0,0,.1);
    gap: 4px;
}
.eq-hours-item {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 1px;
    flex: 1;
    min-width: 0;
}
.eq-hours-sep {
    width: 1px;
    background: rgba(0,0,0,.12);
    align-self: stretch;
    flex-shrink: 0;
    margin: 2px 0;
}
.eq-hours-val {
    font-size: 13px;
    font-weight: 800;
    color: #1a1a2e;
    line-height: 1.15;
    white-space: nowrap;
}
.eq-hours-val.today-val { color: #0a60cc; }
.eq-hours-lbl {
    font-size: 9px;
    color: #999;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ═══════════════════════════════════════════
   نظام Tooltip — JS يتحكم بـ .tt-show
   ═══════════════════════════════════════════ */
.tt-trigger { position: relative; }

.tt-box {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    bottom: calc(100% + 12px);
    right: 50%;
    transform: translateX(50%);
    background: #07081e;
    color: #e8e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    min-width: 210px;
    max-width: 280px;
    font-size: 12px;
    line-height: 1.75;
    z-index: 99999;
    box-shadow: 0 10px 36px rgba(0,0,34,.6);
    border: 1px solid rgba(255,204,0,.25);
    pointer-events: none;
    transition: opacity .18s ease, visibility .18s ease;
    white-space: normal;
    text-align: right;
}
.tt-box::after {
    content: '';
    position: absolute;
    top: 100%; right: 50%;
    transform: translateX(50%);
    border: 8px solid transparent;
    border-top-color: #07081e;
}
/* إظهار عبر JS */
.tt-box.tt-show {
    visibility: visible;
    opacity: 1;
}
/* عندما يكون تحت العنصر */
.tt-box.tt-below {
    bottom: auto;
    top: calc(100% + 12px);
}
.tt-box.tt-below::after {
    top: auto; bottom: 100%;
    border-top-color: transparent;
    border-bottom-color: #07081e;
}
/* عنوان الـ tooltip */
.tt-title {
    font-size: 13px; font-weight: 700; color: #ffcc00;
    margin-bottom: 8px;
    padding-bottom: 7px;
    border-bottom: 1px solid rgba(255,204,0,.2);
    display: flex; align-items: center; gap: 7px;
}
.tt-row {
    display: flex; justify-content: space-between; align-items: baseline; gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.04);
    padding: 2px 0;
}
.tt-row:last-child { border-bottom: none; }
.tt-k { color: #8888aa; font-size: 11px; white-space: nowrap; }
.tt-v { color: #e0e0f0; font-weight: 600; text-align: left; font-size: 11.5px; }

/* ── حالة فارغة ── */
.empty-mine-body {
    text-align: center; padding: 30px 20px; color: #b0b8c8;
}
.empty-mine-body i { font-size: 38px; margin-bottom: 10px; display: block; opacity: .4; }
.empty-mine-body p { font-size: 13px; opacity: .7; }

/* ── قسم معدات بلا منجم ── */
.orphan-section {
    margin-top: 28px;
    background: #fff;
    border-radius: 18px;
    border: 2px dashed #d0d7e2;
    padding: 22px;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.orphan-title {
    font-size: 14px; font-weight: 700; color: #888;
    margin-bottom: 16px; display: flex; align-items: center; gap: 9px;
}

/* ── حالة لا مناجم ── */
.zero-state {
    text-align: center; padding: 80px 20px; color: #b0b8c8;
}
.zero-state i { font-size: 80px; display: block; margin-bottom: 18px; opacity: .25; }
.zero-state h3 { font-size: 20px; color: #c0c8d8; }
.zero-state p  { font-size: 14px; margin-top: 8px; opacity: .6; }

/* ── Responsive ── */
@media (max-width: 820px) {
    .mines-grid { grid-template-columns: 1fr; }
    .eq-card    { width: 104px; }
    .stat-tile  { flex: 1 1 145px; }
    .map-header h1 { font-size: 18px; }
    .map-live   { display: none; }
}
@media (max-width: 480px) {
    .map-page  { padding: 16px 14px 40px; }
    .eq-card   { width: 90px; }
    .eq-machine-icon { width: 44px; height: 44px; font-size: 22px; }
}
</style>

<div class="main-content" id="main-content">
<div class="map-page">

<!-- ═══ رأس الصفحة ═══ -->
<div class="map-header">
    <div class="map-header-icon"><i class="fas fa-map-marked-alt"></i></div>
    <div class="map-header-text">
        <h1>خريطة الموقع</h1>
        <div class="sub">
            <span><i class="fas fa-project-diagram"></i><?php echo htmlspecialchars($selected_project['name']); ?></span>
            <?php if (!empty($selected_project['project_code'])): ?>
            <span><i class="fas fa-hashtag"></i><?php echo htmlspecialchars($selected_project['project_code']); ?></span>
            <?php endif; ?>
            <?php if (!empty($selected_project['location'])): ?>
            <span><i class="fas fa-map-pin"></i><?php echo htmlspecialchars($selected_project['location']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="map-live"><span class="live-dot"></span> عرض مباشر</div>
</div>

<!-- ═══ الإحصائيات ═══ -->
<div class="stats-row">
    <div class="stat-tile">
        <div class="stat-tile-icon c-gold"><i class="fas fa-mountain"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_mines; ?></div><div class="stat-tile-lbl">إجمالي المناجم</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-blue"><i class="fas fa-truck-monster"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_equip; ?></div><div class="stat-tile-lbl">إجمالي الآليات</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-green"><i class="fas fa-cog fa-spin" style="animation-duration:3s;"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_working; ?></div><div class="stat-tile-lbl">آليات عاملة</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-red"><i class="fas fa-tools"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_stopped; ?></div><div class="stat-tile-lbl">آليات متوقفة</div></div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-icon c-purple"><i class="fas fa-hard-hat"></i></div>
        <div><div class="stat-tile-val"><?php echo $total_operators; ?></div><div class="stat-tile-lbl">إجمالي المشغلين</div></div>
    </div>
</div>

<!-- ═══ مفتاح الألوان ═══ -->
<div class="legend-row">
    <strong style="color:#333; font-size:12px; margin-left:6px;">المفتاح:</strong>
    <div class="legend-item"><div class="ldot green"></div> آلية عاملة</div>
    <div class="legend-item"><div class="ldot red"></div> آلية متوقفة</div>
    <div class="legend-item">
        <div class="op-av active" style="width:18px;height:18px;font-size:9px;border:0;"><i class="fas fa-user" style="font-size:8px;"></i></div>
        مشغّل أساسي
    </div>
    <div class="legend-item">
        <div class="op-av reserve" style="width:18px;height:18px;font-size:9px;border:0;"><i class="fas fa-user" style="font-size:8px;"></i></div>
        احتياطي (قريباً)
    </div>
    <div class="legend-item" style="margin-right:auto; color:#aaa; font-size:11px;">
        <i class="fas fa-hand-pointer fa-xs"></i> مرّر على أي عنصر لعرض تفاصيله
    </div>
</div>

<?php if (empty($mines_data)): ?>
<!-- حالة لا مناجم -->
<div class="zero-state">
    <i class="fas fa-mountain"></i>
    <h3>لا توجد مناجم مسجّلة في هذا المشروع</h3>
    <p>يمكنك إضافة المناجم من صفحة إدارة المشاريع</p>
</div>
<?php else: ?>

<!-- ═══ شبكة المناجم ═══ -->
<div class="mines-grid">

<?php
$mine_colors = ['#1a1660','#0a3a60','#1a3a00','#3a0a20','#1a2a1a'];
$mi = 0;
foreach ($mines_data as $mine_id => $mine):
    $mine_equips  = $mine['equipments'];
    $mine_total   = count($mine_equips);
    $mine_working = 0; $mine_stopped = 0; $mine_ops = 0;
    foreach ($mine_equips as $op) {
        if ($op['is_working']) $mine_working++; else $mine_stopped++;
        $mine_ops += count($op['drivers']);
    }
    $hdr_accent = $mine_colors[$mi % count($mine_colors)];
    $mi++;

    // tooltip المنجم
    $tt_mine_data = [
        'الكود'       => $mine['mine_code'] ?? '-',
        'المدير'      => $mine['manager_name'] ?? '-',
        'النوع'       => $mine['mine_type'] ?? '-',
        'المعدن'      => $mine['mineral_type'] ?? '-',
        'المساحة'     => $mine['mine_area'] ? ($mine['mine_area'] . ' ' . ($mine['mine_area_unit'] ?? '')) : '-',
    ];
?>

<div class="mine-card">
    <!-- رأس المنجم -->
    <div class="mine-hdr" style="background: linear-gradient(135deg, #07081e, <?php echo htmlspecialchars($hdr_accent); ?>);">
        <div class="mine-hdr-icon"><i class="fas fa-mountain"></i></div>
        <div>
            <!-- الاسم قابل للـ tooltip -->
            <div class="mine-hdr-name tt-trigger" style="display:inline-block; cursor:default;">
                <?php echo htmlspecialchars($mine['mine_name']); ?>
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-mountain"></i> <?php echo htmlspecialchars($mine['mine_name']); ?></div>
                    <?php foreach ($tt_mine_data as $k => $v): ?>
                    <div class="tt-row">
                        <span class="tt-k"><?php echo $k; ?></span>
                        <span class="tt-v"><?php echo htmlspecialchars($v); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mine-hdr-code"><?php echo htmlspecialchars($mine['mine_code'] ?? ''); ?></div>
        </div>
        <div class="mine-hdr-badges">
            <span class="m-badge gold"><i class="fas fa-truck-monster fa-xs"></i> <?php echo $mine_total; ?></span>
            <?php if ($mine_working > 0): ?>
            <span class="m-badge green"><i class="fas fa-check fa-xs"></i> <?php echo $mine_working; ?></span>
            <?php endif; ?>
            <?php if ($mine_stopped > 0): ?>
            <span class="m-badge red"><i class="fas fa-times fa-xs"></i> <?php echo $mine_stopped; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- شريط إحصائيات المنجم -->
    <div class="mine-stats-strip">
        <div class="mss-item"><span class="mss-n"><?php echo $mine_total; ?></span>إجمالي</div>
        <div class="mss-item"><span class="mss-n green"><?php echo $mine_working; ?></span>عاملة</div>
        <div class="mss-item"><span class="mss-n red"><?php echo $mine_stopped; ?></span>متوقفة</div>
        <div class="mss-item"><span class="mss-n blue"><?php echo $mine_ops; ?></span>مشغّلون</div>
        <?php if (!empty($mine['mineral_type'])): ?>
        <div class="mss-item"><span class="mss-n gold" style="font-size:13px;">⛏</span><?php echo htmlspecialchars($mine['mineral_type']); ?></div>
        <?php endif; ?>
    </div>

    <!-- جسم المنجم -->
    <div class="mine-body">
        <?php if (empty($mine_equips)): ?>
        <div class="empty-mine-body">
            <i class="fas fa-truck-monster"></i>
            <p>لا توجد آليات مسندة لهذا المنجم</p>
        </div>
        <?php else:
            $working_list = array_filter($mine_equips, fn($o) => $o['is_working']);
            $stopped_list = array_filter($mine_equips, fn($o) => !$o['is_working']);
        ?>

        <?php if (!empty($working_list)): ?>
        <div class="section-sep working-sep">
            <i class="fas fa-play-circle"></i> آليات عاملة (<?php echo count($working_list); ?>)
        </div>
        <div class="eq-grid">
            <?php foreach ($working_list as $op):
                $drv_count = count($op['drivers']);
                $tt_eq_data = [
                    'الكود'   => $op['eq_code'] ?? '-',
                    'الاسم'   => $op['eq_name'] ?? '-',
                    'النوع'   => $op['type_name'] ?: '-',
                    'الماركة' => trim(($op['manufacturer'] ?? '') . ' ' . ($op['model'] ?? '')) ?: '-',
                    'المورد'  => $op['supplier_name'] ?? '-',
                ];
            ?>
            <div class="eq-card working tt-trigger">
                <div class="eq-status-dot"></div>
                <div class="eq-inner">
                    <div class="eq-top-bar"></div>
                    <div class="eq-content">
                        <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                        <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                        <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                    </div>
                    <!-- صف المشغلين -->
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash" style="font-size:9px;"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['driver_code'] ?? '-');
                                $dp = htmlspecialchars($drv['phone'] ?? '-');
                                $ds = htmlspecialchars($drv['skill_level'] ?? '-');
                                $dy = htmlspecialchars($drv['years_in_field'] ?? '0');
                        ?>
                        <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                            <i class="fas fa-user" style="font-size:10px;"></i>
                            <div class="tt-box" style="min-width:190px;">
                                <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                                <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo $dc; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo $dp; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo $ds; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الخبرة</span><span class="tt-v"><?php echo $dy; ?> سنة</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- ساعات التشغيل -->
                    <div class="eq-hours-row">
                        <div class="eq-hours-item">
                            <span class="eq-hours-val"><?php echo number_format($op['ts_total'], 1); ?></span>
                            <span class="eq-hours-lbl">إجمالي ساعات</span>
                        </div>
                        <div class="eq-hours-sep"></div>
                        <div class="eq-hours-item">
                            <span class="eq-hours-val today-val"><?php echo number_format($op['ts_today'], 1); ?></span>
                            <span class="eq-hours-lbl">ساعات اليوم</span>
                        </div>
                    </div>
                </div>
                <!-- tooltip بطاقة المعدة -->
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-truck-monster"></i> <?php echo htmlspecialchars($op['eq_name']); ?></div>
                    <?php foreach ($tt_eq_data as $k => $v): ?>
                    <div class="tt-row"><span class="tt-k"><?php echo $k; ?></span><span class="tt-v"><?php echo htmlspecialchars($v); ?></span></div>
                    <?php endforeach; ?>
                    <div class="tt-row"><span class="tt-k">المشغلون</span><span class="tt-v"><?php echo $drv_count; ?> أساسي · 0 احتياطي</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($stopped_list)): ?>
        <div class="section-sep stopped-sep">
            <i class="fas fa-pause-circle"></i> آليات متوقفة (<?php echo count($stopped_list); ?>)
        </div>
        <div class="eq-grid">
            <?php foreach ($stopped_list as $op):
                $drv_count = count($op['drivers']);
                $tt_eq_data = [
                    'الكود'   => $op['eq_code'] ?? '-',
                    'الاسم'   => $op['eq_name'] ?? '-',
                    'النوع'   => $op['type_name'] ?: '-',
                    'الحالة'  => $op['availability_status'] ?? '-',
                    'المورد'  => $op['supplier_name'] ?? '-',
                ];
            ?>
            <div class="eq-card stopped tt-trigger">
                <div class="eq-status-dot"></div>
                <div class="eq-inner">
                    <div class="eq-top-bar"></div>
                    <div class="eq-content">
                        <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                        <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                        <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                    </div>
                    <div class="op-row">
                        <?php if ($drv_count === 0): ?>
                        <span class="no-op-label"><i class="fas fa-user-slash" style="font-size:9px;"></i> لا مشغّل</span>
                        <?php else:
                            foreach ($op['drivers'] as $drv):
                                $dn = htmlspecialchars($drv['driver_name']);
                                $dc = htmlspecialchars($drv['driver_code'] ?? '-');
                                $dp = htmlspecialchars($drv['phone'] ?? '-');
                                $ds = htmlspecialchars($drv['skill_level'] ?? '-');
                                $dy = htmlspecialchars($drv['years_in_field'] ?? '0');
                        ?>
                        <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                            <i class="fas fa-user" style="font-size:10px;"></i>
                            <div class="tt-box" style="min-width:190px;">
                                <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                                <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo $dc; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo $dp; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo $ds; ?></span></div>
                                <div class="tt-row"><span class="tt-k">الخبرة</span><span class="tt-v"><?php echo $dy; ?> سنة</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- ساعات التشغيل -->
                    <div class="eq-hours-row">
                        <div class="eq-hours-item">
                            <span class="eq-hours-val"><?php echo number_format($op['ts_total'], 1); ?></span>
                            <span class="eq-hours-lbl">إجمالي ساعات</span>
                        </div>
                        <div class="eq-hours-sep"></div>
                        <div class="eq-hours-item">
                            <span class="eq-hours-val today-val"><?php echo number_format($op['ts_today'], 1); ?></span>
                            <span class="eq-hours-lbl">ساعات اليوم</span>
                        </div>
                    </div>
                </div>
                <div class="tt-box">
                    <div class="tt-title"><i class="fas fa-truck-monster"></i> <?php echo htmlspecialchars($op['eq_name']); ?></div>
                    <?php foreach ($tt_eq_data as $k => $v): ?>
                    <div class="tt-row"><span class="tt-k"><?php echo $k; ?></span><span class="tt-v"><?php echo htmlspecialchars($v); ?></span></div>
                    <?php endforeach; ?>
                    <div class="tt-row"><span class="tt-k">المشغلون</span><span class="tt-v"><?php echo $drv_count; ?> أساسي · 0 احتياطي</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* end empty check */ ?>
    </div><!-- .mine-body -->
</div><!-- .mine-card -->

<?php endforeach; ?>
</div><!-- .mines-grid -->

<?php endif; /* end empty($mines_data) */ ?>

<!-- ═══ معدات بلا منجم ═══ -->
<?php if (!empty($no_mine_equipments)): ?>
<div class="orphan-section">
    <div class="orphan-title">
        <i class="fas fa-exclamation-triangle" style="color:#e09900;"></i>
        آليات غير مرتبطة بمنجم محدد (<?php echo count($no_mine_equipments); ?>)
    </div>
    <div class="eq-grid">
        <?php foreach ($no_mine_equipments as $op):
            $css_cls   = $op['is_working'] ? 'working' : 'stopped';
            $drv_count = count($op['drivers']);
            $tt_eq_data = [
                'الكود'   => $op['eq_code'] ?? '-',
                'النوع'   => $op['type_name'] ?: '-',
                'الحالة'  => $op['availability_status'] ?? '-',
                'المورد'  => $op['supplier_name'] ?? '-',
            ];
        ?>
        <div class="eq-card <?php echo $css_cls; ?> tt-trigger">
            <div class="eq-status-dot"></div>
            <div class="eq-inner">
                <div class="eq-top-bar"></div>
                <div class="eq-content">
                    <div class="eq-machine-icon"><i class="fas fa-truck-monster"></i></div>
                    <div class="eq-code"><?php echo htmlspecialchars($op['eq_code']); ?></div>
                    <div class="eq-type-lbl"><?php echo htmlspecialchars($op['type_name'] ?: ($op['eq_name'] ?? '')); ?></div>
                </div>
                <div class="op-row">
                    <?php if ($drv_count === 0): ?>
                    <span class="no-op-label"><i class="fas fa-user-slash" style="font-size:9px;"></i> لا مشغّل</span>
                    <?php else:
                        foreach ($op['drivers'] as $drv):
                            $dn = htmlspecialchars($drv['driver_name']);
                    ?>
                    <div class="op-av active tt-trigger" title="<?php echo $dn; ?>">
                        <i class="fas fa-user" style="font-size:10px;"></i>
                        <div class="tt-box" style="min-width:190px;">
                            <div class="tt-title"><i class="fas fa-dharmachakra"></i> <?php echo $dn; ?></div>
                            <div class="tt-row"><span class="tt-k">الكود</span><span class="tt-v"><?php echo htmlspecialchars($drv['driver_code'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الهاتف</span><span class="tt-v"><?php echo htmlspecialchars($drv['phone'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الكفاءة</span><span class="tt-v"><?php echo htmlspecialchars($drv['skill_level'] ?? '-'); ?></span></div>
                            <div class="tt-row"><span class="tt-k">الخبرة</span><span class="tt-v"><?php echo htmlspecialchars($drv['years_in_field'] ?? '0'); ?> سنة</span></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- ساعات التشغيل -->
                <div class="eq-hours-row">
                    <div class="eq-hours-item">
                        <span class="eq-hours-val"><?php echo number_format($op['ts_total'], 1); ?></span>
                        <span class="eq-hours-lbl">إجمالي ساعات</span>
                    </div>
                    <div class="eq-hours-sep"></div>
                    <div class="eq-hours-item">
                        <span class="eq-hours-val today-val"><?php echo number_format($op['ts_today'], 1); ?></span>
                        <span class="eq-hours-lbl">ساعات اليوم</span>
                    </div>
                </div>
            </div>
            <div class="tt-box">
                <div class="tt-title"><i class="fas fa-truck-monster"></i> <?php echo htmlspecialchars($op['eq_name']); ?></div>
                <?php foreach ($tt_eq_data as $k => $v): ?>
                <div class="tt-row"><span class="tt-k"><?php echo $k; ?></span><span class="tt-v"><?php echo htmlspecialchars($v); ?></span></div>
                <?php endforeach; ?>
                <div class="tt-row"><span class="tt-k">المشغلون</span><span class="tt-v"><?php echo $drv_count; ?> أساسي · 0 احتياطي</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div><!-- .map-page -->
</div><!-- .main-content -->

<script>
/**
 * نظام Tooltip الذكي — يعرض tooltip للعنصر الأعمق فقط
 * الأولوية: مشغّل > آلية > منجم
 */
(function () {
    'use strict';

    var activeBox = null;

    /* إخفاء الـ tooltip الحالي */
    function closeActive() {
        if (activeBox) {
            activeBox.classList.remove('tt-show');
            activeBox.classList.remove('tt-below');
            activeBox = null;
        }
    }

    /* ضبط موضع الـ tooltip حتى لا يخرج عن الشاشة */
    function positionBox(trigger, box) {
        /* reset */
        box.style.right     = '';
        box.style.left      = '';
        box.style.transform = '';
        box.classList.remove('tt-below');

        var trigRect = trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        /* هل يوجد مساحة فوق؟ */
        var boxH = 170; /* تقدير */
        if (trigRect.top < boxH + 18) {
            box.classList.add('tt-below');
        }

        /* ضبط أفقي: ابدأ بالتمركز */
        box.style.right     = '50%';
        box.style.transform = 'translateX(50%)';

        /* بعد render نتحقق من الحدود الفعلية */
        requestAnimationFrame(function () {
            var bRect = box.getBoundingClientRect();
            if (bRect.right > vw - 12) {
                box.style.right     = '0';
                box.style.left      = 'auto';
                box.style.transform = 'none';
            } else if (bRect.left < 12) {
                box.style.right     = 'auto';
                box.style.left      = '0';
                box.style.transform = 'none';
            }
        });
    }

    /* معالج mouseover الرئيسي — يُعالج على مستوى document */
    document.addEventListener('mouseover', function (e) {
        /* ابحث عن أعمق .tt-trigger يحتوي العنصر المحوّم */
        var target = e.target;
        var deepest = null;
        var el = target;

        while (el && el !== document.body) {
            if (el.classList && el.classList.contains('tt-trigger')) {
                deepest = el;
                break;   /* أعمق عنصر هو الأول في الصعود */
            }
            el = el.parentElement;
        }

        if (!deepest) {
            closeActive();
            return;
        }

        /* الـ tt-box المباشر للعنصر الأعمق فقط */
        var box = null;
        var children = deepest.childNodes;
        for (var i = 0; i < children.length; i++) {
            var c = children[i];
            if (c.nodeType === 1 && c.classList.contains('tt-box')) {
                box = c;
                break;
            }
        }

        if (!box) { closeActive(); return; }

        /* إن كان نفس الـ tooltip المفتوح بالفعل، لا حاجة لتغيير */
        if (box === activeBox) return;

        /* إخفاء القديم وعرض الجديد */
        closeActive();
        positionBox(deepest, box);
        box.classList.add('tt-show');
        activeBox = box;
    });

    /* إخفاء عند مغادرة الصفحة */
    document.addEventListener('mouseleave', closeActive);

    /* إخفاء عند النقر */
    document.addEventListener('click', closeActive);

    /* إخفاء عند التمرير (scroll) لتجنب tooltip عالق */
    document.addEventListener('scroll', closeActive, true);

}());
</script>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/js/sidebar.js"></script>
</body>
</html>
