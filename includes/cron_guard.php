<?php
/**
 * includes/cron_guard.php — ملفُّ الجدولةِ لا يُشغَّل من المتصفّح
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0025
 *
 * ── المقيس ────────────────────────────────────────────────────────────────
 * **سبعةَ عشرَ ملفَّ جدولةٍ · صفرُ حارس.** وأشباهُ الحرّاسِ فيها تخدع:
 *
 *     if (!defined('EMS_CLI')) { define('EMS_CLI', true); }
 *
 * فالملفُّ **يُعرّف الشرطَ الذي يُفترض أن يحرسه** — فيصدق دائمًا. ونتيجتُه أنَّ
 * أيَّ زائرٍ يفتح `Operations/cron_wfm_engine.php` في متصفّحه فيُشغّل محرّكَ
 * العمل كاملًا: يُصعّد مهامَّ ويغلق تفويضاتٍ ويولّد صفوفًا — بلا جلسةٍ ولا دور.
 *
 * ── الحكم ─────────────────────────────────────────────────────────────────
 * ① **سطرُ الأوامرِ يمرُّ** — وهو مسلكُ الجدولةِ الحقيقيّ.
 * ② **وHTTP يُردُّ ٤٠٣** ويُكتب صفُّ رفضٍ — إلا برمزٍ سرّيٍّ مُعلَنٍ في البيئة
 *    (`CRON_HTTP_TOKEN`)، لأنَّ بعضَ المضيفاتِ لا تجدول إلا بنداءِ رابط.
 * ③ **وغيابُ الرمزِ حجبٌ لا إذن**: بيئةٌ بلا رمزٍ تمنع كلَّ نداءٍ HTTP. فالسكوتُ
 *    يُقرأ منعًا لا سماحًا — وهي القاعدةُ التي أنقذت البابَ في حملةِ الصلاحيات.
 *
 * ◆ والمقارنةُ بـ`hash_equals` لا بـ`===`: فرقُ التوقيتِ يسرّب الرمزَ حرفًا حرفًا.
 * ◆ ويُستدعى **قبل أيِّ عمل**: حارسٌ بعد أوّلِ كتابةٍ ليس حارسًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_cron_guard')) {
    /**
     * @param string $jobLabel اسمُ المهمةِ — يُكتب في صفِّ الرفضِ ليُعرف ما حُوول تشغيلُه
     */
    function ems_cron_guard($jobLabel = '')
    {
        if (php_sapi_name() === 'cli') { return true; }

        $token = '';
        if (function_exists('ems_env')) { $token = (string) ems_env('CRON_HTTP_TOKEN'); }
        if ($token === '' && isset($_ENV['CRON_HTTP_TOKEN'])) { $token = (string) $_ENV['CRON_HTTP_TOKEN']; }
        $given = isset($_GET['token']) ? (string) $_GET['token'] : '';

        if ($token !== '' && $given !== '' && hash_equals($token, $given)) {
            return true;                     /* نداءُ جدولةٍ مضيفٍ مُصرَّحٌ به */
        }

        /* الرفضُ يُسجَّل — فمحاولةُ التشغيلِ من المتصفّحِ خبرٌ أمنيٌّ لا صمت */
        $why = ($token === '')
            ? 'لا رمز جدولة في البيئة — فكل نداء HTTP محجوب'
            : 'رمز الجدولة غير مطابق';
        if (function_exists('ems_log_denial')) {
            @ems_log_denial('GOV-CRON-403', ($jobLabel !== '' ? $jobLabel : basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), $why);
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $st = $GLOBALS['conn']->prepare(
                'INSERT INTO security_log (event_type, details, ip_address, created_at) VALUES (?, ?, ?, NOW())');
            if ($st) {
                $et = 'GOV-CRON-403';
                $d  = 'محاولة تشغيل مهمة جدولة من المتصفح: '
                    . ($jobLabel !== '' ? $jobLabel : (string) ($_SERVER['SCRIPT_NAME'] ?? '')) . ' — ' . $why;
                $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                $st->bind_param('sss', $et, $d, $ip);
                @$st->execute();
                $st->close();
            }
        }

        if (!headers_sent()) { header('Content-Type: text/plain; charset=utf-8', true, 403); }
        echo "GOV-CRON-403: مهمة الجدولة لا تشغل من المتصفح — {$why}\n";
        exit;
    }
}
