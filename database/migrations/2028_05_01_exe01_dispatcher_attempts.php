<?php
/**
 * 2028_05_01_exe01_dispatcher_attempts.php — عدّادُ محاولاتٍ خاصٌّ بالجيلِ الأول (EXE-01 §3)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الجذرُ ليس «كاتبَين للرسائلِ الميتة» بل جدولًا واحدًا لغرضَين متنافيَين.**
 *   للنظامِ ناقلانِ من جيلَين يقرآن كونَين مختلفَين:
 *     ① `EventDispatcher`      يقرأ `fin_financial_events` — والصفُّ عنده **عدّادٌ عابر** يُحذَف عند النجاحِ وعند الموت
 *     ② `EventDeliveryWorker`  يقرأ `ems_event_outbox`      — والصفُّ عنده **دفترُ حالاتٍ دائم**
 *   وكلاهما يتتبَّع في `ems_event_deliveries`. فالحذفُ الذي يبدو عطبًا **هو
 *   تصميمُ الأوّلِ الصحيح**، وبقاءُ الصفِّ هو تصميمُ الثاني الصحيح — والاثنانِ
 *   لا يجتمعان في جدولٍ واحد.
 *
 * ◆ **فيُفصَل التخزينُ ولا يُعدَّل السلوك**: عدّادٌ خاصٌّ للأوّل، فيتحرَّر
 *   `ems_event_deliveries` ليكون دفترَ الثاني وحدَه. ⇐ يُبلَغ المعياران:
 *     `DLQ_DELIVERY_DELETED_ON_FAILURE = 0` · `DLQ_STATE_SOURCE_COUNT = 1`
 *   **بلا مساسٍ بمستهلكي الماليّةِ والصرفِ والتوجيه** — ومؤشراتُهم عند الأقصى
 *   (‏17985) أي لاحقةٌ عاملة، فتقاعدُها كان سيُعطّل عاملًا لا ميتًا.
 *
 * ⛔ **والقيدُ الفريدُ يُنقَل كما هو**: `(consumer, event_id)` — فعليه يقوم
 *   `ON DUPLICATE KEY UPDATE attempts+1`. ومن نقل الجدولَ بلا قيدِه حوَّل
 *   العدّادَ إلى إدراجٍ متكرِّرٍ صامت.
 *
 * ⛔ **ولا تُنقَل صفوفٌ قائمة**: الجدولُ القديمُ فيه **صفرُ صفٍّ** من هذا الجيلِ
 *   (كلُّها `outbox_id > 0`) — فلا بيانات تُهاجَر ولا شيءَ يُفقَد.
 *
 * التشغيل: php database/migrations/2028_05_01_exe01_dispatcher_attempts.php
 * العكس  : php database/migrations/2028_05_01_exe01_dispatcher_attempts_down.php
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

/* ═══ ⓪ حارس: لا يُفصَل التخزينُ وفي الدفترِ صفوفٌ من الجيلِ الأول ══════════
   فلو وُجد صفٌّ بلا `outbox_id` لكان للأوّلِ حالةٌ حيّةٌ تُفقَد بالفصل. */
$legacy = $conn->query('SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `outbox_id` = 0 OR `outbox_id` IS NULL');
$legacyN = $legacy ? (int) $legacy->fetch_row()[0] : -1;
if ($legacyN !== 0) {
    exit("⛔ في `ems_event_deliveries` صفوفٌ من الجيلِ الأول ($legacyN) — لا يُفصَل التخزينُ قبل حكمٍ فيها.\n");
}
echo "  ✔ حارسُ الهجرة: صفرُ صفٍّ من الجيلِ الأولِ في الدفتر\n";

$ddl = "CREATE TABLE IF NOT EXISTS `ems_dispatcher_attempts` (\n"
     . "  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
     . "  `consumer` VARCHAR(64) NOT NULL COMMENT 'مستهلكُ الجيلِ الأول — ems_event_consumers.consumer',\n"
     . "  `event_id` BIGINT UNSIGNED NOT NULL COMMENT 'fin_financial_events.id — لا ems_business_events',\n"
     . "  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,\n"
     . "  `last_error` VARCHAR(500) NULL,\n"
     . "  `next_retry_at` DATETIME NULL COMMENT 'التصاعدُ الزمنيُّ 2^attempts دقيقة · سقفُه 64',\n"
     . "  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
     . "  PRIMARY KEY (`id`),\n"
     /* ⛔ القيدُ الفريدُ **شرطُ عملِ العدّاد** — عليه يقوم ON DUPLICATE KEY */
     . "  UNIQUE KEY `uq_consumer_event` (`consumer`, `event_id`),\n"
     . "  KEY `ix_next_retry` (`next_retry_at`)\n"
     . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n"
     . "  COMMENT='EXE-01 §3 — عدّادُ محاولاتٍ عابرٌ للجيلِ الأول · يُحذَف صفُّه عند النجاحِ وعند العزل'";

$e = $conn->query("SHOW TABLES LIKE 'ems_dispatcher_attempts'");
$existed = $e && $e->num_rows > 0;
if (!$conn->query($ddl)) { exit('⛔ فشل الإنشاء: ' . $conn->error . "\n"); }
echo $existed ? "  = `ems_dispatcher_attempts` موجودٌ سلفًا\n" : "  + `ems_dispatcher_attempts` أُنشئ\n";

printf("\n◆ EXE-01 §3 · فصلُ التخزين: زمن %.2fs\n", microtime(true) - $t0);
echo "⛔ ولم يُمَسَّ `ems_event_deliveries` ولا صفٌّ فيه — إضافةٌ محضة.\n";
echo "◆ والخطوةُ التالية: تحويلُ `EventDispatcher` إلى الجدولِ الجديدِ بمفتاحِ ميزة.\n";

require_once __DIR__ . '/_ledger.php';
if (function_exists('ems_migration_recorded')) {
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
}
