<?php
/**
 * 2027_12_12_repair01_freeze_tool_version.php — البصمةُ السداسيّةُ تكتمل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر**: `MASTER_EXEC` §٢② — *«كلُّ قياسٍ رسميٍّ يحمل بصمتَه
 *   السداسيّة: `Snapshot ID` · `Exact Commit Hash` · `Schema Version` ·
 *   `Registry Version` · **`Measurement Tool Version`** · `Timestamp`»*.
 *
 * ◆ **وما قِستُه قبل التنفيذ**: `repair01_freeze_snapshot` يحمل **خمسًا** —
 *   المعرِّفَ والبصمةَ والمخطَّطَ والسجلَّ والزمنَ — **وسادستُه غائبة**. وفي
 *   موضعِها `config_baseline` (‏بصمةُ مفاتيحِ البيئة)، وهي مفيدةٌ **وتجيب
 *   سؤالًا آخر**: «بأيِّ إعدادٍ قيس؟» لا «بأيِّ أداةٍ قيس؟».
 *   ⇒ **فالعمودُ يُضاف ولا يُستبدَل** — والسؤالان كلاهما يُسأل.
 *
 * ◆ **ولماذا يلزم**: `RPR-02` §١٠ — *«أدواتُ القياسِ نفسُها موثَّقةٌ في المستودع
 *   فيُعرَف ما الذي قاس»*. ولقطتان بالبصمةِ نفسِها وأداتَين مختلفتَين تُنتجان
 *   رقمَين، **فبلا هذا العمودِ يصير الفرقُ بينهما سؤالًا بلا جواب**.
 *
 * ⛔ **ولا يُملأ التاريخيُّ رجعيًّا بقيمةِ اليوم**: الستُّ لقطاتٍ السابقةُ قيست
 *   بعُدّةٍ لا نعرف بصمتَها الآن، **فتأخذ `—` مُعلَنةً لا قيمةً مخترَعة**.
 *   والقاعدةُ الصلبةُ تلزم الجديدَ وحدَه.
 *
 * التشغيل: php database/migrations/2027_12_12_repair01_freeze_tool_version.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ① العمودُ — يُضاف ولا يُستبدَل ────────────────────────────────────────── */
$r = $conn->query("SHOW COLUMNS FROM `repair01_freeze_snapshot` LIKE 'measurement_tool_version'");
if ($r && $r->num_rows) {
    echo "  ◆ العمودُ قائمٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_freeze_snapshot`
        ADD COLUMN `measurement_tool_version` VARCHAR(64) NOT NULL DEFAULT ''
            COMMENT 'بصمة عدة القياس: MT-<sha1 12>/<عدد الملفات> — السادسة من بصمة MASTER_EXEC 2-2'
            AFTER `config_baseline`");
    if (!$ok) { exit("✘ تعذّرت إضافةُ العمود: {$conn->error}\n"); }
    echo "  ✔ أُضيف `measurement_tool_version`\n";
}

/* ② التاريخيُّ يُعلَن ولا يُخترَع ──────────────────────────────────────── */
$ok = $conn->query("UPDATE `repair01_freeze_snapshot`
                       SET `measurement_tool_version` = '— غير مقيس عند التجميد'
                     WHERE `measurement_tool_version` = ''");
if (!$ok) { exit("✘ تعذّر وسمُ التاريخيّ: {$conn->error}\n"); }
printf("  ✔ وُسمت %d لقطةً تاريخيّةً بـ«غير مقيس» — ولا قيمةَ رجعيّةً مخترَعة\n",
       $conn->affected_rows);

/* ③ القاعدةُ الصلبة: الجديدُ لا يُقيَّد بلا سادسته ──────────────────────
     ⛔ **والشرطُ لا يُطبَّق على القديمِ** — فوسمُ «غير مقيس» غيرُ فارغٍ فيمرّ،
        والفراغُ وحدَه يُردّ. فالقاعدةُ تلزم من بعدَها ولا تُبطل ما قبلَها. */
$r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                      AND CONSTRAINT_NAME = 'chk_frz_tool'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    echo "  ◆ القاعدةُ قائمةٌ سلفًا\n";
} else {
    $ok = $conn->query("ALTER TABLE `repair01_freeze_snapshot`
        ADD CONSTRAINT `chk_frz_tool` CHECK (`measurement_tool_version` <> '')");
    if (!$ok) { exit("✘ تعذّرت إضافةُ القاعدة: {$conn->error}\n"); }
    echo "  ✔ قاعدةٌ صلبة: لا لقطةَ بعمودِ عُدّةٍ فارغ\n";
}

/* ④ الحصيلةُ — بعدٍّ لا بدعوى ────────────────────────────────────────── */
$r = $conn->query("SELECT COUNT(*) n, SUM(`measurement_tool_version` <> '') f
                     FROM `repair01_freeze_snapshot`");
$x = $r->fetch_assoc();
printf("\n  لقطاتٌ: %d · بعمودِ عُدّةٍ مملوء: %d\n", (int) $x['n'], (int) $x['f']);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ البصمةُ صارت سداسيّةً — والسادسةُ تجيب: بأيِّ أداةٍ قيس؟\n";
