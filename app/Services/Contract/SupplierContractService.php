<?php
/**
 * app/Services/Contract/SupplierContractService.php — عقدُ المورد ببنوده (H-07)
 * ═══════════════════════════════════════════════════════════════════════════
 * CON-03 §2-②④ · §6:
 *  · «نموذجُ التشغيل لكل بند: ساعةٌ · طنٌ · نقلةٌ · مترٌ — **وقد يختلف عن نموذج
 *    عقد العميل** بالاتفاق، ولا يُشترط تطابقُ الوحدة ولا الكمية» (§2-②).
 *  · «سعرُ الوحدة لكل نموذجٍ وبند · العملةُ · شروطُ تعديل السعر · **أساسُ
 *    احتساب الاستعداد إن استُحق**» (§2-④) — وهذا الأساسُ هو ما تنتظره الفجوةُ
 *    المدوَّنةُ نصًّا في `EffectFanout::qtyAward` («يلزمه سعرٌ لا وجودَ له»).
 *  · حراسُ §6-Validation: نموذجٌ خارج القائمة 422 · تعديلُ نافذٍ مباشرةً
 *    **423 «التغيير بملحق»** · قاعدةٌ بلا سعرٍ مكتوب 422.
 *
 * وحالةُ العقد مفرداتُ `ContractStateMachine` نفسُها — CON-03 لا تعدّد حالاتٍ
 * للمورد، فاختراعُ قاموسٍ ثانٍ للعقد نفسِه تلفيقٌ ويكسر «الاسمَ في موضعين».
 *
 * ولا حصصَ هنا: هرمُ L2/L3 استوعبته `op_containers` في H-06 (§6-التوافق نصًّا).
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/ContractStateMachine.php';

class SupplierContractService
{
    /** نماذجُ §2-② الأربعة — مرآةُ ENUM `work_model` حرفًا بحرف. */
    const WORK_MODELS = array('hour', 'ton', 'trip', 'meter');

    /** أسسُ الاستعداد الثلاثة — مرآةُ ENUM `standby_basis`. */
    const STANDBY_BASES = array('none', 'rate', 'percent');

    /**
     * تسمياتُ الوحدة المقبولة لكل نموذج — **هي تسمياتُ محرّك الفوترة نفسُها**
     * (`EffectFanout::CONTRACT_UNIT`) لا قاموسٌ ثالث. فما لا يعرفه المحرّكُ
     * لا يُقبل بندًا: بندٌ بوحدةٍ مجهولةٍ سعرٌ لا يصل إلى مال — «لا تسعير ملفَّق».
     */
    const UNIT_LABELS = array(
        'hour'  => array('ساعة'),
        'ton'   => array('طن'),
        'trip'  => array('نقلة'),
        'meter' => array('متر طولي', 'متر'),
    );

    /** العملاتُ المعروفة — مرآةُ `EffectFanout::CONTRACT_CURRENCY` بقيمها. */
    const CURRENCIES = array('USD', 'SDG', 'EUR', 'SAR');

    // ═════════════════════════════════════════════════════════════════════
    // ① الرأس
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إنشاءُ رأس عقد مورد (CON-03 §6).
     * @return array{ok:bool,code:int,reason:string,contract_id:?int}
     */
    public static function createContract($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'contract_id' => null);

        $supplierId = isset($args['supplier_id']) ? (int) $args['supplier_id'] : 0;
        $clientRef  = isset($args['client_contract_id']) && $args['client_contract_id'] !== ''
                        ? (int) $args['client_contract_id'] : null;
        $start      = isset($args['start_date']) ? trim((string) $args['start_date']) : '';
        $end        = isset($args['end_date']) ? trim((string) $args['end_date']) : '';
        $currency   = isset($args['currency']) ? strtoupper(trim((string) $args['currency'])) : '';

        if ($supplierId <= 0) { $out['code'] = 422; $out['reason'] = 'الموردُ إلزامي'; return $out; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ البدء إلزامي (المفتاحُ الفريد يحمله)'; return $out;
        }
        if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الانتهاء غيرُ صالح'; return $out;
        }
        if ($end !== '' && $end < $start) {
            $out['code'] = 422; $out['reason'] = 'الانتهاءُ قبل البدء'; return $out;
        }
        if ($currency !== '' && !in_array($currency, self::CURRENCIES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'عملةٌ غيرُ معروفةٍ لمحرّك الفوترة: ' . $currency . ' — المعروف: ' . implode('·', self::CURRENCIES);
            return $out;
        }

        // الموردُ من النطاق
        $sup = null;
        try { $sup = $gate->selectOne('suppliers', array('columns' => array('id'), 'where' => array('id' => $supplierId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $sup'); $sup = null; }
        if (!$sup) { $out['code'] = 422; $out['reason'] = 'الموردُ غيرُ موجودٍ في نطاقك'; return $out; }

        // وصلةُ L1 — عقدُ العميل الذي تُقتطع منه الحصة
        if ($clientRef !== null) {
            $cc = null;
            try { $cc = $gate->selectOne('contracts', array('columns' => array('id'), 'where' => array('id' => $clientRef))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $cc'); $cc = null; }
            if (!$cc) { $out['code'] = 422; $out['reason'] = 'عقدُ العميل (L1) غيرُ موجودٍ في نطاقك'; return $out; }
        }

        $newId = null;
        try {
            $newId = (int) $gate->insert('supplier_contracts', array(
                'supplier_id'        => $supplierId,
                'client_contract_id' => $clientRef,
                'project_id'         => isset($args['project_id']) && $args['project_id'] !== '' ? (int) $args['project_id'] : null,
                'start_date'         => $start,
                'end_date'           => $end !== '' ? $end : null,
                'currency'           => $currency !== '' ? $currency : null,
                'state'              => ContractStateMachine::DRAFT,
                'notes'              => isset($args['notes']) && trim((string) $args['notes']) !== ''
                                        ? mb_substr(trim((string) $args['notes']), 0, 255) : null,
                'created_by'         => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (self::isDuplicate($t)) {
                $out['code'] = 409;
                $out['reason'] = 'للمورد عقدٌ على عقد العميل نفسِه بتاريخ البدء نفسِه (UQ) — عدّل التاريخ أو حرّر القائم';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الإنشاء: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_contracts', 'create', $newId,
            array(), array('supplier_id' => $supplierId, 'client_contract_id' => $clientRef, 'start_date' => $start));

        $out['ok'] = true; $out['code'] = 200; $out['contract_id'] = $newId;
        return $out;
    }

    /**
     * انتقالُ حالة عقد المورد — **بقائمة سماح `ContractStateMachine` نفسِها**.
     * (لولاها بقي الرأسُ مسودةً أبدًا؛ ولا قائمةَ ثانيةً تُخترع لعقدٍ هو عقد.)
     * قفلٌ تفاؤلي: النسخةُ المتغيرة **409** · المرحَّلُ **423 بمصدره**.
     * @return array{ok:bool,code:int,reason:string,state:?string}
     */
    public static function transition($conn, $gate, $companyId, $contractId, $to, $version, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null);
        $head = self::head($gate, (int) $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }
        if (trim((string) $head['source_table']) !== '') {
            $out['code'] = 423;
            $out['reason'] = 'عقدٌ مرحَّلٌ من ' . $head['source_table'] . '#' . $head['source_id']
                           . ' — حالتُه مرآةُ مصدره ولا تُقاد من هنا';
            return $out;
        }
        $from = (string) $head['state'];
        if (!ContractStateMachine::canTransition($from, (string) $to)) {
            $out['code'] = 422;
            $out['reason'] = 'انتقالٌ غيرُ مشروعٍ من «' . $from . '» إلى «' . $to . '» — المسموح: '
                           . (implode(' · ', ContractStateMachine::allowedFrom($from)) ?: 'لا شيء');
            return $out;
        }
        if ((int) $version > 0 && (int) $version !== (int) $head['version']) {
            $out['code'] = 409; $out['reason'] = 'تغيّر العقدُ من طرفٍ آخر (قفلٌ تفاؤلي) — أعد التحميل'; return $out;
        }

        // ── M-17 · «**ونتيجتُه شرطٌ في التجديد**» (CON-03 §4-التقييم) ───────
        // التجديدُ وحدَه يُحرَس: باقي الانتقالات لا تمسّها النتيجة. وبلا تقييمٍ
        // معتمَدٍ — أو بتقييمٍ يقضي بعدم الأهلية — **423 بسببه مسمًّى**، لا رفضٌ
        // أعمى: القارئُ يعرف ما يفعله ليجدّد.
        if ((string) $to === ContractStateMachine::RENEWED) {
            require_once __DIR__ . '/SupplierEvaluationService.php';
            $g = SupplierEvaluationService::renewalGate($gate, (int) $head['supplier_id']);
            if (!$g['ok']) {
                $out['code'] = 423; $out['reason'] = $g['reason']; return $out;
            }
        }

        // ── M-18 · «تصفيةُ إنهاء العقد» شرطُ إقفاله (ENT-02 §4) ─────────────
        // الإقفالُ وحدَه يُحرَس: إقفالُ الحصة وتسويةُ السلف وردُّ الضمان وشهادةُ
        // الإخلاء **قبل** أن يصير العقدُ «مقفلًا» — وإلا أُقفل عقدٌ وعليه رصيدُ
        // سلفةٍ أو ضمانٌ محتجزٌ لصاحبه.
        if ((string) $to === ContractStateMachine::CLOSED) {
            require_once __DIR__ . '/SupplierClosureService.php';
            $g = SupplierClosureService::contractCloseGate($gate, (int) $contractId);
            if (!$g['ok']) {
                $out['code'] = 423; $out['reason'] = $g['reason']; return $out;
            }
        }
        try {
            $gate->update('supplier_contracts',
                array('state' => (string) $to, 'version' => (int) $head['version'] + 1),
                array('id' => (int) $contractId, 'version' => (int) $head['version']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الانتقال: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'supplier_contracts', 'transition', (int) $contractId,
            array('state' => $from), array('state' => (string) $to));

        /* ══ INJ-0152 · «وينشر حدثًا واحدًا يُقفل حاوياتِه» ═══════════════════════
             كان الانتقالُ يُدقَّق ولا يُنشر: الحالةُ تتغيّر في الجدولِ ولا يعلم بها
             أحد. والدورةُ المحاسبيةُ كلُّها مبنيةٌ على **الوقائعِ المنشورة**
             (ADR-15) — فانتقالٌ صامتٌ لا يُحرّك مروحةَ الأثر، وحاوياتُ عقدٍ
             انتهى تبقى **نشطةً** فتستقبل تحميلًا على عقدٍ لا وجودَ له.
           ◆ **حدثٌ واحدٌ لا اثنان**: واقعةٌ واحدةٌ لانتقالٍ واحد — وتعدُّدُ
             الحقائقِ للواقعةِ الواحدةِ يُضاعف الأثرَ في المروحة.
           ◆ **والإقفالُ أثرُ الحدثِ لا فعلٌ موازٍ**: يقع مع الإنهاءِ والإقفالِ
             وحدَهما، ويحمل سببَه في `close_reason` فيُقرأ لاحقًا لماذا أُقفلت. */
        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                /* ◆ **والنطاقُ لاتينيٌّ بلا شرطةٍ سفلية**: عقدُ §9 يشترط `domain.entity.action`
                     و`^[a-z]+` للنطاق — فـ`supplier_contract.state.changed` تُرفض
                     صامتةً (يُبتلع الاستثناءُ في السجل) فيبقى الانتقالُ بلا حقيقة. */
                'event_key'     => 'procurement.supplier_contract.state_changed',
                'category'      => 'commercial',
                'source_module' => 'procurement',
                'company_id'    => (int) $companyId,
                'entity_type'   => 'supplier_contract',
                'entity_id'     => (int) $contractId,
                'occurred_at'   => gmdate('Y-m-d H:i:s'),
                'created_by'    => (int) $actor ?: 1,
                'payload'       => array('from' => $from, 'to' => (string) $to),
            ));
        } catch (\Throwable $t) {
            /* الحدثُ أثرٌ لا شرط: انتقالٌ وقع لا يُلغى لأنَّ النشرَ تعثّر —
               لكنَّ التعثّرَ يُسجَّل فلا يمرُّ صامتًا. */
            error_log('supplier contract fact failed #' . (int) $contractId . ': ' . $t->getMessage());
        }

        if (in_array((string) $to, array(ContractStateMachine::ENDED, ContractStateMachine::CLOSED), true)) {
            $closed = 0;
            $st = $conn->prepare(
                "UPDATE op_containers
                    SET state = 'مقفلة', close_reason = ?, updated_at = NOW()
                  WHERE company_id = ? AND supplier_id = ? AND state <> 'مقفلة'
                    AND COALESCE(is_deleted,0) = 0");
            if ($st) {
                $why = 'إقفالٌ تبعًا لعقدِ المورد #' . (int) $contractId . ' (' . (string) $to . ')';
                $cid2 = (int) $companyId; $sup = (int) $head['supplier_id'];
                $st->bind_param('sii', $why, $cid2, $sup);
                if ($st->execute()) { $closed = (int) $conn->affected_rows; }
                $st->close();
            }
            if ($closed > 0) {
                self::audit($conn, $companyId, $actor, 'op_containers', 'close_by_contract',
                    (int) $contractId, array('state' => 'نشطة'), array('state' => 'مقفلة', 'count' => $closed));
                $out['containers_closed'] = $closed;
            }
        }

        $out['ok'] = true; $out['code'] = 200; $out['state'] = (string) $to;
        return $out;
    }

    /**
     * ══ INJ-0152 · نقضُ الإنهاء — **حركةٌ معوِّضةٌ بمرجعِها لا رجوعٌ في الزمن** ══
     *
     * نصُّ القبول يشترط «**وله فعلُ نقضٍ**». وجدولُ الانتقالاتِ لا يحمل حافةً من
     * «منتهٍ» إلى ما قبلَها — وهذا صوابٌ: العقدُ المنتهي لا يُحيا بتعديلِ عمود.
     *
     * فالنقضُ هنا **ليس انتقالًا** بل **تعويضٌ محكوم**:
     *   ① لا يقع إلا على عقدٍ «منتهٍ» — وما بعدَه (مقفل · مصفّى) لا نقضَ له
     *      لأنَّ التزاماتِه سُوِّيت وردُّها يفتح ما أُغلق.
     *   ② ولا يقع إلا **داخلَ نافذةِ سبعةِ أيامٍ** من الإنهاء: خطأٌ يُكتشف
     *      بعد أسبوعٍ يُعالَج بعقدٍ جديدٍ لا بإنكارِ ما وقع.
     *   ③ ويُعيد الحالةَ إلى **ما كانت عليه قبلَ الإنهاءِ مقروءةً من سجلِّ
     *      التدقيق** — لا إلى حالةٍ يختارها الناقض.
     *   ④ ويُعيد فتحَ الحاوياتِ التي أُقفلت **بذلك الإنهاءِ وحدَه** (بمطابقةِ
     *      سببِ الإقفال) — فلا يفتح ما أُقفل لسببٍ آخر.
     *   ⑤ وينشر حقيقةً جديدةً تشير إلى الأصل — فالسجلُّ يقرأ «أُنهي ثم نُقض»
     *      لا «لم يُنهَ قطّ». **والعكسُ حركةٌ لا محو** (CS-08).
     *
     * @return array{ok:bool,code:int,reason:string,state:?string,containers_reopened:int}
     */
    public static function revokeTermination($conn, $gate, $companyId, $contractId, $why, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null, 'containers_reopened' => 0);
        $contractId = (int) $contractId;
        $why = trim((string) $why);
        if ($why === '') {
            $out['code'] = 422; $out['reason'] = 'SCT-422: نقضُ الإنهاءِ يلزمه سببٌ مكتوب'; return $out;
        }
        $head = self::head($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $head['state'] !== ContractStateMachine::ENDED) {
            $out['code'] = 422;
            $out['reason'] = 'SCT-422: النقضُ للمنتهي وحدَه — والعقدُ الآن «' . $head['state'] . '»';
            return $out;
        }

        /* ① الحالةُ السابقةُ والنافذةُ الزمنيةُ — من سجلِّ التدقيقِ لا من ظنّ */
        $prev = ''; $whenTs = 0;
        /* ◆ **وعمودُ الجدولِ في سجلِّ التدقيقِ اسمُه `screen_name` لا `table_name`**:
             أوّلُ صياغةٍ سألت باسمٍ لا وجودَ له فرجعت صفرًا دائمًا — فالنقضُ
             يردُّ ٤٠٩ «لا أثرَ» على عقدٍ أثرُه مكتوب. والاستعلامُ يُقاس لا يُظنّ. */
        $st = $conn->prepare(
            "SELECT old_value, created_at FROM activity_logs
              WHERE screen_name = 'supplier_contracts' AND record_id = ?
                AND action_type = 'transition'
              ORDER BY id DESC LIMIT 1");
        if ($st) {
            $st->bind_param('i', $contractId);
            if ($st->execute()) {
                $row = $st->get_result()->fetch_assoc();
                if ($row) {
                    $j = json_decode((string) $row['old_value'], true);
                    if (is_array($j) && isset($j['state'])) { $prev = (string) $j['state']; }
                    $whenTs = strtotime((string) $row['created_at']);
                }
            }
            $st->close();
        }
        if ($prev === '' || !in_array($prev, ContractStateMachine::ALL, true)) {
            $out['code'] = 409;
            $out['reason'] = 'SCT-409: لا أثرَ تدقيقٍ يحمل الحالةَ السابقة — فلا يُخمَّن إلى أين يُردّ';
            return $out;
        }
        if ($whenTs > 0 && (time() - $whenTs) > (7 * 86400)) {
            $out['code'] = 423;
            $out['reason'] = 'SCT-423: مضى أكثرُ من سبعةِ أيامٍ على الإنهاء — يُعالَج بعقدٍ جديدٍ لا بنقض';
            return $out;
        }

        try {
            $gate->update('supplier_contracts',
                array('state' => $prev, 'version' => (int) $head['version'] + 1),
                array('id' => $contractId, 'version' => (int) $head['version']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر النقض: ' . $t->getMessage(); return $out;
        }

        /* ④ الحاوياتُ التي أُقفلت بهذا الإنهاءِ وحدَها */
        $reopened = 0;
        $st2 = $conn->prepare(
            "UPDATE op_containers SET state = 'نشطة', close_reason = NULL, updated_at = NOW()
              WHERE company_id = ? AND state = 'مقفلة' AND close_reason LIKE ?");
        if ($st2) {
            $like = 'إقفالٌ تبعًا لعقدِ المورد #' . $contractId . '%';
            $cid2 = (int) $companyId;
            $st2->bind_param('is', $cid2, $like);
            if ($st2->execute()) { $reopened = (int) $conn->affected_rows; }
            $st2->close();
        }

        self::audit($conn, $companyId, $actor, 'supplier_contracts', 'revoke_termination', $contractId,
            array('state' => ContractStateMachine::ENDED),
            array('state' => $prev, 'reason' => $why, 'containers_reopened' => $reopened));

        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'     => 'procurement.supplier_contract.termination_revoked',
                'category'      => 'commercial',
                'source_module' => 'procurement',
                'company_id'    => (int) $companyId,
                'entity_type'   => 'supplier_contract',
                'entity_id'     => $contractId,
                'occurred_at'   => gmdate('Y-m-d H:i:s'),
                'created_by'    => (int) $actor ?: 1,
                'payload'       => array('restored_to' => $prev, 'reason' => $why,
                                         'containers_reopened' => $reopened),
            ));
        } catch (\Throwable $t) {
            error_log('supplier revoke fact failed #' . $contractId . ': ' . $t->getMessage());
        }

        $out['ok'] = true; $out['code'] = 200; $out['state'] = $prev;
        $out['containers_reopened'] = $reopened;
        $out['reason'] = 'نُقض الإنهاءُ — عاد العقدُ إلى «' . $prev . '»'
                       . ($reopened > 0 ? (' وأُعيد فتحُ ' . $reopened . ' حاوية') : '');
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② البنود — «سعرُ الوحدة لكل نموذجٍ وبند» + أساسُ الاستعداد
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إضافةُ بندٍ أو تعديلُه (`line_id` يعني تعديلًا).
     * @return array{ok:bool,code:int,reason:string,line_id:?int}
     */
    public static function saveLine($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => null);
        $contractId = (int) $contractId;
        $lineId = isset($args['line_id']) && (int) $args['line_id'] > 0 ? (int) $args['line_id'] : 0;

        $head = self::head($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }

        // ── حارسا الشريحة: النافذُ بملحقٍ · والمرحَّلُ بمصدره ──────────────
        $blocked = self::assertEditable($head);
        if ($blocked !== null) { return array_merge($out, $blocked); }

        // ── قوائمُ §6 المحكومة ────────────────────────────────────────────
        $model = isset($args['work_model']) ? trim((string) $args['work_model']) : '';
        if (!in_array($model, self::WORK_MODELS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'نموذجُ تشغيلٍ خارج قائمة §2-② الأربعة: ' . implode('·', self::WORK_MODELS);
            return $out;
        }
        $unit = isset($args['unit']) ? trim((string) $args['unit']) : '';
        if (!in_array($unit, self::UNIT_LABELS[$model], true)) {
            $out['code'] = 422;
            $out['reason'] = 'وحدةٌ لا يعرفها محرّكُ الفوترة لنموذج «' . $model . '» — المقبول: '
                           . implode(' · ', self::UNIT_LABELS[$model]) . ' (بندٌ بوحدةٍ مجهولةٍ سعرٌ لا يصل إلى مال)';
            return $out;
        }

        $price = isset($args['unit_price']) ? round((float) $args['unit_price'], 2) : 0.0;
        if ($price <= 0) {
            $out['code'] = 422; $out['reason'] = 'سعرُ الوحدة إلزاميٌّ وموجب — «صفرُ بندٍ بلا سعرٍ مكتوب» (§7-القبول)';
            return $out;
        }

        $currency = isset($args['currency']) ? strtoupper(trim((string) $args['currency'])) : '';
        if ($currency !== '' && !in_array($currency, self::CURRENCIES, true)) {
            $out['code'] = 422; $out['reason'] = 'عملةُ بندٍ غيرُ معروفة: ' . $currency; return $out;
        }

        // ── أساسُ الاستعداد — «إن استُحق» (§2-④) ───────────────────────────
        $basis = isset($args['standby_basis']) ? trim((string) $args['standby_basis']) : 'none';
        if (!in_array($basis, self::STANDBY_BASES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'أساسُ استعدادٍ خارج الثلاثة: ' . implode('·', self::STANDBY_BASES);
            return $out;
        }
        $rateRaw = isset($args['standby_rate']) && $args['standby_rate'] !== '' ? (float) $args['standby_rate'] : null;
        if ($basis === 'none' && $rateRaw !== null) {
            $out['code'] = 422;
            $out['reason'] = 'معدلُ استعدادٍ بلا أساسٍ مشترط — إمّا أساسٌ بمعدله أو لا استعداد';
            return $out;
        }
        if ($basis !== 'none' && ($rateRaw === null || $rateRaw <= 0)) {
            $out['code'] = 422;
            $out['reason'] = 'أساسُ الاستعداد «' . $basis . '» بلا معدلٍ موجب — «قاعدةٌ بلا سعرٍ مكتوب» مرفوضة (§6-Validation)';
            return $out;
        }
        if ($basis === 'percent' && $rateRaw > 100) {
            $out['code'] = 422; $out['reason'] = 'نسبةُ الاستعداد تتجاوز 100٪ من سعر الوحدة'; return $out;
        }

        // ── CAP-01 §8.2: بندُ نوع المعدة والاحتياطي في عقد المورد ──────────
        // «لا حصةَ بلا التزامِ نوعِ معدةٍ في عقد عميلٍ نافذ» — المرجعُ يُفحص وجودًا ونفاذًا
        //
        // ◆ **الحارسُ كان مشروطًا بوجودِ ما يحرسه** (البند ٢-١ من INJ-EXEC-CLOSE-01):
        //   كان `if ($oblRef !== null)` — فمن حذف الحقلَ من الطلبِ تخطّى القاعدةَ
        //   كلَّها. والقاعدةُ كانت مكتوبةً في ثلاثةِ مواضعَ (الوثيقةِ، وهذا التعليقِ،
        //   وملصقِ الحقلِ نفسِه) ومنفَّذةً في **صفر**. والمقيسُ يومَها: `saveLine`
        //   بلا مرجعٍ تعود **200**، و**٦ من ٦** بنودٍ حيةٍ بلا مرجعِ التزام.
        // ◆ والغيابُ الآن **يُردُّ ٤٢٢ قبلَ أيِّ فحصٍ آخر** — فالشرطُ على الغيابِ لا
        //   على الحضور، ولا يُتخطّى بحذفِ الحقل.
        $oblRef = isset($args['contract_obligation_ref']) && (int) $args['contract_obligation_ref'] > 0
                  ? (int) $args['contract_obligation_ref'] : null;
        if ($oblRef === null) {
            $out['code'] = 422;
            $out['reason'] = 'لا حصةَ بلا التزام — مرجعُ التزامِ نوعِ المعدةِ في عقدِ العميلِ مطلوبٌ ولا يُترك فارغًا (CAP-01 §8.2)';
            return $out;
        }
        {
            $obl = $gate->scopedQuery(array('scope' => array('cc' => 'contract_commitments', 'k' => 'contracts')),
                "SELECT cc.id, cc.equipment_type_code FROM contract_commitments cc
                   JOIN contracts k ON k.id = cc.contract_ref AND k.is_deleted = 0
                  WHERE {TENANT_SCOPE} AND cc.id = ? AND cc.is_deleted = 0",
                array($oblRef));
            if (!$obl) {
                $out['code'] = 422;
                $out['reason'] = 'لا حصةَ بلا التزامٍ — مرجعُ التزام نوع المعدة غيرُ موجودٍ في عقد عميلٍ نافذ';
                return $out;
            }
        }
        $etype = isset($args['equipment_type_code']) ? trim((string) $args['equipment_type_code']) : '';
        if ($etype !== '' && !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $etype)) {
            $out['code'] = 422; $out['reason'] = 'رمزُ نوع المعدة غيرُ صالح'; return $out;
        }
        $capInt = function ($key) use ($args) {
            if (!isset($args[$key]) || trim((string) $args[$key]) === '') return null;
            $v = (int) $args[$key];
            return $v < 0 ? false : $v;
        };
        $primaryCommitted = $capInt('primary_units_committed');
        $sbRequired = $capInt('standby_units_required');
        $sbAllowed = $capInt('standby_units_allowed');
        if ($primaryCommitted === false || $sbRequired === false || $sbAllowed === false) {
            $out['code'] = 422; $out['reason'] = 'أعدادُ الوحدات الملتزَم بها أرقامٌ غيرُ سالبة'; return $out;
        }
        $slaRaw = isset($args['replacement_sla_hours']) && trim((string) $args['replacement_sla_hours']) !== ''
                  ? (float) $args['replacement_sla_hours'] : null;
        if ($slaRaw !== null && $slaRaw < 0) {
            $out['code'] = 422; $out['reason'] = 'مهلةُ الإحلال بالساعات لا تكون سالبة'; return $out;
        }
        // مقابلُ الاحتياطي لا يُفترض — الفارغُ NULL صراحةً (DEC-CAP-A)
        $sbActTerms = isset($args['standby_activation_terms']) && trim((string) $args['standby_activation_terms']) !== ''
                      ? mb_substr(trim((string) $args['standby_activation_terms']), 0, 255) : null;
        $sbPayTerms = isset($args['standby_payment_terms']) && trim((string) $args['standby_payment_terms']) !== ''
                      ? mb_substr(trim((string) $args['standby_payment_terms']), 0, 255) : null;

        $data = array(
            'contract_obligation_ref'  => $oblRef,
            'equipment_type_code'      => $etype !== '' ? $etype : null,
            'primary_units_committed'  => $primaryCommitted,
            'standby_units_required'   => $sbRequired,
            'standby_units_allowed'    => $sbAllowed,
            'replacement_sla_hours'    => $slaRaw,
            'standby_activation_terms' => $sbActTerms,
            'standby_payment_terms'    => $sbPayTerms,
            'work_model'    => $model,
            'unit'          => $unit,
            'unit_price'    => $price,
            'currency'      => $currency !== '' ? $currency : null,
            'standby_basis' => $basis,
            'standby_rate'  => $basis === 'none' ? null : round((float) $rateRaw, 4),
            'valid_from'    => isset($args['valid_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_from'])
                               ? (string) $args['valid_from'] : $head['start_date'],
            'valid_to'      => isset($args['valid_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_to'])
                               ? (string) $args['valid_to'] : null,
        );

        $before = array();
        try {
            if ($lineId > 0) {
                $cur = $gate->selectOne('supplier_contract_lines', array('where' => array('id' => $lineId)));
                if (!$cur || (int) $cur['contract_id'] !== $contractId) {
                    $out['code'] = 404; $out['reason'] = 'البندُ غيرُ موجودٍ في هذا العقد'; return $out;
                }
                if (trim((string) $cur['source_table']) !== '') {
                    $out['code'] = 423;
                    $out['reason'] = 'بندٌ مرحَّلٌ من ' . $cur['source_table'] . '#' . $cur['source_id']
                                   . ' — الكتابةُ تبقى في مصدره حتى تكتمل المطابقة (N-04 مرحلة ①)';
                    return $out;
                }
                // C12 (CAP-25): حصةٌ استُهلك منها في شهرٍ مقفلٍ لا تُعدَّل كمياتُها —
                // الانحرافُ التاريخيُّ محفوظٌ والتعديلُ فترةٌ جديدةٌ بسريان
                $qtyChanged = ((int) $cur['primary_units_committed'] !== (int) $primaryCommitted)
                    || (round((float) $cur['unit_price'], 2) !== $price);
                if ($qtyChanged && class_exists('\\App\\Services\\Capacity\\SupplierPerformanceAggregator')) {
                    $ed = \App\Services\Capacity\SupplierPerformanceAggregator::assertShareEditable($gate, $lineId);
                    if (!$ed['ok']) { return array_merge($out, array('code' => $ed['code'], 'reason' => $ed['reason'])); }
                }
                $before = array('work_model' => $cur['work_model'], 'unit' => $cur['unit'],
                                'unit_price' => $cur['unit_price'], 'standby_basis' => $cur['standby_basis'],
                                'standby_rate' => $cur['standby_rate']);
                $gate->update('supplier_contract_lines', $data, array('id' => $lineId));
            } else {
                $data['contract_id'] = $contractId;
                $data['state'] = 'active';
                $data['created_by'] = (int) $actor ?: null;
                $lineId = (int) $gate->insert('supplier_contract_lines', $data);
            }
        } catch (\Throwable $t) {
            if (self::isDuplicate($t)) {
                $out['code'] = 409;
                $out['reason'] = 'للعقد بندٌ بالنموذج والوحدة نفسِهما (UQ عقد×نموذج×وحدة) — حرّر القائمَ ولا تكرره';
                return $out;
            }
            if (strpos($t->getMessage(), 'ck_sup_line_standby') !== false) {
                $out['code'] = 422; $out['reason'] = 'قيدُ الاستعداد البنيوي رفض الصف — أساسٌ بلا معدلٍ أو معدلٌ بلا أساس';
                return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_contract_lines',
            $before ? 'update' : 'create', $lineId, $before, $data);

        $out['ok'] = true; $out['code'] = 200; $out['line_id'] = $lineId;
        return $out;
    }

    /** إنهاءُ بندٍ بسريان — لا حذفَ (عقيدة ④): الحالةُ `ended` وتاريخُ نهايته. */
    public static function endLine($conn, $gate, $companyId, $contractId, $lineId, $endDate, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $head = self::head($gate, (int) $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقدُ المورد غيرُ موجودٍ في نطاقك'; return $out; }
        $blocked = self::assertEditable($head);
        if ($blocked !== null) { return array_merge($out, $blocked); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $endDate)) {
            $out['code'] = 422; $out['reason'] = 'تاريخُ الإنهاء إلزامي'; return $out;
        }
        $cur = null;
        try { $cur = $gate->selectOne('supplier_contract_lines', array('where' => array('id' => (int) $lineId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $cur'); $cur = null; }
        if (!$cur || (int) $cur['contract_id'] !== (int) $contractId) {
            $out['code'] = 404; $out['reason'] = 'البندُ غيرُ موجودٍ في هذا العقد'; return $out;
        }
        if ((string) $cur['state'] === 'ended') {
            $out['code'] = 422; $out['reason'] = 'البندُ منتهٍ سلفًا'; return $out;
        }
        try {
            $gate->update('supplier_contract_lines',
                array('state' => 'ended', 'valid_to' => (string) $endDate), array('id' => (int) $lineId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإنهاء: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'supplier_contract_lines', 'end', (int) $lineId,
            array('state' => $cur['state'], 'valid_to' => $cur['valid_to']),
            array('state' => 'ended', 'valid_to' => (string) $endDate));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الوصلُ الحي — «أساسُ احتساب الاستعداد» الذي ينتظره محرّكُ المروحة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أساسُ استعداد المورد الساري بتاريخ العمل لنموذجٍ بعينه.
     *
     * هذه هي الإجابةُ على الفجوة المدوَّنة نصًّا في `EffectFanout::qtyAward`:
     * «تحويلُ الاستعداد إلى مالٍ يلزمه **سعرٌ لا وجودَ له** … فيُوسَم
     * `standby_needs_rate` ولا يُخترع له مال». فإن أعلن بندُ عقد المورد أساسًا
     * صار السعرُ **مقرَّرًا** — وإن لم يُعلن بقي الإعلانُ كما هو حرفًا بحرف.
     *
     * سلّمُ الحسم (تناظرُ `resolveTimesheet`): الساري بالتاريخ ← الوحيدُ النافذ
     * ← تعذّرٌ معلَن. وتعددُ العقود السارية **لا يُخمَّن فيه** — يُعلَن.
     *
     * @return array{ok:bool,reason:string,basis:?string,rate:?float,unit_price:?float,
     *               currency:?string,line_id:?int,contract_id:?int}
     */
    public static function standbyBasisFor(\mysqli $conn, $companyId, $supplierId, $clientContractId, $workModel, $date)
    {
        if (!in_array((string) $workModel, self::WORK_MODELS, true)) {
            return self::emptyBasis('نموذجُ تشغيلٍ غيرُ صالح');
        }
        $map = self::standbyBasisMap($conn, $companyId, $supplierId, $clientContractId, $date);
        return isset($map[(string) $workModel]) ? $map[(string) $workModel]
             : self::emptyBasis('لا بندَ بهذا النموذج');
    }

    /**
     * خريطةُ الأسس للنماذج الأربعة دفعةً واحدة — استعلامٌ واحدٌ لا أربعة.
     * (يُقرأ بـ`mysqli` وشركةٍ صريحةٍ لأن مستهلكَه `EffectFanout::resolveTimesheet`
     * قراءةٌ خالصةٌ بلا بوابةٍ — والعزلُ محفوظٌ بشرط `company_id` في النص.)
     *
     * @return array<string,array{ok:bool,reason:string,basis:?string,rate:?float,
     *                            unit_price:?float,currency:?string,line_id:?int,contract_id:?int}>
     */
    public static function standbyBasisMap(\mysqli $conn, $companyId, $supplierId, $clientContractId, $date)
    {
        $map = array();
        foreach (self::WORK_MODELS as $m) { $map[$m] = self::emptyBasis(''); }

        $companyId = (int) $companyId; $supplierId = (int) $supplierId;
        if ($companyId <= 0 || $supplierId <= 0) {
            foreach ($map as $m => $_) { $map[$m] = self::emptyBasis('لا موردَ على الواقعة'); }
            return $map;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) { $date = date('Y-m-d'); }

        // مفرداتُ الحالة النافذة من الآلة نفسِها — لا قائمةَ ثانيةً تتقادم.
        $marks = implode(',', array_fill(0, count(ContractStateMachine::EFFECTIVE_STATES), '?'));
        $sql = "SELECT h.id AS contract_id, h.currency AS head_currency,
                       l.id AS line_id, l.work_model, l.unit_price, l.currency AS line_currency,
                       l.standby_basis, l.standby_rate,
                       (h.start_date IS NOT NULL AND h.start_date <= ?
                        AND (h.end_date IS NULL OR h.end_date >= ?)) AS in_force
                  FROM supplier_contracts h
                  JOIN supplier_contract_lines l
                       ON l.contract_id = h.id AND COALESCE(l.is_deleted,0) = 0
                      AND l.state = 'active'
                      AND (l.valid_from IS NULL OR l.valid_from <= ?)
                      AND (l.valid_to   IS NULL OR l.valid_to   >= ?)
                 WHERE h.company_id = ? AND h.supplier_id = ?
                   AND COALESCE(h.is_deleted,0) = 0
                   AND h.state IN ({$marks})";
        $types = 'ssssii' . str_repeat('s', count(ContractStateMachine::EFFECTIVE_STATES));
        $params = array($date, $date, $date, $date, $companyId, $supplierId);
        foreach (ContractStateMachine::EFFECTIVE_STATES as $s) { $params[] = $s; }
        if ((int) $clientContractId > 0) {
            $sql .= " AND (h.client_contract_id = ? OR h.client_contract_id IS NULL)";
            $types .= 'i'; $params[] = (int) $clientContractId;
        }
        $sql .= " ORDER BY h.id";

        $st = $conn->prepare($sql);
        if (!$st) {
            foreach ($map as $m => $_) { $map[$m] = self::emptyBasis('تعذّرت القراءة: ' . $conn->error); }
            return $map;
        }
        $st->bind_param($types, ...$params);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        // سلّمُ الحسم لكل نموذجٍ على حدة (تناظرُ `resolveTimesheet`):
        // الساري بالتاريخ ← الوحيدُ النافذ ← **تعذّرٌ معلَن** بلا تخمين.
        $byModel = array();
        foreach ($rows as $r) { $byModel[(string) $r['work_model']][] = $r; }

        foreach (self::WORK_MODELS as $m) {
            if (empty($byModel[$m])) {
                $map[$m] = self::emptyBasis('لا بندَ عقدِ موردٍ نافذًا بنموذج «' . $m
                                            . '» — الاستعدادُ يبقى معلَنًا بلا سعرٍ مقرَّر');
                continue;
            }
            $cands = $byModel[$m];
            $inForce = array();
            foreach ($cands as $r) { if ((int) $r['in_force'] === 1) { $inForce[] = $r; } }
            $pick = null;
            if (count($inForce) === 1)   { $pick = $inForce[0]; }
            elseif (count($inForce) > 1) { $map[$m] = self::emptyBasis('أكثرُ من عقد موردٍ سارٍ بتاريخ العمل — يلزم حسمٌ يدوي'); continue; }
            elseif (count($cands) === 1) { $pick = $cands[0]; }
            else { $map[$m] = self::emptyBasis('عقودُ موردٍ متعددةٌ ولا ساريَ بتاريخ العمل — لا يُخمَّن أساس'); continue; }

            $info = self::emptyBasis('');
            $info['contract_id'] = (int) $pick['contract_id'];
            $info['line_id']     = (int) $pick['line_id'];
            $info['unit_price']  = (float) $pick['unit_price'];
            $info['currency']    = trim((string) $pick['line_currency']) !== ''
                                   ? (string) $pick['line_currency'] : $pick['head_currency'];
            $info['basis']       = (string) $pick['standby_basis'];
            if ($info['basis'] === 'none') {
                $info['reason'] = 'بندُ عقد المورد لا يشترط استعدادًا — لا يُخترع له سعر';
            } else {
                $info['ok'] = true;
                $info['rate'] = (float) $pick['standby_rate'];
            }
            $map[$m] = $info;
        }
        return $map;
    }

    /** قالبُ «لا أساسَ» بسببه المكتوب — الغيابُ يُعلَن ولا يُبتلع. */
    private static function emptyBasis($reason)
    {
        return array('ok' => false, 'reason' => (string) $reason, 'basis' => null, 'rate' => null,
                     'unit_price' => null, 'currency' => null, 'line_id' => null, 'contract_id' => null);
    }

    /**
     * تحويلُ ساعاتِ الاستعداد إلى مالٍ **بسعرٍ مقرَّر** — أو صفرٌ معلَن.
     * `rate`    → ساعاتٌ × معدلِ الساعة.
     * `percent` → ساعاتٌ × سعرِ الوحدة × النسبة ÷ 100.
     * @return array{amount:?float,note:string}
     */
    public static function standbyAmount(array $basisInfo, $hours)
    {
        $hours = round((float) $hours, 2);
        if ($hours <= 0) { return array('amount' => 0.0, 'note' => 'لا ساعاتِ استعدادٍ مفوترة'); }
        if (empty($basisInfo['ok'])) {
            return array('amount' => null,
                         'note' => 'استعدادٌ مفوترٌ بلا سعرٍ مقرَّر: ' . (string) $basisInfo['reason']);
        }
        $rate = (float) $basisInfo['rate'];
        if ($basisInfo['basis'] === 'rate') {
            return array('amount' => round($hours * $rate, 2),
                         'note' => 'استعدادٌ بمعدل ساعةٍ مقرَّرٍ في بند عقد المورد #' . (int) $basisInfo['line_id']);
        }
        $amount = round($hours * (float) $basisInfo['unit_price'] * ($rate / 100.0), 2);
        return array('amount' => $amount,
                     'note' => 'استعدادٌ بنسبة ' . $rate . '٪ من سعر الوحدة في بند عقد المورد #' . (int) $basisInfo['line_id']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءاتٌ ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    /** بنودُ عقدٍ مرتبةً — قراءةٌ خالصة. */
    public static function linesOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
                "SELECT l.* FROM supplier_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_id = ? AND COALESCE(l.is_deleted,0)=0
                  ORDER BY l.work_model, l.unit, l.id", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    /** رأسُ العقد من النطاق. */
    public static function head($gate, $contractId)
    {
        try { return $gate->selectOne('supplier_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    /**
     * حارسا الشريحة على الرأس:
     *  · **المرحَّلُ محصَّنٌ 423 بمصدره** — الكتابةُ تبقى في المصدر (N-04 مرحلة ①).
     *  · **النافذُ 423 «التغيير بملحق»** (§6-Validation · §4-الملاحق).
     * @return ?array{code:int,reason:string}
     */
    public static function assertEditable(array $head)
    {
        if (trim((string) $head['source_table']) !== '') {
            return array('code' => 423,
                'reason' => 'عقدٌ مرحَّلٌ قراءةً من ' . $head['source_table'] . '#' . $head['source_id']
                          . ' — الكتابةُ تبقى في مصدره حتى تكتمل مطابقةُ فترةٍ بصفر فرق (N-04 مرحلة ①)');
        }
        if (in_array((string) $head['state'], ContractStateMachine::EFFECTIVE_STATES, true)) {
            return array('code' => 423,
                'reason' => 'العقدُ «' . $head['state'] . '» — لا تعديلَ مباشرًا على نافذ: **التغيير بملحق** (CON-03 §4)');
        }
        return null;
    }

    /** كشفُ خرق مفتاحٍ فريد — `config.php` يبتلع الرمي فالنصُّ هو الدليل. */
    private static function isDuplicate(\Throwable $t)
    {
        $m = $t->getMessage();
        return strpos($m, 'Duplicate') !== false || strpos($m, 'uq_sup_') !== false;
    }

    /** تدقيقُ N-02 — قيمٌ قبل/بعد، لا يرمي. */
    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
