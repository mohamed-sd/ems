<?php
/**
 * tools/gov_exec_fresh_install_drill.php — تمرينُ التثبيتِ من الصفرِ وقيدُه (FR-GOV-014)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي يخدمه**: `tests/injfrd01_gov014_clean_clone_fingerprint.php`
 *   يشترط تمرينَ تثبيتٍ **غيرَ متقادم**: `dr_drills.finished_at ≥ MAX(schema_migrations.applied_at)`.
 *   فكلُّ هجرةٍ جديدةٍ **تُقادِم** آخرَ تمرينٍ وتُرسِّب الحزام. وإعادةُ التمرينِ
 *   يدويًّا في كلِّ إدارةٍ تعني خطواتٍ تُنسى — فالعُدّةُ تفعلها بخطوةٍ واحدة.
 *
 * ◆ **وما يُقاس فعلًا** (لا «نجح» تُكتب): المُثبِّتُ يبني قاعدةً بكرًا من
 *   `database/schema/` ثمَّ **تُقارَن كائناتُها بكائناتِ القاعدةِ الحيّة**
 *   جدولًا جدولًا. والفرقُ يُسمّى ولا يُبتلع: جدولٌ حيٌّ غائبٌ عن المُثبِّتِ
 *   يعني **مخطَّطًا لا يُستنسَخ** — وهو بعينِه ما تحرسه هذه البوابة.
 *
 * ◆ **ولا يُقيَّد حكمٌ قبل القياس**: `verdict` يُشتقُّ من الفرقِ المقيس،
 *   و`operator_note` يحمل العددَ والفرقَ باسمِه.
 *
 * ◆ **والنظافةُ جزءٌ من التمرين**: قاعدةُ التمرينِ تُسقَط وعلامةُ `.installed`
 *   تُزال إن لم تكن قائمةً قبله — فلا يترك التمرينُ أثرًا يُربك التالي.
 *
 * التشغيل: php tools/gov_exec_fresh_install_drill.php --note="سبب التمرين"
 *          [--keep] (يُبقي قاعدةَ التمرينِ للفحص)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$NOTE = ''; $KEEP = in_array('--keep', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--note=') === 0) { $NOTE = substr($a, 7); } }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$adminU = ems_env('DB_ADMIN_USER') ?: ems_env('DB_USER');
$adminP = ems_env('DB_ADMIN_USER') ? ems_env('DB_ADMIN_PASS') : ems_env('DB_PASS');
$live   = ems_env('DB_NAME');

$mu = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$mp = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $mu, $mp, $live, $port);
if ($conn->connect_errno) { exit("⛔ الحيّة: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* رقمُ المحضرِ التالي — من الدفترِ لا من عدٍّ في الرأس */
$r = $conn->query("SELECT drill_no FROM dr_drills WHERE drill_no LIKE 'DR-%'
                    ORDER BY CAST(SUBSTRING_INDEX(drill_no,'-',-1) AS UNSIGNED) DESC LIMIT 1");
$last = $r ? $r->fetch_row() : null;
$n = $last ? ((int) substr($last[0], -4) + 1) : 1;
$drill = sprintf('DR-%s-%04d', date('Y'), $n);
$dbName = 'ems_drill_' . $n;
echo "═ تمرينُ تثبيتٍ من الصفر · {$drill} ⇐ {$dbName} ═\n";

/* المواصفةُ من `.env` — ولا كلمةَ مرورٍ تُطبع */
$env = array();
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
    list($k, $v) = explode('=', $ln, 2);
    $env[trim($k)] = trim($v);
}
$cfg = array(
    'db_host' => $env['DB_HOST'], 'db_name' => $dbName,
    'db_user' => $adminU, 'db_pass' => $adminP, 'db_create' => true,
    'company_name' => 'تمرين تثبيت نظيف', 'company_email' => 'drill@equipation.sd',
    'admin_name' => 'مشغل التمرين', 'admin_username' => 'drill_admin',
    'admin_password' => bin2hex(random_bytes(8)), 'admin_email' => 'drill@equipation.sd',
);
$specFile = sys_get_temp_dir() . '/ems_drill_' . getmypid() . '.json';
file_put_contents($specFile, json_encode($cfg, JSON_UNESCAPED_UNICODE));
$markerPre = is_file($ROOT . '/.installed');

/* ⛔ **التمرينُ يبدأ من العدمِ وإلّا فليس تمرينًا**: قاعدةٌ باقيةٌ من تشغيلةٍ
   سابقةٍ تجعل المُثبِّتَ يرفض العملَ فوق قاعدةٍ عامرةٍ، **فتُقاس كائناتُها
   القديمةُ وتُقيَّد حكمًا كاذبًا**. ودرسٌ مقيسٌ في أوّلِ تشغيلة: قُيِّد
   `fail` وكائناتُه مطابقةٌ تمامًا — لأنَّ المقارنةَ وقعت على أثرِ تشغيلةٍ
   سابقة. فالإسقاطُ **قبلَ** التثبيتِ شرطٌ لا نظافةٌ بعده.
   ⛔ ولا يُسقَط إلّا ما يطابق نمطَ قواعدِ التمرينِ حصرًا. */
$adm0 = new mysqli($host, $adminU, $adminP, '', $port);
if ($adm0->connect_errno) { exit("⛔ المدير: {$adm0->connect_error}\n"); }
if (!preg_match('~^ems_drill_\d+$~', $dbName)) { exit("⛔ اسمُ قاعدةِ التمرينِ خارجَ نمطِه\n"); }
$q0 = $adm0->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '"
                 . $adm0->real_escape_string($dbName) . "'");
if ($q0 && $q0->num_rows > 0) {
    $adm0->query("DROP DATABASE `{$dbName}`");
    echo "  ○ أُسقطت قاعدةُ تمرينٍ باقيةٌ بالاسمِ نفسِه قبلَ البدء\n";
}
$adm0->close();

$startedAt = date('Y-m-d H:i:s');
$t0 = microtime(true);
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/database/install.php')
                . ' --config=' . escapeshellarg($specFile) . ' --yes 2>&1');
@unlink($specFile);
$installed = (mb_strpos((string) $out, 'اكتمل التثبيت') !== false);
echo $installed ? "  ✔ المُثبِّتُ أكمل\n" : "  ✘ المُثبِّتُ لم يكمل:\n" . $out . "\n";
$rto = (int) round(microtime(true) - $t0);

/* ── القياس: كائناتُ البكرِ مقابلَ الحيّة ─────────────────────────────────── */
$adm = new mysqli($host, $adminU, $adminP, '', $port);
if ($adm->connect_errno) { exit("⛔ المدير: {$adm->connect_error}\n"); }
$adm->set_charset('utf8mb4');
$objOf = function ($schema) use ($adm) {
    $o = array();
    $st = $adm->prepare("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
    $st->bind_param('s', $schema);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_row()) { $o[$x[0]] = $x[1]; }
    $st->close();
    return $o;
};
$fresh = $objOf($dbName);
$liveO = $objOf($live);
$missingInFresh = array_diff(array_keys($liveO), array_keys($fresh));
$extraInFresh   = array_diff(array_keys($fresh), array_keys($liveO));
printf("  الحيّة: %d كائنًا · البكر: %d كائنًا\n", count($liveO), count($fresh));
printf("  حيٌّ غائبٌ عن البكرِ: **%d** · بكرٌ زائدٌ: %d\n", count($missingInFresh), count($extraInFresh));
foreach (array_slice(array_values($missingInFresh), 0, 12) as $m) { echo "     ✘ لا يُستنسَخ: {$m}\n"; }
foreach (array_slice(array_values($extraInFresh), 0, 6) as $m)   { echo "     ○ في البكرِ فقط: {$m}\n"; }

$verdict = ($installed && count($missingInFresh) === 0) ? 'pass' : 'fail';
$note = ($NOTE !== '' ? $NOTE . ' — ' : '')
      . 'كائنات البكر ' . count($fresh) . ' مقابل الحية ' . count($liveO)
      . ' · حي غائب عن البكر ' . count($missingInFresh)
      . ' · بكر زائد ' . count($extraInFresh);

/* ◆ **بصمةُ مجموعةِ الهجراتِ وقتَ التمرين** — بها يُحكَم على التقادمِ بلا
     ساعةِ حائطٍ ترجع. انظر رأسَ `tools/lib/migration_set_hash.php`. */
require_once __DIR__ . '/lib/migration_set_hash.php';
$msh = ems_migration_set_hash($conn);
$note .= ' · بصمةُ مجموعةِ الهجرات ' . substr($msh['hash'], 0, 10) . ' على ' . $msh['count'] . ' هجرة';

$st = $conn->prepare("INSERT INTO dr_drills
    (company_id, entity_layer, drill_no, drill_kind, started_at, finished_at, target_point,
     rpo_target_minutes, rpo_actual_minutes, rto_actual_seconds,
     rows_before, rows_after_expected_gone, rows_after_actual, verdict,
     evidence_path, runbook_ref, operator_note, migration_set_hash,
     created_by, created_by_role, approved_at)
    VALUES (1,'operations',?,'fresh_install',?,NOW(3),'0000-00-00 00:00:00',15,0,?,?,?,?,?,
            ?, ?, ?, ?, 0, 0, NOW())");
$ev  = 'docs/REPAIR01_20260823/RESTORE_DRILL_20260830.md';
$run = 'php tools/gov_exec_fresh_install_drill.php (database/install.php --db-name=' . $dbName . ')';
$live_n = count($liveO); $fresh_n = count($fresh); $miss_n = count($missingInFresh);
$mshHash = $msh['hash'];
$st->bind_param('ssiiiisssss', $drill, $startedAt, $rto, $live_n, $fresh_n, $miss_n, $verdict, $ev, $run, $note, $mshHash);
if (!$st->execute()) { exit("⛔ قيدُ المحضر: {$conn->error}\n"); }
$st->close();
printf("  ✔ قُيِّد %s · الحكم: **%s** · زمنُ التثبيت %d ثانية\n", $drill, $verdict, $rto);

if (!$KEEP) {
    $adm->query("DROP DATABASE IF EXISTS `" . str_replace('`', '', $dbName) . "`");
    echo "  ✔ أُسقطت قاعدةُ التمرين\n";
    if (!$markerPre && is_file($ROOT . '/.installed')) {
        @unlink($ROOT . '/.installed');
        echo "  ✔ أُزيلت علامةُ التثبيتِ التي أنشأها التمرين\n";
    }
}
exit($verdict === 'pass' ? 0 : 1);
