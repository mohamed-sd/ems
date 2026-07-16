<?php
/**
 * شارات بوابة الطلب المالي D05 للقائمة الجانبية — عدّاداتٌ خفيفة معزولة بالبوابة:
 * صندوق الإدارة (تحت المراجعة لإدارات الدور) · مكتب المحاسب (بانتظار ولادة الحدث) ·
 * طلباتي (المعاد لمنشئه). مستقلٌّ عن مساعدات الوحدة (يُحمَّل في كل صفحةٍ عبر
 * insidebar) — أي فشلٍ يعيد خريطةً صفرية ولا يمسّ الواجهة.
 *
 * الاستعمال: $badges = ems_finreq_nav_badges($conn); ثم تمريرها لـ renderDynamicNavigation.
 */

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
        } catch (\Throwable $t) {
            // شاراتٌ فقط — الواجهة لا تتأثر بأي فشل
        }
        return $out;
    }
}
