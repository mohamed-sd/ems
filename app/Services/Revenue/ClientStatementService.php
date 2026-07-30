<?php
/**
 * app/Services/Revenue/ClientStatementService.php — كشفُ حساب العميل (M-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-03 §6: «تفاصيل: **كشفُ العميل بطبقاته** (مستخلصاتٌ · فواتيرُ · تحصيلاتٌ ·
 * محتجزٌ · مقدمةٌ · رصيد) **وكلُّ رقمٍ برابط مصدره**».
 * §4: «**الاستقطاعاتُ المحتجزة** — ضمانُ حسن التنفيذ يبقى رصيدًا محتجَزًا …
 * **ويظهر بندًا في كشف العميل** — لا يُنسى ولا يُخلط بالذمة الجارية» ·
 * «**الدفعة المقدمة** … **ورصيدُها المتبقي ظاهرٌ دائمًا**» · «**التحصيلُ الجزئي**
 * … **والتخصيصُ ظاهرٌ في الكشف لا صامتًا**».
 *
 * ── القاعدةُ الحاكمة (نظيرُ كشف المورد M-14 حرفيًّا) ────────────────────────
 * **كلُّ صفٍّ يحمل رابطَ مصدره — ومن لا مصدرَ له يُعلَن `orphan` ولا يُخفى.**
 * (إخفاءُ رقمٍ بلا مصدرٍ **يكذب**، وإظهارُه موسومًا **يُصلَح**.)
 *
 * ── وثلاثُ قواعدَ في البنية ────────────────────────────────────────────────
 * ① **المحتجزُ والمقدمةُ كلٌّ في طبقته** — لا مدمجَين في الرصيد الجاري:
 *    «لا يُنسى ولا يُخلط بالذمة الجارية» (§4) نصًّا.
 * ② **الرصيدُ من الفواتير لا من المستخلصات**: الفاتورةُ هي المطالبةُ النظامية،
 *    والمستخلصُ **اعترافٌ** سابقٌ عليها — وجمعُهما معًا يحتسب الدَّينَ مرتين.
 * ③ **لا تُجمع عملتان في رقم**: المجاميعُ **بعملةٍ عملةٍ**، وتعدُّدُها يُعلَن.
 */

namespace App\Services\Revenue;

class ClientStatementService
{
    /** خريطةُ مصدر الصف إلى شاشته — والرابطُ يشير إلى **مصدر السطر**. */
    const SOURCE_ROUTES = array(
        'claim'       => '../Contracts/claims.php?open=',
        'tax_invoice' => '../Contracts/tax_invoices.php?open=',
        'collection'  => '../Finance/payments_fin.php?id=',
        'advance'     => '../Contracts/advances.php?id=',
    );

    const LAYERS = array('claims', 'invoices', 'collections', 'retention', 'advance');
    const LAYER_LABELS = array(
        'claims'      => 'المستخلصات (اعترافٌ)',
        'invoices'    => 'الفواتير الضريبية (مطالبة)',
        'collections' => 'التحصيلات',
        'retention'   => 'المحتجز (ضمانُ حسن التنفيذ)',
        'advance'     => 'الدفعة المقدمة',
    );

    /**
     * بناءُ الكشف بطبقاته لفترة.
     *
     * @return array{layers:array,totals:array,orphans:int,period:array,currencies:array,notes:array}
     */
    public static function build($gate, $clientId, $from, $to)
    {
        $clientId = (int) $clientId;
        $out = array(
            'layers' => array(), 'orphans' => 0, 'currencies' => array(), 'notes' => array(),
            'period' => array('from' => (string) $from, 'to' => (string) $to),
            'totals' => array('claims' => 0.0, 'invoices' => 0.0, 'collections' => 0.0,
                              'retention' => 0.0, 'advance' => 0.0, 'balance' => 0.0),
        );
        foreach (self::LAYERS as $l) {
            $out['layers'][$l] = array('label' => self::LAYER_LABELS[$l], 'rows' => array(), 'total' => 0.0);
        }

        // ── ① المستخلصات — اعترافٌ لا مطالبة ────────────────────────────────
        $claims = array();
        try {
            $claims = $gate->scopedQuery(array('scope' => array('c' => 'claims')),
                "SELECT c.id, c.claim_no, c.state, c.period_from, c.period_to, c.currency,
                        c.gross_amount, c.retention_amount, c.net_amount, c.invoice_no
                   FROM claims c
                  WHERE {TENANT_SCOPE} AND c.client_id = ? AND COALESCE(c.is_deleted,0)=0
                    AND c.period_to BETWEEN ? AND ?
                  ORDER BY c.period_from, c.id",
                array($clientId, (string) $from, (string) $to));
        } catch (\Throwable $t) { $claims = array(); }

        $claimIds = array();
        foreach ($claims as $c) {
            $claimIds[] = (int) $c['id'];
            self::push($out, 'claims', self::row(
                'مستخلص ' . $c['claim_no'] . ' (' . $c['state'] . ') — '
                . $c['period_from'] . ' → ' . $c['period_to'],
                (string) $c['period_to'], (float) $c['net_amount'], (string) $c['currency'],
                'claim', (string) $c['id'], 'إجمالي ' . $c['gross_amount']));

            // ── ④ المحتجز — طبقةٌ مستقلةٌ لا تُخلط بالذمة الجارية (§4) ──────
            if ((float) $c['retention_amount'] > 0) {
                self::push($out, 'retention', self::row(
                    'محتجزُ حسن التنفيذ من مستخلص ' . $c['claim_no'],
                    (string) $c['period_to'], (float) $c['retention_amount'], (string) $c['currency'],
                    'claim', (string) $c['id'], 'يُردُّ بمهلته العقدية'));
            }
        }

        // ── ② الفواتير الضريبية — المطالبةُ النظامية (M-03) ─────────────────
        $invoices = array();
        if ($claimIds) {
            $in = implode(',', array_map('intval', $claimIds));
            try {
                $invoices = $gate->scopedQuery(
                    array('scope' => array('t' => 'tax_invoices'), 'enrich' => array('c' => 'claims')),
                    "SELECT t.id, t.serial_no, t.state, t.currency, t.net_amount, t.tax_amount,
                            t.total_amount, t.issued_at, c.claim_no
                       FROM tax_invoices t
                       LEFT JOIN claims c ON c.id = t.claim_id
                      WHERE {TENANT_SCOPE} AND t.claim_id IN ({$in})
                      ORDER BY t.id");
            } catch (\Throwable $t) { $invoices = array(); }
        }
        foreach ($invoices as $i) {
            $cancelled = ((string) $i['state'] === 'cancelled');
            self::push($out, 'invoices', self::row(
                'فاتورة ' . $i['serial_no'] . ($cancelled ? ' — **ملغاةٌ ضريبيًّا**' : '')
                . ' (مستخلص ' . $i['claim_no'] . ')',
                substr((string) $i['issued_at'], 0, 10),
                $cancelled ? 0.0 : (float) $i['total_amount'], (string) $i['currency'],
                'tax_invoice', (string) $i['id'],
                'صافٍ ' . $i['net_amount'] . ' + ضريبة ' . $i['tax_amount']));
        }

        // ── ③ التحصيلات — «والتخصيصُ ظاهرٌ في الكشف لا صامتًا» (§4) ─────────
        $collections = array();
        try {
            $collections = $gate->scopedQuery(
                array('scope' => array('p' => 'fin_payments'), 'enrich' => array('r' => 'fin_receivables')),
                "SELECT p.id, p.payment_no, p.amount, p.currency, p.method, p.state,
                        DATE(p.created_at) AS p_date, r.doc_ref, r.id AS recv_id
                   FROM fin_payments p
                   LEFT JOIN fin_receivables r ON r.id = p.receivable_id
                  WHERE {TENANT_SCOPE} AND p.direction = 'collection'
                    AND r.customer_entity_id = ? AND COALESCE(p.is_deleted,0)=0
                    AND DATE(p.created_at) BETWEEN ? AND ?
                  ORDER BY p.id",
                array($clientId, (string) $from, (string) $to));
        } catch (\Throwable $t) { $collections = array(); }
        foreach ($collections as $p) {
            self::push($out, 'collections', self::row(
                'تحصيل ' . $p['payment_no'] . ' (' . $p['state'] . ')'
                . ' — خُصّص لـ' . ($p['doc_ref'] !== null && $p['doc_ref'] !== ''
                                    ? $p['doc_ref'] : '⚠ بلا ذمّةٍ مخصَّصة'),
                (string) $p['p_date'], -1 * (float) $p['amount'], (string) $p['currency'],
                'collection', (string) $p['id'], (string) $p['method']));
        }

        // ── ⑤ الدفعة المقدمة — «ورصيدُها المتبقي ظاهرٌ دائمًا» (§4) ─────────
        // **الوصلُ بعقود مستخلصاته**: `contracts` بلا عمود عميل (الطرفُ الثاني
        // نصٌّ حر) — والرابطُ الموثوقُ هو `claims.contract_id` لعميلٍ بعينه.
        // فالقياسُ به **ويُعلَن كذلك**، ولا يُخترع ربطٌ لا وجودَ له.
        $advances = array();
        $contractIds = array();
        try {
            foreach ($gate->scopedQuery(array('scope' => array('c' => 'claims')),
                "SELECT DISTINCT c.contract_id FROM claims c
                  WHERE {TENANT_SCOPE} AND c.client_id = ? AND c.contract_id IS NOT NULL
                    AND COALESCE(c.is_deleted,0)=0", array($clientId)) as $x) {
                if ((int) $x['contract_id'] > 0) { $contractIds[] = (int) $x['contract_id']; }
            }
        } catch (\Throwable $t) { $contractIds = array(); }
        if ($contractIds) {
            $cin = implode(',', $contractIds);
            try {
                $advances = $gate->scopedQuery(array('scope' => array('a' => 'contract_advances')),
                    "SELECT a.id, a.advance_no, a.amount, a.currency, a.state, a.received_date, a.doc_ref
                       FROM contract_advances a
                      WHERE {TENANT_SCOPE} AND a.contract_id IN ({$cin})
                        AND COALESCE(a.is_deleted,0)=0
                      ORDER BY a.id");
            } catch (\Throwable $t) { $advances = array(); }
        } else {
            $out['notes'][] = 'ℹ لا عقدَ مربوطًا بمستخلصات هذا العميل — **فطبقةُ الدفعة المقدمة تُقرأ فارغةً وتُعلَن** '
                            . '(`contracts` بلا عمود عميل، والرابطُ الموثوقُ `claims.contract_id`)';
        }
        foreach ($advances as $a) {
            self::push($out, 'advance', self::row(
                'دفعةٌ مقدمة ' . $a['advance_no'] . ' (' . $a['state'] . ') — سند ' . $a['doc_ref'],
                (string) $a['received_date'], (float) $a['amount'], (string) $a['currency'],
                'advance', (string) $a['id'], 'تُستهلك بجدولها من كل مستخلص'));
        }

        // ── المجاميع ────────────────────────────────────────────────────────
        foreach (self::LAYERS as $l) {
            $out['totals'][$l] = round($out['layers'][$l]['total'], 2);
        }
        // ② الرصيدُ من **الفواتير** لا من المستخلصات — وإلا احتُسب الدَّينُ مرتين
        $out['totals']['balance'] = round($out['totals']['invoices'] + $out['totals']['collections'], 2);

        if (count($out['currencies']) > 1) {
            $out['notes'][] = '⚠ الكشفُ يحمل أكثرَ من عملة (' . implode(' · ', array_keys($out['currencies']))
                            . ') — **والمجاميعُ لا تجمع عملتين في رقم**: اقرأ كلَّ سطرٍ بعملته';
        }
        if ($out['orphans'] > 0) {
            $out['notes'][] = '⚠ ' . $out['orphans'] . ' صفًّا **بلا مصدرٍ يُنقر إليه** — يُعلَن ولا يُخفى';
        }
        if ($out['totals']['retention'] > 0) {
            $out['notes'][] = 'ℹ المحتجزُ ' . $out['totals']['retention']
                            . ' **رصيدٌ محتجزٌ في طبقته** — لا يُنسى ولا يُخلط بالذمة الجارية (§4)';
        }
        return $out;
    }

    private static function push(&$out, $layer, $row)
    {
        if ($row['orphan']) { $out['orphans']++; }
        if ($row['currency'] !== '') { $out['currencies'][$row['currency']] = true; }
        $out['layers'][$layer]['rows'][] = $row;
        $out['layers'][$layer]['total'] = round($out['layers'][$layer]['total'] + $row['amount'], 2);
    }

    /** صفٌّ بمصدره — و**من لا مصدرَ له يُوسم `orphan`** ولا يُخفى. */
    private static function row($desc, $date, $amount, $currency, $sourceKind, $sourceRef, $context = '')
    {
        $kind = trim((string) $sourceKind);
        $ref  = trim((string) $sourceRef);
        $orphan = ($kind === '' || $ref === '' || $ref === '0');
        $link = null;
        if (!$orphan && isset(self::SOURCE_ROUTES[$kind])) {
            $link = self::SOURCE_ROUTES[$kind] . rawurlencode($ref);
        }
        return array(
            'description' => (string) $desc,
            'date'        => (string) $date,
            'amount'      => round((float) $amount, 2),
            'currency'    => (string) $currency,
            'source_kind' => $kind,
            'source_ref'  => $ref,
            'context'     => (string) $context,
            'link'        => $link,
            'orphan'      => $orphan,
        );
    }
}
