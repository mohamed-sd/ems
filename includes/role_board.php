<?php
/**
 * لوحة الدور — المكوّنات السبعة (UX-00 §7 · UX-01 §5)
 * ─────────────────────────────────────────────────────────────────────────
 * «تُلغى اللوحة العامة الواحدة، وتُبنى لكل دورٍ لوحتُه من سبعة مكوّناتٍ ثابتة
 *  الترتيب — تختلف محتوياتُها لا بنيتُها» (UX-01 §5):
 *
 *   ① مؤشرات اليوم — ثلاثةٌ إلى خمسة أرقامٍ تُقرَّر منها أفعالُ اليوم
 *   ② مهامي        — ما يجب فعلُه الآن بأزرار قفز
 *   ③ موافقاتي     — عدّادُ المنتظر وفتحُ الصندوق بنقرة
 *   ④ التنبيهات    — المتأخر والحرج **بسببٍ وقفزة** (§9: لا تنبيهَ بلا إجراء)
 *   ⑤ إنشاء سريع   — أكثر ثلاث عملياتٍ استخدامًا
 *   ⑥ نبض الأداء   — رسمٌ أو اثنان يخصّان قرار الدور
 *   ⑦ عملي الأخير  — آخر ما لمسه ليتابع
 *
 * قاعدتان حاكمتان يفرضهما الدستور وتُنفَّذان هنا بنيويًّا:
 *   • كل رقمٍ ينقر إلى مصدره — فلا مؤشرَ بلا `href` (يُرفض عند البناء).
 *   • التنبيهُ يحمل سببَه وقفزتَه — وتعريفاتُ التنبيهات لكل دورٍ منقولةٌ
 *     **حرفيًّا** من UX-01 §8، لا اجتهادًا (انظر roleBoardAlertSpecs).
 *
 * التنبيهات تُشتق من الحالة التشغيلية ولا تُقرأ من جدول تنبيهاتٍ موحّد —
 * لأن الوثيقة لم تطلب جدولًا قط: كلُّ تنبيهٍ فيها شرطٌ محسوب («وحداتٌ غير
 * معتمدة منذ أمس» · «عقدٌ ينتهي خلال 30 يومًا») لا صفٌّ مخزَّن.
 *
 * التشغيل المزدوج بعلَمٍ لكل دور: EMS_ROLE_BOARD_ROLES في .env — كما فعلنا
 * في المصدر الموحّد للسايدبار؛ ودورٌ خارج العلم يبقى على اللوحة العامة حرفيًّا.
 */

require_once __DIR__ . '/dynamic_nav.php';

/** هل الدور على لوحة الدور؟ ($csv تجاوزٌ اختباري؛ null = من البيئة) */
function roleBoardEnabled($roleId, $csv = null)
{
    if ($csv === null) {
        $csv = function_exists('ems_env') ? (string) ems_env('EMS_ROLE_BOARD_ROLES', '') : '';
    }
    if (trim($csv) === '') { return false; }
    return in_array(strval(intval($roleId)), array_map('trim', explode(',', $csv)), true);
}

/**
 * لوحةُ كلِّ دور (قرار المالك 2026-07-26: «الرئيسية» تفتح لوحةَ الدور مباشرةً —
 * نصُّ UX-01 §4: «أول ما يُفتح — لوحة الدور»). الدورُ الفرعي يرث لوحةَ أبيه
 * (قرار المالك: «الفرعية ترث أباها») — فيُمرَّر parent إن وُجد.
 * يعيد المسار من جذر التطبيق، أو null لدورٍ بلا لوحةٍ بعد (يبقى على العامة).
 */
function roleBoardRoute($roleId, $parentRoleId = null)
{
    $map = array(
        17 => 'Finance/cfo_daily_board_fin.php',
        13 => 'Maintenance/dashboard_mnt.php',
        // القادمة: 16 المشتريات و23 الرحلات (لوحتاهما قائمتان — تُكملان بالمكوّنات)
    );
    $rid = intval($roleId);
    if (isset($map[$rid])) { return $map[$rid]; }
    $pid = intval($parentRoleId);
    return ($pid > 0 && isset($map[$pid])) ? $map[$pid] : null;
}

/**
 * قياسٌ عدديٌّ معزول عبر البوابة — يعيد 0 عند أي فشلٍ ولا يرمي أبدًا:
 * لوحةُ الدور لا تتعطل لعطبِ مؤشرٍ واحد (نمط finreq_badges المثبَت).
 */
function roleBoardScalar($gate, array $scope, $sql, array $params = array())
{
    try {
        $r = $gate->scopedQuery($scope, $sql, $params);
        return $r ? (float) array_values($r[0])[0] : 0.0;
    } catch (\Throwable $t) {
        error_log('role_board scalar: ' . $t->getMessage());
        return 0.0;
    }
}

/**
 * ④ تعريفات التنبيهات لكل دور — منقولةٌ حرفيًّا من UX-01 §8.
 * لكل تنبيه: النص (السبب) · الشرط (دالّة تعيد العدد) · القفزة (الشاشة).
 * يُبنى هنا التعريفُ وحده؛ والتنفيذُ في roleBoardAlerts.
 */
function roleBoardAlertSpecs($roleId)
{
    $specs = array(
        // §8.11 · المدير المالي
        17 => array(
            array('key' => 'liquidity', 'label' => 'سيولةٌ دون الحد',            'href' => '../Finance/cash_forecast_fin.php',  'tone' => 'err'),
            array('key' => 'aged_dues', 'label' => 'ذممٌ تجاوزت أعمارها',        'href' => '../Finance/dues_fin.php',           'tone' => 'err'),
            array('key' => 'req_sla',   'label' => 'طلباتٌ فوق مهلة الاعتماد',   'href' => '../FinRequests/finance_gateway.php','tone' => 'warn'),
            array('key' => 'variance',  'label' => 'انحرافُ ميزانيةٍ فوق النسبة', 'href' => '../Finance/budget_form_fin.php',    'tone' => 'warn'),
        ),
        // §8.8 · ادارة الصيانة — التنبيه الرابع في النص («قطعةٌ منتظرةٌ توقف
        // أمرًا») بلا مصدرِ بياناتٍ اليوم: لا حالةَ wait_parts في آلة الأمر
        // والقطعُ نصٌّ حرٌّ (قرار DEC-10) — لا يُعرض حتى يُبنى مصدرُه
        // (تدقيق الصيانة، المستوى الأول #6). قاعدةُ عدم التلفيق نافذة.
        13 => array(
            array('key' => 'critical_down', 'label' => 'معدةٌ حرجةٌ متوقفة',        'href' => '../Maintenance/orders.php',           'tone' => 'err'),
            array('key' => 'order_overdue', 'label' => 'أمرٌ مفتوحٌ فوق مدته',      'href' => '../Maintenance/orders.php',           'tone' => 'warn'),
            array('key' => 'pm_late',       'label' => 'وقائيةٌ متأخرة',            'href' => '../Maintenance/preventive_plans.php', 'tone' => 'warn'),
        ),
    );
    return isset($specs[intval($roleId)]) ? $specs[intval($roleId)] : array();
}

/** ④ حساب التنبيهات الحية — كلٌّ بعدده وسببه وقفزته؛ وما عدده صفرٌ يختفي. */
function roleBoardAlerts($conn, $gate, $roleId)
{
    $rid = intval($roleId);
    $out = array();
    $today = date('Y-m-d');

    if ($rid === 17) {
        $counts = array();
        // سيولةٌ دون الحد: صافي 7 أيامٍ سالب (الذمم المستحقة ناقص الملتزم)
        $wk = date('Y-m-d', strtotime('+7 days'));
        $in7 = roleBoardScalar($gate, array('scope' => array('r' => 'fin_receivables')),
            "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE}
             AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<=?", array($wk));
        $out7 = roleBoardScalar($gate, array('scope' => array('f' => 'fin_funding_schedules')),
            "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f
             WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date<=?", array($wk));
        $counts['liquidity'] = ($in7 - $out7) < 0 ? 1 : 0;

        $counts['aged_dues'] = (int) roleBoardScalar($gate, array('scope' => array('r' => 'fin_receivables')),
            "SELECT COUNT(*) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
             AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<?", array($today));

        // الحالات المفتوحة بأسمائها الفعلية في ENUM الجدول (لا اجتهاد)
        $counts['req_sla'] = (int) roleBoardScalar($gate, array('scope' => array('q' => 'fin_requests')),
            "SELECT COUNT(*) FROM fin_requests q WHERE {TENANT_SCOPE} AND COALESCE(q.is_deleted,0)=0
             AND q.state IN('draft','under_review','pending_approval','returned')
             AND q.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");

        $counts['variance'] = (int) roleBoardScalar($gate,
            array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
            "SELECT COUNT(*) FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id
             WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0
             AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct)>10");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }   // «المُنجَز يختفي فورًا» (§9)
        }
    }

    if ($rid === 13) {
        $counts = array();
        // «حرجة» = أمرٌ مفتوحٌ بأولوية عاجلة/عالية (الحقول العربية بقيمها الحية)
        $counts['critical_down'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
            "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
             AND o.state IN('بلاغ','تنفيذ','فحص') AND o.priority IN('عاجلة','عالية')");
        // لا عمودَ مدةٍ مستهدفةٍ على الأمر — «فوق مدته» = مفتوحٌ منذ أكثر من 7 أيام
        // (تقريبٌ موثَّق حتى تُبنى الأزمنة المحسوبة — تدقيق الصيانة، المستوى الأول #1)
        $counts['order_overdue'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
            "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
             AND o.state IN('بلاغ','تنفيذ','فحص') AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $counts['pm_late'] = (int) roleBoardScalar($gate, array('scope' => array('p' => 'mnt_plan')),
            "SELECT COUNT(*) FROM mnt_plan p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.next_due_date IS NOT NULL AND p.next_due_date < CURDATE()");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }
        }
    }
    return $out;
}

/**
 * ③ موافقاتي — سجل العدّادات: لكل مصدرٍ تعريفٌ واحد فلا يُحسب في شاشتين
 * بقيمتين (UX-01 §10.3). المفتاحُ نفسُه المستعمل في nav_items.counter_source.
 */
function roleBoardApprovals($conn, $gate, $roleId, array $navBadges = array())
{
    $rid = intval($roleId);
    $out = array();
    // العدّادات المحسوبة سلفًا للسايدبار تُعاد استعمالًا — مصدرٌ واحدٌ لا اثنان
    $map = array(
        'FinRequests/dept_inbox.php'      => array('موافقات إدارتي',  'fa fa-inbox'),
        'FinRequests/finance_gateway.php' => array('الطلبات المالية', 'fa fa-building-columns'),
        'FinRequests/accountant_desk.php' => array('مكتب المحاسب',    'fa fa-calculator'),
        'Approvals/hours_approval.php'    => array('اعتماد الوحدات التشغيلية', 'fa fa-check-double'),
    );
    foreach ($map as $route => $meta) {
        $n = isset($navBadges[$route]) ? intval($navBadges[$route]) : 0;
        if ($n > 0) {
            $out[] = array('label' => $meta[0], 'icon' => $meta[1], 'count' => $n, 'href' => '../' . $route);
        }
    }
    return $out;
}

/** ⑦ عملي الأخير — من سجل النشاط القائم (لا جدولَ جديد). */
function roleBoardRecent($conn, $userId, $limit = 6)
{
    $out = array();
    $uid = intval($userId);
    if ($uid <= 0) { return $out; }
    try {
        $q = $conn->prepare(
            "SELECT screen_name, url, MAX(created_at) AS last_at
               FROM activity_logs
              WHERE user_id = ? AND screen_name IS NOT NULL AND screen_name <> ''
              GROUP BY screen_name, url
              ORDER BY last_at DESC LIMIT " . intval($limit));
        $q->bind_param('i', $uid);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) { $out[] = $r; }
        $q->close();
    } catch (\Throwable $t) { error_log('role_board recent: ' . $t->getMessage()); }
    return $out;
}

/**
 * ⑤ إنشاء سريع — أكثر ثلاث عملياتٍ استخدامًا للدور: تُشتق من المصدر الموحّد
 * (باب العمل اليومي) مرتَّبةً باستعمال المستخدم الفعلي من سجل النشاط.
 */
function roleBoardQuickActions($conn, $roleId, $userId, $limit = 3)
{
    $out = array();
    $rid = intval($roleId); $uid = intval($userId);
    try {
        $rows = array();
        $q = $conn->prepare(
            "SELECT n.label_ar, n.route, n.icon,
                    (SELECT COUNT(*) FROM activity_logs a
                      WHERE a.user_id = ? AND a.url LIKE CONCAT('%', n.route, '%')) AS uses
               FROM nav_items n
              WHERE n.role_id = ? AND n.active = 1 AND n.door = 'DAILY'
                AND (n.permission_code IS NULL OR EXISTS (
                      SELECT 1 FROM role_permissions p
                       WHERE p.module_id = n.module_id AND p.role_id = n.role_id AND p.can_view = 1))
              ORDER BY uses DESC, n.sort_order ASC LIMIT " . intval($limit));
        $q->bind_param('ii', $uid, $rid);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $q->close();
        $out = $rows;
    } catch (\Throwable $t) { error_log('role_board quick: ' . $t->getMessage()); }
    return $out;
}

/**
 * ② مهامي — ما يجب فعلُه الآن: صناديقُ العمل المفتوحة للدور بأزرار قفز.
 * تُبنى من المؤشرات ذات الطابع الإجرائي (لا من قائمةٍ ثابتة).
 */
function roleBoardTasks($conn, $gate, $roleId)
{
    $rid = intval($roleId);
    $out = array();
    if ($rid === 17) {
        // «جاهزة للتنفيذ» = approved (لا قيمةَ ready في ENUM الجدول)
        $n = (int) roleBoardScalar($gate, array('scope' => array('p' => 'fin_payments')),
            "SELECT COUNT(*) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.direction='disbursement' AND p.state='approved'");
        if ($n > 0) { $out[] = array('label' => 'مدفوعاتٌ جاهزةٌ للتنفيذ', 'count' => $n, 'href' => '../Finance/payments_fin.php', 'icon' => 'fa fa-money-bill-transfer'); }

        $n = (int) roleBoardScalar($gate, array('scope' => array('e' => 'fin_financial_events')),
            "SELECT COUNT(*) FROM fin_financial_events e WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 AND e.state='draft'");
        if ($n > 0) { $out[] = array('label' => 'معاملاتٌ واردةٌ تنتظر المراجعة', 'count' => $n, 'href' => '../Finance/events_list_fin.php', 'icon' => 'fa fa-receipt'); }

        $n = (int) roleBoardScalar($gate, array('scope' => array('u' => 'fin_unit_records')),
            "SELECT COUNT(*) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='matched'");
        if ($n > 0) { $out[] = array('label' => 'وحداتٌ تنتظر ختم المالية', 'count' => $n, 'href' => '../Finance/unit_records_fin.php', 'icon' => 'fa fa-scale-balanced'); }
    }

    if ($rid === 13) {
        // دورة الأمر بحالاتها العربية الخمس — كل حالةٍ مفتوحةٍ صندوقُ عملٍ بقفزة
        $map = array(
            array('بلاغ',  'بلاغاتٌ تنتظر بدءَ التنفيذ', 'fa fa-bell'),
            array('تنفيذ', 'أوامرُ قيد التنفيذ',          'fa fa-screwdriver-wrench'),
            array('فحص',   'أوامرُ تنتظر الفحصَ والإقفال', 'fa fa-clipboard-check'),
        );
        foreach ($map as $m) {
            $n = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
                "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND o.state=?", array($m[0]));
            if ($n > 0) { $out[] = array('label' => $m[1], 'count' => $n, 'href' => '../Maintenance/orders.php', 'icon' => $m[2]); }
        }
        // وقائيةُ الأسبوع المستحقة (UX-04 §6)
        $n = (int) roleBoardScalar($gate, array('scope' => array('p' => 'mnt_plan')),
            "SELECT COUNT(*) FROM mnt_plan p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.next_due_date IS NOT NULL AND p.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        if ($n > 0) { $out[] = array('label' => 'وقائيةُ الأسبوع المستحقة', 'count' => $n, 'href' => '../Maintenance/preventive_plans.php', 'icon' => 'fa fa-calendar-check'); }
    }
    return $out;
}
