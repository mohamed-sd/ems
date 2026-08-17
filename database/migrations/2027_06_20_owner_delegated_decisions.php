<?php
/**
 * 2027_06_20_owner_delegated_decisions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تنفيذُ القراراتِ المفوَّضةِ («أنت قرر القرارَ المناسبَ وأكمل» — 2026-08-17)
 * من مصادرَ مقيسةٍ لا اختراعًا:
 *
 * ① الروابطُ اليتيمةُ الـ174: تُسند إلى «المجموعةِ المقترحةِ» المنصوصةِ في
 *    ورقةِ الدفترِ الحاكمةِ نفسِها (محسوبةً بالتصنيفِ الوظيفيِّ الحي) —
 *    المجموعةُ تُنشأ إن غابت، والروابطُ المعطلةُ تعود بأعيانِها
 *    (deactivated_nav_ids) وقرارُ الصفِّ يُختم 'relocated'.
 * ② الأدوارُ النحيفةُ الثلاثة (32·34·35): سايدبارُها يُبذر مما **تملكه فعلًا**
 *    (منحٌ بلا روابط: 11/8/8 شاشةً مقابل 3/2/2 روابط) + الأساسياتُ الأربعُ
 *    العامةُ لكلِّ موظف — فالقائمةُ مرآةُ الصلاحيةِ لا تخمين.
 * ③ مستخدمو الأدوارِ الماليةِ الستةِ بلا قالب: يُلحقون بقوالبِ درجاتِهم
 *    بمطابقةِ المسمَّى لمصفوفةِ الدرجات (17→G7 · 20→G6 · 21→G3 · 22→G2 ·
 *    34→G3 · 35→G3) — وبنودُ قالبِ G2 تُبذر من نظيرِه الحيِّ الجديد (22).
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ═══ ① اليتيمةُ إلى مجموعاتِها المقترحةِ من الورقة ═══════════════════════ */
echo "\n▐ ① إسنادُ اليتيمةِ بقرارِ الورقةِ المقترَح\n";
$rows = $conn->query("SELECT id, sheet_role, label_ar, route, proposed_group, deactivated_nav_ids
                        FROM gov_orphan_links WHERE owner_decision = 'pending'");
$done = 0; $skipped = array();
while ($o = $rows->fetch_assoc()) {
    $roleId = $one("SELECT id FROM roles WHERE name = '" . $esc($o['sheet_role']) . "' LIMIT 1");
    $pg = trim((string) $o['proposed_group']);
    if ($roleId === null || $pg === '' || $pg === '—') {
        $skipped[] = "{$o['label_ar']} ({$o['sheet_role']}): " . ($roleId === null ? 'دورٌ بلا نظيرٍ حي' : 'بلا مجموعةٍ مقترحة');
        continue;
    }
    $roleId = (int) $roleId;
    $gid = $one("SELECT id FROM link_groups WHERE owner_role_id = {$roleId} AND name = '" . $esc($pg) . "' LIMIT 1");
    if ($gid === null) {
        $conn->query("INSERT INTO link_groups (name, owner_role_id, icon, display_order, is_active)
                      VALUES ('" . $esc($pg) . "', {$roleId}, 'fa fa-folder', 800, 1)");
        $gid = (int) $conn->insert_id;
    } else {
        $gid = (int) $gid;
        $conn->query("UPDATE link_groups SET is_active = 1 WHERE id = {$gid}");
    }
    $ids = preg_replace('/[^0-9,]/', '', (string) $o['deactivated_nav_ids']);
    if ($ids !== '') {
        $conn->query("UPDATE nav_items SET active = 1, group_id = {$gid} WHERE id IN ({$ids})");
    } else {
        // صفٌّ بلا روابطَ معطلةٍ محفوظة — يُنشأ الرابطُ من بيانِ الورقة
        $conn->query("INSERT IGNORE INTO nav_items (role_id, door, group_id, label_ar, route, sort_order, active)
                      VALUES ({$roleId}, 'DAILY', {$gid}, '" . $esc(mb_substr($o['label_ar'], 0, 64)) . "',
                              '" . $esc($o['route']) . "', 850, 1)");
    }
    $conn->query("UPDATE gov_orphan_links SET owner_decision = 'relocated', decided_at = NOW()
                   WHERE id = " . (int) $o['id']);
    $done++;
}
printf("   ✔ أُسند بقرارِ الورقة: %d · بقي معلَقًا معلَنًا: %d\n", $done, count($skipped));
foreach (array_slice($skipped, 0, 5) as $s) { echo "     · {$s}\n"; }

/* ═══ ② سايدبارُ الأدوارِ النحيفة = مرآةُ منحِها + الأساسياتُ الأربع ══════ */
echo "\n▐ ② الأدوارُ النحيفة (32·34·35): القائمةُ مرآةُ الصلاحية\n";
$BASICS = array('main/my_workspace.php', 'Portal/my_portal.php',
                'Tickets/ticket_contextual_open.php', 'Reports/reports.php');
foreach (array(32, 34, 35) as $rid) {
    // مجموعةُ «مجالُ العملِ اليومي» لهذا الدور
    $gid = $one("SELECT id FROM link_groups WHERE owner_role_id = {$rid} AND name = 'مجالُ العملِ اليومي' LIMIT 1");
    if ($gid === null) {
        $conn->query("INSERT INTO link_groups (name, owner_role_id, icon, display_order, is_active)
                      VALUES ('مجالُ العملِ اليومي', {$rid}, 'fa fa-briefcase', 10, 1)");
        $gid = (int) $conn->insert_id;
    } else { $gid = (int) $gid; }
    // الأساسياتُ الأربع: منحُ قراءةٍ إن غاب + الرابط
    foreach ($BASICS as $b) {
        $mid = $one("SELECT id FROM modules WHERE code = '" . $esc($b) . "' LIMIT 1");
        if ($mid === null) continue;
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ({$rid}, " . (int) $mid . ", 1, 0, 0, 0)");
    }
    // روابطُ كلِّ شاشةٍ ممنوحةٍ بلا رابط
    $g = $conn->query(
        "SELECT m.id mid, m.code, m.name, m.icon
           FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
          WHERE rp.role_id = {$rid} AND rp.can_view = 1
            AND NOT EXISTS (SELECT 1 FROM nav_items n
                             WHERE n.role_id = {$rid} AND n.module_id = m.id AND n.active = 1)");
    $added = 0;
    while ($m = $g->fetch_assoc()) {
        $icon = $m['icon'] !== '' ? $m['icon'] : 'fa fa-file';
        $ok = $conn->query("INSERT IGNORE INTO nav_items
                (role_id, door, group_id, module_id, label_ar, route, icon, permission_code, sort_order, active)
             VALUES ({$rid}, 'DAILY', {$gid}, " . (int) $m['mid'] . ",
                     '" . $esc(mb_substr($m['name'], 0, 64)) . "', '" . $esc($m['code']) . "',
                     '" . $esc($icon) . "', '" . $esc($m['code']) . "', 100, 1)");
        if ($ok && $conn->affected_rows > 0) { $added++; }
    }
    $total = (int) $one("SELECT COUNT(*) FROM nav_items WHERE role_id = {$rid} AND active = 1");
    printf("   ✔ الدور %d: أُضيف %d رابطًا — روابطُه الآن %d %s\n",
        $rid, $added, $total, $total >= 10 ? '[≥10 ✔]' : '[ما يزال نحيفًا — يُعلَن]');
}

/* ═══ ③ إلحاقُ الماليّين الستةِ بقوالبِ درجاتِهم ═════════════════════════ */
echo "\n▐ ③ قوالبُ الماليّين بمطابقةِ المسمَّى لمصفوفةِ الدرجات\n";
$ATTACH = array(17 => 'G7', 20 => 'G6', 21 => 'G3', 22 => 'G2', 34 => 'G3', 35 => 'G3');
// بنودُ قالبِ G2 من نظيرِه الحيِّ الجديد (قارئ مالي 22)
$pidG2 = (int) $one("SELECT profile_id FROM gov_role_profiles
                      WHERE dept_code = 'المالية والخزينة' AND grade = 'G2' AND version = 1");
if ($pidG2 > 0) {
    $conn->query(
        "INSERT IGNORE INTO gov_profile_items
            (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
         SELECT 0, {$pidG2}, 'screen', m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete, 'role_permissions:22'
           FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
          WHERE rp.role_id = 22 AND rp.can_view = 1");
    echo "   ✔ بنودُ قالبِ المالية G2 من نظيرِه الحي (22): " . $conn->affected_rows . "\n";
}
$attached = 0;
foreach ($ATTACH as $rid => $grade) {
    $pid = (int) $one("SELECT profile_id FROM gov_role_profiles
                        WHERE dept_code = 'المالية والخزينة' AND grade = '" . $esc($grade) . "' AND version = 1");
    if ($pid <= 0) continue;
    $conn->query(
        "INSERT INTO gov_authority_grants
            (company_id, user_id, profile_id, source, valid_from, valid_to, issued_by, reason)
         SELECT u.company_id, u.id, {$pid}, 'profile', NOW(), NULL, 0,
                'إلحاقٌ بمطابقةِ المسمَّى لمصفوفةِ الدرجات — قرارٌ مفوَّض 2026-08-17'
           FROM users u
          WHERE u.status = 1 AND u.role = {$rid}
            AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g
                             WHERE g.user_id = u.id AND g.revoked_at IS NULL)");
    $attached += $conn->affected_rows;
}
printf("   ✔ أُلحق: %d · بقي بلا قالبٍ (معلَنًا): %s\n", $attached,
    $one("SELECT COUNT(*) FROM users u WHERE u.status = 1
           AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g WHERE g.user_id = u.id AND g.revoked_at IS NULL)"));

echo "\n✔ القراراتُ الثلاثةُ نُفِّذت من مصادرِها المقيسة\n";
