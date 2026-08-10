<?php
/**
 * Workforce/recruitment_pipeline.php — دورةُ التوظيف (★ · update0007-ب F7)
 * ───────────────────────────────────────────────────────────────────────────
 * الخطواتُ العشرُ الغائبة (NAV-02 §12-①): طلبُ شاغرٍ ← نشرٌ ← سيرٌ ← فرزٌ ←
 * مقابلاتٌ ← اختبارٌ عمليٌّ ← عرضٌ ← قبولُه ← تعاقدٌ ← مباشرةٌ ← تجربة.
 * التقدمُ خطوةً خطوةً بلا قفزٍ — وكلُّ انتقالٍ سطرٌ في rec_stage_log.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

/* الخطواتُ بترتيبها — القفزُ ممنوعٌ والرفضُ/الانسحابُ خروجٌ من أيٍّ */
$STAGES = array(
    'received'       => 'وصلت السيرة',
    'screening'      => 'الفرز',
    'interview'      => 'المقابلة',
    'practical_test' => 'الاختبار العملي',
    'offer'          => 'العرض الوظيفي',
    'offer_accepted' => 'قبول العرض',
    'contracting'    => 'التعاقد',
    'onboarded'      => 'المباشرة',
    'probation'      => 'فترة التجربة',
    'confirmed'      => 'مثبَّت',
);
$ORDER = array_keys($STAGES);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['new_vacancy'])) {
        $tt = trim($_POST['title_text'] ?? '');
        if ($tt === '') { $msg = 'مسمّى الشاغر إلزامي (422)'; }
        else {
            $no = 'VAC-' . date('ym') . '-' . str_pad(strval(rand(1, 999)), 3, '0', STR_PAD_LEFT);
            $st = mysqli_prepare($conn, "INSERT INTO rec_vacancies (company_id, vacancy_no, title_text, reason, state, posted_at, created_by)
                                         VALUES (?,?,?,?, 'open', CURDATE(), ?)");
            $rs = trim($_POST['reason'] ?? '');
            mysqli_stmt_bind_param($st, 'isssi', $company_id, $no, $tt, $rs, $uid);
            mysqli_stmt_execute($st);
            $msg = "فُتح الشاغرُ $no ونُشر";
        }
    } elseif (isset($_POST['new_applicant'])) {
        $vac = intval($_POST['vac_id'] ?? 0);
        $nm  = trim($_POST['applicant_name'] ?? '');
        if ($vac <= 0 || $nm === '') { $msg = 'الشاغرُ والاسمُ إلزاميان (422)'; }
        else {
            $cv = trim($_POST['cv_ref'] ?? '');
            $st = mysqli_prepare($conn, "INSERT INTO rec_applications (company_id, vac_id, applicant_name, applicant_phone, cv_ref)
                                         VALUES (?,?,?,?,?)");
            $ph = trim($_POST['applicant_phone'] ?? '');
            mysqli_stmt_bind_param($st, 'iisss', $company_id, $vac, $nm, $ph, $cv);
            mysqli_stmt_execute($st);
            $aid = mysqli_stmt_insert_id($st);
            mysqli_query($conn, "INSERT INTO rec_stage_log (app_id, from_stage, to_stage, by_person) VALUES ($aid, NULL, 'received', $uid)");
            $msg = "سُجّل المتقدمُ #$aid";
        }
    } elseif (isset($_POST['advance_app'])) {
        $aid  = intval($_POST['advance_app']);
        $note = trim($_POST['stage_note'] ?? '');
        $r = mysqli_query($conn, "SELECT stage FROM rec_applications WHERE app_id = $aid AND company_id = $company_id");
        if ($r && ($a = mysqli_fetch_assoc($r))) {
            $idx = array_search($a['stage'], $ORDER, true);
            if ($idx === false || $idx >= count($ORDER) - 1) { $msg = 'لا خطوةَ تاليةً — مثبَّتٌ أو خارجٌ (409)'; }
            else {
                $next = $ORDER[$idx + 1];
                // المباشرةُ تستلزم موظفًا حقيقيًّا — لا onboarded بلا employee_id (شاهد §11-⑭: لا يُباشر من لم تكتمل وثائقُه)
                if ($next === 'onboarded' && intval($_POST['employee_id'] ?? 0) <= 0) {
                    $msg = 'المباشرةُ تستلزم ربطَ الموظف المعيَّن (employee_id) — 422';
                } else {
                    $extra = '';
                    if ($next === 'onboarded') $extra = ", employee_id = " . intval($_POST['employee_id']);
                    if ($next === 'probation') $extra = ", probation_end = DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
                    if ($a['stage'] === 'practical_test' && isset($_POST['test_score']) && $_POST['test_score'] !== '')
                        $extra .= ", test_score = " . floatval($_POST['test_score']);
                    mysqli_query($conn, "UPDATE rec_applications SET stage = '$next'$extra WHERE app_id = $aid");
                    mysqli_query($conn, "INSERT INTO rec_stage_log (app_id, from_stage, to_stage, note, by_person)
                                         VALUES ($aid, '" . $a['stage'] . "', '$next', '" . mysqli_real_escape_string($conn, $note) . "', $uid)");
                    $msg = 'تقدّم إلى: ' . $STAGES[$next];
                }
            }
        }
    } elseif (isset($_POST['reject_app'])) {
        $aid = intval($_POST['reject_app']);
        $why = trim($_POST['reject_reason'] ?? '');
        if ($why === '') { $msg = 'سببُ الرفض إلزامي (422)'; }
        else {
            $r = mysqli_query($conn, "SELECT stage FROM rec_applications WHERE app_id = $aid AND company_id = $company_id");
            $from = $r && ($a = mysqli_fetch_assoc($r)) ? $a['stage'] : null;
            mysqli_query($conn, "UPDATE rec_applications SET stage = 'rejected', stage_note = '" . mysqli_real_escape_string($conn, $why) . "' WHERE app_id = $aid");
            mysqli_query($conn, "INSERT INTO rec_stage_log (app_id, from_stage, to_stage, note, by_person)
                                 VALUES ($aid, " . ($from ? "'$from'" : 'NULL') . ", 'rejected', '" . mysqli_real_escape_string($conn, $why) . "', $uid)");
            $msg = 'رُفض بسببٍ موثَّق';
        }
    }
}

$vacs = array(); $apps = array();
$r = mysqli_query($conn, "SELECT vac_id, vacancy_no, title_text, state FROM rec_vacancies
                          WHERE company_id = $company_id AND state = 'open' ORDER BY vac_id DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $vacs[] = $x;
$r = mysqli_query($conn, "SELECT a.*, v.vacancy_no, v.title_text FROM rec_applications a
                          JOIN rec_vacancies v ON v.vac_id = a.vac_id
                          WHERE a.company_id = $company_id AND a.stage NOT IN ('rejected','withdrawn','confirmed')
                          ORDER BY FIELD(a.stage, 'received','screening','interview','practical_test','offer',
                                         'offer_accepted','contracting','onboarded','probation'), a.app_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $apps[] = $x;

$page_title = 'دورة التوظيف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-user-plus';
$header_title_html = htmlspecialchars('دورةُ التوظيف — عشرُ خطواتٍ من الشاغر إلى التثبيت', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px">
    <form method="post" class="ems-form" style="display:flex;gap:8px;align-items:end">
      <input type="hidden" name="new_vacancy" value="1">
      <div><label for="emsf_1785_5c43b">① طلبُ شاغرٍ جديد</label><input type="text" name="title_text" class="form-control" placeholder="المسمّى" required id="emsf_1785_5c43b"></div>
      <div><label for="emsf_1786_048c7">السبب</label><input type="text" name="reason" class="form-control" id="emsf_1786_048c7"></div>
      <button class="btn btn-primary">افتح وانشر</button>
    </form>
    <form method="post" class="ems-form" style="display:flex;gap:8px;align-items:end">
      <input type="hidden" name="new_applicant" value="1">
      <div><label for="emsf_1787_75453">③ سيرةٌ لمتقدم</label>
        <select name="vac_id" class="form-control" required id="emsf_1787_75453"><option value="">— الشاغر —</option>
          <?php foreach ($vacs as $v): ?><option value="<?= intval($v['vac_id']) ?>"><?= htmlspecialchars($v['vacancy_no'] . ' — ' . $v['title_text'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
      <div><label for="emsf_1788_6b93b">الاسم</label><input type="text" name="applicant_name" class="form-control" required id="emsf_1788_6b93b"></div>
      <div><label for="emsf_1789_0744a">الهاتف</label><input type="text" name="applicant_phone" class="form-control" style="max-width:130px" id="emsf_1789_0744a"></div>
      <div><label for="emsf_1790_cebd8">مرجعُ السيرة</label><input type="text" name="cv_ref" class="form-control" style="max-width:130px" id="emsf_1790_cebd8"></div>
      <button class="btn btn-primary">سجّل</button>
    </form>
  </div>

  <table class="table table-striped" data-no-dt>
    <thead><tr><th>#</th><th>المتقدم</th><th>سبب الشاغر</th><th>الخطوة</th><th>الاختبار</th><th>تقدّم</th><th>رفض</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
              <th class="ems-fn-th" data-fn="1">الإدارة الطالبة</th>
              <th class="ems-fn-th" data-fn="1">المسمى المطلوب</th>
              <th class="ems-fn-th" data-fn="1">عدد الشواغر</th>
              <th class="ems-fn-th" data-fn="1">المؤهل المطلوب</th>
              <th class="ems-fn-th" data-fn="1">الخبرة المطلوبة</th>
              <th class="ems-fn-th" data-fn="1">الموقع</th>
              <th class="ems-fn-th" data-fn="1">نوع العقد</th>
              <th class="ems-fn-th" data-fn="1">الأجر المقترح</th>
              <th class="ems-fn-th" data-fn="1">اعتماد الموارد</th>
              <th class="ems-fn-th" data-fn="1">اعتماد المالية</th>
              <th class="ems-fn-th" data-fn="1">المرشح المختار</th>
              <th class="ems-fn-th" data-fn="1">تاريخ العرض</th>
              <th class="ems-fn-th" data-fn="1">تاريخ القبول</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ المباشرة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($apps)): ?><tr><td colspan="7" class="text-center text-muted">لا متقدمين في الدورة</td></tr><?php endif; ?>
    <?php foreach ($apps as $a2):
        $idx = array_search($a2['stage'], $ORDER, true);
        $nextLabel = ($idx !== false && $idx < count($ORDER) - 1) ? $STAGES[$ORDER[$idx + 1]] : null; ?>
      <tr>
        <td><?= intval($a2['app_id']) ?></td>
        <td><?= htmlspecialchars($a2['applicant_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($a2['vacancy_no'] . ' — ' . $a2['title_text'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="badge" style="background:#0d6efd"><?= ($idx + 1) . '/10 · ' . $STAGES[$a2['stage']] ?></span></td>
        <td><?= $a2['test_score'] !== null ? floatval($a2['test_score']) : '—' ?></td>
        <td>
          <?php if ($nextLabel): ?>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="advance_app" value="<?= intval($a2['app_id']) ?>">
            <?php if ($a2['stage'] === 'practical_test'): ?>
              <input type="number" step="0.5" name="test_score" class="form-control form-control-sm" placeholder="الدرجة" style="max-width:90px" aria-label="الدرجة">
            <?php endif; ?>
            <?php if ($ORDER[$idx + 1] === 'onboarded'): ?>
              <input type="number" name="employee_id" class="form-control form-control-sm" placeholder="رقم الموظف" style="max-width:110px" required aria-label="رقم الموظف">
            <?php endif; ?>
            <input type="text" name="stage_note" class="form-control form-control-sm" placeholder="ملاحظة" style="max-width:130px" aria-label="ملاحظة">
            <button class="action-btn" type="submit">← <?= $nextLabel ?></button>
          </form>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:flex;gap:4px">
            <input type="hidden" name="reject_app" value="<?= intval($a2['app_id']) ?>">
            <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="السبب" style="max-width:110px" required aria-label="السبب">
            <button class="action-btn" type="submit" style="color:#dc3545">رفض</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
