<?php
/**
 * tools/u13_spec_extract.php — استخراجُ عقدِ الحزمة من الوثائقِ نفسِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المصبُّ الواحد: هذا المُستخرِجُ هو **المصدرُ الوحيد** الذي يقرأ منه
 *   البذرُ (`u13_seed.php`) والفاحصُ العكسيُّ (`u13_reverse_audit.php`) معًا.
 *   فلا يدَ تنسخ رقمًا من وثيقةٍ إلى ترحيل — وكلُّ رقمٍ في الحيِّ متتبَّعٌ
 *   إلى سطرِه في الوثيقة.
 *
 * ما يُستخرَج:
 *   ① ACC-01..ACC-10   — التخصصاتُ العشرةُ بحساباتِها ونطاقِها وأبعادِها وحدِّها
 *   ② RT-01..RT-35     — مساراتُ التوجيه: المُطلِقُ · المصدرُ · الشرطُ · الوجهةُ
 *                        · الحساباتُ · الأبعادُ · السلسلةُ · الحكمُ الحارس
 *   ③ BF-01..BF-15     — المرتجَعُ الماليُّ للإدارات
 *   ④ BR-01..BR-06     — قواعدُ المرتجَع
 *   ⑤ AV-1..AV-5       — اختبارُ التجنبِ الخماسي
 *   ⑥ OB-01..OB-08     — أنواعُ الالتزام
 *   ⑦ OR-01..OR-12     — قواعدُ محرّكِ الالتزامات
 *   ⑧ AL-01..AL-12     — التنبيهات
 *   ⑨ L1..L3 · DC-1..DC-4 · IN-01..IN-08 · SR-01..SR-08 · AR-01..AR-10
 *   ⑩ APR-1..APR-4     — أنواعُ الاعتماد
 *   ⑪ شاشاتُ الالتزامات (ob_*.php) بأعمدتِها
 *   ⑫ الأرقامُ الحاكمةُ لكلِّ وثيقةٍ (جدولُ «ما قِيس»)
 *
 * التشغيل: php tools/u13_spec_extract.php [--out=docs/update0013/spec.json]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$SRC  = $ROOT . '/docs/update0013/extracted';
$out  = $ROOT . '/docs/update0013/spec.json';
foreach ($argv as $a) { if (strpos($a, '--out=') === 0) { $out = $a[6] === '/' || preg_match('~^[A-Za-z]:~', substr($a, 6)) ? substr($a, 6) : $ROOT . '/' . substr($a, 6); } }

/* ── أدواتٌ نصية ─────────────────────────────────────────────────────────── */

/** يجمع كل صفوف جدول Markdown في ملف: مصفوفةُ خلايا لكل صف. */
function md_rows($file)
{
    $rows = array();
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] !== '|') { continue; }
        if (preg_match('~^\|[\s\-\|]+\|$~u', $ln)) { continue; }   // فاصلُ الترويسة
        $cells = array_map('trim', explode('|', trim($ln, '|')));
        $rows[] = $cells;
    }
    return $rows;
}

/**
 * يجمع نصَّ كلِّ متطلبٍ ذريٍّ بمعرّفه الجذر (بلا لاحقة أ/ب/ج).
 * ◆ البادئةُ `AC-` مستثناةٌ: تلك جدولُ **معاييرِ القبول** لا سجلُّ المتطلبات —
 *   وخلطُهما يُضخِّم عددَ الأحكامِ الخامِّ فيرسب قياسُه على الوثيقة.
 */
function atoms($file, $skipAcceptance = true)
{
    $out = array();
    foreach (md_rows($file) as $c) {
        if (count($c) < 2) { continue; }
        /* المعرّفات على أشكال: OBL-0266 · CEO-Y0119 · AC-P10 · CEOX-0901 · SCN-946
           واللاحقةُ حرفٌ عربيٌّ (أ ب ج د هـ و) — والجذرُ ما قبلها. */
        if (!preg_match('~^([A-Z][A-Za-z0-9]*-[A-Za-z]?[0-9]{2,5})(?:-([\x{0600}-\x{06FF}]{1,3}))?$~u', $c[0], $m)) { continue; }
        if ($skipAcceptance && strpos($m[1], 'AC-') === 0) { continue; }
        $root = $m[1];
        if (!isset($out[$root])) { $out[$root] = array('id' => $root, 'parts' => array(), 'test' => isset($c[2]) ? $c[2] : ''); }
        $out[$root]['parts'][] = $c[1];
    }
    foreach ($out as $k => $v) { $out[$k]['text'] = implode(' · ', $v['parts']); }
    return $out;
}

/**
 * تنظيفُ نصٍّ عربي.
 * ◆ گوتشا: `trim($s, "·—")` يعمل على **البايتات** لا الأحرف — فيقطع محرفًا
 *   متعددَ البايتات نصفين ويُفسد UTF-8 فيسقط `json_encode` صامتًا.
 *   فالقصُّ هنا بتعبيرٍ نمطيٍّ بعلم /u لا بـtrim ذي قائمةِ محارف.
 */
function clean($s)
{
    $s = str_replace(array('◆', '●', '▪'), '', (string) $s);
    $s = preg_replace('~\s+~u', ' ', $s);
    if ($s === null) { return ''; }
    $s = preg_replace('~^[\s·—–\-]+|[\s·—–\-]+$~u', '', $s);
    return $s === null ? '' : $s;
}

/** يلتقط أول تطابقٍ لنمطٍ داخل نص، أو '' . */
function grab($re, $s)
{
    return preg_match($re, $s, $m) ? clean($m[1]) : '';
}

$FOBL = $SRC . '/FIN-OBL-01-v1.md';
$FACC = $SRC . '/FIN-ACC-01.md';
$PROP = $SRC . '/PROP-01.md';
foreach (array($FOBL, $FACC, $PROP) as $f) { if (!is_file($f)) { exit("ناقص: $f\n"); } }

$oblAtoms  = atoms($FOBL);
$accAtoms  = atoms($FACC);
$propAtoms = atoms($PROP);
$spec = array('generated_from' => 'docs/update0013/extracted', 'doc' => array());

/* ── ① التخصصاتُ العشرة ─────────────────────────────────────────────────── */
$specs = array();
foreach ($accAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~التخصص «(ACC-\d\d)\s*[—–-]\s*([^»]+)»~u', $t, $m)) { continue; }
    /* ◆ الاسمُ الإنجليزيُّ خارجَ «» لا داخلَها — فيُلتقط بعدَ إغلاقِ القوس. */
    $specs[$m[1]] = array(
        'code'     => $m[1],
        'name_ar'  => clean($m[2]),
        'name_en'  => grab('~«ACC-\d\d[^»]*»\s*\(([^)]+)\)~u', $t),
        /* ◆ قوائمُ الحساباتِ والأبعادِ تفصلها «·» — فالنمطُ الكسولُ إلى الحدِّ
             التالي لا إلى أولِ فاصل، وإلا بُترت «2101 · 2102 · 5105..5107». */
        'accounts' => grab('~حساباتُه (.+?)\s*·\s*ونطاقُه~u', $t),
        'scope'    => grab('~ونطاقُه (.+?)\s*·\s*وأبعادُه~u', $t),
        'dims'     => '',
        'limit'    => grab('~وحدُّه:?\s*(.+)$~u', $t),
        'src'      => $id,
    );
    if (preg_match('~وأبعادُه (.+?)·\s*وحدُّه~u', $t, $dm)) {
        preg_match_all('~D\d~u', $dm[1], $dm2);
        $specs[$m[1]]['dims'] = implode('·', array_values(array_unique($dm2[0])));
    }
}
$spec['acc_specializations'] = array_values($specs);

/* ── ② مساراتُ التوجيه ─────────────────────────────────────────────────── */
$routes = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~مسارُ التوجيه «(RT-\d\d)»~u', $t, $m)) { continue; }
    $rt = $m[1];
    if (!preg_match('~المُطلِقُ «([^»]+)» من ([^:]+):~u', $t, $mm)) { continue; }
    /* ◆ RT-17 «الحكمُ الجامع» ليس مسارًا محدَّدًا بل القاعدةُ الاحتياطية:
         وجهتُه «محاسبُ التخصصِ المسنَدُ للإدارةِ ونوعِ الواقعة» لا رمزُ ACC —
         فيُوسَم `fallback` ولا يُطلب منه رمزُ تخصصٍ ولا أبعادٌ ثابتة. */
    $target     = grab('~ويُوجَّه إلى (.+?)\s*·\s*وحساباتُه~u', $t);
    $isFallback = !preg_match('~^ACC-\d\d~u', $target);
    $routes[$rt] = array(
        'code'      => $rt,
        'kind'      => $isFallback ? 'fallback' : 'route',
        'trigger'   => clean($mm[1]),
        'source'    => clean($mm[2]),
        'condition' => grab('~شرطُ إطلاقِه (.+?)\s*·\s*ويُوجَّه~u', $t),
        'target'    => $target,
        'target_spec' => $isFallback ? '' : substr($target, 0, 6),
        'accounts'  => '',
        'dims'      => '',
        /* ◆ السلسلةُ تُقرأ من **مقطعِها** لا من النصِّ المدموج: الجزءُ «ب» يحمل
             عقدَ المسار والأجزاءُ بعدَه تحمل الحكمَ الحارس — فالقراءةُ من المدموج
             تبتلع الحارسَ داخلَ السلسلة. */
        'chain'     => grab('~وسلسلتُه (.+)$~u', isset($a['parts'][1]) ? $a['parts'][1] : ''),
        'guard'     => clean(implode(' · ', array_slice($a['parts'], 2))),
        'test'      => clean($a['test']),
        'src'       => $id,
    );
    if (preg_match('~وحساباتُه (.+?)\s*·\s*وأبعادُه~u', $t, $am)) { $routes[$rt]['accounts'] = clean($am[1]); }
    if (preg_match('~وأبعادُه (.+?)\s*·\s*وسلسلتُه~u', $t, $dm2)) {
        preg_match_all('~D\d~u', $dm2[1], $dm3);
        $routes[$rt]['dims'] = implode('·', array_values(array_unique($dm3[0])));
        if ($routes[$rt]['dims'] === '') { $routes[$rt]['dims'] = clean($dm2[1]); }
    }
}
ksort($routes, SORT_NATURAL);
$spec['routes'] = array_values($routes);

/* ── ③ المرتجَعُ الماليُّ BF-01..BF-15 ──────────────────────────────────── */
$bf = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~المرتجَع «(BF-\d\d)\s*[—–-]\s*([^»]+)»~u', $t, $m)) { continue; }
    $bf[$m[1]] = array(
        'code'    => $m[1],
        'title'   => clean($m[2]),
        'trigger' => grab('~يُطلق (.+?)·\s*ووجهتُه~u', $t),
        'to'      => grab('~ووجهتُه (.+?)(?:·|$)~u', $t),
        'rule'    => clean(implode(' · ', array_slice($a['parts'], 2))),
        'src'     => $id,
    );
}
ksort($bf, SORT_NATURAL);
$spec['backflow'] = array_values($bf);

/* ── ④ قواعدُ المرتجَع BR-01..BR-06 ────────────────────────────────────── */
$br = array();
foreach ($oblAtoms as $id => $a) {
    if (!preg_match('~قاعدةُ المرتجَع «(BR-\d\d)»~u', $a['text'], $m)) { continue; }
    $body = clean(implode(' · ', array_slice($a['parts'], 1)));
    $br[$m[1]] = array('code' => $m[1], 'rule' => $body, 'test' => clean($a['test']), 'src' => $id);
}
ksort($br, SORT_NATURAL);
$spec['backflow_rules'] = array_values($br);

/* ── ⑤ اختبارُ التجنب AV-1..AV-5 ──────────────────────────────────────── */
$av = array();
foreach ($oblAtoms as $id => $a) {
    if (!preg_match('~اختبارُ التجنب «(AV-\d)»~u', $a['text'], $m)) { continue; }
    $t = $a['text'];
    $av[$m[1]] = array(
        'code'     => $m[1],
        'question' => grab('~يسأل «([^»]+)»~u', $t),
        'outcome'  => grab('~فالنتيجةُ (.+)$~u', $t),
        'src'      => $id,
    );
}
ksort($av, SORT_NATURAL);
$spec['avoidance_tests'] = array_values($av);

/* ── ⑥ أنواعُ الالتزام OB-01..OB-08 ───────────────────────────────────── */
$ob = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~نوعُ الالتزام «(OB-\d\d)\s*[—–-]\s*([^»]+)»~u', $t, $m)) { continue; }
    $ob[$m[1]] = array(
        'code'      => $m[1],
        'title'     => clean($m[2]),
        'created_at_event' => grab('~يُنشأ (.+?)·\s*وحساباتُه~u', $t),
        'accounts'  => grab('~وحساباتُه (.+?)·\s*ويُحسب~u', $t),
        'formula'   => grab('~ويُحسب (.+?)(?:\s*·\s*[◆]|$)~u', $t),
        'term_rule' => clean(implode(' · ', array_slice($a['parts'], 2))),
        'src'       => $id,
    );
}
ksort($ob, SORT_NATURAL);
$spec['obligation_types'] = array_values($ob);

/* ── ⑦ قواعدُ الالتزامات OR-01..OR-12 ─────────────────────────────────── */
$or = array();
foreach ($oblAtoms as $id => $a) {
    if (!preg_match('~قاعدةُ الالتزامات «(OR-\d\d)»~u', $a['text'], $m)) { continue; }
    $or[$m[1]] = array('code' => $m[1], 'rule' => clean(implode(' · ', array_slice($a['parts'], 1))), 'test' => clean($a['test']), 'src' => $id);
}
ksort($or, SORT_NATURAL);
$spec['obligation_rules'] = array_values($or);

/* ── ⑧ التنبيهات AL-01..AL-12 ─────────────────────────────────────────── */
$al = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~التنبيه «(AL-\d\d)~u', $t, $m)) { continue; }
    $title = grab('~التنبيه «AL-\d\d\s*[—–-]\s*([^»]+)»~u', $t);
    if ($title === '') { $title = clean(isset($a['parts'][1]) ? preg_replace('~»:.*$~u', '', $a['parts'][1]) : ''); }
    $al[$m[1]] = array(
        'code'    => $m[1],
        'title'   => $title,
        'trigger' => grab('~يُطلق (.+?)·\s*ووجهتُه~u', $t),
        'to'      => grab('~ووجهتُه (.+?)·\s*والخطرُ~u', $t),
        'risk'    => grab('~والخطرُ عند إهماله (.+)$~u', $t),
        'src'     => $id,
    );
}
ksort($al, SORT_NATURAL);
$spec['alerts'] = array_values($al);

/* ── ⑨ الطبقاتُ الثلاث · التصنيفُ الرباعيُّ · التوريثُ · المورد · الاستحقاق ─ */
$layers = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~طبقةُ الاعتراف «(L\d)~u', $t, $m)) { continue; }
    $layers[$m[1]] = array(
        'code'  => $m[1],
        'title' => clean(grab('~طبقةُ الاعتراف «L\d\s*[—–-]?\s*([^»(]+)~u', $t)),
        'birth' => grab('~تنشأ (.+?)·\s*وحكمُها~u', $t),
        'rule'  => grab('~وحكمُها (.+?)·\s*وعلى الجانبين~u', $t),
        'sides' => grab('~وعلى الجانبين (.+)$~u', $t),
        'src'   => $id,
    );
}
ksort($layers, SORT_NATURAL);
$spec['recognition_layers'] = array_values($layers);

$dc = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~صنفُ البيانات «(DC-\d)\s*[—–-]\s*([^»(]+)~u', $t, $m)) { continue; }
    $dc[$m[1]] = array(
        'code'     => $m[1],
        'title'    => clean($m[2]),
        'name_en'  => grab('~صنفُ البيانات «DC-\d[^(]*\(([^)]+)\)~u', $t),
        'meaning'  => grab('~نطاقُ الحكم · (.+?)·\s*وأمثلتُه~u', $t),
        'examples' => grab('~وأمثلتُه (.+?)·\s*ومالكُه~u', $t),
        'owner'    => grab('~ومالكُه (.+)$~u', $t),
        'src'      => $id,
    );
}
ksort($dc, SORT_NATURAL);
$spec['data_classes'] = array_values($dc);

foreach (array('inheritance' => 'قاعدةُ التوريث «(IN-\d\d)»', 'supplier_rules' => 'قاعدةُ التزامِ المورد «(SR-\d\d)»',
               'accrual_rules' => 'قاعدةُ الاستحقاق «(AR-\d\d)»', 'symmetry_rules' => 'قاعدةُ التناظر «(SY-\d\d)»') as $key => $re) {
    $bag = array();
    foreach ($oblAtoms as $id => $a) {
        if (!preg_match('~' . $re . '~u', $a['text'], $m)) { continue; }
        $bag[$m[1]] = array('code' => $m[1], 'rule' => clean(implode(' · ', array_slice($a['parts'], 1))), 'test' => clean($a['test']), 'src' => $id);
    }
    ksort($bag, SORT_NATURAL);
    $spec[$key] = array_values($bag);
}

/* ── ⑨-ج اختصاصاتُ المراجعةِ وصلاحياتُها (IAF §٤-٣ · §٤-٤) ────────────── */
$iafAtoms = atoms($SRC . '/IAF-01.md');
$iafComp = array(); $iafAuth = array(); $ci = 0; $ai = 0;
foreach ($iafAtoms as $id => $a) {
    $t = $a['text'];
    if (preg_match('~اختصاصُ المراجعةِ الداخلية:?\s*(.+?)$~u', $t, $m)) {
        $title = clean($m[1]);
        if ($title === '' || mb_strpos($title, 'نطاقُ الحكم') === 0) {
            $title = clean(isset($a['parts'][1]) ? $a['parts'][1] : '');
        }
        if ($title === '') { continue; }
        $iafComp[] = array('code' => sprintf('IAF-C%02d', ++$ci), 'seq' => $ci,
                           'title' => $title, 'test' => clean($a['test']), 'src' => $id);
        continue;
    }
    if (preg_match('~صلاحيةُ المراجعِ الداخليِّ في النظام:?\s*(.+?)$~u', $t, $m)) {
        $title = clean($m[1]);
        if ($title === '' || mb_strpos($title, 'نطاقُ الحكم') === 0) {
            $title = clean(isset($a['parts'][1]) ? $a['parts'][1] : '');
        }
        if ($title === '') { continue; }
        /* IAF-0043 وحدَها نفيٌ صريحٌ للكتابة — وما عداها قراءةٌ أو كتابةٌ في سجلِّها. */
        $mode = (mb_strpos($title, 'لا يملك كتابةً') !== false) ? 'forbidden'
              : ((mb_strpos($title, 'فتحُ') !== false || mb_strpos($title, 'طلبُ') !== false
                  || mb_strpos($title, 'متابعةُ') !== false || mb_strpos($title, 'تصعيدُ') !== false
                  || mb_strpos($title, 'التحققُ') !== false || mb_strpos($title, 'سحبُ') !== false)
                 ? 'write_own' : 'read');
        $iafAuth[] = array('code' => sprintf('IAF-A%02d', ++$ai), 'seq' => $ai,
                           'title' => $title, 'mode' => $mode, 'test' => clean($a['test']), 'src' => $id);
    }
}
$spec['iaf_competencies'] = $iafComp;
$spec['iaf_authorities']  = $iafAuth;

/* ── ⑨-أ شروطُ الاعترافِ بمعيارِ كلِّ نوعِ عقد (§٤-١٢) ─────────────────── */
$recog = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~شرطُ الاعتراف لـ«([^»]+)»~u', $t, $m)) { continue; }
    $recog[] = array(
        'kind'     => clean($m[1]),
        'standard' => grab('~معيارُه (.+?)\s*·\s*(?:و?يتحقق)~u', $t),
        'trigger'  => grab('~يتحقق (.+?)\s*·\s*فالأولى~u', $t),
        'layers'   => grab('~فالأولى (.+?)(?:\s*·\s*$|$)~u', $t),
        'guard'    => clean(end($a['parts'])),
        'src'      => $id,
    );
}
$spec['recognition_conditions'] = $recog;

/* ── ⑨-ب أزواجُ فصلِ الواجبات ─────────────────────────────────────────── */
/* الشكل: الجزءُ «ب» يحمل «A» لا يُجمع مع «B» · والجزءُ «ج» يحمل لماذا.
   والمصدرُ الأمُّ FIN-ACC-01 §٤-٩ — وتُقارَن به بقيةُ الوثائقِ عددًا. */
$sod = array(); $i = 0;
foreach ($accAtoms as $id => $a) {
    if (!preg_match('~فصلُ الواجبات~u', $a['text'])) { continue; }
    if (!preg_match('~«([^»]+)»\s*لا يُجمع مع\s*«([^»]+)»~u', $a['text'], $m)) { continue; }
    $i++;
    $sod[] = array(
        'code'   => sprintf('SOD-%02d', $i),
        'func_a' => clean($m[1]),
        'func_b' => clean($m[2]),
        'why'    => clean(implode(' · ', array_slice($a['parts'], 2))),
        'test'   => clean($a['test']),
        'src'    => $id,
    );
}
$spec['sod_pairs'] = $sod;
/* عددُ الأزواجِ كما تحمله كلُّ وثيقةٍ — الأربعُ تعلن «١٣ زوجًا» فيُقارَن. */
$spec['sod_declared'] = array();
foreach (array('FIN-OBL-01' => 'FIN-OBL-01-v1.md', 'PROP-01' => 'PROP-01.md', 'FIN-ACC-01' => 'FIN-ACC-01.md',
               'FIN-CTRL-01' => 'FIN-CTRL-01.md', 'FIN-MGR-01' => 'FIN-MGR-01.md',
               'FIN-TRE-01' => 'FIN-TRE-01.md', 'IAF-01' => 'IAF-01.md') as $code => $fn) {
    $p = $SRC . '/' . $fn;
    if (!is_file($p)) { continue; }
    $n = 0;
    foreach (atoms($p) as $a) {
        if (preg_match('~«[^»]+»\s*لا يُجمع مع\s*«[^»]+»~u', $a['text'])) { $n++; }
    }
    if ($n > 0) { $spec['sod_declared'][$code] = $n; }
}

/* ── ⑩ أنواعُ الاعتماد APR-1..APR-4 ───────────────────────────────────── */
$apr = array();
foreach ($accAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~نوعُ الاعتماد «(APR-\d)»\s*[—–-]\s*(.+?)\s*[—–-]\s*نطاقُ الحكم~u', $t, $m)) { continue; }
    $apr[$m[1]] = array(
        'code'     => $m[1],
        'title'    => clean($m[2]),
        'owner'    => grab('~صاحبُه (.+?)·\s*يسأل~u', $t),
        'question' => grab('~يسأل «([^»]+)»~u', $t),
        'rule'     => clean(implode(' · ', array_slice($a['parts'], 2))),
        'src'      => $id,
    );
}
ksort($apr, SORT_NATURAL);
$spec['approval_types'] = array_values($apr);

/* ── ⑪ شاشاتُ الالتزامات ──────────────────────────────────────────────── */
$scr = array();
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (!preg_match('~شاشةُ الالتزامات «([^»]+)»~u', $t, $m)) { continue; }
    $scr[] = array(
        'title'   => clean($m[1]),
        'nature'  => grab('~طبيعتُها ([^·]+)·~u', $t),
        'file'    => grab('~وملفُّها ([A-Za-z0-9_]+\.php)~u', $t),
        'columns' => (int) grab('~وأعمدتُها (\d+)~u', $t),
        'src'     => $id,
    );
}
$spec['obligation_screens'] = $scr;

/* ── ⑫ الأعمدةُ الإلزامية ─────────────────────────────────────────────── */
$mand = array('avoidance' => array(), 'schedule' => array());
foreach ($oblAtoms as $id => $a) {
    $t = $a['text'];
    if (preg_match('~عمودٌ إلزاميٌّ في كلِّ شاشةِ عقدٍ والتزامٍ واستحقاق~u', $t)) {
        $c = clean(grab('~نطاقُ الحكم · «([^»]+)»~u', $t));
        if ($c !== '') { $mand['avoidance'][] = array('col' => $c, 'src' => $id); }
    }
    if (preg_match('~عمودٌ إلزاميٌّ في جدولِ الاستحقاقِ والالتزام~u', $t)) {
        $c = clean(grab('~«([^»]+)»~u', $t));
        if ($c !== '') { $mand['schedule'][] = array('col' => $c, 'src' => $id); }
    }
}
$spec['mandatory_columns'] = $mand;

/* ── ⑬ الأرقامُ الحاكمةُ لكلِّ وثيقة ────────────────────────────────────── */
$docs = array('FIN-OBL-01' => 'FIN-OBL-01-v1.md', 'PROP-01' => 'PROP-01.md', 'FIN-ACC-01' => 'FIN-ACC-01.md',
              'FIN-CTRL-01' => 'FIN-CTRL-01.md', 'FIN-MGR-01' => 'FIN-MGR-01.md', 'FIN-TRE-01' => 'FIN-TRE-01.md', 'IAF-01' => 'IAF-01.md');
foreach ($docs as $code => $fn) {
    $p = $SRC . '/' . $fn;
    if (!is_file($p)) { continue; }
    $txt  = file_get_contents($p);
    $meta = array('file' => $fn);
    $meta['raw_rulings'] = (int) grab('~\| الأحكامُ الخام \| (\d+) حكمًا \|~u', $txt);
    $meta['atomic']      = (int) grab('~\| المتطلباتُ الذرية \| [^0-9]*(\d+) متطلبًا~u', $txt);
    $meta['atoms_found'] = count(atoms($p));
    $meta['rulings_match'] = ($meta['atoms_found'] === $meta['raw_rulings']);
    $docs2 = array();
    /* جدولُ «ما قِيس | العدد | الدلالة» */
    $inTable = false;
    foreach (file($p, FILE_IGNORE_NEW_LINES) as $ln) {
        $ln = trim($ln);
        if (strpos($ln, '| ما قِيس |') === 0) { $inTable = true; continue; }
        if ($inTable) {
            if ($ln === '' || $ln[0] !== '|') { $inTable = false; continue; }
            if (preg_match('~^\|[\s\-\|]+\|$~u', $ln)) { continue; }
            $c = array_map('trim', explode('|', trim($ln, '|')));
            if (count($c) >= 2) { $docs2[clean($c[0])] = clean($c[1]); }
        }
    }
    $meta['governing_numbers'] = $docs2;
    $spec['doc'][$code] = $meta;
}

/* ── ⑭ كشفُ التعارضِ داخلَ الوثائقِ نفسِها ───────────────────────────────── */
/* ◆ الوثيقةُ ليست معصومة: ترويستُها تعلن عددًا وسجلُّها الذريُّ يسجّل غيرَه.
     والكشفُ آليٌّ هنا فلا يعتمد على عينٍ تقرأ — ويظهر تلقائيًّا متى تغيّرت. */
$spec['variances'] = array();
$vi = 0;
foreach ($docs as $code => $fn) {
    $p = $SRC . '/' . $fn;
    if (!is_file($p)) { continue; }
    $txt = file_get_contents($p);

    /* ① الشاشات: المعلَنُ في الترويسةِ · المعلَنُ في «الأرقامِ الحاكمة» · المسجَّلُ ذريًّا */
    $hdrN = grab('~\| الشاشاتُ المملوكة \| ([٠-٩0-9]+) شاش~u', $txt);
    $numN = isset($spec['doc'][$code]['governing_numbers']['الشاشاتُ المملوكة'])
          ? $spec['doc'][$code]['governing_numbers']['الشاشاتُ المملوكة'] : '';
    $hdrCols = grab('~\| الشاشاتُ المملوكة \| [٠-٩0-9]+ شاشةً بـ([٠-٩0-9]+) عمودًا~u', $txt);
    $ar2en = function ($s) {
        return strtr((string) $s, array('٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                                        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
    };
    $hdr = (int) $ar2en($hdrN);
    $num = (int) $ar2en($numN);
    $cols = (int) $ar2en($hdrCols);
    $reg  = ($code === 'FIN-OBL-01') ? count($spec['obligation_screens']) : 0;
    $regCols = 0;
    if ($code === 'FIN-OBL-01') { foreach ($spec['obligation_screens'] as $s) { $regCols += (int) $s['columns']; } }

    /* الترويسةُ تخالف «الأرقامَ الحاكمة» في الوثيقةِ نفسِها */
    if ($hdr > 0 && $num > 0 && $hdr !== $num) {
        $spec['variances'][] = array(
            'code' => sprintf('V-%02d', ++$vi), 'doc' => $code,
            'subject' => 'عددُ الشاشاتِ المملوكة',
            'declared_where' => 'ترويسةُ الوثيقة', 'declared_value' => (string) $hdr,
            'registered_where' => 'جدولُ الأرقامِ الحاكمة §٢-١', 'registered_value' => (string) $num,
            'kind' => 'internal_contradiction',
        );
    }
    /* المعلَنُ يخالف المسجَّلَ ذريًّا */
    if ($hdr > 0 && $reg > 0 && $hdr !== $reg) {
        $spec['variances'][] = array(
            'code' => sprintf('V-%02d', ++$vi), 'doc' => $code,
            'subject' => 'عددُ الشاشاتِ المملوكة',
            'declared_where' => 'ترويسةُ الوثيقة', 'declared_value' => (string) $hdr,
            'registered_where' => 'السجلُّ الذريُّ §٤-٢٣', 'registered_value' => (string) $reg,
            'kind' => 'declared_vs_register',
        );
    }
    if ($cols > 0 && $regCols > 0 && $cols !== $regCols) {
        $spec['variances'][] = array(
            'code' => sprintf('V-%02d', ++$vi), 'doc' => $code,
            'subject' => 'مجموعُ أعمدةِ الشاشاتِ المملوكة',
            'declared_where' => 'ترويسةُ الوثيقة', 'declared_value' => (string) $cols,
            'registered_where' => 'مجموعُ أعمدةِ السجلِّ الذري', 'registered_value' => (string) $regCols,
            'kind' => 'declared_vs_register',
        );
    }
    /* عددٌ معلَنٌ بلا سجلٍّ ذريٍّ أصلًا — لا يُبنى على رقمٍ بلا نصّ */
    if ($hdr > 0 && $reg === 0 && $code !== 'PROP-01') {
        $spec['variances'][] = array(
            'code' => sprintf('V-%02d', ++$vi), 'doc' => $code,
            'subject' => 'شاشاتٌ معلَنةٌ بلا سجلٍّ ذريّ',
            'declared_where' => 'ترويسةُ الوثيقة', 'declared_value' => (string) $hdr,
            'registered_where' => 'السجلُّ الذريّ', 'registered_value' => '0',
            'kind' => 'declared_without_register',
        );
    }
}
/* ② أزواجُ فصلِ الواجبات: PROP-01 جدولُها يخالف سجلَّها */
/* ◆ العدُّ يبدأ **بعد** صفِّ الترويسة: ترويسةُ الجدولِ نفسُها تحمل «لا تُجمع مع»
     فعدُّها صفًّا يُنتج واحدًا بدل سبعة. */
$propTable = 0; $inTbl = false;
foreach (md_rows($SRC . '/PROP-01.md') as $c) {
    if (count($c) >= 3 && trim($c[1]) === 'لا تُجمع مع') { $inTbl = true; continue; }
    if ($inTbl) {
        if (count($c) < 3 || trim($c[0]) === '') { $inTbl = false; continue; }
        $propTable++;
    }
}
$propAtoms = isset($spec['sod_declared']['PROP-01']) ? (int) $spec['sod_declared']['PROP-01'] : 0;
if ($propTable > 0 && $propAtoms > 0 && $propTable !== $propAtoms) {
    $spec['variances'][] = array(
        'code' => sprintf('V-%02d', ++$vi), 'doc' => 'PROP-01',
        'subject' => 'أزواجُ فصلِ الواجباتِ المنتشرة',
        'declared_where' => 'جدولُ §٤-٢', 'declared_value' => (string) $propTable,
        'registered_where' => 'السجلُّ الذريّ', 'registered_value' => (string) $propAtoms,
        'kind' => 'declared_vs_register',
    );
}

/* ── الإخراج ─────────────────────────────────────────────────────────────── */
@mkdir(dirname($out), 0777, true);
$json = json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) { exit('تعذّر الترميز: ' . json_last_error_msg() . "\n"); }
file_put_contents($out, $json);

printf("✔ %s\n", str_replace($ROOT . '/', '', str_replace('\\', '/', $out)));
printf("  التخصصات %d · المسارات %d · المرتجَع %d · قواعدُه %d · التجنب %d\n",
    count($spec['acc_specializations']), count($spec['routes']), count($spec['backflow']),
    count($spec['backflow_rules']), count($spec['avoidance_tests']));
printf("  أنواعُ الالتزام %d · قواعدُه %d · التنبيهات %d · الطبقات %d · الأصناف %d\n",
    count($spec['obligation_types']), count($spec['obligation_rules']), count($spec['alerts']),
    count($spec['recognition_layers']), count($spec['data_classes']));
printf("  التوريث %d · المورد %d · الاستحقاق %d · التناظر %d · الاعتماد %d · الشاشات %d\n",
    count($spec['inheritance']), count($spec['supplier_rules']), count($spec['accrual_rules']),
    count($spec['symmetry_rules']), count($spec['approval_types']), count($spec['obligation_screens']));
printf("  الأعمدةُ الإلزامية: تجنب %d · جدول %d\n", count($mand['avoidance']), count($mand['schedule']));
printf("  ◆ تعارضاتٌ مكشوفةٌ داخلَ الوثائق: %d\n", count($spec['variances']));
foreach ($spec['variances'] as $v) {
    printf("     %s %-12s %-34s %s=%s ✕ %s=%s\n", $v['code'], $v['doc'], mb_substr($v['subject'], 0, 32),
        mb_substr($v['declared_where'], 0, 18), $v['declared_value'],
        mb_substr($v['registered_where'], 0, 22), $v['registered_value']);
}
$allMatch = true;
foreach ($spec['doc'] as $c => $m) {
    if (!$m['rulings_match']) { $allMatch = false; }
    printf("  %-12s خام %-4s ذرية %-4s وُجد %-4s %s\n", $c, $m['raw_rulings'], $m['atomic'], $m['atoms_found'], $m['rulings_match'] ? '✔' : '✗');
}
printf("\n  %s\n", $allMatch ? '✔ الأحكامُ الخامُّ تُستنسَخ من الوثائقِ السبعِ كلِّها بلا فرق'
                             : '✗ فرقٌ بين المعلَنِ والمستخرَج — لا يُبذر قبلَ حسمِه');
exit($allMatch ? 0 : 1);
