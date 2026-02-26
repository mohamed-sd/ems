<?php
session_start();
// تضمين ملف الجلسات
include '../includes/sessions.php';
// تعريف عنوان الصفحة
$page_title = 'Equipation | المستخدمين';
// تضمين الهيدر
include '../inheader.php';
// تضمين الشريط الجانبي
include '../insidebar.php';

include '../config.php';

// إضافة أو تعديل مستخدم (بدون تشفير كلمة المرور)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = isset($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : '';
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $project = (($role == "5" || $role == "10") && !empty($_POST['project_id'])) ? intval($_POST['project_id']) : 0;
    $mine = (($role == "5" || $role == "10") && !empty($_POST['mine_id'])) ? intval($_POST['mine_id']) : 0;
    $contract = (($role == "5" || $role == "10") && !empty($_POST['contract_id'])) ? intval($_POST['contract_id']) : 0;
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

    if ($uid > 0) {
        // تحقق من التكرار عند التعديل (يتجاهل السجل الحالي)
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != '$uid' LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // إذا تم إدخال كلمة مرور جديدة، نحدّثها كما هي؛ وإلا لا نغيّرها
            $sql_pass = "";
            if (!empty($password)) {
                $sql_pass = ", password='$password'";
            }

            $sql = "UPDATE users 
                    SET name='$name', username='$username', phone='$phone', role='$role', project_id='$project', mine_id='$mine', contract_id='$contract', updated_at=NOW() $sql_pass
                    WHERE id='$uid'";
            if (mysqli_query($conn, $sql)) {
                echo "<script>alert('✅ تم التعديل بنجاح'); window.location.href='users.php';</script>";
            } else {
                echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
            }
        }
    } else {
        // تحقق من التكرار عند الإضافة
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");
        if (!$check) {
            echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($check) > 0) {
            echo "<script>alert('⚠️ اسم المستخدم موجود مسبقاً!');</script>";
        } else {
            // إدراج كلمة المرور كنص عادي (غير مشفر)
            $sql = "INSERT INTO users (name, username, password, phone, role, project_id, mine_id, contract_id, parent_id, created_at, updated_at) 
                    VALUES ('$name', '$username', '$password', '$phone', '$role', '$project', '$mine', '$contract', '0', NOW(), NOW())";
            if (mysqli_query($conn, $sql)) {
                echo "<script>alert('✅ تم الحفظ بنجاح'); window.location.href='users.php';</script>";
            } else {
                echo "<script>alert('❌ حدث خطأ: " . mysqli_error($conn) . "');</script>";
            }
        }
    }
}

// ملاحظة: التعامل مع حذف المستخدم موجود في الواجهة (رابط ?delete=...) لكن كود الحذف كان معلقاً في النسخة الأصلية.
// إذا أردت أفعّل الحذف أضيفه لك هنا بأمان مع تحقق الصلاحيات.
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<style>
    /* أنماط الأدوار بألوان مختلفة */
    .role-badge {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    /* مدير المشاريع */
    .role-badge.role-1 {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border-left: 3px solid #1d4ed8;
    }

    /* مدير الموردين */
    .role-badge.role-2 {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
        border-left: 3px solid #6d28d9;
    }

    /* مدير المشغلين */
    .role-badge.role-3 {
        background: linear-gradient(135deg, #ec4899, #be185d);
        color: white;
        border-left: 3px solid #be185d;
    }

    /* مدير الاسطول */
    .role-badge.role-4 {
        background: linear-gradient(135deg, #f97316, #c2410c);
        color: white;
        border-left: 3px solid #c2410c;
    }

    /* مدير الموقع / حركة وتشغيل */
    .role-badge.role-5,
    .role-badge.role-10 {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
        border-left: 3px solid #0891b2;
    }

    /* إضافة اللون أيضاً للصفوف عند التمرير */
    tr:hover .role-badge {
        transform: translateX(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
</style>

<body>

    <div class="main">

        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="title-icon"><i class="fas fa-users-cog"></i></div>
                <h1 class="page-title">إدارة المستخدمين</h1>
            </div>
            <div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
                <button id="toggleForm" class="add">
                    <i class="fas fa-plus-circle"></i> إضافة مستخدم
                </button>
            </div>
        </div>

        <?php

        $roles = array(
            "0" => "مدير",
            "1" => "مدير المشاريع",
            "2" => "مدير الموردين",
            "3" => "مدير المشغلين",
            "4" => "مدير الأسطول",
            "5" => "مدير موقع",
            "6" => "مدخل ساعات عمل",
            "7" => "مراجع ساعات مورد",
            "8" => "مراجع ساعات مشغل",
            "9" => "مراجع الاعطال",
            "10" => "حركة وتشغيل"
        );

        $userRole = $_SESSION['user']['role'];
        $userName = $_SESSION['user']['name'];
        $roleText = isset($roles[$userRole]) ? $roles[$userRole] : "غير معروف";
        ?>

        <form id="projectForm" action="" method="post" style="display:none;">
            <input type="hidden" name="uid" id="uid" value="0" />
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-edit"></i> إضافة / تعديل مستخدم</h5>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div>
                            <label><i class="fas fa-user"></i> الاسم الثلاثي</label>
                            <input type="text" name="name" id="name" placeholder="الاسم الثلاثي" required />
                        </div>
                        <div>
                            <label><i class="fas fa-id-badge"></i> اسم المستخدم</label>
                            <input type="text" name="username" id="username" placeholder="اسم المستخدم (الحد الأدنى 3 أحرف)" required autocomplete="off" />
                            <small id="usernameFeedback" style="display:block; margin-top:5px; min-height:20px;"></small>
                        </div>
                        <div>
                            <label><i class="fas fa-lock"></i> كلمة المرور</label>
                            <input type="password" name="password" id="password" placeholder="كلمة المرور" />
                            <small class="text-muted"><i class="fas fa-info-circle"></i> اتركه فارغاً إذا لا تريد تغييره
                                عند التعديل</small>
                        </div>
                        <div>
                            <label><i class="fas fa-user-shield"></i> الدور / الصلاحية</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="">-- حدد الصلاحية --</option>
                                <option value="1">👨‍💼 مدير المشاريع</option>
                                <option value="2">🏢 مدير الموردين</option>
                                <option value="4">🚚 مدير الأسطول</option>
                                <option value="3">👷 مدير المشغلين</option>
                                <option value="5">📍 مدير موقع</option>
                                <option value="10">📍 حركة وتشغيل </option>
                            </select>
                        </div>
                        <div>
                            <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="text" name="phone" id="phone" placeholder="رقم الهاتف" required />
                        </div>

                        <div id="projectDiv" style="display:none;">
                            <label><i class="fas fa-project-diagram"></i> المشروع <span
                                    style="color: red;">*</span></label>
                            <select id="project_id" name="project_id" class="form-control">
                                <option value="">-- اختر المشروع --</option>
                                <?php
                                $sql = "SELECT id, name, project_code FROM project WHERE status = '1' ORDER BY name ASC";
                                $result = mysqli_query($conn, $sql);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " ({$row['project_code']})</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div id="mineDiv" style="display:none;">
                            <label><i class="fas fa-mountain"></i> المنجم <span style="color: red;">*</span></label>
                            <select id="mine_id" name="mine_id" class="form-control">
                                <option value="">-- اختر المنجم --</option>
                            </select>
                        </div>

                        <div id="contractDiv" style="display:none;">
                            <label><i class="fas fa-file-contract"></i> العقد <span style="color: red;">*</span></label>
                            <select id="contract_id" name="contract_id" class="form-control">
                                <option value="">-- اختر العقد --</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center;">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i> مسح الحقول
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ المستخدم
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> قائمة المستخدمين</h5>
            </div>
            <div class="card-body">
                <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم </th>
                            <th>اسم المستخدم </th>
                            <th>كلمه المرور </th>
                            <th>الدور </th>
                            <th>رقم الهاتف</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT id, name, username, password, phone, role, project_id, mine_id, contract_id FROM users WHERE parent_id='0' AND role!='-1' ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        $roles = array(
                            "1" => "مدير المشاريع",
                            "2" => "مدير الموردين",
                            "3" => "مدير المشغلين",
                            "4" => "مدير الاسطول",
                            "5" => "مدير موقع",
                            "10" => "حركة وتشغيل"
                        );

                        $i = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $project_id = $row['project_id'];
                            $mine_id = $row['mine_id'];
                            $contract_id = $row['contract_id'];

                            $project_info = "";

                            if ($row['role'] == "5" || $row['role'] == "10") {
                                // جلب اسم المشروع
                                if ($project_id > 0) {
                                    $select_project = mysqli_query($conn, "SELECT name, project_code FROM `project` WHERE `id` = $project_id");
                                    if ($project_row = mysqli_fetch_array($select_project)) {
                                        $project_info = "<div style='margin-top: 5px;'><i class='fas fa-project-diagram' style='color: var(--accent-color);'></i> " . htmlspecialchars($project_row['name'], ENT_QUOTES, 'UTF-8') . " (" . $project_row['project_code'] . ")";
                                    }
                                }

                                // جلب اسم المنجم
                                if ($mine_id > 0) {
                                    $select_mine = mysqli_query($conn, "SELECT mine_name, mine_code FROM `mines` WHERE `id` = $mine_id");
                                    if ($mine_row = mysqli_fetch_array($select_mine)) {
                                        $project_info .= "<br><i class='fas fa-mountain' style='color: var(--accent-color);'></i> " . htmlspecialchars($mine_row['mine_name'], ENT_QUOTES, 'UTF-8') . " (" . $mine_row['mine_code'] . ")";
                                    }
                                }

                                // جلب تاريخ العقد
                                if ($contract_id > 0) {
                                    $select_contract = mysqli_query($conn, "SELECT contract_signing_date FROM `contracts` WHERE `id` = $contract_id");
                                    if ($contract_row = mysqli_fetch_array($select_contract)) {
                                        $project_info .= "<br><i class='fas fa-file-contract' style='color: var(--accent-color);'></i> عقد #" . $contract_id . " - " . $contract_row['contract_signing_date'];
                                    }
                                }

                                if (!empty($project_info)) {
                                    $project_info = "<div style='font-size: 0.85rem; color: #6c757d; margin-top: 5px;'>" . $project_info . "</div></div>";
                                }
                            }

                            echo "<tr>";
                            echo "<td><strong>" . $i++ . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . $project_info . "</td>";
                            echo "<td><strong>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                            echo "<td><span class='password-cell'>" . htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8') . "</span></td>";
                            echo "<td><span class='role-badge role-" . $row['role'] . "'>" . (isset($roles[$row['role']]) ? $roles[$row['role']] : "غير معروف") . "</span></td>";
                            echo "<td><i class='fas fa-phone'></i>" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>
                                <div class='action-btns'>
                                <a href='javascript:void(0)' class='editBtn action-btn edit' 
                                   data-id='{$row['id']}' 
                                   data-name='" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-username='" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-phone='" . htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8') . "' 
                                   data-role='{$row['role']}'
                                   data-project='{$row['project_id']}'
                                   data-mine='{$row['mine_id']}'
                                   data-contract='{$row['contract_id']}'
                                   title='تعديل'><i class='fas fa-edit'></i></a> 
                                <a href='?delete={$row['id']}' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>
                                </div>
                              </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </div>

    <!-- jQuery + DataTables -->
    <script src="../includes/js/jquery-3.7.1.main.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
document.addEventListener("DOMContentLoaded", function () {

    const roleSelect = document.getElementById("role");
    const projectDiv = document.getElementById("projectDiv");
    const mineDiv = document.getElementById("mineDiv");
    const contractDiv = document.getElementById("contractDiv");

    const projectSelect = document.getElementById("project_id");
    const mineSelect = document.getElementById("mine_id");
    const contractSelect = document.getElementById("contract_id");

    const form = document.getElementById('projectForm');
    const usernameInput = document.getElementById("username");
    const usernameFeedback = document.getElementById("usernameFeedback");

    let usernameValid = true;

    /* =============================
       إظهار الحقول حسب الدور
    ============================== */
    roleSelect.addEventListener("change", function () {

        if (this.value === "5" || this.value === "10") {

            projectDiv.style.display = "block";
            mineDiv.style.display = "block";
            contractDiv.style.display = "block";

            projectSelect.required = true;
            mineSelect.required = true;
            contractSelect.required = true;

        } else {

            projectDiv.style.display = "none";
            mineDiv.style.display = "none";
            contractDiv.style.display = "none";

            projectSelect.required = false;
            mineSelect.required = false;
            contractSelect.required = false;

            projectSelect.value = "";
            mineSelect.innerHTML = '<option value="">-- اختر المنجم --</option>';
            contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';
        }
    });

    /* =============================
       تحميل المناجم
    ============================== */
    async function loadMines(projectId, selectedMine = null) {

        mineSelect.innerHTML = '<option value="">-- اختر المنجم --</option>';
        contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';

        if (!projectId) return;

        try {
            const response = await fetch(`../Projects/get_project_mines_ajax.php?project_id=${projectId}`);
            const data = await response.json();

            if (data.success && data.mines.length > 0) {
                data.mines.forEach(mine => {
                    const option = document.createElement('option');
                    option.value = mine.id;
                    option.textContent = `${mine.mine_name} (${mine.mine_code})`;
                    mineSelect.appendChild(option);
                });

                if (selectedMine) {
                    mineSelect.value = selectedMine;
                }
            }

        } catch (error) {
            console.error("خطأ في تحميل المناجم:", error);
        }
    }

    /* =============================
       تحميل العقود
    ============================== */
    async function loadContracts(mineId, selectedContract = null) {

        contractSelect.innerHTML = '<option value="">-- اختر العقد --</option>';

        if (!mineId) return;

        try {
            const response = await fetch(`../Contracts/get_mine_contracts_ajax.php?mine_id=${mineId}`);
            const data = await response.json();

            if (data.success && data.contracts.length > 0) {
                data.contracts.forEach(contract => {
                    const option = document.createElement('option');
                    option.value = contract.id;
                    option.textContent = `عقد #${contract.id} - ${contract.contract_signing_date}`;
                    contractSelect.appendChild(option);
                });

                if (selectedContract) {
                    contractSelect.value = selectedContract;
                }
            }

        } catch (error) {
            console.error("خطأ في تحميل العقود:", error);
        }
    }

    /* =============================
       عند تغيير المشروع
    ============================== */
    projectSelect.addEventListener("change", async function () {
        await loadMines(this.value);
    });

    /* =============================
       عند تغيير المنجم
    ============================== */
    mineSelect.addEventListener("change", async function () {
        await loadContracts(this.value);
    });

    /* =============================
       DataTable
    ============================== */
    $('#projectsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: 'نسخ' },
            { extend: 'excel', text: 'تصدير Excel' },
            { extend: 'csv', text: 'تصدير CSV' },
            { extend: 'pdf', text: 'تصدير PDF' },
            { extend: 'print', text: 'طباعة' }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
        }
    });

    /* =============================
       إظهار / إخفاء النموذج
    ============================== */
    document.getElementById('toggleForm').addEventListener('click', function () {
        form.reset();
        document.getElementById("uid").value = 0;
        usernameFeedback.innerHTML = "";
        usernameInput.style.borderColor = "#e9ecef";
        usernameInput.style.boxShadow = "";
        usernameValid = true;
        form.style.display = (form.style.display === "none") ? "block" : "none";
    });

    /* =============================
       تعبئة الفورم عند التعديل
    ============================== */
    $(document).on('click', '.editBtn', async function () {

        const id = $(this).data('id');
        const name = $(this).data('name');
        const username = $(this).data('username');
        const phone = $(this).data('phone');
        const role = $(this).data('role');

        const projectId = $(this).data('project');
        const mineId = $(this).data('mine');
        const contractId = $(this).data('contract');

        $('#uid').val(id);
        $('#name').val(name);
        $('#username').val(username);
        $('#phone').val(phone);
        $('#role').val(role).trigger('change');

        // إعادة تعيين حالة التحقق من اسم المستخدم
        usernameFeedback.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> اسم المستخدم الحالي</span>';
        usernameInput.style.borderColor = "#e9ecef";
        usernameInput.style.boxShadow = "";
        usernameValid = true;

        if (role == "5" || role == "10") {

            if (projectId) {
                $('#project_id').val(projectId);
                await loadMines(projectId, mineId);

                if (mineId) {
                    await loadContracts(mineId, contractId);
                }
            }
        }

        $('#password').val("");
        form.style.display = "block";

        $('html, body').animate({
            scrollTop: $(form).offset().top - 20
        }, 500);
    });

    /* =============================
       تحقق Ajax من اسم المستخدم أثناء الكتابة
    ============================== */
    usernameInput.addEventListener("input", async function () {
        const username = this.value.trim();
        const uid = document.getElementById("uid").value;

        // إعادة تعيين الحالة إذا كان المدخل فارغاً
        if (username === "") {
            usernameFeedback.innerHTML = "";
            usernameInput.style.borderColor = "#e9ecef";
            usernameInput.style.boxShadow = "";
            usernameValid = true;
            return;
        }

        // التحقق من الطول الأدنى
        if (username.length < 3) {
            usernameFeedback.innerHTML = '<span style="color: #ffc107;"><i class="fas fa-info-circle"></i> الحد الأدنى 3 أحرف</span>';
            usernameInput.style.borderColor = "#ffc107";
            usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(255, 193, 7, 0.25)";
            usernameValid = false;
            return;
        }

        // إظهار رسالة التحميل
        usernameFeedback.innerHTML = '<span style="color: #17a2b8;"><i class="fas fa-spinner fa-spin"></i> جاري التحقق...</span>';

        try {
            const response = await fetch("check_username_availability.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `username=${encodeURIComponent(username)}&uid=${uid}`
            });

            const data = await response.json();

            if (data.available) {
                usernameFeedback.innerHTML = `<span style="color: #28a745;"><i class="fas fa-check-circle"></i> ${data.message}</span>`;
                usernameValid = true;
                usernameInput.style.borderColor = "#28a745";
                usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(40, 167, 69, 0.25)";
            } else {
                usernameFeedback.innerHTML = `<span style="color: #dc3545;"><i class="fas fa-times-circle"></i> ${data.message}</span>`;
                usernameValid = false;
                usernameInput.style.borderColor = "#dc3545";
                usernameInput.style.boxShadow = "0 0 0 0.2rem rgba(220, 53, 69, 0.25)";
            }
        } catch (error) {
            usernameFeedback.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> خطأ في التحقق</span>';
            console.error("خطأ:", error);
            usernameValid = false;
        }
    });

    /* =============================
       منع الإرسال إذا كان اسم المستخدم غير متاح
    ============================== */
    document.getElementById('projectForm').addEventListener('submit', function (e) {
        const username = usernameInput.value.trim();
        
        if (username !== "" && !usernameValid) {
            e.preventDefault();
            alert("⚠️ اسم المستخدم غير متاح، يرجى اختيار اسم آخر");
            usernameInput.focus();
            return false;
        }

        if (username === "") {
            e.preventDefault();
            alert("⚠️ يرجى إدخال اسم المستخدم");
            usernameInput.focus();
            return false;
        }
    });

});
</script>

</body>

</html>