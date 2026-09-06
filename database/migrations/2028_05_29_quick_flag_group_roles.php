<?php
/**
 * 2028_05_29_quick_flag_group_roles.php — استيفاءُ بذرِ الوصولِ السريع
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **عطبٌ في البذرِ السابقِ كشفه القياسُ بالتصيير** (لا بالجدول):
 *   بذرُ 2028_05_28 قرأ **فرعًا واحدًا** من منطقِ لوحةِ التحكم — `is_quick`
 *   على الوحدات. ولوحةُ التحكمِ لها **فرعان**: متى كان للدورِ مجموعاتُ روابطَ
 *   (`getNavGroups`) صُيِّرت **بلاطاتُ المجموعةِ كلُّها** بلا سؤالٍ عن
 *   `is_quick` إطلاقًا. فأربعةُ أدوارٍ (27 · 28 · 29 · 30) كانت بلاطتُها
 *   تأتي من ذلك الفرع، ولم تُبذَر لها رايةٌ — فتسقط بلاطتُها عند التحويل.
 *   ◆ **والقياسُ الذي كشفه لقطتا تصييرٍ قبل/بعد**، لا استعلامٌ على الجدول:
 *     الجدولُ كان يقول «صفرُ فجوة» لأنه سُئل عن الفرعِ الذي بُذر منه وحدَه.
 *
 * ◆ **والاستيفاءُ يقرأ الفرعَ الثاني بحرفِه**: مجموعاتُ الدورِ × روابطُها
 *   الديناميكيّة — وهو تعريفُ ما كان يُصيَّر.
 * ⛔ **ولا يُنشئ صفًّا حيًّا**: ما لا صفَّ له في `nav_items` يُستوفى `active=0`
 *   كما في الجولةِ السابقة — رايةُ وصولٍ سريعٍ بلا زيادةِ رابطٍ في سايدبار.
 *
 * التشغيل: php database/migrations/2028_05_29_quick_flag_group_roles.php
 * العكس:   php database/migrations/2028_05_29_quick_flag_group_roles_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };
$GLOBALS['conn'] = $conn;
require_once $ROOT . '/includes/dynamic_nav.php';

$norm = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

$has = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items' AND COLUMN_NAME = 'is_quick'");
if ($has === 0) { exit("⛔ لا عمودَ is_quick — شغِّل 2028_05_28 أولًا.\n"); }

$before = $one("SELECT COUNT(*) FROM nav_items WHERE is_quick = 1");
echo "══ ① قبلَ الاستيفاء — رايات: {$before} ═══════════════════════════════\n";

/* نموذجُ المسارِ لصفٍّ يُنشأ — من أشيعِ صفٍّ له في السجلّ */
$proto = array();
$r = $conn->query("SELECT route, door, label_ar, icon, module_id, permission_code, COUNT(*) n
                     FROM nav_items GROUP BY route, door, label_ar, icon, module_id, permission_code
                    ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $norm($x['route']);
    if (!isset($proto[$k])) { $proto[$k] = $x; }
}

$roles = array();
$r = $conn->query('SELECT id FROM roles ORDER BY id');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }

$upd = $conn->prepare("UPDATE nav_items SET is_quick = 1 WHERE role_id = ? AND route = ?");
$ins = $conn->prepare("INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
         counter_source, permission_code, active, is_quick)
     VALUES (?, ?, NULL, ?, ?, ?, ?, 900, NULL, ?, 0, 1)");

$touched = 0; $created = 0; $groupRoles = array();
foreach ($roles as $rid) {
    /* الفرعُ الثاني حرفًا: مجموعاتُ الدورِ التي لها روابطُ ⇒ كلُّ روابطِها بلاطات */
    $links  = getDynamicNavLinks($conn, (string) $rid);
    $groups = function_exists('getNavGroups') ? getNavGroups($conn, (string) $rid) : array();
    if (empty($groups)) { continue; }
    $byG = array();
    foreach ($links as $l) {
        $g = (isset($l['group_id']) && $l['group_id'] !== null) ? (int) $l['group_id'] : 0;
        if ($g > 0) { $byG[$g][] = $l; }
    }
    $tiles = array();
    foreach ($groups as $g) {
        $gid = (int) $g['id'];
        if (!empty($byG[$gid])) { foreach ($byG[$gid] as $l) { $tiles[] = $l; } }
    }
    if (!$tiles) { continue; }        /* لم يكن هذا الفرعُ هو المُصيَّرَ لهذا الدور */
    $groupRoles[] = $rid;

    $mine = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$rid}");
    while ($q && ($x = $q->fetch_row())) { $mine[$norm($x[0])] = (string) $x[0]; }

    foreach ($tiles as $l) {
        $code = (string) $l['code'];
        $k = $norm($code);
        if (isset($mine[$k])) {
            $rt = $mine[$k];
            $upd->bind_param('is', $rid, $rt);
            $upd->execute();
            $touched += $upd->affected_rows;
            continue;
        }
        $pr = isset($proto[$k]) ? $proto[$k] : null;
        $door  = $pr ? (string) $pr['door'] : 'DAILY';
        $label = $pr ? (string) $pr['label_ar'] : (string) $l['name'];
        $icon  = $pr ? (string) $pr['icon'] : (!empty($l['icon']) ? (string) $l['icon'] : 'fa fa-link');
        $mid   = ($pr && $pr['module_id'] !== null) ? (int) $pr['module_id'] : (int) $l['id'];
        $pc    = $pr ? (string) $pr['permission_code'] : $code;
        $route = $pr ? (string) $pr['route'] : $code;
        $ins->bind_param('isissss', $rid, $door, $mid, $label, $route, $icon, $pc);
        if ($ins->execute()) { $created += $ins->affected_rows; }
    }
}
$upd->close();
$ins->close();

echo "\n══ ② الاستيفاء ═══════════════════════════════════════════════════════\n";
printf("   أدوارٌ كان مصدرُ بلاطاتِها المجموعات: %s\n", $groupRoles ? implode(' · ', $groupRoles) : 'لا شيء');
printf("   رايات رُفعت: %d · صفوفٌ ساكنةٌ أُنشئت: %d\n", $touched, $created);

echo "\n══ ③ الشاهد ══════════════════════════════════════════════════════════\n";
$after = $one("SELECT COUNT(*) FROM nav_items WHERE is_quick = 1");
printf("   رايات الآن: %d (كانت %d)\n", $after, $before);
$act = $one("SELECT COUNT(*) FROM nav_items WHERE active = 1");
printf("   صفوفٌ حيّةٌ (لا تتغيّر): %d\n", $act);

/* صفرُ فجوةٍ على **الفرعَين معًا** لا على فرعٍ واحد */
$mq = array();
$r = $conn->query('SELECT id FROM modules WHERE is_quick = 1');
while ($r && ($x = $r->fetch_row())) { $mq[(int) $x[0]] = true; }
$gap = 0; $sample = array();
foreach ($roles as $rid) {
    $flags = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$rid} AND is_quick = 1");
    while ($q && ($x = $q->fetch_row())) { $flags[$norm($x[0])] = true; }

    $links  = getDynamicNavLinks($conn, (string) $rid);
    $groups = function_exists('getNavGroups') ? getNavGroups($conn, (string) $rid) : array();
    $byG = array();
    foreach ($links as $l) {
        $g = (isset($l['group_id']) && $l['group_id'] !== null) ? (int) $l['group_id'] : 0;
        if ($g > 0) { $byG[$g][] = $l; }
    }
    $tiles = array();
    foreach ($groups as $g) {
        $gid = (int) $g['id'];
        if (!empty($byG[$gid])) { foreach ($byG[$gid] as $l) { $tiles[] = $l; } }
    }
    if (!$tiles) {  /* الفرعُ الإرثيّ */
        foreach ($links as $l) { if (!empty($mq[(int) $l['id']])) { $tiles[] = $l; } }
    }
    foreach ($tiles as $l) {
        if (!isset($flags[$norm((string) $l['code'])])) {
            $gap++;
            if (count($sample) < 5) { $sample[] = $rid . ':' . $l['code']; }
        }
    }
}
printf("   بلاطاتٌ كانت تُصيَّر وبلا رايةٍ الآن: %d%s\n", $gap, $gap ? ' ⛔ — ' . implode(' · ', $sample) : '');
if ($gap !== 0) { exit("\n⛔ الشاهدُ لم يتحقّق — لا تلتزمْ قبلَ المراجعة.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — الفرعان مبذوران، والحكمُ النهائيُّ بلقطةِ تصييرٍ لا بجدول.\n";
