<?php
/**
 * Maintenance/breakdown_intake.php — استقبالُ البلاغاتِ الفنّية (RPR-W07 · MNT-04)
 * ───────────────────────────────────────────────────────────────────────────
 * **«البلاغُ يُنشأ في مركزِ البلاغاتِ ويصل الصيانةَ محالًا؛ الصيانةُ لا تنشئ
 *   بلاغًا موازيًا»** (`MNT-04` نصًّا). فهذا **سطحُ استقبالٍ لا سطحُ إنشاء**:
 *   بلا نموذجِ فتحِ بلاغٍ واحد، ووجهةُ الإنشاءِ زرٌّ إلى مركزِ البلاغات.
 *
 * ◆ **ولماذا وُجد أصلًا**: `Maintenance/breakdowns.php` صار تحويلًا إلى
 *   `Tickets/`، و`mnt_breakdown` بقي **بلا كاتبٍ ولا قارئٍ في شاشةٍ حيّة** مع
 *   أنَّ `mnt_order.breakdown_id` يشير إليه و`RiskSignalEngine::sg01` يعدُّ
 *   صفوفَه. فالبلاغُ المُحالُ إلى الصيانةِ **يقع بلا مكانٍ يُرى فيه**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Maintenance/breakdown_intake.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('mnt breakdown intake super') : ems_tenant_db();
$state = isset($_GET['state']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['state']) : '';

$rows = array(); $equip = array(); $states = array(); $ordered = array();
try {
    $opts = array('orderBy' => 'report_datetime DESC, id DESC', 'limit' => 400);
    if ($state !== '') { $opts['where'] = array('state' => $state); }
    $rows = $gate->select('mnt_breakdown', $opts);
} catch (\Throwable $t) { error_log('breakdown_intake.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_breakdown', array('columns' => array('state'), 'limit' => 400)) as $s) { $states[(string) $s['state']] = true; } }
catch (\Throwable $t) { error_log('breakdown_intake.php states: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('breakdown_intake.php equip: ' . $t->getMessage()); }
try { foreach ($gate->select('mnt_order', array('columns' => array('id', 'code', 'breakdown_id'), 'limit' => 500)) as $o) { if ((int) $o['breakdown_id'] > 0) { $ordered[(int) $o['breakdown_id']] = (string) $o['code']; } } }
catch (\Throwable $t) { error_log('breakdown_intake.php orders: ' . $t->getMessage()); }

$n = count($rows); $stopped = 0; $noOrder = 0;
foreach ($rows as $r) {
    if ((int) $r['is_stopped'] === 1) { $stopped++; }
    if (!isset($ordered[(int) $r['id']])) { $noOrder++; }
}

$page_title = 'إيكوبيشن | استقبال البلاغات الفنية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'استقبال البلاغات الفنية'; $header_icon = 'fa fa-bell';
    $header_actions = array(array('href' => '../Tickets/tickets_list.php', 'class' => 'ems-btn', 'icon' => 'fa fa-plus', 'label' => 'مركز البلاغات'));
    $header_back = array('href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر العمل');
    include('../includes/page_header.php'); ?>
    <?php  ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $n ?></div><div class="ems-stat-label">بلاغات مستلمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $stopped ?></div><div class="ems-stat-label">أوقفت المعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noOrder ?></div><div class="ems-stat-label">بلا أمر عمل بعد</div></div>
    </div>
    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w7_bi_st">حالة البلاغ</label><select name="state" id="w7_bi_st" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            <?php foreach (array_keys($states) as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= $state === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select></div>
    </form>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا بلاغات محالة إلى الصيانة', 'البلاغ ينشأ في مركز البلاغات ويصل هنا محالا. ولا ينشأ من هذه الشاشة'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>كود البلاغ</th><th>المعدة</th><th>وقت البلاغ</th><th>الجهة المبلغة</th>
            <th>أوقفت المعدة</th><th>الوصف</th><th>حالة البلاغ</th><th>أمر العمل</th><th>معرف الاستقبال</th><th>رقم البلاغ</th><th>تاريخ البلاغ</th><th>المبلغ</th><th>كود المعدة</th><th>الموقع</th><th>وصف العطل</th><th>عقدة الشجرة المبدئية</th><th>درجة الخطورة</th><th>المعدة متوقفة؟</th><th>أثر الإيقاف</th><th>قرار الاستقبال</th><th>رقم طلب الفحص المتفرع</th><th>حالة الاستقبال</th><th>شدة العطل الفني</th><th>مدة التوقف</th><th>الأثر التشغيلي</th><th>قابلية المنع</th><th>التكرار</th><th>أداء الاستجابة</th><th>سبب التأخير</th><th>سلسلة المسؤولية</th><th>وقت توقف المعدة</th><th>وقت إبلاغ المشغل</th><th>وقت استلام الصيانة</th><th>وقت بدء التشخيص</th><th>وقت انتهاء التشخيص</th><th>وقت طلب القطعة</th><th>وقت توفر القطعة</th><th>وقت وصولها للموقع</th><th>وقت حضور الفني</th><th>وقت بدء الإصلاح</th><th>وقت انتهاء الإصلاح</th><th>وقت الاختبار</th><th>وقت التصديق</th><th>وقت عودة المعدة للخدمة</th><th>إجمالي التوقف</th><th>زمن الإصلاح الفعلي</th><th>المنشئ</th><th>تاريخ الإنشاء</th><th>حالة البيانات</th><th>مرجع المصدر</th></tr></thead>
        <tbody>
        <?php if ($rows): $i = 0; foreach ($rows as $r): $i++; $bid = (int) $r['id']; ?>
            <tr><td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $r['code']) ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id'])) ?></td>
                <td><?= htmlspecialchars((string) $r['report_datetime']) ?></td>
                <td><?= htmlspecialchars((string) $r['reporter_dept']) ?></td>
                <td><?= ((int) $r['is_stopped'] === 1 ? 'نعم' : 'لا') ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $r['description'], 0, 120)) ?></small></td>
                <td><?= htmlspecialchars((string) $r['state']) ?></td>
                <td><?= isset($ordered[$bid]) ? htmlspecialchars($ordered[$bid]) : 'لم يفتح بعد' ?></td>
            <td><?= ems_sf($r, 'intake_uid') ?></td><td><?= ems_sf($r, 'report_no') ?></td><td><?= ems_sf($r, 'report_date') ?></td><td><?= ems_sf($r, 'amount') ?></td><td><?= ems_sf($r, 'equipment_code') ?></td><td><?= ems_sf($r, 'location') ?></td><td><?= ems_sf($r, 'fault_description') ?></td><td><?= ems_sf($r, 'initial_tree_node') ?></td><td><?= ems_sf($r, 'severity_degree') ?></td><td><?= ems_sf($r, 'equipment_stopped') ?></td><td><?= ems_sf($r, 'stoppage_impact') ?></td><td><?= ems_sf($r, 'intake_decision') ?></td><td><?= ems_sf($r, 'branched_inspection_req_no') ?></td><td><?= ems_sf($r, 'intake_state') ?></td><td><?= ems_sf($r, 'fault_severity') ?></td><td><?= ems_sf($r, 'downtime_duration') ?></td><td><?= ems_sf($r, 'operational_impact') ?></td><td><?= ems_sf($r, 'preventability') ?></td><td><?= ems_sf($r, 'recurrence') ?></td><td><?= ems_sf($r, 'response_performance') ?></td><td><?= ems_sf($r, 'delay_reason') ?></td><td><?= ems_sf($r, 'chain_of_responsibility') ?></td><td><?= ems_sf($r, 'equipment_stop_time') ?></td><td><?= ems_sf($r, 'operator_report_time') ?></td><td><?= ems_sf($r, 'maintenance_receipt_time') ?></td><td><?= ems_sf($r, 'diagnosis_start_time') ?></td><td><?= ems_sf($r, 'diagnosis_end_time') ?></td><td><?= ems_sf($r, 'part_request_time') ?></td><td><?= ems_sf($r, 'part_available_time') ?></td><td><?= ems_sf($r, 'site_arrival_time') ?></td><td><?= ems_sf($r, 'technician_arrival_time') ?></td><td><?= ems_sf($r, 'repair_start_time') ?></td><td><?= ems_sf($r, 'repair_end_time') ?></td><td><?= ems_sf($r, 'test_time') ?></td><td><?= ems_sf($r, 'certification_time') ?></td><td><?= ems_sf($r, 'equipment_back_in_service_time') ?></td><td><?= ems_sf($r, 'downtime_total') ?></td><td><?= ems_sf($r, 'actual_repair_time') ?></td><td><?= ems_sf($r, 'creator_name') ?></td><td><?= ems_sf($r, 'created_date') ?></td><td><?= ems_sf($r, 'data_state') ?></td><td><?= ems_sf($r, 'source_ref') ?></td></tr>
        <?php endforeach; else: ?>
            <tr><td colspan="51">لا بلاغات محالة إلى الصيانة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
    <?php  ?>
</div>
</body></html>
