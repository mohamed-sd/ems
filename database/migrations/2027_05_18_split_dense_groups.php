<?php
/**
 * 2027_05_18_split_dense_groups.php
 * ═══════════════════════════════════════════════════════════════════════════
 * شطرُ المجموعاتِ المتكدّسة (تنبيهُ nav_seven ③: > 12 عنصرًا ظاهرًا)
 *
 * تُشطر إلى تتماتٍ بسبعةٍ كحدٍّ — **خارجَ نطاقِ ورقةِ nav09 حصرًا**:
 * مجموعاتُ «أخرى — للمراجعة» (n9s99 معفاةٌ من مقارنةِ الورقةِ بالتصميم)
 * ومجموعاتُ المالكِ n9o. مجموعاتُ n9s العاملةُ **لا تُمسّ** — شطرُها يكسر
 * أمانةَ الموضع. والروابطُ تبقى بترتيبِها، إنما تتوزع صفحاتٍ مقروءة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ شطرُ المتكدّس (>12) خارجَ نطاقِ الورقة ══\n\n";
$rs = $conn->query(
    "SELECT g.id, g.name, g.group_code, g.owner_role_id, g.icon, g.display_order, g.stage_no, g.stage_title,
            COUNT(*) n
     FROM link_groups g JOIN nav_items i ON i.group_id = g.id AND i.active = 1
     WHERE (g.group_code LIKE 'n9s99_others%' OR g.group_code LIKE 'n9o%')
     GROUP BY g.id HAVING n > 12 ORDER BY n DESC");
$LET = array('ب', 'ج', 'د', 'هـ', 'و', 'ز', 'ح');
$split = 0;
while ($g = $rs->fetch_assoc()) {
    $gid = (int) $g['id']; $n = (int) $g['n'];
    printf("  %-34s دور %-3s %d عنصرًا\n", mb_substr($g['name'], 0, 32), $g['owner_role_id'], $n);
    $ids = array();
    $r2 = $conn->query("SELECT id FROM nav_items WHERE group_id=$gid AND active=1 ORDER BY sort_order, id");
    while ($x = $r2->fetch_row()) { $ids[] = (int) $x[0]; }
    $chunks = array_chunk(array_slice($ids, 7), 7);
    foreach ($chunks as $ci => $chunk) {
        $suffix = $LET[$ci] ?? ('ط' . $ci);
        $code = $conn->real_escape_string($g['group_code'] . '_' . ($ci + 2));
        $q = $conn->query("SELECT id FROM link_groups WHERE group_code='$code'");
        $ng = (int) ($q && $q->num_rows ? $q->fetch_row()[0] : 0);
        if (!$ng) {
            $st = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $nm = $g['name'] . ' (' . $suffix . ')';
            $ord = (int) $g['display_order'] + $ci + 1;
            $role = (int) $g['owner_role_id']; $sn = (int) $g['stage_no']; $stt = (string) $g['stage_title'];
            $st->bind_param('ssisiis', $nm, $code, $role, $g['icon'], $ord, $sn, $stt);
            $st->execute(); $ng = (int) $conn->insert_id; $st->close();
        }
        $conn->query("UPDATE nav_items SET group_id=$ng WHERE id IN (" . implode(',', $chunk) . ")");
    }
    $split++;
}
echo "\n  شُطرت $split مجموعةً إلى تتماتٍ بسبعة\n";
$r = $conn->query("SELECT COUNT(*) FROM (SELECT g.id FROM link_groups g JOIN nav_items i ON i.group_id=g.id AND i.active=1
                   GROUP BY g.id HAVING COUNT(*)>12) d");
echo '  مجموعاتٌ فوقَ 12 بعدًا: ' . $r->fetch_row()[0] . "\n";
echo "\n✔ تمّت\n";
