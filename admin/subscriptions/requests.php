<?php
require_once dirname(__DIR__) . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'طلبات الاشتراك';
$current_page = 'subscriptions';

function req_table_has_column($tableName, $columnName) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
    $sql = "SHOW COLUMNS FROM " . $safeTable . " LIKE '" . mysqli_real_escape_string($GLOBALS['conn'], $safeCol) . "'";
    $res = @mysqli_query($GLOBALS['conn'], $sql);

    return $res && mysqli_num_rows($res) > 0;
}

function req_make_temp_password($length) {
    $length = max(10, intval($length));
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $out;
}

function req_get_request_details($req) {
    $details = array();

    $addDetail = function ($label, $value) use (&$details) {
        if ($value === null) {
            return;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return;
        }
        $details[] = array('label' => $label, 'value' => $text);
    };

    $statusMap = array(
        'pending' => 'معلق',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض'
    );

    $addDetail('رقم الطلب', isset($req['id']) ? $req['id'] : '');
    $addDetail('اسم الشركة', isset($req['company_name']) ? $req['company_name'] : '');
    $addDetail('البريد الرسمي', isset($req['email']) ? $req['email'] : '');
    $addDetail('هاتف الشركة', isset($req['phone']) ? $req['phone'] : '');
    $addDetail('الخطة المطلوبة', isset($req['plan_name']) ? $req['plan_name'] : '');
    $addDetail('حالة الطلب', isset($statusMap[$req['status']]) ? $statusMap[$req['status']] : (isset($req['status']) ? $req['status'] : ''));

    if (!empty($req['created_at']) && strtotime($req['created_at']) !== false) {
        $addDetail('تاريخ الطلب', date('d/m/Y H:i', strtotime($req['created_at'])));
    }

    $messageRaw = isset($req['message']) ? trim((string)$req['message']) : '';
    $decodedPayload = array();
    if ($messageRaw !== '' && substr($messageRaw, 0, 1) === '{') {
        $decoded = json_decode($messageRaw, true);
        if (is_array($decoded)) {
            $decodedPayload = $decoded;
        }
    }

    if (!empty($decodedPayload)) {
        $payloadLabels = array(
            'manager_name' => 'اسم المدير العام',
            'manager_email' => 'بريد المدير العام',
            'manager_phone' => 'هاتف المدير العام',
            'company_name_en' => 'اسم الشركة (EN)',
            'commercial_registration' => 'السجل التجاري',
            'sector' => 'القطاع',
            'country' => 'الدولة',
            'city' => 'المدينة',
            'tax_number' => 'الرقم الضريبي',
            'postal_address' => 'العنوان البريدي',
            'modules_enabled' => 'الموديولات المطلوبة',
            'currency' => 'العملة',
            'timezone' => 'المنطقة الزمنية',
            'max_users' => 'الحد الأقصى للمستخدمين',
            'max_equipments' => 'الحد الأقصى للمعدات',
            'max_projects' => 'الحد الأقصى للمشاريع',
            'source' => 'مصدر الطلب'
        );

        foreach ($decodedPayload as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $label = isset($payloadLabels[$key]) ? $payloadLabels[$key] : $key;
            $addDetail($label, $value);
        }
    } elseif ($messageRaw !== '') {
        $addDetail('الرسالة', $messageRaw);
    }

    if (!empty($req['review_note'])) {
        $addDetail('ملاحظة المراجعة', $req['review_note']);
    }
    if (!empty($req['reviewer_name'])) {
        $addDetail('المراجع', $req['reviewer_name']);
    }
    if (!empty($req['reviewed_at']) && strtotime($req['reviewed_at']) !== false) {
        $addDetail('تاريخ المراجعة', date('d/m/Y H:i', strtotime($req['reviewed_at'])));
    }

    return $details;
}

// ── Handle approve / reject (AJAX or POST) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        die(json_encode(['success' => false, 'message' => 'رمز الحماية غير صحيح']));
    }
    $req_id = intval(isset($_POST['request_id']) ? $_POST['request_id'] : 0);
    $action = $_POST['action'];

    if ($req_id > 0 && in_array($action, ['approve', 'reject'])) {
        $new_status  = $action === 'approve' ? 'approved' : 'rejected';
        $reviewed_by = intval($admin['id']);
        $esc_status  = mysqli_real_escape_string($conn, $new_status);
        $noteRaw     = trim(isset($_POST['note']) ? $_POST['note'] : '');
        $note        = mysqli_real_escape_string($conn, $noteRaw);
        $approvalMessage = '';

        if ($action === 'approve') {
            mysqli_query($conn, 'START TRANSACTION');

            $reqSql = "SELECT * FROM admin_subscription_requests WHERE id=$req_id AND status='pending' LIMIT 1";
            $reqRes = @mysqli_query($conn, $reqSql);
            $reqRow = $reqRes ? mysqli_fetch_assoc($reqRes) : null;

            if (!$reqRow) {
                mysqli_query($conn, 'ROLLBACK');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'الطلب غير موجود أو تمت مراجعته مسبقاً']);
                    exit;
                }
                header('Location: ' . super_admin_url('subscriptions/requests'));
                exit;
            }

            $payload = array();
            $messageRaw = isset($reqRow['message']) ? trim((string)$reqRow['message']) : '';
            if ($messageRaw !== '' && substr($messageRaw, 0, 1) === '{') {
                $decoded = json_decode($messageRaw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $companyName = isset($reqRow['company_name']) ? trim((string)$reqRow['company_name']) : '';
            $companyEmail = isset($reqRow['email']) ? trim((string)$reqRow['email']) : '';
            $companyPhone = isset($reqRow['phone']) ? trim((string)$reqRow['phone']) : '';
            $planId = intval(isset($reqRow['plan_id']) ? $reqRow['plan_id'] : 0);

            $managerName = trim(isset($payload['manager_name']) ? $payload['manager_name'] : '');
            $managerEmail = trim(isset($payload['manager_email']) ? $payload['manager_email'] : '');
            $managerPhone = trim(isset($payload['manager_phone']) ? $payload['manager_phone'] : $companyPhone);
            $companyNameEn = trim(isset($payload['company_name_en']) ? $payload['company_name_en'] : '');
            $commercialRegistration = trim(isset($payload['commercial_registration']) ? $payload['commercial_registration'] : '');
            $sector = trim(isset($payload['sector']) ? $payload['sector'] : '');
            $country = trim(isset($payload['country']) ? $payload['country'] : '');
            $city = trim(isset($payload['city']) ? $payload['city'] : '');
            $taxNumber = trim(isset($payload['tax_number']) ? $payload['tax_number'] : '');
            $postalAddress = trim(isset($payload['postal_address']) ? $payload['postal_address'] : '');
            $modulesEnabled = trim(isset($payload['modules_enabled']) ? $payload['modules_enabled'] : '');
            $currency = trim(isset($payload['currency']) ? $payload['currency'] : 'SAR');
            $timezone = trim(isset($payload['timezone']) ? $payload['timezone'] : 'Asia/Riyadh');
            $maxUsers = intval(isset($payload['max_users']) ? $payload['max_users'] : 0);
            $maxEquipments = intval(isset($payload['max_equipments']) ? $payload['max_equipments'] : 0);
            $maxProjects = intval(isset($payload['max_projects']) ? $payload['max_projects'] : 0);

            if ($managerName === '') {
                $managerName = $companyName . ' - المدير العام';
            }
            if ($managerEmail === '') {
                $managerEmail = $companyEmail;
            }

            if (!validate_email($companyEmail) || !validate_email($managerEmail)) {
                mysqli_query($conn, 'ROLLBACK');
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'بيانات البريد الإلكتروني غير صالحة في الطلب']);
                    exit;
                }
                header('Location: ' . super_admin_url('subscriptions/requests'));
                exit;
            }

            $companyId = intval(isset($reqRow['company_id']) ? $reqRow['company_id'] : 0);
            $companyCreatedNow = false;

            if ($companyId > 0) {
                $safePlan = $planId > 0 ? strval($planId) : 'NULL';
                @mysqli_query($conn, "UPDATE admin_companies SET status='active', plan_id=$safePlan WHERE id=$companyId");
            } else {
                $companyEsc = mysqli_real_escape_string($conn, $companyName);
                $companyEmailEsc = mysqli_real_escape_string($conn, $companyEmail);
                $companyPhoneEsc = mysqli_real_escape_string($conn, $companyPhone);
                $companyNameEnEsc = mysqli_real_escape_string($conn, $companyNameEn);
                $commercialRegistrationEsc = mysqli_real_escape_string($conn, $commercialRegistration);
                $sectorEsc = mysqli_real_escape_string($conn, $sector);
                $countryEsc = mysqli_real_escape_string($conn, $country);
                $cityEsc = mysqli_real_escape_string($conn, $city);
                $taxNumberEsc = mysqli_real_escape_string($conn, $taxNumber);
                $postalAddressEsc = mysqli_real_escape_string($conn, $postalAddress);
                $modulesEnabledEsc = mysqli_real_escape_string($conn, $modulesEnabled);
                $currencyEsc = mysqli_real_escape_string($conn, $currency);
                $timezoneEsc = mysqli_real_escape_string($conn, $timezone);

                $cols = array();
                $vals = array();

                if (req_table_has_column('admin_companies', 'name')) {
                    $cols[] = 'name';
                    $vals[] = "'" . $companyEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'company_name')) {
                    $cols[] = 'company_name';
                    $vals[] = "'" . $companyEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'company_name_ar')) {
                    $cols[] = 'company_name_ar';
                    $vals[] = "'" . $companyEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'company_name_en')) {
                    $cols[] = 'company_name_en';
                    $vals[] = "'" . $companyNameEnEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'commercial_registration')) {
                    $cols[] = 'commercial_registration';
                    $vals[] = "'" . $commercialRegistrationEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'sector')) {
                    $cols[] = 'sector';
                    $vals[] = "'" . $sectorEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'country')) {
                    $cols[] = 'country';
                    $vals[] = "'" . $countryEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'city')) {
                    $cols[] = 'city';
                    $vals[] = "'" . $cityEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'tax_number')) {
                    $cols[] = 'tax_number';
                    $vals[] = "'" . $taxNumberEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'postal_address')) {
                    $cols[] = 'postal_address';
                    $vals[] = "'" . $postalAddressEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'modules_enabled')) {
                    $cols[] = 'modules_enabled';
                    $vals[] = "'" . $modulesEnabledEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'currency')) {
                    $cols[] = 'currency';
                    $vals[] = "'" . $currencyEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'timezone')) {
                    $cols[] = 'timezone';
                    $vals[] = "'" . $timezoneEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'max_users')) {
                    $cols[] = 'max_users';
                    $vals[] = strval(max(0, $maxUsers));
                }
                if (req_table_has_column('admin_companies', 'max_equipments')) {
                    $cols[] = 'max_equipments';
                    $vals[] = strval(max(0, $maxEquipments));
                }
                if (req_table_has_column('admin_companies', 'max_projects')) {
                    $cols[] = 'max_projects';
                    $vals[] = strval(max(0, $maxProjects));
                }

                $cols[] = 'email';
                $vals[] = "'" . $companyEmailEsc . "'";

                if (req_table_has_column('admin_companies', 'phone')) {
                    $cols[] = 'phone';
                    $vals[] = "'" . $companyPhoneEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'address')) {
                    $cols[] = 'address';
                    $vals[] = "'" . $postalAddressEsc . "'";
                }
                if (req_table_has_column('admin_companies', 'plan_id')) {
                    $cols[] = 'plan_id';
                    $vals[] = $planId > 0 ? strval($planId) : 'NULL';
                }
                if (req_table_has_column('admin_companies', 'status')) {
                    $cols[] = 'status';
                    $vals[] = "'active'";
                }
                if (req_table_has_column('admin_companies', 'users_count')) {
                    $cols[] = 'users_count';
                    $vals[] = '1';
                }

                $insertCompanySql = 'INSERT INTO admin_companies (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
                $insCompany = @mysqli_query($conn, $insertCompanySql);
                if (!$insCompany) {
                    mysqli_query($conn, 'ROLLBACK');
                    $sqlErr = mysqli_error($conn);
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'message' => 'فشل إنشاء الشركة: ' . $sqlErr]);
                        exit;
                    }
                    header('Location: ' . super_admin_url('subscriptions/requests'));
                    exit;
                }

                $companyId = intval(mysqli_insert_id($conn));
                $companyCreatedNow = true;
            }

            if ($companyId > 0) {
                $userDupStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
                if ($userDupStmt) {
                    mysqli_stmt_bind_param($userDupStmt, 's', $managerEmail);
                    mysqli_stmt_execute($userDupStmt);
                    $dupRes = mysqli_stmt_get_result($userDupStmt);
                    $dupRow = $dupRes ? mysqli_fetch_assoc($dupRes) : null;
                    mysqli_stmt_close($userDupStmt);

                    if (!$dupRow) {
                        $tempPassword = req_make_temp_password(12);
                        $tempPasswordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

                        $managerNameEsc = mysqli_real_escape_string($conn, $managerName);
                        $managerEmailEsc = mysqli_real_escape_string($conn, $managerEmail);
                        $managerPhoneEsc = mysqli_real_escape_string($conn, $managerPhone);
                        $passEsc = mysqli_real_escape_string($conn, $tempPasswordHash);

                        $uCols = array('name', 'username', 'password', 'phone', 'role');
                        $uVals = array("'" . $managerNameEsc . "'", "'" . $managerEmailEsc . "'", "'" . $passEsc . "'", "'" . $managerPhoneEsc . "'", "'1'");

                        if (req_table_has_column('users', 'email')) {
                            $uCols[] = 'email';
                            $uVals[] = "'" . $managerEmailEsc . "'";
                        }
                        if (req_table_has_column('users', 'role_id')) {
                            $uCols[] = 'role_id';
                            $uVals[] = '1';
                        }
                        if (req_table_has_column('users', 'company_id')) {
                            $uCols[] = 'company_id';
                            $uVals[] = strval($companyId);
                        }
                        if (req_table_has_column('users', 'status')) {
                            $uCols[] = 'status';
                            $uVals[] = "'active'";
                        }
                        if (req_table_has_column('users', 'force_password_change')) {
                            $uCols[] = 'force_password_change';
                            $uVals[] = '1';
                        }
                        if (req_table_has_column('users', 'temp_password_set_at')) {
                            $uCols[] = 'temp_password_set_at';
                            $uVals[] = 'NOW()';
                        }

                        $insertUserSql = 'INSERT INTO users (' . implode(', ', $uCols) . ') VALUES (' . implode(', ', $uVals) . ')';
                        $insUser = @mysqli_query($conn, $insertUserSql);
                        if (!$insUser) {
                            mysqli_query($conn, 'ROLLBACK');
                            $sqlErr = mysqli_error($conn);
                            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['success' => false, 'message' => 'فشل إنشاء المدير العام: ' . $sqlErr]);
                                exit;
                            }
                            header('Location: ' . super_admin_url('subscriptions/requests'));
                            exit;
                        }

                        $approvalMessage = 'تم إنشاء حساب المدير العام. كلمة المرور المؤقتة: ' . $tempPassword;
                    }
                }
            }

            $finalNote = $noteRaw;
            if ($approvalMessage !== '') {
                $finalNote = trim($noteRaw . "\n" . $approvalMessage);
            }
            $finalNoteEsc = mysqli_real_escape_string($conn, $finalNote);

            $upd = @mysqli_query($conn,
                "UPDATE admin_subscription_requests
                 SET status='$esc_status', reviewed_by=$reviewed_by, reviewed_at=NOW(), review_note='$finalNoteEsc', company_id=" . intval($companyId) . "
                 WHERE id=$req_id AND status='pending'"
            );

            if ($upd && mysqli_affected_rows($conn) > 0) {
                mysqli_query($conn, 'COMMIT');
                if ($companyCreatedNow) {
                    super_admin_write_audit($reviewed_by, 'approve', 'طلب اشتراك', 'قبول الطلب وإنشاء الشركة والمدير العام', $req_id);
                } else {
                    super_admin_write_audit($reviewed_by, 'approve', 'طلب اشتراك', 'قبول الطلب وتفعيل الشركة', $req_id);
                }
            } else {
                mysqli_query($conn, 'ROLLBACK');
            }
        } else {
            $upd = @mysqli_query($conn,
                "UPDATE admin_subscription_requests
                 SET status='$esc_status', reviewed_by=$reviewed_by, reviewed_at=NOW(), review_note='$note'
                 WHERE id=$req_id AND status='pending'"
            );
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            $msg = $upd ? 'تم التحديث بنجاح' : mysqli_error($conn);
            if ($upd && $approvalMessage !== '') {
                $msg .= ' - ' . $approvalMessage;
            }
            echo json_encode(['success' => !!$upd, 'message' => $msg]);
            exit;
        }
    }
    header('Location: ' . super_admin_url('subscriptions/requests'));
    exit;
}

// ── Tab filter ────────────────────────────────────────────────────────────
$tabValue = isset($_GET['tab']) ? $_GET['tab'] : '';
$tab = in_array($tabValue, array('approved', 'rejected'), true) ? $tabValue : 'pending';

// ── Count by status ───────────────────────────────────────────────────────
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$cq = @mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM admin_subscription_requests GROUP BY status");
if ($cq) { while ($row = mysqli_fetch_assoc($cq)) $counts[$row['status']] = intval($row['c']); }

// ── Fetch requests ────────────────────────────────────────────────────────
$requests = [];
$esc_tab  = mysqli_real_escape_string($conn, $tab);
$rq = @mysqli_query($conn,
    "SELECT r.*, p.plan_name, sa.name AS reviewer_name
     FROM admin_subscription_requests r
     LEFT JOIN admin_subscription_plans p ON r.plan_id = p.id
     LEFT JOIN super_admins sa ON r.reviewed_by = sa.id
     WHERE r.status = '$esc_tab'
     ORDER BY r.created_at DESC
     LIMIT 100"
);
if ($rq) { while ($row = mysqli_fetch_assoc($rq)) $requests[] = $row; }
$table_missing = ($rq === false);

$csrf = generate_csrf_token();
require_once dirname(__DIR__) . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>طلبات الاشتراك</h2>
        <p class="sub">مراجعة طلبات الشركات الجديدة — قبول أو رفض</p>
    </div>
</div>

<!-- ── Tabs ──────────────────────────────────────────────────────────────── -->
<div class="tabs">
    <a href="?tab=pending"  class="tab-btn <?php echo $tab === 'pending'  ? 'active' : ''; ?>">
        معلق
        <?php if ($counts['pending'] > 0): ?>
        <span style="background:#d97706;color:#fff;border-radius:999px;padding:1px 7px;font-size:0.7rem;margin-right:4px;">
            <?php echo $counts['pending']; ?>
        </span>
        <?php endif; ?>
    </a>
    <a href="?tab=approved" class="tab-btn <?php echo $tab === 'approved' ? 'active' : ''; ?>">
        مقبول
        <span style="background:rgba(5,150,105,.12);color:#059669;border-radius:999px;padding:1px 7px;font-size:0.7rem;margin-right:4px;">
            <?php echo $counts['approved']; ?>
        </span>
    </a>
    <a href="?tab=rejected" class="tab-btn <?php echo $tab === 'rejected' ? 'active' : ''; ?>">
        مرفوض
        <span style="background:rgba(100,116,139,.12);color:#64748b;border-radius:999px;padding:1px 7px;font-size:0.7rem;margin-right:4px;">
            <?php echo $counts['rejected']; ?>
        </span>
    </a>
</div>

<!-- ── Table ─────────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if ($table_missing): ?>
    <div class="coming-banner" style="margin:20px;">
        <i class="fas fa-database"></i>
        <h3>جداول البيانات غير موجودة بعد</h3>
        <p>قم بتنفيذ <code>database/admin_saas_tables.sql</code> لإنشاء جداول النظام.</p>
    </div>
    <?php elseif (empty($requests)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>لا توجد طلبات <?php echo $tab === 'pending' ? 'معلقة' : ($tab === 'approved' ? 'مقبولة' : 'مرفوضة'); ?></p>
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>الشركة</th>
                    <th>البريد الإلكتروني</th>
                    <th>الخطة المطلوبة</th>
                    <th>تاريخ الطلب</th>
                    <th>تفاصيل الطلب</th>
                    <?php if ($tab !== 'pending'): ?>
                    <th>ملاحظة المراجعة</th>
                    <th>تاريخ المراجعة</th>
                    <?php endif; ?>
                    <?php if ($tab === 'pending'): ?><th>الإجراءات</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td style="font-weight:700;"><?php echo e(isset($req['company_name']) ? $req['company_name'] : '—'); ?></td>
                    <td><?php echo e(isset($req['email']) ? $req['email'] : '—'); ?></td>
                    <td>
                        <?php if ($req['plan_name']): ?>
                            <span class="badge bg-blue"><?php echo e($req['plan_name']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-muted"><?php echo e(date('d/m/Y H:i', strtotime($req['created_at']))); ?></td>
                    <td>
                        <?php $requestDetails = req_get_request_details($req); ?>
                        <button
                            class="btn btn-ghost btn-sm"
                            onclick="showRequestDetails(this)"
                            data-company="<?php echo e(isset($req['company_name']) ? $req['company_name'] : ''); ?>"
                            data-details="<?php echo e(json_encode($requestDetails, JSON_UNESCAPED_UNICODE)); ?>"
                        >
                            <i class="fas fa-eye"></i> عرض كامل
                        </button>
                    </td>
                    <?php if ($tab !== 'pending'): ?>
                    <td class="text-muted"><?php echo e(isset($req['review_note']) ? $req['review_note'] : '—'); ?></td>
                    <td class="text-muted"><?php echo $req['reviewed_at'] ? e(date('d/m/Y', strtotime($req['reviewed_at']))) : '—'; ?></td>
                    <?php endif; ?>
                    <?php if ($tab === 'pending'): ?>
                    <td>
                        <div class="flex">
                            <button class="btn btn-success btn-sm" onclick="reviewRequest(<?php echo intval($req['id']); ?>, 'approve', '<?php echo e($req['company_name']); ?>')">
                                <i class="fas fa-check"></i> قبول
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="reviewRequest(<?php echo intval($req['id']); ?>, 'reject', '<?php echo e($req['company_name']); ?>')">
                                <i class="fas fa-times"></i> رفض
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Review Modal ────────────────────────────────────────────────────── -->
<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 id="reviewModalTitle" style="font-size:1rem;font-weight:800;"></h3>
            <button onclick="closeReviewModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:24px;">
            <div id="reviewModalBody" class="alert alert-info" style="margin-bottom:16px;"></div>
            <div class="form-group">
                <label class="form-label">ملاحظة (اختياري)</label>
                <textarea id="reviewNote" class="form-ctrl" placeholder="أضف ملاحظة للشركة..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeReviewModal()" class="btn btn-ghost">إلغاء</button>
                <button id="reviewConfirmBtn" type="button" class="btn btn-primary" onclick="confirmReview()">
                    تأكيد
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Details Modal ───────────────────────────────────────────────────── -->
<div id="detailsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:510;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:760px;max-height:86vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
            <h3 id="detailsModalTitle" style="font-size:1rem;font-weight:800;margin:0;"></h3>
            <button onclick="closeDetailsModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="detailsModalBody" style="padding:20px 24px;"></div>
        <div style="padding:0 24px 24px;text-align:left;">
            <button type="button" onclick="closeDetailsModal()" class="btn btn-primary">إغلاق</button>
        </div>
    </div>
</div>

<script>
var _reviewId = 0, _reviewAction = '';
var _csrf = '<?php echo e($csrf); ?>';

function reviewRequest(id, action, name) {
    _reviewId     = id;
    _reviewAction = action;
    var modal = document.getElementById('reviewModal');
    document.getElementById('reviewModalTitle').textContent = action === 'approve' ? 'قبول الطلب' : 'رفض الطلب';
    var body = document.getElementById('reviewModalBody');
    body.className = 'alert ' + (action === 'approve' ? 'alert-success' : 'alert-danger');
    body.innerHTML = '<i class="fas fa-' + (action === 'approve' ? 'check-circle' : 'times-circle') + '"></i>' +
                     '<span>سيتم <strong>' + (action === 'approve' ? 'قبول' : 'رفض') + '</strong> طلب الشركة: ' + name + '</span>';
    var btn = document.getElementById('reviewConfirmBtn');
    btn.className = 'btn ' + (action === 'approve' ? 'btn-success' : 'btn-danger');
    btn.textContent = action === 'approve' ? 'قبول الطلب' : 'رفض الطلب';
    modal.style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
    document.getElementById('reviewNote').value = '';
}

function showRequestDetails(button) {
    var company = button.getAttribute('data-company') || 'الطلب';
    var encodedDetails = button.getAttribute('data-details') || '[]';
    var details = [];

    try {
        details = JSON.parse(encodedDetails);
    } catch (e) {
        details = [];
    }

    var html = '';
    if (!Array.isArray(details) || details.length === 0) {
        html = '<p class="text-muted" style="margin:0;">لا توجد تفاصيل إضافية.</p>';
    } else {
        html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">';
        details.forEach(function (item) {
            var label = item && item.label ? String(item.label) : '';
            var value = item && item.value ? String(item.value) : '';

            html += '<div style="border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:#f8fafc;">' +
                    '<div style="font-size:0.8rem;color:#64748b;font-weight:700;margin-bottom:6px;">' + escapeHtml(label) + '</div>' +
                    '<div style="white-space:pre-wrap;word-break:break-word;font-weight:700;color:#0f172a;">' + escapeHtml(value) + '</div>' +
                    '</div>';
        });
        html += '</div>';
    }

    document.getElementById('detailsModalTitle').textContent = 'تفاصيل الطلب - ' + company;
    document.getElementById('detailsModalBody').innerHTML = html;
    document.getElementById('detailsModal').style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
    document.getElementById('detailsModalBody').innerHTML = '';
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function confirmReview() {
    var note = document.getElementById('reviewNote').value;
    var btn  = document.getElementById('reviewConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'جارٍ المعالجة...';

    var fd = new FormData();
    fd.append('action', _reviewAction);
    fd.append('request_id', _reviewId);
    fd.append('note', note);
    fd.append('csrf_token', _csrf);

    fetch(window.location.href, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeReviewModal();
            window.location.reload();
        } else {
            alert('خطأ: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'تأكيد';
        }
    })
    .catch(function(){ btn.disabled = false; btn.textContent = 'تأكيد'; });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/layout_foot.php'; ?>
