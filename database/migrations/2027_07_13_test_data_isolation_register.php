<?php
/**
 * 2027_07_13_test_data_isolation_register.php — سجلُّ عزلِ الصفوفِ الملوَّثة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-19 · سادسًا) — خمسُ خطواتٍ لكلِّ قيمةٍ ملوَّثة، وفيها:
 *   «**فإن لم توجد يقينًا فلا تخمّن ولا تكتب NULL تلقائيًّا** — اعزلِ السجلَّ
 *   أو امنعْ أثرَه وفقَ قيدِ الحقل… احفظِ النصَّ الأصليَّ ومرجعَه».
 *
 * ◆ والقياسُ الحيُّ حسم شكلَ العلاج: الصفُّ المصابُ **مصطنَعٌ بكاملِه** لا
 *   صفٌّ سليمٌ فيه حقلٌ فاسد. مثالٌ مقيس (`fleet_model` id=2): `model_name`
 *   اسمُ شخصٍ، و`manufacturer` و`operating_category` و`fuel_type` و
 *   `std_capacity_uom` **تحمل الجملةَ نفسَها حرفًا**. فلا «قيمةٌ صحيحةٌ»
 *   تُبحث لـ`fuel_type` — إذ لا أصلَ له يُستعاد. وقِسْ عليه الصفَّ السليمَ
 *   (id=1): تايوتا · samba · تحميل · بنزين · لتر.
 *
 * ◆ ودليلٌ ثانٍ على سوءِ الموضع: القيمُ **مبتورةٌ في منتصفِ كلمتِها**
 *   («UAT-202» بدل «UAT-2026-0013») لأن عمودَ الرمزِ أضيقُ من الجملة.
 *
 * ◆ وأعمدةُ الإصابةِ **NOT NULL** فلا يُكتب فيها NULL — وهو عينُ ما اشترطه
 *   المالك: «NULL لا يُستعمل إلا حيث يُسمح به دلاليًّا وبنيويًّا».
 *
 * ◆ فهذه الهجرةُ تُنشئ السجلَّ **فارغًا**: يحفظ المفتاحَ والنصَّ الأصليَّ
 *   ومرجعَه وقيدَ الحقلِ ومجالَ السياسة — **وصفرُ تعديلٍ في بياناتِ العمل**.
 *   والصفُّ نفسُه لا يُمَسُّ حتى يُحسم المجال: فمنه ما يقع في **المجالاتِ
 *   الستةِ المحظورة** (`approval_workflow_rules` · `fin_approval_chain` ·
 *   `fin_obl_register`) وقاعدةُ المالكِ فيها صريحة: لا تطبيقَ بلا اعتراضٍ أبدًا.
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
  `original_value` TEXT NOT NULL COMMENT 'النصُّ الأصليُّ محفوظًا — لا يُفقد',
  `source_ref` VARCHAR(190) NOT NULL COMMENT 'مرجعُ النصِّ كما وُجد',
  `reason` VARCHAR(190) NOT NULL,
  `column_nullable` TINYINT(1) NOT NULL COMMENT 'أيقبل الحقلُ NULL؟ يحدّد العلاجَ الممكن',
  `policy_domain` ENUM('NAVIGATION_NAMING_POSITION','PERMISSIONS','APPROVAL_LADDERS',
        'FINANCIAL_CAPS','SEGREGATION_OF_DUTIES','LEGAL_OBLIGATIONS','FINANCIAL_DECISIONS',
        'OPERATIONAL_DATA') NOT NULL DEFAULT 'OPERATIONAL_DATA',
  `resolution` ENUM('PENDING_SOURCE','PENDING_OWNER','QUARANTINED','RESOLVED')
        NOT NULL DEFAULT 'PENDING_SOURCE',
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_row_col` (`table_name`, `pk_value`, `column_name`),
  KEY `ix_res` (`resolution`),
  KEY `ix_dom` (`policy_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عزلٌ بتسجيلٍ — النصُّ محفوظٌ وبياناتُ العملِ لم تُمَسّ'");
if (!$ok) { exit("✗ {$conn->error}\n"); }
echo "✔ gov_test_data_isolation أُنشئ فارغًا — والملءُ بجردٍ لا بتعديل\n";
