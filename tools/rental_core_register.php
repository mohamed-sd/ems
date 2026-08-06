<?php
/**
 * tools/rental_core_register.php — تسجيلُ RENTAL-CORE في النظام · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثُ شاشاتٍ جديدةٍ لا تُحرَس ولا تُرى حتى تُسجَّل في ثلاثة مواضع:
 *   ① `modules`          — وإلا مرّ الحارسُ المركزي عليها شفافًا (درسُ ح-01).
 *   ② `role_permissions` — صلاحيةُ دور المبيعات (12) عليها.
 *   ③ `nav_items`        — بابُها في القائمة الجانبية.
 * ومالكُها الدور 12 صراحةً — فحلُّ المسار يُفضّل موديولَ الدور (درسُ ح-16).
 *
 * php tools/rental_core_register.php            → تجريب
 * php tools/rental_core_register.php --apply    → تنفيذ
 * php tools/rental_core_register.php --revert   → تراجع
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROLE = 12;

/** الشاشاتُ الثلاث: [code, name, icon, door, group_id, sort, label] */
$SCREENS = array(
    array('Operations/fleet_calendar.php',   'تقويم الأسطول والحجز',      'fa fa-calendar-check', 'DAILY', 3414, 5,  'تقويمُ الأسطول والحجز'),
    array('Clients/rate_books.php',          'دفتر الأسعار بالشرائح',     'fa fa-book-open',      'DAILY', 3417, 5,  'دفترُ الأسعار بالشرائح'),
    array('Operations/fleet_utilization.php','استغلال الأسطول ومردوده',   'fa fa-gauge-high',     'DAILY', 3426, 5,  'استغلالُ الأسطول ومردودُه'),
);

$o('══ تسجيلُ RENTAL-CORE ══');

if ($REVERT) {
    foreach ($SCREENS as $s) {
        $code = $s[0];
        $m = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM modules WHERE code='" . mysqli_real_escape_string($conn, $code) . "' AND owner_role_id=$ROLE"));
        if ($m) {
            mysqli_query($conn, "DELETE FROM nav_items WHERE module_id=" . (int) $m['id']);
            mysqli_query($conn, "DELETE FROM role_permissions WHERE module_id=" . (int) $m['id']);
            mysqli_query($conn, "DELETE FROM modules WHERE id=" . (int) $m['id']);
            $o('  نُزع: ' . $code);
        }
    }
    exit(0);
}

$plan = array();
foreach ($SCREENS as $s) {
    list($code, $name, $icon, $door, $gid, $sort, $label) = $s;
    $m = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM modules WHERE code='" . mysqli_real_escape_string($conn, $code) . "' AND owner_role_id=$ROLE"));
    $hasM = $m ? (int) $m['id'] : 0;
    $hasP = 0; $hasN = 0;
    if ($hasM) {
        $hasP = (int) reset(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM role_permissions WHERE role_id=$ROLE AND module_id=$hasM")));
        $hasN = (int) reset(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE role_id=$ROLE AND module_id=$hasM")));
    }
    $o(sprintf('  %-40s موديول=%-6s صلاحية=%-3s قائمة=%s', $code, $hasM ?: 'لا', $hasP ?: 'لا', $hasN ?: 'لا'));
    $plan[] = compact('code', 'name', 'icon', 'door', 'gid', 'sort', 'label', 'hasM', 'hasP', 'hasN');
}

if (!$APPLY) { $o('  (تجريبٌ — أعِد التشغيل بـ --apply)'); exit(0); }

$o('');
$o('── التنفيذ');
foreach ($plan as $p) {
    // ① الموديول
    $mid = $p['hasM'];
    if (!$mid) {
        $st = $conn->prepare("INSERT INTO modules (name, code, icon, owner_role_id, is_link) VALUES (?,?,?,?,1)");
        $st->bind_param('sssi', $p['name'], $p['code'], $p['icon'], $ROLE);
        $st->execute(); $mid = (int) $conn->insert_id; $st->close();
        $o('  + موديول #' . $mid . ' ' . $p['code']);
    }
    // ② الصلاحية — عرضٌ وإضافةٌ وتعديل؛ لا حذفَ (الحجزُ يُلغى لا يُحذف)
    if (!$p['hasP']) {
        $st = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?,?,1,1,1,0)");
        $st->bind_param('ii', $ROLE, $mid);
        $st->execute(); $st->close();
        $o('  + صلاحية دور ' . $ROLE . ' على #' . $mid . ' (v1 a1 e1 d0)');
    }
    // ③ القائمة
    if (!$p['hasN']) {
        $route = '../' . $p['code'];
        $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at, updated_at)
                              VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),NOW())");
        $st->bind_param('isiisssis', $ROLE, $p['door'], $p['gid'], $mid, $p['label'], $route, $p['icon'], $p['sort'], $p['code']);
        $st->execute(); $st->close();
        $o('  + عنصرُ قائمةٍ في باب ' . $p['door'] . ' مجموعة ' . $p['gid']);
    }
}

$o('');
$o('── التحقق');
foreach ($SCREENS as $s) {
    $code = $s[0];
    $q = mysqli_query($conn, "SELECT m.id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete, n.active
        FROM modules m
        LEFT JOIN role_permissions rp ON rp.module_id=m.id AND rp.role_id=$ROLE
        LEFT JOIN nav_items n ON n.module_id=m.id AND n.role_id=$ROLE
        WHERE m.code='" . mysqli_real_escape_string($conn, $code) . "' AND m.owner_role_id=$ROLE");
    $r = mysqli_fetch_assoc($q);
    $o(sprintf('  %-40s %s', $code, $r
        ? ('#' . $r['id'] . ' v' . (int)$r['can_view'] . ' a' . (int)$r['can_add'] . ' e' . (int)$r['can_edit']
           . ' d' . (int)$r['can_delete'] . ' · قائمة=' . ((int)$r['active'] === 1 ? 'نشطة ✓' : 'لا'))
        : '✗ غير مسجَّل'));
}
