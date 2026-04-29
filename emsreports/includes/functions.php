<?php
/**
 * دوال مساعدة لنظام التقارير
 * يعتمد على جدول report_role_permissions البسيط (role_id + report_code)
 */

/**
 * كتالوج التقارير الكامل - تعريف ثابت في PHP
 * المفتاح هو report_code
 */
function getReportsCatalog() {
    return [
        // ── ساعات العمل ────────────────────────────────────────
        'timesheet_summary' => [
            'code'        => 'timesheet_summary',
            'name_ar'     => 'ملخص ساعات العمل',
            'icon'        => 'fa-clock',
            'category'    => 'timesheet',
            'description' => 'ملخص شامل لساعات العمل مجمعة حسب المشروع والمعدة',
            'url'         => 'reports/timesheet_summary.php',
        ],
        'timesheet_detailed' => [
            'code'        => 'timesheet_detailed',
            'name_ar'     => 'تفاصيل ساعات العمل',
            'icon'        => 'fa-list-alt',
            'category'    => 'timesheet',
            'description' => 'تفاصيل كاملة لكل إدخال ساعات عمل',
            'url'         => 'reports/timesheet_detailed.php',
        ],
        'timesheet_by_project' => [
            'code'        => 'timesheet_by_project',
            'name_ar'     => 'ساعات العمل حسب المشروع',
            'icon'        => 'fa-project-diagram',
            'category'    => 'timesheet',
            'description' => 'تقرير ساعات العمل مجمع حسب المشروع',
            'url'         => 'reports/timesheet_by_project.php',
        ],
        'timesheet_by_equipment' => [
            'code'        => 'timesheet_by_equipment',
            'name_ar'     => 'ساعات العمل حسب المعدة',
            'icon'        => 'fa-tractor',
            'category'    => 'timesheet',
            'description' => 'تقرير ساعات العمل مجمع حسب المعدة',
            'url'         => 'reports/timesheet_by_equipment.php',
        ],
        'timesheet_by_driver' => [
            'code'        => 'timesheet_by_driver',
            'name_ar'     => 'ساعات العمل حسب المشغل',
            'icon'        => 'fa-id-card',
            'category'    => 'timesheet',
            'description' => 'تقرير ساعات العمل مجمع حسب المشغل',
            'url'         => 'reports/timesheet_by_driver.php',
        ],

        // ── المشاريع ────────────────────────────────────────────
        'project_summary' => [
            'code'        => 'project_summary',
            'name_ar'     => 'ملخص المشاريع',
            'icon'        => 'fa-folder-open',
            'category'    => 'projects',
            'description' => 'نظرة عامة على جميع المشاريع وحالتها',
            'url'         => 'reports/project_summary.php',
        ],
        'project_detailed' => [
            'code'        => 'project_detailed',
            'name_ar'     => 'تفاصيل المشاريع',
            'icon'        => 'fa-file-alt',
            'category'    => 'projects',
            'description' => 'تقرير تفصيلي شامل لكل مشروع',
            'url'         => 'reports/project_detailed.php',
        ],

        // ── العقود ──────────────────────────────────────────────
        'contracts_summary' => [
            'code'        => 'contracts_summary',
            'name_ar'     => 'ملخص العقود',
            'icon'        => 'fa-file-contract',
            'category'    => 'contracts',
            'description' => 'نظرة عامة على جميع عقود المشاريع',
            'url'         => 'reports/contracts_summary.php',
        ],
        'contracts_detailed' => [
            'code'        => 'contracts_detailed',
            'name_ar'     => 'تفاصيل العقود',
            'icon'        => 'fa-file-signature',
            'category'    => 'contracts',
            'description' => 'تقرير تفصيلي لعقود المشاريع',
            'url'         => 'reports/contracts_detailed.php',
        ],

        // ── الموردون ────────────────────────────────────────────
        'supplier_contracts_summary' => [
            'code'        => 'supplier_contracts_summary',
            'name_ar'     => 'ملخص عقود الموردين',
            'icon'        => 'fa-handshake',
            'category'    => 'suppliers',
            'description' => 'ملخص شامل لعقود الموردين وأدائهم',
            'url'         => 'reports/supplier_contracts_summary.php',
        ],
        'supplier_contracts_detailed' => [
            'code'        => 'supplier_contracts_detailed',
            'name_ar'     => 'تفاصيل عقود الموردين',
            'icon'        => 'fa-clipboard-list',
            'category'    => 'suppliers',
            'description' => 'تقرير تفصيلي لكل عقد مورد',
            'url'         => 'reports/supplier_contracts_detailed.php',
        ],
        'supplier_timesheet' => [
            'code'        => 'supplier_timesheet',
            'name_ar'     => 'ساعات الموردين',
            'icon'        => 'fa-business-time',
            'category'    => 'suppliers',
            'description' => 'تقرير ساعات العمل مجمع حسب المورد',
            'url'         => 'reports/supplier_timesheet.php',
        ],
        'supplier_equipment_performance' => [
            'code'        => 'supplier_equipment_performance',
            'name_ar'     => 'أداء معدات الموردين',
            'icon'        => 'fa-chart-bar',
            'category'    => 'suppliers',
            'description' => 'تقرير أداء المعدات مقارنة بأهداف الموردين',
            'url'         => 'reports/supplier_equipment_performance.php',
        ],

        // ── الأسطول ─────────────────────────────────────────────
        'fleet_equipment_summary' => [
            'code'        => 'fleet_equipment_summary',
            'name_ar'     => 'ملخص المعدات',
            'icon'        => 'fa-tractor',
            'category'    => 'fleet',
            'description' => 'نظرة عامة على جميع المعدات وحالتها',
            'url'         => 'reports/fleet_equipment_summary.php',
        ],
        'fleet_equipment_detailed' => [
            'code'        => 'fleet_equipment_detailed',
            'name_ar'     => 'تفاصيل المعدات',
            'icon'        => 'fa-cogs',
            'category'    => 'fleet',
            'description' => 'تقرير تفصيلي لكل معدة بما فيها ساعات التشغيل',
            'url'         => 'reports/fleet_equipment_detailed.php',
        ],
        'fleet_operations' => [
            'code'        => 'fleet_operations',
            'name_ar'     => 'تقرير التشغيل',
            'icon'        => 'fa-play-circle',
            'category'    => 'fleet',
            'description' => 'تقرير عمليات تشغيل المعدات عبر المشاريع',
            'url'         => 'reports/fleet_operations.php',
        ],
        'fleet_timesheet' => [
            'code'        => 'fleet_timesheet',
            'name_ar'     => 'ساعات تشغيل الأسطول',
            'icon'        => 'fa-clock',
            'category'    => 'fleet',
            'description' => 'ساعات العمل الإجمالية لجميع معدات الأسطول',
            'url'         => 'reports/fleet_timesheet.php',
        ],

        // ── المشغلون ────────────────────────────────────────────
        'drivers_summary' => [
            'code'        => 'drivers_summary',
            'name_ar'     => 'ملخص المشغلين',
            'icon'        => 'fa-id-badge',
            'category'    => 'drivers',
            'description' => 'نظرة عامة على جميع المشغلين وحالتهم',
            'url'         => 'reports/drivers_summary.php',
        ],
        'drivers_detailed' => [
            'code'        => 'drivers_detailed',
            'name_ar'     => 'تفاصيل المشغلين',
            'icon'        => 'fa-user-cog',
            'category'    => 'drivers',
            'description' => 'تقرير تفصيلي لكل مشغل',
            'url'         => 'reports/drivers_detailed.php',
        ],
        'drivers_timesheet' => [
            'code'        => 'drivers_timesheet',
            'name_ar'     => 'ساعات عمل المشغلين',
            'icon'        => 'fa-user-clock',
            'category'    => 'drivers',
            'description' => 'تقرير ساعات العمل مجمع لكل مشغل',
            'url'         => 'reports/drivers_timesheet.php',
        ],
        'drivers_contracts' => [
            'code'        => 'drivers_contracts',
            'name_ar'     => 'عقود المشغلين',
            'icon'        => 'fa-file-alt',
            'category'    => 'drivers',
            'description' => 'تقرير عقود المشغلين وتفاصيلها',
            'url'         => 'reports/drivers_contracts.php',
        ],

        // ── التشغيل ─────────────────────────────────────────────
        'operations_summary' => [
            'code'        => 'operations_summary',
            'name_ar'     => 'ملخص العمليات',
            'icon'        => 'fa-tasks',
            'category'    => 'operations',
            'description' => 'نظرة عامة على جميع عمليات التشغيل',
            'url'         => 'reports/operations_summary.php',
        ],
        'operations_detailed' => [
            'code'        => 'operations_detailed',
            'name_ar'     => 'تفاصيل العمليات',
            'icon'        => 'fa-list-ul',
            'category'    => 'operations',
            'description' => 'تقرير تفصيلي لكل عملية تشغيل',
            'url'         => 'reports/operations_detailed.php',
        ],
    ];
}

/**
 * التحقق إذا كان الدور يملك صلاحية عرض تقرير معين
 */
function checkReportPermission($conn, $reportCode, $roleId) {
    $roleId = intval($roleId);
    // السوبر ادمن يرى كل التقارير
    if ($roleId === -1) return true;
    $reportCode = mysqli_real_escape_string($conn, $reportCode);
    $result = @mysqli_query($conn, "SELECT id FROM report_role_permissions WHERE role_id = $roleId AND report_code = '$reportCode' LIMIT 1");
    // إذا الجدول غير موجود أو مديرو المشاريع (role=1) — منح الوصول
    if (!$result) return ($roleId === 1);
    return (mysqli_num_rows($result) > 0);
}

/**
 * جلب قائمة التقارير المتاحة للدور مع بيانات الكتالوج
 */
function getAvailableReports($conn, $roleId) {
    $roleId  = intval($roleId);
    $catalog = getReportsCatalog();

    // السوبر ادمن يرى كل التقارير
    if ($roleId === -1) return array_values($catalog);

    // جلب كل الأكواد المسموح بها لهذا الدور
    $result = @mysqli_query($conn, "SELECT report_code FROM report_role_permissions WHERE role_id = $roleId");
    // إذا الجدول غير موجود أو مدير مشاريع — أظهر كل التقارير
    if (!$result) return ($roleId === 1) ? array_values($catalog) : [];

    $allowed = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $allowed[$row['report_code']] = true;
    }

    // دمج مع الكتالوج
    $available = [];
    foreach ($catalog as $code => $info) {
        if (isset($allowed[$code])) {
            $available[] = $info;
        }
    }
    return $available;
}

/**
 * ترجمة أسماء الفئات إلى العربية
 */
function getCategoryLabel($category) {
    $labels = [
        'timesheet'  => 'ساعات العمل',
        'projects'   => 'المشاريع',
        'contracts'  => 'العقود',
        'suppliers'  => 'الموردون',
        'fleet'      => 'الأسطول',
        'drivers'    => 'المشغلون',
        'operations' => 'التشغيل',
    ];
    return isset($labels[$category]) ? $labels[$category] : $category;
}

/**
 * أيقونة الفئة
 */
function getCategoryIcon($category) {
    $icons = [
        'timesheet'  => 'fa-clock',
        'projects'   => 'fa-folder-open',
        'contracts'  => 'fa-file-contract',
        'suppliers'  => 'fa-handshake',
        'fleet'      => 'fa-tractor',
        'drivers'    => 'fa-id-card',
        'operations' => 'fa-cogs',
    ];
    return isset($icons[$category]) ? $icons[$category] : 'fa-chart-bar';
}

/**
 * تنسيق التاريخ بالعربية
 */
function formatDateArabic($date) {
    if (empty($date) || $date === '0000-00-00') return '-';
    $months = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
               'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $ts  = strtotime($date);
    if (!$ts) return $date;
    $day   = date('j', $ts);
    $month = $months[(int)date('n', $ts)];
    $year  = date('Y', $ts);
    return "$day $month $year";
}

/**
 * حساب مجموع الساعات من دقائق
 */
function calculateTotalHours($totalMinutes) {
    $hours   = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return sprintf('%d:%02d', $hours, $minutes);
}

/**
 * تنسيق العملة
 */
function formatCurrency($value, $currency = 'دولار') {
    return number_format(floatval($value), 2) . ' ' . $currency;
}

// ═══════════════════════════════════════════════════════════════════════════
// دوال SaaS — تحديد نطاق الشركة
// ═══════════════════════════════════════════════════════════════════════════

/**
 * بناء شرط نطاق الشركة لجدول معين
 * يستخدم company_id مباشرة إذا وُجد، وإلا يرجع إلى created_by
 */
function rptCompanyScope($conn, $alias, $tableName, $companyId, $isSuperAdmin) {
    if ($isSuperAdmin || $companyId <= 0) return '1=1';
    $prefix = $alias !== '' ? $alias . '.' : '';
    if (db_table_has_column($conn, $tableName, 'company_id')) {
        return $prefix . 'company_id = ' . intval($companyId);
    }
    if ($tableName === 'project') {
        return "EXISTS (SELECT 1 FROM users __u WHERE __u.id = {$prefix}created_by AND __u.company_id = " . intval($companyId) . ")";
    }
    return '1=1';
}

/**
 * جلب المشاريع للـ dropdown (مصفوفة بـ company)
 */
function getProjectsForDropdown($conn, $companyId, $isSuperAdmin) {
    $scope = rptCompanyScope($conn, 'p', 'project', $companyId, $isSuperAdmin);
    $sql   = "SELECT p.id, p.name, p.project_code FROM project p WHERE p.status = 1 AND ($scope) ORDER BY p.name ASC";
    $res   = mysqli_query($conn, $sql);
    $list  = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
    return $list;
}

/**
 * جلب الموردين للـ dropdown
 */
function getSuppliersForDropdown($conn, $companyId, $isSuperAdmin) {
    $scope = rptCompanyScope($conn, 's', 'suppliers', $companyId, $isSuperAdmin);
    $sql   = "SELECT id, name FROM suppliers s WHERE status = 1 AND ($scope) ORDER BY name ASC";
    $res   = mysqli_query($conn, $sql);
    $list  = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
    return $list;
}

/**
 * جلب المشغلين للـ dropdown
 */
function getDriversForDropdown($conn, $companyId, $isSuperAdmin) {
    $scope = rptCompanyScope($conn, 'd', 'drivers', $companyId, $isSuperAdmin);
    $sql   = "SELECT id, name, driver_code FROM drivers d WHERE status = 1 AND ($scope) ORDER BY name ASC";
    $res   = mysqli_query($conn, $sql);
    $list  = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
    return $list;
}

/**
 * تحويل قائمة إلى خيارات HTML لـ select
 */
function rptSelectOptions($list, $valueKey, $labelKey, $selected = 0, $extra = '') {
    $html = '<option value="">— الكل —</option>';
    foreach ($list as $item) {
        $val   = htmlspecialchars((string)$item[$valueKey], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)$item[$labelKey], ENT_QUOTES, 'UTF-8');
        $sel   = ($item[$valueKey] == $selected) ? ' selected' : '';
        $info  = $extra && isset($item[$extra]) ? ' (' . htmlspecialchars($item[$extra], ENT_QUOTES, 'UTF-8') . ')' : '';
        $html .= "<option value=\"{$val}\"{$sel}>{$label}{$info}</option>";
    }
    return $html;
}

// db_table_has_column() is defined in config.php — do not redeclare here.
