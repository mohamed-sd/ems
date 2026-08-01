<?php
/**
 * PermissionReviewService — المراجعة النصف سنوية (SEC-01 §10⑥ · §12 خدمة ⑫ · SEC-25)
 * ───────────────────────────────────────────────────────────────────────────
 * «يوقّع كل مدير إدارة على صلاحيات فريقه بندًا بندًا — تأكيد أو خفض أو إلغاء ·
 * وما لم يُوقَّع خلال مهلته يُصعَّد للإدارة العامة» — وهي أهم ما يطلبه
 * المراجع الخارجي.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';
require_once __DIR__ . '/PositionService.php';

class PermissionReviewService
{
    /** فتح دورة لوحدة: سطر لكل (موظف × صلاحية) من المشتق الحي. */
    public static function openCycle(\mysqli $conn, $companyId, $orgUnitId, $period, $managerPersonId, $dueDays = 14)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'cycle_id' => 0, 'lines' => 0);
        $companyId = intval($companyId);
        $orgUnitId = intval($orgUnitId);
        $managerPersonId = intval($managerPersonId);
        $dueDays = intval($dueDays);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO permission_review_cycles (company_id, org_unit_id, period, manager_person_id, due_at, state)
                 VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), 'open')");
            $stmt->bind_param('iisii', $companyId, $orgUnitId, $period, $managerPersonId, $dueDays);
            if (!$stmt->execute()) {
                $stmt->close();
                $conn->rollback();
                $out['code'] = 409; $out['reason'] = 'دورة قائمة للوحدة والفترة نفسيهما';
                return $out;
            }
            $cycleId = intval($conn->insert_id);
            $stmt->close();

            // فريق الوحدة: أصحاب المراكز النشطة فيها — وكل صلاحياتهم المشتقة
            $res = $conn->query(
                "SELECT DISTINCT ep.person_id, ep.permission_code, ep.scope_rule
                   FROM person_positions pp
                   JOIN effective_permissions ep ON ep.person_id = pp.person_id AND ep.company_id = pp.company_id
                  WHERE pp.company_id = {$companyId} AND pp.org_unit_id = {$orgUnitId} AND pp.state = 'active'");
            $n = 0;
            while ($res && ($x = $res->fetch_assoc())) {
                $stmt = $conn->prepare(
                    'INSERT INTO permission_review_lines (cycle_id, person_id, permission_code, scope_rule) VALUES (?, ?, ?, ?)');
                $p = intval($x['person_id']);
                $stmt->bind_param('iiss', $cycleId, $p, $x['permission_code'], $x['scope_rule']);
                $stmt->execute();
                $stmt->close();
                $n++;
            }
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['cycle_id'] = $cycleId; $out['lines'] = $n;
            $out['reason'] = 'فُتحت الدورة بـ' . $n . ' بندًا';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
    }

    /** قرار بند — تأكيد أو خفض أو إلغاء (Insert-only على القرار الأول). */
    public static function decideLine(\mysqli $conn, $lineId, $decision, $reason = null)
    {
        if (!in_array($decision, array('confirm', 'reduce', 'revoke'), true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'القرار: confirm·reduce·revoke');
        }
        $lineId = intval($lineId);
        $stmt = $conn->prepare(
            "UPDATE permission_review_lines SET decision = ?, reason = ?, decided_at = NOW()
             WHERE line_id = ? AND decision IS NULL");
        $stmt->bind_param('ssi', $decision, $reason, $lineId);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return array('ok' => $ok, 'code' => $ok ? 200 : 409,
            'reason' => $ok ? 'سُجّل' : 'بند محسوم سلفًا — السجل لا يُعاد');
    }

    /** توقيع الدورة — لا توقيع وفيها بند بلا قرار. */
    public static function sign(\mysqli $conn, $cycleId, $managerPersonId)
    {
        $cycleId = intval($cycleId);
        $c = $conn->query("SELECT * FROM permission_review_cycles WHERE cycle_id = {$cycleId}")->fetch_assoc();
        if (!$c) { return array('ok' => false, 'code' => 404, 'reason' => 'دورة غير موجودة'); }
        if (intval($c['manager_person_id']) !== intval($managerPersonId)) {
            return array('ok' => false, 'code' => 403, 'reason' => 'التوقيع لمدير الدورة وحده');
        }
        $r = $conn->query("SELECT COUNT(*) c FROM permission_review_lines WHERE cycle_id = {$cycleId} AND decision IS NULL")->fetch_assoc();
        if (intval($r['c']) > 0) {
            return array('ok' => false, 'code' => 409, 'reason' => 'بقي ' . $r['c'] . ' بندًا بلا قرار — بندًا بندًا لا جملة');
        }
        $conn->query("UPDATE permission_review_cycles SET state = 'signed', signed_at = NOW() WHERE cycle_id = {$cycleId}");
        // تنفيذ قرارات الإلغاء: استثناء منع؟ الخفض والإلغاء ينفذهما مدير الصلاحيات
        // من مصادرها (قالب/استثناء/منح) — وهنا يُسجَّل الأثر تدقيقًا
        $res = $conn->query("SELECT person_id, permission_code, decision FROM permission_review_lines
                              WHERE cycle_id = {$cycleId} AND decision IN ('reduce','revoke')");
        while ($res && ($x = $res->fetch_assoc())) {
            PositionService::audit($conn, intval($c['company_id']), intval($x['person_id']),
                $x['decision'] === 'revoke' ? 'revoked' : 'reduced', $x['permission_code'], null,
                'قرار المراجعة الدورية ' . $c['period'] . ' — ينفذه مدير الصلاحيات من المصدر', intval($managerPersonId));
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'وُقِّعت الدورة');
    }

    /** التصعيد: دورات فات أجلها بلا توقيع → escalated + تنبيه الإدارة العامة (S25). */
    public static function escalateOverdue(\mysqli $conn, $companyId = null)
    {
        $coF = $companyId !== null ? ' AND company_id = ' . intval($companyId) : '';
        $res = $conn->query("SELECT cycle_id, company_id, org_unit_id, period FROM permission_review_cycles
                              WHERE state = 'open' AND due_at < CURDATE(){$coF}");
        $n = 0;
        while ($res && ($x = $res->fetch_assoc())) {
            $id = intval($x['cycle_id']);
            $conn->query("UPDATE permission_review_cycles SET state = 'escalated' WHERE cycle_id = {$id}");
            $stmt = $conn->prepare(
                "INSERT INTO fin_notifications (company_id, target_level, title, link)
                 VALUES (?, 'all', ?, 'admin/org_assignments.php')");
            $co = intval($x['company_id']);
            $title = 'تصعيد للإدارة العامة: مراجعة الصلاحيات ' . $x['period'] . ' للوحدة #' . $x['org_unit_id'] . ' لم تُوقَّع في مهلتها';
            $stmt->bind_param('is', $co, $title);
            $stmt->execute();
            $stmt->close();
            $n++;
        }
        return $n;
    }
}
