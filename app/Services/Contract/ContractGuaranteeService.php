<?php
/**
 * app/Services/Contract/ContractGuaranteeService.php — الضمانات (P-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §3.1 «المحتجَز والضمانات»: «**فصلٌ إلزامي**: ① محتجزٌ نقديٌّ يُخصم
 * من المستخلص — **أصلٌ لدى العميل** · ② خطابُ ضمانٍ بنكي — **التزامٌ محتملٌ
 * خارج الميزانية**، لا نقدَ محجوزًا ولا يُخصم من مستخلص · ③ تأمينٌ أو كفالة.
 * **والخلطُ بينها خطأٌ محاسبيٌّ**» · §9-⑯: «خطابُ ضمانٍ بنكي: **لا يُخصم من
 * مستخلصٍ ولا يظهر أصلًا**».
 *
 * ── لماذا سجلٌّ والعمودُ قائم ────────────────────────────────────────────────
 * لأن العمودَ **نثر**: `contracts.guarantees` MEDIUMTEXT وفيه حيًّا «رهن سيارة»
 * و«تأمين المشروع» ونصُّ لوريم — في **تسعة عقودٍ من عشرة**. فلا يُعرف من نصِّه
 * أهو أصلٌ أم التزامٌ محتمل، ولا متى ينتهي، ولا أيُخصم من مستخلصٍ أم لا.
 *
 * ── ثلاثُ قواعد ─────────────────────────────────────────────────────────────
 * ① **الطبيعةُ محكومةٌ بالنوع** — المحتجَزُ النقديُّ **أصلٌ** حتمًا وما عداه
 *    **خارجَ الميزانية** حتمًا، و«أخرى» خارجَها **لأن الافتراضَ الآمن ألّا
 *    يصير شيءٌ أصلًا إلا بإعلان**.
 * ② **ولا يُخصم من مستخلصٍ إلا المحتجَزُ النقدي** — قيدٌ في البنية لا سياسة.
 * ③ **والرصيدُ يُقرأ من مصدره**: مبالغُ المحتجَز مسجَّلةٌ في `claims` وردُّه في
 *    `claim_lines` — **ولا جدولَ ثالث** (درسُ M-01 حرفيًّا). وهذا السجلُّ
 *    **شروطٌ وتواريخُ ردٍّ وأدوات** لا مبالغُ رصيد.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

class ContractGuaranteeService
{
    const KINDS = array('cash_retention', 'bank_guarantee', 'insurance', 'surety', 'pledge', 'other');

    const KIND_AR = array(
        'cash_retention' => 'محتجزٌ نقدي', 'bank_guarantee' => 'خطابُ ضمانٍ بنكي',
        'insurance' => 'تأمين', 'surety' => 'كفالة', 'pledge' => 'رهن', 'other' => 'أخرى',
    );

    /** **الطبيعةُ محكومةٌ بالنوع** — ولا تُختار. */
    const NATURE_OF = array(
        'cash_retention' => 'asset',
        'bank_guarantee' => 'off_balance',
        'insurance' => 'off_balance',
        'surety' => 'off_balance',
        'pledge' => 'off_balance',
        'other' => 'off_balance',
    );

    const NATURE_AR = array(
        'asset' => 'أصلٌ لدى العميل', 'off_balance' => 'التزامٌ محتملٌ خارج الميزانية',
    );

    const STATES = array('draft', 'active', 'expired', 'released', 'called');

    const STATE_AR = array(
        'draft' => 'مسودة', 'active' => 'سارٍ', 'expired' => 'منتهي السريان',
        'released' => 'مُفرَجٌ عنه', 'called' => 'مُصادَر',
    );

    // ═════════════════════════════════════════════════════════════════════

    /**
     * تسجيلُ أداةِ ضمان — **والنوعُ يحسم الطبيعةَ وقابليةَ الخصم**.
     * @return array{ok:bool,code:int,reason:string,id:int}
     */
    public static function add($conn, $gate, $companyId, $contractId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => 0);
        try { $c = $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غيرُ موجودٍ في نطاقك'; return $out; }

        $kind = (string) (isset($a['kind']) ? $a['kind'] : '');
        if (!in_array($kind, self::KINDS, true)) {
            $out['code'] = 422; $out['reason'] = '**نوعُ الأداة إلزاميٌّ** وواحدٌ من الستة'; return $out;
        }
        // ① الطبيعةُ **لا تُختار** — ومحاولةُ اختيارها خلافَ النوع تُرفض بنصِّها
        $nature = self::NATURE_OF[$kind];
        $asked = (string) (isset($a['nature']) ? $a['nature'] : '');
        if ($asked !== '' && $asked !== $nature) {
            $out['code'] = 422;
            $out['reason'] = '**«' . self::KIND_AR[$kind] . '» طبيعتُه «' . self::NATURE_AR[$nature]
                . '» حتمًا** — والخلطُ بين الأصل والالتزام المحتمل **خطأٌ محاسبيٌّ لا خيارُ مستخدم**';
            return $out;
        }
        // ② ولا يُخصم من مستخلصٍ إلا المحتجَزُ النقدي
        $deduct = !empty($a['deductible_from_claim']) ? 1 : 0;
        if ($deduct === 1 && $kind !== 'cash_retention') {
            $out['code'] = 422;
            $out['reason'] = '**«' . self::KIND_AR[$kind] . '» لا يُخصم من مستخلصٍ أبدًا** — '
                . 'وليس نقدًا محجوزًا حتى يُخصم';
            return $out;
        }
        if ($kind === 'cash_retention') { $deduct = 1; }

        $amount = round((float) (isset($a['amount']) ? $a['amount'] : 0), 2);
        if ($amount < 0) { $out['code'] = 422; $out['reason'] = 'قيمةُ الأداة غيرُ سالبة'; return $out; }
        $pct = (isset($a['percent_value']) && trim((string) $a['percent_value']) !== '')
               ? round((float) $a['percent_value'], 3) : null;
        if ($pct !== null && ($pct < 0 || $pct > 100)) {
            $out['code'] = 422; $out['reason'] = 'النسبةُ في [0,100]'; return $out;
        }
        $expiry = self::dateOrNull(isset($a['expiry_date']) ? $a['expiry_date'] : null);
        $due = self::dateOrNull(isset($a['due_release_date']) ? $a['due_release_date'] : null);
        $cond = trim((string) (isset($a['release_condition']) ? $a['release_condition'] : ''));

        // ③ ولكلِّ أداةٍ أفقُها
        if ($kind === 'cash_retention') {
            if ($due === null && $cond === '') {
                $out['code'] = 422;
                $out['reason'] = '**المحتجَزُ أصلٌ بذمةٍ مؤجَّلةٍ — فلا بدَّ من تاريخِ ردٍّ أو شرطِه**؛ '
                    . 'وأصلٌ بلا موعدِ عودةٍ رقمٌ معلَّق';
                return $out;
            }
        } else {
            if ($expiry === null) {
                $out['code'] = 422;
                $out['reason'] = '**تاريخُ انتهاء سريان «' . self::KIND_AR[$kind] . '» إلزامي** — '
                    . 'والتزامٌ محتملٌ بلا أفقٍ يبقى معلَّقًا إلى الأبد';
                return $out;
            }
        }

        $row = array(
            'contract_id' => (int) $contractId, 'kind' => $kind, 'nature' => $nature,
            'deductible_from_claim' => $deduct, 'amount' => $amount, 'percent_value' => $pct,
            'currency' => (string) (isset($a['currency']) && trim((string) $a['currency']) !== ''
                          ? $a['currency'] : (string) ($c['price_currency_contract'] ?: 'USD')),
            'issuer' => self::strOrNull(isset($a['issuer']) ? $a['issuer'] : null, 190),
            'instrument_ref' => self::strOrNull(isset($a['instrument_ref']) ? $a['instrument_ref'] : null, 120),
            'issue_date' => self::dateOrNull(isset($a['issue_date']) ? $a['issue_date'] : null),
            'expiry_date' => $expiry, 'due_release_date' => $due,
            'release_condition' => ($cond === '') ? null : mb_substr($cond, 0, 200),
            'state' => 'active',
            'note' => self::strOrNull(isset($a['note']) ? $a['note'] : null, 255),
            'created_by' => (int) $actor ?: null,
        );
        try { $id = (int) $gate->insert('contract_guarantees', $row); }
        catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التسجيل: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'add_guarantee', $id, array(), $row);
        $out['ok'] = true; $out['code'] = 200; $out['id'] = $id;
        $out['reason'] = 'سُجّل «' . self::KIND_AR[$kind] . '» بقيمة ' . $amount . ' '
            . $row['currency'] . ' · **' . self::NATURE_AR[$nature] . '**'
            . ($deduct ? ' · يُخصم من المستخلص' : ' · **لا يُخصم من مستخلص**');
        return $out;
    }

    /** تغييرُ الحال بسببه — **ولا حذف**. */
    public static function setState($conn, $gate, $companyId, $id, $state, $reason, $atDate, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        if (!in_array((string) $state, self::STATES, true)) {
            $out['code'] = 422; $out['reason'] = 'حالٌ غيرُ معروف'; return $out;
        }
        $g = self::rowOf($gate, (int) $id);
        if (!$g) { $out['code'] = 404; $out['reason'] = 'الأداةُ غيرُ موجودة'; return $out; }
        $reason = trim((string) $reason);
        if (in_array((string) $state, array('released', 'called', 'expired'), true) && $reason === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببُ الخروج إلزامي** — ولا أداةَ تخرج صامتة'; return $out;
        }
        if ((string) $g['state'] === (string) $state) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'الحالُ كما هو — فعلٌ عاطل'; return $out;
        }
        try {
            $gate->update('contract_guarantees', array(
                'state' => (string) $state,
                'state_reason' => ($reason === '') ? null : mb_substr($reason, 0, 255),
                'state_at' => self::dateOrNull($atDate) ?: date('Y-m-d'),
                'needs_review' => 0,
            ), array('id' => (int) $id));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التغيير: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'guarantee_state', (int) $id,
            array('state' => $g['state']), array('state' => $state, 'reason' => $reason));
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'صار الحالُ «' . self::STATE_AR[(string) $state] . '»';
        return $out;
    }

    /**
     * **رصيدُ المحتجَز يُقرأ من مصدره** — `claims` و`claim_lines` — **لا من هذا
     * السجل**. وهو نظيرُ `advance_balance` حرفيًّا (M-01).
     * @return array{withheld:float,released:float,balance:float,note:string}
     */
    public static function retentionBalance($gate, $contractId)
    {
        $o = array('withheld' => 0.0, 'released' => 0.0, 'balance' => 0.0, 'note' => '');
        try {
            $r = $gate->scopedQuery(array('scope' => array('c' => 'claims')),
                "SELECT ROUND(COALESCE(SUM(c.retention_amount),0),2) AS s FROM claims c
                  WHERE {TENANT_SCOPE} AND c.contract_id = ? AND COALESCE(c.is_deleted,0)=0",
                array((int) $contractId));
            $o['withheld'] = $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا مستخلصَ = صفر'); /* لا مستخلصَ = صفر */ }
        try {
            $r = $gate->scopedQuery(
                array('scope' => array('l' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT ROUND(COALESCE(SUM(ABS(l.amount)),0),2) AS s FROM claim_lines l
                   LEFT JOIN claims c ON c.id = l.claim_id
                  WHERE {TENANT_SCOPE} AND c.contract_id = ? AND l.source_kind = 'retention_release'",
                array((int) $contractId));
            $o['released'] = $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا ردَّ = صفر'); /* لا ردَّ = صفر */ }
        $o['balance'] = round($o['withheld'] - $o['released'], 2);
        $o['note'] = 'محتجزٌ ' . $o['withheld'] . ' · مردودٌ ' . $o['released']
            . ' · **الرصيدُ ' . $o['balance'] . '** — مقروءًا من المستخلصات لا من سجل الضمانات';
        return $o;
    }

    /**
     * **الأصلُ والالتزامُ المحتمل — رقمان لا يُجمعان** (§9-⑯).
     * @return array{asset:float,off_balance:float,currency:string,rows:int,note:string}
     */
    public static function exposure($gate, $contractId)
    {
        $o = array('asset' => 0.0, 'off_balance' => 0.0, 'currency' => '', 'rows' => 0,
                   'expiring' => 0, 'note' => '');
        $bal = self::retentionBalance($gate, (int) $contractId);
        $today = date('Y-m-d');
        foreach (self::rowsOf($gate, (int) $contractId) as $r) {
            if (!in_array((string) $r['state'], array('active', 'draft'), true)) { continue; }
            $o['rows']++;
            $o['currency'] = (string) $r['currency'];
            if ((string) $r['nature'] === 'off_balance') {
                $o['off_balance'] = round($o['off_balance'] + (float) $r['amount'], 2);
                if ($r['expiry_date'] !== null && (string) $r['expiry_date'] < $today) { $o['expiring']++; }
            }
        }
        // **الأصلُ رصيدُ المحتجَز الفعليُّ من المستخلصات** لا قيمةَ سطرِ السجل
        $o['asset'] = $bal['balance'];
        $o['note'] = 'أصلٌ (محتجزٌ نقديٌّ فعليّ) ' . $o['asset']
            . ' · **والتزامٌ محتملٌ خارج الميزانية** ' . $o['off_balance']
            . ' — **رقمان لا يُجمعان**'
            . ($o['expiring'] > 0 ? (' · **' . $o['expiring'] . ' أداةً انقضى سريانُها**') : '');
        return $o;
    }

    /** الأدواتُ التي **يجوز** خصمُها من مستخلص — والقائمةُ لا تحوي إلا المحتجَز. */
    public static function deductibleInstruments($gate, $contractId)
    {
        $o = array();
        foreach (self::rowsOf($gate, (int) $contractId) as $r) {
            if ((int) $r['deductible_from_claim'] === 1 && (string) $r['state'] === 'active') { $o[] = $r; }
        }
        return $o;
    }

    public static function rowsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('g' => 'contract_guarantees')),
                "SELECT g.* FROM contract_guarantees g
                  WHERE {TENANT_SCOPE} AND g.contract_id = ? AND COALESCE(g.is_deleted,0)=0
                  ORDER BY g.nature, g.id", array((int) $contractId));
        } catch (\Throwable $t) { error_log('P-06 rowsOf: ' . $t->getMessage()); return array(); }
    }

    /** ما يحتاج إقرارَ المالك — «الآلةُ تقترح والمالكُ يُقرّ». */
    public static function pendingReview($gate)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('g' => 'contract_guarantees')),
                "SELECT g.* FROM contract_guarantees g
                  WHERE {TENANT_SCOPE} AND g.needs_review = 1 AND COALESCE(g.is_deleted,0)=0
                  ORDER BY g.contract_id, g.id LIMIT 200");
        } catch (\Throwable $t) { return array(); }
    }

    public static function rowOf($gate, $id)
    {
        try { return $gate->selectOne('contract_guarantees', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function strOrNull($v, $len)
    {
        $v = trim((string) $v);
        return ($v === '') ? null : mb_substr($v, 0, $len);
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_guarantees', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
