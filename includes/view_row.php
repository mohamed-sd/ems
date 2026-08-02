<?php
/**
 * includes/view_row.php — حلّالُ صفِّ العرض (NAV-01 v6 §6 · update0007 T-04)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا عارضَ بلا صفٍّ معلن» — الشاشةُ تسأل: بأي صفةٍ يراني هذا المستخدم؟
 * فتأخذ نطاقَه وزاويتَه وأعمدتَه وفلاترَه من مصفوفة العرض لا من اجتهاد الشاشة.
 *
 * الاستعمال داخل شاشةٍ متعددةِ العرض:
 *     require_once __DIR__ . '/../includes/view_row.php';
 *     $vr = ems_view_row($conn, 'Timesheet/timesheet.php');
 *     if ($vr && $vr['role_kind'] === 'viewer') { ... طبّق $vr['scope_sql'] ... }
 *
 * النطاقُ يُفرض في الخادم (استعلامًا) لا بإخفاء أعمدة — SEC-01 §7.
 */
if (!function_exists('ems_view_row')) {

    /**
     * صفُّ عرضِ المستخدم الحالي لهذه الشاشة — أو null إن لم يكن ناظرًا معلَنًا.
     * @return array{screen_name:string,dept:string,role_kind:string,scope_text:string,
     *               angle:?string,columns_text:?string,filters_text:?string,scope_sql:string}|null
     */
    function ems_view_row(\mysqli $conn, $route)
    {
        $role = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
        if ($role <= 0) return null;
        $st = mysqli_prepare($conn,
            "SELECT screen_name, dept, role_kind, scope_text, angle, columns_text, filters_text
             FROM screen_view_rows WHERE route = ? AND role_id = ? AND active = 1 LIMIT 1");
        mysqli_stmt_bind_param($st, 'si', $route, $role);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($st);
        if (!$row) return null;
        $row['scope_sql'] = ems_view_scope_sql($row['scope_text']);
        return $row;
    }

    /**
     * ترجمةُ نصِّ النطاق شرطَ SQL جاهزًا (يُلحق بـ AND) — بمتغيرات الجلسة.
     * «موقعُه» تفترض عمودَ project_id/site يطابق مواقعَ المستخدم؛
     * والشاشةُ تمرر اسمَ عمودها إن خالف الافتراض.
     */
    function ems_view_scope_sql($scopeText, $siteCol = 'project_id', $supplierCol = 'supplier_entity_id',
                                $contractCol = 'contract_id', $selfCol = 'created_by')
    {
        $u = $_SESSION['user'] ?? array();
        $uid = intval($u['id'] ?? 0);
        if (mb_strpos($scopeText, 'موقعُه') !== false || mb_strpos($scopeText, 'موقعه') !== false) {
            // مواقعُ المستخدم من صفاته (user_capacities scope site/project)
            $ids = ems_view_user_scope_ids('site');
            return $ids ? " $siteCol IN (" . implode(',', $ids) . ") " : ' 1=0 ';
        }
        if (mb_strpos($scopeText, 'مورديه') !== false || mb_strpos($scopeText, 'مورده') !== false) {
            $ids = ems_view_user_scope_ids('supplier');
            return $ids ? " $supplierCol IN (" . implode(',', $ids) . ") " : ' 1=0 ';
        }
        if (mb_strpos($scopeText, 'عقود') !== false) {
            $ids = ems_view_user_scope_ids('contract');
            return $ids ? " $contractCol IN (" . implode(',', $ids) . ") " : ' 1=1 ';
        }
        if (mb_strpos($scopeText, 'سجلات') !== false) return " $selfCol = $uid ";
        return ' 1=1 '; // الشركةُ أو نطاقُ الإدارة — عزلُ الشركة قائمٌ في TenantGate أصلًا
    }

    /** معرّفاتُ نطاق المستخدم من صفاته النشطة */
    function ems_view_user_scope_ids($kind)
    {
        static $cache = array();
        if (isset($cache[$kind])) return $cache[$kind];
        $conn = $GLOBALS['conn'] ?? null;
        $acc = intval($_SESSION['user']['id'] ?? 0);
        if (!$conn || !$acc) return $cache[$kind] = array();
        $map = array('site' => "('site','project')", 'supplier' => "('supplier')", 'contract' => "('client')");
        if (!isset($map[$kind])) return $cache[$kind] = array();
        $ids = array();
        $r = mysqli_query($conn, "SELECT DISTINCT scope_id FROM user_capacities
                                  WHERE account_id = $acc AND scope_type IN {$map[$kind]} AND scope_id IS NOT NULL");
        if ($r) while ($x = mysqli_fetch_row($r)) $ids[] = intval($x[0]);
        return $cache[$kind] = $ids;
    }
}
