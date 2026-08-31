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
/* قاعدتا القطعِ R1/R2 (SIDEBAR_CLOSE §٢②) — عينُ قواعدِ المقياسِ فلا يتفرّقان */
$reqByFile = array(); $reqByName = array();
foreach ($specByReq as $rq0 => $sp0) {
    if (preg_match('~\(([A-Za-z0-9_./-]+\.php)\)~u', (string) $sp0['surface'], $mf)) {
        $reqByFile[strtolower(basename($mf[1]))] = $rq0;
    }
    $nm0 = ra_norm(preg_replace('~\s*\([^)]*\)\s*$~u', '', (string) $sp0['surface']));
    if ($nm0 === '') { continue; }
    $reqByName[$nm0] = isset($reqByName[$nm0]) ? '__AMBIG__' : $rq0;
}
$canonNm = array();
$r = @$conn->query("SELECT route, canonical_ar FROM nav_canonical WHERE status = 'APPROVED'");
while ($r && ($x = $r->fetch_assoc())) {
    $canonNm[strtolower(trim(preg_replace('~[?#].*$~', '', (string) $x['route']), '/'))] = (string) $x['canonical_ar'];
}
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
/* SIDEBAR_CLOSE ①: المدى كلُّ دورٍ له بنودٌ حيّةٌ — لا مستخدمو co4 وحدَهم،
   فالدوران 11 و14 بلا مستخدمٍ ولم تبلغهما المحاذاةُ قطُّ (uid=0 يصيّرهما) */
$roleUid = array();
$r = $conn->query("SELECT DISTINCT role_id FROM nav_items WHERE active = 1 ORDER BY role_id");
while ($x = $r->fetch_row()) { $roleUid[(int) $x[0]] = 0; }
$r = $conn->query("SELECT CAST(u.role AS UNSIGNED) rid, MIN(u.id) uid FROM users u
                    WHERE u.company_id = 4 GROUP BY rid ORDER BY rid");
while ($x = $r->fetch_assoc()) {
    if (isset($roleUid[(int) $x['rid']])) { $roleUid[(int) $x['rid']] = (int) $x['uid']; }
}
unset($roleUid[5]);   /* مؤرشَفٌ بإثباتٍ حتى 2026-09-17 — يُستثنى كما في بوابةِ الحفظ */

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
        if ($rq === '' && isset($reqByFile[strtolower(basename($b))])) {
            $rq = $reqByFile[strtolower(basename($b))];                        /* R1 بالمسار حرفا */
        }
        if ($rq === '') {
            $lbl0 = isset($canonNm[$b]) ? $canonNm[$b] : '';
            $cand0 = ($lbl0 !== '' && isset($reqByName[ra_norm($lbl0)])) ? $reqByName[ra_norm($lbl0)] : '';
            if ($cand0 !== '' && $cand0 !== '__AMBIG__') { $rq = $cand0; }     /* R2 بالاسم المعياري */
        }
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
        $plan[$dk] = array('rid' => $rid, 'base' => $b,
            'route' => $scr !== null ? (string) $scr['route'] : $b,
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

    /* ═══ ترقيمُ المجموعاتِ بمستوى الدورِ — ⛔ لا بمستوى الإدارة ═══════════
       المُصيِّرُ يفرز الأقسامَ بـgroup_no داخل الدورِ كلِّه، فترقيمٌ لكلِّ
       إدارةٍ على حدةٍ يُصادم الأرقامَ عبر الإداراتِ ويخلط الرؤوسَ (قيس:
       سطحُ خزينةٍ صُيِّر تحت رأسِ أسطول). الاسمُ الواحدُ داخل الدورِ رقمٌ
       واحدٌ: يبدأ من أرقامِ إعلاناتِ الورقةِ القائمةِ ويُكمل بعد أقصاها. */
    $roleNo = array(); $roleNext = array();
    $r0 = $conn->query("SELECT role_id, group_ar, group_no FROM gov_target_nav
                         WHERE doc_code NOT LIKE 'RENDER-ALIGN%' ORDER BY role_id, group_no");
    while ($r0 && ($z0 = $r0->fetch_assoc())) {
        $rk = (int) $z0['role_id']; $gk = ra_norm((string) $z0['group_ar']);
        if ($gk === '') { continue; }
        if (!isset($roleNo[$rk][$gk])) { $roleNo[$rk][$gk] = (int) $z0['group_no']; }
        $roleNext[$rk] = max(isset($roleNext[$rk]) ? $roleNext[$rk] : 0, (int) $z0['group_no']);
    }
    $numOf = function ($rid, $g) use (&$roleNo, &$roleNext) {
        $gk = ra_norm($g);
        if (!isset($roleNo[$rid][$gk])) {
            $roleNext[$rid] = min(250, (isset($roleNext[$rid]) ? $roleNext[$rid] : 0) + 1);
            $roleNo[$rid][$gk] = $roleNext[$rid];
        }
        return $roleNo[$rid][$gk];
    };
    /* كنسُ ما رُقِّم سابقًا بالإدارةِ — يُعاد ترقيمُه بمستوى الدور */
    $r0 = $conn->query("SELECT id, role_id, group_ar FROM gov_target_nav
                         WHERE doc_code LIKE 'RENDER-ALIGN%' ORDER BY role_id, group_no, item_no");
    $fixedNo = 0;
    while ($r0 && ($z0 = $r0->fetch_assoc())) {
        $no = $numOf((int) $z0['role_id'], (string) $z0['group_ar']);
        if ($conn->query("UPDATE gov_target_nav SET group_no = $no WHERE id = " . (int) $z0['id'])) {
            $fixedNo += $conn->affected_rows;
        }
    }
    if ($fixedNo) { printf("  ◆ أُعيد ترقيمُ %d إعلانًا قائمًا بمستوى الدور\n", $fixedNo); }

    /* ═══ فكُّ تصادمِ الأرقامِ داخل الدورِ — حتى بين أوراقٍ متزاحمة ══════════
       قيس: دورُ ٢ يحمل g2 لاسمَين («التعاقد» من ورقةٍ و«عملي اليومي» من
       أخرى) والمُصيِّرُ يفرز بالرقمِ فيختلط الرأسان. الاسمُ الأسبقُ يُبقي
       رقمَه واللاحقُ يُنقل لأوّلِ رقمٍ حرٍّ — وكلُّ صفٍّ يُمسُّ يُسجَّل في
       سجلِّ التراجعِ قبل مسِّه. */
    $collFixed = 0;
    $r0 = $conn->query("SELECT role_id, group_no, group_ar FROM gov_target_nav
                         ORDER BY role_id, group_no, id");
    $byRoleNum = array();
    while ($r0 && ($z0 = $r0->fetch_assoc())) {
        $rk = (int) $z0['role_id']; $no = (int) $z0['group_no']; $gk = ra_norm((string) $z0['group_ar']);
        if (!isset($byRoleNum[$rk][$no])) { $byRoleNum[$rk][$no] = $gk; }
    }
    $r0 = $conn->query("SELECT id, doc_code, role_id, route, group_no, group_ar, item_no
                          FROM gov_target_nav ORDER BY role_id, group_no, id");
    $moves = array();
    $used = array();
    foreach ($byRoleNum as $rk => $ns) { foreach ($ns as $no => $gk) { $used[$rk][$no] = 1; } }
    while ($r0 && ($z0 = $r0->fetch_assoc())) {
        $rk = (int) $z0['role_id']; $no = (int) $z0['group_no']; $gk = ra_norm((string) $z0['group_ar']);
        if ($gk === '' || !isset($byRoleNum[$rk][$no]) || $byRoleNum[$rk][$no] === $gk) { continue; }
        /* اسمٌ لاحقٌ على رقمٍ محجوزٍ لغيرِه */
        if (!isset($moves[$rk][$gk])) {
            $free = $no;
            while (isset($used[$rk][$free])) { $free++; }
            $used[$rk][$free] = 1;
            $moves[$rk][$gk] = min(250, $free);
        }
        $newNo = $moves[$rk][$gk];
        $conn->query("INSERT INTO repair01_render_align
              (role_id, route, requirement_id, had_row, gt_id, before_group_ar, before_group_no,
               before_item_no, after_group_ar, after_group_no, after_item_no, rendered_before,
               witness, snapshot_id, applied_at)
            VALUES ($rk, '" . $e((string) $z0['route']) . "', '', 1, " . (int) $z0['id'] . ",
                    '" . $e((string) $z0['group_ar']) . "', $no, " . (int) $z0['item_no'] . ",
                    '" . $e((string) $z0['group_ar']) . "', $newNo, " . (int) $z0['item_no'] . ",
                    '" . $e('فك تصادم رقمي — الرقم ' . $no . ' محجوز لاسم اسبق في الدور') . "',
                    '" . $e('اسمان على رقم واحد داخل الدور والمصير يفرز بالرقم — اللاحق ينقل لاول رقم حر (' . $z0['doc_code'] . ')') . "',
                    '" . $e($sid) . "', NOW())
            ON DUPLICATE KEY UPDATE witness = VALUES(witness)");
        if ($conn->query("UPDATE gov_target_nav SET group_no = $newNo WHERE id = " . (int) $z0['id'])) {
            $collFixed += $conn->affected_rows;
        }
    }
    if ($collFixed) { printf("  ◆ فُكَّ تصادمُ %d صفًّا (اسمان على رقمٍ واحد)\n", $collFixed); }

    $n = 0;
    foreach ($plan as $x) {
        $x['gno'] = $numOf((int) $x['rid'], $x['g_after']);
        $conn->query('START TRANSACTION');
        $decl = $x['decl'];
        if ($decl) {
            $gtId = (int) $decl['id'];
            $ok1 = $conn->query("UPDATE gov_target_nav
                  SET group_ar = '" . $e($x['g_after']) . "', group_no = " . (int) $x['gno'] . ",
                      item_no = " . (int) $x['ino'] . "
                WHERE id = $gtId");
        } else {
            /* قيدُ uq_tn الفريدُ (doc_code, route) بلا بُعدِ دورٍ — فرمزُ
               المستندِ يحمل الدورَ: RENDER-ALIGN-R<n> */
            $ok1 = $conn->query("INSERT INTO gov_target_nav
                  (doc_code, role_id, group_no, group_ar, item_no, item_ar, route, note)
                VALUES ('" . $e('RENDER-ALIGN-R' . (int) $x['rid']) . "', " . (int) $x['rid'] . ", " . (int) $x['gno'] . ",
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
        if (!$ok1 || !$ok2) {
            $err = $conn->errno . ' ' . $conn->error;
            $conn->query('ROLLBACK');
            exit('✘ ' . ($ok1 ? 'سجلُّ التراجع' : 'gov_target_nav') . " ({$x['rid']} · {$x['route']} · gno={$x['gno']} ino={$x['ino']} g=«{$x['g_after']}»): $err\n");
        }
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
