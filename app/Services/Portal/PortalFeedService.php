<?php
/**
 * app/Services/Portal/PortalFeedService.php — عقدُ التغذية الموحّد (H-18)
 * ═══════════════════════════════════════════════════════════════════════════
 * USR-01 §3: «لا تستعلم البوابةُ من جداول الإدارات مباشرةً في شاشاتها: كلُّ
 * بطاقةٍ بترويسةٍ ثابتة (الكود · العنوان · القيمة · الفترة · رابطُ المصدر ·
 * كودُ الظهور)» · «الموجزُ قراءةٌ لحظيةٌ — فلا رقمَ قديمٌ ولا مصدرُ حقيقةٍ ثانٍ».
 *
 * كلُّ بطاقةٍ تمرّ بالحارس الثلاثي (H-17) قبل تصييرها — «ولا يُصيَّر عنصرٌ
 * لم يُفحص»؛ والمخفيُّ يُبلَّغ قسمًا محجوبًا (hidden_sections) لا فراغًا صامتًا.
 */

namespace App\Services\Portal;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/VisibilityGuard.php';

class PortalFeedService
{
    /**
     * موجزُ الشخص لصفةٍ — بطاقاتٌ بترويسةٍ موحّدةٍ مفحوصةٌ بطاقة بطاقة.
     *
     * @return array{ok:bool,code:int,reason:string,cards:array,hidden_sections:array}
     */
    public static function feed($conn, $gate, $companyId, $accountId, $capacityId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'cards' => array(), 'hidden_sections' => array());
        $cap = null;
        try { $cap = $gate->selectOne('user_capacities', array('where' => array('id' => (int) $capacityId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $cap'); $cap = null; }
        if (!$cap || (int) $cap['account_id'] !== (int) $accountId) {
            $out['code'] = 403; $out['reason'] = 'الصفةُ ليست لهذا الحساب'; return $out;
        }
        // «صفةٌ منتهيةٌ → 403» (§9.2) — والانتهاءُ الكسولُ يفحص المصدر
        $fresh = CapacityService::activeOf($conn, $gate, (int) $accountId);
        foreach ($fresh as $f) { if ((int) $f['id'] === (int) $capacityId) { $cap = $f; break; } }
        if ((string) $cap['state'] !== 'active') {
            self::log($conn, $companyId, $accountId, $capacityId, 'feed', 'capacity', (string) $capacityId, 'denied');
            $out['code'] = 403;
            $out['reason'] = 'الصفةُ ' . $cap['state'] . ' — «قراءةُ تاريخِه فقط بلا إجراءات» (U8)';
            return $out;
        }

        $viewer = array('account_id' => (int) $accountId, 'role' => (string) $cap['role'],
                        'capacity_type' => (string) $cap['capacity_type'],
                        'scope_type' => (string) $cap['scope_type'],
                        'scope_id' => $cap['scope_id']);
        $subject = array('account_id' => (int) $accountId);
        $personId = $cap['person_id'] !== null ? (int) $cap['person_id'] : 0;
        $co = (int) $companyId;

        $builders = array(
            'card.contract' => function () use ($conn, $co, $personId) {
                if ($personId <= 0) { return array('value' => 'لا سجلَّ موظف', 'period' => '', 'link' => null); }
                $r = $conn->query("SELECT id, category, state, start_date, end_date
                                     FROM employee_contracts
                                    WHERE company_id={$co} AND employee_id={$personId}
                                      AND COALESCE(is_deleted,0)=0
                                    ORDER BY start_date DESC LIMIT 1");
                $row = $r ? $r->fetch_assoc() : null;
                if (!$row) { return array('value' => 'لا عقدَ في السجل', 'period' => '', 'link' => null); }
                return array('value' => $row['category'] . ' — ' . $row['state'],
                    'period' => $row['start_date'] . ' → ' . ($row['end_date'] ?: 'مفتوح'),
                    'link' => 'HR/employee_contracts.php?id=' . (int) $row['id']);
            },
            'card.units' => function () use ($conn, $co, $personId) {
                if ($personId <= 0) { return array('value' => 'لا ينطبق', 'period' => '', 'link' => null); }
                $r = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(qty_due),0),2) q
                                     FROM unit_party_awards
                                    WHERE company_id={$co} AND party='employee' AND party_ref={$personId}
                                      AND deleted_at IS NULL
                                      AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                $row = $r ? $r->fetch_assoc() : array('n' => 0, 'q' => 0);
                return array('value' => $row['n'] . ' حصةً · ' . $row['q'] . ' كميةً مستحقة',
                    'period' => date('Y-m'), 'link' => 'Operations/units.php');
            },
            'card.requests' => function () use ($conn, $co, $accountId) {
                // WFM أولًا (قاموس الـ62) والمالية القديمة تُجمع معًا — رقمٌ واحدٌ صادق
                $wfm = 0;
                $r = $conn->query("SELECT COUNT(*) n FROM requests
                                    WHERE company_id={$co} AND requester_user_id={$accountId}
                                      AND status NOT IN ('closed','cancelled','rejected')");
                if ($r && ($x = $r->fetch_assoc())) { $wfm = intval($x['n']); }
                $fin = 0;
                $r = $conn->query("SELECT COUNT(*) n FROM fin_requests
                                    WHERE company_id={$co} AND requester_id={$accountId}
                                      AND state NOT IN ('approved','rejected','paid','closed')");
                if ($r && ($x = $r->fetch_assoc())) { $fin = intval($x['n']); }
                return array('value' => ($wfm + $fin) . ' طلبًا جاريًا' . ($fin > 0 ? " (منها {$fin} مالية)" : ''),
                    'period' => '', 'link' => 'Portal/my_requests.php');
            },
            'card.approvals' => function () use ($conn, $co, $accountId) {
                // صندوق الاعتماد الموحد: حاملُ طلبٍ + خطوةُ approval_links + حلقةُ سلسلةٍ بدوره
                $n = 0;
                $r = $conn->query("SELECT COUNT(*) x FROM requests
                                    WHERE company_id={$co} AND current_holder_user_id={$accountId}
                                      AND status IN ('submitted','routed','in_approval','approved')");
                if ($r && ($x = $r->fetch_assoc())) { $n += intval($x['x']); }
                $r = $conn->query("SELECT COUNT(*) x FROM approval_links
                                    WHERE company_id={$co} AND status='pending' AND approver_user_id={$accountId}");
                if ($r && ($x = $r->fetch_assoc())) { $n += intval($x['x']); }
                $role = '';
                $r = $conn->query("SELECT role FROM users WHERE id={$accountId} LIMIT 1");
                if ($r && ($x = $r->fetch_assoc())) { $role = strval($x['role']); }
                $stageRoles = array('submitted' => array('5','6'), 'site_approved' => array('1'),
                                    'parties_review' => array('2','4'), 'parties_approved' => array('12'),
                                    'sales_approved' => array('17','19'));
                $mine = array();
                foreach ($stageRoles as $stage => $rr) { if (in_array($role, $rr, true)) { $mine[] = $stage; } }
                if ($mine) {
                    $in = "'" . implode("','", $mine) . "'";
                    $r = $conn->query("SELECT COUNT(*) x FROM unit_entries ue
                                        WHERE ue.company_id={$co} AND ue.state IN ({$in})
                                          AND NOT (ue.state='converted' AND ue.converted_at IS NULL)");
                    if ($r && ($x = $r->fetch_assoc())) { $n += intval($x['x']); }
                }
                return array('value' => $n . ' بانتظار قرارك', 'period' => '',
                    'link' => 'Portal/approvals_inbox.php');
            },
            'card.tickets' => function () use ($conn, $co, $accountId) {
                $r = $conn->query("SELECT COUNT(*) n FROM tickets
                                    WHERE company_id={$co}
                                      AND (reporter_user_id={$accountId} OR assigned_user_id={$accountId})
                                      AND close_date IS NULL");
                $row = $r ? $r->fetch_assoc() : array('n' => 0);
                return array('value' => $row['n'] . ' بلاغًا مفتوحًا', 'period' => '',
                    'link' => 'Tickets/tickets_list.php');
            },
            'card.payroll' => function () use ($conn, $co, $personId) {
                if ($personId <= 0) { return array('value' => 'لا ينطبق', 'period' => '', 'link' => null); }
                $r = $conn->query("SELECT pr.period_from, pr.period_to,
                                          ROUND(COALESCE(SUM(pl.amount),0),2) total
                                     FROM payroll_lines pl JOIN payroll_runs pr ON pr.id = pl.run_id
                                    WHERE pl.company_id={$co} AND pl.person_id={$personId}
                                      AND COALESCE(pr.is_deleted,0)=0
                                    GROUP BY pr.id ORDER BY pr.period_from DESC LIMIT 1");
                $row = $r ? $r->fetch_assoc() : null;
                if (!$row) { return array('value' => 'لا كشفَ بعد', 'period' => '', 'link' => null); }
                return array('value' => 'إجمالي المكوّنات ' . $row['total'],
                    'period' => $row['period_from'] . ' → ' . $row['period_to'],
                    'link' => 'Payroll/payroll_runs.php');
            },
            'card.activity' => function () use ($conn, $co, $accountId) {
                $r = $conn->query("SELECT MAX(at) last_at, COUNT(*) n FROM portal_activity_log
                                    WHERE company_id={$co} AND account_id={$accountId}");
                $row = $r ? $r->fetch_assoc() : null;
                return array('value' => ($row && $row['n'] ? $row['n'] . ' حدثًا' : 'لا نشاطَ مسجَّلًا'),
                    'period' => $row && $row['last_at'] ? ('آخرُه ' . $row['last_at']) : '',
                    'link' => null);
            },
        );

        $titles = array();
        foreach (VisibilityPolicyService::elements($conn) as $e) {
            $titles[(string) $e['element_code']] = (string) $e['title_ar'];
        }

        foreach ($builders as $code => $fn) {
            $v = VisibilityGuard::check($conn, $gate, $companyId, $viewer, $code, $subject);
            if ($v['decision'] === 'deny') {
                self::log($conn, $companyId, $accountId, $capacityId, 'feed_card', $code, '', 'denied');
                $out['code'] = 403; $out['reason'] = $v['reason']; return $out;
            }
            if ($v['decision'] !== 'allow') {
                // «قسمٌ مغلقٌ بمفتاحٍ → لا يُصيَّر» — ويُعلَن محجوبًا لا فراغًا
                $out['hidden_sections'][] = $code;
                continue;
            }
            try { $data = $fn(); } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'بانيةُ بطاقةٍ واحدةٍ فشلت — تُعرض بقيمةٍ محايدةٍ وبقيةُ البطاقاتِ تعمل'); $data = array('value' => '—', 'period' => '', 'link' => null); }
            $out['cards'][] = array(
                'code' => $code,
                'title' => isset($titles[$code]) ? $titles[$code] : $code,
                'value' => (string) $data['value'],
                'period' => (string) $data['period'],
                'source_link' => $data['link'],
                'visible' => true,
            );
        }

        self::log($conn, $companyId, $accountId, $capacityId, 'feed', 'portal', '', 'ok');
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** سجلُّ النشاط — Insert-only (USR-01 §5). */
    public static function log($conn, $companyId, $accountId, $capacityId, $action, $targetType, $targetId, $result)
    {
        $st = $conn->prepare("INSERT INTO portal_activity_log
            (company_id, account_id, capacity_id, action_code, target_type, target_id, result, ip, device)
            VALUES (?,?,?,?,?,?,?,?,?)");
        if (!$st) { return; }
        $co = (int) $companyId; $acc = (int) $accountId;
        $cid = (int) $capacityId ?: null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        $dev = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 190) : null;
        $st->bind_param('iiissssss', $co, $acc, $cid, $action, $targetType, $targetId, $result, $ip, $dev);
        $st->execute();
        $st->close();
    }
}
