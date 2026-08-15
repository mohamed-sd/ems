<?php
/**
 * 2027_03_30_dedupe_chats_nav_rows.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حذفُ صفوفِ «المراسلات» المكرَّرةِ من `nav_items`
 * ⇐ INJ-0414 · INJ-0489 · INJ-0540 · INJ-0448 · INJ-0222 · INJ-0562
 *
 * **العلّة**: «المراسلات» تُطبع مرتين في سايدبارِ ٢٥ دورًا — مرةً محقونةً في
 * المولِّد (`includes/unified_nav.php`) ومرةً من صفٍّ في `nav_items`.
 *
 * ── القرارُ ولماذا ─────────────────────────────────────────────────────────
 * **تبقى المحقونةُ ويُحذف الصفُّ.** وجهان:
 *   ① المحقونةُ وحدَها تحمل **شارةَ غيرِ المقروءِ الحيّة** (`#nav-unread-badge`
 *      يحدّثها مستطلِعُ `insidebar`). ونزعُ الحقنِ يُفقد الشارةَ كلَّ دور.
 *   ② وهي في **المولِّد** فتصمد أمام كلِّ إعادةِ توليدٍ من الوثيقة — بينما
 *      الصفُّ يعود مع أوّلِ بذرة.
 *
 * ◆ والحذفُ **ليس وحدَه** الإصلاح: `printNavLinkItem` صارت تحرس التكرارَ
 *   بمجموعةِ مسارٍ مطبوعٍ مطبَّعةٍ (بلا مرساةٍ ولا بادئةٍ ولا سلسلةِ استعلام) —
 *   فلو عاد الصفُّ من بذرةٍ لاحقةٍ لم يُطبع مرتين.
 * ◆ ولا حذفَ صلبٌ بلا رجعة: الصفوفُ تُنسخ إلى `nav_items_archive_chats` قبل
 *   الحذفِ فيمكن ردُّها بأمر.
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
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ حذفُ صفوفِ «المراسلات» المكرَّرة ══\n\n";

$before = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%chats/index.php%'");
if ($r) { $before = (int) $r->fetch_row()[0]; }
echo "  الصفوفُ قبل: {$before}\n";
if ($before === 0) { echo "  · لا شيءَ يُحذف — الهجرةُ مطبَّقةٌ سلفًا\n"; exit(0); }

/* أرشيفٌ قبل الحذف — فالردُّ ممكنٌ بأمرٍ واحد */
$conn->query('CREATE TABLE IF NOT EXISTS nav_items_archive_chats LIKE nav_items');
$ok = $conn->query("INSERT INTO nav_items_archive_chats
                    SELECT * FROM nav_items WHERE route LIKE '%chats/index.php%'");
if (!$ok) { echo "  ✘ تعذَّرت الأرشفة: {$conn->error}\n"; exit(1); }
$arch = $conn->affected_rows;
echo "  ✔ أُرشف {$arch} صفًّا في `nav_items_archive_chats`\n";

if (!$conn->query("DELETE FROM nav_items WHERE route LIKE '%chats/index.php%'")) {
    echo "  ✘ تعذَّر الحذف: {$conn->error}\n";
    exit(1);
}
$del = $conn->affected_rows;
$after = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%chats/index.php%'");
if ($r) { $after = (int) $r->fetch_row()[0]; }
echo "  ✔ حُذف {$del} صفًّا · المتبقّي: {$after}\n";
echo "\n  ◆ والرابطُ باقٍ: المولِّدُ يطبعه بشارةِ غيرِ المقروءِ الحيّة.\n";
echo "  ◆ والردُّ إن لزم: INSERT INTO nav_items SELECT * FROM nav_items_archive_chats;\n";
exit($after === 0 ? 0 : 1);
