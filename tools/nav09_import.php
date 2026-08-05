<?php
/**
 * tools/nav09_import.php — مستوردُ NAV-09: القوائمُ من الورقة 98 (حكم ١٤)
 * ───────────────────────────────────────────────────────────────────────────
 * «القوائمُ من الورقة 98 وربطُ الأحداث من الورقة 97 — ولا يُكتب أيٌّ منهما
 * في الكود.» هذا المستوردُ يجعل المصنّفَ مصدرَ الحقيقة:
 *
 *   --diff   يقرأ المصنّفَ ويقارن بالمولَّد الحالي ويطبع ما سيتغير — ولا يمسّ شيئًا
 *   --apply  يولّد: صفوفَ العرض بالرباعية (784) · ومجموعاتِ المراحل ·
 *            وروابطَ القوائم لكل أدوار الإدارة — والرابطُ بحسب القاموس
 *            (حيًّا أو «قريبًا») · آمنُ الإعادة (يمسح مولَّدَه ويعيد بناءه)
 *
 * الوثيقةُ المحدثةُ القادمة = نسخُ الملف الجديد ثم --diff ثم --apply.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';
require_once __DIR__ . '/../includes/nav_icon_map.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);

/* أدوارُ كل إدارةٍ — معكوسُ خريطة dept_inbox المعتمدة (الدورُ الأول أساسي) */
$DEPT_ROLES = array(
    'الإدارة التنفيذية' => array(9), // الفجوة 9 التاريخية سُدت بها (NAV-09 v4) — الحساب بيد المالك
    'إدارة الموقع'      => array(6, 7),
    'إدارة التشغيل'     => array(1),
    'إدارة الصيانة'     => array(13, 14),
    'النقل والترحيل'    => array(23),
    'المشتريات'         => array(16),
    'المخازن'           => array(25),
    'المبيعات والعقود'  => array(12),
    'إدارة الموردين'    => array(2, 8),
    'القوى التشغيلية'   => array(27),
    'إدارة الأسطول'     => array(3, 10),
    'الموارد البشرية'   => array(4),
    'المالية والخزينة'  => array(17, 18, 19, 20, 21, 22),
    'التمويل والملكية'  => array(26),
    'مركز البلاغات'     => array(24),
    'الحوكمة والالتزام' => array(15),
);

/* كتمُ المالك: روابطُ يقرر المالكُ إسقاطَها من قائمة دورٍ بعينه (تصمد أمام التوليد)
   2026-08-03: الصندوق الجامع يُسقط من التشغيل — «ما ينتظر اعتمادي» الحلقي يغني عنه */
$SUPPRESS = array(1 => array('approvals_inbox.php'));

$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');

/* القاموس: قانوني → مسارُ الوجهة الفعلي */
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }
$hidden = array(); // «قريبًا» التي أخفاها المالك (owner-hide) — لا يولَّد رابطُها حتى تُبنى
$r = mysqli_query($conn, "SELECT canonical_file FROM nav09_file_map WHERE note = 'owner-hide' AND state = 'soon'");
while ($x = mysqli_fetch_row($r)) { $hidden[$x[0]] = 1; }
$routeOf = function ($cf) use ($map) {
    if (isset($map[$cf]) && $map[$cf]['state'] !== 'soon' && $map[$cf]['real_path'] !== null) {
        return $map[$cf]['real_path'];
    }
    return 'main/soon.php?screen=' . $cf;
};

/* ── الفرق: مصفوفةُ المصنّف مقابل صفوف العرض المولَّدة ─────────────────── */
$newKeys = array();
foreach ($doc['matrix'] as $m) { $newKeys[$m['file'] . '⊕' . $m['viewer']] = $m; }
$oldKeys = array();
$r = mysqli_query($conn, "SELECT canonical_file, dept, scope_text, angle, allowed_text, blocked_text
                          FROM screen_view_rows WHERE canonical_file IS NOT NULL AND active = 1");
while ($x = mysqli_fetch_assoc($r)) { $oldKeys[$x['canonical_file'] . '⊕' . $x['dept']] = $x; }
$added = array_diff_key($newKeys, $oldKeys);
$removed = array_diff_key($oldKeys, $newKeys);
$changed = array();
foreach (array_intersect_key($newKeys, $oldKeys) as $k => $m) {
    $o = $oldKeys[$k];
    if ($o['scope_text'] !== $m['scope'] || $o['angle'] !== $m['angle']
        || $o['allowed_text'] !== $m['allowed'] || $o['blocked_text'] !== $m['blocked']) { $changed[$k] = 1; }
}
echo "الفرق عن المولَّد الحالي: +" . count($added) . " ظهورًا جديدًا · −" . count($removed) . " يُزال · ~" . count($changed) . " تتغير رباعيتُه\n";
if (!$APPLY) {
    foreach (array_slice(array_keys($added), 0, 15) as $k) { echo "  + $k\n"; }
    foreach (array_slice(array_keys($removed), 0, 15) as $k) { echo "  − $k\n"; }
    echo "(معاينةٌ — التطبيق بـ --apply)\n";
    exit(0);
}

/* ── التطبيق ① صفوفُ العرض: تعطيلُ القديم غير القانوني وبناءُ 784 ─────── */
mysqli_query($conn, "UPDATE screen_view_rows SET active = 0 WHERE canonical_file IS NULL");
mysqli_query($conn, "DELETE FROM screen_view_rows WHERE canonical_file IS NOT NULL");
$primaryRole = function ($dept) use ($DEPT_ROLES) {
    return isset($DEPT_ROLES[$dept]) ? $DEPT_ROLES[$dept][0] : null;
};
$n = 0;
foreach ($doc['matrix'] as $m) {
    $rid = $primaryRole($m['viewer']);
    $ridSql = $rid === null ? 'NULL' : $rid;
    $kind = ($m['owner'] === $m['viewer']) ? 'مالك' : 'عارض';
    $q = sprintf(
        "INSERT INTO screen_view_rows (screen_name, canonical_file, route, dept, role_id, role_kind,
             scope_text, angle, columns_text, filters_text, allowed_text, blocked_text, nav_group, active)
         VALUES ('%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', '', '', '%s', '%s', '', 1)
         ON DUPLICATE KEY UPDATE scope_text = VALUES(scope_text), angle = VALUES(angle),
             allowed_text = VALUES(allowed_text), blocked_text = VALUES(blocked_text)",
        mysqli_real_escape_string($conn, $m['title']),
        mysqli_real_escape_string($conn, $m['file']),
        mysqli_real_escape_string($conn, $routeOf($m['file'])),
        mysqli_real_escape_string($conn, $m['viewer']),
        $ridSql, $kind,
        mysqli_real_escape_string($conn, $m['scope']),
        mysqli_real_escape_string($conn, $m['angle']),
        mysqli_real_escape_string($conn, $m['allowed']),
        mysqli_real_escape_string($conn, $m['blocked']));
    mysqli_query($conn, $q) or die('✘ svr: ' . mysqli_error($conn) . "\n");
    $n++;
}
/* أوراقُ الإدارات تعلن ظهوراتٍ برباعيتها قد تغفلها المصفوفة 98 — الاتحادُ يجمعهما
   (المصفوفةُ أولى عند التطابق، والورقةُ تسدّ ما أغفلته) */
$extra = 0;
foreach ($doc['depts'] as $dept) {
    foreach ($dept['rows'] as $row) {
        if ($row['kind'] !== 'screen') { continue; }
        $rid = $primaryRole($dept['name']); $ridSql = $rid === null ? 'NULL' : $rid;
        $q = sprintf(
            "INSERT INTO screen_view_rows (screen_name, canonical_file, route, dept, role_id, role_kind,
                 scope_text, angle, columns_text, filters_text, allowed_text, blocked_text, nav_group, active)
             VALUES ('%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', '', '', '%s', '%s', '%s', 1)
             ON DUPLICATE KEY UPDATE svr_id = svr_id",
            mysqli_real_escape_string($conn, $row['title']),
            mysqli_real_escape_string($conn, $row['file']),
            mysqli_real_escape_string($conn, $routeOf($row['file'])),
            mysqli_real_escape_string($conn, $dept['name']),
            $ridSql,
            mysqli_real_escape_string($conn, $row['role'] === 'مالك' ? 'مالك' : 'عارض'),
            mysqli_real_escape_string($conn, $row['scope']),
            mysqli_real_escape_string($conn, $row['angle']),
            mysqli_real_escape_string($conn, $row['allowed']),
            mysqli_real_escape_string($conn, $row['blocked']),
            mysqli_real_escape_string($conn, (string) $row['group']));
        mysqli_query($conn, $q) or die('✘ svr2: ' . mysqli_error($conn) . "\n");
        if (mysqli_affected_rows($conn) === 1) { $extra++; }
    }
}
echo "① صفوفُ العرض: $n من المصفوفة + $extra أكملتها أوراقُ الإدارات\n";

/* ── التطبيق ② القوائم: مجموعاتُ المراحل وروابطُها لكل أدوار الإدارة ──── */
/* مسحُ المولَّد السابق (المعلَّم n9s) ثم إعادةُ البناء */
/* «أخرى» (n9s99) ملكُ الكنّاس وقرارِ المالك — لا يمسّها التوليد */
mysqli_query($conn, "DELETE ni FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
                     WHERE lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
mysqli_query($conn, "DELETE FROM link_groups WHERE group_code LIKE 'n9s%' AND group_code NOT LIKE 'n9s99_others%'");

$groups = 0; $links = 0; $soonLinks = 0;
foreach ($doc['depts'] as $deptNo => $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; } // «مساحة عملي» بابٌ لا قائمة
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        $currentGroup = null; $gid = null; $gi = 0; $si = 0;
        $usedRoutes = array(); // الوثيقةُ تكرر الشاشةَ عمدًا عبر المجموعات — التكرارُ بمرساةٍ يكسر التفرد
        foreach ($dept['rows'] as $row) {
            $gkey = $row['stage'] . '⊕' . $row['group'];
            if ($gkey !== $currentGroup) {
                $currentGroup = $gkey; $gi++;
                $stage = isset($dept['stages'][$row['stage']]) ? $dept['stages'][$row['stage']] : array('title' => '');
                $stitle = preg_replace('/^[\d\s·◇]+/u', '', (string) $stage['title']);
                $gname = $row['group'] !== null ? $row['group'] : 'عام';
                $code = sprintf('n9s%02d_%d_%d_r%d', $deptNo, $row['stage'], $gi, $role);
                mysqli_query($conn, sprintf(
                    "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                     VALUES ('%s', '%s', %d, 'fa fa-layer-group', %d, %d, '%s', 1)",
                    mysqli_real_escape_string($conn, $gname), $code, $role,
                    $row['stage'] * 100 + $gi, $row['stage'],
                    mysqli_real_escape_string($conn, $stitle))) or die('✘ lg: ' . mysqli_error($conn) . "\n");
                $gid = mysqli_insert_id($conn);
                $groups++;
                $si = 0;
            }
            if ($row['kind'] !== 'screen') { continue; } // الأفعالُ تستوردها أداة 97
            if (isset($hidden[$row['file']])) { continue; } // أخفاها المالك — بلا رابطٍ حتى تُبنى
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; } // كتمُ المالك لهذا الدور
            $si++;
            $route = $routeOf($row['file']);
            if (strpos($route, 'main/soon.php') === 0) { $soonLinks++; }
            if (isset($usedRoutes[$route])) { // ظهورٌ ثانٍ مقصودٌ للشاشة نفسِها في القائمة
                // المرساةُ بموضع الصف كاملًا (مجموعة×تسلسل) — فمواءمتان لمسارٍ واحدٍ
                // داخل المجموعة نفسِها لا تتصادمان (قيست: distribution+op_assign)
                $route .= '#n9g' . $gi . 'i' . $si;
            }
            $usedRoutes[$route] = 1;
            // التصادمُ مع رابطٍ قديمٍ لنفس الدور = تبنّيه في بنية المولَّد لا خطأ
            // E-03 UX-07: الربطُ بالصلاحية من القاعدة الواحدة في nav09_read
            $pl = nav09_perm_link($conn, $route);
            mysqli_query($conn, sprintf(
                "INSERT INTO nav_items (role_id, door, group_id, label_ar, route, icon, sort_order, active, module_id, permission_code)
                 VALUES (%d, 'DAILY', %d, '%s', '%s', '%s', %d, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE group_id = %d, label_ar = VALUES(label_ar), door = 'DAILY',
                     icon = VALUES(icon), sort_order = VALUES(sort_order), active = 1,
                     module_id = VALUES(module_id), permission_code = VALUES(permission_code)",
                $role, $gid,
                mysqli_real_escape_string($conn, $row['title']),
                mysqli_real_escape_string($conn, $route),
                mysqli_real_escape_string($conn, ems_nav_icon_for($row['title'], $route)),
                $si,
                $pl['module_id'] === null ? 'NULL' : (int) $pl['module_id'],
                $pl['permission_code'] === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $pl['permission_code']) . "'",
                $gid)) or die('✘ ni: ' . mysqli_error($conn) . "\n");
            $links++;
        }
    }
}
echo "② القوائم: $groups مجموعةً مرحليةً · $links رابطًا (منها $soonLinks «قريبًا») لأدوار الإدارات الخمس عشرة\n";
echo "✔ اكتمل — شغّل كنّاس «أخرى» ثم تحقق بصريًّا\n";
