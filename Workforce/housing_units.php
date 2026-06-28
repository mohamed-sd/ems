<?php
/** EQUIP-OPE-S04 — وحدات السكن (مرجعي · 8.11). Bolt-on، صفر لمسٍ للقائم. */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/housing_units.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
$scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id) . " ";

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save' && ($can_add || $can_edit)) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $capacity = $_POST['capacity'] !== '' ? intval($_POST['capacity']) : null;
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($name !== '') {
        if ($id > 0 && $can_edit) {
            $sc = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
            $st = $conn->prepare("UPDATE housing_unit SET name=?, project_id=?, capacity=?, location=?, notes=? WHERE id=? $sc");
            $st->bind_param('siissi', $name, $project_id, $capacity, $location, $notes, $id);
            $st->execute(); $st->close();
        } elseif ($can_add) {
            $cid = $is_super_admin ? null : $company_id;
            $st = $conn->prepare("INSERT INTO housing_unit (company_id,name,project_id,capacity,location,notes) VALUES (?,?,?,?,?,?)");
            $st->bind_param('isiiss', $cid, $name, $project_id, $capacity, $location, $notes);
            $st->execute(); $st->close();
        }
    }
    header("Location: housing_units.php?msg=✅+تم+الحفظ"); exit();
}
if (($_GET['delete'] ?? '') !== '' && $can_delete) {
    $sc = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $st = $conn->prepare("DELETE FROM housing_unit WHERE id=? $sc"); $d=intval($_GET['delete']);
    $st->bind_param('i',$d); $st->execute(); $st->close();
    header("Location: housing_units.php?msg=✅+تم+الحذف"); exit();
}

$projects = [];
$pq = mysqli_query($conn, "SELECT id, name FROM project ORDER BY id DESC LIMIT 500");
if ($pq) { while ($p = mysqli_fetch_assoc($pq)) { $projects[$p['id']] = $p['name']; } }

$page_title = "إيكوبيشن | وحدات السكن";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='وحدات السكن'; $header_icon='fas fa-building'; $header_actions=array();
    if ($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'وحدة سكن');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="hForm" action="" method="post" class="allforms" style="display:none;">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
        <div class="card-header"><h5><i class="fas fa-plus"></i> وحدة سكن/مخيم</h5></div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:14px;">
            <div class="field"><label>الاسم</label><input type="text" name="name" required></div>
            <div class="field"><label>المشروع</label><select name="project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>السعة</label><input type="number" name="capacity"></div>
            <div class="field"><label>الموقع</label><input type="text" name="location"></div>
            <div class="field" style="grid-column:2/-1;"><label>ملاحظات</label><input type="text" name="notes"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>الاسم</th><th>المشروع</th><th>السعة</th><th>الموقع</th></tr></thead><tbody>
        <?php $list=mysqli_query($conn,"SELECT h.*, p.name AS pname FROM housing_unit h LEFT JOIN project p ON p.id=h.project_id WHERE 1=1 $scope ORDER BY h.id DESC");
        $i=1; $WF_VIEW = []; if($list){ while($r=mysqli_fetch_assoc($list)):
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل وحدة السكن', 'fas fa-building', [
                ems_wf_field('الاسم', $r['name'], 'fas fa-signature', ['size' => 'lg']),
                ems_wf_field('المشروع', $r['pname'] ?: '-', 'fas fa-folder-open'),
                ems_wf_field('السعة', $r['capacity'] !== null ? $r['capacity'] : '-', 'fas fa-users'),
                ems_wf_field('الموقع', $r['location'] ?: '-', 'fas fa-location-dot', ['size' => 'lg']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns">
                <?= ems_wf_view_button($r['id']) ?>
                <?php if($can_delete): ?><a href="housing_units.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td><td><?= htmlspecialchars($r['pname'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['capacity'] ?: '-') ?></td><td><?= htmlspecialchars($r['location'] ?: '-') ?></td></tr>
        <?php endwhile; } if(!$list||$i===1){} ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('hForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
