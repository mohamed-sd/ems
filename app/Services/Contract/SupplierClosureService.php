<?php
/**
 * app/Services/Contract/SupplierClosureService.php — تصفيةُ إنهاء العقد (M-18)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-02 §4: «**تصفية إنهاء العقد** — عند الإنهاء: **إقفالُ الحصة** · **تسويةُ
 * العهد والسلف** · **ردُّ الضمان بعد مهلته** · و**شهادةُ إخلاءٍ موثَّقة** —
 * بحدثٍ ماليٍّ بمفتاح (**العقد × التصفية**)».
 * CON-03 §2-⑦: «… **وضمانُ الأداء والدفعةُ المقدمة**».
 *
 * ── أربعُ قواعدَ تحكم كلَّ خطوةٍ هنا ────────────────────────────────────────
 * ① **التصفيةُ عند الإنهاء لا قبله**: عقدٌ حيٌّ لا يُصفّى — 422 بحالته.
 * ② **الخطواتُ بترتيبها لا بالنيّة**: لا إخلاءَ وحاويةٌ مفتوحةٌ أو سلفةٌ برصيد.
 *    والحصةُ غيرُ المستهلَكة **تُقفل بسببٍ مكتوب** لا صامتةً.
 * ③ **ردُّ الضمان بعد مهلته** — قبلها **423 بتاريخ استحقاقه**؛ و**الردُّ يترك
 *    ذمّةً دائنةً بمرجعها** (أثرٌ لا وسمٌ في خانة) — خدمةً و`CHECK`ًا.
 * ④ **شهادةُ إخلاءٍ موثَّقة**: إقفالُ التصفية بلا مستندٍ **مستحيلٌ بنيويًّا**.
 */

namespace App\Services\Contract;

class SupplierClosureService
{
    /** الحالاتُ التي تُفتح منها التصفية — «عند الإنهاء». */
    const CLOSABLE_FROM = array('منتهٍ', 'مقفل');

    // ═════════════════════════════════════════════════════════════════════
    // ① الفتح — لقطةُ الضمان وموعدُ ردّه
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,closure_id:?int} */
    public static function open($conn, $gate, $companyId, $contractId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'closure_id' => null);
        $contractId = (int) $contractId;

        require_once __DIR__ . '/SupplierContractService.php';
        $head = SupplierContractService::head($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }

        // ── «عند الإنهاء» — لا قبله ────────────────────────────────────────
        if (!in_array((string) $head['state'], self::CLOSABLE_FROM, true)) {
            $out['code'] = 422;
            $out['reason'] = 'العقدُ في حالة «' . $head['state'] . '» — و**التصفيةُ عند الإنهاء لا قبله** '
                           . '(ENT-02 §4): أنهِ العقدَ ثم صفِّه';
            return $out;
        }

        $ex = self::byContract($gate, $contractId);
        if ($ex) {
            $out['code'] = 409; $out['closure_id'] = (int) $ex['id'];
            $out['reason'] = 'للعقد تصفيةٌ قائمةٌ #' . $ex['id'] . ' («بمفتاح العقد × التصفية»)';
            return $out;
        }

        $guarantee = ($head['performance_guarantee'] !== null)
                     ? round((float) $head['performance_guarantee'], 2) : null;
        $dueDate = null;
        if ($guarantee !== null) {
            $days = (int) $head['guarantee_retention_days'];
            $base = ($head['end_date'] !== null && (string) $head['end_date'] !== '')
                    ? (string) $head['end_date'] : date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime($base . ' +' . $days . ' days'));
        }

        require_once dirname(__DIR__) . '/Settlement/SupplierAdvanceService.php';
        $balance = \App\Services\Settlement\SupplierAdvanceService::openBalance(
            $gate, (int) $head['supplier_id']);

        try {
            $out['closure_id'] = (int) $gate->insert('supplier_contract_closures', array(
                'contract_id'        => $contractId,
                'supplier_id'        => (int) $head['supplier_id'],
                'state'              => 'open',
                'quota_open_count'   => self::openQuotaCount($gate, (int) $head['supplier_id']),
                'advances_balance'   => $balance,
                'guarantee_amount'   => $guarantee,
                'guarantee_currency' => $guarantee !== null ? (string) $head['currency'] : null,
                'guarantee_due_date' => $dueDate,
                'opened_by'          => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر فتحُ التصفية: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'create', (int) $out['closure_id'], array(),
            array('contract_id' => $contractId, 'guarantee' => $guarantee, 'due' => $dueDate));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الخطوةُ ①: إقفالُ الحصة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إقفالُ حاويات العقد المفتوحة — **وما لم يُستهلك يلزمه سببٌ مكتوب**.
     * @return array{ok:bool,code:int,reason:string,closed:int}
     */
    public static function closeQuota($conn, $gate, $companyId, $closureId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'closed' => 0);
        $cl = self::head($gate, (int) $closureId);
        if (!$cl) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $cl['state'] === 'closed') {
            $out['code'] = 409; $out['reason'] = 'التصفيةُ مقفلةٌ — والتصحيحُ بعدها بعكسٍ موثَّق'; return $out;
        }
        if ($cl['quota_closed_at'] !== null) {
            $out['code'] = 409; $out['reason'] = 'الحصةُ أُقفلت سلفًا'; return $out;
        }

        $open = self::openQuotas($gate, (int) $cl['supplier_id']);
        $unconsumed = 0;
        foreach ($open as $c) { if ((float) $c['remaining_qty'] > 0) { $unconsumed++; } }

        $reason = trim((string) $reason);
        if ($unconsumed > 0 && $reason === '') {
            $out['code'] = 422;
            $out['reason'] = $unconsumed . ' حاويةً **لم تُستهلك بالكامل** — وإقفالُها يلزمه سببٌ مكتوب '
                           . '(كما لا يُتجاوز السقفُ صامتًا لا يُقفل الباقي صامتًا)';
            return $out;
        }

        try {
            $gate->runInTransaction(function ($g) use ($open, $reason, $cl, &$out) {
                foreach ($open as $c) {
                    // حالاتُ الحاوية **عربيةٌ** في مصدرها (H-01) — والوسمُ بلغتها
                    // لا بلغةٍ ثانيةٍ تُخترع هنا (وإلا ابتلع ENUM القيمةَ صامتًا)
                    $g->update('op_containers',
                        array('state' => 'مقفلة',
                              'close_reason' => mb_substr('تصفيةُ إنهاء العقد #' . $cl['contract_id']
                                                . ($reason !== '' ? (' — ' . $reason) : ''), 0, 255)),
                        array('id' => (int) $c['id']));
                    $out['closed']++;
                }
                $g->update('supplier_contract_closures', array(
                    'quota_open_count' => 0,
                    'quota_closed_at' => date('Y-m-d H:i:s'),
                    'quota_close_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
                ), array('id' => (int) $cl['id']));
            }, 'إقفال حصة تصفية ' . $cl['id']);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر إقفالُ الحصة: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'close_quota', (int) $cl['id'],
            array('open' => count($open)), array('closed' => $out['closed'], 'reason' => $reason));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الخطوةُ ②: تسويةُ العهد والسلف
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,balance:float} */
    public static function settleAdvances($conn, $gate, $companyId, $closureId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'balance' => 0.0);
        $cl = self::head($gate, (int) $closureId);
        if (!$cl) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $cl['state'] === 'closed') {
            $out['code'] = 409; $out['reason'] = 'التصفيةُ مقفلة'; return $out;
        }

        require_once dirname(__DIR__) . '/Settlement/SupplierAdvanceService.php';
        $balance = \App\Services\Settlement\SupplierAdvanceService::openBalance(
            $gate, (int) $cl['supplier_id']);
        $out['balance'] = $balance;

        // **لا إخلاءَ ورصيدُ سلفةٍ قائم** — الرقمُ لا يضيع بالإنهاء
        if ($balance > 0.005) {
            try {
                $gate->update('supplier_contract_closures', array('advances_balance' => $balance),
                    array('id' => (int) $cl['id']));
            } catch (\Throwable $t) { /* القراءةُ تُعلن ولو تعذّر الوسم */ }
            $out['code'] = 423;
            $out['reason'] = 'رصيدُ سلفٍ مفتوحٌ ' . $balance . ' — **يُسترد أو يُحوَّل ذمّةً مدينةً '
                           . 'قبل الإخلاء** (ENT-02 §4: «تسويةُ العهد والسلف»)';
            return $out;
        }

        try {
            $gate->update('supplier_contract_closures',
                array('advances_balance' => 0, 'advances_settled_at' => date('Y-m-d H:i:s')),
                array('id' => (int) $cl['id']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الوسم: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'settle_advances', (int) $cl['id'], array(), array('balance' => 0));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ الخطوةُ ③: ردُّ الضمان بعد مهلته
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,due_id:?int} */
    public static function releaseGuarantee($conn, $gate, $companyId, $closureId, $asOf, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'due_id' => null);
        $cl = self::head($gate, (int) $closureId);
        if (!$cl) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $cl['state'] === 'closed') {
            $out['code'] = 409; $out['reason'] = 'التصفيةُ مقفلة'; return $out;
        }
        if ($cl['guarantee_amount'] === null || (float) $cl['guarantee_amount'] <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'لا ضمانَ أداءٍ مكتوبًا في هذا العقد — **ولا يُردُّ ما لم يُؤخذ**';
            return $out;
        }
        if ($cl['guarantee_released_at'] !== null) {
            $out['code'] = 409;
            $out['reason'] = 'الضمانُ رُدَّ سلفًا بذمّة #' . (int) $cl['guarantee_due_ref'];
            $out['due_id'] = (int) $cl['guarantee_due_ref'];
            return $out;
        }

        // ── «ردُّ الضمان **بعد مهلته**» ────────────────────────────────────
        $today = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) ? (string) $asOf : date('Y-m-d');
        if ($cl['guarantee_due_date'] !== null && $today < (string) $cl['guarantee_due_date']) {
            $out['code'] = 423;
            $out['reason'] = 'مهلةُ الضمان لم تنقضِ — يُردُّ في ' . $cl['guarantee_due_date']
                           . ' («ردُّ الضمان **بعد مهلته**» — ENT-02 §4)';
            return $out;
        }

        $amount = round((float) $cl['guarantee_amount'], 2);
        $cur = ($cl['guarantee_currency'] !== null && (string) $cl['guarantee_currency'] !== '')
               ? (string) $cl['guarantee_currency'] : 'USD';
        $dueId = null;
        try {
            $gate->runInTransaction(function ($g) use (&$dueId, $cl, $amount, $cur, $today, $actor) {
                // الذمّةُ الدائنةُ **باسمها** — لا «أخرى» ولا «تسوية»
                $dueId = (int) $g->insert('fin_dues', array(
                    'party_type' => 'supplier', 'party_ref' => (int) $cl['supplier_id'],
                    'due_type' => 'guarantee_release', 'direction' => 'credit',
                    'amount' => $amount, 'currency' => $cur,
                    'period_ref' => substr($today, 0, 7),
                    'source_doc_type' => 'supplier_closure', 'source_doc_id' => (int) $cl['id'],
                    'created_by' => (int) $actor ?: null,
                ));
                $g->update('supplier_contract_closures', array(
                    'guarantee_released_at' => date('Y-m-d H:i:s'),
                    'guarantee_due_ref' => $dueId,
                ), array('id' => (int) $cl['id']));
            }, 'رد ضمان تصفية ' . $cl['id']);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر ردُّ الضمان: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'release_guarantee', (int) $cl['id'], array(),
            array('amount' => $amount, 'due_id' => $dueId));
        $out['ok'] = true; $out['code'] = 200; $out['due_id'] = $dueId;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ⑤ الخطوةُ ④: شهادةُ الإخلاء والإقفال
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string} */
    public static function close($conn, $gate, $companyId, $closureId, $clearanceDoc, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $cl = self::head($gate, (int) $closureId);
        if (!$cl) { $out['code'] = 404; $out['reason'] = 'التصفيةُ غيرُ موجودةٍ في نطاقك'; return $out; }
        if ((string) $cl['state'] === 'closed') {
            $out['code'] = 409; $out['reason'] = 'التصفيةُ مقفلةٌ سلفًا'; return $out;
        }

        $doc = trim((string) $clearanceDoc);
        if ($doc === '') {
            $out['code'] = 422;
            $out['reason'] = '**شهادةُ إخلاءٍ موثَّقة** إلزامية — «الإخلاءُ بلا مستندٍ كلامٌ» (ENT-02 §4)';
            return $out;
        }

        $missing = self::missingSteps($cl);
        if ($missing) {
            $out['code'] = 423;
            $out['reason'] = 'خطواتٌ لم تكتمل: ' . implode(' · ', $missing)
                           . ' — **ولا إخلاءَ قبل إتمامها**';
            return $out;
        }

        try {
            $gate->update('supplier_contract_closures', array(
                'state' => 'closed', 'clearance_doc' => mb_substr($doc, 0, 120),
                'closed_by' => (int) $actor ?: null, 'closed_at' => date('Y-m-d H:i:s'),
            ), array('id' => (int) $cl['id']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإقفال: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'close', (int) $cl['id'],
            array('state' => $cl['state']), array('state' => 'closed', 'doc' => $doc));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** الخطواتُ الناقصةُ بأسمائها — «الرفضُ يقول ما يُفعل». */
    public static function missingSteps($cl)
    {
        $missing = array();
        if ($cl['quota_closed_at'] === null)     { $missing[] = 'إقفالُ الحصة'; }
        if ($cl['advances_settled_at'] === null) { $missing[] = 'تسويةُ العهد والسلف'; }
        if ($cl['guarantee_amount'] !== null && (float) $cl['guarantee_amount'] > 0
            && $cl['guarantee_released_at'] === null) {
            $missing[] = 'ردُّ الضمان';
        }
        return $missing;
    }

    /**
     * بوابةُ إقفال العقد — «لا إقفالَ لعقدٍ بلا تصفيةِ إخلاء».
     * @return array{ok:bool,reason:string}
     */
    public static function contractCloseGate($gate, $contractId)
    {
        $cl = self::byContract($gate, (int) $contractId);
        if (!$cl) {
            return array('ok' => false,
                'reason' => 'لا تصفيةَ إنهاءٍ لهذا العقد — و«إقفالُ الحصة وتسويةُ السلف وردُّ الضمان '
                          . 'وشهادةُ الإخلاء» شرطُ إقفاله (ENT-02 §4): افتح تصفيتَه ثم أقفِله');
        }
        if ((string) $cl['state'] !== 'closed') {
            $missing = self::missingSteps($cl);
            return array('ok' => false,
                'reason' => 'تصفيةُ العقد #' . $cl['id'] . ' لم تُقفل بعد'
                          . ($missing ? (' — الناقص: ' . implode(' · ', $missing)) : ' — تنقصها شهادةُ الإخلاء'));
        }
        return array('ok' => true, 'reason' => 'تصفيةٌ مقفلةٌ بشهادة ' . $cl['clearance_doc']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // ⑥ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function head($gate, $closureId)
    {
        try { return $gate->selectOne('supplier_contract_closures', array('where' => array('id' => (int) $closureId))); }
        catch (\Throwable $t) { return null; }
    }

    public static function byContract($gate, $contractId)
    {
        try {
            return $gate->selectOne('supplier_contract_closures',
                array('whereRaw' => 'contract_id = ? AND COALESCE(is_deleted,0)=0',
                      'params' => array((int) $contractId)));
        } catch (\Throwable $t) { return null; }
    }

    public static function listAll($gate)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'supplier_contract_closures'),
                      'enrich' => array('s' => 'suppliers')),
                "SELECT c.*, s.name AS supplier_name
                   FROM supplier_contract_closures c
                   LEFT JOIN suppliers s ON s.id = c.supplier_id
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                  ORDER BY c.id DESC");
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * حاوياتُ المورد المفتوحةُ — مصدرُ خطوة «إقفال الحصة».
     *
     * **الوصلُ بالمورد لا بعقده**: `op_containers.contract_id` هو **عقدُ العميل**
     * (L1) لا عقدُ المورد، و`supplier_id` هو الرابطُ الصحيحُ لحصته (L2/L3).
     * فالقياسُ بالمورد ويُعلَن كذلك — لا يُخترع ربطٌ لا وجودَ له.
     */
    public static function openQuotas($gate, $supplierId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.id, c.container_no, c.allocated_qty, c.consumed_qty, c.remaining_qty, c.level
                   FROM op_containers c
                  WHERE {TENANT_SCOPE} AND c.supplier_id = ? AND COALESCE(c.is_deleted,0)=0
                    AND c.state <> 'مقفلة'
                  ORDER BY c.id", array((int) $supplierId));
        } catch (\Throwable $t) { return array(); }
    }

    private static function openQuotaCount($gate, $supplierId)
    {
        return count(self::openQuotas($gate, $supplierId));
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', 'supplier_contract_closures', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
