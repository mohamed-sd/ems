<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø¹Ù„Ù‰ ÙˆØ­Ø¯Ø© Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
$_currentUserRole = intval($_SESSION['user']['role']);

// Ø§Ù„Ø¨Ø­Ø« Ø¹Ù† Ù…Ø¹Ø±Ù Ø§Ù„ÙˆØ­Ø¯Ø© Ù…Ø¹ Ù…Ø±Ø§Ø¹Ø§Ø© Ø¯ÙˆØ± Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ
$module_query = "SELECT id FROM modules 
                WHERE (code = 'main/project_users.php' 
                    OR code = 'project_users' 
                    OR code LIKE '%project_users%')
                AND owner_role_id = $_currentUserRole
                LIMIT 1";
$module_result = $conn->query($module_query);
$module_info   = $module_result ? $module_result->fetch_assoc() : null;
$module_id     = $module_info ? $module_info['id'] : null;

// Ø¥Ø°Ø§ Ù„Ù… ÙŠÙÙˆØ¬Ø¯ Ø³Ø¬Ù„ Ø®Ø§Øµ Ø¨Ù‡Ø°Ø§ Ø§Ù„Ø¯ÙˆØ±ØŒ Ø§ÙØªØ±Ø¶ Ø¬Ù…ÙŠØ¹ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª (Ù„Ù„ØªÙˆØ§ÙÙ‚ Ù…Ø¹ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ù‚Ø¯ÙŠÙ…Ø©)
if (!$module_id) {
    $can_view = $can_add = $can_edit = $can_delete = true;
} else {
    $can_view   = false;
    $can_add    = false;
    $can_edit   = false;
    $can_delete = false;

    $perms      = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† Ù‡Ù†Ø§Ùƒ ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
    header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+ØµÙØ­Ø©+Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†+âŒ");
    exit();
}

$page_title = "Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†";

// Ø¬Ù„Ø¨ Ø§Ø³Ù… ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ
$currentRole = $_SESSION['user']['role'];
$roleNameQuery = "SELECT name FROM roles WHERE id = $currentRole LIMIT 1";
$roleNameResult = mysqli_query($conn, $roleNameQuery);
$roleName = '';
if ($roleNameResult && $roleRow = mysqli_fetch_assoc($roleNameResult)) {
    $roleName = htmlspecialchars($roleRow['name'], ENT_QUOTES, 'UTF-8');
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø­Ø°Ù
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: project_users.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø­Ø°Ù+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†+âŒ");
        exit();
    }
    $deleteId = intval($_GET['delete']);
    $userid = $_SESSION['user']['id'];
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ù…Ø±Ø§Ø¯ Ø­Ø°ÙÙ‡ ØªØ§Ø¨Ø¹ Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ Ø£Ùˆ Ù…Ù† Ø¯ÙˆØ± ØªØ§Ø¨Ø¹
    $verifyQuery = "SELECT u.id FROM users u 
                    WHERE u.id = $deleteId 
                    AND (u.parent_id = '$userid' OR u.role IN (
                        SELECT r.id FROM roles r 
                        WHERE r.parent_role_id = {$_SESSION['user']['role']} 
                        AND (r.status = '1' OR r.status = 1)
                    ))";
    
    $verifyResult = mysqli_query($conn, $verifyQuery);
    
    if (mysqli_num_rows($verifyResult) > 0) {
        $deleteSQL = "DELETE FROM users WHERE id = $deleteId";
        if (mysqli_query($conn, $deleteSQL)) {
            header("Location: project_users.php?msg=ØªÙ…+Ø­Ø°Ù+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit;
        } else {
            header("Location: project_users.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø­Ø°Ù+âŒ");
            exit;
        }
    } else {
        header("Location: project_users.php?msg=Ù„ÙŠØ³+Ù„Ø¯ÙŠÙƒ+ØµÙ„Ø§Ø­ÙŠØ©+Ù„Ø­Ø°Ù+Ù‡Ø°Ø§+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+âŒ");
        exit;
    }
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!$can_edit) {
        header("Location: project_users.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†+âŒ");
        exit();
    }
    $userId = intval($_POST['user_id']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = !empty($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : '';
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $userid = $_SESSION['user']['id'];
    
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø£Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ù…Ø±Ø§Ø¯ ØªØ¹Ø¯ÙŠÙ„Ù‡ ØªØ§Ø¨Ø¹ Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ
    $verifyQuery = "SELECT u.id FROM users u 
                    WHERE u.id = $userId 
                    AND (u.parent_id = '$userid' OR u.role IN (
                        SELECT r.id FROM roles r 
                        WHERE r.parent_role_id = {$_SESSION['user']['role']} 
                        AND (r.status = '1' OR r.status = 1)
                    ))";
    
    $verifyResult = mysqli_query($conn, $verifyQuery);
    
    if (mysqli_num_rows($verifyResult) === 0) {
        header("Location: project_users.php?msg=Ù„ÙŠØ³+Ù„Ø¯ÙŠÙƒ+ØµÙ„Ø§Ø­ÙŠØ©+Ù„ØªØ¹Ø¯ÙŠÙ„+Ù‡Ø°Ø§+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+âŒ");
        exit;
    }
    
    // ØªØ­Ù‚Ù‚ Ù…Ù† ØªÙƒØ±Ø§Ø± Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… (Ù…Ø§ Ø¹Ø¯Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ)
    $check_query = "SELECT id FROM users WHERE username = '$username' AND id != $userId";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=Ø§Ø³Ù…+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+Ù…ÙˆØ¬ÙˆØ¯+Ù…Ø³Ø¨Ù‚Ø§Ù‹+âŒ");
        exit;
    }

    // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
    $passwordUpdate = '';
    if (!empty($password)) {
        $passwordUpdate = ", password = '$password'";
    }
    
    $updateSQL = "UPDATE users SET name = '$name', username = '$username', phone = '$phone', role = '$role', updated_at = NOW() $passwordUpdate WHERE id = $userId";
    
    if (mysqli_query($conn, $updateSQL)) {
        header("Location: project_users.php?msg=ØªÙ…+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit;
    } else {
        header("Location: project_users.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„ØªØ¹Ø¯ÙŠÙ„+âŒ");
        exit;
    }
}

// Ù…Ø¹Ø§Ù„Ø¬Ø© Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªØ®Ø¯Ù… Ø¬Ø¯ÙŠØ¯
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && (!isset($_POST['action']) || $_POST['action'] === 'add')) {
    if (!$can_add) {
        header("Location: project_users.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¥Ø¶Ø§ÙØ©+Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†+Ø¬Ø¯Ø¯+âŒ");
        exit();
    }
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $project = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    $parent_id = intval($_SESSION['user']['id']);

    // ØªØ­Ù‚Ù‚ Ù…Ù† ØªÙƒØ±Ø§Ø± Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
    $check_query = "SELECT id FROM users WHERE username = '$username'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        header("Location: project_users.php?msg=Ø§Ø³Ù…+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+Ù…ÙˆØ¬ÙˆØ¯+Ù…Ø³Ø¨Ù‚Ø§Ù‹+âŒ");
        exit;
    }

    // Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªØ®Ø¯Ù… Ø¬Ø¯ÙŠØ¯
    $sql = "INSERT INTO users (name, username, password, phone, role, project_id, parent_id, created_at, updated_at) 
            VALUES ('$name', '$username', '$password', '$phone', '$role', '$project', '$parent_id', NOW(), NOW())";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: project_users.php?msg=ØªÙ…+Ø¥Ø¶Ø§ÙØ©+Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        exit;
    } else {
        header("Location: project_users.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+Ø£Ø«Ù†Ø§Ø¡+Ø§Ù„Ø¥Ø¶Ø§ÙØ©+âŒ");
        exit;
    }
}

$page_title = "Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users-cog"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ù…Ø´Ø±ÙÙŠÙ† <?php echo !empty($roleName) ? '- ' . $roleName : ''; ?>
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($can_add): ?>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ø´Ø±Ù Ø¬Ø¯ÙŠØ¯
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): 
        $isSuccess = strpos($_GET['msg'], 'âœ…') !== false;
    ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

            <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ù…Ø³ØªØ®Ø¯Ù… -->
    <form id="projectForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <input type="hidden" id="action" name="action" value="add">
        <input type="hidden" id="user_id" name="user_id" value="">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <span id="formTitle">Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªØ®Ø¯Ù… Ø¬Ø¯ÙŠØ¯</span></h5>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user"></i> Ø§Ù„Ø§Ø³Ù… Ø«Ù„Ø§Ø«ÙŠ *</label>
                        <input type="text" name="name" id="name" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ù„Ø§Ø³Ù… Ø«Ù„Ø§Ø«ÙŠ" required />
                    </div>
                    <div>
                        <label><i class="fas fa-at"></i> Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… *</label>
                        <input type="text" name="username" id="username" placeholder="Ø£Ø¯Ø®Ù„ Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…" required />
                    </div>
                    <div>
                        <label><i class="fas fa-lock"></i> ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± <span id="passwordRequired">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Ø£Ø¯Ø®Ù„ ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ±" />
                        <small id="passwordHint" style="color: #999; display:none;">Ø§ØªØ±Ùƒ ÙØ§Ø±ØºØ§Ù‹ Ù„Ù„Ø§Ø­ØªÙØ§Ø¸ Ø¨ÙƒÙ„Ù…Ø© Ø§Ù„Ù…Ø±ÙˆØ± Ø§Ù„Ø­Ø§Ù„ÙŠØ©</small>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ *</label>
                        <input type="tel" name="phone" id="phone" placeholder="Ù…Ø«Ø§Ù„: +249123456789" required />
                    </div>
                    <div>
                        <label><i class="fas fa-shield-alt"></i> Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© / Ø§Ù„Ø¯ÙˆØ± *</label>
                        <select name="role" id="role" required>
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© --</option>
                            <?php 
                            // Ø¬Ù„Ø¨ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ø¯ÙˆØ± Ø§Ù„Ø­Ø§Ù„ÙŠ Ù…Ù† Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
                            $currentRole = $_SESSION['user']['role'];
                            $rolesQuery = "SELECT id, name FROM roles 
                                         WHERE parent_role_id = $currentRole 
                                         AND (status = '1' OR status = 1)
                                         ORDER BY id ASC";
                            $rolesResult = mysqli_query($conn, $rolesQuery);
                            
                            if ($rolesResult && mysqli_num_rows($rolesResult) > 0) {
                                while ($roleRow = mysqli_fetch_assoc($rolesResult)) {
                                    echo '<option value="' . $roleRow['id'] . '">' . 
                                         htmlspecialchars($roleRow['name'], ENT_QUOTES, 'UTF-8') . 
                                         '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>Ù„Ø§ ØªÙˆØ¬Ø¯ ØµÙ„Ø§Ø­ÙŠØ§Øª Ù…ØªØ§Ø­Ø©</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">Ø­ÙØ¸ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…</span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('projectForm').style.display='none';">
                        <i class="fas fa-times"></i> Ø¥Ù„ØºØ§Ø¡
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†</h5>
        </div>
        <div class="card-body">
            <table id="usersTable" class="display nowrap">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ø§Ù„Ø§Ø³Ù…</th>
                        <th>Ø§Ø³Ù… Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…</th>
                        <th>Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ</th>
                        <th>Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ©</th>
                        <th>ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡</th>
                        <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„ØªØ§Ø¨Ø¹ÙŠÙ† Ù„Ù„Ù…Ø¯ÙŠØ± Ø§Ù„Ø­Ø§Ù„ÙŠ 
                    // + Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ù…Ù† Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ø¯ÙˆØ± Ø§Ù„Ø­Ø§Ù„ÙŠ
                    $userid = $_SESSION['user']['id'];
                    $currentRole = $_SESSION['user']['role'];

                    $roles = array(
                        "6" => "ðŸ“ Ù…Ø¯Ø®Ù„ Ø³Ø§Ø¹Ø§Øª Ø¹Ù…Ù„",
                        "7" => "âœ“ Ù…Ø±Ø§Ø¬Ø¹ Ø³Ø§Ø¹Ø§Øª Ù…ÙˆØ±Ø¯",
                        "8" => "âœ“ Ù…Ø±Ø§Ø¬Ø¹ Ø³Ø§Ø¹Ø§Øª Ù…Ø´ØºÙ„",
                        "9" => "ðŸ”§ Ù…Ø±Ø§Ø¬Ø¹ Ø§Ù„Ø£Ø¹Ø·Ø§Ù„",
                    );

                    // Ø§Ù„Ø§Ø³ØªØ¹Ù„Ø§Ù… ÙŠØ¬Ù„Ø¨:
                    // 1. Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„Ø°ÙŠÙ† parent_id = Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… Ø§Ù„Ø­Ø§Ù„ÙŠ
                    // 2. Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† Ø§Ù„Ø°ÙŠÙ† role Ù…Ù† Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ø¯ÙˆØ± Ø§Ù„Ø­Ø§Ù„ÙŠ
                    $query = "SELECT DISTINCT u.id, u.name, u.username, u.phone, u.role, u.created_at
                             FROM users u
                             WHERE u.parent_id = '$userid'
                                OR u.role IN (
                                   SELECT r.id FROM roles r 
                                   WHERE r.parent_role_id = $currentRole 
                                   AND (r.status = '1' OR r.status = 1)
                                )
                             ORDER BY u.id DESC";
                    
                    $result = mysqli_query($conn, $query);
                    $i = 1;

                    while ($row = mysqli_fetch_assoc($result)) {
                        $roleText = isset($roles[$row['role']]) ? $roles[$row['role']] : '<span style="color: #999;">ØºÙŠØ± Ù…Ø¹Ø±ÙˆÙ</span>';
                        $createdDate = date('Y-m-d', strtotime($row['created_at']));

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                        echo "<td><code style=\"background: #f0f2f8; padding: 4px 8px; border-radius: 6px;\">" . htmlspecialchars($row['username']) . "</code></td>";
                        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                        echo "<td>" . $roleText . "</td>";
                        echo "<td>" . $createdDate . "</td>";
                        $action_btns = "<td><div class='action-btns'>";
                        if ($can_edit) {
                            $action_btns .= "<a href='javascript:void(0)' 
                                       class='action-btn edit' 
                                       onclick='editUser({$row['id']}, \"" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "\", {$row['role']})'
                                       title='ØªØ¹Ø¯ÙŠÙ„'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            $action_btns .= "<a href='project_users.php?delete={$row['id']}' 
                                       class='action-btn delete' 
                                       onclick=\"return confirm('Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ØŸ')\"
                                       title='Ø­Ø°Ù'><i class='fas fa-trash'></i></a>";
                        }
                        $action_btns .= "</div></td>";
                        echo $action_btns;
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- jQuery (Required first) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
    (function () {
        // ØªØ´ØºÙŠÙ„ DataTable Ø¨Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©
        $(document).ready(function () {
            $('#usersTable').DataTable({
                responsive: true,
                dom: 'Bfrtip', // Buttons + Search + Pagination
                buttons: [
                    { extend: 'copy', text: 'Ù†Ø³Ø®' },
                    { extend: 'excel', text: 'ØªØµØ¯ÙŠØ± Excel' },
                    { extend: 'csv', text: 'ØªØµØ¯ÙŠØ± CSV' },
                    { extend: 'pdf', text: 'ØªØµØ¯ÙŠØ± PDF' },
                    { extend: 'print', text: 'Ø·Ø¨Ø§Ø¹Ø©' }
                ],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø± ÙˆØ¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
        const toggleFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');

        if (toggleFormBtn) {
            toggleFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                // ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø¹Ù†Ø¯ Ø§Ù„Ø¥Ø¶Ø§ÙØ©
                if (projectForm.style.display === "block") {
                    resetForm();
                    $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
                }
            });
        }

        // Ø¯Ø§Ù„Ø© ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
        window.editUser = function(userId, name, username, phone, role) {
            // Ù…Ù„Ø¡ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø¨Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
            document.getElementById('user_id').value = userId;
            document.getElementById('name').value = name;
            document.getElementById('username').value = username;
            document.getElementById('phone').value = phone;
            document.getElementById('role').value = role;
            document.getElementById('password').value = '';
            
            // ØªØºÙŠÙŠØ± Ù†Øµ Ø§Ù„ÙÙˆØ±Ù… ÙˆØ§Ù„Ø²Ø±
            document.getElementById('formTitle').textContent = 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…';
            document.getElementById('submitBtnText').textContent = 'ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…';
            document.getElementById('action').value = 'edit';
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('passwordHint').style.display = 'block';
            document.getElementById('password').removeAttribute('required');
            
            // Ø¹Ø±Ø¶ Ø§Ù„ÙÙˆØ±Ù…
            projectForm.style.display = 'block';
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top - 100 }, 500);
        };

        // Ø¯Ø§Ù„Ø© Ø¥Ø¹Ø§Ø¯Ø© ØªØ¹ÙŠÙŠÙ† Ø§Ù„ÙÙˆØ±Ù…
        window.resetForm = function() {
            document.getElementById('projectForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('action').value = 'add';
            document.getElementById('formTitle').textContent = 'Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªØ®Ø¯Ù… Ø¬Ø¯ÙŠØ¯';
            document.getElementById('submitBtnText').textContent = 'Ø­ÙØ¸ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…';
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('passwordHint').style.display = 'none';
            document.getElementById('password').setAttribute('required', 'required');
        };
    })();
</script>

</body>

</html>
