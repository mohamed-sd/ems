<?php
/**
 * tools/amd01_impact_bridge.php — `AMD-01` م٤ · جسرُ مفرداتِ الأثرِ إلى المعرِّفات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — م٤ تشترط إسقاطَ كلِّ قرارٍ على **كلِّ ما يتأثّر به**،
 *   والمقيسُ **صفرٌ من ١٢٥** و**٩٥٥ خليّةً محجوبة**. والسببُ عمودٌ واحد:
 *   `affected_screens` **مفرداتٌ حرّة**: «م03-2» · «كل الأسطح» · «شهادة العودة».
 *
 * ◆ **واللغزُ حُلَّ نصفَ حلٍّ — ويُقال نصفًا لا كاملًا**:
 *   · **البادئةُ الحرفيّةُ تدلُّ على الإدارةِ دلالةً قاطعة**، ومصدرُها
 *     **مقيسٌ لا مُخترَع**: ورقةُ `C_كتالوج_الأحداث` في «٠٣ · الدستور.xlsx»
 *     تحمل عمودَي «الإدارة المصدر» و«الشاشة المصدر» جنبًا إلى جنب، فمنها
 *     يُنتزع الحرفُ ⇐ الإدارة. ⛔ **ولا يُشتقّ الحرفُ من أوّلِ اسمِ الإدارة**
 *     — فـ«م» تصلح للمالية وللمبيعات وللمخازن وللمخاطر وللموقع وللموردين،
 *     والاشتقاقُ بالحدسِ يُنتج جسرًا يبدو صحيحًا ويصيب غيرَ مقصودِه.
 *   · ⛔ **والرقمُ لا يدلُّ على الشاشة**: جُرِّب على ثمانيةِ رموزٍ معروفةِ
 *     المقصدِ من كتالوجِ الأحداثِ نفسِه (`م10` الإقفالُ الشهريّ · `ق09` إقفالُ
 *     يومِ الموقع · `ش07` أمرُ الشراء …)، فوافق **اثنان** وانحرف **ستّة**:
 *     ترقيمُ الدليلِ التصميميِّ وترقيمُ `seq` في سجلِّ المتطلبات **كونان
 *     مختلفان**. ⇒ فالرمزُ يُحلّ إلى **نطاقٍ** ولا يُحلّ إلى **سطح**، ويُقال.
 *
 * ◆ **أربعُ قواعدَ بترتيبٍ حتميّ**:
 *   **T1 · `SCREEN` بالتغطية** — مفردةٌ نثريّةٌ تسمّي سطحًا («شهادة العودة»)
 *        تُطابَق بمسمَّى سطحٍ حيٍّ بتغطيةِ **ثلثَي مفرداتِها فأكثر**،
 *        **ومرشَّحٌ واحدٌ لا غير**. الشاهد: التغطيةُ والمسمَّى والمعرِّف.
 *   **T2 · `UNIT` بالبادئة** — رمزٌ `L##` وبادئتُه في اللُّغْزِ المنتزَعِ من
 *        الدستور ⇒ الإدارةُ قاطعةً، **والسطحُ لا**. الشاهد: الحرفُ وصفُّ
 *        الكتالوجِ الذي عرّفه.
 *   **T3 · `SCOPE_CLASS` بالإعلان** — عبارةُ مدًى لا فردٍ («كل الأسطح» ·
 *        «اللوحات والتقارير») تُحلّ إلى **طبقةٍ معلَنةٍ** بشرطِ انطباقِها
 *        مكتوبًا. ⛔ ولا تُعدّ سطحًا واحدًا فيُقرأ الشاملُ فردًا.
 *   **T4 · `UNRESOLVED`** — ما عدا ذلك يبقى **متعذِّرًا باسمِه**، ولا يُلحق
 *        بأقربِ مرشَّحٍ ليكتمل العدّ. فعدَدٌ مكتمِلٌ بحدسٍ أسوأُ من ناقصٍ مُعلَن.
 *
 * التشغيل:
 *   php tools/amd01_impact_bridge.php [--apply] [--md] [--list] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/lib/xlsx_io.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$LIST  = in_array('--list', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$norm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0653}-\x{0655}\x{0670}\x{0640}]~u', '', (string) $s);
    $s = preg_replace('~[\x{0622}\x{0623}\x{0625}]~u', "\u{0627}", $s);
    $s = preg_replace('~\x{0649}~u', "\u{064A}", $s);
    $s = preg_replace('~\x{0629}~u', "\u{0647}", $s);
    $s = preg_replace('~[«»"\'\[\]\-–—/·،,\.]~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};
$STOP = array('و','في','من','على','عن','ال','كل','مع','او','أو','الي','إلى','بحسب','انطباق','الشركه');
$tok = function ($s) use ($norm, $STOP) {
    $o = array();
    foreach (explode(' ', $norm($s)) as $w) {
        $w = trim($w);
        if ($w === '' || mb_strlen($w) < 3 || in_array($w, $STOP, true)) { continue; }
        $o[$w] = 1;
    }
    return array_keys($o);
};

/* ═══ ① اللُّغْزُ منتزَعًا من الدستور — لا مُخترَعًا ═════════════════════ */
function amd01_letter_legend($ROOT)
{
    $f = $ROOT . '/docs/REPAIR01_20260823/03 · الدستور.xlsx';
    if (!is_file($f)) { return array(array(), 'المصدرُ الحاكمُ غائبٌ عن القرص: 03 · الدستور.xlsx'); }
    $wb = xlsx_read($f);
    if (!isset($wb['C_كتالوج_الأحداث'])) { return array(array(), 'ورقةُ `C_كتالوج_الأحداث` غائبةٌ عن الدستور'); }
    $rows = $wb['C_كتالوج_الأحداث'];
    $vote = array(); $wit = array();
    foreach ($rows as $ri => $x) {
        if (!is_array($x)) { continue; }
        $dept = isset($x[2]) ? trim((string) $x[2]) : '';
        $scr  = isset($x[3]) ? trim((string) $x[3]) : '';
        if ($dept === '' || $scr === '') { continue; }
        /* رقمُ الإدارةِ في صدرِ خانتِها: «14 الصيانة» */
        if (!preg_match('~^(\d{2})\s~u', $dept, $dm)) { continue; }
        $unit = 'DEP-' . $dm[1];
        /* رمزُ الشاشةِ في خانتِها — وقد تحمل أكثرَ من رمزٍ بفاصل.
           ⛔ **والمركَّبُ يُستثنى**: «خ ر04» حرفٌ مفردٌ يسبق الرمزَ، فليس
           «ر04» رمزَ شاشةٍ بل جزءًا من مركَّب. ولولا هذا الاستثناءُ لاعتُمد
           «ر» ⇐ `DEP-09` **بسندِ صفٍّ واحدٍ مركَّب**، فبُنيت به سبعةُ حلولٍ
           لقراراتِ القيادةِ (`ر09-2` · `ر15`) **تصيب إدارةً غيرَ مقصودةٍ**.
           والاستثناءُ نحويٌّ لا ذوقيّ: رمزٌ يسبقه حرفٌ مفردٌ ليس رمزًا مفردًا. */
        if (preg_match_all('~(?<![\x{0621}-\x{064A}])([\x{0621}-\x{064A}])(\d{2})(?:-\d)?(?![\x{0621}-\x{064A}])~u', $scr, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($mm as $m) {
                $at = $m[0][1];
                /* ⛔ **والسابقُ يُفحص كاملًا لا مقطوعًا بحرفين**: قطعُ حرفَين
                   يجعل «التحصيل » تبدو «ل » فتُقرأ مركَّبًا وتُقصى `ز` ظلمًا. */
                $before = mb_strcut($scr, 0, $at);
                if (preg_match('~(?:^|[\s/·،])[\x{0621}-\x{064A}]\s$~u', $before)) { continue; }
                $L = $m[1][0];
                $vote[$L][$unit] = isset($vote[$L][$unit]) ? $vote[$L][$unit] + 1 : 1;
                if (!isset($wit[$L])) { $wit[$L] = 'الدستور › C_كتالوج_الأحداث › ص' . $ri . ' «' . $dept . '» ⇐ «' . $scr . '»'; }
            }
        }
    }
    /* ⛔ **والحرفُ لا يُعتمد إلّا إن دلَّ على إدارةٍ واحدةٍ لا غير** — فحرفٌ
       يتنازعه اثنان جسرٌ يصيب غيرَ مقصودِه، ويبقى `UNRESOLVED` بحقّه. */
    $legend = array(); $amb = array();
    foreach ($vote as $L => $ds) {
        if (count($ds) === 1) { $legend[$L] = array(key($ds), $wit[$L]); }
        else { $amb[$L] = implode(' · ', array_keys($ds)); }
    }
    return array($legend, $amb);
}

/* ═══ ② طبقاتُ المدى المُعلَنةُ — عبارةٌ ⇐ صنفٌ، بشرطِ انطباقٍ مكتوب ═════ */
$SCOPE = array(
    'كل الأسطح'          => array('ALL_SURFACES',       'مدًى شاملٌ لكلِّ سطحٍ حيٍّ — لا فردَ يُسمّى'),
    'كل الشيتات'         => array('ALL_SHEETS',         'مدًى شاملٌ لكلِّ شيتِ وحدةٍ تشغيليّة'),
    'كل شيتات الوحدات'   => array('ALL_SHEETS',         'مدًى شاملٌ لكلِّ شيتِ وحدةٍ تشغيليّة'),
    'اللوحات والتقارير'  => array('PROJECTION_LAYER',   'طبقةُ الإسقاطِ كلُّها — لوحاتٌ وتقارير'),
    'الأمهات والتوابع'   => array('PARENT_CHILD_LAYER', 'كلُّ أمٍّ وبنودُها — حبّةٌ بأمٍّ وتابع'),
    'ترويسات'            => array('HEADER_LAYER',       'ترويسةُ كلِّ سطحٍ — لا جسدُه'),
    'ترويسات الوحدات'    => array('HEADER_LAYER',       'ترويسةُ كلِّ وحدةٍ تشغيليّة'),
    'ترويسة كل وحدة تشغيلية' => array('HEADER_LAYER',   'ترويسةُ كلِّ وحدةٍ تشغيليّة'),
    'الحُرّاس'            => array('GUARD_LAYER',        'طبقةُ الحُرّاسِ الخادميّة'),
    'الحالات'            => array('STATE_LAYER',        'آلاتُ الحالةِ في الأسطحِ كلِّها'),
    'مسارات الاعتماد'    => array('APPROVAL_LAYER',     'مساراتُ الاعتمادِ في محرِّكِ السلطة'),
    'مصفوفة الأثر'       => array('IMPACT_MATRIX',      'مصفوفةُ الأثرِ نفسُها — سجلٌّ لا سطح'),
    'أسطح القيادة'       => array('EXEC_LAYER',         'أسطحُ القيادةِ — الرئيسُ والنوّاب'),
    'الأسطح الخمسة'      => array('DECLARED_FIVE',      'خمسةُ أسطحٍ مُعلَنةٌ في القرارِ نفسِه — عددٌ لا أسماء'),
);

/* ═══ ③ الاختبارُ السالبُ — بمفردةٍ فريدةٍ تكسر التمييز ═══════════════════ */
if ($SELF) {
    $fail = 0;
    list($legend, $amb) = amd01_letter_legend($ROOT);
    if (count($legend) < 6) { echo '  X اللُّغْزُ لم يُنتزَع — ' . count($legend) . " حرفًا فقط\n"; $fail++; }
    /* المعروفُ من الكتالوجِ نفسِه — والانتزاعُ يجب أن يوافقه */
    $must = array('م' => 'DEP-05', 'ق' => 'DEP-12', 'ش' => 'DEP-16', 'ت' => 'DEP-10');
    foreach ($must as $L => $U) {
        if (!isset($legend[$L])) { echo "  X الحرفُ «$L» لم يُنتزَع\n"; $fail++; }
        elseif ($legend[$L][0] !== $U) { echo "  X «$L» ⇐ {$legend[$L][0]} والمقصودُ $U\n"; $fail++; }
    }
    /* **الكاسرُ**: حرفٌ لا وجودَ له في الكتالوجِ لا يجوز أن يُحَلّ */
    if (isset($legend['ژ'])) { echo "  X حرفٌ غيرُ موجودٍ حُلَّ\n"; $fail++; }
    /* والتغطيةُ تُختبر بطرفَيها */
    $a = $tok('شهادة العودة'); $b = $tok('شهادة العودة للخدمة');
    $cov = count(array_intersect($a, $b)) / max(1, count($a));
    if ($cov < 2 / 3) { echo "  X التغطيةُ أسقطت المطابقَ\n"; $fail++; }
    $c2 = $tok('دليل الأصناف');
    $cov2 = count(array_intersect($a, $c2)) / max(1, count($a));
    if ($cov2 >= 2 / 3) { echo "  X التغطيةُ أقرَّت غيرَ المطابق\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — اللُّغْزُ منتزَعٌ من الدستورِ ويوافق كتالوجَه\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ نافذةُ القياس ═══════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

list($legend, $amb) = amd01_letter_legend($ROOT);

/* ═══ ⑤ الأسطحُ الحيّةُ بمسمَّياتِها ═════════════════════════════════════ */
$live = array();
$r = $conn->query("SELECT screen_id, canonical_label_ar, owner_code
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE' AND canonical_label_ar <> ''");
while ($x = $r->fetch_assoc()) { $live[] = $x; }

/* ═══ ⑥ الحلّ ═══════════════════════════════════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT decision_id, affected_screens FROM repair01_decisions
                    WHERE affected_screens <> '' ORDER BY decision_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$out = array();
$cnt = array('SCREEN' => 0, 'UNIT' => 0, 'SCOPE_CLASS' => 0, 'UNRESOLVED' => 0);
$COVER = 2 / 3;

foreach ($rows as $d) {
    foreach (preg_split('~\s*[·،,]\s*~u', (string) $d['affected_screens']) as $raw) {
        $raw = trim($raw);
        if ($raw === '') { continue; }
        $res = 'UNRESOLVED'; $unit = ''; $scr = ''; $cls = ''; $rule = 'T4_UNRESOLVED';
        $wit = 'مفردةٌ حرّةٌ لم تُحَلّ: «' . $raw . '» — لا مسمَّى يبلغ ثلثَي تغطيتِها ولا بادئةً معرَّفةً في الدستور';

        /* T1 — نثرٌ يسمّي سطحًا */
        $tt = $tok($raw);
        if ($tt) {
            $hits = array();
            foreach ($live as $b) {
                $sh = array_intersect($tt, $tok($b['canonical_label_ar']));
                if (count($sh) / count($tt) >= $COVER) { $hits[] = array($b, $sh); }
            }
            if (count($hits) === 1) {
                $res = 'SCREEN'; $scr = $hits[0][0]['screen_id']; $unit = $hits[0][0]['owner_code'];
                $rule = 'T1_LABEL_COVERAGE';
                $wit = 'تغطيةٌ ' . round(count($hits[0][1]) * 100 / count($tt)) . '٪ («'
                     . implode('» «', array_slice($hits[0][1], 0, 3)) . '») على مسمًّى حيٍّ واحدٍ لا غير: «'
                     . $hits[0][0]['canonical_label_ar'] . '» (`' . $scr . '`) · لقطة ' . $sid;
            }
        }

        /* T2 — رمزٌ ببادئةٍ معرَّفةٍ في الدستور ⇒ نطاقٌ لا سطح */
        if ($res === 'UNRESOLVED'
            && preg_match('~^([\x{0621}-\x{064A}])\s?(\d{2})(?:-\d)?~u', $raw, $m)
            && isset($legend[$m[1]])) {
            $res = 'UNIT'; $unit = $legend[$m[1]][0]; $rule = 'T2_LETTER_LEGEND';
            $wit = 'بادئةُ الرمزِ «' . $m[1] . '» ⇐ `' . $unit . '` منتزَعةً من ' . $legend[$m[1]][1]
                 . ' · ⛔ **والرقمُ ' . $m[2] . ' لا يدلُّ على سطحٍ بعينه**: ترقيمُ الدليلِ وترقيمُ `seq` كونان مختلفان · لقطة ' . $sid;
        }

        /* T3 — عبارةُ مدًى مُعلَنة */
        if ($res === 'UNRESOLVED' && isset($SCOPE[$raw])) {
            $res = 'SCOPE_CLASS'; $cls = $SCOPE[$raw][0]; $rule = 'T3_DECLARED_SCOPE';
            $wit = 'عبارةُ مدًى لا فردٍ — الطبقةُ `' . $cls . '`: ' . $SCOPE[$raw][1] . ' · لقطة ' . $sid;
        }

        $cnt[$res]++;
        $out[] = array($d['decision_id'], $raw, $res, $unit, $scr, $cls, $rule, $wit);
    }
}

/* ═══ ⑦ العرض ═══════════════════════════════════════════════════════════ */
$tot = count($out);
echo "\n═══ `AMD-01` م٤ — جسرُ مفرداتِ الأثرِ إلى المعرِّفات ═══\n";
printf("  اللقطة %s · قراراتٌ بمفردات %d · مفرداتٌ كلُّها **%d**\n\n", $sid, count($rows), $tot);
printf("  ── اللُّغْزُ منتزَعًا من الدستور ──\n     حروفٌ دلَّت على إدارةٍ واحدة: **%d**", count($legend));
if ($amb) { echo ' · وحروفٌ تنازعتها إدارتان (تبقى بلا حلّ): ' . count($amb); }
echo "\n     ";
$L = array(); foreach ($legend as $k => $v) { $L[] = $k . '⇐' . $v[0]; }
echo implode(' · ', $L) . "\n";

echo "\n  ── القواعدُ الأربع ──\n";
printf("     T1 `SCREEN`        %4d — نثرٌ يسمّي سطحًا بتغطيةِ ثلثَين ومرشَّحٍ واحد\n", $cnt['SCREEN']);
printf("     T2 `UNIT`          %4d — رمزٌ ببادئةٍ معرَّفةٍ ⇒ إدارةٌ قاطعةً والسطحُ لا\n", $cnt['UNIT']);
printf("     T3 `SCOPE_CLASS`   %4d — عبارةُ مدًى لا فردٍ ⇒ طبقةٌ مُعلَنة\n", $cnt['SCOPE_CLASS']);
printf("     T4 `UNRESOLVED`    %4d — متعذِّرٌ باسمِه · ولا يُلحق بأقربِ مرشَّحٍ ليكتمل العدّ\n", $cnt['UNRESOLVED']);

if ($LIST) {
    echo "\n  ── المتعذِّراتُ بأسمائها ──\n";
    foreach ($out as $o) { if ($o[2] === 'UNRESOLVED') { printf("     %s  «%s»\n", $o[0], $o[1]); } }
}

if ($APPLY) {
    $conn->query("DELETE FROM repair01_decision_screen_bridge");
    $n = 0;
    foreach ($out as $o) {
        $ok = $conn->query("INSERT INTO repair01_decision_screen_bridge
            (decision_id, token_raw, resolution, unit_code, screen_id, scope_class,
             bridge_rule, bridge_witness, snapshot_id, resolved_at)
            VALUES ('" . $e($o[0]) . "','" . $e(mb_substr($o[1], 0, 190)) . "','" . $e($o[2]) . "',
                    '" . $e($o[3]) . "','" . $e($o[4]) . "','" . $e($o[5]) . "',
                    '" . $e($o[6]) . "','" . $e(mb_substr($o[7], 0, 395)) . "','" . $e($sid) . "', NOW())
            ON DUPLICATE KEY UPDATE resolution=VALUES(resolution), unit_code=VALUES(unit_code),
              screen_id=VALUES(screen_id), scope_class=VALUES(scope_class),
              bridge_rule=VALUES(bridge_rule), bridge_witness=VALUES(bridge_witness),
              snapshot_id=VALUES(snapshot_id), resolved_at=NOW()");
        if (!$ok) { exit("✘ تعذّر حلُّ {$o[0]}/{$o[1]}: {$conn->error}\n"); }
        $n++;
    }
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_decision_screen_bridge")->fetch_row()[0];
    $bad  = (int) $conn->query("SELECT COUNT(*) FROM repair01_decision_screen_bridge
                                 WHERE bridge_witness = ''")->fetch_row()[0];
    printf("\n  ✔ كُتب **%d** حلًّا · أُعيدت القراءة %d · حلٌّ بلا شاهدٍ %d\n", $n, $back, $bad);
    $dec = (int) $conn->query("SELECT COUNT(DISTINCT decision_id) FROM repair01_decision_screen_bridge
                                WHERE resolution <> 'UNRESOLVED'")->fetch_row()[0];
    printf("  ◆ قرارٌ له مفردةٌ واحدةٌ محلولةٌ فأكثر: **%d من %d**\n", $dec, count($rows));
} else {
    echo "\n  ◆ عرضٌ فقط — `--apply` يكتب\n";
}

if ($MD) {
    $o  = "# `AMD-01` م٤ — جسرُ مفرداتِ الأثرِ إلى المعرِّفات\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## اللُّغْزُ منتزَعٌ من الدستورِ لا مُخترَع\n\n";
    $o .= "ورقةُ `C_كتالوج_الأحداث` في «٠٣ · الدستور.xlsx» تحمل «الإدارة المصدر» و«الشاشة المصدر»\n";
    $o .= "جنبًا إلى جنب، فمنها انتُزع الحرفُ ⇐ الإدارة. **حروفٌ دلَّت على إدارةٍ واحدة: "
        . count($legend) . "**.\n\n";
    $o .= "⛔ **ولا يُشتقّ الحرفُ من أوّلِ اسمِ الإدارة** — فـ«م» تصلح للمالية وللمبيعات\n";
    $o .= "وللمخازن وللمخاطر وللموقع وللموردين، والاشتقاقُ بالحدسِ يُنتج جسرًا يصيب غيرَ مقصودِه.\n\n";
    $o .= "## والرقمُ لا يدلُّ على الشاشة — مقيسًا\n\n";
    $o .= "جُرِّب على ثمانيةِ رموزٍ معروفةِ المقصدِ من كتالوجِ الأحداثِ نفسِه، فوافق **اثنان**\n";
    $o .= "وانحرف **ستّة**: ترقيمُ الدليلِ التصميميِّ و`seq` في سجلِّ المتطلبات **كونان مختلفان**.\n";
    $o .= "⇒ فالرمزُ يُحلّ إلى **نطاقٍ** ولا يُحلّ إلى **سطح**.\n\n";
    $o .= "## القواعدُ الأربع\n\n| القاعدة | الصنف | العدد |\n|---|---|---:|\n";
    $o .= "| `T1` | `SCREEN` | **" . $cnt['SCREEN'] . "** |\n";
    $o .= "| `T2` | `UNIT` | **" . $cnt['UNIT'] . "** |\n";
    $o .= "| `T3` | `SCOPE_CLASS` | **" . $cnt['SCOPE_CLASS'] . "** |\n";
    $o .= "| `T4` | `UNRESOLVED` | **" . $cnt['UNRESOLVED'] . "** |\n\n";
    $o .= "المجموع **" . $tot . "** مفردةً في **" . count($rows) . "** قرارًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/AMD01_IMPACT_BRIDGE.md', $o);
    echo "  ✔ كُتب docs/REPAIR01_20260823/AMD01_IMPACT_BRIDGE.md\n";
}
