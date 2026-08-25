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

        // ── الصيانة ─────────────────────────────────────────────
        'maintenance_summary' => [
            'code'        => 'maintenance_summary',
            'name_ar'     => 'مؤشرات الصيانة',
            'icon'        => 'fa-wrench',
            'category'    => 'maintenance',
            'description' => 'مؤشرات الصيانة لكل معدة: الأوامر، التوقف، التكلفة، MTBF، MTTR، نسبة الجاهزية',
            'url'         => 'reports/maintenance_summary.php',
        ],

        // ── المشتريات (§16 — أول تقارير الوحدة، أُضيف 2026-08-06) ──
        'procurement_summary' => [
            'code'        => 'procurement_summary',
            'name_ar'     => 'ملخص المشتريات',
            'icon'        => 'fa-cart-shopping',
            'category'    => 'procurement',
            'description' => 'الدورة لكل مورد: الأوامر وقيمها، نسب الاستلام، المطابقة الثلاثية، الذمم المفتوحة',
            'url'         => 'reports/procurement_summary.php',
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
    // جدول report_role_permissions مرجعيٌّ عالمي (T_GLOBAL) — قراءته عبر البوابة بلا تنطيق شركة.
    try {
        $rows = ems_tenant_db()->select('report_role_permissions', array(
            'columns' => array('id'),
            'where'   => array('role_id' => $roleId, 'report_code' => strval($reportCode)),
            'limit'   => 1,
        ));
    } catch (\Throwable $t) {
        // إذا الجدول غير موجود أو مديرو المشاريع (role=1) — منح الوصول (سلوك الأصل)
        return ($roleId === 1);
    }
    return (count($rows) > 0);
}

/**
 * جلب قائمة التقارير المتاحة للدور مع بيانات الكتالوج
 */
function getAvailableReports($conn, $roleId) {
    $roleId  = intval($roleId);
    $catalog = getReportsCatalog();

    // السوبر ادمن يرى كل التقارير
    if ($roleId === -1) return array_values($catalog);

    // جلب كل الأكواد المسموح بها لهذا الدور (جدول مرجعي عالمي عبر البوابة)
    try {
        $result = ems_tenant_db()->select('report_role_permissions', array(
            'columns' => array('report_code'),
            'where'   => array('role_id' => $roleId),
        ));
    } catch (\Throwable $t) {
        // إذا الجدول غير موجود أو مدير مشاريع — أظهر كل التقارير (سلوك الأصل)
        return ($roleId === 1) ? array_values($catalog) : [];
    }

    $allowed = [];
    foreach ($result as $row) {
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
        'maintenance' => 'الصيانة',
        'procurement' => 'المشتريات',
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
        'maintenance' => 'fa-wrench',
        'procurement' => 'fa-cart-shopping',
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
 * بوابة تقارير موحّدة — العزل عبر بوابة المستأجر، والسوبر عبر forAllTenants المسجَّل
 * (سلوك الأصل: rptCompanyScope كان يعيد 1=1 للسوبر و company_id=N للمستأجر — البوابة تكافئه حرفيًا).
 */
function rptGate($isSuperAdmin) {
    return $isSuperAdmin ? ems_tenant_db()->forAllTenants('emsreports super') : ems_tenant_db();
}

/**
 * جلب المشاريع للـ dropdown (مصفوفة بـ company)
 */
function getProjectsForDropdown($conn, $companyId, $isSuperAdmin) {
    try {
        return rptGate($isSuperAdmin)->scopedQuery(array('scope' => array('p' => 'project')),
            "SELECT p.id, p.name, p.project_code FROM project p WHERE 1=1 AND {TENANT_SCOPE} ORDER BY p.name ASC");
    } catch (\Throwable $t) { error_log('emsreports projects dropdown: ' . $t->getMessage()); return []; }
}

/**
 * جلب الموردين للـ dropdown
 */
function getSuppliersForDropdown($conn, $companyId, $isSuperAdmin) {
    try {
        return rptGate($isSuperAdmin)->scopedQuery(array('scope' => array('s' => 'suppliers')),
            "SELECT id, name FROM suppliers s WHERE 1=1 AND {TENANT_SCOPE} ORDER BY name ASC");
    } catch (\Throwable $t) { error_log('emsreports suppliers dropdown: ' . $t->getMessage()); return []; }
}

/**
 * جلب المشغلين للـ dropdown
 */
function getDriversForDropdown($conn, $companyId, $isSuperAdmin) {
    try {
        return rptGate($isSuperAdmin)->scopedQuery(array('scope' => array('d' => 'employees')),
            "SELECT id, name, employee_code FROM employees d WHERE 1=1 AND {TENANT_SCOPE} ORDER BY name ASC");
    } catch (\Throwable $t) { error_log('emsreports drivers dropdown: ' . $t->getMessage()); return []; }
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
