<?php
/**
 * tools/addform_toggle_probe.php — أيعمل زرُّ الترويسةِ فتحًا وطيًّا فعلًا؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **وجودُ الصنفِ ليس سلوكًا**: سطحٌ يحمل `class="allforms"` ولا زرَّ له يبقى
 *   **مخفيًّا إلى الأبد** — فالورقةُ تحمل `.allforms{display:none}`. فالسلوكُ
 *   سلسلةٌ من أربعِ حلقاتٍ تُفحص كلُّها، وانقطاعُ واحدةٍ يُعطّل الشاشة:
 *   ① زرٌّ مُعلَنٌ في `$header_actions` **وتربطه الشيفرةُ** (‏أيًّا كان معرِّفُه)
 *   ② نموذجٌ بـ`id` وصنفِ `allforms`
 *   ③ شيفرةٌ تربط الزرَّ بالنموذجِ **بمعرِّفِه هو** لا بمعرِّفٍ آخر
 *   ④ التبديلُ بصنفِ `allforms-visible`
 *   ⑤ زرُّ إلغاءٍ مربوطٌ يطوي ويُفرِّغ
 *
 * ◆ **و`ems-form` ليس عطبًا**: خطّافُ جِلدٍ بلا طيٍّ — لأسطحٍ نموذجُها ظاهرٌ
 *   دائمًا (أفعالُ `u13` · شريطا `contract_baseline` · مودالٌ · قالبُ صفّ).
 *   يُسجَّل «بلا طيٍّ بالتصميم» **لا مخالفةً**.
 *
 * ⛔ **ولا يُقاس بمعرِّفٍ مفترَض**: يُستخرَج معرِّفُ النموذجِ من الترميزِ نفسِه
 *   ثمّ يُبحَث عنه في الشيفرة — فسطحٌ نُسخ من آخرَ وبقي معرِّفُ الأصلِ في
 *   جافاسكربت يُرصَد هنا، وهو عطبٌ صامتٌ لا يظهر إلا بالنقر.
 *
 * التشغيل: php tools/addform_toggle_probe.php [--dept=DEP-NN ...]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

$DEPTS = array();
foreach ($argv as $a) { if (strpos($a, '--dept=') === 0) { $DEPTS[] = strtoupper(substr($a, 7)); } }
if (!$DEPTS) { $DEPTS = array('DEP-01', 'DEP-11', 'DEP-02', 'DEP-04'); }

$in = "'" . implode("','", array_map(array($db, 'real_escape_string'), $DEPTS)) . "'";
$rows = array();
$rs = $db->query("SELECT owner_code, route, canonical_label_ar FROM repair01_screen_registry
                   WHERE owner_code IN ($in) AND on_disk = 1 ORDER BY owner_code, route");
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }

/* المرجعُ الموجَبُ — أسطحٌ معلومةُ السلامةِ لم تُمَسَّ في هذه الجولات */
$REF = array('Clients/clients.php', 'Clients/units_of_measure.php', 'Clients/tenders.php');
foreach ($REF as $r) { $rows[] = array('owner_code' => 'مرجع', 'route' => $r, 'canonical_label_ar' => 'مرجعٌ معياريّ'); }

/** يفحص سلسلةَ الطيِّ الخماسيّةَ في مصدرِ سطحٍ */
function tp_chain($src)
{
    /* ② النموذجُ ومعرِّفُه — من الترميزِ لا بافتراض */
    $fid = null;
    if (preg_match_all('/<form\b([^>]*)>/i', $src, $mm)) {
        foreach ($mm[1] as $attrs) {
            if (!preg_match('/class\s*=\s*["\'][^"\']*\ballforms\b/i', $attrs)) { continue; }
            if (preg_match('/id\s*=\s*["\']([^"\']+)["\']/i', $attrs, $im)) { $fid = $im[1]; }
            else { $fid = ''; }                      /* نموذجُ طيٍّ بلا معرِّف */
            break;
        }
    }
    if ($fid === null) {
        $skin = (bool) preg_match('/<form\b[^>]*class\s*=\s*["\'][^"\']*\bems-form\b/i', $src);
        return array('kind' => $skin ? 'skin' : 'none');
    }

    $r = array('kind' => 'toggle', 'fid' => $fid);

    /* ⛔ **ولا يُشترَط معرِّفُ زرٍّ بعينِه**: `toggleForm` عرفٌ شائعٌ لا عقد —
       و`Operations/unbilled.php` يستعمل `cmp03AddBtn` وسلسلتُه سليمةٌ تمامًا.
       فيُستخرَج **كلُّ** معرِّفٍ أُعلن في `$header_actions` ثمّ يُبحَث عمّا
       تربطه الشيفرةُ بالنموذج — وإلّا كان الإنذارُ كذبًا في كلِّ سطحٍ سمّى زرَّه. */
    $btnIds = array();
    if (preg_match('/\$header_actions\s*(?:\[\s*\]\s*)?=.*?(?:;|\n\s*\$header_back)/s', $src, $hm)) {
        preg_match_all("/'id'\s*=>\s*'([^']+)'/", $hm[0], $bm);
        $btnIds = $bm[1];
    }
    preg_match_all("/'id'\s*=>\s*'([^']+)'[^\n]*'class'\s*=>\s*'[^']*\badd-btn\b/", $src, $am);
    $btnIds = array_unique(array_merge($btnIds, $am[1]));
    $r['btnid'] = '';
    foreach ($btnIds as $b) {
        if (preg_match('/getElementById\(\s*[\'"]' . preg_quote($b, '/') . '[\'"]|\$\(\s*[\'"]#'
                       . preg_quote($b, '/') . '[\'"]/', $src)) { $r['btnid'] = $b; break; }
    }
    $r['btn'] = $r['btnid'] !== '';

    /* ③ الربطُ بمعرِّفِ النموذجِ نفسِه — لا بمعرِّفٍ نُسخ من سطحٍ آخر */
    $r['bind'] = $fid !== '' && $r['btn']
              && (bool) preg_match('/[\'"]#?' . preg_quote($fid, '/') . '[\'"]/', $src);

    /* ④ التبديلُ بالصنفِ لا بالعرض */
    $r['cls'] = (bool) preg_match('/allforms-visible/', $src);
    /* ⛔ **وليس كلُّ `hide()` عطبًا** — وهذا ما كشفه رسوبُ المراجعِ الثلاثةِ
       المعياريّة: النمطُ القانونيُّ نفسُه يكتب `form.addClass('allforms-visible')
       .hide()` **ثمّ** `slideDown()` — إخفاءٌ لحظيٌّ ليبدأ الانزلاق. والتحذيرُ
       المحفوظُ يخصُّ **غلافَ حقلٍ** داخلَ نموذجٍ مخطوف (‏تهزمه `!important`)
       لا النموذجَ نفسَه. فأُسقِط هذا الشرطُ من النجاحِ ويبقى خبرًا:
       ⛔ **ومعيارٌ يُرسِّب المرجعَ المعياريَّ معيارٌ فاسدٌ لا مرجعٌ فاسد.** */
    $code = preg_replace(array('#/\*.*?\*/#s', '#(^|\s)//[^\n]*#m'), ' ', $src);
    $r['disp_note'] = (bool) preg_match('/\bstyle\.display\s*=/', $code);

    /* ⑤ زرُّ الإلغاء */
    $r['cancel'] = (bool) preg_match('/CancelBtn|FormCancel|formCancelBtn/i', $src);
    return $r;
}

printf("%-8s %-42s %-26s %s\n", 'الإدارة', 'الملف', 'المعرّف', 'زرّ · ربط · صنف · إلغاء');
echo str_repeat('-', 120) . "\n";
$ok = 0; $broken = array(); $skin = 0; $none = 0;
foreach ($rows as $s) {
    $p = $ROOT . '/' . $s['route'];
    if (!is_file($p)) { continue; }
    $c = tp_chain(file_get_contents($p));
    if ($c['kind'] === 'none') { $none++; continue; }
    if ($c['kind'] === 'skin') { $skin++; continue; }

    $flags = array($c['btn'], $c['bind'], $c['cls'], $c['cancel']);
    $all = !in_array(false, $flags, true);
    if ($all) { $ok++; } else { $broken[] = array($s, $c); }
    printf("%-8s %-42s %-26s %s %s\n", $s['owner_code'], mb_substr($s['route'], 0, 40),
        ($c['fid'] === '' ? '⛔ بلا معرِّف' : $c['fid']),
        implode(' ', array_map(function ($f) { return $f ? '✔' : '⛔'; }, $flags)),
        $all ? '' : '  ← سلسلةٌ منقطعة');
}
echo str_repeat('-', 120) . "\n";
printf("◆ بسلسلةِ طيٍّ كاملة: %d · منقطعة: %d · جِلدٌ بلا طيٍّ بالتصميم (`ems-form`): %d · بلا نموذجِ طيّ: %d\n",
       $ok, count($broken), $skin, $none);
if ($broken) {
    echo "\n⛔ المنقطعة:\n";
    foreach ($broken as $b) {
        $miss = array();
        if (!$b[1]['btn'])    { $miss[] = 'لا زرَّ في الترويسة'; }
        if (!$b[1]['bind'])   { $miss[] = 'الشيفرةُ لا تربط معرِّفَ النموذج'; }
        if (!$b[1]['cls'])    { $miss[] = 'لا تبديلَ بـallforms-visible'; }
        if (!$b[1]['cancel']) { $miss[] = 'لا زرَّ إلغاءٍ مربوط'; }
        printf("   %-44s %s\n", $b[0]['route'], implode(' · ', $miss));
    }
} else {
    echo "\n✔ لا سلسلةَ منقطعةً — والمراجعُ المعياريّةُ في العيّنةِ تُثبت أنّ الفحصَ يقيس شيئًا\n";
}
