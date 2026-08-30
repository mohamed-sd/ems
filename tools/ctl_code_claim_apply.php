<?php
/**
 * tools/ctl_code_claim_apply.php — الاستئنافُ دفعة ١ · قاعدةُ المطالبةِ الحرفيّة R-CODE_CLAIM
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيسُ الذي أمسكه بابُ البناء**: قبل بناءِ أوّلِ هدفٍ «غيرِ
 *   مبنيٍّ» وُجد أنَّ `Fleet/asset_readiness.php` **يذكر `FLEET-20` في رأسِه
 *   حرفًا** — فجسرُ الأسماءِ أعماه فرقُ صيغةٍ («الشهرية» ≠ «الشهري») وكاد
 *   البناءُ **يُعيد بناءَ المبنيّ** — وهو المحظورُ الأوّلُ في أمرِ الضبط.
 *
 * ◆ **القاعدة**: رأسُ سطحٍ حيٍّ (‏أوّلُ ٤٠٠٠ بايت — فذكرٌ في الجسمِ قد يكون
 *   رابطًا) يذكر معرِّفَ المتطلبِ حرفًا (‏بصيغِ `FLEET-20` و`FLEET-19/20`
 *   المفكوكة) = **مطالبةُ تنفيذٍ مقصودة** — فعُرفُ الدارِ كتابةُ معرِّفاتِ
 *   المتطلباتِ في رؤوسِ منفِّذاتِها.
 *   ⇒ هدفُ `NOT_BUILT` بمطالِبٍ حيٍّ **واحدٍ لا غير** يُصحَّح `MATCHED`
 *   بمعرِّفِ سطحِه وشاهدِ المطالبة.
 *   ⛔ **ومطالِبان فأكثرُ لا يُرجَّحان** — يُسمَّون ويُتركون للحسم.
 *   ⛔ **وسطحٌ طالبَ به هدفان لا يُمنح لثانيهما** — الهدفُ الواحدُ سطحُه واحد.
 *
 * التشغيل: php tools/ctl_code_claim_apply.php [--apply] [--md] [--selftest]
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
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);
$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

define('CC_PFX', 'ACC|CEO|FIN|FLEET|GOV|HR|IAF|MNT|MY|OPS|PRC|RSK|SAL|SITE|SUP|TKT|TRP|TRS|VP|WH|WRK');

/** مطالباتُ رأسِ ملفٍّ واحدٍ — بفكِّ صيغِ `/` */
function cc_head_claims($head)
{
    $out = array();
    if (!preg_match_all('~\b(' . CC_PFX . ')-(\d+(?:/\d+)*)~u', (string) $head, $m, PREG_SET_ORDER)) { return $out; }
    foreach ($m as $mm) {
        foreach (explode('/', $mm[2]) as $num) {
            $out[$mm[1] . '-' . str_pad($num, 2, '0', STR_PAD_LEFT)] = 1;
        }
    }
    return array_keys($out);
}

if ($SELF) {
    $fail = 0;
    $a = cc_head_claims('… (RPR-W05 · FLEET-19/20) …');
    if (!in_array('FLEET-19', $a, true) || !in_array('FLEET-20', $a, true)) { echo "  X صيغةُ / لم تُفكّ\n"; $fail++; }
    if (cc_head_claims('SAL-02 نصٌّ') !== array('SAL-02')) { echo "  X المفردُ لم يُلتقط\n"; $fail++; }
    /* **الكاسر**: بادئةٌ ليست من العائلةِ لا تُلتقط */
    if (cc_head_claims('ZZQ-77 ليس متطلبًا')) { echo "  X بادئةٌ غريبةٌ التُقطت\n"; $fail++; }
    if (in_array('CLM-20', cc_head_claims('CLM-2026 مستند'), true)) { echo "  X مرجعُ مستندٍ عُدَّ متطلبًا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n" : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الفكُّ صحيحٌ والغريبُ لا يمرّ\n";
    exit($fail ? 1 : 0);
}

/* ═══ ① المسحُ ══════════════════════════════════════════════════════════ */
$claims = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE on_disk = 1 AND route <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    $p = $ROOT . '/' . $x['route'];
    if (!is_file($p)) { continue; }
    foreach (cc_head_claims(file_get_contents($p, false, null, 0, 4000)) as $rid) {
        $claims[$rid][$x['screen_id']] = $x['route'];
    }
}

/* الأسطحُ المُطالَبُ بها سلفًا من أهدافٍ محكومة — لا تُمنح ثانيةً */
$taken = array();
$r = $conn->query("SELECT screen_id, target_uid FROM repair01_target_universe WHERE screen_id <> ''");
while ($r && ($x = $r->fetch_assoc())) { $taken[$x['screen_id']] = $x['target_uid']; }

/* ═══ ② الخطّة ══════════════════════════════════════════════════════════ */
$plan = array(); $multi = array(); $conflict = array();
$r = $conn->query("SELECT target_uid, requirement_id, unit FROM repair01_target_universe
                    WHERE verdict = 'NOT_BUILT' AND COALESCE(requirement_id,'') <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    $rid = $x['requirement_id'];
    if (!isset($claims[$rid])) { continue; }
    if (count($claims[$rid]) > 1) {
        $multi[] = $x['target_uid'] . ' ' . $rid . ' ⇐ ' . implode(' · ', array_values($claims[$rid]));
        continue;
    }
    $sid = key($claims[$rid]);
    $route = $claims[$rid][$sid];
    /* ◆ **السطحُ الواحدُ قد ينفّذ متطلبَين — بنصِّ المطالبةِ نفسِها**: رأسُ
       `asset_readiness` يذكر `FLEET-19/20` معًا، وأمرُ الضبطِ §٤ يجيز
       متعدّدًا لمتعدّد. ⇒ المشاركةُ **تُدوَّن في الشاهدِ** لا تُحجب. */
    $shared = isset($taken[$sid]) ? $taken[$sid] : '';
    $plan[] = array('t' => $x, 'sid' => $sid, 'route' => $route, 'shared' => $shared);
    $taken[$sid] = $x['target_uid'];
}

echo "\n═══ الاستئنافُ دفعة ١ — قاعدةُ المطالبةِ الحرفيّة `R-CODE_CLAIM` ═══\n";
printf("  اللقطة %s · متطلباتٌ مُطالَبٌ بها في الرؤوس: %d\n", $snap !== '' ? $snap : 'DRY', count($claims));
printf("  يُصحَّح `NOT_BUILT` ⇒ `MATCHED`: **%d** · متعدّدُ المطالِبين (يُحسم لا يُرجَّح): %d · سطحُه مأخوذٌ سلفًا: %d\n",
       count($plan), count($multi), count($conflict));
foreach ($plan as $p0) { echo "    ✔ {$p0['t']['target_uid']} {$p0['t']['requirement_id']} ⇐ {$p0['sid']} {$p0['route']}\n"; }
foreach ($multi as $q) { echo "    ⛔ $q\n"; }
foreach ($conflict as $q) { echo "    ⛔ $q\n"; }

if ($APPLY) {
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $p0) {
        $wit = 'R-CODE_CLAIM · رأسُ `' . $p0['route'] . '` يذكر `' . $p0['t']['requirement_id']
             . '` حرفًا (‏عُرفُ الدارِ كتابةُ معرِّفاتِ المتطلباتِ في رؤوسِ منفِّذاتِها) — '
             . 'مطالِبٌ حيٌّ **واحدٌ لا غير**، وجسرُ الأسماءِ أعماه فرقُ صيغة. '
             . ($p0['shared'] !== '' ? 'والسطحُ يخدم معه ' . $p0['shared'] . ' (‏متعدّدٌ لمتعدّدٍ بنصِّ المطالبة). ' : '')
             . 'كان `NOT_BUILT` وكاد يُعادُ بناؤه · لقطة ' . $snap;
        $ok = $conn->query("UPDATE repair01_target_universe
                               SET verdict = 'MATCHED', screen_id = '" . $e($p0['sid']) . "',
                                   verdict_witness = '" . $e($wit) . "',
                                   verdict_snapshot = '" . $e($snap) . "', verdict_at = NOW()
                             WHERE target_uid = '" . $e($p0['t']['target_uid']) . "' AND verdict = 'NOT_BUILT'");
        if (!$ok) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
        $n += $conn->affected_rows;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ صُحِّح **%d** هدفًا — كلٌّ بشاهدِ مطالبتِه ولقطتِه\n", $n);
}

if ($MD) {
    $o  = "# الاستئنافُ دفعة ١ — قاعدةُ المطالبةِ الحرفيّة `R-CODE_CLAIM`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "**بابُ البناءِ أمسك إعادةَ بناءِ المبنيّ**: أولُ أهدافِ Build Lane (`FLEET-20`) وجد سطحَه\n";
    $o .= "الحيَّ يطالب به حرفًا في رأسِه — فمُسحت الرؤوسُ كلُّها قبل أيِّ بناء.\n\n";
    $o .= "| المفردة | العدد |\n|---|---:|\n";
    $o .= "| متطلباتٌ مُطالَبٌ بها في رؤوسِ الشيفرة | " . count($claims) . " |\n";
    $o .= "| `NOT_BUILT` صُحِّح `MATCHED` بمطالِبٍ واحد | **" . count($plan) . "** |\n";
    $o .= "| متعدّدُ المطالِبين — يُحسم لا يُرجَّح | " . count($multi) . " |\n";
    $o .= "| سطحُه مأخوذٌ سلفًا — لا يُمنح لثانٍ | " . count($conflict) . " |\n\n";
    if ($multi || $conflict) {
        $o .= "## الموقوفُ بأسمائه\n\n";
        foreach ($multi as $q) { $o .= "- ⛔ $q\n"; }
        foreach ($conflict as $q) { $o .= "- ⛔ $q\n"; }
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_CODE_CLAIM.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_CODE_CLAIM.md\n";
}
