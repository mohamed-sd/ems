<?php
/**
 * Procurement/requests_proc.php — طلبات الشراء التشغيلية (proc_request + proc_request_line) — §15.1.
 * مستند رأس + سطور في صفحة واحدة (سطور ديناميكية عبر <template>). عزل شركة + حذف ناعم.
 * التصنيف التشغيلي إلزامي (وقائية/تصحيحية/رأسمالية/استهلاكية). شاشة جديدة مستقلة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/requests_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض طلبات الشراء ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$classifications = proc_classifications();
$need_sources   = proc_need_sources();
$priorities     = proc_priorities();
$states         = proc_request_states();
$fin_states     = array('بانتظار', 'معتمد مالياً', 'مرفوض');

/* ── INJ-0089 · مَن يملك ضبطَ «الاعتماد المالي»؟ ─────────────────────────────
     ليست صلاحيةَ هذه الشاشةِ بل صلاحيةَ **المالية**: من يملك الكتابةَ على
     صندوقِ الاعتماداتِ المالية. فطالبُ الشراءِ قد يملك `can_edit` هنا بحقٍّ
     ويبقى ممنوعًا من إعلانِ طلبِه معتمدًا ماليًّا.
     ◆ والسوبرُ استثناءٌ مُعلَن، والدالةُ تُحسب مرةً واحدةً لكلِّ طلبٍ (static). */
if (!function_exists('ems_proc_may_finance_approve')) {
    function ems_proc_may_finance_approve(mysqli $conn, $uid)
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        if (strval($_SESSION['user']['role'] ?? '') === '-1') { return $cache = true; }
        $role = intval($_SESSION['user']['role'] ?? 0);
        if ($role <= 0) { return $cache = false; }
        $st = $conn->prepare("SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                               WHERE rp.role_id = ? AND rp.can_edit = 1
                                 AND m.code IN ('Finance/approvals_inbox.php', 'FinRequests/requests.php')
                               LIMIT 1");
        if (!$st) { return $cache = false; }
        $st->bind_param('i', $role);
        $st->execute();
        $cache = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $cache;
    }
}

// ── ③ توليدُ الاحتياج الآن: جسرُ الصيانة + كنّاسُ حدود الطلب — يدويًّا ──
// (القناةُ الدورية cron_proc_replenish.php؛ والزرُّ لمن لا ينتظر الساعة)
/* AC-F2: حارسُ الكتابةِ المركزيُّ **قبلَ** أولِ عبارةِ كتابة — fail-closed.
   وفحوصُ $can_add/$can_edit تبقى داخلَ فروعِها: تلك تميّز الفعلَ وهذا يحرس البوابة. */
ems_require_action($conn, 'Procurement/requests_proc.php', 'edit', array('deny_msg' => 'طلباتُ الشراءِ تحتاج صلاحيةَ تحرير'));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_needs') {
    if (!$can_add) { ems_gov_flash_redirect('requests_proc.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    require_once __DIR__ . '/../app/Services/Procurement/ProcReorderService.php';
    require_once __DIR__ . '/../app/Services/Procurement/MntProcBridgeService.php';
    $gn_gate = proc_gate(false);
    $b = \App\Services\Procurement\MntProcBridgeService::run($conn, $gn_gate, $company_id, $current_user_id, false);
    $r2 = \App\Services\Procurement\ProcReorderService::run($conn, $gn_gate, $company_id, $current_user_id, false);
    $n = count($b['generated']) + count($r2['generated']);
    $sk = count($b['skipped']) + count($r2['skipped']);
    ems_gov_redirect("Location: requests_proc.php?msg=" . urlencode(
        $n > 0 ? "وُلّد $n طلبًا (صيانة: " . count($b['generated']) . " · حد الطلب: " . count($r2['generated']) . ")"
               . ($sk ? " — وتُخطي $sk بعطالته" : '') . " ✅"
               : "لا احتياجَ جديدًا — كلُّ المفتوح مغطًّى بطلبه" . ($sk ? " ($sk بعطالته)" : '') . " ✅"
    )); exit();
}

// ── حفظ (إضافة/تعديل) رأس + سطور ضمن معاملة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['need_source'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('requests_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('requests_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('requests_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $need_source = trim($_POST['need_source'] ?? '');
    $source_ref  = trim($_POST['source_ref'] ?? '');
    $op_classification = trim($_POST['op_classification'] ?? '');
    $requesting_dept   = trim($_POST['requesting_dept'] ?? '');
    $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? intval($_POST['equipment_id']) : null;
    $project_id   = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
    $priority     = trim($_POST['priority'] ?? 'عادي');
    /* ── INJ-0089 · الحالةُ الماليةُ ليست حقلًا في نموذجِ الطالب ─────────────────
         نصُّ القبول: «محاولةُ الطالبِ ضبطَ الحالة المالية بنفسه غيرُ ممكنةٍ
         (الحقلُ **غيرُ موجودٍ في نموذجه**)». وكان في النموذجِ قائمةٌ منسدلةٌ
         مفتوحةٌ لكلِّ من يكتب — فيُعلن الطالبُ طلبَه «معتمدًا ماليًّا» بنفسه.
         والنزعُ من الواجهةِ وحدَه لا يكفي: الطلبُ يُصنَع بأداةٍ خارجَ المتصفح.
         فيُتجاهل الحقلُ في **الخادمِ** لمن لا يملك الاعتمادَ المالي. */
    $__mayFin = ems_proc_may_finance_approve($conn, $current_user_id);
    $fin_state    = $__mayFin ? trim($_POST['fin_approval_state'] ?? 'بانتظار') : 'بانتظار';
    if (!$__mayFin && isset($_POST['fin_approval_state'])
        && trim((string) $_POST['fin_approval_state']) !== 'بانتظار') {
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'procurement', 'proc_request', 'fin_state_refused', 0,
            array(), array('attempted' => trim((string) $_POST['fin_approval_state'])),
            array('company_id' => intval($company_id), 'user_id' => intval($current_user_id)));
    }
    $state        = trim($_POST['state'] ?? 'مسودة');
    $notes        = trim($_POST['notes'] ?? '');

    if (!in_array($need_source, $need_sources, true) || !in_array($op_classification, $classifications, true)) {
        ems_gov_flash_redirect('requests_proc.php', 'بيانات غير مكتملة (المصدر والتصنيف إلزاميان) ❌', 'GOV-FAIL-409', ''); exit();
    }
    if (!in_array($priority, $priorities, true)) { $priority = 'عادي'; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }
    if (!in_array($fin_state, $fin_states, true)) { $fin_state = 'بانتظار'; }

    // K9-M1: الأب عبر البوابة والسطور عبر replaceChildren (النمط المبرَّر §8)
    $parent = array(
        'need_source' => $need_source, 'source_ref' => $source_ref,
        'op_classification' => $op_classification, 'requesting_dept' => $requesting_dept,
        'equipment_id' => $equipment_id, 'project_id' => $project_id,
        'priority' => $priority, 'fin_approval_state' => $fin_state,
        'state' => $state, 'notes' => $notes,
    );
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $classes = $_POST['line_class'] ?? array();
    $lnotes = $_POST['line_note'] ?? array();
    $line_rows = array();
    for ($i = 0; $i < count($item_names); $i++) {
        $iname = trim($item_names[$i] ?? '');
        if ($iname === '') { continue; }
        $cls = trim($classes[$i] ?? '');
        if (!in_array($cls, $classifications, true)) { $cls = $op_classification; }
        $line_rows[] = array(
            'item_id' => (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null,
            'item_name' => $iname,
            'qty' => (float)($qtys[$i] ?? 1),
            'op_classification' => $cls,
            'note' => trim($lnotes[$i] ?? ''),
        );
    }
    try {
        $g = proc_gate(false);
        if ($is_editing) {
            $g->update('proc_request', $parent, array('id' => $id, 'is_deleted' => 0));
            $req_id = $id;
        } else {
            $parent['code'] = proc_gen_code($conn, 'proc_request', 'PRC-REQ', $company_id);
            $parent['created_by'] = $current_user_id;
            $req_id = $g->insert('proc_request', $parent);
        }
        $g->replaceChildren('proc_request', $req_id, 'proc_request_line', 'request_id', $line_rows, 'request lines rewrite');
    } catch (\Throwable $e) {
        error_log('requests_proc save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('requests_proc.php', 'تعذّر الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_redirect("Location: requests_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الطلب+بنجاح+✅' : 'تمت+إضافة+الطلب+بنجاح+✅')); exit();
}

// ── حذف ناعم (السطور تُحذف بالـ CASCADE عند الحذف الصلب، لكن هنا حذف ناعم للرأس فقط) ──
// E-21 (UX-00 §4.3): **ثلاثيةُ القرار الموحّدة** على صندوق طلبات الشراء —
// اعتمادٌ · إعادةٌ للاستكمال بسبب · رفضٌ بسبب (كانت الحالةُ قائمةً منسدلةً حرة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'e21_decide') {
    if (!$can_edit) { ems_gov_flash_redirect('requests_proc.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
    $rid = intval($_POST['request_id'] ?? 0);
    $decision = strval($_POST['decision'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    $req = proc_gate(false)->selectOne('proc_request', array('where' => array('id' => $rid)));
    if (!$req || (string)$req['state'] !== 'مقدَّم') {
        ems_gov_flash_redirect('requests_proc.php', 'القرارُ على «مقدَّم» وحدَه — الحالُ: ' . ($req['state'] ?? 'غير موجود') . ' ❌', 'GOV-REF-404', ''); exit();
    }
    // M-45: من أنشأ لا يعتمد — الحارسُ العام
    if ($decision === 'approve') {
        require_once __DIR__ . '/../includes/self_approval_guard.php';
        $blocked = ems_no_self_approval($conn, intval($req['created_by']), $current_user_id,
            'طلب الشراء ' . strval($req['code']), $company_id);
        if ($blocked !== null) { ems_gov_flash_redirect('requests_proc.php', $blocked['reason'] . ' ❌', 'GOV-FAIL-409', ''); exit(); }
    }
    if (in_array($decision, array('return', 'reject'), true) && $reason === '') {
        ems_gov_flash_redirect('requests_proc.php', 'الإعادةُ والرفضُ بسببٍ مكتوبٍ إلزامًا ❌', 'GOV-FAIL-409', ''); exit();
    }
    /* ══ INJ-0089 · «فوقَ سقفِ المشترياتِ لا يُعتمد داخلَ الإدارة» ══════════════
         نصُّ القبول: «طلبُ شراءٍ بقيمةٍ فوق سقفِ المشتريات **لا يمكن اعتمادُه
         داخل الإدارة** ويظهر في صندوق اعتماد نائب المالية».
         وقيمةُ الطلبِ ليست عمودًا فيه بل مجموعُ أسطرِه — فتُجمع من مصدرِها.
         و`AuthorityGuard` يقرأ سقفَ المعتمِدِ من `signing_authorities`؛ فإن
         تجاوزَه الطلبُ **لم يُعتمد هنا** بل رُفع صفًّا في صندوقِ الاعتمادِ الأعلى. */
    if ($decision === 'approve') {
        require_once __DIR__ . '/../app/Core/AuthorityGuard.php';
        $__total = 0.0;
        /* ◆ سطرُ الطلبِ لا يحمل سعرًا — السعرُ يأتي عند العرضِ والأمر. فقيمةُ
             الطلبِ التقديريةُ = الكميةُ × **متوسطُ تكلفةِ الصنف** (`avg_cost`).
             وصنفٌ بلا متوسطٍ يُحسب صفرًا فلا يرفع الطلبَ فوقَ سقفٍ بلا سند. */
        $__q = $conn->prepare('SELECT COALESCE(SUM(l.qty * COALESCE(i.avg_cost, 0)), 0)
                                 FROM proc_request_line l
                                 LEFT JOIN proc_item i ON i.id = l.item_id
                                WHERE l.request_id = ?');
        if ($__q) {
            $__q->bind_param('i', $rid);
            $__q->execute();
            $__x = $__q->get_result()->fetch_row();
            $__q->close();
            if ($__x) { $__total = (float) $__x[0]; }
        }
        $__ent = \App\Core\AuthorityGuard::tenantEntity($conn, $company_id);
        if ($__ent && $__total > 0) {
            $__sig = \App\Core\AuthorityGuard::sign($conn, array(
                'document_type' => 'proc_request', 'document_id' => $rid, 'step' => 'approve',
                'person_id' => (int) $current_user_id, 'company_id' => (int) $company_id,
                'entity_id' => $__ent, 'amount' => $__total,
                'created_by_person_id' => (int) $req['created_by'],
            ));
            if (empty($__sig['ok']) && (int) $__sig['code'] === 409) {
                $__esc = $conn->prepare("INSERT INTO exec_approvals
                    (company_id, request_no, received_date, doc_type, document, requesting_dept,
                     raise_reason, amount, currency, status, source_kind, created_by, created_by_name)
                    VALUES (?, ?, CURDATE(), 'طلب شراء', ?, 'المشتريات التشغيلية',
                            ?, ?, 'USD', 'قيد المراجعة', 'escalation', ?, ?)");
                if ($__esc) {
                    $__rq = 'ESC-PR-' . $rid . '-' . date('ymdHis');
                    $__doc = 'طلبُ شراءٍ ' . (string) ($req['code'] ?? ('#' . $rid));
                    $__why = 'تجاوزُ سقفِ المشتريات — ' . $__sig['reason'];
                    $__amt = (string) $__total;
                    $__nm  = 'معتمِدُ المشتريات #' . (int) $current_user_id;
                    $__uidI = (int) $current_user_id;
                    $__esc->bind_param('sssssis', $__rq, $__doc, $__why, $__amt, $__uidI, $__nm);
                    $__esc->execute();
                    $__esc->close();
                }
                ems_gov_flash_redirect('requests_proc.php',
                    'PROC-CAP-409: ' . $__sig['reason'] . ' — **رُفع الطلبُ إلى صندوقِ اعتمادِ نائبِ المالية** ⤴',
                    'GOV-FAIL-409', 'الاعتمادُ داخلَ الإدارةِ لا يتجاوز سقفَها');
                exit();
            }
        }
    }
    $to = $decision === 'approve' ? 'اعتماد المشتريات'
        : ($decision === 'return' ? 'مسودة' : 'مرفوض');
    proc_gate(false)->update('proc_request', array('state' => $to,
        'notes' => trim((string)$req['notes'] . ($reason !== '' ? ("\n[" . ($decision === 'return' ? 'إعادة' : 'رفض') . '] ' . $reason) : ''))),
        array('id' => $rid));
    require_once __DIR__ . '/../includes/audit_trail.php';
    ems_audit_change($conn, 'procurement', 'proc_request', 'e21_' . $decision, $rid,
        array('state' => 'مقدَّم'), array('state' => $to, 'reason' => $reason),
        array('company_id' => intval($company_id), 'user_id' => intval($current_user_id)));
    ems_gov_flash_redirect('requests_proc.php', 'قرارٌ بالثلاثية: ' . $to . ' ✅', 'GOV-OK-200', ''); exit();
}

if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('requests_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        proc_gate(false)->softDelete('proc_request', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('requests_proc softDelete refused: ' . $e->getMessage());
    }
    ems_gov_flash_redirect('requests_proc.php', 'تم حذف الطلب بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── تحميل طلب للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $edit = proc_gate($is_super_admin)->selectOne('proc_request', array('where' => array('id' => $eid)));
    if ($edit) {
        $edit_lines = proc_gate($is_super_admin)->select('proc_request_line', array(
            'where' => array('request_id' => $eid), 'orderBy' => 'id ASC',
        ));
    }
}

$page_title = 'إيكوبيشن | طلبات الشراء';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/** يبني صف سطر واحد (للسطور المحمّلة عند التعديل). */
function proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $cls = $line ? (string)($line['op_classification'] ?? '') : '';
    $note = $line ? htmlspecialchars((string)($line['note'] ?? ''), ENT_QUOTES) : '';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    $clsopts = '<option value="">— تصنيف السطر —</option>';
    foreach ($classifications as $c) {
        $sel = ($c === $cls) ? ' selected' : '';
        $clsopts .= '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . '</option>';
    }
    return '<div class="proc-line form-grid proc-req-line">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item" aria-label="الصنفُ من كتالوج الأصناف">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" aria-label="اسمُ الصنفِ المطلوب" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" aria-label="الكميةُ المطلوبةُ من الصنف" value="' . $qty . '"></div>'
        . '<div class="form-group"><label>تصنيف السطر</label><select name="line_class[]" aria-label="التصنيفُ التشغيليُّ لسطرِ الطلب">' . $clsopts . '</select></div>'
        . '<div class="form-group"><label>ملاحظة</label><input type="text" name="line_note[]" aria-label="ملاحظةُ سطرِ الطلب" value="' . $note . '"></div>'
        . '<div class="form-group"><button type="button" class="btn-secondary removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-requests-main ems-unified-page-shell">
    <?php
    $header_title = 'طلبات الشراء التشغيلية';
    $header_icon  = 'fa fa-file-lines';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا طلباتِ شراءٍ مطابقةً للفلاترِ الحالية',
        'أضف طلبًا جديدًا من رأسِ الشاشة، أو ولّد الاحتياجَ آليًّا من الصيانةِ وحدودِ إعادة الطلب');
    ?>

    <?php proc_msg_banner(); ?>

    <?php
    /* ── INJ-0556 · فلترُ الفترةِ والإدارةِ الطالبةِ من الخادم ─────────────────── */
    $__rqFrom = isset($_GET['from']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['from']) ? $_GET['from'] : '';
    $__rqTo   = isset($_GET['to'])   && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $_GET['to'])   ? $_GET['to']   : '';
    $__rqDept = isset($_GET['dept']) ? trim((string) $_GET['dept']) : '';
    $__rqDepts = array();
    $__dr = $conn->prepare("SELECT DISTINCT requesting_dept FROM proc_request
                             WHERE company_id = ? AND requesting_dept IS NOT NULL AND requesting_dept <> ''
                             ORDER BY requesting_dept");
    $__dr->bind_param('i', $company_id);
    $__dr->execute();
    $__dres = $__dr->get_result();
    while ($__dx = $__dres->fetch_row()) { $__rqDepts[] = (string) $__dx[0]; }
    $__dr->close();
    if ($__rqDept !== '' && !in_array($__rqDept, $__rqDepts, true)) { $__rqDept = ''; }
    ?>
    <form method="get" class="filter" data-ems-period="1">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-calendar-days"></i></span> فترةُ الإنشاءِ والإدارةُ الطالبة</div>
        <div class="filter-body">
            <div class="filter-field"><label for="rqFrom">من تاريخ</label>
                <input type="date" id="rqFrom" name="from" class="form-control" value="<?php echo htmlspecialchars($__rqFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="filter-field"><label for="rqTo">إلى تاريخ</label>
                <input type="date" id="rqTo" name="to" class="form-control" value="<?php echo htmlspecialchars($__rqTo, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <div class="filter-field"><label for="rqDept">الإدارة الطالبة</label>
                <select id="rqDept" name="dept" class="form-control">
                    <option value="">— كلُّ الإدارات —</option>
                    <?php foreach ($__rqDepts as $__d): ?>
                    <option value="<?php echo htmlspecialchars($__d, ENT_QUOTES, 'UTF-8'); ?>"<?php
                        echo ($__d === $__rqDept ? ' selected' : ''); ?>><?php
                        echo htmlspecialchars($__d, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="filter-actions"><button type="submit" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button></div>
        </div>
    </form>

    <?php if ($can_add): ?>
    <form method="post" class="proc-req-genform">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_needs">
        <button type="submit" class="add-btn proc-req-genbtn"
                title="جسر الصيانة + كناس حدود الطلب — بعطالة فلا ازدواج">
            <i class="fas fa-bolt"></i> توليد الاحتياج الآن (صيانة + حدود الطلب)</button>
    </form>
    <?php endif; ?>

    <?php
    // E-17: قدومٌ من زرِّ النقص — تلميحُ التعبئة بمرجع الأمر وصنفه
    if (isset($_GET['prefill_item'])):
        $pfItem = proc_gate(false)->selectOne('proc_item', array('columns' => array('name'),
            'where' => array('id' => intval($_GET['prefill_item']))));
    ?>
    <div class="alert alert-info">
        <i class="fa fa-cart-plus"></i> طلبُ شراءٍ من زرِّ النقص —
        الصنف: <strong><?php echo htmlspecialchars((string)($pfItem['name'] ?? ('#' . intval($_GET['prefill_item'])))); ?></strong>
        · المصدر: <strong><?php echo htmlspecialchars((string)($_GET['need_source'] ?? '')); ?></strong>
        · مرجعُ الأمر: <strong><?php echo htmlspecialchars((string)($_GET['source_ref'] ?? '')); ?></strong>
        — عبّئ النموذجَ بها.
    </div>
    <?php endif; ?>

    <form id="procForm" action="requests_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <?= csrf_field() ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل طلب شراء' : 'طلب شراء جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="req_need_source">مصدر الاحتياج <span class="required">*</span></label>
                        <select name="need_source" id="req_need_source" required>
                            <?php foreach ($need_sources as $s): $sel = ($edit && $edit['need_source'] === $s) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="req_source_ref">مرجع المصدر (خطة/أمر/نقطة طلب)</label>
                        <input type="text" name="source_ref" id="req_source_ref" value="<?php echo $edit ? htmlspecialchars((string)$edit['source_ref']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="req_op_class">التصنيف التشغيلي <span class="required">*</span></label>
                        <select name="op_classification" id="req_op_class" required>
                            <?php foreach ($classifications as $c): $sel = ($edit && $edit['op_classification'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="req_dept">الإدارة الطالبة</label>
                        <input type="text" name="requesting_dept" id="req_dept" value="<?php echo $edit ? htmlspecialchars((string)$edit['requesting_dept']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="req_equipment">المعدة</label>
                        <select name="equipment_id" id="req_equipment"><?php echo proc_equipment_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['equipment_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="req_project">المشروع</label>
                        <select name="project_id" id="req_project"><?php echo proc_project_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['project_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="req_priority">الأولوية</label>
                        <select name="priority" id="req_priority">
                            <?php foreach ($priorities as $p): $sel = ($edit && $edit['priority'] === $p) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="req_state">حالة الطلب</label>
                        <select name="state" id="req_state">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php /* INJ-0089: الحقلُ لمن يملك الاعتمادَ المالي وحدَه — والطالبُ
                             يرى حالتَه قراءةً لا قائمةً يختار منها. */ ?>
                    <?php if (ems_proc_may_finance_approve($conn, $current_user_id)): ?>
                    <div class="form-group">
                        <label for="req_fin_state">حالة الاعتماد المالي</label>
                        <select name="fin_approval_state" id="req_fin_state">
                            <?php foreach ($fin_states as $fs): $sel = ($edit && $edit['fin_approval_state'] === $fs) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($fs); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($fs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>حالة الاعتماد المالي</label>
                        <div class="ems-readonly-value">
                            <?php echo htmlspecialchars($edit ? (string) $edit['fin_approval_state'] : 'بانتظار'); ?>
                            <small class="text-muted">— يضبطها الاعتمادُ الماليُّ لا مُقدِّمُ الطلب</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group proc-req-full">
                        <label for="req_notes">ملاحظات</label>
                        <input type="text" name="notes" id="req_notes" value="<?php echo $edit ? htmlspecialchars((string)$edit['notes']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="card-header"><h5><i class="fas fa-list"></i> الأصناف المطلوبة</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, $l); }
                    } else {
                        echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn proc-req-addline"><i class="fas fa-plus"></i> إضافة سطر</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <a href="requests_proc.php" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables proc-req-table"
                   data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>مصدر الاحتياج</th><th>التصنيف التشغيلي</th><th>الأولوية</th>
                    <th>الحالة</th><th>الاعتماد المالي</th><th>عدد الأصناف</th><th>أُنشئ</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
                    <th class="ems-fn-th" data-fn="1">الإدارة الطالبة</th>
                    <th class="ems-fn-th" data-fn="1">المرجع</th>
                    <th class="ems-fn-th" data-fn="1">المعدة أو المشروع</th>
                    <th class="ems-fn-th" data-fn="1">رقم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">اسم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">الكمية</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة</th>
                    <th class="ems-fn-th" data-fn="1">القيمة التقديرية</th>
                    <th class="ems-fn-th" data-fn="1">بند الموازنة</th>
                    <th class="ems-fn-th" data-fn="1">المتاح في الموازنة</th>
                    <th class="ems-fn-th" data-fn="1">قدّمه</th>
                    <th class="ems-fn-th none" data-fn="1">اعتماد الإدارة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // ترطيب ثنائي: الطلبات ثم عدّ سطورها بجلبٍ واحد
                    $gv = proc_gate($is_super_admin);
                    /* INJ-0556: كان الترشيحُ بحثًا نصيًّا عامًّا في المتصفحِ على
                       الصفحةِ المُحمَّلةِ وحدَها — فما لم يُحمَّل لا يُرشَّح. صار مدى
                       التاريخِ والإدارةُ الطالبةُ في الاستعلامِ نفسِه، والعمودان
                       `created_at` و`requesting_dept` موجودان أصلًا في `proc_request`. */
                    $__reqWhere = array();
                    if ($__rqFrom !== '') { $__reqWhere[] = "created_at >= '" . $conn->real_escape_string($__rqFrom) . " 00:00:00'"; }
                    if ($__rqTo   !== '') { $__reqWhere[] = "created_at <= '" . $conn->real_escape_string($__rqTo)   . " 23:59:59'"; }
                    if ($__rqDept !== '') { $__reqWhere[] = "requesting_dept = '" . $conn->real_escape_string($__rqDept) . "'"; }
                    $__reqOpts = array(
                        'columns' => array('id', 'code', 'need_source', 'op_classification', 'priority', 'state', 'fin_approval_state', 'created_at'),
                        'orderBy' => 'id DESC',
                    );
                    if ($__reqWhere) { $__reqOpts['whereRaw'] = implode(' AND ', $__reqWhere); }
                    $request_rows = $gv->select('proc_request', $__reqOpts);
                    $line_counts = array();
                    if (!empty($request_rows)) {
                        $rids = array();
                        foreach ($request_rows as $rr) { $rids[] = intval($rr['id']); }
                        foreach ($gv->select('proc_request_line', array(
                            'columns' => array('request_id'),
                            'whereRaw' => 'request_id IN (' . implode(',', $rids) . ')',
                        )) as $lr) {
                            $lrid = intval($lr['request_id']);
                            $line_counts[$lrid] = ($line_counts[$lrid] ?? 0) + 1;
                        }
                    }
                    { foreach ($request_rows as $row) {
                        $row['line_count'] = $line_counts[intval($row['id'])] ?? 0;
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['need_source']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['op_classification']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['priority']) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span>";
                        // E-21: الثلاثيةُ الموحّدة على «مقدَّم» — والإعادةُ والرفضُ بسبب
                        if ($can_edit && (string)$row['state'] === 'مقدَّم') {
                            $rid = intval($row['id']);
                            /* الحقلُ الحامي كان نصًّا حرفيًّا داخلَ سلسلةِ PHP («<?=» لا يُنفَّذ
                               داخلَ نصٍّ) — فالنماذجُ الثلاثةُ كانت تُرسَل بلا رمزِ حماية.
                               والنداءُ الآنَ مُوصولٌ بالضمِّ فيخرج حقلًا حقيقيًّا. */
                            echo "<div class='proc-req-decide'>"
                               . "<form method='post' class='proc-req-decide-form'>"
                               . csrf_field()
                               . "<input type='hidden' name='action' value='e21_decide'>"
                               . "<input type='hidden' name='request_id' value='{$rid}'>"
                               . "<input type='hidden' name='decision' value='approve'>"
                               . "<button type='submit' class='btn-primary' title='اعتماد'>✓</button></form>"
                               . "<form method='post' class='proc-req-decide-form proc-req-decide-reason'>"
                               . csrf_field()
                               . "<input type='hidden' name='action' value='e21_decide'>"
                               . "<input type='hidden' name='request_id' value='{$rid}'>"
                               . "<input type='hidden' name='decision' value='return'>"
                               . "<input type='text' name='reason' placeholder='سببُ الإعادة *' required class='proc-req-reason'>"
                               . "<button type='submit' class='btn-primary' title='إعادةٌ للاستكمال'>↩</button></form>"
                               . "<form method='post' class='proc-req-decide-form proc-req-decide-reason'>"
                               . csrf_field()
                               . "<input type='hidden' name='action' value='e21_decide'>"
                               . "<input type='hidden' name='request_id' value='{$rid}'>"
                               . "<input type='hidden' name='decision' value='reject'>"
                               . "<input type='text' name='reason' placeholder='سببُ الرفض *' required class='proc-req-reason'>"
                               . "<button type='submit' class='btn-primary' title='رفض'>✗</button></form></div>";
                        }
                        echo "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['fin_approval_state']) . "</td>";
                        echo "<td>" . intval($row['line_count']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['created_at']) . "</td>";
                        echo "</tr>";
                    } }
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
        // UXW-01 ⑤: التهيئةُ المحليةُ حُذفت — المكوّنُ المركزيُّ (ui-unification.js)
        // يلتقط الجدولَ آليًّا، والسلوكُ محفوظٌ بسماتِ data-scroll-x و data-state-save.
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () { $('#procForm').toggleClass('allforms-visible'); });
        }

        // إضافة سطر من القالب
        $('#addLine').on('click', function () {
            var tpl = document.getElementById('lineTemplate');
            var clone = document.importNode(tpl.content, true);
            document.getElementById('linesBody').appendChild(clone);
        });

        // حذف سطر
        $(document).on('click', '.removeLine', function () {
            var rows = $('#linesBody .proc-line');
            if (rows.length > 1) { $(this).closest('.proc-line').remove(); }
            else { $(this).closest('.proc-line').find('input,select').val(''); }
        });

        // عند اختيار صنف من الكتالوج: انسخ اسمه إلى اسم الصنف إن كان فارغاً
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                // أزل بادئة الكود إن وُجدت (code — name)
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
    });
})();
</script>

<style>
    /* UXW-01 ①②: أصنافٌ محلَّ الأنماطِ الموضعيةِ — واللونُ برمزِ اللوحة */
    .proc-req-line { align-items: end; margin-bottom: 8px; }
    .proc-req-genform { margin-bottom: 12px; }
    .proc-req-genbtn { background: var(--c-166534); }
    .proc-req-full { grid-column: 1 / -1; }
    .proc-req-addline { margin-top: 6px; }
    .proc-req-table { width: 100%; }
    .proc-req-decide { display: flex; gap: 3px; margin-top: 4px; flex-wrap: wrap; }
    .proc-req-decide-form { display: inline; }
    .proc-req-decide-reason { display: inline-flex; gap: 2px; }
    .proc-req-reason { width: 90px; }
</style>
</body>
</html>
