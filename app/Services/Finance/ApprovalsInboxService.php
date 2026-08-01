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

        $total = 0;
        foreach ($boxes as $b) { $total += (int) $b['count']; }
        return array('ok' => true, 'boxes' => $boxes, 'total' => $total);
    }
}
