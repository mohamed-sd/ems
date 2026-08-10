<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * شارات بوابة الطلب المالي D05 للقائمة الجانبية — عدّاداتٌ خفيفة معزولة بالبوابة:
 * صندوق الإدارة (تحت المراجعة لإدارات الدور) · مكتب المحاسب (بانتظار ولادة الحدث) ·
 * طلباتي (المعاد لمنشئه). مستقلٌّ عن مساعدات الوحدة (يُحمَّل في كل صفحةٍ عبر
 * insidebar) — أي فشلٍ يعيد خريطةً صفرية ولا يمسّ الواجهة.
 *
 * الاستعمال: $badges = ems_finreq_nav_badges($conn); ثم تمريرها لـ renderDynamicNavigation.
 */

if (!function_exists('ems_finreq_nav_links')) {
    /**
     * روابط بوابة الطلبات التي يستحقها المستخدم — تُحسب من جدول التوجيه المفعّل
     * لا من مالك الوحدة (owner_role_id) الواحد؛ فكل دورٍ له طريقٌ في أي إدارةٍ
     * مفعّلة يرى النموذج وطلباته، وكل مراجعٍ/معتمِدٍ يرى صندوق إدارته.
     * تُستدعى من insidebar بعد الروابط الديناميكية.
     *
     * @return array من code => array('label','icon')
     */
    function ems_finreq_nav_links($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id']) || !function_exists('ems_tenant_db')) {
            return $out;
        }
        try {
            $role = strval($_SESSION['user']['role'] ?? '');
            $is_super = ($role === '-1');
            $g = $is_super ? ems_tenant_db()->forAllTenants('finreq nav links super') : ems_tenant_db();

            $can_create = $is_super;
            $can_review = $is_super;
            foreach ($g->select('fin_request_routing', array('where' => array('is_active' => 1))) as $rt) {
                $creators = array_map('trim', explode(',', strval($rt['requester_roles'])));
                if (in_array($role, $creators, true)) { $can_create = true; }
                if (strval($rt['reviewer_role_id']) === $role || strval($rt['manager_role_id']) === $role) {
                    $can_review = true;
                }
            }
            // أدوار المالية: مكتب المحاسب وبوابة المالية
            $is_acct = in_array($role, array('17', '18'), true) || $is_super;
            $is_fin  = in_array($role, array('17', '18', '19', '20', '21', '22'), true) || $is_super;

            if ($can_create) {
                $out['FinRequests/request_form.php'] = array('label' => 'طلب مالي جديد', 'icon' => 'fa fa-file-circle-plus');
                $out['FinRequests/my_requests.php']  = array('label' => 'طلباتي المالية', 'icon' => 'fa fa-list-check');
            }
            if ($can_review) {
                $out['FinRequests/dept_inbox.php'] = array('label' => 'موافقات إدارتي', 'icon' => 'fa fa-inbox');
            }
            if ($is_acct) {
                $out['FinRequests/accountant_desk.php'] = array('label' => 'مكتب المحاسب', 'icon' => 'fa fa-calculator');
            }
            if ($is_fin) {
                $out['FinRequests/finance_gateway.php'] = array('label' => 'الطلبات المالية', 'icon' => 'fa fa-building-columns');
                $out['FinRequests/cycle_time_board.php'] = array('label' => 'زمن دورة الطلبات', 'icon' => 'fa fa-stopwatch');
                $out['FinRequests/requests_reports.php'] = array('label' => 'تقارير الطلبات المالية', 'icon' => 'fa fa-chart-column');
            }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'القائمة الجانبية لا تتعطل بأي فشلٍ هنا');
            // القائمة الجانبية لا تتعطل بأي فشلٍ هنا
        }
        return $out;
    }
}

if (!function_exists('ems_finance_nav_links')) {
    /**
     * روابط شاشات المالية الممنوحة للأدوار التشغيلية (قرار 2026-07-17):
     * السايدبار مملوكيٌّ (owner_role_id=17) فلا يعرضها لغير عائلة المالية —
     * هنا تُشتق من role_permissions مباشرةً (can_view=1) فيصل كل دورٍ لما
     * مُنح له عرضًا. أدوار المالية (17-22) يغطيها dynamic_nav فلا تُكرَّر.
     *
     * @return array من code => array('label','icon')
     */
    function ems_finance_nav_links($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id'])) {
            return $out;
        }
        $role = strval($_SESSION['user']['role'] ?? '');
        if ($role === '' || $role === '-1' || in_array($role, array('17', '18', '19', '20', '21', '22'), true)) {
            return $out;
        }
        try {
            $rid = intval($role);
            $q = $conn->prepare(
                "SELECT m.code, m.name, COALESCE(NULLIF(TRIM(m.icon), ''), 'fa fa-coins') AS icon
                   FROM modules m
                   JOIN role_permissions rp ON rp.module_id = m.id
                  WHERE rp.role_id = ? AND rp.can_view = 1
                    AND m.code LIKE 'Finance/%' AND m.is_link = '1'
                  ORDER BY m.display_order ASC, m.id ASC"
            );
            $q->bind_param('i', $rid);
            $q->execute();
            $res = $q->get_result();
            while ($m = $res->fetch_assoc()) {
                $out[strval($m['code'])] = array('label' => strval($m['name']), 'icon' => strval($m['icon']));
            }
            $q->close();
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'القائمة الجانبية لا تتعطل بأي فشلٍ هنا');
            // القائمة الجانبية لا تتعطل بأي فشلٍ هنا
        }
        return $out;
    }
}

if (!function_exists('ems_finreq_nav_badges')) {
    function ems_finreq_nav_badges($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id']) || !function_exists('ems_tenant_db')) {
            return $out;
        }
        try {
            $role = strval($_SESSION['user']['role'] ?? '');
            $uid = intval($_SESSION['user']['id']);
            $is_super = ($role === '-1');
            $g = $is_super ? ems_tenant_db()->forAllTenants('finreq nav badges super') : ems_tenant_db();

            // إدارات هذا الدور (مراجعًا/معتمدًا) من التوجيه المفعّل
            $mods = array();
            foreach ($g->select('fin_request_routing', array('where' => array('is_active' => 1))) as $rt) {
                $allowed = ($is_super
                    || strval($rt['reviewer_role_id']) === $role
                    || strval($rt['manager_role_id']) === $role);
                if ($allowed) { $mods[] = strval($rt['source_module']); }
            }
            if ($mods) {
                $ph = implode(',', array_fill(0, count($mods), '?'));
                $n = intval($g->count('fin_requests', array(
                    'whereRaw' => "state = 'under_review' AND source_module IN ($ph)",
                    'params' => $mods,
                )));
                if ($n > 0) { $out['FinRequests/dept_inbox.php'] = $n; }
            }
            if ($role === '18' || $role === '17' || $is_super) {
                $n = intval($g->count('fin_requests', array(
                    'whereRaw' => "state = 'pending_approval' AND event_id IS NULL",
                    'params' => array(),
                )));
                if ($n > 0) { $out['FinRequests/accountant_desk.php'] = $n; }
            }
            $n = intval($g->count('fin_requests', array(
                'whereRaw' => "state = 'returned' AND (created_by = ? OR requester_id = ?)",
                'params' => array($uid, $uid),
            )));
            if ($n > 0) { $out['FinRequests/my_requests.php'] = $n; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'شاراتٌ فقط — الواجهة لا تتأثر بأي فشل');
            // شاراتٌ فقط — الواجهة لا تتأثر بأي فشل
        }
        return $out;
    }
}
