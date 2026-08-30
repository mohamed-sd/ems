<?php
/**
 * 2028_01_17_rpr02_s6_nav_perm_code.php — سدُّ ثقبِ الظهورِ بلا فحصِ صلاحية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §٦ الخطوةُ **س٦**: *«صحِّحِ الظهورَ بالصلاحيّةِ
 *   — ولا تُضحَّى الصلاحيّةُ لأجلِ ترتيبٍ أجمل»*. والمقيسُ **موضعان** على مسارٍ
 *   واحدٍ: `Portal/my_tasks.php` عند الدورَين **٢٨** و**٢٩** بـ`permission_code`
 *   **فارغ** — و`getUnifiedNavItems` تنصُّ: `permission_code IS NULL` ⇒ **ظهورٌ
 *   بلا فحص**. ⇒ **الدوران يريان البندَ بلا منحٍ ألبتّة**، بينما **٣٢** دورًا
 *   آخرَ على المسارِ نفسِه **مفحوصون** بـ`module_id = 246`.
 *
 * ⛔ **وهذا ثقبُ تجاوزٍ لا فرقُ تنسيق**: الصفُّ نفسُه في الجدولِ نفسِه يُقرأ
 *   بقاعدتَين — مرّةً بفحصٍ ومرّةً بلا فحص ([[counter-parity-two-readers]]).
 *
 * ◆ **وسدُّ الثقبِ فعلٌ أمنيٌّ · والمنحُ فعلُ مالك** — ⛔ **ولا يُلفَّق منح**:
 *   يُكتب `permission_code` فيصير الظهورُ مفحوصًا؛ فإن لم يكن للدورِ صفُّ
 *   `role_permissions` بـ`can_view = 1` **اختفى البندُ عنه** — وهو ما يقوله
 *   نموذجُ الصلاحيةِ المغلَقُ افتراضًا. **وأثرُ ذلك يُقاس ويُعلَن بعددِه**،
 *   ويبقى **منحُ الدورَين ٢٨ و٢٩** بندًا مسمًّى لقرارِ المالك.
 *
 * ◆ **وهذا الموضعُ نفسُه مصدرُ التراجع** · ⛔ **ولا تراجعَ بذاكرةٍ ولا بأداة.**
 *
 * التشغيل: php database/migrations/2028_01_17_rpr02_s6_nav_perm_code.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_nav_perm_bind` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `nav_item_id`    INT(11) NOT NULL,
  `role_id`        INT(11) NOT NULL,
  `route`          VARCHAR(190) NOT NULL,
  `module_id`      INT(11) NULL,
  `after_code`     VARCHAR(128) NOT NULL COMMENT 'رمز الصلاحية المكتوب — من اخوته على المسار نفسه',
  `peer_roles`     INT(11) NOT NULL DEFAULT 0 COMMENT 'ادوار على المسار نفسه مفحوصة سلفا — وهي السند',
  `had_grant`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = للدور can_view سلفا فلا يختفي شيء',
  `witness`        VARCHAR(600) NOT NULL,
  `snapshot_id`    VARCHAR(48) NOT NULL,
  `applied_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_npb_item` (`nav_item_id`),
  CONSTRAINT `chk_npb_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_npb_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_npb_code`     CHECK (`after_code` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 6 س6 · سد ثقب الظهور بلا فحص صلاحية — وهو مصدر التراجع'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_nav_perm_bind")->fetch_row()[0];
echo "  ✔ `repair01_nav_perm_bind` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ⛔ **وسدُّ الثقبِ فعلٌ أمنيٌّ والمنحُ فعلُ مالك** — ولا يُلفَّق منح\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الموضعُ مفتوحٌ — و`nav_items` لم تُمَسّ\n";
