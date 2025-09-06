<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
	<meta charset="UTF-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>  إيكوبيشن  </title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" type="text/css" href="../assets/css/style.css"/>
</head>
<body>

  <?php include('../includes/insidebar.php'); 

include '../config.php';
$equipment_id = intval($_GET['equipment_id']);

// جلب السائقين المرتبطين مسبقًا
$current = [];
$res = mysqli_query($conn, "SELECT ed.id, d.id AS driver_id, d.name 
                             FROM equipment_drivers ed
                             JOIN drivers d ON ed.driver_id = d.id
                             WHERE ed.equipment_id = $equipment_id");
while ($r = mysqli_fetch_assoc($res)) {
    $current[] = $r['driver_id'];
    $linked[] = $r; // نخزن البيانات للعرض في الجدول
}
 
 ?>

  <div class="main">


    

  
<h2>إضافة سائقين للآلية</h2>
  <br/>
  <br/>
  <hr/>
   <form method="POST" action="save_equipment_drivers.php">
    <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">

    <label>اختر السائقين:</label><br>
    <select name="drivers[]" multiple size="6">
        <?php
        $drivers = mysqli_query($conn, "SELECT id, name FROM drivers");
        while ($d = mysqli_fetch_assoc($drivers)) {
            $selected = in_array($d['id'], $current) ? "selected" : "";
            echo "<option value='{$d['id']}' $selected>{$d['name']}</option>";
        }
        ?>
    </select>

    <br><br>
    <button type="submit">💾 حفظ</button>
</form>


<!-- جدول السائقين الحاليين -->
<h3>السائقين المرتبطين بهذه الآلية</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>الاسم</th>
        <th>الإجراء</th>
    </tr>
    <?php
    if (!empty($linked)) {
        foreach ($linked as $row) {
            echo "
            <tr>
                <td>{$row['name']}</td>
                <td>
                    <a href='delete_equipment_driver.php?id={$row['id']}&equipment_id=$equipment_id' 
                       onclick='return confirm(\"هل أنت متأكد من الحذف؟\")'>
                       ❌ حذف
                    </a>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='2'>لا يوجد سائقين مرتبطين</td></tr>";
    }
    ?>
</table>



  </div>

</body>
</html>