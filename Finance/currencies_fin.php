<?php
/**
 * Finance/currencies_fin.php — العملات وأسعار الصرف (FES-01 §3.1 · §3.3)
 * ───────────────────────────────────────────────────────────────────────────
 * «ثلاثيةُ العملة: عملة · سعرُ صرفٍ · معادلٌ موحّد» — وهذه الشاشة بيتُ الطرفين
 * الأولين، والثالثُ يُحسب منهما آليًّا (`includes/fx.php`).
 *
 * **عملةُ الأساس لا تُختار هنا**: تُعلَن في بيانات الشركة (`admin_companies.currency`)
 * ومنها اشتُقّت — فالشاشةُ تعرضها ولا تعدّلها (مصدرٌ واحدٌ محكوم).
 *
 * **قاعدةُ عدم التلفيق**: ما لا سعرَ له في تاريخه يبقى بلا معادلٍ موحّد — تُعرض
 * «بانتظار سعر» ولا يُحسب برقمٍ مفترَض. وإدخالُ السعر **يُعيد تقييمَ** ما كان
 * ينتظره من الدفتر والذمم دفعةً واحدة، كلُّ صفٍّ بسعر تاريخه هو.
 *
 * **السعرُ بتاريخه**: `rate_to_base` = كم وحدةَ أساسٍ يساوي واحدٌ من العملة،
 * فيكون `base = ROUND(amount × rate, 2)` ضربًا لا قسمة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/../includes/fx.php';

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/currencies_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض العملات ❌', 'GOV-PERM-403', '');
    exit();
}

/**
 * إعادةُ تقييم ما ينتظر سعرًا — كلُّ صفٍّ بسعر **تاريخه هو** لا بآخر سعرٍ عُرف.
 * تُستدعى بعد كل سعرٍ جديد. ولا تمسّ صفًّا له معادلٌ سلفًا (لا تُعيد كتابة تاريخ).
 *
 * @return array عدد ما قُيّم في الدفتر والذمم
 */
function fx_revalue_pending($conn, $company_id, $code)
{
    /* CS-05 / AC-F6: المنطقُ انتقل إلى `App\Services\Finance\FxRevaluationService`
       ولم يُنسخ. هذه الدالةُ غلافُ توافقٍ لنداءاتِ الشاشةِ القائمة. */
    require_once __DIR__ . '/../app/Services/Finance/FxRevaluationService.php';
    return \App\Services\Finance\FxRevaluationService::revaluePending($conn, $company_id, $code);
}


// ── إضافة عملة ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_currency'])) {
    if (!$can_edit) { ems_gov_flash_redirect('currencies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }

    $code = strtoupper(trim(strval($_POST['code'] ?? '')));
    $name = trim(strval($_POST['name_ar'] ?? ''));
    $sym  = trim(strval($_POST['symbol'] ?? ''));

    $err = null;
    if ($code === '' || !preg_match('/^[A-Z]{3}$/', $code)) { $err = 'الرمز ثلاثة أحرف لاتينية (ISO)'; }
    elseif ($name === '')                                   { $err = 'اسم العملة إلزامي'; }
    if ($err !== null) { ems_gov_flash_redirect('currencies_fin.php', "{$err} ❌", 'GOV-FAIL-409', ''); exit(); }

    try {
        fin_gate($is_super_admin)->insert('fin_currencies', array(
            'code'       => $code,
            'name_ar'    => $name,
            'symbol'     => ($sym !== '') ? $sym : null,
            'decimals'   => 2,
            'is_base'    => 0,          // الأساسُ يُعلَن في بيانات الشركة لا هنا
            'active'     => 1,
            'sort_order' => 50,
            'created_by' => $current_user_id,
        ));
        ems_fx_cache_clear();
        ems_gov_flash_redirect('currencies_fin.php', "سجلت العملة {$code} — أدخل سعر صرفها ✅", 'GOV-OK-200', '');
    } catch (\Throwable $t) {
        error_log('currencies add: ' . $t->getMessage());
        $dup = (strpos($t->getMessage(), 'Duplicate') !== false);
        ems_gov_flash_redirect('currencies_fin.php', ($dup ? 'العملة مسجلة سلفا' : 'تعذر التسجيل') . ' ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── إضافة سعر صرف ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rate'])) {
    if (!$can_edit) { ems_gov_flash_redirect('currencies_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }

    $code    = strtoupper(trim(strval($_POST['currency_code'] ?? '')));
    $rateRaw = trim(strval($_POST['rate_to_base'] ?? ''));
    $from    = trim(strval($_POST['effective_from'] ?? ''));
    $src     = trim(strval($_POST['source'] ?? ''));
    $note    = trim(strval($_POST['note'] ?? ''));

    // الحقلُ نصيٌّ فالتطبيعُ هنا: فاصلةٌ عربيةٌ أو لاتينية · أرقامٌ هندية ·
    // فواصلُ آلافٍ — كلُّها تُقرأ، وما ليس رقمًا يُردّ برسالةٍ صريحة.
    $rate = ems_parse_decimal($rateRaw);

    $known = ems_fx_currencies();
    $err = null;
    if (!isset($known[$code]))                       { $err = 'اختر عملة مسجلة'; }
    elseif ($rateRaw === '')                         { $err = 'السعر إلزامي'; }
    elseif ($rate === null)                          { $err = 'السعر رقم — يقبل الكسر العشري (مثال 0.00166667)'; }
    elseif ($rate <= 0)                              { $err = 'السعر رقم موجب'; }
    elseif ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $err = 'تاريخ السريان إلزامي'; }
    if ($err !== null) { ems_gov_flash_redirect('currencies_fin.php', "{$err} ❌", 'GOV-FAIL-409', ''); exit(); }

    try {
        fin_gate($is_super_admin)->insert('fin_fx_rates', array(
            'currency_code'  => $code,
            'rate_to_base'   => $rate,
            'effective_from' => $from,
            'source'         => ($src !== '') ? $src : null,
            'note'           => ($note !== '') ? $note : null,
            'created_by'     => $current_user_id,
        ));
        $co = $is_super_admin ? $company_id : $company_id;
        $done = fx_revalue_pending($conn, $co, $code);
        $msg = 'سجل السعر ✅';
        if ($done['events'] > 0 || $done['dues'] > 0) {
            $msg .= ' — وقيم ' . intval($done['events']) . ' حدثا و' . intval($done['dues']) . ' ذمة';
        }
        ems_gov_flash_redirect('currencies_fin.php', "{$msg}", 'GOV-INFO-200', '');
    } catch (\Throwable $t) {
        error_log('fx rate add: ' . $t->getMessage());
        $dup = (strpos($t->getMessage(), 'Duplicate') !== false);
        ems_gov_flash_redirect('currencies_fin.php', ($dup ? 'لهذه العملة سعر بهذا التاريخ سلفا' : 'تعذر التسجيل') . ' ❌', 'GOV-FAIL-409', '');
    }
    exit();
}

// ── القراءة ──────────────────────────────────────────────────────────────
$currencies = ems_fx_currencies();
$baseCode   = ems_fx_base_currency();

$rates = array();
try {
    $rows = fin_gate($is_super_admin)->select('fin_fx_rates', array(
        'columns' => array('id', 'currency_code', 'rate_to_base', 'effective_from', 'source', 'note'),
        'orderBy' => 'currency_code',
    ));
    foreach ($rows as $r) { $rates[strval($r['currency_code'])][] = $r; }
    foreach ($rates as $k => $list) {
        usort($rates[$k], function ($a, $b) { return strcmp($b['effective_from'], $a['effective_from']); });
    }
} catch (\Throwable $t) { error_log('fx rates read: ' . $t->getMessage()); }

// ما ينتظر سعرًا — العددُ من مصدره لا تقديرًا
$pending = array();
$q = $conn->prepare(
    "SELECT currency, COUNT(*) c FROM ems_business_events
      WHERE company_id = ? AND currency IS NOT NULL AND base_amount IS NULL AND amount IS NOT NULL
      GROUP BY currency");
if ($q) {
    $q->bind_param('i', $company_id);
    if ($q->execute()) {
        $res = $q->get_result();
        while ($x = $res->fetch_assoc()) { $pending[strval($x['currency'])]['events'] = intval($x['c']); }
    }
    $q->close();
}
$q = $conn->prepare(
    "SELECT currency, COUNT(*) c FROM fin_dues
      WHERE company_id = ? AND base_amount IS NULL AND amount IS NOT NULL
        AND COALESCE(is_deleted,0) = 0
      GROUP BY currency");
if ($q) {
    $q->bind_param('i', $company_id);
    if ($q->execute()) {
        $res = $q->get_result();
        while ($x = $res->fetch_assoc()) { $pending[strval($x['currency'])]['dues'] = intval($x['c']); }
    }
    $q->close();
}
$pendingTotal = 0;
foreach ($pending as $p) { $pendingTotal += (isset($p['events']) ? $p['events'] : 0) + (isset($p['dues']) ? $p['dues'] : 0); }

$page_title = 'إيكوبيشن | العملات وأسعار الصرف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

<div class="main fin-currencies-main ems-unified-page-shell">
    <?php
    $header_title = 'العملات وأسعار الصرف';
    $header_icon  = 'fa fa-money-bill-transfer';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا عملات مسجلة بعد عملة الأساس', 'سجل عملة بنموذج «عملة جديدة» ثم أدخل سعر صرفها بتاريخ سريانه');
    ?>

    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <p class="fin-cur-lead">
            <i class="fas fa-circle-info"></i>
            سجل عملات التعامل وأسعار صرفها بتواريخها — تدخلها كلما تغير السعر.
            كل مبلغ في النظام يقاس ب<strong>عملة الأساس</strong>
            (<strong><?php echo htmlspecialchars((string) $baseCode); ?></strong>، معلنة في بيانات الشركة)
            بالمعادلة <code>المعادل = المبلغ × السعر</code> بسعر <strong>يوم الواقعة</strong> لا سعر اليوم.
            وما لا سعر لتاريخه يبقى <strong>بلا معادل</strong> ولا يحسب برقم مفترض —
            وإدخال السعر يقيمه فورا.
        </p>
        <div class="fin-cur-chips">
            <span class="badge badge-success fin-cur-chip">
                <?php echo count($currencies); ?> عملة مسجلة
            </span>
            <?php if ($pendingTotal > 0): ?>
            <span class="badge badge-warning fin-cur-chip">
                <i class="fas fa-hourglass-half"></i>
                <?php echo $pendingTotal; ?> صفا بانتظار سعر صرف لتاريخه
            </span>
            <?php else: ?>
            <span class="badge badge-success fin-cur-chip">
                <i class="fas fa-check"></i> لا صف ينتظر سعرا
            </span>
            <?php endif; ?>
        </div>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 class="fin-cur-h5"><i class="fas fa-plus"></i> سعر صرف جديد</h5>
        <form action="" method="post" class="allforms allforms-visible fin-cur-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="add_rate" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="emsf_229_b9610">العملة *</label>
                    <select name="currency_code" required id="emsf_229_b9610">
                        <option value="">— اختر —</option>
                        <?php foreach ($currencies as $code => $c) {
                            if (!empty($c['is_base'])) { continue; }   // الأساسُ سعرُه واحدٌ أبدًا
                            echo "<option value='" . htmlspecialchars($code) . "'>"
                               . htmlspecialchars($c['name_ar'] . ' (' . $code . ')') . "</option>";
                        } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>كم <?php echo htmlspecialchars((string) $baseCode); ?> للوحدة الواحدة؟ *</label>
                    <!-- نصّيٌّ لا رقميّ: `type=number` يرفض الفاصلةَ العربية والأرقامَ
                         الهندية بصمتٍ فيبدو أنه لا يقبل الكسور. inputmode=decimal
                         يبقي لوحةَ الأرقام على الهاتف، والتطبيعُ في ems_parse_decimal. -->
                    <input type="text" name="rate_to_base" inputmode="decimal" required
                           dir="ltr" class="fin-cur-ltr"
                           placeholder="مثال: 0.00166667">
                </div>
                <div class="form-group">
                    <label>يسري من *</label>
                    <input type="date" name="effective_from" required aria-label="تاريخ بدء سريان السعر" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="emsf_230_40a3b">المصدر</label>
                    <input type="text" name="source" maxlength="32" placeholder="بنك مركزي · قرار إداري" id="emsf_230_40a3b">
                </div>
                <div class="form-group fin-cur-span-all">
                    <label for="emsf_231_486e6">ملاحظة</label>
                    <input type="text" name="note" maxlength="255" id="emsf_231_486e6">
                </div>
            </div></div>
            <div class="fin-cur-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ السعر</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <h5 class="fin-cur-h5"><i class="fas fa-coins"></i> العملات وأسعارها</h5>
        <div class="fin-cur-scroll">
        <table class="table table-striped fin-cur-table">
            <thead>
                <tr>
                    <th>من عملة</th><th>الرمز</th><th>الحالة</th>
                    <th>السعر</th><th>يسري من</th><th>التاريخ</th><th>بانتظار سعر</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">إلى عملة</th>
                    <th class="ems-fn-th" data-fn="1">مصدر السعر</th>
                    <th class="ems-fn-th" data-fn="1">نوع السعر</th>
                    <th class="ems-fn-th" data-fn="1">المستند المرجعي</th>
                    <th class="ems-fn-th" data-fn="1">اعتمده</th>
                    <th class="ems-fn-th" data-fn="1">سار من</th>
                    <th class="ems-fn-th" data-fn="1">سار إلى</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $code => $c):
                $isBase  = !empty($c['is_base']);
                $list    = isset($rates[$code]) ? $rates[$code] : array();
                $current = null;
                $today   = date('Y-m-d');
                foreach ($list as $r) { if ($r['effective_from'] <= $today) { $current = $r; break; } }
                $waitE = isset($pending[$code]['events']) ? $pending[$code]['events'] : 0;
                $waitD = isset($pending[$code]['dues'])   ? $pending[$code]['dues']   : 0;
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string) $c['name_ar']); ?></strong></td>
                    <td><code><?php echo htmlspecialchars((string) $code); ?></code></td>
                    <td>
                        <?php if ($isBase): ?>
                            <span class="badge badge-primary">عملة الأساس</span>
                        <?php elseif (empty($c['active'])): ?>
                            <span class="badge badge-secondary">موقوفة</span>
                        <?php else: ?>
                            <span class="badge badge-success">سارية</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isBase): ?>
                            <span class="fin-cur-muted">1 (نفسها)</span>
                        <?php elseif ($current !== null): ?>
                            <strong><?php echo htmlspecialchars(rtrim(rtrim((string) $current['rate_to_base'], '0'), '.')); ?></strong>
                        <?php else: ?>
                            <span class="badge badge-warning">لا سعر بعد</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $current !== null ? htmlspecialchars((string) $current['effective_from']) : '—'; ?></td>
                    <td><?php echo count($list); ?> سعرا</td>
                    <td>
                        <?php if ($waitE + $waitD > 0): ?>
                            <span class="badge badge-warning"><?php echo $waitE; ?> حدثا · <?php echo $waitD; ?> ذمة</span>
                        <?php else: ?>
                            <span class="fin-cur-ok">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-body">
        <h5 class="fin-cur-h5"><i class="fas fa-plus"></i> عملة جديدة</h5>
        <p class="fin-cur-hint">
            عملة الأساس تعلن في بيانات الشركة ولا تختار هنا — وما يسجل هنا يقاس بها.
        </p>
        <form action="" method="post" class="allforms allforms-visible fin-cur-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="add_currency" value="1">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_232_537f9">الرمز (ISO) *</label>
                    <input type="text" name="code" required maxlength="3" placeholder="EUR" class="fin-cur-upper" id="emsf_232_537f9"></div>
                <div class="form-group"><label for="emsf_233_f36df">الاسم *</label>
                    <input type="text" name="name_ar" required maxlength="64" placeholder="اليورو" id="emsf_233_f36df"></div>
                <div class="form-group"><label for="emsf_234_171ec">الرمز المختصر</label>
                    <input type="text" name="symbol" maxlength="8" placeholder="€" id="emsf_234_171ec"></div>
            </div></div>
            <div class="fin-cur-actions">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-save"></i> تسجيل العملة</button>
            </div>
        </form>
    </div></div>
    <?php endif; ?>

</div>

</body>
</html>
