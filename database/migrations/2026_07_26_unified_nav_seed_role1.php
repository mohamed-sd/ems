<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * بذرُ المصدر الموحّد — الدور الرائد 1 «ادارة التشغيل» — 2026-07-26
 * ───────────────────────────────────────────────────────────────────────────
 * أولُ دورٍ يُحوَّل إلى nav_items (UX-01 §10.4-② تشغيلٌ مزدوجٌ بعلَمٍ لكل دور).
 * المقيس قبل البذر: يرى 8 روابطَ (ما يملكه) وعنده صلاحيةُ عرضٍ على 18 أخرى
 * لا يراها — منها main/project_users.php مسجَّلًا **خمس مرات** بخمسة معرّفات
 * لخمسة مُلّاك (أحدُ «الروابط الخمسة المكرّرة» في v8 §16-و)، وReports/reports
 * مرتين. فالبذرُ يوحّد بالمسار: صفٌّ واحدٌ لكل route (uq_nav_role_route).
 *
 * توزيعُ الأبواب من UX-01 §8.1 (ادارة التشغيل) ومصفوفة §9، باستثناءٍ واحدٍ
 * معلَن: «سجل النشاط» وضعته §9 في التقارير، والدستورُ UX-00 §6-⑥ و§2-٨ يوجب
 * جمعَ سجلات التدقيق في باب الإعدادات والتدقيق — والدستورُ أعلى (UX-00 §1).
 * وبه تبقى مجموعةُ «النظام والمتابعة» كاملةً في بابٍ واحدٍ بلا شطر.
 *
 * الصلاحيةُ لا تُمنح هنا ولا تُسحب: permission_code يشير لكود الشاشة، والعرضُ
 * يُفحص وقت التصيير بـ can_view. فالبذرُ يقرر **المكان** لا **الحق**.
 *
 * idempotent: INSERT ... ON DUPLICATE KEY UPDATE على (role_id, route).
 * التشغيل: php database/migrations/2026_07_26_unified_nav_seed_role1.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
mysqli_report(MYSQLI_REPORT_OFF);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/includes/env.php';
$mu = ems_env('DB_MIGRATOR_USER'); $mp = ems_env('DB_MIGRATOR_PASS');
if (!$mu || !$mp) { fwrite(STDERR, "FATAL: DB_MIGRATOR_USER/PASS مطلوبان في .env.\n"); exit(1); }
$conn = new mysqli(ems_env('DB_HOST'), $mu, $mp, ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

const ROLE = 1;

/** معرّفات مجموعات الدور القائمة (تبقى داخل أبوابها). */
$G = array();
$r = $conn->query("SELECT id, name FROM link_groups WHERE owner_role_id = " . ROLE);
while ($g = $r->fetch_assoc()) { $G[$g['name']] = intval($g['id']); }

/**
 * الخريطة: [الباب, المجموعة أو null, الترتيب, اسم العرض, المسار, الأيقونة, عدّاد]
 * كلُّ مسارٍ مرةً واحدة — التكرارُ الخماسي ينحلّ هنا بنيويًّا.
 */
$MAP = array(
    // ② العمل اليومي — الإدخال والإنشاء
    array('DAILY', null, 10, 'طلب مالي جديد',        'FinRequests/request_form.php',  'fa fa-file-circle-plus', null),
    array('DAILY', null, 20, 'طلباتي المالية',        'FinRequests/my_requests.php',   'fa fa-folder-open',      null),
    array('DAILY', null, 30, 'تسجيل الوحدات',         'Timesheet/timesheet_type.php',  'fa fa-business-time',    null),

    // ③ المتابعة والموافقات — صناديقُ الانتظار بعدّاداتها
    array('APPR', null, 10, 'موافقات إدارتي',          'FinRequests/dept_inbox.php',    'fa fa-inbox',            'finreq_dept_inbox'),
    array('APPR', null, 20, 'مطابقة الوحدات اليومية',  'Finance/unit_records_fin.php',  'fa fa-scale-balanced',   null),
    array('APPR', null, 30, 'الميزانية والانحراف',     'Finance/budget_form_fin.php',   'fa fa-chart-line',       null),

    // ④ السجلات الرئيسية — الكيانات، والبطاقاتُ تُفتح منها
    array('REC', $G['علاقات العملاء'] ?? null, 10, 'العملاء',            'Clients/clients.php',            'fa fa-users',          null),
    array('REC', $G['علاقات العملاء'] ?? null, 20, 'المشاريع',           'Projects/projects.php',          'fa fa-folder-open',    null),
    array('REC', $G['علاقات العملاء'] ?? null, 30, 'العقود',             'Contracts/contracts.php',        'fa fa-file-contract',  null),
    array('REC', null, 40, 'الموظفون',                                   'Employees/employees.php',        'fa fa-id-card',        null),
    array('REC', null, 50, 'المعدات',                                    'Equipments/equipments_fleet.php','fa fa-tractor',        null),
    array('REC', null, 60, 'المعاونون',                                  'main/project_users.php',         'fa fa-users-cog',      null),
    array('REC', null, 70, 'المعاملات المالية',                          'Finance/events_list_fin.php',    'fa fa-receipt',        null),

    // ⑤ التقارير والتحليلات — عبر مركزها
    array('REP', null, 10, 'مركز التقارير',      'Reports/reports.php',         'fa fa-chart-pie',  null),
    array('REP', null, 20, 'التكاليف والربحية',  'Finance/cost_report_fin.php', 'fa fa-coins',      null),

    // ⑥ الإعدادات والتدقيق — خلف الصلاحية حصرًا، ومعها سجلُّ التدقيق
    array('SET', $G['الأصول والتشغيل'] ?? null, 10, 'أنواع المعدات',       'Equipments/equipments_types.php',           'fa fa-screwdriver-wrench', null),
    array('SET', $G['الأصول والتشغيل'] ?? null, 20, 'الأنواع والموديلات',  'Equipments/fleet_models.php',                'fa fa-list',               null),
    array('SET', $G['الأصول والتشغيل'] ?? null, 30, 'إعداد الإهلاك',       'Equipments/fleet_depreciation_profiles.php', 'fa fa-percent',            null),
    array('SET', $G['النظام والمتابعة'] ?? null, 40, 'الصلاحيات',          'main/users.php',                             'fa fa-user-shield',        null),
    array('SET', $G['النظام والمتابعة'] ?? null, 50, 'سجل النشاط',         'ActivityLogs/activity_logs.php',             'fa fa-clock-rotate-left',  null),
    array('SET', null, 60, 'الإعدادات',                                    'Settings/settings.php',                      'fa fa-cog',                null),
);

/** كودُ الشاشة ومعرّفُها من سجل الشاشات — أدنى معرِّفٍ للمسار (التكرارُ ينحلّ). */
$stmtMod = $conn->prepare("SELECT id, code FROM modules WHERE code = ? ORDER BY id LIMIT 1");

$ins = $conn->prepare(
    "INSERT INTO nav_items
       (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, counter_source, permission_code, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       door = VALUES(door), group_id = VALUES(group_id), module_id = VALUES(module_id),
       label_ar = VALUES(label_ar), icon = VALUES(icon), sort_order = VALUES(sort_order),
       counter_source = VALUES(counter_source), permission_code = VALUES(permission_code), active = 1"
);

$n = 0; $noMod = 0;
foreach ($MAP as $m) {
    list($door, $gid, $sort, $label, $route, $icon, $counter) = $m;
    $stmtMod->bind_param('s', $route);
    $stmtMod->execute();
    $mod = $stmtMod->get_result()->fetch_assoc();
    $moduleId = $mod ? intval($mod['id']) : null;
    $permCode = $mod ? $mod['code'] : null;   // NULL ⇒ ظهورٌ بلا فحصِ صلاحية
    if (!$mod) { $noMod++; echo "  ! لا صفَّ شاشةٍ للمسار {$route} — يظهر بلا فحصِ صلاحية\n"; }

    $role = ROLE;
    // 10 وسيطًا: role(i) door(s) group(i) module(i) label(s) route(s) icon(s) sort(i) counter(s) perm(s)
    $ins->bind_param('isiisssiss', $role, $door, $gid, $moduleId, $label, $route, $icon, $sort, $counter, $permCode);
    if (!$ins->execute()) { echo "  ✘ {$route}: " . $ins->error . "\n"; continue; }
    $n++;
}

$byDoor = array();
$r = $conn->query("SELECT door, COUNT(*) c FROM nav_items WHERE role_id = " . ROLE . " GROUP BY door");
while ($x = $r->fetch_assoc()) { $byDoor[] = $x['door'] . '=' . $x['c']; }

echo "\nبُذر للدور " . ROLE . ": {$n} عنصرًا · بلا صفِّ شاشة: {$noMod}\n";
echo "التوزيع: " . implode(' · ', $byDoor) . "\n";
exit(0);
