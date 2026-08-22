<?php
/**
 * 2027_10_14_frd_app001_ladder_shadow.php
 *   FR-APP-001 · CHG-APP-LADDER-01 — سجلُّ تباينِ نمطِ الظلِّ لبوابةِ السلّم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-59 · P0): «تشغيلُ نمطِ الظلّ: تمضي المعاملةُ
 *   بالسلوكِ الحاليِّ ويُحسَب ما كان سيفعله السلّمُ · **صفُّ تباينٍ عند
 *   الاختلاف**» · وسلوكُ الفشل: «**لا يوقف الظلُّ معاملةً أبدًا** — وفشلُ
 *   المقارنِ يُسجَّل ولا يمنع».
 *
 * ◆ **ولماذا سجلٌّ جديدٌ لا `guard_denials`**: الأخيرُ يسجّل **المنعَ** وحدَه،
 *   والظلُّ يحتاج **كلَّ تقييم** — سماحًا ومنعًا — ليُقاس مقامُ نافذةِ
 *   الملاحظةِ (٥٠٠ قرارٍ أو ٧ أيام) وتغطيةُ كلِّ نوعِ كيانٍ حيّ. وعدُّ
 *   المنعِ وحدَه يجعل المقامَ مجهولًا فيصير «صفرُ تباين» بلا معنًى.
 *
 * ◆ **والعطالةُ بمفتاح**: (كيان × مرجع × خطوة × فاعل) — فتكرارُ المحاولةِ
 *   لا يضخّم المقامَ ولا يُنشئ تباينًا ثانيًا.
 *
 * التشغيل:  php database/migrations/2027_10_14_frd_app001_ladder_shadow.php
 * الرجوع :  php database/migrations/2027_10_14_frd_app001_ladder_shadow.php --revert
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

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_ladder_shadow`");
    echo "↺ أُسقط سجلُّ ظلِّ السلّم\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_ladder_shadow` (
    `id`            BIGINT NOT NULL AUTO_INCREMENT,
    `company_id`    INT NOT NULL,
    `ladder_code`   VARCHAR(16) NOT NULL,
    `subject_kind`  VARCHAR(48) NOT NULL,
    `subject_ref`   BIGINT NOT NULL,
    `step_no`       INT NULL,
    `actor_id`      INT NOT NULL,
    /* قرارُ السلوكِ الحاليِّ — وهو في نمطِ الظلِّ «مرَّ» دائمًا */
    `current_decision` ENUM('allow','deny') NOT NULL,
    /* ما كان السلّمُ سيقرّره لو كان نافذًا */
    `ladder_decision`  ENUM('allow','deny') NOT NULL,
    `diverged`      TINYINT(1) NOT NULL DEFAULT 0,
    `reason`        VARCHAR(500) NOT NULL DEFAULT '',
    `idem_key`      VARCHAR(128) NOT NULL,
    `observed_at`   DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `u_idem` (`idem_key`),
    KEY `k_div` (`diverged`, `observed_at`),
    KEY `k_kind` (`subject_kind`, `observed_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='FR-APP-001 — ظلُّ بوابةِ السلّم · يرصد ولا يمنع'");

$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_ladder_shadow'");
$made = $q ? (int) $q->fetch_row()[0] : 0;
printf("① سجلُّ الظلِّ: %s\n", $made === 1 ? '✔ قائم' : '✘ لم يُنشأ');
if ($made !== 1) { exit(1); }

/* ── ② نافذةُ الملاحظةِ الدنيا — من مصدرِها لا من عندي ──────────────────── */
echo "② نافذةُ الملاحظةِ الدنيا: **٥٠٠ قرارٍ أو ٧ أيامٍ أيُّهما أكبر**\n";
echo "   (Threshold_Source في الدفتر: برنامج الإصلاح §3 — ولا تُخترع عتبة)\n";
$n = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladder_shadow`")->fetch_row()[0];
printf("③ المرصودُ الآن: %d قرارًا — والنافذةُ لم تُستوفَ بعد\n", $n);
echo "   ◆ فلا يُكتب EVIDENCE_CLOSED لهذا المتطلب: **مبنيٌّ وموصولٌ ولم يُمارَس**.\n";

ems_migration_recorded(__FILE__, $conn, 0);
