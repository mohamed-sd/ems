<?php
/**
 * includes/self_approval_guard.php — منعُ اعتماد الذات قيدًا عامًّا (M-45)
 * ───────────────────────────────────────────────────────────────────────────
 * UI-01 §8: «لا اعتمادَ للذات» — كان القياسُ: «حيٌّ تطبيقيًّا في المستخلصات
 * والتسويات فقط»؛ وهذا **التعميم**: دالةٌ واحدةٌ يستدعيها كلُّ مسار اعتمادٍ
 * (طلبٌ ماليٌّ · تسويةٌ · قيدٌ · تقييمٌ · إقفال) — والمخالفةُ **403 مسجَّلةٌ**
 * في سجل التدقيق (N-02).
 *
 * «منعُ اعتماد الذات بنيويًّا» (§10-③): الحارسُ في **الخادم** لا بإخفاء زر.
 */

if (!function_exists('ems_no_self_approval')) {
    /**
     * @param mysqli $conn
     * @param int    $creatorId  منشئُ المستند (users.id)
     * @param int    $approverId معتمِدُه
     * @param string $docLabel   وصفُ المستند للسجل (مثل «طلب مالي #7»)
     * @param int    $companyId
     * @return ?array{code:int,reason:string}  null = يجوز الاعتماد
     */
    function ems_no_self_approval($conn, $creatorId, $approverId, $docLabel, $companyId = 0)
    {
        $creatorId = (int) $creatorId;
        $approverId = (int) $approverId;
        if ($creatorId <= 0 || $approverId <= 0 || $creatorId !== $approverId) {
            return null; // يدان مختلفتان — أو منشئٌ مجهولٌ يفصل فيه حارسُ مساره
        }
        // 403 مسجَّلة — «محاولاتُ الرفض مسجَّلة» (PLAN-01 §12.2-③)
        require_once __DIR__ . '/audit_trail.php';
        ems_audit_change($conn, 'permissions', 'self_approval_guard', 'denied_403', $creatorId,
            array(),
            array('doc' => (string) $docLabel, 'creator' => $creatorId, 'approver' => $approverId),
            array('company_id' => (int) $companyId, 'user_id' => $approverId));
        return array('code' => 403,
            'reason' => '**من أنشأ لا يعتمد** — ' . (string) $docLabel
                      . ' أنشأتَه بنفسك، والاعتمادُ يدٌ ثانية (UI-01 §8 · 403 مسجَّلة)');
    }
}
