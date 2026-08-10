<?php
/**
 * tools/fix_probe.php — جسٌّ حيٌّ لحزمة FIX (التصحيحات الثلاث)
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يكتب شيئًا. يقرأ القاعدةَ الحيةَ ويجيب عن أسئلةِ الوثائقِ بالأرقام.
 * التشغيل: php tools/fix_probe.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_PORT') { $cfg['port'] = (int) $v; }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit("تعذّر الاتصال: " . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

function one($db, $sql) { $r = $db->query($sql); if (!$r) { return 'ERR: ' . $db->error; } $x = $r->fetch_row(); return $x ? $x[0] : null; }
function rows($db, $sql, $n = 20) { $r = $db->query($sql); if (!$r) { echo "  [SQL ERR] " . $db->error . "\n"; return array(); } $o = array(); while (($x = $r->fetch_assoc()) && count($o) < $n) { $o[] = $x; } return $o; }
function h($t) { echo "\n══ $t ══\n"; }
function p($k, $v) { echo str_pad($k, 56, '.') . ' ' . $v . "\n"; }

echo "قاعدة: {$cfg['db']}@{$cfg['host']}:{$cfg['port']}\n";

/* ── ① modules / role_permissions البنية ─────────────────────────────── */
h('① بنية الصلاحيات');
foreach (array('modules', 'role_permissions', 'nav_items', 'nav_groups', 'roles') as $t) {
    $e = (int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'");
    p("جدول $t", $e ? one($db, "SELECT COUNT(*) FROM `$t`") . ' صف' : 'غير موجود');
}
$cols = rows($db, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' ORDER BY ORDINAL_POSITION", 50);
p('أعمدة role_permissions', implode(',', array_column($cols, 'COLUMN_NAME')));
$cols = rows($db, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='modules' ORDER BY ORDINAL_POSITION", 50);
p('أعمدة modules', implode(',', array_column($cols, 'COLUMN_NAME')));
$cols = rows($db, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_items' ORDER BY ORDINAL_POSITION", 50);
p('أعمدة nav_items', implode(',', array_column($cols, 'COLUMN_NAME')));

/* ── ② FN-01 · سايدبار الأدوار 31/32/33 ──────────────────────────────── */
h('② FN-01 · الأدوار 31 · 32 · 33');
$rcols = array_column(rows($db, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roles' ORDER BY ORDINAL_POSITION", 40), 'COLUMN_NAME');
p('أعمدة roles', implode(',', $rcols));
$nameCol = in_array('role_name', $rcols, true) ? 'role_name' : (in_array('name', $rcols, true) ? 'name' : 'id');
foreach (rows($db, "SELECT id, `$nameCol` AS nm FROM roles WHERE id BETWEEN 26 AND 40 ORDER BY id", 30) as $r) {
    $navN = (int) one($db, "SELECT COUNT(*) FROM nav_items WHERE role_id=" . (int) $r['id'] . " AND active=1");
    $permN = (int) one($db, "SELECT COUNT(*) FROM role_permissions WHERE role_id=" . (int) $r['id'] . " AND can_view=1");
    p("دور {$r['id']} — {$r['nm']}", "nav=$navN · grants=$permN");
}

/* ── ③ FN-02 · صفوف التنقل الميتة ───────────────────────────────────── */
h('③ FN-02 · الروابط الميتة');
p('nav_items إجمالًا', one($db, "SELECT COUNT(*) FROM nav_items"));
p("route LIKE '../../%'", one($db, "SELECT COUNT(*) FROM nav_items WHERE route LIKE '../../%'"));
p('module_id NULL/0 مع perm_code غير فارغ', one($db, "SELECT COUNT(*) FROM nav_items WHERE (module_id IS NULL OR module_id=0)"));
foreach (rows($db, "SELECT id,role_id,label,route,module_id FROM nav_items WHERE route LIKE '../../%' ORDER BY id", 8) as $r) {
    p("  #{$r['id']} دور{$r['role_id']}", $r['route'] . ' | mod=' . var_export($r['module_id'], true) . ' | ' . $r['label']);
}

/* ── ④ FN-09 · حدود السلطة ──────────────────────────────────────────── */
h('④ FN-09 · gov_authority_limits');
$e = (int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_authority_limits'");
if ($e) {
    $cols = rows($db, "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_authority_limits' ORDER BY ORDINAL_POSITION", 50);
    foreach ($cols as $c) { p('  عمود ' . $c['COLUMN_NAME'], $c['COLUMN_TYPE']); }
    p('عدد الحدود', one($db, "SELECT COUNT(*) FROM gov_authority_limits"));
    p('بأكواد أفعال', one($db, "SELECT COUNT(*) FROM gov_authority_limits WHERE action_codes IS NOT NULL AND action_codes<>''"));
    foreach (rows($db, "SELECT * FROM gov_authority_limits ORDER BY id", 25) as $r) {
        p('  #' . $r['id'], mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 260));
    }
} else { p('gov_authority_limits', 'غير موجود'); }

/* ── ⑤ التصدير والحقول الحساسة ──────────────────────────────────────── */
h('⑤ RF-03 · الحقول الحساسة');
foreach (array('scr_sensitive_fields', 'sensitive_read_log', 'gov_export_log', 'security_log') as $t) {
    $e = (int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'");
    p("جدول $t", $e ? one($db, "SELECT COUNT(*) FROM `$t`") . ' صف' : 'غير موجود');
}

/* ── ⑥ FN-07/FN-08 · المخزون والنقل ─────────────────────────────────── */
h('⑥ FN-07/08 · جداول المخزون والنقل');
foreach (array('proc_stock_move', 'transfer_orders', 'transfer_events', 'transfer_cost_lines', 'proc_issue', 'proc_issue_line') as $t) {
    $e = (int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'");
    p("جدول $t", $e ? one($db, "SELECT COUNT(*) FROM `$t`") . ' صف' : 'غير موجود');
}
$cols = rows($db, "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proc_stock_move' ORDER BY ORDINAL_POSITION", 50);
p('أعمدة proc_stock_move', implode(',', array_column($cols, 'COLUMN_NAME')));
$cols = rows($db, "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transfer_events' ORDER BY ORDINAL_POSITION", 50);
p('أعمدة transfer_events', implode(',', array_column($cols, 'COLUMN_NAME')));
$cols = rows($db, "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transfer_orders' ORDER BY ORDINAL_POSITION", 60);
p('أعمدة transfer_orders', implode(',', array_column($cols, 'COLUMN_NAME')));

/* ── ⑦ الأفعال المسجَّلة ─────────────────────────────────────────────── */
h('⑦ قاموس الأفعال');
foreach (array('nav09_action_map', 'processed_operations', 'ems_processed_operations') as $t) {
    $e = (int) one($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'");
    p("جدول $t", $e ? one($db, "SELECT COUNT(*) FROM `$t`") . ' صف' : 'غير موجود');
}

echo "\nتمّ.\n";
