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
