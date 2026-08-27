<?php
/**
 * tools/repair01_sup_nav_activate.php — إظهارُ شاشاتِ الموردين في سايدبارِ إدارتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * **ملاحظةُ المالك**: «لم يتم تغييرُ السايدبار؟» — **وهي صحيحةٌ بالقياس**.
 *
 * ◆ **والنقلُ وقع والظهورُ لم يقع**: `Suppliers/suppliers.php` **في مجموعةِ
 *   [التأسيس]** و`rfq_requests` و`supplierscontracts` **في [التعاقد]** —
 *   أي أنَّ مجموعاتِ الدليلِ أُنشئت والبنودَ نُقلت إليها. **لكنَّ `active = 0`
 *   في عشرين بندًا، والسايدبارُ لا يُصيِّر الخامل** ⇒ **فلا يرى المستخدمُ شيئًا**.
 *
 * ⛔ **والإظهارُ توسيعُ وصولٍ حيٍّ لا يقرّره مبرمج** — وقد أَذِن به المالكُ
 *   صراحةً ثلاثَ مرّات: «وزّع المجموعاتِ والروابطَ لكلِّ إدارةٍ بمعياريّةِ
 *   الدليل» · «امنح الشاشاتِ الجديدةَ صلاحياتٍ كاملةً لمديرِ الإدارة» ·
 *   «أريد إدارةَ الموردين **مكتملة**». **فالإذنُ لهذه الإدارةِ بعينِها.**
 *
 * ⛔ **ويقتصر على ثلاثةِ قيود**: **شاشةُ الإدارةِ نفسِها** (`DEP-02`) ·
 *   **في مجموعةٍ من مجموعاتِ الدليلِ السبع** · **ودورُها يملك `can_view`**.
 *   ⇒ فلا يظهر ما لا يملكه الدورُ، ولا شاشةُ إدارةٍ أخرى، ولا خارجَ الدليل.
 *
 * التشغيل: php tools/repair01_sup_nav_activate.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$ROLE = 2;                       /* «ادارة الموردين» — دورُ DEP-02 المقيس */
$DEP  = 'DEP-02';

/* مجموعاتُ الدليلِ السبعُ لهذا الدور */
$spec = json_decode((string) file_get_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json'), true)[$DEP];
$nz = function ($v) { $v = str_replace(array('أ','إ','آ'), 'ا', (string) $v); $v = str_replace('ة', 'ه', $v);
    $v = preg_replace('~\s*\([A-Za-z][^)]*\)~u', '', $v);
    $v = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}]~u', '', $v);
    return preg_replace('~\s+~u', ' ', trim($v)); };
$tok = function ($t) use ($nz) { $o = array();
    foreach (preg_split('~[\s—·\-–\(\)/،,]+~u', $nz($t)) as $w) {
        $w = preg_replace('~^(وال|بال|فال|كال|ال|و)~u', '', $w);
        $w = preg_replace('~(ات|ون|ين|ه|ي)$~u', '', $w);
        /* ⚠ **وكلمةُ الإدارةِ في كلِّ عنوان**: «مورد/موردين» ترد في أغلبِ
             شاشاتِ الدليلِ السبعِ والثلاثين — **فمطابقتُها تُصيب كلَّ مجموعة**
             فذهبت «سلفيات» إلى [التأسيس] و«وثائق المورد» إلى [المالية].
             ⇒ **تُستبعَد كلمةُ الإدارةِ ويبقى المجالُ هو الدليل** — وهذا
             العطبُ نفسُه أصلحتُه في أهليّةِ الفلترة، **فهو نمطٌ لا حادثة**. */
        if (in_array($w, array('مورد', 'مورده', 'موردي', 'سجل', 'دفتر'), true)) { continue; }
        if (mb_strlen($w) >= 3) { $o[$w] = 1; } }
    return $o; };
$gid = array();
$r = $conn->query("SELECT id, name FROM link_groups WHERE owner_role_id = $ROLE");
while ($r && ($x = $r->fetch_assoc())) { $gid[$nz($x['name'])] = (int) $x['id']; }
$guideGroups = array();
foreach ($spec['groups'] as $g => $o) { if (isset($gid[$nz($g)])) { $guideGroups[$gid[$nz($g)]] = $g; } }
printf("\n═══ إظهارُ شاشاتِ الموردين — دور %d ═══\n", $ROLE);
printf("  مجموعاتُ الدليلِ الحاضرة: %d من %d\n", count($guideGroups), count($spec['groups']));

/* الشاشاتُ التي يملك الدورُ رؤيتَها */
$canSee = array();
$r = $conn->query("SELECT m.code FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE rp.role_id = $ROLE AND rp.can_view = 1");
while ($r && ($x = $r->fetch_row())) { $canSee[strtolower(preg_replace('~\.php$~i', '', ltrim($x[0], '/')))] = 1; }
/* وشاشاتُ الإدارةِ في السجل */
$mine = array();
$r = $conn->query("SELECT route, canonical_label_ar FROM repair01_screen_registry
                    WHERE owner_code = '$DEP' AND on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')");
while ($r && ($x = $r->fetch_assoc())) { $mine[strtolower(preg_replace('~\.php$~i', '', $x['route']))] = $x; }

$on = array(); $skipPerm = array(); $skipGrp = 0;
$r = $conn->query("SELECT id, route, label_ar, group_id, active, sort_order FROM nav_items WHERE role_id = $ROLE");
while ($r && ($x = $r->fetch_assoc())) {
    if ((int) $x['active'] === 1) { continue; }
    $k = strtolower(preg_replace('~\.php.*$~i', '', ltrim((string) $x['route'], '/')));
    if (!isset($mine[$k])) { continue; }                        /* ليست شاشةَ هذه الإدارة */
    /* ◆ **وشاشةُ الإدارةِ التي لا يذكرها الدليلُ تُسنَد لا تُترَك**: ثلاثَ عشرةَ
         شاشةً حيّةً في مجموعاتٍ قديمةٍ **من صفٍّ واحدٍ لكلٍّ** — وهي البنيةُ التي
         جاء الدليلُ ليستبدلها. ⛔ **وتركُها خارجَه يُبقي الإدارةَ نصفَين**.
       ⇒ تُسنَد إلى **أقربِ مجموعةٍ من مجموعاتِ الدليلِ بتقاطعِ كلماتِ اسمِها
         مع أسماءِ شاشاتِ تلك المجموعة** ⛔ لا باسمِ المجموعةِ وحدَه، فاسمُ
         المجموعةِ كلمةٌ واحدةٌ وأسماءُ شاشاتِها تصف مجالَها. */
    if (!isset($guideGroups[(int) $x['group_id']])) {
        $bg = 0; $bs = 0;
        foreach ($guideGroups as $g2 => $gname) {
            $pool = $gname;
            foreach ($spec['screens'] as $sc) { if ($sc['group'] === $gname) { $pool .= ' ' . $sc['title']; } }
            $a = $tok($mine[$k]['canonical_label_ar']); $b = $tok($pool);
            $i = count(array_intersect_key($a, $b));
            $sc2 = count($a) ? $i / count($a) : 0;
            if ($sc2 > $bs) { $bs = $sc2; $bg = $g2; }
        }
        if ($bg && $bs >= 0.34) { $x['group_id'] = $bg; $x['moved'] = round($bs * 100); }
        else { $skipGrp++; continue; }
    }
    if (!isset($canSee[$k])) { $skipPerm[] = $x['route']; continue; }          /* لا يملك رؤيتَها */
    $on[] = $x + array('grp' => $guideGroups[(int) $x['group_id']], 'lbl' => $mine[$k]['canonical_label_ar']);
}
printf("  **يُظهَر: %d** · خارجَ مجموعاتِ الدليل: %d · بلا صلاحيةِ عرض: %d\n\n", count($on), $skipGrp, count($skipPerm));
foreach ($on as $x) { printf("  ✔ %-34s [%s]\n", mb_substr($x['route'], 0, 32), mb_substr($x['grp'], 0, 20)); }
if ($skipPerm) { echo "\n  ⛔ لا تُظهَر — الدورُ لا يملك رؤيتَها:\n";
    foreach ($skipPerm as $p) { echo "     · $p\n"; } }

if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; exit(0); }
$n = 0;
foreach ($on as $x) {
    $set = 'active = 1';
    if (isset($x['moved'])) { $set .= ', group_id = ' . (int) $x['group_id']; }
    if ($conn->query("UPDATE nav_items SET $set WHERE id = " . (int) $x['id'])) { $n++; }
}
/* ⛔ **والتحقُّقُ بإعادةِ القياسِ لا بعدِّ الكتابات** */
$live = (int) $conn->query("SELECT COUNT(*) FROM nav_items ni
                             WHERE ni.role_id = $ROLE AND ni.active = 1
                               AND ni.group_id IN (" . (count($guideGroups) ? implode(',', array_keys($guideGroups)) : '0') . ")")->fetch_row()[0];
printf("\n✔ أُظهر %d · **وإعادةُ القياس: %d بندًا نشطًا في مجموعاتِ الدليل**\n", $n, $live);
