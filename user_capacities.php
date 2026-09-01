<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-057 · SCN-058 · SCN-061 · SCN-062 · SCN-063 · SCN-064 · SCN-065 · SCN-066 · SCN-067 · SCN-068 · SCN-069 · SCN-070 · SCN-071 · SCN-072 · SCN-073 · SCN-074 · SCN-075
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
require_once __DIR__ . '/includes/w14_grid.php';
require_once __DIR__ . '/app/Services/Portal/CapacityService.php';

use App\Services\Portal\CapacityService as CAP;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    /* INJ-0492: التحويلُ إلى شاشةِ الدخول — والوميضُ لا يعبرها بالضرورة.
       فالرسالةُ **برمزٍ** يملك `login.php` نصَّه، لا بنصٍّ في الرابط. */
    header('Location: login.php?m=nocompany');
    exit();
}

$MODULE_CODE = 'user_capacities.php';
// ── صلاحيةٌ من المصدرِ الواحدِ (RPR-03 §٦): القراءةُ المستقلّةُ كانت تقفز
//    طبقةَ قوالبِ GOV-AUTH-01 فيفترق المساران — check_page_permissions يمرّ
//    بها كلِّها، وcan_edit يبقى بوابةَ الاشتقاقِ والتجميدِ نفسَها ─────────────
require_once __DIR__ . '/includes/permissions_helper.php';
$can_view = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_edit = true;
} else {
    $pp = check_page_permissions($conn, $MODULE_CODE);
    $can_view = !empty($pp['can_view']);
    $can_edit = !empty($pp['can_edit']);
}
// كلُّ مسجَّلٍ يرى **صفاتِه هو** ولو لم يُمنح الموديول — البوابةُ شخصية
$can_view = $can_view || $uid > 0;

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('user capacities super') : ems_tenant_db();
/* INJ-0492: الرسالةُ إلى الجلسةِ لا إلى الرابط — فلا يُطبع نصٌّ من `$_GET` */
$redirect = function ($msg) { ems_flash_set($msg); header('Location: user_capacities.php'); exit(); };

// ── الأفعال ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strval($_POST['cap_action'] ?? '');

    if ($action === 'switch') {
        $r = CAP::switchTo($conn, $gate, $company_id, $uid,
            intval($_POST['capacity_id'] ?? 0), $_SESSION, $uid);
        $redirect($r['ok']
            ? ('تحولت إلى صفة «' . $_SESSION['active_capacity']['label'] . '» ✅')
            : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }

    if ($action === 'derive_dry' || $action === 'derive_apply') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = CAP::derive($conn, $gate, $company_id, $uid, $action === 'derive_dry');
        $redirect(($action === 'derive_dry' ? 'تجريب الاشتقاق: ' : 'الاشتقاق: ')
            . $r['created'] . ' جديدة · ' . $r['skipped'] . ' قائمة تخطيت'
            . ($r['declared'] ? (' · إعلانات: ' . count($r['declared'])) : '') . ' ✅');
    }

    if ($action === 'freeze') {
        if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
        $r = CAP::freeze($conn, $gate, $company_id,
            intval($_POST['capacity_id'] ?? 0), strval($_POST['reason'] ?? ''), $uid);
        $redirect($r['ok'] ? 'جمدت الصفة بسببها — والسجل باق للقراءة ✅'
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
}

$myCaps = CAP::activeOf($conn, $gate, $uid);
$allCaps = $can_edit ? CAP::listAll($gate, 500) : array();
$activeCapId = isset($_SESSION['active_capacity']['id']) ? intval($_SESSION['active_capacity']['id']) : 0;

$page_title = 'إيكوبيشن | صفاتي ومبدل المساحة';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
// (الملفُّ في جذرِ ems — فمسارُ includes مباشرٌ لا بـ../ التي كانت تشير خارجَ الجذر)
require_once __DIR__ . '/includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include 'inheader.php';
include 'insidebar.php';
require_once __DIR__ . '/includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (§2·2-③).
         ⛔ ولا فلترَ يُخترَع: `ems_filter_box` يشتقُّ ضوابطَه من رؤوسِ
         الجدولِ المُصيَّرِ نفسِه، ويخفي نفسَه إن غاب الجدول. */
    require_once __DIR__ . '/includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_my_user_capacities')); ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_my_user_capacities
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف الصفة' => 'g37',
            'الصفة' => 'g38',
            'مصدرها' => 'g39',
            'النطاق' => 'g40',
            'سارية من' => 'g41',
            'إلى' => 'g42',
            'نشطة الآن؟' => 'g43',
            'آخر تبديل' => 'g44',
            'مرجع التفويض عند الإنابة' => 'g45',
            'حالة الصفة' => 'g46',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('my_user_capacities');
        echo ems_w14_grid('emsList_my_user_capacities', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الصفات الوظيفية والتبديل بينها'); /* /GUIDE_COLS */ ?>
    </div></div></div>

    <?php
    $header_title = 'صفاتي ومبدل المساحة'; $header_icon = 'fa fa-id-badge';
    $header_actions = array();
    include('includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }

    /* UXW-01 ⑨: حزمةُ الحالاتِ الدنيا — مخفيةٌ افتراضًا ويُظهرها منطقُ الشاشةِ عند حالِها */
    echo ems_states_bundle('لا صفات مشتقة لحسابك بعد',
        'الاشتقاق بيد مدير الصلاحيات — الصفة تشتق من عقدك أو ربطك لا تمنح يدويا');
    ?>

    <div class="card"><div class="card-body">
        <p class="uc-note">
            <strong>الشخص واحد والصفات متعددة ومتزامنة</strong> (USR-01 §2) — كل صفة
            بنطاقها ودورها منفصلة <strong>ولا تتسرب بينهما بيانات</strong>. الصلاحيات
            مرتبطة <strong>بالصفة لا بالشخص</strong>: انتهاء العقد أو التفويض
            <strong>يجمد الصفة آليا</strong> ويبقى سجلها للقراءة.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-people-arrows"></i> صفاتي</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap uc-w100" data-no-dt="1">
            <thead><tr><th>الصفة</th><th>الدور</th><th>النطاق</th><th>المصدر</th>
                <th>السريان</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php if (!$myCaps): ?>
                <tr><td colspan="7" class="uc-empty-cell">
                    لا صفات مشتقة لحسابك بعد — الاشتقاق بيد مدير الصلاحيات</td></tr>
            <?php endif; ?>
            <?php foreach ($myCaps as $c): ?>
                <tr<?php echo (string)$c['state'] !== 'active' ? ' class="uc-frozen"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars(CAP::CAPACITY_AR[$c['capacity_type']] ?? $c['capacity_type']); ?></strong>
                        <?php if (intval($c['id']) === $activeCapId): ?>
                            <span class="badge badge-success">الفعالة الآن</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['role']); ?></td>
                    <td><?php echo htmlspecialchars((string)$c['scope_type']); ?>
                        <?php if ($c['scope_id'] !== null): ?>#<?php echo intval($c['scope_id']); ?><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['source_type']); ?>
                        <?php if ($c['source_id'] !== null): ?>#<?php echo intval($c['source_id']); ?><?php endif; ?>
                        <?php if ((string)$c['source_note'] !== ''): ?>
                            <small class="uc-declared" title="<?php echo htmlspecialchars((string)$c['source_note']); ?>">⚠ معلن</small>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$c['valid_from']); ?>
                        → <?php echo $c['valid_to'] !== null ? htmlspecialchars((string)$c['valid_to']) : 'مفتوح'; ?></td>
                    <td><?php if ((string)$c['state'] === 'active'): ?>
                            <span class="badge badge-success">نشطة</span>
                        <?php else: ?>
                            <span class="badge badge-secondary" title="<?php echo htmlspecialchars((string)$c['state_reason']); ?>">
                                مجمدة — للقراءة</span>
                        <?php endif; ?></td>
                    <td><?php if ((string)$c['state'] === 'active' && intval($c['id']) !== $activeCapId): ?>
                        <form method="post" class="uc-inline">
                            <input type="hidden" name="cap_action" value="switch">
                            <input type="hidden" name="capacity_id" value="<?php echo intval($c['id']); ?>">
                            <button type="submit" class="btn-primary">البس هذه الصفة</button>
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
        <p class="uc-note">يشتق صفة لكل حساب نشط من <strong>عقده النشط في السجل الموحد</strong>
            (H-08) أو من <strong>ربط المورد</strong> — وما لا مصدر له يعلن
            <strong>«تفويضا موروثا»</strong> ولا يلفق. <strong>الإعادة لا تكرر صفا.</strong></p>
        <div class="uc-actions-row">
            <form method="post"><input type="hidden" name="cap_action" value="derive_dry">
                <button type="submit" class="btn-primary"><i class="fa fa-flask"></i> جرب (بلا كتابة)</button></form>
            <form method="post"><input type="hidden" name="cap_action" value="derive_apply">
                <button type="submit" class="btn-primary"><i class="fa fa-play"></i> اشتق فعلا</button></form>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> كل الصفات (<?php echo count($allCaps); ?>)</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap uc-w100">
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
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
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
                        <form method="post" class="uc-freeze-form">
                            <input type="hidden" name="cap_action" value="freeze">
                            <input type="hidden" name="capacity_id" value="<?php echo intval($c['id']); ?>">
                            <input type="text" name="reason" placeholder="السبب *" required class="uc-reason-input" aria-label="سبب التجميد">
                            <button type="submit" class="btn-primary">جمد</button>
                        </form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<script src="includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
