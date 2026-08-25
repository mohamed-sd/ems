<?php
/**
 * 2027_11_17_nav_anchor_key.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W02 §٤-٣ — **مرساةُ القشرةِ تُقرأ من السجلِّ لا تُكتب في الشيفرة**.
 *
 * ◆ **العيبُ المقيس**: سبعةُ مساراتٍ حرفيّةٍ في `insidebar.php` و
 *   `includes/unified_nav.php` (`chats/index.php` · `emsreports/index.php` ·
 *   `Reports/reports.php` · `Settings/settings.php` · `main/role_board.php`) —
 *   كلٌّ منها **مسارُ تحريرٍ يدويٍّ لبندِ قائمة**: تغييرُ وجهةِ الرابطِ أو اسمِه
 *   يعني تحريرَ ملفِّ قشرةٍ، فيتفرَّق ما في السجلِّ عمّا يراه المستخدم.
 *
 * ◆ **ولماذا `nav_canonical` لا جدولٌ جديد**: هو **السجلُّ المعياريُّ للتنقّلِ
 *   القائم** (٣٨٨ مسارًا) وفيه المسارُ والاسمُ المعتمَدُ وحالتُه. والمراسي
 *   الخمسُ صفوفٌ فيه سلفًا — ينقصها **مفتاحُ نداءٍ** يعرفه الغلافُ. فالعمودُ
 *   `anchor_key` جسرٌ من الشيفرةِ إلى السجلّ: الشيفرةُ تقول «المرساةُ CHATS»
 *   والسجلُّ يقول أينَ تشير وبأيِّ اسم.
 *   ⛔ ولا تقرأ الشيفرةُ الحيّةُ جداولَ `repair01_*` — تلك دفترُ حملةٍ لا
 *      مصدرُ تشغيل (W01_CLOSURE §١٠).
 *
 * ◆ **والأيقونةُ ليست هنا**: مصدرُها الواحدُ `includes/nav_icon_map.php`
 *   (`ems_nav_icon_for`) — ونسخُها عمودًا ثانيًا يخلق مصدرَين يتفرَّقان.
 *
 * التشغيل: php database/migrations/2027_11_17_nav_anchor_key.php
 *          (⛔ لا `migrate.php up`)
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$r = $conn->query("SHOW COLUMNS FROM `nav_canonical` LIKE 'anchor_key'");
if ($r && $r->num_rows > 0) {
    echo "= nav_canonical.anchor_key (قائم)\n";
} elseif ($conn->query("ALTER TABLE `nav_canonical`
            ADD COLUMN `anchor_key` VARCHAR(24) NULL DEFAULT NULL
              COMMENT 'مفتاحُ مِرساةِ القشرة — الغلافُ ينادي المفتاحَ والسجلُّ يعطي المسارَ والاسم',
            ADD UNIQUE KEY `uq_anchor` (`anchor_key`)") === false) {
    exit("✘ ALTER: {$conn->error}\n");
} else {
    echo "✔ nav_canonical.anchor_key\n";
}

/* المراسي الخمسُ — المفتاحُ ⇐ المسارُ المعياريُّ القائمُ في السجلّ.
   ولا يُنشأ صفٌّ جديد: كلُّها موجودةٌ سلفًا، والوسمُ نداءٌ لا إضافة. */
$anchors = array(
    'HOME'         => 'main/role_board.php',
    'CHATS'        => 'chats/index.php',
    'REPORTS_GOV'  => 'emsreports/index.php',
    'REPORTS_EXEC' => 'Reports/reports.php',
    'SETTINGS'     => 'Settings/settings.php',
);
$ok = 0; $miss = 0;
$conn->query("UPDATE `nav_canonical` SET `anchor_key` = NULL WHERE `anchor_key` IS NOT NULL");
foreach ($anchors as $key => $route) {
    $sql = "UPDATE `nav_canonical` SET `anchor_key` = '" . $conn->real_escape_string($key) . "'
            WHERE `route` = '" . $conn->real_escape_string($route) . "' LIMIT 1";
    if ($conn->query($sql) === false) { echo "✘ $key : {$conn->error}\n"; continue; }
    if ($conn->affected_rows === 1) { $ok++; echo "✔ $key ⇐ $route\n"; }
    else { $miss++; echo "✘ $key : لا صفَّ لـ$route في nav_canonical — المرساةُ بلا سجلّ\n"; }
}
echo "\nمُرسًى موصولٌ: $ok  ·  بلا صفٍّ: $miss\n";
exit($miss === 0 ? 0 : 1);
