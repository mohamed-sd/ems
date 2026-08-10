<?php
/**
 * app/Services/Capacity/CapacitySourceService.php — علَمُ مصدر الرصيد (CAP-15)
 * ═══════════════════════════════════════════════════════════════════════════
 * «علمٌ واحدٌ يحكم المصدر»: EMS_CAPACITY_SOURCE (columns · ledger) — على نمط
 * EMS_PERM_SOURCE (SEC-29): كتابةٌ مزدوجةٌ وظلٌّ يقارن ويسجّل كلَّ فرقٍ في
 * capacity_shadow_diffs، **ولا يُقلب إلى ledger قبل صفرِ فرقٍ أربعةَ عشرَ
 * يومًا متصلة** — والحدُّ صفرٌ لا نسبة.
 */

namespace App\Services\Capacity;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/BalanceCalculator.php';

class CapacitySourceService
{
    /** المصدرُ الحاكم الآن — من العلم الواحد. الافتراضُ columns (القائم). */
    public static function currentSource()
    {
        $v = function_exists('ems_env') ? strtolower((string) ems_env('EMS_CAPACITY_SOURCE', 'columns')) : 'columns';
        return $v === 'ledger' ? 'ledger' : 'columns';
    }

    /**
     * الرصيدُ الحرُّ لحاويةٍ بحسب المصدر الحاكم:
     * columns → من العمود المخزَّن (القائم) · ledger → محسوبًا من الدفتر.
     * @return float المتبقي
     */
    public static function freeOf($gate, array $containerRow)
    {
        if (self::currentSource() === 'ledger') {
            $b = BalanceCalculator::balanceOf($gate, (int) $containerRow['id']);
            return $b['remaining'];
        }
        return round((float) $containerRow['cap_qty'] - (float) $containerRow['consumed_qty'], 2);
    }

    /**
     * الظل: يقارن المخزَّنَ بالمحسوب من الدفتر ويسجّل الفرقَ (صفًّا يوميًّا لكل
     * حاوية — uq_shadow_daily). اليومُ بساعة القاعدة لا PHP (فارقُ الساعة).
     * @return array{diff:bool,stored:float,ledger:float}
     */
    public static function shadowCompare($conn, $gate, $containerId)
    {
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.consumed_qty FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ?",
            array((int) $containerId));
        if (!$rows) { return array('diff' => false, 'stored' => 0.0, 'ledger' => 0.0); }
        $stored = round((float) $rows[0]['consumed_qty'], 2);
        $ledger = BalanceCalculator::consumedOfContainer($gate, (int) $containerId);
        $diff = abs($stored - $ledger) > 0.001;
        if ($diff) {
            try {
                // noted_on بساعة القاعدة (CURDATE) — عُرفُ المهل والدوريات
                $gate->insert('capacity_shadow_diffs', array(
                    'container_id'    => (int) $containerId,
                    'stored_consumed' => $stored,
                    'ledger_consumed' => $ledger,
                    'diff_qty'        => round($stored - $ledger, 2),
                    'noted_on'        => self::dbToday($conn),
                    'detail'          => 'ظلُّ الاستهلاك — المخزَّنُ لا يساوي الدفتر',
                ));
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'uq_shadow_daily: فرقُ اليوم مسجَّلٌ سلفًا — يكفي صفٌّ يوميٌّ واحد');
                // uq_shadow_daily: فرقُ اليوم مسجَّلٌ سلفًا — يكفي صفٌّ يوميٌّ واحد
            }
        }
        return array('diff' => $diff, 'stored' => $stored, 'ledger' => $ledger);
    }

    /**
     * ميزانُ القلب: أيامُ الفرق في آخر أربعةَ عشرَ يومًا — الشرط: صفر.
     * @return array{days_with_diff:int,eligible:bool}
     */
    public static function flipReadiness($gate)
    {
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'capacity_shadow_diffs')),
            "SELECT COUNT(DISTINCT d.noted_on) AS days FROM capacity_shadow_diffs d
              WHERE {TENANT_SCOPE} AND d.noted_on >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)",
            array());
        $days = $rows ? (int) $rows[0]['days'] : 0;
        return array('days_with_diff' => $days, 'eligible' => $days === 0);
    }

    /** اليومُ بساعة القاعدة — لا date() (فارقُ ساعةٍ مقيس). */
    private static function dbToday($conn)
    {
        $r = $conn->query('SELECT CURDATE() d');
        return $r ? (string) $r->fetch_assoc()['d'] : date('Y-m-d');
    }
}
