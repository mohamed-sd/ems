<?php
/**
 * tools/sidebar_order_authority.php — أيُّ مخزنٍ يحكم ترتيبَ السايدبارِ ومجموعتَه؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_RENDER_FIX §٤·٢: «لا تفترضْ أيَّ المصادرِ يحكم — أثبِتْه
 *   بحركةِ عدّاد» — خمسُ تجاربَ على الدورِ ١ (موضعِ البلاغ)، لكلٍّ:
 *   **قبل ⇒ تغييرٌ ⇒ تصييرٌ نقيٌّ ⇒ بعد ⇒ ردٌّ ⇒ تحقّقُ الردّ**.
 * ◆ ⛔ ولا يُترك تغييرٌ تجريبيٌّ في القاعدة: الأصلُ يُحفظ قبل اللمسِ ويُردُّ
 *   حرفًا ويُثبَت الردُّ بتصييرٍ ثالثٍ يطابق الأوّل.
 * ◆ الهدفُ يُنتقى قياسًا من شجرةِ الدورِ ١ المُصيَّرةِ نفسِها (لا تسميةً):
 *   بندٌ غيرُ مرسوٍّ له صفٌّ في المخازنِ الأربعة.
 *
 * التشغيل: php tools/sidebar_order_authority.php [--md]
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
$MD = in_array('--md', $argv, true);
$RID = 1;

/* مستخدم الدور 1 في co4 */
$uid = (int) $conn->query("SELECT MIN(id) FROM users WHERE company_id = 4
                            AND CAST(role AS UNSIGNED) = $RID")->fetch_row()[0];

/** تصييرٌ في عمليّةٍ نقيّةٍ وإرجاعُ المواضع [i => {g,l,h,base}] */
function soa_render($ROOT, $rid, $uid)
{
    $o = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . (int) $rid . ' ' . (int) $uid . ' 2>NUL', $o);
    $j = json_decode(implode('', $o), true);
    if (!is_array($j)) { return array(); }
    $out = array();
    foreach ($j['positions'] as $i => $p) {
        $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $p['h']))));
        $out[] = array('g' => $p['g'], 'l' => $p['l'], 'base' => $b);
    }
    return $out;
}
/** موضعُ مسارٍ في الشجرة: [الفهرسُ الكلّي, فهرسُه داخل مجموعتِه, اسمُ مجموعتِه] */
function soa_locate($pos, $base)
{
    $inG = array();
    foreach ($pos as $i => $p) {
        $inG[$p['g']] = isset($inG[$p['g']]) ? $inG[$p['g']] + 1 : 1;
        if ($p['base'] === $base) { return array($i, $inG[$p['g']], $p['g']); }
    }
    return array(-1, -1, '');
}

/* ═══ ① انتقاءُ الهدفِ قياسًا ═══════════════════════════════════════════ */
$before0 = soa_render($ROOT, $RID, $uid);
if (!$before0) { exit("⛔ تعذّر تصييرُ الدورِ 1\n"); }
/* أحجامُ المجموعاتِ المُصيَّرة — تجربةُ الترتيبِ تحتاج بندًا رتبتُه ≥2 في
   مجموعةٍ من ≥3 كي تُرى حركةُ الدفعِ للقمّة (بندٌ وحيدٌ او اوّلُ اعمى) */
$gSize = array(); $gIdx = array();
foreach ($before0 as $p) { $gSize[$p['g']] = isset($gSize[$p['g']]) ? $gSize[$p['g']] + 1 : 1; }
/* هدفان: هدفُ الترتيبِ (رتبتُه ≥2 في مجموعةٍ ≥3 وله مخازنُ الترتيبِ الثلاثةُ)
   وهدفُ المجموعةِ (اي بندٍ له صفُّ الاعلانِ وصفُّ نطاقِ المسار) */
$lookup = function ($b) use ($conn, $e, $RID) {
    return array(
        'ni' => $conn->query("SELECT n.id, n.sort_order, n.group_id FROM nav_items n
                               WHERE n.role_id = $RID AND n.active = 1
                                 AND LOWER(TRIM(LEADING '/' FROM REPLACE(n.route,'../',''))) LIKE '%" . $e($b) . "'
                               LIMIT 1")->fetch_assoc(),
        'nc' => $conn->query("SELECT route, sort_no FROM nav_canonical
                               WHERE LOWER(route) = '" . $e($b) . "' LIMIT 1")->fetch_assoc(),
        'gt' => $conn->query("SELECT id, item_no, group_ar FROM gov_target_nav
                               WHERE role_id = $RID AND LOWER(route) = '" . $e($b) . "' LIMIT 1")->fetch_assoc(),
        'rg' => $conn->query("SELECT route, group_code FROM nav_route_group
                               WHERE LOWER(route) = '" . $e($b) . "' LIMIT 1")->fetch_assoc(),
    );
};
/* ثلاثةُ أهدافٍ بنصِّ سلَّمِ الشيفرةِ نفسِه:
   تجربتا 1-2 بندٌ **بلا صفِّ إعلانٍ** (والا حجب item_no قناتَي sort) ·
   تجربةُ 3 بندٌ **مُعلَنٌ** · تجربتا 4-5 بندٌ له إعلانٌ ونطاقُ مسار.
   والاتجاهُ بالرتبة: الاوّلُ يُدفع اسفلَ (99999) وغيرُه اعلى (-500000). */
/* مجموعةُ المرساتَين تُستبعد من هدفَي الترتيب — الدفعُ للقمّةِ لا يعلوهما */
$anchorGroup = '';
foreach ($before0 as $p) { if ($p['base'] === 'main/role_board.php') { $anchorGroup = $p['g']; break; } }
$t12 = null; $t3 = null; $tGroup = null;
$seen = array();
foreach ($before0 as $p) {
    $b = $p['base'];
    $seen[$p['g']] = isset($seen[$p['g']]) ? $seen[$p['g']] + 1 : 1;
    if ($b === '' || $b === 'main/role_board.php' || $b === 'chats/index.php') { continue; }
    if ($gSize[$p['g']] < 2 || $p['g'] === $anchorGroup) { continue; }
    $rowsB = $lookup($b);
    $meta = array('base' => $b, 'label' => $p['l'], 'rank' => $seen[$p['g']], 'grp' => $p['g']);
    if ($t12 === null && $rowsB['ni'] && $rowsB['nc'] && !$rowsB['gt']) {
        $t12 = array_merge($meta, $rowsB);
    }
    if ($t3 === null && $rowsB['gt']) {
        $t3 = array_merge($meta, $rowsB);
    }
    if ($tGroup === null && $rowsB['gt'] && $rowsB['rg']) {
        $tGroup = array_merge($meta, $rowsB);
    }
    if ($t12 !== null && $t3 !== null && $tGroup !== null) { break; }
}
if (!$t12 || !$t3 || !$tGroup) {
    exit("⛔ الاهداف (1-2: " . ($t12 ? '✔' : '✘') . " · 3: " . ($t3 ? '✔' : '✘')
       . " · مجموعة: " . ($tGroup ? '✔' : '✘') . ")\n");
}
$ordVal = function ($t) { return ((int) $t['rank'] === 1) ? 99999 : -500000; };
$target = $t12;
$B = $t12['base'];
$B3 = $t3['base'];
$BG = $tGroup['base'];
printf("  هدف 1-2: «%s» `%s` (#%d في «%s») — الدفع %s\n", $t12['label'], $B, $t12['rank'], $t12['grp'],
    $ordVal($t12) > 0 ? 'اسفل' : 'اعلى');
printf("  هدف 3:   «%s» `%s` (#%d في «%s») — الدفع %s\n", $t3['label'], $B3, $t3['rank'], $t3['grp'],
    $ordVal($t3) > 0 ? 'اسفل' : 'اعلى');
list($i0, $gI0, $g0) = soa_locate($before0, $B);
printf("═══ تجاربُ الحاكمِ الخمس — الدور 1 (مستخدم %d) ═══\n", $uid);
printf("  الهدفُ المنتقى قياسًا: «%s» `%s`\n", $target['label'], $B);
printf("  موضعُه قبل الكلّ: الفهرس %d · #%d داخل «%s»\n\n", $i0, $gI0, $g0);

/* مجموعةٌ بديلةٌ حيّةٌ لتجربتَي المجموعة — من الشجرةِ نفسِها */
list($gi0, $ggI0, $gg0) = soa_locate($before0, $BG);
printf("  هدفُ المجموعة: «%s» `%s` (#%d في «%s»)\n\n", $tGroup['label'], $BG, $ggI0, $gg0);
/* بديلُ ⑤ رأسُ طيٍّ مُصيَّرٌ قائمٌ من غيرِ رأسِه — فالحركةُ عبر الرؤوسِ
   ظاهرةٌ للمقياس (بديلٌ فرعيٌّ داخل الرأسِ نفسِه اعمى الجولةَ السابقة) */
$altGroup = ($anchorGroup !== '' && $anchorGroup !== $gg0) ? $anchorGroup : '';
if ($altGroup === '') {
    foreach ($before0 as $p) { if ($p['g'] !== '' && $p['g'] !== $gg0) { $altGroup = $p['g']; break; } }
}
$altCode = '';
$q = $conn->query("SELECT DISTINCT group_code FROM nav_route_group
                    WHERE group_code <> '" . $e((string) $tGroup['rg']['group_code']) . "' LIMIT 1");
if ($q && ($z = $q->fetch_row())) { $altCode = (string) $z[0]; }
/* مجموعةٌ بديلةٌ من link_groups للتجربة ⑥ — قناةُ nav_items.group_id */
$altGid = 0;
if ($tGroup['ni']) {
    $q = $conn->query("SELECT id FROM link_groups WHERE is_active = 1
                        AND id <> " . (int) $tGroup['ni']['group_id'] . " LIMIT 1");
    if ($q && ($z = $q->fetch_row())) { $altGid = (int) $z[0]; }
}

$EXPS = array(
    array('n' => 1, 'store' => 'nav_items.sort_order',
          'set'    => "UPDATE nav_items SET sort_order = " . $ordVal($t12) . " WHERE id = " . (int) $t12['ni']['id'],
          'revert' => "UPDATE nav_items SET sort_order = " . (int) $t12['ni']['sort_order']
                    . " WHERE id = " . (int) $t12['ni']['id'],
          'watch' => 'order', 'base' => $B),
    array('n' => 2, 'store' => 'nav_canonical.sort_no',
          'set'    => "UPDATE nav_canonical SET sort_no = " . $ordVal($t12) . " WHERE LOWER(route) = '" . $e($B) . "'",
          'revert' => "UPDATE nav_canonical SET sort_no = " . (int) $t12['nc']['sort_no']
                    . " WHERE LOWER(route) = '" . $e($B) . "'",
          'watch' => 'order', 'base' => $B),
    array('n' => 3, 'store' => 'gov_target_nav.item_no',
          'set'    => "UPDATE gov_target_nav SET item_no = " . $ordVal($t3) . " WHERE id = " . (int) $t3['gt']['id'],
          'revert' => "UPDATE gov_target_nav SET item_no = " . (int) $t3['gt']['item_no']
                    . " WHERE id = " . (int) $t3['gt']['id'],
          'watch' => 'order', 'base' => $B3),
    array('n' => 4, 'store' => 'nav_route_group.group_code',
          'set'    => "UPDATE nav_route_group SET group_code = '" . $e($altCode) . "' WHERE LOWER(route) = '" . $e($BG) . "'",
          'revert' => "UPDATE nav_route_group SET group_code = '" . $e((string) $tGroup['rg']['group_code'])
                    . "' WHERE LOWER(route) = '" . $e($BG) . "'",
          'watch' => 'group', 'base' => $BG),
    array('n' => 5, 'store' => 'gov_target_nav.group_ar',
          'set'    => "UPDATE gov_target_nav SET group_ar = '" . $e($altGroup) . "' WHERE id = " . (int) $tGroup['gt']['id'],
          'revert' => "UPDATE gov_target_nav SET group_ar = '" . $e((string) $tGroup['gt']['group_ar'])
                    . "' WHERE id = " . (int) $tGroup['gt']['id'],
          'watch' => 'group', 'base' => $BG),
);
if ($tGroup['ni'] && $altGid > 0) {
    $EXPS[] = array('n' => 6, 'store' => 'nav_items.group_id',
          'set'    => "UPDATE nav_items SET group_id = $altGid WHERE id = " . (int) $tGroup['ni']['id'],
          'revert' => "UPDATE nav_items SET group_id = " . (int) $tGroup['ni']['group_id']
                    . " WHERE id = " . (int) $tGroup['ni']['id'],
          'watch' => 'group', 'base' => $BG);
}
/* ⑧ عمودُ المجموعةِ الذي كتبت فيه اداةُ المحاذاة: nav_canonical.group_name */
$ncg = $conn->query("SELECT group_name FROM nav_canonical WHERE LOWER(route) = '" . $e($B) . "' LIMIT 1")->fetch_assoc();
if ($ncg !== null) {
    $EXPS[] = array('n' => 8, 'store' => 'nav_canonical.group_name',
          'set'    => "UPDATE nav_canonical SET group_name = '" . $e($altGroup) . "' WHERE LOWER(route) = '" . $e($B) . "'",
          'revert' => "UPDATE nav_canonical SET group_name = '" . $e((string) $ncg['group_name'])
                    . "' WHERE LOWER(route) = '" . $e($B) . "'",
          'watch' => 'group', 'base' => $B);
}
/* ⑦ رأسُ طيِّ **غيرِ المُعلَنِ**: nav_route_group على هدفِ 1-2 (بلا صفِّ إعلان) */
if ($t12['rg']) {
    $altCode12 = '';
    $q = $conn->query("SELECT DISTINCT group_code FROM nav_route_group
                        WHERE group_code <> '" . $e((string) $t12['rg']['group_code']) . "' LIMIT 1");
    if ($q && ($z = $q->fetch_row())) { $altCode12 = (string) $z[0]; }
    if ($altCode12 !== '') {
        $EXPS[] = array('n' => 7, 'store' => 'nav_route_group.group_code (غير معلن)',
              'set'    => "UPDATE nav_route_group SET group_code = '" . $e($altCode12) . "' WHERE LOWER(route) = '" . $e($B) . "'",
              'revert' => "UPDATE nav_route_group SET group_code = '" . $e((string) $t12['rg']['group_code'])
                        . "' WHERE LOWER(route) = '" . $e($B) . "'",
              'watch' => 'group', 'base' => $B);
    }
}

$results = array();
foreach ($EXPS as $X) {
    $bx = isset($X['base']) ? $X['base'] : $B;
    $pre = soa_render($ROOT, $RID, $uid);
    list($pi, $pgi, $pg) = soa_locate($pre, $bx);
    if (!$conn->query($X['set'])) { exit("✘ تجربة {$X['n']}: {$conn->error}\n"); }
    $post = soa_render($ROOT, $RID, $uid);
    list($qi, $qgi, $qg) = soa_locate($post, $bx);
    if (!$conn->query($X['revert'])) { exit("✘⛔ ردُّ تجربة {$X['n']} فشل — رُدَّ يدويًّا: {$X['revert']}\n"); }
    $back = soa_render($ROOT, $RID, $uid);
    list($bi, $bgi, $bg) = soa_locate($back, $bx);
    $reverted = ($bi === $pi && $bg === $pg && $bgi === $pgi);
    $moved = ($X['watch'] === 'order') ? ($qgi !== $pgi || $qg !== $pg) : ($qg !== $pg);
    $results[] = array('n' => $X['n'], 'store' => $X['store'], 'moved' => $moved,
                       'pre' => "#$pgi في «{$pg}»", 'post' => "#$qgi في «{$qg}»", 'rev' => $reverted);
    printf("  تجربة %d · %-28s قبل: #%d في «%s» ⇒ بعد: #%d في «%s» ⇒ %s · الردُّ %s\n",
        $X['n'], $X['store'], $pgi, $pg, $qgi, $qg,
        $moved ? '**تحرّك**' : 'لم يتحرّك',
        $reverted ? '✔ تامّ' : '⛔ لم يتطابق');
    if (!$reverted) { exit("⛔ الردُّ لم يُثبَت — توقّفْ وافحص\n"); }
}

/* الحكم */
$orderGov = array(); $groupGov = array();
foreach ($results as $r0) {
    if ($r0['moved']) {
        if (in_array($r0['n'], array(1, 2, 3), true)) { $orderGov[] = $r0['store']; }
        else { $groupGov[] = $r0['store']; }
    }
}
echo "\n  ── الحكمُ المُثبَتُ بحركةِ العدّاد ──\n";
printf("  **الترتيبُ يُكتب في: %s**\n", $orderGov ? implode(' و', $orderGov) : '؟ (لا حركةَ في الثلاثة)');
printf("  **والمجموعةُ تُكتب في: %s**\n", $groupGov ? implode(' و', $groupGov) : '؟ (لا حركةَ في الاثنتين)');

if ($MD) {
    $o = "# حاكمُ ترتيبِ السايدبارِ ومجموعتِه — بحركةِ عدّادٍ لا بقراءةِ شيفرة\n\n"
       . "> أمرُ `SIDEBAR_RENDER_FIX` §٤·٢ · الدور 1 (مستخدم $uid) · الهدفُ «" . $target['label']
       . "» `\$B = $B` منتقًى قياسًا · " . date('Y-m-d H:i') . "\n\n"
       . "| # | المخزن | قبل | بعد التغيير | تحرّك؟ | الردُّ تامّ؟ |\n|---|---|---|---|---|---|\n";
    foreach ($results as $r0) {
        $o .= '| ' . $r0['n'] . ' | `' . $r0['store'] . '` | ' . $r0['pre'] . ' | ' . $r0['post']
            . ' | ' . ($r0['moved'] ? '**نعم**' : 'لا') . ' | ' . ($r0['rev'] ? 'نعم' : '⛔') . " |\n";
    }
    $o .= "\n**الترتيبُ يُكتب في «" . ($orderGov ? implode('» و«', $orderGov) : '؟')
        . "» والمجموعةُ في «" . ($groupGov ? implode('» و«', $groupGov) : '؟') . "»**\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_ORDER_AUTHORITY.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/SIDEBAR_ORDER_AUTHORITY.md\n";
}
