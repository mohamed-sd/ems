<?php
/**
 * tests/injexec01_migration_ledger_gate.php
 *   بوابةُ دفترِ الهجرات — INJ-EXEC-01 · NF-05
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ المقيس**: خمسٌ وثلاثون هجرةً خارجَ الدفتر، ثم خمسَ عشرةَ أخرى
 *   بالنمطِ نفسِه بعدَ «إصلاحِه». ⇒ **إدخالُ الصفوفِ علاجُ عَرَض؛ والعلاجُ
 *   قاعدةٌ تمنع تكرارَه**: هجرةٌ على القرصِ بلا صفٍّ في الدفترِ تُرسِّب البوابة.
 *
 * ◆ **والبصمةُ تُقاس لا تُفترض**: صفٌّ ببصمةٍ تخالف الملفَّ يعني أن الملفَّ
 *   حُرِّر بعدَ تطبيقِه — وهو خرقٌ لقاعدةِ «لا تُعدَّل هجرةٌ طُبِّقت».
 *
 * ◆ **وسنةُ التسميةِ القادمة**: تُقاس وتُعلَن **ولا تُرسِّب ولا تُسقَّط** — لأن
 *   الاسمَ المؤرَّخَ للأمامِ هو مفتاحُ ترتيبِ التطبيق؛ فسقّاطةٌ عليه تعاقب على
 *   السلوكِ الصحيح. التفصيلُ في البند ③.
 *
 * التشغيل:  php tests/injexec01_migration_ledger_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "══ بوابةُ دفترِ الهجرات — NF-05 ══\n";

/* ① ما على القرصِ وما في الدفتر */
$disk = array();
foreach (glob($ROOT . '/database/migrations/*.php') as $f) {
    $b = basename($f);
    if ($b[0] === '_') { continue; }                  /* المُعِينات لا هجرات */
    if (!preg_match('/^\d{4}_\d{2}_\d{2}_/', $b)) { continue; }
    $disk[$b] = $f;
}
$led = array();
$q = $conn->query("SELECT `filename`, `checksum`, `status` FROM `schema_migrations`");
while ($r = $q->fetch_assoc()) { $led[$r['filename']] = $r; }

$missing = array_diff(array_keys($disk), array_keys($led));
$drift   = array();
foreach ($disk as $b => $f) {
    if (!isset($led[$b])) { continue; }
    if ($led[$b]['status'] === 'baseline') { continue; }  /* الأساسُ لا بصمةَ محرَّرةً له */
    if (@sha1_file($f) !== $led[$b]['checksum']) { $drift[] = $b; }
}

/* الاستثناءُ يُقبل **مكتوبًا بسببِه** لا مسكوتًا عنه — والمسكوتُ عنه يُرسِّب */
$EXC = $ROOT . '/storage/gates/injexec01_ledger_exceptions.json';
$exc = is_file($EXC) ? (array) (json_decode((string) file_get_contents($EXC), true)['exceptions'] ?? array()) : array();
$driftUndeclared = array();
foreach ($drift as $b) {
    $why = isset($exc[$b]) ? (string) $exc[$b] : '';
    if ($why === '' || strpos($why, 'NEEDS_REVIEW') === 0) { $driftUndeclared[] = $b; }
}

printf("\n── ① المقام ──\n  على القرص: %d · في الدفتر: %d\n", count($disk), count($led));
chk(count($missing) === 0, '**صفرُ هجرةٍ على القرصِ خارجَ الدفتر**',
    count($missing) ? count($missing) . ' خارجَه: ' . implode(' · ', array_slice($missing, 0, 6)) : '0');
chk(count($driftUndeclared) === 0, 'كلُّ بصمةٍ منزاحةٍ لها سببٌ مكتوب',
    count($driftUndeclared) ? implode(' · ', $driftUndeclared) : count($drift) . ' منزاحةً · كلُّها مُعلَنة');
foreach ($drift as $b) {
    if (isset($exc[$b])) { echo "     ◆ استثناءٌ مكتوب — {$b}: " . mb_substr($exc[$b], 0, 90) . "…\n"; }
}

/* ② القاعدةُ نفسُها موجودة — لا الأثرُ وحدَه */
$helper = $ROOT . '/database/migrations/_ledger.php';
chk(is_file($helper), 'مُعينُ القيدِ الذاتيِّ موجود', '_ledger.php');
$adopters = 0;
foreach ($disk as $b => $f) {
    $s = (string) @file_get_contents($f);
    if (strpos($s, 'ems_migration_recorded') !== false) { $adopters++; }
}
printf("  ◆ هجراتٌ تُقيِّد نفسَها ذريًّا: %d من %d\n", $adopters, count($disk));
echo "     ⇐ والقاعدةُ تسري على **الجديدِ** — ولا تُعاد كتابةُ 352 ملفًّا طُبِّقت.\n";

/* ③ اصطلاحُ سنةِ التسمية — **يُقاس ويُعلَن ولا يُرسِّب، وهذا قرارٌ مسجَّلٌ بسببِه**
 * ◆ أوّلُ صياغةٍ لهذه البوابةِ جعلته سقّاطةً تمنع الازدياد. وذلك **خطأٌ في
 *   البوابةِ لا في النظام**: اسمُ الملفِّ المؤرَّخُ للأمامِ هو **مفتاحُ ترتيبِ
 *   التطبيق**. فهجرةٌ جديدةٌ باسمِ السنةِ الحقيقيةِ (2026) تسبق ٢٥٨ هجرةً
 *   مطبَّقةً في الفرزِ، فتُشغَّل قبلَ تبعياتِها على استنساخٍ نظيف — أي أن
 *   «إصلاحَ» الاصطلاحِ **يكسر بوابةَ الاستنساخِ النظيف**.
 * ◆ ⇒ **القرار**: يبقى الاصطلاحُ للجديدِ حفاظًا على الترتيب، ويُعلَن العددُ
 *   في كلِّ جولةٍ ولا يُرسِّب. وقلبُه يلزمه إعادةُ ترقيمِ التاريخِ كلِّه دفعةً
 *   واحدةً مع الدفترِ والبصمات — وهو **تغييرُ نطاقٍ بقرارِ مالك**.
 * ◆ **والبوابةُ التي تعاقب على السلوكِ الصحيحِ تُعلِّم الالتفافَ عليها.**
 */
$future = 0; $yr = (int) date('Y');
foreach (array_keys($disk) as $b) {
    if ((int) substr($b, 0, 4) > $yr) { $future++; }
}
printf("\n── ③ اصطلاحُ سنةِ التسمية (مقيسٌ مُعلَنٌ لا حكم) ──\n");
printf("  ملفاتٌ بسنةٍ لم تأتِ بعد: **%d** من %d — والسنةُ الحالية %d\n", $future, count($disk), $yr);
echo "  ◆ الاسمُ المؤرَّخُ للأمامِ مفتاحُ ترتيبِ التطبيق — فقلبُه يكسر ترتيبَ\n";
echo "    الاستنساخِ النظيف. **قرارُ القلبِ للمالكِ ولا يُتَّخذ في جولةِ تنفيذ.**\n";

require_once $ROOT . '/tools/lib/gap_verdict.php';
gapv('GAP-18', count($missing) === 0 && count($driftUndeclared) === 0,
     'كلُّ هجرةٍ على القرصِ مقيَّدةٌ في الدفتر ببصمةٍ مطابقة — والقاعدةُ تمنع تكرارَ الخروج', $bad);

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
