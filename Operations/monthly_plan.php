<?php
/**
 * Operations/monthly_plan.php — الخطةُ الشهريةُ للتشغيل
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`scr_op_monthly`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ الخطةُ الشهريةُ مقابلَ المنفَّذ — وخطةُ الغدِ اليوميةُ في daily_plan.php وهي غيرُ هذه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Operations/monthly_plan.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحيات يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

$rows = array(); $failed = false;
$sql = "SELECT t.`month_ref`, t.`code_operator`, t.`equipment_name`, t.`site_name`, t.`days_work`, t.`hours_operations`, t.`hours_standby`, t.`pct_achievement`, t.`status_label` FROM `scr_op_monthly` t
         WHERE t.company_id = ? AND (1=1)
         ORDER BY t.month_ref DESC, id DESC LIMIT 500";
$st = $conn->prepare($sql);
if (!$st) { $failed = true; }
else {
    $st->bind_param('i', $company_id);
    if (!$st->execute()) { $failed = true; }
    else { $res = $st->get_result(); while ($res && ($x = $res->fetch_assoc())) { $rows[] = $x; } }
    $st->close();
}

$page_title = 'الخطة الشهرية للتشغيل';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01: أنماطُ الشاشةِ في كتلةٍ واحدة — لا نمطَ موضعيًّا ولا لونَ خارجَ الرموز */
.mpl-flash { margin: 10px 0; }
.mpl-kpi { padding: 10px 14px; margin: 10px 0; border-inline-start: 4px solid var(--c-0d6efd); display: inline-block; }
.mpl-kpi-label { font-size: .78rem; opacity: .75; }
.mpl-kpi-num { font-size: 1.4rem; font-weight: 700; }
.mpl-table-full { width: 100%; }
.mpl-empty-cell { text-align: center; opacity: .7; }
.mpl-note { font-size: .8rem; margin-top: 8px; }
</style>
<div class="main" dir="rtl">
<?php
$header_icon = 'fa fa-calendar-days';
$header_title_html = htmlspecialchars('الخطة الشهرية للتشغيل', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_ops_monthly_plan
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف سطر الخطة' => 'g39',
            'شهر الخطة' => 'g40',
            'رقم المشروع' => 'g41',
            'اسم المشروع' => 'g42',
            'كود عقد العميل' => 'g43',
            'كود المعدة' => 'g44',
            'نوع المعدة' => 'g45',
            'نموذج العمل' => 'g46',
            'المستهدف التعاقدي' => 'g47',
            'مستهدف الخطة' => 'g48',
            'وحدة القياس' => 'g49',
            'أيام العمل المخططة' => 'g50',
            'ورديات اليوم' => 'g51',
            'الساعات المتاحة/وردية' => 'g52',
            'معامل الموسم' => 'g53',
            'المستهدف المعاير بالموسم' => 'g54',
            'مبرر الفارق عن التعاقدي' => 'g55',
            'حالة السطر' => 'g56',
            'المنشئ' => 'g57',
            'تاريخ الإنشاء' => 'g58',
            'المراجع' => 'g59',
            'المعتمد' => 'g60',
            'تاريخ الاعتماد' => 'g61',
            'حالة البيانات' => 'g62',
            'مرجع المصدر' => 'g63',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('ops_monthly_plan');
        echo ems_w14_grid('emsList_ops_monthly_plan', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في الخطة الشهرية للتشغيل'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضيًا
echo ems_states_bundle('لا صفوف خطة شهرية لهذا الكيان', 'سجل التشغيل الشهري في مصدره ثم عد لقراءة المقابلة');
?>
  <?php if ($failed): ?>
  <div class="alert alert-danger mpl-flash">
    <strong>تعذرت قراءة البيانات.</strong>
    فرق بين «لا صف» و«تعذر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card mpl-kpi">
    <div class="mpl-kpi-label">صفوف معروضة</div>
    <div class="mpl-kpi-num"><?php echo number_format(count($rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped mpl-table-full">
      <thead><tr>
        <th>الشهر</th>
        <th>المشغل</th>
        <th>المعدة</th>
        <th>الموقع</th>
        <th>أيام عمل</th>
        <th>ساعات تشغيل</th>
        <th>ساعات استعداد</th>
        <th>نسبة الإنجاز</th>
        <th>الحال</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="99" class="mpl-empty-cell">لا صف مسجل بعد.</td></tr>
      <?php else: foreach ($rows as $x): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $x['month_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['code_operator'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['equipment_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['site_name'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['days_work'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['hours_operations'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['hours_standby'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['pct_achievement'], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) $x['status_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted mpl-note">
      قراءة محضة — الخطة الشهرية مقابل المنفذ — وخطة الغد اليومية في daily_plan.php وهي غير هذه. وأحدث 500 صف.
    </p>
  </div></div>
  <?php endif; ?>
</div>
