<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$perms = get_page_permissions($conn );

// Ù…Ù†Ø¹ Ø§Ù„Ø¯Ø®ÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ÙŠÙƒÙ† Ù„Ø¯ÙŠÙ‡ ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$perms['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('âŒ Ù„Ø§ ØªÙˆØ¬Ø¯ ØµÙ„Ø§Ø­ÙŠØ© Ù„Ù„ÙˆØµÙˆÙ„ Ù„Ù‡Ø°Ù‡ Ø§Ù„ØµÙØ­Ø©'));
    exit();
}


$page_title = "Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¢Ù„ÙŠØ§Øª";
include("../inheader.php");
include("../insidebar.php");

/* Ù…Ù†Ø¹ Ø§Ù„Ø­Ø°Ù Ù…Ø¤Ù‚ØªØ§Ù‹ (Backend) */
if (isset($_GET['delete_id'])) {
    http_response_code(403);
    exit('Deletion is temporarily disabled.');
}

/* Ø¬Ù„Ø¨ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM equipments_types WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
}

/* Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = trim($_POST['form']);
    $type = trim($_POST['type']);
    $status = $_POST['status'];

    if (!empty($_POST['edit_id'])) {
        $id = (int) $_POST['edit_id'];
        $stmt = $conn->prepare(
            "UPDATE equipments_types SET form = ?, type = ?, status = ? WHERE id = ?"
        );
        $stmt->bind_param("sssi", $form, $type, $status, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO equipments_types (form, type, status) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $form, $type, $status);
    }

    $stmt->execute();
    header("Location: equipments_types.php");
    exit;
}
?>

<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    .delete-disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .delete-disabled:hover {
        opacity: 0.6;
    }

    .badge-heavy {
        background-color: #1a6fbb;
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-block;
    }

    .badge-truck {
        background-color: #e07b00;
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-block;
    }
</style>

<div class="main">

    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-cubes"></i></div>
            <h1 class="page-title">Ø¥Ø¯Ø§Ø±Ø© Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¢Ù„ÙŠØ§Øª</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
            </a>
            <?php if ($perms['can_add']): ?>
            <button id="toggleForm" class="add">
                <i class="fa-solid fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù†ÙˆØ¹ Ø¬Ø¯ÙŠØ¯
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ø­Ø°Ù (Ù…Ø®ÙÙŠØ©) -->
    <div id="deleteAlert" class="alert alert-warning text-center" style="display:none;">
        <i class="fa-solid fa-circle-info"></i>
        ØªÙ… Ø¥ÙŠÙ‚Ø§Ù Ø§Ù„Ø­Ø°Ù Ù…Ø¤Ù‚ØªØ§Ù‹ Ø­ÙØ§Ø¸Ø§Ù‹ Ø¹Ù„Ù‰ Ø³Ù„Ø§Ù…Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
    </div>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ -->
    <form id="projectForm" method="post" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">

        <div class="card">
            <div class="card-header">
                <h5>
                    <?= !empty($editData) ? 'ØªØ¹Ø¯ÙŠÙ„ Ù†ÙˆØ¹ Ø§Ù„Ø¢Ù„ÙŠØ©' : 'Ø¥Ø¶Ø§ÙØ© Ù†ÙˆØ¹ Ø¢Ù„ÙŠØ© Ø¬Ø¯ÙŠØ¯Ø©'; ?>
                </h5>
            </div>

            <div class="card-body">
                <div class="form-grid">

                    <?php if (!empty($editData)): ?>
                        <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>Ø§Ù„Ù‚Ø³Ù…</label>
                          <select name="form" required>
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù‚Ø³Ù… --</option>
                            <option value="1"  <?= (!empty($editData) && $editData['form'] === '1') ? 'selected' : ''; ?>> Ù…Ø¹Ø¯Ø§Øª Ø«Ù‚ÙŠÙ„Ø© </option>
                            <option value="2" <?= (!empty($editData) && $editData['form'] === '2') ? 'selected' : ''; ?>> Ø´Ø§Ø­Ù†Ø§Øª  </option>
                        </select>
                    </div>
                    <div>                           

                        <label>Ø¥Ø³Ù… Ø§Ù„Ù†ÙˆØ¹</label>
                        <input type="text" name="type" required
                            value="<?= htmlspecialchars($editData['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div>
                        <label>Ø§Ù„Ø­Ø§Ù„Ø©</label>
                        <select name="status" required>
                            <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ø­Ø§Ù„Ø© --</option>
                            <option value="active" <?= (!empty($editData) && $editData['status'] === 'active') ? 'selected' : ''; ?>>
                                Ù†Ø´Ø·Ø©
                            </option>
                            <option value="inactive" <?= (!empty($editData) && $editData['status'] === 'inactive') ? 'selected' : ''; ?>>
                                ØºÙŠØ± Ù†Ø´Ø·Ø©
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-save"></i> Ø­ÙØ¸
                    </button>

                </div>
            </div>
        </div>
    </form>

    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ø£Ù†ÙˆØ§Ø¹ -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> Ù‚Ø§Ø¦Ù…Ø© Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ø¢Ù„ÙŠØ§Øª</h5>
        </div>

        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ø§Ù„Ù‚Ø³Ù…</th>
                            <th>Ø§Ù„Ù†ÙˆØ¹</th>
                            <th>Ø§Ù„Ø­Ø§Ù„Ø©</th>
                            <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        $result = $conn->query("SELECT * FROM equipments_types");
                        $i = 1;
                        while ($row = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $i++; ?></td>
                                <td>
                                   <?= $row['form'] === '1'
                                        ? "<span class='badge-heavy'>Ù…Ø¹Ø¯Ø§Øª Ø«Ù‚ÙŠÙ„Ø©</span>"
                                        : "<span class='badge-truck'>Ø´Ø§Ø­Ù†Ø§Øª</span>"; ?>
                                </td>
                                <td><?= htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?= $row['status'] === 'active'
                                        ? "<span class='status-active'>Ù†Ø´Ø·</span>"
                                        : "<span class='status-inactive'>ØºÙŠØ± Ù†Ø´Ø·</span>"; ?>
                                </td>
                                <td class="text-center">

                                    <div class="action-btns">
                                        <?php if ($perms['can_edit']): ?>
                                        <a href="equipments_types.php?edit_id=<?= $row['id']; ?>" class="action-btn edit"
                                            title="ØªØ¹Ø¯ÙŠÙ„">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($perms['can_delete']): ?>
                                        <button type="button" class="action-btn delete delete-disabled" title="Ø­Ø°Ù">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                </td>
                            </tr>
                        <?php endwhile; ?>

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
    $(document).ready(function () {

        $('#projectsTable').DataTable({
            responsive: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });

        $('#toggleForm').on('click', function () {
            $('#projectForm').slideToggle();
        });

        $('.delete-disabled').on('click', function () {
            const alertBox = $('#deleteAlert');
            alertBox.fadeIn();

            setTimeout(() => {
                alertBox.fadeOut();
            }, 3000);
        });

    });
</script>

</body>

</html>
