<?php
/**
 * سجل الاطّلاع الحساس — M-14 BR-GOV-07 («القراءةُ على السرِّ فعلٌ يُساءل عنه»)
 * ───────────────────────────────────────────────────────────────────────────
 * كلُّ عرضِ حقلٍ حساسٍ (راتب · حساب بنكي · تقييم · جزاءات · مستندات شخصية)
 * يُسجَّل كما تُسجَّل الكتابة: من قرأ، عن مَن، أيَّ حقل، من أي شاشة.
 * القناة سجلُّ الأمن المركزي (security log) بحدث SENSITIVE_READ — لا جدولَ
 * جديدًا (DDL مجمَّد)، وتقرير «سجل الاطّلاع الحساس» يقرؤه بالوسم.
 *
 * الاستعمال في أي شاشةٍ تعرض حقلًا حساسًا:
 *   require_once __DIR__ . '/../includes/sensitive_read_log.php';
 *   ems_log_sensitive_read($conn, 'salary', 'employee:867', 'كشف الفرد');
 * ولمرةٍ لكل (قارئ×موضوع×حقل×يوم) — فلا يغرق السجل بتحديثات الصفحة.
 */

if (!function_exists('ems_log_sensitive_read')) {
    function ems_log_sensitive_read(mysqli $conn, $field, $subjectRef, $screen = '')
    {
        if (!isset($_SESSION['user']['id'])) { return false; }
        $uid = intval($_SESSION['user']['id']);
        $field = preg_replace('/[^a-z0-9_\-]/i', '', (string) $field);
        $subjectRef = mb_substr(trim((string) $subjectRef), 0, 80);
        $screen = mb_substr(trim((string) $screen), 0, 120);
        if ($field === '' || $subjectRef === '') { return false; }

        // عطالة اليوم الواحد: مفتاح (قارئ×موضوع×حقل×يوم) في الجلسة ثم في السجل
        $k = 'srl_' . md5($uid . '|' . $field . '|' . $subjectRef . '|' . date('Y-m-d'));
        if (!empty($_SESSION[$k])) { return true; }
        $_SESSION[$k] = 1;

        if (function_exists('log_security_event')) {
            log_security_event('SENSITIVE_READ',
                'field=' . $field . ' subject=' . $subjectRef
                . ' screen=' . $screen . ' reader=' . $uid
                . ' role=' . (string) ($_SESSION['user']['role'] ?? '?'));
            return true;
        }
        return false;
    }
}

/* ═══ INJ-FIX-01 · الموجة أ ② — قناةُ العرضِ للحقلِ الحساس (GAP-10) ═══════════
 * ◆ **الثغرةُ التي يسدُّها — مقيسةٌ بمسحٍ شجريّ**: خمسةُ أسطحِ ملفٍّ/بطاقةٍ تعرض
 *   قيمةً حساسةً بلا مرورٍ بنقطةِ القرار — ملفُّ العميلِ (رصيدٌ افتتاحيّ) ·
 *   ملفُّ الموظفِ (هاتف) · تفصيلُ النسبةِ (نتيجةُ نسبة) · بطاقةُ المورِّد
 *   (رقمٌ ضريبيّ) · بطاقةُ الخطر (الوحدةُ المالكة). والإعلانُ لكلٍّ منها
 *   إخفاءٌ مكتوبٌ في `scr_sensitive_fields.policy_masking` — كان حبرًا.
 * ◆ **ولا مقرِّرَ سادس**: هذه الدالةُ تفوّض إلى `SensitiveFieldGuard` نفسِه
 *   الذي تستهلكه الشاشةُ والتصديرُ والواجهةُ البرمجية — فالنقطةُ واحدةٌ
 *   بأربعةِ مداخل، لا أربعةُ نقاطٍ بحكمٍ واحد.
 * ◆ **وفشلٌ مغلق**: غيابُ الحارسِ يُخفي القيمةَ ولا يعرضها خامًّا.
 * ◆ **وصيغةُ الإخفاءِ من الإعلانِ لا من الشاشة**: `full` ⇐ «•••» ·
 *   `partial` ⇐ آخرُ أربعةٍ · `none` ⇐ يُعرض للمخوَّلِ وحدَه.
 *
 * @param  string $code   «جدول.حقل» — مثل employees.phone
 * @param  mixed  $value  القيمةُ الخام
 * @param  string $subjectRef مرجعُ الموضوع للسجل — مثل client:12
 * @return string القيمةُ كما يستحقُّها القارئُ الحاليّ (جاهزةٌ للعرض)
 */
if (!function_exists('ems_sensitive_display')) {
    function ems_sensitive_display(mysqli $conn, $code, $value, $subjectRef = '', $screen = '')
    {
        if ($value === null || $value === '') { return $value; }

        $guardFile = dirname(__DIR__) . '/app/Services/Security/SensitiveFieldGuard.php';
        if (!class_exists('\App\Services\Security\SensitiveFieldGuard', false)) {
            if (!is_file($guardFile)) { return '•••'; }          // فشلٌ مغلق
            require_once $guardFile;
        }
        $G = '\App\Services\Security\SensitiveFieldGuard';
        $pol = $G::policy($conn, $code);
        if (!$pol) { return $value; }                             // غيرُ مصنَّفٍ — يمرّ

        $role      = isset($_SESSION['user']['role'])       ? (string) $_SESSION['user']['role'] : '';
        $personId  = isset($_SESSION['user']['id'])         ? (int) $_SESSION['user']['id'] : 0;
        $companyId = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;

        $v = $G::readerAllowed($conn, $pol, $personId, $role, $companyId, $code);
        if (empty($v['ok'])) {
            return $pol['masking_rule'] === 'partial' ? $G::maskPartial((string) $value) : '•••';
        }
        if ($subjectRef !== '') {
            ems_log_sensitive_read($conn, str_replace('.', '_', $code), $subjectRef, $screen);
        }
        return $value;
    }
}
