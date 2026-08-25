<?php
/**
 * DuplicateDetector — كشف التكرار والربط (TKT-01 §9 · §12 خدمة ⑥ · TKT-09)
 * ───────────────────────────────────────────────────────────────────────────
 * «عند الإنشاء يبحث آليًّا عن مفتوح بنفس (الموضوع × النوع × الموقع) في نافذة
 * معلنة ويعرضه قبل الحفظ» · «أتابعه» = متابع يُضاف ولا بلاغ ثانٍ ولا أمر عمل
 * ثانٍ · وثلاثة فأكثر في مجموعة خلال شهر → تُرفع «مشكلة».
 */

namespace App\Services\Tickets;

class DuplicateDetector
{
    /** البحث قبل الحفظ — النافذة المعلنة 72 ساعة (صف إعداد مستقبلًا). */
    public static function findOpen(\mysqli $conn, $companyId, $ticketTypeId, $siteId, $equipmentId = null, $windowHours = 72)
    {
        $sql = "SELECT id, ticket_no, complaint, created_at FROM tickets
                 WHERE company_id = " . intval($companyId) . "
                   AND ticket_type_id = " . intval($ticketTypeId) . "
                   AND head_state = 'open'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL " . intval($windowHours) . " HOUR)";
        if ($equipmentId !== null && intval($equipmentId) > 0) {
            $sql .= " AND equipment_id = " . intval($equipmentId);
        } elseif (intval($siteId) > 0) {
            $sql .= " AND site_id = " . intval($siteId);
        }
        $res = $conn->query($sql . " ORDER BY id DESC LIMIT 5");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
    }

    /**
     * «أتابعه»: ربط المكرر بالأصل — إغلاق إداري للمكرر (إن وُجد) وإضافة مبلغه
     * متابعًا للأصل فلا يُفقد أنه أبلغ، ورفع عدّاد التكرار (DuplicateLinked).
     */
    public static function linkDuplicate(\mysqli $conn, $originalTkId, $reporterPersonId, $duplicateTkId = null)
    {
        $originalTkId = intval($originalTkId);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT IGNORE INTO ticket_participants (tk_id, person_id, role) VALUES (?, ?, 'duplicate_reporter')");
            $p = intval($reporterPersonId);
            $stmt->bind_param('ii', $originalTkId, $p);
            $stmt->execute();
            $stmt->close();
            if ($duplicateTkId !== null) {
                $dup = intval($duplicateTkId);
                $conn->query("UPDATE tickets SET duplicate_of_ticket_id = {$originalTkId}, stage = 'cancelled',
                              head_state = 'closed', close_date = CURDATE() WHERE id = {$dup}");
                $conn->query("UPDATE ticket_workstreams SET state = 'admin_closed', closed_at = NOW()
                              WHERE tk_id = {$dup} AND state NOT IN ('closed','admin_closed')");
            }
            // مجموعة التكرار وعتبة «مشكلة» (RecurrenceThresholdReached)
            $orig = $conn->query("SELECT recurrence_group_id, equipment_id, site_id, ticket_type_id, company_id FROM tickets WHERE id = {$originalTkId}")->fetch_assoc();
            $grp = intval($orig['recurrence_group_id'] ?? 0);
            if ($grp === 0) {
                $grp = $originalTkId; // المجموعة برأسها الأول
                $conn->query("UPDATE tickets SET recurrence_group_id = {$grp} WHERE id = {$originalTkId}");
            }
            $cnt = intval($conn->query(
                "SELECT COUNT(*) c FROM (
                    SELECT id FROM tickets WHERE recurrence_group_id = {$grp}
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    UNION SELECT p_id FROM ticket_participants WHERE tk_id = {$originalTkId} AND role = 'duplicate_reporter'
                 ) x")->fetch_assoc()['c']);
            $promoted = false;
            if ($cnt >= 3) {
                // ثلاثة فأكثر خلال شهر → تُرفع «مشكلة» لا حادثة — سبب جذري
                $conn->query("UPDATE tickets SET ticket_nature = 'recurring' WHERE id = {$originalTkId}");
                $stmt = $conn->prepare("INSERT INTO ticket_responses (tk_id, person_id, response_type, body) VALUES (?, 0, 'info_added', ?)");
                $body = 'RecurrenceThresholdReached — ' . $cnt . ' في المجموعة خلال شهر: ترفع مشكلة وتحال لسبب جذري (§9)';
                $stmt->bind_param('is', $originalTkId, $body);
                $stmt->execute();
                $stmt->close();
                $promoted = true;
            }
            $conn->commit();
            return array('ok' => true, 'code' => 200, 'group' => $grp, 'count' => $cnt, 'promoted' => $promoted,
                'reason' => 'أضيف متابعا للأصل — ولا بلاغ ثان ولا أمر عمل ثان');
        } catch (\Throwable $e) {
            $conn->rollback();
            return array('ok' => false, 'code' => 500, 'reason' => $e->getMessage());
        }
    }
}
