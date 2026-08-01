<?php
/**
 * مُحمِّل متغيرات البيئة — EMS (.env loader) · ADR-04
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ ملف .env من جذر المشروع مرة واحدة لكل طلب (حاوية ساكنة). بلا مكتبات
 * خارجية، وبلا putenv (لا تسريب للقيم إلى عمليات فرعية أو phpinfo).
 *
 * الصيغة المدعومة: KEY=VALUE سطرًا بسطر · تعليقات تبدأ بـ # · اقتباس اختياري
 * "..." أو '...' حول القيمة.
 *
 * القاعدة (قائمة التجميد · EQUIP-ARC-R02 §5): أي سرٍّ جديد يوضع في .env حصريًا
 * عبر ems_env() — لا يُكتب أي سرٍّ في الكود أو المستودع. القالب: .env.example
 */

if (!function_exists('ems_env')) {

    /**
     * كل متغيرات .env كمصفوفة (تُقرأ مرة واحدة).
     *
     * عزل عدة الاختبار (PLAN-05 §3-⑩): إن حمل متغيرُ البيئة الحقيقي
     * EMS_ENV_OVERLAY مسارَ ملفِ تراكبٍ مقروء، قُرئ **بعد** .env فغلبت قيمُه —
     * فلا يكتب اختبارٌ في ملف التهيئة الحي أبدًا، والتراكبُ ملفٌ مؤقتٌ معزول
     * يورَّث للعمليات الفرعية (المسابير) عبر البيئة.
     */
    function ems_env_all()
    {
        static $vars = null;
        if ($vars !== null) {
            return $vars;
        }
        $vars = array();

        $file = dirname(__DIR__) . '/.env';
        if (!is_readable($file)) {
            return $vars;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $vars;
        }

        $overlay = getenv('EMS_ENV_OVERLAY');
        if (is_string($overlay) && $overlay !== '' && is_readable($overlay)) {
            $extra = @file($overlay, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($extra)) {
                $lines = array_merge($lines, $extra); // التراكب أخيرًا فيغلب
            }
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($key === '' || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
                continue;
            }
            $len = strlen($val);
            if ($len >= 2 && (($val[0] === '"' && $val[$len - 1] === '"') || ($val[0] === "'" && $val[$len - 1] === "'"))) {
                $val = substr($val, 1, -1);
            }
            $vars[$key] = $val;
        }

        return $vars;
    }

    /** قيمة متغير بيئة، أو $default إن غاب المفتاح أو الملف. */
    function ems_env($key, $default = null)
    {
        $vars = ems_env_all();
        return array_key_exists($key, $vars) ? $vars[$key] : $default;
    }

    /** هل ملف .env موجود ومقروء؟ (لتحذير الإقلاع في config.php) */
    function ems_env_loaded()
    {
        return is_readable(dirname(__DIR__) . '/.env');
    }

    /**
     * الكتابة الوحيدة المعتمدة في .env (PLAN-05 §3-⑩): **نسخة احتياطية آلية
     * قبل أي تعديل على التهيئة** — تُحفظ في database/backups/env_history/
     * بختم وقتها، ثم تُكتب القيم. لا يكتب أي مسار آخر في .env مباشرة.
     *
     * @param array<string,string> $pairs المفتاح => القيمة الجديدة
     * @return string|false مسار النسخة الاحتياطية، أو false عند الفشل
     */
    function ems_env_write(array $pairs)
    {
        $file = dirname(__DIR__) . '/.env';
        if (!is_readable($file) || !is_writable($file)) {
            return false;
        }
        $histDir = dirname(__DIR__) . '/database/backups/env_history';
        if (!is_dir($histDir)) {
            @mkdir($histDir, 0700, true);
        }
        $bak = $histDir . '/env_' . date('Ymd_His') . '_' . getmypid() . '.bak';
        if (!@copy($file, $bak)) {
            return false; // لا تعديل بلا نسخة — fail-closed
        }
        $out = file_get_contents($file);
        foreach ($pairs as $k => $v) {
            $line = $k . '=' . $v;
            $swapped = preg_replace('/^' . preg_quote($k, '/') . '=.*$/m', $line, $out, 1, $n);
            if ($swapped === null) {
                return false; // درس N-21: لا كتابة بناتج preg بلا فحص NULL
            }
            $out = ($n > 0) ? $swapped : (rtrim($out) . "\n" . $line . "\n");
        }
        return (file_put_contents($file, $out) !== false) ? $bak : false;
    }
}
