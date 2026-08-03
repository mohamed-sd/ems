<?php
/**
 * user_capacities.php — صفاتي ومبدّل المساحة (H-15 · الشاشة 182)
 * ───────────────────────────────────────────────────────────────────────────
 * USR-01 §2: «مبدّلُ المساحة يعرض صفاتِه، وكلُّ مساحةٍ بنطاقها وصلاحياتها
 * منفصلةً — ولا تتسرّب بينهما بيانات» · «صفةٌ مصدرُها عقدٌ منتهٍ → تُجمَّد
 * فورًا مع بقاء السجل التاريخي للقراءة».
 *
 * كلُّ مستخدمٍ يرى **صفاتِه هو** ويبدّل بينها؛ ومديرُ الصلاحيات (15) يدير
 * الاشتقاقَ والتجميدَ ويرى السجلَّ كاملًا.
 */
require_once __DIR__ . '/includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'config.php';
require_once __DIR__ . '/app/Services/Portal/CapacityService.php';

use App\Services\Portal\CapacityService as CAP;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'user_capacities.php';
$can_view = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
// كلُّ مسجَّلٍ يرى **صفاتِه هو** ولو لم يُمنح الموديول — البوابةُ شخصية
$can_view = $can_view || $uid > 0;

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('user capacities super') : ems_tenant_db();
$redirect = function ($msg) { header("Location: user_capacities.php?msg=" . rawurlencode($msg)); exit(); };

// ── الأفعال ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['cap_action'] ?? '');

    if ($action === 'switch') {
        $r = CAP::switchTo($conn, $gate, $company_id, $uid,
            intval($_POST['capacity_id'] ?? 0), $_SESSION, $uid);
        $redirect($r['ok']
            ? ('تحوّلتَ إلى صفة «' . $_SESSION['active_capacity']['label'] . '» ✅')
            : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }

    if ($action === 'derive_dry' || $action === 'derive_apply') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = CAP::derive($conn, $gate, $company_id, $uid, $action === 'derive_dry');
        $redirect(($action === 'derive_dry' ? 'تجريبُ الاشتقاق: ' : 'الاشتقاق: ')
            . $r['created'] . ' جديدة · ' . $r['skipped'] . ' قائمة تُخطّيت'
            . ($r['declared'] ? (' · إعلانات: ' . count($r['declared'])) : '') . ' ✅');
    }

    if ($action === 'freeze') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = CAP::freeze($conn, $gate, $company_id,
            intval($_POST['capacity_id'] ?? 0), strval($_POST['reason'] ?? ''), $uid);
        $redirect($r['ok'] ? 'جُمّدت الصفةُ بسببها — والسجلُّ باقٍ للقراءة ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
}

$myCaps = CAP::activeOf($conn, $gate, $uid);
$allCaps = $can_edit ? CAP::listAll($gate, 500) : array();
$activeCapId = isset($_SESSION['active_capacity']['id']) ? intval($_SESSION['active_capacity']['id']) : 0;

$page_title = 'إيكوبيشن | صفاتي ومبدّل المساحة';
include 'inheader.php';
include 'insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'صفاتي ومبدّل المساحة'; $header_icon = 'fa fa-id-badge';
    $header_actions = array();
    include('includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#666">
            <strong>الشخصُ واحدٌ والصفاتُ متعددةٌ ومتزامنة</strong> (USR-01 §2) — كلُّ صفةٍ
            بنطاقها ودورِها منفصلةً <strong>ولا تتسرّب بينهما بيانات</strong>. الصلاحياتُ
            مرتبطةٌ <strong>بالصفة لا بالشخص</strong>: انتهاءُ العقد أو التفويض
            <strong>يجمّد الصفةَ آليًّا</strong> ويبقى سجلُّها للقراءة.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-people-arrows"></i> صفاتي</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الصفة</th><th>الدور</th><th>النطاق</th><th>المصدر</th>
                <th>السريان</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php if (!$myCaps): ?>
                <tr><td colspan="7" style="text-align:center;color:#888">
                    لا صفاتَ مشتقةً لحسابك بعدُ — الاشتقاقُ بيد مدير الصلاحيات</td></tr>
            <?php endif; ?>
            <?php foreach ($myCaps as $c): ?>
                <tr<?php echo (string)$c['state'] !== 'active' ? ' style="opacity:.55"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars(CAP::CAPACITY_AR[$c['capacity_type']] ?? $c['capacity_type']); ?></strong>
                        <?php if (intval($c['id']) === $activeCapId): ?>
                            <span class="badge badge-success">الفعّالة الآن</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['role']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['scope_type']); ?>
                        <?php if ($c['scope_id'] !== null): ?>#<?php echo intval($c['scope_id']); ?><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['source_type']); ?>
                        <?php if ($c['source_id'] !== null): ?>#<?php echo intval($c['source_id']); ?><?php endif; ?>
                        <?php if ((string)$c['source_note'] !== ''): ?>
                            <small style="color:#a15c00" title="<?php echo htmlspecialchars((string)$c['source_note']); ?>">⚠ معلَن</small>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['valid_from']); ?>
                        → <?php echo $c['valid_to'] !== null ? htmlspecialchars((string)$c['valid_to']) : 'مفتوح'; ?></td>
                    <td><?php if ((string)$c['state'] === 'active'): ?>
                            <span class="badge badge-success">نشطة</span>
                        <?php else: ?>
                            <span class="badge badge-secondary" title="<?php echo htmlspecialchars((string)$c['state_reason']); ?>">
                                مجمَّدة — للقراءة</span>
                        <?php endif; ?></td>
                    <td><?php if ((string)$c['state'] === 'active' && intval($c['id']) !== $activeCapId): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="cap_action" value="switch">
                            <input type="hidden" name="capacity_id" value="<?php echo intval($c['id']); ?>">
                            <button type="submit" class="btn-save">البس هذه الصفة</button>
                        </form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-wand-magic-sparkles"></i>
        الاشتقاق من القائم (لمدير الصلاحيات)</h5></div>
    <div class="card-body">
        <p style="color:#666">يشتقّ صفةً لكل حسابٍ نشطٍ من <strong>عقده النشط في السجل الموحّد</strong>
            (H-08) أو من <strong>ربط المورد</strong> — وما لا مصدرَ له يُعلَن
            <strong>«تفويضًا موروثًا»</strong> ولا يُلفَّق. <strong>الإعادةُ لا تكرّر صفًّا.</strong></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="post"><input type="hidden" name="cap_action" value="derive_dry">
                <button type="submit" class="btn-save"><i class="fa fa-flask"></i> جرّب (بلا كتابة)</button></form>
            <form method="post"><input type="hidden" name="cap_action" value="derive_apply">
                <button type="submit" class="btn-save"><i class="fa fa-play"></i> اشتقّ فعلًا</button></form>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> كل الصفات (<?php echo count($allCaps); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>#</th><th>كود الحساب</th><th>الشخص</th><th>الصفة</th><th>الدور</th>
                <th>النطاق</th><th>المصدر</th><th>الحال</th><th>تجميد</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">الإدارة</th>
                <th class="ems-fn-th" data-fn="1">سقف الاعتماد</th>
                <th class="ems-fn-th" data-fn="1">من تاريخ</th>
                <th class="ems-fn-th" data-fn="1">إلى تاريخ</th>
                <th class="ems-fn-th" data-fn="1">الصفة النشطة الآن</th>
                <th class="ems-fn-th" data-fn="1">آخر تبديل</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                </tr></thead>
            <tbody>
            <?php foreach ($allCaps as $c): ?>
                <tr>
                    <td><?php echo intval($c['id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['account_name'] ?? ('#' . $c['account_id']))); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['person_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars(CAP::CAPACITY_AR[$c['capacity_type']] ?? $c['capacity_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['role']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['scope_type']); ?><?php
                        if ($c['scope_id'] !== null) { echo ' #' . intval($c['scope_id']); } ?></td>
                    <td><?php echo htmlspecialchars((string)$c['source_type']); ?><?php
                        if ($c['source_id'] !== null) { echo ' #' . intval($c['source_id']); }
                        if ((string)$c['source_note'] !== '') { echo ' ⚠'; } ?></td>
                    <td><?php echo (string)$c['state'] === 'active'
                        ? "<span class='badge badge-success'>نشطة</span>"
                        : "<span class='badge badge-secondary' title='" . htmlspecialchars((string)$c['state_reason']) . "'>" . htmlspecialchars((string)$c['state']) . "</span>"; ?></td>
                    <td><?php if ((string)$c['state'] === 'active'): ?>
                        <form method="post" style="display:flex;gap:4px">
                            <input type="hidden" name="cap_action" value="freeze">
                            <input type="hidden" name="capacity_id" value="<?php echo intval($c['id']); ?>">
                            <input type="text" name="reason" placeholder="السبب *" required style="width:120px">
                            <button type="submit" class="btn-save">جمّد</button>
                        </form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
