<?php
/**
 * Portal/dept_achievement.php — إنجازُ الإدارة (NAV-01 v6 §7-④ · update0007 S-05)
 * ───────────────────────────────────────────────────────────────────────────
 * «مجموعةٌ في كل قائمةِ إدارةٍ تعرض إنجازَ الإدارة — لا إنجازَ الفرد؛
 * فإنجازُ الفرد شخصيٌّ وإنجازُ الإدارة إداري.»
 *
 * يجمع من مصادر الدورة الحية بحسب وحدة إدارة المستخدم (خريطةُ dept_inbox):
 * وحداتٌ معتمدة · بلاغاتٌ أُغلقت · أوامرُ صيانةٍ أُقفلت — بمدةٍ حرة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../Tickets/tkt_helpers.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role       = intval($_SESSION['user']['role'] ?? 0);
/* الافتراضُ آخرُ تسعين يومًا لا الشهرُ الجاري — فمطلعُ الشهرِ نافذةٌ خاويةٌ تُوهم أن لا إنجاز */
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-90 days'));
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '')   ? $_GET['to']   : date('Y-m-d');

/* وحدةُ الإدارة من خريطة الدور (المصححة في update0007 G-04) */
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';
$unit = ems_dept_unit_of_role($role);

$q = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql);
    return $r ? floatval(mysqli_fetch_row($r)[0] ?? 0) : 0; };
$cw = "company_id = $company_id";

/**
 * ح-07 · نسبةُ المؤشرات إلى الإدارة فعلًا — لا إلى الشركة كلِّها.
 * ───────────────────────────────────────────────────────────────────────────
 * كانت ثلاثةٌ من أربعةٍ ترشّح بـ company_id وحدَه، فيرى كلُّ دورٍ أرقامَ الشركة
 * منسوبةً إليه (المبيعاتُ ترى 48 ألف وحدةٍ وهي لا تُدخل وحدةً واحدة).
 *
 * لا عمودَ وحدةٍ تنظيميةٍ في unit_entries ولا في mnt_order — والمنسبُ الحقيقيُّ
 * هو **فاعلُ الواقعة**: unit_entries.entered_by (ممتلئٌ 100٪) و
 * mnt_order.created_by. فنجمع مستخدمي الإدارة ونرشّح بهم.
 *
 * والإدارةُ وحدةٌ لا دور: 13 و14 صيانةٌ واحدة · 17–22 ماليةٌ واحدة — فنأخذ كلَّ
 * الأدوار التي تشترك في وحدةِ الدور الحالي (خريطةُ dept_inbox_map).
 */
$unit_roles = array();
if (intval($unit) > 0) {
    for ($rid = 1; $rid <= 40; $rid++) {
        if (ems_dept_unit_of_role($rid) === intval($unit)) { $unit_roles[] = $rid; }
    }
}
// دورٌ بلا وحدةٍ في الخريطة ⇒ لا مستخدمين ⇒ أصفارٌ صريحةٌ لا أرقامُ شركةٍ مضلِّلة
$roles_in   = count($unit_roles) ? implode(',', $unit_roles) : '0';
$dept_users = "SELECT id FROM users WHERE company_id = $company_id AND role IN ($roles_in)";
require_once __DIR__ . '/../includes/unit_chain_helpers.php';
$ue_state   = ems_uc_accepted_sql('unit_entries');   // التعريفُ المركزيُّ الواحد (INJ-0334)

/**
 * ح-12 · مؤشراتُ الإدارة من **دورتها هي** لا من دورة غيرها.
 * ───────────────────────────────────────────────────────────────────────────
 * كانت الأربعةُ العامة (وحدات/ساعات/صيانة) أصفارًا دائمةً للمشتريات — فالإدارةُ
 * لا تُدخل وحداتٍ ولا تقفل أوامرَ صيانة. مؤشراتُها من جداولها: أوامرُ صدرت ·
 * استلاماتٌ · مطابقاتٌ · ذممٌ فُتحت · صرفيات (والبلاغاتُ مؤشرٌ مشتركٌ يبقى).
 */
if (intval($unit) === 11) {   // المشتريات
    $metrics = array(
        array('أوامر شراء صدرت (غادرت المسودة)', $q("SELECT COUNT(*) FROM proc_order WHERE $cw
              AND COALESCE(is_deleted,0)=0 AND state <> 'مسودة'
              AND created_at BETWEEN '$from' AND '$to 23:59:59'")),
        array('عهد استلام سجلت', $q("SELECT COUNT(*) FROM proc_receipt_custody WHERE $cw
              AND COALESCE(is_deleted,0)=0
              AND created_at BETWEEN '$from' AND '$to 23:59:59'")),
        array('فواتير طوبقت (مطابقة ثلاثية)', $q("SELECT COUNT(*) FROM proc_order WHERE $cw
              AND COALESCE(is_deleted,0)=0 AND match_state = 'matched'
              AND matched_at BETWEEN '$from' AND '$to 23:59:59'")),
        array('ذمم موردين فتحت بالمطابقة', $q("SELECT COUNT(*) FROM fin_dues WHERE $cw
              AND party_type = 'proc_supplier' AND due_type = 'purchase'
              AND created_at BETWEEN '$from' AND '$to 23:59:59'")),
        array('صرفيات مخزون نفذت', $q("SELECT COUNT(*) FROM proc_issue WHERE $cw
              AND COALESCE(is_deleted,0)=0
              AND created_at BETWEEN '$from' AND '$to 23:59:59'")),
        array('مسارات بلاغات أغلقتها الإدارة', $q("SELECT COUNT(*) FROM ticket_workstreams ws
              JOIN tickets t ON t.id = ws.tk_id AND t.$cw
              WHERE ws.org_unit_id = " . intval($unit) . " AND ws.state = 'closed'
              AND ws.closed_at BETWEEN '$from' AND '$to 23:59:59'")),
    );
} else {
$metrics = array(
    array('وحدات معتمدة في المدة', $q("SELECT COUNT(*) FROM unit_entries WHERE $cw
          AND $ue_state AND entry_date BETWEEN '$from' AND '$to'
          AND entered_by IN ($dept_users)")),
    array('ساعات منفذة معتمدة', $q("SELECT COALESCE(SUM(qty),0) FROM unit_entries WHERE $cw
          AND $ue_state AND entry_date BETWEEN '$from' AND '$to'
          AND entered_by IN ($dept_users)")),
    array('مسارات بلاغات أغلقتها الإدارة', $q("SELECT COUNT(*) FROM ticket_workstreams ws
          JOIN tickets t ON t.id = ws.tk_id AND t.$cw
          WHERE ws.org_unit_id = " . intval($unit) . " AND ws.state = 'closed'
          AND ws.closed_at BETWEEN '$from' AND '$to 23:59:59'")),
    array('أوامر صيانة أقفلت', $q("SELECT COUNT(*) FROM mnt_order WHERE $cw
          AND state = 'إغلاق' AND updated_at BETWEEN '$from' AND '$to 23:59:59'
          AND created_by IN ($dept_users)")),
);
}

$page_title = 'إنجاز الإدارة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-chart-line';
$header_title_html = htmlspecialchars('إنجاز الإدارة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
echo ems_states_bundle('لا نشاط معتمدا للإدارة في هذه المدة', 'وسع «من/إلى» أعلاه أو تحقق من توفر السجلات');
?>
    <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
  <div class="filter">
      <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
      <div class="filter-body">
  <form method="get" class="ems-form ems-dach-filter">
    <div><label for="emsf_368_d3c6f">من</label><input type="date" name="from" class="form-control" id="emsf_368_d3c6f" aria-label="من — بداية المدة" value="<?= htmlspecialchars($from) ?>"></div>
    <div><label for="emsf_369_142f6">إلى</label><input type="date" name="to" class="form-control" id="emsf_369_142f6" aria-label="إلى — نهاية المدة" value="<?= htmlspecialchars($to) ?>"></div>
    <button class="btn btn-primary">عرض</button>
  </form>
      </div>
  </div>
  <?php $allZero = true; foreach ($metrics as $m2) if ($m2[1] > 0) { $allZero = false; break; }
  if ($allZero): ?>
    <div class="alert alert-warning">لا نشاط معتمدا في هذه المدة — وسع «من/إلى» أعلاه.</div>
  <?php endif; ?>
  <?php
  /* بطاقاتُ الإحصاءِ الموحَّدة — التصميمُ من `assets/css/ems-statcards.css` وحدَه.
     ◆ والأصنافُ تُكتب هنا **صراحةً** لا تُترك لعونِ الوسمِ `ems-statcards.js`:
       اسمُ `ems-dach-card` ليس من عائلةِ البطاقاتِ في العون (لا `stat` ولا `kpi`
       ولا `metric` ولا `tile`) فما كان يراها أصلًا — فبقيت بتصميمِها القديم
       (رماديٌّ فاتحٌ · قطر 8 · بلا ظلّ) بينما أخواتُها في النظامِ موحَّدة.
     ◆ وعدَدُ الأعمدةِ يُعلَن على الحاويةِ نفسِها (القسم ⑨ من الملف) لا يُنقَض
       من الشاشة، فتملأ البطاقاتُ العرضَ: أربعةٌ ⇐ أربعةٌ في صفٍّ واحد ·
       ستةٌ (المشتريات) ⇐ ثلاثةٌ في صفَّين. و`ems-statgrid--fill` يمدُّ الأخيرةَ
       على ما بقي من صفِّها فلا تبقى خانةٌ خاوية مهما تغيَّر العدَد. */
  $dach_n    = count($metrics);
  $dach_cols = ($dach_n % 4 === 0) ? 4 : (($dach_n % 3 === 0) ? 3 : max(2, min(6, $dach_n)));
  ?>
  <div class="ems-dach-cards ems-statgrid ems-statgrid--fill" data-cols="<?= (int) $dach_cols ?>">
    <?php foreach ($metrics as $m2): ?>
    <div class="ems-dach-card ems-statcard">
      <div class="ems-dach-num ems-statcard__value"><?= number_format($m2[1], is_float($m2[1]) && $m2[1] != intval($m2[1]) ? 1 : 0) ?></div>
      <div class="text-muted ems-statcard__title"><?= $m2[0] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="text-muted ems-dach-note">إنجاز الفرد في «إنجازي» بمساحة عملي — وهذا إنجاز الإدارة (NAV-01 §7-④).</p>
</div>
