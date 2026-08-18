<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* قاموسُ المبتدئ (TS-01 §٤-٦) — ستةَ عشرَ مصطلحًا بلغةِ أولِ يوم ·
   ولغةُ الشاشةِ الستُّ (§٤-٧) معروضتان معًا. قراءةٌ محضةٌ — لا POST هنا. */
$TERMS = array(
    array('الحاويةُ السنوية', 'اتفاقُنا مع العميلِ لسنةٍ: كم ساعةَ عملٍ سنبيعه إجمالًا. كلُّ شيءٍ تحتَها.'),
    array('حاويةُ النوع', 'حصةُ كلِّ نوعِ آليةٍ (حفّار · قلّاب…) من ساعاتِ السنة.'),
    array('خانةُ الآلية', 'مقعدٌ مرقَّمٌ لآليةٍ واحدةٍ داخلَ النوع — له أساسُ ساعاتٍ يوميّ.'),
    array('أساسُ الخانةِ اليومي', '20 ساعةً إن عملت ورديتين · 12 إن عملت واحدةً. يُحسب آليًّا ولا يُدخَل.'),
    array('إسنادُ الخانة', 'تسميةُ الموردِ الذي يشغّل هذا المقعدَ ومدةَ بقائِه فيه.'),
    array('القيدُ اليومي', 'سطرٌ واحدٌ لكلِّ آليةٍ في كلِّ ورديةٍ: كم اشتغلت وكم استعدّت وكم تعطّلت ومَن السبب.'),
    array('ساعاتُ التشغيل', 'الساعاتُ التي عملت فيها الآليةُ فعلًا — هي التي تُفوتَر للعميل.'),
    array('ساعاتُ الاستعداد', 'الآليةُ جاهزةٌ وواقفةٌ بلا عمل — تُفوتَر غالبًا لأنها ليست ذنبَنا.'),
    array('ساعاتُ التعطل', 'الآليةُ معطَّلة. نسجّل مَن يتحمّل: العميلُ أم الموردُ أم نحن.'),
    array('الطرفُ المتحمِّل', 'مَن يدفع ثمنَ التوقف. تعطلٌ سببُه العميلُ يُفوتَر استعدادًا — هذا حكمُ المالك.'),
    array('حدثُ التسليم', 'انتقالُ حصةِ ساعاتٍ من موردٍ إلى آخر — بمستندٍ وتاريخٍ، ولا يُمسُّ شهرٌ مغلق.'),
    array('التسويةُ الشهرية', 'جلسةُ الحسابِ مع المورد: منفَّذُه + ما يُضاف له − ما يُخصم منه. وكلُّ تعديلٍ بمستند.'),
    array('المتحمَّلُ من الخزينة', 'ما دفعناه للموردِ ولم نحصّله من العميل — مقياسُ خسارتِنا المباشرة. يُحسب آليًّا.'),
    array('وسيطُ ساعةِ اليوم', 'الرقمُ الأوسطُ لساعاتِ أيامِ الآلية — أصدقُ من المتوسطِ لأن يومًا شاذًّا لا يجرُّه.'),
    array('أيامُ العمل', 'عددُ الأيامِ التي فيها قيدُ عملٍ فعليّ — لا الفرقُ بين أولِ يومٍ وآخرِه.'),
    array('الشهرُ المغلق', 'شهرٌ حُسبت أرقامُه واعتُمدت — لا يُعدَّل فيه شيءٌ بأثرٍ رجعيّ.'),
);
$RULES = array(
    'الزرُّ فعلٌ لا اسم: «احفظ» و«اعتمد» — لا «تنفيذ».',
    'رسالةُ المنعِ تقول ما يُفعل: «لا تملك صلاحيةَ الاعتماد — راجِعْ مديرَك».',
    'كلُّ رقمٍ بوحدتِه: «3,600 ساعة» لا «3600».',
    'الحقلُ الإلزاميُّ موسومٌ بنجمةٍ قبلَ الحفظِ لا بعدَه.',
    'الحالةُ بلونٍ ونصٍّ معًا — فاللونُ وحدَه لا يقرؤه الجميع.',
    'اسمُ المرحلةِ فعلٌ يفهمه من لم يقرأ الوثيقة: «نسجّل ما حدث اليوم».',
);
$page_title = 'إيكوبيشن | قاموسُ المبتدئ';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="content-wrapper" dir="rtl">
<style>
  .gls-lead { color: var(--c-666666); margin: 4px 0 0; }
  .gls-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 10px; }
  .gls-card { border: 1px solid var(--c-e3e3e3, #e3e3e3); border-radius: 8px; padding: 10px 12px; background: var(--c-surface); }
  .gls-term { color: var(--c-1f3a5f, #1f3a5f); }
  .gls-def { margin: 6px 0 0; color: var(--c-444, #444); }
  .gls-rules { margin: 0; padding-right: 20px; line-height: 2; }
</style>
<?php
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا مصطلحاتِ قاموسٍ معروضةً الآن', 'حدِّثِ الصفحةَ — والقاموسُ يُوسَّع من دليلِ المصطلحاتِ الحاكم');
?>
  <section class="content-header"><h1>قاموسُ المبتدئ</h1>
    <p class="gls-lead">ستةَ عشرَ مصطلحًا بلغةِ أولِ يومٍ — لا تسأل زميلَك «ما هذه؟».</p>
  </section>
  <section class="content">
    <div class="box"><div class="box-body gls-grid">
      <?php foreach ($TERMS as $i => [$t, $d]): ?>
        <div class="gls-card">
          <strong class="gls-term"><?= ($i + 1) . '. ' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></strong>
          <p class="gls-def"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      <?php endforeach; ?>
    </div></div>
    <div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">لغةُ الشاشةِ الستُّ — تُطبَّق في كلِّ شاشةٍ جديدة</h3></div>
      <div class="box-body"><ol class="gls-rules">
        <?php foreach ($RULES as $r): ?><li><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
      </ol></div></div>
  </section>
</div>
<?php include __DIR__ . '/../infooter.php'; ?>
