<?php
/**
 * tools/injint01/dept_map.php — خريطةُ الإداراتِ: مساحةٌ · أدوارٌ · حسابٌ حيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا مانيفستَ لسبعَ عشرةَ إدارة**، والعُدّةُ القائمةُ تحتاج واحدًا لكلٍّ.
 *   فتُبنى الخريطةُ **من السجلّاتِ الحاكمة** — `nav_targets` للمساحاتِ،
 *   و`navarch_role_workspace` لربطِ الدورِ بمساحتِه، و`users` للحسابِ الحيّ.
 *
 * ⛔ **ولا تُخمَّن مساحةُ الدور**: تُسأل الدالّةُ التي يسألها المُصيِّرُ نفسُه
 *   (`navarch_role_workspace`) — فما يُبنى على تخمينٍ يُقاس على غيرِ محلِّه.
 *
 * ⛔ **والدورُ بلا حسابٍ حيٍّ لا يُقاس تصييرًا**: يُسمَّى ويُخطَّى — ولا يُحسَب
 *   نظيفًا ولا معطوبًا. (‏«ما لم تستطع قياسَه — سمِّه ولا تخمّنه».)
 *
 * التشغيل: php tools/injint01/dept_map.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($conn->connect_errno) { exit('تعذّر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/navarch_renderer.php';
$rows = function ($q) use ($conn) { $r = $conn->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

/* ═══ ① كلُّ دورٍ ومساحتُه — بالدالّةِ الحاكمةِ لا بتخمين ═══════════════════ */
$ws = array();
foreach ($rows('SELECT id, name FROM roles ORDER BY id') as $r) {
    $w = navarch_role_workspace($conn, (int) $r['id']);
    if ($w === null) { $w = '—'; }
    $ws[$w]['roles'][] = (int) $r['id'];
    $ws[$w]['names'][] = $r['name'];
}

/* ═══ ② حسابٌ حيٌّ لكلِّ مساحة ═══════════════════════════════════════════ */
$acc = array();
foreach ($rows("SELECT u.id, u.username, COALESCE(NULLIF(u.role,0), u.role_id) rid
                  FROM users u WHERE u.status='active' AND u.company_id=4 ORDER BY u.id") as $u) {
    $acc[(int) $u['rid']][] = $u;
}

/* ═══ ③ الأسطحُ المُعلَنةُ لكلِّ مساحة ═══════════════════════════════════ */
$norm = function ($r) {
    $s = strtolower(ltrim(preg_replace('~^(\.\./)+~', '', (string) $r), '/'));
    $s = preg_replace('~[?#].*$~', '', $s);
    if ($s !== '' && substr($s, -4) !== '.php') { $s .= '.php'; }
    return $s;
};
$plc = array();
foreach ($rows("SELECT workspace_id, route FROM nav_placements WHERE active=1") as $r) { $plc[$r['workspace_id']][$norm($r['route'])] = 1; }
foreach ($rows('SELECT workspace_id, route FROM nav_workspace_placements') as $r) { $plc[$r['workspace_id']][$norm($r['route'])] = 1; }

$map = array();
ksort($ws);
printf("%-9s %-30s %-7s %-9s %-9s %s\n", 'المساحة', 'الأدوار', 'أدوار', 'مواضع', 'حساب', 'المستخدم');
echo str_repeat('-', 92) . "\n";
foreach ($ws as $w => $d) {
    if ($w === '—') { continue; }
    $who = null;
    foreach ($d['roles'] as $rid) { if (!empty($acc[$rid])) { $who = $acc[$rid][0]; break; } }
    $n = isset($plc[$w]) ? count($plc[$w]) : 0;
    printf("%-9s %-30s %-7d %-9d %-9s %s\n", $w, mb_substr(implode('·', $d['names']), 0, 28),
        count($d['roles']), $n, $who ? '✔' : '⛔', $who ? $who['username'] . ' (#' . $who['id'] . ')' : '—');
    $map[$w] = array('workspace' => $w, 'roles' => $d['roles'], 'role_names' => $d['names'],
        'placements' => $n, 'user' => $who ? $who['username'] : null, 'user_id' => $who ? (int) $who['id'] : null);
}
if (isset($ws['—'])) { printf("\n⛔ أدوارٌ بلا مساحةٍ (%d): %s\n", count($ws['—']['roles']), implode(' · ', $ws['—']['names'])); }

file_put_contents($ROOT . '/docs/injint01/dept_map.json', json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("\n◆ مساحاتٌ قابلةٌ للفحص: %d · الخريطة: docs/injint01/dept_map.json\n", count($map));
