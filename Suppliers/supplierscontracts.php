<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

// التحقق من وجود معرف المورد
if (!isset($_GET['id'])) {
  header("Location: suppliers.php");
  exit();
}

$supplier_id = intval($_GET['id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إيكوبيشن | عقود المورد</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- DataTables CSS -->

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Call bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <!-- CSS الموقع -->
  <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');

  :root {
    --primary-color: #1a1a2e;
    --secondary-color: #1a1a2e;
    --gold-color: #ffcc00;
    --text-color: #010326;
    --light-color: #fff9e6;
    --border-color: #f1e3a3;
    --shadow-color: rgba(0, 0, 0, 0.12);
  }



  .main {
    width: calc(100% - 250px);
    padding: 30px;
  }

  /* Page Title */
  .main h2 {
    font-size: 2rem;
    font-weight: 900;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 2rem;
  }

  /* Action Buttons Container */
  .aligin {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding: 1rem;
    border-radius: 15px;
  }

  /* Modern Action Buttons */
  .aligin .add {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px var(--shadow-color);
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  }

  .aligin .add::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
  }

  .aligin .add:hover::before {
    width: 300px;
    height: 300px;
  }

  .aligin .add:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
  }

  .aligin .add:active {
    transform: translateY(-1px);
  }

  #toggleForm {
    background: linear-gradient(135deg, var(--gold-color) 0%, var(--secondary-color) 100%);
  }

  /* Form Styling */
  #projectForm {
    animation: fadeInUp 0.6s ease;
  }

  .card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 5px 20px var(--shadow-color);
    overflow: hidden;
    margin-bottom: 2rem;
  }

  .card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    padding: 1.5rem;
    border: none;
  }

  .card-header h5 {
    color: white;
    font-weight: 700;
    margin: 0;
  }

  .card-body {
    padding: 2rem;
  }

  /* Section Titles */
  .section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gold-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 1.5rem 0;
  }

  .chip {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--text-color);
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: 0 3px 10px var(--shadow-color);
  }

  /* Form Fields */
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
  }

  .field label {
    display: block;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
  }

  .field input,
  .field select,
  .field textarea {
    width: 100%;
    padding: 0.75rem;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    font-weight: 500;
  }

  .field input:focus,
  .field select:focus,
  .field textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(255, 204, 0, 0.25);
  }

  .field input[readonly] {
    background: #f8f9fa;
    cursor: not-allowed;
  }

  /* KPI Cards */
  .totals {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
  }

  .kpi {
    background: linear-gradient(135deg, #ffffff 0%, var(--light-color) 100%);
    border: none;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 3px 15px var(--shadow-color);
    transition: all 0.3s ease;
    border-right: 5px solid var(--primary-color);
  }

  .kpi:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .kpi .v {
    font-weight: 900;
    font-size: 2rem;
    color: var(--gold-color);
    margin-bottom: 0.5rem;
  }

  .kpi .t {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 600;
  }

  /* Buttons */
  button.primary,
  .btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--text-color);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px var(--shadow-color);
  }

  button.primary:hover,
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
  }

  #addEquipmentBtn {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  }

  /* HR Separator */
  .hr {
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
    margin: 2rem 0;
    border: none;
  }

  /* Equipment Sections */
  .equipment-section {
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    padding: 1.5rem;
    border-radius: 15px;
    margin-bottom: 1.5rem;
    border: 2px solid var(--border-color);
    position: relative;
  }

  .equipment-section h4 {
    color: var(--gold-color);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .remove-equipment {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    position: absolute;
    top: 1rem;
    left: 1rem;
  }

  .remove-equipment:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
  }

  /* DataTable Styling */
  .dataTables_wrapper {
    padding: 1rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 3px 15px var(--shadow-color);
  }

  table.dataTable {
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
  }

  table.dataTable thead th {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    font-weight: 700;
    padding: 1rem;
    text-align: center;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    white-space: nowrap;
    font-size: 0.9rem;
  }

  table.dataTable thead th:first-child {
    border-left: none;
  }

  /* Group column colors for better organization */
  table.dataTable thead th.group-basic {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  }

  table.dataTable thead th.group-dates {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  }

  table.dataTable thead th.group-hours {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
  }

  table.dataTable thead th.group-parties {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
  }

  table.dataTable thead th.group-services {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  }

  table.dataTable thead th.group-operations {
    background: linear-gradient(135deg, #fd7e14 0%, #e66a0a 100%);
  }

  table.dataTable thead th.group-status {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  }

  table.dataTable tbody tr {
    transition: all 0.3s ease;
  }

  table.dataTable tbody tr:hover {
    background: linear-gradient(135deg, #fef7d6 0%, var(--light-color) 100%);
    transform: scale(1.005);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  table.dataTable tbody td {
    padding: 1rem;
    text-align: center;
    font-weight: 500;
  }

  /* Action Buttons in Table */
  .btn-action {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0.2rem;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  }

  .btn-action i {
    margin: 0;
  }

  .btn-action-edit {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: var(--text-color);
  }

  .btn-action-edit:hover {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255, 204, 0, 0.3);
  }

  .btn-action-delete {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
  }

  .btn-action-delete:hover {
    background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
  }

  .btn-action-view {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
  }

  .btn-action-view:hover {
    background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
  }

  /* Group Toggle Buttons */
  .btn-group-toggle {
    padding: 0.5rem 1rem;
    border: 2px solid #e0e0e0;
    background: white;
    color: #666;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-group-toggle:hover {
    border-color: var(--primary-color);
    color: var(--gold-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(255, 204, 0, 0.2);
  }

  .btn-group-toggle.active {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
  }

  .btn-group-toggle.active:hover {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
  }

  .btn-group-toggle-all {
    padding: 0.5rem 1.2rem;
    border: 2px solid #28a745;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 700;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-group-toggle-all:hover {
    background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(40, 167, 69, 0.4);
  }

  /* Hidden columns */
  .group-hidden {
    display: none !important;
  }

  /* Responsive table */
  @media (max-width: 1400px) {
    table.dataTable {
      font-size: 0.85rem;
    }

    table.dataTable thead th,
    table.dataTable tbody td {
      padding: 0.7rem 0.5rem;
    }
  }

  /* Animation */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Responsive */
  @media (max-width: 768px) {
    .aligin {
      justify-content: center;
    }

    .aligin .add {
      flex: 1 1 45%;
    }

    .form-grid {
      grid-template-columns: 1fr;
    }

    .totals {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  table.dataTable thead th {
    color: #ffffff !important;
  }
</style>

<body>

  <?php include('../insidebar.php'); ?>

  <div class="main">
    <div class="aligin">
      <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fas fa-plus-circle"></i> عقد جديد
      </a>
      <a href="suppliers.php" class="add" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);">
        <i class="fas fa-arrow-right"></i> العودة للموردين
      </a>
    </div>

    <!-- فورم إضافة عقد -->
    <form id="projectForm" action="" method="post" style="display:none;">

      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0">
            <i class="fas fa-file-signature"></i> إضافة / تعديل عقد المورد
          </h5>
        </div>
        <div class="card-body">

          <input type="hidden" name="id" id="contract_id" value="">
          <input type="hidden" name="supplier_id" value="<?php echo $supplier_id; ?>" required />

          <!-- القسم 1: اختيار المشروع والمنجم والعقد -->
          <div class="section-title"><span class="chip">1</span> اختيار المشروع والمنجم والعقد</div>
          <br>

          <div class="form-grid">
            <div class="field md-4">
              <label>اسم المشروع <font color="red">*</font></label>
              <div class="control">
                <select name="project_id" id="project_id" required>
                  <option value="">— اختر المشروع —</option>
                  <?php
                  include '../config.php';
                  $projects_query = "SELECT id, name FROM project WHERE status = 1 ORDER BY name ASC";
                  $projects_result = mysqli_query($conn, $projects_query);
                  while ($project = mysqli_fetch_assoc($projects_result)) {
                    echo "<option value='" . $project['id'] . "'>" . $project['name'] . "</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="field md-4">
              <label>المنجم <font color="red">*</font></label>
              <div class="control">
                <select name="mine_id" id="mine_id" required disabled>
                  <option value="">— اختر المشروع أولاً —</option>
                </select>
              </div>
            </div>

            <div class="field md-4">
              <label>عقد المنجم <font color="red">*</font></label>
              <div class="control">
                <select name="project_contract_id" id="project_contract_id" required disabled>
                  <option value="">— اختر المنجم أولاً —</option>
                </select>
              </div>
            </div>
          </div>

          <!-- عرض معلومات ساعات العقد -->
          <div id="projectHoursInfo"
            style="display:none; margin: 1rem 0; padding: 1.5rem; background: linear-gradient(135deg, #fff7d1 0%, #ffe8a3 100%); border-radius: 15px; border-right: 4px solid var(--primary-color); box-shadow: 0 3px 10px var(--shadow-color);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
              <div
                style="background: white; padding: 1.2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <strong style="color: #1976d2; font-size: 0.9rem; display: block; margin-bottom: 0.5rem;">
                  <i class="fas fa-clock"></i> إجمالي ساعات العقد
                </strong>
                <div style="font-size: 2rem; color: #0d47a1; font-weight: 700;" id="contractTotalHours">0</div>
                <div id="equipmentBreakdown"
                  style="margin-top: 0.8rem; padding-top: 0.8rem; border-top: 2px dashed #e3f2fd; font-size: 0.85rem;">
                  <!-- سيتم ملء التفصيل هنا -->
                </div>
              </div>
              <div
                style="background: white; padding: 1.2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <strong style="color: #d32f2f; font-size: 0.9rem; display: block; margin-bottom: 0.5rem;">
                  <i class="fas fa-handshake"></i> المتعاقد عليه مع موردين
                </strong>
                <div style="font-size: 2rem; color: #c62828; font-weight: 700;" id="suppliersContractedHours">0</div>
              </div>
              <div
                style="background: white; padding: 1.2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <strong style="color: #388e3c; font-size: 0.9rem; display: block; margin-bottom: 0.5rem;">
                  <i class="fas fa-chart-line"></i> الساعات المتبقية
                </strong>
                <div style="font-size: 2rem; color: #2e7d32; font-weight: 700;" id="remainingHours">0</div>
              </div>
            </div>
          </div>

          <hr class="hr" />

          <!-- القسم 2: إجماليات الساعات (يومياً وللعقد) -->
          <div class="section-title"><span class="chip">2</span> إجماليات الساعات (يومياً وللعقد)</div>
          <br>

          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">الساعات اليومية المطلوبة</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">إجمالي ساعات العقد</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">معدات × ساعات لليوم</div>
            </div>
          </div>

          <div
            style="margin-top: 2rem; padding: 1rem; background: var(--light-color); border-radius: 10px; border-right: 4px solid var(--primary-color);">
            <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
              <i class="fas fa-info-circle"></i> <strong>ملاحظة:</strong> يتم حساب الإجماليات تلقائياً بناءً على
              البيانات المدخلة في الأقسام التالية
            </p>
          </div>

          <hr class="hr" />

          <div class="section-title"><span class="chip">3</span> البيانات الأساسية للعميل والعقد</div>
          <br>

          <div class="form-grid">

            <!-- صف 1: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>تاريخ توقيع العقد </label>
              <div class="control"><input name="contract_signing_date" id="contract_signing_date" type="date"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>فترة السماح بين التوقيع والتنفيذ </label>
              <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0"
                  placeholder="عدد الأيام"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>بداية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_start" id="actual_start" type="date"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>نهاية التنفيذ الفعلي المتفق عليه</label>
              <div class="control"><input name="actual_end" id="actual_end" type="date"></div>
            </div>



            <!-- خانتان فارغتان -->


            <!-- صف 2: 3 خانات -->

            <div class="field md-3 sm-6">
              <label>مدة العقد بالأيام </label>
              <div class="control"><input name="contract_duration_days" id="contract_duration_days" type="number"
                  min="0" placeholder="يُحتسب تلقائياً" readonly></div>
            </div>





            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="price_currency_contract" id="price_currency_contract">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>المبلغ المدفوع</label>
              <div class="control"><input name="paid_contract" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وقت الدفع</label>
              <div class="control">
                <select name="payment_time" id="payment_time">
                  <option value="">— اختر —</option>
                  <option value="مقدم">مقدم</option>
                  <option value=" مؤخر">مؤخر </option>

                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> الضمانات</label>
              <div class="control"><input name="guarantees" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label> تاريخ الدفع</label>
              <div class="control"><input name="payment_date" id="payment_date" type="date"></div>
            </div>











            <div class="field md-3 sm-6">
              <label>عدد الورديات للعقد </label>
              <div class="control"><input name="equip_shifts_contract" type="number" min="0" placeholder="مثال: 2">
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> ساعات الوردية للعقد</label>
              <div class="control"><input name="shift_contract" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الوحدات يومياً للعقد </label>
              <div class="control"><input name="equip_total_contract" type="number" placeholder=" "></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر للعقد</label>
              <div class="control"><input name="total_contract_permonth" type="number" min="0"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>إجمالي وحدات العقد </label>
              <div class="control"><input name="total_contract" type="number" placeholder=" "></div>
            </div>

            <div class="field md-3 sm-6">
              <label>مدراء الموقع </label>
              <div class="control"><input type="number" name="daily_operators" id="daily_operators" min="0"
                  placeholder="مثال: 3"></div>
            </div>



            <div class="field md-3 sm-6">
              <label>الترحيل (Transportation)</label>
              <div class="control">
                <select name="transportation" id="transportation">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>السكن (Place for Living)</label>
              <div class="control">
                <select name="place_for_living" id="place_for_living">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- صف 3: 3 خانات -->
            <div class="field md-3 sm-6">
              <label>الإعاشة (Accommodation)</label>
              <div class="control">
                <select name="accommodation" id="accommodation">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>الورشة (Workshop)</label>
              <div class="control">
                <select name="workshop" id="workshop">
                  <option value="">— اختر —</option>
                  <option value="مالك المعدة">مالك المعدة</option>
                  <option value="مالك المشروع">مالك المشروع</option>
                  <option value="بدون">بدون</option>
                </select>
              </div>
            </div>
            <!-- خانتان فارغتان -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>
          </div>

          <hr class="hr" />

          <!-- القسم 4: بيانات ساعات العمل المطلوبة للمعدات -->
          <div id="equipmentSections">
            <div class="section-title"><span class="chip">4</span> بيانات ساعات العمل المطلوبة <strong>للمعدات</strong>
            </div>
            <br>
            <div class="equipment-section" data-index="1">
              <div
                style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                <h6 style="margin: 0 0 15px 0;">المعدات رقم 1</h6>
                <div class="form-grid">
                  <div class="field md-3 sm-6">
                    <label>نوع المعدة</label>
                    <div class="control">
                      <select name="equip_type_1" class="equip-type">
                        <option value="">— اختر —</option>
                        <option value="حفار">حفار</option>
                        <option value="قلاب">قلاب</option>
                        <option value="خرامة">خرامة</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>حجم المعدة (Size)</label>
                    <div class="control"><input name="equip_size_1" type="number" placeholder="مثال: 340"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>عدد المعدات</label>
                    <div class="control"><input name="equip_count_1" type="number" min="0"></div>
                  </div>





                  <div class="field md-3 sm-6">
                    <label>عدد المشغلين</label>
                    <div class="control"><input name="equip_operators_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد المساعدين</label>
                    <div class="control"><input name="equip_assistants_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>عدد الورديات</label>
                    <div class="control"><input name="equip_shifts_1" type="number" min="0" placeholder="مثال: 2"></div>
                  </div>
                  <!-- أوقات الورديات -->
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                    <div class="control"><input name="shift1_start_1" type="time" placeholder="مثال: 08:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                    <div class="control"><input name="shift1_end_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                    <div class="control"><input name="shift2_start_1" type="time" placeholder="مثال: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                    <div class="control"><input name="shift2_end_1" type="time" placeholder="مثال: 00:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدة القياس</label>
                    <div class="control">
                      <select name="equip_unit_1" class="equip-unit">
                        <option value="">— اختر —</option>
                        <option value="ساعة">ساعة</option>
                        <option value="طن">طن</option>
                        <option value="متر طولي">متر طولي</option>
                        <option value="متر مكعب">متر مكعب</option>
                      </select>
                    </div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>ساعات الوردية</label>
                    <div class="control"><input name="shift_hours_1" type="number" min="0"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>إجمالي الوحدات يومياً</label>
                    <div class="control"><input name="equip_total_month_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>وحدات العمل في الشهر</label>
                    <div class="control"><input name="equip_target_per_month_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>إجمالي وحدات العقد</label>
                    <div class="control"><input name="equip_total_contract_1" type="number" readonly
                        placeholder="يُحتسب تلقائياً"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>العملة</label>
                    <div class="control">
                      <select name="equip_price_currency_1">
                        <option value="">— اختر —</option>
                        <option value="دولار">دولار</option>
                        <option value="جنيه">جنيه</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>السعر\للوحدة</label>
                    <div class="control"><input name="equip_price_1" type="number" min="0" step="0.01"
                        placeholder="0.00"></div>
                  </div>

                  <div class="field md-3 sm-6">

                  </div>





                  <!-- خانتان فارغتان للحفاظ على 3 خانات لكل صف -->

                  <div class="field md-3 sm-6">
                    <label>عدد المشرفين</label>
                    <div class="control"><input name="equip_supervisors_1" type="number" min="0"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>عدد الفنيين</label>
                    <div class="control"><input name="equip_technicians_1" type="number" min="0"></div>
                  </div>
                  <!-- إكمال الصف بثلاث خانات -->
                  <div class="field md-3 sm-6"></div>
                  <div class="field md-3 sm-6"></div>
                </div>
              </div>
            </div>
          </div>

          <div style="margin: 15px 0; display: flex; gap: 10px;">
            <button type="button" class="primary" id="addEquipmentBtn"
              style="padding: 0.75rem 1.5rem; font-size: 0.95rem;">
              <i class="fas fa-plus-circle"></i> إضافة مزيد من المعدات
            </button>
          </div>

          <hr class="hr" />
          <div class="section-title"><span class="chip">5</span> بيانات إضافية</div>
          <br>

          <div class="form-grid">

            <div class="field md-3 sm-6" style="display: none;">
              <label>عدد ساعات العمل اليومية <font color="red"> * مهم </font></label>
              <div class="control"><input type="number" id="daily_work_hours" name="daily_work_hours" min="0"
                  placeholder="مثال: 8" value="20"></div>
            </div>
            <!-- Orgnization Break  -->



            <div class="field md-3 sm-6">
              <label>الطرف الأول </label>
              <div class="control"><input type="text" name="first_party" id="first_party"
                  placeholder="اسم الطرف الاول ">
              </div>
            </div>



            <div class="field md-3 sm-6">
              <label>الطرف الثاني </label>
              <div class="control"><input type="text" name="second_party" id="second_party"
                  placeholder="اسم الطرف الثاني ">
              </div>
            </div>

            <div class="field md-3 sm-6"> </div>

            <div class="field md-3 sm-6">
              <label>الشاهد الأول</label>
              <div class="control"><input type="text" name="witness_one" id="witness_one"
                  placeholder="اسم الشاهد الأول">
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>الشاهد الثاني</label>
              <div class="control"><input type="text" name="witness_two" id="witness_two"
                  placeholder="اسم الشاهد الثاني">
              </div>
            </div>
          </div>


          <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center;">
            <button type="reset"
              style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); color: white; border: none; padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
              <i class="fas fa-eraser"></i> تفريغ الحقول
            </button>
            <button type="submit" class="primary" style="padding: 0.75rem 3rem;">
              <i class="fas fa-save"></i> حفظ البيانات
            </button>
          </div>
        </div>
      </div>
    </form>
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0">
          <i class="fas fa-list-alt"></i> قائمة العقود
        </h5>
      </div>

      <!-- أزرار التحكم في المجموعات -->
      <div class="card-body" style="padding: 1rem 2rem; border-bottom: 1px solid #e0e0e0;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
          <span style="font-weight: 700; color: var(--primary-color ); margin-left: 10px;">
            <i class="fas fa-filter"></i> عرض المجموعات:
          </span>
          <button class="btn-group-toggle active" data-group="basic" title="المعلومات الأساسية">
            <i class="fas fa-info-circle"></i> أساسية
          </button>
          <button class="btn-group-toggle active" data-group="dates" title="التواريخ والمدد">
            <i class="far fa-calendar"></i> تواريخ
          </button>
          <button class="btn-group-toggle active" data-group="hours" title="الساعات والأهداف">
            <i class="fas fa-clock"></i> ساعات
          </button>
          <button class="btn-group-toggle" data-group="parties" title="أطراف العقد">
            <i class="fas fa-users"></i> أطراف
          </button>
          <button class="btn-group-toggle" data-group="services" title="الخدمات المقدمة">
            <i class="fas fa-hands-helping"></i> خدمات
          </button>
          <button class="btn-group-toggle" data-group="operations" title="التشغيل اليومي">
            <i class="fas fa-cogs"></i> تشغيل
          </button>
          <button class="btn-group-toggle active" data-group="status" title="الحالة والإجراءات">
            <i class="fas fa-check-circle"></i> حالة
          </button>
          <button class="btn-group-toggle-all" title="إظهار/إخفاء الكل">
            <i class="fas fa-eye"></i> الكل
          </button>
        </div>
      </div>

      <div class="card-body" style="padding: 2rem; overflow-x: auto;">
        <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
          <thead>
            <tr>
              <!-- المعلومات الأساسية -->
              <th class="group-basic"><i class="fas fa-hashtag"></i> رقم العقد</th>
              <th class="group-basic"><i class="fas fa-project-diagram"></i> المشروع</th>
              <th class="group-basic"><i class="fas fa-mountain"></i> المنجم</th>
              <th class="group-basic"><i class="fas fa-file-contract"></i> رقم عقد المنجم</th>

              <!-- التواريخ والمدد -->
              <th class="group-dates"><i class="far fa-calendar"></i> تاريخ التوقيع</th>
              <th class="group-dates"><i class="fas fa-hourglass-half"></i> مدة السماح (أيام)</th>
              <th class="group-dates"><i class="fas fa-calendar-days"></i> مدة العقد (أيام)</th>
              <th class="group-dates"><i class="fas fa-play-circle"></i> بداية التنفيذ</th>
              <th class="group-dates"><i class="fas fa-stop-circle"></i> نهاية التنفيذ</th>

              <!-- الساعات والأهداف -->
              <th class="group-hours"><i class="far fa-clock"></i> هدف ساعات شهري</th>
              <th class="group-hours"><i class="fas fa-clock"></i> إجمالي ساعات متوقعة</th>

              <!-- أطراف العقد -->
              <th class="group-parties"><i class="fas fa-user-tie"></i> الطرف الأول</th>
              <th class="group-parties"><i class="fas fa-user-check"></i> الطرف الثاني</th>
              <th class="group-parties"><i class="fas fa-eye"></i> شاهد أول</th>
              <th class="group-parties"><i class="fas fa-eye"></i> شاهد ثاني</th>

              <!-- الخدمات المقدمة -->
              <th class="group-services"><i class="fas fa-truck"></i> النقل</th>
              <th class="group-services"><i class="fas fa-bed"></i> السكن</th>
              <th class="group-services"><i class="fas fa-home"></i> مكان المعيشة</th>
              <th class="group-services"><i class="fas fa-wrench"></i> الورشة</th>

              <!-- التشغيل اليومي -->
              <th class="group-operations"><i class="fas fa-business-time"></i> ساعات العمل يومياً</th>
              <th class="group-operations"><i class="fas fa-users-cog"></i> عدد المشغلين يومياً</th>

              <!-- البيانات المالية -->
              <th class="group-basic"><i class="fas fa-money-bill-wave"></i> العملة</th>
              <th class="group-basic"><i class="fas fa-dollar-sign"></i> المبلغ المدفوع</th>
              <th class="group-basic"><i class="fas fa-clock"></i> وقت الدفع</th>
              <th class="group-basic"><i class="fas fa-shield-alt"></i> الضمانات</th>
              <th class="group-basic"><i class="fas fa-calendar-check"></i> تاريخ الدفع</th>

              <!-- الحالة والإجراءات -->
              <th class="group-status"><i class="fas fa-info-circle"></i> الحالة</th>
              <th class="group-status"><i class="fas fa-cogs"></i> الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <?php
            include '../config.php';

            // إضافة عقد جديد عند إرسال الفورم
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['supplier_id']) && !empty($_POST['project_id']) && !empty($_POST['project_contract_id'])) {

              $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
              $supplier_id_post = intval($_POST['supplier_id']);
              $project_id = intval($_POST['project_id']);
              $mine_id = isset($_POST['mine_id']) ? intval($_POST['mine_id']) : 0;
              $project_contract_id = intval($_POST['project_contract_id']);


              $contract_signing_date = mysqli_real_escape_string($conn, $_POST['contract_signing_date']);
              $grace_period_days = intval($_POST['grace_period_days']);

              // حساب مدة العقد بالأيام من تاريخ البداية والنهاية
              $actual_start = mysqli_real_escape_string($conn, $_POST['actual_start']);
              $actual_end = mysqli_real_escape_string($conn, $_POST['actual_end']);

              // حساب عدد الأيام من تاريخ البداية إلى تاريخ الانتهاء (شامل يوم البداية ويوم النهاية)
              if (!empty($actual_start) && !empty($actual_end)) {
                $start_date = new DateTime($actual_start);
                $end_date = new DateTime($actual_end);
                $interval = $start_date->diff($end_date);
                $contract_duration_days = $interval->days + 1; // +1 لحساب يوم البداية ويوم النهاية معاً
              } else {
                $contract_duration_days = 0;
              }

              $transportation = mysqli_real_escape_string($conn, $_POST['transportation']);
              $accommodation = mysqli_real_escape_string($conn, $_POST['accommodation']);
              $place_for_living = mysqli_real_escape_string($conn, $_POST['place_for_living']);
              $workshop = mysqli_real_escape_string($conn, $_POST['workshop']);

              $hours_monthly_target = floatval($_POST['hours_monthly_target']);
              $forecasted_contracted_hours = floatval($_POST['forecasted_contracted_hours']);

              $daily_work_hours = floatval($_POST['daily_work_hours']);
              $daily_operators = intval($_POST['daily_operators']);
              $first_party = mysqli_real_escape_string($conn, $_POST['first_party']);
              $second_party = mysqli_real_escape_string($conn, $_POST['second_party']);
              $witness_one = mysqli_real_escape_string($conn, $_POST['witness_one']);
              $witness_two = mysqli_real_escape_string($conn, $_POST['witness_two']);

              // الحقول المالية الجديدة
              $price_currency_contract = isset($_POST['price_currency_contract']) ? mysqli_real_escape_string($conn, $_POST['price_currency_contract']) : '';
              $paid_contract = isset($_POST['paid_contract']) ? mysqli_real_escape_string($conn, $_POST['paid_contract']) : '';
              $payment_time = isset($_POST['payment_time']) ? mysqli_real_escape_string($conn, $_POST['payment_time']) : '';
              $guarantees = isset($_POST['guarantees']) ? mysqli_real_escape_string($conn, $_POST['guarantees']) : '';
              $payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : '';

              // الحقول الإضافية للعقد
              $equip_shifts_contract = isset($_POST['equip_shifts_contract']) ? intval($_POST['equip_shifts_contract']) : 0;
              $shift_contract = isset($_POST['shift_contract']) ? intval($_POST['shift_contract']) : 0;
              $equip_total_contract_daily = isset($_POST['equip_total_contract']) ? intval($_POST['equip_total_contract']) : 0;
              $total_contract_permonth = isset($_POST['total_contract_permonth']) ? intval($_POST['total_contract_permonth']) : 0;
              $total_contract_units = isset($_POST['total_contract']) ? intval($_POST['total_contract']) : 0;


              if ($id > 0) {
                // تعديل
                $sql = "UPDATE supplierscontracts SET 
            project_id='$project_id',
            mine_id='$mine_id',
            project_contract_id='$project_contract_id',
            contract_signing_date='$contract_signing_date',
            grace_period_days='$grace_period_days',
            contract_duration_days='$contract_duration_days',
            equip_shifts_contract='$equip_shifts_contract',
            shift_contract='$shift_contract',
            equip_total_contract_daily='$equip_total_contract_daily',
            total_contract_permonth='$total_contract_permonth',
            total_contract_units='$total_contract_units',
            actual_start='$actual_start',
            actual_end='$actual_end',
            transportation='$transportation',
            accommodation='$accommodation',
            place_for_living='$place_for_living',
            workshop='$workshop',
            hours_monthly_target='$hours_monthly_target',
            forecasted_contracted_hours='$forecasted_contracted_hours',
            daily_work_hours='$daily_work_hours',
            daily_operators='$daily_operators',
            first_party='$first_party',
            second_party='$second_party',
            witness_one='$witness_one',
            witness_two='$witness_two',
            price_currency_contract='$price_currency_contract',
            paid_contract='$paid_contract',
            payment_time='$payment_time',
            guarantees='$guarantees',
            payment_date='$payment_date'
        WHERE id=$id";
              } else {
                // إضافة
                $sql = "INSERT INTO supplierscontracts (
            supplier_id, project_id, mine_id, project_contract_id, contract_signing_date, grace_period_days, contract_duration_days,
            equip_shifts_contract, shift_contract, equip_total_contract_daily, total_contract_permonth, total_contract_units,
            actual_start, actual_end, transportation, accommodation, place_for_living, workshop,
            hours_monthly_target, forecasted_contracted_hours,
            daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two,
            price_currency_contract, paid_contract, payment_time, guarantees, payment_date
        ) VALUES (
            '$supplier_id_post', '$project_id', '$mine_id', '$project_contract_id', '$contract_signing_date', '$grace_period_days', '$contract_duration_days',
            '$equip_shifts_contract', '$shift_contract', '$equip_total_contract_daily', '$total_contract_permonth', '$total_contract_units',
            '$actual_start','$actual_end', '$transportation','$accommodation','$place_for_living','$workshop',
            '$hours_monthly_target','$forecasted_contracted_hours',
            '$daily_work_hours','$daily_operators','$first_party','$second_party','$witness_one','$witness_two',
            '$price_currency_contract','$paid_contract','$payment_time','$guarantees','$payment_date'
        )";
              }
              $result = mysqli_query($conn, $sql);

              if ($result) {
                // الحصول على معرف العقد المُضاف حديثاً أو معرف العقد المُحدّث
                if ($id > 0) {
                  $contract_id = $id;
                } else {
                  $contract_id = mysqli_insert_id($conn);
                }

                // جمع بيانات المعدات من الفورم
                $equipment_array = [];
                $i = 1;
                // البحث عن أكبر index موجود
                $max_index = 0;
                foreach ($_POST as $key => $value) {
                  if (preg_match('/equip_type_(\d+)/', $key, $matches)) {
                    $max_index = max($max_index, (int) $matches[1]);
                  }
                }

                // جمع البيانات من جميع الأقسام
                for ($i = 1; $i <= $max_index; $i++) {
                  if (isset($_POST["equip_type_$i"]) && !empty($_POST["equip_type_$i"])) {
                    $equipment_array[] = [
                      'equip_type' => mysqli_real_escape_string($conn, $_POST["equip_type_$i"]),
                      'equip_size' => isset($_POST["equip_size_$i"]) ? intval($_POST["equip_size_$i"]) : 0,
                      'equip_count' => isset($_POST["equip_count_$i"]) ? intval($_POST["equip_count_$i"]) : 0,
                      'equip_shifts' => isset($_POST["equip_shifts_$i"]) ? intval($_POST["equip_shifts_$i"]) : 0,
                      'equip_unit' => isset($_POST["equip_unit_$i"]) ? mysqli_real_escape_string($conn, $_POST["equip_unit_$i"]) : '',
                      'shift1_start' => isset($_POST["shift1_start_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift1_start_$i"]) : '',
                      'shift1_end' => isset($_POST["shift1_end_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift1_end_$i"]) : '',
                      'shift2_start' => isset($_POST["shift2_start_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift2_start_$i"]) : '',
                      'shift2_end' => isset($_POST["shift2_end_$i"]) ? mysqli_real_escape_string($conn, $_POST["shift2_end_$i"]) : '',
                      'shift_hours' => isset($_POST["shift_hours_$i"]) ? floatval($_POST["shift_hours_$i"]) : 0,
                      'equip_total_month' => isset($_POST["equip_total_month_$i"]) ? floatval($_POST["equip_total_month_$i"]) : 0,
                      'equip_monthly_target' => isset($_POST["equip_target_per_month_$i"]) ? floatval($_POST["equip_target_per_month_$i"]) : 0,
                      'equip_total_contract' => isset($_POST["equip_total_contract_$i"]) ? floatval($_POST["equip_total_contract_$i"]) : 0,
                      'equip_price' => isset($_POST["equip_price_$i"]) ? floatval($_POST["equip_price_$i"]) : 0,
                      'equip_price_currency' => isset($_POST["equip_price_currency_$i"]) ? mysqli_real_escape_string($conn, $_POST["equip_price_currency_$i"]) : '',
                      'equip_operators' => isset($_POST["equip_operators_$i"]) ? intval($_POST["equip_operators_$i"]) : 0,
                      'equip_supervisors' => isset($_POST["equip_supervisors_$i"]) ? intval($_POST["equip_supervisors_$i"]) : 0,
                      'equip_technicians' => isset($_POST["equip_technicians_$i"]) ? intval($_POST["equip_technicians_$i"]) : 0,
                      'equip_assistants' => isset($_POST["equip_assistants_$i"]) ? intval($_POST["equip_assistants_$i"]) : 0
                    ];
                  }
                }

                // إضافة بيانات المعدات الجديدة
                if (!empty($equipment_array)) {
                  // حذف المعدات القديمة أولاً
                  $delete_sql = "DELETE FROM suppliercontractequipments WHERE contract_id = $contract_id";
                  mysqli_query($conn, $delete_sql);

                  // إضافة المعدات الجديدة
                  foreach ($equipment_array as $equip) {
                    $insert_equip_sql = "INSERT INTO suppliercontractequipments (
                      contract_id, equip_type, equip_size, equip_count, equip_shifts, equip_unit,
                      shift1_start, shift1_end, shift2_start, shift2_end, shift_hours,
                      equip_total_month, equip_monthly_target, equip_total_contract,
                      equip_price, equip_price_currency, equip_operators, equip_supervisors,
                      equip_technicians, equip_assistants
                    ) VALUES (
                      $contract_id, '{$equip['equip_type']}', {$equip['equip_size']}, {$equip['equip_count']},
                      {$equip['equip_shifts']}, '{$equip['equip_unit']}', '{$equip['shift1_start']}',
                      '{$equip['shift1_end']}', '{$equip['shift2_start']}', '{$equip['shift2_end']}',
                      {$equip['shift_hours']}, {$equip['equip_total_month']}, {$equip['equip_monthly_target']},
                      {$equip['equip_total_contract']}, {$equip['equip_price']}, '{$equip['equip_price_currency']}',
                      {$equip['equip_operators']}, {$equip['equip_supervisors']}, {$equip['equip_technicians']},
                      {$equip['equip_assistants']}
                    )";
                    mysqli_query($conn, $insert_equip_sql);
                  }
                }
              }

              echo "<script>window.location.href='supplierscontracts.php?id=$supplier_id';</script>";
              exit;
            }

            // جلب العقود للمورد مع بيانات المنجم
            $query = "SELECT sc.*, 
                      op.name AS project_name,
                      c.mine_id,
                      m.mine_name,
                      m.mine_code
                      FROM supplierscontracts sc 
                      LEFT JOIN project op ON sc.project_id = op.id
                      LEFT JOIN contracts c ON sc.project_contract_id = c.id
                      LEFT JOIN mines m ON c.mine_id = m.id
                      WHERE sc.supplier_id = $supplier_id 
                      ORDER BY sc.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;


            while ($row = mysqli_fetch_assoc($result)) {

              // عرض حالة العقد من status
              $contractStatus = isset($row['status']) ? $row['status'] : 1;
              $statusColor = 'green';
              $statusText = 'ساري';
              if ($contractStatus == 1) {
                $statusColor = 'green';
                $statusText = 'ساري';
              } else {
                $statusColor = 'red';
                $statusText = 'غير ساري';
              }
              $status = "<font color='" . $statusColor . "'>" . $statusText . "</font>";

              echo "<tr>";

              // المعلومات الأساسية
              echo "<td class='group-basic'>" . $row['id'] . "</td>";
              echo "<td class='group-basic'>" . (isset($row['project_name']) ? $row['project_name'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['mine_name']) ? $row['mine_name'] . ' (' . $row['mine_code'] . ')' : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['project_contract_id']) ? 'عقد #' . $row['project_contract_id'] : '-') . "</td>";

              // التواريخ والمدد
              echo "<td class='group-dates'>" . $row['contract_signing_date'] . "</td>";
              echo "<td class='group-dates'>" . (isset($row['grace_period_days']) ? $row['grace_period_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . $row['actual_start'] . "</td>";
              echo "<td class='group-dates'>" . $row['actual_end'] . "</td>";

              // الساعات والأهداف
              echo "<td class='group-hours'>" . $row['hours_monthly_target'] . "</td>";
              echo "<td class='group-hours'>" . $row['forecasted_contracted_hours'] . "</td>";

              // أطراف العقد
              echo "<td class='group-parties'>" . (isset($row['first_party']) ? $row['first_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['second_party']) ? $row['second_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_one']) ? $row['witness_one'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_two']) ? $row['witness_two'] : '-') . "</td>";

              // الخدمات المقدمة
              $transportationText = isset($row['transportation']) && $row['transportation'] ? $row['transportation'] : '-';
              $accommodationText = isset($row['accommodation']) && $row['accommodation'] ? $row['accommodation'] : '-';
              $place_for_livingText = isset($row['place_for_living']) && $row['place_for_living'] ? $row['place_for_living'] : '-';
              $workshopText = isset($row['workshop']) && $row['workshop'] ? $row['workshop'] : '-';

              echo "<td class='group-services'>" . $transportationText . "</td>";
              echo "<td class='group-services'>" . $accommodationText . "</td>";
              echo "<td class='group-services'>" . $place_for_livingText . "</td>";
              echo "<td class='group-services'>" . $workshopText . "</td>";

              // التشغيل اليومي
              echo "<td class='group-operations'>" . (isset($row['daily_work_hours']) ? $row['daily_work_hours'] : '-') . "</td>";
              echo "<td class='group-operations'>" . (isset($row['daily_operators']) ? $row['daily_operators'] : '-') . "</td>";

              // البيانات المالية
              echo "<td class='group-basic'>" . (isset($row['price_currency_contract']) && $row['price_currency_contract'] ? $row['price_currency_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['paid_contract']) && $row['paid_contract'] ? $row['paid_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_time']) && $row['payment_time'] ? $row['payment_time'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['guarantees']) && $row['guarantees'] ? $row['guarantees'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_date']) && $row['payment_date'] ? $row['payment_date'] : '-') . "</td>";

              // الحالة والإجراءات
              echo "<td class='group-status'>" . $status . "</td>";

              echo "<td class='group-status'>
                        <a href='javascript:void(0)' class='editBtn'
             data-id='" . $row['id'] . "'
             data-project_id='" . $row['project_id'] . "'
             data-mine_id='" . (isset($row['mine_id']) ? $row['mine_id'] : '') . "'
             data-project_contract_id='" . (isset($row['project_contract_id']) ? $row['project_contract_id'] : '') . "'
             data-contract_signing_date='" . $row['contract_signing_date'] . "'
             data-grace_period_days='" . $row['grace_period_days'] . "'
             data-contract_duration_days='" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "'
             data-actual_start='" . $row['actual_start'] . "'
             data-actual_end='" . $row['actual_end'] . "'
             data-hours_monthly_target='" . $row['hours_monthly_target'] . "'
             daily_work_hours ='" . $row['daily_work_hours'] . "'
              daily_operators ='" . $row['daily_operators'] . "'
               first_party ='" . $row['first_party'] . "'
                second_party ='" . $row['second_party'] . "'
                 witness_one ='" . $row['witness_one'] . "'
                  witness_two ='" . $row['witness_two'] . "'
                  transportation ='" . $row['transportation'] . "'
                  accommodation ='" . $row['accommodation'] . "'
                  place_for_living ='" . $row['place_for_living'] . "'
                  workshop ='" . $row['workshop'] . "'
                  equip_shifts_contract ='" . (isset($row['equip_shifts_contract']) ? $row['equip_shifts_contract'] : 0) . "'
                  shift_contract ='" . (isset($row['shift_contract']) ? $row['shift_contract'] : 0) . "'
                  equip_total_contract_daily ='" . (isset($row['equip_total_contract_daily']) ? $row['equip_total_contract_daily'] : 0) . "'
                  total_contract_permonth ='" . (isset($row['total_contract_permonth']) ? $row['total_contract_permonth'] : 0) . "'
                  total_contract_units ='" . (isset($row['total_contract_units']) ? $row['total_contract_units'] : 0) . "'
                  price_currency_contract ='" . (isset($row['price_currency_contract']) ? $row['price_currency_contract'] : '') . "'
                  paid_contract ='" . (isset($row['paid_contract']) ? $row['paid_contract'] : '') . "'
                  payment_time ='" . (isset($row['payment_time']) ? $row['payment_time'] : '') . "'
                  guarantees ='" . (isset($row['guarantees']) ? $row['guarantees'] : '') . "'
                  payment_date ='" . (isset($row['payment_date']) ? $row['payment_date'] : '') . "'
                  
             data-forecasted_contracted_hours='" . $row['forecasted_contracted_hours'] . "'
             class='btn btn-action btn-action-edit'><i class='fas fa-edit'></i></a>
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"هل أنت متأكد؟\")' class='btn btn-action btn-action-delete'><i class='fas fa-trash-alt'></i></a>
                        <a href='supplierscontracts_details.php?id=" . $row['id'] . "' class='btn btn-action btn-action-view'><i class='fas fa-eye'></i></a>
                      </td>";
              echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <!-- JS -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
      // تشغيل DataTable بالعربية


      $(document).ready(function () {
        $('#projectsTable').DataTable({
          dom: 'Bfrtip', // Buttons + Search + Pagination
          buttons: [
            { extend: 'copy', text: 'نسخ' },
            { extend: 'excel', text: 'تصدير Excel' },
            { extend: 'csv', text: 'تصدير CSV' },
            { extend: 'pdf', text: 'تصدير PDF' },
            { extend: 'print', text: 'طباعة' }
          ],
          "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
          }
        });
      });


      // التحكم في إظهار وإخفاء الفورم
      const toggleContractFormBtn = document.getElementById('toggleForm');
      const contractForm = document.getElementById('projectForm');

      toggleContractFormBtn.addEventListener('click', function () {
        contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
      });
    })();

  </script>

  <script>
    const $el = (sel) => document.querySelector(sel);
    let equipmentIndex = 1;

    const fields = {
      contractDays: $el('#contract_duration_days'),
      actualStart: $el('#actual_start'),
      actualEnd: $el('#actual_end'),
      kpiMonthTotal: $el('#kpi_month_total'),
      kpiContractTotal: $el('#kpi_contract_total'),
      kpiEquipMonth: $el('#kpi_equip_month'),
      hoursMonthlyTarget: $el('#hours_monthly_target'),
      forecastedContractedHours: $el('#forecasted_contracted_hours'),
    };

    function num(v) {
      const n = parseFloat(v);
      return isFinite(n) ? n : 0;
    }

    function fmt(n) {
      return new Intl.NumberFormat('ar-EG').format(Math.max(0, Math.round(n)));
    }

    // حساب مدة العقد بالأيام من التاريخين
    function calculateDaysFromDates() {
      const startDate = fields.actualStart.value;
      const endDate = fields.actualEnd.value;

      if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        fields.contractDays.value = diffDays;
      } else {
        fields.contractDays.value = '';
      }
    }

    // تحديث حساب الأيام عند تغيير التواريخ
    fields.actualStart.addEventListener('change', calculateDaysFromDates);
    fields.actualEnd.addEventListener('change', calculateDaysFromDates);

    // إضافة قسم معدات جديد
    function addEquipmentSection() {
      equipmentIndex++;
      const newSection = document.createElement('div');
      newSection.className = 'equipment-section';
      newSection.setAttribute('data-index', equipmentIndex);
      newSection.innerHTML = `
        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h6 style="margin: 0;">المعدات رقم ${equipmentIndex}</h6>
            <button type="button" class="removeEquipmentBtn" data-index="${equipmentIndex}" 
              style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
              <i class="fa fa-trash"></i> حذف
            </button>
          </div>
          <div class="form-grid">
            <div class="field md-3 sm-6">
              <label>نوع المعدة</label>
              <div class="control">
                <select name="equip_type_${equipmentIndex}" class="equip-type">
                  <option value="">— اختر —</option>
                  <option value="حفار">حفار</option>
                  <option value="قلاب">قلاب</option>
                  <option value="خرامة">خرامة</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>حجم المعدة (Size)</label>
              <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="مثال: 340"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المعدات</label>
              <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد المشغلين</label>
              <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المساعدين</label>
              <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>عدد الورديات</label>
              <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2"></div>
            </div>

            <!-- أوقات الورديات -->
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
              <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" placeholder="مثال: 08:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
              <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" placeholder="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
              <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" placeholder="مثال: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
              <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" placeholder="مثال: 00:00"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>وحدة القياس</label>
              <div class="control">
                <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                  <option value="">— اختر —</option>
                  <option value="ساعة">ساعة</option>
                  <option value="طن">طن</option>
                  <option value="متر طولي">متر طولي</option>
                  <option value="متر مكعب">متر مكعب</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>ساعات الوردية</label>
              <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>إجمالي الساعات يومياً</label>
              <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>وحدات العمل في الشهر</label>
              <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>إجمالي ساعات العقد</label>
              <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>العملة</label>
              <div class="control">
                <select name="equip_price_currency_${equipmentIndex}">
                  <option value="">— اختر —</option>
                  <option value="دولار">دولار</option>
                  <option value="جنيه">جنيه</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>السعر</label>
              <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00"></div>
            </div>
            <div class="field md-3 sm-6">
            </div>
            <div class="field md-3 sm-6">
              <label>عدد المشرفين</label>
              <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>عدد الفنيين</label>
              <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0"></div>
            </div>
          </div>
        </div>
      `;
      document.getElementById('equipmentSections').appendChild(newSection);

      // إضافة event listeners للحقول الجديدة
      newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));

      // إضافة event listener لزر الحذف
      newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
        newSection.remove();
        recalc();
      });
    }

    function recalc() {
      const days = num(fields.contractDays.value);

      // حساب إجمالي المعدات
      let totalEquipMonth = 0;
      let totalEquipContract = 0;

      // حساب كل قسم معدات
      document.querySelectorAll('.equipment-section').forEach(section => {
        const index = section.getAttribute('data-index');
        const countInput = section.querySelector(`input[name="equip_count_${index}"]`);
        const targetInput = section.querySelector(`input[name="shift_hours_${index}"]`);
        const monthInput = section.querySelector(`input[name="equip_total_month_${index}"]`);
        const contractInput = section.querySelector(`input[name="equip_total_contract_${index}"]`);

        if (countInput && targetInput) {
          const count = num(countInput.value);
          const target = num(targetInput.value);
          const sectionMonth = count * target;
          // حساب إجمالي الساعات على أساس الأيام بدلاً من الشهور
          // نفترض أن الـ target هو الساعات اليومية للمعدة
          const sectionContract = sectionMonth * days;

          monthInput.value = sectionMonth;
          contractInput.value = sectionContract;

          totalEquipMonth += sectionMonth;
          totalEquipContract += sectionContract;
        }
      });

      const monthTotal = totalEquipMonth;
      const contractTotal = totalEquipContract;

      fields.kpiEquipMonth.textContent = fmt(totalEquipMonth);
      fields.kpiMonthTotal.textContent = fmt(monthTotal);
      fields.kpiContractTotal.textContent = fmt(contractTotal);

      fields.hoursMonthlyTarget.value = monthTotal;
      fields.forecastedContractedHours.value = contractTotal;
    }

    // تشغيل الحسبة عند تغيير أي مدخل
    document.addEventListener('input', function (e) {
      if (e.target.closest('#projectForm')) {
        recalc();
      }
    });

    // زر إضافة المعدات
    document.getElementById('addEquipmentBtn').addEventListener('click', function (e) {
      e.preventDefault();
      addEquipmentSection();
    });

    // جلب الفورم
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(recalc, 0));
    }

    // أول تشغيل
    recalc();

    // جلب مناجم المشروع عند تغيير المشروع
    $('#project_id').on('change', function () {
      const projectId = $(this).val();
      $('#mine_id').prop('disabled', true).html('<option value="">— جاري التحميل... —</option>');
      $('#project_contract_id').prop('disabled', true).html('<option value="">— اختر المنجم أولاً —</option>');
      $('#projectHoursInfo').fadeOut();

      if (projectId) {
        $.ajax({
          url: 'get_project_mines.php',
          type: 'POST',
          data: { project_id: projectId },
          dataType: 'json',
          success: function (response) {
            if (response.success && response.mines.length > 0) {
              let options = '<option value="">— اختر المنجم —</option>';
              response.mines.forEach(function (mine) {
                options += `<option value="${mine.id}">${mine.display_name}</option>`;
              });
              $('#mine_id').html(options).prop('disabled', false);
            } else {
              $('#mine_id').html('<option value="">— لا توجد مناجم لهذا المشروع —</option>').prop('disabled', true);
            }
          },
          error: function () {
            $('#mine_id').html('<option value="">— خطأ في التحميل —</option>').prop('disabled', true);
          }
        });
      } else {
        $('#mine_id').html('<option value="">— اختر المشروع أولاً —</option>').prop('disabled', true);
        $('#project_contract_id').html('<option value="">— اختر المنجم أولاً —</option>').prop('disabled', true);
      }
    });

    // جلب عقود المنجم عند تغيير المنجم
    $('#mine_id').on('change', function () {
      const mineId = $(this).val();
      $('#project_contract_id').prop('disabled', true).html('<option value="">— جاري التحميل... —</option>');
      $('#projectHoursInfo').fadeOut();

      if (mineId) {
        $.ajax({
          url: 'get_mine_contracts.php',
          type: 'POST',
          data: { mine_id: mineId },
          dataType: 'json',
          success: function (response) {
            if (response.success && response.contracts.length > 0) {
              let options = '<option value="">— اختر العقد —</option>';
              response.contracts.forEach(function (contract) {
                options += `<option value="${contract.id}">${contract.display_name}</option>`;
              });
              $('#project_contract_id').html(options).prop('disabled', false);
            } else {
              $('#project_contract_id').html('<option value="">— لا توجد عقود لهذا المنجم —</option>').prop('disabled', true);
            }
          },
          error: function () {
            $('#project_contract_id').html('<option value="">— خطأ في التحميل —</option>').prop('disabled', true);
          }
        });
      } else {
        $('#project_contract_id').html('<option value="">— اختر المنجم أولاً —</option>').prop('disabled', true);
      }
    });

    // جلب بيانات ساعات العقد عند تغيير العقد
    $('#project_contract_id').on('change', function () {
      const contractId = $(this).val();
      const supplierContractId = $('#contract_id').val();
      if (contractId) {
        $.ajax({
          url: 'get_project_hours.php',
          type: 'POST',
          data: {
            project_contract_id: contractId,
            supplier_contract_id: supplierContractId || 0
          },
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              $('#contractTotalHours').text(new Intl.NumberFormat('ar-EG').format(response.contract_total_hours));
              $('#suppliersContractedHours').text(new Intl.NumberFormat('ar-EG').format(response.suppliers_contracted_hours));
              $('#remainingHours').text(new Intl.NumberFormat('ar-EG').format(response.remaining_hours));

              // عرض تفصيل المعدات
              var breakdownDiv = $('#equipmentBreakdown');
              breakdownDiv.empty();

              if (response.equipment_breakdown && response.equipment_breakdown.length > 0) {
                var breakdownHtml = '<div style="color: #555;"><strong style="color: #1976d2; display: block; margin-bottom: 0.5rem;">تفصيل الساعات:</strong>';

                response.equipment_breakdown.forEach(function (item) {
                  var percentage = ((item.hours / response.contract_total_hours) * 100).toFixed(1);
                  breakdownHtml += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; padding: 0.3rem 0;">';
                  breakdownHtml += '<span><i class="fas fa-tools" style="color: #1976d2; margin-left: 0.3rem;"></i>' + item.type + '</span>';
                  breakdownHtml += '<span style="font-weight: 600; color: #0d47a1;">' + new Intl.NumberFormat('ar-EG').format(item.hours) + ' ساعة (' + percentage + '%)</span>';
                  breakdownHtml += '</div>';
                });

                breakdownHtml += '</div>';
                breakdownDiv.html(breakdownHtml);
              } else {
                breakdownDiv.html('<span style="color: #999; font-style: italic;">لا توجد معدات مسجلة لهذا العقد</span>');
              }

              $('#projectHoursInfo').fadeIn();
            } else {
              $('#projectHoursInfo').fadeOut();
            }
          },
          error: function () {
            $('#projectHoursInfo').fadeOut();
          }
        });
      } else {
        $('#projectHoursInfo').fadeOut();
      }
    });

    // تعبئة الفورم عند التعديل
    $(document).on("click", ".editBtn", function () {
      $("#projectForm").show();
      $("#contract_id").val($(this).data("id"));

      // تحميل المشروع والمنجم والعقد
      const projectId = $(this).data("project_id");
      const mineId = $(this).data("mine_id");
      const projectContractId = $(this).data("project_contract_id");

      $("#project_id").val(projectId);

      // تحميل مناجم المشروع أولاً
      if (projectId) {
        $.ajax({
          url: 'get_project_mines.php',
          type: 'POST',
          data: { project_id: projectId },
          dataType: 'json',
          success: function (response) {
            if (response.success && response.mines.length > 0) {
              let mineOptions = '<option value="">— اختر المنجم —</option>';
              response.mines.forEach(function (mine) {
                const selected = mine.id == mineId ? 'selected' : '';
                mineOptions += `<option value="${mine.id}" ${selected}>${mine.display_name}</option>`;
              });
              $('#mine_id').html(mineOptions).prop('disabled', false);

              // تحميل عقود المنجم
              if (mineId) {
                $.ajax({
                  url: 'get_mine_contracts.php',
                  type: 'POST',
                  data: { mine_id: mineId },
                  dataType: 'json',
                  success: function (contractResponse) {
                    if (contractResponse.success && contractResponse.contracts.length > 0) {
                      let options = '<option value="">— اختر العقد —</option>';
                      contractResponse.contracts.forEach(function (contract) {
                        const selected = contract.id == projectContractId ? 'selected' : '';
                        options += `<option value="${contract.id}" ${selected}>${contract.display_name}</option>`;
                      });
                      $('#project_contract_id').html(options).prop('disabled', false);

                      // تفعيل تحميل بيانات الساعات
                      if (projectContractId) {
                        $('#project_contract_id').trigger('change');
                      }
                    }
                  }
                });
              }
            }
          }
        });
      }

      $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
      $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
      $("#projectForm [name='contract_duration_days']").val($(this).data("contract_duration_days"));
      $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
      $("#projectForm [name='actual_end']").val($(this).data("actual_end"));


      $("#projectForm [name='hours_monthly_target']").val($(this).data("hours_monthly_target"));
      $("#projectForm [name='forecasted_contracted_hours']").val($(this).data("forecasted_contracted_hours"));

      $("#projectForm [name='daily_work_hours']").val($(this).attr("daily_work_hours"));

      $("#projectForm [name='daily_operators']").val($(this).attr("daily_operators"));

      // تحميل الحقول الإضافية للعقد
      $("#projectForm [name='equip_shifts_contract']").val($(this).attr("equip_shifts_contract"));
      $("#projectForm [name='shift_contract']").val($(this).attr("shift_contract"));
      $("#projectForm [name='equip_total_contract']").val($(this).attr("equip_total_contract_daily"));
      $("#projectForm [name='total_contract_permonth']").val($(this).attr("total_contract_permonth"));
      $("#projectForm [name='total_contract']").val($(this).attr("total_contract_units"));

      $("#projectForm [name='first_party']").val($(this).attr("first_party"));
      $("#projectForm [name='second_party']").val($(this).attr("second_party"));
      $("#projectForm [name='witness_one']").val($(this).attr("witness_one"));
      $("#projectForm [name='witness_two']").val($(this).attr("witness_two"));
      $("#projectForm [name='transportation']").val($(this).attr("transportation"));
      $("#projectForm [name='accommodation']").val($(this).attr("accommodation"));
      $("#projectForm [name='place_for_living']").val($(this).attr("place_for_living"));
      $("#projectForm [name='workshop']").val($(this).attr("workshop"));

      // البيانات المالية الجديدة
      $("#projectForm [name='price_currency_contract']").val($(this).attr("price_currency_contract"));
      $("#projectForm [name='paid_contract']").val($(this).attr("paid_contract"));
      $("#projectForm [name='payment_time']").val($(this).attr("payment_time"));
      $("#projectForm [name='guarantees']").val($(this).attr("guarantees"));
      $("#projectForm [name='payment_date']").val($(this).attr("payment_date"));

      // تحميل المعدات الخاصة بالعقد
      const contractId = $(this).data("id");
      $.ajax({
        url: 'get_supplier_contract_equipments.php',
        type: 'POST',
        data: { contract_id: contractId },
        dataType: 'json',
        success: function (equipments) {
          // مسح الأقسام القديمة ما عدا الأول
          $('#equipmentSections .equipment-section').not(':first').remove();
          equipmentIndex = 1;

          // تحميل المعدات
          if (equipments.length > 0) {
            equipments.forEach(function (equip, index) {
              const sectionIndex = index + 1;

              if (sectionIndex === 1) {
                // تحديث القسم الأول
                $(`select[name="equip_type_1"]`).val(equip.equip_type);
                $(`input[name="equip_size_1"]`).val(equip.equip_size);
                $(`input[name="equip_count_1"]`).val(equip.equip_count);
                $(`input[name="equip_shifts_1"]`).val(equip.equip_shifts);
                $(`select[name="equip_unit_1"]`).val(equip.equip_unit);
                $(`input[name="shift1_start_1"]`).val(equip.shift1_start);
                $(`input[name="shift1_end_1"]`).val(equip.shift1_end);
                $(`input[name="shift2_start_1"]`).val(equip.shift2_start);
                $(`input[name="shift2_end_1"]`).val(equip.shift2_end);
                $(`input[name="shift_hours_1"]`).val(equip.shift_hours);
                $(`input[name="equip_total_month_1"]`).val(equip.equip_total_month);
                $(`input[name="equip_total_contract_1"]`).val(equip.equip_total_contract);
                $(`input[name="equip_price_1"]`).val(equip.equip_price);
                $(`select[name="equip_price_currency_1"]`).val(equip.equip_price_currency);
                $(`input[name="equip_operators_1"]`).val(equip.equip_operators);
                $(`input[name="equip_supervisors_1"]`).val(equip.equip_supervisors);
                $(`input[name="equip_technicians_1"]`).val(equip.equip_technicians);
                $(`input[name="equip_assistants_1"]`).val(equip.equip_assistants);
                equipmentIndex = 1;
              } else {
                // إضافة أقسام جديدة
                equipmentIndex++;
                const newSection = document.createElement('div');
                newSection.className = 'equipment-section';
                newSection.setAttribute('data-index', equipmentIndex);
                newSection.innerHTML = `
                  <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                      <h6 style="margin: 0;">المعدات رقم ${equipmentIndex}</h6>
                      <button type="button" class="removeEquipmentBtn" data-index="${equipmentIndex}" 
                        style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-trash"></i> حذف
                      </button>
                    </div>
                    <div class="form-grid">
                      <div class="field md-3 sm-6">
                        <label>نوع المعدة</label>
                        <div class="control">
                          <select name="equip_type_${equipmentIndex}" class="equip-type">
                            <option value="">— اختر —</option>
                            <option value="حفار" ${equip.equip_type === 'حفار' ? 'selected' : ''}>حفار</option>
                            <option value="قلاب" ${equip.equip_type === 'قلاب' ? 'selected' : ''}>قلاب</option>
                            <option value="خرامة" ${equip.equip_type === 'خرامة' ? 'selected' : ''}>خرامة</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>حجم المعدة (Size)</label>
                        <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="مثال: 340" value="${equip.equip_size}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المعدات</label>
                        <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0" value="${equip.equip_count}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المشغلين</label>
                        <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0" value="${equip.equip_operators}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المساعدين</label>
                        <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0" value="${equip.equip_assistants}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الورديات</label>
                        <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="مثال: 2" value="${equip.equip_shifts}"></div>
                      </div>
                      
                      <!-- أوقات الورديات -->
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الأولى</label>
                        <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" value="${equip.shift1_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الأولى</label>
                        <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" value="${equip.shift1_end || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> بداية الوردية الثانية</label>
                        <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" value="${equip.shift2_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> نهاية الوردية الثانية</label>
                        <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" value="${equip.shift2_end || ''}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>وحدة القياس</label>
                        <div class="control">
                          <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                            <option value="">— اختر —</option>
                            <option value="ساعة" ${equip.equip_unit === 'ساعة' ? 'selected' : ''}>ساعة</option>
                            <option value="طن" ${equip.equip_unit === 'طن' ? 'selected' : ''}>طن</option>
                            <option value="متر طولي" ${equip.equip_unit === 'متر طولي' ? 'selected' : ''}>متر طولي</option>
                            <option value="متر مكعب" ${equip.equip_unit === 'متر مكعب' ? 'selected' : ''}>متر مكعب</option>
                          </select>
                        </div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>ساعات الوردية</label>
                        <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0" value="${equip.shift_hours}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي الساعات يومياً</label>
                        <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_month}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>وحدات العمل في الشهر</label>
                        <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0" value="${equip.equip_monthly_target || 0}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>إجمالي ساعات العقد</label>
                        <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="يُحتسب تلقائياً" value="${equip.equip_total_contract}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>العملة</label>
                        <div class="control">
                          <select name="equip_price_currency_${equipmentIndex}">
                            <option value="">— اختر —</option>
                            <option value="دولار" ${equip.equip_price_currency === 'دولار' ? 'selected' : ''}>دولار</option>
                            <option value="جنيه" ${equip.equip_price_currency === 'جنيه' ? 'selected' : ''}>جنيه</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>السعر</label>
                        <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" value="${equip.equip_price}"></div>
                      </div>
                       <div class="field md-3 sm-6">
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد المشرفين</label>
                        <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0" value="${equip.equip_supervisors}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>عدد الفنيين</label>
                        <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0" value="${equip.equip_technicians}"></div>
                      </div>
                    </div>
                  </div>
                `;
                document.getElementById('equipmentSections').appendChild(newSection);

                // إضافة event listeners
                newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
                newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
                  newSection.remove();
                  recalc();
                });
              }
            });
          }

          recalc();
        }
      });

      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });

    // ==================== Group Toggle Functionality ====================
    // حفظ حالة المجموعات في localStorage
    const groupStates = JSON.parse(localStorage.getItem('supplierContractGroupStates')) || {
      basic: true,
      dates: true,
      hours: true,
      parties: false,
      services: false,
      operations: false,
      status: true
    };

    // تطبيق الحالة المحفوظة عند تحميل الصفحة
    function applyGroupStates() {
      Object.keys(groupStates).forEach(group => {
        const isActive = groupStates[group];
        const btn = $(`.btn-group-toggle[data-group="${group}"]`);
        const columns = $(`.group-${group}`);

        if (isActive) {
          btn.addClass('active');
          columns.removeClass('group-hidden');
        } else {
          btn.removeClass('active');
          columns.addClass('group-hidden');
        }
      });
    }

    // تطبيق الحالة عند تحميل الصفحة
    applyGroupStates();

    // التحكم في إظهار/إخفاء المجموعات
    $('.btn-group-toggle').on('click', function () {
      const group = $(this).data('group');
      const isActive = $(this).hasClass('active');

      if (isActive) {
        // إخفاء المجموعة
        $(this).removeClass('active');
        $(`.group-${group}`).addClass('group-hidden');
        groupStates[group] = false;
      } else {
        // إظهار المجموعة
        $(this).addClass('active');
        $(`.group-${group}`).removeClass('group-hidden');
        groupStates[group] = true;
      }

      // حفظ الحالة
      localStorage.setItem('supplierContractGroupStates', JSON.stringify(groupStates));
    });

    // زر إظهار/إخفاء الكل
    $('.btn-group-toggle-all').on('click', function () {
      const allActive = Object.values(groupStates).every(state => state);

      if (allActive) {
        // إخفاء الكل
        $('.btn-group-toggle').removeClass('active');
        $('[class*="group-"]').addClass('group-hidden');
        Object.keys(groupStates).forEach(key => groupStates[key] = false);
        $(this).html('<i class="fas fa-eye-slash"></i> إخفاء الكل');
      } else {
        // إظهار الكل
        $('.btn-group-toggle').addClass('active');
        $('[class*="group-"]').removeClass('group-hidden');
        Object.keys(groupStates).forEach(key => groupStates[key] = true);
        $(this).html('<i class="fas fa-eye"></i> الكل');
      }

      // حفظ الحالة
      localStorage.setItem('supplierContractGroupStates', JSON.stringify(groupStates));
    });

    // تحديث نص زر "الكل" عند التحميل
    $(document).ready(function () {
      const allActive = Object.values(groupStates).every(state => state);
      if (allActive) {
        $('.btn-group-toggle-all').html('<i class="fas fa-eye"></i> الكل');
      } else {
        $('.btn-group-toggle-all').html('<i class="fas fa-eye-slash"></i> إظهار الكل');
      }
    });
  </script>


</body>

</html>