<?php
namespace App\Services\Workforce;

/**
 * app/Services/Workforce/LeaveRequestService.php — طلبُ الإجازةِ عند مالكِه (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النطاقُ يملك تعريفَ طلبِه** (‏القرار ③): طلبُ الإجازةِ يُنشَأ **في سجلِّ
 *   القوى التشغيليّة** `worker_leave_absence` وحدَه — و«طلباتي» تُطلِقه ولا
 *   تخزّنه. ⛔ **ولا نسخةَ محلّيّةً في مساحةِ العمل.**
 *
 * ◆ **ومَن يُنشئ هو المالك**: هذه الخدمةُ تحت نطاقِ القوى التشغيليّة، والكتابةُ
 *   تقع هنا لا في المُطلِق — فحاجبُ «كتابةٌ من مساحةِ الموجة» يبقى صفرًا.
 *
 * ◆ **والحالةُ الأولى `مطلوب` لا `معتمد`**: الاعتمادُ فعلٌ لاحقٌ بسلطتِه —
 *   و`AAM` يحسم من يعتمد. ⛔ **ولا يعتمد المُطلِقُ طلبَ نفسِه.**
 *
 * ◆ **والكتابةُ عبرَ بوّابةِ العزلِ وحدَها** — الكيانُ يُحقَن ولا يُمرَّر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class LeaveRequestService
{
    /** الإدارةُ المالكةُ — القوى التشغيلية. */
    const OWNER_DEPT = 'DEP-13';

    /**
     * نقطةُ الإنشاءِ التي يناديها مُطلِقُ الطلبات.
     *
     * @param array $ctx     `company_id` · `requester_id` · `type_code` · `authority_rule`
     * @param array $payload حقولُ الطلبِ كما عرَّفها النطاق
     * @return array `ok` · `row_id` · `why`
     */
    public static function createFromLauncher(\mysqli $conn, array $ctx, array $payload)
    {
        $requester = isset($ctx['requester_id']) ? (int) $ctx['requester_id'] : 0;
        if ($requester <= 0) { return array('ok' => false, 'row_id' => 0, 'why' => 'لا طلب بلا صاحب'); }

        /* موظّفُ صاحبِ الحساب — والطلبُ لموظّفٍ لا لحسابٍ مجرَّد.
           ⚠ **والقراءةُ عبرَ البوّابةِ لا باستعلامٍ خامّ** (‏`FR-SEC-006`). */
        $gate = (isset($ctx['gate']) && $ctx['gate'] !== null) ? $ctx['gate'] : \ems_tenant_db();
        $empId = 0;
        try {
            $u = $gate->select('users', array('where' => array('id' => $requester), 'limit' => 1));
            if ($u) { $empId = (int) $u[0]['employee_id']; }
        } catch (\Throwable $t) { \error_log('w15 leave user: ' . $t->getMessage()); }
        if ($empId <= 0) { return array('ok' => false, 'row_id' => 0, 'why' => 'الحساب غير مربوط بموظف'); }

        $from = isset($payload['date_from']) ? (string) $payload['date_from'] : date('Y-m-d');
        $to   = isset($payload['date_to']) ? (string) $payload['date_to'] : $from;
        if (strtotime($to) < strtotime($from)) {
            return array('ok' => false, 'row_id' => 0, 'why' => 'تاريخ النهاية قبل البداية');
        }

        $row = array(
            'employee_id'   => $empId,
            'event_class'   => isset($payload['event_class']) ? (string) $payload['event_class'] : 'مخطّط',
            'event_type'    => isset($payload['event_type']) ? (string) $payload['event_type'] : 'إجازة',
            'date_from'     => $from,
            'date_to'       => $to,
            'reason'        => isset($payload['reason']) ? (string) $payload['reason'] : '',
            'state'         => 'مطلوب',
            'created_by'    => $requester,
        );
        try { $id = (int) $gate->insert('worker_leave_absence', $row); }
        catch (\Throwable $t) {
            error_log('w15 leave create: ' . $t->getMessage());
            return array('ok' => false, 'row_id' => 0, 'why' => 'تعذر انشاء الطلب عند مالكه');
        }
        if ($id <= 0) { return array('ok' => false, 'row_id' => 0, 'why' => 'تعذر انشاء الطلب عند مالكه'); }
        return array('ok' => true, 'row_id' => $id, 'why' => '');
    }
}
