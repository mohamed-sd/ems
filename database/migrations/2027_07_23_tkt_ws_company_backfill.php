<?php
/**
 * 2027_07_23_tkt_ws_company_backfill.php — مسارٌ بلا كيانٍ مالكٍ يسقط صامتًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المقيس: **25 صفًّا من `ticket_workstreams` بـ`company_id = NULL`** — كلُّها
 *   من إنتاجِ `TicketRouter::route()`، فجملةُ الإدراجِ فيه **لا تذكر العمودَ
 *   إطلاقًا** بينما التعليقُ على العمودِ يقول «DEC-D ① — مشتقٌّ من tickets».
 *
 * ◆ ولماذا لم يظهر العطلُ حتى الآن: `dept_inbox.php` كانت تستعلم بـ`mysqli_query`
 *   خامًا وتفلتر الشركةَ على **رأسِ البلاغ** لا على المسار — فالعمودُ الفارغُ
 *   لا يؤذيها. لكنّ `TenantDb::scopedQuery()` تحقن `ws.company_id = ?`، فأيُّ
 *   قارئٍ محكومٍ بالبوابةِ **يُسقط الخمسةَ والعشرين بلا رسالة**. وهذا بالضبط
 *   ما كان سيحدث للتبويبِ الجديدِ «موجَّهة لإدارتي».
 *
 * ◆ **الغيابُ ليس محوًا**: يُردم العمودُ من رأسِ البلاغِ نفسِه (المصدرُ الوحيدُ
 *   المعتبَر)، ولا يُخمَّن ولا يُترك.
 *
 * ◆ والجذرُ يُقفل في الشيفرة: `TicketRouter` يكتب `company_id` عند الإدراج —
 *   فالردمُ هنا يعالج ما مضى، والقيدُ هناك يمنع عودتَه.
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

function b_one($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { echo "  ✖ {$conn->error}\n"; return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

echo "═══ 2027_07_23 · ردمُ كيانِ المسارات ═══\n\n";

$before = (int) b_one($conn, "SELECT COUNT(*) FROM ticket_workstreams WHERE company_id IS NULL");
$mismatch = (int) b_one($conn,
    "SELECT COUNT(*) FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
      WHERE w.company_id IS NOT NULL AND w.company_id <> t.company_id");
echo "قبل: بلا كيان = {$before} · مخالفٌ لكيانِ رأسِه = {$mismatch}\n\n";

// الردمُ من الرأسِ حصرًا — ولا يُمسّ صفٌّ له كيانٌ صحيحٌ سلفًا
$ok = $conn->query(
    "UPDATE ticket_workstreams w
       JOIN tickets t ON t.id = w.tk_id
        SET w.company_id = t.company_id
      WHERE w.company_id IS NULL");
if ($ok === false) { exit("  ✖ الردم: {$conn->error}\n"); }
$filled = $conn->affected_rows;
echo "✔ رُدم {$filled} صفًّا من رأسِ بلاغِه\n";

$after = (int) b_one($conn, "SELECT COUNT(*) FROM ticket_workstreams WHERE company_id IS NULL");
$orphan = (int) b_one($conn,
    "SELECT COUNT(*) FROM ticket_workstreams w
      LEFT JOIN tickets t ON t.id = w.tk_id WHERE t.id IS NULL");

echo "\n═══ النتيجة ═══\n";
echo "  بلا كيان: {$before} ⇐ {$after}" . ($after === 0 ? '  ✔' : '  ⚠ يتيمٌ بلا رأس') . "\n";
echo "  مساراتٌ بلا رأسٍ إطلاقًا: {$orphan}\n";
$conn->close();
