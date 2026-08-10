<?php
/**
 * app/Services/Procurement/ProcCostingService.php — طبقةُ تكاليف المشتريات
 * ═══════════════════════════════════════════════════════════════════════════
 * (إضافات الدور 16 بقرار المالك 2026-08-06 — ① التقييم · ② التكلفة الوصولية)
 *
 * المبدأ الحاكم: **التكلفةُ تُشتق حتميًّا من دفتر الحركات، لا تُراكم في متغير**.
 * إعادةُ الاحتساب آمنةٌ في أي لحظةٍ وتعيد النتيجةَ ذاتَها (idempotent) — فلا
 * انجراف، ولا خوفَ من تعديلٍ أو أرشفةٍ لاحقة.
 *
 *   · تكلفةُ استلام الوحدة = سعرُ سطر الأمر × fx_rate الأمر (معادلُ الدفاتر —
 *     نفسُ عرف base_amount) + **نصيبُها الوصولي**: Σ landed_base للأمر موزعةً
 *     على بنوده بقيمها، ثم على وحدات الكمية المستلَمة.
 *   · المتوسطُ المرجح للصنف = Σ(qty×unit_cost) / Σqty لاستلاماتٍ **مُكلَّفةٍ**
 *     فقط (unit_cost NOT NULL) — فالتاريخُ غيرُ المسعَّر خارج المتوسط بالتصميم
 *     (تقييمٌ مستقبليٌّ من لحظة التفعيل، بقرار المنفِّذ بتفويض المالك:
 *     التاريخُ بذورٌ ملوثةٌ لا يصح أن تسمم المتوسط).
 *   · الصرفُ يحمل المتوسطَ لحظتَه (يجمّده في unit_cost حركته) — دفترٌ دائم.
 *
 * العلَم: EMS_PROC_COSTING (افتراضه on) — off يعيد الصرفَ للتكلفة اليدوية
 * ويوقف تحديث المتوسط (rollback نظيف بلا مساس بالبيانات).
 */

namespace App\Services\Procurement;

require_once __DIR__ . '/../../../includes/catch_log.php';

class ProcCostingService
{
    /** هل طبقةُ التكاليف حية؟ (rollback: EMS_PROC_COSTING=off في .env) */
    public static function enabled()
    {
        return function_exists('ems_env') ? (ems_env('EMS_PROC_COSTING', 'on') !== 'off') : true;
    }

    /**
     * تكلفةُ وحدة كل صنفٍ في أمرٍ معطى — بمعادل الدفاتر شاملةً النصيبَ الوصولي.
     * تُستدعى عند (إعادة) كتابة حركات استلام الأمر وعند إضافة تكلفةٍ وصولية.
     *
     * @return array item_id => unit_cost_base (للأصناف المُكتلَجة ذات السعر)
     */
    public static function orderUnitCosts($g, $order_id)
    {
        $order_id = intval($order_id);
        $out = array();
        $po = $g->selectOne('proc_order', array('where' => array('id' => $order_id), 'includeDeleted' => true));
        if (!$po) { return $out; }
        $fx = (isset($po['fx_rate']) && (float) $po['fx_rate'] > 0) ? (float) $po['fx_rate'] : 1.0;

        $lines = $g->select('proc_order_line', array('where' => array('order_id' => $order_id)));
        $orderTotal = 0.0;
        $per = array();   // item_id => (qty, subtotal, unit_price)
        foreach ($lines as $l) {
            $iid = intval($l['item_id']);
            $orderTotal += (float) $l['subtotal'];
            if ($iid <= 0) { continue; }
            if (!isset($per[$iid])) { $per[$iid] = array('qty' => 0.0, 'subtotal' => 0.0); }
            $per[$iid]['qty']      += (float) $l['qty'];
            $per[$iid]['subtotal'] += (float) $l['subtotal'];
        }
        if (!$per) { return $out; }

        // Σ الوصولي للأمر بمعادل الدفاتر (الحي فقط — الأرشفةُ تُخرِج نصيبَها تلقائيًا)
        $landedBase = 0.0;
        try {
            $lr = $g->scopedQuery(array('scope' => array('lc' => 'proc_landed_cost')),
                "SELECT COALESCE(SUM(lc.base_amount),0) s FROM proc_landed_cost lc
                  WHERE {TENANT_SCOPE} AND lc.order_id = ? AND COALESCE(lc.is_deleted,0) = 0",
                array($order_id));
            $landedBase = $lr ? (float) $lr[0]['s'] : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'الجدولُ الوليد قد يغيب في بيئة قديمة — بلا وصولي'); /* الجدولُ الوليد قد يغيب في بيئة قديمة — بلا وصولي */ }

        foreach ($per as $iid => $p) {
            if ($p['qty'] <= 0) { continue; }
            $share = ($orderTotal > 0) ? ($p['subtotal'] / $orderTotal) : 0.0;   // التوزيعُ بالقيمة
            $unitBase = ($p['subtotal'] * $fx + $landedBase * $share) / $p['qty'];
            $out[$iid] = round($unitBase, 4);
        }
        return $out;
    }

    /**
     * إعادةُ احتساب المتوسط المرجح لصنفٍ من دفتر الحركات — الحقيقةُ الوحيدة.
     * استلاماتٌ مُكلَّفةٌ فقط؛ صفرُ استلامٍ مُكلَّفٍ ⇒ يبقى المتوسط كما هو (لا يُصفَّر).
     */
    public static function recomputeItemAvg($g, $item_id)
    {
        $item_id = intval($item_id);
        if ($item_id <= 0 || !self::enabled()) { return null; }
        try {
            $r = $g->scopedQuery(array('scope' => array('m' => 'proc_stock_move')),
                "SELECT COALESCE(SUM(m.qty * m.unit_cost),0) c, COALESCE(SUM(m.qty),0) q
                   FROM proc_stock_move m
                  WHERE {TENANT_SCOPE} AND m.item_id = ? AND m.move_type = 'استلام'
                    AND m.unit_cost IS NOT NULL",
                array($item_id));
            if (!$r || (float) $r[0]['q'] <= 0) { return null; }
            $avg = round((float) $r[0]['c'] / (float) $r[0]['q'], 4);
            $g->update('proc_item', array('avg_cost' => $avg, 'avg_cost_updated_at' => date('Y-m-d H:i:s')),
                       array('id' => $item_id));
            return $avg;
        } catch (\Throwable $t) {
            error_log('proc costing recompute #' . $item_id . ': ' . $t->getMessage());
            return null;
        }
    }

    /**
     * إعادةُ تسعير حركات استلام أمرٍ (بعد إضافة/أرشفة تكلفةٍ وصولية أو إعادة
     * كتابة الاستلام) ثم إعادةُ احتساب متوسطات أصنافه — المدخلُ الواحد للقناتين.
     */
    public static function repriceOrderReceipts($g, $order_id)
    {
        if (!self::enabled()) { return 0; }
        $order_id = intval($order_id);
        $costs = self::orderUnitCosts($g, $order_id);
        $touched = array();
        try {
            // enrich = LEFT JOIN حصرًا (عقد scopedQuery) — والشرطُ على rc في WHERE يقيّده
            $moves = $g->scopedQuery(
                array('scope' => array('m' => 'proc_stock_move'), 'enrich' => array('rc' => 'proc_receipt_custody')),
                "SELECT m.id, m.item_id FROM proc_stock_move m
                   LEFT JOIN proc_receipt_custody rc ON rc.id = m.ref_id
                  WHERE {TENANT_SCOPE} AND m.ref_type = 'proc_receipt_custody'
                    AND m.move_type = 'استلام' AND rc.order_id = ?",
                array($order_id));
        } catch (\Throwable $t) { return 0; }
        foreach ($moves as $mv) {
            $iid = intval($mv['item_id']);
            if (!isset($costs[$iid])) { continue; }   // صنفٌ بلا سعرِ سطرٍ — يبقى غيرَ مسعَّر
            $g->update('proc_stock_move', array('unit_cost' => $costs[$iid]), array('id' => intval($mv['id'])));
            $touched[$iid] = true;
        }
        foreach (array_keys($touched) as $iid) { self::recomputeItemAvg($g, $iid); }
        return count($touched);
    }

    /** متوسطُ الصنف الحالي (للصرف والعرض). */
    public static function avgCostOf($g, $item_id)
    {
        $row = $g->selectOne('proc_item', array('columns' => array('avg_cost'),
            'where' => array('id' => intval($item_id)), 'includeDeleted' => true));
        return ($row && $row['avg_cost'] !== null) ? (float) $row['avg_cost'] : null;
    }
}
