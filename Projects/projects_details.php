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
    <title>Ø¥ÙŠÙƒÙˆØ¨ÙŠØ´Ù† | ØªÙØ§ØµÙŠÙ„ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹</title>

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

        <div class="aligin">
            <a href="../Contracts/contracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
                <i class="fa fa-plus"></i> Ø¹Ù‚ÙˆØ¯Ø§Øª Ø§Ù„Ù…Ø´Ø±ÙˆØ¹
            </a>
            <!-- <?php if ($_SESSION['user']['role'] == "1") { ?>
                <a href="../users.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
                    <i class="fa fa-plus"></i> Ù…Ø¯Ø±Ø§Ø¡ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹
                </a>
            <?php } ?> -->
        </div>

        <h3> ØªÙØ§ØµÙŠØ± Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ : </h3>

                            <?php
                    include '../config.php';
                    $project = $_GET['id'];
                    $suppliers = mysqli_query($conn, "SELECT COUNT(DISTINCT pm.suppliers) AS total_suppliers
FROM equipments pm
JOIN operations m ON pm.id = m.equipment
WHERE m.project =  $project;");
                    $rowsuppliers = mysqli_fetch_assoc($suppliers);
                    $total_suppliers = $rowsuppliers['total_suppliers'];
                    $select = mysqli_query($conn, "SELECT * FROM `project` WHERE `id` = $project");
                    while ($row = mysqli_fetch_array($select)) {
                        ?>
    <div class="report">
        <div class="row">
            <div class="col-lg-2 col-5">
                Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ 
            </div>
            <div class="col-lg-4 col-7">
                <?php echo $row['name']; ?>
            </div>
            <div class="col-lg-2 col-5">
                Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ 
            </div>
            <div class="col-lg-4 col-7">
                <?php echo $row['client']; ?>
            </div>

            <div class="col-lg-2 col-5">
                Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ 
            </div>
            <div class="col-lg-4 col-7">
                <?php echo $row['location']; ?>
            </div>
            <div class="col-lg-2 col-5">
               Ø¹Ø¯Ø¯ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ† 
            </div>
            <div class="col-lg-4 col-7">
               <?php echo $total_suppliers; ?>
            </div>
        </div>
    </div>

        <?php } ?>

        <br /> <br /> <br />

        <!-- Ø¬Ø¯ÙˆÙ„ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹ -->
        <h3> Ø§Ù„Ø¢Ù„ÙŠØ§Øª </h3>
        <br />
        <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ø§Ø³Ù… Ø§Ù„Ø§Ù„ÙŠØ©</th>
                    <th>ØªØ³Ù…ÙŠØ© Ø§Ù„Ø¹Ù…ÙŠÙ„</th>
                    <th>Ø§Ù„Ù…ÙˆØ±Ø¯</th>
                    <th>Ø§Ù„Ù†ÙˆØ¹</th>
                    <!-- <th style="text-align: right;">Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th> -->
                </tr>
            </thead>
            <tbody>
                <?php


                // Ø¥Ø¶Ø§ÙØ© Ù…Ø´Ø±ÙˆØ¹ Ø¬Ø¯ÙŠØ¯ Ø¹Ù†Ø¯ Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙÙˆØ±Ù…
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
                    $name = mysqli_real_escape_string($conn, $_POST['name']);
                    $client = mysqli_real_escape_string($conn, $_POST['client']);
                    $location = mysqli_real_escape_string($conn, $_POST['location']);
                    $total = floatval($_POST['total']);
                    $date = date('Y-m-d H:i:s');
                    mysqli_query($conn, "INSERT INTO project (name, client, location, total, create_at) VALUES ('$name', '$client', '$location', '$total', '$date')");
                }




                // Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹
                $query = "SELECT m.id, s.name AS supplier_name, m.type, m.code, m.name AS equipment_name
FROM equipments m
JOIN operations pm ON m.id = pm.equipment
JOIN suppliers s ON m.suppliers = s.id
WHERE pm.project = $project";
                $result = mysqli_query($conn, $query);
                $i = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . $i++ . "</td>";
                    echo "<td>" . $row['code'] . "</td>";
                    echo "<td>" . $row['equipment_name'] . "</td>";
                    echo "<td>" . $row['supplier_name'] . "</td>";

                    echo $row['type'] == "1" ? "<td style='color:green;'> Ø­ÙØ§Ø± </td>" : "<td style='color:red;'> Ù‚Ù„Ø§Ø¨ </td>";



                    // echo "<td>
                    //         <a href='edit.php?id=".$row['id']."'>ØªØ¹Ø¯ÙŠÙ„</a> | 
                    //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"Ù‡Ù„ Ø£Ù†Øª Ù…ØªØ£ÙƒØ¯ØŸ\")'>Ø­Ø°Ù</a> 
                    //       </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <br />
        <h3> Ø§Ù„Ø¹Ù‚ÙˆØ¯ </h3>
        <br />
        <table id="projectsTable1" class="projectsTable" style="width:100%; margin-top: 20px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th style="text-align: center;">ØªØ§Ø±ÙŠØ® Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©</th>
                    <th style="text-align: center;"> Ø§Ù„Ù…Ø³ØªÙ‡Ø¯Ù Ø´Ù‡Ø±ÙŠØ§</th>
                    <th style="text-align: center;">Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹</th>
                    <th style="text-align: center;">Ø§Ù„Ø­Ø§Ù„Ø©</th>
                    <th style="text-align: center;">Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include '../config.php';

                $query = "SELECT * FROM `contracts` where project LIKE '$project' ORDER BY id DESC";
                $result = mysqli_query($conn, $query);
                $i = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                     $status = $row['status']=="1" ? "<font color='green'>Ø³Ø§Ø±ÙŠ</font>" : "
                    <font color='red'>Ù…Ù†ØªÙ‡ÙŠ</font>";

                    echo "<tr>";
                    echo "<td>" . $i++ . "</td>";
                    echo "<td>" . $row['contract_signing_date'] . "</td>";
                    echo "<td>" . $row['hours_monthly_target'] . "</td>";
                    echo "<td>" . $row['forecasted_contracted_hours'] . "</td>";
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
