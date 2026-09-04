<?php
/**
 * 2028_04_29_injint01_registers.php — سجلّا INJ-INT-01: التصرُّفُ في التسليمِ وتغطيةُ اللاتكرار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إضافةٌ محضةٌ لا تمسُّ قائمًا**: جدولانِ جديدانِ فحسب. لا `ALTER` على جدولٍ
 *   حيٍّ، ولا إدراجَ في سجلٍّ قائم، ولا حذف. فالمخاطرةُ صفرٌ بنيويًّا لا تقديرًا.
 *
 * ◆ **`injint01_retry_disposition`** — حكمُ كلِّ صفِّ تسليمٍ عالقٍ فردًا فردًا
 *   (‏§21 من الأمر). فالأمرُ يمنع إعادةَ الإرسالِ دفعةً واحدةً، ويُلزِم أن يُسأل
 *   عن كلِّ تسليمٍ: أَقابلٌ مصدرُه للحلّ؟ أَمُحقَّقٌ أثرُه؟ أَمقفلةٌ فترتُه؟
 *
 * ⛔ **والحكمُ يُخزَّن بمفرداتِه لا بنصٍّ حر**: `SAFE_RETRY` و`EFFECT_ALREADY_REALIZED`
 *   و`ENTITY_STATE_CHANGED` و`PERIOD_CLOSED` و`SOURCE_UNRESOLVABLE`
 *   و`MANUAL_RECONCILIATION` و`NON_RETRYABLE` — سبعةٌ كما نصَّ الأمرُ حرفًا.
 *   ومفردةٌ خارجَها تصير `''` صامتًا في ENUM، فلا يُكتب إلا ما اشتُقَّ من هنا.
 *
 * ◆ **`injint01_idempotency_audit`** — لكلِّ مستهلكٍ نشِطٍ ستةُ أسئلةِ §24:
 *   أينشئ المنتِجُ مفتاحًا؟ أتعيد المحاولةُ المفتاحَ نفسَه؟ أيفحص المستهلكُ قبلَ
 *   الأثر؟ أفي مخزنِ الأثرِ قيدُ فرادة؟ ما مآلُ التسليمِ المكرَّر؟ ما التعويض؟
 *
 * ⛔ **ولا يحمل السجلّانِ حقيقةَ عملٍ**: كلاهما **قياسٌ وحكمٌ** — يُعاد بناؤهما
 *   بإعادةِ التشغيلِ ولا يُشتقُّ منهما قيدٌ ولا أثرٌ ماليّ. فسقوطُهما لا يُسقِط
 *   معاملةً، وحذفُهما في العكسِ لا يفقد حقيقة.
 *
 * التشغيل: php database/migrations/2028_04_29_injint01_registers.php
 * العكس  : php database/migrations/2028_04_29_injint01_registers_down.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$t0 = microtime(true);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$ddl = array();

/* ═══ ① سجلُّ التصرُّفِ في التسليمِ العالق (‏§21) ═══════════════════════════ */
$ddl['injint01_retry_disposition'] =
    "CREATE TABLE IF NOT EXISTS `injint01_retry_disposition` (\n"
  . "  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
  . "  `delivery_id` BIGINT UNSIGNED NOT NULL COMMENT 'ems_event_deliveries.id',\n"
  . "  `event_id` BIGINT UNSIGNED NULL COMMENT 'ems_business_events.id',\n"
  . "  `event_key` VARCHAR(80) NOT NULL DEFAULT '',\n"
  . "  `consumer_key` VARCHAR(64) NOT NULL DEFAULT '',\n"
  . "  `delivery_state` VARCHAR(16) NOT NULL DEFAULT '',\n"
  . "  `fail_code` VARCHAR(32) NOT NULL DEFAULT '',\n"
  . "  `entity_type` VARCHAR(40) NOT NULL DEFAULT '',\n"
  . "  `entity_id` BIGINT UNSIGNED NULL,\n"
  . "  `resolved_table` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'الجدولُ الذي حُلَّ إليه نوعُ الكيان',\n"
  . "  `source_resolvable` TINYINT(1) NOT NULL DEFAULT 0,\n"
  . "  `existing_links` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'fin_event_links',\n"
  . "  `existing_effects` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'fin_event_effects',\n"
  . "  `amount` DECIMAL(16,2) NULL,\n"
  . "  `disposition` ENUM('SAFE_RETRY','EFFECT_ALREADY_REALIZED','ENTITY_STATE_CHANGED',"
  . "'PERIOD_CLOSED','SOURCE_UNRESOLVABLE','MANUAL_RECONCILIATION','NON_RETRYABLE') NOT NULL,\n"
  . "  `evidence` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'لماذا هذا الحكمُ — بالقياسِ لا بالرأي',\n"
  . "  `owner_ruling` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'يُملأ بقرارِ المالكِ وحدَه',\n"
  . "  `snapshot_id` VARCHAR(64) NOT NULL DEFAULT '',\n"
  . "  `measured_at` DATETIME NOT NULL,\n"
  . "  PRIMARY KEY (`id`),\n"
  . "  UNIQUE KEY `uq_delivery_snapshot` (`delivery_id`, `snapshot_id`),\n"
  . "  KEY `ix_disposition` (`disposition`),\n"
  . "  KEY `ix_event_key` (`event_key`)\n"
  . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n"
  . "  COMMENT='INJ-INT-01 §21 — حكمُ التصرُّفِ في كلِّ تسليمٍ عالقٍ فردًا فردًا'";

/* ═══ ② تدقيقُ تغطيةِ اللاتكرارِ لكلِّ مستهلكٍ نشِط (‏§24) ═══════════════════ */
$ddl['injint01_idempotency_audit'] =
    "CREATE TABLE IF NOT EXISTS `injint01_idempotency_audit` (\n"
  . "  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
  . "  `consumer_key` VARCHAR(64) NOT NULL,\n"
  . "  `consumer_class` VARCHAR(190) NOT NULL DEFAULT '',\n"
  . "  `event_keys` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'كم مفتاحًا يشترك فيه',\n"
  . "  `side_effecting` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'أذو أثرٍ أم مراقبٌ فقط',\n"
  . "  `producer_generates_key` TINYINT(1) NOT NULL DEFAULT 0,\n"
  . "  `retry_reuses_key` TINYINT(1) NOT NULL DEFAULT 0,\n"
  . "  `consumer_checks_before_effect` TINYINT(1) NOT NULL DEFAULT 0,\n"
  . "  `effect_store_unique` TINYINT(1) NOT NULL DEFAULT 0,\n"
  . "  `unique_constraint_name` VARCHAR(128) NOT NULL DEFAULT '',\n"
  . "  `duplicate_delivery_outcome` VARCHAR(120) NOT NULL DEFAULT '',\n"
  . "  `compensation_behavior` VARCHAR(120) NOT NULL DEFAULT '',\n"
  . "  `verdict` ENUM('COVERED','PARTIAL','UNCOVERED','NOT_APPLICABLE') NOT NULL,\n"
  . "  `evidence` VARCHAR(500) NOT NULL DEFAULT '',\n"
  . "  `snapshot_id` VARCHAR(64) NOT NULL DEFAULT '',\n"
  . "  `measured_at` DATETIME NOT NULL,\n"
  . "  PRIMARY KEY (`id`),\n"
  . "  UNIQUE KEY `uq_consumer_snapshot` (`consumer_key`, `snapshot_id`),\n"
  . "  KEY `ix_verdict` (`verdict`)\n"
  . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n"
  . "  COMMENT='INJ-INT-01 §24 — ستةُ أسئلةِ اللاتكرارِ لكلِّ مستهلكٍ نشِط'";

$made = 0; $had = 0;
foreach ($ddl as $table => $sql) {
    $ex = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $existed = $ex && $ex->num_rows > 0;
    if (!$conn->query($sql)) { exit("⛔ فشل إنشاء `$table`: " . $conn->error . "\n"); }
    if ($existed) { $had++; echo "  = `$table` موجودٌ سلفًا — لا تغيير\n"; }
    else { $made++; echo "  + `$table` أُنشئ\n"; }
}

printf("\n◆ INJ-INT-01 · السجلّان: أُنشئ %d · قائمٌ سلفًا %d · زمن %.2fs\n", $made, $had, microtime(true) - $t0);
echo "⛔ ولم يُمَسَّ جدولٌ قائمٌ ولا صفٌّ واحد — إضافةٌ محضة.\n";

/* ── قيدُ الدفتر — `G-MIG-01` يجعل الاستدعاءَ شرطًا لا عُرفًا ───────────────
   وهجرةٌ لا تستدعيه تبقى «على القرصِ خارجَ الدفتر» فتردُّ البوّابةُ كلَّ التزام. */
if (function_exists('ems_migration_recorded')) {
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
} else {
    echo "◆ شُغِّلت خارجَ المُشغِّل — قيِّدها بـ`php database/migrate.php mark-applied " . basename(__FILE__) . "`\n";
}
