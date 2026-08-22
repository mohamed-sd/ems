<?php
/**
 * tests/injfrd01_nav001_source_clean.php
 *   شاهدُ FR-NAV-001 — **الفحصُ على المصدرِ لا المخرَج**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبولِ بنصِّ الدفتر**: «فحصٌ على المصدرِ لا المخرَجِ يُخرج صفرَ
 *   صيغة» · والاختبارُ السالب: «صيغةٌ ممنوعةٌ باقيةٌ ← يُرسِّب التفعيل».
 *
 * ◆ **والقائمةُ المحظورةُ تُقرأ من نصِّ الوثيقةِ الحاكمةِ وقتَ التشغيل** — لا
 *   تُكتب حرفًا هنا. فلو زِيدت صيغةٌ في الوثيقةِ قاسها الشاهدُ بلا تعديل.
 *
 * ◆ **والاستثناءُ يُعلَن لا يُخمَّن**: ثلاثُ مجموعاتٍ تبدأ بالنونِ وهي **أسماءٌ
 *   لا أفعال** (ناقلُ الأحداث · نماذجُ العمل · نماذجُ التمويل). فتُدرَج في
 *   قائمةٍ بيضاءَ **معلَنةٍ باسمِها** — والنمطُ وحدَه يتّهم البريء.
 *
 * التشغيل: php tests/injfrd01_nav001_source_clean.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

/** أسماءٌ تبدأ بالنونِ وليست أفعالًا — استثناءٌ **معلَنٌ باسمِه** لا بالنمط. */
$NOUN_WHITELIST = array('ناقلُ الأحداث', 'نماذج العمل ووحدات القياس', 'نماذج التمويل');

/* القائمةُ المحظورةُ من نصِّ الوثيقةِ الحاكمة — لا من ذاكرةِ الكاتب */
function docx_text($path)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return ''; }
    $xml = $z->getFromName('word/document.xml');
    $z->close();
    if ($xml === false) { return ''; }
    $xml = preg_replace('~</w:p>~', "\n", $xml);
    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}
$banned = array();
foreach (glob($ROOT . '/docs/sources/*/*.docx') as $f) {
    $t = docx_text($f);
    if (preg_match('~ممنوعةٌ منعًا باتًّا في التنقّل[^\n]*\n([^\n]+)~u', $t, $m)) {
        foreach (explode('·', $m[1]) as $x) { $x = trim($x); if ($x !== '') { $banned[$x] = true; } }
    }
}
/* المصدرُ الثاني: وثيقتا المواءمةِ في مجلدِ النصوصِ إن كانتا محفوظتين */
$banned = array_keys($banned);

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

echo "══ FR-NAV-001 · الفحصُ على مصدرِ الدورةِ لا على المخرَج ══\n";

$neg = in_array('--negative', $argv, true);
$MARK = 'NAV001BELT';
if ($neg) {
    /* ◆ **الحزامُ السلبيُّ يدسُّ صيغةً ممنوعةً حيّةً ثم يكنسها** — فبوابةٌ لم
     *   تُجرَّب معطوبةً لا تُصدَّق. والدسُّ في صفٍّ جديدٍ لا في صفٍّ قائم. */
    $db->query("INSERT INTO `gov_screen_cycle`
        (`company_id`,`dept_name`,`layer_name`,`stage_order`,`stage_name`,`group_name`,
         `screen_title`,`screen_file`,`stage_kind`)
        VALUES (4,'{$MARK}','اختبار',999,'مرحلةُ حزام','نحاسبه ونصرف','شاشةُ حزام','belt/{$MARK}.php','عادي')");
    echo "  ◆ دُسَّت صيغةٌ ممنوعةٌ حيّةٌ للحزامِ السلبيّ\n";
}

/* ① القائمةُ المحظورةُ قُرئت */
/* ◆ **قائمةٌ فارغةٌ تصنع خضرةً كاذبة**: لو لم تُقرأ الوثيقةُ لعاد الفحصُ
 *   التالي «صفرُ صيغةٍ محظورة» — وهو صفرٌ لأن لا شيءَ يُبحث عنه لا لأن
 *   المصدرَ نظيف. ⇒ القائمةُ الفارغةُ **توقف الشاهدَ** ولا تمرّ. */
chk(count($banned) > 0, 'القائمةُ المحظورةُ تُقرأ من نصِّ الوثيقة',
    count($banned) . ' صيغةً محظورة');
if (count($banned) === 0) {
    echo "
⛔ **لا قائمةَ محظورةً تُقرأ** — والفحصُ بلا قائمةٍ خضرةٌ كاذبة. أُوقِف.
";
    exit(1);
}

/* ② صفرُ صيغةٍ محظورةٍ في **المصدر** */
$hits = array(); $rows = 0;
foreach ($banned as $b) {
    $e = $db->real_escape_string($b);
    $c = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle`
                  WHERE `group_name` = '{$e}' OR `stage_name` = '{$e}'");
    if ($c > 0) { $hits[] = "{$b}×{$c}"; $rows += $c; }
}
chk(empty($hits), '**صفرُ صيغةٍ محظورةٍ في مصدرِ الدورة**',
    empty($hits) ? '0 صفًّا' : "{$rows} صفًّا: " . implode(' · ', array_slice($hits, 0, 5)));

/* ③ صفرُ مجموعةٍ فعليةٍ خارجَ القائمةِ البيضاءِ المُعلَنة */
$verbRows = array();
$r = $db->query("SELECT `group_name`, COUNT(*) c FROM `gov_screen_cycle`
                  WHERE `group_name` REGEXP '^ن[^ ]+' OR `group_name` LIKE 'نحن%'
                  GROUP BY 1");
while ($r && $x = $r->fetch_assoc()) {
    if (in_array($x['group_name'], $NOUN_WHITELIST, true)) { continue; }
    $verbRows[] = $x['group_name'] . '×' . $x['c'];
}
chk(empty($verbRows), 'وصفرُ مجموعةٍ فعليةٍ خارجَ الاستثناءِ المُعلَن',
    empty($verbRows) ? 'صفر · والمُعلَنُ ' . count($NOUN_WHITELIST) . ' اسمًا'
                     : implode(' · ', $verbRows));

/* ④ صفرُ لفظٍ متقاعد */
$ret = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle`
                WHERE `group_name` LIKE '%الحاويات%' OR `stage_name` LIKE '%الحاويات%'
                   OR `group_name` LIKE '%الخانات%'  OR `stage_name` LIKE '%الخانات%'");
chk($ret === 0, 'وصفرُ لفظٍ متقاعدٍ في المصدر', "المقيس: {$ret}");

/* ⑤ صفرُ فقد — السجلُّ يحمل ما تغيَّر ويُرجعه */
$logged = n($db, "SELECT COUNT(*) FROM `gov_cycle_name_log` WHERE `requirement_id` = 'FR-NAV-001'");
chk($logged > 0, 'وكلُّ تغييرٍ محفوظٌ بقيمتِه السابقةِ فالرجوعُ ممكن',
    "{$logged} قيدَ تغيير");

if ($neg) {
    $db->query("DELETE FROM `gov_screen_cycle` WHERE `dept_name` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle` WHERE `dept_name` = '{$MARK}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    echo "\n◆ الحزامُ السلبيّ: **يُتوقَّع رسوبٌ أعلاه** — فإن مرَّ كلُّه فالشاهدُ أعمى.\n";
    printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
    exit($bad > 0 ? 0 : 1);          /* الحزامُ ينجح حين يرسُب الشاهد */
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
