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

    /** كل متغيرات .env كمصفوفة (تُقرأ مرة واحدة). */
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
}
