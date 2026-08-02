<?php
/**
 * includes/permit_gate.php — موصّل حارس الأذونات بالمواضع التسعة (ORG-13)
 * ───────────────────────────────────────────────────────────────────────────
 * «كل دخول أو خروج أو تفعيل أو إيقاف يمرّ بالحارس» (ORG-01 §5/§7-⑤).
 * الاستدعاء من معالج الفعل قبل التنفيذ:
 *
 *     require_once __DIR__ . '/../includes/permit_gate.php';
 *     $pg = ems_permit_gate($conn, $company_id, 'equipment_site_entry',
 *                           'EQ:' . $equipment_id, $site_id, $user_id);
 *     if (!$pg['ok']) { echo json_encode(['status'=>'error','message'=>$pg['reason']]);
 *                       http_response_code($pg['code']); exit; }
 *
 * العلم EMS_PERMIT_GATE: off (افتراض) · monitor (يسجّل ويمضي) · enforce (يمنع).
 * والتجربة بموقع واحد عبر EMS_PERMIT_GATE_SITES (قائمة معرّفات مواقع).
 */

require_once __DIR__ . '/../app/Services/Org/PermitGate.php';

if (!function_exists('ems_default_site_of_project')) {
    /** موقع المشروع الافتراضي (is_default ثم الأقدم) — سياق شاشات الحركة مشروعي. */
    function ems_default_site_of_project(\mysqli $conn, $companyId, $projectId)
    {
        $stmt = $conn->prepare(
            'SELECT id FROM sites WHERE company_id = ? AND project_id = ? AND is_deleted = 0
              ORDER BY is_default DESC, id ASC LIMIT 1');
        $companyId = intval($companyId); $projectId = intval($projectId);
        $stmt->bind_param('ii', $companyId, $projectId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? intval($row['id']) : 0;
    }
}

if (!function_exists('ems_permit_gate')) {
    /**
     * @return array{ok:bool, code:int, reason:string, req_id:?int, mode:string}
     */
    function ems_permit_gate(\mysqli $conn, $companyId, $permitTypeCode, $subjectRef, $siteId, $actorPersonId = 0, $consume = true)
    {
        return \App\Services\Org\PermitGate::check($conn, $companyId, $permitTypeCode, $subjectRef, $siteId, $actorPersonId, $consume);
    }
}
