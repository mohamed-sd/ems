<?php
/**
 * tools/screen_about_import_authored.php — استيرادُ النصوص المصوغة في الملفات
 * ═══════════════════════════════════════════════════════════════════════════
 * قرارُ المالك (2026-08-09): **الجدولُ هو المرجع.** فالنصوصُ المصوغةُ باليد
 * داخلَ ملفاتِ الشاشات (وهي أجودُ ما عندنا) تُنقل إلى `screen_about` بوسم
 * `authored`، ليصير الدليلُ كلُّه محرَّرًا من مكانٍ واحدٍ بلا نشرِ كود.
 *
 * ◆ الاستخراجُ **ساكنٌ لا تنفيذيّ**: يُقرأ الوسيطُ الأولُ لنداء
 *   `ems_screen_about(` نصًّا ويُفكُّ تسلسلُه (`'a' . 'b'`) بلا `eval` — فملفُّ
 *   شاشةٍ لا يُنفَّذ في أداةِ نقلٍ نصية. وما كان وسيطُه **متغيّرًا** (لا نصًّا
 *   حرفيًّا) يُتخطّى ويُعلَن: قيمتُه لا تُعرف إلا وقتَ التشغيل.
 *
 * ◆ لا يمسّ ملفًّا واحدًا: النداءاتُ تبقى في مكانها **شبكةَ أمانٍ** إن حُذف
 *   صفٌّ من الجدول. وترتيبُ الترجيح صار: **السجل ← نصُّ الملف ← الاشتقاق**.
 *
 * التشغيل:
 *   php tools/screen_about_import_authored.php            عرضٌ فقط
 *   php tools/screen_about_import_authored.php --apply    كتابةُ السجل
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);

$apply = in_array('--apply', $argv, true);
$root  = dirname(__DIR__);
require_once $root . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$db->set_charset('utf8mb4');

/**
 * يفكُّ تعبيرَ نصٍّ حرفيٍّ متسلسلٍ إلى قيمته — بلا تنفيذ.
 * يقبل: 'a' . "b" . 'c'   ويرفض ما فيه متغيّرٌ أو نداءُ دالة.
 * @return string|null null = ليس نصًّا حرفيًّا خالصًا
 */
function ems_parse_literal($expr)
{
    $expr = trim($expr);
    if ($expr === '') { return null; }
    $out = ''; $i = 0; $n = strlen($expr); $expectString = true;
    while ($i < $n) {
        $ch = $expr[$i];
        if ($ch === ' ' || $ch === "\t" || $ch === "\r" || $ch === "\n") { $i++; continue; }
        if ($expectString) {
            if ($ch !== "'" && $ch !== '"') { return null; }   // متغيّرٌ أو تعبيرٌ آخر
            $quote = $ch; $i++; $buf = '';
            while ($i < $n) {
                $c = $expr[$i];
                if ($c === '\\' && $i + 1 < $n) {
                    $nx = $expr[$i + 1];
                    if ($quote === "'") { $buf .= ($nx === "'" || $nx === '\\') ? $nx : '\\' . $nx; }
                    else {
                        $map = array('n' => "\n", 't' => "\t", 'r' => "\r", '"' => '"', '\\' => '\\', '$' => '$');
                        $buf .= isset($map[$nx]) ? $map[$nx] : '\\' . $nx;
                    }
                    $i += 2; continue;
                }
                if ($c === $quote) { $i++; break; }
                if ($quote === '"' && $c === '$') { return null; }   // إقحامُ متغيّر
                $buf .= $c; $i++;
            }
            $out .= $buf; $expectString = false; continue;
        }
        if ($ch === '.') { $expectString = true; $i++; continue; }
        return null;   // عاملٌ غيرُ التسلسل
    }
    return $expectString && $out === '' ? null : $out;
}

/** يستخرج الوسيطَ الأولَ لأولِ نداءِ ems_screen_about( في المصدر. */
function ems_first_arg($src)
{
    if (!preg_match('/ems_screen_about\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) { return null; }
    $i = $m[0][1] + strlen($m[0][0]);
    $depth = 1; $len = strlen($src); $q = ''; $start = $i;
    while ($i < $len) {
        $c = $src[$i];
        if ($q !== '') {
            if ($c === '\\') { $i += 2; continue; }
            if ($c === $q) { $q = ''; }
        } elseif ($c === "'" || $c === '"') { $q = $c; }
        elseif ($c === '(' || $c === '[') { $depth++; }
        elseif ($c === ')' || $c === ']') { $depth--; if ($depth === 0) { break; } }
        elseif ($c === ',' && $depth === 1) { break; }
        $i++;
    }
    return substr($src, $start, $i - $start);
}

/* ── المسح ─────────────────────────────────────────────────────────────── */
$skip = array('vendor/','storage/','node_modules/','.git/','docs/','database/','install/','examples/','tests/','tools/','scripts/');
$rii  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

$ok = array(); $dynamic = array();
foreach ($rii as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $rel = ltrim(substr($p, strlen($root)), '/');
    foreach ($skip as $s) { if (strpos($rel, $s) === 0) { continue 2; } }
    if ($rel === 'includes/screen_contract.php') { continue; }
    $src = file_get_contents($p);
    if (!preg_match('/ems_screen_about\s*\(/', $src)) { continue; }

    $arg = ems_first_arg($src);
    if ($arg === null) { continue; }
    $lit = ems_parse_literal($arg);
    if ($lit === null) { $dynamic[] = $rel; continue; }
    $lit = trim(preg_replace('/[ \t]+/', ' ', $lit));
    if (mb_strlen($lit) < 20) { $dynamic[] = $rel . ' (نصٌّ أقصرُ من أن يكون تعريفًا)'; continue; }
    $ok[$rel] = $lit;
}

echo "نصوصٌ مصوغةٌ استُخرجت حرفيًّا : " . count($ok) . "\n";
echo "نداءاتٌ وسيطُها متغيّرٌ (تُتخطّى): " . count($dynamic) . "\n";
foreach ($dynamic as $d) { echo "   - $d\n"; }
echo str_repeat('─', 74) . "\n";

if (!$apply) {
    $i = 0;
    foreach ($ok as $rel => $t) {
        if ($i++ >= 4) { break; }
        echo "\n[$rel]\n" . mb_substr($t, 0, 220) . (mb_strlen($t) > 220 ? '…' : '') . "\n";
    }
    echo "\n\n(عرضٌ فقط — أضف --apply للكتابة)\n";
    exit(0);
}

/* العنوانُ يبقى كما ولّده المولِّدُ إن وُجد؛ وإلا فاسمُ الملف */
$st = $db->prepare("INSERT INTO screen_about (screen_path, title_ar, description, source)
                    VALUES (?, '', ?, 'authored')
                    ON DUPLICATE KEY UPDATE description = VALUES(description), source = 'authored'");
$n = 0;
foreach ($ok as $rel => $t) { $st->bind_param('ss', $rel, $t); $st->execute(); $n++; }
$st->close();
echo "كُتب $n صفًّا بوسم authored\n";
