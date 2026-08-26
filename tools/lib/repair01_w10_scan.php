<?php
/**
 * tools/lib/repair01_w10_scan.php — مكتبةُ قياسِ المرحلةِ العاشرة (شقُّ الوحدة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مفرداتُ الشقِّ تُشتَقُّ ولا تُكتَب**: `repair01_w10_vocab` يُبنى من ثلاثةِ
 *   مصادرَ في المخزنِ مرتَّبةِ الأسبقيّة — سطحُ المتطلَّبِ · بندُ قاعدةِ الشقِّ ·
 *   اسمُ الإدارةِ المعياريّ. **ولا قائمةَ أسماءِ ملفّاتٍ في هذا الملفّ ولا في
 *   غيرِه** — وهي صيغةُ W01 (‏`$FIN_TRE` و`$FIN_SHARED`) التي تُستبدَل هنا.
 *
 * ◆ **والمفردةُ كلمتانِ فأكثر**: «الحسابات» وحدَها تقع في «دليل الحسابات» (‏قيدٌ)
 *   وفي «الحسابات البنكية» (‏خزينة)، و«الصرف» في «أسعار الصرف» (‏قاعدةٌ محاسبيّة)
 *   وفي «تنفيذ الصرف» (‏تنفيذٌ نقديّ). **والمفردةُ المفردةُ تشقُّ بالخطأ**، فالحدُّ
 *   الأدنى كلمتانِ ويُقرأ من `repair01_w10_thresholds` لا من رقمٍ في الشيفرة.
 *
 * ◆ **ونصُّ المطابقةِ لا يشمل المجموعةَ ولا المرحلة**: «الخزينة والبنوك» مجموعةٌ
 *   حيّةٌ **تجمع الشقَّين معًا** — فمطابقةُ عنوانِ المجموعةِ تجرُّ «ذمم الموردين»
 *   إلى الخزينة. المطابقةُ على **اسمِ السطحِ نفسِه** وحدَه ومسمّاه المعياريِّ
 *   وجذعِ ملفِّه.
 *
 * ◆ **والمراجعةُ تبقى مع المحاسبة**: «مراجعة محاضر الجرد النقدي» و«مراجعة
 *   المطابقات البنكية» سطحا **رقابةٍ على مخرَجِ الخزينة** لا سطحا تنفيذٍ نقديّ —
 *   وإسنادُهما إلى `DEP-06` يجعل المنفِّذَ مراجعَ نفسِه. القاعدةُ صيغةُ الاسمِ لا
 *   قائمةُ ملفّات.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** استعلامُ قيمةٍ واحدة */
function repair01_w10_one(mysqli $conn, $sql)
{
    $r = @$conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}

/** الوحدتانِ المشقوقتانِ بمسمّاهما الحيّ — من الجسرِ لا من نصٍّ مكتوب */
function repair01_w10_split_units(mysqli $conn)
{
    $out = array();
    $r = $conn->query("SELECT legacy_name, canonical_code, split_rule, note
                         FROM repair01_dept_crosswalk WHERE verdict = 'SPLIT'
                        ORDER BY legacy_name, canonical_code");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['legacy_name']][$x['canonical_code']] = array('rule' => (string) $x['split_rule'],
                                                              'note' => (string) $x['note']);
    }
    return $out;
}

/* ══════════════════════════════════════════════════════════════════════════
   ① التطبيعُ — النصُّ يُقارَن بعد نزعِ ما لا يحمل معنى
   ══════════════════════════════════════════════════════════════════════════
   ⚠ **ونزعُ التشكيلِ هنا لا يمسُّ صفًّا حيًّا**: النصُّ يُنسَخ ويُطبَّع في الذاكرة
     للمقارنةِ وحدَها — والقاعدةُ ③ في معيارِ النقاءِ (‏قيمُ البيانات) محفوظة.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_norm($s)
{
    $s = (string) $s;
    /* التشكيلُ والتطويل */
    $s = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $s);
    /* توحيدُ الألفِ والياءِ والتاءِ المربوطة */
    $s = preg_replace('/[\x{0622}\x{0623}\x{0625}\x{0627}]/u', 'ا', $s);
    $s = preg_replace('/[\x{0649}]/u', 'ي', $s);
    $s = preg_replace('/[\x{0629}]/u', 'ه', $s);
    /* ما ليس حرفًا عربيًّا ولا لاتينيًّا ولا رقمًا يصير مسافة */
    $s = preg_replace('/[^\x{0621}-\x{064A}a-zA-Z0-9]+/u', ' ', $s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return $s;
}

/** كلماتُ الوصلِ التي لا تحمل معنى تمييزيًّا */
function repair01_w10_stopwords()
{
    return array('و', 'في', 'من', 'علي', 'الي', 'عن', 'مع', 'او', 'ما', 'بحسب',
                 'انطباق', 'الشركه', 'حسب', 'the', 'of', 'and', 'for', 'within');
}

/** يقسم نصًّا مطبَّعًا إلى كلماتٍ ذاتِ معنى */
function repair01_w10_words($norm)
{
    $stop = repair01_w10_stopwords();
    $out = array();
    foreach (explode(' ', $norm) as $w) {
        if ($w === '' || in_array($w, $stop, true)) { continue; }
        $out[] = $w;
    }
    return $out;
}

/* ══════════════════════════════════════════════════════════════════════════
   ② اشتقاقُ المفرداتِ من المخزن — ثلاثةُ مصادرَ بأسبقيّةٍ مقروءةٍ من السجلّ
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_thresholds(mysqli $conn)
{
    $out = array();
    $r = $conn->query("SELECT threshold_key, value_num, why, ref FROM repair01_w10_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('v' => (int) $x['value_num'], 'why' => (string) $x['why'],
                                          'ref' => (string) $x['ref']);
    }
    return $out;
}

/**
 * يشتقُّ مفرداتِ الشقِّ من المخزن.
 * تُعيد مصفوفةَ صفوفٍ جاهزةٍ للكتابةِ في `repair01_w10_vocab` — ولا تكتب.
 */
function repair01_w10_derive_vocab(mysqli $conn)
{
    $TH = repair01_w10_thresholds($conn);
    if (!isset($TH['W10_W_REQUIREMENT_SURFACE'], $TH['W10_W_CROSSWALK_CLAUSE'],
               $TH['W10_W_DEPARTMENT_NAME'], $TH['W10_MIN_TOKEN_WORDS'], $TH['W10_W_WAVE_RULING'])) {
        return array();                    /* ⛔ بلا سجلِّ أوزانٍ لا تخمين */
    }
    $minWords = $TH['W10_MIN_TOKEN_WORDS']['v'];
    $rows = array();

    /* المصدرُ الأوّل — سطحُ المتطلَّبِ في وحدةٍ من شِقَّي الوحدةِ المشقوقة */
    $unitSide = repair01_w10_unit_side_map($conn);
    $esc = array();
    foreach (array_keys($unitSide) as $u) { $esc[] = "'" . $conn->real_escape_string($u) . "'"; }
    if ($esc) {
        $r = $conn->query("SELECT requirement_id, unit, surface FROM repair01_requirements
                            WHERE unit IN (" . implode(',', $esc) . ")");
        while ($r && $x = $r->fetch_assoc()) {
            $side = $unitSide[$x['unit']];
            foreach (repair01_w10_phrase_tokens((string) $x['surface'], $minWords) as $tok) {
                $rows[] = array('token' => $tok['raw'], 'norm' => $tok['norm'], 'wc' => $tok['wc'],
                                'side' => $side, 'weight' => $TH['W10_W_REQUIREMENT_SURFACE']['v'] + $tok['wc'] * 10 + mb_strlen($tok['norm']),
                                'kind' => 'REQUIREMENT_SURFACE', 'ref' => $x['requirement_id']);
            }
        }
    }

    /* المصدرُ الثاني — بندُ قاعدةِ الشقِّ في الجسر */
    $r = $conn->query("SELECT legacy_name, canonical_code, split_rule FROM repair01_dept_crosswalk
                        WHERE verdict = 'SPLIT' AND split_rule <> ''");
    while ($r && $x = $r->fetch_assoc()) {
        foreach (preg_split('/\s*·\s*/u', (string) $x['split_rule']) as $clause) {
            $clause = trim($clause);
            if ($clause === '') { continue; }
            foreach (repair01_w10_phrase_tokens($clause, $minWords) as $tok) {
                $rows[] = array('token' => $tok['raw'], 'norm' => $tok['norm'], 'wc' => $tok['wc'],
                                'side' => $x['canonical_code'],
                                'weight' => $TH['W10_W_CROSSWALK_CLAUSE']['v'] + $tok['wc'] * 10 + mb_strlen($tok['norm']),
                                'kind' => 'CROSSWALK_CLAUSE', 'ref' => 'crosswalk · ' . $x['legacy_name']);
            }
        }
    }

    /* ── المصدرُ الثالث — حكمُ الموجةِ نصًّا حيث لا يحمل المخزنُ مفردتَه ────
       ◆ **ولماذا يُكتب حكمٌ أصلًا**: الجسرُ نفسُه يقول «لا يُحسم الصفُّ آليًّا».
         والاشتقاقُ يبلغ ما تبلغه المفردةُ المسجَّلة — وما بقي **يُحكَم فيه صراحةً
         ويُقيَّد بالبندِ الذي يحقّقه**، لا يُترك لقاعدةٍ أمٍّ تبتلعه صامتًا.
       ◆ **وكلُّ حكمٍ هنا يحمل البندَ الذي يحقّقه** — فهو ترجمةُ نصِّ قرارٍ قائم
         لا رأيٌ جديد. ووزنُه دونَ بندِ الجسرِ وسطحِ المتطلَّبِ فلا يغلبهما. */
    $rulings = repair01_w10_wave_rulings();
    foreach ($rulings as $ru) {
        $tokNorm = repair01_w10_norm($ru['token']);
        $wc = count(repair01_w10_words($tokNorm));
        if ($wc < $minWords) { continue; }
        $rows[] = array('token' => $ru['token'], 'norm' => $tokNorm, 'wc' => $wc, 'side' => $ru['side'],
                        'weight' => $TH['W10_W_WAVE_RULING']['v'] + $wc * 10 + mb_strlen($tokNorm),
                        'kind' => 'W10_RULING', 'ref' => $ru['ref']);
    }

    /* المصدرُ الرابع — اسمُ الإدارةِ المعياريّ */
    $sides = array();
    foreach ($unitSide as $code) { $sides[$code] = true; }
    foreach (array_keys($sides) as $code) {
        $nm = (string) repair01_w10_one($conn, "SELECT name_ar FROM repair01_departments
                                                 WHERE canonical_code = '" . $conn->real_escape_string($code) . "'");
        if ($nm === '') { continue; }
        foreach (repair01_w10_phrase_tokens($nm, $minWords) as $tok) {
            $rows[] = array('token' => $tok['raw'], 'norm' => $tok['norm'], 'wc' => $tok['wc'],
                            'side' => $code,
                            'weight' => $TH['W10_W_DEPARTMENT_NAME']['v'] + $tok['wc'] * 10 + mb_strlen($tok['norm']),
                            'kind' => 'DEPARTMENT_NAME', 'ref' => 'repair01_departments · ' . $code);
        }
    }
    return $rows;
}

/**
 * أحكامُ الموجةِ نصًّا — **كلُّ حكمٍ مقيَّدٌ بالبندِ الذي يحقّقه**.
 * ⛔ ولا يُضاف هنا حكمٌ بلا بندٍ قائمٍ في الجسرِ أو المتطلَّبات، ولا يُستعمَل هذا
 *   المصدرُ لتجاوزِ مطابقةٍ مشتقّة — وزنُه دونَ الاثنَين.
 */
function repair01_w10_wave_rulings()
{
    return array(
        array('token' => 'التدفق النقدي', 'side' => 'DEP-06',
              'ref' => 'W10-D-02 ⇐ بند «توقّع السيولة» · TRS-04 خطة السيولة والتدفق'),
    );
}

/**
 * وحدةُ المتطلَّبِ ⇐ الشقُّ الذي تخصُّه.
 * **مشتقٌّ من الرقمِ المصدَّرِ في اسمِ الوحدةِ** (‏`05 …` ⇒ `DEP-05`) ومن مساحتَي
 * الرئيسِ والنوّابِ (‏`E1` و`E2`) — لا من خريطةٍ مكتوبةٍ في الشيفرة.
 */
function repair01_w10_unit_side_map(mysqli $conn)
{
    $out = array();
    $splitCodes = array();
    $r = $conn->query("SELECT canonical_code FROM repair01_dept_crosswalk WHERE verdict = 'SPLIT'");
    while ($r && $x = $r->fetch_row()) { $splitCodes[$x[0]] = true; }

    $r = $conn->query("SELECT DISTINCT unit FROM repair01_requirements");
    while ($r && $x = $r->fetch_row()) {
        $u = (string) $x[0];
        if (preg_match('/^(\d{2})\s/u', $u, $m)) {
            $code = 'DEP-' . $m[1];
            if (isset($splitCodes[$code])) { $out[$u] = $code; }
            continue;
        }
        if (preg_match('/^E1\s/u', $u) && isset($splitCodes['EX-CEO'])) { $out[$u] = 'EX-CEO'; }
        if (preg_match('/^E2\s/u', $u) && isset($splitCodes['EX-DVP'])) { $out[$u] = 'EX-DVP'; }
    }
    return $out;
}

/**
 * عباراتٌ مرشَّحةٌ من نصٍّ واحد: العبارةُ كاملةً ثمَّ كلُّ نافذةٍ متّصلةٍ طولُها
 * `>= $minWords`. والطويلُ يغلب القصيرَ بالوزنِ لا بترتيبِ الإدراج.
 */
function repair01_w10_phrase_tokens($text, $minWords)
{
    $norm = repair01_w10_norm($text);
    $words = repair01_w10_words($norm);
    $n = count($words);
    $out = array(); $seen = array();
    if ($n < $minWords) { return $out; }
    for ($len = $n; $len >= $minWords; $len--) {
        for ($i = 0; $i + $len <= $n; $i++) {
            $slice = array_slice($words, $i, $len);
            $tk = implode(' ', $slice);
            if (isset($seen[$tk])) { continue; }
            $seen[$tk] = true;
            $out[] = array('raw' => $tk, 'norm' => $tk, 'wc' => $len);
        }
    }
    return $out;
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ نصُّ المطابقةِ لسطحٍ — اسمُه هو ومسمّاه المعياريُّ وجذعُ ملفِّه
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_match_text(array $row)
{
    $parts = array((string) ($row['title'] ?? ''), (string) ($row['canonical_ar'] ?? ''),
                   (string) ($row['gap_name'] ?? ''));
    $file = (string) ($row['screen_file'] ?? '');
    $stem = preg_replace('/\.php$/i', '', basename($file));
    $parts[] = str_replace(array('_', '-'), ' ', $stem);
    return repair01_w10_norm(implode(' ', array_filter($parts)));
}

/**
 * يصنّف نصًّا على الشِّقَّين.
 * يُعيد `array(side, rule, why, anchor, token, weight)` — و`side=''` حين لا مطابقة
 * أو حين تتساوى كفّتا الشقَّين، **فالتساوي لا يُحسَم بترتيبٍ داخليّ**.
 */
function repair01_w10_classify($text, array $vocab, array $sidesOfUnit)
{
    $best = array();
    foreach ($vocab as $v) {
        if (!isset($sidesOfUnit[$v['side']])) { continue; }
        if (mb_strpos($text, $v['norm']) === false) { continue; }
        $s = $v['side'];
        if (!isset($best[$s]) || $v['weight'] > $best[$s]['weight']) { $best[$s] = $v; }
    }
    if (!$best) { return array('side' => '', 'rule' => '', 'why' => '', 'anchor' => '', 'token' => '', 'weight' => 0); }
    uasort($best, function ($a, $b) { return $b['weight'] - $a['weight']; });
    $keys = array_keys($best);
    $top = $best[$keys[0]];
    if (count($keys) > 1 && $best[$keys[1]]['weight'] === $top['weight']) {
        return array('side' => '', 'rule' => 'TIE', 'why' => 'كفّتا الشقَّينِ متساويتان — لا يُحسَم بترتيبٍ داخليّ',
                     'anchor' => '', 'token' => $top['norm'], 'weight' => $top['weight']);
    }
    $ruleByKind = array('REQUIREMENT_SURFACE' => 'W10_REQ_SURFACE_MATCH',
                        'CROSSWALK_CLAUSE'    => 'W10_SPLIT_RULE_CLAUSE',
                        'W10_RULING'          => 'W10_WAVE_RULING',
                        'DEPARTMENT_NAME'     => 'W10_DEPT_NAME_MATCH');
    $whyByKind = array(
        'REQUIREMENT_SURFACE' => 'سطحُ المتطلَّبِ في وحدةِ الشقِّ يطابق اسمَ السطح',
        'CROSSWALK_CLAUSE'    => 'بندٌ من قاعدةِ الشقِّ المسجَّلةِ في الجسرِ يطابق اسمَ السطح',
        'W10_RULING'          => 'حكمُ الموجةِ نصًّا مقيَّدًا بالبندِ الذي يحقّقه — حيث لا يحمل المخزنُ مفردتَه',
        'DEPARTMENT_NAME'     => 'اسمُ الإدارةِ المعياريُّ يطابق اسمَ السطح',
    );
    return array('side' => $keys[0], 'rule' => $ruleByKind[$top['kind']],
                 'why' => $whyByKind[$top['kind']] . ' ⇐ «' . $top['token'] . '»',
                 'anchor' => $top['ref'], 'token' => $top['norm'], 'weight' => $top['weight']);
}

/** أهو سطحُ رقابةٍ على مخرَجِ الشقِّ الآخر؟ — الصيغةُ لا القائمة */
function repair01_w10_is_review_surface($title)
{
    $n = repair01_w10_norm($title);
    return (mb_strpos($n, 'مراجعه ') === 0 || mb_strpos($n, 'رقابه ') === 0);
}

/* ══════════════════════════════════════════════════════════════════════════
   ③-ب جذعُ الملفِّ وعائلتُه — الانتشارُ من المُرسى إلى التوأمِ لا قائمةُ ملفّات
   ══════════════════════════════════════════════════════════════════════════
   ◆ **لماذا الانتشارُ لا الاجتهاد**: `Finance/payments_fin.php` و`payments.php`
     ملفّانِ لسطحٍ واحد — الأوّلُ نسخةُ المجلَّدِ الماليِّ والثاني جذرُه. ومطابقةُ
     المفرداتِ تُرسي أحدَهما ولا تُرسي الآخرَ (‏الاسمُ المُصيَّرُ مختلف)، فيصير
     التوأمانِ في شقَّين. **واللاحقةُ `_fin` والبادئةُ `tre_` عائلتانِ في تسميةِ
     هذا المستودع** — لا اجتهادَ فيهما، وتُقاسان من أسماءِ الملفّاتِ نفسِها.
   ◆ **والعائلةُ لا تنتشر إلّا بإجماع**: بادئةٌ نصفُ أسطحِها هنا ونصفُها هناك
     **لا تحسم** — فالانتشارُ يقف ويسقط الصفُّ إلى القاعدةِ الأمّ مُعلَنًا بعددِه.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_stem($screenFileOrRoute)
{
    $base = basename(str_replace('\\', '/', (string) $screenFileOrRoute));
    $base = preg_replace('/\.php$/i', '', $base);
    return mb_strtolower($base, 'UTF-8');
}

/**
 * هويّةُ السطحِ **اسمُ ملفِّه في الدراسةِ لا مسارُه المحلول**.
 * ⚠ **ولماذا**: W02 حلَّت المسارَ بمطابقةِ الاسمِ الأساسيِّ على القرص، فأصابت
 *   `payments.php` بملفِّ مكتبةِ PhpSpreadsheet الذي يحمل الاسمَ نفسَه. والهويّةُ
 *   المأخوذةُ من المسارِ المحلولِ تجعل جذعَ سطحِ الخزينةِ جذعَ صنفٍ في مكتبةِ
 *   جداولَ — فينكسر انتشارُ التوأمِ والعائلة. (‏`W10-D-05`)
 */
function repair01_w10_identity_stem(array $row)
{
    $f = (string) ($row['screen_file'] ?? '');
    if ($f !== '') { return repair01_w10_stem($f); }
    return repair01_w10_stem((string) ($row['route'] ?? ''));
}

/** جذعٌ بعد نزعِ لاحقةِ نسخةِ المجلَّد — «توأمُ الملفّ» */
function repair01_w10_twin_stem($stem)
{
    $t = preg_replace('/_(fin|proc|tre)$/', '', (string) $stem);
    return $t;
}

/** بادئةُ العائلةِ — ما قبلَ أوّلِ شرطةٍ سفليّةٍ حين تكون قصيرة */
function repair01_w10_family_prefix($stem)
{
    $p = strpos((string) $stem, '_');
    if ($p === false || $p < 2 || $p > 5) { return ''; }
    return substr((string) $stem, 0, $p);
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ حارسُ العرضِ — يُقاس من الملفِّ لا يُصدَّق من السجلّ
   ══════════════════════════════════════════════════════════════════════════ */
/**
 * ⚠ **والحارسُ قد يكون في المُضمَّنِ لا في الملفّ**: قشرةُ الصفحةِ تحمله، وكاشفٌ
 *   يقرأ الملفَّ وحدَه يعُدُّ ثمانيةً وأربعينَ سطحًا محروسًا «بلا حارس». فالكشفُ
 *   يتبع سلسلةَ التضمينِ **مستويَين** — وهو ما يفعله كاشفُ W02 بعمقٍ أكبر.
 */
function repair01_w10_guard_of($ROOT, $route, $depth = 0)
{
    static $seen = array();
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);
    if (strpos($src, 'check_page_permissions') !== false
        || strpos($src, 'enforce_current_page_view_permission') !== false
        || strpos($src, 'ems_require_governance_screen') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'حارس صلاحية في الملف نفسه');
    }
    if (strpos($src, 'ems_gov_flash_redirect') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'رد الحوكمة في الملف');
    }
    if ($depth < 2) {
        if (preg_match_all('~(?:include|require)(?:_once)?\s*[\( ]\s*[^;]{0,200}?[\'"]([^\'"]+\.php)[\'"]~i',
                           $src, $mm)) {
            foreach ($mm[1] as $inc) {
                $rel = ltrim(str_replace('\\', '/', $inc), './');
                $cand = array($rel, 'includes/' . basename($rel), dirname($route) . '/' . $inc);
                foreach ($cand as $c) {
                    $c = preg_replace('~[^/]+/\.\./~', '', str_replace('\\', '/', $c));
                    if ($c === '' || isset($seen[$c]) || !is_file($ROOT . '/' . $c)) { continue; }
                    $seen[$c] = 1;
                    $g = repair01_w10_guard_of($ROOT, $c, $depth + 1);
                    unset($seen[$c]);
                    if ($g['kind'] !== 'NONE') {
                        return array('kind' => 'SHELL', 'evidence' => 'حارس موروث من ' . $c);
                    }
                }
            }
        }
    }
    if (strpos($src, "\$_SESSION['user']") !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'فحص الجلسة في الملف');
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس');
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ مقامُ الشقِّ — يُعاد بناؤه في كلِّ تشغيلٍ من السجلَّين معًا
   ══════════════════════════════════════════════════════════════════════════
   **السجلّان مقامانِ مختلفان ولا يُدمَجان في رقم**: `repair01_surfaces` دفترُ
   الدراسةِ (‏سطحٌ في ورقةِ إكسل)، و`repair01_screen_registry` سجلُّ الشاشاتِ
   المشتقُّ من القرصِ والملاحة. وسطحٌ في الأوّلِ قد لا يكون في الثاني والعكس —
   فالشقُّ يجب أن يشمل الاثنين وإلّا بقي نصفُ النظامِ على الحكمِ القديم.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_scope_rows(mysqli $conn)
{
    $units = repair01_w10_split_units($conn);
    $unitNames = array_keys($units);
    if (!$unitNames) { return array(); }
    $in = array();
    foreach ($unitNames as $u) { $in[] = "'" . $conn->real_escape_string($u) . "'"; }
    $inSql = implode(',', $in);

    $rows = array();

    /* أ) دفترُ الأسطح */
    $r = $conn->query("SELECT s.screen_id, s.screen_file, s.disk_path, s.on_disk, s.screen_title,
                              s.dept_legacy, s.canonical_code, s.canon_rule
                         FROM repair01_surfaces s
                        WHERE s.dept_legacy IN ($inSql) AND s.screen_id <> ''");
    while ($r && $x = $r->fetch_assoc()) {
        $k = (string) $x['screen_id'];
        if (!isset($rows[$k])) {
            $rows[$k] = array('scope_key' => $k, 'route' => '', 'screen_file' => (string) $x['screen_file'],
                              'title' => '', 'legacy_unit' => (string) $x['dept_legacy'],
                              'in_surfaces' => 0, 'in_registry' => 0, 'surf_code_before' => '',
                              'reg_code_before' => '', 'canon_rule' => '', 'owner_rule' => '',
                              'canonical_ar' => '', 'gap_name' => '');
        }
        $rows[$k]['in_surfaces'] = 1;
        $rows[$k]['surf_code_before'] = (string) $x['canonical_code'];
        $rows[$k]['canon_rule'] = (string) $x['canon_rule'];
        if ($rows[$k]['title'] === '') { $rows[$k]['title'] = (string) $x['screen_title']; }
        if ((int) $x['on_disk'] === 1 && $rows[$k]['route'] === '') { $rows[$k]['route'] = (string) $x['disk_path']; }
    }

    /* ب) سجلُّ الشاشاتِ — بمالكٍ اسمُه الوحدةُ الأمُّ باسمِها القديم */
    $r = $conn->query("SELECT screen_id, screen_file, route, owner_code, owner_role, owner_rule
                         FROM repair01_screen_registry WHERE owner_role IN ($inSql)");
    while ($r && $x = $r->fetch_assoc()) {
        $k = (string) $x['screen_id'];
        if (!isset($rows[$k])) {
            $rows[$k] = array('scope_key' => $k, 'route' => (string) $x['route'],
                              'screen_file' => (string) $x['screen_file'], 'title' => '',
                              'legacy_unit' => (string) $x['owner_role'], 'in_surfaces' => 0,
                              'in_registry' => 0, 'surf_code_before' => '', 'reg_code_before' => '',
                              'canon_rule' => '', 'owner_rule' => '', 'canonical_ar' => '', 'gap_name' => '');
        }
        $rows[$k]['in_registry'] = 1;
        $rows[$k]['reg_code_before'] = (string) $x['owner_code'];
        $rows[$k]['owner_rule'] = (string) $x['owner_rule'];
        if ($rows[$k]['route'] === '') { $rows[$k]['route'] = (string) $x['route']; }
        if ($rows[$k]['screen_file'] === '') { $rows[$k]['screen_file'] = (string) $x['screen_file']; }
    }

    /* ج) عددُ الوحداتِ التي يظهر السطحُ تحتها — **الشاشةُ العابرةُ ليست ملكَ الشقّ** */
    $unitsOf = array();
    $r = $conn->query("SELECT screen_id, dept_legacy FROM repair01_surfaces WHERE screen_id <> ''");
    while ($r && $x = $r->fetch_assoc()) { $unitsOf[(string) $x['screen_id']][(string) $x['dept_legacy']] = true; }
    foreach ($rows as $k => $v) {
        $all = isset($unitsOf[$k]) ? array_keys($unitsOf[$k]) : array();
        $outside = array();
        foreach ($all as $u) { if (!isset($units[$u])) { $outside[] = $u; } }
        $rows[$k]['units_all'] = $all;
        $rows[$k]['units_outside'] = $outside;
    }

    /* د) إثراءٌ بالمسمّى المعياريِّ الحيّ · ووسمُ المسارِ المشتبَهِ به */
    foreach ($rows as $k => $v) {
        $rows[$k]['disk_suspect'] = (preg_match('~(^|[\\\\/])vendor[\\\\/]~i', (string) $v['route']) ? 1 : 0);
        if ($v['route'] === '') { continue; }
        $ca = repair01_w10_one($conn, "SELECT canonical_ar FROM nav_canonical
                                        WHERE route = '" . $conn->real_escape_string($v['route']) . "' LIMIT 1");
        if ($ca !== null) { $rows[$k]['canonical_ar'] = (string) $ca; }
    }
    ksort($rows);
    return $rows;
}

/**
 * المؤشِّراتُ الحيّةُ التي تسمّي وحدةً مشقوقةً باسمِها القديم.
 * **تُقاس من القاعدةِ لا تُعلَن**: كلُّ جدولٍ حيٍّ فيه عمودٌ يحمل اسمَ إدارةٍ.
 */
function repair01_w10_pointer_sources()
{
    return array(
        array('table' => 'nav_canonical',   'col' => 'owner_dept', 'key' => 'route',
              'why'  => 'السجلُّ المعياريُّ للملاحةِ يسمّي الإدارةَ المالكةَ نصًّا — وهو مصدرُ اشتقاقِ المالكِ في W02'),
        array('table' => 'nav09_file_map',  'col' => 'owner_dept', 'key' => 'real_path',
              'why'  => 'خريطةُ الملفّاتِ تُقرأ حيّةً في عقدِ الشاشةِ فتعرض اسمَ الإدارةِ المالكة'),
        array('table' => 'request_types',   'col' => 'owner_dept', 'key' => 'code',
              'why'  => 'قاموسُ الطلباتِ يوجّه الطلبَ إلى إدارةٍ باسمِها — والشقُّ يغيّر مَن يستلم'),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ الحلُّ الكامل — يُعاد اشتقاقُه في كلِّ تشغيلٍ ولا يُقرأ من دفترٍ خُزِّن
   ══════════════════════════════════════════════════════════════════════════
   ترتيبُ القواعدِ مقفلٌ ومُعلَنٌ في `W10-D-01`:
   ① وسمُ W01 «يخدم الشقَّين» يُحفَظ ولا يُدهَس · ② سطحُ المتطلَّبِ ·
   ③ بندُ قاعدةِ الشقِّ · ④ اسمُ الإدارةِ · ⑤ الرقابةُ تبقى مع المحاسبة ·
   ⑥ توأمُ الملفِّ · ⑦ عائلةُ البادئةِ بإجماعٍ · ⑧ القاعدةُ الأمُّ مُعلَنةً بعددِها.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w10_resolve_all(mysqli $conn)
{
    $units  = repair01_w10_split_units($conn);
    $rows   = repair01_w10_scope_rows($conn);

    /* المفرداتُ من السجلِّ المكتوبِ لا من اشتقاقٍ طائر — والبوّابةُ تقارنهما */
    $vocab = array();
    $r = $conn->query("SELECT token, token_norm, word_count, side, weight, src_kind, src_ref
                         FROM repair01_w10_vocab");
    while ($r && $x = $r->fetch_assoc()) {
        $vocab[] = array('token' => (string) $x['token'], 'norm' => (string) $x['token_norm'],
                         'wc' => (int) $x['word_count'], 'side' => (string) $x['side'],
                         'weight' => (int) $x['weight'], 'kind' => (string) $x['src_kind'],
                         'ref' => (string) $x['src_ref']);
    }

    /* أسماءُ الفجواتِ المستهدفةِ تُثري نصَّ المطابقةِ للأسطحِ غيرِ المبنيّة */
    $gapByStem = array();
    $r = $conn->query("SELECT unit, surface_name FROM repair01_target_gaps");
    while ($r && $x = $r->fetch_assoc()) {
        if (preg_match('/\(([^)]+\.php)\)\s*$/u', (string) $x['surface_name'], $m)) {
            $gapByStem[repair01_w10_stem($m[1])] = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $x['surface_name']));
        }
    }

    $out = array();
    foreach ($rows as $k => $v) {
        $sides = array();
        foreach ($units[$v['legacy_unit']] as $code => $_) { $sides[$code] = true; }
        $parent = array_keys($sides); sort($parent); $parent = $parent[0];   /* الشقُّ الأمُّ = الأصغرُ رمزًا */
        $stem = repair01_w10_identity_stem($v);
        if (isset($gapByStem[$stem]) && $v['gap_name'] === '') { $v['gap_name'] = $gapByStem[$stem]; }
        $v['stem'] = $stem;
        $v['sides'] = $sides;
        $v['parent'] = $parent;
        $v['text'] = repair01_w10_match_text($v);
        $out[$k] = $v;
    }

    /* ── ⓪ الشاشةُ العابرةُ للوحداتِ ليست ملكَ هذا الشقّ ─────────────────
       سطحٌ تسمّيه ستَّ عشرةَ وحدةً (‏«مهامّي» · «محادثاتي») ليس ملكَ الوحدةِ
       المشقوقةِ حتّى يشقَّه شقُّها. **وتغييرُ مالكِه يغيّر مَن يراه في ستَّ عشرةَ
       إدارة** — وهو قرارُ حوكمةٍ خارجَ ولايةِ هذه المرحلة. فيبقى كما هو، مُعلَنًا
       بعددِه في `W10-D-04` لا صامتًا. */
    foreach ($out as $k => $v) {
        if (count($v['units_all']) < 2) { continue; }
        $keep = $v['in_surfaces'] === 1 ? $v['surf_code_before'] : $v['reg_code_before'];
        if ($keep === '') { $keep = $v['parent']; }
        $out[$k] = repair01_w10_set($v, $keep, 'W10_CROSS_UNIT_KEPT',
            'سطحٌ تسمّيه ' . count($v['units_all']) . ' وحدةً — ليس ملكَ الوحدةِ المشقوقةِ وحدَها، وتغييرُ مالكِه يتجاوز ولايةَ الشقّ',
            'repair01_surfaces · وحداتٌ خارجَ الشقِّ ' . count($v['units_outside']), '', 1);
    }

    /* ── ① وسمُ W01 «يخدم الشقَّين» — قرارٌ سابقٌ مُرسًى في دفترِ الأسطح ──── */
    foreach ($out as $k => $v) {
        if (isset($v['resolved_code'])) { continue; }
        if ($v['canon_rule'] === 'SPLIT_FIN_SHARED') {
            $out[$k] = repair01_w10_set($v, $v['parent'], 'W10_W01_SHARED_KEPT',
                'سطحٌ يخدم الشقَّينِ معًا وسمَته W01 مشتركًا — يبقى مع الشقِّ الأمِّ ويُعلَن اشتراكُه ولا يُخفى',
                'repair01_surfaces.canon_rule = SPLIT_FIN_SHARED', '', 1);
        }
    }

    /* ── ②③④ المطابقةُ على المفردات ──────────────────────────────────── */
    foreach ($out as $k => $v) {
        if (isset($v['resolved_code'])) { continue; }
        $c = repair01_w10_classify($v['text'], $vocab, $v['sides']);
        if ($c['side'] === '') { continue; }
        $out[$k] = repair01_w10_set($v, $c['side'], $c['rule'], $c['why'], $c['anchor'], $c['token'], 0);
    }

    /* ── ⑤ الرقابةُ على مخرَجِ الخزينةِ تبقى مع المحاسبة ───────────────── */
    foreach ($out as $k => $v) {
        if (!isset($v['resolved_code'])) { continue; }
        if ($v['resolved_code'] === $v['parent']) { continue; }
        if ($v['legacy_unit'] !== 'المالية والخزينة') { continue; }
        if (!repair01_w10_is_review_surface($v['title'] !== '' ? $v['title'] : $v['canonical_ar'])) { continue; }
        $out[$k]['resolved_code'] = $v['parent'];
        $out[$k]['split_rule']    = 'W10_REVIEW_STAYS_ACCOUNTING';
        $out[$k]['split_why']     = 'سطحُ رقابةٍ على مخرَجِ الشقِّ المنفِّذ — وإسنادُه إليه يجعل المنفِّذَ مراجعَ نفسِه';
    }

    /* ── ⑥ توأمُ الملفِّ — نسخةُ المجلَّدِ ترث حكمَ جذرِها ───────────────── */
    $byStem = array();
    foreach ($out as $k => $v) { if (isset($v['resolved_code'])) { $byStem[$v['stem']][] = $v['resolved_code']; } }
    foreach ($out as $k => $v) {
        if (isset($v['resolved_code'])) { continue; }
        $tw = repair01_w10_twin_stem($v['stem']);
        if ($tw === $v['stem'] || !isset($byStem[$tw])) { continue; }
        $set = array_unique($byStem[$tw]);
        if (count($set) !== 1) { continue; }
        $side = $set[array_key_first($set)];
        if (!isset($v['sides'][$side])) { continue; }
        $out[$k] = repair01_w10_set($v, $side, 'W10_STEM_TWIN',
            'نسخةُ المجلَّدِ لسطحٍ جذرُه مُرسًى — والتوأمانِ لا يفترقانِ في شقَّين', 'stem:' . $tw, $tw, 0);
    }

    /* ── ⑦ عائلةُ البادئةِ — بإجماعٍ وحدَه ─────────────────────────────── */
    $byFam = array();
    foreach ($out as $k => $v) {
        if (!isset($v['resolved_code'])) { continue; }
        $f = repair01_w10_family_prefix($v['stem']);
        if ($f === '') { continue; }
        $byFam[$f][$v['resolved_code']] = true;
    }
    foreach ($out as $k => $v) {
        if (isset($v['resolved_code'])) { continue; }
        $f = repair01_w10_family_prefix($v['stem']);
        if ($f === '' || !isset($byFam[$f]) || count($byFam[$f]) !== 1) { continue; }
        $side = array_key_first($byFam[$f]);
        if (!isset($v['sides'][$side])) { continue; }
        $out[$k] = repair01_w10_set($v, $side, 'W10_STEM_FAMILY',
            'عائلةُ بادئةِ الملفِّ مُرساةٌ كلُّها في شقٍّ واحدٍ بإجماعٍ — والصفُّ يرثها', 'family:' . $f, $f, 0);
    }

    /* ── ⑧ القاعدةُ الأمُّ — «وما عداه في هذه الوحدة» نصَّ الجسرِ حرفًا ──── */
    foreach ($out as $k => $v) {
        if (isset($v['resolved_code'])) { continue; }
        $out[$k] = repair01_w10_set($v, $v['parent'], 'W10_PARENT_DEFAULT',
            'لم يطابق مفردةً ولا وَرِث توأمًا ولا عائلةً — فيبقى مع الشقِّ الأمِّ بنصِّ الجسر، مُعلَنًا بعددِه لا صامتًا',
            'crosswalk · ' . $v['legacy_unit'], '', 0);
    }

    /* ── ⑨ حاجزُ الرئاسة: النوّابُ مستهدَفٌ لا مبنيّ (‏W1-10 مُغلَق) ────── */
    foreach ($out as $k => $v) {
        if ($v['resolved_code'] !== 'EX-DVP') { continue; }
        $out[$k]['resolved_code'] = 'EX-CEO';
        $out[$k]['split_rule']    = 'W10_DVP_IS_TARGET_ONLY';
        $out[$k]['split_why']     = 'أسطحُ النوّابِ كلُّها مستهدَفةٌ في دفترِ الفجواتِ بلا مقابلٍ مبنيّ — فالمبنيُّ للرئيس، والشقُّ يقع في دفترِ الفجواتِ لا في دفترِ المبنيّ';
        $out[$k]['anchor_ref']    = 'W1-10 · repair01_target_gaps';
    }
    return $out;
}

/** يضع حكمَ صفٍّ بحقولِه كاملةً */
function repair01_w10_set(array $v, $code, $rule, $why, $anchor, $token, $shared)
{
    $v['resolved_code'] = $code;
    $v['split_rule']    = $rule;
    $v['split_why']     = $why;
    $v['anchor_ref']    = (string) $anchor;
    $v['matched_token'] = (string) $token;
    $v['serves_both']   = (int) $shared;
    return $v;
}

/** كياناتُ المرحلةِ التي يلزمها آلةُ حالة */
function repair01_w10_entity_types()
{
    return array('SURFACE_OWNERSHIP', 'DEPT_SPLIT', 'LEGACY_POINTER', 'SIDEBAR_ITEM', 'AUDIT_REFERENCE');
}

/** أحداثُ النطاقِ — لكلٍّ عقدُ أثرٍ في `repair01_events` وناشرٌ مُثبَتٌ من القرص */
function repair01_w10_stage_events()
{
    return array('dept.split.applied', 'surface.owner.reassigned',
                 'legacy.pointer.translated', 'sidebar.item.replaced',
                 'split.conflict.detected');
}

/**
 * الأسطحُ التي كان شقُّها محسومًا **بترتيبِ الصفوفِ لا بمعناه**.
 * تُقاس من السجلِّ حيًّا: مالكٌ اسمُه وحدةٌ مشقوقةٌ وقاعدتُه اشتقاقٌ من الملاحة —
 * والجسرُ يعطي مفتاحًا مكرَّرًا فيغلب آخرُ صفٍّ فيه.
 */
function repair01_w10_arbitrary_rows(mysqli $conn)
{
    $units = array_keys(repair01_w10_split_units($conn));
    if (!$units) { return array(); }
    $in = array();
    foreach ($units as $u) { $in[] = "'" . $conn->real_escape_string($u) . "'"; }
    $out = array();
    $r = $conn->query("SELECT screen_id, route, owner_code, owner_rule FROM repair01_screen_registry
                        WHERE owner_role IN (" . implode(',', $in) . ")
                          AND (owner_rule = 'NAV_CANONICAL_OWNER' OR owner_rule = 'NAV_SOLE_ROLE_SPACE'
                               OR owner_rule LIKE 'PARENT_INHERIT:%')");
    while ($r && $x = $r->fetch_assoc()) { $out[(string) $x['screen_id']] = $x; }
    return $out;
}

/**
 * أثرُ العطبِ في مولِّدِه — **الكاشفُ يرسو على شكلِ الشيفرةِ لا على عبارةٍ**.
 * يُعيد `true` حين يبني W02 خريطةَ الجسرِ بمفتاحِ الاسمِ **بلا استثناءِ المشقوق**،
 * وهي الصيغةُ التي تجعل آخرَ صفٍّ يدهس أوّلَه.
 */
function repair01_w10_generator_defect($ROOT)
{
    $f = $ROOT . '/tools/repair01_w2_apply.php';
    if (!is_file($f)) { return array('defect' => true, 'why' => 'مولِّدُ W02 غيرُ موجود'); }
    $src = (string) file_get_contents($f);
    /* الصيغةُ المعطوبة: إسنادٌ إلى `$cross[...]` داخلَ حلقةٍ تقرأ الجسرَ كلَّه */
    /* ⚠ **والعلامةُ الحاسمةُ هي الإسنادُ نفسُه لا وجودُ حارسٍ في مكانٍ آخرَ من
         الملفّ**: ملفٌّ يذكر `repair01_w10_split` في سطرٍ ويكتب الخريطةَ العمياءَ
         في سطرٍ آخرَ **معطوبٌ** — والحاجبُ الذي يقبل الذكرَ عذرًا أعمى. */
    $hasBlindMap = (bool) preg_match('~\$cross\[\s*trim\(\$x\[.legacy_name.\]\)\s*\]\s*=~', $src);
    $guardsSplit = (strpos($src, "\$x['verdict'] === 'SPLIT'") !== false)
                && (strpos($src, 'repair01_w10_split') !== false);
    return array('defect' => $hasBlindMap,
                 'blind_map' => $hasBlindMap, 'guards_split' => $guardsSplit,
                 'why' => $hasBlindMap
                     ? 'خريطةُ الجسرِ بمفتاحِ الاسمِ — والمفتاحُ مكرَّرٌ للمشقوقِ فآخرُ صفٍّ يدهس أوّلَه'
                     : 'الإسنادُ بمفتاحِ الاسمِ منزوعٌ — والمشقوقُ يُحَلُّ بمسارِه من دفترِ الشقّ');
}
