<?php
/**
 * tools/guard_debt_sweep.php — تصنيف دَين «ملفاتٌ لا تنادي الحارس» (E-04)
 * ───────────────────────────────────────────────────────────────────────────
 * الحارس المركزي يعمل من insidebar.php (enforce_current_page_view_permission)
 * — فالملفُ الذي لا يضمّنه ولا يفحص بنفسه خارجُ المظلة. هذا المسح يصنّف
 * لا يخمّن:
 *   A صفحةٌ كاملةٌ بلا مظلة (تُخرج HTML ولا insidebar ولا فحصًا ذاتيًّا) — الخطر.
 *   B معالجُ AJAX/POST — مظلته سجل action_guard أو فحص داخلي (يُعدّ من فيه فحص).
 *   C تضمينٌ/CLI/مكوّن — لا يُنفَّذ مباشرةً (session_bootstrap فقط أو بلا مخرج).
 * التقرير: docs/GUARD_DEBT_SWEEP_ar.csv — والعدّاد الحاكم لاحقًا فئة A.
 */
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
              'Fleet','Governance','Maintenance','Movement','Operations','Portal','Procurement',
              'Settings','Suppliers','Tickets','Timesheet','Transport','Warehouse','Workforce',
              'main','admin','chats','company','emsreports');
$rows = array(); $cnt = array('A' => 0, 'B' => 0, 'C' => 0, 'P' => 0, 'guarded' => 0);
/* عامٌّ بطبيعته (قبل الجلسة): الدخول والاسترداد والتسجيل — يُعلَن لا يُدفن.
   setup_once تعطّل نفسها بعد أول تهيئة (تُفحص يدويًّا في كل مراجعة). */
$PUBLIC = '/(login|forgot_password|reset_password|register|request_subscription|logout|setup_once)\.php$|^company\/home\.php$/';
/* معالجاتُ المراسلات مفتوحةٌ لكل مسجَّلٍ بعرف النظام المعلن (نمط chats/index
   في قائمة OPEN_SCREENS بحزام SEC-GOV) — والنطاقُ يُفرض داخلها بالمحادثة. */
$OPEN_BY_CONVENTION = '/^chats\//';
foreach ($dirs as $d) {
    $p = $ROOT . '/' . $d;
    if (!is_dir($p)) { continue; }
    foreach (glob($p . '/*.php') as $f) {
        $rel = $d . '/' . basename($f);
        $src = (string) file_get_contents($f);
        $hasSidebar = strpos($src, 'insidebar') !== false;
        $hasCheck = strpos($src, 'ems_guard_handler') !== false          // حارس المعالجات المشترك
                 || strpos($src, 'check_page_permissions') !== false
                 || strpos($src, 'enforce_current_page_view_permission') !== false
                 || strpos($src, 'governance_guard') !== false
                 || strpos($src, 'action_guard') !== false
                 || strpos($src, 'ems_require_json_permission') !== false
                 || strpos($src, 'SupplierPortalGuard') !== false
                 || strpos($src, 'super_admin_require_login') !== false   // لوحة السوبر بجلستها
                 || strpos($src, 'company_require_role') !== false        // بوابة الشركات بجلستها
                 || strpos($src, 'EMS_DIRECT_ACCESS_FORBIDDEN') !== false // تضمينٌ يرفض النداء المباشر
                 || strpos($src, 'EMS_ROLES_') !== false                  // قائمة أدوار صلبة (ADR-07)
                 || preg_match('/allowed_roles|in_array\(\s*\$role/', $src) === 1
                 || preg_match('/role.\]\)\s*!==\s*-1/', $src) === 1;     // حصرٌ بالسوبر صراحةً
        if ($hasSidebar || $hasCheck) { $cnt['guarded']++; continue; }
        $isCli = strpos($src, "PHP_SAPI !== 'cli'") !== false || strpos($src, 'EMS_CLI') !== false;
        $emitsHtml = preg_match('/<(html|body|table|div|form)[\s>]/i', $src) === 1;
        $handlesPost = strpos($src, "\$_POST") !== false || strpos($src, "\$_REQUEST") !== false;
        $sessionOnly = strpos($src, "\$_SESSION['user']") !== false; // فحص جلسةٍ فقط لا صلاحية
        if (preg_match($PUBLIC, $rel)) { $k = 'P'; }
        elseif (preg_match($OPEN_BY_CONVENTION, $rel)) { $k = 'P'; }
        elseif ($isCli) { $k = 'C'; }
        elseif ($emitsHtml) { $k = 'A'; }
        elseif ($handlesPost) { $k = 'B'; }
        else { $k = 'C'; }
        $cnt[$k]++;
        $rows[] = array($k, $rel, $sessionOnly ? 'جلسة فقط' : 'بلا فحص', $emitsHtml ? 'HTML' : ($handlesPost ? 'POST' : '—'));
    }
}
usort($rows, function ($a, $b) { return strcmp($a[0] . $a[1], $b[0] . $b[1]); });
$f = fopen($ROOT . '/docs/GUARD_DEBT_SWEEP_ar.csv', 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, array('الفئة', 'الملف', 'الفحص', 'الطبيعة'));
foreach ($rows as $r) { fputcsv($f, $r); }
fclose($f);
fwrite(STDOUT, "════ مسح دَين الحارس ════\n");
fwrite(STDOUT, "محروس (مظلة/فحص): {$cnt['guarded']}\n");
fwrite(STDOUT, "A صفحةٌ كاملةٌ بلا مظلة (الخطر الحاكم): {$cnt['A']}\n");
fwrite(STDOUT, "B معالجٌ بلا فحصٍ مسجَّل: {$cnt['B']}\n");
fwrite(STDOUT, "C تضمين/CLI (لا يُنفَّذ مباشرة): {$cnt['C']}\n");
fwrite(STDOUT, "P عامٌّ بطبيعته معلَنًا (دخول/استرداد/تسجيل): {$cnt['P']}\n");
fwrite(STDOUT, "التقرير: docs/GUARD_DEBT_SWEEP_ar.csv\n");
$aRows = array_values(array_filter($rows, function ($r) { return $r[0] === 'A'; }));
foreach (array_slice($aRows, 0, 12) as $r) { fwrite(STDOUT, "  A · {$r[1]} ({$r[2]})\n"); }
exit(0);
