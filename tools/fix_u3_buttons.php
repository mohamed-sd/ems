<?php
/**
 * tools/fix_u3_buttons.php — توحيدُ أنماطِ الأزرارِ إلى أربعة (AC-U3 · SH-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ 81 صنفَ زرٍّ من تأليفنا عبر 1104 استعمالًا. والمستخدمُ لا يتعلّم إحدى
 *   وثمانين إشارةً بصريّة — يتعلّم أربعًا: **ما يُنفِّذ · ما يتراجع · ما يُتلف ·
 *   ما هو ثانويٌّ خفيف**. وكثرةُ الأنماطِ تُفقد الزرَّ معناه فيُقرأ بالنصِّ وحده.
 *
 * ◆ **قاعدةُ الإسنادِ الحاكمة — السلامةُ قبل الجمال:**
 *     ما التبس إسنادُه يذهب إلى **ثانويّ**، لا إلى «مُنفِّذ» ولا إلى «مُتلِف».
 *   فزرُّ حذفٍ يبدو ذهبيًّا كزرِّ الحفظِ خطرٌ حقيقيّ، وزرُّ حفظٍ يبدو أحمرَ
 *   يُربك. والثانويُّ محايدٌ لا يَعِد بشيءٍ فلا يخدع.
 *
 * ◆ والأحجامُ والأغلفةُ ليست أنماطًا: `btn-sm` حجمٌ و`btn-group` غلافُ تخطيط —
 *   تُترك كما هي. والمعيارُ عن **الأنماطِ الدلالية** لا عن كلِّ صنفٍ يبدأ بـbtn.
 *
 * التشغيل: php tools/fix_u3_buttons.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';

$VENDOR = array('bootstrap', 'jquery', 'datatables', 'fontawesome', '.min.css', '.min.js');

/** أحجامٌ وأغلفةٌ — ليست أنماطًا دلاليةً فلا تُمَسّ ولا تُعَدّ. */
$NON_VARIANT = array(
    'btn-sm', 'btn-lg', 'btn-block', 'btn-close', 'btn-check', 'btn-toolbar',
    'btn-group', 'btn-group-vertical', 'btn-group-sm', 'btn-group-lg',
    'btn-group-toggle', 'btn-group-toggle-all',
);

/** الأنماطُ الأربعةُ المعتمدة. */
$PRIMARY   = 'btn-primary';
$SECONDARY = 'btn-secondary';
$DANGER    = 'btn-danger';
$GHOST     = 'btn-ghost';

/* ── خريطةُ الإسنادِ — صريحةٌ صنفًا صنفًا، لا استنتاجًا بمطابقةِ نص ────────
   الاستنتاجُ بالاسمِ يخطئ حيث لا يجوز الخطأ: `btn-clear-filters` فيه «clear»
   وليس إتلافًا، و`btn-out` فيه «out» وليس خروجًا مُتلفًا. */
$MAP = array(
    // ① مُنفِّذٌ — الفعلُ الملتزِمُ في الشاشة
    'btn-save' => $PRIMARY, 'btn-submit' => $PRIMARY, 'btn-ok' => $PRIMARY,
    'btn-add' => $PRIMARY, 'btn-create' => $PRIMARY, 'btn-complete' => $PRIMARY,
    'btn-main' => $PRIMARY, 'btn-gold' => $PRIMARY, 'btn-bulk-save' => $PRIMARY,
    'btn-modal-save' => $PRIMARY, 'btn-save-row' => $PRIMARY,
    'btn-submit-contract' => $PRIMARY, 'btn-submit-wide' => $PRIMARY,
    'btn-approve-sel' => $PRIMARY, 'btn-confirm-approve' => $PRIMARY,
    'btn-merge' => $PRIMARY, 'btn-import' => $PRIMARY, 'btn--primary' => $PRIMARY,
    'btn-unified' => $PRIMARY, 'btn-gradient' => $PRIMARY,
    'btn-renewal-confirm' => $PRIMARY, 'btn-resume-confirm' => $PRIMARY,
    'btn-settlement-confirm' => $PRIMARY, 'btn-pause-confirm' => $PRIMARY,

    // ③ مُتلِفٌ أو رافض — يُسنَد بالاسمِ الصريحِ وحدَه، ولا يُخمَّن أبدًا
    'btn-delete' => $DANGER, 'btn-action-delete' => $DANGER, 'btn-del-row' => $DANGER,
    'btn-confirm-reject' => $DANGER, 'btn-terminate' => $DANGER, 'btn-warn' => $DANGER,

    // ④ خفيفٌ داخلَ السطر
    'btn-ghost' => $GHOST, 'btn-tertiary' => $GHOST, 'btn-icon' => $GHOST,
    'btn-note' => $GHOST, 'btn-fault-badge' => $GHOST,

    /* ── أنماطُ بوتستراب الدلاليةُ المستعملةُ في ملفاتِنا ────────────────────
       تُسنَد صراحةً لا بالقاعدةِ الافتراضية: `btn-outline-danger` زرُّ إتلافٍ
       بمظهرٍ هادئ، وإلحاقُه بـ«ثانويّ» يمحو تحذيرَه. و`btn-success` فعلٌ
       ملتزِمٌ لا حيادَ فيه. أما `warning` فتنبيهٌ لا إتلافٌ — إلى الثانويّ. */
    'btn-success' => $PRIMARY, 'btn-outline-success' => $PRIMARY,
    'btn-outline-danger' => $DANGER,
    'btn-link' => $GHOST,
);
// ② وما عدا ذلك **ثانويّ** — القاعدةُ الافتراضيةُ الآمنة.

/* ── الجمعُ والاستبدال ─────────────────────────────────────────────────── */
$stamp = 'u3_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;
$files = 0; $edits = 0; $seen = array(); $to = array();

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) { continue; }
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, array('php', 'css', 'js'), true)) { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    if (fix_is_skipped($rel)) { continue; }
    $skip = false;
    foreach ($VENDOR as $v) { if (stripos($rel, $v) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    // ملفُّ أنماطِ الأزرارِ نفسُه يعرّف الأربعةَ فلا يُعاد كتابتُه
    if ($rel === 'assets/css/ems-buttons.css') { continue; }

    $src = (string) file_get_contents($f->getPathname());
    $n = 0;
    $new = preg_replace_callback('/\bbtn-[a-z0-9_-]+/i',
        function ($m) use ($MAP, $NON_VARIANT, $SECONDARY, $PRIMARY, $DANGER, $GHOST, &$n, &$seen, &$to) {
            $c = strtolower($m[0]);
            $seen[$c] = true;
            if (in_array($c, $NON_VARIANT, true)) { return $m[0]; }         // حجمٌ أو غلاف
            if (in_array($c, array($PRIMARY, $SECONDARY, $DANGER, $GHOST), true)) { return $m[0]; }
            $t = isset($MAP[$c]) ? $MAP[$c] : $SECONDARY;
            $to[$c] = $t;
            $n++;
            return $t;
        }, $src);

    if ($new === null || $n === 0) { continue; }
    if ($apply) {
        $bdir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($f->getPathname(), $backupDir . '/' . $rel);
        file_put_contents($f->getPathname(), $new);
    }
    $files++; $edits += $n;
}

ksort($to);
$byT = array();
foreach ($to as $from => $t) { $byT[$t][] = $from; }
echo ($apply ? 'طُبِّق' : 'سيُطبَّق') . ": {$files} ملفًّا · {$edits} استعمالًا · "
   . count($to) . " صنفًا أُسنِد\n\n";
foreach ($byT as $t => $list) {
    echo "  {$t} ← " . count($list) . " صنفًا\n";
    echo '     ' . implode(' · ', array_slice($list, 0, 12))
       . (count($list) > 12 ? ' … (+' . (count($list) - 12) . ')' : '') . "\n";
}
if ($apply) { echo "\nالنسخُ الاحتياطيّ: storage/backups/{$stamp}\n"; }
