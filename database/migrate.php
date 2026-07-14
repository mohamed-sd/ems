<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * مُشغِّل الترحيلات المركزي — EMS Migrations Runner (ADR-03)
 * ───────────────────────────────────────────────────────────────────────────
 * الباب الوحيد المعتمد لأي تغييرٍ على مخطّط قاعدة البيانات. يتتبّع ما طُبِّق في
 * جدول schema_migrations ويمنع التطبيق المزدوج ويكشف تعديل ملفٍ سبق تطبيقه.
 *
 * الأوامر:
 *   php database/migrate.php status                 عرض حالة كل الترحيلات
 *   php database/migrate.php up [--dry-run]         تطبيق المعلَّق بالترتيب
 *   php database/migrate.php mark-applied <file>    تسجيل ملفٍ كمُطبَّق دون تنفيذه
 *   php database/migrate.php mark-applied --all-pending   تسوية كل المعلَّق (خطّ أساس)
 *   php database/migrate.php baseline               تصدير مخطّط القاعدة الكامل
 *
 * قرارات تصميم (مقصودة — لا تغيّرها دون قرارٍ مُرقَّم):
 *   • CLI حصريًا: لا مسار ويب إطلاقًا — أداة تنفّذ DDL لا يجوز تعريضها للمتصفح.
 *   • الاتصال عبر config.php: مصدر واحد لبيانات الاتصال، بلا تكرار أسرار،
 *     ومع فرض utf8mb4/utf8mb4_unicode_ci تلقائيًا (يمنع تشوّه ENUM العربية).
 *   • لا rollback لـ DDL في MySQL (implicit commit) — الحماية: لقطة مخطّطٍ
 *     تلقائية قبل كل `up` + إلزام كل ملفٍ بأن يكون idempotent (راجع الدليل).
 *   • ترحيلات .php تُنفَّذ في عمليةٍ منفصلة (عزل كامل للدوال والمتغيرات).
 *   • ملفٌ طُبِّق ثم تغيّر محتواه = خطأ يوقف up (يُكشف بالـ checksum).
 *
 * رموز الخروج: 0 نجاح · 1 فشل (صالح للاستخدام في أتمتة النشر).
 * الدليل الكامل: docs/MIGRATIONS_GUIDE_ar.md
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden — migrations runner is CLI-only.');
}

// كتم عرض تنبيهات الإهمال فقط (PHP 8.4 يطبع Deprecated لـ session.sid_length من
// secure_session_start؛ في CLI يُعدّ ذلك إخراجًا مبكرًا يُفشل بدء الجلسة تسلسليًا).
// الأخطاء والتحذيرات الحقيقية تبقى ظاهرة، والإهمالات تبقى مسجَّلة في error_log.
error_reporting(E_ALL & ~E_DEPRECATED);

// اتصال القاعدة من المصدر الوحيد (config.php واعٍ لوضع CLI: يتخطى معالجات
// الإخراج وحرّاس AJAX/الجلسة، ويضبط utf8mb4 + utf8mb4_unicode_ci على الاتصال).
require_once dirname(__DIR__) . '/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "[migrate] FATAL: config.php لم يوفّر اتصال قاعدة بيانات.\n");
    exit(1);
}

// P0-2ب (ADR-04 · فصل المسارين): اتصال التطبيق صار DML-only (ems_app)، والمُشغِّل
// يحتاج DDL — إن وُجد زوج DB_MIGRATOR_* في .env يُستبدل الاتصال باتصال المُرحِّل
// (صلاحياته على قاعدة EMS حصرًا). غيابه = البقاء على اتصال config (توافق رجعي).
$migUser = ems_env('DB_MIGRATOR_USER');
$migPass = ems_env('DB_MIGRATOR_PASS');
if ($migUser !== null && $migUser !== '' && $migPass !== null && $migPass !== '') {
    $migConn = @new mysqli(ems_env('DB_HOST', 'localhost'), $migUser, $migPass, ems_env('DB_NAME', 'equipation_manage'));
    if ($migConn->connect_error) {
        fwrite(STDERR, "[migrate] FATAL: فشل اتصال المُرحِّل (DB_MIGRATOR_USER=$migUser): " . $migConn->connect_error . "\n");
        exit(1);
    }
    $conn->close();
    $conn = $migConn;
    fwrite(STDOUT, "[migrate] الاتصال: $migUser (مسار الترحيلات المنفصل — DDL)\n");
}

// تأكيد صريح للترميز حتى لو تغيّر config.php مستقبلًا — شرط سلامة العربية في DDL.
if ($conn->character_set_name() !== 'utf8mb4') {
    $conn->set_charset('utf8mb4');
    $conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");
}

define('MIGRATE_DIR', __DIR__ . '/migrations');
define('BASELINE_DIR', __DIR__ . '/baseline');
define('TRACKING_TABLE', 'schema_migrations');

// ─────────────────────────────────────────────────────────────────────────────
// جدول التتبّع — الـ DDL الوحيد الذي ينشئه المُشغِّل بنفسه (idempotent)
// ─────────────────────────────────────────────────────────────────────────────
function migrate_ensure_tracking_table(mysqli $conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `" . TRACKING_TABLE . "` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `filename` VARCHAR(255) NOT NULL,
        `checksum` CHAR(40) NOT NULL COMMENT 'SHA-1 لمحتوى الملف وقت التطبيق',
        `status` ENUM('applied','baseline','failed') NOT NULL,
        `applied_at` DATETIME NOT NULL,
        `execution_ms` INT NOT NULL DEFAULT 0,
        `applied_by` VARCHAR(128) NOT NULL DEFAULT '',
        `error_text` TEXT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_schema_migrations_filename` (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        fwrite(STDERR, "[migrate] FATAL: تعذّر إنشاء جدول التتبّع: {$conn->error}\n");
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// أدوات مساعدة
// ─────────────────────────────────────────────────────────────────────────────
function migrate_operator_identity()
{
    $user = function_exists('get_current_user') ? get_current_user() : 'unknown';
    $host = function_exists('gethostname') ? gethostname() : 'unknown';
    return substr($user . '@' . $host, 0, 128);
}

/** ملفات الترحيل على القرص: YYYY_MM_DD_slug.(sql|php) مرتّبة بالاسم. */
function migrate_scan_files()
{
    $files = array();
    if (!is_dir(MIGRATE_DIR)) {
        return $files;
    }
    foreach (scandir(MIGRATE_DIR) as $f) {
        if (preg_match('/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/', $f)) {
            $files[] = $f;
        }
    }
    sort($files, SORT_STRING);
    return $files;
}

/** ملفات sql/php داخل migrations/ لا تطابق عُرف التسمية — تُعرض ولا تُتجاهل بصمت. */
function migrate_scan_unmanaged()
{
    $bad = array();
    if (!is_dir(MIGRATE_DIR)) {
        return $bad;
    }
    foreach (scandir(MIGRATE_DIR) as $f) {
        if (preg_match('/\.(sql|php)$/', $f) && !preg_match('/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/', $f)) {
            $bad[] = $f;
        }
    }
    sort($bad, SORT_STRING);
    return $bad;
}

/** الصفوف المتتبَّعة: filename => row */
function migrate_fetch_tracked(mysqli $conn)
{
    $rows = array();
    $res = $conn->query("SELECT filename, checksum, status, applied_at, execution_ms, applied_by, error_text FROM `" . TRACKING_TABLE . "`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[$r['filename']] = $r;
        }
        $res->free();
    }
    return $rows;
}

function migrate_record(mysqli $conn, $filename, $checksum, $status, $execMs, $errorText = null)
{
    $stmt = $conn->prepare(
        "INSERT INTO `" . TRACKING_TABLE . "` (filename, checksum, status, applied_at, execution_ms, applied_by, error_text)
         VALUES (?, ?, ?, NOW(), ?, ?, ?)
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = VALUES(status),
             applied_at = VALUES(applied_at), execution_ms = VALUES(execution_ms),
             applied_by = VALUES(applied_by), error_text = VALUES(error_text)"
    );
    if (!$stmt) {
        fwrite(STDERR, "[migrate] FATAL: فشل تحضير تسجيل الحالة: {$conn->error}\n");
        exit(1);
    }
    $by = migrate_operator_identity();
    $stmt->bind_param('sssiss', $filename, $checksum, $status, $execMs, $by, $errorText);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        fwrite(STDERR, "[migrate] FATAL: فشل تسجيل حالة {$filename}: {$conn->error}\n");
        exit(1);
    }
}

/**
 * تنفيذ ملف SQL كاملًا عبر multi_query (خادم MySQL هو من يفصل العبارات —
 * يتعامل بشكلٍ صحيح مع الفواصل المنقوطة داخل النصوص والتعليقات).
 * يعيد '' عند النجاح أو نص الخطأ عند الفشل.
 */
function migrate_run_sql_file(mysqli $conn, $path)
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        return 'تعذّرت قراءة الملف';
    }
    // إزالة BOM إن وُجد حتى لا يفسد أول عبارة.
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }
    if (trim($sql) === '') {
        return 'الملف فارغ';
    }

    if (!$conn->multi_query($sql)) {
        return $conn->error;
    }
    // استنزاف كل النتائج؛ أول خطأ في أي عبارةٍ يوقف السلسلة ويظهر هنا.
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
        if ($conn->error !== '') {
            return $conn->error;
        }
    } while ($conn->more_results() && $conn->next_result());

    return $conn->error !== '' ? $conn->error : '';
}

/**
 * تنفيذ ترحيل PHP في عمليةٍ منفصلة — عزلٌ كامل (الملف مسؤول عن اتصاله ومخرجاته،
 * كما في 2026_06_27_employee_unification.php). النجاح = رمز خروج صفر.
 */
function migrate_run_php_file($path)
{
    $php = PHP_BINARY;
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1';
    $output = array();
    $code = 1;
    exec($cmd, $output, $code);
    foreach ($output as $line) {
        echo "      | " . $line . "\n";
    }
    return $code === 0 ? '' : ('رمز خروج غير صفري: ' . $code);
}

// ═══════════════════════════════════════════════════════════════════════════
// سور الجداول الجديدة (REV-08 · البند 4 — صمّام وقاية):
// لا ترحيلَ يُنشئ جدول أساسٍ جديدًا بلا company_id إلا إذا كان مصنَّفًا مسبقًا
// في عقد TenantRegistry تصنيفًا غيرَ مستأجَرٍ (عالميّ/ابن/كتالوج/مقيَّد) —
// وإلا يُحجَب الترحيل: تُسقَط الجداول المخالفة المُنشأة للتوّ (تراجُع الإنشاء)،
// ويُسجَّل الملف فاشلًا، ويتوقف التنفيذ. يعمل لكل ملفٍّ على حدة (عزو دقيق).
// ═══════════════════════════════════════════════════════════════════════════

/** قائمة جداول الأساس الحالية (BASE TABLE فقط — الـViews معفاة بطبيعتها). */
function migrate_wall_tables(mysqli $conn)
{
    $out = array();
    $res = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
    if ($res) {
        while ($r = $res->fetch_row()) { $out[] = $r[0]; }
    }
    return $out;
}

/** هل يحمل الجدول عمود company_id؟ */
function migrate_wall_has_company(mysqli $conn, $table)
{
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = 'company_id'");
    return $res && $res->num_rows > 0;
}

/** هل الجدول معفًى بالتصميم (مصنَّف مسبقًا في العقد تصنيفًا غير مستأجَر)؟ */
function migrate_wall_exempt($table)
{
    static $loaded = false;
    if (!$loaded) {
        require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
        $loaded = true;
    }
    $def = \App\Core\TenantRegistry::get(strtolower($table));
    if ($def === null) {
        return false; // غير مسجَّل في العقد = غير معفًى (السور يطالبه بcompany_id)
    }
    return in_array($def['type'], array(
        \App\Core\TenantRegistry::T_GLOBAL,
        \App\Core\TenantRegistry::T_CHILD,      // معزولٌ بأبيه بالتصميم
        \App\Core\TenantRegistry::T_CATALOG,    // صفوفه العامّة NULL مقصودة
        \App\Core\TenantRegistry::T_RESTRICTED,
    ), true);
}

/**
 * فحص السور بعد تنفيذ ملفٍّ واحد. يعيد '' عند السلامة، أو نص الخرق بعد
 * إسقاط الجداول المخالفة المُنشأة للتوّ (تراجُعُ إنشائها).
 */
function migrate_wall_check(mysqli $conn, array $tablesBefore)
{
    $created = array_diff(migrate_wall_tables($conn), $tablesBefore);
    $blocked = array();
    foreach ($created as $t) {
        if (!migrate_wall_exempt($t) && !migrate_wall_has_company($conn, $t)) {
            $blocked[] = $t;
        }
    }
    if (empty($blocked)) {
        return '';
    }
    foreach ($blocked as $t) {
        $conn->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
    }
    return "MIGRATION BLOCKED: جدول(جداول) جديدة بلا company_id وغير مصنَّفة عالميًّا/ابنًا في العقد: "
         . implode(', ', $blocked)
         . " — أُسقطت. أضف company_id (+backfill+فهرس) أو سجِّل التصنيف في TenantRegistry أولًا.";
}

/** لقطة مخطّطٍ كاملة (جداول + Views) إلى ملف SQL موثَّق الترويسة. */
function migrate_export_schema(mysqli $conn, $prefix)
{
    if (!is_dir(BASELINE_DIR) && !mkdir(BASELINE_DIR, 0755, true)) {
        return array('', 'تعذّر إنشاء مجلد baseline');
    }

    $dbRes = $conn->query('SELECT DATABASE() AS db');
    $dbName = $dbRes ? $dbRes->fetch_assoc()['db'] : 'unknown';

    $tables = array();
    $views = array();
    $res = $conn->query('SHOW FULL TABLES');
    if (!$res) {
        return array('', 'فشل SHOW FULL TABLES: ' . $conn->error);
    }
    while ($row = $res->fetch_row()) {
        if (strcasecmp($row[1], 'VIEW') === 0) {
            $views[] = $row[0];
        } else {
            $tables[] = $row[0];
        }
    }
    $res->free();
    sort($tables, SORT_STRING);
    sort($views, SORT_STRING);

    $stamp = date('Ymd_His');
    $file = BASELINE_DIR . '/' . $prefix . $stamp . '_' . preg_replace('/[^A-Za-z0-9_]/', '', $dbName) . '.sql';

    $out = array();
    $out[] = '-- ═══════════════════════════════════════════════════════════════════════';
    $out[] = '-- EMS Schema Baseline — لقطة مخطّط (بنية فقط، بلا بيانات)';
    $out[] = '-- المصدر: ' . $dbName . ' @ ' . migrate_operator_identity();
    $out[] = '-- التاريخ: ' . date('Y-m-d H:i:s');
    $out[] = '-- الجداول: ' . count($tables) . ' · Views: ' . count($views);
    $out[] = '-- الغرض: مرجع خطّ الأساس والمقارنة (diff). للاستعادة الكاملة ببياناتها';
    $out[] = '-- استخدم mysqldump — هذه اللقطة للبنية والتوثيق.';
    $out[] = '-- ═══════════════════════════════════════════════════════════════════════';
    $out[] = 'SET NAMES utf8mb4;';
    $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $out[] = '';

    foreach ($tables as $t) {
        $r = $conn->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`');
        if (!$r) {
            return array('', "فشل SHOW CREATE TABLE {$t}: " . $conn->error);
        }
        $row = $r->fetch_assoc();
        $r->free();
        $out[] = '-- ── Table: ' . $t . ' ──';
        $out[] = $row['Create Table'] . ';';
        $out[] = '';
    }
    foreach ($views as $v) {
        $r = $conn->query('SHOW CREATE VIEW `' . str_replace('`', '``', $v) . '`');
        if (!$r) {
            return array('', "فشل SHOW CREATE VIEW {$v}: " . $conn->error);
        }
        $row = $r->fetch_assoc();
        $r->free();
        $out[] = '-- ── View: ' . $v . ' ──';
        $out[] = $row['Create View'] . ';';
        $out[] = '';
    }
    $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    $out[] = '';

    if (file_put_contents($file, implode("\n", $out)) === false) {
        return array('', 'تعذّرت كتابة ملف اللقطة');
    }
    return array($file, '');
}

// ─────────────────────────────────────────────────────────────────────────────
// الأوامر
// ─────────────────────────────────────────────────────────────────────────────
function cmd_status(mysqli $conn)
{
    $files = migrate_scan_files();
    $tracked = migrate_fetch_tracked($conn);

    $counts = array('applied' => 0, 'baseline' => 0, 'pending' => 0, 'failed' => 0, 'modified' => 0, 'missing' => 0);
    echo "حالة الترحيلات — قاعدة التتبّع: " . TRACKING_TABLE . "\n";
    echo str_repeat('─', 78) . "\n";

    foreach ($files as $f) {
        $sum = sha1_file(MIGRATE_DIR . '/' . $f);
        if (!isset($tracked[$f])) {
            $counts['pending']++;
            printf("  %-10s %s\n", '[pending]', $f);
        } elseif ($tracked[$f]['status'] === 'failed') {
            $counts['failed']++;
            printf("  %-10s %s\n", '[FAILED]', $f);
            echo "             خطأ سابق: " . trim((string)$tracked[$f]['error_text']) . "\n";
        } elseif ($tracked[$f]['checksum'] !== $sum) {
            $counts['modified']++;
            printf("  %-10s %s\n", '[MODIFIED]', $f);
            echo "             ⚠ الملف تغيّر بعد تطبيقه — يلزم قرار: أعد المحتوى الأصلي أو أنشئ ترحيلًا جديدًا.\n";
        } else {
            $counts[$tracked[$f]['status']]++;
            printf("  %-10s %s  (%s)\n", '[' . $tracked[$f]['status'] . ']', $f, $tracked[$f]['applied_at']);
        }
    }

    foreach ($tracked as $f => $row) {
        if (!in_array($f, $files, true)) {
            $counts['missing']++;
            printf("  %-10s %s\n", '[MISSING]', $f);
            echo "             ⚠ مسجَّل في القاعدة لكن الملف غير موجود على القرص.\n";
        }
    }

    foreach (migrate_scan_unmanaged() as $f) {
        printf("  %-10s %s\n", '[UNMANAGED]', $f);
        echo "             ⚠ خارج عُرف التسمية YYYY_MM_DD_slug — لن يُدار حتى يُعاد تسميته.\n";
    }

    echo str_repeat('─', 78) . "\n";
    echo "applied={$counts['applied']} baseline={$counts['baseline']} pending={$counts['pending']}"
        . " failed={$counts['failed']} modified={$counts['modified']} missing={$counts['missing']}\n";

    // حالات تستدعي انتباه المشغّل تُعيد رمزًا غير صفري في up فقط؛ status للعرض دائمًا.
    return 0;
}

function cmd_up(mysqli $conn, $dryRun)
{
    $files = migrate_scan_files();
    $tracked = migrate_fetch_tracked($conn);

    // سلامة أولًا: ملف مُطبَّق تغيّر محتواه ⇒ لا نتقدم قبل حسمه.
    foreach ($files as $f) {
        if (isset($tracked[$f]) && $tracked[$f]['status'] !== 'failed'
            && $tracked[$f]['checksum'] !== sha1_file(MIGRATE_DIR . '/' . $f)) {
            fwrite(STDERR, "[migrate] رفض: {$f} مُطبَّق سابقًا لكن محتواه تغيّر (checksum mismatch).\n");
            fwrite(STDERR, "          أعد المحتوى الأصلي أو ضع التغيير في ملف ترحيلٍ جديد.\n");
            return 1;
        }
    }

    $queue = array();
    foreach ($files as $f) {
        if (!isset($tracked[$f]) || $tracked[$f]['status'] === 'failed') {
            $queue[] = $f;
        }
    }

    if (empty($queue)) {
        echo "لا ترحيلات معلَّقة — القاعدة مطابقة للملفات.\n";
        return 0;
    }

    echo ($dryRun ? "[dry-run] " : "") . "الترحيلات المعلَّقة (" . count($queue) . "):\n";
    foreach ($queue as $f) {
        echo "  → {$f}\n";
    }
    if ($dryRun) {
        echo "[dry-run] لم يُنفَّذ شيء.\n";
        return 0;
    }

    // لقطة مخطّط تلقائية قبل أي تنفيذ (بديل غياب rollback للـ DDL).
    list($snap, $err) = migrate_export_schema($conn, 'auto_pre_up_');
    if ($err !== '') {
        fwrite(STDERR, "[migrate] فشل أخذ لقطة المخطّط قبل التنفيذ: {$err}\n");
        return 1;
    }
    echo "لقطة ما قبل التنفيذ: " . basename($snap) . "\n\n";

    foreach ($queue as $f) {
        $path = MIGRATE_DIR . '/' . $f;
        $sum = sha1_file($path);
        echo "تطبيق {$f} ...\n";
        $t0 = microtime(true);
        $tablesBefore = migrate_wall_tables($conn); // سور REV-08 §4: لقطة الجداول قبل الملف

        $errText = (substr($f, -4) === '.php')
            ? migrate_run_php_file($path)
            : migrate_run_sql_file($conn, $path);

        // سور الجداول الجديدة: نجاح التنفيذ لا يكفي — الجدول الأعمى يُسقَط ويُحجَب الملف
        if ($errText === '') {
            $errText = migrate_wall_check($conn, $tablesBefore);
        }

        $ms = (int) round((microtime(true) - $t0) * 1000);

        if ($errText === '') {
            migrate_record($conn, $f, $sum, 'applied', $ms, null);
            echo "  ✔ نجح ({$ms}ms)\n";
        } else {
            migrate_record($conn, $f, $sum, 'failed', $ms, $errText);
            fwrite(STDERR, "  ✘ فشل: {$errText}\n");
            fwrite(STDERR, "[migrate] توقّف عند أول فشل. عالج السبب ثم أعد `up` (الملفات idempotent).\n");
            return 1;
        }
    }

    echo "\nاكتمل التطبيق. شغّل `status` للمراجعة.\n";
    return 0;
}

function cmd_mark_applied(mysqli $conn, $target)
{
    $files = migrate_scan_files();
    $tracked = migrate_fetch_tracked($conn);

    $list = array();
    if ($target === '--all-pending') {
        foreach ($files as $f) {
            if (!isset($tracked[$f])) {
                $list[] = $f;
            }
        }
        if (empty($list)) {
            echo "لا ملفات معلَّقة للتسوية.\n";
            return 0;
        }
    } else {
        if (!in_array($target, $files, true)) {
            fwrite(STDERR, "[migrate] الملف غير موجود في migrations/: {$target}\n");
            return 1;
        }
        if (isset($tracked[$target])) {
            fwrite(STDERR, "[migrate] {$target} مسجَّل مسبقًا بحالة {$tracked[$target]['status']}.\n");
            return 1;
        }
        $list[] = $target;
    }

    foreach ($list as $f) {
        migrate_record($conn, $f, sha1_file(MIGRATE_DIR . '/' . $f), 'baseline', 0, null);
        echo "  ✔ سُجِّل كخطّ أساس (لم يُنفَّذ): {$f}\n";
    }
    echo "التسوية تعني: القاعدة تحتوي أثر هذا الملف مسبقًا (طُبِّق يدويًا قبل وجود المُشغِّل).\n";
    return 0;
}

function cmd_baseline(mysqli $conn)
{
    list($file, $err) = migrate_export_schema($conn, 'schema_baseline_');
    if ($err !== '') {
        fwrite(STDERR, "[migrate] فشل التصدير: {$err}\n");
        return 1;
    }
    echo "خطّ الأساس صُدِّر إلى: {$file}\n";
    echo "تذكير: خطّ الأساس المرجعي المعتمد يُؤخذ من قاعدة الإنتاج الحيّة (راجع الدليل).\n";
    return 0;
}

function cmd_help()
{
    echo "مُشغِّل الترحيلات — الاستخدام:\n";
    echo "  php database/migrate.php status\n";
    echo "  php database/migrate.php up [--dry-run]\n";
    echo "  php database/migrate.php mark-applied <filename>|--all-pending\n";
    echo "  php database/migrate.php baseline\n";
    echo "الدليل: docs/MIGRATIONS_GUIDE_ar.md\n";
    return 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// نقطة الدخول
// ─────────────────────────────────────────────────────────────────────────────
migrate_ensure_tracking_table($conn);

$command = isset($argv[1]) ? $argv[1] : 'help';
switch ($command) {
    case 'status':
        exit(cmd_status($conn));
    case 'up':
        exit(cmd_up($conn, in_array('--dry-run', $argv, true)));
    case 'mark-applied':
        if (!isset($argv[2])) {
            fwrite(STDERR, "[migrate] حدّد اسم الملف أو --all-pending\n");
            exit(1);
        }
        exit(cmd_mark_applied($conn, $argv[2]));
    case 'baseline':
        exit(cmd_baseline($conn));
    default:
        exit(cmd_help());
}
