<?php
/**
 * tools/nav09_read.php — قارئُ «NAV-09 نظام الشاشات النافذ» المتحقق (⓪-1)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ المصنّف العشرين ورقةً ويعيد بنيتَه مصفوفاتٍ جاهزةً للاستيراد،
 * **ويتحقق أن إجماليات الفهرس تطابق ما في الأوراق فعلًا** — فوثيقةٌ لا
 * يطابق فهرسُها متنَها لا يُستورَد منها.
 *
 * التشغيل:  php tools/nav09_read.php [--file=path] [--summary|--depts|--check]
 * الاستعمال برمجيًّا:  require ثم Nav09Reader::load($path)
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * القاعدة الواحدة لربط رابط قائمةٍ بصلاحيته (E-03 UX-07 · قرار 2026-08-06):
 * المسارُ (بلا مرساة #) المطابقُ لموديولٍ مسجَّلٍ بcode يُربط به —
 * module_id للفحص وpermission_code مفتاحًا؛ وما لا موديولَ له يبقى NULL
 * (ظهورٌ بلا فحصٍ كالثوابت). يستهلكها المستورد وتمريرةُ الربط معًا
 * فيبقى «المصدرُ الواحدُ المحكوم» (UXP-041) كودًا واحدًا.
 */
function nav09_perm_link(mysqli $conn, $route)
{
    $base = strpos($route, '#') !== false ? substr($route, 0, strpos($route, '#')) : $route;
    $e = mysqli_real_escape_string($conn, $base);
    $r = mysqli_query($conn, "SELECT id FROM modules WHERE code = '{$e}' LIMIT 1");
    if ($r && ($x = mysqli_fetch_assoc($r))) {
        return array('module_id' => (int) $x['id'], 'permission_code' => $base);
    }
    return array('module_id' => null, 'permission_code' => null);
}

class Nav09Reader
{
    /** الأوراق الإدارية 01..16 → [dept_no, dept_name, meta{}, stages[], groups[], screens[], actions[]] */
    public static function load($path)
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $wb = $reader->load($path);

        $out = array('index' => array(), 'depts' => array(), 'impact' => array(), 'matrix' => array(), 'rules' => array());

        /* ── 00 الفهرس ─────────────────────────────────────────────────────
         * v4 أضافت عمودَ «الطبقة» بخلايا مدمجةٍ تُقرأ فارغةً — فالمواضعُ تتزحزح.
         * القراءةُ الصامدة: الاسمُ أولُ خليةٍ نصيةٍ بعد الرقم، والأعدادُ الثلاثة
         * (مراحل·مجموعات·شاشات) آخرُ ثلاث خلايا رقميةٍ في الصف. */
        $s = $wb->getSheetByName('00 — الفهرس');
        foreach ($s->toArray(null, true, false, false) as $i => $r) {
            if ($i === 0) { continue; }
            $cells = array(); foreach ($r as $c) { $c = trim((string) $c); if ($c !== '') { $cells[] = $c; } }
            if (count($cells) < 4) { continue; }
            $nums = array_values(array_filter($cells, function ($c) { return ctype_digit($c); }));
            if (count($nums) < 3) { continue; }
            $tail = array_slice($nums, -3);
            if ($cells[0] === '—') {
                $out['index']['totals'] = array(
                    'stages' => intval($tail[0]), 'groups' => intval($tail[1]), 'screens' => intval($tail[2]));
                continue;
            }
            if (!ctype_digit($cells[0])) { continue; }
            $out['index']['depts'][intval($cells[0])] = array(
                'name' => $cells[1],
                'stages' => intval($tail[0]), 'groups' => intval($tail[1]), 'screens' => intval($tail[2]));
        }

        /* ── الأوراق الإدارية ───────────────────────────────────────────── */
        foreach ($wb->getSheetNames() as $sheetName) {
            if (!preg_match('/^(\d{2}) · (.+)$/u', $sheetName, $m)) { continue; }
            $deptNo = intval($m[1]); $deptName = trim($m[2]);
            $s = $wb->getSheetByName($sheetName);
            $dept = array('no' => $deptNo, 'name' => $deptName, 'meta' => array(),
                          'stages' => array(), 'rows' => array(), 'anomalies' => array());
            $stage = array('no' => 0, 'title' => '◇ اللوحة والمساحة'); // ما قبل أول مرحلة مرقمة
            $stageSeq = -1; // العدُّ بالتسلسل لا بالرقم المعلن — فالوثيقة قد تكرر رقمًا (الموردون: ٧ مرتين)
            $group = null;
            foreach ($s->toArray(null, true, false, false) as $i => $r) {
                $cells = array_values(array_filter(array_map(function ($c) { return trim((string) $c); }, $r),
                                                   function ($c) { return $c !== ''; }));
                if (!$cells || $i === 0) { continue; }
                $first = $cells[0];
                if (mb_substr($first, 0, 1) === '◆' || mb_substr($first, 0, 1) === '◄') {
                    $dept['meta'][$first] = isset($cells[1]) ? $cells[1] : '';
                    continue;
                }
                if (mb_substr($first, 0, 1) === '◇') { // لوحة العرض قبل المراحل
                    $stageSeq++;
                    $stage = array('no' => $stageSeq, 'declared' => 0, 'title' => $first);
                    $dept['stages'][$stageSeq] = $stage;
                    continue;
                }
                if (preg_match('/^(\d+)\s*·/u', $first, $sm) && mb_strpos($first, 'php') === false
                    && !ctype_digit($first)) { // «1 · أولًا: …» سطرُ مرحلة
                    $stageSeq++;
                    if (isset($dept['stages'][$stageSeq - 1]) && $dept['stages'][$stageSeq - 1]['declared'] === intval($sm[1])) {
                        $dept['anomalies'][] = "رقمُ المرحلة {$sm[1]} مكررٌ («{$first}») — عُدَّت بتسلسلها {$stageSeq}";
                    }
                    $stage = array('no' => $stageSeq, 'declared' => intval($sm[1]), 'title' => $first,
                                   'doc' => isset($cells[1]) ? $cells[1] : '', 'owner' => isset($cells[2]) ? $cells[2] : '');
                    $dept['stages'][$stageSeq] = $stage;
                    continue;
                }
                if (mb_substr($first, 0, 1) === '▸') { // مجموعة
                    $group = trim(mb_substr($first, 1));
                    continue;
                }
                if (mb_substr($first, 0, 1) === '⏵') { // فعلٌ داخل آخر شاشة
                    $dept['rows'][] = array('kind' => 'action', 'stage' => $stage['no'], 'group' => $group,
                        'label' => trim(mb_substr($first, 1)),
                        'code' => isset($cells[1]) ? $cells[1] : '',
                        'pub' => isset($cells[2]) ? $cells[2] : '',
                        'effect' => isset($cells[3]) ? $cells[3] : '',
                        'reverse' => isset($cells[4]) ? $cells[4] : '');
                    continue;
                }
                if (ctype_digit($first) && isset($cells[2]) && mb_strpos((string) $cells[2], '.php') !== false) {
                    // صفُّ شاشة: # · الاسم · الملف · مالك/عارض · النطاق · الزاوية · المسموح · المحجوب
                    $dept['rows'][] = array('kind' => 'screen', 'stage' => $stage['no'], 'group' => $group,
                        'seq' => intval($first),
                        'title' => $cells[1], 'file' => $cells[2],
                        'role' => isset($cells[3]) ? $cells[3] : '',
                        'scope' => isset($cells[4]) ? $cells[4] : '',
                        'angle' => isset($cells[5]) ? $cells[5] : '',
                        'allowed' => isset($cells[6]) ? $cells[6] : '',
                        'blocked' => isset($cells[7]) ? $cells[7] : '');
                }
            }
            $out['depts'][$deptNo] = $dept;
        }

        /* ── 97 خريطة الأثر ─────────────────────────────────────────────── */
        $s = $wb->getSheetByName('97 — خريطة الأثر');
        foreach ($s->toArray(null, true, false, false) as $i => $r) {
            if ($i === 0 || trim((string) $r[2]) === '') { continue; }
            $out['impact'][] = array(
                'screen' => trim((string) $r[0]), 'label' => trim((string) $r[1]),
                'code' => trim((string) $r[2]), 'actor' => trim((string) $r[3]),
                'writes' => trim((string) $r[4]), 'event' => trim((string) $r[5]),
                'consumers' => trim((string) $r[6]), 'effect' => trim((string) $r[7]),
                'reverse' => trim((string) $r[8]));
        }

        /* ── 98 مصفوفة العرض ────────────────────────────────────────────── */
        $s = $wb->getSheetByName('98 — مصفوفة العرض');
        foreach ($s->toArray(null, true, false, false) as $i => $r) {
            if ($i === 0 || trim((string) $r[1]) === '') { continue; }
            $out['matrix'][] = array(
                'title' => trim((string) $r[0]), 'file' => trim((string) $r[1]),
                'owner' => trim((string) $r[2]), 'viewer' => trim((string) $r[3]),
                'scope' => trim((string) $r[4]), 'angle' => trim((string) $r[5]),
                'allowed' => trim((string) $r[6]), 'blocked' => trim((string) $r[7]));
        }

        /* ── 99 قاعدة التنفيذ ───────────────────────────────────────────── */
        $s = $wb->getSheetByName('99 — قاعدة التنفيذ');
        foreach ($s->toArray(null, true, false, false) as $i => $r) {
            if ($i === 0 || trim((string) $r[0]) === '') { continue; }
            $out['rules'][trim((string) $r[0])] = trim((string) $r[1]);
        }

        return $out;
    }

    /** التحقق: الفهرسُ يطابق المتن — يعيد قائمة مخالفات (فارغةً عند التطابق). */
    public static function check(array $d)
    {
        $bad = array();
        $sumStages = 0; $sumGroups = 0; $sumScreens = 0;
        foreach ($d['depts'] as $no => $dept) {
            $ix = isset($d['index']['depts'][$no]) ? $d['index']['depts'][$no] : null;
            if (!$ix) { $bad[] = "الإدارة $no ({$dept['name']}) ليست في الفهرس"; continue; }
            $stages = count($dept['stages']);
            $groups = count(array_unique(array_filter(array_map(function ($r) {
                return $r['group'] . '#' . $r['stage']; }, $dept['rows']))));
            $screens = count(array_filter($dept['rows'], function ($r) { return $r['kind'] === 'screen'; }));
            $sumStages += $ix['stages']; $sumGroups += $ix['groups']; $sumScreens += $ix['screens'];
            if ($screens !== $ix['screens']) {
                $bad[] = "«{$dept['name']}»: الفهرس يعلن {$ix['screens']} شاشة والورقة تحمل $screens";
            }
            if ($groups !== $ix['groups']) {
                $bad[] = "«{$dept['name']}»: الفهرس يعلن {$ix['groups']} مجموعة والورقة تحمل $groups";
            }
            if ($stages !== $ix['stages']) {
                $bad[] = "«{$dept['name']}»: الفهرس يعلن {$ix['stages']} مرحلة والورقة تحمل $stages";
            }
        }
        $t = $d['index']['totals'];
        if ($sumScreens !== $t['screens']) { $bad[] = "إجمالي الشاشات: الفهرس {$t['screens']} ≠ مجموع الصفوف $sumScreens"; }
        if ($sumGroups !== $t['groups'])   { $bad[] = "إجمالي المجموعات: الفهرس {$t['groups']} ≠ المجموع $sumGroups"; }
        if ($sumStages !== $t['stages'])   { $bad[] = "إجمالي المراحل: الفهرس {$t['stages']} ≠ المجموع $sumStages"; }
        return $bad;
    }
}

/* ── CLI ───────────────────────────────────────────────────────────────── */
if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $file = 'C:/wamp64/www/ems/docs/files/NAV-09-current.xlsx';
    foreach ($argv as $a) { if (strpos($a, '--file=') === 0) { $file = substr($a, 7); } }
    $d = Nav09Reader::load($file);
    $mode = in_array('--depts', $argv, true) ? 'depts' : (in_array('--check', $argv, true) ? 'check' : 'summary');

    if ($mode === 'summary' || $mode === 'check') {
        $t = $d['index']['totals'];
        $files = array(); foreach ($d['matrix'] as $m) { $files[$m['file']] = 1; }
        echo "الفهرس: {$t['stages']} مرحلة · {$t['groups']} مجموعة · {$t['screens']} شاشة\n";
        echo "97: " . count($d['impact']) . " فعلًا · 98: " . count($d['matrix']) . " ظهورًا لـ" . count($files) . " ملفًّا · 99: " . count($d['rules']) . " حكمًا\n";
        $bad = Nav09Reader::check($d);
        if ($bad) { echo "✘ مخالفات التطابق (" . count($bad) . "):\n"; foreach ($bad as $b) { echo "  · $b\n"; } exit(1); }
        echo "✔ الفهرسُ يطابق المتن — صالحةٌ للاستيراد\n";
    }
    if ($mode === 'depts') {
        foreach ($d['depts'] as $no => $dept) {
            $screens = count(array_filter($dept['rows'], function ($r) { return $r['kind'] === 'screen'; }));
            $acts = count(array_filter($dept['rows'], function ($r) { return $r['kind'] === 'action'; }));
            echo sprintf("%02d %s — مراحل %d · شاشات %d · أفعال %d\n",
                $no, $dept['name'], count($dept['stages']), $screens, $acts);
        }
    }
}
