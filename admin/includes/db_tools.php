<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * أدوات قاعدة البيانات للإدارة العليا — نسخ احتياطي واستيراد/استعادة.
 * ───────────────────────────────────────────────────────────────────────────
 * • محصورة في قاعدة EMS وحدها (DB_NAME من .env). خادم MySQL هنا مشترَك مع قواعد
 *   مشاريع أخرى، فلا يجوز أن تلمس هذه الأدوات أي قاعدة سواها إطلاقًا.
 * • تُستدعى حصرًا من admin/settings.php بعد super_admin_require_login() + CSRF.
 * • كلمة مرور القاعدة تُمرَّر عبر ملف اعتماداتٍ مؤقّت (--defaults-extra-file)،
 *   لا في سطر الأوامر (كي لا تظهر في قائمة العمليات).
 * • التنفيذ عبر proc_open بصيغة المصفوفة (بلا صدفة) — لا حقن ولا مزالق اقتباس.
 * • مسارات الأدوات تُكتشف وقت التشغيل (includes/portable_paths.php) — لا مسارَ
 *   مطلقًا لحزمةٍ بعينها، فالمشروع يعمل على WAMP وXAMPP معًا (ج٢ · قابلية النقل).
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once dirname(__DIR__, 2) . '/includes/portable_paths.php';

if (!function_exists('ems_dbtool_bin_dir')) {

    /**
     * مجلد أدوات MySQL ‏(mysqldump/mysql). **لا افتراضَ مثبَّتًا لحزمةٍ بعينها** —
     * كان `C:/xampp/mysql/bin` فكان يعمل على XAMPP ويفشل على أي بيئةٍ سواها
     * (قابلية النقل · PROMPT_cross_env_portability_fix ج٢). الترتيب:
     *   ① `.env` ← MYSQL_BIN_DIR (المصدر المُعتمَد صراحةً)
     *   ② مجلدُ mysqldump المُكتشَفُ من PATH
     *   ③ المواضعُ الشائعةُ للحزمِ المعروفة — **بالفحص `is_dir` لا بالافتراض**
     * وإن أخفقتِ الثلاثةُ رجع '' فيُظهر النداءُ رسالةً تطلب ضبطَ المفتاح.
     */
    function ems_dbtool_bin_dir()
    {
        return ems_portable_mysql_bin_dir();
    }

    /**
     * رسالةُ فشلٍ قابلةٌ للتنفيذ حين لا تُكتشف أداةُ MySQL — تقول ما ينقص وكيف
     * يُضبط، لا «غير موجودة في: » وحدها (تصير فارغةً حين يخفق الاكتشافُ كلُّه).
     */
    function ems_dbtool_bin_hint($tool)
    {
        $dir = ems_dbtool_bin_dir();
        if ($dir === '') {
            return 'تعذّر اكتشافُ مجلد أدوات MySQL على هذا الجهاز. اضبط MYSQL_BIN_DIR '
                . 'في ملف .env على مجلد bin الخاص بخادم قاعدتك — مثال: '
                . 'MYSQL_BIN_DIR=C:/…/mysql/bin  (شخِّص بـ: php tools/doctor.php)';
        }

        return 'أداة ' . $tool . ' غير موجودة في: ' . $dir
            . ' — صحِّح MYSQL_BIN_DIR في .env (شخِّص بـ: php tools/doctor.php)';
    }

    /** مجلد تخزين النسخ (storage/backups — محجوب عن الويب عبر storage/.htaccess). */
    function ems_dbtool_backup_dir()
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /** اسم قاعدة EMS — المصدر الوحيد (.env). لا إدخال مستخدم إطلاقًا. */
    function ems_dbtool_db_name()
    {
        return (string) ems_env('DB_NAME');
    }

    /** حجم القاعدة وعدد الجداول للعرض: ['mb' => float, 'tables' => int]. */
    function ems_dbtool_size_info($conn)
    {
        $db = ems_dbtool_db_name();
        $out = array('mb' => 0.0, 'tables' => 0);
        if ($db === '') {
            return $out;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) mb, COUNT(*) n
               FROM information_schema.tables WHERE table_schema = ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $db);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $out['mb'] = (float) $row['mb'];
                $out['tables'] = (int) $row['n'];
            }
            mysqli_stmt_close($stmt);
        }
        return $out;
    }

    /** حجمٌ مقروء بالبشر. */
    function ems_dbtool_human_size($bytes)
    {
        $bytes = (float) $bytes;
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, ($i === 0 ? 0 : 1)) . ' ' . $units[$i];
    }

    /** كتابة ملف اعتماداتٍ مؤقّت للعميل. يُحذف فور الاستعمال. يُعيد المسار أو null. */
    function ems_dbtool_write_cnf()
    {
        $host = (string) ems_env('DB_HOST');
        $user = (string) ems_env('DB_USER');
        $pass = (string) ems_env('DB_PASS');
        $path = ems_dbtool_backup_dir() . '/.cnf_' . bin2hex(random_bytes(8)) . '.ini';
        // صيغة ملف خيارات MariaDB: القيمة بين علامتَي اقتباس تُجرَّد منهما (يدعم كلمة فارغة/برموز).
        $content = "[client]\n"
                 . 'host="' . $host . "\"\n"
                 . 'user="' . $user . "\"\n"
                 . 'password="' . $pass . "\"\n";
        if (@file_put_contents($path, $content) === false) {
            return null;
        }
        @chmod($path, 0600);
        return $path;
    }

    /**
     * مُشغّل عملية عام عبر proc_open (بلا صدفة). يُعيد رمز الخروج (int)، ويملأ $stderr.
     *
     * @param array       $args        [المسار التنفيذي, وسيط, ...]
     * @param string|null $stdinFile   ملف يُغذّى إلى stdin (للاستيراد) أو null.
     * @param string|null $stdoutFile  ملف يُكتب إليه stdout (للنسخ) أو null.
     */
    function ems_dbtool_run(array $args, $stdinFile, $stdoutFile, &$stderr)
    {
        $stderr = '';
        $desc = array(
            0 => ($stdinFile !== null) ? array('file', $stdinFile, 'r') : array('pipe', 'r'),
            1 => ($stdoutFile !== null) ? array('file', $stdoutFile, 'w') : array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $proc = @proc_open($args, $desc, $pipes);
        if (!is_resource($proc)) {
            $stderr = 'تعذّر تشغيل الأداة (proc_open).';
            return 1;
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        $stderr = isset($pipes[2]) ? (string) stream_get_contents($pipes[2]) : '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        return proc_close($proc);
    }

    /**
     * إنشاء نسخة احتياطية من قاعدة EMS. يُعيد مسار ملف .sql عند النجاح أو null.
     * النسخ بلا --databases: جداول القاعدة فقط (لا CREATE DATABASE)، كي يبقى أي
     * استيراد لاحق موجَّهًا للقاعدة التي نحدّدها نحن حصرًا.
     *
     * @param string $prefix بادئة اسم الملف (backup أو autobackup_before_import…).
     */
    function ems_dbtool_backup(&$err, $prefix = 'ems_backup')
    {
        $err = '';
        $db = ems_dbtool_db_name();
        if ($db === '') {
            $err = 'اسم قاعدة البيانات غير مضبوط في .env';
            return null;
        }
        $dumpBin = ems_dbtool_bin_dir() . '/mysqldump.exe';
        if (!is_file($dumpBin)) {
            $dumpBin = ems_dbtool_bin_dir() . '/mysqldump';
        }
        if (!is_file($dumpBin)) {
            $err = ems_dbtool_bin_hint('mysqldump');
            return null;
        }
        $cnf = ems_dbtool_write_cnf();
        if ($cnf === null) {
            $err = 'تعذّر تجهيز ملف الاعتمادات المؤقّت.';
            return null;
        }
        $safePrefix = preg_replace('/[^a-z0-9_]/i', '', $prefix);
        $out = ems_dbtool_backup_dir() . '/' . $safePrefix . '_' . date('Ymd_His') . '.sql';

        $args = array(
            $dumpBin,
            '--defaults-extra-file=' . $cnf,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--add-drop-table',
            '--default-character-set=utf8mb4',
            $db,
        );
        $stderr = '';
        $code = ems_dbtool_run($args, null, $out, $stderr);
        @unlink($cnf);

        if ($code !== 0 || !is_file($out) || filesize($out) === 0) {
            @unlink($out);
            $err = 'فشل النسخ الاحتياطي' . (trim($stderr) !== '' ? ': ' . trim($stderr) : ' (رمز ' . $code . ')');
            return null;
        }
        return $out;
    }

    /**
     * فحص أمان لملف SQL قبل الاستيراد: يرفض العبارات العابرة لقاعدة EMS أو الخطيرة
     * (DROP/CREATE DATABASE، USE قاعدةٍ أخرى، GRANT/CREATE USER، INTO OUTFILE، LOAD DATA…).
     * دفاعٌ في العمق كي لا يعبث ملفٌ مرفوعٌ بالخطأ بقواعد المشاريع الأخرى على الخادم.
     *
     * @return bool  true إن كان الملف آمنًا للاستيراد في قاعدة EMS.
     */
    function ems_dbtool_scan_sql($sqlFile, &$reason)
    {
        $reason = '';
        $db = strtolower(ems_dbtool_db_name());
        $fh = @fopen($sqlFile, 'r');
        if (!$fh) {
            $reason = 'تعذّر قراءة الملف للفحص.';
            return false;
        }
        // الأنماط مثبَّتة على بداية السطر (^): mysqldump يكتب كل عبارةٍ عليا من العمود 0،
        // بينما بيانات INSERT تبدأ بـ INSERT INTO — فلا تُطابَق كلمات كـ grant داخل القيم
        // (تفاديًا للإيجابيات الكاذبة). الغرض الأساسي: منع عبثِ ملفٍ عابرٍ بقواعد أخرى.
        $deny = array(
            '/^\s*drop\s+database\b/i'    => 'حذف قاعدة بيانات (DROP DATABASE)',
            '/^\s*create\s+database\b/i'  => 'إنشاء قاعدة بيانات (CREATE DATABASE)',
            '/^\s*(grant|revoke)\b/i'     => 'صلاحيات (GRANT/REVOKE)',
            '/^\s*create\s+user\b/i'      => 'إنشاء مستخدم (CREATE USER)',
            '/^\s*drop\s+user\b/i'        => 'حذف مستخدم (DROP USER)',
            '/^\s*load\s+data\b/i'        => 'تحميل بيانات من ملف (LOAD DATA)',
        );
        $lineNo = 0;
        while (($line = fgets($fh)) !== false) {
            $lineNo++;
            $trim = ltrim($line);
            if ($trim === '' || $trim[0] === '-' || $trim[0] === '#' || strncmp($trim, '/*', 2) === 0) {
                continue; // تعليق
            }
            foreach ($deny as $re => $label) {
                if (preg_match($re, $line)) {
                    $reason = 'الملف يحتوي عبارة غير مسموحة: ' . $label . ' (سطر ' . $lineNo . ')';
                    fclose($fh);
                    return false;
                }
            }
            // USE مسموح فقط لقاعدة EMS نفسها (مثبَّت على بداية السطر)
            if (preg_match('/^\s*use\s+`?([a-z0-9_]+)`?/i', $line, $m)) {
                if (strtolower($m[1]) !== $db) {
                    $reason = 'الملف يبدّل إلى قاعدة أخرى: USE ' . $m[1] . ' (سطر ' . $lineNo . ')';
                    fclose($fh);
                    return false;
                }
            }
        }
        fclose($fh);
        return true;
    }

    /**
     * استيراد/استعادة ملف SQL إلى قاعدة EMS. يأخذ نسخةً احتياطيةً وقائيةً أولًا،
     * ثم يفحص الملف، ثم يستورد. القاعدة الهدف تُفرَض دائمًا على قاعدة EMS.
     *
     * @param string      $sqlFile     مسار ملف .sql المصدر.
     * @param string|null $autobackup  (خرج) مسار النسخة الوقائية التي أُخذت قبل الاستيراد.
     * @return bool
     */
    function ems_dbtool_import($sqlFile, &$err, &$autobackup)
    {
        $err = '';
        $autobackup = null;
        if (!is_file($sqlFile) || filesize($sqlFile) === 0) {
            $err = 'ملف SQL غير صالح أو فارغ.';
            return false;
        }
        $reason = '';
        if (!ems_dbtool_scan_sql($sqlFile, $reason)) {
            $err = $reason;
            return false;
        }
        $db = ems_dbtool_db_name();
        if ($db === '') {
            $err = 'اسم قاعدة البيانات غير مضبوط في .env';
            return false;
        }
        $mysqlBin = ems_dbtool_bin_dir() . '/mysql.exe';
        if (!is_file($mysqlBin)) {
            $mysqlBin = ems_dbtool_bin_dir() . '/mysql';
        }
        if (!is_file($mysqlBin)) {
            $err = ems_dbtool_bin_hint('mysql');
            return false;
        }

        // 1) نسخة وقائية قبل الاستبدال (شبكة أمان)
        $bkErr = '';
        $autobackup = ems_dbtool_backup($bkErr, 'ems_autobackup_before_import');
        if ($autobackup === null) {
            $err = 'أُوقِف الاستيراد: تعذّرت النسخة الوقائية قبل الاستبدال (' . $bkErr . ').';
            return false;
        }

        // 2) الاستيراد — الهدف قاعدة EMS حصرًا (يُمرَّر منّا لا من الملف)
        $cnf = ems_dbtool_write_cnf();
        if ($cnf === null) {
            $err = 'تعذّر تجهيز ملف الاعتمادات المؤقّت.';
            return false;
        }
        $args = array(
            $mysqlBin,
            '--defaults-extra-file=' . $cnf,
            '--default-character-set=utf8mb4',
            $db,
        );
        $stderr = '';
        $code = ems_dbtool_run($args, $sqlFile, null, $stderr);
        @unlink($cnf);

        if ($code !== 0) {
            $err = 'فشل الاستيراد' . (trim($stderr) !== '' ? ': ' . trim($stderr) : ' (رمز ' . $code . ')')
                 . ' — بياناتك السابقة محفوظة في النسخة الوقائية: ' . basename($autobackup);
            return false;
        }
        return true;
    }

    /** قائمة النسخ المخزّنة (الأحدث أولًا). كل عنصر: [name, size, size_h, mtime, is_auto]. */
    function ems_dbtool_list_backups()
    {
        $dir = ems_dbtool_backup_dir();
        $items = array();
        foreach (glob($dir . '/ems_*.sql') ?: array() as $path) {
            $name = basename($path);
            $kind = 'يدوية';
            if (strpos($name, 'ems_scheduled_') === 0) {
                $kind = 'مجدولة';
            } elseif (strpos($name, 'ems_autobackup') === 0) {
                $kind = 'وقائية';
            } elseif (strpos($name, 'ems_uploaded_') === 0) {
                $kind = 'مرفوعة';
            }
            $items[] = array(
                'name'    => $name,
                'size'    => filesize($path),
                'size_h'  => ems_dbtool_human_size(filesize($path)),
                'mtime'   => filemtime($path),
                'kind'    => $kind,
                'is_auto' => (strpos($name, 'autobackup') !== false),
            );
        }
        usort($items, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });
        return $items;
    }

    /**
     * يتحقّق أن اسم ملفٍ يخصّ نسخةً صالحةً داخل مجلد النسخ (بلا اجتياز مسار)،
     * ويُعيد المسار المطلق المُتحقَّق منه أو null.
     */
    function ems_dbtool_resolve_backup($name)
    {
        if (!preg_match('/^ems_[a-z0-9_]+_\d{8}_\d{6}\.sql$/i', (string) $name)) {
            return null;
        }
        $dir = realpath(ems_dbtool_backup_dir());
        $path = realpath($dir . '/' . $name);
        if ($path === false || $dir === false || strncmp($path, $dir, strlen($dir)) !== 0) {
            return null; // خارج المجلد — رُفِض
        }
        return $path;
    }

    /**
     * بثّ ملف نسخةٍ كتنزيلٍ للمتصفح ثم إنهاء التنفيذ. ينظّف كل مخازن الإخراج
     * (config.php يفتح ob_start لحقن CSRF وإصلاح الترميز) كي لا يفسد الملف.
     */
    function ems_dbtool_stream_download($path)
    {
        if (!is_file($path)) {
            return; // لا شيء نبثّه — يعود المعالج لعرض رسالة خطأ
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
        readfile($path);
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الجدولة التلقائية — إعداداتٌ في storage/backups/backup_schedule.json
    // (بلا تعديل مخطّط)، ومُشغّلٌ يأخذ نسخةً متى حان الموعد ويُقلّم القديمة.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * مسار مُنفّذ PHP لإطلاق العمليات الخلفية. **بلا افتراضٍ مثبَّت** — كان
     * `C:/xampp/php/php.exe` فكان يفشل خارج XAMPP (قابلية النقل · ج٢).
     * الاكتشافُ في ems_portable_php_bin(): ‏.env ← PHP_BINARY ← PATH ← فحصُ الجذور.
     */
    function ems_dbtool_php_bin()
    {
        return ems_portable_php_bin();
    }

    /** مسار ملف إعدادات الجدولة. */
    function ems_dbtool_schedule_path()
    {
        return ems_dbtool_backup_dir() . '/backup_schedule.json';
    }

    /** القيم الافتراضية للجدولة. */
    function ems_dbtool_schedule_defaults()
    {
        return array(
            'enabled'       => false,
            'interval_days' => 1,
            'retention'     => 14,
            'last_run_at'   => null,
            'last_status'   => null,   // success | error
            'last_message'  => null,
            'last_file'     => null,
        );
    }

    /** قراءة إعدادات الجدولة (مع دمج الافتراضات). */
    function ems_dbtool_schedule_get()
    {
        $def = ems_dbtool_schedule_defaults();
        $path = ems_dbtool_schedule_path();
        if (is_file($path)) {
            $j = json_decode((string) @file_get_contents($path), true);
            if (is_array($j)) {
                return array_merge($def, $j);
            }
        }
        return $def;
    }

    /** حفظ إعدادات الجدولة (مع تعقيم القيم). */
    function ems_dbtool_schedule_save($cfg)
    {
        $out = array_merge(ems_dbtool_schedule_defaults(), is_array($cfg) ? $cfg : array());
        $out['enabled']       = !empty($out['enabled']);
        $out['interval_days'] = max(1, min(365, intval($out['interval_days'])));
        $out['retention']     = max(1, min(365, intval($out['retention'])));
        return @file_put_contents(
            ems_dbtool_schedule_path(),
            json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    /** هل حان موعد نسخةٍ مجدولة؟ (هامش ساعة كي لا يفوت التشغيل اليومي بفارق دقائق). */
    function ems_dbtool_schedule_due($cfg = null, $nowTs = null)
    {
        if ($cfg === null) {
            $cfg = ems_dbtool_schedule_get();
        }
        if (empty($cfg['enabled'])) {
            return false;
        }
        if ($nowTs === null) {
            $nowTs = time();
        }
        if (empty($cfg['last_run_at'])) {
            return true; // لم تُؤخَذ نسخة قط
        }
        $last = strtotime((string) $cfg['last_run_at']);
        if ($last === false) {
            return true;
        }
        $intervalSec = max(1, intval($cfg['interval_days'])) * 86400;
        return ($nowTs - $last) >= ($intervalSec - 3600);
    }

    /** نصٌّ يصف الموعد التالي للعرض. */
    function ems_dbtool_schedule_next_text($cfg = null)
    {
        if ($cfg === null) {
            $cfg = ems_dbtool_schedule_get();
        }
        if (empty($cfg['enabled'])) {
            return 'متوقّفة';
        }
        if (empty($cfg['last_run_at'])) {
            return 'عند أقرب تشغيلٍ مجدول';
        }
        $next = strtotime((string) $cfg['last_run_at']) + max(1, intval($cfg['interval_days'])) * 86400;
        return date('Y/m/d H:i', $next);
    }

    /** تقليم النسخ المجدولة للإبقاء على أحدث $keep منها فقط. يُعيد عدد المحذوف. */
    function ems_dbtool_prune_scheduled($keep)
    {
        $keep = max(1, intval($keep));
        $files = glob(ems_dbtool_backup_dir() . '/ems_scheduled_*.sql') ?: array();
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        $deleted = 0;
        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * تشغيل النسخة المجدولة: يأخذ نسخةً إن كانت الجدولة مفعّلةً وحان موعدها (أو $force)،
     * ثم يُقلّم القديمة ويحدّث حالة آخر تشغيل. آمنٌ للاستدعاء المتكرّر (قفل 30 دقيقة).
     *
     * @return array ['ok'=>bool, 'skipped'=>bool, 'message'=>string, 'file'=>?string]
     */
    function ems_dbtool_run_scheduled(&$err, $force = false)
    {
        $err = '';
        $cfg = ems_dbtool_schedule_get();
        if (!$force && empty($cfg['enabled'])) {
            return array('ok' => false, 'skipped' => true, 'message' => 'الجدولة معطّلة.');
        }
        if (!$force && !ems_dbtool_schedule_due($cfg)) {
            return array('ok' => false, 'skipped' => true, 'message' => 'لم يحن موعد النسخة بعد.');
        }
        $lock = ems_dbtool_backup_dir() . '/.sched.lock';
        if (is_file($lock) && (time() - filemtime($lock)) < 1800) {
            return array('ok' => false, 'skipped' => true, 'message' => 'نسخة مجدولة قيد التنفيذ بالفعل.');
        }
        @touch($lock);

        $bkErr = '';
        $path = ems_dbtool_backup($bkErr, 'ems_scheduled');
        $now = date('Y-m-d H:i:s');
        $cfg['last_run_at'] = $now; // نُقدّم المؤقّت في الحالتين (لا حلقة فشلٍ متكرّرة)

        if ($path) {
            $pruned = ems_dbtool_prune_scheduled($cfg['retention']);
            $cfg['last_status']  = 'success';
            $cfg['last_message'] = 'تم — ' . basename($path) . ' (' . ems_dbtool_human_size(filesize($path)) . ')'
                                 . ($pruned ? " — حُذفت {$pruned} نسخة قديمة" : '');
            $cfg['last_file']    = basename($path);
            ems_dbtool_schedule_save($cfg);
            @unlink($lock);
            return array('ok' => true, 'skipped' => false, 'message' => $cfg['last_message'], 'file' => basename($path));
        }

        $cfg['last_status']  = 'error';
        $cfg['last_message'] = $bkErr;
        ems_dbtool_schedule_save($cfg);
        @unlink($lock);
        $err = $bkErr;
        return array('ok' => false, 'skipped' => false, 'message' => 'فشل النسخة المجدولة: ' . $bkErr, 'file' => null);
    }

    /** إطلاق عمليةٍ خلفيةٍ غير حاجبة على Windows (تكمل بعد انتهاء طلب الويب). */
    function ems_dbtool_spawn_background(array $args)
    {
        $parts = array();
        foreach ($args as $a) {
            $parts[] = '"' . $a . '"';
        }
        $cmd = 'cmd /c start /B "" ' . implode(' ', $parts);
        $h = @popen($cmd, 'r');
        if ($h !== false) {
            @pclose($h);
        }
    }

    /**
     * الشبكة الاحتياطية: تُستدعى على كل صفحة إدارة. إن كانت الجدولة مفعّلةً وحان
     * الموعد، تُطلق النسخة في الخلفية (بلا حجب الصفحة) — لتعويض أي تشغيلٍ فائتٍ
     * لمهمّة النظام. مُخنَّقة بعلامةٍ زمنية كي لا تُطلَق مرارًا من تبويبات متعدّدة.
     */
    function ems_dbtool_lazy_tick()
    {
        $cfg = ems_dbtool_schedule_get();
        if (empty($cfg['enabled']) || !ems_dbtool_schedule_due($cfg)) {
            return;
        }
        $spawn = ems_dbtool_backup_dir() . '/.sched.spawn';
        if (is_file($spawn) && (time() - filemtime($spawn)) < 1800) {
            return; // أُطلقت قريبًا
        }
        @touch($spawn);
        ems_dbtool_spawn_background(array(
            ems_dbtool_php_bin(),
            dirname(__DIR__) . '/cron_backup.php',
            '--lazy',
        ));
    }
}
