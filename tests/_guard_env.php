<?php
/**
 * tests/_guard_env.php — تجاوز أعلام البيئة للاختبارات **بلا مسّ .env الحي**
 * ─────────────────────────────────────────────────────────────────────────
 * (سلامة البيئة — PLAN-05 §3-⑩): «لا يكتب اختبارٌ في ملف تهيئةٍ حي —
 * نسخةٌ معزولةٌ أو استثناءٌ من المشغّل الجامع».
 *
 * الآلية الجديدة: **ملف تراكبٍ مؤقتٍ معزول** في مجلد النظام المؤقت، يُعلَن
 * مسارُه عبر متغير البيئة الحقيقي `EMS_ENV_OVERLAY` فيقرؤه `ems_env_all()`
 * **بعد** .env فتغلب قيمُه — و.env الحي لا يُقرأ للكتابة إطلاقًا.
 * التراكب يورَّث للعمليات الفرعية (المسابير) عبر بيئة العملية، ويُكنس عند
 * انتهائها بـregister_shutdown_function.
 *
 * الاستعمال — قبل `require config.php` حتمًا (ems_env_all تُخزّن في static):
 *
 *     require_once __DIR__ . '/_guard_env.php';
 *     ems_test_env_override(array('EMS_DOC_EXPIRY_GUARD' => 'off'));
 *     require_once dirname(__DIR__) . '/config.php';
 *
 * التوقيع نفسه القديم — كل الحزم القائمة تعمل بلا تعديل.
 */

if (!function_exists('ems_test_env_override')) {
    /**
     * يعلن قيمًا تغلب .env لهذه العملية وفروعها — بلا كتابة في الملف الحي.
     *
     * @param array<string,string> $pairs المفتاح => القيمة
     * @param bool $forWebServer الحزم ذات مسابير HTTP: Apache لا يرى تراكب
     *        العملية — فتُكتب القيم عبر ems_env_write (**بنسخة آلية قبلها** —
     *        وهذا «الاستثناء من المشغّل الجامع» المنصوص) وتُستعاد بايت-مطابقة
     *        عند الانتهاء من النسخة نفسها.
     */
    function ems_test_env_override(array $pairs, $forWebServer = false)
    {
        if ($forWebServer) {
            require_once dirname(__DIR__) . '/includes/env.php';
            static $webBak = null;
            $bak = ems_env_write($pairs);
            if ($bak === false) { return false; }
            if ($webBak === null) {
                $webBak = $bak; // أول نسخة = حالة ما قبل الحزمة كلها
                register_shutdown_function(function () use ($webBak) {
                    @copy($webBak, dirname(__DIR__) . '/.env'); // استعادة بايت-مطابقة
                });
            }
            // ويُعلَن للتراكب المحلي أيضًا كي ترى عمليةُ الاختبار نفسها القيمَ
        }
        static $overlayFile = null;
        static $accum = array();

        if ($overlayFile === null) {
            $overlayFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
                . 'ems_env_overlay_' . getmypid() . '_' . substr(sha1(uniqid('', true)), 0, 8) . '.env';
            putenv('EMS_ENV_OVERLAY=' . $overlayFile);
            $_ENV['EMS_ENV_OVERLAY'] = $overlayFile;
            register_shutdown_function(function () use ($overlayFile) {
                @unlink($overlayFile);
                putenv('EMS_ENV_OVERLAY');
            });
        }

        foreach ($pairs as $k => $v) {
            $accum[$k] = $v;
        }
        $out = '';
        foreach ($accum as $k => $v) {
            $out .= $k . '=' . $v . "\n";
        }
        return file_put_contents($overlayFile, $out) !== false;
    }
}
