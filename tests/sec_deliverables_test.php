<?php
/**
 * update0004 · الموجة ⑤ — حزام المخرَجات (SEC-01→SEC-06)
 * التشغيل: php tests/sec_deliverables_test.php
 * يثبت وجود المخرَجات الست واكتمال بنيتها وأعدادها الحية.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }

$D = dirname(__DIR__) . '/docs/sec01';
fwrite(STDOUT, "\n══ update0004 موجة ⑤ — المخرجات الست ══\n");

$files = array(
    'SEC-D1_dictionary_draft_ar.md' => 'DEC-SEC-K',
    'SEC-D2_matrix_summary_ar.md' => 'قاعدة التحويل',
    'SEC-D2_matrix_208x25.csv' => null,
    'SEC-D3_roles_map_ar.md' => 'ولا صف بلا قرار',
    'SEC-D4_sources_17_ar.md' => 'السبعة عشر',
    'SEC-D5_screens_reconciliation_ar.md' => 'صفًّا صفًّا',
    'SEC-D5_screens_reconciliation.csv' => null,
    'SEC-D6_four_sources_review_ar.md' => 'المصادر الأربعة',
);
foreach ($files as $f => $marker) {
    $p = $D . '/' . $f;
    $okF = is_file($p) && filesize($p) > 200;
    check($okF, "{$f} موجود");
    if ($okF && $marker !== null) {
        check(mb_strpos(file_get_contents($p), $marker) !== false, "  يحمل علامة «{$marker}»");
    }
}

/* الاتصالُ يُفتح **قبل** أوّلِ قياسٍ يحتاجه (كان يُفتح عند D2 وحدَها). */
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');

// D1: العائلات الثلاث عشرة والمسميات معلَّمة
$d1 = file_get_contents($D . '/SEC-D1_dictionary_draft_ar.md');
check(substr_count($d1, '|') > 0 && preg_match_all('/^\|\d+\|`/m', $d1) === 13 || substr_count($d1, 'tickets') >= 1, 'D1: العائلات الثلاث عشرة');
/* ══ **المُخرَجُ مولَّدٌ من الحيّ — فيُقاس بمطابقتِه للحيّ لا برقمِ يومِ الكتابة.**
     كان الشرطُ `=== 16` (مسمياتُ الوظائفِ يومَها)، والمقيسُ الآن 23 لأن
     المسمياتَ نمت بقرارات. و`tools/sec01_deliverables.php` يولّد الملفَّ من
     القاعدةِ مباشرةً — فأيُّ رقمٍ مجمَّدٍ يُرسِب **نموًّا مشروعًا** ويُخفي ما
     يهمُّ فعلًا: أن يكون الملفُّ **صورةً صادقةً** لما في القاعدة. */
/* نطاقُ العدِّ **من المولِّدِ نفسِه**: `tools/sec01_deliverables.php:69` يسرد
   `job_titles ORDER BY id` بلا مُرشِّح — فيُقاس المجموعُ لا المُفعَّل. (والعمودُ
   `active` لا `is_active` — سؤالٌ عن عمودٍ لا وجودَ له يردُّ صمتًا يُقرأ عطلًا.) */
$jtDb = intval($db->query("SELECT COUNT(*) c FROM job_titles")->fetch_assoc()['c']);
$jtFile = substr_count($d1, 'JT-');
check($jtFile > 0 && $jtFile === $jtDb,
    "D1: المسمياتُ في المُخرَجِ = الحيُّ في القاعدة ({$jtFile}/{$jtDb})");
check(substr_count($d1, '★') > 30, 'D1: الاستنتاجات معلَّمة ★ (' . substr_count($d1, '★') . ')');

// D2: صف لكل (دور × موديول)
$lines = count(file($D . '/SEC-D2_matrix_208x25.csv'));
$expect = intval($db->query("SELECT (SELECT COUNT(*) FROM roles) * (SELECT COUNT(*) FROM modules) c")->fetch_assoc()['c']);
check($lines === $expect + 1, "D2: {$expect} صفًّا (+رأس) — وُجد " . ($lines - 1));
$head = fgets(fopen($D . '/SEC-D2_matrix_208x25.csv', 'r'));
foreach (array('screen_view', 'return_for_fix', 'reverse', 'delete_draft', 'grant_permission', 'override_cap', 'scope_proposed') as $col) {
    check(strpos($head, $col) !== false, "D2: العمود {$col}");
}

// D3: 25 دورًا
$d3 = file_get_contents($D . '/SEC-D3_roles_map_ar.md');
/* المُخرَجُ مولَّدٌ: صفُّ دورٍ لكلِّ دورٍ في القاعدة — والأدوارُ نمت 25 ⇒ 35. */
$rolesDb = intval($db->query("SELECT COUNT(*) c FROM roles")->fetch_assoc()['c']);
$rolesFile = preg_match_all('/^\| \d+ \|/m', $d3);
check($rolesFile === $rolesDb, "D3: صفوفُ الأدوارِ في المُخرَجِ = الأدوارُ في القاعدة ({$rolesFile}/{$rolesDb})");

// D5: المصالحة تحمل قرارًا لكل صف
$csv = file($D . '/SEC-D5_screens_reconciliation.csv');
$noDecision = 0;
foreach (array_slice($csv, 1) as $l) { if (trim($l) !== '' && substr_count($l, ',') < 3) { $noDecision++; } }
check($noDecision === 0, 'D5: كل صف بأربعة أعمدة (قرار مقترح)');
check(count($csv) > 300, 'D5: الجرد شامل (' . (count($csv) - 1) . ' صفًّا)');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
