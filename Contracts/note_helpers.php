<?php
/**
 * Contracts/note_helpers.php — الإشعارُ الدائن/المدين (M-02 · ENT-03 §3-⑥)
 * ═══════════════════════════════════════════════════════════════════════════
 * **الفاتورةُ الصادرة لا تُعدَّل** — تُصحَّح بإشعارٍ دائنٍ أو مدين. وقبل هذا الملف
 * كان تصحيحُ مستخلصٍ مفوترٍ **مستحيلًا نظاميًّا**: لا سبيلَ إلا العبثُ بالبيانات،
 * وهو ما تمنعه القاعدةُ الأولى نصًّا.
 *
 * ── ما يفعله الإشعار وما لا يفعله ─────────────────────────────────────────
 *   ✅ يشير إلى المستخلص الأصلي **وسطرِه**، ويحرّك **ذمّةَ العميل** بمقداره،
 *      ويُدوّن حقيقتَه في الجذر المحايد حاملةً `reverses_event_id` إلى فاتورته.
 *   ❌ **لا يمسّ `claims` ولا `claim_lines`** — الأصلُ يبقى كما صدر.
 *   ❌ **ولا يُنشئ قيدَ إيرادٍ ولا يعكسه**.
 *
 * ── ولماذا لا قيدَ إيراد (القاعدة ③ — سؤالُ العبور) ───────────────────────
 * «هل يغيّر ما كُسب أم متى يُفوتر؟» — والإشعارُ يغيّر **مقدارَ ما يُطالَب به
 * العميل**، والاعترافُ بالإيراد وقع سلفًا في مسارٍ آخر («المروحةُ تعترف
 * والمستخلصُ يفوتر» — قرارُ المالك 2026-07-27). فلو نشر الإشعارُ قيدًا لصار
 * الإيرادُ مزدوجًا في اتجاهٍ أو منقوصًا في آخر. `publishFact` إذن، لا `publish`.
 *
 * وهذا هو **الفخُّ نفسُه الذي كلّف النظام عطبًا** في ردّ الضمان: قيدُ `revenue`
 * على ردٍّ لم يدخل الدفترُ أصلًا ضخّم إيرادَ العقد 5 من 124.95 إلى 131.20.
 *
 * ⚠️ **وتصحيحُ «ما كُسب» له مسارُه الآخرُ القائم**: `AttributionService::reverse()`
 * بسطرٍ عاكسٍ على الواقعة. **مساران لسؤالين** — ومَن خلطهما ضاعف الإيراد.
 *
 * ── فصلُ اليدين والعطالة ──────────────────────────────────────────────────
 * `prepared_by ≠ approved_by` بنيويًّا (نظيرُ `claim_approve` و`SettlementService`)،
 * والسببُ والمستندُ إلزامان، والعطالةُ بمفتاحٍ يمنع فتحَ ذمّتين بطلبين متطابقين.
 */

if (!defined('CDNOTE_HELPERS_INCLUDED')) { define('CDNOTE_HELPERS_INCLUDED', true); }

require_once __DIR__ . '/claim_helpers.php';

if (!function_exists('cdnote_states')) {
    /** دورةُ حياة الإشعار — أربعُ حالاتٍ لا أكثر. */
    function cdnote_states()
    {
        return array(
            'draft'     => 'مسودة',
            'review'    => 'قيد المراجعة',
            'approved'  => 'معتمد',
            'cancelled' => 'ملغى',
        );
    }
}

if (!function_exists('cdnote_kinds')) {
    /**
     * الاتجاهان بمعناهما للمستخدم — لا `credit`/`debit` في وجهه.
     * والمعنى المحاسبي: الدائنُ **يُنقص** ما على العميل، والمدينُ **يزيده**.
     */
    function cdnote_kinds()
    {
        return array(
            'credit' => 'إشعارٌ دائن (يُنقص مطالبةَ العميل)',
            'debit'  => 'إشعارٌ مدين (يزيد مطالبةَ العميل)',
        );
    }
}

if (!function_exists('cdnote_next_no')) {
    /** ترقيمٌ خادميٌّ CDN-سنة-تسلسل لكل شركة (فريدٌ بقيد `uq_note_no`). */
    function cdnote_next_no($gate)
    {
        $year = date('Y');
        $rows = $gate->select('credit_debit_notes', array(
            'columns'  => array('note_no'),
            'whereRaw' => "note_no LIKE ?",
            'params'   => array('CDN-' . $year . '-%'),
            'orderBy'  => 'id DESC', 'limit' => 1,
            'includeDeleted' => true,
        ));
        $seq = 1;
        if ($rows && preg_match('~-(\d+)$~', (string) $rows[0]['note_no'], $m)) {
            $seq = intval($m[1]) + 1;
        }
        return 'CDN-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('cdnote_create')) {
    /**
     * إنشاءُ إشعارٍ مسودةً — يشير إلى الأصل ولا يمسّه.
     *
     * الحرّاس:
     *   • المستخلصُ يجب أن يكون **مفوترًا فعلًا** — لا إشعارَ على ما لم يصدر بعد
     *     (المسودةُ تُعدَّل، والفاتورةُ الصادرةُ هي التي لا تُعدَّل).
     *   • السببُ والمستندُ إلزامان · والمبلغُ موجب.
     *   • **لا يتجاوز الإشعارُ الدائنُ صافيَ الفاتورة** ناقصًا ما سبقه من دائنٍ
     *     معتمَد — وإلا فُتح للعميل رصيدٌ دائنٌ من عدم.
     *   • العطالةُ بـ`idem_key`: الطلبُ المكرَّر يُرجع القائمَ بـ200 ولا ينشئ ثانيًا.
     *
     * @return array{ok:bool,code:int,note_id:?int,note_no:?string,reason:string,existing:bool}
     */
    function cdnote_create($gate, $claim_id, $kind, $amount, $reason, $doc_ref,
                           $claim_line_id = null, $idem_key = null, $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'note_id' => null, 'note_no' => null,
                     'reason' => '', 'existing' => false);
        $claim_id = intval($claim_id);
        $kind = (string) $kind;
        $amount = round((float) $amount, 2);
        $reason = trim((string) $reason);
        $doc_ref = trim((string) $doc_ref);

        if (!isset(cdnote_kinds()[$kind])) {
            $out['code'] = 422; $out['reason'] = 'اتجاهُ الإشعار إما دائنٌ أو مدين'; return $out;
        }
        if ($amount <= 0) {
            $out['code'] = 422; $out['reason'] = 'مبلغُ الإشعار موجبٌ دائمًا — والاتجاهُ يحمل الإشارة'; return $out;
        }
        if ($reason === '') {
            $out['code'] = 422; $out['reason'] = 'سببُ الإشعار إلزامي — لا تتحرك ذمّةٌ بلا سببٍ مكتوب'; return $out;
        }
        if ($doc_ref === '') {
            $out['code'] = 422; $out['reason'] = 'مرجعُ المستند المؤيِّد إلزامي'; return $out;
        }

        try {
            $c = $gate->selectOne('claims', array('where' => array('id' => $claim_id)));
        } catch (\Throwable $t) {
            error_log('cdnote_create claim: ' . $t->getMessage());
            $out['code'] = 500; $out['reason'] = 'تعذّرت قراءةُ المستخلص'; return $out;
        }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'المستخلصُ غير موجود'; return $out; }

        // لا إشعارَ إلا على فاتورةٍ صدرت: ما لم يُفوتر بعدُ يُصحَّح في موضعه
        if (!in_array((string) $c['state'], array('invoiced', 'collected'), true)) {
            $out['code'] = 422;
            $out['reason'] = 'لا إشعارَ إلا على مستخلصٍ صدرت فاتورتُه — حالتُه: '
                           . (string) $c['state'] . ' (وما لم يُفوتر بعدُ يُصحَّح في موضعه)';
            return $out;
        }

        // السطرُ المشار إليه — إن ذُكر — يجب أن يكون من هذا المستخلص
        $lineId = ($claim_line_id === null || $claim_line_id === '') ? null : intval($claim_line_id);
        if ($lineId !== null) {
            try {
                $ln = $gate->selectOne('claim_lines', array(
                    'columns' => array('id', 'claim_id'), 'where' => array('id' => $lineId)));
            } catch (\Throwable $t) { $ln = null; }
            if (!$ln || intval($ln['claim_id']) !== $claim_id) {
                $out['code'] = 422; $out['reason'] = 'السطرُ المشار إليه ليس من هذا المستخلص'; return $out;
            }
        }

        // ── العطالةُ قبل كل شيء (القاعدة ⑦) ────────────────────────────────
        $idem = ($idem_key === null || $idem_key === '') ? null : mb_substr(trim((string) $idem_key), 0, 64);
        if ($idem !== null) {
            try {
                $ex = $gate->selectOne('credit_debit_notes', array(
                    'whereRaw' => "claim_id = ? AND note_kind = ? AND idem_key = ?",
                    'params'   => array($claim_id, $kind, $idem)));
            } catch (\Throwable $t) { $ex = null; }
            if ($ex) {
                $out['ok'] = true; $out['code'] = 200; $out['existing'] = true;
                $out['note_id'] = intval($ex['id']); $out['note_no'] = (string) $ex['note_no'];
                $out['reason'] = 'إشعارٌ بهذا المفتاح قائمٌ سلفًا: ' . $ex['note_no'];
                return $out;
            }
        }

        // ── سقفُ الدائن: لا يُفتح للعميل رصيدٌ دائنٌ من عدم ─────────────────
        if ($kind === 'credit') {
            $already = cdnote_approved_total($gate, $claim_id, 'credit');
            $room = round((float) $c['net_amount'] + (float) $c['tax_amount'] - $already, 2);
            if ($amount > $room) {
                $out['code'] = 422;
                $out['reason'] = 'الإشعارُ الدائن (' . number_format($amount, 2) . ') يتجاوز المتبقي من الفاتورة ('
                               . number_format($room, 2) . ') — ولا يُفتح رصيدٌ دائنٌ من عدم';
                return $out;
            }
        }

        $no = cdnote_next_no($gate);
        try {
            $id = intval($gate->insert('credit_debit_notes', array(
                'note_no'       => $no,
                'note_kind'     => $kind,
                'claim_id'      => $claim_id,
                'claim_line_id' => $lineId,
                'invoice_no'    => ($c['invoice_no'] !== null) ? (string) $c['invoice_no'] : null,
                'currency'      => (string) $c['currency'],
                'amount'        => $amount,
                'reason'        => mb_substr($reason, 0, 255),
                'doc_ref'       => mb_substr($doc_ref, 0, 120),
                'state'         => 'draft',
                'idem_key'      => $idem,
                'prepared_by'   => intval($uid) ?: null,
                'created_by'    => intval($uid) ?: null,
            )));
        } catch (\Throwable $t) {
            error_log('cdnote_create insert: ' . $t->getMessage());
            $out['code'] = 500; $out['reason'] = 'تعذّر إنشاءُ الإشعار'; return $out;
        }

        $out['ok'] = true; $out['code'] = 201; $out['note_id'] = $id; $out['note_no'] = $no;
        return $out;
    }
}

if (!function_exists('cdnote_approved_total')) {
    /** مجموعُ إشعاراتِ اتجاهٍ **معتمَدةٍ** على مستخلص — أساسُ السقف والصافي. */
    function cdnote_approved_total($gate, $claim_id, $kind)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('n' => 'credit_debit_notes')),
                "SELECT COALESCE(SUM(n.amount),0) AS s FROM credit_debit_notes n
                  WHERE {TENANT_SCOPE} AND COALESCE(n.is_deleted,0) = 0
                    AND n.claim_id = ? AND n.note_kind = ? AND n.state = 'approved'",
                array(intval($claim_id), (string) $kind));
            return isset($rows[0]['s']) ? round((float) $rows[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) {
            error_log('cdnote_approved_total: ' . $t->getMessage());
            return 0.0;
        }
    }
}

if (!function_exists('cdnote_submit')) {
    /** رفعُ الإشعار للمالية — يقفل تعديلَه عند مُعِدِّه. */
    function cdnote_submit($gate, $note_id, $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        try { $n = $gate->selectOne('credit_debit_notes', array('where' => array('id' => intval($note_id)))); }
        catch (\Throwable $t) { $n = null; }
        if (!$n) { $out['code'] = 404; $out['reason'] = 'الإشعارُ غير موجود'; return $out; }
        if ((string) $n['state'] === 'review') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مرفوعٌ سلفًا'; return $out;
        }
        if ((string) $n['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'لا يُرفع إلا إشعارٌ مسودة — حالتُه: ' . $n['state']; return $out;
        }
        $gate->update('credit_debit_notes', array(
            'state' => 'review', 'submitted_by' => intval($uid) ?: null,
            'submitted_at' => date('Y-m-d H:i:s'), 'version' => intval($n['version']) + 1,
        ), array('id' => intval($note_id)));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }
}

if (!function_exists('cdnote_approve')) {
    /**
     * إجازةُ الإشعار — **هنا وحدَها تتحرك الذمّة**.
     *
     * الترتيبُ مقصود: عطالةٌ ← فصلُ يدين ← حقيقةٌ في الجذر ← أثرٌ على الذمّة.
     * فالحقيقةُ تُدوَّن قبل أثرِها، والأثرُ لا يقع مرتين.
     *
     * @return array{ok:bool,code:int,reason:string,receivable_id:?int,existing:bool}
     */
    function cdnote_approve($conn, $gate, $note_id, $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'receivable_id' => null, 'existing' => false);
        $note_id = intval($note_id);
        try { $n = $gate->selectOne('credit_debit_notes', array('where' => array('id' => $note_id))); }
        catch (\Throwable $t) { $n = null; }
        if (!$n) { $out['code'] = 404; $out['reason'] = 'الإشعارُ غير موجود'; return $out; }

        // ① العطالة: المعتمَدُ يُرجَع كما هو — لا ذمّةَ تتحرك مرتين
        if ((string) $n['state'] === 'approved') {
            $out['ok'] = true; $out['code'] = 200; $out['existing'] = true;
            $out['receivable_id'] = ($n['receivable_id'] !== null) ? intval($n['receivable_id']) : null;
            $out['reason'] = 'الإشعارُ معتمدٌ سلفًا — ' . $n['note_no'];
            return $out;
        }
        if ((string) $n['state'] === 'cancelled') {
            $out['code'] = 422; $out['reason'] = 'الإشعارُ ملغى'; return $out;
        }

        /* ══ P1-B — رأسُ هذا الملفِّ يعلن «`prepared_by ≠ approved_by` بنيويًّا»
             ولم يكن في الشيفرةِ فحصٌ واحدٌ يفعله. والإشعارُ يحرّك ذمّةً — فاليدُ
             الثانيةُ شرطُ صحته. (**العطالةُ فوقَه**: المعتمَدُ سلفًا رجع قبل هذا.) */
        require_once __DIR__ . '/../includes/self_approval_guard.php';
        $__sa = ems_no_self_approval($conn, intval($n['created_by'] ?? 0), intval($uid),
            'إشعارٌ دائن/مدين ' . (string) ($n['note_no'] ?? ('#' . $note_id)),
            intval($n['company_id'] ?? 0));
        if ($__sa !== null) { $out['code'] = 403; $out['reason'] = $__sa['reason']; return $out; }
        if ((string) $n['state'] !== 'review') {
            $out['code'] = 422;
            $out['reason'] = 'لا يُجاز إلا إشعارٌ رُفع للمالية — حالتُه: ' . $n['state'];
            return $out;
        }

        // ② فصلُ اليدين — بنيويٌّ فوق المنح
        $preparer = intval($n['submitted_by']) ?: intval($n['prepared_by']);
        if ($preparer > 0 && $preparer === intval($uid)) {
            $out['code'] = 403;
            $out['reason'] = 'لا يُجيز الإشعارَ من أعدّه — الإجازةُ يدٌ ثانية';
            return $out;
        }

        try { $c = $gate->selectOne('claims', array('where' => array('id' => intval($n['claim_id'])))); }
        catch (\Throwable $t) { $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'المستخلصُ الأصلي غير موجود'; return $out; }

        $kind   = (string) $n['note_kind'];
        $amount = round((float) $n['amount'], 2);
        $signed = ($kind === 'credit') ? -$amount : $amount;

        // ③ الحقيقةُ في الجذر المحايد — **بلا إسقاطٍ في الدفتر**
        //    وتحمل نسبَها: `reverses_event_id` إلى حدث فوترة المستخلص، فيصير
        //    النسبُ بين الأصل وعاكسه في الجذر لا في عمودٍ يُصان.
        $reverses = null;
        try {
            $st = $conn->prepare("SELECT id FROM ems_business_events
                                   WHERE company_id=? AND entity_type='claim' AND entity_id=?
                                     AND event_key='billing.claim.invoiced'
                                   ORDER BY id DESC LIMIT 1");
            $co = intval($n['company_id']); $ci = intval($n['claim_id']);
            $st->bind_param('ii', $co, $ci);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) { $reverses = intval($r['id']); }
        } catch (\Throwable $t) { /* النسبُ زينةُ تتبّعٍ لا شرطُ صحة */ }

        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        // ⚠️ `publishFact` تُرجع **مصفوفةً** {id, correlation_id, idempotency_key}
        // لا رقمًا — وإسنادُها خامًا إلى عمودٍ عدديٍّ يخزّن قيمةً بلا معنى.
        $eventId = null;
        $conn->begin_transaction();
        try {
            $pub = \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'          => ($kind === 'credit')
                                        ? 'billing.note.credit_issued' : 'billing.note.debit_issued',
                'category'           => 'financial',
                'source_module'      => 'sales',
                'company_id'         => intval($n['company_id']),
                'entity_type'        => 'credit_debit_note',
                'entity_id'          => $note_id,
                'occurred_at'        => gmdate('Y-m-d H:i:s'),
                'created_by'         => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'    => 'cdnote:' . $note_id,
                'reverses_event_id'  => $reverses,
                'amount'             => $amount,
                'currency'           => (string) $n['currency'],
                'source_ref'         => (string) $n['note_no'],
                'project_id'         => !empty($c['project_id']) ? intval($c['project_id']) : null,
                'customer_entity_id' => !empty($c['client_id']) ? intval($c['client_id']) : null,
                'contract_id'        => !empty($c['contract_id']) ? intval($c['contract_id']) : null,
                'notes'              => (($kind === 'credit') ? 'إشعارٌ دائن ' : 'إشعارٌ مدين ')
                                      . $n['note_no'] . ' على فاتورة ' . (string) $n['invoice_no'],
                'payload'            => array(
                    'note_id'       => $note_id,
                    'note_no'       => (string) $n['note_no'],
                    'note_kind'     => $kind,
                    'claim_id'      => intval($n['claim_id']),
                    'claim_no'      => (string) $c['claim_no'],
                    'claim_line_id' => ($n['claim_line_id'] !== null) ? intval($n['claim_line_id']) : null,
                    'invoice_no'    => (string) $n['invoice_no'],
                    'amount'        => $amount,
                    'signed_effect' => $signed,
                    'reason'        => (string) $n['reason'],
                    'doc_ref'       => (string) $n['doc_ref'],
                    // شفافيةُ الحدّ: الإشعارُ يفوتر عكسًا ولا يعترف من جديد
                    'recognition'   => 'none',
                    'note'          => 'يحرّك الذمّةَ ولا يُنشئ قيدَ إيراد — الاعترافُ في مساره',
                ),
            ));
            $eventId = (is_array($pub) && isset($pub['id'])) ? intval($pub['id']) : null;
            $conn->commit();
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('cdnote publish #' . $note_id . ': ' . $t->getMessage());
            $out['code'] = 500; $out['reason'] = 'تعذّر تدوينُ حقيقة الإشعار'; return $out;
        }

        // ④ أثرُ الذمّة — بمقدار الإشعار وباتجاهه
        $recvId = ($c['receivable_id'] !== null) ? intval($c['receivable_id']) : null;
        if ($recvId > 0) {
            try {
                $rc = $gate->selectOne('fin_receivables', array('where' => array('id' => $recvId)));
                if ($rc) {
                    // `outstanding` عمودٌ **مولَّد** (amount − collected) فلا يُكتب —
                    // التحريكُ على `amount` وحدَه، والقاعدةُ تُعيد حسابَ المتبقي.
                    $newAmount = round((float) $rc['amount'] + $signed, 2);
                    if ($newAmount < 0) { $newAmount = 0.0; }
                    $collected = (float) $rc['collected'];
                    $newState = ($collected <= 0) ? 'open'
                              : (($collected >= $newAmount) ? 'collected' : 'partial');
                    $gate->update('fin_receivables', array(
                        'amount' => $newAmount, 'state' => $newState,
                    ), array('id' => $recvId));
                }
            } catch (\Throwable $t) {
                error_log('cdnote receivable #' . $note_id . ': ' . $t->getMessage());
            }
        }

        try {
            $gate->update('credit_debit_notes', array(
                'state'         => 'approved',
                'approved_by'   => intval($uid) ?: null,
                'approved_at'   => date('Y-m-d H:i:s'),
                'event_id'      => $eventId,
                'receivable_id' => $recvId ?: null,
                'version'       => intval($n['version']) + 1,
            ), array('id' => $note_id));
        } catch (\Throwable $t) {
            error_log('cdnote state #' . $note_id . ': ' . $t->getMessage());
        }

        $out['ok'] = true; $out['code'] = 200; $out['receivable_id'] = $recvId ?: null;
        return $out;
    }
}

if (!function_exists('cdnote_cancel')) {
    /** إلغاءُ إشعارٍ لم يُجَز بعد — والمعتمَدُ لا يُلغى (يُقابَل بإشعارٍ عكسي). */
    function cdnote_cancel($gate, $note_id, $uid = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        try { $n = $gate->selectOne('credit_debit_notes', array('where' => array('id' => intval($note_id)))); }
        catch (\Throwable $t) { $n = null; }
        if (!$n) { $out['code'] = 404; $out['reason'] = 'الإشعارُ غير موجود'; return $out; }
        if ((string) $n['state'] === 'approved') {
            $out['code'] = 422;
            $out['reason'] = 'الإشعارُ المعتمدُ لا يُلغى — يُقابَل بإشعارٍ عكسيٍّ موثَّق';
            return $out;
        }
        if ((string) $n['state'] === 'cancelled') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'ملغًى سلفًا'; return $out;
        }
        $gate->update('credit_debit_notes', array(
            'state' => 'cancelled', 'version' => intval($n['version']) + 1,
        ), array('id' => intval($note_id)));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }
}

if (!function_exists('cdnote_for_claim')) {
    /** إشعاراتُ مستخلصٍ مرتَّبةً — للعرض بجانب الأصل لا داخله. */
    function cdnote_for_claim($gate, $claim_id)
    {
        try {
            return $gate->select('credit_debit_notes', array(
                'where' => array('claim_id' => intval($claim_id)),
                'orderBy' => 'id DESC'));
        } catch (\Throwable $t) {
            error_log('cdnote_for_claim: ' . $t->getMessage());
            return array();
        }
    }
}

if (!function_exists('cdnote_claim_net')) {
    /**
     * صافي المطالبة بعد الإشعارات المعتمَدة — **رقمٌ مشتقٌّ لا مخزَّن**.
     * الأصلُ لا يُمسّ، فالصافي الحقيقيُّ يُحسب عند العرض: الفاتورةُ − الدائن + المدين.
     */
    function cdnote_claim_net($gate, array $claim)
    {
        $base = round((float) $claim['net_amount'] + (float) $claim['tax_amount'], 2);
        $cr = cdnote_approved_total($gate, intval($claim['id']), 'credit');
        $db = cdnote_approved_total($gate, intval($claim['id']), 'debit');
        return array(
            'invoiced' => $base,
            'credit'   => $cr,
            'debit'    => $db,
            'net'      => round($base - $cr + $db, 2),
        );
    }
}
