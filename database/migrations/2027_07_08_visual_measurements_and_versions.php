<?php
/**
 * 2027_07_08_visual_measurements_and_versions.php — قياسُ المتصفحِ وإصدارُ المكوّن
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-18 · ثالثًا):
 *   البند ٢: «G19 بقياسِ متصفحٍ حقيقيٍّ (getBoundingClientRect) على الدقتَين».
 *   البند ٧: «خطُّ أساسٍ بصريٌّ **مربوطٌ برقمِ إصدارِ المكوّن**».
 *   ورابعًا: «كلُّ إصدارِ مكوّنٍ **غيرُ قابلٍ للتعديلِ بعد ترقيتِه** — وأيُّ تغييرٍ
 *   جوهريٍّ ينشئ إصدارًا جديدًا يلزمه إعادةُ تحقق، ولا يُعدَّل المعتمَدُ في صمت».
 *
 * ◆ فالقياسُ لا يُعلَن رقمًا في تقريرٍ بل يُسجَّل صفًّا: الشاشةُ والدقةُ والقيمةُ
 *   ووقتُها **وبصمةُ إصدارِ المكوّناتِ وقتَها**. فإن تغيّر المكوّنُ بطَل القياسُ
 *   تلقائيًّا ولزمت إعادتُه — ولا يُقرأ رقمٌ قديمٌ على بناءٍ جديد.
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

/* ① سجلُّ إصداراتِ المكوّنات — المرقَّى لا يُعدَّل */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_component_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version_tag` VARCHAR(24) NOT NULL COMMENT 'مثال: ux-1.0.0',
  `fingerprint` CHAR(64) NOT NULL COMMENT 'sha256 لملفاتِ المكتبةِ مجتمعة',
  `files_json` TEXT NOT NULL COMMENT 'الملفاتُ الداخلةُ في البصمةِ وبصمةُ كلٍّ',
  `state` ENUM('DRAFT','PROMOTED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT'
      COMMENT 'PROMOTED غيرُ قابلٍ للتعديل — التغييرُ ينشئ إصدارًا جديدًا',
  `promoted_at` DATETIME NULL,
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tag` (`version_tag`),
  UNIQUE KEY `uq_fp` (`fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='إصداراتُ مكتبةِ المكوّنات — المرقَّى ثابتٌ ولا يُعدَّل في صمت'");

/* ② سجلُّ قياساتِ المتصفحِ الحقيقيّ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_visual_measurements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `screen_file` VARCHAR(160) NOT NULL,
  `viewport_w` SMALLINT UNSIGNED NOT NULL,
  `viewport_h` SMALLINT UNSIGNED NOT NULL,
  `header_px` SMALLINT UNSIGNED NULL COMMENT 'getBoundingClientRect — لا تقديرَ بنيويّ',
  `header_within_limit` TINYINT(1) NULL,
  `has_h_scroll` TINYINT(1) NULL COMMENT 'تمريرٌ أفقيٌّ للصفحة — والجدولُ مستثنًى',
  `primary_buttons` SMALLINT UNSIGNED NULL,
  `stacked_toolbars` SMALLINT UNSIGNED NULL COMMENT 'أشرطةٌ متراكبةٌ فوقَ جدولٍ واحد',
  `worst_cell_actions` SMALLINT UNSIGNED NULL,
  `row_height_px` SMALLINT UNSIGNED NULL,
  `component_version` VARCHAR(24) NULL COMMENT 'الإصدارُ وقتَ القياس — يبطل بتغيُّرِه',
  `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `measured_by` VARCHAR(60) NOT NULL DEFAULT 'browser-probe',
  PRIMARY KEY (`id`),
  KEY `ix_screen_vp` (`screen_file`, `viewport_w`, `measured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='قياساتُ محرّكِ العرضِ الحقيقيّ — كلُّ رقمٍ بدقتِه ووقتِه وإصدارِ مكوّنِه'");

/* ③ بصمةُ الإصدارِ الحاليِّ من ملفاتِ المكتبةِ فعلًا */
$FILES = array(
    'assets/css/uxui-tokens.css',
    'assets/css/uxui-components.css',
    'includes/uxui_components.php',
    'includes/status_display.php',
);
$parts = array(); $missing = array();
foreach ($FILES as $f) {
    $p2 = $ROOT . '/' . $f;
    if (!is_file($p2)) { $missing[] = $f; continue; }
    $parts[$f] = hash_file('sha256', $p2);
}
if ($missing) { exit("✗ ملفاتُ مكتبةٍ غائبة: " . implode(' · ', $missing) . "\n"); }
ksort($parts);
$fp = hash('sha256', implode('|', array_map(function ($k, $v) { return $k . ':' . $v; }, array_keys($parts), $parts)));
$json = json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$exists = $conn->query("SELECT version_tag, state FROM gov_component_versions WHERE fingerprint = '" . $conn->real_escape_string($fp) . "'");
if ($exists && $exists->num_rows > 0) {
    $x = $exists->fetch_assoc();
    echo "الإصدارُ الحاليُّ مسجَّلٌ سلفًا: {$x['version_tag']} ({$x['state']})\n";
} else {
    $n = (int) $conn->query("SELECT COUNT(*) c FROM gov_component_versions")->fetch_assoc()['c'];
    $tag = 'ux-1.' . $n . '.0';
    $note = 'مكتبةُ مكوّناتِ UXUI-01 — الشريطُ الموحَّدُ فوقَ الجدولِ (G20) والرقاقةُ للمنظرِ النشط';
    $st = $conn->prepare("INSERT INTO gov_component_versions (version_tag, fingerprint, files_json, state, note)
                          VALUES (?,?,?,'DRAFT',?)");
    $st->bind_param('ssss', $tag, $fp, $json, $note);
    $st->execute();
    echo "إصدارٌ جديدٌ مسجَّل: {$tag} (DRAFT — يُرقَّى بعدَ مرورِ البوابة)\n";
}

$cur = $conn->query("SELECT version_tag, state, LEFT(fingerprint,12) fp FROM gov_component_versions ORDER BY id DESC LIMIT 1")->fetch_assoc();
echo "الإصدارُ العامل: {$cur['version_tag']} · {$cur['state']} · بصمة {$cur['fp']}…\n";
echo "ملفاتُ البصمة: " . count($parts) . "\n";
echo "✔ سجلُّ القياسِ وسجلُّ الإصداراتِ جاهزان — والقياسُ يبطل تلقائيًّا بتغيُّرِ المكوّن\n";
