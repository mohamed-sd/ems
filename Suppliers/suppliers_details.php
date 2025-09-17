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
    <title>إيكوبيشن | تفاصيل المورد</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- Bootstrab 5 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS الموقع -->
    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>

<body>

    <?php include('../insidebar.php'); ?>

    <div class="main">

        <!-- <h2>تفاصيل المشروع</h2> -->

        <a href="supplierscontracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
            <i class="fa fa-plus"></i> العقودات
        </a>
        <!-- <a href="../Equipments/equipments.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اضافة آلية
    </a> -->
        <!--  <a href="../Contracts/contracts.php?id=<?php echo $_GET['id']; ?>" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> العقودات
    </a> -->

        <h3> تفاصير المورد : </h3>
        <br />

        <?php
        include '../config.php';

        $project = $_GET['id'];

        $select = mysqli_query($conn, "SELECT * , 
                      (SELECT COUNT(*) FROM equipments WHERE equipments.suppliers = suppliers.id ) as 'equipments' 
                      FROM `suppliers` WHERE `id` = $project ORDER BY id DESC");
        while ($row = mysqli_fetch_array($select)) {
            ?>
            <div class="report">
                <div class="row">
                    <div class="col-lg-2 col-5">اسم المورد </div>
                    <div class="col-lg-4 col-7"><?php echo $row['name']; ?></div>
                    <div class="col-lg-2 col-5"> رقم الهاتف </div>
                    <div class="col-lg-4 col-7"><?php echo $row['phone']; ?></div>
                       <div class="col-lg-2 col-5"> عدد الآليات </div>
                    <div class="col-lg-4 col-7"> <?php echo $row['equipments']; ?> </div>
                    <div class="col-lg-2 col-5"> الحالة </div>
                    <div class="col-lg-4 col-7"><?php echo $row['status']=="1"?"نشط":"معلق"; ?></div>
                </div>
            </div>
                <?php
        } // end while loop
        ?>


            <br /> <br /> <br />

            <!-- جدول المشاريع -->
            <h3> الآليات </h3>
            <br />
            <table id="projectsTable" class="display" style="width:100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="text-align: right;">كود المعدة</th>
                        <th style="text-align: right;"> الاسم </th>
                        <th style="text-align: right;">نوع الآليه</th>
                        <!-- <th style="text-align: right;"> اسم العميل </th> -->
                        <!-- <th style="text-align: right;">إجراءات</th> -->
                    </tr>
                </thead>
                <tbody>
                    <?php

                    // جلب المشاريع
                    $query = "SELECT `id`, `code`, `type`, `name`, `status` FROM `equipments` where suppliers = $project ORDER BY id DESC";
                    $result = mysqli_query($conn, $query);
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $row['code'] . "</td>";
                        echo "<td>" . $row['name'] . "</td>";
                        echo $row['type'] == "1" ? "<td style='color:green;'> حفار </td>" : "<td style='color:red;'> قلاب </td>";

                        // echo "<td>".$row['status']."</td>";
                        // echo "<td>
                        //         <a href='edit.php?id=".$row['id']."'>تعديل</a> | 
                        //         <a href='delete.php?id=".$row['id']."' onclick='return confirm(\"هل أنت متأكد؟\")'>حذف</a> | <a href=''> عرض </a>
                        //       </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <br />




        </div>

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        <script>
            (function () {
                // تشغيل DataTable بالعربية
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

                // التحكم في إظهار وإخفاء الفورم
                const toggleProjectFormBtn = document.getElementById('toggleForm');
                const projectForm = document.getElementById('projectForm');

                toggleProjectFormBtn.addEventListener('click', function () {
                    projectForm.style.display = projectForm.style.display === "none" ? "block" : "none";
                });
            })();
        </script>

</body>

</html>