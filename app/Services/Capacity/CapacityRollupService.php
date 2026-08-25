<?php
/**
 * CapacityRollupService — الاشتقاقُ الصعوديُّ F-03/F-04 (TS-01 §٤-٨)
 * ═══════════════════════════════════════════════════════════════════════════
 * «الرقمُ الأعلى مشتقٌّ من الأدنى بنيويًّا · وسعةُ الحاويةِ السنويةِ مجموعُ
 * حاوياتِ الأنواعِ — تُستدعى من الخدمةِ بعدَ كلِّ تغييرٍ ولا تُكتب من الشاشة».
 *
 * ◆ في النموذجِ الحيِّ (`op_containers` هرمًا) يترجَم الحكمُ إلى:
 *   **مخصَّصُ الأبِ = مجموعُ سعاتِ أبنائِه الأحياء** — لكلِّ مستوًى (رئيسية ⇐
 *   مورد ⇐ معدة). وقادحُ MySQL لا يستطيع تعديلَ الجدولِ الذي أطلقه، فالاشتقاقُ
 *   خدمةٌ تُستدعى بعدَ كلِّ تغييرٍ (والكرونُ `cron_capacity_rollup` شبكةُ أمانٍ
 *   دوريةٌ تكشف أيَّ انحرافٍ وتصحّحه وتبلّغ عنه — الانحرافُ عيبٌ يُبلَّغ لا
 *   حالةٌ طبيعية).
 * ◆ وقيودُ CHECK القائمةُ (المخصَّصُ ≤ السعة) تصدُّ أيَّ اشتقاقٍ يتجاوز — فلو
 *   تجاوز مجموعُ الأبناءِ سعةَ الأبِ رُفض التحديثُ وبقي الانحرافُ ظاهرًا في
 *   التقريرِ حتى يُصحَّح مصدرُه (CK-01/CK-03 يمسكانِه).
 */

namespace App\Services\Capacity;

class CapacityRollupService
{
    /**
     * يقيس الانحرافَ ثم يصحّحه — ويعيد ما قِيس وما صُحِّح وما تعذّر.
     * @return array{measured:int,drifted:int,fixed:int,blocked:array}
     */
    public static function recompute(\mysqli $conn, $companyId)
    {
        $companyId = (int) $companyId;
        $out = array('measured' => 0, 'drifted' => 0, 'fixed' => 0, 'blocked' => array());

        /* الانحرافُ أولًا قياسًا — أبٌ مخصَّصُه ≠ مجموعِ سعاتِ أبنائِه */
        $rs = $conn->query(
            "SELECT p.id, p.container_no, p.allocated_qty, COALESCE(SUM(c.cap_qty), 0) child_sum, p.cap_qty
             FROM op_containers p
             LEFT JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
             WHERE p.company_id = $companyId AND p.is_deleted = 0
               AND EXISTS (SELECT 1 FROM op_containers x WHERE x.parent_id = p.id AND x.is_deleted = 0)
             GROUP BY p.id, p.container_no, p.allocated_qty, p.cap_qty");
        if (!$rs) { return $out; }

        while ($p = $rs->fetch_assoc()) {
            $out['measured']++;
            $want = round((float) $p['child_sum'], 2);
            $have = round((float) $p['allocated_qty'], 2);
            if (abs($want - $have) < 0.005) { continue; }
            $out['drifted']++;
            $id = (int) $p['id'];
            $st = $conn->prepare("UPDATE op_containers SET allocated_qty = ? WHERE id = ?");
            $st->bind_param('di', $want, $id);
            if ($st->execute()) {
                $out['fixed']++;
            } else {
                /* رفضته قيودُ CHECK — الأبناءُ يتجاوزون السعةَ: يُبلَّغ ولا يُدفَن */
                $out['blocked'][] = $p['container_no'] . ' (مجموع الأبناء ' . $want . ' وسعة الأب ' . $p['cap_qty'] . ')';
            }
            $st->close();
        }
        return $out;
    }
}
