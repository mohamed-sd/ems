<?php
/**
 * app/Install/Installer.php — مُثبِّت EMS (المرحلة ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * ينصب نظامًا كاملًا على قاعدةٍ فارغة: المخطَّطُ ثمّ البذرةُ ثمّ ثلاثيّةُ الدخول.
 *
 * لا يطبع حرفًا ولا يُنهي العملية — كلُّ خطوةٍ تُرجع نتيجةً منظَّمة، والمداخلُ
 * (CLI والويب) هي من تعرضها. هذا ما يجعل المنطقَ واحدًا خلف واجهتين.
 *
 * ── لماذا «قاعدةٌ فارغة» شرطٌ صلب ──────────────────────────────────────────
 * MySQL لا يتراجع عن DDL داخل معاملة، فلا سبيلَ لتثبيتٍ ذرّيٍّ حقيقي. البديلُ
 * الأمين: نرفض العملَ ما لم تكن القاعدة خالية، فالفشلُ في منتصف الطريق لا
 * يُتلف شيئًا — تُسقَط القاعدةُ ويُعاد التثبيت. مُثبِّتٌ يعمل فوق قاعدةٍ عامرة
 * هو أداةُ إتلافٍ لا أداةُ تنصيب.
 *
 * ── الثلاثيّة ───────────────────────────────────────────────────────────────
 * `login.php` يرفض أيَّ حسابٍ بلا موظّفٍ مُسنَد (عدا المدير الأعلى -1)، فمُثبِّتٌ
 * يُنشئ صفَّ `users` وحده يُنتج نظامًا لا يمكن الدخولُ إليه. لذا يُنشئ المُثبِّت
 * ثلاثةَ صفوفٍ مترابطة: شركة → موظّف → حساب مربوطٌ بـ employee_id.
 *
 * ── الترقيةُ بعد التثبيت ────────────────────────────────────────────────────
 * المُثبِّتُ للتنصيب الأوّل فقط. تغييرُ المخطَّط على قاعدةٍ عاملة يبقى من نصيب
 * `database/migrate.php up` — ولذلك يسجّل المُثبِّتُ كلَّ ترحيلٍ موجودٍ وقتَه
 * `baseline`، فلا يُعاد تطبيقُ ما هو داخلٌ في المخطَّط أصلًا.
 */

namespace App\Install;

use mysqli;

class Installer
{
    const MIN_PHP = '7.4.0';
    const REQUIRED_EXT = array('mysqli', 'mbstring', 'json');

    /** الدورُ الافتراضيُّ لحساب المُثبِّت (1 = إدارة التشغيل — أوسعُ دورٍ تشغيلي). */
    const ADMIN_ROLE = '1';

    /** @var array */
    private $cfg;

    /** @var mysqli|null */
    private $conn = null;

    /** @var string جذرُ المشروع */
    private $root;

    /** @var array سجلُّ الخطوات المنفَّذة */
    private $steps = array();

    /**
     * @param array $cfg مفاتيحُ متوقَّعة:
     *   db_host db_name db_user db_pass db_create(bool)
     *   company_name company_email company_currency company_timezone
     *   admin_name admin_username admin_password admin_email admin_phone
     *   write_env(bool)
     */
    public function __construct(array $cfg, $root = null)
    {
        $this->cfg = $cfg + array(
            'db_host'          => 'localhost',
            'db_name'          => '',
            'db_user'          => '',
            'db_pass'          => '',
            'db_create'        => false,
            'company_name'     => '',
            'company_email'    => '',
            'company_currency' => 'SDG',
            'company_timezone' => 'Africa/Khartoum',
            'admin_name'       => '',
            'admin_username'   => '',
            'admin_password'   => '',
            'admin_email'      => '',
            'admin_phone'      => '',
            'write_env'        => true,
        );
        $this->root = $root !== null ? rtrim($root, "/\\") : dirname(dirname(__DIR__));
    }

    public function schemaDir()
    {
        return $this->root . '/database/schema';
    }

    public function markerPath()
    {
        return $this->root . '/.installed';
    }

    public function isInstalled()
    {
        return is_file($this->markerPath());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الفحصُ القبلي
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * فحوصٌ لا تكتب شيئًا. كلُّ عنصر: ['ok'=>bool,'label'=>string,'detail'=>string,'fatal'=>bool]
     */
    public function preflight()
    {
        $c = array();

        $c[] = $this->check(
            version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'إصدار PHP',
            PHP_VERSION . ' (المطلوب ' . self::MIN_PHP . ' فأعلى)'
        );

        foreach (self::REQUIRED_EXT as $ext) {
            $c[] = $this->check(extension_loaded($ext), "إضافة {$ext}", extension_loaded($ext) ? 'محمَّلة' : 'مفقودة');
        }

        $c[] = $this->check(!$this->isInstalled(), 'علامةُ التثبيت', $this->isInstalled()
            ? 'الملف .installed موجود — النظام مثبَّتٌ مسبقًا. احذفه عمدًا إن أردت إعادةَ التثبيت.'
            : 'غير موجودة (تثبيتٌ أوّل)');

        // مصنوعاتُ التثبيت وسلامةُ بصماتها
        $c = array_merge($c, $this->checkArtifacts());

        // إمكانيةُ الكتابة — .env و.installed يُكتبان في الجذر
        $c[] = $this->check(is_writable($this->root), 'الكتابة في جذر المشروع', $this->root);

        // الاتصالُ وحالةُ القاعدة
        $c = array_merge($c, $this->checkDatabase());

        // المدخلاتُ الإلزامية
        foreach (array(
            'db_name'        => 'اسم قاعدة البيانات',
            'company_name'   => 'اسم الشركة',
            'company_email'  => 'بريد الشركة',
            'admin_name'     => 'اسم المستخدم الكامل',
            'admin_username' => 'اسم الدخول',
            'admin_password' => 'كلمة المرور',
        ) as $k => $label) {
            $c[] = $this->check(trim((string) $this->cfg[$k]) !== '', $label, trim((string) $this->cfg[$k]) !== '' ? 'مُدخَل' : 'مطلوب');
        }

        if (trim((string) $this->cfg['admin_password']) !== '' && strlen($this->cfg['admin_password']) < 8) {
            $c[] = $this->check(false, 'طول كلمة المرور', 'ثمانيةُ محارفَ على الأقل');
        }

        return $c;
    }

    /** هل اجتاز الفحصُ القبليُّ كلَّه؟ */
    public static function passed(array $checks)
    {
        foreach ($checks as $c) {
            if (!$c['ok']) {
                return false;
            }
        }
        return true;
    }

    private function checkArtifacts()
    {
        $out = array();
        $dir = $this->schemaDir();
        $manifestPath = $dir . '/MANIFEST.json';

        if (!is_file($manifestPath)) {
            $out[] = $this->check(false, 'بيانُ المصنوعات', 'MANIFEST.json مفقود — شغّل `php database/migrate.php dump-schema`');
            return $out;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || !isset($manifest['files'])) {
            $out[] = $this->check(false, 'بيانُ المصنوعات', 'MANIFEST.json تالفٌ أو بلا مفتاح files');
            return $out;
        }
        $out[] = $this->check(true, 'بيانُ المصنوعات', 'مولَّدٌ في ' . (isset($manifest['generated_at']) ? $manifest['generated_at'] : '؟'));

        foreach ($manifest['files'] as $name => $meta) {
            $path = $dir . '/' . $name;
            if (!is_file($path)) {
                $out[] = $this->check(false, "الملف {$name}", 'مفقود');
                continue;
            }
            $sha = sha1((string) file_get_contents($path));
            $ok = isset($meta['sha1']) && $sha === $meta['sha1'];
            $out[] = $this->check(
                $ok,
                "بصمة {$name}",
                $ok ? 'مطابقة' : 'غيرُ مطابقة — الملف حُرِّر بيدٍ بعد التوليد. أعِد dump-schema.'
            );
        }
        return $out;
    }

    private function checkDatabase()
    {
        $out = array();
        $err = $this->connect();
        if ($err !== '') {
            $out[] = $this->check(false, 'الاتصال بالقاعدة', $err);
            return $out;
        }
        $out[] = $this->check(true, 'الاتصال بالقاعدة', $this->cfg['db_user'] . '@' . $this->cfg['db_host']);

        $exists = $this->databaseExists($this->cfg['db_name']);
        if (!$exists) {
            $out[] = $this->check(
                (bool) $this->cfg['db_create'],
                'القاعدة ' . $this->cfg['db_name'],
                $this->cfg['db_create'] ? 'غيرُ موجودةٍ — سيُنشئها المُثبِّت' : 'غيرُ موجودة. أنشئها أو فعّل خيار الإنشاء.'
            );
            return $out;
        }

        if (!$this->conn->select_db($this->cfg['db_name'])) {
            $out[] = $this->check(false, 'اختيار القاعدة', $this->conn->error);
            return $out;
        }
        $n = $this->objectCount();
        $out[] = $this->check(
            $n === 0,
            'القاعدة فارغة',
            $n === 0 ? 'نعم (صفر كائن)' : "تحتوي {$n} كائنًا — المُثبِّتُ يرفض العملَ فوق قاعدةٍ عامرة."
        );
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // التنفيذ
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @return array ['ok'=>bool, 'steps'=>[...], 'error'=>string, 'summary'=>[...]]
     */
    public function install()
    {
        $this->steps = array();

        $checks = $this->preflight();
        if (!self::passed($checks)) {
            return $this->fail('الفحصُ القبليُّ لم يجتز — عالج الملاحظاتِ ثم أعِد المحاولة.');
        }

        // ① القاعدة
        if (!$this->databaseExists($this->cfg['db_name'])) {
            $name = $this->qi($this->cfg['db_name']);
            if (!$this->conn->query("CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                return $this->fail('تعذّر إنشاء القاعدة: ' . $this->conn->error);
            }
            $this->step('أُنشئت القاعدة ' . $this->cfg['db_name'], 'utf8mb4_unicode_ci');
        }
        if (!$this->conn->select_db($this->cfg['db_name'])) {
            return $this->fail('تعذّر اختيار القاعدة: ' . $this->conn->error);
        }
        $this->conn->set_charset('utf8mb4');
        $this->conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

        // ② المخطّط
        $err = $this->runSqlFile($this->schemaDir() . '/schema.sql');
        if ($err !== '') {
            return $this->fail('فشل استيراد المخطّط: ' . $err);
        }
        $this->step('استُورد المخطّط', $this->objectCount() . ' كائنًا');

        // ③ الشركة — تسبق البذرةَ المستأجَرة لأن معرّفَها يُحقن فيها
        $companyId = $this->createCompany();
        if ($companyId <= 0) {
            return $this->fail('تعذّر إنشاء الشركة: ' . $this->conn->error);
        }
        $this->step('أُنشئت الشركة', $this->cfg['company_name'] . ' (id=' . $companyId . ')');

        // ④ البذرة
        $err = $this->runSqlFile(
            $this->schemaDir() . '/seed_reference.sql',
            array(SchemaDumper::COMPANY_PLACEHOLDER => (string) $companyId)
        );
        if ($err !== '') {
            return $this->fail('فشل استيراد البذرة: ' . $err);
        }
        $this->step('استُوردت البذرة المرجعية', 'company_id=' . $companyId);

        // ⑤ الموظّف ثمّ الحساب — «لا حساب بلا موظّف»
        $employeeId = $this->createEmployee($companyId);
        if ($employeeId <= 0) {
            return $this->fail('تعذّر إنشاء الموظّف: ' . $this->conn->error);
        }
        $this->step('أُنشئ الموظّف', $this->cfg['admin_name'] . ' (id=' . $employeeId . ')');

        $userId = $this->createUser($companyId, $employeeId);
        if ($userId <= 0) {
            return $this->fail('تعذّر إنشاء الحساب: ' . $this->conn->error);
        }
        $this->step('أُنشئ الحساب', $this->cfg['admin_username'] . ' → employee_id=' . $employeeId);

        // ⑥ تسويةُ سجلّ الترحيلات — ما في المخطَّط لا يُعاد تطبيقُه
        $marked = $this->markMigrationsBaseline();
        $this->step('سُوّي سجلُّ الترحيلات', $marked . ' ملفًّا كخطِّ أساس');

        // ⑦ ملفّ البيئة
        if ($this->cfg['write_env']) {
            $envErr = $this->writeEnv();
            if ($envErr !== '') {
                // لا يُفشِل التثبيت: القاعدةُ صارت سليمة، والملفُّ يُكتب يدويًّا.
                $this->step('⚠ تعذّرت كتابة .env', $envErr);
            } else {
                $this->step('كُتب ملفّ .env', 'راجع المفاتيح قبل التشغيل');
            }
        }

        // ⑧ العلامة
        $markerErr = $this->writeMarker($companyId, $userId);
        if ($markerErr !== '') {
            $this->step('⚠ تعذّرت كتابة .installed', $markerErr);
        } else {
            $this->step('كُتبت علامةُ التثبيت', '.installed');
        }

        return array(
            'ok'      => true,
            'steps'   => $this->steps,
            'error'   => '',
            'summary' => array(
                'database'    => $this->cfg['db_name'],
                'objects'     => $this->objectCount(),
                'company_id'  => $companyId,
                'employee_id' => $employeeId,
                'user_id'     => $userId,
                'username'    => $this->cfg['admin_username'],
            ),
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // خطواتٌ مفردة
    // ═══════════════════════════════════════════════════════════════════════

    private function createCompany()
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO `admin_companies`
             (`company_name`, `name`, `company_name_ar`, `email`, `status`, `currency`, `timezone`, `created_at`)
             VALUES (?, ?, ?, ?, 'active', ?, ?, NOW())"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'ssssss',
            $this->cfg['company_name'],
            $this->cfg['company_name'],
            $this->cfg['company_name'],
            $this->cfg['company_email'],
            $this->cfg['company_currency'],
            $this->cfg['company_timezone']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        return $id;
    }

    private function createEmployee($companyId)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO `employees` (`company_id`, `name`, `email`, `created_at`)
             VALUES (?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return 0;
        }
        $email = $this->cfg['admin_email'] !== '' ? $this->cfg['admin_email'] : null;
        $stmt->bind_param('iss', $companyId, $this->cfg['admin_name'], $email);
        $ok = $stmt->execute();
        $id = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        return $id;
    }

    private function createUser($companyId, $employeeId)
    {
        $hash = password_hash($this->cfg['admin_password'], PASSWORD_DEFAULT);
        $role = self::ADMIN_ROLE;
        $stmt = $this->conn->prepare(
            "INSERT INTO `users`
             (`name`, `username`, `email`, `password`, `phone`, `role`, `company_id`, `employee_id`,
              `status`, `project_id`, `parent_id`, `created_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', '0', '0', NOW())"
        );
        if (!$stmt) {
            return 0;
        }
        $email = $this->cfg['admin_email'] !== '' ? $this->cfg['admin_email'] : null;
        $phone = $this->cfg['admin_phone'] !== '' ? $this->cfg['admin_phone'] : null;
        $stmt->bind_param(
            'ssssssii',
            $this->cfg['admin_name'],
            $this->cfg['admin_username'],
            $email,
            $hash,
            $phone,
            $role,
            $companyId,
            $employeeId
        );
        $ok = $stmt->execute();
        $id = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        return $id;
    }

    /**
     * كلُّ ترحيلٍ موجودٍ على القرص وقتَ التثبيت أثرُه داخلٌ في المخطَّط سلفًا،
     * فيُسجَّل `baseline` (مسجَّلٌ ولم يُنفَّذ) — تمامًا كما يفعل mark-applied.
     */
    private function markMigrationsBaseline()
    {
        $dir = $this->root . '/database/migrations';
        if (!is_dir($dir)) {
            return 0;
        }
        $stmt = $this->conn->prepare(
            "INSERT INTO `schema_migrations` (filename, checksum, status, applied_at, execution_ms, applied_by)
             VALUES (?, ?, 'baseline', NOW(), 0, 'installer')
             ON DUPLICATE KEY UPDATE filename = filename"
        );
        if (!$stmt) {
            return 0;
        }
        $n = 0;
        foreach (scandir($dir) as $f) {
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/', $f)) {
                continue;
            }
            $sum = sha1_file($dir . '/' . $f);
            $stmt->bind_param('ss', $f, $sum);
            if ($stmt->execute()) {
                $n++;
            }
        }
        $stmt->close();
        return $n;
    }

    /**
     * يُولَّد من `.env.example` لا من قائمةِ مفاتيحَ مكرّرةٍ هنا — فأيُّ مفتاحٍ
     * جديدٍ يُضاف للقالب يصل المُثبِّتَ تلقائيًّا بتعليقاته الموثِّقة.
     * لا يُكتب فوق `.env` قائم.
     */
    private function writeEnv()
    {
        $target = $this->root . '/.env';
        if (is_file($target)) {
            return 'الملف موجودٌ سلفًا — لم يُمَسّ';
        }
        $tpl = $this->root . '/.env.example';
        if (!is_file($tpl)) {
            return '.env.example مفقود';
        }
        $content = (string) file_get_contents($tpl);

        $values = array(
            'DB_HOST'          => $this->cfg['db_host'],
            'DB_USER'          => $this->cfg['db_user'],
            'DB_PASS'          => $this->cfg['db_pass'],
            'DB_NAME'          => $this->cfg['db_name'],
            'FINANCE_CRON_KEY'   => $this->randomKey(),
            'TRANSPORT_CRON_KEY' => $this->randomKey(),
            'REQUESTS_CRON_KEY'  => $this->randomKey(),
            'EVENTS_CRON_KEY'    => $this->randomKey(),
        );
        foreach ($values as $k => $v) {
            $content = preg_replace(
                '/^' . preg_quote($k, '/') . '=.*$/m',
                $k . '=' . $v,
                $content,
                1
            );
        }

        if (file_put_contents($target, $content) === false) {
            return 'تعذّرت الكتابة';
        }
        @chmod($target, 0640);
        return '';
    }

    private function writeMarker($companyId, $userId)
    {
        $manifest = @json_decode((string) @file_get_contents($this->schemaDir() . '/MANIFEST.json'), true);
        $payload = array(
            'installed_at' => date('Y-m-d H:i:s'),
            'database'     => $this->cfg['db_name'],
            'company_id'   => $companyId,
            'user_id'      => $userId,
            'php'          => PHP_VERSION,
            'manifest'     => is_array($manifest) ? array(
                'generated_at' => isset($manifest['generated_at']) ? $manifest['generated_at'] : null,
                'files'        => isset($manifest['files']) ? $manifest['files'] : null,
            ) : null,
        );
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->markerPath(), $json . "\n") === false ? 'تعذّرت الكتابة' : '';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // أدوات
    // ═══════════════════════════════════════════════════════════════════════

    private function connect()
    {
        if ($this->conn instanceof mysqli) {
            return '';
        }
        $c = @new mysqli($this->cfg['db_host'], $this->cfg['db_user'], $this->cfg['db_pass']);
        if ($c->connect_error) {
            return $c->connect_error;
        }
        $c->set_charset('utf8mb4');
        $c->query("SET collation_connection = 'utf8mb4_unicode_ci'");
        $this->conn = $c;
        return '';
    }

    private function databaseExists($name)
    {
        if (!($this->conn instanceof mysqli)) {
            return false;
        }
        $n = $this->conn->real_escape_string($name);
        $res = $this->conn->query("SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$n}'");
        return $res && $res->num_rows > 0;
    }

    private function objectCount()
    {
        $res = $this->conn->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        if (!$res) {
            return -1;
        }
        $row = $res->fetch_row();
        $res->free();
        return (int) $row[0];
    }

    /**
     * تنفيذُ ملفِّ SQL كاملًا — الخادمُ هو من يفصل العبارات (multi_query)، وهو
     * السلوكُ نفسُه المُجرَّب في migrate.php.
     * @param array $replace استبدالاتٌ نصّيةٌ قبل التنفيذ (العلامةُ النائبة).
     */
    private function runSqlFile($path, array $replace = array())
    {
        $sql = @file_get_contents($path);
        if ($sql === false) {
            return "تعذّرت قراءة {$path}";
        }
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
        }
        if (trim($sql) === '') {
            return 'الملف فارغ';
        }
        if (!empty($replace)) {
            $sql = str_replace(array_keys($replace), array_values($replace), $sql);
        }

        if (!$this->conn->multi_query($sql)) {
            return $this->conn->error;
        }
        do {
            if ($res = $this->conn->store_result()) {
                $res->free();
            }
            if ($this->conn->error !== '') {
                return $this->conn->error;
            }
        } while ($this->conn->more_results() && $this->conn->next_result());

        return $this->conn->error !== '' ? $this->conn->error : '';
    }

    private function randomKey()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(20));
        }
        return sha1(uniqid('', true) . microtime(true));
    }

    private function qi($ident)
    {
        return '`' . str_replace('`', '``', $ident) . '`';
    }

    private function check($ok, $label, $detail)
    {
        return array('ok' => (bool) $ok, 'label' => $label, 'detail' => $detail);
    }

    private function step($label, $detail = '')
    {
        $this->steps[] = array('label' => $label, 'detail' => $detail);
    }

    private function fail($msg)
    {
        return array('ok' => false, 'steps' => $this->steps, 'error' => $msg, 'summary' => array());
    }
}
