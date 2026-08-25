<?php
/**
 * 2027_11_15_repair01_w1_ownership.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W01 — أعمدةُ الحكمِ في دفترِ الملكيّةِ ودفترُ قراراتِ المرحلة.
 *
 * ◆ **لماذا أعمدةٌ لا جدولٌ منفصل**: الحكمُ صفةُ الصفِّ لا كيانٌ مستقل. وفصلُه
 *   في جدولٍ جانبيٍّ يخلق صفًّا بلا حكمٍ وحكمًا بلا صفّ — وهما بالضبط ما
 *   تمنعه المرحلة: «لا صفَّ بلا حكم».
 *
 * ◆ **ولماذا `repair01_w1_decisions` جدولٌ جديدٌ لا صفوفٌ في `repair01_decisions`**:
 *   الأخيرُ **مُجمَّدٌ من المصدرِ عند ١٠٨ صفوف** والبوّابةُ `G0-02` تُثبّت
 *   المقامَ (‏١٠٨ = ٩٢ + ١٦). فحقنُ قرارِ تنفيذٍ فيه يُسقط أساسَ المرحلةِ صفر.
 *   قراراتُ الحملةِ شيءٌ، وقراراتُ المالكِ المُجمَّدةُ شيءٌ آخر.
 *
 * ◆ **والفارغُ بلا مصدرٍ يُسجَّل قرارًا ولا يُخمَّن** (W01 §٤-٣): فكلُّ قيمةٍ
 *   وُضعت بلا مصدرٍ في الدفترِ تحمل `source` يشير إلى صفِّ قرارٍ فيه المبرَّرُ
 *   ومَن يملك حسمَه — لا قيمةً بلا أثرٍ يعود إليها.
 *
 * التشغيل: php database/migrations/2027_11_15_repair01_w1_ownership.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
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

/** هل العمودُ قائم؟ */
function col_exists(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $had = 0; $err = 0;

/* ① دفترُ الملكيّة — حكمُ الظهورِ المحرَّم ومبرَّرُه ودليلُه */
$own = array(
    'w1_verdict'  => "ENUM('REVOKED_TO_OWNER','CONTEXTUAL_READ_ONLY','RECLASSIFY_OWNED','RECLASSIFY_PERSONAL','ESCALATED_DECISION') NULL",
    'w1_rule'     => "VARCHAR(48) NOT NULL DEFAULT ''",
    'w1_reason'   => "VARCHAR(400) NOT NULL DEFAULT ''",
    'w1_evidence' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'w1_at'       => "DATETIME NULL",
);
foreach ($own as $c => $type) {
    if (col_exists($conn, 'repair01_ownership', $c)) { $had++; echo "= repair01_ownership.$c (قائم)\n"; continue; }
    if ($conn->query("ALTER TABLE `repair01_ownership` ADD COLUMN `$c` $type") === false) {
        $err++; echo "✘ repair01_ownership.$c : {$conn->error}\n";
    } else { $done++; echo "✔ repair01_ownership.$c\n"; }
}
if (!col_exists($conn, 'repair01_ownership', 'w1_verdict')) { /* لا مفتاحَ على عمودٍ لم يُنشأ */ }
else {
    $k = $conn->query("SHOW INDEX FROM `repair01_ownership` WHERE Key_name = 'k_w1'");
    if (!$k || $k->num_rows === 0) { $conn->query("ALTER TABLE `repair01_ownership` ADD KEY `k_w1` (`w1_verdict`)"); }
}

/* ② دفترُ الأسطح — مصدرُ الرمزِ المعياريِّ ومصدرُ الدورِ المسؤول */
$sur = array(
    'canon_rule'  => "VARCHAR(48) NOT NULL DEFAULT ''",
    'canon_why'   => "VARCHAR(400) NOT NULL DEFAULT ''",
    'role_source' => "VARCHAR(64) NOT NULL DEFAULT ''",
    'role_why'    => "VARCHAR(400) NOT NULL DEFAULT ''",
);
foreach ($sur as $c => $type) {
    if (col_exists($conn, 'repair01_surfaces', $c)) { $had++; echo "= repair01_surfaces.$c (قائم)\n"; continue; }
    if ($conn->query("ALTER TABLE `repair01_surfaces` ADD COLUMN `$c` $type") === false) {
        $err++; echo "✘ repair01_surfaces.$c : {$conn->error}\n";
    } else { $done++; echo "✔ repair01_surfaces.$c\n"; }
}

/* ③ دفترُ قراراتِ المرحلة — ما لا مصدرَ له في الحزمةِ يُسجَّل هنا لا يُخمَّن */
$ddl = "
CREATE TABLE IF NOT EXISTS `repair01_w1_decisions` (
  `decision_id` VARCHAR(32) NOT NULL,
  `stage`       VARCHAR(8)  NOT NULL DEFAULT 'W01',
  `topic`       VARCHAR(160) NOT NULL DEFAULT '',
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   TEXT NULL,
  `evidence`    VARCHAR(400) NOT NULL DEFAULT '',
  `scope_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      ENUM('RECORDED_PENDING_OWNER','OWNER_APPROVED') NOT NULL DEFAULT 'RECORDED_PENDING_OWNER',
  `decided_at`  DATETIME NULL,
  PRIMARY KEY (`decision_id`), KEY `k_stage` (`stage`), KEY `k_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$exists = $conn->query("SHOW TABLES LIKE 'repair01_w1_decisions'");
$was = $exists && $exists->num_rows > 0;
if ($conn->query($ddl) === false) { $err++; echo "✘ repair01_w1_decisions : {$conn->error}\n"; }
elseif ($was) { $had++; echo "= repair01_w1_decisions (قائم)\n"; }
else { $done++; echo "✔ repair01_w1_decisions\n"; }

echo "\nأُضيف: $done  ·  قائمٌ سلفًا: $had  ·  أخطاء: $err\n";
exit($err === 0 ? 0 : 1);
