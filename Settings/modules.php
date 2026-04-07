<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';

function moduleColumnExists($conn, $column_name)
{
    $safe_column_name = mysqli_real_escape_string($conn, $column_name);
    $result = $conn->query("SHOW COLUMNS FROM `modules` LIKE '" . $safe_column_name . "'");
    return $result && $result->num_rows > 0;
}

$module_has_icon_column = moduleColumnExists($conn, 'icon');
$default_module_icon = 'fa fa-link';
$common_sidebar_icons = array(
    array('class' => 'fa fa-link', 'label' => 'رابط عام'),
    array('class' => 'fa fa-home', 'label' => 'الرئيسية'),
    array('class' => 'fa fa-users', 'label' => 'العملاء'),
    array('class' => 'fa fa-user-shield', 'label' => 'الصلاحيات'),
    array('class' => 'fa fa-users-cog', 'label' => 'المستخدمون'),
    array('class' => 'fa fa-folder-open', 'label' => 'المشاريع'),
    array('class' => 'fa fa-list-alt', 'label' => 'القوائم'),
    array('class' => 'fa fa-mountain', 'label' => 'المناجم'),
    array('class' => 'fa fa-truck-loading', 'label' => 'الموردون'),
    array('class' => 'fa fa-tractor', 'label' => 'المعدات'),
    array('class' => 'fa fa-truck-moving', 'label' => 'الأسطول'),
    array('class' => 'fa fa-id-card', 'label' => 'المشغلون'),
    array('class' => 'fa fa-cogs', 'label' => 'التشغيل'),
    array('class' => 'fa fa-business-time', 'label' => 'ساعات العمل'),
    array('class' => 'fa fa-calendar-days', 'label' => 'ساعات اليوم'),
    array('class' => 'fa fa-file-contract', 'label' => 'العقود'),
    array('class' => 'fa fa-check-double', 'label' => 'الموافقات'),
    array('class' => 'fa fa-chart-pie', 'label' => 'التقارير'),
    array('class' => 'fa fa-chart-line', 'label' => 'تحليل'),
    array('class' => 'fa fa-chart-column', 'label' => 'إحصائيات'),
    array('class' => 'fa fa-gear', 'label' => 'الإعدادات'),
    array('class' => 'fa fa-key', 'label' => 'الأمان'),
    array('class' => 'fa fa-clipboard-list', 'label' => 'نماذج'),
    array('class' => 'fa fa-screwdriver-wrench', 'label' => 'الأنواع'),
    array('class' => 'fa fa-hard-hat', 'label' => 'الموقع'),
    array('class' => 'fa fa-layer-group', 'label' => 'موديولات'),
    array('class' => 'fa fa-database', 'label' => 'بيانات'),
    array('class' => 'fa fa-bell', 'label' => 'تنبيهات')
);

// جلب role_id من URL إن وجد (للانتقال من صفحة الأدوار)
$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $select_columns = "`id`, `name`, `code`, `owner_role_id`, `is_link`";
    if ($module_has_icon_column) {
        $select_columns .= ", `icon`";
    }
    $stmt = $conn->prepare("SELECT " . $select_columns . " FROM `modules` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    if ($editData && !isset($editData['icon'])) {
        $editData['icon'] = $default_module_icon;
    }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $owner_role_id = !empty($_POST['owner_role_id']) ? (int)$_POST['owner_role_id'] : null;
    $is_link = isset($_POST['is_link']) && $_POST['is_link'] == '1' ? 1 : 0;
    $icon = trim($_POST['icon'] ?? $default_module_icon);
    $icon = preg_replace('/[^a-zA-Z0-9\-\s]/', '', $icon);
    $icon = trim(preg_replace('/\s+/', ' ', $icon));
    if ($icon === '') {
        $icon = $default_module_icon;
    }

    // التحقق من صحة البيانات
    if (empty($name) || empty($code)) {
        $error_msg = 'اسم الصفحة والكود مطلوبان ❌';
    } else {
        if (!empty($_POST['edit_id'])) {
            // تعديل
            $id = (int) $_POST['edit_id'];
            if ($module_has_icon_column) {
                $stmt = $conn->prepare(
                    "UPDATE `modules` SET `name` = ?, `code` = ?, `owner_role_id` = ?, `is_link` = ?, `icon` = ? WHERE `id` = ?"
                );
                $stmt->bind_param("ssiisi", $name, $code, $owner_role_id, $is_link, $icon, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE `modules` SET `name` = ?, `code` = ?, `owner_role_id` = ?, `is_link` = ? WHERE `id` = ?"
                );
                $stmt->bind_param("ssiii", $name, $code, $owner_role_id, $is_link, $id);
            }
        } else {
            // التحقق من عدم تكرار نفس الصفحة لنفس الدور
            $check_stmt = $conn->prepare(
                "SELECT id FROM `modules` WHERE `code` = ? AND `owner_role_id` <=> ? LIMIT 1"
            );
            $check_stmt->bind_param("si", $code, $owner_role_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $error_msg = 'هذه الصفحة مضافة مسبقاً لنفس الدور المسؤول ❌';
            } else {
                // إضافة
                if ($module_has_icon_column) {
                    $stmt = $conn->prepare(
                        "INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`, `icon`) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssiis", $name, $code, $owner_role_id, $is_link, $icon);
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO `modules` (`name`, `code`, `owner_role_id`, `is_link`) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssii", $name, $code, $owner_role_id, $is_link);
                }
            }
        }

        if (!isset($error_msg)) {
            if ($stmt->execute()) {
                header("Location: modules.php?msg=تم+البحفاظ+على+البيانات+بنجاح+✅");
                exit;
            } else {
                $error_msg = 'حدث خطأ: ' . htmlspecialchars($stmt->error) . ' ❌';
            }
        }
    }
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM `modules` WHERE `id` = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: modules.php?msg=تم+حذف+الصفحة+بنجاح+✅");
    } else {
        header("Location: modules.php?msg=حدث+خطأ+في+الحذف+❌");
    }
    exit;
}

// جلب جميع الأدوار
$stmt = $conn->prepare("SELECT `id`, `name` FROM `roles` ORDER BY `level`, `name`");
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "إدارة الصفحات والموديولات";
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

<style>
    .icon-picker-shell {
        display: grid;
        gap: 12px;
    }

    .icon-picker-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
    }

    .icon-picker-search {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        padding: 11px 14px;
        font-family: 'Cairo', sans-serif;
        font-size: .92rem;
        background: #fff;
    }

    .icon-picker-search:focus {
        outline: none;
        border-color: rgba(37,99,235,.35);
        box-shadow: 0 0 0 4px rgba(37,99,235,.08);
    }

    .icon-suggest-btn {
        white-space: nowrap;
    }

    .icon-preview-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: linear-gradient(135deg, rgba(232,184,0,.12), rgba(37,99,235,.08));
        border: 1.5px dashed rgba(232,184,0,.35);
        border-radius: var(--radius-lg);
    }

    .icon-preview-box {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border: 1.5px solid rgba(232,184,0,.28);
        color: var(--navy);
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .icon-preview-meta strong {
        display: block;
        color: var(--txt);
        font-size: .94rem;
        margin-bottom: 2px;
    }

    .icon-preview-meta span {
        color: var(--sub);
        font-size: .8rem;
        word-break: break-word;
    }

    .icon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 10px;
        max-height: 320px;
        overflow-y: auto;
        padding-left: 2px;
    }

    .icon-option {
        border: 1.5px solid var(--border);
        border-radius: 14px;
        background: #fff;
        padding: 12px 10px;
        display: grid;
        justify-items: center;
        gap: 8px;
        cursor: pointer;
        transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease), background var(--ease);
        min-height: 94px;
    }

    .icon-option i {
        font-size: 1.25rem;
        color: var(--navy);
    }

    .icon-option span {
        font-size: .78rem;
        font-weight: 700;
        text-align: center;
        color: var(--txt);
        line-height: 1.45;
    }

    .icon-option:hover {
        transform: translateY(-2px);
        border-color: rgba(37,99,235,.28);
        box-shadow: var(--shadow-sm);
    }

    .icon-option.is-selected {
        background: linear-gradient(180deg, rgba(37,99,235,.08), rgba(232,184,0,.10));
        border-color: rgba(232,184,0,.58);
        box-shadow: 0 10px 20px rgba(15,23,42,.08);
    }

    .icon-picker-note {
        color: var(--sub);
        font-size: .78rem;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .icon-picker-toolbar {
            grid-template-columns: 1fr;
        }

        .icon-grid {
            grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
        }
    }
</style>

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-layer-group"></i></div>
            إدارة الصفحات والموديولات
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="settings.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة صفحة جديدة
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
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

    <!-- فورم إضافة / تعديل -->
    <form id="moduleForm" action="" method="post" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <?= !empty($editData) ? 'تعديل الصفحة' : 'إضافة صفحة جديدة'; ?></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div class="form-grid">
                    <!-- اسم الصفحة -->
                    <div>
                        <label><i class="fas fa-book"></i> اسم الصفحة *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: إدارة العملاء" required />
                    </div>

                    <!-- كود الصفحة -->
                    <div>
                        <label><i class="fas fa-code"></i> كود الصفحة *</label>
                        <input type="text" name="code" id="code" placeholder="مثال: clients" required />
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label><i class="fas fa-icons"></i> أيقونة القائمة</label>
                        <div class="icon-picker-shell">
                            <input type="hidden" name="icon" id="icon" value="<?= htmlspecialchars($default_module_icon, ENT_QUOTES, 'UTF-8'); ?>" />
                            <input type="hidden" id="icon_manually_selected" value="0" />

                            <div class="icon-preview-card">
                                <div class="icon-preview-box"><i id="icon_preview" class="<?= htmlspecialchars($default_module_icon, ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                                <div class="icon-preview-meta">
                                    <strong>الأيقونة المختارة للـ sidebar</strong>
                                    <span id="icon_preview_text"><?= htmlspecialchars($default_module_icon, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>

                            <div class="icon-picker-toolbar">
                                <input type="text" id="iconSearch" class="icon-picker-search" placeholder="ابحث باسم الأيقونة أو معناها مثل: تقارير، مستخدمين، معدات" />
                                <button type="button" id="autoSuggestIcon" class="back-btn icon-suggest-btn">
                                    <i class="fas fa-wand-magic-sparkles"></i> اقتراح تلقائي
                                </button>
                            </div>

                            <div id="iconGrid" class="icon-grid">
                                <?php foreach ($common_sidebar_icons as $sidebar_icon): ?>
                                    <button type="button"
                                            class="icon-option"
                                            data-icon="<?= htmlspecialchars($sidebar_icon['class'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-label="<?= htmlspecialchars($sidebar_icon['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-search="<?= htmlspecialchars($sidebar_icon['label'] . ' ' . $sidebar_icon['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="<?= htmlspecialchars($sidebar_icon['class'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                        <span><?= htmlspecialchars($sidebar_icon['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="icon-picker-note">
                                يتم عرض أشهر أيقونات الشريط الجانبي الجاهزة للاختيار السريع، مع اقتراح تلقائي حسب اسم الصفحة ومسارها.
                                <?php if (!$module_has_icon_column): ?>
                                    <br>ملاحظة: قاعدة البيانات الحالية لا تحتوي بعد على عمود `icon`، لذا ستظهر الأيقونة بعد تنفيذ ملف التحديث داخل مجلد `database`.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- الدور المسؤول -->
                    <div>
                        <label><i class="fas fa-user-tie"></i> الدور المسؤول *</label>
                        <select name="owner_role_id" id="owner_role_id" required>
                            <option value="">-- اختر الدور المسؤول --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id']; ?>" 
                                    <?= ($selected_role_id && $selected_role_id == $role['id'] && !$editData) ? 'selected' : ''; ?>
                                    <?= (!empty($editData) && $editData['owner_role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- رابط -->
                    <div style="display: flex; align-items: center;">
                        <input type="checkbox" name="is_link" id="is_link" value="1" />
                        <label for="is_link" style="margin: 0; margin-right: 8px; cursor: pointer;">
                            <i class="fas fa-link"></i> رابط
                        </label>
                    </div>

                    <button type="submit" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> حفظ الصفحة
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الصفحات -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <h5 style="margin:0;"><i class="fas fa-list"></i> جميع الصفحات والموديولات</h5>
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:700; margin:0;"><i class="fas fa-user-tie"></i> فلترة حسب الدور:</label>
                <select id="roleFilterSelect" style="padding:7px 14px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Cairo',sans-serif; font-size:.88rem; min-width:180px;">
                    <option value="">-- جميع الأدوار --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="clearRoleFilter" class="back-btn" style="display:none;" title="مسح الفلتر">
                    <i class="fas fa-times"></i> مسح
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="modulesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th><i class="fas fa-book"></i> اسم الصفحة</th>
                            <th width="110"><i class="fas fa-icons"></i> الأيقونة</th>
                            <th width="150"><i class="fas fa-code"></i> الكود</th>
                            <th><i class="fas fa-user-tie"></i> الدور المسؤول</th>
                            <th width="80"><i class="fas fa-link"></i> رابط</th>
                            <th width="150"><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where = '';
                        if (isset($_GET['edit_id'])) {
                                $where = "WHERE m.id = " . (int)$_GET['edit_id'];
                        }

                        $result = $conn->query("
                            SELECT 
                                m.`id`, 
                                m.`name`, 
                                m.`code`, 
                                m.`owner_role_id`,
                                m.`is_link`,
                                " . ($module_has_icon_column ? "COALESCE(NULLIF(TRIM(m.`icon`), ''), '" . $default_module_icon . "')" : "'" . $default_module_icon . "'") . " AS `icon`,
                                r.`name` AS role_name
                            FROM `modules` m
                            LEFT JOIN `roles` r ON m.`owner_role_id` = r.`id`
                            $where
                            ORDER BY m.`name`
                        ");
                        
                        if (!$result) {
                            echo '<tr><td colspan="7" class="text-center text-danger">خطأ في جلب البيانات: ' . htmlspecialchars($conn->error) . '</td></tr>';
                        } else {
                            $i = 1;
                            while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span style="display:inline-flex; align-items:center; justify-content:center; width:42px; height:42px; border-radius:12px; background:var(--gold-soft); color:var(--navy); border:1.5px solid rgba(232,184,0,.25);">
                                            <i class="<?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="background: var(--gold-soft); color: var(--navy); padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                            <?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?>
                                        </code>
                                    </td>
                                    <td data-search="<?= htmlspecialchars($row['role_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <a href="role_permissions.php?role_id=<?= $row['owner_role_id']; ?>" 
                                           style="color: var(--blue); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                           onmouseover="this.style.color='var(--navy)'; this.style.textDecoration='underline';"
                                           onmouseout="this.style.color='var(--blue)'; this.style.textDecoration='none';"
                                           title="الانتقال إلى صلاحيات هذا الدور">
                                            <i class="fas fa-user-shield"></i> 
                                            <?= htmlspecialchars($row['role_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['is_link'] == 1): ?>
                                            <span style="display: inline-block; background: var(--green-soft); color: var(--green); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                <i class="fas fa-check-circle"></i> نعم
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background: var(--gray-soft); color: var(--gray); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                <i class="fas fa-times-circle"></i> لا
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0);" 
                                           onclick="editModule(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)"
                                           class="btn btn-sm btn-primary" title="تعديل" style="background: var(--blue-soft); color: var(--blue); border: 1.5px solid rgba(37,99,235,.18);">
                                            <i class="fas fa-edit"></i> 
                                        </a>
                                        <a href="javascript:void(0);" 
                                           onclick="confirmDelete(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>')"
                                           class="btn btn-sm btn-danger" title="حذف" style="background: var(--red-soft); color: var(--red); border: 1.5px solid rgba(220,38,38,.18);">
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
$(document).ready(function () {
    // تهيئة DataTable
    var modulesTable = $('#modulesTable').DataTable({
        responsive: true,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        },
        columnDefs: [
            { "orderable": false, "targets": [6] }
        ]
    });

    // فلترة حسب الدور المسؤول (العمود index 4)
    $('#roleFilterSelect').on('change', function () {
        var val = $.trim($(this).val());
        // بحث نصي عادي بدون regex لضمان عمله مع النص العربي
        modulesTable.column(4).search(val, false, false).draw();
        $('#clearRoleFilter').toggle(val !== '');
    });

    $('#clearRoleFilter').on('click', function () {
        $('#roleFilterSelect').val('');
        modulesTable.column(4).search('', false, false).draw();
        $(this).hide();
    });

    // إظهار/إخفاء النموذج
    $('#toggleForm').on('click', function () {
        $('#moduleForm').slideToggle(300);
        $('html, body').animate({
            scrollTop: $('#moduleForm').offset().top - 100
        }, 500);
    });

    // إذا كان هناك role_id محدد في URL، فعّل الفلتر تلقائياً بعد جاهزية DataTable
    <?php if ($selected_role_id):
        $selected_role_name = '';
        foreach ($roles as $r) {
            if ($r['id'] == $selected_role_id) { $selected_role_name = $r['name']; break; }
        }
    ?>
    var autoRoleName = <?= json_encode($selected_role_name); ?>;
    if (autoRoleName) {
        $('#roleFilterSelect').val(autoRoleName).trigger('change');
    }
    <?php endif; ?>

    // إظهار النموذج تلقائياً عند الانتقال ب role_id
    <?php if ($selected_role_id): ?>
    document.getElementById('moduleForm').style.display = 'block';
    $('html, body').animate({ scrollTop: $('#moduleForm').offset().top - 100 }, 500);
    <?php endif; ?>

    initializeIconPicker();
});

function initializeIconPicker() {
    var iconInput = document.getElementById('icon');
    var defaultIcon = iconInput.value || 'fa fa-link';

    selectIcon(defaultIcon, false);

    $('#iconGrid').on('click', '.icon-option', function () {
        selectIcon($(this).data('icon'), true);
    });

    $('#iconSearch').on('input', function () {
        var searchValue = $.trim($(this).val().toLowerCase());
        $('#iconGrid .icon-option').each(function () {
            var haystack = ($(this).data('search') || '').toString().toLowerCase();
            $(this).toggle(haystack.indexOf(searchValue) !== -1);
        });
    });

    $('#autoSuggestIcon').on('click', function () {
        document.getElementById('icon_manually_selected').value = '0';
        autoSuggestIcon();
    });

    $('#name, #code').on('input', function () {
        if (document.getElementById('icon_manually_selected').value === '1') {
            return;
        }
        autoSuggestIcon();
    });

    autoSuggestIcon();
}

function selectIcon(iconClass, isManualSelection) {
    var normalizedIcon = $.trim(iconClass || 'fa fa-link');
    var iconInput = document.getElementById('icon');
    var preview = document.getElementById('icon_preview');
    var previewText = document.getElementById('icon_preview_text');

    iconInput.value = normalizedIcon;
    preview.className = normalizedIcon;
    previewText.textContent = normalizedIcon;

    $('#iconGrid .icon-option').removeClass('is-selected');
    $('#iconGrid .icon-option[data-icon="' + normalizedIcon.replace(/"/g, '\\"') + '"]').addClass('is-selected');

    if (isManualSelection) {
        document.getElementById('icon_manually_selected').value = '1';
    }
}

function autoSuggestIcon() {
    var text = [$('#name').val(), $('#code').val()].join(' ').toLowerCase();
    var suggestedIcon = 'fa fa-link';
    var iconMap = [
        { pattern: /(home|dashboard|رئيس|لوحة)/, icon: 'fa fa-home' },
        { pattern: /(client|customer|عميل|عملاء)/, icon: 'fa fa-users' },
        { pattern: /(role|permission|صلاح|دور)/, icon: 'fa fa-user-shield' },
        { pattern: /(user|users|مستخدم)/, icon: 'fa fa-users-cog' },
        { pattern: /(project|projects|مشروع|مشاريع)/, icon: 'fa fa-folder-open' },
        { pattern: /(mine|mines|منجم|مناجم)/, icon: 'fa fa-mountain' },
        { pattern: /(supplier|suppliers|مورد)/, icon: 'fa fa-truck-loading' },
        { pattern: /(equipment|equipments|fleet|معدة|معدات|آليات|اسطول|أسطول)/, icon: 'fa fa-tractor' },
        { pattern: /(driver|drivers|مشغل|مشغلين)/, icon: 'fa fa-id-card' },
        { pattern: /(operator|operation|oprator|تشغيل)/, icon: 'fa fa-cogs' },
        { pattern: /(timesheet|hour|hours|ساعات|دوام)/, icon: 'fa fa-business-time' },
        { pattern: /(day|daily|today|يوم|اليوم)/, icon: 'fa fa-calendar-days' },
        { pattern: /(contract|contracts|عقد|عقود)/, icon: 'fa fa-file-contract' },
        { pattern: /(approval|request|requests|موافق|اعتماد|طلبات)/, icon: 'fa fa-check-double' },
        { pattern: /(report|reports|تقارير|احصا|إحصا|chart)/, icon: 'fa fa-chart-pie' },
        { pattern: /(setting|settings|اعداد|إعداد)/, icon: 'fa fa-gear' },
        { pattern: /(password|security|key|كلمة المرور|امان|أمان)/, icon: 'fa fa-key' },
        { pattern: /(module|modules|صفحة|موديول)/, icon: 'fa fa-layer-group' },
        { pattern: /(type|types|نوع|أنواع)/, icon: 'fa fa-screwdriver-wrench' }
    ];

    $.each(iconMap, function (_, item) {
        if (item.pattern.test(text)) {
            suggestedIcon = item.icon;
            return false;
        }
    });

    selectIcon(suggestedIcon, false);
}

// دالة تعديل البيانات
function editModule(data) {
    document.getElementById('moduleForm').style.display = 'block';
    document.getElementById('edit_id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('code').value = data.code;
    document.getElementById('owner_role_id').value = data.owner_role_id || '';
    document.getElementById('is_link').checked = data.is_link == 1;
    document.getElementById('icon_manually_selected').value = '1';
    selectIcon(data.icon || 'fa fa-link', false);
    $('html, body').animate({ scrollTop: $('#moduleForm').offset().top - 100 }, 500);
}

// دالة تأكيد الحذف
function confirmDelete(id, name) {
    if (confirm(`هل أنت متأكد من رغبتك في حذف الصفحة "${name}"؟`)) {
        window.location.href = 'modules.php?delete_id=' + id;
    }
}
</script>

</body>

</html>

