<?php
/**
 * 2027_11_15_repair01_w1_ownership_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تراجعُ W01 — نزعُ أعمدةِ الحكمِ ودفترِ قراراتِ المرحلة.
 *
 * ◆ يُشغَّل ضمنَ تسلسلِ التراجعِ الموثَّقِ في `W01_CLOSURE.md §١٠`، ولا يُشغَّل
 *   منفردًا: البوّابةُ `W1-01..08` تقرأ هذه الأعمدةَ فتسقط بعد نزعِها — وهذا
 *   مقصود، فالتراجعُ يُتبَع بإرجاعِ الأدواتِ (`git checkout main`).
 *
 * ◆ **ولا يمسُّ صفًّا واحدًا خارجَ الأعمدةِ الجديدة**: `canonical_code`
 *   و`resp_role` عمودانِ أصليّانِ من `2027_11_12_repair01_store.php` — تُترك
 *   قيمُهما كما هي. وإرجاعُهما إلى حالتِهما قبل W01 يكون بإعادةِ الاستيعاب
 *   (`php tools/repair01_ingest.php`) لا بحذفِ عمود.
 *
 * التشغيل: php database/migrations/2027_11_15_repair01_w1_ownership_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

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

function has_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$gone = 0; $skip = 0; $err = 0;

$k = $conn->query("SHOW INDEX FROM `repair01_ownership` WHERE Key_name = 'k_w1'");
if ($k && $k->num_rows > 0) { $conn->query("ALTER TABLE `repair01_ownership` DROP KEY `k_w1`"); }

$drops = array(
    'repair01_ownership' => array('w1_verdict', 'w1_rule', 'w1_reason', 'w1_evidence', 'w1_at'),
    'repair01_surfaces'  => array('canon_rule', 'canon_why', 'role_source', 'role_why'),
);
foreach ($drops as $t => $cols) {
    foreach ($cols as $c) {
        if (!has_col($conn, $t, $c)) { $skip++; echo "= $t.$c (منزوعٌ سلفًا)\n"; continue; }
        if ($conn->query("ALTER TABLE `$t` DROP COLUMN `$c`") === false) {
            $err++; echo "✘ $t.$c : {$conn->error}\n";
        } else { $gone++; echo "✔ $t.$c نُزع\n"; }
    }
}

$e = $conn->query("SHOW TABLES LIKE 'repair01_w1_decisions'");
if ($e && $e->num_rows > 0) {
    /* ⛔ `ems_app` بلا DROP — والهجرةُ تعمل بمستخدمِ الهجرات، فالحذفُ هنا مقصودٌ
       ومحصورٌ في جدولٍ أنشأته هذه المرحلةُ وحدَها. */
    if ($conn->query("DROP TABLE `repair01_w1_decisions`") === false) {
        $err++; echo "✘ repair01_w1_decisions : {$conn->error}\n";
    } else { $gone++; echo "✔ repair01_w1_decisions حُذف\n"; }
} else { $skip++; echo "= repair01_w1_decisions (غيرُ موجود)\n"; }

echo "\nنُزع: $gone  ·  متجاوَز: $skip  ·  أخطاء: $err\n";
echo "أتبِعْه بـ: git checkout main   ثمّ   php tools/repair01_ingest.php   لإرجاعِ الدفترِ لحالتِه قبل W01\n";
exit($err === 0 ? 0 : 1);
