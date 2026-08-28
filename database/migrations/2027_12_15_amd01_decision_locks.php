<?php
/**
 * 2027_12_15_amd01_decision_locks.php — قفلُ ما صار صادقًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هجرةٌ ثانية**: القاعدتان أُخِّرتا في `2027_12_14` لأنَّ إضافتَهما
 *   حينَها تُرَدّ بأحدَ عشرَ صفًّا `CONFIG_PENDING` بلا مرحلةٍ مكتوبة — **وهو
 *   خرقٌ قائمٌ صادقٌ لا عطبٌ في القاعدة**. وقد مُلئت المراحلُ بأحكامِ المرحلةِ
 *   الثانيةِ ومراجعِها، **فتُقفل الآن**.
 *
 * ◆ **والقفلُ يلي صدقَ المحتوى لا يسبقه**: قاعدةٌ تُضاف قبلَ أن يصدق محتواها
 *   إمّا تُرَدّ وإمّا **تُغري بملءِ الفراغِ بأيِّ نصٍّ ليمرَّ الحاجب** — وذاك
 *   أسوأُ من غيابِ القاعدة، لأنّه يُنتج خضرةً كاذبة.
 *
 * ◆ **والقفلان**:
 *   ① `CONFIG_PENDING` بلا مرحلةٍ ممنوعة — `MASTER_EXEC` §٣: «ولكلِّ
 *      `CONFIG_PENDING` تُذكر صراحةً المرحلةُ التي تصير عندها حاجزًا».
 *   ② حكمٌ بلا مرجعٍ ممنوع — `RPR-02` §٤·٢: «فحكمٌ صحيحٌ بلا شاهدٍ لا يُقبل».
 *
 * ⛔ **ولا تُضاف قاعدةٌ صامتةً**: كلٌّ تُقاس قبلَ إضافتِها، ويُطبع عددُ الخارقين
 *   إن وُجدوا — فقاعدةٌ تُردّ بلا بيانِ من ردَّها تُغري بحذفِها.
 *
 * التشغيل: php database/migrations/2027_12_15_amd01_decision_locks.php
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
    /* ⛔ **يُقاس الخارقون قبلَ الإضافة** — فرفضٌ بلا بيانٍ يُغري بحذفِ القاعدة */
    $v = (int) $conn->query($violQ)->fetch_row()[0];
    if ($v > 0) {
        exit("⛔ **$v صفًّا يخرق «$what»** — والقاعدةُ لا تُضاف فوقَ خرقٍ قائم.\n"
           . "   أصلِحِ المحتوى أوّلًا: php tools/amd01_phase2_decisions.php --apply\n");
    }
    $ok = $conn->query("ALTER TABLE `repair01_decisions` ADD CONSTRAINT `$name` CHECK ($expr)");
    if (!$ok) { exit("✘ تعذّرت `$name`: {$conn->error}\n"); }
    echo "  ✔ قُفلت: $what\n";
};

$mk('chk_dec_cfg_stage',
    "`blocking_level` <> 'CONFIG_PENDING' OR `config_pending_stage` <> ''",
    "SELECT COUNT(*) FROM repair01_decisions
      WHERE blocking_level = 'CONFIG_PENDING' AND config_pending_stage = ''",
    'قيمةٌ مؤجَّلةٌ بلا بيانِ مرحلتِها');

$mk('chk_dec_verdict_ref',
    "`amd01_verdict` IS NULL OR `amd01_verdict_ref` <> ''",
    "SELECT COUNT(*) FROM repair01_decisions
      WHERE amd01_verdict IS NOT NULL AND amd01_verdict_ref = ''",
    'حكمٌ بلا مرجع');

$r = $conn->query("SELECT COUNT(*) n, SUM(config_pending_stage <> '') s
                     FROM repair01_decisions WHERE blocking_level = 'CONFIG_PENDING'");
$x = $r->fetch_assoc();
printf("\n  `CONFIG_PENDING`: %d · وبمرحلةٍ مكتوبة: %d\n", (int) $x['n'], (int) $x['s']);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));

echo "\n✔ قُفلت القاعدتان — والقيمةُ المؤجَّلةُ تُؤجَّل ولا تُنسى\n";
