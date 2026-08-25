<?php
/**
 * app/Services/Finance/FxSettlementService.php — العملاتُ الثلاث (P-08)
 * ═══════════════════════════════════════════════════════════════════════════
 * الملحق §3-`P-08`: «**العملاتُ الثلاث** (`contract` · `settlement` ·
 * `functional`) + **الفروقُ الأربعة** التي لا تُخلط» ·
 * §9-⑨: «قبضٌ بعملةٍ أخرى: **الذمةُ تُطفأ بالمعادل، والمتبقي رصيدٌ غيرُ مسددٍ
 * لا فرقَ صرف**، وفرقُ الصرف بسطره في العملة الوظيفية» ·
 * §9-⑫: «زيادةُ سداد: **رصيدٌ دائنٌ للعميل لا إيراد**».
 *
 * ── العملاتُ الثلاث ─────────────────────────────────────────────────────────
 *   `contract`   عملةُ العقد — بها تُحرَّر الذممُ والبنودُ والخطة.
 *   `settlement` عملةُ السداد — بها يصل النقدُ فعلًا، وقد تخالف الأولى.
 *   `functional` العملةُ الوظيفية — عملةُ أساس الشركة، **وفيها وحدَها يُقاس
 *                فرقُ الصرف**. (`fin_currencies.is_base`)
 *
 * ── والفروقُ الأربعةُ التي **لا تُخلط** ─────────────────────────────────────
 * ① **رصيدٌ غيرُ مسدد** — بيتُه `fin_receivables.outstanding` بعملة الذمّة.
 *    نقصُ المعادل **يبقى دَينًا** ولا يُغلَق بفرقِ صرف.
 * ② **فرقٌ محقَّق** — عند السداد: الفرقُ بين قيمة الذمّة يومَ الاعتراف وقيمة
 *    النقد يومَ القبض، **بالعملة الوظيفية** — `fin_fx_differences.kind='realized'`.
 * ③ **فرقٌ غيرُ محقَّق** — إعادةُ تقييم المفتوح بسعر اليوم — `'unrealized'`.
 *    **ولا يُقفل ذمّةً ولا يمسّ رصيدًا** — تقديرٌ يُعاد كل فترة.
 * ④ **زيادةُ سداد** — بيتُها `fin_payments.unallocated_amount` (P-07):
 *    **رصيدٌ دائنٌ للعميل لا إيرادٌ ولا فرقُ صرف**.
 *
 * **ولا يُجمع أيُّ اثنين منها في رقم.** والدالةُ `fourfold()` تعرضها **أربعةً
 * منفصلة** وتُعلن ذلك نصًّا.
 */

namespace App\Services\Finance;

require_once __DIR__ . '/../../../includes/catch_log.php';

class FxSettlementService
{
    const DIFF_KINDS = array('unpaid_balance', 'realized', 'unrealized', 'overpayment');

    const DIFF_AR = array(
        'unpaid_balance' => 'رصيدٌ غيرُ مسدد',
        'realized' => 'فرقُ صرفٍ محقَّق',
        'unrealized' => 'فرقُ صرفٍ غيرُ محقَّق',
        'overpayment' => 'زيادةُ سدادٍ (رصيدٌ دائنٌ للعميل)',
    );

    const DIFF_HOME = array(
        'unpaid_balance' => 'fin_receivables.outstanding',
        'realized' => 'fin_fx_differences (realized)',
        'unrealized' => 'fin_fx_differences (unrealized)',
        'overpayment' => 'fin_payments.unallocated_amount',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① العملاتُ الثلاث
    // ═════════════════════════════════════════════════════════════════════

    /**
     * العملاتُ الثلاثُ لعقدٍ — **بأسمائها لا بمواضعها**.
     * @return array{contract:string,settlement:?string,functional:string,note:string}
     */
    public static function threeCurrencies($gate, $contractId, $settlement = null)
    {
        require_once dirname(__DIR__, 3) . '/includes/fx.php';
        $functional = (string) ems_fx_base_currency();
        $contract = '';
        try {
            $c = $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId)));
            if ($c) { $contract = (string) ems_fx_code($c['price_currency_contract']); }
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل بقيمة \'\' — $contract'); $contract = ''; }
        if ($contract === '') { $contract = $functional; }
        $set = ($settlement !== null && trim((string) $settlement) !== '')
               ? (string) ems_fx_code($settlement) : null;
        $n = count(array_unique(array_filter(array($contract, $set, $functional))));
        return array(
            'contract' => $contract, 'settlement' => $set, 'functional' => $functional,
            'note' => 'عقد ' . $contract . ' · سداد ' . ($set ?: '—') . ' · وظيفية ' . $functional
                    . ($n > 1 ? ' — **ثلاث عملات لا تجمع في رقم**' : ' — عملة واحدة'),
        );
    }

    /** سعرُ العملة إلى الأساس في تاريخ — **والقيمةُ ضربًا** (نمطُ FX القائم). */
    public static function rateOf($code, $date = null)
    {
        require_once dirname(__DIR__, 3) . '/includes/fx.php';
        $r = ems_fx_rate($code, $date);
        return ($r === null) ? null : (float) $r;
    }

    /**
     * تحويلُ مبلغٍ من عملةٍ إلى أخرى عبر الأساس.
     * @return array{ok:bool,reason:string,amount:float,rate_from:?float,rate_to:?float,base:float}
     */
    public static function convert($amount, $from, $to, $date = null)
    {
        $o = array('ok' => false, 'reason' => '', 'amount' => 0.0,
                   'rate_from' => null, 'rate_to' => null, 'base' => 0.0);
        $amount = round((float) $amount, 2);
        $rf = self::rateOf($from, $date);
        $rt = self::rateOf($to, $date);
        if ($rf === null || $rf <= 0) {
            $o['reason'] = '**لا سعر صرف مسجل للعملة ' . $from . '** في ' . ($date ?: 'اليوم')
                . ' — ولا يحول بسعر مخمن';
            return $o;
        }
        if ($rt === null || $rt <= 0) {
            $o['reason'] = '**لا سعر صرف مسجل للعملة ' . $to . '**';
            return $o;
        }
        $base = round($amount * $rf, 2);
        $o['ok'] = true; $o['rate_from'] = $rf; $o['rate_to'] = $rt;
        $o['base'] = $base;
        $o['amount'] = round($base / $rt, 2);
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الفرقُ المحقَّق — عند السداد
    // ═════════════════════════════════════════════════════════════════════

    /**
     * حسابُ الفرق المحقَّق لتخصيصٍ — **ولا يشمل النقصَ في التغطية**.
     *
     * @param float  $amountTarget المعادلُ الذي أُطفئت به الذمّة (بعملة الهدف)
     * @param float  $baseSettled  قيمةُ النقد المقبوض بالعملة الوظيفية
     * @param float  $rateRecognized سعرُ الذمّة المجمَّد يومَ الاعتراف
     * @return float موجبٌ ربحٌ · سالبٌ خسارة
     */
    public static function realizedDiff($amountTarget, $baseSettled, $rateRecognized)
    {
        $baseRecognized = round((float) $amountTarget * (float) $rateRecognized, 2);
        return round((float) $baseSettled - $baseRecognized, 2);
    }

    /**
     * تسجيلُ فرقٍ — **عاطلٌ بالمصدر**: (نوع × مصدر) فريدٌ فلا يتضاعف بإعادة النداء.
     * @return array{ok:bool,code:int,reason:string,id:int}
     */
    public static function recordDiff($conn, $gate, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => 0);
        $kind = (string) (isset($a['kind']) ? $a['kind'] : '');
        if (!in_array($kind, array('realized', 'unrealized'), true)) {
            $out['code'] = 422;
            $out['reason'] = '**المخزن فرقان لا أربعة**: المحقق وغير المحقق. '
                . 'أما الرصيد غير المسدد فبيته `' . self::DIFF_HOME['unpaid_balance']
                . '` وزيادة السداد `' . self::DIFF_HOME['overpayment'] . '` — **ولا ينقلان إلى هنا**';
            return $out;
        }
        $amount = round((float) (isset($a['amount']) ? $a['amount'] : 0), 2);
        if (abs($amount) < 0.005) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = '**صفر ليس فرقا** — ولا يكتب سطر يخفي الفروق الحقيقية';
            return $out;
        }
        require_once dirname(__DIR__, 3) . '/includes/fx.php';
        $row = array(
            'kind' => $kind,
            'source_kind' => (string) (isset($a['source_kind']) ? $a['source_kind'] : 'allocation'),
            'source_ref' => (int) (isset($a['source_ref']) ? $a['source_ref'] : 0),
            'party_ref' => (isset($a['party_ref']) && (int) $a['party_ref'] > 0) ? (int) $a['party_ref'] : null,
            'from_currency' => (string) (isset($a['from_currency']) ? $a['from_currency'] : ''),
            'functional_currency' => (string) ems_fx_base_currency(),
            'amount' => $amount,
            'rate_from' => isset($a['rate_from']) ? (float) $a['rate_from'] : null,
            'rate_to' => isset($a['rate_to']) ? (float) $a['rate_to'] : null,
            'occurred_on' => (string) (isset($a['occurred_on']) ? $a['occurred_on'] : date('Y-m-d')),
            'note' => isset($a['note']) ? mb_substr((string) $a['note'], 0, 255) : null,
            'created_by' => (int) $actor ?: null,
        );
        if ($row['source_ref'] <= 0 || $row['from_currency'] === '') {
            $out['code'] = 422; $out['reason'] = 'مصدر الفرق وعملته إلزاميان'; return $out;
        }
        try { $id = (int) $gate->insert('fin_fx_differences', $row); }
        catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['ok'] = true; $out['code'] = 200;
                $out['reason'] = 'الفرق مسجل سلفا لهذا المصدر — **فعل عاطل**';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذر التسجيل: ' . $t->getMessage(); return $out;
        }
        $out['ok'] = true; $out['code'] = 200; $out['id'] = $id;
        $out['reason'] = ($amount > 0 ? 'ربح صرف ' : 'خسارة صرف ') . abs($amount) . ' '
            . $row['functional_currency'] . ' · ' . self::DIFF_AR[$kind]
            . ' — **بسطره في العملة الوظيفية**';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الفرقُ غيرُ المحقَّق — إعادةُ التقييم
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إعادةُ تقييم الذمم المفتوحة بسعر اليوم — **ولا تمسّ رصيدًا ولا تُقفل ذمّة**.
     * @return array{ok:bool,rows:int,total:float,currency:string,note:string,details:array}
     */
    public static function revalueOpen($conn, $gate, $companyId, $asOf = null, $actor = 0, $apply = true)
    {
        require_once dirname(__DIR__, 3) . '/includes/fx.php';
        $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) ? (string) $asOf : date('Y-m-d');
        $fn = (string) ems_fx_base_currency();
        $o = array('ok' => true, 'rows' => 0, 'total' => 0.0, 'currency' => $fn,
                   'note' => '', 'details' => array());
        $open = array();
        try {
            $open = $gate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                "SELECT r.id, r.doc_ref, r.customer_entity_id, r.currency, r.outstanding,
                        r.fx_rate_recognized
                   FROM fin_receivables r
                  WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding > 0
                  ORDER BY r.id");
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $open'); $open = array(); }

        foreach ($open as $r) {
            $cur = (string) $r['currency'];
            if ($cur === '' || $cur === $fn) { continue; }   // لا فرقَ على عملة الأساس
            $now = self::rateOf($cur, $day);
            $then = ($r['fx_rate_recognized'] !== null) ? (float) $r['fx_rate_recognized'] : null;
            if ($now === null || $then === null || $then <= 0) { continue; }
            $outst = round((float) $r['outstanding'], 2);
            $diff = round($outst * ($now - $then), 2);
            if (abs($diff) < 0.005) { continue; }
            $o['rows']++;
            $o['total'] = round($o['total'] + $diff, 2);
            $o['details'][] = array('receivable_id' => (int) $r['id'],
                'doc_ref' => (string) $r['doc_ref'], 'currency' => $cur,
                'outstanding' => $outst, 'rate_then' => $then, 'rate_now' => $now, 'diff' => $diff);
            if ($apply) {
                self::recordDiff($conn, $gate, $companyId, array(
                    'kind' => 'unrealized', 'source_kind' => 'revaluation',
                    'source_ref' => (int) $r['id'], 'party_ref' => (int) $r['customer_entity_id'],
                    'from_currency' => $cur, 'amount' => $diff,
                    'rate_from' => $then, 'rate_to' => $now, 'occurred_on' => $day,
                    'note' => 'إعادة تقييم ' . $r['doc_ref'] . ' — **تقدير لا يقفل ذمة**',
                ), $actor);
            }
        }
        $o['note'] = $o['rows'] . ' ذمة أعيد تقييمها بفرق **غير محقق** ' . $o['total'] . ' ' . $fn
            . ' — **ولا رصيد تغير ولا ذمة أقفلت**';
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ الأربعةُ مجتمعةً — **ومنفصلةً**
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الفروقُ الأربعةُ لعميل — **أربعةُ أرقامٍ في أربعة أبوابٍ لا مجموعٌ واحد**.
     * @return array{unpaid:array,realized:float,unrealized:float,overpayment:float,note:string}
     */
    public static function fourfold($gate, $clientId = 0)
    {
        require_once dirname(__DIR__, 3) . '/includes/fx.php';
        $fn = (string) ems_fx_base_currency();
        $o = array('unpaid' => array(), 'realized' => 0.0, 'unrealized' => 0.0,
                   'overpayment' => 0.0, 'functional' => $fn, 'note' => '');

        // ① الرصيدُ غيرُ المسدد — **بعملة كل ذمّةٍ ولا يُجمع عبر العملات**
        try {
            $w = ((int) $clientId > 0) ? ' AND r.customer_entity_id = ?' : '';
            $p = ((int) $clientId > 0) ? array((int) $clientId) : array();
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
                "SELECT r.currency, ROUND(SUM(r.outstanding),2) AS s FROM fin_receivables r
                  WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding > 0" . $w . "
                  GROUP BY r.currency", $p);
            foreach ($rows as $x) { $o['unpaid'][(string) $x['currency']] = round((float) $x['s'], 2); }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا ذمة = فراغ'); /* لا ذمّةَ = فراغ */ }

        // ②③ الفرقان المخزَّنان
        try {
            $w = ((int) $clientId > 0) ? ' AND d.party_ref = ?' : '';
            $p = ((int) $clientId > 0) ? array((int) $clientId) : array();
            $rows = $gate->scopedQuery(array('scope' => array('d' => 'fin_fx_differences')),
                "SELECT d.kind, ROUND(SUM(d.amount),2) AS s FROM fin_fx_differences d
                  WHERE {TENANT_SCOPE}" . $w . " GROUP BY d.kind", $p);
            foreach ($rows as $x) { $o[(string) $x['kind']] = round((float) $x['s'], 2); }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا فرق = صفر'); /* لا فرق = صفر */ }

        // ④ زيادةُ السداد — **من عمود P-07 لا من حسابٍ ثانٍ**
        try {
            $w = ((int) $clientId > 0) ? ' AND p.party_ref = ?' : '';
            $p = ((int) $clientId > 0) ? array((int) $clientId) : array();
            $rows = $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
                "SELECT ROUND(SUM(p.unallocated_amount),2) AS s FROM fin_payments p
                  WHERE {TENANT_SCOPE} AND p.direction = 'collection'
                    AND COALESCE(p.is_deleted,0)=0 AND p.unallocated_amount > 0" . $w, $p);
            $o['overpayment'] = $rows ? round((float) $rows[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'فشل يعامل بقيمة افتراضية — $o[\'overpayment\'] = 0.0'); $o['overpayment'] = 0.0; }

        $parts = array();
        foreach ($o['unpaid'] as $cur => $v) { $parts[] = $v . ' ' . $cur; }
        $o['note'] = '① رصيدٌ غيرُ مسدد: ' . ($parts ? implode(' · ', $parts) : 'صفر')
            . ' · ② محقق: ' . $o['realized'] . ' ' . $fn
            . ' · ③ غير محقق: ' . $o['unrealized'] . ' ' . $fn
            . ' · ④ زيادة سداد: ' . $o['overpayment']
            . ' — **أربعة لا تخلط ولا تجمع في رقم**';
        return $o;
    }

    public static function differencesOf($gate, $kind = '', $limit = 200)
    {
        try {
            $w = ($kind !== '') ? ' AND d.kind = ?' : '';
            $p = ($kind !== '') ? array($kind) : array();
            return $gate->scopedQuery(array('scope' => array('d' => 'fin_fx_differences')),
                "SELECT d.* FROM fin_fx_differences d
                  WHERE {TENANT_SCOPE}" . $w . "
                  ORDER BY d.occurred_on DESC, d.id DESC LIMIT " . (int) $limit, $p);
        } catch (\Throwable $t) { return array(); }
    }
}
