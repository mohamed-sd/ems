<?php
/**
 * tools/injexec01_dept_snapshot.php — لقطةُ نقطةِ الأمانِ للجولة (المرحلة صفر)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما تُثبته**: حالةَ التنقّلِ والمساراتِ والصلاحياتِ ودفترِ الدورةِ **قبلَ**
 *   أوَّلِ تعديل. وهي المرجعُ الذي تُقاس عليه بوابتا «صفر فقد» و«صفر تبويبٍ
 *   مكسور» بعدَ الجولة — فالبوابةُ بلا مرجعٍ مجمَّدٍ انطباعٌ لا قياس.
 *
 * ◆ **ولا تُقصر اللقطةُ على الإداراتِ الخمس**: تُلتقط الشجرةُ كلُّها ثم تُقرأ
 *   الخمسُ منها. لأن كسرَ مسارٍ في إدارةٍ مجاورةٍ فقدٌ أيضًا — والبوابةُ التي
 *   تقيس ما اخترتَ قياسَه لا تُغلق شيئًا.
 *
 * ◆ **والوجودُ على القرصِ يُقاس بالمقصدِ لا بالنصِّ المخزَّن**: المسارُ ذو
 *   `?view=` أو `?type=` يُقطع عند أوَّلِ `?` أو `#` قبلَ الفحص.
 *
 * التشغيل:
 *   php tools/injexec01_dept_snapshot.php --write     يكتب اللقطةَ المجمَّدة
 *   php tools/injexec01_dept_snapshot.php             يطبع مقارنةً بالحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DEPTS = array(
    'المبيعات والعقود', 'إدارة الموردين', 'المالية والخزينة',
    'إدارة التشغيل', 'إدارة الأسطول',
);

/** يقطع المعاملات ويوحّد الفواصل — فالمقصدُ ملفٌّ لا نصٌّ مخزَّن */
function route_file($r)
{
    $r = str_replace('\\', '/', trim((string) $r));
    $r = preg_replace('/[?\#].*$/', '', $r);
    return ltrim($r, '/');
}

$snap = array('captured_at' => date('c'), 'depts' => $DEPTS);

/* ① التنقّل الحيّ — كلُّ صفٍّ بمفتاحِه المركَّب */
$rows = array();
$q = $conn->query("SELECT role_id, route, label_ar, group_id, sort_order, active, permission_code
                     FROM `nav_items` ORDER BY role_id, route, sort_order");
while ($x = $q->fetch_assoc()) {
    $rows[] = array(
        'role'  => (int) $x['role_id'],
        'route' => (string) $x['route'],
        'file'  => route_file($x['route']),
        'label' => (string) $x['label_ar'],
        'grp'   => (int) $x['group_id'],
        'ord'   => (int) $x['sort_order'],
        'act'   => (int) $x['active'],
        'perm'  => (string) $x['permission_code'],
    );
}
$snap['nav_items'] = $rows;

/* ② المجموعات */
$g = array();
$q = $conn->query("SELECT id, name, group_code, owner_role_id, display_order, stage_no, stage_title, is_active
                     FROM `link_groups` ORDER BY id");
while ($x = $q->fetch_assoc()) { $g[(int) $x['id']] = $x; }
$snap['link_groups'] = $g;

/* ③ الوحدات والصلاحيات */
$m = array();
$q = $conn->query("SELECT id, name, code, owner_role_id, group_id, is_link FROM `modules` ORDER BY id");
while ($x = $q->fetch_assoc()) { $m[(int) $x['id']] = $x; }
$snap['modules'] = $m;

$rp = array();
$q = $conn->query("SELECT role_id, module_id, can_view, can_add, can_edit, can_delete
                     FROM `role_permissions` ORDER BY role_id, module_id");
while ($x = $q->fetch_assoc()) {
    $rp[$x['role_id'] . ':' . $x['module_id']] =
        ((int) $x['can_view']) . ((int) $x['can_add']) . ((int) $x['can_edit']) . ((int) $x['can_delete']);
}
$snap['role_permissions'] = $rp;

/* ④ دفترُ الدورة — بالإداراتِ كلِّها */
$cy = array();
$q = $conn->query("SELECT dept_name, stage_order, stage_name, group_name, screen_title, screen_file,
                          output_doc, resp_role, next_state
                     FROM `gov_screen_cycle` ORDER BY dept_name, stage_order, screen_file");
while ($x = $q->fetch_assoc()) { $cy[] = $x; }
$snap['screen_cycle'] = $cy;

/* ⑤ وجودُ كلِّ مقصدٍ على القرص
 * ◆ **فخُّ الاسمِ العاري**: `gov_screen_cycle.screen_file` يحمل اسمًا عاريًا بلا
 *   مجلد (`clients.php`) بينما `nav_items.route` يحمل مسارًا كاملًا. فقياسُ
 *   الوجودِ بضمِّ الجذرِ إلى الاسمِ العاري يُخرج «كلُّها مفقودة» — وهو **فجوةٌ
 *   وهميةٌ صنعها القياسُ لا النظام**. ⇒ يُبنى فهرسُ أسماءٍ ويُحَلُّ به.
 */
$SKIP = array('vendor', 'node_modules', '.git', 'docs', 'tests', 'tools', 'storage',
              'database', 'logs', 'uploads', 'assets', '.claude', '.ssdiff');
$index = array();                       /* basename => [paths…] */
$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
        function ($cur) use ($ROOT, $SKIP) {
            if (!$cur->isDir()) { return true; }
            $rel = str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT) + 1));
            $top = explode('/', $rel)[0];
            return !in_array($top, $SKIP, true);
        }
    )
);
foreach ($it as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($ROOT) + 1));
    $index[basename($rel)][] = $rel;
}
$snap['disk_index_count'] = count($index);

$files = array();
foreach ($rows as $r)  { if ($r['file'] !== '') { $files[$r['file']] = true; } }
foreach ($cy as $c)    { $f = route_file($c['screen_file']); if ($f !== '') { $files[$f] = true; } }
$disk = array();
foreach (array_keys($files) as $f) {
    if (is_file($ROOT . '/' . $f)) { $disk[$f] = 1; continue; }
    $b = basename($f);
    $disk[$f] = isset($index[$b]) ? 1 : 0;   /* الاسمُ العاري يُحَلُّ بالفهرس */
}
$snap['disk'] = $disk;

/* ⑥ ملخّصٌ للإداراتِ الخمس */
$per = array();
foreach ($DEPTS as $d) {
    $n = 0; $miss = 0;
    foreach ($cy as $c) {
        if ($c['dept_name'] !== $d) { continue; }
        $n++;
        $f = route_file($c['screen_file']);
        if ($f === '' || empty($disk[$f])) { $miss++; }
    }
    $per[$d] = array('cycle_rows' => $n, 'missing_on_disk' => $miss);
}
$snap['dept_summary'] = $per;

$OUT = $ROOT . '/docs/INJEXEC01/snapshot';
if (in_array('--write', $argv, true)) {
    if (!is_dir($OUT)) { @mkdir($OUT, 0777, true); }
    file_put_contents($OUT . '/dept_baseline.json',
        json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "✔ كُتبت اللقطةُ المجمَّدة: docs/INJEXEC01/snapshot/dept_baseline.json\n";
}

echo "══ لقطةُ نقطةِ الأمانِ — INJ-EXEC-01 المرحلةُ صفر ══\n";
printf("  صفوفُ التنقّل: %d · مجموعات: %d · وحدات: %d · منحُ صلاحية: %d\n",
       count($rows), count($g), count($m), count($rp));
printf("  صفوفُ دفترِ الدورة: %d · مقاصدُ فريدة: %d · **مفقودةٌ على القرص: %d**\n",
       count($cy), count($disk), count(array_filter($disk, function ($v) { return $v === 0; })));
echo str_repeat('─', 78) . "\n";
foreach ($per as $d => $v) {
    printf("  %-24s دورة=%-4d غيرُ موجودٍ على القرص=%d\n", $d, $v['cycle_rows'], $v['missing_on_disk']);
}
echo str_repeat('─', 78) . "\n";
echo "◆ والمفقودُ على القرصِ **ليس بالضرورةِ قدرةً مفقودة**: قد يكون قائمًا باسمٍ\n";
echo "  آخرَ — وهذا ما تحسمه بوابةُ المصالحةِ لا هذا العدُّ.\n";
