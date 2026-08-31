<?php
/**
 * 2028_01_31_cycle_bridge_authoring.php — قواعدُ تأليفِ جسرِ الدورة (البند ⑫)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` ⑫ · §٥·١٠: التأليفُ يحتاج قاعدتَين جديدتَين في حارسِ الجسر:
 *   `C5_AUTHORED`        صفٌّ مؤلَّفٌ بمرحلةٍ من مفرداتِ دورتِه — بمعرِّفٍ إلزاميّ
 *   `C5_NOT_APPLICABLE`  صفُّ سببِ عدمِ انطباقٍ — بمعرِّفٍ إلزاميّ
 *   `C6_MANUAL_AMBIG`    الملتبسُ المحسومُ يدويًّا بالمُصيَّر — بمعرِّفٍ إلزاميّ
 * و`stage_kind` يقبل `not_applicable` (§٥·١٠: سببُ الانطباقِ بدل اختراعِ مرحلة).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$ok = $conn->query("ALTER TABLE gov_screen_cycle
    MODIFY bridge_rule ENUM('','BASENAME_UNIQUE','AMBIGUOUS_DECLARED','NO_LIVE_SURFACE','PATH_OR_SCOPE_RESOLVED','C5_AUTHORED','C5_NOT_APPLICABLE','C6_MANUAL_AMBIG') NOT NULL DEFAULT ''
      COMMENT 'قاعدة الجسر — والمقياس يعلن ايتها حكمت'");
if (!$ok) { exit("✘ bridge_rule: {$conn->error}\n"); }
echo "  ✔ bridge_rule يعرف قواعد التأليف الثلاث\n";

$ok = $conn->query("ALTER TABLE gov_screen_cycle
    MODIFY stage_kind ENUM('canonical','contextual','not_applicable') NOT NULL DEFAULT 'canonical'
      COMMENT 'NF-13 — canonical: مرحلة قانونية · contextual: قراءة سياقية · not_applicable: سبب عدم انطباق (5-10)'");
if (!$ok) { exit("✘ stage_kind: {$conn->error}\n"); }
echo "  ✔ stage_kind يقبل not_applicable\n";

$conn->query("ALTER TABLE gov_screen_cycle DROP CONSTRAINT chk_cyc_bridge");
$ok = $conn->query("ALTER TABLE gov_screen_cycle ADD CONSTRAINT chk_cyc_bridge CHECK (
    (bridge_rule IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED','C5_AUTHORED','C5_NOT_APPLICABLE','C6_MANUAL_AMBIG') AND screen_id <> '')
    OR (bridge_rule NOT IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED','C5_AUTHORED','C5_NOT_APPLICABLE','C6_MANUAL_AMBIG') AND screen_id = '')
)");
if (!$ok) { exit("✘ chk_cyc_bridge: {$conn->error}\n"); }
echo "  ✔ حارسُ الجسرِ يعرف قواعدَ التأليفِ الثلاثَ — والمعرِّفُ فيها إلزاميّ\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
