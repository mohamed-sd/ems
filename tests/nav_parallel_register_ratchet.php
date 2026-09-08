<?php
/**
 * tests/nav_parallel_register_ratchet.php
 *   سقّاطةُ **السجلِّ الموازي في قرارِ الظهور** — «العارضُ يشتقُّ من الحارس»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ المُنفَذة**: سطحٌ يُظهِر روابطَ شاشاتٍ **لا يقرِّر ما يُعرَض من
 *   سجلٍّ موازٍ** (`role_permissions`) — بل **يشتقُّه من الحارسِ نفسِه** الذي
 *   يحكم الوصول. فالمزامنةُ تنفكُّ، والاشتقاقُ لا ينفكّ.
 *
 * ⛔ **العطبُ الذي تمنعه — مقيسٌ لا مفترَض**: هذا النمطُ بعينِه أنتج
 *   **٥٤٧ رابطًا يُعرَض ويردُّه بابُه** في **خمسةِ مواضعَ** مختلفة:
 *     · `navarch_renderer` (454) · `getDynamicNavLinks` (78 حيّةً · 918 مخرَجًا)
 *     · `roleBoardQuickActions` (15) · و`unified_nav` بموضعَين نائمَين.
 *   وأخطرُها لم يفحص شيئًا أصلًا — **فالبحثُ عن الفحصِ الخطأ لا يجد غيابَه**.
 *   ⇐ ووُجدت اثنتانِ من الستِّ بالبحثِ عن أسماءِ دوالَّ معروفة، والأربعُ الباقيةُ
 *     **بالمصادفةِ وإلحاحِ السؤال**. فالحاجزُ هنا يُبدِّل المصادفةَ بالعدّ.
 *
 * ◆ **ولماذا سقّاطةٌ لا منعٌ تامّ**: أربعةُ نداءاتٍ قائمةٌ اليوم، وبعضُها مشروعٌ
 *   (لوحاتُ الحوكمةِ تقرأ السجلَّ القديمَ **أثرًا** للمقارنة). فالمنعُ التامُّ
 *   يُرسِّب الشجرةَ أبدًا فيُعطَّل الحاجزُ — **والسقّاطةُ تمنع الازديادَ وتقيس
 *   التقدُّم**. وهو عينُ نمطِ `injfix01_raw_query_ratchet` في هذه الشجرة.
 *
 * ⛔ **والانخفاضُ يُرسِّب أيضًا**: من أزال نداءً يُنقِص خطَّ الأساسِ في هذا
 *   الملفِّ نفسِه — **فسقّاطةٌ لا تُشدُّ تصير سقفًا يُنسى**.
 *
 * ◆ **والتعليقاتُ والتعريفاتُ لا تُعَدّ**: الماسحُ يقرأ التعليقَ كما يقرأ
 *   الشيفرةَ إن لم يُنزَع (‏عطبٌ موثَّقٌ في هذه الشجرة)، و`function x(` تعريفٌ
 *   لا نداء. فيُنزَعان قبلَ العدّ.
 *
 * ⛔ **وصفرُ مقيسٍ يُرسِّب**: ماسحٌ لا يجد ملفًّا واحدًا **أداةٌ مكسورةٌ** لا
 *   شجرةٌ نظيفة — فيُشترط أن يُمسَح ألفُ ملفٍّ على الأقلّ.
 *
 * التشغيل: php tests/nav_parallel_register_ratchet.php
 *          php tests/nav_parallel_register_ratchet.php --list
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$LIST = in_array('--list', $argv, true);

/* ── خطُّ الأساسِ المقيس (2026-09-07) ─────────────────────────────────────
     يُنقَص عند إزالةِ نداءٍ — ولا يُزاد إلّا بقرارٍ مكتوبٍ في السجلِّ أدناه. */
$BASELINE = 4;

/* ── سجلُّ النداءاتِ القائمة: لكلٍّ **سببٌ ومالك** ───────────────────────── */
$REGISTER = array(
  'includes/navarch_renderer.php' => array(
      'calls'  => 1,
      'reason' => 'ترشيحٌ أوّليٌّ رخيصٌ قبلَ حكمِ القالب — والقرارُ النهائيُّ من '
                . '`ems_template_nav_state` فوقَه (قِيس: صفرُ فرقٍ لو فُتح السجلُّ بالكامل)',
      'owner'  => 'NAV-ARCH-02'),
  'includes/unified_nav.php' => array(
      'calls'  => 2,
      'reason' => 'المسارُ القديمُ ووسمُ المنع — والقرارُ النهائيُّ من طبقةِ القوالبِ فوقَهما',
      'owner'  => 'NAV-ARCH-02'),
  'main/project_users.php' => array(
      'calls'  => 1,
      'reason' => 'شاشةُ «فريقُ العمل» تعرض روابطَ الدورِ **تقريرًا** — ولا تحرس بها وصولًا',
      'owner'  => 'PERM-01'),
);

/* ── المسحُ ────────────────────────────────────────────────────────────── */
$SKIP = array('tools','tests','storage','database','vendor','docs','logs','node_modules',
              'install','scripts','.git','user_guide','examples','emsreports','chats');
$PAT  = array('perm_nav_view_exists_sql', 'perm_nav_left_join_sql', 'perm_nav_view_select_sql');

$scanned = 0; $hits = array(); $total = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', $f->getPathname());
    $rel = ltrim(substr($rel, strlen($ROOT)), '/');
    $top = explode('/', $rel)[0];
    if (in_array($top, $SKIP, true)) { continue; }
    $scanned++;
    $src = (string) @file_get_contents($f->getPathname());
    if ($src === '') { continue; }
    $code = preg_replace('~/\*.*?\*/~s', '', $src);
    $code = preg_replace('~^\s*//.*$~m', '', $code);
    foreach ($PAT as $needle) {
        $q    = preg_quote($needle, '~');
        $body = preg_replace('~function\s+' . $q . '\s*\(~', '', $code);
        $n    = preg_match_all('~\b' . $q . '\s*\(~', $body);
        if ($n > 0) { $hits[$rel] = (isset($hits[$rel]) ? $hits[$rel] : 0) + $n; $total += $n; }
    }
}

if ($LIST) {
    foreach ($hits as $f => $n) { echo "{$f}\t{$n}\n"; }
    exit(0);
}

$okN = 0; $badN = 0;
function ok($c, $m, &$okN, &$badN, $d = '') {
    if ($c) { $okN++; echo "  ✔ {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $badN++; echo "  ✘ FAIL: {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "\n══ سقّاطةُ السجلِّ الموازي — «العارضُ يشتقُّ من الحارس» ══\n";
echo "  ملفّاتُ إنتاجٍ مُسحت: {$scanned}\n";

ok($scanned >= 1000, 'الماسحُ مسح فعلًا — وصفرُ ممسوحٍ عطبُ أداةٍ لا نظافةُ شجرة',
   $okN, $badN, "عدد={$scanned}");

ok($total <= $BASELINE, '★★★ **لا نداءَ جديدٌ يقرِّر الظهورَ من السجلِّ الموازي**',
   $okN, $badN, "مقيس={$total} · الأساس={$BASELINE}");

ok($total >= $BASELINE, 'ولم ينخفضِ العددُ بلا شدِّ السقّاطة — أنقِصِ $BASELINE في هذا الملف',
   $okN, $badN, "مقيس={$total} · الأساس={$BASELINE}");

$unreg = array();
foreach ($hits as $f => $n) { if (!isset($REGISTER[$f])) { $unreg[] = "{$f} (×{$n})"; } }
ok(empty($unreg), '★★ **وكلُّ نداءٍ مسجَّلٌ بسببٍ ومالك** — والجديدُ يُرسِّب حتى يُبرَّر',
   $okN, $badN, empty($unreg) ? count($REGISTER) . ' مدخلًا' : implode(' · ', $unreg));

$drift = array();
foreach ($REGISTER as $f => $e) {
    $now = isset($hits[$f]) ? $hits[$f] : 0;
    if ($now !== $e['calls']) { $drift[] = "{$f}: سُجِّل {$e['calls']} · مقيس {$now}"; }
}
ok(empty($drift), 'ولا مدخلَ في السجلِّ فارق مقياسَه — فالسجلُّ لا يتقادم بصمت',
   $okN, $badN, empty($drift) ? 'صفر' : implode(' · ', $drift));

$noReason = 0;
foreach ($REGISTER as $e) { if (trim((string) $e['reason']) === '') { $noReason++; } }
ok($noReason === 0, 'ولكلِّ مدخلٍ **سببٌ مكتوب**', $okN, $badN, "بلا سبب: {$noReason}");

echo "\n  ◆ المدخلاتُ المسجَّلة:\n";
foreach ($REGISTER as $f => $e) {
    printf("     %-34s ×%d · %s\n", $f, $e['calls'], $e['owner']);
}

echo "───────────────────────────────────────────────────────────────\n";
echo ($badN === 0 ? "✔" : "✘") . " النتيجة: نجح {$okN} · رسب {$badN}\n";
echo "◆ والسقّاطةُ لا تُلغي السجلَّ القديمَ — تمنع أن يُقرَّر به ظهورٌ جديد.\n";
exit($badN === 0 ? 0 : 1);
