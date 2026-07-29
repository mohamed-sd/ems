<?php
/**
 * خدمة إدخال الوحدات — TimesheetEntryService (UX-03 §8.3 · D02 §3.1/§4.2)
 * ───────────────────────────────────────────────────────────────────────────
 * «جداولُ سجل الوحدات القانوني القائمة تصير مصدرَ الكتابة الوحيد لوحدات اليوم
 *  عبر خدمة الإدخال — وشاشاتُ الساعات القديمة Views وRedirects» (UX-03 §8.1).
 *
 * المدخلات الستة (§8.3): المعدة · الوردية · التاريخ · الكمية · توزيعُ الزمن
 * بمسؤوله · المرجعُ الميداني — والباقي اشتقاقٌ من خريطة §5.1 (المعدة تجلب
 * نوعَها وموردَها ومشروعَها وعقدَها ومشغّلَها الحاليّ).
 *
 * آلة الحالات (§8.2):
 *   Draft → Submitted → SiteApproved → PartiesApproved → SalesApproved
 *   (+ ReturnedToSite بسببٍ وبالرقم نفسه — round_no يفتح جولةً والرقمُ ثابت)
 * أعمدة state القائمة تُطرق منها هنا: draft · submitted · site_approved ·
 * parties_review · parties_approved · sales_approved · returned. والسبعُ
 * الباقية (on_hold · converted · superseded · reversed · rejected · cancelled ·
 * والمراجعات §7.1) معلَنةٌ في الـENUM ولا تطرقها هذه الخدمة — أصحابُها
 * محرّكُ التحويل ومحرّكُ العكسيات، لا مسارُ الإدخال.
 *
 * العطالة (§8.2): المفتاح (المعدة × التاريخ × الوردية) خدميٌّ لا بنيوي —
 * «مفتاحٌ قائم → 200 بالمرجع (لا تكرار)». البنيويُّ الوحيد قيدُ sync_uuid.
 * (القياس 2026-07-26: أربعةُ أزواجٍ تاريخيةٍ تتصادم على المفتاح — فقيدُ
 *  UNIQUE بنيويٌّ كان سيكسر الترحيل؛ الدمجُ قرارُ مرحلة الترحيل ③ لا الخدمة.)
 *
 * حارس الطاقة: CapacityGuard القائم يُستدعى لا يُبنى — evaluate() عند الحفظ
 * (يحفظ معلَّمًا ولا يمنع)، وassertSiteApprovable() شرطُ اعتماد الموقع (§5.2).
 *
 * ⚠️ طورُ الكتابة المزدوجة (خطوة ② من منهج §9-③):
 *   المسار الحيّ timesheet يبقى سيّدَ الحقيقة والمالِ — هذه الخدمة تسجّل
 *   بالتوازي ولا تستدعي المروحةَ المالية إطلاقًا. اكتمالُ السلسلة يُسجَّل
 *   حالةً وحدثًا، والتحويلُ المالي يبقى لبوابة المالية القائمة حتى خطوة ④.
 *   والمرآةُ (mirrorFromTimesheet) مسجِّلُ وقائعَ لا مُنفِذُ قواعد: تكتب ما
 *   وقع في المسار السيادي كما وقع، ولا تنشر أحداثًا (الناشر القديم ينشر عن
 *   المسار الحيّ سلفًا — حدثان لواقعةٍ واحدةٍ تلفيق).
 */

namespace App\Services\Unit;

require_once __DIR__ . '/CapacityGuard.php';
require_once __DIR__ . '/DocumentGuard.php';
require_once __DIR__ . '/../../Core/EventPublisher.php';
require_once __DIR__ . '/../Contract/AttributionService.php';

use App\Core\EventPublisher;
use App\Services\Contract\AttributionService;

class TimesheetEntryService
{
    /** مراحل الأطراف الموازية (§4.2 المرحلة ③) — تُطلب منها الحاضرةُ مراجعُها فقط. */
    const PARTY_STAGES = array('supplier', 'operator', 'supervisor');

    /** خريطة مستويات الاعتماد القديمة → مراحل السجل القانوني (للمرآة حصرًا).
     *  الدلالة من hours_approval_handler: 1=مدير المشاريع · 2=الموردين ·
     *  3=الأسطول · 4=المشغلين — لا مرحلةَ مبيعاتٍ في المسار القديم، فلا تُلفَّق. */
    const LEGACY_LEVEL_STAGE = array(1 => 'site', 2 => 'supplier', 3 => 'fleet', 4 => 'operator');

    /** ورديات الشاشات القديمة → ENUM الوردية القانوني. */
    const SHIFT_MAP = array('D' => 'day', 'N' => 'night', 'ص' => 'day', 'م' => 'night',
                            'day' => 'day', 'night' => 'night');

    // ═══════════════════════════════════════════════════════════════════════
    // §8.3 — الإدخال: POST /units
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * تسجيل وحدة يومٍ واحدة. المدخلات الستة إلزاميةٌ والباقي اشتقاق.
     *
     * $input = array(
     *   'company_id'   => int,                 // صريحٌ — الخدمة تعمل من CLI بلا جلسة
     *   'equipment_id' => int,
     *   'shift'        => 'day'|'night'|'D'|'N'|'ص'|'م',
     *   'date'         => 'Y-m-d',
     *   'qty'          => float,               // كمية وحدة العقد — لا زمن العمل
     *   'unit_type'    => hour|ton|meter|cbm|day|shift|trip,
     *   'time_lines'   => array of {hours, ops_state, resp_party, cause_note?, ref?},
     *   'source_ref'   => string,              // المرجع الميداني
     *   // اختياري:
     *   'state'        => 'draft'|'submitted' (افتراضه submitted),
     *   'record_basis' => 'contract'|'analytical',
     *   'operation_id' => int,   // يوفَّر من المرآة؛ غيابُه = اشتقاقٌ من المعدة
     *   'sync_uuid'    => string, 'note' => string, 'shift_raw' => string,
     *   'publish_events' => bool (افتراضه true — المرآة تمرّر false),
     * )
     *
     * @return array {ok, code, missing?, entry?, existing?, warnings}
     *   code: 201 وليد · 200 مفتاحٌ قائمٌ أُرجع مرجعُه · 422 نقصُ إلزامي
     */
    public static function submit(\mysqli $conn, $gate, array $input, $actor)
    {
        // ── التحقق: نقصُ إلزاميٍّ → 422 بقائمته (§8.3) ──
        $missing = array();
        $companyId = isset($input['company_id']) ? (int) $input['company_id'] : 0;
        $equipmentId = isset($input['equipment_id']) ? (int) $input['equipment_id'] : 0;
        $date = isset($input['date']) ? trim((string) $input['date']) : '';
        $qty = isset($input['qty']) ? (float) $input['qty'] : null;
        $unitType = isset($input['unit_type']) ? trim((string) $input['unit_type']) : '';
        $shiftRaw = isset($input['shift']) ? trim((string) $input['shift']) : '';
        $shift = isset(self::SHIFT_MAP[$shiftRaw]) ? self::SHIFT_MAP[$shiftRaw] : null;
        $timeLines = (isset($input['time_lines']) && is_array($input['time_lines'])) ? $input['time_lines'] : array();
        $sourceRef = isset($input['source_ref']) ? trim((string) $input['source_ref']) : '';

        if ($companyId <= 0)   { $missing[] = 'company_id'; }
        if ($equipmentId <= 0) { $missing[] = 'equipment_id'; }
        if ($shift === null)   { $missing[] = 'shift'; }
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $missing[] = 'date'; }
        if ($qty === null || $qty < 0) { $missing[] = 'qty'; }
        $validUnits = array('hour','ton','meter','cbm','day','shift','trip');
        if (!in_array($unitType, $validUnits, true)) { $missing[] = 'unit_type'; }
        if (empty($timeLines)) { $missing[] = 'time_lines'; }
        if ($sourceRef === '') { $missing[] = 'source_ref'; }
        if (!empty($missing)) {
            return array('ok' => false, 'code' => 422, 'missing' => $missing, 'warnings' => array());
        }

        // ── العطالة الخدمية: (المعدة × التاريخ × الوردية) → 200 بالمرجع (§8.2/§8.3) ──
        $existing = self::findByDayKey($conn, $companyId, $equipmentId, $date, $shift);
        if ($existing !== null) {
            return array('ok' => true, 'code' => 200, 'existing' => true,
                         'entry' => self::dto($conn, $companyId, (int) $existing['id']),
                         'warnings' => array());
        }
        // والعطالة البنيوية: sync_uuid قائم → نفس الرد (مزامنةٌ مكررة دون اتصال)
        $syncUuid = isset($input['sync_uuid']) ? trim((string) $input['sync_uuid']) : '';
        if ($syncUuid !== '') {
            $st = $conn->prepare("SELECT id FROM unit_entries WHERE company_id=? AND sync_uuid=?");
            $st->bind_param('is', $companyId, $syncUuid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                return array('ok' => true, 'code' => 200, 'existing' => true,
                             'entry' => self::dto($conn, $companyId, (int) $row['id']),
                             'warnings' => array());
            }
        }

        // ── الاشتقاق (§5.1): المعدة تجلب مشروعَها وعقدَها وموردَها ومشغّلَها ──
        $opId = isset($input['operation_id']) ? (int) $input['operation_id'] : 0;
        $drv = self::deriveFromEquipment($conn, $companyId, $equipmentId, $opId);
        // المشغّل: يُمرَّر صراحةً (المرآة تمرّر عمود الدوام). اشتقاقُه الآلي ينتظر
        // دورةَ التوزيع (UX-03 §2.2) غيرَ المبنية — غيابُه موثَّقٌ لا ملفَّق.
        if (!empty($input['operator_employee_id'])) {
            $drv['operator_employee_id'] = (int) $input['operator_employee_id'];
        }

        // ── H-01 ③: حاوياتُ الموقع شرطُ التسجيل (خلف `EMS_CONTAINER_GATE`) ──
        // «لا تُسجَّل وحدةٌ في موقعٍ لم تكتمل حاوياتُه». والعلَمُ **قائمةُ مواقع**
        // لا مفتاحٌ ثنائيّ — فلا قلبةَ واحدةٌ على الجميع (فخُّ E-08: 138 من 138).
        // والرسالةُ تحمل **روابطَ الإصلاح**: رسالةٌ بلا رابطٍ تُوقف الميدانَ بلا مخرج.
        require_once __DIR__ . '/../Operations/ContainerGate.php';
        $cgate = \App\Services\Operations\ContainerGate::assertReady($gate, array(
            'project_id'           => $drv['project_id'],
            'contract_id'          => $drv['contract_id'],
            'equipment_id'         => $equipmentId,
            'operator_employee_id' => isset($drv['operator_employee_id']) ? $drv['operator_employee_id'] : 0,
            'unit_type'            => $unitType,
            'entry_date'           => $date,
        ));
        if (!$cgate['ok']) {
            return array('ok' => false, 'code' => 422,
                         'reasons' => \App\Services\Operations\ContainerGate::flatten($cgate['reasons']),
                         'blocked' => $cgate['reasons'],   // بروابطها — لتعرضها الشاشة
                         'warnings' => array());
        }

        $state = (isset($input['state']) && $input['state'] === 'draft') ? 'draft' : 'submitted';
        $basis = (isset($input['record_basis']) && $input['record_basis'] === 'analytical')
               ? 'analytical' : 'contract';

        $entryId = 0;
        $gate->runInTransaction(function ($g) use (&$entryId, $conn, $companyId, $equipmentId,
            $date, $shift, $qty, $unitType, $basis, $state, $sourceRef, $syncUuid, $drv,
            $input, $actor, $timeLines) {

            $row = array(
                // عنصرٌ نائبٌ فريدٌ لا يتصادم تحت التزامن — يُستبدل بالهوية النهائية فورًا
                'entry_no'             => 'TMP-' . uniqid('', true),
                'entry_date'           => $date,
                'project_id'           => $drv['project_id'],
                'contract_id'          => $drv['contract_id'],
                'equipment_id'         => $equipmentId,
                'operator_employee_id' => $drv['operator_employee_id'],
                'supplier_entity_id'   => $drv['supplier_entity_id'],
                'unit_type'            => $unitType,
                'qty'                  => round((float) $qty, 2),
                'record_basis'         => $basis,
                'shift'                => $shift,
                'source_ref'           => $sourceRef,
                'note'                 => isset($input['note']) ? mb_substr(trim((string) $input['note']), 0, 200) : null,
                'state'                => $state,
                'entered_by'           => (int) $actor ?: null,
            );
            if ($syncUuid !== '') { $row['sync_uuid'] = $syncUuid; }
            $entryId = (int) $g->insert('unit_entries', $row);
            if ($entryId <= 0) { throw new \RuntimeException('TimesheetEntryService: فشل إدراج الواقعة'); }

            // الهوية الثابتة entry_no — خادميّةٌ من المعرّف الوليد (فريدةٌ بلا سباق)
            $g->update('unit_entries',
                array('entry_no' => 'UNT-' . str_pad((string) $entryId, 6, '0', STR_PAD_LEFT)),
                array('id' => $entryId));

            // سطور الزمن (§3.3): سطرٌ لكل فترةٍ بحالتها ومسؤولها — زمنٌ لا فوترة
            foreach ($timeLines as $line) {
                $hours = isset($line['hours']) ? round((float) $line['hours'], 2) : 0.0;
                if ($hours <= 0) { continue; }
                $g->insert('unit_time_log', array(
                    'log_date'             => $date,
                    'shift'                => $shift,
                    'project_id'           => $drv['project_id'],
                    'equipment_id'         => $equipmentId,
                    'operator_employee_id' => $drv['operator_employee_id'],
                    'supplier_entity_id'   => $drv['supplier_entity_id'],
                    'hours'                => $hours,
                    'ops_state'            => self::safeOpsState(isset($line['ops_state']) ? $line['ops_state'] : ''),
                    'cause_note'           => isset($line['cause_note']) ? mb_substr(trim((string) $line['cause_note']), 0, 200) : null,
                    'resp_party'           => self::safeRespParty(isset($line['resp_party']) ? $line['resp_party'] : ''),
                    'entry_id'             => $entryId,
                    'entered_by'           => (int) $actor ?: null,
                ));
            }
        }, 'unit-entry-submit');

        // ── حارس الطاقة: يقيس ويعلّم ولا يمنع (§3.10) — خارج معاملة الإدراج ──
        $entryRow = self::rawEntry($conn, $companyId, $entryId);
        $warnings = array();
        if ($entryRow) {
            $breaches = CapacityGuard::evaluate($conn, $gate, $entryRow);
            foreach ($breaches as $b) {
                $warnings[] = array('type' => 'capacity',
                    'subject' => $b['subject'], 'measured' => $b['measured'], 'capacity' => $b['capacity']);
            }
        }

        // ── الحدث UnitSubmitted (§8.3) — المرآة تعطّله لئلا تُمثَّل الواقعةُ مرتين ──
        if (!isset($input['publish_events']) || $input['publish_events']) {
            self::publishUnitEvent($conn, $companyId, $entryId, 'operations.unit.submitted',
                $actor, array('qty' => $qty, 'unit' => $unitType, 'warnings' => count($warnings)));
        }

        return array('ok' => true, 'code' => 201, 'existing' => false,
                     'entry' => self::dto($conn, $companyId, $entryId), 'warnings' => $warnings);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // §8.2 — الاعتماد: POST /units/(id)/approve?stage=…
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * قرارُ مرحلةٍ في السلسلة. المراحل: site · supplier · operator · supervisor ·
     * fleet · sales. (finance في الـENUM لمحرّك التحويل — ليست من مسار الإدخال.)
     *
     * @param array $opts اختياري: note · enforce_capacity (افتراضه true —
     *                    المرآة تمرّر false لأنها مسجِّلُ تاريخٍ لا مُنفِذ)
     * @return array {ok, code, state?, reasons?}
     */
    public static function approve(\mysqli $conn, $gate, $companyId, $entryId, $stage, $actor, array $opts = array())
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $stage = trim((string) $stage);
        $entry = self::rawEntry($conn, $companyId, $entryId);
        if (!$entry) { return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة')); }

        $state = $entry['state'];
        $round = (int) $entry['current_round'];
        $enforce = !isset($opts['enforce_capacity']) || $opts['enforce_capacity'];

        // العطالة أولًا: قرارٌ مسجَّلٌ للمرحلة في الجولة الجارية يُرجَع كما هو —
        // قبل فحص الحالة، فالمزامنة المكررة بعد تقدّم السلسلة ليست خرقًا للآلة
        if (self::stageDecided($conn, $companyId, $entryId, $round, $stage)) {
            return array('ok' => true, 'code' => 200, 'state' => $state, 'existing' => true);
        }

        // ── شروط الآلة (§8.2): كل انتقالٍ من حالته المشروعة وحدها ──
        if ($stage === 'site') {
            if ($state !== 'submitted') {
                return array('ok' => false, 'code' => 409, 'reasons' => array("اعتماد الموقع يتطلب حالة submitted — الحالية: {$state}"));
            }
            if ($enforce) {
                $guard = CapacityGuard::assertSiteApprovable($conn, $companyId, $entryId);
                if (!$guard['ok']) {
                    return array('ok' => false, 'code' => 422, 'reasons' => $guard['reasons']);
                }
                // ── حارسُ الإسناد الإلزامي (CON-02 §5 · ق-5) ────────────────────
                // اعتمادُ الموقع هو نقطةُ الحجب: الإدخالُ يُحفظ مسودةً، ولا يُعتمد
                // موقعيًّا وفيه زمنُ توقفٍ بلا مسؤول. وهي نفسُها نقطةُ ق-4 —
                // «الكاتبُ يقترح والمشرفُ يعتمد» — فاعتمادُ المشرف **هو** الحجب.
                //
                // ⚠️ محكومٌ بعَلَم `EMS_ATTRIBUTION_MATRIX` (ق-24): لا يُفعَّل قبل
                //    أن تُملأ مصفوفاتُ العقود وتُجيزها المالية، وإلا تجمّدت العقودُ
                //    التسعةُ كلُّها بـ423. «الشاشةُ ← الملءُ ← تفعيلُ القارئ».
                if (AttributionService::enforced()) {
                    $att = AttributionService::assertAttributable($conn, $gate, $companyId, $entryId);
                    if (!$att['ok']) {
                        return array('ok' => false, 'code' => $att['code'], 'reasons' => $att['reasons']);
                    }
                }
                // ── حارسُ الوثائق المنتهية (UX-10 §8.2 · UX-03 §8.3) ────────────
                // معدةٌ أو مشغّلٌ بوثيقةِ أهليةٍ منتهيةٍ **يومَ العمل** لا يُعتمد
                // موقعيًّا — فاليومُ يُدوَّن كما وقع، ولا يصير مالًا وهو مطعونٌ فيه.
                // خلف `EMS_DOC_EXPIRY_GUARD` (monitor عند التسليم: 138/138 تُحجب).
                $doc = DocumentGuard::assertDocumentsValid($conn, $companyId, $entryId);
                if (!$doc['ok']) {
                    return array('ok' => false, 'code' => $doc['code'], 'reasons' => $doc['reasons']);
                }
            }
        } elseif (in_array($stage, self::PARTY_STAGES, true) || $stage === 'fleet') {
            if (!in_array($state, array('site_approved', 'parties_review'), true)) {
                return array('ok' => false, 'code' => 409, 'reasons' => array("بطاقة الطرف تتطلب site_approved/parties_review — الحالية: {$state}"));
            }
        } elseif ($stage === 'sales') {
            if ($state !== 'parties_approved') {
                return array('ok' => false, 'code' => 409, 'reasons' => array("اعتماد المبيعات يتطلب parties_approved — الحالية: {$state}"));
            }
        } else {
            return array('ok' => false, 'code' => 422, 'reasons' => array("مرحلةٌ غير معروفة: {$stage}"));
        }

        $note = isset($opts['note']) ? mb_substr(trim((string) $opts['note']), 0, 200) : null;
        $newState = $state;

        $gate->runInTransaction(function ($g) use ($conn, $companyId, $entryId, $round, $stage,
            $actor, $note, $entry, $state, &$newState) {

            $g->insert('unit_approvals', array(
                'entry_id' => $entryId, 'round_no' => $round, 'stage' => $stage,
                'decision' => 'approved', 'actor_id' => (int) $actor, 'note' => $note,
            ));

            if ($stage === 'site') {
                $newState = 'site_approved';
            } elseif ($stage === 'sales') {
                $newState = 'sales_approved'; // اكتمالُ السلسلة (§8.2) — التحويل لخطوة ④
            } else {
                // مرحلة الأطراف الموازية: المطلوبون هم الحاضرون مراجعُهم على الواقعة
                $required = self::requiredParties($entry);
                $decided = self::decidedParties($conn, $companyId, $entryId, $round);
                $decided[$stage] = true;
                $allDone = true;
                foreach ($required as $p) { if (empty($decided[$p])) { $allDone = false; break; } }
                $newState = $allDone ? 'parties_approved' : 'parties_review';
            }
            $g->update('unit_entries', array('state' => $newState), array('id' => $entryId));
        }, 'unit-entry-approve');

        // ── H-01 ③: **الاستهلاكُ عند اعتماد الموقع لا عند الإدخال** ──────────
        // الواقعةُ أُقرّت فحينئذٍ تُخصم من سلسلة حاوياتها. ومفتاحُ العطالة يحمل
        // **الجولة** (`entry:{id}:r{round}`)، فالاعتمادُ المكرَّرُ في الجولة نفسِها
        // لا يخصم مرتين، والجولةُ الجديدةُ بعد إعادةٍ خصمٌ جديدٌ بمفتاحٍ جديد.
        // ويقع **بعد** نجاح المعاملة: لا يُخصم من حاويةٍ لواقعةٍ لم تُعتمد.
        if ($stage === 'site') {
            try {
                require_once __DIR__ . '/../Operations/ContainerGate.php';
                $fresh = self::rawEntry($conn, $companyId, $entryId);
                if ($fresh) {
                    \App\Services\Operations\ContainerGate::consumeForEntry($conn, $gate, $companyId, $fresh);
                }
            } catch (\Throwable $t) {
                // الخصمُ أثرٌ تابعٌ لا شرطُ صحةٍ للاعتماد — يُسجَّل ولا يُسقطه
                error_log('container consume on site approve #' . $entryId . ': ' . $t->getMessage());
            }
        }

        if (!isset($opts['publish_events']) || $opts['publish_events']) {
            $key = ($newState === 'sales_approved')
                 ? 'operations.unit.chain_completed' : 'operations.unit.stage_approved';
            self::publishUnitEvent($conn, $companyId, $entryId, $key, $actor,
                array('stage' => $stage, 'round' => $round, 'state' => $newState));
        }

        return array('ok' => true, 'code' => 200, 'state' => $newState);
    }

    /**
     * الإعادة للموقع — «بسببٍ وبالرقم نفسه» (§8.2): الحالة returned، الرقمُ
     * entry_no ثابت، والجولةُ تُفتح (current_round+1) فتعتمد المراحلُ من جديد.
     *
     * @return array {ok, code, round?}
     */
    public static function returnToSite(\mysqli $conn, $gate, $companyId, $entryId, $stage, $reason, $actor, array $opts = array())
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $reason = trim((string) $reason);
        if ($reason === '') {
            return array('ok' => false, 'code' => 422, 'reasons' => array('الإعادة بلا سببٍ لا تقع (§8.2)'));
        }
        $entry = self::rawEntry($conn, $companyId, $entryId);
        if (!$entry) { return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة')); }
        if (in_array($entry['state'], array('draft', 'returned'), true)) {
            return array('ok' => false, 'code' => 409, 'reasons' => array("لا إعادةَ من حالة {$entry['state']}"));
        }

        $round = (int) $entry['current_round'];
        $nextRound = $round + 1;
        $gate->runInTransaction(function ($g) use ($entryId, $round, $nextRound, $stage, $reason, $actor) {
            $g->insert('unit_approvals', array(
                'entry_id' => $entryId, 'round_no' => $round,
                'stage' => $stage, 'decision' => 'returned',
                'actor_id' => (int) $actor, 'note' => mb_substr($reason, 0, 200),
            ));
            $g->update('unit_entries',
                array('state' => 'returned', 'current_round' => $nextRound),
                array('id' => $entryId));
        }, 'unit-entry-return');

        // ── H-01 ③: الإعادةُ تردّ الاستهلاكَ **بحركةٍ عاكسةٍ لا بحذف** (القاعدة ①)
        // المفتاحُ مشتقٌّ من مفتاح الخصم بلاحقة `:rev` فلا يُردُّ مرتين، والسجلُّ
        // يبقى صفَّين (خصمٌ وردّ) — أثرُ التدقيق كاملٌ لا ممحوّ.
        try {
            require_once __DIR__ . '/../Operations/ContainerGate.php';
            \App\Services\Operations\ContainerGate::reverseForEntry(
                $conn, $gate, $companyId, $entry, $round);
        } catch (\Throwable $t) {
            error_log('container reverse on return #' . $entryId . ': ' . $t->getMessage());
        }

        if (!isset($opts['publish_events']) || $opts['publish_events']) {
            self::publishUnitEvent($conn, $companyId, $entryId, 'operations.unit.returned',
                $actor, array('stage' => $stage, 'reason' => $reason, 'round' => $nextRound));
        }
        return array('ok' => true, 'code' => 200, 'round' => $nextRound);
    }

    /**
     * إعادة الإرسال بعد الاستكمال — من returned إلى submitted بالرقم نفسه.
     * تعديلُ الكمية/الزمن قبل الإرسال شأنُ الشاشة؛ الخدمة تنقل الحالة وتقيس.
     */
    public static function resubmit(\mysqli $conn, $gate, $companyId, $entryId, $actor, array $opts = array())
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $entry = self::rawEntry($conn, $companyId, $entryId);
        if (!$entry) { return array('ok' => false, 'code' => 404, 'reasons' => array('الواقعة غير موجودة')); }
        if ($entry['state'] !== 'returned') {
            return array('ok' => false, 'code' => 409, 'reasons' => array("إعادة الإرسال من returned وحدها — الحالية: {$entry['state']}"));
        }
        $gate->update('unit_entries', array('state' => 'submitted'), array('id' => $entryId));

        // يُعاد القياس — فالاستكمال قد غيّر الزمن
        $fresh = self::rawEntry($conn, $companyId, $entryId);
        $warnings = array();
        foreach (CapacityGuard::evaluate($conn, $gate, $fresh) as $b) {
            $warnings[] = array('type' => 'capacity', 'subject' => $b['subject'],
                'measured' => $b['measured'], 'capacity' => $b['capacity']);
        }
        if (!isset($opts['publish_events']) || $opts['publish_events']) {
            self::publishUnitEvent($conn, $companyId, $entryId, 'operations.unit.submitted',
                $actor, array('resubmitted' => true, 'round' => (int) $fresh['current_round']));
        }
        return array('ok' => true, 'code' => 200, 'warnings' => $warnings);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // المرآة — طورُ الكتابة المزدوجة (خطوة ②): timesheet → السجل القانوني
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * فيرآةُ صفِّ دوامٍ حيٍّ إلى السجل القانوني. عطالةٌ كاملة: الصفُّ الممرآةُ
     * سلفًا يُرجَع مرجعُه (بالمفتاح الخدمي أو بـsync_uuid = 'ts:{id}').
     *
     * قراءة timesheet قراءةٌ محضة. الفصل الثلاثي (§5.1) يقع هنا بنيويًّا:
     *   زمنُ العمل → unit_time_log بحالاته العشر · وحدةُ العقد → qty/unit_type
     *   (الأمتار/الأطنان المسجَّلة أولى من الساعات لأنها وحدةُ الفوترة الفعلية) ·
     *   الإنتاجُ التحليلي → لا يُمرآة (لا مصدرَ له في الصف القديم — يوثَّق غيابُه).
     *
     * @return array {ok, code, entry_id?, skipped?}
     */
    /**
     * @param array|null $postedLines سطورُ زمنٍ حقيقيةٌ جمعتها الشاشة (UX-03 §5.1:
     *   «توزيعُ زمن الوردية بحالاته ومسؤوليه») — إن وُجدت استُعملت **بدل** ترجمة
     *   الأعمدة التسعة، فيدخل السجلَّ القانوني السببُ الدقيق (fuel_logistics_stop
     *   مثلًا) بمرجعه الميداني، بينما لا ترى الأعمدةُ القديمة إلا أقربَ خانة.
     */
    public static function mirrorFromTimesheet(\mysqli $conn, $gate, $tsId, $actor, $postedLines = null)
    {
        $tsId = (int) $tsId;
        $st = $conn->prepare(
            "SELECT t.*, o.id AS op_id, o.project_id, o.contract_id,
                    o.equipment AS eq_id, e.suppliers AS supplier_id
               FROM timesheet t
               LEFT JOIN operations o ON o.id = t.operator
               LEFT JOIN equipments e ON e.id = o.equipment
              WHERE t.id = ? LIMIT 1");
        $st->bind_param('i', $tsId);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$t) { return array('ok' => false, 'code' => 404, 'skipped' => 'صف الدوام غير موجود'); }
        if (empty($t['eq_id'])) {
            return array('ok' => false, 'code' => 422, 'skipped' => 'لا معدةَ قابلةً للاشتقاق (operations)');
        }

        // وحدة العقد: المسجَّلُ غيرُ الساعيّ أولى — فهو وحدةُ الفوترة الفعلية
        $meters = (float) $t['meters_count'];
        $tons   = (float) $t['tons_count'];
        $hours  = (float) $t['executed_hours'];
        if ($meters > 0)     { $unitType = 'meter'; $qty = $meters; }
        elseif ($tons > 0)   { $unitType = 'ton';   $qty = $tons; }
        else                 { $unitType = 'hour';  $qty = $hours; }

        $input = array(
            'company_id'   => (int) $t['company_id'],
            'equipment_id' => (int) $t['eq_id'],
            'shift'        => (string) $t['shift'],
            'date'         => (string) $t['date'],
            'qty'          => $qty,
            'unit_type'    => $unitType,
            'time_lines'   => (is_array($postedLines) && !empty($postedLines))
                                ? $postedLines
                                : self::timeLinesFromTimesheet($t),
            'source_ref'   => 'TS-' . $tsId,
            'operation_id' => (int) $t['op_id'],
            'operator_employee_id' => (int) $t['employee_id'] ?: null,
            'sync_uuid'    => 'ts:' . $tsId,     // عطالةُ المرآة البنيوية
            'note'         => 'مرآةُ الكتابة المزدوجة عن سجل الدوام الحي',
            'publish_events' => false,           // الناشر القديم يمثّل الواقعة سلفًا
        );
        $res = self::submit($conn, $gate, $input, $actor);
        if (!$res['ok']) { return array('ok' => false, 'code' => $res['code'], 'skipped' => implode('·', isset($res['missing']) ? $res['missing'] : array())); }

        // ── فجوةٌ أُصلحت (2026-07-27): المرآةُ كانت تقف عند 200 فلا تنتقل
        //    تعديلاتُ الصف الحي إليها أبدًا. الآن الواقعةُ القائمة تُنعَش —
        //    وإن كانت مقفولةً (اعتُمد موقعُها) بقيت كما هي: مسجِّلُ تاريخٍ
        //    لا يعدّل معتمَدًا، والرفضُ يُسجَّل لا يُفشِل.
        if (!empty($res['existing'])) {
            $rf = self::refreshEntry($conn, $gate, (int) $t['company_id'], (int) $res['entry']['id'], array(
                'qty' => $qty, 'unit_type' => $unitType,
                'operator_employee_id' => (int) $t['employee_id'] ?: null,
                'time_lines' => $input['time_lines'],
            ), $actor);
            if (!$rf['ok'] && $rf['code'] !== 423) {
                error_log('mirror refresh ts#' . $tsId . ': ' . (isset($rf['skipped']) ? $rf['skipped'] : $rf['code']));
            }
        }
        return array('ok' => true, 'code' => $res['code'],
                     'entry_id' => (int) $res['entry']['id'], 'existing' => !empty($res['existing']));
    }

    /**
     * فيرآةُ سلسلة اعتماد الصف القديم إلى مراحلها القانونية — إعادةُ تشغيلٍ
     * كاملةٌ بترتيب المستويات (العطالة تجعل التكرار آمنًا): فصفٌّ اعتُمدت
     * مستوياتُه الأولى قبل تفعيل العلم تُستكمل مرآتُه عند أول اعتمادٍ بعده.
     *
     * المرآةُ مسجِّلُ تاريخٍ لا مُنفِذُ قواعد: لا تفرض حارسَ الطاقة على اعتمادٍ
     * وقع فعلًا في المسار السيادي — العلمُ يبقى مرفوعًا على الواقعة ويُقرأ.
     */
    public static function mirrorApproval(\mysqli $conn, $gate, $tsId, $level, $actor)
    {
        $tsId = (int) $tsId;
        // الواقعة الممرآة لهذا الصف
        $st = $conn->prepare("SELECT id, company_id FROM unit_entries WHERE sync_uuid = ? LIMIT 1");
        $uuid = 'ts:' . $tsId;
        $st->bind_param('s', $uuid);
        $st->execute();
        $e = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$e) { return array('ok' => false, 'code' => 404, 'skipped' => 'لا مرآةَ لهذا الصف بعد'); }

        // كل المستويات المعتمدة في المسار الحي، بترتيبها — لا المستوى الوارد وحده
        $st = $conn->prepare(
            "SELECT approval_level, approved_by FROM timesheet_approvals
              WHERE timesheet_id=? AND status=1 ORDER BY approval_level");
        $st->bind_param('i', $tsId);
        $st->execute();
        $levels = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $last = array('ok' => true, 'code' => 200);
        foreach ($levels as $lv) {
            $l = (int) $lv['approval_level'];
            if (!isset(self::LEGACY_LEVEL_STAGE[$l])) { continue; }
            $who = (int) $lv['approved_by'] ?: (int) $actor;
            $last = self::approve($conn, $gate, (int) $e['company_id'], (int) $e['id'],
                self::LEGACY_LEVEL_STAGE[$l], $who,
                array('enforce_capacity' => false, 'publish_events' => false,
                      'note' => 'مرآةُ اعتماد المسار الحي L' . $l));
        }
        return $last;
    }

    /** علم الكتابة المزدوجة — fail-closed: غيابُه = لا مرآة. */
    public static function dualWriteOn()
    {
        return function_exists('ems_env') && ems_env('EMS_UNIT_DUAL_WRITE', 'off') === 'on';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // أدوات داخلية
    // ═══════════════════════════════════════════════════════════════════════

    /** أعمدة الدوام القديم → سطور الزمن العشر (§3.3). الترجمة نفسُها المعتمدة
     *  في EffectFanout::resolveTimesheet — مصدرٌ واحدٌ للدلالة. */
    /**
     * العكسُ الحاكم: سطورُ الزمن → الأعمدةُ القديمة (UX-03 §5.1 خلف EMS_TS_TIME_LINES).
     *
     * حين تجمع الشاشةُ السطورَ مباشرةً، **الخادمُ وحده** يشتق منها الأعمدةَ التسعة
     * — لا يُوثَق بحسابِ جافاسكربت. الخريطةُ عكسُ timeLinesFromTimesheet حرفيًّا،
     * وما لا خانةَ له في القديم (fuel_logistics_stop) يهبط إلى «أعطال أخرى»
     * بينما يحفظ السجلُّ القانوني حالتَه الدقيقة — هذا فرقُ القيمة كلُّه.
     *
     * @return array أعمدةُ timesheet المشتقة + 'balance_ok' + 'sum'
     */
    public static function legacyColumnsFromLines(array $lines)
    {
        $col = array(
            'executed_hours' => 0.0, 'standby_hours' => 0.0, 'dependence_hours' => 0.0,
            'maintenance_fault' => 0.0, 'hr_fault' => 0.0, 'marketing_fault' => 0.0,
            'approval_fault' => 0.0, 'ts_supplier_stop_hours' => 0.0,
            'ts_planned_stop_hours' => 0.0, 'ts_force_majeure_hours' => 0.0,
            'other_fault_hours' => 0.0,
        );
        $sum = 0.0;
        foreach ($lines as $l) {
            $h = isset($l['hours']) ? (float) $l['hours'] : 0.0;
            if ($h <= 0) { continue; }
            $sum += $h;
            $state = isset($l['ops_state']) ? (string) $l['ops_state'] : '';
            $resp  = isset($l['resp_party']) ? (string) $l['resp_party'] : 'none';
            switch ($state) {
                case 'actual_work':    $col['executed_hours'] += $h; break;
                case 'standby':
                    if ($resp === 'company') { $col['dependence_hours'] += $h; }
                    else                     { $col['standby_hours'] += $h; }
                    break;
                case 'tech_breakdown': $col['maintenance_fault'] += $h; break;
                case 'operator_stop':  $col['hr_fault'] += $h; break;
                case 'client_stop':    $col['marketing_fault'] += $h; break;
                case 'supplier_stop':  $col['ts_supplier_stop_hours'] += $h; break;
                case 'planned_stop':   $col['ts_planned_stop_hours'] += $h; break;
                case 'force_majeure':  $col['ts_force_majeure_hours'] += $h; break;
                case 'fuel_logistics_stop': // لا خانةَ قديمةً لها — أقربُ صادق
                default:               $col['other_fault_hours'] += $h; break;
            }
        }
        foreach ($col as $k => $v) { $col[$k] = round($v, 2); }
        $faults = round($col['maintenance_fault'] + $col['hr_fault'] + $col['marketing_fault']
                + $col['approval_fault'] + $col['ts_supplier_stop_hours'] + $col['ts_planned_stop_hours']
                + $col['ts_force_majeure_hours'] + $col['other_fault_hours'], 2);
        $col['total_fault_hours'] = $faults;
        $col['total_work_hours']  = round($col['executed_hours'] + $col['standby_hours'], 2);
        $col['shift_hours']       = round($sum, 2);
        return $col;
    }

    /** علم إدخال سطور الزمن من الشاشة — fail-closed كأخويه. */
    public static function timeLinesUiOn()
    {
        return function_exists('ems_env') && ems_env('EMS_TS_TIME_LINES', 'off') === 'on';
    }

    /**
     * علمُ قلب الكاتب (UX-03 §8.1: «مصدرُ الكتابة الوحيد عبر خدمة الإدخال»):
     *   legacy (الافتراض) = timesheet يُكتب أولًا والخدمةُ مرآة.
     *   service           = الخدمةُ تكتب أولًا وtimesheet نسخةٌ مشتقة.
     * الرجوعُ قلبةُ علَمٍ بلا نشر كود.
     */
    public static function writerServiceOn()
    {
        return function_exists('ems_env') && ems_env('EMS_TS_WRITER', 'legacy') === 'service';
    }

    /** الحالاتُ التي يجوز فيها تحديثُ الواقعة — قبل قفل النسخة (§8.2). */
    const REFRESHABLE_STATES = array('draft', 'submitted', 'returned');

    /**
     * تحديثُ واقعةٍ قائمةٍ قبل قفل النسخة (§8.2: اعتمادُ الموقع = قفل).
     *
     * يحدّث الكميةَ ووحدتَها والمشغّلَ ويستبدل سطورَ الزمن كاملةً، ثم يعيد
     * تقييمَ حارس الطاقة على الواقع الجديد (وقد يرفع العلمَ أو— إن زال
     * التجاوزُ — يتركه للتخليص البشري: العلمُ لا يُخفَض آليًّا، قرارُ D02).
     *
     * @return array {ok, code, warnings?}  423 = مقفولة (اعتُمد موقعُها)
     */
    public static function refreshEntry(\mysqli $conn, $gate, $companyId, $entryId, array $input, $actor)
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $e = self::rawEntry($conn, $companyId, $entryId);
        if (!$e) { return array('ok' => false, 'code' => 404, 'skipped' => 'واقعةٌ غير موجودة'); }
        if (!in_array($e['state'], self::REFRESHABLE_STATES, true)) {
            return array('ok' => false, 'code' => 423,
                'skipped' => 'قفلُ نسخة: اعتُمد موقعُها (' . $e['state'] . ') — التعديلُ بالإعادة الرسمية بسببٍ وبالرقم نفسه');
        }

        $timeLines = (isset($input['time_lines']) && is_array($input['time_lines'])) ? $input['time_lines'] : array();
        $gate->runInTransaction(function ($g) use ($conn, $companyId, $entryId, $e, $input, $timeLines, $actor) {
            $upd = array();
            if (isset($input['qty']))       { $upd['qty'] = round((float) $input['qty'], 2); }
            if (isset($input['unit_type'])) { $upd['unit_type'] = (string) $input['unit_type']; }
            if (array_key_exists('operator_employee_id', $input)) {
                $upd['operator_employee_id'] = $input['operator_employee_id'] ? (int) $input['operator_employee_id'] : null;
            }
            if (!empty($upd)) { $g->update('unit_entries', $upd, array('id' => $entryId)); }

            if (!empty($timeLines)) {
                // استبدالُ السطور كاملًا — الواقعةُ الجديدة تحلّ محلّ القديمة نصًّا
                $del = $conn->prepare("DELETE FROM unit_time_log WHERE company_id=? AND entry_id=?");
                $del->bind_param('ii', $companyId, $entryId);
                $del->execute();
                $del->close();
                $opEmp = array_key_exists('operator_employee_id', $input)
                    ? ($input['operator_employee_id'] ? (int) $input['operator_employee_id'] : null)
                    : ($e['operator_employee_id'] !== null ? (int) $e['operator_employee_id'] : null);
                foreach ($timeLines as $line) {
                    $hours = isset($line['hours']) ? round((float) $line['hours'], 2) : 0.0;
                    if ($hours <= 0) { continue; }
                    $g->insert('unit_time_log', array(
                        'log_date' => $e['entry_date'], 'shift' => $e['shift'],
                        'project_id' => (int) $e['project_id'], 'equipment_id' => (int) $e['equipment_id'],
                        'operator_employee_id' => $opEmp,
                        'supplier_entity_id' => $e['supplier_entity_id'] !== null ? (int) $e['supplier_entity_id'] : null,
                        'hours' => $hours,
                        'ops_state' => self::safeOpsState(isset($line['ops_state']) ? $line['ops_state'] : ''),
                        'cause_note' => isset($line['cause_note']) ? mb_substr(trim((string) $line['cause_note']), 0, 200) : null,
                        'resp_party' => self::safeRespParty(isset($line['resp_party']) ? $line['resp_party'] : ''),
                        'entry_id' => $entryId, 'entered_by' => (int) $actor ?: null,
                    ));
                }
            }
        }, 'unit-entry-refresh');

        // إعادةُ تقييم الحارس على الواقع الجديد
        $warnings = array();
        $fresh = self::rawEntry($conn, $companyId, $entryId);
        if ($fresh) {
            foreach (CapacityGuard::evaluate($conn, $gate, $fresh) as $b) {
                $warnings[] = array('type' => 'capacity', 'subject' => $b['subject'],
                    'measured' => $b['measured'], 'capacity' => $b['capacity']);
            }
        }
        return array('ok' => true, 'code' => 200, 'warnings' => $warnings);
    }

    private static function timeLinesFromTimesheet(array $t)
    {
        $mk = function ($hours, $state, $resp, $cause) {
            return array('hours' => (float) $hours, 'ops_state' => $state,
                         'resp_party' => $resp, 'cause_note' => $cause);
        };
        $lines = array(
            $mk($t['executed_hours'], 'actual_work', 'none', null),
            $mk($t['standby_hours'], 'standby', 'client', 'استعدادٌ بسبب العميل'),
            $mk((float) $t['dependence_hours'] + (float) $t['approval_fault'], 'standby', 'company', 'تبعية/انتظار اعتماد'),
            $mk($t['maintenance_fault'], 'tech_breakdown', 'company', null),
            $mk($t['hr_fault'], 'operator_stop', 'operator', null),
            $mk($t['marketing_fault'], 'client_stop', 'client', 'توقفٌ سببه العميل (عطل تسويق)'),
            $mk($t['ts_supplier_stop_hours'], 'supplier_stop', 'supplier', null),
            $mk($t['ts_planned_stop_hours'], 'planned_stop', 'planned', null),
            $mk($t['ts_force_majeure_hours'], 'force_majeure', 'force_majeure', null),
            $mk($t['other_fault_hours'], 'unlogged', 'none', 'أعطالٌ أخرى غير مصنّفة'),
        );
        $out = array();
        foreach ($lines as $l) { if ($l['hours'] > 0) { $out[] = $l; } }
        return $out;
    }

    /** الاشتقاق §5.1: صفُّ التشغيل الحاكم للمعدة (الممرَّرُ صراحةً أولى، وإلا الأحدث). */
    private static function deriveFromEquipment(\mysqli $conn, $companyId, $equipmentId, $opId = 0)
    {
        // «اختيارُ المعدة يجلب نوعَها وموردَها ووحدةَ عقد مشروعها» (§5.1) — من صف
        // التشغيل الحاكم. المشغّلُ ليس هنا: مصدرُه دورةُ التوزيع (§2.2) حين تُبنى.
        $sql = "SELECT o.id, o.project_id, o.contract_id, e.suppliers AS supplier_id
                  FROM operations o
                  JOIN equipments e ON e.id = o.equipment
                 WHERE o.company_id = ? AND " . ($opId > 0 ? "o.id = ?" : "o.equipment = ?") . "
                 ORDER BY o.id DESC LIMIT 1";
        $st = $conn->prepare($sql);
        $ref = $opId > 0 ? $opId : $equipmentId;
        $st->bind_param('ii', $companyId, $ref);
        $st->execute();
        $o = $st->get_result()->fetch_assoc();
        $st->close();
        return array(
            'project_id'           => $o && !empty($o['project_id']) ? (int) $o['project_id'] : 0,
            'contract_id'          => $o && !empty($o['contract_id']) ? (int) $o['contract_id'] : null,
            'supplier_entity_id'   => $o && !empty($o['supplier_id']) ? (int) $o['supplier_id'] : null,
            'operator_employee_id' => null, // ينتظر دورة التوزيع §2.2 — أو تمريرًا صريحًا
        );
    }

    /** الأطراف المطلوبون توازيًا (§4.2 ③): «بين المعنيّين فقط» — الحاضرُ مرجعُه معنيّ. */
    /**
     * المطلوبون علنًا — **نافذةٌ للقراءة على القاعدة نفسِها** التي تحكم بها الآلة.
     * فُتحت لشريط رحلة الوحدة (E-09): الشريطُ يعرض «2 من 3» والآلةُ تنتظر
     * الثلاثة، فلو نسخ الشريطُ القاعدةَ لنفسه لافترقا يومَ تتغير إحداهما.
     * قراءةٌ خالصةٌ بلا حالة — لا يغيّر استعمالُها شيئًا.
     */
    public static function requiredPartiesOf(array $entry)
    {
        return self::requiredParties($entry);
    }

    private static function requiredParties(array $entry)
    {
        $req = array();
        if (!empty($entry['supplier_entity_id']))   { $req[] = 'supplier'; }
        if (!empty($entry['operator_employee_id'])) { $req[] = 'operator'; }
        if (!empty($entry['supervisor_id']))        { $req[] = 'supervisor'; }
        // واقعةٌ بلا طرفٍ خارجيٍّ أصلًا: بطاقةُ الأسطول تحلّ محلّها كي لا تعبر بلا حكم
        if (empty($req)) { $req[] = 'fleet'; }
        return $req;
    }

    private static function decidedParties(\mysqli $conn, $companyId, $entryId, $round)
    {
        $st = $conn->prepare(
            "SELECT stage FROM unit_approvals
              WHERE company_id=? AND entry_id=? AND round_no=? AND decision='approved'");
        $st->bind_param('iii', $companyId, $entryId, $round);
        $st->execute();
        $res = $st->get_result();
        $out = array();
        while ($r = $res->fetch_assoc()) { $out[$r['stage']] = true; }
        $st->close();
        return $out;
    }

    private static function stageDecided(\mysqli $conn, $companyId, $entryId, $round, $stage)
    {
        $st = $conn->prepare(
            "SELECT id FROM unit_approvals
              WHERE company_id=? AND entry_id=? AND round_no=? AND stage=? LIMIT 1");
        $st->bind_param('iiis', $companyId, $entryId, $round, $stage);
        $st->execute();
        $found = $st->get_result()->fetch_assoc() !== null;
        $st->close();
        return $found;
    }

    private static function findByDayKey(\mysqli $conn, $companyId, $equipmentId, $date, $shift)
    {
        // المفتاح الخدمي لا يحتسب الملغاة/المرفوضة — يومٌ رُفض صفُّه يُدخَل من جديد
        $st = $conn->prepare(
            "SELECT id FROM unit_entries
              WHERE company_id=? AND equipment_id=? AND entry_date=? AND shift=?
                AND state NOT IN ('rejected','cancelled','superseded','reversed')
              ORDER BY id DESC LIMIT 1");
        $st->bind_param('iiss', $companyId, $equipmentId, $date, $shift);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function rawEntry(\mysqli $conn, $companyId, $entryId)
    {
        $st = $conn->prepare("SELECT * FROM unit_entries WHERE company_id=? AND id=? LIMIT 1");
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /** UnitDTO (§8.3): رأسُ الواقعة بحقول الاشتقاق معبأةً · time_lines · الحالة · التحذيرات. */
    public static function dto(\mysqli $conn, $companyId, $entryId)
    {
        $e = self::rawEntry($conn, $companyId, $entryId);
        if (!$e) { return null; }
        $st = $conn->prepare(
            "SELECT hours, ops_state, resp_party, cause_note
               FROM unit_time_log WHERE company_id=? AND entry_id=? ORDER BY id");
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $lines = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        return array(
            'id' => (int) $e['id'], 'entry_no' => $e['entry_no'],
            'entry_date' => $e['entry_date'], 'shift' => $e['shift'],
            'project_id' => (int) $e['project_id'],
            'contract_id' => $e['contract_id'] !== null ? (int) $e['contract_id'] : null,
            'equipment_id' => $e['equipment_id'] !== null ? (int) $e['equipment_id'] : null,
            'operator_employee_id' => $e['operator_employee_id'] !== null ? (int) $e['operator_employee_id'] : null,
            'supplier_entity_id' => $e['supplier_entity_id'] !== null ? (int) $e['supplier_entity_id'] : null,
            'unit_type' => $e['unit_type'], 'qty' => (float) $e['qty'],
            'record_basis' => $e['record_basis'],
            'state' => $e['state'], 'round' => (int) $e['current_round'],
            'capacity_flag' => (int) $e['capacity_flag'],
            'source_ref' => $e['source_ref'],
            'time_lines' => $lines,
        );
    }

    /** قيم ENUM تُمرَّر آمنةً — القيمة المجهولة تسقط إلى unlogged/none معلَنةً
     *  لا مبتلَعةً صامتةً (گوتشا ENUM الموثقة). */
    private static function safeOpsState($v)
    {
        $valid = array('actual_work','standby','tech_breakdown','supplier_stop','operator_stop',
                       'client_stop','fuel_logistics_stop','planned_stop','force_majeure','unlogged');
        return in_array($v, $valid, true) ? $v : 'unlogged';
    }

    /**
     * الجهةُ المسؤولة — والابتلاعُ الصامتُ مرفوع (CON-02 · هـ-4).
     *
     * كان هذا السطرُ يبتلع **أي** قيمةٍ غير صالحةٍ إلى `'none'` بلا أثر، فيبدو
     * الإسنادُ نظيفًا وهو في الحقيقة ضائع — وهو ما جعل تقريرَ الفروقات يقول:
     * «أخطرُ ما في الوضع الراهن ليس ما هو مفقود بل ما يبدو موجودًا».
     *
     * والرفعُ على نمط المشروع المجرَّب (`EMS_ACTION_GUARD` · `EMS_CSRF_ENFORCE`):
     *   `monitor` (الافتراض) → يُسجَّل في `error_log` ويمضي — أسبوعُ رصد
     *   `enforce`            → يرمي، فلا تمرّ قيمةٌ مجهولةٌ إطلاقًا
     * والبياناتُ نظيفةٌ اليوم (0 مخالفة · مقيس) فالمخاطرةُ صفر — لكن يُلتزم النمط.
     */
    private static function safeRespParty($v)
    {
        $valid = array('company','supplier','operator','client','planned','force_majeure','none');
        if (in_array($v, $valid, true)) { return $v; }

        $mode = function_exists('ems_env')
            ? strtolower((string) ems_env('EMS_RESP_PARTY_STRICT', 'monitor')) : 'monitor';
        $shown = ($v === null) ? 'NULL' : ('«' . (string) $v . '»');
        if ($mode === 'enforce') {
            throw new \InvalidArgumentException(
                'جهةٌ مسؤولةٌ غيرُ معروفة: ' . $shown . ' — الإسنادُ لا يُبتلع صامتًا (CON-02 §5)');
        }
        error_log('[resp_party] قيمةٌ غيرُ معروفةٍ ابتُلعت إلى none: ' . $shown
                  . ' — رصدٌ قبل الإلزام (EMS_RESP_PARTY_STRICT)');
        return 'none';
    }

    private static function publishUnitEvent(\mysqli $conn, $companyId, $entryId, $eventKey, $actor, array $payload)
    {
        try {
            EventPublisher::publishFact($conn, array(
                'event_key' => $eventKey,
                'category' => 'operational',
                'source_module' => 'projects',
                'company_id' => (int) $companyId,
                'entity_type' => 'unit_entry',
                'entity_id' => (int) $entryId,
                'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => (int) $actor ?: 1,
                'idempotency_key' => $eventKey . ':unit_entry:' . $entryId . ':r' . (isset($payload['round']) ? $payload['round'] : 1),
                'payload' => $payload,
            ));
        } catch (\Throwable $t) {
            // الحدثُ إشهارٌ لا شرطُ صحة — فشلُه يُسجَّل ولا يُسقط العملية
            error_log('TimesheetEntryService publish ' . $eventKey . ': ' . $t->getMessage());
        }
    }
}
