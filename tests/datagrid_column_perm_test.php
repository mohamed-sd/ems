<?php
/**
 * tests/datagrid_column_perm_test.php — العمودُ الممنوعُ لا يُعرَض **ولا يُصدَّر**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (ف١٦ · شبكةُ إنجاز): «أعمدةٌ واعيةٌ بالصلاحيةِ وتصديرٌ
 *   بالمسموحِ لا بالكل».
 *
 * ◆ والعطبُ الذي يُسدُّ: عمودٌ يُخفى بصنفِ CSS **يبقى في ملفِّ التصدير** —
 *   فمن لا يراه على الشاشةِ يجده في إكسل. تسريبٌ صامتٌ لا يظهر في أيِّ
 *   فحصِ صلاحيات.
 *
 * ◆ يُفحص المصدرُ نفسُه (لا يُشغَّل متصفح) بسبعةِ فحوصٍ يقرأ كلٌّ منها شيفرةً
 *   حقيقيةً في `assets/js/ui-unification.js` و`inheader.php`:
 *   ① المسلكُ الأولُ — العرضُ يستدعي فحصَ الصلاحية.
 *   ② المسلكُ الثاني — التصديرُ يستدعيه أيضًا (وهو موضعُ التسريب).
 *   ③ زرُّ «إظهار كل الأعمدة» **لا يُبطل** المنع.
 *   ④ الممنوعُ لا يُدرَج في قائمةِ المنتقي أصلًا.
 *   ⑤ غيابُ الوسمِ ليس منعًا — صفرُ ارتدادٍ في الشاشاتِ غيرِ المعلَّمة.
 *   ⑥ الخادمُ يزرع الرموزَ مُرشَّحةً بنمطٍ صارم.
 *   ⑦ ولا يُبثُّ شيءٌ لغيرِ ذي جلسة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$js  = (string) file_get_contents($ROOT . '/assets/js/ui-unification.js');
$php = (string) file_get_contents($ROOT . '/inheader.php');

$pass = 0; $fail = 0;
$check = function ($label, $ok, $detail = '') use (&$pass, &$fail) {
    printf("  %s %-58s %s\n", $ok ? '✔' : '✗', $label, $detail);
    $ok ? $pass++ : $fail++;
};

echo "════ المسلكان يقرآن مسندًا واحدًا ════\n";

/* ① العرض */
$hasApply = (bool) preg_match('~function applyColumnPermissions\s*\(~', $js);
$calledAtInit = (bool) preg_match('~applyColumnPermissions\(api\)~', $js);
$check('① العرضُ: applyColumnPermissions مُعرَّفةٌ ومُستدعاةٌ عند التهيئة',
    $hasApply && $calledAtInit);

/* ② التصدير — وهو موضعُ التسريب */
$exportBlock = '';
if (preg_match('~function isExportableColumn\s*\(.*?\n\s*\}~s', $js, $m)) { $exportBlock = $m[0]; }
$check('② التصديرُ: isExportableColumn تستدعي isPermittedColumn',
    strpos($exportBlock, 'isPermittedColumn') !== false,
    $exportBlock === '' ? 'لم تُقرأ الدالة' : '');

/* ③ «إظهار كل الأعمدة» لا يُبطل المنع
   ◆ **وتُنزع التعليقاتُ قبل الفحص**: النصُّ الذي يشرح إزالةَ النمطِ القديمِ
     يذكره حرفًا، فيقرؤه الفاحصُ الساذجُ نمطًا قائمًا ويُخفق على شيفرةٍ سليمة.
     وقد وقع ذلك فعلًا في أولِ تشغيل. */
$allBtnBlock = '';
if (preg_match('~allBtn\.addEventListener\(.*?\n\s*\}\);~s', $js, $m)) { $allBtnBlock = $m[0]; }
$allBtnCode = preg_replace('~/\*.*?\*/~s', '', $allBtnBlock);
$allBtnCode = preg_replace('~//[^\n]*~', '', (string) $allBtnCode);
$usesAllowed = (strpos($allBtnCode, 'isPermittedColumn') !== false)
               && (strpos($allBtnCode, 'api.columns().visible(true') === false);
$check('③ «إظهار كل الأعمدة» يستثني الممنوعَ ولا يعمّم', $usesAllowed,
    $allBtnBlock === '' ? 'لم يُقرأ المعالج' : '');

/* ④ الممنوعُ خارجَ قائمةِ المنتقي */
$check('④ حلقةُ المنتقي تتخطّى الممنوع',
    (bool) preg_match('~if\s*\(!isPermittedColumn\(api,\s*i\)\)\s*\{\s*continue;~', $js));

echo "\n════ حارسُ الحارس — لا يتحوّل إلى منعٍ عام ════\n";

/* ⑤ غيابُ الوسمِ ليس منعًا */
$permBlock = '';
if (preg_match('~function isPermittedColumn\s*\(.*?\n\s*\}~s', $js, $m)) { $permBlock = $m[0]; }
$check('⑤ عمودٌ بلا data-perm يُسمح به (return true)',
    (bool) preg_match('~if\s*\(!need\)\s*\{\s*return true;~', $permBlock),
    $permBlock === '' ? 'لم تُقرأ الدالة' : '');

echo "\n════ الخادمُ مصدرُ الرموز — ومُرشَّحةٌ لا خامًا ════\n";

/* ⑥ ترشيحٌ صارم */
$check('⑥ الرموزُ تُرشَّح بنمطٍ صارمٍ قبل البثّ',
    (bool) preg_match('~preg_match\(.\^\[a-zA-Z0-9_\.\\\\?\-\]\{2,64\}\$.~', $php)
    || strpos($php, "'/^[a-zA-Z0-9_.\\-]{2,64}$/'") !== false);

/* ⑦ لا بثَّ بلا جلسة */
$check('⑦ لا يُبثُّ المسندُ لغيرِ ذي جلسة',
    (bool) preg_match("~isset\(\\\$_SESSION\['user'\]\)\s*&&\s*!empty\(\\\$GLOBALS\['EMS_COL_PERMS'\]\)~", $php));

echo "\n════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d\n", $pass, $fail);
echo $fail === 0
    ? "✔ الممنوعُ لا يُعرَض ولا يُصدَّر — والمسلكان يقرآن مسندًا واحدًا\n"
    : "✗ الطبقةُ غيرُ مُثبَتة\n";
exit($fail === 0 ? 0 : 1);
