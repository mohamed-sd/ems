<?php
/**
 * 2028_06_22 — إعلانُ مواضعِ سلسلةِ التايم شيت في ورقةِ الدليل (إتمامُ 2028_06_19)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيسُ بالبوّابةِ لا بالظنّ**: `2028_06_19_timesheet_chain_wiring`
 *   وصلت السلسلةَ فكتبت المواضعَ في **`nav_workspace_placements`** — وبوّابةُ
 *   `U3` تبني «السندَ المكتوب» من **`gov_target_nav` + `nav_placements`**
 *   (`tools/uxui_gates.php:66,78`). فالموضعُ في جدولٍ والحاجبُ يقرأ جدولَين
 *   آخرَين ⇒ صار المساران يُصيَّرانِ في مجموعتَين **بلا سند**، و`U3` **حجبت
 *   الالتزامَ فعلًا** (`✗ 1 بوابة راسبة — البناءُ مرسَّب`).
 *
 * ◆ **وهذا تسجيلٌ لواقعٍ مُقرَّرٍ لا قرارٌ جديد**: دوقبلوك `2028_06_19` يحمل
 *   إقرارَ المالكِ نصًّا: «أُقرَّ صراحةً 2026-09-08 أنَّ مديرَ الموقعِ هو صاحبُ
 *   إدخالِ ساعاتِ العمل». فالمجموعةُ الثانيةُ **مأذونٌ بها سلفًا**، والناقصُ
 *   أن تُكتَب في السجلِّ الذي يقرأه الحاجب. (`CLAUDE.md` ⑦: يُوثَّق الواقع.)
 *
 * ◆ **والأزواجُ مقيسةٌ بدوالِّ البوّابةِ نفسِها** لا بإعادةِ بناءِ منطقِها. قِيست
 *   أربعةٌ بلا سند، **وثلاثةٌ تكفي**:
 *     دور 5 · `timesheet`         ⇐ DEP-18 · g1447 «التشغيل اليومي»
 *     دور 6 · `timesheet`         ⇐ DEP-12 · g73  «التنفيذ الميداني اليومي»
 *     دور 6 · `timesheet_details` ⇐ DEP-12 · g73  «التنفيذ الميداني اليومي»
 *   والرابعُ (دور 1 · `timesheet_details` ⇐ DEP-11 · g66) **أُسقط عمدًا** —
 *   انظر تعليقَ `$ROWS` أدناه: إعلانُه أسقط طيّارَ DEP-11، وهو غيرُ لازم.
 *   والمسارُ يُكتَب **بـ`.php`** كما تخزّنه الورقةُ (صفُّ `#244` شاهدُ الصيغة).
 *
 * ◆ **ولا توسعةَ صلاحيّة**: `nav_placements` **ورقةُ دليلٍ تشهد بالموضع**، ولا
 *   تفتح بابًا ولا تمنح حقًّا — المنحُ في `role_permissions` و`gov_profile_items`
 *   وقد أقرّته `2028_06_19` سلفًا. ⇒ صفرُ تغييرِ وصولٍ حيّ.
 *
 * ◆ **ومُعاوَدة**: يُفحَص وجودُ الصفِّ بالمفتاحِ الثلاثيِّ (مساحة · مجموعة · مسار)
 *   قبلَ الإدراج — فتشغيلٌ ثانٍ يجد صفرَ عملٍ ويخرج بنجاح.
 *
 * التشغيل: php database/migrations/2028_06_22_timesheet_placement_declaration.php
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

$SRC = 'إتمامُ 2028_06_19 — الموضعُ كُتب في nav_workspace_placements ولم يُعلَن في ورقةِ الدليل (U3)';

/* (مساحة، مجموعة، مسار، وسمُ الهدف) — الأربعةُ المقيسةُ من البوّابة */
$ROWS = array(
    /* ⛔ **ولا يُعلَن هدفٌ لا يبنيه خطُّ المساحة**: صفُّ DEP-11 لـ`timesheet_details`
       أُسقط بعدَ قياس — `placement_type=MENU_ITEM` يجعله **هدفًا مطلوبًا** عند
       `tools/navarch/metrics.php:62`، وخطُّ DEP-11 لا يبنيه، فأسقط الطيّارَ
       (`EXACT_WORKSPACE_NAV_CONFORMANCE`). وهو غيرُ لازمٍ لـ`U3` أصلًا:
       شرطُ الرسوبِ `count($bare) > 1` — فسندُ مجموعةٍ واحدةٍ يكفي. */
    array('DEP-18', 1447, 'timesheet/timesheet.php',         'DEP-18·4·سجل ساعات التشغيل اليومي'),
    array('DEP-12', 73,   'timesheet/timesheet.php',         'DEP-12·3·سجل ساعات التشغيل اليومي'),
    array('DEP-12', 73,   'timesheet/timesheet_details.php', 'DEP-12·3·تفاصيل التايم شيت اليومي'),
);

$made = 0; $already = 0; $failed = array();
foreach ($ROWS as $r) {
    list($ws, $gid, $route, $tref) = $r;

    /* ⛔ **المجموعةُ تُتحقَّق لا تُفترَض**: مجموعةٌ معطَّلةٌ أو غائبةٌ لا يقرأها
       الحاجبُ (شرطُه `g.active = 1`) — فإدراجٌ عليها سندٌ لا يُقرأ. */
    $g = $conn->prepare('SELECT label_ar FROM nav_lifecycle_groups WHERE id = ? AND active = 1');
    $g->bind_param('i', $gid);
    $g->execute();
    $gr = $g->get_result()->fetch_assoc();
    if (!$gr) { $failed[] = "{$ws}/{$gid} — مجموعةٌ غيرُ قائمةٍ أو معطَّلة"; continue; }

    $s = $conn->prepare('SELECT COUNT(*) c FROM nav_placements
                          WHERE workspace_id = ? AND group_id = ? AND route = ?');
    $s->bind_param('sis', $ws, $gid, $route);
    $s->execute();
    if ((int) $s->get_result()->fetch_row()[0] > 0) { $already++; continue; }

    $n = $conn->prepare('SELECT COALESCE(MAX(sort_no),0)+1 n FROM nav_placements
                          WHERE workspace_id = ? AND group_id = ?');
    $n->bind_param('si', $ws, $gid);
    $n->execute();
    $sort = (int) $n->get_result()->fetch_row()[0];

    $i = $conn->prepare('INSERT INTO nav_placements
        (workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no,
         placement_type, source_ref, active)
        VALUES (?, NULL, ?, ?, NULL, ?, ?, \'MENU_ITEM\', ?, 1)');
    $i->bind_param('sssiis', $ws, $route, $tref, $gid, $sort, $SRC);
    if ($i->execute()) { $made++; }
    else { $failed[] = "{$ws}/{$gid}/{$route} :: " . substr($conn->error, 0, 70); }
}

echo "  ✔ مواضعُ أُعلنت في ورقةِ الدليل : {$made}\n";
echo "  ◦ مُعلَنةٌ سلفًا                : {$already}\n";
if ($failed) {
    echo "  ⚠ تعذّر (" . count($failed) . "):\n";
    foreach ($failed as $f) { echo "       · {$f}\n"; }
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
