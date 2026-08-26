<?php
/**
 * Procurement/proc_track_policy.php — سياسة تتبع الأصناف (RPR-W09 · DEC-OPEN-15)
 * ───────────────────────────────────────────────────────────────────────────
 * **«المرونةُ يجب أن تكون في الإعداداتِ لا في الكود»** (‏قرارُ المالك ㉗ نصًّا):
 * «لا أريد أن يحتاج المبرمجُ لتعديلِ البرنامجِ كلّما قرّرنا جعلَ الصلاحيةِ
 * إلزاميّةً للبطاريات». فهذا السطحُ **هو موضعُ القرارِ** لا الشيفرة.
 *
 * ◆ **مستويان لا مستوى**: الفئةُ تعطي `Default` والصنفُ يخصّصه — و`النطاق`
 *   في كلِّ صفٍّ يقول أيُّهما حكم. والتخصيصُ **يغلب الافتراض**.
 *
 * ◆ **وثلاثُ درجاتٍ لا نعم ولا**: `OFF` لا يُطلَب · `OPTIONAL` يُدخَل إن توفّر
 *   **ولا يمنع** · `REQUIRED` لا تكتمل العمليّةُ دونه. والمرحلةُ الحاليّةُ
 *   وضعُ انتقالٍ: الاتّجاهُ العامُّ `OPTIONAL` لا `REQUIRED`.
 *
 * ◆ **والنسخةُ مؤرَّخةٌ بلا أثرٍ رجعيّ**: حركةٌ وقعت قبل تشديدِ السياسةِ
 *   تُحاسَب بسياستِها هي — و`سريان من` و`سريان إلى` عمودانِ يُريان ذلك.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/proc_track_policy.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('track_policy super') : ems_tenant_db();
$pick = isset($_GET['scope']) ? preg_replace('/[^A-Z]/', '', (string) $_GET['scope']) : '';

$rows = array(); $items = array();
try {
    $opts = array('orderBy' => 'scope_kind, scope_key, version DESC', 'limit' => 500);
    if ($pick !== '') { $opts['where'] = array('scope_kind' => $pick); }
    $rows = $gate->select('proc_track_policy', $opts);
} catch (\Throwable $t) { error_log('track_policy list: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_item', array('columns' => array('id', 'name', 'category'), 'limit' => 900)) as $i) { $items[(int) $i['id']] = $i; } }
catch (\Throwable $t) { error_log('track_policy items: ' . $t->getMessage()); }

/* ◆ **ساعةُ القاعدةِ لا ساعةُ PHP**: السريانُ يُقارَن بتواريخَ خزّنَتها
     القاعدةُ، فمرجعُ اليومِ منها — وهذا يرفع دَينَ `VT-07` أيضًا. */
$__d = $conn->query('SELECT CURDATE()');
$today = $__d ? (string) $__d->fetch_row()[0] : '';
$catN = 0; $itemN = 0; $liveN = 0; $strictN = 0;
foreach ($rows as $r) {
    if ((string) $r['scope_kind'] === 'CATEGORY') { $catN++; } else { $itemN++; }
    $to = (string) $r['effective_to'];
    if ((string) $r['effective_from'] <= $today && ($to === '' || $to >= $today)) { $liveN++; }
    foreach (array('lot', 'serial', 'mfg_date', 'expiry', 'warranty') as $k) {
        if ((string) $r[$k] === 'REQUIRED') { $strictN++; break; }
    }
}

$page_title = 'إيكوبيشن | سياسة تتبع الأصناف';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'سياسة تتبع الأصناف'; $header_icon = 'fa fa-sliders'; $header_actions = array();
    $header_back = array('href' => 'items_proc.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'دليل الأصناف');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $catN ?></div><div class="ems-stat-label">افتراض على فئة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $itemN ?></div><div class="ems-stat-label">تخصيص على صنف</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $liveN ?></div><div class="ems-stat-label">سارية اليوم</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $strictN ?></div><div class="ems-stat-label">فيها خاصية إلزامية</div></div>
    </div>

    <form method="get" action="" class="ems-filters">
        <div class="field"><label for="w9_tp_sc">مستوى السياسة</label><select name="scope" id="w9_tp_sc" onchange="this.form.submit()">
            <option value="">الكل</option>
            <option value="CATEGORY" <?= $pick === 'CATEGORY' ? 'selected' : '' ?>>افتراض الفئة</option>
            <option value="ITEM" <?= $pick === 'ITEM' ? 'selected' : '' ?>>تخصيص الصنف</option>
        </select></div>
    </form>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا سياسات تتبع مسجلة',
        'الفئة تعطي الافتراض والصنف يخصصه. والمرحلة الحالية اتجاهها اختياري لا إلزامي'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>المستوى</th><th>الفئة أو الصنف</th><th>النسخة</th><th>سريان من</th><th>سريان إلى</th><th>الدفعة</th><th>التسلسلي</th><th>التصنيع</th><th>الصلاحية</th><th>الضمان</th><th>إنفاذ المنتهي</th><th>ترتيب الصرف</th><th>إعادة التأهيل</th><th>سلطة التجاوز</th><th>سبب الإلزام</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r):
            $key = (string) $r['scope_key'];
            if ((string) $r['scope_kind'] === 'ITEM' && isset($items[(int) $key])) { $key = (string) $items[(int) $key]['name']; }
            $to = (string) $r['effective_to'];
            $isLive = ((string) $r['effective_from'] <= $today && ($to === '' || $to >= $today)); ?>
            <tr>
                <td><?= ((string) $r['scope_kind'] === 'CATEGORY' ? 'افتراض فئة' : 'تخصيص صنف') ?></td>
                <td><?= htmlspecialchars($key) ?></td>
                <td><?= (int) $r['version'] ?><?= $isLive ? ' (سارية)' : '' ?></td>
                <td><?= htmlspecialchars((string) $r['effective_from']) ?></td>
                <td><?= htmlspecialchars($to !== '' ? $to : 'مفتوحة') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['lot'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['serial'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['mfg_date'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['expiry'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['warranty'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['expiry_enforce'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['issue_policy'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $r['requalify'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $r['override_authority']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['strict_why']) ?></small></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <h3 class="ems-section-title">الدرجة المحلولة لكل صنف</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>الفئة</th><th>الدفعة</th><th>التسلسلي</th><th>التصنيع</th><th>الصلاحية</th><th>الضمان</th><th>إنفاذ المنتهي</th><th>ترتيب الصرف</th><th>مصدر الحكم</th><th>النسخة</th></tr></thead>
        <tbody>
        <?php
        $solved = array();
        try { $solved = $gate->select('proc_item', array('orderBy' => 'category, id', 'limit' => 400)); }
        catch (\Throwable $t) { error_log('track_policy solved: ' . $t->getMessage()); }
        if ($solved): foreach ($solved as $it): ?>
            <tr>
                <td><?= htmlspecialchars((string) $it['name']) ?></td>
                <td><?= htmlspecialchars((string) $it['category']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['track_lot_level'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['track_serial_level'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['track_mfg_level'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['track_expiry_level'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['track_warranty_level'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['expiry_enforce'], $conn)) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $it['issue_policy'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $it['policy_scope'] === 'ITEM' ? 'تخصيص الصنف'
                        : ((string) $it['policy_scope'] === 'CATEGORY' ? 'افتراض الفئة' : 'لا سياسة')) ?></td>
                <td><?= (int) $it['policy_version'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
