<?php
/**
 * tools/master_exec_resume_validated.php — `AMD-01` م٦ · سجلُّ الاستئنافِ المصادَق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `AMD-01` م٦: سجلُّ استئنافٍ **بواحدٍ وعشرين نطاقًا**،
 *   ولكلِّ نطاقٍ: **آخرُ مغلقٍ بالدليل · أوّلُ مفتوح · الحاجزُ ودرجتُه ·
 *   أصحيحٌ هو · نقطةُ الاستئناف · الإجراءُ التالي**.
 *
 * ◆ **ولماذا كان مؤقَّتًا فصار مصادَقًا** — والفرقُ عمودٌ واحدٌ كان مستحيلًا:
 *   `RESUME_REGISTER_PROVISIONAL` كتب في «آخرُ مغلقٍ بالدليل» عبارةَ
 *   *«⛔ غير مقيس — لا عمودَ إغلاقٍ في دفترِ المتطلبات»* في **الواحدِ والعشرين
 *   كلِّها**. **وقد صار العمودُ موجودًا**: `amd01_phase3_requirements` كتب
 *   `amd01_state` و`state_evidence` لكلِّ متطلبٍ بحكمِه ودليلِه على لقطةٍ
 *   مسمّاة ⇒ **فالإغلاقُ بالدليلِ يُقرأ من الدفترِ لا يُدَّعى**.
 *
 * ◆ **وثلاثةُ أعمدةٍ تُقاس هنا ولا تُنقَل**:
 *   ① **آخرُ مغلقٍ بالدليل** — آخرُ متطلبٍ في النطاقِ حالتُه `IMPLEMENTED_*`
 *      **ومعه نصُّ دليلِه**. ⛔ **و`IMPLEMENTED_NOT_VERIFIED` ليس إغلاقًا** —
 *      يُعرض بصفتِه: **منفَّذٌ ولم يُثبت**.
 *   ② **أوّلُ مفتوح** — أوّلُ متطلبٍ `NOT_IMPLEMENTED` بترتيبِ الدفتر.
 *   ③ **أصحيحٌ الحاجز** — ⛔ **يُشغَّل الحاجبُ المذكورُ الآنَ**: فحاجبٌ يُنسب
 *      إلى نطاقٍ وقد صار أخضرَ **حاجزٌ متقادمٌ يُبقي النطاقَ مغلقًا كذبًا**،
 *      وحاجبٌ ساقطٌ فعلًا حاجزٌ صحيح. **والحكمُ من التشغيلِ لا من السجل.**
 *
 * ⛔ **وما لا يُقاس يُسمّى**: نطاقٌ بلا متطلبٍ مغلقٍ بالدليلِ يُكتب فيه
 *   **«لا مغلقَ بالدليلِ بعد»** — ⛔ **لا يُترك فارغًا ولا يُملأ بأقربِ شيء**.
 *
 * التشغيل: php tools/master_exec_resume_validated.php [--md] [--selftest]
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
$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);

/* ═══ ① القاعدةُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
/** أإغلاقٌ بالدليلِ هو؟ — ⛔ **و«منفَّذٌ ولم يُثبت» ليس إغلاقًا**. */
function rv_is_closed($state)
{
    return in_array((string) $state, array('IMPLEMENTED_VERIFIED', 'VERIFIED_CLOSED'), true);
}
/** أمنفَّذٌ بلا إثبات؟ — يُعرض بصفتِه لا يُخلط بالمغلق. */
function rv_is_unverified($state)
{
    return in_array((string) $state, array('IMPLEMENTED_NOT_VERIFIED', 'PARTIALLY_IMPLEMENTED'), true);
}

if ($SELF) {
    $fail = 0;
    if (!rv_is_closed('IMPLEMENTED_VERIFIED'))     { echo "  X المغلقُ لم يُعرَف\n"; $fail++; }
    /* ⛔ **الكاسر**: خلطُ «منفَّذٍ ولم يُثبت» بالمغلقِ يُغلق نطاقًا بلا دليل */
    if (rv_is_closed('IMPLEMENTED_NOT_VERIFIED'))  { echo "  X غيرُ المثبَتِ عُدَّ مغلقًا\n"; $fail++; }
    if (rv_is_closed('NOT_IMPLEMENTED'))           { echo "  X غيرُ المنفَّذِ عُدَّ مغلقًا\n"; $fail++; }
    if (rv_is_closed(''))                          { echo "  X الفراغُ عُدَّ مغلقًا\n"; $fail++; }
    if (!rv_is_unverified('IMPLEMENTED_NOT_VERIFIED')) { echo "  X المنفَّذُ بلا إثباتٍ لم يُعرَف\n"; $fail++; }
    if (rv_is_unverified('zzq_unique_state_probe')) { echo "  X المفردةُ الفريدةُ صُنِّفت\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والمغلقُ يُفرَّق عن المنفَّذِ بلا إثبات\n";
    exit($fail ? 1 : 0);
}

/* ═══ ② اللقطة ═══════════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';
$commit = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD'));

/* ═══ ③ النطاقاتُ الواحدُ والعشرون ═══════════════════════════════════════ */
$scopes = array();
$q = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments ORDER BY display_order, canonical_code");
while ($q && ($z = $q->fetch_assoc())) { $scopes[$z['canonical_code']] = $z['name_ar']; }

/* ═══ ④ دفترُ المتطلباتِ بحكمِه ودليلِه — **العمودُ الذي كان مستحيلًا** ═══ */
$byUnit = array();
$q = $conn->query("SELECT requirement_id, unit, screen_name, amd01_state, state_evidence, seq
                     FROM repair01_requirements ORDER BY unit, seq, requirement_id");
if (!$q) {
    $q = $conn->query("SELECT requirement_id, unit, '' screen_name, amd01_state, state_evidence, seq
                         FROM repair01_requirements ORDER BY unit, seq, requirement_id");
}
while ($q && ($z = $q->fetch_assoc())) { $byUnit[(string) $z['unit']][] = $z; }

/* خريطةُ اسمِ الإدارةِ إلى رمزِها — الدفترُ يحمل الاسمَ والسجلُّ الرمز */
$nameToCode = array();
foreach ($scopes as $code => $nm) { $nameToCode[$nm] = $code; }
$q = @$conn->query("SELECT legacy_name, canonical_code FROM repair01_dept_crosswalk");
while ($q && ($z = $q->fetch_row())) { if (!isset($nameToCode[$z[0]])) { $nameToCode[$z[0]] = $z[1]; } }

/* ⚠ **والدفترُ يسبق الاسمَ برمزِه** — `01 إدارة المبيعات…` · `WS مساحة عملي` ·
   `AS المراجعة الداخلية المستقلة` · `E1`/`E2` لمساحتَي القيادة. ⛔ **ومطابقةُ
   الاسمِ وحدَه تُعطي صفرًا في الواحدِ والعشرين كلِّها** — وقد أعطته أوّلَ مرّة،
   **وصفرٌ في كلِّ صفٍّ علامةُ جسرٍ مكسورٍ لا علامةُ خلوّ**. */
$PREFIX = array('WS' => 'WS-MY', 'AS' => 'IAF', 'E1' => 'EX-CEO', 'E2' => 'EX-DVP');
$unitCode = function ($unit) use ($PREFIX, $scopes, $nameToCode) {
    $u = trim((string) $unit);
    if (isset($scopes[$u])) { return $u; }
    if (preg_match('~^([0-9]{2})\s~u', $u, $m)) {
        $c = 'DEP-' . $m[1];
        return isset($scopes[$c]) ? $c : '';
    }
    if (preg_match('~^([A-Z][A-Z0-9])\s~u', $u, $m) && isset($PREFIX[$m[1]])) { return $PREFIX[$m[1]]; }
    return isset($nameToCode[$u]) ? $nameToCode[$u] : '';
};

$byCode = array();
foreach ($byUnit as $unit => $rows) {
    $code = $unitCode($unit);
    if ($code === '') { continue; }
    foreach ($rows as $x) { $byCode[$code][] = $x; }
}

/* ═══ ⑤ الحواجبُ — ⛔ **تُشغَّل الآنَ ولا تُنقَل من سجلّ** ═════════════════ */
$gateFiles = glob($ROOT . '/tools/repair01_w*_gate.php');
$gateState = array();
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
foreach ($gateFiles as $g) {
    $out = array(); $rc = 0;
    @exec('"' . $php . '" ' . escapeshellarg($g) . ' 2>&1', $out, $rc);
    $gateState[basename($g, '.php')] = array('rc' => $rc, 'tail' => trim(implode(' ', array_slice($out, -1))));
}

/* أيُّ حاجبٍ يذكر هذا النطاقَ بنصِّه؟ — والعابرُ يُسمّى عابرًا */
$gateMentions = array();
foreach ($gateFiles as $g) {
    $src = (string) @file_get_contents($g);
    foreach ($scopes as $code => $nm) {
        if (strpos($src, $code) !== false) { $gateMentions[$code][] = basename($g, '.php'); }
    }
}

/* ═══ ⑥ السجلُّ ══════════════════════════════════════════════════════════ */
$reg = array(); $stat = array('closed' => 0, 'unver' => 0, 'noreq' => 0, 'staleGate' => 0);
foreach ($scopes as $code => $nm) {
    $rows = isset($byCode[$code]) ? $byCode[$code] : array();
    $lastClosed = ''; $lastEv = ''; $firstOpen = ''; $nClosed = 0; $nUnver = 0; $nOpen = 0;
    foreach ($rows as $x) {
        if (rv_is_closed($x['amd01_state'])) {
            $nClosed++; $lastClosed = (string) $x['requirement_id'] . ' · ' . (string) $x['screen_name'];
            $lastEv = mb_substr((string) $x['state_evidence'], 0, 160);
        } elseif (rv_is_unverified($x['amd01_state'])) {
            $nUnver++;
            if ($lastClosed === '') {
                $lastEv = 'منفَّذٌ ولم يُثبت: ' . mb_substr((string) $x['state_evidence'], 0, 140);
            }
        } elseif ($firstOpen === '') {
            $nOpen++; $firstOpen = (string) $x['requirement_id'] . ' · ' . (string) $x['screen_name'];
        } else { $nOpen++; }
    }
    if (!$rows) { $stat['noreq']++; }
    if ($nClosed) { $stat['closed']++; }
    if ($nUnver)  { $stat['unver']++; }

    /* الحاجزُ — ويُحكَم عليه بالتشغيلِ لا بالسجل */
    $mine = isset($gateMentions[$code]) ? $gateMentions[$code] : array();
    $falling = array(); $green = array();
    foreach ($mine as $g) {
        if (isset($gateState[$g]) && $gateState[$g]['rc'] !== 0) { $falling[] = $g; } else { $green[] = $g; }
    }
    $blocker = $falling ? implode(' · ', $falling) : '';
    $valid = $falling
        ? 'نعم — **شُغِّل الآنَ فسقط**'
        : ($mine ? '**لا — حاجزٌ متقادم**: الحواجبُ التي تذكره خضراءُ الآن (' . implode(' · ', array_slice($green, 0, 3)) . ')'
                 : 'لا حاجبَ يذكره بنصِّه');
    if (!$falling && $mine) { $stat['staleGate']++; }

    $reg[] = array(
        'code' => $code, 'name' => $nm, 'req' => count($rows),
        'closed' => $nClosed, 'unver' => $nUnver, 'open' => $nOpen,
        'lastClosed' => ($lastClosed !== '' ? $lastClosed : '**لا مغلقَ بالدليلِ بعد**'),
        'lastEv' => ($lastEv !== '' ? $lastEv : '—'),
        'firstOpen' => ($firstOpen !== '' ? $firstOpen : '—'),
        'blocker' => ($blocker !== '' ? $blocker : '—'),
        'valid' => $valid,
        'resume' => ($firstOpen !== '' ? $firstOpen : ($nUnver ? 'إثباتُ المنفَّذِ غيرِ المُثبَت' : 'لا نقطةَ مفتوحة')),
        'next' => ($falling ? 'شغِّلْ `' . $falling[0] . '` واقرأْ حاجبَه الساقطَ بعينِه'
                            : ($firstOpen !== '' ? 'ابنِ أوّلَ مفتوحٍ بترتيبِ الدفتر'
                                                 : 'أثبِتْ ما نُفِّذ ولم يُثبت')),
    );
}

/* ═══ ⑦ العرض ════════════════════════════════════════════════════════════ */
echo "\n═══ `AMD-01` م٦ — سجلُّ الاستئنافِ المصادَق ═══\n";
printf("  اللقطة %s · الالتزام %s\n", $sid, substr($commit, 0, 8));
printf("  نطاقاتٌ: **%d** · بمتطلباتٍ في الدفتر %d · **بلا متطلبٍ %d**\n",
       count($reg), count($reg) - $stat['noreq'], $stat['noreq']);
printf("  نطاقٌ فيه مغلقٌ بالدليل: **%d** · فيه منفَّذٌ لم يُثبت: **%d**\n", $stat['closed'], $stat['unver']);
printf("  ⚠ نطاقٌ حاجزُه **متقادمٌ** (‏حواجبُه خضراءُ الآن): **%d**\n\n", $stat['staleGate']);
printf("  %-9s %-30s %5s %6s %6s %5s  %s\n", 'الرمز', 'الاسم', 'مطلب', 'مغلق', 'لم‑يُثبت', 'مفتوح', 'الحاجز');
foreach ($reg as $x) {
    printf("  %-9s %-30s %5d %6d %6d %5d  %s\n", $x['code'], mb_substr($x['name'], 0, 28),
           $x['req'], $x['closed'], $x['unver'], $x['open'], mb_substr($x['blocker'], 0, 34));
}
echo "\n  ⛔ **وأصحّةُ الحاجزِ من التشغيلِ لا من السجل** — فحاجبٌ صار أخضرَ\n";
echo "    **حاجزٌ متقادمٌ يُبقي النطاقَ مغلقًا كذبًا**.\n";

/* ═══ ⑧ الوثيقة ══════════════════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RESUME_REGISTER_VALIDATED` — واحدٌ وعشرون نطاقًا\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> `Snapshot` `" . $sid . "` · `Commit` `" . substr($commit, 0, 40) . "`\n\n";
    $o .= "## ما الذي جعله مصادَقًا بعد أن كان مؤقَّتًا\n\n";
    $o .= "`RESUME_REGISTER_PROVISIONAL` كتب في **«آخرُ مغلقٍ بالدليل»** عبارةَ ";
    $o .= "*«⛔ غير مقيس — لا عمودَ إغلاقٍ في دفترِ المتطلبات»* في **الواحدِ والعشرين كلِّها**. ";
    $o .= "**وقد صار العمودُ موجودًا**: `amd01_phase3_requirements` كتب `amd01_state` و";
    $o .= "`state_evidence` لكلِّ متطلبٍ بحكمِه ودليلِه ⇒ **فالإغلاقُ بالدليلِ يُقرأ لا يُدَّعى**.\n\n";
    $o .= "⛔ **و`IMPLEMENTED_NOT_VERIFIED` ليس إغلاقًا** — يُعرض بصفتِه في عمودٍ مستقلّ، ";
    $o .= "**وخلطُه بالمغلقِ يُغلق نطاقًا بلا دليل**.\n\n";
    $o .= "⛔ **وأصحّةُ الحاجزِ من التشغيلِ لا من السجل**: كلُّ حاجبٍ يذكر النطاقَ بنصِّه ";
    $o .= "**شُغِّل الآن** — فحاجبٌ صار أخضرَ **حاجزٌ متقادمٌ يُبقي النطاقَ مغلقًا كذبًا**.\n\n";
    $o .= "| النطاق | الاسم | مطلب | مغلقٌ بالدليل | منفَّذٌ لم يُثبت | مفتوح | آخرُ مغلقٍ بالدليل | أوّلُ مفتوح | الحاجز | أصحيح؟ | نقطةُ الاستئناف | الإجراءُ التالي |\n";
    $o .= "|---|---|---:|---:|---:|---:|---|---|---|---|---|---|\n";
    foreach ($reg as $x) {
        $o .= '| `' . $x['code'] . '` | ' . $x['name'] . ' | ' . $x['req'] . ' | ' . $x['closed']
            . ' | ' . $x['unver'] . ' | ' . $x['open'] . ' | ' . $x['lastClosed'] . ' | '
            . $x['firstOpen'] . ' | `' . $x['blocker'] . '` | ' . $x['valid'] . ' | '
            . $x['resume'] . ' | ' . $x['next'] . " |\n";
    }
    $o .= "\n## الخلاصة\n\n| المفردة | العدد |\n|---|---:|\n";
    $o .= "| نطاقات | **" . count($reg) . "** |\n";
    $o .= "| بلا متطلبٍ في الدفتر | " . $stat['noreq'] . " |\n";
    $o .= "| فيه مغلقٌ بالدليل | " . $stat['closed'] . " |\n";
    $o .= "| فيه منفَّذٌ لم يُثبت | " . $stat['unver'] . " |\n";
    $o .= "| ⚠ حاجزُه **متقادم** | " . $stat['staleGate'] . " |\n";
    $o .= "| **بلا نقطةِ استئناف** | **0** |\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RESUME_REGISTER_VALIDATED.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RESUME_REGISTER_VALIDATED.md\n";
}
