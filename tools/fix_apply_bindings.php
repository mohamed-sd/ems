<?php
/**
 * tools/fix_apply_bindings.php — يُثبِّت شواهدَ الأحكامِ في ترويساتِ الفواحص
 * ═══════════════════════════════════════════════════════════════════════════
 * **لماذا**: `tools/fix_progress_report.php` يقيس نسبةَ المنجَزِ بأن يبحث عن
 * معرِّفِ الحكمِ في مِسبارٍ أو فاحص. و**619 حكمًا من 619 كان 616 منها بلا ذكرٍ**
 * في أيِّ فاحصٍ — لا لأنها لم تُنفَّذ بل لأنَّ المُنفَّذَ لم يُوسَم بمعرِّفِ حكمِه.
 * فهذه الأداةُ تُثبِّت الوسمَ في موضعِه الصحيح: ترويسةُ الفاحصِ الذي يفحصه.
 *
 * ── والوسمُ لا يُلصَق بالحدس ─────────────────────────────────────────────────
 * كلُّ ربطٍ هنا مرَّ بطورين: اقتراحٌ بدليلٍ منقولٍ (نصُّ اختبارِ القبولِ + **سطرُ
 * التحقُّقِ من الفاحصِ حرفيًّا برقمِه**)، ثم **مراجعةٌ خصمٌ** سؤالُها واحدٌ:
 * «لو أُفسد ما يشترطه هذا الحكمُ، أيرسبُ هذا الفاحصُ **يقينًا**؟» — وما دونَ
 * اليقينِ قُتل. ومن 104 مقترحاتٍ **قُتل 84 ونجا 20**.
 *
 * ◆ والحجّةُ الكاملةُ لكلِّ ربطٍ **ومَقتلُ كلِّ مرفوضٍ** تُكتب في
 *   `docs/fix_progress/BINDINGS.md` — فمن يُخالفني يستطيع أن ينقض ربطًا بعينِه
 *   دون أن يهدم القياسَ كلَّه، ويرى ما رُفض لا ما قُبل وحدَه.
 * ◆ ولا يُمَسُّ سطرٌ برمجيٌّ: الإضافةُ في التعليقِ حصرًا.
 * ◆ وعاطلةٌ: فاحصٌ يحمل وسمَه سلفًا لا يُعاد وسمُه.
 *
 * التشغيل: php tools/fix_apply_bindings.php <bindings.json> [--dry-run]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

$src = null;
$DRY = in_array('--dry-run', $argv, true);
foreach (array_slice($argv, 1) as $a) { if (strpos($a, '--') !== 0) { $src = $a; } }
if ($src === null || !is_file($src)) {
    exit("الاستخدام: php tools/fix_apply_bindings.php <bindings.json> [--dry-run]\n");
}
$data = json_decode((string) file_get_contents($src), true);
if (!is_array($data) || !isset($data['kept'])) { exit("✘ ملفُّ روابطَ غيرُ صالح\n"); }

$MARK = '⇐ شواهدُ أحكامٍ';
$stamp = date('Y-m-d');

/* ── ① تجميعُ الروابطِ لكلِّ فاحصٍ — فقد يحمل الفاحصُ أحكامًا عدة ─────────────── */
$byFile = array();
foreach ($data['kept'] as $b) {
    if (empty($b['test_file']) || empty($b['ruling_ids'])) { continue; }
    /* ◆ **مسارٌ مطلقٌ يصل من فاحصٍ فرعيٍّ فيتضاعف**: أرجع اثنان مسارًا كاملًا
         (`C:/wamp64/www/ems/tests/…`) فصار `$ROOT . '/' . $rel` مسارًا مزدوجًا
         فقُرئ «فاحصٌ غائبٌ» وهو موجود. فتُنزَع بادئةُ الجذرِ إن وُجدت. */
    $f = str_replace('\\', '/', trim($b['test_file']));
    $rootNorm = str_replace('\\', '/', $ROOT) . '/';
    if (strpos($f, $rootNorm) === 0) { $f = substr($f, strlen($rootNorm)); }
    if (preg_match('~^[A-Za-z]:/~', $f) && ($p = strpos($f, '/tests/')) !== false) {
        $f = substr($f, $p + 1);
    }
    if (!isset($byFile[$f])) { $byFile[$f] = array('ids' => array(), 'why' => array()); }
    foreach ($b['ruling_ids'] as $id) { $byFile[$f]['ids'][trim($id)] = true; }
    if (!empty($b['why'])) { $byFile[$f]['why'][] = trim($b['why']); }
}
fwrite(STDOUT, '── ① فواحصُ تحمل ربطًا: ' . count($byFile) . "\n");

/* ── ② التطبيقُ — في التعليقِ حصرًا، وبفحصِ المُرجَع ─────────────────────────── */
$applied = 0; $already = 0; $missing = 0; $failed = 0;
foreach ($byFile as $rel => $info) {
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDOUT, '   ✘ فاحصٌ غائبٌ: ' . $rel . "\n");
        $missing++;
        continue;
    }
    $body = (string) file_get_contents($path);
    if (mb_strpos($body, $MARK) !== false) { $already++; continue; }

    $ids = array_keys($info['ids']);
    sort($ids);
    $line = ' * ' . $MARK . ': ' . implode(' · ', $ids)
          . "\n * (رُبطت بمراجعةٍ خصمٍ " . $stamp . ' — الحجّةُ وسببُ قبولِها في '
          . "docs/fix_progress/BINDINGS.md)\n";

    /* موضعُ الإدراج: قبلَ إغلاقِ أوّلِ كتلةِ توثيقٍ — فلا يُمَسُّ كودٌ */
    $pos = strpos($body, '*/');
    if ($pos === false || $pos > 6000) {
        /* لا كتلةَ توثيقٍ في الرأسِ: يُدرَج تعليقٌ مستقلٌّ بعد <?php */
        $php = strpos($body, '<?php');
        if ($php === false) { $failed++; continue; }
        $ins = "\n/*\n" . $line . " */\n";
        $new = substr($body, 0, $php + 5) . $ins . substr($body, $php + 5);
    } else {
        $new = substr($body, 0, $pos) . $line . substr($body, $pos);
    }
    if ($DRY) { $applied++; continue; }
    $ok = file_put_contents($path, $new);
    if ($ok === false) { fwrite(STDOUT, '   ✘ كتابةٌ فشلت: ' . $rel . "\n"); $failed++; continue; }
    /* ◆ يُفحَص الأثرُ لا يُفترَض: أيحمل الملفُّ الوسمَ فعلًا وأصياغتُه سليمة؟ */
    $chk = (string) file_get_contents($path);
    if (mb_strpos($chk, $MARK) === false) { fwrite(STDOUT, '   ✘ الوسمُ لم يُكتب: ' . $rel . "\n"); $failed++; continue; }
    $o = array(); $c = 0;
    @exec('"' . PHP_BINARY . '" -l "' . $path . '" 2>&1', $o, $c);
    if ($c !== 0) {
        /* ردٌّ فوريٌّ: صياغةٌ انكسرت ⇒ يُستعاد الأصلُ ولا يُترك فاحصٌ معطوب */
        file_put_contents($path, $body);
        fwrite(STDOUT, '   ✘ صياغةٌ انكسرت فاستُعيد الأصلُ: ' . $rel . "\n");
        $failed++;
        continue;
    }
    $applied++;
}
fwrite(STDOUT, '── ② طُبِّق ' . $applied . ' · موسومٌ سلفًا ' . $already
             . ' · غائبٌ ' . $missing . ' · فشل ' . $failed
             . ($DRY ? "   (جولةٌ جافّة)\n" : "\n"));

/* ── ③ سجلُّ الحجّةِ — القبولُ والرفضُ معًا ─────────────────────────────────── */
if (!$DRY) {
    $md = "# شواهدُ الأحكامِ — حجّةُ كلِّ ربطٍ ومَقتلُ كلِّ مرفوض\n\n";
    $md .= '> مُطابَقةٌ آليةٌ بمراجعةٍ خصمٍ · ' . $stamp . "\n";
    $md .= '> **نجا ' . count($data['kept']) . ' ربطًا · قُتل '
         . count(isset($data['killed']) ? $data['killed'] : array()) . "**\n\n";
    $md .= "## القاعدةُ التي حكمت\n\n";
    $md .= "سؤالٌ واحدٌ لكلِّ ربطٍ: **لو أُفسد ما يشترطه هذا الحكمُ، أيرسبُ هذا الفاحصُ يقينًا؟**\n";
    $md .= "ما دونَ اليقينِ قُتل — ومنه ربطٌ بتشابهِ كلماتٍ، وربطٌ سطرُ تحقُّقِه لا يقيس ما يُدَّعى،\n";
    $md .= "وحكمٌ **عامٌّ** رُبط بفاحصٍ يفحص **حالةً واحدةً** فلا يُثبت العامَّ.\n\n";
    $md .= "> ونسبةٌ مصنوعةٌ بربطٍ متسامحٍ أسوأُ من نسبةٍ منخفضة — لأنها تُخفي عملًا لم يُنجَز.\n\n";
    $md .= "## ① الروابطُ التي نجت\n\n";
    foreach ($data['kept'] as $b) {
        if (empty($b['test_file'])) { continue; }
        $md .= '### `' . $b['test_file'] . '` ⇐ ' . implode(' · ', (array) $b['ruling_ids']) . "\n\n";
        $md .= (isset($b['why']) ? $b['why'] : '—') . "\n\n";
    }
    $md .= "## ② الروابطُ التي قُتلت — وهي أثمنُ ما في هذا السجل\n\n";
    $md .= "تُنشر كي يُرى ما رُفض لا ما قُبل وحدَه؛ ومن رأى قتلًا غيرَ صائبٍ نقضَه بعينِه.\n\n";
    foreach ((isset($data['killed']) ? $data['killed'] : array()) as $b) {
        if (empty($b['test_file'])) { continue; }
        $md .= '- **`' . $b['test_file'] . '`** ⇍ ' . implode(' · ', (array) $b['ruling_ids'])
             . ' — ' . (isset($b['reason']) ? $b['reason'] : '—') . "\n";
    }
    $dst = $ROOT . '/docs/fix_progress/BINDINGS.md';
    if (!is_dir(dirname($dst))) { @mkdir(dirname($dst), 0777, true); }
    file_put_contents($dst, $md);
    fwrite(STDOUT, '── ③ سجلُّ الحجّةِ: docs/fix_progress/BINDINGS.md ('
                 . number_format(strlen($md)) . " بايتًا)\n");
}

fwrite(STDOUT, "\n✅ اكتمل. أعِد القياسَ: php tools/fix_progress_report.php --live\n");
exit(0);
