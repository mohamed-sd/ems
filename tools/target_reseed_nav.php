<?php
/**
 * الموجة ③ · N-01→N-03 · إعادةُ بذر القوائم من الترتيب المستهدف — update0007
 * ───────────────────────────────────────────────────────────────────────────
 * «المرجعُ النافذ: ملفُّ الترتيب المستهدف» (NAV-02 v7) — لكل ورقةِ إدارة:
 *   ① مجموعاتُها المرنةُ بعددها هي (NAV-01 v6 §4: الثماني استرشاديٌّ لا فرض)
 *   ② ظهوراتُ شاشاتها بترتيب الورقة — القائمُ يُبذر والجديدُ ★ يُبذر إن حُلّ
 *     مسارُه (بلاغاتُ إدارتي = dept_inbox) وإلا انتظر بناءَه.
 *   ③ ما ليس في المستهدف يُطفأ (عدا قائمةِ ثوابتَ معلنة).
 * idempotent. التشغيل: php tools/target_reseed_nav.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/target_order_read.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* المساراتُ المحلولةُ للجديد ★ الذي بُني فعلًا */
$builtNew = array('بلاغات إدارتي' => 'Tickets/dept_inbox.php');
/* ثوابتُ لا يمسّها الإطفاء (خارج أوراق الإدارات أو بنيةُ الحاوية) */
$keepAlways = array('main/my_workspace.php');

/* بابُ الصفّ من موضع مجموعته — door محصورٌ بقيدٍ فيُشتق بالموضع لا بالاسم */
function door_of($idx, $total) {
    if ($idx === 1) return 'HOME';
    if ($idx === 2) return 'DAILY';
    if ($idx === $total) return 'SET';
    if ($idx === $total - 1) return 'REP';
    return 'REC';
}

$stats = array('groups' => 0, 'items' => 0, 'linked' => 0, 'skipped_new' => 0, 'deact' => 0);
$targetLive = array();   // role_id:route → 1 (لتحديد ما يُطفأ لاحقًا)

foreach (target_dept_sheets() as $dept => $sheetIdx) {
    $role = target_dept_role($dept);
    if ($role === null || $role === 0) continue;          // مساحةُ عملي فوق الإدارات
    $rows = array_slice(target_sheet($sheetIdx), 3);       // الرأسان + العناوين

    /* ① المجموعاتُ المرنة — بترتيب أول ظهورٍ في الورقة */
    $groups = array(); $order = array();
    foreach ($rows as $r) {
        $g = trim($r[1] ?? '');
        if ($g !== '' && !isset($groups[$g])) { $groups[$g] = null; $order[] = $g; }
    }
    $total = count($order);
    foreach ($order as $i => $gname) {
        $idx = $i + 1;
        $code = 'g' . $idx;
        // مجموعةُ الدور بهذا الترتيب: تُحدَّث باسم المستهدف أو تُنشأ
        $gn = mysqli_real_escape_string($conn, preg_replace('/^[①-⑮]\s*/u', '', $gname));
        $r2 = mysqli_query($conn, "SELECT id FROM link_groups WHERE owner_role_id=$role AND group_code='$code' LIMIT 1");
        if ($r2 && ($x = mysqli_fetch_assoc($r2))) {
            mysqli_query($conn, "UPDATE link_groups SET name='$gn', is_active=1, display_order=$idx WHERE id={$x['id']}");
            $groups[$gname] = intval($x['id']);
        } else {
            mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, display_order, is_active)
                                 VALUES ('$gn','$code',$role,$idx,1)");
            $groups[$gname] = mysqli_insert_id($conn);
        }
        $stats['groups']++;
    }
    // مجموعاتُ الدور الزائدةُ عن المستهدف تُطفأ
    mysqli_query($conn, "UPDATE link_groups SET is_active=0
                         WHERE owner_role_id=$role AND is_active=1 AND group_code > 'g$total' AND group_code LIKE 'g%'");

    /* ② الظهورات */
    $sort = 0;
    foreach ($rows as $r) {
        $gname = trim($r[1] ?? '');
        $name  = trim($r[2] ?? '');
        $route = trim($r[6] ?? '');
        $src   = trim($r[16] ?? '') . trim($r[14] ?? '');
        if ($name === '' || $gname === '' || !isset($groups[$gname])) continue;
        $sort += 2;
        $isNew = (mb_strpos(implode('', $r), '★') !== false) && ($route === '' || $route === '—');
        if ($isNew) {
            if (isset($builtNew[$name])) { $route = $builtNew[$name]; $stats['linked']++; }
            else { $stats['skipped_new']++; continue; }     // يُبذر عند بنائه (الموجتان ④⑤)
        }
        if ($route === '' || $route === '—') continue;
        $gid = $groups[$gname];
        $gidx = array_search($gname, $order) + 1;
        $door = door_of($gidx, $total);
        $en = mysqli_real_escape_string($conn, $name);
        $er = mysqli_real_escape_string($conn, $route);
        $targetLive["$role:" . strtolower($route)] = 1;
        // upsert على UQ(role_id, route)
        mysqli_query($conn, "INSERT INTO nav_items (role_id, door, group_id, label_ar, route, icon, sort_order, active)
                             VALUES ($role, '$door', $gid, '$en', '$er', 'fa fa-link', $sort, 1)
                             ON DUPLICATE KEY UPDATE door='$door', group_id=$gid, label_ar='$en',
                               sort_order=$sort, active=1");
        $stats['items']++;
    }
}

/* ③ الإطفاء: الحيُّ خارج المستهدف — لأدوار الإدارات المبذورة وحدَها */
$rolesSeeded = array();
foreach (target_dept_sheets() as $dept => $i) { $r = target_dept_role($dept); if ($r) $rolesSeeded[] = $r; }
$in = implode(',', $rolesSeeded);
$r = mysqli_query($conn, "SELECT id, role_id, route FROM nav_items WHERE active=1 AND role_id IN ($in)");
while ($x = mysqli_fetch_assoc($r)) {
    $k = $x['role_id'] . ':' . strtolower(trim($x['route']));
    if (isset($targetLive[$k])) continue;
    if (in_array(strtolower(trim($x['route'])), array_map('strtolower', $GLOBALS['keepAlways'] ?? array('main/my_workspace.php')), true)) continue;
    mysqli_query($conn, "UPDATE nav_items SET active=0 WHERE id=" . intval($x['id']));
    $stats['deact']++;
}

foreach ($stats as $k => $v) echo "  $k: $v\n";
$r = mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE active=1");
echo "الروابطُ الحيةُ الآن: " . mysqli_fetch_assoc($r)['c'] . "\n";
