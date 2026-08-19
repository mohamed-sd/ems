<?php
/**
 * Financing/fin_models.php — نماذج التمويل ومعالجتها (CMP-03 ⑥ v2 — بتصميم SCR-DES حرفيًّا + إدخال حي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 13 · التمويل والملكية · الأعمدة 19 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. الصفوف في المخزن البيني `cmp03_screen_rows` (معزول
 * بالكيان) حتى يولد جدول الشاشة الأصلي — مهمة اللحاق في
 * docs/CMP03_FOLLOWUP_SOURCES_ar.md. الفائض فوق 22 عمودًا منهارٌ (توصية ①).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

require_once __DIR__ . '/../includes/cmp03_local_store.php'; // الموجة ٢ — الجدول الأصلي

$CANONICAL = 'fin_models.php';
$COLS   = array (
  0 => 'رمز النموذج',
  1 => 'اسم النموذج',
  2 => 'المالك القانوني',
  3 => 'المنتفع الاقتصادي',
  4 => 'الاعتراف المحاسبي',
  5 => 'حامل الإهلاك',
  6 => 'مرتهن الضمان',
  7 => 'معالجة الالتزام',
  8 => 'معالجة العائد',
  9 => 'المرجع المحاسبي',
  10 => 'اعتمده المراجع',
  11 => 'تاريخ الاعتماد',
  12 => 'الحالة',
  13 => 'الكيان',
  14 => 'المُنشئ — الاسم والصفة',
  15 => 'تاريخ الإنشاء',
  16 => 'مرجع التفويض',
  17 => 'المرفق',
  18 => 'سجل الاطّلاع',
);
$FIELDS = array (
  0 => 'رمز النموذج',
  1 => 'اسم النموذج',
  2 => 'المالك القانوني',
  3 => 'المنتفع الاقتصادي',
  4 => 'الاعتراف المحاسبي',
  5 => 'حامل الإهلاك',
  6 => 'مرتهن الضمان',
  7 => 'معالجة الالتزام',
  8 => 'معالجة العائد',
  9 => 'المرجع المحاسبي',
  10 => 'اعتمده المراجع',
  11 => 'تاريخ الاعتماد',
  12 => 'الحالة',
  13 => 'مرجع التفويض',
  14 => 'المرفق',
);

/* ══ INJ-0003 (P0) — «لكلِّ بيانٍ موضعٌ واحدٌ يُنشأ فيه ويُعدَّل» ═══════════
   كانت هذه الشاشةُ تكتب وتقرأ من المخزنِ البينيِّ `scr_fin_models`، بينما
   **جدولُ المجال** `financing_models` هو الذي يقرؤه منشئُ العملية
   (`financing_operation_new.php:62`) ويتحقق منه `FinancingService::createOperation`
   ويرفض 422 إن لم يجد النموذج ⇒ **نموذجٌ يُضاف من شاشتِه لا يوجد حين يُنشأ به
   عملية**. الآن: مخزنٌ واحدٌ هو جدولُ المجال، والقيمُ محكومةٌ بقوائمِ ENUM
   (والخارجُ عنها يُرفض برسالةٍ — فـENUM يبتلع الغريبَ صامتًا). */
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Financing/FinancingModelService.php';

// CS-01 — الحارسُ فوقَ المعالج.
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$fm_svc = new \App\Services\Financing\FinancingModelService($conn);
$fm_msg = '';

$__pc = ems_post_contract($conn, array(
    'action'  => 'fin.model.upsert',
    'perm'    => 'can_add',
    'trigger' => 'model_code',
    'idem'    => array('code' => trim((string) ($_POST['model_code'] ?? ''))),
    'validate' => function (array $in) {
        if (trim((string) ($in['model_code'] ?? '')) === '') {
            return array('ok' => false, 'msg' => 'رمزُ النموذجِ إلزامي (422)');
        }
        return array('ok' => true, 'data' => $in);
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $fm_msg = $__pc['msg']; }
if ($__pc['replay'])                     { $fm_msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $res = $fm_svc->upsert($__pc['data'], $uid);
    $fm_msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'financing_models:' . $res['code']); }
}

/* ── القراءة: من **جدولِ المجالِ** لا من المخزنِ البينيِّ ─────────────────
   وتُعاد بشكلِ الشاشةِ القديم (id·payload·status) فلا تُعاد كتابةُ الجدول. */
$fm_models = $fm_svc->all(false);
$rows = array();
foreach ($fm_models as $i => $m) {
    $rows[] = array(
        'id' => $i + 1,
        'payload' => array(
            'رمز النموذج'        => $m['model_code'],
            'اسم النموذج'        => $m['name_ar'],
            'المالك القانوني'    => \App\Services\Financing\FinancingModelService::LEGAL_OWNER[$m['legal_owner_effect']] ?? $m['legal_owner_effect'],
            'المنتفع الاقتصادي'  => \App\Services\Financing\FinancingModelService::BENEFICIARY[$m['economic_beneficiary']] ?? $m['economic_beneficiary'],
            'الاعتراف المحاسبي'  => \App\Services\Financing\FinancingModelService::RECOGNITION[$m['accounting_recognition']] ?? $m['accounting_recognition'],
            'حامل الإهلاك'       => $m['depreciation_bearer'],
            'مرتهن الضمان'       => $m['security_interest_holder'] ?: '—',
            'المرجع المحاسبي'    => $m['policy_doc_ref'],
            'اعتمده المراجع'     => $m['approved_by'] ? ('#' . $m['approved_by']) : '—',
            'تاريخ الاعتماد'     => $m['approved_at'] ?: '—',
        ),
        'status'          => ((int) $m['active'] === 1 ? 'نافذ' : 'معطَّل'),
        'created_by_name' => $m['approved_by'] ? ('#' . $m['approved_by']) : '—',
        'created_at'      => $m['approved_at'] ?: '',
        'is_seed'         => 0,
    );
}

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية العمود من الصف — الحوكمة الآلية حية وسائرها من الحمولة أو «—» */
function cmp03_cell($col, $row, $entityName) {
    $n = cmp03_screen_norm($col);
    if ($n === cmp03_screen_norm('الكيان')) { return $entityName; }
    if ($n === cmp03_screen_norm('المُنشئ — الاسم والصفة') || $n === cmp03_screen_norm('الجهة المُنشئة')) {
        return $row['created_by_name'] ?: '—';
    }
    if ($n === cmp03_screen_norm('تاريخ الإنشاء')) { return $row['created_at']; }
    if ($n === cmp03_screen_norm('الحالة')) { return $row['status']; }
    if ($n === cmp03_screen_norm('مفتاح منع التكرار')) { return 'CMP03-' . intval($row['id']); }
    if (isset($row['payload'][$col]) && $row['payload'][$col] !== '') { return $row['payload'][$col]; }
    return '—';
}
/** تطبيع محلي خفيف (مرآة cmp03_norm دون جر مكتبة الأدوات للويب) */
function cmp03_screen_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    $s = str_replace(array('أ','إ','آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    return preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
}

$page_title = 'إيكوبيشن | نماذج التمويل ومعالجتها';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'نماذج التمويل ومعالجتها';
    $header_icon = 'fa fa-file-invoice';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo ems_states_bundle('لا نماذجَ تمويلٍ معرَّفةً بعدُ', 'أضف أولَ نموذجٍ بزرِّ «إضافة» — والنموذجُ المعرَّفُ هنا هو ما تُنشأ به عملياتُ التمويل');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('financing', 'الممولُ والشروط'); ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <?php if ($fm_msg !== ''): ?>
      <div class="alert <?= (mb_strpos($fm_msg, '✅') !== false ? 'alert-success' : 'alert-danger') ?>">
        <?= htmlspecialchars($fm_msg, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- INJ-0003: الحقولُ صارت أسماءَ أعمدةِ **جدولِ المجال**، والمحكومةُ منها
         قوائمَ مغلقةً — فـENUM يبتلع القيمةَ الغريبةَ صامتًا، والنصُّ الحرُّ كان
         يُكتب في مخزنٍ بينيٍّ لا يقرؤه أحد. -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <?= csrf_field() ?>
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة/تحديث — نماذج التمويل ومعالجتها</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <?php $FMS = '\\App\\Services\\Financing\\FinancingModelService'; ?>
                <div class="form-group"><label for="fm_code">رمز النموذج <small>(لاتينيٌّ صغيرٌ وأرقامٌ و_)</small></label>
                    <input type="text" name="model_code" required maxlength="32" pattern="[a-z0-9_]{2,32}" id="fm_code"></div>
                <div class="form-group"><label for="fm_name">اسم النموذج</label>
                    <input type="text" name="name_ar" required maxlength="120" id="fm_name"></div>
                <div class="form-group"><label for="fm_owner">أثرُ الملكية القانونية</label>
                    <select name="legal_owner_effect" required id="fm_owner">
                        <option value="">— اختر —</option>
                        <?php foreach ($FMS::LEGAL_OWNER as $k => $v): ?>
                          <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="fm_benef">المنتفع الاقتصادي</label>
                    <select name="economic_beneficiary" required id="fm_benef">
                        <option value="">— اختر —</option>
                        <?php foreach ($FMS::BENEFICIARY as $k => $v): ?>
                          <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="fm_recog">الاعتراف المحاسبي</label>
                    <select name="accounting_recognition" required id="fm_recog">
                        <option value="">— اختر —</option>
                        <?php foreach ($FMS::RECOGNITION as $k => $v): ?>
                          <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="fm_bearer">حامل الإهلاك</label>
                    <input type="text" name="depreciation_bearer" required maxlength="60" id="fm_bearer"></div>
                <div class="form-group"><label for="fm_holder">مرتهن الضمان <small>(اختياري)</small></label>
                    <input type="text" name="security_interest_holder" maxlength="60" id="fm_holder"></div>
                <div class="form-group"><label for="fm_policy">المرجع المحاسبي — سندُ السياسة</label>
                    <input type="text" name="policy_doc_ref" required maxlength="160" id="fm_policy"></div>
                <div class="form-group"><label for="fm_active">الحالة</label>
                    <select name="active" id="fm_active">
                        <option value="1">نافذ</option>
                        <option value="0">معطَّل</option>
                    </select></div>
            </div></div>
            <div class="cmp03-form-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="fin_modelsTable">
            <thead><tr>
            <th>رمز النموذج</th>
            <th>اسم النموذج</th>
            <th>المالك القانوني</th>
            <th>المنتفع الاقتصادي</th>
            <th>الاعتراف المحاسبي</th>
            <th>حامل الإهلاك</th>
            <th>مرتهن الضمان</th>
            <th>معالجة الالتزام</th>
            <th>معالجة العائد</th>
            <th>المرجع المحاسبي</th>
            <th>اعتمده المراجع</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="19" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach ($COLS as $c): $v = cmp03_cell($c, $r, $entityName); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
