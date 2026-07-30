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

        if ($employeeId <= 0) { $out['code'] = 422; $out['reason'] = 'الشخصُ إلزامي — «العقدُ يشير إلى سجل الأشخاص»'; return $out; }
        if (!in_array($category, self::CATEGORIES, true)) {
            $out['code'] = 422; $out['reason'] = 'فئةٌ من خارج قائمة CON-01 §2: ' . $category; return $out;
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $out['code'] = 422; $out['reason'] = 'نهايةُ المدة قبل بدايتها'; return $out;
        }

        // الشخصُ من نطاق الشركة (البوابةُ ترفض الأجنبي)
        $emp = null;
        try { $emp = $gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $employeeId))); }
        catch (\Throwable $t) { $emp = null; }
        if (!$emp) { $out['code'] = 422; $out['reason'] = 'الشخصُ غير موجودٍ في نطاقك'; return $out; }

        // «نموذجٌ غيرُ مذكورٍ في القائمة الخمس عشرة → 422» — الكتالوجُ المحكوم حصرًا
        $pm = self::payModelOf($conn, $payModelId);
        if (!$pm) { $out['code'] = 422; $out['reason'] = 'نموذجُ أجرٍ من خارج القائمة الخمس عشرة المحكومة'; return $out; }

        // المشروعُ من النطاق إن حُدّد
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        if ($projectId > 0) {
            $proj = null;
            try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $projectId))); }
            catch (\Throwable $t) { $proj = null; }
            if (!$proj) { $out['code'] = 422; $out['reason'] = 'المشروعُ غير موجودٍ في نطاقك'; return $out; }
        }

        // «عقدٌ متداخلُ المدة للشخص نفسه في الكيان نفسه → 409 بمرجع القائم»
        $clash = self::overlapOf($gate, $employeeId, $startDate, $endDate, 0);
        if ($clash) {
            $out['code'] = 409;
            $out['reason'] = 'مدةٌ متداخلةٌ مع العقد #' . (int) $clash['id']
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
            $out['reason'] = 'تعذّر الإنشاء — عقدٌ بالبداية نفسها للشخص نفسه قائم؟ (' . $t->getMessage() . ')';
            return $out;
        }
        if ($newId <= 0) {
            // گوتشا مقيسة: mysqli لا يرمي — خرقُ قيدٍ يعود false صامتًا
            $out['code'] = 409; $out['reason'] = 'تعذّر الإنشاء — قيدُ التفرد (الشخصُ × الكيانُ × البداية)';
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
        catch (\Throwable $t) { $c = null; }
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غير موجود'; return $out; }

        $src = trim((string) ($c['source_table'] ?? ''));
        if ($src !== '') {
            $out['code'] = 423;
            $out['reason'] = 'صفٌّ مرحَّلٌ قراءةً — كاتبُه مصدرُه القديم (' . $src . ') حتى إقفال القديم (N-04)';
            return $out;
        }
        $state = (string) $c['state'];
        if (EmployeeContractStateMachine::isReadable($state)
            || $state === EmployeeContractStateMachine::SUSPENDED) {
            $out['code'] = 423;
            $out['reason'] = 'لا تعديلَ مباشرًا على عقدٍ نافذ — التغييرُ بملحقٍ بسريان (H-10)';
            return $out;
        }
        if (!in_array($state, self::EDITABLE_STATES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'العقدُ في دورة اعتماده (' . EmployeeContractStateMachine::labelAr($state)
                . ') — يُعاد إلى المسودة بقرارٍ أو يُكمل دورتَه';
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
                $out['code'] = 422; $out['reason'] = 'فئةٌ من خارج القائمة: ' . $cat; return $out;
            }
            $upd['category'] = $cat;
        }
        if (array_key_exists('pay_model_id', $data)) {
            $pm = self::payModelOf($conn, (int) $data['pay_model_id']);
            if (!$pm) { $out['code'] = 422; $out['reason'] = 'نموذجُ أجرٍ من خارج القائمة المحكومة'; return $out; }
            $upd['pay_model_id'] = (int) $pm['id'];
        }
        if (array_key_exists('project_id', $data)) {
            $pid = (int) $data['project_id'];
            if ($pid > 0) {
                $proj = null;
                try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $pid))); }
                catch (\Throwable $t) { $proj = null; }
                if (!$proj) { $out['code'] = 422; $out['reason'] = 'المشروعُ غير موجودٍ في نطاقك'; return $out; }
            }
            $upd['project_id'] = $pid > 0 ? $pid : null;
        }
        if (!$upd) { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'لا تغيير'; return $out; }

        $ns = array_key_exists('start_date', $upd) ? $upd['start_date'] : $c['start_date'];
        $ne = array_key_exists('end_date', $upd) ? $upd['end_date'] : $c['end_date'];
        if ($ns !== null && $ne !== null && $ne < $ns) {
            $out['code'] = 422; $out['reason'] = 'نهايةُ المدة قبل بدايتها'; return $out;
        }
        $clash = self::overlapOf($gate, (int) $c['employee_id'], $ns, $ne, $contractId);
        if ($clash) {
            $out['code'] = 409;
            $out['reason'] = 'مدةٌ متداخلةٌ مع العقد #' . (int) $clash['id'];
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
