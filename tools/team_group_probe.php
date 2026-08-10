<?php
/**
 * tools/team_group_probe.php — فاحصُ «هل يُصيَّر رابطُ إدارة المعاونين فعلًا؟»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا يوجد هذا الملف: أُضيف الرابطُ بترحيلٍ كتب في `nav_items`، وتحقّقتُ
 *   من ثلاثةِ أدوارٍ فمرّت، فأعلنتُ الإتمام — وكانت الثلاثةُ كلُّها **داخلَ
 *   علَمِ التنقّل الموحَّد**، وأربعةٌ خارجَه (منها إدارةُ المخاطر) لا تقرأ
 *   `nav_items` أصلًا. فالعيّنةُ المتجانسةُ شهدت للسليمِ وسكتت عن المعطوب.
 *
 * ◆ فالفحصُ هنا **لا يعيد كتابةَ الاستعلام** — بل ينادي دوالَّ التصيير نفسَها
 *   (`getUnifiedNavItems` · `getDynamicNavLinks`+`getNavGroups`+`buildNavTree`)
 *   بعد اختيارِ المسار بالعلَمِ كما يختاره `insidebar.php` حرفيًّا. شاهدٌ من
 *   نفس المصدر الذي يراه المستخدم، لا من مصدرٍ يشبهه.
 *
 * التشغيل:  php tools/team_group_probe.php
 * الخروج:   0 إن صُيِّر الرابطُ داخلَ مجموعة «فريق العمل» لكل دورٍ مستهدَف.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
define('EMS_CLI', true);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dynamic_nav.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

const ROUTE = 'main/project_users.php';
const GROUP = 'فريق العمل';
const LABEL = 'إدارة المعاونين';

/* الأدوارُ المستهدفة = من له مجموعةُ الفريق (لا قائمةٌ مثبَّتةٌ في الفاحص —
   وإلا فحصَ الفاحصُ ما اخترتُ فحصَه، وهو عينُ ما تفعله البوابةُ حين تصادق
   على نفسِها). */
$roles = array();
$r = mysqli_query($conn, "SELECT g.owner_role_id rid, r.name
                            FROM link_groups g JOIN roles r ON r.id = g.owner_role_id
                           WHERE g.group_code LIKE 'n9o_team_r%' AND g.is_active = 1
                           ORDER BY g.owner_role_id");
while ($x = mysqli_fetch_assoc($r)) { $roles[intval($x['rid'])] = $x['name']; }

$fail = 0; $okCount = 0;
echo "فاحصُ تصييرِ «" . LABEL . "» — " . count($roles) . " دورًا مستهدَفًا\n";
echo str_repeat('─', 78) . "\n";

foreach ($roles as $roleId => $roleName) {
    /* جلسةٌ مصطنعةٌ بالحدِّ الذي تقرؤه دوالُّ التصيير (حارسُ البوابة الموردية
       وبوابةُ التمويل يقرآن `$_SESSION['user']`). */
    $_SESSION['user'] = array('id' => 1, 'role' => (string) $roleId, 'company_id' => 4);

    $unified = unifiedNavEnabled($roleId);
    $path = $unified ? 'موحَّد' : 'قديم ';
    $found = false; $groupOk = false; $label = ''; $place = '';

    if ($unified) {
        foreach (getUnifiedNavItems($conn, $roleId) as $it) {
            if ((string) $it['route'] !== ROUTE) { continue; }
            $found = true;
            $label = (string) $it['label_ar'];
            $place = trim((string) $it['stage_title']) !== '' ? trim((string) $it['stage_title']) : (string) $it['group_name'];
            $groupOk = ($place === GROUP);
            break;
        }
    } else {
        $tree = buildNavTree(getDynamicNavLinks($conn, $roleId), getNavGroups($conn, $roleId));
        foreach ($tree as $node) {
            if (!isset($node['type']) || $node['type'] !== 'group') { continue; }
            foreach ($node['links'] as $l) {
                if ((string) $l['code'] !== ROUTE) { continue; }
                $found = true;
                $label = (string) $l['name'];
                $place = (string) $node['name'];
                $groupOk = ($place === GROUP);
                break 2;
            }
        }
        if (!$found) { // أهو مصيَّرٌ خارجَ مجموعة؟ (رابطٌ مسطَّح = مخالفة)
            foreach ($tree as $node) {
                if (isset($node['link']) && (string) $node['link']['code'] === ROUTE) {
                    $found = true; $label = (string) $node['link']['name']; $place = '(بلا مجموعة)';
                }
            }
        }
    }

    $labelOk = ($label === LABEL);
    $ok = $found && $groupOk && $labelOk;
    if ($ok) { $okCount++; } else { $fail++; }

    /* ◆ گوتشا مثبَتة: `»` بعد متغيّرٍ **تُبتلع في اسمِه** (بايتاتٌ ≥0x80 حروفُ
       معرِّفٍ صالحة) — فـ"«$label»" تقرأ متغيّرًا اسمُه `label»` فتُطبع فارغةً.
       والحكمُ كان يصحّ والسطرُ يكذب، وهو أسوأُ من فحصٍ راسب. `{$x}` إلزام. */
    printf("%s دور %-3s [%s] %-28s %s\n",
        $ok ? '✔' : '✘', $roleId, $path, mb_substr($roleName, 0, 26),
        $found ? ("«{$label}» ضمن «{$place}»") : 'لا يُصيَّر إطلاقًا');
}

echo str_repeat('─', 78) . "\n";
echo ($fail === 0 ? "✔ " : "✘ ") . "$okCount/" . count($roles) . " دورًا يُصيَّر لهم الرابطُ باسمِه داخلَ مجموعتِه\n";
exit($fail === 0 ? 0 : 1);
