<?php
/**
 * Finance/periods_fin.php — الفترات المالية والإقفال (fin_financial_periods + fin_closing_items) — §3.16/§3.22/§8.
 * دورة الفترة: مخطّطة → مفتوحة → إقفال مرحلي → مقفلة (بعد قائمة الإقفال) → مقفلة نهائياً / إعادة فتح.
 * قاعدة: لا إقفالَ نهائيٌّ قبل إنجاز بنود الإقفال الإلزامية. شاشة مستقلة — عزل شركة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/periods_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الفترات ❌', 'GOV-PERM-403', ''); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$period_states = fin_period_states();
$closing_steps = fin_closing_steps();

// ── انتقالات حالة الفترة ──
if (isset($_GET['action']) && isset($_GET['pid'])) {
    if (!$can_edit) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية الإجراء ❌', 'GOV-PERM-403', ''); exit(); }
    $pid = intval($_GET['pid']); $act = $_GET['action'];
    // انتقالات الحالة عبر البوابة — حراسة الحالة عبر whereRaw، والعزل يُحقن تلقائيًّا.
    $g = fin_gate($is_super_admin);
    $now = date('Y-m-d H:i:s');
    if ($act === 'open') {
        $g->update('fin_financial_periods', array('state'=>'open','posting_allowed'=>1), array('id'=>$pid), "state IN('planned','reopened')");
        ems_gov_flash_redirect('periods_fin.php', 'تم فتح الفترة ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'soft_close') {
        $g->update('fin_financial_periods', array('state'=>'soft_closed','posting_allowed'=>0,'soft_closed_at'=>$now), array('id'=>$pid), "state='open'");
        ems_gov_flash_redirect('periods_fin.php', 'تم الإقفال المرحلي ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'close') {
        // قاعدة الإقفال: كل البنود الإلزامية يجب أن تكون منجَزة
        $n = $g->count('fin_closing_items', array('where'=>array('period_id'=>$pid,'required'=>1,'item_state'=>'pending')));
        if ($n > 0) { header("Location: periods_fin.php?pid=$pid&msg=لا+إقفال:+بنود+إلزامية+غير+منجَزة+($n)+❌"); exit(); }
        // M-39 (SPEC-01 #14): «زرُّ الإقفال يمنع حين يوجد غيرُ مرحَّلٍ أو فرقٌ
        // مفتوح — بقائمة الموانع» — الفحصُ قبل Close لا بعده.
        require_once __DIR__ . '/../includes/period_guard.php';
        $blockers = ems_period_close_blockers($conn, $company_id, $pid);
        if (!empty($blockers)) {
            $bl = array();
            foreach ($blockers as $b) { $bl[] = $b['label'] . ' (' . $b['count'] . ')'; }
            header("Location: periods_fin.php?pid=$pid&msg=" . urlencode('لا إقفال — الموانع: ' . implode(' · ', $bl)) . "+❌"); exit();
        }
        $g->update('fin_financial_periods', array('state'=>'closed','posting_allowed'=>0,'closed_at'=>$now), array('id'=>$pid), "state IN('open','soft_closed')");
        ems_gov_flash_redirect('periods_fin.php', 'تم إقفال الفترة ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'lock') {
        $g->update('fin_financial_periods', array('state'=>'locked','locked_at'=>$now), array('id'=>$pid), "state='closed'");
        ems_gov_flash_redirect('periods_fin.php', 'تم القفل النهائي ✅', 'GOV-OK-200', ''); exit();
    } elseif ($act === 'reopen') {
        // M-39 (SPEC-01 #14): «فتحُ فترةٍ مقفلةٍ قرارٌ أعلى موثَّق» — السببُ
        // إلزامٌ مكتوبٌ لا نصٌّ ثابت، وفاعلُه مختوم (reopened_by).
        $reason = trim((string) ($_GET['reason'] ?? ''));
        if ($reason === '') {
            header("Location: periods_fin.php?pid=$pid&msg=" . urlencode('الفتحُ الاستثنائي يلزمه سببٌ موثَّق — اكتبه في نافذة التأكيد') . "+❌"); exit();
        }
        $g->update('fin_financial_periods', array('state'=>'reopened','posting_allowed'=>1,'reopen_reason'=>mb_substr($reason,0,200),'reopened_by'=>$current_user_id), array('id'=>$pid), "state IN('closed','soft_closed')");
        // N-02: قرارُ الفتح الاستثنائي يدخل سجلَّ التدقيق بسببه
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'journal', 'periods_fin', 'reopen_period', $pid,
            array('posting_allowed' => 0), array('posting_allowed' => 1),
            array('company_id' => $company_id, 'user_id' => $current_user_id, 'note' => $reason));
        ems_gov_flash_redirect('periods_fin.php', 'تم الفتح الاستثنائي ✅', 'GOV-OK-200', ''); exit();
    }
}

// ── إنجاز بند إقفال ──
if (isset($_GET['done_item'])) {
    if (!$can_edit) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $it = intval($_GET['done_item']); $pid = intval($_GET['pid'] ?? 0);
    fin_gate($is_super_admin)->update('fin_closing_items', array('item_state'=>'done','done_by'=>$current_user_id,'done_at'=>date('Y-m-d H:i:s')), array('id'=>$it));
    header("Location: periods_fin.php?pid=$pid&msg=تم+إنجاز+البند+✅"); exit();
}

// ── إنشاء فترة + بنود إقفالها ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fiscal_year'])) {
    if (!$can_add) { ems_gov_flash_redirect('periods_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $fy = intval($_POST['fiscal_year'] ?? 0);
    $ptype = ($_POST['period_type'] ?? '') === 'year' ? 'year' : 'month';
    $pno = $ptype === 'month' ? max(1, min(12, intval($_POST['period_no'] ?? 1))) : null;
    if ($fy < 2000) { ems_gov_flash_redirect('periods_fin.php', 'سنة غير صحيحة ❌', 'GOV-REF-404', ''); exit(); }
    if ($ptype === 'year') { $start = "$fy-01-01"; $end = "$fy-12-31"; }
    else { $start = date('Y-m-01', mktime(0, 0, 0, $pno, 1, $fy)); $end = date('Y-m-t', mktime(0, 0, 0, $pno, 1, $fy)); }

    // إنشاء الفترة + بذر بنود إقفالها زوجٌ مترابط → معاملة ذرّية عبر §9؛ العزل يُحقن تلقائيًّا.
    $pid = 0;
    try {
        $pid = fin_gate($is_super_admin)->runInTransaction(function ($gate) use ($fy, $ptype, $pno, $start, $end, $current_user_id, $closing_steps) {
            $newId = $gate->insert('fin_financial_periods', array(
                'fiscal_year' => $fy,
                'period_type' => $ptype,
                'period_no'   => $pno,
                'start_date'  => $start,
                'end_date'    => $end,
                'state'       => 'planned',
                'created_by'  => $current_user_id,
            ));
            foreach (array_keys($closing_steps) as $step) {
                $gate->insert('fin_closing_items', array(
                    'period_id' => $newId,
                    'step'      => $step,
                    'required'  => 1,
                ));
            }
            return $newId;
        }, 'periods: create period + seed closing items');
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            ems_gov_flash_redirect('periods_fin.php', 'الفترة موجودة مسبقاً ❌', 'GOV-INFO-200', ''); exit();
        }
        throw $e;
    }
    header("Location: periods_fin.php?pid=$pid&msg=تم+إنشاء+الفترة+وقائمة+إقفالها+✅"); exit();
}

// الفترة المختارة لعرض قائمة الإقفال
$sel_pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;

$page_title = 'إيكوبيشن | إقفال الفترات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-periods-main ems-unified-page-shell">
    <?php
    $header_title = 'إقفال الفترات'; $header_icon = 'fa fa-calendar-check';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء فترة'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء فترة مالية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>السنة المالية <span class="required">*</span></label><input type="number" name="fiscal_year" required value="<?php echo date('Y'); ?>"></div>
            <div class="form-group"><label>النوع</label><select name="period_type" id="pt"><option value="month">شهر</option><option value="year">سنة</option></select></div>
            <div class="form-group" id="pnowrap"><label>رقم الشهر</label><input type="number" name="period_no" min="1" max="12" value="1"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> إنشاء</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-calendar"></i> الفترات المالية</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>السنة</th><th>النوع</th><th>القيود المنشورة</th><th>إلى</th><th>القيد مسموح</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإقفال</th>
              <th class="ems-fn-th" data-fn="1">الوحدات المعتمدة</th>
              <th class="ems-fn-th" data-fn="1">الوحدات المعلَّقة</th>
              <th class="ems-fn-th" data-fn="1">المستخلصات المُصدَرة</th>
              <th class="ems-fn-th" data-fn="1">الفواتير المُصدَرة</th>
              <th class="ems-fn-th" data-fn="1">المسيّرات المعتمدة</th>
              <th class="ems-fn-th" data-fn="1">تسويات الموردين</th>
              <th class="ems-fn-th" data-fn="1">المطابقات البنكية</th>
              <th class="ems-fn-th" data-fn="1">بنود لم تُحسم</th>
              <th class="ems-fn-th" data-fn="1">شرط الإقفال</th>
              <th class="ems-fn-th" data-fn="1">أقفله</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr></thead>
                <tbody>
                <?php
                $period_rows = fin_gate($is_super_admin)->select('fin_financial_periods', array('orderBy' => 'fiscal_year DESC, period_type ASC, period_no ASC'));
                foreach ($period_rows as $row) {
                    $st = (string)$row['state']; $id = intval($row['id']);
                    $tone = in_array($st, array('open','reopened')) ? 'success' : ($st === 'planned' ? 'secondary' : ($st === 'locked' ? 'dark' : 'primary'));
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_edit) {
                        if (in_array($st, array('planned','reopened'))) echo "<a href='?action=open&pid=$id' class='action-btn edit' title='فتح'><i class='fas fa-lock-open'></i></a>";
                        if ($st === 'open') echo "<a href='?action=soft_close&pid=$id' class='action-btn edit' title='إقفال مرحلي'><i class='fas fa-hourglass-half'></i></a>";
                        if (in_array($st, array('open','soft_closed'))) echo "<a href='?action=close&pid=$id' class='action-btn edit' title='إقفال نهائي' onclick='return confirm(\"إقفال الفترة؟ يتطلب إنجاز بنود الإقفال.\")'><i class='fas fa-flag-checkered'></i></a>";
                        if ($st === 'closed') echo "<a href='?action=lock&pid=$id' class='action-btn delete' title='قفل نهائي' onclick='return confirm(\"قفل نهائي؟ يمنع أي تعديل.\")'><i class='fas fa-lock'></i></a>";
                        if (in_array($st, array('closed','soft_closed'))) echo "<a href='javascript:void(0)' class='action-btn edit' title='فتح استثنائي' onclick='emsReopenPeriod($id)'><i class='fas fa-rotate-left'></i></a>";
                    }
                    echo "<a href='?pid=$id' class='action-btn' title='قائمة الإقفال'><i class='fas fa-list-check'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . intval($row['fiscal_year']) . "</td>";
                    echo "<td>" . ($row['period_type'] === 'year' ? 'سنة' : 'شهر ' . intval($row['period_no'])) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['start_date']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['end_date']) . "</td>";
                    echo "<td>" . ($row['posting_allowed'] ? "<span class='badge badge-success'>نعم</span>" : "<span class='badge badge-secondary'>لا</span>") . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($period_states[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ($sel_pid > 0): ?>
        <h5 style="margin:18px 0 10px"><i class="fas fa-list-check"></i> قائمة إقفال الفترة #<?php echo $sel_pid; ?></h5>
        <div class="table-container">
            <table class="alltables" style="width:100%;">
                <thead><tr><th>البند</th><th>إلزامي</th><th>الحالة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php
                $done = 0; $total = 0;
                $closing_rows = fin_gate($is_super_admin)->select('fin_closing_items', array('where' => array('period_id' => $sel_pid), 'orderBy' => 'id ASC'));
                foreach ($closing_rows as $it) {
                    $total++; if ($it['item_state'] === 'done') $done++;
                    $tone = $it['item_state'] === 'done' ? 'success' : ($it['item_state'] === 'na' ? 'secondary' : 'warn');
                    $lbl = array('pending' => 'معلّق', 'done' => 'منجَز', 'na' => 'لا ينطبق');
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($closing_steps[$it['step']] ?? $it['step']) . "</td>";
                    echo "<td>" . ($it['required'] ? '✔' : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . ($lbl[$it['item_state']] ?? $it['item_state']) . "</span></td>";
                    echo "<td>";
                    if ($can_edit && $it['item_state'] === 'pending') echo "<a href='?done_item=" . intval($it['id']) . "&pid=$sel_pid' class='action-btn edit' title='إنجاز'><i class='fas fa-check'></i></a>";
                    echo "</td></tr>";
                }
                $pct = $total > 0 ? round($done / $total * 100) : 0;
                ?>
                </tbody>
                <tfoot><tr><th colspan="4">نسبة الاكتمال: <span class="badge badge-<?php echo $pct == 100 ? 'success' : 'warn'; ?>"><?php echo $pct; ?>%</span> (<?php echo $done; ?>/<?php echo $total; ?>)</th></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
    $('#pt').on('change', function () { $('#pnowrap').toggle(this.value === 'month'); });
});
// M-39: الفتحُ الاستثنائي قرارٌ موثَّق — السببُ إلزامٌ قبل الإرسال
window.emsReopenPeriod = function (pid) {
    var reason = window.prompt('الفتحُ الاستثنائي لفترةٍ مقفلةٍ قرارٌ موثَّق.\nاكتب سببَ الفتح:');
    if (reason === null) { return; }
    reason = reason.trim();
    if (reason === '') { alert('السببُ إلزامي — لا فتحَ بلا سببٍ مكتوب'); return; }
    window.location = '?action=reopen&pid=' + pid + '&reason=' + encodeURIComponent(reason);
};
</script>
</body>
</html>
