<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * حارسُ قابلية النقل — php tests/portability_test.php
 * ───────────────────────────────────────────────────────────────────────────
 * يمنع عودةَ نمطٍ تكرّر فعلًا: كتابةُ مسارٍ مطلقٍ يخصّ جهازَ مطوّرٍ بعينه في ملفٍّ
 * متتبَّع، فيعمل عنده وينهار عند غيره. السابقةُ الموثَّقة: الالتزام 7f3064b كتب في
 * ‏.htaccess التوجيه `auto_prepend_file "C:/wamp64/www/ems/includes/session_bootstrap.php"`
 * فسقط المشروعُ على كلِّ جهازٍ لا يملك ذلك المسار حرفيًّا (أزاله 5e55e36؛ التوثيق
 * في docs/nfr/N-26_infra_readiness_ar.md §NFR-13).
 *
 * ولا يحرس المسارات وحدها: يحرس كذلك تطابقَ .env.example مع ما يقرأه الكود فعلًا
 * — **مقارنةً آليّةً لا قائمةً مكتوبة**، لأن القائمةَ اليدويةَ تتقادم عند أوّل علمٍ
 * جديد، وحينها يعود العطلُ نفسُه في ثوبٍ آخر.
 *
 * لا يحتاج قاعدةَ بياناتٍ ولا .env — يعمل على أي نسخةٍ من المستودع.
 * رمزُ الخروج: 0 نجاح · 1 فشل.
 * المرجع: PROMPT_cross_env_portability_fix.md
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__);
$PASS = 0; $FAIL = 0;

function check($c, $m) {
    global $PASS, $FAIL;
    $c ? $PASS++ : $FAIL++;
    fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n");
}
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

fwrite(STDOUT, "\n══ حارسُ قابلية النقل ══\n");

/**
 * ملفّاتُ المستودع المتتبَّعة التي يُحكَم عليها.
 *
 * الاستثناءاتُ مقصودة: vendor/ ليس كودَنا · docs/ و*.md توثيقٌ يذكر المسارات
 * شرحًا لا تنفيذًا · tests/ يحوي تجهيزاتِ اختبارٍ لا إعداداتِ تشغيل ·
 * ‏.git/ و.claude/ و storage/ ليست كودَ تطبيق.
 */
function repo_files($root, array $extensions)
{
    $out = array();
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($walk as $file) {
        $p = str_replace('\\', '/', $file->getPathname());
        if (preg_match('#/(\.git|\.claude|vendor|node_modules|storage|logs|database/backups)/#', $p)) {
            continue;
        }
        $name = basename($p);
        $ext = strtolower((string) pathinfo($p, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true) && !in_array($name, $extensions, true)) {
            continue;
        }
        $out[] = $p;
    }
    return $out;
}

/** يُزيل تعليقاتِ PHP وسطورَ التعليق كي لا يُحاكَم شرحٌ على أنه كود. */
function strip_comments($src, $isPhp)
{
    if ($isPhp && function_exists('token_get_all')) {
        $kept = '';
        foreach (@token_get_all($src) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                    $kept .= "\n";
                    continue;
                }
                $kept .= $token[1];
            } else {
                $kept .= $token;
            }
        }
        return $kept;
    }
    // ملفّاتُ الإعداد (.htaccess/.ini): التعليقُ سطرٌ يبدأ بـ# أو ;
    return preg_replace('/^\s*[#;].*$/m', '', $src);
}

// ─────────────────────────────────────────────────────────────────────────────
head('① لا مسارَ مطلقًا ولا اسمَ حزمةٍ في كودٍ متتبَّع');

$offenders = array();
$scanned = 0;
foreach (repo_files($ROOT, array('php', 'htaccess', 'ini', 'json', '.htaccess')) as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $path), '/');
    // ثلاثةٌ لا تُحاكَم بحكم وظيفتها: الحارسُ نفسُه، والفاحصُ (يطبع أمثلةَ مسارات
    // في رسائل الإرشاد)، والقالبُ (يوثّق أمثلةً للمفاتيح)، ووحدةُ الاكتشاف
    // (تحمل قائمةَ حزمٍ مرشَّحةً تُفحص بـis_dir — وهي النمطُ المعتمَد لا المخالفة).
    if (preg_match('#^(tests/portability_test\.php|tools/doctor\.php|includes/portable_paths\.php|\.env\.example)$#', $rel)) {
        continue;
    }
    $src = (string) @file_get_contents($path);
    if ($src === '') { continue; }
    $scanned++;

    $code = strip_comments($src, substr($path, -4) === '.php');
    // مسارُ ويندوز مطلق: حرفُ سواقةٍ + ':' + فاصل (أو فاصلين في JSON المُهرَّب)
    // + مكوّنُ مسارٍ حقيقي. الشرطان الدقيقان ضروريان بالتجربة:
    //   • اللَّحظُ الخلفي يستبعد `php://input` و`Details:\s` (حرفٌ قبل الحرف).
    //   • واستبعادُ '\' قبله يستبعد أصنافَ المحارف في التعابير النمطية `[\s:\-]`.
    //   • واشتراطُ حرفين فأكثر بعد الفاصل يستبعد `s:\-` وأشباهه.
    $absolutePath = preg_match('#(?<![A-Za-z0-9_\\\\])[A-Za-z]:[\\\\/]{1,2}[A-Za-z0-9_.]{2,}#', $code);
    $stackName = preg_match('#\b(wamp64|xampp|laragon)\b#i', $code);
    if ($absolutePath || $stackName) {
        $offenders[] = $rel;
    }
}
check($scanned > 300, "ملفّاتُ المستودع ممسوحة ({$scanned} ملفًا)");
check(empty($offenders),
    'لا مسارَ مطلقًا ولا اسمَ حزمةٍ في كودٍ منفَّذ'
    . (empty($offenders) ? '' : ' — مخالف: ' . implode(', ', array_slice($offenders, 0, 6))
        . (count($offenders) > 6 ? ' …(+' . (count($offenders) - 6) . ')' : '')));

// ─────────────────────────────────────────────────────────────────────────────
head('② كلُّ مفتاحٍ يقرأه الكود موثَّقٌ في .env.example');

$usedKeys = array();
foreach (repo_files($ROOT, array('php')) as $path) {
    $rel = ltrim(str_replace(str_replace('\\', '/', $ROOT), '', $path), '/');
    // tests/ تجهيزاتُ اختبارٍ لا إعداداتُ تشغيل (مثل EMS_PROBE_SAFETY الذي
    // يُحقن تراكبًا مؤقتًا في عدّة الاختبار ولا معنى له في قالب التهيئة).
    if (strpos($rel, 'tests/') === 0) { continue; }
    $src = (string) @file_get_contents($path);
    if (preg_match_all("/ems_env\(\s*'([A-Z][A-Z0-9_]*)'/", $src, $m)) {
        foreach ($m[1] as $k) { $usedKeys[$k] = true; }
    }
}
$usedKeys = array_keys($usedKeys);
sort($usedKeys);

$documented = array();
foreach ((array) @file($ROOT . '/.env.example', FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') { continue; }
    $pos = strpos($line, '=');
    if ($pos !== false) {
        $k = trim(substr($line, 0, $pos));
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) { $documented[] = $k; }
    }
}

$undocumented = array_values(array_diff($usedKeys, $documented));
check(count($usedKeys) > 30, 'مفاتيحُ البيئة مُستخرَجةٌ آليًّا (' . count($usedKeys) . ' مفتاحًا)');
check(empty($undocumented),
    'كلُّ مفتاحٍ يقرأه الكود موجودٌ في .env.example'
    . (empty($undocumented) ? '' : ' — ينقص: ' . implode(', ', $undocumented)));

// ─────────────────────────────────────────────────────────────────────────────
head('③ ‏.htaccess خالٍ من auto_prepend_file (السابقةُ N-26)');

$ht = (string) @file_get_contents($ROOT . '/.htaccess');
check($ht !== '', '.htaccess مقروء');
check(!preg_match('/^\s*php_value\s+auto_prepend_file/mi', $ht),
    'لا توجيهَ auto_prepend_file — المعالجُ يُستدعى نسبيًّا في الكود');

// ─────────────────────────────────────────────────────────────────────────────
head('④ أدواتُ التشخيص والدليل موجودة');

check(is_file($ROOT . '/tools/doctor.php'), 'tools/doctor.php موجود');
check(is_file($ROOT . '/includes/portable_paths.php'), 'includes/portable_paths.php موجود');
check(is_file($ROOT . '/docs/SETUP_ar.md'), 'docs/SETUP_ar.md موجود');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
