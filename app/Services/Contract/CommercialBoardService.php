<?php
/**
 * app/Services/Contract/CommercialBoardService.php — اللوحةُ التجارية (P-12)
 * ═══════════════════════════════════════════════════════════════════════════
 * الملحق §3-`P-12`: «**لوحةُ العقد التجارية**: المخططُ · المنفَّذُ · المفوترُ ·
 * المحصَّل **في سطرٍ واحدٍ لكل عقدٍ نافذ**، **وكلُّ فجوةٍ بمالكها**» ·
 * §4 **شرطُ إغلاق الموجة**: «`P-12` تعرض الأرقام الأربعة لعقدٍ رائدٍ واحدٍ على
 * الأقل، وكلُّ فجوةٍ لها مالكٌ مسمًّى — **وهذا دليلُ أن خطَّ الأساس صار حيًّا
 * لا وثيقة**».
 *
 * ── الأرقامُ الأربعةُ من بيوتها — ولا رقمَ يُخترع هنا ───────────────────────
 *   **المخطَّط**  `contract_monthly_plan` × سعرِ البند (P-03 · P-02)
 *   **المنفَّذ**  `unit_entries` بمفتاح `contract_line_id` (P-09) × السعر
 *   **المفوتَر**  `claims.net_amount` للمعتمَدة فما فوق
 *   **المحصَّل**  `fin_receivables.collected` عبر `claims.receivable_id`
 *
 * ── وثلاثُ فجواتٍ لكلٍّ **مالكٌ مسمًّى** ────────────────────────────────────
 *   منفَّذ − مخطَّط  ⇒ **فجوةُ التنفيذ**  — مالكُها **التشغيل**
 *   مفوتَر − منفَّذ  ⇒ **فجوةُ الفوترة**  — مالكُها **المبيعات**
 *   محصَّل − مفوتَر  ⇒ **فجوةُ التحصيل** — مالكُها **المالية**
 *
 * ── ومصداقيةُ اللوحة تُعلَن مع أرقامها ─────────────────────────────────────
 * لوحةٌ تعرض «منفَّذًا» ونصفُ وحداتها **غيرُ موصولةٍ بمفتاح** (P-09) تعرض رقمًا
 * **ناقصًا يبدو تامًّا**. فتُعلن `coverage` مع كل سطر، **والرقمُ المبنيُّ على
 * تغطيةٍ ناقصةٍ يُوسَم**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/PlanActualLinkService.php';
require_once __DIR__ . '/ContractBaselineService.php';

class CommercialBoardService
{
    /** **كلُّ فجوةٍ بمالكها** — بالاسم والدور لا بالإشارة. */
    const GAP_OWNERS = array(
        'execution' => array('label' => 'فجوةُ التنفيذ', 'owner' => 'التشغيل',
                             'role' => '1', 'question' => 'لماذا نفَّذنا خلافَ ما خُطِّط؟'),
        'billing'   => array('label' => 'فجوةُ الفوترة', 'owner' => 'المبيعات',
                             'role' => '12', 'question' => 'لماذا لم يُفوتر ما نُفِّذ؟'),
        'collection' => array('label' => 'فجوةُ التحصيل', 'owner' => 'المالية',
                              'role' => '17', 'question' => 'لماذا لم يُحصَّل ما فُوتر؟'),
    );

    /**
     * سطرُ عقدٍ واحد — **الأرقامُ الأربعةُ والفجواتُ الثلاثُ بمالكيها**.
     * @return array{ok:bool,contract_id:int,currency:string,planned:float,executed:float,
     *               billed:float,collected:float,gaps:array,coverage:array,
     *               baseline:?string,credible:bool,note:string}
     */
    public static function row($gate, $contractId, $fromMonth = '', $toMonth = '')
    {
        $contractId = (int) $contractId;
        $o = array('ok' => false, 'contract_id' => $contractId, 'currency' => '',
                   'planned' => 0.0, 'executed' => 0.0, 'billed' => 0.0, 'collected' => 0.0,
                   'gaps' => array(), 'coverage' => array(), 'baseline' => null,
                   'credible' => true, 'note' => '');

        // ── المخطَّطُ والمنفَّذُ **بالقيمة** — والكميةُ تُضرب بسعر بندها ───────
        $pv = PlanActualLinkService::planVsActual($gate, $contractId, $fromMonth, $toMonth);
        $priceOf = array(); $curOf = array();
        foreach (ContractLineService::linesOf($gate, $contractId, false) as $l) {
            $priceOf[(int) $l['id']] = (float) $l['unit_price'];
            $curOf[(int) $l['id']] = (string) $l['currency'];
        }
        $curs = array();
        foreach ($pv['rows'] as $r) {
            $lid = (int) $r['line_id'];
            $p = isset($priceOf[$lid]) ? $priceOf[$lid] : 0.0;
            $o['planned'] = round($o['planned'] + (float) $r['planned'] * $p, 2);
            $o['executed'] = round($o['executed'] + (float) $r['actual'] * $p, 2);
            if (isset($curOf[$lid]) && $curOf[$lid] !== '') { $curs[$curOf[$lid]] = true; }
        }
        // **ولا تُجمع عملتان في رقم** — قاعدةٌ سارية في كل اللوحات
        if (count($curs) > 1) {
            $o['note'] = '**العقدُ بعملتين أو أكثر (' . implode(' · ', array_keys($curs))
                       . ') — ولا تُجمع عملتان في رقم**';
            $o['currency'] = implode('/', array_keys($curs));
            $o['credible'] = false;
            return $o;
        }
        $o['currency'] = $curs ? (string) array_keys($curs)[0] : '';

        // ── المفوتَرُ والمحصَّلُ من بيتيهما ──────────────────────────────────
        try {
            $r = $gate->scopedQuery(
                array('scope' => array('c' => 'claims'), 'enrich' => array('r' => 'fin_receivables')),
                "SELECT ROUND(COALESCE(SUM(c.net_amount),0),2) AS billed,
                        ROUND(COALESCE(SUM(r.collected),0),2) AS collected
                   FROM claims c
                   LEFT JOIN fin_receivables r ON r.id = c.receivable_id
                  WHERE {TENANT_SCOPE} AND c.contract_id = ? AND COALESCE(c.is_deleted,0)=0
                    AND c.state IN ('approved','invoiced','collected')", array($contractId));
            if ($r) {
                $o['billed'] = round((float) $r[0]['billed'], 2);
                $o['collected'] = round((float) $r[0]['collected'], 2);
            }
        } catch (\Throwable $t) { /* لا مستخلصَ = صفر */ }

        // ── الفجواتُ الثلاثُ **بمالكيها** ───────────────────────────────────
        $o['gaps'] = array(
            'execution' => array_merge(self::GAP_OWNERS['execution'],
                array('value' => round($o['executed'] - $o['planned'], 2))),
            'billing' => array_merge(self::GAP_OWNERS['billing'],
                array('value' => round($o['billed'] - $o['executed'], 2))),
            'collection' => array_merge(self::GAP_OWNERS['collection'],
                array('value' => round($o['collected'] - $o['billed'], 2))),
        );

        // ── ومصداقيةُ اللوحة تُعلَن مع أرقامها ──────────────────────────────
        $cov = PlanActualLinkService::coverage($gate, $contractId);
        $o['coverage'] = $cov;
        $unlinked = (int) $cov['units_total'] - (int) $cov['units_linked'];
        if ($unlinked > 0) {
            $o['credible'] = false;
        }
        $b = ContractBaselineService::current($gate, $contractId);
        $o['baseline'] = $b ? (string) $b['state'] : null;

        $o['ok'] = true;
        $o['note'] = 'مخطَّطٌ ' . $o['planned'] . ' · منفَّذٌ ' . $o['executed']
            . ' · مفوتَرٌ ' . $o['billed'] . ' · محصَّلٌ ' . $o['collected']
            . ($o['currency'] !== '' ? (' ' . $o['currency']) : '')
            . ($unlinked > 0
               ? (' · ⚠ **' . $unlinked . ' وحدةً غيرَ موصولةٍ — والمنفَّذُ ناقصٌ يبدو تامًّا**')
               : ' · **التغطيةُ كاملة**')
            . ' · خطُّ الأساس: '
            . ($o['baseline'] !== null
               ? ContractBaselineService::STATE_AR[$o['baseline']] : '**غيرُ مفتوح**');
        return $o;
    }

    /** اللوحةُ كاملةً — **سطرٌ واحدٌ لكل عقدٍ نافذ**. */
    public static function board($gate, $onlyActive = true, $limit = 100)
    {
        $rows = array();
        $contracts = array();
        try {
            $w = $onlyActive ? " AND c.contract_status IN ('نافذ','قيد التنفيذ','معدَّل','مجدَّد')" : '';
            $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
                "SELECT c.id, c.second_party, c.contract_status, c.project_id
                   FROM contracts c
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0" . $w . "
                  ORDER BY c.id DESC LIMIT " . (int) $limit);
        } catch (\Throwable $t) { $contracts = array(); }
        foreach ($contracts as $c) {
            $r = self::row($gate, (int) $c['id']);
            $r['second_party'] = (string) $c['second_party'];
            $r['contract_status'] = (string) $c['contract_status'];
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * **شرطُ إغلاق الموجة** (§4): هل يوجد **عقدٌ رائدٌ واحدٌ على الأقل** تُعرض
     * أرقامُه الأربعةُ **وكلُّ فجوةٍ فيه لها مالكٌ مسمًّى**؟
     *
     * @return array{ok:bool,pilots:array,reason:string}
     */
    public static function closureCheck($gate)
    {
        $o = array('ok' => false, 'pilots' => array(), 'reason' => '');
        foreach (self::board($gate, true, 100) as $r) {
            if (!$r['ok']) { continue; }
            // عقدٌ «تُعرض أرقامُه» = له مخطَّطٌ ومنفَّذٌ حقيقيّان — لا أصفارٌ أربعة
            if ($r['planned'] <= 0.004 && $r['executed'] <= 0.004) { continue; }
            $named = true;
            foreach ($r['gaps'] as $g) {
                if (!isset($g['owner']) || trim((string) $g['owner']) === '') { $named = false; }
            }
            if (!$named) { continue; }
            $o['pilots'][] = array(
                'contract_id' => $r['contract_id'], 'planned' => $r['planned'],
                'executed' => $r['executed'], 'billed' => $r['billed'],
                'collected' => $r['collected'], 'currency' => $r['currency'],
                'credible' => $r['credible'], 'baseline' => $r['baseline'],
            );
        }
        $o['ok'] = count($o['pilots']) > 0;
        $o['reason'] = $o['ok']
            ? '**' . count($o['pilots']) . ' عقدًا** تُعرض أرقامُه الأربعةُ **وكلُّ فجوةٍ بمالكها** '
              . '— وهو **دليلُ أن خطَّ الأساس صار حيًّا لا وثيقة** (§4)'
            : '**لا عقدَ تُعرض أرقامُه الأربعة** — والموجةُ لا تُغلق بلوحةٍ فارغة';
        return $o;
    }

    /** مجاميعُ اللوحة **بعملةٍ واحدة** — ولا تُجمع عملتان. */
    public static function totals(array $rows)
    {
        $o = array();
        foreach ($rows as $r) {
            if (!$r['ok'] || (string) $r['currency'] === '') { continue; }
            $c = (string) $r['currency'];
            if (!isset($o[$c])) {
                $o[$c] = array('planned' => 0.0, 'executed' => 0.0, 'billed' => 0.0,
                               'collected' => 0.0, 'contracts' => 0);
            }
            foreach (array('planned', 'executed', 'billed', 'collected') as $k) {
                $o[$c][$k] = round($o[$c][$k] + (float) $r[$k], 2);
            }
            $o[$c]['contracts']++;
        }
        return $o;
    }
}
