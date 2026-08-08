<?php
/**
 * Finance/events_list_fin.php — الأحداث المالية (fin_financial_events) — §4.
 * نمط موحّد: ترويسة + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 * شاشة جديدة مستقلة تماماً — لا تلمس أي جدول قائم (الأبعاد تُقرأ قراءةً فقط).
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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-INFO-200', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/events_list_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الأحداث المالية ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$event_types    = fin_event_types();
$source_modules = fin_source_modules();
$event_states   = fin_event_states();
$currencies     = fin_currencies();

// (فجوة 4) تعليم الإشعارات مقروءة
fin_handle_notif_read($conn, $company_id, 'events_list_fin.php');

/**
 * H-22: خلايا صفِّ الحدث (8 أعمدة) — تُستهلك من نقطة serverSide حصرًا.
 * أزرارُ الصف تُبنى بصلاحيات الجلسة نفسِها التي كانت تبنيها في SSR.
 */
function fin_event_row_cells(array $row, array $event_types, array $source_modules,
                             array $event_states, array $flow, $can_edit, $can_delete): array
{
    $st  = (string) $row['state'];
    $dim = $row['project_name'] ?: ($row['supplier_name'] ?: '—');
    $data_attrs =
        "data-id='" . intval($row['id']) . "' " .
        "data-type='" . htmlspecialchars((string) $row['event_type'], ENT_QUOTES) . "' " .
        "data-source='" . htmlspecialchars((string) $row['source_module'], ENT_QUOTES) . "' " .
        "data-ref='" . htmlspecialchars((string) ($row['source_ref'] ?? ''), ENT_QUOTES) . "' " .
        "data-amount='" . htmlspecialchars((string) $row['amount'], ENT_QUOTES) . "' " .
        "data-currency='" . htmlspecialchars((string) $row['currency'], ENT_QUOTES) . "' " .
        "data-fx='" . htmlspecialchars((string) ($row['fx_rate'] ?? ''), ENT_QUOTES) . "' " .
        "data-project='" . intval($row['project_id']) . "' " .
        "data-supplier='" . intval($row['supplier_entity_id']) . "' " .
        "data-equipment='" . intval($row['equipment_id']) . "' " .
        "data-notes='" . htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES) . "'";
    $can_modify = ($st === 'draft'); // الإقفال بالمرحلة: بعد المسودة تُقفل من الشاشة

    $actions = "<div class='action-btns'>";
    if ($can_edit && $can_modify) {
        $actions .= "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
    }
    if ($can_delete && $can_modify) {
        $actions .= "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
    }
    if ($can_edit && isset($flow[$st])) {
        $lbl = $flow[$st][1];
        $actions .= "<a href='?advance_id=" . intval($row['id']) . "' class='action-btn edit' title='" . htmlspecialchars($lbl) . "' onclick='return confirm(\"" . htmlspecialchars($lbl) . "؟\")'><i class='fas fa-circle-chevron-left'></i></a>";
    }
    if ($can_edit && !in_array($st, array('draft', 'posted', 'settled', 'closed', 'rejected'), true)) {
        $actions .= "<a href='?reject_id=" . intval($row['id']) . "' class='action-btn delete' title='رفض/إعادة' onclick='return confirm(\"رفض الحدث وإعادته؟\")'><i class='fas fa-ban'></i></a>";
    }
    if ($can_edit && $st === 'rejected') {
        $actions .= "<a href='?resume_id=" . intval($row['id']) . "' class='action-btn edit' title='إعادة للدورة' onclick='return confirm(\"إعادة الحدث للدورة كمسودة؟\")'><i class='fas fa-rotate-left'></i></a>";
    }
    $actions .= "</div>";

    return array(
        $actions,
        htmlspecialchars((string) $row['event_no']),
        htmlspecialchars($event_types[$row['event_type']] ?? $row['event_type']),
        htmlspecialchars($source_modules[$row['source_module']] ?? $row['source_module']),
        htmlspecialchars((string) ($row['source_ref'] ?? '')),
        number_format((float) $row['amount'], 2) . " " . htmlspecialchars((string) $row['currency']),
        htmlspecialchars((string) $dim),
        "<span class='badge badge-" . fin_state_tone($st) . "'>" . htmlspecialchars($event_states[$st] ?? $st) . "</span>",
    );
}

// ── H-22: نقطةُ DataTables الخادمية (UI-01 §4) — دفترُ الناقل ينمو مع كل
//    تشغيلٍ (507+ حدثًا) وكان يُحمَّل كاملًا في المتصفح بلا LIMIT.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'dt') {
    require_once __DIR__ . '/../includes/datatable_server.php';

    $dt = ems_dt_params(array(
        1 => 'e.id', 2 => 'e.event_type', 3 => 'e.source_module',
        4 => 'e.source_ref', 5 => 'e.amount', 7 => 'e.state',
    ));

    $filter_state = (isset($_GET['fstate']) && isset($event_states[$_GET['fstate']])) ? $_GET['fstate'] : '';
    $flow = fin_event_flow();

    $ev_whereRaw = "COALESCE(e.is_deleted,0)=0";
    $ev_params = array();
    if ($filter_state !== '') { $ev_whereRaw .= " AND e.state=?"; $ev_params[] = $filter_state; }
    $proj_scope = fin_project_scope($conn, $ctx);
    if ($proj_scope !== null) { $ev_whereRaw .= " AND e.project_id = ?"; $ev_params[] = $proj_scope; }

    $searchClause = ems_dt_like_clause($conn, $dt['search'],
        array('e.event_no', 'e.source_ref', 'e.event_type', 'e.source_module', 'p.name', 's.name'));
    $ev_whereAll = $ev_whereRaw . ($searchClause !== '' ? " AND " . $searchClause : '');

    $decl = array('scope' => array('e' => 'fin_financial_events'),
                  'enrich' => array('p' => 'project', 's' => 'suppliers'));
    $joins = "FROM fin_financial_events e
              LEFT JOIN project p ON p.id = e.project_id
              LEFT JOIN suppliers s ON s.id = e.supplier_entity_id";

    $trows = fin_gate($is_super_admin)->scopedQuery($decl,
        "SELECT COUNT(*) c $joins WHERE {TENANT_SCOPE} AND " . $ev_whereRaw, $ev_params);
    $total = $trows ? intval($trows[0]['c']) : 0;
    $frows = fin_gate($is_super_admin)->scopedQuery($decl,
        "SELECT COUNT(*) c $joins WHERE {TENANT_SCOPE} AND " . $ev_whereAll, $ev_params);
    $filtered = $frows ? intval($frows[0]['c']) : 0;

    $order = $dt['order_sql'] !== '' ? $dt['order_sql'] : 'e.id DESC';
    $event_rows = fin_gate($is_super_admin)->scopedQuery($decl,
        "SELECT e.*, p.name AS project_name, s.name AS supplier_name
         $joins WHERE {TENANT_SCOPE} AND " . $ev_whereAll . "
         ORDER BY $order, e.id DESC
         LIMIT " . intval($dt['length']) . " OFFSET " . intval($dt['start']),
        $ev_params);

    $data = array();
    foreach ($event_rows as $row) {
        $data[] = fin_event_row_cells($row, $event_types, $source_modules, $event_states,
                                      $flow, $can_edit, $can_delete);
    }
    ems_dt_emit($dt['draw'], $total, $filtered, $data);
}

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_type'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('events_list_fin.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }

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
        ems_gov_flash_redirect('events_list_fin.php', 'بيانات غير صحيحة (نوع/مصدر/مبلغ) ❌', 'GOV-REF-404', ''); exit();
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
            ems_gov_flash_redirect('events_list_fin.php', 'لا يجوز تعديل حدثٍ منشورٍ على الناقل ❌', 'GOV-INFO-200', ''); exit();
        }
        ems_gov_flash_redirect('events_list_fin.php', 'تم تعديل الحدث المالي بنجاح ✅', 'GOV-OK-200', ''); exit();
    } else {
        $event_no = fin_gen_code($conn, 'fin_financial_events', 'FIN-EV', $company_id);
        ems_tenant_db()->insert('fin_financial_events', array(
            'event_no' => $event_no, 'event_type' => $event_type, 'source_module' => $source_module,
            'source_ref' => $source_ref, 'amount' => $amount, 'currency' => $currency, 'fx_rate' => $fx_rate,
            'project_id' => $project_id, 'supplier_entity_id' => $supplier_id, 'equipment_id' => $equipment_id,
            'notes' => $notes, 'state' => 'draft', 'created_by' => $current_user_id,
        ));
        ems_gov_flash_redirect('events_list_fin.php', 'تمت إضافة الحدث المالي بنجاح ✅', 'GOV-OK-200', ''); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    // A0 · حذف ناعم عبر البوابة المحصَّنة (§12): حذف حدثٍ منشورٍ مرفوض.
    try {
        $gate = $is_super_admin ? ems_tenant_db()->forAllTenants('finance super delete event') : ems_tenant_db();
        $gate->softDelete('fin_financial_events', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('events_list delete refused: ' . $e->getMessage());
        ems_gov_flash_redirect('events_list_fin.php', 'لا يجوز حذف حدثٍ منشورٍ على الناقل ❌', 'GOV-INFO-200', ''); exit();
    }
    ems_gov_flash_redirect('events_list_fin.php', 'تم حذف الحدث المالي بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── تقديم الحدث في دورة الاعتماد (صندوق الاعتماد المستقل) ──
if (isset($_GET['advance_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية الاعتماد ❌', 'GOV-PERM-403', ''); exit(); }
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
        // H-12 (FES §7.2): مزامنةُ آلة الحالات الأربعَ عشرة — بقفلها التفاؤلي
        // وختمِ فاعلها (approved_by/at عند الاعتماد). فشلُها يُسجَّل ولا يقطع
        // المسارَ القائم (الحالةُ القديمة تقدّمت سلفًا والمرآةُ تلحق).
        require_once __DIR__ . '/../app/Services/Finance/EventStateMachine.php';
        $fesSync = \App\Services\Finance\EventStateMachine::syncFromLegacy(
            ems_tenant_db(), $conn, $aid, $next, $current_user_id);
        if (!$fesSync['ok']) {
            error_log('events_list fes sync #' . $aid . ' → ' . $next . ': ' . implode(' · ', $fesSync['reasons']));
        }

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
        ems_gov_flash_redirect('events_list_fin.php', 'تم ($lbl) ✅$auto_msg', 'GOV-OK-200', ''); exit();
    }
    ems_gov_flash_redirect('events_list_fin.php', 'لا انتقال متاح من هذه الحالة ❌', 'GOV-INFO-200', ''); exit();
}

// ── رفض الحدث (يعود لمنشئه بسبب مسجَّل) ──
if (isset($_GET['reject_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية الرفض ❌', 'GOV-PERM-403', ''); exit(); }
    $rid = intval($_GET['reject_id']);
    // الرفض نقلُ حالةٍ لا تعديلُ مضمون — يمرّ للمنشور واليدوي معًا (§12 + immutable_allow)
    $erow = ems_tenant_db()->selectOne('fin_financial_events', array('columns' => array('state'), 'where' => array('id' => $rid)));
    $cur = $erow ? $erow['state'] : null;
    if ($cur !== null && !in_array($cur, array('posted','settled','closed','rejected'), true)) {
        ems_tenant_db()->update('fin_financial_events', array('state' => 'rejected'), array('id' => $rid));
        fin_log_approval($conn, $company_id, $rid, $cur, 'rejected', 'reject', null, $current_user_id, 'رفض/إعادة');
        // H-12: رفضُ هذه الشاشة قابلٌ للإعادة للدورة — فهو ReturnedToSource دلاليًّا
        // (FES §7.2) لا Rejected النهائية التي لا رجعةَ منها إلا بمستندٍ جديد.
        require_once __DIR__ . '/../app/Services/Finance/EventStateMachine.php';
        $fesSync = \App\Services\Finance\EventStateMachine::syncTo(
            ems_tenant_db(), $conn, $rid, 'ReturnedToSource', $current_user_id);
        if (!$fesSync['ok']) { error_log('events_list fes reject sync #' . $rid . ': ' . implode(' · ', $fesSync['reasons'])); }
        fin_notify($conn, $company_id, 'dept_accountant', 'حدث مرفوض أُعيد إليك للتصحيح', 'events_list_fin.php?fstate=rejected');
        ems_gov_flash_redirect('events_list_fin.php', 'تم رفض الحدث ✅', 'GOV-OK-200', ''); exit();
    }
    ems_gov_flash_redirect('events_list_fin.php', 'لا يمكن رفض هذه الحالة ❌', 'GOV-INFO-200', ''); exit();
}

// ── إعادة الحدث المرفوض للدورة (يعود لمنشئه، إصلاح #8) ──
if (isset($_GET['resume_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('events_list_fin.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $rid = intval($_GET['resume_id']);
    // الإعادة للدورة نقلُ حالةٍ لا تعديلُ مضمون — تمرّ للمنشور واليدوي معًا
    $erow = ems_tenant_db()->selectOne('fin_financial_events', array('columns' => array('state'), 'where' => array('id' => $rid)));
    $cur = $erow ? $erow['state'] : null;
    if ($cur === 'rejected') {
        ems_tenant_db()->update('fin_financial_events', array('state' => 'draft'), array('id' => $rid), "state='rejected'");
        fin_log_approval($conn, $company_id, $rid, 'rejected', 'draft', 'advance', 'dept_accountant', $current_user_id, 'إعادة للدورة');
        // H-12: العودةُ للدورة = ReturnedToSource → Published (نسخةٌ تُستأنف — FES §7.2)
        require_once __DIR__ . '/../app/Services/Finance/EventStateMachine.php';
        $fesSync = \App\Services\Finance\EventStateMachine::syncTo(
            ems_tenant_db(), $conn, $rid, 'Published', $current_user_id);
        if (!$fesSync['ok']) { error_log('events_list fes resume sync #' . $rid . ': ' . implode(' · ', $fesSync['reasons'])); }
        ems_gov_flash_redirect('events_list_fin.php', 'تمت إعادة الحدث للدورة (مسودة) ✅', 'GOV-OK-200', ''); exit();
    }
    ems_gov_flash_redirect('events_list_fin.php', 'لا يمكن إعادة هذه الحالة ❌', 'GOV-INFO-200', ''); exit();
}

$page_title = 'إيكوبيشن | المعاملات المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
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
                                    <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ السجل وبأي صفة">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="السجل الذي تولد عنه">المرجع الأب</th>
</tr></thead>
                <tbody>
                    <?php
                    // H-22: كان يُحمَّل هنا الدفترُ كاملًا بلا LIMIT (محظورُ UI-01 §4)
                    // — الجدولُ الآن serverSide يجلب صفحةَ 50 من ?ajax=dt أعلاه.
                    $filter_state = (isset($_GET['fstate']) && isset($event_states[$_GET['fstate']])) ? $_GET['fstate'] : '';
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
            // ── H-22 (UI-01 §4/§9): معالجةٌ خادمية — الدفترُ لا يُحمَّل كاملًا
            //    في المتصفح؛ صفحةُ 50 وبحثٌ مؤخَّر 400ms من الخادم.
            serverSide: true,
            processing: true,
            searchDelay: 400,
            deferRender: true,
            pageLength: 50,
            ajax: {
                url: 'events_list_fin.php',
                data: function (d) {
                    d.ajax = 'dt';
                    d.fstate = <?php echo json_encode($filter_state); ?>;
                }
            },
            // الأحدثُ أولًا: الترتيبُ من الخادم (ORDER BY e.id DESC) — نمنع فرزَ
            // DataTables الافتراضيَّ على عمود «الإجراءات» الذي كان يبعثره
            order: [],
            columnDefs: [{ targets: [0, 6], orderable: false }],
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
