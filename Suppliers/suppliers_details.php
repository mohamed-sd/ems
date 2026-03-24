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
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- Bootstrab 5 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Ø§Ù„Ù…ÙˆÙ‚Ø¹ -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>

<body>

    <?php include('../insidebar.php'); ?>

    <div class="main">

        <!-- <h2>ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</h2> -->
        <div class="aligin">
            <a href="supplierscontracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
                <i class="fa fa-plus"></i> Ø¹Ù‚ÙˆØ¯Ø§Øª Ø§Ù„Ù…ÙˆØ±Ø¯
            </a>
        </div>
        <!-- <a href="../Equipments/equipments.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> Ø§Ø¶Ø§ÙØ© Ø¢Ù„ÙŠØ©
    </a> -->
        <!--  <a href="../Contracts/contracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> Ø§Ù„Ø¹Ù‚ÙˆØ¯Ø§Øª
    </a> -->

        <h3> ØªÙØ§ØµÙŠØ± Ø§Ù„Ù…ÙˆØ±Ø¯ : </h3>
        <br />

        <?php
        include '../config.php';

        $project = $_GET['id'];

        $select = mysqli_query($conn, "SELECT * , 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments',
                      (SELECT COUNT(*) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'num_contracts',
                      (SELECT COALESCE(SUM(forecasted_contracted_hours), 0) FROM supplierscontracts WHERE supplierscontracts.supplier_id = suppliers.id ) as 'total_hours'
                      FROM `suppliers` WHERE `id` = $project ORDER BY id DESC");
        while ($row = mysqli_fetch_array($select)) {
            ?>
            <div class="report">
                <div class="row">
                    <div class="col-lg-2 col-5">Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯ </div>
                    <div class="col-lg-4 col-7"><?php echo $row['name']; ?></div>
                    <div class="col-lg-2 col-5"> Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ </div>
                    <div class="col-lg-4 col-7"><?php echo $row['phone']; ?></div>
                    <div class="col-lg-2 col-5"> Ø¹Ø¯Ø¯ Ø§Ù„Ø¢Ù„ÙŠØ§Øª </div>
                    <div class="col-lg-4 col-7"> <?php echo $row['equipments']; ?> </div>
                    <div class="col-lg-2 col-5"> Ø¹Ø¯Ø¯ Ø§Ù„Ø¹Ù‚ÙˆØ¯ </div>
                    <div class="col-lg-4 col-7" style="font-weight: 600;"> <?php echo $row['num_contracts']; ?> </div>
                    <div class="col-lg-2 col-5"> Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ù…ØªØ¹Ø§Ù‚Ø¯ Ø¹Ù„ÙŠÙ‡Ø§ </div>
                    <div class="col-lg-4 col-7" style="font-weight: 700; color: #667eea; font-size: 1.1rem;"> <?php echo number_format($row['total_hours']); ?> Ø³Ø§Ø¹Ø© </div>
                    <div class="col-lg-2 col-5"> Ø§Ù„Ø­Ø§Ù„Ø© </div>
                    <div class="col-lg-4 col-7"><?php echo $row['status'] == "1" ? "Ù†Ø´Ø·" : "Ù…Ø¹Ù„Ù‚"; ?></div>
                </div>
            </div>
            <?php
        } // end while loop
        ?>


        <br /> <br /> <br />

        <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ -->
        <h3> Ø§Ù„Ø¢Ù„ÙŠØ§Øª </h3>
        <br />
        <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th style="text-align: right;">ÙƒÙˆØ¯ Ø§Ù„Ù…Ø¹Ø¯Ø©</th>
                    <th style="text-align: right;"> Ø§Ù„Ø§Ø³Ù… </th>
                    <th style="text-align: right;">Ù†ÙˆØ¹ Ø§Ù„Ø¢Ù„ÙŠÙ‡</th>
                    <!-- <th style="text-align: right;"> Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ </th> -->
                    <!-- <th style="text-align: right;">Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th> -->
                </tr>
            </thead>
            <tbody>
                <?php

                // Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹
                $query = "SELECT `id`, `code`, `type`, `name`, `status` FROM `equipments` where suppliers = $project ORDER BY id DESC";
                $result = mysqli_query($conn, $query);
                $i = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . $i++ . "</td>";
                    echo "<td>" . $row['code'] . "</td>";
                    echo "<td>" . $row['name'] . "</td>";
                    echo $row['type'] == "1" ? "<td style='color:green;'> Ø­ÙØ§Ø± </td>" : "<td style='color:red;'> Ù‚Ù„Ø§Ø¨ </td>";

                    // echo "<td>".$row['status']."</td>";
                    // echo "<td>
                    //         <a href='edit.php?id=".$row['id']."'>ØªØ¹Ø¯ÙŠÙ„</a> | 
                    //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ØŸ\")'>Ø­Ø°Ù</a> | <a href=''> Ø¹Ø±Ø¶ </a>
                    //       </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <br />

         <br />
        <h3> Ø§Ù„Ø¹Ù‚ÙˆØ¯ </h3>
        <br />
        <table id="projectsTable1" class="projectsTable" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</th>
                    <th style="text-align: center;">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©</th>
                    <th style="text-align: center;">Ø§Ù„Ù…Ø³ØªÙ‡Ø¯Ù Ø´Ù‡Ø±ÙŠØ§Ù‹</th>
                    <th style="text-align: center;">Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø³Ø§Ø¹Ø§Øª Ø§Ù„Ø¹Ù‚Ø¯</th>
                    <th style="text-align: center;">Ø§Ù„Ø­Ø§Ù„Ø©</th>
                    <th style="text-align: center;">Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include '../config.php';

                $query = "SELECT sc.*, op.name as project_name 
                          FROM `supplierscontracts` sc
                          LEFT JOIN project op ON sc.project_id = op.id
                          WHERE sc.supplier_id = '$project' 
                          ORDER BY sc.id DESC";
                $result = mysqli_query($conn, $query);
                $i = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                     $status = $row['status']=="1" ? "<font color='green'>Ø³Ø§Ø±ÙŠ</font>" : "
                    <font color='red'>Ù…Ù†ØªÙ‡ÙŠ</font>";

                    echo "<tr>";
                    echo "<td>" . $i++ . "</td>";
                    echo "<td><strong>" . ($row['project_name'] ?? 'ØºÙŠØ± Ù…Ø­Ø¯Ø¯') . "</strong></td>";
                    echo "<td>" . $row['contract_signing_date'] . "</td>";
                    echo "<td style='font-weight: 600; color: #28a745;'>" . number_format($row['hours_monthly_target']) . " Ø³Ø§Ø¹Ø©</td>";
                    echo "<td style='font-weight: 700; color: #667eea; font-size: 1.05rem;'>" . number_format($row['forecasted_contracted_hours']) . " Ø³Ø§Ø¹Ø©</td>";
                    echo "<td>" . $status . "</td>";
                    // echo "<td>
                    //         <a href='edit.php?id=".$row['id']."'>ØªØ¹Ø¯ÙŠÙ„</a> | 
                    //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ØŸ\")'>Ø­Ø°Ù</a> | <a href=''> Ø¹Ø±Ø¶ </a>
                    //       </td>";
                    echo "<td><a href='../Contracts/contracts_details.php?id=" . $row['id'] . "' style='color: #28a745'><i class='fa fa-eye'></i></a></td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>




    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script>
        (function () {
            // ØªØ´ØºÙŠÙ„ DataTable Ø¨Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©
            $(document).ready(function () {
                $('#projectsTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                    }
                });
            });
            $(document).ready(function () {
                $('#projectsTable1').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
                    }
                });
            });

            // Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø¥Ø¸Ù‡Ø§Ø± ÙˆØ¥Ø®ÙØ§Ø¡ Ø§Ù„ÙÙˆØ±Ù…
            const toggleProjectFormBtn = document.getElementById('toggleForm');
            const projectForm = document.getElementById('projectForm');

            toggleProjectFormBtn.addEventListener('click', function () {
                projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
            });
        })();
    </script>

</body>

</html>
