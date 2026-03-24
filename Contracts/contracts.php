<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}

require_once '../config.php';
require_once '../includes/permissions_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
  header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+Ø¨ÙŠØ¦Ø©+Ø´Ø±ÙƒØ©+ØµØ§Ù„Ø­Ø©+Ù„Ù„Ù…Ø³ØªØ®Ø¯Ù…+âŒ");
  exit();
}

$contracts_has_company = db_table_has_column($conn, 'contracts', 'company_id');

$contract_scope_sql = "1=1";
if (!$is_super_admin) {
  if ($contracts_has_company) {
    $contract_scope_sql = "c.company_id = $company_id";
  } else {
    $contract_scope_sql = "EXISTS (
      SELECT 1
      FROM mines sm
      INNER JOIN project sp ON sp.id = sm.project_id
      LEFT JOIN users su ON su.id = sp.created_by
      LEFT JOIN clients sc ON sc.id = sp.company_client_id
      LEFT JOIN users scu ON scu.id = sc.created_by
      WHERE sm.id = c.mine_id
        AND (su.company_id = $company_id OR scu.company_id = $company_id)
    )";
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ðŸ” Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
$page_permissions = check_page_permissions($conn, 'Contracts/contracts.php');
$can_view = $page_permissions['can_view'];
$can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];

// Ù…Ù†Ø¹ Ø§Ù„ÙˆØµÙˆÙ„ Ø¥Ø°Ø§ Ù„Ù… ØªÙƒÙ† ØµÙ„Ø§Ø­ÙŠØ© Ø¹Ø±Ø¶
if (!$can_view) {
  header("Location: ../login.php?msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¹Ø±Ø¶+Ø§Ù„Ø¹Ù‚ÙˆØ¯+âŒ");
  exit();
}

$equipmentTypes = [];
$equipmentTypesQuery = "SELECT id, type FROM equipments_types WHERE status = 'active' ORDER BY type ASC";
$equipmentTypesResult = mysqli_query($conn, $equipmentTypesQuery);
if ($equipmentTypesResult) {
  while ($row = mysqli_fetch_assoc($equipmentTypesResult)) {
    $equipmentTypes[] = $row;
  }
}

$equipmentTypeOptionsHtml = '<option value="">â€” Ø§Ø®ØªØ± â€”</option>';
foreach ($equipmentTypes as $equipmentType) {
  $typeId = (int) $equipmentType['id'];
  $typeName = htmlspecialchars($equipmentType['type'], ENT_QUOTES, 'UTF-8');
  $equipmentTypeOptionsHtml .= '<option value="' . $typeId . '">' . $typeName . '</option>';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø§Ù„Ø¹Ù‚ÙˆØ¯</title>
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
  <!-- CSS Ø§Ù„Ù…ÙˆÙ‚Ø¹ -->
  <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/main_admin_style.css" />

</head>

<body>

  <?php include('../insidebar.php'); ?>

  <div class="main">

    <div class="page-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div class="title-icon"><i class="fas fa-file-contract"></i></div>
        <h1 class="page-title">Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¹Ù‚ÙˆØ¯</h1>
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="../main/dashboard.php" class="back-btn">
          <i class="fas fa-arrow-right"></i> Ø±Ø¬ÙˆØ¹
        </a>
        <?php if ($can_add): ?>
        <a href="javascript:void(0)" id="toggleForm" class="add-btn">
          <i class="fas fa-plus-circle"></i> Ø¹Ù‚Ø¯ Ø¬Ø¯ÙŠØ¯
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯ -->
    <form id="projectForm" action="" method="post" style="display:none;">

      <div class="card">
        <div class="card-header">
          <h5>
            <i class="fas fa-file-signature"></i> Ø¥Ø¶Ø§ÙØ© / ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯
          </h5>
        </div>
        <div class="card-body">

          <input type="hidden" name="id" id="contract_id" value="">

          <input type="hidden" name="mine_id" placeholder="Ù…Ø¹Ø±Ù Ø§Ù„Ù…Ù†Ø¬Ù…"
            value="<?php echo isset($_GET['id']) ? intval($_GET['id']) : 0; ?>" required />

          <!-- Ø§Ù„Ù‚Ø³Ù… 1: Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª Ø§Ù„Ø³Ø§Ø¹Ø§Øª (ÙŠÙˆÙ…ÙŠØ§Ù‹ ÙˆÙ„Ù„Ø¹Ù‚Ø¯) -->
          <div class="section-title"><span class="chip">1</span> Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª Ø§Ù„Ø³Ø§Ø¹Ø§Øª (ÙŠÙˆÙ…ÙŠØ§Ù‹ ÙˆÙ„Ù„Ø¹Ù‚Ø¯)</div>
          <br>

          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…ÙŠØ© Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">Ù…Ø¹Ø¯Ø§Øª Ã— Ø³Ø§Ø¹Ø§Øª Ù„Ù„ÙŠÙˆÙ…</div>
            </div>
          </div>

          <div
            style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 10px; border-right: 4px solid #667eea;">
            <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
              <i class="fas fa-info-circle"></i> <strong>Ù…Ù„Ø§Ø­Ø¸Ø©:</strong> ÙŠØªÙ… Ø­Ø³Ø§Ø¨ Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰
              Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¯Ø®Ù„Ø© ÙÙŠ Ø§Ù„Ø£Ù‚Ø³Ø§Ù… Ø§Ù„ØªØ§Ù„ÙŠØ©
            </p>
          </div>

          <hr class="hr" />

          <div class="section-title"><span class="chip">2</span> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© Ù„Ù„Ø¹Ù…ÙŠÙ„ ÙˆØ§Ù„Ø¹Ù‚Ø¯</div>
          <br>

          <div class="form-grid">

            <!-- ØµÙ 1: 3 Ø®Ø§Ù†Ø§Øª -->
            <div class="field md-3 sm-6">
              <label>ØªØ§Ø±ÙŠØ® ØªÙˆÙ‚ÙŠØ¹ Ø§Ù„Ø¹Ù‚Ø¯ </label>
              <div class="control"><input name="contract_signing_date" id="contract_signing_date" type="date"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>ÙØªØ±Ø© Ø§Ù„Ø³Ù…Ø§Ø­ Ø¨ÙŠÙ† Ø§Ù„ØªÙˆÙ‚ÙŠØ¹ ÙˆØ§Ù„ØªÙ†ÙÙŠØ° </label>
              <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0"
                  placeholder="Ø¹Ø¯Ø¯ Ø§Ù„Ø£ÙŠØ§Ù…"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ° Ø§Ù„ÙØ¹Ù„ÙŠ Ø§Ù„Ù…ØªÙÙ‚ Ø¹Ù„ÙŠÙ‡</label>
              <div class="control"><input name="actual_start" id="actual_start" type="date"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ° Ø§Ù„ÙØ¹Ù„ÙŠ Ø§Ù„Ù…ØªÙÙ‚ Ø¹Ù„ÙŠÙ‡</label>
              <div class="control"><input name="actual_end" id="actual_end" type="date"></div>
            </div>



            <!-- Ø®Ø§Ù†ØªØ§Ù† ÙØ§Ø±ØºØªØ§Ù† -->


            <!-- ØµÙ 2: 3 Ø®Ø§Ù†Ø§Øª -->

            <div class="field md-3 sm-6">
              <label>Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ Ø¨Ø§Ù„Ø£ÙŠØ§Ù… </label>
              <div class="control"><input name="contract_duration_days" id="contract_duration_days" type="number"
                  min="0" placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹" readonly></div>
            </div>





            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø¹Ù…Ù„Ø©</label>
              <div class="control">
                <select name="price_currency_contract" id="price_currency_contract">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ø¯ÙˆÙ„Ø§Ø±">Ø¯ÙˆÙ„Ø§Ø±</option>
                  <option value="Ø¬Ù†ÙŠÙ‡">Ø¬Ù†ÙŠÙ‡</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø¯ÙÙˆØ¹</label>
              <div class="control"><input name="paid_contract" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>ÙˆÙ‚Øª Ø§Ù„Ø¯ÙØ¹</label>
              <div class="control">
                <select name="payment_time" id="payment_time">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ù…Ù‚Ø¯Ù…">Ù…Ù‚Ø¯Ù…</option>
                  <option value=" Ù…Ø¤Ø®Ø±">Ù…Ø¤Ø®Ø± </option>

                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> Ø§Ù„Ø¶Ù…Ø§Ù†Ø§Øª</label>
              <div class="control"><input name="guarantees" type="text"></div>
            </div>

            <div class="field md-3 sm-6">
              <label> ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¯ÙØ¹</label>
              <div class="control"><input name="payment_date" id="payment_date" type="date"></div>
            </div>











            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª Ù„Ù„Ø¹Ù‚Ø¯ </label>
              <div class="control"><input name="equip_shifts_contract" type="number" min="0" placeholder="Ù…Ø«Ø§Ù„: 2">
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label> Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ù„Ù„Ø¹Ù‚Ø¯</label>
              <div class="control"><input name="shift_contract" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„ÙˆØ­Ø¯Ø§Øª ÙŠÙˆÙ…ÙŠØ§Ù‹ Ù„Ù„Ø¹Ù‚Ø¯ </label>
              <div class="control"><input name="equip_total_contract" type="number" placeholder=" "></div>
            </div>
            <div class="field md-3 sm-6">
              <label>ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ÙÙŠ Ø§Ù„Ø´Ù‡Ø± Ù„Ù„Ø¹Ù‚Ø¯</label>
              <div class="control"><input name="total_contract_permonth" type="number" min="0"></div>
            </div>


            <div class="field md-3 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯ </label>
              <div class="control"><input name="total_contract" type="number" placeholder=" "></div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ù…Ø¯Ø±Ø§Ø¡ Ø§Ù„Ù…ÙˆÙ‚Ø¹ </label>
              <div class="control"><input type="number" name="daily_operators" id="daily_operators" min="0"
                  placeholder="Ù…Ø«Ø§Ù„: 3"></div>
            </div>



            <div class="field md-3 sm-6">
              <label>Ø§Ù„ØªØ±Ø­ÙŠÙ„ (Transportation)</label>
              <div class="control">
                <select name="transportation" id="transportation">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</option>
                  <option value="Ø¨Ø¯ÙˆÙ†">Ø¨Ø¯ÙˆÙ†</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø³ÙƒÙ† (Place for Living)</label>
              <div class="control">
                <select name="place_for_living" id="place_for_living">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</option>
                  <option value="Ø¨Ø¯ÙˆÙ†">Ø¨Ø¯ÙˆÙ†</option>
                </select>
              </div>
            </div>
            <!-- ØµÙ 3: 3 Ø®Ø§Ù†Ø§Øª -->
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø¥Ø¹Ø§Ø´Ø© (Accommodation)</label>
              <div class="control">
                <select name="accommodation" id="accommodation">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</option>
                  <option value="Ø¨Ø¯ÙˆÙ†">Ø¨Ø¯ÙˆÙ†</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø§Ù„ÙˆØ±Ø´Ø© (Workshop)</label>
              <div class="control">
                <select name="workshop" id="workshop">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø¹Ø¯Ø©</option>
                  <option value="Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹">Ù…Ø§Ù„Ùƒ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</option>
                  <option value="Ø¨Ø¯ÙˆÙ†">Ø¨Ø¯ÙˆÙ†</option>
                </select>
              </div>
            </div>
            <!-- Ø®Ø§Ù†ØªØ§Ù† ÙØ§Ø±ØºØªØ§Ù† -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>
          </div>

          <hr class="hr" />

          <!-- Ø§Ù„Ù‚Ø³Ù… 3: Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© Ù„Ù„Ù…Ø¹Ø¯Ø§Øª -->
          <div id="equipmentSections">
            <div class="section-title"><span class="chip">3</span> Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© <strong>Ù„Ù„Ù…Ø¹Ø¯Ø§Øª</strong>
            </div>
            <br>
            <div class="equipment-section" data-index="1">
              <div
                style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                <h6 style="margin: 0 0 15px 0;">Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø±Ù‚Ù… 1</h6>
                <div class="form-grid">
                  <div class="field md-3 sm-6">
                    <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©</label>
                    <div class="control">
                      <select name="equip_type_1" class="equip-type">
                        <?php echo $equipmentTypeOptionsHtml; ?>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>Ø­Ø¬Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© (Size)</label>
                    <div class="control"><input name="equip_size_1" type="number" placeholder="Ù…Ø«Ø§Ù„: 340"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</label>
                    <div class="control"><input name="equip_count_1" type="number" min="0"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span style="color: #007bff; font-weight: 600;">â– </span> Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©</label>
                    <div class="control"><input name="equip_count_basic_1" type="number" min="0"
                        style="background: #e3f2fd; border-right: 3px solid #007bff;"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label><span style="color: #ffc107; font-weight: 600;">â– </span> Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø§Ø­ØªÙŠØ§Ø·ÙŠØ©</label>
                    <div class="control"><input name="equip_count_backup_1" type="number" min="0"
                        style="background: #fffde7; border-right: 3px solid #ffc107;"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†</label>
                    <div class="control"><input name="equip_operators_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø³Ø§Ø¹Ø¯ÙŠÙ†</label>
                    <div class="control"><input name="equip_assistants_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª</label>
                    <div class="control"><input name="equip_shifts_1" type="number" min="0" placeholder="Ù…Ø«Ø§Ù„: 2"></div>
                  </div>
                  <!-- Ø£ÙˆÙ‚Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª -->
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
                    <div class="control"><input name="shift1_start_1" type="time" placeholder="Ù…Ø«Ø§Ù„: 08:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
                    <div class="control"><input name="shift1_end_1" type="time" placeholder="Ù…Ø«Ø§Ù„: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
                    <div class="control"><input name="shift2_start_1" type="time" placeholder="Ù…Ø«Ø§Ù„: 16:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
                    <div class="control"><input name="shift2_end_1" type="time" placeholder="Ù…Ø«Ø§Ù„: 00:00"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>ÙˆØ­Ø¯Ø© Ø§Ù„Ù‚ÙŠØ§Ø³</label>
                    <div class="control">
                      <select name="equip_unit_1" class="equip-unit">
                        <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                        <option value="Ø³Ø§Ø¹Ø©">Ø³Ø§Ø¹Ø©</option>
                        <option value="Ø·Ù†">Ø·Ù†</option>
                        <option value="Ù…ØªØ± Ø·ÙˆÙ„ÙŠ">Ù…ØªØ± Ø·ÙˆÙ„ÙŠ</option>
                        <option value="Ù…ØªØ± Ù…ÙƒØ¹Ø¨">Ù…ØªØ± Ù…ÙƒØ¹Ø¨</option>
                      </select>
                    </div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</label>
                    <div class="control"><input name="shift_hours_1" type="number" min="0"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„ÙˆØ­Ø¯Ø§Øª ÙŠÙˆÙ…ÙŠØ§Ù‹</label>
                    <div class="control"><input name="equip_total_month_1" type="number" readonly
                        placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ÙÙŠ Ø§Ù„Ø´Ù‡Ø±</label>
                    <div class="control"><input name="equip_target_per_month_1" type="number" min="0"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</label>
                    <div class="control"><input name="equip_total_contract_1" type="number" readonly
                        placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
                  </div>


                  <div class="field md-3 sm-6">
                    <label>Ø§Ù„Ø¹Ù…Ù„Ø©</label>
                    <div class="control">
                      <select name="equip_price_currency_1">
                        <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                        <option value="Ø¯ÙˆÙ„Ø§Ø±">Ø¯ÙˆÙ„Ø§Ø±</option>
                        <option value="Ø¬Ù†ÙŠÙ‡">Ø¬Ù†ÙŠÙ‡</option>
                      </select>
                    </div>
                  </div>
                  <div class="field md-3 sm-6">
                    <label>Ø§Ù„Ø³Ø¹Ø±\Ù„Ù„ÙˆØ­Ø¯Ø©</label>
                    <div class="control"><input name="equip_price_1" type="number" min="0" step="0.01"
                        placeholder="0.00"></div>
                  </div>

                  <div class="field md-3 sm-6">

                  </div>





                  <!-- Ø®Ø§Ù†ØªØ§Ù† ÙØ§Ø±ØºØªØ§Ù† Ù„Ù„Ø­ÙØ§Ø¸ Ø¹Ù„Ù‰ 3 Ø®Ø§Ù†Ø§Øª Ù„ÙƒÙ„ ØµÙ -->

                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†</label>
                    <div class="control"><input name="equip_supervisors_1" type="number" min="0"></div>
                  </div>

                  <div class="field md-3 sm-6">
                    <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙÙ†ÙŠÙŠÙ†</label>
                    <div class="control"><input name="equip_technicians_1" type="number" min="0"></div>
                  </div>
                  <!-- Ø¥ÙƒÙ…Ø§Ù„ Ø§Ù„ØµÙ Ø¨Ø«Ù„Ø§Ø« Ø®Ø§Ù†Ø§Øª -->
                  <div class="field md-3 sm-6"></div>
                  <div class="field md-3 sm-6"></div>
                </div>
              </div>
            </div>
          </div>

          <div style="margin: 15px 0; display: flex; gap: 10px;">
            <button type="button" class="primary" id="addEquipmentBtn"
              style="padding: 0.75rem 1.5rem; font-size: 0.95rem;">
              <i class="fas fa-plus-circle"></i> Ø¥Ø¶Ø§ÙØ© Ù…Ø²ÙŠØ¯ Ù…Ù† Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
            </button>
          </div>

          <hr class="hr" />
          <div class="section-title"><span class="chip">4</span> Ø¨ÙŠØ§Ù†Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</div>
          <br>

          <div class="form-grid">

            <div class="field md-3 sm-6" style="display: none;">
              <label>Ø¹Ø¯Ø¯ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠØ© <font color="red"> * Ù…Ù‡Ù… </font></label>
              <div class="control"><input type="number" id="daily_work_hours" name="daily_work_hours" min="0"
                  placeholder="Ù…Ø«Ø§Ù„: 8" value="20"></div>
            </div>
            <!-- Orgnization Break  -->



            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø£ÙˆÙ„ </label>
              <div class="control"><input type="text" name="first_party" id="first_party"
                  placeholder="Ø§Ø³Ù… Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø§ÙˆÙ„ ">
              </div>
            </div>



            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ </label>
              <div class="control"><input type="text" name="second_party" id="second_party"
                  placeholder="Ø§Ø³Ù… Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ ">
              </div>
            </div>

            <div class="field md-3 sm-6"> </div>

            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø£ÙˆÙ„</label>
              <div class="control"><input type="text" name="witness_one" id="witness_one"
                  placeholder="Ø§Ø³Ù… Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø£ÙˆÙ„">
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø«Ø§Ù†ÙŠ</label>
              <div class="control"><input type="text" name="witness_two" id="witness_two"
                  placeholder="Ø§Ø³Ù… Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø«Ø§Ù†ÙŠ">
              </div>
            </div>
          </div>


          <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: center;">
            <button type="reset"
              style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); color: white; border: none; padding: 0.75rem 2rem; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
              <i class="fas fa-eraser"></i> ØªÙØ±ÙŠØº Ø§Ù„Ø­Ù‚ÙˆÙ„
            </button>
            <button type="submit" class="primary" style="padding: 0.75rem 3rem;">
              <i class="fas fa-save"></i> Ø­ÙØ¸ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
            </button>
          </div>
        </div>
      </div>
    </form>
    <div class="card">
      <div class="card-header">
        <h5>
          <i class="fas fa-list-alt"></i> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ø¹Ù‚ÙˆØ¯
        </h5>
      </div>

      <!-- Ø£Ø²Ø±Ø§Ø± Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª -->
      <div class="card-body" style="padding: 1rem 2rem; border-bottom: 1px solid #e0e0e0;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
          <span style="font-weight: 700; color: #667eea; margin-left: 10px;">
            <i class="fas fa-filter"></i> Ø¹Ø±Ø¶ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª:
          </span>
          <button class="btn-group-toggle active" data-group="basic" title="Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©">
            <i class="fas fa-info-circle"></i> Ø£Ø³Ø§Ø³ÙŠØ©
          </button>
          <button class="btn-group-toggle active" data-group="dates" title="Ø§Ù„ØªÙˆØ§Ø±ÙŠØ® ÙˆØ§Ù„Ù…Ø¯Ø¯">
            <i class="far fa-calendar"></i> ØªÙˆØ§Ø±ÙŠØ®
          </button>
          <button class="btn-group-toggle active" data-group="hours" title="Ø§Ù„Ø³Ø§Ø¹Ø§Øª ÙˆØ§Ù„Ø£Ù‡Ø¯Ø§Ù">
            <i class="fas fa-clock"></i> Ø³Ø§Ø¹Ø§Øª
          </button>
          <button class="btn-group-toggle" data-group="parties" title="Ø£Ø·Ø±Ø§Ù Ø§Ù„Ø¹Ù‚Ø¯">
            <i class="fas fa-users"></i> Ø£Ø·Ø±Ø§Ù
          </button>
          <button class="btn-group-toggle" data-group="services" title="Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ù…Ù‚Ø¯Ù…Ø©">
            <i class="fas fa-hands-helping"></i> Ø®Ø¯Ù…Ø§Øª
          </button>
          <button class="btn-group-toggle" data-group="operations" title="Ø§Ù„ØªØ´ØºÙŠÙ„ Ø§Ù„ÙŠÙˆÙ…ÙŠ">
            <i class="fas fa-cogs"></i> ØªØ´ØºÙŠÙ„
          </button>
          <button class="btn-group-toggle active" data-group="status" title="Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª">
            <i class="fas fa-check-circle"></i> Ø­Ø§Ù„Ø©
          </button>
          <button class="btn-group-toggle-all" title="Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„">
            <i class="fas fa-eye"></i> Ø§Ù„ÙƒÙ„
          </button>
        </div>
      </div>

      <div class="card-body" style="padding: 2rem; overflow-x: auto;">
        <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
          <thead>
            <tr>
              <!-- Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© -->
              <th class="group-basic"><i class="fas fa-hashtag"></i> Ø±Ù‚Ù… Ø§Ù„Ø¹Ù‚Ø¯</th>

              <!-- Ø§Ù„ØªÙˆØ§Ø±ÙŠØ® ÙˆØ§Ù„Ù…Ø¯Ø¯ -->
              <th class="group-dates"><i class="far fa-calendar"></i> ØªØ§Ø±ÙŠØ® Ø§Ù„ØªÙˆÙ‚ÙŠØ¹</th>
              <th class="group-dates"><i class="fas fa-hourglass-half"></i> Ù…Ø¯Ø© Ø§Ù„Ø³Ù…Ø§Ø­ (Ø£ÙŠØ§Ù…)</th>
              <th class="group-dates"><i class="fas fa-calendar-days"></i> Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ (Ø£ÙŠØ§Ù…)</th>
              <th class="group-dates"><i class="fas fa-play-circle"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ°</th>
              <th class="group-dates"><i class="fas fa-stop-circle"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ°</th>

              <!-- Ø§Ù„Ø³Ø§Ø¹Ø§Øª ÙˆØ§Ù„Ø£Ù‡Ø¯Ø§Ù -->
              <th class="group-hours"><i class="far fa-clock"></i> Ù‡Ø¯Ù Ø³Ø§Ø¹Ø§Øª Ø´Ù‡Ø±ÙŠ</th>
              <th class="group-hours"><i class="fas fa-clock"></i> Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ù…ØªÙˆÙ‚Ø¹Ø©</th>

              <!-- Ø£Ø·Ø±Ø§Ù Ø§Ù„Ø¹Ù‚Ø¯ -->
              <th class="group-parties"><i class="fas fa-user-tie"></i> Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø£ÙˆÙ„</th>
              <th class="group-parties"><i class="fas fa-user-check"></i> Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ</th>
              <th class="group-parties"><i class="fas fa-eye"></i> Ø´Ø§Ù‡Ø¯ Ø£ÙˆÙ„</th>
              <th class="group-parties"><i class="fas fa-eye"></i> Ø´Ø§Ù‡Ø¯ Ø«Ø§Ù†ÙŠ</th>

              <!-- Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ù…Ù‚Ø¯Ù…Ø© -->
              <th class="group-services"><i class="fas fa-truck"></i> Ø§Ù„Ù†Ù‚Ù„</th>
              <th class="group-services"><i class="fas fa-bed"></i> Ø§Ù„Ø³ÙƒÙ†</th>
              <th class="group-services"><i class="fas fa-home"></i> Ù…ÙƒØ§Ù† Ø§Ù„Ù…Ø¹ÙŠØ´Ø©</th>
              <th class="group-services"><i class="fas fa-wrench"></i> Ø§Ù„ÙˆØ±Ø´Ø©</th>

              <!-- Ø§Ù„ØªØ´ØºÙŠÙ„ Ø§Ù„ÙŠÙˆÙ…ÙŠ -->
              <th class="group-operations"><i class="fas fa-business-time"></i> Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ÙŠÙˆÙ…ÙŠØ§Ù‹</th>
              <th class="group-operations"><i class="fas fa-users-cog"></i> Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† ÙŠÙˆÙ…ÙŠØ§Ù‹</th>

              <!-- Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© -->
              <th class="group-basic"><i class="fas fa-money-bill-wave"></i> Ø§Ù„Ø¹Ù…Ù„Ø©</th>
              <th class="group-basic"><i class="fas fa-dollar-sign"></i> Ø§Ù„Ù…Ø¨Ù„Øº Ø§Ù„Ù…Ø¯ÙÙˆØ¹</th>
              <th class="group-basic"><i class="fas fa-clock"></i> ÙˆÙ‚Øª Ø§Ù„Ø¯ÙØ¹</th>
              <th class="group-basic"><i class="fas fa-shield-alt"></i> Ø§Ù„Ø¶Ù…Ø§Ù†Ø§Øª</th>
              <th class="group-basic"><i class="fas fa-calendar-check"></i> ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¯ÙØ¹</th>

              <!-- Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª -->
              <th class="group-status"><i class="fas fa-info-circle"></i> Ø§Ù„Ø­Ø§Ù„Ø©</th>
              <th class="group-status"><i class="fas fa-cogs"></i> Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
            </tr>
          </thead>
          <tbody>
            <?php
            include 'contractequipments_handler.php';
            $mine_id = $_GET['id'];

            // Ø¥Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯ Ø¬Ø¯ÙŠØ¯ Ø¹Ù†Ø¯ Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙÙˆØ±Ù…
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mine_id'])) {

              $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
              $mine_id = intval($_POST['mine_id']);

              if (!$is_super_admin) {
                $mine_scope_query = "SELECT m.id
                  FROM mines m
                  INNER JOIN project p ON p.id = m.project_id
                  LEFT JOIN users su ON su.id = p.created_by
                  LEFT JOIN clients sc ON sc.id = p.company_client_id
                  LEFT JOIN users scu ON scu.id = sc.created_by
                  WHERE m.id = $mine_id
                    AND (su.company_id = $company_id OR scu.company_id = $company_id)
                  LIMIT 1";
                $mine_scope_result = mysqli_query($conn, $mine_scope_query);
                if (!$mine_scope_result || mysqli_num_rows($mine_scope_result) === 0) {
                  echo "<script>alert('âŒ Ø§Ù„Ù…Ù†Ø¬Ù… Ø§Ù„Ù…Ø­Ø¯Ø¯ Ù„Ø§ ÙŠØªØ¨Ø¹ Ù„Ø´Ø±ÙƒØªÙƒ'); window.location.href='contracts.php?id=$mine_id';</script>";
                  exit;
                }
              }

              if ($id > 0 && !$can_edit) {
                header("Location: contracts.php?id=$mine_id&msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+ØªØ¹Ø¯ÙŠÙ„+Ø§Ù„Ø¹Ù‚ÙˆØ¯+âŒ");
                exit;
              } elseif ($id <= 0 && !$can_add) {
                header("Location: contracts.php?id=$mine_id&msg=Ù„Ø§+ØªÙˆØ¬Ø¯+ØµÙ„Ø§Ø­ÙŠØ©+Ø¥Ø¶Ø§ÙØ©+Ø¹Ù‚ÙˆØ¯+Ø¬Ø¯ÙŠØ¯Ø©+âŒ");
                exit;
              }


              $contract_signing_date = $_POST['contract_signing_date'];
              $grace_period_days = $_POST['grace_period_days'];

              // Ø­Ø³Ø§Ø¨ Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ Ø¨Ø§Ù„Ø£ÙŠØ§Ù… Ù…Ù† ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© ÙˆØ§Ù„Ù†Ù‡Ø§ÙŠØ©
              $actual_start = $_POST['actual_start'];
              $actual_end = $_POST['actual_end'];

              // Ø­Ø³Ø§Ø¨ Ø¹Ø¯Ø¯ Ø§Ù„Ø£ÙŠØ§Ù… Ù…Ù† ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© Ø¥Ù„Ù‰ ØªØ§Ø±ÙŠØ® Ø§Ù„Ø§Ù†ØªÙ‡Ø§Ø¡ (Ø´Ø§Ù…Ù„ ÙŠÙˆÙ… Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© ÙˆÙŠÙˆÙ… Ø§Ù„Ù†Ù‡Ø§ÙŠØ©)
              if (!empty($actual_start) && !empty($actual_end)) {
                $start_date = new DateTime($actual_start);
                $end_date = new DateTime($actual_end);
                $interval = $start_date->diff($end_date);
                $contract_duration_days = $interval->days + 1; // +1 Ù„Ø­Ø³Ø§Ø¨ ÙŠÙˆÙ… Ø§Ù„Ø¨Ø¯Ø§ÙŠØ© ÙˆÙŠÙˆÙ… Ø§Ù„Ù†Ù‡Ø§ÙŠØ© Ù…Ø¹Ø§Ù‹
              } else {
                $contract_duration_days = 0;
              }

              $transportation = $_POST['transportation'];
              $accommodation = $_POST['accommodation'];
              $place_for_living = $_POST['place_for_living'];
              $workshop = $_POST['workshop'];

              $hours_monthly_target = $_POST['hours_monthly_target'];
              $forecasted_contracted_hours = $_POST['forecasted_contracted_hours'];

              $daily_work_hours = $_POST['daily_work_hours'];
              $daily_operators = $_POST['daily_operators'];
              $first_party = $_POST['first_party'];
              $second_party = $_POST['second_party'];
              $witness_one = $_POST['witness_one'];
              $witness_two = $_POST['witness_two'];

              // Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø§Ù„ÙŠØ© Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø©
              $price_currency_contract = isset($_POST['price_currency_contract']) ? mysqli_real_escape_string($conn, $_POST['price_currency_contract']) : '';
              $paid_contract = isset($_POST['paid_contract']) ? mysqli_real_escape_string($conn, $_POST['paid_contract']) : '';
              $payment_time = isset($_POST['payment_time']) ? mysqli_real_escape_string($conn, $_POST['payment_time']) : '';
              $guarantees = isset($_POST['guarantees']) ? mysqli_real_escape_string($conn, $_POST['guarantees']) : '';
              $payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : '';

              // Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ© Ù„Ù„Ø¹Ù‚Ø¯
              $equip_shifts_contract = isset($_POST['equip_shifts_contract']) ? intval($_POST['equip_shifts_contract']) : 0;
              $shift_contract = isset($_POST['shift_contract']) ? intval($_POST['shift_contract']) : 0;
              $equip_total_contract_daily = isset($_POST['equip_total_contract']) ? intval($_POST['equip_total_contract']) : 0;
              $total_contract_permonth = isset($_POST['total_contract_permonth']) ? intval($_POST['total_contract_permonth']) : 0;
              $total_contract_units = isset($_POST['total_contract']) ? intval($_POST['total_contract']) : 0;


              if ($id > 0) {
                // ØªØ¹Ø¯ÙŠÙ„
                $contract_update_scope = (!$is_super_admin && $contracts_has_company)
                  ? " AND company_id = $company_id"
                  : "";
                $sql = "UPDATE contracts SET 
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
          WHERE id=$id$contract_update_scope";
              } else {
                // Ø¥Ø¶Ø§ÙØ©
              $contract_insert_col = (!$is_super_admin && $contracts_has_company) ? ", company_id" : "";
              $contract_insert_val = (!$is_super_admin && $contracts_has_company) ? ", '$company_id'" : "";
                $sql = "INSERT INTO contracts (
            contract_signing_date, mine_id, grace_period_days, contract_duration_days,
            equip_shifts_contract, shift_contract, equip_total_contract_daily, total_contract_permonth, total_contract_units,
            actual_start, actual_end, transportation, accommodation, place_for_living, workshop,
            hours_monthly_target, forecasted_contracted_hours,
            daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two,
            price_currency_contract, paid_contract, payment_time, guarantees, payment_date$contract_insert_col
        ) VALUES (
            '$contract_signing_date', '$mine_id','$grace_period_days', '$contract_duration_days',
            '$equip_shifts_contract', '$shift_contract', '$equip_total_contract_daily', '$total_contract_permonth', '$total_contract_units',
            '$actual_start','$actual_end', '$transportation','$accommodation','$place_for_living','$workshop',
            '$hours_monthly_target','$forecasted_contracted_hours',
            '$daily_work_hours','$daily_operators','$first_party','$second_party','$witness_one','$witness_two',
            '$price_currency_contract','$paid_contract','$payment_time','$guarantees','$payment_date'$contract_insert_val
        )";
              }
              $result = mysqli_query($conn, $sql);

              if ($result) {
                // Ø§Ù„Ø­ØµÙˆÙ„ Ø¹Ù„Ù‰ Ù…Ø¹Ø±Ù Ø§Ù„Ø¹Ù‚Ø¯ Ø§Ù„Ù…ÙØ¶Ø§Ù Ø­Ø¯ÙŠØ«Ø§Ù‹ Ø£Ùˆ Ù…Ø¹Ø±Ù Ø§Ù„Ø¹Ù‚Ø¯ Ø§Ù„Ù…ÙØ­Ø¯Ù‘Ø«
                if ($id > 0) {
                  $contract_id = $id;
                } else {
                  $contract_id = mysqli_insert_id($conn);
                }

                // Ø¬Ù…Ø¹ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ù…Ù† Ø§Ù„ÙÙˆØ±Ù…
                $equipment_array = [];
                $i = 1;
                // Ø§Ù„Ø¨Ø­Ø« Ø¹Ù† Ø£ÙƒØ¨Ø± index Ù…ÙˆØ¬ÙˆØ¯
                $max_index = 0;
                foreach ($_POST as $key => $value) {
                  if (preg_match('/equip_type_(\d+)/', $key, $matches)) {
                    $max_index = max($max_index, (int) $matches[1]);
                  }
                }

                // Ø¬Ù…Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ù…Ù† Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø£Ù‚Ø³Ø§Ù…
                for ($i = 1; $i <= $max_index; $i++) {
                  if (isset($_POST["equip_type_$i"]) && !empty($_POST["equip_type_$i"])) {
                    $equipment_array[] = [
                      'equip_type' => intval($_POST["equip_type_$i"]),
                      'equip_size' => isset($_POST["equip_size_$i"]) ? $_POST["equip_size_$i"] : 0,
                      'equip_count' => isset($_POST["equip_count_$i"]) ? $_POST["equip_count_$i"] : 0,
                      'equip_count_basic' => isset($_POST["equip_count_basic_$i"]) ? intval($_POST["equip_count_basic_$i"]) : 0,
                      'equip_count_backup' => isset($_POST["equip_count_backup_$i"]) ? intval($_POST["equip_count_backup_$i"]) : 0,
                      'equip_shifts' => isset($_POST["equip_shifts_$i"]) ? $_POST["equip_shifts_$i"] : 0,
                      'equip_unit' => isset($_POST["equip_unit_$i"]) ? $_POST["equip_unit_$i"] : '',
                      'shift1_start' => isset($_POST["shift1_start_$i"]) ? $_POST["shift1_start_$i"] : '',
                      'shift1_end' => isset($_POST["shift1_end_$i"]) ? $_POST["shift1_end_$i"] : '',
                      'shift2_start' => isset($_POST["shift2_start_$i"]) ? $_POST["shift2_start_$i"] : '',
                      'shift2_end' => isset($_POST["shift2_end_$i"]) ? $_POST["shift2_end_$i"] : '',
                      'shift_hours' => isset($_POST["shift_hours_$i"]) ? $_POST["shift_hours_$i"] : 0,
                      'equip_total_month' => isset($_POST["equip_total_month_$i"]) ? $_POST["equip_total_month_$i"] : 0,
                      'equip_monthly_target' => isset($_POST["equip_target_per_month_$i"]) ? $_POST["equip_target_per_month_$i"] : 0,
                      'equip_total_contract' => isset($_POST["equip_total_contract_$i"]) ? $_POST["equip_total_contract_$i"] : 0,
                      'equip_price' => isset($_POST["equip_price_$i"]) ? $_POST["equip_price_$i"] : 0,
                      'equip_price_currency' => isset($_POST["equip_price_currency_$i"]) ? $_POST["equip_price_currency_$i"] : '',
                      'equip_operators' => isset($_POST["equip_operators_$i"]) ? $_POST["equip_operators_$i"] : 0,
                      'equip_supervisors' => isset($_POST["equip_supervisors_$i"]) ? $_POST["equip_supervisors_$i"] : 0,
                      'equip_technicians' => isset($_POST["equip_technicians_$i"]) ? $_POST["equip_technicians_$i"] : 0,
                      'equip_assistants' => isset($_POST["equip_assistants_$i"]) ? $_POST["equip_assistants_$i"] : 0
                    ];
                  }
                }

                // Ø¥Ø¶Ø§ÙØ© Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø©
                if (!empty($equipment_array)) {
                  include('contractequipments_handler.php');
                  saveContractEquipments($contract_id, $equipment_array, $conn);
                }
              }

              echo "<script>window.location.href='contracts.php?id=$mine_id';</script>";
              exit;
            }

            // Ø¬Ù„Ø¨ Ø§Ù„Ø¹Ù‚ÙˆØ¯ Ù…Ø¹ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ù†Ø¬Ù… ÙˆØ§Ù„Ù…Ø´Ø±ÙˆØ¹
            $query = "SELECT c.*, m.mine_name, m.mine_code, p.name AS project_name 
                      FROM `contracts` c
                      LEFT JOIN mines m ON c.mine_id = m.id
                      LEFT JOIN project p ON m.project_id = p.id
                      WHERE c.mine_id = '$mine_id' AND $contract_scope_sql
                      ORDER BY c.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;


            while ($row = mysqli_fetch_assoc($result)) {

              // Ø¹Ø±Ø¶ Ø­Ø§Ù„Ø© Ø§Ù„Ø¹Ù‚Ø¯ Ù…Ù† status
              $contractStatus = isset($row['status']) ? $row['status'] : 1;
              $statusColor = 'green';
              $statusText = 'Ø³Ø§Ø±ÙŠ';
              if ($contractStatus == 1) {
                $statusColor = 'green';
                $statusText = 'Ø³Ø§Ø±ÙŠ';
              } else {
                $statusColor = 'red';
                $statusText = 'ØºÙŠØ± Ø³Ø§Ø±ÙŠ';
              }
              $status = "<font color='" . $statusColor . "'>" . $statusText . "</font>";

              echo "<tr>";

              // Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©
              echo "<td class='group-basic'>" . $row['id'] . "</td>";

              // Ø§Ù„ØªÙˆØ§Ø±ÙŠØ® ÙˆØ§Ù„Ù…Ø¯Ø¯
              echo "<td class='group-dates'>" . $row['contract_signing_date'] . "</td>";
              echo "<td class='group-dates'>" . (isset($row['grace_period_days']) ? $row['grace_period_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . (isset($row['contract_duration_days']) ? $row['contract_duration_days'] : 0) . "</td>";
              echo "<td class='group-dates'>" . $row['actual_start'] . "</td>";
              echo "<td class='group-dates'>" . $row['actual_end'] . "</td>";

              // Ø§Ù„Ø³Ø§Ø¹Ø§Øª ÙˆØ§Ù„Ø£Ù‡Ø¯Ø§Ù
              echo "<td class='group-hours'>" . $row['hours_monthly_target'] . "</td>";
              echo "<td class='group-hours'>" . $row['forecasted_contracted_hours'] . "</td>";

              // Ø£Ø·Ø±Ø§Ù Ø§Ù„Ø¹Ù‚Ø¯
              echo "<td class='group-parties'>" . (isset($row['first_party']) ? $row['first_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['second_party']) ? $row['second_party'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_one']) ? $row['witness_one'] : '-') . "</td>";
              echo "<td class='group-parties'>" . (isset($row['witness_two']) ? $row['witness_two'] : '-') . "</td>";

              // Ø§Ù„Ø®Ø¯Ù…Ø§Øª Ø§Ù„Ù…Ù‚Ø¯Ù…Ø©
              $transportationText = isset($row['transportation']) && $row['transportation'] ? $row['transportation'] : '-';
              $accommodationText = isset($row['accommodation']) && $row['accommodation'] ? $row['accommodation'] : '-';
              $place_for_livingText = isset($row['place_for_living']) && $row['place_for_living'] ? $row['place_for_living'] : '-';
              $workshopText = isset($row['workshop']) && $row['workshop'] ? $row['workshop'] : '-';

              echo "<td class='group-services'>" . $transportationText . "</td>";
              echo "<td class='group-services'>" . $accommodationText . "</td>";
              echo "<td class='group-services'>" . $place_for_livingText . "</td>";
              echo "<td class='group-services'>" . $workshopText . "</td>";

              // Ø§Ù„ØªØ´ØºÙŠÙ„ Ø§Ù„ÙŠÙˆÙ…ÙŠ
              echo "<td class='group-operations'>" . (isset($row['daily_work_hours']) ? $row['daily_work_hours'] : '-') . "</td>";
              echo "<td class='group-operations'>" . (isset($row['daily_operators']) ? $row['daily_operators'] : '-') . "</td>";

              // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ©
              echo "<td class='group-basic'>" . (isset($row['price_currency_contract']) && $row['price_currency_contract'] ? $row['price_currency_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['paid_contract']) && $row['paid_contract'] ? $row['paid_contract'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_time']) && $row['payment_time'] ? $row['payment_time'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['guarantees']) && $row['guarantees'] ? $row['guarantees'] : '-') . "</td>";
              echo "<td class='group-basic'>" . (isset($row['payment_date']) && $row['payment_date'] ? $row['payment_date'] : '-') . "</td>";

              // Ø§Ù„Ø­Ø§Ù„Ø© ÙˆØ§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª
              echo "<td class='group-status'>" . $status . "</td>";

              echo "<td class='group-status'>";

              if ($can_edit) {
                echo "<a href='javascript:void(0)' class='editBtn'
             data-id='" . $row['id'] . "'
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
             class='btn btn-action btn-action-edit'><i class='fas fa-edit'></i></a>";
              }

              if ($can_delete) {
                echo "<a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ØŸ\")' class='btn btn-action btn-action-delete'><i class='fas fa-trash-alt'></i></a>";
              }

              echo "<a href='contracts_details.php?id=" . $row['id'] . "' class='btn btn-action btn-action-view'><i class='fas fa-eye'></i></a>
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
      // ØªØ´ØºÙŠÙ„ DataTable Ø¨Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©


      $(document).ready(function () {
        $('#projectsTable').DataTable({
          dom: 'Bfrtip', // Buttons + Search + Pagination
          buttons: [
            { extend: 'copy', text: 'Ù†Ø³Ø®' },
            { extend: 'excel', text: 'ØªØµØ¯ÙŠØ± Excel' },
            { extend: 'csv', text: 'ØªØµØ¯ÙŠØ± CSV' },
            { extend: 'pdf', text: 'ØªØµØ¯ÙŠØ± PDF' },
            { extend: 'print', text: 'Ø·Ø¨Ø§Ø¹Ø©' }
          ],
          "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
          }
        });
      });


      // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø± ÙˆØ¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
      const toggleContractFormBtn = document.getElementById('toggleForm');
      const contractForm = document.getElementById('projectForm');

      if (toggleContractFormBtn && contractForm) {
        toggleContractFormBtn.addEventListener('click', function () {
          contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
        });
      }
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

    function updateEquipmentTypeOptions() {
      const selectedValues = new Set();
      document.querySelectorAll('.equip-type').forEach(select => {
        if (select.value) {
          selectedValues.add(select.value);
        }
      });

      document.querySelectorAll('.equip-type').forEach(select => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => {
          if (!option.value) {
            option.hidden = false;
            option.disabled = false;
            return;
          }

          if (option.value === currentValue) {
            option.hidden = false;
            option.disabled = false;
            return;
          }

          if (selectedValues.has(option.value)) {
            option.hidden = true;
            option.disabled = true;
          } else {
            option.hidden = false;
            option.disabled = false;
          }
        });
      });
    }

    // Ø­Ø³Ø§Ø¨ Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ Ø¨Ø§Ù„Ø£ÙŠØ§Ù… Ù…Ù† Ø§Ù„ØªØ§Ø±ÙŠØ®ÙŠÙ†
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

    // ØªØ­Ø¯ÙŠØ« Ø­Ø³Ø§Ø¨ Ø§Ù„Ø£ÙŠØ§Ù… Ø¹Ù†Ø¯ ØªØºÙŠÙŠØ± Ø§Ù„ØªÙˆØ§Ø±ÙŠØ®
    fields.actualStart.addEventListener('change', calculateDaysFromDates);
    fields.actualEnd.addEventListener('change', calculateDaysFromDates);

    // Ø¥Ø¶Ø§ÙØ© Ù‚Ø³Ù… Ù…Ø¹Ø¯Ø§Øª Ø¬Ø¯ÙŠØ¯
    function addEquipmentSection() {
      equipmentIndex++;
      const newSection = document.createElement('div');
      newSection.className = 'equipment-section';
      newSection.setAttribute('data-index', equipmentIndex);
      newSection.innerHTML = `
        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h6 style="margin: 0;">Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø±Ù‚Ù… ${equipmentIndex}</h6>
            <button type="button" class="removeEquipmentBtn" data-index="${equipmentIndex}" 
              style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
              <i class="fa fa-trash"></i> Ø­Ø°Ù
            </button>
          </div>
          <div class="form-grid">

          
            <div class="field md-3 sm-6">
              <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©</label>
              <div class="control">
                <select name="equip_type_${equipmentIndex}" class="equip-type">
                  <?php echo $equipmentTypeOptionsHtml; ?>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø­Ø¬Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© (Size)</label>
              <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="Ù…Ø«Ø§Ù„: 340"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</label>
              <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span style="color: #007bff; font-weight: 600;">â– </span> Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©</label>
              <div class="control"><input name="equip_count_basic_${equipmentIndex}" type="number" min="0" style="background: #e3f2fd; border-right: 3px solid #007bff;"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><span style="color: #ffc107; font-weight: 600;">â– </span> Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø§Ø­ØªÙŠØ§Ø·ÙŠØ©</label>
              <div class="control"><input name="equip_count_backup_${equipmentIndex}" type="number" min="0" style="background: #fffde7; border-right: 3px solid #ffc107;"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†</label>
              <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø³Ø§Ø¹Ø¯ÙŠÙ†</label>
              <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª</label>
              <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="Ù…Ø«Ø§Ù„: 2"></div>
            </div>

            <!-- Ø£ÙˆÙ‚Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª -->
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
              <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" placeholder="Ù…Ø«Ø§Ù„: 08:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
              <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" placeholder="Ù…Ø«Ø§Ù„: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
              <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" placeholder="Ù…Ø«Ø§Ù„: 16:00"></div>
            </div>
            <div class="field md-3 sm-6">
              <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
              <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" placeholder="Ù…Ø«Ø§Ù„: 00:00"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>ÙˆØ­Ø¯Ø© Ø§Ù„Ù‚ÙŠØ§Ø³</label>
              <div class="control">
                <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ø³Ø§Ø¹Ø©">Ø³Ø§Ø¹Ø©</option>
                  <option value="Ø·Ù†">Ø·Ù†</option>
                  <option value="Ù…ØªØ± Ø·ÙˆÙ„ÙŠ">Ù…ØªØ± Ø·ÙˆÙ„ÙŠ</option>
                  <option value="Ù…ØªØ± Ù…ÙƒØ¹Ø¨">Ù…ØªØ± Ù…ÙƒØ¹Ø¨</option>
                </select>
              </div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</label>
              <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª ÙŠÙˆÙ…ÙŠØ§Ù‹</label>
              <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ÙÙŠ Ø§Ù„Ø´Ù‡Ø±</label>
              <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0"></div>
            </div>

            <div class="field md-3 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</label>
              <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø¹Ù…Ù„Ø©</label>
              <div class="control">
                <select name="equip_price_currency_${equipmentIndex}">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option value="Ø¯ÙˆÙ„Ø§Ø±">Ø¯ÙˆÙ„Ø§Ø±</option>
                  <option value="Ø¬Ù†ÙŠÙ‡">Ø¬Ù†ÙŠÙ‡</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø³Ø¹Ø±</label>
              <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00"></div>
            </div>
            <div class="field md-3 sm-6">
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†</label>
              <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙÙ†ÙŠÙŠÙ†</label>
              <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0"></div>
            </div>
          </div>
        </div>
      `;
      document.getElementById('equipmentSections').appendChild(newSection);

      // Ø¥Ø¶Ø§ÙØ© event listeners Ù„Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø©
      newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
      newSection.querySelectorAll('.equip-type').forEach(el => el.addEventListener('change', updateEquipmentTypeOptions));

      // Ø¥Ø¶Ø§ÙØ© event listener Ù„Ø²Ø± Ø§Ù„Ø­Ø°Ù
      newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
        newSection.remove();
        recalc();
        updateEquipmentTypeOptions();
      });

      updateEquipmentTypeOptions();
    }

    function recalc() {
      const days = num(fields.contractDays.value);

      // Ø­Ø³Ø§Ø¨ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
      let totalEquipMonth = 0;
      let totalEquipContract = 0;

      // Ø­Ø³Ø§Ø¨ ÙƒÙ„ Ù‚Ø³Ù… Ù…Ø¹Ø¯Ø§Øª
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
          // Ø­Ø³Ø§Ø¨ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø¹Ù„Ù‰ Ø£Ø³Ø§Ø³ Ø§Ù„Ø£ÙŠØ§Ù… Ø¨Ø¯Ù„Ø§Ù‹ Ù…Ù† Ø§Ù„Ø´Ù‡ÙˆØ±
          // Ù†ÙØªØ±Ø¶ Ø£Ù† Ø§Ù„Ù€ target Ù‡Ùˆ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…ÙŠØ© Ù„Ù„Ù…Ø¹Ø¯Ø©
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

    // ØªØ´ØºÙŠÙ„ Ø§Ù„Ø­Ø³Ø¨Ø© Ø¹Ù†Ø¯ ØªØºÙŠÙŠØ± Ø£ÙŠ Ù…Ø¯Ø®Ù„
    document.addEventListener('input', function (e) {
      if (e.target.closest('#projectForm')) {
        recalc();
      }
    });

    document.addEventListener('change', function (e) {
      if (e.target.classList && e.target.classList.contains('equip-type')) {
        updateEquipmentTypeOptions();
      }
    });

    // Ø²Ø± Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
    document.getElementById('addEquipmentBtn').addEventListener('click', function (e) {
      e.preventDefault();
      addEquipmentSection();
    });

    // Ø¬Ù„Ø¨ Ø§Ù„ÙÙˆØ±Ù…
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(() => {
        recalc();
        updateEquipmentTypeOptions();
      }, 0));
    }

    // Ø£ÙˆÙ„ ØªØ´ØºÙŠÙ„
    recalc();
    updateEquipmentTypeOptions();



    // ØªØ¹Ø¨Ø¦Ø© Ø§Ù„ÙÙˆØ±Ù… Ø¹Ù†Ø¯ Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
    $(document).on("click", ".editBtn", function () {
      $("#projectForm").show();
      $("#contract_id").val($(this).data("id"));
      $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
      $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
      $("#projectForm [name='contract_duration_days']").val($(this).data("contract_duration_days"));
      $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
      $("#projectForm [name='actual_end']").val($(this).data("actual_end"));


      $("#projectForm [name='hours_monthly_target']").val($(this).data("hours_monthly_target"));
      $("#projectForm [name='forecasted_contracted_hours']").val($(this).data("forecasted_contracted_hours"));

      $("#projectForm [name='daily_work_hours']").val($(this).attr("daily_work_hours"));

      $("#projectForm [name='daily_operators']").val($(this).attr("daily_operators"));

      // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø¥Ø¶Ø§ÙÙŠØ© Ù„Ù„Ø¹Ù‚Ø¯
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

      // Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø§Ù„ÙŠØ© Ø§Ù„Ø¬Ø¯ÙŠØ¯Ø©
      $("#projectForm [name='price_currency_contract']").val($(this).attr("price_currency_contract"));
      $("#projectForm [name='paid_contract']").val($(this).attr("paid_contract"));
      $("#projectForm [name='payment_time']").val($(this).attr("payment_time"));
      $("#projectForm [name='guarantees']").val($(this).attr("guarantees"));
      $("#projectForm [name='payment_date']").val($(this).attr("payment_date"));

      // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø®Ø§ØµØ© Ø¨Ø§Ù„Ø¹Ù‚Ø¯
      const contractId = $(this).data("id");
      $.ajax({
        url: 'get_equipments.php',
        type: 'POST',
        data: { contract_id: contractId },
        dataType: 'json',
        success: function (equipments) {
          // Ù…Ø³Ø­ Ø§Ù„Ø£Ù‚Ø³Ø§Ù… Ø§Ù„Ù‚Ø¯ÙŠÙ…Ø© Ù…Ø§ Ø¹Ø¯Ø§ Ø§Ù„Ø£ÙˆÙ„
          $('#equipmentSections .equipment-section').not(':first').remove();
          equipmentIndex = 1;

          // ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª
          if (equipments.length > 0) {
            equipments.forEach(function (equip, index) {
              const sectionIndex = index + 1;

              if (sectionIndex === 1) {
                // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù‚Ø³Ù… Ø§Ù„Ø£ÙˆÙ„
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
                updateEquipmentTypeOptions();
              } else {
                // Ø¥Ø¶Ø§ÙØ© Ø£Ù‚Ø³Ø§Ù… Ø¬Ø¯ÙŠØ¯Ø©
                equipmentIndex++;
                const newSection = document.createElement('div');
                newSection.className = 'equipment-section';
                newSection.setAttribute('data-index', equipmentIndex);
                newSection.innerHTML = `
                  <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                      <h6 style="margin: 0;">Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø±Ù‚Ù… ${equipmentIndex}</h6>
                      <button type="button" class="removeEquipmentBtn" data-index="${equipmentIndex}" 
                        style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-trash"></i> Ø­Ø°Ù
                      </button>
                    </div>
                    <div class="form-grid">

                    
                      <div class="field md-3 sm-6">
                        <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø©</label>
                        <div class="control">
                          <select name="equip_type_${equipmentIndex}" class="equip-type">
                            <?php echo $equipmentTypeOptionsHtml; ?>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø­Ø¬Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© (Size)</label>
                        <div class="control"><input name="equip_size_${equipmentIndex}" type="number" placeholder="Ù…Ø«Ø§Ù„: 340" value="${equip.equip_size}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª</label>
                        <div class="control"><input name="equip_count_${equipmentIndex}" type="number" min="0" value="${equip.equip_count}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ†</label>
                        <div class="control"><input name="equip_operators_${equipmentIndex}" type="number" min="0" value="${equip.equip_operators}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø³Ø§Ø¹Ø¯ÙŠÙ†</label>
                        <div class="control"><input name="equip_assistants_${equipmentIndex}" type="number" min="0" value="${equip.equip_assistants}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª</label>
                        <div class="control"><input name="equip_shifts_${equipmentIndex}" type="number" min="0" placeholder="Ù…Ø«Ø§Ù„: 2" value="${equip.equip_shifts}"></div>
                      </div>
                      
                      <!-- Ø£ÙˆÙ‚Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ§Øª -->
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
                        <div class="control"><input name="shift1_start_${equipmentIndex}" type="time" value="${equip.shift1_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø£ÙˆÙ„Ù‰</label>
                        <div class="control"><input name="shift1_end_${equipmentIndex}" type="time" value="${equip.shift1_end || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
                        <div class="control"><input name="shift2_start_${equipmentIndex}" type="time" value="${equip.shift2_start || ''}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label><i class="fas fa-clock"></i> Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ÙˆØ±Ø¯ÙŠØ© Ø§Ù„Ø«Ø§Ù†ÙŠØ©</label>
                        <div class="control"><input name="shift2_end_${equipmentIndex}" type="time" value="${equip.shift2_end || ''}"></div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>ÙˆØ­Ø¯Ø© Ø§Ù„Ù‚ÙŠØ§Ø³</label>
                        <div class="control">
                          <select name="equip_unit_${equipmentIndex}" class="equip-unit">
                            <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                            <option value="Ø³Ø§Ø¹Ø©" ${equip.equip_unit === 'Ø³Ø§Ø¹Ø©' ? 'selected' : ''}>Ø³Ø§Ø¹Ø©</option>
                            <option value="Ø·Ù†" ${equip.equip_unit === 'Ø·Ù†' ? 'selected' : ''}>Ø·Ù†</option>
                            <option value="Ù…ØªØ± Ø·ÙˆÙ„ÙŠ" ${equip.equip_unit === 'Ù…ØªØ± Ø·ÙˆÙ„ÙŠ' ? 'selected' : ''}>Ù…ØªØ± Ø·ÙˆÙ„ÙŠ</option>
                            <option value="Ù…ØªØ± Ù…ÙƒØ¹Ø¨" ${equip.equip_unit === 'Ù…ØªØ± Ù…ÙƒØ¹Ø¨' ? 'selected' : ''}>Ù…ØªØ± Ù…ÙƒØ¹Ø¨</option>
                          </select>
                        </div>
                      </div>

                      <div class="field md-3 sm-6">
                        <label>Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙˆØ±Ø¯ÙŠØ©</label>
                        <div class="control"><input name="shift_hours_${equipmentIndex}" type="number" min="0" value="${equip.shift_hours}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª ÙŠÙˆÙ…ÙŠØ§Ù‹</label>
                        <div class="control"><input name="equip_total_month_${equipmentIndex}" type="number" readonly placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹" value="${equip.equip_total_month}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>ÙˆØ­Ø¯Ø§Øª Ø§Ù„Ø¹Ù…Ù„ ÙÙŠ Ø§Ù„Ø´Ù‡Ø±</label>
                        <div class="control"><input name="equip_target_per_month_${equipmentIndex}" type="number" min="0" value="${equip.equip_monthly_target || 0}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</label>
                        <div class="control"><input name="equip_total_contract_${equipmentIndex}" type="number" readonly placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹" value="${equip.equip_total_contract}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø§Ù„Ø¹Ù…Ù„Ø©</label>
                        <div class="control">
                          <select name="equip_price_currency_${equipmentIndex}">
                            <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                            <option value="Ø¯ÙˆÙ„Ø§Ø±" ${equip.equip_price_currency === 'Ø¯ÙˆÙ„Ø§Ø±' ? 'selected' : ''}>Ø¯ÙˆÙ„Ø§Ø±</option>
                            <option value="Ø¬Ù†ÙŠÙ‡" ${equip.equip_price_currency === 'Ø¬Ù†ÙŠÙ‡' ? 'selected' : ''}>Ø¬Ù†ÙŠÙ‡</option>
                          </select>
                        </div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø§Ù„Ø³Ø¹Ø±</label>
                        <div class="control"><input name="equip_price_${equipmentIndex}" type="number" min="0" step="0.01" placeholder="0.00" value="${equip.equip_price}"></div>
                      </div>
                       <div class="field md-3 sm-6">
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´Ø±ÙÙŠÙ†</label>
                        <div class="control"><input name="equip_supervisors_${equipmentIndex}" type="number" min="0" value="${equip.equip_supervisors}"></div>
                      </div>
                      <div class="field md-3 sm-6">
                        <label>Ø¹Ø¯Ø¯ Ø§Ù„ÙÙ†ÙŠÙŠÙ†</label>
                        <div class="control"><input name="equip_technicians_${equipmentIndex}" type="number" min="0" value="${equip.equip_technicians}"></div>
                      </div>
                    </div>
                  </div>
                `;
                document.getElementById('equipmentSections').appendChild(newSection);

                const newSelect = newSection.querySelector(`select[name="equip_type_${equipmentIndex}"]`);
                if (newSelect) {
                  newSelect.value = equip.equip_type;
                }

                // Ø¥Ø¶Ø§ÙØ© event listeners
                newSection.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
                newSection.querySelectorAll('.equip-type').forEach(el => el.addEventListener('change', updateEquipmentTypeOptions));
                newSection.querySelector('.removeEquipmentBtn').addEventListener('click', function () {
                  newSection.remove();
                  recalc();
                  updateEquipmentTypeOptions();
                });
              }
            });
          }

          recalc();
          updateEquipmentTypeOptions();
        }
      });

      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });

    // ==================== Group Toggle Functionality ====================
    // Ø­ÙØ¸ Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª ÙÙŠ localStorage
    const groupStates = JSON.parse(localStorage.getItem('contractGroupStates')) || {
      basic: true,
      dates: true,
      hours: true,
      parties: false,
      services: false,
      operations: false,
      status: true
    };

    // ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù…Ø­ÙÙˆØ¸Ø© Ø¹Ù†Ø¯ ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø©
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

    // ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø­Ø§Ù„Ø© Ø¹Ù†Ø¯ ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø©
    applyGroupStates();

    // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª
    $('.btn-group-toggle').on('click', function () {
      const group = $(this).data('group');
      const isActive = $(this).hasClass('active');

      if (isActive) {
        // Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø©
        $(this).removeClass('active');
        $(`.group-${group}`).addClass('group-hidden');
        groupStates[group] = false;
      } else {
        // Ø¥Ø¸Ù‡Ø§Ø± Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø©
        $(this).addClass('active');
        $(`.group-${group}`).removeClass('group-hidden');
        groupStates[group] = true;
      }

      // Ø­ÙØ¸ Ø§Ù„Ø­Ø§Ù„Ø©
      localStorage.setItem('contractGroupStates', JSON.stringify(groupStates));
    });

    // Ø²Ø± Ø¥Ø¸Ù‡Ø§Ø±/Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„
    $('.btn-group-toggle-all').on('click', function () {
      const allActive = Object.values(groupStates).every(state => state);

      if (allActive) {
        // Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„
        $('.btn-group-toggle').removeClass('active');
        $('[class*="group-"]').addClass('group-hidden');
        Object.keys(groupStates).forEach(key => groupStates[key] = false);
        $(this).html('<i class="fas fa-eye-slash"></i> Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙ„');
      } else {
        // Ø¥Ø¸Ù‡Ø§Ø± Ø§Ù„ÙƒÙ„
        $('.btn-group-toggle').addClass('active');
        $('[class*="group-"]').removeClass('group-hidden');
        Object.keys(groupStates).forEach(key => groupStates[key] = true);
        $(this).html('<i class="fas fa-eye"></i> Ø§Ù„ÙƒÙ„');
      }

      // Ø­ÙØ¸ Ø§Ù„Ø­Ø§Ù„Ø©
      localStorage.setItem('contractGroupStates', JSON.stringify(groupStates));
    });

    // ØªØ­Ø¯ÙŠØ« Ù†Øµ Ø²Ø± "Ø§Ù„ÙƒÙ„" Ø¹Ù†Ø¯ Ø§Ù„ØªØ­Ù…ÙŠÙ„
    $(document).ready(function () {
      const allActive = Object.values(groupStates).every(state => state);
      if (allActive) {
        $('.btn-group-toggle-all').html('<i class="fas fa-eye"></i> Ø§Ù„ÙƒÙ„');
      } else {
        $('.btn-group-toggle-all').html('<i class="fas fa-eye-slash"></i> Ø¥Ø¸Ù‡Ø§Ø± Ø§Ù„ÙƒÙ„');
      }
    });
  </script>


</body>

</html>
