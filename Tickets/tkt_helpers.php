<?php
/**
 * Tickets/tkt_helpers.php — دوال مساعدة مشتركة لوحدة البلاغات (ticket_*).
 *
 * ملاحظات معمارية (نفس نمط وحدات النقل والمشتريات والمالية):
 *   • دوال نقية قابلة لإعادة الاستخدام فقط — إقلاع الجلسة/الصلاحيات يبقى داخل كل صفحة.
 *   • الأنواع والتصنيفات كتالوج مشترك (T_CATALOG): القراءة «عام أو مِلكي» عبر
 *     البوابة تلقائيًا؛ الكتابة التعديلية على صفوف الشركة حصرًا (الصفوف العامة
 *     المبذورة بالهجرة تُعرض للقراءة فقط).
 *   • لا حذف في هذه الوحدة إطلاقًا — الإلغاء حالةٌ تُسجَّل لا محوٌ للسجل،
 *     والتعطيل عبر العلم active لا عبر softDelete.
 *
 * @package EMS\Tickets
 */

if (!function_exists('tkt_ctx')) {
    /** سياق المستخدم الحالي من الجلسة. */
    function tkt_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'       => $role,
            'is_super'   => ($role === EMS_ROLE_SUPER_ADMIN),
            'company_id' => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'    => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }
}

if (!function_exists('tkt_page_perms')) {
    /** حل صلاحيات صفحة البلاغات (super admin يملك الكل). */
    function tkt_page_perms($conn, $code, $is_super)
    {
        $p = check_page_permissions($conn, $code);
        return array(
            'can_view'   => $is_super ? true : $p['can_view'],
            'can_add'    => $is_super ? true : $p['can_add'],
            'can_edit'   => $is_super ? true : $p['can_edit'],
            'can_delete' => $is_super ? true : $p['can_delete'],
        );
    }
}

if (!function_exists('tkt_gate')) {
    /**
     * بوابة العزل للوحدة — سياق الجلسة افتراضًا؛ is_super عبر forAllTenants
     * المسجلة. وفي سياق النظام (cron لاحقًا): تركيب بوابة forSystem($cid)
     * وحقنها عبر tkt_gate_override فتتبعها كل الدوال هنا.
     */
    function tkt_gate($is_super = false)
    {
        if (isset($GLOBALS['__tkt_gate_override']) && $GLOBALS['__tkt_gate_override'] instanceof \App\Core\TenantDb) {
            return $GLOBALS['__tkt_gate_override'];
        }
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('tkt helpers super view') : $gate;
    }

    /** حقن بوابة دورة النظام (null = العودة لسياق الجلسة). */
    function tkt_gate_override($gate)
    {
        $GLOBALS['__tkt_gate_override'] = $gate;
    }
}

if (!function_exists('tkt_msg_banner')) {
    /** شريط رسالة النجاح/الخطأ من msg في الـ query string. */
    function tkt_msg_banner()
    {
        if (empty($_GET['msg'])) {
            return;
        }
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        echo '<div class="success-message ' . ($isSuccess ? 'is-success' : 'is-error') . '">';
        echo '<i class="fas ' . ($isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle') . '"></i> ';
        echo htmlspecialchars($_GET['msg']);
        echo '</div>';
    }
}

if (!function_exists('tkt_label')) {
    /** ترجمة قيمة إلى عنوانها العربي من خريطة، مع تمرير القيمة نفسها إن لم توجد. */
    function tkt_label($map, $key)
    {
        return isset($map[$key]) ? $map[$key] : (string)$key;
    }
}

if (!function_exists('tkt_gen_code')) {
    /** كود تقني بسيط للصفوف المضافة يدويًا (فريد ضمن الشركة عبر uq code). */
    function tkt_gen_code($table)
    {
        $n = tkt_gate(false)->count($table, array());
        return 'custom_' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

// ── قوائم بيضاء (خرائط ترجمة القيم الثابتة إلى عربية للعرض) ──

if (!function_exists('tkt_natures')) {
    /** طبيعة التذكرة: طلبٌ أو بلاغُ عطلٍ أو مهمّةٌ دورية. */
    function tkt_natures()
    {
        return array('request' => 'طلب', 'incident' => 'بلاغ', 'recurring' => 'دوري');
    }
}

if (!function_exists('tkt_ref_tables')) {
    /**
     * القائمة البيضاء لجداول التنفيذ في النظام — سلامة المرجع المرن تُفرَض
     * هنا في طبقة التطبيق لا بقيد قاعدة بيانات، لدعم البناء المرحلي.
     */
    function tkt_ref_tables()
    {
        return array(
            'mnt_order'            => 'أوامر الصيانة (mnt_order)',
            'transfer_orders'      => 'أوامر النقل (transfer_orders)',
            'proc_request'         => 'طلبات المشتريات (proc_request)',
            'proc_stock_move'      => 'حركات المخزون (proc_stock_move)',
            'fin_requests'         => 'الطلبات المالية (fin_requests)',
            'equipments'           => 'المعدات (equipments)',
            'employees'            => 'الموظفون (employees)',
            'worker_leave_absence' => 'الإجازات والغياب (worker_leave_absence)',
            'contracts'            => 'العقود (contracts)',
            'operations'           => 'التشغيل (operations)',
            'suppliers'            => 'الموردون (suppliers)',
        );
    }
}

if (!function_exists('tkt_owner_role_ids')) {
    /**
     * أدوار الإدارات المالكة المسموح التوجيه إليها: رؤوس المستوى الأول في
     * شجرة الأدوار + دور البلاغات نفسه (بلاغات السلامة والطوارئ تبدأ عنده).
     */
    function tkt_owner_role_ids()
    {
        return array(
            intval(EMS_ROLE_OPERATIONS_MGR),   // 1  التشغيل
            intval(EMS_ROLE_SUPPLIERS_MGR),    // 2  الموردون
            intval(EMS_ROLE_FLEET_MGR),        // 3  الأسطول
            intval(EMS_ROLE_HR_MGR),           // 4  القوى
            intval(EMS_ROLE_SALES_MGR),        // 12 المبيعات
            intval(EMS_ROLE_MAINTENANCE_MGR),  // 13 الصيانة
            intval(EMS_ROLE_PROCUREMENT_MGR),  // 16 المشتريات
            intval(EMS_ROLE_CFO),              // 17 التمويل
            intval(EMS_ROLE_TRANSPORT_MGR),    // 23 النقل
            intval(EMS_ROLE_TICKETS_MGR),      // 24 البلاغات
        );
    }
}

if (!function_exists('tkt_roles_map')) {
    /** خريطة id ← اسم الدور من جدول roles (مرجع عام — قراءة عبر البوابة). */
    function tkt_roles_map()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = array();
        $rows = tkt_gate(false)->select('roles', array(
            'columns'  => array('id', 'name'),
            'whereRaw' => "(status = '1' OR status = 1)",
            'orderBy'  => 'id ASC',
        ));
        foreach ($rows as $r) {
            $map[intval($r['id'])] = (string)$r['name'];
        }
        return $map;
    }
}

if (!function_exists('tkt_stages')) {
    /** مراحل دورة حياة التذكرة الثماني + حالة الإلغاء. */
    function tkt_stages()
    {
        return array(
            'new'         => 'جديدة',
            'classified'  => 'مصنّفة',
            'routed'      => 'محالة',
            'in_progress' => 'قيد التنفيذ',
            'waiting'     => 'بانتظار جهة أخرى',
            'follow_up'   => 'قيد المتابعة',
            'done'        => 'منجزة',
            'closed'      => 'مغلقة',
            'cancelled'   => 'ملغاة',
        );
    }
}

if (!function_exists('tkt_stage_badge')) {
    /** شارة مرحلة التذكرة للعرض (نمط trs_stage_badge). */
    function tkt_stage_badge($stage)
    {
        $colors = array(
            'new' => '#6c757d', 'classified' => '#6610f2', 'routed' => '#0d6efd',
            'in_progress' => '#fd7e14', 'waiting' => '#b58900', 'follow_up' => '#0dcaf0',
            'done' => '#198754', 'closed' => '#212529', 'cancelled' => '#dc3545',
        );
        $map = tkt_stages();
        $c = isset($colors[$stage]) ? $colors[$stage] : '#6c757d';
        $lbl = isset($map[$stage]) ? $map[$stage] : $stage;
        return "<span class='action-btn' style='color:#fff;background:$c;border-radius:12px;padding:2px 10px'>" . htmlspecialchars($lbl) . "</span>";
    }
}

if (!function_exists('tkt_stage_step')) {
    /**
     * موضعُ البلاغِ من رحلتِه الخمس — **تعريفٌ واحدٌ** يقرأه شريطُ الشاشةِ
     * (`tkt_journey`) ومؤشِّرُ القائمةِ (`tkt_stage_mini`) معًا.
     *
     * ◆ ولماذا مصدرٌ واحد: عدّادٌ وعارضٌ في موضعين يتفرّقان — فلو أضيفتْ مرحلةٌ
     *   أو أُعيدت تسميتُها في أحدِهما لقرأ المستخدمُ رقمين مختلفين للبلاغ نفسه.
     * ◆ والمرحلتان الموقوفتان (`waiting`/`follow_up`) ليستا خانةً في الشريط بل
     *   **وقفةٌ داخل «قيد التنفيذ»** — فالرحلةُ لا تتقدَّم بوقفة.
     * ◆ ويُحسب من `stage` وحدَه بلا أحداث، فيصلح لصفِّ قائمةٍ لا استعلامَ له.
     *
     * @param string $stage قيمة tickets.stage
     * @return array{index:int,total:int,map:array,labels:array,label:string,paused:bool,stopped:bool,final:bool}
     */
    function tkt_stage_step($stage)
    {
        $stage  = strval($stage);
        $map    = array('new' => 1, 'classified' => 1, 'routed' => 2,
                        'in_progress' => 3, 'done' => 4, 'closed' => 5);
        $labels = array(1 => 'سُجّل', 2 => 'وُجّه', 3 => 'قيد التنفيذ', 4 => 'أُنجز', 5 => 'أُغلق');

        $paused  = ($stage === 'waiting' || $stage === 'follow_up');
        $stopped = ($stage === 'cancelled');

        if     ($stopped)            { $index = 0; }   // توقفت الرحلة
        elseif ($stage === 'closed') { $index = 6; }   // كلُّ الخمسِ منجَزة
        elseif (isset($map[$stage])) { $index = $map[$stage]; }
        else                         { $index = $paused ? 3 : 1; }

        return array(
            'index'   => $index,
            'total'   => 5,
            'map'     => $map,
            'labels'  => $labels,
            'label'   => isset($labels[$index]) ? $labels[$index] : '',
            'paused'  => $paused,
            'stopped' => $stopped,
            'final'   => in_array($stage, array('done', 'closed', 'cancelled'), true),
        );
    }
}

if (!function_exists('tkt_stage_mini')) {
    /**
     * مؤشِّرُ تقدُّمٍ مُصغَّرٌ لصفِّ القائمة — خمسُ نقاطٍ تُضيء حتى المرحلةِ الحالية،
     * فيُقرأ موقعُ كلِّ بلاغٍ من رحلتِه **بلا فتحِه**. الشريطُ الكاملُ بأسماءِ
     * المراحلِ وأصحابِها وخطوتِها التالية يبقى داخلَ شاشةِ البلاغ.
     *
     * ◆ النصُّ لا يُستغنى عنه بلونٍ وحدَه: `title` و`aria-label` يحملان المرحلةَ
     *   بالحروف، والبادجُ النصيُّ باقٍ إلى جانبِ المؤشِّر.
     *
     * @param string $stage   قيمة tickets.stage
     * @param bool   $overdue هل كسر البلاغُ مهلتَه (يُلوَّن الحاليُّ إنذارًا)
     */
    function tkt_stage_mini($stage, $overdue = false)
    {
        $s = tkt_stage_step($stage);
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

        $now  = $s['stopped'] ? 'أُلغي — توقفت الرحلة'
              : ($s['paused'] ? tkt_label(tkt_stages(), $stage) . ' (وقفةٌ داخل «قيد التنفيذ»)'
                              : $s['label']);
        $tip  = 'المرحلة: ' . $now . ' — ' . min($s['index'], $s['total']) . ' من ' . $s['total'];

        $out = '<span class="ems-jmini' . ($s['stopped'] ? ' is-stopped' : '')
             . '" role="img" aria-label="' . $e($tip) . '" title="' . $e($tip) . '">';
        for ($k = 1; $k <= $s['total']; $k++) {
            if     ($s['stopped'])      { $cls = 'is-off'; }
            elseif ($k <  $s['index'])  { $cls = 'is-done'; }
            elseif ($k === $s['index']) { $cls = 'is-current' . ($overdue ? ' is-overdue' : '') . ($s['paused'] ? ' is-paused' : ''); }
            else                        { $cls = 'is-todo'; }
            $out .= '<i class="ems-jmini-dot ' . $cls . '" aria-hidden="true"></i>';
        }
        return $out . '</span>';
    }
}

if (!function_exists('tkt_priorities')) {
    function tkt_priorities()
    {
        return array('normal' => 'عادية', 'high' => 'عالية', 'critical' => 'حرجة');
    }
}

if (!function_exists('tkt_impacts')) {
    /** الوزن التشغيلي: أثر البلاغ على الإنتاج أو الإيراد أو السلامة. */
    function tkt_impacts()
    {
        return array(
            'production_critical' => 'حرج للإنتاج',
            'revenue'             => 'مؤثر على الإيراد',
            'safety'              => 'سلامة',
            'admin'               => 'إداري',
        );
    }
}

if (!function_exists('tkt_machine_conditions')) {
    function tkt_machine_conditions()
    {
        return array('running' => 'تعمل', 'stopped' => 'متوقفة');
    }
}

if (!function_exists('tkt_next_ticket_no')) {
    /**
     * رقم التذكرة بصيغة سنة-شهر-تسلسل (مثل 26-07-0001) — يفوّضه هذا الغلاف
     * إلى TicketNumber، سلطةِ الترقيم الواحدة لكل كتّاب البلاغات.
     *
     * يُستدعى **خارج** معاملة الإنشاء لا بداخلها: التخصيص داخل المعاملة كان
     * يجعل ارتدادَها يتراجع بالعدّاد، فتعلق الشاشة تطلب رقمًا محجوزًا أبدًا.
     * التخصيصُ ذاتي الشفاء والفجوةُ عند الارتداد مقصودةٌ ومقبولة.
     */
    function tkt_next_ticket_no($conn, $company_id)
    {
        require_once dirname(__DIR__) . '/app/Services/Tickets/TicketNumber.php';
        return \App\Services\Tickets\TicketNumber::allocateUnique($conn, $company_id);
    }
}

if (!function_exists('tkt_roles_tree')) {
    /** شجرة الأدوار الحية: id ← parent_role_id (قراءة عبر البوابة — مرجع عام). */
    function tkt_roles_tree()
    {
        static $tree = null;
        if ($tree !== null) {
            return $tree;
        }
        $tree = array();
        $rows = tkt_gate(false)->select('roles', array(
            'columns'  => array('id', 'parent_role_id'),
            'whereRaw' => "(status = '1' OR status = 1)",
        ));
        foreach ($rows as $r) {
            $tree[intval($r['id'])] = ($r['parent_role_id'] === null) ? null : intval($r['parent_role_id']);
        }
        return $tree;
    }
}

if (!function_exists('tkt_visible_owner_role_ids')) {
    /**
     * نطاق رؤية التوجيه لدورٍ ما = دورُه نفسه + **ذرّيّتُه** في شجرة الأدوار
     * (parent_role_id). النزولُ وحدَه — والصعودُ مُغلق.
     *
     * ◆ لماذا أُغلق الصعود (قرارُ المالك 2026-08-17): كان النطاقُ يضمُّ
     *   **الأجدادَ** أيضًا، فيرى المرؤوسُ بلاغاتِ رئيسِه. والمقيسُ وقتَ الإغلاق:
     *   **١٢ دورًا** ترى ما لا تملك — أشدُّها «مشرف صيانة» (14) يرى **٣٤** بلاغًا
     *   لـ«إدارة الصيانة» (13) وهو يملك صفرًا، وثمانيةُ أدوارٍ ماليةٍ تابعةٍ ترى
     *   بلاغَ «إدارة المالية» (17). والقاعدةُ المعلَنة: «كلُّ دورٍ يرى بلاغَه
     *   وتذكرتَه» — فالإشرافُ ينزل ولا يصعد.
     * ◆ والنزولُ باقٍ عمدًا: رئيسُ الإدارةِ مسؤولٌ عمّا يجري تحته، فحجبُه عن
     *   بلاغاتِ مرؤوسيه يكسر الإشرافَ لا يحميه.
     * ◆ وما أبلغ به المستخدمُ نفسُه يبقى مرئيًّا له — شرطٌ منفصلٌ في الشاشة
     *   (reporter_user_id/created_by) لا في هذه الدالة.
     *
     * تنبيه: لا يُستخدم العمود role_scope القائم لهذا الغرض — كلُّ مديري
     * الإدارات مضبوطون فيه على «عام»، فإعادةُ استخدامه تكشف كلَّ البلاغات
     * لكل مدير إدارة.
     */
    function tkt_visible_owner_role_ids($role_id)
    {
        $role_id = intval($role_id);
        $tree = tkt_roles_tree();
        $visible = array($role_id => true);

        // الذرية: BFS على الأبناء
        $frontier = array($role_id);
        $guard = 0;
        while (!empty($frontier) && $guard < 10) {
            $next = array();
            foreach ($tree as $id => $parent) {
                if ($parent !== null && in_array($parent, $frontier, true) && !isset($visible[$id])) {
                    $visible[$id] = true;
                    $next[] = $id;
                }
            }
            $frontier = $next;
            $guard++;
        }

        return array_keys($visible);
    }
}

if (!function_exists('tkt_options_from_rows')) {
    /** بناء <option> من صفوف بوابة (id + label جاهزان) — نمط trs_options_from_rows. */
    function tkt_options_from_rows(array $rows, $selected = 0, $placeholder = '— اختر —')
    {
        $out = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        $selected = intval($selected);
        foreach ($rows as $r) {
            $id  = intval($r['id']);
            $lbl = isset($r['label']) ? (string)$r['label'] : '';
            $sel = ($id === $selected) ? ' selected' : '';
            $out .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
        }
        return $out;
    }
}

if (!function_exists('tkt_type_options')) {
    /** قائمة الأنواع الفعالة (كتالوج: عام + مِلكي عبر البوابة). */
    function tkt_type_options($selected = 0)
    {
        $rows = tkt_gate(false)->select('ticket_types', array(
            'columns' => array('id', 'name'),
            'where'   => array('active' => 1),
            'orderBy' => 'id ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return tkt_options_from_rows($rows, $selected, '— اختر نوع البلاغ —');
    }
}

if (!function_exists('tkt_equipment_options')) {
    /** قائمة المعدات — قراءة فقط من equipments (نمط trs_equipment_options). */
    function tkt_equipment_options($selected = 0)
    {
        $rows = tkt_gate(false)->select('equipments', array(
            'columns'  => array('id', 'code', 'name'),
            'whereRaw' => 'COALESCE(status,1)=1',
            'orderBy'  => 'id DESC',
        ));
        foreach ($rows as &$r) {
            $code = (string) $r['code'];
            $base = ($code === '') ? ('#' . intval($r['id'])) : $code;
            $name = (string) $r['name'];
            $r['label'] = $base . ($name === '' ? '' : ' — ' . $name);
        }
        unset($r);
        return tkt_options_from_rows($rows, $selected, '— بلا معدة —');
    }
}

if (!function_exists('tkt_project_options')) {
    /** قائمة المشاريع — قراءة فقط من project. */
    function tkt_project_options($selected = 0)
    {
        $rows = tkt_gate(false)->select('project', array(
            'columns' => array('id', 'name'), 'orderBy' => 'name ASC', 'includeDeleted' => true,
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return tkt_options_from_rows($rows, $selected, '— بلا مشروع —');
    }
}

if (!function_exists('tkt_log_event')) {
    /** إضافة حدث إلى سجلّ التذكرة — إدراجٌ فقط، بلا تعديلٍ أو حذف. */
    function tkt_log_event($ticket_id, $event_type, $body, $old = null, $new = null, $actor_user_id = null, $actor_role_id = null)
    {
        try {
            tkt_gate(false)->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => $event_type,
                'actor_user_id' => $actor_user_id, 'actor_role_id' => $actor_role_id,
                'body' => $body, 'old_value' => $old, 'new_value' => $new,
            ));
        } catch (\App\Core\TenantGateException $e) {
            error_log('tkt_log_event refused: ' . $e->getMessage());
        }
    }
}

// ── محرّك الاستحقاق (SLA) ──

if (!function_exists('tkt_match_sla_policy')) {
    /**
     * انتقاء سياسة الاستحقاق المطبَّقة على تذكرة — **الأكثرُ تحديدًا يفوز**
     * (نفس مبدأ انتقاء قواعد التكلفة في وحدة النقل: القاعدة المحدَّدة تسبق
     * العامة). سلّم الأفضلية بالنقاط:
     *   نوع مطابق = 4 · أولوية مطابقة = 2 · وزن مطابق = 1 · (NULL = عام، بلا نقاط)
     * أي شرطٍ غير مطابقٍ (وغير NULL) يُسقط السياسة كليًّا. التعادل ⇒ الأقدم (id).
     * @return array|null صف السياسة أو null إن لم تُضبط سياساتٌ مطابقة.
     */
    function tkt_match_sla_policy($ticket_type_id, $priority, $business_impact)
    {
        // القاعدة نفسها للجميع — التنفيذ في TicketSla ليبقى للمهلة مرجعٌ واحد
        // تستعمله الشاشاتُ (عبر هذا الغلاف) والخدماتُ والكرون على السواء.
        require_once dirname(__DIR__) . '/app/Services/Tickets/TicketSla.php';
        global $conn;
        $ctx = tkt_ctx();
        return \App\Services\Tickets\TicketSla::match(
            $conn, $ctx['company_id'], $ticket_type_id, $priority, $business_impact);
    }
}

if (!function_exists('tkt_compute_due')) {
    /**
     * مواعيد الاستحقاق من لحظة البلاغ + ساعات السياسة — **ساعاتٌ تقويميّة**
     * (المواقع تعمل بورديتين، فاليومُ يومٌ كامل لا ساعاتِ دوامٍ مكتبيّ).
     * @return array{response: ?string, resolution: ?string, policy_id: ?int}
     */
    function tkt_compute_due($call_date, $call_time, array $policy = null)
    {
        if ($policy === null) {
            return array('response' => null, 'resolution' => null, 'policy_id' => null);
        }
        $base = trim((string)$call_date . ' ' . (string)$call_time);
        $ts = strtotime($base);
        if ($ts === false) { $ts = time(); }
        $resp = (float)$policy['response_hours'];
        $reso = (float)$policy['resolution_hours'];
        return array(
            'response'   => date('Y-m-d H:i:s', $ts + (int)round($resp * 3600)),
            'resolution' => date('Y-m-d H:i:s', $ts + (int)round($reso * 3600)),
            'policy_id'  => intval($policy['id']),
        );
    }
}

if (!function_exists('tkt_apply_sla')) {
    /**
     * يطابق السياسة ويحسب المواعيد ويحدّث التذكرة (عبر بوابةٍ ممرَّرة أو سياقية).
     * يُستدعى عند الإنشاء وعند كل تعديلٍ للتصنيف أو الأولوية أو الوزن.
     * @return array|null المواعيد المحسوبة أو null إن لا سياسة.
     */
    function tkt_apply_sla($gate, $ticket_id, $ticket_type_id, $priority, $business_impact, $call_date, $call_time)
    {
        $policy = tkt_match_sla_policy($ticket_type_id, $priority, $business_impact);
        $due = tkt_compute_due($call_date, $call_time, $policy);
        $gate->update('tickets', array(
            'sla_policy_id'     => $due['policy_id'],
            'response_due_at'   => $due['response'],
            'resolution_due_at' => $due['resolution'],
        ), array('id' => $ticket_id));
        return ($due['policy_id'] === null) ? null : $due;
    }
}

if (!function_exists('tkt_is_overdue')) {
    /** متأخّرة = تجاوزت موعد الإنجاز ولمّا تُنجَز أو تُغلَق أو تُلغَ. */
    function tkt_is_overdue(array $t)
    {
        if (empty($t['resolution_due_at'])) { return false; }
        if (in_array($t['stage'], array('done', 'closed', 'cancelled'), true)) { return false; }
        return strtotime($t['resolution_due_at']) < time();
    }
}

if (!function_exists('tkt_overdue_badge')) {
    function tkt_overdue_badge(array $t)
    {
        if (!tkt_is_overdue($t)) { return '—'; }
        $late = time() - strtotime($t['resolution_due_at']);
        $h = max(1, (int)floor($late / 3600));
        return "<span class='action-btn' style='color:#fff;background:#c0392b;border-radius:12px;padding:2px 10px'>متأخّر " . $h . "س</span>";
    }
}

if (!function_exists('tkt_escalation_target_role')) {
    /**
     * ترجمة مستوى التصعيد المجرّد إلى دورٍ حقيقيٍّ في النظام، باستثمار شجرة
     * الأدوار: المسؤول/رئيس القسم = الإدارة المالكة نفسها · مدير الإدارة =
     * الدور الأب لها (أو هي إن كانت جذرًا) · مدير التشغيل · الإدارة العليا =
     * مدير البلاغات.
     */
    function tkt_escalation_target_role($level_role, $owner_role_id)
    {
        $owner_role_id = intval($owner_role_id);
        switch ((string)$level_role) {
            case 'responsible':
            case 'dept_head':
                return $owner_role_id;
            case 'dept_manager':
                $tree = tkt_roles_tree();
                return (isset($tree[$owner_role_id]) && $tree[$owner_role_id] !== null)
                    ? intval($tree[$owner_role_id]) : $owner_role_id;
            case 'ops_manager':
                return intval(EMS_ROLE_OPERATIONS_MGR);
            case 'top_mgmt':
            default:
                return intval(EMS_ROLE_TICKETS_MGR);
        }
    }
}

if (!function_exists('tkt_notify')) {
    /**
     * إشعار وحدة البلاغات (جدول tkt_notifications) مع منع التكرار عبر
     * dedupe_key الفريد — يُعيد false عند التكرار بدل رمي خطأ.
     */
    function tkt_notify($notif_type, $title, $body, $ticket_id, $target_role, $link_url, $dedupe_key)
    {
        try {
            tkt_gate(false)->insert('tkt_notifications', array(
                'ticket_id'   => ($ticket_id === null) ? null : intval($ticket_id),
                'notif_type'  => $notif_type,
                'target_role' => ($target_role === null) ? null : intval($target_role),
                'title'       => $title, 'body' => $body,
                'link_url'    => $link_url, 'dedupe_key' => $dedupe_key,
            ));
            return true;
        } catch (\App\Core\TenantGateException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return false; // مكرر اليوم
            }
            error_log('tkt_notify refused: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('tkt_open_children_count')) {
    /** عدد الفروع غير المغلقة — يمنع إغلاق التذكرة الرئيسية قبلها. */
    function tkt_open_children_count($parent_id)
    {
        return (int) tkt_gate(false)->count('tickets', array(
            'whereRaw' => "parent_id = ? AND stage NOT IN ('closed','cancelled')",
            'params'   => array(intval($parent_id)),
        ));
    }
}

// ── دورة حياة التذكرة ──

if (!function_exists('tkt_transitions')) {
    /**
     * خريطة انتقالات مرحلة التذكرة: action ⇒ [from, to, need, reason, label, icon, color].
     *
     * need: edit = صلاحية التعديل (قيادة دورة الحياة) · delete = صلاحية
     *       الإلغاء وإعادة الفتح.
     * reason: true = سببٌ إلزاميٌّ يُرفض الانتقالُ بدونه ويُسجَّل في الحدث.
     *
     * الانتقالات كلُّها بيد فريق البلاغات؛ والإدارات المنفِّذة تُتابع وتُعلّق
     * ولا تُحرّك المراحل.
     */
    function tkt_transitions()
    {
        return array(
            // التوجيه: المخرج الوحيد من الاستقبال إلى العمل. البلاغات القادمة
            // من المسار البرمجي (الفتح السياقي · كسر الزجاج · التفتيش) تُولد
            // في «جديدة»، وشاشة التصنيف تنقلها إلى «مصنّفة» — ولم يكن لأيٍّ
            // منهما مخرجٌ إلى «محالة»، فكان البلاغ الميداني يُلغى ولا يُنجَز.
            'route'    => array('from' => array('new', 'classified'), 'to' => 'routed', 'need' => 'edit', 'reason' => false, 'label' => 'توجيه للإدارة المختصة', 'icon' => 'fa-share',   'color' => '#0d6efd'),
            'start'    => array('from' => 'routed',      'to' => 'in_progress', 'need' => 'edit',   'reason' => false, 'label' => 'بدء التنفيذ',            'icon' => 'fa-play',           'color' => '#fd7e14'),
            'wait'     => array('from' => 'in_progress', 'to' => 'waiting',     'need' => 'edit',   'reason' => true,  'label' => 'تعليق (بانتظار جهة)',    'icon' => 'fa-hourglass-half', 'color' => '#b58900'),
            'follow'   => array('from' => 'waiting',     'to' => 'follow_up',   'need' => 'edit',   'reason' => false, 'label' => 'رفع المُعَوِّق (متابعة)', 'icon' => 'fa-rotate',         'color' => '#0dcaf0'),
            'resume'   => array('from' => 'follow_up',   'to' => 'in_progress', 'need' => 'edit',   'reason' => false, 'label' => 'استئناف التنفيذ',        'icon' => 'fa-play',           'color' => '#fd7e14'),
            'complete' => array('from' => 'in_progress', 'to' => 'done',        'need' => 'edit',   'reason' => false, 'label' => 'إنجاز العمل',            'icon' => 'fa-check',          'color' => '#198754'),
            'rework'   => array('from' => 'done',        'to' => 'in_progress', 'need' => 'edit',   'reason' => true,  'label' => 'إعادة للاستكمال',        'icon' => 'fa-rotate-left',    'color' => '#6f42c1'),
            'close'    => array('from' => 'done',        'to' => 'closed',      'need' => 'edit',   'reason' => false, 'label' => 'إغلاق التذكرة',          'icon' => 'fa-lock',           'color' => '#212529'),
            'reopen'   => array('from' => 'closed',      'to' => 'follow_up',   'need' => 'delete', 'reason' => true,  'label' => 'إعادة فتح',              'icon' => 'fa-lock-open',      'color' => '#6f42c1'),
        );
    }
}

if (!function_exists('tkt_transition_allows')) {
    /**
     * هل يقبل هذا الانتقالُ المرحلةَ الحالية؟ الحقل `from` يقبل نصًّا واحدًا
     * أو مصفوفةَ مراحل (التوجيه يخرج من «جديدة» و«مصنّفة» معًا).
     */
    function tkt_transition_allows(array $tr, $stage)
    {
        $from = $tr['from'];
        return is_array($from) ? in_array((string) $stage, $from, true) : ((string) $stage === (string) $from);
    }
}

if (!function_exists('tkt_sync_head_state')) {
    /**
     * مزامنة `head_state` مع الواقع — يُستدعى بعد كل انتقالٍ في دورة الحياة.
     *
     * للبلاغ نموذجا حالةٍ متعايشان: رأسٌ تقوده أزرارُ دورة الحياة (بلاغات
     * الشاشة اليدوية، بلا مسارات)، ومساراتٌ متوازيةٌ يشتقّ منها
     * TicketStateService الرأسَ (بلاغات المسار البرمجي). كان زرُّ الإغلاق
     * يكتب `stage` وحده، فيبقى `head_state='open'` أبدًا ويظل البلاغ المغلق
     * ظاهرًا في لوحة المسارات ومرشَّحًا في كاشف التكرار.
     *
     * القاعدة: ذو المسارات يشتقّ رأسه منها (الكاتب الأصلي يبقى سيّدًا)،
     * وعديمُ المسارات يعكس رأسُه مرحلتَه مباشرةً.
     */
    function tkt_sync_head_state($gate, $ticket_id, $stage)
    {
        $ticket_id = intval($ticket_id);
        $head = in_array((string) $stage, array('closed', 'cancelled'), true) ? 'closed' : 'open';

        // ذو المسارات لا يُغلق رأسُه ومسارٌ إلزاميٌّ مفتوح — قاعدةُ نموذج
        // المسارات نفسُها (423). حارسُ الإغلاق يمنع الوصول هنا أصلًا، وهذا
        // دفاعٌ في العمق. الإلغاء الإداريُّ يُغلق مساراتِه أولًا فيمرّ.
        if ($head === 'closed' && tkt_open_mandatory_ws_count($gate, $ticket_id) > 0) {
            return false;
        }
        $gate->update('tickets', array('head_state' => $head), array('id' => $ticket_id));
        return true;
    }
}

if (!function_exists('tkt_open_mandatory_ws_count')) {
    /** عددُ المسارات الإلزامية المفتوحة — صفرٌ أيضًا لبلاغٍ بلا مسارات أصلًا. */
    function tkt_open_mandatory_ws_count($gate, $ticket_id)
    {
        return intval($gate->count('ticket_workstreams', array(
            'whereRaw' => "tk_id = ? AND mandatory = 1 AND activation_state = 'opened'
                           AND state NOT IN ('closed','admin_closed')",
            'params'   => array(intval($ticket_id)),
        )));
    }
}

if (!function_exists('tkt_close_open_workstreams')) {
    /**
     * إقفالٌ إداريٌّ لما بقي مفتوحًا من مسارات البلاغ — للإلغاء وحده.
     * إغلاقٌ لا حذف: الصفوف تبقى كاملةً للتدقيق وتُوسَم بسببها.
     */
    function tkt_close_open_workstreams($gate, $ticket_id)
    {
        $rows = $gate->select('ticket_workstreams', array(
            'columns'  => array('ws_id'),
            'whereRaw' => "tk_id = ? AND state NOT IN ('closed','admin_closed')",
            'params'   => array(intval($ticket_id)),
        ));
        foreach ($rows as $w) {
            $gate->update('ticket_workstreams',
                array('state' => 'admin_closed', 'closed_at' => date('Y-m-d H:i:s')),
                array('ws_id' => intval($w['ws_id'])));
        }
        return count($rows);
    }
}

if (!function_exists('tkt_can_view_ticket')) {
    /** فحص رؤية تذكرةٍ واحدة بنفس قاعدة القائمة — خادميًّا قبل العرض أو التعليق. */
    function tkt_can_view_ticket(array $t, $ctx)
    {
        if ($ctx['is_super']) { return true; }
        if ($ctx['role'] === EMS_ROLE_TICKETS_MGR) { return true; }
        $rid = intval($ctx['role']);
        $uid = intval($ctx['user_id']);
        if (intval($t['reporter_user_id']) === $uid || intval($t['created_by']) === $uid) { return true; }
        return in_array(intval($t['owner_role_id']), tkt_visible_owner_role_ids($rid), true);
    }
}

if (!function_exists('tkt_user_options')) {
    /** قائمة مستخدمي الشركة (لإسناد المسؤول) — قراءة فقط من users. */
    function tkt_user_options($selected = 0)
    {
        $rows = tkt_gate(false)->select('users', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return tkt_options_from_rows($rows, $selected, '— بلا مسؤول محدد —');
    }
}

if (!function_exists('tkt_save_attachment')) {
    /**
     * حفظ مرفق تذكرة (صورة/مستند) خارج مسار الكود + تسجيله في ticket_attachments.
     * يتحقّق من النوع والحجم، ويعيد المسار النسبي أو null عند الرفض.
     */
    function tkt_save_attachment($ticket_id, $file, $uploaded_by)
    {
        if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 8 * 1024 * 1024) { return null; }   // ≤ 8MB
        $allowed = array('jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1, 'pdf' => 1);
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) { return null; }
        $dir = __DIR__ . '/uploads/tickets';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $safe = 'tk_' . intval($ticket_id) . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $safe)) { return null; }
        $rel = 'uploads/tickets/' . $safe;
        try {
            tkt_gate(false)->insert('ticket_attachments', array(
                'ticket_id' => $ticket_id, 'file_path' => $rel,
                'file_type' => ($ext === 'pdf') ? 'document' : 'photo',
                'captured_at' => date('Y-m-d H:i:s'), 'uploaded_by' => $uploaded_by,
            ));
        } catch (\App\Core\TenantGateException $e) {
            error_log('tkt_save_attachment refused: ' . $e->getMessage());
        }
        return $rel;
    }
}

if (!function_exists('tkt_event_type_labels')) {
    function tkt_event_type_labels()
    {
        return array(
            'note' => 'ملاحظة', 'communication' => 'تواصل', 'status_change' => 'تغيير حالة',
            'transfer' => 'تحويل ملكية', 'escalation' => 'تصعيد', 'attachment' => 'مرفق',
            'reminder' => 'تذكير', 'system' => 'نظام',
        );
    }
}

if (!function_exists('tkt_scope_badge')) {
    /** شارة نطاق صف الكتالوج: عام (بذر النظام) أو خاص بالشركة. */
    function tkt_scope_badge($row_company_id)
    {
        if ($row_company_id === null || $row_company_id === '') {
            return "<span class='action-btn' style='color:#6c757d'>عام</span>";
        }
        return "<span class='action-btn' style='color:#0d6efd'>خاص بالشركة</span>";
    }
}

if (!function_exists('tkt_active_badge')) {
    /** شارة حالة التفعيل. */
    function tkt_active_badge($active)
    {
        return ((int)$active === 1)
            ? "<span class='action-btn' style='color:#1e7e34'>مفعّل</span>"
            : "<span class='action-btn' style='color:#c0392b'>معطّل</span>";
    }
}

if (!function_exists('tkt_journey')) {
    /**
     * بانيةُ شريط رحلة البلاغ (الدستور §5/§9 · UX-01 §6.3 · UX-07 §2).
     * ─────────────────────────────────────────────────────────────────────
     * المكوّنُ المصيِّر عامٌّ (includes/journey_bar.php) — هذه بانيتُه للبلاغ،
     * توأمُ finreq_journey للطلب المالي. تقرأ ما هو مسجَّلٌ ولا تخترع.
     *
     * **خمسُ مراحلَ من مراحل النظام التسع** — والأربعُ الباقية ليست تقدّمًا:
     *   ① سُجّل (new · classified) ② وُجّه (routed) ③ قيد التنفيذ (in_progress)
     *   ④ أُنجز (done) ⑤ أُغلق (closed)
     * و`waiting` و`follow_up` **وقفتان داخل ③** لا مرحلتان (نصُّ آلة الحالات:
     * تعليقٌ بانتظار جهة ثم رفعُ المعوّق ثم استئنافُ التنفيذ — لا تقدّمَ فيهما)
     * فتُعرضان لافتةً بسببها؛ و`cancelled` توقفٌ.
     *
     * **آخرُ دخولٍ للمرحلة لا أوّلُه**: البلاغُ يُعاد فتحُه ويُعاد للاستكمال
     * (closed→follow_up · done→in_progress في الآلة)، فالزمنُ المعروض آخرُ مرةٍ
     * دخل فيها المرحلةَ — وإلا عرضنا زمنَ دورةٍ ماتت.
     *
     * @param  array $events صفوف ticket_events مرتّبةً تصاعديًّا
     * @return array عقد ems_journey_bar()
     */
    function tkt_journey(array $t, array $events = array())
    {
        require_once __DIR__ . '/../includes/journey_bar.php';   // ems_journey_ago

        $stage = strval($t['stage']);

        // ── ① آخرُ دخولٍ لكل مرحلة + سببُ آخر وقفة ─────────────────────
        // خريطةُ المراحلِ وترتيبُها من **المصدرِ الواحد** — لا نسخةَ ثانيةً هنا.
        $step     = tkt_stage_step($stage);
        $stageIdx = $step['map'];
        $at = array();
        $pauseReason = null; $pauseAt = null;
        $escalations = 0;
        foreach ($events as $ev) {
            $type = strval($ev['event_type']);
            if ($type === 'escalation') { $escalations++; continue; }
            if ($type !== 'status_change' && $type !== 'system') { continue; }
            $new = strval($ev['new_value']);
            if ($new === '') { continue; }
            if (isset($stageIdx[$new])) { $at[$stageIdx[$new]] = strval($ev['created_at']); }
            if ($new === 'waiting' || $new === 'follow_up') {
                $pauseReason = trim(strval($ev['body']));
                $pauseAt = strval($ev['created_at']);
            }
        }
        // زمنُ التسجيل من البلاغ نفسه حين لا حركةَ مسجَّلة له
        if (!isset($at[1])) { $at[1] = strval($t['created_at']); }

        // ── ② المرحلة الحالية ───────────────────────────────────────────
        $paused  = $step['paused'];
        $current = $step['index'];

        // والملغى وحدَه يحتاج الأحداثَ: أينَ وقف قبل الإلغاء لا يُعرف من stage.
        if ($stage === 'cancelled') {
            for ($k = 5; $k >= 1; $k--) { if (isset($at[$k])) { $current = $k + 1; break; } }
            if ($current === 0) { $current = 1; }
        }

        // ── ③ الأصحاب: المسنَد إليه شخصًا إن وُجد، وإلا الإدارةُ المالكة ──
        $roles = tkt_roles_map();
        $ownerRole = isset($roles[intval($t['owner_role_id'])]) ? $roles[intval($t['owner_role_id'])] : '';
        $assignee = '';
        if (!empty($t['assigned_user_id'])) {
            try {
                $u = tkt_gate(false)->selectOne('users', array(
                    'columns' => array('username'), 'where' => array('id' => intval($t['assigned_user_id']))));
                if ($u && !empty($u['username'])) { $assignee = strval($u['username']); }
            } catch (\Throwable $e) { /* اسمٌ زينةُ عرض */ }
        }
        $doer = ($assignee !== '') ? $assignee : $ownerRole;

        // الأسماءُ من المصدرِ الواحد، وأصحابُها وحدَهم يُحسبون هنا.
        $owners = array(1 => 'المبلِّغ', 2 => $ownerRole, 3 => $doer, 4 => $doer, 5 => $ownerRole);
        $defs = array();
        foreach ($step['labels'] as $k => $lbl) {
            $defs[$k] = array('label' => $lbl, 'owner' => isset($owners[$k]) ? $owners[$k] : '');
        }

        // ── ④ الساعتان: الاستجابة قبل البدء، والإنجاز بعده ──────────────
        $isStopped = ($stage === 'cancelled');
        $isFinal   = in_array($stage, array('done', 'closed', 'cancelled'), true);
        $started   = !empty($t['first_action_at']);
        $overdueResp = (!$isFinal && !$started && !empty($t['response_due_at'])
                        && strtotime(strval($t['response_due_at'])) < time());
        $overdueRes  = tkt_is_overdue($t);      // مهلة الإنجاز — الحارس القائم
        $overdue = ($overdueResp || $overdueRes);

        $stages = array();
        foreach ($defs as $k => $d) {
            if     ($k <  $current)  { $status = 'done'; }
            elseif ($k === $current) { $status = $isStopped ? 'off' : 'current'; }
            else                     { $status = $isStopped ? 'off' : 'todo'; }
            $row = array('label' => $d['label'], 'status' => $status);
            if (isset($at[$k]))     { $row['at'] = $at[$k]; }
            if ($d['owner'] !== '') { $row['owner'] = $d['owner']; }
            if ($status === 'current' && $overdue) { $row['overdue'] = true; }
            if ($status === 'current' && $paused)  { $row['note'] = tkt_label(tkt_stages(), $stage); }
            $stages[] = $row;
        }

        $j = array('stages' => $stages);

        // ── ⑤ الخطوة التالية باسم صاحبها ────────────────────────────────
        if (!$isStopped && $current >= 1 && $current <= 5) {
            $since = isset($at[$current]) ? $at[$current] : strval($t['created_at']);
            $j['next'] = array(
                'label'   => $paused ? tkt_label(tkt_stages(), $stage) : $defs[$current]['label'],
                'owner'   => $defs[$current]['owner'],
                'since'   => $since,
                'overdue' => $overdue,
            );
        }

        // ── ⑥ اللافتة: وقفةٌ بسببها · إلغاءٌ · اكتمال ────────────────────
        if ($paused) {
            $j['banner'] = array(
                'kind'  => 'return',
                'title' => ($stage === 'waiting') ? 'موقوفٌ بانتظار جهةٍ أخرى:' : 'رُفع المعوّق — قيد المتابعة:',
                'text'  => ($pauseReason !== null && $pauseReason !== '') ? $pauseReason : 'بلا سببٍ مسجَّل',
                'meta'  => $pauseAt ? ems_journey_ago($pauseAt) : '',
            );
        } elseif ($isStopped) {
            $j['banner'] = array('kind' => 'stop', 'title' => 'أُلغي البلاغ — توقفت الرحلة.');
        } elseif ($stage === 'closed') {
            $j['banner'] = array('kind' => 'done', 'title' => 'أُغلق البلاغ واكتملت رحلته.',
                'meta' => isset($at[5]) ? ems_journey_ago($at[5]) : '');
        } elseif ($overdueResp) {
            $j['banner'] = array('kind' => 'stop', 'title' => 'انكسرت مهلة الاستجابة',
                'text' => 'لم يبدأ أحدٌ العملَ عليه بعد.');
        }

        // التصعيداتُ المسجَّلة تُذكر عددًا — لا تُخترع درجة
        if ($escalations > 0 && isset($j['next'])) {
            $j['next']['label'] .= ' · صُعّد ' . ems_journey_plural($escalations, 'مرة', 'مرتين', 'مرات', 'مرةً');
        }

        return $j;
    }
}
