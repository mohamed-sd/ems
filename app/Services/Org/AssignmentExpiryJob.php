<?php
/**
 * AssignmentExpiryJob — السقوط الآلي وتنبيه الثلاثين يومًا (ORG-01 §7 خدمة ③)
 * ───────────────────────────────────────────────────────────────────────────
 * «انتهاء مدة التكليف يُسقط الصلاحية في اللحظة نفسها — ولا يُنتظر تدخل بشري»
 * (ORG-01 §2). المسح بساعة القاعدة (CURDATE) لا PHP — بينهما فارق ساعة معلوم.
 * التنبيه قبل الانتهاء بثلاثين يومًا عبر fin_notifications (نمط
 * AuthorityGuard::sweepExpiring القائم).
 */

namespace App\Services\Org;

require_once __DIR__ . '/AssignmentService.php';

class AssignmentExpiryJob
{
    /**
     * يُنهي المنقضي ويولّد تنبيهات القادم على الانتهاء.
     * @return array{expired:int, notified:int}
     */
    public static function run(\mysqli $conn, $companyId = null, $daysAhead = 30)
    {
        $expired = 0;
        $coFilter = $companyId !== null ? ' AND company_id = ' . intval($companyId) : '';

        // ① الإنهاء الآلي — AssignmentExpired
        $res = $conn->query(
            "SELECT asg_id, person_id, valid_to, state FROM org_assignments
              WHERE state IN ('active','suspended') AND valid_to < CURDATE(){$coFilter}");
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        foreach ($rows as $r) {
            $asgId = intval($r['asg_id']);
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE org_assignments SET state = 'ended' WHERE asg_id = {$asgId}");
                AssignmentService::audit($conn, $asgId, 'ended',
                    'انتهاء المدة — سقوط آلي بلا تدخل بشري (AssignmentExpired)',
                    array('state' => $r['state'], 'valid_to' => $r['valid_to']),
                    array('state' => 'ended'), 0);
                $conn->commit();
                $expired++;
            } catch (\Throwable $e) {
                $conn->rollback();
            }
        }

        // ② تنبيه الثلاثين يومًا — «التكليفات التي تنتهي خلال ثلاثين يومًا»
        $daysAhead = intval($daysAhead);
        $res = $conn->query(
            "SELECT a.asg_id, a.company_id, a.person_id, a.valid_to, t.name_ar
               FROM org_assignments a
               JOIN org_assignment_types t ON t.type_code = a.assignment_type_code
              WHERE a.state = 'active'
                AND a.valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$daysAhead} DAY){$coFilter}");
        $soon = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        $notified = 0;
        foreach ($soon as $r) {
            // عطالة التنبيه: لا يُكرَّر تنبيه التكليف نفسه ليومه
            $chk = $conn->query(
                "SELECT 1 FROM fin_notifications
                  WHERE company_id = " . intval($r['company_id']) . "
                    AND title LIKE 'تكليف #" . intval($r['asg_id']) . " %'
                    AND DATE(created_at) = CURDATE() LIMIT 1");
            if ($chk && $chk->num_rows > 0) { continue; }
            $title = 'تكليف #' . intval($r['asg_id']) . ' (' . $r['name_ar'] . ') ينتهي في ' . $r['valid_to'];
            // target_level ENUM بلا قيمة لمدير التشغيل — 'all' كي لا يبتلع ENUM قيمة غريبة صامتًا
            $stmt = $conn->prepare(
                "INSERT INTO fin_notifications (company_id, target_level, title, link)
                 VALUES (?, 'all', ?, 'admin/org_assignments.php')");
            $co = intval($r['company_id']);
            $stmt->bind_param('is', $co, $title);
            $stmt->execute();
            $stmt->close();
            $notified++;
        }

        return array('expired' => $expired, 'notified' => $notified);
    }
}
