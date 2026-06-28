<?php
/** EQUIP-OPE-S04 — 8.6 الإجازات التبادلية + 8.13 الغياب والطوارئ (موحّدة). Bolt-on. */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/CoverageService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_leave_absence.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
$scope_sql = $is_super_admin ? "" : " AND la.company_id = " . intval($company_id) . " ";
$wp_scope  = $is_super_admin ? "" : " AND wp.company_id = " . intval($company_id) . " ";

$STATES = ['مطلوب','معتمد','مفتوح','مُغطًّى','منتهٍ','مغلق'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save' && $can_add) {
    $worker_id=intval($_POST['worker_id']??0);
    $event_class=trim($_POST['event_class']??'مخطّط');
    $event_type=trim($_POST['event_type']??'');
    $date_from=!empty($_POST['date_from'])?$_POST['date_from']:null;
    $date_to=!empty($_POST['date_to'])?$_POST['date_to']:null;
    $substitute=!empty($_POST['substitute_id'])?intval($_POST['substitute_id']):null;
    $rotation=trim($_POST['rotation_pattern']??''); $rotation=$rotation!==''?$rotation:null;
    $next_due=!empty($_POST['next_due_date'])?$_POST['next_due_date']:null;
    $coverage=trim($_POST['coverage_impact']??''); $coverage=$coverage!==''?$coverage:null;
    $outcome=trim($_POST['outcome']??''); $outcome=$outcome!==''?$outcome:null;
    $state=trim($_POST['state']??'مطلوب');
    $reason=trim($_POST['reason']??''); $reason=$reason!==''?$reason:null;
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;
    if ($worker_id>0 && $event_type!=='') {
        // محرّك التغطية: عند خروج العامل بلا بديلٍ محدَّد، نقترح أنسب بديلٍ متاح.
        if ($substitute===null) { $substitute = ems_coverage_best_id($conn, $worker_id); }
        $cid=$is_super_admin?null:$company_id;
        $st=$conn->prepare("INSERT INTO worker_leave_absence
            (company_id,employee_id,event_class,event_type,date_from,date_to,substitute_id,rotation_pattern,next_due_date,coverage_impact,outcome,state,reason,notes,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // types: company i, worker i, class s, type s, from s, to s, sub i, rot s, due s, cov s, out s, state s, reason s, notes s, by i
        $st->bind_param('iissssisssssssi',
            $cid,$worker_id,$event_class,$event_type,$date_from,$date_to,$substitute,$rotation,$next_due,$coverage,$outcome,$state,$reason,$notes,$user_id);
        $st->execute(); $st->close();
    }
    header("Location: worker_leave_absence.php?msg=✅+تم+الحفظ"); exit();
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) {
        $sc=$is_super_admin?"":" AND company_id = ".intval($company_id);
        $st=$conn->prepare("UPDATE worker_leave_absence SET state=? WHERE id=? $sc");
        $st->bind_param('si',$ns,$id); $st->execute(); $st->close();
    }
    header("Location: worker_leave_absence.php?msg=✅+تم+تحديث+الحالة"); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) {
    $sc=$is_super_admin?"":" AND company_id = ".intval($company_id); $d=intval($_GET['delete']);
    $st=$conn->prepare("DELETE FROM worker_leave_absence WHERE id=? $sc"); $st->bind_param('i',$d); $st->execute(); $st->close();
    header("Location: worker_leave_absence.php?msg=✅+تم+الحذف"); exit();
}

$workers=[]; $wq=mysqli_query($conn,"SELECT wp.id,wp.name AS name FROM employees wp WHERE wp.is_workforce=1 $wp_scope ORDER BY wp.name");
if($wq){while($w=mysqli_fetch_assoc($wq)){$workers[$w['id']]=$w['name'];}}

$page_title="إيكوبيشن | الإجازات والغياب"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='الإجازات والغياب'; $header_icon='fas fa-plane-departure'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تسجيل إجازة/غياب');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="lForm" action="" method="post" class="allforms" style="display:none;">
        <input type="hidden" name="action" value="save">
        <div class="card-header"><h5><i class="fas fa-plus"></i> إجازة / غياب</h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>العامل</label><select name="worker_id" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>التصنيف</label><select name="event_class"><option value="مخطّط">مخطّط (إجازة/تناوب)</option><option value="طارئ">طارئ (غياب)</option></select></div>
            <div class="field"><label>النوع</label><select name="event_type" required>
                <optgroup label="مخطّط"><option>تبادلية</option><option>اعتيادية</option><option>مأمورية</option></optgroup>
                <optgroup label="طارئ"><option>غياب مفاجئ</option><option>انقطاع عن العمل</option><option>هروب من الموقع</option><option>مرض مفاجئ</option><option>إصابة</option><option>ظرف أسري طارئ</option><option>وفاة</option></optgroup>
            </select></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach($STATES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>من</label><input type="date" name="date_from"></div>
            <div class="field"><label>إلى</label><input type="date" name="date_to"></div>
            <div class="field"><label>البديل</label><select name="substitute_id"><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>أثر التغطية</label><select name="coverage_impact"><option value="">—</option><option>مغطًّى</option><option>فجوة جزئية</option><option>فجوة حرجة</option></select></div>
            <div class="field"><label>الاستحقاق القادم</label><input type="date" name="next_due_date"></div>
            <div class="field"><label>النتيجة</label><select name="outcome"><option value="">—</option><option>عودة للعمل</option><option>تحويل لإجازة</option><option>إنهاء وتسوية</option></select></div>
            <div class="field" style="grid-column:1/-1;"><label>السبب/ملاحظات</label><input type="text" name="reason"></div>
        </div>
        <div style="padding:0 14px 16px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>
    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>العامل</th><th>التصنيف</th><th>النوع</th><th>من</th><th>إلى</th><th>البديل</th><th>الحالة</th></tr></thead><tbody>
        <?php $list=mysqli_query($conn,"SELECT la.*, e.name AS wname, e2.name AS sname
            FROM worker_leave_absence la
            LEFT JOIN employees e ON e.id=la.employee_id
            LEFT JOIN employees e2 ON e2.id=la.substitute_id
            WHERE 1=1 $scope_sql ORDER BY la.id DESC");
        $i=1; $WF_VIEW = []; if($list){ while($r=mysqli_fetch_assoc($list)):
            $sc=($r['state']==='مغلق'||$r['state']==='منتهٍ')?'status-inactive':(($r['state']==='مُغطًّى'||$r['state']==='معتمد')?'status-active':'status-warning');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل الإجازة/الغياب', 'fas fa-plane-departure', [
                ems_wf_field('العامل', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('التصنيف', $r['event_class'], 'fas fa-layer-group'),
                ems_wf_field('النوع', $r['event_type'], 'fas fa-tag'),
                ems_wf_field('من', $r['date_from'] ?: '-', 'fas fa-calendar-day'),
                ems_wf_field('إلى', $r['date_to'] ?: '-', 'fas fa-calendar-xmark'),
                ems_wf_field('البديل', $r['sname'] ?: '-', 'fas fa-user-shield'),
                ems_wf_field('أثر التغطية', $r['coverage_impact'] ?: '-', 'fas fa-shield-halved'),
                ems_wf_field('نمط التناوب', $r['rotation_pattern'] ?: '-', 'fas fa-rotate'),
                ems_wf_field('الاستحقاق القادم', $r['next_due_date'] ?: '-', 'fas fa-calendar-check'),
                ems_wf_field('النتيجة', $r['outcome'] ?: '-', 'fas fa-flag-checkered'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('السبب/ملاحظات', $r['reason'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns" style="gap:4px;align-items:center;">
                <?= ems_wf_view_button($r['id']) ?>
                <form action="" method="post" style="display:inline;"><input type="hidden" name="action" value="set_state"><input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                    <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                </form>
                <?php if($can_delete): ?><a href="worker_leave_absence.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['event_class']) ?></td><td><?= htmlspecialchars($r['event_type']) ?></td>
            <td><?= htmlspecialchars($r['date_from'] ?: '-') ?></td><td><?= htmlspecialchars($r['date_to'] ?: '-') ?></td>
            <td><?= htmlspecialchars($r['sname'] ?: '-') ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endwhile; } if(!$list||$i===1): ?><tr><td colspan="9" style="text-align:center;color:#888;padding:18px;">لا توجد سجلاتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('lForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
