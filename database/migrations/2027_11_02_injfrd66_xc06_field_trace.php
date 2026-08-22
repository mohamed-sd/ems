<?php
/**
 * 2027_11_02_injfrd66_xc06_field_trace.php
 *   XC-06 — إدخالُ مصفوفةِ تتبُّعِ الحقولِ القاعدةَ لتصير مقيسةً دائمًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «لكلِّ حقلٍ في المرجعَين هدفٌ في النظامِ أو سببٌ موثَّقٌ لعدمِ
 *   ظهوره — 589 للمبيعاتِ و828 للموردين · **صفرُ حقلٍ بلا حكم**».
 *
 * ◆ **ولماذا تُدخَل القاعدة؟** لأن المصفوفةَ تعيش في مصنَّفٍ خارجَ المستودع
 *   (`INJ-FRD-TRACE-01.xlsx` في مجلَّدِ التنزيلات). وبوابةٌ تقرأ ملفًّا خارجَ
 *   الشجرةِ **لا تُعاد على جهازٍ آخر ولا في التكامل**، فيصير «الأخضرُ» رهنَ
 *   وجودِ ملفٍّ في حاسوبٍ بعينِه. فتُنقل إلى `gov_field_trace` ويُحفظ مصدرُها
 *   نصًّا في `docs/injfrd66/field_trace.tsv` — فتُقاس من الشجرةِ وحدَها.
 *
 * ◆ **ولا يُعاد ترقيمُ الحقول**: `header_cell` + `sheet` + `book` مفتاحٌ
 *   طبيعيٌّ من المصنَّفِ نفسِه — فإعادةُ التحميلِ تُحدِّث ولا تُضاعف.
 *
 * ◆ **والمقامانِ يُتحقَّقان قبلَ الالتزام**: إن لم يجئ 589 و828 فالمصدرُ
 *   ليس النسخةَ الحاكمةَ — وتُوقَف الهجرةُ ولا تُحمَّل مصفوفةٌ مغلوطةُ المقام.
 *
 * التشغيل:  php database/migrations/2027_11_02_injfrd66_xc06_field_trace.php
 * الرجوع :  php database/migrations/2027_11_02_injfrd66_xc06_field_trace.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_field_trace`");
    echo "↺ أُسقط gov_field_trace\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_field_trace` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `book` VARCHAR(60) NOT NULL,
    `sheet` VARCHAR(120) NOT NULL,
    `sheet_no` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `header_cell` VARCHAR(12) NOT NULL,
    `field_no` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `field_name` VARCHAR(255) NOT NULL,
    `as_is` VARCHAR(120) NOT NULL DEFAULT '',
    `to_be` VARCHAR(120) NOT NULL DEFAULT '',
    `visibility_rule` VARCHAR(255) NOT NULL DEFAULT '',
    `judged_from` VARCHAR(160) NOT NULL DEFAULT '',
    `surface_state` VARCHAR(120) NOT NULL DEFAULT '',
    `req_id` VARCHAR(16) NOT NULL DEFAULT '',
    `reconciliation` TEXT NULL,
    UNIQUE KEY `u_field` (`book`,`sheet`,`header_cell`),
    KEY `k_req` (`req_id`),
    KEY `k_tobe` (`to_be`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$SRC = $ROOT . '/docs/injfrd66/field_trace.tsv';
if (!is_file($SRC)) { fwrite(STDERR, "✘ المصدرُ مفقود: {$SRC}\n"); exit(1); }

$lines = file($SRC, FILE_IGNORE_NEW_LINES);
$head  = explode("\t", array_shift($lines));
$ix    = array_flip($head);
foreach (array('المصنَّف', 'الورقة', 'خلية الرأس', 'اسم الحقل',
               'سلوك النظام المستهدف (TO-BE)', 'قاعدة الظهور', 'المتطلب الحاكم') as $need) {
    if (!isset($ix[$need])) { fwrite(STDERR, "✘ عمودٌ مفقودٌ في المصدر: «{$need}»\n"); exit(1); }
}

/* ── ① التحقُّقُ من المقامَين قبلَ أيِّ كتابة ────────────────────────────── */
$rows = array(); $byBook = array(); $unjudged = 0;
foreach ($lines as $ln) {
    if (trim($ln) === '') { continue; }
    $c = explode("\t", $ln);
    if (count($c) < count($head)) { $c = array_pad($c, count($head), ''); }
    $book = trim($c[$ix['المصنَّف']]);
    if ($book === '') { continue; }
    $byBook[$book] = ($byBook[$book] ?? 0) + 1;
    $tobe = trim($c[$ix['سلوك النظام المستهدف (TO-BE)']]);
    if ($tobe === '' || $tobe === '—') { $unjudged++; }
    $rows[] = $c;
}
printf("① المقامات: %s  ·  الإجمالي %d  ·  بلا حكمٍ %d\n",
    implode(' · ', array_map(static fn($k, $v) => "{$k}={$v}", array_keys($byBook), $byBook)),
    count($rows), $unjudged);

$sales = 0; $supp = 0;
foreach ($byBook as $b => $n) {
    if (mb_strpos($b, 'المبيعات') !== false) { $sales = $n; }
    elseif (mb_strpos($b, 'الموردين') !== false) { $supp = $n; }
}
if ($sales !== 589 || $supp !== 828) {
    fwrite(STDERR, "✘ المقامانِ لا يطابقان المرجعَ الحاكم (المبيعات {$sales}/589 · الموردون {$supp}/828)\n");
    fwrite(STDERR, "  المصدرُ ليس النسخةَ الحاكمة — أُوقفت الهجرةُ ولم تُحمَّل مصفوفة\n");
    exit(1);
}
if ($unjudged > 0) {
    fwrite(STDERR, "✘ {$unjudged} حقلًا بلا حكم — والمرجعُ يُعلن صفرًا. أُوقفت الهجرة\n");
    exit(1);
}

/* ── ② التحميل ────────────────────────────────────────────────────────── */
$st = $conn->prepare(
    "INSERT INTO `gov_field_trace`
        (`book`,`sheet`,`sheet_no`,`header_cell`,`field_no`,`field_name`,
         `as_is`,`to_be`,`visibility_rule`,`judged_from`,`surface_state`,`req_id`,`reconciliation`)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        `field_name`=VALUES(`field_name`), `as_is`=VALUES(`as_is`), `to_be`=VALUES(`to_be`),
        `visibility_rule`=VALUES(`visibility_rule`), `judged_from`=VALUES(`judged_from`),
        `surface_state`=VALUES(`surface_state`), `req_id`=VALUES(`req_id`),
        `reconciliation`=VALUES(`reconciliation`)");

$g = static function (array $c, array $ix, string $k): string {
    return isset($ix[$k]) ? trim((string) ($c[$ix[$k]] ?? '')) : '';
};
$n = 0;
foreach ($rows as $c) {
    $book  = $g($c, $ix, 'المصنَّف');
    $sheet = $g($c, $ix, 'الورقة');
    $sno   = (int) $g($c, $ix, 'ترتيب الورقة');
    $cell  = $g($c, $ix, 'خلية الرأس');
    $fno   = (int) $g($c, $ix, 'رقم الحقل');
    $fname = mb_substr($g($c, $ix, 'اسم الحقل'), 0, 250);
    $asis  = mb_substr($g($c, $ix, 'آلية المصدر (AS-IS)'), 0, 110);
    $tobe  = mb_substr($g($c, $ix, 'سلوك النظام المستهدف (TO-BE)'), 0, 110);
    $vis   = mb_substr($g($c, $ix, 'قاعدة الظهور'), 0, 250);
    $jfrom = mb_substr($g($c, $ix, 'مصدر الحكم'), 0, 150);
    $sstat = mb_substr($g($c, $ix, 'حالة السطح'), 0, 110);
    $req   = mb_substr($g($c, $ix, 'المتطلب الحاكم'), 0, 16);
    $recon = $g($c, $ix, 'قرار المصالحة');
    $st->bind_param('ssisissssssss', $book, $sheet, $sno, $cell, $fno, $fname,
        $asis, $tobe, $vis, $jfrom, $sstat, $req, $recon);
    if ($st->execute()) { $n++; }
}
$st->close();

$q = $conn->query("SELECT COUNT(*) c FROM `gov_field_trace`");
printf("② حُمِّل %d صفًّا · في الجدولِ الآن %d\n", $n, (int) $q->fetch_assoc()['c']);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل — والمصفوفةُ تُقاس من الشجرةِ لا من مجلَّدِ التنزيلات\n";
