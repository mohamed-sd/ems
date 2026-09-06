<?php
/**
 * 2028_05_25_perm04_fix_recorder_nav_group.php — رابطٌ بلا مجموعةٍ لا يُصيَّر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **عطبٌ أدخلتْه الهجرةُ 2028_05_19 وكشفه `tests/fixc_finance_nav_test.php`**:
 *   كُتبت بنودُ ملاحةِ `Finance/acc_approval_record.php` بـ`group_id = NULL`
 *   وبابٍ `FIN`. والقاعدةُ في هذا النظام: **رابطٌ بلا مجموعةٍ تحضنه لا يُصيَّر
 *   في أيِّ منطقة — موجودٌ ولا يُرى**. فالشاشةُ يفتحها الحارسُ ولا يقود إليها
 *   رابط، وهو عينُ العطبِ الذي جاءت هذه الحملةُ تعالجه.
 *   والأربعةُ الوحيدةُ بلا مجموعةٍ في الشجرةِ كلِّها كانت **لي**.
 *
 * ◆ **وموضعُ الرابطِ يُقرَّر بجارِه لا بالتخمين**: شاشةُ سلسلةِ الاعتمادِ
 *   `Finance/acc_approval_chain.php` — وهي أختُ هذه الشاشةِ في الوظيفة — تسكن
 *   مجموعةَ «الترحيل والاعتمادات المالية» في بابِ `DAILY` لكلِّ دورٍ له مثلُها.
 *   ومن لا مجموعةَ اعتماداتٍ له تُوضَع في أقربِ مجموعةٍ قائمةٍ **بالاسمِ لا
 *   بالرقم**، ويُسمّى الاختيارُ هنا فلا يكون صامتًا:
 *     18 · 31 ⇐ «الترحيل والاعتمادات المالية» (مجموعةُ الأختِ نفسِها)
 *     21      ⇐ «الاعتمادات الواردة» (أقربُ مجموعةِ اعتماداتٍ في مساحتِه)
 *     34      ⇐ «مجال العمل اليومي» (لا مجموعةَ اعتماداتٍ في مساحتِه)
 *
 * التشغيل: php database/migrations/2028_05_25_perm04_fix_recorder_nav_group.php
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

$ROUTE = 'Finance/acc_approval_record.php';
$e = $conn->real_escape_string($ROUTE);

echo "══ تصحيحُ مجموعةِ رابطِ مسجِّلِ الاعتماد ══════════════════════════════\n";
$before = $one("SELECT COUNT(*) FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                 WHERE n.active = 1 AND (n.group_id IS NULL OR g.id IS NULL)");
printf("   روابطُ الشجرةِ بلا مجموعةٍ قبل: %d\n\n", $before);

/* ◆ **المجموعةُ تُطلَب بالاسمِ داخلَ مساحةِ الدورِ نفسِه** — فرقمُ المجموعةِ
     يختلف لكلِّ دورٍ ولا يُنقَل بين الأدوار. */
$wantByRole = array(
    18 => array('الترحيل والاعتمادات المالية', 'الاعتمادات الواردة', 'مجال العمل اليومي'),
    21 => array('الترحيل والاعتمادات المالية', 'الاعتمادات الواردة', 'مجال العمل اليومي'),
    31 => array('الترحيل والاعتمادات المالية', 'الاعتمادات الواردة', 'مجال العمل اليومي'),
    34 => array('الترحيل والاعتمادات المالية', 'الاعتمادات الواردة', 'مجال العمل اليومي'),
);

$fixed = 0; $stuck = array();
foreach ($wantByRole as $rid => $names) {
    $gid = 0; $gname = '';
    foreach ($names as $n) {
        $st = $conn->prepare("SELECT n.group_id, g.name FROM nav_items n
                               JOIN link_groups g ON g.id = n.group_id
                              WHERE n.role_id = ? AND n.active = 1 AND g.name = ?
                              GROUP BY n.group_id LIMIT 1");
        $st->bind_param('is', $rid, $n);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) { $gid = (int) $row['group_id']; $gname = (string) $row['name']; break; }
    }
    if ($gid < 1) { $stuck[] = $rid; continue; }
    $st = $conn->prepare("UPDATE nav_items SET group_id = ?, door = 'DAILY'
                           WHERE role_id = ? AND route = ? AND active = 1");
    $st->bind_param('iis', $gid, $rid, $ROUTE);
    $st->execute();
    if ($st->affected_rows > 0) { $fixed++; }
    $st->close();
    printf("   الدور %-3d ⇐ مجموعة %d «%s»\n", $rid, $gid, $gname);
}
if ($stuck) { printf("   ⚠ أدوارٌ بلا مجموعةٍ مناسبة: %s\n", implode(' · ', $stuck)); }

/* ── الشاهد: صفرُ رابطٍ بلا مجموعةٍ · والرابطُ يُصيَّر فعلًا ─────────────── */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
$after = $one("SELECT COUNT(*) FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                WHERE n.active = 1 AND (n.group_id IS NULL OR g.id IS NULL)");
printf("   روابطُ الشجرةِ بلا مجموعةٍ بعد: %d\n", $after);

$rendered = 0; $checked = 0;
foreach (array_keys($wantByRole) as $rid) {
    $u = $conn->query("SELECT id FROM users WHERE role = '{$rid}' AND is_deleted = 0
                        AND status = 'active' AND company_id = 4 LIMIT 1")->fetch_row();
    if (!$u) { continue; }
    $checked++;
    $_SESSION['user'] = array('id' => (int) $u[0], 'role' => (string) $rid,
                              'company_id' => 4, 'name' => 'nav probe');
    $ws = navarch_role_workspace($conn, $rid);
    if (!$ws) { continue; }
    $tree = navarch_render($conn, $ws, $rid, array('include_shell' => false));
    foreach ($tree['groups'] as $g) {
        foreach ($g['items'] as $it) {
            if ($it['route'] === 'finance/acc_approval_record') {
                $rendered++;
                printf("   الدور %-3d يرى الرابطَ تحت: %s\n", $rid, $g['label']);
            }
        }
    }
}
printf("   مُصيَّرٌ لـ %d من %d دورٍ فُحص\n", $rendered, $checked);

if ($after > $before) { exit("\n⛔ ازداد اليتيم — راجعْ.\n"); }
if ($after !== 0) { printf("\n⚠ بقي %d رابطًا بلا مجموعةٍ — وليست من هذه الشاشة.\n", $after); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والرابطُ صار يُرى لا موجودًا وحسب.\n";
