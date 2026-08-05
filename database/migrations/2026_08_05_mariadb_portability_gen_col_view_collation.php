<?php
/**
 * قابليةُ النقل إلى MariaDB — عائقا استيراد المخطّط على استضافةٍ مشتركة
 * ───────────────────────────────────────────────────────────────────────────
 * ① contract_commitments.obl_type_uq_key: التعبيرُ المولَّد كان يستعمل
 *    DATE_FORMAT() — وMariaDB تحظرها في GENERATED ALWAYS AS (#1901) لأن
 *    ناتجَها رهينُ lc_time_names. البديلُ CAST(valid_from AS CHAR) يعطي
 *    'YYYY-MM-DD' ذاتَها لعمود DATE (تكافؤٌ مفحوصٌ على البيانات قبل الاستبدال)
 *    والمفتاحُ الفريد uq_obl_type_from يُعاد على العمود الجديد كما كان.
 * ② المناظيرُ الأربعة (v_org_unit_heads · v_worker_billable_hours ·
 *    v_worker_presence · v_worker_worklog) مخزّنةٌ بترتيب اتصالٍ
 *    utf8mb4_0900_ai_ci — ترتيبُ MySQL 8 الذي لا تعرفه MariaDB، والمُصدِّر
 *    «نسخٌ أمين» يبثّه كما هو فينفجر الاستيراد. تُعاد جميعُ المناظير المخالفة
 *    تحت جلسة utf8mb4_unicode_ci فيتوحّد المخزونُ مع ترتيب القاعدة.
 * idempotent بالكامل — الفحص عبر information_schema قبل كل DDL.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
// set_charset وحدَه يترك الترتيبَ لافتراض الخادم (0900 في MySQL 8) — والمناظيرُ
// المُعادةُ أدناه ترث ترتيبَ الجلسة، فيُثبَّت صراحةً.
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

function idx_exists($conn, $table, $idx) {
    $r = $conn->query("SELECT COUNT(*) n FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = '{$table}' AND index_name = '{$idx}'");
    return $r && intval($r->fetch_assoc()['n']) > 0;
}
function add_ddl($conn, $guard, $ddl, $label) {
    if ($guard) { echo "  = {$label} قائم\n"; return; }
    if (!$conn->query($ddl)) { fwrite(STDERR, "تعذر {$label}: {$conn->error}\n"); exit(1); }
    echo "  + {$label}\n";
}

// ═══ ① obl_type_uq_key — التعبيرُ بلا DATE_FORMAT ═══
echo "── ① contract_commitments.obl_type_uq_key\n";
$r = $conn->query("SELECT GENERATION_EXPRESSION expr FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'contract_commitments'
      AND column_name = 'obl_type_uq_key'");
$row = $r ? $r->fetch_assoc() : null;
if ($row === null) {
    fwrite(STDERR, "obl_type_uq_key غائبٌ أصلًا — شغّل هجرة 2026_08_02 أولًا\n"); exit(1);
}
if (stripos($row['expr'], 'date_format') === false) {
    echo "  = التعبيرُ نظيفٌ سلفًا\n";
} else {
    // تكافؤُ التعبيرين على البيانات الحية — شرطُ الاستبدال لا افتراضُه
    $r = $conn->query("SELECT COUNT(*) n FROM contract_commitments
        WHERE NOT (IFNULL(DATE_FORMAT(valid_from,'%Y-%m-%d'),'open') <=> IFNULL(CAST(valid_from AS CHAR),'open'))");
    $n = $r ? intval($r->fetch_assoc()['n']) : -1;
    if ($n !== 0) { fwrite(STDERR, "التعبيران غيرُ متكافئين على {$n} صفًّا — لا استبدال\n"); exit(1); }
    add_ddl($conn, !idx_exists($conn, 'contract_commitments', 'uq_obl_type_from'),
        "ALTER TABLE contract_commitments DROP INDEX uq_obl_type_from",
        'إسقاط uq_obl_type_from مؤقتًا');
    add_ddl($conn, false,
        "ALTER TABLE contract_commitments DROP COLUMN obl_type_uq_key",
        'إسقاط العمود المولَّد القديم');
    add_ddl($conn, false,
        "ALTER TABLE contract_commitments ADD COLUMN obl_type_uq_key VARCHAR(130) GENERATED ALWAYS AS (
            IF(equipment_type_code IS NOT NULL AND is_deleted = 0,
               CONCAT(company_id, ':', contract_ref, ':', equipment_type_code, ':', IFNULL(CAST(valid_from AS CHAR), 'open')),
               NULL)) STORED
         COMMENT 'CAP-01: فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد — UQ(contract, equipment_type_code, valid_from) للأحياء ذوي النوع (DEC-CAP-C) · CAST لا DATE_FORMAT لقابلية MariaDB'",
        'العمودُ المولَّد بتعبير CAST');
    add_ddl($conn, false,
        "ALTER TABLE contract_commitments ADD UNIQUE KEY uq_obl_type_from (obl_type_uq_key)",
        'إعادة uq_obl_type_from');
}

// ═══ ② المناظيرُ المخالفة — إعادةٌ تحت utf8mb4_unicode_ci ═══
echo "── ② ترتيبُ اتصال المناظير\n";
$r = $conn->query("SELECT TABLE_NAME v FROM information_schema.views
    WHERE table_schema = DATABASE() AND collation_connection <> 'utf8mb4_unicode_ci'
    ORDER BY TABLE_NAME");
$bad = array();
while ($x = $r->fetch_assoc()) { $bad[] = $x['v']; }
if (!$bad) { echo "  = المناظيرُ كلُّها موحَّدةٌ سلفًا\n"; }
foreach ($bad as $v) {
    $r = $conn->query("SHOW CREATE VIEW `{$v}`");
    $create = $r ? $r->fetch_assoc()['Create View'] : null;
    if ($create === null) { fwrite(STDERR, "تعذرت قراءةُ {$v}: {$conn->error}\n"); exit(1); }
    // DEFINER يُنزع — المرحِّلُ لا يملك انتحالَ غيرِه، والمُصدِّرُ ينزعه أصلًا
    $create = preg_replace('/\sDEFINER=`[^`]+`@`[^`]+`/', '', $create, 1);
    $create = preg_replace('/^CREATE\s/', 'CREATE OR REPLACE ', $create, 1);
    add_ddl($conn, false, $create, "إعادةُ {$v} بترتيب unicode_ci");
}

echo "اكتمل — شغّل dump-schema ليتجدد المخطّط\n";
