<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> إيكوبيشن | التقارير </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" type="text/css" href="../assets/css/style.css" />
</head>

<body>

    <?php include('../insidebar.php');

    include '../config.php';
    $supplier_filter = isset($_GET['supplier']) ? $_GET['supplier'] : '';
    $project_filter = isset($_GET['project']) ? $_GET['project'] : '';

    $sql = "
SELECT 
    s.name AS supplier_name,
    p.name AS project_name,
    SUM(t.total_work_hours) AS total_hours
FROM timesheet t
JOIN operations o ON t.operator = o.id        -- التايم شيت مرتبط بالتشغيل
JOIN equipments e ON o.equipment = e.id   
JOIN suppliers s ON e.suppliers = s.id
JOIN projects p ON o.project = p.id
WHERE 1=1
";

    if (!empty($supplier_filter)) {
        $sql .= " AND s.id = '$supplier_filter' ";
    }
    if (!empty($project_filter)) {
        $sql .= " AND p.id = '$project_filter' ";
    }

    $sql .= " GROUP BY s.name, p.name ";

    $result = mysqli_query($conn, $sql);


    ?>

    <div class="main">
        
        <h2> التقارير </h2>
        <br/>
        <br/>
        <hr/>
        <br/>


        <button class="add"><a href="deliy.php"><i class="fa fa-clock"></i> <span>ساعات اليوم</span></a></button>
        <button class="add"><a href="deriver.php"><i class="fa fa-clock"></i> <span>ساعات السائق</span></a></button>
        <button class="add"><a href="timesheetdeliy.php"><i class="fa fa-clock"></i> <span>ساعات العمل اليومية</span></a></button>

                <button class="add"><a href="contract_report.php"><i class="fa fa-clock"></i> <span>العقد  </span></a></button>
                                <button class="add"><a href="contractall.php"><i class="fa fa-clock"></i> <span>احصائيات العقد  </span></a></button>



        <br/>
        <br/>

        <form  method="GET">
            <label>المورد:</label>
            <select name="supplier">
                <option value="">-- اختر المورد --</option>
                <?php
                $sup = mysqli_query($conn, "SELECT id, name FROM suppliers");
                while ($row = mysqli_fetch_assoc($sup)) {
                    $selected = ($supplier_filter == $row['id']) ? "selected" : "";
                    echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                }
                ?>
            </select>

            <label>المشروع:</label>
            <select name="project">
                <option value="">-- اختر المشروع --</option>
                <?php
                $prj = mysqli_query($conn, "SELECT id, name FROM projects");
                while ($row = mysqli_fetch_assoc($prj)) {
                    $selected = ($project_filter == $row['id']) ? "selected" : "";
                    echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                }
                ?>
            </select>

            <button type="submit">عرض</button>
        </form>

        <br/>

        <table id="projectsTable" class="table">
            <thead>
            <tr>
                <th>المورد</th>
                <th>المشروع</th>
                <th>إجمالي ساعات التشغيل</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td><?php echo $row['supplier_name']; ?></td>
                    <td><?php echo $row['project_name']; ?></td>
                    <td><?php echo $row['total_hours']; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

    </div>

</body>

</html>