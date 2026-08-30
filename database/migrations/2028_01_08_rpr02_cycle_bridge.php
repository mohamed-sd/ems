<?php
/**
 * 2028_01_08_rpr02_cycle_bridge.php — جسرُ دورةِ العملِ إلى معرِّفِ الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` **#٧** و§٧ الخطوة ١٣ تشترطان الوصلَ
 *   **بمعرِّفِ الشاشةِ صراحةً لا بالمسارِ ولا بالاسم**. و`gov_screen_cycle`
 *   تصل بـ`screen_file` — **وهو اسمُ ملفٍّ مجرَّدٌ لا مسارٌ كامل**: `index.php`
 *   يطابق **ثلاثةَ** أسطحٍ حيّة، و`modules.php` و`roles.php` و`employees.php`
 *   و`timesheet.php` كلٌّ منها **سطحان**. ⇒ **فالوصلُ ملتبسٌ بطبعِه**.
 *
 * ◆ **والمقيسُ يحدُّ العطبَ ولا يهوّله**: من **٥١٧** اسمَ ملفٍّ في الجدول
 *   **٣٤٨** يُحلُّ إلى سطحٍ حيٍّ **واحدٍ لا غير**، و**٧** إلى أكثرَ من سطح،
 *   و**١٦٢** لا سطحَ حيًّا له. ⇒ **فالجسرُ ممكنٌ لأكثرِه، والملتبسُ يُعلَن**.
 *
 * ⛔ **ولا يُحسم الملتبسُ بأوّلِ مصادفة** — عمودُ `screen_id` يبقى فارغًا
 *   و`bridge_rule` يقول `AMBIGUOUS_DECLARED` بعددِ مرشَّحيه. **فاختيارُ أحدِ
 *   ثلاثةِ `index.php` يربط مرحلةَ دورةٍ بشاشةٍ ليست هي.**
 *
 * ◆ **وقاعدةٌ صلبةٌ تُسنُّ والعمودُ فارغ**: `BASENAME_UNIQUE` **يوجب**
 *   `screen_id` غيرَ فارغ، وما عداه **يوجبه فارغًا** — فلا يتسلّل معرِّفٌ
 *   إلى صفٍّ حكمُه أنّه ملتبس.
 *
 * التشغيل: php database/migrations/2028_01_08_rpr02_cycle_bridge.php
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

$r = $conn->query("SHOW COLUMNS FROM `gov_screen_cycle` LIKE 'screen_id'");
if (!$r || !$r->num_rows) {
    $ok = $conn->query("ALTER TABLE `gov_screen_cycle`
        ADD COLUMN `screen_id` VARCHAR(12) NOT NULL DEFAULT ''
            COMMENT 'معرف الشاشة — الوصل بالمعرف لا بالاسم (7-13)',
        ADD COLUMN `bridge_rule` ENUM('','BASENAME_UNIQUE','AMBIGUOUS_DECLARED','NO_LIVE_SURFACE')
            NOT NULL DEFAULT '' COMMENT 'قاعدة الحل — والمقياس يعلن ايتها',
        ADD COLUMN `bridge_witness` VARCHAR(400) NOT NULL DEFAULT ''
            COMMENT 'شاهد الحل او شاهد تعذره',
        ADD COLUMN `bridge_snapshot` VARCHAR(48) NOT NULL DEFAULT '',
        ADD KEY `ix_cyc_screen` (`screen_id`),
        ADD KEY `ix_cyc_rule` (`bridge_rule`)");
    if (!$ok) { exit("✘ تعذّر إضافةُ الأعمدة: {$conn->error}\n"); }
    echo "  ✔ أُضيفت `screen_id` و`bridge_rule` و`bridge_witness` و`bridge_snapshot`\n";
} else {
    echo "  ◆ الأعمدةُ قائمةٌ سلفًا — ولا يُعاد إنشاؤها\n";
}

$r = $conn->query("SHOW CREATE TABLE `gov_screen_cycle`");
$ddl = $r ? $r->fetch_row()[1] : '';
if (strpos($ddl, 'chk_cyc_bridge') === false) {
    $ok = $conn->query("ALTER TABLE `gov_screen_cycle`
        ADD CONSTRAINT `chk_cyc_bridge` CHECK (
            (`bridge_rule` = 'BASENAME_UNIQUE' AND `screen_id` <> '')
         OR (`bridge_rule` <> 'BASENAME_UNIQUE' AND `screen_id` = ''))");
    echo $ok ? "  ✔ سُنَّت `chk_cyc_bridge`: **المعرِّفُ للمحلولِ وحدَه**\n"
             : "  ⚠ تعذّر سنُّ القاعدة: {$conn->error}\n";
} else {
    echo "  ◆ `chk_cyc_bridge` قائمةٌ سلفًا\n";
}

$n = (int) $conn->query("SELECT COUNT(*) FROM gov_screen_cycle")->fetch_row()[0];
echo "  ◆ صفوفُ دورةِ العمل: $n — **ولم يُملأ معرِّفٌ واحد**، يملؤها `rpr02_cycle_bridge.php --apply`\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الجسرِ مفتوحٌ — و`screen_file` لم يُمَسّ (‏مصدرٌ حاكمٌ يبقى كما ورد)\n";
