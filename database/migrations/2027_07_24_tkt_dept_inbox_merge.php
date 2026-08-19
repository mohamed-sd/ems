<?php
/**
 * 2027_07_24_tkt_dept_inbox_merge.php — صندوقُ الإدارةِ يصير تبويبًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ `Tickets/dept_inbox.php` كانت شاشةً مستقلّةً مربوطةً بـ**34 دورًا من 35**
 *   (الوحيدُ بلا رابطٍ: #9 الإدارةُ التنفيذية) — وهي في حقيقتها **مرشِّحٌ** على
 *   `ticket_workstreams` بوحدةِ دورِ المستخدم: نفسُ استعلامِ
 *   `ticket_workstreams_board.php` مقصورًا على وحدةٍ واحدةٍ وبأعمدةٍ أقل.
 *
 * ◆ فتُنقل تبويبًا خامسًا داخلَ `tickets_list.php` («موجَّهة لإدارتي») لتَرِثَ:
 *   حارسَ `can_view` (كانت **بلا حارسٍ إطلاقًا**) · الفلاترَ · DataTable ·
 *   زرَّ Excel. ويبقى للمستخدمِ **بابٌ واحدٌ** للبلاغات.
 *
 * ◆ **قِيس أنّ النقلَ لا يحجب أحدًا**: 33 دورًا يملك منحةَ الوحدتين (132 و307)
 *   معًا، و**صفرٌ** يملك 307 وحدَها. فلا دورَ يفقد وصولًا.
 *
 * ◆ `module_id` **يبقى 307 ولا يُبدَّل**: `unified_nav` يرشّح بـ`n.module_id`
 *   لا بالمسار، فتبديلُه يغيّر معنى المنحةِ لأربعةٍ وثلاثين صفًّا دفعةً واحدة.
 *   الرابطُ يتغيّر، والمنحةُ التي تُظهره تبقى كما هي.
 *
 * ◆ والاسمُ يُوحَّد على المعتمَدِ في `nav_canonical` صفّ 334: **«صندوقُ بلاغاتِ
 *   الإدارة»** — كان 29 دورًا يحملون الاسمَ القديمَ «بلاغات إدارتي».
 *
 * ◆ والملفُّ لا يُحذف: يبقى **مُحوِّلًا** (302) للإشاراتِ المحفوظةِ ولأيِّ
 *   مرجعٍ فاتَنا، ويُقيَّد في `nav_redirects` ليُقاس استعمالُه.
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

function n_one($conn, $sql)
{
    $r = $conn->query($sql);
    if (!$r) { echo "  ✖ {$conn->error}\n"; return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

$OLD = 'Tickets/dept_inbox.php';
$NEW = 'Tickets/tickets_list.php?tab=dept';
$LBL = 'صندوقُ بلاغاتِ الإدارة';

echo "═══ 2027_07_24 · دمجُ صندوقِ الإدارةِ تبويبًا ═══\n\n";

/* ── حارسٌ قبل الكتابة: لا يُحجب أحد ───────────────────────────────────── */
$locked = (int) n_one($conn,
    "SELECT COUNT(*) FROM (
        SELECT role_id,
               MAX(CASE WHEN module_id = 132 THEN can_view END) v132,
               MAX(CASE WHEN module_id = 307 THEN can_view END) v307
          FROM role_permissions WHERE module_id IN (132,307) GROUP BY role_id
     ) x WHERE x.v307 = 1 AND (x.v132 IS NULL OR x.v132 = 0)");
if ($locked > 0) {
    exit("✖ توقّف: {$locked} دورًا يملك 307 بلا 132 — النقلُ يحجبهم. امنحهم 132 أولًا.\n");
}
echo "✔ حارسُ الوصول: صفرُ دورٍ يُحجب بالنقل\n\n";

$before = (int) n_one($conn, "SELECT COUNT(*) FROM nav_items WHERE route = '{$OLD}'");
echo "قبل: {$before} صفَّ تنقلٍ يقصد الشاشةَ المستقلّة\n\n";

/* ── ① تحويلُ صفوفِ التنقل ─────────────────────────────────────────────── */
echo "① صفوفُ التنقل — المسارُ والاسم\n";
$ok = $conn->query("UPDATE nav_items
                       SET route = '" . $conn->real_escape_string($NEW) . "',
                           label_ar = '" . $conn->real_escape_string($LBL) . "'
                     WHERE route = '" . $conn->real_escape_string($OLD) . "'");
if ($ok === false) { exit("  ✖ {$conn->error}\n"); }
echo "  ✔ حُوِّل {$conn->affected_rows} صفًّا ⇐ {$NEW}\n";
echo "  ○ module_id بقي 307 — المنحةُ التي تُظهر الرابطَ لم تتغيّر\n";

/* ── ② قيدُ التحويلِ ليُقاس استعمالُ الرابطِ القديم ───────────────────── */
echo "\n② قيدُ التحويل\n";
$ok = $conn->query("INSERT INTO nav_redirects (old_route, new_route, active)
                    SELECT '" . $conn->real_escape_string($OLD) . "',
                           '" . $conn->real_escape_string($NEW) . "', 1
                      FROM DUAL
                     WHERE NOT EXISTS (SELECT 1 FROM nav_redirects r
                           WHERE r.old_route = '" . $conn->real_escape_string($OLD) . "')");
if ($ok === false) { echo "  ✖ {$conn->error}\n"; }
else { echo $conn->affected_rows > 0 ? "  ✔ قُيِّد التحويل\n" : "  ○ مقيَّدٌ سلفًا\n"; }

/* ── ③ سجلُّ التسميةِ المعياريّ ────────────────────────────────────────── */
echo "\n③ nav_canonical — حالةُ الدمج\n";
$ok = $conn->query("UPDATE nav_canonical
                       SET status = 'MERGED',
                           retirement_status = 'MERGE_THEN_REDIRECT',
                           merge_into = 'يُدمج في Tickets/tickets_list.php تبويبًا «موجَّهة لإدارتي» — والملفُّ يبقى مُحوِّلًا'
                     WHERE route = '" . $conn->real_escape_string($OLD) . "'
                       AND retirement_status <> 'MERGE_THEN_REDIRECT'");
if ($ok === false) { echo "  ✖ {$conn->error}\n"; }
else { echo $conn->affected_rows > 0 ? "  ✔ صفّ 334 ⇐ MERGED / MERGE_THEN_REDIRECT\n" : "  ○ مُعلَّمٌ سلفًا\n"; }

/* ── القياسُ بعد ───────────────────────────────────────────────────────── */
$after_old = (int) n_one($conn, "SELECT COUNT(*) FROM nav_items WHERE route = '{$OLD}'");
$after_new = (int) n_one($conn, "SELECT COUNT(*) FROM nav_items WHERE route = '"
    . $conn->real_escape_string($NEW) . "'");
$labels = (int) n_one($conn, "SELECT COUNT(DISTINCT label_ar) FROM nav_items WHERE route = '"
    . $conn->real_escape_string($NEW) . "'");

echo "\n═══ النتيجة ═══\n";
echo "  صفوفٌ تقصد الشاشةَ القديمة: {$before} ⇐ {$after_old}\n";
echo "  صفوفٌ تقصد التبويبَ الجديد: {$after_new}\n";
echo "  أسماءٌ مختلفةٌ لنفسِ الرابط: {$labels} (المتوقَّع 1)\n";
$conn->close();
