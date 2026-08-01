<?php
/**
 * app/Services/Procurement/ProcReorderService.php — إعادةُ الطلب الآلية (M-43 · M-51)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-09 §2.2: «التوليدُ الآلي لطلب الشراء عند بلوغ الحد بمفتاح (صنف × دورة)»
 * و«متوسطُ الاستهلاك مصدرًا لحدِّ إعادة الطلب» — كان `reordering_proc.php`
 * إدارةَ حدودٍ بلا توليدٍ، والرصيدُ حيًّا والمتوسطُ غيرَ محتسب.
 *
 * ── قواعدُ ──────────────────────────────────────────────────────────────────
 * ① **الرصيدُ من الحركات لا من حقلٍ** (Σ proc_stock_move).
 * ② **المتوسطُ من الصرف الفعلي** (آخرُ 90 يومًا ÷ أيامها) — والحدُّ المقترحُ
 *    = متوسطٌ يومي × مهلةُ التوريد + مخزونُ الأمان (M-51) **يُعرض ولا يُفرض**.
 * ③ **مفتاحُ (صنف × دورة)**: طلبُ إعادةٍ آليٌّ مفتوحٌ للصنف = دورةٌ جارية —
 *    **لا يولَّد ثانٍ حتى تُقفل** (تحويلًا أو رفضًا)؛ فالتكرارُ مستحيلٌ بنيويًّا
 *    بوسم `need_source='إعادة طلب آلي'` وفحصِ المفتوح قبل الكتابة.
 */

namespace App\Services\Procurement;

class ProcReorderService
{
    const AUTO_SOURCE = 'إعادة طلب';
    const OPEN_STATES = array('مسودة', 'مقدَّم', 'مراجعة مالية', 'معتمد مالياً', 'اعتماد المشتريات');

    /** رصيدُ الصنف الحي — Σ الحركات (in موجبٌ · out سالبٌ بحسب النوع). */
    public static function balance($conn, $companyId, $itemId, $warehouseId = 0)
    {
        $co = (int) $companyId; $it = (int) $itemId;
        $ww = (int) $warehouseId > 0 ? (' AND warehouse_id = ' . (int) $warehouseId) : '';
        $r = $conn->query("SELECT ROUND(COALESCE(SUM(CASE
                    WHEN move_type = 'استلام' THEN qty
                    ELSE -qty END),0),2) b
              FROM proc_stock_move WHERE company_id={$co} AND item_id={$it}{$ww}");
        $x = $r ? $r->fetch_assoc() : null;
        return $x ? (float) $x['b'] : 0.0;
    }

    /**
     * M-51: متوسطُ الاستهلاك اليومي (آخرُ N يومًا من الصرف الفعلي) والحدُّ
     * المقترح — «مصدرًا لحدِّ إعادة الطلب» يُعرض بجانب الحدِّ المضبوط.
     */
    public static function consumption($conn, $companyId, $itemId, $days = 90)
    {
        $co = (int) $companyId; $it = (int) $itemId; $d = max(1, (int) $days);
        $r = $conn->query("SELECT ROUND(COALESCE(SUM(qty),0),2) total
              FROM proc_stock_move
             WHERE company_id={$co} AND item_id={$it}
               AND move_type = 'صرف'
               AND moved_at >= DATE_SUB(CURDATE(), INTERVAL {$d} DAY)");
        $x = $r ? $r->fetch_assoc() : null;
        $total = $x ? (float) $x['total'] : 0.0;
        $avgDaily = round($total / $d, 3);

        $item = null;
        $r = $conn->query("SELECT lead_time_days, safety_stock, min_qty, max_qty FROM proc_item
                            WHERE company_id={$co} AND id={$it} LIMIT 1");
        if ($r) { $item = $r->fetch_assoc(); }
        $lead = $item !== null ? (int) $item['lead_time_days'] : 0;
        $safety = $item !== null ? (float) $item['safety_stock'] : 0.0;
        return array(
            'window_days' => $d, 'consumed' => $total, 'avg_daily' => $avgDaily,
            'lead_time_days' => $lead, 'safety_stock' => $safety,
            // الحدُّ المقترح = ما يُستهلك خلال مهلة التوريد + الأمان
            'suggested_trigger' => round($avgDaily * $lead + $safety, 2),
        );
    }

    /** الدورةُ الجارية للصنف — طلبُ إعادةٍ آليٌّ مفتوحٌ إن وُجد. */
    public static function openCycle($conn, $companyId, $itemId)
    {
        $co = (int) $companyId; $it = (int) $itemId;
        $states = "'" . implode("','", self::OPEN_STATES) . "'";
        $r = $conn->query("SELECT r.id, r.code, r.state FROM proc_request r
                            JOIN proc_request_line l ON l.request_id = r.id
                           WHERE r.company_id={$co} AND l.item_id={$it}
                             AND r.need_source = '" . self::AUTO_SOURCE . "'
                             AND r.state IN ({$states}) AND COALESCE(r.is_deleted,0)=0
                           LIMIT 1");
        return $r ? $r->fetch_assoc() : null;
    }

    /**
     * M-43: التوليدُ الآلي — لكل نقطةِ طلبٍ بلغ رصيدُها حدَّها ولا دورةَ
     * جاريةً لصنفها: طلبُ شراءٍ بكمية (الحدُّ الأعلى − الرصيد).
     *
     * @return array{ok:bool,dry:bool,generated:array,skipped:array}
     */
    public static function run($conn, $gate, $companyId, $actor, $dry = true)
    {
        $co = (int) $companyId;
        $out = array('ok' => true, 'dry' => (bool) $dry, 'generated' => array(), 'skipped' => array());
        $r = $conn->query("SELECT op.*, i.name item_name, i.code item_code, i.max_qty item_max
                             FROM proc_orderpoint op
                             JOIN proc_item i ON i.id = op.item_id
                            WHERE op.company_id={$co} AND COALESCE(op.is_deleted,0)=0");
        $points = array();
        while ($r && ($x = $r->fetch_assoc())) { $points[] = $x; }

        foreach ($points as $p) {
            $itemId = (int) $p['item_id'];
            $bal = self::balance($conn, $co, $itemId, (int) $p['warehouse_id']);
            $trigger = (float) $p['trigger_qty'] > 0 ? (float) $p['trigger_qty'] : (float) $p['min_qty'];
            if ($trigger <= 0 || $bal > $trigger) { continue; }

            // ③ مفتاحُ (صنف × دورة) — دورةٌ جاريةٌ تمنع التوليد
            $cycle = self::openCycle($conn, $co, $itemId);
            if ($cycle) {
                $out['skipped'][] = array('item' => (string) $p['item_name'],
                    'reason' => 'دورةٌ جاريةٌ ' . $cycle['code'] . ' (' . $cycle['state'] . ') — لا توليدَ ثانيًا');
                continue;
            }

            $target = (float) ($p['max_qty'] ?: $p['item_max']);
            $qty = round(max(1, $target - $bal), 2);
            $plan = array('item_id' => $itemId, 'item' => (string) $p['item_name'],
                'balance' => $bal, 'trigger' => $trigger, 'qty' => $qty);
            if (!$dry) {
                try {
                    $reqId = 0;
                    $gate->runInTransaction(function ($g) use ($conn, $co, $itemId, $qty, $p, $actor, &$reqId) {
                        $code = 'PRQ-AUTO-' . date('ymd') . '-' . $itemId;
                        $reqId = (int) $g->insert('proc_request', array(
                            'code' => $code, 'need_source' => ProcReorderService::AUTO_SOURCE,
                            'op_classification' => 'تشغيلي', 'requesting_dept' => 'المستودع',
                            'priority' => 'عادية', 'state' => 'مقدَّم',
                            'notes' => 'توليدٌ آليٌّ: الرصيدُ بلغ الحد (M-43)',
                            'created_by' => (int) $actor,
                        ));
                        $g->insert('proc_request_line', array(
                            'request_id' => $reqId, 'item_id' => $itemId,
                            'item_name' => (string) $p['item_name'], 'qty' => $qty,
                        ));
                    }, 'auto reorder request');
                    $plan['request_id'] = $reqId;
                } catch (\Throwable $t) {
                    $out['skipped'][] = array('item' => (string) $p['item_name'],
                        'reason' => 'تعذّر التوليد: ' . $t->getMessage());
                    continue;
                }
            }
            $out['generated'][] = $plan;
        }
        return $out;
    }
}
