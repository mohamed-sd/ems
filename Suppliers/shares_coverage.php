<?php
/**
 * Suppliers/shares_coverage.php — حصصُ الموردين والتغطية (★ · update0007-ب F12)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل موردٍ: التزامُه (Σ حاوياته) · المستهلَكُ (من دفتر القدرات محسوبًا لا
 * مخزَّنًا — CAP-01 §13) · التغطيةُ المعطاةُ والمستلمة (بنودٌ مستقلةٌ لا ترفع
 * الحصة) · والفجوة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

// ◆ **الحجبُ قبلَ الاستعلامِ لا بعده.** كان هذا السطحُ يعتمد على insidebar.php
//   وحدَه (السطرُ 40)، والاستعلامُ الأولُ يقع قبلَه — فبياناتُ الموردين
//   والحاوياتِ ودفترِ القدراتِ تُقرأ كاملةً لحسابٍ محرومٍ ثم يُعاد التوجيهُ
//   برسالةِ «لا صلاحية». والدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في
//   **متى**. (INJ-0454: «يُمنع … **قبل تنفيذ أيِّ استعلام**» — وهو ما أغفله
//   قياسٌ اكتفى بنتيجةِ HTTP، فالنتيجةُ كانت 302 والقراءةُ قد تمّت.)
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$r = mysqli_query($conn,
    "SELECT s.id supplier_id, s.name,
            COALESCE(SUM(oc.cap_qty), 0) committed,
            COALESCE((SELECT SUM(l.qty) FROM capacity_consumption_ledger l
                      WHERE l.effect_target_type = 'supplier' AND l.effect_target_ref = s.id
                        AND l.effect_type = 'supplier_share' AND l.reverses_led_id IS NULL), 0) consumed,
            COALESCE((SELECT SUM(cl.qty) FROM coverage_settlement_lines cl
                      JOIN substitute_coverages c2 ON c2.cov_id = cl.cov_id
                      WHERE cl.party = 'covering_supplier' AND cl.effect = 'exceptional_line'
                        AND c2.covering_supplier_id = s.id), 0) coverage_given
     FROM suppliers s
     LEFT JOIN op_containers oc ON oc.supplier_id = s.id AND oc.is_deleted = 0
     WHERE s.company_id = $company_id
     GROUP BY s.id, s.name
     HAVING committed > 0 OR consumed > 0 OR coverage_given > 0
     ORDER BY committed DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'حصص الموردين والتغطية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier_contract', 'الحصص والتغطية');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-chart-pie';
$header_title_html = htmlspecialchars('حصص الموردين والتغطية', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا حصة مورد عليها التزام أو استهلاك في دفتر القدرات', 'سجل التزام الحصة في عقد المورد ثم أعد فتح هذه الشاشة');
?>
  <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
  <p class="text-muted sup-shc-note">المستهلك محسوب من دفتر القدرات لا من عمود مخزن — والتغطية الاستثنائية بند لا يرفع الحصة (CAP-01 §7).</p>
  <?php /* GUIDE_COLS:govui_field_close
       الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
       والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
       ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
  $GUIDE_COLS = array(
      'معرف السطر' => 'id_1',
      'مفتاح دورة الالتزام' => 'role_obligation',
      'رقم فترة الالتزام' => 'no_role_obligation',
      'نوع الآلية/البند' => 'type_line',
      'سعة فترة الالتزام' => 'role_obligation_5',
      'مجموع مستهدفات الوحدات التعاقدية' => 'target_unit',
      'مجموع المنفذ' => 'c7',
      'نسبة التحقق' => 'verify',
      'مجموع عجز المقصرين' => 'deficit',
      'مجموع فائض المتجاوزين' => 'surplus',
      'نسبة التغطية بينهما' => 'c11',
      'عدد الموردين العاجزين' => 'count_supplier',
      'عدد المتجاوزين' => 'count',
      'أشهر متتالية بلا تغطية' => 'c14',
      'فجوة الالتزام النهائية' => 'c15',
      'إشارة إنذار' => 'c16',
      'مرجع الخطر المتفرع' => 'ref',
  );
  $D = array();
  $__gridRows = ems_w14_guide_rows('sup_deficit_surplus');
  echo ems_w14_grid('emsList_sup_coverage', $GUIDE_COLS, $__gridRows, $D, 'لا قياس تغطية مسجل بعد'); /* /GUIDE_COLS */ ?>
</div>
