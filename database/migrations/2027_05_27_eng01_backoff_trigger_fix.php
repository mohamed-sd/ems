<?php
/**
 * 2027_05_27_eng01_backoff_trigger_fix.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · تصحيحُ قادحِ F-13 وردمُ طريقةِ الإهلاكِ من العمودِ الصحيح
 * ───────────────────────────────────────────────────────────────────────────
 * ① قادحُ التباعد: النصُّ في TS-01 «POW(4, attempt_no)» بتعليقِ «1·4·16·64·256».
 *   والعاملُ يزيد attempt_no مع وسمِ الفشلِ نفسِه، فقراءةُ NEW تعطي 4 للمحاولةِ
 *   الأولى لا 1. والصحيحُ قراءةُ OLD — أي عددُ المحاولاتِ قبلَ هذه — فتخرج
 *   المتتاليةُ كما أعلنتها الوثيقةُ حرفًا:
 *     OLD=0 → 1ث · 1 → 4ث · 2 → 16ث · 3 → 64ث · 4 → 256ث · ثم dlq
 *
 * ② depr_method: الهجرةُ السابقةُ قرأت equipments.category وهو غيرُ موجود —
 *   والعمودُ الحيُّ operating_category. «المخرَجُ يتقادم فيكذب» فالردمُ أُعيد.
 *   (ولا تُعدَّل هجرةٌ طُبِّقت — فالتصحيحُ هجرةٌ جديدةٌ لا تحريرٌ للقديمة.)
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
$run = function (string $sql, string $label) use ($conn): bool {
    if ($conn->query($sql)) { echo "   ✔ $label\n"; return true; }
    echo "   ✗ $label — " . $conn->error . "\n"; return false;
};

echo "\n▐ ① قادحُ F-13 — التباعدُ من OLD.attempt_no (1·4·16·64·256)\n";
$conn->query("DROP TRIGGER IF EXISTS `trg_delivery_backoff`");
$run("CREATE TRIGGER `trg_delivery_backoff` BEFORE UPDATE ON `ems_event_deliveries`
      FOR EACH ROW
      BEGIN
        IF NEW.`state` = 'failed' AND NEW.`attempt_no` > OLD.`attempt_no` THEN
          SET NEW.`next_attempt_at` = NOW(3) + INTERVAL POW(4, LEAST(OLD.`attempt_no`, 4)) SECOND;
        END IF;
        IF NEW.`state` = 'processed' AND NEW.`processed_at` IS NULL THEN
          SET NEW.`processed_at` = NOW(3);
        END IF;
        IF NEW.`state` = 'dlq' AND NEW.`processed_at` IS NULL THEN
          SET NEW.`processed_at` = NOW(3);
        END IF;
      END", 'TRIGGER trg_delivery_backoff (OLD.attempt_no)');

echo "\n▐ ② ردمُ depr_method من operating_category (لا category)\n";
$conn->query(
    "UPDATE `asset_hour_reconciliations` a
       JOIN `equipments` e ON e.id = a.`equipment_id`
       JOIN `fleet_depreciation_profile` p
         ON p.`asset_category` = e.`operating_category` AND p.`state` = 'approved'
        SET a.`depr_method` = CASE p.`method` WHEN 'uop' THEN 'usage_hours' ELSE 'straight_line' END,
            a.`useful_life_hours` = CASE WHEN p.`method` = 'uop' THEN CAST(p.`useful_life` AS UNSIGNED) ELSE NULL END
      WHERE a.`depr_method` IS NULL AND a.`owner_type` = 'company'"
);
$n = $conn->affected_rows;
echo ($n >= 0 ? "   ✔ رُدمت طريقةُ الإهلاكِ لـ$n صفًّا\n" : "   ✗ " . $conn->error . "\n");

$r = $conn->query("SELECT COALESCE(depr_method,'(غير مقرَّرة)') m, COUNT(*) c
                     FROM `asset_hour_reconciliations` WHERE owner_type='company' GROUP BY m");
while ($x = $r->fetch_row()) { printf("      %-20s %s\n", $x[0], $x[1]); }

echo "\n";
