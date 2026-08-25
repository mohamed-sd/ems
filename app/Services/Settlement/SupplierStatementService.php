<?php
/**
 * app/Services/Settlement/SupplierStatementService.php — كشفُ حساب المورد (M-14)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-02 §6: «تفاصيل: **كشفُ المورد بطبقاته** و**كلُّ رقمٍ برابط مصدره** ·
 * تبويبُ اللقطة يعرض **الأسعارَ التي احتُسب بها**».
 * §3: «**لا إدخالَ حرًّا** — كلُّ تحميلٍ سطرٌ **برابط مستنده**؛ وما لا مستندَ له
 * لا يُحمَّل — **والمبلغُ يُقرأ من مصدره لا يُكتب**».
 * §7-القبول: «**وقراءةُ المورد كشفَه ففهمُه بندًا بندًا حتى مستنده**».
 *
 * ── القاعدةُ الحاكمة ───────────────────────────────────────────────────────
 * **كلُّ صفٍّ يحمل رابطَ مصدره — ومن لا مصدرَ له يُعلَن `orphan` ولا يُخفى.**
 * المقيسُ قبل البناء: الشاشةُ كانت **أربعةَ مجاميعَ صمّاء** بلا سطرٍ واحدٍ ولا
 * رابط — فالمورد يرى رقمًا ولا يعرف من أين جاء، وهو عينُ ما يمنعه §7-القبول.
 *
 * قراءةٌ خالصة: **لا كتابةَ ولا أثر**.
 */

namespace App\Services\Settlement;

require_once __DIR__ . '/../../../includes/catch_log.php';

class SupplierStatementService
{
    /** الطبقاتُ الخمس بترتيب سلسلة القيمة (§1). */
    const LAYERS = array(
        'entitlement' => 'الاستحقاقُ من الوحدات المعتمدة',
        'charges'     => 'التحميلاتُ الست بمصادرها',
        'penalties'   => 'الجزاءاتُ بقواعدها',
        'advances'    => 'السلفُ واستردادُها',
        'payments'    => 'السدادُ بمرجعه',
    );

    /** خريطةُ المصدر ⇒ الشاشةُ التي يُنقر إليها. */
    const SOURCE_ROUTES = array(
        'settlement'        => '../Suppliers/settlements.php?open=',
        'settlement_line'   => '../Suppliers/settlements.php?open=',
        'due'               => '../Finance/dues_fin.php?id=',
        'payment'           => '../Finance/payments_fin.php?id=',
        'supplier_advance'  => '../Suppliers/supplier_advances.php?supplier_id=',
        'proc_issue'        => '../Procurement/issues.php?id=',
        'mnt_order'         => '../Maintenance/orders.php?id=',
    );

    /**
     * بناءُ الكشف بطبقاته لفترة.
     *
     * @return array{layers:array,totals:array,orphans:int,period:array}
     */
    public static function build($gate, $supplierId, $from, $to)
    {
        $supplierId = (int) $supplierId;
        $out = array(
            'layers' => array(), 'orphans' => 0,
            'period' => array('from' => (string) $from, 'to' => (string) $to),
            'totals' => array('entitlement' => 0.0, 'charges' => 0.0, 'penalties' => 0.0,
                              'advances' => 0.0, 'payments' => 0.0, 'net' => 0.0, 'balance' => 0.0),
        );
        foreach (self::LAYERS as $k => $label) {
            $out['layers'][$k] = array('label' => $label, 'rows' => array(), 'total' => 0.0);
        }
        if ($supplierId <= 0) { return $out; }

        // ── ①② الاستحقاقُ والتحميلاتُ والجزاءاتُ من أسطر التسويات ─────────
        // المصدرُ أسطرُ التسوية لا مجاميعُها: «بندًا بندًا حتى مستنده».
        $lines = array();
        try {
            $lines = $gate->scopedQuery(
                array('scope' => array('l' => 'settlement_lines'), 'enrich' => array('s' => 'settlements')),
                "SELECT l.id, l.line_kind, l.charge_type, l.source_kind, l.source_ref,
                        l.description, l.work_date, l.amount, l.currency, l.objected,
                        s.id AS settlement_id, s.settlement_no, s.state
                   FROM settlement_lines l
                   LEFT JOIN settlements s ON s.id = l.settlement_id
                  WHERE {TENANT_SCOPE} AND s.party_type = 'supplier' AND s.party_ref = ?
                    AND COALESCE(s.is_deleted,0) = 0
                    AND s.period_to BETWEEN ? AND ?
                  ORDER BY s.period_from, l.id",
                array($supplierId, (string) $from, (string) $to));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $lines'); $lines = array(); }

        foreach ($lines as $l) {
            $isCharge = ((string) $l['line_kind'] === 'charge');
            $isPenalty = ($isCharge && (string) $l['charge_type'] === 'penalty');
            $isAdvance = ($isCharge && (string) $l['source_kind'] === 'supplier_advance');
            $layer = $isPenalty ? 'penalties' : ($isAdvance ? 'advances' : ($isCharge ? 'charges' : 'entitlement'));
            // التحميلُ سالبٌ في الكشف (يُنقص المستحق) والاستحقاقُ موجب
            $signed = $isCharge ? -1 * (float) $l['amount'] : (float) $l['amount'];

            $row = self::row(
                (string) $l['description'],
                (string) $l['work_date'],
                $signed,
                (string) $l['currency'],
                (string) $l['source_kind'],
                (string) $l['source_ref'],
                'تسوية ' . $l['settlement_no'] . ' (' . $l['state'] . ')',
                (int) $l['settlement_id']
            );
            $row['objected'] = (int) $l['objected'];
            $row['charge_type'] = $l['charge_type'];
            if ($row['orphan']) { $out['orphans']++; }
            $out['layers'][$layer]['rows'][] = $row;
            $out['layers'][$layer]['total'] = round($out['layers'][$layer]['total'] + $signed, 2);
        }

        // ── ④ استردادُ السلف الفعلي (M-12) — واقعةٌ لا نيّة ────────────────
        $recs = array();
        try {
            $recs = $gate->scopedQuery(
                array('scope' => array('r' => 'supplier_advance_recoveries'),
                      'enrich' => array('a' => 'supplier_advance_requests', 's' => 'settlements')),
                "SELECT r.id, r.amount, r.doc_ref, r.created_at, r.advance_id, r.settlement_id,
                        a.advance_type, a.balance, s.settlement_no
                   FROM supplier_advance_recoveries r
                   LEFT JOIN supplier_advance_requests a ON a.id = r.advance_id
                   LEFT JOIN settlements s ON s.id = r.settlement_id
                  WHERE {TENANT_SCOPE} AND a.supplier_id = ?
                    AND DATE(r.created_at) BETWEEN ? AND ?
                  ORDER BY r.id",
                array($supplierId, (string) $from, (string) $to));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $recs'); $recs = array(); }
        foreach ($recs as $r) {
            $out['layers']['advances']['rows'][] = self::row(
                'استرداد سلفة #' . (int) $r['advance_id'] . ' — سند ' . $r['doc_ref']
                . ' · الرصيد بعده ' . $r['balance'],
                substr((string) $r['created_at'], 0, 10),
                0.0,                               // الأثرُ المالي في بند التسوية أعلاه — هذا سطرُ أثرٍ لا مبلغٍ ثانٍ
                '', 'supplier_advance', (string) $r['advance_id'],
                'تسوية ' . (string) $r['settlement_no'], (int) $r['settlement_id']
            );
        }

        // ── ⑤ السدادُ بمرجعه ──────────────────────────────────────────────
        $pays = array();
        try {
            $pays = $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
                "SELECT p.id, p.amount, p.currency, p.state, p.payment_no, p.method, p.memo,
                        COALESCE(p.paid_at, p.created_at) AS pay_date
                   FROM fin_payments p
                  WHERE {TENANT_SCOPE} AND p.party_type = 'supplier' AND p.party_ref = ?
                    AND p.direction = 'disbursement' AND COALESCE(p.is_deleted,0) = 0
                    AND DATE(COALESCE(p.paid_at, p.created_at)) BETWEEN ? AND ?
                  ORDER BY p.id",
                array($supplierId, (string) $from, (string) $to));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $pays'); $pays = array(); }
        foreach ($pays as $p) {
            $row = self::row(
                'سداد ' . $p['payment_no'] . ' (' . $p['state'] . ' · ' . $p['method'] . ')'
                . (trim((string) $p['memo']) !== '' ? ' — ' . $p['memo'] : ''),
                substr((string) $p['pay_date'], 0, 10),
                -1 * (float) $p['amount'],
                (string) $p['currency'], 'payment', (string) $p['id'], '', (int) $p['id']
            );
            if ($row['orphan']) { $out['orphans']++; }
            $out['layers']['payments']['rows'][] = $row;
            $out['layers']['payments']['total'] = round($out['layers']['payments']['total']
                                                        - (float) $p['amount'], 2);
        }

        // ── المجاميع ──────────────────────────────────────────────────────
        foreach (self::LAYERS as $k => $_) { $out['totals'][$k] = $out['layers'][$k]['total']; }
        $out['totals']['net'] = round(
            $out['totals']['entitlement'] + $out['totals']['charges']
            + $out['totals']['penalties'] + $out['totals']['advances'], 2);
        $out['totals']['balance'] = round($out['totals']['net'] + $out['totals']['payments'], 2);
        return $out;
    }

    /**
     * «تبويبُ اللقطة يعرض **الأسعارَ التي احتُسب بها**» (§6).
     * يقرأ بنودَ عقد المورد النافذة (H-07) بمعدلاتها وأساسِ استعدادها.
     */
    public static function priceSnapshot($gate, $supplierId, $asOf)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('h' => 'supplier_contracts'),
                      'enrich' => array('l' => 'supplier_contract_lines')),
                "SELECT h.id AS contract_id, h.start_date, h.end_date, h.state, h.currency,
                        l.work_model, l.unit, l.unit_price, l.standby_basis, l.standby_rate,
                        l.valid_from, l.valid_to
                   FROM supplier_contracts h
                   LEFT JOIN supplier_contract_lines l
                          ON l.contract_id = h.id AND COALESCE(l.is_deleted,0) = 0
                  WHERE {TENANT_SCOPE} AND h.supplier_id = ? AND COALESCE(h.is_deleted,0) = 0
                    AND (h.start_date IS NULL OR h.start_date <= ?)
                    AND (h.end_date IS NULL OR h.end_date >= ?)
                  ORDER BY h.id, l.work_model",
                array((int) $supplierId, (string) $asOf, (string) $asOf));
        } catch (\Throwable $t) { return array(); }
    }

    /** رصيدُ السلف المفتوح — يُعرض في رأس الكشف («ظاهرٌ في بطاقته دائمًا»). */
    public static function openAdvanceBalance($gate, $supplierId)
    {
        require_once __DIR__ . '/SupplierAdvanceService.php';
        return SupplierAdvanceService::openBalance($gate, $supplierId);
    }

    // ═════════════════════════════════════════════════════════════════════

    /**
     * صفٌّ بمصدره — و**من لا مصدرَ له يُوسم `orphan`** ولا يُخفى.
     * (إخفاءُ رقمٍ بلا مصدرٍ أسوأُ من إظهاره موسومًا: الأولُ يكذب والثاني يُصلَح.)
     */
    private static function row($desc, $date, $amount, $currency, $sourceKind, $sourceRef, $context, $linkRef)
    {
        $kind = trim((string) $sourceKind);
        $ref  = trim((string) $sourceRef);
        $orphan = ($kind === '' || $ref === '' || $ref === '0');
        $link = null;
        if (!$orphan && isset(self::SOURCE_ROUTES[$kind])) {
            $link = self::SOURCE_ROUTES[$kind] . rawurlencode((string) ((int) $linkRef > 0 ? $linkRef : $ref));
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
            'objected'    => 0,
            'charge_type' => null,
        );
    }
}
