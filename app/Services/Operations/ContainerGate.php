<?php
/**
 * app/Services/Operations/ContainerGate.php — H-01 المرحلة ③
 * ═══════════════════════════════════════════════════════════════════════════
 * **«لا تُسجَّل وحدةٌ في موقعٍ لم تكتمل حاوياتُه»** (OPM-01 §4 · شرطُ بدء التشغيل).
 *
 * ── العلَمُ قائمةُ مواقعَ لا `on/off` ────────────────────────────────────────
 * `EMS_CONTAINER_GATE` قيمتُه **قائمةُ `project_id`** تُفعَّل عليها (فارغٌ = مطفأ)،
 * على نمط `TENANT_ENFORCE_PATHS`. مثال: `EMS_CONTAINER_GATE=4`.
 *
 * والسببُ مقيسٌ لا احتياطيّ: **صفرُ حاويةٍ كانت موجودةً قبل المرحلة ②**، وإنفاذُ
 * الحجب على الجميع اليومَ يوقف **كلَّ إدخال تايم شيت** في المواقع التي لم تُبنَ
 * حاوياتُها بعد (المشروعان 1 و2 مثلًا). وهو **فخُّ E-08 نفسُه** (138 من 138)، وقد
 * نجحت معالجتُه بالعلَم. **موقعٌ واحدٌ أولًا · أسبوعُ رصد · ثم التالي.**
 *
 * ── الرسالةُ تشرح الناقصَ **بروابطه** ──────────────────────────────────────
 * «رسالةٌ بلا رابطٍ تُوقف الميدانَ بلا مخرج»: فكلُّ سببٍ يحمل وجهتَه — الحاويةُ
 * الناقصةُ إلى شاشة الحاويات على عقدها، والمشغّلُ الغائبُ إلى موضع تعيينه.
 * وهو **درسُ E-08-أ**: ردٌّ صحيحٌ لا يراه أحدٌ ليس حارسًا.
 *
 * ── وملاحظةٌ مرفوعةٌ لا معطِّلة ─────────────────────────────────────────────
 * الحجبُ **عند الإدخال** يمنع تسجيلَ واقعةٍ **وقعت فعلًا** لأن إداريًّا لم يُكمل
 * توزيعًا — وسابقةُ المشروع عكسُها (`CapacityGuard`: «التجاوزُ لا يُمنع إدخالُه
 * ويُمنع اعتمادُه»). نُفِّذ قرارُ المالك كما هو، **والرصدُ أسبوعًا على الموقع
 * الأول**: إن ظهرت وقائعُ تُسجَّل خارجَ النظام فالقياسُ يُرفع ليُقرَّر نقلُ الحجب
 * إلى الاعتماد.
 */

namespace App\Services\Operations;

require_once __DIR__ . '/OperationalTransformService.php';

use App\Services\Operations\OperationalTransformService as OTS;

class ContainerGate
{
    /**
     * المواقعُ المفعَّلُ عليها الحجب — قائمةٌ لا مفتاحٌ ثنائيّ.
     * @return int[] فارغةٌ = الحارسُ مطفأ
     */
    public static function enabledSites()
    {
        $raw = function_exists('ems_env') ? (string) ems_env('EMS_CONTAINER_GATE', '') : '';
        $raw = trim($raw);
        if ($raw === '') { return array(); }
        $out = array();
        foreach (explode(',', $raw) as $p) {
            $p = (int) trim($p);
            if ($p > 0) { $out[] = $p; }
        }
        return $out;
    }

    /** هل الموقعُ محكومٌ بالحارس؟ */
    public static function isEnabledFor($projectId)
    {
        $sites = self::enabledSites();
        return !empty($sites) && in_array((int) $projectId, $sites, true);
    }

    /**
     * H-01-③ (فتحُ الرائد): وضعُ البوابة — `EMS_CONTAINER_GATE_MODE`.
     * enforce (الافتراضُ — دلالةُ القائمة كما صُمّمت) · monitor = يفحص ويسجّل
     * would-block **مهيكلًا** (موصل N-02) ويمرّر — نمطُ TenantDb/CSRF المجرَّب
     * وقاعدةُ N-04 §2-④: رصدُ أسبوعِ الرائد قبل قلب الحجب.
     */
    public static function mode()
    {
        $m = function_exists('ems_env') ? strtolower(trim((string) ems_env('EMS_CONTAINER_GATE_MODE', 'enforce'))) : 'enforce';
        return $m === 'monitor' ? 'monitor' : 'enforce';
    }

    /**
     * فحصُ جاهزية سلسلةِ حاوياتِ واقعةٍ **قبل تسجيلها**.
     *
     * الشروطُ الثلاثةُ بنصّ الوثيقة، وكلٌّ برابطه:
     *   ① **حاويةٌ ناقصة**: لا حاويةَ مشغّلٍ تربط (المشغّل × المعدة) تحت عقدها.
     *   ② **معدةٌ بلا مشغّل**: الواقعةُ بلا `operator_employee_id` أصلًا.
     *   ③ **مشغّلٌ بلا دورة تناوب**: حاويتُه قائمةٌ ولا `operator_rotations` لها.
     *
     * @param array $ctx {project_id, contract_id, equipment_id, operator_employee_id, unit_type, entry_date}
     * @return array{ok:bool,code:int,reasons:array,blocked:bool,container_id:?int}
     */
    public static function assertReady($gate, array $ctx)
    {
        $out = array('ok' => true, 'code' => 200, 'reasons' => array(),
                     'blocked' => false, 'container_id' => null);

        $projectId  = isset($ctx['project_id']) ? (int) $ctx['project_id'] : 0;
        $contractId = isset($ctx['contract_id']) ? (int) $ctx['contract_id'] : 0;
        $equipId    = isset($ctx['equipment_id']) ? (int) $ctx['equipment_id'] : 0;
        $opId       = isset($ctx['operator_employee_id']) ? (int) $ctx['operator_employee_id'] : 0;
        $unit       = isset($ctx['unit_type']) ? (string) $ctx['unit_type'] : 'hour';

        if (!self::isEnabledFor($projectId)) { return $out; }   // الموقعُ خارج العلَم

        $reasons = array();

        // ② معدةٌ بلا مشغّل — يُفحص أولًا: بدونه لا سلسلةَ أصلًا
        if ($opId <= 0) {
            $reasons[] = array(
                'kind'  => 'no_operator',
                'text'  => 'معدةٌ بلا مشغّل — لا تُسجَّل وحدةٌ بلا صاحبِ ساعاتها',
                'href'  => '../Oprators/select_project.php',
                'label' => 'عيِّن مشغّلًا للمعدة',
            );
        }

        // ① حاويةُ المشغّل تحت معدته في عقد الواقعة
        $leaf = null;
        if ($opId > 0 && $equipId > 0 && $contractId > 0) {
            $leaf = self::leafFor($gate, $contractId, $equipId, $opId, $unit);
            if ($leaf === null) {
                $reasons[] = array(
                    'kind'  => 'no_container',
                    'text'  => 'حاويةٌ ناقصة — لا حصةَ لهذا المشغّل على هذه المعدة في حاويات العقد',
                    'href'  => '../Operations/containers.php?contract=' . $contractId,
                    'label' => 'افتح حاويات العقد ووزّع الحصة',
                );
            }
        }

        // ③ مشغّلٌ بلا دورة تناوب
        if ($leaf !== null) {
            $out['container_id'] = (int) $leaf['id'];
            if (!self::hasRotation($gate, (int) $leaf['id'])) {
                $reasons[] = array(
                    'kind'  => 'no_rotation',
                    'text'  => 'مشغّلٌ بلا دورة تناوب — لا يُعرف أهو مناوبٌ هذا اليوم أم في راحته',
                    'href'  => '../Operations/containers.php?contract=' . $contractId,
                    'label' => 'سجّل دورةَ تناوبه',
                );
            }
        }

        // ④ H-03 (OPM-01 §6-⑥): «تخصيصُ المواقع والورديات» — موقعٌ بلا خطةِ
        //   يومٍ مفتوحةٍ ناقصُ التخصيص. خلف العلمِ والوضعِ نفسِهما (يُرصد أسبوعًا).
        if ($projectId > 0 && !empty($ctx['entry_date'])) {
            require_once __DIR__ . '/DailyPlanService.php';
            if (!DailyPlanService::hasOpenPlan($gate, $projectId, (string) $ctx['entry_date'])) {
                $reasons[] = array(
                    'kind'  => 'no_open_plan',
                    'text'  => 'موقعٌ بلا خطةِ يومٍ مفتوحة — «لا يُفتح تسجيلٌ لموقعٍ ناقص التخصيص»',
                    'href'  => '../Operations/daily_plan.php?project=' . $projectId,
                    'label' => 'افتح خطةَ اليوم (توليدٌ ← توزيعٌ ← اعتمادٌ ← فتح)',
                );
            }
        }

        if (empty($reasons)) { return $out; }

        // وضعُ الرصد: يُسجَّل ما كان سيُحجب (مهيكلًا — يقرؤه تقريرُ المطابقة
        // الأسبوعي §13-①) وتمرّ الواقعة — الميدانُ لا يقف أثناء أسبوع الرائد.
        if (self::mode() === 'monitor') {
            self::logWouldBlock($ctx, $reasons);
            $out['monitored'] = true;
            return $out;   // ok=true — مرورٌ مرصود
        }

        $out['ok'] = false; $out['code'] = 422; $out['blocked'] = true;
        $out['reasons'] = $reasons;
        return $out;
    }

    /** تسجيلُ would-block مهيكلًا (activity_logs عبر موصل N-02) — لا يرمي. */
    private static function logWouldBlock(array $ctx, array $reasons)
    {
        try {
            require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
            $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
            if (!$conn) { return; }
            $kinds = array();
            foreach ($reasons as $r) { $kinds[] = (string) $r['kind']; }
            ems_audit_change($conn, 'operations', 'container_gate', 'would_block',
                (int) ($ctx['equipment_id'] ?? 0),
                array(), array(
                    'kinds'      => implode(',', $kinds),
                    'reasons'    => self::flatten($reasons),
                    'project_id' => (int) ($ctx['project_id'] ?? 0),
                    'operator'   => (int) ($ctx['operator_employee_id'] ?? 0),
                    'entry_date' => (string) ($ctx['entry_date'] ?? ''),
                ),
                array(
                    'company_id'  => (int) ($ctx['company_id'] ?? 0),
                    'user_id'     => 0,
                    'contract_id' => (int) ($ctx['contract_id'] ?? 0),
                ));
        } catch (\Throwable $t) {
            error_log('ContainerGate logWouldBlock: ' . $t->getMessage());
        }
        if (function_exists('log_security_event')) {
            log_security_event('container_gate_would_block',
                'project=' . (int) ($ctx['project_id'] ?? 0) . ' :: ' . implode(' | ', self::flatten($reasons)));
        }
    }

    /** نصٌّ مسطَّحٌ للأسباب — لمن لا يستطيع عرضَ الروابط (سجلٌّ · رسالةٌ قصيرة). */
    public static function flatten(array $reasons)
    {
        $out = array();
        foreach ($reasons as $r) {
            $out[] = (string) $r['text'] . ' → ' . (string) $r['label'];
        }
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════

    /** حاويةُ المشغّل تحت معدته — شرطُ الاستهلاك ومفتاحُ السلسلة. */
    public static function leafFor($gate, $contractId, $equipmentId, $operatorId, $unitType = 'hour')
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('o' => 'op_containers'), 'enrich' => array('e' => 'op_containers')),
                "SELECT o.* FROM op_containers o
                   LEFT JOIN op_containers e ON e.id = o.parent_id
                  WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
                    AND o.level = 'مشغّل' AND o.state = 'نشطة'
                    AND o.contract_id = ? AND o.unit_type = ?
                    AND o.operator_employee_id = ? AND e.equipment_id = ?
                  ORDER BY o.id LIMIT 1",
                array((int) $contractId, (string) $unitType, (int) $operatorId, (int) $equipmentId));
            return empty($rows) ? null : $rows[0];
        } catch (\Throwable $t) {
            error_log('ContainerGate leafFor: ' . $t->getMessage());
            return null;
        }
    }

    private static function hasRotation($gate, $containerId)
    {
        try {
            $r = $gate->selectOne('operator_rotations', array(
                'whereRaw' => 'container_id = ?', 'params' => array((int) $containerId)));
            return $r !== null;
        } catch (\Throwable $t) { return false; }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // الاستهلاك — **عند اعتماد الموقع لا عند الإدخال**
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * خصمُ واقعةٍ أُقرّت من سلسلة حاوياتها.
     *
     * «الاستهلاكُ يقع عند اعتماد الموقع لا عند الإدخال» — الواقعةُ أُقرّت فحينئذٍ
     * تُخصم. ومفتاحُ العطالة يحمل **الجولة**: `entry:{id}:r{round}` — فإعادةٌ
     * ثم اعتمادٌ جديدٌ خصمٌ جديدٌ بمفتاحٍ جديد، والاعتمادُ المكرَّرُ في الجولة
     * نفسِها لا يخصم مرتين.
     *
     * @return array{ok:bool,skipped:bool,reason:string,levels:int}
     */
    public static function consumeForEntry($conn, $gate, $companyId, array $entry)
    {
        $out = array('ok' => true, 'skipped' => true, 'reason' => '', 'levels' => 0);
        $projectId = isset($entry['project_id']) ? (int) $entry['project_id'] : 0;
        if (!self::isEnabledFor($projectId)) {
            $out['reason'] = 'الموقعُ خارج العلَم — لا استهلاك'; return $out;
        }

        $leaf = self::leafFor($gate, (int) $entry['contract_id'], (int) $entry['equipment_id'],
                              (int) $entry['operator_employee_id'], (string) $entry['unit_type']);
        if ($leaf === null) {
            $out['reason'] = 'لا حاويةَ لهذه الواقعة — لا يُخصم من عدم'; return $out;
        }

        $round = isset($entry['current_round']) ? (int) $entry['current_round'] : 1;
        $key = 'entry:' . (int) $entry['id'] . ':r' . $round;
        $r = OTS::consume($conn, $gate, $companyId, (int) $leaf['id'],
            round((float) $entry['qty'], 2), $key, array(
                'source_kind' => 'unit_entry', 'source_ref' => (int) $entry['id'],
                'unit_type'   => (string) $entry['unit_type'],
                'consumed_on' => (string) $entry['entry_date'],
                'note'        => 'اعتمادُ الموقع — الجولة ' . $round,
            ));
        $out['ok'] = !empty($r['ok']);
        $out['skipped'] = !empty($r['existing']);
        $out['reason'] = $r['reason'];
        $out['levels'] = isset($r['levels']) ? (int) $r['levels'] : 0;
        return $out;
    }

    /**
     * ردُّ استهلاكِ واقعةٍ أُعيدت — **حركةٌ عاكسةٌ لا حذف** (القاعدة ①).
     * المفتاحُ مشتقٌّ من مفتاح الخصم بلاحقة `:rev` فلا يُردُّ مرتين.
     */
    public static function reverseForEntry($conn, $gate, $companyId, array $entry, $round)
    {
        $out = array('ok' => true, 'skipped' => true, 'reason' => '');
        $projectId = isset($entry['project_id']) ? (int) $entry['project_id'] : 0;
        if (!self::isEnabledFor($projectId)) { return $out; }

        $key = 'entry:' . (int) $entry['id'] . ':r' . (int) $round;
        // لا يُردُّ ما لم يُخصم: العكسُ يتبع أصلًا موجودًا
        try {
            $orig = $gate->selectOne('container_consumption', array(
                'whereRaw' => 'idem_key = ?', 'params' => array($key)));
        } catch (\Throwable $t) { $orig = null; }
        if (!$orig) { $out['reason'] = 'لا خصمَ لهذه الجولة — لا شيءَ يُردّ'; return $out; }

        $r = OTS::consume($conn, $gate, $companyId, (int) $orig['container_id'],
            -1 * round((float) $orig['qty'], 2), $key . ':rev', array(
                'source_kind' => 'unit_entry', 'source_ref' => (int) $entry['id'],
                'unit_type'   => (string) $orig['unit_type'],
                'consumed_on' => date('Y-m-d'),
                'note'        => 'ردُّ استهلاكٍ بإعادة الواقعة — الجولة ' . (int) $round,
            ));
        $out['ok'] = !empty($r['ok']);
        $out['skipped'] = !empty($r['existing']);
        $out['reason'] = $r['reason'];
        return $out;
    }
}
