<?php
/**
 * 2028_04_29_injint01_registers_down.php — عكسُ سجلَّي INJ-INT-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العكسُ تامٌّ بلا فقدِ حقيقة**: الجدولانِ **قياسٌ وحكمٌ** لا حقيقةُ عملٍ —
 *   يُعاد بناؤهما كاملَين بتشغيلِ أداتَيهما، ولا يُشتقُّ منهما قيدٌ ولا أثرٌ
 *   ماليّ. فإسقاطُهما لا يُسقِط معاملةً ولا يُيتِّم صفًّا في جدولٍ آخر.
 *
 * ⛔ **ولا يمسُّ العكسُ سطرًا خارجَ هذين الاسمين**: لا `ems_event_deliveries`
 *   ولا `gov_event_rulings` ولا `fin_*`. فما لم تُنشئه هذه الهجرةُ لا تحذفه.
 *
 * ⚠ **وحكمُ المالكِ إن كُتب يُفقَد**: عمودُ `owner_ruling` قد يحمل قرارًا بشريًّا
 *   لا يُعاد بناؤه بالقياس — فيُصدَّر إلى ملفٍّ بجانبِ الهجرةِ قبلَ الإسقاط.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

/* ═══ ⓪ صونُ حكمِ المالكِ قبلَ الإسقاط — ما لا يُعاد بناؤه بالقياسِ يُصدَّر ═══ */
$ex = $conn->query("SHOW TABLES LIKE 'injint01_retry_disposition'");
if ($ex && $ex->num_rows > 0) {
    $res = $conn->query("SELECT * FROM `injint01_retry_disposition` WHERE `owner_ruling` <> ''");
    $keep = array();
    while ($res && ($r = $res->fetch_assoc())) { $keep[] = $r; }
    if ($keep) {
        $f = __DIR__ . '/2028_04_29_injint01_registers.owner_rulings.json';
        file_put_contents($f, json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        printf("  ⚠ صُدِّر %d حكمَ مالكٍ إلى %s\n", count($keep), basename($f));
    }
}

$dropped = 0;
foreach (array('injint01_retry_disposition', 'injint01_idempotency_audit') as $t) {
    if (!$conn->query("DROP TABLE IF EXISTS `$t`")) { exit("⛔ فشل إسقاط `$t`: " . $conn->error . "\n"); }
    echo "  − `$t` أُسقط\n"; $dropped++;
}
printf("\n◆ عُكست هجرةُ INJ-INT-01: أُسقط %d جدولًا. ولم يُمَسَّ سواهما.\n", $dropped);
