<?php
/**
 * tools/u9_nav09_v5_links.php — تطبيقُ إضافةِ v5 وحدَها على المولَّد (DEC-B)
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا لا nav09_import --apply؟ لأنه يعيد بناءَ n9s كلِّه وفرقُه يحمل −233
 * إزالةً محظورةً بقرار مالكٍ قائم (سجل CMP-03). فتُطبَّق إضافةُ التأجير الموجهةُ
 * وحدَها بأعراف المستورد حرفيًّا:
 *   ① مجموعةٌ مرحليةٌ n9s08_9_<gi>_r12 باسم «التأجير والحجز» (display=900+gi)
 *   ② ثلاثةُ روابطَ بأيقوناتها من nav_icon_map وربطِها بالصلاحية (nav09_perm_link)
 *   ③ صفوفُ العرض screen_view_rows كما يكتبها المستورد
 *   ④ تعطيلُ الروابطِ اليدويةِ القديمةِ المكررة (nav 6802–6804 في g3/g10)
 * idempotent · dry-run افتراضيًّا.
 *
 * php tools/u9_nav09_v5_links.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once __DIR__ . '/nav09_read.php';
require_once dirname(__DIR__) . '/includes/nav_icon_map.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$ROLE = 12; $DEPT_NO = 8; $STAGE = 9;
$GROUP_NAME = 'التأجير والحجز';
$STAGE_TITLE = 'تاسعًا: التأجير قصير الأمد';

/* الشاشات من الوثيقة v5 نفسِها — لا قائمة مستقلة (مصدرُ الحقيقة الواحد) */
$doc = Nav09Reader::load(dirname(__DIR__) . '/docs/files/NAV-09-current.xlsx');
$rows = array();
foreach ($doc['depts'][$DEPT_NO]['rows'] as $r) {
    if ($r['kind'] === 'screen' && $r['stage'] === $STAGE && $r['group'] === $GROUP_NAME) { $rows[] = $r; }
}
if (count($rows) !== 3) { $o('✘ الوثيقةُ لا تحمل شاشاتِ التأجير الثلاثَ في المرحلة 9 (وجد: ' . count($rows) . ')'); exit(1); }

$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, real_path FROM nav09_file_map WHERE real_path IS NOT NULL");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x['real_path']; }

$o('══ v5 → المولَّد — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');
$conn->begin_transaction();
try {
    /* ① المجموعة */
    $r = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code LIKE 'n9s08\\_9\\_%\\_r12' AND name = '" . mysqli_real_escape_string($conn, $GROUP_NAME) . "'");
    $g = mysqli_fetch_assoc($r);
    if ($g) {
        $gid = (int) $g['id'];
        $o("= المجموعةُ قائمة #$gid");
    } else {
        $r = mysqli_query($conn, "SELECT COUNT(*) c FROM link_groups WHERE group_code LIKE 'n9s08\\_%\\_r12'");
        $gi = intval(mysqli_fetch_assoc($r)['c']) + 1;
        $code = sprintf('n9s%02d_%d_%d_r%d', $DEPT_NO, $STAGE, $gi, $ROLE);
        $o("+ مجموعةٌ $code «{$GROUP_NAME}» display=" . ($STAGE * 100 + $gi));
        if ($APPLY) {
            mysqli_query($conn, sprintf(
                "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                 VALUES ('%s', '%s', %d, 'fa fa-layer-group', %d, %d, '%s', 1)",
                mysqli_real_escape_string($conn, $GROUP_NAME), $code, $ROLE,
                $STAGE * 100 + $gi, $STAGE,
                mysqli_real_escape_string($conn, $STAGE_TITLE))) or throw new RuntimeException(mysqli_error($conn));
            $gid = mysqli_insert_id($conn);
        } else { $gid = -1; }
    }

    /* ② الروابط */
    $si = 0;
    foreach ($rows as $row) {
        $si++;
        $route = $map[$row['file']];
        $pl = nav09_perm_link($conn, $route);
        $r = mysqli_query($conn, "SELECT id FROM nav_items WHERE role_id = $ROLE AND route = '" . mysqli_real_escape_string($conn, $route) . "' AND group_id = " . ($gid > 0 ? $gid : 0));
        if ($gid > 0 && mysqli_fetch_assoc($r)) { $o("= رابطٌ قائم: {$row['title']}"); continue; }
        $o("+ رابطٌ {$row['title']} → $route (module=" . ($pl['module_id'] ?? '—') . ')');
        if ($APPLY) {
            mysqli_query($conn, sprintf(
                "INSERT INTO nav_items (role_id, door, group_id, label_ar, route, icon, sort_order, active, module_id, permission_code)
                 VALUES (%d, 'DAILY', %d, '%s', '%s', '%s', %d, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE group_id = %d, label_ar = VALUES(label_ar), door = 'DAILY',
                     icon = VALUES(icon), sort_order = VALUES(sort_order), active = 1,
                     module_id = VALUES(module_id), permission_code = VALUES(permission_code)",
                $ROLE, $gid,
                mysqli_real_escape_string($conn, $row['title']),
                mysqli_real_escape_string($conn, $route),
                mysqli_real_escape_string($conn, ems_nav_icon_for($row['title'], $route)),
                $si,
                $pl['module_id'] === null ? 'NULL' : (int) $pl['module_id'],
                $pl['permission_code'] === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $pl['permission_code']) . "'",
                $gid)) or throw new RuntimeException(mysqli_error($conn));
        }
    }

    /* ③ صفوف العرض */
    foreach ($rows as $row) {
        $route = $map[$row['file']];
        $q = sprintf(
            "INSERT INTO screen_view_rows (screen_name, canonical_file, route, dept, role_id, role_kind,
                 scope_text, angle, columns_text, filters_text, allowed_text, blocked_text, nav_group, active)
             VALUES ('%s', '%s', '%s', 'المبيعات والعقود', %d, 'مالك', '%s', '%s', '', '', '%s', '%s', '%s', 1)
             ON DUPLICATE KEY UPDATE svr_id = svr_id",
            mysqli_real_escape_string($conn, $row['title']),
            mysqli_real_escape_string($conn, $row['file']),
            mysqli_real_escape_string($conn, $route),
            $ROLE,
            mysqli_real_escape_string($conn, $row['scope']),
            mysqli_real_escape_string($conn, $row['angle']),
            mysqli_real_escape_string($conn, $row['allowed']),
            mysqli_real_escape_string($conn, $row['blocked']),
            mysqli_real_escape_string($conn, $GROUP_NAME));
        if ($APPLY) { mysqli_query($conn, $q) or throw new RuntimeException(mysqli_error($conn)); }
    }
    $o('③ صفوفُ العرض: 3 (idempotent)');

    /* ④ تعطيل اليدوية المكررة */
    $r = mysqli_query($conn,
        "SELECT ni.id, ni.label_ar FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
          WHERE ni.role_id = $ROLE AND ni.active = 1 AND lg.group_code NOT LIKE 'n9s%'
            AND (ni.route LIKE '%fleet_calendar%' OR ni.route LIKE '%rate_books%' OR ni.route LIKE '%fleet_utilization%')");
    $olds = array();
    while ($x = mysqli_fetch_assoc($r)) { $olds[] = $x; }
    foreach ($olds as $x) { $o("− تعطيلُ اليدوي #{$x['id']} «{$x['label_ar']}»"); }
    if ($APPLY && $olds) {
        $ids = implode(',', array_map(function ($x) { return (int) $x['id']; }, $olds));
        mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id IN ($ids)") or throw new RuntimeException(mysqli_error($conn));
    }

    if ($APPLY) { $conn->commit(); $o('✔ COMMITTED'); }
    else        { $conn->rollback(); $o('— dry-run: لا تغيير'); }
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}
