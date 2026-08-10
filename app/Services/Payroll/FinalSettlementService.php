<?php
/**
 * app/Services/Payroll/FinalSettlementService.php — تصفيةُ إنهاء الخدمة (M-22)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §5-التصفية: «إنهاءُ العقد يفتح **دورةَ تصفيةٍ خاصة**: **المستحقُّ حتى
 * تاريخ الأثر** · **رصيدُ الإجازات** · **نهايةُ الخدمة بقاعدتها من اللقطة** ·
 * **تسويةُ السلف والعهد** — بحدثٍ ماليٍّ واحدٍ بمفتاح (**العقد × التصفية**)».
 * §6: «بنودُ التصفية **محسوبةً من اللقطة** · الرصيدُ الصافي **بعد المقاصّة**».
 * §8.1-E6: «Event: **SettlementFinalized مرةً**».
 *
 * ── أربعُ قواعدَ تحكم كلَّ رقمٍ هنا ─────────────────────────────────────────
 * ① **محسوبٌ لا مُدخَل**: لا حقلَ لكتابة صافٍ ولا بندٍ — كلُّ رقمٍ من مصدره،
 *    و`worker_settlement` اليدويُّ **يُترك حيث هو** ولا يُبنى عليه.
 * ② **بلا قاعدةٍ مكتوبةٍ لا احتساب**: أيامُ نهاية الخدمة والإجازة **تُكتب في
 *    العقد**، وبغيرها يظهر البندُ **`computable=0` بسببه** ولا يُقدَّر بصفرٍ صامت.
 * ③ **الأساسُ من علَمِه في اللقطة**: نهايةُ الخدمة على مكوّنات `in_eos`،
 *    والإجازةُ على `in_leave_pay` — **من `snapshot_json` لا من الجداول الحية**؛
 *    وطريقةُ حسابٍ لا يفهمها المحرّك **تُعلَن** ولا تُبتلع صامتةً.
 * ④ **والمقاصّةُ ظاهرةٌ ومحدودة**: السلفُ بندٌ باسمه يُطرح — **ولا يُقاصّ أكثرُ
 *    من المستحق**، والباقي يبقى رصيدًا مفتوحًا **يُعلَن**.
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once dirname(__DIR__) . '/Contract/ContractSnapshotService.php';

use App\Services\Contract\ContractSnapshotService;

class FinalSettlementService
{
    /** الحالاتُ التي تُفتح منها التصفية — «إنهاءُ العقد يفتح» (CON-01 §4). */
    const CLOSABLE_STATES = array('terminated', 'expired', 'closed');

    /** أيامُ الشهر المتفَّق عليها لاشتقاق الأجر اليومي من الشهري. */
    const MONTH_DAYS = 30.0;

    /**
     * أنواعُ الغياب التي **تستهلك رصيدَ الإجازة الاعتيادية**.
     * «تبادلية» دورةُ تناوبٍ لا إجازةٌ سنوية · و«مأمورية» عملٌ لا إجازة ·
     * والطارئُ بابُه خصمُ الغياب في المسيّر (H-09-②) لا رصيدُ الإجازة.
     */
    const LEAVE_TYPES = array('اعتيادية');

    /** حالاتُ سجل الإجازة التي تُعدُّ **واقعةً** لا طلبًا. */
    const LEAVE_STATES = array('معتمد', 'منتهٍ', 'مغلق');

    /** طرائقُ الحساب التي يفهمها المحرّك من اللقطة — وما عداها **يُعلَن**. */
    const KNOWN_METHODS = array('fixed_amount', 'pct_basic');

    // ═════════════════════════════════════════════════════════════════════
    // ① الاحتساب — قراءةٌ خالصةٌ تصلح للمعاينة كما تصلح للقرار
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,lines:array,totals:array,
     *               basis:array,snapshot_id:?int,fingerprint:?string}
     */
    public static function compute($conn, $gate, $companyId, $contractId, $effectiveDate, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'lines' => array(),
                     'totals' => array('dues' => 0.0, 'leave' => 0.0, 'eos' => 0.0,
                                       'advances' => 0.0, 'advances_remaining' => 0.0,
                                       'recognized' => 0.0, 'net' => 0.0),
                     'basis' => array(), 'snapshot_id' => null, 'fingerprint' => null);

        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'عقدُ الموظف غيرُ موجودٍ في نطاقك'; return $out; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $effectiveDate)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الأثر إلزاميٌّ بصيغة Y-m-d'; return $out;
        }

        $start = (string) $c['start_date'];
        if ($start === '' || $start === '0000-00-00') {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ بلا تاريخِ بداية — ومدةُ الخدمة لا تُقدَّر (§5)';
            return $out;
        }
        if ($effectiveDate < $start) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الأثر قبل بداية العقد'; return $out;
        }
        $days  = (strtotime($effectiveDate) - strtotime($start)) / 86400.0;
        $years = round($days / 365.25, 3);

        // ── ③ الأسسُ **من اللقطة** (H-11 · ENT-01 §2) ─────────────────────
        $snap = ContractSnapshotService::snapshotForSettlement(
            $conn, $gate, $companyId, (int) $c['id'], (string) $effectiveDate, $actor);
        if (!$snap['ok']) {
            $out['code'] = $snap['code'];
            $out['reason'] = 'لا لقطةَ صالحةً للاحتساب — «بقاعدتها **من اللقطة**» (§5): ' . $snap['reason'];
            return $out;
        }
        $out['snapshot_id'] = $snap['id'];
        $out['fingerprint'] = $snap['fingerprint'];

        $payload = ContractSnapshotService::payloadOf($gate, (int) $snap['id']);
        $components = ($payload && isset($payload['components']) && is_array($payload['components']))
                      ? $payload['components'] : array();

        $eos   = self::componentBase($components, 'in_eos');
        $leave = self::componentBase($components, 'in_leave_pay');

        $basis = array(
            'start_date' => $start, 'effective_date' => (string) $effectiveDate,
            'service_years' => $years,
            'snapshot_id' => (int) $snap['id'], 'fingerprint' => (string) $snap['fingerprint'],
            'snapshot_minted' => !empty($snap['minted']),
            'eos_monthly_base' => $eos['base'], 'leave_monthly_base' => $leave['base'],
            'eos_days_per_year' => $c['eos_days_per_year'] !== null ? (float) $c['eos_days_per_year'] : null,
            'leave_days_per_year' => $c['leave_days_per_year'] !== null ? (float) $c['leave_days_per_year'] : null,
            'month_days' => self::MONTH_DAYS,
            'unreadable_components' => array_merge($eos['unreadable'], $leave['unreadable']),
        );

        // ── المستحقُّ حتى تاريخ الأثر ───────────────────────────────────────
        $dues = self::openDues($gate, (int) $c['employee_id'], (string) $effectiveDate);
        $out['lines'][] = array(
            'line_type' => 'dues', 'computable' => 1,
            'description' => 'المستحقُّ حتى تاريخ الأثر',
            'qty' => null, 'rate' => null, 'amount' => $dues,
            'source_note' => 'ذممٌ دائنةٌ غيرُ مسوّاةٍ في `fin_dues` حتى ' . $effectiveDate
                           . ' — **اعتُرف بها في مصدرها** فتُعرض ولا يُعاد الاعترافُ بها');

        // ── رصيدُ الإجازات ──────────────────────────────────────────────────
        $leaveAmount = 0.0; $leaveDays = null; $leaveRate = null; $leaveOk = 1; $leaveNote = '';
        if ($basis['leave_days_per_year'] === null) {
            $leaveOk = 0;
            $leaveNote = '⚠ **لا قاعدةَ أيامِ إجازةٍ مكتوبةً في العقد** — لا تُحتسب ولا تُقدَّر';
        } elseif ($leave['base'] <= 0) {
            $leaveOk = 0;
            $leaveNote = '⚠ **لا مكوّنَ أجرٍ بعلَم `in_leave_pay` في اللقطة** — فلا أساسَ لأجر الإجازة';
        } else {
            $accrued   = round($basis['leave_days_per_year'] * $years, 2);
            $taken     = self::leaveDaysTaken($gate, (int) $c['employee_id'], $start, (string) $effectiveDate);
            $leaveDays = round(max(0.0, $accrued - $taken), 2);
            $leaveRate = round($leave['base'] / self::MONTH_DAYS, 2);
            $leaveAmount = round($leaveDays * $leaveRate, 2);
            $leaveNote = 'مستحقٌّ ' . $accrued . ' يومًا − مأخوذٌ ' . $taken . ' = ' . $leaveDays
                       . ' × أجرٍ يوميٍّ ' . $leaveRate . ' (أساسُ `in_leave_pay` ' . $leave['base'] . ')';
        }
        $out['lines'][] = array(
            'line_type' => 'leave', 'computable' => $leaveOk,
            'description' => 'رصيدُ الإجازات', 'qty' => $leaveDays, 'rate' => $leaveRate,
            'amount' => $leaveAmount, 'source_note' => $leaveNote);

        // ── نهايةُ الخدمة بقاعدتها ─────────────────────────────────────────
        $eosAmount = 0.0; $eosDays = null; $eosRate = null; $eosOk = 1; $eosNote = '';
        if ($basis['eos_days_per_year'] === null) {
            $eosOk = 0;
            $eosNote = '⚠ **لا قاعدةَ نهاية خدمةٍ مكتوبةً في العقد** — لا تُحتسب ولا تُقدَّر';
        } elseif ($eos['base'] <= 0) {
            $eosOk = 0;
            $eosNote = '⚠ **لا مكوّنَ أجرٍ بعلَم `in_eos` في اللقطة** — فلا أساسَ لنهاية الخدمة';
        } else {
            $eosDays = round($basis['eos_days_per_year'] * $years, 2);
            $eosRate = round($eos['base'] / self::MONTH_DAYS, 2);
            $eosAmount = round($eosDays * $eosRate, 2);
            $eosNote = $basis['eos_days_per_year'] . ' يومًا × ' . $years . ' سنةَ خدمةٍ = ' . $eosDays
                     . ' × أجرٍ يوميٍّ ' . $eosRate . ' (أساسُ `in_eos` ' . $eos['base']
                     . ' · لقطة #' . (int) $snap['id'] . ')';
        }
        $out['lines'][] = array(
            'line_type' => 'eos', 'computable' => $eosOk,
            'description' => 'نهايةُ الخدمة', 'qty' => $eosDays, 'rate' => $eosRate,
            'amount' => $eosAmount, 'source_note' => $eosNote);

        // ── ④ المقاصّةُ ظاهرةٌ ومحدودة ─────────────────────────────────────
        $entitled  = round($dues + $leaveAmount + $eosAmount, 2);
        $balance   = self::advanceBalance($gate, (int) $c['employee_id']);
        $offset    = round(min($balance, $entitled), 2);
        $remaining = round($balance - $offset, 2);
        $offNote = 'رصيدٌ مفتوحٌ في `employee_advances` ' . $balance
                 . ' — **يُطرح ظاهرًا لا صامتًا**';
        if ($remaining > 0) {
            $offNote .= ' · **والمقاصّةُ لا تتجاوز المستحقَّ ' . $entitled . '**: يبقى '
                      . $remaining . ' رصيدًا مفتوحًا يُعلَن ولا يُسقَط';
        }
        $out['lines'][] = array(
            'line_type' => 'advance_offset', 'computable' => 1,
            'description' => 'مقاصّةُ السلف والعهد', 'qty' => null, 'rate' => null,
            'amount' => $offset, 'source_note' => $offNote);

        $out['totals']['dues'] = $dues;
        $out['totals']['leave'] = $leaveAmount;
        $out['totals']['eos'] = $eosAmount;
        $out['totals']['advances'] = $offset;
        $out['totals']['advances_remaining'] = $remaining;
        // ما تعترف به التصفيةُ **جديدًا** — والمستحقُّ السابقُ اعتُرف به في مصدره
        $out['totals']['recognized'] = round($leaveAmount + $eosAmount, 2);
        $out['totals']['net'] = round($entitled - $offset, 2);
        $out['basis'] = $basis;
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الفتح والاعتماد
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,settlement_id:?int,net:float} */
    public static function open($conn, $gate, $companyId, $contractId, $effectiveDate, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'settlement_id' => null, 'net' => 0.0);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'عقدُ الموظف غيرُ موجودٍ في نطاقك'; return $out; }

        if (!in_array((string) $c['state'], self::CLOSABLE_STATES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ في حالة «' . $c['state'] . '» — و**التصفيةُ يفتحها الإنهاء** '
                           . '(ENT-01 §5): أنهِ العقدَ ثم صفِّ';
            return $out;
        }
        $ex = self::byContract($gate, (int) $contractId);
        if ($ex) {
            $out['code'] = 409; $out['settlement_id'] = (int) $ex['id'];
            $out['reason'] = 'للعقد تصفيةٌ قائمةٌ #' . $ex['id'] . ' («بمفتاح العقد × التصفية»)';
            return $out;
        }

        $calc = self::compute($conn, $gate, $companyId, (int) $contractId, $effectiveDate, $actor);
        if (!$calc['ok']) { return array_merge($out, array('code' => $calc['code'], 'reason' => $calc['reason'])); }

        $sid = null;
        try {
            $gate->runInTransaction(function ($g) use (&$sid, $c, $calc, $effectiveDate, $actor) {
                $sid = (int) $g->insert('employee_final_settlements', array(
                    'contract_id'       => (int) $c['id'],
                    'employee_id'       => (int) $c['employee_id'],
                    'effective_date'    => (string) $effectiveDate,
                    'currency'          => ($c['currency'] !== null && (string) $c['currency'] !== '')
                                           ? (string) $c['currency'] : 'SDG',
                    'service_years'     => $calc['basis']['service_years'],
                    'dues_amount'       => $calc['totals']['dues'],
                    'leave_amount'      => $calc['totals']['leave'],
                    'eos_amount'        => $calc['totals']['eos'],
                    'advances_offset'   => $calc['totals']['advances'],
                    'advances_remaining' => $calc['totals']['advances_remaining'],
                    'net_amount'        => $calc['totals']['net'],
                    'recognized_amount' => $calc['totals']['recognized'],
                    'snapshot_id'       => $calc['snapshot_id'],
                    'snapshot_fingerprint' => $calc['fingerprint'],
                    'basis_json'        => json_encode($calc['basis'], JSON_UNESCAPED_UNICODE),
                    'state'             => 'draft',
                    'prepared_by'       => (int) $actor ?: null,
                ));
                if ($sid <= 0) { throw new \RuntimeException('تعذّر إدراجُ رأس التصفية'); }
                foreach ($calc['lines'] as $l) {
                    $g->insert('employee_final_settlement_lines', array(
                        'settlement_id' => $sid,
                        'line_type'     => $l['line_type'],
                        'description'   => mb_substr((string) $l['description'], 0, 255),
                        'qty'           => $l['qty'],
                        'rate'          => $l['rate'],
                        'amount'        => $l['amount'],
                        'computable'    => (int) $l['computable'],
                        'source_note'   => mb_substr((string) $l['source_note'], 0, 255),
                    ));
                }
            }, 'تصفية إنهاء خدمة عقد ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'create', (int) $sid, array(),
            array('contract_id' => (int) $contractId, 'net' => $calc['totals']['net'],
                  'snapshot_id' => $calc['snapshot_id']));
        $out['ok'] = true; $out['code'] = 200; $out['settlement_id'] = (int) $sid;
        $out['net'] = $calc['totals']['net'];
        return $out;
    }

    /**
     * الاعتماد — وبه يقع **الحدثُ الماليُّ الواحد** (§5).
     * @return array{ok:bool,code:int,reason:string,due_id:?int,recovered:float}
     */
    public static function approve($conn, $gate, $companyId, $settlementId, $clearanceDoc, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'due_id' => null, 'recovered' => 0.0);
        $s = self::head($gate, (int) $settlementId);
        if (!$s) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $s['state'] !== 'draft') {
            $out['code'] = 409; $out['reason'] = 'التصفيةُ ليست مسودةً (حالُها: ' . $s['state'] . ')'; return $out;
        }
        if ($s['net_due_ref'] !== null) {
            $out['code'] = 409; $out['due_id'] = (int) $s['net_due_ref'];
            $out['reason'] = 'للتصفية حدثٌ ماليٌّ قائمٌ #' . (int) $s['net_due_ref'] . ' — «لا يتكرر»';
            return $out;
        }
        if ((int) $s['prepared_by'] > 0 && (int) $s['prepared_by'] === (int) $actor) {
            $out['code'] = 403; $out['reason'] = 'لا يعتمد المرءُ ما أعدّ (فصلُ اليدين)'; return $out;
        }
        $doc = trim((string) $clearanceDoc);
        if ($doc === '') {
            $out['code'] = 422; $out['reason'] = '**مرفقُ الإخلاء إلزامي** (ENT-01 §6)'; return $out;
        }

        $recognized = round((float) $s['recognized_amount'], 2);
        $offset     = round((float) $s['advances_offset'], 2);
        $cur = ($s['currency'] !== null && (string) $s['currency'] !== '') ? (string) $s['currency'] : 'SDG';
        $dueId = null; $recovered = 0.0;

        try {
            $gate->runInTransaction(function ($g) use (&$dueId, &$recovered, $s, $recognized,
                                                       $offset, $cur, $doc, $actor) {
                // ① الذمّةُ **باسمها** بما تعترف به التصفيةُ جديدًا
                if ($recognized > 0) {
                    $dueId = (int) $g->insert('fin_dues', array(
                        'party_type' => 'employee', 'party_ref' => (int) $s['employee_id'],
                        'due_type' => 'end_of_service', 'direction' => 'credit',
                        'amount' => $recognized, 'currency' => $cur,
                        'period_ref' => substr((string) $s['effective_date'], 0, 7),
                        'source_doc_type' => 'employee_closure', 'source_doc_id' => (int) $s['id'],
                        'created_by' => (int) $actor ?: null,
                    ));
                    if ($dueId <= 0) { throw new \RuntimeException('تعذّر إنشاءُ ذمّة التصفية'); }
                }
                // ② واستردادُ السلف فعليًّا بمقدار المقاصّة — الأقدمُ أولًا
                $recovered = self::recoverAdvances($g, (int) $s['employee_id'], $offset);
                // ③ ثم الاعتماد
                $g->update('employee_final_settlements', array(
                    'state' => 'approved', 'clearance_doc' => mb_substr($doc, 0, 120),
                    'approved_by' => (int) $actor ?: null, 'approved_at' => date('Y-m-d H:i:s'),
                    'net_due_ref' => $dueId,
                ), array('id' => (int) $s['id']));
            }, 'اعتماد تصفية ' . (int) $s['id']);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الاعتماد: ' . $t->getMessage(); return $out;
        }

        self::finalizedFact($conn, $companyId, $s, $dueId, $recovered, $actor);
        self::audit($conn, $companyId, $actor, 'approve', (int) $s['id'],
            array('state' => 'draft'),
            array('state' => 'approved', 'doc' => $doc, 'due_id' => $dueId, 'recovered' => $recovered));

        $out['ok'] = true; $out['code'] = 200; $out['due_id'] = $dueId; $out['recovered'] = $recovered;
        return $out;
    }

    /** الإلغاء — **بسببٍ مكتوب**، وللمسودة وحدَها (المعتمدةُ تُصحَّح بعكسٍ لا بمحو). */
    public static function cancel($conn, $gate, $companyId, $settlementId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $s = self::head($gate, (int) $settlementId);
        if (!$s) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $s['state'] !== 'draft') {
            $out['code'] = 423;
            $out['reason'] = 'المعتمَدةُ لا تُلغى — التصحيحُ بحركةٍ عاكسةٍ لا بمحوٍ (SPEC-00 §3.1)';
            return $out;
        }
        $why = trim((string) $reason);
        if ($why === '') { $out['code'] = 422; $out['reason'] = 'سببُ الإلغاء إلزامي'; return $out; }
        try {
            $gate->update('employee_final_settlements',
                array('state' => 'cancelled', 'cancel_reason' => mb_substr($why, 0, 255)),
                array('id' => (int) $settlementId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإلغاء: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'cancel', (int) $settlementId,
            array('state' => 'draft'), array('state' => 'cancelled', 'reason' => $why));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ مصادرُ القياس
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أساسٌ شهريٌّ من مكوّنات **اللقطة** بعلَمٍ بعينه — «الأساسُ من علَمِه».
     * وما لا يفهمه المحرّك من طرائق الحساب **يُعلَن** ولا يُبتلع صفرًا صامتًا.
     *
     * @return array{base:float,unreadable:array}
     */
    public static function componentBase(array $components, $flag)
    {
        $res = array('base' => 0.0, 'unreadable' => array());
        if (!in_array($flag, array('in_eos', 'in_leave_pay', 'in_insurance', 'in_tax'), true)) { return $res; }

        $basic = 0.0;
        foreach ($components as $r) {
            if ((string) ($r['component_type'] ?? '') === 'basic'
                && (string) ($r['calc_method'] ?? '') === 'fixed_amount') {
                $basic = round((float) $r['value'], 2);
            }
        }
        $sum = 0.0;
        foreach ($components as $r) {
            if ((int) ($r[$flag] ?? 0) !== 1) { continue; }
            $method = (string) ($r['calc_method'] ?? '');
            if ($method === 'fixed_amount') {
                $sum += (float) $r['value'];
            } elseif ($method === 'pct_basic') {
                $sum += round($basic * ((float) $r['rate']) / 100.0, 2);
            } else {
                // «لا تلفيق»: طريقةٌ متغيرةٌ بالفترة لا يحملها أساسٌ شهريٌّ ثابت
                $res['unreadable'][] = array(
                    'component_id' => (int) ($r['id'] ?? 0),
                    'component_type' => (string) ($r['component_type'] ?? ''),
                    'calc_method' => $method, 'flag' => $flag);
            }
        }
        $res['base'] = round($sum, 2);
        return $res;
    }

    /** المستحقُّ غيرُ المسوّى حتى تاريخ الأثر. */
    public static function openDues($gate, $employeeId, $asOf)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('d' => 'fin_dues')),
                "SELECT ROUND(SUM(d.amount),2) AS s FROM fin_dues d
                  WHERE {TENANT_SCOPE} AND d.party_type = 'employee' AND d.party_ref = ?
                    AND d.direction = 'credit' AND COALESCE(d.is_deleted,0)=0
                    AND d.settlement_id IS NULL AND DATE(d.created_at) <= ?",
                array((int) $employeeId, (string) $asOf));
            return ($rows && $rows[0]['s'] !== null) ? round((float) $rows[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /** أيامُ الإجازة **الاعتيادية** المأخوذةُ في مدة الخدمة. */
    public static function leaveDaysTaken($gate, $employeeId, $from, $to)
    {
        $types  = "'" . implode("','", self::LEAVE_TYPES) . "'";
        $states = "'" . implode("','", self::LEAVE_STATES) . "'";
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'worker_leave_absence')),
                "SELECT ROUND(SUM(DATEDIFF(LEAST(l.date_to, ?), GREATEST(l.date_from, ?)) + 1),2) AS d
                   FROM worker_leave_absence l
                  WHERE {TENANT_SCOPE} AND l.employee_id = ?
                    AND l.event_type IN ({$types}) AND l.state IN ({$states})
                    AND l.date_from IS NOT NULL AND l.date_to IS NOT NULL
                    AND l.date_from <= ? AND l.date_to >= ?",
                array((string) $to, (string) $from, (int) $employeeId, (string) $to, (string) $from));
            return ($rows && $rows[0]['d'] !== null) ? round((float) $rows[0]['d'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /** رصيدُ السلف المفتوح — «تسويةُ السلف والعهد». */
    public static function advanceBalance($gate, $employeeId)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('a' => 'employee_advances')),
                "SELECT ROUND(SUM(a.amount - a.recovered),2) AS b FROM employee_advances a
                  WHERE {TENANT_SCOPE} AND a.person_id = ? AND COALESCE(a.is_deleted,0)=0
                    AND a.state IN ('active','approved')", array((int) $employeeId));
            return ($rows && $rows[0]['b'] !== null) ? max(0.0, round((float) $rows[0]['b'], 2)) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /** استردادٌ فعليٌّ بمقدار المقاصّة — الأقدمُ أولًا · و`balance` مولَّدٌ لا يُكتب. */
    private static function recoverAdvances($g, $employeeId, $amount)
    {
        $left = round((float) $amount, 2);
        if ($left <= 0) { return 0.0; }
        $rows = array();
        try {
            $rows = $g->scopedQuery(array('scope' => array('a' => 'employee_advances')),
                "SELECT a.id, a.amount, a.recovered FROM employee_advances a
                  WHERE {TENANT_SCOPE} AND a.person_id = ? AND COALESCE(a.is_deleted,0)=0
                    AND a.state IN ('active','approved') AND a.recovered < a.amount
                  ORDER BY a.issued_date, a.id", array((int) $employeeId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $rows'); $rows = array(); }

        $done = 0.0;
        foreach ($rows as $a) {
            if ($left <= 0) { break; }
            $bal  = round((float) $a['amount'] - (float) $a['recovered'], 2);
            if ($bal <= 0) { continue; }
            $take = min($bal, $left);
            $rec  = round((float) $a['recovered'] + $take, 2);
            $data = array('recovered' => $rec);
            if ($rec >= (float) $a['amount']) { $data['state'] = 'settled'; }
            $g->update('employee_advances', $data, array('id' => (int) $a['id']));
            $left = round($left - $take, 2);
            $done = round($done + $take, 2);
        }
        return $done;
    }

    /** «SettlementFinalized **مرةً**» — بمفتاح عطالةٍ حتميٍّ (العقد × التصفية). */
    private static function finalizedFact($conn, $companyId, $s, $dueId, $recovered, $actor)
    {
        try {
            require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'workforce.final_settlement.approved',
                'category'        => 'hr',
                'source_module'   => 'workforce',
                'company_id'      => (int) $companyId,
                'entity_type'     => 'employee_final_settlement',
                'entity_id'       => (int) $s['id'],
                'amount'          => round((float) $s['net_amount'], 2),
                'currency'        => (string) $s['currency'],
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => (int) $actor ?: 1,
                // «بمفتاح (العقد × التصفية) لا يتكرر» — حتميٌّ بلا زمنٍ فيه
                'idempotency_key' => 'emp_fs:' . (int) $s['contract_id'] . ':' . (int) $s['id'],
                'payload'         => array(
                    'employee_contract_id' => (int) $s['contract_id'],
                    'employee_id'      => (int) $s['employee_id'],
                    'effective_date'   => (string) $s['effective_date'],
                    'service_years'    => (float) $s['service_years'],
                    'dues'             => (float) $s['dues_amount'],
                    'leave'            => (float) $s['leave_amount'],
                    'eos'              => (float) $s['eos_amount'],
                    'advances_offset'  => (float) $s['advances_offset'],
                    'advances_remaining' => (float) $s['advances_remaining'],
                    'recognized'       => (float) $s['recognized_amount'],
                    'net'              => (float) $s['net_amount'],
                    'recovered'        => round((float) $recovered, 2),
                    'due_id'           => $dueId !== null ? (int) $dueId : null,
                    'snapshot_id'      => $s['snapshot_id'] !== null ? (int) $s['snapshot_id'] : null,
                    'fingerprint'      => (string) $s['snapshot_fingerprint'],
                ),
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'M-22 finalizedFact #');
            error_log('M-22 finalizedFact #' . (int) $s['id'] . ': ' . $t->getMessage());
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function head($gate, $id)
    {
        try { return $gate->selectOne('employee_final_settlements', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    public static function byContract($gate, $contractId)
    {
        try {
            return $gate->selectOne('employee_final_settlements',
                array('whereRaw' => 'contract_id = ? AND COALESCE(is_deleted,0)=0',
                      'params' => array((int) $contractId)));
        } catch (\Throwable $t) { return null; }
    }

    public static function linesOf($gate, $settlementId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'employee_final_settlement_lines')),
                "SELECT l.* FROM employee_final_settlement_lines l
                  WHERE {TENANT_SCOPE} AND l.settlement_id = ? ORDER BY l.id",
                array((int) $settlementId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function listAll($gate, $limit = 100)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('s' => 'employee_final_settlements'),
                      'enrich' => array('e' => 'employees')),
                "SELECT s.*, e.name AS employee_name
                   FROM employee_final_settlements s
                   LEFT JOIN employees e ON e.id = s.employee_id
                  WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0
                  ORDER BY s.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    /** العقودُ المنتهيةُ التي لم تُصفَّ بعد — مصدرُ قائمة الشاشة. */
    public static function settleableContracts($gate, $limit = 200)
    {
        $states = "'" . implode("','", self::CLOSABLE_STATES) . "'";
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'employee_contracts'),
                      'enrich' => array('e' => 'employees', 'f' => 'employee_final_settlements')),
                "SELECT c.id, c.employee_id, c.state, c.start_date, c.end_date, c.currency,
                        c.eos_days_per_year, c.leave_days_per_year,
                        e.name AS employee_name, f.id AS settlement_id, f.state AS settlement_state
                   FROM employee_contracts c
                   LEFT JOIN employees e ON e.id = c.employee_id
                   LEFT JOIN employee_final_settlements f
                          ON f.contract_id = c.id AND COALESCE(f.is_deleted,0)=0
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                    AND c.state IN ({$states})
                  ORDER BY c.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    private static function contractOf($gate, $contractId)
    {
        try { return $gate->selectOne('employee_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_final_settlements', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
