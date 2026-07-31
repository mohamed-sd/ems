<?php
/**
 * app/Services/Contract/ContractPaymentScheduleService.php — خطةُ الدفع (P-05)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §3.5 (الملحق §3-`P-05`): «**خطةُ الدفع** بأنماطها الثمانية +
 * **أنواعُ المقدم الأربعة** — **توليدٌ آليٌّ من الرأس والجدول**».
 * و§3.5: «تُولَّد آليًّا من رأس العقد والجدول الشهري، وتُعدَّل يدويًّا للمعالم
 * والدفعات الخاصة — **ولا تُدخل كلُّها يدويًّا**».
 *
 * ── لماذا جدولٌ والنظامُ يعرف ما قُبض وما فُوتر ─────────────────────────────
 * لأنه **لا يعرف ما استُحقّ**. `contract_advances` تقول ما دخل، و`claims`
 * تقول ما فُوتر — **ولا موضعَ يقول «دفعةٌ مستحقةٌ يومَ كذا»**. فلا توقُّعَ
 * ولا «متأخر»، والتأخرُ يُكتشف بالمصادفة. وشروطُ السداد في الرأس **نصٌّ حرّ**
 * (`payment_time` VARCHAR) و**تاريخٌ واحدٌ للعقد كلِّه** (`payment_date`).
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **أنواعُ المقدم الأربعة لا تُخلط** — والمعالجةُ **محكومةٌ بالنوع** لا
 *    بالاختيار: المستهلَكُ **التزام**، والحجزُ والمعلَمُ **إيراد**، والتعبئةُ
 *    **بنص العقد فتُعلَن ولا تُفترض** (PLAN-03 §6: «الخلطُ بينها يقلب التزامًا
 *    إلى إيرادٍ أو العكس»).
 * ② **ودفترُ السلف للالتزام وحدَه** — فقبضُ سطرٍ إيراديٍّ **لا يدخل
 *    `contract_advances`** ولا يُستقطع من مستخلص. ولا مصدرَ ثانيًا للرقم:
 *    الالتزامُ يُقبض بـ`advance_record` القائمة (M-01) نفسِها.
 * ③ **والمقبوضُ لا يتجاوز المتوقَّع** — والزائدُ **يُعلَن ولا يُبتلع** (409)،
 *    وبيتُه قناةُ التخصيص (`P-07`) لا هذا الجدول.
 * ④ **والملحقُ يفتح نسخةً لا يعدّل** — والقديمةُ تُختم بـ`effective_to`
 *    وتبقى (اختبارُ §9-⑰: «نسخةٌ جديدةٌ والقديمةُ محفوظة»).
 */

namespace App\Services\Contract;

require_once __DIR__ . '/ContractMonthlyPlanService.php';

class ContractPaymentScheduleService
{
    /** الأنماطُ الثمانية — PLAN-03 §3.5 حرفًا. */
    const PATTERNS = array(
        'single_payment', 'advance_then_monthly', 'partial_advance', 'advance_installments',
        'milestone_payments', 'monthly_claim', 'final_payment', 'retention_release',
    );

    const PATTERN_AR = array(
        'single_payment' => 'دفعةٌ واحدة',
        'advance_then_monthly' => 'مقدمٌ ثم تسوياتٌ شهرية',
        'partial_advance' => 'مقدمٌ جزئي',
        'advance_installments' => 'مقدمٌ على دفعات',
        'milestone_payments' => 'دفعاتٌ عند معالمَ',
        'monthly_claim' => 'مستخلصٌ شهري',
        'final_payment' => 'دفعةٌ ختامية',
        'retention_release' => 'ردُّ محتجز الضمان',
    );

    const KINDS = array('advance', 'monthly_settlement', 'milestone', 'final',
                        'retention_release', 'single');

    const KIND_AR = array(
        'advance' => 'مقدَّم', 'monthly_settlement' => 'تسويةٌ شهرية',
        'milestone' => 'معلَم', 'final' => 'ختامية',
        'retention_release' => 'ردُّ محتجز', 'single' => 'دفعةٌ واحدة',
    );

    /** أنواعُ المقدم الأربعة — PLAN-03 §3.1. */
    const ADVANCE_TYPES = array('recoverable', 'mobilization',
                                'non_refundable_booking', 'milestone_earned');

    const ADVANCE_AR = array(
        'recoverable' => 'مقدمٌ على حساب المستخلصات (يُستهلك أو يُرد)',
        'mobilization' => 'رسومُ تعبئة',
        'non_refundable_booking' => 'رسومُ حجزٍ غيرُ قابلةٍ للرد',
        'milestone_earned' => 'دفعةُ معلَمٍ مكتمل',
    );

    /**
     * **المعالجةُ محكومةٌ بالنوع** — والتعبئةُ وحدَها `null` أي «بنص العقد».
     * وهذا هو مانعُ قلبِ الالتزام إيرادًا: لا يُختار إلا حيث نصَّ العقدُ فعلًا.
     */
    const TREATMENT_OF = array(
        'recoverable' => 'liability',
        'non_refundable_booking' => 'revenue',
        'milestone_earned' => 'revenue',
        'mobilization' => null,
    );

    const STATES = array('not_due', 'due', 'partial', 'completed', 'overdue');

    const STATE_AR = array(
        'not_due' => 'غيرُ مستحق', 'due' => 'مستحق', 'partial' => 'جزئي',
        'completed' => 'مكتمل', 'overdue' => 'متأخر',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① التوليدُ الآلي — من الرأس والجدول
    // ═════════════════════════════════════════════════════════════════════

    /**
     * توليدُ خطةِ دفعٍ من رأس العقد والجدول الشهري (P-03).
     *
     * @param array $opt {pattern, advance:{type,basis,percent,amount,due_date,
     *                    treatment,treatment_basis}, retention:{condition},
     *                    final:{percent,condition}}
     * @return array{ok:bool,code:int,reason:string,version:int,rows:int,expected:float,currency:string}
     */
    public static function generate($conn, $gate, $companyId, $contractId, array $opt, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'version' => 0,
                     'rows' => 0, 'expected' => 0.0, 'currency' => '');

        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غيرُ موجودٍ في نطاقك'; return $out; }

        if (self::liveRows($gate, (int) $contractId)) {
            $out['code'] = 409;
            $out['reason'] = '**للعقد خطةُ دفعٍ نافذة** — والتغييرُ **بنسخةٍ جديدة** (`newVersion`) '
                           . 'لا بتوليدٍ فوقها؛ فالقديمةُ محفوظة';
            return $out;
        }

        $pattern = (string) (isset($opt['pattern']) ? $opt['pattern'] : 'monthly_claim');
        if (!in_array($pattern, self::PATTERNS, true)) {
            $out['code'] = 422; $out['reason'] = 'نمطُ دفعٍ غيرُ معروف: ' . $pattern; return $out;
        }

        $from = substr((string) $c['actual_start'], 0, 7);
        $to   = substr((string) $c['actual_end'], 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}$/', $to)) {
            $out['code'] = 422;
            $out['reason'] = '**مدةُ العقد غيرُ محدَّدة** — ولا جدولَ دفعٍ بلا بدايةٍ ونهاية'; return $out;
        }

        // ── الجدولُ الشهري (P-03) هو مصدرُ التسويات — لا تقديرَ ولا قسمةٌ ────
        $pv = ContractMonthlyPlanService::periodValue($gate, (int) $contractId, $from, $to);
        if (count($pv['by_currency']) > 1) {
            $out['code'] = 422;
            $out['reason'] = '**العقدُ بعملتين أو أكثر** (' . implode(' · ', array_keys($pv['by_currency']))
                . ') — ولا تُجمع عملتان في سطرِ دفع';
            return $out;
        }
        if (!$pv['months']) {
            $out['code'] = 422;
            $out['reason'] = '**لا جدولَ شهريًّا نافذًا للعقد** — و`P-05` تُولَّد منه لا من التخمين';
            return $out;
        }
        $cur = (string) array_keys($pv['by_currency'])[0];
        $total = round((float) $pv['by_currency'][$cur], 2);

        // ── بناءُ الأسطر في الذاكرة ثم كتابتُها معاملةً واحدة ────────────────
        $rows = array(); $seq = 0;

        // ① المقدَّم — إن طُلب
        $adv = isset($opt['advance']) && is_array($opt['advance']) ? $opt['advance'] : null;
        if ($adv !== null) {
            $built = self::buildAdvanceRow($adv, $total, $cur, (string) $c['actual_start']);
            if (!$built['ok']) { return array_merge($out, $built); }
            $built['row']['seq'] = ++$seq;
            $built['row']['pattern'] = $pattern;
            $rows[] = $built['row'];
        }

        // ② التسوياتُ الشهرية — شهرًا شهرًا من الجدول
        $payDays = max(0, (int) (isset($opt['due_days']) ? $opt['due_days'] : 30));
        foreach ($pv['months'] as $mm => $byCur) {
            $amt = round((float) (isset($byCur[$cur]) ? $byCur[$cur] : 0), 2);
            if ($amt <= 0) { continue; }   // شهرُ توقفٍ لا دفعةَ له — والصفرُ لا يُطالَب به
            $due = date('Y-m-d', strtotime($mm . '-01 +1 month +' . $payDays . ' days'));
            $rows[] = array(
                'seq' => ++$seq, 'pattern' => $pattern, 'payment_kind' => 'monthly_settlement',
                'amount_basis' => 'fixed', 'amount_expected' => $amt, 'currency' => $cur,
                'due_date' => $due, 'period_month' => $mm, 'source' => 'generated',
                'note' => 'تسويةُ ' . $mm . ' — مهلةُ ' . $payDays . ' يومًا بعد انقضاء الشهر',
            );
        }

        // ③ ردُّ محتجز الضمان — إن كان للعقد احتجاز
        $retPct = round((float) $c['retention_pct'], 2);
        if ($retPct > 0) {
            $cond = trim((string) (isset($opt['retention']['condition'])
                        ? $opt['retention']['condition'] : ''));
            if ($cond === '') { $cond = 'بعد انقضاء فترة الضمان وقبولِ الأعمال نهائيًّا'; }
            $rows[] = array(
                'seq' => ++$seq, 'pattern' => $pattern, 'payment_kind' => 'retention_release',
                'amount_basis' => 'percent', 'percent_value' => $retPct,
                'amount_expected' => round($total * $retPct / 100, 2), 'currency' => $cur,
                'due_date' => null, 'due_condition' => $cond, 'source' => 'generated',
                'note' => 'ردُّ محتجزٍ ' . $retPct . '% — **شرطٌ لا تاريخ**',
            );
        }

        if (!$rows) { $out['code'] = 422; $out['reason'] = 'لم يُنتج التوليدُ سطرًا واحدًا'; return $out; }

        $eff = self::dateOrNull(isset($opt['effective_from']) ? $opt['effective_from'] : null);
        if ($eff === null) { $eff = (string) $c['actual_start']; }

        try {
            $gate->runInTransaction(function ($g) use ($rows, $contractId, $eff, $actor) {
                foreach ($rows as $r) {
                    $r['contract_id'] = (int) $contractId;
                    $r['version'] = 1;
                    $r['effective_from'] = $eff;
                    $r['created_by'] = (int) $actor ?: null;
                    $g->insert('contract_payment_schedule', $r);
                }
            }, 'توليد خطة دفع للعقد ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التوليد: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'generate_schedule', (int) $contractId, array(),
            array('pattern' => $pattern, 'rows' => count($rows)));

        $exp = 0.0;
        foreach ($rows as $r) { $exp = round($exp + (float) $r['amount_expected'], 2); }
        $out['ok'] = true; $out['code'] = 200; $out['version'] = 1;
        $out['rows'] = count($rows); $out['expected'] = $exp; $out['currency'] = $cur;
        $out['reason'] = 'وُلّدت خطةُ «' . self::PATTERN_AR[$pattern] . '»: ' . count($rows)
            . ' سطرًا بمتوقَّعٍ ' . $exp . ' ' . $cur
            . ' · **وΣ الخطة ليست قيمةَ العقد**: المقدمُ يُستهلك من المستخلصات فلا يُجمع معها';
        return $out;
    }

    /** بناءُ سطر المقدَّم بنوعه ومعالجته — **والمعالجةُ محكومةٌ بالنوع**. */
    private static function buildAdvanceRow(array $adv, $total, $cur, $startDate)
    {
        $type = (string) (isset($adv['type']) ? $adv['type'] : '');
        if (!in_array($type, self::ADVANCE_TYPES, true)) {
            return array('ok' => false, 'code' => 422,
                'reason' => '**نوعُ المقدم إلزاميٌّ وواحدٌ من الأربعة** — ولا مقدمَ بلا نوع');
        }
        $fixed = self::TREATMENT_OF[$type];
        $treatment = (string) (isset($adv['treatment']) ? $adv['treatment'] : '');
        $basis = trim((string) (isset($adv['treatment_basis']) ? $adv['treatment_basis'] : ''));

        if ($fixed !== null) {
            // ثلاثةُ أنواعٍ تحسمها المحاسبةُ لا الاختيار
            if ($treatment !== '' && $treatment !== $fixed) {
                return array('ok' => false, 'code' => 422,
                    'reason' => '**«' . self::ADVANCE_AR[$type] . '» معالجتُه ' . $fixed
                        . ' حتمًا** — ولا يُقلَب بالاختيار (PLAN-03 §6)');
            }
            $treatment = $fixed;
            $basis = ($basis === '') ? null : mb_substr($basis, 0, 255);
        } else {
            // التعبئةُ وحدَها **بنص العقد** — فتُعلَن ولا تُفترض
            if ($treatment !== 'liability' && $treatment !== 'revenue') {
                return array('ok' => false, 'code' => 422,
                    'reason' => '**رسومُ التعبئة قد تكون دَينًا أو إيرادًا بحسب نص العقد** — '
                        . 'فالمعالجةُ تُعلَن صراحةً (`liability` أو `revenue`) ولا تُفترض');
            }
            if ($basis === '') {
                return array('ok' => false, 'code' => 422,
                    'reason' => '**نصُّ العقد الذي حكم معالجةَ التعبئة إلزامي** — '
                        . 'ولا معالجةَ بلا سندٍ من العقد');
            }
            $basis = mb_substr($basis, 0, 255);
        }

        $basisKind = (string) (isset($adv['basis']) ? $adv['basis'] : 'percent');
        $pct = null; $amount = 0.0;
        if ($basisKind === 'percent') {
            $pct = round((float) (isset($adv['percent']) ? $adv['percent'] : 0), 3);
            if ($pct <= 0 || $pct > 100) {
                return array('ok' => false, 'code' => 422,
                    'reason' => 'نسبةُ المقدم في (0,100] — والمقروءُ ' . $pct);
            }
            $amount = round($total * $pct / 100, 2);
        } else {
            $basisKind = 'fixed';
            $amount = round((float) (isset($adv['amount']) ? $adv['amount'] : 0), 2);
            if ($amount <= 0) {
                return array('ok' => false, 'code' => 422, 'reason' => 'مبلغُ المقدم موجب');
            }
        }

        $due = self::dateOrNull(isset($adv['due_date']) ? $adv['due_date'] : null);
        $cond = trim((string) (isset($adv['due_condition']) ? $adv['due_condition'] : ''));
        if ($due === null && $cond === '') { $due = (string) $startDate; }

        return array('ok' => true, 'code' => 200, 'reason' => '', 'row' => array(
            'payment_kind' => 'advance', 'advance_type' => $type,
            'treatment' => $treatment, 'treatment_basis' => $basis,
            'amount_basis' => $basisKind, 'percent_value' => $pct,
            'amount_expected' => $amount, 'currency' => $cur,
            'due_date' => $due, 'due_condition' => ($cond === '' ? null : mb_substr($cond, 0, 200)),
            'source' => 'generated',
            'note' => self::ADVANCE_AR[$type] . ' — والمعالجةُ ' . $treatment,
        ));
    }

    /**
     * سطرٌ يدويٌّ — **للمعالم والدفعات الخاصة وحدَها** (§3.5).
     * @return array{ok:bool,code:int,reason:string,row_id:int}
     */
    public static function addRow($conn, $gate, $companyId, $contractId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'row_id' => 0);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غيرُ موجود'; return $out; }

        $kind = (string) (isset($a['payment_kind']) ? $a['payment_kind'] : '');
        if (!in_array($kind, self::KINDS, true)) {
            $out['code'] = 422; $out['reason'] = 'نوعُ دفعةٍ غيرُ معروف: ' . $kind; return $out;
        }
        if ($kind === 'monthly_settlement') {
            $out['code'] = 422;
            $out['reason'] = '**التسويةُ الشهرية تُولَّد من الجدول لا تُدخل يدويًّا** — '
                . 'وإدخالُها يدويًّا يفتح مصدرًا ثانيًا للرقم نفسِه';
            return $out;
        }
        $live = self::liveRows($gate, (int) $contractId);
        $version = $live ? (int) $live[0]['version'] : 1;
        $seq = 0;
        foreach ($live as $r) { $seq = max($seq, (int) $r['seq']); }

        $row = array(
            'contract_id' => (int) $contractId, 'version' => $version, 'seq' => $seq + 1,
            'effective_from' => $live ? (string) $live[0]['effective_from'] : (string) $c['actual_start'],
            'pattern' => (string) (isset($a['pattern']) ? $a['pattern'] : 'milestone_payments'),
            'payment_kind' => $kind,
            'amount_basis' => 'fixed',
            'amount_expected' => round((float) (isset($a['amount_expected']) ? $a['amount_expected'] : 0), 2),
            'currency' => (string) (isset($a['currency']) ? $a['currency'] : ($live ? $live[0]['currency'] : '')),
            'due_date' => self::dateOrNull(isset($a['due_date']) ? $a['due_date'] : null),
            'due_condition' => trim((string) (isset($a['due_condition']) ? $a['due_condition'] : '')) !== ''
                               ? mb_substr(trim((string) $a['due_condition']), 0, 200) : null,
            'source' => 'manual',
            'note' => isset($a['note']) ? mb_substr((string) $a['note'], 0, 255) : null,
            'created_by' => (int) $actor ?: null,
        );
        if (!in_array($row['pattern'], self::PATTERNS, true)) { $row['pattern'] = 'milestone_payments'; }
        if ($kind === 'advance') {
            // السطرُ اليدويُّ **بمبلغه لا بنسبته** — والنوعُ والمعالجةُ وحدَهما من `advance`
            $advIn = isset($a['advance']) && is_array($a['advance']) ? $a['advance'] : array();
            $advIn['basis'] = 'fixed';
            $advIn['amount'] = (float) $row['amount_expected'];
            $built = self::buildAdvanceRow($advIn,
                (float) $row['amount_expected'], (string) $row['currency'], (string) $c['actual_start']);
            if (!$built['ok']) { return array_merge($out, $built); }
            foreach (array('advance_type', 'treatment', 'treatment_basis') as $k) {
                $row[$k] = $built['row'][$k];
            }
        }
        if ($row['amount_expected'] <= 0) {
            $out['code'] = 422; $out['reason'] = 'مبلغُ السطر موجب'; return $out;
        }
        if ($row['due_date'] === null && $row['due_condition'] === null) {
            $out['code'] = 422;
            $out['reason'] = '**تاريخُ الاستحقاق أو شرطُه إلزامي** — ولا سطرَ بلا استحقاق'; return $out;
        }
        try { $id = (int) $gate->insert('contract_payment_schedule', $row); }
        catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت الإضافة: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'add_payment_row', $id, array(), $row);
        $out['ok'] = true; $out['code'] = 200; $out['row_id'] = $id;
        $out['reason'] = 'أُضيف سطرُ «' . self::KIND_AR[$kind] . '» بمبلغ ' . $row['amount_expected'];
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القبض — والالتزامُ وحدَه يدخل دفترَ السلف
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تسجيلُ قبضٍ على سطرٍ من الخطة.
     *  · `treatment='liability'` ⇒ يُقبض بـ`advance_record` (M-01) **فيدخل رصيدَ
     *    الاستهلاك** ولا يُكتب رقمٌ ثانٍ في مكانٍ آخر.
     *  · وغيرُه ⇒ **لا يدخل `contract_advances` بتاتًا** — فلا يُستقطع من مستخلص.
     *
     * @return array{ok:bool,code:int,reason:string,state:string,received:float,remaining:float}
     */
    public static function markReceived($conn, $gate, $companyId, $rowId, $amount, $receivedDate,
                                        $docRef, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => '',
                     'received' => 0.0, 'remaining' => 0.0);
        $r = self::rowOf($gate, (int) $rowId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'سطرُ الخطة غيرُ موجود'; return $out; }
        if ($r['effective_to'] !== null) {
            $out['code'] = 423;
            $out['reason'] = '**السطرُ من نسخةٍ مختومة** — والقبضُ على النسخة النافذة'; return $out;
        }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { $out['code'] = 422; $out['reason'] = 'مبلغُ القبض موجب'; return $out; }
        $docRef = trim((string) $docRef);
        if ($docRef === '') {
            $out['code'] = 422; $out['reason'] = '**مرجعُ سند القبض إلزامي** — لا قبضَ بلا مستند'; return $out;
        }
        $receivedDate = self::dateOrNull($receivedDate);
        if ($receivedDate === null) { $out['code'] = 422; $out['reason'] = 'تاريخُ القبض غير صالح'; return $out; }

        $expected = round((float) $r['amount_expected'], 2);
        $already = round((float) $r['received_amount'], 2);
        $after = round($already + $amount, 2);

        // ③ **الزائدُ يُعلَن ولا يُبتلع**
        if ($after > $expected + 0.0001) {
            $out['code'] = 409;
            $out['reason'] = '**المقبوضُ ' . $after . ' يتجاوز المتوقَّع ' . $expected . '** — '
                . 'والفائضُ ' . round($after - $expected, 2)
                . ' **رصيدٌ دائنٌ للعميل لا إيراد**: بيتُه قناةُ التخصيص (P-07) لا سطرُ الخطة';
            $out['received'] = $already; $out['remaining'] = round($expected - $already, 2);
            return $out;
        }

        $advId = ($r['advance_id'] !== null) ? (int) $r['advance_id'] : null;
        $isLiability = ((string) $r['treatment'] === 'liability');

        try {
            if ($isLiability) {
                // ② **مصدرٌ واحدٌ للرقم**: دفترُ السلف القائم (M-01) هو القابض
                require_once dirname(__DIR__, 3) . '/Contracts/advance_helpers.php';
                $ar = advance_record($conn, $gate, (int) $r['contract_id'], $amount,
                                     $receivedDate, $docRef,
                                     'قبضٌ على سطر خطة الدفع #' . (int) $rowId, (int) $actor);
                if (!$ar['ok']) {
                    $out['code'] = (int) $ar['code'] ?: 422;
                    $out['reason'] = 'تعذّر قبضُ المقدم: ' . $ar['reason']; return $out;
                }
                $advId = (int) $ar['advance_id'];
            }
            $state = self::stateFor($after, $expected, (string) $r['due_date']);
            $gate->update('contract_payment_schedule', array(
                'received_amount' => $after, 'state' => $state,
                'collection_ref' => mb_substr($docRef, 0, 120),
                'advance_id' => $advId,
            ), array('id' => (int) $rowId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason' ] = 'تعذّر تسجيلُ القبض: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'payment_received', (int) $rowId,
            array('received' => $already), array('received' => $after, 'doc_ref' => $docRef));

        $out['ok'] = true; $out['code'] = 200; $out['received'] = $after;
        $out['remaining'] = round($expected - $after, 2);
        $out['state'] = self::stateFor($after, $expected, (string) $r['due_date']);
        $out['reason'] = 'قُبض ' . $amount . ' · المستلمُ ' . $after . ' من ' . $expected
            . ' · الحال: ' . self::STATE_AR[$out['state']]
            . ($isLiability
               ? ' · **دخل دفترَ السلف فيُستهلك من المستخلصات**'
               : ' · **إيرادٌ لا يدخل دفترَ السلف** فلا يُستقطع من مستخلص');
        return $out;
    }

    /**
     * نسخةٌ جديدةٌ بملحق — **والقديمةُ تُختم وتبقى** (§9-⑰).
     * @return array{ok:bool,code:int,reason:string,version:int,rows:int}
     */
    public static function newVersion($conn, $gate, $companyId, $contractId, $effectiveFrom,
                                      $amendmentId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'version' => 0, 'rows' => 0);
        $live = self::liveRows($gate, (int) $contractId);
        if (!$live) {
            $out['code'] = 404; $out['reason'] = 'لا خطةَ نافذةً تُنسخ — ولّدها أولًا'; return $out;
        }
        $eff = self::dateOrNull($effectiveFrom);
        if ($eff === null) { $out['code'] = 422; $out['reason'] = '**تاريخُ سريان النسخة إلزامي**'; return $out; }
        $old = (int) $live[0]['version'];
        if ($eff < (string) $live[0]['effective_from']) {
            $out['code'] = 422;
            $out['reason'] = '**سريانُ النسخة الجديدة قبل سريان القديمة** ('
                . $live[0]['effective_from'] . ') — والزمنُ لا يرجع';
            return $out;
        }
        $closeAt = date('Y-m-d', strtotime($eff . ' -1 day'));
        if ($closeAt < (string) $live[0]['effective_from']) { $closeAt = (string) $live[0]['effective_from']; }

        try {
            $gate->runInTransaction(function ($g) use ($live, $contractId, $eff, $closeAt, $old, $amendmentId, $actor) {
                foreach ($live as $r) {
                    $g->update('contract_payment_schedule', array('effective_to' => $closeAt),
                        array('id' => (int) $r['id']));
                    $new = array(
                        'contract_id' => (int) $contractId, 'version' => $old + 1,
                        'effective_from' => $eff, 'effective_to' => null,
                        'amendment_id' => ((int) $amendmentId > 0) ? (int) $amendmentId : null,
                        'seq' => (int) $r['seq'], 'pattern' => (string) $r['pattern'],
                        'payment_kind' => (string) $r['payment_kind'],
                        'advance_type' => $r['advance_type'], 'treatment' => $r['treatment'],
                        'treatment_basis' => $r['treatment_basis'],
                        'amount_basis' => (string) $r['amount_basis'],
                        'percent_value' => $r['percent_value'],
                        'amount_expected' => (float) $r['amount_expected'],
                        'currency' => (string) $r['currency'],
                        'due_date' => $r['due_date'], 'due_condition' => $r['due_condition'],
                        'period_month' => $r['period_month'], 'line_id' => $r['line_id'],
                        // **المقبوضُ يُرحَّل**: القبضُ واقعةٌ لا تُلغيها نسخة
                        'received_amount' => (float) $r['received_amount'],
                        'state' => (string) $r['state'],
                        'collection_ref' => $r['collection_ref'], 'advance_id' => $r['advance_id'],
                        'source' => (string) $r['source'], 'note' => $r['note'],
                        'created_by' => (int) $actor ?: null,
                    );
                    $g->insert('contract_payment_schedule', $new);
                }
            }, 'نسخة خطة دفع للعقد ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت النسخة: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'new_schedule_version', (int) $contractId,
            array('version' => $old), array('version' => $old + 1, 'amendment_id' => $amendmentId));
        $out['ok'] = true; $out['code'] = 200; $out['version'] = $old + 1; $out['rows'] = count($live);
        $out['reason'] = 'فُتحت النسخة ' . ($old + 1) . ' من ' . $eff . ' بـ' . count($live)
            . ' سطرًا · **والنسخة ' . $old . ' مختومةٌ في ' . $closeAt . ' وباقية**';
        return $out;
    }

    /** تحديثُ الحالات بمرور الزمن — «المتأخرُ يُعلَن لا يُكتشف بالمصادفة». */
    public static function refreshStates($gate, $contractId, $today = null)
    {
        $day = self::dateOrNull($today);
        if ($day === null) { $day = date('Y-m-d'); }
        $n = 0;
        foreach (self::liveRows($gate, (int) $contractId) as $r) {
            $st = self::stateFor((float) $r['received_amount'], (float) $r['amount_expected'],
                                 (string) $r['due_date'], $day);
            if ($st !== (string) $r['state']) {
                try {
                    $gate->update('contract_payment_schedule', array('state' => $st),
                        array('id' => (int) $r['id']));
                    $n++;
                } catch (\Throwable $t) { /* حالةٌ لا تُكتب لا تُسقط الباقي */ }
            }
        }
        return $n;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ القراءة
    // ═════════════════════════════════════════════════════════════════════

    /** ملخّصُ الخطة — والمتوقَّعُ والمستلمُ والمتبقي **مفروزًا بالمعالجة**. */
    public static function summary($gate, $contractId, $today = null)
    {
        $day = self::dateOrNull($today);
        if ($day === null) { $day = date('Y-m-d'); }
        $o = array('currency' => '', 'expected' => 0.0, 'received' => 0.0, 'remaining' => 0.0,
                   'liability' => 0.0, 'revenue' => 0.0, 'overdue' => 0.0, 'overdue_rows' => 0,
                   'by_kind' => array(), 'rows' => 0, 'version' => 0, 'note' => '');
        $rows = self::liveRows($gate, (int) $contractId);
        foreach ($rows as $r) {
            $o['currency'] = (string) $r['currency'];
            $o['version'] = (int) $r['version'];
            $exp = round((float) $r['amount_expected'], 2);
            $rec = round((float) $r['received_amount'], 2);
            $o['expected'] = round($o['expected'] + $exp, 2);
            $o['received'] = round($o['received'] + $rec, 2);
            $k = (string) $r['payment_kind'];
            if (!isset($o['by_kind'][$k])) { $o['by_kind'][$k] = array('expected' => 0.0, 'received' => 0.0); }
            $o['by_kind'][$k]['expected'] = round($o['by_kind'][$k]['expected'] + $exp, 2);
            $o['by_kind'][$k]['received'] = round($o['by_kind'][$k]['received'] + $rec, 2);
            if ((string) $r['treatment'] === 'liability') { $o['liability'] = round($o['liability'] + $rec, 2); }
            elseif ((string) $r['treatment'] === 'revenue') { $o['revenue'] = round($o['revenue'] + $rec, 2); }
            $st = self::stateFor($rec, $exp, (string) $r['due_date'], $day);
            if ($st === 'overdue') {
                $o['overdue'] = round($o['overdue'] + ($exp - $rec), 2);
                $o['overdue_rows']++;
            }
        }
        $o['rows'] = count($rows);
        $o['remaining'] = round($o['expected'] - $o['received'], 2);
        $o['note'] = $o['rows'] . ' سطرًا · متوقَّعٌ ' . $o['expected'] . ' ' . $o['currency']
            . ' · مستلمٌ ' . $o['received'] . ' · متبقٍّ ' . $o['remaining']
            . ($o['overdue_rows'] > 0
               ? (' · **متأخرٌ ' . $o['overdue'] . ' في ' . $o['overdue_rows'] . ' سطرًا**') : '')
            . ' · **وΣ الخطة ليست قيمةَ العقد**';
        return $o;
    }

    public static function liveRows($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('s' => 'contract_payment_schedule')),
                "SELECT s.* FROM contract_payment_schedule s
                  WHERE {TENANT_SCOPE} AND s.contract_id = ? AND s.effective_to IS NULL
                    AND COALESCE(s.is_deleted,0) = 0
                  ORDER BY s.seq", array((int) $contractId));
        } catch (\Throwable $t) { error_log('P-05 liveRows: ' . $t->getMessage()); return array(); }
    }

    public static function allRows($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('s' => 'contract_payment_schedule')),
                "SELECT s.* FROM contract_payment_schedule s
                  WHERE {TENANT_SCOPE} AND s.contract_id = ?
                  ORDER BY s.version DESC, s.seq", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function versionsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('s' => 'contract_payment_schedule')),
                "SELECT s.version, s.effective_from, MAX(s.effective_to) AS effective_to,
                        COUNT(*) AS rows_n, ROUND(SUM(s.amount_expected),2) AS expected
                   FROM contract_payment_schedule s
                  WHERE {TENANT_SCOPE} AND s.contract_id = ?
                  GROUP BY s.version, s.effective_from
                  ORDER BY s.version DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function rowOf($gate, $id)
    {
        try { return $gate->selectOne('contract_payment_schedule', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════

    /** الحالُ من المبالغ والتاريخ — الخمسةُ في §3.5. */
    public static function stateFor($received, $expected, $dueDate, $today = null)
    {
        $received = round((float) $received, 2);
        $expected = round((float) $expected, 2);
        if ($received >= $expected - 0.0001 && $expected > 0) { return 'completed'; }
        $day = ($today !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today))
               ? (string) $today : date('Y-m-d');
        $due = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dueDate)) ? (string) $dueDate : null;
        $late = ($due !== null && $due < $day);
        if ($received > 0.0001) { return $late ? 'overdue' : 'partial'; }
        if ($late) { return 'overdue'; }
        // **الشرطيُّ لا يتأخر**: سطرٌ استحقاقُه شرطٌ لا تاريخٌ يبقى «غيرَ مستحق»
        if ($due === null) { return 'not_due'; }
        return ($due <= $day) ? 'due' : 'not_due';
    }

    private static function contractOf($gate, $id)
    {
        try { return $gate->selectOne('contracts', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_payment_schedule', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
