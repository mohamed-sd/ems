<?php
/** EQUIP-OPE-S04 — 8.11 التحرّك والوصول + 8.12 النقل بين المشاريع (موحّدة). Bolt-on. */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_movement.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
$scope_sql=$is_super_admin?"":" AND m.company_id = ".intval($company_id)." ";
$wp_scope =$is_super_admin?"":" AND wp.company_id = ".intval($company_id)." ";

$STATES=['مسودة','أمرٌ صادر','في الطريق','وصل','مستلَم بالموقع','جاهزٌ للعمل','ملغى'];
$DIRECTIONS=['التحاق أول','عودة من إجازة','مغادرة لإجازة/مأمورية','نقل بين مشاريع','مغادرة نهائية'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save' && $can_add) {
    $worker_id=intval($_POST['worker_id']??0);
    $direction=trim($_POST['direction']??'التحاق أول');
    $allocation=!empty($_POST['allocation_id'])?intval($_POST['allocation_id']):null;
    $origin=trim($_POST['origin']??''); $origin=$origin!==''?$origin:null;
    $dest_proj=!empty($_POST['destination_project_id'])?intval($_POST['destination_project_id']):null;
    $transport=trim($_POST['transport_mode']??''); $transport=$transport!==''?$transport:null;
    $dep=!empty($_POST['departure_date'])?$_POST['departure_date']:null;
    $exp=!empty($_POST['expected_arrival'])?$_POST['expected_arrival']:null;
    $act=!empty($_POST['actual_arrival'])?$_POST['actual_arrival']:null;
    $received_by=!empty($_POST['received_by'])?intval($_POST['received_by']):null;
    $housing=!empty($_POST['housing_unit_id'])?intval($_POST['housing_unit_id']):null;
    $site_zone=trim($_POST['site_zone']??''); $site_zone=$site_zone!==''?$site_zone:null;
    $safety=isset($_POST['safety_kit_received'])?1:0;
    $custody=null; // مؤجّل (S09)
    $ready=!empty($_POST['ready_date'])?$_POST['ready_date']:null;
    $transfer_type=trim($_POST['transfer_type']??''); $transfer_type=$transfer_type!==''?$transfer_type:null;
    $from_proj=!empty($_POST['from_project_id'])?intval($_POST['from_project_id']):null;
    $to_proj=!empty($_POST['to_project_id'])?intval($_POST['to_project_id']):null;
    $state=trim($_POST['state']??'مسودة');
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if ($worker_id>0) {
        $cid=$is_super_admin?null:$company_id;
        $st=$conn->prepare("INSERT INTO worker_movement
            (company_id,employee_id,direction,allocation_id,origin,destination_project_id,transport_mode,departure_date,expected_arrival,actual_arrival,received_by,housing_unit_id,site_zone,safety_kit_received,custody_received,ready_date,transfer_type,from_project_id,to_project_id,state,notes,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // types (22): company i,worker i,dir s,alloc i,origin s,dest i,transport s,dep s,exp s,act s,recv i,house i,zone s,safety i,custody i,ready s,ttype s,from i,to i,state s,notes s,by i
        $st->bind_param('iisisissssiisiissiissi',
            $cid,$worker_id,$direction,$allocation,$origin,$dest_proj,$transport,$dep,$exp,$act,$received_by,$housing,$site_zone,$safety,$custody,$ready,$transfer_type,$from_proj,$to_proj,$state,$notes,$user_id);
        $st->execute(); $st->close();
    }
    header("Location: worker_movement.php?msg=✅+تم+الحفظ"); exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) {
        $sc=$is_super_admin?"":" AND company_id = ".intval($company_id);
        $st=$conn->prepare("UPDATE worker_movement SET state=? WHERE id=? $sc"); $st->bind_param('si',$ns,$id); $st->execute(); $st->close();
    }
    header("Location: worker_movement.php?msg=✅+تم+تحديث+الحالة"); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) {
    $sc=$is_super_admin?"":" AND company_id = ".intval($company_id); $d=intval($_GET['delete']);
    $st=$conn->prepare("DELETE FROM worker_movement WHERE id=? $sc"); $st->bind_param('i',$d); $st->execute(); $st->close();
    header("Location: worker_movement.php?msg=✅+تم+الحذف"); exit();
}

$workers=[]; $wq=mysqli_query($conn,"SELECT wp.id,wp.name AS name FROM employees wp WHERE wp.is_workforce=1 $wp_scope ORDER BY wp.name");
if($wq){while($w=mysqli_fetch_assoc($wq)){$workers[$w['id']]=$w['name'];}}
$projects=[]; $pq=mysqli_query($conn,"SELECT id,name FROM project ORDER BY id DESC LIMIT 500");
if($pq){while($p=mysqli_fetch_assoc($pq)){$projects[$p['id']]=$p['name'];}}
$housing=[]; $hq=mysqli_query($conn,"SELECT id,name FROM housing_unit WHERE 1=1".($is_super_admin?"":" AND company_id = ".intval($company_id))." ORDER BY name");
if($hq){while($h=mysqli_fetch_assoc($hq)){$housing[$h['id']]=$h['name'];}}

$page_title="إيكوبيشن | التحرّك والنقل"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='التحرّك والنقل'; $header_icon='fas fa-route'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'أمر تحرّك/نقل');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="mForm" action="" method="post" class="allforms" style="display:none;">
        <input type="hidden" name="action" value="save">
        <div class="card-header"><h5><i class="fas fa-plus"></i> أمر تحرّك / نقل</h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>العامل</label><select name="worker_id" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>نوع الحركة</label><select name="direction"><?php foreach($DIRECTIONS as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach($STATES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>تخصيص مرتبط (#)</label><input type="number" name="allocation_id"></div>

            <div class="field"><label>نقطة الانطلاق</label><input type="text" name="origin"></div>
            <div class="field"><label>الوجهة (مشروع)</label><select name="destination_project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>وسيلة النقل</label><select name="transport_mode"><option value="">—</option><option>بري</option><option>جوي</option><option>ترتيب مورد</option></select></div>
            <div class="field"><label>السكن</label><select name="housing_unit_id"><option value="">—</option><?php foreach($housing as $hid=>$hn): ?><option value="<?= intval($hid) ?>"><?= htmlspecialchars($hn) ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>تاريخ التحرك</label><input type="date" name="departure_date"></div>
            <div class="field"><label>الوصول المتوقع</label><input type="date" name="expected_arrival"></div>
            <div class="field"><label>الوصول الفعلي</label><input type="date" name="actual_arrival"></div>
            <div class="field"><label>تاريخ الجاهزية</label><input type="date" name="ready_date"></div>

            <div class="field"><label>منطقة العمل</label><input type="text" name="site_zone"></div>
            <div class="field" style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="safety_kit_received" id="skr" value="1"><label for="skr" style="margin:0;">استلام معدات السلامة</label></div>
            <div class="field"><label>نوع النقل (للنقل)</label><select name="transfer_type"><option value="">—</option><option>مؤقت</option><option>دائم</option><option>إعادة تخصيص</option></select></div>
            <div class="field"><label>المستلِم (موظف #)</label><input type="number" name="received_by"></div>

            <div class="field"><label>من مشروع</label><select name="from_project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>إلى مشروع</label><select name="to_project_id"><option value="">—</option><?php foreach($projects as $pid=>$pn): ?><option value="<?= intval($pid) ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?></select></div>
            <div class="field" style="grid-column:3/-1;"><label>ملاحظات</label><input type="text" name="notes"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>العامل</th><th>الحركة</th><th>الوجهة</th><th>الوصول الفعلي</th><th>الحالة</th></tr></thead><tbody>
        <?php $list=mysqli_query($conn,"SELECT m.*, e.name AS wname, p.name AS pname
            FROM worker_movement m LEFT JOIN employees e ON e.id=m.employee_id
            LEFT JOIN project p ON p.id=m.destination_project_id WHERE 1=1 $scope_sql ORDER BY m.id DESC");
        $i=1; $WF_VIEW = []; if($list){ while($r=mysqli_fetch_assoc($list)):
            $sc=($r['state']==='جاهزٌ للعمل'||$r['state']==='مستلَم بالموقع')?'status-active':(($r['state']==='ملغى')?'status-inactive':'status-warning');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل أمر التحرّك/النقل', 'fas fa-route', [
                ems_wf_field('العامل', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('نوع الحركة', $r['direction'], 'fas fa-arrows-turn-right'),
                ems_wf_field('الوجهة (مشروع)', $r['pname'] ?: '-', 'fas fa-folder-open'),
                ems_wf_field('نقطة الانطلاق', $r['origin'] ?: '-', 'fas fa-location-arrow'),
                ems_wf_field('وسيلة النقل', $r['transport_mode'] ?: '-', 'fas fa-van-shuttle'),
                ems_wf_field('تاريخ التحرك', $r['departure_date'] ?: '-', 'fas fa-calendar-day'),
                ems_wf_field('الوصول المتوقع', $r['expected_arrival'] ?: '-', 'fas fa-calendar'),
                ems_wf_field('الوصول الفعلي', $r['actual_arrival'] ?: '-', 'fas fa-calendar-check'),
                ems_wf_field('تاريخ الجاهزية', $r['ready_date'] ?: '-', 'fas fa-circle-check'),
                ems_wf_field('نوع النقل', $r['transfer_type'] ?: '-', 'fas fa-right-left'),
                ems_wf_field('منطقة العمل', $r['site_zone'] ?: '-', 'fas fa-map-location-dot'),
                ems_wf_field('استلام معدات السلامة', intval($r['safety_kit_received']) ? 'نعم' : 'لا', 'fas fa-helmet-safety'),
                ems_wf_field('التخصيص المرتبط', $r['allocation_id'] ? ('#' . intval($r['allocation_id'])) : '-', 'fas fa-diagram-project'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns" style="gap:4px;align-items:center;">
                <?= ems_wf_view_button($r['id']) ?>
                <form action="" method="post" style="display:inline;"><input type="hidden" name="action" value="set_state"><input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                    <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                </form>
                <?php if($can_delete): ?><a href="worker_movement.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['direction']) ?></td><td><?= htmlspecialchars($r['pname'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['actual_arrival'] ?: '-') ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endwhile; } if(!$list||$i===1): ?><tr><td colspan="7" style="text-align:center;color:#888;padding:18px;">لا توجد أوامرٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('mForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
