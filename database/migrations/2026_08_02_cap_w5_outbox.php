<?php
/**
 * update0005 · الموجة ⑤ · CAP-26 — جدولُ الصادر لمجال القدرات (DEC-CAP-B)
 * ───────────────────────────────────────────────────────────────────────────
 * «الصادرُ لمجال القدرات وحدَه — ولا يُمسّ EffectFanout ولا ADR-15 ولا
 * ems_business_events. والغرضُ الوحيدُ إمرارُ C28»: كتابةُ صفٍّ داخل المعاملة
 * الذرية (§14-⑤) — والنشرُ بعد COMMIT بعاملٍ يعيد المحاولةَ تصاعديًّا،
 * والعطالةُ (idempotency_key) تضمن ألا تُستهلك الحصةُ ثانيةً عند الإعادة.
 * idempotent.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

$has = $conn->query("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'capacity_outbox'")->fetch_row()[0];
if (intval($has) > 0) { echo "  = capacity_outbox قائم\n"; exit(0); }

$ok = $conn->query("CREATE TABLE capacity_outbox (
    obx_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id       INT NOT NULL,
    event_key        VARCHAR(60) NOT NULL COMMENT 'أحد أحداث مجال القدرات الستة (§14)',
    entity_type      VARCHAR(40) NOT NULL,
    entity_id        INT NOT NULL,
    quantity         DECIMAL(18,3) NULL,
    unit             VARCHAR(16) NULL,
    payload_json     JSON NOT NULL,
    idempotency_key  VARCHAR(64) NOT NULL COMMENT 'مفتاحُ منع التكرار عبر الطبقات (CAP-30) — يمرّ إلى publishFact نفسِه',
    state            ENUM('pending','published','failed') NOT NULL DEFAULT 'pending',
    attempts         SMALLINT NOT NULL DEFAULT 0,
    next_attempt_at  DATETIME NULL COMMENT 'إعادةُ المحاولة التصاعدية — بساعة القاعدة',
    published_event_id INT NULL COMMENT 'ems_business_events.id بعد النشر',
    last_error       VARCHAR(255) NULL,
    created_by       INT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at     DATETIME NULL,
    PRIMARY KEY (obx_id),
    UNIQUE KEY uq_obx_idem (idempotency_key),
    KEY ix_obx_pending (company_id, state, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CAP-01 §14 · DEC-CAP-B — صادرُ مجال القدرات: صفٌّ داخل المعاملة والنشرُ بعد COMMIT؛ الفشلُ يُعاد تصاعديًّا بلا استهلاكٍ ثانٍ (C28)'");
if (!$ok) { fwrite(STDERR, "تعذر capacity_outbox: {$conn->error}\n"); exit(1); }
echo "  + capacity_outbox\n";
