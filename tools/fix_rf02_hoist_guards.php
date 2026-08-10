<?php
/**
 * tools/fix_rf02_hoist_guards.php — RF-02 · FIXA-0025: «راجعِ الأربعةَ المرصودةَ
 * أولًا ثم **امسحِ الأسطحَ كلَّها بالفاحصِ نفسِه**».
 * ═══════════════════════════════════════════════════════════════════════════
 * يرفع حارسَ العرضِ إلى أعلى الملفِّ — مباشرةً بعدَ ‎config‎ و‎permissions_helper‎
 * وقبلَ أيِّ معالجِ كتابة. الأسطحُ المرصودةُ اليومَ تعتمد على ‎insidebar.php‎
 * وحدَه في الحجب، و‎insidebar‎ يقع **بعدَ** المعالج — فيُرحَّل الأثرُ ثم يُعاد
 * توجيهُ المستخدمِ برسالةِ «لا صلاحية».
 *
 * ◆ لماذا التحويلُ آمن: الدالةُ نفسُها تُنفَّذ اليومَ على هذه الأسطحِ لكن متأخرةً
 *   (داخلَ ‎insidebar‎). الرفعُ لا يغيّر **مَن يُمنع** بل **متى** — قبلَ الكتابةِ
 *   لا بعدَها. وإعفاءاتُها (اللوحةُ · المراسلاتُ · البلاغاتُ · التقارير) داخلَها
 *   فلا تُمَسّ.
 *
 * ◆ گوتشا موثَّقة: «مُرحِّلٌ آليٌّ يمسّ نصًّا يلزمه فاحصٌ عكسيّ». فلكلِّ ملفٍّ هنا:
 *   نسخةٌ احتياطية · فحصُ تركيبٍ بعدَ التعديل · وتراجعٌ فوريٌّ عند أيِّ خطأ ·
 *   وفاحصٌ عكسيٌّ يُعيد القياسَ بعدَ الانتهاء.
 *
 * التشغيل:
 *   php tools/fix_rf02_hoist_guards.php            → عرضٌ بلا تعديل (dry-run)
 *   php tools/fix_rf02_hoist_guards.php --apply    → التعديلُ مع نسخٍ احتياطية
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once __DIR__ . '/fix_checks.php';

$apply = in_array('--apply', $argv, true);
$stamp = date('Ymd_His');
$backupDir = $ROOT . '/storage/backups/fix_rf02_' . $stamp;

$GUARD_CALL = 'enforce_current_page_view_permission';

/** عمقُ المسارِ ⇒ بادئةُ التحويلِ إلى لوحةِ التحكم. */
function rf02_redirect_for($rel)
{
    $depth = substr_count(str_replace('\\', '/', $rel), '/');
    return str_repeat('../', max(1, $depth)) . 'main/dashboard.php';
}

/** سطرُ آخرِ تضمينٍ لازمٍ (config ثم permissions_helper) — نُدرج بعدَه. */
function rf02_anchor_line($src)
{
    $code = fix_strip_comments($src);
    $lines = explode("\n", $code);
    $anchor = 0;
    foreach ($lines as $i => $ln) {
        // المرساةُ آخرُ تضمينٍ يضمن وجودَ ‎$conn‎ ودوالِّ الصلاحيات. وبعضُ الأسطحِ
        // تُضمِّن مُهيِّئًا مشتركًا (‎_risk_common.php‎) يحمل الاثنين معًا — فهو
        // مرساةٌ صالحةٌ كذلك، وإلا سقطت شاشاتٌ كاملةٌ من المسحِ بلا سبب.
        if (preg_match('/\b(include|include_once|require|require_once)\b[^;]{0,200}(config\.php|permissions_helper\.php|_risk_common\.php)/i', $ln)) {
            $anchor = $i + 1;
        }
    }
    return $anchor;
}

$targets = array();
foreach (fix_surface_files($ROOT) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '') { continue; }
    $writes = fix_sql_write_lines($src);
    if (!$writes) { continue; }
    $guards = fix_guard_lines($src);
    $firstGuard = $guards ? min($guards) : PHP_INT_MAX;
    $firstWrite = PHP_INT_MAX;
    foreach ($writes as $w) { if ($w['line'] < $firstWrite) { $firstWrite = $w['line']; } }
    if ($firstWrite >= $firstGuard) { continue; }

    $code = fix_strip_comments($src);
    if (strpos($code, $GUARD_CALL) !== false) {
        echo "تخطٍّ (حارسٌ صريحٌ موجودٌ سلفًا): {$rel}\n";
        continue;
    }
    $anchor = rf02_anchor_line($src);
    if ($anchor === 0) { echo "تخطٍّ (لا مرساةَ تضمينٍ): {$rel}\n"; continue; }
    if ($anchor >= $firstWrite) { echo "تخطٍّ (المرساةُ بعدَ الكتابة): {$rel}\n"; continue; }
    $targets[$rel] = array('anchor' => $anchor, 'write' => $firstWrite, 'guard' => $firstGuard);
}

echo "\nأسطحٌ تُرفع حرّاسُها: " . count($targets) . "\n";
echo str_repeat('─', 72) . "\n";
foreach ($targets as $rel => $t) {
    printf("  %-52s مرساة:%-5d كتابة:%-5d حارس:%d\n", $rel, $t['anchor'], $t['write'], $t['guard']);
}
if (!$apply) { echo "\n(عرضٌ فقط — أضف --apply للتنفيذ)\n"; exit(0); }

if (!is_dir($backupDir)) { @mkdir($backupDir, 0750, true); }
$ok = 0; $failed = array();
foreach ($targets as $rel => $t) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) file_get_contents($abs);
    @copy($abs, $backupDir . '/' . str_replace('/', '__', $rel));

    $redirect = rf02_redirect_for($rel);
    $insert = "\n"
        . "// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────\n"
        . "// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع\n"
        . "// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».\n"
        . "// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.\n"
        . "if (function_exists('{$GUARD_CALL}') && isset(\$conn)) {\n"
        . "    {$GUARD_CALL}(\$conn, '{$redirect}');\n"
        . "}\n";

    $lines = explode("\n", $src);
    array_splice($lines, $t['anchor'], 0, rtrim($insert, "\n"));
    $out = implode("\n", $lines);
    file_put_contents($abs, $out);

    $lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1');
    if (strpos($lint, 'No syntax errors') === false) {
        file_put_contents($abs, $src);   // ◆ تراجعٌ فوريٌّ — لا يُترك ملفٌّ مكسور
        $failed[] = $rel . ' — ' . trim($lint);
        continue;
    }
    $ok++;
}

echo "\nعُدِّل: {$ok} · أخفق: " . count($failed) . "\n";
foreach ($failed as $f) { echo "  ✘ {$f}\n"; }
echo "نسخٌ احتياطية: {$backupDir}\n";

/* ── الفاحصُ العكسيّ: أُعيد القياسَ بعدَ التعديلِ لا أفترض النجاح ────────── */
$after = fix_check_guard_before_write($ROOT);
echo "\nالفاحصُ العكسيُّ بعدَ التعديل: " . ($after['ok'] ? '✔' : '✘') . ' — ' . $after['evidence'] . "\n";
exit($after['ok'] ? 0 : 1);
