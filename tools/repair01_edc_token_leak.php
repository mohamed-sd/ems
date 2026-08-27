<?php
/**
 * tools/repair01_edc_token_leak.php — رمزٌ تقنيٌّ يصل إلى عينِ المستخدم
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**: أصلح `$typetext` **وابحث عن النظائر**، والهدف
 * `TECHNICAL_TOKEN_IN_UI_LABEL = 0` ⛔ «**ولا نعتبر الحارسَ مكتملًا حتى يثبت
 * أنّه يستطيع الرسوب**».
 *
 * ◆ **والاسمُ لا يكفي دليلًا**: `$typetext` في `Timesheet/aprovment.php` **مُستبدَلٌ
 *   صحيحًا** (`<?= $typetext ?>` و`"… $typetext"`) — فالبحثُ عن السلسلةِ يعطي
 *   ستَّ ضربات **كلُّها سليمة**. **والتسرُّبُ سلوكٌ لا سلسلة**: رمزٌ يبقى حرفيًّا
 *   لأنّه **خارجَ وسمِ PHP** أو **داخلَ سلسلةٍ مفردةِ الاقتباس**.
 *
 * ⚠ ويستثني الكاشفُ: `$` في CSS/JS، و`'…'` في `require`/`define`، والنسخَ
 *   الاحتياطيّةَ في `storage/backups`، و`vendor/`.
 *
 * التشغيل: php tools/repair01_edc_token_leak.php [--fix]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* ⚠ **وفصلُ PHP عن HTML بتعبيرٍ نمطيٍّ يقيس تقريبي لا الكود**: نسختايَ الأولى
     والثانيةُ أعطتا 2222 ثمَّ 939 ضربةً — كلُّها من ملفاتِ PHP خالصةٍ ظنَّها
     النمطُ نصَّ HTML. **فالمُحلِّلُ الوحيدُ الموثوقُ مُحلِّلُ PHP نفسُه**:
     `token_get_all()` يعطي `T_INLINE_HTML` **تعريفًا لا تخمينًا** لما يقع
     خارجَ وسمِ PHP. وهذا هو النصُّ الذي يصل إلى المتصفّحِ حرفيًّا. */

$hits = array(); $news = array(); $scanned = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $f = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    foreach (array('/.git/', '/vendor/', '/node_modules/', '/storage/backups/',
                   '/tools/', '/tests/', '/database/', '/docs/') as $skip) {
        if (strpos($f, $skip) !== false) { continue 2; }
    }
    $scanned++;
    $c = (string) @file_get_contents($f);
    $toks = @token_get_all($c);
    if (!is_array($toks)) { continue; }
    /* ⚠ **وكتلةُ السكربتِ تمتدُّ عبر عدّةِ رموزٍ يقطعها `<?= ?>`** — فنزعُها
         داخلَ الرمزِ الواحدِ يفشل، وأعطى **153 ضربةً كلُّها جافاسكربت**.
         **فالحالةُ تُتتبَّع عبر الرموزِ لا داخلَ الواحد.** */
    $inJs = false;
    foreach ($toks as $t) {
        if (!is_array($t) || $t[0] !== T_INLINE_HTML) { continue; }
        $h = ''; $rest = $t[1];
        while ($rest !== '') {
            if ($inJs) {
                $e = preg_match('~</(script|style)\b[^>]*>~i', $rest, $mm, PREG_OFFSET_CAPTURE);
                if (!$e) { $rest = ''; break; }
                $rest = substr($rest, $mm[0][1] + strlen($mm[0][0])); $inJs = false;
            } else {
                $e = preg_match('~<(script|style)\b[^>]*>~i', $rest, $mm, PREG_OFFSET_CAPTURE);
                if (!$e) { $h .= $rest; $rest = ''; break; }
                $h .= substr($rest, 0, $mm[0][1]);
                $rest = substr($rest, $mm[0][1] + strlen($mm[0][0])); $inJs = true;
            }
        }
        /* ◆ **ورمزٌ معروضٌ رمزًا ليس تسرُّبًا**: `<code>$_SESSION['super_admin']</code>`
             في شرحِ صفحةِ الإعداداتِ **مقصودٌ ومُعلَّمٌ بوسمِ الشِّفرة** — والتسرُّبُ
             أن يظهر الرمزُ **حيث يُنتظر اسمٌ بشريّ**. ⛔ ولا يُمحى بل يُفصل خبرًا. */
        $doc = ''; $lbl = $h;
        if (preg_match_all('~<(code|pre|kbd|samp)\b[^>]*>(.*?)</(?:code|pre|kbd|samp)>~si', $h, $cm)) {
            $doc = implode(' ', $cm[2]);
            $lbl = preg_replace('~<(code|pre|kbd|samp)\b[^>]*>.*?</(?:code|pre|kbd|samp)>~si', '', $h);
        }
        if (preg_match_all('~(?<![\w$])\$([a-z_][a-z_0-9]{2,})~i', $lbl, $m)) {
            foreach ($m[1] as $v) { $hits[] = array(substr($f, strlen($ROOT) + 1), '$' . $v, (int) $t[2]); }
        }
        if ($doc !== '' && preg_match_all('~(?<![\w$])\$([a-z_][a-z_0-9]{2,})~i', $doc, $m)) {
            foreach ($m[1] as $v) { $news[] = array(substr($f, strlen($ROOT) + 1), '$' . $v, (int) $t[2]); }
        }
    }
}

echo "\n═══ رمزٌ تقنيٌّ في نصِّ الواجهة — بمُحلِّلِ PHP لا بنمطٍ ═══\n";
printf("  ملفاتُ إنتاجٍ مسحت: %d\n", $scanned);
printf("  **TECHNICAL_TOKEN_IN_UI_LABEL = %d**\n\n", count($hits));
$by = array();
foreach ($hits as $h) { $by[$h[0]][] = $h; }
foreach ($by as $file => $hs) {
    printf("  %s (%d)\n", $file, count($hs));
    foreach (array_slice($hs, 0, 5) as $h) { printf("     · %-24s س%d\n", $h[1], $h[2]); }
}
printf("
  ◆ خبرٌ خارجَ الحكم — رمزٌ داخلَ وسمِ شِفرةٍ (‏شرحٌ مقصود): %d
", count($news));
foreach ($news as $x) { printf("     · %-22s %s س%d
", $x[1], $x[0], $x[2]); }
if (!$hits) { echo "
  ✔ **صفر** — ولا رمزَ تقنيٍّ يصل إلى عينِ المستخدم\n"; }
exit(count($hits) > 0 ? 1 : 0);
