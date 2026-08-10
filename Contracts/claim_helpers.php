<?php
/**
 * Contracts/claim_helpers.php — المستخلص: من الوحدة المعتمدة إلى ذمّة العميل
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-08 §5.2 و§8.2 · FES §8 (المبيعات: «الإيرادُ من المستخلص المعتمد ← الفاتورةُ
 * الضريبية ← الذمة»). الرحلةُ أربعُ خطواتٍ لا واحدة، ولكلٍّ معناها:
 *   المستخلصُ **مطالبة** · الفاتورةُ **مستندٌ ضريبي** · الذمّةُ **دَينٌ يُطارَد**.
 *
 * **الفجوة التي يسدّها** (مقيسة 2026-07-27): عشرةُ أحداثِ إيرادٍ بـ9,290,900 كلُّها
 * بلا عميلٍ وبلا ذمّة — فالنظامُ يسجّل إيرادًا لا يعرف ممّن يُحصَّل.
 *
 * **سلسلةُ العميل** (بيان المالك، متحقَّقٌ على 359 صفًّا بلا استثناء):
 *     timesheet.operator → operations.contract_id/project_id → project.client_id
 * **والتسعير** من `contractequipments` بمطابقة (العقد × نوع المعدة) — وهو **مصدرُ
 * المروحة حرفيًّا** (EffectFanout::resolveTimesheet) فلا يفترق المستخلصُ عن الإيراد.
 *
 * صفرُ إدخالِ كمية: البنودُ تُشتق كلُّها؛ والمُدخَلُ وحده الفترةُ والعقد.
 */

if (!defined('CLAIM_HELPERS_INCLUDED')) { define('CLAIM_HELPERS_INCLUDED', true); }

if (!function_exists('claim_states')) {
    /** دورةُ حياة المستخلص (UX-08 §8.2). */
    function claim_states()
    {
        return array(
            'draft'     => 'مسودة',
            'review'    => 'قيد المراجعة',
            'approved'  => 'معتمد',
            'invoiced'  => 'مفوتر',
            // M-05 (ENT-03 §4): «Invoiced → **PartiallyCollected** → Collected»
            'partially_collected' => 'محصَّلٌ جزئيًّا',
            'collected' => 'محصَّل',
            'cancelled' => 'ملغى',
        );
    }
}

if (!function_exists('claim_gate')) {
    function claim_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('claims super view') : $gate;
    }
}

if (!function_exists('claim_next_no')) {
    /** ترقيمٌ خادميٌّ CLM-سنة-تسلسل لكل شركة (فريدٌ بقيد uq_claim_no). */
    function claim_next_no($gate)
    {
        $year = date('Y');
        $rows = $gate->select('claims', array(
            'columns'  => array('claim_no'),
            'whereRaw' => "claim_no LIKE ?",
            'params'   => array('CLM-' . $year . '-%'),
            'orderBy'  => 'id DESC', 'limit' => 1,
        ));
        $seq = 1;
        if ($rows && preg_match('~-(\d+)$~', (string) $rows[0]['claim_no'], $m)) {
            $seq = intval($m[1]) + 1;
        }
        return 'CLM-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('claim_contract_context')) {
    /**
     * سياقُ العقد: العميلُ والمشروعُ والعملة — مشتقٌّ لا مُدخل.
     *
     * **العملة تُطبَّع عند الحدّ**: طبقةُ العقود تخزّن الاسمَ العربي («دولار»)
     * وطبقةُ المال تخزّن الرمزَ المعياري (`USD`) — وهو فصلٌ مقصودٌ قائمٌ منذ
     * نشأة المروحة (`EffectFanout::CONTRACT_CURRENCY`). فتمريرُ اسمِ العقد خامًا
     * إلى `claims` ثم إلى `fin_dues` كان يفتح ذمّةً بعملةٍ لا يعرفها الدفتر،
     * فلا يصحّ جمعُها مع شيء. والافتراضُ الصامت `'SDG'` كان أسوأ: عقدٌ بالدولار
     * يُفوتَر بالجنيه بلا أثرٍ ظاهر.
     *
     * والعملةُ غيرُ المعروفة تُترك **null** — تعذّرٌ معلَنٌ يوقف الاعتماد،
     * لا قيمةٌ مفترَضةٌ تمضي.
     *
     * @return array|null ('client_id','project_id','currency','client_name','project_name')
     */
    function claim_contract_context($gate, $contract_id)
    {
        require_once dirname(__DIR__) . '/includes/fx.php';

        $rows = $gate->scopedQuery(
            array('scope' => array('c' => 'contracts'),
                  'enrich' => array('p' => 'project', 'cl' => 'clients')),
            "SELECT c.id, c.project_id, c.price_currency_contract AS cur,
                    p.client_id, p.name AS project_name, cl.client_name
               FROM contracts c
               LEFT JOIN project p ON p.id = c.project_id
               LEFT JOIN clients cl ON cl.id = p.client_id
              WHERE {TENANT_SCOPE} AND c.id = ? AND COALESCE(c.is_deleted,0) = 0",
            array(intval($contract_id)));
        if (!$rows) { return null; }
        $r = $rows[0];
        return array(
            'client_id'    => ($r['client_id'] !== null) ? intval($r['client_id']) : null,
            'project_id'   => ($r['project_id'] !== null) ? intval($r['project_id']) : null,
            'currency'     => ems_fx_code($r['cur']),
            'currency_raw' => (string) ($r['cur'] ?? ''),
            'client_name'  => (string) ($r['client_name'] ?? ''),
            'project_name' => (string) ($r['project_name'] ?? ''),
        );
    }
}

if (!function_exists('claim_billable_units')) {
    /**
     * الوقائعُ القابلةُ للاستخلاص لعقدٍ في فترة — **من قيود الإيراد المعترَف بها**.
     * ─────────────────────────────────────────────────────────────────────
     * ⚠️ **أُعيد بناؤها 2026-07-28 على قرار المالك «المروحةُ تعترف والمستخلصُ
     * يفوتر»**. كانت تقرأ الدوامَ الخام بشرط `ts.status=1` وحده، فأورثت فجوتين
     * قِيستا بالبرهان (`tests/unit_chain_e2e_proof.php`):
     *   ① **ذمّةٌ بلا حارس**: يومٌ **بصفر اعتماد** استُخلص بـ17,500 وفُتحت به
     *      ذمّةُ عميلٍ — بينما مستحقُّ المورد والمشغّل محجوزٌ بختم المالية.
     *   ② **تفاوتُ التسعير**: كانت تسعّر من `executed_hours` الخام والمروحةُ
     *      تسعّر من الكمية **المحكومة** (`EMS_AWARD_AUTHORITY`)، فسياسةُ ساعةٍ
     *      بـ`pct` تُفرِّق المستخلصَ عن قيد الإيراد لليوم نفسِه.
     *
     * والمصدرُ الآن **قيدُ الإيراد نفسُه** (`fin_event_links` ← `fin_financial_events`):
     *   • **حارسٌ بلا حارسٍ جديد**: وجودُ القيد يعني أن اليومَ اكتمل اعتمادُه
     *     الرباعي **وحوّلته المالية** — فيرث المستخلصُ بوابةَ التحويل ولا يُبنى
     *     له بابٌ ثانٍ يتناقض معها (نفسُ مبدأ «الطابور استنتاجٌ لا حقلُ حالة»).
     *   • **مصدرٌ واحدٌ للرقم**: الكميةُ والمبلغُ والوحدةُ والعملةُ من القيد كما
     *     وقع — فلا يمكن بنيويًّا أن يفترق المستخلصُ عن الإيراد.
     * ويُستبعد ما استُخلص سلفًا (في أي مستخلصٍ غيرِ ملغى) — فلا وحدةَ مرتين.
     */
    function claim_billable_units($gate, $contract_id, $from, $to, $exclude_claim_id = 0)
    {
        // **گوتشا البوابة**: `NOT EXISTS` على جدولٍ مستأجرٍ مرفوضٌ بنيويًّا
        // («جدول الإثراء يُربط LEFT JOIN حصرًا») — فاستبعادُ المستخلَص سلفًا
        // يتم بـLEFT JOIN وشرطِ `IS NULL`، وهو أسرعُ أيضًا. والمربوطُ INNER
        // (شرطُ وجودٍ لا إثراء) موضعُه `scope` — وبه يُعزل بشركته أيضًا.
        return $gate->scopedQuery(
            array('scope' => array('ts' => 'timesheet', 'l' => 'fin_event_links',
                                   'fe' => 'fin_financial_events'),
                  'enrich' => array('o' => 'operations', 'cll' => 'claim_lines', 'clh' => 'claims')),
            "SELECT ts.id AS source_ref, ts.date AS work_date,
                    o.equipment AS equipment_ref, o.equipment_type,
                    fe.id AS event_id, fe.quantity AS qty, fe.unit AS unit_type,
                    fe.amount AS amount, fe.currency AS currency
               FROM timesheet ts
               JOIN fin_event_links l
                    ON l.parent_kind = 'timesheet' AND l.parent_ref = ts.id
                   AND l.effect_type = 'revenue_event'
               JOIN fin_financial_events fe
                    ON fe.id = l.target_id AND fe.event_type = 'revenue'
                   AND COALESCE(fe.is_deleted,0) = 0
               LEFT JOIN operations o ON o.id = ts.operator
               LEFT JOIN claim_lines cll
                      ON cll.source_kind = 'timesheet' AND cll.source_ref = ts.id
               LEFT JOIN claims clh
                      ON clh.id = cll.claim_id AND clh.state <> 'cancelled'
                     AND COALESCE(clh.is_deleted,0) = 0 AND clh.id <> ?
              WHERE {TENANT_SCOPE}
                AND o.contract_id = ?
                AND ts.date BETWEEN ? AND ?
                AND ts.status = 1
                AND clh.id IS NULL
              ORDER BY ts.date ASC, ts.id ASC",
            array(intval($exclude_claim_id), intval($contract_id), $from, $to));
    }
}

if (!function_exists('claim_already_billed_units')) {
    /**
     * M-08 (ENT-03 §7): الوحداتُ المفوترةُ سابقًا في الفترة — **بمرجع سطرها**.
     * كانت تُستبعد صامتةً (`clh.id IS NULL` في claim_billable_units)، والمعيارُ:
     * «وحدةٌ فُوترت سابقًا → 409 بمرجع سطرها · صفرُ ازدواجٍ في الإيراد».
     *
     * @return array[] {source_ref, work_date, claim_line_id, claim_id, claim_no}
     */
    function claim_already_billed_units($gate, $contract_id, $from, $to)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('ts' => 'timesheet', 'cll' => 'claim_lines', 'clh' => 'claims'),
                      'enrich' => array('o' => 'operations')),
                "SELECT ts.id AS source_ref, ts.date AS work_date,
                        cll.id AS claim_line_id, clh.id AS claim_id, clh.claim_no
                   FROM timesheet ts
                   JOIN claim_lines cll
                        ON cll.source_kind = 'timesheet' AND cll.source_ref = ts.id
                   JOIN claims clh
                        ON clh.id = cll.claim_id AND clh.state <> 'cancelled'
                       AND COALESCE(clh.is_deleted,0) = 0
                   LEFT JOIN operations o ON o.id = ts.operator
                  WHERE {TENANT_SCOPE}
                    AND o.contract_id = ?
                    AND ts.date BETWEEN ? AND ?
                    AND ts.status = 1
                  ORDER BY ts.date ASC, ts.id ASC",
                array(intval($contract_id), $from, $to));
        } catch (\Throwable $t) {
            error_log('claim_already_billed_units: ' . $t->getMessage());
            return array();
        }
    }
}

if (!function_exists('claim_unbilled_scope')) {
    /**
     * وصلاتُ «الجاهز للفوترة ولم يُفوتر» وشرطُها — **تعريفٌ واحدٌ في موضعٍ واحد**.
     * ─────────────────────────────────────────────────────────────────────
     * هذه هي `claim_billable_units` نفسُها بلا قيدِ عقدٍ ولا فترة: قيدُ الإيراد
     * شرطُ وجود (فيرث المستخلصُ بوابةَ التحويل)، والمستخلَصُ سلفًا يُستبعد
     * بـLEFT JOIN و`IS NULL` (گوتشا البوابة: `NOT EXISTS` مرفوضٌ بنيويًّا).
     *
     * والعدّادُ والقائمةُ يبنيان منها معًا — فلا يفترق رقمٌ يراه المستخدم في
     * اللوحة عن الصفوف التي ينقر إليها (UX-01 §10.3 «لكل مصدرٍ تعريفٌ واحد»).
     * ⚠️ لا يُستنسخ هذا الشكلُ في موضعٍ ثالث: نسخةٌ ثانيةٌ = انفصامٌ مؤجَّل.
     *
     * @return array{scope: array, from: string}
     */
    function claim_unbilled_scope()
    {
        return array(
            'scope' => array('ts' => 'timesheet', 'l' => 'fin_event_links',
                             'fe' => 'fin_financial_events'),
            'enrich' => array('o' => 'operations', 'cll' => 'claim_lines',
                              'clh' => 'claims', 'ct' => 'contracts',
                              'p' => 'project', 'cn' => 'clients'),
            'from' => "FROM timesheet ts
                       JOIN fin_event_links l
                            ON l.parent_kind = 'timesheet' AND l.parent_ref = ts.id
                           AND l.effect_type = 'revenue_event'
                       JOIN fin_financial_events fe
                            ON fe.id = l.target_id AND fe.event_type = 'revenue'
                           AND COALESCE(fe.is_deleted,0) = 0
                       LEFT JOIN operations o ON o.id = ts.operator
                       LEFT JOIN claim_lines cll
                              ON cll.source_kind = 'timesheet' AND cll.source_ref = ts.id
                       LEFT JOIN claims clh
                              ON clh.id = cll.claim_id AND clh.state <> 'cancelled'
                             AND COALESCE(clh.is_deleted,0) = 0
                       LEFT JOIN contracts ct ON ct.id = o.contract_id
                       LEFT JOIN project p    ON p.id = ct.project_id
                       LEFT JOIN clients cn   ON cn.id = p.client_id
                      WHERE {TENANT_SCOPE}
                        AND ts.status = 1
                        AND clh.id IS NULL
                        AND o.contract_id IS NOT NULL",
        );
    }
}

if (!function_exists('claim_unbilled_summary')) {
    /**
     * الجاهزُ للفوترة ولم يُفوتر — مجمَّعًا **بالعقد وعملته**.
     *
     * ⚠️ **العملةُ في المجموعة لا في الدالة** (مقيسٌ 2026-07-29): العقد 2 وحدَه
     * فيه يومان بعملتين — 1600 SDG و400 USD. فجمعُهما في رقمٍ واحد (2000) رقمٌ
     * لا معنى له، و**التوحيدُ إلى الأساس غيرُ متاحٍ بعد**: سعرُ SDG→USD لم
     * يُدخَل (362 صفًّا تنتظره في سجل الأسعار). فالصفُّ لكل عملةٍ على حدة —
     * إعلانُ الحقيقة كما هي أصدقُ من مجموعٍ يبدو نظيفًا وهو خطأ.
     *
     * وعدُّ الأيام `COUNT(DISTINCT ts.id)` لا `COUNT(*)`: يومٌ بقيدَي إيرادٍ
     * يُعدُّ يومًا واحدًا.
     *
     * @return array[] contract_id · currency · days · amount · first_date
     *                 · last_date · project_name · client_name
     */
    function claim_unbilled_summary($gate)
    {
        $d = claim_unbilled_scope();
        try {
            return $gate->scopedQuery(
                array('scope' => $d['scope'], 'enrich' => $d['enrich']),
                "SELECT o.contract_id AS contract_id,
                        fe.currency AS currency,
                        COUNT(DISTINCT ts.id) AS days,
                        ROUND(SUM(fe.amount), 2) AS amount,
                        MIN(ts.date) AS first_date,
                        MAX(ts.date) AS last_date,
                        MAX(p.name) AS project_name,
                        MAX(cn.client_name) AS client_name
                 {$d['from']}
                  GROUP BY o.contract_id, fe.currency
                  ORDER BY o.contract_id DESC, amount DESC", array());
        } catch (\Throwable $t) {
            error_log('claim_unbilled_summary: ' . $t->getMessage());
            return array();
        }
    }
}

if (!function_exists('claim_unbilled_days')) {
    /**
     * عددُ **أيام العمل** الجاهزة للفوترة ولم تُفوتر — رقمُ تنبيه اللوحة.
     *
     * عدٌّ مستقلٌّ لا مجموعُ صفوف `claim_unbilled_summary`: تلك مجمَّعةٌ بالعملة
     * أيضًا، فيومٌ بعملتين يظهر في صفّين ويُحسب مرتين لو جُمع. والوصلاتُ
     * والشرطُ من `claim_unbilled_scope()` نفسِها — فالتعريفُ واحدٌ والعدُّ دقيق.
     */
    function claim_unbilled_days($gate)
    {
        $d = claim_unbilled_scope();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => $d['scope'], 'enrich' => $d['enrich']),
                "SELECT COUNT(DISTINCT ts.id) AS n {$d['from']}", array());
            return isset($rows[0]['n']) ? intval($rows[0]['n']) : 0;
        } catch (\Throwable $t) {
            error_log('claim_unbilled_days: ' . $t->getMessage());
            return 0;
        }
    }
}

if (!function_exists('claim_pending_states')) {
    /**
     * حالاتُ «المطالبةِ المعلَّقة» — ما لم يبلغ الاعتمادَ بعد.
     * `draft` عملُ المبيعات لم يُرفع · `review` رُفع وينتظر إجازةَ المالية.
     * وكلاهما معلَّقٌ من زاوية المبيعات: الأولُ ينتظر يدَه، والثاني ينتظر ردًّا.
     * والمعتمَدُ والمفوترُ والمحصَّلُ والملغى خارجَ العدّ — انتهى انتظارُها.
     */
    function claim_pending_states() { return array('draft', 'review'); }
}

if (!function_exists('claim_pending_count')) {
    /** عددُ المستخلصات المعلَّقة — تعريفٌ واحدٌ للّوحة وللشاشة. */
    function claim_pending_count($gate)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('cl' => 'claims')),
                "SELECT COUNT(*) AS n FROM claims cl
                  WHERE {TENANT_SCOPE} AND COALESCE(cl.is_deleted,0) = 0
                    AND cl.state IN ('draft','review')", array());
            return isset($rows[0]['n']) ? intval($rows[0]['n']) : 0;
        } catch (\Throwable $t) {
            error_log('claim_pending_count: ' . $t->getMessage());
            return 0;
        }
    }
}

if (!function_exists('claim_period_diagnosis')) {
    /**
     * تشخيصُ فترةٍ لم تُنتج بنودًا — **أين وقف اليومُ في السلسلة؟**
     * ─────────────────────────────────────────────────────────────────────
     * «لا حدودَ صامتة»: رسالةُ «لا يومَ محوَّلًا» وحدَها لا تفرّق بين أربع حالاتٍ
     * مختلفةِ العلاج (لا يومَ أصلًا · لم يكتمل اعتمادُه · بانتظار المالية ·
     * حُوِّل بلا قيدِ إيراد · استُخلص سلفًا) — فتُعدّ هنا كلُّها بأسمائها.
     * قراءةٌ خالصةٌ للعرض لا يبني عليها قرار.
     */
    function claim_period_diagnosis($gate, $contract_id, $from, $to)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('ts' => 'timesheet'),
                      'enrich' => array('o' => 'operations', 'a4' => 'timesheet_approvals',
                                        'lr' => 'fin_event_links', 'lx' => 'fin_event_links',
                                        'cll' => 'claim_lines')),
                "SELECT COUNT(*) total,
                        COUNT(a4.id) approved4,
                        COUNT(lx.id) converted,
                        COUNT(lr.id) with_revenue,
                        COUNT(cll.id) claimed
                   FROM timesheet ts
                   LEFT JOIN operations o ON o.id = ts.operator
                   LEFT JOIN timesheet_approvals a4
                          ON a4.timesheet_id = ts.id AND a4.approval_level = 4 AND a4.status = 1
                   LEFT JOIN fin_event_links lr
                          ON lr.parent_kind = 'timesheet' AND lr.parent_ref = ts.id
                         AND lr.effect_type = 'revenue_event'
                   LEFT JOIN fin_event_links lx
                          ON lx.parent_kind = 'timesheet' AND lx.parent_ref = ts.id
                         AND lx.effect_type = 'cost_record'
                   LEFT JOIN claim_lines cll
                          ON cll.source_kind = 'timesheet' AND cll.source_ref = ts.id
                  WHERE {TENANT_SCOPE} AND o.contract_id = ?
                    AND ts.date BETWEEN ? AND ? AND ts.status = 1",
                array(intval($contract_id), $from, $to));
        } catch (\Throwable $t) {
            error_log('claim diagnosis: ' . $t->getMessage());
            return '';
        }
        if (!$rows) { return ''; }
        $d = $rows[0];
        $total = intval($d['total']);
        if ($total === 0) { return 'لا يومَ عملٍ مسجَّلًا لهذا العقد في الفترة أصلًا'; }

        $parts = array();
        $notApproved = $total - intval($d['approved4']);
        $awaiting = intval($d['approved4']) - intval($d['converted']);
        $noRevenue = intval($d['converted']) - intval($d['with_revenue']);
        if ($notApproved > 0) { $parts[] = $notApproved . ' يومًا لم يكتمل اعتمادُه الرباعي'; }
        if ($awaiting > 0)    { $parts[] = $awaiting . ' مكتملَ الاعتماد بانتظار تحويل المالية من «وحدات الأطراف»'; }
        if ($noRevenue > 0)   { $parts[] = $noRevenue . ' حُوِّل بلا قيدِ إيراد (لا سعرَ عقدِ عميلٍ لنوع المعدة أو لا كميةَ بوحدته)'; }
        if (intval($d['claimed']) > 0) { $parts[] = intval($d['claimed']) . ' استُخلص سلفًا'; }
        return $parts ? ('في الفترة ' . $total . ' يومًا: ' . implode(' · ', $parts)) : '';
    }
}

if (!function_exists('claim_generate')) {
    /**
     * توليدُ مستخلص الفترة — صفرُ إدخالِ كمية (UX-08 §5.2 · §8.2 «— → Draft»).
     * @return array ('status' => created|exists|empty|failed, 'claim_id','lines','gross','reason')
     */
    function claim_generate($conn, $contract_id, $from, $to, $uid = 0)
    {
        $out = array('status' => 'failed', 'claim_id' => null, 'lines' => 0, 'gross' => 0.0, 'reason' => '');
        $contract_id = intval($contract_id);
        if ($contract_id <= 0 || $from === '' || $to === '') {
            $out['reason'] = 'العقدُ والفترةُ إلزاميان'; $out['status'] = 'failed'; return $out;
        }
        if (strtotime($to) < strtotime($from)) {
            $out['reason'] = 'نهايةُ الفترة قبل بدايتها'; $out['status'] = 'failed'; return $out;
        }

        $gate = claim_gate(false);

        // مستخلصٌ واحدٌ لكل (عقد × فترة) — إعادةُ التوليد تعيد المرجعَ القائم
        try {
            $ex = $gate->selectOne('claims', array(
                'whereRaw' => "contract_id = ? AND period_from = ? AND period_to = ?",
                'params'   => array($contract_id, $from, $to),
            ));
            if ($ex) {
                $out['status'] = 'exists';
                $out['claim_id'] = intval($ex['id']);
                $out['reason'] = 'مستخلصُ الفترة قائمٌ بالرقم ' . $ex['claim_no'];
                return $out;
            }
        } catch (\Throwable $t) {
            error_log('claim exists check: ' . $t->getMessage());
            $out['reason'] = 'تعذّر الفحص'; return $out;
        }

        $ctx = claim_contract_context($gate, $contract_id);
        if ($ctx === null) { $out['reason'] = 'العقدُ غير موجود'; return $out; }

        // تعذّرٌ معلَن: عملةُ العقد لا رمزَ مسجَّلًا لها. لا يُستخلص بعملةٍ
        // مجهولةٍ ولا بعملةٍ مفترَضة — تُسجَّل العملةُ من شاشتها ثم يُعاد التوليد.
        if ($ctx['currency'] === null) {
            $out['reason'] = ($ctx['currency_raw'] === '')
                ? 'العقدُ بلا عملة — سجّلها في بياناته أولًا'
                : 'عملةُ العقد «' . $ctx['currency_raw'] . '» غير مسجَّلةٍ في سجل العملات';
            return $out;
        }

        try {
            $units = claim_billable_units($gate, $contract_id, $from, $to);
        } catch (\Throwable $t) {
            error_log('claim units: ' . $t->getMessage());
            $out['reason'] = 'تعذّرت قراءةُ الوحدات'; return $out;
        }

        // البندُ = قيدُ إيرادٍ معترَفٌ به. كميتُه ومبلغُه كما وقعا في الدفتر،
        // وسعرُ الوحدة مشتقٌّ منهما (المبلغ ÷ الكمية) — فلا رقمَ ثانيًا يُحسب
        // هنا فيفترق. واختلافُ العملة تعذّرٌ معلَن: لا يُجمع جنيهٌ فوق دولار.
        $lines = array(); $gross = 0.0; $curMismatch = 0; $zeroQty = 0;
        foreach ($units as $u) {
            $qty = (float) $u['qty'];
            $amount = round((float) $u['amount'], 2);
            if ($qty <= 0 || $amount <= 0) { $zeroQty++; continue; }
            $lineCur = trim((string) ($u['currency'] ?? ''));
            if ($lineCur !== '' && $lineCur !== (string) $ctx['currency']) { $curMismatch++; continue; }
            $gross += $amount;
            $lines[] = array(
                'source_kind'   => 'timesheet',
                'source_ref'    => intval($u['source_ref']),
                'event_id'      => intval($u['event_id']),   // مرجعُ الاعتراف — لا إيرادٌ ثانٍ
                'work_date'     => $u['work_date'],
                'equipment_ref' => (string) ($u['equipment_ref'] ?? ''),
                'unit_type'     => (string) ($u['unit_type'] ?: 'hour'),
                'qty'           => $qty,
                'unit_price'    => round($amount / $qty, 2),
                'amount'        => $amount,
            );
        }

        // ── CON-02 §6 (ق-7 · ق-8): بنودُ الجزاء والحافز والحد الأدنى ──────────
        //    تُقرأ من احتساباتٍ **نُشرت قيودُها في الدفتر** — لا بندَ مستخلصٍ
        //    معزولٍ بلا قيد. فكلُّ بندٍ هنا له `event_id` يشير إلى قيده، تمامًا
        //    كبنود الوحدات. والغرامةُ تدخل **بمبلغٍ سالبٍ ظاهرٍ باسمه** لا خصمًا
        //    صامتًا (§6)، فيراه العميلُ في مستخلصه ويعرف ممّ خُصم.
        $penLines = array(); $penSkipped = 0;
        if (function_exists('claim_penalty_lines')) {
            foreach (claim_penalty_lines($gate, $contract_id, $from, $to) as $pl) {
                $plCur = trim((string) ($pl['currency'] ?? ''));
                if ($plCur !== '' && $plCur !== (string) $ctx['currency']) { $penSkipped++; continue; }
                unset($pl['currency']);
                $gross += (float) $pl['amount'];
                $penLines[] = $pl;
            }
        }
        $lines = array_merge($lines, $penLines);

        if (!$lines) {
            // تشخيصٌ يقول **أين وقف اليوم** بدل رسالةٍ عامةٍ تُترك للتخمين
            $diag = claim_period_diagnosis($gate, $contract_id, $from, $to);
            // M-08 (ENT-03 §7): وحدةٌ فُوترت سابقًا → 409 **بمرجع سطرها** لا
            // استبعادٌ صامتٌ يوهم أن الفترةَ فارغة.
            $billed = claim_already_billed_units($gate, $contract_id, $from, $to);
            if (!empty($billed)) {
                $refs = array();
                foreach (array_slice($billed, 0, 5) as $b) {
                    $refs[] = 'اليوم #' . $b['source_ref'] . ' (' . $b['work_date'] . ') في '
                            . $b['claim_no'] . ' سطر #' . $b['claim_line_id'];
                }
                $out['status'] = 'conflict';
                $out['code'] = 409;
                $out['billed_conflicts'] = $billed;
                $out['reason'] = '409: ' . count($billed) . ' وحدةً في الفترة فُوترت سابقًا — '
                    . implode(' · ', $refs)
                    . (count($billed) > 5 ? ' · …' : '')
                    . ' — صفرُ ازدواجٍ في الإيراد';
                return $out;
            }
            $out['status'] = 'empty';
            $out['reason'] = 'لا يومَ قابلًا للاستخلاص'
                . ($diag !== '' ? (' — ' . $diag) : '')
                . ($curMismatch > 0 ? (' · ' . $curMismatch . ' بعملةٍ تخالف عملة العقد') : '')
                . ($zeroQty > 0 ? (' · ' . $zeroQty . ' بكميةٍ أو مبلغٍ صفري') : '');
            return $out;
        }

        $gross = round($gross, 2);
        try {
            $claimId = null;
            $gate->runInTransaction(function ($g) use (&$claimId, $gate, $contract_id, $ctx, $from, $to, $gross, $lines, $uid) {
                $claimId = intval($g->insert('claims', array(
                    'claim_no'     => claim_next_no($g),
                    'contract_id'  => $contract_id,
                    'client_id'    => $ctx['client_id'],
                    'project_id'   => $ctx['project_id'],
                    'period_from'  => $from,
                    'period_to'    => $to,
                    'currency'     => $ctx['currency'],
                    'gross_amount' => $gross,
                    'net_amount'   => $gross,
                    'state'        => 'draft',
                    'created_by'   => intval($uid) > 0 ? intval($uid) : null,
                )));
                foreach ($lines as $ln) {
                    $ln['claim_id'] = $claimId;
                    $g->insert('claim_lines', $ln);
                }
            }, 'claim generate');
            $out['status'] = 'created';
            $out['claim_id'] = $claimId;
            $out['lines'] = count($lines);
            $out['gross'] = $gross;
            $skips = array();
            if ($curMismatch > 0) { $skips[] = $curMismatch . ' بعملةٍ تخالف عملة العقد'; }
            if ($zeroQty > 0)     { $skips[] = $zeroQty . ' بكميةٍ أو مبلغٍ صفري'; }
            if ($penSkipped > 0)  { $skips[] = $penSkipped . ' جزاءً/حافزًا بعملةٍ مخالفة'; }
            // M-08: المفوترةُ سابقًا تُعلَن بعدّها ومراجعها — لا استبعادَ صامتًا
            $billedPartial = claim_already_billed_units($gate, $contract_id, $from, $to);
            if (!empty($billedPartial)) {
                $out['billed_conflicts'] = $billedPartial;
                $skips[] = count($billedPartial) . ' وحدةً فُوترت سابقًا (لم تُكرَّر — 409 لكلٍّ بمرجع سطرها)';
            }
            if ($skips) { $out['reason'] = 'تُركت: ' . implode(' · ', $skips); }
        } catch (\Throwable $t) {
            error_log('claim generate: ' . $t->getMessage());
            $out['reason'] = 'تعذّر الحفظ';
        }
        return $out;
    }
}

if (!function_exists('claim_penalty_lines')) {
    /**
     * بنودُ الجزاء والحافز والحد الأدنى + الاستقطاعات (CON-02 §6 · ق-19).
     * ─────────────────────────────────────────────────────────────────────
     * **كلُّها بنودٌ ظاهرةٌ باسمها** — لا خصمٌ صامت (§6). والغرامةُ والاستقطاعُ
     * يدخلان بمبلغٍ **سالب**، فيقرأ العميلُ في مستخلصه ممّ خُصم ولماذا.
     *
     * ⚠️ **الاستقطاعان لا يُكتبان في `claims.retention_amount`**: `claim_recalc`
     *    يحسب الصافي = Σ البنود − `retention_amount`، فلو كُتب الاستقطاعُ في
     *    الاثنين لخُصم مرتين. والبندُ الظاهرُ هو ما تنصّ عليه ق-19، فيُترك
     *    العمودُ صفرًا ويُقرأ الرصيدُ التراكميُّ بجمع بنود `retention` للعقد.
     */
    /**
     * @param int $exclude_claim_id مستخلصٌ يُستثنى من حساب المستهلَك من الدفعة
     *   المقدَّمة — لإعادة توليدٍ لا تعدّ بنودَ نفسِها استهلاكًا سابقًا. صفرٌ عند
     *   التوليد الأول (المستخلصُ لم يُنشأ بعد).
     */
    function claim_penalty_lines($gate, $contract_id, $from, $to, $exclude_claim_id = 0)
    {
        $lines = array();

        // ① الجزاءاتُ والحوافزُ والحدُّ الأدنى — المنشورةُ قيودُها وحدَها
        if (class_exists('\\App\\Services\\Contract\\PenaltyService')) {
            $lines = \App\Services\Contract\PenaltyService::claimLinesFor($gate, $contract_id, $from, $to);
        }

        // ② الاستقطاعان الآليّان (ق-19) — نسبةٌ من مجمل ما استُحقّ في الفترة
        $c = $gate->selectOne('contracts', array(
            'columns' => array('id', 'retention_pct', 'advance_recovery_pct'),
            'where'   => array('id' => intval($contract_id)),
        ));
        if (!$c) { return $lines; }

        $retPct = ($c['retention_pct'] === null) ? 0.0 : (float) $c['retention_pct'];
        $advPct = ($c['advance_recovery_pct'] === null) ? 0.0 : (float) $c['advance_recovery_pct'];
        if ($retPct <= 0 && $advPct <= 0) { return $lines; }

        // الأساسُ: ما استُحقّ فعلًا في الفترة (وحداتٌ + جزاءاتٌ) قبل الاستقطاع
        $units = claim_billable_units($gate, $contract_id, $from, $to);
        $base = 0.0;
        foreach ($units as $u) { $base += (float) $u['amount']; }
        foreach ($lines as $l) { $base += (float) $l['amount']; }
        $base = round($base, 2);
        if ($base <= 0) { return $lines; }

        $cur = '';
        foreach ($lines as $l) { if (!empty($l['currency'])) { $cur = $l['currency']; break; } }
        if ($cur === '' && !empty($units)) { $cur = (string) ($units[0]['currency'] ?? ''); }

        if ($retPct > 0) {
            $amt = round($base * $retPct / 100, 2);
            if ($amt > 0) {
                $lines[] = array(
                    'source_kind' => 'retention', 'source_ref' => intval($contract_id),
                    'event_id' => null, 'work_date' => $to, 'equipment_ref' => '',
                    'unit_type' => 'retention', 'qty' => 1,
                    'unit_price' => -$amt, 'amount' => -$amt, 'currency' => $cur,
                );
            }
        }
        // ── M-01: الاستردادُ محكومٌ برصيدٍ وسقف (2026-07-29) ────────────────
        // كان هنا `round($base * $advPct / 100, 2)` **بلا رصيدٍ ولا سقفٍ ولا
        // قبضٍ ابتدائي**، فيتكرر الخصمُ إلى الأبد حتى بعد تصفية الدفعة — بل
        // ويقع على عقدٍ **لم تُقبض له دفعةٌ أصلًا** (النظامُ يسترد دَينًا لم
        // يُقرضه؛ العقد 5: 12.50 مخصومةٌ وصفرُ قبضٍ مسجَّل).
        //
        // والآن: الأقلُّ من (النسبة · الرصيد)، **وصفرٌ إن لا رصيد** ⇒ لا بندَ
        // يُولَّد أصلًا. فالحالةُ الافتراضية (لا قبضَ مسجَّل) توقف النزيفَ فورًا
        // بلا انتظار قرار، ولا تُمسّ المبالغُ المخصومةُ سلفًا (تُرصد في
        // `advance_reconciliation()` وتُقفل بقرار المالك).
        if ($advPct > 0) {
            require_once __DIR__ . '/advance_helpers.php';
            $rec = advance_recovery_due($gate, $contract_id, $base, $advPct, intval($exclude_claim_id));
            $amt = round((float) $rec['due'], 2);
            if ($amt > 0) {
                $lines[] = array(
                    'source_kind' => 'advance_recovery', 'source_ref' => intval($contract_id),
                    'event_id' => null, 'work_date' => $to, 'equipment_ref' => '',
                    'unit_type' => 'advance_recovery', 'qty' => 1,
                    'unit_price' => -$amt, 'amount' => -$amt, 'currency' => $cur,
                );
            }
        }
        return $lines;
    }
}

if (!function_exists('claim_retention_balance')) {
    /**
     * الرصيدُ التراكميُّ للضمان المحتجَز على العقد (ق-19 «مع رصيدٍ تراكميٍّ ظاهر»).
     * وردُّه **قرارٌ يدويٌّ من الدور 19 بعد انتهاء العقد** (ق-20) — فلا أتمتةَ هنا،
     * والدالةُ تعرض ولا تردّ.
     *
     * ⚠️ **والردُّ يدخل الجمعَ نفسَه** ولا يُستثنى منه: بنودُ `retention` **سالبةٌ**
     * (احتجازٌ يُخصم) وبندُ `retention_release` **موجب** (ردٌّ يُضاف) — فصيغةُ
     * `SUM(-amount)` الواحدةُ تُخرج الرصيدَ الصحيحَ للنوعين بلا حالةٍ خاصة:
     * 6.25 محتجَزًا − 6.25 مردودًا = **صفر**. ولو استُثني الردُّ من الجمع لبقيت
     * الشاشةُ تعرض رصيدًا رُدَّ فعلًا — وهو أخطرُ من ألّا تعرض شيئًا.
     */
    function claim_retention_balance($gate, $contract_id)
    {
        $rows = $gate->scopedQuery(array(
            'scope'  => array('cl' => 'claim_lines'),
            'enrich' => array('c' => 'claims'),
        ), "SELECT COALESCE(SUM(-cl.amount),0) held
              FROM claim_lines cl
              LEFT JOIN claims c ON c.id = cl.claim_id
             WHERE {TENANT_SCOPE} AND cl.source_kind IN ('retention','retention_release')
               AND c.contract_id = ?",
            array(intval($contract_id)));
        return $rows ? round((float) $rows[0]['held'], 2) : 0.0;
    }
}

if (!function_exists('claim_retention_release')) {
    /**
     * ردُّ ضمان حسن التنفيذ (ق-20) — **قرارٌ يدويٌّ من الدور 19 بعد انتهاء العقد**.
     * ═══════════════════════════════════════════════════════════════════════════
     * «النظامُ يحتجز ويتراكم ويعرض الرصيد — والردُّ قرارٌ بشريٌّ موثَّقٌ لأنه يتوقف
     * على تسليمٍ ومخالصةٍ لا يراهما النظام» (ق-20).
     *
     * ── لماذا `publishFact` لا `postLedger`؟ (قرارُ المالك 2026-07-28) ─────────
     * **لأن الاحتجازَ لم يدخل الدفترَ أصلًا.** مقيسٌ على العقد 5 حرفيًّا:
     *   • قيودُ الإيراد:  150.00 − 12.25 − 12.80 = **124.95**
     *   • بنودُ المستخلص: 150.00 − 12.25 − 12.80 − 6.25 − 12.50 = **106.20**
     *   • وبندُ `retention` (−6.25) يحمل **`event_id = NULL`**.
     * فالإيرادُ اعتُرف به **كاملًا** في المروحة، والاحتجازُ خصمٌ على مستوى
     * **المطالبة** لا الاعتراف. ولو نُشر الردُّ قيدَ `revenue` لصار إيرادُ العقد
     * 131.20 بدل 124.95 — **تضخيمٌ بمقدار المحتجَز**، وهو من صنف ازدواج
     * 56,000/28,000 الذي أُصلح بقاعدة «المروحةُ تعترف والمستخلصُ يفوتر».
     *
     * فالنمطُ المتَّبَعُ هنا نمطُ `claim_approve` حرفيًّا: **حقيقةٌ محايدةٌ في الجذر
     * بلا إسقاطٍ في الدفتر**، و**المالُ الجديدُ الوحيد هو الذمّةُ** التي تفتحها
     * إجازةُ المستخلص. والبندُ يحمل `event_id = NULL` كنظيرِه السالب تمامًا.
     *
     * ── الحدود ────────────────────────────────────────────────────────────────
     *   `403` غيرُ الدور 19            ·  `409` رُدَّ سلفًا (عطالة)
     *   `422` قبل تجاوز `actual_end`   ·  `422` لا رصيدَ يُردّ
     *
     * **ولا خصمَ للغرامات المعلّقة** (قرارُ المالك): الغرامةُ خُصمت في مستخلصها
     * ببندها الظاهر، فخصمُها ثانيةً من الضمان ازدواجٌ صريح. الردُّ **كاملٌ**.
     *
     * @return array{status:string,code:int,claim_id:?int,amount:float,reason:string}
     */
    function claim_retention_release($conn, $contract_id, $uid, $role, $as_of = null)
    {
        $out = array('status' => 'failed', 'code' => 422, 'claim_id' => null,
                     'amount' => 0.0, 'reason' => '');
        $contract_id = intval($contract_id);
        $as_of = ($as_of === null) ? date('Y-m-d') : $as_of;

        // ① اليد: الدور 19 وحدَه (ق-20 · ق-13)
        $role = strval($role);
        if ($role !== '19' && $role !== '-1') {
            $out['code'] = 403;
            $out['reason'] = 'ردُّ ضمان حسن التنفيذ بيد مدير الإدارة المالية وحدَه (ق-20)';
            return $out;
        }
        if ($contract_id <= 0) { $out['reason'] = 'معرّفُ عقدٍ غير صالح'; return $out; }

        $gate = claim_gate(false);
        $c = null;
        try {
            // ⚠️ گوتشا البوابة: `scopedQuery` **ترفض ذكرَ `company_id` يدويًّا**
            //    («manual company_id refused») — فهي تحقنه بـ{TENANT_SCOPE} وحدَها.
            //    والشركةُ تُقرأ لاحقًا من صفّ المستخلص الذي تكتبه البوابةُ بنفسها
            //    (نظيرُ `claim_approve` تمامًا).
            $rows = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
                "SELECT c.id, c.actual_end FROM contracts c
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0 AND c.id = ? LIMIT 1",
                array($contract_id));
            $c = $rows ? $rows[0] : null;
        } catch (\Throwable $t) {
            error_log('retention release fetch: ' . $t->getMessage());
            $out['reason'] = 'تعذّرت قراءةُ العقد'; return $out;
        }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        // ② الشرطُ الزمني: **بعد تجاوز actual_end حصرًا** — لا قبله ولا في يومه
        $end = (string) $c['actual_end'];
        if ($end === '' || $end === '0000-00-00') {
            $out['reason'] = 'العقدُ بلا تاريخِ انتهاءٍ مسجَّل — ولا يُردُّ ضمانٌ قبل انتهاءٍ معلوم';
            return $out;
        }
        if (strtotime($end) >= strtotime($as_of)) {
            $out['reason'] = 'العقدُ لم ينتهِ بعد (ينتهي ' . $end . ') — والردُّ لا يكون إلا بعد تجاوز مدته (ق-20)';
            return $out;
        }

        // ③ العطالة **قبل الرصيد**: لا يُردُّ ضمانٌ مرتين — البندُ نفسُه هو الحارس.
        //    والترتيبُ مقصود: الردُّ يُصفّر الرصيد، فلو سبقه فحصُ الرصيد لارتدّت
        //    المحاولةُ الثانيةُ بـ«لا رصيدَ» — جوابٌ صحيحٌ حسابيًّا **ومضلِّلٌ
        //    تشخيصيًّا**: يُقرأ «لم يُحتجز شيءٌ قطّ» والحقُّ «رُدَّ سلفًا».
        try {
            $prev = $gate->scopedQuery(array(
                'scope'  => array('cl' => 'claim_lines'),
                'enrich' => array('c' => 'claims'),
            ), "SELECT cl.claim_id FROM claim_lines cl
                LEFT JOIN claims c ON c.id = cl.claim_id
                WHERE {TENANT_SCOPE} AND cl.source_kind = 'retention_release'
                  AND c.contract_id = ? LIMIT 1", array($contract_id));
            if (!empty($prev)) {
                $out['status'] = 'exists'; $out['code'] = 409;
                $out['claim_id'] = intval($prev[0]['claim_id']);
                $out['reason'] = 'رُدَّ الضمانُ سلفًا في المستخلص #' . intval($prev[0]['claim_id'])
                               . ' — ولا يُردُّ ضمانٌ مرتين';
                return $out;
            }
        } catch (\Throwable $t) {
            error_log('retention release dupe: ' . $t->getMessage());
            $out['reason'] = 'تعذّر فحصُ الردّ السابق'; return $out;
        }

        // ④ الرصيد: كاملًا بلا خصمٍ للغرامات المعلّقة (منعُ الازدواج)
        try {
            $held = claim_retention_balance($gate, $contract_id);
        } catch (\Throwable $t) {
            error_log('retention release balance: ' . $t->getMessage());
            $out['reason'] = 'تعذّر حسابُ الرصيد'; return $out;
        }
        if ($held <= 0) {
            $out['reason'] = ($held < 0)
                ? 'الرصيدُ سالبٌ (' . number_format($held, 2) . ') — رُدَّ أكثرُ مما احتُجز، فيُراجَع قبل أي ردٍّ جديد'
                : 'لا رصيدَ ضمانٍ محتجَزٌ على هذا العقد — لا شيءَ يُردّ';
            return $out;
        }

        $ctx = claim_contract_context($gate, $contract_id);
        if ($ctx === null) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }
        if ($ctx['currency'] === null) {
            $out['reason'] = ($ctx['currency_raw'] === '')
                ? 'العقدُ بلا عملة — سجّلها في بياناته أولًا'
                : 'عملةُ العقد «' . $ctx['currency_raw'] . '» غير مسجَّلةٍ في سجل العملات';
            return $out;
        }

        // ⑤ مستخلصٌ ختاميٌّ **جديد** بفترةِ يومِ الانتهاء (قرارُ المالك): الردُّ
        //    حدثٌ مستقلٌّ بتاريخه، ويمرّ بدورة الحياة كاملةً فتُجيزه يدٌ ثانية.
        //    وقيدُ `uq_claim_period` حارسٌ بنيويٌّ مجانيٌّ فوق عطالة ④.
        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        $claimId = null;
        try {
            $gate->runInTransaction(function ($g) use (&$claimId, $contract_id, $ctx, $end, $held, $uid) {
                $claimId = intval($g->insert('claims', array(
                    'claim_no'     => claim_next_no($g),
                    'contract_id'  => $contract_id,
                    'client_id'    => $ctx['client_id'],
                    'project_id'   => $ctx['project_id'],
                    'period_from'  => $end,
                    'period_to'    => $end,
                    'currency'     => $ctx['currency'],
                    'gross_amount' => $held,
                    'net_amount'   => $held,
                    'state'        => 'draft',
                    'notes'        => 'مستخلصٌ ختاميّ — ردُّ ضمان حسن التنفيذ (ق-20)',
                    'created_by'   => intval($uid) > 0 ? intval($uid) : null,
                )));
                $g->insert('claim_lines', array(
                    'claim_id'      => $claimId,
                    'source_kind'   => 'retention_release',
                    'source_ref'    => $contract_id,
                    // `event_id = NULL` كنظيرِه السالب: الردُّ لا يخلق إيرادًا
                    'event_id'      => null,
                    'work_date'     => $end,
                    'equipment_ref' => '',
                    'unit_type'     => 'retention',   // varchar(16) — و«retention_release» يتجاوزه
                    'qty'           => 1,
                    'unit_price'    => $held,
                    'amount'        => $held,
                ));
            }, 'retention release');
        } catch (\Throwable $t) {
            error_log('retention release write #' . $contract_id . ': ' . $t->getMessage());
            $out['code'] = 500; $out['reason'] = 'تعذّر حفظُ المستخلص الختامي';
            return $out;
        }

        // ⑥ الحقيقةُ المحايدة — جذرٌ بلا إسقاط (نمطُ claim_approve)، وبعطالتها
        //    فلا يُدوَّن ردٌّ مرتين ولو استُدعيت الدالةُ مرتين.
        try {
            // الشركةُ من الصفّ الذي كتبته البوابةُ بنفسها — لا من ذكرٍ يدويّ
            $saved = $gate->selectOne('claims', array('where' => array('id' => $claimId)));
            $companyId = ($saved && !empty($saved['company_id'])) ? intval($saved['company_id']) : 0;
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'          => 'retention.released',
                'category'           => 'financial',
                'source_module'      => 'sales',
                'company_id'         => $companyId,
                'entity_type'        => 'contract',
                'entity_id'          => $contract_id,
                'occurred_at'        => gmdate('Y-m-d H:i:s'),
                'created_by'         => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'    => 'retention:release:contract:' . $contract_id,
                'amount'             => $held,
                'currency'           => (string) $ctx['currency'],
                'source_ref'         => 'RET-' . $contract_id,
                'contract_id'        => $contract_id,
                'project_id'         => $ctx['project_id'],
                'customer_entity_id' => $ctx['client_id'],
                'notes'              => 'ردُّ ضمان حسن التنفيذ — العقد #' . $contract_id . ' المنتهي ' . $end,
                'payload'            => array(
                    'contract_id' => $contract_id, 'claim_id' => $claimId,
                    'released'    => $held, 'actual_end' => $end,
                    'released_by' => intval($uid),
                    // شفافيةُ التصنيف: لا اعترافَ جديدًا — الردُّ تحريرُ مطالبةٍ
                    'recognition' => 'none',
                    'rationale'   => 'الاحتجازُ خصمُ مطالبةٍ لا خصمُ اعتراف — فردُّه لا يخلق إيرادًا',
                ),
            ));
        } catch (\Throwable $t) {
            // الحقيقةُ إشهارٌ لا شرطُ صحة — والمستخلصُ الختاميُّ قائمٌ بذاته
            error_log('retention release publish #' . $contract_id . ': ' . $t->getMessage());
        }

        $out['status'] = 'released';
        $out['code'] = 200;
        $out['claim_id'] = $claimId;
        $out['amount'] = $held;
        $out['reason'] = 'رُدَّ ضمانُ حسن التنفيذ كاملًا (' . number_format($held, 2) . ' '
                       . $ctx['currency'] . ') في مستخلصٍ ختاميٍّ مسودة — يُرفع ثم تُجيزه يدٌ ثانية';
        return $out;
    }
}

if (!function_exists('claim_recalc')) {
    /**
     * إعادةُ حساب المجاميع من البنود والاستقطاع — الصافي محسوبٌ لا مُدخل.
     *
     * **M-03 · لا تعديلَ بعد الإصدار** (ENT-03 §6): مستخلصٌ صدرت فاتورتُه
     * الضريبيةُ **تتجمّد أرقامُه** — فتُعاد قيمتُه المحفوظة ولا يُعاد الحساب،
     * والتصحيحُ **بإشعارٍ دائن/مدين** لا بتحريكِ رقمٍ تحت مستندٍ ضريبيٍّ صادر.
     */
    function claim_recalc($gate, $claim_id)
    {
        $claim_id = intval($claim_id);

        require_once __DIR__ . '/../app/Services/Revenue/TaxInvoiceService.php';
        if (\App\Services\Revenue\TaxInvoiceService::assertEditable($gate, $claim_id) !== null) {
            $frozen = $gate->selectOne('claims', array(
                'columns' => array('net_amount'), 'where' => array('id' => $claim_id)));
            return $frozen ? round((float) $frozen['net_amount'], 2) : 0.0;
        }

        $rows = $gate->scopedQuery(array('scope' => array('cl' => 'claim_lines')),
            "SELECT COALESCE(SUM(CASE WHEN cl.dispute_flag = 0 THEN cl.amount ELSE 0 END),0) s
               FROM claim_lines cl WHERE {TENANT_SCOPE} AND cl.claim_id = ?",
            array($claim_id));
        $gross = $rows ? (float) $rows[0]['s'] : 0.0;
        $c = $gate->selectOne('claims', array('where' => array('id' => $claim_id)));
        if (!$c) { return 0.0; }
        $ret = (float) $c['retention_amount'];
        $net = round($gross - $ret, 2);
        $gate->update('claims', array(
            'gross_amount' => round($gross, 2),
            'net_amount'   => $net,
        ), array('id' => $claim_id));
        return $net;
    }
}

if (!function_exists('claim_submit')) {
    /**
     * رفعُ المستخلص للمالية (يدُ المبيعات) — `draft → review`.
     * ─────────────────────────────────────────────────────────────────────
     * قرارُ المالك 2026-07-28: «المبيعاتُ تُنشئ والمالية تعتمد». فالرفعُ إعلانُ
     * فراغِ يد المبيعات من المراجعة، ويُسجَّل باسم رافعه — لأن **من رفع لا يعتمد**.
     * @return array ('status' => submitted|exists|blocked|failed, 'reason')
     */
    function claim_submit($claim_id, $uid = 0)
    {
        $out = array('status' => 'failed', 'reason' => '');
        $claim_id = intval($claim_id);
        if ($claim_id <= 0) { $out['reason'] = 'معرّفٌ غير صالح'; return $out; }
        $gate = claim_gate(false);
        try {
            $c = $gate->selectOne('claims', array('where' => array('id' => $claim_id)));
        } catch (\Throwable $t) {
            error_log('claim submit fetch: ' . $t->getMessage()); return $out;
        }
        if (!$c) { $out['reason'] = 'المستخلصُ غير موجود'; return $out; }
        if ((string) $c['state'] === 'review') {
            $out['status'] = 'exists'; $out['reason'] = 'مرفوعٌ سلفًا وبانتظار المالية'; return $out;
        }
        if ((string) $c['state'] !== 'draft') {
            $out['status'] = 'blocked';
            $out['reason'] = 'لا يُرفع إلا مستخلصٌ مسودة — حالتُه: ' . (string) $c['state'];
            return $out;
        }
        // لا يُرفع فارغٌ ولا سالب: المراجعةُ المالية تستحقّ رقمًا حقيقيًّا
        $net = claim_recalc($gate, $claim_id);
        if ($net <= 0) {
            $out['status'] = 'blocked';
            $out['reason'] = 'الصافي صفرٌ أو سالب — لا يُرفع للمالية';
            return $out;
        }
        try {
            $gate->update('claims', array(
                'state'        => 'review',
                'submitted_by' => intval($uid) > 0 ? intval($uid) : null,
                'submitted_at' => date('Y-m-d H:i:s'),
            ), array('id' => $claim_id));
        } catch (\Throwable $t) {
            error_log('claim submit #' . $claim_id . ': ' . $t->getMessage());
            $out['reason'] = 'تعذّر الرفع'; return $out;
        }
        $out['status'] = 'submitted';
        $out['reason'] = 'رُفع للمالية بصافي ' . number_format($net, 2);
        return $out;
    }
}

if (!function_exists('claim_approve')) {
    /**
     * الإجازةُ المالية: مستخلصٌ مرفوعٌ → فاتورةٌ ضريبيةٌ → **ذمّةُ العميل**.
     * ─────────────────────────────────────────────────────────────────────
     * «ولا فاتورةَ بلا مستخلصٍ معتمد» (UX-08 §5.2). والعطالةُ بمفتاح
     * `claim:{id}` — فاعتمادان لا يفتحان ذمّتين.
     *
     * ⚠️ **لا ينشر إيرادًا (تصحيحُ 2026-07-28 · قرارُ المالك)**: كان ينشر
     * `revenue.claim.approved` بـ`legacy_event_type='revenue'`، فيُسقِط قيدَ
     * إيرادٍ **ثانيًا** عن الساعات التي قيّدتها المروحةُ سلفًا — 56,000 عن عملٍ
     * بـ28,000 (مقيسٌ في `tests/unit_chain_e2e_proof.php`). المفتاحان لا يريان
     * بعضهما (`fanout:ts:{id}:revenue` · `claim:{id}`) فلا عطالةَ تحمي.
     * الآن: **الاعترافُ للمروحة والفوترةُ للمستخلص** — تُنشر حقيقةُ فوترةٍ
     * محايدةٌ بـ`publishFact` (جذرٌ بلا إسقاطٍ في الدفتر)، وبنودُ المستخلص
     * تحمل `event_id` قيودِ الإيراد التي فوتَرتها. والذمّةُ هي المالُ الجديدُ
     * الوحيد الذي يخلقه هذا الفعل.
     *
     * @return array ('status' => approved|exists|blocked|failed, 'invoice_no','receivable_id','reason')
     */
    function claim_approve($conn, $claim_id, $tax_code = null, $uid = 0)
    {
        $out = array('status' => 'failed', 'invoice_no' => null, 'receivable_id' => null, 'reason' => '');
        $claim_id = intval($claim_id);
        if ($claim_id <= 0) { $out['reason'] = 'معرّفٌ غير صالح'; return $out; }

        $gate = claim_gate(false);
        try {
            $c = $gate->selectOne('claims', array('where' => array('id' => $claim_id)));
        } catch (\Throwable $t) {
            error_log('claim approve fetch: ' . $t->getMessage()); return $out;
        }
        if (!$c) { $out['reason'] = 'المستخلصُ غير موجود'; return $out; }

        /* ══ P1-B — «من أنشأ لا يعتمد» على المستخلص ═══════════════════════════
           كان الختمُ يُكتب (`approved_by`) بلا مقارنةِ المُعِدِّ بالمعتمِد —
           والمستخلصُ هو ما يُفوتَر عليه العميل. **العطالةُ قبلَ الحارس**: المعتمَدُ
           سلفًا يُرجَع كما هو ولا يُقرأ «اعتمادَ ذاتٍ» جديدًا. */
        if ((string) $c['state'] === 'review' || (string) $c['state'] === 'submitted') {
            require_once __DIR__ . '/../includes/self_approval_guard.php';
            $__sa = ems_no_self_approval($conn, intval($c['created_by'] ?? 0), intval($uid),
                'مستخلصٌ #' . $claim_id, intval($c['company_id'] ?? 0));
            if ($__sa !== null) { $out['reason'] = $__sa['reason']; return $out; }
        }

        if (in_array((string) $c['state'], array('approved', 'invoiced', 'collected'), true)) {
            $out['status'] = 'exists';
            $out['invoice_no'] = (string) $c['invoice_no'];
            $out['reason'] = 'المستخلصُ معتمدٌ سلفًا';
            return $out;
        }
        if ((string) $c['state'] === 'cancelled') { $out['reason'] = 'المستخلصُ ملغى'; $out['status'] = 'blocked'; return $out; }

        // ── P-10: «فوترةٌ قبل قفل خط الأساس تُرفض» (PLAN-03 §9-⑱) ────────────
        // **وبحدود §2-② الملزِمة**: البوابةُ **تبدأ مطفأة** والعقودُ القائمةُ
        // تُفوتر كما هي؛ ولا تمنع إلا في `enforce` **ولعقدٍ رائدٍ مسمًّى**.
        require_once __DIR__ . '/../app/Services/Contract/ContractBaselineService.php';
        $__bg = \App\Services\Contract\ContractBaselineService::billingGate($gate, (int) $c['contract_id']);
        if (!$__bg['allow']) {
            $out['status'] = 'blocked';
            $out['reason'] = $__bg['reason'];
            return $out;
        }
        if (empty($c['client_id'])) { $out['reason'] = 'لا عميلَ على العقد — لا ذمّةَ بلا مَدين'; $out['status'] = 'blocked'; return $out; }

        // ── يدان لا يدٌ واحدة (قرارُ المالك 2026-07-28) ──────────────────────
        // ① لا تُجاز إلا مرفوعةٌ: المسودةُ ملكُ المبيعات تعدّلها حتى ترفعها.
        if ((string) $c['state'] !== 'review') {
            $out['status'] = 'blocked';
            $out['reason'] = 'لا تُجاز إلا مستخلصٌ رفعته المبيعاتُ للمالية — حالتُه: ' . (string) $c['state'];
            return $out;
        }
        // ② ولا يعتمد المرءُ ما رفع (نظيرُ حاجز تسويات الموردين) — حاجزٌ فوق المنح.
        if (!empty($c['submitted_by']) && intval($c['submitted_by']) === intval($uid)) {
            $out['status'] = 'blocked';
            $out['reason'] = 'لا يُجيز المستخلصَ من رفعه — الإجازةُ يدٌ ثانية';
            return $out;
        }

        // M-08/M-39 (ENT-03 §7): فترةٌ مقفلة → 423 — الفوترةُ فعلٌ ماليٌّ يقع
        // اليوم، فالفترةُ المفحوصة فترةُ يوم الإجازة لا فترةُ العمل المستخلَص.
        require_once __DIR__ . '/../includes/period_guard.php';
        $pchk = ems_period_check($conn, intval($c['company_id']), date('Y-m-d'));
        if (!$pchk['ok']) {
            $out['status'] = 'blocked';
            $out['code'] = 423;
            $out['reason'] = $pchk['reason'];
            return $out;
        }

        // بندٌ متنازَعٌ عليه يقف وحده — والمجاميعُ تُعاد بلا احتسابه
        $net = claim_recalc($gate, $claim_id);
        if ($net <= 0) { $out['reason'] = 'الصافي صفرٌ أو سالب — لا استحقاق'; $out['status'] = 'blocked'; return $out; }

        $currency  = (string) $c['currency'];
        $company   = intval($c['company_id']);

        // ── M-03 · الفاتورةُ الضريبيةُ بتسلسلها النظامي (ENT-03 §4) ─────────
        // كان الرقمُ **مشتقًّا من رقم المستخلص** (`INV-{claim_no}`) وبلا حقلٍ
        // نظاميٍّ واحد. وصار يُصدَر من `tax_invoices` **بتسلسلٍ لكل (شركة ×
        // سنة)** وحقولٍ نظاميةٍ ملتقَطةٍ لحظةَ الإصدار — والضريبةُ **بمرجعها**.
        // و«Approved → Invoiced» فعلٌ واحدٌ يقع هنا (§4)، فالعلَمُ `approving`.
        require_once __DIR__ . '/../app/Services/Revenue/TaxInvoiceService.php';
        $inv = \App\Services\Revenue\TaxInvoiceService::issueForClaim(
            $conn, $gate, $company, $claim_id,
            array('tax_code' => $tax_code, 'approving' => true), $uid);
        if (!$inv['ok'] && (int) $inv['code'] !== 409) {
            $out['status'] = 'blocked';
            $out['code']   = (int) $inv['code'];
            $out['reason'] = $inv['reason'];
            return $out;
        }
        $invoiceNo   = (string) $inv['serial_no'];
        $taxAmount   = (float) $inv['tax'];
        $taxCodeUsed = ($tax_code !== null && $tax_code !== '') ? (string) $tax_code : null;

        // قيودُ الإيراد التي يفوترها هذا المستخلص — للحقيقة المحايدة ولا تُنشأ
        // من جديد (البنودُ تحملها في `claim_lines.event_id` منذ التوليد).
        $billedEvents = array();
        try {
            foreach ($gate->select('claim_lines', array(
                'columns' => array('event_id'), 'where' => array('claim_id' => $claim_id))) as $ln) {
                if (!empty($ln['event_id'])) { $billedEvents[] = intval($ln['event_id']); }
            }
        } catch (\Throwable $t) { /* بنودٌ سابقةٌ للعمود — الحقيقةُ تُنشر بلا قائمة */ }

        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        $conn->begin_transaction();
        try {
            // حقيقةُ فوترةٍ محايدةٌ في الجذر **بلا إسقاطٍ في الدفتر** (publishFact):
            // فالاعترافُ بالإيراد وقع سلفًا في المروحة، وهذا مستندٌ ضريبيٌّ لا قيد.
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'          => 'billing.claim.invoiced',
                'category'           => 'financial',
                'source_module'      => 'sales',
                'company_id'         => $company,
                'entity_type'        => 'claim',
                'entity_id'          => $claim_id,
                'occurred_at'        => gmdate('Y-m-d H:i:s'),
                'created_by'         => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'    => 'claim:' . $claim_id,
                'amount'             => round($net + $taxAmount, 2),
                'currency'           => $currency,
                'source_ref'         => (string) $c['claim_no'],
                'project_id'         => !empty($c['project_id']) ? intval($c['project_id']) : null,
                'customer_entity_id' => intval($c['client_id']),
                'contract_id'        => !empty($c['contract_id']) ? intval($c['contract_id']) : null,
                'notes'              => 'فاتورةُ مستخلص ' . $c['claim_no'] . ' — ' . $invoiceNo,
                'payload'            => array(
                    'claim_id'    => $claim_id,
                    'claim_no'    => (string) $c['claim_no'],
                    'invoice_no'  => $invoiceNo,
                    'period'      => $c['period_from'] . '→' . $c['period_to'],
                    'gross'       => (float) $c['gross_amount'],
                    'retention'   => (float) $c['retention_amount'],
                    'net'         => $net,
                    'tax_code'    => $taxCodeUsed,
                    'tax_amount'  => $taxAmount,
                    'client_id'   => intval($c['client_id']),
                    // شفافيةُ المراجع: أيُّ قيودِ إيرادٍ فوترتها هذه الفاتورة
                    'billed_revenue_events' => $billedEvents,
                    'recognition' => 'fanout',   // الاعترافُ في المروحة لا هنا
                ),
            ));
            $conn->commit();
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('claim approve publish #' . $claim_id . ': ' . $t->getMessage());
            $out['reason'] = 'تعذّر تدوينُ حقيقة الفوترة';
            return $out;
        }

        // الذمّةُ المدينة — دَينٌ باسم العميل يُطارَد (بعطالته: مرجعُ المستخلص)
        $recvId = null;
        require_once __DIR__ . '/../app/Services/Finance/FxSettlementService.php';
        try {
            // `doc_type` قيمتان محصورتان (invoice·statement) — والذمّةُ تنشأ عن
            // **الفاتورة الضريبية** المولَّدة من المستخلص، فمرجعُها رقمُ الفاتورة.
            $ex = $gate->selectOne('fin_receivables', array(
                'columns' => array('id'), 'whereRaw' => "doc_type = 'invoice' AND doc_ref = ?",
                'params'  => array($invoiceNo),
            ));
            if ($ex) {
                $recvId = intval($ex['id']);
            } else {
                $recvId = intval($gate->insert('fin_receivables', array(
                    'customer_entity_id' => intval($c['client_id']),
                    'doc_type'           => 'invoice',
                    'doc_ref'            => $invoiceNo,
                    'project_id'         => !empty($c['project_id']) ? intval($c['project_id']) : null,
                    'amount'             => round($net + $taxAmount, 2),
                    // P-08: **الذمّةُ بعملتها وسعرِها المجمَّد** — ذمّةٌ بلا عملةٍ
                    // مبلغٌ لا يُعرف بأيِّ عملةٍ هو، ولا يُخصَّص له قبضٌ أصلًا.
                    'currency'           => (string) $c['currency'],
                    'fx_rate_recognized' => \App\Services\Finance\FxSettlementService::rateOf(
                                                (string) $c['currency'], date('Y-m-d')),
                    'base_amount'        => (function ($amt, $cur) {
                                                $r = \App\Services\Finance\FxSettlementService::rateOf($cur, date('Y-m-d'));
                                                return ($r === null) ? null : round($amt * $r, 2);
                                            })(round($net + $taxAmount, 2), (string) $c['currency']),
                    'collected'          => 0,
                    // `outstanding` عمودٌ **مولَّد** (amount − collected) — الكتابةُ
                    // إليه يرفضها MySQL؛ يُترك للقاعدة تحسبه.
                    'state'              => 'open',
                    'created_by'         => intval($uid) > 0 ? intval($uid) : null,
                )));
            }
        } catch (\Throwable $t) {
            error_log('claim receivable #' . $claim_id . ': ' . $t->getMessage());
        }

        try {
            $gate->update('claims', array(
                'state'         => 'invoiced',
                'invoice_no'    => $invoiceNo,
                'invoice_date'  => date('Y-m-d'),
                'tax_code'      => $taxCodeUsed,
                'tax_amount'    => $taxAmount,
                'receivable_id' => $recvId,
                'approved_by'   => intval($uid) > 0 ? intval($uid) : null,
                'approved_at'   => date('Y-m-d H:i:s'),
                'version'       => intval($c['version']) + 1,
            ), array('id' => $claim_id));
        } catch (\Throwable $t) {
            error_log('claim approve state #' . $claim_id . ': ' . $t->getMessage());
        }

        $out['status'] = 'approved';
        $out['invoice_no'] = $invoiceNo;
        $out['receivable_id'] = $recvId;
        return $out;
    }
}
