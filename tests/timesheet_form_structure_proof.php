<?php
/**
 * tests/timesheet_form_structure_proof.php — برهانٌ حيٌّ: بنيةُ نموذجِ التايم شيت
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **لماذا برهانٌ حيٌّ لا فحصٌ ساكن**
 *   العطبُ الذي عولج في 2026-08-17 لا يظهر في المصدرِ بالنظر: كتلةٌ لُصقت
 *   مرّتين وتُركت بـ`<div>`ين مفتوحَين. PHP لا تشتكي، والصفحةُ تُردُّ بحالةِ
 *   200، وأيُّ فاحصٍ يعدُّ الأسطرَ يمرّ — لأن الخللَ في **مكدَّسِ الوسومِ كما
 *   يبنيه المحلِّل**، لا في نصِّ الملف.
 *
 *   وأثرُه كان مقيسًا: `</form>` تُنهي عنصرَ النموذجِ ولا تُغلق ما بقي مفتوحًا
 *   من `<div>`، فصار **جدولُ التايم شيت كلُّه ابنًا للنموذج** — و`.allforms`
 *   مطويٌّ افتراضًا، فالجدولُ لا يُرى إلا بفتحِ نموذجِ الإدخال. وبقيةُ الحقولِ
 *   انحشرت في عمودٍ عرضُه ٢٦٦ بكسلًا وطولُه ٣٩٤٢.
 *
 * ◆ فيُقاس هنا **مخرَجُ الخادمِ نفسُه** بمُحلِّلِ DOM، للأنواعِ الثلاثةِ معًا
 *   (`?type=1|2|3` — ثلاثةُ فروعٍ مستقلةٍ في الملفِّ نفسِه، ولا يُصيَّر منها
 *   إلا واحدٌ في كلِّ طلب، فالفحصُ على واحدٍ يُعمي عن الآخرَين):
 *     ① الجدولُ **خارجَ** النموذج (وإلا اختفى بطيِّه).
 *     ② لا معرِّفَ مكرَّرٌ — والتكرارُ هنا ليس ترفًا: الجافاسكربت يكتب في
 *        الأولِ (`getElementById`) وPHP تقرأ الأخيرَ (`$_POST`)، فيُرسَل غيرُ
 *        ما كُتب صامتًا.
 *     ③ لا خليةَ شبكةٍ فارغة (`<div></div>` مخلَّفةٌ من تخطيطٍ قديم).
 *     ④ لا ترميزَ مشوَّهًا في النصِّ المعروض.
 *     ⑤ الكتلُ العريضةُ تُعلن امتدادَها بصنفِ المكوّنِ المشترك `form-grid-full`
 *        — فـ`grid-column` لاغيةٌ في الشبكةِ المرنةِ الموحَّدة.
 *
 * ◆ **وقد جُرِّب معطوبًا قبلَ تصديقِ مرورِه** (2026-08-17): أُعيدت النسخةُ
 *   السابقةُ فرسب بتسعِ إخفاقاتٍ — ١٠ معرِّفاتٍ مكرَّرةٍ في النوع ١ والجدولُ
 *   داخلَ النموذج، و١٢ خليةً فارغةً في النوع ٢ — ثم مرَّ بصفرٍ على المصلَحة.
 *   فبوابةٌ لم تُجرَّب معطوبةً خضراءُ أبدًا ولا تُصدَّق.
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يقيس لونًا محسوبًا ولا عرضًا
 *   مُصيَّرًا (يلزمه متصفحٌ حقيقيّ)، ولا يفتح نموذجَ التعديلِ بمعرِّفِ سجلّ.
 *
 * ◆ يتطلّب Apache حيًّا على http://localhost/ems
 *   php tests/timesheet_form_structure_proof.php [اسم المستخدم]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$USER = isset($argv[1]) ? $argv[1] : 'محمد';   // مالكُ مسارِ Timesheet/ — tools/uxw_accounts.txt
$PASS = 0; $FAIL = 0;

function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', ''); }
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
}

function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "   \xE2\x9C\x94 {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "   \xE2\x9C\x96 {$m}\n"); }

echo "\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90 برهانٌ حيٌّ · بنيةُ نموذجِ التايم شيت \xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n\n";

$jar = sys_get_temp_dir() . '/ems_ts_proof_' . md5($USER) . '.jar';
if (file_exists($jar)) { @unlink($jar); }
list($c0) = req($BASE . '/login.php', $jar);
if ($c0 === 0) { exit("\xE2\x9C\x96 Apache لا يستجيب على {$BASE} — البرهانُ الحيُّ متعذِّر\n"); }
list(, , $lb) = req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lb, $m);
list($lc) = req($BASE . '/login.php', $jar, array(
    'username' => $USER, 'password' => '12345678', 'csrf_token' => isset($m[1]) ? $m[1] : ''));
if ($lc !== 200 && $lc !== 302) { exit("\xE2\x9C\x96 تعذّر الدخولُ بـ«{$USER}» — HTTP {$lc}\n"); }

foreach (array(1, 2, 3) as $t) {
    echo "\xE2\x94\x80\xE2\x94\x80 نوعُ الآلية {$t}\n";
    list($code, , $html) = req($BASE . '/Timesheet/timesheet.php?type=' . $t, $jar);
    if ($code !== 200) { bad("الصفحةُ ردَّت HTTP {$code}"); continue; }

    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $x = new DOMXPath($d);

    /* ① الجدولُ خارجَ النموذجِ المطويّ */
    $tbl = $d->getElementById('projectsTable');
    if (!$tbl) {
        bad('جدولُ التايم شيت غيرُ موجودٍ في المُصيَّر');
    } elseif ($x->query('ancestor::form', $tbl)->length) {
        bad('الجدولُ داخلَ النموذج — يختفي بطيِّه (وسومٌ غيرُ متوازنةٍ قبلَه)');
    } else {
        ok('الجدولُ خارجَ النموذج فيُرى والنموذجُ مطويّ');
    }

    /* ② لا معرِّفَ مكرَّر */
    $ids = array();
    foreach ($x->query('//*[@id]') as $e) { $ids[] = $e->getAttribute('id'); }
    $dups = array_keys(array_filter(array_count_values($ids), function ($c) { return $c > 1; }));
    if ($dups) {
        bad('معرِّفاتٌ مكرَّرة (' . count($dups) . '): ' . implode(' · ', array_slice($dups, 0, 12)));
    } else {
        ok('لا معرِّفَ مكرَّرًا — فما يكتبه المستخدمُ هو ما يُرسَل');
    }

    /* ③④⑤ شبكةُ الحقول */
    $form = $x->query('//form[contains(@class,"allforms")]')->item(0);
    $grid = $form ? $x->query('.//div[contains(@class,"form-grid")]', $form)->item(0) : null;
    if (!$grid) {
        bad('لا شبكةَ حقولٍ موحَّدةً (.form-grid) داخلَ النموذج');
    } else {
        $kids  = $x->query('./*', $grid)->length;
        $full  = $x->query('./*[contains(@class,"form-grid-full")]', $grid)->length;
        $empty = $x->query('./div[not(node())]', $grid)->length;
        if ($empty) { bad("خلايا شبكةٍ فارغة: {$empty} — فجواتٌ بيضاءُ بين الحقول"); }
        else        { ok('لا خليةَ شبكةٍ فارغة'); }
        if ($full)  { ok("الكتلُ العريضةُ تُعلن امتدادَها بالمكوّنِ المشترك: {$full} من {$kids}"); }
        else        { bad("لا كتلةَ تُعلن `form-grid-full` — العريضُ سيُحشر في عمودِ حقل ({$kids} ابنًا)"); }
    }

    /* ④ ترميزٌ مشوَّه — النمطُ بهروبِ \x{} كي لا يحملَ الملفُّ بايتاتِ المشوَّهِ نفسَه */
    if (preg_match('~\x{00C3}\x{00B0}|\x{00C5}\x{00B8}|\x{00E2}\x{20AC}\x{0153}|\x{00F0}\x{0178}~u', $html)) { bad('ترميزٌ مشوَّهٌ في النصِّ المعروض'); }
    else { ok('لا ترميزَ مشوَّهًا'); }
}

echo "\n";
echo $FAIL === 0
    ? "\xE2\x9C\x94 صفرُ إخفاقٍ في {$PASS} فحصًا — الأنواعُ الثلاثةُ سليمةُ البنية\n"
    : "\xE2\x9C\x96 إخفاقات: {$FAIL} · ناجحة: {$PASS}\n";
exit($FAIL === 0 ? 0 : 1);
