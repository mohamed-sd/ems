<?php
/**
 * 2027_09_30_journey_wired_measured.php
 *   `ladder_wired` يُوسَم بالقياسِ من الشيفرةِ الحيّة — لا بقائمةٍ مكتوبةٍ يدًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الفرقُ بين وسمٍ وقياس**: الهجرةُ السابقةُ وسمت ستَّ خطواتٍ من قائمةٍ
 *   كتبتُها. وهذه **تمسح شجرةَ الإنتاج** وتسمّي كلَّ سلّمٍ يُقرأ فعلًا من نقطةِ
 *   قرارٍ حيّة — فالوسمُ أثرُ القياسِ لا مصدرُه.
 *
 * ◆ **وما يُعَدُّ وصلًا** أحدُ اثنين لا ثالثَ لهما:
 *   ① نداءُ `ems_ladder_guard(..., 'LD-nn', ...)` في ملفِّ إنتاج
 *   ② خريطةُ مراحلِ سلسلةِ الوحدات `ems_uc_stage_ladder()` — وهي نقطةُ
 *     قرارِ الوحداتِ الواحدة
 *   **وذِكرُ الرمزِ في تعليقٍ أو وثيقةٍ ليس وصلًا** — كما أن ذِكرَ رمزِ الفجوةِ
 *   ليس إغلاقًا.
 *
 * ◆ ويُعلَن ما لم يُوصَل بعددِه وسببِه — ولا يُوسَم شيءٌ كذبًا.
 *
 * التشغيل:  php database/migrations/2027_09_30_journey_wired_measured.php
 * الرجوع :  php database/migrations/2027_09_30_journey_wired_measured.php --revert
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

$NOTE_OFF = 'الشاشةُ تقودُ الخطوةَ ولا تقرأ سلّمَها — فترتيبُ الخطواتِ والسقفُ و«لا يدَ تمشي خطوتَين» غيرُ مُنفَّذة';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("UPDATE `gov_journey_ladders` SET `ladder_wired` = 0, `gap_note` = ?");
    $st->bind_param('s', $NOTE_OFF); $st->execute();
    echo "↺ أُعيدت {$st->affected_rows} خطوةً إلى غيرِ موصولة\n";
    $st->close();
    exit(0);
}

/* ── ① المسحُ: أيُّ سلّمٍ يُقرأ من نقطةِ قرارٍ حيّة ─────────────────────── */
$SKIP = array('vendor', 'node_modules', '.git', 'docs', 'tests', 'tools', 'storage', 'database', 'logs');
$wired = array(); $where = array();
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    function ($cur) use ($ROOT, $SKIP) {
        if (!$cur->isDir()) { return true; }
        $rel = str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT) + 1));
        return !in_array(explode('/', $rel)[0], $SKIP, true);
    }));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $src = (string) @file_get_contents($f->getPathname());
    /* ① نداءُ البوابةِ العامة — الرمزُ داخلَ النداءِ نفسِه لا في تعليق */
    if (preg_match_all("/ems_ladder_guard\s*\([^;]*?'(LD-\d{2})'/s", $src, $m)) {
        foreach ($m[1] as $ld) { $wired[$ld] = true; $where[$ld][] = $rel; }
    }
    /* ② خريطةُ مراحلِ سلسلةِ الوحدات — نقطةُ قرارٍ واحدةٌ لسبعِ مراحل */
    if (strpos($rel, 'unit_chain_helpers.php') !== false
        && preg_match_all("/=>\s*'(LD-\d{2})'/", $src, $m2)) {
        foreach ($m2[1] as $ld) { $wired[$ld] = true; $where[$ld][] = $rel; }
    }
}
ksort($wired);
printf("① سلاليمُ تُقرأ من نقطةِ قرارٍ حيّة: **%d** — %s\n",
       count($wired), implode(' · ', array_keys($wired)));

/* ── ② الوسمُ بالقياس ────────────────────────────────────────────────── */
$NOTE_ON = 'موصولٌ فعلًا: سلّمُه يُقرأ من نقطةِ قرارٍ حيّةٍ قبلَ الكتابة — '
         . 'تُفحص أهليةُ الدورِ و«لا يدَ تمشي خطوتَين» من `gov_ladder_decisions`. '
         . 'مقيسٌ من الشجرةِ لا مكتوبٌ يدًا.';
$in = "'" . implode("','", array_keys($wired)) . "'";
$st = $conn->prepare("UPDATE `gov_journey_ladders` SET `ladder_wired` = 1, `gap_note` = ?
                       WHERE `ladder_code` IN ({$in})");
$st->bind_param('s', $NOTE_ON); $st->execute(); $on = $st->affected_rows; $st->close();

$st = $conn->prepare("UPDATE `gov_journey_ladders` SET `ladder_wired` = 0, `gap_note` = ?
                       WHERE `ladder_code` NOT IN ({$in})");
$st->bind_param('s', $NOTE_OFF); $st->execute(); $st->close();

$q = $conn->query("SELECT SUM(`ladder_wired`), COUNT(*) FROM `gov_journey_ladders`");
$x = $q ? $q->fetch_row() : array(0, 0);
printf("② الموصولُ الآن: **%d من %d** (حُدِّث %d صفًّا)\n", (int) $x[0], (int) $x[1], $on);

$q = $conn->query("SELECT DISTINCT `ladder_code` FROM `gov_journey_ladders` WHERE `ladder_wired` = 0");
$rest = array();
while ($q && $r = $q->fetch_row()) { $rest[] = $r[0]; }
if ($rest) {
    echo "③ ما لم يُوصَل بعد: " . implode(' · ', $rest) . "\n";
    echo "   ◆ ويبقى صفرًا مُعلَنًا — **ووسمُه كذبًا يُغلق GAP-01 على ورقٍ ويتركه مفتوحًا**.\n";
} else {
    echo "③ **لا سلّمَ غيرَ موصول** — وكلُّ خطوةِ رحلةٍ تقرأ سلّمَها عند التنفيذ\n";
}
foreach ($where as $ld => $files) {
    printf("   %-7s %s\n", $ld, implode(' · ', array_unique(array_slice($files, 0, 2))));
}

ems_migration_recorded(__FILE__, $conn, 0);
