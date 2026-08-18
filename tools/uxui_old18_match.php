<?php
/**
 * tools/uxui_old18_match.php — إثباتُ مطابقةِ روابطِ «إدارة الموقع — قديم» الـ18
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُصيِّر تعريفَ التنقلِ القديمَ (الدور 5) حيًّا بالمُصيِّرِ نفسِه، ثم يطابق
 *   كلَّ رابطٍ من الثمانيةَ عشرَ ثلاثيًّا:
 *     ① صفُّه في المصفوفةِ المعيارية docs/uxui_matrix_20260818.csv
 *     ② ظهورُه في التعريفِ الجديدِ «إدارة الموقع» (الدور 6) المُصيَّرِ حيًّا
 *     ③ ظهورُه في بقيةِ الأدوارِ الجذريةِ (فلا يضيع مسارٌ بأرشفةِ القديم)
 * ◆ الأرشفةُ ليست من عملِ هذه الأداة: القرارُ للمالكِ بعد الإثبات — لا قبله.
 *
 * التشغيل (قراءةٌ فقط):
 *   php tools/uxui_old18_match.php                الجدولُ على الشاشة (Markdown)
 *   php tools/uxui_old18_match.php --md=<path>    كتابةُ الإثباتِ ملفًّا
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── المصفوفة ── */
$fh = fopen($ROOT . '/docs/uxui_matrix_20260818.csv', 'r');
$hdr = fgetcsv($fh); $matrix = array();
while (($r = fgetcsv($fh)) !== false) { $row = array_combine($hdr, $r); $matrix[mb_strtolower(trim($row['route']))] = $row; }
fclose($fh);

/* ── جلساتُ الأدوار (أولُ مستخدمٍ حيٍّ في ايكوبيشن co4) ── */
$roleUsers = array();
$res = mysqli_query($conn, "SELECT CAST(role AS UNSIGNED) r, MIN(id) uid FROM users WHERE company_id = 4 GROUP BY CAST(role AS UNSIGNED)");
while ($x = mysqli_fetch_assoc($res)) { $roleUsers[(int)$x['r']] = (int)$x['uid']; }
$roleNames = array();
$res = mysqli_query($conn, "SELECT id, name FROM roles");
while ($x = mysqli_fetch_assoc($res)) { $roleNames[(int)$x['id']] = $x['name']; }

function uxo_render($conn, $rid, $uid) {
    $_SESSION['user'] = array('id' => $uid, 'role' => (string)$rid, 'company_id' => 4, 'name' => 'old18-probe');
    if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
    $chats = '<li><a href="../chats/index.php" id="sidebarChatLink"><i class="fa fa-comments"></i>'
           . '<span class="sidebar-link-text">المراسلات</span></a></li>' . "\n";
    ob_start();
    $ok = renderUnifiedNavigationV2($conn, (string)$rid, '../', array(), $chats);
    $html = ob_get_clean();
    if (!$ok) { return array(); }
    $out = array(); $group = '— خارج التبويب';
    if (preg_match_all('/<span class="nav-group-name">(?<g>[^<]*)<\/span>|<a\b[^>]*href="(?<h>[^"]*)"[^>]*>(?<in>.*?)<\/a>/us', $html, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            if (isset($m['g']) && $m['g'] !== '') { $group = trim(html_entity_decode($m['g'], ENT_QUOTES, 'UTF-8')); continue; }
            $inner = preg_replace('/<span[^>]*nav-count-badge[^>]*>.*?<\/span>/us', '', $m['in']);
            $label = preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8')));
            $route = preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($m['h'])));
            $out[] = array('group' => $group, 'label' => $label, 'route' => $route);
        }
    }
    return $out;
}

/* ── القديم (5) والجديد (6) وبقيةُ الجذرية ── */
$old = uxo_render($conn, 5, isset($roleUsers[5]) ? $roleUsers[5] : 0);
$new = uxo_render($conn, 6, isset($roleUsers[6]) ? $roleUsers[6] : 0);
$newByRoute = array();
foreach ($new as $p) { $newByRoute[mb_strtolower($p['route'])][] = $p; }

$OTHER_ROLES = array(1,2,3,4,9,12,13,15,16,17,23,25,26,27,28,32,33);
$elsewhere = array(); // route_lc => [role_id,...]
foreach ($OTHER_ROLES as $rid) {
    foreach (uxo_render($conn, $rid, isset($roleUsers[$rid]) ? $roleUsers[$rid] : 0) as $p) {
        $elsewhere[mb_strtolower($p['route'])][$rid] = true;
    }
}

/* ── الجدول ── */
$L = array();
$L[] = '# إثباتُ مطابقةِ روابطِ «إدارة الموقع — قديم» الثمانيةَ عشرَ مع الجديد';
$L[] = '';
$L[] = '· التاريخ: ' . date('Y-m-d H:i') . ' · القديم = تصييرٌ حيٌّ للدور 5 · الجديد = تصييرٌ حيٌّ للدور 6 + صفُّ المصفوفة';
$L[] = '· أمرُ الإنتاج: `php tools/uxui_old18_match.php --md=docs/UXUI_OLD18_MATCH_ar.md`';
$L[] = '· الأرشفةُ **بعد** توقيعِ المالكِ على هذا الإثبات — لا قبله، ولا تنفذها هذه الأداة.';
$L[] = '';
$L[] = '| # | الرابطُ القديم (اسمُه وموضعُه) | المسار | صفُّ المصفوفة | حالتُه | في «إدارة الموقع» الجديدة (الدور 6)؟ | في أدوارٍ أخرى؟ |';
$L[] = '|---|---|---|---|---|---|---|';
$i = 0; $matched = 0; $inNew = 0;
foreach ($old as $p) {
    $i++;
    $lc = mb_strtolower($p['route']);
    $hit = isset($matrix[$lc]) ? $matrix[$lc] : null;
    if ($hit) { $matched++; }
    $n6 = isset($newByRoute[$lc]) ? $newByRoute[$lc] : array();
    if ($n6) { $inNew++; }
    $n6txt = $n6 ? ('نعم — «' . $n6[0]['label'] . '» في «' . $n6[0]['group'] . '»') : '**لا**';
    $othr = isset($elsewhere[$lc]) ? implode('·', array_keys($elsewhere[$lc])) : '—';
    $L[] = '| ' . $i . ' | «' . $p['label'] . '» في «' . $p['group'] . '» | `' . $p['route'] . '` | '
         . ($hit ? ('#' . $hit['n'] . ' «' . $hit['canonical_ar'] . '» في «' . $hit['canonical_group'] . '»') : '**لا صفَّ**')
         . ' | ' . ($hit ? $hit['status'] : '—') . ' | ' . $n6txt . ' | ' . $othr . ' |';
}
$L[] = '';
$L[] = '**الحصيلة:** ' . $i . ' رابطًا مُصيَّرًا حيًّا للدور 5 · له صفٌّ في المصفوفة: ' . $matched . '/' . $i
     . ' · يظهر في الدور 6 الجديد: ' . $inNew . '/' . $i . ' · والباقي مذكورٌ بأدوارِه في العمودِ الأخير.';
$L[] = '';
$out = implode("\n", $L) . "\n";
if (!empty($args['md'])) { file_put_contents($args['md'], $out); echo "MD ⇐ {$args['md']}\n"; }
else { echo $out; }
