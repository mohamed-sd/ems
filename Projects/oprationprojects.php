<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';

// معالجة حذف المشروع
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_query = "DELETE FROM project WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_query)) {
        header("Location: oprationprojects.php?msg=تم+حذف+المشروع+بنجاح+✅");
        exit();
    } else {
        header("Location: oprationprojects.php?msg=حدث+خطأ+أثناء+الحذف+❌");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['project_name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $company_client_id = !empty($_POST['company_client_id']) ? intval($_POST['company_client_id']) : 0;

    // جلب البيانات المدخولة يدويًا
    $name = mysqli_real_escape_string($conn, $_POST['project_name']);
    $project_code = mysqli_real_escape_string($conn, $_POST['project_code'] ?? '');
    $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
    $sub_sector = mysqli_real_escape_string($conn, $_POST['sub_sector'] ?? '');
    $state = mysqli_real_escape_string($conn, $_POST['state'] ?? '');
    $region = mysqli_real_escape_string($conn, $_POST['region'] ?? '');
    $nearest_market = mysqli_real_escape_string($conn, $_POST['nearest_market'] ?? '');
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude'] ?? '');
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude'] ?? '');
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');

    // جلب اسم العميل إذا تم اختياره
    $client = '';
    if ($company_client_id > 0) {
        $client_data = mysqli_query($conn, "SELECT client_name FROM clients WHERE id = $company_client_id");
        if ($client_row = mysqli_fetch_assoc($client_data)) {
            $client = mysqli_real_escape_string($conn, $client_row['client_name']);
        }
    } else {
        $client = mysqli_real_escape_string($conn, $_POST['client_name'] ?? '');
    }

    $total = floatval($_POST['total'] ?? 0);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $created_by = $_SESSION['user']['id'] ?? 1;
    $date = date('Y-m-d H:i:s');

    if ($id > 0) {
        // تحديث
        $sql = "UPDATE project SET 
            company_client_id='$company_client_id',
            name='$name',
            client='$client',
            location='$location',
            project_code='$project_code',
            category='$category',
            sub_sector='$sub_sector',
            state='$state',
            region='$region',
            nearest_market='$nearest_market',
            latitude='$latitude',
            longitude='$longitude',
            total='$total',
            status='$status',
            updated_at=NOW()
        WHERE id=$id";
        mysqli_query($conn, $sql);

        header("Location: oprationprojects.php?msg=تم+تعديل+المشروع+بنجاح+✅");
        exit;
    } else {
        // إضافة
        $sql = "INSERT INTO project (company_client_id, name, client, location, project_code, category, sub_sector, state, region, nearest_market, latitude, longitude, total, status, created_by, create_at) 
        VALUES ('$company_client_id', '$name', '$client', '$location', '$project_code', '$category', '$sub_sector', '$state', '$region', '$nearest_market', '$latitude', '$longitude', '$total', '$status', '$created_by', '$date')";
        mysqli_query($conn, $sql);
        header("Location: oprationprojects.php?msg=تم+اضافه+المشروع+بنجاح+✅");
        exit;
    }
}
?>


<?php
$page_title = "إيكوبيشن | المشاريع";
include("../inheader.php");
// include('../insidebar.php');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<div class="main">
    <div class="page-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="title-icon"><i class="fas fa-project-diagram"></i></div>
            <h1 class="page-title">إدارة المشاريع</h1>
        </div>
        <div>
            <a href="../main/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add">
                <i class="fas fa-plus-circle"></i> إضافة مشروع
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>



    <!-- فورم إضافة / تعديل مشروع -->
    <form id="projectForm" action="" method="post" style="display:none;">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> إضافة / تعديل مشروع</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="id" id="project_id" value="">
                <div class="form-grid">
                    <div>
                        <label><i class="fas fa-user-tie"></i> اسم العميل (اختياري)</label>
                        <select name="company_client_id" id="company_client_id" required>
                            <option value="">-- اختر العميل  --</option>
                            <?php
                            $clients_query = mysqli_query($conn, "SELECT id, client_code, client_name FROM clients WHERE status = 'نشط' ORDER BY client_name ASC");
                            while ($cli = mysqli_fetch_assoc($clients_query)) {
                                echo "<option value='" . $cli['id'] . "'>[" . $cli['client_code'] . "] " . $cli['client_name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-barcode"></i> كود المشروع</label>
                        <input type="text" name="project_code" placeholder="كود المشروع" id="project_code" />
                    </div>
                    <div>
                        <label><i class="fas fa-file-signature"></i> اسم المشروع</label>
                        <input type="text" name="project_name" id="project_name" placeholder="أدخل اسم المشروع"
                            required />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker-alt"></i> موقع المشروع</label>
                        <input type="text" name="location" placeholder="أدخل موقع المشروع" id="project_location" />
                        <input type="hidden" name="total" value="0" />
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> الفئة</label>
                        <input type="text" name="category" placeholder="الفئة" id="project_category" />
                    </div>
                    <div>
                        <label><i class="fas fa-industry"></i> القطاع الفرعي</label>
                        <input type="text" name="sub_sector" placeholder="القطاع الفرعي" id="project_sub_sector" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marked-alt"></i> الولاية</label>
                        <input type="text" name="state" placeholder="الولاية" id="project_state" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-pin"></i> المنطقة</label>
                        <input type="text" name="region" placeholder="المنطقة" id="project_region" />
                    </div>
                    <div>
                        <label><i class="fas fa-store"></i> أقرب سوق</label>
                        <input type="text" name="nearest_market" placeholder="أقرب سوق" id="project_nearest_market" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> خط العرض</label>
                        <input type="text" name="latitude" placeholder="خط العرض" id="project_latitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-map-marker"></i> خط الطول</label>
                        <input type="text" name="longitude" placeholder="خط الطول" id="project_longitude" />
                    </div>
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة المشروع</label>
                        <select name="status" id="project_status" required>
                            <option value="">-- اختر الحالة --</option>
                            <option value="1">✅ نشط</option>
                            <option value="0">❌ غير نشط</option>
                        </select>
                    </div>
                    <button type="submit">
                        <i class="fas fa-save"></i> <span>حفظ المشروع</span>
                    </button> المشروع
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المشاريع -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0;"><i class="fas fa-list"></i> قائمة المشاريع</h5>

                <?php

                if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                    $client_id = intval($_GET['client_id']);
                    $client_result = mysqli_query($conn, "SELECT client_name FROM clients WHERE id = $client_id");
                    if ($client_row = mysqli_fetch_assoc($client_result)) {
                        echo "للعميل: <strong>" . htmlspecialchars($client_row['client_name']) . "</strong>";
                    }
                }

                ?>

            </h5>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-sm btn-success" id="exportBtn" title="تحميل النموذج">
                    <i class="fas fa-download"></i> تحميل النموذج
                </button>
                <button class="btn btn-sm btn-info" id="importBtn" title="استيراد ملف">
                    <i class="fas fa-upload"></i> استيراد من Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display" style="width:100%;">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> تاريخ الإضافة</th>
                            <th><i class="fas fa-user-tie"></i> العميل</th>
                            <th><i class="fas fa-file-contract"></i> كود المشروع</th>
                            <th><i class="fas fa-project-diagram"></i> المشروع</th>
                            <th><i class="fas fa-truck"></i> عدد الموردين</th>
                            <th><i class="fas fa-toggle-on"></i> الحالة</th>
                            <!-- <th><i class="fas fa-file-contract"></i> عقود المشروع</th> -->
                            <th> المناجم</th>
                            <th><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        include '../config.php';

                        $client_filter = "";

                        if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
                            $client_id = intval($_GET['client_id']);
                            $client_filter = " WHERE op.company_client_id = $client_id ";
                        }

                        // جلب جميع المشاريع من جدول project مع البيانات المدخولة يدويًا
                        $query = "SELECT op.`id`, op.`name`, op.`client`, op.`location`, op.`total`, op.`status`, op.`create_at`, 
                      op.`project_code`, op.`category`, op.`sub_sector`, op.`state`, op.`region`, 
                      op.`nearest_market`, op.`latitude`, op.`longitude`, op.`company_client_id`,
                      cc.`client_name`,
                      (SELECT COUNT(*) 
                       FROM contracts c 
                       INNER JOIN mines m ON c.mine_id = m.id 
                       WHERE m.project_id = op.id) as 'contracts',
                      (SELECT COUNT(DISTINCT pm.suppliers) 
                          FROM equipments pm
                          JOIN operations m ON pm.id = m.equipment
                          WHERE m.project_id = op.id) as 'total_suppliers',
                          (SELECT COUNT(*) FROM mines WHERE project_id = op.id) as mines_count
                      FROM project op
                      LEFT JOIN clients cc ON op.company_client_id = cc.id
                      $client_filter
                      ORDER BY op.id DESC";

                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $row['create_at'] . "</td>";
                            echo "<td>" . ($row['client_name'] ?? $row['client']) . "</td>";
                            echo "<td>" . ($row['project_code'] ?? '-') . "</td>";
                            echo "<td><strong>" . $row['name'] . "</strong></td>";
                            echo "<td><span class='count-badge'>" . $row['total_suppliers'] . "</span></td>";
                            if ($row['status'] == "1") {
                                echo "<td><span class='status-active'><i class='fas fa-check-circle'></i> نشط</span></td>";
                            } else {
                                echo "<td><span class='status-inactive'><i class='fas fa-times-circle'></i> غير نشط</span></td>";
                            }

                            echo "<td>
                           

                             <a href='project_mines.php?project_id=" . $row['id'] . "' 
                                       class='mines-count-link' 
                                       title='عرض المناجم'>
                                        <i class='fas fa-mountain'></i>
                                        <span class='mines-count-badge'>" . $row['mines_count'] . "</span>
                             </a>

                        </td>";

                            echo "<td>
                            <div class='action-btns'>
                                <a href='javascript:void(0)' 
                                   class='action-btn view viewBtn' 
                                   data-id='" . $row['id'] . "' 
                                   data-project-name='" . htmlspecialchars($row['name']) . "' 
                                   data-client-name='" . htmlspecialchars($row['client_name'] ?? $row['client']) . "' 
                                   data-location='" . htmlspecialchars($row['location']) . "' 
                                   data-project-code='" . htmlspecialchars($row['project_code'] ?? '') . "' 
                                   data-category='" . htmlspecialchars($row['category'] ?? '') . "' 
                                   data-sub-sector='" . htmlspecialchars($row['sub_sector'] ?? '') . "' 
                                   data-state='" . htmlspecialchars($row['state'] ?? '') . "' 
                                   data-region='" . htmlspecialchars($row['region'] ?? '') . "' 
                                   data-nearest-market='" . htmlspecialchars($row['nearest_market'] ?? '') . "' 
                                   data-latitude='" . htmlspecialchars($row['latitude'] ?? '') . "' 
                                   data-longitude='" . htmlspecialchars($row['longitude'] ?? '') . "' 
                                   data-status='" . $row['status'] . "' 
                                   data-contracts='" . $row['contracts'] . "' 
                                   data-suppliers='" . $row['total_suppliers'] . "'
                                   title='عرض التفاصيل'>
                                   <i class='fas fa-eye'></i>
                                </a>
                                <a href='javascript:void(0)' 
                                   class='action-btn edit editBtn' 
                                   data-id='" . $row['id'] . "' 
                                   data-company-client-id='" . ($row['company_client_id'] ?? '') . "' 
                                   data-project-name='" . htmlspecialchars($row['name']) . "' 
                                   data-location='" . htmlspecialchars($row['location']) . "' 
                                   data-project-code='" . htmlspecialchars($row['project_code'] ?? '') . "' 
                                   data-category='" . htmlspecialchars($row['category'] ?? '') . "' 
                                   data-sub-sector='" . htmlspecialchars($row['sub_sector'] ?? '') . "' 
                                   data-state='" . htmlspecialchars($row['state'] ?? '') . "' 
                                   data-region='" . htmlspecialchars($row['region'] ?? '') . "' 
                                   data-nearest-market='" . htmlspecialchars($row['nearest_market'] ?? '') . "' 
                                   data-latitude='" . htmlspecialchars($row['latitude'] ?? '') . "' 
                                   data-longitude='" . htmlspecialchars($row['longitude'] ?? '') . "' 
                                   data-status='" . $row['status'] . "'
                                   title='تعديل'>
                                   <i class='fas fa-edit'></i>
                                </a>
                                <a href='oprationprojects.php?delete_id=" . $row['id'] . "' 
                                   class='action-btn delete' 
                                   onclick='return confirm(\"هل أنت متأكد من حذف هذا المشروع؟\")'
                                   title='حذف'>
                                   <i class='fas fa-trash-alt'></i>
                                </a>
                              
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

<!-- Modal عرض تفاصيل المشروع -->
<div id="viewProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye"></i> عرض تفاصيل المشروع</h5>
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-modal-body">
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-user-tie"></i> اسم العميل</div>
                    <div class="view-item-value" id="view_client_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-barcode"></i> كود المشروع</div>
                    <div class="view-item-value" id="view_project_code">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-project-diagram"></i> اسم المشروع</div>
                    <div class="view-item-value" id="view_project_name">-</div>
                </div>
                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-layer-group"></i> الفئة</div>
                    <div class="view-item-value" id="view_category">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-industry"></i> القطاع الفرعي</div>
                    <div class="view-item-value" id="view_sub_sector">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marked-alt"></i> الولاية</div>
                    <div class="view-item-value" id="view_state">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-pin"></i> المنطقة</div>
                    <div class="view-item-value" id="view_region">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker-alt"></i> موقع المشروع</div>
                    <div class="view-item-value" id="view_location">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-store"></i> أقرب سوق</div>
                    <div class="view-item-value" id="view_nearest_market">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-map-marker"></i> الإحداثيات (خط العرض / خط الطول)
                    </div>
                    <div class="view-item-value" id="view_coordinates">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-file-contract"></i> عدد العقود</div>
                    <div class="view-item-value" id="view_contracts">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-truck"></i> عدد الموردين</div>
                    <div class="view-item-value" id="view_suppliers">-</div>
                </div>

                <div class="view-item">
                    <div class="view-item-label"><i class="fas fa-toggle-on"></i> حالة المشروع</div>
                    <div class="view-item-value" id="view_status">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a id="viewMinesBtn" class="btn-modal btn-modal-save" style="text-decoration: none;">
                <i class="fas fa-mountain"></i> مناجم المشروع
            </a>
            <button type="button" class="btn-modal btn-modal-save editBtn" id="viewEditBtn">
                <i class="fas fa-edit"></i> تعديل المشروع
            </button>
            <button type="button" class="btn-modal btn-modal-cancel" onclick="closeViewModal()">
                <i class="fas fa-times"></i> إغلاق
            </button>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap JS (Bundle includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    // إغلاق Modal عرض المشروع - تعريف عام
    function closeViewModal() {
        $('#viewProjectModal').fadeOut(300);
    }

    (function () {
        // تشغيل DataTable
        $(document).ready(function () {
            $('#projectsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    { extend: 'copy', text: 'نسخ (Copy)' },
                    { extend: 'excel', text: 'تصدير Excel' },
                    { extend: 'csv', text: 'تصدير CSV' },
                    { extend: 'pdf', text: 'تصدير PDF' },
                    { extend: 'print', text: 'طباعة (Print)' }
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                }
            });
        });

        // اظهار/اخفاء الفورم
        const toggleProjectFormBtn = document.getElementById('toggleForm');
        const projectForm = document.getElementById('projectForm');
        toggleProjectFormBtn.addEventListener('click', function () {
            projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
            // تنظيف الحقول عند الإضافة
            $("#project_id").val("");
            $("#project_name").val("");
            $("#company_client_id").val("");
            $("#project_location").val("");
            $("#project_code").val("");
            $("#project_category").val("");
            $("#project_sub_sector").val("");
            $("#project_state").val("");
            $("#project_region").val("");
            $("#project_nearest_market").val("");
            $("#project_latitude").val("");
            $("#project_longitude").val("");
            $("#project_status").val("");
        });

        // عرض Modal عند الضغط على زر العرض
        $(document).on("click", ".viewBtn", function () {
            const projectData = {
                id: $(this).data('id'),
                projectName: $(this).data('project-name'),
                clientName: $(this).data('client-name'),
                location: $(this).data('location'),
                projectCode: $(this).data('project-code'),
                category: $(this).data('category'),
                subSector: $(this).data('sub-sector'),
                state: $(this).data('state'),
                region: $(this).data('region'),
                nearestMarket: $(this).data('nearest-market'),
                latitude: $(this).data('latitude'),
                longitude: $(this).data('longitude'),
                status: $(this).data('status'),
                contracts: $(this).data('contracts'),
                suppliers: $(this).data('suppliers')
            };

            // ملء بيانات العرض
            $('#view_project_name').text(projectData.projectName || '-');
            $('#view_client_name').text(projectData.clientName || '-');
            $('#view_project_code').text(projectData.projectCode || '-');
            $('#view_category').text(projectData.category || '-');
            $('#view_sub_sector').text(projectData.subSector || '-');
            $('#view_state').text(projectData.state || '-');
            $('#view_region').text(projectData.region || '-');
            $('#view_location').text(projectData.location || '-');
            $('#view_nearest_market').text(projectData.nearestMarket || '-');

            // عرض الإحداثيات
            let coordsText = '-';
            if (projectData.latitude && projectData.longitude) {
                coordsText = projectData.latitude + ' / ' + projectData.longitude;
            }
            $('#view_coordinates').text(coordsText);

            $('#view_contracts').text(projectData.contracts || '0');
            $('#view_suppliers').text(projectData.suppliers || '0');

            // عرض الحالة بألوان
            let statusHtml = '<span style="padding: 4px 12px; border-radius: 20px; color: white;';
            if (projectData.status === '1' || projectData.status === 1) {
                statusHtml += ' background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);';
            } else {
                statusHtml += ' background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);';
            }
            statusHtml += ' display: inline-block;">';
            statusHtml += '<i class="fas fa-circle" style="margin-left: 6px; font-size: 8px;"></i> ' + (projectData.status === '1' || projectData.status === 1 ? 'نشط' : 'غير نشط') + '</span>';
            $('#view_status').html(statusHtml);

            // تحضير زر التعديل
            const editBtn = $('#viewEditBtn');
            editBtn.data('id', projectData.id);
            editBtn.data('company-project-id', $(this).data('company-project-id'));
            editBtn.data('company-client-id', $(this).data('company-client-id'));
            editBtn.data('name', $(this).data('name'));
            editBtn.data('location', projectData.location);
            editBtn.data('status', projectData.status);

            // تحضير زر مناجم المشروع
            $('#viewMinesBtn').attr('href', 'project_mines.php?project_id=' + projectData.id);

            $('#viewProjectModal').fadeIn(300);
        });

        // إغلاق عند الضغط خارج Modal
        $(window).on('click', function (e) {
            if (e.target.id === 'viewProjectModal') {
                closeViewModal();
            }
        });

        // إغلاق عند الضغط على ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#viewProjectModal').is(':visible')) {
                closeViewModal();
            }
        });

        // التعامل مع زر التعديل من Modal العرض
        $('#viewEditBtn').on('click', function () {
            $("#project_id").val($(this).data('id'));
            $("#company_project_id").val($(this).data('company-project-id'));
            $("#company_client_id").val($(this).data('company-client-id'));
            $("#project_location").val($(this).data('location'));
            $("#project_status").val($(this).data('status'));

            closeViewModal();
            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند الضغط على زر تعديل من الجدول
        $(document).on("click", ".editBtn:not(#viewEditBtn)", function () {
            $("#project_id").val($(this).data("id"));
            $("#project_name").val($(this).data("project-name"));
            $("#company_client_id").val($(this).data("company-client-id"));
            $("#project_location").val($(this).data("location"));
            $("#project_code").val($(this).data("project-code"));
            $("#project_category").val($(this).data("category"));
            $("#project_sub_sector").val($(this).data("sub-sector"));
            $("#project_state").val($(this).data("state"));
            $("#project_region").val($(this).data("region"));
            $("#project_nearest_market").val($(this).data("nearest-market"));
            $("#project_latitude").val($(this).data("latitude"));
            $("#project_longitude").val($(this).data("longitude"));
            $("#project_status").val($(this).data("status"));

            $("#projectForm").show();
            $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
        });

        // عند تمرير رقم العميل قي ال url
        $(document).ready(function () {
            // إذا تم تمرير client_id في الرابط، افتح الفورم تلقائيًا
            const urlParams = new URLSearchParams(window.location.search);
            const clientId = urlParams.get('client_id');

            if (clientId) {
                $('#projectForm').show();
                $('#company_client_id').val(clientId);
            }
        });

        // ===== معالجات الاستيراد والتصدير =====
        
        // زر تحميل النموذج
        $('#exportBtn').on('click', function() {
            window.location.href = 'download_projects_template.php';
        });

        // زر الاستيراد من Excel
        $('#importBtn').on('click', function() {
            $('#importModal').modal('show');
        });

        // معالج رفع الملف
        $('#importFileForm').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#projectFile')[0];
            if (!fileInput.files.length) {
                alert('يرجى اختيار ملف');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            $.ajax({
                url: 'import_projects_excel.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('تم استيراد ' + response.imported_count + ' مشروع بنجاح!');
                        $('#importModal').modal('hide');
                        $('#projectsTable').DataTable().ajax.reload();
                        location.reload(); // إعادة تحميل الصفحة لتحديث الجدول
                    } else {
                        let errorMsg = 'حدث خطأ أثناء الاستيراد:\n\n';
                        if (response.errors && response.errors.length > 0) {
                            response.errors.forEach(function(error) {
                                errorMsg += error + '\n';
                            });
                        }
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    alert('حدث خطأ في الاتصال: ' + error);
                }
            });
        });

    })();
</script>

<!-- Modal لاستيراد الملفات -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel"><i class="fas fa-upload"></i> استيراد المشاريع من ملف Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="importFileForm">
                    <div class="form-group">
                        <label for="projectFile">اختر ملف Excel:</label>
                        <input type="file" class="form-control" id="projectFile" name="file" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">الملفات المقبولة: Excel (.xlsx, .xls)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="submit" form="importFileForm" class="btn btn-primary">
                    <i class="fas fa-upload"></i> استيراد
                </button>
            </div>
        </div>
    </div>
</div>

</body>

</html>