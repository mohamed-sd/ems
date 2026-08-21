<?php
/**
 * 2027_08_19_nav_role2_timesheet_entry_restore.php
 *   إعادةُ مدخلِ تسجيلِ الوحداتِ إلى سايدبارِ دورِ الموردين — استيفاءُ «صفرِ الفقد»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السبب** — هجرةُ `2027_08_17_nav_timesheet_link_to_view.php` نفَّذت نصَّ المالك:
 *   رابطُ التايم شيت يقود إلى **سجلِّ** الوحدات لا إلى مُنتقي النوع، لدورَي
 *   ١ (التشغيل) و٢ (الموردين). وقد بدَّلت الصفَّ ولم تُضِف صفًّا.
 *
 * ◆ **والقياس فرَّق بين الدورَين** — والمقصدُ لا المسارُ المخزَّن هو ما يُقاس:
 *   الصفُّ القديمُ كان يحمل `Timesheet/timesheet.php` **بلا `?type=`**، و`timesheet.php`
 *   يشترط `type ∈ {1,2,3}` وإلا حوَّل إلى `timesheet_type.php`. فمقصدُ الرابطِ
 *   القديمِ الفعليُّ كان **مُنتقي النوع**.
 *     · دور ١ ما يزال يملك صفًّا مستقلًّا إلى `timesheet_type.php` («تسجيل الوحدات»)
 *       ⇐ فمقصدُ الرابطِ القديمِ باقٍ في سايدبارِه · **لا فقدَ فعليًّا**.
 *     · دور ٢ لا يملكه ⇐ فقد **مدخلَ الإدخالِ من السايدبار**، ولم يبقَ إليه
 *       إلا زرُّ رجوعٍ داخلَ `view_timesheet.php` (سطر ٨٤٤). **هذا فقدٌ يُرسِّب.**
 *
 * ◆ **ولذلك يُعالَج دورُ ٢ وحدَه** — بقرارِ المالكِ (2026-08-21): يبقى
 *   `view_timesheet.php` كما طُلب، ويعود المدخلُ المفقودُ **وحدَه**. فصفرُ الفقدِ
 *   يصير صادقًا **بالقياس** لا بإعلانٍ في `nav_redirects` يصف ملفًّا حيًّا بأنه
 *   متقاعد. ولا يُمسُّ دورُ ١ ولا أيُّ دورٍ آخر.
 *
 * ◆ **ولا يُفتح قفلٌ جديد**: الدورُ ٢ يملك `can_view=1` على الوحدة ١٠
 *   (`Timesheet/timesheet_type.php`) في `role_permissions` سلفًا — والهجرةُ تتحقَّق
 *   وتتوقّف إن لم يكن، فلا يُصنع رابطٌ يقود إلى ٤٠٣.
 *
 * ◆ **ومصدرانِ لا مصدرٌ واحد**: يُكتب الصفُّ في `nav_items` **و**`nav_canonical_current`
 *   معًا — فتحويلُ أحدِهما وحدَه يصطنع توأمًا بأيقونةِ `fa fa-link`.
 *
 * التشغيل:  php database/migrations/2027_08_19_nav_role2_timesheet_entry_restore.php
 * الرجوع :  php database/migrations/2027_08_19_nav_role2_timesheet_entry_restore.php --revert
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

$ROLE   = 2;                                     // ادارة الموردين — وحدَه
$ROUTE  = 'Timesheet/timesheet_type.php';
$KEY    = mb_strtolower($ROUTE);
$LABEL  = 'تسجيل الوحدات';                        // اسمُ الصفِّ نفسِه في دور ١
$ICON   = 'fa fa-business-time';
$revert = in_array('--revert', $argv, true);

/* ── الوحدةُ بالرمزِ لا برقمٍ محفوظ · وأدنى id عند التكرار (سلوكُ الحارسِ نفسِه) ── */
$st = $conn->prepare("SELECT MIN(id) FROM modules WHERE code = ?");
$st->bind_param('s', $ROUTE); $st->execute(); $st->bind_result($modId); $st->fetch(); $st->close();
$modId = (int) $modId;
if ($modId === 0) { exit("✘ لا وحدةَ مسجَّلةٌ للمسار «{$ROUTE}» — أُوقفت الهجرة\n"); }

if ($revert) {
    $st = $conn->prepare("DELETE FROM nav_items WHERE role_id = ? AND route = ? AND module_id = ?");
    $st->bind_param('isi', $ROLE, $ROUTE, $modId); $st->execute();
    $n1 = $st->affected_rows; $st->close();
    $st = $conn->prepare("DELETE FROM nav_canonical_current WHERE role_id = ? AND route = ?");
    $st->bind_param('is', $ROLE, $KEY); $st->execute();
    $n2 = $st->affected_rows; $st->close();
    echo "↺ رجوع: nav_items={$n1} · nav_canonical_current={$n2}\n";
    exit(0);
}

/* ── ① الصلاحيةُ شرطُ الرابط ─────────────────────────────────────────────── */
$st = $conn->prepare("SELECT can_view FROM role_permissions WHERE role_id = ? AND module_id = ?");
$st->bind_param('ii', $ROLE, $modId); $st->execute();
$st->bind_result($canView); $has = $st->fetch(); $st->close();
if (!$has || (int) $canView !== 1) {
    exit("✘ الدور {$ROLE} بلا can_view على الوحدة {$modId} — أُوقفت الهجرة (لا يُصنع رابطٌ يقود إلى ٤٠٣)\n");
}

/* ── ② العطالة: إن كان الصفُّ قائمًا فلا يُكرَّر ──────────────────────────── */
$st = $conn->prepare("SELECT id FROM nav_items WHERE role_id = ? AND route = ? AND active = 1");
$st->bind_param('is', $ROLE, $ROUTE); $st->execute();
$st->bind_result($exists); $already = $st->fetch(); $st->close();

/* ── ③ الجوار: يُوضع في بابِ وقسمِ وترتيبِ جارِه `view_timesheet.php` ────────
   فالمدخلُ والسجلُّ مرحلتان متتاليتان — ولا يُنثر أحدُهما في بابٍ آخر. */
$st = $conn->prepare("SELECT door, group_id, sort_order FROM nav_items
                       WHERE role_id = ? AND route = 'Timesheet/view_timesheet.php' AND active = 1");
$st->bind_param('i', $ROLE); $st->execute();
$st->bind_result($door, $groupId, $sortOrder); $gotNeighbour = $st->fetch(); $st->close();
if (!$gotNeighbour) { $door = 'DAILY'; $groupId = null; $sortOrder = 24; }
$sortOrder = (int) $sortOrder + 1;

if ($already) {
    echo "↺ عطالة: الصفُّ قائمٌ سلفًا (nav_items#{$exists}) — لم يُكرَّر\n";
} else {
    $st = $conn->prepare("INSERT INTO nav_items
              (role_id, door, group_id, module_id, label_ar, route, icon, permission_code, active, sort_order)
       VALUES (?,       ?,    ?,        ?,         ?,        ?,     ?,    ?,               1,      ?)");
    $st->bind_param('isiissssi', $ROLE, $door, $groupId, $modId, $LABEL, $ROUTE, $ICON, $ROUTE, $sortOrder);
    if (!$st->execute()) { exit("✘ فشلَ الإدراج: " . $st->error . "\n"); }
    $newId = $st->insert_id; $st->close();
    echo "✔ nav_items#{$newId}: دور {$ROLE} · باب {$door} · مجموعة " . var_export($groupId, true)
       . " · ترتيب {$sortOrder} · وحدة {$modId}\n";
}

/* ── ④ السجلُّ المعياريُّ الحاضر — المصدرُ الثاني للوجهة ──────────────────── */
$st = $conn->prepare("SELECT cur_group, cur_order FROM nav_canonical_current
                       WHERE role_id = ? AND route = 'timesheet/view_timesheet.php'");
$st->bind_param('i', $ROLE); $st->execute();
$st->bind_result($nGroup, $nOrder); $gotReg = $st->fetch(); $st->close();
$curGroup = $gotReg ? $nGroup : 'لوحة الإدارة';
$curOrder = $gotReg ? ((int) $nOrder + 1) : $sortOrder;

$st = $conn->prepare("SELECT COUNT(*) FROM nav_canonical_current WHERE role_id = ? AND route = ?");
$st->bind_param('is', $ROLE, $KEY); $st->execute();
$st->bind_result($regN); $st->fetch(); $st->close();
if ((int) $regN > 0) {
    echo "↺ عطالة: موضعُ السجلِّ قائمٌ سلفًا — لم يُكرَّر\n";
} else {
    $st = $conn->prepare("INSERT INTO nav_canonical_current (route, role_id, cur_label, cur_group, cur_order)
                          VALUES (?, ?, ?, ?, ?)");
    $st->bind_param('sissi', $KEY, $ROLE, $LABEL, $curGroup, $curOrder);
    if (!$st->execute()) { exit("✘ فشلَ إدراجُ موضعِ السجل: " . $st->error . "\n"); }
    $st->close();
    echo "✔ nav_canonical_current: «{$KEY}» · دور {$ROLE} · قسم «{$curGroup}» · ترتيب {$curOrder}\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo "تمَّت. الشاهد: php tools/uxui_preserve_check.php --gate\n";
