<?php
/**
 * tools/baseline_disk_scan.php — BL-20260821: مسح أسطح PHP الحية وتصنيفها
 * قراءة فقط. التشغيل: php tools/baseline_disk_scan.php
 * الإخراج: docs/baseline_20260821/extract/disk_surfaces.json
 *
 * التصنيف بالمحتوى لا بالاسم وحده:
 *   SCREEN   = يضمّن insidebar (يُصيَّر داخل قشرة النظام)
 *   HANDLER  = يقرأ POST/GET لفعلٍ ويعيد توجيهًا/JSON بلا insidebar
 *   CRON     = cron_* أو يضمّن cron_guard
 *   INCLUDE  = ملف عون (لا جلسة تصيير ولا فعل مباشر) — يُضمَّن من غيره
 *   ENTRY    = صفحة دخول/توجيه (login, dashboard, index)
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$OUT = $ROOT . '/docs/baseline_20260821/extract';
require_once __DIR__ . '/lib/deprecated_mark.php';   /* NF-06 — قاعدةُ الوسمِ الوحيدة */
if (!is_dir($OUT)) { mkdir($OUT, 0777, true); }

$SKIP = array('tools', 'tests', 'includes', 'app', 'vendor', 'database', 'docs', 'storage',
    'node_modules', 'assets', '.git', '.claude', 'logs', 'emsreports', 'install', 'examples', 'chats');

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    $top = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '';
    if ($top !== '' && in_array($top, $SKIP, true)) { continue; }
    if ($top === '' && in_array($rel, array('config.php', 'excel.php'), true)) { /* يُصنَّفان أدناه */ }
    $files[] = $rel;
}
sort($files);

$out = array();
foreach ($files as $rel) {
    $src = (string) file_get_contents($ROOT . '/' . $rel);
    $base = basename($rel);
    $hasSidebar = (strpos($src, 'insidebar') !== false);
    $hasHeader  = (strpos($src, 'inheader') !== false);
    $hasCronG   = (strpos($src, 'cron_guard') !== false);
    $hasActionG = (strpos($src, 'action_guard') !== false);
    $hasBootstrap = (strpos($src, 'session_bootstrap') !== false);
    $readsPost  = (bool) preg_match('/\$_POST\s*\[/', $src);
    $echoesJson = (strpos($src, 'json_encode') !== false && (strpos($src, "header('Content-Type: application/json") !== false || strpos($src, '"Content-Type: application/json') !== false || strpos($src, 'application/json') !== false));
    $hasThead   = (strpos($src, '<thead') !== false);
    $hasForm    = (bool) preg_match('/<form[\s>]/i', $src);
    /* ══ وسمُ التقاعد — INJ-FIX-02 · NF-06 ═══════════════════════════════════
     * ◆ **الكاشفُ السابقُ كان يرصد مفرداتِه هو**: `/@deprecated|DEPRECATED|متقاعد/`
     *   على الشيفرةِ الخام. فوسَم ١٣ سطحًا متقاعدًا وليس فيها متقاعدٌ واحد:
     *     · ١٢ منها مطابقتُها **`E_DEPRECATED`** في `error_reporting(E_ALL & ~E_DEPRECATED)`
     *       — ومنها `cron_jobs.php` نفسُه، **العاملُ الذي يشغّل المجدولَ كلَّه**.
     *     · و`Governance/auth_profiles.php` مطابقتُها كلمةُ «متقاعد» التي **تطبعها
     *       الشاشةُ لافتةَ حالةٍ لغيرِها** — وهي الشاشةُ التي تحكم دخولَ ٩٧٪.
     *   ومَن قرأ الوسمَ فأطفأهما أوقف النظام. **فالوسمُ الخاطئُ خطرُ إتلافٍ لا دَينُ
     *   توثيق** — ولذلك دخل الموجةَ أ.
     *
     * ◆ **والقاعدةُ الجديدة: يُقاس المُعلَنُ لا المذكور.** لا يُعتدُّ إلا بوسمٍ
     *   مقصودٍ في موضعِ إعلان — `@deprecated` وسمَ توثيقٍ في أولِ الكلمة، أو
     *   `EMS_DEPRECATED` ثابتًا مُعلَنًا. وتُستبعد `E_DEPRECATED` بحدِّ كلمةٍ
     *   يسبقها، وتُستبعد النصوصُ المطبوعةُ بقصرِ العربيِّ على التعليقات.
     * ═══════════════════════════════════════════════════════════════════════ */
    $deprecatedMark = ems_deprecated_mark($src);   /* tools/lib/deprecated_mark.php */

    $isU13 = (strpos($src, '$U13') !== false || strpos($src, 'u13_screen_kit') !== false);
    $isDeptGov = (strpos($src, 'dept_gov_space') !== false);
    $isFinShell = (strpos($src, 'fin_analysis_shell') !== false);
    if (strpos($base, 'cron_') === 0 || $hasCronG) { $cls = 'CRON'; }
    elseif ($hasSidebar) { $cls = 'SCREEN'; }
    elseif ($isU13) { $cls = 'SCREEN'; }
    elseif ($isDeptGov || $isFinShell) { $cls = 'SCREEN'; }
    elseif (preg_match('/(_handler|_action|_actions|_ajax|_save|_delete|_export)\.php$/', $base) || ($readsPost && !$hasSidebar && !$hasHeader) || $echoesJson) { $cls = 'HANDLER'; }
    elseif (in_array($base, array('login.php', 'logout.php', 'index.php', 'dashboard.php', 'config.php'), true)) { $cls = 'ENTRY'; }
    elseif (!$hasBootstrap && !$readsPost) { $cls = 'INCLUDE'; }
    else { $cls = 'NEEDS_REVIEW'; }

    $gen = $isU13 ? 'U13_MANIFEST' : ($isDeptGov ? 'DEPT_GOV_WRAPPER' : ($isFinShell ? 'FIN_SHELL' : ''));
    $u13Screen = '';
    if ($isU13 && preg_match("/'screen'\s*=>\s*'([^']+)'/", $src, $um)) { $u13Screen = $um[1]; }
    $out[] = array(
        'path' => $rel,
        'class' => $cls,
        'generator' => $gen,
        'u13_screen' => $u13Screen,
        'sidebar' => $hasSidebar ? 1 : 0,
        'header' => $hasHeader ? 1 : 0,
        'bootstrap' => $hasBootstrap ? 1 : 0,
        'action_guard' => $hasActionG ? 1 : 0,
        'reads_post' => $readsPost ? 1 : 0,
        'json' => $echoesJson ? 1 : 0,
        'thead' => $hasThead ? 1 : 0,
        'form' => $hasForm ? 1 : 0,
        'deprecated_mark' => $deprecatedMark ? 1 : 0,
        'lines' => substr_count($src, "\n") + 1,
        'mtime' => date('Y-m-d', filemtime($ROOT . '/' . $rel)),
    );
}
file_put_contents($OUT . '/disk_surfaces.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$sum = array();
foreach ($out as $o) { $sum[$o['class']] = ($sum[$o['class']] ?? 0) + 1; }
echo 'files: ' . count($out) . "\n";
foreach ($sum as $k => $v) { echo str_pad($k, 16) . $v . "\n"; }
