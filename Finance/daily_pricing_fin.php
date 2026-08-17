<?php
/**
 * Finance/daily_pricing_fin.php — التسعيرُ اليوميُّ من الإدارةِ المالية
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-12** (جوابًا على «ما مصدرُ مؤشراتِ الأسعار؟»):
 *   «مصدرُها التحديثُ الوقتيُّ للأسعارِ **من الإدارةِ المالية** لكلِّ عمليةٍ بشكلِ
 *    تسعيرٍ لكلِّ معاملةٍ، مع إمكانيةِ تحديدِ السعرِ **لليومِ بشكلٍ يوميّ**.»
 *
 * ── لماذا شاشةٌ مستقلّةٌ ولم تُمنح الماليةُ صلاحيةَ شاشةِ العقود ─────────────
 * `Contracts/price_terms.php` فيه أربعةُ أفعالٍ وبتَّان فقط: `add_reading`
 * (تسجيلُ سعرِ اليومِ — **عملُ المالية**) يتقاسم `can_add` مع `save_term`
 * (كتابةُ **بندِ العقدِ** — عملُ الدور 12). فمنحُ الماليةِ `can_add` هناك
 * **منحٌ زائدٌ** يعطيها كتابةَ بنودِ العقود. فالفصلُ بشاشةٍ بموديولِها:
 *   · هنا: **تسجيلُ سعرِ اليومِ** وتوليدُ مراجعاتِه — وهو ما قال المالكُ إنه للمالية.
 *   · وهناك: **بندُ العقدِ** (المعادلةُ والعتبةُ والسقفُ) — يبقى لمالكِ العقود.
 *
 * ── ولا شيءَ يُكتب خامًا ────────────────────────────────────────────────────
 * كلُّ فعلٍ يمرُّ بـ`PriceAdjustmentService`: `recordIndexReading` ⇒ `applyDue` ⇒
 * `approve`. فالحرّاسُ تعمل: لا سعرَ بلا مُسعِّرٍ مُعرَّفٍ (403) · ولا سعرُ يومٍ
 * مرتين (409) · و**من أنشأ لا يعتمد** (403) · ولا اعتمادَ بلا معتمِدٍ مُعرَّف.
 * والسعرُ الأساسيُّ في العقد **لا يُمَسّ**: المراجعةُ طبقةٌ بتاريخها فوقَه.
 *
 * ◆ والحجبُ **قبلَ أيِّ استعلامٍ** لا بعده (درسُ INJ-0454).
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
include '../includes/permissions_helper.php';

/* ◆ الحارسُ فوقَ كلِّ معالجٍ واستعلامٍ — فلا تُقرأ بياناتٌ لحسابٍ محرومٍ ثم يُقال
     «لا صلاحية». (INJ-0454: شاشتان في الموردين كانتا تقرآن ثم تُعيدان التوجيه.) */
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/../app/Services/Contract/PriceAdjustmentService.php';

use App\Services\Contract\PriceAdjustmentService as PAS;

$ctx = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}
$perms = fin_page_perms($conn, 'Finance/daily_pricing_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
$can_add  = isset($perms['can_add']) ? $perms['can_add'] : $can_edit;
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض التسعير اليومي ❌', 'GOV-PERM-403', '');
    exit();
}

$CO = ems_scope_company($conn);
$gate = ems_tenant_db();
$SELF = 'daily_pricing_fin.php';

/* ══ ① تسجيلُ سعرِ اليومِ — عبر الخدمةِ حصرًا ════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_price'])) {
    if (!$can_add) {
        ems_gov_flash_redirect($SELF, 'لا توجد صلاحية تسجيل سعرٍ ❌', 'GOV-PERM-403', ''); exit();
    }
    $res = PAS::recordIndexReading($conn, $gate, $CO, array(
        'index_code'   => isset($_POST['index_code']) ? $_POST['index_code'] : '',
        'reading_date' => isset($_POST['reading_date']) ? $_POST['reading_date'] : '',
        'value'        => isset($_POST['value']) ? $_POST['value'] : 0,
        'source_ref'   => isset($_POST['source_ref']) ? $_POST['source_ref'] : '',
        'note'         => isset($_POST['note']) ? $_POST['note'] : '',
    ), $current_user_id);
    if (!empty($res['ok'])) {
        ems_gov_flash_redirect($SELF, 'سُجّل سعرُ اليومِ ✅ — ولّد مراجعتَه لتسري على معاملاتِ يومِه',
                               'GOV-OK-200', '');
    } else {
        ems_gov_flash_redirect($SELF, (isset($res['reason']) ? $res['reason'] : 'تعذّر التسجيل') . ' ❌',
                               'GOV-FAIL-' . (isset($res['code']) ? $res['code'] : '409'), '');
    }
    exit();
}

/* ══ ② توليدُ مراجعاتِ يومٍ لعقدٍ — والاعتمادُ **لغيرِ المُولِّد** ═══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_day'])) {
    if (!$can_edit) {
        ems_gov_flash_redirect($SELF, 'لا توجد صلاحية توليد المراجعات ❌', 'GOV-PERM-403', ''); exit();
    }
    $cid = intval(isset($_POST['contract_id']) ? $_POST['contract_id'] : 0);
    $day = isset($_POST['day']) ? trim((string) $_POST['day']) : '';
    if ($cid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        ems_gov_flash_redirect($SELF, 'العقدُ والتاريخُ إلزاميان ❌', 'GOV-FAIL-422', ''); exit();
    }
    $r = PAS::applyDue($conn, $gate, $CO, $cid, $day, $current_user_id, 'user');
    if (!empty($r['ok'])) {
        $made = isset($r['created']) ? (int) $r['created'] : 0;
        $skip = isset($r['skipped']) ? (int) $r['skipped'] : 0;
        ems_gov_flash_redirect($SELF, "وُلّد {$made} مراجعةً · وتُخطّي {$skip} — والاعتمادُ لغيرِ من ولَّد ⏳",
                               'GOV-OK-200', '');
    } else {
        ems_gov_flash_redirect($SELF, (isset($r['reason']) ? $r['reason'] : 'تعذّر التوليد') . ' ❌',
                               'GOV-FAIL-' . (isset($r['code']) ? $r['code'] : '422'), '');
    }
    exit();
}

/* ══ ③ اعتمادُ مراجعةٍ — والخدمةُ تردُّ المُنشئَ نفسَه بنيويًّا ══════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_rev'])) {
    if (!$can_edit) {
        ems_gov_flash_redirect($SELF, 'لا توجد صلاحية الاعتماد ❌', 'GOV-PERM-403', ''); exit();
    }
    $rid = intval(isset($_POST['revision_id']) ? $_POST['revision_id'] : 0);
    $a = PAS::approve($conn, $gate, $CO, $rid, $current_user_id);
    if (!empty($a['ok'])) {
        ems_gov_flash_redirect($SELF, 'اعتُمدت المراجعةُ — سعرُها يسري على معاملاتِ يومِها ✅', 'GOV-OK-200', '');
    } else {
        ems_gov_flash_redirect($SELF, (isset($a['reason']) ? $a['reason'] : 'تعذّر الاعتماد') . ' ❌',
                               'GOV-FAIL-' . (isset($a['code']) ? $a['code'] : '409'), '');
    }
    exit();
}

/* ══ القراءةُ للعرضِ — بعدَ الحارسِ لا قبلَه ══════════════════════════════════
     ◆ **جدولٌ فارغٌ لا يعني «لا بيانات».** `config.php` يضبط mysqli على عدمِ
       الرمي، فاستعلامٌ بعمودٍ خاطئٍ يعود `false` **صامتًا** وتُعرَض الشاشةُ 200
       بجدولٍ فارغٍ كأنْ لا شيءَ هناك. ووقع فعلًا في أوّلِ نسخةٍ من هذه الشاشة:
       كتبتُ `c.contract_no` ولا وجودَ لذلك العمودِ في `contracts` (المعرِّفُ `id`
       والطرفُ `second_party`) — فظهرت ثلاثةُ بنودٍ قائمةٍ **صفرًا**، والصفحةُ 200.
     ◆ فكلُّ استعلامِ عرضٍ يمرُّ بـ`$fetch` الذي **يميّز الفشلَ من الفراغِ**
       ويُعلن الفشلَ على الشاشة — فلا يُقرأ صمتُ الخطإِ «لا بيانات» بعد اليوم. */
$TODAY = date('Y-m-d');
$qErrors = array();
/** يُنفّذ استعلامَ عرضٍ ويميّز الفشلَ من الفراغ — والفشلُ يُعلَن لا يُدفَن */
$fetch = function ($sql, $label) use ($conn, &$qErrors) {
    $r = $conn->query($sql);
    if ($r === false) {
        $qErrors[] = $label . ': ' . $conn->error;
        return array();
    }
    $out = array();
    while ($x = $r->fetch_assoc()) { $out[] = $x; }
    return $out;
};

/* بنودُ التسعيرِ اليوميِّ الحيّةُ ومعها سعرُ اليومِ لكلِّ بندٍ */
$terms = array();
$rowsT = $fetch("SELECT t.id, t.contract_id, t.contract_item_id, t.index_code, t.trigger_kind,
                          t.base_index, t.threshold_percent, t.pass_through_percent, t.cap_percent,
                          t.valid_from, t.valid_to, c.second_party, ce.equip_price
                     FROM contract_price_terms t
                     JOIN contracts c ON c.id = t.contract_id
                     LEFT JOIN contractequipments ce ON ce.id = t.contract_item_id
                    WHERE t.company_id = {$CO} AND t.periodicity = 'daily'
                    ORDER BY t.contract_id, t.id", 'بنودُ التسعيرِ اليومي');
foreach ($rowsT as $x) {
    $x['price_today'] = null;
    if ((int) $x['contract_item_id'] > 0 && $x['equip_price'] !== null) {
        $x['price_today'] = PAS::effectivePrice($conn, $CO, (int) $x['contract_id'],
                                                (int) $x['contract_item_id'], $TODAY,
                                                (float) $x['equip_price']);
    }
    $terms[] = $x;
}

/* آخرُ أسعارِ الأيامِ المسجَّلةِ — ومن سجّلها (فالمسؤوليةُ جزءُ الحقيقة) */
$readings = $fetch("SELECT r.id, r.index_code, r.reading_date, r.value, r.source_ref, r.note,
                          r.created_by, u.username AS by_name
                     FROM contract_price_index_readings r
                     LEFT JOIN users u ON u.id = r.created_by
                    WHERE EXISTS (SELECT 1 FROM contract_price_terms t
                                   WHERE t.company_id = {$CO} AND t.index_code = r.index_code)
                    ORDER BY r.reading_date DESC, r.id DESC LIMIT 60", 'أسعارُ الأيامِ المسجَّلة');

/* مراجعاتٌ تنتظر اعتمادًا — ويُبيَّن من ولَّدها ليُعرَف أنه لا يعتمدها */
$pending = $fetch("SELECT r.id, r.period_key, r.effective_from, r.old_price, r.new_price,
                          r.outcome, r.created_by, r.created_origin, t.index_code,
                          c.second_party, u.username AS by_name
                     FROM contract_price_revisions r
                     JOIN contract_price_terms t ON t.id = r.term_id
                     JOIN contracts c ON c.id = r.contract_id
                     LEFT JOIN users u ON u.id = r.created_by
                    WHERE r.company_id = {$CO} AND r.approved_at IS NULL
                      AND t.periodicity = 'daily'
                    ORDER BY r.effective_from DESC, r.id DESC LIMIT 60", 'مراجعاتٌ تنتظر الاعتماد');

/* رموزُ المؤشرِ المتاحةُ للتسجيل — من بنودٍ قائمةٍ فقط، فلا يُخترع رمز */
$codes = array();
foreach ($terms as $t) { $codes[(string) $t['index_code']] = true; }
$codes = array_keys($codes);

$page_title = 'إيكوبيشن | التسعير اليومي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ٢: أنماطُ هذه الشاشةِ الثابتةُ صارت أصنافًا ببادئةِ الشاشة */
.fin-dp-errcard { border-inline-start: 4px solid var(--c-dc3545); }
.fin-dp-errtitle { margin: 0 0 6px; color: var(--c-dc3545); }
.fin-dp-errlist { margin: 0; padding-inline-start: 18px; }
.fin-dp-flat { margin: 0; }
.fin-dp-h6 { margin: 0 0 10px; }
.fin-dp-note { margin: 0 0 8px; }
.fin-dp-form { box-shadow: none; padding: 0; }
.fin-dp-span-all { grid-column: 1 / -1; }
.fin-dp-rowform { margin: 0; display: flex; gap: 6px; }
.fin-dp-day { max-width: 150px; }
</style>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'التسعير اليومي — من الإدارة المالية';
    $header_icon = 'fa fa-calendar-day';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($conn)) { ems_screen_about_auto($conn); }
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
    echo ems_states_bundle('لا أسعارَ أيامٍ مسجّلةً بعدُ', 'سجّلْ سعرَ اليومِ بمرجعِ قرارِه من نموذجِ «تسجيلُ سعرِ يومٍ» أعلاه');
    ?>

    <?php if ($qErrors): ?>
    <?php /* ◆ استعلامُ عرضٍ فشل: يُعلَن **على الشاشة** لا يُدفَن، وإلا قُرئ الجدولُ
             الفارغُ «لا بيانات» وهو خطأُ استعلامٍ. */ ?>
    <div class="card"><div class="card-body fin-dp-errcard">
        <h6 class="fin-dp-errtitle">
            <i class="fas fa-triangle-exclamation"></i>
            استعلامُ عرضٍ فشل — الجدولُ الفارغُ أدناه <strong>خطأٌ لا غيابُ بيانات</strong>
        </h6>
        <ul class="fin-dp-errlist">
            <?php foreach ($qErrors as $e): ?>
            <li><code><?php echo htmlspecialchars($e); ?></code></li>
            <?php endforeach; ?>
        </ul>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <p class="text-muted fin-dp-flat">
            <i class="fas fa-info-circle"></i>
            <strong>سعرُ اليومِ يسري على معاملاتِ يومِه</strong> — وواقعةُ الأمسِ تبقى بسعرِ أمسِها
            (لا رجعية). والسعرُ الأساسيُّ في العقدِ لا يُمَسّ: المراجعةُ طبقةٌ بتاريخها فوقَه.
            <br>
            <i class="fas fa-user-shield"></i>
            <strong>من سجّل السعرَ لا يعتمد مراجعتَه</strong> — الفصلُ بنيويٌّ في الخدمةِ لا اختياريٌّ هنا.
        </p>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-body">
        <h6 class="fin-dp-h6"><i class="fas fa-plus-circle"></i> تسجيلُ سعرِ يومٍ</h6>
        <?php if (!$codes): ?>
            <p class="text-muted fin-dp-flat">
                لا بندَ تسعيرٍ <strong>يوميٍّ</strong> في الشركة بعد — يُنشئه مالكُ العقودِ في
                «شروط تعديل السعر» بدوريةٍ «يومي»، ثم تُسعّر الماليةُ هنا.
            </p>
        <?php else: ?>
        <form action="" method="post" class="allforms allforms-visible fin-dp-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="record_price" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="dp_code">رمزُ المؤشر *</label>
                    <select id="dp_code" name="index_code" required>
                        <?php foreach ($codes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dp_date">تاريخُ السعر *</label>
                    <input type="date" id="dp_date" name="reading_date" value="<?php echo $TODAY; ?>" required>
                </div>
                <div class="form-group">
                    <label for="dp_val">قيمةُ السعر *</label>
                    <input type="number" step="0.00000001" min="0.00000001" id="dp_val" name="value" required>
                </div>
                <div class="form-group">
                    <label for="dp_ref">مرجعُ قرارِ التسعير *</label>
                    <input type="text" id="dp_ref" name="source_ref" maxlength="160" required
                           placeholder="رقمُ محضرٍ أو تعميمٍ ماليٍّ — لا رقمَ بلا مرجع">
                </div>
                <div class="form-group fin-dp-span-all">
                    <label for="dp_note">ملاحظة</label>
                    <input type="text" id="dp_note" name="note" maxlength="255"
                           placeholder="سببُ التغيير — ارتفاعُ وقودٍ · تغيُّرُ صرفٍ …">
                </div>
            </div></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> سجّل سعرَ اليوم</button>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h6 class="fin-dp-h6">
            <i class="fas fa-list"></i> بنودُ التسعيرِ اليوميِّ وسعرُ اليومِ لكلٍّ
            <span class="badge bg-secondary"><?php echo count($terms); ?></span>
        </h6>
        <div class="table-responsive">
        <table class="alltables display" id="dpTermsTable">
            <thead><tr>
                <th>العقد</th><th>البند</th><th>رمزُ المؤشر</th><th>المحفِّز</th>
                <th>المرجع</th><th>العتبة٪</th><th>التمرير٪</th><th>السقف٪</th>
                <th>الأساس</th><th>سعرُ اليوم</th><th>السريان</th><th>توليدُ مراجعةِ اليوم</th>
            </tr></thead>
            <tbody>
            <?php foreach ($terms as $t): ?>
                <tr>
                    <td>#<?php echo (int) $t['contract_id']; ?> — <?php echo htmlspecialchars(mb_substr((string) $t['second_party'], 0, 28)); ?></td>
                    <td><?php echo (int) $t['contract_item_id'] === 0 ? 'كلُّ البنود' : intval($t['contract_item_id']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['index_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['trigger_kind']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['base_index']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['threshold_percent']); ?></td>
                    <td><?php echo htmlspecialchars((string) $t['pass_through_percent']); ?></td>
                    <td><?php echo $t['cap_percent'] === null ? '—' : htmlspecialchars((string) $t['cap_percent']); ?></td>
                    <td><?php echo $t['equip_price'] === null ? '—' : htmlspecialchars((string) $t['equip_price']); ?></td>
                    <td><strong><?php echo $t['price_today'] === null ? '—' : htmlspecialchars((string) $t['price_today']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string) $t['valid_from']); ?><?php echo $t['valid_to'] ? ' ← ' . htmlspecialchars((string) $t['valid_to']) : ''; ?></td>
                    <td>
                        <?php if ($can_edit): ?>
                        <form action="" method="post" class="fin-dp-rowform">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="run_day" value="1">
                            <input type="hidden" name="contract_id" value="<?php echo (int) $t['contract_id']; ?>">
                            <?php /* AC-U7: حقلٌ داخلَ خليةِ جدولٍ لا يسعه `label for` بلا كسرِ
                                     تخطيطِ الصف — فيُوسَم وصفيًّا، وهو ما يقبله المعيارُ نصًّا
                                     («بعنوانٍ أو وسمٍ وصفيّ»). وكان الحقلَ الوحيدَ الناقصَ في
                                     الشجرةِ كلِّها: 3859 من 3860. */ ?>
                            <input type="date" name="day" aria-label="يومُ توليدِ سعرِ العقد" title="يومُ توليدِ سعرِ العقد"
                                   class="fin-dp-day" value="<?php echo $TODAY; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">ولّد</button>
                        </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <h6 class="fin-dp-h6">
            <i class="fas fa-hourglass-half"></i> مراجعاتٌ تنتظر الاعتماد
            <span class="badge bg-warning"><?php echo count($pending); ?></span>
        </h6>
        <p class="text-muted fin-dp-note">
            من ولَّد المراجعةَ لا يعتمدها — فإن كنتَ مولِّدَها فسيُردُّ فعلُك 403، وهذا مقصود.
        </p>
        <div class="table-responsive">
        <table class="alltables display" id="dpPendingTable">
            <thead><tr>
                <th>العقد</th><th>رمزُ المؤشر</th><th>مفتاحُ اليوم</th><th>السريان</th>
                <th>قبل</th><th>بعد</th><th>النتيجة</th><th>ولَّدها</th><th>الاعتماد</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars(mb_substr((string) $p['second_party'], 0, 28)); ?></td>
                    <td><?php echo htmlspecialchars((string) $p['index_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $p['period_key']); ?></td>
                    <td><?php echo htmlspecialchars((string) $p['effective_from']); ?></td>
                    <td><?php echo $p['old_price'] === null ? '—' : htmlspecialchars((string) $p['old_price']); ?></td>
                    <td><?php echo $p['new_price'] === null ? '—' : htmlspecialchars((string) $p['new_price']); ?></td>
                    <td><?php echo htmlspecialchars((string) $p['outcome']); ?></td>
                    <td><?php
                        echo (string) $p['created_origin'] === 'system'
                             ? 'الكرون (آليًّا)'
                             : htmlspecialchars((string) ($p['by_name'] !== null ? $p['by_name'] : $p['created_by']));
                    ?></td>
                    <td>
                        <?php if ($can_edit && in_array((string) $p['outcome'], array('amended', 'capped'), true)): ?>
                        <form action="" method="post" class="fin-dp-flat">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="approve_rev" value="1">
                            <input type="hidden" name="revision_id" value="<?php echo (int) $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">اعتمد</button>
                        </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <h6 class="fin-dp-h6">
            <i class="fas fa-history"></i> أسعارُ الأيامِ المسجَّلةُ ومن سجّلها
            <span class="badge bg-secondary"><?php echo count($readings); ?></span>
        </h6>
        <div class="table-responsive">
        <table class="alltables display" id="dpReadingsTable">
            <thead><tr>
                <th>التاريخ</th><th>رمزُ المؤشر</th><th>القيمة</th>
                <th>مرجعُ القرار</th><th>الملاحظة</th><th>سجّلها</th>
            </tr></thead>
            <tbody>
            <?php foreach ($readings as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) $r['reading_date']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['index_code']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['value']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['source_ref']); ?></td>
                    <td><?php echo htmlspecialchars((string) $r['note']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($r['by_name'] !== null ? $r['by_name'] : $r['created_by'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>
</body>
</html>
