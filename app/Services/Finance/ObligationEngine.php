<?php
/**
 * محرّكُ الالتزاماتِ والاستحقاقات — ObligationEngine (FIN-OBL-01 §٤-٥ .. §٤-٢٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * يجيب سؤالًا واحدًا: **متى ينشأ التزامٌ على الشركة؟** — والجوابُ بنيويٌّ لا وصفي.
 *
 *  - OR-01  ◆ الالتزامُ يُنشأ عند **اعتمادِ العقدِ** لا عند أولِ دفعة · والعقدُ
 *           النافذُ يولّد جدولَ استحقاقٍ لكلِّ مدتِه فورًا.
 *  - SY-06  ◆ ويُنتَج **دفعةً واحدة** لا شهرًا بشهر — فالإنشاءُ التدريجيُّ يُخفي
 *           المدى الكاملَ عن التدفقِ النقديِّ وعن المراجع.
 *  - OR-02  وكلُّ استحقاقٍ بتاريخِه **بيومه** لا شهرًا مجملًا.
 *  - SY-02  ◆ عقدٌ اثنا عشرَ شهرًا يبدأ يومَ عشرين يمسُّ **ثلاثةَ عشرَ** إقفالًا
 *           محاسبيًّا: كسرٌ أولٌ وأحدَ عشرَ كاملًا وكسرٌ أخير.
 *  - SY-03  ◆ وهو نفسُه **اثنا عشرَ** إقفالًا تعاقديًّا — والفرقُ ليس خطأً بل
 *           مقياسان مختلفان، ولا يُخلطان في تقريرٍ بلا إعلانِ أيِّهما يُقاس.
 *  - SY-04  والكسرُ بالتناسبِ اليوميّ · SY-05 ويُوسَم صريحًا.
 *  - OR-03  والتصنيفُ قصيرًا أو طويلًا بتاريخِ الاستحقاقِ — ويُعاد كلَّ إقفال.
 *  - OR-05  والمستحقُّ غيرُ المدفوعِ يُرحَّل إلى الذممِ الدائنة.
 *  - OR-07  وتعديلُ العقدِ **لا يحذف** الجدولَ القديم — يُغلقه ويُنشئ جديدًا يشير إليه.
 *  - OR-08  وإنهاؤه يُغلق ما لم يستحقَّ — والمستحقُّ قبلَه يبقى حتى يُسدَّد.
 *  - OR-12  وتقريرُ الالتزاماتِ ثلاثةُ آفاق: ثلاثون يومًا · سنةٌ · وما بعدها.
 *
 * ◆ الحدُّ الحاكم — OR-10 / OBL-0051:
 *   «المحرّكُ **لا يُنشئ قيدًا** بل جدولًا معلَنًا — والقيدُ يقع عند الاستحقاقِ
 *    الفعليِّ أو الاستلامِ أو الدفعِ بحسبِ طبيعةِ الالتزام.»
 *   فلا سطرَ في هذا الملفِّ يكتب في `fin_journal_entries` ولا `fin_journal_lines`.
 */

namespace App\Services\Finance;

class ObligationEngine
{
    /** آفاقُ التقريرِ الثلاثة (OR-12) — بالأيام. */
    const HORIZON_NEAR = 30;
    const HORIZON_YEAR = 365;

    /** حدُّ الجوهريةِ لنسبةِ غيرِ القابلِ للتجنب (AV-3) — يُراجَع بقرارٍ مالي. */
    const MATERIALITY_PCT = 10.0;

    /* ═══════════════════════════════════════════════════════════════════════
       ① اختبارُ التجنبِ الخماسي — بترتيبِه ولا يُقفز (OBL-0200)
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يُجري الاختباراتِ الخمسةَ بالترتيبِ ويسجّل نتيجتَها.
     * «ولا يُترك عقدٌ بلا نتيجةِ اختبارٍ مسجَّلة».
     *
     * @param array $c company_id · contract_kind · contract_ref · contract_value ·
     *                 cancellable · cancel_cost · min_guarantee · termination_cost ·
     *                 special_standard · expected_benefit · decided_by
     */
    public static function avoidanceTest(\mysqli $conn, array $c)
    {
        $co    = intval($c['company_id'] ?? 0);
        $kind  = trim((string) ($c['contract_kind'] ?? ''));
        $ref   = trim((string) ($c['contract_ref'] ?? ''));
        $by    = intval($c['decided_by'] ?? 0);
        $value = (float) ($c['contract_value'] ?? 0);
        if ($co <= 0 || $kind === '' || $ref === '' || $by <= 0) {
            return self::fail(422, 'اختبارُ التجنبِ يحتاج الكيانَ والعقدَ ومن يقرر');
        }

        $steps = array();

        /* AV-1 — أالعقدُ قابلٌ للإلغاءِ من طرفنا بلا تكلفةٍ جوهرية؟ */
        $cancellable = !empty($c['cancellable']);
        $steps['AV-1'] = $cancellable ? 'نعم → ارتباطٌ يُفصح عنه فقط' : 'لا → يُنتقل للثاني';

        /* AV-2 — ما مقدارُ المبلغِ غيرِ القابلِ للتجنب؟ «أعلاها». */
        $penalty   = (float) ($c['cancel_cost'] ?? 0);
        $termCost  = (float) ($c['termination_cost'] ?? 0);
        $minGuar   = (float) ($c['min_guarantee'] ?? 0);
        $unavoid   = $cancellable ? 0.0 : max($penalty, $termCost, $minGuar);
        $steps['AV-2'] = 'غيرُ القابلِ للتجنب = ' . number_format($unavoid, 2)
                       . ' (أعلى: جزاء ' . number_format($penalty, 2)
                       . ' · إنهاء ' . number_format($termCost, 2)
                       . ' · حدٌّ أدنى ' . number_format($minGuar, 2) . ')';

        /* AV-3 — أنسبتُه من قيمةِ العقدِ تبلغ حدًّا جوهريًّا؟ */
        $pct = $value > 0 ? round($unavoid / $value * 100, 3) : 0.0;
        $candidate = ($pct >= self::MATERIALITY_PCT);
        $steps['AV-3'] = 'النسبة ' . $pct . '٪ ' . ($candidate ? '≥' : '<') . ' ' . self::MATERIALITY_PCT
                       . '٪ → ' . ($candidate ? 'مرشَّحٌ للاعتراف' : 'ارتباطٌ يُفصح عنه والجزاءُ منفصلًا');

        /* AV-4 — أيوجد معيارٌ خاصٌّ يوجب الاعترافَ بلا استثناء؟
           ◆ والمعيارُ الخاصُّ **يحدّد قادحَه** ولا يوجب الاعترافَ لحظةَ التوقيع:
             الإيجارُ عند بدءِ السريان · والتمويلُ عند السحب · والموظفُ عند أداءِ
             الخدمة. فوجودُ المعيارِ وحدَه لا يكفي — يُسأل: **أوقع قادحُه؟**
             (كشفه STDTEST-04: تسهيلٌ غيرُ مسحوبٍ كان يخرج «recognize» وحكمُ
              الوثيقةِ «ارتباطٌ يُفصح عنه وصفرُ قيد»). */
        $special = trim((string) ($c['special_standard'] ?? self::specialStandardFor($kind)));
        $trigger = array_key_exists('special_trigger_met', $c) && $c['special_trigger_met'] !== ''
                 ? !empty($c['special_trigger_met'])
                 /* بلا إفادةٍ صريحة: القابلُ للإلغاءِ لم يُسحب ولم يبدأ سريانُه بعدُ. */
                 : !$cancellable;
        $specialDue = ($special !== '' && $trigger);
        $steps['AV-4'] = $special === '' ? 'لا معيارَ خاصَّ موجِب'
                       : ('معيارٌ خاصّ: ' . $special . ' — قادحُه '
                          . ($trigger ? 'وقع → يوجب الاعتراف' : 'لم يقع → ارتباطٌ يُفصح عنه'));

        /* AV-5 — أتفوق التكاليفُ غيرُ القابلةِ للتجنبِ المنافعَ المتوقعة؟ */
        $benefit = isset($c['expected_benefit']) && $c['expected_benefit'] !== '' ? (float) $c['expected_benefit'] : null;
        $onerous = ($benefit !== null && $unavoid > $benefit);
        $steps['AV-5'] = $benefit === null ? 'لا منافعَ مقدَّرةٌ فلا حكمَ بالإثقال'
                       : ($onerous ? 'مُثقِلٌ → مخصَّصٌ فورًا' : 'غيرُ مُثقِل');

        /* الحكم — بترتيبِ الأسبقية: الإثقالُ ثم المعيارُ الخاصُّ ثم الجوهرية. */
        if ($onerous)         { $verdict = 'onerous'; }
        elseif ($specialDue)  { $verdict = 'recognize'; }
        elseif ($cancellable) { $verdict = 'disclose_only'; }
        elseif ($candidate)   { $verdict = 'recognition_candidate'; }
        else                  { $verdict = 'disclose_with_penalty'; }

        /* التزامان لا واحد (OBL-0204) — ولا يُدمجان في رقمٍ واحدٍ بحال:
             الحجمُ مشروطٌ بالأداءِ ويسقط بالعجز · والجزاءُ غيرُ مشروطٍ ولا يسقط. */
        $volume  = max(0.0, $value - $unavoid);
        $penalObl = $unavoid;

        $sql = "INSERT INTO fin_obl_avoidance
                  (company_id, contract_kind, contract_ref, contract_value, currency,
                   cancellable, cancel_cost, unavoidable, unavoidable_pct, recognition_candidate,
                   volume_obligation, penalty_obligation, special_standard, onerous, expected_benefit,
                   verdict, decided_by, decided_at, next_review_at, steps_json)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?)
                ON DUPLICATE KEY UPDATE
                  contract_value=VALUES(contract_value), cancellable=VALUES(cancellable),
                  cancel_cost=VALUES(cancel_cost), unavoidable=VALUES(unavoidable),
                  unavoidable_pct=VALUES(unavoidable_pct), recognition_candidate=VALUES(recognition_candidate),
                  volume_obligation=VALUES(volume_obligation), penalty_obligation=VALUES(penalty_obligation),
                  special_standard=VALUES(special_standard), onerous=VALUES(onerous),
                  expected_benefit=VALUES(expected_benefit), verdict=VALUES(verdict),
                  decided_by=VALUES(decided_by), decided_at=NOW(),
                  next_review_at=VALUES(next_review_at), steps_json=VALUES(steps_json)";
        $st = $conn->prepare($sql);
        if (!$st) { return self::fail(500, 'تعذّر تسجيلُ نتيجةِ الاختبار: ' . $conn->error); }
        $cur   = (string) ($c['currency'] ?? 'USD');
        $canc  = $cancellable ? 1 : 0;
        $cand  = $candidate ? 1 : 0;
        $oner  = $onerous ? 1 : 0;
        $next  = (string) ($c['next_review_at'] ?? date('Y-m-d', strtotime('+1 year')));
        $json  = mb_substr(json_encode($steps, JSON_UNESCAPED_UNICODE), 0, 900);
        $vals  = array($co, $kind, $ref, $value, $cur, $canc, $penalty, $unavoid, $pct, $cand,
                       $volume, $penalObl, $special, $oner, $benefit, $verdict, $by, $next, $json);
        $types = 'i' . 'ss' . 'd' . 's' . 'i' . 'ddd' . 'i' . 'dd' . 's' . 'i' . 'd' . 's' . 'i' . 'ss';
        self::assertArity($types, $vals, 'fin_obl_avoidance');
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) { $e = $st->error; $st->close(); return self::fail(500, 'تعذّر التسجيل: ' . $e); }
        $st->close();

        return array('ok' => true, 'code' => 200, 'verdict' => $verdict,
                     'unavoidable' => $unavoid, 'unavoidable_pct' => $pct,
                     'volume_obligation' => $volume, 'penalty_obligation' => $penalObl,
                     'onerous' => $onerous, 'special_standard' => $special, 'steps' => $steps);
    }

    /** المعيارُ الخاصُّ الموجِبُ للاعترافِ بحسبِ نوعِ العقد (AV-4). */
    public static function specialStandardFor($kind)
    {
        switch ($kind) {
            case 'lease':     return 'معيارُ الإيجارات — يُعترف عند بدءِ السريان';
            case 'financing': return 'معيارُ الأدواتِ المالية — يُعترف عند السحب';
            case 'employee':  return 'معيارُ منافعِ العاملين — يُعترف عند أداءِ الخدمة';
            default:          return '';
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② توليدُ الجدولِ الكامل — عند النفاذِ ودفعةً واحدة
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يولّد الالتزامَ وجدولَه كاملًا.
     *
     * @param array $c company_id · ob_type · side · contract_kind · contract_ref ·
     *                 counterparty · total_value · start_date · end_date · currency ·
     *                 recognition_rule · dims (project_id · site_id · equipment_id ·
     *                 cost_center · party_type · party_id) · generated_by ·
     *                 amount_per_period (اختياري لجدولٍ تعاقديٍّ غيرِ متساوٍ)
     */
    public static function generateSchedule(\mysqli $conn, array $c)
    {
        $co    = intval($c['company_id'] ?? 0);
        $type  = trim((string) ($c['ob_type'] ?? ''));
        $kind  = trim((string) ($c['contract_kind'] ?? ''));
        $ref   = trim((string) ($c['contract_ref'] ?? ''));
        $start = (string) ($c['start_date'] ?? '');
        $end   = (string) ($c['end_date'] ?? '');
        $value = (float) ($c['total_value'] ?? 0);
        $by    = intval($c['generated_by'] ?? 0);
        if ($co <= 0 || $type === '' || $ref === '' || $start === '' || $end === '') {
            return self::fail(422, 'توليدُ الجدولِ يحتاج الكيانَ والنوعَ والعقدَ ومدتَه');
        }
        if (strtotime($end) < strtotime($start)) {
            return self::fail(422, 'تاريخُ الانتهاءِ قبلَ البدء');
        }

        /* ◆ OBL-0200: «ولا يُترك عقدٌ بلا نتيجةِ اختبارٍ مسجَّلة» — والجدولُ لا
             يُولَّد قبلَ الاختبار، فالتصنيفُ يسبق التوليد. */
        if (!self::hasAvoidanceVerdict($conn, $co, $kind, $ref)) {
            return self::fail(409, 'لا يُولَّد جدولٌ لعقدٍ بلا نتيجةِ اختبارِ تجنبٍ مسجَّلة (OBL-0200)');
        }

        $periods = self::buildPeriods($start, $end);
        $acct    = count($periods);                      // SY-02 — الإقفالاتُ المحاسبية
        $contr   = self::contractPeriods($start, $end);  // SY-03 — الإقفالاتُ التعاقدية

        /* الحصةُ الشهريةُ على الفتراتِ التعاقديةِ لا المحاسبية (AR-03):
           فالقيمةُ تُقسَّم على شهورِ العقدِ ثم يُقسَّم الكسرُ بالتناسبِ اليومي. */
        $monthly = isset($c['amount_per_period']) && $c['amount_per_period'] !== ''
                 ? (float) $c['amount_per_period']
                 : ($contr > 0 ? round($value / $contr, 2) : 0.0);

        $conn->begin_transaction();
        try {
            /* OR-07: القائمُ يُغلق ويشير إليه الجديدُ — ولا يُحذف. */
            $prev = self::activeObligation($conn, $co, $kind, $ref);
            if ($prev !== null) {
                self::exec($conn, "UPDATE fin_obl_register SET state='superseded' WHERE id=?", 'i', array($prev['id']));
                self::exec($conn, "UPDATE fin_obl_schedule SET state='cancelled',
                                     close_reason='حلَّ محلَّه جدولٌ جديدٌ بمرجعِ تعديل'
                                   WHERE obligation_id=? AND state='scheduled'", 'i', array($prev['id']));
            }

            $no = (string) ($c['obligation_no'] ?? ('OBL-' . $co . '-' . strtoupper(substr(sha1($kind . $ref . microtime(true)), 0, 10))));
            $sql = "INSERT INTO fin_obl_register
                      (company_id, obligation_no, ob_type, side, contract_kind, contract_ref, counterparty,
                       currency, total_value, start_date, end_date, accounting_periods, contract_periods,
                       proration_basis, project_id, site_id, equipment_id, cost_center, party_type, party_id,
                       dims_json, state, supersedes_id, amendment_ref, generated_at, generated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',?,?,NOW(),?)";
            $st = $conn->prepare($sql);
            if (!$st) { throw new \RuntimeException('تعذّر إنشاءُ الالتزام: ' . $conn->error); }
            $side  = in_array((string) ($c['side'] ?? 'payable'), array('payable', 'receivable'), true) ? (string) $c['side'] : 'payable';
            $party = mb_substr((string) ($c['counterparty'] ?? ''), 0, 200);
            $cur   = (string) ($c['currency'] ?? 'USD');
            $basis = (string) ($c['proration_basis'] ?? 'daily');
            $prj = !empty($c['project_id'])   ? intval($c['project_id'])   : null;
            $sit = !empty($c['site_id'])      ? intval($c['site_id'])      : null;
            $eqp = !empty($c['equipment_id']) ? intval($c['equipment_id']) : null;
            $cc  = mb_substr((string) ($c['cost_center'] ?? ''), 0, 60);
            $pt  = mb_substr((string) ($c['party_type'] ?? ''), 0, 16);
            $pid = !empty($c['party_id']) ? intval($c['party_id']) : null;
            $dims = mb_substr((string) ($c['dims_json'] ?? ''), 0, 400);
            $sup  = $prev !== null ? intval($prev['id']) : null;
            $amd  = mb_substr((string) ($c['amendment_ref'] ?? ''), 0, 120);
            $vals = array($co, $no, $type, $side, $kind, $ref, $party, $cur, $value, $start, $end,
                          $acct, $contr, $basis, $prj, $sit, $eqp, $cc, $pt, $pid, $dims, $sup, $amd, $by);
            $types = 'i' . 'ssssss' . 's' . 'd' . 'ss' . 'ii' . 's' . 'iii' . 'ss' . 'i' . 's' . 'i' . 's' . 'i';
            self::assertArity($types, $vals, 'fin_obl_register');
            $st->bind_param($types, ...$vals);
            if (!$st->execute()) { $e = $st->error; $st->close(); throw new \RuntimeException('تعذّر إنشاءُ الالتزام: ' . $e); }
            $oblId = $st->insert_id;
            $st->close();

            /* SY-06: الجدولُ كلُّه دفعةً واحدةً هنا — لا شهرًا بشهر. */
            $rule    = mb_substr((string) ($c['recognition_rule'] ?? self::recognitionRuleFor($conn, $kind)), 0, 300);
            $cum     = 0.0;
            $remain  = $value;
            $ins = $conn->prepare(
                "INSERT INTO fin_obl_schedule
                   (company_id, obligation_id, period_no, period_start, period_end, due_date,
                    is_partial, partial_days, month_days, proration_basis,
                    l1_commitment, l1_remaining, l2_recognized, l2_cumulative,
                    l3_open, settled, gap_l1_l2, recognition_rule, term_class, state)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?,'scheduled')");
            if (!$ins) { throw new \RuntimeException('تعذّر إنشاءُ الجدول: ' . $conn->error); }

            foreach ($periods as $i => $p) {
                /* SY-04: الكسرُ بالتناسبِ اليوميّ — أيامُ الكسرِ ÷ أيامِ الشهرِ × الحصة. */
                $amt = $p['partial']
                     ? round($monthly * $p['days'] / $p['month_days'], 2)
                     : $monthly;
                $cum += $amt;
                $remain = round($remain - $amt, 2);
                /* OR-03: قصيرٌ ما يستحق خلالَ اثني عشرَ شهرًا من اليوم. */
                $term = (strtotime($p['due']) <= strtotime('+' . self::HORIZON_YEAR . ' days')) ? 'short' : 'long';

                /* ◆ الطبقاتُ الثلاثُ لا تُدمج ولا تُقفز (OBL-0137):
                     • L1 الارتباط = **حصةُ هذه الفترة** — ينشأ عند اعتمادِ العقد
                       ولا يُقيَّد في الميزانية (OBL-0134). ومجموعُ الأعمدةِ يساوي
                       قيمةَ العقدِ بلا تسرُّب.
                     • L1 المتبقي = رصيدُ الارتباطِ غيرِ المنفَّذِ بعدَ هذه الفترة.
                     • L2 المعترَفُ به = **صفرٌ عند التوليد**: «تنشأ عند أداءِ
                       الطرفِ الآخرِ أو تحققِ شرطِ الاعتراف» (OBL-0135) — ولا أداءَ
                       وقع بعدُ. ومن يملؤها هنا يُثبت اعترافًا لا وجودَ له.
                     • L3 الذمة = صفرٌ حتى الفوترةِ أو حلولِ السداد (OBL-0136).
                     • الفرقُ = الارتباطُ ناقصَ المعترَفِ به (OBL-0165). */
                $vals = array($co, $oblId, $i + 1, $p['start'], $p['end'], $p['due'],
                              $p['partial'] ? 1 : 0, $p['days'], $p['month_days'], $basis,
                              $amt,                    // L1 — حصةُ الفترة
                              max(0.0, $remain),       // L1 المتبقي بعدَها
                              0.0,                     // L2 في الفترة — لا أداءَ بعد
                              0.0,                     // L2 تراكميًّا
                              $amt,                    // الفرقُ = L1 − L2
                              $rule, $term);
                $t = 'ii' . 'i' . 'sss' . 'i' . 'ii' . 's' . 'dddd' . 'd' . 'ss';
                self::assertArity($t, $vals, 'fin_obl_schedule');
                $ins->bind_param($t, ...$vals);
                if (!$ins->execute()) { $e = $ins->error; $ins->close(); throw new \RuntimeException('تعذّر صفُّ الجدول: ' . $e); }
            }
            $ins->close();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            return self::fail(500, $e->getMessage());
        }

        return array('ok' => true, 'code' => 200, 'obligation_id' => $oblId, 'obligation_no' => $no,
                     'accounting_periods' => $acct, 'contract_periods' => $contr,
                     'monthly' => $monthly, 'superseded' => $prev !== null ? $prev['obligation_no'] : null,
                     'reason' => sprintf('وُلِّد جدولٌ بـ%d فترةً محاسبيةً و%d فترةً تعاقدية', $acct, $contr));
    }

    /**
     * فتراتُ الجدولِ المحاسبية (SY-02): كسرٌ أولٌ إن لم يبدأ العقدُ أولَ الشهر ·
     * ثم شهورٌ كاملة · ثم كسرٌ أخيرٌ إن لم ينتهِ آخرَ الشهر.
     * وكلُّ فترةٍ بتاريخِ استحقاقٍ **بيومه** (OR-02) — وهو آخرُ يومٍ فيها.
     */
    public static function buildPeriods($start, $end)
    {
        $out = array();
        $s = new \DateTimeImmutable($start);
        $e = new \DateTimeImmutable($end);
        $cursor = $s;
        while ($cursor <= $e) {
            $monthEnd  = $cursor->modify('last day of this month');
            $periodEnd = ($monthEnd > $e) ? $e : $monthEnd;
            $monthDays = (int) $cursor->format('t');
            $days      = (int) $cursor->diff($periodEnd)->days + 1;
            $out[] = array(
                'start'      => $cursor->format('Y-m-d'),
                'end'        => $periodEnd->format('Y-m-d'),
                'due'        => $periodEnd->format('Y-m-d'),
                'partial'    => ($days < $monthDays),
                'days'       => $days,
                'month_days' => $monthDays,
            );
            $cursor = $periodEnd->modify('+1 day');
        }
        return $out;
    }

    /**
     * الفتراتُ التعاقدية (SY-03): شهورُ العقدِ من تاريخِه إلى مثلِه.
     * ◆ وهي **غيرُ** المحاسبيةِ عمدًا — والفرقُ مقياسان لا خطأ.
     */
    public static function contractPeriods($start, $end)
    {
        $s = new \DateTimeImmutable($start);
        $e = new \DateTimeImmutable($end);
        $d = $s->diff($e->modify('+1 day'));
        return max(1, $d->y * 12 + $d->m + ($d->d > 0 ? 1 : 0));
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ الإقفالُ الدوري — إعادةُ التصنيفِ وترحيلُ المتأخر
       ═══════════════════════════════════════════════════════════════════════ */

    /** OR-03: يُعاد التصنيفُ قصيرًا/طويلًا في كلِّ إقفالٍ آليًّا للجانبين. */
    public static function reclassify(\mysqli $conn, $co, $asOf = null)
    {
        $asOf = $asOf ?: date('Y-m-d');
        $cut  = date('Y-m-d', strtotime($asOf . ' +' . self::HORIZON_YEAR . ' days'));
        $moved = 0;
        $st = $conn->prepare(
            "UPDATE fin_obl_schedule SET term_class = 'short', reclassified_at = NOW()
              WHERE company_id = ? AND state IN ('scheduled','recognized','invoiced')
                AND due_date <= ? AND term_class = 'long'");
        if ($st) {
            $co = (int) $co;
            $st->bind_param('is', $co, $cut);
            $st->execute();
            $moved = $st->affected_rows;
            $st->close();
        }
        return array('ok' => true, 'code' => 200, 'moved_to_short' => max(0, $moved),
                     'reason' => 'رُحِّل إلى القصيرِ ما دخل نطاقَ السنة: ' . max(0, $moved) . ' استحقاقًا');
    }

    /** OR-05: المستحقُّ الذي مرَّ تاريخُه بلا سدادٍ يُرحَّل إلى الذممِ الدائنة. */
    public static function sweepOverdue(\mysqli $conn, $co, $asOf = null)
    {
        $asOf = $asOf ?: date('Y-m-d');
        $n = 0;
        /* ◆ OR-05: «المستحقُّ **غيرُ المدفوعِ** يتحول إلى ذمةٍ دائنة» — والمقياسُ
             الارتباطُ الحالُّ ناقصَ المسدَّد، لا المعترَفَ به: فالمعترَفُ به صفرٌ
             قبلَ الأداء (OBL-0135)، ولو قِيس عليه لما رُحِّل متأخرٌ قطُّ وبقيت
             الذممُ الدائنةُ فارغةً بينما الاستحقاقاتُ تمرُّ بلا دفع. */
        $st = $conn->prepare(
            "UPDATE fin_obl_schedule
                SET state = 'moved_to_payables', moved_at = NOW(),
                    l3_open = GREATEST(0, l1_commitment - settled)
              WHERE company_id = ? AND due_date < ?
                AND state IN ('scheduled','recognized','invoiced','overdue')
                AND settled < l1_commitment");
        if ($st) {
            $co = (int) $co;
            $st->bind_param('is', $co, $asOf);
            $st->execute();
            $n = $st->affected_rows;
            $st->close();
        }
        return array('ok' => true, 'code' => 200, 'moved' => max(0, $n),
                     'reason' => 'رُحِّل إلى الذممِ الدائنة: ' . max(0, $n) . ' استحقاقًا');
    }

    /** OR-08: إنهاءُ العقدِ يُغلق ما لم يستحقَّ — والمستحقُّ قبلَه يبقى. */
    public static function terminate(\mysqli $conn, $co, $kind, $ref, $onDate, $why)
    {
        $obl = self::activeObligation($conn, $co, $kind, $ref);
        if ($obl === null) { return self::fail(404, 'لا التزامَ نشطٌ لهذا العقد'); }
        $kept = 0; $closed = 0;

        $st = $conn->prepare(
            "UPDATE fin_obl_schedule SET state='cancelled', close_reason=?
              WHERE obligation_id=? AND due_date > ? AND state='scheduled'");
        if ($st) {
            $why = mb_substr((string) $why, 0, 300);
            $id = (int) $obl['id'];
            $st->bind_param('sis', $why, $id, $onDate);
            $st->execute();
            $closed = max(0, $st->affected_rows);
            $st->close();
        }
        $kept = (int) self::scalar($conn,
            "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id = " . (int) $obl['id']
          . " AND due_date <= '" . $conn->real_escape_string($onDate) . "' AND state <> 'cancelled'");

        self::exec($conn, "UPDATE fin_obl_register SET state='terminated', terminated_at=? WHERE id=?",
                   'si', array($onDate, $obl['id']));

        return array('ok' => true, 'code' => 200, 'closed_future' => $closed, 'kept_accrued' => $kept,
                     'reason' => "أُغلق $closed استحقاقًا لم يستحقَّ بعدُ · وبقي $kept مستحقًّا حتى يُسدَّد");
    }

    /** OR-12: ثلاثةُ آفاقٍ زمنية — ثلاثون يومًا · سنةٌ · وما بعدها. */
    public static function horizons(\mysqli $conn, $co, $asOf = null)
    {
        $asOf = $asOf ?: date('Y-m-d');
        $d30  = date('Y-m-d', strtotime($asOf . ' +' . self::HORIZON_NEAR . ' days'));
        $d365 = date('Y-m-d', strtotime($asOf . ' +' . self::HORIZON_YEAR . ' days'));
        return array('ok' => true, 'code' => 200, 'as_of' => $asOf,
                     'within_30d' => self::sumDue($conn, $co, $asOf, $d30),
                     'within_1y'  => self::sumDue($conn, $co, $d30, $d365),
                     'beyond_1y'  => self::sumDue($conn, $co, $d365, null));
    }

    /**
     * ما **يحلُّ في** الأفقِ ولم يُسدَّد بعد.
     * ◆ OR-12 يسأل «المستحقُّ خلالَ ثلاثين يومًا · وخلالَ سنةٍ · وما بعدها»
     *   ويُقاطَع «بجدولِ التدفقاتِ النقديةِ المتوقعة» — والتدفقُ المتوقعُ هو
     *   **الارتباطُ الحالُّ ناقصَ المسدَّد**، لا المعترَفَ به: فالمعترَفُ به صفرٌ
     *   قبلَ الأداء (OBL-0135)، ولو قِيس عليه لأظهر التقريرُ صفرًا وأخفى كلَّ
     *   التزامٍ قادمٍ — وهو بعينُه ما جاءت الوثيقةُ لتمنعه.
     */
    private static function sumDue(\mysqli $conn, $co, $from, $to)
    {
        $w = $to === null
           ? "due_date > '" . $conn->real_escape_string($from) . "'"
           : "due_date > '" . $conn->real_escape_string($from) . "' AND due_date <= '" . $conn->real_escape_string($to) . "'";
        return (float) self::scalar($conn,
            "SELECT COALESCE(SUM(GREATEST(l1_commitment - settled, 0)),0) FROM fin_obl_schedule
              WHERE company_id = " . (int) $co . "
                AND state IN ('scheduled','recognized','invoiced','overdue') AND $w");
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑥ حقولُ العقدِ الحاكمة — FIN-OBL-01 §٤-٦ (OBL-0058..0085)
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يفحص عقدًا حيًّا على الحقولِ الحاكمةِ الإلزاميةِ التي **لها موضعٌ في
     * القاعدة**. وما لا موضعَ له لا يُفحص ولا يُدَّعى فحصُه — يُعاد في
     * `gaps` ليُرى.
     *
     * ◆ لماذا تُقرأ المصفوفةُ من الجدولِ لا من ثابتٍ في الكود: لأن الموضعَ
     *   يتغيّر بتغيّرِ القاعدة، ولأن الفجوةَ يجب أن تُعرض للمالكِ في شاشةٍ لا
     *   أن تسكن في مصفوفةِ PHP لا يقرؤها أحد.
     *
     * @return array ok · missing[] · gaps[] · checked
     */
    public static function assertContractFields(\mysqli $conn, $co, $contractId)
    {
        $co = (int) $co; $contractId = (int) $contractId;
        if ($co <= 0 || $contractId <= 0) { return self::fail(422, 'الفحصُ يحتاج الكيانَ والعقد'); }

        $q = $conn->query("SELECT field_code, title, obligation, home_table, home_column, resolve_state
                             FROM fin_contract_fields
                            WHERE active = 1 AND obligation = 'always' ORDER BY seq");
        if ($q === false) { return self::fail(500, 'تعذّر قراءةُ مصفوفةِ الحقول: ' . $conn->error); }
        $fields = $q->fetch_all(MYSQLI_ASSOC);
        if (!$fields) { return self::fail(500, 'مصفوفةُ الحقولِ فارغة — شغّل u13_contract_fields_seed'); }

        /* الأعمدةُ التي تعيش في `contracts` تُقرأ بصفٍّ واحد — والباقي بوجودِ صفٍّ
           مرتبطٍ في جدولِه. */
        $own = array();
        foreach ($fields as $f) {
            if ($f['resolve_state'] === 'live' && $f['home_table'] === 'contracts') { $own[] = $f['home_column']; }
        }
        $row = array();
        if ($own) {
            $sel = '`' . implode('`,`', array_unique($own)) . '`';
            $r = $conn->query("SELECT $sel FROM contracts WHERE id = $contractId LIMIT 1");
            if ($r === false) { return self::fail(500, 'تعذّرت قراءةُ العقد: ' . $conn->error); }
            $row = $r->fetch_assoc() ?: array();
            if (!$row) { return self::fail(404, 'عقدٌ غيرُ موجود: ' . $contractId); }
        }

        $missing = array(); $gaps = array(); $checked = 0;
        foreach ($fields as $f) {
            if ($f['resolve_state'] !== 'live') { $gaps[] = $f['field_code'] . ' — ' . $f['title']; continue; }
            if ($f['home_table'] !== 'contracts') { continue; }   // يُفحص في موضعِه لا هنا
            $checked++;
            $v = $row[$f['home_column']] ?? null;
            /* الصفرُ قيمةٌ مشروعةٌ في مهلةِ السماحِ والمشروع — والفراغُ وحدَه نقص. */
            if ($v === null || trim((string) $v) === '') {
                $missing[] = $f['field_code'] . ' — ' . $f['title'] . ' (' . $f['home_column'] . ')';
            }
        }

        return array('ok' => !$missing, 'code' => $missing ? 409 : 200,
                     'checked' => $checked, 'missing' => $missing, 'gaps' => $gaps,
                     'reason' => $missing
                        ? 'حقولٌ حاكمةٌ إلزاميةٌ ناقصة: ' . implode(' · ', array_slice($missing, 0, 4))
                        : "فُحص $checked حقلًا حاكمًا إلزاميًّا ولا نقص"
                          . ($gaps ? ' · و' . count($gaps) . ' حقلًا إلزاميًّا بلا موضعٍ في القاعدة' : ''));
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    public static function hasAvoidanceVerdict(\mysqli $conn, $co, $kind, $ref)
    {
        $st = $conn->prepare("SELECT 1 FROM fin_obl_avoidance
                               WHERE company_id=? AND contract_kind=? AND contract_ref=? LIMIT 1");
        if (!$st) { return false; }
        $co = (int) $co;
        $st->bind_param('iss', $co, $kind, $ref);
        $st->execute();
        $ok = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    public static function activeObligation(\mysqli $conn, $co, $kind, $ref)
    {
        $st = $conn->prepare("SELECT id, obligation_no FROM fin_obl_register
                               WHERE company_id=? AND contract_kind=? AND contract_ref=? AND state='active'
                               ORDER BY id DESC LIMIT 1");
        if (!$st) { return null; }
        $co = (int) $co;
        $st->bind_param('iss', $co, $kind, $ref);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function recognitionRuleFor(\mysqli $conn, $kind)
    {
        $map = array('client' => 'عقدُ عميلٍ بنموذجِ عمل', 'supplier' => 'عقدُ موردِ خدمةٍ أو معدةٍ بالوحدة',
                     'lease' => 'عقدُ إيجارِ مبنًى أو مرفق', 'employee' => 'عقدُ موظف',
                     'financing' => 'عقدُ تمويلٍ أو قرض', 'po' => 'أمرُ شراءٍ تشغيليٍّ أو رأسمالي');
        $needle = isset($map[$kind]) ? $map[$kind] : $kind;
        $st = $conn->prepare("SELECT standard, trigger_text FROM fin_obl_recognition
                               WHERE contract_kind LIKE CONCAT('%', ?, '%') AND active=1 LIMIT 1");
        if (!$st) { return ''; }
        $st->bind_param('s', $needle);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? ($r['standard'] . ' — ' . $r['trigger_text']) : '';
    }

    private static function scalar(\mysqli $conn, $sql)
    {
        $r = $conn->query($sql);
        if (!$r) { return 0; }
        $x = $r->fetch_row();
        return $x ? $x[0] : 0;
    }

    private static function exec(\mysqli $conn, $sql, $types, array $vals)
    {
        $st = $conn->prepare($sql);
        if (!$st) { return 0; }
        $st->bind_param($types, ...$vals);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        return max(0, $n);
    }

    /** ◆ حارسُ الانزياح — حرفٌ زائدٌ في سلسلةِ الأنواعِ يكتب صامتًا في الخانةِ الخطأ. */
    private static function assertArity($types, array $vals, $label)
    {
        if (strlen($types) !== count($vals)) {
            throw new \LengthException(sprintf(
                'انزياحُ وسائطٍ في %s — أنواع %d · قيم %d', $label, strlen($types), count($vals)));
        }
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }
}
