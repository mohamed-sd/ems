<?php
/**
 * 2027_08_20_nav_timesheet_heir_declaration.php
 *   تصريحُ الوارثِ لصفِّ التايم شيت — إغلاقُ «الفقدِ غيرِ المصرَّح» بقناتِه المعلَنة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الواقعة** — هجرةُ `2027_08_17` نفَّذت نصَّ المالك فبدَّلت وجهةَ صفِّ التنقل
 *   من `Timesheet/timesheet.php` إلى `Timesheet/view_timesheet.php` لدورَي ١ و٢.
 *   ومصفوفةُ الحفظِ تقيس هويةَ البندِ بـ(الملفُّ الأمُّ + الاسمُ المعروض)، فاختفاءُ
 *   الملفِّ من سايدبارِ الدورِ **فقدٌ صريحٌ يُرسِّب** — ولو لم ينقص الوصولُ حرفًا.
 *
 * ◆ **وقاعدةُ البوابةِ ليست «لا يُحذف رابط» بل «لا يُحذف رابطٌ إلا إلى وارثٍ
 *   مُعلَنٍ حاضر»** — بشرطَين مقيسَين معًا:
 *     ① إعلانٌ في `nav_redirects` النشط: قديمٌ ⇐ جديد.
 *     ② وحضورُ الوارثِ **فعلًا** في سايدبارِ الدورِ نفسِه وقتَ القياس.
 *   والشرطُ ② متحقِّقٌ سلفًا: `view_timesheet.php` حاضرٌ في الدورَين. والناقصُ
 *   كان الشرطَ ① وحدَه — أي **السجلّ**، لا الوصول.
 *
 * ◆ **وما يُعلَن هنا وارثُ صفِّ التنقلِ لا تقاعدُ الملف**: `Timesheet/timesheet.php`
 *   باقٍ حيًّا شاشةَ إدخالٍ ويُبلَغ من `timesheet_type.php` الحاضرِ في سايدبارِ
 *   الدورَين (ودورُ ٢ أُعيد مدخلُه بهجرةِ `2027_08_19`). و`nav_redirects` **عدّادٌ
 *   وسجلُّ وراثةٍ لا موجِّهٌ تلقائيّ**: التحويلُ لا يقع إلا إن نادى الملفُّ نفسُه
 *   `ems_route_redirect()`، و`timesheet.php` لا ينادِيها — فلا أثرَ تشغيليَّ لهذا الصف.
 *
 * ◆ **ولا يُفتح البابُ على مصراعَيه**: الإعلانُ لا يُستشار إلا حين يغيب الملفُّ
 *   من دورٍ **ويحضر وارثُه في الدورِ نفسِه**. فالأدوارُ التي ما تزال تحمل
 *   `timesheet.php` لا يمسُّها هذا الصفُّ أصلًا.
 *
 * التشغيل:  php database/migrations/2027_08_20_nav_timesheet_heir_declaration.php
 * الرجوع :  php database/migrations/2027_08_20_nav_timesheet_heir_declaration.php --revert
 * الشاهد :  php tools/uxui_preserve_check.php --gate     ⇐ صفرُ دورٍ فيه نقص
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$OLD    = 'Timesheet/timesheet.php';
$NEW    = 'Timesheet/view_timesheet.php';
$revert = in_array('--revert', $argv, true);

if ($revert) {
    $st = $conn->prepare("DELETE FROM nav_redirects WHERE old_route = ? AND new_route = ?");
    $st->bind_param('ss', $OLD, $NEW);
    $st->execute();
    echo "↺ رجوع: nav_redirects حُذف {$st->affected_rows} صفًّا\n";
    $st->close();
    exit(0);
}

/* ── الشرطُ ② يُقاس قبلَ الإعلان: لا يُعلَن وارثٌ غائب ────────────────────── */
$st = $conn->prepare("SELECT role_id FROM nav_items WHERE route = ? AND active = 1 ORDER BY role_id");
$st->bind_param('s', $NEW); $st->execute();
$res = $st->get_result(); $heirRoles = array();
while ($x = $res->fetch_assoc()) { $heirRoles[] = (int) $x['role_id']; }
$st->close();
if (!$heirRoles) { exit("✘ الوارثُ «{$NEW}» غيرُ حاضرٍ في أيِّ دور — لا يُعلَن وارثٌ غائب\n"); }
echo "الوارثُ حاضرٌ في الأدوار: " . implode(', ', $heirRoles) . "\n";

/* ── العطالة: صفٌّ واحدٌ لا يُكرَّر ─────────────────────────────────────────── */
$st = $conn->prepare("SELECT id, active FROM nav_redirects WHERE old_route = ? AND new_route = ?");
$st->bind_param('ss', $OLD, $NEW); $st->execute();
$st->bind_result($rid, $act); $exists = $st->fetch(); $st->close();

if ($exists) {
    if ((int) $act === 1) {
        echo "↺ عطالة: الإعلانُ قائمٌ ونشطٌ سلفًا (nav_redirects#{$rid})\n";
    } else {
        $st = $conn->prepare("UPDATE nav_redirects SET active = 1 WHERE id = ?");
        $st->bind_param('i', $rid); $st->execute(); $st->close();
        echo "✔ أُعيد تنشيطُ الإعلانِ (nav_redirects#{$rid})\n";
    }
} else {
    $st = $conn->prepare("INSERT INTO nav_redirects (old_route, new_route, active, hits) VALUES (?, ?, 1, 0)");
    $st->bind_param('ss', $OLD, $NEW);
    if (!$st->execute()) { exit("✘ فشلَ الإدراج: " . $st->error . "\n"); }
    echo "✔ nav_redirects#{$st->insert_id}: «{$OLD}» ⇐ «{$NEW}» (نشط · hits=0)\n";
    $st->close();
}

echo "───────────────────────────────────────────────────────────────\n";
echo "تمَّت. الشاهد: php tools/uxui_preserve_check.php --gate\n";
