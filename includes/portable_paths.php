<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * اكتشافُ مسارات الأدوات وقتَ التشغيل — EMS (قابلية النقل بين بيئات التطوير)
 * ───────────────────────────────────────────────────────────────────────────
 * القاعدة (PROMPT_cross_env_portability_fix · ج٢): **لا مسارَ مطلقًا مثبَّتًا
 * لحزمةٍ بعينها في الكود.** المشروع يُطوَّر على WAMP وXAMPP معًا، وكلُّ مسارٍ
 * يُكتب افتراضًا لإحداهما يعمل عند مطوّرٍ ويفشل عند الآخر — وهو النمطُ نفسُه
 * الذي كسر المشروع سابقًا بـ`auto_prepend_file` بمسارٍ مطلق في .htaccess
 * (docs/nfr/N-26_infra_readiness_ar.md §NFR-13).
 *
 * الترتيبُ الثابت في كل دالةٍ هنا:
 *   ① ما يعرفه PHP عن نفسه يقينًا  ② .env صراحةً  ③ PATH  ④ فحصُ مواضعَ
 *   شائعةٍ بـ is_dir/is_file — **فحصًا لا افتراضًا** ⑤ الفشلُ برسالةٍ واضحة.
 *
 * بلا تبعيات: يُحمَّل مستقلًّا في CLI وفي الويب على السواء.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_portable_which')) {

    /**
     * موضعُ أداةٍ في PATH — نظيرُ `which` بلا صدفةٍ ولا عمليةٍ فرعية.
     *
     * @param string $binary اسمُ الأداة بلا امتداد (مثل mysqldump)
     * @return string المسارُ الكامل، أو '' إن لم تُوجد
     */
    function ems_portable_which($binary)
    {
        $binary = trim((string) $binary);
        if ($binary === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $binary)) {
            return '';
        }

        $isWindows = (DIRECTORY_SEPARATOR === '\\');
        $exts = $isWindows ? array('.exe', '.cmd', '.bat', '') : array('');

        $path = (string) getenv('PATH');
        if ($path === '') {
            return '';
        }

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $dir = rtrim(str_replace('\\', '/', trim($dir)), '/');
            if ($dir === '') {
                continue;
            }
            foreach ($exts as $ext) {
                $candidate = $dir . '/' . $binary . $ext;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * جذورُ حزمِ التطوير الموجودةُ فعلًا على هذا الجهاز.
     *
     * القائمةُ مرشَّحاتٌ تُفحص بـ`is_dir` لا افتراضاتٌ تُبنى عليها مسارات — فلا
     * يرجع منها إلا ما هو كائنٌ حقًّا. ترتيبُها لا يعني تفضيلًا: النداءُ يفحص
     * وجودَ الأداة داخل كلِّ جذرٍ قبل اعتماده.
     *
     * @return string[] مساراتٌ موجودةٌ بفواصل '/'
     */
    function ems_portable_stack_roots()
    {
        static $roots = null;
        if ($roots !== null) {
            return $roots;
        }

        $candidates = array();

        // جذرُ الحزمةِ المستضيفةِ لهذا الملفِّ نفسِه — أدقُّ مرشَّحٍ على الإطلاق:
        // C:/wamp64/www/ems → C:/wamp64 · C:/xampp/htdocs/ems → C:/xampp
        $self = str_replace('\\', '/', dirname(__DIR__));
        for ($up = 0; $up < 3; $up++) {
            $self = dirname($self);
            if ($self === '' || $self === '.' || preg_match('#^[A-Za-z]:/?$#', $self) || $self === '/') {
                break;
            }
            $candidates[] = $self;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (array('C', 'D', 'E') as $drive) {
                foreach (array('wamp64', 'wamp', 'xampp', 'laragon', 'mamp') as $stack) {
                    $candidates[] = $drive . ':/' . $stack;
                }
            }
        } else {
            foreach (array('/opt/lampp', '/usr/local', '/usr', '/opt/homebrew') as $unixRoot) {
                $candidates[] = $unixRoot;
            }
        }

        $roots = array();
        foreach ($candidates as $candidate) {
            $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
            if ($candidate !== '' && is_dir($candidate) && !in_array($candidate, $roots, true)) {
                $roots[] = $candidate;
            }
        }

        return $roots;
    }

    /**
     * مجلدُ أدوات خادم القاعدة (mysqldump/mysql).
     *
     * **المُحلِّلُ الوحيد** — تستدعيه أدواتُ النسخ الاحتياطي في لوحة الإدارة
     * ‏(admin/includes/db_tools.php) ويستدعيه فاحصُ البيئة (tools/doctor.php).
     * توحيدُهما مقصود: مُحلِّلان مختلفان يعنيان أن الفاحصَ قد يقول «لا أدوات»
     * بينما الأداةُ تعمل، أو العكس — وكلاهما تشخيصٌ كاذبٌ يُضيع وقتَ المطوّر.
     *
     * @return string المجلد بفواصل '/'، أو '' إن تعذّر الاكتشاف
     */
    function ems_portable_mysql_bin_dir()
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $fromEnv = (string) ems_env('MYSQL_BIN_DIR', '');
        if ($fromEnv !== '') {
            return $resolved = rtrim(str_replace('\\', '/', $fromEnv), '/');
        }

        $fromPath = ems_portable_which('mysqldump');
        if ($fromPath !== '') {
            return $resolved = rtrim(str_replace('\\', '/', dirname($fromPath)), '/');
        }

        $isWindows = (DIRECTORY_SEPARATOR === '\\');
        $exe = $isWindows ? 'mysqldump.exe' : 'mysqldump';
        foreach (ems_portable_stack_roots() as $root) {
            foreach (array('/mysql/bin', '/bin/mysql', '/bin/mariadb') as $suffix) {
                $candidate = $root . $suffix;
                if (is_file($candidate . '/' . $exe)) {
                    return $resolved = $candidate;
                }
                // حزمُ WAMP تُعشّش الإصدارَ مجلدًا إضافيًّا: bin/mysql/mysql8.4.7/bin
                foreach ((array) @glob($candidate . '/*/bin', GLOB_ONLYDIR) as $nested) {
                    $nested = rtrim(str_replace('\\', '/', $nested), '/');
                    if (is_file($nested . '/' . $exe)) {
                        return $resolved = $nested;
                    }
                }
            }
        }

        return $resolved = '';
    }

    /**
     * مسارُ مُنفِّذ PHP الحالي.
     *
     * `PHP_BINARY` هو الجواب اليقيني في CLI — لا تخمينَ فيه. وتحت الويب قد يكون
     * فارغًا أو مسارَ وحدةِ أباتشي، فيُستكمل من .env ثم PATH ثم فحصِ الجذور.
     *
     * @return string المسارُ الكامل، أو '' إن تعذّر الاكتشاف
     */
    function ems_portable_php_bin()
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $fromEnv = (string) ems_env('PHP_BIN', '');
        if ($fromEnv !== '') {
            return $resolved = str_replace('\\', '/', $fromEnv);
        }

        // في CLI فقط: PHP_BINARY يشير إلى مُنفِّذ سطر الأوامر يقينًا. أما تحت
        // أباتشي فقد يشير إلى httpd نفسه — فلا يُعتمد.
        if (PHP_SAPI === 'cli' && defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
            return $resolved = str_replace('\\', '/', PHP_BINARY);
        }

        $fromPath = ems_portable_which('php');
        if ($fromPath !== '') {
            return $resolved = $fromPath;
        }

        $isWindows = (DIRECTORY_SEPARATOR === '\\');
        $exe = $isWindows ? 'php.exe' : 'php';
        foreach (ems_portable_stack_roots() as $root) {
            $direct = $root . '/php/' . $exe;
            if (is_file($direct)) {
                return $resolved = $direct;
            }
            // حزمُ WAMP تُعشّش الإصدار: bin/php/php8.3.28/php.exe — يُختار الأحدث
            $nested = (array) @glob($root . '/bin/php/php*/' . $exe);
            if (!empty($nested)) {
                sort($nested, SORT_NATURAL);
                return $resolved = str_replace('\\', '/', (string) end($nested));
            }
        }

        return $resolved = '';
    }
}
