<?php
/**
 * e03_about_sweep — اكتساح «ما هذه الشاشة؟» (E-03) على كل شاشات الواجهة.
 * ───────────────────────────────────────────────────────────────────────────
 * كل ملفٍ يحمل insidebar (شاشة مصيَّرة) ولا يحمل ems_screen_about يُزرع فيه
 * النداء الآلي `ems_screen_about_auto($conn)` بعد رأس الصفحة مباشرة —
 * فيشتق السطرُ حيًّا من سجل الشاشة وموضعها في قائمة الدور.
 *
 * السلامة: يُلنت كلُّ ملفٍ بعد تعديله، والفاشل يُسترجع كما كان.
 * الاستثناء: حارة الجلسة الموازية وشاشات M-00 الخمس (موجتها جارية عندهم).
 *
 * التشغيل: php tools/e03_about_sweep.php [--apply]
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$APPLY = in_array('--apply', $argv, true);
$ROOT = dirname(__DIR__);
/* ◆ المُنفِّذُ **من العمليةِ الجاريةِ** لا مسارًا مثبَّتًا: نسخةُ PHP تتغيّر
     (8.2.30 لم تكن موجودةً أصلًا في وقتٍ ما) والمسارُ المثبَّتُ يُميت الأداةَ
     على أيِّ جهازٍ آخر. و`PHP_BINARY` هو النمطُ المعتمَدُ في المستودع. */
$PHP = PHP_BINARY;

$SKIP_DIRS = array('app', 'includes', 'database', 'tools', 'tests', 'vendor', 'docs', 'storage',
    'logs', 'node_modules', '.git', '.claude', 'worktrees', 'chats', 'emsreports');
// الحارة الموازية فُتحت (تفويض إكمال الـ66 · 2026-08-06 مساءً): عملها الجاري
// حُفظ لقطةً ملتزمة والجلسة خاملة — الزرع الإضافي لا يمس منطقها.
$SKIP_FILES = array(
    // قوالب الإطار في الجذر ليست شاشات — الزرع فيها ينفجر مسارًا (درس inheader)
    'inheader.php', 'insidebar.php', 'infooter.php', 'config.php',
);

$candidates = array();
$rii = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    function ($cur) use ($SKIP_DIRS, $ROOT) {
        if ($cur->isDir()) {
            $rel = ltrim(str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT))), '/');
            foreach ($SKIP_DIRS as $d) { if ($rel === $d || strpos($rel, $d . '/') === 0) { return false; } }
        }
        return true;
    }));
foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT))), '/');
    if (in_array($rel, $SKIP_FILES, true)) { continue; }
    if (strpos($rel, '/') === false) { continue; } // ملفات الجذر قوالب إطار لا شاشات
    $src = (string) file_get_contents($f->getPathname());
    if (strpos($src, 'insidebar.php') === false) { continue; }          // ليست شاشة مصيَّرة
    if (strpos($src, 'ems_screen_about') !== false) { continue; }        // لديها سطرها
    $candidates[$rel] = $src;
}

fwrite(STDOUT, "شاشات بلا سطر تعريف: " . count($candidates) . "\n");
if (!$APPLY) {
    foreach (array_slice(array_keys($candidates), 0, 15) as $r) { fwrite(STDOUT, "  · $r\n"); }
    fwrite(STDOUT, count($candidates) > 15 ? "  …\n" : '');
    fwrite(STDOUT, "جرّب بلا أثر — أضف --apply للزرع\n");
    exit(0);
}

$INJ = "<?php require_once __DIR__ . '/../includes/screen_contract.php'; "
     . "if (isset(\$conn)) { ems_screen_about_auto(\$conn); } ?>\n";

$done = 0; $failed = array(); $noAnchor = array();
foreach ($candidates as $rel => $src) {
    $depth = substr_count($rel, '/');
    $prefix = str_repeat('../', max(1, $depth));
    $inj = str_replace("__DIR__ . '/../includes", "__DIR__ . '/" . $prefix . "includes", $INJ);
    // نقطة الزرع: بعد سطر تضمين رأس الصفحة إن وجد، وإلا بعد سطر insidebar
    $lines = preg_split('/(\n)/', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = ''; $planted = false;
    for ($i = 0; $i < count($lines); $i++) {
        $out .= $lines[$i];
        if ($planted || $lines[$i] === "\n") { continue; }
        $ln = $lines[$i];
        if (strpos($ln, "page_header.php'") !== false || strpos($ln, 'page_header.php"') !== false) {
            // بعد نهاية كتلة PHP الحاوية إن كنا داخلها — الأبسط: الزرع بعد السطر
            // بنفس السياق: السطر تضمين PHP فنزرع نداء PHP خالصا بلا فتح وسم
            $out .= (isset($lines[$i + 1]) ? $lines[$i + 1] : '');
            $i++;
            $out .= "require_once __DIR__ . '/" . $prefix . "includes/screen_contract.php'; if (isset(\$conn)) { ems_screen_about_auto(\$conn); }\n";
            $planted = true;
            continue;
        }
        if (strpos($ln, "insidebar.php'") !== false || strpos($ln, 'insidebar.php"') !== false) {
            $out .= (isset($lines[$i + 1]) ? $lines[$i + 1] : '');
            $i++;
            // سطر التضمين قد يكون داخل PHP أو HTML — نكشف السياق من الوسم
            $isHtmlCtx = (strpos($ln, '<?php') !== false && strpos($ln, '?>') !== false);
            $out .= $isHtmlCtx ? $inj
                : "require_once __DIR__ . '/" . $prefix . "includes/screen_contract.php'; if (isset(\$conn)) { ems_screen_about_auto(\$conn); }\n";
            $planted = true;
            continue;
        }
    }
    if (!$planted) { $noAnchor[] = $rel; continue; }
    $path = $ROOT . '/' . $rel;
    $backup = $src;
    file_put_contents($path, $out);
    exec('"' . $PHP . '" -l ' . escapeshellarg($path) . ' 2>&1', $o, $rc);
    if ($rc !== 0) { file_put_contents($path, $backup); $failed[] = $rel; continue; }
    $done++;
}

fwrite(STDOUT, "زُرع: {$done} · بلا مرساة: " . count($noAnchor) . " · فشل لنت (استُرجع): " . count($failed) . "\n");
foreach ($failed as $r) { fwrite(STDOUT, "  ✘ $r\n"); }
foreach (array_slice($noAnchor, 0, 10) as $r) { fwrite(STDOUT, "  ◌ $r\n"); }
exit(count($failed) > 0 ? 1 : 0);
