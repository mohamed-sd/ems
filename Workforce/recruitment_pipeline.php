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
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-user-plus"></i> دورةُ التوظيف — عشرُ خطواتٍ من الشاغر إلى التثبيت</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px">
    <form method="post" class="ems-form" style="display:flex;gap:8px;align-items:end">
      <input type="hidden" name="new_vacancy" value="1">
      <div><label>① طلبُ شاغرٍ جديد</label><input type="text" name="title_text" class="form-control" placeholder="المسمّى" required></div>
      <div><label>السبب</label><input type="text" name="reason" class="form-control"></div>
      <button class="btn btn-primary">افتح وانشر</button>
    </form>
    <form method="post" class="ems-form" style="display:flex;gap:8px;align-items:end">
      <input type="hidden" name="new_applicant" value="1">
      <div><label>③ سيرةٌ لمتقدم</label>
        <select name="vac_id" class="form-control" required><option value="">— الشاغر —</option>
          <?php foreach ($vacs as $v): ?><option value="<?= intval($v['vac_id']) ?>"><?= htmlspecialchars($v['vacancy_no'] . ' — ' . $v['title_text'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
      <div><label>الاسم</label><input type="text" name="applicant_name" class="form-control" required></div>
      <div><label>الهاتف</label><input type="text" name="applicant_phone" class="form-control" style="max-width:130px"></div>
      <div><label>مرجعُ السيرة</label><input type="text" name="cv_ref" class="form-control" style="max-width:130px"></div>
      <button class="btn btn-primary">سجّل</button>
    </form>
  </div>

  <table class="table table-striped" data-no-dt>
    <thead><tr><th>#</th><th>المتقدم</th><th>الشاغر</th><th>الخطوة</th><th>الاختبار</th><th>تقدّم</th><th>رفض</th></tr></thead>
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
              <input type="number" step="0.5" name="test_score" class="form-control form-control-sm" placeholder="الدرجة" style="max-width:90px">
            <?php endif; ?>
            <?php if ($ORDER[$idx + 1] === 'onboarded'): ?>
              <input type="number" name="employee_id" class="form-control form-control-sm" placeholder="رقم الموظف" style="max-width:110px" required>
            <?php endif; ?>
            <input type="text" name="stage_note" class="form-control form-control-sm" placeholder="ملاحظة" style="max-width:130px">
            <button class="action-btn" type="submit">← <?= $nextLabel ?></button>
          </form>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:flex;gap:4px">
            <input type="hidden" name="reject_app" value="<?= intval($a2['app_id']) ?>">
            <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="السبب" style="max-width:110px" required>
            <button class="action-btn" type="submit" style="color:#dc3545">رفض</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
