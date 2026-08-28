<?php
/**
 * 2027_12_24_rpr03_event_dead_letter_rulings.php — أحكامُ الرسائلِ الميتةِ للأحداث
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يوجبه الأمر** — `RPR-03` §٦·٣ و§٩: *«وكلُّ رسالةٍ ميتةٍ تنتهي إلى حكم:
 *   `Replay` · `Compensate` · `Close with reason` ⛔ **ولا رسالةَ ميتةٌ بلا
 *   حكم**»* · و§١٢: *«لا إغلاقَ رسالةٍ ميتةٍ بلا سببٍ وقرار»*.
 *
 * ◆ **وسطحان لا سطحٌ واحد — وهذا ما كشفه القياس**:
 *   · `gov_dead_letter_rulings` **قائمٌ وفيه ١٧ حكمًا**، لكنّه مفتاحُه
 *     `job_id`/`job_type` ⇒ **يحكم على ميّتِ طابورِ المهامّ لا على ميّتِ
 *     الأحداث**.
 *   · `ems_business_events` فيها **٢٦ واقعةً `delivered_failed>0`** —
 *     **وصفرُ حكمٍ لأيٍّ منها**، ولا مفتاحَ لها في السجلِّ القائم.
 *   ⇒ فسجلٌّ ثالثٌ ليس تكرارًا: **مقامٌ آخرُ ومفتاحٌ آخر**.
 *
 * ◆ **وتناقضٌ يُسمّى**: `ems_business_events.in_dlq = 1` في الواقعةِ `13947`،
 *   **و`ems_event_dead_letter` فارغٌ تمامًا (صفرُ صفّ)** — فالرايةُ تقول «في
 *   صفِّ الميّت» والجدولُ يقول «لا أحدَ فيه». **قارئان يتفرّقان**، ⛔ ولا يُصدَّق
 *   أحدُهما بإسكاتِ الآخر.
 *
 * ◆ **وأثرُه على الحكم**: `ems_event_dead_letter.last_error` هو **الموضعُ
 *   الوحيدُ لسببِ الفشل**، وهو فارغ. ⇒ **لا يُعرَف لِمَ فشلت الستُّ والعشرون**،
 *   و`Replay` أو `Compensate` **حكمان يحتاجان السببَ**: الأوّلُ يفترض عطبًا
 *   عابرًا والثاني يفترض أثرًا وقع ناقصًا. ⛔ **فالحكمُ بلا سببٍ تخمين**.
 *
 * التشغيل: php database/migrations/2027_12_24_rpr03_event_dead_letter_rulings.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `rpr03_event_dead_letter_rulings` (
  `event_id`    BIGINT(20) UNSIGNED NOT NULL COMMENT 'ems_business_events.id',
  `event_key`   VARCHAR(120) NOT NULL DEFAULT '',
  `ruling`      ENUM('REPLAY','COMPENSATE','CLOSE_WITH_REASON','NEEDS_ADJUDICATION')
                NOT NULL DEFAULT 'NEEDS_ADJUDICATION'
                COMMENT 'RPR-03 §6-3 — ولا رسالة ميتة بلا حكم',
  `reason`      VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'السبب — ولا اغلاق بلا سبب وقرار',
  `evidence`    VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الدليل المقيس',
  `owner_role`  VARCHAR(64)  NOT NULL DEFAULT '',
  `snapshot_id` VARCHAR(48)  NOT NULL DEFAULT '',
  `ruled_at`    DATETIME     NULL,
  PRIMARY KEY (`event_id`),
  KEY `ix_edlr_ruling` (`ruling`),
  /* ⛔ **ولا إغلاقَ بلا سبب** — §١٢ نصًّا */
  CONSTRAINT `chk_edlr_close_reason`
    CHECK (`ruling` <> 'CLOSE_WITH_REASON' OR `reason` <> ''),
  /* ⛔ **ولا حكمَ منفَّذٌ بلا دليلٍ يسمّي قياسَه** */
  CONSTRAINT `chk_edlr_evidence`
    CHECK (`ruling` = 'NEEDS_ADJUDICATION' OR `evidence` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-03 §6-3 — احكام ميت الاحداث · ومقامه غير مقام gov_dead_letter_rulings'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "  ✔ `rpr03_event_dead_letter_rulings`\n";

$dead = (int) $conn->query("SELECT COUNT(*) FROM ems_business_events
                             WHERE delivered_failed > 0 OR in_dlq > 0")->fetch_row()[0];
$dlqTbl = (int) $conn->query("SELECT COUNT(*) FROM ems_event_dead_letter")->fetch_row()[0];
$flagged = (int) $conn->query("SELECT COUNT(*) FROM ems_business_events WHERE in_dlq > 0")->fetch_row()[0];
printf("\n  رسائلُ ميتةٌ في الصندوق: **%d** · والرايةُ `in_dlq` مرفوعةٌ في **%d**\n", $dead, $flagged);
printf("  و`ems_event_dead_letter` فيه **%d** صفًّا", $dlqTbl);
echo ($flagged > 0 && $dlqTbl === 0)
    ? " ⛔ **قارئان يتفرّقان — الرايةُ ترفع والجدولُ خالٍ**\n"
    : "\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الموضعُ مُهيَّأ — والحكمُ بـ`rpr03_dead_letters.php`\n";
