<?php
/**
 * 2027_12_18_amd01_requirement_locks.php — قفلُ دفترِ المتطلباتِ بعد صدقِ محتواه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الترتيبُ نفسُه للمرّةِ الثالثة**: الموضعُ يُفتح (`2027_12_16`) ← ثمَّ يُملأ
 *   بأحكامِ المرحلةِ الثالثةِ وأدلّتِها ← **ثمَّ يُقفل**. وقاعدةٌ تُضاف قبلَ صدقِ
 *   محتواها إمّا تُرَدّ وإمّا **تُغري بملءِ الفراغِ بأيِّ نصٍّ ليمرَّ الحاجب**.
 *
 * ◆ **والقفلان**:
 *   ① **حالةٌ بلا دليلٍ ممنوعة** — `AMD-01` §٨: «متطلبٌ بلا حكمٍ ودليل = صفر».
 *      فحالةٌ مكتوبةٌ بلا `state_evidence` تسمّي قياسَها **دعوى لا حكم**.
 *   ② **حالةٌ بلا لقطةٍ ممنوعة** — `MASTER_EXEC` §٢②: «⛔ ولا تخلطْ أرقامًا من
 *      لقطتَين». فحكمٌ لا يحمل `state_snapshot` لا يُعرَف أيَّ نسخةٍ يمثّل.
 *
 * ⛔ **ولا تُقفل قاعدةٌ صامتةً**: يُقاس الخارقون أوّلًا ويُطبع عددُهم —
 *   فقاعدةٌ تُردّ بلا بيانِ من ردَّها تُغري بحذفِها لا بإصلاحِ سببِها.
 *
 * التشغيل: php database/migrations/2027_12_18_amd01_requirement_locks.php
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

$mk = function ($name, $expr, $violQ, $what) use ($conn) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '" . $name . "'");
    if ($r && (int) $r->fetch_row()[0] > 0) { echo "  ◆ `$name` قائمةٌ سلفًا\n"; return; }
    $v = (int) $conn->query($violQ)->fetch_row()[0];
    if ($v > 0) {
        exit("⛔ **$v صفًّا يخرق «$what»** — والقاعدةُ لا تُضاف فوقَ خرقٍ قائم.\n"
           . "   أصلِحِ المحتوى أوّلًا: php tools/amd01_phase3_requirements.php --apply\n");
    }
    $ok = $conn->query("ALTER TABLE `repair01_requirements` ADD CONSTRAINT `$name` CHECK ($expr)");
    if (!$ok) { exit("✘ تعذّرت `$name`: {$conn->error}\n"); }
    echo "  ✔ قُفلت: $what\n";
};

$mk('chk_req_state_evidence',
    "`amd01_state` IS NULL OR `state_evidence` <> ''",
    "SELECT COUNT(*) FROM repair01_requirements
      WHERE amd01_state IS NOT NULL AND state_evidence = ''",
    'حالةٌ مكتوبةٌ بلا دليلٍ يسمّي قياسَها');

$mk('chk_req_state_snapshot',
    "`amd01_state` IS NULL OR `state_snapshot` <> ''",
    "SELECT COUNT(*) FROM repair01_requirements
      WHERE amd01_state IS NOT NULL AND state_snapshot = ''",
    'حالةٌ مكتوبةٌ بلا معرِّفِ لقطة');

$r = $conn->query("SELECT COALESCE(amd01_state,'(بلا حكم — محجوب)') s, COUNT(*) n
                     FROM repair01_requirements GROUP BY 1 ORDER BY n DESC");
echo "\n  ── حالاتُ الدفتر ──\n";
while ($x = $r->fetch_assoc()) { printf("     %-34s %d\n", $x['s'], $x['n']); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ قُفل الدفترُ — ولا حالةَ بلا دليلٍ ولا لقطة\n";
