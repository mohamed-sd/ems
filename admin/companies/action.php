<?php
require_once dirname(__DIR__) . '/includes/auth.php';
super_admin_require_login();
super_admin_require_post_csrf();

$admin = super_admin_current();
$actorId = intval(isset($admin['id']) ? $admin['id'] : 0);

function companies_redirect_with_msg($target, $type, $text) {
    super_admin_redirect($target, array('msg' => $type . ':' . $text));
}

function companies_table_has_column($tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);

    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);

    return $res && mysqli_num_rows($res) > 0;
}

$action = trim(isset($_POST['action']) ? $_POST['action'] : '');
$id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
$redirectTo = trim(isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'companies');

if ($redirectTo === '') {
    $redirectTo = 'companies';
}

if ($redirectTo !== 'companies') {
    if (!preg_match('/^companies\/[0-9]+$/', $redirectTo)) {
        $redirectTo = 'companies';
    }
}

$statusAllowed = array('pending', 'active', 'suspended', 'cancelled');

if ($action === 'create' || $action === 'update') {
    $companyName = trim(isset($_POST['company_name']) ? $_POST['company_name'] : '');
    $companyNameEn = trim(isset($_POST['company_name_en']) ? $_POST['company_name_en'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $postalAddress = trim(isset($_POST['postal_address']) ? $_POST['postal_address'] : '');
    $commercialRegistration = trim(isset($_POST['commercial_registration']) ? $_POST['commercial_registration'] : '');
    $sector = trim(isset($_POST['sector']) ? $_POST['sector'] : '');
    $country = trim(isset($_POST['country']) ? $_POST['country'] : '');
    $city = trim(isset($_POST['city']) ? $_POST['city'] : '');
    $taxNumber = trim(isset($_POST['tax_number']) ? $_POST['tax_number'] : '');
    $logoPath = trim(isset($_POST['logo_path']) ? $_POST['logo_path'] : '');
    $modulesEnabled = trim(isset($_POST['modules_enabled']) ? $_POST['modules_enabled'] : '');
    $subscriptionStart = trim(isset($_POST['subscription_start']) ? $_POST['subscription_start'] : '');
    $subscriptionEnd = trim(isset($_POST['subscription_end']) ? $_POST['subscription_end'] : '');
    $maxUsers = intval(isset($_POST['max_users']) ? $_POST['max_users'] : 0);
    $maxEquipments = intval(isset($_POST['max_equipments']) ? $_POST['max_equipments'] : 0);
    $maxProjects = intval(isset($_POST['max_projects']) ? $_POST['max_projects'] : 0);
    $currency = trim(isset($_POST['currency']) ? $_POST['currency'] : 'SAR');
    $timezone = trim(isset($_POST['timezone']) ? $_POST['timezone'] : 'Asia/Riyadh');

    $managerName = trim(isset($_POST['manager_name']) ? $_POST['manager_name'] : '');
    $managerEmail = trim(isset($_POST['manager_email']) ? $_POST['manager_email'] : '');
    $tempPassword = isset($_POST['temp_password']) ? trim($_POST['temp_password']) : '';

    $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');
    $planIdInput = trim(isset($_POST['plan_id']) ? $_POST['plan_id'] : '');
    $planId = ($planIdInput !== '' && intval($planIdInput) > 0) ? intval($planIdInput) : null;
    $hasNameCol = companies_table_has_column('admin_companies', 'name');
    $hasCompanyNameCol = companies_table_has_column('admin_companies', 'company_name');
    $hasAddressCol = companies_table_has_column('admin_companies', 'address');

    if (!validate_length($companyName, 2, 200)) {
        companies_redirect_with_msg($redirectTo, 'error', 'اسم الشركة مطلوب (2-200 حرف).');
    }

    if (!validate_length($commercialRegistration, 2, 120)) {
        companies_redirect_with_msg($redirectTo, 'error', 'رقم السجل التجاري مطلوب.');
    }

    if (!in_array($sector, array('تعدين', 'مقاولات', 'إنشاء'), true)) {
        companies_redirect_with_msg($redirectTo, 'error', 'قطاع النشاط غير صالح.');
    }

    if (!validate_length($country, 2, 100) || !validate_length($city, 2, 100)) {
        companies_redirect_with_msg($redirectTo, 'error', 'الدولة والمدينة مطلوبتان.');
    }

    if (!validate_email($email) || !validate_length($email, 5, 150)) {
        companies_redirect_with_msg($redirectTo, 'error', 'البريد الإلكتروني غير صالح.');
    }

    if ($phone !== '' && !validate_length($phone, 3, 30)) {
        companies_redirect_with_msg($redirectTo, 'error', 'رقم الهاتف غير صالح.');
    }

    if (!validate_length($phone, 3, 30)) {
        companies_redirect_with_msg($redirectTo, 'error', 'رقم الهاتف (مع رمز الدولة) مطلوب.');
    }

    if ($address !== '' && !validate_length($address, 3, 2000)) {
        companies_redirect_with_msg($redirectTo, 'error', 'العنوان طويل أو غير صالح.');
    }

    if (!in_array($status, $statusAllowed, true)) {
        $status = 'pending';
    }

    if ($action === 'create') {
        $status = 'pending';
    }

    if ($action === 'create') {
        if (!validate_length($managerName, 2, 150)) {
            companies_redirect_with_msg($redirectTo, 'error', 'اسم المدير العام مطلوب.');
        }
        if (!validate_email($managerEmail) || !validate_length($managerEmail, 5, 150)) {
            companies_redirect_with_msg($redirectTo, 'error', 'بريد المدير العام غير صالح.');
        }
        if (!validate_length($tempPassword, 8, 255)) {
            companies_redirect_with_msg($redirectTo, 'error', 'كلمة المرور المؤقتة يجب ألا تقل عن 8 أحرف.');
        }
    }

    if ($planId !== null) {
        $checkPlanStmt = mysqli_prepare($conn, 'SELECT id FROM admin_subscription_plans WHERE id = ? LIMIT 1');
        if (!$checkPlanStmt) {
            companies_redirect_with_msg($redirectTo, 'error', 'تعذر التحقق من خطة الاشتراك حالياً.');
        }

        mysqli_stmt_bind_param($checkPlanStmt, 'i', $planId);
        mysqli_stmt_execute($checkPlanStmt);
        $planRes = mysqli_stmt_get_result($checkPlanStmt);
        $planRow = $planRes ? mysqli_fetch_assoc($planRes) : null;
        mysqli_stmt_close($checkPlanStmt);

        if (!$planRow) {
            companies_redirect_with_msg($redirectTo, 'error', 'خطة الاشتراك المختارة غير موجودة.');
        }
    }

    if (companies_table_has_column('admin_companies', 'commercial_registration')) {
        $regEsc = mysqli_real_escape_string($conn, $commercialRegistration);
        $dupRegSql = "SELECT id FROM admin_companies WHERE commercial_registration = '$regEsc'";
        if ($action === 'update') {
            $dupRegSql .= ' AND id <> ' . intval($id);
        }
        $dupRegSql .= ' LIMIT 1';
        $dupRegQ = @mysqli_query($conn, $dupRegSql);
        if ($dupRegQ && mysqli_fetch_assoc($dupRegQ)) {
            companies_redirect_with_msg($redirectTo, 'error', 'رقم السجل التجاري مستخدم مسبقاً.');
        }
    }

    if ($action === 'create') {
        $dupStmt = mysqli_prepare($conn, 'SELECT id FROM admin_companies WHERE email = ? LIMIT 1');
        if (!$dupStmt) {
            companies_redirect_with_msg($redirectTo, 'error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
        }

        mysqli_stmt_bind_param($dupStmt, 's', $email);
        mysqli_stmt_execute($dupStmt);
        $dupRes = mysqli_stmt_get_result($dupStmt);
        $dupRow = $dupRes ? mysqli_fetch_assoc($dupRes) : null;
        mysqli_stmt_close($dupStmt);

        if ($dupRow) {
            companies_redirect_with_msg($redirectTo, 'error', 'هذا البريد الإلكتروني مستخدم بالفعل.');
        }

        if (!$hasNameCol && !$hasCompanyNameCol) {
            companies_redirect_with_msg($redirectTo, 'error', 'هيكل جدول الشركات لا يحتوي أعمدة اسم الشركة المطلوبة.');
        }

        if (!companies_table_has_column('users', 'email') || !companies_table_has_column('users', 'company_id')) {
            companies_redirect_with_msg($redirectTo, 'error', 'جدول المستخدمين غير مهيأ لإنشاء مدير الشركة. نفذ ترقية قاعدة البيانات.');
        }

        $dupUserStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        if (!$dupUserStmt) {
            companies_redirect_with_msg($redirectTo, 'error', 'تعذر التحقق من بريد المدير العام.');
        }
        mysqli_stmt_bind_param($dupUserStmt, 's', $managerEmail);
        mysqli_stmt_execute($dupUserStmt);
        $dupUserRes = mysqli_stmt_get_result($dupUserStmt);
        $dupUserRow = $dupUserRes ? mysqli_fetch_assoc($dupUserRes) : null;
        mysqli_stmt_close($dupUserStmt);
        if ($dupUserRow) {
            companies_redirect_with_msg($redirectTo, 'error', 'بريد المدير العام مستخدم مسبقاً.');
        }

        $companyEsc = mysqli_real_escape_string($conn, $companyName);
        $companyNameEnEsc = mysqli_real_escape_string($conn, $companyNameEn);
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $phoneEsc = mysqli_real_escape_string($conn, $phone);
        $addressEsc = mysqli_real_escape_string($conn, $address);
        $postalAddressEsc = mysqli_real_escape_string($conn, $postalAddress);
        $commercialRegistrationEsc = mysqli_real_escape_string($conn, $commercialRegistration);
        $sectorEsc = mysqli_real_escape_string($conn, $sector);
        $countryEsc = mysqli_real_escape_string($conn, $country);
        $cityEsc = mysqli_real_escape_string($conn, $city);
        $taxNumberEsc = mysqli_real_escape_string($conn, $taxNumber);
        $logoPathEsc = mysqli_real_escape_string($conn, $logoPath);
        $modulesEnabledEsc = mysqli_real_escape_string($conn, $modulesEnabled);
        $currencyEsc = mysqli_real_escape_string($conn, $currency);
        $timezoneEsc = mysqli_real_escape_string($conn, $timezone);
        $statusEsc = mysqli_real_escape_string($conn, $status);

        $cols = array();
        $vals = array();

        if (companies_table_has_column('admin_companies', 'plan_id')) {
            $cols[] = 'plan_id';
            $vals[] = ($planId !== null) ? strval(intval($planId)) : 'NULL';
        }
        if ($hasNameCol) {
            $cols[] = 'name';
            $vals[] = "'" . $companyEsc . "'";
        }
        if ($hasCompanyNameCol) {
            $cols[] = 'company_name';
            $vals[] = "'" . $companyEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'company_name_ar')) {
            $cols[] = 'company_name_ar';
            $vals[] = "'" . $companyEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'company_name_en')) {
            $cols[] = 'company_name_en';
            $vals[] = "'" . $companyNameEnEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'commercial_registration')) {
            $cols[] = 'commercial_registration';
            $vals[] = "'" . $commercialRegistrationEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'sector')) {
            $cols[] = 'sector';
            $vals[] = "'" . $sectorEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'country')) {
            $cols[] = 'country';
            $vals[] = "'" . $countryEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'city')) {
            $cols[] = 'city';
            $vals[] = "'" . $cityEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'tax_number')) {
            $cols[] = 'tax_number';
            $vals[] = "'" . $taxNumberEsc . "'";
        }
        $cols[] = 'email';
        $vals[] = "'" . $emailEsc . "'";

        if (companies_table_has_column('admin_companies', 'phone')) {
            $cols[] = 'phone';
            $vals[] = "'" . $phoneEsc . "'";
        }
        if ($hasAddressCol) {
            $cols[] = 'address';
            $vals[] = "'" . $addressEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'postal_address')) {
            $cols[] = 'postal_address';
            $vals[] = "'" . $postalAddressEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'logo_path')) {
            $cols[] = 'logo_path';
            $vals[] = "'" . $logoPathEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'modules_enabled')) {
            $cols[] = 'modules_enabled';
            $vals[] = "'" . $modulesEnabledEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'subscription_start') && $subscriptionStart !== '') {
            $startEsc = mysqli_real_escape_string($conn, $subscriptionStart);
            $cols[] = 'subscription_start';
            $vals[] = "'" . $startEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'subscription_end') && $subscriptionEnd !== '') {
            $endEsc = mysqli_real_escape_string($conn, $subscriptionEnd);
            $cols[] = 'subscription_end';
            $vals[] = "'" . $endEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'max_users')) {
            $cols[] = 'max_users';
            $vals[] = strval(max(0, $maxUsers));
        }
        if (companies_table_has_column('admin_companies', 'max_equipments')) {
            $cols[] = 'max_equipments';
            $vals[] = strval(max(0, $maxEquipments));
        }
        if (companies_table_has_column('admin_companies', 'max_projects')) {
            $cols[] = 'max_projects';
            $vals[] = strval(max(0, $maxProjects));
        }
        if (companies_table_has_column('admin_companies', 'currency')) {
            $cols[] = 'currency';
            $vals[] = "'" . $currencyEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'timezone')) {
            $cols[] = 'timezone';
            $vals[] = "'" . $timezoneEsc . "'";
        }
        if (companies_table_has_column('admin_companies', 'users_count')) {
            $cols[] = 'users_count';
            $vals[] = '1';
        }

        $cols[] = 'status';
        $vals[] = "'" . $statusEsc . "'";

        $insertSql = 'INSERT INTO admin_companies (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
        mysqli_begin_transaction($conn);
        $ok = @mysqli_query($conn, $insertSql);
        $newId = $ok ? intval(mysqli_insert_id($conn)) : 0;

        if ($ok && $newId > 0) {
            $managerPassHash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $managerNameEsc = mysqli_real_escape_string($conn, $managerName);
            $managerEmailEsc = mysqli_real_escape_string($conn, $managerEmail);
            $managerPassHashEsc = mysqli_real_escape_string($conn, $managerPassHash);
            $managerPhoneEsc = mysqli_real_escape_string($conn, $phone);

            $userCols = array('name', 'username', 'password', 'phone', 'role');
            $userVals = array("'" . $managerNameEsc . "'", "'" . $managerEmailEsc . "'", "'" . $managerPassHashEsc . "'", "'" . $managerPhoneEsc . "'", "'1'");

            if (companies_table_has_column('users', 'email')) {
                $userCols[] = 'email';
                $userVals[] = "'" . $managerEmailEsc . "'";
            }
            if (companies_table_has_column('users', 'role_id')) {
                $userCols[] = 'role_id';
                $userVals[] = '1';
            }
            if (companies_table_has_column('users', 'company_id')) {
                $userCols[] = 'company_id';
                $userVals[] = strval($newId);
            }
            if (companies_table_has_column('users', 'status')) {
                $userCols[] = 'status';
                $userVals[] = "'active'";
            }
            if (companies_table_has_column('users', 'force_password_change')) {
                $userCols[] = 'force_password_change';
                $userVals[] = '1';
            }
            if (companies_table_has_column('users', 'temp_password_set_at')) {
                $userCols[] = 'temp_password_set_at';
                $userVals[] = 'NOW()';
            }

            $insertUserSql = 'INSERT INTO users (' . implode(', ', $userCols) . ') VALUES (' . implode(', ', $userVals) . ')';
            $ok = @mysqli_query($conn, $insertUserSql);
        }

        if ($ok && $newId > 0) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
        }

        if ($ok && $newId > 0) {
            super_admin_write_audit($actorId, 'create', 'شركة', 'إضافة شركة جديدة: ' . $companyName, $newId);
            companies_redirect_with_msg('companies', 'success', 'تمت إضافة الشركة بنجاح.');
        }

        companies_redirect_with_msg($redirectTo, 'error', 'فشل إضافة الشركة: ' . mysqli_error($conn));
    }

    if ($id <= 0) {
        companies_redirect_with_msg('companies', 'error', 'معرف الشركة غير صالح.');
    }

    $dupStmt = mysqli_prepare($conn, 'SELECT id FROM admin_companies WHERE email = ? AND id <> ? LIMIT 1');
    if (!$dupStmt) {
        companies_redirect_with_msg($redirectTo, 'error', 'تعذر التحقق من البريد الإلكتروني حالياً.');
    }

    mysqli_stmt_bind_param($dupStmt, 'si', $email, $id);
    mysqli_stmt_execute($dupStmt);
    $dupRes = mysqli_stmt_get_result($dupStmt);
    $dupRow = $dupRes ? mysqli_fetch_assoc($dupRes) : null;
    mysqli_stmt_close($dupStmt);

    if ($dupRow) {
        companies_redirect_with_msg($redirectTo, 'error', 'البريد الإلكتروني مستخدم لشركة أخرى.');
    }

    if (!$hasNameCol && !$hasCompanyNameCol) {
        companies_redirect_with_msg($redirectTo, 'error', 'هيكل جدول الشركات لا يحتوي أعمدة اسم الشركة المطلوبة.');
    }

    $companyEsc = mysqli_real_escape_string($conn, $companyName);
    $companyNameEnEsc = mysqli_real_escape_string($conn, $companyNameEn);
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $phoneEsc = mysqli_real_escape_string($conn, $phone);
    $addressEsc = mysqli_real_escape_string($conn, $address);
    $postalAddressEsc = mysqli_real_escape_string($conn, $postalAddress);
    $commercialRegistrationEsc = mysqli_real_escape_string($conn, $commercialRegistration);
    $sectorEsc = mysqli_real_escape_string($conn, $sector);
    $countryEsc = mysqli_real_escape_string($conn, $country);
    $cityEsc = mysqli_real_escape_string($conn, $city);
    $taxNumberEsc = mysqli_real_escape_string($conn, $taxNumber);
    $logoPathEsc = mysqli_real_escape_string($conn, $logoPath);
    $modulesEnabledEsc = mysqli_real_escape_string($conn, $modulesEnabled);
    $currencyEsc = mysqli_real_escape_string($conn, $currency);
    $timezoneEsc = mysqli_real_escape_string($conn, $timezone);
    $statusEsc = mysqli_real_escape_string($conn, $status);

    $sets = array();
    if (companies_table_has_column('admin_companies', 'plan_id')) {
        $sets[] = 'plan_id = ' . (($planId !== null) ? strval(intval($planId)) : 'NULL');
    }
    if ($hasNameCol) {
        $sets[] = "name = '" . $companyEsc . "'";
    }
    if ($hasCompanyNameCol) {
        $sets[] = "company_name = '" . $companyEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'company_name_ar')) {
        $sets[] = "company_name_ar = '" . $companyEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'company_name_en')) {
        $sets[] = "company_name_en = '" . $companyNameEnEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'commercial_registration')) {
        $sets[] = "commercial_registration = '" . $commercialRegistrationEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'sector')) {
        $sets[] = "sector = '" . $sectorEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'country')) {
        $sets[] = "country = '" . $countryEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'city')) {
        $sets[] = "city = '" . $cityEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'tax_number')) {
        $sets[] = "tax_number = '" . $taxNumberEsc . "'";
    }
    $sets[] = "email = '" . $emailEsc . "'";

    if (companies_table_has_column('admin_companies', 'phone')) {
        $sets[] = "phone = '" . $phoneEsc . "'";
    }
    if ($hasAddressCol) {
        $sets[] = "address = '" . $addressEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'postal_address')) {
        $sets[] = "postal_address = '" . $postalAddressEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'logo_path')) {
        $sets[] = "logo_path = '" . $logoPathEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'modules_enabled')) {
        $sets[] = "modules_enabled = '" . $modulesEnabledEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'subscription_start')) {
        if ($subscriptionStart !== '') {
            $startEsc = mysqli_real_escape_string($conn, $subscriptionStart);
            $sets[] = "subscription_start = '" . $startEsc . "'";
        } else {
            $sets[] = 'subscription_start = NULL';
        }
    }
    if (companies_table_has_column('admin_companies', 'subscription_end')) {
        if ($subscriptionEnd !== '') {
            $endEsc = mysqli_real_escape_string($conn, $subscriptionEnd);
            $sets[] = "subscription_end = '" . $endEsc . "'";
        } else {
            $sets[] = 'subscription_end = NULL';
        }
    }
    if (companies_table_has_column('admin_companies', 'max_users')) {
        $sets[] = 'max_users = ' . strval(max(0, $maxUsers));
    }
    if (companies_table_has_column('admin_companies', 'max_equipments')) {
        $sets[] = 'max_equipments = ' . strval(max(0, $maxEquipments));
    }
    if (companies_table_has_column('admin_companies', 'max_projects')) {
        $sets[] = 'max_projects = ' . strval(max(0, $maxProjects));
    }
    if (companies_table_has_column('admin_companies', 'currency')) {
        $sets[] = "currency = '" . $currencyEsc . "'";
    }
    if (companies_table_has_column('admin_companies', 'timezone')) {
        $sets[] = "timezone = '" . $timezoneEsc . "'";
    }

    $sets[] = "status = '" . $statusEsc . "'";
    $sets[] = 'updated_at = NOW()';

    $updateSql = 'UPDATE admin_companies SET ' . implode(', ', $sets) . ' WHERE id = ' . intval($id) . ' LIMIT 1';
    $ok = @mysqli_query($conn, $updateSql);

    if ($ok) {
        super_admin_write_audit($actorId, 'update', 'شركة', 'تحديث بيانات الشركة: ' . $companyName, $id);
        companies_redirect_with_msg($redirectTo, 'success', 'تم تحديث بيانات الشركة بنجاح.');
    }

    companies_redirect_with_msg($redirectTo, 'error', 'فشل تحديث بيانات الشركة: ' . mysqli_error($conn));
}

if ($action === 'update_company_user_password') {
    if ($id <= 0) {
        companies_redirect_with_msg('companies', 'error', 'معرف الشركة غير صالح.');
    }

    $userId = intval(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
    $newPassword = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
    $confirmPassword = trim(isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

    if ($userId <= 0) {
        companies_redirect_with_msg($redirectTo, 'error', 'المستخدم المستهدف غير صالح.');
    }

    if (!validate_length($newPassword, 8, 255)) {
        companies_redirect_with_msg($redirectTo, 'error', 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.');
    }

    if ($newPassword !== $confirmPassword) {
        companies_redirect_with_msg($redirectTo, 'error', 'تأكيد كلمة المرور غير متطابق.');
    }

    $userCheckSql = 'SELECT id, username FROM users WHERE id = ? AND company_id = ? LIMIT 1';
    $userCheckStmt = mysqli_prepare($conn, $userCheckSql);
    if (!$userCheckStmt) {
        companies_redirect_with_msg($redirectTo, 'error', 'تعذر التحقق من المستخدم حالياً.');
    }

    mysqli_stmt_bind_param($userCheckStmt, 'ii', $userId, $id);
    mysqli_stmt_execute($userCheckStmt);
    $userRes = mysqli_stmt_get_result($userCheckStmt);
    $userRow = $userRes ? mysqli_fetch_assoc($userRes) : null;
    mysqli_stmt_close($userCheckStmt);

    if (!$userRow) {
        companies_redirect_with_msg($redirectTo, 'error', 'المستخدم لا يتبع لهذه الشركة.');
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    if (!$passwordHash) {
        companies_redirect_with_msg($redirectTo, 'error', 'تعذر إنشاء تشفير كلمة المرور.');
    }

    $setParts = array();
    $setParts[] = "password = '" . mysqli_real_escape_string($conn, $passwordHash) . "'";

    if (companies_table_has_column('users', 'force_password_change')) {
        $setParts[] = 'force_password_change = 1';
    }
    if (companies_table_has_column('users', 'temp_password_set_at')) {
        $setParts[] = 'temp_password_set_at = NOW()';
    }

    $updatePasswordSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ' . $userId . ' AND company_id = ' . $id . ' LIMIT 1';
    $ok = @mysqli_query($conn, $updatePasswordSql);

    if ($ok) {
        $auditText = 'تحديث كلمة مرور مستخدم الشركة: #' . $userId;
        super_admin_write_audit($actorId, 'update_password', 'شركة', $auditText, $id);
        companies_redirect_with_msg($redirectTo, 'success', 'تم تحديث كلمة المرور بنجاح.');
    }

    companies_redirect_with_msg($redirectTo, 'error', 'فشل تحديث كلمة المرور: ' . mysqli_error($conn));
}

if ($id <= 0) {
    companies_redirect_with_msg('companies', 'error', 'معرف الشركة غير صالح.');
}

if ($action === 'activate' || $action === 'suspend') {
    $newStatus = $action === 'activate' ? 'active' : 'suspended';
    $statusStmt = mysqli_prepare($conn, 'UPDATE admin_companies SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');

    if (!$statusStmt) {
        companies_redirect_with_msg($redirectTo, 'error', 'تعذر تغيير حالة الشركة حالياً.');
    }

    mysqli_stmt_bind_param($statusStmt, 'si', $newStatus, $id);
    $ok = mysqli_stmt_execute($statusStmt);
    mysqli_stmt_close($statusStmt);

    if ($ok) {
        $auditAction = $action === 'activate' ? 'activate' : 'suspend';
        $label = $action === 'activate' ? 'تفعيل' : 'تعليق';
        super_admin_write_audit($actorId, $auditAction, 'شركة', $label . ' الشركة رقم #' . $id, $id);
        companies_redirect_with_msg($redirectTo, 'success', 'تم تحديث حالة الشركة.');
    }

    companies_redirect_with_msg($redirectTo, 'error', 'فشل تحديث حالة الشركة.');
}

if ($action === 'delete') {
    $usersCount = 0;
    $usersStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM users WHERE company_id = ?');

    if ($usersStmt) {
        mysqli_stmt_bind_param($usersStmt, 'i', $id);
        mysqli_stmt_execute($usersStmt);
        $usersRes = mysqli_stmt_get_result($usersStmt);
        $usersRow = $usersRes ? mysqli_fetch_assoc($usersRes) : null;
        mysqli_stmt_close($usersStmt);
        if ($usersRow) {
            $usersCount = intval($usersRow['c']);
        }
    }

    if ($usersCount > 0) {
        companies_redirect_with_msg($redirectTo, 'error', 'لا يمكن حذف شركة مرتبطة بمستخدمين. قم بنقل/حذف المستخدمين أولاً.');
    }

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM admin_companies WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
        companies_redirect_with_msg($redirectTo, 'error', 'تعذر حذف الشركة حالياً.');
    }

    mysqli_stmt_bind_param($deleteStmt, 'i', $id);
    $ok = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($ok) {
        super_admin_write_audit($actorId, 'delete', 'شركة', 'حذف شركة رقم #' . $id, $id);
        companies_redirect_with_msg('companies', 'success', 'تم حذف الشركة بنجاح.');
    }

    companies_redirect_with_msg($redirectTo, 'error', 'فشل حذف الشركة.');
}

companies_redirect_with_msg('companies', 'error', 'الإجراء غير معروف.');
