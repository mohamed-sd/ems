<?php
/**
 * 2028_04_18_navarch02_guide_leaf_placements.php — استردادُ مواضعِ أهدافِ
 * الدليلِ المبنيّةِ التي لم يمرَّ بها التصيير (‏§8 · §22 · SILENT_DROP_FIX)
 * @migration-objects: nav_workspace_placements rows
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ مقيسٌ لا مستنتَج**: `nav_workspace_placements` كلُّه من مخرَجِ
 *   `tools/navarch/classify.php`، **ومقامُ ذلك المُصنِّفِ `NAV_ARCH_BASELINE`**
 *   أي **ما يُصيَّر اليوم**. فالورقةُ تُقرأ لتصنيفِ الظاهرِ ولا يُسأل قطُّ عمّا
 *   في الورقةِ ولم يظهر. ⇒ **هدفٌ مبنيٌّ لا صفَّ له في `nav_items`، أو مساحتُه
 *   بلا دورٍ مربوطٍ فتُقرأ `rendered = null`، يسقط من السجلِّ الحاكمِ صامتًا**،
 *   ثمَّ يحجبه §22 (`No Placement = No Sidebar Render`) فيختفي بلا حكمٍ مكتوب.
 *
 * ◆ **المقيسُ حرفًا** (‏قبلَ هذه الهجرة): 413 ورقةَ دليلٍ لها مسارٌ مبنيّ،
 *   **23 منها بلا موضعٍ حاكمٍ إطلاقًا** — و**مساحتانِ كاملتانِ صفرٌ**:
 *     · `EX-DVP` **12** — و`nav_ws_roles` لا يحمل لها صفًّا أصلًا (‏ولا دورَ
 *       «نائبِ الرئيس» في `roles` الخمسةِ والثلاثين) فلا تُصيَّر لأحد.
 *     · `WS-MY` **7** — للسببِ نفسِه.
 *     · و**4** تبويباتٍ في `DEP-01`×2 · `DEP-02` · `DEP-17`.
 *
 * ⭐ **والحكمُ من الورقةِ نفسِها لا من اجتهاد**: الصنفُ والمجموعةُ والترتيبُ
 *   كلُّها من صفِّ `nav_placements` الحاكمِ (‏ورقةُ الدليلِ المستوعَبة) —
 *   ⛔ ولا يُخترَع منها شيء، و⛔ **ولا تُمَسُّ هويّةُ شاشةٍ** (§42).
 *
 * ⛔ **ولا يدخل إلّا مبنيٌّ على القرص**: مسارُ `GAP:` وصنفُ `NOT_BUILT` وملفٌّ
 *   غيرُ موجودٍ **تخرج بحكمٍ مكتوبٍ مطبوعٍ لا بصمت** (`NOT_REALLY_BUILT`).
 *
 * ◆ **والعطبُ عولج في أداتِه أيضًا** [[fix-the-tool-not-the-output]]:
 *   `tools/navarch/classify.php` صار له **ممرُّ ورقةِ الدليل** بالمنطقِ نفسِه،
 *   فإعادةُ بنائِه الكاملةُ لا تُسقط هذه الثلاثةَ والعشرين مرّةً أخرى.
 *   **وهذه الهجرةُ تُنزل التغييرَ على المخزنِ بعكسٍ صريح** — والاثنانِ يتّفقان.
 *
 * والعكسُ في `_down.php`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/** تسويةُ المسارِ — الصيغةُ الواحدةُ التي يقارن بها المُصيِّرُ والمُصنِّف */
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ① المواضعُ الحاكمةُ القائمة — بمفتاحِ (مساحة · مسارٌ مُسوًّى) */
$have = array();
$r = $conn->query("SELECT workspace_id, route FROM nav_workspace_placements
                    WHERE route IS NOT NULL AND route <> ''");
while ($r && ($x = $r->fetch_assoc())) { $have[$x['workspace_id'] . '|' . $rt($x['route'])] = true; }
echo "◆ مواضعُ حاكمةٌ قائمة: " . count($have) . "\n";

/* ② أصنافُ الورقةِ السبعةُ ⇒ مفرداتُ §9 التسع — ⛔ ولا مفردةَ تُستحدَث */
$L2P = array('MENU_ITEM' => 'PRIMARY', 'LANDING_PAGE' => 'PRIMARY',
             'TAB_CHILD' => 'TAB_CHILD', 'DIRECT_ONLY' => 'DIRECT_ONLY',
             'PROJECTION' => 'EXECUTIVE_PROJECTION', 'UTILITY' => 'UTILITY');

$ins = $conn->prepare("INSERT INTO nav_workspace_placements
    (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
     canonical_label, governing_source, source_ref, reason_code, effective_from, status,
     version, created_by, approved_by, legacy_ref, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'ACTIVE',1,?,NULL,?,NOW())");

$CB = 'migrations/2028_04_18_navarch02_guide_leaf_placements.php';
$today = date('Y-m-d');
$n = 0; $skip = array(); $made = array();

$r = $conn->query("SELECT p.id, p.workspace_id, p.screen_id, p.route, p.target_ref, p.sort_no,
                          p.placement_type, p.group_id, s.canonical_label_ar
                     FROM nav_placements p
                LEFT JOIN repair01_screen_registry s ON s.screen_id = p.screen_id
                    WHERE p.active = 1 AND p.route IS NOT NULL AND p.route <> ''
                 ORDER BY p.workspace_id, p.group_id, p.sort_no, p.id");
while ($x = $r->fetch_assoc()) {
    $ws  = (string) $x['workspace_id'];
    $raw = (string) $x['route'];
    if (strncmp($raw, 'GAP:', 4) === 0) { continue; }          /* منصوصٌ بلا شاشةٍ حيّة */
    $k = $rt($raw);
    if ($k === '' || isset($have[$ws . '|' . $k])) { continue; }
    $lpt = (string) $x['placement_type'];
    if (!isset($L2P[$lpt])) { $skip[] = "{$ws} · {$raw} — صنفُ ورقةٍ لا يُبنى: {$lpt}"; continue; }
    if (!is_file($ROOT . '/' . $raw)) {
        $skip[] = "{$ws} · {$raw} — NOT_REALLY_BUILT: لا ملفَّ على القرص";
        continue;
    }
    /* الاسمُ الحاكمُ والبند: `code·idx·الاسم` */
    $tp = explode('·', (string) $x['target_ref']);
    $idx = (count($tp) >= 3) ? trim($tp[1]) : '';
    $nm  = (count($tp) >= 3) ? trim(implode('·', array_slice($tp, 2))) : '';
    $label = ((string) $x['canonical_label_ar'] !== '') ? (string) $x['canonical_label_ar'] : $nm;

    /* ⭐ «مساحة عملي» صنفُها `PERSONAL` بنصِّ §11 ولو كان صفُّ الورقةِ `MENU_ITEM` */
    $pt = ($ws === 'WS-MY' && $L2P[$lpt] === 'PRIMARY') ? 'PERSONAL' : $L2P[$lpt];
    $rc = ($pt === 'PERSONAL') ? 'PERSONAL_WS_MY_S11' : 'GUIDE_LEAF_WITHOUT_PLACEMENT_S22';

    $pid = 'WP-' . strtoupper(substr(sha1($ws . '|' . $k), 0, 16));
    $sid = ((string) $x['screen_id'] !== '') ? (string) $x['screen_id'] : null;
    $gid = ((int) $x['group_id'] > 0) ? (int) $x['group_id'] : null;
    $srt = (int) $x['sort_no'];
    $gs  = '01 · الدليل المعماري.xlsx · ورقة ' . $ws . ($idx !== '' ? ' · بند ' . $idx : '')
         . ' — NAV-ARCH-02 §8 · §22: هدفُ ورقةٍ مبنيٌّ يلزمه موضعٌ حاكم';
    $sr  = 'nav_placements#' . (int) $x['id'] . ' · target_ref = ' . (string) $x['target_ref']
         . ' · صنفُ الورقة ' . $lpt . ' · ' . $raw . ' موجودٌ على القرص';
    $lr  = (int) $x['id'];

    $ins->bind_param('sssisisssssssi', $pid, $sid, $ws, $gid, $pt, $srt, $k,
                     $label, $gs, $sr, $rc, $today, $CB, $lr);
    if ($ins->execute()) {
        $n++; $made[$ws] = (isset($made[$ws]) ? $made[$ws] : 0) + 1;
        $have[$ws . '|' . $k] = true;
        printf("+ %-8s %-12s %-20s g=%-5s s=%-3s %s\n", $ws, $sid ?: '—', $pt, $gid, $srt, $k);
    } else {
        echo "x {$ws} · {$k}: " . $conn->error . "\n";
    }
}
$ins->close();

echo "\n= أُنشئ {$n} موضعًا حاكمًا\n";
ksort($made);
foreach ($made as $w => $c) { printf("   %-10s %3d\n", $w, $c); }
foreach ($skip as $s) { echo "· لم يُنشأ: {$s}\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
