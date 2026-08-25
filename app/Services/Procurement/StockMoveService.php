<?php
/**
 * StockMoveService — خدمةُ نطاقِ حركاتِ المخزن (CS-05 · CS-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ «الشاشةُ لا تكتب جدولًا — تنادي خدمةَ نطاقها · والحكمُ في الخدمةِ المالكة ·
 *   فالحكمُ في موضعٍ واحدٍ يُختبر مرةً واحدة» (FIXA-0006).
 *
 * تسويةُ الجردِ والتحويلُ بين المخازن — وكلاهما كان يُكتب حرفيًّا في ملفِّ السطح.
 */

declare(strict_types=1);

namespace App\Services\Procurement;

class StockMoveService
{
    /** حركاتٌ تزيد الرصيدَ — المرجعُ الوحيدُ لحسابِ الرصيدِ من الحركات. */
    const INBOUND = array('استلام', 'تحويل وارد', 'مرتجع', 'تسوية زيادة');

    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * تسويةُ فرقِ الجردِ بحركةِ زيادةٍ أو عجزٍ بسببٍ موثَّق.
     * ◆ لا عمودَ رصيدٍ يُعدَّل — الرصيدُ محسوبٌ من الحركاتِ دائمًا.
     *
     * @return array{ok:bool,msg:string,move_id:int}
     */
    public function adjustCount(int $companyId, int $itemId, int $warehouseId, float $diff, string $reason, int $actorId): array
    {
        if (trim($reason) === '')   { return array('ok' => false, 'msg' => 'سبب التسوية إلزامي (422)', 'move_id' => 0); }
        if (abs($diff) < 0.001)     { return array('ok' => false, 'msg' => 'لا فرق — لا تسوية تكتب', 'move_id' => 0); }
        if ($itemId <= 0)           { return array('ok' => false, 'msg' => 'صنف غير صالح (422)', 'move_id' => 0); }

        /* ══ INJ-0100 · من صرف الصنفَ لا يُسوّي فرقَه ═══════════════════════════
             نصُّ القبول: «من نفّذ حركةَ صرفٍ **لا يستطيع تسويةَ فرقِ الصنفِ نفسِه**
             في الجرد بلا اعتمادِ مدير المخازن».
             والمقيسُ قبلَه: لا شيء يمنع — فمن صرف مئةً وسجّل تسعين يُسوّي العشرةَ
             الباقيةَ «عجزًا» بيدِه، والدفترُ يوافق. **وسببُ التسويةِ مطلوبٌ سلفًا،
             لكنَّ السببَ نصٌّ واليدُ الثانيةُ حكم.**
           ◆ والحدُّ **بالصنفِ والمخزنِ معًا**: من صرف صنفًا في مخزنٍ لا يُسوّيه
             فيه — ولا يُمنع من تسويةِ صنفٍ لم يمسَّه. فالمنعُ بقدرِ التعارضِ لا فوقَه.
           ◆ والنافذةُ **ثلاثون يومًا**: صرفٌ قديمٌ لا يُقيّد الجردَ إلى الأبد. */
        $st0 = $this->conn->prepare(
            "SELECT COUNT(*) FROM proc_stock_move
              WHERE company_id = ? AND item_id = ? AND warehouse_id = ?
                AND created_by = ? AND move_type IN ('صرف','تحويل صادر')
                AND moved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($st0) {
            $st0->bind_param('iiii', $companyId, $itemId, $warehouseId, $actorId);
            if ($st0->execute()) {
                $r0 = $st0->get_result()->fetch_row();
                $mine = $r0 ? (int) $r0[0] : 0;
                if ($mine > 0) {
                    $st0->close();
                    if (function_exists('ems_log_denial')) {
                        @ems_log_denial('STK-403-SELFADJ', 'item:' . $itemId . '@wh:' . $warehouseId,
                            'من صرف الصنف حاول تسوية فرقه');
                    }
                    return array('ok' => false, 'move_id' => 0,
                        'msg' => 'STK-403-SELFADJ: صرفت هذا الصنف من هذا المخزن خلال ٣٠ يوما ('
                               . $mine . ' حركة) — تسوية فرقه تحتاج يدا ثانية باعتماد مدير المخازن');
                }
            }
            $st0->close();
        }

        $type = $diff > 0 ? 'تسوية زيادة' : 'تسوية عجز';
        $qty  = abs($diff);

        $this->conn->begin_transaction();
        try {
            $st = $this->conn->prepare(
                "INSERT INTO proc_stock_move
                   (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
                 VALUES (?,?,?,?,?, 'stock_count', ?, NOW(), ?)");
            if (!$st) { throw new \RuntimeException('prepare: ' . $this->conn->error); }
            $st->bind_param('iiisdsi', $companyId, $itemId, $warehouseId, $type, $qty, $reason, $actorId);
            if (!$st->execute()) { throw new \RuntimeException('execute: ' . $st->error); }
            $id = (int) $this->conn->insert_id;
            $st->close();
            $this->conn->commit();
            return array('ok' => true, 'move_id' => $id,
                'msg' => 'سوي الفرق (' . ($diff > 0 ? '+' : '−') . $qty . ") بحركة «{$type}»");
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('StockMoveService::adjustCount: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'تعذرت التسوية — لم يكتب شيء (ERR-STK-1042)', 'move_id' => 0);
        }
    }

    /**
     * التحويلُ بين المخازن: **حركتان ذريّتان** بمرجعٍ واحدٍ في معاملةٍ واحدة —
     * فلا يظهر صنفٌ في مخزنين ولا يختفي من كليهما.
     * ◆ الرصيدُ يُقرأ **داخلَ المعاملةِ بقفل** (‎FOR UPDATE‎): القراءةُ الحرةُ ثم
     *   الكتابةُ تسمح لطلبين متزامنين بتحويلِ ما لا يوجد.
     *
     * @return array{ok:bool,msg:string,ref:string}
     */
    public function transfer(int $companyId, int $itemId, int $fromWh, int $toWh, float $qty, string $refSuffix, int $actorId): array
    {
        if ($itemId <= 0 || $fromWh <= 0 || $toWh <= 0 || $qty <= 0) {
            return array('ok' => false, 'msg' => 'الصنف والمخزنان والكمية إلزامية (422)', 'ref' => '');
        }
        if ($fromWh === $toWh) { return array('ok' => false, 'msg' => 'المصدر والوجهة مخزن واحد (422)', 'ref' => ''); }

        $this->conn->begin_transaction();
        try {
            $in = "'" . implode("','", self::INBOUND) . "'";
            $st = $this->conn->prepare(
                "SELECT COALESCE(SUM(CASE WHEN move_type IN ({$in}) THEN qty ELSE -qty END),0) b
                   FROM proc_stock_move
                  WHERE company_id=? AND item_id=? AND warehouse_id=? FOR UPDATE");
            if (!$st) { throw new \RuntimeException('balance prepare: ' . $this->conn->error); }
            $st->bind_param('iii', $companyId, $itemId, $fromWh);
            $st->execute();
            $bal = (float) ($st->get_result()->fetch_assoc()['b'] ?? 0);
            $st->close();

            if ($bal < $qty) {
                $this->conn->rollback();
                return array('ok' => false, 'ref' => '',
                    'msg' => "الرصيد المتاح في المصدر {$bal} فقط — والتحويل يرفض 409");
            }

            $ref = 'TRF-' . date('ymd-His') . ($refSuffix !== '' ? '-' . substr($refSuffix, 0, 6) : '');
            $ins = $this->conn->prepare(
                "INSERT INTO proc_stock_move
                   (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
                 VALUES (?,?,?,?,?, 'wh_transfer', ?, NOW(), ?)");
            if (!$ins) { throw new \RuntimeException('insert prepare: ' . $this->conn->error); }
            foreach (array(array($fromWh, 'تحويل صادر'), array($toWh, 'تحويل وارد')) as $leg) {
                $wh = (int) $leg[0]; $type = (string) $leg[1];
                $ins->bind_param('iiisdsi', $companyId, $itemId, $wh, $type, $qty, $ref, $actorId);
                if (!$ins->execute()) { throw new \RuntimeException('leg insert: ' . $ins->error); }
            }
            $ins->close();
            $this->conn->commit();
            return array('ok' => true, 'ref' => $ref, 'msg' => "حول {$qty} بمرجع {$ref} — حركتان ذريتان");
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('StockMoveService::transfer: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'فشلت المعاملة فألغيت الحركتان معا (ERR-STK-1043)', 'ref' => '');
        }
    }
}
