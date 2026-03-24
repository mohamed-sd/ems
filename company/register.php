<?php
require_once __DIR__ . '/auth.php';

if (company_is_logged_in()) {
    $to = isset($_SESSION['company_user']['dashboard']) ? $_SESSION['company_user']['dashboard'] : '/ems/company/home.php';
    header('Location: ' . $to);
    exit();
}

$error = '';
$success = '';

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $success = 'تم إرسال طلب الاشتراك للباقات المدفوعة بنجاح. سيقوم فريقنا بمراجعته والتواصل معكم.';
}

function register_table_exists($tableName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    if ($safeTable === '') {
        return false;
    }

    $sql = "SHOW TABLES LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeTable) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);
    $exists = $res && mysqli_num_rows($res) > 0;

    return $exists;
}

function register_table_has_column($tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    if ($safeTable === '' || $safeCol === '') {
        return false;
    }

    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);

    return $res && mysqli_num_rows($res) > 0;
}

function company_freemium_plan_definition() {
    return array(
        'plan_name' => 'مجاني',
        'price' => 0,
        'max_users' => 3,
        'max_projects' => 1,
        'max_equipments' => 1,
        'features' => "تجربة ذاتية فورية\n1 مشروع\n1 معدة\n3 مستخدمين"
    );
}

function company_default_plan_catalog() {
    $freePlan = company_freemium_plan_definition();

    return array(
        array(
            'id' => 1,
            'plan_name' => $freePlan['plan_name'],
            'price' => $freePlan['price'],
            'max_users' => $freePlan['max_users'],
            'max_projects' => $freePlan['max_projects'],
            'max_equipments' => $freePlan['max_equipments'],
            'features' => $freePlan['features'],
            'sort_order' => 1,
            'is_active' => 1,
            'accent' => 'slate',
            'tagline' => 'ابدأ فوراً بدون انتظار موافقة وبحدود مناسبة للتجربة'
        ),
        array(
            'id' => 2,
            'plan_name' => 'أساسي',
            'price' => 99,
            'max_users' => 15,
            'max_projects' => 10,
            'max_equipments' => 50,
            'features' => "كل مزايا المجاني\nتقارير متقدمة\nدعم فني بالبريد",
            'sort_order' => 2,
            'is_active' => 1,
            'accent' => 'blue',
            'tagline' => 'أفضل نقطة بداية تشغيلية لمعظم الشركات'
        ),
        array(
            'id' => 3,
            'plan_name' => 'احترافي',
            'price' => 299,
            'max_users' => 50,
            'max_projects' => 30,
            'max_equipments' => 200,
            'features' => "كل مزايا الأساسي\nمستخدمون أكثر\nدعم أولوية\nتصدير Excel",
            'sort_order' => 3,
            'is_active' => 1,
            'accent' => 'gold',
            'tagline' => 'الخيار الأنسب للشركات المتنامية',
            'is_recommended' => 1
        ),
        array(
            'id' => 4,
            'plan_name' => 'مؤسسي',
            'price' => 699,
            'max_users' => 0,
            'max_projects' => 0,
            'max_equipments' => 0,
            'features' => "كل الميزات\nمدير حساب مخصص\nاتفاقية مستوى الخدمة\nتهيئة خاصة",
            'sort_order' => 4,
            'is_active' => 1,
            'accent' => 'ink',
            'tagline' => 'للمنشآت الكبيرة واحتياجات الحوكمة المتقدمة'
        )
    );
}

function ensure_saas_subscription_tables($conn) {
    $queries = array();

    if (!register_table_exists('admin_subscription_plans')) {
        $queries[] = "CREATE TABLE IF NOT EXISTS admin_subscription_plans (
            id INT NOT NULL AUTO_INCREMENT,
            plan_name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_users INT NOT NULL DEFAULT 0,
            max_projects INT NOT NULL DEFAULT 0,
            max_equipments INT NOT NULL DEFAULT 0,
            features TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    if (!register_table_exists('admin_companies')) {
        $queries[] = "CREATE TABLE IF NOT EXISTS admin_companies (
            id INT NOT NULL AUTO_INCREMENT,
            plan_id INT NULL,
            name VARCHAR(200) NOT NULL,
            company_name_ar VARCHAR(200) NULL,
            company_name_en VARCHAR(200) NULL,
            company_name VARCHAR(200) NULL,
            commercial_registration VARCHAR(120) NULL,
            sector VARCHAR(100) NULL,
            country VARCHAR(100) NULL,
            city VARCHAR(100) NULL,
            tax_number VARCHAR(120) NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            address TEXT NULL,
            postal_address TEXT NULL,
            logo_path VARCHAR(255) NULL,
            status ENUM('pending','active','suspended','cancelled') NOT NULL DEFAULT 'pending',
            modules_enabled TEXT NULL,
            subscription_start DATE NULL,
            subscription_end DATE NULL,
            users_count INT NOT NULL DEFAULT 0,
            max_users INT NOT NULL DEFAULT 0,
            max_equipments INT NOT NULL DEFAULT 0,
            max_projects INT NOT NULL DEFAULT 0,
            currency VARCHAR(20) NOT NULL DEFAULT 'SAR',
            timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_admin_companies_email (email),
            UNIQUE KEY uq_admin_companies_commercial_registration (commercial_registration),
            KEY idx_admin_companies_plan (plan_id),
            KEY idx_admin_companies_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    if (!register_table_exists('admin_subscription_requests')) {
        $queries[] = "CREATE TABLE IF NOT EXISTS admin_subscription_requests (
            id INT NOT NULL AUTO_INCREMENT,
            company_id INT NULL,
            company_name VARCHAR(200) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            plan_id INT NULL,
            message TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            review_note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_sub_req_status (status),
            KEY idx_admin_sub_req_plan (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    foreach ($queries as $sql) {
        if (!@mysqli_query($conn, $sql)) {
            return mysqli_error($conn);
        }
    }

    if (register_table_exists('admin_subscription_plans')) {
        $countRes = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM admin_subscription_plans');
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $countVal = $countRow ? intval($countRow['c']) : 0;

        if ($countVal === 0) {
            foreach (company_default_plan_catalog() as $plan) {
                $nameEsc = mysqli_real_escape_string($conn, $plan['plan_name']);
                $featuresEsc = mysqli_real_escape_string($conn, $plan['features']);
                $sql = "INSERT INTO admin_subscription_plans (id, plan_name, price, max_users, max_projects, max_equipments, features, sort_order, is_active)
                        VALUES (" . intval($plan['id']) . ", '" . $nameEsc . "', " . floatval($plan['price']) . ", " . intval($plan['max_users']) . ", " . intval($plan['max_projects']) . ", " . intval($plan['max_equipments']) . ", '" . $featuresEsc . "', " . intval($plan['sort_order']) . ", 1)";
                if (!@mysqli_query($conn, $sql)) {
                    return mysqli_error($conn);
                }
            }
        }
    }

    return '';
}

function company_get_plan_options($conn) {
    $plans = array();
    if (!register_table_exists('admin_subscription_plans')) {
        return company_default_plan_catalog();
    }

    $q = @mysqli_query($conn, 'SELECT id, plan_name, price, max_users, max_projects, max_equipments, features, sort_order, is_active FROM admin_subscription_plans WHERE is_active = 1 ORDER BY sort_order, id');
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $row['tagline'] = '';
            $row['accent'] = 'blue';
            $plans[] = $row;
        }
    }

    if (empty($plans)) {
        return company_default_plan_catalog();
    }

    return $plans;
}

function company_sync_freemium_plan($conn) {
    $def = company_freemium_plan_definition();

    if (!register_table_exists('admin_subscription_plans')) {
        $def['id'] = 1;
        return $def;
    }

    $planNameEsc = mysqli_real_escape_string($conn, $def['plan_name']);
    $findSql = "SELECT id FROM admin_subscription_plans WHERE LOWER(plan_name) = LOWER('" . $planNameEsc . "') ORDER BY id ASC LIMIT 1";
    $findRes = @mysqli_query($conn, $findSql);
    $row = $findRes ? mysqli_fetch_assoc($findRes) : null;

    if (!$row) {
        $freeRes = @mysqli_query($conn, 'SELECT id FROM admin_subscription_plans WHERE price = 0 ORDER BY sort_order ASC, id ASC LIMIT 1');
        $row = $freeRes ? mysqli_fetch_assoc($freeRes) : null;
    }

    if ($row) {
        $planId = intval($row['id']);
        $featuresEsc = mysqli_real_escape_string($conn, $def['features']);
        @mysqli_query($conn,
            "UPDATE admin_subscription_plans
             SET plan_name='" . $planNameEsc . "', price=0, max_users=" . intval($def['max_users']) . ", max_projects=" . intval($def['max_projects']) . ", max_equipments=" . intval($def['max_equipments']) . ", features='" . $featuresEsc . "', is_active=1, sort_order=1
             WHERE id=" . $planId
        );
        $def['id'] = $planId;
        return $def;
    }

    $insStmt = mysqli_prepare(
        $conn,
        'INSERT INTO admin_subscription_plans (plan_name, price, max_users, max_projects, max_equipments, features, sort_order, is_active) VALUES (?, 0, ?, ?, ?, ?, 1, 1)'
    );
    if (!$insStmt) {
        $def['id'] = 1;
        return $def;
    }

    mysqli_stmt_bind_param($insStmt, 'siiis', $def['plan_name'], $def['max_users'], $def['max_projects'], $def['max_equipments'], $def['features']);
    $ok = mysqli_stmt_execute($insStmt);
    mysqli_stmt_close($insStmt);

    $def['id'] = $ok ? intval(mysqli_insert_id($conn)) : 1;
    return $def;
}

function company_get_plan_index_by_id($plans) {
    $out = array();
    if (!is_array($plans)) {
        return $out;
    }

    foreach ($plans as $plan) {
        $pid = intval(isset($plan['id']) ? $plan['id'] : 0);
        if ($pid > 0) {
            $out[$pid] = $plan;
        }
    }

    return $out;
}

$bootstrapError = ensure_saas_subscription_tables($conn);
if ($bootstrapError !== '' && $error === '') {
    $error = 'تعذر تهيئة جداول الاشتراكات تلقائياً: ' . $bootstrapError;
}

$freemiumPlan = company_freemium_plan_definition();
if ($bootstrapError === '') {
    $freemiumPlan = company_sync_freemium_plan($conn);
}

$plans = company_get_plan_options($conn);
$plansById = company_get_plan_index_by_id($plans);
$freemiumPlanId = intval(isset($freemiumPlan['id']) ? $freemiumPlan['id'] : 1);
if ($freemiumPlanId <= 0) {
    $freemiumPlanId = 1;
}
if (!isset($plansById[$freemiumPlanId])) {
    $plansById[$freemiumPlanId] = $freemiumPlan;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
    } elseif ($bootstrapError !== '') {
        $error = 'تعذر تجهيز بيئة الاشتراكات حالياً. ' . $bootstrapError;
    } elseif (!register_table_exists('admin_subscription_requests')) {
        $error = 'تعذر إنشاء جدول طلبات الاشتراك تلقائياً.';
    } elseif (!register_table_exists('admin_companies')) {
        $error = 'تعذر إنشاء جدول الشركات تلقائياً.';
    } else {
        $companyName = trim(isset($_POST['company_name']) ? $_POST['company_name'] : '');
        $companyNameEn = trim(isset($_POST['company_name_en']) ? $_POST['company_name_en'] : '');
        $commercialRegistration = trim(isset($_POST['commercial_registration']) ? $_POST['commercial_registration'] : '');
        $sector = trim(isset($_POST['sector']) ? $_POST['sector'] : '');
        $country = trim(isset($_POST['country']) ? $_POST['country'] : '');
        $city = trim(isset($_POST['city']) ? $_POST['city'] : '');
        $taxNumber = trim(isset($_POST['tax_number']) ? $_POST['tax_number'] : '');
        $companyEmail = trim(isset($_POST['company_email']) ? $_POST['company_email'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $postalAddress = trim(isset($_POST['postal_address']) ? $_POST['postal_address'] : '');
        $planId = intval(isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
        $modulesEnabled = trim(isset($_POST['modules_enabled']) ? $_POST['modules_enabled'] : '');
        $currency = trim(isset($_POST['currency']) ? $_POST['currency'] : 'SAR');
        $timezone = trim(isset($_POST['timezone']) ? $_POST['timezone'] : 'Asia/Riyadh');

        $managerName = trim(isset($_POST['manager_name']) ? $_POST['manager_name'] : '');
        $managerEmail = trim(isset($_POST['manager_email']) ? $_POST['manager_email'] : '');
        $managerPhone = trim(isset($_POST['manager_phone']) ? $_POST['manager_phone'] : '');
        $managerPassword = trim(isset($_POST['manager_password']) ? $_POST['manager_password'] : '');
        $managerPasswordConfirm = trim(isset($_POST['manager_password_confirm']) ? $_POST['manager_password_confirm'] : '');

        $selectedPlanId = ($planId > 0 && isset($plansById[$planId])) ? $planId : $freemiumPlanId;
        $selectedPlan = isset($plansById[$selectedPlanId]) ? $plansById[$selectedPlanId] : $freemiumPlan;
        $isFreemium = ($selectedPlanId === $freemiumPlanId);

        if (!validate_length($companyName, 2, 200)) {
            $error = 'اسم الشركة مطلوب.';
        } elseif (!validate_length($commercialRegistration, 2, 120)) {
            $error = 'رقم السجل التجاري مطلوب.';
        } elseif (!in_array($sector, array('تعدين', 'مقاولات', 'إنشاء'), true)) {
            $error = 'قطاع النشاط غير صالح.';
        } elseif (!validate_length($country, 2, 100) || !validate_length($city, 2, 100)) {
            $error = 'الدولة والمدينة مطلوبتان.';
        } elseif (!validate_email($companyEmail) || !validate_length($companyEmail, 5, 150)) {
            $error = 'البريد الرسمي للشركة غير صالح.';
        } elseif (!validate_length($phone, 3, 30)) {
            $error = 'رقم هاتف الشركة مطلوب.';
        } elseif (!validate_length($managerName, 2, 150)) {
            $error = 'اسم المدير العام مطلوب.';
        } elseif (!validate_email($managerEmail) || !validate_length($managerEmail, 5, 150)) {
            $error = 'بريد المدير العام غير صالح.';
        } elseif (!validate_length($managerPhone, 3, 30)) {
            $error = 'رقم هاتف المدير مطلوب.';
        } elseif ($isFreemium && !validate_length($managerPassword, 8, 100)) {
            $error = 'كلمة مرور المدير العام مطلوبة (8 أحرف على الأقل) للتفعيل الفوري.';
        } elseif ($isFreemium && $managerPassword !== $managerPasswordConfirm) {
            $error = 'تأكيد كلمة المرور غير مطابق.';
        } else {
            $checkCompany = mysqli_prepare($conn, 'SELECT id FROM admin_companies WHERE email = ? LIMIT 1');
            $checkRequest = mysqli_prepare($conn, 'SELECT id FROM admin_subscription_requests WHERE email = ? AND status = "pending" LIMIT 1');

            if (!$checkCompany || !$checkRequest) {
                $error = 'تعذر التحقق من البيانات حالياً.';
            } else {
                mysqli_stmt_bind_param($checkCompany, 's', $companyEmail);
                mysqli_stmt_execute($checkCompany);
                $companyExists = mysqli_stmt_get_result($checkCompany);
                $companyExistsRow = $companyExists ? mysqli_fetch_assoc($companyExists) : null;
                mysqli_stmt_close($checkCompany);

                mysqli_stmt_bind_param($checkRequest, 's', $companyEmail);
                mysqli_stmt_execute($checkRequest);
                $requestExists = mysqli_stmt_get_result($checkRequest);
                $requestExistsRow = $requestExists ? mysqli_fetch_assoc($requestExists) : null;
                mysqli_stmt_close($checkRequest);

                $managerDupRow = null;
                if ($error === '') {
                    $managerEmailEsc = mysqli_real_escape_string($conn, $managerEmail);
                    $dupSql = "SELECT id FROM users WHERE username='" . $managerEmailEsc . "'";
                    if (company_users_has_column('email')) {
                        $dupSql .= " OR email='" . $managerEmailEsc . "'";
                    }
                    $dupSql .= ' LIMIT 1';
                    $dupRes = @mysqli_query($conn, $dupSql);
                    $managerDupRow = $dupRes ? mysqli_fetch_assoc($dupRes) : null;
                }

                if ($companyExistsRow) {
                    $error = 'البريد الرسمي للشركة مسجل مسبقاً.';
                } elseif ($managerDupRow) {
                    $error = 'بريد المدير العام مستخدم مسبقاً.';
                } elseif (!$isFreemium && $requestExistsRow) {
                    $error = 'يوجد طلب اشتراك معلق بنفس البريد الإلكتروني.';
                } elseif ($isFreemium) {
                    mysqli_query($conn, 'START TRANSACTION');

                    $companyEsc = mysqli_real_escape_string($conn, $companyName);
                    $companyNameEnEsc = mysqli_real_escape_string($conn, $companyNameEn);
                    $commercialRegistrationEsc = mysqli_real_escape_string($conn, $commercialRegistration);
                    $sectorEsc = mysqli_real_escape_string($conn, $sector);
                    $countryEsc = mysqli_real_escape_string($conn, $country);
                    $cityEsc = mysqli_real_escape_string($conn, $city);
                    $taxNumberEsc = mysqli_real_escape_string($conn, $taxNumber);
                    $companyEmailEsc = mysqli_real_escape_string($conn, $companyEmail);
                    $phoneEsc = mysqli_real_escape_string($conn, $phone);
                    $postalAddressEsc = mysqli_real_escape_string($conn, $postalAddress);
                    $modulesEnabledEsc = mysqli_real_escape_string($conn, $modulesEnabled);
                    $currencyEsc = mysqli_real_escape_string($conn, $currency);
                    $timezoneEsc = mysqli_real_escape_string($conn, $timezone);

                    $maxUsers = intval(isset($freemiumPlan['max_users']) ? $freemiumPlan['max_users'] : 3);
                    $maxProjects = intval(isset($freemiumPlan['max_projects']) ? $freemiumPlan['max_projects'] : 1);
                    $maxEquipments = intval(isset($freemiumPlan['max_equipments']) ? $freemiumPlan['max_equipments'] : 1);

                    $cols = array();
                    $vals = array();

                    if (register_table_has_column('admin_companies', 'name')) {
                        $cols[] = 'name';
                        $vals[] = "'" . $companyEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'company_name')) {
                        $cols[] = 'company_name';
                        $vals[] = "'" . $companyEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'company_name_ar')) {
                        $cols[] = 'company_name_ar';
                        $vals[] = "'" . $companyEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'company_name_en')) {
                        $cols[] = 'company_name_en';
                        $vals[] = "'" . $companyNameEnEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'commercial_registration')) {
                        $cols[] = 'commercial_registration';
                        $vals[] = "'" . $commercialRegistrationEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'sector')) {
                        $cols[] = 'sector';
                        $vals[] = "'" . $sectorEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'country')) {
                        $cols[] = 'country';
                        $vals[] = "'" . $countryEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'city')) {
                        $cols[] = 'city';
                        $vals[] = "'" . $cityEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'tax_number')) {
                        $cols[] = 'tax_number';
                        $vals[] = "'" . $taxNumberEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'email')) {
                        $cols[] = 'email';
                        $vals[] = "'" . $companyEmailEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'phone')) {
                        $cols[] = 'phone';
                        $vals[] = "'" . $phoneEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'address')) {
                        $cols[] = 'address';
                        $vals[] = "'" . $postalAddressEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'postal_address')) {
                        $cols[] = 'postal_address';
                        $vals[] = "'" . $postalAddressEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'modules_enabled')) {
                        $cols[] = 'modules_enabled';
                        $vals[] = "'" . $modulesEnabledEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'currency')) {
                        $cols[] = 'currency';
                        $vals[] = "'" . $currencyEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'timezone')) {
                        $cols[] = 'timezone';
                        $vals[] = "'" . $timezoneEsc . "'";
                    }
                    if (register_table_has_column('admin_companies', 'plan_id')) {
                        $cols[] = 'plan_id';
                        $vals[] = strval($freemiumPlanId);
                    }
                    if (register_table_has_column('admin_companies', 'status')) {
                        $cols[] = 'status';
                        $vals[] = "'active'";
                    }
                    if (register_table_has_column('admin_companies', 'subscription_start')) {
                        $cols[] = 'subscription_start';
                        $vals[] = 'CURDATE()';
                    }
                    if (register_table_has_column('admin_companies', 'users_count')) {
                        $cols[] = 'users_count';
                        $vals[] = '1';
                    }
                    if (register_table_has_column('admin_companies', 'max_users')) {
                        $cols[] = 'max_users';
                        $vals[] = strval($maxUsers);
                    }
                    if (register_table_has_column('admin_companies', 'max_projects')) {
                        $cols[] = 'max_projects';
                        $vals[] = strval($maxProjects);
                    }
                    if (register_table_has_column('admin_companies', 'max_equipments')) {
                        $cols[] = 'max_equipments';
                        $vals[] = strval($maxEquipments);
                    }

                    $insertCompanySql = 'INSERT INTO admin_companies (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
                    $companyOk = @mysqli_query($conn, $insertCompanySql);

                    if (!$companyOk) {
                        mysqli_query($conn, 'ROLLBACK');
                        $error = 'فشل إنشاء الشركة: ' . mysqli_error($conn);
                    } else {
                        $companyId = intval(mysqli_insert_id($conn));
                        $managerNameEsc = mysqli_real_escape_string($conn, $managerName);
                        $managerEmailEsc = mysqli_real_escape_string($conn, $managerEmail);
                        $managerPhoneEsc = mysqli_real_escape_string($conn, $managerPhone);
                        $passwordHashEsc = mysqli_real_escape_string($conn, password_hash($managerPassword, PASSWORD_BCRYPT));

                        $uCols = array('name', 'username', 'password', 'phone', 'role');
                        $uVals = array("'" . $managerNameEsc . "'", "'" . $managerEmailEsc . "'", "'" . $passwordHashEsc . "'", "'" . $managerPhoneEsc . "'", "'1'");

                        if (register_table_has_column('users', 'email')) {
                            $uCols[] = 'email';
                            $uVals[] = "'" . $managerEmailEsc . "'";
                        }
                        if (register_table_has_column('users', 'company_id')) {
                            $uCols[] = 'company_id';
                            $uVals[] = strval($companyId);
                        }
                        if (register_table_has_column('users', 'role_id')) {
                            $uCols[] = 'role_id';
                            $uVals[] = '1';
                        }
                        if (register_table_has_column('users', 'status')) {
                            $uCols[] = 'status';
                            $uVals[] = "'active'";
                        }
                        if (register_table_has_column('users', 'force_password_change')) {
                            $uCols[] = 'force_password_change';
                            $uVals[] = '0';
                        }

                        $insertUserSql = 'INSERT INTO users (' . implode(', ', $uCols) . ') VALUES (' . implode(', ', $uVals) . ')';
                        $userOk = @mysqli_query($conn, $insertUserSql);

                        if (!$userOk) {
                            mysqli_query($conn, 'ROLLBACK');
                            $error = 'فشل إنشاء حساب المدير العام: ' . mysqli_error($conn);
                        } else {
                            mysqli_query($conn, 'COMMIT');

                            $newUser = array(
                                'id' => intval(mysqli_insert_id($conn)),
                                'name' => $managerName,
                                'username' => $managerEmail,
                                'email' => $managerEmail,
                                'phone' => $managerPhone,
                                'role' => '1',
                                'project_id' => 0,
                                'mine_id' => 0,
                                'contract_id' => 0
                            );
                            $newCompany = array(
                                'id' => $companyId,
                                'company_name' => $companyName
                            );

                            company_login_success($newUser, $newCompany);
                            company_redirect('home.php');
                        }
                    }
                } else {
                    $selectedMaxUsers = intval(isset($selectedPlan['max_users']) ? $selectedPlan['max_users'] : 0);
                    $selectedMaxEquipments = intval(isset($selectedPlan['max_equipments']) ? $selectedPlan['max_equipments'] : 0);
                    $selectedMaxProjects = intval(isset($selectedPlan['max_projects']) ? $selectedPlan['max_projects'] : 0);

                    $payload = array(
                        'company_name_en' => $companyNameEn,
                        'commercial_registration' => $commercialRegistration,
                        'sector' => $sector,
                        'country' => $country,
                        'city' => $city,
                        'tax_number' => $taxNumber,
                        'postal_address' => $postalAddress,
                        'modules_enabled' => $modulesEnabled,
                        'currency' => $currency,
                        'timezone' => $timezone,
                        'max_users' => max(0, $selectedMaxUsers),
                        'max_equipments' => max(0, $selectedMaxEquipments),
                        'max_projects' => max(0, $selectedMaxProjects),
                        'manager_name' => $managerName,
                        'manager_email' => $managerEmail,
                        'manager_phone' => $managerPhone,
                        'source' => 'company_register'
                    );

                    $messageJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
                    if ($messageJson === false) {
                        $messageJson = '';
                    }

                    $insertReq = mysqli_prepare(
                        $conn,
                        'INSERT INTO admin_subscription_requests (company_name, email, phone, plan_id, message, status) VALUES (?, ?, ?, ?, ?, "pending")'
                    );

                    if (!$insertReq) {
                        $error = 'تعذر إرسال الطلب حالياً. ' . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($insertReq, 'sssis', $companyName, $companyEmail, $phone, $selectedPlanId, $messageJson);
                        $ok = mysqli_stmt_execute($insertReq);
                        mysqli_stmt_close($insertReq);

                        if ($ok) {
                            company_redirect('register.php', array('submitted' => '1'));
                        } else {
                            $error = 'فشل إرسال الطلب: ' . mysqli_error($conn);
                        }
                    }
                }
            }
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب تسجيل شركة | إيكوبيشن</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ─── DESIGN TOKENS (matches main app index.php) ─── */
:root {
    --navy:   #0c1c3e;
    --navy-m: #132050;
    --navy-l: #1b2f6e;
    --gold:   #e8b800;
    --gold-l: #ffd740;
    --gold-d: rgba(232,184,0,.13);
    --bg:     #f0f2f8;
    --card:   #ffffff;
    --bdr:    rgba(12,28,62,.08);
    --txt:    #0c1c3e;
    --sub:    #64748b;
    --ok:     #0a7a52;
    --ok-bg:  rgba(10,122,82,.09);
    --danger: #dc2626;
    --dgr:    rgba(220,38,38,.09);
    --ease:   .22s cubic-bezier(.4,0,.2,1);
    --font:   'Cairo', sans-serif;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html,body { height:100%; font-family:var(--font); color:var(--txt); }

/* ─── LAYOUT ─── */
.page { display:grid; grid-template-columns:400px 1fr; min-height:100vh; }

/* ══════════════════════════════════════
   LEFT HERO PANEL
══════════════════════════════════════ */
.panel {
    position:relative; overflow:hidden;
    background:linear-gradient(145deg, var(--navy) 0%, var(--navy-m) 50%, var(--navy-l) 100%);
    display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between;
    padding:40px 36px; gap:0;
}
/* dot-grid overlay */
.panel::before {
    content:''; position:absolute; inset:0;
    background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
    background-size:22px 22px; pointer-events:none;
}
/* gold orb top-left */
.panel::after {
    content:''; position:absolute; top:-90px; left:-90px;
    width:340px; height:340px; border-radius:50%;
    background:radial-gradient(circle, rgba(232,184,0,.22) 0%, transparent 65%);
    pointer-events:none;
}
.orb-br {
    position:absolute; bottom:-60px; right:-60px;
    width:260px; height:260px; border-radius:50%;
    background:radial-gradient(circle, rgba(27,47,110,.55) 0%, transparent 70%);
    pointer-events:none;
}

/* brand */
.p-brand { position:relative; z-index:1; display:flex; flex-direction:column; gap:10px; }
.p-icon {
    width:62px; height:62px; border-radius:18px;
    background:linear-gradient(135deg, var(--gold), var(--gold-l));
    display:flex; align-items:center; justify-content:center;
    font-size:1.7rem; color:var(--navy);
    box-shadow:0 8px 28px rgba(232,184,0,.4);
    animation:bob 4s ease-in-out infinite;
}
@keyframes bob { 0%,100%{transform:translateY(0) rotate(-2deg)} 50%{transform:translateY(-8px) rotate(2deg)} }
.p-name { font-size:1.85rem; font-weight:900; color:#fff; line-height:1.1; }
.p-name em { color:var(--gold); font-style:normal; }
.p-tag { font-size:.72rem; font-weight:600; color:rgba(255,255,255,.4); letter-spacing:.1em; text-transform:uppercase; }

/* hero headline */
.p-headline { position:relative; z-index:1; flex:1; display:flex; flex-direction:column; justify-content:center; gap:12px; padding:28px 0; }
.p-headline h2 { font-size:1.55rem; font-weight:900; color:#fff; line-height:1.35; }
.p-headline h2 span { color:var(--gold); }
.p-headline p { font-size:.82rem; color:rgba(255,255,255,.52); line-height:1.7; }

/* equipment silhouette strip */
.equip-strip {
    position:relative; z-index:1; display:flex; gap:10px; align-items:flex-end;
    padding:12px 0; border-top:1px solid rgba(255,255,255,.07);
    border-bottom:1px solid rgba(255,255,255,.07); margin:4px 0;
}
.equip-ico {
    display:flex; flex-direction:column; align-items:center; gap:5px;
    padding:8px 10px; border-radius:12px;
    background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.07);
    transition:background var(--ease);
}
.equip-ico:hover { background:rgba(232,184,0,.1); border-color:rgba(232,184,0,.22); }
.equip-ico i { font-size:1.35rem; color:rgba(255,255,255,.65); }
.equip-ico span { font-size:.62rem; color:rgba(255,255,255,.38); font-weight:600; white-space:nowrap; }

/* feature list */
.features { position:relative; z-index:1; display:flex; flex-direction:column; gap:8px; width:100%; }
.feat {
    display:flex; align-items:center; gap:12px;
    background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.07);
    border-radius:12px; padding:10px 13px;
    transition:background var(--ease), border-color var(--ease);
}
.feat:hover { background:rgba(255,255,255,.09); border-color:rgba(232,184,0,.2); }
.fi-ico {
    width:34px; height:34px; flex-shrink:0; border-radius:9px;
    background:var(--gold-d); display:flex; align-items:center; justify-content:center;
    font-size:.85rem; color:var(--gold);
}
.fi-txt h4 { font-size:.82rem; font-weight:700; color:#fff; }
.fi-txt p { font-size:.7rem; color:rgba(255,255,255,.38); margin-top:1px; }

/* stats */
.p-stats {
    position:relative; z-index:1; display:flex; align-items:center; gap:0;
    background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.07);
    border-radius:14px; overflow:hidden; width:100%;
}
.stat-item { flex:1; text-align:center; padding:10px 8px; }
.stat-num { font-size:1.15rem; font-weight:900; color:var(--gold); line-height:1; }
.stat-lbl { font-size:.65rem; color:rgba(255,255,255,.38); margin-top:2px; }
.stat-sep { width:1px; align-self:stretch; background:rgba(255,255,255,.08); }

.p-foot { position:relative; z-index:1; font-size:.66rem; color:rgba(255,255,255,.25); letter-spacing:.05em; }

/* ══════════════════════════════════════
   RIGHT FORM SIDE
══════════════════════════════════════ */
.form-side {
    background:var(--bg); overflow-y:auto;
    display:flex; align-items:flex-start; justify-content:center;
    padding:32px 24px; position:relative;
}
.form-side::before {
    content:''; position:absolute; inset:0;
    background-image:radial-gradient(rgba(12,28,62,.04) 1px, transparent 1px);
    background-size:24px 24px; pointer-events:none;
}

/* ─── CARD ─── */
.card {
    position:relative; z-index:1;
    background:var(--card); border-radius:24px;
    border:1.5px solid var(--bdr);
    padding:32px 28px; width:100%; max-width:680px;
    box-shadow:0 14px 46px rgba(12,28,62,.12);
    animation:fadeUp .45s cubic-bezier(.4,0,.2,1) both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

/* card header */
.card-head { display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:22px; gap:6px; }
.ch-badge {
    display:inline-flex; align-items:center; gap:7px;
    background:var(--gold-d); border:1px solid rgba(232,184,0,.3);
    border-radius:999px; padding:6px 14px;
    font-size:.75rem; font-weight:700; color:#8a6a00;
}
.card-head h2 { font-size:1.35rem; font-weight:900; color:var(--navy); margin:0; }
.card-head p { font-size:.8rem; color:var(--sub); margin:0; }

/* divider */
.div-line { height:1px; background:linear-gradient(90deg,transparent,var(--bdr),var(--gold-d),var(--bdr),transparent); margin:0 -4px 20px; }

/* alerts */
.alert { display:flex; align-items:flex-start; gap:10px; border-radius:12px; padding:11px 14px; margin-bottom:16px; font-size:.84rem; font-weight:600; line-height:1.5; }
.alert-ok  { background:var(--ok-bg);  border:1px solid rgba(10,122,82,.18); color:var(--ok); }
.alert-err { background:var(--dgr); border:1px solid rgba(220,38,38,.18); color:var(--danger); animation:shake .38s ease; }
@keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-4px)} 40%,80%{transform:translateX(4px)} }
.alert i { flex-shrink:0; margin-top:1px; }

/* ─── STEP NAVIGATOR ─── */
.step-nav {
    display:flex; align-items:center; justify-content:center;
    gap:0; margin-bottom:20px;
}
.sn-item { display:flex; flex-direction:column; align-items:center; gap:5px; position:relative; }
.sn-dot {
    width:40px; height:40px; border-radius:50%;
    border:2px solid var(--bdr);
    display:flex; align-items:center; justify-content:center;
    font-size:.85rem; color:var(--sub);
    background:var(--bg);
    transition:border-color var(--ease), background var(--ease), color var(--ease), box-shadow var(--ease);
    position:relative; z-index:1;
}
.sn-lbl { font-size:.68rem; font-weight:700; color:var(--sub); white-space:nowrap; transition:color var(--ease); }
.sn-line { flex:1; min-width:40px; height:2px; background:var(--bdr); margin:0 -2px; margin-bottom:21px; transition:background var(--ease); }

.sn-item.active .sn-dot { background:var(--navy); border-color:var(--navy); color:#fff; box-shadow:0 4px 14px rgba(12,28,62,.25); }
.sn-item.active .sn-lbl { color:var(--navy); font-weight:800; }
.sn-item.done .sn-dot { background:var(--gold); border-color:var(--gold); color:var(--navy); }
.sn-item.done .sn-lbl { color:var(--ok); }
.sn-line.done { background:var(--gold); }

/* section heading */
.sec-head { display:flex; align-items:center; gap:9px; margin:4px 0 16px; }
.sec-head .sec-ico { width:30px; height:30px; border-radius:8px; background:var(--gold-d); display:flex; align-items:center; justify-content:center; font-size:.8rem; color:var(--gold); flex-shrink:0; }
.sec-head h3 { font-size:.92rem; font-weight:800; color:var(--navy); margin:0; }

/* ─── FORM FIELDS ─── */
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.field { margin-bottom:13px; }
.field label { display:block; font-size:.7rem; font-weight:700; color:var(--sub); letter-spacing:.07em; text-transform:uppercase; margin-bottom:5px; }
.fw { position:relative; }
.fw .ico { position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:.82rem; color:var(--sub); pointer-events:none; transition:color var(--ease); }
.fw .ico-l { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:.82rem; color:var(--sub); pointer-events:none; }
.fw input, .fw select, .fw textarea {
    width:100%; padding:10px 36px 10px 12px;
    border-radius:11px; border:1.5px solid var(--bdr);
    background:var(--bg); font-family:var(--font);
    font-size:.88rem; font-weight:500; color:var(--txt);
    outline:none; transition:border-color var(--ease), box-shadow var(--ease), background var(--ease);
}
.fw select { padding-left:12px; }
.fw textarea { padding:10px 36px 10px 12px; min-height:72px; resize:vertical; }
.fw input::placeholder, .fw textarea::placeholder { color:#b0b8cc; font-weight:400; }
.fw input:focus,.fw select:focus,.fw textarea:focus { background:#fff; border-color:var(--gold); box-shadow:0 0 0 3px rgba(232,184,0,.14); }
.fw input:focus~.ico,.fw select:focus~.ico,.fw textarea:focus~.ico { color:var(--gold); }

/* no-icon fields */
.field>input,.field>select,.field>textarea {
    width:100%; padding:10px 12px;
    border-radius:11px; border:1.5px solid var(--bdr);
    background:var(--bg); font-family:var(--font);
    font-size:.88rem; font-weight:500; color:var(--txt);
    outline:none; transition:border-color var(--ease), box-shadow var(--ease), background var(--ease);
}
.field>input:focus,.field>select:focus,.field>textarea:focus { background:#fff; border-color:var(--gold); box-shadow:0 0 0 3px rgba(232,184,0,.14); }
.field>textarea { min-height:72px; resize:vertical; }

/* ─── PLAN CARDS ─── */
.plans-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:4px; }
.plan-card {
    position:relative; border:1.5px solid var(--bdr); border-radius:16px;
    padding:16px; background:#fff; cursor:pointer;
    transition:transform var(--ease), box-shadow var(--ease), border-color var(--ease), background var(--ease);
}
.plan-card:hover { transform:translateY(-2px); box-shadow:0 12px 30px rgba(12,28,62,.1); }
.plan-card.selected { border-color:var(--gold); box-shadow:0 0 0 3px rgba(232,184,0,.18), 0 12px 30px rgba(232,184,0,.14); background:linear-gradient(160deg,#fffef7,#fff9e0); }
.plan-card.recommended::before {
    content:'الأكثر شيوعاً'; position:absolute; top:-10px; right:14px;
    background:linear-gradient(135deg,var(--navy),var(--navy-l)); color:var(--gold);
    font-size:.65rem; padding:4px 10px; border-radius:999px; font-weight:800;
}
.plan-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.plan-title { font-size:.95rem; font-weight:900; color:var(--navy); }
.plan-price-badge { background:var(--gold-d); border-radius:999px; padding:3px 9px; font-size:.7rem; font-weight:800; color:#7a5f00; }
.plan-price-badge.free { background:rgba(10,122,82,.1); color:var(--ok); }
.plan-tagline { color:var(--sub); font-size:.75rem; margin-bottom:10px; min-height:32px; }
.plan-limits { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.plan-limit { font-size:.7rem; background:var(--bg); border-radius:7px; padding:3px 8px; color:var(--navy); font-weight:700; }
.plan-features { margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:5px; }
.plan-features li { font-size:.74rem; color:#334155; padding-right:16px; position:relative; }
.plan-features li::before { content:'✓'; position:absolute; right:0; color:var(--gold); font-weight:900; font-size:.72rem; }
.plan-sel { margin-top:12px; display:flex; align-items:center; justify-content:space-between; }
.plan-sel span { font-size:.75rem; font-weight:700; color:var(--sub); }
.plan-dot { width:18px; height:18px; border-radius:50%; border:2px solid var(--bdr); display:inline-flex; align-items:center; justify-content:center; transition:border-color var(--ease); }
.plan-card.selected .plan-dot { border-color:var(--gold); }
.plan-card.selected .plan-dot::after { content:''; width:8px; height:8px; background:var(--gold); border-radius:50%; display:block; }
.plan-select-fallback { display:none; }

/* ─── BUTTONS ─── */
.step-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; padding-top:16px; border-top:1px solid var(--bdr); }
.step-actions.single { justify-content:space-between; }
.btn-primary {
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    border:none; border-radius:12px; padding:11px 22px;
    background:linear-gradient(135deg,var(--navy) 0%,var(--navy-l) 100%);
    color:#fff; font-family:var(--font); font-size:.88rem; font-weight:800;
    cursor:pointer; position:relative; overflow:hidden;
    transition:transform var(--ease), box-shadow var(--ease);
    box-shadow:0 5px 16px rgba(12,28,62,.22);
}
.btn-primary::after { content:''; position:absolute; inset:0; background:linear-gradient(135deg,var(--gold),var(--gold-l)); opacity:0; transition:opacity var(--ease); }
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(12,28,62,.26); }
.btn-primary:hover::after { opacity:1; }
.btn-primary:hover i,.btn-primary:hover span { color:var(--navy) !important; }
.btn-primary i,.btn-primary span { position:relative; z-index:1; }
.btn-secondary {
    display:inline-flex; align-items:center; gap:7px; border:1.5px solid var(--bdr);
    border-radius:12px; padding:10px 18px; background:#fff;
    color:var(--navy); font-family:var(--font); font-size:.85rem; font-weight:700;
    cursor:pointer; transition:border-color var(--ease), background var(--ease), transform var(--ease);
}
.btn-secondary:hover { border-color:rgba(232,184,0,.5); background:rgba(232,184,0,.07); transform:translateY(-1px); }
.link { color:var(--navy); font-size:.82rem; font-weight:700; text-decoration:none; }
.link:hover { color:var(--gold); text-decoration:underline; }

/* ─── MISC ─── */
.note-box { display:flex; align-items:flex-start; gap:9px; background:var(--bg); border:1px solid var(--bdr); border-radius:11px; padding:10px 13px; margin-top:10px; }
.note-box i { color:var(--gold); flex-shrink:0; margin-top:2px; font-size:.82rem; }
.note-box p { font-size:.76rem; color:var(--sub); line-height:1.6; }
.note-box p strong { color:var(--navy); }

/* ─── RESPONSIVE ─── */
@media (max-width:1000px) { .page { grid-template-columns:330px 1fr; } }
@media (max-width:820px) { .page { grid-template-columns:1fr; } .panel { display:none; } .form-side { padding:24px 16px; } .plans-grid { grid-template-columns:1fr 1fr; } }
@media (max-width:520px) { .grid2 { grid-template-columns:1fr; } .plans-grid { grid-template-columns:1fr; } .card { padding:22px 16px; } }
    </style>
</head>
<body>
<div class="page">

    <!-- ████ LEFT HERO PANEL ████ -->
    <div class="panel">
        <div class="orb-br"></div>

        <!-- Brand -->
        <div class="p-brand">
            <div class="p-icon"><i class="fas fa-truck-monster"></i></div>
            <div class="p-name">EnJ<em>az</em></div>
            <div class="p-tag">Heavy Equipment Management SaaS</div>
        </div>

        <!-- Headline -->
        <div class="p-headline">
            <h2>أدر أسطولك الثقيل<br><span>بذكاء وكفاءة عالية</span></h2>
            <p>منصة متكاملة مخصصة لشركات المعدات الثقيلة والتعدين — من تتبع الحفارات والقلابات حتى إدارة عقود الموردين وساعات التشغيل.</p>

            <!-- Equipment icons strip -->
            <div class="equip-strip">
                <div class="equip-ico">
                    <i class="fas fa-tractor"></i>
                    <span>حفارات</span>
                </div>
                <div class="equip-ico">
                    <i class="fas fa-truck"></i>
                    <span>قلابات</span>
                </div>
                <div class="equip-ico">
                    <i class="fas fa-tools"></i>
                    <span>صيانة</span>
                </div>
                <div class="equip-ico">
                    <i class="fas fa-industry"></i>
                    <span>مصانع</span>
                </div>
                <div class="equip-ico">
                    <i class="fas fa-mountain"></i>
                    <span>مناجم</span>
                </div>
                <div class="equip-ico">
                    <i class="fas fa-hard-hat"></i>
                    <span>مشغلون</span>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="features">
            <div class="feat">
                <div class="fi-ico"><i class="fas fa-layer-group"></i></div>
                <div class="fi-txt"><h4>إدارة الأسطول والمعدات</h4><p>تتبع شامل لجميع الآليات لحظةً بلحظة</p></div>
            </div>
            <div class="feat">
                <div class="fi-ico"><i class="fas fa-file-contract"></i></div>
                <div class="fi-txt"><h4>عقود الموردين والمشاريع</h4><p>دورة حياة كاملة وتتبع تلقائي للحالة</p></div>
            </div>
            <div class="feat">
                <div class="fi-ico"><i class="fas fa-clock"></i></div>
                <div class="fi-txt"><h4>الجداول الزمنية وساعات العمل</h4><p>تسجيل دقيق لساعات المشغلين والتشغيل</p></div>
            </div>
            <div class="feat">
                <div class="fi-ico"><i class="fas fa-chart-line"></i></div>
                <div class="fi-txt"><h4>تقارير وتحليلات الأداء</h4><p>بيانات آنية لدعم قرارات الإدارة العليا</p></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="p-stats">
            <div class="stat-item">
                <div class="stat-num">+500</div>
                <div class="stat-lbl">معدة مُدارة</div>
            </div>
            <div class="stat-sep"></div>
            <div class="stat-item">
                <div class="stat-num">+50</div>
                <div class="stat-lbl">شركة نشطة</div>
            </div>
            <div class="stat-sep"></div>
            <div class="stat-item">
                <div class="stat-num">+200</div>
                <div class="stat-lbl">مشروع منجز</div>
            </div>
        </div>

        <div class="p-foot">© <?php echo date('Y'); ?> إيكوبيشن — جميع الحقوق محفوظة</div>
    </div>

    <!-- ████ RIGHT FORM SIDE ████ -->
    <div class="form-side">
        <div class="card">

            <!-- Card header -->
            <div class="card-head">
                <span class="ch-badge"><i class="fas fa-building"></i> تسجيل شركة جديدة</span>
                <h2>ابدأ فوراً على الباقة المجانية</h2>
                <p>اختر الباقة المناسبة: المجانية تتفعّل مباشرة، والمدفوعة تُرسل للمراجعة</p>
            </div>

            <!-- Step navigator -->
            <div class="step-nav" id="stepNav">
                <div class="sn-item active" id="sn-1">
                    <div class="sn-dot"><i class="fas fa-building"></i></div>
                    <div class="sn-lbl">الشركة</div>
                </div>
                <div class="sn-line" id="sl-1"></div>
                <div class="sn-item" id="sn-2">
                    <div class="sn-dot"><i class="fas fa-address-card"></i></div>
                    <div class="sn-lbl">التواصل</div>
                </div>
                <div class="sn-line" id="sl-2"></div>
                <div class="sn-item" id="sn-3">
                    <div class="sn-dot"><i class="fas fa-crown"></i></div>
                    <div class="sn-lbl">الباقة</div>
                </div>
            </div>

            <div class="div-line"></div>

            <!-- Alerts -->
            <?php if ($success !== ''): ?>
            <div class="alert alert-ok"><i class="fas fa-check-circle"></i><span><?php echo e($success); ?></span></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
            <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i><span><?php echo e($error); ?></span></div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <!-- ══ STEP 1: Company Identity ══ -->
                <div class="step" data-step="1">
                    <div class="sec-head">
                        <div class="sec-ico"><i class="fas fa-id-card"></i></div>
                        <h3>هوية الشركة</h3>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="company_name">اسم الشركة (عربي) *</label>
                            <div class="fw">
                                <input id="company_name" name="company_name" maxlength="200" required placeholder="شركة المعدات الثقيلة" value="<?php echo isset($_POST['company_name']) ? e($_POST['company_name']) : ''; ?>">
                                <i class="fas fa-building ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="company_name_en">اسم الشركة (إنجليزي)</label>
                            <div class="fw">
                                <input id="company_name_en" name="company_name_en" maxlength="200" placeholder="Heavy Equipment Co." value="<?php echo isset($_POST['company_name_en']) ? e($_POST['company_name_en']) : ''; ?>">
                                <i class="fas fa-building ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="commercial_registration">السجل التجاري *</label>
                            <div class="fw">
                                <input id="commercial_registration" name="commercial_registration" maxlength="120" required placeholder="1234567890" value="<?php echo isset($_POST['commercial_registration']) ? e($_POST['commercial_registration']) : ''; ?>">
                                <i class="fas fa-file-alt ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="sector">قطاع النشاط *</label>
                            <div class="fw">
                                <select id="sector" name="sector" required>
                                    <option value="">— اختر القطاع —</option>
                                    <option value="تعدين" <?php echo (isset($_POST['sector']) && $_POST['sector'] === 'تعدين') ? 'selected' : ''; ?>>⛏️ تعدين</option>
                                    <option value="مقاولات" <?php echo (isset($_POST['sector']) && $_POST['sector'] === 'مقاولات') ? 'selected' : ''; ?>>🏗️ مقاولات</option>
                                    <option value="إنشاء" <?php echo (isset($_POST['sector']) && $_POST['sector'] === 'إنشاء') ? 'selected' : ''; ?>>🏢 إنشاء</option>
                                </select>
                                <i class="fas fa-industry ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="country">الدولة *</label>
                            <div class="fw">
                                <input id="country" name="country" maxlength="100" required placeholder="المملكة العربية السعودية" value="<?php echo isset($_POST['country']) ? e($_POST['country']) : ''; ?>">
                                <i class="fas fa-globe ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="city">المدينة *</label>
                            <div class="fw">
                                <input id="city" name="city" maxlength="100" required placeholder="الرياض" value="<?php echo isset($_POST['city']) ? e($_POST['city']) : ''; ?>">
                                <i class="fas fa-map-marker-alt ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="step-actions single">
                        <a class="link" href="<?php echo e(company_url('login.php')); ?>"><i class="fas fa-sign-in-alt"></i> لديك حساب؟ تسجيل الدخول</a>
                        <button class="btn-primary" type="button" onclick="nextStep()"><span>التالي</span><i class="fas fa-arrow-left"></i></button>
                    </div>
                </div>

                <!-- ══ STEP 2: Contact & Manager ══ -->
                <div class="step" data-step="2" style="display:none;">
                    <div class="sec-head">
                        <div class="sec-ico"><i class="fas fa-phone-alt"></i></div>
                        <h3>بيانات التواصل</h3>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="company_email">البريد الرسمي للشركة *</label>
                            <div class="fw">
                                <input id="company_email" name="company_email" type="email" maxlength="150" required placeholder="info@company.com" value="<?php echo isset($_POST['company_email']) ? e($_POST['company_email']) : ''; ?>">
                                <i class="fas fa-envelope ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="phone">هاتف الشركة *</label>
                            <div class="fw">
                                <input id="phone" name="phone" maxlength="30" required placeholder="+966 50 xxx xxxx" value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>">
                                <i class="fas fa-phone ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label for="postal_address">العنوان البريدي</label>
                        <div class="fw">
                            <textarea id="postal_address" name="postal_address" maxlength="400" placeholder="العنوان التفصيلي للشركة..."><?php echo isset($_POST['postal_address']) ? e($_POST['postal_address']) : ''; ?></textarea>
                            <i class="fas fa-map ico" style="top:14px;"></i>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="tax_number">الرقم الضريبي</label>
                            <div class="fw">
                                <input id="tax_number" name="tax_number" maxlength="100" placeholder="3001234567890" value="<?php echo isset($_POST['tax_number']) ? e($_POST['tax_number']) : ''; ?>">
                                <i class="fas fa-receipt ico"></i>
                            </div>
                        </div>
                        <div class="field"></div>
                    </div>

                    <div class="sec-head" style="margin-top:8px;">
                        <div class="sec-ico"><i class="fas fa-user-tie"></i></div>
                        <h3>المدير العام</h3>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="manager_name">اسم المدير العام *</label>
                            <div class="fw">
                                <input id="manager_name" name="manager_name" maxlength="150" required placeholder="محمد أحمد" value="<?php echo isset($_POST['manager_name']) ? e($_POST['manager_name']) : ''; ?>">
                                <i class="fas fa-user-tie ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="manager_email">بريد المدير العام *</label>
                            <div class="fw">
                                <input id="manager_email" name="manager_email" type="email" maxlength="150" required placeholder="manager@company.com" value="<?php echo isset($_POST['manager_email']) ? e($_POST['manager_email']) : ''; ?>">
                                <i class="fas fa-envelope ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="manager_phone">هاتف المدير العام *</label>
                            <div class="fw">
                                <input id="manager_phone" name="manager_phone" maxlength="30" required placeholder="+966 55 xxx xxxx" value="<?php echo isset($_POST['manager_phone']) ? e($_POST['manager_phone']) : ''; ?>">
                                <i class="fas fa-mobile-alt ico"></i>
                            </div>
                        </div>
                        <div class="field"></div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="manager_password">كلمة مرور المدير العام *</label>
                            <div class="fw">
                                <input id="manager_password" name="manager_password" type="password" maxlength="100" placeholder="8 أحرف على الأقل" value="">
                                <i class="fas fa-lock ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="manager_password_confirm">تأكيد كلمة المرور *</label>
                            <div class="fw">
                                <input id="manager_password_confirm" name="manager_password_confirm" type="password" maxlength="100" placeholder="أعد كتابة كلمة المرور" value="">
                                <i class="fas fa-shield-alt ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="note-box" style="margin-top:4px;">
                        <i class="fas fa-key"></i>
                        <p>كلمة المرور مطلوبة فقط عند اختيار الباقة <strong>المجانية</strong> لأن الحساب يُفعّل فوراً. في الباقات المدفوعة سيظل الطلب قيد المراجعة.</p>
                    </div>
                    <div class="step-actions">
                        <button class="btn-secondary" type="button" onclick="prevStep()"><i class="fas fa-arrow-right"></i> السابق</button>
                        <button class="btn-primary" type="button" onclick="nextStep()"><span>التالي</span><i class="fas fa-arrow-left"></i></button>
                    </div>
                </div>

                <!-- ══ STEP 3: Plan & Settings ══ -->
                <div class="step" data-step="3" style="display:none;">
                    <?php $selectedPlan = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : $freemiumPlanId; ?>
                    <div class="sec-head">
                        <div class="sec-ico"><i class="fas fa-crown"></i></div>
                        <h3>اختر الباقة المناسبة</h3>
                    </div>
                    <input type="hidden" id="plan_id" name="plan_id" value="<?php echo $selectedPlan; ?>">
                    <div class="plans-grid">
                        <?php foreach ($plans as $p):
                            $pid = intval($p['id']);
                            $features = array();
                            if (isset($p['features']) && trim($p['features']) !== '') {
                                $features = preg_split('/\r\n|\r|\n/', trim($p['features']));
                            }
                            $recommended = !empty($p['is_recommended']) || $pid === 3;
                            $isSelected  = $selectedPlan === $pid;
                            $price       = floatval(isset($p['price']) ? $p['price'] : 0);
                        ?>
                        <div class="plan-card <?php echo $recommended ? 'recommended' : ''; ?> <?php echo $isSelected ? 'selected' : ''; ?>"
                             data-plan-id="<?php echo $pid; ?>"
                             data-max-users="<?php echo intval(isset($p['max_users']) ? $p['max_users'] : 0); ?>"
                             data-max-projects="<?php echo intval(isset($p['max_projects']) ? $p['max_projects'] : 0); ?>"
                             data-max-equipments="<?php echo intval(isset($p['max_equipments']) ? $p['max_equipments'] : 0); ?>"
                             data-price="<?php echo floatval(isset($p['price']) ? $p['price'] : 0); ?>"
                             onclick="selectPlan(this)">
                            <div class="plan-hd">
                                <div class="plan-title"><?php echo e($p['plan_name']); ?></div>
                                <span class="plan-price-badge <?php echo $price == 0 ? 'free' : ''; ?>">
                                    <?php echo $price > 0 ? number_format($price, 0) . ' $' : 'مجاني'; ?>
                                </span>
                            </div>
                            <div class="plan-tagline"><?php echo e(isset($p['tagline']) && $p['tagline'] !== '' ? $p['tagline'] : 'باقة لتشغيل أعمالك بكفاءة.'); ?></div>
                            <div class="plan-limits">
                                <span class="plan-limit"><i class="fas fa-users"></i> <?php echo intval(isset($p['max_users']) ? $p['max_users'] : 0); ?> مستخدم</span>
                                <span class="plan-limit"><i class="fas fa-project-diagram"></i> <?php echo intval(isset($p['max_projects']) ? $p['max_projects'] : 0); ?> مشروع</span>
                                <span class="plan-limit"><i class="fas fa-truck"></i> <?php echo intval(isset($p['max_equipments']) ? $p['max_equipments'] : 0); ?> معدة</span>
                            </div>
                            <ul class="plan-features">
                                <?php foreach ($features as $feature): if (trim($feature) === '') continue; ?>
                                <li><?php echo e(trim($feature)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="plan-sel">
                                <span>اختر هذه الباقة</span>
                                <span class="plan-dot"></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <select class="plan-select-fallback" id="plan_id_fallback">
                        <?php foreach ($plans as $p): ?>
                        <option value="<?php echo intval($p['id']); ?>" <?php echo $selectedPlan === intval($p['id']) ? 'selected' : ''; ?>><?php echo e($p['plan_name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="sec-head" style="margin-top:18px;">
                        <div class="sec-ico"><i class="fas fa-cog"></i></div>
                        <h3>إعدادات إضافية</h3>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="currency">العملة</label>
                            <div class="fw">
                                <select id="currency" name="currency">
                                    <option value="SAR" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'SAR') ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                                    <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'USD') ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                                    <option value="EGP" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'EGP') ? 'selected' : ''; ?>>جنيه مصري (EGP)</option>
                                    <option value="SDG" <?php echo (isset($_POST['currency']) && $_POST['currency'] === 'SDG') ? 'selected' : ''; ?>>جنيه سوداني (SDG)</option>
                                </select>
                                <i class="fas fa-coins ico"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="timezone">المنطقة الزمنية</label>
                            <div class="fw">
                                <input id="timezone" name="timezone" maxlength="64" value="<?php echo isset($_POST['timezone']) ? e($_POST['timezone']) : 'Asia/Riyadh'; ?>">
                                <i class="fas fa-clock ico"></i>
                            </div>
                        </div>
                    </div>
                    <div class="grid2">
                        <div class="field">
                            <label for="modules_enabled">الوحدات المطلوبة</label>
                            <div class="fw">
                                <input id="modules_enabled" name="modules_enabled" maxlength="255" placeholder="projects,timesheet,reports" value="<?php echo isset($_POST['modules_enabled']) ? e($_POST['modules_enabled']) : ''; ?>">
                                <i class="fas fa-puzzle-piece ico"></i>
                            </div>
                        </div>
                        <div class="field"></div>
                    </div>

                    <div class="note-box">
                        <i class="fas fa-shield-alt"></i>
                        <p><strong>Freemium:</strong> عند اختيار الباقة المجانية (1 مشروع، 1 معدة، 3 مستخدمين) يتم التفعيل فوراً والدخول مباشرة. <strong>الباقات المدفوعة:</strong> تُرسل كطلب مراجعة لفريق الإدارة.</p>
                    </div>

                    <!-- Hidden limits fields -->
                    <input type="hidden" id="freemium_plan_id" value="<?php echo intval($freemiumPlanId); ?>">
                    <input type="hidden" name="max_users" id="max_users" value="<?php echo isset($_POST['max_users']) ? intval($_POST['max_users']) : 0; ?>">
                    <input type="hidden" name="max_equipments" id="max_equipments" value="<?php echo isset($_POST['max_equipments']) ? intval($_POST['max_equipments']) : 0; ?>">
                    <input type="hidden" name="max_projects" id="max_projects" value="<?php echo isset($_POST['max_projects']) ? intval($_POST['max_projects']) : 0; ?>">

                    <div class="step-actions">
                        <button class="btn-secondary" type="button" onclick="prevStep()"><i class="fas fa-arrow-right"></i> السابق</button>
                        <button class="btn-primary" type="submit"><i class="fas fa-paper-plane"></i><span>إنشاء الحساب / إرسال الطلب</span></button>
                    </div>
                </div>
            </form>

        </div>
    </div>

</div>

<script>
var currentStep = 1;
var totalSteps  = 3;

function updateNav(step) {
    for (var i = 1; i <= totalSteps; i++) {
        var item = document.getElementById('sn-' + i);
        var line = document.getElementById('sl-' + i);
        if (!item) continue;
        item.classList.remove('active', 'done');
        if (i < step)       item.classList.add('done');
        else if (i === step) item.classList.add('active');
        if (line) {
            line.classList.toggle('done', i < step);
        }
    }
}

function showStep(step) {
    for (var i = 1; i <= totalSteps; i++) {
        var el = document.querySelector('.step[data-step="' + i + '"]');
        if (el) el.style.display = i === step ? 'block' : 'none';
    }
    currentStep = step;
    updateNav(step);
    // scroll card into view on mobile
    var card = document.querySelector('.card');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function validateStep(step) {
    var stepEl = document.querySelector('.step[data-step="' + step + '"]');
    if (!stepEl) return true;
    var req = stepEl.querySelectorAll('input[required], select[required], textarea[required]');
    for (var i = 0; i < req.length; i++) {
        if (!req[i].value.trim()) {
            req[i].focus();
            req[i].style.borderColor = '#dc2626';
            req[i].addEventListener('input', function(){ this.style.borderColor = ''; }, { once: true });
            return false;
        }
    }
    return true;
}

function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < totalSteps) showStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function setPlanLimitsFromCard(card) {
    if (!card) return;

    var maxUsers = card.getAttribute('data-max-users') || '0';
    var maxProjects = card.getAttribute('data-max-projects') || '0';
    var maxEquipments = card.getAttribute('data-max-equipments') || '0';

    var maxUsersInput = document.getElementById('max_users');
    var maxProjectsInput = document.getElementById('max_projects');
    var maxEquipmentsInput = document.getElementById('max_equipments');

    if (maxUsersInput) maxUsersInput.value = maxUsers;
    if (maxProjectsInput) maxProjectsInput.value = maxProjects;
    if (maxEquipmentsInput) maxEquipmentsInput.value = maxEquipments;
}

function syncManagerPasswordRequirement() {
    var freemiumPlanId = document.getElementById('freemium_plan_id');
    var selectedPlanId = document.getElementById('plan_id');
    var passwordInput = document.getElementById('manager_password');
    var passwordConfirmInput = document.getElementById('manager_password_confirm');

    if (!freemiumPlanId || !selectedPlanId || !passwordInput || !passwordConfirmInput) {
        return;
    }

    var isFreemium = String(selectedPlanId.value) === String(freemiumPlanId.value);
    if (isFreemium) {
        passwordInput.required = true;
        passwordConfirmInput.required = true;
    } else {
        passwordInput.required = false;
        passwordConfirmInput.required = false;
    }
}

function selectPlan(card) {
    document.querySelectorAll('.plan-card').forEach(function(c){ c.classList.remove('selected'); });
    card.classList.add('selected');
    document.getElementById('plan_id').value = card.getAttribute('data-plan-id') || '0';
    setPlanLimitsFromCard(card);
    syncManagerPasswordRequirement();
}

// Init
showStep(1);
var selectedCard = document.querySelector('.plan-card.selected') || document.querySelector('.plan-card');
if (selectedCard) {
    selectPlan(selectedCard);
} else {
    syncManagerPasswordRequirement();
}
</script>
</body>
</html>
