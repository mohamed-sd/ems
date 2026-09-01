<?php
/**
 * tools/gov_exec_human_card.php — بطاقةُ التحقّقِ البشريِّ لمساحةٍ (GOV_EXEC §9 · §26)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما هي**: `HUMAN_NAV_PASS` **لا ينجزه المنفِّذ** — يشترط دخولًا بدورٍ
 *   حقيقيٍّ وعينًا بشريّة. فالذي **يُنجَز هنا** هو **إعدادُ البطاقة**: الدورُ
 *   ومستخدمُه · الشجرةُ المتوقَّعةُ بمجموعاتِها وترتيبِها **من الورقةِ الحاكمة**
 *   · ونقاطُ الفحصِ · وموضعُ التوقيع. ثمَّ تُسلَّم وتُمضى (⛔ ولا تُنتظر).
 *
 * ◆ **والشجرةُ من الحاكمِ لا من التصيير**: `nav_placements` + `nav_lifecycle_groups`
 *   — فلو صُيِّرت شجرةٌ مخالفةٌ **رآها المتحقِّقُ مخالفةً**، ولو بُنيت البطاقةُ
 *   من التصييرِ نفسِه لَطابقت نفسَها دائمًا وما كشفت شيئًا.
 *
 * ◆ **والمساحةُ بلا دورٍ حيٍّ** تُكتب بطاقتُها بحاجزِها `BLOCKED_ROLE_BINDING`
 *   ولا تُزوَّر لها `PASS` (§27).
 *
 * التشغيل: php tools/gov_exec_human_card.php [--ws=DEP-04] [--all]
 * الخرج:   docs/REPAIR01_20260823/human_cards/<WS>.md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$OUT = $ROOT . '/docs/REPAIR01_20260823/human_cards';
if (!is_dir($OUT)) { @mkdir($OUT, 0777, true); }

$WS = null; $ALL = in_array('--all', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $WS = substr($a, 5); } }
if ($WS === null && !$ALL) { exit("⛔ --ws=<WORKSPACE> أو --all\n"); }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$snap = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

$spaces = array();
$q = $conn->query("SELECT workspace_id, name_ar FROM nav_workspaces WHERE active = 1"
    . ($WS !== null ? " AND workspace_id = '" . $conn->real_escape_string($WS) . "'" : '')
    . " ORDER BY workspace_id");
while ($x = $q->fetch_assoc()) { $spaces[$x['workspace_id']] = $x['name_ar']; }
if (!$spaces) { exit("⛔ لا مساحةَ بهذا المفتاح\n"); }

$made = 0;
foreach ($spaces as $ws => $nameAr) {
    /* الدورُ ومستخدمُه */
    $role = null; $user = null;
    $r = $conn->query("SELECT role_id FROM nav_ws_roles WHERE workspace_id = '{$conn->real_escape_string($ws)}'
                         AND binding = 'PRIMARY' LIMIT 1");
    $x = $r ? $r->fetch_row() : null;
    if ($x) {
        $role = (int) $x[0];
        $r = $conn->query("SELECT username FROM users WHERE role = '{$role}'
                             AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY id LIMIT 1");
        $y = $r ? $r->fetch_row() : null;
        $user = $y ? $y[0] : null;
    }

    /* الشجرةُ المتوقَّعةُ من الحاكم */
    $tree = array();
    $r = $conn->query("SELECT g.label_ar grp, g.sort_no gsort, p.sort_no psort,
                              p.target_ref, IFNULL(p.route,'') route, p.placement_type
                         FROM nav_placements p JOIN nav_lifecycle_groups g ON g.id = p.group_id
                        WHERE p.active = 1 AND p.workspace_id = '{$conn->real_escape_string($ws)}'
                        ORDER BY g.sort_no, p.sort_no");
    while ($x = $r->fetch_assoc()) { $tree[$x['grp']][] = $x; }
    if (!$tree) { continue; }

    $nBuilt = 0; $nTot = 0;
    foreach ($tree as $g) { foreach ($g as $it) { $nTot++; if ($it['route'] !== '') { $nBuilt++; } } }

    $md = "# بطاقةُ التحقّقِ البشريِّ — `{$ws}` {$nameAr}\n\n"
        . "> **اللقطة**: `{$snap}` · " . date('Y-m-d H:i') . " · **المصدرُ الحاكم**: "
        . "`nav_placements` + `nav_lifecycle_groups` (ورقةُ «01 · الدليل المعماري»)\n"
        . "> ⛔ **الشجرةُ أدناه مأخوذةٌ من الحاكمِ لا من التصيير** — فإن خالفها ما تراه على الشاشةِ "
        . "فالمخالفةُ عطبٌ يُسجَّل، لا فرقٌ يُسوّى في البطاقة.\n\n";

    if ($role === null) {
        $md .= "## ⛔ الحاجز\n\n**`BLOCKED_ROLE_BINDING`** — لا دورَ حيًّا مرتبطًا بهذه المساحة، "
             . "والتحقّقُ لا يُزوَّر بـ`PASS` (§27 من أمرِ الحوكمة). **يُرفَع للمالك**: "
             . "أيُّ دورٍ قائمٍ يحملها أم يُستحدَث؟\n\n";
    } elseif ($user === null) {
        $md .= "## ⛔ الحاجز\n\n**`BLOCKED_ROLE_BINDING`** — الدور {$role} مرتبطٌ ولا **مستخدمَ حيًّا** "
             . "يحمله، فلا سبيلَ للدخولِ به. **يُرفَع للمالك**.\n\n";
    } else {
        $md .= "## ① الدخول\n\n"
             . "| المفردة | القيمة |\n|---|---|\n"
             . "| الدور | **{$role}** |\n"
             . "| المستخدم | **`{$user}`** |\n"
             . "| المسار | `login.php` ⇐ ثمَّ الشريطُ الجانبيُّ لهذه المساحة |\n\n";
    }

    $md .= "## ② الشجرةُ المتوقَّعةُ — " . count($tree) . " مجموعةً · {$nBuilt}/{$nTot} مبنيّةً\n\n";
    $gi = 0;
    foreach ($tree as $grp => $items) {
        $gi++;
        $md .= "### {$gi}. {$grp}\n\n| # | البند | المسار | الحال |\n|---|---|---|---|\n";
        foreach ($items as $it) {
            $nm = preg_replace('~^[^·]*·[^·]*·~u', '', $it['target_ref']);
            $md .= '| ' . $it['psort'] . ' | ' . $nm . ' | '
                 . ($it['route'] !== '' ? '`' . $it['route'] . '`' : '—') . ' | '
                 . ($it['route'] !== '' ? '✔ مبنيّ' : '◇ غيرُ مبنيّ — لا يُتوقَّع ظهورُه') . " |\n";
        }
        $md .= "\n";
    }

    $md .= "## ③ نقاطُ الفحصِ — كلٌّ يُجاب بـ ✔ أو ✘ وسببِه\n\n"
         . "| # | ما يُفحَص | الحكم | الملاحظة |\n|---|---|---|---|\n"
         . "| 1 | **المجموعاتُ ظاهرةٌ بأسمائِها** كما في الجدولِ أعلاه وبترتيبِها | | |\n"
         . "| 2 | **بنودُ كلِّ مجموعةٍ بترتيبِها** ولا بندَ في غيرِ مجموعتِه | | |\n"
         . "| 3 | **كلُّ بندٍ مبنيٍّ يفتح شاشتَه** بلا 403/404 ولا صفحةٍ فارغة | | |\n"
         . "| 4 | **لا بندَ زائدٍ** في هذه المساحةِ خارجَ الجدول | | |\n"
         . "| 5 | **غيرُ المبنيِّ لا يظهر** — ولا رابطَ ميّتًا | | |\n"
         . "| 6 | **أسماءُ الشاشاتِ** على الشاشةِ نفسِها = اسمُ البندِ في القائمة | | |\n"
         . "| 7 | **الأعمدةُ** في كلِّ شاشةٍ = حقولُ ورقتِها بترتيبِها | | |\n"
         . "| 8 | **لا زرَّ يُعرض ولا يعمل** (فعلٌ بلا صلاحيّتِه) | | |\n\n"
         . "## ④ التوقيع\n\n"
         . "| المفردة | القيمة |\n|---|---|\n"
         . "| المتحقِّق (الاسم) | |\n"
         . "| الدورُ الذي دخل به | |\n"
         . "| التاريخُ والوقت | |\n"
         . "| الحكمُ النهائيّ | ☐ `HUMAN_NAV_PASS` ☐ `FAIL` بأسبابِه أعلاه |\n"
         . "| التوقيع | |\n\n"
         . "> **بعد التوقيع**: يُقيَّد الحكمُ بـ`php tools/gov_exec_human_card.php` "
         . "ولا يُرفَع `HUMAN_NAV_PASS` بلا هذه البطاقةِ موقَّعة.\n";

    file_put_contents($OUT . '/' . $ws . '.md', $md);
    echo "⇒ docs/REPAIR01_20260823/human_cards/{$ws}.md · " . count($tree) . " مجموعةً · {$nBuilt}/{$nTot}"
       . ($role === null || $user === null ? ' · ⛔ BLOCKED_ROLE_BINDING' : " · دور {$role}/{$user}") . "\n";
    $made++;
}
echo "── بطاقاتٌ: {$made}\n";
