<?php
/**
 * Contracts/advance_helpers.php — دفترُ الدفعة المقدَّمة (M-01 · ق-19)
 * ═══════════════════════════════════════════════════════════════════════════
 * **الخللُ الماليُّ الذي يوقفه** (مقيسٌ لا مقدَّر): كان الاستقطاعُ يخصم
 * `advance_recovery_pct` من أساس كل فترة **بلا رصيدٍ ولا سقفٍ ولا قبضٍ ابتدائي**
 * — فيتكرر **إلى الأبد** حتى بعد تصفية الدفعة. والعقد 5 نسبتُه 10٪ وخُصم منه
 * **12.50 فعلًا**، و**صفرُ سجلٍّ لقبض أيِّ دفعةٍ مقدَّمةٍ في النظام كلِّه**:
 * أي أن النظامَ كان **يسترد دَينًا لم يُقرضه**.
 *
 * ── الدفعةُ المقدَّمة سلفةٌ تُستردّ لا إيرادٌ يُعترف به ──────────────────────
 * فقبضُها **يغيّر متى يُقبض لا ما كُسب** ⇒ `publishFact` **لا** `postLedger`
 * (القاعدة ③). وهو الفخُّ نفسُه الذي كلّف عطبَ ردّ الضمان: قيدُ `revenue` على
 * حركةٍ لم تدخل الدفترَ أصلًا ضخّم إيرادَ العقد 5 من 124.95 إلى 131.20.
 * **النظيرُ الحرفيُّ**: `claim_retention_release()` — وقد وقع فيه الخطأُ وأُصلح.
 *
 * ── الرصيدُ من مصدرَين قائمَين لا من جدولٍ ثالث ────────────────────────────
 *   المقبوض   ← `contract_advances` (صفوفُ `recorded`)
 *   المستهلَك ← `claim_lines` بـ`source_kind='advance_recovery'` (مبالغُ سالبة)
 * وهو نمطُ الاحتجاز نفسُه (`retention`/`retention_release`). وجدولُ استهلاكٍ
 * ثالثٌ يكرّر ما هو مسجَّلٌ ⇒ مصدرانِ للرقم الواحد ⇒ انفصامٌ مؤجَّل (المبدأ ④).
 *
 * ── والسقفُ هو الإصلاح ──────────────────────────────────────────────────────
 * `advance_recovery_due()` تُرجع **الأقلَّ** من (نسبةِ الفترة · الرصيدِ المتبقي)،
 * و**صفرًا إن لا رصيد**. فعقدٌ بلا قبضٍ مسجَّل ⇒ رصيدٌ صفر ⇒ **لا بندَ استهلاكٍ
 * يُولَّد أصلًا** — والنزيفُ يتوقف من اللحظة الأولى بلا انتظار قرار.
 */

if (!defined('ADVANCE_HELPERS_INCLUDED')) { define('ADVANCE_HELPERS_INCLUDED', true); }

if (!function_exists('advance_next_no')) {
    /** ترقيمٌ خادميٌّ ADV-سنة-تسلسل لكل شركة (فريدٌ بقيد `uq_advance_no`). */
    function advance_next_no($gate)
    {
        $year = date('Y');
        $rows = $gate->select('contract_advances', array(
            'columns'  => array('advance_no'),
            'whereRaw' => "advance_no LIKE ?",
            'params'   => array('ADV-' . $year . '-%'),
            'orderBy'  => 'id DESC', 'limit' => 1,
            'includeDeleted' => true,
        ));
        $seq = 1;
        if ($rows && preg_match('~-(\d+)$~', (string) $rows[0]['advance_no'], $m)) {
            $seq = intval($m[1]) + 1;
        }
        return 'ADV-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('advance_received_total')) {
    /** مجموعُ الدفعات المقبوضةِ فعلًا على العقد — الملغاةُ خارجَ العدّ. */
    function advance_received_total($gate, $contract_id)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('a' => 'contract_advances')),
                "SELECT COALESCE(SUM(a.amount),0) AS s FROM contract_advances a
                  WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0) = 0
                    AND a.contract_id = ? AND a.state = 'recorded'",
                array(intval($contract_id)));
            return isset($rows[0]['s']) ? round((float) $rows[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) {
            error_log('advance_received_total: ' . $t->getMessage());
            return 0.0;
        }
    }
}

if (!function_exists('advance_recovered_total')) {
    /**
     * مجموعُ ما استُهلك من الدفعة — من بنود المستخلصات لا من جدولٍ موازٍ.
     * البنودُ **سالبةٌ** (خصمٌ من المطالبة) فتُقلب بـ`SUM(-amount)` — نفسُ صيغة
     * `claim_retention_balance` بالحرف.
     *
     * ⚠️ **والمستخلصُ الملغى لا يُحسب**: بنودُه لم تُطالِب أحدًا، فعدُّها
     * استهلاكًا يمنع استردادًا مستحقًّا.
     */
    function advance_recovered_total($gate, $contract_id, $exclude_claim_id = 0)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('cl' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT COALESCE(SUM(-cl.amount),0) AS s
                   FROM claim_lines cl
                   LEFT JOIN claims c ON c.id = cl.claim_id
                  WHERE {TENANT_SCOPE} AND cl.source_kind = 'advance_recovery'
                    AND c.contract_id = ?
                    AND c.state <> 'cancelled'
                    AND COALESCE(c.is_deleted,0) = 0
                    AND c.id <> ?",
                array(intval($contract_id), intval($exclude_claim_id)));
            return isset($rows[0]['s']) ? round((float) $rows[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) {
            error_log('advance_recovered_total: ' . $t->getMessage());
            return 0.0;
        }
    }
}

if (!function_exists('advance_balance')) {
    /**
     * رصيدُ الدفعة المقدَّمة المتبقي على العقد — **الرقمُ الذي يحكم الاستقطاع**.
     *
     * @return array{received:float,recovered:float,balance:float}
     */
    function advance_balance($gate, $contract_id, $exclude_claim_id = 0)
    {
        $recv = advance_received_total($gate, $contract_id);
        $used = advance_recovered_total($gate, $contract_id, $exclude_claim_id);
        return array(
            'received'  => $recv,
            'recovered' => $used,
            'balance'   => round($recv - $used, 2),
        );
    }
}

if (!function_exists('advance_recovery_due')) {
    /**
     * **قلبُ الإصلاح**: مقدارُ ما يُستقطع في هذه الفترة = الأقلُّ من
     *   ① نسبةِ العقد من أساس الفترة   ② الرصيدِ المتبقي
     * وصفرٌ إن لا رصيد — فلا استردادَ لدَينٍ لم يُقرَض، ولا استردادٌ بعد التصفية.
     *
     * @return array{due:float,balance:float,by_pct:float,capped:bool,reason:string}
     */
    function advance_recovery_due($gate, $contract_id, $base, $pct, $exclude_claim_id = 0)
    {
        $byPct = round((float) $base * (float) $pct / 100, 2);
        $bal   = advance_balance($gate, $contract_id, $exclude_claim_id);
        $balance = $bal['balance'];

        if ($balance <= 0) {
            return array('due' => 0.0, 'balance' => $balance, 'by_pct' => $byPct, 'capped' => true,
                'reason' => ($bal['received'] <= 0)
                    ? 'لا دفعة مقدمة مقبوضة مسجلة على هذا العقد — فلا استرداد'
                    : 'الدفعة المقدمة استردت بالكامل — فلا استرداد');
        }
        if ($byPct > $balance) {
            return array('due' => $balance, 'balance' => $balance, 'by_pct' => $byPct, 'capped' => true,
                'reason' => 'الاستقطاع مقصوص عند الرصيد المتبقي (' . number_format($balance, 2) . ')');
        }
        return array('due' => $byPct, 'balance' => $balance, 'by_pct' => $byPct, 'capped' => false, 'reason' => '');
    }
}

if (!function_exists('advance_record')) {
    /**
     * تسجيلُ قبضِ دفعةٍ مقدَّمة — **حقيقةٌ في الجذر بلا قيدٍ في الدفتر**.
     *
     * ولا يُشتق المبلغُ من نسبةٍ ولا يُقدَّر: **يُدخله صاحبُه من سند القبض**
     * (قاعدةُ عدم التلفيق). والمستندُ إلزامٌ، وهو نفسُه مفتاحُ العطالة.
     *
     * @return array{ok:bool,code:int,advance_id:?int,advance_no:?string,reason:string,existing:bool}
     */
    function advance_record($conn, $gate, $contract_id, $amount, $received_date,
                            $doc_ref, $note = '', $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'advance_id' => null, 'advance_no' => null,
                     'reason' => '', 'existing' => false);
        $contract_id = intval($contract_id);
        $amount = round((float) $amount, 2);
        $doc_ref = trim((string) $doc_ref);
        $received_date = trim((string) $received_date);

        if ($amount <= 0) {
            $out['code'] = 422; $out['reason'] = 'مبلغ الدفعة موجب — ولا يشتق من نسبة ولا يقدر'; return $out;
        }
        if ($doc_ref === '') {
            $out['code'] = 422; $out['reason'] = 'مرجع سند القبض إلزامي — لا سلفة بلا مستند'; return $out;
        }
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $received_date)) {
            $out['code'] = 422; $out['reason'] = 'تاريخ القبض غير صالح'; return $out;
        }

        try { $c = $gate->selectOne('contracts', array('where' => array('id' => $contract_id))); }
        catch (\Throwable $t) { $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقد غير موجود'; return $out; }

        // العملةُ من العقد — مطبَّعةٌ عند الحدّ كما في المستخلص (لا اسمٌ عربيٌّ خام)
        require_once dirname(__DIR__) . '/includes/fx.php';
        $currency = ems_fx_code($c['price_currency_contract'] ?? null);
        if ($currency === null) {
            $out['code'] = 422;
            $out['reason'] = 'عملة العقد غير مسجلة في سجل العملات — لا تقبض دفعة بعملة لا يعرفها الدفتر';
            return $out;
        }

        // ── العطالةُ قبل الكتابة (القاعدة ⑦): سندُ قبضٍ واحدٌ لا يُسجَّل مرتين ──
        try {
            $ex = $gate->selectOne('contract_advances', array(
                'whereRaw' => "contract_id = ? AND doc_ref = ?",
                'params'   => array($contract_id, $doc_ref)));
        } catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true;
            $out['advance_id'] = intval($ex['id']); $out['advance_no'] = (string) $ex['advance_no'];
            $out['reason'] = 'سند القبض مسجل سلفا: ' . $ex['advance_no'];
            return $out;
        }

        $no = advance_next_no($gate);
        $id = null;
        try {
            $id = intval($gate->insert('contract_advances', array(
                'contract_id'   => $contract_id,
                'advance_no'    => $no,
                'amount'        => $amount,
                'currency'      => $currency,
                'received_date' => $received_date,
                'doc_ref'       => mb_substr($doc_ref, 0, 120),
                'note'          => (trim((string) $note) === '') ? null : mb_substr(trim((string) $note), 0, 255),
                'state'         => 'recorded',
                'recorded_by'   => intval($uid) ?: null,
                'recorded_at'   => date('Y-m-d H:i:s'),
                'created_by'    => intval($uid) ?: null,
            )));
        } catch (\Throwable $t) {
            error_log('advance_record insert: ' . $t->getMessage());
            $out['code'] = 500; $out['reason'] = 'تعذر تسجيل القبض'; return $out;
        }

        // ── الحقيقةُ في الجذر — **بلا إسقاطٍ في الدفتر** ────────────────────
        // «سلفةٌ تُستردّ لا إيرادٌ يُعترف به»: قيدُ إيرادٍ هنا يضخّم الربحَ بمقدارها.
        // ⚠️ `publishFact` تُرجع **مصفوفةً** {id,…} لا رقمًا.
        require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
        try {
            $pub = \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'contract.advance.received',
                'category'        => 'financial',
                'source_module'   => 'sales',
                'company_id'      => intval($c['company_id']),
                'entity_type'     => 'contract_advance',
                'entity_id'       => $id,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key' => 'advance:' . $id,
                'amount'          => $amount,
                'currency'        => $currency,
                'source_ref'      => $no,
                'contract_id'     => $contract_id,
                'notes'           => 'قبض دفعة مقدمة ' . $no . ' — سند ' . $doc_ref,
                'payload'         => array(
                    'advance_id'    => $id,
                    'advance_no'    => $no,
                    'contract_id'   => $contract_id,
                    'amount'        => $amount,
                    'received_date' => $received_date,
                    'doc_ref'       => $doc_ref,
                    'recognition'   => 'none',
                    'note'          => 'سلفة تسترد لا إيراد يعترف به — لا قيد في الدفتر',
                ),
            ));
            $evId = (is_array($pub) && isset($pub['id'])) ? intval($pub['id']) : null;
            if ($evId !== null) {
                $gate->update('contract_advances', array('event_id' => $evId), array('id' => $id));
            }
        } catch (\Throwable $t) {
            // الحدثُ إشهارٌ لا شرطُ صحة — القبضُ مسجَّلٌ والفشلُ يُعلَن في السجل
            error_log('advance publish #' . $id . ': ' . $t->getMessage());
        }

        $out['ok'] = true; $out['code'] = 201; $out['advance_id'] = $id; $out['advance_no'] = $no;
        return $out;
    }
}

if (!function_exists('advance_cancel')) {
    /** إلغاءُ قبضٍ سُجّل خطأً — حالةٌ لا حذف، وتُخرجه من الرصيد فورًا. */
    function advance_cancel($gate, $advance_id, $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        try { $a = $gate->selectOne('contract_advances', array('where' => array('id' => intval($advance_id)))); }
        catch (\Throwable $t) { $a = null; }
        if (!$a) { $out['code'] = 404; $out['reason'] = 'سند القبض غير موجود'; return $out; }
        if ((string) $a['state'] === 'cancelled') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'ملغى سلفا'; return $out;
        }
        // ⚠️ لا يُلغى قبضٌ استُهلك منه: إلغاؤه يجعل الرصيدَ سالبًا ويُظهر
        // استردادًا بلا سلفة — وهو الخللُ الأصليُّ بعينه مقلوبًا.
        $bal = advance_balance($gate, intval($a['contract_id']));
        if ($bal['recovered'] > 0) {
            $out['code'] = 409;
            $out['reason'] = 'استهلك من هذه الدفعة ' . number_format($bal['recovered'], 2)
                           . ' — لا تلغى بعد الاستهلاك، ويصحح الفرق بإشعار موثق';
            return $out;
        }
        $gate->update('contract_advances', array('state' => 'cancelled'), array('id' => intval($advance_id)));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }
}

if (!function_exists('advance_schedule')) {
    /**
     * جدولُ الاستهلاك — بنودُ الاسترداد بمستخلصاتها، مرتَّبةً زمنيًّا.
     * قراءةٌ محضةٌ من المصدر القائم: **لا جدولَ موازٍ يُصان**.
     */
    function advance_schedule($gate, $contract_id)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('cl' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT c.claim_no, c.state AS claim_state, c.period_from, c.period_to,
                        cl.amount, cl.work_date, cl.claim_id
                   FROM claim_lines cl
                   LEFT JOIN claims c ON c.id = cl.claim_id
                  WHERE {TENANT_SCOPE} AND cl.source_kind = 'advance_recovery'
                    AND c.contract_id = ?
                  ORDER BY cl.work_date ASC, cl.id ASC",
                array(intval($contract_id)));
        } catch (\Throwable $t) {
            error_log('advance_schedule: ' . $t->getMessage());
            return array();
        }
    }
}

if (!function_exists('advance_reconciliation')) {
    /**
     * **تقريرُ المطابقة** (معيارُ القبول ④): عقودٌ استُردّ منها بلا قبضٍ مسجَّل،
     * أو استُردّ منها أكثرُ ممّا قُبض.
     *
     * ولا يُصلحُ شيئًا ولا يمسّ صفًّا — **يُعلن ويبقى ظاهرًا حتى يُقفل بقرار**
     * (قاعدةُ «لا مسحَ ولا تعديلَ لقيدٍ مرحَّل»). فالـ12.50 المخصومةُ سلفًا من
     * العقد 5 تظهر هنا باسمها ومبلغها، ولا تُمحى ولا تُعدَّل.
     *
     * @return array[] صفٌّ لكل عقدٍ منحرف: contract_id · received · recovered · gap · kind
     */
    function advance_reconciliation($gate)
    {
        $out = array();
        try {
            // العقودُ التي لها بندُ استردادٍ واحدٌ على الأقل
            $rows = $gate->scopedQuery(
                array('scope' => array('cl' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT c.contract_id, COALESCE(SUM(-cl.amount),0) AS recovered, COUNT(*) AS lines_n
                   FROM claim_lines cl
                   LEFT JOIN claims c ON c.id = cl.claim_id
                  WHERE {TENANT_SCOPE} AND cl.source_kind = 'advance_recovery'
                    AND c.state <> 'cancelled' AND COALESCE(c.is_deleted,0) = 0
                  GROUP BY c.contract_id", array());
        } catch (\Throwable $t) {
            error_log('advance_reconciliation: ' . $t->getMessage());
            return $out;
        }
        foreach ($rows as $r) {
            $cid = intval($r['contract_id']);
            if ($cid <= 0) { continue; }
            $recovered = round((float) $r['recovered'], 2);
            $received  = advance_received_total($gate, $cid);
            if ($recovered <= $received) { continue; }      // منضبط
            $out[] = array(
                'contract_id' => $cid,
                'received'    => $received,
                'recovered'   => $recovered,
                'lines'       => intval($r['lines_n']),
                'gap'         => round($recovered - $received, 2),
                'kind'        => ($received <= 0) ? 'no_receipt' : 'over_recovered',
                'label'       => ($received <= 0)
                    ? 'استرداد بلا قبض مسجل — ينتظر قرار المالك'
                    : 'استرد أكثر مما قبض — ينتظر قرار المالك',
            );
        }
        return $out;
    }
}
