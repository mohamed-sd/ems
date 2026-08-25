<?php
namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

use App\Core\EventPublisher;

require_once __DIR__ . '/../../Core/EventPublisher.php';

/**
 * PenaltyService — محرّكُ الجزاء والحافز والحدّ الأدنى (CON-02 §6 · T-08/T-09)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقيس الفارقَ بين **الملتزَم** (`contract_commitments`) و**المنفَّذ** (قيودُ
 * الإيراد في الدفتر لا تقديرٌ)، ويطبّق قاعدةَ العقد (`contract_penalty_rules`)
 * فيولّد **بندًا ظاهرًا** لا خصمًا صامتًا (§6).
 *
 * ── القرارات الحاكمة ─────────────────────────────────────────────────────
 * **ق-9 · نوعان لا أكثر للجزاء** — ولا `formula_json` ولا مفسِّرَ تعابير:
 *   `shortfall_pct` : غرامةُ العجز    = قيمةُ الفارق × rate
 *   `readiness_min` : غرامةُ الجاهزية = قيمةُ الفترة × rate (إن نزلت الجاهزيةُ
 *                     عن `min_readiness_pct`؛ والجاهزيةُ = ساعاتُ العمل ÷
 *                     ساعاتِ الوردية — محسوبةٌ سلفًا من سطور الزمن)
 * ويقابلهما للحافز (ق-10): `bonus_qty_pct` بالنسبة · `bonus_fixed` مبلغًا مقطوعًا
 * بمعيارٍ يدويٍّ معتمد.
 *
 * **ق-11 · الدورية**: لا احتسابَ نسبيًّا. فترةٌ لم تكتمل دوريتُها **تُترك**
 *   والغرامةُ تلحق في مستخلص الإغلاق — الاحتسابُ النسبيُّ يخترع رقمًا لا نصَّ
 *   له في العقد، وقاعدةُ عدم التلفيق تحكم المشروعَ كلَّه.
 *
 * **ق-12 · السقف**: نسبةٌ من **قيمة البند الملتزَم في الفترة** — فالسقفُ
 *   والأساسُ من جنسٍ واحد. ويُحفظ `raw_amount` قبله فيُرى أين قُصَّ الرقم.
 *
 * **ق-15 · اتجاهٌ واحد**: غرامةٌ حين نكون **نحن** المقصّرين فقط. فالبندُ الذي
 *   `obliged_party <> 'company'` **لا يولّد غرامةً هنا إطلاقًا** — تقصيرُ
 *   العميل تعالجه مصفوفةُ §4 فيصير استعدادًا مفوترًا. المحرّكان لا يتقاطعان.
 *
 * **ق-13 · الملكية**: النظامُ يحتسب · 12 يراجع · 19 يُجيز **ويملك الإعفاء
 *   بسببٍ إلزاميٍّ موثَّق**. ولا يعتمد أحدٌ ما راجعه بنفسه.
 *
 * **ق-7 · ق-8 · المسار المالي**: الإجازةُ تنشر قيدًا عبر `EventPublisher`
 *   (الجذرُ المحايد ثم إسقاطُ الدفتر) وتربطه بـ`fin_event_links` — ثم يقرؤه
 *   المستخلصُ كما يقرأ الإيراد. **لا كتابةَ مباشرةً** في `fin_financial_events`
 *   ولا بندَ مستخلصٍ معزول: هذا هو التطابقُ الذي كلّف إصلاحُه ازدواجَ 56,000.
 *
 * **هـ-6**: قيدُ الحد الأدنى بوحدةٍ مميزة `min_guarantee` لا بوحدة الإنتاج.
 */
class PenaltyService
{
    /** وسمُ وحدة قيد الحد الأدنى (هـ-6 · قرارُ المالك 2026-07-28). */
    const MIN_GUARANTEE_UNIT = 'min_guarantee';

    /** مفاتيحُ الأحداث — عائلتان مستقلتان (قرارُ المالك 2026-07-28). */
    const EVT_PENALTY_ASSESSED   = 'penalty.assessed';
    const EVT_PENALTY_APPROVED   = 'penalty.approved';
    const EVT_PENALTY_WAIVED     = 'penalty.waived';
    const EVT_INCENTIVE_ASSESSED = 'incentive.assessed';
    const EVT_INCENTIVE_APPROVED = 'incentive.approved';

    // ═══════════════════════════════════════════════════════════════════════
    // ① الاحتساب
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * يحتسب كلَّ ما تنصّ عليه قواعدُ العقد لفترةٍ بعينها، ويخزّنه `computed`.
     * قابلٌ لإعادة التشغيل: المفتاحُ الفريد يجعل الثانيةَ **تحديثًا** لا تكرارًا،
     * **ما لم** يكن الاحتسابُ قد أُجيز — فالمُجازُ أنتج قيدًا فلا يُعاد حسابه.
     *
     * @return array{ok:bool,computed:int,skipped:array,rows:array}
     */
    public static function assess($conn, $gate, $companyId, $contractId, $from, $to, $actor)
    {
        $contractId = intval($contractId);
        $out = array('ok' => true, 'computed' => 0, 'skipped' => array(), 'rows' => array());

        $ctx = self::contractCtx($gate, $contractId);
        if ($ctx === null) {
            return array('ok' => false, 'computed' => 0, 'rows' => array(),
                         'skipped' => array('العقد غير موجود أو خارج نطاق شركتك'));
        }

        $rules = $gate->scopedQuery(array('scope' => array('r' => 'contract_penalty_rules')),
            "SELECT r.id, r.rule_kind, r.commitment_ref, r.rate, r.min_readiness_pct,
                    r.fixed_amount, r.cap_percent, r.currency, r.periodicity, r.note
               FROM contract_penalty_rules r
              WHERE {TENANT_SCOPE} AND r.is_deleted = 0 AND r.client_contract_id = ?
                AND r.valid_from <= ? AND (r.valid_to IS NULL OR r.valid_to >= ?)
              ORDER BY r.id", array($contractId, $from, $from));

        foreach ($rules as $r) {
            $res = self::computeRule($gate, $companyId, $contractId, $ctx, $r, $from, $to);
            if ($res === null) { continue; }
            if (isset($res['skip'])) { $out['skipped'][] = $res['skip']; continue; }
            $id = self::upsert($gate, $companyId, $contractId, $res, $actor);
            if ($id > 0) { $out['computed']++; $res['id'] = $id; $out['rows'][] = $res; }
        }

        // الحدُّ الأدنى المضمون — مصدرُه `contract_commitments` لا قاعدةَ جزاء (ق-8)
        $mg = self::computeMinGuarantee($gate, $companyId, $contractId, $ctx, $from, $to);
        if ($mg !== null) {
            if (isset($mg['skip'])) { $out['skipped'][] = $mg['skip']; }
            else {
                $id = self::upsert($gate, $companyId, $contractId, $mg, $actor);
                if ($id > 0) { $out['computed']++; $mg['id'] = $id; $out['rows'][] = $mg; }
            }
        }
        return $out;
    }

    /** احتسابُ قاعدةٍ واحدة — قراءةٌ خالصة. */
    private static function computeRule($gate, $companyId, $contractId, array $ctx, array $r, $from, $to)
    {
        $kind = in_array($r['rule_kind'], array('bonus_qty_pct', 'bonus_fixed'), true) ? 'incentive' : 'penalty';
        $label = 'قاعدة #' . $r['id'] . ' (' . $r['rule_kind'] . ')';

        // ── ق-11: لا احتسابَ قبل اكتمال الدورية ──
        if (!self::periodComplete($r['periodicity'], $from, $to, $ctx)) {
            return array('skip' => $label . ': الدورية لم تكتمل في هذه الفترة — يؤجل الاحتساب ولا يحتسب نسبيا (ق-11)');
        }

        $cm = null;
        if (!empty($r['commitment_ref'])) {
            $cm = self::commitment($gate, intval($r['commitment_ref']));
            if ($cm === null) { return array('skip' => $label . ': البند الملتزم المرساة غير موجود'); }
        }

        // ── ق-15: اتجاهٌ واحد — لا غرامةَ إلا حين نكون نحن المقصّرين ──
        if ($kind === 'penalty' && $cm !== null && $cm['obliged_party'] !== 'company') {
            return array('skip' => $label . ': الملتزم «' . $cm['obliged_party']
                . '» لا الشركة — تقصيره تعالجه مصفوفة §4 لا محرك الغرامة (ق-15)');
        }

        $currency = ($r['currency'] !== null && $r['currency'] !== '') ? $r['currency'] : $ctx['currency'];
        $row = array(
            'kind' => $kind, 'rule_id' => intval($r['id']), 'rule_kind' => $r['rule_kind'],
            'commitment_ref' => !empty($r['commitment_ref']) ? intval($r['commitment_ref']) : null,
            'period_from' => $from, 'period_to' => $to, 'periodicity' => $r['periodicity'],
            'currency' => $currency, 'note' => $r['note'],
            'committed_qty' => null, 'actual_qty' => null, 'gap_qty' => null, 'readiness_pct' => null,
            'unit_price' => null, 'base_amount' => 0.0, 'raw_amount' => 0.0,
            'cap_amount' => null, 'amount' => 0.0,
        );

        if ($r['rule_kind'] === 'bonus_fixed') {
            // ق-10: مبلغٌ مقطوعٌ بمعيارٍ يدويٍّ معتمد — لا اشتقاقَ من كمية
            $row['raw_amount'] = round((float) $r['fixed_amount'], 2);
            $row['amount'] = $row['raw_amount'];
            if ($row['amount'] <= 0) { return array('skip' => $label . ': مبلغ مقطوع غير مسجل'); }
            return $row;
        }

        if ($cm === null) { return array('skip' => $label . ': القاعدة بلا بند ملتزم مرساة — لا أساس للقياس'); }

        $price = self::unitPrice($gate, $contractId, $cm['unit_type']);
        if ($price === null || $price <= 0) {
            return array('skip' => $label . ': لا سعر وحدة في العقد لوحدة «' . $cm['unit_type'] . '» — لا تسعير ملفق');
        }
        $committed = self::committedForPeriod((float) $cm['qty'], $cm['period'], $from, $to);
        $actual = self::actualQty($gate, $contractId, $cm['unit_type'], $from, $to);
        $base = round($committed * $price, 2);

        $row['committed_qty'] = round($committed, 4);
        $row['actual_qty'] = round($actual, 4);
        $row['gap_qty'] = round($committed - $actual, 4);
        $row['unit_price'] = $price;
        $row['base_amount'] = $base;

        if ($r['rule_kind'] === 'shortfall_pct') {
            if ($row['gap_qty'] <= 0) { return array('skip' => $label . ': لا عجز في الفترة'); }
            $row['raw_amount'] = round($row['gap_qty'] * $price * ((float) $r['rate'] / 100), 2);

        } elseif ($r['rule_kind'] === 'readiness_min') {
            $rd = self::readiness($gate, $contractId, $from, $to);
            if ($rd === null) { return array('skip' => $label . ': لا ساعات وردية مسجلة فتقاس الجاهزية'); }
            $row['readiness_pct'] = round($rd, 2);
            if ($rd >= (float) $r['min_readiness_pct']) {
                return array('skip' => $label . ': الجاهزية ' . round($rd, 2) . '٪ بلغت العتبة ' . $r['min_readiness_pct'] . '٪');
            }
            $row['raw_amount'] = round($base * ((float) $r['rate'] / 100), 2);

        } elseif ($r['rule_kind'] === 'bonus_qty_pct') {
            if ($row['gap_qty'] >= 0) { return array('skip' => $label . ': لا تجاوز للكمية الملتزمة'); }
            $excess = -$row['gap_qty'];
            $row['raw_amount'] = round($excess * $price * ((float) $r['rate'] / 100), 2);
        }

        // ── ق-12: السقفُ نسبةٌ من قيمة البند الملتزَم في الفترة ──
        $row['amount'] = $row['raw_amount'];
        if ($r['cap_percent'] !== null && (float) $r['cap_percent'] > 0) {
            $cap = round($base * ((float) $r['cap_percent'] / 100), 2);
            $row['cap_amount'] = $cap;
            if ($row['amount'] > $cap) { $row['amount'] = $cap; }
        }
        if ($row['amount'] <= 0) { return array('skip' => $label . ': المبلغ المحتسب صفر'); }
        return $row;
    }

    /**
     * الحدُّ الأدنى المضمون (ق-8): إن نزل المنفَّذُ عن الملتزَم فالفارقُ
     * **قيدُ إيرادٍ مستقلٌّ بنوعه** — لا بندَ تسويةٍ في المستخلص وحدَه،
     * و**بوحدةٍ مميزةٍ** لا بوحدة الإنتاج (هـ-6).
     */
    private static function computeMinGuarantee($gate, $companyId, $contractId, array $ctx, $from, $to)
    {
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.id, c.unit_type, c.qty, c.period, c.obliged_party
               FROM contract_commitments c
              WHERE {TENANT_SCOPE} AND c.is_deleted = 0 AND c.contract_ref = ?
                AND c.commitment_type = 'min_guaranteed' AND c.party_scope = 'client'
              ORDER BY c.id LIMIT 1", array($contractId));
        if (empty($rows)) { return null; }
        $cm = $rows[0];
        $label = 'الحد الأدنى المضمون (بند #' . $cm['id'] . ')';

        if (!self::periodComplete($cm['period'], $from, $to, $ctx)) {
            return array('skip' => $label . ': الدورية لم تكتمل — يؤجل (ق-11)');
        }
        $price = self::unitPrice($gate, $contractId, $cm['unit_type']);
        if ($price === null || $price <= 0) {
            return array('skip' => $label . ': لا سعر وحدة في العقد لوحدة «' . $cm['unit_type'] . '»');
        }
        $committed = self::committedForPeriod((float) $cm['qty'], $cm['period'], $from, $to);
        $actual = self::actualQty($gate, $contractId, $cm['unit_type'], $from, $to);
        $gap = $committed - $actual;
        if ($gap <= 0) { return array('skip' => $label . ': المنفذ بلغ الحد الأدنى فلا فارق يفوتر'); }

        return array(
            'kind' => 'min_guarantee', 'rule_id' => null, 'rule_kind' => 'min_guaranteed',
            'commitment_ref' => intval($cm['id']),
            'period_from' => $from, 'period_to' => $to, 'periodicity' => $cm['period'],
            'currency' => $ctx['currency'],
            'committed_qty' => round($committed, 4), 'actual_qty' => round($actual, 4),
            'gap_qty' => round($gap, 4), 'readiness_pct' => null,
            'unit_price' => $price, 'base_amount' => round($committed * $price, 2),
            'raw_amount' => round($gap * $price, 2), 'cap_amount' => null,
            'amount' => round($gap * $price, 2),
            'note' => 'فارق الحد الأدنى — بند باسمه لا يدس في كميات الوحدات (§5)',
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ② دورةُ الاعتماد (ق-13)
    // ═══════════════════════════════════════════════════════════════════════

    /** مراجعةُ المبيعات (12): `computed → reviewed`. */
    public static function review($gate, $companyId, $id, $actor)
    {
        $a = self::one($gate, $id);
        if (!$a) { return array('ok' => false, 'reason' => 'الاحتساب غير موجود'); }
        if ($a['state'] !== 'computed') { return array('ok' => false, 'reason' => 'لا يراجع إلا المحتسب حديثا'); }
        $gate->update('contract_penalty_assessments', array(
            'state' => 'reviewed', 'reviewed_by' => intval($actor), 'reviewed_at' => date('Y-m-d H:i:s'),
        ), array('id' => intval($id)), 'is_deleted = 0');
        return array('ok' => true, 'reason' => 'روجع وأحيل إلى المالية للإجازة');
    }

    /**
     * إجازةُ المالية (19) — وهنا **يولد القيدُ المالي** (ق-7).
     * ولا يعتمد أحدٌ ما راجعه بنفسه (فصلُ اليدين بنيويًّا لا بالمنح وحدها).
     */
    public static function approve($conn, $gate, $companyId, $id, $actor)
    {
        $a = self::one($gate, $id);
        if (!$a) { return array('ok' => false, 'reason' => 'الاحتساب غير موجود'); }
        if ($a['state'] !== 'reviewed') {
            return array('ok' => false, 'reason' => 'لا يجاز إلا ما راجعته المبيعات أولا (ق-13)');
        }
        if (intval($a['reviewed_by']) === intval($actor)) {
            return array('ok' => false, 'reason' => 'لا اعتماد ذات: من راجع لا يجيز');
        }

        $eventId = null;
        $gate->runInTransaction(function ($g) use ($conn, $a, $actor, $companyId, &$eventId) {
            $eventId = self::postLedger($conn, $g, $companyId, $a, $actor);
            $g->update('contract_penalty_assessments', array(
                'state' => 'posted', 'approved_by' => intval($actor),
                'approved_at' => date('Y-m-d H:i:s'), 'event_id' => $eventId,
            ), array('id' => intval($a['id'])), 'is_deleted = 0');
        }, 'penalty approve');

        return array('ok' => true, 'event_id' => $eventId,
                     'reason' => 'أجيز ونشر قيده — ويظهر بندا في مستخلص الفترة');
    }

    /** الإعفاءُ بيد 19 **بسببٍ إلزاميٍّ موثَّق** (ق-13). */
    public static function waive($conn, $gate, $companyId, $id, $reason, $actor)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { return array('ok' => false, 'reason' => 'سبب الإعفاء إلزامي وموثق (ق-13)'); }
        $a = self::one($gate, $id);
        if (!$a) { return array('ok' => false, 'reason' => 'الاحتساب غير موجود'); }
        if (in_array($a['state'], array('posted', 'waived'), true)) {
            return array('ok' => false, 'reason' => 'لا يعفى ما نشر قيده أو أعفي سلفا');
        }
        $gate->update('contract_penalty_assessments', array(
            'state' => 'waived', 'waive_reason' => mb_substr($reason, 0, 255),
            'approved_by' => intval($actor), 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => intval($id)), 'is_deleted = 0');

        self::emit($conn, $companyId, intval($a['client_contract_id']), self::EVT_PENALTY_WAIVED, $actor,
            array('assessment_id' => intval($id), 'amount' => (float) $a['amount'], 'reason' => $reason));
        return array('ok' => true, 'reason' => 'أعفي بسببه الموثق — ولا يدخل المستخلص');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ③ المسار المالي (ق-7 · ق-8) — عبر الناشر حصرًا
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * ينشر القيدَ ويربطه — فيقرؤه المستخلصُ من `fin_event_links` كما يقرأ الإيراد.
     * **الغرامةُ إيرادٌ سالب** (خصمٌ من مطالبة العميل) لا مصروف: فهي تعديلُ
     * قيمةِ ما نطالب به لا نفقةٌ نُنفقها. والحافزُ والحدُّ الأدنى إيرادٌ موجب.
     */
    private static function postLedger($conn, $gate, $companyId, array $a, $actor)
    {
        $kind = $a['kind'];
        $amount = round((float) $a['amount'], 2);
        $signed = ($kind === 'penalty') ? -$amount : $amount;

        $key = ($kind === 'penalty') ? self::EVT_PENALTY_APPROVED : self::EVT_INCENTIVE_APPROVED;
        $unit = ($kind === 'min_guarantee') ? self::MIN_GUARANTEE_UNIT : $kind;
        $noteMap = array('penalty' => 'غرامة تعاقدية', 'incentive' => 'حافز تعاقدي',
                         'min_guarantee' => 'فارق الحد الأدنى المضمون');

        $res = EventPublisher::publish($conn, array(
            'event_key' => $key,
            'category' => 'financial',
            'source_module' => 'sales',
            'company_id' => intval($companyId),
            'entity_type' => 'contract_penalty_assessment',
            'entity_id' => intval($a['id']),
            'occurred_at' => $a['period_to'] . ' 00:00:00',
            'created_by' => intval($actor) ?: 1,
            'idempotency_key' => 'penalty:assessment:' . intval($a['id']),
            'legacy_event_type' => 'revenue',
            'amount' => $signed,
            'currency' => strval($a['currency']),
            'quantity' => ($a['gap_qty'] !== null) ? abs((float) $a['gap_qty']) : null,
            'unit' => $unit,
            'source_ref' => 'PEN-' . intval($a['id']),
            'contract_id' => intval($a['client_contract_id']),
            'notes' => $noteMap[$kind] . ' — ' . $a['period_from'] . ' → ' . $a['period_to'],
            'payload' => array(
                'assessment_id' => intval($a['id']), 'kind' => $kind, 'rule_kind' => $a['rule_kind'],
                'committed_qty' => $a['committed_qty'], 'actual_qty' => $a['actual_qty'],
                'gap_qty' => $a['gap_qty'], 'unit_price' => $a['unit_price'],
                'base_amount' => $a['base_amount'], 'raw_amount' => $a['raw_amount'],
                'cap_amount' => $a['cap_amount'], 'final_amount' => $amount,
                'readiness_pct' => $a['readiness_pct'],
            ),
        ));
        $eventId = intval($res['id']);

        // الرابطُ هو ما يقرؤه المستخلص — بلا فقدِه يصير القيدُ يتيمًا
        $gate->insert('fin_event_links', array(
            'parent_kind' => 'event',
            'parent_ref' => intval($a['id']),
            'effect_type' => 'revenue_event',
            'target_table' => 'fin_financial_events',
            'target_id' => $eventId,
            'event_id' => $eventId,
        ));

        // ── ق-16: جزاءُ المورد لموردٍ بعينه — يدخل دفترَ الطرف فتلتقطه التسويةُ آليًّا ──
        if ($kind === 'penalty') {
            self::supplierPassthrough($gate, $companyId, $a, $eventId, $amount);
        }
        return $eventId;
    }

    /**
     * تمريرُ جزاء المورد (ق-16) — **لموردٍ بعينه فقط**: حين يكون زمنُ التوقف
     * في الفترة مُسنَدًا إلى موردٍ **واحدٍ محدَّد**. والتوزيعُ النسبيُّ بين
     * موردين **مؤجَّلٌ مع UX-05** (لا جدولَ حاوياتٍ اليوم) — فلا يُخترع توزيع.
     *
     * `fin_dues.due_type` فيه `penalty` و`direction` فيه `debit` سلفًا ⇒ صفرُ هجرة،
     * و`SettlementService` تلتقطه من دفتر الطرف بلا تعديل.
     */
    private static function supplierPassthrough($gate, $companyId, array $a, $eventId, $amount)
    {
        $rows = $gate->scopedQuery(array(
            'scope'  => array('l' => 'unit_time_log'),
            'enrich' => array('e' => 'unit_entries'),
        ), "SELECT DISTINCT l.supplier_entity_id AS sid
              FROM unit_time_log l
              LEFT JOIN unit_entries e ON e.id = l.entry_id
             WHERE {TENANT_SCOPE} AND l.supplier_entity_id IS NOT NULL
               AND l.log_date BETWEEN ? AND ?
               AND e.contract_id = ?
               AND l.supplier_countable = 0",
            array($a['period_from'], $a['period_to'], intval($a['client_contract_id'])));

        if (count($rows) !== 1) { return; }   // صفرٌ أو أكثرُ من مورد ⇒ لا تمرير
        $sid = intval($rows[0]['sid']);
        if ($sid <= 0) { return; }

        $gate->insert('fin_dues', array(
            'party_type' => 'supplier', 'party_ref' => $sid,
            'due_type' => 'penalty', 'direction' => 'debit',
            'amount' => $amount, 'currency' => strval($a['currency']),
            'period_ref' => substr((string) $a['period_from'], 0, 7),
            'event_id' => intval($eventId),
            // M-11: كلُّ خصمٍ ينقر إلى مستنده — والقيدُ البنيويُّ `ck_dues_debit_source`
            // يرفض الإدراجَ بدونه، فلا يمرّ مسارٌ جديدٌ نُسي فيه المصدر.
            'source_doc_type' => 'penalty_assessment',
            'source_doc_id'   => intval($a['id']),
            'settlement_state' => 'pending',
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ④ قراءاتٌ مساعدة
    // ═══════════════════════════════════════════════════════════════════════

    /** بنودُ المستخلص من الاحتسابات المنشورة — يقرؤها `claim_generate`. */
    public static function claimLinesFor($gate, $contractId, $from, $to)
    {
        $rows = $gate->scopedQuery(array('scope' => array('a' => 'contract_penalty_assessments')),
            "SELECT a.id, a.kind, a.amount, a.currency, a.event_id, a.period_to,
                    a.gap_qty, a.unit_price, a.rule_kind
               FROM contract_penalty_assessments a
              WHERE {TENANT_SCOPE} AND a.is_deleted = 0
                AND a.client_contract_id = ? AND a.state = 'posted'
                AND a.period_from >= ? AND a.period_to <= ?
              ORDER BY a.id", array(intval($contractId), $from, $to));

        $out = array();
        foreach ($rows as $r) {
            $amt = round((float) $r['amount'], 2);
            $out[] = array(
                'source_kind'   => strval($r['kind']),          // penalty · incentive · min_guarantee
                'source_ref'    => intval($r['id']),
                'event_id'      => intval($r['event_id']),
                'work_date'     => $r['period_to'],
                'equipment_ref' => '',
                'unit_type'     => ($r['kind'] === 'min_guarantee') ? self::MIN_GUARANTEE_UNIT : strval($r['kind']),
                'qty'           => 1,
                'unit_price'    => ($r['kind'] === 'penalty') ? -$amt : $amt,
                'amount'        => ($r['kind'] === 'penalty') ? -$amt : $amt,
                'currency'      => strval($r['currency']),
            );
        }
        return $out;
    }

    private static function contractCtx($gate, $contractId)
    {
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
            "SELECT c.id, c.price_currency_contract, c.actual_start, c.actual_end,
                    c.daily_work_hours, c.shift_contract, c.equip_shifts_contract
               FROM contracts c WHERE {TENANT_SCOPE} AND c.is_deleted = 0 AND c.id = ? LIMIT 1",
            array(intval($contractId)));
        if (empty($rows)) { return null; }
        $c = $rows[0];
        $map = array('دولار' => 'USD', 'جنيه' => 'SDG', 'USD' => 'USD', 'SDG' => 'SDG');
        $raw = trim((string) $c['price_currency_contract']);
        return array(
            'currency' => isset($map[$raw]) ? $map[$raw] : 'USD',
            'actual_start' => $c['actual_start'], 'actual_end' => $c['actual_end'],
            'daily_work_hours' => (float) $c['daily_work_hours'],
        );
    }

    private static function commitment($gate, $id)
    {
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.id, c.unit_type, c.qty, c.period, c.obliged_party, c.commitment_type
               FROM contract_commitments c WHERE {TENANT_SCOPE} AND c.is_deleted = 0 AND c.id = ? LIMIT 1",
            array(intval($id)));
        return empty($rows) ? null : $rows[0];
    }

    /** سعرُ الوحدة من `contractequipments` — نفسُ مصدر المروحة فلا يفترق الرقمان. */
    private static function unitPrice($gate, $contractId, $unitType)
    {
        $labels = array('hour' => array('ساعة'), 'meter' => array('متر طولي', 'متر'),
                        'ton' => array('طن'), 'trip' => array('نقلة'));
        $want = isset($labels[$unitType]) ? $labels[$unitType] : array();
        $rows = $gate->scopedQuery(array('scope' => array('ce' => 'contractequipments')),
            "SELECT ce.equip_unit, ce.equip_price FROM contractequipments ce
              WHERE {TENANT_SCOPE} AND ce.contract_id = ? ORDER BY ce.id",
            array(intval($contractId)));
        foreach ($rows as $r) {
            if (in_array(trim((string) $r['equip_unit']), $want, true)) { return (float) $r['equip_price']; }
        }
        return null;
    }

    /** المنفَّذُ فعلًا — **من قيود الإيراد** لا من تقديرٍ (نفسُ ما يفوتره المستخلص). */
    private static function actualQty($gate, $contractId, $unitType, $from, $to)
    {
        $rows = $gate->scopedQuery(array('scope' => array('fe' => 'fin_financial_events')),
            "SELECT COALESCE(SUM(fe.quantity),0) q FROM fin_financial_events fe
              WHERE {TENANT_SCOPE} AND fe.is_deleted = 0 AND fe.event_status = 'active'
                AND fe.event_type = 'revenue' AND fe.contract_id = ?
                AND fe.unit = ? AND DATE(fe.occurred_at) BETWEEN ? AND ?",
            array(intval($contractId), strval($unitType), $from, $to));
        return empty($rows) ? 0.0 : (float) $rows[0]['q'];
    }

    /** الجاهزيةُ = ساعاتُ العمل ÷ ساعاتِ الوردية — محسوبةٌ سلفًا من سطور الزمن (ق-9). */
    private static function readiness($gate, $contractId, $from, $to)
    {
        $rows = $gate->scopedQuery(array(
            'scope'  => array('l' => 'unit_time_log'),
            'enrich' => array('e' => 'unit_entries'),
        ), "SELECT COALESCE(SUM(CASE WHEN l.ops_state = 'actual_work' THEN l.hours ELSE 0 END),0) w,
                   COALESCE(SUM(l.hours),0) t
              FROM unit_time_log l
              LEFT JOIN unit_entries e ON e.id = l.entry_id
             WHERE {TENANT_SCOPE} AND l.log_date BETWEEN ? AND ? AND e.contract_id = ?",
            array($from, $to, intval($contractId)));
        if (empty($rows) || (float) $rows[0]['t'] <= 0) { return null; }
        return ((float) $rows[0]['w'] / (float) $rows[0]['t']) * 100;
    }

    /** الكميةُ الملتزَمُ بها في هذه الفترة بحسب دوريتها. */
    private static function committedForPeriod($qty, $period, $from, $to)
    {
        if ($period === 'daily') {
            $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
            return $qty * max(1, $days);
        }
        return $qty; // شهريٌّ أو كاملُ العقد: الرقمُ كما هو للفترة المكتملة
    }

    /**
     * ق-11: هل اكتملت الدوريةُ داخل الفترة؟ فمستخلصُ 20 يومًا مقابلَ التزامٍ
     * شهريٍّ يمضي بالوحدات وحدَها، والغرامةُ تلحق في مستخلص الإغلاق.
     */
    private static function periodComplete($periodicity, $from, $to, array $ctx)
    {
        if ($periodicity === 'daily') { return true; }
        if ($periodicity === 'monthly') {
            // الشهرُ مكتملٌ إن غطّت الفترةُ شهرًا تقويميًّا كاملًا
            return ($from === date('Y-m-01', strtotime($from)))
                && ($to === date('Y-m-t', strtotime($from)));
        }
        // كاملُ العقد: لا يكتمل إلا ببلوغ نهايته داخل الفترة
        $end = $ctx['actual_end'];
        return ($end !== null && $end !== '' && strtotime($end) <= strtotime($to));
    }

    private static function one($gate, $id)
    {
        $rows = $gate->scopedQuery(array('scope' => array('a' => 'contract_penalty_assessments')),
            "SELECT a.* FROM contract_penalty_assessments a
              WHERE {TENANT_SCOPE} AND a.is_deleted = 0 AND a.id = ? LIMIT 1", array(intval($id)));
        return empty($rows) ? null : $rows[0];
    }

    /** إدراجٌ أو تحديث — والمُجازُ/المنشورُ لا يُعاد حسابُه (قيدُه في الدفتر). */
    private static function upsert($gate, $companyId, $contractId, array $row, $actor)
    {
        $existing = $gate->scopedQuery(array('scope' => array('a' => 'contract_penalty_assessments')),
            "SELECT a.id, a.state FROM contract_penalty_assessments a
              WHERE {TENANT_SCOPE} AND a.is_deleted = 0
                AND a.client_contract_id = ? AND a.kind = ?
                AND a.period_from = ? AND a.period_to = ?
                AND a.rule_key = CONCAT(?, ':', ?) LIMIT 1",
            array($contractId, $row['kind'], $row['period_from'], $row['period_to'],
                  $row['rule_id'] === null ? '*' : strval($row['rule_id']),
                  $row['commitment_ref'] === null ? '*' : strval($row['commitment_ref'])));

        $data = array(
            'client_contract_id' => $contractId,
            'rule_id' => $row['rule_id'], 'commitment_ref' => $row['commitment_ref'],
            'kind' => $row['kind'], 'rule_kind' => $row['rule_kind'],
            'period_from' => $row['period_from'], 'period_to' => $row['period_to'],
            'periodicity' => $row['periodicity'],
            'committed_qty' => $row['committed_qty'], 'actual_qty' => $row['actual_qty'],
            'gap_qty' => $row['gap_qty'], 'readiness_pct' => $row['readiness_pct'],
            'unit_price' => $row['unit_price'], 'base_amount' => $row['base_amount'],
            'raw_amount' => $row['raw_amount'], 'cap_amount' => $row['cap_amount'],
            'amount' => $row['amount'], 'currency' => $row['currency'], 'note' => $row['note'],
        );

        if (!empty($existing)) {
            $st = $existing[0]['state'];
            if (in_array($st, array('posted', 'waived'), true)) { return 0; }
            $gate->update('contract_penalty_assessments', $data, array('id' => intval($existing[0]['id'])), 'is_deleted = 0');
            return intval($existing[0]['id']);
        }
        $data['state'] = 'computed';
        $data['created_by'] = intval($actor);
        return intval($gate->insert('contract_penalty_assessments', $data));
    }

    private static function emit($conn, $companyId, $contractId, $key, $actor, array $payload)
    {
        try {
            EventPublisher::publishFact($conn, array(
                'event_key' => $key, 'category' => 'financial', 'source_module' => 'sales',
                'company_id' => intval($companyId), 'entity_type' => 'contract',
                'entity_id' => intval($contractId), 'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => intval($actor) ?: 1,
                'idempotency_key' => $key . ':' . substr(sha1(json_encode($payload)), 0, 16),
                'payload' => $payload,
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'PenaltyService publish');
            error_log('PenaltyService publish ' . $key . ': ' . $t->getMessage());
        }
    }
}
