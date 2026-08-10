<?php
/**
 * tools/screen_about_generate.php — مولِّدُ نصوصِ التعريف لكلِّ شاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * يملأ `screen_about` بنصٍّ يشرح **ما هي الشاشةُ وما فيها** — دليلَ مستخدمٍ
 * مصغَّرًا لمن يفتحها. بلا ذكرِ الأدوارِ ولا المهامِّ ولا الصلاحيات (قرار
 * المالك): تلك بياناتُ حوكمةٍ موضعُها شاشاتُها، وإقحامُها يحوّل التعريفَ
 * إلى تقريرٍ يُتصفَّح.
 *
 * ◆ **القاعدةُ الحاكمة: لا تُكتب جملةٌ بلا مصدرٍ حيٍّ يُراجَع.** المطلوبُ
 *   «معلوماتٌ حقيقية»، والحقيقيُّ ما يُرَدُّ إلى صفٍّ في القاعدة — لا نصٌّ
 *   يُصاغ من التخمين فيقرأه المستخدمُ على أنه عقد. فما لا مصدرَ له يبقى
 *   تعريفًا أدنى (اسمٌ وإدارة) ويُوسَم `derived` كي يُعرف ويُنقَّح لاحقًا.
 *
 * المصادرُ بترتيب الجودة:
 *   ① `nav09_action_map` — أفعالُ الشاشة بأسمائها العربية وأثرِ كلٍّ منها.
 *      (المصدرُ الأغنى: يقول ما يُصنع في الشاشة وما يترتب عليه.)
 *   ② `nav09_file_map`  — العنوانُ القانونيُّ والإدارةُ المالكة.
 *   ③ `modules.name`    — اسمُ الشاشة كما يراه المستخدم في قائمته.
 *
 *   والشاشاتُ الـ69 التي تصوغ تعريفَها بيدها في ملفِّها **لا تُمسّ**: نصُّها
 *   يغلب السجلَّ عند التصيير، فتوليدُ نصٍّ لها عبثٌ يُنافس نصًّا أفضل.
 *
 * التشغيل:
 *   php tools/screen_about_generate.php            عرضٌ فقط (لا كتابة)
 *   php tools/screen_about_generate.php --apply    كتابةُ السجل
 */
if (PHP_SAPI !== 'cli') { exit(1); }
define('EMS_CLI', true);
error_reporting(E_ALL & ~E_DEPRECATED);

$apply = in_array('--apply', $argv, true);
$root  = dirname(__DIR__);
require_once $root . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$db->set_charset('utf8mb4');
$q = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : array(); };

/* ── ① الشاشاتُ التي تحمل المكوِّن، ومن منها يصوغ نصَّه بيده ─────────────── */
$skip = array('vendor/','storage/','node_modules/','.git/','docs/','database/','install/','examples/','tests/','tools/','scripts/');
$rii  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$screens = array(); $authored = array();
foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $rel = ltrim(substr($p, strlen($root)), '/');
    foreach ($skip as $s) { if (strpos($rel, $s) === 0) { continue 2; } }
    if ($rel === 'includes/screen_contract.php') { continue; }
    $src = file_get_contents($p);
    if (strpos($src, 'ems_screen_about') === false) { continue; }
    $screens[$rel] = true;
    if (preg_match('/ems_screen_about\s*\(\s*[\'"]/', $src)) { $authored[$rel] = true; }
}

/* ── ② المصادر ─────────────────────────────────────────────────────────── */
$fileMap = array();
foreach ($q("SELECT real_path, title_ar, owner_dept FROM nav09_file_map WHERE real_path IS NOT NULL AND real_path <> ''") as $r) {
    $fileMap[strtolower($r['real_path'])] = $r;
}
$modName = array(); $modByBase = array();
foreach ($q("SELECT code, name FROM modules WHERE code IS NOT NULL AND code <> '' ORDER BY id") as $r) {
    $k = strtolower($r['code']);
    if (!isset($modName[$k])) { $modName[$k] = $r['name']; }
    $b = strtolower(basename($r['code']));
    if (!isset($modByBase[$b])) { $modByBase[$b] = $r['name']; }
}

/* اسمُ الشاشة كما يقرؤه المستخدمُ في قائمته — مصدرٌ ثالثٌ للعنوان يسبق
   اسمَ الملف. (بدونه خرجت شاشاتٌ باسمٍ لاتينيٍّ مثل «requests» تعريفًا.) */
$navLabel = array();
foreach ($q("SELECT route, label_ar, COUNT(*) c FROM nav_items
              WHERE active = 1 AND label_ar <> '' GROUP BY route, label_ar ORDER BY c DESC") as $r) {
    $k = strtolower($r['route']);
    if (!isset($navLabel[$k])) { $navLabel[$k] = trim($r['label_ar']); }
}

/* موضعُ الشاشة في مسار العمل — مرحلةٌ ومجموعةٌ من بنية القوائم المولَّدة.
   سياقٌ حقيقيٌّ يوجّه القارئَ: أين تقع هذه الشاشةُ من دورةِ العمل.
   وتُستبعد المجموعاتُ الإداريةُ الداخلية («— بقرار المالك» · «أخرى —
   للمراجعة»): هي وسمُ حوكمةٍ لا موضعُ عملٍ يُشرح لمستخدم. */
$place = array();
foreach ($q("SELECT ni.route, lg.stage_title, lg.name gname, COUNT(*) c
               FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
              WHERE ni.active = 1 AND lg.is_active = 1
                AND lg.stage_title IS NOT NULL AND lg.stage_title <> ''
                AND lg.group_code NOT LIKE 'n9s99_others%'
                AND lg.group_code NOT LIKE 'n9o%'
              GROUP BY ni.route, lg.stage_title, lg.name
              ORDER BY c DESC") as $r) {
    $k = strtolower($r['route']);
    if (isset($place[$k])) { continue; }              // الأشيعُ يمثّل الشاشة
    $stage = trim((string) $r['stage_title']);
    $gname = trim((string) $r['gname']);
    if (mb_strpos($stage, 'خارج الوثيقة') !== false) { continue; }
    if ($gname !== '' && (mb_substr($gname, 0, 1) === '—' || mb_strpos($gname, 'للمراجعة') !== false)) { $gname = ''; }
    $place[$k] = array('stage' => $stage, 'group' => $gname);
}
/* ◆ حمايةُ التحرير اليدوي: صفٌّ وُسم `authored` **لا يُدهس**. بعد أن صار
   الجدولُ مرجعًا يُحرَّر من كونسول المزوّد، كان تشغيلُ هذا المولِّد ثانيةً
   يمحو كلَّ ما كتبه المالكُ بيده ويعيده نصًّا مركَّبًا — فقدُ عملٍ صامتٌ لا
   يُكتشف إلا حين يفتح مستخدمٌ الشاشة. والمولِّدُ يملأ الفراغَ ولا يصحّح يدًا. */
$keepAuthored = array();
foreach ($q("SELECT screen_path FROM screen_about WHERE source = 'authored'") as $r) {
    $keepAuthored[strtolower($r['screen_path'])] = true;
}

$acts = array();
foreach ($q("SELECT live_code, label_ar, effect_text, write_class
               FROM nav09_action_map
              WHERE live_code LIKE 'page:%' AND state <> 'declared_unbuilt'
              ORDER BY (write_class = 'read_only'), canonical_code") as $r) {
    $acts[strtolower(substr($r['live_code'], 5))][] = $r;
}

/* ── ③ أدواتُ الصياغة ──────────────────────────────────────────────────── */
/** وصلُ عناصرَ عربيةً وصلًا سليمًا: «أ، وب، وج» */
$join = function (array $items) {
    $items = array_values(array_filter(array_map('trim', $items), function ($s) { return $s !== ''; }));
    $n = count($items);
    if ($n === 0) { return ''; }
    if ($n === 1) { return $items[0]; }
    $last = array_pop($items);
    return implode('، و', $items) . '، و' . $last;
};
/** تنظيفُ نصِّ الأثر: يُنزع التنقيطُ الزخرفيُّ ويُوحَّد الفاصل */
$clean = function ($s) {
    $s = trim((string) $s);
    if ($s === '' || $s === '—') { return ''; }
    $s = str_replace(array(' · ', '·'), array('، ', '، '), $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return rtrim(trim($s), '.،');
};

/**
 * ◆ الكتلةُ التوثيقيةُ للملفِّ **رُفضت مصدرًا** بعد تجربتها: هي مكتوبةٌ
 *   للمطوِّر لا للمستخدم، فأنتجت جملًا مبتورةً («…وما يعتمده.»)، ومراجعَ
 *   أحكامٍ داخلية («ق-4»)، وترميزَ تنسيقٍ خامًّا، وملاحظاتِ إعادةِ هيكلةٍ لا
 *   معنى لها لمن يفتح الشاشة. ونصٌّ تقنيٌّ يُعرض للمستخدم **أسوأُ من سطرٍ
 *   واحدٍ صادقٍ مقتضب**. فبقي مصدران نظيفان: أفعالُ الشاشة (①)، وبنيةُ ما
 *   تعرضه فعلًا (④ أدناه).
 *
 * ما تعرضه الشاشةُ فعلًا — يُقرأ من ترميزها لا يُفترض: جدولُ سجلٍّ · نموذجُ
 * إدخال · مؤشرات · رسوم. وصفٌ يوجّه من يفتحها أولَ مرة إلى شكلِ ما سيراه.
 */
$fromStructure = function ($src) {
    $has = function ($needles) use ($src) {
        foreach ((array) $needles as $n) { if (stripos($src, $n) !== false) { return true; } }
        return false;
    };
    $bits = array();
    if ($has(array('<table', 'DataTable'))) {
        $bits[] = 'جدولٍ يمكن البحثُ فيه وترتيبُه وتصديرُه';
    }
    if ($has(array('ems-kpi', 'stat-card', 'kpi-card'))) {
        $bits[] = 'بطاقاتِ مؤشراتٍ موجزة';
    }
    if ($has(array('<canvas', 'Chart('))) {
        $bits[] = 'رسومٍ بيانية';
    }
    if (!$bits) { return ''; }

    $txt = 'تعرض بياناتِها في ' . (count($bits) === 1 ? $bits[0]
          : implode('، و', array_slice($bits, 0, -1)) . '، و' . end($bits));
    // نموذجُ إدخالٍ داخلَ الشاشة نفسِها
    if (preg_match('~<form\b~i', $src) && preg_match('~<(input|select|textarea)\b~i', $src)) {
        $txt .= '، وفيها نموذجٌ لإضافةِ البيانات وتعديلِها من الشاشة نفسِها';
    }
    return $txt;
};

/* ── ④ التوليد ─────────────────────────────────────────────────────────── */
$rows = array(); $stat = array('authored' => 0, 'composed' => 0, 'derived' => 0, 'kept' => 0);

foreach (array_keys($screens) as $rel) {
    if (isset($keepAuthored[strtolower($rel)])) { $stat['kept']++; continue; }   // محرَّرٌ بيد — لا يُدهس
    if (isset($authored[$rel])) { $stat['authored']++; continue; }   // نصُّها في ملفِّها ويغلب

    $lc = strtolower($rel);
    $fm = isset($fileMap[$lc]) ? $fileMap[$lc] : null;
    $title = '';
    $isAr = function ($s) { return preg_match('/[\x{0600}-\x{06FF}]/u', (string) $s) === 1; };
    if ($fm && $isAr($fm['title_ar']))          { $title = trim($fm['title_ar']); }
    if ($title === '' && isset($modName[$lc]) && $isAr($modName[$lc]))   { $title = trim($modName[$lc]); }
    if ($title === '' && isset($navLabel[$lc]) && $isAr($navLabel[$lc])) { $title = trim($navLabel[$lc]); }
    $b = strtolower(basename($rel));
    if ($title === '' && isset($modByBase[$b]) && $isAr($modByBase[$b])) { $title = trim($modByBase[$b]); }
    if ($title === '')                          { $title = basename($rel, '.php'); }
    $dept = $fm ? trim((string) $fm['owner_dept']) : '';

    $A  = isset($acts[$lc])  ? $acts[$lc]  : array();
    $PL = isset($place[$lc]) ? $place[$lc] : null;

    // الفقرةُ الأولى: ما هي الشاشة — واسمُها وإدارتُها وموضعُها من دورة العمل
    $p1 = 'شاشة «' . $title . '»' . ($dept !== '' ? ' — ضمن نطاق ' . $dept : '') . '.';
    if ($PL && $PL['stage'] !== '') {
        $p1 .= ' تقع في مرحلة «' . $PL['stage'] . '»'
             . (($PL['group'] !== '' && $PL['group'] !== $PL['stage']) ? ' تحت «' . $PL['group'] . '»' : '')
             . ' من مسار العمل.';
    }

    $paras = array($p1);
    $source = 'derived';

    if ($A) {
        $source = 'composed';
        // ما فيها: أسماءُ ما يُصنع في الشاشة (لا قائمةَ مهامّ — سردٌ متصل)
        $labels = array();
        foreach ($A as $a) {
            $l = $clean($a['label_ar']);
            if ($l !== '' && !in_array($l, $labels, true)) { $labels[] = $l; }
            if (count($labels) >= 5) { break; }
        }
        if ($labels) {
            $more = count($A) - count($labels);
            $paras[] = 'يجري فيها ' . $join($labels)
                     . ($more > 0 ? '، وغيرُها مما تحويه الشاشة' : '') . '.';
        }
        // أثرُ ما يُسجَّل فيها — أوضحُ ما يحتاج المستخدمُ فهمَه قبل أن يبدأ
        $effects = array();
        foreach ($A as $a) {
            if ((string) $a['write_class'] === 'read_only') { continue; }
            $e = $clean($a['effect_text']);
            if ($e !== '' && !in_array($e, $effects, true)) { $effects[] = $e; }
            if (count($effects) >= 2) { break; }
        }
        if ($effects) {
            $paras[] = 'وما يُسجَّل فيها يمضي إلى ما بعده: ' . $join($effects) . '.';
        }
    } else {
        /* لا أفعالَ مسجَّلةً لهذه الشاشة — يُوصف ما تعرضه فعلًا من ترميزها.
           فإن لم تُعطِ شيئًا بقي التعريفُ أدنى ووُسم `derived` ليُعرف موضعُ
           النقص ويُنقَّح، لا ليُخفى بعبارةٍ عامةٍ تبدو معلومةً وليست. */
        $st = $fromStructure((string) @file_get_contents($root . '/' . $rel));
        if ($st !== '') {
            $paras[] = $st . '.';
            $source = 'composed';
        }
    }

    $desc = implode("\n\n", $paras);
    $rows[$rel] = array('title' => $title, 'desc' => $desc, 'source' => $source,
                        'unnamed' => !$isAr($title));
    $stat[$source]++;
}

/* ── ⑤ الكتابة أو العرض ───────────────────────────────────────────────── */
echo "شاشاتٌ تحمل المكوِّن: " . count($screens) . "\n";
echo "  محرَّرةٌ بيدٍ في السجل (محميّة — لا تُدهس): {$stat['kept']}\n";
echo "  نصُّها مصوغٌ في ملفِّها (لا تُمسّ): {$stat['authored']}\n";
echo "  مركَّبٌ من أفعالِ الشاشة وأثرِها  : {$stat['composed']}\n";
echo "  تعريفٌ أدنى (اسمٌ وإدارة)        : {$stat['derived']}\n";

/* ◆ فجوةٌ تُعلَن لا تُلطَّف: شاشةٌ لا اسمَ عربيَّ لها في أيِّ سجل — لا في
   `nav09_file_map` ولا `modules` ولا `nav_items`. تعريفُها يخرج باسم ملفِّها،
   وتسميتُها قرارُ مالكٍ لا تخمينُ أداة. */
$unnamed = array();
foreach ($rows as $rel => $r) { if (!empty($r['unnamed'])) { $unnamed[] = $rel; } }
if ($unnamed) {
    echo "\n  ◆ " . count($unnamed) . " شاشةً بلا اسمٍ عربيٍّ في أيِّ سجل — تُسمّى بقرارِ مالك:\n";
    foreach ($unnamed as $u) { echo "     - $u\n"; }
}
echo str_repeat('─', 74) . "\n";

if (!$apply) {
    $n = 0;
    foreach ($rows as $rel => $r) {
        if ($r['source'] !== 'composed') { continue; }
        if ($n++ >= 5) { break; }
        echo "\n[$rel]  ({$r['source']})\n" . $r['desc'] . "\n";
    }
    $n = 0;
    foreach ($rows as $rel => $r) {
        if ($r['source'] !== 'derived') { continue; }
        if ($n++ >= 3) { break; }
        echo "\n[$rel]  ({$r['source']})\n" . $r['desc'] . "\n";
    }
    echo "\n\n(عرضٌ فقط — أضف --apply للكتابة)\n";
    exit(0);
}

$st = $db->prepare("INSERT INTO screen_about (screen_path, title_ar, description, source)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE title_ar = VALUES(title_ar),
                                            description = VALUES(description),
                                            source = VALUES(source)");
$w = 0;
foreach ($rows as $rel => $r) {
    $st->bind_param('ssss', $rel, $r['title'], $r['desc'], $r['source']);
    $st->execute();
    $w++;
}
$st->close();
echo "كُتب $w صفًّا في screen_about\n";
