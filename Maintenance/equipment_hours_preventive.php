<?php
/**
 * Maintenance/equipment_hours_preventive.php — ساعاتُ المعدة والوقائية
 * (NAV-01 v6 §6.1 «قاعدةُ الصيانة» · update0007 S-06)
 * ───────────────────────────────────────────────────────────────────────────
 * «التايم شيتُ عند الصيانة ليس نسخةً — هو الساعاتُ المتراكمةُ للمعدة مقابل
 * جدول الغيار: فيظهر (متبقٍّ ثلاثون ساعةً على الغيار) لا (مَن عمل اليوم).
 * البياناتُ واحدةٌ والسؤالُ مختلف.»
 *
 * المصادر: unit_entries (ساعاتُ المعدة المعتمدة) · mnt_plan (خططُ الوقائية
 * بأساس العدّاد last_done_meter/interval_value).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/unit_chain_helpers.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = !empty($_SESSION['user']['is_super_admin']);
$cw = $is_super ? '1=1' : "mp.company_id = $company_id";

/* لكل خطةٍ وقائيةٍ بأساس العدّاد: المتراكمُ منذ آخر غيارٍ والمتبقي
   ◆ والحالاتُ المقبولةُ من التعريفِ المركزيِّ الواحد (`ems_uc_accepted_sql`) —
     لا من قائمةٍ مكتوبةٍ هنا تتقادم مع أوّلِ هجرةٍ تمسُّ التعداد (INJ-0334). */
$ue_ok = ems_uc_accepted_sql('ue');
$rows = array();
$sql = "SELECT mp.id, mp.name AS plan_name, mp.interval_value, mp.tolerance,
               mp.last_done_meter, mp.last_done_date, mp.next_due_meter,
               e.id AS eq_id, e.name AS eq_name,
               COALESCE((SELECT SUM(ue.qty) FROM unit_entries ue
                         WHERE ue.equipment_id = e.id AND $ue_ok
                           AND (mp.last_done_date IS NULL OR ue.entry_date > mp.last_done_date)), 0) AS hours_since
        FROM mnt_plan mp
        JOIN equipments e ON e.id = mp.equipment_id
        WHERE $cw AND mp.is_deleted = 0 AND mp.state <> 'Retired'
          AND mp.trigger_basis IN ('meter','hours','Meter')
        ORDER BY (mp.interval_value - COALESCE((SELECT SUM(ue.qty) FROM unit_entries ue
                  WHERE ue.equipment_id = e.id AND $ue_ok
                    AND (mp.last_done_date IS NULL OR ue.entry_date > mp.last_done_date)), 0)) ASC";
$res = mysqli_query($conn, $sql);
if ($res) { while ($x = mysqli_fetch_assoc($res)) $rows[] = $x; }

$page_title = 'ساعاتُ المعدة والوقائية';
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
$header_icon = 'fa fa-stopwatch';
$header_title_html = htmlspecialchars('ساعاتُ المعدة مقابل جدول الغيار', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا خططَ وقائيةً بأساس عدّادِ الساعات', 'اربطِ الخطةَ الوقائيةَ بمعدةٍ واجعل أساسَ تكرارِها ساعاتٍ من شاشةِ الصيانةِ الوقائية');
?>
  <p class="text-muted mnt-eh-note">زاويةُ الصيانة للتايم شيت: المتراكمُ منذ آخر إنجازٍ مقابل الفترة — لا «من عمل اليوم».</p>

  <table class="table table-striped" data-no-dt>
    <thead><tr>
      <th>المعدة</th><th>الخطة</th><th>الفترة (ساعة)</th>
      <th>المتراكمُ منذ آخر غيار</th><th>المتبقي</th><th>الحالة</th>
      <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
      <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
      <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
      <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
      <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
      <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
      <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
      <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
      </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="text-center text-muted">لا خططَ وقائيةً بأساس العدّاد</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $interval = floatval($r['interval_value']);
        $since    = floatval($r['hours_since']);
        $left     = $interval - $since;
        $tol      = floatval($r['tolerance']);
        // UXW-01 ①: نبرةُ الشارةِ صنفٌ مُعلَنٌ في كتلةِ أنماطِ الشاشةِ لا لونٌ مثبَّتٌ في الوسم
        if ($left < 0)          { $badge = array('mnt-eh-badge--over', 'متجاوزة'); }
        elseif ($left <= $tol)  { $badge = array('mnt-eh-badge--due', 'مستحقةٌ الآن'); }
        elseif ($left <= $interval * 0.15) { $badge = array('mnt-eh-badge--near', 'تقترب'); }
        else                    { $badge = array('mnt-eh-badge--ok', 'ضمن الفترة'); }
    ?>
      <tr>
        <td><a href="../Equipments/equipment_profile.php?id=<?= intval($r['eq_id']) ?>"><?= htmlspecialchars($r['eq_name'], ENT_QUOTES, 'UTF-8') ?></a></td>
        <td><?= htmlspecialchars($r['plan_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format($interval, 0) ?></td>
        <td><?= number_format($since, 1) ?></td>
        <td><strong><?= number_format($left, 1) ?></strong> ساعة</td>
        <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<style>
    /* UXW-01 ①②: نبراتُ شارةِ الاستحقاقِ برموزِ اللوحة، والنمطُ الموضعيُّ صنفًا */
    .mnt-eh-note { font-size:.9em; }
    .mnt-eh-badge--over { background:var(--c-dc3545); }
    .mnt-eh-badge--due { background:var(--c-fd7e14, #fd7e14); }
    .mnt-eh-badge--near { background:var(--c-ffc107); }
    .mnt-eh-badge--ok { background:var(--c-198754); }
</style>
