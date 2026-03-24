<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: ../login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | Ø¹Ù‚ÙˆØ¯ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†</title>
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
</head>
<style>
  .totals {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 10px;
  }

  .kpi {
    /* background: linear-gradient(180deg, #fff, #fffaf0); */
    border: 1px solid #ffcc00;
    border-radius: 14px;
    padding: 14px;
    text-align: center;
  }

  .kpi .v {
    font-weight: 900;
    font-size: clamp(18px, 3vw, 24px);
    color: #7a5a00;
  }

  .kpi .t {
    color: var(--muted);
    font-size: 12px;
  }

  .hr {
    height: 1px;
    /* background: linear-gradient(90deg, transparent, var(--yellow), transparent); */
    margin: 18px 0;
    border: none;
  }
</style>

<body>

  <?php include('../insidebar.php'); ?>

  <div class="main">

    <?php
    // Ø¹Ø±Ø¶ Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª Ø§Ù„Ù…ÙˆØ±Ø¯
    include '../config.php';
    $supplier_id = intval($_GET['id']);
    
    // Ø¬Ù„Ø¨ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ù…ÙˆØ±Ø¯
    $supplier_query = mysqli_query($conn, "SELECT name FROM suppliers WHERE id = $supplier_id");
    $supplier_data = mysqli_fetch_assoc($supplier_query);
    
    // Ø­Ø³Ø§Ø¨ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§
    $hours_query = mysqli_query($conn, "SELECT 
        COUNT(*) as total_contracts,
        COALESCE(SUM(forecasted_contracted_hours), 0) as total_hours,
        COALESCE(SUM(hours_monthly_target), 0) as monthly_hours
        FROM supplierscontracts 
        WHERE supplier_id = $supplier_id");
    $hours_data = mysqli_fetch_assoc($hours_query);
    ?>

    <!-- Ø¨Ø·Ø§Ù‚Ø© Ø¥Ø­ØµØ§Ø¦ÙŠØ§Øª Ø§Ù„Ù…ÙˆØ±Ø¯ -->
    <div class="card shadow-sm" style="margin-bottom: 20px; border-left: 5px solid #667eea;">
      <div class="card-body">
        <h4 style="color: #667eea; margin-bottom: 15px;">
          <i class="fa fa-user-tie"></i> <?php echo $supplier_data['name']; ?>
        </h4>
        <div class="totals" style="grid-template-columns: repeat(3, 1fr);">
          <div class="kpi">
            <div class="v"><?php echo $hours_data['total_contracts']; ?></div>
            <div class="t">Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯ Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠ</div>
          </div>
          <div class="kpi">
            <div class="v"><?php echo number_format($hours_data['monthly_hours']); ?></div>
            <div class="t">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø´Ù‡Ø±ÙŠØ© Ø§Ù„Ù…ØªÙˆØ³Ø·Ø©</div>
          </div>
          <div class="kpi" style="border-right-color: #28a745;">
            <div class="v" style="color: #28a745;"><?php echo number_format($hours_data['total_hours']); ?></div>
            <div class="t">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§</div>
          </div>
        </div>
      </div>
    </div>

    <!-- <h2>Ø§Ù„Ø¹Ù‚ÙˆØ¯</h2> -->
    <div class="aligin">
      <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> Ø§Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯
      </a>
    </div>

    <!-- ÙÙˆØ±Ù… Ø¥Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯ -->
    <form id="projectForm" action="" method="post" style="display:none;">

      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"> Ø§Ø¶Ø§ÙØ©/ ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¹Ù‚Ø¯ </h5>
        </div>
        <div class="card-body">

                  <input type="hidden" name="id" id="contract_id" value="">


          <input type="hidden" name="supplier_id" placeholder="Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯" value="<?php echo $_GET['id'] ?>" required />

          <div class="section-title"><span class="chip">1</span> Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© Ù„Ù„Ù…ÙˆØ±Ø¯ ÙˆØ§Ù„Ø¹Ù‚Ø¯</div>
          <br>
          <div class="form-grid">
              <div class="field md-3 sm-6">
            <label class="form-label">Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</label>
            <div class="control">
              <select name="project_id" id="project_id" required>

                <?php
                include '../config.php';

                $sql = "SELECT id, name FROM project ORDER BY name ASC";
                $result = mysqli_query($conn, $sql);
                ?>
                <option value="">-- Ø§Ø®ØªØ± Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ --</option>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                  <option value="<?php echo $row['id']; ?>">
                    <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
            <div class="field md-3 sm-6">
              <label>ØªØ§Ø±ÙŠØ® ØªÙˆÙ‚ÙŠØ¹ Ø§Ù„Ø¹Ù‚Ø¯ </label>
              <div class="control"><input name="contract_signing_date" id="contract_signing_date" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>ÙØªØ±Ø© Ø§Ù„Ø³Ù…Ø§Ø­ Ø¨ÙŠÙ† Ø§Ù„ØªÙˆÙ‚ÙŠØ¹ ÙˆØ§Ù„ØªÙ†ÙÙŠØ°</label>
              <div class="control"><input name="grace_period_days" id="grace_period_days" type="number" min="0" placeholder="Ø¹Ø¯Ø¯ Ø§Ù„Ø£ÙŠØ§Ù…"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ Ø¨Ø§Ù„Ø´Ù‡ÙˆØ± </label>
              <div class="control"><input name="contract_duration_months" id="contract_duration_months" type="number"
                  min="0" placeholder="Ø¨Ø§Ù„Ø´Ù‡ÙˆØ±"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ° Ø§Ù„ÙØ¹Ù„ÙŠ Ø§Ù„Ù…ØªÙÙ‚ Ø¹Ù„ÙŠÙ‡</label>
              <div class="control"><input name="actual_start" id="actual_start" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ° Ø§Ù„ÙØ¹Ù„ÙŠ Ø§Ù„Ù…ØªÙÙ‚ Ø¹Ù„ÙŠÙ‡</label>
              <div class="control"><input name="actual_end" id="actual_end" type="date"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„ØªØ±Ø­ÙŠÙ„ </label>
              <div class="control">
                <select name="transportation" id="transportation">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option>Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                  <option>ØºÙŠØ± Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø¥Ø¹Ø§Ø´Ø© </label>
              <div class="control">
                <select name="accommodation" id="accommodation">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option>Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                  <option>ØºÙŠØ± Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø³ÙƒÙ† </label>
              <div class="control">
                <select name="place_for_living" id="place_for_living">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option>Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                  <option>ØºÙŠØ± Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                </select>
              </div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„ÙˆØ±Ø´Ø© </label>
              <div class="control">
                <select name="workshop" id="workshop">
                  <option value="">â€” Ø§Ø®ØªØ± â€”</option>
                  <option>Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                  <option>ØºÙŠØ± Ù…Ø´Ù…ÙˆÙ„Ø©</option>
                </select>
              </div>
            </div>
          </div>

          <hr class="hr" />

          <!-- Ø§Ù„Ù‚Ø³Ù… 2: Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© Ù„Ù„Ù…Ø¹Ø¯Ø§Øª -->
          <div class="section-title"><span class="chip">2</span> Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© <strong>Ù„Ù„Ù…Ø¹Ø¯Ø§Øª</strong>
          </div>
                    <br>

          <div class="form-grid">
            <div class="field md-4 sm-6">
              <label>Ù†ÙˆØ¹ Ø§Ù„Ù…Ø¹Ø¯Ø© Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© </label>
              <div class="control"><input name="equip_type" id="equip_type" type="text" placeholder="Ù…Ø«Ø§Ù„: Ø­ÙØ§Ø±" value="Ø­ÙØ§Ø±" ></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø­Ø¬Ù… Ø§Ù„Ù…Ø¹Ø¯Ø© Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© </label>
              <div class="control"><input name="equip_size" id="equip_size" type="number" placeholder="Ù…Ø«Ø§Ù„: 340" value="340"></span>
              </div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©</label>
              <div class="control"><input name="equip_count" id="equip_count" type="number" min="0" value="2"></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-4 sm-6">
              <label>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„  Ù„Ù„Ù…Ø¹Ø¯Ø© Ø´Ù‡Ø±ÙŠØ§Ù‹</label>
              <div class="control"><input name="equip_target_per_month" id="equip_target_per_month" type="number"
                  min="0" value="600"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª  Ù„Ù„Ù…Ø¹Ø¯Ø§Øª Ø´Ù‡Ø±ÙŠØ§Ù‹</label>
              <div class="control"><input name="equip_total_month" id="equip_total_month" type="number" readonly
                  placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯  Ù„Ù„Ù…Ø¹Ø¯Ø§Øª</label>
              <div class="control"><input name="equip_total_contract" id="equip_total_contract" type="number" readonly
                  placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
          </div>

          <hr class="hr" />

          <!-- Ø§Ù„Ù‚Ø³Ù… 3: Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© Ù„Ù„Ø¢Ù„ÙŠØ§Øª -->
          <div class="section-title"><span class="chip">3</span> Ø¨ÙŠØ§Ù†Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© <strong>Ù„Ù„Ø¢Ù„ÙŠØ§Øª</strong>
          </div>
                    <br>

          <div class="form-grid">
            <div class="field md-4 sm-6">
              <label>Ù†ÙˆØ¹ Ø§Ù„Ø¢Ù„ÙŠØ© Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©</label>
              <div class="control"><input name="mach_type" id="mach_type" type="text" placeholder="Ù…Ø«Ø§Ù„: Ù‚Ù„Ø§Ø¨" value="Ù‚Ù„Ø§Ø¨"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø­Ø¬Ù… Ø­Ù…ÙˆÙ„Ø© Ø§Ù„Ø¢Ù„ÙŠØ©</label>
              <div class="control"><input name="mach_size" id="mach_size" type="number" placeholder="Ù…Ø«Ø§Ù„: 340" value="340"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ø¢Ù„ÙŠØ§Øª Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©</label>
              <div class="control"><input name="mach_count" id="mach_count" type="number" min="0" value="8"></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-4 sm-6">
              <label>Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„  Ù„Ù„Ø¢Ù„ÙŠØ© Ø´Ù‡Ø±ÙŠØ§Ù‹</label>
              <div class="control"><input name="mach_target_per_month" id="mach_target_per_month" type="number" min="0"
                  value="600"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª  Ù„Ù„Ø¢Ù„ÙŠØ§Øª Ø´Ù‡Ø±ÙŠØ§Ù‹</label>
              <div class="control"><input name="mach_total_month" id="mach_total_month" type="number" readonly
                  placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
            <div class="field md-4 sm-6">
              <label>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯  Ù„Ù„Ø¢Ù„ÙŠØ§Øª</label>
              <div class="control"><input name="mach_total_contract" id="mach_total_contract" type="number" readonly
                  placeholder="ÙŠÙØ­ØªØ³Ø¨ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹"></div>
            </div>
          </div>

          <hr class="hr" />
          <div class="section-title"><span class="chip">5</span> Ø¨ÙŠØ§Ù†Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©</div>
                    <br>

          <div class="form-grid">
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù…Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</label>
              <div class="control"><input type="number" name="daily_work_hours" id="daily_work_hours" min="0" placeholder="Ù…Ø«Ø§Ù„: 8"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ø´ØºÙ„ÙŠÙ† Ù„Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„ÙŠÙˆÙ…ÙŠØ©</label>
              <div class="control"><input type="number" name="daily_operators" id="daily_operators" min="0" placeholder="Ù…Ø«Ø§Ù„: 3"></div>
            </div>
            <div class="field md-3 sm-6">
              <label> Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø£ÙˆÙ„ </label>
              <div class="control"><input type="text" name="first_party" id="first_party" placeholder="Ø§Ø³Ù… Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø§ÙˆÙ„  "></div>
            </div>

            <!-- Orgnization Break  -->
            <div class="field md-3 sm-6"> </div>
            <div class="field md-3 sm-6"> </div>

            <div class="field md-3 sm-6">
              <label> Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ </label>
              <div class="control"><input type="text" name="second_party" id="second_party" placeholder="Ø§Ø³Ù… Ø§Ù„Ø·Ø±Ù Ø§Ù„Ø«Ø§Ù†ÙŠ"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø£ÙˆÙ„</label>
              <div class="control"><input type="text" name="witness_one" id="witness_one" placeholder="Ø§Ø³Ù… Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø£ÙˆÙ„"></div>
            </div>
            <div class="field md-3 sm-6">
              <label>Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø«Ø§Ù†ÙŠ</label>
              <div class="control"><input type="text" name="witness_two" id="witness_two" placeholder="Ø§Ø³Ù… Ø§Ù„Ø´Ø§Ù‡Ø¯ Ø§Ù„Ø«Ø§Ù†ÙŠ"></div>
            </div>
          </div>


          <hr class="hr" />

          <!-- Ø§Ù„Ù‚Ø³Ù… 4: Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª -->
          <div class="section-title"><span class="chip">4</span> Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª Ø§Ù„Ø³Ø§Ø¹Ø§Øª (Ø´Ù‡Ø±ÙŠØ§Ù‹ ÙˆÙ„Ù„Ø¹Ù‚Ø¯)</div>
          
          <!-- Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ Ø§Ù„Ù…Ø­Ø¯Ø¯ -->
          <div id="project_hours_info" style="display:none; margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 10px; border-right: 5px solid #2196f3;">
            <h5 style="color: #1976d2; margin-bottom: 10px;">
              <i class="fa fa-info-circle"></i> Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
            </h5>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
              <div>
                <strong style="color: #1976d2;">Ø§Ù„Ù…Ø´Ø±ÙˆØ¹:</strong> 
                <span id="selected_project_name" style="color: #424242;">-</span>
              </div>
              <div>
                <strong style="color: #1976d2;">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹:</strong> 
                <span id="project_total_hours" style="color: #424242; font-weight: 600;">0 Ø³Ø§Ø¹Ø©</span>
              </div>
              <div>
                <strong style="color: #1976d2;">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§ Ù…Ø¹ Ù…ÙˆØ±Ø¯ÙŠÙ† Ø¢Ø®Ø±ÙŠÙ†:</strong> 
                <span id="project_contracted_hours" style="color: #f57c00; font-weight: 600;">0 Ø³Ø§Ø¹Ø©</span>
              </div>
            </div>
          </div>

          <div class="totals">
            <div class="kpi">
              <div class="v" id="kpi_month_total">0</div>
              <div class="t">Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…Ø³ØªÙ‡Ø¯ÙØ© Ø´Ù‡Ø±ÙŠØ§Ù‹ - Ù…Ø¹Ø¯Ø§Øª ÙˆØ¢Ù„ÙŠØ§Øª</div>
              <input type="hidden" name="hours_monthly_target" id="hours_monthly_target" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_contract_total">0</div>
              <div class="t">Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯ Ø§Ù„Ù…Ø³ØªÙ‡Ø¯ÙØ© - Ù…Ø¹Ø¯Ø§Øª ÙˆØ¢Ù„ÙŠØ§Øª</div>
              <input type="hidden" name="forecasted_contracted_hours" id="forecasted_contracted_hours" value="0" />
            </div>
            <div class="kpi">
              <div class="v" id="kpi_equip_month">0</div>
              <div class="t">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ù…Ø¹Ø¯Ø§Øª (Ø´Ù‡Ø±ÙŠ)</div>
            </div>
            <div class="kpi">
              <div class="v" id="kpi_mach_month">0</div>
              <div class="t">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø¢Ù„ÙŠØ§Øª (Ø´Ù‡Ø±ÙŠ)</div>
            </div>
          </div>

          <div class="toolbar">
            <button type="reset" class="ghost">ØªÙØ±ÙŠØº Ø§Ù„Ø­Ù‚ÙˆÙ„</button>
                      <button type="submit" class="primary">Ø­ÙØ¸ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª</button>

          </div>

          <p class="muted" style="margin-top:8px">* ÙŠØªÙ… Ø§Ø­ØªØ³Ø§Ø¨ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø¥Ø¬Ù…Ø§Ù„ÙŠØ© ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ù†Ø§Ø¡Ù‹ Ø¹Ù„Ù‰ Ø§Ù„Ù…Ø¯Ø®Ù„Ø§Øª.</p>
        </div>
      </div>
    </form>
    <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ø¹Ù‚ÙˆØ¯ -->
    <div class="card shadow-sm">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"> Ù‚Ø§Ø¦Ù…Ø© Ø§Ù„Ø¹Ù‚ÙˆØ¯Ø§Øª </h5>
      </div>
      <div class="card-body">
        <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
          <thead>
            <tr>
              <th>Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
              <th>ØªØ§Ø±ÙŠØ® Ø§Ù„ØªÙˆÙ‚ÙŠØ¹</th>
              <th>Ù…Ø¯Ø© Ø§Ù„Ø¹Ù‚Ø¯ (Ø´Ù‡ÙˆØ±)</th>
              <th>Ø¨Ø¯Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ°</th>
              <th>Ù†Ù‡Ø§ÙŠØ© Ø§Ù„ØªÙ†ÙÙŠØ°</th>
              <th>Ø³Ø§Ø¹Ø§Øª Ø´Ù‡Ø±ÙŠØ§Ù‹</th>
              <th>Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</th>
              <th>Ø§Ù„Ø­Ø§Ù„Ø©</th>
              <th>Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
            </tr>
          </thead>
          <tbody>
            <?php
            include '../config.php';

            // Ø¥Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯ Ø¬Ø¯ÙŠØ¯ Ø¹Ù†Ø¯ Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙÙˆØ±Ù…
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['supplier_id'])) {

                       $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

              // $project = mysqli_real_escape_string($conn, $_POST['project']);
              $supplier_id = $_GET['id'];
              $project_id = $_POST['project_id'];




              $contract_signing_date = $_POST['contract_signing_date'];
              $grace_period_days = $_POST['grace_period_days'];
              $contract_duration_months = $_POST['contract_duration_months'];
              $actual_start = $_POST['actual_start'];
              $actual_end = $_POST['actual_end'];
              $transportation = $_POST['transportation'];
              $accommodation = $_POST['accommodation'];
              $place_for_living = $_POST['place_for_living'];
              $workshop = $_POST['workshop'];

              $equip_type = $_POST['equip_type'];
              $equip_size = $_POST['equip_size'];
              $equip_count = $_POST['equip_count'];
              $equip_target_per_month = $_POST['equip_target_per_month'];
              $equip_total_month = $_POST['equip_total_month'];
              $equip_total_contract = $_POST['equip_total_contract'];

              $mach_type = $_POST['mach_type'];
              $mach_size = $_POST['mach_size'];
              $mach_count = $_POST['mach_count'];
              $mach_target_per_month = $_POST['mach_target_per_month'];
              $mach_total_month = $_POST['mach_total_month'];
              $mach_total_contract = $_POST['mach_total_contract'];

              $hours_monthly_target = $_POST['hours_monthly_target'];
              $forecasted_contracted_hours = $_POST['forecasted_contracted_hours'];

              $daily_work_hours = $_POST['daily_work_hours'];
              $daily_operators = $_POST['daily_operators'];
              $first_party = $_POST['first_party'];
              $second_party = $_POST['second_party'];
              $witness_one = $_POST['witness_one'];
              $witness_two = $_POST['witness_two'];

                if ($id > 0) {
                  // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¹Ù‚Ø¯
                  mysqli_query($conn, "UPDATE supplierscontracts SET 
                    contract_signing_date='$contract_signing_date',
                    grace_period_days='$grace_period_days',
                    contract_duration_months='$contract_duration_months',
                    actual_start='$actual_start',
                    actual_end='$actual_end',
                    transportation='$transportation',
                    accommodation='$accommodation',
                    place_for_living='$place_for_living',
                    workshop='$workshop',
                    equip_type='$equip_type',
                    equip_size='$equip_size',
                    equip_count='$equip_count',
                    equip_target_per_month='$equip_target_per_month',
                    equip_total_month='$equip_total_month',
                    equip_total_contract='$equip_total_contract',
                    mach_type='$mach_type',
                    mach_size='$mach_size',
                    mach_count='$mach_count',
                    mach_target_per_month='$mach_target_per_month',
                    mach_total_month='$mach_total_month',
                    mach_total_contract='$mach_total_contract',
                    hours_monthly_target='$hours_monthly_target',
                    forecasted_contracted_hours='$forecasted_contracted_hours',
                    daily_work_hours='$daily_work_hours',
                    daily_operators='$daily_operators',
                    first_party='$first_party',
                    second_party='$second_party',
                    witness_one='$witness_one',
                    witness_two='$witness_two',
                    project_id='$project_id'
                  WHERE id=$id");
                } else {
                  // Ø¥Ø¶Ø§ÙØ© Ø¹Ù‚Ø¯ Ø¬Ø¯ÙŠØ¯


              mysqli_query($conn, "INSERT INTO supplierscontracts (
    contract_signing_date, supplier_id, grace_period_days, contract_duration_months,
    actual_start, actual_end, transportation, accommodation, place_for_living, workshop,
    equip_type, equip_size, equip_count, equip_target_per_month, equip_total_month, equip_total_contract,
    mach_type, mach_size, mach_count, mach_target_per_month, mach_total_month, mach_total_contract,
    hours_monthly_target, forecasted_contracted_hours,
    daily_work_hours, daily_operators, first_party, second_party, witness_one, witness_two,project_id
) VALUES (
    '$contract_signing_date', '$supplier_id','$grace_period_days', '$contract_duration_months',
    '$actual_start','$actual_end', '$transportation','$accommodation','$place_for_living','$workshop',
    '$equip_type','$equip_size','$equip_count','$equip_target_per_month', '$equip_total_month', '$equip_total_contract',
    '$mach_type', '$mach_size','$mach_count','$mach_target_per_month','$mach_total_month','$mach_total_contract',
    '$hours_monthly_target','$forecasted_contracted_hours',
    '$daily_work_hours','$daily_operators','$first_party','$second_party','$witness_one','$witness_two','$project_id'
)");


                }
              // Ø¥Ø¹Ø§Ø¯Ø© ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø© Ù„ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù‚Ø§Ø¦Ù…Ø©
               mysqli_query($conn, $sql);
              echo "<script>window.location.href='supplierscontracts.php?id=$supplier_id';</script>";
              exit();

            }
            $supplier_id = $_GET['id'];
            // Ø¬Ù„Ø¨ Ø§Ù„Ø¹Ù‚ÙˆØ¯ Ù…Ø¹ Ø£Ø³Ù…Ø§Ø¡ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹
            $query = "SELECT sc.*, op.name as project_name 
                      FROM `supplierscontracts` sc
                      LEFT JOIN project op ON sc.project_id = op.id
                      WHERE sc.supplier_id = $supplier_id  
                      ORDER BY sc.id DESC";
            $result = mysqli_query($conn, $query);
            $i = 1;


            while ($row = mysqli_fetch_assoc($result)) {

               $status = $row['status']=="1" ? "<font color='green'>Ø³Ø§Ø±ÙŠ</font>" : "
                    <font color='red'>Ù…Ù†ØªÙ‡ÙŠ</font>";

              echo "<tr>";
              echo "<td><strong>" . ($row['project_name'] ?? 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯') . "</strong></td>";
              echo "<td>" . $row['contract_signing_date'] . "</td>";
              echo "<td>" . $row['contract_duration_months'] . "</td>";
              echo "<td>" . $row['actual_start'] . "</td>";
              echo "<td>" . $row['actual_end'] . "</td>";
              echo "<td style='font-weight: 600; color: #28a745;'>" . number_format($row['hours_monthly_target']) . " Ø³Ø§Ø¹Ø©</td>";
              echo "<td style='font-weight: 700; color: #667eea;'>" . number_format($row['forecasted_contracted_hours']) . " Ø³Ø§Ø¹Ø©</td>";
              echo "<td>" . $status . "</td>";

              echo "<td>
                     <a href='javascript:void(0)' class='editBtn'
             data-id='" . $row['id'] . "'
             data-contract_signing_date='" . $row['contract_signing_date'] . "'
             data-grace_period_days='" . $row['grace_period_days'] . "'
             data-contract_duration_months='" . $row['contract_duration_months'] . "'
             data-actual_start='" . $row['actual_start'] . "'
             data-actual_end='" . $row['actual_end'] . "'
             data-equip_type='" . $row['equip_type'] . "'
             data-equip_count='" . $row['equip_count'] . "'
             data-equip_target_per_month='" . $row['equip_target_per_month'] . "'
             data-equip_total_month='" . $row['equip_total_month'] . "'
             data-equip_total_contract='" . $row['equip_total_contract'] . "'
             data-mach_type='" . $row['mach_type'] . "'
             data-mach_count='" . $row['mach_count'] . "'
             data-mach_target_per_month='" . $row['mach_target_per_month'] . "'
             data-mach_total_month='" . $row['mach_total_month'] . "'
             data-mach_total_contract='" . $row['mach_total_contract'] . "'
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
                  project_id ='" . $row['project_id'] . "'
                  
             data-forecasted_contracted_hours='" . $row['forecasted_contracted_hours'] . "'
             style='color:#007bff'><i class='fa fa-edit'></i></a> | 
                        <a href='delete.php?id=" . $row['id'] . "' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ØŸ\")' style='color: #dc3545'><i class='fa fa-trash'></i></a> | 
                        <a href='showcontractsuppliers.php?id=" . $row['id'] . "' style='color: #28a745'><i class='fa fa-eye'></i></a>
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
          responsive: true,
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

      // Ø¬Ù„Ø¨ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ Ø¹Ù†Ø¯ Ø§Ø®ØªÙŠØ§Ø±Ù‡
      $('#project_id').on('change', function() {
        const projectId = $(this).val();
        const projectName = $(this).find('option:selected').text();
        
        if (projectId) {
          $.ajax({
            url: 'get_project_hours.php',
            method: 'POST',
            data: { project_id: projectId },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                $('#project_hours_info').show();
                $('#selected_project_name').text(projectName);
                $('#project_total_hours').text(response.project_total_hours + ' Ø³Ø§Ø¹Ø©');
                $('#project_contracted_hours').text(response.suppliers_contracted_hours + ' Ø³Ø§Ø¹Ø©');
              }
            },
            error: function() {
              $('#project_hours_info').hide();
            }
          });
        } else {
          $('#project_hours_info').hide();
        }
      });

      toggleContractFormBtn.addEventListener('click', function () {
        contractForm.style.display = contractForm.style.display === "none" ? "block" : "none";
      });
    })();

  </script>

  <script>
    const $el = (sel) => document.querySelector(sel);

    const fields = {
      contractMonths: $el('#contract_duration_months'),
      equipCount: $el('#equip_count'),
      equipTarget: $el('#equip_target_per_month'),
      equipTotalMonth: $el('#equip_total_month'),
      equipTotalContract: $el('#equip_total_contract'),
      machCount: $el('#mach_count'),
      machTarget: $el('#mach_target_per_month'),
      machTotalMonth: $el('#mach_total_month'),
      machTotalContract: $el('#mach_total_contract'),
      kpiMonthTotal: $el('#kpi_month_total'),
      kpiContractTotal: $el('#kpi_contract_total'),
      kpiEquipMonth: $el('#kpi_equip_month'),
      kpiMachMonth: $el('#kpi_mach_month'),
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

    function recalc() {
      const months = num(fields.contractMonths.value);

      // Ù…Ø¹Ø¯Ø§Øª
      const equipCount = num(fields.equipCount.value);
      const equipTarget = num(fields.equipTarget.value);
      const equipMonth = equipCount * equipTarget;
      const equipContract = equipMonth * months;

      // Ø¢Ù„ÙŠØ§Øª
      const machCount = num(fields.machCount.value);
      const machTarget = num(fields.machTarget.value);
      const machMonth = machCount * machTarget;
      const machContract = machMonth * months;

      // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø­Ù‚ÙˆÙ„
      fields.equipTotalMonth.value = equipMonth;
      fields.equipTotalContract.value = equipContract;
      fields.machTotalMonth.value = machMonth;
      fields.machTotalContract.value = machContract;

      const monthTotal = equipMonth + machMonth;
      const contractTotal = equipContract + machContract;

      fields.kpiEquipMonth.textContent = fmt(equipMonth);
      fields.kpiMachMonth.textContent = fmt(machMonth);
      fields.kpiMonthTotal.textContent = fmt(monthTotal);
      fields.kpiContractTotal.textContent = fmt(contractTotal);

      fields.hoursMonthlyTarget.value = monthTotal;
      fields.forecastedContractedHours.value = contractTotal;
    }

    // ØªØ´ØºÙŠÙ„ Ø§Ù„Ø­Ø³Ø¨Ø© Ø¹Ù†Ø¯ ØªØºÙŠÙŠØ± Ø£ÙŠ Ù…Ø¯Ø®Ù„
    const inputs = document.querySelectorAll('#projectForm input, #projectForm select');
    inputs.forEach(el => el.addEventListener('input', recalc));

    // Ø¬Ù„Ø¨ Ø§Ù„ÙÙˆØ±Ù…
    const contractForm = document.getElementById('projectForm');
    if (contractForm) {
      contractForm.addEventListener('reset', () => setTimeout(recalc, 0));
    }

    // Ø£ÙˆÙ„ ØªØ´ØºÙŠÙ„
    recalc();


    
    // ØªØ¹Ø¨Ø¦Ø© Ø§Ù„ÙÙˆØ±Ù… Ø¹Ù†Ø¯ Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
    $(document).on("click", ".editBtn", function () {
      $("#projectForm").show();
      $("#contract_id").val($(this).data("id"));
      $("#projectForm [name='contract_signing_date']").val($(this).data("contract_signing_date"));
      $("#projectForm [name='grace_period_days']").val($(this).data("grace_period_days"));
      $("#projectForm [name='contract_duration_months']").val($(this).data("contract_duration_months"));
      $("#projectForm [name='actual_start']").val($(this).data("actual_start"));
      $("#projectForm [name='actual_end']").val($(this).data("actual_end"));
      $("#projectForm [name='equip_type']").val($(this).data("equip_type"));
      $("#projectForm [name='equip_count']").val($(this).data("equip_count"));
      $("#projectForm [name='equip_target_per_month']").val($(this).data("equip_target_per_month"));
      $("#projectForm [name='equip_total_month']").val($(this).data("equip_total_month"));
      $("#projectForm [name='equip_total_contract']").val($(this).data("equip_total_contract"));
      $("#projectForm [name='mach_type']").val($(this).data("mach_type"));
      $("#projectForm [name='mach_count']").val($(this).data("mach_count"));
      $("#projectForm [name='mach_target_per_month']").val($(this).data("mach_target_per_month"));
      $("#projectForm [name='mach_total_month']").val($(this).data("mach_total_month"));
      $("#projectForm [name='mach_total_contract']").val($(this).data("mach_total_contract"));
      $("#projectForm [name='hours_monthly_target']").val($(this).data("hours_monthly_target"));
      $("#projectForm [name='forecasted_contracted_hours']").val($(this).data("forecasted_contracted_hours"));
      $("#projectForm [name='daily_work_hours']").val($(this).attr("daily_work_hours"));
      $("#projectForm [name='daily_operators']").val($(this).attr("daily_operators"));
      $("#projectForm [name='first_party']").val($(this).attr("first_party"));
      $("#projectForm [name='second_party']").val($(this).attr("second_party"));
      $("#projectForm [name='witness_one']").val($(this).attr("witness_one"));
      $("#projectForm [name='witness_two']").val($(this).attr("witness_two"));
      $("#projectForm [name='transportation']").val($(this).attr("transportation"));
      $("#projectForm [name='accommodation']").val($(this).attr("accommodation"));
      $("#projectForm [name='place_for_living']").val($(this).attr("place_for_living"));
      $("#projectForm [name='workshop']").val($(this).attr("workshop"));
      $("#projectForm [name='project_id']").val($(this).attr("project_id"));

      $("html, body").animate({ scrollTop: $("#projectForm").offset().top }, 500);
    });
  </script>


</body>

</html>
