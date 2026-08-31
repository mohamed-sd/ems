<?php
/**
 * tools/u13_probe.php — مسحُ القاعدةِ الحيةِ قبلَ حزمة update0013
 * ═══════════════════════════════════════════════════════════════════════════
 * يقيس ما هو قائمٌ فعلًا في نطاقاتِ الحزمة: الأدوارُ الماليةُ والرقابيةُ ·
 * جداولُ الماليةِ والالتزامات · دليلُ الحسابات · الشاشاتُ المسجَّلة ·
 * الأفعالُ · التكليفاتُ والموافقات · مصفوفةُ فصلِ الواجبات.
 *
 * التشغيل: php tools/u13_probe.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$cfg  = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
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
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

function q($db, $sql) { $r = $db->query($sql); if (!$r) { return array('__err' => $db->error); } $o = array(); while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; }
function scalar($db, $sql) { $r = $db->query($sql); if (!$r) { return '؟'; } $x = $r->fetch_row(); return $x ? $x[0] : '—'; }
function hdr($t) { echo "\n" . str_repeat('═', 78) . "\n  $t\n" . str_repeat('═', 78) . "\n"; }

echo "قاعدة: {$cfg['db']} على {$cfg['host']}:{$cfg['port']}\n";

/* ① الأدوار ─────────────────────────────────────────────────────────────── */
hdr('① الأدوارُ القائمة');
$roles = q($db, "SELECT id, name_ar, name_en FROM roles ORDER BY id");
if (isset($roles['__err'])) { echo "خطأ: {$roles['__err']}\n"; }
else {
    printf("  العدد: %d\n", count($roles));
    foreach ($roles as $r) { printf("  %3d · %-42s %s\n", $r['id'], $r['name_ar'], (string) $r['name_en']); }
}

/* ② جداولُ النطاق ───────────────────────────────────────────────────────── */
hdr('② جداولُ النطاقِ الماليةِ والرقابية');
$pats = array('fin_%', 'ob_%', 'obl_%', 'acc_%', 'iaf_%', 'audit_%', 'trs_%', 'tre_%', 'exec_%', 'gov_%', 'sec_%');
foreach ($pats as $p) {
    $t = q($db, "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='{$cfg['db']}' AND TABLE_NAME LIKE '$p' ORDER BY TABLE_NAME");
    if (isset($t['__err']) || !$t) { continue; }
    printf("\n  ▸ %s (%d)\n", $p, count($t));
    foreach ($t as $x) { printf("     %-46s ~%s\n", $x['TABLE_NAME'], (string) $x['TABLE_ROWS']); }
}

/* ③ دليلُ الحسابات ──────────────────────────────────────────────────────── */
hdr('③ دليلُ الحسابات');
echo "  fin_chart_of_accounts: " . scalar($db, "SELECT COUNT(*) FROM fin_chart_of_accounts") . " صفًّا\n";
$cols = q($db, "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$cfg['db']}' AND TABLE_NAME='fin_chart_of_accounts' ORDER BY ORDINAL_POSITION");
foreach ($cols as $c) { printf("     %-28s %s\n", $c['COLUMN_NAME'], $c['COLUMN_TYPE']); }

/* ④ الشاشاتُ المسجَّلة ──────────────────────────────────────────────────── */
hdr('④ سجلُّ الشاشاتِ والقوائم');
foreach (array('nav_items', 'nav_groups', 'gov_screens', 'cmp_screens') as $t) {
    $n = scalar($db, "SELECT COUNT(*) FROM `$t`");
    echo "  $t: $n\n";
}
echo "\n  شاشاتٌ ماليةٌ في nav_items:\n";
$nv = q($db, "SELECT id, code, label_ar, url FROM nav_items WHERE url LIKE '%Finance%' OR url LIKE '%fin_%' OR code LIKE 'FIN%' ORDER BY id LIMIT 80");
foreach ($nv as $x) { printf("     %-5s %-26s %-38s %s\n", $x['id'], (string) $x['code'], $x['label_ar'], $x['url']); }

/* ⑤ الأفعال ─────────────────────────────────────────────────────────────── */
hdr('⑤ قاموسُ الأفعال');
foreach (array('gov_actions', 'actions', 'sec_actions') as $t) {
    $n = scalar($db, "SELECT COUNT(*) FROM `$t`");
    if ($n !== '؟') { echo "  $t: $n\n"; }
}

/* ⑥ التكليفاتُ والموافقات ──────────────────────────────────────────────── */
hdr('⑥ التكليفاتُ وموافقاتُ الرئيسِ وفصلُ الواجبات — أموجودةٌ؟');
foreach (array('exec_approvals', 'exec_decisions', 'assignments', 'role_assignments', 'sec_sod_pairs', 'gov_sod', 'sod_pairs', 'ceo_approvals') as $t) {
    $n = scalar($db, "SELECT COUNT(*) FROM `$t`");
    printf("  %-24s %s\n", $t, $n === '؟' ? 'غيرُ موجود' : $n);
}

/* ⑦ الأدوارُ الماليةُ القديمة ──────────────────────────────────────────── */
hdr('⑦ حاملو الأدوارِ الماليةِ القديمة');
$u = q($db, "SELECT r.id, r.name_ar, COUNT(u.id) c FROM roles r LEFT JOIN users u ON CAST(u.role AS SIGNED)=r.id GROUP BY r.id ORDER BY r.id");
foreach ($u as $x) { if ((int) $x['c'] > 0) { printf("  %3d · %-42s %d مستخدمًا\n", $x['id'], $x['name_ar'], $x['c']); } }

/* ⑧ جداولُ العقود ──────────────────────────────────────────────────────── */
hdr('⑧ جداولُ العقودِ والأحداثِ الماليةِ الحية');
foreach (array('contracts', 'contract_registry', 'ems_business_events', 'fin_financial_events', 'fin_journal_entries', 'fin_journal_lines') as $t) {
    $n = scalar($db, "SELECT COUNT(*) FROM `$t`");
    printf("  %-28s %s\n", $t, $n === '؟' ? 'غيرُ موجود' : $n);
}

echo "\n";
$db->close();
