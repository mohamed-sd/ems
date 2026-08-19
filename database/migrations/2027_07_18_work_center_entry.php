<?php
/**
 * 2027_07_18_work_center_entry.php — مركزُ العملِ مدخلًا لكلِّ دور (DELTA · X3)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ فحصُ التحقُّقِ في `INJAZ-6-DELTA`: «خمسةُ أرقامٍ تُطابَق… **مركزُ العملِ
 *   مدخلًا في 19/19**».
 * ◆ والمقيسُ حيًّا: **31 من 34 دورًا نشطًا** تصلها شاشاتُ المركزِ الشخصيّ —
 *   وثلاثةٌ لا تصلها: **مشرفُ المخاطرِ (30) · رئيسُ الحساباتِ (31) · المراجعُ
 *   الداخليُّ المستقلُّ (33)**. فيدخل صاحبُ الدورِ ولا يجد «مهامي» ولا «إنجازي».
 *
 * ◆ **ولا يُخترع نمطٌ جديد**: يُنسخ النمطُ القائمُ حرفًا — مجموعةُ «مساحتي
 *   الشخصية» بترتيبٍ 1، وثلاثُ شاشاتٍ APPROVED في السجلِّ المعياريّ:
 *   `Portal/my_tasks.php` · `Portal/my_achievement.php` · `Portal/my_portal.php`.
 *   واسمُ كلٍّ يُقرأ من `nav_canonical` لا يُكتب.
 *
 * ◆ **والصلاحيةُ لا تُمنح هنا**: يُزرع بندُ التنقلِ فقط، و`permission_code`
 *   يساوي المسارَ كما في الأدوارِ الأخرى — فالحارسُ يبقى هو الحاكم. وزرعُ
 *   رابطٍ بلا صلاحيةٍ يُظهر الشاشةَ ثم يمنعها، فتُمنح الرؤيةُ في
 *   `role_permissions` بنفسِ نمطِ الأدوارِ التي تصلها.
 *
 * ◆ **وقاعدةُ صفرِ الفقدِ لا تُخالَف**: إضافةٌ لا حذف — ولا يُمَسُّ بندٌ قائم.
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

$ROUTES = array('Portal/my_tasks.php', 'Portal/my_achievement.php', 'Portal/my_portal.php');

/* ── الأدوارُ الناقصةُ تُكتشَف حيًّا لا تُكتب قائمةً ─────────────────────────── */
$in = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $ROUTES)) . "'";
$lack = array();
$r = $conn->query("SELECT DISTINCT n.role_id FROM nav_items n
                    WHERE n.active = 1
                      AND n.role_id NOT IN (SELECT role_id FROM nav_items
                                             WHERE active = 1 AND route IN ({$in}))");
while ($r && ($x = $r->fetch_assoc())) { $lack[] = (int) $x['role_id']; }
if (!$lack) { echo "✔ مركزُ العملِ يصل كلَّ دورٍ نشط — لا شيءَ يُزرع\n"; exit(0); }

/* ── أسماءُ الشاشاتِ من السجلِّ المعياريِّ لا من عندي ─────────────────────── */
$canon = array();
$r = $conn->query("SELECT route, canonical_ar, sort_no FROM nav_canonical WHERE route IN ({$in})");
while ($r && ($x = $r->fetch_assoc())) { $canon[$x['route']] = $x; }

$addedNav = 0; $addedGrp = 0; $addedPerm = 0; $notes = array();
foreach ($lack as $rid) {
    /* ① المجموعةُ — تُعاد إن وُجدت ولا تُكرَّر */
    $g = $conn->query("SELECT id FROM link_groups WHERE owner_role_id = {$rid} AND name = 'مساحتي الشخصية' LIMIT 1");
    if ($g && $g->num_rows) {
        $gid = (int) $g->fetch_assoc()['id'];
    } else {
        $code = 'n9s00_0_1_r' . $rid;
        $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
                      VALUES ('مساحتي الشخصية', '{$code}', {$rid}, 'fa fa-user', 1, 1)");
        $gid = (int) $conn->insert_id;
        if ($gid > 0) { $addedGrp++; } else { $notes[] = "دور {$rid}: تعذّر إنشاءُ المجموعة"; continue; }
    }
    $conn->query("UPDATE link_groups SET is_active = 1, display_order = 1 WHERE id = {$gid}");

    /* ② بنودُ التنقل — بأسماءِ السجلِّ المعياريّ */
    $ord = 1;
    foreach ($ROUTES as $route) {
        $e = $conn->real_escape_string($route);
        $exists = $conn->query("SELECT 1 FROM nav_items WHERE role_id = {$rid} AND route = '{$e}' LIMIT 1");
        if ($exists && $exists->num_rows) { $ord++; continue; }
        $label = isset($canon[$route]) ? $canon[$route]['canonical_ar'] : basename($route, '.php');
        $le = $conn->real_escape_string($label);
        $mq = $conn->query("SELECT id FROM modules WHERE code = '{$e}' LIMIT 1");
        $mid = ($mq && $mq->num_rows) ? (int) $mq->fetch_assoc()['id'] : 0;
        $midSql = $mid > 0 ? (string) $mid : 'NULL';
        /* ◆ **قيمةُ `door` تُقرأ من النظامِ لا تُخترع**: قيدُ `chk_nav_door`
             يحصرها في تسعِ قيم، و`NULL` يسقط صامتًا. والقيمةُ الصحيحةُ
             لهذه الشاشاتِ **هي الغالبةُ في الأدوارِ التي تصلها فعلًا** —
             تُستخرَج بالاستعلامِ لا بالتقدير، وبلا سابقةٍ لا يُزرع الصفّ. */
        $dq = $conn->query("SELECT door, COUNT(*) c FROM nav_items
                              WHERE active = 1 AND route = '{$e}' AND door IS NOT NULL
                              GROUP BY door ORDER BY c DESC LIMIT 1");
        $door = ($dq && $dq->num_rows) ? $dq->fetch_assoc()['door'] : null;
        if ($door === null) { $notes[] = "دور {$rid} · {$route}: لا سابقةَ `door` — لا يُزرع بتقدير"; $ord++; continue; }
        $de = $conn->real_escape_string($door);
        $ok = $conn->query("INSERT INTO nav_items
                (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                VALUES ({$rid}, '{$de}', {$gid}, {$midSql}, '{$le}', '{$e}', 'fa fa-user', {$ord}, '{$e}', 1)");
        if ($ok) { $addedNav++; } else { $notes[] = "دور {$rid} · {$route}: " . $conn->error; }

        /* ③ الرؤيةُ في `role_permissions` — وإلا ظهر رابطٌ يمنعه الحارس */
        if ($mid > 0) {
            $pq = $conn->query("SELECT 1 FROM role_permissions WHERE role_id = {$rid} AND module_id = {$mid} LIMIT 1");
            if (!$pq || $pq->num_rows === 0) {
                if ($conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                  VALUES ({$rid}, {$mid}, 1, 0, 0, 0)")) { $addedPerm++; }
            }
        }
        $ord++;
    }
}

echo "════ مركزُ العملِ مدخلًا لكلِّ دور ════\n";
echo "  أدوارٌ كانت بلا مركزِ عمل: " . count($lack) . " (" . implode(' · ', $lack) . ")\n";
echo "  مجموعاتٌ أُنشئت: {$addedGrp} · بنودُ تنقلٍ زُرعت: {$addedNav} · منحُ رؤيةٍ: {$addedPerm}\n";
foreach ($notes as $n) { echo "  ⚠ {$n}\n"; }
$still = 0;
$r = $conn->query("SELECT COUNT(DISTINCT n.role_id) c FROM nav_items n
                    WHERE n.active = 1
                      AND n.role_id NOT IN (SELECT role_id FROM nav_items WHERE active = 1 AND route IN ({$in}))");
if ($r) { $still = (int) $r->fetch_assoc()['c']; }
$tot = (int) $conn->query("SELECT COUNT(DISTINCT role_id) c FROM nav_items WHERE active=1")->fetch_assoc()['c'];
echo "  ▸ بعدَ الزرع: " . ($tot - $still) . "/{$tot} دورًا تصلها شاشاتُ المركز\n";
echo $still === 0 ? "✔ مركزُ العملِ مدخلٌ في كلِّ دورٍ نشط\n" : "◆ ما زال {$still} — يُعلَن ولا يُخفى\n";
