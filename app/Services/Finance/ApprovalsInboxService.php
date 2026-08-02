<?php
/**
 * app/Services/Finance/ApprovalsInboxService.php — الصندوقُ الموحّد (M-42)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-02 §5/§6: صندوقٌ واحدٌ في «عملي اليوم» يجمع **كلَّ ما ينتظر قرارًا**
 * من الصناديق الأربعة المتفرقة: الطلبُ الماليُّ · تسويةُ المورد · القيدُ
 * اليدويُّ · إقفالُ الفترة — **والقرارُ يمرّ بخدمة مصدره لا هنا** (اللوحةُ
 * تعرض وتحيل، وكلُّ سطرٍ بزرِّ قفزٍ إلى موضع الفعل).
 */

namespace App\Services\Finance;

class ApprovalsInboxService
{
    /**
     * كلُّ ما ينتظر قرارًا — من مصادره الأربعة الحية.
     * @return array{ok:bool,boxes:array,total:int}
     */
    public static function inbox($conn, $companyId)
    {
        $co = (int) $companyId;
        $boxes = array();

        // ── ① الطلباتُ المالية — بانتظار مراجعةٍ أو اعتماد ───────────────────
        $rows = array();
        $r = $conn->query("SELECT id, request_no, request_type, amount, currency, state, created_at
                             FROM fin_requests
                            WHERE company_id={$co}
                              AND state IN ('submitted','under_review','pending_approval')
                            ORDER BY created_at LIMIT 50");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => $x['request_no'] . ' — ' . $x['request_type']
                    . ' (' . $x['amount'] . ' ' . $x['currency'] . ') · ' . $x['state'],
                'link' => '../FinRequests/finance_gateway.php?id=' . (int) $x['id'],
                'since' => (string) $x['created_at']);
        }
        $boxes[] = array('key' => 'requests', 'title' => 'الطلبات المالية',
            'owner' => 'FinRequests/finance_gateway.php', 'rows' => $rows, 'count' => count($rows));

        // ── ② تسوياتُ الموردين — مسودّاتٌ ومطلوبُ دفعها ─────────────────────
        $rows = array();
        $r = $conn->query("SELECT id, settlement_no, party_ref, party_name, state, created_at
                             FROM settlements
                            WHERE company_id={$co} AND state IN ('draft','payment_requested')
                            ORDER BY created_at LIMIT 50");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => ($x['settlement_no'] ?: ('تسوية #' . $x['id']))
                    . ' — ' . $x['party_name'] . ' · ' . $x['state'],
                'link' => '../Suppliers/settlements.php?id=' . (int) $x['id'],
                'since' => (string) $x['created_at']);
        }
        $boxes[] = array('key' => 'settlements', 'title' => 'تسويات الموردين',
            'owner' => 'Suppliers/settlements.php', 'rows' => $rows, 'count' => count($rows));

        // ── ③ القيودُ اليدوية — ما لم يُرحَّل بعد ───────────────────────────
        $rows = array();
        $r = $conn->query("SELECT id, entry_no, posting_date, state, memo
                             FROM fin_journal_entries
                            WHERE company_id={$co} AND state <> 'posted'
                            ORDER BY posting_date LIMIT 50");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => ($x['entry_no'] ?: ('قيد #' . $x['id'])) . ' — '
                    . mb_substr((string) $x['memo'], 0, 60) . ' · ' . $x['state'],
                'link' => '../Finance/journal_form_fin.php?id=' . (int) $x['id'],
                'since' => (string) $x['posting_date']);
        }
        $boxes[] = array('key' => 'journals', 'title' => 'القيود اليدوية غير المرحّلة',
            'owner' => 'Finance/journal_form_fin.php', 'rows' => $rows, 'count' => count($rows));

        // ── ④ إقفالُ الفترات — المقفلةُ ناعمًا تنتظر الإقفالَ النهائي ────────
        $rows = array();
        $r = $conn->query("SELECT id, CONCAT(fiscal_year,'-',LPAD(period_no,2,'0')) period_code, state
                             FROM fin_financial_periods
                            WHERE company_id={$co} AND state = 'soft_closed'
                            ORDER BY fiscal_year, period_no LIMIT 24");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => 'فترة ' . $x['period_code'] . ' — مقفلةٌ ناعمًا تنتظر النهائي',
                'link' => '../Finance/periods_fin.php',
                'since' => (string) $x['period_code']);
        }
        $boxes[] = array('key' => 'periods', 'title' => 'إقفال الفترات',
            'owner' => 'Finance/periods_fin.php', 'rows' => $rows, 'count' => count($rows));

        // ── ⑤ أذونات المواقع — كل إذن بندٌ واحدٌ يعرض لكل موافقٍ في دوره ─────
        //    (update0004 · ORG-01 §5 «قاعدة التنفيذ» · ORG-14)
        $rows = array();
        $r = $conn->query(
            "SELECT r.req_id, r.subject_ref, r.site_id, r.created_at, t.name_ar,
                    (SELECT rq.approver_role FROM permit_required_approvals rq
                      WHERE rq.permit_type_code = r.permit_type_code
                        AND NOT EXISTS (SELECT 1 FROM permit_approval_actions a
                                         WHERE a.req_id = r.req_id AND a.rq_id = rq.rq_id)
                      ORDER BY rq.seq_no LIMIT 1) AS next_role
               FROM permit_requests r
               JOIN permit_types t ON t.permit_type_code = r.permit_type_code
              WHERE r.company_id={$co} AND r.state = 'pending'
              ORDER BY r.created_at LIMIT 50");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => 'إذن #' . (int) $x['req_id'] . ' — ' . $x['name_ar']
                    . ' (' . $x['subject_ref'] . ') · الدور الآن: ' . ($x['next_role'] ?: '—'),
                'link' => '../admin/org_permits.php?id=' . (int) $x['req_id'],
                'since' => (string) $x['created_at']);
        }
        $boxes[] = array('key' => 'permits', 'title' => 'أذونات المواقع',
            'owner' => 'admin/org_permits.php', 'rows' => $rows, 'count' => count($rows));

        // ── ⑥ طلباتُ التبديل (NAV-01 v6 §6.3 · update0007 S-02) — بموافقتين ──
        $rows = array();
        $r = $conn->query("SELECT cov_id, reason_code, valid_to, estimated_hours, created_at
                             FROM substitute_coverages
                            WHERE state = 'Pending'
                            ORDER BY created_at LIMIT 50");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = array('label' => 'تبديل #' . (int) $x['cov_id'] . ' — ' . $x['reason_code']
                    . ' · حتى ' . $x['valid_to'] . ' · ~' . $x['estimated_hours'] . ' ساعة',
                'link' => '../Operations/swap_request.php?view=' . (int) $x['cov_id'],
                'since' => (string) $x['created_at']);
        }
        $boxes[] = array('key' => 'swaps', 'title' => 'طلبات التبديل — بموافقتين',
            'owner' => 'Operations/sites_board.php', 'rows' => $rows, 'count' => count($rows));

        $total = 0;
        foreach ($boxes as $b) { $total += (int) $b['count']; }
        return array('ok' => true, 'boxes' => $boxes, 'total' => $total);
    }
}
