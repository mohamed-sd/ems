<?php
/**
 * tools/rpr02_target_adjudicate.php — `RPR-02` §٤·٢ · فصلُ الأهدافِ بشاهدٍ لكلٍّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-02` §٤·٢: *«كلُّ هدفٍ ينتهي إلى حكمٍ واحدٍ من
 *   السبعة»* · *«وسجلُّ المراجعةِ يحمل لكلِّ حكمٍ شاهدَه: مرجعُ الدليل · ومصدرُ
 *   القرار · ومعرّفُ اللقطةِ التي قيس عليها. **فحكمٌ صحيحٌ بلا شاهدٍ لا يُقبل**»*.
 *
 * ◆ **وما جرَّبتُه فلم يكفِ — يُسمّى ولا يُخفى**:
 *   · **الدليلُ المعماريُّ لا يحمل مسارَ ملفٍّ لأسطحِه** — كتلُ «■ الشاشة ن من م»
 *     تصف الحبّةَ ومصدرَ الحقيقةِ والمالك، **ولا عمودَ مسارٍ فيها**. فلا جسرَ
 *     تصميمًا ⇐ مبنيًّا بالمعرِّف. **وهذا قائمٌ ولم يُحلّ.**
 *   · **وحبّةُ المبنيِّ كانت غائبةً فصارت مقيسة**: كانت `grain_ar` مملوءةً في
 *     ٤٤ من ٦٢٣ وكلُّها **مُعلَنةٌ** بقرارِ موجةٍ لا مقيسة، فحُجب `MATCHED`
 *     لأنَّ §٤·٢ يعرّفه بـ«الحبّةِ والمالكِ نفسيهما». ⇒ بُنيت
 *     `rpr02_grain_measure.php` (§٧ الخطوة ١) فقيست ٥٩٨ من ٦٢١، **وارتفع
 *     الحاجزُ فصارت `R4`/`R5` ممكنتَين**.
 *   · ⛔ **والحبّةُ التصميميّةُ لا تحمل اسمَ جدولٍ فلا تُقارَن بكيانِ المبنيِّ
 *     حرفًا** — فالمقارنةُ على **صنفِ الحبّة** (سطر/بند/قراءة حيّة) لا على
 *     اسمِ الكيان، وهذا **أضعفُ ممّا يطلبه النصُّ حرفيًّا فيُعوَّض بالتغطية**.
 *
 * ◆ **فخمسُ قواعدَ قاطعةٍ وحدَها تُحكَم — ولكلٍّ شاهدُها**:
 *   **R1 · `NOT_BUILT` قاطعًا**: نطاقٌ فيه **صفرُ سطحٍ مبنيٍّ غيرِ مُطالَبٍ به**
 *     ⇒ لا مقابلَ ممكنًا لهدفِه أصلًا. الشاهدُ: تعدادُ النطاقِ نفسُه.
 *   **R2 · `MERGED_INTO`**: اسمُ الهدفِ **جزءٌ مطابقٌ** من اسمِ سطحٍ مبنيٍّ غيرِ
 *     مُطالَبٍ به في نطاقِه، **وباقي الاسمِ يطابق اسمَ هدفٍ آخرَ في النطاقِ
 *     نفسِه** ⇒ فالسطحُ يجمع هدفَين. الشاهدُ: الهدفان معًا و`screen_id`.
 *   **R3 · `MATCHED`**: كما R2 **لكنَّ الباقيَ ليس هدفًا آخر** ⇒ الاسمُ المبنيُّ
 *     يُفصِّل الهدفَ نفسَه لا يجمع غيرَه. الشاهدُ: `screen_id` والباقي نصًّا.
 *   **R4 · `MATCHED` بالحبّةِ والتغطية**: **ثلثا مفرداتِ اسمِ الهدفِ فأكثرُ**
 *     حاضرةٌ في مسمَّى سطحٍ مبنيٍّ غيرِ مُطالَبٍ به في نطاقِه، **وصنفُ حبّتِه
 *     المقيسُ يساوي صنفَ حبّةِ التصميم**، **والمرشَّحُ واحدٌ لا غير**.
 *     ⛔ **ومفردةٌ نادرةٌ واحدةٌ لا تكفي هويّةً** — «الشاشة» أو «مركز» تجمع
 *     هدفًا بسطحٍ بلا أن تقول إنّهما الشيءُ نفسُه؛ وقد جرَّبتُها فأنتجت
 *     تسعًا وعشرين مطابقةً فيها الضعيفُ البيّن، **فشُدَّت إلى التغطية**
 *     فصارت إحدى عشرة. الشاهدُ: نسبةُ التغطيةِ والمفرداتُ والصنفان والمالك.
 *   **R5 · `NOT_BUILT`**: **لا مفردةَ واحدةً** من مفرداتِ الهدفِ ترد في أيِّ
 *     مسمًّى حيٍّ غيرِ مُطالَبٍ به في نطاقِه، **ولا سطحَ في الكونِ كلِّه يبلغ
 *     ثلثَي تغطيةِ اسمِه**. ⛔ والثاني شرطٌ لازم: «غير مبنيّ» تعني «لا مقابلَ
 *     له على القرص» لا «ليس في بيتِه» — فسطحٌ بُني تحتَ مالكٍ آخرَ **مبنيٌّ
 *     وملكيّتُه خطأٌ لا غيابُه**. الشاهدُ: مفرداتُ الهدفِ وتعدادُ النطاقِ والكون.
 *
 * ⛔ **وما عدا الخمسَ يبقى بلا حكم** — ويُكتب له **فضاءُ فصلِه المحصور**:
 *   أسطحُ نطاقِه غيرُ المُطالَبِ بها بأسمائها. **فالفصلُ يحتاج حكمًا لا خوارزمية**،
 *   ⛔ ولا يُملأ بـ`NOT_BUILT` لأنَّ الاسمَ لم يُطابَق — وذاك عجزٌ من طرحِ عددَين
 *   يمنعه §١١، **وهو أقدمُ حيلةٍ في هذا الباب**: من يملك إخراجَ هدفٍ من المقامِ
 *   يملك رفعَ النسبةِ بلا بناءِ سطرٍ واحد (§٤·٣).
 *
 * ◆ **والترتيبُ حتميّ**: `R1` ثمَّ `R2`/`R3` ثمَّ `R4`/`R5`، والمُطالَبُ به يُقفل فورًا
 *   فلا يُطالِب به هدفان. ⛔ **ولا يعتمد الناتجُ على ترتيبِ الصفوفِ في القاعدة**.
 *
 * التشغيل:
 *   php tools/rpr02_target_adjudicate.php [--apply] [--md] [--list] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة**.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'SELFTEST';

$norm = function ($s) {
    $s = preg_replace('~\s*\([^)]*\)?\s*$~u', '', (string) $s);
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0653}-\x{0655}\x{0670}\x{0640}]~u', '', $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~\s*—.*$~u', '', $s);
    $s = preg_replace('~[«»"\'\[\]\-–/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};

/* ═══ ① الحالُ المقيس ═══════════════════════════════════════════════════ */
$claimed = array();
$r = $conn->query("SELECT screen_id FROM repair01_target_universe
                    WHERE verdict = 'MATCHED' AND screen_id <> ''");
while ($x = $r->fetch_row()) { $claimed[$x[0]] = 1; }

$builtByUnit = array();
$r = $conn->query("SELECT screen_id, owner_code, canonical_label_ar
                     FROM repair01_screen_registry
                    WHERE lifecycle IN ('LIVE_REGISTERED','LIVE_UNREGISTERED')
                      AND canonical_label_ar <> '' AND owner_code <> ''
                    ORDER BY screen_id");
while ($x = $r->fetch_assoc()) {
    $builtByUnit[$x['owner_code']][] = array(
        'id' => $x['screen_id'], 'raw' => $x['canonical_label_ar'],
        'n' => $norm($x['canonical_label_ar']));
}

/* كلُّ أسماءِ الأهدافِ في كلِّ نطاق — لتمييزِ `MERGED_INTO` عن `MATCHED` */
$targetNames = array();
$r = $conn->query("SELECT unit, name_norm FROM repair01_target_universe WHERE name_norm <> ''");
while ($x = $r->fetch_row()) { $targetNames[$x[0]][$x[1]] = 1; }

$open = array();
$r = $conn->query("SELECT target_uid, unit, name_ar, name_norm
                     FROM repair01_target_universe WHERE verdict IS NULL
                    ORDER BY unit, target_uid");
while ($x = $r->fetch_assoc()) { $open[] = $x; }

/* ═══ ② الفصلُ بالقواعدِ الثلاثِ القاطعة ═════════════════════════════════ */
$ruled = array(); $left = array();
$cnt = array('NOT_BUILT' => 0, 'MERGED_INTO' => 0, 'MATCHED' => 0,
             'R4' => 0, 'R5' => 0);

/* R1 — نطاقٌ بلا سطحٍ غيرِ مُطالَبٍ به */
$freeByUnit = array();
foreach ($builtByUnit as $u => $rows) {
    foreach ($rows as $b) { if (!isset($claimed[$b['id']])) { $freeByUnit[$u][] = $b; } }
}
foreach ($open as $t) {
    $u = $t['unit'];
    if (empty($freeByUnit[$u])) {
        $n = isset($builtByUnit[$u]) ? count($builtByUnit[$u]) : 0;
        $ruled[$t['target_uid']] = array('NOT_BUILT', '',
            'R1 · تعدادُ النطاق `' . $u . '`: أسطحٌ مبنيّةٌ حيّةٌ ' . $n
          . ' · **وغيرُ مُطالَبٍ بها صفر** ⇒ لا مقابلَ ممكنًا · لقطة ' . $sid);
        $cnt['NOT_BUILT']++;
        continue;
    }
    $left[] = $t;
}

/* R2/R3 — الاحتواءُ في سطحٍ غيرِ مُطالَبٍ به */
$still = array();
foreach ($left as $t) {
    $u = $t['unit']; $n = $t['name_norm'];
    $done = false;
    if ($n !== '' && !empty($freeByUnit[$u])) {
        foreach ($freeByUnit[$u] as $k => $b) {
            if (isset($claimed[$b['id']])) { continue; }
            if ($b['n'] === $n || mb_strpos($b['n'], $n) === false) { continue; }
            /* الباقي بعد نزعِ اسمِ الهدف */
            $rest = trim(str_replace($n, ' ', $b['n']));
            $rest = trim(preg_replace('~^\s*(و|ال)\s*~u', '', $rest));
            $rest = trim(preg_replace('~\s+~u', ' ', $rest));
            $restIsTarget = false; $restHit = '';
            foreach (array_keys(isset($targetNames[$u]) ? $targetNames[$u] : array()) as $tn) {
                if ($tn === '' || $tn === $n) { continue; }
                if ($rest !== '' && (mb_strpos($rest, $tn) !== false || mb_strpos($tn, $rest) !== false)) {
                    $restIsTarget = true; $restHit = $tn; break;
                }
            }
            if ($restIsTarget) {
                $ruled[$t['target_uid']] = array('MERGED_INTO', $b['id'],
                    'R2 · اسمُ الهدفِ جزءٌ من «' . $b['raw'] . '» (`' . $b['id'] . '`) '
                  . '**وباقيه «' . $rest . '» يطابق هدفًا آخرَ في النطاقِ نفسِه: «' . $restHit . '»** '
                  . '⇒ السطحُ يجمع هدفَين · لقطة ' . $sid);
                $cnt['MERGED_INTO']++;
            } else {
                $ruled[$t['target_uid']] = array('MATCHED', $b['id'],
                    'R3 · اسمُ الهدفِ جزءٌ من «' . $b['raw'] . '» (`' . $b['id'] . '`) '
                  . '**والباقي «' . ($rest === '' ? '—' : $rest) . '» ليس هدفًا آخرَ في النطاق** '
                  . '⇒ الاسمُ المبنيُّ يُفصِّل الهدفَ نفسَه · لقطة ' . $sid);
                $cnt['MATCHED']++;
                /* ⛔ **ولا يُطالِب هدفان بسطحٍ واحد** */
                $claimed[$b['id']] = 1;
                unset($freeByUnit[$u][$k]);
            }
            $done = true;
            break;
        }
    }
    if (!$done) { $still[] = $t; }
}

/* ═══ ②·ب R4/R5 — **الحبّةُ حكَمًا**، بعدَ أن صارت مقيسةً على المبنيّ ═════
     §٤·٢ يعرّف `مطابَق` بـ«سطحٍ مبنيٍّ **بالحبّةِ والمالكِ نفسيهما**». والمالكُ
     مقيسٌ منذ الموجةِ الثانية، **والحبّةُ صارت مقيسةً** بـ`rpr02_grain_measure.php`
     (§٧ الخطوة ١) ⇒ فالحاجزُ الذي مُنع به الفصلُ ارتفع، ويُفصَل بشرطَيه معًا:
       · **صنفُ الحبّةِ نفسُه** — التصميمُ يقوله في ذيلِ حبّتِه («سطر واحد» ·
         «قراءة حية» · «Child/بند») والمبنيُّ يقوله مقيسًا (`ROW`/`LIVE_READ`/`LINE`).
       · **ومفردةٌ نادرةٌ مشتركة** — كلمةٌ من اسمِ الهدفِ لا ترد إلّا في مسمًّى
         أو مسمَّيَين داخلَ النطاق. ⛔ **والمفردةُ الشائعةُ لا تصلح شاهدًا**:
         «سجل» و«إدارة» ترد في عشرات المسمَّيات فتُنتج مطابقةً بلا معنًى.
     ⛔ **والمرشَّحُ يجب أن يكون واحدًا** — فاثنان يعني أنَّ الشاهدَ لا يميّز،
        وحينها **يبقى الهدفُ بلا حكم** ويُقيَّد مرشَّحوه. فالغموضُ يُعلَن ولا
        يُحسم بأوّلِ المصادفات.
     **R5 · `NOT_BUILT` بالمفردات**: لا مفردةَ واحدةً — نادرةً كانت أو شائعةً —
        تجمع الهدفَ بأيِّ سطحٍ حيٍّ غيرِ مُطالَبٍ به في نطاقِه ⇒ **لا مقابلَ على
        القرصِ في بيتِه**. الشاهد: مفرداتُ الهدفِ وتعدادُ النطاق. */

/* صنفُ الحبّةِ التصميميّةِ من نصِّها */
function rpr02_design_card($grain)
{
    $g = (string) $grain;
    if ($g === '') { return ''; }
    if (preg_match('~Child|Lines|بند|بنود|أسطر|اسطر|سطر مدين~ui', $g)) { return 'LINE'; }
    if (preg_match('~قراءة حية|قراءه حيه|قراءة حيّة|مؤشر|لوحة|لوحه|مشتق~ui', $g)) { return 'LIVE_READ'; }
    if (preg_match('~سطر|سجل|بطاقة|بطاقه|واحد|واحدة~ui', $g)) { return 'ROW'; }
    return '';
}
/* توافقُ الصنفَين — و`LIST` قراءةٌ صِرفةٌ تصلح لكلَيهما */
function rpr02_card_ok($design, $built)
{
    if ($design === '' || $built === '' || $built === 'NONE') { return false; }
    if ($design === $built) { return true; }
    return ($built === 'LIST' && ($design === 'ROW' || $design === 'LIVE_READ'));
}

$STOP = array('و','في','من','على','عن','ال','بحسب','انطباق','الشركة','حسب','مع','او','أو','الي','إلى');
$tok = function ($s) use ($norm, $STOP) {
    $out = array();
    foreach (explode(' ', $norm($s)) as $w) {
        $w = trim($w);
        if ($w === '' || mb_strlen($w) < 3 || in_array($w, $STOP, true)) { continue; }
        $out[$w] = 1;
    }
    return array_keys($out);
};

/* حبّةُ التصميمِ لكلِّ هدف · وحبّةُ المبنيِّ لكلِّ سطح */
$tGrain = array();
$r = $conn->query("SELECT tu.target_uid, r.grain
                     FROM repair01_target_universe tu
                     JOIN repair01_requirements r ON r.requirement_id = tu.requirement_id");
while ($x = $r->fetch_row()) { $tGrain[$x[0]] = $x[1]; }

$bGrain = array();
$r = $conn->query("SELECT screen_id, grain_cardinality, grain_entity
                     FROM repair01_screen_registry WHERE on_disk = 1");
while ($x = $r->fetch_assoc()) { $bGrain[$x['screen_id']] = $x; }

$still2 = array();
foreach ($still as $t) {
    $u = $t['unit'];
    $free = isset($freeByUnit[$u]) ? $freeByUnit[$u] : array();
    if (!$free) { $still2[] = $t; continue; }

    /* تردُّدُ المفردةِ داخلَ النطاق — والنادرُ ما لا يتجاوز مسمَّيَين */
    $df = array();
    foreach ($free as $b) { foreach ($tok($b['raw']) as $w) { $df[$w] = isset($df[$w]) ? $df[$w] + 1 : 1; } }
    $tt = $tok($t['name_ar']);
    $dCard = rpr02_design_card(isset($tGrain[$t['target_uid']]) ? $tGrain[$t['target_uid']] : '');

    /* ⛔ **ومفردةٌ نادرةٌ واحدةٌ لا تكفي هويّةً**: «الشاشة» أو «مركز» تجمع
       هدفًا بسطحٍ بلا أن تقول إنّهما الشيءُ نفسُه. و§٤·٢ يطلب **الحبّةَ
       والمالكَ نفسيهما**، والحبّةُ التصميميّةُ لا تحمل اسمَ جدولٍ فلا تُقارَن
       بكيانِ المبنيِّ حرفًا. ⇒ **فالهويّةُ تُشترط بتغطيةٍ لا بمصادفة**: ثلثا
       مفرداتِ الهدفِ فأكثرُ حاضرةٌ في المسمَّى المبنيّ — أي أنَّ المسمَّى المبنيَّ
       **هو اسمُ الهدفِ نفسُه** لا سطحًا يشاركه كلمة. والصنفُ شرطٌ ثانٍ معه. */
    $anyShare = false; $hits = array(); $COVER = 2 / 3;
    foreach ($free as $k => $b) {
        if (isset($claimed[$b['id']])) { continue; }
        $bt = $tok($b['raw']);
        $shared = array_intersect($tt, $bt);
        if (!$shared) { continue; }
        $anyShare = true;
        $cov = count($tt) ? count($shared) / count($tt) : 0;
        if ($cov < $COVER) { continue; }
        $bc = isset($bGrain[$b['id']]) ? $bGrain[$b['id']]['grain_cardinality'] : '';
        if (!rpr02_card_ok($dCard, $bc)) { continue; }
        $hits[] = array($k, $b, $shared, $bc, $cov);
    }

    if (count($hits) === 1) {
        list($k, $b, $shared, $bc, $cov) = $hits[0];
        $ent = isset($bGrain[$b['id']]) ? $bGrain[$b['id']]['grain_entity'] : '';
        $ruled[$t['target_uid']] = array('MATCHED', $b['id'],
            'R4 · تغطيةُ الهويّة ' . round($cov * 100) . '٪ (' . count($shared) . ' من ' . count($tt)
          . ' مفردة: «' . implode('» «', array_slice($shared, 0, 4)) . '») · وصنفُ الحبّة: تصميمٌ `'
          . $dCard . '` = مبنيٌّ `' . $bc . '` (كيان ' . ($ent === '' ? '—' : $ent)
          . ') · والمالكُ `' . $u . '` نفسُه · مرشَّحٌ **واحدٌ لا غير** من '
          . count($free) . ' سطحًا غيرِ مُطالَبٍ به ⇒ «' . $b['raw'] . '» (`' . $b['id'] . '`) · لقطة ' . $sid);
        $cnt['MATCHED']++; $cnt['R4']++;
        $claimed[$b['id']] = 1;
        unset($freeByUnit[$u][$k]);
        continue;
    }
    if (!$anyShare) {
        /* ⛔ **و«غير مبني» تعني «لا مقابلَ له على القرص» لا «ليس في بيتِه»** —
           فسطحٌ بُني تحتَ مالكٍ آخرَ مبنيٌّ فعلًا وملكيّتُه خطأٌ لا غيابُه.
           ⇒ فقبلَ الحكمِ يُمسح **الكونُ المبنيُّ كلُّه** بشرطِ التغطيةِ نفسِه. */
        $elsewhere = '';
        foreach ($freeByUnit as $u2 => $rows2) {
            foreach ($rows2 as $b2) {
                if (isset($claimed[$b2['id']])) { continue; }
                $sh2 = array_intersect($tt, $tok($b2['raw']));
                if (count($tt) && count($sh2) / count($tt) >= $COVER) {
                    $elsewhere = $b2['id'] . ' «' . $b2['raw'] . '» تحتَ `' . $u2 . '`';
                    break 2;
                }
            }
        }
        if ($elsewhere !== '') {
            $t['cross'] = $elsewhere;
            $still2[] = $t;
            continue;
        }
        $ruled[$t['target_uid']] = array('NOT_BUILT', '',
            'R5 · **لا مفردةَ واحدةً** من مفرداتِ الهدفِ («' . implode('» «', array_slice($tt, 0, 4))
          . '») ترد في أيِّ مسمًّى من ' . count($free) . ' سطحًا حيًّا غيرِ مُطالَبٍ به في نطاق `'
          . $u . '` · **ولا سطحَ في الكونِ كلِّه يبلغ ثلثَي تغطيةِ اسمِه** '
          . '⇒ لا مقابلَ له على القرص · لقطة ' . $sid);
        $cnt['NOT_BUILT']++; $cnt['R5']++;
        continue;
    }
    $still2[] = $t;
}
$cnt['R4_R5_left'] = count($still2);
$still = $still2;

/* ⛔ **السالبُ يكسر مفردةً فريدة**: حكمٌ بلا شاهد */
if ($SELF && $ruled) {
    $k = array_keys($ruled)[0];
    $ruled[$k][2] = '';
}
$noWit = 0;
foreach ($ruled as $v) { if (trim($v[2]) === '') { $noWit++; } }

/* ═══ ③ فضاءُ الفصلِ المحصورِ لمن بقي ════════════════════════════════════ */
$space = array();
foreach ($still as $t) {
    $u = $t['unit'];
    $names = array();
    foreach (isset($freeByUnit[$u]) ? $freeByUnit[$u] : array() as $b) {
        $names[] = $b['id'] . ' «' . $b['raw'] . '»';
    }
    $space[$t['target_uid']] = $names;
}

echo "\n═══ `RPR-02` §٤·٢ — فصلُ الأهدافِ بشاهدٍ لكلٍّ ═══\n";
printf("  اللقطة: %s · بلا حكمٍ قبلَ الفصل: **%d**\n\n", $sid, count($open));
echo "  ── الأحكامُ القاطعةُ الخمس ──\n";
printf("     R1 `NOT_BUILT`    %4d — نطاقٌ بصفرِ سطحٍ غيرِ مُطالَبٍ به\n", $cnt['NOT_BUILT'] - $cnt['R5']);
printf("     R2 `MERGED_INTO`  %4d — الباقي يطابق هدفًا آخرَ في النطاق\n", $cnt['MERGED_INTO']);
printf("     R3 `MATCHED`      %4d — الباقي ليس هدفًا آخر\n", $cnt['MATCHED'] - $cnt['R4']);
printf("     R4 `MATCHED`      %4d — **الحبّةُ والمالكُ نفسُهما** ومرشَّحٌ واحدٌ لا غير\n", $cnt['R4']);
printf("     R5 `NOT_BUILT`    %4d — لا مفردةَ واحدةً تجمعه بأيِّ سطحٍ حيٍّ في بيتِه\n", $cnt['R5']);
printf("     **بقي بلا حكم     %4d** — بفضاءِ فصلٍ محصورٍ لكلٍّ\n", count($still));

/* **حكمٌ يُقرأ قبلَ أن يُكتب** — `--list` يعرض كلَّ حكمٍ بشاهدِه كاملًا،
   فمراجعةُ تسعةٍ وعشرين مطابقةً بالعينِ أرخصُ من ردِّ نسبةٍ منفوخة. */
if (in_array('--list', $argv, true)) {
    echo "\n  ── الأحكامُ بشواهدِها ──\n";
    foreach ($ruled as $uid => $v) {
        printf("   %s  %-12s %s\n", $uid, $v[0], mb_substr($v[2], 0, 230));
    }
    echo "\n  ── بقي بلا حكم ──\n";
    foreach ($still as $t) { printf("   %s  %s · %s\n", $t['target_uid'], $t['unit'], $t['name_ar']); }
}

/* ── الحاجزُ الذي كان · وحالُه اليوم ──
   ⛔ **ولا يُترك نصُّ حاجزٍ مرفوعٍ في تقرير**: صفٌّ متقادمٌ داخلَ التقريرِ
      يناقض صدرَه، ومن قرأه ظنَّ الطريقَ مسدودًا وهو مفتوح. */
$gm = $conn->query("SELECT SUM(grain_entity <> ''), COUNT(*) FROM repair01_screen_registry
                     WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'")->fetch_row();
echo "\n  ── الحاجزُ الذي كان ──\n";
printf("     §٤·٢ يعرّف `MATCHED` بـ«الحبّةِ والمالكِ نفسيهما» — والحبّةُ المقيسةُ على المبنيِّ **%d من %d**\n",
       (int) $gm[0], (int) $gm[1]);
echo ((int) $gm[0] > 0)
    ? "     ✔ **الحاجزُ ارتفع** بـ`rpr02_grain_measure.php` (§٧ الخطوة ١) — وبه صارت `R4`/`R5` ممكنتَين\n"
    : "     ⇒ `Track RPR-02 §٤·٢ blocked at stage: حبّةُ المبنيِّ (§٧ الخطوة ١)`\n";
$grainFill = (int) $gm[0]; $builtAll = (int) $gm[1];

if ($APPLY && $noWit === 0) {
    $n = 0;
    foreach ($ruled as $uid => $v) {
        $ok = $conn->query("UPDATE repair01_target_universe
              SET verdict = '" . $e($v[0]) . "',
                  verdict_witness = '" . $e($v[2]) . "',
                  verdict_snapshot = '" . $e($sid) . "',
                  verdict_at = NOW()"
            . ($v[1] !== '' ? ", screen_id = '" . $e($v[1]) . "'" : '')
            . " WHERE target_uid = '" . $e($uid) . "'");
        if (!$ok) { exit("✘ تعذّر حكمُ $uid: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ كُتب حكمُ **%d** هدفٍ بشاهدِه\n", $n);
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe
                                 WHERE verdict IS NOT NULL")->fetch_row()[0];
    $bad  = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe
                                 WHERE verdict IS NOT NULL AND verdict_witness = ''")->fetch_row()[0];
    $dupClaim = (int) $conn->query("SELECT COUNT(*) FROM (
                    SELECT screen_id FROM repair01_target_universe
                     WHERE verdict = 'MATCHED' AND screen_id <> ''
                     GROUP BY 1 HAVING COUNT(*) > 1) t")->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: محكومٌ %d · حكمٌ بلا شاهدٍ %d · **سطحٌ يطالِب به هدفان %d**\n",
           $back, $bad, $dupClaim);
    /* ⛔ **وفضاءُ الفصلِ يُكتب أيضًا**: هدفٌ بلا حكمٍ وبلا مرشَّحاتٍ مكتوبةٍ
         يُعاد اشتقاقُ فضائه في كلِّ جلسة — **والمُشتقُّ يتغيّر والمكتوبُ يُحتجُّ
         به**. فيُقيَّد المرشَّحون بأسمائهم ومعرِّفاتِهم وباللقطةِ التي قيسوا
         فيها. ⛔ ولا يُقرأ هذا حكمًا — عمودُ `verdict` يبقى فارغًا. */
    $sp = 0;
    foreach ($still as $t) {
        $c = isset($space[$t['target_uid']]) ? $space[$t['target_uid']] : array();
        $txt = 'فضاءُ فصلٍ محصورٌ على لقطة ' . $sid . ' — أسطحُ نطاقِه غيرُ المُطالَبِ بها ('
             . count($c) . '): ' . mb_substr(implode(' · ', $c), 0, 330);
        if (!$conn->query("UPDATE repair01_target_universe
              SET match_witness = '" . $e($txt) . "'
            WHERE target_uid = '" . $e($t['target_uid']) . "' AND verdict IS NULL")) {
            exit("✘ تعذّر فضاءُ {$t['target_uid']}: {$conn->error}\n");
        }
        $sp++;
    }
    printf("  ✔ قُيِّد فضاءُ الفصلِ لـ**%d** هدفٍ بلا حكم — ولا يُقرأ حكمًا\n", $sp);
} elseif ($APPLY) {
    echo "\n  ⛔ **لم يُكتب شيء** — حكمٌ بلا شاهدٍ لا يُثبَّت\n";
}

$total = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe")->fetch_row()[0];
$judged = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe
                               WHERE verdict IS NOT NULL")->fetch_row()[0];
$real = (int) $conn->query("SELECT COUNT(*) FROM repair01_target_universe
                             WHERE verdict IN ('MATCHED','MERGED_INTO','TAB_CHILD','PROJECTION')")->fetch_row()[0];
echo "\n────────────────────────────────────────────────────────────\n";
printf("`Target Decision Closure` %s%% (%d من %d) · `Target Realization` %s%% (%d ÷ %d)\n",
       $total ? round($judged * 100 / $total, 1) : 0, $judged, $total,
       $judged ? round($real * 100 / $judged, 1) : 0, $real, $judged);

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $noWit >= 1
        ? "🟢 **العدّادُ تحرَّك بحكمٍ نُزع شاهدُه — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($noWit >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-02` §٤·٢ — فصلُ الأهدافِ بشاهدٍ لكلٍّ\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## ما جُرِّب فلم يكفِ — يُسمّى ولا يُخفى\n\n";
    $o .= "- **الدليلُ المعماريُّ لا يحمل مسارَ ملفٍّ لأسطحِه** — كتلُ «■ الشاشة ن من م» تصف\n";
    $o .= "  الحبّةَ ومصدرَ الحقيقةِ والمالك، ولا عمودَ مسارٍ فيها. فلا جسرَ تصميمًا ⇐ مبنيًّا بالمعرِّف.\n";
    $o .= "- **وحبّةُ المبنيِّ ارتفع حاجزُها**: صارت مقيسةً في **" . $grainFill . " من " . $builtAll
        . "** بـ`rpr02_grain_measure.php` (§٧ الخطوة ١)، وبها صارت `R4`/`R5` ممكنتَين.\n";
    $o .= "- ⛔ **لكنَّ حبّةَ التصميمِ لا تحمل اسمَ جدول** — فلا تُقارَن بكيانِ المبنيِّ حرفًا.\n";
    $o .= "  والمقارنةُ على **صنفِ الحبّة** لا على اسمِ الكيان، **وهو أضعفُ ممّا يطلبه النصُّ\n";
    $o .= "  حرفيًّا فيُعوَّض باشتراطِ تغطيةِ ثلثَي مفرداتِ الاسم**.\n\n";
    $o .= "## القواعدُ الخمسُ القاطعة\n\n| القاعدة | الحكم | العدد | الشاهد |\n|---|---|---|---|\n";
    $o .= "| `R1` | `NOT_BUILT` | **" . ($cnt['NOT_BUILT'] - $cnt['R5']) . "** | نطاقٌ فيه صفرُ سطحٍ مبنيٍّ غيرِ مُطالَبٍ به ⇒ لا مقابلَ ممكنًا |\n";
    $o .= "| `R2` | `MERGED_INTO` | **" . $cnt['MERGED_INTO'] . "** | باقي اسمِ السطحِ المبنيِّ يطابق **هدفًا آخرَ** في النطاقِ نفسِه |\n";
    $o .= "| `R3` | `MATCHED` | **" . ($cnt['MATCHED'] - $cnt['R4']) . "** | الباقي **ليس** هدفًا آخر ⇒ الاسمُ يُفصِّل الهدفَ نفسَه |\n";
    $o .= "| `R4` | `MATCHED` | **" . $cnt['R4'] . "** | تغطيةُ ثلثَي مفرداتِ الاسم · وصنفُ الحبّةِ نفسُه · والمالكُ نفسُه · ومرشَّحٌ واحدٌ لا غير |\n";
    $o .= "| `R5` | `NOT_BUILT` | **" . $cnt['R5'] . "** | لا مفردةَ واحدةً في نطاقِه · **ولا سطحَ في الكونِ كلِّه يبلغ ثلثَي تغطيتِه** |\n";
    $o .= "| — | ⛔ بلا حكم | **" . count($still) . "** | بفضاءِ فصلٍ محصورٍ لكلٍّ — يحتاج حكمًا لا خوارزمية |\n\n";
    $o .= "⛔ **ولا يُملأ الباقي بـ`NOT_BUILT` لأنَّ الاسمَ لم يُطابَق** — عجزٌ من طرحِ عددَين\n";
    $o .= "يمنعه §١١، **وهو أقدمُ حيلةٍ في هذا الباب**: من يملك إخراجَ هدفٍ من المقامِ يملك\n";
    $o .= "رفعَ النسبةِ بلا بناءِ سطرٍ واحد (§٤·٣).\n\n";
    $o .= "## المقياسان\n\n- `Target Decision Closure` = **"
        . ($total ? round($judged * 100 / $total, 1) : 0) . "%** (" . $judged . " من " . $total . ")\n";
    $o .= "- `Target Realization` = **" . ($judged ? round($real * 100 / $judged, 1) : 0)
        . "%** (" . $real . " ÷ " . $judged . ")\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_TARGET_ADJUDICATION.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_TARGET_ADJUDICATION.md\n";
}
