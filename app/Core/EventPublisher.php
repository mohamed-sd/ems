<?php
/**
 * ناشر الأحداث المؤسسي — EventPublisher (K3 · ADR-10 · عقد §9)
 * ───────────────────────────────────────────────────────────────────────────
 * الكاتب الوحيد المعتمد لحقول العقد على مخزن الأحداث (fin_financial_events —
 * سجلٌّ مؤسسيٌّ مشترك بين كل الإدارات؛ المالية أول مستهلكٍ لا المالك).
 *
 * الذرّية (Transactional Outbox): النشر يجري على اتصال المستدعي نفسه — إن كان
 * داخل معاملةٍ شارك فيها؛ فشل معاملة المصدر = تراجع الحدث معه (صفر حدثٍ يتيم).
 * ملاحظة تسلسل: event_no يُخصَّص من ems_sequences داخل المعاملة، فقفل صفّ
 * المتتالية يُمسَك حتى commit — نقطة تسلسلٍ لكل شركةٍ داخل نافذة المعاملة
 * (مقايضة مقبولة لطور الـMonolith، موثَّقة).
 *
 * الحوكمة (§9-1): حدثٌ ناقص الحقول الإلزامية يُرفض EventValidationException —
 * الإلزامية: event_key · category · source_module · company_id · entity_type ·
 * entity_id · occurred_at · created_by · payload. (Event ID خادمي = id ·
 * event_no/correlation/idempotency/schema_version يولّدها الناشر.)
 *
 * «الحدث يحمل مراجع لا نسخًا»: الحقول المرجعية (contract_id · project_id ·
 * equipment_id · supplier_entity_id · customer_entity_id · operator_employee_id)
 * مفاتيح رقمية إلى جداولها المالكة (contracts/project/… تبقى SSOT) — أي قيمةٍ
 * غير رقميةٍ تُرفض (ADR-09: لا مفاتيح نصية). لا يُنسخ أي حقل بياناتٍ من جداول
 * الإدارات إلى الحدث؛ التفاصيل الخاصة بالحدث نفسه في payload حصرًا.
 *
 * وراثة Correlation للتدفق المشتق (Fan-Out — جاهز لK5 الآن):
 *   • حدث الجذر: لا يمرَّر correlation_id ← الناشر يولّده (ServerId::correlationId).
 *   • الحدث المشتق: المستهلك يمرّر correlation_id الجذر كما استلمه حرفيًا ←
 *     يُخزَّن كما هو، فتتجمّع سلسلة الأثر كلها تحت معرّفٍ واحدٍ طرفًا لطرف.
 *   القيمة تعود في ناتج publish() ليمرّرها الـDispatcher (K4) لكل مستهلك.
 *
 * العطالة (Idempotency) على مستوى الدالة: المفتاح حتميٌّ من
 * (event_key، entity_type، entity_id) ما لم يمرَّر صراحةً؛ إعادة نشر نفس العملية
 * المنطقية تُكتشف (1062 على uq_ffe_idempotency) وتعود duplicate=true بمعرّف
 * الحدث القائم — لا استثناء ولا صفٌّ جديد.
 *
 * event_type القديم يبقى بقيمة الجسر 'enterprise' (دلالة النوع الكاملة في
 * event_key/category) — فلا تراه فلاتر شاشات المالية القديمة (منع عدٍّ مزدوج).
 */

namespace App\Core;

require_once __DIR__ . '/ServerId.php';
require_once __DIR__ . '/EventValidationException.php';

class EventPublisher
{
    /** الحقول الإلزامية على المستدعي (§9) — الباقي يولّده الناشر أو سياقي. */
    const MANDATORY = array(
        'event_key', 'category', 'source_module', 'company_id',
        'entity_type', 'entity_id', 'occurred_at', 'created_by', 'payload',
    );

    const CATEGORIES = array('operational', 'financial', 'hr', 'fleet', 'maintenance', 'commercial', 'analytics');

    /** قيم source_module المسموحة (قائمة ENUM الحالية بعد جسر K3). */
    const SOURCE_MODULES = array(
        'sales', 'suppliers', 'workforce', 'procurement', 'warehouse', 'maintenance',
        'projects', 'revenue', 'assets', 'treasury', 'movement', 'finance', 'transport', 'system',
    );

    /** الحقول المرجعية: مفاتيح رقمية إلى الجداول المالكة — لا نسخ بيانات. */
    const REF_FIELDS = array(
        'project_id', 'contract_id', 'equipment_id',
        'supplier_entity_id', 'customer_entity_id', 'operator_employee_id',
    );

    /**
     * نشر حدثٍ مؤسسيٍّ واحد وفق عقد §9 على اتصال المستدعي (يشارك معاملته).
     *
     * @return array{id:int,event_no:string,correlation_id:string,idempotency_key:string,duplicate:bool}
     * @throws EventValidationException عند أي خرقٍ للعقد.
     */
    public static function publish(\mysqli $conn, array $e)
    {
        // ── 1) الإلزام الشامل: كل حقلٍ إلزاميٍّ حاضرٌ وغير فارغ (§9-1) ──
        foreach (self::MANDATORY as $f) {
            if (!array_key_exists($f, $e) || $e[$f] === null || $e[$f] === '') {
                throw new EventValidationException("عقد §9: الحقل الإلزامي مفقود: {$f}");
            }
        }

        // ── 2) الصيغ ──
        if (!preg_match('/^[a-z]+(\.[a-z_]+){1,2}$/', $e['event_key'])) {
            throw new EventValidationException('عقد §9: event_key يجب أن يكون بصيغة domain.entity.action اللاتينية');
        }
        if (!in_array($e['category'], self::CATEGORIES, true)) {
            throw new EventValidationException('عقد §9: category خارج التصنيفات السبعة');
        }
        if (!in_array($e['source_module'], self::SOURCE_MODULES, true)) {
            throw new EventValidationException('عقد §9: source_module غير معروف: ' . $e['source_module']);
        }
        foreach (array('company_id', 'entity_id', 'created_by') as $f) {
            if (!is_numeric($e[$f]) || intval($e[$f]) <= 0 || strval(intval($e[$f])) !== strval($e[$f])) {
                throw new EventValidationException("عقد §9: {$f} معرّفٌ رقميٌّ موجبٌ حصرًا");
            }
        }
        $occurred = \DateTime::createFromFormat('Y-m-d H:i:s', $e['occurred_at']);
        if (!$occurred || $occurred->format('Y-m-d H:i:s') !== $e['occurred_at']) {
            throw new EventValidationException('عقد §9: occurred_at بصيغة Y-m-d H:i:s (UTC) حصرًا');
        }
        if (!is_array($e['payload'])) {
            throw new EventValidationException('عقد §9: payload مصفوفة تُسلسل JSON حصرًا');
        }

        // ── 3) المراجع: رقمية حصرًا — الحدث يحمل مراجع لا نسخًا (ADR-09) ──
        $refs = array();
        foreach (self::REF_FIELDS as $f) {
            if (!isset($e[$f]) || $e[$f] === null || $e[$f] === '') {
                $refs[$f] = null;
                continue;
            }
            if (!is_numeric($e[$f]) || intval($e[$f]) <= 0 || strval(intval($e[$f])) !== strval($e[$f])) {
                throw new EventValidationException("عقد §9: {$f} مرجعٌ رقميٌّ إلى جدوله المالك — لا مفاتيح نصية");
            }
            $refs[$f] = intval($e[$f]);
        }

        // ── 4) ما يولّده الناشر ──
        $companyId = intval($e['company_id']);
        $correlation = (isset($e['correlation_id']) && $e['correlation_id'] !== '' && $e['correlation_id'] !== null)
            ? (string) $e['correlation_id']                      // مشتق: يرث الجذر حرفيًا
            : ServerId::correlationId();                          // جذر: توليد جديد
        $idem = (isset($e['idempotency_key']) && $e['idempotency_key'] !== '' && $e['idempotency_key'] !== null)
            ? (string) $e['idempotency_key']
            : ServerId::idempotencyKey($e['event_key'], $e['entity_type'], intval($e['entity_id']));
        if (strlen($idem) > 64 || strlen($correlation) > 64) {
            throw new EventValidationException('عقد §9: correlation/idempotency ≤ 64 حرفًا');
        }
        $eventNo = ServerId::nextNo($conn, 'fin_financial_events:EV:' . $companyId, 'EV');

        $amount   = isset($e['amount']) ? round((float) $e['amount'], 2) : 0.00;
        $currency = isset($e['currency']) && $e['currency'] !== '' ? (string) $e['currency'] : 'SDG';
        $quantity = (isset($e['quantity']) && $e['quantity'] !== null && $e['quantity'] !== '') ? (string) round((float) $e['quantity'], 4) : null;
        $unit     = (isset($e['unit']) && $e['unit'] !== '') ? (string) $e['unit'] : null;
        $legacyType = (isset($e['legacy_event_type']) && $e['legacy_event_type'] !== '') ? (string) $e['legacy_event_type'] : 'enterprise';
        $payload  = json_encode($e['payload'], JSON_UNESCAPED_UNICODE);
        $sourceRef = (isset($e['source_ref']) && $e['source_ref'] !== '') ? (string) $e['source_ref'] : null;
        $notes     = (isset($e['notes']) && $e['notes'] !== '') ? (string) $e['notes'] : null;

        // ── 5) الإدراج — أعمدة صريحة، prepared، على اتصال/معاملة المستدعي ──
        $sql = 'INSERT INTO `fin_financial_events`
            (company_id, event_no, event_type, event_key, category, source_module, source_ref,
             entity_type, entity_id, amount, quantity, unit, currency,
             project_id, contract_id, equipment_id, supplier_entity_id, customer_entity_id, operator_employee_id,
             state, occurred_at, notes, correlation_id, idempotency_key, schema_version, payload, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('EventPublisher: prepare failed: ' . $conn->error);
        }
        $entityType = (string) $e['entity_type'];
        $entityId   = intval($e['entity_id']);
        $createdBy  = intval($e['created_by']);
        $state      = 'draft';
        $schemaVer  = 1;
        $eventKey   = (string) $e['event_key'];
        $category   = (string) $e['category'];
        $sourceModule = (string) $e['source_module'];
        $occurredAt = (string) $e['occurred_at'];
        // الأنواع للـ27 وسيطًا: i + s×7 + i + d + s×3 + i×6 + s×5 + i + s + i
        $stmt->bind_param(
            'isssssssidsssiiiiiisssssisi',
            $companyId, $eventNo, $legacyType, $eventKey, $category, $sourceModule, $sourceRef,
            $entityType, $entityId, $amount, $quantity, $unit, $currency,
            $refs['project_id'], $refs['contract_id'], $refs['equipment_id'],
            $refs['supplier_entity_id'], $refs['customer_entity_id'], $refs['operator_employee_id'],
            $state, $occurredAt, $notes, $correlation, $idem, $schemaVer, $payload, $createdBy
        );
        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $err = $stmt->error;
            $stmt->close();
            if ($errno === 1062 && strpos($err, 'uq_ffe_idempotency') !== false) {
                // عطالة الأثر على مستوى الدالة: العملية المنطقية منشورة مسبقًا.
                $q = $conn->prepare('SELECT id, event_no, correlation_id FROM `fin_financial_events` WHERE idempotency_key = ? LIMIT 1');
                $q->bind_param('s', $idem);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                return array(
                    'id' => intval($row['id']),
                    'event_no' => (string) $row['event_no'],
                    'correlation_id' => (string) $row['correlation_id'],
                    'idempotency_key' => $idem,
                    'duplicate' => true,
                );
            }
            throw new \RuntimeException('EventPublisher: execute failed: ' . $err);
        }
        $stmt->close();
        return array(
            'id' => intval($conn->insert_id),
            'event_no' => $eventNo,
            'correlation_id' => $correlation,
            'idempotency_key' => $idem,
            'duplicate' => false,
        );
    }
}
