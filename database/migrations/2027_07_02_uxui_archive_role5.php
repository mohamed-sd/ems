<?php
/**
 * 2027_07_02_uxui_archive_role5.php — أرشفةُ تعريفِ التنقلِ القديم (الدور 5)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ف١٥-٥: «لا يجوز بقاءُ القديمِ والجديدِ معًا في مصدرٍ تنفيذيّ: تُطابَق
 *   الروابطُ الثمانيةَ عشرَ واحدًا واحدًا مع الجديد، ثم يُؤرشَف القديمُ
 *   ويُعطَّل — بعدَ إثباتِ التطابقِ لا قبلَه».
 * ◆ الإثباتُ سُلِّم وملتزمٌ: docs/UXUI_OLD18_MATCH_ar.md (التزام 382a0b7) —
 *   18/18 لها صفوفٌ معيارية · 16/18 في الدورِ 6 الجديد · والاثنان الباقيان
 *   (Operations/containers.php · Projects/sites.php) حيّانِ في أدوارٍ أخرى
 *   (1·2·3·12 و1·3) — فصفرُ فقدٍ بالأرشفة.
 * ◆ ولأن القاعدةَ «لا حذفَ مدمِّر»: **حجرٌ ثلاثين يومًا ثم حذف**. فالصفوفُ
 *   تُنسخ كما هي إلى nav_items_archive_role5 بتاريخِ الحجرِ وموعدِ الحذف،
 *   ثم تُعطَّل في الحيِّ (active=0) — ولا صفَّ يُحذف اليومَ إطلاقًا.
 *   والاستعادةُ ممكنةٌ بأمرٍ واحدٍ ما دامت المهلةُ قائمة.
 * ◆ ودورُ الاختبارِ 5 نفسُه لا يُمَسّ: تعطيلُ روابطِه لا يحذف الدورَ ولا
 *   حساباتِه — فمن كان عليه ينتقل إلى الدورِ 6 بقرارِ إداريٍّ لا ببرمجة.
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

/* ── حارسُ الإثبات: لا أرشفةَ بلا ملفِّ التطابقِ الملتزم ── */
$proof = $ROOT . '/docs/UXUI_OLD18_MATCH_ar.md';
if (!is_file($proof)) { exit("✗ لا إثباتَ تطابقٍ ({$proof}) — ولا أرشفةَ قبلَ الإثبات\n"); }
$proofTxt = (string) file_get_contents($proof);
if (strpos($proofTxt, '18/18') === false) { exit("✗ ملفُّ الإثباتِ لا يحمل حصيلةَ 18/18 — يُراجَع قبل الأرشفة\n"); }
echo "✔ إثباتُ التطابقِ حاضرٌ: " . basename($proof) . "\n";

/* ── جدولُ الحجر ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_items_archive_role5` (
  `id` INT NOT NULL COMMENT 'نفسُ معرِّفِ الصفِّ في nav_items — للاستعادةِ الحرفية',
  `role_id` INT NOT NULL,
  `door` VARCHAR(16) NULL,
  `group_id` INT NULL,
  `module_id` INT NULL,
  `label_ar` VARCHAR(64) NOT NULL,
  `route` VARCHAR(128) NOT NULL,
  `icon` VARCHAR(50) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `permission_code` VARCHAR(128) NULL,
  `was_active` TINYINT(1) NOT NULL,
  `quarantined_at` DATETIME NOT NULL,
  `purge_after` DATE NOT NULL COMMENT 'ثلاثون يومًا — ولا حذفَ قبلَها',
  `proof_doc` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01: حجرُ تعريفِ التنقلِ القديم (الدور 5) — ثلاثون يومًا ثم حذف'");

$purge = date('Y-m-d', strtotime('+30 days'));
$conn->query("INSERT IGNORE INTO nav_items_archive_role5
    (id, role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, was_active, quarantined_at, purge_after, proof_doc)
    SELECT id, role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active,
           NOW(), '{$purge}', 'docs/UXUI_OLD18_MATCH_ar.md'
      FROM nav_items WHERE role_id = 5");
$arch = (int) $conn->query("SELECT COUNT(*) c FROM nav_items_archive_role5")->fetch_assoc()['c'];
$activeBefore = (int) $conn->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=5 AND active=1")->fetch_assoc()['c'];
echo "صفوفُ الدورِ 5 المحجورة: {$arch} (منها نشطةٌ الآن: {$activeBefore}) · الحذفُ بعد: {$purge}\n";

/* ── التعطيلُ في الحيِّ — لا حذف ── */
$conn->query("UPDATE nav_items SET active = 0 WHERE role_id = 5 AND active = 1");
$activeAfter = (int) $conn->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=5 AND active=1")->fetch_assoc()['c'];
$rows5 = (int) $conn->query("SELECT COUNT(*) c FROM nav_items WHERE role_id=5")->fetch_assoc()['c'];
echo "بعدَ التعطيل: نشطةٌ={$activeAfter} · وصفوفُ الدورِ باقيةٌ كلُّها={$rows5} (صفرُ حذف)\n";

if ($activeAfter !== 0) { exit("✗ بقيت صفوفٌ نشطةٌ — يُراجَع\n"); }
if ($arch < 18) { exit("✗ المحجورُ أقلُّ من 18 — يُراجَع قبل أيِّ حذف\n"); }
echo "✔ التعريفُ القديمُ أُرشف وعُطِّل — والاستعادةُ ممكنةٌ حتى {$purge}:\n";
echo "   UPDATE nav_items n JOIN nav_items_archive_role5 a ON a.id=n.id SET n.active=a.was_active;\n";
