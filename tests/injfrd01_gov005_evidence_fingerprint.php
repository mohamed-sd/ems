<?php
/**
 * tests/injfrd01_gov005_evidence_fingerprint.php
 *   شاهدُ FR-GOV-005 — كلُّ دليلٍ على الالتزامِ نفسِه والبصمةِ نفسِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «كلُّ دليلٍ على **الالتزامِ نفسِه والبصمةِ نفسِها** —
 *   ولا يُقبل دليلٌ من بناءٍ آخر» · ومعيارُ القبول «مطابقةُ بصمةِ الدليلِ ببصمةِ
 *   البناءِ المُعلَن» · وسالبُه «دليلٌ ببصمةٍ مختلفة ← **يُرفض**».
 *
 * ◆ **والعطبُ الذي يمنعه**: دليلٌ أُنتج على شجرةٍ ثمّ تغيّرت الشيفرةُ تحته يبقى
 *   أخضرَ في الورقةِ **ولا يُعاد تشغيلُه اليوم**. فالإغلاقُ يصير ذكرى لا حقيقة.
 *   والدفترُ يسجّل `Commit` لكلِّ متطلبٍ مُغلَق — **فالبصمةُ مكتوبةٌ سلفًا**،
 *   والناقصُ **حارسٌ يقارنها بالواقع**.
 *
 * ◆ **وثلاثةُ أوجهٍ تُقاس لكلِّ دليل**:
 *   ① الالتزامُ المُعلَنُ **موجودٌ في تاريخِ المستودعِ فعلًا** — لا هاشَ مخترَعًا.
 *   ② ملفُّ الدليلِ **كان موجودًا عندَ ذلك الالتزام** — لا دليلَ سبق نفسَه.
 *   ③ الملفُّ **قائمٌ اليومَ** — فدليلٌ حُذف لا يُعاد تشغيلُه.
 *
 * ◆ **ولا يُطلَب تطابقُ الشجرةِ حرفًا**: العملُ يتقدّم بعدَ كلِّ إغلاق، فاشتراطُ
 *   `HEAD` واحدٍ للجميعِ يجعل كلَّ إغلاقٍ يُبطل ما قبلَه. **المشترَطُ أن يكون
 *   الدليلُ قابلًا لإعادةِ التشغيلِ على التزامِه** — وهو نصُّ §الحادي عشر.
 *
 * التشغيل: php tests/injfrd01_gov005_evidence_fingerprint.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function git($ROOT, $args) {
    return trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' ' . $args . ' 2>&1'));
}

/**
 * تفكيكُ عمودِ الدليلِ إلى مساراتٍ حقيقية.
 * ◆ **الصيغةُ ليست واحدةً**: الفواصلُ `·` و`+`، واللواحقُ `[--negative]`
 *   و`--snapshot=before | --compare`. وأوّلُ محلِّلٍ قرأ `·` وحدَها فرمى
 *   **ستةَ أدلّةٍ قائمةٍ فعلًا** بوصفِها مفقودة — **رسوبٌ من المحلِّلِ لا من
 *   الشجرة**. ولو صُدِّق لأُعيد فتحُ ما أُغلق بحق.
 * ◆ **وما لا يُفهَم يُعاد كما هو** ليُحسَب مفقودًا ويُسمّى — لا يُبتلع.
 */
function ev_pieces($s)
{
    $s = preg_replace('~\[[^\]]*\]~u', ' ', (string) $s);   /* [--negative] */
    $s = preg_replace('~--[a-z0-9=_-]+~i', ' ', $s);          /* --snapshot=before */
    $parts = preg_split('~\s*(?:·|\+|\|)\s*~u', $s);
    $out = array();
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || strpos($p, '/') === false) { continue; }
        $out[] = $p;
    }
    return $out;
}

$neg = in_array('--negative', $argv, true);
echo "══ FR-GOV-005 — بصمةُ الدليلِ تطابق بصمةَ البناءِ المُعلَن ══\n";

$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
if (!is_file($XLSX)) { exit("⛔ الدفترُ الرسميُّ مفقود\n"); }
$wb = xlsx_read($XLSX);
$rows = $wb[array_keys($wb)[0]];
$hdr = $rows[3];
$ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }

$items = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { continue; }
    $cl = trim((string) ($r[$ix['Closure_State']] ?? ''));
    if ($cl !== 'EVIDENCE_CLOSED') { continue; }
    $items[] = array(
        'id'  => $id,
        'sha' => trim((string) ($r[$ix['Commit']] ?? '')),
        'ev'  => trim((string) ($r[$ix['Evidence_Status']] ?? '')),
    );
}
printf("  المقام: **%d متطلبًا مُغلَقًا بالدليل** — وكلُّها تُفحَص\n", count($items));
chk(count($items) > 0, '**المقامُ غيرُ صفريّ** — ثمَّ أدلّةٌ تُفحَص', count($items) . ' متطلبًا');
if (empty($items)) { echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1); }

/* ── ① كلُّ التزامٍ مُعلَنٍ موجودٌ في تاريخِ المستودع ─────────────────────── */
$noSha = array(); $badSha = array();
foreach ($items as $it) {
    if ($it['sha'] === '') { $noSha[] = $it['id']; continue; }
    $t = git($ROOT, 'cat-file -t ' . escapeshellarg($it['sha']));
    if ($t !== 'commit') { $badSha[] = $it['id'] . ' (' . $it['sha'] . ')'; }
}
chk(empty($noSha), 'ولكلِّ مُغلَقٍ **التزامٌ مُعلَن**',
    empty($noSha) ? count($items) . ' من ' . count($items) : 'بلا التزام: ' . implode(' · ', $noSha));
chk(empty($badSha), 'وكلُّ التزامٍ **موجودٌ في تاريخِ المستودعِ فعلًا** — لا هاشَ مخترَعًا',
    empty($badSha) ? 'صفرُ هاشٍ لا وجودَ له' : implode(' · ', array_slice($badSha, 0, 4)));

/* ── ② ملفُّ الدليلِ كان موجودًا عندَ ذلك الالتزام ────────────────────────── */
$preExist = array(); $gone = array(); $checked = 0;
foreach ($items as $it) {
    if ($it['sha'] === '') { continue; }
    foreach (ev_pieces($it['ev']) as $pp) {
        $checked++;
        if (!is_file($ROOT . '/' . $pp)) { $gone[] = $it['id'] . ' ⇒ ' . $pp; continue; }
        $o = git($ROOT, 'cat-file -e ' . escapeshellarg($it['sha'] . ':' . $pp) . ' && echo YES');
        if (strpos($o, 'YES') === false) { $preExist[] = $it['id'] . ' ⇒ ' . $pp; }
    }
}
printf("  ملفاتُ دليلٍ فُحصت: %d\n", $checked);
chk($checked > 0, 'و**مقامُ الملفاتِ غيرُ صفريّ**', "{$checked} ملفًا");
chk(empty($gone), 'وكلُّ ملفِّ دليلٍ **قائمٌ اليومَ** — فالمحذوفُ لا يُعاد تشغيلُه',
    empty($gone) ? 'صفرُ مفقود' : count($gone) . ': ' . implode(' · ', array_slice($gone, 0, 3)));
chk(empty($preExist),
    'FR-GOV-005 · وكلُّ دليلٍ **كان موجودًا عندَ التزامِه** — لا دليلَ سبق نفسَه',
    empty($preExist) ? 'صفرُ دليلٍ من بناءٍ لاحق'
                     : count($preExist) . ': ' . implode(' · ', array_slice($preExist, 0, 3)));

/* ── ③ ولا التزامَ خارجَ الفرعِ العامل ──────────────────────────────────── */
$branch = git($ROOT, 'rev-parse --abbrev-ref HEAD');
$offBranch = array();
foreach ($items as $it) {
    if ($it['sha'] === '') { continue; }
    $m = git($ROOT, 'merge-base --is-ancestor ' . escapeshellarg($it['sha']) . ' HEAD && echo IN');
    if (strpos($m, 'IN') === false) { $offBranch[] = $it['id'] . ' (' . $it['sha'] . ')'; }
}
chk(empty($offBranch),
    'وكلُّ التزامٍ **سلفٌ لرأسِ الفرعِ الحاليّ** — لا دليلَ من بناءٍ آخر',
    empty($offBranch) ? "الفرع: {$branch}" : implode(' · ', array_slice($offBranch, 0, 3)));

if ($neg) {
    /* ◆ الحزامُ يقيس **قدرةَ الفحصِ على الرفض** بهاشٍ لا وجودَ له وبملفٍّ لاحق */
    echo "\n── الحزامُ السالب ──\n";
    $fake = 'deadbeef';
    $t = git($ROOT, 'cat-file -t ' . escapeshellarg($fake));
    chk($t !== 'commit', '**بصمةٌ لا وجودَ لها تُرفض**', "cat-file ⇒ " . mb_substr($t, 0, 40));

    /* ملفٌّ أُنشئ الآن لا يمكن أن يكون موجودًا في التزامٍ سابق */
    $probe = $ROOT . '/Reports/_gov005_belt.php';
    file_put_contents($probe, "<?php // حزامُ FR-GOV-005 — يُكنس فورًا\n");
    clearstatcache();
    if (!is_file($probe)) { echo "  ⛔ **لم يُدَسَّ الحزام** — أُوقِف\n"; exit(1); }
    echo "  ◆ دُسَّ ملفٌّ جديدٌ — **ووجودُه مُثبَتٌ قبلَ القياس**\n";
    $head = git($ROOT, 'rev-parse --short HEAD');
    $o = git($ROOT, 'cat-file -e ' . escapeshellarg($head . ':Reports/_gov005_belt.php') . ' && echo YES');
    chk(strpos($o, 'YES') === false,
        'و**ملفٌّ لم يكن في الالتزامِ يُرفض** — فالدليلُ لا يسبق نفسَه',
        'HEAD=' . $head . ' ⇒ ' . (strpos($o, 'YES') === false ? 'غيرُ موجودٍ فيه ✔' : 'موجودٌ ✘'));
    @unlink($probe);
    clearstatcache();
    chk(!is_file($probe), 'وكُنس الحزامُ أثرَه');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
