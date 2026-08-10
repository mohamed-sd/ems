<?php
/**
 * tools/fix_sh08_labels.php — SH-08: ربطُ العناوينِ بالحقول (الوصولُ الرقمي)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكم (FIXB-0043/0044/0045): «اثنانِ وتسعونَ بالمئةِ من الحقولِ بلا عنوانٍ
 *   مرتبط — فالنقرُ على العنوانِ لا يُركّز والقارئُ لا يعرف الحقل» ·
 *   «كلُّ عنوانٍ يحمل مرجعَ حقلِه وكلُّ حقلٍ يحمل معرّفَه — **والربطُ آليٌّ في
 *   معظمه**» · «والحقلُ بلا عنوانٍ ظاهرٍ يحمل وسمًا وصفيًّا».
 *
 * ◆ ثلاثةُ أنماطٍ آمنةٍ فقط — وما عداها يُترك ويُعلَن (لا تخمين):
 *   ① ‎<label>نص</label>‎ يليه مباشرةً حقلٌ بلا معرّف ⇒ يُولَّد معرّفٌ ويُربط ‎for‎.
 *   ② حقلٌ بلا عنوانٍ وله ‎placeholder‎ ⇒ ‎aria-label‎ من نصِّ الـplaceholder.
 *   ③ حقلٌ بلا عنوانٍ ولا placeholder وله ‎title‎ ⇒ ‎aria-label‎ من الـtitle.
 *
 * ◆ گوتشا موثَّقة: «مُرحِّلٌ آليٌّ يمسّ نصًّا يلزمه فاحصٌ عكسيّ». فلكلِّ ملف:
 *   نسخةٌ احتياطية · فحصُ تركيب · تراجعٌ فوريٌّ عند الخطأ · ثم إعادةُ قياسٍ
 *   شاملةٌ بعدَ الانتهاء.
 * ◆ ولا يُمَسّ حقلٌ مخفيٌّ ولا زرُّ إرسالٍ (لا يحتاجان عنوانًا)، ولا حقلٌ
 *   يحمل ‎aria-label‎ أو ‎for‎ قائمًا — فالموجودُ لا يُستبدل.
 *
 * التشغيل:
 *   php tools/fix_sh08_labels.php            → عرضٌ بلا تعديل
 *   php tools/fix_sh08_labels.php --apply    → التعديلُ مع نسخٍ احتياطية
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$apply = in_array('--apply', $argv, true);
$backupDir = $ROOT . '/storage/backups/fix_sh08_' . date('Ymd_His');

/** ملفاتُ الواجهةِ الحيةِ (php) بلا نسخٍ ولا أدوات. */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    if (fix_is_skipped($rel)) { continue; }
    $files[] = $rel;
}
sort($files);

$SEQ = 0;
$totFor = 0; $totAria = 0; $totLeft = 0; $changedFiles = 0; $failed = array();

/** يُنظِّف نصًّا ليصلح وسمًا وصفيًّا (بلا وسومٍ ولا PHP ولا اقتباسٍ يكسر السمة). */
function sh08_clean_label($t)
{
    $t = preg_replace('/<\?php.*?\?>/s', '', (string) $t);
    $t = preg_replace('/<\?=.*?\?>/s', '', $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    $t = trim($t, " \t\n\r\0\x0B:*·—-");
    $t = str_replace(array('"', "'"), '', $t);
    return mb_substr($t, 0, 90);
}

foreach ($files as $rel) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '' || stripos($src, '<input') === false && stripos($src, '<select') === false && stripos($src, '<textarea') === false) { continue; }

    $fileFor = 0; $fileAria = 0; $fileLeft = 0;
    $out = preg_replace_callback(
        /* ◆ گوتشا جوهريةٌ التقطها التراجعُ التلقائي: النمطُ ‎[^>]*‎ يتوقف عند أولِ
           ‎>‎ — وهو في عشراتِ الوسومِ **داخلَ كتلةِ PHP** في قيمةِ سمة
           (‎value="<?php echo x(); ?>"‎)، فيُبتر الوسمُ ويُكسر الملف. فالمطابقةُ
           تعبر كتلَ PHP صراحةً: إمّا محرفٌ ليس ‎>‎، أو كتلةُ ‎<?php … ?>‎ كاملة. */
        /* ◆ **ترتيبُ البدائلِ هو العيب**: كان `[^>]` أولًا، وهو يطابق `<` فلا
             يُبلَغ فرعُ كتلةِ PHP أبدًا — فيُبتر الوسمُ عند `>` من `?>` ويبقى
             `?` يتيمًا يكسر التركيب. أخفقت التسعون ملفًّا كلُّها بهذا.
             الفرعُ الخاصُّ يسبق العامَّ دائمًا في التبادل. */
        '/(?P<label><label\b(?P<lattrs>(?:<\?(?:php|=).*?\?>|[^>])*)>(?P<ltext>.*?)<\/label>\s*)?'
        . '(?P<tag><(?P<el>input|select|textarea)\b(?P<attrs>(?:<\?(?:php|=).*?\?>|[^>])*)>)/is',
        function ($m) use (&$SEQ, &$fileFor, &$fileAria, &$fileLeft, $rel) {
            $attrs = $m['attrs'];
            $whole = $m[0];

            // حقولٌ لا تحتاج عنوانًا
            if (preg_match('/\btype\s*=\s*("|\')(hidden|submit|button|reset|image)\1/i', $attrs)) { return $whole; }
            // موجودٌ لا يُستبدل
            if (preg_match('/\baria-label(ledby)?\s*=/i', $attrs)) { return $whole; }

            $hasId = preg_match('/\bid\s*=\s*("|\')([^"\']+)\1/i', $attrs, $im);
            $labelPart = isset($m['label']) ? $m['label'] : '';
            $lattrs = isset($m['lattrs']) ? $m['lattrs'] : '';
            $ltext  = isset($m['ltext']) ? $m['ltext'] : '';

            /* ◆ گوتشا التقطها التراجعُ التلقائيُّ في 93 ملفًّا: المطابقةُ الكسولةُ
               تعبر **كتلةَ PHP** بين العنوانِ والحقل (‎<?php endif; ?>‎ وأخواتها)،
               فيُعاد بناءُ العنوانِ في موضعٍ يكسر بنيةَ التحكم. الشرطُ: لا يُقبل
               الجوارُ إن حمل الفاصلُ أو نصُّ العنوانِ كتلةَ تحكّمٍ (‎<?php‎). */
            $sepRaw = ($labelPart !== '') ? substr($labelPart, strpos($labelPart, '</label>') + 8) : '';
            $spansPhpBlock = ($labelPart !== '')
                && (strpos($sepRaw, '<?') !== false || preg_match('/<\?php\b/i', $ltext));

            /* ① عنوانٌ مجاورٌ بلا for ⇒ يُولَّد معرّفٌ ويُربط */
            if ($labelPart !== '' && !$spansPhpBlock && !preg_match('/\bfor\s*=/i', $lattrs)) {
                $id = $hasId ? $im[2] : ('emsf_' . (++$SEQ) . '_' . substr(md5($rel . $SEQ), 0, 5));
                $newAttrs = $hasId ? $attrs : rtrim($attrs) . ' id="' . $id . '"';
                $newLabel = '<label' . rtrim($lattrs) . ' for="' . $id . '">' . $ltext . '</label>';
                // نحفظ المسافةَ الفاصلةَ كما كانت
                $sep = substr($labelPart, strpos($labelPart, '</label>') + 8);
                $fileFor++;
                return $newLabel . $sep . '<' . $m['el'] . $newAttrs . '>';
            }

            // عنوانٌ مجاورٌ **بـfor قائم** ⇒ لا يُمَسّ
            if ($labelPart !== '' && preg_match('/\bfor\s*=/i', $lattrs)) { return $whole; }
            // عنوانٌ يفصله عن حقلِه كتلةُ تحكّمٍ ⇒ يُترك للعنوانِ المكتوب (دَينٌ معلَن)
            if ($spansPhpBlock) { $fileLeft++; return $whole; }

            /* ② وسمٌ وصفيٌّ من placeholder */
            if (preg_match('/\bplaceholder\s*=\s*("|\')(.*?)\1/is', $attrs, $pm)) {
                $txt = sh08_clean_label($pm[2]);
                if ($txt !== '') {
                    $fileAria++;
                    return '<' . $m['el'] . rtrim($attrs) . ' aria-label="' . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '">';
                }
            }
            /* ③ وسمٌ وصفيٌّ من title */
            if (preg_match('/\btitle\s*=\s*("|\')(.*?)\1/is', $attrs, $tm)) {
                $txt = sh08_clean_label($tm[2]);
                if ($txt !== '') {
                    $fileAria++;
                    return '<' . $m['el'] . rtrim($attrs) . ' aria-label="' . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '">';
                }
            }

            $fileLeft++;
            return $whole;   // ◆ لا تخمين: يُترك ويُعدّ في الدَّين المعلَن
        },
        $src);

    $totLeft += $fileLeft;
    if ($out === null || $out === $src || ($fileFor + $fileAria) === 0) { continue; }
    $totFor += $fileFor; $totAria += $fileAria;

    if (!$apply) { $changedFiles++; continue; }

    if (!is_dir($backupDir)) { @mkdir($backupDir, 0750, true); }
    @copy($abs, $backupDir . '/' . str_replace('/', '__', $rel));
    file_put_contents($abs, $out);
    $lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1');
    if (strpos($lint, 'No syntax errors') === false) {
        file_put_contents($abs, $src);
        $failed[] = $rel . ' — ' . trim($lint);
        $totFor -= $fileFor; $totAria -= $fileAria;
        continue;
    }
    $changedFiles++;
}

echo "ملفاتٌ " . ($apply ? 'عُدِّلت' : 'ستُعدَّل') . ": {$changedFiles}\n";
echo "ربطٌ بعنوانٍ (for/id): {$totFor}\n";
echo "وسمٌ وصفيٌّ (aria-label): {$totAria}\n";
echo "◆ بقي بلا ربطٍ (يحتاج عنوانًا مكتوبًا — دَينٌ معلَن): {$totLeft}\n";
if ($failed) {
    echo "\nأخفق (وأُعيد كما كان): " . count($failed) . "\n";
    foreach (array_slice($failed, 0, 10) as $f) { echo "  ✘ {$f}\n"; }
}
if ($apply) { echo "نسخٌ احتياطية: {$backupDir}\n"; }
else { echo "\n(عرضٌ فقط — أضف --apply للتنفيذ)\n"; }

/* ═══════════════════════════════════════════════════════════════════════════
 * الفاحصُ العكسيُّ الشامل — **الدرسُ المدفوعُ ثمنُه في هذه الحزمة**
 * ═══════════════════════════════════════════════════════════════════════════
 * فحصُ التركيبِ لكلِّ ملفٍّ على حدةٍ **لا يكفي**: في تشغيلٍ سابقٍ تسرّبت ثلاثةُ
 * ملفاتٍ مكسورةٍ رغم أن لكلِّ ملفٍّ فحصَه وتراجُعَه — فمسارُ التراجعِ نفسُه قد
 * يخفق (خرجٌ فارغٌ من المُفسِّر · تعاقبُ تشغيلاتٍ · إذنُ كتابة). ولم تُكشف إلا
 * بمسحٍ شاملٍ للشجرة. فالقاعدة: **بعدَ كلِّ تحويلٍ آليٍّ يُمسح المشروعُ كلُّه**
 * ويُستعاد من النسخِ ما انكسر — وتُرفع النتيجةُ برمزِ خروجٍ غيرِ صفريّ.
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($apply) {
    echo "\nالفاحصُ العكسيُّ الشامل — مسحُ الشجرةِ كلِّها…\n";
    $broken = array(); $restored = 0;
    foreach (fix_php_files($ROOT) as $rel) {
        $abs = $ROOT . '/' . $rel;
        $lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1');
        if (strpos($lint, 'No syntax errors') !== false) { continue; }
        $broken[] = $rel;
        // استعادةٌ من أقدمِ نسخةٍ سليمةٍ متاحة (الأقدمُ أضمنُ: قبلَ كلِّ التحويلات).
        foreach (glob($ROOT . '/storage/backups/fix_sh08_*') as $d) {
            $cand = $d . '/' . str_replace('/', '__', $rel);
            if (!is_file($cand)) { continue; }
            $tmp = $abs . '.sh08tmp';
            @copy($abs, $tmp);
            @copy($cand, $abs);
            $l2 = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1');
            if (strpos($l2, 'No syntax errors') !== false) { @unlink($tmp); $restored++; break; }
            @copy($tmp, $abs); @unlink($tmp);
        }
    }
    if ($broken) {
        echo '◆ مكسورٌ عند المسحِ الشامل: ' . count($broken) . " · استُعيد: {$restored}\n";
        foreach ($broken as $b) { echo "    · {$b}\n"; }
        if ($restored < count($broken)) { echo "✘ بقي مكسورٌ — استعدْ يدويًّا من {$backupDir}\n"; exit(1); }
        echo "✔ استُعيد كلُّ ما انكسر — الشجرةُ سليمةٌ تركيبيًّا\n";
    } else {
        echo "✔ صفرُ ملفٍّ مكسور في الشجرةِ كلِّها\n";
    }
}
