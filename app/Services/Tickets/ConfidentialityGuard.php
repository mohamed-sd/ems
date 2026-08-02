<?php
/**
 * ConfidentialityGuard — سرية البلاغات (TKT-01 §8 · §12 خدمة ⑦ · TKT-13)
 * ───────────────────────────────────────────────────────────────────────────
 * «يفصل operational_summary عن private_details في الاستعلام لا في العرض —
 * بلا صلاحية لا يُجلب الحقل أصلًا · وكل قراءة لسري تُسجَّل · والمجهول يُخفي
 * المبلغ عن المشتكى منه ويبقيه للحوكمة».
 */

namespace App\Services\Tickets;

class ConfidentialityGuard
{
    /** من يرى التفاصيل الخاصة؟ — بالمستوى (§8): protected→hr/gov · secret→gov/exec. */
    public static function canReadPrivate($confidentiality, $readerRole)
    {
        $r = (string) $readerRole;
        if ($r === '-1') { return true; }
        switch ((string) $confidentiality) {
            case 'normal':
                return true;
            case 'protected':
                return in_array($r, array('4', '15'), true); // الموارد البشرية أو الحوكمة وحدها
            case 'secret':
                return $r === '15'; // المستقبل المخول وحده (الحوكمة/الإدارة العامة)
            default:
                return false;
        }
    }

    /**
     * جلب بلاغ مرشَّحًا في الاستعلام: الأعمدة الخاصة لا تدخل SELECT لغير المخول.
     */
    public static function fetchTicket(\mysqli $conn, $tkId, $readerPersonId, $readerRole)
    {
        $tkId = intval($tkId);
        $meta = $conn->query("SELECT confidentiality, is_anonymous, reporter_user_id, company_id FROM tickets WHERE id = {$tkId}")->fetch_assoc();
        if (!$meta) { return null; }
        $priv = self::canReadPrivate($meta['confidentiality'], $readerRole);
        $cols = "id, ticket_no, ticket_type_id, stage, head_state, priority, confidentiality,
                 operational_summary, site_id, equipment_id, created_at";
        if ($priv) { $cols .= ", complaint, private_details"; }
        // المجهول: هوية المبلغ لا تُجلب لغير الحوكمة (§8-④)
        if ($priv && (intval($meta['is_anonymous']) === 0 || (string) $readerRole === '15' || (string) $readerRole === '-1')) {
            $cols .= ", reporter_user_id, reporting_person";
        }
        $row = $conn->query("SELECT {$cols} FROM tickets WHERE id = {$tkId}")->fetch_assoc();
        // كل قراءة لسري تُسجَّل — سجل LEG-01 بنطاق ticket.secret
        if ($priv && (string) $meta['confidentiality'] !== 'normal') {
            $stmt = $conn->prepare(
                "INSERT INTO sensitive_read_log (company_id, person_id, element_code, subject_type, subject_id, result, grant_ref, context)
                 VALUES (?, ?, 'ticket.secret', 'ticket', ?, 'allowed', ?, 'ConfidentialityGuard')");
            $co = intval($meta['company_id']);
            $p = intval($readerPersonId);
            $gr = 'role:' . $readerRole;
            $stmt->bind_param('iiis', $co, $p, $tkId, $gr);
            $stmt->execute();
            $stmt->close();
        }
        return $row;
    }

    /** الملخص التشغيلي للباقين: «غير متاح · يحتاج بديلًا · حتى كذا» — بلا سبب ولا اسم. */
    public static function operationalView(\mysqli $conn, $tkId)
    {
        $tkId = intval($tkId);
        return $conn->query("SELECT id, ticket_no, head_state, priority, operational_summary, site_id, created_at
                              FROM tickets WHERE id = {$tkId}")->fetch_assoc();
    }
}
