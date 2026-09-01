<?php
/**
 * 2028_03_17_govui_dep08_field_sources.php — DEP-08 · مصادرُ حقولٍ لا نظيرَ لها
 * @migration-objects: table gov_dashboard_kpi · col gov_policy.review_periodicity
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ المطبَّقة** ([[iaf-field-closure]]): *«rename لا يُخترع، وما لا
 *   نظيرَ له يأخذ عمودًا»*. فبعدَ مصالحةِ حقولِ `DEP-08` بالورقةِ حقلًا حقلًا،
 *   **بقي مصدران لا نظيرَ لهما في المخزن**، وما عداهما رُدَّ إلى عمودٍ قائمٍ
 *   أو إلى اشتقاقٍ من صفوفٍ مقروءةٍ سلفًا.
 *
 * ① **`gov_dashboard_kpi`** — ورقةُ `GOV-01` تصف «لوحة الحوكمة والالتزام»
 *   بحبّةِ **«مؤشر × فترة — قراءة حية»** وستّةِ حقول: معرّفُ المؤشرِ والمؤشرُ
 *   والقيمةُ والوحدةُ والحالةُ وآخرُ تحديث. **والسطحُ كان يقرأ `gov_breach`**
 *   (سجلَّ الإخلالات) — فحبّتُه صفُّ إخلالٍ لا مؤشِّر، وهو خرقُ §11 الأوّل.
 *   ⛔ **ولا جدولَ جديدًا يُخترع نمطُه**: البنيةُ هي بنيةُ `*_dashboard_kpi`
 *   المستقرّةُ في `site_dashboard_kpi` و`wf_dashboard_kpi` حرفًا — لوحةٌ لكلِّ
 *   إدارةٍ بمؤشراتِها، **وهي إسقاطٌ لا مصدرُ حقيقة**.
 *
 * ② **`gov_policy.review_periodicity`** — الورقةُ تُلزم `GOV-03` بحقلِ
 *   «دورية المراجعة» **بحكمِ `BUSINESS_INPUT` («خانة إدخال مفتوحة»)**،
 *   و`gov_policy` تحمل `review_due` (‏**موعدَ** المراجعةِ القادم) ولا تحمل
 *   **دوريّتَها**. ⛔ **والاثنان ليسا واحدًا**: موعدٌ واحدٌ لا يُنتج دوريّةً،
 *   ودوريّةٌ بلا موعدٍ لا تُجدول. **والنظيرُ القائمُ في الشجرةِ نفسِها**
 *   `gov_obligation.periodicity` — فالنوعُ منه لا مُبتكَرًا.
 *
 * ⛔ **وما لم يُضَف عمدًا**: «آخر مراجعة ◄» و«قواعد المنع المستندة ◄»
 *   **مشتقّان بنصِّ الورقة** («قراءة محسوبة — لا إدخال») ⇒ يُحسبان في السطحِ
 *   من صفوفٍ مقروءةٍ (‏إصداراتُ السياسةِ نفسُها · `guard_policies.owner_doc`)،
 *   **وعمودٌ لمشتقٍّ يخلق مصدرَ حقيقةٍ ثانيًا**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* ① لوحةُ مؤشراتِ الحوكمة — بنيةُ `*_dashboard_kpi` نفسُها */
$sql = "CREATE TABLE IF NOT EXISTS `gov_dashboard_kpi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `kpi_id` VARCHAR(60) NULL DEFAULT NULL COMMENT 'معرف المؤشر',
    `kpi_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المؤشر - KPI Catalog',
    `value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القيمة',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    `state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة',
    `updated_on` DATETIME NULL DEFAULT NULL COMMENT 'اخر تحديث',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_govkpi_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT 'DEP-08 GOV-01 - لوحة الحوكمة والالتزام - الحبة: مؤشر واحد لفترة واحدة'";
if ($conn->query($sql)) { echo "gov_dashboard_kpi: جاهز\n"; }
else { echo "تعذّر إنشاء gov_dashboard_kpi: " . $conn->error . "\n"; }

/* ② دوريّةُ مراجعةِ السياسة — والنوعُ من نظيرِه `gov_obligation.periodicity` */
$q = $conn->query("SHOW COLUMNS FROM `gov_policy` LIKE 'review_periodicity'");
if ($q && $q->num_rows) { echo "gov_policy.review_periodicity: قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `gov_policy`
        ADD COLUMN `review_periodicity` VARCHAR(24) NULL DEFAULT NULL
        COMMENT 'دورية المراجعة - خانة ادخال مفتوحة بنص الورقة' AFTER `review_due`")) {
    echo "gov_policy.review_periodicity: أُضيف\n";
} else { echo "تعذّرت الإضافة: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
