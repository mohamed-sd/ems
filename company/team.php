<?php
require_once __DIR__ . '/auth.php';
company_require_role('1');

$user = company_current_user();
$companyId = intval($user['company_id']);
$error = '';
$ok = '';

/**
 * جلب الأدوار من قاعدة البيانات مع الهرمية (مدير → مشرف)
 * يُعيد: [ 'parents' => [...], 'children' => [parentId => [...]], 'flat' => [...] ]
 */
function company_team_roles($conn) {
    $result = array('parents' => array(), 'children' => array(), 'flat' => array());

    // جرّب الاستعلام مباشرة — إذا فشل فالجدول غير موجود
    $testQ = @mysqli_query($conn, 'SELECT id, name, parent_role_id, level FROM roles ORDER BY level ASC, id ASC');

    if ($testQ) {
        // عمود parent_role_id وlevel موجودان
        while ($r = mysqli_fetch_assoc($testQ)) {
            $rid   = intval($r['id']);
            $pid   = ($r['parent_role_id'] !== null) ? intval($r['parent_role_id']) : 0;
            $level = intval($r['level']);
            $entry = array('id' => $rid, 'name' => $r['name'], 'parent_role_id' => $pid, 'level' => $level);
            $result['flat'][strval($rid)] = $entry;
            if ($pid === 0 || $level <= 1) {
                $result['parents'][] = $entry;
            } else {
                $result['children'][strval($pid)][] = $entry;
            }
        }
        return $result;
    }

    // احتواء: الجدول موجود لكن بدون عمود parent_role_id أو level
    $simpleQ = @mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY id ASC');
    if (!$simpleQ) { return $result; }

    while ($r = mysqli_fetch_assoc($simpleQ)) {
        $rid   = intval($r['id']);
        $entry = array('id' => $rid, 'name' => $r['name'], 'parent_role_id' => 0, 'level' => 1);
        $result['flat'][strval($rid)] = $entry;
        $result['parents'][] = $entry;
    }

    return $result;
}

$rolesData = company_team_roles($conn);
$roles     = $rolesData['flat'];   // id => entry  — للتحقق وإرجاع الاسم
$roleMap   = array();
foreach ($roles as $rid => $rEntry) {
    $roleMap[strval($rid)] = $rEntry['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $error = 'رمز الحماية غير صالح.';
    } else {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $roleId = intval(isset($_POST['role_id']) ? $_POST['role_id'] : 0);
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($roles)) {
            $error = 'تعذر تحميل الأدوار من قاعدة البيانات. تأكد من وجود جدول roles.';
        } elseif (!validate_length($name, 2, 150)) {
            $error = 'الاسم مطلوب.';
        } elseif (!validate_email($email) || !validate_length($email, 5, 150)) {
            $error = 'البريد الإلكتروني غير صالح.';
        } elseif (!validate_length($phone, 6, 30)) {
            $error = 'رقم الهاتف مطلوب.';
        } elseif (!in_array($status, array('active', 'inactive', 'suspended'), true)) {
            $error = 'حالة المستخدم غير صحيحة.';
        } elseif (!validate_length($password, 8, 255)) {
            $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
        } elseif ($roleId <= 0 || !isset($roleMap[strval($roleId)])) {
            $error = 'اختر دوراً صحيحاً من الأدوار المسجلة.';
        } else {
            $existsStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
            if (!$existsStmt) {
                $error = 'تعذر التحقق من البريد.';
            } else {
                mysqli_stmt_bind_param($existsStmt, 's', $email);
                mysqli_stmt_execute($existsStmt);
                $res = mysqli_stmt_get_result($existsStmt);
                $found = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($existsStmt);

                if ($found) {
                    $error = 'البريد الإلكتروني مستخدم بالفعل.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $username = $email;
                    $roleText = strval($roleId);

                    if (company_users_has_column('role_id')) {
                        $ins = mysqli_prepare($conn, 'INSERT INTO users (name, username, email, password, phone, role, role_id, company_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        if (!$ins) {
                            $error = 'تعذر إضافة المستخدم.';
                        } else {
                            mysqli_stmt_bind_param($ins, 'ssssssiis', $name, $username, $email, $hash, $phone, $roleText, $roleId, $companyId, $status);
                            $done = mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);
                        }
                    } else {
                        $ins = mysqli_prepare($conn, 'INSERT INTO users (name, username, email, password, phone, role, company_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                        if (!$ins) {
                            $error = 'تعذر إضافة المستخدم.';
                        } else {
                            mysqli_stmt_bind_param($ins, 'ssssssis', $name, $username, $email, $hash, $phone, $roleText, $companyId, $status);
                            $done = mysqli_stmt_execute($ins);
                            mysqli_stmt_close($ins);
                        }
                    }

                    if ($error === '') {
                        if (!isset($done) || !$done) {
                            $error = 'فشل إضافة المستخدم.';
                        } else {
                            if (company_table_exists('admin_companies')) {
                                @mysqli_query($conn, 'UPDATE admin_companies SET users_count = (SELECT COUNT(*) FROM users WHERE company_id = ' . intval($companyId) . ') WHERE id = ' . intval($companyId));
                            }
                            company_write_audit(intval($user['id']), $companyId, 'create_user', $name, 'إضافة مستخدم جديد داخل الشركة');
                            $ok = 'تمت إضافة المستخدم بنجاح.';
                            $_POST = array();
                        }
                    }
                }
            }
        }
    }
}

$list = array();
$listFields = 'id, name, email, phone, role, status, created_at, last_login_at';
if (company_users_has_column('role_id')) {
    $listFields .= ', role_id';
}
$qList = @mysqli_query($conn, 'SELECT ' . $listFields . ' FROM users WHERE company_id = ' . intval($companyId) . ' ORDER BY id DESC');
if ($qList) {
    while ($row = mysqli_fetch_assoc($qList)) {
        $list[] = $row;
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة فريق الشركة | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#102443; --ink2:#30527f; --line:rgba(16,36,67,.1); --ok:#0f8a5f; --danger:#c0392b; }
        *{box-sizing:border-box}
        .role-badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.78rem;font-weight:700;background:rgba(16,36,67,.08);color:#30527f;white-space:nowrap}
        .role-badge.lvl2{background:rgba(99,102,241,.1);color:#4338ca}
        .status-badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.78rem;font-weight:700}
        .st-active{background:rgba(15,138,95,.1);color:#0f8a5f}
        .st-inactive{background:rgba(100,116,139,.12);color:#64748b}
        .st-suspended{background:rgba(192,57,43,.1);color:#c0392b}
        body{margin:0;font-family:'Cairo',sans-serif;background:#edf2f8;color:var(--ink);padding:20px}
        .wrap{max-width:1100px;margin:0 auto}
        h1{margin:0 0 6px}
        .sub{margin:0 0 16px;color:#64748b}
        .grid{display:grid;grid-template-columns:360px 1fr;gap:14px}
        .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px}
        .alert{padding:10px 12px;border-radius:10px;font-size:.9rem;margin-bottom:12px}
        .ok{background:rgba(15,138,95,.1);border:1px solid rgba(15,138,95,.2);color:var(--ok)}
        .err{background:rgba(192,57,43,.1);border:1px solid rgba(192,57,43,.2);color:var(--danger)}
        .field{margin-bottom:10px}
        label{display:block;margin-bottom:5px;font-size:.82rem;font-weight:700;color:var(--ink2)}
        input,select{width:100%;padding:10px 11px;border:1px solid var(--line);border-radius:10px;font-family:inherit}
        .btn{border:none;background:#102443;color:#fff;padding:11px 13px;border-radius:10px;font-family:inherit;font-weight:800;cursor:pointer}
        table{width:100%;border-collapse:collapse}
        th,td{padding:9px;border-bottom:1px solid var(--line);font-size:.86rem;text-align:right}
        th{background:#f6f9ff;color:#30527f}
        .top-links{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
        .linkbtn{display:inline-block;background:#fff;border:1px solid var(--line);color:#30527f;text-decoration:none;padding:8px 11px;border-radius:10px;font-weight:700;font-size:.84rem}
        @media(max-width:900px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <h1>إدارة فريق الشركة</h1>
    <p class="sub">إضافة المدراء والمستخدمين داخل نفس الشركة مع تحديد الدور والحالة.</p>

    <div class="top-links">
        <a class="linkbtn" href="<?php echo e(company_url('home.php')); ?>">العودة لبوابة الشركة</a>
        <a class="linkbtn" href="/ems/main/dashboard.php">الذهاب للوحة النظام</a>
    </div>

    <div class="grid">
        <div class="card">
            <?php if ($ok !== ''): ?><div class="alert ok"><?php echo e($ok); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert err"><?php echo e($error); ?></div><?php endif; ?>

            <form method="post" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                <div class="field"><label for="name">الاسم الكامل *</label><input id="name" name="name" required maxlength="150" value="<?php echo isset($_POST['name']) ? e($_POST['name']) : ''; ?>"></div>
                <div class="field"><label for="email">البريد الإلكتروني *</label><input id="email" name="email" type="email" required maxlength="150" value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>"></div>
                <div class="field"><label for="phone">الهاتف *</label><input id="phone" name="phone" required maxlength="30" value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>"></div>

                <div class="field">
                    <label for="role_id">الدور *</label>
                    <?php if (empty($roles)): ?>
                    <div class="alert err" style="margin:0;">جدول الأدوار غير موجود أو لا يحتوي على أدوار نشطة.</div>
                    <input type="hidden" name="role_id" value="0">
                    <?php else: ?>
                    <select id="role_id" name="role_id" required>
                        <option value="">— اختر الدور —</option>
                        <?php
                        $selectedRole = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
                        $parents  = $rolesData['parents'];
                        $children = $rolesData['children'];

                        foreach ($parents as $parent) {
                            $pid    = intval($parent['id']);
                            $pSel   = ($selectedRole === $pid) ? ' selected' : '';
                            $hasKids = !empty($children[strval($pid)]);

                            if ($hasKids) {
                                echo '<optgroup label="' . e($parent['name']) . '">';
                                // الأب نفسه كخيار قابل للاختيار داخل المجموعة
                                echo '<option value="' . $pid . '"' . $pSel . '>» ' . e($parent['name']) . '</option>';
                                foreach ($children[strval($pid)] as $child) {
                                    $cid  = intval($child['id']);
                                    $cSel = ($selectedRole === $cid) ? ' selected' : '';
                                    echo '<option value="' . $cid . '"' . $cSel . '>   ' . e($child['name']) . '</option>';
                                }
                                echo '</optgroup>';
                            } else {
                                echo '<option value="' . $pid . '"' . $pSel . '>' . e($parent['name']) . '</option>';
                            }
                        }
                        // أدوار بلا أب لم تندرج ضمن parents (احتياط)
                        foreach ($rolesData['children'] as $cpid => $clist) {
                            $isRendered = false;
                            foreach ($parents as $pp) { if (strval($pp['id']) === strval($cpid)) { $isRendered = true; break; } }
                            if (!$isRendered) {
                                foreach ($clist as $orphan) {
                                    $oid  = intval($orphan['id']);
                                    $oSel = ($selectedRole === $oid) ? ' selected' : '';
                                    echo '<option value="' . $oid . '"' . $oSel . '>' . e($orphan['name']) . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="status">الحالة *</label>
                    <select id="status" name="status" required>
                        <?php
                        $statuses = array(
                            'active'    => 'نشط',
                            'inactive'  => 'غير نشط',
                            'suspended' => 'موقوف',
                        );
                        $selectedStatus = isset($_POST['status']) ? $_POST['status'] : 'active';
                        foreach ($statuses as $value => $label) {
                            $sel = ($selectedStatus === $value) ? 'selected' : '';
                            echo '<option value="' . e($value) . '" ' . $sel . '>' . e($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="field"><label for="password">كلمة المرور *</label><input id="password" name="password" type="password" required maxlength="255"></div>

                <button class="btn" type="submit">إضافة المستخدم</button>
            </form>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                        <tr><td colspan="6">لا يوجد مستخدمون بعد.</td></tr>
                    <?php else: foreach ($list as $idx => $u): ?>
                        <?php
                        // تحديد IDّ الدور: role_id أولاً، ثم role
                        $rowRoleId = isset($u['role_id']) && intval($u['role_id']) > 0
                            ? strval(intval($u['role_id']))
                            : (isset($u['role']) && is_numeric($u['role']) ? strval(intval($u['role'])) : '');

                        // اسم الدور من الخريطة
                        if ($rowRoleId !== '' && isset($roleMap[$rowRoleId])) {
                            $roleDisplay = $roleMap[$rowRoleId];
                            $roleLevel   = isset($roles[$rowRoleId]) ? intval($roles[$rowRoleId]['level']) : 1;
                        } elseif (isset($u['role']) && isset($roleMap[strval($u['role'])])) {
                            $roleDisplay = $roleMap[strval($u['role'])];
                            $roleLevel   = 1;
                        } else {
                            $roleDisplay = isset($u['role']) && $u['role'] !== '' ? e($u['role']) : '—';
                            $roleLevel   = 1;
                        }

                        // عرض الحالة بالعربية
                        $statusLabels = array(
                            'active'    => array('label' => 'نشط',     'cls' => 'st-active'),
                            'inactive'  => array('label' => 'غير نشط', 'cls' => 'st-inactive'),
                            'suspended' => array('label' => 'موقوف',   'cls' => 'st-suspended'),
                        );
                        $stKey  = isset($u['status']) ? strtolower($u['status']) : '';
                        $stInfo = isset($statusLabels[$stKey]) ? $statusLabels[$stKey] : array('label' => e($u['status']), 'cls' => 'st-inactive');
                        ?>
                        <tr>
                            <td><?php echo intval($idx + 1); ?></td>
                            <td style="font-weight:700"><?php echo e($u['name']); ?></td>
                            <td style="color:#64748b"><?php echo e($u['email']); ?></td>
                            <td>
                                <span class="role-badge<?php echo $roleLevel >= 2 ? ' lvl2' : ''; ?>">
                                    <?php echo e($roleDisplay); ?>
                                </span>
                            </td>
                            <td><span class="status-badge <?php echo $stInfo['cls']; ?>"><?php echo $stInfo['label']; ?></span></td>
                            <td style="color:#94a3b8;font-size:.82rem"><?php echo !empty($u['last_login_at']) ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : '—'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
