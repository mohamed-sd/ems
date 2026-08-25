<?php
/**
 * app/Services/Actions/ReverseService.php — خدمةُ العكس (ACT-01 v6 §8-⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * «ينفّذ فعلَ العكس المعرَّف · عكسٌ بلا مرجعِ أصلٍ → 422 · حذفٌ بدل عكسٍ →
 * 403 بنيويًّا». البوابةُ الموحّدةُ التي تسألها الخدماتُ المالكة قبل أي عكس:
 * تتحقق من العقد وترُدّ وصفَ فعلِ العكس — والتنفيذُ الفعليُّ يبقى في الخدمة
 * المالكة (أسطرٌ عاكسةٌ عبر publishFact لا حذفٌ ولا تعديل).
 */

namespace App\Services\Actions;

class ReverseService
{
    /**
     * يتحقق ويحلّ فعلَ العكس لفعلٍ منفَّذ.
     * @param string $actionCode   الفعلُ الأصلُ المراد عكسُه
     * @param string $originalRef  مرجعُ التنفيذ الأصلي (idempotency/معرّف السجل)
     * @return array {reverse_action_code, handler_path, guards_json}
     * @throws \RuntimeException 422 بلا مرجع · 422 لا عكسَ معرَّفًا · 403 محاولةُ حذف
     */
    public static function resolve($conn, $actionCode, $originalRef)
    {
        if (trim((string) $originalRef) === '') {
            throw new \RuntimeException('422: عكس بلا مرجع أصل مرفوض');
        }
        $code = mysqli_real_escape_string($conn, (string) $actionCode);
        $r = mysqli_query($conn, "SELECT action_code, reverse_action_code, is_financial FROM actions
                                  WHERE action_code = '$code' AND active = 1");
        $a = $r ? mysqli_fetch_assoc($r) : null;
        if (!$a) { throw new \RuntimeException('422: فعل غير مسجل'); }
        if (empty($a['reverse_action_code'])) {
            throw new \RuntimeException('422: لا فعل عكس معرفا — والمالي لا يدمج بلا عكس (فحص ⑦)');
        }
        $rev = mysqli_real_escape_string($conn, $a['reverse_action_code']);
        $r = mysqli_query($conn, "SELECT action_code, handler_path, guards_json FROM actions
                                  WHERE action_code = '$rev' AND active = 1");
        $rv = $r ? mysqli_fetch_assoc($r) : null;
        if (!$rv) { throw new \RuntimeException('422: فعل العكس المسجل غير موجود حيا'); }
        // «ولا عكسَ بحذفٍ ولا بتعديل» — عقدُ العكس نفسُه لا يجوز أن يعلن حذفًا
        $w = mysqli_query($conn, "SELECT COUNT(*) FROM action_writes WHERE action_code = '$rev' AND operation = 'delete'");
        if ($w && intval(mysqli_fetch_row($w)[0]) > 0) {
            throw new \RuntimeException('403: حذف بدل عكس مرفوض بنيويا — العكس أسطر عاكسة');
        }
        return $rv;
    }
}
