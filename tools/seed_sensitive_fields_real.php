<?php
/**
 * tools/seed_sensitive_fields_real.php — قاموس الحقول الحساسة الحقيقي (م-هـ · M-14)
 * ───────────────────────────────────────────────────────────────────────────
 * كانت scr_sensitive_fields بذورًا عشوائية («ميداني 2» في كل عمود) — تُصدَّر
 * وتُحذف وتُبذر الحقول الحساسة الفعلية المتحققة من المخطط الحي (راتب · بنوك
 * الموردين · هاتف الموظف · كلمات المرور · إجماليات المسيّر · الذمم) بسياسات
 * BR-GOV-07: من يراه · الإخفاء · تسجيل الاطلاع · التصدير. [تجريبي — ق-15]
 * idempotent بمفتاح (الجدول·الحقل). التشغيل: php tools/seed_sensitive_fields_real.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* ① تصدير البذور العشوائية ثم حذفها (سابقة نماذج العمل) */
$rows = array();
$r = mysqli_query($conn, "SELECT * FROM scr_sensitive_fields WHERE is_seed = 1 AND table_name NOT LIKE '%\\_%'");
while ($r && ($x = mysqli_fetch_assoc($r))) { $rows[] = $x; }
if ($rows) {
    $f = __DIR__ . '/../storage/backups/sensitive_fields_generic_seeds_' . date('Ymd_His') . '.json';
    file_put_contents($f, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    mysqli_query($conn, "DELETE FROM scr_sensitive_fields WHERE is_seed = 1 AND table_name NOT LIKE '%\\_%'");
    fwrite(STDOUT, '① صُدّرت ' . count($rows) . " بذرة عشوائية وحُذفت\n");
}

/* ② الحقول الحساسة الحقيقية — كلٌّ متحقق من المخطط الحي قبل بذره */
$FIELDS = array(
    // [الجدول، الحقل، التصنيف، من يراه، الإخفاء، يسجل؟، يصدر؟، الأساس]
    array('worker_contract', 'wage', 'سري — أجر', 'الموارد البشرية والمالية ومدير الصلاحيات', 'يظهر «•••» لغير المخول', 'نعم', 'لا', 'M-13 §الأجور · BR-GOV-07'),
    array('employees', 'phone', 'شخصي — تواصل', 'إدارته المباشرة والموارد', 'آخر 3 أرقام لغير المخول', 'لا', 'لا', 'حماية البيانات الشخصية'),
    array('users', 'password', 'سري أقصى — اعتماد', 'لا أحد — تجزئة لا تُعرض', 'لا يُعرض إطلاقًا', 'نعم', 'لا', 'SEC-01 · لا استرجاع'),
    array('suppliers', 'bank_account_no', 'سري — مصرفي', 'المالية والمشتريات المخولون', 'آخر 4 أرقام', 'نعم', 'لا', 'M-05 §بيانات المورد'),
    array('suppliers', 'bank_iban', 'سري — مصرفي', 'المالية والمشتريات المخولون', 'آخر 4 أحرف', 'نعم', 'لا', 'M-05 §بيانات المورد'),
    array('suppliers', 'tax_number', 'تنظيمي — ضريبي', 'المالية والحوكمة', 'كامل للمخول', 'نعم', 'نعم', 'M-10 §الالتزام الضريبي'),
    array('payroll_runs', 'gross_total', 'سري — مسيّر', 'المالية العليا والموارد', 'يظهر «•••»', 'نعم', 'لا', 'M-13 §المسيّر · وُصل بابه'),
    array('fin_dues', 'amount', 'مالي — ذمم أشخاص', 'المالية وصاحب الذمة', 'كامل للمخول', 'نعم', 'لا', 'M-10 §الذمم'),
    array('fin_bank_accounts', 'account_no', 'سري — خزينة', 'المالية العليا', 'آخر 4 أرقام', 'نعم', 'لا', 'M-10 §الخزينة'),
    array('employee_card', 'salary_block', 'سري — أجر', 'الموارد والمالية', 'قسم الراتب محجوب لغير المخول', 'نعم', 'لا', 'وُصل بابه الأول (BR-GOV-07)'),
);
$ins = $conn->prepare("INSERT INTO scr_sensitive_fields
    (company_id, no_policy, table_name, field_name, classification_sensitivity, from_visible_to,
     policy_masking, log_views_flag, exportable_flag, basis_statutory, date_effective,
     status, status_label, approver_name, is_seed, created_by, created_by_name)
    VALUES (4, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'معتمد', 'معتمد',
            'قرار شركة منفذة — ق-15', 1, 0, 'م-هـ [تجريبي — ق-15]')");
$made = 0; $i = 0;
foreach ($FIELDS as $fdef) {
    $i++;
    // تحقق من المخطط: الجدول والحقل حقيقيان (البنود الوصفية كـsalary_block تمر باسم شاشتها)
    if (strpos($fdef[1], '_block') === false) {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM `{$fdef[0]}` LIKE '{$fdef[1]}'");
        if (!$c || !mysqli_num_rows($c)) { fwrite(STDOUT, "⚠ {$fdef[0]}.{$fdef[1]} غير موجود — لا يُبذر\n"); continue; }
    }
    $ex = $conn->query("SELECT id FROM scr_sensitive_fields WHERE table_name='{$fdef[0]}' AND field_name='{$fdef[1]}'")->fetch_assoc();
    if ($ex) { fwrite(STDOUT, "= {$fdef[0]}.{$fdef[1]} قائم\n"); continue; }
    $no = 'SEN-' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $ins->bind_param('sssssssss', $no, $fdef[0], $fdef[1], $fdef[2], $fdef[3], $fdef[4], $fdef[5], $fdef[6], $fdef[7]);
    if ($ins->execute()) { $made++; fwrite(STDOUT, "+ {$no} {$fdef[0]}.{$fdef[1]}\n"); }
}
fwrite(STDOUT, "② بُذر {$made} حقلًا حساسًا حقيقيًّا\n");
