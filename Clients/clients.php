<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

// ════════════════════════════════════════════════════════════════════════════
// 🔐 التحقق من صلاحيات المستخدم على وحدة العملاء
// ════════════════════════════════════════════════════════════════════════════

// الحصول على معرف وحدة العملاء من جدول modules
$module_query = "SELECT id FROM modules 
                      WHERE code = 'Clients/clients.php' 
                          OR code = 'clients' 
                          OR code LIKE '%clients.php%'
                          OR name LIKE '%عملاء%'
                      LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

// الحصول على صلاحيات المستخدم على هذه الوحدة
$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;

if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view = $perms['can_view'];
    $can_add = $perms['can_add'];
    $can_edit = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}

// منع الوصول إذا لم تكن هناك صلاحية عرض
if (!$can_view) {
    header("Location: ../index.php?msg=لا+توجد+صلاحية+عرض+العملاء+❌");
    exit();
}

// معالجة إضافة/تعديل عميل عبر POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['client_name'])) {
    // التحقق من صلاحية التعديل أو الإضافة
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $is_editing = $client_id > 0;
    
    if ($is_editing && !$can_edit) {
        header("Location: clients.php?msg=لا+توجد+صلاحية+تعديل+العملاء+❌");
        exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: clients.php?msg=لا+توجد+صلاحية+إضافة+عملاء+جدد+❌");
        exit();
    }

    $client_code = mysqli_real_escape_string($conn, trim($_POST['client_code']));
    $client_name = mysqli_real_escape_string($conn, trim($_POST['client_name']));
    $entity_type = mysqli_real_escape_string($conn, trim($_POST['entity_type']));
    $sector_category = mysqli_real_escape_string($conn, trim($_POST['sector_category']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $whatsapp = mysqli_real_escape_string($conn, trim($_POST['whatsapp']));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['user']['id'];

    if ($client_id > 0) {
        $check_query = "SELECT id FROM clients WHERE client_code = '$client_code' AND id != $client_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            header("Location: clients.php?msg=كود+العميل+موجود+مسبقاً❌");
            exit();
        }

        $update_query = "UPDATE clients SET 
            client_code = '$client_code',
            client_name = '$client_name',
            entity_type = '$entity_type',
            sector_category = '$sector_category',
            phone = '$phone',
            email = '$email',
            whatsapp = '$whatsapp',
            status = '$status'
            WHERE id = $client_id";
        
        if (mysqli_query($conn, $update_query)) {
            header("Location: clients.php?msg=تم+تعديل+العميل+بنجاح+✅");
            exit();
        } else {
            header("Location: clients.php?msg=حدث+خطأ+أثناء+التعديل+❌");
            exit();
        }
    } else {
        $check_query = "SELECT id FROM clients WHERE client_code = '$client_code'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            header("Location: clients.php?msg=كود+العميل+موجود+مسبقاً❌");
            exit();
        }

        $insert_query = "INSERT INTO clients 
            (client_code, client_name, entity_type, sector_category, phone, email, whatsapp, status, created_by) 
            VALUES 
            ('$client_code', '$client_name', '$entity_type', '$sector_category', '$phone', '$email', '$whatsapp', '$status', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            header("Location: clients.php?msg=تم+إضافة+العميل+بنجاح+✅");
            exit();
        } else {
            header("Location: clients.php?msg=حدث+خطأ+أثناء+الإضافة+❌");
            exit();
        }
    }
}

// معالجة حذف العميل
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // التحقق من صلاحية الحذف
    if (!$can_delete) {
        header("Location: clients.php?msg=لا+توجد+صلاحية+حذف+العملاء+❌");
        exit();
    }
    
    header("Location: clients.php?msg=تم+تعطيل+الحذف+مؤقتا❌");
    //************************************* ازل التعليق لتفعيل عملية الحذف ***************************** */
    // $check_usage = mysqli_query($conn, "SELECT COUNT(*) as count FROM operationproject WHERE company_client_id = $delete_id");
    // $usage = mysqli_fetch_assoc($check_usage);
    // if ($usage['count'] > 0) {
    //     header("Location: clients.php?msg=لا+يمكن+حذف+العميل+لأنه+مستخدم+في+مشاريع+موجودة+❌");
    //     exit();
    // } else {
    //     $delete_query = "DELETE FROM clients WHERE id = $delete_id";
    //     if (mysqli_query($conn, $delete_query)) {
    //         header("Location: clients.php?msg=تم+حذف+العميل+بنجاح+✅");
    //         exit();
    //     } else {
    //         header("Location: clients.php?msg=حدث+خطأ+أثناء+الحذف+❌");
    //         exit();
    //     }
    // }
}

$page_title = "قائمة العملاء";
include("../inheader.php");
include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<!-- Font Awesome من CDN لضمان ظهور الأيقونات بشكل صحيح -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<div class="main">
    <div class="page-header">
        <h1 class="page-title">
            <div class="title-icon"><i class="fas fa-users"></i></div>
            إدارة العملاء
        </h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <?php if ($can_add): ?>
                <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                    <i class="fas fa-plus-circle"></i> إضافة عميل جديد
                </a>
                <a href="javascript:void(0)" id="openImportModal" class="add-btn"
                    style="background:linear-gradient(135deg,#064e3b,#065f46);color:#fff;border-color:transparent;">
                    <i class="fas fa-file-excel"></i> استيراد من Excel
                </a>
            <?php else: ?>
                <button class="add-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                    <i class="fas fa-plus-circle"></i> إضافة (بدون صلاحيات)
                </button>
            <?php endif; ?>
            <a href="download_clients_template.php" class="add-btn"
                style="background:linear-gradient(135deg,var(--orange),#f59e0b);color:#fff;border-color:transparent;">
                <i class="fas fa-download"></i> تحميل نموذج Excel
            </a>
            <a href="download_clients_template_csv.php" class="add-btn"
                style="background:linear-gradient(135deg,var(--blue),#3b82f6);color:#fff;border-color:transparent;">
                <i class="fas fa-file-csv"></i> تحميل نموذج CSV
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

    <!-- فورم إضافة / تعديل عميل -->
    <form id="clientForm" action="" method="post" style="display:none; margin-bottom:20px;">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل عميل</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="client_id" id="client_id" value="">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-barcode"></i> كود العميل *</label>
                        <input type="text" name="client_code" id="client_code" placeholder="مثال: CL-001" required 
                               pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-user"></i> اسم العميل *</label>
                        <input type="text" name="client_name" id="client_name" placeholder="أدخل اسم العميل" required />
                    </div>
                    <div>
                        <label><i class="fas fa-building"></i> نوع الكيان</label>
                        <select name="entity_type" id="entity_type">
                            <option value="">-- اختر نوع الكيان --</option>
                            <option value="حكومي">حكومي</option>
                            <option value="خاص">خاص</option>
                            <option value="مختلط">مختلط</option>
                            <option value="دولي">دولي</option>
                            <option value="غير ربحي">غير ربحي</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> تصنيف القطاع</label>
                        <select name="sector_category" id="sector_category">
                            <option value="">-- اختر التصنيف --</option>
                            <option value="بنية تحتية">بنية تحتية</option>
                            <option value="نفط وغاز">نفط وغاز</option>
                            <option value="تعدين">تعدين</option>
                            <option value="زراعة">زراعة</option>
                            <option value="خدمات">خدمات</option>
                            <option value="تجارة">تجارة</option>
                            <option value="صناعة">صناعة</option>
                            <option value="طاقة">طاقة</option>
                            <option value="مياه وصرف صحي">مياه وصرف صحي</option>
                            <option value="نقل ومواصلات">نقل ومواصلات</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                        <input type="tel" name="phone" id="phone" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                        <input type="email" name="email" id="email" placeholder="example@company.com" />
                    </div>
                    <div>
                        <label><i class="fab fa-whatsapp"></i> واتساب</label>
                        <input type="tel" name="whatsapp" id="whatsapp" placeholder="مثال: +249123456789" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة العميل *</label>
                        <select name="status" id="status" required>
                            <option value="نشط" selected>نشط ✅</option>
                            <option value="متوقف">متوقف ⏸</option>
                        </select>
                    </div>            
                    <button type="submit">
                        <i class="fas fa-save"></i> حفظ العميل
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> جميع العملاء</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="clientsTable" class="display">
                    <thead>
                        <tr>
                            <th width="100"><i class="fas fa-barcode"></i> كود العميل</th>
                            <th><i class="fas fa-user"></i> اسم العميل</th>
                            <th><i class="fas fa-building"></i> نوع الكيان</th>
                            <th><i class="fas fa-industry"></i> تصنيف القطاع</th>
                            <th><i class="fas fa-phone"></i> الهاتف</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT cc.*, u.name as creator_name 
                                  FROM clients cc 
                                  LEFT JOIN users u ON cc.created_by = u.id 
                                  ORDER BY cc.id DESC";
                        $result = mysqli_query($conn, $query);
                        $counter = 1;

                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td><strong style='font-family:monospace;letter-spacing:.03em'>" . htmlspecialchars($row['client_code']) . "</strong></td>";
                            echo "<td><a class='client-name-link' href='../Projects/oprationprojects.php?client_id=" . urlencode($row['id']) . "'>" . htmlspecialchars($row['client_name']) . "</a></td>";
                            echo "<td>" . htmlspecialchars($row['entity_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['sector_category']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                            if ($row['status'] == 'نشط') {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> متوقف</span></td>";
                            }

                            echo "<td>
                                <div class='action-btns'>
                                    <a href='javascript:void(0)' 
                                       class='action-btn view viewClientBtn' 
                                       data-id='" . $row['id'] . "'
                                       data-code='" . htmlspecialchars($row['client_code']) . "'
                                       data-name='" . htmlspecialchars($row['client_name']) . "'
                                       data-entity='" . htmlspecialchars($row['entity_type']) . "'
                                       data-sector='" . htmlspecialchars($row['sector_category']) . "'
                                       data-phone='" . htmlspecialchars($row['phone']) . "'
                                       data-email='" . htmlspecialchars($row['email']) . "'
                                       data-whatsapp='" . htmlspecialchars($row['whatsapp']) . "'
                                       data-status='" . $row['status'] . "'
                                       data-created='" . htmlspecialchars($row['creator_name'] ?? 'غير محدد') . "'
                                       title='عرض التفاصيل'>
                                        <i class='fas fa-eye'></i>
                                    </a>";
                                    
                                    if ($can_edit) {
                                        echo "<a href='javascript:void(0)' 
                                           class='action-btn edit editClientBtn' 
                                           data-id='" . $row['id'] . "'
                                           data-code='" . htmlspecialchars($row['client_code']) . "'
                                           data-name='" . htmlspecialchars($row['client_name']) . "'
                                           data-entity='" . htmlspecialchars($row['entity_type']) . "'
                                           data-sector='" . htmlspecialchars($row['sector_category']) . "'
                                           data-phone='" . htmlspecialchars($row['phone']) . "'
                                           data-email='" . htmlspecialchars($row['email']) . "'
                                           data-whatsapp='" . htmlspecialchars($row['whatsapp']) . "'
                                           data-status='" . $row['status'] . "'
                                           title='تعديل'>
                                            <i class='fas fa-edit'></i>
                                        </a>";
                                    }
                                    
                                    if ($can_delete) {
                                        echo "<a href='?delete_id=" . $row['id'] . "' class='action-btn delete' 
                                           onclick='return confirm(\"هل أنت متأكد من حذف هذا العميل؟\")' title='حذف'>
                                            <i class='fas fa-trash-alt'></i>
                                        </a>";
                                    }
                                    
                                echo "</div>
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

<!-- Modal استيراد من Excel -->
<div id="importExcelModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h5><i class="fas fa-file-excel"></i> استيراد عملاء من Excel</h5>
            <button class="close-modal" onclick="closeImportModal()">&times;</button>
        </div>
        <form id="importExcelForm" enctype="multipart/form-data">
            <div class="modal-body">
                <div
                    style="background:var(--blue-soft);border:1px solid rgba(37,99,235,.18);padding:16px 18px;border-radius:var(--radius);margin-bottom:18px;">
                    <h6 style="color:var(--blue);font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-info-circle"></i> تعليمات الاستيراد:
                    </h6>
                    <ul style="color:var(--navy);line-height:2;margin:0;padding-right:20px;font-size:.82rem;">
                        <li>قم بتحميل نموذج Excel أو CSV أولاً</li>
                        <li>املأ البيانات حسب الأعمدة المحددة</li>
                        <li>كود العميل يجب أن يكون فريداً</li>
                        <li>الحقول المطلوبة: كود العميل، اسم العميل، الحالة</li>
                        <li>صيغة الملف المدعومة: .xlsx, .xls, .csv</li>
                        <li><strong>ملاحظة:</strong> إذا لم تكن مكتبة PhpSpreadsheet مثبتة، استخدم ملف CSV</li>
                    </ul>
                </div>

                <div class="form-group-modal">
                    <label><i class="fas fa-file-upload"></i> اختر ملف Excel أو CSV (.xlsx, .xls, .csv) *</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required
                        style="padding:14px;border:2px dashed rgba(22,163,74,.4);border-radius:var(--radius);background:rgba(22,163,74,.04);cursor:pointer;width:100%;transition:border-color var(--ease);">
                </div>

                <div id="importProgress" style="display: none; margin-top: 18px;">
                    <div style="background:var(--blue-soft);border-radius:var(--radius);padding:16px;text-align:center;border:1px solid rgba(37,99,235,.18);">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--blue);"></i>
                        <p style="margin:10px 0 0;color:var(--blue);font-weight:700;">جاري الاستيراد...</p>
                    </div>
                </div>

                <div id="importResult" style="display: none; margin-top: 18px;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-modal btn-modal-save"
                    style="background:linear-gradient(135deg,#064e3b,#059669)!important;">
                    <i class="fas fa-upload"></i> رفع واستيراد
                </button>
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeImportModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal عرض العميل -->
<div id="viewClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض بيانات العميل</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود العميل</div>
                    <div class="view-item-value" id="view_client_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user"></i> اسم العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-building"></i> نوع الكيان</div>
                    <div class="view-item-value" id="view_entity_type">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> تصنيف القطاع</div>
                    <div class="view-item-value" id="view_sector_category">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-phone"></i> الهاتف</div>
                    <div class="view-item-value" id="view_phone">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</div>
                    <div class="view-item-value" id="view_email">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fab fa-whatsapp"></i> واتساب</div>
                    <div class="view-item-value" id="view_whatsapp">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> الحالة</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-plus"></i> أضيف بواسطة</div>
                    <div class="view-item-value" id="view_created_by">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <?php if ($can_edit): ?>
                <button type="button" class="btn-modal btn-modal-save editClientBtn" id="viewEditBtn">
                    <i class="fas fa-edit"></i> تعديل البيانات
                </button>
            <?php endif; ?>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

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
    $(document).ready(function () {
        $('#clientsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });

    // Toggle Form (Show/Hide)
    $('#toggleForm').on('click', function () {
        $('#clientForm').slideToggle(400);
        // Reset form when opening
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm')[0].reset();
            $('#client_id').val('');
        }
    });

    // Edit Client - Load data into form
    $(document).on('click', '.editClientBtn', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status')
        };

        // Fill form with data
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // Show form if hidden
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });


    // View Client Modal
    $(document).on('click', '.viewClientBtn', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status'),
            created: $(this).data('created')
        };

        // ملء بيانات العرض
        $('#view_client_code').text(clientData.code || '-');
        $('#view_client_name').text(clientData.name || '-');
        $('#view_entity_type').text(clientData.entity || '-');
        $('#view_sector_category').text(clientData.sector || '-');
        $('#view_phone').text(clientData.phone || '-');
        $('#view_email').text(clientData.email || '-');
        $('#view_whatsapp').text(clientData.whatsapp || '-');

        // عرض الحالة بألوان
        let statusHtml = '';
        if (clientData.status === 'نشط') {
            statusHtml = '<span class="status-active"><i class="fas fa-check-circle"></i> نشط</span>';
        } else {
            statusHtml = '<span class="status-inactive"><i class="fas fa-times-circle"></i> متوقف</span>';
        }
        $('#view_status').html(statusHtml);

        $('#view_created_by').text(clientData.created || '-');

        // تحضير زر التعديل
        const editBtn = $('#viewEditBtn');
        editBtn.data('id', clientData.id);
        editBtn.data('code', clientData.code);
        editBtn.data('name', clientData.name);
        editBtn.data('entity', clientData.entity);
        editBtn.data('sector', clientData.sector);
        editBtn.data('phone', clientData.phone);
        editBtn.data('email', clientData.email);
        editBtn.data('whatsapp', clientData.whatsapp);
        editBtn.data('status', clientData.status);

        $('#viewClientModal').fadeIn(300);
    });

    // Close View Modal
    function closeViewModal() {
        $('#viewClientModal').fadeOut(300);
    }

    // Close modals when clicking outside
    $(window).on('click', function (e) {
        if (e.target.id === 'viewClientModal') {
            closeViewModal();
        }
    });

    // Close modal on ESC key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#viewClientModal').is(':visible')) {
            closeViewModal();
        }
    });

    // Edit from view modal - Load data into form
    $('#viewEditBtn').on('click', function () {
        const clientData = {
            id: $(this).data('id'),
            code: $(this).data('code'),
            name: $(this).data('name'),
            entity: $(this).data('entity'),
            sector: $(this).data('sector'),
            phone: $(this).data('phone'),
            email: $(this).data('email'),
            whatsapp: $(this).data('whatsapp'),
            status: $(this).data('status')
        };

        closeViewModal();

        // Fill form with data
        $('#client_id').val(clientData.id);
        $('#client_code').val(clientData.code);
        $('#client_name').val(clientData.name);
        $('#entity_type').val(clientData.entity);
        $('#sector_category').val(clientData.sector);
        $('#phone').val(clientData.phone);
        $('#email').val(clientData.email);
        $('#whatsapp').val(clientData.whatsapp);
        $('#status').val(clientData.status);

        // Show form if hidden
        if (!$('#clientForm').is(':visible')) {
            $('#clientForm').slideDown(400);
        }

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#clientForm').offset().top - 100
        }, 500);
    });
</script>

<script>
    // فتح Modal الاستيراد
    $('#openImportModal').on('click', function () {
        $('#importExcelModal').fadeIn(300);
    });

    // إغلاق Modal الاستيراد
    function closeImportModal() {
        $('#importExcelModal').fadeOut(300);
        $('#importExcelForm')[0].reset();
        $('#importProgress').hide();
        $('#importResult').hide();
    }

    // إغلاق عند الضغط خارج Modal
    $(window).on('click', function (e) {
        if (e.target.id === 'importExcelModal') {
            closeImportModal();
        }
    });

    // معالجة رفع ملف Excel
    $('#importExcelForm').on('submit', function (e) {
        e.preventDefault();

        const fileInput = $('#excel_file')[0];
        if (!fileInput.files.length) {
            alert('الرجاء اختيار ملف Excel');
            return;
        }

        const formData = new FormData();
        formData.append('excel_file', fileInput.files[0]);
        formData.append('action', 'import_excel');

        $('#importProgress').show();
        $('#importResult').hide();

        $.ajax({
            url: 'import_clients_excel.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                $('#importProgress').hide();

                let resultHtml = '<div style="padding:16px;border-radius:var(--radius);border:1.5px solid;';

                if (response.success) {
                    resultHtml += 'background:var(--green-soft);border-color:rgba(22,163,74,.22);color:var(--green)">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-check-circle"></i> تم الاستيراد بنجاح!</h6>';
                    resultHtml += '<p style="margin:4px 0;">✅ تم إضافة: <strong>' + response.added + '</strong> عميل</p>';
                    if (response.skipped > 0) {
                        resultHtml += '<p style="margin:4px 0;color:#854d0e;">⚠️ تم تخطي: <strong>' + response.skipped + '</strong> عميل (مكرر)</p>';
                    }
                    if (response.errors.length > 0) {
                        resultHtml += '<p style="margin:8px 0 4px;"><strong>الأخطاء:</strong></p><ul style="margin:0;padding-right:20px;">';
                        response.errors.forEach(function (error) {
                            resultHtml += '<li>' + error + '</li>';
                        });
                        resultHtml += '</ul>';
                    }
                    resultHtml += '</div>';
                    setTimeout(function () { location.reload(); }, 3000);
                } else {
                    resultHtml += 'background:var(--red-soft);border-color:rgba(220,38,38,.22);color:var(--red)">';
                    resultHtml += '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> فشل الاستيراد</h6>';
                    resultHtml += '<p style="margin:0;">' + response.message + '</p>';
                    resultHtml += '</div>';
                }

                $('#importResult').html(resultHtml).fadeIn(300);
            },
            error: function (xhr, status, error) {
                $('#importProgress').hide();

                let errorMsg = 'حدث خطأ أثناء رفع الملف. الرجاء المحاولة مرة أخرى.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) { errorMsg = response.message; }
                    } catch (e) {
                        errorMsg += '<br><small>تفاصيل الخطأ: ' + status + '</small>';
                    }
                }

                const errorHtml = '<div style="padding:16px;border-radius:var(--radius);background:var(--red-soft);color:var(--red);border:1.5px solid rgba(220,38,38,.22);">' +
                    '<h6 style="font-weight:700;margin-bottom:8px;"><i class="fas fa-times-circle"></i> حدث خطأ</h6>' +
                    '<p style="margin:0;">' + errorMsg + '</p>' +
                    '<p style="margin:10px 0 4px;"><strong>نصائح:</strong></p>' +
                    '<ul style="font-size:.8rem;margin:0;padding-right:20px;">' +
                    '<li>تأكد من أن الملف بصيغة .xlsx, .xls أو .csv</li>' +
                    '<li>تأكد من أن حجم الملف أقل من 5 ميجا</li>' +
                    '<li>تأكد من أن الملف يحتوي على بيانات صحيحة</li>' +
                    '<li>إذا كنت تستخدم Excel، جرب حفظ الملف كـ CSV</li>' +
                    '</ul></div>';
                $('#importResult').html(errorHtml).fadeIn(300);
            }
        });
    });
</script>

</body>
</html>