<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيكوبيشن | تفاصيل العقد</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

<?php include('../insidebar.php'); ?>

<div class="main">

    <h3> 📑 تفاصيل العقد </h3>

    <!-- أزرار الإجراءات -->
    <div class="aligin" style="margin-bottom: 20px;">
        <button class="add" id="renewalBtn" title="تجديد مدة العقد" style="background-color: #17a2b8;">
            <i class="fa fa-sync"></i> تجديد العقد
        </button>
        <button class="add" id="settlementBtn" title="تسوية الساعات المتبقية" style="background-color: #6c757d;">
            <i class="fa fa-balance-scale"></i> تسوية
        </button>
        <button class="add" id="pauseBtn" title="إيقاف مؤقت للعقد" style="background-color: #ffc107;">
            <i class="fa fa-pause"></i> إيقاف
        </button>
        <button class="add" id="resumeBtn" title="استئناف العقد المتوقف" style="background-color: #28a745;">
            <i class="fa fa-play"></i> استئناف
        </button>
        <button class="add" id="terminateBtn" title="إنهاء العقد" style="background-color: #dc3545;">
            <i class="fa fa-stop"></i> إنهاء
        </button>
        <button class="add" id="mergeBtn" title="دمج هذا العقد مع عقد آخر" style="background-color: #e83e8c;">
            <i class="fa fa-code-branch"></i> دمج
        </button>
    </div>

<?php
include '../config.php';

$contract_id = intval($_GET['id']);

$sql = "SELECT 
            id, project, contract_signing_date, grace_period_days, contract_duration_months, contract_duration_days,
            actual_start, actual_end, transportation, accommodation, place_for_living, 
            workshop, hours_monthly_target, forecasted_contracted_hours, created_at, updated_at,
            daily_work_hours, daily_operators, first_party, second_party, 
            witness_one, witness_two, status, pause_reason, termination_type, termination_reason, merged_with
        FROM contracts
        WHERE id = $contract_id
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("خطأ في الاستعلام: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($result)) {

    // حساب المدة المتبقية من العقد باعتماد تاريخ اليوم وتاريخ الانتهاء
    $today = new DateTime();
    $actual_end_date = new DateTime($row['actual_end']);
    $interval = $today->diff($actual_end_date);
    $remaining_days = (int)$interval->format('%r%a');  



    // جلب اسم المشروع
    $project_sql = "SELECT name FROM projects WHERE id = " . intval($row['project']) . " LIMIT 1";
    $project_result = mysqli_query($conn, $project_sql);
    if ($project_result && mysqli_num_rows($project_result) > 0) {
        $project_row = mysqli_fetch_assoc($project_result);
        $row['project'] = $project_row['name'];
    } else {
        $row['project'] = "غير معروف";
    }

    // تحديد لون الحالة
    $status_color = 'green';
    $status_text = 'ساري';
    if (isset($row['status'])) {
        if ($row['status'] == 1) {
            $status_color = 'green';
            $status_text = 'ساري';
        } else {
            $status_color = 'red';
            $status_text = 'غير ساري';
        }
    } else {
        $row['status'] = 1;
    }
?>
    <div class="report">
        <div class="row mb-2">
            <div class="col-lg-2 col-5">المشروع</div>
            <div class="col-lg-4 col-7"><?php echo $row['project']; ?></div>
            <div class="col-lg-2 col-5">حالة العقد</div>
            <div class="col-lg-4 col-7"><font color="<?php echo $status_color; ?>"><strong><?php echo $status_text; ?></strong></font></div>
            <div class="col-lg-2 col-5">تاريخ توقيع العقد</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_signing_date']; ?></div>
            <div class="col-lg-2 col-5">فترة السماح (أيام)</div>
            <div class="col-lg-4 col-7"><?php echo $row['grace_period_days']; ?></div>
            <div class="col-lg-2 col-5">تاريخ البدء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_start']; ?></div>
            <div class="col-lg-2 col-5">تاريخ الانتهاء الفعلي</div>
            <div class="col-lg-4 col-7"><?php echo $row['actual_end']; ?></div>
            <div class="col-lg-2 col-5">مدة العقد (ايام)</div>
            <div class="col-lg-4 col-7"><?php echo $row['contract_duration_days']; ?></div>
            <div class="col-lg-2 col-5">الايام المتبقية للعقد</div>
            <div class="col-lg-4 col-7"><?php echo $remaining_days; ?></div>
            <div class="col-lg-2 col-5">النقل</div>
            <div class="col-lg-4 col-7"><?php echo $row['transportation']; ?></div>
            <div class="col-lg-2 col-5">السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['accommodation']; ?></div>
            <div class="col-lg-2 col-5">مكان السكن</div>
            <div class="col-lg-4 col-7"><?php echo $row['place_for_living']; ?></div>
            <div class="col-lg-2 col-5">الورشة</div>
            <div class="col-lg-4 col-7"><?php echo $row['workshop']; ?></div>
            <div class="col-lg-2 col-5">الهدف الشهري للساعات</div>
            <div class="col-lg-4 col-7"><?php echo $row['hours_monthly_target'] * 30; ?></div>
            <div class="col-lg-2 col-5">الساعات التعاقدية المتوقعة</div>
            <div class="col-lg-4 col-7"><?php echo $row['forecasted_contracted_hours']; ?></div>
            <?php if (isset($row['pause_reason']) && !empty($row['pause_reason'])): ?>
            <div class="col-lg-2 col-5">سبب الإيقاف</div>
            <div class="col-lg-4 col-7"><?php echo $row['pause_reason']; ?></div>
            <?php endif; ?>
            <?php if (isset($row['termination_reason']) && !empty($row['termination_reason'])): ?>
            <div class="col-lg-2 col-5">سبب الإنهاء</div>
            <div class="col-lg-4 col-7"><?php echo $row['termination_reason']; ?></div>
            <?php endif; ?>
            <div class="col-lg-2 col-5">عدد ساعات العمل اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_work_hours']; ?></div>
            <div class="col-lg-2 col-5">عدد المشغلين للساعات اليومية</div>
            <div class="col-lg-4 col-7"><?php echo $row['daily_operators']; ?></div>
            <div class="col-lg-2 col-5">الطرف الأول</div>
            <div class="col-lg-4 col-7"><?php echo $row['first_party']; ?></div>
            <div class="col-lg-2 col-5">الطرف الثاني</div>
            <div class="col-lg-4 col-7"><?php echo $row['second_party']; ?></div>
            <div class="col-lg-2 col-5">الشاهد الأول</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_one']; ?></div>
            <div class="col-lg-2 col-5">الشاهد الثاني</div>
            <div class="col-lg-4 col-7"><?php echo $row['witness_two']; ?></div>
             <div class="col-lg-2 col-5">تاريخ الإنشاء</div>
            <div class="col-lg-4 col-7"><?php echo $row['created_at']; ?></div>
            <div class="col-lg-2 col-5">آخر تحديث</div>
            <div class="col-lg-4 col-7"><?php echo $row['updated_at']; ?></div>
        </div>
    </div>
<?php 
$contractStatusValue = isset($row['status']) ? $row['status'] : 1;
$project_id = $row['project'];
$actual_end_date = $row['actual_end'];
} 
?>

<!-- جدول معدات العقد (بما فيها معدات العقد المدموج) -->
<div class="card shadow-sm" style="margin-top: 30px;">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">
            معدات العقد
            <?php 
            if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                echo " (العقد #" . $contract_id . " + العقد #" . $row['merged_with'] . ")";
            }
            ?>
        </h5>
    </div>
    <div class="card-body">
        <table class="display nowrap" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع المعدة</th>
                    <th>الحجم</th>
                    <th>العدد</th>
                    <th>عدد الورديات</th>
                    <th>الساعات/اليوم</th>
                    <th>إجمالي الساعات</th>
                    <th> الوحدة </th>
                    <th>إجمالي ساعات العقد</th>
                    <th>السعر</th>
                    <th> المشغلين </th>
                    <th> المشرفين </th>
                    <th> الفنيين </th>
                    <th> المساعدين </th>
                    <?php 
                    if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                        echo "<th>المصدر</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                include 'contractequipments_handler.php';
                $equipments = getContractEquipments($contract_id, $conn);
                
                if (!empty($equipments)) {
                    $i = 1;
                    foreach ($equipments as $equip) {
                        echo "<tr>";
                        echo "<td>" . $i . "</td>";
                        echo "<td>" . htmlspecialchars($equip['equip_type']) . "</td>";
                        echo "<td>" . $equip['equip_size'] . "</td>";
                        echo "<td>" . $equip['equip_count'] . "</td>";
                        echo "<td>" . (isset($equip['equip_shifts']) ? $equip['equip_shifts'] : 0) . "</td>";
                        echo "<td>" . $equip['equip_target_per_month'] . "</td>";
                        echo "<td>" . $equip['equip_total_month'] . "</td>";
                        echo "<td>" . $equip['equip_unit'] . "</td>";
                        echo "<td>" . $equip['equip_total_contract'] . "</td>";
                        echo "<td>" . $equip['equip_price'] ."-". $equip['equip_price_currency'] . "</td>";
                        echo "<td>" . $equip['equip_operators'] . "</td>";
                        echo "<td>" . $equip['equip_supervisors'] . "</td>";
                        echo "<td>" . $equip['equip_technicians'] . "</td>";
                        echo "<td>" . $equip['equip_assistants'] . "</td>";
                        if (!empty($row['merged_with']) && $row['merged_with'] != '0') {
                            // التحقق من هل هذه المعدة من العقد المدموج أم لا
                            $merged_equipments = getContractEquipments(intval($row['merged_with']), $conn);
                            $is_from_merged = false;
                            foreach ($merged_equipments as $m_equip) {
                                if ($m_equip['equip_type'] == $equip['equip_type'] && 
                                    $m_equip['equip_size'] == $equip['equip_size'] &&
                                    $m_equip['equip_count'] == $equip['equip_count']) {
                                    $is_from_merged = true;
                                    break;
                                }
                            }
                            echo "<td><span class='badge " . ($is_from_merged ? "bg-success" : "bg-primary") . "'>" . 
                                 ($is_from_merged ? "العقد #" . $row['merged_with'] : "العقد #" . $contract_id) . 
                                 "</span></td>";
                        }
                        echo "</tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='7' style='text-align: center; color: #999;'>لا توجد معدات لهذا العقد</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// إزالة الجدول المنفصل للعقد المدموج (تم دمج معداته في الجدول الرئيسي)
?>

    <br/><br/><br/>

    <!-- جدول الملاحظات -->
    <div class="card shadow-sm" style="margin-top: 30px;">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">سجل الملاحظات والتغييرات</h5>
        </div>
        <div class="card-body">
            <table class="display nowrap" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الملاحظة</th>
                        <th>التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $notes_query = "SELECT * FROM contract_notes WHERE contract_id = $contract_id ORDER BY created_at DESC";
                    $notes_result = mysqli_query($conn, $notes_query);
                    
                    if ($notes_result && mysqli_num_rows($notes_result) > 0) {
                        $j = 1;
                        while ($note = mysqli_fetch_assoc($notes_result)) {
                            echo "<tr>";
                            echo "<td>" . $j . "</td>";
                            echo "<td>" . htmlspecialchars($note['note']) . "</td>";
                            echo "<td>" . $note['created_at'] . "</td>";
                            echo "</tr>";
                            $j++;
                        }
                    } else {
                        echo "<tr><td colspan='3' style='text-align: center; color: #999;'>لا توجد ملاحظات لهذا العقد</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal for Renewal -->
<div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewalModalLabel">تجديد العقد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="renewalStartDate" class="form-label">تاريخ بدء التجديد <span style="color: red;">*</span></label>
                    <input type="date" id="renewalStartDate" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="renewalEndDate" class="form-label">تاريخ انتهاء التجديد <span style="color: red;">*</span></label>
                    <input type="date" id="renewalEndDate" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="confirmRenewal">تجديد</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Settlement -->
<div class="modal fade" id="settlementModal" tabindex="-1" aria-labelledby="settlementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settlementModalLabel">تسوية العقد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="settlementType" class="form-label">نوع التسوية</label>
                    <select id="settlementType" class="form-control">
                        <option value="">-- اختر --</option>
                        <option value="increase">زيادة ساعات</option>
                        <option value="decrease">نقصان ساعات</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="settlementHours" class="form-label">عدد الساعات</label>
                    <input type="number" id="settlementHours" class="form-control" min="1" placeholder="أدخل عدد الساعات">
                </div>
                <div class="mb-3">
                    <label for="settlementReason" class="form-label">السبب (اختياري)</label>
                    <textarea id="settlementReason" class="form-control" rows="3" placeholder="أدخل السبب"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="confirmSettlement">تسوية</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Pause -->
<div class="modal fade" id="pauseModal" tabindex="-1" aria-labelledby="pauseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pauseModalLabel">إيقاف العقد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="pauseReason" class="form-label">سبب الإيقاف <span style="color: red;">*</span></label>
                    <textarea id="pauseReason" class="form-control" rows="4" placeholder="أدخل السبب المفصل"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-warning" id="confirmPause">إيقاف</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Resume -->
<div class="modal fade" id="resumeModal" tabindex="-1" aria-labelledby="resumeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resumeModalLabel">استئناف العقد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="resumeReason" class="form-label">ملاحظات (اختياري)</label>
                    <textarea id="resumeReason" class="form-control" rows="3" placeholder="أدخل أي ملاحظات"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="confirmResume">استئناف</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Terminate -->
<div class="modal fade" id="terminateModal" tabindex="-1" aria-labelledby="terminateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="terminateModalLabel">إنهاء العقد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="terminationType" class="form-label">نوع الإنهاء <span style="color: red;">*</span></label>
                    <select id="terminationType" class="form-control">
                        <option value="">-- اختر --</option>
                        <option value="amicable">رضائي</option>
                        <option value="hardship">بسبب التعسر</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="terminationReason" class="form-label">السبب (اختياري)</label>
                    <textarea id="terminationReason" class="form-control" rows="3" placeholder="أدخل السبب"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="confirmTerminate">إنهاء</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Merge -->
<div class="modal fade" id="mergeModal" tabindex="-1" aria-labelledby="mergeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mergeModalLabel">دمج العقود</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="mergeWithId" class="form-label">اختر العقد للدمج معه <span style="color: red;">*</span></label>
                    <select id="mergeWithId" class="form-control">
                        <option value="">-- اختر عقد --</option>
                        <?php
                        $merge_query = "SELECT id, contract_signing_date FROM contracts WHERE project = $project_id AND id != $contract_id ORDER BY id DESC";
                        $merge_result = mysqli_query($conn, $merge_query);
                        while ($m_row = mysqli_fetch_assoc($merge_result)) {
                            echo "<option value='" . $m_row['id'] . "'>العقد #" . $m_row['id'] . " - " . $m_row['contract_signing_date'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- عرض المعدات الحالية والمعدات الخاصة بالعقد المختار -->
                <div id="mergeEquipmentsContainer" style="margin-top: 20px;">
                    <h6 class="mb-3">معدات العقود:</h6>
                    
                    <!-- معدات العقد الحالي -->
                    <div class="mb-4">
                        <h6 style="background-color: #f0f0f0; padding: 10px; border-right: 3px solid #0066cc;">
                            <i class="fa fa-cube"></i> معدات العقد الحالي (#<?php echo $contract_id; ?>)
                        </h6>
                        <div id="currentContractEquipments">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>نوع المعدة</th>
                                        <th>الحجم</th>
                                        <th>العدد</th>
                                        <th>الساعات/الشهر</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_equipments = getContractEquipments($contract_id, $conn);
                                    if (!empty($current_equipments)) {
                                        foreach ($current_equipments as $equip) {
                                            echo "<tr>";
                                            echo "<td>" . $equip['equip_type'] . "</td>";
                                            echo "<td>" . $equip['equip_size'] . "</td>";
                                            echo "<td>" . $equip['equip_count'] . "</td>";
                                            echo "<td>" . $equip['equip_target_per_month'] . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' style='text-align: center; color: #999;'>لا توجد معدات</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- معدات العقد المختار -->
                    <div class="mb-4">
                        <h6 style="background-color: #f0f0f0; padding: 10px; border-right: 3px solid #28a745;">
                            <i class="fa fa-cube"></i> معدات العقد المختار
                        </h6>
                        <div id="selectedContractEquipments" style="min-height: 100px;">
                            <p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="confirmMerge">دمج</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (required for your AJAX calls) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const contractId = <?php echo $contract_id; ?>;
const contractStatus = <?php echo isset($contractStatusValue) ? $contractStatusValue : 1; ?>;
const actualEndDate = '<?php echo isset($actual_end_date) ? $actual_end_date : ''; ?>';  // تاريخ انتهاء العقد الفعلي

// دالة عامة للإجراءات
function performAction(action, data = {}) {
    $.ajax({
        url: 'contract_actions_handler.php',
        type: 'POST',
        data: Object.assign({action: action, contract_id: contractId}, data),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('خطأ: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('الخطأ:', error);
            alert('خطأ في الاتصال بالخادم: ' + (xhr.responseText || error));
        }
    });
}

// دالة للتحقق من إمكانية تنفيذ الإجراء
function canPerformAction(action) {
    const activeStatuses = {
        'renewal': [1],
        'settlement': [1],
        'pause': [1],
        'resume': [0],
        'terminate': [1, 0],
        'merge': [1]
    };
    
    if (!activeStatuses[action]) return true;
    
    if (!activeStatuses[action].includes(contractStatus)) {
        const statusMsg = {
            'renewal': 'العقد يجب أن يكون ساري لتجديده',
            'settlement': 'العقد يجب أن يكون ساري لتسويته',
            'pause': 'العقد يجب أن يكون ساري لإيقافه',
            'resume': 'العقد يجب أن يكون غير ساري لاستئنافه',
            'terminate': 'العقد يجب أن يكون ساري أو غير ساري لإنهاؤه',
            'merge': 'العقد يجب أن يكون ساري للدمج'
        };
        alert(statusMsg[action] || 'لا يمكن تنفيذ هذا الإجراء في الحالة الحالية');
        return false;
    }
    return true;
}

// أزرار الإجراءات - Bootstrap 5 syntax
$('#renewalBtn').click(function() {
    if (!canPerformAction('renewal')) return;
    // تعيين تاريخ البدء الافتراضي لتاريخ انتهاء العقد الفعلي
    if (actualEndDate) {
        $('#renewalStartDate').val(actualEndDate);
    }
    const modal = new bootstrap.Modal(document.getElementById('renewalModal'));
    modal.show();
});

$('#confirmRenewal').click(function() {
    const startDate = $('#renewalStartDate').val();
    const endDate = $('#renewalEndDate').val();
    if (!startDate || !endDate) {
        alert('الرجاء ملء جميع الحقول');
        return;
    }
    if (new Date(startDate) >= new Date(endDate)) {
        alert('تاريخ البدء يجب أن يكون قبل تاريخ الانتهاء');
        return;
    }
    performAction('renewal', {
        new_start_date: startDate,
        new_end_date: endDate
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('renewalModal')).hide();
    $('#renewalStartDate').val('');
    $('#renewalEndDate').val('');
});

$('#settlementBtn').click(function() {
    if (!canPerformAction('settlement')) return;
    const modal = new bootstrap.Modal(document.getElementById('settlementModal'));
    modal.show();
});

$('#confirmSettlement').click(function() {
    const type = $('#settlementType').val();
    const hours = $('#settlementHours').val();
    if (!type || !hours) {
        alert('الرجاء ملء الحقول المطلوبة');
        return;
    }
    if (parseInt(hours) <= 0) {
        alert('عدد الساعات يجب أن يكون أكبر من صفر');
        return;
    }
    performAction('settlement', {
        settlement_type: type,
        settlement_hours: hours,
        settlement_reason: $('#settlementReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('settlementModal')).hide();
    $('#settlementType').val('');
    $('#settlementHours').val('');
    $('#settlementReason').val('');
});

$('#pauseBtn').click(function() {
    if (!canPerformAction('pause')) return;
    const modal = new bootstrap.Modal(document.getElementById('pauseModal'));
    modal.show();
});

$('#confirmPause').click(function() {
    const reason = $('#pauseReason').val();
    if (!reason) {
        alert('الرجاء إدخال سبب الإيقاف');
        return;
    }
    performAction('pause', {
        pause_reason: reason
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
    $('#pauseReason').val('');
});

$('#resumeBtn').click(function() {
    if (!canPerformAction('resume')) return;
    const modal = new bootstrap.Modal(document.getElementById('resumeModal'));
    modal.show();
});

$('#confirmResume').click(function() {
    performAction('resume', {
        resume_reason: $('#resumeReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('resumeModal')).hide();
    $('#resumeReason').val('');
});

$('#terminateBtn').click(function() {
    if (!canPerformAction('terminate')) return;
    const modal = new bootstrap.Modal(document.getElementById('terminateModal'));
    modal.show();
});

$('#confirmTerminate').click(function() {
    const type = $('#terminationType').val();
    if (!type) {
        alert('الرجاء اختيار نوع الإنهاء');
        return;
    }
    performAction('terminate', {
        termination_type: type,
        termination_reason: $('#terminationReason').val()
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('terminateModal')).hide();
    $('#terminationType').val('');
    $('#terminationReason').val('');
});

$('#mergeBtn').click(function() {
    if (!canPerformAction('merge')) return;
    const modal = new bootstrap.Modal(document.getElementById('mergeModal'));
    modal.show();
});

// تحميل معدات العقد المختار عند التغيير
$('#mergeWithId').on('change', function() {
    const selectedContractId = $(this).val();
    
    if (!selectedContractId) {
        $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
        return;
    }
    
    // تحميل المعدات عبر AJAX
    $.ajax({
        url: 'get_contract_equipments.php',
        type: 'GET',
        data: { contract_id: selectedContractId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                if (response.equipments.length > 0) {
                    html = '<table class="table table-sm table-bordered">';
                    html += '<thead class="table-light"><tr>';
                    html += '<th>نوع المعدة</th>';
                    html += '<th>الحجم</th>';
                    html += '<th>العدد</th>';
                    html += '<th>الساعات/الشهر</th>';
                    html += '</tr></thead>';
                    html += '<tbody>';
                    
                    response.equipments.forEach(function(equip) {
                        html += '<tr>';
                        html += '<td>' + equip.equip_type + '</td>';
                        html += '<td>' + equip.equip_size + '</td>';
                        html += '<td>' + equip.equip_count + '</td>';
                        html += '<td>' + equip.equip_target_per_month + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                } else {
                    html = '<p style="text-align: center; color: #999;">لا توجد معدات لهذا العقد</p>';
                }
                $('#selectedContractEquipments').html(html);
            } else {
                $('#selectedContractEquipments').html('<p style="text-align: center; color: #c00;">خطأ: ' + response.message + '</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('الخطأ:', error);
            $('#selectedContractEquipments').html('<p style="text-align: center; color: #c00;">خطأ في تحميل المعدات</p>');
        }
    });
});

$('#confirmMerge').click(function() {
    const mergeId = $('#mergeWithId').val();
    if (!mergeId) {
        alert('الرجاء اختيار العقد للدمج معه');
        return;
    }
    if (parseInt(mergeId) === contractId) {
        alert('لا يمكنك دمج العقد مع نفسه');
        return;
    }
    performAction('merge', {
        merge_with_id: mergeId
    });
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('mergeModal')).hide();
    $('#mergeWithId').val('');
    $('#selectedContractEquipments').html('<p style="text-align: center; color: #999;">اختر عقداً لعرض معداته</p>');
});
</script>

</body>
</html>