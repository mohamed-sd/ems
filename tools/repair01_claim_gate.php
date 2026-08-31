<?php
/**
 * tools/repair01_claim_gate.php — حاجبُ «لا نسبةَ بلا مقياس» (G-CLAIM-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * **FINAL_CLOSE §١·١ البند ②**: «رسالةُ التزامٍ تحمل نسبةً لا يُخرجها
 * المقياسُ حرفًا ⇒ تُرَدّ» — وعلّتُه أنّها رابعُ مرّةٍ في ملفِّ السايدبارِ
 * وحدَه يُعلَن بلوغُ ١٠٠٪ قبل بلوغِه.
 *
 * ◆ فحصان:
 *   ① `CLAIM_NOT_IN_MEASURE` — كلُّ نسبةٍ في الرسالةِ (`N٪` أو `N%`) يجب أن
 *      تظهر حرفًا في مُخرَجٍ مولَّدٍ تحت docs/REPAIR01_20260823/*.md
 *      (بعد توحيدِ الأرقامِ الهنديّةِ والفاصلةِ العربيّة).
 *   ② `CLAIM_METRIC_MISMATCH` — سطرٌ يقرن مقياسًا مرقّمًا `#N` بنسبةٍ يجب أن
 *      تكون نسبتُه من خليّةِ «المقيس» لصفِّ N في لوحةِ `RPR-02` أو `RPR-03`
 *      — فوجودُ «100٪» في مقياسٍ آخرَ لا يُجيز ادّعاءَها لهذا.
 *
 * ◆ كلُّ ردٍّ يُقيَّد في `guard_denials` بـ`guard_code='G-CLAIM-01'` —
 *   **والحاجبُ يُثبَت بحركةِ العدّادِ بواحدٍ ثمَّ عودة**
 *   (`repair01_claim_gate_negative.php`).
 *
 * التشغيل: php tools/repair01_claim_gate.php <ملفُّ رسالةِ الالتزام>
 *          php tools/repair01_claim_gate.php --msg="نصُّ الرسالة" [--no-log]
 * الخروج:  0 لا ادّعاءَ بلا مقياس · 1 ادّعاءٌ رُدَّ
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$DOCS = $ROOT . '/docs/REPAIR01_20260823';

/* ── المدخل ─────────────────────────────────────────────────────────────── */
$msg = null; $noLog = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--no-log') { $noLog = true; continue; }
    if (strpos($a, '--msg=') === 0) { $msg = substr($a, 6); continue; }
    if (is_file($a)) { $msg = file_get_contents($a); continue; }
}
if ($msg === null) { fwrite(STDERR, "لا رسالةَ — مرِّرْ ملفًّا أو --msg=\n"); exit(1); }

/* ── التوحيد: أرقامٌ هنديّةٌ ⇒ لاتينيّة · ٫ ⇒ . · % ⇒ ٪ · تُنزع فواصلُ الألوف ── */
function claim_norm($s) {
    $s = strtr($s, array('٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','%'=>'٪','٬'=>''));
    return preg_replace('/(?<=\d),(?=\d{3})/u', '', $s);
}
$msgN = claim_norm($msg);

/* ── انتزاعُ الادّعاءات: نسبةٌ = عددٌ يليه ٪ ──────────────────────────── */
preg_match_all('/(\d+(?:\.\d+)?)٪/u', $msgN, $m);
$claims = array_values(array_unique($m[1]));
if (!$claims) exit(0); // لا نسبةَ في الرسالة — لا شيءَ يُحرس

/* ── مدوّنةُ المقيس: كلُّ .md في جذرِ مجلّدِ الحملةِ (لا plan/ ولا orders/) ── */
$corpus = '';
foreach (glob($DOCS . '/*.md') as $f) $corpus .= claim_norm(file_get_contents($f)) . "\n";

/* ── خلايا «المقيس» للمقياسِ المرقّم في اللوحتَين ─────────────────────── */
function scorecard_row_pcts($file, $n) {
    if (!is_file($file)) return null;
    foreach (explode("\n", claim_norm(file_get_contents($file))) as $line) {
        if (preg_match('/^\|\s*' . $n . '\s*\|/u', $line)) {
            // خليّةُ «المقيس» وحدَها (العمود الخامس) — فنسبُ الهدفِ والشاهدِ ليست مقيسَ هذا الصفّ
            $cells = explode('|', $line);
            if (count($cells) < 6) return null;
            preg_match_all('/(\d+(?:\.\d+)?)٪/u', $cells[5], $mm);
            return $mm[1];
        }
    }
    return null;
}

$fails = array();
foreach ($claims as $c) {
    if (strpos($corpus, $c . '٪') === false) {
        $fails[] = array('CLAIM_NOT_IN_MEASURE', $c . '٪',
            "النسبةُ {$c}٪ لا يُخرجها أيُّ مقياسٍ حرفًا في docs/REPAIR01_20260823/*.md");
    }
}
/* الفحص ② — سطرٌ يقرن #N بنسبة */
foreach (explode("\n", $msgN) as $line) {
    if (!preg_match('/#(\d{1,2})\b/u', $line, $mn)) continue;
    if (!preg_match_all('/(\d+(?:\.\d+)?)٪/u', $line, $mp) || !$mp[1]) continue;
    $n = (int)$mn[1];
    $allowed = array();
    foreach (array('/RPR02_S12_SCORECARD.md', '/RPR03_SCORECARD.md') as $sc) {
        $p = scorecard_row_pcts($DOCS . $sc, $n);
        if ($p) $allowed = array_merge($allowed, $p);
    }
    if (!$allowed) continue; // لا صفَّ يُقاس عليه — يكفي الفحص ①
    foreach ($mp[1] as $c) {
        if ($c === '100' && in_array('100', $allowed, true) === false && preg_match('/هدف|مستهدف/u', $line)) continue;
        if (!in_array($c, $allowed, true)) {
            $fails[] = array('CLAIM_METRIC_MISMATCH', "#{$n}={$c}٪",
                "المقياسُ #{$n} لا يُخرج {$c}٪ — خليّةُ «المقيس» تُخرج: " . implode(' · ', array_unique($allowed)) . '٪');
        }
    }
}

if (!$fails) exit(0);

/* ── الردُّ: قيدٌ في guard_denials ثمَّ خروجٌ أحمر ────────────────────── */
fwrite(STDERR, "⛔ G-CLAIM-01 — رُدَّت رسالةُ الالتزام: نسبةٌ لا يُخرجها المقياسُ حرفًا\n");
foreach ($fails as $f) fwrite(STDERR, "   ✘ [{$f[0]}] {$f[1]} — {$f[2]}\n");
fwrite(STDERR, "   ◆ أصلِحِ الرقمَ إلى ما يُخرجه المقياسُ، أو شغِّلِ المقياسَ حتى يُخرجه — ولا تُرخِ الحاجب.\n");

if (!$noLog) {
    define('EMS_CLI', true);
    require_once $ROOT . '/config.php';
    while (ob_get_level()) ob_end_clean();
    $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    if ($conn) {
        mysqli_set_charset($conn, 'utf8mb4');
        // person_id لا يقبل NULL — والصفرُ هنا «بلا شخصٍ» كعُرفِ جلسةِ uid=0 في العُدّة
        $st = $conn->prepare("INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code, at, verb, verb_state) VALUES (0,'G-CLAIM-01',0,?,?,NOW(),'commit','DENIED')");
        foreach ($fails as $f) {
            $ref = mb_substr($f[1], 0, 120); $rc = mb_substr($f[0] . ': ' . $f[2], 0, 80);
            if (!$st || !($st->bind_param('ss', $ref, $rc) && $st->execute()))
                fwrite(STDERR, "   ⚠ تعذّر قيدُ الردِّ: " . $conn->error . " — والردُّ نافذٌ مع ذلك\n");
        }
    } else fwrite(STDERR, "   ⚠ تعذّر قيدُ الردِّ في guard_denials — والردُّ نافذٌ مع ذلك\n");
}
exit(1);
