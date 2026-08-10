<?php
/**
 * Finance/budget_form_fin.php — الميزانيات وبنودها والانحراف (fin_budgets + fin_budget_lines) — §3.5/§3.6/§7.2.
 * رأس + بنود ديناميكية. الانحراف = فعلي − مخطّط (عمود مولّد). اعتماد يجعلها مرجعاً حاكماً.
 * شاشة مستقلة — عزل شركة + حذف ناعم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/budget_form_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الميزانيات ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$dept_modules   = fin_dept_modules();
$period_types   = fin_period_types();
$budget_states  = fin_budget_states();
$categories     = fin_budget_categories();

// ── الرؤيةُ والملكيةُ مفصولتان (قرارا المالك 2026-07-27) ──────────────────
//   الرؤية  : كلُّ إدارةٍ موازنتُها وحدها · وأدوارُ المالية الكلَّ رقابةً
//   الملكية : مديرُ القسم وحده يُنشئ ويرفع — والمُجيزُ لا يملك قسمًا
// وكلتاهما من جدول توجيه الطلبات، فلا خريطةَ ثانيةً تنحرف.
$dept_scope    = fin_budget_dept_scope($ctx['role'], $is_super_admin);
$is_approver   = fin_budget_is_approver($ctx['role'], $is_super_admin);
$allowed_depts = $is_super_admin ? array_keys($dept_modules) : fin_budget_owned_depts($ctx['role']);

// ── دورةُ الموازنة: رفعٌ من الإدارة · إجازةٌ أو إعادةٌ من المالية ──
// (استُبدل الاعتمادُ المباشر القديم الذي كان يقبل `draft` أيضًا — فكان مَن يُعدّ
//  هو مَن يعتمد، وهو ما يمنعه الدستور.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['bg_action'] ?? '', array('submit', 'approve', 'return'), true)) {
    $act = $_POST['bg_action'];
    if (!$can_edit) { ems_gov_flash_redirect('budget_form_fin.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $res = fin_budget_transition($conn, intval($_POST['id'] ?? 0), $act,
        $ctx['role'], $current_user_id, $is_super_admin, $_POST['reason'] ?? '');
    if ($res['status'] === 'ok') {
        $done = array('submit' => 'رُفعت الموازنة للمالية ✅',
                      'approve' => 'أُجيزت الموازنة — صارت مرجعًا حاكمًا ✅',
                      'return'  => 'أُعيدت الموازنة لإدارتها بسببها ✅');
        ems_gov_flash_redirect('budget_form_fin.php', $done[$act], 'GOV-INFO-200', ''); exit();
    }
    ems_gov_flash_redirect('budget_form_fin.php', ($res['reason'] !== '' ? $res['reason'] : 'تعذّر الإجراء') . ' ❌', 'GOV-FAIL-409', ''); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('budget_form_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    // حذف ناعم مشروط بالمسودة (عبر update لحفظ شرط state='draft' الأصلي — softDelete بلا شرط)
    $did = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->update('fin_budgets',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $did), "state='draft'");
    ems_gov_flash_redirect('budget_form_fin.php', 'تم حذف الميزانية بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ ميزانية جديدة (رأس + بنود) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dept_module'])) {
    if (!$can_add) { ems_gov_flash_redirect('budget_form_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0) { ems_gov_flash_redirect('budget_form_fin.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $dept    = trim($_POST['dept_module'] ?? '');
    $ptype   = trim($_POST['period_type'] ?? '');
    $fyear   = intval($_POST['fiscal_year'] ?? 0);
    $pno     = ($_POST['period_no'] ?? '') === '' ? null : intval($_POST['period_no']);
    $note    = trim($_POST['note'] ?? '');
    $kinds   = $_POST['line_kind'] ?? array();
    $cats    = $_POST['category'] ?? array();
    $planned = $_POST['planned'] ?? array();

    if (!isset($dept_modules[$dept]) || !isset($period_types[$ptype]) || $fyear < 2000) {
        ems_gov_flash_redirect('budget_form_fin.php', 'بيانات غير صحيحة (إدارة/فترة/سنة) ❌', 'GOV-REF-404', ''); exit();
    }
    // النطاقُ الصفّي: الصلاحيةُ على الشاشة لا تكفي — الإدارةُ تنشئ موازنةَ قسمها
    // وحده، وإلا لأنشأ مديرُ الصيانة موازنةَ الموارد البشرية.
    if (!in_array($dept, $allowed_depts, true)) {
        ems_gov_flash_redirect('budget_form_fin.php', 'لا تُنشئ موازنةً لقسمٍ لا تديره ❌', 'GOV-FAIL-409', ''); exit();
    }

    $lines = array(); $t_rev = 0; $t_exp = 0;
    for ($i = 0; $i < count($cats); $i++) {
        $kind = ($kinds[$i] ?? '') === 'revenue' ? 'revenue' : 'expense';
        $cat  = trim($cats[$i] ?? '');
        $amt  = round(floatval($planned[$i] ?? 0), 2);
        if (!isset($categories[$cat]) || $amt <= 0) { continue; }
        $lines[] = array('k' => $kind, 'c' => $cat, 'p' => $amt);
        if ($kind === 'revenue') { $t_rev += $amt; } else { $t_exp += $amt; }
    }
    if (count($lines) < 1) { ems_gov_flash_redirect('budget_form_fin.php', 'الميزانية تحتاج بنداً واحداً فأكثر ❌', 'GOV-FAIL-409', ''); exit(); }

    $budget_no = fin_gen_code($conn, 'fin_budgets', 'FIN-BG', $company_id);
    // الرأس + البنود زوجٌ مترابط ذرّيًا (رأسٌ بلا بنود = ميزانية مكسورة) — معاملة
    // مُدارة §9: الأصل كتابتان غير معاملتين؛ تقوية مسار الفشل فقط، السعيد مطابق
    try {
        fin_gate($is_super_admin)->runInTransaction(function ($g) use ($budget_no, $dept, $ptype, $fyear, $pno, $t_rev, $t_exp, $note, $current_user_id, $lines) {
            $budget_id = $g->insert('fin_budgets', array(
                'budget_no' => $budget_no, 'dept_module' => $dept, 'period_type' => $ptype,
                'fiscal_year' => $fyear, 'period_no' => $pno, 'total_revenue' => $t_rev,
                'total_expense' => $t_exp, 'note' => $note, 'state' => 'draft', 'created_by' => $current_user_id,
            ));
            foreach ($lines as $ln) {
                $g->insert('fin_budget_lines', array(
                    'budget_id' => $budget_id, 'line_kind' => $ln['k'], 'category' => $ln['c'], 'planned_amount' => $ln['p'],
                ));
            }
        }, 'budget_form save head+lines');
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { ems_gov_flash_redirect('budget_form_fin.php', 'ميزانية هذه الفترة موجودة مسبقاً ❌', 'GOV-FAIL-409', ''); exit(); }
        error_log('budget save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('budget_form_fin.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('budget_form_fin.php', 'تم حفظ الميزانية بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | الميزانية والانحراف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main fin-budget-main ems-unified-page-shell">
    <?php
    $header_title = 'الميزانية والانحراف';
    $header_icon  = 'fa fa-chart-pie';
    $header_actions = array();
    // E-04 (SPEC-01 #21): متابعةُ الانحراف تبويبٌ من الميزانية لا رابطًا
    // مستقلًّا في التنقل — رابطُه من هنا وصفُّه في السايدبار أُخفي
    $header_actions[] = array('tag' => 'a', 'href' => 'variance_monitor_fin.php',
        'class' => 'suppliers-header-link', 'icon' => 'fa fa-magnifying-glass-chart',
        'label' => 'متابعة الانحراف');
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء ميزانية');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء ميزانية</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="b_dept">الإدارة المالكة <span class="required">*</span></label>
                        <select name="dept_module" id="b_dept" required>
                            <option value="">— اختر —</option>
                            <?php /* الأقسامُ المتاحةُ لدور المستخدم وحدها — لا يختار
                                      مديرُ الصيانة «الموارد البشرية» فيُرفض بعد الإرسال. */
                            foreach ($dept_modules as $k => $v) {
                                if (!in_array($k, $allowed_depts, true)) { continue; }
                                echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="b_ptype">نوع الفترة <span class="required">*</span></label>
                        <select name="period_type" id="b_ptype" required>
                            <?php foreach ($period_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="b_year">السنة المالية <span class="required">*</span></label>
                        <input type="number" name="fiscal_year" id="b_year" required value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="b_pno">رقم الفترة (ربع/شهر)</label>
                        <input type="number" name="period_no" id="b_pno" min="1" max="12" placeholder="اختياري">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="b_note">ملاحظة</label>
                        <input type="text" name="note" id="b_note">
                    </div>
                </div>
            </div>

            <div class="table-container" style="margin-top:10px;">
                <!-- جدولُ بنودٍ ديناميكيٌّ لا جدولَ عرض: لا يُهيَّأ DataTable البتّة،
                     وإلا محا إعادةُ الرسم صفوفَ البنود التي يضيفها bAddLine. -->
                <table class="alltables no-datatable" data-no-dt="1" style="width:100%;" id="b_lines">
                    <thead><tr><th>النوع</th><th>الفئة</th><th>المبلغ المخطّط</th><th></th></tr></thead>
                    <tbody id="b_lines_body"></tbody>
                </table>
                <button type="button" class="btn-secondary" onclick="bAddLine()"><i class="fas fa-plus"></i> إضافة بند</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ الميزانية</button>
                <button type="button" class="btn-secondary" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-list"></i> الميزانيات</h5>
        <?php
        // النطاق: كلُّ إدارةٍ ترى موازنتَها وحدها؛ والمُجيزُ يرى الكلَّ
        $budget_rows = fin_gate($is_super_admin)->select('fin_budgets', array('orderBy' => 'id DESC'));
        if ($dept_scope !== null) {
            $budget_rows = array_values(array_filter($budget_rows, function ($r) use ($dept_scope) {
                return in_array(strval($r['dept_module']), $dept_scope, true);
            }));
        }
        // إرشادُ الفراغ خارجَ الجدول: صفٌّ نائبٌ بـcolspan داخل tbody يخالف عددَ
        // أعمدة الترويسة، فيرمي DataTables استثناءً يُجهض بقيةَ مُعالج ready —
        // ومنه ربطُ زرِّ فتح الفورم. الجدولُ الفارغُ يعرض رسالةَ ar.json بنفسه.
        if (!$budget_rows) {
            echo "<p class='text-muted' style='margin:0 0 10px'>لا موازناتٍ لقسمك بعد — ابدأ بإنشاء موازنةٍ ثم ارفعها للمالية.</p>";
        }
        ?>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الرقم</th><th>الإدارة</th><th>الفترة</th><th>السنة</th>
                    <th>مخطّط إيراد</th><th>مخطّط مصروف</th><th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                    <?php
                    { foreach ($budget_rows as $row) {
                        $st = (string)$row['state'];
                        $st_tone = in_array($st, array('approved','active')) ? 'success'
                                 : ($st === 'closed' ? 'dark' : ($st === 'returned' ? 'warning'
                                 : ($st === 'submitted' ? 'info' : 'secondary')));
                        $mine = in_array(strval($row['dept_module']), $allowed_depts, true);
                        echo "<tr>";
                        // خانةُ الإجراءات تُبنى في ذاكرةٍ أولًا: إن خرجت فارغةً وُضع
                        // مكانَها **سببُ الفراغ**. خانةٌ خاليةٌ بلا كلمةٍ تُقرأ عطبًا
                        // — والمستخدمُ لا يميّز «لا إجراءَ مطلوبًا» من «الزرُّ معطوب».
                        ob_start();
                        // ① رفعٌ من الإدارة — مسودةً كانت أو معادة
                        if ($can_edit && $mine && !$is_approver && in_array($st, fin_budget_editable_states(), true)) {
                            echo "<form method='post' style='display:inline' onsubmit='return confirm(\"رفعُ الموازنة للمالية؟ لن تستطيع تعديلَ بنودها بعده.\")'>"
                               . "<input type='hidden' name='bg_action' value='submit'>"
                               . "<input type='hidden' name='id' value='" . intval($row['id']) . "'>"
                               . "<button type='submit' class='action-btn edit' title='رفعٌ للمالية'><i class='fas fa-paper-plane'></i></button></form>";
                        }
                        // ② إجازةٌ أو إعادةٌ — للإدارة المالية (رئيسِها ومعاونيه) على
                        //    المرفوعة وحدها، وبشرط ألّا يكون القسمُ قسمَه هو.
                        $self_owned = fin_budget_self_owned($ctx['role'], strval($row['dept_module']), $is_super_admin);
                        if ($is_approver && $st === 'submitted' && $can_edit && !$self_owned) {
                            echo "<form method='post' style='display:inline' onsubmit='return confirm(\"إجازةُ الموازنة كمرجعٍ حاكم؟\")'>"
                               . "<input type='hidden' name='bg_action' value='approve'>"
                               . "<input type='hidden' name='id' value='" . intval($row['id']) . "'>"
                               . "<button type='submit' class='action-btn edit' title='إجازة'><i class='fas fa-gavel'></i></button></form>";
                            echo "<form method='post' style='display:inline' onsubmit='var r=prompt(\"سببُ الإعادة (تقرؤه الإدارة):\");if(!r)return false;this.reason.value=r;'>"
                               . "<input type='hidden' name='bg_action' value='return'>"
                               . "<input type='hidden' name='id' value='" . intval($row['id']) . "'>"
                               . "<input type='hidden' name='reason' value=''>"
                               . "<button type='submit' class='action-btn delete' title='إعادةٌ بسبب'><i class='fas fa-rotate-left'></i></button></form>";
                        }
                        if ($can_delete && $mine && $st === 'draft') {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        $btns = trim((string) ob_get_clean());

                        // ولا خانةَ صامتة: حيث لا زرَّ يُذكر سببُه (الدستور §2 — لا
                        // شاشةَ تنتهي بطريقٍ مسدود). السببُ يُقرأ من حالة الصفّ نفسِها،
                        // فلا ينحرف عن الشروط أعلاه.
                        if ($btns === '') {
                            if ($is_approver && $st === 'submitted' && $can_edit && $self_owned) {
                                $why = "<i class='fas fa-user-lock'></i> قسمُك — يُجيزه مديرُ الإدارة المالية";
                            } elseif (in_array($st, array('approved', 'active'), true)) {
                                $why = "<i class='fas fa-check'></i> معتمدة — لا إجراءَ مطلوب";
                            } elseif ($st === 'closed') {
                                $why = "<i class='fas fa-lock'></i> مقفلة";
                            } elseif ($st === 'submitted') {
                                $why = $is_approver
                                    ? "<i class='fas fa-eye'></i> للاطّلاع — الإجازةُ لمن له تعديل"
                                    : "<i class='fas fa-hourglass-half'></i> عند المالية — تنتظر الإجازة";
                            } elseif (in_array($st, fin_budget_editable_states(), true)) {
                                $why = $mine
                                    ? "<i class='fas fa-eye'></i> للاطّلاع — الرفعُ يحتاج صلاحيةَ تعديل"
                                    : "<i class='fas fa-building'></i> قسمُ إدارةٍ أخرى — ترفعها بنفسها";
                            } else {
                                $why = '—';
                            }
                            $btns = "<span class='text-muted' style='font-size:12px'>{$why}</span>";
                        }
                        echo "<td><div class='action-btns' style='display:flex;gap:6px;flex-wrap:wrap;align-items:center'>"
                           . $btns . "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['budget_no']) . "</td>";
                        echo "<td>" . htmlspecialchars($dept_modules[$row['dept_module']] ?? $row['dept_module']) . "</td>";
                        echo "<td>" . htmlspecialchars($period_types[$row['period_type']] ?? $row['period_type']) . ($row['period_no'] ? ' #' . intval($row['period_no']) : '') . "</td>";
                        echo "<td>" . intval($row['fiscal_year']) . "</td>";
                        echo "<td>" . number_format((float)$row['total_revenue'], 2) . "</td>";
                        echo "<td>" . number_format((float)$row['total_expense'], 2) . "</td>";
                        echo "<td><span class='badge badge-" . $st_tone . "'>" . htmlspecialchars($budget_states[$st] ?? $st) . "</span>";
                        // سببُ الإعادة بارزٌ فوق كل شيءٍ للإدارة (الدستور §4.3)
                        if ($st === 'returned' && !empty($row['return_reason'])) {
                            echo "<div style='color:#7C2D12;font-size:12px;margin-top:4px'><i class='fas fa-rotate-left'></i> أُعيدت لاستكمال: "
                               . htmlspecialchars((string)$row['return_reason']) . "</div>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-triangle-exclamation"></i> مراقبة الانحراف (المخطّط مقابل الفعلي)</h5>
        <div class="table-container">
            <table id="varTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الميزانية</th><th>النوع</th><th>الفئة</th><th>مخطّط</th><th>فعلي</th><th>الانحراف</th><th>النسبة %</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    // تكافؤ INNER→LEFT + b.id IS NOT NULL (نمط F1 المُثبَت)
                    $var_rows = fin_gate($is_super_admin)->scopedQuery(
                        array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
                        "SELECT l.*, b.budget_no FROM fin_budget_lines l
                         LEFT JOIN fin_budgets b ON b.id = l.budget_id
                         WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0 ORDER BY l.id DESC");
                    { foreach ($var_rows as $l) {
                        $var = (float)$l['variance'];
                        $kind = $l['line_kind'] === 'revenue' ? 'إيراد' : 'مصروف';
                        // للمصروف: تجاوز (فعلي>مخطّط) = خطر. للإيراد: نقص (فعلي<مخطّط) = خطر.
                        $bad = ($l['line_kind'] === 'expense') ? ($var > 0) : ($var < 0);
                        $tone = ($var == 0) ? 'secondary' : ($bad ? 'danger' : 'success');
                        $pct = $l['variance_pct'] === null ? '—' : number_format((float)$l['variance_pct'], 1) . '%';
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars((string)$l['budget_no']) . "</td>";
                        echo "<td>" . $kind . "</td>";
                        echo "<td>" . htmlspecialchars($categories[$l['category']] ?? $l['category']) . "</td>";
                        echo "<td>" . number_format((float)$l['planned_amount'], 2) . "</td>";
                        echo "<td>" . number_format((float)$l['actual_amount'], 2) . "</td>";
                        echo "<td><span class='badge badge-" . $tone . "'>" . number_format($var, 2) . "</span></td>";
                        echo "<td>" . $pct . "</td>";
                        echo "<td>" . htmlspecialchars((string)$l['var_state']) . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<template id="b_line_tpl">
    <tr class="b-line">
        <td><select name="line_kind[]" class="b-kind"><option value="expense">مصروف</option><option value="revenue">إيراد</option></select></td>
        <td><select name="category[]" class="b-cat"><?php foreach ($categories as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></td>
        <td><input type="number" step="0.01" min="0" name="planned[]" class="b-planned" value="0"></td>
        <td><a href="javascript:void(0)" class="action-btn delete b-del" title="حذف"><i class="fas fa-times"></i></a></td>
    </tr>
</template>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
(function () {
    window.bAddLine = function () {
        var tpl = document.getElementById('b_line_tpl');
        document.getElementById('b_lines_body').appendChild(document.importNode(tpl.content, true));
    };
    $(document).ready(function () {
        // ① ربطُ الفورم أولاً: أيُّ استثناءٍ من تهيئة الجداول يُجهض بقيةَ هذا
        //    المُعالج، فلا يبقى فتحُ الفورم رهينةَ نجاحِ DataTables.
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#finForm').toggleClass('allforms-visible'); }); }
        $(document).on('click', '.b-del', function () { $(this).closest('tr').remove(); });
        bAddLine(); bAddLine();
        // ② جداولُ العرض بعده
        $('#finTable, #varTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });
    });
    window.finToggleForm = function () { $('#finForm').toggleClass('allforms-visible'); };
})();
</script>
</body>
</html>
