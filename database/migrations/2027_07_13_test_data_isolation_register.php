<?php
/**
 * 2027_07_13_test_data_isolation_register.php — سجلُّ عزلِ الصفوفِ الملوَّثة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-19 · سادسًا) — خمسُ خطواتٍ لكلِّ قيمةٍ في حقلِ رمزٍ
 *   أو تعداد: ① أثبتْ أنها في غيرِ موضعِها ② حدّدِ القيمةَ الصحيحةَ من مصدرٍ
 *   حاكم ③ **فإن لم توجد يقينًا فلا تخمّن ولا تكتب NULL تلقائيًّا** — اعزلِ
 *   السجلَّ أو امنعْ أثرَه **وفقَ قيدِ الحقل** ④ احفظِ النصَّ الأصليَّ ومرجعَه
 *   ⑤ ثم قيدٌ يمنع عودتَه.
 *
 * ◆ والحالُ المقيسُ يمنع القفزَ إلى ⑤ الآن:
 *   ① الأعمدةُ المصابةُ **NOT NULL** — فلا يُكتب فيها NULL أصلًا.
 *   ② ولا مصدرَ حاكمًا يقول ما القيمةُ الصحيحةُ لـ`entity_type` انطلاقًا من
 *      جملةٍ عربيةٍ لا تدلُّ عليها.
 *   ③ وجُرِّب القيدُ المانعُ فعلًا على `deduction_types` فرفضته القاعدةُ
 *      **لأن الصفوفَ الملوَّثةَ قائمة** — فالترتيبُ: عزلٌ ثم قيد، لا العكس.
 *
 * ◆ فهذا الجدولُ يُنجز ① و④ بلا مساسٍ ببياناتِ العمل: يُعدِّد الصفَّ بمفتاحِه
 *   ويحفظ نصَّه الأصليَّ ومرجعَه ومجالَ سياستِه، فيصير المتبقّي **مقيسًا
 *   ومحصورًا** بدل أن يكون تقديرًا.
 *
 * ◆ و`policy_domain` يُشتقُّ من اسمِ الجدولِ لا من إقرارِ الكاتب — فما وقع في
 *   المجالاتِ الستةِ المحظورةِ يُسجَّل ولا يُمَسُّ بلا قرارٍ صريحٍ من المالك.
 *
 * ◆ والتسجيلُ في `TenantRegistry` كـ`T_GLOBAL`: السجلُّ حوكميٌّ عابرٌ للشركات —
 *   والتلوثُ يقع في جداولٍ عالميةٍ ومستأجَرةٍ معًا، فحصرُه بشركةٍ يُخفي نصفَه.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_test_data_isolation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(64) NOT NULL,
  `pk_column` VARCHAR(64) NOT NULL,
  `pk_value` VARCHAR(64) NOT NULL,
  `column_name` VARCHAR(64) NOT NULL,
  `original_value` TEXT NOT NULL
      COMMENT 'النصُّ الأصليُّ محفوظًا حرفًا — قاعدةُ صفرِ فقدٍ تسري هنا أيضًا',
  `source_ref` VARCHAR(64) NOT NULL DEFAULT 'UNMARKED'
      COMMENT 'مرجعُ النصِّ مقروءًا من النصِّ نفسِه — لا يُخترع',
  `reason` VARCHAR(190) NOT NULL,
  `column_nullable` TINYINT(1) NOT NULL
      COMMENT 'أيقبل الحقلُ NULL؟ يحدّد أيَّ علاجٍ ممكنٌ بنيويًّا',
  `policy_domain` ENUM(
      'OPERATIONAL_DATA','PERMISSIONS','APPROVAL_LADDERS','FINANCIAL_CAPS',
      'SEGREGATION_OF_DUTIES','LEGAL_OBLIGATIONS','FINANCIAL_DECISIONS'
  ) NOT NULL DEFAULT 'OPERATIONAL_DATA'
      COMMENT 'ما وقع في الستةِ المحظورةِ لا يُمَسُّ بلا قرارٍ صريح',
  `resolution` ENUM('PENDING_SOURCE','PENDING_OWNER','RESOLVED')
      NOT NULL DEFAULT 'PENDING_SOURCE',
  `resolved_value` VARCHAR(190) DEFAULT NULL
      COMMENT 'لا تُملأ إلا من مصدرٍ حاكمٍ — والتخمينُ ممنوع',
  `resolved_source` VARCHAR(190) DEFAULT NULL
      COMMENT 'المصدرُ الحاكمُ الذي أعطى القيمة — بلا مصدرٍ لا حسم',
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_row_col` (`table_name`, `pk_value`, `column_name`),
  KEY `ix_res` (`resolution`),
  KEY `ix_domain` (`policy_domain`),
  /* ◆ **قيدٌ لا قاعدةٌ مكتوبة**: لا يُعلَن صفٌّ محسومًا بلا قيمةٍ **ومصدرٍ**
       معًا — فالحسمُ بلا مصدرٍ تخمينٌ مسجَّلٌ باسمِ الحقيقة. */
  CONSTRAINT `chk_resolved_needs_source` CHECK (
      `resolution` <> 'RESOLVED'
      OR (`resolved_value` IS NOT NULL AND `resolved_source` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عزلُ صفوفٍ ملوَّثةٍ بتسجيلٍ — النصُّ محفوظٌ والبياناتُ لم تُمَسّ'");

if (!$ok) { exit("✗ {$conn->error}\n"); }

/* ═══════════════════════════════════════════════════════════════════════════
 * إكمالُ الناقصِ في جدولٍ قائم — و`IF NOT EXISTS` لا يفعل ذلك
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ كشفه تشغيلٌ حيٌّ: الجدولُ كان قائمًا سلفًا بـ991 صفًّا، فمرَّ الأمرُ
 *   **ناجحًا وصامتًا** ولم يُضَف القيدُ ولا فرادةُ المفتاح. ولو اكتُفي بمُرجَعِ
 *   `query()` لأُعلن «مُنشأ» وهو ناقص — وذاك رقمٌ بلا مصدر.
 * ◆ فتُفحص البنيةُ **كما هي في القاعدة** ويُكمَل ما نقص:
 *   ① فرادةُ (جدول، مفتاح، عمود) — وإلا سُجِّل الصفُّ الواحدُ مرارًا.
 *   ② قيدُ «لا حسمَ بلا مصدر».
 * ═══════════════════════════════════════════════════════════════════════════ */
$fixes = array();

/* ① الفرادة — تُفحص من الفهارسِ لا من نيّةِ الأمر */
$r = $conn->query("SELECT NON_UNIQUE FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_test_data_isolation'
                      AND INDEX_NAME='uq_row_col' LIMIT 1");
$idx = $r ? $r->fetch_assoc() : null;
if ($idx && (int) $idx['NON_UNIQUE'] === 1) {
    $dups = (int) $conn->query("SELECT COUNT(*) c FROM (
                SELECT 1 FROM gov_test_data_isolation
                 GROUP BY table_name, pk_value, column_name HAVING COUNT(*) > 1) d")->fetch_assoc()['c'];
    if ($dups === 0) {
        if ($conn->query("ALTER TABLE gov_test_data_isolation DROP INDEX `uq_row_col`,
                           ADD UNIQUE KEY `uq_row_col` (`table_name`,`pk_value`,`column_name`)")) {
            $fixes[] = 'الفرادةُ أُضيفت (كان فهرسًا عاديًّا)';
        } else { $fixes[] = 'الفرادةُ رُفضت: ' . $conn->error; }
    } else {
        /* لا يُحذف صفٌّ لتمريرِ قيد — يُعلَن العددُ ويُترك القرارُ للمالك */
        $fixes[] = "الفرادةُ مؤجَّلة: {$dups} مجموعةً مكرَّرةً — ولا يُحذف صفٌّ لتمريرِ قيد";
    }
}

/* ② لا حسمَ بلا مصدرٍ — قيدًا لا قاعدةً مكتوبة */
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='gov_test_data_isolation'
                      AND CONSTRAINT_NAME='chk_resolved_needs_source'");
if (!$r || (int) $r->fetch_assoc()['c'] === 0) {
    /* والعمودانِ قد لا يكونانِ في الجدولِ القائم */
    foreach (array('resolved_value', 'resolved_source') as $needCol) {
        $q = $conn->query("SELECT 1 FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_test_data_isolation'
                              AND COLUMN_NAME='{$needCol}'");
        if (!$q || $q->num_rows === 0) {
            $cmt = ($needCol === 'resolved_value')
                 ? 'لا تُملأ إلا من مصدرٍ حاكمٍ — والتخمينُ ممنوع'
                 : 'المصدرُ الحاكمُ الذي أعطى القيمة — بلا مصدرٍ لا حسم';
            $conn->query("ALTER TABLE gov_test_data_isolation
                          ADD `{$needCol}` VARCHAR(190) DEFAULT NULL COMMENT '{$cmt}'");
            $fixes[] = "العمودُ `{$needCol}` أُضيف";
        }
    }
    if ($conn->query("ALTER TABLE gov_test_data_isolation
        ADD CONSTRAINT `chk_resolved_needs_source` CHECK (
            `resolution` <> 'RESOLVED'
            OR (`resolved_value` IS NOT NULL AND `resolved_source` IS NOT NULL))")) {
        $fixes[] = 'قيدُ «لا حسمَ بلا مصدر» أُضيف';
    } else { $fixes[] = 'القيدُ رُفض: ' . $conn->error; }
}

echo "════ سجلُّ عزلِ الصفوفِ الملوَّثة ════\n";
foreach ($fixes as $f) { echo "  · {$f}\n"; }
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_test_data_isolation'
                      AND CONSTRAINT_TYPE='CHECK'");
$chk = $r ? (int) $r->fetch_assoc()['c'] : 0;
echo "  الجدولُ مُنشأ · قيودُ CHECK: {$chk}\n";
echo "  ▸ الحسمُ بلا مصدرٍ مرفوضٌ **بقيدٍ** لا بقاعدةٍ مكتوبة\n";
echo "✔ العزلُ يسبق القيدَ — لأن القيدَ يرفض جدولًا فيه صفوفٌ ملوَّثةٌ قائمة\n";
