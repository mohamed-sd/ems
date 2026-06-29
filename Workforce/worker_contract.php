<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — 8.3 عقد العامل التشغيلي.
 * Bolt-on مستقلٌّ عن drivercontracts (قرار 1). كتابةٌ بـ Prepared Statements.
 * المالية: إدخالٌ يدويٌّ + تعليقاتٌ مرجعية (قرار 5).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Workforce/RotationService.php';
require_once __DIR__ . '/../app/Services/Workforce/ViewModal.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=بيئة+شركة+غير+صالحة+❌"); exit(); }

$page_permissions = check_page_permissions($conn, 'Workforce/worker_contract.php');
$can_view = $page_permissions['can_view']; $can_add = $page_permissions['can_add'];
$can_edit = $page_permissions['can_edit']; $can_delete = $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+العقود+❌"); exit(); }

$scope_sql = $is_super_admin ? "" : " AND wc.company_id = " . intval($company_id) . " ";

$CONTRACT_TYPES = ['سنوي','غير محدّد','مشروع','موسمي','مؤقت','بالساعة','بالإنتاج','استشاري/إشرافي','احتياطي','تغطية مؤقتة','تجاري مؤقت'];
$WAGE_METHODS   = ['شهري','بالساعة','بالوردية/اليوم','بالإنتاج','مقطوع'];
$ROTATIONS      = ['بلا','شهران+شهر','ثلاثة أشهر+15 يوم','مخصّص'];
$STATES         = ['مسودة','نافذ','منتهٍ'];

// ── معالجة الإرسال ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_contract') {
    $id = intval($_POST['id'] ?? 0); $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: worker_contract.php?msg=لا+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add)  { header("Location: worker_contract.php?msg=لا+صلاحية+إضافة+❌"); exit(); }

    $worker_id     = intval($_POST['worker_id'] ?? 0);
    $code          = trim($_POST['code'] ?? '');
    $contract_type = trim($_POST['contract_type'] ?? 'مشروع');
    $wage          = $_POST['wage'] !== '' ? floatval($_POST['wage']) : null;
    $wage_note     = trim($_POST['wage_finance_note'] ?? '');
    $wage_method   = trim($_POST['wage_method'] ?? 'شهري');
    $date_start    = !empty($_POST['date_start']) ? $_POST['date_start'] : null;
    $date_end      = !empty($_POST['date_end']) ? $_POST['date_end'] : null;
    $state         = trim($_POST['state'] ?? 'مسودة');
    $rotation      = trim($_POST['rotation_pattern'] ?? 'بلا');
    $work_days     = $_POST['work_days'] !== '' ? intval($_POST['work_days']) : null;
    $leave_days    = $_POST['leave_days'] !== '' ? intval($_POST['leave_days']) : null;
    // محرّك التناوب: يُحترَم الإدخال اليدوي، وإلّا يُحتسَب الاستحقاق آلياً من البداية وأيام العمل.
    $next_rot      = ems_rotation_resolve_next_for_save(
                        $_POST['next_rotation_date'] ?? '', $date_start, $rotation, $work_days, $leave_days
                     );
    $mhb           = $_POST['monthly_hours_base'] !== '' ? intval($_POST['monthly_hours_base']) : null;
    $fwr           = $_POST['fixed_wage_ratio'] !== '' ? floatval($_POST['fixed_wage_ratio']) : null;
    $bdt           = trim($_POST['billable_downtime'] ?? '');
    $a_house       = $_POST['allow_housing'] !== '' ? floatval($_POST['allow_housing']) : null;
    $a_food        = $_POST['allow_food'] !== '' ? floatval($_POST['allow_food']) : null;
    $a_site        = $_POST['allow_site'] !== '' ? floatval($_POST['allow_site']) : null;
    $a_trans       = $_POST['allow_transport'] !== '' ? floatval($_POST['allow_transport']) : null;
    $a_note        = trim($_POST['allow_finance_note'] ?? '');
    $term          = trim($_POST['termination_terms'] ?? '');

    if (!in_array($contract_type, $CONTRACT_TYPES, true)) $contract_type = 'مشروع';
    $bdtv = $bdt !== '' ? $bdt : null;

    if (!$is_editing) {
        if ($worker_id <= 0) { header("Location: worker_contract.php?msg=يجب+اختيار+عامل+❌"); exit(); }
        $cid = $is_super_admin ? null : $company_id;
        $sql = "INSERT INTO worker_contract
                (company_id, employee_id, code, contract_type, wage, wage_finance_note, wage_method, date_start, date_end,
                 state, rotation_pattern, work_days, leave_days, next_rotation_date, monthly_hours_base, fixed_wage_ratio,
                 billable_downtime, allow_housing, allow_food, allow_site, allow_transport, allow_finance_note, termination_terms, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        // types: company i, worker i, code s, type s, wage d, wagenote s, method s, dstart s, dend s,
        //        state s, rot s, workdays i, leavedays i, nextrot s, mhb i, fwr d, bdt s,
        //        house d, food d, site d, trans d, anote s, term s, createdby i
        $ok = false;
        if($stmt){ $stmt->bind_param(
            'iissdssssssiisidsddddssi',
            $cid, $worker_id, $code, $contract_type, $wage, $wage_note, $wage_method, $date_start, $date_end,
            $state, $rotation, $work_days, $leave_days, $next_rot, $mhb, $fwr, $bdtv,
            $a_house, $a_food, $a_site, $a_trans, $a_note, $term, $user_id
        );
        $ok = $stmt->execute(); $stmt->close(); }
        header("Location: worker_contract.php?msg=" . ($ok ? "✅+تم+حفظ+العقد" : "❌+تعذّر+الحفظ"));
        exit();
    } else {
        $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
        $sql = "UPDATE worker_contract SET
                  code=?, contract_type=?, wage=?, wage_finance_note=?, wage_method=?, date_start=?, date_end=?,
                  state=?, rotation_pattern=?, work_days=?, leave_days=?, next_rotation_date=?, monthly_hours_base=?,
                  fixed_wage_ratio=?, billable_downtime=?, allow_housing=?, allow_food=?, allow_site=?, allow_transport=?,
                  allow_finance_note=?, termination_terms=?
                WHERE id=? $scope";
        $stmt = $conn->prepare($sql);
        // types: code s, type s, wage d, wnote s, method s, dstart s, dend s, state s, rot s,
        //        workdays i, leavedays i, nextrot s, mhb i, fwr d, bdt s, house d, food d, site d, trans d, anote s, term s, id i
        $ok = false;
        if($stmt){ $stmt->bind_param(
            'ssdssssssiisidsddddssi',
            $code, $contract_type, $wage, $wage_note, $wage_method, $date_start, $date_end, $state, $rotation,
            $work_days, $leave_days, $next_rot, $mhb, $fwr, $bdtv, $a_house, $a_food, $a_site, $a_trans,
            $a_note, $term, $id
        );
        $ok = $stmt->execute(); $stmt->close(); }
        header("Location: worker_contract.php?edit=" . $id . "&msg=" . ($ok ? "✅+تم+التحديث" : "❌+تعذّر+التحديث"));
        exit();
    }
}

if (($_GET['delete'] ?? '') !== '' && $can_delete) {
    $del = intval($_GET['delete']);
    $scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id);
    $stmt = $conn->prepare("DELETE FROM worker_contract WHERE id = ? $scope");
    $stmt->bind_param('i', $del); $stmt->execute(); $stmt->close();
    header("Location: worker_contract.php?msg=✅+تم+الحذف"); exit();
}

// ── تحميل عقدٍ للتعديل ─────────────────────────────────────────────────────────
$edit = null; $edit_id = intval($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $sc = $is_super_admin ? "" : " AND wc.company_id = " . intval($company_id);
    $stmt = $conn->prepare("SELECT wc.* FROM worker_contract wc WHERE wc.id = ? $sc LIMIT 1");
    $stmt->bind_param('i', $edit_id); $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

// قائمة العمال للاختيار
$workers = [];
$wq = mysqli_query($conn, "SELECT wp.id, wp.name AS name FROM employees wp WHERE 1=1" . ($is_super_admin ? "" : " AND wp.company_id = " . intval($company_id)) . " ORDER BY wp.name");
if ($wq) { while ($w = mysqli_fetch_assoc($wq)) { $workers[$w['id']] = $w['name']; } }

$page_title = "إيكوبيشن | عقود العاملين";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main">
    <?php
    $header_title = 'عقود العاملين'; $header_icon = 'fas fa-file-signature'; $header_actions = array();
    if ($can_add) $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'عقد جديد');
    $header_back = array('href' => 'worker_register.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل العامل');
    include('../includes/page_header.php');
    ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'],'✅')!==false; ?>
        <div class="success-message <?= $ok?'is-success':'is-error' ?>"><i class="fas <?= $ok?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <form id="cForm" action="" method="post" class="allforms" style="<?= $edit ? '' : 'display:none;' ?>">
        <input type="hidden" name="action" value="save_contract">
        <input type="hidden" name="id" value="<?= $edit ? intval($edit['id']) : 0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit ? 'تعديل عقد' : 'عقد عاملٍ جديد' ?></h5></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:14px;">
            <div class="field"><label>الموظف</label>
                <?php if ($edit): ?><input type="text" value="<?= htmlspecialchars($workers[$edit['employee_id']] ?? ('#'.$edit['employee_id'])) ?>" disabled>
                <?php else: ?><select name="worker_id" required><option value="">— اختر —</option>
                    <?php foreach ($workers as $wid=>$wn): ?><option value="<?= intval($wid) ?>"><?= htmlspecialchars($wn) ?></option><?php endforeach; ?></select><?php endif; ?>
            </div>
            <div class="field"><label>كود العقد</label><input type="text" name="code" value="<?= htmlspecialchars($edit['code'] ?? '') ?>"></div>
            <div class="field"><label>نوع العقد (11)</label><select name="contract_type"><?php foreach ($CONTRACT_TYPES as $t): ?><option value="<?= $t ?>" <?= (($edit['contract_type']??'')===$t)?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الحالة</label><select name="state"><?php foreach ($STATES as $s): ?><option value="<?= $s ?>" <?= (($edit['state']??'مسودة')===$s)?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>الأجر (مالي — يدوي)</label><input type="number" step="0.01" name="wage" value="<?= htmlspecialchars($edit['wage'] ?? '') ?>"></div>
            <div class="field"><label>تعليق مالي (للمالية لاحقاً)</label><input type="text" name="wage_finance_note" value="<?= htmlspecialchars($edit['wage_finance_note'] ?? '') ?>"></div>
            <div class="field"><label>طريقة الأجر</label><select name="wage_method"><?php foreach ($WAGE_METHODS as $m): ?><option value="<?= $m ?>" <?= (($edit['wage_method']??'شهري')===$m)?'selected':'' ?>><?= $m ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>نسبة الأجر الثابت %</label><input type="number" step="0.01" name="fixed_wage_ratio" value="<?= htmlspecialchars($edit['fixed_wage_ratio'] ?? '') ?>"></div>

            <div class="field"><label>بداية</label><input type="date" name="date_start" value="<?= htmlspecialchars($edit['date_start'] ?? '') ?>"></div>
            <div class="field"><label>نهاية</label><input type="date" name="date_end" value="<?= htmlspecialchars($edit['date_end'] ?? '') ?>"></div>
            <div class="field"><label>نمط التناوب</label><select name="rotation_pattern"><?php foreach ($ROTATIONS as $r): ?><option value="<?= $r ?>" <?= (($edit['rotation_pattern']??'بلا')===$r)?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>الاستحقاق القادم</label><input type="date" name="next_rotation_date" value="<?= htmlspecialchars($edit['next_rotation_date'] ?? '') ?>"></div>

            <div class="field"><label>أيام العمل</label><input type="number" name="work_days" value="<?= htmlspecialchars($edit['work_days'] ?? '') ?>"></div>
            <div class="field"><label>أيام الإجازة</label><input type="number" name="leave_days" value="<?= htmlspecialchars($edit['leave_days'] ?? '') ?>"></div>
            <div class="field"><label>الساعات الشهرية المعيارية</label><input type="number" name="monthly_hours_base" value="<?= htmlspecialchars($edit['monthly_hours_base'] ?? '') ?>"></div>
            <div class="field"><label>معاملة التوقّف</label><select name="billable_downtime"><option value="">—</option><?php foreach (['استعداد العميل','+ عطل الصيانة','حسب الحدث'] as $b): ?><option value="<?= $b ?>" <?= (($edit['billable_downtime']??'')===$b)?'selected':'' ?>><?= $b ?></option><?php endforeach; ?></select></div>

            <div class="field"><label>بدل سكن</label><input type="number" step="0.01" name="allow_housing" value="<?= htmlspecialchars($edit['allow_housing'] ?? '') ?>"></div>
            <div class="field"><label>بدل إعاشة</label><input type="number" step="0.01" name="allow_food" value="<?= htmlspecialchars($edit['allow_food'] ?? '') ?>"></div>
            <div class="field"><label>بدل موقع</label><input type="number" step="0.01" name="allow_site" value="<?= htmlspecialchars($edit['allow_site'] ?? '') ?>"></div>
            <div class="field"><label>بدل نقل</label><input type="number" step="0.01" name="allow_transport" value="<?= htmlspecialchars($edit['allow_transport'] ?? '') ?>"></div>

            <div class="field" style="grid-column:1/3;"><label>تعليق البدلات (للمالية لاحقاً)</label><input type="text" name="allow_finance_note" value="<?= htmlspecialchars($edit['allow_finance_note'] ?? '') ?>"></div>
            <div class="field" style="grid-column:3/-1;"><label>شروط الإنهاء</label><input type="text" name="termination_terms" value="<?= htmlspecialchars($edit['termination_terms'] ?? '') ?>"></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="worker_contract.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>إجراءات</th><th>#</th><th>الكود</th><th>الموظف</th><th>النوع</th><th>طريقة الأجر</th><th>التناوب</th><th>بداية</th><th>نهاية</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php
            $list = mysqli_query($conn, "SELECT wc.*, e.name AS wname FROM worker_contract wc
                    LEFT JOIN employees e ON e.id = wc.employee_id
                    WHERE 1=1 $scope_sql ORDER BY wc.id DESC");
            $i=1; $WF_VIEW = []; if ($list) { while ($r = mysqli_fetch_assoc($list)):
                $sc = ($r['state']==='نافذ')?'status-active':(($r['state']==='منتهٍ')?'status-inactive':'status-warning');
                $WF_VIEW[$r['id']] = ems_wf_view_payload('تفاصيل عقد العامل', 'fas fa-file-signature', [
                    ems_wf_field('الكود', $r['code'] ?: ('C-' . $r['id']), 'fas fa-barcode'),
                    ems_wf_field('الموظف', $r['wname'] ?: '-', 'fas fa-user', ['size' => 'lg']),
                    ems_wf_field('نوع العقد', $r['contract_type'], 'fas fa-file-contract'),
                    ems_wf_field('الحالة', $r['state'], 'fas fa-flag', ['type' => 'status']),
                    ems_wf_field('الأجر', $r['wage'] !== null ? $r['wage'] : '-', 'fas fa-money-bill'),
                    ems_wf_field('طريقة الأجر', $r['wage_method'], 'fas fa-coins'),
                    ems_wf_field('نسبة الأجر الثابت %', $r['fixed_wage_ratio'] !== null ? $r['fixed_wage_ratio'] : '-', 'fas fa-percent'),
                    ems_wf_field('بداية', $r['date_start'] ?: '-', 'fas fa-calendar-day'),
                    ems_wf_field('نهاية', $r['date_end'] ?: '-', 'fas fa-calendar-xmark'),
                    ems_wf_field('نمط التناوب', $r['rotation_pattern'], 'fas fa-rotate'),
                    ems_wf_field('أيام العمل', $r['work_days'] !== null ? $r['work_days'] : '-', 'fas fa-briefcase'),
                    ems_wf_field('أيام الإجازة', $r['leave_days'] !== null ? $r['leave_days'] : '-', 'fas fa-umbrella-beach'),
                    ems_wf_field('الاستحقاق القادم للتدوير', $r['next_rotation_date'] ?: '-', 'fas fa-calendar-check'),
                    ems_wf_field('الساعات الشهرية المعيارية', $r['monthly_hours_base'] !== null ? $r['monthly_hours_base'] : '-', 'fas fa-clock'),
                    ems_wf_field('بدل سكن', $r['allow_housing'] !== null ? $r['allow_housing'] : '-', 'fas fa-house'),
                    ems_wf_field('بدل إعاشة', $r['allow_food'] !== null ? $r['allow_food'] : '-', 'fas fa-utensils'),
                    ems_wf_field('بدل موقع', $r['allow_site'] !== null ? $r['allow_site'] : '-', 'fas fa-location-dot'),
                    ems_wf_field('بدل نقل', $r['allow_transport'] !== null ? $r['allow_transport'] : '-', 'fas fa-van-shuttle'),
                    ems_wf_field('شروط الإنهاء', $r['termination_terms'] ?: '-', 'fas fa-file-circle-xmark', ['size' => 'full']),
                ]); ?>
                <tr>
                    <td><div class="action-btns">
                        <?= ems_wf_view_button($r['id']) ?>
                        <?php if ($can_edit): ?><a href="worker_contract.php?edit=<?= intval($r['id']) ?>" class="action-btn edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                        <?php if ($can_delete): ?><a href="worker_contract.php?delete=<?= intval($r['id']) ?>" class="action-btn delete" onclick="return confirm('حذف العقد؟')"><i class="fas fa-trash"></i></a><?php endif; ?>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><code><?= htmlspecialchars($r['code'] ?: ('C-'.$r['id'])) ?></code></td>
                    <td><strong><?= htmlspecialchars($r['wname'] ?: '-') ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($r['contract_type']) ?></span></td>
                    <td><?= htmlspecialchars($r['wage_method']) ?></td>
                    <td><?= htmlspecialchars($r['rotation_pattern']) ?></td>
                    <td><?= htmlspecialchars($r['date_start'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($r['date_end'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $sc ?>"><?= htmlspecialchars($r['state']) ?></span></td>
                </tr>
            <?php endwhile; } if (!$list || $i===1): ?>
                <tr><td colspan="10" style="text-align:center;color:#888;padding:18px;">لا توجد عقودٌ بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php ems_wf_view_modal($WF_VIEW); ?>
<script>
(function(){ var b=document.getElementById('toggleForm'), f=document.getElementById('cForm');
  if(b&&f) b.addEventListener('click',function(){ f.style.display=(f.style.display==='none'||!f.style.display)?'block':'none'; }); })();
</script>
</body>
</html>
