<?php
/**
 * خدمة التعويض — CompensationService (N-06 ركن ④)
 * ───────────────────────────────────────────────────────────────────────────
 * «ما نُشر ولم يُستهلك يُستأنف (المؤشر)، وما استُهلك جزئيًّا يُعكس بمرجعه
 * ولا يُترك يتيمًا» — PLAN-05 §3-①.
 *
 * تنفّذ عقد الحدث العكسي المرسى بنيويًّا في 2026_07_12 (event_status ·
 * reverses_event_id) وفق ADR-18: العكسي = الأثر نفسه بكميةٍ ومبلغٍ سالبين،
 * برابطٍ صريح، والأصل يبقى (لا حذف — عقيدة GOV-01 §7).
 *
 * عاطلة: عكسُ حدثٍ معكوسٍ سلفًا يعيد مرجعَ عكسه القائم بلا أثرٍ ثانٍ
 * (idempotency_key = rev:<مفتاح الأصل>).
 */

namespace App\Services;

require_once __DIR__ . '/../Core/EventPublisher.php';

use App\Core\EventPublisher;

class CompensationService
{
    /**
     * يعكس حدثًا ماليًّا استُهلك جزئيًّا (أو عُزل) بحدثٍ معوِّضٍ بمرجعه.
     *
     * @param \mysqli $conn    اتصال داخل معاملة المستدعي
     * @param int     $eventId معرّف الصف في fin_financial_events
     * @param string  $reason  سبب التعويض (إلزامي — لا عكسَ بلا سبب)
     * @param int     $actorId المستخدم الفاعل
     * @return array{reversal_id:int,duplicate:bool}
     */
    public static function reverseEvent(\mysqli $conn, $eventId, $reason, $actorId)
    {
        $eventId = intval($eventId);
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('CompensationService: لا عكسَ بلا سببٍ مكتوب');
        }

        $stmt = $conn->prepare('SELECT * FROM `fin_financial_events` WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $orig = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$orig) {
            throw new \RuntimeException('CompensationService: الحدث #' . $eventId . ' غير موجود');
        }

        // عاطلة: عكسٌ قائمٌ يُعاد مرجعُه ولا يتولد ثانٍ.
        $stmt = $conn->prepare('SELECT id FROM `fin_financial_events` WHERE reverses_event_id = ? LIMIT 1');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            return array('reversal_id' => intval($existing['id']), 'duplicate' => true);
        }

        $payload = json_decode((string) $orig['payload'], true);
        if (!is_array($payload)) {
            $payload = array();
        }
        $payload['compensation'] = array('reverses' => $eventId, 'reason' => $reason);

        $res = EventPublisher::publish($conn, array(
            'event_key' => (string) $orig['event_key'],
            'category' => (string) $orig['category'],
            'source_module' => (string) $orig['source_module'],
            'company_id' => intval($orig['company_id']),
            'entity_type' => (string) $orig['entity_type'],
            'entity_id' => intval($orig['entity_id']),
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => intval($actorId) ?: 1,
            'idempotency_key' => 'rev:' . $orig['idempotency_key'],
            'correlation_id' => (string) $orig['correlation_id'], // وراثة السلسلة (K3)
            'amount' => -1 * (float) $orig['amount'],
            'quantity' => ($orig['quantity'] !== null) ? -1 * (float) $orig['quantity'] : null,
            'unit' => $orig['unit'],
            'currency' => (string) $orig['currency'],
            'source_ref' => $orig['source_ref'],
            'project_id' => $orig['project_id'],
            'contract_id' => $orig['contract_id'],
            'equipment_id' => $orig['equipment_id'],
            'supplier_entity_id' => $orig['supplier_entity_id'],
            'customer_entity_id' => $orig['customer_entity_id'],
            'operator_employee_id' => $orig['operator_employee_id'],
            'payload' => $payload,
            'notes' => 'تعويض N-06: ' . $reason,
        ));
        $reversalId = intval($res['id']);

        // الربط الصريح: المعوِّض يشير لأصله، والأصل يُعلَّم معكوسًا — ولا يُحذف.
        $stmt = $conn->prepare('UPDATE `fin_financial_events` SET reverses_event_id = ? WHERE id = ? AND reverses_event_id IS NULL');
        $stmt->bind_param('ii', $eventId, $reversalId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE `fin_financial_events` SET event_status = 'reversed' WHERE id = ?");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $stmt->close();

        return array('reversal_id' => $reversalId, 'duplicate' => !empty($res['duplicate']));
    }
}
