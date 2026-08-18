<?php
/**
 * tools/user_guide_baseline.php — بصمةُ قياسٍ لملفِّ دليلِ إدارةٍ في `user_guide/`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المشكلةُ التي تحلُّها: المالكُ يقول «حدِّث دليلَ إدارةِ كذا» ولا يذكر ما نُفِّذ —
 *   فاكتشافُ الفرقِ مسؤوليةُ الدليل. وبلا لقطةٍ سابقةٍ يصير الاكتشافُ تخمينًا.
 * ◆ ما تحفظه: شاشاتُ الدورِ ومراحلُه وأفعالُه وجداولُه وصلاحياتُه وحساباتُه،
 *   **وبصمةَ محتوى كلِّ ملفِّ شاشة** — فتغيُّرُ سطرٍ واحدٍ في شاشةٍ مشترَكةٍ يظهر
 *   في كلِّ إدارةٍ تعرضها ولا يتخلّف ملفٌّ واحد.
 * ◆ قراءةٌ فقط — لا تكتب في القاعدةِ حرفًا.
 *
 * التشغيل:  php tools/user_guide_baseline.php --role=24 --slug=tickets
 *           php tools/user_guide_baseline.php --role=24 --slug=tickets --diff
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
require_once __DIR__ . '/fix_lib.php';

$ROOT = dirname(__DIR__);
$opt = array('role' => 0, 'slug' => '', 'diff' => false);
foreach ($argv as $a) {
    if (strpos($a, '--role=') === 0) { $opt['role'] = (int) substr($a, 7); }
    if (strpos($a, '--slug=') === 0) { $opt['slug'] = substr($a, 7); }
    if ($a === '--diff') { $opt['diff'] = true; }
}
if ($opt['role'] <= 0 || $opt['slug'] === '') { exit("usage: php tools/user_guide_baseline.php --role=NN --slug=name [--diff]\n"); }

$db = fix_db();
$RID = $opt['role'];
$out = array();

/* ── الدورُ وفروعُه وحساباتُه ── */
$r = $db->query("SELECT id,name,parent_role_id,level,role_scope,status,oversight_role_id FROM roles WHERE id={$RID}");
$out['role'] = $r ? $r->fetch_assoc() : null;
$kids = array();
$r = $db->query("SELECT id,name FROM roles WHERE parent_role_id={$RID} AND id<>{$RID}");
while ($r && ($x = $r->fetch_assoc())) { $kids[] = $x; }
$out['sub_roles'] = $kids;
$ids = $RID; foreach ($kids as $k) { $ids .= ',' . (int) $k['id']; }

$users = array();
$r = $db->query("SELECT id,name,username,status FROM users WHERE role_id IN ($ids) AND is_deleted=0 ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $users[] = $x; }
$out['users'] = $users;

/* ── المراحلُ والمجموعات ── */
$stages = array();
$r = $db->query("SELECT g.id,g.stage_no,g.stage_title,g.name,g.display_order,g.owner_role_id
                 FROM link_groups g WHERE g.owner_role_id IN ($ids) AND g.is_active=1
                 ORDER BY g.owner_role_id, COALESCE(g.stage_no,999), g.display_order");
while ($r && ($x = $r->fetch_assoc())) { $stages[] = $x; }
$out['stages'] = $stages;

/* ── الشاشاتُ وبصمةُ محتواها ── */
$screens = array();
$r = $db->query("SELECT DISTINCT n.route, n.label_ar, g.stage_no, g.name AS grp, g.is_active AS grp_active
                 FROM nav_items n LEFT JOIN link_groups g ON g.id=n.group_id
                 WHERE n.active=1 AND n.role_id IN ($ids) ORDER BY n.route");
while ($r && ($x = $r->fetch_assoc())) {
    $p = ltrim(preg_replace('~[?#].*$~', '', (string) $x['route']), '/');
    $f = $ROOT . '/' . $p;
    $screens[] = array(
        'route' => $x['route'], 'label' => $x['label_ar'], 'stage_no' => $x['stage_no'],
        'group' => $x['grp'], 'group_active' => (int) $x['grp_active'], 'file' => $p,
        'exists' => is_file($f) ? 1 : 0,
        'lines' => is_file($f) ? (substr_count(file_get_contents($f), "\n") + 1) : 0,
        'sha1' => is_file($f) ? sha1_file($f) : null,
    );
}
$out['screens'] = $screens;

/* ── الصلاحيات ── */
$perms = array();
$r = $db->query("SELECT m.code, m.name, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                 FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                 WHERE rp.role_id={$RID} AND rp.can_view=1 ORDER BY m.code");
while ($r && ($x = $r->fetch_assoc())) { $perms[$x['code']] = $x['can_view'] . $x['can_add'] . $x['can_edit'] . $x['can_delete']; }
$out['permissions'] = $perms;

/* ── الأفعالُ المسجَّلةُ لشاشاتِ الإدارة ── */
$acts = array();
$files = array(); foreach ($screens as $s) { $files[strtolower(basename($s['file']))] = 1; }
$r = $db->query("SELECT a.canonical_code,a.label_ar,a.state,a.write_class,a.event_name,a.canonical_file,f.real_path
                 FROM nav09_action_map a LEFT JOIN nav09_file_map f ON f.canonical_file=a.canonical_file
                 ORDER BY a.canonical_code");
while ($r && ($x = $r->fetch_assoc())) {
    $rp = strtolower(basename((string) $x['real_path']));
    if ($rp !== '' && isset($files[$rp])) { $acts[$x['canonical_code']] = $x; }
}
$out['actions'] = $acts;

/* ── جداولُ النطاقِ وأعدادُها ── */
$prefixes = array(24 => array('ticket', 'tkt'));
$pfx = isset($prefixes[$RID]) ? $prefixes[$RID] : array();
$tbl = array();
if ($pfx) {
    $like = array(); foreach ($pfx as $p) { $like[] = "TABLE_NAME LIKE '" . $db->real_escape_string($p) . "%'"; }
    $r = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
                     AND TABLE_TYPE='BASE TABLE' AND (" . implode(' OR ', $like) . ") ORDER BY TABLE_NAME");
    while ($r && ($x = $r->fetch_row())) {
        $c = $db->query('SELECT COUNT(*) FROM `' . $x[0] . '`');
        $tbl[$x[0]] = $c ? (int) $c->fetch_row()[0] : null;
    }
}
$out['tables'] = $tbl;

/* ── لحظةُ القياسِ والالتزام ── */
$commit = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
$branch = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --abbrev-ref HEAD 2>&1'));
$out['_meta'] = array('role_id' => $RID, 'slug' => $opt['slug'], 'commit' => $commit, 'branch' => $branch,
    'measured_at' => date('Y-m-d H:i:s'), 'db' => $db->query('SELECT DATABASE()')->fetch_row()[0]);

$dir = $ROOT . '/user_guide/_baseline';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
$path = $dir . '/' . sprintf('%02d', $RID) . '_' . $opt['slug'] . '.json';

/* ── المقارنةُ بالبصمةِ السابقة ── */
if ($opt['diff'] && is_file($path)) {
    $old = json_decode(file_get_contents($path), true);
    $changes = array();
    $oldS = array(); foreach ($old['screens'] as $s) { $oldS[$s['route']] = $s; }
    $newS = array(); foreach ($out['screens'] as $s) { $newS[$s['route']] = $s; }
    foreach ($newS as $k => $s) {
        if (!isset($oldS[$k])) { $changes[] = "شاشةٌ جديدة: $k ({$s['label']})"; continue; }
        if ($oldS[$k]['sha1'] !== $s['sha1']) { $changes[] = "تغيّر محتوى الشاشة: {$s['file']} (" . $oldS[$k]['lines'] . " ⇐ " . $s['lines'] . " سطرًا)"; }
        if ($oldS[$k]['stage_no'] !== $s['stage_no']) { $changes[] = "انتقلت مرحلةُ الشاشة: $k ({$oldS[$k]['stage_no']} ⇐ {$s['stage_no']})"; }
        if ((int) $oldS[$k]['group_active'] !== (int) $s['group_active']) { $changes[] = "تبدّل ظهورُ الشاشة: $k"; }
    }
    foreach ($oldS as $k => $s) { if (!isset($newS[$k])) { $changes[] = "شاشةٌ خرجت: $k ({$s['label']})"; } }
    $oldG = array(); foreach ($old['stages'] as $g) { $oldG[$g['id']] = $g; }
    foreach ($out['stages'] as $g) {
        if (!isset($oldG[$g['id']])) { $changes[] = "مرحلةٌ/مجموعةٌ جديدة: [{$g['stage_no']}] {$g['name']}"; }
        elseif ($oldG[$g['id']]['stage_title'] !== $g['stage_title']) { $changes[] = "تغيّر عنوانُ المرحلة: «{$oldG[$g['id']]['stage_title']}» ⇐ «{$g['stage_title']}»"; }
    }
    foreach ($out['actions'] as $c => $a) {
        if (!isset($old['actions'][$c])) { $changes[] = "فعلٌ جديد: $c"; }
        elseif ($old['actions'][$c]['state'] !== $a['state']) { $changes[] = "تبدّلت حالةُ الفعل $c: {$old['actions'][$c]['state']} ⇐ {$a['state']}"; }
    }
    foreach ($out['permissions'] as $c => $v) {
        if (!isset($old['permissions'][$c])) { $changes[] = "صلاحيةٌ جديدة: $c ($v)"; }
        elseif ($old['permissions'][$c] !== $v) { $changes[] = "تبدّلت صلاحيةُ $c: {$old['permissions'][$c]} ⇐ $v"; }
    }
    foreach ($out['tables'] as $t => $n) {
        $o = isset($old['tables'][$t]) ? $old['tables'][$t] : null;
        if ($o === null) { $changes[] = "جدولٌ جديد: $t ($n صفًّا)"; }
        elseif ($o !== $n) { $changes[] = "تبدّلت صفوفُ $t: $o ⇐ $n"; }
    }
    echo "══ الفرقُ منذ آخرِ بصمة (" . $old['_meta']['measured_at'] . " · " . $old['_meta']['commit'] . ") ══\n";
    if (!$changes) { echo "  لا فرق — الدليلُ ما يزال مطابقًا.\n"; }
    else { foreach ($changes as $c) { echo "  • $c\n"; } }
    echo "  المجموع: " . count($changes) . " تغيُّرًا\n\n";
}

file_put_contents($path, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "بصمةٌ محفوظة: user_guide/_baseline/" . basename($path) . "\n";
printf("  شاشات=%d · مراحل=%d · أفعال=%d · جداول=%d · صلاحيات=%d · حسابات=%d\n",
    count($out['screens']), count($out['stages']), count($out['actions']), count($out['tables']), count($out['permissions']), count($out['users']));
