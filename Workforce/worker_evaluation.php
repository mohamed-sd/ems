<?php
/** EQUIP-OPE-S04 — 8.5 التقييم والحوافز والجزاءات (+ بنود مؤشّرات الأداء KPI). Bolt-on.
 *  المالية: يدوي + تعليق (قرار 5). الدرجة الإجمالية تُحتسَب آلياً من بنود المؤشّرات (متوسطٌ موزون). */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$pp = check_page_permissions($conn, 'Workforce/worker_evaluation.php');
$can_view=$pp['can_view']; $can_add=$pp['can_add']; $can_edit=$pp['can_edit']; $can_delete=$pp['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+❌"); exit(); }
$scope_sql=$is_super_admin?"":" AND ev.company_id = ".intval($company_id)." ";
$wp_scope =$is_super_admin?"":" AND wp.company_id = ".intval($company_id)." ";
$company_scope_sql=$is_super_admin?"":" AND company_id = ".intval($company_id)." ";
$STATES=['مسودة','معتمد','مرحّل'];

// الدرجة الموزونة من بنود المؤشّرات: Σ(وزن×درجة)/Σ(وزن). إن انعدمت الأوزان فمتوسطٌ بسيط. null إن لا بنود.
function ems_eval_weighted_score($conn, $eval_id) {
    $eval_id=(int)$eval_id; if ($eval_id<=0) return null;
    $q=$conn->prepare("SELECT weight, score FROM worker_evaluation_kpi WHERE evaluation_id=?");
    if (!$q) return null;
    $q->bind_param('i',$eval_id); $q->execute(); $res=$q->get_result();
    $sumW=0.0; $sumWS=0.0; $sumS=0.0; $n=0;
    while ($l=$res->fetch_assoc()) {
        if ($l['score']===null || $l['score']==='') continue;
        $w=floatval($l['weight']); $s=floatval($l['score']);
        $n++; $sumS+=$s; $sumW+=$w; $sumWS+=$w*$s;
    }
    $q->close();
    if ($n===0) return null;
    return ($sumW>0) ? round($sumWS/$sumW,2) : round($sumS/$n,2);
}
// يعيد احتساب الدرجة من البنود ويخزّنها في worker_evaluation (إن وُجدت بنود).
function ems_eval_recompute_store($conn,$eval_id,$company_scope_sql) {
    $ws = ems_eval_weighted_score($conn,$eval_id);
    if ($ws!==null) {
        $st=$conn->prepare("UPDATE worker_evaluation SET score=? WHERE id=? $company_scope_sql");
        if ($st) { $st->bind_param('di',$ws,$eval_id); $st->execute(); $st->close(); }
    }
    return $ws;
}

// ── حفظ التقييم (إضافة/تعديل) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save') {
    $id=intval($_POST['id']??0); $is_editing=$id>0;
    if (($is_editing && !$can_edit) || (!$is_editing && !$can_add)) { header("Location: worker_evaluation.php?msg=لا+صلاحية+❌"); exit(); }
    $worker_id=intval($_POST['worker_id']??0);
    $period=!empty($_POST['period'])?$_POST['period']:null;
    $score=$_POST['score']!==''?floatval($_POST['score']):null;
    $ip_type=trim($_POST['incentive_penalty_type']??'بلا');
    $amount=$_POST['amount']!==''?floatval($_POST['amount']):null;
    $amt_note=trim($_POST['amount_finance_note']??''); $amt_note=$amt_note!==''?$amt_note:null;
    $op_hours=$_POST['operating_hours']!==''?floatval($_POST['operating_hours']):null;
    $att=$_POST['attendance_rate']!==''?floatval($_POST['attendance_rate']):null;
    $prod=$_POST['productivity']!==''?floatval($_POST['productivity']):null;
    $misuse=$_POST['misuse_faults']!==''?intval($_POST['misuse_faults']):null;
    $fuel=$_POST['fuel_consumption']!==''?floatval($_POST['fuel_consumption']):null;
    $safety=$_POST['safety_score']!==''?floatval($_POST['safety_score']):null;
    $state=trim($_POST['state']??'مسودة');
    if(!in_array($state,$STATES,true)) $state=$STATES[0];
    $notes=trim($_POST['notes']??''); $notes=$notes!==''?$notes:null;

    if (!$is_editing) {
        if ($worker_id<=0) { header("Location: worker_evaluation.php?msg=يجب+اختيار+عامل+❌"); exit(); }
        $cid=$is_super_admin?null:$company_id;
        $st=$conn->prepare("INSERT INTO worker_evaluation
            (company_id,employee_id,period,score,incentive_penalty_type,amount,amount_finance_note,operating_hours,attendance_rate,productivity,misuse_faults,fuel_consumption,safety_score,state,notes,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // types(16): company i,worker i,period s,score d,iptype s,amount d,amtnote s,ophours d,att d,prod d,misuse i,fuel d,safety d,state s,notes s,by i
        $nid=0;
        if($st){ $st->bind_param('iisdsdsdddiddssi',
            $cid,$worker_id,$period,$score,$ip_type,$amount,$amt_note,$op_hours,$att,$prod,$misuse,$fuel,$safety,$state,$notes,$user_id);
        $st->execute(); $nid=$st->insert_id; $st->close(); }
        // فتح وضع التعديل ليظهر لوح بنود المؤشّرات.
        header("Location: worker_evaluation.php?edit=".$nid."&msg=✅+تم+الحفظ"); exit();
    } else {
        $sc=$is_super_admin?"":" AND company_id = ".intval($company_id);
        $st=$conn->prepare("UPDATE worker_evaluation SET period=?,score=?,incentive_penalty_type=?,amount=?,amount_finance_note=?,operating_hours=?,attendance_rate=?,productivity=?,misuse_faults=?,fuel_consumption=?,safety_score=?,state=?,notes=? WHERE id=? $sc");
        // types(14): period s,score d,iptype s,amount d,amtnote s,ophours d,att d,prod d,misuse i,fuel d,safety d,state s,notes s,id i
        if($st){ $st->bind_param('sdsdsdddiddssi',
            $period,$score,$ip_type,$amount,$amt_note,$op_hours,$att,$prod,$misuse,$fuel,$safety,$state,$notes,$id);
        $st->execute(); $st->close(); }
        // إن وُجدت بنود مؤشّرات، تتقدّم الدرجة المحسوبة على المدخلة يدوياً.
        ems_eval_recompute_store($conn,$id,$company_scope_sql);
        header("Location: worker_evaluation.php?edit=".$id."&msg=✅+تم+التحديث"); exit();
    }
}

// ── إضافة بند مؤشّر (KPI) ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_kpi' && $can_edit) {
    $eid=intval($_POST['evaluation_id']??0);
    $kpi_name=trim($_POST['kpi_name']??'');
    $weight=$_POST['weight']!==''?floatval($_POST['weight']):null;
    $kscore=$_POST['kpi_score']!==''?floatval($_POST['kpi_score']):null;
    $knote=trim($_POST['kpi_note']??''); $knote=$knote!==''?$knote:null;
    // تحقّق أنّ التقييم ضمن نطاق الشركة قبل الإضافة.
    $own=0; $g=$conn->prepare("SELECT id FROM worker_evaluation WHERE id=? $company_scope_sql LIMIT 1");
    if ($g){ $g->bind_param('i',$eid); $g->execute(); $own=$g->get_result()->num_rows; $g->close(); }
    if ($eid>0 && $own>0 && $kpi_name!=='') {
        $st=$conn->prepare("INSERT INTO worker_evaluation_kpi (evaluation_id,kpi_name,weight,score,notes) VALUES (?,?,?,?,?)");
        if($st){ $st->bind_param('isdds',$eid,$kpi_name,$weight,$kscore,$knote); $st->execute(); $st->close(); }
        ems_eval_recompute_store($conn,$eid,$company_scope_sql);
    }
    header("Location: worker_evaluation.php?edit=".$eid."&msg=✅+تم+حفظ+البند"); exit();
}
if (($_GET['del_kpi']??'')!=='' && $can_delete) {
    $kid=intval($_GET['del_kpi']); $eid=intval($_GET['edit']??0);
    // احذف البند فقط إن كان تقييمه ضمن نطاق الشركة.
    $own=0; $g=$conn->prepare("SELECT ev.id FROM worker_evaluation ev JOIN worker_evaluation_kpi k ON k.evaluation_id=ev.id WHERE k.id=? $scope_sql LIMIT 1");
    if ($g){ $g->bind_param('i',$kid); $g->execute(); $own=$g->get_result()->num_rows; $g->close(); }
    if ($own>0) {
        $st=$conn->prepare("DELETE FROM worker_evaluation_kpi WHERE id=?"); $st->bind_param('i',$kid); $st->execute(); $st->close();
        if ($eid>0) ems_eval_recompute_store($conn,$eid,$company_scope_sql);
    }
    header("Location: worker_evaluation.php?edit=".$eid."&msg=✅+تم+حذف+البند"); exit();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) { $sc=$is_super_admin?"":" AND company_id = ".intval($company_id);
        $st=$conn->prepare("UPDATE worker_evaluation SET state=? WHERE id=? $sc"); $st->bind_param('si',$ns,$id); $st->execute(); $st->close(); }
    header("Location: worker_evaluation.php?msg=✅+تم+تحديث+الحالة"); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) { $sc=$is_super_admin?"":" AND company_id = ".intval($company_id); $d=intval($_GET['delete']);
    $st=$conn->prepare("DELETE FROM worker_evaluation WHERE id=? $sc"); $st->bind_param('i',$d); $st->execute(); $st->close();
    header("Location: worker_evaluation.php?msg=✅+تم+الحذف"); exit(); }

// ── تحميل تقييمٍ للتعديل + بنوده ──────────────────────────────────────────────────
$edit=null; $kpis=[]; $edit_id=intval($_GET['edit']??0);
if ($edit_id>0) {
    $sc=$is_super_admin?"":" AND ev.company_id = ".intval($company_id);
    $st=$conn->prepare("SELECT ev.*, e.name AS wname FROM worker_evaluation ev LEFT JOIN employees e ON e.id=ev.employee_id WHERE ev.id=? $sc LIMIT 1");
    $st->bind_param('i',$edit_id); $st->execute(); $edit=$st->get_result()->fetch_assoc(); $st->close();
    if ($edit) { $kq=mysqli_query($conn,"SELECT * FROM worker_evaluation_kpi WHERE evaluation_id=".intval($edit_id)." ORDER BY id"); if($kq){while($k=mysqli_fetch_assoc($kq)){$kpis[]=$k;}} }
}

$workers=[]; $wq=mysqli_query($conn,"SELECT wp.id,wp.name AS name FROM employees wp WHERE 1=1 $wp_scope ORDER BY wp.name");
if($wq){while($w=mysqli_fetch_assoc($wq)){$workers[$w['id']]=$w['name'];}}

$page_title="إيكوبيشن | تقييم العاملين"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='تقييم العاملين'; $header_icon='fas fa-star-half-stroke'; $header_actions=array();
    if($can_add) $header_actions[]=array('id'=>'toggleForm','class'=>'add-btn','icon'=>'fas fa-plus-circle','label'=>'تقييم جديد');
    $header_back=array('href'=>'worker_register.php','class'=>'','icon'=>'fas fa-arrow-right','label'=>'سجل العامل');
    include('../includes/page_header.php'); ?>
    <?php if(!empty($_GET['msg'])): $ok=strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <form id="eForm" action="" method="post" class="allforms" style="<?= $edit?'display:block;':'display:none;' ?>">
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?intval($edit['id']):0 ?>">
        <div class="card-header"><h5><i class="fas <?= $edit?'fa-edit':'fa-plus' ?>"></i> <?= $edit?'تعديل تقييم':'تقييم عامل' ?></h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>الموظف</label><?php if($edit): ?><input type="text" value="<?= htmlspecialchars($edit['wname'] ?: ('#'.$edit['employee_id'])) ?>" disabled><?php else: ?><select name="worker_id" required><option value="">—</option><?php foreach($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select><?php endif; ?></div>
            <div class="field"><label>الفترة</label><input type="date" name="period" value="<?= htmlspecialchars($edit['period'] ?? '') ?>"></div>
            <div class="field"><label>الدرجة <?= !empty($kpis)?'(محسوبةٌ من البنود)':'' ?></label><input type="number" step="0.01" name="score" value="<?= htmlspecialchars($edit['score'] ?? '') ?>" <?= !empty($kpis)?'readonly title="تُحتسَب آلياً من بنود المؤشّرات"':'' ?>></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= (($edit['state']??'مسودة')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>النوع</label><select name="incentive_penalty_type"><?php foreach(['بلا','حافز','جزاء'] as $t): ?><option value="<?= $t ?>" <?= (($edit['incentive_penalty_type']??'بلا')===$t)?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>المبلغ (مالي — يدوي)</label><input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($edit['amount'] ?? '') ?>"></div>
            <div class="field" style="grid-column:3/-1;"><label>تعليق مالي (للمالية لاحقاً)</label><input type="text" name="amount_finance_note" value="<?= htmlspecialchars($edit['amount_finance_note'] ?? '') ?>"></div>
            <div class="field"><label>ساعات التشغيل</label><input type="number" step="0.01" name="operating_hours" value="<?= htmlspecialchars($edit['operating_hours'] ?? '') ?>"></div>
            <div class="field"><label>الالتزام بالحضور %</label><input type="number" step="0.01" name="attendance_rate" value="<?= htmlspecialchars($edit['attendance_rate'] ?? '') ?>"></div>
            <div class="field"><label>الإنتاجية</label><input type="number" step="0.01" name="productivity" value="<?= htmlspecialchars($edit['productivity'] ?? '') ?>"></div>
            <div class="field"><label>أعطال سوء التشغيل</label><input type="number" name="misuse_faults" value="<?= htmlspecialchars($edit['misuse_faults'] ?? '') ?>"></div>
            <div class="field"><label>استهلاك الوقود</label><input type="number" step="0.01" name="fuel_consumption" value="<?= htmlspecialchars($edit['fuel_consumption'] ?? '') ?>"></div>
            <div class="field"><label>التزام السلامة</label><input type="number" step="0.01" name="safety_score" value="<?= htmlspecialchars($edit['safety_score'] ?? '') ?>"></div>
            <div class="field" style="grid-column:1/-1;"><label>ملاحظات</label><input type="text" name="notes" value="<?= htmlspecialchars($edit['notes'] ?? '') ?>"></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button><?php if($edit): ?><a href="worker_evaluation.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a><?php endif; ?></div>
    </form>

    <?php if ($edit):
        $computed = ems_eval_weighted_score($conn, intval($edit['id']));
        $sumW=0; foreach($kpis as $k){ $sumW += floatval($k['weight']); } ?>
    <div class="allforms" style="display:block;">
        <div class="card-header"><h5><i class="fas fa-list-check"></i> بنود مؤشّرات الأداء (KPI) — الدرجة الموزونة المحسوبة:
            <strong><?= $computed!==null ? number_format($computed,2) : '—' ?></strong>
            <?php if($sumW>0): ?><span style="font-weight:400;color:#888;">(مجموع الأوزان: <?= number_format($sumW,2) ?>)</span><?php endif; ?>
        </h5></div>
        <?php if($can_edit): ?>
        <form action="" method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr 2fr auto;gap:12px;padding:14px;align-items:flex-end;">
            <input type="hidden" name="action" value="add_kpi"><input type="hidden" name="evaluation_id" value="<?= intval($edit['id']) ?>">
            <div class="field"><label>اسم المؤشّر</label><input type="text" name="kpi_name" required></div>
            <div class="field"><label>الوزن</label><input type="number" step="0.01" name="weight" placeholder="مثال 30"></div>
            <div class="field"><label>الدرجة</label><input type="number" step="0.01" name="kpi_score" placeholder="0-100"></div>
            <div class="field"><label>ملاحظة</label><input type="text" name="kpi_note"></div>
            <div class="field"><button type="submit" class="add-btn"><i class="fas fa-plus"></i> إضافة بند</button></div>
        </form>
        <?php endif; ?>
        <table class="data-table" style="width:100%;"><thead><tr><th>المؤشّر</th><th>الوزن</th><th>الدرجة</th><th>ملاحظة</th><th></th></tr></thead><tbody>
        <?php if(empty($kpis)): ?><tr><td colspan="5" style="text-align:center;color:#888;">لا بنود — الدرجة تبقى المُدخَلة يدوياً.</td></tr>
        <?php else: foreach($kpis as $k): ?>
            <tr><td><strong><?= htmlspecialchars($k['kpi_name']) ?></strong></td><td><?= htmlspecialchars($k['weight'] ?? '-') ?></td><td><?= htmlspecialchars($k['score'] ?? '-') ?></td><td><?= htmlspecialchars($k['notes'] ?: '-') ?></td>
            <td><?php if($can_delete): ?><a href="worker_evaluation.php?edit=<?= intval($edit['id']) ?>&del_kpi=<?= intval($k['id']) ?>" class="action-btn delete" onclick="return confirm('حذف البند؟')"><i class="fas fa-trash"></i></a><?php endif; ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table>
    </div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-top:14px;"><table class="data-table" style="width:100%;">
        <thead><tr><th>إجراءات</th><th>#</th><th>الموظف</th><th>الفترة</th><th>الدرجة</th><th>النوع</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody>
        <?php $list=mysqli_query($conn,"SELECT ev.*, e.name AS wname FROM worker_evaluation ev
            LEFT JOIN employees e ON e.id=ev.employee_id
            WHERE 1=1 $scope_sql ORDER BY ev.id DESC");
        $i=1; $WF_VIEW = []; if($list){ while($r=mysqli_fetch_assoc($list)): $i++;
            $sc=($r['state']==='مرحّل')?'status-active':(($r['state']==='معتمد')?'status-warning':'status-inactive');
            $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل التقييم', 'fas fa-star-half-stroke', [
                ems_wf_field('الموظف', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                ems_wf_field('الفترة', $r['period'] ?: '-', 'fas fa-calendar'),
                ems_wf_field('الدرجة', $r['score'] !== null ? $r['score'] : '-', 'fas fa-star'),
                ems_wf_field('النوع', $r['incentive_penalty_type'], 'fas fa-scale-balanced'),
                ems_wf_field('المبلغ', $r['amount'] !== null ? $r['amount'] : '-', 'fas fa-money-bill'),
                ems_wf_field('تعليق مالي', $r['amount_finance_note'] ?: '-', 'fas fa-comment-dollar', ['size' => 'lg']),
                ems_wf_field('ساعات التشغيل', $r['operating_hours'] !== null ? $r['operating_hours'] : '-', 'fas fa-clock'),
                ems_wf_field('الالتزام بالحضور %', $r['attendance_rate'] !== null ? $r['attendance_rate'] : '-', 'fas fa-user-check'),
                ems_wf_field('الإنتاجية', $r['productivity'] !== null ? $r['productivity'] : '-', 'fas fa-gauge-high'),
                ems_wf_field('أعطال سوء التشغيل', $r['misuse_faults'] !== null ? $r['misuse_faults'] : '-', 'fas fa-triangle-exclamation'),
                ems_wf_field('استهلاك الوقود', $r['fuel_consumption'] !== null ? $r['fuel_consumption'] : '-', 'fas fa-gas-pump'),
                ems_wf_field('التزام السلامة', $r['safety_score'] !== null ? $r['safety_score'] : '-', 'fas fa-helmet-safety'),
                ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                ems_wf_field('ملاحظات', $r['notes'] ?: '-', 'fas fa-align-right', ['size' => 'full']),
            ]); ?>
            <tr><td><div class="action-btns" style="gap:4px;align-items:center;">
                <?= ems_wf_view_button($r['id']) ?>
                <?php if($can_edit): ?><a href="worker_evaluation.php?edit=<?= intval($r['id']) ?>" class="action-btn edit" title="تعديل + بنود المؤشّرات"><i class="fas fa-edit"></i></a><?php endif; ?>
                <form action="" method="post" style="display:inline;"><input type="hidden" name="action" value="set_state"><input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                    <select name="new_state" onchange="this.form.submit()" <?= $can_edit?'':'disabled' ?> style="padding:2px;"><?php foreach($STATES as $s): ?><option value="<?= $s ?>" <?= ($r['state']===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
                </form>
                <?php if($can_delete): ?><a href="worker_evaluation.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div></td>
            <td><?= intval($r['id']) ?></td><td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
            <td><?= htmlspecialchars($r['period'] ?: '-') ?></td><td><?= htmlspecialchars($r['score'] ?: '-') ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($r['incentive_penalty_type']) ?></span></td>
            <td><?= htmlspecialchars($r['amount'] ?: '-') ?></td>
            <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td></tr>
        <?php endwhile; } if(!$list||$i===1): ?><tr><td colspan="8" style="text-align:center;color:#888;padding:18px;">لا توجد تقييماتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('eForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
