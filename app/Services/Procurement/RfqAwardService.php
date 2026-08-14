<?php
/**
 * RfqAwardService — الترسيةُ على عرضِ مورد (FN-05 · CS-05 · CS-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ FN-05: «مسارانِ متوازيانِ للترسيةِ على البياناتِ نفسِها» — الأولُ محروسٌ
 *   ويعمل، والثاني مسارٌ ثانٍ للفعلِ نفسِه. والوثيقةُ تشترط **مستندًا واحدًا
 *   ومسارًا واحدًا لكلِّ مرحلة**. فصار جدولُ ‎rfq_awards‎ لا يُكتب إلا من هنا،
 *   والشاشةُ الأخرى منظرٌ قارئٌ لا كاتب.
 *
 * ◆ CS-04: المعاملةُ تفتح **قبلَ** فحصِ التكرارِ فلا يمرُّ طلبان متزامنان —
 *   وفحصُ التكرارِ بقفلٍ (‎FOR UPDATE‎) لا بقراءةٍ حرة.
 * ◆ CS-08: لا حذفَ — إلغاءُ الترسيةِ حركةٌ عاكسةٌ بمرجعِها (‎reverse‎).
 */

declare(strict_types=1);

namespace App\Services\Procurement;

class RfqAwardService
{
    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @return array{ok:bool,msg:string,award_id:int}
     */
    public function award(int $companyId, int $quoteId, string $reason, int $actorId): array
    {
        if ($quoteId <= 0)        { return array('ok' => false, 'msg' => 'عرضٌ غيرُ صالح (422)', 'award_id' => 0); }
        if (trim($reason) === '') { return array('ok' => false, 'msg' => 'سببُ الترسية إلزامي — لا ترسيةَ صامتة (422)', 'award_id' => 0); }

        $this->conn->begin_transaction();
        try {
            $st = $this->conn->prepare(
                "SELECT q.rfq_id, q.line_id, q.supplier_id, q.unit_price, q.currency, q.qty_offered
                   FROM rfq_quotes q
                  WHERE q.id = ? AND q.company_id = ? FOR UPDATE");
            if (!$st) { throw new \RuntimeException('quote prepare: ' . $this->conn->error); }
            $st->bind_param('ii', $quoteId, $companyId);
            $st->execute();
            $q = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$q) {
                $this->conn->rollback();
                return array('ok' => false, 'msg' => 'عرضٌ غيرُ موجود (404)', 'award_id' => 0);
            }

            // بندٌ مُرسًى سلفًا ⇒ 409 بمرجعِ الترسيةِ القائمة (لا ترسيةَ ثانية).
            $lineIsNull = ($q['line_id'] === null);
            $sql = "SELECT id FROM rfq_awards WHERE company_id = ? AND rfq_id = ? AND line_id "
                 . ($lineIsNull ? 'IS NULL' : '= ?') . " FOR UPDATE";
            $st = $this->conn->prepare($sql);
            if (!$st) { throw new \RuntimeException('dup prepare: ' . $this->conn->error); }
            $rfqId = (int) $q['rfq_id'];
            if ($lineIsNull) { $st->bind_param('ii', $companyId, $rfqId); }
            else { $lineId = (int) $q['line_id']; $st->bind_param('iii', $companyId, $rfqId, $lineId); }
            $st->execute();
            $dup = $st->get_result()->fetch_assoc();
            $st->close();
            if ($dup) {
                $this->conn->rollback();
                return array('ok' => false, 'award_id' => (int) $dup['id'],
                    'msg' => 'البندُ مُرسًى من قبل بالترسية #' . (int) $dup['id'] . ' — 409');
            }

            $ins = $this->conn->prepare(
                "INSERT INTO rfq_awards
                   (company_id, rfq_id, line_id, supplier_id, quote_id, qty_awarded,
                    unit_price, currency, reason, awarded_by, awarded_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            if (!$ins) { throw new \RuntimeException('award prepare: ' . $this->conn->error); }
            $lineId   = $lineIsNull ? null : (int) $q['line_id'];
            $supplier = (int) $q['supplier_id'];
            $qty      = (float) $q['qty_offered'];
            $price    = (float) $q['unit_price'];
            $cur      = (string) $q['currency'];
            // الأنواعُ بترتيبِ الأعمدة: i i i i i d d s s i
            $ins->bind_param('iiiiiddssi',
                $companyId, $rfqId, $lineId, $supplier, $quoteId, $qty, $price, $cur, $reason, $actorId);
            if (!$ins->execute()) { throw new \RuntimeException('award insert: ' . $ins->error); }
            $awardId = (int) $this->conn->insert_id;
            $ins->close();

            $up = $this->conn->prepare(
                "UPDATE supplier_rfqs SET state='awarded', awarded_at=NOW(), awarded_by=?
                  WHERE id = ? AND company_id = ?");
            if (!$up) { throw new \RuntimeException('rfq prepare: ' . $this->conn->error); }
            $up->bind_param('iii', $actorId, $rfqId, $companyId);
            if (!$up->execute()) { throw new \RuntimeException('rfq update: ' . $up->error); }
            $up->close();

            $this->conn->commit();
            /* ── INJ-0002 · أثرُ الترسيةِ يقع بعد **نجاحِ المعاملة** ─────────────────
                 الترسيةُ قرارٌ مالٌّ يختار موردًا ويلزم النظامَ بسعره — فلا تقع بلا
                 سطرِ تدقيقٍ يحمل مَن رسّى وعلى مَن وبأيِّ سعرٍ وبأيِّ سبب. والمصدرُ
                 مُضمَّنٌ عند موضعِ الاستعمالِ لا في رأسِ الملفّ. */
            try {
                require_once dirname(dirname(dirname(__DIR__))) . '/includes/audit_trail.php';
                if (function_exists('ems_audit_change')) {
                    ems_audit_change($this->conn, 'procurement', 'rfq_awards', 'award', $awardId,
                        array(),
                        array('rfq_id' => $rfqId, 'quote_id' => $quoteId, 'supplier_id' => $supplier,
                              'unit_price' => $price, 'currency' => $cur, 'reason' => $reason),
                        array('company_id' => $companyId, 'user_id' => $actorId));
                }
            } catch (\Throwable $ae) { error_log('rfq award audit: ' . $ae->getMessage()); }
            return array('ok' => true, 'award_id' => $awardId,
                'msg' => 'رُسّي العرضُ #' . $quoteId . ' بسببٍ موثَّق — الترسية #' . $awardId);
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('RfqAwardService::award: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'فشلت الترسيةُ فأُلغيت بالكامل (ERR-PRC-1047)', 'award_id' => 0);
        }
    }

    /**
     * CS-08 — «العكسُ حركةٌ جديدةٌ بمرجعِها لا حذفٌ». إلغاءُ الترسيةِ يُنشئ صفًّا
     * عاكسًا بمرجعِ الأصلِ ولا يمحوه، ويُعيد الطلبَ إلى حالتِه السابقة.
     *
     * @return array{ok:bool,msg:string,reversal_id:int}
     */
    public function reverse(int $companyId, int $awardId, string $reason, int $actorId): array
    {
        if (trim($reason) === '') {
            return array('ok' => false, 'msg' => 'سببُ العكس إلزامي (422)', 'reversal_id' => 0);
        }
        $this->conn->begin_transaction();
        try {
            $st = $this->conn->prepare("SELECT * FROM rfq_awards WHERE id = ? AND company_id = ? FOR UPDATE");
            if (!$st) { throw new \RuntimeException('prepare: ' . $this->conn->error); }
            $st->bind_param('ii', $awardId, $companyId);
            $st->execute();
            $a = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$a) {
                $this->conn->rollback();
                return array('ok' => false, 'msg' => 'ترسيةٌ غيرُ موجودة (404)', 'reversal_id' => 0);
            }

            $ins = $this->conn->prepare(
                "INSERT INTO rfq_awards
                   (company_id, rfq_id, line_id, supplier_id, quote_id, qty_awarded,
                    unit_price, currency, reason, awarded_by, awarded_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            if (!$ins) { throw new \RuntimeException('reverse prepare: ' . $this->conn->error); }
            $rfqId    = (int) $a['rfq_id'];
            $lineId   = $a['line_id'] === null ? null : (int) $a['line_id'];
            $supplier = (int) $a['supplier_id'];
            $quoteId  = (int) $a['quote_id'];
            $qty      = -1 * (float) $a['qty_awarded'];   // ◆ العكسُ بالإشارةِ لا بالمحو
            $price    = (float) $a['unit_price'];
            $cur      = (string) $a['currency'];
            $note     = 'عكسُ الترسية #' . $awardId . ' — ' . $reason;
            $ins->bind_param('iiiiiddssi',
                $companyId, $rfqId, $lineId, $supplier, $quoteId, $qty, $price, $cur, $note, $actorId);
            if (!$ins->execute()) { throw new \RuntimeException('reverse insert: ' . $ins->error); }
            $revId = (int) $this->conn->insert_id;
            $ins->close();

            $up = $this->conn->prepare("UPDATE supplier_rfqs SET state='quoted', awarded_at=NULL, awarded_by=NULL
                                         WHERE id = ? AND company_id = ?");
            if (!$up) { throw new \RuntimeException('rfq prepare: ' . $this->conn->error); }
            $up->bind_param('ii', $rfqId, $companyId);
            if (!$up->execute()) { throw new \RuntimeException('rfq update: ' . $up->error); }
            $up->close();

            $this->conn->commit();
            return array('ok' => true, 'reversal_id' => $revId,
                'msg' => 'عُكست الترسية #' . $awardId . ' بحركةٍ عاكسةٍ #' . $revId . ' — والأصلُ باقٍ');
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('RfqAwardService::reverse: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'تعذّر العكس — لم يُكتب شيء (ERR-PRC-1048)', 'reversal_id' => 0);
        }
    }
}
