<?php
/**
 * tools/govui_register_card.php — **بطاقةُ سجلِّ الورقةِ بجانبِ ما بُني**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المسألة**: أسطحٌ كثيرةٌ بُنيت قبلَ الورقةِ فصارت **تحليلًا أو قائمةَ عملٍ**
 *   بأزرارِها وأفعالِها، والورقةُ تطلب **سجلَّ الحبّةِ بحقولِه كلِّها**.
 *   ⛔ **ودهسُ كتلةِ الجدولِ القائمةِ يقتل أفعالَها** (تعديلٌ وحذفٌ ونماذجُ
 *   فرعيّة)، **وتركُ الورقةِ يترك الحقولَ غائبة**.
 *
 * ◆ **فالحسمُ إضافةٌ لا استبدال**: تُدرَج **بطاقةُ سجلٍّ** بجدولٍ فارغِ المرساة
 *   (`<table id="…">`) قبلَ محتوى الشاشة، ثمَّ يملؤها `tools/govui_field_close.php`
 *   بخريطةِ الورقة. **فالمبنيُّ يبقى والسجلُّ يحضر.**
 *
 * ⛔ **ولا تُدرَج بطاقةٌ مرّتَين**: وجودُ المرساةِ يمنع التكرار.
 *
 * التشغيل: php tools/govui_register_card.php --file=<route> --id=<grid_id> --title=<عنوان>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));

$FILE = null; $ID = null; $TITLE = null; $ICON = 'fa fa-clipboard-list';
foreach ($argv as $a) {
    if (strpos($a, '--file=') === 0)      { $FILE = substr($a, 7); }
    elseif (strpos($a, '--id=') === 0)    { $ID = substr($a, 5); }
    elseif (strpos($a, '--title=') === 0) { $TITLE = substr($a, 8); }
    elseif (strpos($a, '--icon=') === 0)  { $ICON = substr($a, 7); }
}
if ($FILE === null || $ID === null || $TITLE === null) {
    exit("الاستعمال: --file=<route> --id=<grid_id> --title=<عنوان>\n");
}
$path = $ROOT . '/' . ltrim($FILE, '/');
if (!is_file($path)) { exit("x ملفٌّ غيرُ موجود: " . $FILE . chr(10)); }
$src = (string) file_get_contents($path);
$nl  = (strpos($src, "\r\n") !== false) ? "\r\n" : "\n";
$s   = str_replace("\r\n", "\n", $src);

if (strpos($s, 'id="' . $ID . '"') !== false) {
    exit(". البطاقةُ قائمةٌ سلفًا في " . $FILE . chr(10));
}

/* ── موضعُ الإدراج: بعدَ ترويسةِ الصفحةِ — فالسجلُّ يلي العنوانَ لا يسبقه ──
   ⛔ ولا يُدرَج قبلَ الغلافِ: وسمٌ خارجَ `.main` يخرج المحتوى إلى `body`. */
$anchors = array(
    "include('../includes/page_header.php');",
    'include(\'../includes/page_header.php\'); ?>',
    "include __DIR__ . '/../includes/page_header.php';",
    "require_once __DIR__ . '/../includes/page_header.php';",
);
$pos = false;
foreach ($anchors as $a) {
    $p0 = strpos($s, $a);
    if ($p0 !== false) { $pos = $p0 + strlen($a); break; }
}
if ($pos === false) {
    /* ولا مرساةَ ترويسةٍ: فبعدَ فتحِ الغلافِ الموحَّد */
    $p0 = strpos($s, 'ems-unified-page-shell');
    if ($p0 !== false) { $pos = strpos($s, '>', $p0) + 1; }
}
if ($pos === false) {
    /* ◆ **ولا غلافَ في الملفِّ نفسِه**: أسطحٌ تُصيَّر كلَّها من عُدّةٍ مشتركةٍ
         (`dept_gov_space`) فلا ترويسةَ فيها ولا وسمَ غلاف. **وحزمةُ الحالاتِ
         الدنيا تقع داخلَ الصفحةِ يقينًا** — فهي المرساةُ الثالثة. */
    $p0 = strpos($s, 'ems_states_bundle(');
    if ($p0 !== false) {
        $p1 = strpos($s, ');', $p0);
        if ($p1 !== false) { $pos = strpos($s, chr(10), $p1); }
    }
}
if ($pos === false) { exit("x لا موضعَ إدراجٍ في " . $FILE . chr(10)); }
/* وإن كان الموضعُ داخلَ كتلةِ PHP، تُغلَق ثمَّ يُدرَج الترميزُ ثمَّ تُفتَح */
$openTag  = strrpos(substr($s, 0, $pos), '<?php');
$closeTag = strrpos(substr($s, 0, $pos), '?>');
$inPhp    = ($openTag !== false && ($closeTag === false || $openTag > $closeTag));

$card = ($inPhp ? ' ?>' : '') . "\n"
      . "    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،\n"
      . "         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->\n"
      . "    <div class=\"card\"><div class=\"card-header\"><h5><i class=\"" . $ICON . "\"></i> "
      . htmlspecialchars($TITLE, ENT_QUOTES, 'UTF-8') . "</h5></div>\n"
      . "    <div class=\"card-body\"><div class=\"table-container\">\n"
      . "        <table id=\"" . $ID . "\"></table>\n"
      . "    </div></div></div>\n"
      . ($inPhp ? "    <?php " : '');

$s = substr($s, 0, $pos) . $card . substr($s, $pos);
file_put_contents($path, str_replace("\n", $nl, $s));
echo "+ بطاقةُ سجلٍّ في " . $FILE . " (" . $ID . ")" . chr(10);
