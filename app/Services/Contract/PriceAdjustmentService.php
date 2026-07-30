<?php
/**
 * app/Services/Contract/PriceAdjustmentService.php — شروطُ تعديل السعر (M-09)
 * ═══════════════════════════════════════════════════════════════════════════
 * CON-02 §2-③: «سعرُ الوحدة لكل بند · العملةُ · **شروطُ تعديل السعر (وقودٌ ·
 * تضخمٌ · صرف)** · الضريبةُ» — الثلاثةُ المسمّاةُ نصًّا لا أكثر.
 * CON-02 §6: «كلُّ تغييرٍ في … **السعر** … **ملحقٌ بسريان** — والوقائعُ السابقة
 * تبقى محكومةً بنصّها النافذ يومَها (**لا رجعية**)».
 *
 * ── العمودُ الفقريُّ للتصميم ────────────────────────────────────────────────
 * `contractequipments.equip_price` **يبقى الأساسَ ولا يُمسّ أبدًا**. التعديلُ
 * طبقةٌ بتاريخها فوقه، فسعرُ الواقعة = آخرُ مراجعةٍ **معتمَدةٍ** سريانُها ≤ يومِها
 * وإلا فالأساس. وبذلك تصير «لا رجعية» **حسابًا** لا وعدًا: تغييرُ اليوم لا يمسّ
 * فاتورةَ أمسِ ولو أُعيد احتسابُها.
 *
 * والامتناعُ يُسجَّل كما يُسجَّل التعديل: `below_threshold` · `capped` ·
 * `no_reading` · `no_base_price` — فالمراجعةُ التي لم تُعدّل تُرى بسببها.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/ContractStateMachine.php';

class PriceAdjustmentService
{
    /** المحفِّزاتُ الثلاثة المسمّاة في §2-③ — مرآةُ ENUM `trigger_kind`. */
    const TRIGGERS = array('fuel', 'inflation', 'fx');

    const PERIODICITIES = array('monthly', 'quarterly', 'semiannual', 'annual');

    /** ملحقُ السعر — النوعُ القائمُ سلفًا في ENUM `contract_amendments`. */
    const AMEND_TYPE = 'تغيير أسعار';

    // ═════════════════════════════════════════════════════════════════════
    // ① الشرطُ — كتابتُه وحراسُه
    // ═════════════════════════════════════════════════════════════════════

    /**
     * حفظُ شرطِ تعديلِ سعرٍ (إنشاءٌ أو تعديل).
     * @return array{ok:bool,code:int,reason:string,term_id:?int}
     */
    public static function saveTerm($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'term_id' => null);
        $contractId = (int) $contractId;
        $termId = isset($args['term_id']) && (int) $args['term_id'] > 0 ? (int) $args['term_id'] : 0;

        $contract = null;
        try { $contract = $gate->selectOne('contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { $contract = null; }
        if (!$contract) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجودٍ في نطاقك'; return $out; }

        $trigger = isset($args['trigger_kind']) ? trim((string) $args['trigger_kind']) : '';
        if (!in_array($trigger, self::TRIGGERS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'محفِّزٌ خارج الثلاثة المسمّاة في §2-③ (وقودٌ · تضخمٌ · صرف)';
            return $out;
        }
        $indexCode = isset($args['index_code']) ? strtoupper(trim((string) $args['index_code'])) : '';
        if ($indexCode === '') {
            $out['code'] = 422; $out['reason'] = 'رمزُ المؤشر إلزامي — «لا رقمَ بلا مرجعٍ يُقرأ منه»'; return $out;
        }
        $base = isset($args['base_index']) ? (float) $args['base_index'] : 0.0;
        if ($base <= 0) { $out['code'] = 422; $out['reason'] = 'القيمةُ المرجعيةُ للمؤشر إلزاميةٌ وموجبة'; return $out; }

        $pass = isset($args['pass_through_percent']) && $args['pass_through_percent'] !== ''
                ? (float) $args['pass_through_percent'] : 100.0;
        if ($pass <= 0 || $pass > 100) {
            $out['code'] = 422; $out['reason'] = 'نسبةُ التمرير بين 0 و100 حصرًا'; return $out;
        }
        $threshold = isset($args['threshold_percent']) && $args['threshold_percent'] !== ''
                     ? (float) $args['threshold_percent'] : 0.0;
        if ($threshold < 0) { $out['code'] = 422; $out['reason'] = 'عتبةُ التفعيل لا تكون سالبة'; return $out; }
        $cap = isset($args['cap_percent']) && $args['cap_percent'] !== '' ? (float) $args['cap_percent'] : null;
        if ($cap !== null && $cap <= 0) { $out['code'] = 422; $out['reason'] = 'السقفُ موجبٌ أو غيرُ مكتوب'; return $out; }

        $periodicity = isset($args['periodicity']) ? trim((string) $args['periodicity']) : 'quarterly';
        if (!in_array($periodicity, self::PERIODICITIES, true)) {
            $out['code'] = 422; $out['reason'] = 'دوريةُ مراجعةٍ غيرُ معروفة'; return $out;
        }
        $from = isset($args['valid_from']) ? trim((string) $args['valid_from']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $out['code'] = 422; $out['reason'] = 'سريانُ الشرط إلزامي — «ملحقٌ بسريان» يبدأ من شرطٍ بسريان'; return $out;
        }

        // **0 = كلُّ بنود العقد** لا NULL: المفتاحُ الفريد في MySQL لا يرى NULL
        // مكررًا، فنطاقُ العقد كان يُكتب مرارًا بلا 409 (گوتشا كشفها الاختبار).
        $itemId = isset($args['contract_item_id']) && (int) $args['contract_item_id'] > 0
                  ? (int) $args['contract_item_id'] : 0;
        if ($itemId > 0) {
            $it = null;
            try { $it = $gate->selectOne('contractequipments', array('where' => array('id' => $itemId))); }
            catch (\Throwable $t) { $it = null; }
            if (!$it || (int) $it['contract_id'] !== $contractId) {
                $out['code'] = 422; $out['reason'] = 'بندُ التسعير لا ينتمي لهذا العقد'; return $out;
            }
        }

        // ── حارسُ «لا تعديلَ مباشرًا على نافذ»: الشرطُ القائمُ في عقدٍ نافذٍ
        //    يُنهى ويُكتب بديلُه بسريانٍ جديد، ولا تُبدَّل معادلتُه بأثرٍ رجعي.
        if ($termId > 0) {
            $cur = null;
            try { $cur = $gate->selectOne('contract_price_terms', array('where' => array('id' => $termId))); }
            catch (\Throwable $t) { $cur = null; }
            if (!$cur || (int) $cur['contract_id'] !== $contractId) {
                $out['code'] = 404; $out['reason'] = 'الشرطُ غيرُ موجودٍ في هذا العقد'; return $out;
            }
            $used = 0;
            try {
                $rows = $gate->scopedQuery(array('scope' => array('r' => 'contract_price_revisions')),
                    "SELECT COUNT(*) n FROM contract_price_revisions r
                      WHERE {TENANT_SCOPE} AND r.term_id = ? AND r.approved_at IS NOT NULL", array($termId));
                $used = $rows ? (int) $rows[0]['n'] : 0;
            } catch (\Throwable $t) { $used = 0; }
            if ($used > 0) {
                $out['code'] = 423;
                $out['reason'] = 'للشرط ' . $used . ' مراجعةً معتمَدةً حرّكت سعرًا — لا تُبدَّل معادلتُه بأثرٍ رجعي: '
                               . 'أنهِه بسريانٍ واكتب بديلَه (CON-02 §6)';
                return $out;
            }
        }

        $data = array(
            'contract_id' => $contractId, 'contract_item_id' => $itemId,
            'trigger_kind' => $trigger, 'index_code' => $indexCode,
            'base_index' => $base,
            'base_date' => isset($args['base_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['base_date'])
                           ? (string) $args['base_date'] : null,
            'threshold_percent' => $threshold, 'pass_through_percent' => $pass, 'cap_percent' => $cap,
            'periodicity' => $periodicity, 'valid_from' => $from,
            'valid_to' => isset($args['valid_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_to'])
                          ? (string) $args['valid_to'] : null,
            'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                      ? mb_substr(trim((string) $args['note']), 0, 255) : null,
        );

        try {
            if ($termId > 0) {
                $gate->update('contract_price_terms', $data, array('id' => $termId));
            } else {
                $data['state'] = 'active';
                $data['created_by'] = (int) $actor ?: null;
                $termId = (int) $gate->insert('contract_price_terms', $data);
            }
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false || strpos($t->getMessage(), 'uq_price_term') !== false) {
                $out['code'] = 409; $out['reason'] = 'للعقد شرطٌ بالمحفِّز والنطاق والسريان نفسِها (UQ)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'contract_price_terms', $termId > 0 ? 'save' : 'create',
                    $termId, array(), $data, $contractId);
        $out['ok'] = true; $out['code'] = 200; $out['term_id'] = $termId;
        return $out;
    }

    /** تسجيلُ قراءة مؤشرٍ — `source_ref` إلزاميٌّ بنيويًّا وخدميًّا معًا. */
    public static function recordIndexReading($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'reading_id' => null);
        $code = isset($args['index_code']) ? strtoupper(trim((string) $args['index_code'])) : '';
        $date = isset($args['reading_date']) ? trim((string) $args['reading_date']) : '';
        $value = isset($args['value']) ? (float) $args['value'] : 0.0;
        $ref = isset($args['source_ref']) ? trim((string) $args['source_ref']) : '';

        if ($code === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $out['code'] = 422; $out['reason'] = 'رمزُ المؤشر وتاريخُ القراءة إلزاميان'; return $out;
        }
        if ($value <= 0) { $out['code'] = 422; $out['reason'] = 'قيمةُ المؤشر موجبة'; return $out; }
        if ($ref === '') {
            $out['code'] = 422;
            $out['reason'] = 'مرجعُ المستند إلزامي — رقمٌ بلا مرجعٍ يحرّك مالًا تلفيقٌ (عقيدة ⑦)';
            return $out;
        }
        try {
            $out['reading_id'] = (int) $gate->insert('contract_price_index_readings', array(
                'index_code' => $code, 'reading_date' => $date, 'value' => $value,
                'source_ref' => mb_substr($ref, 0, 160),
                'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                          ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للمؤشر قراءةٌ بهذا التاريخ سلفًا — القراءةُ واقعةٌ لا تُكرَّر'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التسجيل: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'contract_price_index_readings', 'create',
                    (int) $out['reading_id'], array(), array('index_code' => $code, 'value' => $value, 'ref' => $ref), 0);
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② المؤشر — قراءةٌ من مصدرها الواحد، والغيابُ يُعلَن
    // ═════════════════════════════════════════════════════════════════════

    /**
     * قيمةُ المؤشر السارية بتاريخٍ:
     *  · `fx` ⇒ **`fin_fx_rates`** حصرًا (مصدرُ الحقيقة القائم — لا يُستنسخ).
     *  · `fuel`/`inflation` ⇒ أحدثُ قراءةٍ مسجَّلةٍ **بمرجعها**.
     * والغيابُ **يُعلَن ولا يُخترع له رقم**.
     * @return array{ok:bool,value:?float,source:?string,reason:string}
     */
    public static function resolveIndex(\mysqli $conn, $companyId, $trigger, $indexCode, $asOf)
    {
        $companyId = (int) $companyId;
        $indexCode = strtoupper(trim((string) $indexCode));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) { $asOf = date('Y-m-d'); }

        if ($trigger === 'fx') {
            $st = $conn->prepare("SELECT rate_to_base, effective_from, source FROM fin_fx_rates
                                   WHERE company_id = ? AND currency_code = ?
                                     AND COALESCE(is_deleted,0) = 0 AND effective_from <= ?
                                   ORDER BY effective_from DESC, id DESC LIMIT 1");
            if (!$st) { return array('ok' => false, 'value' => null, 'source' => null, 'reason' => 'تعذّر الاستعلام'); }
            $st->bind_param('iss', $companyId, $indexCode, $asOf);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row || (float) $row['rate_to_base'] <= 0) {
                return array('ok' => false, 'value' => null, 'source' => null,
                             'reason' => 'لا سعرَ صرفٍ ساريًا للعملة «' . $indexCode . '» بتاريخ ' . $asOf
                                       . ' — لا يُخترع سعر');
            }
            return array('ok' => true, 'value' => (float) $row['rate_to_base'],
                         'source' => 'fin_fx_rates@' . $row['effective_from'], 'reason' => '');
        }

        $st = $conn->prepare("SELECT value, reading_date, source_ref FROM contract_price_index_readings
                               WHERE company_id = ? AND index_code = ?
                                 AND COALESCE(is_deleted,0) = 0 AND reading_date <= ?
                               ORDER BY reading_date DESC, id DESC LIMIT 1");
        if (!$st) { return array('ok' => false, 'value' => null, 'source' => null, 'reason' => 'تعذّر الاستعلام'); }
        $st->bind_param('iss', $companyId, $indexCode, $asOf);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return array('ok' => false, 'value' => null, 'source' => null,
                         'reason' => 'لا قراءةَ مسجَّلةً للمؤشر «' . $indexCode . '» حتى ' . $asOf
                                   . ' — تُسجَّل بمرجعها ولا تُخترع');
        }
        return array('ok' => true, 'value' => (float) $row['value'],
                     'source' => $row['source_ref'] . '@' . $row['reading_date'], 'reason' => '');
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ التقييمُ والتوليد
    // ═════════════════════════════════════════════════════════════════════

    /** مفتاحُ الدورة — حتميٌّ من التاريخ والدورية. */
    public static function periodKey($periodicity, $date)
    {
        $ts = strtotime((string) $date);
        $y = (int) date('Y', $ts); $m = (int) date('n', $ts);
        switch ((string) $periodicity) {
            case 'monthly':    return sprintf('%04d-%02d', $y, $m);
            case 'semiannual': return $y . '-H' . ($m <= 6 ? 1 : 2);
            case 'annual':     return (string) $y;
            case 'quarterly':
            default:           return $y . '-Q' . ((int) floor(($m - 1) / 3) + 1);
        }
    }

    /**
     * بدايةُ الدورة **التالية** — سريانُ السعر الجديد.
     * (اجتهادٌ مدوَّن: «دوريةُ المراجعة» تُراجَع فترةً ويسري أثرُها **بعدها** —
     * فلا تُعاد فوترةُ فترةٍ جرت وقائعُها بسعرها القائم يومَها.)
     */
    public static function nextPeriodStart($periodicity, $date)
    {
        $ts = strtotime((string) $date);
        $y = (int) date('Y', $ts); $m = (int) date('n', $ts);
        switch ((string) $periodicity) {
            case 'monthly':    return date('Y-m-d', mktime(0, 0, 0, $m + 1, 1, $y));
            case 'semiannual': return $m <= 6 ? sprintf('%04d-07-01', $y) : sprintf('%04d-01-01', $y + 1);
            case 'annual':     return sprintf('%04d-01-01', $y + 1);
            case 'quarterly':
            default:
                $q = (int) floor(($m - 1) / 3) + 1;
                return $q === 4 ? sprintf('%04d-01-01', $y + 1)
                                : date('Y-m-d', mktime(0, 0, 0, $q * 3 + 1, 1, $y));
        }
    }

    /**
     * تقييمٌ **قراءةٌ خالصة** لشروط عقدٍ بتاريخ مراجعة — بلا كتابةٍ ولا أثر.
     * @return array<int,array> اقتراحٌ لكل (شرط × بند)
     */
    public static function evaluate($conn, $gate, $companyId, $contractId, $asOf)
    {
        $proposals = array();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) { $asOf = date('Y-m-d'); }
        $terms = self::activeTerms($gate, (int) $contractId, $asOf);
        foreach ($terms as $term) {
            $idx = self::resolveIndex($conn, $companyId, $term['trigger_kind'], $term['index_code'], $asOf);
            $items = self::itemsOf($gate, (int) $contractId, $term['contract_item_id']);
            $pkey = self::periodKey($term['periodicity'], $asOf);
            $eff  = self::nextPeriodStart($term['periodicity'], $asOf);

            foreach ($items as $item) {
                $p = array(
                    'term_id' => (int) $term['id'], 'contract_id' => (int) $contractId,
                    'contract_item_id' => (int) $item['id'], 'period_key' => $pkey,
                    'as_of_date' => $asOf, 'effective_from' => $eff,
                    'index_value' => null, 'index_source' => null,
                    'delta_percent' => null, 'applied_percent' => null,
                    'old_price' => null, 'new_price' => null,
                    'outcome' => 'no_reading', 'note' => $idx['reason'],
                );
                if (empty($idx['ok'])) { $proposals[] = $p; continue; }

                $p['index_value'] = round((float) $idx['value'], 8);
                $p['index_source'] = $idx['source'];
                $delta = ((float) $idx['value'] - (float) $term['base_index']) / (float) $term['base_index'] * 100.0;
                $p['delta_percent'] = round($delta, 4);

                // السعرُ القائمُ عشيةَ السريان — يشمل المراجعاتِ المعتمَدةَ السابقة
                $base = (float) $item['equip_price'];
                $old = self::effectivePrice($conn, $companyId, (int) $contractId, (int) $item['id'],
                                            date('Y-m-d', strtotime($eff . ' -1 day')), $base);
                if ($old <= 0) {
                    $p['outcome'] = 'no_base_price';
                    $p['note'] = 'البندُ بلا سعرٍ أساسٍ موجب — لا نسبةَ تُطبَّق على صفر';
                    $proposals[] = $p; continue;
                }
                $p['old_price'] = round($old, 4);

                if (abs($delta) < (float) $term['threshold_percent']) {
                    $p['outcome'] = 'below_threshold';
                    $p['applied_percent'] = 0.0;
                    $p['new_price'] = round($old, 4);
                    $p['note'] = 'فارقُ المؤشر ' . round($delta, 4) . '٪ دون عتبة التفعيل '
                               . (float) $term['threshold_percent'] . '٪ — لا تعديل';
                    $proposals[] = $p; continue;
                }

                $applied = $delta * ((float) $term['pass_through_percent'] / 100.0);
                $capped = false;
                if ($term['cap_percent'] !== null && abs($applied) > (float) $term['cap_percent']) {
                    $applied = ($applied < 0 ? -1 : 1) * (float) $term['cap_percent'];
                    $capped = true;
                }
                $p['applied_percent'] = round($applied, 4);
                $p['new_price'] = round($old * (1 + $applied / 100.0), 4);
                $p['outcome'] = $capped ? 'capped' : 'amended';
                $p['note'] = 'مؤشرٌ ' . round($delta, 4) . '٪ × تمرير ' . (float) $term['pass_through_percent'] . '٪'
                           . ($capped ? (' — مقصوصٌ بسقف ' . (float) $term['cap_percent'] . '٪') : '')
                           . ' ⇒ ' . round($applied, 4) . '٪';
                $proposals[] = $p;
            }
        }
        return $proposals;
    }

    /**
     * توليدُ المراجعات المستحقة **ذريًّا** — ومعها ملحقُ «تغيير أسعار» بسريانه.
     *
     * ولا يُمسّ `contractequipments.equip_price` أبدًا: الملحقُ والمراجعةُ هما
     * المستندُ، والسعرُ الفعليُّ يُشتقّ بـ`effectivePrice` بعد الاعتماد.
     * العطالةُ في المنبع: `UQ(term, period, item)` — إعادةُ التشغيل لا تُكرِّر.
     *
     * @return array{ok:bool,created:int,skipped:int,rows:array}
     */
    public static function applyDue($conn, $gate, $companyId, $contractId, $asOf, $actor)
    {
        $out = array('ok' => true, 'created' => 0, 'skipped' => 0, 'rows' => array());
        $proposals = self::evaluate($conn, $gate, $companyId, $contractId, $asOf);
        foreach ($proposals as $p) {
            $exists = array();
            try {
                $exists = $gate->scopedQuery(array('scope' => array('r' => 'contract_price_revisions')),
                    "SELECT r.id FROM contract_price_revisions r
                      WHERE {TENANT_SCOPE} AND r.term_id = ? AND r.period_key = ? AND r.contract_item_id = ?",
                    array($p['term_id'], $p['period_key'], $p['contract_item_id']));
            } catch (\Throwable $t) { $exists = array(); }
            if ($exists) {
                $out['skipped']++;
                $out['rows'][] = array('revision_id' => (int) $exists[0]['id'], 'outcome' => 'idempotent');
                continue;
            }

            $revId = null; $amdId = null;
            try {
                $gate->runInTransaction(function ($g) use ($conn, $p, $contractId, $companyId, $actor, &$revId, &$amdId) {
                    // الملحقُ لا يُولَّد إلا حين يتحرك سعرٌ فعلًا — والامتناعُ
                    // يُسجَّل مراجعةً بسببه بلا ملحقٍ فارغ.
                    if (in_array($p['outcome'], array('amended', 'capped'), true)) {
                        $amdId = (int) $g->insert('contract_amendments', array(
                            'amendment_code' => self::nextAmendmentCode($conn, (int) $companyId),
                            'contract_id'    => $contractId,
                            'amend_type'     => self::AMEND_TYPE,
                            'amend_date'     => $p['as_of_date'],
                            'effective_from' => $p['effective_from'],   // ← «ملحقٌ بسريان» (§6)
                            'requested_by'   => (int) $actor ?: null,
                            'reason'         => mb_substr('تعديلُ سعرٍ بشرط العقد — ' . $p['note'], 0, 500),
                            'old_value'      => (string) $p['old_price'],
                            'new_value'      => (string) $p['new_price'],
                            'effect_price'   => round((float) $p['new_price'] - (float) $p['old_price'], 2),
                            'effect_summary' => 'بندُ التسعير #' . $p['contract_item_id'] . ': '
                                              . $p['old_price'] . ' ⇐ ' . $p['new_price']
                                              . ' من ' . $p['effective_from'],
                            'created_by'     => (int) $actor ?: null,
                        ));
                    }
                    $revId = (int) $g->insert('contract_price_revisions', array(
                        'term_id' => $p['term_id'], 'contract_id' => $contractId,
                        'contract_item_id' => $p['contract_item_id'], 'period_key' => $p['period_key'],
                        'as_of_date' => $p['as_of_date'], 'effective_from' => $p['effective_from'],
                        'index_value' => $p['index_value'], 'index_source' => $p['index_source'],
                        'delta_percent' => $p['delta_percent'], 'applied_percent' => $p['applied_percent'],
                        'old_price' => $p['old_price'], 'new_price' => $p['new_price'],
                        'outcome' => $p['outcome'], 'amendment_id' => $amdId,
                        'note' => mb_substr((string) $p['note'], 0, 255),
                        'created_by' => (int) $actor ?: null,
                    ));
                    return true;
                }, 'M-09 price revision term#' . $p['term_id']);
            } catch (\Throwable $t) {
                $out['ok'] = false;
                $out['rows'][] = array('revision_id' => null, 'outcome' => 'error', 'reason' => $t->getMessage());
                continue;
            }
            $out['created']++;
            $out['rows'][] = array('revision_id' => $revId, 'amendment_id' => $amdId, 'outcome' => $p['outcome']);
            self::audit($conn, $companyId, $actor, 'contract_price_revisions', 'generate', (int) $revId,
                        array('price' => $p['old_price']), array('price' => $p['new_price'],
                        'outcome' => $p['outcome'], 'effective_from' => $p['effective_from']), $contractId);
        }
        return $out;
    }

    /**
     * اعتمادُ مراجعة — **«من أنشأ لا يعتمد»** (403) والمعتمَدةُ وحدَها تصير مالًا.
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function approve($conn, $gate, $companyId, $revisionId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $rev = null;
        try { $rev = $gate->selectOne('contract_price_revisions', array('where' => array('id' => (int) $revisionId))); }
        catch (\Throwable $t) { $rev = null; }
        if (!$rev) { $out['code'] = 404; $out['reason'] = 'المراجعةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ($rev['approved_at'] !== null) { $out['code'] = 422; $out['reason'] = 'معتمَدةٌ سلفًا'; return $out; }
        if (!in_array((string) $rev['outcome'], array('amended', 'capped'), true)) {
            $out['code'] = 422;
            $out['reason'] = 'مراجعةٌ لم تحرّك سعرًا (' . $rev['outcome'] . ') — لا شيءَ يُعتمد';
            return $out;
        }
        if ((int) $rev['created_by'] > 0 && (int) $rev['created_by'] === (int) $actor) {
            $out['code'] = 403; $out['reason'] = 'من أنشأ لا يعتمد — الفصلُ بنيويٌّ لا اختياري'; return $out;
        }
        try {
            $gate->update('contract_price_revisions',
                array('approved_by' => (int) $actor ?: null, 'approved_at' => date('Y-m-d H:i:s')),
                array('id' => (int) $revisionId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الاعتماد: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'contract_price_revisions', 'approve', (int) $revisionId,
                    array('approved_at' => null), array('approved_at' => date('Y-m-d H:i:s')),
                    (int) $rev['contract_id']);
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ الوصلُ الحي — سعرُ الواقعة بتاريخها
    // ═════════════════════════════════════════════════════════════════════

    /**
     * سعرُ بندٍ **بتاريخ الواقعة** = آخرُ مراجعةٍ معتمَدةٍ سريانُها ≤ التاريخ،
     * وإلا فالأساسُ كما هو. **هذه هي «لا رجعية» حسابًا**: واقعةُ أمسِ تُسعَّر
     * بسعرِ أمسِ ولو أُعيد احتسابُها اليوم بعد تعديلٍ نافذٍ من الغد.
     *
     * (يُقرأ بـ`mysqli` وشركةٍ صريحةٍ لأن مستهلكَه `EffectFanout` بلا بوابة.)
     */
    public static function effectivePrice(\mysqli $conn, $companyId, $contractId, $itemId, $date, $basePrice)
    {
        $basePrice = (float) $basePrice;
        $itemId = (int) $itemId;
        if ($itemId <= 0 || (int) $companyId <= 0) { return $basePrice; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) { return $basePrice; }

        $st = $conn->prepare("SELECT new_price FROM contract_price_revisions
                               WHERE company_id = ? AND contract_id = ? AND contract_item_id = ?
                                 AND approved_at IS NOT NULL AND new_price IS NOT NULL
                                 AND outcome IN ('amended','capped')
                                 AND effective_from <= ?
                               ORDER BY effective_from DESC, id DESC LIMIT 1");
        if (!$st) { return $basePrice; }
        $co = (int) $companyId; $cid = (int) $contractId; $d = (string) $date;
        $st->bind_param('iiis', $co, $cid, $itemId, $d);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row || (float) $row['new_price'] <= 0) { return $basePrice; }
        return (float) $row['new_price'];
    }

    /** علمُ التشغيل الدوري — قائمةُ عقودٍ لا ثنائي (N-04 §2-③). */
    public static function autoAdjustEnabledFor($contractId)
    {
        $raw = function_exists('ems_env') ? trim((string) ems_env('EMS_PRICE_ADJUST', '')) : '';
        if ($raw === '') { return false; }
        foreach (explode(',', $raw) as $c) {
            if ((int) trim($c) === (int) $contractId && (int) $contractId > 0) { return true; }
        }
        return false;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ⑤ قراءاتٌ ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    /** شروطُ عقدٍ الساريةُ بتاريخ. */
    public static function activeTerms($gate, $contractId, $asOf)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('t' => 'contract_price_terms')),
                "SELECT t.* FROM contract_price_terms t
                  WHERE {TENANT_SCOPE} AND t.contract_id = ? AND COALESCE(t.is_deleted,0)=0
                    AND t.state = 'active' AND t.valid_from <= ?
                    AND (t.valid_to IS NULL OR t.valid_to >= ?)
                  ORDER BY t.id", array((int) $contractId, (string) $asOf, (string) $asOf));
        } catch (\Throwable $t) { return array(); }
    }

    /** بنودُ التسعير المشمولةُ بشرطٍ (بندٌ بعينه أو كلُّ بنود العقد). */
    public static function itemsOf($gate, $contractId, $itemId)
    {
        try {
            $where = ($itemId !== null && (int) $itemId > 0) ? ' AND ce.id = ?' : '';
            $params = array((int) $contractId);
            if ($where !== '') { $params[] = (int) $itemId; }
            return $gate->scopedQuery(array('scope' => array('ce' => 'contractequipments')),
                "SELECT ce.id, ce.equip_price, ce.equip_unit FROM contractequipments ce
                  WHERE {TENANT_SCOPE} AND ce.contract_id = ?" . $where . " ORDER BY ce.id", $params);
        } catch (\Throwable $t) { return array(); }
    }

    /** مراجعاتُ عقدٍ للعرض. */
    public static function revisionsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('r' => 'contract_price_revisions')),
                "SELECT r.* FROM contract_price_revisions r
                  WHERE {TENANT_SCOPE} AND r.contract_id = ?
                  ORDER BY r.effective_from DESC, r.id DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    /** الكودُ التالي AMD-NNNN بشركة العقد — مرآةُ مولّد M-10 نفسِه. */
    private static function nextAmendmentCode($conn, $companyId)
    {
        $next = 1;
        if ($stmt = $conn->prepare("SELECT amendment_code FROM contract_amendments
                WHERE company_id = ? AND amendment_code REGEXP '^AMD-[0-9]+$' AND is_deleted = 0
                ORDER BY CAST(SUBSTRING(amendment_code, 5) AS UNSIGNED) DESC LIMIT 1")) {
            $stmt->bind_param('i', $companyId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $next = intval(substr($row['amendment_code'], 4)) + 1;
                }
            }
            $stmt->close();
        }
        return 'AMD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /** تدقيقُ N-02 — لا يرمي. */
    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after, $contractId)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor,
                  'contract_id' => (int) $contractId));
    }
}
