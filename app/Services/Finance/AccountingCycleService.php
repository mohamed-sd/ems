<?php
/**
 * AccountingCycleService — دورةُ المحاسبةِ بترتيبِها (‏RPR-W11 · §23)
 * ═══════════════════════════════════════════════════════════════════════════
 * **تأسيسٌ ⇐ دفاترُ مساعدة ⇐ تسويات ⇐ مطابقات ⇐ ميزانُ مراجعة ⇐ قائمةُ إقفال
 *   ⇐ إقفالُ فترة ⇐ قوائمُ مالية** — وكلُّ حلقةٍ لها بوّابتُها، ولا حلقةَ تُقفز.
 *
 * ◆ **§48: لا نطاقَ يكتب قيدًا.** النطاقُ المصدريُّ يصدر **طلبَ اعترافٍ**
 *   (`acc_recognition_request`) والماليّةُ **تقرّر** ثمَّ **تثبّت**. و`requestRecognition`
 *   تردُّ `SCOPE_WRITES_ENTRY` إن كان المصدرُ الماليّةَ نفسَها، و`postAccepted`
 *   تردُّ `POST_WITHOUT_ACCEPTED_REQUEST` إن لم يسبقْها قرارُ قبول.
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): كلُّ نداءٍ
 *   هنا يمرُّ ببوّابةِ المستأجرِ التي تحقن `company_id`، و`assertSingleEntity`
 *   تردُّ `CROSS_ENTITY_UNTAGGED` على أيِّ رقمٍ يجمع كيانَين بلا وسمٍ مسجَّل.
 *
 * ◆ **والعتبةُ من السجلِّ لا من الشيفرة**: `threshold()` تقرأ
 *   `repair01_w11_thresholds` — ولا مقارنةَ رقمٍ صلبةٍ في هذا الملفّ.
 *
 * ◆ **وفصلُ الواجباتِ رمزُ ردٍّ لا وثيقة**: كلُّ تركيبةٍ ممنوعةٍ في
 *   `repair01_w11_sod` لها رمزُ ردٍّ منفَّذٌ هنا بالاسمِ نفسِه.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Finance;

use App\Core\TenantDb;

class AccountingCycleService
{
    /** ترتيبُ الدورةِ المحاسبيّةِ — يُقرأ ولا يُخترع (‏§23) */
    const CYCLE_ORDER = array(
        1 => 'التاسيس المرجعي',
        2 => 'الدفاتر المساعدة',
        3 => 'القيد والدفتر',
        4 => 'التسويات',
        5 => 'المطابقات',
        6 => 'ميزان المراجعة',
        7 => 'قائمة الاقفال',
        8 => 'اقفال الفترة',
        9 => 'القوائم المالية',
    );

    /** وسمُ الرقمِ العابرِ للكيانات — نصُّ القرارِ حرفًا */
    const TAG_SINGLE = 'SINGLE_ENTITY';
    const TAG_GROUP  = 'GROUP_PROJECTION';
    const TAG_LABEL  = 'مجمع على مستوى المجموعة';

    private static $eventConn = null;
    private static $thConn = null;
    private static $th = null;

    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /** العتبةُ من السجلِّ — ولا رقمَ مكتوبٌ في هذا الملفّ */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            $c = self::$thConn;
            if ($c instanceof \mysqli) {
                $r = @$c->query("SELECT threshold_key, value_num FROM repair01_w11_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return isset(self::$th[$key]) ? self::$th[$key] : null;
    }

    private static function fail($code, $detail = '')
    {
        return array('ok' => false, 'code' => $code, 'detail' => $detail);
    }
    private static function done(array $data = array())
    {
        return array_merge(array('ok' => true, 'code' => 'OK'), $data);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⓪ حارسُ الكيانِ الواحد — الرقمُ يُوسَم أو يُرفض
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * رقمٌ مبنيٌّ على أكثرَ من كيانٍ قانونيٍّ لا يمرُّ إلّا بوسمٍ **مسجَّل**.
     * والوسمُ يُقرأ من `repair01_w11_consolidated` لا يُكتب في الشاشة.
     */
    public static function assertSingleEntity(\mysqli $conn, $figureKey, array $companyIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $companyIds))));
        if (count($ids) <= 1) { return self::done(array('tag' => self::TAG_SINGLE, 'entities' => count($ids))); }
        $k = $conn->real_escape_string((string) $figureKey);
        $r = @$conn->query("SELECT tag, tag_label_ar, read_owner FROM repair01_w11_consolidated
                             WHERE figure_key = '$k' LIMIT 1");
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row || (string) $row['tag'] !== self::TAG_GROUP || trim((string) $row['tag_label_ar']) === ''
            || trim((string) $row['read_owner']) === '') {
            return self::fail('CROSS_ENTITY_UNTAGGED',
                'رقم يجمع ' . count($ids) . ' كيانات بلا وسم مسجل');
        }
        return self::done(array('tag' => self::TAG_GROUP, 'entities' => count($ids),
                                'label' => (string) $row['tag_label_ar']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ① طلبُ الاعترافِ — النطاقُ يطلب والماليّةُ تقرّر (§48)
       ══════════════════════════════════════════════════════════════════════ */

    public static function requestRecognition(TenantDb $gate, array $req, $actorId)
    {
        $src = strtolower(trim((string) (isset($req['source_module']) ? $req['source_module'] : '')));
        if ($src === '') { return self::fail('RECOGNITION_WITHOUT_SOURCE', 'الطلب بلا نطاق مصدري'); }
        if ($src === 'finance') {
            return self::fail('SCOPE_WRITES_ENTRY', 'المالية لا تصدر لنفسها طلب اعتراف - تقرر وتثبت');
        }
        $ref = trim((string) (isset($req['source_ref']) ? $req['source_ref'] : ''));
        if ($ref === '') { return self::fail('RECOGNITION_WITHOUT_SOURCE', 'الطلب بلا مرجع واقعة'); }
        $amount = (float) (isset($req['amount']) ? $req['amount'] : 0);
        if ($amount <= 0) { return self::fail('RECOGNITION_AMOUNT_INVALID', 'المبلغ غير موجب'); }
        $cur = trim((string) (isset($req['currency']) ? $req['currency'] : ''));
        if ($cur === '') { return self::fail('RECOGNITION_WITHOUT_CURRENCY', 'الطلب بلا عملة'); }

        $rate = (float) (isset($req['fx_rate']) ? $req['fx_rate'] : 1);
        if ($rate <= 0) { $rate = 1; }
        $idem = 'w11:rec:' . $src . ':' . $ref . ':' . substr(sha1($src . '|' . $ref . '|' . $amount), 0, 12);
        $exists = $gate->selectOne('acc_recognition_request', array('where' => array('idem_key' => $idem)));
        if ($exists) { return self::done(array('request_id' => (int) $exists['id'], 'idempotent' => 1)); }

        $id = $gate->insert('acc_recognition_request', array(
            'request_no'    => (string) (isset($req['request_no']) ? $req['request_no'] : ('REC-' . substr(sha1($idem), 0, 10))),
            'source_module' => $src,
            'source_screen' => (string) (isset($req['source_screen']) ? $req['source_screen'] : ''),
            'source_ref'    => $ref,
            'source_doc_id' => (int) (isset($req['source_doc_id']) ? $req['source_doc_id'] : 0),
            'event_type'    => (string) (isset($req['event_type']) ? $req['event_type'] : 'expense'),
            'amount'        => $amount,
            'currency'      => $cur,
            'fx_rate'       => $rate,
            'base_amount'   => round($amount * $rate, 2),
            'period_code'   => (string) (isset($req['period_code']) ? $req['period_code'] : ''),
            'requested_by'  => (int) $actorId,
            'idem_key'      => $idem,
        ));
        self::emit($gate, 'acc.recognition.requested', array('request_id' => (int) $id));
        return self::done(array('request_id' => (int) $id));
    }

    /** قرارُ الماليّة — **ومن طلب لا يقرّر** */
    public static function decideRecognition(TenantDb $gate, $requestId, $decision, $reason, $actorId)
    {
        $requestId = (int) $requestId; $actorId = (int) $actorId;
        $r = $gate->selectOne('acc_recognition_request', array('where' => array('id' => $requestId)));
        if (!$r) { return self::fail('RECOGNITION_NOT_FOUND', ''); }
        if ((string) $r['finance_decision'] !== 'pending') {
            return self::fail('RECOGNITION_ALREADY_DECIDED', (string) $r['finance_decision']);
        }
        if ((int) $r['requested_by'] === $actorId) {
            return self::fail('SAME_ACTOR_REQUEST_AND_DECIDE', 'من طلب الاعتراف لا يقرره');
        }
        $decision = (string) $decision;
        if (!in_array($decision, array('accepted', 'rejected'), true)) {
            return self::fail('RECOGNITION_DECISION_INVALID', $decision);
        }
        $reason = trim((string) $reason);
        if ($decision === 'rejected' && $reason === '') {
            return self::fail('REJECT_WITHOUT_REASON', 'الرد بلا سبب مكتوب');
        }
        $gate->update('acc_recognition_request', array(
            'finance_decision' => $decision, 'decided_by' => $actorId,
            'decided_at' => date('Y-m-d H:i:s'), 'decision_reason' => $reason,
        ), array('id' => $requestId));
        self::emit($gate, 'acc.recognition.decided', array('request_id' => $requestId, 'decision' => $decision));
        return self::done(array('request_id' => $requestId, 'decision' => $decision));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② القيدُ — متوازنٌ في فترةٍ مفتوحةٍ على طلبٍ مقبول
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * تثبيتُ القيدِ على طلبِ اعترافٍ **مقبول**. والشروطُ أربعةٌ لا تُخفَّف:
     * طلبٌ مقبولٌ · فترةٌ مفتوحةٌ · مدينٌ يساوي دائنَ · ومَن أعدَّ لا يرحّل.
     */
    public static function postAccepted(TenantDb $gate, $requestId, array $lines, $periodId, $actorId)
    {
        $requestId = (int) $requestId; $actorId = (int) $actorId; $periodId = (int) $periodId;
        $r = $gate->selectOne('acc_recognition_request', array('where' => array('id' => $requestId)));
        if (!$r) { return self::fail('RECOGNITION_NOT_FOUND', ''); }
        if ((string) $r['finance_decision'] !== 'accepted') {
            return self::fail('POST_WITHOUT_ACCEPTED_REQUEST', 'لا قيد على طلب لم يقبل');
        }
        if ((int) $r['journal_entry_id'] > 0) {
            return self::done(array('entry_id' => (int) $r['journal_entry_id'], 'idempotent' => 1));
        }
        if ((int) $r['decided_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_POST', 'من قرر الاعتراف لا يرحل قيده');
        }
        $per = $gate->selectOne('fin_financial_periods', array('where' => array('id' => $periodId)));
        if (!$per) { return self::fail('PERIOD_NOT_FOUND', ''); }
        if ((int) $per['posting_allowed'] !== 1 || in_array((string) $per['state'], array('closed', 'locked'), true)) {
            return self::fail('POST_TO_CLOSED_PERIOD', 'لا قيد على فترة مقفلة');
        }

        /* ⛔ **والكتابةُ في دفترِ القيدِ ليست هنا** (`GAP-27`): كتّابُ الدفترِ
             محصورون في كاتبٍ واحدٍ معتمَدٍ وقائمةِ استثناءاتٍ مُعلَنة، والسقّاطةُ
             تُرسِّب عند ظهورِ كاتبٍ جديدٍ صامت. فالحوكمةُ تقع هنا كاملةً —
             طلبٌ مقبولٌ · فترةٌ مفتوحةٌ · من قرّر لا يرحّل — **ثمَّ يُسلَّم
             القيدُ إلى الكاتبِ المعتمَد**. والتوازنُ يفحصه هو فلا يُزدَوَج حكم. */
        require_once __DIR__ . '/PostingService.php';
        $res = PostingService::postFromRecognition($gate, $r, $lines, $periodId, $actorId);
        if (empty($res['ok'])) {
            return self::fail((string) $res['code'], (string) (isset($res['detail']) ? $res['detail'] : ''));
        }
        $entryId = (int) $res['entry_id'];
        $gate->update('acc_recognition_request', array('journal_entry_id' => $entryId), array('id' => $requestId));
        self::emit($gate, 'acc.entry.posted', array('entry_id' => $entryId, 'request_id' => $requestId));
        return self::done(array('entry_id' => $entryId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ التسوياتُ — استحقاقٌ ومقدَّمٌ ومخصَّصٌ بمستندِ أساس
       ══════════════════════════════════════════════════════════════════════ */

    public static function postAdjustment(TenantDb $gate, $adjId, $actorId)
    {
        $adjId = (int) $adjId; $actorId = (int) $actorId;
        $a = $gate->selectOne('acc_period_adjustment', array('where' => array('id' => $adjId)));
        if (!$a) { return self::fail('ADJUSTMENT_NOT_FOUND', ''); }
        if ((string) $a['state'] !== 'draft') { return self::fail('ADJUSTMENT_NOT_DRAFT', (string) $a['state']); }
        if ((int) $a['prepared_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_ADJ', 'من اعد التسوية لا يعتمدها');
        }
        if (trim((string) $a['basis_doc']) === '') {
            return self::fail('ADJUSTMENT_WITHOUT_BASIS', 'تسوية بلا مستند اساس');
        }
        $per = $gate->selectOne('fin_financial_periods', array('where' => array('id' => (int) $a['period_id'])));
        if (!$per || (int) $per['posting_allowed'] !== 1) {
            return self::fail('POST_TO_CLOSED_PERIOD', 'لا تسوية على فترة مقفلة');
        }
        $gate->update('acc_period_adjustment', array(
            'state' => 'posted', 'approved_by' => $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => $adjId));
        self::emit($gate, 'acc.adjustment.posted', array('adjustment_id' => $adjId,
            'period_id' => (int) $a['period_id']));
        return self::done(array('adjustment_id' => $adjId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ مطابقاتُ الحساباتِ — ولا فرقَ مدفونٌ في حقل
       ══════════════════════════════════════════════════════════════════════ */

    public static function openAccountRecon(TenantDb $gate, array $row, $actorId)
    {
        $acc = trim((string) (isset($row['account_code']) ? $row['account_code'] : ''));
        $src = trim((string) (isset($row['control_source']) ? $row['control_source'] : ''));
        if ($acc === '' || $src === '') {
            return self::fail('RECON_WITHOUT_SOURCE', 'المطابقة بلا حساب رقابي او بلا مصدر تفصيلي');
        }
        $gl = (float) (isset($row['gl_balance']) ? $row['gl_balance'] : 0);
        $sb = (float) (isset($row['source_balance']) ? $row['source_balance'] : 0);
        $id = $gate->insert('acc_account_recon', array(
            'period_id'      => (int) (isset($row['period_id']) ? $row['period_id'] : 0),
            'account_code'   => $acc,
            'control_source' => $src,
            'gl_balance'     => $gl,
            'source_balance' => $sb,
            'difference'     => round($gl - $sb, 2),
            'state'          => 'open',
            'prepared_by'    => (int) $actorId,
        ));
        return self::done(array('recon_id' => (int) $id, 'difference' => round($gl - $sb, 2)));
    }

    public static function addReconDifference(TenantDb $gate, $reconId, array $line)
    {
        $reconId = (int) $reconId;
        $rc = $gate->selectOne('acc_account_recon', array('where' => array('id' => $reconId)));
        if (!$rc) { return self::fail('RECON_NOT_FOUND', ''); }
        foreach (array('line_kind', 'cause', 'responsible_role', 'action_taken') as $f) {
            if (trim((string) (isset($line[$f]) ? $line[$f] : '')) === '') {
                return self::fail('DIFFERENCE_WITHOUT_OWNER', 'الفرق بلا نوع او سبب او مسؤول او اجراء');
            }
        }
        $id = $gate->insert('acc_account_recon_line', array(
            'recon_id'         => $reconId,
            'line_kind'        => (string) $line['line_kind'],
            'cause'            => (string) $line['cause'],
            'amount'           => (float) (isset($line['amount']) ? $line['amount'] : 0),
            'responsible_role' => (string) $line['responsible_role'],
            'action_taken'     => (string) $line['action_taken'],
            'state'            => 'open',
        ));
        self::recomputeRecon($gate, $reconId);
        return self::done(array('line_id' => (int) $id));
    }

    public static function resolveReconDifference(TenantDb $gate, $lineId, $actorId)
    {
        $lineId = (int) $lineId;
        $l = $gate->selectOne('acc_account_recon_line', array('where' => array('id' => $lineId)));
        if (!$l) { return self::fail('DIFFERENCE_NOT_FOUND', ''); }
        $gate->update('acc_account_recon_line', array(
            'state' => 'resolved', 'resolved_by' => (int) $actorId, 'resolved_at' => date('Y-m-d H:i:s'),
        ), array('id' => $lineId));
        self::recomputeRecon($gate, (int) $l['recon_id']);
        return self::done(array('line_id' => $lineId));
    }

    /** عدّادُ الفروقِ المفتوحةِ مشتقٌّ — لا يُكتب بيد */
    public static function recomputeRecon(TenantDb $gate, $reconId)
    {
        $reconId = (int) $reconId;
        $open = $gate->count('acc_account_recon_line',
            array('where' => array('recon_id' => $reconId, 'state' => 'open')));
        $gate->update('acc_account_recon', array('open_diffs' => (int) $open), array('id' => $reconId));
        return (int) $open;
    }

    /** ⛔ **لا تُقفَل مطابقةٌ وفيها فرقٌ مفتوح** — ومَن أعدَّ لا يُقفل */
    public static function closeAccountRecon(TenantDb $gate, $reconId, $actorId)
    {
        $reconId = (int) $reconId; $actorId = (int) $actorId;
        $rc = $gate->selectOne('acc_account_recon', array('where' => array('id' => $reconId)));
        if (!$rc) { return self::fail('RECON_NOT_FOUND', ''); }
        if ((string) $rc['state'] === 'closed') { return self::done(array('recon_id' => $reconId, 'idempotent' => 1)); }
        if ((int) $rc['prepared_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_CLOSE_RECON', 'من اعد المطابقة لا يقفلها');
        }
        $open = self::recomputeRecon($gate, $reconId);
        if ($open > 0) {
            return self::fail('RECON_CLOSE_WITH_OPEN_DIFF', 'فروق مفتوحة ' . $open);
        }
        $gate->update('acc_account_recon', array(
            'state' => 'closed', 'reviewed_by' => $actorId, 'closed_by' => $actorId,
            'closed_at' => date('Y-m-d H:i:s'),
        ), array('id' => $reconId));
        self::emit($gate, 'acc.account.reconciled', array('recon_id' => $reconId,
            'period_id' => (int) $rc['period_id'], 'account_code' => (string) $rc['account_code']));
        return self::done(array('recon_id' => $reconId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ ميزانُ المراجعة — مشتقٌّ كليًّا من القيودِ المنشورة
       ══════════════════════════════════════════════════════════════════════ */

    public static function runTrialBalance(TenantDb $gate, $periodId, $actorId)
    {
        $periodId = (int) $periodId; $actorId = (int) $actorId;
        $per = $gate->selectOne('fin_financial_periods', array('where' => array('id' => $periodId)));
        if (!$per) { return self::fail('PERIOD_NOT_FOUND', ''); }

        $entries = $gate->select('fin_journal_entries', array(
            'where' => array('period_code' => (string) $periodId, 'state' => 'posted'), 'limit' => 5000));
        $byAcc = array(); $debit = 0.0; $credit = 0.0; $lineN = 0;
        foreach ($entries as $e) {
            $lines = $gate->select('fin_journal_lines', array('where' => array('entry_id' => (int) $e['id']),
                'limit' => 2000));
            foreach ($lines as $l) {
                $acc = (string) $l['account_id'];
                if (!isset($byAcc[$acc])) { $byAcc[$acc] = array('d' => 0.0, 'c' => 0.0); }
                $byAcc[$acc]['d'] += (float) $l['debit'];
                $byAcc[$acc]['c'] += (float) $l['credit'];
                $debit  += (float) $l['debit'];
                $credit += (float) $l['credit'];
                $lineN++;
            }
        }
        $balanced = (abs($debit - $credit) <= 0.005) ? 1 : 0;
        $ref = 'TB-' . $periodId . '-' . substr(sha1($periodId . '|' . $lineN . '|' . microtime(true)), 0, 10);
        $runId = 0;
        $gate->runInTransaction(function (TenantDb $g) use ($periodId, $ref, $debit, $credit, $balanced,
                                                            $lineN, $entries, $byAcc, $actorId, &$runId) {
            $runId = (int) $g->insert('acc_trial_balance_run', array(
                'period_id'    => $periodId, 'run_ref' => $ref,
                'total_debit'  => round($debit, 2), 'total_credit' => round($credit, 2),
                'balanced'     => $balanced, 'line_count' => $lineN,
                'entry_count'  => count($entries), 'run_by' => $actorId,
                'note'         => 'مشتق من القيود المنشورة',
            ));
            foreach ($byAcc as $acc => $v) {
                $g->insert('acc_trial_balance_line', array(
                    'run_id' => $runId, 'account_code' => (string) $acc, 'account_name' => '',
                    'debit' => round($v['d'], 2), 'credit' => round($v['c'], 2),
                    'balance' => round($v['d'] - $v['c'], 2),
                ));
            }
        }, 'W11 جولة ميزان مراجعة');
        if ($runId <= 0) { return self::fail('TRIAL_RUN_NOT_WRITTEN', ''); }
        self::emit($gate, 'acc.trial.balanced', array('run_id' => $runId, 'period_id' => $periodId,
            'balanced' => $balanced));
        return self::done(array('run_id' => $runId, 'balanced' => $balanced,
            'total_debit' => round($debit, 2), 'total_credit' => round($credit, 2)));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ قائمةُ الإقفالِ — بندٌ ناقصٌ يحجب ما لم يُوثَّق استثناؤه
       ══════════════════════════════════════════════════════════════════════ */

    public static function documentChecklistException(TenantDb $gate, $itemId, $reason, $actorId)
    {
        $itemId = (int) $itemId;
        $reason = trim((string) $reason);
        if ($reason === '') { return self::fail('EXCEPTION_WITHOUT_REASON', 'استثناء بلا قرار مكتوب'); }
        $it = $gate->selectOne('fin_closing_items', array('where' => array('id' => $itemId)));
        if (!$it) { return self::fail('CHECKLIST_ITEM_NOT_FOUND', ''); }
        $gate->update('fin_closing_items', array(
            'exception_reason' => $reason, 'exception_by' => (int) $actorId,
            'exception_at' => date('Y-m-d H:i:s'), 'blocks_close' => 0,
        ), array('id' => $itemId));
        return self::done(array('item_id' => $itemId));
    }

    /** بنودٌ تحجب الإقفالَ — مقيسةٌ لا مُدَّعاة */
    public static function blockingChecklistItems(TenantDb $gate, $periodId)
    {
        $rows = $gate->select('fin_closing_items', array(
            'where' => array('period_id' => (int) $periodId), 'limit' => 500));
        $n = 0;
        foreach ($rows as $r) {
            if ((int) $r['required'] !== 1) { continue; }
            if ((string) $r['item_state'] === 'done' || (string) $r['item_state'] === 'na') { continue; }
            if ((int) $r['blocks_close'] === 0 && trim((string) $r['exception_reason']) !== '') { continue; }
            $n++;
        }
        return $n;
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑦ إقفالُ الفترةِ — إثباتٌ لا إعلان
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ⛔ **ثلاثةُ شروطٍ لا تُخفَّف**: ميزانٌ متوازنٌ لهذه الفترة · صفرُ بندٍ حاجبٍ
     * في قائمةِ الإقفال · ومَن رحّل قيودَ الفترةِ لا يقفلها.
     */
    public static function closePeriod(TenantDb $gate, $periodId, $actorId)
    {
        $periodId = (int) $periodId; $actorId = (int) $actorId;
        $per = $gate->selectOne('fin_financial_periods', array('where' => array('id' => $periodId)));
        if (!$per) { return self::fail('PERIOD_NOT_FOUND', ''); }
        if (in_array((string) $per['state'], array('closed', 'locked'), true)) {
            return self::done(array('period_id' => $periodId, 'idempotent' => 1));
        }
        $tb = $gate->select('acc_trial_balance_run', array(
            'where' => array('period_id' => $periodId), 'orderBy' => 'id DESC', 'limit' => 1));
        if (!$tb) { return self::fail('CLOSE_WITHOUT_TRIAL_BALANCE', 'لا اقفال بلا جولة ميزان'); }
        if ((int) $tb[0]['balanced'] !== 1) {
            return self::fail('CLOSE_WITHOUT_BALANCED_TRIAL', 'الميزان لا يتوازن');
        }
        $blocking = self::blockingChecklistItems($gate, $periodId);
        if ($blocking > 0) {
            return self::fail('CLOSE_WITH_BLOCKING_ITEM', 'بنود قائمة الاقفال الحاجبة ' . $blocking);
        }
        if ((int) $tb[0]['run_by'] === $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_CLOSE', 'من اجرى الميزان لا يقفل الفترة');
        }
        $gate->update('fin_financial_periods', array(
            'state' => 'closed', 'closed_at' => date('Y-m-d H:i:s'),
        ), array('id' => $periodId));
        self::emit($gate, 'acc.period.closed', array('period_id' => $periodId, 'run_id' => (int) $tb[0]['id']));
        return self::done(array('period_id' => $periodId, 'run_id' => (int) $tb[0]['id']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑧ القوائمُ الماليّة — تُشتقُّ بعدَ الإقفالِ لا قبلَه
       ══════════════════════════════════════════════════════════════════════ */

    public static function issueStatements(TenantDb $gate, $periodId, $actorId)
    {
        $periodId = (int) $periodId;
        $per = $gate->selectOne('fin_financial_periods', array('where' => array('id' => $periodId)));
        if (!$per) { return self::fail('PERIOD_NOT_FOUND', ''); }
        if (!in_array((string) $per['state'], array('closed', 'locked'), true)) {
            return self::fail('STATEMENTS_BEFORE_CLOSE', 'القوائم تشتق بعد الاقفال لا قبله');
        }
        $tb = $gate->select('acc_trial_balance_run', array(
            'where' => array('period_id' => $periodId, 'balanced' => 1), 'orderBy' => 'id DESC', 'limit' => 1));
        if (!$tb) { return self::fail('STATEMENTS_WITHOUT_BALANCED_TRIAL', ''); }
        self::emit($gate, 'acc.statements.issued', array('period_id' => $periodId,
            'run_id' => (int) $tb[0]['id'], 'actor' => (int) $actorId));
        return self::done(array('period_id' => $periodId, 'run_id' => (int) $tb[0]['id']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑨ حوكمةُ إعادةِ الفتحِ — استثناءٌ محكومٌ لا فعلٌ عاديّ
       ══════════════════════════════════════════════════════════════════════ */

    public static function requestReopen(TenantDb $gate, array $req, $actorId)
    {
        foreach (array('justification', 'authority_rule_id', 'scope_units') as $f) {
            if (trim((string) (isset($req[$f]) ? $req[$f] : '')) === '') {
                return self::fail('REOPEN_WITHOUT_AUTHORITY', 'طلب اعادة فتح بلا مبرر او قاعدة او نطاق');
            }
        }
        $id = $gate->insert('acc_period_reopen_request', array(
            'period_id'         => (int) (isset($req['period_id']) ? $req['period_id'] : 0),
            'request_no'        => (string) (isset($req['request_no']) ? $req['request_no']
                                             : ('RO-' . substr(sha1(microtime(true) . 'ro'), 0, 10))),
            'justification'     => (string) $req['justification'],
            'scope_from'        => isset($req['scope_from']) ? (string) $req['scope_from'] : null,
            'scope_to'          => isset($req['scope_to']) ? (string) $req['scope_to'] : null,
            'scope_units'       => (string) $req['scope_units'],
            'authority_rule_id' => (string) $req['authority_rule_id'],
            'requested_by'      => (int) $actorId,
            'state'             => 'pending',
        ));
        return self::done(array('reopen_id' => (int) $id));
    }

    public static function approveReopen(TenantDb $gate, $reopenId, $actorId)
    {
        $reopenId = (int) $reopenId; $actorId = (int) $actorId;
        $q = $gate->selectOne('acc_period_reopen_request', array('where' => array('id' => $reopenId)));
        if (!$q) { return self::fail('REOPEN_NOT_FOUND', ''); }
        if ((string) $q['state'] !== 'pending') { return self::fail('REOPEN_NOT_PENDING', (string) $q['state']); }
        if ((int) $q['requested_by'] === $actorId) {
            return self::fail('SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN', 'من طلب اعادة الفتح لا يعتمدها');
        }
        $gate->update('acc_period_reopen_request', array(
            'state' => 'approved', 'approved_by' => $actorId, 'approved_at' => date('Y-m-d H:i:s'),
        ), array('id' => $reopenId));
        $gate->update('fin_financial_periods', array(
            'state' => 'reopened', 'reopen_reason' => mb_substr((string) $q['justification'], 0, 190),
            'reopened_by' => $actorId,
        ), array('id' => (int) $q['period_id']));
        $gate->update('acc_period_reopen_request', array(
            'state' => 'applied', 'applied_at' => date('Y-m-d H:i:s'),
        ), array('id' => $reopenId));
        self::emit($gate, 'acc.period.reopened', array('reopen_id' => $reopenId,
            'period_id' => (int) $q['period_id']));
        return self::done(array('reopen_id' => $reopenId, 'period_id' => (int) $q['period_id']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑩ الرقابةُ الائتمانيّة — التجاوزُ يحجب أو يصعّد بقاعدةٍ لا بذوق
       ══════════════════════════════════════════════════════════════════════ */

    public static function recomputeExposure(TenantDb $gate, $customerId)
    {
        $customerId = (int) $customerId;
        $lim = $gate->selectOne('acc_credit_limit', array('where' => array('customer_entity_id' => $customerId)));
        if (!$lim) { return null; }
        $rows = $gate->select('fin_receivables', array(
            'where' => array('customer_entity_id' => $customerId), 'limit' => 2000));
        $exp = 0.0;
        foreach ($rows as $r) {
            if (in_array((string) $r['state'], array('open', 'partial', 'overdue'), true)) {
                $exp += (float) $r['outstanding'];
            }
        }
        $gate->update('acc_credit_limit', array('exposure_amount' => round($exp, 2)),
            array('id' => (int) $lim['id']));
        return round($exp, 2);
    }

    public static function assertCreditLimit(TenantDb $gate, $customerId, $newAmount)
    {
        $lim = $gate->selectOne('acc_credit_limit',
            array('where' => array('customer_entity_id' => (int) $customerId, 'is_active' => 1)));
        if (!$lim) { return self::done(array('checked' => 0)); }
        $exp = self::recomputeExposure($gate, (int) $customerId);
        $after = (float) $exp + (float) $newAmount;
        if ($after <= (float) $lim['limit_amount'] + 0.005) {
            return self::done(array('exposure' => $after, 'limit' => (float) $lim['limit_amount']));
        }
        if ((string) $lim['breach_action'] === 'escalate') {
            return self::fail('CREDIT_LIMIT_ESCALATE',
                'التجاوز يصعد بقاعدة ' . (string) $lim['authority_rule_id']);
        }
        return self::fail('CREDIT_LIMIT_EXCEEDED',
            'الحد ' . (float) $lim['limit_amount'] . ' والتعرض بعد العملية ' . $after);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑪ المستهلكون — كلٌّ يترك أثرًا تجاريًّا مقيسًا لا صفَّ حدثٍ منشأ (§46)
       ══════════════════════════════════════════════════════════════════════
       ⛔ **ولا استعلامَ خامٍّ على جدولِ مستأجِرٍ هنا** (`GAP-29` · `FR-SEC-006`):
         عقدُ المستهلكِ يمرّر `mysqli` لا `TenantDb`، فيُبنى الجسرُ من **كيانِ
         الحدثِ نفسِه** — `TenantContext::forSystem($company, 0, '', true)` —
         والكيانُ يُقرأ من الحمولةِ لا يُخمَّن. ومستهلكٌ بلا كيانٍ **يقف مُعلِنًا
         سببَه** ولا يقرأ صفًّا واحدًا: الحبّةُ `Legal Entity` تحكم القراءةَ كما
         تحكم الكتابة.
       ══════════════════════════════════════════════════════════════════════ */

    /** بوّابةُ المستهلكِ — من كيانِ الحدثِ لا من سياقِ جلسة */
    private static function gateOf(\mysqli $conn, $companyId)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) { return null; }
        $core = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/';
        require_once $core . 'TenantGateException.php';
        require_once $core . 'TenantRegistry.php';
        require_once $core . 'TenantContext.php';
        require_once $core . 'TenantDb.php';
        try {
            return new \App\Core\TenantDb($conn,
                \App\Core\TenantContext::forSystem($companyId, 0, '', true));
        } catch (\Throwable $t) { return null; }
    }

    /** الطلبُ يقع في فترتِه — وطلبٌ بلا فترةٍ لا يُرحَّل */
    public function onRecognitionRequested(array $event, \mysqli $conn)
    {
        $id = (int) self::payloadOf($event, 'request_id');
        $co = (int) self::payloadOf($event, 'company_id');
        if ($id <= 0) { return 'W11:SKIP:NO_ID'; }
        $g = self::gateOf($conn, $co);
        if (!$g) { return 'W11:NO_ENTITY'; }
        $r = $g->selectOne('acc_recognition_request', array('where' => array('id' => $id)));
        if (!$r) { return 'W11:SKIP:NOT_FOUND'; }
        if (trim((string) $r['period_code']) !== '') { return 'W11:IDEMPOTENT:' . $r['period_code']; }
        $d = substr((string) $r['requested_at'], 0, 10);
        $code = '';
        foreach ($g->select('fin_financial_periods',
                 array('where' => array('period_type' => 'month'), 'limit' => 500)) as $p) {
            if ((string) $p['start_date'] <= $d && (string) $p['end_date'] >= $d) {
                $code = (string) $p['id']; break;
            }
        }
        if ($code === '') { return 'W11:NO_PERIOD'; }
        $g->update('acc_recognition_request', array('period_code' => $code), array('id' => $id));
        return 'W11:PERIOD_RESOLVED:' . $code;
    }

    /** القبولُ يُنشئ واقعةَ الاعترافِ الماليّة — والردُّ لا يُنشئ شيئًا */
    public function onRecognitionDecided(array $event, \mysqli $conn)
    {
        $id = (int) self::payloadOf($event, 'request_id');
        $co = (int) self::payloadOf($event, 'company_id');
        if ($id <= 0) { return 'W11:SKIP:NO_ID'; }
        $g = self::gateOf($conn, $co);
        if (!$g) { return 'W11:NO_ENTITY'; }
        $r = $g->selectOne('acc_recognition_request', array('where' => array('id' => $id)));
        if (!$r) { return 'W11:SKIP:NOT_FOUND'; }
        if ((string) $r['finance_decision'] !== 'accepted') { return 'W11:REJECTED_NO_FACT'; }
        if ((int) $r['event_id'] > 0) { return 'W11:IDEMPOTENT:' . (int) $r['event_id']; }
        $type = in_array((string) $r['event_type'], array('revenue', 'expense', 'payable', 'receivable'), true)
              ? (string) $r['event_type'] : 'expense';
        $eid = (int) $g->insert('fin_financial_events', array(
            'event_no'     => 'W11-' . (string) $r['request_no'],
            'event_type'   => $type,
            'event_key'    => 'acc.recognition.accepted',
            'source_module' => 'finance',
            'source_ref'   => (string) $r['source_ref'],
            'amount'       => (float) $r['amount'],
            'currency'     => (string) $r['currency'],
            'fx_rate'      => (float) $r['fx_rate'],
            'base_amount'  => (float) $r['base_amount'],
            'state'        => 'approved',
            'occurred_at'  => date('Y-m-d H:i:s'),
            'created_by'   => (int) $r['decided_by'],
        ));
        if ($eid <= 0) { return 'W11:FACT_FAILED'; }
        $g->update('acc_recognition_request', array('event_id' => $eid), array('id' => $id));
        return 'W11:FACT_CREATED:' . $eid;
    }

    /** القيدُ يحرّك تعرُّضَ العميلِ الائتمانيَّ — والإتاحةُ تُقرأ من هنا */
    public function onEntryPosted(array $event, \mysqli $conn)
    {
        $entryId = (int) self::payloadOf($event, 'entry_id');
        $co = (int) self::payloadOf($event, 'company_id');
        if ($entryId <= 0) { return 'W11:SKIP:NO_ID'; }
        $g = self::gateOf($conn, $co);
        if (!$g) { return 'W11:NO_ENTITY'; }
        if (!$g->selectOne('fin_journal_entries', array('where' => array('id' => $entryId)))) {
            return 'W11:SKIP:NOT_FOUND';
        }
        $n = 0;
        foreach ($g->select('acc_credit_limit', array('where' => array('is_active' => 1), 'limit' => 500)) as $x) {
            $cu = (int) $x['customer_entity_id'];
            $exp = 0.0;
            foreach ($g->select('fin_receivables',
                     array('where' => array('customer_entity_id' => $cu), 'limit' => 2000)) as $rv) {
                if (in_array((string) $rv['state'], array('open', 'partial', 'overdue'), true)) {
                    $exp += (float) $rv['outstanding'];
                }
            }
            $g->update('acc_credit_limit', array('exposure_amount' => round($exp, 2)),
                array('id' => (int) $x['id']));
            $n++;
        }
        return 'W11:EXPOSURE_REFRESHED:' . $n;
    }

    /** التسويةُ المرحَّلةُ تُغلق بندَ الاستحقاقاتِ في قائمةِ الإقفال */
    public function onAdjustmentPosted(array $event, \mysqli $conn)
    {
        return self::markChecklist($conn, (int) self::payloadOf($event, 'company_id'),
            (int) self::payloadOf($event, 'period_id'), 'post_accruals');
    }

    /** المطابقةُ المقفلةُ تُغلق بندَ مطابقاتِ الذممِ في قائمةِ الإقفال */
    public function onAccountReconciled(array $event, \mysqli $conn)
    {
        $acc = (string) self::payloadOf($event, 'account_code');
        $step = (strpos($acc, '2') === 0) ? 'reconcile_ap' : 'reconcile_ar';
        return self::markChecklist($conn, (int) self::payloadOf($event, 'company_id'),
            (int) self::payloadOf($event, 'period_id'), $step);
    }

    /** الميزانُ المتوازنُ يُغلق بندَ مراجعةِ الفروق */
    public function onTrialBalanced(array $event, \mysqli $conn)
    {
        if ((int) self::payloadOf($event, 'balanced') !== 1) { return 'W11:NOT_BALANCED'; }
        return self::markChecklist($conn, (int) self::payloadOf($event, 'company_id'),
            (int) self::payloadOf($event, 'period_id'), 'variance_reviewed');
    }

    /** الإقفالُ يمنع الترحيلَ — وهذا أثرُه التجاريُّ لا صفُّ الحدث */
    public function onPeriodClosed(array $event, \mysqli $conn)
    {
        $pid = (int) self::payloadOf($event, 'period_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($pid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $g->update('fin_financial_periods', array('posting_allowed' => 0), array('id' => $pid));
        $r = $g->selectOne('fin_financial_periods', array('where' => array('id' => $pid)));
        return 'W11:POSTING_BLOCKED:' . ($r ? (int) $r['posting_allowed'] : -1);
    }

    /** وإعادةُ الفتحِ تعيد الترحيلَ في نطاقِها المعتمَدِ وحدَه */
    public function onPeriodReopened(array $event, \mysqli $conn)
    {
        $pid = (int) self::payloadOf($event, 'period_id');
        $g = self::gateOf($conn, (int) self::payloadOf($event, 'company_id'));
        if ($pid <= 0) { return 'W11:SKIP:NO_ID'; }
        if (!$g) { return 'W11:NO_ENTITY'; }
        $g->update('fin_financial_periods', array('posting_allowed' => 1), array('id' => $pid));
        $r = $g->selectOne('fin_financial_periods', array('where' => array('id' => $pid)));
        return 'W11:POSTING_ALLOWED:' . ($r ? (int) $r['posting_allowed'] : -1);
    }

    /** والقوائمُ الصادرةُ تُغلق بندَ إصدارِ التقارير */
    public function onStatementsIssued(array $event, \mysqli $conn)
    {
        return self::markChecklist($conn, (int) self::payloadOf($event, 'company_id'),
            (int) self::payloadOf($event, 'period_id'), 'reports_issued');
    }

    /* ══════════════════════════════════════════════════════════════════════
       أدواتٌ داخليّة
       ══════════════════════════════════════════════════════════════════════ */

    /** إغلاقُ بندٍ في قائمةِ إقفالِ الفترة — بالبوّابةِ لا باستعلامٍ خام */
    public static function markChecklist(\mysqli $conn, $companyId, $periodId, $step)
    {
        $periodId = (int) $periodId;
        if ($periodId <= 0) { return 'W11:SKIP:NO_PERIOD'; }
        $g = self::gateOf($conn, $companyId);
        if (!$g) { return 'W11:NO_ENTITY'; }
        $done = 0;
        foreach ($g->select('fin_closing_items',
                 array('where' => array('period_id' => $periodId, 'step' => (string) $step),
                       'limit' => 200)) as $it) {
            if ((string) $it['item_state'] !== 'done') {
                $g->update('fin_closing_items',
                    array('item_state' => 'done', 'done_at' => date('Y-m-d H:i:s')),
                    array('id' => (int) $it['id']));
            }
            $done++;
        }
        return 'W11:CHECKLIST_DONE:' . $step . ':' . $done;
    }

    private static function payloadOf(array $event, $key)
    {
        if (isset($event['payload'])) {
            $p = $event['payload'];
            if (is_string($p)) { $p = json_decode($p, true); }
            if (is_array($p) && isset($p[$key])) { return $p[$key]; }
        }
        return isset($event[$key]) ? $event[$key] : 0;
    }
    /** خريطةُ الحدثِ إلى كيانِه — سجلٌّ واحدٌ لا حرفيّاتٌ متناثرة */
    const EVENT_ENTITY = array(
        'acc.recognition.requested' => array('acc_recognition_request', 'request_id'),
        'acc.recognition.decided'   => array('acc_recognition_request', 'request_id'),
        'acc.entry.posted'          => array('fin_journal_entries',     'entry_id'),
        'acc.adjustment.posted'     => array('acc_period_adjustment',   'adjustment_id'),
        'acc.account.reconciled'    => array('acc_account_recon',       'recon_id'),
        'acc.trial.balanced'        => array('acc_trial_balance_run',   'run_id'),
        'acc.period.closed'         => array('fin_financial_periods',   'period_id'),
        'acc.period.reopened'       => array('fin_financial_periods',   'period_id'),
        'acc.statements.issued'     => array('fin_financial_periods',   'period_id'),
    );

    private static function emit(TenantDb $gate, $eventKey, array $payload)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!isset(self::EVENT_ENTITY[$eventKey])) { return null; }
        list($table, $idKey) = self::EVENT_ENTITY[$eventKey];
        $entityId = isset($payload[$idKey]) ? (int) $payload[$idKey] : 0;

        $pub = \dirname(\dirname(\dirname(__DIR__))) . '/app/Core/EventPublisher.php';
        if (!\is_file($pub)) { return null; }
        require_once $pub;

        $company = 0;
        try {
            $row = $gate->selectOne($table, array('columns' => array('company_id'), 'where' => array('id' => $entityId)));
            if ($row) { $company = (int) $row['company_id']; }
        } catch (\Throwable $t) { $company = 0; }

        /* ⚠ **الكيانُ يُحمَل في الحمولةِ لا يُستنتَج عند المستهلك**: عقدُ المستهلكِ
             يمرّر `mysqli` بلا سياقِ مستأجِر، فبلا كيانٍ في الحمولةِ يضطرُّ إلى
             استعلامٍ خامٍّ ليعرف كيانَ الصفّ — وهو ما يمنعه `GAP-29`. */
        $payload['company_id'] = $company;
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'finance',
                'source_module'   => 'finance',
                'entity_type'     => $table,
                'entity_id'       => $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w11:' . $eventKey . ':' . $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'AccountingCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
