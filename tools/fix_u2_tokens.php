<?php
/**
 * tools/fix_u2_tokens.php — توليدُ ملفِّ الرموزِ وتحويلُ الألوانِ الحرفيةِ إليه
 * ═══════════════════════════════════════════════════════════════════════════
 * AC-U2 · SH-03 — «صفرُ قيمةٍ لونيةٍ حرفيةٍ خارجَ ملفِّ الرموز».
 *
 * ◆ **القيمُ تُحفظ كما هي حرفًا بحرف.** التحويلُ استبدالُ مرجعٍ لا إعادةَ تلوين:
 *   `#1e3a5f` تصير `var(--c-navy-700)` وقيمةُ الرمزِ `#1e3a5f`. فالنتيجةُ
 *   المُصيَّرةُ متطابقةٌ بالبكسل، والمكسبُ أن اللوحةَ صارت مرئيةً في موضعٍ واحد.
 *   ◆ ولم أوحّد المتقاربَ عمدًا: 875 لونًا متمايزًا وذيلٌ طويل، وتوحيدُها قرارُ
 *     تصميمٍ يغيّر شكلَ 300 شاشة — يُتخذ باللوحةِ أمام العين لا قبل رؤيتها.
 *     فهذه الخطوةُ **تُظهر الدَّينَ ولا تدفنه**، والتوحيدُ يليها.
 *
 * ◆ التسميةُ بدورِ اللونِ حيث يُعرَف (العلامةُ والدلالاتُ والرماديات)، وبقيمتِه
 *   فيما عدا ذلك — واسمٌ بالقيمةِ أصدقُ من اسمٍ مخترَعٍ يوحي بدورٍ لا يؤديه.
 *
 * ◆ ما **لا** يُمَسّ:
 *     · داخلَ التعليقات — شرحٌ لا أمر.
 *     · داخلَ `url(...)` — روابطُ البياناتِ SVG لا تفهم `var()`.
 *     · داخلَ النصوصِ المقتبسة (`content: "#fff"`).
 *     · ملفاتُ المكتبات (bootstrap وأخواتها) — لا نؤلّفها.
 *     · ملفُّ الرموزِ نفسُه.
 *
 * التشغيل: php tools/fix_u2_tokens.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';

$TOKEN_FILE = 'assets/css/design-tokens.css';
$VENDOR = array('bootstrap', 'jquery', 'datatables', 'fontawesome', 'select2',
                'flatpickr', 'chart', 'leaflet', 'swiper', 'animate', '.min.css');

/** يُطبّع القيمةَ اللونيةَ إلى صورةٍ واحدة (لا يغيّر اللونَ — يوحّد كتابتَه). */
function u2_norm($c)
{
    $c = strtolower(trim($c));
    if ($c !== '' && $c[0] === '#') {
        $h = substr($c, 1);
        if (strlen($h) === 3) { $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]; }
        if (strlen($h) === 4) { $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3]; }
        if (strlen($h) === 8 && substr($h, 6) === 'ff') { $h = substr($h, 0, 6); }
        return '#' . $h;
    }
    $t = preg_replace('/\s+/', '', $c);
    if (preg_match('/^rgba?\((\d+),(\d+),(\d+)(?:,([\d.]+))?\)$/', $t, $m)) {
        $a = isset($m[4]) ? (float) $m[4] : 1.0;
        if ($a >= 1) { return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]); }
        return sprintf('rgba(%d,%d,%d,%s)', $m[1], $m[2], $m[3],
            rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.'));
    }
    return $t;
}

/** أسماءُ الأدوارِ المعروفةِ — من هويةِ النظامِ نفسِها لا من ذوقي. */
$ROLE = array(
    '#f4c542' => 'brand-gold',       '#b48600' => 'brand-gold-ink',
    '#d4a500' => 'brand-gold-deep',  '#1e3a5f' => 'brand-navy',
    '#16a34a' => 'state-ok',         '#dc2626' => 'state-danger',
    '#2563eb' => 'state-info',       '#1d4ed8' => 'state-info-deep',
    '#ffffff' => 'surface',          '#000000' => 'ink-max',
    '#1f1f1f' => 'ink-900',          '#3d3d3d' => 'ink-700',
    '#6b7280' => 'ink-500',          '#475569' => 'ink-600',
    '#e7e5e4' => 'line-200',         '#0f1115' => 'ink-950',
);

/** يُقسّم CSS إلى مقاطعَ آمنةٍ وغيرِ آمنة (تعليقٌ · url() · نصّ). */
function u2_split($src)
{
    $parts = array();
    $re = '#(/\*.*?\*/)|(url\((?:[^()]|\((?:[^()]*)\))*\))|("(?:\\\\.|[^"\\\\])*")|(\'(?:\\\\.|[^\'\\\\])*\')#su';
    $last = 0;
    if (preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            if ($hit[1] > $last) { $parts[] = array(true, substr($src, $last, $hit[1] - $last)); }
            $parts[] = array(false, $hit[0]);
            $last = $hit[1] + strlen($hit[0]);
        }
    }
    if ($last < strlen($src)) { $parts[] = array(true, substr($src, $last)); }
    return $parts;
}

/**
 * يمرّ على **قيمِ الإعلاناتِ وحدَها** ويستبدلها بما تُرجعه الدالة.
 *
 * ◆ الدرسُ الذي دفعتُ ثمنَه: الاستبدالُ على النصِّ كلِّه يطال **المُحدِّدات**
 *   الشبيهةَ بالسداسيّ (`#f8f9fa` معرِّفًا · 148 موضعًا) فيكسر القواعدَ ويسقط
 *   النظامُ كلُّه إلى ألوانِ المتصفحِ الافتراضية. القيمةُ ما بعد `:` وقبل `;`
 *   أو `}` داخلَ كتلة — وما عداها بنيةٌ لا لون.
 */
function u2_map_decls($css, callable $fn)
{
    return preg_replace_callback(
        '/(:)([^;{}]*)(?=[;}])/',
        function ($m) use ($fn) { return $m[1] . $fn($m[2]); },
        $css
    );
}

/* ── ① الجمع ────────────────────────────────────────────────────────────── */
$COLOR_RE = '/#[0-9a-fA-F]{3,8}\b|\brgba?\s*\([^()]*\)/';
$GLOBALS['COLOR_RE'] = $COLOR_RE;
$counts = array();
$targets = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'css') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    if (fix_is_skipped($rel) || $rel === $TOKEN_FILE) { continue; }
    $skip = false;
    foreach ($VENDOR as $v) { if (stripos($rel, $v) !== false) { $skip = true; break; } }
    if ($skip) { continue; }

    $src = (string) file_get_contents($f->getPathname());
    $hit = 0;
    foreach (u2_split($src) as $p) {
        if (!$p[0]) { continue; }
        u2_map_decls($p[1], function ($val) use (&$counts, &$hit) {
            if (preg_match_all($GLOBALS['COLOR_RE'], $val, $m)) {
                foreach ($m[0] as $c) { $k = u2_norm($c); $counts[$k] = ($counts[$k] ?? 0) + 1; $hit++; }
            }
            return $val;
        });
    }
    if ($hit > 0) { $targets[$rel] = $hit; }
}
arsort($counts);

/* ── ② التسمية ──────────────────────────────────────────────────────────── */
$name = array();
$seq = 0;
foreach ($counts as $val => $n) {
    if (isset($ROLE[$val])) { $name[$val] = '--c-' . $ROLE[$val]; continue; }
    $slug = preg_replace('/[^a-z0-9]+/', '', strtolower($val));
    if ($slug === '') { $slug = 'x' . (++$seq); }
    $name[$val] = '--c-' . $slug;
}

echo 'ملفاتٌ فيها ألوان: ' . count($targets) . ' · استعمالات: ' . array_sum($counts)
   . ' · رموزٌ ستُولَّد: ' . count($name) . "\n";
if (!$apply) { echo "(عرضٌ فقط — أضف --apply)\n"; exit(0); }

/* ── ③ ملفُّ الرموز — **يُلحَق ولا يُستبدل** ────────────────────────────────
   ◆ خطأٌ ارتكبتُه ثم كشفه حجمُ الملف: كتبتُ فوقَ ملفٍّ قائمٍ فيه **99 رمزًا**
     (ألوانُ علامةٍ ومسافاتٌ وخطوط) لها **128 استعمالًا حيًّا** في CSS. ومحوُها
     كان سيُفرغ كلَّ `var()` يشير إليها. القاعدة: المولِّدُ يُضيف قسمَه ويترك
     ما لم يكتبه — ولا يملك ملفًّا شاركه غيرُه. */
$existing = (string) @file_get_contents($ROOT . '/' . $TOKEN_FILE);
$MARK_A = '/* ▼▼ AC-U2 — قسمٌ مولَّدٌ آليًّا · لا يُحرَّر يدويًّا ▼▼ */';
$MARK_B = '/* ▲▲ نهايةُ القسمِ المولَّد ▲▲ */';
if ($existing !== '') {
    $a = strpos($existing, $MARK_A);
    $b = strpos($existing, $MARK_B);
    if ($a !== false && $b !== false && $b > $a) {
        // إعادةُ توليدٍ: يُقتطع القسمُ السابقُ وحدَه
        $existing = substr($existing, 0, $a) . substr($existing, $b + strlen($MARK_B));
    }
    $existing = rtrim($existing) . "\n\n";
}

$out  = $existing . $MARK_A . "\n";
$out .= "/* ═══════════════════════════════════════════════════════════════════════\n";
$out .= " * assets/css/design-tokens.css — لوحةُ ألوانِ النظامِ في موضعٍ واحد\n";
$out .= " * ═══════════════════════════════════════════════════════════════════════\n";
$out .= " * مولَّدٌ آليًّا: php tools/fix_u2_tokens.php --apply\n";
$out .= " *\n";
$out .= " * ◆ **كلُّ قيمةٍ هنا هي القيمةُ التي كانت في مكانها حرفًا بحرف** — التحويلُ\n";
$out .= " *   استبدالُ مرجعٍ لا إعادةَ تلوين، والنتيجةُ المُصيَّرةُ متطابقةٌ بالبكسل.\n";
$out .= " *\n";
$out .= " * ◆ والذيلُ الطويلُ أدناه **دَينٌ مرئيٌّ لا لوحةُ تصميم**: " . count($name) . " لونًا\n";
$out .= " *   متمايزًا في نظامٍ واحدٍ عددٌ لا يُقصد. وقد كان مبعثرًا في " . count($targets) . " ملفًّا\n";
$out .= " *   فلا يراه أحد؛ وهو الآن في صفحةٍ واحدةٍ تُقرأ — والتوحيدُ قرارُ تصميمٍ\n";
$out .= " *   يُتخذ باللوحةِ أمام العين، ويغيّر شكلَ الشاشات فيلزمه إقرارُ المالك.\n";
$out .= " */\n\n:root {\n";

$out .= "  /* ── هويةُ العلامةِ والدلالات ─────────────────────────────── */\n";
foreach ($ROLE as $val => $role) {
    if (!isset($name[$val])) { continue; }
    $out .= sprintf("  %-28s %s;%s\n", $name[$val] . ':', $val,
        str_repeat(' ', max(1, 10 - strlen($val))) . '/* ×' . $counts[$val] . ' */');
}
$out .= "\n  /* ── الذيلُ: ألوانٌ بلا دورٍ معلَنٍ بعد (تنتظر التوحيد) ───── */\n";
foreach ($counts as $val => $n) {
    if (isset($ROLE[$val])) { continue; }
    $out .= sprintf("  %-28s %s; /* ×%d */\n", $name[$val] . ':', $val, $n);
}
$out .= "}\n";
file_put_contents($ROOT . '/' . $TOKEN_FILE, $out);
echo "✔ {$TOKEN_FILE} (" . round(strlen($out) / 1024, 1) . " ك.ب)\n";

/* ── ④ الاستبدال ────────────────────────────────────────────────────────── */
$stamp = 'u2_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;
$changed = 0; $repl = 0;
foreach (array_keys($targets) as $rel) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) file_get_contents($abs);
    $new = '';
    foreach (u2_split($src) as $p) {
        if (!$p[0]) { $new .= $p[1]; continue; }
        $new .= u2_map_decls($p[1], function ($val) use ($name, &$repl, $COLOR_RE) {
            return preg_replace_callback($COLOR_RE, function ($m) use ($name, &$repl) {
                $k = u2_norm($m[0]);
                if (!isset($name[$k])) { return $m[0]; }
                $repl++;
                return 'var(' . $name[$k] . ')';
            }, $val);
        });
    }
    if ($new === $src) { continue; }
    $bdir = $backupDir . '/' . dirname($rel);
    if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
    @copy($abs, $backupDir . '/' . $rel);
    file_put_contents($abs, $new);
    $changed++;
}
echo "✔ استُبدل {$repl} موضعًا في {$changed} ملفًّا · النسخ: storage/backups/{$stamp}\n";
echo "◆ الآن: تأكّدْ أن ملفَّ الرموزِ يُحمَّل **قبل** بقيةِ الأنماط.\n";
