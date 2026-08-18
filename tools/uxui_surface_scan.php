<?php
/**
 * tools/uxui_surface_scan.php — جردُ أسطحِ العرضِ · **قراءةٌ فقط**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (ثامنًا ①): «لا تعتبر 753 ملفًّا = 753 شاشةً منطقية… فالملفُّ
 *   قد ينتج أكثرَ من سطح، والسطحُ قد يتركب من ملفات. **والمقامُ الحاكمُ أسطحٌ
 *   قابلةٌ للتصييرِ لا عددُ ملفات**».
 *
 * ◆ فالجردُ يُقيم دليلًا لكلِّ سطحٍ ولا يخمّن:
 *   ① NAVIGABLE      — له صفٌّ في `nav_canonical` أو رابطٌ حيٌّ في السايدبار
 *   ② ACTION_TARGET  — يقرأ `$_POST['action']` ولا يُصيَّر صفحةً كاملة
 *   ③ CHILD_RECORD   — منظرٌ/تبويبٌ من صفٍّ في المصفوفةِ له `view_of`
 *   ④ TECHNICAL_ONLY — تحت مجلداتِ الأدواتِ والتشخيص
 *   ⑤ DEPRECATED     — معلَنٌ متقاعدًا في السجل
 *   وما لا يقوم عليه دليلٌ يبقى **بلا تصنيف** (NULL) — لا يُخمَّن.
 * ◆ و«يتركب من ملفات»: الملفُّ الذي لا يُصيَّر وحدَه (بلا ترويسةٍ ولا سايدبار)
 *   ويُضمَّن من غيرِه يُسجَّل شريكَ تصييرٍ في `extra_files` لا سطحًا مستقلًّا.
 *
 * التشغيل:
 *   php tools/uxui_surface_scan.php               الجردُ وتقريرُه (بلا كتابة)
 *   php tools/uxui_surface_scan.php --save        يسجّل في ui_surfaces
 *   php tools/uxui_surface_scan.php --md=<path>   تقريرُ Markdown
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/* ── ملفاتُ المرشَّحين: مجلداتُ الأسطحِ الحيّةِ لا الشجرةُ كلُّها ── */
$SKIP_DIRS = array('.git', 'vendor', 'node_modules', 'storage/backups', '.claude', 'tests', 'database/migrations');
$files = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    foreach ($SKIP_DIRS as $d) { if (strpos($rel, $d . '/') === 0 || strpos($rel, '/' . $d . '/') !== false) { continue 2; } }
    $files[] = $rel;
}
sort($files);

/* ── المراجعُ الحاكمة ── */
$canon = array();   // route_lc => [canonical_ar, owner_dept, view_of, retirement]
$q = $conn->query("SELECT route, canonical_ar, owner_dept, view_of, retirement_status FROM nav_canonical");
while ($q && ($x = $q->fetch_assoc())) { $canon[strtolower($x['route'])] = $x; }
$navLive = array();  // مساراتٌ حيّةٌ في جدولِ التنقل
$q = $conn->query("SELECT DISTINCT route FROM nav_items WHERE active = 1");
while ($q && ($x = $q->fetch_assoc())) {
    $r = strtolower(preg_replace('~^(\.\./)+~', '', preg_replace('/[?#].*$/', '', (string) $x['route'])));
    $navLive[$r] = true;
}
$modules = array();
$q = $conn->query("SELECT code, name FROM modules");
while ($q && ($x = $q->fetch_assoc())) { $modules[strtolower($x['code'])] = $x['name']; }

/* ── التصنيفُ بدليلٍ لكلِّ ملف ── */
$surfaces = array(); $partials = array();
$stat = array();
foreach ($files as $rel) {
    $lc = strtolower($rel);
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }

    /* شريكُ تصيير: لا يبدأ صفحةً ولا يحمل قشرةً — يُضمَّن من غيرِه */
    $rendersShell = (strpos($src, 'inheader.php') !== false || strpos($src, 'insidebar.php') !== false
                  || stripos($src, '<!DOCTYPE') !== false);
    $isPartial = (!$rendersShell && (strpos($rel, '_') !== false || strpos($lc, '/includes/') !== false
                  || strpos($lc, 'includes/') === 0 || strpos($lc, 'app/') === 0));

    $type = null; $entry = null; $parent = null; $evidence = null; $renderable = null;

    if (strpos($lc, 'tools/') === 0 || strpos($lc, 'cron') !== false || strpos($lc, '/cron') !== false) {
        $type = 'TECHNICAL_ONLY'; $entry = 'cli'; $renderable = 0;
        $evidence = 'تحت مجلدِ الأدواتِ أو مهمةٍ دورية';
    } elseif (isset($canon[$lc])) {
        $c = $canon[$lc];
        if (!empty($c['retirement_status']) && $c['retirement_status'] !== 'ACTIVE') {
            $type = 'DEPRECATED'; $renderable = 0;
            $evidence = 'retirement_status=' . $c['retirement_status'] . ' في السجلِّ المعياريّ';
        } elseif (!empty($c['view_of'])) {
            $type = 'CHILD_RECORD'; $entry = 'view'; $parent = $c['view_of']; $renderable = 1;
            $evidence = 'view_of=' . $c['view_of'] . ' في السجلِّ المعياريّ';
        } else {
            $type = 'NAVIGABLE'; $entry = isset($navLive[$lc]) ? 'sidebar' : 'direct_url'; $renderable = 1;
            $evidence = 'صفٌّ في nav_canonical' . (isset($navLive[$lc]) ? ' + رابطٌ حيٌّ في التنقل' : '');
        }
    } elseif (isset($navLive[$lc])) {
        $type = 'NAVIGABLE'; $entry = 'sidebar'; $renderable = 1;
        $evidence = 'رابطٌ حيٌّ في nav_items بلا صفٍّ في المصفوفة';
    } elseif ($isPartial) {
        $partials[] = $rel;
        continue;   /* ليس سطحًا — شريكُ تصيير */
    } elseif (preg_match('~\$_POST\s*\[\s*[\'"]action[\'"]~', $src) && !$rendersShell) {
        $type = 'ACTION_TARGET'; $entry = 'post_handler'; $renderable = 0;
        $evidence = 'يقرأ $_POST[action] ولا يُصيِّر قشرةَ صفحة';
    } elseif ($rendersShell) {
        /* يُصيَّر لكنْ بلا صفٍّ ولا رابط — سطحٌ بلا تصنيفٍ حاكم */
        $type = null; $entry = 'direct_url'; $renderable = 1;
        $evidence = 'يُصيِّر قشرةَ صفحةٍ بلا صفٍّ في المصفوفةِ ولا رابطٍ حيّ — يحتاج تصنيفًا بشريًّا';
    } else {
        $type = null; $renderable = null;
        $evidence = 'لا دليلَ كافٍ — لا يُخمَّن';
    }

    $surfaces[] = array(
        'id' => $rel, 'file' => $rel, 'type' => $type, 'entry' => $entry, 'parent' => $parent,
        'name' => isset($canon[$lc]) ? $canon[$lc]['canonical_ar'] : (isset($modules[$lc]) ? $modules[$lc] : null),
        'owner' => isset($canon[$lc]) ? $canon[$lc]['owner_dept'] : null,
        'renderable' => $renderable, 'evidence' => $evidence,
    );
    $k = $type === null ? 'بلا تصنيف' : $type;
    $stat[$k] = ($stat[$k] ?? 0) + 1;
}

/* ── التقرير ── */
$renderables = 0;
foreach ($surfaces as $s) { if ($s['renderable'] === 1) { $renderables++; } }
echo "════ جردُ أسطحِ العرضِ — قراءةٌ فقط ════\n";
echo "  ملفاتُ PHP المفحوصة: " . count($files) . "\n";
echo "  شركاءُ تصييرٍ (ليست أسطحًا): " . count($partials) . "\n";
echo "  أسطحٌ مسجَّلة: " . count($surfaces) . "\n";
echo "  ◆ **المقامُ الحاكم — أسطحٌ قابلةٌ للتصيير: {$renderables}**\n\n";
arsort($stat);
foreach ($stat as $k => $n) { printf("    %-16s %d\n", $k, $n); }

if (!empty($args['save'])) {
    $ins = $conn->prepare("INSERT INTO ui_surfaces
        (surface_id, source_file, canonical_name, owner_dept, surface_type, parent_surface, entry_method, renderable, evidence)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE canonical_name=VALUES(canonical_name), owner_dept=VALUES(owner_dept),
          surface_type=VALUES(surface_type), parent_surface=VALUES(parent_surface),
          entry_method=VALUES(entry_method), renderable=VALUES(renderable), evidence=VALUES(evidence)");
    $n = 0;
    foreach ($surfaces as $s) {
        $ins->bind_param('sssssssis', $s['id'], $s['file'], $s['name'], $s['owner'], $s['type'],
                         $s['parent'], $s['entry'], $s['renderable'], $s['evidence']);
        if ($ins->execute()) { $n++; }
    }
    echo "\n  ▸ سُجِّل: {$n} سطحًا\n";
} else {
    echo "\n  ▸ جردٌ بلا تسجيل — أضِفْ --save\n";
}

if (!empty($args['md'])) {
    $L = array('# جردُ أسطحِ العرض — المقامُ أسطحٌ لا ملفات', '',
        '· ' . date('Y-m-d H:i') . ' · `php tools/uxui_surface_scan.php --md=<الملف>`', '',
        '| المقياس | العدد |', '|---|---|',
        '| ملفاتُ PHP المفحوصة | ' . count($files) . ' |',
        '| شركاءُ تصييرٍ (ليست أسطحًا) | ' . count($partials) . ' |',
        '| أسطحٌ مسجَّلة | ' . count($surfaces) . ' |',
        '| **أسطحٌ قابلةٌ للتصيير (المقامُ الحاكم)** | **' . $renderables . '** |', '',
        '## بالصنف', '', '| الصنف | العدد |', '|---|---|');
    foreach ($stat as $k => $n) { $L[] = '| ' . $k . ' | ' . $n . ' |'; }
    $L[] = '';
    $L[] = '◆ «بلا تصنيف» لا يُخمَّن — يحتاج حكمًا بشريًّا بشاهدِه المسجَّل في `ui_surfaces.evidence`.';
    file_put_contents($args['md'], implode("\n", $L) . "\n");
    echo "  MD ⇐ {$args['md']}\n";
}
