<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * فاحصُ البيئة — EMS Doctor  ·  php tools/doctor.php
 * ───────────────────────────────────────────────────────────────────────────
 * يجيب سؤالًا واحدًا: **لماذا لا يعمل المشروع على هذا الجهاز؟**
 *
 * المشروع يُطوَّر على أكثرَ من حزمةٍ محلية (WAMP · XAMPP)، وأكثرُ أعطالِ
 * «يعمل عندي ولا يعمل عنده» ليست أعطالَ كودٍ بل نقصَ إعدادٍ أو ترحيلٍ لم يُشغَّل.
 * هذا الفاحصُ يكشفها دفعةً واحدةً بدل تشخيصٍ يدويٍّ في كل مرة.
 *
 * قواعدُ التصميم (مقصودة — لا تُخالَف):
 *   • **تشخيصٌ محض**: لا يكتب ملفًّا ولا يمسّ قاعدةً ولا يُصلح شيئًا.
 *   • **لا يعتمد على config.php**: ذاك يموت عند نقص .env، وهي بالضبط الحالةُ
 *     التي وُجد الفاحصُ من أجلها. يقرأ .env بنفسه ويفتح اتصالَه بنفسه.
 *   • **يكمل رغم فشل الاتصال**: بنودُ القاعدة تُتخطّى وتُعلَّم، ولا ينهار.
 *   • رمزُ الخروج: 0 إن لم يوجد ✗ · 1 إن وُجد — صالحٌ للأتمتة.
 *
 * المرجع: PROMPT_cross_env_portability_fix.md · الدليل: docs/SETUP_ar.md
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden — doctor is CLI-only.');
}

error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/portable_paths.php';

// ─────────────────────────────────────────────────────────────────────────────
// عدّةُ الطباعة
// ─────────────────────────────────────────────────────────────────────────────
$OK = 0; $BAD = 0; $WARN = 0;

function head($t) { fwrite(STDOUT, "\n── {$t}\n"); }
function ok($m)   { global $OK;   $OK++;   fwrite(STDOUT, "  ✔ {$m}\n"); }
function warn($m, $fix = '') {
    global $WARN; $WARN++;
    fwrite(STDOUT, "  ! {$m}\n");
    if ($fix !== '') { fwrite(STDOUT, "      → {$fix}\n"); }
}
function bad($m, $fix = '') {
    global $BAD; $BAD++;
    fwrite(STDOUT, "  ✘ {$m}\n");
    if ($fix !== '') { fwrite(STDOUT, "      → {$fix}\n"); }
}
function info($m) { fwrite(STDOUT, "      {$m}\n"); }

fwrite(STDOUT, "\n══ فاحصُ بيئة EMS ══\n");
info('الجذر: ' . str_replace('\\', '/', $ROOT));

// ─────────────────────────────────────────────────────────────────────────────
// ① PHP — الإصدارُ والامتدادات
// ─────────────────────────────────────────────────────────────────────────────
head('① PHP');

// المصدرُ الحاكم: composer.json. لا يُكتب الرقمُ هنا يدويًّا كي لا يتقادم حين
// ترفع التبعياتُ سقفَها — الفاحصُ يقرأ ما تشترطه الحزمُ فعلًا.
$minPhp = '8.2.0';
$lockFile = $ROOT . '/composer.lock';
if (is_readable($lockFile)) {
    $lock = json_decode((string) file_get_contents($lockFile), true);
    foreach ((array) ($lock['packages'] ?? array()) as $pkg) {
        $req = (string) ($pkg['require']['php'] ?? '');
        if (preg_match('/>=\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $req, $m)) {
            if (version_compare($m[1], $minPhp, '>')) { $minPhp = $m[1]; }
        }
    }
}

if (version_compare(PHP_VERSION, $minPhp, '>=')) {
    ok('إصدار PHP ' . PHP_VERSION . ' (المطلوب ≥ ' . $minPhp . ')');
} else {
    bad('إصدار PHP ' . PHP_VERSION . ' أقدمُ من المطلوب ' . $minPhp,
        'شغّل المشروع على PHP ' . $minPhp . ' فأحدث. على WAMP: بدّل الإصدار من أيقونة '
        . 'الشريط. على XAMPP: حدّث الحزمة. (سببُ الاشتراط: التبعيات في composer.json)');
}

$binPhp = ems_portable_php_bin();
info('مُنفِّذ PHP: ' . ($binPhp !== '' ? $binPhp : '(لم يُكتشف — اضبط PHP_BIN في .env عند الحاجة)'));

foreach (array('mysqli', 'mbstring', 'json', 'zip', 'gd', 'openssl') as $ext) {
    if (extension_loaded($ext)) {
        ok('الامتداد ' . $ext);
    } else {
        bad('الامتداد ' . $ext . ' غير محمَّل',
            'فعّله في php.ini: أزِل ; من السطر extension=' . $ext . ' ثم أعد تشغيل أباتشي');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ② ملفُّ .env
// ─────────────────────────────────────────────────────────────────────────────
head('② ملفُّ .env');

$envPath = $ROOT . '/.env';
$examplePath = $ROOT . '/.env.example';
$envOk = false;

if (!is_readable($envPath)) {
    bad('.env مفقودٌ أو غير مقروء',
        'أنشئه من القالب: cp .env.example .env  ثم املأ DB_HOST وDB_USER وDB_PASS وDB_NAME');
} else {
    $envOk = true;
    ok('.env موجودٌ ومقروء');

    $envKeys = array_keys(ems_env_all());
    $exampleKeys = array();
    if (is_readable($examplePath)) {
        foreach ((array) file($examplePath, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') { continue; }
            $pos = strpos($line, '=');
            if ($pos !== false) {
                $k = trim(substr($line, 0, $pos));
                if (preg_match('/^[A-Z0-9_]+$/', $k)) { $exampleKeys[] = $k; }
            }
        }
    }

    // مفاتيحُ الاتصال إلزامية — غيابُها يعني أن شيئًا لن يعمل إطلاقًا.
    $required = array('DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME');
    $missingRequired = array();
    foreach ($required as $k) {
        if (!in_array($k, $envKeys, true)) { $missingRequired[] = $k; }
    }
    if (empty($missingRequired)) {
        ok('مفاتيحُ الاتصال الأربعة موجودة (DB_PASS يجوز أن تكون فارغةً صراحةً)');
    } else {
        bad('مفاتيحُ اتصالٍ ناقصة: ' . implode(', ', $missingRequired),
            'أضِفها إلى .env — بلا هذه لا يقلع النظام أصلًا (config.php يفشل fail-fast)');
    }

    $missingOptional = array_values(array_diff($exampleKeys, $envKeys, $required));
    if (empty($missingOptional)) {
        ok('كلُّ مفاتيح القالب موجودةٌ في .env');
    } else {
        warn(count($missingOptional) . ' مفتاحًا في القالب ولا يوجد في .env — يعمل بقيمته الافتراضية',
            'انسخها من .env.example إن أردت ضبطَها صراحةً: ' . implode(', ', array_slice($missingOptional, 0, 8))
            . (count($missingOptional) > 8 ? ' …' : ''));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ③ الاتصالُ بالقاعدة
// ─────────────────────────────────────────────────────────────────────────────
head('③ الاتصالُ بالقاعدة');

$conn = null;
$dbName = (string) ems_env('DB_NAME', '');
$dbUser = (string) ems_env('DB_USER', '');

if (!$envOk) {
    warn('تُخطّي فحوصَ القاعدة — لا .env', 'أصلح البند ② أولًا');
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
    $c = @new mysqli((string) ems_env('DB_HOST', 'localhost'), $dbUser, (string) ems_env('DB_PASS', ''), $dbName);
    if ($c->connect_error) {
        $hint = 'تحقّق أن خادم القاعدة يعمل وأن المنفذ 3306 له.';
        if ((int) $c->connect_errno === 1049) {
            $hint = "القاعدة '{$dbName}' غير موجودةٍ على هذا الخادم — نصّبها: php database/install.php --check --config=install.json";
        } elseif (in_array((int) $c->connect_errno, array(1044, 1045), true)) {
            $hint = "المستخدم '{$dbUser}' مرفوض — راجع DB_USER/DB_PASS في .env، أو أنشئ المستخدم على الخادم.";
        } elseif ((int) $c->connect_errno === 2002) {
            $hint = 'لا خادمَ مُصغٍ على المنفذ — شغّل MySQL. وإن كانت لديك حزمتان مثبَّتتان '
                . 'فتأكّد أن التي تحمل بياناتك هي التي تحجز 3306 (تعارضُ المنفذ سببٌ شائع).';
        }
        bad('فشل الاتصال (' . $c->connect_errno . '): ' . $c->connect_error, $hint);
    } else {
        $conn = $c;
        $conn->set_charset('utf8mb4');
        ok("الاتصال ناجح — القاعدة '{$dbName}' بالمستخدم '{$dbUser}'");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ④ محرّكُ القاعدة — القرارُ المعتمَد: MySQL 8.4 على كل الأجهزة
// ─────────────────────────────────────────────────────────────────────────────
head('④ محرّكُ القاعدة');

if ($conn === null) {
    warn('تُخطّي — لا اتصال');
} else {
    $version = (string) $conn->server_info;
    $isMariaDb = (stripos($version, 'mariadb') !== false);

    if ($isMariaDb) {
        bad('المحرّك MariaDB ' . $version . ' — والمشروع معتمَدٌ على MySQL 8.4',
            'MariaDB منتجٌ مختلف لا إصدارٌ أقدم: مخطّطُ المشروع يستعمل ترتيبَ utf8mb4_0900 '
            . 'وأعمدةَ JSON أصيلةً لا تعرفها MariaDB، فيتوقّف استيرادُ '
            . 'database/schema/schema.sql عندها. ثبّت MySQL Community Server 8.4 واجعله '
            . 'صاحبَ المنفذ 3306. (يمكنك إبقاءُ أباتشي وPHP من حزمتك الحالية بلا تغيير.)');
    } elseif (version_compare(preg_replace('/[^0-9.].*$/', '', $version), '8.0', '<')) {
        warn('MySQL ' . $version . ' أقدمُ من 8.0', 'المعتمَد 8.4 — قد تفشل بعضُ خصائص المخطّط');
    } else {
        ok('MySQL ' . $version);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ⑤ الترحيلات
// ─────────────────────────────────────────────────────────────────────────────
head('⑤ الترحيلات');

if ($conn === null) {
    warn('تُخطّي — لا اتصال');
} else {
    $migDir = $ROOT . '/database/migrations';
    $files = array();
    foreach ((array) @scandir($migDir) as $f) {
        if (preg_match('/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/', (string) $f)) { $files[] = $f; }
    }
    sort($files);

    $applied = array();
    $tracking = @$conn->query("SELECT filename FROM schema_migrations");
    if ($tracking === false) {
        warn('جدولُ التتبّع schema_migrations غير موجودٍ بعد',
            'قاعدةٌ لم تُرحَّل قط — شغّل: php database/migrate.php up');
    } else {
        while ($row = $tracking->fetch_assoc()) { $applied[] = (string) $row['filename']; }
        $tracking->free();

        $pending = array_values(array_diff($files, $applied));
        if (empty($pending)) {
            ok('لا ترحيلاتٍ معلَّقة (' . count($applied) . ' مسجَّلًا · ' . count($files) . ' ملفًا)');
        } else {
            bad(count($pending) . ' ترحيلًا معلَّقًا — الشاشاتُ الجديدة لن تظهر حتى تُشغَّل',
                'php database/migrate.php up');
            foreach (array_slice($pending, 0, 5) as $p) { info('· ' . $p); }
            if (count($pending) > 5) { info('· … و' . (count($pending) - 5) . ' غيرها'); }
        }
    }

    $migUser = (string) ems_env('DB_MIGRATOR_USER', '');
    if ($migUser === '') {
        // ليس عطلًا بذاته: من كان DB_USER عنده يملك DDL يمضي. لكن اتصالَ التطبيق
        // المعتمَد (ems_app) مقصورٌ على DML بتصميمٍ، فالتنبيهُ يسبق الاصطدام.
        warn('DB_MIGRATOR_USER غيرُ مضبوطٍ في .env',
            'إن كان DB_USER بلا صلاحية CREATE فسيفشل migrate.php. اضبط DB_MIGRATOR_USER '
            . 'وDB_MIGRATOR_PASS لمستخدمٍ يملك DDL على القاعدة.');
    } else {
        ok("مستخدمُ الترحيل مضبوط ('{$migUser}')");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ⑥ سلامةُ التنقّل — تفسيرُ «السايدبار ناقص» و«الداشبورد القديمة»
// ─────────────────────────────────────────────────────────────────────────────
head('⑥ سلامةُ التنقّل');

if ($conn === null) {
    warn('تُخطّي — لا اتصال');
} else {
    // السايدبار يُبنى من nav_items باستعلامٍ حيٍّ (includes/unified_nav.php)، فالشاشةُ
    // الجديدةُ كودُها يصل بـgit pull وتسجيلُها لا يصل إلا بالترحيل. نقصُ الروابط
    // عَرَضٌ لقاعدةٍ غيرِ مُرحَّلة لا لملفٍّ ناقص.
    $counts = array();
    foreach (array('modules', 'nav_items', 'role_permissions') as $t) {
        $r = @$conn->query("SELECT COUNT(*) c FROM `{$t}`");
        $counts[$t] = $r ? (int) $r->fetch_assoc()['c'] : -1;
        if ($r) { $r->free(); }
    }

    if (in_array(-1, $counts, true)) {
        bad('جداولُ التنقّل غيرُ مكتملة', 'قاعدةٌ غيرُ منصَّبةٍ — راجع docs/SETUP_ar.md');
    } else {
        info("modules={$counts['modules']} · nav_items={$counts['nav_items']} · role_permissions={$counts['role_permissions']}");

        $r = @$conn->query("SELECT COUNT(*) c FROM nav_items WHERE route = 'main/my_workspace.php'");
        $workspaceRows = $r ? (int) $r->fetch_assoc()['c'] : 0;
        if ($r) { $r->free(); }

        if ($workspaceRows > 0) {
            ok("شاشةُ «مساحة عملي» مسجَّلةٌ في nav_items ({$workspaceRows} صفًّا)");
        } else {
            bad('شاشةُ «مساحة عملي» غيرُ مسجَّلةٍ في nav_items',
                'هذا سببُ «السايدبار ناقص» و«تظهر الداشبورد القديمة» معًا: الكودُ عندك '
                . 'لكنّ القاعدةَ لا تعرفه. شغّل: php database/migrate.php up');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ⑦ الكتابة
// ─────────────────────────────────────────────────────────────────────────────
head('⑦ صلاحياتُ الكتابة');

foreach (array('logs', 'storage') as $dir) {
    $path = $ROOT . '/' . $dir;
    if (!is_dir($path)) {
        bad("المجلد {$dir}/ غير موجود", "أنشئه: mkdir {$dir}");
    } elseif (!is_writable($path)) {
        bad("المجلد {$dir}/ غيرُ قابلٍ للكتابة", 'امنح المستخدمَ الذي يشغّل أباتشي صلاحيةَ الكتابة عليه');
    } else {
        ok("المجلد {$dir}/ قابلٌ للكتابة");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ⑧ قابليةُ النقل
// ─────────────────────────────────────────────────────────────────────────────
head('⑧ قابليةُ النقل');

$htPath = $ROOT . '/.htaccess';
if (!is_file($htPath)) {
    warn('.htaccess غير موجود', 'قد تتعطّل قواعدُ الحماية وإعاداتُ التوجيه');
} else {
    $ht = (string) file_get_contents($htPath);
    if (preg_match('/^\s*php_value\s+auto_prepend_file/mi', $ht)) {
        bad('.htaccess يحوي auto_prepend_file',
            'هذا التوجيهُ بمسارٍ مطلقٍ يُسقط المشروعَ على كلِّ جهازٍ لا يملك ذلك المسار '
            . 'حرفيًّا (سابقةُ N-26). احذف السطر — المعالجُ يُستدعى نسبيًّا في الكود.');
    } elseif (preg_match('/[A-Za-z]:[\\\\\/]/', $ht)) {
        bad('.htaccess يحوي مسارًا مطلقًا', 'استبدله بمسارٍ نسبي — المسارُ المطلق يكسر كلَّ جهازٍ سواك');
    } else {
        ok('.htaccess خالٍ من auto_prepend_file ومن المسارات المطلقة');
    }
}

// نفسُ المُحلِّل الذي تستعمله أدواتُ لوحة الإدارة — لا نسخةٌ ثانيةٌ منه، وإلا
// اختلف تشخيصُ الفاحص عن سلوك الأداة الفعلي.
$mysqlBinDir = ems_portable_mysql_bin_dir();
if ($mysqlBinDir !== '') {
    ok('أدواتُ MySQL مُكتشَفة: ' . $mysqlBinDir);
} else {
    warn('لم تُكتشف أدواتُ MySQL ‏(mysqldump)',
        'النسخُ الاحتياطي والاستعادة من لوحة الإدارة لن يعملا. اضبط MYSQL_BIN_DIR في .env');
}

// ─────────────────────────────────────────────────────────────────────────────
// الخلاصة
// ─────────────────────────────────────────────────────────────────────────────
fwrite(STDOUT, "\n══ الخلاصة: ✔ {$OK} · ! {$WARN} · ✘ {$BAD} ══\n");
if ($BAD === 0 && $WARN === 0) {
    fwrite(STDOUT, "الجهاز جاهز.\n\n");
} elseif ($BAD === 0) {
    fwrite(STDOUT, "الجهاز يعمل — والتنبيهاتُ أعلاه اختيارية.\n\n");
} else {
    fwrite(STDOUT, "عالِج بنودَ ✘ بالترتيب. الدليل: docs/SETUP_ar.md\n\n");
}

if ($conn instanceof mysqli) { $conn->close(); }
exit($BAD === 0 ? 0 : 1);
