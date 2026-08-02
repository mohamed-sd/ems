<?php
/** EQUIP-OPE-S04 — 8.5 التقييم والحوافز والجزاءات (+ بنود مؤشّرات الأداء KPI). Bolt-on.
 *  المالية: يدوي + تعليق (قرار 5). الدرجة الإجمالية تُحتسَب آلياً من بنود المؤشّرات (متوسطٌ موزون). */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
// العزل عبر بوابة المستأجر — والسوبر يمرّ عبر forAllTenants المسجَّل (سلوك الأصل: بلا تنطيق).
$ev_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('worker evaluation super') : ems_tenant_db();
$STATES=['مسودة','معتمد','مرحّل'];

// الدرجة الموزونة من بنود المؤشّرات: Σ(وزن×درجة)/Σ(وزن). إن انعدمت الأوزان فمتوسطٌ بسيط. null إن لا بنود.
// (البنود ابن T_CHILD — البوابة تعزلها عبر التقييم الأب المملوك)
function ems_eval_weighted_score($g, $eval_id) {
    $eval_id=(int)$eval_id; if ($eval_id<=0) return null;
    try { $rows = $g->select('worker_evaluation_kpi', array('columns'=>array('weight','score'), 'where'=>array('evaluation_id'=>$eval_id))); }
    catch (\Throwable $t) { return null; }
    $sumW=0.0; $sumWS=0.0; $sumS=0.0; $n=0;
    foreach ($rows as $l) {
        if ($l['score']===null || $l['score']==='') continue;
        $w=floatval($l['weight']); $s=floatval($l['score']);
        $n++; $sumS+=$s; $sumW+=$w; $sumWS+=$w*$s;
    }
    if ($n===0) return null;
    return ($sumW>0) ? round($sumWS/$sumW,2) : round($sumS/$n,2);
}
// يعيد احتساب الدرجة من البنود ويخزّنها في worker_evaluation (إن وُجدت بنود).
function ems_eval_recompute_store($g,$eval_id) {
    $ws = ems_eval_weighted_score($g,$eval_id);
    if ($ws!==null) {
        try { $g->update('worker_evaluation', array('score' => $ws), array('id' => intval($eval_id))); }
        catch (\Throwable $t) { error_log('worker_evaluation.php recompute: ' . $t->getMessage()); }
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
        $nid=0;
        try {
            // company_id تحقنه البوابة من سياق الجلسة، وتعيد المعرّف الوليد
            $nid = intval($ev_gate->insert('worker_evaluation', array(
                'employee_id' => $worker_id, 'period' => $period, 'score' => $score,
                'incentive_penalty_type' => $ip_type, 'amount' => $amount, 'amount_finance_note' => $amt_note,
                'operating_hours' => $op_hours, 'attendance_rate' => $att, 'productivity' => $prod,
                'misuse_faults' => $misuse, 'fuel_consumption' => $fuel, 'safety_score' => $safety,
                'state' => $state, 'notes' => $notes, 'created_by' => $user_id)));
        } catch (\Throwable $t) { error_log('worker_evaluation.php insert: ' . $t->getMessage()); }
        // فتح وضع التعديل ليظهر لوح بنود المؤشّرات.
        header("Location: worker_evaluation.php?edit=".$nid."&msg=✅+تم+الحفظ"); exit();
    } else {
        try {
            $ev_gate->update('worker_evaluation', array(
                'period' => $period, 'score' => $score, 'incentive_penalty_type' => $ip_type,
                'amount' => $amount, 'amount_finance_note' => $amt_note, 'operating_hours' => $op_hours,
                'attendance_rate' => $att, 'productivity' => $prod, 'misuse_faults' => $misuse,
                'fuel_consumption' => $fuel, 'safety_score' => $safety, 'state' => $state,
                'notes' => $notes), array('id' => $id));
        } catch (\Throwable $t) { error_log('worker_evaluation.php update: ' . $t->getMessage()); }
        // إن وُجدت بنود مؤشّرات، تتقدّم الدرجة المحسوبة على المدخلة يدوياً.
        ems_eval_recompute_store($ev_gate,$id);
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
    $own=0;
    try { $own = ($ev_gate->selectOne('worker_evaluation', array('columns'=>array('id'), 'where'=>array('id'=>$eid))) !== null) ? 1 : 0; }
    catch (\Throwable $t) { $own=0; error_log('worker_evaluation.php add_kpi own: ' . $t->getMessage()); }
    if ($eid>0 && $own>0 && $kpi_name!=='') {
        try {
            // ابن T_CHILD: البوابة تتحقق من ملكية الأب (evaluation_id) قبل الإدراج
            $ev_gate->insert('worker_evaluation_kpi', array(
                'evaluation_id' => $eid, 'kpi_name' => $kpi_name, 'weight' => $weight,
                'score' => $kscore, 'notes' => $knote));
        } catch (\Throwable $t) { error_log('worker_evaluation.php add_kpi: ' . $t->getMessage()); }
        ems_eval_recompute_store($ev_gate,$eid);
    }
    header("Location: worker_evaluation.php?edit=".$eid."&msg=✅+تم+حفظ+البند"); exit();
}
if (($_GET['del_kpi']??'')!=='' && $can_delete) {
    $kid=intval($_GET['del_kpi']); $eid=intval($_GET['edit']??0);
    // احذف البند فقط إن كان تقييمه ضمن نطاق الشركة: يُستحضَر البند عبر البوابة (المعزول
    // بالأب) ثم يُحذف بقيد الأب+الملكية (deleteChild).
    try {
        $ev_kline = $ev_gate->selectOne('worker_evaluation_kpi', array('where' => array('id' => $kid)));
        if ($ev_kline !== null) {
            $ev_gate->deleteChild('worker_evaluation_kpi', $kid,
                'worker_evaluation', intval($ev_kline['evaluation_id']), 'evaluation_id', 'evaluation kpi delete');
            if ($eid>0) ems_eval_recompute_store($ev_gate,$eid);
        }
    } catch (\Throwable $t) { error_log('worker_evaluation.php del_kpi: ' . $t->getMessage()); }
    header("Location: worker_evaluation.php?edit=".$eid."&msg=✅+تم+حذف+البند"); exit();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='set_state' && $can_edit) {
    $id=intval($_POST['id']??0); $ns=trim($_POST['new_state']??'');
    if ($id>0 && in_array($ns,$STATES,true)) {
        try { $ev_gate->update('worker_evaluation', array('state' => $ns), array('id' => $id)); }
        catch (\Throwable $t) { error_log('worker_evaluation.php set_state: ' . $t->getMessage()); }
    }
    header("Location: worker_evaluation.php?msg=✅+تم+تحديث+الحالة"); exit();
}
if (($_GET['delete']??'')!=='' && $can_delete) { $d=intval($_GET['delete']);
    try { $ev_gate->deleteRow('worker_evaluation', $d, 'evaluation delete'); }
    catch (\Throwable $t) { error_log('worker_evaluation.php delete: ' . $t->getMessage()); }
    header("Location: worker_evaluation.php?msg=✅+تم+الحذف"); exit(); }

// ── تحميل تقييمٍ للتعديل + بنوده ──────────────────────────────────────────────────
$edit=null; $kpis=[]; $edit_id=intval($_GET['edit']??0);
if ($edit_id>0) {
    try {
        $ev_edit_rows = $ev_gate->scopedQuery(array('scope'=>array('ev'=>'worker_evaluation'), 'enrich'=>array('e'=>'employees')),
            "SELECT ev.*, e.name AS wname FROM worker_evaluation ev LEFT JOIN employees e ON e.id=ev.employee_id WHERE ev.id=? AND {TENANT_SCOPE} LIMIT 1",
            array($edit_id));
        $edit = !empty($ev_edit_rows) ? $ev_edit_rows[0] : null;
        if ($edit) { $kpis = $ev_gate->select('worker_evaluation_kpi', array('where' => array('evaluation_id' => $edit_id), 'orderBy' => 'id')); }
    } catch (\Throwable $t) { error_log('worker_evaluation.php edit: ' . $t->getMessage()); }
}

$workers=[];
try {
    $ev_workers = $ev_gate->scopedQuery(array('scope'=>array('wp'=>'employees')),
        "SELECT wp.id,wp.name AS name FROM employees wp WHERE 1=1 AND {TENANT_SCOPE} ORDER BY wp.name");
    foreach($ev_workers as $w){$workers[$w['id']]=$w['name'];}
} catch (\Throwable $t) { error_log('worker_evaluation.php workers: ' . $t->getMessage()); }

$page_title="إيكوبيشن | تقييم الأداء"; include '../inheader.php'; include '../insidebar.php';
?>
<div class="main">
    <?php $header_title='تقييم الأداء'; $header_icon='fas fa-star-half-stroke'; $header_actions=array();
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
        $computed = ems_eval_weighted_score($ev_gate, intval($edit['id']));
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
        <?php $list = array();
        try {
            $list = $ev_gate->scopedQuery(array('scope'=>array('ev'=>'worker_evaluation'), 'enrich'=>array('e'=>'employees')),
                "SELECT ev.*, e.name AS wname FROM worker_evaluation ev
            LEFT JOIN employees e ON e.id=ev.employee_id
            WHERE 1=1 AND {TENANT_SCOPE} ORDER BY ev.id DESC");
        } catch (\Throwable $t) { error_log('worker_evaluation.php list: ' . $t->getMessage()); }
        $i=1; $WF_VIEW = []; if($list){ foreach($list as $r): $i++;
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
        <?php endforeach; } if(!$list||$i===1): ?><tr><td colspan="8" style="text-align:center;color:#888;padding:18px;">لا توجد تقييماتٌ بعد.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>(function(){var b=document.getElementById('toggleForm'),f=document.getElementById('eForm');if(b&&f)b.addEventListener('click',function(){f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none';});})();</script>
</body></html>
