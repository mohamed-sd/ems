<?php
/**
 * app/Services/Contract/EmployeeContractService.php — خدمةُ رأس العقد الموحّد
 * (H-08-① · CON-01 §7.2) — قواعدُ التحقق الرأسية من العشرين:
 *   · نموذجٌ غيرُ مذكورٍ في القائمة الخمس عشرة → 422
 *   · عقدٌ متداخلُ المدة للشخص نفسه في الكيان نفسه → 409 بمرجع القائم
 *   · تعديلٌ مباشرٌ على نافذٍ → 423 «التغيير بملحق» (H-10 تبني الملحق)
 *   · الصفُّ المرحَّلُ قراءةً لا يُعدَّل — كاتبُه مصدرُه القديم (N-04)
 * (قواعدُ المكوّنات والحوافز والتحمّل تأتي مع شرائحها ②③④.)
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/EmployeeContractStateMachine.php';

class EmployeeContractService
{
    const CATEGORIES = array('permanent', 'project', 'operator', 'supplier_worker');

    /** ما يُحرَّر مباشرةً — قبل قفل نسخة المراجعة (Completed → Validated يقفل). */
    const EDITABLE_STATES = array(
        EmployeeContractStateMachine::DRAFT,
        EmployeeContractStateMachine::COMPLETED,
    );

    /** حالاتٌ لا تحجز المدة (الرفضُ والاعتذارُ والنهاياتُ المصفّاة). */
    const NON_BLOCKING_STATES = array(
        EmployeeContractStateMachine::REJECTED,
        EmployeeContractStateMachine::DECLINED,
        EmployeeContractStateMachine::EXPIRED,
        EmployeeContractStateMachine::TERMINATED,
        EmployeeContractStateMachine::SETTLED,
        EmployeeContractStateMachine::CLOSED,
        EmployeeContractStateMachine::ARCHIVED,
    );

    /**
     * إنشاءُ رأس عقدٍ (مسودة) — خطواتُ الرحلة ①→③ (الشخصُ والفئةُ والمدةُ والنموذج).
     * @return array{ok:bool,code:int,reason:string,id:?int}
     */
    public static function createHead($conn, $gate, $companyId, $data, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => null);

        $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : 0;
        $category   = isset($data['category']) ? (string) $data['category'] : '';
        $payModelId = isset($data['pay_model_id']) ? (int) $data['pay_model_id'] : 0;
        $startDate  = self::dateOrNull(isset($data['start_date']) ? $data['start_date'] : null);
        $endDate    = self::dateOrNull(isset($data['end_date']) ? $data['end_date'] : null);

        if ($employeeId <= 0) { $out['code'] = 422; $out['reason'] = 'الشخص إلزامي — «العقد يشير إلى سجل الأشخاص»'; return $out; }
        if (!in_array($category, self::CATEGORIES, true)) {
            $out['code'] = 422; $out['reason'] = 'فئة من خارج قائمة CON-01 §2: ' . $category; return $out;
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $out['code'] = 422; $out['reason'] = 'نهاية المدة قبل بدايتها'; return $out;
        }

        // الشخصُ من نطاق الشركة (البوابةُ ترفض الأجنبي)
        $emp = null;
        try { $emp = $gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employeeId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $emp'); $emp = null; }
        if (!$emp) { $out['code'] = 422; $out['reason'] = 'الشخص غير موجود في نطاقك'; return $out; }

        // «نموذجٌ غيرُ مذكورٍ في القائمة الخمس عشرة → 422» — الكتالوجُ المحكوم حصرًا
        $pm = self::payModelOf($conn, $payModelId);
        if (!$pm) { $out['code'] = 422; $out['reason'] = 'نموذج أجر من خارج القائمة الخمس عشرة المحكومة'; return $out; }

        // المشروعُ من النطاق إن حُدّد
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        if ($projectId > 0) {
            $proj = null;
            try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $projectId))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $proj'); $proj = null; }
            if (!$proj) { $out['code'] = 422; $out['reason'] = 'المشروع غير موجود في نطاقك'; return $out; }
        }

        // «عقدٌ متداخلُ المدة للشخص نفسه في الكيان نفسه → 409 بمرجع القائم»
        $clash = self::overlapOf($gate, $employeeId, $startDate, $endDate, 0);
        if ($clash) {
            $out['code'] = 409;
            $out['reason'] = 'مدة متداخلة مع العقد #' . (int) $clash['id']
                . ' (' . EmployeeContractStateMachine::labelAr($clash['state'])
                . ' · ' . ($clash['start_date'] ?: '؟') . ' → ' . ($clash['end_date'] ?: 'مفتوح') . ')';
            return $out;
        }

        $row = array(
            'employee_id'   => $employeeId,
            'category'      => $category,
            'relation_type' => self::strOrNull(isset($data['relation_type']) ? $data['relation_type'] : null, 50),
            'project_id'    => $projectId > 0 ? $projectId : null,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'probation_end' => self::dateOrNull(isset($data['probation_end']) ? $data['probation_end'] : null),
            'pay_model_id'  => (int) $pm['id'],
            'currency'      => self::strOrNull(isset($data['currency']) ? $data['currency'] : null, 8),
            'state'         => EmployeeContractStateMachine::DRAFT,
            'version'       => 1,
            'created_by'    => (int) $actor ?: null,
        );
        try {
            $newId = (int) $gate->insert('employee_contracts', $row);
        } catch (\Throwable $t) {
            $out['code'] = 409;
            $out['reason'] = 'تعذر الإنشاء — عقد بالبداية نفسها للشخص نفسه قائم؟ (' . $t->getMessage() . ')';
            return $out;
        }
        if ($newId <= 0) {
            // گوتشا مقيسة: mysqli لا يرمي — خرقُ قيدٍ يعود false صامتًا
            $out['code'] = 409; $out['reason'] = 'تعذر الإنشاء — قيد التفرد (الشخص × الكيان × البداية)';
            return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contracts', 'create', $newId,
            array(), $row, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId;
        return $out;
    }

    /**
     * تعديلُ الرأس — في المسودة وما قبل قفل المراجعة حصرًا.
     * النافذُ لا يُعدَّل مباشرةً: 423 «التغيير بملحق» (CON-01 §4 قيود الحالة).
     */
    public static function updateHead($conn, $gate, $companyId, $contractId, $data, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $contractId = (int) $contractId;

        $c = null;
        try { $c = $gate->selectOne('employee_contracts', array('where' => array('id' => $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقد غير موجود'; return $out; }

        $src = trim((string) ($c['source_table'] ?? ''));
        if ($src !== '') {
            $out['code'] = 423;
            $out['reason'] = 'صف مرحل قراءة — كاتبه مصدره القديم (' . $src . ') حتى إقفال القديم (N-04)';
            return $out;
        }
        $state = (string) $c['state'];
        if (EmployeeContractStateMachine::isReadable($state)
            || $state === EmployeeContractStateMachine::SUSPENDED) {
            $out['code'] = 423;
            $out['reason'] = 'لا تعديل مباشرا على عقد نافذ — التغيير بملحق بسريان (H-10)';
            return $out;
        }
        if (!in_array($state, self::EDITABLE_STATES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'العقد في دورة اعتماده (' . EmployeeContractStateMachine::labelAr($state)
                . ') — يعاد إلى المسودة بقرار أو يكمل دورته';
            return $out;
        }

        $upd = array();
        foreach (array('relation_type' => 50, 'currency' => 8) as $f => $len) {
            if (array_key_exists($f, $data)) { $upd[$f] = self::strOrNull($data[$f], $len); }
        }
        foreach (array('start_date', 'end_date', 'probation_end') as $f) {
            if (array_key_exists($f, $data)) { $upd[$f] = self::dateOrNull($data[$f]); }
        }
        if (array_key_exists('category', $data)) {
            $cat = (string) $data['category'];
            if (!in_array($cat, self::CATEGORIES, true)) {
                $out['code'] = 422; $out['reason'] = 'فئة من خارج القائمة: ' . $cat; return $out;
            }
            $upd['category'] = $cat;
        }
        if (array_key_exists('pay_model_id', $data)) {
            $pm = self::payModelOf($conn, (int) $data['pay_model_id']);
            if (!$pm) { $out['code'] = 422; $out['reason'] = 'نموذج أجر من خارج القائمة المحكومة'; return $out; }
            $upd['pay_model_id'] = (int) $pm['id'];
        }
        if (array_key_exists('project_id', $data)) {
            $pid = (int) $data['project_id'];
            if ($pid > 0) {
                $proj = null;
                try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $pid))); }
                catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $proj'); $proj = null; }
                if (!$proj) { $out['code'] = 422; $out['reason'] = 'المشروع غير موجود في نطاقك'; return $out; }
            }
            $upd['project_id'] = $pid > 0 ? $pid : null;
        }
        if (!$upd) { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'لا تغيير'; return $out; }

        $ns = array_key_exists('start_date', $upd) ? $upd['start_date'] : $c['start_date'];
        $ne = array_key_exists('end_date', $upd) ? $upd['end_date'] : $c['end_date'];
        if ($ns !== null && $ne !== null && $ne < $ns) {
            $out['code'] = 422; $out['reason'] = 'نهاية المدة قبل بدايتها'; return $out;
        }
        $clash = self::overlapOf($gate, (int) $c['employee_id'], $ns, $ne, $contractId);
        if ($clash) {
            $out['code'] = 409;
            $out['reason'] = 'مدة متداخلة مع العقد #' . (int) $clash['id'];
            return $out;
        }

        $upd['version'] = (int) $c['version'] + 1;
        $gate->update('employee_contracts', $upd, array('id' => $contractId));

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contracts', 'update', $contractId,
            array_intersect_key($c, $upd), $upd,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * H-10 · رفعُ النسخة الموقَّعة — «ثابتةٌ لا تُستبدل، والتصحيحُ ملحقٌ يوضّح».
     * تُقبل في accepted حصرًا (شرطُ Accepted → Signed) وما دامت فارغة.
     */
    public static function attachSignedFile($conn, $gate, $companyId, $contractId, $fileRef, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $fileRef = trim((string) $fileRef);
        if ($fileRef === '') { $out['code'] = 422; $out['reason'] = 'مرجع الملف إلزامي'; return $out; }
        $c = null;
        try { $c = $gate->selectOne('employee_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $c'); $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقد غير موجود'; return $out; }
        if (trim((string) ($c['source_table'] ?? '')) !== '') {
            $out['code'] = 423; $out['reason'] = 'صف مرحل قراءة — كاتبه مصدره القديم'; return $out;
        }
        if (trim((string) ($c['signed_file_ref'] ?? '')) !== '') {
            $out['code'] = 423;
            $out['reason'] = 'النسخة الموقعة ثابتة لا تستبدل — التصحيح ملحق يوضح (CON-01 §5)';
            return $out;
        }
        if ((string) $c['state'] !== EmployeeContractStateMachine::ACCEPTED) {
            $out['code'] = 422;
            $out['reason'] = 'النسخة ترفع بعد قبول الموظف (accepted) — العقد '
                . EmployeeContractStateMachine::labelAr($c['state']);
            return $out;
        }
        $gate->update('employee_contracts', array('signed_file_ref' => mb_substr($fileRef, 0, 255)),
                      array('id' => (int) $contractId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'employee_contracts', 'attach_signed_file', (int) $contractId,
            array('signed_file_ref' => null), array('signed_file_ref' => $fileRef),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // H-08-② · مكوّناتُ الأجر (CON-01 §3.2) — «عددٌ غيرُ محدودٍ ولا نسبَ
    // مثبَّتةً في الكود»: القوائمُ أدناه أسماءٌ محكومةٌ لا قيمَ فيها إطلاقًا.
    // ═══════════════════════════════════════════════════════════════════════

    /** أنواعُ المكوّن العشرون — قائمةُ §3.2 نصًّا (الرمزُ لاتينيٌّ والتعريبُ هنا). */
    const COMPONENT_TYPES = array(
        'basic' => 'أساسي', 'cost_of_living' => 'غلاء معيشة', 'housing' => 'سكن',
        'transport' => 'نقل', 'food' => 'إعاشة', 'site' => 'موقع', 'hazard' => 'مخاطر',
        'work_nature' => 'طبيعة عمل', 'shift' => 'وردية', 'night' => 'ليلي',
        'responsibility' => 'مسؤولية', 'supervision' => 'إشراف', 'assignment' => 'تكليف',
        'travel' => 'سفر', 'mission' => 'مأمورية', 'communication' => 'اتصال',
        'medical' => 'علاج', 'fixed_bonus' => 'مكافأة ثابتة', 'other_allowance' => 'بدل آخر',
        'custom' => 'مخصّص',
    );

    /** طرقُ الاحتساب العشر — §3.2. */
    const CALC_METHODS = array(
        'fixed_amount' => 'مبلغ ثابت', 'pct_reference' => 'نسبة من المرجعي',
        'pct_basic' => 'نسبة من الأساسي', 'pct_gross' => 'نسبة من الإجمالي',
        'per_day' => 'عن يوم', 'per_shift' => 'عن وردية', 'per_hour' => 'عن ساعة',
        'per_unit' => 'عن وحدة', 'tiers' => 'شرائح', 'custom_formula' => 'معادلة مخصصة',
    );

    /** ما يلزمه مبلغٌ (value) وما يلزمه معدلٌ (rate) — الشرائحُ والمعادلةُ بيانُهما لاحق. */
    const VALUE_METHODS = array('fixed_amount');
    const RATE_METHODS  = array('pct_reference', 'pct_basic', 'pct_gross',
                                'per_day', 'per_shift', 'per_hour', 'per_unit');

    /** أعلامُ الدخول السبعة («خصائصُها السبع» PLAN-01 §5.2-②). */
    const COMPONENT_FLAGS = array('in_insurance', 'in_tax', 'in_leave_pay', 'in_eos',
                                  'in_hour_base', 'in_overtime', 'in_incentive_base');

    const BEARER_TYPES = array('project', 'client_contract', 'dept', 'company');

    /**
     * إضافةُ مكوّنِ أجرٍ — على عقدٍ محرَّرٍ (draft/completed) غيرِ مرحَّل حصرًا.
     * @return array{ok:bool,code:int,reason:string,id:?int}
     */
    public static function addComponent($conn, $gate, $companyId, $contractId, $data, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => null);
        $guard = self::componentContractGuard($gate, $contractId);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }

        $v = self::componentValidate($data, false);
        if (!$v['ok']) { return array_merge($out, $v['err']); }
        $row = $v['row'];
        $row['contract_id'] = (int) $contractId;
        $row['state'] = 'active';
        $row['created_by'] = (int) $actor ?: null;

        $newId = 0;
        try { $newId = (int) $gate->insert('pay_components', $row); }
        catch (\Throwable $t) { $out['code'] = 422; $out['reason'] = 'تعذر الحفظ: ' . $t->getMessage(); return $out; }
        if ($newId <= 0) { $out['code'] = 422; $out['reason'] = 'تعذر الحفظ — افحص القيود'; return $out; }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'pay_components', 'create', $newId,
            array(), $row, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId;
        return $out;
    }

    /** تعديلُ مكوّن — بحراس الإضافة نفسِها. */
    public static function updateComponent($conn, $gate, $companyId, $componentId, $data, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $c = self::componentOf($gate, $componentId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'المكون غير موجود'; return $out; }
        $guard = self::componentContractGuard($gate, (int) $c['contract_id']);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }

        $v = self::componentValidate(array_merge(
            array_intersect_key($c, array_flip(array_merge(
                array('component_type', 'calc_method', 'value', 'rate', 'periodicity',
                      'cost_bearer_type', 'cost_bearer_id', 'cost_center_id',
                      'valid_from', 'valid_to', 'is_variable'),
                self::COMPONENT_FLAGS))),
            $data), true);
        if (!$v['ok']) { return array_merge($out, $v['err']); }
        $upd = $v['row'];

        $gate->update('pay_components', $upd, array('id' => (int) $componentId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'pay_components', 'update', (int) $componentId,
            array_intersect_key($c, $upd), $upd,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** إنهاءُ مكوّن (state=ended + تاريخ) — على المحرَّر حصرًا؛ النافذُ بملحق (H-10). */
    public static function endComponent($conn, $gate, $companyId, $componentId, $endDate, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $c = self::componentOf($gate, $componentId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'المكون غير موجود'; return $out; }
        $guard = self::componentContractGuard($gate, (int) $c['contract_id']);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }
        $d = self::dateOrNull($endDate);
        if ($d === null) { $out['code'] = 422; $out['reason'] = 'تاريخ الإنهاء إلزامي بصيغة سليمة'; return $out; }

        $upd = array('state' => 'ended', 'valid_to' => $d);
        $gate->update('pay_components', $upd, array('id' => (int) $componentId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'pay_components', 'end', (int) $componentId,
            array('state' => $c['state'], 'valid_to' => $c['valid_to']), $upd,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** حارسُ عقدِ المكوّن: موجودٌ · غيرُ مرحَّلٍ (423 بمصدره) · محرَّرٌ (وإلا 423 بملحق). */
    private static function componentContractGuard($gate, $contractId)
    {
        $c = null;
        try { $c = $gate->selectOne('employee_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $c'); $c = null; }
        if (!$c) { return array('ok' => false, 'err' => array('code' => 404, 'reason' => 'العقد غير موجود')); }
        $src = trim((string) ($c['source_table'] ?? ''));
        if ($src !== '') {
            return array('ok' => false, 'err' => array('code' => 423,
                'reason' => 'صف مرحل قراءة — كاتبه مصدره القديم (' . $src . ') حتى إقفال القديم (N-04)'));
        }
        $state = (string) $c['state'];
        if (!in_array($state, self::EDITABLE_STATES, true)) {
            return array('ok' => false, 'err' => array('code' => 423,
                'reason' => 'العقد ' . EmployeeContractStateMachine::labelAr($state)
                    . ' — تغيير المكونات على النافذ بملحق بسريان (H-10)'));
        }
        return array('ok' => true, 'contract' => $c);
    }

    /** تحققُ حقول المكوّن — يعيد صفَّ الكتابة أو الخطأ. */
    private static function componentValidate($data, $isUpdate)
    {
        $type   = isset($data['component_type']) ? (string) $data['component_type'] : '';
        $method = isset($data['calc_method']) ? (string) $data['calc_method'] : '';
        if (!isset(self::COMPONENT_TYPES[$type])) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'نوع مكون من خارج قائمة §3.2 العشرين: ' . $type));
        }
        if (!isset(self::CALC_METHODS[$method])) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'طريقة احتساب من خارج العشر: ' . $method));
        }
        $value = (isset($data['value']) && trim((string) $data['value']) !== '') ? round((float) $data['value'], 2) : null;
        $rate  = (isset($data['rate'])  && trim((string) $data['rate'])  !== '') ? round((float) $data['rate'], 2)  : null;
        if (in_array($method, self::VALUE_METHODS, true) && $value === null) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'طريقة «' . self::CALC_METHODS[$method] . '» تلزم مبلغا (value)'));
        }
        if (in_array($method, self::RATE_METHODS, true) && $rate === null) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'طريقة «' . self::CALC_METHODS[$method] . '» تلزم معدلا (rate)'));
        }
        if ($value !== null && $value < 0) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'المبلغ لا يكون سالبا — الخصوم بيتها M-11 لا المكونات'));
        }
        $from = self::dateOrNull(isset($data['valid_from']) ? $data['valid_from'] : null);
        $to   = self::dateOrNull(isset($data['valid_to']) ? $data['valid_to'] : null);
        if ($from !== null && $to !== null && $to < $from) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'نهاية السريان قبل بدايته'));
        }
        $bearerType = isset($data['cost_bearer_type']) ? trim((string) $data['cost_bearer_type']) : '';
        if ($bearerType !== '' && !in_array($bearerType, self::BEARER_TYPES, true)) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'جهة تحمل من خارج الأربع'));
        }
        $per = isset($data['periodicity']) ? (string) $data['periodicity'] : 'monthly';
        if (!in_array($per, array('monthly', 'periodic', 'once'), true)) { $per = 'monthly'; }

        $row = array(
            'component_type' => $type, 'calc_method' => $method,
            'value' => $value, 'rate' => $rate,
            'is_variable' => !empty($data['is_variable']) ? 1 : 0,
            'periodicity' => $per,
            'cost_bearer_type' => $bearerType !== '' ? $bearerType : null,
            'cost_bearer_id' => !empty($data['cost_bearer_id']) ? (int) $data['cost_bearer_id'] : null,
            'cost_center_id' => !empty($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            'valid_from' => $from, 'valid_to' => $to,
        );
        foreach (self::COMPONENT_FLAGS as $f) { $row[$f] = !empty($data[$f]) ? 1 : 0; }
        return array('ok' => true, 'row' => $row);
    }

    private static function componentOf($gate, $componentId)
    {
        try {
            return $gate->selectOne('pay_components', array('where' => array('id' => (int) $componentId)));
        } catch (\Throwable $t) { return null; }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // H-08-③ · قواعدُ الحوافز وتوزيعُها (CON-01 §3.3 · تدمج M-23)
    // ═══════════════════════════════════════════════════════════════════════

    /** أسسُ الحافز السبعة — §3.3 نصًّا (لا قيمَ فيها). */
    const INCENTIVE_BASES = array(
        'unit' => 'وحدة منفَّذة', 'threshold' => 'تجاوز عتبة', 'quality' => 'جودة',
        'readiness' => 'جاهزية', 'safety' => 'التزام سلامة', 'fuel' => 'توفير وقود',
        'tier' => 'شرائح',
    );

    const INCENTIVE_SCOPES = array('project', 'equipment_type', 'site');
    const BENEFICIARY_TYPES = array('employee', 'job_title');

    /**
     * إضافةُ قاعدةِ حافز — على عقدٍ محرَّرٍ غيرِ مرحَّل حصرًا (حراسُ المكوّنات نفسُها).
     * @return array{ok:bool,code:int,reason:string,id:?int}
     */
    public static function addIncentiveRule($conn, $gate, $companyId, $contractId, $data, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => null);
        $guard = self::componentContractGuard($gate, $contractId);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }

        $v = self::incentiveValidate($data);
        if (!$v['ok']) { return array_merge($out, $v['err']); }
        $row = $v['row'];
        $row['contract_id'] = (int) $contractId;
        $row['state'] = 'active';
        $row['created_by'] = (int) $actor ?: null;

        $newId = 0;
        try { $newId = (int) $gate->insert('incentive_rules', $row); }
        catch (\Throwable $t) { $out['code'] = 422; $out['reason'] = 'تعذر الحفظ: ' . $t->getMessage(); return $out; }
        if ($newId <= 0) { $out['code'] = 422; $out['reason'] = 'تعذر الحفظ — افحص القيود'; return $out; }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'incentive_rules', 'create', $newId,
            array(), $row, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200; $out['id'] = $newId;
        return $out;
    }

    /** إنهاءُ قاعدة (state=ended + تاريخ) — على المحرَّر حصرًا. */
    public static function endIncentiveRule($conn, $gate, $companyId, $ruleId, $endDate, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $r = self::ruleOf($gate, $ruleId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'القاعدة غير موجودة'; return $out; }
        $guard = self::componentContractGuard($gate, (int) $r['contract_id']);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }
        $d = self::dateOrNull($endDate);
        if ($d === null) { $out['code'] = 422; $out['reason'] = 'تاريخ الإنهاء إلزامي'; return $out; }

        $upd = array('state' => 'ended', 'valid_to' => $d);
        $gate->update('incentive_rules', $upd, array('id' => (int) $ruleId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'incentive_rules', 'end', (int) $ruleId,
            array('state' => $r['state'], 'valid_to' => $r['valid_to']), $upd,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * التوزيعُ الذري — «مجموعُ التوزيع مئةٌ بالمئة قيدًا بنيويًّا» (§3.3 · M-23).
     * الاستبدالُ دفعةً واحدة (replaceChildren) — لا يبقى توزيعٌ نصفيٌّ أبدًا،
     * وΣ ≠ 100.00 → **422 بالفارق** (§7.2 · حالة N2) والقائمُ لا يُمسّ.
     * التوزيعُ الغائب (صفرُ صفوف) = 100٪ لصاحب العقد ضمنًا («قد يُوزَّع» — §3.3).
     */
    public static function setIncentiveAllocations($conn, $gate, $companyId, $ruleId, $rows, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $r = self::ruleOf($gate, $ruleId);
        if (!$r) { $out['code'] = 404; $out['reason'] = 'القاعدة غير موجودة'; return $out; }
        $guard = self::componentContractGuard($gate, (int) $r['contract_id']);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }

        $clean = array(); $sum = 0.0; $seen = array();
        foreach ((array) $rows as $i => $row) {
            $bt = isset($row['beneficiary_type']) ? (string) $row['beneficiary_type'] : '';
            $bid = isset($row['beneficiary_id']) ? (int) $row['beneficiary_id'] : 0;
            $pct = isset($row['percent']) ? round((float) $row['percent'], 2) : 0.0;
            if (!in_array($bt, self::BENEFICIARY_TYPES, true) || $bid <= 0) {
                $out['code'] = 422; $out['reason'] = 'مستفيد السطر ' . ($i + 1) . ' ناقص النوع أو المعرف'; return $out;
            }
            if ($pct <= 0 || $pct > 100) {
                $out['code'] = 422; $out['reason'] = 'نسبة السطر ' . ($i + 1) . ' خارج (0، 100]'; return $out;
            }
            $key = $bt . '#' . $bid;
            if (isset($seen[$key])) { $out['code'] = 422; $out['reason'] = 'مستفيد مكرر: ' . $key; return $out; }
            $seen[$key] = true;
            $sum = round($sum + $pct, 2);
            $clean[] = array('beneficiary_type' => $bt, 'beneficiary_id' => $bid, 'percent' => $pct);
        }
        if ($clean && $sum !== 100.00) {
            // 422 **بالفارق** — نصُّ §7.2 حرفيًّا (N2: توزيعٌ 80/30 يُرفض ولا يُحفظ)
            $out['code'] = 422;
            $out['reason'] = 'مجموع التوزيع ' . number_format($sum, 2) . '٪ لا 100٪ — الفارق '
                . number_format(round(100 - $sum, 2), 2) . '٪';
            return $out;
        }

        try {
            $gate->replaceChildren('incentive_rules', (int) $ruleId, 'incentive_allocations', 'rule_id',
                $clean, 'H-08-3 incentive allocations Σ=100');
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الاستبدال الذري: ' . $t->getMessage(); return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'incentive_allocations', 'replace', (int) $ruleId,
            array(), array('rows' => $clean, 'sum' => $clean ? $sum : 'ضمني 100 لصاحب العقد'),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** تحققُ قاعدة الحافز. */
    private static function incentiveValidate($data)
    {
        $basis = isset($data['basis']) ? (string) $data['basis'] : '';
        if (!isset(self::INCENTIVE_BASES[$basis])) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'أساس من خارج السبعة (§3.3): ' . $basis));
        }
        $type = trim((string) (isset($data['incentive_type']) ? $data['incentive_type'] : ''));
        if ($type === '') {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'اسم الحافز إلزامي'));
        }
        $num = function ($k) use ($data) {
            return (isset($data[$k]) && trim((string) $data[$k]) !== '') ? round((float) $data[$k], 4) : null;
        };
        $rate = $num('rate'); $threshold = $num('threshold');
        $cap = $num('cap'); $floor = $num('floor');
        if ($rate !== null && $rate < 0) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'المعدل لا يكون سالبا'));
        }
        if ($cap !== null && $floor !== null && $cap < $floor) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'السقف دون الحد الأدنى'));
        }
        $from = self::dateOrNull(isset($data['valid_from']) ? $data['valid_from'] : null);
        $to   = self::dateOrNull(isset($data['valid_to']) ? $data['valid_to'] : null);
        if ($from !== null && $to !== null && $to < $from) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'نهاية السريان قبل بدايته'));
        }
        $scopeType = isset($data['scope_type']) ? trim((string) $data['scope_type']) : '';
        if ($scopeType !== '' && !in_array($scopeType, self::INCENTIVE_SCOPES, true)) {
            return array('ok' => false, 'err' => array('code' => 422, 'reason' => 'نطاق من خارج الثلاثة (مشروع · نوع معدة · موقع)'));
        }
        $per = isset($data['periodicity']) ? (string) $data['periodicity'] : 'monthly';
        if (!in_array($per, array('monthly', 'periodic', 'once'), true)) { $per = 'monthly'; }

        return array('ok' => true, 'row' => array(
            'incentive_type' => mb_substr($type, 0, 50),
            'basis' => $basis, 'rate' => $rate, 'threshold' => $threshold,
            'cap' => $cap, 'floor' => $floor, 'periodicity' => $per,
            'condition_text' => self::strOrNull(isset($data['condition_text']) ? $data['condition_text'] : null, 255),
            'scope_type' => $scopeType !== '' ? $scopeType : null,
            'scope_id' => !empty($data['scope_id']) ? (int) $data['scope_id'] : null,
            'valid_from' => $from, 'valid_to' => $to,
        ));
    }

    private static function ruleOf($gate, $ruleId)
    {
        try {
            return $gate->selectOne('incentive_rules', array('where' => array('id' => (int) $ruleId)));
        } catch (\Throwable $t) { return null; }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // H-08-④ · جهاتُ التحمّل Σ=100 لكل مالكٍ (CON-01 §3.3 · خاتمةُ H-08)
    // ═══════════════════════════════════════════════════════════════════════

    const OWNER_TYPES = array('component', 'rule');
    /** جهاتُ §3.3 الأربع — company (صاحبُ العمل) بلا معرّف. */
    const COST_BEARER_TYPES = array('project' => 'مشروع', 'client_contract' => 'عقد عميل',
                                    'dept' => 'إدارة داخلية', 'company' => 'كيان الشركة');

    /**
     * حفظُ جهات التحمّل دفعةً ذرية — «مجموعُ نسب التحمّل لكل عنصرٍ مئةٌ بالمئة»
     * وΣ ≠ 100.00 → **422 بالفارق ولا حفظَ جزئيًّا** (رفضُ الحفظ بنص PLAN-01 §5.2-④).
     * الاستبدالُ يطوي القديمَ ناعمًا ويدرج الجديدَ في معاملةٍ واحدة.
     */
    public static function setCostBearers($conn, $gate, $companyId, $ownerType, $ownerId, $rows, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $ownerType = (string) $ownerType; $ownerId = (int) $ownerId;
        if (!in_array($ownerType, self::OWNER_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'مالك التحمل مكون أو قاعدة حصرا'; return $out;
        }
        $owner = $ownerType === 'component' ? self::componentOf($gate, $ownerId) : self::ruleOf($gate, $ownerId);
        if (!$owner) { $out['code'] = 404; $out['reason'] = 'مالك التحمل غير موجود'; return $out; }
        $guard = self::componentContractGuard($gate, (int) $owner['contract_id']);
        if (!$guard['ok']) { return array_merge($out, $guard['err']); }

        // التحققُ كاملًا قبل أي كتابة — لا حفظَ جزئيًّا
        $clean = array(); $sum = 0.0; $seen = array();
        foreach ((array) $rows as $i => $row) {
            $bt = isset($row['bearer_type']) ? (string) $row['bearer_type'] : '';
            $bid = !empty($row['bearer_id']) ? (int) $row['bearer_id'] : null;
            $pct = isset($row['percent']) ? round((float) $row['percent'], 2) : 0.0;
            if (!isset(self::COST_BEARER_TYPES[$bt])) {
                $out['code'] = 422; $out['reason'] = 'جهة السطر ' . ($i + 1) . ' من خارج الأربع (§3.3)'; return $out;
            }
            if ($bt !== 'company' && ($bid === null || $bid <= 0)) {
                $out['code'] = 422; $out['reason'] = 'جهة «' . self::COST_BEARER_TYPES[$bt] . '» تلزم معرفا'; return $out;
            }
            if ($bt === 'company') { $bid = null; }
            if ($pct <= 0 || $pct > 100) {
                $out['code'] = 422; $out['reason'] = 'نسبة السطر ' . ($i + 1) . ' خارج (0، 100]'; return $out;
            }
            // المشروعُ وعقدُ العميل من نطاق الشركة (البوابةُ ترفض الأجنبي)
            if ($bt === 'project' || $bt === 'client_contract') {
                $tbl = $bt === 'project' ? 'project' : 'contracts';
                $ref = null;
                try { $ref = $gate->selectOne($tbl, array('columns' => array('id'), 'where' => array('id' => $bid))); }
                catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $ref'); $ref = null; }
                if (!$ref) {
                    $out['code'] = 422; $out['reason'] = self::COST_BEARER_TYPES[$bt] . ' #' . $bid . ' غير موجود في نطاقك'; return $out;
                }
            }
            $key = $bt . '#' . ($bid === null ? '0' : $bid);
            if (isset($seen[$key])) { $out['code'] = 422; $out['reason'] = 'جهة مكررة: ' . $key; return $out; }
            $seen[$key] = true;
            $sum = round($sum + $pct, 2);
            $clean[] = array('bearer_type' => $bt, 'bearer_id' => $bid, 'percent' => $pct);
        }
        if ($clean && $sum !== 100.00) {
            $out['code'] = 422;
            $out['reason'] = 'مجموع التحمل ' . number_format($sum, 2) . '٪ لا 100٪ — الفارق '
                . number_format(round(100 - $sum, 2), 2) . '٪ (يرفض الحفظ — PLAN-01 §5.2-④)';
            return $out;
        }

        try {
            $gate->runInTransaction(function ($g) use ($ownerType, $ownerId, $clean, $actor) {
                // طيُّ القائم ناعمًا (لا محوَ لقرار تحميلٍ سابق) ثم إدراجُ الجديد
                $existing = $g->scopedQuery(array('scope' => array('cb' => 'cost_bearers')),
                    "SELECT cb.id FROM cost_bearers cb
                     WHERE {TENANT_SCOPE} AND cb.owner_type = ? AND cb.owner_id = ?
                       AND COALESCE(cb.is_deleted,0)=0", array($ownerType, $ownerId));
                foreach ($existing as $ex) { $g->softDelete('cost_bearers', (int) $ex['id']); }
                foreach ($clean as $row) {
                    $row['owner_type'] = $ownerType; $row['owner_id'] = $ownerId;
                    $row['created_by'] = (int) $actor ?: null;
                    $g->insert('cost_bearers', $row);
                }
                return true;
            }, 'H-08-4 cost bearers Σ=100');
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الاستبدال الذري: ' . $t->getMessage(); return $out;
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'cost_bearers', 'replace', $ownerId,
            array('owner' => $ownerType . '#' . $ownerId),
            array('rows' => $clean, 'sum' => $clean ? $sum : 'غائب — إشارة المالك المفردة/جهة العقد'),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** جهاتُ تحمّل مالكٍ — الحيّةُ وحدَها. */
    public static function costBearersOf($gate, $ownerType, $ownerId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('cb' => 'cost_bearers')),
                "SELECT cb.* FROM cost_bearers cb
                 WHERE {TENANT_SCOPE} AND cb.owner_type = ? AND cb.owner_id = ?
                   AND COALESCE(cb.is_deleted,0)=0 ORDER BY cb.percent DESC",
                array((string) $ownerType, (int) $ownerId));
        } catch (\Throwable $t) { return array(); }
    }

    // ═══════════════════════════════════════════════════════════════════════

    /**
     * التداخل: عقدٌ للشخص نفسه في الكيان نفسه (نطاقُ البوابة يحصر الكيان)
     * بمدةٍ تتقاطع — والحالاتُ غيرُ الحاجزة (مرفوض/معتذَر/منتهٍ المصفّى...) لا تُحتسب.
     * NULL نهايةً = مفتوح؛ NULL بدايةً لا يحجز (رأسٌ بلا مدةٍ بعد).
     */
    private static function overlapOf($gate, $employeeId, $start, $end, $excludeId)
    {
        if ($start === null) { return null; }
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('ec' => 'employee_contracts')),
                "SELECT ec.id, ec.state, ec.start_date, ec.end_date
                   FROM employee_contracts ec
                  WHERE {TENANT_SCOPE}
                    AND ec.employee_id = ?
                    AND ec.id <> ?
                    AND COALESCE(ec.is_deleted, 0) = 0
                    AND ec.start_date IS NOT NULL
                    AND ec.state NOT IN ('" . implode("','", self::NON_BLOCKING_STATES) . "')
                    AND (? IS NULL OR ec.start_date <= ?)
                    AND (ec.end_date IS NULL OR ec.end_date >= ?)
                  ORDER BY ec.id LIMIT 1",
                array((int) $employeeId, (int) $excludeId, $end, $end, $start));
        } catch (\Throwable $t) {
            error_log('EmployeeContractService overlapOf: ' . $t->getMessage());
            return null;
        }
        return $rows ? $rows[0] : null;
    }

    private static function payModelOf($conn, $payModelId)
    {
        $payModelId = (int) $payModelId;
        if ($payModelId <= 0) { return null; }
        $st = $conn->prepare("SELECT id, code FROM pay_models WHERE id = ? AND is_active = 1 LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('i', $payModelId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }

    private static function strOrNull($v, $len)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }  // ENUM/الأعمدةُ تبتلع '' صامتًا — NULL صراحةً
        return mb_substr($v, 0, $len);
    }
}
