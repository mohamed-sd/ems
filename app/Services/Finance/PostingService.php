<?php
/**
 * PostingService — الخطواتُ الثلاثُ التي تصل الواقعةَ بالدفتر (B8)
 * ═══════════════════════════════════════════════════════════════════════════
 * التشخيصُ (tools/fin00_posting_gap.php): آلةُ الحالاتِ تُعرّف السلسلةَ كاملةً
 * والتهيئةُ موجودةٌ والناقلُ حيّ — لكنَّ الانتقالَ Published ⇐ UnderReview **بلا
 * موضعِ نداءٍ واحدٍ** في الشجرة. فالسلسلةُ تقف عند البابِ الأول. هذه الخدمةُ
 * تبني الأبوابَ الثلاثةَ ولا تخترع حكمًا:
 *
 *   ① reviewPublished()  Published   ⇐ UnderReview  · التوجيهُ لمحاسبِ التخصص
 *   ② approveReviewed()  UnderReview ⇐ Approved     · بالسقوفِ المُعرَّفةِ سلفًا
 *   ③ postApproved()     Approved    ⇐ Posted       · قيدٌ متوازنٌ في الدفتر
 *
 * ── ما يُقرأ ولا يُخترع ────────────────────────────────────────────────
 * · السقوفُ من `fin_approval_matrix` (min/max/required_level) — لا رقمَ مكتوبٌ هنا.
 * · الحساباتُ من `fin_chart_of_accounts` بشرطِ `is_postable=1 AND active=1`.
 * · الفترةُ من `fin_financial_periods` بشرطِ `posting_allowed=1`.
 * · الحالاتُ عبرَ `EventStateMachine::transition` — بقائمةِ سماحِها لا بكتابةٍ مباشرة.
 *
 * ── وما يُعلَن لأنه اجتهادٌ لا نصّ ─────────────────────────────────────
 * `fin_posting_matrix` **نصٌّ وصفيٌّ لا قاعدةٌ آلية** («4101 · 4102 بحسب النموذج»).
 * فخريطةُ الحساباتِ أدناه مستخرَجةٌ من **نمطِ القيودِ التسعةِ القائمةِ في الدفتر**
 * (مدين 1104 ذمم العملاء / دائن 4101 إيراد التأجير بالساعة) وممدودةٌ بالوحدةِ
 * إلى 4102 للطن و4103 للمتر كما تسمّيها أسماءُ الحساباتِ نفسُها. وما لا قاعدةَ
 * له **يقف في PostingFailed بسببِه** ولا يُخمَّن له حساب.
 *
 * ── ولا صفَّ يُكتب بلا هذه الشروط ──────────────────────────────────────
 * مبلغٌ موجب · فترةٌ مفتوحة · حسابانِ قابلانِ للترحيل · ومدينٌ = دائن.
 * والعطالةُ بـ`journal_entry_id` — فإعادةُ التشغيلِ لا تُنتج قيدًا ثانيًا.
 */

namespace App\Services\Finance;

require_once __DIR__ . '/EventStateMachine.php';

class PostingService
{
    /** خريطةُ الحسابات — مُعلَنةٌ لأنها اجتهادٌ فوقَ مصفوفةٍ نصّية.
     *  المفتاحُ الأولُ `النوع|الوحدة` (للإيرادِ فالوحدةُ تحدّد نموذجَ العمل)،
     *  فإن لم يُصب جُرِّب `النوع|المصدر` (للمصروفِ فالوحدةُ فارغةٌ والمصدرُ
     *  هو ما يحدّد طبيعةَ التكلفة). وما لا يُصيب مفتاحًا **يقف بسببِه**. */
    const ACCOUNT_MAP = array(
        // الإيراد: الوحدةُ تحدّد نموذجَ العمل
        'revenue|hour'          => array('debit' => '1104', 'credit' => '4101'),
        'revenue|ton'           => array('debit' => '1104', 'credit' => '4102'),
        'revenue|meter'         => array('debit' => '1104', 'credit' => '4103'),
        // المصروف: المصدرُ يحدّد طبيعةَ التكلفة · والدائنُ ذممُ الموردين
        'expense|maintenance'   => array('debit' => '5110', 'credit' => '2101'),
        'expense|procurement'   => array('debit' => '5109', 'credit' => '2101'),
        'expense|movement'      => array('debit' => '5112', 'credit' => '2101'),
        'expense|transport'     => array('debit' => '5112', 'credit' => '2101'),
    );

    /** مفتاحُ الخريطةِ لواقعةٍ — بالوحدةِ أولًا ثم بالمصدر */
    public static function mapKey($eventType, $unit, $sourceModule)
    {
        $t = strtolower(trim((string) $eventType));
        $u = strtolower(trim((string) $unit));
        if ($u !== '' && isset(self::ACCOUNT_MAP["$t|$u"])) { return "$t|$u"; }
        $s = strtolower(trim((string) $sourceModule));
        if ($s !== '' && isset(self::ACCOUNT_MAP["$t|$s"])) { return "$t|$s"; }
        return null;
    }

    /** ──────────────────────────────────────────────────────────────────
     * ① Published ⇐ UnderReview — التوجيهُ لمحاسبِ التخصص
     * ────────────────────────────────────────────────────────────────── */
    public static function reviewPublished($gate, \mysqli $conn, $companyId, $actor, $limit = 100)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);
        $out = array('moved' => 0, 'skipped' => 0, 'failed' => 0, 'reasons' => array());

        /* ◆ لا تُمرَّر واقعةٌ في السلسلةِ إن كانت لا تستطيع بلوغَ الدفتر: الشرطُ
           هنا هو شرطُ الترحيلِ نفسُه (مبلغٌ موجب · فترةٌ مفتوحة · نوعٌ مخرَّط).
           وإلا تراكمت وقائعُ «معتمَدةٌ لا تُرحَّل» — وهي حالةٌ تُقلق ولا تُفيد.
           (قِيست: 472 واقعةً وقفت في Approved بفترةٍ مغلقةٍ قبلَ هذا الشرط.) */
        $keys = array();
        foreach (array_keys(self::ACCOUNT_MAP) as $k) {
            list($t, $u) = explode('|', $k);
            $t = $conn->real_escape_string($t); $u = $conn->real_escape_string($u);
            /* يُصيب بالوحدةِ أو بالمصدر — كما في mapKey تمامًا، وإلا اختلف
               شرطُ الأهليةِ عن شرطِ الترحيلِ فتراكم معتمَدٌ لا يُرحَّل. */
            $keys[] = "(LOWER(e.event_type)='$t' AND (LOWER(e.unit)='$u' OR LOWER(e.source_module)='$u'))";
        }
        $typeCond = $keys ? '(' . implode(' OR ', $keys) . ')' : '0';

        $rs = $conn->query("SELECT e.id, e.event_no, e.event_type, e.amount, e.accountant_id
                            FROM fin_financial_events e
                            WHERE e.company_id = $companyId AND e.fes_status = 'Published'
                              AND COALESCE(e.is_deleted,0) = 0
                              AND e.amount > 0
                              AND $typeCond
                              AND EXISTS (SELECT 1 FROM fin_financial_periods p
                                          WHERE p.company_id = e.company_id AND p.period_type = 'month'
                                            AND p.posting_allowed = 1
                                            AND DATE(e.occurred_at) BETWEEN p.start_date AND p.end_date)
                            ORDER BY e.id LIMIT $limit");
        if (!$rs) { return $out; }
        while ($e = $rs->fetch_assoc()) {
            $r = EventStateMachine::transition($gate, $conn, (int) $e['id'], 'UnderReview', $actor);
            if (!empty($r['ok'])) { $out['moved']++; }
            else {
                $out['failed']++;
                $why = implode(' · ', (array) ($r['reasons'] ?? array('سببٌ غيرُ معلوم')));
                $out['reasons'][$why] = ($out['reasons'][$why] ?? 0) + 1;
            }
        }
        return $out;
    }

    /** ──────────────────────────────────────────────────────────────────
     * ② UnderReview ⇐ Approved — بالسقفِ المُعرَّفِ في المصفوفة
     * ────────────────────────────────────────────────────────────────── */
    public static function approveReviewed($gate, \mysqli $conn, $companyId, $actor, $limit = 100)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);
        $out = array('moved' => 0, 'held' => 0, 'failed' => 0, 'levels' => array(), 'reasons' => array());

        $rs = $conn->query("SELECT id, event_no, event_type, amount, currency
                            FROM fin_financial_events
                            WHERE company_id = $companyId AND fes_status = 'UnderReview'
                              AND COALESCE(is_deleted,0) = 0
                            ORDER BY id LIMIT $limit");
        if (!$rs) { return $out; }
        while ($e = $rs->fetch_assoc()) {
            $lvl = self::requiredLevel($conn, $companyId, (string) $e['event_type'], (float) $e['amount']);
            if ($lvl === null) {
                $out['held']++;
                $out['reasons']['لا قاعدةَ اعتمادٍ تغطي هذا المبلغ'] =
                    ($out['reasons']['لا قاعدةَ اعتمادٍ تغطي هذا المبلغ'] ?? 0) + 1;
                continue;
            }
            $out['levels'][$lvl] = ($out['levels'][$lvl] ?? 0) + 1;
            $r = EventStateMachine::transition($gate, $conn, (int) $e['id'], 'Approved', $actor);
            if (!empty($r['ok'])) {
                $out['moved']++;
                $conn->query("UPDATE fin_financial_events
                              SET approved_by = " . (int) $actor . ", approved_at = NOW()
                              WHERE id = " . (int) $e['id']);
            } else {
                $out['failed']++;
                $why = implode(' · ', (array) ($r['reasons'] ?? array('سببٌ غيرُ معلوم')));
                $out['reasons'][$why] = ($out['reasons'][$why] ?? 0) + 1;
            }
        }
        return $out;
    }

    /** مستوى الاعتمادِ المطلوبُ من `fin_approval_matrix` — قراءةٌ لا اختراع */
    public static function requiredLevel(\mysqli $conn, $companyId, $eventType, $amount)
    {
        $st = $conn->prepare("SELECT required_level FROM fin_approval_matrix
                              WHERE company_id = ? AND active = 1
                                AND (event_type = ? OR event_type = 'any')
                                AND ? >= min_amount AND ? < max_amount
                              ORDER BY (event_type = 'any'), sequence LIMIT 1");
        if (!$st) { return null; }
        $c = (int) $companyId; $a = (float) $amount;
        $st->bind_param('isdd', $c, $eventType, $a, $a);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (string) $row['required_level'] : null;
    }

    /** ──────────────────────────────────────────────────────────────────
     * ③ Approved ⇐ Posted — القيدُ المتوازن
     * ────────────────────────────────────────────────────────────────── */
    public static function postApproved($gate, \mysqli $conn, $companyId, $actor, $limit = 100)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);
        $out = array('posted' => 0, 'skipped' => 0, 'failed' => 0,
                     'debit_total' => 0.0, 'credit_total' => 0.0, 'reasons' => array());
        $note = function (&$o, $k) { $o['reasons'][$k] = ($o['reasons'][$k] ?? 0) + 1; };

        /* ◆ لا يستهلكِ السقفَ ما لا يُرحَّل: المعتمَدُ في فترةٍ مغلقةٍ حالةٌ مشروعةٌ
           (اعتُمد وينتظر فتحَ فترتِه) — لكنه لو دخل الاختيارَ سدَّ الطابورَ على
           المؤهَّل. (قِيست: 472 عالقًا ابتلعت سقفَ 500 فلم يُرحَّل إلا 28.) */
        $rs = $conn->query("SELECT e.id, e.event_no, e.event_type, e.unit, e.amount, e.base_amount,
                                   e.currency, e.fx_rate, e.occurred_at, e.project_id, e.equipment_id,
                                   e.contract_id, e.customer_entity_id, e.journal_entry_id, e.source_module
                            FROM fin_financial_events e
                            WHERE e.company_id = $companyId
                              AND e.fes_status IN ('Approved','RetryPending')
                              AND COALESCE(e.is_deleted,0) = 0
                              AND e.amount > 0
                              AND EXISTS (SELECT 1 FROM fin_financial_periods p
                                          WHERE p.company_id = e.company_id AND p.period_type = 'month'
                                            AND p.posting_allowed = 1
                                            AND DATE(e.occurred_at) BETWEEN p.start_date AND p.end_date)
                            ORDER BY e.id LIMIT $limit");
        if (!$rs) { return $out; }

        while ($e = $rs->fetch_assoc()) {
            /* عطالة: قيدٌ قائمٌ ⇒ لا يُكرَّر */
            if (!empty($e['journal_entry_id'])) { $out['skipped']++; $note($out, 'له قيدٌ سلفًا'); continue; }

            $amount = (float) $e['amount'];
            if ($amount <= 0) { $out['skipped']++; $note($out, 'مبلغٌ صفريٌّ أو سالب'); continue; }

            $key = self::mapKey($e['event_type'], $e['unit'], $e['source_module']);
            if ($key === null) {
                self::fail($gate, $conn, (int) $e['id'], $actor);
                $out['failed']++;
                $note($out, 'لا قاعدةَ ترحيلٍ لـ' . $e['event_type'] . '|' . ($e['unit'] ?: $e['source_module'] ?: '—'));
                continue;
            }
            $map = self::ACCOUNT_MAP[$key];

            $date = substr((string) $e['occurred_at'], 0, 10);
            if (!self::periodOpen($conn, $companyId, $date)) {
                $out['skipped']++; $note($out, 'فترةٌ مغلقةٌ للترحيل'); continue;
            }
            $dr = self::accountId($conn, $companyId, $map['debit']);
            $cr = self::accountId($conn, $companyId, $map['credit']);
            if (!$dr || !$cr) {
                self::fail($gate, $conn, (int) $e['id'], $actor);
                $out['failed']++; $note($out, 'حسابٌ غيرُ قابلٍ للترحيلِ أو غيرُ موجود');
                continue;
            }

            $amt = round($amount, 2);
            $entryId = 0;
            $gate->runInTransaction(function ($g) use (&$entryId, $companyId, $e, $amt, $dr, $cr, $date, $actor) {
                $entryId = (int) $g->insert('fin_journal_entries', array(
                    'entry_no'     => 'TMP-' . uniqid('', true),
                    'event_id'     => (int) $e['id'],
                    'posting_date' => $date,
                    'txn_date'     => $date,
                    'currency'     => (string) $e['currency'],
                    'fx_rate'      => $e['fx_rate'] !== null ? (float) $e['fx_rate'] : null,
                    'base_amount'  => $e['base_amount'] !== null ? (float) $e['base_amount'] : null,
                    'total_debit'  => $amt,
                    'total_credit' => $amt,
                    'memo'         => 'ترحيلٌ آليٌّ من الواقعة ' . $e['event_no'],
                    'state'        => 'posted',
                    'posted_by'    => (int) $actor ?: null,
                    'posted_at'    => date('Y-m-d H:i:s'),
                    'created_by'   => (int) $actor ?: null,
                ));
                if ($entryId <= 0) { throw new \RuntimeException('PostingService: فشل إدراجُ القيد'); }
                $g->update('fin_journal_entries',
                    array('entry_no' => 'JV-' . str_pad((string) $entryId, 6, '0', STR_PAD_LEFT)),
                    array('id' => $entryId));

                $base = array('entry_id' => $entryId, 'project_id' => $e['project_id'] ?: null,
                              'equipment_id' => $e['equipment_id'] ?: null,
                              'contract_id' => $e['contract_id'] ?: null,
                              'counterparty_type' => 'client',
                              'counterparty_id' => $e['customer_entity_id'] ?: null,
                              'memo' => 'ترحيلٌ آليٌّ من الواقعة ' . $e['event_no']);
                $g->insert('fin_journal_lines', $base + array('account_id' => $dr, 'debit' => $amt, 'credit' => 0));
                $g->insert('fin_journal_lines', $base + array('account_id' => $cr, 'debit' => 0, 'credit' => $amt));
            });

            $t = EventStateMachine::transition($gate, $conn, (int) $e['id'], 'Posted', $actor);
            if (empty($t['ok'])) {
                $out['failed']++;
                $note($out, 'القيدُ كُتب والحالةُ لم تنتقل: ' . implode(' · ', (array) ($t['reasons'] ?? array())));
                continue;
            }
            $conn->query("UPDATE fin_financial_events
                          SET journal_entry_id = $entryId, posted_by = " . (int) $actor . ", posted_at = NOW()
                          WHERE id = " . (int) $e['id']);
            $out['posted']++;
            $out['debit_total'] += $amt;
            $out['credit_total'] += $amt;
        }
        return $out;
    }

    /** ──────────────────────────────────────────────────────────────────
     * ③-ب PostingFailed ⇐ RetryPending — إعادةُ ما رسب بعدَ زوالِ سببِه
     * ────────────────────────────────────────────────────────────────────
     * الرسوبُ ليس حكمًا نهائيًّا: واقعةٌ رسبت لغيابِ خريطةِ حسابٍ ثم أُضيفت
     * الخريطةُ يجب أن تُعاد، وإلا بقي الرسوبُ أثرًا لسببٍ زال. وآلةُ الحالاتِ
     * تسمح PostingFailed ⇐ RetryPending ⇐ Posted — والمسارُ كان معرَّفًا بلا نداء.
     * ◆ ولا يُعاد إلا ما زال سببُه فعلًا: خريطةٌ مُصيبةٌ · فترةٌ مفتوحة · مبلغٌ موجب.
     */
    public static function retryFailed($gate, \mysqli $conn, $companyId, $actor, $limit = 100)
    {
        $companyId = (int) $companyId;
        $limit = max(1, (int) $limit);
        $out = array('requeued' => 0, 'still_blocked' => 0, 'reasons' => array());

        $rs = $conn->query("SELECT id, event_type, unit, source_module, amount, occurred_at
                            FROM fin_financial_events
                            WHERE company_id = $companyId AND fes_status = 'PostingFailed'
                              AND COALESCE(is_deleted,0) = 0
                            ORDER BY id LIMIT $limit");
        if (!$rs) { return $out; }
        while ($e = $rs->fetch_assoc()) {
            $why = null;
            if ((float) $e['amount'] <= 0)                                        { $why = 'مبلغٌ صفريٌّ أو سالب'; }
            elseif (empty($e['occurred_at']))                                     { $why = 'بلا تاريخِ وقوعٍ — لا فترةَ لها'; }
            elseif (self::mapKey($e['event_type'], $e['unit'], $e['source_module']) === null) { $why = 'ما زال بلا خريطةِ حساب'; }
            elseif (!self::periodOpen($conn, $companyId, substr((string) $e['occurred_at'], 0, 10))) { $why = 'فترتُها ما زالت مغلقة'; }

            if ($why !== null) {
                $out['still_blocked']++;
                $out['reasons'][$why] = ($out['reasons'][$why] ?? 0) + 1;
                continue;
            }
            $r = EventStateMachine::transition($gate, $conn, (int) $e['id'], 'RetryPending', $actor);
            if (!empty($r['ok'])) { $out['requeued']++; }
            else {
                $out['still_blocked']++;
                $k = 'تعذّر الانتقال: ' . implode(' · ', (array) ($r['reasons'] ?? array()));
                $out['reasons'][$k] = ($out['reasons'][$k] ?? 0) + 1;
            }
        }
        return $out;
    }

    /** ──────────────────────────────────────────────────────────────────
     * ④ Posted ⇐ Reversed — عكسُ قيدٍ رُحِّل، بحركةٍ مقابلةٍ لا بحذف
     * ────────────────────────────────────────────────────────────────────
     * رُحِّل 1,115 قيدًا ولم يكن لعكسِ واحدٍ منها سبيل — وهي ثغرةٌ في مسارِ
     * الترحيلِ نفسِه لا في غيرِه: من يكتب في دفترٍ يلزمه بابُ خروجٍ من يومِه الأول.
     *
     * ◆ ولا يُعاد بناءُ ما هو مبنيّ: `CompensationService::reverseEvent` يُنشئ
     *   **الواقعةَ المعوِّضةَ** (مبلغٌ سالبٌ · `reverses_event_id` · عطالةٌ بمفتاح
     *   `rev:`) — لكنه لا يمسُّ الدفترَ لأنه سابقٌ لوجودِ مسارِ الترحيل. فتُنادى
     *   كما هي ويُضاف إليها الشقُّ الدفتريُّ هنا:
     *     · قيدٌ عاكسٌ بمدينٍ ودائنٍ **متبادلَين** ومبلغٍ موجب
     *     · يُعلَّق على **الواقعةِ المعوِّضة** لا على الأصل — فيبقى «لكلِّ واقعةٍ
     *       قيدٌ واحد» صحيحًا، ويبقى «لا قيدَ لواقعةٍ ليست Posted» صحيحًا
     *     · والأصلُ ينتقل إلى Reversed بآلةِ الحالاتِ لا بكتابةٍ مباشرة
     * ◆ والأصلُ لا يُحذف ولا يُعدَّل مبلغُه: يبقى في الدفترِ ويقابله عاكسُه.
     */
    public static function reversePosted($gate, \mysqli $conn, $companyId, $eventId, $reason, $actor)
    {
        $companyId = (int) $companyId;
        $eventId   = (int) $eventId;
        $reason    = trim((string) $reason);
        if ($reason === '') {
            return array('ok' => false, 'code' => 422, 'reasons' => array('سببُ العكسِ إلزاميّ — لا عكسَ بلا سببٍ مكتوب'));
        }

        $st = $conn->prepare("SELECT * FROM fin_financial_events WHERE id=? AND company_id=? LIMIT 1");
        $st->bind_param('ii', $eventId, $companyId);
        $st->execute();
        $orig = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$orig) { return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعةُ غيرُ موجودةٍ في كيانِك')); }
        if ((string) $orig['fes_status'] !== 'Posted') {
            return array('ok' => false, 'code' => 409,
                         'reasons' => array('الواقعةُ في حالة «' . $orig['fes_status'] . '» — العكسُ الدفتريُّ للمُرحَّلِ وحدَه'));
        }
        if (empty($orig['journal_entry_id'])) {
            return array('ok' => false, 'code' => 409, 'reasons' => array('واقعةٌ Posted بلا قيد — حالةٌ لا تُعكس بل تُصحَّح'));
        }
        /* ◆ ولا يُعكس عاكس: الواقعةُ المعوِّضةُ تُصيَّر Posted كي يصحَّ قيدُها،
           فتبدو صالحةً للعكس — وعكسُها يُنشئ سلسلةً بلا معنى (عكسُ عكسٍ لعكس)
           ومبلغُها **سالبٌ** فيُنتج رأسَ قيدٍ سالبًا يخالف سطورَه الموجبة.
           (قِيس: سلسلةُ 8577 ⇐ 17982 ⇐ 17983 ورأسٌ بـ-954.80 مقابلَ سطرين موجبين.)
           فمن أراد إلغاءَ عكسٍ فذاك تصحيحُ قيدٍ لا عكسٌ ثانٍ. */
        if (!empty($orig['reverses_event_id'])) {
            return array('ok' => false, 'code' => 409,
                         'reasons' => array('هذه واقعةٌ معوِّضةٌ (تعكس #' . (int) $orig['reverses_event_id']
                                          . ') — ولا يُعكس عاكس'));
        }
        $date = substr((string) $orig['occurred_at'], 0, 10);
        if (!self::periodOpen($conn, $companyId, $date)) {
            return array('ok' => false, 'code' => 409,
                         'reasons' => array('فترةُ الأصلِ مغلقةٌ — العكسُ في فترةٍ مغلقةٍ قرارُ إقفالٍ لا قرارُ شاشة'));
        }

        /* سطرا الأصلِ — منهما يُبنى العاكسُ بالتبادل */
        $rs = $conn->query("SELECT account_id, debit, credit FROM fin_journal_lines
                            WHERE entry_id = " . (int) $orig['journal_entry_id']);
        $lines = array();
        while ($rs && ($l = $rs->fetch_assoc())) { $lines[] = $l; }
        if (count($lines) < 2) { return array('ok' => false, 'code' => 409, 'reasons' => array('قيدُ الأصلِ بأقلَّ من سطرين')); }

        /* ① الواقعةُ المعوِّضةُ — بالخدمةِ القائمةِ لا ببناءٍ جديد */
        require_once dirname(__DIR__) . '/CompensationService.php';
        try {
            $cmp = \App\Services\CompensationService::reverseEvent($conn, $eventId, $reason, (int) $actor);
        } catch (\Throwable $e) {
            return array('ok' => false, 'code' => 500, 'reasons' => array('تعذّر إنشاءُ الواقعةِ المعوِّضة: ' . $e->getMessage()));
        }
        $revEventId = (int) ($cmp['reversal_id'] ?? 0);
        if ($revEventId <= 0) { return array('ok' => false, 'code' => 500, 'reasons' => array('الواقعةُ المعوِّضةُ لم تُنشأ')); }
        if (!empty($cmp['duplicate'])) {
            $q = $conn->query("SELECT journal_entry_id FROM fin_financial_events WHERE id=$revEventId");
            $j = $q ? (int) ($q->fetch_row()[0] ?? 0) : 0;
            if ($j > 0) {
                return array('ok' => true, 'code' => 200, 'duplicate' => true,
                             'reversal_event_id' => $revEventId, 'reversal_entry_id' => $j, 'reasons' => array());
            }
        }

        /* ② القيدُ العاكسُ — مدينٌ ودائنٌ متبادلان */
        /* المبلغُ **مطلقٌ دائمًا**: رأسُ القيدِ مجموعُ مدينٍ ودائنٍ موجبين،
           والاتجاهُ يعبّر عنه تبادلُ السطرين لا إشارةُ الرأس. */
        $amt = round(abs((float) $orig['amount']), 2);
        $revEntryId = 0;
        $gate->runInTransaction(function ($g) use (&$revEntryId, $orig, $lines, $amt, $date, $actor, $revEventId, $reason) {
            $revEntryId = (int) $g->insert('fin_journal_entries', array(
                'entry_no'     => 'TMP-' . uniqid('', true),
                'event_id'     => $revEventId,
                'posting_date' => $date,
                'txn_date'     => $date,
                'currency'     => (string) $orig['currency'],
                'total_debit'  => $amt,
                'total_credit' => $amt,
                'memo'         => 'عكسُ القيد #' . $orig['journal_entry_id'] . ' — ' . mb_substr($reason, 0, 120),
                'state'        => 'posted',
                'posted_by'    => (int) $actor ?: null,
                'posted_at'    => date('Y-m-d H:i:s'),
                'created_by'   => (int) $actor ?: null,
            ));
            if ($revEntryId <= 0) { throw new \RuntimeException('reversePosted: فشل إدراجُ القيدِ العاكس'); }
            $g->update('fin_journal_entries',
                array('entry_no' => 'RJV-' . str_pad((string) $revEntryId, 6, '0', STR_PAD_LEFT)),
                array('id' => $revEntryId));

            foreach ($lines as $l) {
                $g->insert('fin_journal_lines', array(
                    'entry_id'   => $revEntryId,
                    'account_id' => (int) $l['account_id'],
                    'debit'      => round((float) $l['credit'], 2),   // التبادل
                    'credit'     => round((float) $l['debit'], 2),
                    'memo'       => 'عكسٌ آليٌّ للقيد #' . $orig['journal_entry_id'],
                ));
            }
        });

        /* ③ الواقعةُ المعوِّضةُ تصير Posted بقيدِها · والأصلُ Reversed */
        $conn->query("UPDATE fin_financial_events
                      SET journal_entry_id = $revEntryId, fes_status = 'Posted', state = 'posted',
                          posted_by = " . (int) $actor . ", posted_at = NOW()
                      WHERE id = $revEventId");
        $t = EventStateMachine::transition($gate, $conn, $eventId, 'Reversed', $actor);
        if (empty($t['ok'])) {
            return array('ok' => false, 'code' => 500,
                         'reasons' => array('القيدُ العاكسُ كُتب والأصلُ لم ينتقل: ' . implode(' · ', (array) ($t['reasons'] ?? array()))),
                         'reversal_event_id' => $revEventId, 'reversal_entry_id' => $revEntryId);
        }

        return array('ok' => true, 'code' => 200, 'duplicate' => false,
                     'reversal_event_id' => $revEventId, 'reversal_entry_id' => $revEntryId, 'reasons' => array());
    }

    /* ── مساعداتٌ صغيرة ───────────────────────────────────────────── */

    private static function fail($gate, \mysqli $conn, $eventId, $actor)
    {
        EventStateMachine::transition($gate, $conn, $eventId, 'PostingFailed', $actor);
    }

    public static function periodOpen(\mysqli $conn, $companyId, $date)
    {
        $st = $conn->prepare("SELECT 1 FROM fin_financial_periods
                              WHERE company_id = ? AND period_type = 'month'
                                AND posting_allowed = 1 AND ? BETWEEN start_date AND end_date LIMIT 1");
        if (!$st) { return false; }
        $c = (int) $companyId;
        $st->bind_param('is', $c, $date);
        $st->execute();
        $hit = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $hit;
    }

    public static function accountId(\mysqli $conn, $companyId, $code)
    {
        $st = $conn->prepare("SELECT id FROM fin_chart_of_accounts
                              WHERE company_id = ? AND code = ? AND is_postable = 1
                                AND active = 1 AND COALESCE(is_deleted,0) = 0 LIMIT 1");
        if (!$st) { return 0; }
        $c = (int) $companyId;
        $st->bind_param('is', $c, $code);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return $row ? (int) $row[0] : 0;
    }
}
