<?php
$page_title = "إيكوبيشن | اضافة سائق ";
include("../inheader.php");
?>

<?php include('../insidebar.php');

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





    <h2>إضافة مشغلين للآلية</h2>
    <br />
    <br />
    <hr />

    <a href="javascript:void(0)" id="toggleForm" class="add">
        <i class="fa fa-plus"></i> اسناد مشغل
    </a>

    <form id="projectForm" method="POST" action="save_equipment_drivers.php">
        <input type="hidden" name="equipment_id" value="<?php echo $equipment_id; ?>">

        <label>اختر المشغلين:</label><br>
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
    <h3>المشغلين المرتبطين بهذه الآلية</h3>
    <table id="projectsTable" class="display nowrap" style="width:100%; margin-top: 20px;">
        <thead>
        <tr>
            <th>الاسم</th>
            <th>الإجراء</th>
        </tr>
        </thead>
        <tbody>
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


            
            echo "<tr><td>لا يوجد سائقين مرتبطين</td><td>-</td></tr>";
        }
        ?>
        </tbody>
    </table>
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
  $(function () { // يضمن التنفيذ بعد تحميل الـ DOM
    // تهيئة الجدول
    $('#projectsTable').DataTable({
      responsive: true,
      dom: 'Bfrtip',
      buttons: [
        { extend: 'copy', text: 'نسخ' },
        { extend: 'excel', text: 'تصدير Excel' },
        { extend: 'csv', text: 'تصدير CSV' },
        { extend: 'pdf', text: 'تصدير PDF' },
        { extend: 'print', text: 'طباعة' }
      ],
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
      }
    });

    // تحكم اظهار/اخفاء الفورم بطريقة آمنة
    $('#toggleForm').on('click', function (e) {
      e.preventDefault();
      $('#projectForm').toggle();
    });
  });
</script>

</body>

</html>