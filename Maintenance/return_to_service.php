<?php
/**
 * Maintenance/return_to_service.php — الإقفالُ وشهادةُ إعادةِ الخدمة (RPR-W07 · MNT-14)
 * ───────────────────────────────────────────────────────────────────────────
 * **«الشهادةُ وحدَها تعيد المعدّةَ للخدمةِ وتحدّث حالتَها الفنيّةَ عند الأسطول
 *   — لا إعادةَ بغيرها»** (`MNT-14` نصًّا).
 *
 * ◆ **وما كان هنا قبلَ W07 لم يكن شهادةً — ولم يكن يعمل.** المقيسُ في الشجرةِ
 *   الحيّة: الاستعلامُ كان `SELECT mo.order_no … WHERE state IN ('Done','Executed','QA')`
 *   و**`mnt_order` لا يملك عمودًا اسمُه `order_no`**، وحالاتُه الاثنتا عشرةَ
 *   الحيّةُ عربيّةٌ حرّةٌ ليس فيها واحدةٌ من الثلاث. فـ`mysqli_query` تعيد
 *   `false`، و`if ($r) while` تُبقي القائمةَ فارغةً، والشاشةُ تعرض «لا أوامر
 *   منجزة» **دائمًا ومهما كان في القاعدة**. سطحٌ حيٌّ يُصيَّر ولا يعرض شيئًا.
 *   و«شهادةُ الجاهزية» كانت **نصًّا حرًّا في عمودٍ واحد** بلا رقمٍ ولا صلاحيةٍ
 *   ولا مُعتمِدٍ ولا اختبارٍ موثَّق — فـ`MNT-15` الذي يقيس «التكرارَ خلالَ
 *   صلاحيةِ الشهادة» كان **بلا صلاحيةٍ يقيسها**.
 *
 * ◆ **و`DEC-OPEN-12` يحكم مَن تلزمه الشهادةُ ومَن يعتمدها**: البسيطُ لا شهادةَ
 *   له (`CERT_NOT_REQUIRED_FOR_MINOR`)، والحرِجُ للسلامةِ لا يعتمده إلّا مخوَّلٌ
 *   فنّيٌّ لا المشغّلُ (`SAFETY_CRITICAL_NEEDS_TECHNICAL_AUTHORITY`)، **ومَن
 *   أنشأ الشهادةَ لا يعتمدها** (`SOD_SELF_APPROVAL`).
 *
 * ◆ **وصلاحيةُ الشهادةِ من `repair01_w7_thresholds`** لا من رقمٍ في الشيفرة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';
require_once __DIR__ . '/../app/Services/Maintenance/MaintenanceCycleService.php';

use App\Services\Maintenance\MaintenanceCycleService as MCS;

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

/* حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب (RF-02 · CS-01) — قبلَ الكتابةِ لا بعدها */
$pp = check_page_permissions($conn, 'Maintenance/return_to_service.php');
if (!$pp['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('mnt return cert super') : ems_tenant_db();
MCS::setEventConnection($conn);
MCS::setThresholdConnection($conn);

$msg = ''; $msgKind = 'info';

/* ── إصدارُ شهادةٍ لأمرٍ منجَزٍ فنّيًّا ─────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['issue_order'])) {
    $r = MCS::issueCert($gate, (int) $_POST['issue_order'], array(
        'cert_no'             => trim((string) ($_POST['cert_no'] ?? '')),
        'test_performed'      => trim((string) ($_POST['test_performed'] ?? '')),
        'test_result'         => (string) ($_POST['test_result'] ?? 'pass'),
        'meter_at_close'      => (string) ($_POST['meter_at_close'] ?? ''),
        'new_readiness_state' => (string) ($_POST['new_readiness_state'] ?? 'operational'),
        'operating_limits'    => trim((string) ($_POST['operating_limits'] ?? '')),
        'created_by'          => $uid,
        'src_ref'             => 'Maintenance/return_to_service.php',
    ));
    $msg = $r['ok'] ? ('صدرت الشهادة بانتظار الاعتماد. رقم ' . (int) $r['cert_id']) : ('تعذر الإصدار: ' . $r['reason']);
    $msgKind = $r['ok'] ? 'success' : 'danger';
}

/* ── اعتمادُ الشهادة — وهنا وحدَها تعود المعدّة ────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['approve_cert'])) {
    $r = MCS::approveCert($gate, (int) $_POST['approve_cert'], $uid, (string) ($_POST['signer_kind'] ?? ''));
    $msg = $r['ok']
         ? ('اعتمدت الشهادة وعادت المعدة. الصلاحية حتى ' . $r['valid_until'] . '. الجاهزية ' . ems_w7_ar($r['readiness'], $conn))
         : ('تعذر الاعتماد: ' . $r['reason']);
    $msgKind = $r['ok'] ? 'success' : 'danger';
}

/* ── الأوامرُ المنجَزةُ فنّيًّا التي يوجب تصنيفُها شهادةً ولم تصدر بعد ── */
$pending = array(); $certs = array(); $equip = array(); $orders = array();
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('return_to_service equip: ' . $t->getMessage()); }
try {
    foreach ($gate->select('mnt_order', array('orderBy' => 'id DESC', 'limit' => 400)) as $o) {
        $orders[(int) $o['id']] = (string) $o['code'];
        if ((int) $o['is_deleted'] === 1) { continue; }
        if (!in_array((string) $o['safety_severity'], array('major', 'safety_critical'), true)) { continue; }
        if ((string) $o['work_end'] === '' || $o['work_end'] === null) { continue; }
        if ((int) $o['w7_cert_id'] > 0) { continue; }
        $pending[] = $o;
    }
} catch (\Throwable $t) { error_log('return_to_service orders: ' . $t->getMessage()); }
try { $certs = $gate->select('mnt_return_cert', array('orderBy' => 'id DESC', 'limit' => 200)); }
catch (\Throwable $t) { error_log('return_to_service certs: ' . $t->getMessage()); }

$approved = 0; $waiting = 0;
foreach ($certs as $c) {
    if ((string) $c['state'] === 'approved') { $approved++; }
    elseif ((string) $c['state'] === 'submitted') { $waiting++; }
}

$page_title = 'إيكوبيشن | الإقفال وشهادة إعادة الخدمة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'الإقفال وشهادة إعادة الخدمة'; $header_icon = 'fa fa-check-circle'; $header_actions = array();
    $header_back = array('href' => 'orders.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'أوامر العمل');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($pending) ?></div><div class="ems-stat-label">أوامر تنتظر الشهادة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $waiting ?></div><div class="ems-stat-label">شهادات تنتظر الاعتماد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $approved ?></div><div class="ems-stat-label">شهادات معتمدة</div></div>
    </div>
    <?php if ($msg !== ''): ?><div class="alert alert-<?= htmlspecialchars($msgKind) ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أوامر تنتظر شهادة إعادة خدمة', 'الشهادة تصدر لأمر أنجز فنيا وتصنيفه فوق البسيط. والبسيط لا شهادة له'); ?>

    <h3 class="ems-section-title">أوامر أنجزت فنيا وتنتظر الشهادة</h3>
    <div class="table-wrap"><table class="data-table" data-no-dt>
        <thead><tr><th>الأمر</th><th>المعدة</th><th>خطورة السلامة</th><th>الأثر التشغيلي</th><th>حالة المنع</th>
            <th>رقم الشهادة</th><th>الاختبار المنفذ</th><th>النتيجة</th><th>قراءة العداد</th>
            <th>الحالة الفنية الجديدة</th><th>قيود التشغيل</th><th>إجراء</th></tr></thead>
        <tbody>
        <?php if ($pending): foreach ($pending as $o): ?>
            <tr>
                <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="issue_order" value="<?= (int) $o['id'] ?>">
                <td><?= htmlspecialchars((string) $o['code']) ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $o['equipment_id']]) ? $equip[(int) $o['equipment_id']] : ('#' . (int) $o['equipment_id'])) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $o['safety_severity'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $o['ops_impact'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $o['lockout_state'], $conn)) ?></td>
                <td><input type="text" name="cert_no" class="form-control form-control-sm" required aria-label="رقم الشهادة"></td>
                <td><input type="text" name="test_performed" class="form-control form-control-sm" required aria-label="الاختبار المنفذ"></td>
                <td><select name="test_result" aria-label="نتيجة الاختبار">
                    <option value="pass">ناجح</option><option value="conditional">ناجح بشرط</option><option value="fail">مخفق</option>
                </select></td>
                <td><input type="number" step="0.01" name="meter_at_close" class="form-control form-control-sm" aria-label="قراءة العداد"></td>
                <td><select name="new_readiness_state" aria-label="الحالة الفنية الجديدة">
                    <option value="operational">تعمل</option>
                    <option value="operational_restricted">تعمل بقيد</option>
                    <option value="ready_after_approval">جاهزة بعد الاعتماد</option>
                </select></td>
                <td><input type="text" name="operating_limits" class="form-control form-control-sm" aria-label="قيود التشغيل"></td>
                <td><button class="action-btn" type="submit"><i class="fa fa-file-signature"></i> أصدر الشهادة</button></td>
                </form>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="12">لا أوامر تنتظر شهادة إعادة خدمة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <h3 class="ems-section-title">شهادات إعادة الخدمة</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th><th>رقم الشهادة</th><th>الأمر</th><th>المعدة</th><th>خطورة السلامة</th>
            <th>تاريخ الإنجاز الفني</th><th>الاختبار المنفذ</th><th>النتيجة</th><th>ساعات التوقف</th>
            <th>التكلفة الفعلية</th><th>الصلاحية</th><th>الحالة الفنية</th><th>قيود التشغيل</th>
            <th>حالة الشهادة</th><th>قاعدة الحالة</th><th>إجراء</th></tr></thead>
        <tbody>
        <?php if ($certs): $i = 0; foreach ($certs as $c): $i++; ?>
            <tr><td><?= $i ?></td>
                <td><?= htmlspecialchars((string) $c['cert_no']) ?></td>
                <td><?= htmlspecialchars(isset($orders[(int) $c['order_id']]) ? $orders[(int) $c['order_id']] : ('#' . (int) $c['order_id'])) ?></td>
                <td><?= htmlspecialchars(isset($equip[(int) $c['equipment_id']]) ? $equip[(int) $c['equipment_id']] : ('#' . (int) $c['equipment_id'])) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $c['safety_severity'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $c['tech_complete_date']) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $c['test_performed'], 0, 100)) ?></small></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $c['test_result'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $c['downtime_hours']) ?></td>
                <td><?= htmlspecialchars((string) $c['actual_cost']) ?></td>
                <td><?= htmlspecialchars((string) $c['valid_until']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $c['new_readiness_state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars(mb_substr((string) $c['operating_limits'], 0, 80)) ?></small></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $c['state'], $conn)) ?></td>
                <td><small><?= htmlspecialchars((string) $c['state_rule']) ?></small></td>
                <td><?php if ((string) $c['state'] === 'submitted'): ?>
                    <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="approve_cert" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="signer_kind" value="<?= htmlspecialchars((string) $c['signer_kind']) ?>">
                    <button class="action-btn" type="submit"><i class="fa fa-stamp"></i> اعتمد وأعد للخدمة</button>
                    </form>
                <?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="16">لا شهادات إعادة خدمة بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
</body></html>
