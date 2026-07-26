<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * تعميم المصدر الموحّد — بذرُ كل الأدوار النشطة (UX-01 §10.4-③) — 2026-07-26
 * ───────────────────────────────────────────────────────────────────────────
 * يطبّق عقد التعميم المثبَت مع الدور الرائد على بقية الأدوار: قياسُ القائمة
 * المرئية الحالية لكل دورٍ من مصادرها الحية نفسها، وبذرُها 1:1 محافظًا:
 *
 *   ① ملكية modules (بوراثة الأب — منطق getDynamicNavLinks حرفيًّا)
 *   ② بوابة الطلبات (منطق ems_finreq_nav_links: routing + أدوار المالية)
 *   ③ منح المالية للأدوار التشغيلية (منطق ems_finance_nav_links)
 *   ④ الثوابت الحية: hours_approval (الأدوار 2..5) · الرابط الذكي للتقارير
 *     (emsreports لمن له صف report_role_permissions وإلا Reports/reports.php
 *      لمن يملك can_view عليها) · Settings/settings.php (كان بلا فحصٍ للجميع —
 *      يُبذر بكود صلاحيته فيُرشَّح: مَن بلا can_view يختفي عنه = **إصلاح
 *      الرابط الميت** المرصود في v8 §16-و، انحرافٌ عن 1:1 مقصودٌ وموثَّق)
 *
 * قاعدة المالك نافذة: التبعية = هذه الصفوف؛ والصلاحية تُرشّح وقت التصيير.
 * لا يُبذر ما لم يكن مرئيًّا اليوم (منح العرض الصامتة الـ263 تبقى صامتة).
 *
 * توزيع الأبواب: خريطة صريحة لكل مسارٍ من مصفوفة UX-01 §9 وأحكام وثائق
 * الإدارات (UX-04..10) — والضبطُ الفردي لاحقًا من شاشة admin/permissions/nav_items.
 *
 * يتخطى: -1 (السوبر خارج النموذج) والدور 1 (بُذر يدويًّا كرائد).
 * idempotent: ON DUPLICATE KEY (role_id, route). العلم لا يُمسّ هنا —
 * التفعيل قرارٌ منفصل بعد برهان كل دور.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/includes/env.php';
$mu = ems_env('DB_MIGRATOR_USER'); $mp = ems_env('DB_MIGRATOR_PASS');
if (!$mu || !$mp) { fwrite(STDERR, "FATAL: DB_MIGRATOR_USER/PASS مطلوبان.\n"); exit(1); }
$conn = new mysqli(ems_env('DB_HOST'), $mu, $mp, ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

/* خريطة المسار → الباب (من مصفوفة UX-01 §9 وأحكام وثائق الإدارات) */
$DOOR = array(
    // لوحات أدوار قائمة كشاشات
    'Finance/cfo_daily_board_fin.php' => 'HOME', 'Procurement/dashboard_proc.php' => 'HOME',
    'Transport/transfer_dashboard.php' => 'HOME',
    // العمل اليومي
    'Clients/activities.php' => 'DAILY', 'Clients/quotations.php' => 'DAILY', 'Clients/tenders.php' => 'DAILY',
    'Opportunities/opportunities.php' => 'DAILY', 'Oprators/select_project.php' => 'DAILY',
    'Timesheet/timesheet_type.php' => 'DAILY', 'movement/movement_operations.php' => 'DAILY',
    'movement/map_page.php' => 'DAILY',
    'Maintenance/orders.php' => 'DAILY', 'Maintenance/inspections.php' => 'DAILY', 'Maintenance/preventive_plans.php' => 'DAILY',
    'Procurement/requests_proc.php' => 'DAILY', 'Procurement/orders_proc.php' => 'DAILY',
    'Procurement/receipt_custody_proc.php' => 'DAILY', 'Procurement/issue_proc.php' => 'DAILY',
    'Tickets/tickets_list.php' => 'DAILY', 'Transport/transfer_orders_list.php' => 'DAILY',
    'Workforce/worker_leave_absence.php' => 'DAILY', 'Workforce/worker_movement.php' => 'DAILY',
    'Workforce/worker_evaluation.php' => 'DAILY', 'Workforce/workforce_requirement.php' => 'DAILY',
    'Finance/payments_fin.php' => 'DAILY', 'Finance/bank_reconciliation_fin.php' => 'DAILY',
    'FinRequests/request_form.php' => 'DAILY', 'FinRequests/my_requests.php' => 'DAILY',
    'FinRequests/accountant_desk.php' => 'DAILY',
    // المتابعة والموافقات
    'Approvals/hours_approval.php' => 'APPR', 'FinRequests/dept_inbox.php' => 'APPR',
    'FinRequests/finance_gateway.php' => 'APPR', 'Clients/commercial_risks.php' => 'APPR',
    'Clients/readiness_lines.php' => 'APPR', 'Finance/budget_form_fin.php' => 'APPR',
    'Finance/unit_records_fin.php' => 'APPR', 'Finance/variance_monitor_fin.php' => 'APPR',
    'Finance/dues_fin.php' => 'APPR', 'Finance/periods_fin.php' => 'APPR',
    'Finance/import_events_fin.php' => 'APPR', 'Transport/transfer_requests.php' => 'APPR',
    'Workforce/worker_settlement.php' => 'APPR',
    // السجلات الرئيسية
    'Clients/clients.php' => 'REC', 'Projects/projects.php' => 'REC', 'Contracts/contracts.php' => 'REC',
    'Clients/contract_amendments.php' => 'REC', 'Clients/contract_commitments.php' => 'REC',
    'Clients/contract_events.php' => 'REC',
    'Employees/employees.php' => 'REC', 'Employees/equipment_operators.php' => 'REC',
    'Equipments/equipments_fleet.php' => 'REC', 'Suppliers/suppliers.php' => 'REC',
    'Suppliers/supplierscontracts.php' => 'REC', 'Procurement/stock_proc.php' => 'REC',
    'Procurement/suppliers_proc.php' => 'REC', 'Workforce/worker_contract.php' => 'REC',
    'Workforce/worker_register.php' => 'REC', 'Workforce/housing_units.php' => 'REC',
    'main/project_users.php' => 'REC', 'main/all_assistants.php' => 'REC',
    'Finance/events_list_fin.php' => 'REC', 'Finance/journal_form_fin.php' => 'REC',
    'Finance/assets_fin.php' => 'REC', 'Finance/funding_fin.php' => 'REC',
    // التقارير والتحليلات
    'Reports/reports.php' => 'REP', 'emsreports/index.php' => 'REP',
    'Finance/cost_report_fin.php' => 'REP', 'Finance/financial_statements_fin.php' => 'REP',
    'Finance/executive_dashboard_fin.php' => 'REP', 'Finance/management_accounting_fin.php' => 'REP',
    'Finance/cash_forecast_fin.php' => 'REP', 'Finance/supplier_statement_fin.php' => 'REP',
    'FinRequests/cycle_time_board.php' => 'REP', 'FinRequests/requests_reports.php' => 'REP',
    'Tickets/ticket_dashboard.php' => 'REP', 'Workforce/worker_worklog.php' => 'REP',
    // الإعدادات والتدقيق
    'Settings/settings.php' => 'SET', 'main/users.php' => 'SET', 'ActivityLogs/activity_logs.php' => 'SET',
    'Clients/pricelists.php' => 'SET', 'Clients/products.php' => 'SET', 'Clients/units_of_measure.php' => 'SET',
    'Employees/job_titles.php' => 'SET', 'Employees/employee_roles.php' => 'SET',
    'Equipments/equipments_types.php' => 'SET', 'Equipments/fleet_models.php' => 'SET',
    'Equipments/fleet_depreciation_profiles.php' => 'SET', 'Equipments/manage_failure_codes.php' => 'SET',
    'Finance/accounts_fin.php' => 'SET', 'Finance/accountants_fin.php' => 'SET',
    'Finance/tax_fin.php' => 'SET', 'Finance/maintenance_provision_fin.php' => 'SET',
    'Finance/operator_pay_fin.php' => 'SET', 'FinRequests/routing_admin.php' => 'SET',
    'FinRequests/effect_map.php' => 'SET',
    'Maintenance/master_data.php' => 'SET', 'Procurement/items_proc.php' => 'SET',
    'Procurement/master_data_proc.php' => 'SET', 'Procurement/reordering_proc.php' => 'SET',
    'Tickets/ticket_types_config.php' => 'SET', 'Tickets/ticket_categories_config.php' => 'SET',
    'Tickets/ticket_sla_config.php' => 'SET', 'Tickets/ticket_escalation_config.php' => 'SET',
    'Tickets/ticket_recurrence.php' => 'SET',
    'Transport/transfer_types_config.php' => 'SET', 'Transport/transfer_cost_rules_config.php' => 'SET',
    'Transport/trs_locations_config.php' => 'SET',
);

/* ───────── أدوات ───────── */

$one = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_assoc() : null; };
$all = function ($sql) use ($conn) { $out = array(); $r = $conn->query($sql); if ($r) while ($x = $r->fetch_assoc()) $out[] = $x; return $out; };

/** أدنى صف شاشةٍ لمسار (نمط الحارس المركزي). */
$moduleByRoute = function ($route) use ($conn) {
    $s = $conn->prepare("SELECT id, code, name, COALESCE(NULLIF(TRIM(icon),''),'fa fa-link') icon, group_id FROM modules WHERE code = ? ORDER BY id LIMIT 1");
    $s->bind_param('s', $route); $s->execute();
    return $s->get_result()->fetch_assoc();
};

/* توجيه الطلبات (كل الشركات — العرض عالمي كما في ems_finreq_nav_links) */
$routing = $all("SELECT requester_roles, reviewer_role_id, manager_role_id FROM fin_request_routing WHERE is_active = 1");

/* ───────── قياس القائمة المرئية الحالية لدورٍ ───────── */
$currentVisible = function ($rid) use ($conn, $all, $one, $routing) {
    $rid = intval($rid);
    $out = array();   // route => [label, icon, group_id|null, source]

    // ① ملكية modules بوراثة الأب (منطق getDynamicNavLinks)
    $owned = $all(
        "SELECT m.code, m.name, COALESCE(NULLIF(TRIM(m.icon),''),'fa fa-link') icon, m.group_id, m.owner_role_id
         FROM modules m INNER JOIN roles mr ON m.owner_role_id = mr.id
         WHERE m.owner_role_id IN (SELECT id FROM roles WHERE id = $rid
                                   UNION SELECT parent_role_id FROM roles WHERE id = $rid AND parent_role_id IS NOT NULL)
           AND (mr.status = '1' OR mr.status = 1) AND m.is_link = '1'
         ORDER BY m.display_order, m.id");
    foreach ($owned as $m) {
        if (!isset($out[$m['code']])) {
            // المجموعة تُحمل فقط إن كانت لمجموعات الدور المبذور نفسه
            $gid = null;
            if (!empty($m['group_id'])) {
                $g = $one("SELECT owner_role_id FROM link_groups WHERE id = " . intval($m['group_id']) . " AND is_active = 1");
                if ($g && intval($g['owner_role_id']) === $rid) { $gid = intval($m['group_id']); }
            }
            $out[$m['code']] = array($m['name'], $m['icon'], $gid, 'ملكية');
        }
    }

    // ② بوابة الطلبات (منطق ems_finreq_nav_links حرفيًّا)
    $canCreate = false; $canReview = false; $sr = strval($rid);
    foreach ($routing as $rt) {
        $creators = array_map('trim', explode(',', strval($rt['requester_roles'])));
        if (in_array($sr, $creators, true)) { $canCreate = true; }
        if (strval($rt['reviewer_role_id']) === $sr || strval($rt['manager_role_id']) === $sr) { $canReview = true; }
    }
    $isAcct = in_array($sr, array('17', '18'), true);
    $isFin  = in_array($sr, array('17', '18', '19', '20', '21', '22'), true);
    $fr = array();
    if ($canCreate) { $fr['FinRequests/request_form.php'] = array('طلب مالي جديد', 'fa fa-file-circle-plus');
                      $fr['FinRequests/my_requests.php']  = array('طلباتي المالية', 'fa fa-list-check'); }
    if ($canReview) { $fr['FinRequests/dept_inbox.php'] = array('موافقات إدارتي', 'fa fa-inbox'); }
    if ($isAcct)    { $fr['FinRequests/accountant_desk.php'] = array('مكتب المحاسب', 'fa fa-calculator'); }
    if ($isFin)     { $fr['FinRequests/finance_gateway.php'] = array('الطلبات المالية', 'fa fa-building-columns');
                      $fr['FinRequests/cycle_time_board.php'] = array('زمن دورة الطلبات', 'fa fa-stopwatch');
                      $fr['FinRequests/requests_reports.php'] = array('تقارير الطلبات المالية', 'fa fa-chart-column'); }
    foreach ($fr as $code => $meta) {
        if (!isset($out[$code])) { $out[$code] = array($meta[0], $meta[1], null, 'بوابة الطلبات'); }
    }

    // ③ منح المالية للأدوار التشغيلية (منطق ems_finance_nav_links حرفيًّا)
    if (!$isFin) {
        foreach ($all("SELECT m.code, m.name, COALESCE(NULLIF(TRIM(m.icon),''),'fa fa-coins') icon
                       FROM modules m JOIN role_permissions rp ON rp.module_id = m.id
                       WHERE rp.role_id = $rid AND rp.can_view = 1 AND m.code LIKE 'Finance/%' AND m.is_link = '1'
                       ORDER BY m.display_order, m.id") as $m) {
            if (!isset($out[$m['code']])) { $out[$m['code']] = array($m['name'], $m['icon'], null, 'منح المالية'); }
        }
    }

    // ④ الثوابت الحية
    if (in_array($sr, array('2', '3', '4', '5'), true)) {
        if (!isset($out['Approvals/hours_approval.php'])) {
            $out['Approvals/hours_approval.php'] = array('اعتماد الوحدات التشغيلية', 'fa fa-check-double', null, 'ثابت');
        }
    }
    // الرابط الذكي للتقارير
    $hasNew = $one("SELECT 1 x FROM report_role_permissions WHERE role_id = $rid LIMIT 1") !== null;
    if ($hasNew) {
        if (!isset($out['emsreports/index.php'])) { $out['emsreports/index.php'] = array('التقارير', 'fas fa-chart-pie', null, 'ذكي'); }
    } else {
        $old = $one("SELECT 1 x FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                     WHERE rp.role_id = $rid AND rp.can_view = 1 AND m.code = 'Reports/reports.php' LIMIT 1") !== null;
        if ($old && !isset($out['Reports/reports.php'])) { $out['Reports/reports.php'] = array('مركز التقارير', 'fas fa-chart-pie', null, 'ذكي'); }
    }
    // الإعدادات الثابت — يُبذر مفحوصًا (إصلاح الميت)
    if (!isset($out['Settings/settings.php'])) {
        $out['Settings/settings.php'] = array('الإعدادات', 'fa fa-cog', null, 'ثابت');
    }
    return $out;
};

/* ───────── البذر ───────── */

$roles = $all("SELECT id FROM roles WHERE (status = '1' OR status = 1) AND id NOT IN (-1, 1) ORDER BY id");
$ins = $conn->prepare(
    "INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE door = VALUES(door), group_id = VALUES(group_id), module_id = VALUES(module_id),
       label_ar = VALUES(label_ar), icon = VALUES(icon), sort_order = VALUES(sort_order),
       permission_code = VALUES(permission_code)");

$totalRows = 0; $unmapped = array();
foreach ($roles as $r) {
    $rid = intval($r['id']);
    $vis = $currentVisible($rid);
    $sort = array('HOME' => 0, 'DAILY' => 0, 'APPR' => 0, 'REC' => 0, 'REP' => 0, 'SET' => 0);
    $n = 0;
    foreach ($vis as $route => $meta) {
        list($label, $icon, $gid) = $meta;
        $door = $DOOR[$route] ?? null;
        if ($door === null) { $unmapped[$route] = true; $door = 'REC'; }
        $sort[$door] += 10;
        $mod = $moduleByRoute($route);
        $moduleId = $mod ? intval($mod['id']) : null;
        $permCode = $mod ? $mod['code'] : null;
        $so = $sort[$door];
        $ins->bind_param('isiisssis', $rid, $door, $gid, $moduleId, $label, $route, $icon, $so, $permCode);
        if ($ins->execute()) { $n++; }
        else { echo "  ✘ دور {$rid} · {$route}: " . $ins->error . "\n"; }
    }
    $totalRows += $n;
    echo "  ✔ دور {$rid}: {$n} عنصرًا\n";
}

// عدّاد اعتماد الوحدات للأدوار الموروثة من الثابت القديم
$conn->query("UPDATE nav_items SET counter_source = 'hours_approval' WHERE route = 'Approvals/hours_approval.php' AND counter_source IS NULL");

echo "\nالمجموع: {$totalRows} صفًّا";
if ($unmapped) { echo " · مساراتٌ خارج الخريطة (وُضعت REC مؤقتًا): " . implode('، ', array_keys($unmapped)); }
echo "\n";
exit(0);
