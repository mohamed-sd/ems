<?php
/**
 * tools/_uxw_csrf_probe.php — رصدُ نداءِ csrf_field() الواقعِ **داخلَ وسمِ HTML**
 * ═══════════════════════════════════════════════════════════════════════════
 * العلّة المقيسة (رصدتها ثلاثُ دفعاتِ ترحيلٍ مستقلّة):
 *
 *     <form ... class="allforms" style="<?= $edit ? '' : 'display:none;' ?>
 *         <?= csrf_field() ?>">
 *
 * السمةُ `style` لا تُغلَق في سطرِها، فمخرَجُ `csrf_field()` كلُّه — وهو وسمُ
 * `<input type="hidden" name="csrf_token" …>` — يصير **نصًّا داخلَ قيمةِ السمة**.
 * فالنموذجُ يُرسَل بلا رمزِ حماية (٤٠٣ تحتَ الإنفاذ)، وقيمةُ `style` تصير CSSًا
 * فاسدًا فيسقط النموذجُ على `.allforms { display:none }` — أي لا يظهر أصلًا.
 *
 * ولا يصلح regex بسيطٌ هنا: `?>` نفسُها تحوي `>` فتُنهي أيَّ `[^>]*`.
 * فالمسحُ يمشي حرفًا حرفًا من `<tag`: يقفز فوقَ كتلِ PHP كاملةً، ويتتبّع
 * الاقتباسَ المفتوح، حتى يبلغَ `>` الحقيقيَّ الذي يغلق الوسم. وكلُّ ذكرٍ
 * لـ`csrf_field` قبلَ ذلك الحدِّ عيبٌ — سواءٌ ابتلعته سمةٌ أو طُبع نصًّا.
 *
 * مسبارُ قراءةٍ فقط. لا يعدُّ عيبًا نداءً في سلسلةِ PHP مُوصولةٍ بـ`.` خارجَ وسم.
 */
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$hits = 0; $files = array();

/** يمسح من موضعِ `<tag` حتى `>` الذي يغلق الوسم، ويعيد [الحدّ, نصُّ الوسم] */
function tag_span(string $s, int $start): array
{
    $n = strlen($s); $i = $start + 1; $quote = '';
    while ($i < $n) {
        // كتلةُ PHP تُقفَز كاملةً — فعلامةُ إغلاقِها ليست إغلاقًا للوسم
        if ($s[$i] === '<' && $i + 1 < $n && $s[$i + 1] === '?') {
            $end = strpos($s, '?>', $i);
            if ($end === false) { return array($n, substr($s, $start)); }
            $i = $end + 2;
            continue;
        }
        $ch = $s[$i];
        if ($quote !== '') {
            if ($ch === $quote) { $quote = ''; }
        } elseif ($ch === '"' || $ch === "'") {
            $quote = $ch;
        } elseif ($ch === '>') {
            return array($i, substr($s, $start, $i - $start + 1));
        }
        $i++;
    }
    return array($n, substr($s, $start));
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/)#', $p)) continue;
    $s = @file_get_contents($f->getPathname());
    if ($s === false || strpos($s, 'csrf_field') === false) continue;
    $rel = str_replace($ROOT . '/', '', $p);

    if (!preg_match_all('/<(form|input|div|span|td|tr|table|section|button|a)\b/i', $s, $m, PREG_OFFSET_CAPTURE)) continue;
    foreach ($m[0] as $tagHit) {
        list($limit, $tagText) = tag_span($s, $tagHit[1]);
        $cp = strpos($tagText, 'csrf_field');
        if ($cp === false) continue;
        $line = substr_count(substr($s, 0, $tagHit[1]), "\n") + 1;
        echo $rel, ':', $line, "\n    ", preg_replace('/\s+/', ' ', mb_substr($tagText, 0, 190)), "\n";
        $hits++; $files[$rel] = true;
    }
}
echo "\n═══ نداءاتُ csrf_field الواقعةُ داخلَ وسمِ HTML: ", $hits, " في ", count($files), " ملفًّا ═══\n";
