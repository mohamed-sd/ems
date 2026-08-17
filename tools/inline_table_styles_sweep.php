<?php
/**
 * tools/inline_table_styles_sweep.php — كنسُ إعلاناتِ هويّةِ الجداولِ الميّتة.
 * ═══════════════════════════════════════════════════════════════════════════
 * ما يُكنَس: إعلانُ **هويّةٍ بصريّة** (لون · خلفية · لونُ حدٍّ · وزنُ خطٍّ · حجمُه ·
 * استدارة) مكتوبٌ **قيمةً صريحةً** داخلَ كتلةِ `<style>` في شاشة، على محدِّدٍ
 * يمسُّ جدولًا. هذه يحكمها اليومَ `ems-tables.css` بـ`!important` وهو آخرُ ما
 * يُحمَّل — فالإعلانُ لا أثرَ له، لكنه يحيا لحظةَ أن يُنزَع `!important` أو
 * يتبدّل ترتيبُ التحميل. دَينٌ مؤجَّلٌ لا براءة.
 *
 * ما **لا** يُكنَس — وهذه حدودُ الأداةِ لا سهوُها:
 *   • إعلانُ **هندسة** (حشوة · عرض · محاذاة · تمرير) — شأنٌ شاشيٌّ مشروع.
 *   • إعلانٌ يقرأ من لوحةِ المفاتيح `var(--table-…)` — التزامٌ لا مخالفة.
 *   • إعلانٌ بـ`!important` — قد يكون فائزًا فعليًّا؛ لا يُلمَس بلا قرارٍ صريح.
 *   • أيُّ قاعدةٍ في نصِّها وسمُ PHP (`<?`) — القصُّ فيها يفسد المُخرَج.
 *
 * وإن أفرغت القاعدةُ من كلِّ إعلاناتِها حُذفت كلُّها، وإلا بقيت أقواسٌ فارغة.
 *
 * الاستعمال:
 *   php tools/inline_table_styles_sweep.php            ← معاينةٌ بلا لمس
 *   php tools/inline_table_styles_sweep.php --apply    ← التنفيذ
 * ═══════════════════════════════════════════════════════════════════════════
 */

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$SKIP  = array('vendor', 'node_modules', '.git', 'storage', '.ssdiff', 'docs', 'database', 'tools', 'tests');

/**
 * خريطةُ الحُكم: ماذا يحكمُ `ems-tables.css` فعلًا **لكلِّ هدف**؟
 * ═══════════════════════════════════════════════════════════════════════════
 * قائمةُ خصائصَ عامّةٌ لا تكفي، وقد أثبتَ ذلك قياسٌ حيّ:
 * `.ep-movements-table { font-size: 13px }` على **الجدولِ نفسِه** كُنِس بحجّةِ
 * أنه «هويّة» — لكنّ المصدرَ يضبط `font-size` على **الخلايا** لا على الجدول،
 * فسقط الحجمُ إلى ١٤px الموروث وتغيّر المظهر. (قِيس: t8/e0 13px → 14px.)
 *
 * فالحكمُ صار مقيَّدًا بالهدف — وهذه القوائمُ مستخرجةٌ من قواعدِ ems-tables.css
 * نفسِها لا مُفترَضة:
 */
$GOVERNED = array(
    /* الجدولُ نفسُه — القاعدةُ تضبط الإطارَ والخلفيةَ والاستدارةَ فقط */
    'table' => array('background', 'background-color', 'background-image',
                     'border', 'border-color', 'border-radius'),
    /* الترويسة */
    'head'  => array('background', 'background-color', 'background-image', 'color',
                     'border-color', 'border-bottom-color', 'border-top-color',
                     'font-size', 'font-weight'),
    /* الصفّ — الخلفيةُ وحدَها (التخطيطُ والمرور) */
    'row'   => array('background', 'background-color', 'background-image'),
    /* الخليّة */
    'cell'  => array('color', 'border', 'border-color', 'border-top', 'border-bottom',
                     'border-top-color', 'border-bottom-color', 'border-left-color',
                     'border-right-color', 'font-size', 'font-weight'),
);

/** تصنيفُ هدفِ المحدِّد إلى: table | head | row | cell — أو null فلا يُكنَس. */
function sw_kind($branch) {
    $b = strtolower(trim($branch));
    $parts = preg_split('~\s*[>+\~]\s*|\s+~', $b);
    $subject = trim(end($parts));
    $core = preg_replace('~::?[a-z-]+(\([^)]*\))?~i', '', $subject);
    if (trim($core) === '') $core = preg_replace('~::?[a-z-]+(\([^)]*\))?.*$~i', '', $subject);
    $core = trim($core);
    if (preg_match('~^(th)([\.\#]|$)~', $core)) return 'head';
    if (preg_match('~^(thead)([\.\#]|$)~', $core)) return 'head';
    /* `thead … td` ترويسةٌ أيضًا */
    if (preg_match('~^(td)([\.\#]|$)~', $core)) return (strpos($b, 'thead') !== false) ? 'head' : 'cell';
    if (preg_match('~^(tr)([\.\#]|$)~', $core)) return 'row';
    if (preg_match('~^(tbody|tfoot)([\.\#]|$)~', $core)) return 'row';
    if (preg_match('~^(table)([\.\#]|$)~', $core)) return 'table';
    if (preg_match('~^[\.\#][a-z0-9_-]*table[a-z0-9_-]*$~', $core)) return 'table';
    if (preg_match('~^\.(alltable|alltables|display|datatable)$~', $core)) return 'table';
    return null;
}

$NOT_A_TABLE = array(
    '.card', '.box', '.panel', '.table-container', '.table-responsive',
    '.table-section', '.table-wrap', '.table-responsive-wrapper',
    'badge', 'chip', 'btn', 'button', 'icon', 'pill',
    '.dt-button', '.dataTables_filter', '.dataTables_length', '.dataTables_info',
    '.dataTables_paginate', '.dataTables_wrapper .', '.ems-xscroll',
    '.action-btn', '.delete-btn', '.edit-btn', '.eye-btn', '.mnt-seg-btn',
    '.portable', '.notable', '.tablet', '.contracts-table-filter-wrap',
    '.contracts-group-toolbar', '.ems-view-picker', '.ems-colvis', '.ems-more',
);

/**
 * هل **هدفُ** المحدِّدِ خليّةٌ أو صفٌّ أو جدول؟
 *
 * ⚠ هذا الشرطُ صار على **آخرِ مركَّبٍ** (subject) لا على ورودِ الكلمةِ في أيِّ
 *   موضع — وهو تصحيحٌ لخطأٍ وقع فعلًا: المحدِّدُ
 *   `.movement-unified-page table input:disabled` هدفُه **الحقلُ** لا الخليّة،
 *   وطابقه الفحصُ القديمُ لمجرّدِ ورودِ كلمةِ `table` في أثنائِه، فحُذف إعلانُه
 *   وهو **حيٌّ**: `ems-forms.css` لا يغطّي حقلًا خارجَ `.allforms`، و
 *   `ems-tables.css` لا يُصمِّمُ الحقولَ أصلًا. فاستُعيدت الملفاتُ العشرون
 *   وأُعيد الكنسُ بهذا الشرط.
 *
 * والقاعدةُ العامّة: «الملفُّ الموحَّدُ يغلبه» صحيحةٌ للخلايا وحدَها — فما ليس
 * خليّةً لا يشمله الوعدُ ولا يجوز كنسُه.
 */
function sw_targets($sel, $NOT) {
    $s = strtolower($sel);
    foreach ($NOT as $n) if (strpos($s, strtolower($n)) !== false) return false;

    /* المحدِّدُ قد يكون قائمةً — يكفي أن يكون **كلُّ** فروعِه جدوليًّا كي يُكنَس
       بأمان؛ فرعٌ واحدٌ غيرُ جدوليٍّ يجعل الحذفَ خطرًا على ذلك الفرع. */
    $branches = preg_split('~\s*,\s*~', trim($sel));
    foreach ($branches as $br) {
        $br = trim($br);
        if ($br === '') continue;
        /* آخرُ مركَّبٍ = ما بعدَ آخرِ فاصلٍ نَسَبيّ (مسافة أو > أو + أو ~) */
        $parts = preg_split('~\s*[>+\~]\s*|\s+~', $br);
        $subject = end($parts);
        if ($subject === false || $subject === '') return false;
        /* تُنزع الحالاتُ والعناصرُ الزائفة قبلَ الحكم */
        $core = preg_replace('~::?[a-z-]+(\([^)]*\))?~i', '', $subject);
        if (trim($core) === '') {           /* مثل `tr:hover` → الهدفُ tr */
            $core = preg_replace('~::?[a-z-]+(\([^)]*\))?.*$~i', '', $subject);
        }
        /* الهدفُ المقبول — قائمةٌ مغلقةٌ عمدًا:
             • عنصرٌ جدوليٌّ صريح: table thead tbody tfoot tr th td
             • أو صنفٌ في اسمِه كلمةُ `table` (فهو غلافُ جدولٍ أو جدولٌ مُسمّى)
           وأُسقطت عمدًا أنماطٌ كـ`*-row` و`*-cell`: التقطت أصنافًا قد تكون
           `div`ًا لا خليّةً (`.atb-row-missing` مثلًا)، فوسّعت الكنسَ من ٢٠ شاشةً
           إلى ٥٠ بلا يقينٍ أنها جداول. والتضييقُ هنا حِرزٌ لا تقصير. */
        $core = trim($core);
        $isElem  = (bool) preg_match('~^(table|thead|tbody|tfoot|tr|th|td)$~i', $core);
        $isTblCls = (bool) preg_match('~^[\.\#][a-z0-9_-]*table[a-z0-9_-]*$~i', $core);
        $isKnown = (bool) preg_match('~^\.(alltable|alltables|display|dataTable)$~i', $core);
        /* عنصرٌ جدوليٌّ يحمل صنفًا: `td.mvun-empty-cell-sm` */
        $isElemCls = (bool) preg_match('~^(table|thead|tbody|tfoot|tr|th|td)[\.\#]~i', $core);
        if (!($isElem || $isTblCls || $isKnown || $isElemCls)) return false;
    }
    return true;
}
function sw_token($v) { return (bool) preg_match('~var\(\s*--table-~i', $v); }
function sw_raw($v) {
    $x = preg_replace('~var\(\s*--table-[a-z0-9-]+[^)]*\)~i', 'TOKEN', $v);
    return (bool) (preg_match('~\#[0-9a-f]{3,8}\b~i', $x) || preg_match('~\b(rgb|rgba|hsl|hsla)\s*\(~i', $x)
        || preg_match('~\bvar\(\s*--(?!table-)~i', $x)
        || preg_match('~\b(red|blue|green|black|white|gray|grey|orange|yellow|navy|transparent|inherit|currentColor)\b~i', $x)
        || preg_match('~^\s*\d~', $x) || preg_match('~\b(bold|bolder|normal|lighter)\b~i', $x));
}

/* ── الاختبارُ الذاتيّ ── */
$st = array(
    /* هدفٌ جدوليّ */
    'tgt+'   => sw_targets('.x thead th', $NOT_A_TABLE) === true,
    'tgt++'  => sw_targets('table.navtbl tr:hover td', $NOT_A_TABLE) === true,
    'tgt+++' => sw_targets('.modern-table tbody tr:hover', $NOT_A_TABLE) === true,
    'tgt++++'=> sw_targets('td.mvun-empty-cell-sm', $NOT_A_TABLE) === true,
    'cls-'   => sw_targets('.atb-row-missing', $NOT_A_TABLE) === false,   // قد يكون div لا خليّة
    'cls+'   => sw_targets('.sub-drivers-table th', $NOT_A_TABLE) === true,
    /* هدفٌ **ليس** جدوليًّا وإن ورد فيه اسمُ جدول — جوهرُ التصحيح */
    'sub-'   => sw_targets('.page table input:disabled', $NOT_A_TABLE) === false,
    'sub--'  => sw_targets('table select:disabled', $NOT_A_TABLE) === false,
    'sub---' => sw_targets('table td a.link', $NOT_A_TABLE) === false,
    'sub----'=> sw_targets('table th i', $NOT_A_TABLE) === false,
    /* فرعٌ واحدٌ غيرُ جدوليٍّ يمنع الكنسَ عن القاعدةِ كلِّها */
    'mix-'   => sw_targets('table th, table input', $NOT_A_TABLE) === false,
    'mix+'   => sw_targets('table th, table td', $NOT_A_TABLE) === true,
    'tgt-'   => sw_targets('.table-container', $NOT_A_TABLE) === false,
    'tok+'   => sw_token('var(--table-text,#161616)') === true,
    'raw+'   => sw_raw('#eee') === true,
    'raw-'   => sw_raw('var(--table-text, #161616)') === false,
    /* خريطةُ الحُكم — الدرسُ المدفوع */
    'knd1'   => sw_kind('.ep-movements-table') === 'table',
    'knd2'   => sw_kind('.x thead th') === 'head',
    'knd3'   => sw_kind('.x tbody tr:hover') === 'row',
    'knd4'   => sw_kind('td.mvun-empty-cell-sm') === 'cell',
    'knd5'   => sw_kind('.x thead tr td') === 'head',
    'knd6'   => sw_kind('table input:disabled') === null,
    /* حجمُ الخطِّ على **الجدول** غيرُ محكومٍ فلا يُكنَس — عينُ ما وقع */
    'gov-'   => in_array('font-size', $GOVERNED['table'], true) === false,
    'gov+'   => in_array('font-size', $GOVERNED['cell'],  true) === true,
);
$bad = array(); foreach ($st as $k => $v) if (!$v) $bad[] = $k;
if (count($bad)) { echo 'فحصُ الفاحصِ فشل: ' . implode(',', $bad) . PHP_EOL; exit(1); }
echo 'فحصُ الفاحص: ' . count($st) . ' حالةً — كلُّها سليمة.' . PHP_EOL . PHP_EOL;

/* ── الكنس ── */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (strtolower($f->getExtension()) !== 'php') continue;
    $p = str_replace(chr(92), '/', $f->getPathname());
    $rel = substr($p, strlen($ROOT) + 1);
    if (count(array_intersect(explode('/', $rel), $SKIP)) > 0) continue;
    $files[$rel] = $p;
}
ksort($files);

$totalRemoved = 0; $totalRules = 0; $touched = 0; $skippedPhp = 0;
foreach ($files as $rel => $abs) {
    $src = file_get_contents($abs);
    if (stripos($src, '<style') === false) continue;

    /* كتلُ <style> بمواضعها */
    if (!preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $src, $bm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) continue;

    $edits = array();     // موضع => array(len, replacement)
    $removedHere = 0; $rulesHere = 0; $log = array();

    foreach ($bm as $blk) {
        $blkTxt = $blk[1][0];
        $blkOff = $blk[1][1];
        if (!preg_match_all('~([^{}]+)\{([^}]*)\}~s', $blkTxt, $rm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) continue;
        foreach ($rm as $r) {
            $selRaw = $r[1][0];
            $bodyRaw = $r[2][0];
            $sel = trim(preg_replace('~\s+~', ' ', $selRaw));
            if ($sel === '' || $sel[0] === '@') continue;
            if (strpos($r[0][0], '<?') !== false) { $skippedPhp++; continue; }   /* PHP داخل CSS */
            if (!sw_targets($sel, $NOT_A_TABLE)) continue;

            $keep = array(); $drop = array();
            foreach (explode(';', $bodyRaw) as $d) {
                if (trim($d) === '') continue;
                if (strpos($d, ':') === false) { $keep[] = $d; continue; }
                list($pp, $vv) = explode(':', $d, 2);
                $pn = strtolower(trim($pp)); $vt = trim($vv);
                /* هل يحكمُ المصدرُ هذه الخاصيّةَ **لهذا الهدف** بعينِه؟ */
                $kinds = array();
                foreach (preg_split("~\s*,\s*~", trim($sel)) as $br) {
                    if (trim($br) === "") continue;
                    $k = sw_kind($br);
                    if ($k === null) { $kinds = array(); break; }
                    $kinds[$k] = true;
                }
                $govAll = count($kinds) > 0;
                foreach (array_keys($kinds) as $k) {
                    if (!in_array($pn, $GOVERNED[$k], true)) { $govAll = false; break; }
                }
                if (!$govAll) { $keep[] = $d; continue; }
                if (sw_token($vt))                   { $keep[] = $d; continue; }
                if (stripos($vt, '!important') !== false) { $keep[] = $d; continue; }
                if (!sw_raw($vt))                    { $keep[] = $d; continue; }
                $drop[] = $pn . ': ' . $vt;
            }
            if (!count($drop)) continue;

            $rulesHere++; $removedHere += count($drop);
            $log[] = array($sel, $drop);

            $ruleStart = $blkOff + $r[0][1];
            $ruleLen   = strlen($r[0][0]);
            if (count($keep)) {
                $newBody = implode(';', $keep);
                if (rtrim($newBody) !== '' && substr(rtrim($newBody), -1) !== ';') $newBody .= ';';
                $repl = $selRaw . '{' . $newBody . '}';
            } else {
                $repl = '';               /* القاعدةُ أفرغت — تُحذف كلُّها */
            }
            $edits[$ruleStart] = array($ruleLen, $repl);
        }
    }

    if (!count($edits)) continue;
    $touched++; $totalRemoved += $removedHere; $totalRules += $rulesHere;

    echo '── ' . $rel . '  (' . $removedHere . ' إعلانًا في ' . $rulesHere . ' قاعدة)' . PHP_EOL;
    foreach ($log as $l) {
        echo '     ⟨' . substr($l[0], 0, 48) . '⟩' . PHP_EOL;
        foreach ($l[1] as $d) echo '        − ' . substr($d, 0, 58) . PHP_EOL;
    }

    if ($apply) {
        krsort($edits);                   /* من الآخرِ للأوّلِ كي لا تنزاح المواضع */
        foreach ($edits as $off => $e) $src = substr($src, 0, $off) . $e[1] . substr($src, $off + $e[0]);
        file_put_contents($abs, $src);
    }
}

echo PHP_EOL . str_repeat('─', 66) . PHP_EOL;
echo ($apply ? 'كُنِس: ' : 'سيُكنَس: ') . $totalRemoved . ' إعلانًا · '
   . $totalRules . ' قاعدة · ' . $touched . ' شاشة' . PHP_EOL;
if ($skippedPhp) echo 'قواعدُ تُخطِّيت لوجودِ PHP في نصِّها: ' . $skippedPhp . PHP_EOL;
if (!$apply) echo PHP_EOL . '(معاينةٌ فقط — أضف --apply للتنفيذ)' . PHP_EOL;
