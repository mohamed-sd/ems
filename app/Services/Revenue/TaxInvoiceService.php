<?php
/**
 * app/Services/Revenue/TaxInvoiceService.php — الفاتورةُ الضريبية (M-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-03 §4: «الفوترة · مستخلصٌ معتمد · **فاتورةٌ ضريبيةٌ مولَّدةٌ بحقولها
 * النظامية ورقمِها التسلسلي** — **ولا فاتورةَ بلا مستخلص**».
 * §6: «**لا تعديلَ بعد الإصدار** — والتصحيحُ زرُّ «إشعار دائن/مدين»».
 * §7: «فاتورةٌ بلا مستخلصٍ معتمد → **422**» · «تعديلُ فاتورةٍ صادرة → **423**».
 * §5: «**والضريبةُ سطرٌ مستقلٌّ بمرجعها**».
 *
 * ── أربعُ قواعدَ تحكم كلَّ رقمٍ هنا ─────────────────────────────────────────
 * ① **لا فاتورةَ بلا مستخلصٍ معتمد**: المصدرُ واحدٌ والمبالغُ **تُقرأ منه**
 *    لا تُكتب — و`claim_id` مفتاحٌ أجنبيٌّ بـ`RESTRICT` فوق حارس الخدمة.
 * ② **تسلسلٌ نظاميٌّ لكل (شركة × سنة)**: `INV-{سنة}-{تسلسل}` بفريدَين —
 *    التسلسلُ **يُحجز داخل المعاملة** والفريدُ هو الحكم، **والثغرةُ تُرى**.
 * ③ **لا تعديلَ بعد الإصدار**: المستخلصُ المفوتَر **تتجمّد أرقامُه** —
 *    و`claim_recalc` تعيد المحفوظَ ولا تعيد الحساب؛ والتصحيحُ **بإشعار**.
 * ④ **والضريبةُ بمرجعها**: مبلغُ ضريبةٍ بلا رمزٍ ونسبةٍ مكتوبين **مستحيل**
 *    (`CHECK`) — «سطرٌ مستقلٌّ بمرجعها» لا رقمٌ مضافٌ بلا سند.
 */

namespace App\Services\Revenue;

require_once __DIR__ . '/../../../includes/catch_log.php';

class TaxInvoiceService
{
    /** حالاتُ المستخلص التي تُصدَر منها فاتورة — «مستخلصٌ معتمد». */
    const INVOICEABLE_CLAIM_STATES = array('approved', 'invoiced');

    // ═════════════════════════════════════════════════════════════════════
    // ① الإصدار
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إصدارُ فاتورةٍ ضريبيةٍ لمستخلص.
     *
     * @return array{ok:bool,code:int,reason:string,invoice_id:?int,serial_no:?string,
     *               net:float,tax:float,total:float}
     */
    public static function issueForClaim($conn, $gate, $companyId, $claimId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'invoice_id' => null,
                     'serial_no' => null, 'net' => 0.0, 'tax' => 0.0, 'total' => 0.0);
        $claimId = (int) $claimId;

        $claim = self::claimOf($gate, $claimId);
        if (!$claim) { $out['code'] = 404; $out['reason'] = 'المستخلصُ غيرُ موجودٍ في نطاقك'; return $out; }

        // ── «ولا فاتورةَ بلا مستخلصٍ معتمد» (§4 · §7-Validation) ───────────
        // و`approving=true` تعني أن **المستدعي هو انتقالُ الاعتماد نفسُه** وقد
        // مرّ بحرّاسه (يدان لا يد · فترةٌ مفتوحة · صافٍ موجب) — وهو نصُّ §4:
        // «Approved → Invoiced» فعلٌ واحدٌ يقع في لحظة الإجازة. ولا يفتح هذا
        // بابًا لغيره: أيُّ مستدعٍ آخر يلزمه **مستخلصٌ معتمدٌ فعلًا**.
        $approving = !empty($args['approving']);
        $allowed = $approving
            ? array_merge(self::INVOICEABLE_CLAIM_STATES, array('review'))
            : self::INVOICEABLE_CLAIM_STATES;
        if (!in_array((string) $claim['state'], $allowed, true)) {
            $out['code'] = 422;
            $out['reason'] = 'المستخلصُ في حالة «' . $claim['state'] . '» — و**لا فاتورةَ بلا مستخلصٍ '
                           . 'معتمد** (ENT-03 §4): اعتمِدْه ثم أصدِر';
            return $out;
        }
        if (empty($claim['client_id'])) {
            $out['code'] = 422; $out['reason'] = 'لا عميلَ على المستخلص — ولا فاتورةَ بلا مشترٍ'; return $out;
        }

        $ex = self::byClaim($gate, $claimId);
        if ($ex) {
            $out['code'] = 409; $out['invoice_id'] = (int) $ex['id'];
            $out['serial_no'] = (string) $ex['serial_no'];
            $out['reason'] = 'للمستخلص فاتورةٌ صادرةٌ ' . $ex['serial_no']
                           . ' — **والتصحيحُ بإشعارٍ دائن/مدين لا بإعادة إصدار**';
            return $out;
        }

        $net = round((float) $claim['net_amount'], 2);
        if ($net <= 0) {
            $out['code'] = 422; $out['reason'] = 'صافي المستخلص غيرُ موجب — لا فاتورةَ لصفر'; return $out;
        }

        // ── الضريبةُ **بمرجعها**: الرمزُ يُقرأ من سجله ونسبتُه منه ─────────
        $taxCode = isset($args['tax_code']) ? trim((string) $args['tax_code']) : (string) $claim['tax_code'];
        $taxRate = null; $taxAmount = 0.0;
        if ($taxCode !== '') {
            $tc = null;
            try {
                $tc = $gate->selectOne('fin_tax_codes',
                    array('whereRaw' => 'code = ?', 'params' => array($taxCode)));
            } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $tc'); $tc = null; }
            if (!$tc) {
                $out['code'] = 422;
                $out['reason'] = 'رمزٌ ضريبيٌّ غيرُ مسجَّل: ' . $taxCode
                               . ' — و**الضريبةُ سطرٌ بمرجعها** لا نسبةٌ تُكتب يدًا (§5)';
                return $out;
            }
            $taxRate = round((float) $tc['rate'], 2);
            $taxAmount = round($net * $taxRate / 100.0, 2);
        }
        $total = round($net + $taxAmount, 2);

        // ── الحقولُ النظامية — لقطةٌ لحظةَ الإصدار لا اشتقاقٌ لاحق ─────────
        $fields = self::statutoryFields($conn, $gate, $companyId, $claim);
        if (!empty($fields['_missing'])) {
            $out['code'] = 422;
            $out['reason'] = 'حقولٌ نظاميةٌ ناقصةٌ للفاتورة: ' . implode(' · ', $fields['_missing'])
                           . ' — «بحقولها النظامية» (§4)';
            return $out;
        }

        $year = (int) date('Y');
        $invoiceId = null; $serial = null;
        try {
            $gate->runInTransaction(function ($g) use (
                &$invoiceId, &$serial, $claimId, $claim, $year, $net, $taxCode, $taxRate,
                $taxAmount, $total, $fields, $actor
            ) {
                $seq = self::nextSeq($g, $year);
                $serial = 'INV-' . $year . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
                $invoiceId = (int) $g->insert('tax_invoices', array(
                    'claim_id'        => $claimId,
                    'client_id'       => (int) $claim['client_id'],
                    'serial_no'       => $serial,
                    'serial_year'     => $year,
                    'serial_seq'      => $seq,
                    'currency'        => (string) $claim['currency'],
                    'net_amount'      => $net,
                    'tax_code'        => $taxCode !== '' ? $taxCode : null,
                    'tax_rate'        => $taxRate,
                    'tax_amount'      => $taxAmount,
                    'total_amount'    => $total,
                    'tax_fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                    'state'           => 'issued',
                    'issued_at'       => date('Y-m-d H:i:s'),
                    'issued_by'       => (int) $actor ?: null,
                ));
            }, 'إصدار فاتورة ضريبية لمستخلص ' . $claimId);
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409;
                $out['reason'] = 'تزاحمٌ على التسلسل — أعد المحاولة (الفريدُ حرس التسلسل)';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الإصدار: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'create', (int) $invoiceId, array(),
            array('claim_id' => $claimId, 'serial_no' => $serial, 'total' => $total));

        $out['ok'] = true; $out['code'] = 200;
        $out['invoice_id'] = (int) $invoiceId; $out['serial_no'] = $serial;
        $out['net'] = $net; $out['tax'] = $taxAmount; $out['total'] = $total;
        return $out;
    }

    /** التسلسلُ التالي لـ(شركة × سنة) — والفريدُ هو الحكمُ عند التزاحم. */
    private static function nextSeq($gate, $year)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('t' => 'tax_invoices')),
                "SELECT COALESCE(MAX(t.serial_seq),0) AS mx FROM tax_invoices t
                  WHERE {TENANT_SCOPE} AND t.serial_year = ?", array((int) $year));
            return $rows ? ((int) $rows[0]['mx'] + 1) : 1;
        } catch (\Throwable $t) { return 1; }
    }

    /**
     * الحقولُ النظامية — والناقصُ منها **يُسمّى** ولا يُملأ بفراغ.
     * (البائعُ من `admin_companies` والمشتري من `clients` — كلٌّ من سجله.)
     */
    public static function statutoryFields($conn, $gate, $companyId, $claim)
    {
        $f = array('_missing' => array());

        $co = null;
        try {
            $r = $conn->query("SELECT * FROM admin_companies WHERE id = " . (int) $companyId . " LIMIT 1");
            $co = $r ? $r->fetch_assoc() : null;
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $co'); $co = null; }
        $f['seller_name'] = $co ? (string) self::pick($co, array('company_name', 'name', 'title')) : '';
        $f['seller_tax_no'] = $co ? (string) self::pick($co, array('tax_number', 'tax_no', 'vat_number')) : '';
        if ($f['seller_name'] === '') { $f['_missing'][] = 'اسمُ البائع (بيانات الشركة)'; }

        $client = null;
        try {
            $client = $gate->selectOne('clients', array('where' => array('id' => (int) $claim['client_id'])));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $client'); $client = null; }
        $f['buyer_name'] = $client ? (string) self::pick($client, array('name', 'client_name', 'company_name')) : '';
        $f['buyer_tax_no'] = $client ? (string) self::pick($client, array('tax_number', 'tax_no', 'vat_number')) : '';
        $f['buyer_address'] = $client ? (string) self::pick($client, array('address', 'full_address')) : '';
        if ($f['buyer_name'] === '') { $f['_missing'][] = 'اسمُ المشتري (سجل العميل)'; }

        $f['claim_no']    = (string) $claim['claim_no'];
        $f['period_from'] = (string) $claim['period_from'];
        $f['period_to']   = (string) $claim['period_to'];
        $f['issued_on']   = date('Y-m-d');
        return $f;
    }

    private static function pick($row, $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') { return (string) $row[$k]; }
        }
        return '';
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② لا تعديلَ بعد الإصدار
    // ═════════════════════════════════════════════════════════════════════

    /**
     * هل يجوز تعديلُ مستخلصٍ؟ — «تعديلُ فاتورةٍ صادرة → **423**».
     * @return ?array{code:int,reason:string} null = يجوز
     */
    public static function assertEditable($gate, $claimId)
    {
        $inv = self::byClaim($gate, (int) $claimId);
        if (!$inv || (string) $inv['state'] === 'cancelled') { return null; }
        return array('code' => 423,
            'reason' => 'للمستخلص فاتورةٌ ضريبيةٌ صادرة (' . $inv['serial_no'] . ') — '
                      . '**لا تعديلَ بعد الإصدار**، والتصحيحُ **بإشعارٍ دائن/مدين** (ENT-03 §6)');
    }

    /** إلغاءٌ ضريبيٌّ بسببٍ مكتوب — ولا يُمحى صفٌّ ولا يُعاد استعمالُ رقمه. */
    public static function cancel($conn, $gate, $companyId, $invoiceId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $inv = self::head($gate, (int) $invoiceId);
        if (!$inv) { $out['code'] = 404; $out['reason'] = 'الفاتورةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $inv['state'] === 'cancelled') {
            $out['code'] = 409; $out['reason'] = 'الفاتورةُ ملغاةٌ سلفًا'; return $out;
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            $out['code'] = 422;
            $out['reason'] = 'الإلغاءُ الضريبيُّ **يلزمه سببٌ مكتوب** — ورقمُ الملغاة لا يُعاد استعمالُه';
            return $out;
        }
        try {
            $gate->update('tax_invoices', array(
                'state' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 255),
                'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => (int) $actor ?: null,
            ), array('id' => (int) $invoiceId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإلغاء: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'cancel', (int) $invoiceId,
            array('state' => 'issued'), array('state' => 'cancelled', 'reason' => $reason));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function head($gate, $invoiceId)
    {
        try { return $gate->selectOne('tax_invoices', array('where' => array('id' => (int) $invoiceId))); }
        catch (\Throwable $t) { return null; }
    }

    /** فاتورةُ المستخلص **الصادرة** — والملغاةُ لا تحجب إصدارًا جديدًا. */
    public static function byClaim($gate, $claimId)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('t' => 'tax_invoices')),
                "SELECT t.* FROM tax_invoices t
                  WHERE {TENANT_SCOPE} AND t.claim_id = ? AND t.state = 'issued'
                  ORDER BY t.id DESC LIMIT 1", array((int) $claimId));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function listAll($gate, $limit = 200)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('t' => 'tax_invoices'),
                      'enrich' => array('c' => 'claims', 'cl' => 'clients')),
                "SELECT t.*, c.claim_no, c.period_from, c.period_to, cl.client_name AS client_name
                   FROM tax_invoices t
                   LEFT JOIN claims c ON c.id = t.claim_id
                   LEFT JOIN clients cl ON cl.id = t.client_id
                  WHERE {TENANT_SCOPE}
                  ORDER BY t.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * أسطرُ المستخلص **ببند بيعها** — مواءمةُ PLAN-03 §5 (توسيعٌ لا هدم):
     * الفاتورةُ لا تخزّن أسطرًا (أرقامُها مجمَّدةٌ في رأسها) والبندُ يُقرأ من
     * `claim_lines.contract_line_id` (مفتاحُ P-09) — وما لا بندَ له
     * **يُعلَن غيرَ موصولٍ ولا يُخفى ولا يُخمَّن له بند**.
     */
    public static function linesOf($gate, $claimId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('l' => 'claim_lines'),
                      'enrich' => array('ccl' => 'client_contract_lines')),
                "SELECT l.id, l.work_date, l.equipment_ref, l.unit_type, l.qty, l.unit_price,
                        l.amount, l.contract_line_id,
                        ccl.line_no AS sale_line_no, ccl.description AS sale_line_desc,
                        ccl.pricing_model AS sale_line_model, ccl.tax_status AS sale_tax_status
                   FROM claim_lines l
                   LEFT JOIN client_contract_lines ccl ON ccl.id = l.contract_line_id
                  WHERE {TENANT_SCOPE} AND l.claim_id = ?
                  ORDER BY l.id", array((int) $claimId));
        } catch (\Throwable $t) { return array(); }
    }

    private static function claimOf($gate, $claimId)
    {
        try { return $gate->selectOne('claims', array('where' => array('id' => (int) $claimId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'tax_invoices', $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
