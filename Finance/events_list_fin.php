<?php
/**
 * Finance/events_list_fin.php — الأحداث المالية (fin_financial_events) — §4.
 * نمط موحّد: ترويسة + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 * شاشة جديدة مستقلة تماماً — لا تلمس أي جدول قائم (الأبعاد تُقرأ قراءةً فقط).
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

$perms = fin_page_perms($conn, 'Finance/events_list_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الأحداث+المالية+❌");
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$event_types    = fin_event_types();
$source_modules = fin_source_modules();
$event_states   = fin_event_states();
$currencies     = fin_currencies();

// (فجوة 4) تعليم الإشعارات مقروءة
fin_handle_notif_read($conn, $company_id, 'events_list_fin.php');

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_type'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: events_list_fin.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $event_type    = trim($_POST['event_type'] ?? '');
    $source_module = trim($_POST['source_module'] ?? '');
    $source_ref    = trim($_POST['source_ref'] ?? '');
    $amount        = floatval($_POST['amount'] ?? 0);
    $currency      = trim($_POST['currency'] ?? 'SDG');
    $fx_rate       = ($_POST['fx_rate'] ?? '') === '' ? null : floatval($_POST['fx_rate']);
    $project_id    = intval($_POST['project_id'] ?? 0) ?: null;
    $supplier_id   = intval($_POST['supplier_entity_id'] ?? 0) ?: null;
    $equipment_id  = intval($_POST['equipment_id'] ?? 0) ?: null;
    $notes         = trim($_POST['notes'] ?? '');

    // تحقّق خادمي: النوع/المصدر ضمن القوائم البيضاء + مبلغ موجب
    if (!isset($event_types[$event_type]) || !isset($source_modules[$source_module]) || $amount <= 0) {
        header("Location: events_list_fin.php?msg=بيانات+غير+صحيحة+(نوع/مصدر/مبلغ)+❌"); exit();
    }
    if (!in_array($currency, $currencies, true)) { $currency = 'SDG'; }

    if ($is_editing) {
        // A0 · عبر البوابة المحصَّنة (§12): تعديل حدثٍ منشورٍ على الناقل مرفوض؛
        // القيود اليدوية تُعدَّل كالمعتاد. سعة السوبر محفوظة بforAllTenants.
        try {
            $gate = $is_super_admin ? ems_tenant_db()->forAllTenants('finance super edit event') : ems_tenant_db();
            $gate->update('fin_financial_events', array(
                'event_type' => $event_type, 'source_module' => $source_module, 'source_ref' => $source_ref,
                'amount' => $amount, 'currency' => $currency, 'fx_rate' => $fx_rate,
                'project_id' => $project_id, 'supplier_entity_id' => $supplier_id,
                'equipment_id' => $equipment_id, 'notes' => $notes,
            ), array('id' => $id), 'COALESCE(is_deleted,0)=0');
        } catch (\App\Core\TenantGateException $e) {
            error_log('events_list edit refused: ' . $e->getMessage());
            header("Location: events_list_fin.php?msg=لا+يجوز+تعديل+حدثٍ+منشورٍ+على+الناقل+❌"); exit();
        }
        header("Location: events_list_fin.php?msg=تم+تعديل+الحدث+المالي+بنجاح+✅"); exit();
    } else {
        $event_no = fin_gen_code($conn, 'fin_financial_events', 'FIN-EV', $company_id);
        ems_tenant_db()->insert('fin_financial_events', array(
            'event_no' => $event_no, 'event_type' => $event_type, 'source_module' => $source_module,
            'source_ref' => $source_ref, 'amount' => $amount, 'currency' => $currency, 'fx_rate' => $fx_rate,
            'project_id' => $project_id, 'supplier_entity_id' => $supplier_id, 'equipment_id' => $equipment_id,
            'notes' => $notes, 'state' => 'draft', 'created_by' => $current_user_id,
        ));
        header("Location: events_list_fin.php?msg=تمت+إضافة+الحدث+المالي+بنجاح+✅"); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    // A0 · حذف ناعم عبر البوابة المحصَّنة (§12): حذف حدثٍ منشورٍ مرفوض.
    try {
        $gate = $is_super_admin ? ems_tenant_db()->forAllTenants('finance super delete event') : ems_tenant_db();
        $gate->softDelete('fin_financial_events', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('events_list delete refused: ' . $e->getMessage());
        header("Location: events_list_fin.php?msg=لا+يجوز+حذف+حدثٍ+منشورٍ+على+الناقل+❌"); exit();
    }
    header("Location: events_list_fin.php?msg=تم+حذف+الحدث+المالي+بنجاح+✅"); exit();
}

// ── تقديم الحدث في دورة الاعتماد (صندوق الاعتماد المستقل) ──
if (isset($_GET['advance_id'])) {
    if (!$can_edit) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+الاعتماد+❌"); exit(); }
    $aid = intval($_GET['advance_id']);
    $flow = fin_event_flow();
    // A0 · الحصانة (§12) تحرس **مضمون** الحدث المنشور لا موضعه في سير العمل:
    // تقدّم الحالة يمرّ عبر البوابة (state ضمن immutable_allow) — وهذا شرط التحام
    // D05→D04: الطلب يلد الحدث ثم تسير دورته وتُشتقّ حالة الطلب منها (§9).
    // قراءةٌ واحدة للحدث كاملًا عبر البوابة (تحقن is_deleted=0) تخدم المصفوفة والقيد.
    $event = ems_tenant_db()->selectOne('fin_financial_events', array('where' => array('id' => $aid)));
    $cur = $event ? $event['state'] : null;
    if ($cur !== null && isset($flow[$cur])) {
        list($next, $lbl, $level) = $flow[$cur];
        // فصل الواجبات: هذا الانتقال يخصّ مستواه فقط
        if (!fin_can_perform($conn, $ctx['role'], $level)) {
            header("Location: events_list_fin.php?msg=هذا+الإجراء+(" . urlencode($lbl) . ")+يخصّ+" . urlencode(fin_level_owner_label($level)) . "+❌"); exit();
        }

        // (فجوة 1) الاعتماد النهائي يخضع لمصفوفة الاعتماد بالمبلغ
        if ($next === 'approved' && $event) {
            $base = round((float)$event['amount'] * (($event['currency'] === 'USD') ? (float)($event['fx_rate'] ?: 600) : 1), 2);
            list($allowed, $required) = fin_matrix_gate($conn, $company_id, $ctx['role'], $event['event_type'], $base);
            if (!$allowed) {
                fin_notify($conn, $company_id, 'finance_manager', 'الحدث ' . $event['event_no'] . ' (' . number_format($base, 0) . ') يتطلب اعتماد ' . fin_matrix_level_label($required), 'events_list_fin.php?fstate=audited');
                header("Location: events_list_fin.php?msg=المصفوفة:+هذا+المبلغ+يتطلب+اعتماد+(" . urlencode(fin_matrix_level_label($required)) . ")+—+المدير+الأعلى+❌"); exit();
            }
        }

        // نقل الحالة عبر البوابة (حارس تفاؤلي state=? + حصانة §12) — للحدث اليدوي يمرّ؛ للمنشور يُرفض
        ems_tenant_db()->update('fin_financial_events', array('state' => $next), array('id' => $aid), "state=?", array($cur));
        fin_log_approval($conn, $company_id, $aid, $cur, $next, 'advance', $level, $current_user_id, $lbl);

        // (فجوة 4) إشعار صاحب الخطوة التالية
        $next_owner = array('dept_review' => 'dept_manager', 'dept_approved' => 'dept_manager',
                            'fin_review' => 'finance_reviewer', 'audited' => 'finance_manager');
        if ($event && isset($next_owner[$next])) {
            fin_notify($conn, $company_id, $next_owner[$next], 'الحدث ' . $event['event_no'] . ' بانتظارك: ' . ($event_states[$next] ?? $next), 'events_list_fin.php?fstate=' . $next);
        }

        // §9.3: حقيقة request.approved على الجذر عند الاعتماد المالي (لحدثِ طلبٍ حصرًا)
        if ($next === 'approved' && $event) {
            fin_publish_request_fact($conn, $aid, 'request.approved', 'appr',
                array('approved_by' => $current_user_id));
        }

        // (فجوة 2) عند الاعتماد النهائي: توليد القيد آليًا (مسودة مرتبطة)
        $auto_msg = '';
        if ($next === 'approved' && $event) {
            $jid = fin_auto_journal($conn, $company_id, $event, $current_user_id);
            if ($jid > 0) {
                $jrow = ems_tenant_db()->selectOne('fin_journal_entries', array('columns' => array('entry_no'), 'where' => array('id' => $jid)));
                $jno = $jrow ? $jrow['entry_no'] : ('#' . $jid);
                $auto_msg = '+وتولّد+القيد+' . urlencode($jno) . '+آليًا';
                fin_notify($conn, $company_id, 'finance_manager', 'قيد آلي ' . $jno . ' جاهز للترحيل (من ' . $event['event_no'] . ')', 'journal_form_fin.php');
            }
        }
        header("Location: events_list_fin.php?msg=تم+($lbl)+✅$auto_msg"); exit();
    }
    header("Location: events_list_fin.php?msg=لا+انتقال+متاح+من+هذه+الحالة+❌"); exit();
}

// ── رفض الحدث (يعود لمنشئه بسبب مسجَّل) ──
if (isset($_GET['reject_id'])) {
    if (!$can_edit) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+الرفض+❌"); exit(); }
    $rid = intval($_GET['reject_id']);
    // الرفض نقلُ حالةٍ لا تعديلُ مضمون — يمرّ للمنشور واليدوي معًا (§12 + immutable_allow)
    $erow = ems_tenant_db()->selectOne('fin_financial_events', array('columns' => array('state'), 'where' => array('id' => $rid)));
    $cur = $erow ? $erow['state'] : null;
    if ($cur !== null && !in_array($cur, array('posted','settled','closed','rejected'), true)) {
        ems_tenant_db()->update('fin_financial_events', array('state' => 'rejected'), array('id' => $rid));
        fin_log_approval($conn, $company_id, $rid, $cur, 'rejected', 'reject', null, $current_user_id, 'رفض/إعادة');
        fin_notify($conn, $company_id, 'dept_accountant', 'حدث مرفوض أُعيد إليك للتصحيح', 'events_list_fin.php?fstate=rejected');
        header("Location: events_list_fin.php?msg=تم+رفض+الحدث+✅"); exit();
    }
    header("Location: events_list_fin.php?msg=لا+يمكن+رفض+هذه+الحالة+❌"); exit();
}

// ── إعادة الحدث المرفوض للدورة (يعود لمنشئه، إصلاح #8) ──
if (isset($_GET['resume_id'])) {
    if (!$can_edit) { header("Location: events_list_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $rid = intval($_GET['resume_id']);
    // الإعادة للدورة نقلُ حالةٍ لا تعديلُ مضمون — تمرّ للمنشور واليدوي معًا
    $erow = ems_tenant_db()->selectOne('fin_financial_events', array('columns' => array('state'), 'where' => array('id' => $rid)));
    $cur = $erow ? $erow['state'] : null;
    if ($cur === 'rejected') {
        ems_tenant_db()->update('fin_financial_events', array('state' => 'draft'), array('id' => $rid), "state='rejected'");
        fin_log_approval($conn, $company_id, $rid, 'rejected', 'draft', 'advance', 'dept_accountant', $current_user_id, 'إعادة للدورة');
        header("Location: events_list_fin.php?msg=تمت+إعادة+الحدث+للدورة+(مسودة)+✅"); exit();
    }
    header("Location: events_list_fin.php?msg=لا+يمكن+إعادة+هذه+الحالة+❌"); exit();
}

$page_title = 'إيكوبيشن | المعاملات المالية';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main fin-events-main ems-unified-page-shell">
    <?php
    $header_title = 'المعاملات المالية';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة حدث مالي');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>
    <?php fin_notifications_panel($conn, $ctx, 'events_list_fin.php'); ?>

    <!-- فورم إضافة/تعديل -->
    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء / تعديل حدث مالي</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="f_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>نوع الحدث <span class="required">*</span></label>
                        <select name="event_type" id="f_event_type" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($event_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الإدارة المصدر <span class="required">*</span></label>
                        <select name="source_module" id="f_source_module" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($source_modules as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>المرجع التشغيلي</label>
                        <input type="text" name="source_ref" id="f_source_ref" placeholder="فاتورة / أمر / مستخلص">
                    </div>
                    <div class="form-group">
                        <label>المبلغ <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0" name="amount" id="f_amount" required>
                    </div>
                    <div class="form-group">
                        <label>العملة</label>
                        <select name="currency" id="f_currency">
                            <?php foreach ($currencies as $c) echo "<option value='" . htmlspecialchars($c) . "'>" . htmlspecialchars($c) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>سعر الصرف (للدولار)</label>
                        <input type="number" step="0.000001" min="0" name="fx_rate" id="f_fx_rate" placeholder="اختياري">
                    </div>
                    <div class="form-group">
                        <label>المشروع (بُعد تكلفة)</label>
                        <select name="project_id" id="f_project_id"><?php echo fin_project_options($conn, $is_super_admin, $company_id); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المورد</label>
                        <select name="supplier_entity_id" id="f_supplier_id"><?php echo fin_supplier_options($conn, $is_super_admin, $company_id); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المعدة (بُعد تكلفة)</label>
                        <select name="equipment_id" id="f_equipment_id"><?php echo fin_equipment_options($conn, $is_super_admin, $company_id); ?></select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" id="f_notes">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <?php $cur_f = (isset($_GET['fstate']) && isset($event_states[$_GET['fstate']])) ? $_GET['fstate'] : ''; ?>
    <div class="card"><div class="card-body" style="padding-bottom:6px">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <strong style="margin-inline-end:6px"><i class="fas fa-filter"></i> تصفية:</strong>
            <a href="events_list_fin.php" class="badge badge-<?php echo $cur_f === '' ? 'primary' : 'secondary'; ?>" style="text-decoration:none">الكل</a>
            <?php foreach (array('draft','dept_review','fin_review','audited','approved','rejected') as $fs): ?>
                <a href="?fstate=<?php echo $fs; ?>" class="badge badge-<?php echo $cur_f === $fs ? 'primary' : 'secondary'; ?>" style="text-decoration:none"><?php echo htmlspecialchars($event_states[$fs]); ?></a>
            <?php endforeach; ?>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>رقم الحدث</th><th>النوع</th><th>المصدر</th><th>المرجع</th>
                    <th>المبلغ</th><th>المشروع/المورد</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $filter_state = (isset($_GET['fstate']) && isset($event_states[$_GET['fstate']])) ? $_GET['fstate'] : '';
                    $flow = fin_event_flow();
                    // إثراء المشروع/المورد LEFT JOIN عبر scopedQuery (العزل على e، super→كل الشركات)؛
                    // تصفية الحالة الاختيارية بمعامِلٍ مربوط (لا حقن)
                    $ev_whereRaw = "COALESCE(e.is_deleted,0)=0";
                    $ev_params = array();
                    if ($filter_state !== '') { $ev_whereRaw .= " AND e.state=?"; $ev_params[] = $filter_state; }
                    // نطاق المشروع للدورين 5/6 (fin_project_scope — يريان أحداث موقعهما حصرًا)
                    $proj_scope = fin_project_scope($conn, $ctx);
                    if ($proj_scope !== null) { $ev_whereRaw .= " AND e.project_id = ?"; $ev_params[] = $proj_scope; }
                    $event_rows = fin_gate($is_super_admin)->scopedQuery(
                        array('scope' => array('e' => 'fin_financial_events'), 'enrich' => array('p' => 'project', 's' => 'suppliers')),
                        "SELECT e.*, p.name AS project_name, s.name AS supplier_name
                         FROM fin_financial_events e
                         LEFT JOIN project p ON p.id = e.project_id
                         LEFT JOIN suppliers s ON s.id = e.supplier_entity_id
                         WHERE {TENANT_SCOPE} AND " . $ev_whereRaw . " ORDER BY e.id DESC",
                        $ev_params);
                    foreach ($event_rows as $row) {
                        $st   = (string)$row['state'];
                        $dim  = $row['project_name'] ?: ($row['supplier_name'] ?: '—');
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-type='" . htmlspecialchars((string)$row['event_type'], ENT_QUOTES) . "' " .
                            "data-source='" . htmlspecialchars((string)$row['source_module'], ENT_QUOTES) . "' " .
                            "data-ref='" . htmlspecialchars((string)($row['source_ref'] ?? ''), ENT_QUOTES) . "' " .
                            "data-amount='" . htmlspecialchars((string)$row['amount'], ENT_QUOTES) . "' " .
                            "data-currency='" . htmlspecialchars((string)$row['currency'], ENT_QUOTES) . "' " .
                            "data-fx='" . htmlspecialchars((string)($row['fx_rate'] ?? ''), ENT_QUOTES) . "' " .
                            "data-project='" . intval($row['project_id']) . "' " .
                            "data-supplier='" . intval($row['supplier_entity_id']) . "' " .
                            "data-equipment='" . intval($row['equipment_id']) . "' " .
                            "data-notes='" . htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES) . "'";
                        $can_modify = ($st === 'draft'); // الإقفال بالمرحلة: بعد المسودة تُقفل من الشاشة
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && $can_modify) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete && $can_modify) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        if ($can_edit && isset($flow[$st])) {
                            $lbl = $flow[$st][1];
                            echo "<a href='?advance_id=" . intval($row['id']) . "' class='action-btn edit' title='" . htmlspecialchars($lbl) . "' onclick='return confirm(\"" . htmlspecialchars($lbl) . "؟\")'><i class='fas fa-circle-chevron-left'></i></a>";
                        }
                        if ($can_edit && !in_array($st, array('draft','posted','settled','closed','rejected'), true)) {
                            echo "<a href='?reject_id=" . intval($row['id']) . "' class='action-btn delete' title='رفض/إعادة' onclick='return confirm(\"رفض الحدث وإعادته؟\")'><i class='fas fa-ban'></i></a>";
                        }
                        if ($can_edit && $st === 'rejected') {
                            echo "<a href='?resume_id=" . intval($row['id']) . "' class='action-btn edit' title='إعادة للدورة' onclick='return confirm(\"إعادة الحدث للدورة كمسودة؟\")'><i class='fas fa-rotate-left'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['event_no']) . "</td>";
                        echo "<td>" . htmlspecialchars($event_types[$row['event_type']] ?? $row['event_type']) . "</td>";
                        echo "<td>" . htmlspecialchars($source_modules[$row['source_module']] ?? $row['source_module']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['source_ref'] ?? '')) . "</td>";
                        echo "<td>" . number_format((float)$row['amount'], 2) . " " . htmlspecialchars((string)$row['currency']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$dim) . "</td>";
                        echo "<td><span class='badge badge-" . fin_state_tone($st) . "'>" . htmlspecialchars($event_states[$st] ?? $st) . "</span></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
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
(function () {
    $(document).ready(function () {
        $('#finTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            // الأحدثُ أولًا: الترتيبُ من الخادم (ORDER BY e.id DESC) — نمنع فرزَ
            // DataTables الافتراضيَّ على عمود «الإجراءات» الذي كان يبعثره
            order: [],
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('finForm').reset();
                $('#f_id').val('');
                $('#finForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#f_id').val($t.data('id'));
            $('#f_event_type').val($t.data('type'));
            $('#f_source_module').val($t.data('source'));
            $('#f_source_ref').val($t.data('ref'));
            $('#f_amount').val($t.data('amount'));
            $('#f_currency').val($t.data('currency'));
            $('#f_fx_rate').val($t.data('fx'));
            $('#f_project_id').val(String($t.data('project')));
            $('#f_supplier_id').val(String($t.data('supplier')));
            $('#f_equipment_id').val(String($t.data('equipment')));
            $('#f_notes').val($t.data('notes'));
            $('#finForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#finForm').offset().top }, 400);
        });
    });

    window.finToggleForm = function () {
        var form = $('#finForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('finForm').reset();
            $('#f_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
