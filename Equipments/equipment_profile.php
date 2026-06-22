<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once '../includes/driver_contract_dates.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header('Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌');
    exit();
}

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) {
    header('Location: equipments.php?msg=معرف+المعدة+غير+صحيح+❌');
    exit();
}

$equipments_has_company = db_table_has_column($conn, 'equipments', 'company_id');
$operations_has_company = db_table_has_column($conn, 'operations', 'company_id');
$equipment_drivers_has_company = db_table_has_column($conn, 'equipment_drivers', 'company_id');

// صلاحية اعتماد الكرت = صلاحية تعديل المعدات (دور الأسطول)
$__pp = function_exists('check_page_permissions') ? check_page_permissions($conn, 'equipments_fleet') : ['can_edit' => true];
$can_edit = !empty($__pp['can_edit']);

$scope = "e.id = $equipment_id";
if (!$is_super_admin && $equipments_has_company) {
    $scope .= " AND e.company_id = $company_id";
}

$equipment_query = "SELECT e.*, s.name AS supplier_name, et.type AS equipment_type_name
                    FROM equipments e
                    LEFT JOIN suppliers s ON s.id = e.suppliers
                    LEFT JOIN equipments_types et ON et.id = e.type
                    WHERE $scope
                    LIMIT 1";
$equipment_result = mysqli_query($conn, $equipment_query);
$equipment = ($equipment_result && mysqli_num_rows($equipment_result) > 0) ? mysqli_fetch_assoc($equipment_result) : null;

if (!$equipment) {
    header('Location: equipments.php?msg=المعدة+غير+موجودة+او+خارج+نطاق+الشركة+❌');
    exit();
}

$ops_scope = "o.equipment = $equipment_id";
if (!$is_super_admin && $operations_has_company) {
    $ops_scope .= " AND o.company_id = $company_id";
}
$ed_scope = "ed.equipment_id = $equipment_id";
if (!$is_super_admin && $equipment_drivers_has_company) {
    $ed_scope .= " AND ed.company_id = $company_id";
}

$operations_count = 0;
$active_operations = 0;
$projects_count = 0;
$drivers_count = 0;
$hours_sum = 0;
$standby_sum = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $operations_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM operations o WHERE $ops_scope AND o.status = 1");
if ($r) {
    $active_operations = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT o.project_id) AS c FROM operations o WHERE $ops_scope");
if ($r) {
    $projects_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT COUNT(DISTINCT ed.driver_id) AS c FROM equipment_drivers ed WHERE $ed_scope AND ed.status = 1");
if ($r) {
    $drivers_count = intval(mysqli_fetch_assoc($r)['c']);
}
$r = mysqli_query($conn, "SELECT IFNULL(SUM(t.operator_hours),0) AS op_hours,
                                IFNULL(SUM(t.operator_standby_hours),0) AS standby_hours
                         FROM timesheet t
                         INNER JOIN operations o ON o.id = t.operator
                         WHERE $ops_scope AND t.status = 1");
if ($r) {
    $hours_row = mysqli_fetch_assoc($r);
    $hours_sum = floatval($hours_row['op_hours']);
    $standby_sum = floatval($hours_row['standby_hours']);
}

$projects_list = mysqli_query($conn, "SELECT
                            p.id,
                            p.name,
                            p.project_code,
                            IFNULL(SUM(t.operator_hours + t.operator_standby_hours),0) AS total_hours,
                            COUNT(t.id) AS shifts_count
                        FROM operations o
                        LEFT JOIN project p ON p.id = o.project_id
                        LEFT JOIN timesheet t ON t.operator = o.id AND t.status = 1
                        WHERE $ops_scope
                        GROUP BY p.id, p.name, p.project_code
                        ORDER BY total_hours DESC
                        LIMIT 10");

$drivers_list = mysqli_query($conn, "SELECT
                           d.id,
                           d.name,
                           ed.start_date,
                           ed.end_date,
                           ed.status
                        FROM equipment_drivers ed
                        INNER JOIN employees d ON d.id = ed.driver_id
                        WHERE $ed_scope
                        ORDER BY ed.id DESC
                        LIMIT 10");

// ═══════════════════════════════════════════════════════════════════
//  كرت المعدة — جداول الأبناء (وثائق · حماية · مكوّنات · تاريخ)
// ═══════════════════════════════════════════════════════════════════
$can_edit_card = !empty($can_edit);
$child_company_scope = (!$is_super_admin && $company_id > 0) ? " AND company_id = $company_id" : "";

// قوائم ثابتة
$DOC_TYPES        = ['تأمين', 'رخصة', 'شهادة فحص', 'شهادة سلامة', 'شهادة رفع', 'شهادة معايرة', 'أخرى'];
$PROTECTION_TYPES = ['تنجيد مقاعد', 'شبك حماية زجاج', 'حمايات معدنية', 'نظام إطفاء', 'نظام تتبّع', 'تجهيزات سلامة', 'حماية تشغيل', 'تأمين شامل', 'تأمين هندسي', 'أخرى'];
$PROTECTION_STATES = ['فعّال', 'يحتاج تجديداً', 'منتهٍ/مفكوك'];
$COMPONENT_TYPES  = ['محرك', 'هيدروليك', 'جيربوكس', 'دفرنس', 'مولّد', 'أخرى'];
$EVENT_TYPES      = ['دخول', 'تشغيل بمشروع', 'خروج', 'ترحيل', 'صيانة', 'عطل', 'حادث/ضرر', 'تفتيش', 'إيقاف', 'إعادة تشغيل', 'تغيير مصدر', 'خروج/بيع'];

// جلب السطور (مع تحقّق وجود الجداول للتوافق الرجعي)
$compliance_rows = $protection_rows = $component_rows = $history_rows = [];
$fc_exists = db_table_has_column($conn, 'fleet_equipment_compliance', 'id');
$fp_exists = db_table_has_column($conn, 'fleet_equipment_protection', 'id');
$fcmp_exists = db_table_has_column($conn, 'fleet_equipment_component', 'id');
$fh_exists = db_table_has_column($conn, 'fleet_equipment_history', 'id');

if ($fc_exists) {
    $q = mysqli_query($conn, "SELECT * FROM fleet_equipment_compliance WHERE equipment_id = $equipment_id AND is_deleted = 0$child_company_scope ORDER BY (expiry_date IS NULL), expiry_date ASC, id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $compliance_rows[] = $r;
}
if ($fp_exists) {
    $q = mysqli_query($conn, "SELECT p.* FROM fleet_equipment_protection p WHERE p.equipment_id = $equipment_id AND p.is_deleted = 0" . (!$is_super_admin && $company_id > 0 ? " AND p.company_id = $company_id" : '') . " ORDER BY p.id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $protection_rows[] = $r;
}
if ($fcmp_exists) {
    $q = mysqli_query($conn, "SELECT * FROM fleet_equipment_component WHERE equipment_id = $equipment_id AND is_deleted = 0$child_company_scope ORDER BY is_current DESC, id DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $component_rows[] = $r;
}
if ($fh_exists) {
    $q = mysqli_query($conn, "SELECT h.*, pr.name AS project_name FROM fleet_equipment_history h LEFT JOIN project pr ON pr.id = h.project_id WHERE h.equipment_id = $equipment_id" . (!$is_super_admin && $company_id > 0 ? " AND h.company_id = $company_id" : '') . " ORDER BY h.event_date DESC, h.id DESC LIMIT 100");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $history_rows[] = $r;
}

// ═══════════════════════════════════════════════════════════════════
//  قسم الصيانة — أوامر هذه المعدة + مؤشرات (مرئي لكل من يفتح الكرت/قراءة)
//  المصدر: mnt_order (معزول بالشركة). المؤشرات تُحتسب من بيانات الأوامر + ساعات التشغيل.
// ═══════════════════════════════════════════════════════════════════
$mnt_orders = array();
$mnt_total = $mnt_closed = $mnt_failures = $mnt_open = 0;
$mnt_downtime = 0.0; $mnt_cost = 0.0;
$mnt_last = isset($equipment['last_maintenance_date']) ? $equipment['last_maintenance_date'] : null;
$mnt_scope = $is_super_admin ? "" : " AND mo.company_id = $company_id";
if (db_table_has_column($conn, 'mnt_order', 'id')) {
    $q = mysqli_query($conn, "SELECT mo.id, mo.code, mo.source, mo.maint_type, mo.state,
                                     mo.downtime_hours, mo.total_cost, mo.work_start, mo.work_end, mo.closed_at
                                FROM mnt_order mo
                               WHERE mo.equipment_id = $equipment_id AND COALESCE(mo.is_deleted,0)=0 $mnt_scope
                               ORDER BY mo.id DESC LIMIT 50");
    if ($q) while ($r = mysqli_fetch_assoc($q)) { $mnt_orders[] = $r; }

    $agg = mysqli_query($conn, "SELECT COUNT(*) total,
                                       SUM(state='إغلاق') closed,
                                       SUM(state IN ('بلاغ','تنفيذ','فحص')) opened,
                                       COALESCE(SUM(downtime_hours),0) downtime,
                                       COALESCE(SUM(total_cost),0) cost,
                                       SUM(source='بلاغ') failures,
                                       MAX(closed_at) last_closed
                                  FROM mnt_order mo
                                 WHERE mo.equipment_id = $equipment_id AND COALESCE(mo.is_deleted,0)=0 $mnt_scope");
    if ($agg && ($a = mysqli_fetch_assoc($agg))) {
        $mnt_total    = intval($a['total']);
        $mnt_closed   = intval($a['closed']);
        $mnt_open     = intval($a['opened']);
        $mnt_downtime = floatval($a['downtime']);
        $mnt_cost     = floatval($a['cost']);
        $mnt_failures = intval($a['failures']);
        if (!empty($a['last_closed'])) { $mnt_last = $a['last_closed']; }
    }
}
// المؤشرات: MTBF = ساعات التشغيل / عدد الأعطال · MTTR = إجمالي التوقّف / الأوامر المغلقة
// نسبة الجاهزية = التشغيل / (التشغيل + التوقّف) ×100
$mnt_mtbf  = $mnt_failures > 0 ? ($hours_sum / $mnt_failures) : null;
$mnt_mttr  = $mnt_closed   > 0 ? ($mnt_downtime / $mnt_closed) : null;
$mnt_avail = ($hours_sum + $mnt_downtime) > 0 ? ($hours_sum / ($hours_sum + $mnt_downtime) * 100) : null;

// ═══════════════════════════════════════════════════════════════════
//  قسم التفتيش الفني — تفتيشات هذه المعدة (المصدر: mnt_inspection، معزول بالشركة)
// ═══════════════════════════════════════════════════════════════════
$ins_rows = array();
$ins_total = $ins_done = $ins_open = $ins_critical = 0;
$ins_last = null;
if (db_table_has_column($conn, 'mnt_inspection', 'id')) {
    $ins_scope = $is_super_admin ? "" : " AND i.company_id = $company_id";
    $q = mysqli_query($conn, "SELECT i.id, i.code, i.inspection_type, i.scheduled_date, i.completed_at,
                                     i.overall_result, i.state, u.name AS inspector_name
                                FROM mnt_inspection i
                                LEFT JOIN users u ON u.id = i.inspector_id
                               WHERE i.equipment_id = $equipment_id AND COALESCE(i.is_deleted,0)=0 $ins_scope
                               ORDER BY i.id DESC LIMIT 50");
    if ($q) while ($r = mysqli_fetch_assoc($q)) { $ins_rows[] = $r; }

    $agg = mysqli_query($conn, "SELECT COUNT(*) total,
                                       SUM(state IN ('مكتمل','مغلق')) done,
                                       SUM(state IN ('جديد','مجدول','قيد التنفيذ')) opened,
                                       MAX(COALESCE(completed_at, scheduled_date)) last_at
                                  FROM mnt_inspection i
                                 WHERE i.equipment_id = $equipment_id AND COALESCE(i.is_deleted,0)=0 $ins_scope");
    if ($agg && ($a = mysqli_fetch_assoc($agg))) {
        $ins_total = intval($a['total']);
        $ins_done  = intval($a['done']);
        $ins_open  = intval($a['opened']);
        $ins_last  = !empty($a['last_at']) ? $a['last_at'] : null;
    }
    // ملاحظات حرجة من بنود الفحص لهذه المعدة
    if (db_table_has_column($conn, 'mnt_inspection_line', 'id')) {
        $cq = mysqli_query($conn, "SELECT COUNT(*) c
                                     FROM mnt_inspection_line l
                                     INNER JOIN mnt_inspection i ON i.id = l.inspection_id
                                    WHERE i.equipment_id = $equipment_id AND COALESCE(i.is_deleted,0)=0
                                      AND l.condition_state = 'حرج' $ins_scope");
        if ($cq && ($c = mysqli_fetch_assoc($cq))) { $ins_critical = intval($c['c']); }
    }
}

// ═══════════════════════════════════════════════════════════════════
//  قسم الصيانة الوقائية — خطط هذه المعدة (المصدر: mnt_plan، معزول بالشركة)
// ═══════════════════════════════════════════════════════════════════
$pln_rows = array();
$pln_total = $pln_active = $pln_due = 0;
$pln_last = null; $pln_next = null;
$today_str = date('Y-m-d');
if (db_table_has_column($conn, 'mnt_plan', 'id')) {
    $pln_scope = $is_super_admin ? "" : " AND pl.company_id = $company_id";
    $q = mysqli_query($conn, "SELECT pl.id, pl.code, pl.name, pl.trigger_basis, pl.interval_value,
                                     pl.last_done_date, pl.last_done_meter, pl.next_due_date, pl.next_due_meter, pl.state
                                FROM mnt_plan pl
                               WHERE pl.equipment_id = $equipment_id AND COALESCE(pl.is_deleted,0)=0 $pln_scope
                               ORDER BY pl.id DESC LIMIT 50");
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $pln_rows[] = $r;
        if ($r['state'] === 'نشطة') {
            $pln_active++;
            if ($r['trigger_basis'] === 'ساعات') {
                if ($r['next_due_meter'] !== null && $hours_sum >= floatval($r['next_due_meter'])) { $pln_due++; }
            } else {
                if (!empty($r['next_due_date']) && $r['next_due_date'] <= $today_str) { $pln_due++; }
            }
        }
        if (!empty($r['next_due_date']) && ($pln_next === null || $r['next_due_date'] < $pln_next)) { $pln_next = $r['next_due_date']; }
        if (!empty($r['last_done_date']) && ($pln_last === null || $r['last_done_date'] > $pln_last)) { $pln_last = $r['last_done_date']; }
    }
    $pln_total = count($pln_rows);
}

// المنفّذ/المورد صار إدخالاً يدوياً حرّاً (غير مربوط بجدول الموردين) — لا حاجة لجلب الموردين.

// حساب حالة الوثيقة من تاريخ الانتهاء (سارية/قاربت/منتهية) + تنبيهات حرجة
$DOC_ALERT_DAYS = 30;
$today_ts = strtotime(date('Y-m-d'));
$critical_expired = 0; $docs_expired = 0; $docs_soon = 0;
function ems_doc_status($expiry, $today_ts, $days)
{
    if (empty($expiry) || $expiry === '0000-00-00') return ['code' => 'none', 'label' => '—', 'cls' => ''];
    $ts = strtotime($expiry);
    if (!$ts) return ['code' => 'none', 'label' => '—', 'cls' => ''];
    if ($ts < $today_ts) return ['code' => 'expired', 'label' => 'منتهية', 'cls' => 'status-inactive'];
    if ($ts <= $today_ts + ($days * 86400)) return ['code' => 'soon', 'label' => 'قاربت الانتهاء', 'cls' => 'badge-busy'];
    return ['code' => 'valid', 'label' => 'سارية', 'cls' => 'status-active'];
}
foreach ($compliance_rows as $cr) {
    $stt = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS);
    if ($stt['code'] === 'expired') { $docs_expired++; if (!empty($cr['is_critical'])) $critical_expired++; }
    elseif ($stt['code'] === 'soon') { $docs_soon++; }
}

$ee = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

$page_title = 'إيكوبيشن | بطاقة المعدة';
include '../inheader.php';
include '../insidebar.php';
?>

<style>
/* ════════ بطاقة المعدة — إعادة تصميم مواءِمة لهوية الموقع (برتقالي/كهرماني + design-tokens) ════════ */
.equipment-profile-page{ --ep-accent:var(--brand-orange-bright,#E67E00); }

/* الهيرو */
.equipment-profile-page .ep-hero{
  position:relative; overflow:hidden; padding:18px 20px; margin-bottom:14px;
  border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-xl,16px);
  background:
    radial-gradient(120% 150% at 100% 0%, rgba(247,147,26,.10) 0%, rgba(247,147,26,0) 46%),
    linear-gradient(180deg,#fff 0%, var(--gray-50,#FAFAF9) 100%);
  box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.05));
}
.equipment-profile-page .ep-hero::before{
  content:""; position:absolute; inset-inline-start:0; top:0; bottom:0; width:5px;
  background:linear-gradient(180deg,var(--brand-amber,#F2AA2A),var(--brand-orange-bright,#E67E00));
}
.equipment-profile-page .ep-hero-top{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; justify-content:space-between; }
.equipment-profile-page .ep-hero-name{ display:flex; align-items:center; gap:14px; min-width:0; }
.equipment-profile-page .ep-hero-ic{
  width:50px; height:50px; flex:0 0 50px; border-radius:14px; display:grid; place-items:center; color:#fff; font-size:22px;
  background:linear-gradient(160deg,var(--brand-amber,#F2AA2A),var(--brand-orange-bright,#E67E00));
  box-shadow:0 6px 16px rgba(230,126,0,.30);
}
.equipment-profile-page .ep-hero-name h2{ margin:0; font-size:var(--text-h1,24px); font-weight:800; color:var(--gray-900,#1C1917); line-height:1.2; }
.equipment-profile-page .ep-chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.equipment-profile-page .ep-chip{
  display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:3px 10px;
  border-radius:var(--radius-pill,9999px); background:var(--gray-100,#F5F5F4); color:var(--gray-700,#44403C); border:1px solid var(--gray-200,#E7E5E4);
}
.equipment-profile-page .ep-chip i{ color:var(--ep-accent); }
.equipment-profile-page .ep-hero-actions{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.equipment-profile-page .ep-pill{ display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:var(--radius-pill,9999px); font-weight:700; font-size:12.5px; }
.equipment-profile-page .ep-pill i{ font-size:9px; }

/* شريط الحقائق */
.equipment-profile-page .ep-facts{
  display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1px; margin-top:16px;
  background:var(--gray-200,#E7E5E4); border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-md,8px); overflow:hidden;
}
.equipment-profile-page .ep-fact{ background:#fff; padding:10px 13px; }
.equipment-profile-page .ep-fact .k{ font-size:11px; color:var(--gray-500,#78716C); font-weight:600; }
.equipment-profile-page .ep-fact .v{ font-size:14px; color:var(--gray-900,#1C1917); font-weight:700; margin-top:2px; }

/* التبويبات */
.equipment-profile-page .ep-tabs{
  position:sticky; top:var(--topbar-height,60px); z-index:var(--z-sticky,10);
  display:flex; flex-wrap:wrap; gap:6px; padding:6px; margin-bottom:16px;
  background:rgba(255,255,255,.94); backdrop-filter:blur(8px);
  border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-lg,12px); box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.05));
}
.equipment-profile-page .ep-tab{
  appearance:none; border:0; cursor:pointer; font-family:inherit; font-weight:700; font-size:13.5px;
  display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:var(--radius-md,8px);
  color:var(--gray-700,#44403C); background:transparent; transition:all var(--transition-fast,150ms ease);
}
.equipment-profile-page .ep-tab i{ font-size:14px; opacity:.9; }
.equipment-profile-page .ep-tab:hover{ background:var(--gray-100,#F5F5F4); color:var(--gray-900,#1C1917); }
.equipment-profile-page .ep-tab.is-active{
  background:linear-gradient(160deg,var(--brand-amber,#F2AA2A),var(--brand-orange-bright,#E67E00)); color:#fff;
  box-shadow:0 4px 12px rgba(230,126,0,.28);
}
.equipment-profile-page .ep-tab-badge{
  font-size:11px; font-weight:800; min-width:19px; height:19px; padding:0 6px; border-radius:10px;
  display:inline-grid; place-items:center; background:var(--gray-200,#E7E5E4); color:var(--gray-700,#44403C);
}
.equipment-profile-page .ep-tab.is-active .ep-tab-badge{ background:rgba(255,255,255,.30); color:#fff; }

/* اللوحات */
.equipment-profile-page .ep-tab-panel{ display:none; }
.equipment-profile-page .ep-tab-panel.is-active{ display:block; animation:epFade .25s ease; }
@keyframes epFade{ from{opacity:0; transform:translateY(5px);} to{opacity:1; transform:none;} }

/* الشبكات والمؤشرات — تملأ العرض بلا فراغات */
.equipment-profile-page .profile-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(165px,1fr)); gap:12px; margin-bottom:0; grid-auto-flow:dense; }
.equipment-profile-page .profile-card{
  background:#fff; border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-md,8px); padding:12px 14px;
  transition:border-color var(--transition-fast,150ms ease), box-shadow var(--transition-fast,150ms ease);
}
.equipment-profile-page .profile-card:hover{ border-color:var(--gray-300,#D6D3D1); box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.05)); }
.equipment-profile-page .kpi{ font-weight:800; font-size:1.55rem; color:var(--gray-900,#1C1917); line-height:1.1; }
.equipment-profile-page .label{ color:var(--gray-500,#78716C); font-size:.86rem; }
.equipment-profile-page .profile-card > div:not(.label):not(.kpi){ font-weight:600; color:var(--gray-900,#1C1917); margin-top:2px; }

/* تجميل البطاقات داخل الصفحة */
.equipment-profile-page .card{ border:1px solid var(--gray-200,#E7E5E4); border-radius:var(--radius-lg,12px); box-shadow:var(--shadow-sm,0 1px 2px rgba(0,0,0,.05)); margin-bottom:14px; }
.equipment-profile-page .card-header h5{ font-size:var(--text-h3,16px); font-weight:700; color:var(--gray-900,#1C1917); display:flex; align-items:center; gap:8px; }
.equipment-profile-page .card-header h5 i{ color:var(--ep-accent); }
</style>

<div class="main equipment-profile-page ems-unified-page-shell">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_title   = 'بطاقة المعدة / الشاحنة';
    $header_icon    = 'fas fa-id-card';
    $header_actions = array(
        array('href' => 'add_drivers.php?equipment_id=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-user-cog', 'label' => 'إدارة المشغلين'),
        array('href' => 'equipments.php?edit=' . intval($equipment_id), 'class' => 'add-btn', 'icon' => 'fas fa-edit', 'label' => 'تعديل المعدة'),
    );
    $header_back = array('href' => 'equipments.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php
    // حالة الكرت (حوكمة خفيفة) + الحالة التشغيلية
    $card_state     = isset($equipment['card_state']) ? $equipment['card_state'] : 'active';
    $card_is_active = ($card_state === 'active');
    $status_avail   = intval($equipment['status']) === 1;
    ?>
    <div class="ep-hero">
        <div class="ep-hero-top">
            <div class="ep-hero-name">
                <div class="ep-hero-ic"><i class="fas fa-truck-monster"></i></div>
                <div>
                    <h2><?php echo $ee($equipment['name']); ?></h2>
                    <div class="ep-chips">
                        <span class="ep-chip"><i class="fas fa-barcode"></i> <?php echo $ee($equipment['code']); ?></span>
                        <span class="ep-chip"><i class="fas fa-layer-group"></i> <?php echo $ee($equipment['equipment_type_name'] ?: $equipment['type']); ?></span>
                        <span class="ep-chip"><i class="fas fa-truck"></i> <?php echo $ee($equipment['supplier_name'] ?: '—'); ?></span>
                    </div>
                </div>
            </div>
            <div class="ep-hero-actions">
                <span class="ep-pill <?php echo $status_avail ? 'status-active' : 'status-inactive'; ?>">
                    <i class="fas fa-circle"></i> <?php echo $status_avail ? 'متاحة' : 'مشغولة'; ?>
                </span>
                <?php if ($card_is_active): ?>
                    <span class="ep-pill status-active"><i class="fas fa-id-card"></i> كرت معتمد</span>
                <?php else: ?>
                    <span class="ep-pill status-inactive"><i class="fas fa-id-card"></i> كرت مسودة</span>
                    <?php if (!empty($can_edit)): ?>
                        <form method="post" action="approve_card.php" class="d-inline" onsubmit="return confirm('اعتماد كرت هذه المعدة؟');">
                            <input type="hidden" name="equipment_id" value="<?php echo intval($equipment_id); ?>">
                            <input type="hidden" name="return" value="equipment_profile.php">
                            <input type="hidden" name="return_id" value="<?php echo intval($equipment_id); ?>">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-circle-check"></i> اعتماد الكرت</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="ep-facts">
            <div class="ep-fact"><div class="k">الموديل</div><div class="v"><?php echo $ee($equipment['model'] ?: '—'); ?></div></div>
            <div class="ep-fact"><div class="k">سنة الصنع</div><div class="v"><?php echo $ee($equipment['manufacturing_year'] ?: '—'); ?></div></div>
            <div class="ep-fact"><div class="k">رقم الهيكل</div><div class="v"><?php echo $ee($equipment['chassis_number'] ?: '—'); ?></div></div>
            <div class="ep-fact"><div class="k">ساعات التشغيل</div><div class="v"><?php echo number_format($hours_sum, 0); ?></div></div>
        </div>
    </div>

    <!-- ════════ شريط التبويبات ════════ -->
    <div class="ep-tabs" role="tablist">
        <button type="button" class="ep-tab is-active" data-ep-tab="overview"><i class="fas fa-circle-info"></i> نظرة عامة</button>
        <button type="button" class="ep-tab" data-ep-tab="operations"><i class="fas fa-diagram-project"></i> التشغيل <span class="ep-tab-badge"><?php echo intval($projects_count); ?></span></button>
        <button type="button" class="ep-tab" data-ep-tab="maintenance"><i class="fas fa-wrench"></i> الصيانة والتفتيش <span class="ep-tab-badge"><?php echo intval($mnt_total + $ins_total + $pln_total); ?></span></button>
        <button type="button" class="ep-tab" data-ep-tab="records"><i class="fas fa-folder-open"></i> الوثائق والسجل <span class="ep-tab-badge"><?php echo intval(count($compliance_rows) + count($protection_rows) + count($component_rows)); ?></span></button>
    </div>

    <div class="ep-panels">
    <!-- ════════ لوحة: نظرة عامة ════════ -->
    <div class="ep-tab-panel is-active" id="tab-overview">

    <?php
    // ── بطاقة: الهوية والمصدر + العدّاد (كرت المعدة) ──
    $pf = function ($k) use ($equipment) {
        return isset($equipment[$k]) && $equipment[$k] !== '' && $equipment[$k] !== null
            ? htmlspecialchars((string) $equipment[$k]) : '—';
    };
    $cap = (isset($equipment['capacity']) && $equipment['capacity'] !== '' && $equipment['capacity'] !== null)
        ? (htmlspecialchars((string) $equipment['capacity']) . ' ' . htmlspecialchars((string) ($equipment['capacity_uom'] ?? ''))) : '—';
    $acq = (isset($equipment['acquisition_cost']) && $equipment['acquisition_cost'] !== '' && $equipment['acquisition_cost'] !== null)
        ? (htmlspecialchars((string) $equipment['acquisition_cost']) . ' ' . htmlspecialchars((string) ($equipment['acquisition_currency'] ?? ''))) : '—';
    $meter = (isset($equipment['opening_meter']) && $equipment['opening_meter'] !== '' && $equipment['opening_meter'] !== null)
        ? (htmlspecialchars((string) $equipment['opening_meter']) . ' ' . htmlspecialchars((string) ($equipment['meter_uom'] ?? ''))) : '—';
    ?>
    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-id-badge"></i> الهوية والمصدر والعدّاد</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="label">الفئة التشغيلية</div><div><?php echo $pf('operating_category'); ?></div></div>
                <div class="profile-card"><div class="label">بلد الصنع</div><div><?php echo $pf('origin_country'); ?></div></div>
                <div class="profile-card"><div class="label">رقم الموتور</div><div><?php echo $pf('engine_no'); ?></div></div>
                <div class="profile-card"><div class="label">رقم اللوحة</div><div><?php echo $pf('plate_no'); ?></div></div>
                <div class="profile-card"><div class="label">السعة/القدرة</div><div><?php echo $cap; ?></div></div>
                <div class="profile-card"><div class="label">المقاسات الفنية</div><div><?php echo $pf('dimensions'); ?></div></div>
                <div class="profile-card"><div class="label">نوع المصدر</div><div><?php echo $pf('source_type'); ?></div></div>
                <div class="profile-card"><div class="label">تاريخ الدخول</div><div><?php echo $pf('entry_date'); ?></div></div>
                <div class="profile-card"><div class="label">تكلفة الشراء</div><div><?php echo $acq; ?></div></div>
                <div class="profile-card"><div class="label">العدّاد الافتتاحي</div><div><?php echo $meter; ?></div></div>
                <div class="profile-card"><div class="label">مصدر العدّاد</div><div><?php echo $pf('meter_source'); ?></div></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-gauge-high"></i> ملخص التشغيل</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="kpi"><?php echo $operations_count; ?></div><div class="label">إجمالي عمليات التشغيل</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $active_operations; ?></div><div class="label">عمليات نشطة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $projects_count; ?></div><div class="label">المشاريع المرتبطة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $drivers_count; ?></div><div class="label">المشغلون النشطون</div></div>
                <div class="profile-card"><div class="kpi"><?php echo number_format($hours_sum, 0); ?></div><div class="label">ساعات التشغيل</div></div>
                <div class="profile-card"><div class="kpi"><?php echo number_format($standby_sum, 0); ?></div><div class="label">ساعات الاستعداد</div></div>
            </div>
        </div>
    </div>
    </div><!-- /#tab-overview -->

    <!-- ════════ لوحة: التشغيل ════════ -->
    <div class="ep-tab-panel" id="tab-operations">
    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-project-diagram"></i> المشاريع المرتبطة بالمعدة</h5></div>
        <div class="card-body">
            <table id="equipmentProjectsTable" class="display" style="width:100%;">
                <thead><tr><th>المشروع</th><th>كود المشروع</th><th>الساعات</th><th>عدد الورديات</th></tr></thead>
                <tbody>
                    <?php if ($projects_list): while ($row = mysqli_fetch_assoc($projects_list)): ?>
                        <tr>
                            <td><?php if (!empty($row['id'])): ?><a href="../Projects/project_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a><?php else: ?>غير محدد<?php endif; ?></td>
                            <td><?php echo htmlspecialchars($row['project_code'] ?: '-'); ?></td>
                            <td><?php echo number_format($row['total_hours'], 0); ?></td>
                            <td><?php echo intval($row['shifts_count']); ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-users"></i> آخر المشغلين المرتبطين</h5></div>
        <div class="card-body">
            <table id="equipmentDriversTable" class="display" style="width:100%;">
                <thead><tr><th>المشغل</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr></thead>
                <tbody>
                    <?php if ($drivers_list): while ($row = mysqli_fetch_assoc($drivers_list)): ?>
                        <tr>
                            <td><a href="../Drivers/driver_profile.php?id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                            <td><?php echo htmlspecialchars(ems_format_open_end($row['end_date'])); ?></td>
                            <td><?php echo intval($row['status']) === 1 ? 'نشط' : 'متوقف'; ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </div><!-- /#tab-operations -->

    <!-- ════════ لوحة: الصيانة والتفتيش ════════ -->
    <div class="ep-tab-panel" id="tab-maintenance">
    <!-- قسم الصيانة (مؤشرات + أوامر) -->
    <div class="card" id="sec-maintenance" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-wrench"></i> الصيانة — المؤشرات وأوامر الصيانة</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="kpi"><?php echo intval($mnt_total); ?></div><div class="label">إجمالي أوامر الصيانة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($mnt_open); ?></div><div class="label">أوامر مفتوحة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($mnt_failures); ?></div><div class="label">أعطال (من بلاغ)</div></div>
                <div class="profile-card"><div class="kpi"><?php echo number_format($mnt_downtime, 1); ?></div><div class="label">ساعات التوقّف</div></div>
                <div class="profile-card"><div class="kpi"><?php echo number_format($mnt_cost, 0); ?></div><div class="label">إجمالي تكلفة الصيانة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $mnt_last ? htmlspecialchars((string) $mnt_last) : '—'; ?></div><div class="label">آخر صيانة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $mnt_mtbf !== null ? number_format($mnt_mtbf, 1) : '—'; ?></div><div class="label">MTBF (ساعة/عطل)</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $mnt_mttr !== null ? number_format($mnt_mttr, 1) : '—'; ?></div><div class="label">MTTR (ساعة/أمر)</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $mnt_avail !== null ? number_format($mnt_avail, 1) . '%' : '—'; ?></div><div class="label">نسبة الجاهزية</div></div>
            </div>

            <div class="table-container" style="margin-top:12px;">
                <table class="display" style="width:100%;">
                    <thead><tr><th>المرجع</th><th>المصدر</th><th>النوع</th><th>الحالة</th><th>التوقّف (ساعة)</th><th>التكلفة</th><th>الإغلاق</th></tr></thead>
                    <tbody>
                        <?php if (empty($mnt_orders)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#888;">لا توجد أوامر صيانة لهذه المعدة</td></tr>
                        <?php else: foreach ($mnt_orders as $mo): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $mo['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) $mo['source']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mo['maint_type'] ?: '—')); ?></td>
                                <td><span class="action-btn"><?php echo htmlspecialchars((string) $mo['state']); ?></span></td>
                                <td><?php echo number_format((float) $mo['downtime_hours'], 1); ?></td>
                                <td><?php echo number_format((float) $mo['total_cost'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mo['closed_at'] ?: '—')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ════════════════ قسم التفتيش الفني — مرئي لكل من يفتح الكرت ════════════════ -->
    <div class="card" id="sec-inspections" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> التفتيش الفني — المؤشرات والتفتيشات</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="kpi"><?php echo intval($ins_total); ?></div><div class="label">إجمالي التفتيشات</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($ins_done); ?></div><div class="label">مكتملة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($ins_open); ?></div><div class="label">مجدولة/مفتوحة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($ins_critical); ?></div><div class="label">ملاحظات حرجة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $ins_last ? htmlspecialchars((string) $ins_last) : '—'; ?></div><div class="label">آخر تفتيش</div></div>
            </div>

            <div class="table-container" style="margin-top:12px;">
                <table class="display" style="width:100%;">
                    <thead><tr><th>المرجع</th><th>النوع</th><th>الفاحص</th><th>التاريخ المجدول</th><th>تاريخ الإكمال</th><th>النتيجة</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php if (empty($ins_rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#888;">لا توجد تفتيشات لهذه المعدة</td></tr>
                        <?php else: foreach ($ins_rows as $ir): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $ir['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) ($ir['inspection_type'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir['inspector_name'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir['scheduled_date'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir['completed_at'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($ir['overall_result'] ?: '—')); ?></td>
                                <td><span class="action-btn"><?php echo htmlspecialchars((string) $ir['state']); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ════════════════ قسم الصيانة الوقائية — مرئي لكل من يفتح الكرت ════════════════ -->
    <div class="card" id="sec-preventive" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-calendar-check"></i> الصيانة الوقائية — الخطط المسندة للمعدة</h5></div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-card"><div class="kpi"><?php echo intval($pln_total); ?></div><div class="label">إجمالي الخطط</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($pln_active); ?></div><div class="label">نشطة</div></div>
                <div class="profile-card"><div class="kpi"><?php echo intval($pln_due); ?></div><div class="label">مستحقة الآن</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $pln_last ? htmlspecialchars((string) $pln_last) : '—'; ?></div><div class="label">آخر تنفيذ</div></div>
                <div class="profile-card"><div class="kpi"><?php echo $pln_next ? htmlspecialchars((string) $pln_next) : '—'; ?></div><div class="label">الاستحقاق القادم</div></div>
            </div>

            <div class="table-container" style="margin-top:12px;">
                <table class="display" style="width:100%;">
                    <thead><tr><th>المرجع</th><th>الخطة</th><th>الأساس</th><th>الفاصل</th><th>آخر تنفيذ</th><th>الاستحقاق القادم</th><th>الحالة</th></tr></thead>
                    <tbody>
                        <?php if (empty($pln_rows)): ?>
                            <tr><td colspan="7" style="text-align:center;color:#888;">لا توجد خطط وقائية لهذه المعدة</td></tr>
                        <?php else: foreach ($pln_rows as $pr):
                            $pr_due = ($pr['trigger_basis'] === 'ساعات')
                                ? (($pr['next_due_meter'] !== null && $pr['next_due_meter'] !== '') ? $pr['next_due_meter'] : '—')
                                : (!empty($pr['next_due_date']) ? $pr['next_due_date'] : '—');
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string) $pr['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars((string) $pr['name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $pr['trigger_basis']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pr['interval_value'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($pr['last_done_date'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string) $pr_due); ?></td>
                                <td><span class="action-btn"><?php echo htmlspecialchars((string) $pr['state']); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div><!-- /#tab-maintenance -->

    <!-- ════════ لوحة: الوثائق والسجل ════════ -->
    <div class="ep-tab-panel" id="tab-records">
    <!-- جداول الأبناء: الوثائق · الحماية · المكوّنات · السجل -->
    <?php if ($critical_expired > 0): ?>
        <div class="success-message is-error" style="margin:12px 0;font-weight:700;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            تحذير حرج: توجد <?= (int) $critical_expired; ?> وثيقة حرجة منتهية الصلاحية لهذه المعدة. (سيُربط لاحقاً بمنع التشغيل/التخصيص)
        </div>
    <?php endif; ?>

    <!-- (1) الوثائق الرسمية -->
    <div class="card" id="sec-docs">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-file-contract"></i> الوثائق الرسمية
                <?php if ($docs_expired): ?><span class="status-inactive" style="margin-inline-start:6px;">منتهية: <?= (int) $docs_expired; ?></span><?php endif; ?>
                <?php if ($docs_soon): ?><span class="badge-busy" style="margin-inline-start:6px;">قاربت: <?= (int) $docs_soon; ?></span><?php endif; ?>
            </h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-docs')"><i class="fas fa-plus"></i> إضافة وثيقة</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-docs" class="child-add-form ems-form" method="post" action="equipment_child_save.php" enctype="multipart/form-data" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الوثيقة *</label><select name="doc_type" required><option value="">-- اختر --</option><?php foreach ($DOC_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الرقم/المرجع</label><input type="text" name="reference"></div>
                    <div><label>تاريخ الإصدار</label><input type="date" name="issue_date"></div>
                    <div><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
                    <div><label>مرفق (صورة/PDF)</label><input type="file" name="attachment" accept="image/*,application/pdf"></div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:22px;"><input type="checkbox" name="is_critical" id="doc_crit" value="1"><label for="doc_crit" style="margin:0;">وثيقة حرجة</label></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>المرجع</th><th>الإصدار</th><th>الانتهاء</th><th>حرجة</th><th>الحالة</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($compliance_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" style="text-align:center;color:#888;">لا توجد وثائق مُسجّلة</td></tr>
                        <?php else: foreach ($compliance_rows as $cr): $st = ems_doc_status($cr['expiry_date'] ?? null, $today_ts, $DOC_ALERT_DAYS); ?>
                            <tr>
                                <td><?= $ee($cr['doc_type']); ?></td>
                                <td><?= $ee($cr['reference'] ?: '—'); ?></td>
                                <td><?= $ee($cr['issue_date'] ?: '—'); ?></td>
                                <td><?= $ee($cr['expiry_date'] ?: '—'); ?></td>
                                <td><?= !empty($cr['is_critical']) ? '<span class="status-inactive">حرجة</span>' : '—'; ?></td>
                                <td><?php echo $st['cls'] ? "<span class='{$st['cls']}'>" . $ee($st['label']) . "</span>" : $ee($st['label']); ?></td>
                                <td><?php if (!empty($cr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($cr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذه الوثيقة؟');" style="margin:0;"><input type="hidden" name="entity" value="compliance"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (2) تجهيزات الحماية -->
    <div class="card" id="sec-protection">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-shield-halved"></i> تجهيزات الحماية</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-prot')"><i class="fas fa-plus"></i> إضافة تجهيز</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-prot" class="child-add-form ems-form" method="post" action="equipment_child_save.php" enctype="multipart/form-data" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الحماية *</label><select name="protection_type" required><option value="">-- اختر --</option><?php foreach ($PROTECTION_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الوصف</label><input type="text" name="description"></div>
                    <div><label>تاريخ التركيب/البدء</label><input type="date" name="start_date"></div>
                    <div><label>التكلفة</label><input type="number" step="0.01" name="cost"></div>
                    <div><label>الحالة</label><select name="state"><option value="">-- اختر --</option><?php foreach ($PROTECTION_STATES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>تاريخ التجديد</label><input type="date" name="renewal_date"></div>
                    <div><label>المنفّذ/المورد</label><input type="text" name="partner_name" autocomplete="off" placeholder="اكتب اسم المنفّذ/المورد (إدخال يدوي)"></div>
                    <div><label>مرتبط بوثيقة (للتأمين)</label><select name="compliance_id"><option value="">-- بدون --</option><?php foreach ($compliance_rows as $cr) echo '<option value="' . (int) $cr['id'] . '">' . $ee($cr['doc_type'] . ($cr['reference'] ? ' — ' . $cr['reference'] : '')) . '</option>'; ?></select></div>
                    <div><label>مرفق</label><input type="file" name="attachment" accept="image/*,application/pdf"></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>الوصف</th><th>البدء</th><th>التكلفة</th><th>الحالة</th><th>التجديد</th><th>المنفّذ</th><th>مرفق</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($protection_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 9 : 8; ?>" style="text-align:center;color:#888;">لا توجد تجهيزات مُسجّلة</td></tr>
                        <?php else: foreach ($protection_rows as $pr): $needs = ($pr['state'] ?? '') === 'يحتاج تجديداً'; ?>
                            <tr>
                                <td><?= $ee($pr['protection_type']); ?></td>
                                <td><?= $ee($pr['description'] ?: '—'); ?></td>
                                <td><?= $ee($pr['start_date'] ?: '—'); ?></td>
                                <td><?= $pr['cost'] !== null && $pr['cost'] !== '' ? $ee($pr['cost']) : '—'; ?></td>
                                <td><?php echo $needs ? '<span class="badge-busy">' . $ee($pr['state']) . '</span>' : $ee($pr['state'] ?: '—'); ?></td>
                                <td><?= $ee($pr['renewal_date'] ?: '—'); ?></td>
                                <td><?= $ee($pr['partner_name'] ?: '—'); ?></td>
                                <td><?php if (!empty($pr['attachment_path'])): ?><a href="fleet_file.php?f=<?= $ee(basename($pr['attachment_path'])); ?>" target="_blank"><i class="fas fa-paperclip"></i> عرض</a><?php else: ?>—<?php endif; ?></td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا التجهيز؟');" style="margin:0;"><input type="hidden" name="entity" value="protection"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $pr['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (3) المكوّنات الكبرى -->
    <div class="card" id="sec-components">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-gears"></i> المكوّنات الكبرى</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-comp')"><i class="fas fa-plus"></i> إضافة مكوّن</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-comp" class="child-add-form ems-form" method="post" action="equipment_child_save.php" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع المكوّن *</label><select name="component_type" required><option value="">-- اختر --</option><?php foreach ($COMPONENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>الرقم التسلسلي</label><input type="text" name="serial_no"></div>
                    <div><label>تاريخ التركيب</label><input type="date" name="install_date"></div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:22px;"><input type="checkbox" name="is_current" id="comp_cur" value="1" checked><label for="comp_cur" style="margin:0;">مُركَّب حالياً</label></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> حفظ</button>
            </form>
            <?php endif; ?>
            <div class="table-container">
                <table class="display" style="width:100%;">
                    <thead><tr><th>النوع</th><th>الرقم التسلسلي</th><th>التركيب</th><th>حالي؟</th><th>الاستبدال</th><th>ساعات المكوّن</th><th>مرّات الاستبدال</th><?php if ($can_edit_card): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (empty($component_rows)): ?>
                            <tr><td colspan="<?= $can_edit_card ? 8 : 7; ?>" style="text-align:center;color:#888;">لا توجد مكوّنات مُسجّلة</td></tr>
                        <?php else: foreach ($component_rows as $cm): ?>
                            <tr>
                                <td><?= $ee($cm['component_type']); ?></td>
                                <td><?= $ee($cm['serial_no'] ?: '—'); ?></td>
                                <td><?= $ee($cm['install_date'] ?: '—'); ?></td>
                                <td><?= !empty($cm['is_current']) ? '<span class="status-active">نعم</span>' : 'لا'; ?></td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <td style="color:#aaa;">لاحقاً</td>
                                <?php if ($can_edit_card): ?><td><form method="post" action="equipment_child_save.php" onsubmit="return confirm('حذف هذا المكوّن؟');" style="margin:0;"><input type="hidden" name="entity" value="component"><input type="hidden" name="action" value="delete"><input type="hidden" name="row_id" value="<?= (int) $cm['id']; ?>"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>"><button class="action-btn delete" title="حذف"><i class="fa-solid fa-trash"></i></button></form></td><?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- (4) سجل تاريخ المعدة (إدراج فقط) -->
    <div class="card" id="sec-history">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h5><i class="fas fa-timeline"></i> سجل تاريخ المعدة</h5>
            <?php if ($can_edit_card): ?><button type="button" class="btn btn-primary btn-sm" onclick="emsToggle('add-hist')"><i class="fas fa-plus"></i> إضافة حدث يدوي</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($can_edit_card): ?>
            <form id="add-hist" class="child-add-form ems-form" method="post" action="equipment_child_save.php" style="display:none;margin-bottom:14px;">
                <input type="hidden" name="entity" value="history"><input type="hidden" name="action" value="add"><input type="hidden" name="equipment_id" value="<?= (int) $equipment_id; ?>">
                <div class="form-grid">
                    <div><label>نوع الحدث *</label><select name="event_type" required><option value="">-- اختر --</option><?php foreach ($EVENT_TYPES as $o) echo '<option>' . $ee($o) . '</option>'; ?></select></div>
                    <div><label>التاريخ والوقت *</label><input type="datetime-local" name="event_date" value="<?= date('Y-m-d\TH:i'); ?>" required></div>
                    <div><label>الموقع</label><input type="text" name="site_id"></div>
                    <div><label>تاريخ دخول/خروج</label><input type="date" name="in_out_date"></div>
                    <div style="grid-column:1/-1;"><label>ملاحظة</label><input type="text" name="note"></div>
                </div>
                <button type="submit" class="btn btn-success btn-sm" style="margin-top:10px;"><i class="fa-solid fa-save"></i> تسجيل</button>
            </form>
            <?php endif; ?>
            <?php if (empty($history_rows)): ?>
                <div style="text-align:center;color:#888;padding:14px;">لا توجد أحداث مُسجّلة</div>
            <?php else: ?>
                <ul class="ems-timeline">
                    <?php foreach ($history_rows as $h): ?>
                        <li>
                            <span class="ems-tl-dot"></span>
                            <div class="ems-tl-body">
                                <div><strong><?= $ee($h['event_type']); ?></strong>
                                    <span style="color:#888;font-size:12px;margin-inline-start:8px;"><?= $ee($h['event_date']); ?></span></div>
                                <div style="font-size:13px;color:#555;">
                                    <?php
                                    $bits = [];
                                    if (!empty($h['project_name'])) $bits[] = 'المشروع: ' . $ee($h['project_name']);
                                    if (!empty($h['site_id'])) $bits[] = 'الموقع: ' . $ee($h['site_id']);
                                    if (!empty($h['in_out_date'])) $bits[] = 'دخول/خروج: ' . $ee($h['in_out_date']);
                                    if (!empty($h['note'])) $bits[] = $ee($h['note']);
                                    echo implode(' · ', $bits) ?: '—';
                                    ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    </div><!-- /#tab-records -->
    </div><!-- /.ep-panels -->

</div>

<style>
    .ems-timeline { list-style:none; margin:0; padding:0; position:relative; }
    .ems-timeline:before { content:''; position:absolute; right:7px; top:4px; bottom:4px; width:2px; background:#e3e3e3; }
    .ems-timeline li { position:relative; padding:0 26px 16px 0; }
    .ems-tl-dot { position:absolute; right:1px; top:4px; width:14px; height:14px; border-radius:50%; background:#F3BE00; border:2px solid #fff; box-shadow:0 0 0 1px #e3e3e3; }
    .child-add-form { background:#fafafa; border:1px solid #ececec; border-radius:8px; padding:12px; }
</style>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
function emsToggle(id){ var el=document.getElementById(id); if(el){ el.style.display = (el.style.display==='none'||!el.style.display) ? 'block' : 'none'; } }
$(function () {
    $('#equipmentProjectsTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
    $('#equipmentDriversTable').DataTable({ language: { url: '/ems/assets/i18n/datatables/ar.json' } });
});

// ════════ تبديل التبويبات (hash-linkable) + ضبط أعمدة DataTables عند الإظهار ════════
$(function () {
    var page = document.querySelector('.equipment-profile-page');
    if (!page) return;
    var tabs   = page.querySelectorAll('.ep-tab');
    var panels = page.querySelectorAll('.ep-tab-panel');
    function activate(name) {
        var found = false;
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-ep-tab') === name); });
        panels.forEach(function (p) { var on = (p.id === 'tab-' + name); p.classList.toggle('is-active', on); if (on) found = true; });
        if (!found) return;
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
        if (window.jQuery && $.fn.dataTable) {
            setTimeout(function () { try { $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); } catch (e) {} }, 40);
        }
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.getAttribute('data-ep-tab')); }); });
    var h = (location.hash || '').replace('#', '');
    if (h && page.querySelector('#tab-' + h)) activate(h);
});
</script>
