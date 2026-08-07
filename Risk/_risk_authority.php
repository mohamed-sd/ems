<?php
/**
 * Risk/_risk_authority.php — خريطة سلطة القبول (ورقة 27) — تُضمَّن من المعالج
 * والشاشات معًا (المعالج لا يضمّن _risk_common كي يبقى JSON صافيًا).
 * حكم مؤقت موثق: لا طبقة «نواب» في الهيكل الحي — فالمرتفع يرتفع للرئيس.
 */
if (!function_exists('risk_actor_authority_map')) {
    function risk_actor_authority_map($role)
    {
        if ($role === '-1' || $role === '9') { return 'ceo'; }
        if ($role === '28' || $role === '29') { return 'analyst'; }
        $deptHeads = array('1','2','3','4','6','12','13','16','17','19','23','24','25','26','27');
        return in_array($role, $deptHeads, true) ? 'risk_owner' : '';
    }
}
