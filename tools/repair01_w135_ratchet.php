<?php
/**
 * tools/repair01_w135_ratchet.php — سقّاطةُ السطحِ الجديدِ (البند 9)
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البند 9**: «أيُّ `Surface` جديدٍ من هذه اللحظةِ يجب أن يرفض
 * التسجيلَ أو الترقيةَ إذا لم يكن لديه على الأقلِّ [اثنا عشرَ شرطًا] … لا تطبّق
 * هذه البوّابةَ رجعيًّا كحاجبٍ على كلِّ `Legacy` الآن. القديم: `Counted Debt`.
 * الجديد: `Zero Tolerance`. **هذه `Ratchet` وليست حملةَ تنظيف**.»
 *
 * ◆ **والخطُّ الفاصلُ زمنيٌّ لا كيفيّ**: كلُّ سطحٍ ختمُه `origin` موجةٌ **بعد**
 *   `W13` يخضع للاثنَي عشرَ كاملةً. وما قبلَه دَينٌ معدودٌ يُقاس ولا يُردّ.
 *   والخطُّ **يُقرأ من ملفِّ الخطِّ لا من ثابتٍ في الشيفرة** — فينمو المقامُ
 *   بالموجاتِ ولا يحتاج تعديلَ أداة.
 *
 * ◆ **والسقّاطةُ تعدُّ ولا تُغلق**: نصُّ الأمرِ يسمّيها `Ratchet`. فحكمُها
 *   **ازديادُ الدَّينِ** لا وجودُه — ودَينٌ ثابتٌ عند خطِّه يمرّ.
 *
 * ⛔ **ولا تُخضِرُّ على خلاء**: مقامٌ صفرٌ من الأسطحِ الجديدةِ يُعلَن صراحةً
 *   ولا يُقرأ نجاحًا — فبوّابةٌ تقيس صفرًا من صفرٍ تمرُّ على تطابقِ لا شيء.
 *
 * التشغيل: php tools/repair01_w135_ratchet.php [--baseline]
 * الخروج : 0 لا دَينَ زاد · 1 سطحٌ جديدٌ ناقصُ الشروط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$SET = in_array('--baseline', $argv, true);

/* ═══ ① خطُّ الأساسِ — الموجةُ التي بعدَها يسري الإلزام ═══════════════════ */
$LINE = $ROOT . '/docs/REPAIR01_20260823/W135_RATCHET_LINE.txt';
$from = is_file($LINE) ? (int) trim(file_get_contents($LINE)) : 13;
printf("\n═══ سقّاطةُ السطحِ الجديد — البند 9 ═══\n  الخطّ: كلُّ سطحٍ ختمُه بعدَ W%02d\n\n", $from);

/* ═══ ② الشروطُ الاثنا عشر ═══════════════════════════════════════════════ */
$REQ = array(
    'screen_id'          => 'المعرف المعياري',
    'canonical_label_ar' => 'المسمى العربي المعياري',
    'owner_code'         => 'الادارة المالكة',
    'surface_kind'       => 'مصدر ام اسقاط',
    'route'              => 'المسار المعياري',
    'lifecycle'          => 'موضعه من دورة الحياة',
    'guard_kind'         => 'حارس العرض الخادمي',
    'action_guard'       => 'حارس الفعل الخادمي',
    'permission_policy'  => 'سياسة الصلاحية',
    'grain_ar'           => 'الحبة',
    'source_of_truth'    => 'مصدر الحقيقة',
    'state_model_ref'    => 'مرجع الة الحالة',
);

/* ═══ ③ القياس ═══════════════════════════════════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT screen_id, screen_file, origin, "
    . implode(', ', array_map(function ($c) { return "COALESCE(`$c`,'') `$c`"; }, array_keys($REQ)))
    . " FROM repair01_screen_registry
        WHERE origin REGEXP '^W[0-9]+$' AND CAST(SUBSTRING(origin,2) AS UNSIGNED) > $from
        ORDER BY origin, screen_file");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$bad = array(); $missTally = array();
foreach ($rows as $s) {
    $m = array();
    foreach ($REQ as $c => $ar) { if (trim((string) $s[$c]) === '') { $m[] = $ar; $missTally[$ar] = isset($missTally[$ar]) ? $missTally[$ar] + 1 : 1; } }
    if ($m) { $bad[] = array($s['screen_file'], $s['origin'], $m); }
}
$now = count($bad);

/* ⛔ **خلاءٌ مُعلَنٌ لا مسكوتٌ عنه** */
if (!$rows) {
    echo "  ◆ **مقامٌ خالٍ**: لا سطحَ ختمُه بعدَ W" . sprintf('%02d', $from) . " بعد.\n";
    echo "     والسقّاطةُ تسري على أوّلِ سطحٍ يُسجَّل — ولا تُقرأ خضرتُها اليومَ نجاحًا.\n\n";
}
printf("  أسطحٌ في نطاقِ الإلزام: %d · ناقصةُ الشروط: %d\n", count($rows), $now);
foreach (array_slice($bad, 0, 12) as $b) {
    printf("    ✘ %-34s [%s] ينقصه: %s\n", $b[0], $b[1], implode(' · ', array_slice($b[2], 0, 4))
        . (count($b[2]) > 4 ? ' …+' . (count($b[2]) - 4) : ''));
}
if (count($bad) > 12) { printf("    … و%d غيرُها\n", count($bad) - 12); }
if ($missTally) {
    echo "\n  الشروطُ الأكثرُ غيابًا:\n";
    arsort($missTally);
    $i = 0;
    foreach ($missTally as $k => $v) { printf("    · %-28s %d\n", $k, $v); if (++$i >= 6) { break; } }
}

/* ═══ ④ السقّاطة — الحكمُ على الازديادِ لا على الوجود ═══════════════════ */
$BASE = $ROOT . '/docs/REPAIR01_20260823/W135_RATCHET_BASE.txt';
$base = is_file($BASE) ? (int) trim(file_get_contents($BASE)) : null;
if ($SET) {
    file_put_contents($BASE, (string) $now);
    if (!is_file($LINE)) { file_put_contents($LINE, (string) $from); }
    printf("\n  ✔ ثُبِّت خطُّ الأساسِ عند %d\n", $now);
    exit(0);
}
if ($base === null) {
    echo "\n  ⚠ لا خطَّ أساسٍ — شغّلْ مرّةً بـ--baseline لتثبيتِه.\n";
    exit(1);
}
echo "\n────────────────────────────────────────────────────────────\n";
printf("الأساس %d · اليوم %d\n", $base, $now);
if ($now > $base) {
    printf("الحكم: **زاد الدَّينُ %d** — سطحٌ جديدٌ سُجِّل ناقصَ الشروط ✘\n", $now - $base);
    exit(1);
}
if ($now < $base) {
    printf("الحكم: انخفض %d — شُدَّ السقّاطةَ بـ--baseline ✔\n", $base - $now);
    exit(0);
}
echo "الحكم: ثابتٌ عند خطِّه — لا دَينَ جديد ✔\n";
exit(0);
