<?php
/**
 * StockReturnService — خدمةُ نطاقِ المرتجعاتِ المخزنية (FN-07 · CS-05)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ في موضعٍ واحدٍ يُختبر مرةً واحدة: الشاشةُ لا تكتب جدولًا — تنادي
 *   هذه الخدمة. وكلُّ قاعدةِ المرتجعِ هنا لا في الشاشة.
 *
 * ◆ القاعدةُ المُصحَّحة (FIXC-0039/0040):
 *     المتاحُ للإرجاع = **مجموعُ** كمياتِ الصرفِ للصنفِ في السند
 *                       − **مجموعُ** ما أُرجع منه قبلَه.
 *   كان التحققُ يقرأ سطرًا واحدًا ولا يطرح ما سبق — فيمكن إرجاعُ أكثرَ من
 *   المصروفِ بتكرارِ الإرجاع (ثغرةُ كميةٍ تُخرج مخزونًا بلا سند · P0).
 *
 * ◆ والقفلُ داخلَ المعاملة (‎FOR UPDATE‎) يمنع سباقَ طلبين متزامنين: قراءةٌ ثم
 *   كتابةٌ بلا قفلٍ تسمح لهما بالمرورِ معًا فيتجاوز المجموعُ المصروف.
 * ◆ وفوقَه قادحٌ في القاعدةِ يرفض التجاوزَ ولو نُودي الجدولُ من خارجِ التطبيق
 *   (RSK-M3: «القيدُ في القاعدةِ إلزاميٌّ — فالتطبيقُ يُتجاوز»).
 */

declare(strict_types=1);

namespace App\Services\Procurement;

class StockReturnService
{
    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * المتاحُ للإرجاعِ من صنفٍ في سندِ صرف.
     *
     * @return array{issued:float,returned:float,available:float,warehouse_id:int,found:bool}
     */
    public function returnableOf(int $companyId, int $issueId, int $itemId, bool $forUpdate = false): array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $out = array('issued' => 0.0, 'returned' => 0.0, 'available' => 0.0, 'warehouse_id' => 0, 'found' => false);

        // ① مجموعُ المصروفِ للصنفِ في السند — كلُّ السطورِ لا سطرٌ واحد.
        $st = $this->conn->prepare(
            "SELECT COALESCE(SUM(il.qty),0) issued, MAX(i.warehouse_id) wh, COUNT(*) n
               FROM proc_issue_line il
               JOIN proc_issue i ON i.id = il.issue_id
              WHERE il.issue_id = ? AND il.item_id = ? AND i.company_id = ?" . $lock);
        if (!$st) { throw new \RuntimeException('returnableOf/issued: ' . $this->conn->error); }
        $st->bind_param('iii', $issueId, $itemId, $companyId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row || (int) $row['n'] === 0) { return $out; }

        $out['found']        = true;
        $out['issued']       = (float) $row['issued'];
        $out['warehouse_id'] = (int) $row['wh'];

        // ② مجموعُ ما أُرجع من هذا الصنفِ بمرجعِ هذا السند.
        $st = $this->conn->prepare(
            "SELECT COALESCE(SUM(qty),0) r
               FROM proc_stock_move
              WHERE company_id = ? AND move_type = 'مرتجع'
                AND ref_type = 'issue' AND ref_id = ? AND item_id = ?" . $lock);
        if (!$st) { throw new \RuntimeException('returnableOf/returned: ' . $this->conn->error); }
        $st->bind_param('iii', $companyId, $issueId, $itemId);
        $st->execute();
        $out['returned'] = (float) ($st->get_result()->fetch_assoc()['r'] ?? 0);
        $st->close();

        $out['available'] = round($out['issued'] - $out['returned'], 4);
        return $out;
    }

    /**
     * يُرجع كميةً إلى المخزنِ بمرجعِ سندِ الصرفِ الأصليِّ **إلزامًا**.
     *
     * @return array{ok:bool,msg:string,move_id:int,available:float}
     */
    public function returnToWarehouse(int $companyId, int $issueId, int $itemId, float $qty, string $reason, int $actorId): array
    {
        if ($qty <= 0)        { return array('ok' => false, 'msg' => 'الكمية المرتجعة يجب أن تكون موجبة (422)', 'move_id' => 0, 'available' => 0.0); }
        if (trim($reason) === '') { return array('ok' => false, 'msg' => 'سبب الإرجاع إلزامي (422)', 'move_id' => 0, 'available' => 0.0); }

        $this->conn->begin_transaction();
        try {
            $st = $this->returnableOf($companyId, $issueId, $itemId, true);
            if (!$st['found']) {
                $this->conn->rollback();
                return array('ok' => false, 'msg' => 'سطر صرف غير موجود لهذا الصنف في السند (404)', 'move_id' => 0, 'available' => 0.0);
            }
            if ($qty > $st['available'] + 1e-9) {
                $this->conn->rollback();
                return array(
                    'ok'  => false,
                    'msg' => 'المرتجع يتجاوز المتاح — مصروف ' . $st['issued']
                           . ' · أرجع سابقا ' . $st['returned']
                           . ' · المتاح ' . $st['available'] . ' — يرفض 409',
                    'move_id' => 0, 'available' => $st['available'],
                );
            }

            $ins = $this->conn->prepare(
                "INSERT INTO proc_stock_move
                   (company_id,item_id,warehouse_id,move_type,qty,ref_type,ref_id,note,moved_at,created_by)
                 VALUES (?,?,?,'مرتجع',?,'issue',?,?,NOW(),?)");
            if (!$ins) { throw new \RuntimeException('insert prepare: ' . $this->conn->error); }
            $wh = (int) $st['warehouse_id'];
            $ins->bind_param('iiidisi', $companyId, $itemId, $wh, $qty, $issueId, $reason, $actorId);
            if (!$ins->execute()) { throw new \RuntimeException('insert: ' . $ins->error); }
            $moveId = (int) $this->conn->insert_id;
            $ins->close();

            $this->conn->commit();
            return array(
                'ok' => true, 'move_id' => $moveId,
                'available' => round($st['available'] - $qty, 4),
                'msg' => 'أرجع ' . $qty . ' بمرجع سند الصرف #' . $issueId
                       . ' — المتاح بعده ' . round($st['available'] - $qty, 4),
            );
        } catch (\Throwable $e) {
            // CS-12: لا ابتلاعَ ولا نجاحٌ كاذب — يُرجع رمزٌ ويُسجَّل السبب.
            $this->conn->rollback();
            error_log('StockReturnService failed: ' . $e->getMessage());
            // القادحُ في القاعدةِ يرفع 45000 برسالتِه — تُعرض كما هي فهي حكمُ القاعدة.
            $m = $e->getMessage();
            if (stripos($m, 'FN-07') !== false) {
                return array('ok' => false, 'msg' => $m, 'move_id' => 0, 'available' => 0.0);
            }
            return array('ok' => false, 'msg' => 'تعذر تسجيل المرتجع — لم يكتب شيء (ERR-STK-1044)', 'move_id' => 0, 'available' => 0.0);
        }
    }
}
