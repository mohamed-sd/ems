<?php
/**
 * DeputyResolver — تفعيل النائب المعتمَد (ORG-01 §7 خدمة ④)
 * ───────────────────────────────────────────────────────────────────────────
 * «عند غياب المكلَّف أو انتهاء تكليفه: يفعّل النائب المعتمَد بسقفه ومدته —
 *  ولا نيابة بلا سجل ولا مفتوحة المدة» (§2⑥ · O4).
 *
 * النيابة لا تتجاوز مدةَ التكليف نفسِه (فمدتها مكتوبة بحدّه الأقصى)، وسقفُها
 * سقفُ التكليف — والتفعيل سطرُ `delegated` في السجل فلا نيابةَ صامتة.
 */

namespace App\Services\Org;

require_once __DIR__ . '/AssignmentService.php';

class DeputyResolver
{
    /**
     * من يعمل بسلطة هذا التكليف الآن؟
     * @return array{person_id:?int, is_deputy:bool, reason:string}
     */
    public static function resolveActor(\mysqli $conn, $asgId, $principalAbsent = false)
    {
        $a = AssignmentService::fetch($conn, $asgId);
        if (!$a) { return array('person_id' => null, 'is_deputy' => false, 'reason' => 'تكليف غير موجود'); }

        $today = OrgAuthorityResolver::dbToday($conn);
        $valid = ($a['state'] === 'active' && $a['valid_from'] <= $today && $today <= $a['valid_to']);

        if ($valid && !$principalAbsent) {
            return array('person_id' => intval($a['person_id']), 'is_deputy' => false, 'reason' => 'المكلَّف حاضر بتكليف نافذ');
        }

        // الغياب أو التعليق يفعّلان النائب — أما الانتهاء فلا نيابة بعده
        $suspendedOrAbsent = $principalAbsent || $a['state'] === 'suspended';
        if ($suspendedOrAbsent && $a['deputy_person_id'] !== null
            && $a['valid_from'] <= $today && $today <= $a['valid_to'] && $a['state'] !== 'ended') {
            AssignmentService::audit($conn, intval($a['asg_id']), 'delegated',
                'تفعيل النائب المعتمَد — DeputyActivated',
                array('principal' => intval($a['person_id'])),
                array('deputy' => intval($a['deputy_person_id']), 'until' => $a['valid_to']),
                intval($a['deputy_person_id']));
            return array('person_id' => intval($a['deputy_person_id']), 'is_deputy' => true,
                         'reason' => 'النائب المعتمَد يعمل بسقف التكليف حتى ' . $a['valid_to']);
        }

        return array('person_id' => null, 'is_deputy' => false,
                     'reason' => $a['state'] === 'ended' || $a['valid_to'] < $today
                        ? 'التكليف منتهٍ — لا نيابة بعد الانتهاء، وتُرفع للأعلى فلا تتوقف السلسلة'
                        : 'لا نائب معتمَدًا مسجَّلًا — تُرفع للأعلى');
    }
}
