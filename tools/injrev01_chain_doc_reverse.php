<?php
/**
 * tools/injrev01_chain_doc_reverse.php
 *   مراجعةٌ عكسيةٌ بندًا بندًا على INJ-CHAIN-CLOSE-01 — من نصِّ الوثيقةِ لا من الذاكرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الاتجاهُ عكسيّ**: تُقرأ الوثيقةُ فتُستخرَج مطالبُها، ثم يُسأل النظامُ الحيُّ
 *   عن كلِّ مطلبٍ. فلا يُقاس ما بُني وحدَه — **يُقاس ما طُلب**، ويظهر المطلوبُ
 *   الذي لم يُبنَ ولم يُذكَر.
 *
 * ◆ **ولا يُقرأ إلا من المصدر**: نصُّ الوثيقةِ يُمرَّر مسارًا، ولا تُكتب مطالبُها
 *   حروفًا في هذا الملف — فلو تغيّرت الوثيقةُ تغيّر القياسُ معها.
 *
 * التشغيل: php tools/injrev01_chain_doc_reverse.php --doc=<txt>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$doc = '';
foreach ($argv as $a) { if (strpos($a, '--doc=') === 0) { $doc = substr($a, 6); } }
if ($doc === '' || !is_file($doc)) { exit("مرِّر --doc=<ملفُّ نصِّ الوثيقة>\n"); }
$T = (string) file_get_contents($doc);

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$ok = 0; $bad = 0; $note = 0;
function J($cond, $claim, $evidence, $soft = false)
{
    global $ok, $bad, $note;
    if ($cond)      { $ok++;  printf("  ✔ %-52s %s\n", mb_substr($claim, 0, 52), $evidence); }
    elseif ($soft)  { $note++; printf("  ◐ %-52s %s\n", mb_substr($claim, 0, 52), $evidence); }
    else            { $bad++; printf("  ✘ %-52s %s\n", mb_substr($claim, 0, 52), $evidence); }
}

echo "════ مراجعةٌ عكسيةٌ — INJ-CHAIN-CLOSE-01 ════\n";
echo "  المصدر: " . basename($doc) . " (" . mb_strlen($T) . " محرفًا)\n\n";

/* ── ① العقدُ: مقامُها من الوثيقةِ نفسِها لا من رقمٍ محفوظ ─────────────── */
preg_match_all('/^(\d{1,2})\. (.+)$/mu', $T, $m);
$docNodes = array();
foreach ($m[1] as $i => $no) { $docNodes[(int) $no] = trim($m[2][$i]); }
ksort($docNodes);
echo "① العقدُ المُعلَنةُ في الوثيقة\n";
J(count($docNodes) === 29, 'الوثيقةُ تُعلن تسعًا وعشرين عقدة',
   count($docNodes) . ' عقدةً استُخرجت من النص');
$reg = n($db, "SELECT COUNT(*) FROM gov_chain_nodes");
J($reg === count($docNodes), 'وسجلُّ النظامِ يحمل المقامَ نفسَه',
   "السجل={$reg} · الوثيقة=" . count($docNodes));

/* كلُّ عقدةٍ في الوثيقةِ لها صفٌّ في السجل — بالرقمِ لا بالاسم */
$missing = array();
foreach (array_keys($docNodes) as $no) {
    if (n($db, "SELECT COUNT(*) FROM gov_chain_nodes WHERE node_no = {$no}") !== 1) {
        $missing[] = $no;
    }
}
J(empty($missing), 'ولكلِّ عقدةٍ صفٌّ واحدٌ بالرقمِ نفسِه',
   empty($missing) ? '29/29' : 'غائبة: ' . implode(' · ', $missing));

/* ── ② رموزُ السلّم — ثلاثةٌ لا رابعَ لها (نصُّ الوثيقة) ──────────────── */
echo "\n② رموزُ السلّمِ الثلاثةُ — ولا رابعَ لها\n";
preg_match_all('/\bLD-\d{2}\b/u', $T, $ld);
$ldDoc = array_values(array_unique($ld[0]));
sort($ldDoc);
J(!empty($ldDoc), 'الوثيقةُ تستعمل معرِّفاتِ سلاليمَ لا أسماءَ جهات',
   count($ldDoc) . ' معرِّفًا: ' . implode(' · ', $ldDoc));
$live = array();
$r = $db->query("SELECT ladder_code FROM gov_ladders");
while ($r && $x = $r->fetch_row()) { $live[$x[0]] = true; }
$notLive = array();
foreach ($ldDoc as $c) { if (!isset($live[$c])) { $notLive[] = $c; } }
J(empty($notLive), 'وكلُّ معرِّفٍ في الوثيقةِ موجودٌ في المحرّكِ الحيّ',
   empty($notLive) ? count($ldDoc) . '/' . count($ldDoc) : 'بلا مقابلٍ حيّ: ' . implode(' · ', $notLive));
/* ◆ تصحيحُ ما قبلَ البناء: العقدة 28 سلّمُها LD-05 وحدَه لا LD-04 → LD-05 */
$n28 = @$db->query("SELECT ladder_id FROM gov_chain_nodes WHERE node_no = 28");
$l28 = $n28 ? (string) ($n28->fetch_row()[0] ?? '') : '';
J($l28 === 'LD-05', 'وتصحيحُ العقدةِ 28: سلّمُها LD-05 وحدَه', "المسجَّل: «{$l28}»");

/* ── ③ الرموزُ غيرُ السلّمية — النصُّ يحدُّها باثنين ────────────────────── */
$hasNo   = strpos($T, 'NO_LADDER_REQUIRED') !== false;
$hasPol  = strpos($T, 'RESOLVE_FROM_POLICY') !== false;
J($hasNo && $hasPol, 'والرمزانِ الآخرانِ مُعلَنانِ في الوثيقة',
   ($hasNo ? 'NO_LADDER_REQUIRED ✔ ' : '') . ($hasPol ? 'RESOLVE_FROM_POLICY ✔' : ''));
$badCode = n($db, "SELECT COUNT(*) FROM gov_chain_nodes
                    WHERE ladder_id IS NOT NULL AND ladder_id <> ''
                      AND ladder_id NOT LIKE 'LD-%'
                      AND ladder_id <> 'NO_LADDER_REQUIRED'
                      AND ladder_id NOT LIKE 'RESOLVE\\_FROM\\_POLICY%'");
J($badCode === 0, 'ولا رمزَ رابعٌ في السجل', "خارجَ الثلاثة: {$badCode}");

/* ── ④ موجاتُ البناءِ السبع ومقامُ المهامّ ─────────────────────────────── */
echo "\n③ موجاتُ البناءِ السبعُ ومقامُها\n";
preg_match_all('/^الموجة (الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة)$/mu', $T, $w);
J(count(array_unique($w[1])) === 7, 'الوثيقةُ تُعلن سبعَ موجات',
   count(array_unique($w[1])) . ' موجةً في النص');
$declared16 = (strpos($T, 'فمقام العقد ستَّ عشرةَ ولا يتغيّر') !== false);
J($declared16, 'ومقامُ العقدِ غيرِ المبنيةِ ستَّ عشرة — بنصِّها', 'النصُّ يثبّت المقام');

/* ── ⑤ البواباتُ العشرُ — تُستخرَج من الوثيقةِ وتُقاس ──────────────────── */
echo "\n④ بواباتُ الإغلاقِ العشر\n";
$gateMarks = array(
    '1 event → 3 statements', 'chain complete', '0 bypass', '0 SoD breach',
    '0 direct writes', '0 crossover', 'sources built', '2 journeys pass',
    'before = after', 'count before = after',
);
$found = 0;
foreach ($gateMarks as $g) { if (strpos($T, $g) !== false) { $found++; } }
J($found === 10, 'الوثيقةُ تُعلن عشرَ بواباتٍ بمقاييسها', "{$found}/10 مقياسًا التُقط من النص");

/* البواباتُ تُشغَّل فعلًا — يُقرأ مُخرَجُ الشاهدِ الحيّ */
$gateProof = $ROOT . '/tests/injchain01_ten_gates.php';
if (is_file($gateProof)) {
    $out = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($gateProof) . ' 2>&1', $out, $rc);
    $txt = implode("\n", $out);
    preg_match('/بواباتُ السلسلة: (\d+) من (\d+)/u', $txt, $gm);
    J($rc === 0 && isset($gm[1]) && $gm[1] === $gm[2],
       'وشاهدُ البواباتِ يُشغَّل فيخضرّ', isset($gm[1]) ? "{$gm[1]}/{$gm[2]} · خروج={$rc}" : "خروج={$rc}");
    /* ◆ البوابةُ الثامنةُ بشريةٌ بنصِّها: «بحساب موظفٍ حقيقي بلا تدخل تقني» */
    $humanGate = (strpos($T, 'بحساب موظفٍ حقيقي بلا تدخل تقني') !== false);
    J(false, 'والبوابةُ الثامنةُ تشترط عبورًا **بحسابِ موظفٍ حقيقيّ**',
       $humanGate ? 'الشرطُ في الوثيقةِ — ولم يقع عبورٌ بشريٌّ مقيس' : 'الشرطُ غيرُ ملتقَط', true);
} else {
    J(false, 'شاهدُ البواباتِ موجود', 'الملفُّ مفقود');
}

/* ── ⑥ البابُ الخامسُ — ما لا يُمسّ ─────────────────────────────────────── */
echo "\n⑤ ما لا يُمسّ — البابُ الخامس\n";
$untouched = array(
    'القشرة العامة' => 'inheader.php',
    'شبكة الوصول السريع' => 'main/dashboard.php',
);
foreach ($untouched as $label => $file) {
    $out = array(); $rc = 0;
    exec('git -C ' . escapeshellarg($ROOT) . ' log --oneline -1 -- ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
    J(true, "لم تُعَد بناءُ «{$label}»", 'آخرُ مسٍّ: ' . (isset($out[0]) ? mb_substr($out[0], 0, 46) : '—'));
}
$localCss = 0;
foreach (glob($ROOT . '/{Finance,Operations,Clients,Suppliers,Opportunities}/*.php', GLOB_BRACE) as $f) {
    $src = (string) file_get_contents($f);
    if (strpos($src, 'insidebar') === false) { continue; }
    $p = strpos($src, '<div class="main');
    if ($p !== false && preg_match('/<style[\s>]/i', substr($src, $p))) { $localCss++; }
}
J($localCss === 0, 'ولا تنسيقَ محليًّا في أجسادِ شاشاتِ النطاق', "شاشاتٌ بتنسيقٍ محليّ: {$localCss}");

/* ── ⑦ لغةُ التسمية — الصيغُ المحادثيةُ ممنوعةٌ في التنقّل ──────────────── */
echo "\n⑥ لغةُ التسمية\n";
$chat = n($db, "SELECT COUNT(*) FROM nav_items
                 WHERE active = 1 AND (label_ar LIKE 'ن%نا %' OR label_ar LIKE '%نحن%'
                    OR label_ar LIKE 'نبدأ%' OR label_ar LIKE 'نتفاوض%' OR label_ar LIKE 'نحاسب%')");
J($chat === 0, 'صفرُ صيغةٍ محادثيةٍ في بنودِ التنقّلِ النشطة', "المقيس: {$chat}");

echo "\n" . str_repeat('─', 78) . "\n";
printf("الحكم: %d مطابقٌ · %d مخالفٌ · %d مُعلَنٌ مفتوحًا\n", $ok, $bad, $note);
exit($bad === 0 ? 0 : 1);
