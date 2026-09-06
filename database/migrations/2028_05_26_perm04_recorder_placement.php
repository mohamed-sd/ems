<?php
/**
 * 2028_05_26_perm04_recorder_placement.php — موضعُ مسجِّلِ الاعتمادِ في المساحة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **تتمّةُ عطبٍ لي كشفه الفحصُ في المتصفّح**: سجّلتُ الشاشةَ في `modules`
 *   و`nav_items` و`role_permissions` — **ولم أضع لها موضعَ مساحة**. وسايدبارُ
 *   هذه الأدوارِ يُصيَّر من `nav_workspace_placements` حصرًا، فالشاشةُ
 *   **يفتحها الحارسُ ولا يقود إليها رابطٌ في الشاشة** — وهو عينُ العطبِ الذي
 *   جاءت هذه الحملةُ كلُّها لتعالجه، وقعتُ فيه وأنا أعالجه.
 *
 * ◆ **ومساحاتُ الأدوارِ الأربعةِ اثنتان**: 18 و31 و34 في `DEP-05` · و21 في
 *   `DEP-06`. والسجلُّ يقبل المسارَ في أكثرَ من مساحةٍ (‏`approvals/requests`
 *   في أربع)، فيُوضَع موضعان لا واحد.
 *
 * ◆ **والمجموعةُ تُقرأ من جيرانِها في المساحةِ نفسِها**: «العمل اليومي» —
 *   36 في `DEP-05` و43 في `DEP-06`. وأرقامُ مجموعاتِ المواضعِ غيرُ أرقامِ
 *   مجموعاتِ `nav_items`، فلا يُنقَل رقمٌ بين السجلَّين.
 *
 * ⚠ **وأختُها `acc_approval_chain` بلا موضعٍ أيضًا** — عطبٌ سابقٌ لي لا أمسُّه
 *   هنا: إضافتُه قرارُ ملاحةٍ لصاحبِ الشاشة، ويُسمّى ولا يُطوى.
 *
 * التشغيل: php database/migrations/2028_05_26_perm04_recorder_placement.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/navarch_renderer.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$ROUTE = 'finance/acc_approval_record';
$WITHPHP = 'finance/acc_approval_record.php';
$LABEL = 'تسجيل قرار الاعتماد';
$SCR = 'SCR-0952';
$SRC = 'PERM-04 — الشاشة كانت مسجلة ومحروسة وبلا موضع، فلا رابط يقود اليها';
$REF = 'قياس في المتصفح: الحارس يفتح والسايدبار لا يعرض';

$PLACES = array(
    array('ws' => 'DEP-05', 'group' => 36, 'sort' => 60, 'target' => 'NT-DEP-05-901', 'row' => 901),
    array('ws' => 'DEP-06', 'group' => 43, 'sort' => 60, 'target' => 'NT-DEP-06-901', 'row' => 901),
);

echo "══ موضعُ مسجِّلِ الاعتمادِ في مساحتَيه ══════════════════════════════\n";
$made = 0;
foreach ($PLACES as $p) {
    $ws = $p['ws'];
    if ($one("SELECT COUNT(*) FROM nav_targets WHERE target_id = '" . $conn->real_escape_string($p['target']) . "'") < 1) {
        $gk = 'العمل اليومي';
        $st = $conn->prepare("INSERT INTO nav_targets
              (target_id, source_doc, sheet_code, row_no, canonical_title,
               workspace_id, group_key, target_order, visibility_class, active)
              VALUES (?,?,?,?,?,?,?,?,'MENU_ITEM',1)");
        $st->bind_param('sssisssi', $p['target'], $SRC, $ws, $p['row'], $LABEL, $ws, $gk, $p['row']);
        $st->execute(); $st->close();
    }
    $tref = $ws . '·' . $p['row'] . '·' . $LABEL;
    $exists = $one("SELECT COUNT(*) FROM nav_placements
                     WHERE route = '" . $conn->real_escape_string($WITHPHP) . "'
                       AND workspace_id = '" . $conn->real_escape_string($ws) . "'");
    if ($exists < 1) {
        $st = $conn->prepare("INSERT INTO nav_placements
              (workspace_id, screen_id, route, target_ref, target_id, group_id,
               sort_no, placement_type, source_ref, active)
              VALUES (?,?,?,?,?,?,?,'MENU_ITEM',?,1)");
        $st->bind_param('sssssiis', $ws, $SCR, $WITHPHP, $tref, $p['target'], $p['group'], $p['sort'], $REF);
        $st->execute(); $st->close();
    }
    $exists = $one("SELECT COUNT(*) FROM nav_workspace_placements
                     WHERE route = '" . $conn->real_escape_string($ROUTE) . "'
                       AND workspace_id = '" . $conn->real_escape_string($ws) . "'");
    if ($exists < 1) {
        $pid = 'WP-' . strtoupper(substr(md5($ws . '|' . $ROUTE), 0, 16));
        $by = 'migrations/2028_05_26_perm04_recorder_placement';
        $st = $conn->prepare("INSERT INTO nav_workspace_placements
              (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no,
               route, canonical_label, governing_source, source_ref, reason_code,
               effective_from, status, version, created_by, legacy_ref, created_at)
              VALUES (?,?,?,?,'PRIMARY',?,?,?,?,?,'GUIDE_OWNED_LIFECYCLE_S9',
                      CURDATE(),'ACTIVE',1,?,?,NOW())");
        $st->bind_param('sssiisssssi', $pid, $SCR, $ws, $p['group'], $p['sort'],
                        $ROUTE, $LABEL, $SRC, $REF, $by, $p['group']);
        $st->execute(); $st->close();
        $made++;
        printf("   + موضعٌ في %s تحت المجموعة %d\n", $ws, $p['group']);
    } else {
        printf("   = موضعٌ قائمٌ في %s\n", $ws);
    }
}

/* ── الشاهد: الرابطُ يُصيَّر لأصحابِه ولا يُصيَّر لغيرِهم ─────────────────── */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
$seen = 0; $checked = 0;
foreach (array(18, 21, 31, 34) as $rid) {
    $u = $conn->query("SELECT id FROM users WHERE role = '{$rid}' AND is_deleted = 0
                        AND status = 'active' AND company_id = 4 LIMIT 1")->fetch_row();
    if (!$u) { continue; }
    $checked++;
    $_SESSION['user'] = array('id' => (int) $u[0], 'role' => (string) $rid,
                              'company_id' => 4, 'name' => 'placement probe');
    $ws = navarch_role_workspace($conn, $rid);
    $tree = navarch_render($conn, (string) $ws, $rid, array('include_shell' => false));
    $hit = null;
    foreach ($tree['groups'] as $g) {
        foreach ($g['items'] as $it) { if ($it['route'] === $ROUTE) { $hit = $g['label']; } }
    }
    if ($hit !== null) { $seen++; }
    printf("   الدور %-3d (%s): %s\n", $rid, (string) $ws, $hit !== null ? ('يرى الرابطَ تحت ' . $hit) : 'غائب ⛔');
}
/* الضابطُ السالب: دورٌ ليس صاحبَ نوعِ اعتمادٍ لا يراه. */
$leak = 0;
$u = $conn->query("SELECT id FROM users WHERE role = '6' AND is_deleted = 0
                    AND status = 'active' AND company_id = 4 LIMIT 1")->fetch_row();
if ($u) {
    $_SESSION['user'] = array('id' => (int) $u[0], 'role' => '6', 'company_id' => 4, 'name' => 'negative');
    $ws6 = navarch_role_workspace($conn, 6);
    if ($ws6) {
        $t6 = navarch_render($conn, (string) $ws6, 6, array('include_shell' => false));
        foreach ($t6['groups'] as $g) {
            foreach ($g['items'] as $it) { if ($it['route'] === $ROUTE) { $leak++; } }
        }
    }
}
printf("   الضابطُ السالب — دورٌ ليس صاحبَ نوعٍ يراه: %d\n", $leak);

if ($seen < $checked) { exit("\n⛔ ما يزال غائبًا عن بعضِ أصحابِه.\n"); }
if ($leak > 0) { exit("\n⛔ يُصيَّر لغيرِ أصحابِه.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والبابُ صار له رابطٌ يقود إليه.\n";
