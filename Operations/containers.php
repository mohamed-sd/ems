<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Operations/containers.php — حاوياتُ العقود (H-01 المرحلة ② · OPM-01 §4)
 * ═══════════════════════════════════════════════════════════════════════════
 * شجرةُ المستويات الأربعة بأرصدتها: **سقفٌ · موزَّعٌ · مستهلَكٌ · متبقٍّ** — وكلُّ
 * رقمٍ منها مصدرُه سجلٌّ يُنقر إليه، ولا رقمَ يُكتب هنا بيدٍ إلا الحصةَ نفسَها.
 *
 * ── الحارسُ ليس هنا ───────────────────────────────────────────────────────
 * قيدُ Σ **بنيويٌّ في القاعدة** (`ck_container_alloc`)، والخدمةُ تكتب داخل معاملة
 * فيرفض المحرّكُ التجاوز. وهذه الشاشةُ **تترجم الرفضَ** إلى رسالةٍ تسمّي المتاحَ
 * والمطلوب — لا «حدث خطأ». (درسُ E-08-أ: الردُّ الخادميُّ لا يصل المستخدم تلقائيًّا.)
 *
 * ── وسمُ «مشتقّة» ظاهرٌ لا مخبوء ──────────────────────────────────────────
 * الحصةُ **قرارٌ تجاريٌّ لا اشتقاقٌ حسابي**، فما استُنتج من صفوف التشغيل يُعرض
 * بشارةٍ صريحةٍ وبملاحظته («من أين اشتُقّ») حتى تُقرّه الإدارة. ولا يُقدَّم رقمٌ
 * مشتقٌّ كأنه متفقٌ عليه.
 *
 * **POST عاديٌّ لا AJAX** — فلا حاجةَ لتسجيلٍ في `action_guard` (نمطُ لوحة الإسناد).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

include '../config.php';
require_once __DIR__ . '/../app/Services/Operations/OperationalTransformService.php';
require_once __DIR__ . '/../app/Services/Contract/ContractStateMachine.php';

use App\Services\Operations\OperationalTransformService as OTS;
use App\Services\Contract\ContractStateMachine as CSM;

$role       = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super   = ($role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة', 'GOV-INFO-200', ''); exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها، وغيابُها منعٌ لا إذن ─────────────────────
$MODULE = 'Operations/containers.php';
$can_view = $can_manage = false;
if ($is_super) { $can_view = $can_manage = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($role);
    $st->bind_param('si', $MODULE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view   = (intval($row['can_view']) === 1);
        $can_manage = (intval($row['can_add']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض حاويات العقود ❌', 'GOV-PERM-403', ''); exit();
}

$gate = ems_tenant_db();
function cnt_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function cnt_n($v) { return number_format((float) $v, 2); }

if (empty($_SESSION['cnt_csrf'])) { $_SESSION['cnt_csrf'] = bin2hex(openssl_random_pseudo_bytes(32)); }
$CSRF = $_SESSION['cnt_csrf'];
function cnt_csrf_ok() {
    return isset($_POST['cnt_csrf']) && isset($_SESSION['cnt_csrf'])
        && hash_equals($_SESSION['cnt_csrf'], (string) $_POST['cnt_csrf']);
}
$contract = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
function cnt_back($msg, $c) {
    ems_gov_redirect('Location: containers.php?contract=' . intval($c) . '&msg=' . rawurlencode($msg)); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// الأفعال — كلُّها تمرّ بالخدمة، ولا منطقَ حراسةٍ هنا
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cnt_action'])) {
    if (!$can_manage) { cnt_back('إدارة الحاويات صلاحية إدارة التشغيل ❌', $contract); }
    if (!cnt_csrf_ok()) { cnt_back('رمز الحماية غير صالح ❌', $contract); }
    $act = strval($_POST['cnt_action']);
    $cid = intval($_POST['contract_id'] ?? $contract);

    if ($act === 'generate_main') {
        $r = OTS::generateMain($conn, $gate, $company_id, $cid, $uid);
        if (empty($r['ok'])) { cnt_back($r['reason'] . ' ❌', $cid); }
        $m = 'الحاويات الرئيسية: ' . $r['created'] . ' جديدة · ' . $r['existing'] . ' قائمة';
        if (!empty($r['skipped'])) { $m .= ' — ' . implode(' · ', $r['skipped']); }
        cnt_back($m . ' ✅', $cid);

    } elseif ($act === 'derive') {
        $r = OTS::deriveFromOperations($conn, $gate, $company_id, $cid, $uid);
        if (empty($r['ok'])) { cnt_back($r['reason'] . ' ❌', $cid); }
        $m = 'تولدت حاويات **مشتقة** من صفوف التشغيل: '
           . $r['created']['supplier'] . ' مورد · ' . $r['created']['equipment'] . ' معدة · '
           . $r['created']['operator'] . ' مشغّل — وتنتظر إقرارَ الإدارة';
        if (!empty($r['unmatched'])) { $m .= ' · ' . count($r['unmatched']) . ' بندا بلا حاوية (انظر تقرير المطابقة)'; }
        cnt_back($m . ' ✅', $cid);

    } elseif ($act === 'allocate') {
        $r = OTS::allocate($conn, $gate, $company_id, intval($_POST['parent_id'] ?? 0),
            strval($_POST['child_level'] ?? ''), intval($_POST['child_ref'] ?? 0),
            $_POST['qty'] ?? 0, array(
                'role_kind' => strval($_POST['role_kind'] ?? ''),
                'shift_no'  => $_POST['shift_no'] ?? '',
                'actor'     => $uid,
                'origin'    => 'عقد',
                'origin_note' => 'تخصيص يدوي بقرار الإدارة',
            ));
        // ★ رسالةُ الرفض تسمّي المتاحَ والمطلوب — لا «حدث خطأ»
        cnt_back($r['ok'] ? 'خصصت الحصة ✅' : ($r['reason'] . ' ❌'), $cid);

    } elseif ($act === 'acknowledge') {
        $r = OTS::acknowledge($gate, $company_id, intval($_POST['container_id'] ?? 0), $uid);
        cnt_back($r['ok'] ? ('أقرت الحصة المشتقة — ورفع عنها الوسم ✅')
                          : ($r['reason'] . ' ❌'), $cid);

    } elseif ($act === 'rotation') {
        // H-01-③: مقبضُ الدورات — رابطُ «سجّل دورةَ تناوبه» كان طريقًا مسدودًا.
        // الميدانُ يسجّل دوراتِه الحقيقية هنا — لا بذرَ آليًّا (اختراعُ الأنماط تلفيق).
        $rcId = intval($_POST['container_id'] ?? 0);
        $on   = intval($_POST['cycle_on_days'] ?? 0);
        $off  = intval($_POST['cycle_off_days'] ?? 0);
        $start = strval($_POST['cycle_start'] ?? '');
        if ($on <= 0) { cnt_back('أيام العمل في الدورة موجبة إلزاما ❌', $cid); }
        if ($off < 0) { cnt_back('أيام الراحة لا تكون سالبة ❌', $cid); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) { cnt_back('تاريخ بدء الدورة إلزامي ❌', $cid); }
        $rc = null;
        try { $rc = $gate->selectOne('op_containers', array('where' => array('id' => $rcId))); }
        catch (\Throwable $t) { $rc = null; }
        if (!$rc) { cnt_back('الحاوية غير موجودة في نطاقك ❌', $cid); }
        if ((string) $rc['level'] !== 'مشغّل') { cnt_back('الدورة تسجل على حاوية مشغل حصرا ❌', $cid); }
        try {
            $gate->insert('operator_rotations', array(
                'container_id'         => $rcId,
                'operator_employee_id' => intval($rc['operator_employee_id']),
                'cycle_on_days'        => $on,
                'cycle_off_days'       => $off,
                'cycle_start'          => $start,
                'shift_no'             => intval($_POST['shift_no'] ?? 0) ?: null,
                'note'                 => mb_substr(trim(strval($_POST['note'] ?? '')), 0, 200) ?: null,
                'created_by'           => $uid ?: null,
            ));
        } catch (\Throwable $t) {
            error_log('container rotation: ' . $t->getMessage());
            cnt_back('تعذر تسجيل الدورة ❌', $cid);
        }
        cnt_back('سجلت دورة التناوب — وزال سبب «مشغل بلا دورة» عن حاويته ✅', $cid);

    } elseif ($act === 'swap') {
        // H-04: الاستبدالُ صار **نقلًا ذريًّا** لا سجلًّا وصفيًّا — «تُجمَّد
        // الخارجةُ عند رصيدها وتُفتح البديلةُ بالمتبقي» والسجلُّ داخل معاملته.
        require_once __DIR__ . '/../app/Services/Operations/RotationSwapService.php';
        $r = \App\Services\Operations\RotationSwapService::swap(
            $conn, $gate, $company_id,
            intval($_POST['container_id'] ?? 0),
            intval($_POST['in_ref'] ?? 0),
            strval($_POST['reason'] ?? ''),
            strval($_POST['doc_ref'] ?? ''),
            $uid,
            strval($_POST['effective_from'] ?? date('Y-m-d')));
        cnt_back($r['ok']
            ? ('نقل الرصيد ذريا: ' . $r['moved_qty'] . ' إلى الحاوية #' . intval($r['to_container_id'])
               . ' — والخارجة مجمدة عند رصيدها ✅')
            : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')'), $cid);
    }
    cnt_back('إجراء غير معروف ❌', $cid);
}

// ══════════════════════════════════════════════════════════════════════════════
// القراءة
// ══════════════════════════════════════════════════════════════════════════════
$contracts = array();
try {
    $contracts = $gate->scopedQuery(
        array('scope' => array('c' => 'contracts'), 'enrich' => array('p' => 'project')),
        "SELECT c.id, c.contract_status, p.name AS project_name
           FROM contracts c LEFT JOIN project p ON p.id = c.project_id
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0
          ORDER BY c.id", array());
} catch (\Throwable $t) { error_log('containers contracts: ' . $t->getMessage()); }

$tree = ($contract > 0) ? OTS::treeOf($gate, $contract) : array();
$recon = OTS::reconciliation($gate, $company_id, $contract);

// شجرةٌ مفهرسةٌ بأبيها للعرض المتدرّج
$byParent = array(); $mains = array();
foreach ($tree as $n) {
    if ($n['parent_id'] === null) { $mains[] = $n; }
    else { $byParent[(int) $n['parent_id']][] = $n; }
}
$LEVEL_NEXT = array('رئيسية' => 'مورد', 'مورد' => 'معدة', 'معدة' => 'مشغّل', 'مشغّل' => null);
$ROLES = array('مورد' => array(), 'معدة' => array('أساسية', 'احتياطية'),
               'مشغّل' => array('أساسي', 'بديل أول', 'بديل ثانٍ', 'مشترك'));

$page_title = 'إيكوبيشن | حاويات العقود';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/** صفُّ حاويةٍ وأبناؤه — تكرارٌ مرتّبٌ بعمقه. */
function cnt_node($n, $depth, $byParent, $can_manage, $CSRF, $LEVEL_NEXT, $ROLES, $contract)
{
    $id = (int) $n['id'];
    $free = round((float) $n['cap_qty'] - (float) $n['allocated_qty'], 2);
    $rem  = round((float) $n['cap_qty'] - (float) $n['consumed_qty'], 2);
    $party = trim((string) ($n['supplier_name'] ?? '') . (string) ($n['equipment_name'] ?? '')
                 . (string) ($n['operator_name'] ?? ''));
    $derived = ((string) $n['origin'] === 'مشتقّة');
    $pending = $derived && ($n['origin_ack_by'] === null);
    ?>
    <tr class="<?php echo $pending ? 'cnt-derived' : ''; ?>">
        <td data-allow-style style="padding-right:<?php echo 6 + $depth * 22; ?>px">
            <?php if ($depth > 0): ?><span class="cnt-branch">└</span> <?php endif; ?>
            <strong><?php echo cnt_e($n['level']); ?></strong>
            <code class="cnt-no"><?php echo cnt_e($n['container_no']); ?></code>
            <?php if ($party !== ''): ?><span class="cnt-party"><?php echo cnt_e($party); ?></span><?php endif; ?>
            <?php if ($n['role_kind']): ?><span class="badge bg-light text-dark"><?php echo cnt_e($n['role_kind']); ?></span><?php endif; ?>
            <?php if ($pending): ?>
                <span class="badge bg-warning text-dark" title="<?php echo cnt_e($n['origin_note']); ?>">
                    مشتقّة — تنتظر الإقرار</span>
            <?php elseif ($derived): ?>
                <span class="badge bg-success">مشتقة ومقرة</span>
            <?php endif; ?>
            <?php if ($pending): ?>
                <div class="cnt-note"><i class="fa fa-quote-right"></i> <?php echo cnt_e($n['origin_note']); ?></div>
            <?php endif; ?>
        </td>
        <td class="cnt-num"><?php echo cnt_n($n['cap_qty']); ?></td>
        <td class="cnt-num"><?php echo cnt_n($n['allocated_qty']); ?></td>
        <td class="cnt-num"><strong><?php echo cnt_n($free); ?></strong></td>
        <td class="cnt-num"><?php echo cnt_n($n['consumed_qty']); ?></td>
        <td class="cnt-num"><strong class="<?php echo $rem > 0 ? 'cnt-rem-ok' : 'cnt-rem-low'; ?>">
            <?php echo cnt_n($rem); ?></strong></td>
        <td class="cnt-small"><?php echo cnt_e($n['unit_type']); ?></td>
        <td class="cnt-nowrap">
            <?php if ($can_manage && $pending): ?>
            <form method="post" class="cnt-inline">
        <?= csrf_field() ?>
                <input type="hidden" name="cnt_action" value="acknowledge">
                <input type="hidden" name="container_id" value="<?php echo $id; ?>">
                <input type="hidden" name="contract_id" value="<?php echo (int) $contract; ?>">
                <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
                <button class="btn btn-sm btn-primary" title="أقر الحصة المشتقة">أقر</button>
            </form>
            <?php endif; ?>
            <?php if ($can_manage && $LEVEL_NEXT[(string) $n['level']] !== null): ?>
            <button class="btn btn-sm btn-secondary cnt-alloc-btn"
                    data-parent="<?php echo $id; ?>"
                    data-level="<?php echo cnt_e($LEVEL_NEXT[(string) $n['level']]); ?>"
                    data-free="<?php echo cnt_n($free); ?>"
                    data-no="<?php echo cnt_e($n['container_no']); ?>">وزع</button>
            <?php endif; ?>
            <?php if ($can_manage && in_array((string) $n['level'], array('معدة', 'مشغّل'), true)): ?>
            <button class="btn btn-sm btn-secondary cnt-swap-btn"
                    data-container="<?php echo $id; ?>"
                    data-kind="<?php echo cnt_e($n['level']); ?>">بدل</button>
            <?php endif; ?>
            <?php if ($can_manage && (string) $n['level'] === 'مشغّل'): ?>
            <!-- H-01-③: مقبضُ الدورات — شرطُ بوابة الحصص الثالث (كان طريقًا مسدودًا) -->
            <details class="cnt-inline-block">
                <summary class="btn btn-sm btn-secondary cnt-pointer">دورة</summary>
                <form method="post" class="cnt-rot-form">
        <?= csrf_field() ?>
                    <input type="hidden" name="cnt_action" value="rotation">
                    <input type="hidden" name="container_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="contract_id" value="<?php echo (int) $contract; ?>">
                    <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
                    <input type="number" name="cycle_on_days" min="1" placeholder="أيام عمل" class="cnt-w80" required aria-label="أيام عمل">
                    <input type="number" name="cycle_off_days" min="0" placeholder="أيام راحة" class="cnt-w80" required aria-label="أيام راحة">
                    <input type="date" name="cycle_start" aria-label="تاريخ بدء دورة التناوب" required>
                    <input type="text" name="note" placeholder="ملاحظة" class="cnt-w100" aria-label="ملاحظة">
                    <button class="btn btn-sm btn-secondary">سجل الدورة</button>
                </form>
            </details>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    if (isset($byParent[$id])) {
        foreach ($byParent[$id] as $ch) {
            cnt_node($ch, $depth + 1, $byParent, $can_manage, $CSRF, $LEVEL_NEXT, $ROLES, $contract);
        }
    }
}
?>
<div class="main cnt-main ems-unified-page-shell">
    <?php
    $header_title = 'حاويات العقود';
    $header_icon  = 'fa fa-boxes-stacked';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا حاويات عقد مسجلة في هذا النطاق بعد', 'اختر عقدا من القائمة ثم ولد الحاويات الرئيسية من بنوده');
    ?>

    <?php if (isset($_GET['msg'])): ?>
    <div class="card"><div class="card-body cnt-msg-body">
        <?php echo cnt_e($_GET['msg']); ?></div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <p class="text-muted cnt-intro">
            <i class="fa fa-circle-info"></i>
            الحاويةُ **سقفُ كميةٍ** ينزل من العقد إلى المورد إلى المعدة إلى المشغّل.
            و<strong>Σ حصص الأبناء لا تتجاوز سقفَ الأم</strong> — يحرسه المحرّكُ نفسُه لا الشاشة.
            <br><i class="fa fa-triangle-exclamation"></i>
            <strong>الحصةُ قرارٌ تجاريّ</strong>: ما استُنتج من صفوف التشغيل يُعرض
            <span class="badge bg-warning text-dark">مشتقّة</span> بملاحظته حتى تُقرّه الإدارة —
            ولا يُقدَّم رقمٌ مشتقٌّ كأنه متفقٌ عليه.
        </p>
        <form method="get" class="cnt-filter">
            <div class="form-group cnt-contract-group">
                <label for="emsf_799_342fc">العقد</label>
                <select name="contract" onchange="this.form.submit()" id="emsf_799_342fc">
                    <option value="">— اختر عقدًا —</option>
                    <?php foreach ($contracts as $c):
                        $eff = CSM::isEffective($c['contract_status']); ?>
                        <option value="<?php echo (int) $c['id']; ?>" <?php echo ($contract === (int) $c['id']) ? 'selected' : ''; ?>>
                            عقد #<?php echo (int) $c['id']; ?>
                            <?php echo $c['project_name'] ? ' — ' . cnt_e($c['project_name']) : ''; ?>
                            · <?php echo cnt_e($c['contract_status']); ?><?php echo $eff ? '' : ' (غير نافذ)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div></div>

    <?php if ($contract > 0): ?>
    <div class="card"><div class="card-body">
        <h5 class="cnt-tight"><i class="fa fa-sitemap"></i> شجرة الحاويات</h5>
        <?php if ($can_manage): ?>
        <div class="cnt-gen-actions">
            <form method="post" class="cnt-inline">
        <?= csrf_field() ?>
                <input type="hidden" name="cnt_action" value="generate_main">
                <input type="hidden" name="contract_id" value="<?php echo $contract; ?>">
                <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
                <button class="btn btn-sm btn-primary"><i class="fa fa-wand-magic-sparkles"></i>
                    ولّد الرئيسيات من بنود العقد</button>
            </form>
            <form method="post" class="cnt-inline"
                  onsubmit="return confirm('التوليدُ الرجعيُّ يستنتج الحصصَ من صفوف التشغيل، ويوسمها «مشتقّة» حتى تُقرّها الإدارة. متابعة؟');">
        <?= csrf_field() ?>
                <input type="hidden" name="cnt_action" value="derive">
                <input type="hidden" name="contract_id" value="<?php echo $contract; ?>">
                <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
                <button class="btn btn-sm btn-secondary"><i class="fa fa-diagram-project"></i>
                    ولّد الشجرةَ من صفوف التشغيل (مشتقّة)</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!$mains): ?>
            <p class="text-muted">لا حاويات لهذا العقد بعد — ابدأ بتوليد الرئيسيات من بنوده.</p>
        <?php else: ?>
        <div class="table-container cnt-scroll-x">
        <table class="display cnt-table no-datatable cnt-table-full">
            <thead><tr>
                <th>الحاوية</th><th>السقف</th><th>الموزع</th><th>المتاح للتوزيع</th>
                <th>المستهلك</th><th>المتبقي</th><th>رقم الوحدة</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($mains as $m) {
                cnt_node($m, 0, $byParent, $can_manage, $CSRF, $LEVEL_NEXT, $ROLES, $contract);
            } ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <?php /* ── تقريرُ المطابقة — يُعلن ولا يُصلح ─────────────────────────── */ ?>
    <div class="card"><div class="card-body">
        <h5 class="cnt-tight"><i class="fa fa-scale-balanced"></i> تقرير المطابقة</h5>
        <p class="text-muted cnt-tight">
            يعلن ما ينتظر قرارا ولا يصلحه — ويبقى ظاهرا حتى يقفل.
        </p>
        <h6 class="fw-bold">حصص مشتقة تنتظر الإقرار: <?php echo count($recon['derived_pending']); ?></h6>
        <?php if ($recon['derived_pending']): ?>
        <div class="table-container cnt-scroll-x cnt-mb14">
        <table class="display no-datatable cnt-table-full">
            <thead><tr><th>الحاوية</th><th>المستوى</th><th>العقد</th><th>السقف</th><th>من أين اشتقت</th></tr></thead>
            <tbody>
            <?php foreach ($recon['derived_pending'] as $d): ?>
                <tr>
                    <td><code><?php echo cnt_e($d['container_no']); ?></code></td>
                    <td><?php echo cnt_e($d['level']); ?></td>
                    <td>#<?php echo (int) $d['contract_id']; ?></td>
                    <td><?php echo cnt_n($d['cap_qty']) . ' ' . cnt_e($d['unit_type']); ?></td>
                    <td class="cnt-small"><?php echo cnt_e($d['origin_note']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?><p class="text-muted">لا شيء.</p><?php endif; ?>

        <h6 class="fw-bold">وقائع بوحدة لا حاوية رئيسية لها: <?php echo count($recon['unmatched_units']); ?></h6>
        <?php if ($recon['unmatched_units']): ?>
        <div class="table-container cnt-scroll-x">
        <table class="display no-datatable cnt-table-full">
            <thead><tr><th>العقد</th><th>الوحدة التعاقدية</th><th>الوقائع</th><th>الكمية</th><th>الحكم</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">نوع المعدة</th>
              <th class="ems-fn-th" data-fn="1">رقم المقعد</th>
              <th class="ems-fn-th" data-fn="1">دور الوحدة</th>
              <th class="ems-fn-th" data-fn="1">الساعات التعاقدية الشهرية</th>
              <th class="ems-fn-th" data-fn="1">سعر الساعة</th>
              <th class="ems-fn-th" data-fn="1">نوع الوحدة</th>
              <th class="ems-fn-th" data-fn="1">المعدة المسندة حاليا</th>
              <th class="ems-fn-th" data-fn="1">مالك المعدة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ بدء الإسناد</th>
              <th class="ems-fn-th" data-fn="1">حالة الإشغال</th>
              <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
              <th class="ems-fn-th" data-fn="1">عرفها</th>
              <th class="ems-fn-th" data-fn="1">رقم الإسناد</th>
              <th class="ems-fn-th" data-fn="1">كود المعدة</th>
              <th class="ems-fn-th" data-fn="1">من تاريخ</th>
              <th class="ems-fn-th" data-fn="1">إلى تاريخ</th>
              <th class="ems-fn-th" data-fn="1">عدد الأيام</th>
              <th class="ems-fn-th none" data-fn="1">سبب الاستبدال</th>
              <th class="ems-fn-th none" data-fn="1">الوحدة السابقة</th>
              <th class="ems-fn-th none" data-fn="1">المشغلون المسندون</th>
              <th class="ems-fn-th none" data-fn="1">أسنده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
            <tbody>
            <?php foreach ($recon['unmatched_units'] as $u): ?>
                <tr>
                    <td>#<?php echo (int) $u['contract_id']; ?></td>
                    <td><?php echo cnt_e($u['unit_type']); ?></td>
                    <td><?php echo (int) $u['entries']; ?></td>
                    <td><?php echo cnt_n($u['qty']); ?></td>
                    <td class="cnt-small">لا بند بهذه الوحدة في العقد — <strong>تنتظر بندا أو ملحقا</strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?><p class="text-muted">لا شيء.</p><?php endif; ?>
    </div></div>

    <?php if ($can_manage && $contract > 0): ?>
    <div class="card is-hidden" id="allocCard"><div class="card-body">
        <h5 class="cnt-tight"><i class="fa fa-share-nodes"></i>
            توزيع حصة من <span id="allocParentNo"></span></h5>
        <p class="text-muted cnt-tight">المتاح للتوزيع: <strong id="allocFree">—</strong></p>
        <form method="post" class="allforms allforms-visible cnt-form-plain">
        <?= csrf_field() ?>
            <input type="hidden" name="cnt_action" value="allocate">
            <input type="hidden" name="contract_id" value="<?php echo $contract; ?>">
            <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
            <input type="hidden" name="parent_id" id="allocParent">
            <input type="hidden" name="child_level" id="allocLevel">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="allocLevelTxt">المستوى</label>
                    <input type="text" id="allocLevelTxt" disabled></div>
                <div class="form-group"><label for="emsf_800_c5dd9">مرجع الطرف *
                    <span class="mnt-req-hint">(رقم المورد/المعدة/الموظف)</span></label>
                    <input type="number" min="1" name="child_ref" required id="emsf_800_c5dd9"></div>
                <div class="form-group"><label for="emsf_801_79d3f">الحصة *</label>
                    <input type="number" step="0.01" min="0.01" name="qty" required id="emsf_801_79d3f"></div>
                <div class="form-group"><label for="allocRole">الدور</label>
                    <select name="role_kind" id="allocRole"><option value="">—</option></select></div>
                <div class="form-group"><label for="emsf_802_82d58">النوبة</label>
                    <input type="number" min="1" max="3" name="shift_no" id="emsf_802_82d58"></div>
            </div></div>
            <div class="cnt-form-actions">
                <button class="btn btn-primary"><i class="fa fa-check"></i> وزع</button>
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('allocCard').classList.add('is-hidden')">إلغاء</button>
            </div>
        </form>
    </div></div>

    <div class="card is-hidden" id="swapCard"><div class="card-body">
        <h5 class="cnt-tight"><i class="fa fa-right-left"></i> تبديل</h5>
        <form method="post" class="allforms allforms-visible cnt-form-plain">
        <?= csrf_field() ?>
            <input type="hidden" name="cnt_action" value="swap">
            <input type="hidden" name="contract_id" value="<?php echo $contract; ?>">
            <input type="hidden" name="cnt_csrf" value="<?php echo cnt_e($CSRF); ?>">
            <input type="hidden" name="container_id" id="swapContainer">
            <input type="hidden" name="swap_kind" id="swapKind">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_803_36593">الخارج</label>
                    <input type="number" min="1" name="out_ref" id="emsf_803_36593"></div>
                <div class="form-group"><label for="emsf_804_42ce1">الداخل</label>
                    <input type="number" min="1" name="in_ref" id="emsf_804_42ce1"></div>
                <div class="form-group"><label for="emsf_805_975a5">من تاريخ *</label>
                    <input type="date" name="effective_from" required id="emsf_805_975a5"></div>
                <div class="form-group"><label for="emsf_806_cdd4c">السبب *
                    <span class="mnt-req-hint">(إلزام — لا تبديل بلا سبب)</span></label>
                    <input type="text" name="reason" maxlength="255" required id="emsf_806_cdd4c"></div>
                <div class="form-group"><label for="emsf_807_86c6c">مرجع المستند</label>
                    <input type="text" name="doc_ref" maxlength="120" id="emsf_807_86c6c"></div>
            </div></div>
            <div class="cnt-form-actions">
                <button class="btn btn-primary"><i class="fa fa-check"></i> سجل التبديل</button>
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('swapCard').classList.add('is-hidden')">إلغاء</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>
</div>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<script>
// بلا jQuery ولا ready — فلا يُسقطه عطبُ مُعالجٍ آخر (گوتشا `_DT_CellIndex`)
(function () {
    var ROLES = <?php echo json_encode($ROLES, JSON_UNESCAPED_UNICODE); ?>;
    var card = document.getElementById('allocCard');
    var btns = document.querySelectorAll('.cnt-alloc-btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].addEventListener('click', function () {
            var lvl = this.getAttribute('data-level');
            document.getElementById('allocParent').value = this.getAttribute('data-parent');
            document.getElementById('allocLevel').value = lvl;
            document.getElementById('allocLevelTxt').value = lvl;
            document.getElementById('allocFree').textContent = this.getAttribute('data-free');
            document.getElementById('allocParentNo').textContent = this.getAttribute('data-no');
            var sel = document.getElementById('allocRole');
            sel.innerHTML = '<option value="">—</option>';
            (ROLES[lvl] || []).forEach(function (r) {
                var o = document.createElement('option'); o.value = r; o.textContent = r; sel.appendChild(o);
            });
            card.classList.remove('is-hidden'); card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
    var sw = document.getElementById('swapCard');
    var sbtns = document.querySelectorAll('.cnt-swap-btn');
    for (var j = 0; j < sbtns.length; j++) {
        sbtns[j].addEventListener('click', function () {
            document.getElementById('swapContainer').value = this.getAttribute('data-container');
            document.getElementById('swapKind').value = this.getAttribute('data-kind');
            sw.classList.remove('is-hidden'); sw.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
})();
</script>
</body>
</html>
