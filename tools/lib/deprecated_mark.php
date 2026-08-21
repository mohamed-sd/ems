<?php
/**
 * tools/lib/deprecated_mark.php — INJ-FIX-02 · NF-06 · قاعدةُ وسمِ التقاعدِ الوحيدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **موضعٌ واحدٌ للقاعدةِ** حتى لا يتفرّق الماسحُ عن الفاحص: `baseline_disk_scan`
 *   يسمُ بها، و`tests/injfix02_retirement_tag_proof.php` يقيسها. ونسختان من
 *   قاعدةٍ واحدةٍ تتفرّقان بلا إنذار.
 *
 * ◆ **يُقاس المُعلَنُ لا المذكور**: الكاشفُ السابقُ طابق الشيفرةَ الخامَ بـ
 *   `/DEPRECATED|متقاعد/` فوسَم ١٣ سطحًا حيًّا متقاعدًا — ١٢ بسببِ
 *   `E_DEPRECATED`، وواحدٌ لأن الشاشةَ **تطبع** الكلمةَ لافتةً لغيرِها.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_deprecated_mark')) {
    /**
     * هل يُعلن هذا الملفُّ تقاعدَه؟ — ثلاثةُ مواضعِ إعلانٍ لا رابعَ لها.
     *
     * @param  string $src شيفرةُ الملفِّ الخام
     * @return bool
     */
    function ems_deprecated_mark($src)
    {
        /* ① وسمُ توثيقٍ مقصود: @deprecated في أولِ كلمة */
        if (preg_match('/(^|[\s*\/])@deprecated\b/i', $src)) { return true; }

        /* ② ثابتٌ مُعلَنٌ صراحةً — و`E_DEPRECATED` ليست منه (حدُّ كلمةٍ يسبقها) */
        if (preg_match('/(?<![A-Za-z0-9_])EMS_DEPRECATED(?![A-Za-z0-9_])/', $src)) { return true; }

        /* ③ إعلانٌ عربيٌّ **في التعليقاتِ وحدَها** — فلافتةٌ تطبعها الشاشةُ ليست إعلانًا */
        $tokens = @token_get_all($src);
        if (is_array($tokens)) {
            foreach ($tokens as $tk) {
                if (!is_array($tk)) { continue; }
                if ($tk[0] !== T_COMMENT && $tk[0] !== T_DOC_COMMENT) { continue; }
                if (preg_match('/متقاعد|قديم — لا يُستخدم/u', $tk[1])) { return true; }
            }
        }
        return false;
    }
}
