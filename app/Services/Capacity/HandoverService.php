<?php
/**
 * HandoverService — تسليمُ الحصةِ بين الموردين (SUP-CNT-01 · HO-01..05)
 * ═══════════════════════════════════════════════════════════════════════════
 * «كلُّ تغيّرٍ في تشكيلِ الحاويةِ له صفُّ حدثٍ بتاريخِه، وبطرفين، ولا حدثَ
 * يعيد احتسابَ شهرٍ مغلق». الصفُّ الواحدُ في `container_swaps` يحمل الطرفين
 * معًا (HO-02/03 بنيويًّا)، والقادحُ `trg_swap_ho_ins` يفرض المستندَ والتاريخَ
 * والشهرَ المفتوحَ في القاعدةِ — وهذه الخدمةُ فوقَه للتحقّقِ المبكّرِ بلغةٍ
 * يفهمها المبتدئ، ولنقلِ الحصةِ نفسِها بين الحاويتين ذرّيًّا.
 *
 * ◆ النقل: `allocated_qty` تنقص من المسلِّمةِ وتزيد في المستلِمة — وقيودُ
 *   CHECK القائمةُ (allocated ≤ cap) وحارسُ سعةِ الأبِ يصدّان أيَّ تجاوزٍ
 *   بنيويًّا، فالخدمةُ لا تكرّر حكمَ القاعدةِ بل تترجم رفضَها للمستخدم.
 */

namespace App\Services\Capacity;

class HandoverService
{
    /**
     * @param array $in company_id · from_container_id · to_container_id ·
     *                  moved_qty · effective_from (Y-m-d) · doc_ref · reason
     * @return array{ok:bool,code:int,swap_id:?int,reasons:array}
     */
    public static function record($gate, \mysqli $conn, array $in, $actor)
    {
        $co   = (int) ($in['company_id'] ?? 0);
        $from = (int) ($in['from_container_id'] ?? 0);
        $to   = (int) ($in['to_container_id'] ?? 0);
        $qty  = round((float) ($in['moved_qty'] ?? 0), 2);
        $date = trim((string) ($in['effective_from'] ?? ''));
        $doc  = trim((string) ($in['doc_ref'] ?? ''));
        $why  = trim((string) ($in['reason'] ?? ''));

        $miss = array();
        if ($from <= 0)              { $miss[] = 'الحاوية المسلمة'; }
        if ($to <= 0)                { $miss[] = 'الحاوية المستلمة'; }
        if ($from > 0 && $from === $to) { $miss[] = 'الطرفان متطابقان — التسليم بين حاويتين مختلفتين'; }
        if ($qty <= 0)               { $miss[] = 'الكمية المنقولة (أكبر من صفر)'; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $miss[] = 'تاريخ السريان'; }
        if ($doc === '')             { $miss[] = 'مستند التسليم (محضر أو خطاب)'; }
        if ($why === '')             { $miss[] = 'سبب التسليم'; }
        if ($miss) { return array('ok' => false, 'code' => 422, 'swap_id' => null, 'reasons' => $miss); }

        /* الطرفان من كيانِ الجلسةِ وحيّان */
        $st = $conn->prepare("SELECT id, container_no, allocated_qty, cap_qty, supplier_id
                              FROM op_containers WHERE id IN (?, ?) AND company_id = ? AND is_deleted = 0");
        $st->bind_param('iii', $from, $to, $co);
        $st->execute();
        $rs = $st->get_result();
        $rows = array();
        while ($x = $rs->fetch_assoc()) { $rows[(int) $x['id']] = $x; }
        $st->close();
        if (!isset($rows[$from], $rows[$to])) {
            return array('ok' => false, 'code' => 404, 'swap_id' => null,
                         'reasons' => array('إحدى الحاويتين غير موجودة في كيانك'));
        }
        if ((float) $rows[$from]['allocated_qty'] < $qty) {
            return array('ok' => false, 'code' => 409, 'swap_id' => null,
                         'reasons' => array('المسلمة لا تحمل ' . number_format($qty, 2)
                             . ' — المتاح فيها ' . number_format((float) $rows[$from]['allocated_qty'], 2) . ' ساعة'));
        }

        /* HO-05 مبكّرًا (والقادحُ يصدُّه في القاعدةِ أيضًا) */
        $st = $conn->prepare("SELECT 1 FROM fin_financial_periods
                              WHERE company_id=? AND period_type='month' AND posting_allowed=0
                                AND ? BETWEEN start_date AND end_date LIMIT 1");
        $st->bind_param('is', $co, $date);
        $st->execute();
        $closed = (bool) $st->get_result()->fetch_row();
        $st->close();
        if ($closed) {
            return array('ok' => false, 'code' => 409, 'swap_id' => null,
                         'reasons' => array('الشهر مغلق — لا حدث يعيد احتساب شهر مقفل (HO-05)'));
        }

        $swapId = 0;
        try {
            $gate->runInTransaction(function ($g) use (&$swapId, $conn, $co, $from, $to, $qty, $date, $doc, $why, $actor) {
                /* القيمُ تُقرأ داخلَ المعاملةِ (لا من لقطةٍ سابقةٍ قد تتقادم) ثم
                   تُكتب مطلقةً — النقصُ أولًا ثم الزيادة، وحارسُ السعةِ وقيودُ
                   CHECK يرفضان أيَّ تجاوزٍ بنيويًّا */
                $f = $g->selectOne('op_containers', array('columns' => array('allocated_qty'), 'where' => array('id' => $from)));
                $t = $g->selectOne('op_containers', array('columns' => array('allocated_qty'), 'where' => array('id' => $to)));
                $newFrom = round((float) $f['allocated_qty'] - $qty, 2);
                $newTo   = round((float) $t['allocated_qty'] + $qty, 2);
                if ($newFrom < 0) { throw new \RuntimeException('الرصيد تغير أثناء الحفظ — أعد المحاولة'); }
                $g->update('op_containers', array('allocated_qty' => $newFrom), array('id' => $from));
                $g->update('op_containers', array('allocated_qty' => $newTo), array('id' => $to));
                $swapId = (int) $g->insert('container_swaps', array(
                    'container_id'    => $from,
                    'swap_kind'       => 'حصة',
                    'to_container_id' => $to,
                    'moved_qty'       => $qty,
                    'effective_from'  => $date,
                    'reason'          => mb_substr($why, 0, 190),
                    'doc_ref'         => mb_substr($doc, 0, 190),
                    'created_by'      => (int) $actor ?: null,
                ));
                if ($swapId <= 0) { throw new \RuntimeException('فشل تسجيل حدث التسليم'); }
            });
        } catch (\Throwable $e) {
            return array('ok' => false, 'code' => 409, 'swap_id' => null,
                         'reasons' => array('رفضته القاعدة: ' . mb_substr($e->getMessage(), 0, 160)));
        }
        return array('ok' => true, 'code' => 201, 'swap_id' => $swapId, 'reasons' => array());
    }
}
