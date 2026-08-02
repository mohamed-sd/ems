<?php
/**
 * update0005 · الموجة ④ · CAP-20/21 — بنيةُ الموافقات ومرقبُ الفجوة
 * ───────────────────────────────────────────────────────────────────────────
 * · substitute_coverages: موافقاتُ الدرجات (JSON مرجعيّ) · الأثرُ المحسوبُ قبل
 *   الإرسال (§6.1-⑤) · والموردُ المتعطلُ لقطةً عند التقديم.
 * · capacity_gap_watch: رصدُ الفجوة يوميًّا بالساعات (§10-②) بتصعيدٍ آليٍّ —
 *   صفٌّ مفتوحٌ واحدٌ لكل التزامٍ بعمودٍ مولَّدٍ فريد.
 * idempotent بالكامل.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');

function w4_exists($conn, $sql) { $r = $conn->query($sql); return $r && intval($r->fetch_row()[0]) > 0; }
function w4_col($conn, $t, $c) {
    return w4_exists($conn, "SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'");
}
function w4_run($conn, $guard, $ddl, $label) {
    if ($guard) { echo "  = {$label} قائم\n"; return; }
    if (!$conn->query($ddl)) { fwrite(STDERR, "تعذر {$label}: {$conn->error}\n"); exit(1); }
    echo "  + {$label}\n";
}

echo "── CAP-20 · substitute_coverages: الموافقاتُ والأثرُ المحسوب\n";
w4_run($conn, w4_col($conn, 'substitute_coverages', 'approvals_json'),
    "ALTER TABLE substitute_coverages ADD COLUMN approvals_json JSON NULL
       COMMENT 'CAP-01 §6: موافقاتُ الدرجة المجموعة — {role: {by, at}}؛ والاكتمالُ بحسب مصفوفة الدرجة' AFTER approvals_ref",
    'substitute_coverages.approvals_json');
w4_run($conn, w4_col($conn, 'substitute_coverages', 'impact_json'),
    "ALTER TABLE substitute_coverages ADD COLUMN impact_json JSON NULL
       COMMENT 'CAP-01 §6.1-⑤: الأثرُ على الأطراف الأربعة محسوبًا قبل الإرسال — يُعرض على الموافقين لا يُقدَّر بعد التنفيذ' AFTER approvals_json",
    'substitute_coverages.impact_json');
w4_run($conn, w4_col($conn, 'substitute_coverages', 'failed_supplier_id'),
    "ALTER TABLE substitute_coverages ADD COLUMN failed_supplier_id INT NULL
       COMMENT 'الموردُ المتعطل — لقطةٌ من شجرة المقعد عند التقديم' AFTER covering_supplier_id",
    'substitute_coverages.failed_supplier_id');

echo "── CAP-21 · capacity_gap_watch\n";
w4_run($conn,
    w4_exists($conn, "SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'capacity_gap_watch'"),
    "CREATE TABLE capacity_gap_watch (
        gap_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id       INT NOT NULL,
        obl_id           INT UNSIGNED NOT NULL COMMENT 'التزامُ نوع المعدة — contract_commitments.id',
        gap_units        SMALLINT NOT NULL COMMENT 'الوحداتُ غيرُ المغطاة',
        gap_hours        DECIMAL(14,2) NOT NULL COMMENT 'الفجوةُ بالساعات لا بالعدد فقط (§10-①/C13)',
        measure_code     ENUM('hour','ton','trip','meter') NOT NULL DEFAULT 'hour',
        opened_on        DATE NOT NULL COMMENT 'يومُ أول رصدٍ — بساعة القاعدة',
        last_seen_on     DATE NOT NULL COMMENT 'آخرُ يومٍ رُصدت فيه — المرقبُ يوميٌّ لا شهري',
        escalate_after_days SMALLINT NOT NULL DEFAULT 3 COMMENT 'مهلةُ المعالجة المعلنةُ قبل التصعيد',
        escalated_ops_at DATETIME NULL COMMENT 'تصعيدٌ آليٌّ لمدير التشغيل',
        escalated_gm_at  DATETIME NULL COMMENT 'ثم للإدارة العامة',
        closed_on        DATE NULL,
        state            ENUM('open','escalated_ops','escalated_gm','closed') NOT NULL DEFAULT 'open',
        open_key         VARCHAR(40) GENERATED ALWAYS AS (
                           IF(closed_on IS NULL, CONCAT(company_id, ':', obl_id), NULL)) STORED
                         COMMENT 'صفٌّ مفتوحٌ واحدٌ لكل التزام — فريدٌ مشروطٌ على عمودٍ مولَّد',
        created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (gap_id),
        UNIQUE KEY uq_gap_open (open_key),
        KEY ix_gap_state (company_id, state, last_seen_on)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='CAP-01 §10 — مرقبُ الفجوة اليومي بالساعات: الفجوةُ التي تُكتشف آخرَ الشهر خسارةٌ وقعت'",
    'capacity_gap_watch');

echo "تمت هجرة الموجة ④\n";
