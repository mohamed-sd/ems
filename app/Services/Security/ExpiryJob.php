<?php
/**
 * ExpiryJob — السقوط الآلي للاستثناءات والمراكز (SEC-01 §12 خدمة ③ · SEC-18)
 * ───────────────────────────────────────────────────────────────────────────
 * «يُسقط الاستثناءات والتكليفات المنقضية في لحظتها ويعيد بناء المشتق» —
 * المسح بساعة القاعدة (NOW/CURDATE). تنبيه قبل 30 يومًا.
 * Events: PermissionExpired · PositionEnded — أسطر permission_audit_events.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';
require_once __DIR__ . '/PositionService.php';

class ExpiryJob
{
    /** @return array{exceptions:int, positions:int, notified:int, rebuilt:int} */
    public static function run(\mysqli $conn, $companyId = null)
    {
        $coF = $companyId !== null ? ' AND company_id = ' . intval($companyId) : '';
        $touched = array(); // person:company → إعادة بناء واحدة

        // ① الاستثناءات المنقضية — PermissionExpired
        $res = $conn->query("SELECT ex_id, company_id, person_id, permission_code FROM permission_exceptions
                              WHERE state = 'active' AND valid_to < NOW(){$coF}");
        $ex = 0;
        while ($res && ($x = $res->fetch_assoc())) {
            $id = intval($x['ex_id']);
            $conn->query("UPDATE permission_exceptions SET state = 'expired' WHERE ex_id = {$id}");
            PositionService::audit($conn, intval($x['company_id']), intval($x['person_id']),
                'expired', $x['permission_code'], null, 'انقضاء استثناء — PermissionExpired (سقوط آلي)', 0);
            $touched[$x['person_id'] . ':' . $x['company_id']] = true;
            $ex++;
        }

        // ② المراكز المنقضية — PositionEnded
        $res = $conn->query("SELECT p_id, company_id, person_id, title_code FROM person_positions
                              WHERE state = 'active' AND valid_to IS NOT NULL AND valid_to < CURDATE(){$coF}");
        $pos = 0;
        while ($res && ($x = $res->fetch_assoc())) {
            $id = intval($x['p_id']);
            $conn->query("UPDATE person_positions SET state = 'ended' WHERE p_id = {$id}");
            PositionService::audit($conn, intval($x['company_id']), intval($x['person_id']),
                'revoked', 'position:' . $id, null, 'انقضاء مركز — PositionEnded (سقوط آلي)', 0);
            $touched[$x['person_id'] . ':' . $x['company_id']] = true;
            $pos++;
        }

        // ③ إعادة بناء المشتق لمن مسّه سقوط — في لحظته
        $rebuilt = 0;
        foreach (array_keys($touched) as $k) {
            list($p, $c) = explode(':', $k);
            if (PermissionResolver::rebuild($conn, intval($p), intval($c)) >= 0) { $rebuilt++; }
        }

        // ④ تنبيه الثلاثين يومًا (استثناءات ومنح حساس بمراجعة مستحقة)
        $notified = 0;
        $res = $conn->query("SELECT ex_id, company_id FROM permission_exceptions
                              WHERE state = 'active' AND valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY){$coF}");
        $soonEx = $res ? $res->num_rows : 0;
        $res = $conn->query("SELECT gr_id, company_id FROM sensitive_access_grants
                              WHERE state = 'active' AND review_due_at IS NOT NULL
                                AND review_due_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY){$coF}");
        $soonGr = $res ? $res->num_rows : 0;
        if ($soonEx + $soonGr > 0) {
            $co = $companyId !== null ? intval($companyId) : 4;
            $chk = $conn->query("SELECT 1 FROM fin_notifications
                                  WHERE company_id = {$co} AND title LIKE 'صلاحيات قاربة الانتهاء%'
                                    AND DATE(created_at) = CURDATE() LIMIT 1");
            if (!$chk || $chk->num_rows === 0) {
                $title = 'صلاحيات قاربة الانتهاء: ' . $soonEx . ' استثناء و' . $soonGr . ' منحا حساسا بمراجعة مستحقة خلال 30 يوما';
                $stmt = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                                        VALUES (?, 'all', ?, 'admin/org_assignments.php')");
                $stmt->bind_param('is', $co, $title);
                $stmt->execute();
                $stmt->close();
                $notified = 1;
            }
        }

        return array('exceptions' => $ex, 'positions' => $pos, 'notified' => $notified, 'rebuilt' => $rebuilt);
    }
}
