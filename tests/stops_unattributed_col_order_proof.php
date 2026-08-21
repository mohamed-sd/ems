<?php
/**
 * tests/stops_unattributed_col_order_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP: عمودُ «الإسناد» أوّلُ عمودٍ في Operations/stops_unattributed.php
 * — من الشاشةِ المُصيَّرةِ لا من المصدر (فالنصُّ لا يُظهر ما تحقنه JS ولا ما
 *   تفعله الفروع). أربعةُ أحكام:
 *   ① أوّلُ رأسٍ عارٍ (غيرُ ems-gov-th/ems-fn-th) هو «الإسناد».
 *   ② أوّلُ خليةٍ في كلِّ صفِّ بياناتٍ تحمل نموذجَ الإسناد (select + زرّ «أسند»).
 *   ③ عددُ خلايا الصفِّ = عددُ الرؤوسِ العاريةِ (٧) — فلا انزياحَ ولا فقد.
 *   ④ لا فقدَ في المحتوى: التاريخُ والوردية والساعاتُ ما تزال حاضرةً بترتيبها.
 * التشغيل: php tests/stops_unattributed_col_order_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

$BASE = 'http://localhost/ems';
$J = sys_get_temp_dir() . '/stu_col_' . getmypid() . '.jar';
$P = 0; $F = 0;
function ok($m)  { global $P; $P++; echo "  ✔ {$m}\n"; }
function bad($m) { global $F; $F++; echo "  ✘ FAIL: {$m}\n"; }
function chk($c, $m, $why = '') { $c ? ok($m) : bad($m . ($why ? " — {$why}" : '')); }

$rq = function ($u, $post = null) use ($J) {
    $ch = curl_init($u);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => $post !== null));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};

echo "── دخولٌ وتصييرٌ حيّ\n";
$lp = $rq($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $lp, $tk);
$rq($BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678',
                                'csrf_token' => isset($tk[1]) ? $tk[1] : ''));
$html = $rq($BASE . '/Operations/stops_unattributed.php');
chk(mb_strpos($html, 'التوقفات') !== false, 'الشاشةُ صُيِّرت (لا تحويلٌ إلى login)');

/* ── تجريدُ الشجرةِ بـDOM لا بنمط (uxui01-reverse-audit) ─────────────────── */
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xp = new DOMXPath($dom);

$tbl = null;
foreach ($xp->query('//table') as $t) {
    if ($xp->query('.//th[contains(text(),"الإسناد")]', $t)->length) { $tbl = $t; break; }
}
if (!$tbl) { bad('لم يُعثر على الجدولِ الهدفِ في المُصيَّر'); echo "\nPASS={$P} FAIL={$F}\n"; exit(1); }

/* الرؤوسُ العاريةُ وحدَها — رؤوسُ الحوكمةِ والوظيفةِ لا خلايا لها في المصدر */
$bare = array();
foreach ($xp->query('.//thead//th', $tbl) as $th) {
    $cls = (string) $th->getAttribute('class');
    if (strpos($cls, 'ems-gov-th') !== false || strpos($cls, 'ems-fn-th') !== false) { continue; }
    $bare[] = trim(preg_replace('~\s+~u', ' ', $th->textContent));
}

echo "\n── ① الرأسُ المتصدِّر\n";
chk(isset($bare[0]) && $bare[0] === 'الإسناد',
    'أوّلُ رأسٍ عارٍ = «الإسناد»', 'المقيس: «' . (isset($bare[0]) ? $bare[0] : '—') . '»');
chk($bare === array('الإسناد', '#', 'التاريخ', 'أثر أجر المشغّل', 'الوردية', 'ساعاتُ التعطل', 'النوع'),
    'وترتيبُ الرؤوسِ الباقيةِ لم يتبدّل', 'المقيس: ' . implode(' | ', $bare));

echo "\n── ②③ الخلايا في الصفوف\n";
$dataRows = 0; $firstIsForm = 0; $widthOk = 0; $seenDate = 0;
foreach ($xp->query('.//tbody/tr', $tbl) as $tr) {
    $tds = $xp->query('./td', $tr);
    if ($tds->length === 1) { continue; } /* صفُّ «صفرُ توقفٍ» ذو colspan */
    $dataRows++;
    if ($tds->length === count($bare)) { $widthOk++; }
    $c0 = $tds->item(0);
    if ($c0 && $xp->query('.//select[@name="fault_department"]', $c0)->length
            && $xp->query('.//button', $c0)->length) { $firstIsForm++; }
    $c2 = $tds->item(2);
    if ($c2 && preg_match('~\d{4}-\d{2}-\d{2}~', $c2->textContent)) { $seenDate++; }
}
echo "  · صفوفُ بياناتٍ مقيسة: {$dataRows}\n";
if ($dataRows === 0) {
    bad('صفرُ صفِّ بياناتٍ — القياسُ خواء؛ لا تُصدِّقْ أخضرَ على جدولٍ فارغ');
} else {
    chk($firstIsForm === $dataRows, "أوّلُ خليةٍ = نموذجُ الإسناد في {$firstIsForm}/{$dataRows}");
    chk($widthOk === $dataRows, "عرضُ الصفِّ = " . count($bare) . " خليةً في {$widthOk}/{$dataRows}");
    chk($seenDate === $dataRows, "والتاريخُ في العمودِ ٣ (إزاحةٌ متّسقة) في {$seenDate}/{$dataRows}");
}

echo "\n── ④ سلامةُ النموذجِ بعد النقل\n";
chk(substr_count($html, 'name="assign_ts"') === $dataRows || $dataRows === 0,
    'حقلُ assign_ts مرّةً لكلِّ صفّ');
chk(mb_strpos($html, 'csrf_token') !== false, 'ورمزُ CSRF ما يزال محقونًا في النموذج');

echo "\n═══ PASS={$P} · FAIL={$F} ═══\n";
@unlink($J);
exit($F ? 1 : 0);
