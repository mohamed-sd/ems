<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

include '../config.php';
$equipment_id = intval($_GET['equipment_id']);

// جلب معلومات المعدة
$equipment_query = "SELECT e.*, s.name as supplier_name 
                    FROM equipments e 
                    LEFT JOIN suppliers s ON e.suppliers = s.id 
                    WHERE e.id = $equipment_id";
$equipment_result = mysqli_query($conn, $equipment_query);
$equipment = mysqli_fetch_assoc($equipment_result);

// جلب المشغلين المرتبطين مسبقًا
$current = [];
$linked = [];
$res = mysqli_query($conn, "SELECT ed.id, ed.start_date, ed.end_date, d.id AS driver_id, d.name, d.phone, ed.status
                             FROM equipment_drivers ed
                             JOIN drivers d ON ed.driver_id = d.id
                             WHERE ed.equipment_id = $equipment_id");
while ($r = mysqli_fetch_assoc($res)) {
    $current[] = $r['driver_id'];
    $linked[] = $r;
}

$page_title = "إيكوبيشن | إدارة مشغلي المعدة";
include("../inheader.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

<style>
/* استخدام نفس نظام الألوان الموحد للنظام */
:root {
    --navy:        #0c1c3e;
    --navy-m:      #132050;
    --navy-l:      #1b2f6e;
    --gold:        #e8b800;
    --gold-l:      #ffd740;
    --gold-soft:   rgba(232,184,0,.13);
    --bg:          #f0f2f8;
    --surface:     #ffffff;
    --border:      rgba(12,28,62,.07);
    --txt:         #0c1c3e;
    --sub:         #64748b;
    --green:       #16a34a;
    --green-soft:  rgba(22,163,74,.11);
    --red:         #dc2626;
    --red-soft:    rgba(220,38,38,.10);
    --blue:        #2563eb;
    --blue-soft:   rgba(37,99,235,.10);
    --orange:      #ea6f00;
    --orange-soft: rgba(234,111,0,.10);
    --shadow-sm:   0 1px 5px rgba(12,28,62,.06);
    --shadow-md:   0 5px 20px rgba(12,28,62,.09);
    --shadow-lg:   0 14px 44px rgba(12,28,62,.13);
    --radius:      12px;
    --radius-lg:   18px;
    --ease:        .22s cubic-bezier(.4,0,.2,1);
}

body {
    font-family: 'Cairo', sans-serif;
    background: var(--bg);
}

.main {
    padding: 20px;
    transition: all 0.4s ease;
    width: 100%;
    background: var(--bg);
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
    animation: slideDown 0.5s cubic-bezier(.4,0,.2,1);
}

@keyframes slideDown {
    from { opacity:0; transform:translateY(-12px); }
    to   { opacity:1; transform:translateY(0); }
}

.page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.35rem;
    font-weight: 900;
    color: var(--txt);
    margin: 0;
    font-family: 'Cairo', sans-serif;
}

.title-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    color: var(--gold);
    box-shadow: var(--shadow-md);
    flex-shrink: 0;
}

.equipment-info {
    background: var(--gold-soft);
    padding: 12px 20px;
    border-radius: var(--radius);
    border: 1.5px solid rgba(232,184,0,.28);
}

.equipment-info h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--navy);
}

.equipment-info p {
    margin: 5px 0 0;
    font-size: 0.88rem;
    color: var(--sub);
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 50px;
    font-weight: 700;
    font-size: .82rem;
    transition: all var(--ease);
    border: 1.5px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    font-family: 'Cairo', sans-serif;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-primary,
.btn-success {
    background: var(--gold-soft);
    color: var(--navy);
    border-color: rgba(232,184,0,.28);
}

.btn-primary:hover,
.btn-success:hover {
    background: var(--gold);
    color: var(--navy);
    box-shadow: 0 5px 16px rgba(232,184,0,.35);
}

.btn-secondary {
    background: var(--blue-soft);
    color: var(--blue);
    border-color: rgba(37,99,235,.18);
}

.btn-secondary:hover {
    background: var(--blue);
    color: white;
    box-shadow: 0 5px 16px rgba(37,99,235,.35);
}

.btn-danger {
    background: var(--red-soft);
    color: var(--red);
    border-color: rgba(220,38,38,.18);
}

.btn-danger:hover {
    background: var(--red);
    color: white;
    box-shadow: 0 5px 16px rgba(220,38,38,.35);
}

.card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    margin-bottom: 22px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.card-header {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: white;
    padding: 16px 22px;
    font-weight: 700;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.card-body {
    padding: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 700;
    color: var(--txt);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
}

.form-group input,
.form-group select {
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.92rem;
    font-family: 'Cairo', sans-serif;
    transition: all var(--ease);
    background: var(--surface);
    color: var(--txt);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-soft);
}

.form-group select[multiple] {
    min-height: 200px;
    padding: 10px;
}

.form-group select[multiple] option {
    padding: 10px;
    margin: 3px 0;
    border-radius: var(--radius);
    cursor: pointer;
}

.form-group select[multiple] option:hover {
    background: var(--navy);
    color: var(--gold);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1.5px solid var(--border);
}

.alert {
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    animation: slideDown 0.4s ease;
}

.alert-info {
    background: var(--blue-soft);
    color: var(--blue);
    border: 1.5px solid rgba(37,99,235,.25);
}

.alert-success {
    background: var(--green-soft);
    color: var(--green);
    border: 1.5px solid rgba(22,163,74,.25);
}

.table-container {
    overflow-x: auto;
}

table.dataTable {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0;
}

table.dataTable thead th {
    background: linear-gradient(135deg, var(--navy), var(--navy-l));
    color: white;
    padding: 14px;
    font-weight: 700;
    text-align: center;
    border: none;
    font-size: 0.9rem;
}

 table.dataTable tbody td {
    padding: 14px;
    text-align: center;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--txt);
}

table.dataTable tbody tr:hover {
    background: var(--gold-soft);
    transition: all var(--ease);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
}

.status-active {
    background: var(--green-soft);
    color: var(--green);
    border: 1.5px solid rgba(22,163,74,.22);
}

.status-inactive {
    background: var(--red-soft);
    color: var(--red);
    border: 1.5px solid rgba(220,38,38,.22);
}

.action-btns {
    display: flex;
    gap: 7px;
    justify-content: center;
    align-items: center;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: var(--radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all var(--ease);
    font-size: 0.9rem;
    border: 1.5px solid transparent;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.action-btn.edit {
    background: var(--blue-soft);
    color: var(--blue);
    border-color: rgba(37,99,235,.2);
}

.action-btn.edit:hover {
    background: var(--blue);
    color: white;
}

.action-btn.delete {
    background: var(--red-soft);
    color: var(--red);
    border-color: rgba(220,38,38,.2);
}

.action-btn.delete:hover {
    background: var(--red);
    color: white;
}

.action-btn.activate {
    background: var(--green-soft);
    color: var(--green);
    border-color: rgba(22,163,74,.2);
}

.action-btn.activate:hover {
    background: var(--green);
    color: white;
}

.dt-buttons {
    margin-bottom: 14px;
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.dt-button {
    background: linear-gradient(135deg, var(--navy), var(--navy-l)) !important;
    color: white !important;
    border: 1.5px solid rgba(232,184,0,.28) !important;
    padding: 8px 16px !important;
    border-radius: 50px !important;
    font-family: 'Cairo', sans-serif !important;
    font-weight: 700 !important;
    font-size: 0.82rem !important;
    cursor: pointer !important;
    transition: all var(--ease) !important;
}

.dt-button:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md) !important;
    background: linear-gradient(135deg, var(--navy-l), var(--navy)) !important;
    border-color: var(--gold) !important;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--sub);
}

.empty-state i {
    font-size: 3.5rem;
    margin-bottom: 18px;
    color: var(--gold);
    opacity: 0.4;
}

.empty-state h3 {
    font-size: 1.3rem;
    margin-bottom: 10px;
    color: var(--txt);
    font-weight: 700;
}

.empty-state p {
    font-size: 0.95rem;
    margin-bottom: 20px;
    color: var(--sub);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        text-align: center;
        align-items: stretch;
    }
    
    .btn-group {
        width: 100%;
        justify-content: center;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .equipment-info {
        text-align: center;
    }
    
    .page-title {
        justify-content: center;
    }
}
</style>

<?php include('../insidebar.php'); ?>

<div class="main">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <div class="title-icon"><i class="fas fa-users-cog"></i></div>
                إدارة مشغلي المعدة
            </h1>
            <?php if ($equipment): ?>
            <div class="equipment-info">
                <h3><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($equipment['name']); ?></h3>
                <p><i class="fas fa-barcode"></i> الكود: <strong><?php echo htmlspecialchars($equipment['code']); ?></strong> | 
                   <i class="fas fa-building"></i> المورد: <strong><?php echo htmlspecialchars($equipment['supplier_name'] ?: 'غير محدد'); ?></strong></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <a href="equipments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="btn btn-success">
                <i class="fas fa-user-plus"></i> إسناد مشغل جديد
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة مشغل -->
    <div class="card" id="projectForm" style="display: none;">
        <div class="card-header">
            <i class="fas fa-user-plus"></i> إسناد مشغل جديد للمعدة
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>ملاحظة:</strong> يمكنك اختيار أكثر من مشغل بالضغط على Ctrl (أو Cmd في Mac) أثناء التحديد.
                    <br>المشغلون المعروضون هم الذين لم يتم إسنادهم لأي معدة نشطة حالياً.
                </div>
            </div>
            
            <form method="POST" action="save_equipment_drivers.php">
                <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-check"></i> تاريخ بداية القيادة <span style="color: red;">*</span>
                        </label>
                        <input type="date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-times"></i> تاريخ نهاية القيادة (اختياري)
                        </label>
                        <input type="date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-users"></i> اختر المشغلين <span style="color: red;">*</span>
                    </label>
                    <select name="drivers[]" multiple required>
                        <?php
                        $drivers = mysqli_query($conn, "SELECT d.id, d.name, d.phone
                                                        FROM drivers d
                                                        WHERE d.id NOT IN (
                                                            SELECT driver_id 
                                                            FROM equipment_drivers 
                                                            WHERE status = 1
                                                        ) AND d.status = 1
                                                        ORDER BY d.name");
                        
                        if (mysqli_num_rows($drivers) > 0) {
                            while ($d = mysqli_fetch_assoc($drivers)) {
                                $driverInfo = htmlspecialchars($d['name']);
                                if ($d['phone']) {
                                    $driverInfo .= " - " . htmlspecialchars($d['phone']);
                                }
                                echo "<option value='{$d['id']}'>$driverInfo</option>";
                            }
                        } else {
                            echo "<option disabled>لا يوجد مشغلون متاحون حالياً</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> حفظ الإسناد
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('projectForm').style.display='none'">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول المشغلين المرتبطين -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list-alt"></i> المشغلون المرتبطون بهذه المعدة
            <?php if (count($linked) > 0): ?>
                <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; margin-right: auto;">
                    <?php echo count($linked); ?> مشغل
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (count($linked) > 0): ?>
                <div class="table-container">
                    <table id="projectsTable" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم المشغل</th>
                                <th>رقم الهاتف</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ النهاية</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            foreach ($linked as $row): 
                                $statusClass = $row['status'] ? 'status-active' : 'status-inactive';
                                $statusIcon = $row['status'] ? 'check-circle' : 'times-circle';
                                $statusText = $row['status'] ? 'نشط' : 'غير نشط';
                                $actionIcon = $row['status'] ? 'ban' : 'check';
                                $actionText = $row['status'] ? 'تعطيل' : 'تفعيل';
                                $actionClass = $row['status'] ? 'delete' : 'activate';
                            ?>
                            <tr>
                                <td><strong><?php echo $i++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo $row['start_date'] ? date('Y-m-d', strtotime($row['start_date'])) : '-'; ?></td>
                                <td><?php echo $row['end_date'] ? date('Y-m-d', strtotime($row['end_date'])) : '-'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="delete_equipment_driver.php?id=<?php echo $row['id']; ?>&equipment_id=<?php echo $equipment_id; ?>" 
                                           class="action-btn <?php echo $actionClass; ?>"
                                           onclick="return confirm('هل أنت متأكد من <?php echo $actionText; ?> هذا المشغل؟')"
                                           title="<?php echo $actionText; ?>">
                                            <i class="fas fa-<?php echo $actionIcon; ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>لا يوجد مشغلون مسندون لهذه المعدة</h3>
                    <p>ابدأ بإضافة مشغلين للمعدة باستخدام الزر أعلاه</p>
                    <button onclick="document.getElementById('toggleForm').click()" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> إسناد مشغل الآن
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- jQuery (واحد فقط) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables core -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Responsive extension -->
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<!-- Export dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Buttons extension -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- تهيئة DataTable وجافاسكربت الواجهة -->
<script>
$(document).ready(function() {
    // تهيئة DataTable
    <?php if (count($linked) > 0): ?>
    $('#projectsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'copy', text: '<i class="fas fa-copy"></i> نسخ' },
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> تصدير Excel' },
            { extend: 'csv', text: '<i class="fas fa-file-csv"></i> تصدير CSV' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> تصدير PDF' },
            { extend: 'print', text: '<i class="fas fa-print"></i> طباعة' }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        order: [[0, 'asc']],
        pageLength: 25
    });
    <?php endif; ?>

    // التحكم في إظهار/إخفاء الفورم
    $('#toggleForm').on('click', function(e) {
        e.preventDefault();
        const form = $('#projectForm');
        
        if (form.is(':visible')) {
            form.slideUp(300);
            $(this).html('<i class="fas fa-user-plus"></i> إسناد مشغل جديد');
        } else {
            form.slideDown(300);
            $(this).html('<i class="fas fa-times"></i> إخفاء الفورم');
            $('html, body').animate({ scrollTop: form.offset().top - 100 }, 500);
        }
    });

    // تحسين تجربة اختيار المشغلين المتعددين
    $('select[multiple]').on('change', function() {
        const selectedCount = $(this).find('option:selected').length;
        $(this).css('border-color', selectedCount > 0 ? 'var(--success)' : 'var(--border)');
    });

    // التحقق من صحة التواريخ
    $('input[name="end_date"]').on('change', function() {
        const startDate = new Date($('input[name="start_date"]').val());
        const endDate = new Date($(this).val());
        if (endDate < startDate) {
            alert('⚠️ تاريخ النهاية لا يمكن أن يكون قبل تاريخ البداية');
            $(this).val('');
        }
    });

    // رسالة النجاح التلقائية
    <?php if (isset($_GET['msg'])): ?>
    setTimeout(function() { $('.alert-success').fadeOut(500); }, 5000);
    <?php endif; ?>

    // تحسين رسائل التأكيد
    $('.action-btn').on('click', function(e) {
        const actionType = $(this).hasClass('delete') ? 'تعطيل' : 'تفعيل';
        const driverName = $(this).closest('tr').find('td:eq(1)').text();
        if (!confirm(`هل أنت متأكد من ${actionType} المشغل: ${driverName}؟`)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

</body>

</html>