<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

/* Ø¬Ù„Ø¨ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT `id`, `name`, `parent_role_id`, `level`, `status`, `created_at` FROM `roles` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_role_id = !empty($_POST['parent_role_id']) && $_POST['parent_role_id'] !== '' ? (int) $_POST['parent_role_id'] : null;
    $level = (int) ($_POST['level'] ?? 1);
    $status = (int) ($_POST['status'] ?? 1);

    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµØ­Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
    if (empty($name)) {
        $error_msg = 'Ø§Ø³Ù… Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© Ù…Ø·Ù„ÙˆØ¨ âŒ';
    } else {
        if (!empty($_POST['edit_id'])) {
            // ØªØ¹Ø¯ÙŠÙ„
            $id = (int) $_POST['edit_id'];
            $stmt = $conn->prepare(
                "UPDATE `roles` SET `name` = ?, `parent_role_id` = ?, `level` = ?, `status` = ? WHERE `id` = ?"
            );
            $stmt->bind_param("siiii", $name, $parent_role_id, $level, $status, $id);
        } else {
            // Ø¥Ø¶Ø§ÙØ©
            $stmt = $conn->prepare(
                "INSERT INTO `roles` (`name`, `parent_role_id`, `level`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param("siii", $name, $parent_role_id, $level, $status);
        }

        if ($stmt->execute()) {
            header("Location: roles.php?msg=ØªÙ…+Ø§Ù„Ø¨Ø­ÙØ§Ø¸+Ø¹Ù„Ù‰+Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
            exit;
        } else {
            $error_msg = 'Ø­Ø¯Ø« Ø®Ø·Ø£: ' . htmlspecialchars($stmt->error) . ' âŒ';
        }
    }
}

/* Ø­Ø°Ù */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    // Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø¹Ø¯Ù… Ø§Ø³ØªØ®Ø¯Ø§Ù… Ù‡Ø°Ø§ Ø§Ù„Ø¯ÙˆØ± ÙƒØ¯ÙˆØ± Ø£Ø¨
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `roles` WHERE `parent_role_id` = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        header("Location: roles.php?msg=Ù„Ø§+ÙŠÙ…ÙƒÙ†+Ø­Ø°Ù+Ù‡Ø°Ø§+Ø§Ù„Ø¯ÙˆØ±+Ù„Ø£Ù†Ù‡+ÙŠÙ…ØªÙ„Ùƒ+Ø£Ø¯ÙˆØ§Ø±+ÙØ±Ø¹ÙŠØ©+âŒ");
    } else {
        $stmt = $conn->prepare("DELETE FROM `roles` WHERE `id` = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: roles.php?msg=ØªÙ…+Ø­Ø°Ù+Ø§Ù„Ø¯ÙˆØ±+Ø¨Ù†Ø¬Ø§Ø­+âœ…");
        } else {
            header("Location: roles.php?msg=Ø­Ø¯Ø«+Ø®Ø·Ø£+ÙÙŠ+Ø§Ù„Ø­Ø°Ù+âŒ");
        }
    }
    exit;
}

// Ø¬Ù„Ø¨ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ø¯ÙˆØ§Ø± Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠØ© (Ø¨Ø¯ÙˆÙ† Ø¯ÙˆØ± Ø£Ø¨)
$stmt = $conn->prepare("SELECT `id`, `name` FROM `roles` WHERE `parent_role_id` IS NULL ORDER BY `name`");
$stmt->execute();
$parent_roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-shield-alt"></i></div>
            Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª ÙˆØ§Ù„Ø£Ø¯ÙˆØ§Ø±
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="settings.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© ØµÙ„Ø§Ø­ÙŠØ© Ø¬Ø¯ÙŠØ¯Ø©
            </a>
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

    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ -->
    <form id="roleForm" action="" method="post" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <?= !empty($editData) ? 'ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ©' : 'Ø¥Ø¶Ø§ÙØ© ØµÙ„Ø§Ø­ÙŠØ© Ø¬Ø¯ÙŠØ¯Ø©'; ?></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div class="form-grid">
                    <!-- Ø§Ø³Ù… Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© -->
                    <div>
                        <label><i class="fas fa-tag"></i> Ø§Ø³Ù… Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© *</label>
                        <input type="text" name="name" id="name" placeholder="Ù…Ø«Ø§Ù„: Ù…Ø¯ÙŠØ± Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹" required />
                    </div>

                    <!-- Ø§Ù„Ø¯ÙˆØ± Ø§Ù„Ø£Ø¨ (Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© Ø§Ù„Ø£Ø¨) -->
                    <div>
                        <label><i class="fas fa-sitemap"></i> Ø§Ù„Ø¯ÙˆØ± Ø§Ù„Ø£Ø¨ (Ø§Ø®ØªÙŠØ§Ø±ÙŠ)</label>
                        <select id="parent_role_id" name="parent_role_id">
                            <option value="">-- Ø¨Ø¯ÙˆÙ† Ø¯ÙˆØ± Ø£Ø¨ (Ù…Ø¯ÙŠØ± Ø±Ø¦ÙŠØ³ÙŠ) --</option>
                            <?php foreach ($parent_roles as $pRole): ?>
                                <option value="<?= $pRole['id']; ?>">
                                    <?= htmlspecialchars($pRole['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ø§Ù„Ù…Ø³ØªÙˆÙ‰ -->
                    <div>
                        <label><i class="fas fa-layer-group"></i> Ù…Ø³ØªÙˆÙ‰ Ø§Ù„Ù‡Ø±Ù…ÙŠØ©</label>
                        <select name="level" id="level" required>
                            <option value="1" selected> Ù…Ø¯ÙŠØ± </option>
                            <option value="2"> Ù…Ø´Ø±Ù </option>
                        </select>
                    </div>

                    <!-- Ø§Ù„Ø­Ø§Ù„Ø© -->
                    <div>
                        <label><i class="fas fa-toggle-on"></i> Ø­Ø§Ù„Ø© Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© *</label>
                        <select name="status" id="status" required>
                            <option value="1" selected>Ù†Ø´Ø·Ø© âœ…</option>
                            <option value="0">ØºÙŠØ± Ù†Ø´Ø·Ø© â¸</option>
                        </select>
                    </div>

                    <button type="submit" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ©
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> Ø¬Ù…ÙŠØ¹ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="rolesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th><i class="fas fa-tag"></i> Ø§Ø³Ù… Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ©</th>
                            <th><i class="fas fa-sitemap"></i> Ø§Ù„Ø¯ÙˆØ± Ø§Ù„Ø£Ø¨</th>
                            <th width="100"><i class="fas fa-layer-group"></i> Ø§Ù„Ù…Ø³ØªÙˆÙ‰</th>
                            <th width="100"><i class="fas fa-toggle-on"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
                            <th width="120"><i class="fas fa-calendar"></i> ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡</th>
                            <th width="120"><i class="fas fa-cogs"></i> Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("
                            SELECT 
                                r.`id`, 
                                r.`name`, 
                                r.`parent_role_id`, 
                                r.`level`, 
                                r.`status`, 
                                r.`created_at`,
                                p.`name` AS parent_name
                            FROM `roles` r
                            LEFT JOIN `roles` p ON r.`parent_role_id` = p.`id`
                            ORDER BY r.`level`, r.`parent_role_id`, r.`name`
                        ");

                        if (!$result) {
                            echo '<tr><td colspan="7" class="text-center text-danger">Ø®Ø·Ø£ ÙÙŠ Ø¬Ù„Ø¨ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª: ' . htmlspecialchars($conn->error) . '</td></tr>';
                        } else {
                            $i = 1;
                            while ($row = $result->fetch_assoc()):
                                $status_badge = $row['status'] == 1
                                    ? '<span class="status-active"><i class="fas fa-check-circle"></i> Ù†Ø´Ø·</span>'
                                    : '<span class="status-inactive"><i class="fas fa-times-circle"></i> ØºÙŠØ± Ù†Ø´Ø·</span>';
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td>
                                        <a href="modules.php?role_id=<?= $row['id']; ?>"
                                            style="color: var(--navy); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                            onmouseover="this.style.color='var(--gold)'; this.style.textDecoration='underline';"
                                            onmouseout="this.style.color='var(--navy)'; this.style.textDecoration='none';">
                                            <strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </a>
                                        <?php if ($row['parent_role_id'] === null): ?>
                                            <br><small style="color: var(--gold); font-weight: 600;">ðŸ”µ Ù…Ø¯ÙŠØ± Ø±Ø¦ÙŠØ³ÙŠ</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['parent_name'])): ?>
                                            <a href="modules.php?role_id=<?= $row['parent_role_id']; ?>"
                                                style="color: var(--blue); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                                onmouseover="this.style.color='var(--navy)'; this.style.textDecoration='underline';"
                                                onmouseout="this.style.color='var(--blue)'; this.style.textDecoration='none';">
                                                <i class="fas fa-link"></i>
                                                <?= htmlspecialchars($row['parent_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--sub);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-active"
                                            style="background: rgba(37,99,235,.12); color: var(--blue);"><i
                                                class="fas fa-layer-group"></i> <?= (int) $row['level']; ?></span></td>
                                    <td><?= $status_badge; ?></td>
                                    <td>
                                        <small style="color: var(--sub);">
                                            <?= date('Y-m-d', strtotime($row['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0);"
                                            onclick="editRole(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="btn btn-sm btn-primary" title="ØªØ¹Ø¯ÙŠÙ„"
                                            style="background: var(--blue-soft); color: var(--blue); border: 1.5px solid rgba(37,99,235,.18);">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);"
                                            onclick="confirmDelete(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>')"
                                            class="btn btn-sm btn-danger" title="Ø­Ø°Ù"
                                            style="background: var(--red-soft); color: var(--red); border: 1.5px solid rgba(220,38,38,.18);">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>


<script>
    // ØªÙ‡ÙŠØ¦Ø© DataTable
    $('#rolesTable').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        },
        columnDefs: [
            { "orderable": false, "targets": [6] }
        ]
    });

    $(document).ready(function () {
        // Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬
        $('#toggleForm').on('click', function () {
            $('#roleForm').slideToggle(300);
            $('html, body').animate({
                scrollTop: $('#roleForm').offset().top - 100
            }, 500);
        });
    });

    // Ø¯Ø§Ù„Ø© ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
    function editRole(data) {
        document.getElementById('roleForm').style.display = 'block';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('parent_role_id').value = data.parent_role_id || '';
        document.getElementById('level').value = data.level || 1;
        document.getElementById('status').value = data.status || 1;

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#roleForm').offset().top - 100
        }, 500);
    }

    // Ø¯Ø§Ù„Ø© ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø­Ø°Ù
    function confirmDelete(id, name) {
        if (confirm(`Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ Ù…Ù† Ø±ØºØ¨ØªÙƒ ÙÙŠ Ø­Ø°Ù Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ© "${name}"ØŸ`)) {
            window.location.href = 'roles.php?delete_id=' + id;
        }
    }
</script>

</body>

</html>
