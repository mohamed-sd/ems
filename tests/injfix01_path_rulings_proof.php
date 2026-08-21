<?php
/**
 * tests/injfix01_path_rulings_proof.php — INJ-FIX-01 · GAP-24 و GAP-31
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **GAP-24**: «إخراجُ الوهميِّ من الخدمةِ أو وصلُه بالحقيقيّ» — والحكمُ **يُقاس
 *   لا يُحفَظ**: عددُ قرّاءِ الإنتاجِ يُعاد عدُّه، فإن صار للوهميِّ قارئُ قرارٍ
 *   جديدٌ رسب الفحصُ — **فحكمٌ لا يُعاد قياسُه يتقادم فيكذب**.
 * ◆ **GAP-31**: «صفرُ مسارٍ بلا حكمٍ مكتوب».
 *
 * التشغيل: php tests/injfix01_path_rulings_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}
/** يعدُّ ملفاتِ الإنتاجِ التي تذكر مفتاحًا — بنفسِ نطاقِ القياسِ الأصليِّ */
function prodReaders($ROOT, $key)
{
    $dirs = array('app', 'includes', 'Finance', 'Operations', 'main', 'admin');
    $n = 0;
    foreach ($dirs as $d) {
        $base = $ROOT . '/' . $d;
        if (!is_dir($base)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
            if (strpos((string) @file_get_contents($f->getPathname()), $key) !== false) { $n++; }
        }
    }
    return $n;
}

echo "══ ① لكلِّ مسارٍ حكمٌ مكتوبٌ بسببٍ ودليل ══\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_path_rulings'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ أحكامِ المساراتِ غيرُ موجود — تُشغَّل الهجرة 2027_09_09');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1);
}
$rows = array();
$q = $conn->query("SELECT * FROM `gov_path_rulings` ORDER BY `gap`,`path_key`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
$noWhy = 0;
foreach ($rows as $x) {
    if (trim((string) $x['reason']) === '' || trim((string) $x['evidence']) === '') { $noWhy++; }
    printf("     %-8s %-28s %-20s\n", $x['gap'], $x['path_key'], $x['ruling']);
}
chk(count($rows) >= 6, 'المساراتُ المحكومة: ' . count($rows));
chk($noWhy === 0, "صفرُ حكمٍ بلا سببٍ ودليل — {$noWhy}");

echo "\n══ ② الحكمُ يُعاد قياسُه فلا يتقادم ══\n";
$AUTH = 'fin_financial_periods';
$PHANTOM = 'scr_monthly_close';
$aNow = prodReaders($ROOT, $AUTH);
$pNow = prodReaders($ROOT, $PHANTOM);
printf("     %-26s قرّاءُ الإنتاجِ الآن: %d\n", $AUTH, $aNow);
printf("     %-26s قرّاءُ الإنتاجِ الآن: %d\n", $PHANTOM, $pNow);
chk($aNow > $pNow, "◆ السلطةُ ما تزال أكثرَ قراءةً من الوهميّ — {$aNow} > {$pNow}");

$q = $conn->query("SELECT `prod_readers` FROM `gov_path_rulings` WHERE `path_key`='{$PHANTOM}'");
$pWas = $q ? (int) $q->fetch_row()[0] : 0;
chk($pNow <= $pWas, "لم يكتسب الوهميُّ قارئًا جديدًا — {$pNow} ≤ {$pWas}");

echo "\n══ ③ الحكمُ مكتوبٌ في الجدولِ نفسِه ══\n";
foreach (array($AUTH, $PHANTOM) as $t) {
    $q = $conn->query("SELECT `TABLE_COMMENT` FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}'");
    $cm = $q ? (string) $q->fetch_row()[0] : '';
    chk(strpos($cm, 'GAP-24') !== false,
        "`{$t}` يحمل حكمَه في تعليقِه — «" . mb_substr($cm, 0, 46) . "»");
}
echo "  ◆ فمن يفتح الجدولَ يقرأ حكمَه، ولا يلزمه بلوغُ وثيقةٍ ليعرف أيُّهما السلطة.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
