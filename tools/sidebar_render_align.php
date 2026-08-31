<?php
/**
 * tools/sidebar_render_align.php — محاذاةُ السايدبارِ **في الحاكمِ المُثبَت**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_RENDER_FIX §٤·٣: «أعِدِ المحاذاةَ في المخزنِ الحاكمِ وحدَه —
 *   بهجرةٍ لها عكسٌ وبمعاينةِ قبل/بعد **من الشجرةِ المُصيَّرةِ لا من الجدول**».
 * ◆ الحاكمُ المُثبَتُ بحركةِ العدّادِ (`SIDEBAR_ORDER_AUTHORITY.md`):
 *   `gov_target_nav` بمفتاحِ الدورِ (المجموعةُ `group_ar` والرتبةُ `item_no`) —
 *   وعمودُ المحاذاةِ السابقِ (`nav_canonical.group_name`) **ميّتٌ تصييرًا**
 *   (التجربة ⑧ صفرُ حركةٍ حتى للعنوانِ الفرعيّ).
 * ◆ **الفرقُ يُقاس من الشجرةِ المُصيَّرةِ** لكلِّ دورٍ بعمليّةٍ نقيّةٍ، والملفُّ
 *   من `repair01_requirements` بجسرِ الكونِ (`MATCHED`) — ⛔ وما لا جسرَ له
 *   لا يُخترع له موضع. والمطابقةُ تصحُّ على الرأسِ او الفرعيِّ المُصيَّرَين.
 * ◆ والتراجعُ من `repair01_render_align` نفسِه (هجرة 2028_01_29 وعكسُها).
 *
 * التشغيل: php tools/sidebar_render_align.php [--apply] [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);

$snap = null;
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc()['snapshot_id']; }
if (!$snap && $APPLY) { exit("⛔ لا نافذةَ قياسٍ مفتوحة — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap : 'DRY';

/* التطبيعُ نفسُه المستعملُ في المقياس */
function ra_norm($s)
{
    $s = preg_replace('~[\x{0617}-\x{061A}\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'));
    $s = preg_replace('~[^\p{Arabic}\p{L}\p{N}]+~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}
function ra_render($ROOT, $rid, $uid)
{
    $o = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . (int) $rid . ' ' . (int) $uid . ' 2>NUL', $o);
    $j = json_decode(implode('', $o), true);
    return is_array($j) ? $j : null;
}

/* ═══ ① الملفُّ والجسر ═══════════════════════════════════════════════════ */
$scr2req = array();
$r = $conn->query("SELECT screen_id, requirement_id FROM repair01_target_universe
                    WHERE verdict = 'MATCHED' AND screen_id <> '' AND requirement_id <> ''");
while ($x = $r->fetch_row()) { $scr2req[$x[0]] = $x[1]; }
$byRoute = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE route <> ''");
while ($x = $r->fetch_assoc()) {
    $byRoute[strtolower(trim(preg_replace('~[?#].*$~', '', $x['route']), '/'))] = $x;
}
$specByReq = array();
$r = $conn->query("SELECT requirement_id, unit, group_name, surface, seq FROM repair01_requirements");
while ($x = $r->fetch_assoc()) { $specByReq[$x['requirement_id']] = $x; }
/* رتبةُ مجموعةِ الملفِّ داخل إدارتِها — من أدنى تسلسلٍ فيها (حتميٌّ من الملفّ) */
$gRank = array();
foreach ($specByReq as $sp) {
    $g = trim((string) $sp['group_name']);
    if ($g === '') { continue; }
    $k = $sp['unit'] . '|' . $g;
    $s0 = (int) $sp['seq'];
    if (!isset($gRank[$k]) || $s0 < $gRank[$k]) { $gRank[$k] = $s0; }
}
$gNo = array();
$byUnit = array();
foreach ($gRank as $k => $mn) { list($u0, $g0) = explode('|', $k, 2); $byUnit[$u0][$g0] = $mn; }
foreach ($byUnit as $u0 => $gs) {
    asort($gs);
    $i = 0;
    foreach ($gs as $g0 => $mn) { $gNo[$u0 . '|' . $g0] = min(250, ++$i); }
}

/* الإعلاناتُ القائمة */
$declRows = array();
$r = $conn->query("SELECT id, role_id, route, group_ar, group_no, item_no FROM gov_target_nav");
while ($x = $r->fetch_assoc()) {
    $k = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $x['route']), '/'));
    if ($k !== '') { $declRows[(int) $x['role_id'] . '|' . $k] = $x; }
}

/* ═══ ② الفرقُ من الشجرةِ المُصيَّرةِ لكلِّ دور ═══════════════════════════ */
$roleUid = array();
$r = $conn->query("SELECT CAST(u.role AS UNSIGNED) rid, MIN(u.id) uid FROM users u
                    WHERE u.company_id = 4 GROUP BY rid ORDER BY rid");
while ($x = $r->fetch_assoc()) { $roleUid[(int) $x['rid']] = (int) $x['uid']; }

$plan = array(); $stat = array('mis' => 0, 'ok' => 0, 'nobridge' => 0);
$role1Before = null;
foreach ($roleUid as $rid => $uid) {
    $j = ra_render($ROOT, $rid, $uid);
    if ($j === null) { continue; }
    if ($rid === 1) { $role1Before = $j; }
    foreach ($j['positions'] as $p) {
        $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $p['h']))));
        if ($b === '' || $b === 'main/role_board.php' || $b === 'chats/index.php') { continue; }
        $scr = isset($byRoute[$b]) ? $byRoute[$b] : null;
        $rq  = ($scr && isset($scr2req[$scr['screen_id']])) ? $scr2req[$scr['screen_id']] : '';
        if ($rq === '' || !isset($specByReq[$rq])) { $stat['nobridge']++; continue; }
        $sp = $specByReq[$rq];
        $g  = trim((string) $sp['group_name']);
        if ($g === '') { continue; }
        $gn = ra_norm($g);
        if ($gn === ra_norm((string) $p['g']) || $gn === ra_norm(isset($p['s']) ? (string) $p['s'] : '')) {
            $stat['ok']++;
            continue;
        }
        $stat['mis']++;
        $dk = $rid . '|' . $b;
        $decl = isset($declRows[$dk]) ? $declRows[$dk] : null;
        $plan[$dk] = array('rid' => $rid, 'base' => $b, 'route' => (string) $scr['route'],
            'req' => $rq, 'label' => (string) $p['l'],
            'g_after' => mb_substr($g, 0, 120),
            'gno' => isset($gNo[$sp['unit'] . '|' . $g]) ? $gNo[$sp['unit'] . '|' . $g] : 200,
            'ino' => min(250, max(1, (int) $sp['seq'])),
            'decl' => $decl,
            'rb' => 'رأس «' . $p['g'] . '» فرعي «' . (isset($p['s']) ? $p['s'] : '') . '»');
    }
}

printf("═══ محاذاةُ المُصيَّرِ في الحاكمِ المُثبَت — لقطة %s ═══\n", $sid);
printf("  مطابقٌ %d · مخالفٌ %d (سيُحاذى) · بلا جسرٍ %d\n",
       $stat['ok'], $stat['mis'], $stat['nobridge']);
$updN = 0; $insN = 0;
foreach ($plan as $x) { if ($x['decl']) { $updN++; } else { $insN++; } }
printf("  إعلانٌ قائمٌ يُصحَّح: %d · إعلانٌ جديدٌ يُدرَج (doc_code=RENDER-ALIGN): %d\n\n", $updN, $insN);

/* ═══ ③ التطبيق — سجلٌّ ثم كتابة ═════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_render_align'");
    if (!$has || !$has->num_rows) { exit("⛔ سجلُّ التراجعِ غيرُ موجودٍ — شغِّل الهجرةَ أوّلًا.\n"); }
    $n = 0;
    foreach ($plan as $x) {
        $conn->query('START TRANSACTION');
        $decl = $x['decl'];
        if ($decl) {
            $gtId = (int) $decl['id'];
            $ok1 = $conn->query("UPDATE gov_target_nav
                  SET group_ar = '" . $e($x['g_after']) . "', group_no = " . (int) $x['gno'] . ",
                      item_no = " . (int) $x['ino'] . "
                WHERE id = $gtId");
        } else {
            $ok1 = $conn->query("INSERT INTO gov_target_nav
                  (doc_code, role_id, group_no, group_ar, item_no, item_ar, route, note)
                VALUES ('RENDER-ALIGN', " . (int) $x['rid'] . ", " . (int) $x['gno'] . ",
                        '" . $e($x['g_after']) . "', " . (int) $x['ino'] . ",
                        '" . $e(mb_substr($x['label'], 0, 120)) . "', '" . $e($x['route']) . "',
                        '" . $e('محاذاة الملف التصميمي — SIDEBAR_RENDER_FIX §4·3 · ' . $x['req']) . "')");
            $gtId = (int) $conn->insert_id;
        }
        $wit = 'الفرقُ مقيسٌ من الشجرةِ المُصيَّرةِ (' . $x['rb'] . ') والملفُّ يقول «' . $x['g_after']
             . '» (' . $x['req'] . ') — والكتابةُ في الحاكمِ المُثبَتِ بحركةِ العدّادِ (SIDEBAR_ORDER_AUTHORITY)';
        $ok2 = $conn->query("INSERT INTO repair01_render_align
              (role_id, route, requirement_id, had_row, gt_id, before_group_ar, before_group_no,
               before_item_no, after_group_ar, after_group_no, after_item_no, rendered_before,
               witness, snapshot_id, applied_at)
            VALUES (" . (int) $x['rid'] . ", '" . $e($x['route']) . "', '" . $e($x['req']) . "',
                    " . ($decl ? 1 : 0) . ", $gtId,
                    '" . $e($decl ? $decl['group_ar'] : '') . "', " . ($decl ? (int) $decl['group_no'] : 0) . ",
                    " . ($decl ? (int) $decl['item_no'] : 0) . ",
                    '" . $e($x['g_after']) . "', " . (int) $x['gno'] . ", " . (int) $x['ino'] . ",
                    '" . $e(mb_substr($x['rb'], 0, 220)) . "', '" . $e($wit) . "', '" . $e($sid) . "', NOW())
            ON DUPLICATE KEY UPDATE witness = VALUES(witness)");
        if (!$ok1 || !$ok2) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
        $conn->query('COMMIT');
        $n++;
    }
    printf("  ✔ حُوذي **%d** موضعًا في الحاكمِ — والسجلُّ يحمل قبلَ كلٍّ وبعدَه\n\n", $n);

    /* §٤·٤ — الإثباتُ بلقطتَي شجرةٍ للدور 1 */
    $after1 = ra_render($ROOT, 1, $roleUid[1]);
    $head5 = function ($j) {
        $out = array(); $g = null;
        if (!$j) { return array('(تعذّر التصيير)'); }
        foreach ($j['positions'] as $p) {
            if ($p['g'] !== $g) { $g = $p['g']; $out[] = $g === '' ? '(بلا رأس)' : $g; }
            if (count($out) >= 5) { break; }
        }
        return $out;
    };
    echo "  [Snapshot] الدور 1 · قبل: " . implode(' ← ', $head5($role1Before)) . "\n";
    echo "                     بعد : " . implode(' ← ', $head5($after1)) . "\n";
    $chg = ($head5($role1Before) !== $head5($after1));
    /* والاختلافُ قد يقع تحت الرؤوسِ الخمسةِ الاولى — فيُحسب ايضًا بمواضعِ البنود */
    $sig = function ($j) { $s = array(); foreach ($j['positions'] as $p) { $s[] = $p['g'] . '|' . $p['h']; } return md5(implode(';', $s)); };
    if (!$chg && $role1Before && $after1) { $chg = ($sig($role1Before) !== $sig($after1)); }
    echo $chg ? "  ✔ **اللقطتان مختلفتان — التغييرُ مُثبَتٌ في الشجرة**\n"
              : "  ⛔ **اللقطتان متطابقتان — لا يُقبل «صُحِّح»**\n";
}
