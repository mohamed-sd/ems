<?php
/**
 * tests/_guard_env.php — تحييدُ علَمٍ لا يخصّ موضوعَ الحزمة
 * ─────────────────────────────────────────────────────────────────────────
 * **لماذا يلزم:** الحزمُ التي تختبر آلةَ الحالات تسجّل وقائعَ بتاريخٍ مستقبليٍّ
 * بعيد (2031 مثلًا) كي لا تلامس مفاتيحَ الأيام الحيّة. وحارسُ الوثائق يقارن
 * `expiry_date < entry_date` — **فأيُّ رخصةٍ سارية اليوم منتهيةٌ بالنسبة ليومِ
 * عملٍ في 2031**. القياسُ 2026-07-29: المشغّلون الأربعةُ المفحوصون كلُّهم
 * محجوبون في 2031، بمن فيهم المجدَّدون (رخصةٌ إلى 2029).
 *
 * فالحجبُ **سلوكٌ صحيحٌ لا عطب** — لكنه يخصّ حارسَ الوثائق لا آلةَ الحالات.
 * والحزمةُ التي موضوعُها الآلةُ تُعلن تحييدَ ما لا يخصّها، كما تمرّر المرآةُ
 * `enforce_capacity=false` لأنها مسجِّلُ تاريخٍ لا مُنفِذُ قواعد.
 *
 * **الاستعمال — قبل `require config.php` حتمًا** (فـ`ems_env_all()` تُخزّن
 * القيمَ في `static` عند أول نداء، وهو يقع داخل config):
 *
 *     require_once __DIR__ . '/_guard_env.php';
 *     ems_test_env_override(array('EMS_DOC_EXPIRY_GUARD' => 'off'));
 *     require_once dirname(__DIR__) . '/config.php';
 *
 * الاستعادةُ مضمونةٌ بـ`register_shutdown_function` — و.env يعود **بايت-مطابقًا**
 * حتى لو ماتت الحزمةُ في منتصفها. ولا تُشغَّل حزمتان معًا (التشغيلُ تسلسليٌّ).
 */

if (!function_exists('ems_test_env_override')) {
    /**
     * يكتب قيمًا في .env مؤقتًا ويستعيده كاملًا عند انتهاء العملية.
     *
     * @param array<string,string> $pairs المفتاح => القيمة
     */
    function ems_test_env_override(array $pairs)
    {
        $file = dirname(__DIR__) . '/.env';
        if (!is_readable($file) || !is_writable($file)) { return false; }

        static $original = null;
        if ($original === null) {
            $original = file_get_contents($file);
            register_shutdown_function(function () use ($file, $original) {
                file_put_contents($file, $original);
            });
        }

        $out = $original;
        foreach ($pairs as $k => $v) {
            $line = $k . '=' . $v;
            $swapped = preg_replace('/^' . preg_quote($k, '/') . '=.*$/m', $line, $out, 1, $n);
            $out = ($n > 0) ? $swapped : (rtrim($out) . "\n" . $line . "\n");
        }
        return file_put_contents($file, $out) !== false;
    }
}
