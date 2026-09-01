<?php
/**
 * hours_approval.php
 * صفحة اعتماد ساعات العمل - نظام هرمي للمدراء الرئيسيين
 *
 * مستويات الاعتماد:
 *  Level 1 → مدير المشاريع (role 1)
 *  Level 2 → مدير الموردين (role 2)
 *  Level 3 → مدير الأسطول (role 3)
 *  Level 4 → مدير المشغلين (role 4) ← الاعتماد النهائي
 */

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';

$role           = strval($_SESSION['user']['role']);
$user_id        = intval($_SESSION['user']['id']);
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);

// حارس الشاشة (M-14 BR-GOV-01): كانت شاشةَ اعتمادٍ حيةً بلا فحص can_view —
// كشفها مسحُ فصل الواجبات 2026-08-06. (المعالجُ محميٌّ سلفًا بقائمة ADR-07.)
require_once '../includes/permissions_helper.php';
$__pp = check_page_permissions($conn, 'Approvals/hours_approval.php');
if ($role !== '-1' && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($role), 'Approvals/hours_approval.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}
$session_proj   = intval($_SESSION['user']['project_id'] ?? 0);

$equip_type_filter = intval($_GET['equip_type'] ?? 0);
if (!in_array($equip_type_filter, [0, 1, 2, 3], true)) {
  $equip_type_filter = 0;
}
$equip_type_where = ($equip_type_filter > 0) ? " AND e.type = $equip_type_filter" : '';
$equip_type_label = ($equip_type_filter === 1) ? 'حفارات' : (($equip_type_filter === 2) ? 'قلابات' : (($equip_type_filter === 3) ? 'خرامات' : 'الكل'));

// role 5 = مدير الموقع (عرض فقط مقيّد بمشروعه ومنجمه)
$is_site_manager = ($role === '5');

$allowed_roles = EMS_ROLES_HOURS_APPROVAL_ACCESS; // فهرس ثوابت الأدوار (ADR-07)
if (!in_array($role, $allowed_roles)) {
    header('Location: ../main/dashboard.php');
    exit();
}

// (أُسقط إنشاء الجداول التلقائي — الجدولان قائمان ومسجَّلان في سجل البوابة T_TENANT)

// ─── خريطة الأدوار ────────────────────────────────────────────
// النظام الهرمي الرباعي مع الألوان والأيقونات المحددة في المواصفات
// **مشتقةٌ من `includes/roles.php`** — السلسلةُ خمسُ أيدٍ وآخرُها المبيعات
// (قرارُ المالك 2026-08-12 · `SPEC_TIMESHEET_CYCLE §TS-13`).
$role_level_map  = ems_hours_role_level_map();
$level_role_name = [
    1 => ['label' => 'مدير المشاريع',   'color' => 'var(--c-0b1e3f, #0B1E3F)', 'icon' => 'fa-user-tie'],
    2 => ['label' => 'مدير الموردين',   'color' => 'var(--c-a8541c)', 'icon' => 'fa-truck-medical'],
    3 => ['label' => 'مدير الأسطول',    'color' => 'var(--c-ink-600)', 'icon' => 'fa-truck'],
    4 => ['label' => 'مدير المشغلين',   'color' => 'var(--c-5b7f1e)', 'icon' => 'fa-shield-halved'],
    5 => ['label' => 'مدير المبيعات',   'color' => 'var(--c-7c2d12)', 'icon' => 'fa-file-signature'],
];
$FINAL_LEVEL = EMS_HOURS_APPROVAL_FINAL_LEVEL;

$my_level   = $role_level_map[$role] ?? 0;
$is_admin   = ($role === '-1');
$prev_level = $my_level - 1;

// ─── نطاق الشركة: عبر بوابة المستأجر ({TENANT_SCOPE}) — فرع OR IS NULL القديم مكافئ
// حرفيًا (صفر صفوف بلا شركة، مثبت من القاعدة)؛ والأدمن عبر forAllTenants المسجَّل.
$ha_gate = $is_admin ? ems_tenant_db()->forAllTenants('hours approval admin') : ems_tenant_db();

// ─── نطاق مدير الموقع (مشروع + منجم محدد) ────────────────────
$site_scope_ts = '';
if ($is_site_manager) {
    if ($session_proj > 0) {
        $site_scope_ts .= " AND o.project_id = $session_proj";
    }
}

// بناء شرط "قيد الاعتماد" بناءً على الدور
if ($is_site_manager) {
    // مدير الموقع يرى جميع سجلاته بغض النظر عن حالة الاعتماد
    $pending_condition = "1=1";
} elseif ($is_admin) {
    // الأدمن يرى كل ما لم يُعتمد نهائياً (آخرُ مستوًى في السلسلة)
    $pending_condition = "NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = {$FINAL_LEVEL} AND ta2.status = 1
    )";
} elseif ($my_level === 1) {
    // مدير المشاريع يرى كل ما لم يعتمده بعد
    $pending_condition = "NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = 1 AND ta2.status = 1
    )";
} else {
    // المدراء 2-4 يرون ما اعتمده المستوى قبلهم ولم يعتمدوه هم
    $pending_condition = "EXISTS (
        SELECT 1 FROM timesheet_approvals ta2
        WHERE ta2.timesheet_id = t.id AND ta2.approval_level = $prev_level AND ta2.status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM timesheet_approvals ta3
        WHERE ta3.timesheet_id = t.id AND ta3.approval_level = $my_level AND ta3.status = 1
    )";
}

// العزل (t) عبر {TENANT_SCOPE}؛ بقية LEFT JOIN والاستعلامات الفرعية إثراءٌ بلا تنطيق (سلوك الأصل)
$ha_decl = array(
    'scope'  => array('t' => 'timesheet'),
    'enrich' => array('o' => 'operations', 'e' => 'equipments', 's' => 'suppliers', 'p' => 'project',
                      'd' => 'employees', 'u' => 'users', 'n' => 'timesheet_approval_notes', 'ta_l' => 'timesheet_approvals'),
);
$pending_sql = "
    SELECT t.*,
           d.name  AS driver_name,
           e.code  AS equip_code,
           e.name  AS equip_name,
           s.name  AS supplier_name,
           p.name  AS project_name,
           u.name  AS entry_user_name,
           (SELECT COUNT(*) FROM timesheet_approval_notes n
            WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count,
           (SELECT MAX(ta_l.approval_level) FROM timesheet_approvals ta_l
            WHERE ta_l.timesheet_id = t.id AND ta_l.status = 1) AS max_approved_level
    FROM timesheet t
    LEFT JOIN operations    o ON o.id      = t.operator
    LEFT JOIN equipments    e ON e.id      = o.equipment
    LEFT JOIN suppliers     s ON s.id      = e.suppliers
    LEFT JOIN project       p ON p.id      = o.project_id
    LEFT JOIN employees       d ON d.id      = t.employee_id
    LEFT JOIN users         u ON u.id      = t.user_id
    WHERE t.status = 1
      AND $pending_condition
      $site_scope_ts
      $equip_type_where
      AND {TENANT_SCOPE}
    ORDER BY t.date DESC, t.id DESC
    LIMIT 500
";

$pending_rows   = [];
try {
    $pending_rows = $ha_gate->scopedQuery($ha_decl, $pending_sql);
} catch (\Throwable $t) { error_log('hours_approval pending: ' . $t->getMessage()); }

// ─── السجلات المعتمدة نهائياً (آخر 100) ────────────────────
$approved_sql = "
    SELECT t.*,
           d.name  AS driver_name,
           e.code  AS equip_code,
           e.name  AS equip_name,
           s.name  AS supplier_name,
           p.name  AS project_name,
           u.name  AS entry_user_name,
           ta_final.approved_by_name AS final_approver,
           ta_final.approved_at      AS final_approved_at,
           (SELECT COUNT(*) FROM timesheet_approval_notes n
            WHERE n.timesheet_id = t.id AND n.status = 1) AS notes_count
    FROM timesheet t
    INNER JOIN timesheet_approvals ta_final
           ON ta_final.timesheet_id = t.id
           AND ta_final.approval_level = {$FINAL_LEVEL}
           AND ta_final.status = 1
    LEFT JOIN operations    o ON o.id      = t.operator
    LEFT JOIN equipments    e ON e.id      = o.equipment
    LEFT JOIN suppliers     s ON s.id      = e.suppliers
    LEFT JOIN project       p ON p.id      = o.project_id
    LEFT JOIN employees       d ON d.id      = t.employee_id
    LEFT JOIN users         u ON u.id      = t.user_id
    WHERE t.status = 1
      $site_scope_ts
      $equip_type_where
      AND {TENANT_SCOPE}
    ORDER BY ta_final.approved_at DESC
    LIMIT 100
";

$approved_rows   = [];
try {
    $approved_rows = $ha_gate->scopedQuery(array(
        'scope'  => array('t' => 'timesheet', 'ta_final' => 'timesheet_approvals'),
        'enrich' => array('o' => 'operations', 'e' => 'equipments', 's' => 'suppliers', 'p' => 'project',
                          'd' => 'employees', 'u' => 'users', 'n' => 'timesheet_approval_notes'),
    ), $approved_sql);
} catch (\Throwable $t) { error_log('hours_approval approved: ' . $t->getMessage()); }

// جلب أعداد الأعطال من جدول الأعطال لجميع السجلات دفعة واحدة
$fault_counts_map = [];
$_all_ts_for_faults = array_merge(
    array_column($pending_rows, 'id'),
    array_column($approved_rows, 'id')
);
$_all_ts_for_faults = array_values(array_unique(array_filter(array_map('intval', $_all_ts_for_faults))));
if (!empty($_all_ts_for_faults)) {
    $_fc_ids_ha = implode(',', $_all_ts_for_faults);
    try {
        $_fc_rows_ha = $ha_gate->scopedQuery(array('scope' => array('f' => 'timesheet_failure_hours')),
            "SELECT timesheet_id, COUNT(*) AS cnt FROM timesheet_failure_hours f WHERE f.timesheet_id IN ($_fc_ids_ha) AND f.status = 1 AND {TENANT_SCOPE} GROUP BY timesheet_id");
        foreach ($_fc_rows_ha as $_fc_ha) {
            $fault_counts_map[intval($_fc_ha['timesheet_id'])] = intval($_fc_ha['cnt']);
        }
    } catch (\Throwable $t) { error_log('hours_approval fault counts: ' . $t->getMessage()); }
}

// ─── جلب تفاصيل كل مستويات الاعتماد للسجلات المعتمدة نهائياً ───
// نجلب دفعة واحدة لكل السجلات لتفادي N+1 queries
$approved_ids = array_column($approved_rows, 'id');
$all_approval_details = []; // [timesheet_id][level] = row
if (!empty($approved_ids)) {
    $ids_in = implode(',', array_map('intval', $approved_ids));
    try {
        $adv_rows = $ha_gate->scopedQuery(array('scope' => array('ta' => 'timesheet_approvals')),
            "SELECT timesheet_id, approval_level, approved_by_name, approved_at
         FROM timesheet_approvals ta
         WHERE ta.timesheet_id IN ($ids_in) AND ta.status = 1 AND {TENANT_SCOPE}
         ORDER BY timesheet_id, approval_level ASC");
        foreach ($adv_rows as $adv) {
            $all_approval_details[intval($adv['timesheet_id'])][intval($adv['approval_level'])] = $adv;
        }
    } catch (\Throwable $t) { error_log('hours_approval details bulk: ' . $t->getMessage()); }
}

// ═══ UX-03 §5.2 — بياناتُ صندوق الاعتماد (خلف EMS_APPROVAL_BOX) ═══════════
// لكل صفٍّ معلّق: ملخّصُ زمنه (فعلي/استعداد/توقف من سطور السجل القانوني)
// وشارةُ تجاوز الطاقة بأعلامها المفتوحة — جلبةٌ واحدةٌ لا N+1.
require_once __DIR__ . '/../app/Services/Unit/TimesheetEntryService.php';
$box_on = function_exists('ems_env') && ems_env('EMS_APPROVAL_BOX', 'off') === 'on';
$box_map = array();   // ts_id => {entry_id, state, flagged, time: {actual, standby, stop}, flags: []}
if ($box_on && !empty($pending_rows)) {
    try {
        $bx_ids = array_values(array_filter(array_map('intval', array_column($pending_rows, 'id'))));
        if (!empty($bx_ids)) {
            $bx_uuids = "'ts:" . implode("','ts:", $bx_ids) . "'";
            // الواقعة وملخّص زمنها
            $bx_rows = $ha_gate->scopedQuery(
                array('scope' => array('u' => 'unit_entries'), 'enrich' => array('l' => 'unit_time_log')),
                "SELECT u.id AS entry_id, u.sync_uuid, u.state, u.capacity_flag,
                        ROUND(COALESCE(SUM(CASE WHEN l.ops_state = 'actual_work' THEN l.hours END), 0), 2) AS t_actual,
                        ROUND(COALESCE(SUM(CASE WHEN l.ops_state = 'standby' THEN l.hours END), 0), 2) AS t_standby,
                        ROUND(COALESCE(SUM(CASE WHEN l.ops_state NOT IN ('actual_work','standby') THEN l.hours END), 0), 2) AS t_stop
                   FROM unit_entries u
                   LEFT JOIN unit_time_log l ON l.entry_id = u.id
                  WHERE u.sync_uuid IN ({$bx_uuids}) AND {TENANT_SCOPE}
                  GROUP BY u.id, u.sync_uuid, u.state, u.capacity_flag");
            $bx_entry_ids = array();
            foreach ($bx_rows as $bx) {
                $bts = (int) substr($bx['sync_uuid'], 3);
                $box_map[$bts] = array(
                    'entry_id' => (int) $bx['entry_id'], 'state' => $bx['state'],
                    'flagged' => (int) $bx['capacity_flag'] === 1,
                    'time' => array('actual' => (float) $bx['t_actual'],
                                    'standby' => (float) $bx['t_standby'],
                                    'stop' => (float) $bx['t_stop']),
                    'flags' => array(),
                );
                $bx_entry_ids[(int) $bx['entry_id']] = $bts;
            }
            // الأعلام المفتوحة بتفصيلها (للتلميح ومودال التخليص)
            if (!empty($bx_entry_ids)) {
                $bx_in = implode(',', array_keys($bx_entry_ids));
                $bx_flags = $ha_gate->scopedQuery(
                    array('scope' => array('f' => 'unit_capacity_flags')),
                    "SELECT f.entry_id, f.subject, f.subject_ref, f.measured_hours, f.capacity_hours
                       FROM unit_capacity_flags f
                      WHERE f.entry_id IN ({$bx_in}) AND f.cleared_at IS NULL AND {TENANT_SCOPE}");
                foreach ($bx_flags as $bf) {
                    $bts = $bx_entry_ids[(int) $bf['entry_id']] ?? 0;
                    if ($bts && isset($box_map[$bts])) {
                        $box_map[$bts]['flags'][] = ($bf['subject'] === 'equipment' ? 'المعدة' : 'المشغل')
                            . ' #' . $bf['subject_ref'] . ': ' . $bf['measured_hours'] . '/' . $bf['capacity_hours'] . ' س';
                    }
                }
            }
        }
    } catch (\Throwable $t) { error_log('approval box map: ' . $t->getMessage()); }
}

// ─── بيانات الفلاتر (قوائم فريدة) ───────────────────────────
$_all_rows = $pending_rows;
$filter_projects = array_values(array_unique(array_filter(array_column($_all_rows, 'project_name'))));
$filter_suppliers = array_values(array_unique(array_filter(array_column($_all_rows, 'supplier_name'))));
$filter_drivers  = array_values(array_unique(array_filter(array_column($_all_rows, 'driver_name'))));
$filter_equips   = [];
foreach ($_all_rows as $_r) {
    $_en = trim(($_r['equip_code'] ?? '') . ' ' . ($_r['equip_name'] ?? ''));
    if ($_en) $filter_equips[] = $_en;
}
$filter_equips = array_values(array_unique($filter_equips));
sort($filter_projects); sort($filter_suppliers); sort($filter_drivers); sort($filter_equips);

// ─── إحصاءات سريعة ──────────────────────────────────────────
$stats = [
    'pending'  => count($pending_rows),
    'approved' => count($approved_rows),
    'my_level' => $my_level,
    'my_label' => $is_admin ? 'الأدمن' : ($level_role_name[$my_level]['label'] ?? 'مدير'),
];

// قائمة الأعمدة المتاحة للتعليق
$column_labels = [
    'date'              => 'التاريخ',
    'shift'             => 'الوردية',
    'shift_hours'       => 'ساعات الوردية',
    'executed_hours'    => 'الساعات المنفذة',
    'total_work_hours'  => 'إجمالي ساعات العمل',
    'total_fault_hours' => 'إجمالي الأعطال',
    'hr_fault'          => 'عطل بشري',
    'maintenance_fault' => 'عطل صيانة',
    'marketing_fault'   => 'عطل تسويق',
    'approval_fault'    => 'عطل اعتماد',
    'other_fault_hours' => 'أعطال أخرى',
    'standby_hours'     => 'ساعات الانتظار',
    'dependence_hours'  => 'ساعات الاعتماد',
    'extra_hours'       => 'ساعات إضافية',
    'operator_hours'    => 'ساعات المشغل',
    'counter_diff'      => 'فرق العداد',
    'fault_type'        => 'نوع العطل',
    'fault_department'  => 'القسم المسؤول',
    'work_notes'        => 'ملاحظات العمل',
    'fault_notes'       => 'ملاحظات الأعطال',
    'general_notes'     => 'ملاحظات عامة',
];

// الأعمدة تُتحكم بها عبر أزرار إظهار/إخفاء المجموعات في الصفحة

$page_title = 'اعتماد الوحدات التشغيلية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include('../inheader.php');
?>
<!-- ============================================================
     CSS الصفحة
============================================================ -->
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<style>
/* تفعيل التمرير الأفقي بنفس نمط صفحة المتابعة */
.hours-approval-main .table-wrap {
  overflow-x: auto !important;
  -webkit-overflow-scrolling: touch;
  width: 100%;
}
.hours-approval-main .table-wrap > .dataTables_wrapper {
  overflow: visible;
}
.hours-approval-main .table-wrap .dataTables_scroll,
.hours-approval-main .table-wrap .dataTables_scrollHead,
.hours-approval-main .table-wrap .dataTables_scrollBody {
  overflow-x: auto !important;
}
.hours-approval-main .ha-table {
  width: max-content !important;
  min-width: 100%;
}

/* UXW-01 ②·①: أنماطُ الشاشةِ منقولةً من السماتِ الموضعيةِ إلى أصنافٍ برموزِ الألوان */
.hours-approval-main .ha-mb16 { margin-bottom: 16px; }
.hours-approval-main .ha-mb0  { margin-bottom: 0; }
.hours-approval-main .ha-steps-wrap { border-radius: 8px; overflow: hidden; }
.hours-approval-main .ha-stat-1 { border-color: var(--c-a8541c); }
.hours-approval-main .ha-stat-1 .stat-icon { background: var(--c-a8541c); }
.hours-approval-main .ha-stat-2 { border-color: var(--c-5b7f1e); }
.hours-approval-main .ha-stat-2 .stat-icon { background: var(--c-5b7f1e); }
.hours-approval-main .ha-stat-3 { border-color: var(--c-0b1e3f, #0B1E3F); }
.hours-approval-main .ha-stat-3 .stat-icon { background: var(--c-0b1e3f, #0B1E3F); }
.hours-approval-main .ha-stat-4 { border-color: var(--c-ink-600); }
.hours-approval-main .ha-stat-4 .stat-icon { background: var(--c-ink-600); }
.hours-approval-main .ha-toolbar-card {
  background: var(--c-f8f9fa);
  border-right: 4px solid var(--c-0b1e3f, #0B1E3F);
  padding-bottom: 0;
}
.hours-approval-main .ha-lbl-140 { min-width: 140px; }
.hours-approval-main .ha-sel-200 { max-width: 200px; }
.hours-approval-main .ha-fs-82 { font-size: .82rem; }
.hours-approval-main .ha-fs-75 { font-size: .75rem; }
.hours-approval-main .ha-fs-68 { font-size: .68rem; }
.hours-approval-main .ha-fb-100 { flex-basis: 100%; }
.hours-approval-main .ha-ico-pending   { background: var(--c-fd7e14, #fd7e14); }
.hours-approval-main .ha-badge-pending { background: var(--c-fd7e14, #fd7e14); }
.hours-approval-main .ha-ico-approved   { background: var(--c-5b7f1e); }
.hours-approval-main .ha-badge-approved { background: var(--c-5b7f1e); }
.hours-approval-main .ha-w100 { width: 100%; }
.hours-approval-main .ha-w36  { width: 36px; }
.hours-approval-main .ha-w44  { width: 44px; }
.hours-approval-main .ha-nw   { white-space: nowrap; }
.hours-approval-main .ha-tc   { text-align: center; }
.hours-approval-main .ha-mw120 { max-width: 120px; }
.hours-approval-main .ha-mw100 { max-width: 100px; }
.hours-approval-main .ha-shift {
  padding: 2px 8px; border-radius: 12px; font-size: .75rem; font-weight: 600;
}
.hours-approval-main .ha-shift-day   { background: var(--c-fff3cd); color: var(--c-856404, #856404); }
.hours-approval-main .ha-shift-night { background: var(--c-d1ecf1, #d1ecf1); color: var(--c-0c5460, #0c5460); }
.hours-approval-main .ha-t-actual  { color: var(--c-state-ok);   font-weight: 700; }
.hours-approval-main .ha-t-standby { color: var(--c-state-info); font-weight: 700; }
.hours-approval-main .ha-t-stop    { color: var(--c-ink-500);    font-weight: 700; }
.hours-approval-main .ha-t-sep     { color: var(--c-9ca3af); }
.hours-approval-main .ha-flag-badge {
  background: var(--c-f0b429, #f0b429); color: var(--c-7c2d12); cursor: pointer;
}
.hours-approval-main .ha-ico-danger { color: var(--c-dc3545); font-size: .85rem; }
.hours-approval-main .ha-ico-ok     { color: var(--c-059669); font-size: .9rem; }
.hours-approval-main .ha-note-active { color: var(--c-ffaa33, #ffaa33); }
.hours-approval-main .ha-ico-xs  { font-size: .5rem; }
.hours-approval-main .ha-ico-xxs { font-size: .38rem; }
.hours-approval-main .ha-ico-mr3 { margin-right: 3px; }
.hours-approval-main .ha-btn-return {
  background: var(--c-fef3c7);
  border: 1px solid var(--c-f0b429, #f0b429);
  color: var(--c-92400e);
  border-radius: 6px;
  padding: 2px 8px;
}
.hours-approval-main .ha-meta-date { font-size: .78rem; color: var(--c-6c757d); }

/* المودالاتُ والتنبيهاتُ خارجَ حاويةِ .hours-approval-main فلا تُقيَّد بها */
.ha-mh-danger { border-bottom: 2px solid var(--c-f8d7da, #f8d7da); }
.ha-mh-warn   { background: var(--c-fef3c7); }
.ha-mh-info   { background: var(--c-e0e7ff, #e0e7ff); }
.ha-ico-warn  { color: var(--c-b45309); }
.ha-note-p    { font-size: .85rem; color: var(--c-ink-500); }
.ha-fs-85     { font-size: .85rem; }
.ha-hidden    { display: none; }
.ha-toast-wrap { z-index: 9999; }
.ha-reject-reason { border-color: var(--c-dee2e6); }
.ha-reject-reason.ha-invalid { border-color: var(--c-dc3545); }
</style>

<!-- ============================================================
     HTML
============================================================ -->

<?php
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main hours-approval-main">
<div class="page-wrapper">

  <?php
  // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
  $header_title   = 'اعتماد الوحدات التشغيلية';
  $header_icon    = 'fa fa-check-double';
  $header_actions = array(
      array('href' => ($is_admin ? '../admin/dashboard.php' : '../main/dashboard.php'), 'class' => 'btn btn-secondary btn-sm fw-semibold', 'icon' => 'fa fa-home me-1', 'label' => 'لوحة التحكم'),
      array('href' => 'hours_approval_followup.php', 'class' => 'btn btn-primary btn-sm fw-semibold', 'icon' => 'fa fa-route me-1', 'label' => 'متابعة الاعتمادات المنقولة'),
      array('raw' => '<span class="badge bg-light text-dark border">فلتر نوع المعدة: ' . htmlspecialchars($equip_type_label) . '</span>'),
  );
  $header_back = array();
  include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_ops_hours_approval
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الدفعة' => 'g64',
            'يوم الاعتماد' => 'g65',
            'نطاق الدفعة' => 'g66',
            'عدد السجلات' => 'g67',
            'سجلات بتجاوز طاقة' => 'g68',
            'سجلات ناقصة السبب' => 'g69',
            'مرحلة الاعتماد' => 'g70',
            'معتمد الموقع' => 'g71',
            'معتمد الأطراف' => 'g72',
            'معتمد العقود' => 'g73',
            'نتيجة المطابقة' => 'g74',
            'سجلات مستثناة' => 'g75',
            'سبب الاستثناء' => 'g76',
            'قرار الدفعة' => 'g77',
            'المنشئ' => 'g78',
            'تاريخ الإنشاء' => 'g79',
            'المراجع' => 'g80',
            'المعتمد' => 'g81',
            'تاريخ الاعتماد' => 'g82',
            'حالة البيانات' => 'g83',
            'مرجع المصدر' => 'g84',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('ops_hours_approval');
        echo ems_w14_grid('emsList_ops_hours_approval', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في اعتماد الوحدات'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
  // TKT-15 · زر الإبلاغ السياقي — الاعتمادات (§2-⑥)
  require_once __DIR__ . '/../includes/report_button.php';
  ems_report_button(array('screen' => 'approvals'));
  // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
  echo ems_states_bundle('لا سجل ساعات في نطاق اعتمادك الآن', 'وسع فلتر نوع المعدة، أو انتظر اعتماد المستوى السابق في السلسلة');
  ?>

  <?php /* E-08-أ: موضعُ الأسباب المفصَّلة للصفوف الموقوفة — يملؤه renderBlocked()
           من `blocked[].reasons`. يبقى فارغًا حتى يقع حجبٌ فعليّ. */ ?>
  <div id="blocked-panel" class="alert alert-warning d-none ha-mb16" role="alert">
    <div class="d-flex justify-content-between align-items-start">
      <h6 class="fw-bold mb-2"><i class="fa fa-ban me-1"></i> صفوف لم تعتمد — والسبب لكل منها:</h6>
      <button type="button" class="btn-close" aria-label="إغلاق"
              onclick="document.getElementById('blocked-panel').classList.add('d-none');"></button>
    </div>
    <ul id="blocked-list" class="mb-2 ps-3"></ul>
    <button type="button" class="btn btn-sm btn-secondary"
            onclick="location.reload();"><i class="fa fa-rotate me-1"></i> حدث الجدول</button>
  </div>

  <div class="mb-4">
    <small class="text-muted">
      <?php
        if ($is_admin) echo 'عرض كامل - الأدمن';
        elseif ($is_site_manager) echo 'مدير الموقع — عرض فقط' . ($session_proj > 0 ? ' | مشروع #'.$session_proj : '');
        else echo 'مستوى الاعتماد ' . $my_level . ' — ' . ($level_role_name[$my_level]['label'] ?? '');
      ?>
    </small>
  </div>

  <!-- ── شريط التقدم الهرمي ── -->
  <div class="approval-steps mb-4 ha-steps-wrap">
    <?php foreach ($level_role_name as $lvl => $info):
      $cls = '';
      if (!$is_admin) {
          if ($lvl < $my_level)  $cls = 'done';
          elseif ($lvl == $my_level) $cls = 'active';
      } else {
          $cls = 'done';
      }
    ?>
    <div class="approval-step <?= $cls ?>" data-allow-style
         style="background:<?= $info['color'] ?>;">
      <div>
        <div class="step-num mx-auto"><?= $lvl ?></div>
        <div><?= $info['label'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── بطاقات الإحصاء ── -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card ha-stat-1">
        <div class="stat-icon"><i class="fa fa-hourglass-half"></i></div>
        <div>
          <div class="stat-val"><?= count($pending_rows) ?></div>
          <div class="stat-label">قيد الاعتماد</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card ha-stat-2">
        <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
        <div>
          <div class="stat-val"><?= count($approved_rows) ?></div>
          <div class="stat-label">معتمد نهائيا (آخر 100)</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card ha-stat-3">
        <div class="stat-icon"><i class="fa fa-layer-group"></i></div>
        <div>
          <div class="stat-val"><?= $is_admin ? $FINAL_LEVEL : $my_level ?></div>
          <div class="stat-label">مستوى الاعتماد الحالي</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="stat-card ha-stat-4">
        <div class="stat-icon"><i class="fa fa-comment-dots"></i></div>
        <div>
          <div class="stat-val" id="total-notes-count">—</div>
          <div class="stat-label">إجمالي الملاحظات</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── شريط الأدوات والفلاتر المدمجة ── -->
  <div class="table-card ha-toolbar-card">
    <!-- التصفية حسب نوع المعدة -->
    <div class="toolbar-row">
      <label for="equip_type" class="fw-semibold mb-0 ha-lbl-140">
        <i class="fa fa-filter me-1"></i>نوع المعدة:
      </label>
      <select name="equip_type" id="equip_type" class="form-select form-select-sm ha-sel-200">
        <option value="0" <?= $equip_type_filter === 0 ? 'selected' : '' ?>>الكل</option>
        <option value="1" <?= $equip_type_filter === 1 ? 'selected' : '' ?>>حفارات</option>
        <option value="2" <?= $equip_type_filter === 2 ? 'selected' : '' ?>>قلابات</option>
        <option value="3" <?= $equip_type_filter === 3 ? 'selected' : '' ?>>خرامات</option>
      </select>
      <button type="button" class="btn btn-sm btn-primary fw-semibold" onclick="applyEquipTypeFilter()">
        <i class="fa fa-filter me-1"></i> تطبيق
      </button>
      <?php if ($equip_type_filter !== 0): ?>
      <a href="hours_approval.php" class="btn btn-sm btn-secondary">
        <i class="fa fa-rotate-left me-1"></i> إلغاء الفلتر
      </a>
      <?php endif; ?>
      <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars($equip_type_label) ?></span>
    </div>

    <!-- شريط إظهار/إخفاء مجموعات الأعمدة -->
    <div class="toolbar-row ha-mb0">
      <span class="fw-semibold text-muted me-2 ha-fs-82">
        <i class="fa fa-table-columns me-1"></i> الأعمدة الإضافية:
      </span>
      <button type="button" class="ems-btn-group-toggle" data-group="hours">
        <i class="fa fa-clock"></i> ساعات تفصيلية
      </button>
      <button type="button" class="ems-btn-group-toggle" data-group="faults">
        <i class="fa fa-tools"></i> الأعطال
      </button>
      <button type="button" class="ems-btn-group-toggle" data-group="notes">
        <i class="fa fa-sticky-note"></i> ملاحظات
      </button>
      <button type="button" class="ems-btn-group-toggle-all">
        <i class="fa fa-eye"></i> الكل
      </button>
      <small class="text-muted ms-auto ha-fs-75">
        <i class="fa fa-info-circle me-1"></i> المجموع = الساعات المنفذة + الانتظار
      </small>
    </div>
  </div>

  <!-- ── شريط الفلاتر المتقدمة ── -->
  <div class="filter" id="main-filters-bar">
    <div class="filter-title">
      <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
      فلاتر البحث المتقدمة
    </div>
    <div class="filter-body">
      <div class="filter-field">
        <label for="filter-project"><i class="fa fa-project-diagram me-1"></i>المشروع</label>
        <select id="filter-project" class="form-control">
          <option value="">— كل المشاريع —</option>
          <?php foreach ($filter_projects as $fp_val): ?>
          <option value="<?= htmlspecialchars($fp_val) ?>"><?= htmlspecialchars($fp_val) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field">
        <label for="filter-supplier"><i class="fa fa-truck me-1"></i>المورد</label>
        <select id="filter-supplier" class="form-control">
          <option value="">— كل الموردين —</option>
          <?php foreach ($filter_suppliers as $fs_val): ?>
          <option value="<?= htmlspecialchars($fs_val) ?>"><?= htmlspecialchars($fs_val) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field">
        <label for="filter-driver"><i class="fa fa-user-hard-hat me-1"></i>المشغل</label>
        <select id="filter-driver" class="form-control">
          <option value="">— كل المشغلين —</option>
          <?php foreach ($filter_drivers as $fd_val): ?>
          <option value="<?= htmlspecialchars($fd_val) ?>"><?= htmlspecialchars($fd_val) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field">
        <label for="filter-equip"><i class="fa fa-cogs me-1"></i>الآلية</label>
        <select id="filter-equip" class="form-control">
          <option value="">— كل الآليات —</option>
          <?php foreach ($filter_equips as $fe_val): ?>
          <option value="<?= htmlspecialchars($fe_val) ?>"><?= htmlspecialchars($fe_val) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-actions">
        <button type="button" class="btn-primary" onclick="applyFilters()"><i class="fa fa-search"></i> تطبيق</button>
        <button type="button" class="btn-secondary" title="إعادة تعيين" onclick="resetFilters()"><i class="fa fa-rotate-left"></i></button>
      </div>

      <span class="active-filters-info ha-fb-100" id="active-filters-info">
        <i class="fa fa-check-circle me-1"></i>
        <span id="active-filters-text"></span>
      </span>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       جدول ١: التايمشيت قيد الاعتماد
  ══════════════════════════════════════════════════════════ -->
  <div class="table-card">
    <div class="card-header-custom">
      <div class="ch-icon ha-ico-pending"><i class="fa fa-hourglass-half"></i></div>
      <div>
        <h5>سجلات التايمشيت — قيد الاعتماد</h5>
        <small class="text-muted">
          <?php if ($is_admin): ?>
            الأدمن يرى جميع السجلات غير المعتمدة نهائيا
          <?php elseif ($my_level === 1): ?>
            السجلات التي لم تحظ بعد باعتمادك (المستوى 1)
          <?php else: ?>
            السجلات المعتمدة من المستوى <?= $prev_level ?> وبانتظار اعتمادك (المستوى <?= $my_level ?>)
          <?php endif; ?>
        </small>
      </div>
      <span class="badge-count badge ha-badge-pending"><?= count($pending_rows) ?> سجل</span>
    </div>

    <!-- شريط الأدوات -->
    <?php if (!$is_admin && !$is_site_manager): ?>
    <div class="toolbar-row">
      <button class="btn btn-sm btn-primary fw-bold" onclick="approveSelected()" id="btn-primary">
        <i class="fa fa-check me-1"></i> اعتماد المحدد
      </button>
      <button class="btn btn-sm btn-secondary" onclick="selectAllPending()" id="btn-secondary">
        <i class="fa fa-check-square me-1"></i> تحديد الكل
      </button>
      <button class="btn btn-sm btn-danger" onclick="deselectAllPending()">
        <i class="fa fa-times me-1"></i> إلغاء التحديد
      </button>
      <span class="text-muted small ms-auto" id="sel-count-label">لا توجد سجلات محددة</span>
    </div>
    <?php endif; ?>

    <!-- الجدول -->
    <div class="table-wrap">
      <table id="tbl-pending" class="ha-table display nowrap ha-w100">
        <thead>
          <tr>
            <?php if (!$is_admin && !$is_site_manager): ?>
            <th class="nosort ha-w36">
              <input type="checkbox" id="chk-all-pending" aria-label="تحديد كل السجلات المعروضة للاعتماد" onchange="toggleAllPending(this)">
            </th>
            <?php endif; ?>
            <th>#</th>
            <th>التاريخ</th>
            <th>الوردية</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <?php if ($box_on): ?>
            <th class="nosort" title="من سطور السجل القانوني">توزيع الزمن</th>
            <th class="nosort ha-w44">⚠</th>
            <?php endif; ?>
            <th>المنفذة</th>
            <th>الانتظار</th>
            <th>الأعطال</th>
            <th>المجموع </th>
            <th class="nosort">الأعطال المصنفة</th>
            <th class="col-g-hours nosort">ساعات الوردية</th>
            <th class="col-g-hours nosort">ساعات الاعتماد</th>
            <th class="col-g-hours nosort">ساعات إضافية</th>
            <th class="col-g-hours nosort">ساعات المشغل</th>
            <th class="col-g-faults nosort">عطل صيانة</th>
            <th class="col-g-faults nosort">عطل بشري</th>
            <th class="col-g-faults nosort">أعطال أخرى</th>
            <th class="col-g-faults nosort">فرق العداد</th>
            <th class="col-g-faults nosort">الأعطال</th>
            <th class="col-g-notes nosort">ملاحظات العمل</th>
            <th class="col-g-faults nosort">ملاحظات الأعطال</th>
            <th class="nosort">ملاحظات</th>
            <th class="nosort ha-nw">الاعتماد والتفاصيل</th>
            <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
            <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($pending_rows as $row):
          // جلب تفاصيل الاعتمادات لكل سجل عند عرض مدير الموقع
          $approval_details = [];
          if ($is_site_manager) {
              try {
                  $ad_rows = $ha_gate->select('timesheet_approvals', array(
                      'columns' => array('approval_level', 'approved_by_name', 'approved_at'),
                      'where'   => array('timesheet_id' => intval($row['id']), 'status' => 1),
                      'orderBy' => 'approval_level ASC',
                  ));
                  foreach ($ad_rows as $ad) { $approval_details[$ad['approval_level']] = $ad; }
              } catch (\Throwable $t) { error_log('hours_approval site details: ' . $t->getMessage()); }
          }
          $_prow_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
        ?>
          <tr data-id="<?= $row['id'] ?>"
              data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_prow_equip) ?>">
            <?php if (!$is_admin && !$is_site_manager): ?>
            <td>
              <input type="checkbox" class="row-chk" aria-label="تحديد هذا السجل للاعتماد" value="<?= $row['id'] ?>"
                     onchange="updateSelCount()">
            </td>
            <?php endif; ?>
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td>
              <?php $shift = $row['shift'] ?? '';
                    $shift_ar = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : $shift);
                    $shift_cls = ($shift === 'D') ? 'ha-shift-day' : 'ha-shift-night';
              ?>
              <span class="ha-shift <?= $shift_cls ?>">
                <?= $shift_ar ?>
              </span>
            </td>
            <td><span class="text-truncate d-block ha-mw120" title="<?= htmlspecialchars($row['project_name'] ?? '') ?>"><?= htmlspecialchars($row['project_name'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''))) ?: '—' ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <?php if ($box_on):
              // §5.2: ملخّصُ الزمن من السجل القانوني + شارةُ تجاوز الطاقة
              $_bx = $box_map[intval($row['id'])] ?? null; ?>
            <td class="ha-nw" data-order="0">
              <?php if ($_bx): $_t = $_bx['time']; ?>
                <span title="تشغيل فعلي" class="ha-t-actual"><?= $_t['actual'] ?></span>
                <span class="ha-t-sep">·</span>
                <span title="استعداد" class="ha-t-standby"><?= $_t['standby'] ?></span>
                <span class="ha-t-sep">·</span>
                <span title="توقف" class="ha-t-stop"><?= $_t['stop'] ?></span>
              <?php else: ?>
                <span class="text-muted" title="صف سابق للسجل القانوني — لا سطور له">—</span>
              <?php endif; ?>
            </td>
            <td class="ha-tc">
              <?php if ($_bx && $_bx['flagged'] && !empty($_bx['flags'])): ?>
                <span class="badge bx-flag ha-flag-badge" data-ts-id="<?= intval($row['id']) ?>"
                      data-flags="<?= htmlspecialchars(implode(' · ', $_bx['flags'])) ?>"
                      title="<?= htmlspecialchars(implode(' · ', $_bx['flags'])) ?> — لا يعتمد قبل التخليص (انقر للتخليص)">⚠</span>
              <?php elseif ($_bx && $_bx['flagged']): ?>
                <span class="badge bg-success" title="كان معلما وخلص — التفصيل في سجل الأعلام">✓</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td><?= floatval($row['executed_hours'] ?? 0) ?></td>
            <td><?= floatval($row['standby_hours'] ?? 0) ?></td>
            <td><?= floatval($row['total_fault_hours'] ?? 0) ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <?php
              $_fc_c = intval($fault_counts_map[$row['id']] ?? 0);
              $_has_leg = !empty($row['fault_type']) || !empty($row['fault_part']);
              $_bc = $_fc_c > 0 ? $_fc_c : ($_has_leg ? 1 : 0);
            ?>
            <td class="ha-tc">
              <?php if ($_bc > 0): ?>
                <button class="btn-ghost" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال">
                  <i class="fa fa-exclamation-triangle ha-ico-danger"></i>
                  <span class="badge rounded-pill bg-danger ha-fs-68"><?= $_bc ?></span>
                </button>
              <?php else: ?>
                <i class="fa fa-check-circle ha-ico-ok" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td><?= floatval($row['shift_hours'] ?? 0) ?></td>
            <td><?= floatval($row['dependence_hours'] ?? 0) ?></td>
            <td><?= floatval($row['extra_hours'] ?? 0) ?></td>
            <td><?= floatval($row['operator_hours'] ?? 0) ?></td>
            <td><?= floatval($row['maintenance_fault'] ?? 0) ?></td>
            <td><?= floatval($row['hr_fault'] ?? 0) ?></td>
            <td><?= floatval($row['other_fault_hours'] ?? 0) ?></td>
            <td><?= floatval($row['counter_diff'] ?? 0) ?></td>
            <td class="ha-tc">
              <?php if ($_bc > 0): ?>
                <button class="btn-ghost" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال">
                  <i class="fa fa-exclamation-triangle ha-ico-danger"></i>
                  <span class="badge rounded-pill bg-danger ha-fs-68"><?= $_bc ?></span>
                </button>
              <?php else: ?>
                <i class="fa fa-check-circle ha-ico-ok" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td class="text-truncate ha-mw100" title="<?= htmlspecialchars($row['work_notes'] ?? '') ?>"><?= htmlspecialchars($row['work_notes'] ?? '—') ?></td>
            <td class="text-truncate ha-mw100" title="<?= htmlspecialchars($row['fault_notes'] ?? '') ?>"><?= htmlspecialchars($row['fault_notes'] ?? '—') ?></td>
            <td>
              <button class="btn-ghost" onclick="openNotes(<?= $row['id'] ?>)"
                      title="عرض / إضافة ملاحظة">
                <i class="fa fa-comment-dots<?php if (intval($row['notes_count']) > 0): ?> ha-note-active<?php endif; ?>"></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                  <span class="note-cnt"><?= $row['notes_count'] ?></span>
                <?php endif; ?>
              </button>
            </td>
            <!-- عمود مؤشر التقدم البصري وإجراءات الاعتماد -->
            <td class="ha-nw">
              <div class="d-inline-flex align-items-center gap-2">
                <!-- مؤشر التقدم: 4 دوائر -->
                <div class="apv-circles">
                  <?php for ($lv = 1; $lv <= 4; $lv++):
                    $lv_info = $level_role_name[$lv];
                    $done    = (intval($row['max_approved_level'] ?? 0) >= $lv);
                  ?>
                  <span class="apv-circle-wrap">
                    <span class="apv-circle <?= $done ? 'apv-done' : 'apv-pending' ?>" data-allow-style
                          style="border-color:<?= $lv_info['color'] ?>;
                                  <?php if ($done): ?>background:<?= $lv_info['color'] ?>;color:var(--c-surface);<?php endif; ?>"
                          title="<?= $lv_info['label'] ?>">
                      <i class="fa <?= $lv_info['icon'] ?> ha-ico-xs"></i>
                    </span>
                    <span class="apv-tooltip">
                      <span class="tt-role"><?= $lv_info['label'] ?></span>
                      <span class="tt-name"><?= $done ? 'معتمد' : 'قيد الانتظار' ?></span>
                    </span>
                  </span>
                  <?php if ($lv < 4): ?><span class="apv-connector <?= $done ? 'apv-conn-done' : '' ?>"></span><?php endif; ?>
                  <?php endfor; ?>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="d-inline-flex gap-1">
                  <a href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>" target="_blank"
                     class="apv-eye" title="عرض تفاصيل السجل">
                    <i class="fa fa-circle-info"></i>
                  </a>
                  <?php if (!$is_admin && !$is_site_manager && $my_level <= 4 && (intval($row['max_approved_level'] ?? 0) < $my_level)): ?>
                  <button class="apv-approve" onclick="approveSingle(<?= $row['id'] ?>)"
                          title="اعتماد السجل">
                    <i class="fa fa-check"></i>
                  </button>
                  <button class="apv-reject" onclick="rejectSingle(<?= $row['id'] ?>)"
                          title="رفض السجل">
                    <i class="fa fa-xmark"></i>
                  </button>
                  <?php if ($box_on && isset($box_map[intval($row['id'])])): ?>
                  <!-- §5.2 الثلاثية الموحّدة: اعتماد · إعادة بسبب · رفض بسبب -->
                  <button class="apv-return ha-btn-return" onclick="returnSingle(<?= $row['id'] ?>)"
                          title="إعادة للاستكمال بسبب — بالرقم نفسه">
                    <i class="fa fa-rotate-left"></i>
                  </button>
                  <?php endif; ?>
                  <?php elseif (!$is_admin && !$is_site_manager): ?>
                  <!-- أزرار معطّلة للأدوار التي لا يطابق مستواها السجل الحالي -->
                  <span class="apv-action-disabled" title="لا يمكنك الاعتماد في هذا المستوى"><i class="fa fa-check"></i></span>
                  <span class="apv-action-disabled" title="لا يمكنك الرفض في هذا المستوى"><i class="fa fa-xmark"></i></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       جدول ٢: التايمشيت المعتمد نهائياً
  ══════════════════════════════════════════════════════════ -->
  <div class="table-card">
    <div class="card-header-custom">
      <div class="ch-icon ha-ico-approved"><i class="fa fa-shield-check"></i></div>
      <div>
        <h5>التايمشيت المعتمد نهائيا</h5>
        <small class="text-muted">آخر 100 سجل حصلوا على اعتماد المستوى الرابع (مدير المشغلين)</small>
      </div>
      <span class="badge-count badge ha-badge-approved"><?= count($approved_rows) ?> سجل</span>
    </div>

    <div class="table-wrap">
      <table id="tbl-approved" class="ha-table display nowrap ha-w100">
        <thead>
          <tr>
            <th>#</th>
            <th>التاريخ</th>
            <th>الوردية</th>
            <th>المشروع</th>
            <th>المورد</th>
            <th>الآلية</th>
            <th>المشغل</th>
            <th> المنفذة</th>
            <th> الانتظار</th>
            <th> الأعطال</th>
            <th>المجموع </th>
            <th class="nosort">الأعطال المصنفة</th>
            <th class="col-g-hours nosort">ساعات الوردية</th>
            <th class="col-g-hours nosort">ساعات الاعتماد</th>
            <th class="col-g-hours nosort">ساعات إضافية</th>
            <th class="col-g-hours nosort">ساعات المشغل</th>
            <th class="col-g-faults nosort">عطل صيانة</th>
            <th class="col-g-faults nosort">عطل بشري</th>
            <th class="col-g-faults nosort">أعطال أخرى</th>
            <th class="col-g-faults nosort">فرق العداد</th>
            <th class="col-g-faults nosort">الأعطال</th>
            <th class="col-g-notes nosort">ملاحظات العمل</th>
            <th class="col-g-faults nosort">ملاحظات الأعطال</th>
            <th class="nosort">مسار الاعتماد</th>
            <th class="nosort">تاريخ الاعتماد</th>
            <th class="nosort">ملاحظات</th>
          </tr>
        </thead>
        <tbody>
        <?php $idx = 1; foreach ($approved_rows as $row):
          // تفاصيل اعتمادات هذا السجل (تم جلبها مسبقاً دفعة واحدة)
          $row_approvals = $all_approval_details[intval($row['id'])] ?? [];
          $_arow_equip = trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''));
        ?>
          <tr data-project="<?= htmlspecialchars($row['project_name'] ?? '') ?>"
              data-supplier="<?= htmlspecialchars($row['supplier_name'] ?? '') ?>"
              data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
              data-equip="<?= htmlspecialchars($_arow_equip) ?>">
            <td><?= $idx++ ?></td>
            <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
            <td>
              <?php $shift = $row['shift'] ?? '';
                    $shift_ar = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : $shift);
                    $shift_cls = ($shift === 'D') ? 'ha-shift-day' : 'ha-shift-night';
              ?>
              <span class="ha-shift <?= $shift_cls ?>">
                <?= $shift_ar ?>
              </span>
            </td>
            <td><span class="text-truncate d-block ha-mw120" title="<?= htmlspecialchars($row['project_name'] ?? '') ?>"><?= htmlspecialchars($row['project_name'] ?? '—') ?></span></td>
            <td><?= htmlspecialchars($row['supplier_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(trim(($row['equip_code'] ?? '') . ' ' . ($row['equip_name'] ?? ''))) ?: '—' ?></td>
            <td><?= htmlspecialchars($row['driver_name'] ?? '—') ?></td>
            <td><?= floatval($row['executed_hours'] ?? 0) ?></td>
            <td><?= floatval($row['standby_hours'] ?? 0) ?></td>
            <td><?= floatval($row['total_fault_hours'] ?? 0) ?></td>
            <td><strong><?= floatval($row['total_work_hours'] ?? 0) ?></strong></td>
            <?php
              $_fc_c2 = intval($fault_counts_map[$row['id']] ?? 0);
              $_has_leg2 = !empty($row['fault_type']) || !empty($row['fault_part']);
              $_bc2 = $_fc_c2 > 0 ? $_fc_c2 : ($_has_leg2 ? 1 : 0);
            ?>
            <td class="ha-tc">
              <?php if ($_bc2 > 0): ?>
                <button class="btn-ghost" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال">
                  <i class="fa fa-exclamation-triangle ha-ico-danger"></i>
                  <span class="badge rounded-pill bg-danger ha-fs-68"><?= $_bc2 ?></span>
                </button>
              <?php else: ?>
                <i class="fa fa-check-circle ha-ico-ok" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td><?= floatval($row['shift_hours'] ?? 0) ?></td>
            <td><?= floatval($row['dependence_hours'] ?? 0) ?></td>
            <td><?= floatval($row['extra_hours'] ?? 0) ?></td>
            <td><?= floatval($row['operator_hours'] ?? 0) ?></td>
            <td><?= floatval($row['maintenance_fault'] ?? 0) ?></td>
            <td><?= floatval($row['hr_fault'] ?? 0) ?></td>
            <td><?= floatval($row['other_fault_hours'] ?? 0) ?></td>
            <td><?= floatval($row['counter_diff'] ?? 0) ?></td>
            <td class="ha-tc">
              <?php if ($_bc2 > 0): ?>
                <button class="btn-ghost" data-ts-id="<?= intval($row['id']) ?>" title="عرض الأعطال">
                  <i class="fa fa-exclamation-triangle ha-ico-danger"></i>
                  <span class="badge rounded-pill bg-danger ha-fs-68"><?= $_bc2 ?></span>
                </button>
              <?php else: ?>
                <i class="fa fa-check-circle ha-ico-ok" title="لا توجد أعطال"></i>
              <?php endif; ?>
            </td>
            <td class="text-truncate ha-mw100" title="<?= htmlspecialchars($row['work_notes'] ?? '') ?>"><?= htmlspecialchars($row['work_notes'] ?? '—') ?></td>
            <td class="text-truncate ha-mw100" title="<?= htmlspecialchars($row['fault_notes'] ?? '') ?>"><?= htmlspecialchars($row['fault_notes'] ?? '—') ?></td>

            <!-- ══ عمود مؤشر التقدم: 4 دوائر بـ tooltip مع معلومات المعتمد ══ -->
            <td class="ha-nw">
              <div class="d-inline-flex align-items-center gap-0">
                <?php for ($lv = 1; $lv <= 4; $lv++):
                  $lv_info  = $level_role_name[$lv];
                  $lv_data  = $row_approvals[$lv] ?? null;
                  $apv_name = $lv_data ? htmlspecialchars($lv_data['approved_by_name']) : '—';
                  $apv_date = $lv_data ? date('Y-m-d H:i', strtotime($lv_data['approved_at'])) : '—';
                  $apv_role = htmlspecialchars($lv_info['label']);
                ?>
                <span class="apv-circle-wrap">
                  <span class="apv-circle apv-done" data-allow-style
                        style="background:<?= $lv_info['color'] ?>;border-color:<?= $lv_info['color'] ?>;color:var(--c-surface);">
                    <i class="fa <?= $lv_info['icon'] ?> ha-ico-xxs"></i>
                  </span>
                  <span class="apv-tooltip">
                    <span class="tt-role"><?= $apv_role ?></span>
                    <span class="tt-name"><?= $apv_name ?></span>
                    <span class="tt-date"><i class="fa fa-clock ha-ico-mr3"></i><?= $apv_date ?></span>
                  </span>
                </span>
                <?php if ($lv < 4): ?><span class="apv-connector apv-conn-done"></span><?php endif; ?>
                <?php endfor; ?>
              </div>
            </td>

            <td>
              <span class="ha-meta-date">
                <?= date('Y-m-d H:i', strtotime($row['final_approved_at'] ?? 'now')) ?>
              </span>
            </td>
            <td>
              <button class="btn-ghost" onclick="openNotes(<?= $row['id'] ?>)"
                      title="عرض الملاحظات">
                <i class="fa fa-comment-dots<?php if (intval($row['notes_count']) > 0): ?> ha-note-active<?php endif; ?>"></i>
                <?php if (intval($row['notes_count']) > 0): ?>
                  <span class="note-cnt"><?= $row['notes_count'] ?></span>
                <?php endif; ?>
              </button>
              <span class="apv-sep">|</span>
              <a href="../Timesheet/timesheet_details.php?id=<?= intval($row['id']) ?>" target="_blank"
                 class="apv-eye" title="عرض التفاصيل">
                <i class="fa fa-circle-info"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- end page-wrapper -->
</div>

<!-- ══ Modal: عرض الأعطال ══ -->
<div class="modal fade" id="faultDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-exclamation-triangle text-danger me-2"></i>
          تفاصيل الأعطال — سجل #<span id="faultModal_ts_id_ha">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="faultModalBody_ha">
        <div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Modal: الملاحظات
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-comment-dots me-2 text-primary"></i>
          ملاحظات السجل #<span id="modal-ts-id">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- قائمة الملاحظات الموجودة -->
        <div id="notes-list" class="mb-3">
          <div class="text-center text-muted py-3">
            <i class="fa fa-spinner fa-spin me-1"></i> جار التحميل...
          </div>
        </div>

        <hr>

        <!-- نموذج إضافة ملاحظة جديدة -->
        <div>
          <h6 class="fw-bold mb-3"><i class="fa fa-plus-circle me-1 text-primary"></i> إضافة ملاحظة جديدة</h6>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold" for="note-col-select">العمود / الحقل المعني</label>
              <select class="form-select form-select-sm" id="note-col-select">
                <option value="">— اختر العمود —</option>
                <?php foreach ($column_labels as $col_key => $col_lbl): ?>
                <option value="<?= $col_key ?>" data-label="<?= htmlspecialchars($col_lbl) ?>">
                  <?= htmlspecialchars($col_lbl) ?>
                </option>
                <?php endforeach; ?>
                <option value="other" data-label="أخرى">أخرى</option>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-semibold" for="note-text-input">نص الملاحظة</label>
              <textarea class="form-control form-control-sm" id="note-text-input"
                        rows="3" placeholder="اكتب ملاحظتك هنا..."></textarea>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm fw-bold" onclick="submitNote()">
              <i class="fa fa-save me-1"></i> حفظ الملاحظة
            </button>
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Modal: تأكيد الاعتماد
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" dir="rtl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-check-circle me-2 text-success"></i> تأكيد الاعتماد
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="confirm-approve-msg" class="mb-0"></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary fw-bold" id="btn-primary">
          <i class="fa fa-check me-1"></i> نعم، اعتمد
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Modal: رفض سجل مع ملاحظة
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" dir="rtl">
      <div class="modal-header ha-mh-danger">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-xmark me-2 text-danger"></i> رفض السجل #<span id="reject-ts-id">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <i class="fa fa-triangle-exclamation mt-1 flex-shrink-0"></i>
          <div>سيتم إعادة السجل إلى أول سلسلة الاعتماد. يجب كتابة سبب واضح للرفض.</div>
        </div>
        <label class="form-label fw-bold" for="reject-reason-text">
          <i class="fa fa-comment-dots me-1 text-danger"></i>
          سبب الرفض <span class="text-danger">*</span>
        </label>
        <textarea id="reject-reason-text" class="form-control ha-reject-reason" rows="4"
                  placeholder="اكتب سبب الرفض بالتفصيل..."></textarea>
        <div id="reject-reason-error" class="text-danger small mt-1 ha-hidden">
          <i class="fa fa-exclamation-circle me-1"></i> يرجى كتابة سبب الرفض
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger fw-bold" id="btn-danger">
          <i class="fa fa-xmark me-1"></i> تأكيد الرفض
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Modal: رفض سجل مع ملاحظة إلزامية
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" dir="rtl">
      <div class="modal-header ha-mh-danger">
        <h5 class="modal-title fw-bold">
          <i class="fa fa-xmark me-2 text-danger"></i>
          رفض السجل #<span id="reject-ts-id">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <i class="fa fa-triangle-exclamation mt-1 flex-shrink-0"></i>
          <div>سيتم إعادة السجل إلى أول سلسلة الاعتماد. يجب كتابة سبب واضح للرفض.</div>
        </div>
        <label class="form-label fw-bold" for="reject-reason-text">
          <i class="fa fa-comment-dots me-1 text-danger"></i>
          سبب الرفض <span class="text-danger">*</span>
        </label>
        <textarea id="reject-reason-text" class="form-control ha-reject-reason" rows="4"
                  placeholder="اكتب سبب الرفض بالتفصيل..."></textarea>
        <div id="reject-reason-error" class="text-danger small mt-1 ha-hidden">
          <i class="fa fa-exclamation-circle me-1"></i> يرجى كتابة سبب الرفض
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger fw-bold" id="btn-danger">
          <i class="fa fa-xmark me-1"></i> تأكيد الرفض
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Toasts ── -->
<div class="position-fixed bottom-0 end-0 p-3 ha-toast-wrap">
  <div id="approvalToast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-bold" id="toast-msg"></div>
      <button type="button" class="btn-close btn-secondary ms-auto me-2" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JS
══════════════════════════════════════════════════════════════ -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>

<script>
// ── متغيرات الحالة ──────────────────────────────────────────
var currentTsId      = 0;
var pendingApproveIds = [];
var dtPending   = null;
var dtApproved  = null;

// ── تهيئة DataTables + ربط الأحداث بعد تحميل DOM ────────────
$(function () {
  var dtOpts = {
    language : { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json' },
    pageLength: 25,
    scrollX   : true,
    scrollCollapse: true,
    autoWidth : false,
    order     : [[1, 'desc']],
    dom       : '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>rt<"row mt-2"<"col-sm-6"i><"col-sm-6"p>>',
  };

  dtPending = $('#tbl-pending').DataTable($.extend({}, dtOpts, {
    order: [[<?= (!$is_admin && !$is_site_manager) ? 2 : 1 ?>, 'desc']],
    columnDefs: [{ orderable: false, targets: '.nosort' }]
  }));
  dtApproved = $('#tbl-approved').DataTable($.extend({}, dtOpts, {
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: '.nosort' }]
  }));

  // إعادة ضبط قياسات الأعمدة لضمان ظهور شريط التمرير الأفقي بعد التحميل.
  setTimeout(function () {
    if (dtPending) dtPending.columns.adjust();
    if (dtApproved) dtApproved.columns.adjust();
  }, 50);

  $(window).on('resize', function () {
    if (dtPending) dtPending.columns.adjust();
    if (dtApproved) dtApproved.columns.adjust();
  });

  // إظهار/إخفاء المجموعات — موحّد عبر assets/js/column-groups.js (يطبّق على الجدولين)
  if (window.EmsColumnGroups) {
    EmsColumnGroups.init({
      storageKey: 'hoursApprovalGroupStates',
      mode: 'datatable',
      tables: [dtPending, dtApproved],
      columnClass: true,
      buttons  : '.ems-btn-group-toggle[data-group]',
      allButton: '.ems-btn-group-toggle-all'
    });
  }

  // حساب إجمالي الملاحظات
  var totalNotes = 0;
  $('.note-cnt').each(function(){ totalNotes += parseInt($(this).text()) || 0; });
  $('#total-notes-count').text(totalNotes);

  // ── زر تأكيد الاعتماد ───────────────────────────────────────
  $('#btn-primary').on('click', function () {
    if (pendingApproveIds.length === 0) return;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جار الاعتماد...';

    $.ajax({
      url      : 'hours_approval_handler.php',
      method   : 'POST',
      dataType : 'json',
      data     : { action: 'approve', ids: pendingApproveIds.join(',') },
      success  : function(res) {
        var modal = bootstrap.Modal.getInstance(document.getElementById('confirmApproveModal'));
        if (modal) modal.hide();
        if (res.success) {
          // ── E-08-أ: الأسبابُ المفصَّلةُ تصل المستخدم ────────────────────
          // كان الردُّ يحمل `blocked[].reasons` مسمّاةً (اسمُ الوثيقة وتاريخُ
          // انتهائها · محورُ الطاقة وقياسُه) و**الشاشةُ تعرض `res.message`
          // وحدَه** — فيرى المعتمِدُ «موقوف» بلا أن يعرف ما يفعل. والملخّصُ
          // لا يكفي: العلاجُ يختلف باختلاف السبب وباختلاف الصفّ.
          renderBlocked(res.blocked);
          if (res.blocked && res.blocked.length) {
            // لا تحديثَ تلقائيّ: إعادةُ التحميل تمحو الأسبابَ قبل أن تُقرأ.
            showToast('⚠️ ' + res.message, 'warning');
          } else {
            showToast('✅ ' + res.message, 'success');
            setTimeout(function(){ location.reload(); }, 1200);
          }
        } else {
          showToast('❌ ' + res.message, 'danger');
        }
      },
      error: function(xhr) {
        showToast('❌ حدث خطأ في الاتصال: ' + xhr.status, 'danger');
      },
      complete: function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check me-1"></i> نعم، اعتمد';
      }
    });
  });
});

// ── دوال الاعتماد والرفض ─────────────────────────────────────
// تبديل مجموعات الأعمدة: مُوحّد الآن عبر assets/js/column-groups.js
// (يُهيّأ داخل كتلة DataTables أعلاه).

function approveSingle(id) {
  pendingApproveIds = [id];
  $('#confirm-approve-msg').html(
    'هل تريد اعتماد السجل رقم <strong>#' + id + '</strong>؟'
  );
  var modal = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
  modal.show();
}

function approveSelected() {
  var checked = [];
  $('.row-chk:checked').each(function() { checked.push($(this).val()); });
  if (checked.length === 0) {
    showToast('يرجى تحديد سجل واحد على الأقل', 'warning');
    return;
  }
  pendingApproveIds = checked;
  $('#confirm-approve-msg').html(
    'هل تريد اعتماد <strong>' + checked.length + '</strong> سجل محدد؟'
  );
  var modal = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
  modal.show();
}

// ── دالة الرفض مع ملاحظة ─────────────────────────────────────
var rejectTargetId = 0;

function rejectSingle(id) {
  rejectTargetId = id;
  $('#reject-ts-id').text(id);
  $('#reject-reason-text').val('');
  $('#reject-reason-error').hide();
  $('#reject-reason-text').removeClass('ha-invalid');
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

$(function() {
  $('#btn-danger').on('click', function() {
    var reason = $('#reject-reason-text').val().trim();
    if (!reason) {
      $('#reject-reason-error').show();
      $('#reject-reason-text').addClass('ha-invalid').trigger('focus');
      return;
    }
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> جار الرفض...';

    $.ajax({
      url      : 'hours_approval_handler.php',
      method   : 'POST',
      dataType : 'json',
      data     : { action: 'reject', timesheet_id: rejectTargetId, reason: reason },
      success  : function(res) {
        var modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
        if (modal) modal.hide();
        showToast((res.success ? '↩ ' : '❌ ') + res.message, res.success ? 'warning' : 'danger');
        if (res.success) setTimeout(function(){ location.reload(); }, 1400);
      },
      error: function(xhr) {
        showToast('❌ حدث خطأ في الاتصال: ' + xhr.status, 'danger');
      },
      complete: function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-xmark me-1"></i> تأكيد الرفض';
      }
    });
  });
});

// ── دوال تحديد الكل ─────────────────────────────────────────
function selectAllPending() {
  $('.row-chk').prop('checked', true);
  $('#chk-all-pending').prop('checked', true);
  updateSelCount();
}
function deselectAllPending() {
  $('.row-chk').prop('checked', false);
  $('#chk-all-pending').prop('checked', false);
  updateSelCount();
}
function toggleAllPending(cb) {
  $('.row-chk').prop('checked', cb.checked);
  updateSelCount();
}
function updateSelCount() {
  const cnt = $('.row-chk:checked').length;
  $('#sel-count-label').text(cnt > 0 ? cnt + ' سجل محدد' : 'لا توجد سجلات محددة');
  // تمييز الصفوف
  $('#tbl-pending tbody tr').each(function(){
    const chk = $(this).find('.row-chk');
    if (chk.length && chk.is(':checked')) {
      $(this).addClass('selected-row');
    } else {
      $(this).removeClass('selected-row');
    }
  });
}

// ── دوال الملاحظات ───────────────────────────────────────────
function openNotes(tsId) {
  currentTsId = tsId;
  $('#modal-ts-id').text(tsId);
  $('#notes-list').html('<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin me-1"></i> جار التحميل...</div>');
  $('#note-col-select').val('');
  $('#note-text-input').val('');

  var modal = new bootstrap.Modal(document.getElementById('notesModal'));
  modal.show();

  loadNotes(tsId);
}

function loadNotes(tsId) {
  $.ajax({
    url      : 'hours_approval_handler.php',
    method   : 'POST',
    dataType : 'json',
    data     : { action: 'get_notes', timesheet_id: tsId },
    success  : function(res) {
      if (!res.success) {
        $('#notes-list').html('<div class="alert alert-danger">' + res.message + '</div>');
        return;
      }
      if (res.notes.length === 0) {
        $('#notes-list').html('<div class="text-center text-muted py-2"><i class="fa fa-comment-slash me-1"></i>لا توجد ملاحظات بعد</div>');
        return;
      }
      let html = '';
      res.notes.forEach(function(n) {
        const roleTxt = n.created_by_role_label ? ' - ' + n.created_by_role_label : '';
        html += `
          <div class="note-item" id="note-item-${n.id}">
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="note-col-badge">${escHtml(n.column_label)}</span>
              <span class="fw-semibold small">${escHtml(n.created_by_name)}${escHtml(roleTxt)}</span>
            </div>
            <div class="note-text">${escHtml(n.note_text)}</div>
            <div class="note-meta"><i class="fa fa-calendar-alt me-1"></i>${escHtml(n.created_at)}</div>
          </div>
        `;
      });
      $('#notes-list').html(html);
    },
    error: function() {
      $('#notes-list').html('<div class="alert alert-danger">فشل تحميل الملاحظات</div>');
    }
  });
}

function submitNote() {
  var col   = $('#note-col-select').val();
  var label = $('#note-col-select option:selected').data('label') || col;
  var text  = $('#note-text-input').val().trim();

  if (!col) { showToast('يرجى اختيار العمود المعني', 'warning'); return; }
  if (!text){ showToast('يرجى كتابة نص الملاحظة', 'warning'); return; }

  $.ajax({
    url      : 'hours_approval_handler.php',
    method   : 'POST',
    dataType : 'json',
    data     : {
      action      : 'add_note',
      timesheet_id: currentTsId,
      column_name : col,
      column_label: label,
      note_text   : text
    },
    success: function(res) {
      if (res.success) {
        showToast('✅ تمت إضافة الملاحظة', 'success');
        $('#note-col-select').val('');
        $('#note-text-input').val('');
        loadNotes(currentTsId);
        // تحديث عداد الملاحظات في الجدول
        updateNoteCountBadge(currentTsId, 1);
      } else {
        showToast('❌ ' + res.message, 'danger');
      }
    },
    error: function() { showToast('❌ خطأ في الاتصال', 'danger'); }
  });
}

function updateNoteCountBadge(tsId, delta) {
  // نحدّث عداد الملاحظة في زر الملاحظة في الجدول
  $('tr[data-id="' + tsId + '"]').find('.btn-ghost').each(function(){
    const cntEl = $(this).find('.note-cnt');
    let current = parseInt(cntEl.text()) || 0;
    const newVal = Math.max(0, current + delta);
    if (newVal > 0) {
      if (cntEl.length) cntEl.text(newVal);
      else $(this).append('<span class="note-cnt">' + newVal + '</span>');
    } else {
      cntEl.remove();
    }
  });
}

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type) {
  const el = document.getElementById('approvalToast');
  el.className = 'toast align-items-center text-white border-0 bg-' + (type || 'success');
  document.getElementById('toast-msg').textContent = msg;
  const toast = new bootstrap.Toast(el, { delay: 3000 });
  toast.show();
}

// ── E-08-أ: عرضُ أسباب الحجب المفصَّلة ────────────────────────
// «الردُّ الخادميُّ لا يصل المستخدم تلقائيًّا»: المعالجُ يُرسل لكل صفٍّ موقوفٍ
// أسبابَه مسمّاةً، وهذه الدالةُ هي التي توصلها. صفٌّ واحدٌ لكل معرّف، وأسبابُه
// تحته — فيرى المعتمِدُ **أيَّ** صفٍّ وقف و**لماذا** و**ماذا يفعل**، لا ملخّصًا
// يقول «موقوف» ويتركه يخمّن.
function renderBlocked(blocked) {
  var panel = document.getElementById('blocked-panel');
  var list  = document.getElementById('blocked-list');
  if (!panel || !list) { return; }
  if (!blocked || !blocked.length) { panel.classList.add('d-none'); list.innerHTML = ''; return; }

  var html = '';
  for (var i = 0; i < blocked.length; i++) {
    var b = blocked[i] || {};
    var reasons = b.reasons || [];
    // لا سببَ مُرسَل = عطبٌ يُعلَن لا يُخفى (لا نخترع له نصًّا)
    var body = reasons.length
      ? '<ul class="mb-0">' + reasons.map(function (r) {
          return '<li>' + escHtml(r) + '</li>';
        }).join('') + '</ul>'
      : '<span class="text-muted">لم يرسل سبب مفصل — راجع سجل النظام.</span>';
    html += '<li class="mb-2"><strong>السجل #' + escHtml(b.id) + '</strong>'
          + (b.kind === 'document' ? ' <span class="badge bg-danger">وثيقة منتهية</span>'
             : (b.kind === 'capacity' ? ' <span class="badge bg-warning text-dark">تجاوز طاقة</span>' : ''))
          + body + '</li>';
  }
  list.innerHTML = html;
  panel.classList.remove('d-none');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Escape HTML ──────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

// ── الأعطال badge handler ────────────────────────────────────
$(document).on('click', '.btn-ghost', function() {
  var tsId = $(this).data('ts-id');
  $('#faultModal_ts_id_ha').text(tsId);
  $('#faultModalBody_ha').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>');
  var modal = new bootstrap.Modal(document.getElementById('faultDetailModal'));
  modal.show();
  $.getJSON('../Timesheet/get_timesheet_failures.php?timesheet_id=' + tsId, function(res) {
    if (res && res.success && res.data && res.data.length > 0) {
      var html = '<div class="table"><table class="table table-sm table-hover table-bordered">';
      html += '<thead class="table-dark"><tr><th>#</th><th>الكود الكامل</th><th>نوع الحدث</th><th>الفئة الرئيسية</th><th>الفئة الفرعية</th><th>تفصيل العطل</th></tr></thead><tbody>';
      $.each(res.data, function(i, f) {
        html += '<tr>';
        html += '<td>' + (i+1) + '</td>';
        html += '<td><span class="badge rounded-pill bg-danger">' + escHtml(f.full_code || '—') + '</span></td>';
        html += '<td>' + escHtml(f.event_type_name || '—') + '</td>';
        html += '<td>' + escHtml(f.main_category_name || '—') + '</td>';
        html += '<td>' + escHtml(f.sub_category || '—') + '</td>';
        html += '<td>' + escHtml(f.failure_detail || '—') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table></div>';
      $('#faultModalBody_ha').html(html);
    } else {
      $('#faultModalBody_ha').html('<div class="alert alert-warning">لا توجد أعطال مصنفة من منظومة الأعطال. <small class="text-muted">قد تكون البيانات محفوظة بالنظام القديم.</small></div>');
    }
  }).fail(function() {
    $('#faultModalBody_ha').html('<div class="alert alert-danger">تعذر تحميل بيانات الأعطال.</div>');
  });
});

// ── الفلاتر ──────────────────────────────────────────────────
// دالة مخصصة ل DataTables تفلتر بناء على data attributes في <tr>
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
  var tblId = settings.nTable.id;
  if (tblId !== 'tbl-pending' && tblId !== 'tbl-approved') return true;

  var nTr = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
  if (!nTr) return true;

  var $tr = $(nTr);
  var fp  = $('#filter-project').val();
  var fs  = $('#filter-supplier').val();
  var fd  = $('#filter-driver').val();
  var fe  = $('#filter-equip').val();

  if (fp && $tr.data('project')  !== fp) return false;
  if (fs && $tr.data('supplier') !== fs) return false;
  if (fd && $tr.data('driver')   !== fd) return false;
  if (fe && $tr.data('equip')    !== fe) return false;
  return true;
});

function applyFilters() {
  if (dtPending)  dtPending.draw();
  if (dtApproved) dtApproved.draw();
  updateFilterInfo();
}

function resetFilters() {
  $('#filter-project').val('');
  $('#filter-supplier').val('');
  $('#filter-driver').val('');
  $('#filter-equip').val('');
  applyFilters();
}

function updateFilterInfo() {
  var fp = $('#filter-project').val();
  var fs = $('#filter-supplier').val();
  var fd = $('#filter-driver').val();
  var fe = $('#filter-equip').val();
  var parts = [];
  if (fp) parts.push('مشروع: ' + fp);
  if (fs) parts.push('مورد: ' + fs);
  if (fd) parts.push('مشغل: ' + fd);
  if (fe) parts.push('آلية: ' + fe);
  var info = $('#active-filters-info');
  if (parts.length > 0) {
    $('#active-filters-text').text(parts.join(' | '));
    info.show();
  } else {
    info.hide();
  }
}

// تطبيق الفلاتر عند تغيير أي قائمة منسدلة
$(document).on('change', '#filter-project, #filter-supplier, #filter-driver, #filter-equip', function(){
  applyFilters();
});

// ── فلتر نوع المعدة ──────────────────────────────────────────
function applyEquipTypeFilter() {
  var equipType = $('#equip_type').val();
  // إعادة تحميل الصفحة مع معامل الفلتر
  window.location.href = 'hours_approval.php?equip_type=' + equipType;
}
</script>

<?php if ($box_on): ?>
<!-- ═══ UX-03 §5.2 — مودالا التخليص والإعادة (خلف EMS_APPROVAL_BOX) ═══ -->
<div class="modal fade" id="bxClearModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header ha-mh-warn">
      <h6 class="modal-title"><i class="fa fa-triangle-exclamation ha-ico-warn"></i>
        تخليص تجاوز الطاقة — السجل <strong id="bx-clear-id"></strong></h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="alert alert-warning py-2 ha-fs-85" id="bx-clear-flags"></div>
      <p class="ha-note-p">
        نص §5.2: «صف تجاوز طاقة لا يعتمد قبل: السبب · فحص التداخل ·
        تحديد المشغل الثاني» — الفحص يجري آليا، والاثنان الباقيان إفصاحك أنت.
      </p>
      <label class="form-label fw-bold" for="bx-clear-cause">سبب التجاوز *</label>
      <textarea id="bx-clear-cause" class="form-control" rows="2"
                placeholder="مثال: وردية مزدوجة طارئة بطلب العميل — تسليم شحنة"></textarea>
      <label class="form-label fw-bold mt-3" for="bx-return-reason">هل عمل مشغل ثان؟ *</label>
      <div>
        <label class="me-3"><input type="radio" name="bx-second" value="1" aria-label="نعم — عمل مشغل ثان في الوردية"> نعم — مشغلان تناوبا</label>
        <label><input type="radio" name="bx-second" value="0" aria-label="لا — مشغل واحد في الوردية"> لا — مشغل واحد</label>
      </div>
      <div id="bx-clear-err" class="text-danger mt-2 ha-fs-85 ha-hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
      <button class="btn btn-secondary btn-sm fw-bold" id="bx-clear-go">
        <i class="fa fa-unlock"></i> خلص باسمي</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="bxReturnModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header ha-mh-info">
      <h6 class="modal-title"><i class="fa fa-rotate-left"></i>
        إعادة للاستكمال — السجل <strong id="bx-return-id"></strong></h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="ha-note-p">
        تعود الواقعة لمدخلها <strong>بالرقم نفسه</strong> في جولة جديدة —
        يعدلها ويعيد إرسالها، وتاريخ الجولات كله محفوظ (§8.2).
      </p>
      <label class="form-label fw-bold">سبب الإعادة *</label>
      <div>
        <label class="me-3"><input type="radio" name="bx-second" value="1" aria-label="نعم — عمل مشغل ثان في الوردية"> نعم — مشغلان تناوبا</label>
        <label><input type="radio" name="bx-second" value="0" aria-label="لا — مشغل واحد في الوردية"> لا — مشغل واحد</label>
      </div>
      <div id="bx-clear-err" class="text-danger mt-2 ha-fs-85 ha-hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
      <button class="btn btn-secondary btn-sm fw-bold" id="bx-clear-go">
        <i class="fa fa-unlock"></i> خلص باسمي</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="bxReturnModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header ha-mh-info">
      <h6 class="modal-title"><i class="fa fa-rotate-left"></i>
        إعادة للاستكمال — السجل <strong id="bx-return-id"></strong></h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="ha-note-p">
        تعود الواقعة لمدخلها <strong>بالرقم نفسه</strong> في جولة جديدة —
        يعدلها ويعيد إرسالها، وتاريخ الجولات كله محفوظ (§8.2).
      </p>
      <label class="form-label fw-bold">سبب الإعادة *</label>
      <textarea id="bx-return-reason" class="form-control" rows="2"
                placeholder="مثال: توزيع الزمن ناقص — ساعتا التوقف بلا مسؤول ومرجع"></textarea>
      <div id="bx-return-err" class="text-danger mt-2 ha-fs-85 ha-hidden"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">إلغاء</button>
      <button class="btn btn-primary btn-sm fw-bold" id="bx-return-go">
        <i class="fa fa-rotate-left"></i> أعد للاستكمال</button>
    </div>
  </div></div>
</div>

<script>
// ═══ §5.2 — سلوك الصندوق ═══════════════════════════════════════════════
var bxClearTsId = 0, bxReturnTsId = 0;

// شارةُ ⚠ تفتح مودالَ التخليص (لمعتمِد الموقع؛ الخادم يتحقق من المستوى)
$(document).on('click', '.bx-flag', function () {
  bxClearTsId = parseInt($(this).data('ts-id'), 10);
  $('#bx-clear-id').text('#' + bxClearTsId);
  $('#bx-clear-flags').text($(this).data('flags'));
  $('#bx-clear-cause').val('');
  $('input[name="bx-second"]').prop('checked', false);
  $('#bx-clear-err').hide();
  new bootstrap.Modal(document.getElementById('bxClearModal')).show();
});

$('#bx-clear-go').on('click', function () {
  var cause = $.trim($('#bx-clear-cause').val());
  var second = $('input[name="bx-second"]:checked').val();
  if (!cause || second === undefined) {
    $('#bx-clear-err').text('السبب وإعلان المشغل الثاني إلزاميان.').show();
    return;
  }
  var btn = this; btn.disabled = true;
  $.post('hours_approval_handler.php',
    { action: 'clear_capacity', timesheet_id: bxClearTsId, cause_note: cause,
      second_operator: second, csrf_token: window.csrfToken || $('input[name="csrf_token"]').val() },
    function (res) {
      if (res.success) { showToast('✅ ' + res.message, 'success'); setTimeout(function(){ location.reload(); }, 1100); }
      else { $('#bx-clear-err').text(res.message).show(); }
    }, 'json').always(function(){ btn.disabled = false; });
});

// زرُّ الإعادة
function returnSingle(id) {
  bxReturnTsId = id;
  $('#bx-return-id').text('#' + id);
  $('#bx-return-reason').val('');
  $('#bx-return-err').hide();
  new bootstrap.Modal(document.getElementById('bxReturnModal')).show();
}

$('#bx-return-go').on('click', function () {
  var reason = $.trim($('#bx-return-reason').val());
  if (!reason) { $('#bx-return-err').text('سبب الإعادة إلزامي — «إعادة بسبب» نصا.').show(); return; }
  var btn = this; btn.disabled = true;
  $.post('hours_approval_handler.php',
    { action: 'return_to_site', timesheet_id: bxReturnTsId, reason: reason,
      csrf_token: window.csrfToken || $('input[name="csrf_token"]').val() },
    function (res) {
      if (res.success) { showToast('✅ ' + res.message, 'success'); setTimeout(function(){ location.reload(); }, 1300); }
      else { $('#bx-return-err').text(res.message).show(); }
    }, 'json').always(function(){ btn.disabled = false; });
});
</script>
<?php endif; ?>
