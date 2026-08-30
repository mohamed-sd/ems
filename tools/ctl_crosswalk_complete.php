<?php
/**
 * tools/ctl_crosswalk_complete.php — إكمالُ ربطِ الجسرِ: الـ128 بلا متطلب
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ الاستئنافِ الثاني**: «وجودُ 128 هدفًا بلا ربطِ متطلبٍ يعني أنَّ
 * الجسرَ ليس مكتملَ الربط — لكلِّ واحدةٍ Mapping أو Disposition معماريٌّ
 * نهائيّ، وNO_REQUIREMENT_MAPPING لا يُعدُّ نهائيًّا. ولا تجعلِ البناءَ
 * هو الذي يخلق العلاقةَ المعماريّة».
 *
 * ◆ الـ128 كلُّها من ورقةِ الطرفِ الآخرِ (`source='THEIRS'`) باسمِ ملفٍّ
 *   مخطَّطٍ — والربطُ بثلاثِ درجاتٍ مرتَّبة:
 *   ① **XWALK_VOCAB**: تغطيةُ ثلثَي مفرداتِ الاسمِ المطبَّعِ في سطحِ متطلبٍ
 *      **من وحدتِه نفسِها** (جسرُ `repair01_dept_crosswalk`) ⇒ ربطٌ
 *      بالمتطلبِ، والتعدُّدُ m:1 مسموحٌ ويُسمّى.
 *   ② **ROLE_LENS**: اسمٌ بمفرداتِ عدسةِ الدورِ (عملي · نطاقي · إدارتي ·
 *      الواردة · تخصصي) ⇒ حكمٌ معماريٌّ نهائيٌّ: عدسةُ دورٍ على أسطحِ
 *      وحدتِها لا متطلبٌ مستقلٌّ — كنظائرِ الدمجِ القائمة.
 *   ③ **REGISTER_AMENDMENT_REQUIRED**: لا مقابلَ ولا عدسةَ ⇒ حكمٌ معماريٌّ
 *      نهائيٌّ للجسرِ («خارجُ الدفترِ الحاكمِ») — وإدراجُه في الدفترِ قرارُ
 *      مالكٍ يُرفَع **فئةً واحدةً** لا 128 صفًّا (قاعدةُ القيودِ اليدويّة).
 * ◆ ⛔ لا يُكتب `requirement_id` إلا بشاهدِ مفرداتٍ — والحكمانِ ②③ يبقيانِه
 *   فارغًا عمدًا: الجسرُ يكتمل والبناءُ يبقى محجوبًا بحاجبِه الصادق.
 *
 * التشغيل: php tools/ctl_crosswalk_complete.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$snap = '';
$r = @$conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = (string) $x[0]; }

function xw_norm($s)
{
    $s = (string) $s;
    $s = preg_replace('~\([^)]*\)~u', ' ', $s);            /* اسمُ الملفِّ بين قوسين ليس مفردةً */
    $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = preg_replace('~[^\p{Arabic}A-Za-z0-9]+~u', ' ', $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
}
$STOP = array('اداره', 'ال', 'من', 'في', 'الى', 'على', 'و', 'او', 'سجل', 'شاشه', 'لوحه', 'the', 'my',
              'بحسب', 'انطباق', 'الشركه');
/** تجذيعٌ خفيفٌ للمطابقةِ وحدَها (لا يُكتب): نزعُ ولال/ال والجمعِ واللاحقة —
 *  «الموازنه/ميزانيه» و«فواتير/فاتوره» تتلاقيان، وكلُّ ربطٍ يسمّي مفرداتِه */
function xw_stem($t)
{
    $t = preg_replace('~^(وال|بال|لل|ال)~u', '', $t);
    $syn = array('موازنه' => 'ميزانيه', 'فواتير' => 'فاتوره', 'قيود' => 'قيد', 'حسابات' => 'حساب',
                 'موردين' => 'مورد', 'موردون' => 'مورد', 'عملاء' => 'عميل', 'اصول' => 'اصل',
                 'مستندات' => 'مستند', 'تقارير' => 'تقرير', 'طلبات' => 'طلب', 'اوامر' => 'امر',
                 'عقود' => 'عقد', 'فترات' => 'فتره');
    if (isset($syn[$t])) { return $syn[$t]; }
    $t = preg_replace('~(ات|ين|ون|ها|هم)$~u', '', $t);
    return $t;
}
function xw_toks($s, $stop)
{
    $out = array();
    foreach (explode(' ', xw_norm($s)) as $t) {
        if (mb_strlen($t) < 2) { continue; }
        if (in_array($t, $stop, true)) { continue; }
        $st = xw_stem($t);
        if (mb_strlen($st) < 2) { $st = $t; }
        $out[$st] = true;
    }
    return array_keys($out);
}

/* وحدةُ الهدفِ ⇒ بادئةُ وحدةِ المتطلب — من جسرِ الإداراتِ القائم */
$UNIT = array('DEP-01' => '01', 'DEP-02' => '02', 'DEP-03' => '03', 'DEP-04' => '04', 'DEP-05' => '05',
              'DEP-06' => '06', 'DEP-07' => '07', 'DEP-08' => '08', 'DEP-09' => '09', 'DEP-10' => '10',
              'DEP-11' => '11', 'DEP-12' => '12', 'DEP-13' => '13', 'DEP-14' => '14', 'DEP-15' => '15',
              'DEP-16' => '16', 'DEP-17' => '17', 'EX-CEO' => 'E1', 'EX-DVP' => 'E2', 'IAF' => 'AS', 'WS-MY' => 'WS');

$reqs = array();
$r = $conn->query("SELECT requirement_id, unit, surface FROM repair01_requirements");
while ($x = $r->fetch_assoc()) {
    $pfx = substr((string) $x['unit'], 0, 2);
    $reqs[$pfx][] = array('id' => $x['requirement_id'], 'surface' => (string) $x['surface'],
                          'toks' => xw_toks($x['surface'], $STOP));
}

$targets = array();
$r = $conn->query("SELECT target_uid, unit, name_ar, screen_file FROM repair01_target_universe
                    WHERE (requirement_id IS NULL OR requirement_id = '') ORDER BY target_uid");
while ($x = $r->fetch_assoc()) { $targets[] = $x; }

/* عدسةُ الدورِ تُفحَص على المفردةِ **الخام** لا الجذعِ: «العمليات» تُجذَّع
   «عملي» فتلبس لبوسَ العدسةِ كذبًا — و«اليومي» صفةُ دورةٍ لا عدسةُ دورٍ */
$LENS = array('عملي', 'نطاقي', 'ادارتي', 'الوارده', 'تخصصي', 'مكتبي');

$mapped = array(); $lens = array(); $amend = array();
foreach ($targets as $t) {
    $pfx = isset($UNIT[(string) $t['unit']]) ? $UNIT[(string) $t['unit']] : '';
    $tt = xw_toks($t['name_ar'], $STOP);
    $best = null; $bestCov = 0.0; $bestHits = array();
    /* الشقُّ 05/06 وE1/E2 وحدةٌ ورقيّةٌ واحدةٌ في المصدرِ — يُفتَّش الشقّان */
    $pfxList = $pfx !== '' ? array($pfx) : array();
    if ($pfx === '05') { $pfxList[] = '06'; }
    if ($pfx === '06') { $pfxList[] = '05'; }
    if ($pfx === 'E1') { $pfxList[] = 'E2'; }
    if ($pfx === 'E2') { $pfxList[] = 'E1'; }
    foreach ($pfxList as $p0) {
        if (!isset($reqs[$p0])) { continue; }
        foreach ($reqs[$p0] as $q) {
            if (!$tt) { continue; }
            $hits = array_intersect($tt, $q['toks']);
            $cov = count($hits) / count($tt);
            if ($cov > $bestCov) { $bestCov = $cov; $best = $q; $bestHits = $hits; }
        }
    }
    if ($best !== null && $bestCov >= (2 / 3) && count($bestHits) >= 2) {
        $mapped[] = array($t, $best, $bestCov, $bestHits);
        continue;
    }
    $isLens = false;
    $ttRaw = array();
    foreach (explode(' ', xw_norm($t['name_ar'])) as $w0) { if (mb_strlen($w0) >= 2) { $ttRaw[$w0] = true; } }
    foreach ($LENS as $lw) { if (isset($ttRaw[$lw])) { $isLens = true; break; } }
    if ($isLens) { $lens[] = array($t, $best, $bestCov); } else { $amend[] = array($t, $best, $bestCov); }
}

printf("\n═══ إكمالُ ربطِ الجسر — %d هدفًا بلا متطلب ═══\n", count($targets));
printf("  ① ربطٌ بالمفردات (تغطية ثلثين ومفردتان فأكثر): **%d**\n", count($mapped));
printf("  ② عدسةُ دورٍ (حكمٌ معماريٌّ نهائيّ):            **%d**\n", count($lens));
printf("  ③ يلزم تعديلُ الدفترِ (فئةُ مالكٍ واحدة):       **%d**\n\n", count($amend));
foreach ($mapped as $m0) {
    printf("  ✔ %s ⇒ %s (%.0f%%) «%s» بمفردات [%s]\n", $m0[0]['target_uid'], $m0[1]['id'],
           $m0[2] * 100, mb_substr($m0[1]['surface'], 0, 40), implode(' ', array_slice($m0[3], 0, 4)));
}
echo "\n";
foreach ($lens as $m0) { printf("  ◐ %s عدسةُ دور «%s»\n", $m0[0]['target_uid'], mb_substr($m0[0]['name_ar'], 0, 50)); }
echo "\n";
foreach ($amend as $m0) { printf("  ⛔ %s بلا مقابل «%s»\n", $m0[0]['target_uid'], mb_substr($m0[0]['name_ar'], 0, 50)); }

if (!$APPLY) { echo "\n⛔ معاينةٌ — التطبيقُ بـ--apply\n"; exit(0); }

$n1 = 0; $n2 = 0; $n3 = 0;
foreach ($mapped as $m0) {
    list($t, $q, $cov, $hits) = $m0;
    $w = 'جسرُ المفردات: تغطية ' . round($cov * 100) . '% من اسمِ الهدفِ في سطح `' . $q['id'] . '» بمفردات ['
       . implode(' ', $hits) . '] — m:1 مسموحٌ ومسمًّى · لقطة ' . $snap;
    $ok = $conn->query("UPDATE repair01_target_universe
          SET requirement_id = '" . $e($q['id']) . "', match_method = 'XWALK_VOCAB',
              match_witness = '" . $e($w) . "'
        WHERE target_uid = '" . $e($t['target_uid']) . "' AND (requirement_id IS NULL OR requirement_id = '')");
    if (!$ok) { exit("✘ {$t['target_uid']}: {$conn->error}\n"); }
    $n1 += $conn->affected_rows;
}
foreach ($lens as $m0) {
    $t = $m0[0];
    $w = 'حكمٌ معماريٌّ نهائيّ: عدسةُ دورٍ على أسطحِ وحدتِها (' . $t['unit'] . ') لا متطلبٌ مستقلٌّ — '
       . 'كنظائرِ الدمجِ القائمةِ، ولا يخلق البناءُ العلاقةَ · لقطة ' . $snap;
    $ok = $conn->query("UPDATE repair01_target_universe
          SET match_method = 'ARCH_ROLE_LENS', match_witness = '" . $e($w) . "'
        WHERE target_uid = '" . $e($t['target_uid']) . "' AND (requirement_id IS NULL OR requirement_id = '')");
    if (!$ok) { exit("✘ {$t['target_uid']}: {$conn->error}\n"); }
    $n2 += $conn->affected_rows;
}
foreach ($amend as $m0) {
    $t = $m0[0];
    $w = 'حكمٌ معماريٌّ نهائيّ: سطحٌ من ورقةِ الطرفِ الآخرِ لا مقابلَ له في الدفترِ الحاكمِ — '
       . 'إدراجُه تعديلُ دفترٍ بقرارِ مالكٍ (فئةُ OA-XWALK-AMEND) · لقطة ' . $snap;
    $ok = $conn->query("UPDATE repair01_target_universe
          SET match_method = 'ARCH_REGISTER_AMENDMENT', match_witness = '" . $e($w) . "'
        WHERE target_uid = '" . $e($t['target_uid']) . "' AND (requirement_id IS NULL OR requirement_id = '')");
    if (!$ok) { exit("✘ {$t['target_uid']}: {$conn->error}\n"); }
    $n3 += $conn->affected_rows;
}
/* بندُ المالكِ الفئويُّ الواحدُ — لا 128 صفًّا */
if ($n3 > 0) {
    $dec = 'اعتمادُ إدراجِ ' . $n3 . ' سطحًا من ورقةِ الطرفِ الآخرِ في دفترِ المتطلباتِ الحاكمِ او رفضُه فئةً واحدة';
    $conn->query("INSERT INTO repair01_owner_actions
        (action_key, class, decision, blocks, required_by, options, impact, recommendation, status, snapshot_id, raised_at)
        VALUES ('OA-XWALK-AMEND', 'BUSINESS_DECISION', '" . $e($dec) . "',
                'بناء الاسطح الـ" . (int) $n3 . " المعلقة على تعديل الدفتر — لا يحجب غيرها',
                'امر الاستئناف الثاني — اكمال الجسر', 'ادراج بالجملة بعد مراجعة عينة · او رفض فئوي · او ادراج انتقائي بقائمة',
                'بدون القرار تبقى الاسطح خارج الدفتر الحاكم بحكم معماري نهائي مسجل',
                'مراجعة عينة عشرة ثم قرار فئوي', 'PENDING', '" . $e($snap) . "', NOW())
        ON DUPLICATE KEY UPDATE decision = VALUES(decision), blocks = VALUES(blocks), snapshot_id = VALUES(snapshot_id)");
}
printf("\n✔ رُبط %d · وعُدست %d · وعُلِّقت على تعديلِ الدفترِ %d (بندُ مالكٍ فئويٌّ واحد `OA-XWALK-AMEND`)\n", $n1, $n2, $n3);
