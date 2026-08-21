<?php
/**
 * 2027_09_18_journey_ladder_wired.php
 *   وسمُ الرحلاتِ الموصولةِ فعلًا — INJ-CHAIN-CLOSE-01 · GAP-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا يُوسَم إلا ما قِيس**: `ladder_wired = 1` تُكتب **فقط** للرحلةِ التي
 *   صار سلّمُها يُقرأ عند التنفيذِ من نقطةِ قرارٍ حيّة. والسلاليمُ التي وُصلت
 *   في هذه الجولةِ هي التي تمرُّ بمحرّكِ الوحدات:
 *     LD-01 · LD-02 · LD-03 · LD-04 · LD-05
 *   وشاهدُها `tests/injchain01_ladder_wiring_proof.php` بحزامٍ سلبيّ.
 *
 * ◆ **وما لم يُوصَل يبقى صفرًا مُعلَنًا**: LD-06 (الفاتورة) و LD-07 (الاعتمادُ
 *   الماليُّ النهائيّ) و LD-08..LD-13 (الدفعُ والخزينةُ والمشترياتُ والتسوية)
 *   — قائدُ خطوتِها سطحٌ آخرُ لم يُوصَل بعد. **ووسمُها كذبًا يُغلق GAP-01
 *   على ورقٍ ويتركه مفتوحًا في النظام.**
 *
 * ◆ ويُكتب سببُ كلِّ صفٍّ في `gap_note` — فالحكمُ يُقرأ من السجلِّ لا يُستنتَج.
 *
 * التشغيل:  php database/migrations/2027_09_18_journey_ladder_wired.php
 * الرجوع :  php database/migrations/2027_09_18_journey_ladder_wired.php --revert
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

$WIRED = array('LD-01', 'LD-02', 'LD-03', 'LD-04', 'LD-05');
$NOTE_ON  = 'موصولٌ فعلًا: نقطةُ قرارِ الوحداتِ الواحدة (TimesheetEntryService::approve) '
          . 'تقرأ خطواتِ السلّمِ وأدوارَها قبلَ الكتابة، وترفض من لا يملك خطوتَه، '
          . 'وتمنع أن تمشي يدٌ خطوتَين. شاهدُه injchain01_ladder_wiring_proof بحزامٍ سلبيّ.';
$NOTE_OFF = 'الشاشةُ تقودُ الخطوةَ ولا تقرأ سلّمَها — فترتيبُ الخطواتِ والسقفُ و«لا يدَ تمشي خطوتَين» غيرُ مُنفَّذة';

if (in_array('--revert', $argv, true)) {
    $in = "'" . implode("','", $WIRED) . "'";
    $conn->query("UPDATE `gov_journey_ladders` SET `ladder_wired` = 0,
                    `gap_note` = " . "'" . $conn->real_escape_string($NOTE_OFF) . "'
                  WHERE `ladder_code` IN ({$in})");
    echo "↺ أُعيدت {$conn->affected_rows} رحلةً إلى غيرِ موصولة\n";
    exit(0);
}

$in = "'" . implode("','", $WIRED) . "'";
$st = $conn->prepare("UPDATE `gov_journey_ladders` SET `ladder_wired` = 1, `gap_note` = ?
                       WHERE `ladder_code` IN ({$in})");
$st->bind_param('s', $NOTE_ON);
$st->execute();
$on = $st->affected_rows;
$st->close();

$st = $conn->prepare("UPDATE `gov_journey_ladders` SET `ladder_wired` = 0, `gap_note` = ?
                       WHERE `ladder_code` NOT IN ({$in})");
$st->bind_param('s', $NOTE_OFF);
$st->execute();
$st->close();

$q = $conn->query("SELECT SUM(`ladder_wired`), COUNT(*) FROM `gov_journey_ladders`");
$x = $q ? $q->fetch_row() : array(0, 0);
printf("① وُسِمت %d خطوةَ رحلةٍ موصولةً — والمقام %d · الموصولُ الآن **%d من %d**\n",
       $on, (int) $x[1], (int) $x[0], (int) $x[1]);

$q = $conn->query("SELECT `ladder_code`, COUNT(*) FROM `gov_journey_ladders`
                    WHERE `ladder_wired` = 0 GROUP BY `ladder_code` ORDER BY `ladder_code`");
$rest = array();
while ($q && $r = $q->fetch_row()) { $rest[] = $r[0] . '×' . $r[1]; }
echo "② ما لم يُوصَل بعدُ (مُعلَنٌ لا مطويّ): " . implode(' · ', $rest) . "\n";
echo "   ◆ قائدُ خطوتِها سطحٌ آخرُ — الفاتورةُ والدفعُ والخزينةُ والمشترياتُ والتسوية.\n";
echo "   ◆ **ووسمُها كذبًا يُغلق GAP-01 على ورقٍ ويتركه مفتوحًا في النظام.**\n";

ems_migration_recorded(__FILE__, $conn, 0);
