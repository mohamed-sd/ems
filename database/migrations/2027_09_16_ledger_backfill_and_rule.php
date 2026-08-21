<?php
/**
 * 2027_09_16_ledger_backfill_and_rule.php
 *   دفترُ الهجرات: قيدُ ما خرج · وقاعدةٌ تمنع خروجَ الجديد — NF-05
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيب**: خمسٌ وثلاثون هجرةً خرجت من الدفترِ فقُيِّدت، ثم خرجت خمسَ عشرةَ
 *   أخرى **بالنمطِ نفسِه**. ⇒ الإدخالُ وحدَه علاجُ عَرَض، والنمطُ يتكرر لأن
 *   الهجرةَ المكتوبةَ ملفًّا مستقلًّا **لا تمرُّ بالمُشغِّلِ فلا تُقيِّد نفسَها**.
 *
 * ◆ **فعلاجان معًا**:
 *   ① قيدُ ما خرج — بحالةِ `applied` وبصمتِه الحالية.
 *   ② قاعدةٌ نافذة — `database/migrations/_ledger.php` تُستدعى من كلِّ هجرةٍ
 *     جديدة، وبوابةُ `tests/injexec01_migration_ledger_gate.php` ترسُب إن خرج
 *     ملفٌّ واحد. **فالقاعدةُ هي العلاج، والقيدُ أثرُه.**
 *
 * ◆ **وبصمةٌ لا تُعاد كتابتُها بالتخمين**: ملفٌّ بصمتُه في الدفترِ تخالف قرصَه
 *   يعني أنه حُرِّر بعدَ تطبيقِه. ولا يُعاد ختمُه صامتًا — يُسجَّل استثناءً
 *   مكتوبًا بسببِه، لأن «إعادةَ الختمِ بلا دليلٍ تجعل الدفترَ يوافق أيَّ ملف».
 *
 * التشغيل:  php database/migrations/2027_09_16_ledger_backfill_and_rule.php
 * الرجوع :  php database/migrations/2027_09_16_ledger_backfill_and_rule.php --revert
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

$STAMP = 'INJ-EXEC-01/NF-05';

if (in_array('--revert', $argv, true)) {
    $st = $conn->prepare("DELETE FROM `schema_migrations` WHERE `error_text` = ?");
    $st->bind_param('s', $STAMP); $st->execute();
    echo "↺ حُذف {$st->affected_rows} قيدًا مُضافًا في هذه الهجرة\n";
    $st->close();
    @unlink($ROOT . '/storage/gates/injexec01_ledger_exceptions.json');
    exit(0);
}

/* ── ① قيدُ ما خرج ─────────────────────────────────────────────────────────── */
$disk = array();
foreach (glob($ROOT . '/database/migrations/*.php') as $f) {
    $b = basename($f);
    if ($b[0] === '_' || !preg_match('/^\d{4}_\d{2}_\d{2}_/', $b)) { continue; }
    $disk[$b] = $f;
}
$have = array();
$q = $conn->query("SELECT `filename` FROM `schema_migrations`");
while ($r = $q->fetch_row()) { $have[$r[0]] = true; }

$ins = $conn->prepare(
    "INSERT INTO `schema_migrations`
       (`filename`,`checksum`,`status`,`applied_at`,`execution_ms`,`applied_by`,`error_text`)
     VALUES (?,?,'applied',NOW(),0,?,?)");
$by = (function_exists('get_current_user') ? get_current_user() : 'cli') . '@' . (gethostname() ?: 'unknown');
$n = 0; $names = array();
foreach ($disk as $b => $f) {
    if (isset($have[$b])) { continue; }
    $sum = sha1_file($f);
    $ins->bind_param('ssss', $b, $sum, $by, $STAMP);
    if ($ins->execute()) { $n++; $names[] = $b; }
    else { echo "✘ تعذّر قيدُ {$b}: {$ins->error}\n"; }
}
$ins->close();
printf("① قُيِّد %d ملفًّا كان خارجَ الدفتر\n", $n);
foreach ($names as $x) { echo "   · {$x}\n"; }

/* ── ② الاستثناءُ المكتوبُ للبصمةِ المنزاحة ──────────────────────────────── */
$drift = array();
$q = $conn->query("SELECT `filename`,`checksum`,`status` FROM `schema_migrations`");
while ($r = $q->fetch_assoc()) {
    $b = $r['filename'];
    if (!isset($disk[$b]) || $r['status'] === 'baseline') { continue; }
    if (sha1_file($disk[$b]) !== $r['checksum']) { $drift[] = $b; }
}
$EXC = $ROOT . '/storage/gates/injexec01_ledger_exceptions.json';
if (!is_dir(dirname($EXC))) { @mkdir(dirname($EXC), 0777, true); }
$known = array(
  '2027_06_28_bus_actions_handler_fix.php' =>
    'النسخةُ التي طُبِّقت (2026-08-18 10:45) سابقةٌ لالتزامِها بثلاثِ دقائق (10:48) '
  . 'فلا وجودَ لها في Git ولا يمكن مقارنتُها. **ولا يُعاد ختمُها بالتخمين** — '
  . 'تُعلَن استثناءً مكتوبًا حتى تُعاد الهجرةُ على قاعدةٍ نظيفةٍ فتُختم بدليل.',
);
$out = array('declared_at' => date('c'), 'exceptions' => array());
foreach ($drift as $b) {
    $out['exceptions'][$b] = isset($known[$b]) ? $known[$b] : 'NEEDS_REVIEW — بلا سببٍ مكتوبٍ بعد';
}
file_put_contents($EXC, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("② بصماتٌ منزاحةٌ مُعلَنةٌ استثناءً: %d\n", count($drift));
foreach ($drift as $b) { echo "   · {$b}\n"; }

/* ── ③ الهجرةُ تُقيِّد نفسَها — القاعدةُ تُطبَّق على نفسِها أوَّلًا ─────────── */
ems_migration_recorded(__FILE__, $conn, 0);
$prev = $ROOT . '/database/migrations/2027_09_15_screen_path_reconciliation.php';
if (is_file($prev)) { ems_migration_recorded($prev, $conn, 0); }
echo "③ قُيِّدت هذه الهجرةُ وسابقتُها ذريًّا بـ`ems_migration_recorded()`\n";
