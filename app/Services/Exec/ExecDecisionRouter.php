<?php
namespace App\Services\Exec;

/**
 * app/Services/Exec/ExecDecisionRouter.php — موجِّهُ قرارِ القيادة (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الرؤيةُ لا تساوي السلطة** (‏قيدُ المالك §٢ · الأمرُ الأوّل البند 27):
 *   ⛔ **لا زرَّ فعلٍ في مساحةِ القيادةِ يكتب في جدولِ إدارةٍ مباشرةً.**
 *   والقرارُ من مساحةِ القيادةِ **يمرُّ بمحرّكِ الاعتمادِ نفسِه** الذي يمرُّ به
 *   قرارُ الإدارة — لا بمسارٍ موازٍ.
 *
 * ◆ **وهذا الموجِّهُ لا يكتب هو أيضًا**: يحسم السلطةَ، ثمّ **يسلّم القرارَ
 *   لخدمةِ نطاقِ المستند** بمرجعِ سلطتِه. فالكتابةُ تقع عند المالكِ وحدَه،
 *   وهنا `route()` تعيد **رمزَ ردٍّ ووجهةً** لا صفًّا مكتوبًا.
 *
 * ◆ **والسلسلةُ كاملةٌ قبل أيِّ توجيه**: `Authority` ثمّ `SoD` ثمّ `State`.
 *   - سلطةٌ غيرُ مسجَّلةٍ ⇒ `AUTHORITY_NOT_CONFIGURED` — ⛔ ولا تُفترَض.
 *   - مَن أعدَّ لا يعتمد ⇒ `SOD_SELF_APPROVAL`.
 *   - ومعتمَدةٌ لا `Overwrite` لها ⇒ `STATE_LOCKED_APPROVED` (‏قيدُ المالك §٤):
 *     تعديلٌ أو تصحيحٌ بمستندِه، لا كتابةٌ فوق قرارٍ نافذ.
 *
 * ◆ **ولا `Admin Override` سرّيّ**: كلُّ تجاوزٍ مشروعٍ يمرُّ بطلبِ استثناءٍ
 *   بسلطتِه ونطاقِه ومدّتِه وسببِه وأثرِه — `EXCEPTION_REQUIRED` هو الردُّ،
 *   لا فتحُ الباب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class ExecDecisionRouter
{
    const ROUTED               = 'ROUTED_TO_APPROVAL_ENGINE';
    const SOD_SELF_APPROVAL    = 'SOD_SELF_APPROVAL';
    const STATE_LOCKED         = 'STATE_LOCKED_APPROVED';
    const EXCEPTION_REQUIRED   = 'EXCEPTION_REQUIRED';
    const NO_OWNER_SERVICE     = 'NO_OWNER_SERVICE';

    /** حالاتٌ لا يُكتب فوقها — التعديلُ بمستندِه لا بالدهس. */
    private static $LOCKED = array('approved', 'معتمد', 'effective', 'closed', 'مغلق');

    /**
     * يوجّه قرارَ قيادةٍ إلى محرّكِ الاعتماد.
     *
     * @param array $doc  `owner_dept` · `owner_service` · `doc_kind` · `doc_ref`
     *                    · `state` · `prepared_by` · `amount` · `event_type`
     * @return array `verdict` · `authority_rule` · `required_level` · `target`
     *               · `why` — ⛔ **ولا صفَّ مكتوبًا**.
     */
    public static function route(\mysqli $conn, array $user, array $doc)
    {
        $actionKey = isset($doc['action_key']) ? (string) $doc['action_key'] : '';
        $auth = ScopeEngine::authority($conn, $user, $actionKey, array(
            'amount'     => isset($doc['amount']) ? $doc['amount'] : null,
            'event_type' => isset($doc['event_type']) ? $doc['event_type'] : 'any',
        ));
        if ($auth['verdict'] !== ScopeEngine::OK) {
            return array('verdict' => $auth['verdict'], 'authority_rule' => $auth['rule_id'],
                         'required_level' => $auth['required_level'], 'target' => '',
                         'why' => $auth['why']);
        }

        /* مَن أعدَّ لا يعتمد — فوقَ الدرجاتِ كلِّها. */
        $me = isset($user['id']) ? (int) $user['id'] : 0;
        $prep = isset($doc['prepared_by']) ? (int) $doc['prepared_by'] : 0;
        if ($me > 0 && $prep > 0 && $me === $prep) {
            return array('verdict' => self::SOD_SELF_APPROVAL, 'authority_rule' => $auth['rule_id'],
                         'required_level' => $auth['required_level'], 'target' => '',
                         'why' => 'معد المستند لا يعتمده');
        }

        /* معتمَدةٌ لا Overwrite لها. */
        $state = isset($doc['state']) ? (string) $doc['state'] : '';
        if ($state !== '' && in_array($state, self::$LOCKED, true)) {
            return array('verdict' => self::STATE_LOCKED, 'authority_rule' => $auth['rule_id'],
                         'required_level' => $auth['required_level'], 'target' => '',
                         'why' => 'الحالة نافذة والتغيير عليها تعديل أو تصحيح بمستنده');
        }

        /* وجهةُ التنفيذ: خدمةُ نطاقِ المستند — ولا كتابةَ من هنا. */
        $svc = isset($doc['owner_service']) ? trim((string) $doc['owner_service']) : '';
        if ($svc === '') {
            return array('verdict' => self::NO_OWNER_SERVICE, 'authority_rule' => $auth['rule_id'],
                         'required_level' => $auth['required_level'], 'target' => '',
                         'why' => 'لا خدمة مالك مسجلة لهذا المستند');
        }

        return array('verdict' => self::ROUTED, 'authority_rule' => $auth['rule_id'],
                     'required_level' => $auth['required_level'], 'target' => $svc, 'why' => '');
    }

    /**
     * تجاوزٌ مشروعٌ — **طلبُ استثناءٍ لا بابٌ خلفيّ**.
     * يعيد المطلوبَ لفتحِ الطلبِ ولا يفتحه، فالاستثناءُ يملكه نطاقُ الحوكمة.
     */
    public static function overrideRequest($guardCode, $reason)
    {
        return array(
            'verdict'  => self::EXCEPTION_REQUIRED,
            'guard'    => (string) $guardCode,
            'needs'    => array('Authority', 'Scope', 'Expiry', 'Reason', 'Audit'),
            'reason'   => (string) $reason,
            'owner'    => 'DEP-08',
            'why'      => 'لا تجاوز بلا طلب استثناء بسلطته ونطاقه ومدته وسببه وأثره',
        );
    }
}
