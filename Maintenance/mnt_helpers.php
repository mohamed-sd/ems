<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 * Maintenance module — shared helper functions (دوال قسم الصيانة المشتركة)
 * ════════════════════════════════════════════════════════════════════════════
 * يحتوي دوالاً نقيّة (بلا أي إخراج HTML) تُضمّن بعد config.php في كل شاشات الصيانة.
 * كل الدوال تحترم عزل الشركة (company_id) وتستخدم prepared statements للكتابة.
 *
 * مبادئ منطق الحالة (القسم 6 من المواصفات — لا تخالفها):
 *   • الصيانة لا تكتب equipments.availability_status عند الدخول إطلاقاً
 *     (دخول «تحت الصيانة» حصري لمسار move_oprators لمدير الحركة والتشغيل).
 *   • عند فتح أمر صيانة: تُضبط operations.equipment_health='معطلة' للتشغيل الساري فقط.
 *   • عند إغلاق أمر صيانة: equipment_health='سليمة' + availability_status='متاحة للعمل'
 *     + last_maintenance_date=اليوم. ولا تُمسّ operations.status (التشغيل) أبداً.
 */

if (!defined('MNT_HELPERS_LOADED')) {
    define('MNT_HELPERS_LOADED', true);

    /**
     * توليد مرجع تسلسلي مقيّد بالشركة، مثل BR-2026-0001 / MNT-2026-0007.
     *
     * @param mysqli $conn
     * @param string $table       اسم جدول الصيانة (يُمرّر داخلياً فقط — قائمة بيضاء)
     * @param string $prefix      البادئة (BR/MNT/INS/PLN)
     * @param int    $company_id  معرّف الشركة
     * @return string
     */
    function mnt_next_code($conn, $table, $prefix, $company_id)
    {
        // قائمة بيضاء صارمة لأسماء الجداول (لا تُبنى من مدخلات المستخدم).
        $allowed = array('mnt_breakdown', 'mnt_order', 'mnt_inspection', 'mnt_plan');
        if (!in_array($table, $allowed, true)) {
            return $prefix . '-' . date('Y') . '-0001';
        }

        $year = date('Y');

        // العدّاد = عدّ كل الأكواد لنفس البادئة/السنة/الشركة + 1. includeDeleted لمطابقة
        // الأصل (بلا فلتر is_deleted) — المؤرشَف يشغل رقمه فلا يتكرّر الكود.
        $like = $prefix . '-' . $year . '-%';
        $c = ems_tenant_db()->count($table, array('whereRaw' => 'code LIKE ?', 'params' => array($like), 'includeDeleted' => true));
        $next = $c + 1;

        return $prefix . '-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * فتح أمر صيانة ⇐ ضبط الصحة الفنية «معطلة» للتشغيل الساري لهذه المعدة.
     * لا تُكتب availability_status ولا تُمسّ operations.status (القرار 4.5/6).
     *
     * @return int عدد صفوف operations المتأثرة
     */
    function mnt_mark_equipment_unhealthy($conn, $equipment_id, $company_id, $user_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        $user_id      = intval($user_id);
        if ($equipment_id <= 0) {
            return 0;
        }

        // فقط التشغيل الساري (status=1) لهذه المعدة — العزل بالشركة يُحقن (صفر operations
        // بـNULL فالعزل الصارم مطابق للـOR-NULL الدفاعي). NOW → PHP-side.
        $affected = ems_tenant_db()->update('operations', array(
            'equipment_health' => 'معطلة', 'health_reason' => 'صيانة',
            'health_updated_at' => date('Y-m-d H:i:s'), 'health_updated_by' => $user_id,
        ), array(), "equipment = ? AND status = 1", array($equipment_id));
        return max(0, (int) $affected);
    }

    /**
     * إغلاق أمر صيانة ⇐ إعادة الصحة الفنية «سليمة» لما سبق وضعه «معطلة» لهذه المعدة.
     * نعيد فقط ما وضعناه (equipment_health='معطلة') دون لمس operations.status.
     */
    function mnt_mark_equipment_healthy($conn, $equipment_id, $company_id, $user_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        $user_id      = intval($user_id);
        if ($equipment_id <= 0) {
            return 0;
        }

        // نعيد فقط ما وضعناه معطلة (العزل بالشركة يُحقن). NOW → PHP-side؛ health_reason=NULL.
        $affected = ems_tenant_db()->update('operations', array(
            'equipment_health' => 'سليمة', 'health_reason' => null,
            'health_updated_at' => date('Y-m-d H:i:s'), 'health_updated_by' => $user_id,
        ), array(), "equipment = ? AND equipment_health = 'معطلة'", array($equipment_id));
        return max(0, (int) $affected);
    }

    /**
     * إغلاق أمر صيانة ⇐ إعادة المعدة «متاحة للعمل» + تحديث آخر صيانة.
     * هذا هو الموضع الوحيد الذي تكتب فيه الصيانة availability_status / last_maintenance_date.
     * مقيّد بالشركة، ويحدّث availability_state إن وُجد (نموذج الحقلين القائم).
     */
    function mnt_return_equipment_available($conn, $equipment_id, $company_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return false;
        }

        $has_state = function_exists('db_table_has_column')
            ? db_table_has_column($conn, 'equipments', 'availability_state')
            : false;
        $has_last  = function_exists('db_table_has_column')
            ? db_table_has_column($conn, 'equipments', 'last_maintenance_date')
            : false;

        $gate = ems_tenant_db();
        $data = array('availability_status' => 'متاحة للعمل');
        if ($has_state) { $data['availability_state'] = 'متوفرة'; }
        if ($has_last)  { $data['last_maintenance_date'] = date('Y-m-d'); } // CURDATE → PHP-side
        try {
            $gate->update('equipments', $data, array('id' => $equipment_id)); // العزل بالشركة يُحقن
            $ok = true;
        } catch (\App\Core\TenantGateException $e) { $ok = false; }

        // استعادة الدور التشغيلي السابق (أساسي/احتياطي) — تعبير COALESCE يُحسب PHP-side لكل صف (best-effort)
        if (function_exists('db_table_has_column')
            && db_table_has_column($conn, 'operations', 'prev_equipment_category')) {
            try {
                $ops = $gate->select('operations', array('columns' => array('id', 'prev_equipment_category'),
                    'whereRaw' => "equipment = ? AND equipment_category = 'متعطل'", 'params' => array($equipment_id)));
                foreach ($ops as $op) {
                    $newCat = ($op['prev_equipment_category'] !== null && $op['prev_equipment_category'] !== '') ? $op['prev_equipment_category'] : 'أساسي';
                    $gate->update('operations', array('equipment_category' => $newCat, 'prev_equipment_category' => null), array('id' => intval($op['id'])));
                }
            } catch (\Throwable $t) { /* best-effort كالأصل (@) */ }
        }

        // إعادة الحالة التشغيلية «معطلة» → «جاهزة» للتشغيل الساري عند الإغلاق/الإلغاء
        if (function_exists('db_table_has_column')
            && db_table_has_column($conn, 'operations', 'op_state')) {
            $aff = 0;
            try {
                $aff = $gate->update('operations', array('op_state' => 'جاهزة'), array(),
                    "equipment = ? AND status = 1 AND op_state = 'معطلة'", array($equipment_id));
            } catch (\Throwable $t) { /* best-effort */ }

            // سجل «عودة من الصيانة» فقط إن تغيّرت الحالة فعليًا (معطلة → جاهزة).
            if ($aff > 0) {
                if (!function_exists('log_equipment_event') && file_exists(__DIR__ . '/../includes/equipment_log_helper.php')) {
                    require_once __DIR__ . '/../includes/equipment_log_helper.php';
                }
                if (function_exists('log_equipment_event')) {
                    $log_opts = ['to' => 'جاهزة'];
                    if (intval($company_id) > 0) { $log_opts['company_id'] = intval($company_id); }
                    log_equipment_event($conn, intval($equipment_id), 'عودة من الصيانة', $log_opts);
                }
            }
        }

        return (bool) $ok;
    }

    /**
     * إكمال تفتيش ⇐ كتابة نتيجته على كرت المعدة (equipment_condition/engine_condition).
     * مقيّد بالشركة. القيم النصّية تأتي من بنود التفتيش.
     */
    function mnt_apply_inspection_to_equipment($conn, $equipment_id, $company_id, $equipment_condition, $engine_condition)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return false;
        }

        $has_eq_cond = function_exists('db_table_has_column') ? db_table_has_column($conn, 'equipments', 'equipment_condition') : false;
        $has_en_cond = function_exists('db_table_has_column') ? db_table_has_column($conn, 'equipments', 'engine_condition') : false;

        $data = array();
        if ($has_eq_cond && $equipment_condition !== null && $equipment_condition !== '') {
            $data['equipment_condition'] = $equipment_condition;
        }
        if ($has_en_cond && $engine_condition !== null && $engine_condition !== '') {
            $data['engine_condition'] = $engine_condition;
        }
        if (empty($data)) {
            return false;
        }
        try {
            ems_tenant_db()->update('equipments', $data, array('id' => $equipment_id)); // العزل بالشركة يُحقن
            return true;
        } catch (\App\Core\TenantGateException $e) {
            return false;
        }
    }

    /**
     * إعادة احتساب تكاليف أمر الصيانة من أسطر العمالة والقطع (مقيّد بالشركة).
     * total_cost = labor_cost + parts_cost + external_cost.
     */
    function mnt_recalc_order_totals($conn, $order_id, $company_id)
    {
        $order_id   = intval($order_id);
        $company_id = intval($company_id);
        if ($order_id <= 0) {
            return false;
        }

        $gate = ems_tenant_db();
        // مجاميع مُعزّلة عبر scopedQuery (§10)
        $lr = $gate->scopedQuery(array('scope' => array('l' => 'mnt_order_labor')),
            "SELECT COALESCE(SUM(l.cost),0) AS s FROM mnt_order_labor l WHERE {TENANT_SCOPE} AND l.order_id = ?", array($order_id));
        $labor = $lr ? (float) $lr[0]['s'] : 0.0;
        $pr = $gate->scopedQuery(array('scope' => array('p' => 'mnt_order_part')),
            "SELECT COALESCE(SUM(p.subtotal),0) AS s FROM mnt_order_part p WHERE {TENANT_SCOPE} AND p.order_id = ?", array($order_id));
        $parts = $pr ? (float) $pr[0]['s'] : 0.0;
        // external_cost يُدخل يدوياً على الأمر
        $ord = $gate->selectOne('mnt_order', array('columns' => array('external_cost'), 'where' => array('id' => $order_id)));
        $external = $ord ? (float) $ord['external_cost'] : 0.0;

        $total = $labor + $parts + $external;
        try {
            $gate->update('mnt_order', array('labor_cost' => $labor, 'parts_cost' => $parts, 'total_cost' => $total), array('id' => $order_id));
            return true;
        } catch (\App\Core\TenantGateException $e) {
            return false;
        }
    }

    /**
     * إعادة جدولة خطة وقائية بعد إغلاق أمر مولّد منها (مقيّد بالشركة).
     * ساعات: next_due_meter = العدّاد الحالي + interval_value. زمن: next_due_date = اليوم + interval_value يوماً.
     */
    function mnt_reschedule_plan($conn, $plan_id, $company_id, $current_meter = null)
    {
        $plan_id    = intval($plan_id);
        $company_id = intval($company_id);
        if ($plan_id <= 0) {
            return false;
        }

        $gate = ems_tenant_db();
        $plan = $gate->selectOne('mnt_plan', array('columns' => array('trigger_basis', 'interval_value', 'equipment_id'), 'where' => array('id' => $plan_id)));
        if (!$plan) {
            return false;
        }

        $interval = intval($plan['interval_value']);
        $today = date('Y-m-d');
        try {
            if (trim((string) $plan['trigger_basis']) === 'زمن') {
                // DATE_ADD(CURDATE(), INTERVAL ? DAY) → PHP-side
                $gate->update('mnt_plan', array(
                    'last_done_date' => $today,
                    'next_due_date'  => date('Y-m-d', strtotime('+' . $interval . ' day')),
                ), array('id' => $plan_id));
            } else {
                // ساعات: العدّاد الحالي من التايم‌شيت إن لم يُمرّر صراحةً (بدل افتراض صفر).
                if ($current_meter !== null) {
                    $meter = floatval($current_meter);
                } elseif (intval($plan['equipment_id'] ?? 0) > 0) {
                    $meter = mnt_equipment_actual_hours($conn, intval($plan['equipment_id']), $company_id);
                } else {
                    $meter = 0.0;
                }
                $gate->update('mnt_plan', array(
                    'last_done_date'  => $today, 'last_done_meter' => $meter, 'next_due_meter' => $meter + $interval,
                ), array('id' => $plan_id));
            }
            return true;
        } catch (\App\Core\TenantGateException $e) {
            return false;
        }
    }

    /**
     * عدّادُ ساعات المعدة — مصدرُ جدولة الوقائية (UX-04 §3 · القرار 9).
     *
     * ── M-25: ما تغيّر ولماذا ─────────────────────────────────────────────
     * كانت هذه الدالّة تجمع `SUM(timesheet.operator_hours)` وتُقدَّم عدّادًا —
     * **وليست عدّادًا**: هي ساعاتُ المشغّل التراكمية (لا ساعاتُ الآلة)، ولا
     * تتأثر بتغيير عدّادٍ ولا بتصفيرِه، والوقائيةُ كانت تُجدول عليها.
     *
     * صارت الآن واجهةً على `MeterReadingService::currentMeter` بسلّمها المعلَن:
     * ① آخرُ **قراءةِ عدّادٍ** مسجَّلة ← ② العدّادُ الافتتاحي ← ③ **البديلُ
     * الموروثُ نفسُه مسمًّى باسمه**. فالرقمُ لا يقفز على البيانات القائمة (③
     * يعيد ما كانت تعيده حرفيًّا)، ويصير صادقًا فورَ تسجيل أول قراءة.
     *
     * التوقيعُ محفوظٌ عمدًا فلا يُكسر مستدعٍ. ولمن يحتاج المصدرَ مسمًّى:
     * `mnt_equipment_meter_info()` أدناه.
     */
    function mnt_equipment_actual_hours($conn, $equipment_id, $company_id)
    {
        $info = mnt_equipment_meter_info($conn, $equipment_id, $company_id);
        return (float) $info['value'];
    }

    /**
     * العدّادُ **بمصدره مسمًّى** — للعرض والتقارير: من يقرأ رقمًا يعرف أهو
     * قراءةُ عدّادٍ أم بديلٌ موروث (`is_reading`).
     * @return array{value:float,source:string,as_of:?string,is_reading:bool,note:string}
     */
    function mnt_equipment_meter_info($conn, $equipment_id, $company_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return array('value' => 0.0, 'source' => 'none', 'as_of' => null,
                         'is_reading' => false, 'note' => 'لا معدةَ محدَّدة');
        }
        require_once dirname(__DIR__) . '/app/Services/Fleet/MeterReadingService.php';
        return \App\Services\Fleet\MeterReadingService::currentMeter(
            $conn, ems_tenant_db(), $company_id, $equipment_id, 'hour');
    }

    /**
     * خيارات كتالوج mnt_lookup حسب النوع (مقيّد بالشركة، غير محذوف، نشط).
     * تُعيد مصفوفة [id => name].
     */
    function mnt_lookup_options($conn, $company_id, $type)
    {
        $out = array();
        // mnt_lookup soft=true فالبوابة تستبعد is_deleted تلقائيًّا (يوافق COALESCE(is_deleted,0)=0)
        $rows = ems_tenant_db()->select('mnt_lookup', array(
            'columns' => array('id', 'name'),
            'where' => array('type' => $type), 'whereRaw' => "COALESCE(is_active,1)=1", 'orderBy' => 'name ASC'));
        foreach ($rows as $r) { $out[intval($r['id'])] = $r['name']; }
        return $out;
    }

    /**
     * عدّاد البلاغات «الجديدة» لشركة المستخدم (للتوبار وقوائم الصيانة).
     */
    function mnt_new_breakdowns_count($conn, $company_id, $target_role = null)
    {
        $company_id = intval($company_id);
        if ($company_id <= 0) {
            return 0;
        }
        // عند تمرير الدور وتوفّر عمود التوجيه: نعدّ «الواردة إلى دوري الجديدة» فقط.
        // mnt_breakdown soft=true فالبوابة تستبعد is_deleted تلقائيًّا (يوافق الأصل).
        $use_role = ($target_role !== null && $target_role !== '' && function_exists('db_table_has_column')
            && db_table_has_column($conn, 'mnt_breakdown', 'target_role'));
        $where = array('state' => 'جديد');
        if ($use_role) { $where['target_role'] = intval($target_role); }
        return ems_tenant_db()->count('mnt_breakdown', array('where' => $where));
    }

    /**
     * هل المستخدم الحالي من دور الصيانة (مدير 13 أو مشرف 14)؟ يحدّد ظهور أزرار الصيانة.
     */
    function mnt_user_is_maintenance($conn)
    {
        if (!isset($_SESSION['user']['role'])) {
            return false;
        }
        $role = intval($_SESSION['user']['role']);
        // الأدوار المملوكة لشاشات الصيانة: المدير وأي دور فرعي تابع له.
        if ($role === -1) {
            return true; // السوبر أدمن يرى كل شيء
        }
        // modules + role_permissions جدولان عامّان (RBAC) — قراءتان عبر البوابة تُدمجان
        // في PHP (لا JOIN عالميّ في البوابة؛ لا عزل شركة على العالمي).
        $gate = ems_tenant_db();
        $mod = $gate->selectOne('modules', array('columns' => array('id'), 'where' => array('code' => 'Maintenance/orders.php')));
        if (!$mod) { return false; }
        return $gate->count('role_permissions', array('where' => array(
            'role_id' => $role, 'module_id' => intval($mod['id']), 'can_view' => 1))) > 0;
    }

    /**
     * المعدات «تحت الصيانة» المُسندة لمشروع معيّن عبر جدول التشغيل (operations).
     * الربط: operations.equipment = equipments.id ، operations.project_id = المشروع.
     * جدول التشغيل سجلّ تاريخي (history) — قد تتكرر المعدة في أكثر من سجل (سارٍ/منتهٍ)،
     * لذا نستخدم DISTINCT لعرض المعدة مرّة واحدة بصرف النظر عن حالة التشغيل (لا نقيّد بـ o.status).
     *
     * تعريف «تحت الصيانة» = حقل equipments.status = 1 (هو ما تعرضه شاشة الأسطول في عمود
     * «حالة المعدة»: 0 متاحة · 1 تحت الصيانة · 2 محجوزة · 3 معطلة · 5 مسحوبة). ونُبقي
     * availability_status='تحت الصيانة' كاحتياط لمسار move_oprators. مقيّد بالشركة.
     * include_id: يضمن ظهور معدة محدّدة دائماً (لفورم التحرير).
     * يُعيد مصفوفة صفوف [id, name, code].
     */
    function mnt_maint_equipment_in_project($conn, $company_id, $project_id, $include_id = 0)
    {
        $company_id = intval($company_id);
        $project_id = intval($project_id);
        $include_id = intval($include_id);
        $out = array();
        $seen = array();

        if ($project_id > 0) {
            // «تحت الصيانة» يُحدَّد عبر availability_status (الحالة الفعلية المعتمدة في كل النظام)،
            // وليس عبر العمود العام status (= علم «صف نشط» = 1 لكل معدة فعّالة). الاعتماد على status
            // كان يُظهر كل المعدات النشطة في القائمة ويمنع اختفاءها بعد إغلاق أمر الصيانة.
            // عزل على e + إثراء LEFT بـoperations؛ INNER→LEFT+o.id IS NOT NULL مكافئ
            $rows = ems_tenant_db()->scopedQuery(
                array('scope' => array('e' => 'equipments'), 'enrich' => array('o' => 'operations')),
                "SELECT DISTINCT e.id, e.name, e.code
                   FROM equipments e
                   LEFT JOIN operations o ON o.equipment = e.id AND o.project_id = ?
                  WHERE {TENANT_SCOPE} AND o.id IS NOT NULL
                    AND e.availability_status IN ('تحت الصيانة', 'موقوفة للصيانة')
                  ORDER BY e.name", array($project_id));
            foreach ($rows as $r) { $out[] = $r; $seen[intval($r['id'])] = true; }
        }

        // ضمان ظهور المعدة المختارة حالياً في فورم التحرير (حتى لو لم تَعُد «تحت الصيانة»).
        if ($include_id > 0 && empty($seen[$include_id])) {
            $r = ems_tenant_db()->selectOne('equipments', array('columns' => array('id', 'name', 'code'), 'where' => array('id' => $include_id)));
            if ($r) { array_unshift($out, $r); }
        }

        return $out;
    }

    /**
     * كل معدات المشروع (أي حالة إتاحة) — تُستخدم في شاشة التفتيش لاختيار المعدة بعد المشروع.
     * بخلاف mnt_maint_equipment_in_project لا تُقيِّد النتيجة بـ availability_status.
     *
     * @param int $include_id يضمن ظهور معدة محدّدة في فورم التحرير حتى لو لم تَعُد مرتبطة بتشغيل المشروع.
     */
    function mnt_all_equipment_in_project($conn, $company_id, $project_id, $include_id = 0)
    {
        $company_id = intval($company_id);
        $project_id = intval($project_id);
        $include_id = intval($include_id);
        $out = array();
        $seen = array();

        if ($project_id > 0) {
            // عزل على e + إثراء LEFT بـoperations (أي حالة إتاحة)؛ INNER→LEFT+o.id IS NOT NULL
            $rows = ems_tenant_db()->scopedQuery(
                array('scope' => array('e' => 'equipments'), 'enrich' => array('o' => 'operations')),
                "SELECT DISTINCT e.id, e.name, e.code
                   FROM equipments e
                   LEFT JOIN operations o ON o.equipment = e.id AND o.project_id = ?
                  WHERE {TENANT_SCOPE} AND o.id IS NOT NULL
                  ORDER BY e.name", array($project_id));
            foreach ($rows as $r) { $out[] = $r; $seen[intval($r['id'])] = true; }
        }

        // ضمان ظهور المعدة المختارة حالياً في فورم التحرير.
        if ($include_id > 0 && empty($seen[$include_id])) {
            $r = ems_tenant_db()->selectOne('equipments', array('columns' => array('id', 'name', 'code'), 'where' => array('id' => $include_id)));
            if ($r) { array_unshift($out, $r); }
        }

        return $out;
    }
}

if (!function_exists('mnt_publish_order_cost')) {
    /**
     * نشرُ أثر تكلفة أمر الصيانة من منبعه (UX-04 §8.2 · FES §7 و§8).
     * ─────────────────────────────────────────────────────────────────────
     * «التوليدُ من المنبع حصرًا — لا استيرادَ لاحقًا» (FES §7): كان أثرُ الصيانة
     * لا يبلغ الدفترَ إلا بزرِّ سحبٍ يضغطه موظفُ المالية (تقرير تدقيق الصيانة:
     * «يدويٌّ بالسحب … لا نشرَ حقيقةٍ آليًّا») — فتُقاس تكلفةُ ساعة المعدة
     * وربحيةُ المشروع وانحرافُ الميزانية على مصروفٍ ناقص. صار الإقفالُ نفسُه
     * ينشر الأثر.
     *
     * **عقدُ النشر هو عقدُ شاشة الاستيراد حرفيًّا** — المفتاح `mnt:order:{id}`
     * ونوعُ الحدث والكيان والمرجع: فمرشِّحُ الاستيراد (source_ref غيرُ مستعمل)
     * يستبعد الأمرَ المنشور تلقائيًّا، وعطالةُ الناشر تحجب أي تكرارٍ ثانٍ.
     * فلا يُسجَّل المبلغُ مرتين مهما تكرر الإقفالُ أو ضُغط الزر.
     *
     * لا يرمي أبدًا: الإقفالُ حقيقةٌ تشغيليةٌ لا يجوز أن يُفقد لتعثّرِ نشرٍ —
     * وعند الفشل يبقى زرُّ الاستيراد شبكةَ أمانٍ (والخطأ في السجل).
     *
     * @param  int    $order_id  الأمرُ بعد تحديث مجاميعه (mnt_recalc_order_totals)
     * @param  string $channel   قناةُ النشر للتدقيق: close | import_events_fin
     * @return string published | duplicate | skipped | failed
     */
    function mnt_publish_order_cost($conn, $order_id, $uid, $channel = 'close')
    {
        $order_id = intval($order_id);
        if ($order_id <= 0) { return 'skipped'; }

        try {
            $row = ems_tenant_db()->selectOne('mnt_order', array('where' => array('id' => $order_id)));
        } catch (\Throwable $t) {
            error_log('mnt publish cost #' . $order_id . ': ' . $t->getMessage());
            return 'failed';
        }
        if (!$row) { return 'skipped'; }

        // شروطُ مرشِّح الاستيراد نفسُها: مبلغٌ موجبٌ وكودٌ غير فارغ — وأمرٌ بلا
        // تكلفةٍ لا أثرَ له فلا يُنشر حدثٌ صفريٌّ يشوّش الدفتر (قاعدة عدم التلفيق).
        $amount = (float) (isset($row['total_cost']) ? $row['total_cost'] : 0);
        $code   = isset($row['code']) ? trim((string) $row['code']) : '';
        $docCompany = intval(isset($row['company_id']) ? $row['company_id'] : 0);
        if ($amount <= 0 || $code === '' || $docCompany <= 0) { return 'skipped'; }

        require_once __DIR__ . '/../app/Core/EventPublisher.php';
        $conn->begin_transaction();
        try {
            $res = \App\Core\EventPublisher::publish($conn, array(
                'event_key'         => 'expense.maintenance.recorded',
                'category'          => 'financial',
                'source_module'     => 'maintenance',
                'company_id'        => $docCompany,
                'entity_type'       => 'mnt_order',
                'entity_id'         => $order_id,
                'occurred_at'       => gmdate('Y-m-d H:i:s'),
                'created_by'        => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'   => 'mnt:order:' . $order_id,
                'legacy_event_type' => 'expense',
                'amount'            => $amount,
                'currency'          => (isset($row['currency']) && $row['currency'] !== null && $row['currency'] !== '') ? $row['currency'] : 'SDG',
                'source_ref'        => $code,
                'equipment_id'      => (isset($row['equipment_id']) && intval($row['equipment_id']) > 0) ? intval($row['equipment_id']) : null,
                'project_id'        => (isset($row['project_id']) && intval($row['project_id']) > 0) ? intval($row['project_id']) : null,
                'notes'             => 'تكلفة أمر صيانة ' . $code,
                'payload'           => array(
                    'order_id'      => $order_id,
                    'order_code'    => $code,
                    'labor_cost'    => (float) (isset($row['labor_cost']) ? $row['labor_cost'] : 0),
                    'parts_cost'    => (float) (isset($row['parts_cost']) ? $row['parts_cost'] : 0),
                    'external_cost' => (float) (isset($row['external_cost']) ? $row['external_cost'] : 0),
                    'channel'       => $channel,
                ),
            ));
            $conn->commit();
            return (is_array($res) && !empty($res['duplicate'])) ? 'duplicate' : 'published';
        } catch (\Throwable $t) {
            $conn->rollback();
            // M-33 (UX-04 §8.2): «423 بفترةٍ مقفلةٍ → RetryPending» — حكمُ الفترة
            // يُعلَن باسمه لا يُبلَع في failed: الأمرُ يبقى وينشره الاستيرادُ أو
            // إعادةُ الإقفال يومَ تُفتح الفترة (زرُّ الاستيراد شبكةُ الأمان القائمة).
            if (strpos($t->getMessage(), '423') !== false) {
                error_log('mnt publish cost #' . $order_id . ' (' . $channel . '): RetryPending — ' . $t->getMessage());
                return 'retry_pending';
            }
            error_log('mnt publish cost #' . $order_id . ' (' . $channel . '): ' . $t->getMessage());
            return 'failed';
        }
    }
}
