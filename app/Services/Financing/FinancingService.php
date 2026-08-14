<?php
/**
 * طبقة التمويل والملكية — FinancingService (N-15 · FIN-01 §9)
 * ───────────────────────────────────────────────────────────────────────────
 * OwnershipTimelineService + FinancingOperationService + DeviationMonitor في
 * ملف واحد. كل الاستدعاءات على اتصال المجال المقيَّد (بعد عبور حارس الملكية —
 * الشاشات خلف صلاحية FIN-01 §1.1؛ الخدمة تفترض مستدعيًا مخوَّلًا).
 *
 * الأحكام: Σ الحصص النشطة في أي لحظة = 100.00 بالضبط (422 بالفارق) · لا تداخل
 * لنفس (الأصل×الممول) 409 · الخروج بمستند إلزامي 422 · التصحيح موثق بلا محو ·
 * لا عملية بلا نموذج ومعالجة 422 · قسط مكرر 409 بمفتاحه · الأقساط تولَّد لا
 * تُدخل · حدث القسط بمفتاح (العملية×القسط) · الانحراف لا يُغلق بلا قرار ومستند.
 */

namespace App\Services\Financing;

require_once __DIR__ . '/../../Core/EventPublisher.php';

use App\Core\EventPublisher;

class FinancingService
{
    // ═══════════════ الحصص — OwnershipTimelineService ═══════════════

    /** الحصص النافذة لأصل في تاريخ. */
    public static function sharesAt(\mysqli $conn, $companyId, $assetKind, $assetId, $onDate)
    {
        $stmt = $conn->prepare(
            "SELECT * FROM asset_ownership_shares
              WHERE company_id = ? AND asset_kind = ? AND asset_id = ?
                AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)
              ORDER BY percent DESC");
        $companyId = intval($companyId); $assetId = intval($assetId);
        $assetKind = (string) $assetKind; $onDate = (string) $onDate;
        $stmt->bind_param('isiss', $companyId, $assetKind, $assetId, $onDate, $onDate);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * كتابة حصص لحظةٍ واحدة معاملةً: إنهاء السابقة (close_previous) وفتح
     * الجديدة — **Σ عند التاريخ = 100.00 بالضبط وإلا 422 بالفارق**.
     * @param array $shares [{financier, percent, from, doc_ref?, op_id?, model_code?, capital?}]
     */
    public static function applyShares(\mysqli $conn, $companyId, $assetKind, $assetId, array $shares, $closePrevious = null, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'sum_at_date' => 0.0, 'shares' => 0);
        $companyId = intval($companyId); $assetId = intval($assetId); $assetKind = (string) $assetKind;
        if (empty($shares)) { $out['code'] = 422; $out['reason'] = 'لا حصص'; return $out; }
        $from = (string) $shares[0]['from'];

        // ① قيد المئة على الدفعة + المستمرة غير المغلقة
        $sum = 0.0;
        foreach ($shares as $s) { $sum += (float) $s['percent']; }
        $continuing = 0.0;
        foreach (self::sharesAt($conn, $companyId, $assetKind, $assetId, $from) as $ex) {
            $endsBefore = ($closePrevious !== null && ($ex['valid_to'] === null || $ex['valid_to'] >= $from));
            if (!$endsBefore) { $continuing += (float) $ex['percent']; }
        }
        $total = round($sum + $continuing, 2);
        if (abs($total - 100.00) > 0.005) {
            $out['code'] = 422;
            $out['reason'] = 'المجموع ' . number_format($total, 2) . ' (الفارق ' . sprintf('%+0.2f', $total - 100) . ') — Σ الحصص النشطة = 100.00 بالضبط';
            return $out;
        }
        // ② البيع/الانتقال بمستند إلزامي: أي حصة جديدة بعد إغلاق سابقة = انتقال
        if ($closePrevious !== null) {
            foreach ($shares as $s) {
                if (empty($s['doc_ref'])) {
                    $out['code'] = 422; $out['reason'] = 'بيع/انتقال حصة بلا مستند يُرفض — doc_ref إلزامي';
                    return $out;
                }
            }
        }
        // ③ لا تداخل لنفس (الأصل×الممول)
        foreach ($shares as $s) {
            $fid = intval($s['financier']);
            $stmt = $conn->prepare(
                "SELECT share_id FROM asset_ownership_shares
                  WHERE company_id = ? AND asset_kind = ? AND asset_id = ? AND financier_entity_id = ?
                    AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)" .
                ($closePrevious !== null ? " AND valid_from > ?" : "") . " LIMIT 1");
            if ($closePrevious !== null) {
                $cp = (string) $closePrevious;
                $stmt->bind_param('isiisss', $companyId, $assetKind, $assetId, $fid, $from, $from, $cp);
            } else {
                $stmt->bind_param('isiiss', $companyId, $assetKind, $assetId, $fid, $from, $from);
            }
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_row();
            $stmt->close();
            if ($dup) {
                $out['code'] = 409; $out['reason'] = 'تداخل فترتين لنفس (الأصل × الممول #' . $fid . ')';
                return $out;
            }
        }

        $conn->begin_transaction();
        try {
            if ($closePrevious !== null) {
                $stmt = $conn->prepare(
                    "UPDATE asset_ownership_shares SET valid_to = ?
                      WHERE company_id = ? AND asset_kind = ? AND asset_id = ?
                        AND (valid_to IS NULL OR valid_to >= ?) AND valid_from <= ?");
                $cp = (string) $closePrevious;
                $stmt->bind_param('sisiss', $cp, $companyId, $assetKind, $assetId, $cp, $cp);
                $stmt->execute();
                $stmt->close();
            }
            $n = 0;
            foreach ($shares as $s) {
                $stmt = $conn->prepare(
                    'INSERT INTO asset_ownership_shares (company_id, asset_kind, asset_id, financier_entity_id, op_id, model_code, percent, valid_from, capital, doc_ref, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $fid = intval($s['financier']);
                $opId = isset($s['op_id']) ? intval($s['op_id']) : null;
                $mc = isset($s['model_code']) ? (string) $s['model_code'] : null;
                $pct = (float) $s['percent'];
                $sfrom = (string) $s['from'];
                $cap = isset($s['capital']) ? (float) $s['capital'] : null;
                $doc = isset($s['doc_ref']) ? (string) $s['doc_ref'] : null;
                $act = intval($actor);
                $stmt->bind_param('isiiisdsdsi', $companyId, $assetKind, $assetId, $fid, $opId, $mc, $pct, $sfrom, $cap, $doc, $act);
                $stmt->execute();
                $stmt->close();
                $n++;
            }
            $conn->commit();
        } catch (\Throwable $t) {
            $conn->rollback();
            $out['code'] = 422; $out['reason'] = 'فشل الحفظ معاملةً: ' . $t->getMessage();
            return $out;
        }
        $final = 0.0;
        foreach (self::sharesAt($conn, $companyId, $assetKind, $assetId, $from) as $r) { $final += (float) $r['percent']; }
        $out['ok'] = true; $out['code'] = 200; $out['sum_at_date'] = round($final, 2); $out['shares'] = $n;
        $out['reason'] = 'طُبّقت — Σ عند ' . $from . ' = ' . number_format($final, 2);
        return $out;
    }

    /** التصحيح الموثق — النسبة المسجَّلة والمصححة والسبب والحكم، بلا محو. */
    public static function correctShare(\mysqli $conn, $companyId, $shareId, $correctedPct, $reason, $approvedPct, $actor)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { return array('ok' => false, 'code' => 422, 'reason' => 'سبب التصحيح إلزامي'); }
        $stmt = $conn->prepare(
            "UPDATE asset_ownership_shares
                SET recorded_percent = percent, corrected_percent = ?, correction_reason = ?, approved_percent = ?, percent = ?
              WHERE company_id = ? AND share_id = ?");
        $c = (float) $correctedPct; $ap = (float) $approvedPct;
        $companyId = intval($companyId); $shareId = intval($shareId);
        $stmt->bind_param('dsddii', $c, $reason, $ap, $ap, $companyId, $shareId);
        $stmt->execute();
        $done = $stmt->affected_rows > 0;
        $stmt->close();
        return array('ok' => $done, 'code' => $done ? 200 : 404,
            'reason' => $done ? 'صُحّحت موثَّقةً — المسجَّلة والمصححة والسبب والحكم، والأصل محفوظ للتدقيق' : 'غير موجودة');
    }

    // ═══════════════ العمليات — FinancingOperationService ═══════════════

    /** إنشاء عملية — لا اعتماد بلا نموذج ومعالجته المكتوبة. */
    public static function createOperation(\mysqli $conn, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'op_id' => 0);
        $mc = isset($a['model_code']) ? (string) $a['model_code'] : '';
        $stmt = $conn->prepare('SELECT model_code, accounting_recognition, policy_doc_ref FROM financing_models WHERE model_code = ? AND active = 1');
        $stmt->bind_param('s', $mc);
        $stmt->execute();
        $model = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$model) { $out['code'] = 422; $out['reason'] = 'نموذج تمويل غير معرَّف: «' . $mc . '» — ولا يُفترض نموذج أبدًا'; return $out; }
        if ((string) $model['policy_doc_ref'] === '') { $out['code'] = 422; $out['reason'] = 'لا اعتماد بلا معالجة محاسبية مكتوبة'; return $out; }
        foreach (array('op_code', 'financier_entity_id', 'currency', 'capital') as $f) {
            if (!isset($a[$f]) || $a[$f] === '') { $out['code'] = 422; $out['reason'] = 'حقل إلزامي: ' . $f; return $out; }
        }
        /* ══ INJ-0053 · سقفُ التفويضِ يحكم **حالةَ** العمليةِ لا رسالتَها ══════════
             نصُّ القبول: «إنشاءُ عمليةٍ فوق سقف الدور **لا يجعلها نافذةً** بل
             معلَّقةً بتصعيدٍ لمن يعلوه؛ وكلُّ عمليةٍ نافذةٍ تحمل مرجعَ تفويضِ
             معتمِدها».

             والمقيسُ قبلَه: كلُّ عمليةٍ تُنشأ `'active'` مهما بلغ رأسُ المال.
             فالسقفُ لم يكن يُقرأ أصلًا — و`AuthorityGuard` مبنيٌّ منذ LEG-01
             بتسعةِ مستهلكين ليس فيهم هذه الخدمة (عيبُ MD-05).

             ◆ والحكمُ **في الخدمةِ لا في الشاشة**: هي مَخنَقُ إنشاءِ العمليات،
               فأيُّ نافذةٍ أخرى تنشئ عمليةً تنال القيدَ نفسَه. */
        $companyId = intval($companyId);
        $capital = (float) $a['capital'];
        /* المصدرُ يُضمَّن **قبل** أوّلِ نداءٍ للصنف — لا داخلَ الشرطِ بعده */
        require_once dirname(dirname(__DIR__)) . '/Core/AuthorityGuard.php';
        $entityId = \App\Core\AuthorityGuard::tenantEntity($conn, $companyId);
        $authRef = null; $escRef = null; $newState = 'active';
        $capMsg = '';
        if ($entityId) {
            $sig = \App\Core\AuthorityGuard::sign($conn, array(
                'document_type' => 'financing_operation',
                'document_id'   => 0,          /* لم يُنشأ بعد — التوقيعُ على المبلغ */
                'step'          => 'create',
                'person_id'     => intval($actor),
                'company_id'    => $companyId,
                'entity_id'     => $entityId,
                'amount'        => $capital,
            ));
            if (!empty($sig['ok']) && !empty($sig['auth_id'])) {
                $authRef = 'AUTH-' . (int) $sig['auth_id'];
            } elseif ((int) $sig['code'] === 409) {
                /* فوقَ السقفِ: **معلَّقةٌ** لا نافذة — والتصعيدُ صفٌّ حقيقيّ */
                $newState = 'draft';
                $capMsg = ' — ' . $sig['reason'];
                $esc = $conn->prepare("INSERT INTO exec_approvals
                    (company_id, request_no, received_date, doc_type, document, requesting_dept,
                     raise_reason, amount, currency, status, source_kind, created_by, created_by_name)
                    VALUES (?, ?, CURDATE(), 'عملية تمويل', ?, 'التمويل والملكية',
                            ?, ?, ?, 'قيد المراجعة', 'escalation', ?, ?)");
                if ($esc) {
                    $rq  = 'ESC-FIN-' . date('ymdHis') . '-' . intval($actor);
                    $doc = 'عمليةُ تمويلٍ ' . (string) $a['op_code'];
                    $why = 'تجاوزُ سقفِ التفويضِ عند الإنشاء' . $capMsg;
                    $amtS = (string) $capital;
                    $curS = (string) $a['currency'];
                    $act2 = intval($actor);
                    $nm  = 'منشئُ العملية #' . $act2;
                    /* ثمانِ علاماتٍ في العبارةِ ⇐ ثمانيةُ مُعامَلات: `company_id`
                       أوّلُها — وقد سقط في أوّلِ صياغةٍ فرمى `ArgumentCountError`. */
                    $esc->bind_param('isssssis', $companyId, $rq, $doc, $why, $amtS, $curS, $act2, $nm);
                    if ($esc->execute()) { $escRef = $rq; }
                    $esc->close();
                }
            }
        }

        $stmt = $conn->prepare(
            'INSERT INTO financing_operations (company_id, op_code, financier_entity_id, model_code, currency, signed_date, capital, purchase_value, down_payment, profit_rate, profit_amount, installments_no, installment_amount, outstanding_balance, maturity_date, state, created_by, authority_ref, escalated_to)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $code = (string) $a['op_code']; $fid = intval($a['financier_entity_id']);
        $cur = (string) $a['currency']; $signed = isset($a['signed_date']) ? (string) $a['signed_date'] : date('Y-m-d');
        $pv = isset($a['purchase_value']) ? (float) $a['purchase_value'] : null;
        $dp = isset($a['down_payment']) ? (float) $a['down_payment'] : 0.0;
        $pr = isset($a['profit_rate']) ? (float) $a['profit_rate'] : null;
        $pa = isset($a['profit_amount']) ? (float) $a['profit_amount'] : null;
        $ni = isset($a['installments_no']) ? intval($a['installments_no']) : 0;
        $ia = isset($a['installment_amount']) ? (float) $a['installment_amount'] : null;
        $outstanding = round($capital - $dp + (float) ($pa !== null ? $pa : 0), 2);
        $mat = isset($a['maturity_date']) ? (string) $a['maturity_date'] : null;
        $act = intval($actor);
        $stmt->bind_param('isisssdddddiddssiss', $companyId, $code, $fid, $mc, $cur, $signed, $capital,
            $pv, $dp, $pr, $pa, $ni, $ia, $outstanding, $mat, $newState, $act, $authRef, $escRef);
        if (!$stmt->execute()) { $out['code'] = 422; $out['reason'] = $stmt->error; $stmt->close(); return $out; }
        $out['op_id'] = intval($stmt->insert_id);
        $stmt->close();
        $out['ok'] = true; $out['code'] = 201;
        $out['state'] = $newState;
        $out['authority_ref'] = $authRef;
        $out['escalated_to'] = $escRef;
        /* الرسالةُ تقول الحالةَ الحقيقيةَ — «نافذة» على معلَّقةٍ خداعٌ للمستخدم */
        $out['reason'] = ($newState === 'active')
            ? ('عملية ' . $code . ' (' . $mc . ' — ' . $model['accounting_recognition'] . ') نافذة برصيد '
               . $outstanding . ($authRef !== null ? ' · بمرجع تفويض ' . $authRef : ''))
            : ('عملية ' . $code . ' **معلَّقةٌ ولم تنفذ**' . $capMsg
               . ($escRef !== null ? ' · رُفعت لصندوق الاعتماد الأعلى (' . $escRef . ')' : ''));
        return $out;
    }

    /** توليد جدول الأقساط من العملية — ولا يُدخل يدويًّا؛ التكرار عاطل بالمفتاح. */
    public static function generateInstallments(\mysqli $conn, $companyId, $opId, $firstDue)
    {
        $opId = intval($opId);
        $stmt = $conn->prepare('SELECT installments_no, installment_amount, currency, outstanding_balance FROM financing_operations WHERE company_id = ? AND op_id = ?');
        $companyId = intval($companyId);
        $stmt->bind_param('ii', $companyId, $opId);
        $stmt->execute();
        $op = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$op || intval($op['installments_no']) <= 0) {
            return array('ok' => false, 'code' => 422, 'reason' => 'لا نظام أقساطٍ في العملية');
        }
        $n = intval($op['installments_no']);
        $amt = ($op['installment_amount'] !== null) ? (float) $op['installment_amount'] : round((float) $op['outstanding_balance'] / $n, 2);
        $created = 0;
        for ($i = 1; $i <= $n; $i++) {
            $due = date('Y-m-d', strtotime($firstDue . ' +' . ($i - 1) . ' month'));
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO financing_installments (op_id, seq_no, due_date, amount_principal, amount_total, currency)
                 VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iisdds', $opId, $i, $due, $amt, $amt, $op['currency']);
            $stmt->execute();
            if ($stmt->affected_rows === 1) { $created++; }
            $stmt->close();
        }
        return array('ok' => true, 'code' => 200, 'created' => $created,
            'reason' => $created . '/' . $n . ' قسطًا مولَّدًا — والتكرار عاطل بمفتاح (العملية×القسط)');
    }

    /** استحقاق قسط → حدث مالي بمفتاح (العملية × القسط) — لا يتكرر (F6). */
    public static function publishInstallmentDue(\mysqli $conn, $companyId, $opId, $seqNo, $actor)
    {
        $opId = intval($opId); $seqNo = intval($seqNo);
        $stmt = $conn->prepare(
            'SELECT i.*, o.company_id co FROM financing_installments i JOIN financing_operations o ON o.op_id = i.op_id
              WHERE i.op_id = ? AND i.seq_no = ?');
        $stmt->bind_param('ii', $opId, $seqNo);
        $stmt->execute();
        $inst = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$inst) { return array('ok' => false, 'code' => 404, 'reason' => 'القسط غير موجود'); }
        $r = EventPublisher::publish($conn, array(
            'event_key' => 'financing.installment.due',
            'category' => 'financial',
            'source_module' => 'treasury',
            'company_id' => intval($inst['co']),
            'entity_type' => 'financing_installment',
            'entity_id' => intval($inst['inst_id']),
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => intval($actor) ?: 1,
            'idempotency_key' => 'fininst:' . $opId . ':' . $seqNo,
            'amount' => (float) $inst['amount_total'],
            'currency' => (string) $inst['currency'],
            'payload' => array('op_id' => $opId, 'seq_no' => $seqNo, 'due_date' => $inst['due_date']),
        ));
        $conn->query("UPDATE financing_installments SET state = 'due' WHERE op_id = {$opId} AND seq_no = {$seqNo} AND state = 'scheduled'");
        return array('ok' => true, 'code' => 200, 'event_id' => intval($r['id']),
            'duplicate' => !empty($r['duplicate']),
            'reason' => !empty($r['duplicate']) ? 'حدث القسط قائم — 409 دلاليًّا بمفتاحه' : 'InstallmentDue منشور');
    }

    /** سداد قسط — يُثبَّت سعر يوم السداد (فرق محقق بسطره) وينقص الرصيد. */
    public static function payInstallment(\mysqli $conn, $companyId, $opId, $seqNo, $paidDate, $paymentRef, $fxRate = null, $functionalEq = null)
    {
        $opId = intval($opId); $seqNo = intval($seqNo);
        $stmt = $conn->prepare(
            "UPDATE financing_installments SET state = 'paid', paid_date = ?, payment_ref = ?, fx_rate_at_payment = ?, functional_equivalent = ?
              WHERE op_id = ? AND seq_no = ? AND state <> 'paid'");
        $pd = (string) $paidDate; $ref = (string) $paymentRef;
        $fx = ($fxRate !== null) ? (float) $fxRate : null;
        $fe = ($functionalEq !== null) ? (float) $functionalEq : null;
        $stmt->bind_param('ssddii', $pd, $ref, $fx, $fe, $opId, $seqNo);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$changed) { return array('ok' => true, 'code' => 200, 'reason' => 'مسدَّد سلفًا — عاطل'); }
        $stmt = $conn->prepare(
            "UPDATE financing_operations o
                SET o.outstanding_balance = GREATEST(0, o.outstanding_balance - (SELECT amount_total FROM financing_installments WHERE op_id = ? AND seq_no = ?)),
                    o.state = IF(o.outstanding_balance <= 0.005, 'settled', o.state)
              WHERE o.op_id = ?");
        $stmt->bind_param('iii', $opId, $seqNo, $opId);
        $stmt->execute();
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'reason' => 'سُدّد القسط والرصيد نقص — والسداد يتجاوز الرصيد محال بالقص إلى صفر');
    }

    /**
     * فروق الصرف على التزامات التمويل (F12 · PLAN-03 §7.2 حرفيًّا):
     * الرصيد القائم بعملة تخالف الدفاتر **يُعاد تقييمه دوريًّا** — فرق غير
     * محقق بسطره في fin_fx_differences (يُعكس عند السداد)، لا مدموجًا في
     * تكلفة التمويل. عاطل بمفتاح (العملية × الفترة) عبر note المفتاحية.
     */
    public static function revalueOutstanding(\mysqli $conn, $companyId, $period, array $rates, $functionalCurrency, $actor)
    {
        $companyId = intval($companyId);
        $period = (string) $period;
        $rows = array();
        $res = $conn->query(
            "SELECT op_id, op_code, currency, outstanding_balance FROM financing_operations
              WHERE company_id = {$companyId} AND state IN ('active','paying')
                AND outstanding_balance > 0 AND currency <> '" . $conn->real_escape_string((string) $functionalCurrency) . "'");
        $created = 0;
        while ($op = $res->fetch_assoc()) {
            $cur = (string) $op['currency'];
            if (!isset($rates[$cur]['old']) || !isset($rates[$cur]['new'])) { continue; }
            $old = (float) $rates[$cur]['old'];
            $new = (float) $rates[$cur]['new'];
            $bal = (float) $op['outstanding_balance'];
            $diff = round($bal * ($new - $old), 2);
            if (abs($diff) < 0.005) { continue; }
            $key = 'finrev:' . $op['op_id'] . ':' . $period;
            $ex = $conn->query("SELECT id FROM fin_fx_differences WHERE company_id = {$companyId} AND note = '" . $conn->real_escape_string($key) . "' LIMIT 1");
            if ($ex && $ex->fetch_row()) { continue; } // عاطل بالفترة
            // source_kind من ENUM القائم (revaluation) وsource_ref رقمي — والعطالة بمفتاح note
            $stmt = $conn->prepare(
                "INSERT INTO fin_fx_differences (company_id, kind, source_kind, source_ref, from_currency, functional_currency, amount, rate_from, rate_to, occurred_on, note, created_by)
                 VALUES (?, 'unrealized', 'revaluation', ?, ?, ?, ?, ?, ?, LAST_DAY(CONCAT(?, '-01')), ?, ?)");
            $ref = intval($op['op_id']); $fc = (string) $functionalCurrency;
            $act = intval($actor);
            $stmt->bind_param('iissdddssi', $companyId, $ref, $cur, $fc, $diff, $old, $new, $period, $key, $act);
            $okIns = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$okIns) {
                $rows[] = array('op' => $op['op_code'], 'error' => $err);
                continue;
            }
            $created++;
            $rows[] = array('op' => $op['op_code'], 'diff' => $diff, 'currency' => $cur);
        }
        return array('ok' => true, 'created' => $created, 'rows' => $rows,
            'reason' => $created . ' فرقًا غير محققٍ بسطره — يُعكس عند السداد ولا يُدمج في تكلفة التمويل');
    }

    // ═══════════════ الانحرافات — DeviationMonitor ═══════════════

    /** مسح دوري يولّد صفوف الأوراق الثلاث — Insert-only (التكرار عاطل بالمفتاح). */
    public static function deviationSweep(\mysqli $conn, $companyId)
    {
        $companyId = intval($companyId);
        $n = 0;
        // ① عقود بلا حركة: عملية نافذة بلا أي قسط مسدَّد ولا حدث دفتري
        $conn->query(
            "INSERT IGNORE INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, required_doc)
             SELECT o.company_id, 'no_ledger', CONCAT('op:', o.op_id),
                    CONCAT('عملية ', o.op_code, ' موقَّعة ولا حركة لها في الدفتر'), 'high', 'قرار: لم تبدأ · ألغيت · قيد ناقص'
               FROM financing_operations o
              WHERE o.company_id = {$companyId} AND o.state IN ('active','paying')
                AND NOT EXISTS (SELECT 1 FROM financing_installments i WHERE i.op_id = o.op_id AND i.state = 'paid')
                AND NOT EXISTS (SELECT 1 FROM fin_financial_events e WHERE e.entity_type = 'financing_installment'
                                  AND e.entity_id IN (SELECT inst_id FROM financing_installments WHERE op_id = o.op_id))");
        $n += $conn->affected_rows;
        // ② فروق السداد: قسط متجاوز استحقاقه بلا سداد
        $conn->query(
            "INSERT IGNORE INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, required_doc)
             SELECT o.company_id, 'payment_gap', CONCAT('inst:', i.inst_id),
                    CONCAT('قسط ', i.seq_no, ' من ', o.op_code, ' استُحق ', i.due_date, ' ولم يُسدَّد'), 'normal', 'سبب معتمد وقرار تسوية'
               FROM financing_installments i JOIN financing_operations o ON o.op_id = i.op_id
              WHERE o.company_id = {$companyId} AND i.state IN ('due','scheduled') AND i.due_date < CURDATE()");
        $n += $conn->affected_rows;
        // ③ الخروج غير المسجَّل: عملية مقفلة/مسدَّدة وحصة ممولها ما زالت مفتوحة
        $conn->query(
            "INSERT IGNORE INTO financing_deviations (company_id, dev_type, subject_ref, description, priority, required_doc)
             SELECT s.company_id, 'unrecorded_exit', CONCAT('share:', s.share_id),
                    CONCAT('حصة الممول #', s.financier_entity_id, ' ما زالت مفتوحة وعمليتها منتهية'), 'high', 'مستند بيع وتاريخ اعتماد'
               FROM asset_ownership_shares s JOIN financing_operations o ON o.op_id = s.op_id
              WHERE s.company_id = {$companyId} AND s.valid_to IS NULL AND o.state IN ('settled','closed')
                AND s.financier_entity_id = o.financier_entity_id");
        $n += $conn->affected_rows;
        return $n;
    }

    /** إغلاق صف انحراف — لا يُغلق بلا قرار ومستند (CHECK يسندها). */
    public static function closeDeviation(\mysqli $conn, $companyId, $devId, $decision, $docRef, $actor)
    {
        $decision = trim((string) $decision); $docRef = trim((string) $docRef);
        if ($decision === '' || $docRef === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'لا يُغلق صف بلا قرار ومستند');
        }
        $stmt = $conn->prepare(
            "UPDATE financing_deviations SET state = 'closed', decision = ?, decision_doc_ref = ?, closed_by = ?, closed_at = NOW()
              WHERE company_id = ? AND dev_id = ? AND state = 'open'");
        $companyId = intval($companyId); $devId = intval($devId); $act = intval($actor);
        $stmt->bind_param('ssiii', $decision, $docRef, $act, $companyId, $devId);
        $stmt->execute();
        $done = $stmt->affected_rows > 0;
        $stmt->close();
        return array('ok' => $done, 'code' => $done ? 200 : 404, 'reason' => $done ? 'أُغلق بقراره ومستنده' : 'غير موجود أو مغلق');
    }
}
