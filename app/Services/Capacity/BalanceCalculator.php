<?php
/**
 * app/Services/Capacity/BalanceCalculator.php — الأرصدةُ من الدفتر (CAP-13/14)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §13: «لا يُعدَّل رصيدُ حصةٍ أو التزامٍ مباشرةً أبدًا — بل يُحسب من
 * الدفتر والإعكاسات … والرصيدُ نتيجةٌ لا مصدر».
 *
 * العقيدة المنفَّذة:
 *   ① **صفرُ قراءةٍ لعمود رصيدٍ مخزَّن** — هذه الخدمة لا تقرأ consumed_qty
 *     ولا allocated_qty ولا remaining_qty أبدًا؛ تقرأ الدفترَ والإعكاسات،
 *     وسقفَ cap_qty وحدَه (سقفُ العقد تعريفٌ لا رصيد).
 *   ② أعمدةُ op_containers الرصيديةُ **مخبأٌ يُعاد بناؤه** من الدفتر عبر
 *     rebuildContainerCache — الكاتبُ الشرعيُّ الوحيدُ لها بعد القلب (CAP-14).
 *   ③ تُعيد بناءَ الأداء عند أي عكسٍ أو تصحيح — البناءُ حسابٌ من الأسطر
 *     فالعكسُ يظهر تلقائيًّا بلا تحريرٍ يدوي.
 */

namespace App\Services\Capacity;

class BalanceCalculator
{
    /**
     * المستهلَك المحسوب من الدفتر والإعكاسات لحاويةٍ وذريتها.
     * أسطرُ الدفتر تربط الورقةَ (contract_seat_id) — فاستهلاكُ الأم Σ ذريتها.
     *
     * @return float
     */
    public static function consumedOfContainer($gate, $containerId, $period = null)
    {
        $ids = self::subtreeIds($gate, (int) $containerId);
        if (empty($ids)) { return 0.0; }
        $in = implode(',', array_map('intval', $ids));
        $params = array();
        $periodCond = '';
        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $periodCond = ' AND l.period = ?';
            $params[] = (string) $period;
        }
        // الرصيدُ = Σ الأسطر غير العاكسة − Σ العاكسة (بمراجعها إلى أسطر الذرية)
        $rows = $gate->scopedQuery(array('scope' => array('l' => 'capacity_consumption_ledger')),
            "SELECT COALESCE(SUM(CASE WHEN l.effect_type = 'reversal' THEN -l.qty ELSE l.qty END), 0) AS consumed
               FROM capacity_consumption_ledger l
              WHERE {TENANT_SCOPE} AND l.contract_seat_id IN ({$in})" . $periodCond,
            $params);
        return $rows ? round((float) $rows[0]['consumed'], 2) : 0.0;
    }

    /**
     * المستهلَك المحسوب لمرجعٍ مباشر (التزامٌ · حصةٌ · بندُ مورد · مشغّل).
     * @param string $refCol contract_obligation_id | supplier_share_id |
     *                       supplier_contract_line_id | operator_assignment_id
     */
    public static function consumedOfRef($gate, $refCol, $refId, $period = null)
    {
        $allowed = array('contract_obligation_id', 'supplier_share_id',
                         'supplier_contract_line_id', 'operator_assignment_id');
        if (!in_array((string) $refCol, $allowed, true)) {
            throw new \InvalidArgumentException('مرجعُ رصيدٍ غيرُ معروف: ' . $refCol);
        }
        $params = array((int) $refId);
        $periodCond = '';
        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $periodCond = ' AND l.period = ?';
            $params[] = (string) $period;
        }
        $rows = $gate->scopedQuery(array('scope' => array('l' => 'capacity_consumption_ledger')),
            "SELECT COALESCE(SUM(CASE WHEN l.effect_type = 'reversal' THEN -l.qty ELSE l.qty END), 0) AS consumed
               FROM capacity_consumption_ledger l
              WHERE {TENANT_SCOPE} AND l.`{$refCol}` = ?" . $periodCond,
            $params);
        return $rows ? round((float) $rows[0]['consumed'], 2) : 0.0;
    }

    /**
     * رصيدُ حاوية: السقفُ (تعريفُ العقد) − المستهلَكُ المحسوب من الدفتر.
     * @return array{cap:float,consumed:float,remaining:float}
     */
    public static function balanceOf($gate, $containerId, $period = null)
    {
        // cap_qty سقفُ العقد — تعريفٌ يُقرأ؛ والمستهلكُ من الدفتر حصرًا (§13)
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.cap_qty FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ?",
            array((int) $containerId));
        $cap = $rows ? round((float) $rows[0]['cap_qty'], 2) : 0.0;
        $consumed = self::consumedOfContainer($gate, $containerId, $period);
        return array('cap' => $cap, 'consumed' => $consumed,
                     'remaining' => round($cap - $consumed, 2));
    }

    /**
     * إعادةُ بناء المخبأ (CAP-14) — الكاتبُ الشرعيُّ الوحيدُ لعمود الرصيد:
     * يعيد حسابَ consumed_qty لكل حاويةٍ في شجرة العقد من الدفتر ويكتبه مخبأً.
     * تُستدعى بعد كل عكسٍ أو تصحيحٍ (③) وفي دوريات المطابقة.
     *
     * @return array{ok:bool,rebuilt:int}
     */
    public static function rebuildContainerCache($gate, $contractId)
    {
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id FROM op_containers c WHERE {TENANT_SCOPE} AND c.contract_id = ? AND c.is_deleted = 0",
            array((int) $contractId));
        $n = 0;
        foreach ($rows as $r) {
            $consumed = self::consumedOfContainer($gate, (int) $r['id']);
            $gate->update('op_containers', array('consumed_qty' => $consumed), array('id' => (int) $r['id']));
            $n++;
        }
        return array('ok' => true, 'rebuilt' => $n);
    }

    /** ذريّةُ الحاوية (هي ونسلُها) — جولاتٌ محدودةٌ بعمق الشجرة الخماسي. */
    public static function subtreeIds($gate, $containerId)
    {
        $ids = array((int) $containerId);
        $frontier = array((int) $containerId);
        for ($depth = 0; $depth < 6 && !empty($frontier); $depth++) {
            $in = implode(',', array_map('intval', $frontier));
            $rows = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.id FROM op_containers c WHERE {TENANT_SCOPE} AND c.parent_id IN ({$in})",
                array());
            $frontier = array();
            foreach ($rows as $r) {
                $id = (int) $r['id'];
                if (!in_array($id, $ids, true)) { $ids[] = $id; $frontier[] = $id; }
            }
        }
        return $ids;
    }
}
