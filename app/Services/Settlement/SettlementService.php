<?php
/**
 * app/Services/Settlement/SettlementService.php — خدمةُ التسوية الموحّدة
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-02 §15.3/§15.4 · UX-05 §2.2/§5.2 — خدمةٌ واحدةٌ للطرفين (`supplier` و
 * `employee`) بمدخلاتها المنصوصة: PartyType · PartyID · PeriodFrom/To · CompanyID.
 *
 * **صفرُ إدخالٍ يدويٍّ لمصدر** (نصُّ §15.3): البنودُ تُجلب من مصادرها كلٌّ برابط
 * أصله — لا يكتب المستخدمُ مبلغًا ولا كمية:
 *   · الاستحقاقُ الأولي  ← `fin_dues` بـ`direction='credit'` (يغذّيها محرّكُ المروحة)
 *   · التحميلاتُ         ← `fin_dues` بـ`direction='debit'` (وقود · سلف · جزاءات · نقل)
 *                        + `proc_issue` (قطعُ الغيار) و`mnt_order` (الصيانة)
 *                          بعمودِ التحميل الصريح `charge_supplier_id`.
 *
 * **العملة**: التسويةُ بعملة الأساس، وكلُّ بندٍ يحتفظ بعملته وسعرِ صرفِ **تاريخه**
 * ومعادلِه. وبندٌ لا سعرَ لتاريخه يبقى بلا معادلٍ ويمنع الاعتماد بسببٍ معلَن —
 * لا يُحسب بصفرٍ ولا بسعرٍ مفترَض (قاعدةُ عدم التلفيق).
 *
 * **الصافي السالب** (قرارُ المالك 2026-07-28): حين تفوق التحميلاتُ الاستحقاقَ،
 * التسويةُ تُعتمد وتُفتح **ذمّةٌ مدينةٌ على الطرف** — الرقمُ لا يضيع ولا يُنسى.
 *
 * **فصلُ اليدين** (§15.4): `prepared_by ≠ approved_by` بنيويًّا — لا يعتمد المرءُ
 * ما أعدّ. والإعدادُ لإدارة الموردين والإجازةُ للمالية (قرارُ المالك).
 *
 * @package EMS\Services\Settlement
 */

namespace App\Services\Settlement;

class SettlementService
{
    /** الحالاتُ الست بحروف §15.3. */
    const ST_DRAFT     = 'draft';
    const ST_REVIEW    = 'review';
    const ST_APPROVED  = 'approved';
    const ST_REQUESTED = 'payment_requested';
    const ST_PAID      = 'paid';
    const ST_CANCELLED = 'cancelled';

    /** أنواعُ التحميل الستة (UX-05 §2.2) — الأربعةُ بلا مصدرٍ تدخل عبر دفتر الطرف. */
    const CHARGE_TYPES = array('fuel', 'parts', 'maintenance', 'transport', 'advance', 'penalty');

    /**
     * توليدُ التسوية من مصادرها.
     *
     * @return array ok · settlement_id · reason · code (409/422) · counts
     */
    public static function generate($gate, $conn, $partyType, $partyRef, $from, $to, $userId)
    {
        $out = array('ok' => false, 'settlement_id' => null, 'reason' => '', 'code' => 0,
                     'entitlements' => 0, 'charges' => 0, 'unpriced' => 0);

        $partyType = (string) $partyType;
        $partyRef  = intval($partyRef);
        if (!in_array($partyType, array('supplier', 'employee'), true) || $partyRef <= 0) {
            $out['reason'] = 'طرفٌ غير صالح'; $out['code'] = 422; return $out;
        }
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $from) ||
            !preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $to) || $from > $to) {
            $out['reason'] = 'فترةٌ غير صالحة'; $out['code'] = 422; return $out;
        }

        // ── العطالة: تسويةٌ واحدةٌ لكل (طرف × فترة) — 409 بمرجع القائم (§15.4)
        $ex = $gate->selectOne('settlements', array(
            'columns'  => array('id', 'settlement_no', 'state'),
            'whereRaw' => "party_type = ? AND party_ref = ? AND period_from = ? AND period_to = ?",
            'params'   => array($partyType, $partyRef, $from, $to),
        ));
        if ($ex) {
            $out['settlement_id'] = intval($ex['id']);
            $out['reason'] = 'لهذه الفترة تسويةٌ قائمة: ' . $ex['settlement_no'];
            $out['code']   = 409;
            return $out;
        }

        $lines = self::collectLines($gate, $partyType, $partyRef, $from, $to);

        $entitlements = 0; $charges = 0;
        foreach ($lines as $l) { if ($l['line_kind'] === 'entitlement') { $entitlements++; } else { $charges++; } }

        // 422: طرفٌ بلا أحكامٍ بالفترة (§15.4) — لا تسويةَ من عدم
        if ($entitlements === 0 && $charges === 0) {
            $out['reason'] = 'لا استحقاقَ ولا تحميلَ لهذا الطرف في الفترة';
            $out['code']   = 422;
            return $out;
        }

        require_once dirname(dirname(__DIR__)) . '/../includes/fx.php';
        $base = ems_fx_base_currency();
        if ($base === null) {
            $out['reason'] = 'لا عملةَ أساسٍ مسجَّلة'; $out['code'] = 422; return $out;
        }

        // ── التقييمُ بعملة الأساس، كلُّ بندٍ بسعر تاريخه ─────────────────────
        $gross = 0.0; $chargeSum = 0.0; $unpriced = 0;
        foreach ($lines as $i => $l) {
            $conv = ems_fx_to_base($l['amount'], $l['currency'], $l['work_date']);
            $lines[$i]['fx_rate']     = $conv['ok'] ? $conv['rate'] : null;
            $lines[$i]['base_amount'] = $conv['ok'] ? $conv['base'] : null;
            if (!$conv['ok']) { $unpriced++; continue; }
            if ($l['line_kind'] === 'entitlement') { $gross += $conv['base']; }
            else                                   { $chargeSum += $conv['base']; }
        }
        $gross     = round($gross, 2);
        $chargeSum = round($chargeSum, 2);
        $net       = round($gross - $chargeSum, 2);

        $no   = self::nextNo($gate, $conn);
        $name = self::partyName($gate, $partyType, $partyRef);

        $sid = null;
        try {
            $gate->runInTransaction(function ($g) use (
                &$sid, $no, $partyType, $partyRef, $name, $from, $to, $base,
                $gross, $chargeSum, $net, $userId, $lines
            ) {
                $sid = $g->insert('settlements', array(
                    'settlement_no'  => $no,
                    'party_type'     => $partyType,
                    'party_ref'      => $partyRef,
                    'party_name'     => $name,
                    'period_from'    => $from,
                    'period_to'      => $to,
                    'currency'       => $base,
                    'fx_rate'        => 1.0,            // التسويةُ بعملة الأساس نفسِها
                    'base_amount'    => $net,
                    'gross_amount'   => $gross,
                    'charges_amount' => $chargeSum,
                    'net_amount'     => $net,
                    'net_direction'  => ($net < 0) ? 'receivable' : 'payable',
                    'state'          => self::ST_DRAFT,
                    'prepared_by'    => $userId,
                    'prepared_at'    => date('Y-m-d H:i:s'),
                    'created_by'     => $userId,
                ));

                foreach ($lines as $l) {
                    $g->insert('settlement_lines', array(
                        'settlement_id' => $sid,
                        'line_kind'     => $l['line_kind'],
                        'charge_type'   => isset($l['charge_type']) ? $l['charge_type'] : null,
                        'source_kind'   => $l['source_kind'],
                        'source_ref'    => $l['source_ref'],
                        'description'   => $l['description'],
                        'work_date'     => $l['work_date'],
                        'amount'        => $l['amount'],
                        'currency'      => $l['currency'],
                        'fx_rate'       => $l['fx_rate'],
                        'base_amount'   => $l['base_amount'],
                    ));
                    // وسمُ صفِّ الدفتر بتسويته — فلا يُحتسب في تسويتين
                    if ($l['source_kind'] === 'due') {
                        $g->update('fin_dues', array('settlement_id' => $sid),
                                   array('id' => intval($l['source_ref'])));
                    }
                }
            }, 'توليد تسوية ' . $no);
        } catch (\Throwable $t) {
            error_log('settlement generate: ' . $t->getMessage());
            $out['reason'] = 'تعذّر توليدُ التسوية';
            return $out;
        }

        $out['ok'] = true;
        $out['settlement_id'] = intval($sid);
        $out['entitlements']  = $entitlements;
        $out['charges']       = $charges;
        $out['unpriced']      = $unpriced;
        return $out;
    }

    /**
     * جمعُ البنود من مصادرها — كلٌّ برابط أصله، وصفرُ إدخالٍ يدوي.
     */
    private static function collectLines($gate, $partyType, $partyRef, $from, $to)
    {
        $lines = array();
        $mFrom = substr($from, 0, 7);
        $mTo   = substr($to, 0, 7);

        // ── ① دفترُ الطرف: الاستحقاقُ (credit) والتحميلاتُ (debit) ──────────
        //    الفترةُ من `period_ref` حين وُجد (هو الفترةُ المحاسبية)، وإلا فتاريخُ
        //    النشوء — فذمّةُ تموزَ المسجَّلةُ في آبَ تدخل تسويةَ تموز لا آب.
        $dues = $gate->scopedQuery(
            array('scope' => array('d' => 'fin_dues')),
            // M-11: مصدرُ الخصم يُقرأ ويُعرض — «كلُّ رقمٍ في التسوية ينقر إلى مستنده»
            "SELECT d.id, d.due_type, d.direction, d.amount, d.currency, d.period_ref,
                    d.source_doc_type, d.source_doc_id,
                    DATE(d.created_at) AS d_date
               FROM fin_dues d
              WHERE {TENANT_SCOPE}
                AND d.party_type = ? AND d.party_ref = ?
                AND COALESCE(d.is_deleted, 0) = 0
                AND d.settlement_id IS NULL
                AND COALESCE(d.pre_settlement_legacy, 0) = 0
                AND (
                      (d.period_ref IS NOT NULL AND d.period_ref BETWEEN ? AND ?)
                   OR (d.period_ref IS NULL AND DATE(d.created_at) BETWEEN ? AND ?)
                )
              ORDER BY d.id",
            array($partyType, (string) $partyRef, $mFrom, $mTo, $from, $to)
        );
        foreach ($dues as $d) {
            $isCharge = ((string) $d['direction'] === 'debit');
            $lines[] = array(
                'line_kind'   => $isCharge ? 'charge' : 'entitlement',
                'charge_type' => $isCharge ? self::chargeTypeOf((string) $d['due_type']) : null,
                'source_kind' => 'due',
                'source_ref'  => (string) $d['id'],
                // M-11: البيانُ يحمل مستندَه — فيقرأ المستخدمُ من أين جاء الرقم
                'description' => ($isCharge ? 'تحميل: ' : 'استحقاق: ') . $d['due_type']
                                 . (($d['period_ref'] !== null) ? ' — ' . $d['period_ref'] : '')
                                 . ($isCharge ? self::sourceLabel($d['source_doc_type'], $d['source_doc_id']) : ''),
                'work_date'   => (string) $d['d_date'],
                'amount'      => (float) $d['amount'],
                'currency'    => (string) $d['currency'],
            );
        }

        // ── M-21 · تحميلاتُ العامل: أقساطُ سلفه ومدفوعاتُ النيابة عنه ────────
        // الفجوةُ المقيسة كانت هنا حرفيًّا: «التحميلاتُ للمورد فقط» — فتسويةُ
        // العامل تعرض استحقاقَه ولا تعرض ما عليه. وبعد H-09-④ صار للسلفة
        // جدولٌ ورصيدٌ ومستند، فتُقرأ أقساطُها المخصومةُ فعلًا **بمرجعها**.
        // (تُقرأ من `payroll_deductions` لا من السلفة: المخصومُ واقعةٌ جرت،
        //  والرصيدُ نيّةٌ — ولا تُحمَّل نيّةٌ على تسوية.)
        if ($partyType === 'employee') {
            try {
                $ded = $gate->scopedQuery(
                    array('scope' => array('d' => 'payroll_deductions'),
                          'enrich' => array('r' => 'payroll_runs')),
                    "SELECT d.id, d.amount, d.doc_ref, d.source_type, d.source_id, d.note,
                            r.period_to AS d_date
                       FROM payroll_deductions d
                       LEFT JOIN payroll_runs r ON r.id = d.run_id
                      WHERE {TENANT_SCOPE} AND d.person_id = ? AND d.amount > 0
                        AND r.period_to BETWEEN ? AND ?
                      ORDER BY d.id",
                    array((int) $partyRef, $from, $to));
                foreach ($ded as $x) {
                    $lines[] = array(
                        'line_kind'   => 'charge',
                        'charge_type' => 'advance',
                        'source_kind' => 'payroll_deduction',
                        'source_ref'  => (string) $x['id'],
                        'description' => 'قسطُ سلفةٍ من المسيّر — سند ' . $x['doc_ref']
                                         . ' (سلفة #' . (int) $x['source_id'] . ')',
                        'work_date'   => (string) $x['d_date'],
                        'amount'      => (float) $x['amount'],
                        'currency'    => 'SDG',
                    );
                }
            } catch (\Throwable $t) { error_log('settlement employee advances: ' . $t->getMessage()); }
        }

        // التحميلان المباشران للمورد وحده (العاملُ لا تُصرف له قطعٌ ولا أوامرُ صيانة)
        if ($partyType !== 'supplier') { return $lines; }

        // ── M-12 · أقساطُ سلف المورد — «بوابةٌ واحدةٌ … بجدولِ استرداد» ──────
        // البندُ يظهر هنا بمستنده، ولا يُنقص الرصيدَ إلا عند **اعتماد** التسوية:
        // المسودةُ نيّةٌ والمعتمَدةُ واقعة (ENT-02 §3-«المقاصّة الظاهرة»).
        try {
            require_once __DIR__ . '/SupplierAdvanceService.php';
            foreach (SupplierAdvanceService::chargeLines($gate, $partyRef, $from, $to) as $adv) {
                $lines[] = $adv;
            }
        } catch (\Throwable $t) { error_log('settlement supplier advances: ' . $t->getMessage()); }

        // ── ② قطعُ الغيار من الصرف بعمودِ التحميل الصريح ────────────────────
        try {
            $issues = $gate->scopedQuery(
                array('scope' => array('i' => 'proc_issue')),
                "SELECT i.id, i.total_cost, i.currency, DATE(i.created_at) AS d_date
                   FROM proc_issue i
                  WHERE {TENANT_SCOPE}
                    AND i.charge_supplier_id = ?
                    AND COALESCE(i.is_deleted, 0) = 0
                    AND DATE(i.created_at) BETWEEN ? AND ?
                    AND COALESCE(i.total_cost, 0) > 0
                  ORDER BY i.id",
                array($partyRef, $from, $to)
            );
            foreach ($issues as $x) {
                $lines[] = array(
                    'line_kind'   => 'charge',
                    'charge_type' => 'parts',
                    'source_kind' => 'parts',
                    'source_ref'  => (string) $x['id'],
                    'description' => 'قطعُ غيار — صرف #' . $x['id'],
                    'work_date'   => (string) $x['d_date'],
                    'amount'      => (float) $x['total_cost'],
                    'currency'    => ($x['currency'] !== null && $x['currency'] !== '')
                                     ? (string) $x['currency'] : 'SDG',
                );
            }
        } catch (\Throwable $t) { error_log('settlement parts: ' . $t->getMessage()); }

        // ── ③ الصيانة من أمرها بعمودِ التحميل الصريح ────────────────────────
        try {
            $orders = $gate->scopedQuery(
                array('scope' => array('o' => 'mnt_order')),
                "SELECT o.id, o.total_cost, DATE(COALESCE(o.closed_at, o.created_at)) AS d_date
                   FROM mnt_order o
                  WHERE {TENANT_SCOPE}
                    AND o.charge_supplier_id = ?
                    AND COALESCE(o.is_deleted, 0) = 0
                    AND DATE(COALESCE(o.closed_at, o.created_at)) BETWEEN ? AND ?
                    AND COALESCE(o.total_cost, 0) > 0
                  ORDER BY o.id",
                array($partyRef, $from, $to)
            );
            foreach ($orders as $x) {
                $lines[] = array(
                    'line_kind'   => 'charge',
                    'charge_type' => 'maintenance',
                    'source_kind' => 'maintenance',
                    'source_ref'  => (string) $x['id'],
                    'description' => 'صيانة — أمر #' . $x['id'],
                    'work_date'   => (string) $x['d_date'],
                    'amount'      => (float) $x['total_cost'],
                    'currency'    => 'SDG',   // أمرُ الصيانة بلا عمود عملة — عملةُ التشغيل
                );
            }
        } catch (\Throwable $t) { error_log('settlement maintenance: ' . $t->getMessage()); }

        return $lines;
    }

    /**
     * وسمُ المستند المصدر بلغة المهمة (M-11) — «كلُّ رقمٍ ينقر إلى مستنده».
     * والفجوةُ المعلَنةُ تُقال باسمها: صمتُها أخطرُ من إعلانها.
     */
    private static function sourceLabel($type, $id)
    {
        if ($type === null || $type === '') { return ''; }
        $map = array(
            'proc_issue'         => 'سند صرف',
            'mnt_order'          => 'أمر صيانة',
            'transfer_order'     => 'أمر نقل',
            'penalty_assessment' => 'احتساب جزاء',
            'settlement'         => 'تسوية',
        );
        if ($type === 'legacy_no_ref')  { return ' · ⚠ موروثٌ بلا مرجع'; }
        if ($type === 'pending_source') { return ' · ⚠ بلا مصدرٍ مستنديٍّ بعد'; }
        $lbl = isset($map[$type]) ? $map[$type] : $type;
        return ' · ' . $lbl . (intval($id) > 0 ? (' #' . intval($id)) : '');
    }

    /** نوعُ التحميل من نوع الذمّة — وما لا يُعرف يبقى null بلا تخمين. */
    private static function chargeTypeOf($dueType)
    {
        $t = strtolower(trim((string) $dueType));
        return in_array($t, self::CHARGE_TYPES, true) ? $t : null;
    }

    /** اعتراضُ الطرف على بند — بسببٍ إلزامي، والتسويةُ لا تتجمد (§15.3). */
    public static function objectLine($gate, $lineId, $note, $userId)
    {
        $out = array('ok' => false, 'reason' => '', 'code' => 0);
        $note = trim((string) $note);
        if ($note === '') { $out['reason'] = 'سببُ الاعتراض إلزامي'; $out['code'] = 422; return $out; }

        $line = $gate->selectOne('settlement_lines', array('where' => array('id' => intval($lineId))));
        if (!$line) { $out['reason'] = 'البندُ غير موجود'; $out['code'] = 404; return $out; }

        $st = $gate->selectOne('settlements', array('where' => array('id' => intval($line['settlement_id']))));
        if (!$st) { $out['reason'] = 'التسويةُ غير موجودة'; $out['code'] = 404; return $out; }
        if (!in_array((string) $st['state'], array(self::ST_DRAFT, self::ST_REVIEW), true)) {
            $out['reason'] = 'لا اعتراضَ بعد الاعتماد'; $out['code'] = 423; return $out;
        }
        if (intval($line['objected']) === 1) { $out['ok'] = true; return $out; }   // عطالة

        try {
            $gate->runInTransaction(function ($g) use ($line, $note, $userId, $st) {
                $g->update('settlement_lines', array(
                    'objected'       => 1,
                    'objection_note' => $note,
                    'objected_by'    => $userId,
                    'objected_at'    => date('Y-m-d H:i:s'),
                ), array('id' => intval($line['id'])));
                $g->update('settlements', array(
                    'open_objections' => intval($st['open_objections']) + 1,
                ), array('id' => intval($st['id'])));
            }, 'اعتراض بند تسوية');
        } catch (\Throwable $t) {
            error_log('settlement object: ' . $t->getMessage());
            $out['reason'] = 'تعذّر تسجيلُ الاعتراض'; return $out;
        }
        $out['ok'] = true;
        return $out;
    }

    /** حسمُ الاعتراض — يعود البندُ محتسبًا. */
    public static function resolveObjection($gate, $lineId, $userId)
    {
        $out = array('ok' => false, 'reason' => '');
        $line = $gate->selectOne('settlement_lines', array('where' => array('id' => intval($lineId))));
        if (!$line || intval($line['objected']) !== 1) { $out['reason'] = 'لا اعتراضَ مفتوح'; return $out; }
        $st = $gate->selectOne('settlements', array('where' => array('id' => intval($line['settlement_id']))));
        if (!$st) { $out['reason'] = 'التسويةُ غير موجودة'; return $out; }

        try {
            $gate->runInTransaction(function ($g) use ($line, $st) {
                $g->update('settlement_lines', array(
                    'objected' => 0, 'resolved_at' => date('Y-m-d H:i:s'),
                ), array('id' => intval($line['id'])));
                $g->update('settlements', array(
                    'open_objections' => max(0, intval($st['open_objections']) - 1),
                ), array('id' => intval($st['id'])));
            }, 'حسم اعتراض');
        } catch (\Throwable $t) {
            error_log('settlement resolve: ' . $t->getMessage());
            $out['reason'] = 'تعذّر الحسم'; return $out;
        }
        $out['ok'] = true;
        return $out;
    }

    /** رفعُ التسوية للمراجعة — قفلُ نسخة (Draft → Review). */
    public static function submit($gate, $settlementId, $userId)
    {
        $out = array('ok' => false, 'reason' => '', 'code' => 0);
        $st = $gate->selectOne('settlements', array('where' => array('id' => intval($settlementId))));
        if (!$st) { $out['reason'] = 'غير موجودة'; $out['code'] = 404; return $out; }
        if ((string) $st['state'] !== self::ST_DRAFT) {
            $out['reason'] = 'لا تُرفع إلا من مسودة'; $out['code'] = 409; return $out;
        }
        try {
            $gate->update('settlements', array('state' => self::ST_REVIEW),
                          array('id' => intval($settlementId)));
        } catch (\Throwable $t) {
            error_log('settlement submit: ' . $t->getMessage());
            $out['reason'] = 'تعذّر الرفع'; return $out;
        }
        // N-02: تدقيقُ التغيير بقيم قبل/بعد
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof \mysqli) {
            require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
            ems_audit_change($GLOBALS['conn'], 'settlements', 'settlements', 'state_transition',
                intval($settlementId),
                array('state' => (string) $st['state']), array('state' => self::ST_REVIEW),
                array('company_id' => intval($st['company_id']), 'user_id' => intval($userId)));
        }
        $out['ok'] = true;
        return $out;
    }

    /**
     * الاعتماد: حدثُ FES بمفتاح رقمها + طلبُ الدفع آليًّا (أو ذمّةٌ مدينةٌ للسالب).
     * الشروط: من مراجعةٍ · لا اعتراضَ مفتوح (423) · لا اعتمادَ ذاتٍ · كلُّ بندٍ مقيَّم.
     */
    public static function approve($gate, $conn, $settlementId, $userId)
    {
        $out = array('ok' => false, 'reason' => '', 'code' => 0, 'net_direction' => null);
        $sid = intval($settlementId);

        $st = $gate->selectOne('settlements', array('where' => array('id' => $sid)));
        if (!$st) { $out['reason'] = 'غير موجودة'; $out['code'] = 404; return $out; }
        if ((string) $st['state'] !== self::ST_REVIEW) {
            $out['reason'] = 'لا تُعتمد إلا من مراجعة'; $out['code'] = 409; return $out;
        }
        // §15.4 — لا اعتمادَ ذاتٍ، بنيويًّا لا بفحصٍ لاحق
        if (intval($st['prepared_by']) === intval($userId)) {
            $out['reason'] = 'لا يعتمد المرءُ ما أعدّ (فصلُ اليدين)'; $out['code'] = 403; return $out;
        }
        // §15.4 — اعتمادٌ وبندٌ معترَضٌ مفتوح → 423
        if (intval($st['open_objections']) > 0) {
            $out['reason'] = 'يوجد ' . intval($st['open_objections']) . ' بندًا معترَضًا لم يُحسم';
            $out['code']   = 423; return $out;
        }
        // بندٌ بلا معادلٍ موحّد ⇒ الصافي ناقص — لا يُعتمد رقمٌ غيرُ مكتمل
        $un = $gate->scopedQuery(
            array('scope' => array('l' => 'settlement_lines')),
            "SELECT COUNT(*) AS c FROM settlement_lines l
              WHERE {TENANT_SCOPE} AND l.settlement_id = ? AND l.base_amount IS NULL",
            array($sid)
        );
        if ($un && intval($un[0]['c']) > 0) {
            $out['reason'] = 'بنودٌ بلا سعرِ صرفٍ لتاريخها (' . intval($un[0]['c']) .
                             ') — أدخِل السعرَ ثم أعد التوليد';
            $out['code']   = 422; return $out;
        }

        $net       = (float) $st['net_amount'];
        $direction = ($net < 0) ? 'receivable' : 'payable';
        $company   = intval($st['company_id']);
        $no        = (string) $st['settlement_no'];

        require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
        $conn->begin_transaction();
        try {
            \App\Core\EventPublisher::publish($conn, array(
                'event_key'         => 'settlement.approved',
                'category'          => 'financial',
                'source_module'     => 'finance',
                'company_id'        => $company,
                'entity_type'       => 'settlement',
                'entity_id'         => $sid,
                'occurred_at'       => gmdate('Y-m-d H:i:s'),
                'created_by'        => intval($userId) > 0 ? intval($userId) : 1,
                'idempotency_key'   => 'settlement:' . $sid,
                'legacy_event_type' => 'expense',
                'amount'            => abs($net),
                'currency'          => (string) $st['currency'],
                'source_ref'        => $no,
                'supplier_entity_id' => ((string) $st['party_type'] === 'supplier')
                                        ? intval($st['party_ref']) : null,
                'operator_employee_id' => ((string) $st['party_type'] === 'employee')
                                        ? intval($st['party_ref']) : null,
                'notes'             => 'تسويةُ ' . $st['party_name'] . ' — ' . $no,
                'payload'           => array(
                    'settlement_no' => $no,
                    'party_type'    => (string) $st['party_type'],
                    'party_ref'     => intval($st['party_ref']),
                    'period'        => $st['period_from'] . '→' . $st['period_to'],
                    'gross'         => (float) $st['gross_amount'],
                    'charges'       => (float) $st['charges_amount'],
                    'net'           => $net,
                    'direction'     => $direction,
                ),
            ));
            $conn->commit();
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('settlement approve publish #' . $sid . ': ' . $t->getMessage());
            $out['reason'] = 'تعذّر نشرُ حدث التسوية';
            return $out;
        }

        // ── M-12 · الاستردادُ يقع **باعتماد التسوية** لا بتوليدها ────────────
        // المسودةُ نيّةٌ والمعتمَدةُ واقعة — والعطالةُ بمفتاح (سلفة × تسوية).
        if ((string) $st['party_type'] === 'supplier') {
            try {
                require_once __DIR__ . '/SupplierAdvanceService.php';
                SupplierAdvanceService::applyRecoveries($conn, $gate, $company, $sid, $userId);
            } catch (\Throwable $t) {
                error_log('settlement advance recovery #' . $sid . ': ' . $t->getMessage());
            }
        }

        // الصافي السالب ⇒ ذمّةٌ مدينةٌ على الطرف (قرارُ المالك ①)
        $recvId = null;
        if ($direction === 'receivable') {
            try {
                $recvId = $gate->insert('fin_dues', array(
                    'party_type'    => (string) $st['party_type'],
                    'party_ref'     => (string) $st['party_ref'],
                    'due_type'      => 'settlement',
                    'direction'     => 'debit',
                    'amount'        => abs($net),
                    'currency'      => (string) $st['currency'],
                    'fx_rate'       => 1.0,
                    'base_amount'   => abs($net),
                    'period_ref'    => substr((string) $st['period_to'], 0, 7),
                    'settlement_id' => $sid,
                    // M-11: مصدرُ هذا الخصم تسويتُه — والقيدُ البنيويُّ يلزمه
                    'source_doc_type' => 'settlement',
                    'source_doc_id'   => $sid,
                    'created_by'    => $userId,
                ));
            } catch (\Throwable $t) {
                error_log('settlement receivable #' . $sid . ': ' . $t->getMessage());
            }
        }

        // الصافي الموجب ⇒ طلبُ الدفع آليًّا (§15.3 `Approved → PaymentRequested`)
        $reqId = null; $reqNo = null;
        if ($direction === 'payable' && $net > 0) {
            $gen = self::generatePaymentRequest($gate, $st, $net, $userId);
            $reqId = $gen['id']; $reqNo = $gen['no'];
        }

        try {
            $gate->update('settlements', array(
                'state'              => ($reqId !== null) ? self::ST_REQUESTED : self::ST_APPROVED,
                'net_direction'      => $direction,
                'approved_by'        => $userId,
                'approved_at'        => date('Y-m-d H:i:s'),
                'receivable_due_id'  => $recvId,
                'payment_request_id' => $reqId,
            ), array('id' => $sid));
        } catch (\Throwable $t) {
            error_log('settlement approve state #' . $sid . ': ' . $t->getMessage());
            $out['reason'] = 'نُشر الحدثُ وتعذّر تحديثُ الحالة'; return $out;
        }

        // N-02: تدقيقُ الاعتماد بقيم قبل/بعد (الحالةُ والاتجاهُ والمعتمِد)
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'settlements', 'settlements', 'approve', $sid,
            array('state' => (string) $st['state'], 'net_direction' => (string) $st['net_direction']),
            array('state' => ($reqId !== null) ? self::ST_REQUESTED : self::ST_APPROVED,
                  'net_direction' => $direction, 'approved_by' => intval($userId)),
            array('company_id' => $company, 'user_id' => intval($userId)));

        $out['ok'] = true;
        $out['net_direction']      = $direction;
        $out['payment_request_id'] = $reqId;
        $out['payment_request_no'] = $reqNo;
        return $out;
    }

    /**
     * طلبُ الدفع مولَّدًا من التسوية المعتمدة (§15.3 · قرارُ المالك 2026-07-28).
     *
     * **يدخل معتمَدًا جاهزًا للصرف** لا مسودةً ولا في انتظار اعتماد: التسويةُ
     * أُجيزت للتوّ من مديرِ الإدارة المالية، فإعادتُها إلى المحاسب ثم إليه اعتمادٌ
     * مكرَّرٌ للرقم نفسِه — طقسٌ لا فحص. والخزينةُ وحدها هي الخطوةُ الباقية.
     *
     * **صفرُ إدخالٍ يدوي**: المبلغُ والمستفيدُ والعملةُ والمرجعُ كلُّها منسوخةٌ من
     * التسوية — وهذا هو موضعُ الخطر الذي أُغلق: كتابةُ الرقم بيدٍ ثانيةً كانت
     * تفتح بابَ الفارق الصامت.
     *
     * @return array id · no  (كلاهما null عند التعذّر — والتعذّرُ لا يُسقط الاعتماد)
     */
    private static function generatePaymentRequest($gate, $st, $net, $userId)
    {
        $out = array('id' => null, 'no' => null);
        try {
            require_once dirname(dirname(dirname(__DIR__))) . '/FinRequests/_finreq_helpers.php';

            $isSupplier = ((string) $st['party_type'] === 'supplier');
            $no = finreq_next_no($gate);

            $id = $gate->insert('fin_requests', array(
                'request_no'       => $no,
                'request_type'     => $isSupplier ? 'supplier_payment' : 'employee_payment',
                'source_module'    => $isSupplier ? 'suppliers' : 'workforce',
                'source_ref'       => (string) $st['settlement_no'],
                'settlement_id'    => intval($st['id']),      // القيدُ البنيوي يلزمه
                'requester_id'     => $userId,
                'beneficiary_type' => $isSupplier ? 'supplier' : 'employee',
                'beneficiary_ref'  => intval($st['party_ref']),
                'beneficiary_name' => (string) $st['party_name'],
                'amount'           => $net,
                'currency'         => (string) $st['currency'],
                'statement'        => 'صافي تسوية ' . $st['settlement_no'] . ' — ' . $st['party_name'],
                'justification'    => 'مولَّدٌ آليًّا من التسوية المعتمدة للفترة '
                                      . $st['period_from'] . ' → ' . $st['period_to'],
                'state'            => 'approved',   // قرارُ المالك: جاهزٌ للصرف
                'created_by'       => $userId,
            ));

            $out['id'] = intval($id);
            $out['no'] = $no;
        } catch (\Throwable $t) {
            // التعذّرُ يُسجَّل ولا يُسقط اعتمادَ التسوية — الحدثُ نُشر والذمّةُ قُيّدت،
            // ويبقى توليدُ الطلب قابلًا للإعادة من الشاشة.
            error_log('settlement payment request #' . $st['id'] . ': ' . $t->getMessage());
        }
        return $out;
    }

    /** الرقمُ التسلسلي STL-سنة-رقم. */
    private static function nextNo($gate, $conn)
    {
        $year = date('Y');
        $rows = $gate->scopedQuery(
            array('scope' => array('s' => 'settlements')),
            "SELECT COUNT(*) AS c FROM settlements s
              WHERE {TENANT_SCOPE} AND s.settlement_no LIKE ?",
            array('STL-' . $year . '-%')
        );
        $seq = ($rows ? intval($rows[0]['c']) : 0) + 1;
        return 'STL-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** اسمُ الطرف لقطةً — والغيابُ لا يكسر التوليد. */
    private static function partyName($gate, $partyType, $partyRef)
    {
        try {
            if ($partyType === 'supplier') {
                $r = $gate->selectOne('suppliers', array('columns' => array('name'),
                                                         'where' => array('id' => $partyRef)));
                return $r ? (string) $r['name'] : ('مورد #' . $partyRef);
            }
            $r = $gate->selectOne('employees', array('columns' => array('name'),
                                                     'where' => array('id' => $partyRef)));
            return $r ? (string) $r['name'] : ('موظف #' . $partyRef);
        } catch (\Throwable $t) {
            return ($partyType === 'supplier' ? 'مورد #' : 'موظف #') . $partyRef;
        }
    }
}
