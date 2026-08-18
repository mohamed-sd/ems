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
 *
 * الجذر المحايد (ADR-15 · REV-04 §6.2) — خلف علم EMS_EVENT_ROOT:
 *   off (افتراضًا)  = سلوك اليوم حرفيًا: الكتابة في الدفتر المالي وحده.
 *   publish        = كتابة مزدوجة ذرّية بنفس معاملة المستدعي: الحقيقة أولًا في
 *                    ems_business_events ثم إسقاطها المالي في fin_financial_events
 *                    حاملًا root_event_id — فيقرأ أي مستهلكٍ مستقبلي الحقيقةَ من
 *                    الجذر لا من غرفة المالية. مستهلك المالية ومؤشره لا يتحركان.
 *   العطالة متسقة: مفتاح idempotency واحد للجذر وإسقاطه؛ إعادة نشر عمليةٍ
 *   منشورة لا تُنشئ جذرًا ثانيًا، وتشفي إسقاطًا قديمًا بلا جذرٍ بربطه (self-heal).
 *   NULL في root_event_id دلاليٌّ مقصود: صفٌّ يدويٌّ أو سابقٌ للجذر.
 */

namespace App\Core;

require_once __DIR__ . '/../../includes/catch_log.php';

require_once __DIR__ . '/ServerId.php';
require_once __DIR__ . '/EventValidationException.php';
// ENG-01 ④: مروحةُ الصادرِ تُنادى من writeRoot — ولا مُحمِّلَ تلقائيًّا مضمونًا هنا.
require_once __DIR__ . '/../Services/Bus/EventOutboxFanout.php';

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
        'capacity', // update0005 · CAP-29: مجالُ القدرات — أحداثُ CAP-01 §14 الستة
        'risk',     // M-16 §10-1: إدارةُ المخاطرِ تنشر ٢٦ حدثًا — والتكاملُ بالأحداثِ
                    // لا بالنداءِ المباشر. أحداثُها حقائقُ محايدةٌ بلا إسقاطٍ ماليٍّ
                    // (RK-06: تقييمُ الخطرِ لا يُنشئ قيدًا) — فتُنشر بـpublishFact حصرًا.
    );

    /** الحقول المرجعية: مفاتيح رقمية إلى الجداول المالكة — لا نسخ بيانات. */
    const REF_FIELDS = array(
        'project_id', 'contract_id', 'equipment_id',
        'supplier_entity_id', 'customer_entity_id', 'operator_employee_id',
    );

    /** حالات الحقيقة (ADR-18): العكسي = نفس الأثر بكميةٍ سالبة. */
    const ROOT_STATUSES = array('recorded', 'corrected', 'reversed');

    /** أنواعُ الأثر الحصرية (FES §4.1 · H-12) — ما ليس فيها يسقط إلى الاشتقاق. */
    const EFFECT_TYPES = array(
        'client_receivable', 'supplier_accrual', 'operator_due', 'project_cost',
        'equip_cost', 'payment', 'receipt', 'settlement', 'depreciation',
        'tax_return', 'finance_installment', 'adjustment_reversal',
    );

    /**
     * تجاوزٌ اختباريٌّ لوضع الجذر: null = من البيئة (EMS_EVENT_ROOT)،
     * 'off'/'publish' = فرضٌ صريح — للاختبارات الحتمية حصرًا.
     */
    public static $rootModeOverride = null;

    /** وضع الجذر المحايد النافذ: off (سلوك اليوم) أو publish (كتابة مزدوجة). */
    private static function rootMode()
    {
        if (self::$rootModeOverride !== null) {
            return (string) self::$rootModeOverride;
        }
        return function_exists('ems_env') ? (string) ems_env('EMS_EVENT_ROOT', 'off') : 'off';
    }

    /**
     * التحقق والتطبيع المشترك لعقد §9 — تستعمله publish() (الإسقاط المالي)
     * وpublishFact() (الحقيقة المحايدة): قواعد إلزامٍ وصيغٍ ومراجع واحدة للاثنين.
     *
     * @throws EventValidationException عند أي خرقٍ للعقد.
     */
    private static function normalize(array $e)
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
        $correlation = (isset($e['correlation_id']) && $e['correlation_id'] !== '' && $e['correlation_id'] !== null)
            ? (string) $e['correlation_id']                      // مشتق: يرث الجذر حرفيًا
            : ServerId::correlationId();                          // جذر: توليد جديد
        $idem = (isset($e['idempotency_key']) && $e['idempotency_key'] !== '' && $e['idempotency_key'] !== null)
            ? (string) $e['idempotency_key']
            : ServerId::idempotencyKey($e['event_key'], $e['entity_type'], intval($e['entity_id']));
        if (strlen($idem) > 64 || strlen($correlation) > 64) {
            throw new EventValidationException('عقد §9: correlation/idempotency ≤ 64 حرفًا');
        }

        return array(
            'company_id'   => intval($e['company_id']),
            'event_key'    => (string) $e['event_key'],
            'category'     => (string) $e['category'],
            'source_module'=> (string) $e['source_module'],
            'entity_type'  => (string) $e['entity_type'],
            'entity_id'    => intval($e['entity_id']),
            'created_by'   => intval($e['created_by']),
            'occurred_at'  => (string) $e['occurred_at'],
            'correlation_id' => $correlation,
            'idempotency_key' => $idem,
            'refs'         => $refs,
            'amount'       => isset($e['amount']) ? round((float) $e['amount'], 2) : 0.00,
            'currency'     => (isset($e['currency']) && $e['currency'] !== '') ? (string) $e['currency'] : 'SDG',
            'quantity'     => (isset($e['quantity']) && $e['quantity'] !== null && $e['quantity'] !== '') ? (string) round((float) $e['quantity'], 4) : null,
            'unit'         => (isset($e['unit']) && $e['unit'] !== '') ? (string) $e['unit'] : null,
            'payload_json' => json_encode($e['payload'], JSON_UNESCAPED_UNICODE),
            'source_ref'   => (isset($e['source_ref']) && $e['source_ref'] !== '') ? (string) $e['source_ref'] : null,
        );
    }

    /**
     * نشر حقيقةٍ محايدةٍ فقط (ADR-15 · D05 §9.3): تُكتب في الجذر ems_business_events
     * بلا أي إسقاطٍ مالي — لأحداث دورة العمل (request.submitted/…) التي ليست
     * أحداثًا مالية فلا يجوز أن تلوّث الدفتر. خلف علم EMS_EVENT_ROOT نفسه:
     * off = لا-عملية موثَّقة تعيد null (مفتاح إطفاءٍ واحدٌ لعائلة الجذر كلها).
     * العقد والتحقق نفسه (§9)، والعطالة على uq_ebe_idempotency تعيد id القائم.
     *
     * @return array{id:int,correlation_id:string,idempotency_key:string}|null
     * @throws EventValidationException عند أي خرقٍ للعقد.
     */
    public static function publishFact(\mysqli $conn, array $e)
    {
        if (self::rootMode() !== 'publish') {
            return null;
        }
        $n = self::normalize($e);
        $rootId = self::writeRoot($conn, array(
            'company_id'   => $n['company_id'],
            'event_key'    => $n['event_key'],
            'category'     => $n['category'],
            'source_module'=> $n['source_module'],
            'source_ref'   => $n['source_ref'],
            'entity_type'  => $n['entity_type'],
            'entity_id'    => $n['entity_id'],
            'quantity'     => $n['quantity'],
            'unit'         => $n['unit'],
            'amount'       => $n['amount'],
            'currency'     => $n['currency'],
            'refs'         => $n['refs'],
            'event_status' => (isset($e['event_status']) && in_array($e['event_status'], self::ROOT_STATUSES, true)) ? (string) $e['event_status'] : 'recorded',
            'reverses_event_id' => (isset($e['reverses_event_id']) && is_numeric($e['reverses_event_id']) && intval($e['reverses_event_id']) > 0) ? intval($e['reverses_event_id']) : null,
            'occurred_at'  => $n['occurred_at'],
            'payload'      => $n['payload_json'],
            'correlation_id' => $n['correlation_id'],
            'idempotency_key' => $n['idempotency_key'],
            'created_by'   => $n['created_by'],
        ));
        return array(
            'id' => $rootId,
            'correlation_id' => $n['correlation_id'],
            'idempotency_key' => $n['idempotency_key'],
        );
    }

    /**
     * نشر حدثٍ مؤسسيٍّ واحد وفق عقد §9 على اتصال المستدعي (يشارك معاملته).
     *
     * @return array{id:int,event_no:string,correlation_id:string,idempotency_key:string,duplicate:bool}
     * @throws EventValidationException عند أي خرقٍ للعقد.
     */
    public static function publish(\mysqli $conn, array $e)
    {
        // ── 1-4) التحقق والتطبيع المشترك (§9) — القواعد نفسها للحقيقة وإسقاطها ──
        $n = self::normalize($e);
        $companyId = $n['company_id'];
        $correlation = $n['correlation_id'];
        $idem = $n['idempotency_key'];
        $refs = $n['refs'];
        $amount = $n['amount'];
        $currency = $n['currency'];
        $quantity = $n['quantity'];
        $unit = $n['unit'];
        $payload = $n['payload_json'];
        $sourceRef = $n['source_ref'];
        $legacyType = (isset($e['legacy_event_type']) && $e['legacy_event_type'] !== '') ? (string) $e['legacy_event_type'] : 'enterprise';
        $notes = (isset($e['notes']) && $e['notes'] !== '') ? (string) $e['notes'] : null;
        // ── العطالةُ قبل كل الحوارس (N-01/M-39): عمليةٌ منشورةٌ سلفًا تعيد
        //    مرجعَها القائم **ولو أُقفلت الفترةُ بعدها** — لا كتابةَ جديدةَ فيها. ──
        $pre = $conn->prepare('SELECT id, event_no, correlation_id, root_event_id
                                 FROM `fin_financial_events` WHERE idempotency_key = ? LIMIT 1');
        if ($pre) {
            $pre->bind_param('s', $idem);
            $pre->execute();
            $preRow = $pre->get_result()->fetch_assoc();
            $pre->close();
            if ($preRow) {
                // الشفاء الذاتي (ADR-15) كما في مسار 1062: إسقاطٌ قديمٌ بلا جذرٍ
                // يُربط الآن — وwriteRoot عاطلةٌ بالعطالة فلا جذرَ ثانيًا.
                if ($preRow['root_event_id'] === null && self::rootMode() === 'publish') {
                    try {
                        $healRoot = self::writeRoot($conn, array(
                            'company_id'   => $companyId,
                            'event_key'    => $n['event_key'],
                            'category'     => $n['category'],
                            'source_module'=> $n['source_module'],
                            'source_ref'   => $sourceRef,
                            'entity_type'  => $n['entity_type'],
                            'entity_id'    => $n['entity_id'],
                            'quantity'     => $quantity,
                            'unit'         => $unit,
                            'amount'       => $amount,
                            'currency'     => $currency,
                            'refs'         => $refs,
                            'event_status' => 'recorded',
                            'reverses_event_id' => null,
                            'occurred_at'  => $n['occurred_at'],
                            'payload'      => $payload,
                            'correlation_id' => $correlation,
                            'idempotency_key' => $idem,
                            'created_by'   => $n['created_by'],
                        ));
                        $h = $conn->prepare('UPDATE `fin_financial_events` SET root_event_id = ? WHERE id = ? AND root_event_id IS NULL');
                        $hid = intval($preRow['id']);
                        $h->bind_param('ii', $healRoot, $hid);
                        $h->execute();
                        $h->close();
                        $preRow['root_event_id'] = $healRoot;
                    } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'EventPublisher pre-dup heal');
                        error_log('EventPublisher pre-dup heal: ' . $t->getMessage());
                    }
                }
                return array(
                    'id' => intval($preRow['id']),
                    'event_no' => (string) $preRow['event_no'],
                    'correlation_id' => (string) $preRow['correlation_id'],
                    'idempotency_key' => $idem,
                    'root_event_id' => ($preRow['root_event_id'] !== null) ? intval($preRow['root_event_id']) : null,
                    'duplicate' => true,
                );
            }
        }

        // ── M-39 (UX-02 §15.4 · FES §3.1): لا نشرَ ماليًّا في فترةٍ مقفلة — 423 ──
        // الناشرُ نقطةُ الخنق الوحيدةُ للدفتر فالحارسُ هنا يغطي كلَّ منبع، ويسبق
        // كتابةَ الجذر حتى لا يبقى جذرٌ يتيمٌ لمستدعٍ بلا معاملة.
        require_once dirname(__DIR__, 2) . '/includes/period_guard.php';
        $pchk = ems_period_check($conn, $companyId, $n['occurred_at']);
        if (!$pchk['ok']) {
            throw new EventValidationException($pchk['reason']);
        }

        $eventNo = ServerId::nextNo($conn, 'fin_financial_events:EV:' . $companyId, 'EV');

        // ── 4ب) الجذر المحايد (ADR-15): الحقيقة تُدوَّن قبل إسقاطها المالي —
        //        نفس الاتصال/المعاملة، فالذرّية ذرّية المستدعي نفسها. ──
        $rootId = null;
        if (self::rootMode() === 'publish') {
            $rootId = self::writeRoot($conn, array(
                'company_id'   => $companyId,
                'event_key'    => $n['event_key'],
                'category'     => $n['category'],
                'source_module'=> $n['source_module'],
                'source_ref'   => $sourceRef,
                'entity_type'  => $n['entity_type'],
                'entity_id'    => $n['entity_id'],
                'quantity'     => $quantity,
                'unit'         => $unit,
                'amount'       => $amount,
                'currency'     => $currency,
                'refs'         => $refs,
                'event_status' => (isset($e['event_status']) && in_array($e['event_status'], self::ROOT_STATUSES, true)) ? (string) $e['event_status'] : 'recorded',
                'reverses_event_id' => (isset($e['reverses_event_id']) && is_numeric($e['reverses_event_id']) && intval($e['reverses_event_id']) > 0) ? intval($e['reverses_event_id']) : null,
                'occurred_at'  => $n['occurred_at'],
                'payload'      => $payload,
                'correlation_id' => $correlation,
                'idempotency_key' => $idem,
                'created_by'   => $n['created_by'],
            ));
        }

        // ── 4ج) عقد FES §3.1 (H-12): الطرفُ الموحّد والفترةُ وبصمةُ المستند ──
        // الناشرُ يستنتج (خريطة الاشتقاق) — والمستدعي له تمريرُ الأدقّ صراحةً.
        $srcLineId  = (isset($e['source_line_id']) && is_numeric($e['source_line_id'])) ? intval($e['source_line_id']) : 0;
        $srcDocVer  = (isset($e['source_doc_version']) && is_numeric($e['source_doc_version']) && intval($e['source_doc_version']) > 0) ? intval($e['source_doc_version']) : 1;
        $causation  = (isset($e['causation_id']) && $e['causation_id'] !== '') ? (string) $e['causation_id'] : null;
        $dueDate    = (isset($e['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $e['due_date'])) ? substr((string) $e['due_date'], 0, 10) : null;
        $contractLineId = (isset($e['contract_line_id']) && is_numeric($e['contract_line_id']) && intval($e['contract_line_id']) > 0) ? intval($e['contract_line_id']) : null;
        list($partyType, $partyId) = self::resolveParty($e, $legacyType, $refs);
        $periodId   = self::resolvePeriodId($conn, $companyId, $n['occurred_at']);
        $fesStatus  = 'Published'; // الناشرُ هو خطوةُ النشر (Draft ما قبل الناشر)

        // ── M-40 (FES §3.3): ثلاثيةُ العملة عند النشر — السعرُ النافذ بتاريخ
        //    الوقوع من fin_fx_rates، والمعادلُ ROUND(amount × fx, 2). المستدعي
        //    له تمريرُ سعره (يثبت بالاعتماد)، وغيابُ السعر NULL معلَنٌ لا مخترَع. ──
        $fxRate = (isset($e['fx_rate']) && is_numeric($e['fx_rate']) && (float) $e['fx_rate'] > 0)
            ? (float) $e['fx_rate']
            : self::resolveFxRate($conn, $companyId, $currency, $n['occurred_at']);
        $baseAmount = ($fxRate !== null && $amount !== null) ? round(((float) $amount) * $fxRate, 2) : null;

        // ── 5) الإدراج — أعمدة صريحة، prepared، على اتصال/معاملة المستدعي ──
        $sql = 'INSERT INTO `fin_financial_events`
            (company_id, event_no, event_type, event_key, category, source_module, source_ref,
             entity_type, entity_id, source_line_id, source_doc_version, amount, quantity, unit, currency,
             fx_rate, base_amount,
             project_id, contract_id, contract_line_id, equipment_id, supplier_entity_id, customer_entity_id, operator_employee_id,
             party_type, party_id, fiscal_period_id, due_date, fes_status,
             state, occurred_at, notes, correlation_id, causation_id, idempotency_key, schema_version, payload, created_by, root_event_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('EventPublisher: prepare failed: ' . $conn->error);
        }
        $entityType = $n['entity_type'];
        $entityId   = $n['entity_id'];
        $createdBy  = $n['created_by'];
        $state      = 'draft';
        $schemaVer  = 1;
        $eventKey   = $n['event_key'];
        $category   = $n['category'];
        $sourceModule = $n['source_module'];
        $occurredAt = $n['occurred_at'];
        // الأنواع للـ39 وسيطًا (بترتيب الأعمدة أعلاه حرفيًّا)
        $stmt->bind_param(
            'isssssssiiidsssddiiiiiiisiissssssssisii',
            $companyId, $eventNo, $legacyType, $eventKey, $category, $sourceModule, $sourceRef,
            $entityType, $entityId, $srcLineId, $srcDocVer, $amount, $quantity, $unit, $currency,
            $fxRate, $baseAmount,
            $refs['project_id'], $refs['contract_id'], $contractLineId, $refs['equipment_id'],
            $refs['supplier_entity_id'], $refs['customer_entity_id'], $refs['operator_employee_id'],
            $partyType, $partyId, $periodId, $dueDate, $fesStatus,
            $state, $occurredAt, $notes, $correlation, $causation, $idem, $schemaVer, $payload, $createdBy, $rootId
        );
        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $err = $stmt->error;
            $stmt->close();
            if ($errno === 1062 && strpos($err, 'uq_ffe_idempotency') !== false) {
                // عطالة الأثر على مستوى الدالة: العملية المنطقية منشورة مسبقًا.
                $q = $conn->prepare('SELECT id, event_no, correlation_id, root_event_id FROM `fin_financial_events` WHERE idempotency_key = ? LIMIT 1');
                $q->bind_param('s', $idem);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                // الشفاء الذاتي (ADR-15): إسقاطٌ قديمٌ نُشر والعلم مطفأ فبقي بلا
                // جذر — إن توفّر الآن جذرُ نفس المفتاح نربطه بدل تركه يتيمًا.
                if ($rootId !== null && $row && $row['root_event_id'] === null) {
                    $h = $conn->prepare('UPDATE `fin_financial_events` SET root_event_id = ? WHERE id = ? AND root_event_id IS NULL');
                    $hid = intval($row['id']);
                    $h->bind_param('ii', $rootId, $hid);
                    $h->execute();
                    $h->close();
                    $row['root_event_id'] = $rootId;
                }
                return array(
                    'id' => intval($row['id']),
                    'event_no' => (string) $row['event_no'],
                    'correlation_id' => (string) $row['correlation_id'],
                    'idempotency_key' => $idem,
                    'root_event_id' => isset($row['root_event_id']) && $row['root_event_id'] !== null ? intval($row['root_event_id']) : null,
                    'duplicate' => true,
                );
            }
            throw new \RuntimeException('EventPublisher: execute failed: ' . $err);
        }
        $stmt->close();
        $newId = intval($conn->insert_id);

        // ── 6) أثرُ الحدث (FES §3.2 · H-12): رأسٌ وأثرُه معًا في معاملة المستدعي ──
        // النوعُ من المستدعي إن مرّره، وإلا اشتقاقٌ من نوع الجسر والمراجع.
        // UQ المركّب يجعل التكرار عطالةً (1062 يُبتلع عمدًا — الأثرُ قائم).
        $effType = (isset($e['effect_type']) && in_array((string) $e['effect_type'], self::EFFECT_TYPES, true))
            ? (string) $e['effect_type']
            : self::deriveEffectType($legacyType, $refs);
        if ($effType !== null) {
            $es = $conn->prepare('INSERT INTO `fin_event_effects`
                (company_id, event_id, effect_type, party_type, party_id, contract_line_id, amount, base_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\')');
            if ($es) {
                // أعمدةُ المفتاح الفريد لا تقبل NULL (عطالةٌ حقيقية): الغياب = ''/0
                $effPartyType = ($partyType === null) ? '' : $partyType;
                $effPartyId   = ($partyId === null) ? 0 : $partyId;
                $effLineId    = ($contractLineId === null) ? 0 : $contractLineId;
                $es->bind_param('iissiidd', $companyId, $newId, $effType, $effPartyType, $effPartyId, $effLineId, $amount, $baseAmount);
                if (!$es->execute() && $es->errno !== 1062) {
                    $err = $es->error;
                    $es->close();
                    throw new \RuntimeException('EventPublisher: effect insert failed: ' . $err);
                }
                $es->close();
            }
        }

        return array(
            'id' => $newId,
            'event_no' => $eventNo,
            'correlation_id' => $correlation,
            'idempotency_key' => $idem,
            'root_event_id' => $rootId,
            'duplicate' => false,
        );
    }

    /**
     * الطرفُ الموحّد (FES §4.1 · H-12): من المستدعي صراحةً، وإلا فالمرجعُ
     * الوحيدُ المعمور، وعند التعدد الأسبقيةُ لنوع الحدث (إيراد → عميل ·
     * رواتب → مشغّل · وإلا مورد). صفرُ اختراع: لا مرجعَ = NULL معلَن.
     *
     * @return array{0:?string,1:?int}
     */
    private static function resolveParty(array $e, $legacyType, array $refs)
    {
        $valid = array('customer', 'supplier', 'operator', 'employee', 'owner_dept');
        if (isset($e['party_type'], $e['party_id'])
            && in_array((string) $e['party_type'], $valid, true)
            && is_numeric($e['party_id']) && intval($e['party_id']) > 0) {
            return array((string) $e['party_type'], intval($e['party_id']));
        }
        $cust = isset($refs['customer_entity_id']) ? intval($refs['customer_entity_id']) : 0;
        $sup  = isset($refs['supplier_entity_id']) ? intval($refs['supplier_entity_id']) : 0;
        $op   = isset($refs['operator_employee_id']) ? intval($refs['operator_employee_id']) : 0;
        $set = array();
        if ($cust > 0) { $set['customer'] = $cust; }
        if ($sup > 0)  { $set['supplier'] = $sup; }
        if ($op > 0)   { $set['operator'] = $op; }
        if (empty($set)) { return array(null, null); }
        if (count($set) === 1) {
            $t = array_keys($set);
            return array($t[0], $set[$t[0]]);
        }
        // تعدُّدٌ: الأسبقية بنوع الحدث
        if (in_array($legacyType, array('revenue', 'receivable'), true) && isset($set['customer'])) {
            return array('customer', $set['customer']);
        }
        if ($legacyType === 'payroll' && isset($set['operator'])) {
            return array('operator', $set['operator']);
        }
        if (isset($set['supplier'])) { return array('supplier', $set['supplier']); }
        $t = array_keys($set);
        return array($t[0], $set[$t[0]]);
    }

    /**
     * السعرُ النافذ لعملةٍ بتاريخٍ — آخرُ سعرٍ سريانُه ≤ التاريخ (FES §3.3 · M-40).
     * SQL مباشرٌ لا بوابة: الناشرُ يُستدعى من كل السياقات (شاشة · cron · CLI).
     * NULL = لا سعرَ يغطي التاريخ — غيابٌ معلَنٌ لا يُخترع.
     */
    private static function resolveFxRate(\mysqli $conn, $companyId, $currency, $occurredAt)
    {
        $currency = trim((string) $currency);
        if ($currency === '') { return null; }
        $date = ($occurredAt !== null && $occurredAt !== '') ? substr((string) $occurredAt, 0, 10) : date('Y-m-d');
        $st = $conn->prepare('SELECT rate_to_base FROM `fin_fx_rates`
            WHERE company_id = ? AND currency_code = ? AND COALESCE(is_deleted, 0) = 0
              AND effective_from <= ?
            ORDER BY effective_from DESC LIMIT 1');
        if (!$st) { return null; }
        $cid = intval($companyId);
        $st->bind_param('iss', $cid, $currency, $date);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (float) $row['rate_to_base'] : null;
    }

    /**
     * الفترةُ المالية الشهرية التي يقع فيها تاريخُ الحدث — NULL إن لا فترةَ
     * معرَّفة (فجوةٌ معلَنة؛ حارسُ «لا نشرَ في فترةٍ مقفلة» في M-39).
     */
    private static function resolvePeriodId(\mysqli $conn, $companyId, $occurredAt)
    {
        $date = ($occurredAt !== null && $occurredAt !== '') ? substr((string) $occurredAt, 0, 10) : date('Y-m-d');
        $st = $conn->prepare('SELECT id FROM `fin_financial_periods`
            WHERE company_id = ? AND period_type = \'month\'
              AND ? BETWEEN start_date AND end_date LIMIT 1');
        if (!$st) { return null; }
        $cid = intval($companyId);
        $st->bind_param('is', $cid, $date);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['id']) : null;
    }

    /**
     * اشتقاقُ نوع الأثر (FES §4.1) من نوع الجسر والمراجع — الخريطةُ نفسُها
     * المستعملة في التعبئة الرجعية (هجرة H-12). NULL = لا نسبةَ صادقة
     * (enterprise ذو المروحة القائمة يبقى بلا أثرٍ مشتقٍّ هنا — أحداثُ
     * مروحته المستقلة تحمل آثارَها بأنفسها).
     */
    private static function deriveEffectType($legacyType, array $refs)
    {
        $sup  = isset($refs['supplier_entity_id']) ? intval($refs['supplier_entity_id']) : 0;
        $proj = isset($refs['project_id']) ? intval($refs['project_id']) : 0;
        $eq   = isset($refs['equipment_id']) ? intval($refs['equipment_id']) : 0;
        switch ($legacyType) {
            case 'revenue':
            case 'receivable': return 'client_receivable';
            case 'payable':    return 'supplier_accrual';
            case 'payroll':    return 'operator_due';
            case 'settlement': return 'settlement';
            case 'expense':
                if ($sup > 0)  { return 'supplier_accrual'; }
                if ($proj > 0) { return 'project_cost'; }
                if ($eq > 0)   { return 'equip_cost'; }
                return null;
            default: return null;
        }
    }

    /**
     * كتابة الحقيقة في الجذر المحايد ems_business_events (ADR-15) — على اتصال
     * المستدعي نفسه (يشارك معاملته). عطالة المصدر بنفس مفتاح الإسقاط: التصادم
     * على uq_ebe_idempotency يعيد id الحقيقة القائمة بلا صفٍّ جديد.
     * (رقم BE قد يُستهلك ويضيع عند التصادم — فجوة ترقيمٍ مقبولة، نمط nextNo.)
     *
     * @return int معرّف الحقيقة (الجديدة أو القائمة).
     */
    private static function writeRoot(\mysqli $conn, array $r)
    {
        // ENG-01 ④ · «◆ ولا يُنشر حدثٌ لا اشتراكَ له» — الحارسُ قبلَ الكتابة لا بعدَها،
        // فيقول السببَ بالعربيةِ قبلَ أن يقولَه chk_consumers برقمِ خطإٍ (CK-11).
        // والعددُ يدخل جملةَ الإدراجِ نفسَها لأن القيدَ يُفحص لحظتَها لا بعدَها.
        $declaredConsumers = \App\Services\Bus\EventOutboxFanout::assertHasConsumer($conn, $r['event_key']);

        $eventNo = ServerId::nextNo($conn, 'ems_business_events:BE:' . $r['company_id'], 'BE');
        $uuid = ServerId::ulid();
        $sql = 'INSERT INTO `ems_business_events`
            (company_id, event_no, event_uuid, event_key, category, source_module, source_ref,
             entity_type, entity_id, quantity, unit, amount, currency,
             project_id, contract_id, equipment_id, supplier_entity_id, customer_entity_id, operator_employee_id,
             event_status, reverses_event_id, occurred_at, payload, correlation_id, idempotency_key, schema_version, created_by,
             consumers_declared)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException('EventPublisher(root): prepare failed: ' . $conn->error);
        }
        $schemaVer = 1;
        // الأنواع للـ28 وسيطًا: i + s×7 + i + s×2 + d + s + i×6 + s + i + s×4 + i×3
        $stmt->bind_param(
            'isssssssissdsiiiiiisissssiii',
            $r['company_id'], $eventNo, $uuid, $r['event_key'], $r['category'], $r['source_module'], $r['source_ref'],
            $r['entity_type'], $r['entity_id'], $r['quantity'], $r['unit'], $r['amount'], $r['currency'],
            $r['refs']['project_id'], $r['refs']['contract_id'], $r['refs']['equipment_id'],
            $r['refs']['supplier_entity_id'], $r['refs']['customer_entity_id'], $r['refs']['operator_employee_id'],
            $r['event_status'], $r['reverses_event_id'], $r['occurred_at'], $r['payload'],
            $r['correlation_id'], $r['idempotency_key'], $schemaVer, $r['created_by'],
            $declaredConsumers
        );
        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $err = $stmt->error;
            $stmt->close();
            if ($errno === 1062 && strpos($err, 'uq_ebe_idempotency') !== false) {
                $q = $conn->prepare('SELECT id FROM `ems_business_events` WHERE idempotency_key = ? LIMIT 1');
                $q->bind_param('s', $r['idempotency_key']);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                if ($row) {
                    return intval($row['id']);
                }
            }
            throw new \RuntimeException('EventPublisher(root): execute failed: ' . $err);
        }
        $stmt->close();
        $rootId = intval($conn->insert_id);

        // ENG-01 ④ · مروحةُ الصادرِ داخلَ معاملةِ الواقعةِ نفسِها: تُثبت
        // consumers_declared وتفتح صفَّ تسليمٍ لكلِّ مشتركٍ بمفتاحِ عطالةٍ فريد.
        // فإن انهارت الواقعةُ انهار معها الصادرُ والتسليم — ولا حدثَ لواقعةٍ لم تقع.
        \App\Services\Bus\EventOutboxFanout::open($conn, $rootId, $r['event_key'], $r['company_id']);

        return $rootId;
    }
}
