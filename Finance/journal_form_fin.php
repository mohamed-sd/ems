<?php
/**
 * Finance/journal_form_fin.php — القيود المالية (fin_journal_entries + fin_journal_lines) — §3.8 / §7.1.
 * محرك القيد المتوازن: لا يُرحَّل قيدٌ إلا إذا تساوى إجمالي المدين والدائن وله سطران فأكثر.
 * رأس + سطور ديناميكية في صفحة واحدة. عزل شركة + حذف ناعم. شاشة مستقلة.
 */
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = fin_page_perms($conn, 'Finance/journal_form_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+القيود+❌");
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);

// ── ترحيل القيد (محرك التوازن) ──
if (isset($_GET['post_id'])) {
    if (!$can_edit) { header("Location: journal_form_fin.php?msg=لا+توجد+صلاحية+الترحيل+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: journal_form_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); } // إصلاح #2
    if (!fin_can_perform($conn, $ctx['role'], 'finance_manager')) { header("Location: journal_form_fin.php?msg=الترحيل+يخصّ+المدير+المالي+فقط+❌"); exit(); } // فصل الواجبات
    $pid = intval($_GET['post_id']);
    // اجمع السطور وتحقق من التوازن
    $chk = mysqli_query($conn, "SELECT COUNT(*) AS n, COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c
                                FROM fin_journal_lines WHERE entry_id=$pid AND company_id=$company_id");
    $r = $chk ? mysqli_fetch_assoc($chk) : null;
    $n = $r ? intval($r['n']) : 0; $d = $r ? (float)$r['d'] : 0; $c = $r ? (float)$r['c'] : 0;
    if ($n < 2) { header("Location: journal_form_fin.php?msg=لا+ترحيل:+القيد+يحتاج+سطرين+فأكثر+❌"); exit(); }
    if (round($d, 2) !== round($c, 2)) { header("Location: journal_form_fin.php?msg=لا+ترحيل:+القيد+غير+متوازن+(مدين≠دائن)+❌"); exit(); }

    // إصلاح #3: لا ترحيل في فترة مالية مقفلة
    $pdrow = mysqli_query($conn, "SELECT posting_date FROM fin_journal_entries WHERE id=$pid AND company_id=$company_id LIMIT 1");
    $pdate = ($pdrow && ($pr = mysqli_fetch_assoc($pdrow))) ? $pr['posting_date'] : date('Y-m-d');
    if (!fin_period_posting_open($conn, $company_id, $pdate)) {
        header("Location: journal_form_fin.php?msg=لا+ترحيل:+الفترة+المالية+مقفلة+لهذا+التاريخ+❌"); exit();
    }

    mysqli_query($conn, "UPDATE fin_journal_entries SET state='posted', posted_by=$current_user_id, posted_at=NOW()
                         WHERE id=$pid AND company_id=$company_id AND state='draft'");
    // أثر الترحيل: نقل الحدث المرتبط إلى 'مقيَّد'
    $ev = mysqli_query($conn, "SELECT event_id FROM fin_journal_entries WHERE id=$pid AND company_id=$company_id");
    $evr = $ev ? mysqli_fetch_assoc($ev) : null;
    if ($evr && intval($evr['event_id']) > 0) {
        $eid = intval($evr['event_id']);
        mysqli_query($conn, "UPDATE fin_financial_events SET state='posted', journal_entry_id=$pid
                             WHERE id=$eid AND company_id=$company_id AND COALESCE(is_deleted,0)=0");
    }
    // (فجوة 3) الانحراف المستمر: تغذية «الفعلي» في الموازنة من القيود المرحّلة فورًا
    $fed = fin_recalc_budget_actuals($conn, $company_id);
    header("Location: journal_form_fin.php?msg=تم+ترحيل+القيد+وتحدّث+فعلي+الموازنة+($fed+بند)+✅"); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: journal_form_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $did = intval($_GET['delete_id']);
    mysqli_query($conn, "UPDATE fin_journal_entries SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id
                         WHERE id=$did AND company_id=$company_id AND state='draft'");
    header("Location: journal_form_fin.php?msg=تم+حذف+القيد+بنجاح+✅"); exit();
}

// ── حفظ قيد جديد (رأس + سطور) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['posting_date'])) {
    if (!$can_add) { header("Location: journal_form_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: journal_form_fin.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $posting_date = trim($_POST['posting_date'] ?? '');
    $event_id     = intval($_POST['event_id'] ?? 0) ?: null;
    $memo         = trim($_POST['memo'] ?? '');
    $accounts = $_POST['account_id'] ?? array();
    $debits   = $_POST['debit'] ?? array();
    $credits  = $_POST['credit'] ?? array();
    $lmemos   = $_POST['line_memo'] ?? array();

    if ($posting_date === '') { header("Location: journal_form_fin.php?msg=تاريخ+الترحيل+مطلوب+❌"); exit(); }

    // اجمع السطور الصالحة
    $lines = array(); $tot_d = 0; $tot_c = 0;
    for ($i = 0; $i < count($accounts); $i++) {
        $acc = intval($accounts[$i]);
        $dv  = round(floatval($debits[$i] ?? 0), 2);
        $cv  = round(floatval($credits[$i] ?? 0), 2);
        if ($acc <= 0 || ($dv == 0 && $cv == 0)) { continue; }
        $lines[] = array('acc' => $acc, 'd' => $dv, 'c' => $cv, 'm' => trim($lmemos[$i] ?? ''));
        $tot_d += $dv; $tot_c += $cv;
    }
    if (count($lines) < 2) { header("Location: journal_form_fin.php?msg=القيد+يحتاج+سطرين+صالحين+فأكثر+❌"); exit(); }

    $entry_no = fin_gen_code($conn, 'fin_journal_entries', 'FIN-JV', $company_id);
    $sql = "INSERT INTO fin_journal_entries (company_id, entry_no, event_id, posting_date, total_debit, total_credit, memo, state, created_by)
            VALUES (?,?,?,?,?,?,?, 'draft', ?)";
    $entry_id = 0;
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isssddsi', $company_id, $entry_no, $event_id, $posting_date, $tot_d, $tot_c, $memo, $current_user_id);
        mysqli_stmt_execute($stmt);
        $entry_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }
    if ($entry_id > 0) {
        $ls = mysqli_prepare($conn, "INSERT INTO fin_journal_lines (company_id, entry_id, account_id, debit, credit, memo) VALUES (?,?,?,?,?,?)");
        if ($ls) {
            foreach ($lines as $ln) {
                mysqli_stmt_bind_param($ls, 'iiidds', $company_id, $entry_id, $ln['acc'], $ln['d'], $ln['c'], $ln['m']);
                mysqli_stmt_execute($ls);
            }
            mysqli_stmt_close($ls);
        }
    }
    $bal = (round($tot_d, 2) === round($tot_c, 2)) ? 'متوازن' : 'غير متوازن';
    header("Location: journal_form_fin.php?msg=تم+حفظ+القيد+($bal)+✅"); exit();
}

$page_title = 'إيكوبيشن | القيود المالية';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main fin-journal-main ems-unified-page-shell">
    <?php
    $header_title = 'القيود المالية';
    $header_icon  = 'fa fa-scale-balanced';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء قيد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء قيد (مدين = دائن)</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>تاريخ الترحيل <span class="required">*</span></label>
                        <input type="date" name="posting_date" id="j_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>الحدث المرتبط (اختياري)</label>
                        <select name="event_id" id="j_event">
                            <option value="">— بلا حدث —</option>
                            <?php
                            $evsql = "SELECT id, CONCAT(event_no,' — ',amount,' ',currency) AS label FROM fin_financial_events
                                      WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0 AND state IN('approved','audited','fin_review') ORDER BY id DESC";
                            if ($er = mysqli_query($conn, $evsql)) { while ($e = mysqli_fetch_assoc($er)) {
                                echo "<option value='" . intval($e['id']) . "'>" . htmlspecialchars($e['label']) . "</option>";
                            } }
                            ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>بيان القيد</label>
                        <input type="text" name="memo" id="j_memo" placeholder="وصف القيد">
                    </div>
                </div>
            </div>

            <div class="table-container" style="margin-top:10px;">
                <table class="alltables" style="width:100%;" id="j_lines">
                    <thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>بيان</th><th></th></tr></thead>
                    <tbody id="j_lines_body"></tbody>
                    <tfoot><tr>
                        <th style="text-align:end">الإجمالي</th>
                        <th id="j_tot_d">0.00</th>
                        <th id="j_tot_c">0.00</th>
                        <th colspan="2"><span id="j_balance" class="badge badge-secondary">—</span></th>
                    </tr></tfoot>
                </table>
                <button type="button" class="btn-cancel" onclick="jAddLine()"><i class="fas fa-plus"></i> إضافة سطر</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ القيد</button>
                <button type="button" class="btn-cancel" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>رقم القيد</th><th>التاريخ</th><th>مدين</th><th>دائن</th>
                    <th>التوازن</th><th>البيان</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $scope_j = fin_scope('company_id', $is_super_admin, $company_id);
                    $sql = "SELECT * FROM fin_journal_entries WHERE $scope_j AND COALESCE(is_deleted,0)=0 ORDER BY id DESC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $balanced = (round((float)$row['total_debit'], 2) === round((float)$row['total_credit'], 2));
                        $st = (string)$row['state'];
                        $st_label = $st === 'posted' ? 'مرحَّل' : ($st === 'reversed' ? 'معكوس' : 'مسودة');
                        $st_tone  = $st === 'posted' ? 'success' : ($st === 'reversed' ? 'dark' : 'secondary');
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && $st === 'draft') {
                            echo "<a href='?post_id=" . intval($row['id']) . "&_t=" . fin_action_token() . "' class='action-btn edit' title='ترحيل' onclick='return confirm(\"ترحيل القيد؟ لا يُرحَّل غير المتوازن.\")'><i class='fas fa-check-double'></i></a>";
                        }
                        if ($can_delete && $st === 'draft') {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['entry_no']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['posting_date']) . "</td>";
                        echo "<td>" . number_format((float)$row['total_debit'], 2) . "</td>";
                        echo "<td>" . number_format((float)$row['total_credit'], 2) . "</td>";
                        echo "<td>" . ($balanced ? "<span class='badge badge-success'>متوازن</span>" : "<span class='badge badge-danger'>غير متوازن</span>") . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['memo'] ?? '')) . "</td>";
                        echo "<td><span class='badge badge-" . $st_tone . "'>" . htmlspecialchars($st_label) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<template id="j_line_tpl">
    <tr class="j-line">
        <td><select name="account_id[]" class="j-acc"><?php echo fin_postable_account_options($conn, $is_super_admin, $company_id); ?></select></td>
        <td><input type="number" step="0.01" min="0" name="debit[]" class="j-debit" value="0"></td>
        <td><input type="number" step="0.01" min="0" name="credit[]" class="j-credit" value="0"></td>
        <td><input type="text" name="line_memo[]" class="j-memo"></td>
        <td><a href="javascript:void(0)" class="action-btn delete j-del" title="حذف السطر"><i class="fas fa-times"></i></a></td>
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
    window.jAddLine = function () {
        var tpl = document.getElementById('j_line_tpl');
        var clone = document.importNode(tpl.content, true);
        document.getElementById('j_lines_body').appendChild(clone);
        jRecalc();
    };
    window.jRecalc = function () {
        var d = 0, c = 0;
        $('#j_lines_body .j-line').each(function () {
            d += parseFloat($(this).find('.j-debit').val()) || 0;
            c += parseFloat($(this).find('.j-credit').val()) || 0;
        });
        $('#j_tot_d').text(d.toFixed(2));
        $('#j_tot_c').text(c.toFixed(2));
        var bal = $('#j_balance');
        if (d === 0 && c === 0) { bal.attr('class', 'badge badge-secondary').text('—'); }
        else if (Math.round(d * 100) === Math.round(c * 100)) { bal.attr('class', 'badge badge-success').text('متوازن ✔'); }
        else { bal.attr('class', 'badge badge-danger').text('غير متوازن (فرق ' + (d - c).toFixed(2) + ')'); }
    };

    $(document).ready(function () {
        $('#finTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        // سطران افتراضيان جاهزان
        jAddLine(); jAddLine();

        $(document).on('input', '#j_lines_body .j-debit, #j_lines_body .j-credit', jRecalc);
        $(document).on('click', '.j-del', function () { $(this).closest('tr').remove(); jRecalc(); });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                $('#finForm').toggleClass('allforms-visible');
            });
        }
    });

    window.finToggleForm = function () {
        $('#finForm').toggleClass('allforms-visible');
    };
})();
</script>
</body>
</html>
