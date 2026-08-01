<?php
/**
 * الإعارة وتعدد الكيانات — IntercompanyLoanService (N-09 · LEG-01 §7 النمط ②)
 * ───────────────────────────────────────────────────────────────────────────
 * إعارة معدة بين كيانين **داخليين** بقيمة محاسبية ومستحق متبادل بنسب تحمّل —
 * وإنشاء الإعارة **يفتح النمط ②** (internal_parties) لنطاق الكيانين تلقائيًّا.
 * القيود: الكيان لا يتعاقد مع نفسه (CHECK) · الطرفان داخليان وإلا 422 ·
 * Σ نسب التحمل = 100 · المستحق المتبادل صفّان متوازنان لكل فترة.
 */

namespace App\Services\Fleet;

class IntercompanyLoanService
{
    /** @return array{ok:bool,code:int,reason:string,loan_id:int} */
    public static function open(\mysqli $conn, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'loan_id' => 0);
        foreach (array('equipment_id', 'lender_entity_id', 'borrower_entity_id', 'date_from', 'monthly_value', 'bearing_split') as $f) {
            if (!isset($a[$f]) || $a[$f] === '') { $out['code'] = 422; $out['reason'] = 'حقل إلزامي: ' . $f; return $out; }
        }
        $lender = intval($a['lender_entity_id']); $borrower = intval($a['borrower_entity_id']);
        if ($lender === $borrower) {
            $out['code'] = 422; $out['reason'] = 'لا كيانَ يتعاقد مع نفسه — قيد التناقض ⑥ (CHECK بنيوي يسنده)';
            return $out;
        }
        // الطرفان داخليان (مستأجران في المجموعة)
        foreach (array($lender, $borrower) as $e) {
            $r = $conn->query("SELECT is_tenant FROM legal_entities WHERE entity_id = " . intval($e));
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || intval($row['is_tenant']) !== 1) {
                $out['code'] = 422;
                $out['reason'] = 'النمط ② للكيانات الداخلية — الكيان #' . $e . ' ليس من كيانات المجموعة (الأطراف الخارجية نمط ③)';
                return $out;
            }
        }
        $split = $a['bearing_split'];
        $sum = 0.0;
        foreach ($split as $pct) { $sum += (float) $pct; }
        if (abs($sum - 100.0) > 0.005) {
            $out['code'] = 422; $out['reason'] = 'Σ نسب التحمل = 100 — الفعلي ' . $sum;
            return $out;
        }
        $stmt = $conn->prepare(
            'INSERT INTO intercompany_loans (company_id, equipment_id, lender_entity_id, borrower_entity_id, date_from, date_to, monthly_value, currency, bearing_split_json, doc_ref, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $companyId = intval($companyId);
        $eq = intval($a['equipment_id']);
        $df = (string) $a['date_from'];
        $dt = isset($a['date_to']) && $a['date_to'] !== '' ? (string) $a['date_to'] : null;
        $mv = (float) $a['monthly_value'];
        $cur = isset($a['currency']) ? (string) $a['currency'] : 'SDG';
        $sj = json_encode($split, JSON_UNESCAPED_UNICODE);
        $doc = isset($a['doc_ref']) ? (string) $a['doc_ref'] : null;
        $act = intval($actor);
        $stmt->bind_param('iiiissdsssi', $companyId, $eq, $lender, $borrower, $df, $dt, $mv, $cur, $sj, $doc, $act);
        if (!$stmt->execute()) { $out['code'] = 422; $out['reason'] = $stmt->error; $stmt->close(); return $out; }
        $out['loan_id'] = intval($stmt->insert_id);
        $stmt->close();

        // فتح النمط ② لنطاق الكيانين — علم internal_parties (LEG-01 §7)
        foreach (array($lender, $borrower) as $e) {
            $conn->query("INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled, reason, set_by)
                          VALUES ('internal_parties', 'entity', " . intval($e) . ", 1, 'N-09: فُتح النمط ② بإعارة #" . $out['loan_id'] . "', " . $act . ")
                          ON DUPLICATE KEY UPDATE enabled = 1");
        }
        $out['ok'] = true; $out['code'] = 201;
        $out['reason'] = 'إعارة #' . $out['loan_id'] . ' بين كيانين داخليين — والنمط ② مفتوح لنطاقهما';
        return $out;
    }

    /** استحقاق فترة — مستحق متبادل مسجَّل: دائن (المعير) ومدين (المستعير) بنسب التحمل. */
    public static function accruePeriod(\mysqli $conn, $companyId, $loanId, $period)
    {
        $loanId = intval($loanId);
        $r = $conn->query('SELECT * FROM intercompany_loans WHERE loan_id = ' . $loanId . " AND state = 'active'");
        $loan = $r ? $r->fetch_assoc() : null;
        if (!$loan) { return array('ok' => false, 'code' => 404, 'reason' => 'الإعارة غير موجودة أو منتهية'); }
        $split = json_decode((string) $loan['bearing_split_json'], true);
        $amount = (float) $loan['monthly_value'];
        $created = 0;
        // المستحق المتبادل: للمعير على المستعير بنسبة تحمّله
        $borrowerPct = isset($split[(string) $loan['borrower_entity_id']]) ? (float) $split[(string) $loan['borrower_entity_id']] : 100.0;
        $due = round($amount * $borrowerPct / 100, 2);
        $stmt = $conn->prepare(
            'INSERT IGNORE INTO intercompany_dues (company_id, loan_id, period, creditor_entity_id, debtor_entity_id, amount, currency)
             VALUES (?, ?, ?, ?, ?, ?, ?)');
        $companyId = intval($companyId);
        $per = (string) $period;
        $cr = intval($loan['lender_entity_id']); $db = intval($loan['borrower_entity_id']);
        $cur = (string) $loan['currency'];
        $stmt->bind_param('iisiids', $companyId, $loanId, $per, $cr, $db, $due, $cur);
        $stmt->execute();
        if ($stmt->affected_rows === 1) { $created++; }
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'created' => $created, 'amount' => $due,
            'reason' => $created ? 'مستحق متبادل ' . $per . ': للمعير على المستعير ' . $due . ' ' . $cur . ' (بنسبة تحمّله ' . $borrowerPct . '%)' : 'مستحق الفترة قائم — عاطل');
    }
}
