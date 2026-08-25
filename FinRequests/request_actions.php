<?php
/**
 * بوابة الطلب المالي D05 — معالج الأفعال (POST + إعادة توجيه، نمط كرت المعدة)
 * الأفعال: create/update_draft/attach_doc/submit/withdraw/recommend/dept_approve/
 *          return_request/reject/acct_forward — كلها عبر بوابة العزل، وقرارات
 *          الحالة عبر محرّك StateMachine بأدوار صفّ التوجيه، والسجل إلحاقي.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
// (الشاشةُ الأمُّ صارت `request_form.php` بعدَ دمجِ `my_requests.php` فيها — 2026-08-21)
ems_guard_handler($conn, 'FinRequests/request_form.php', 'view');

require_once __DIR__ . '/_finreq_helpers.php';

$role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin request actions super') : ems_tenant_db();

function finreq_redirect($to, $msg)
{
    ems_flash_set($msg);
    header('Location: ' . $to);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finreq_redirect('my_requests.php', '❌ طريقة الطلب غير صحيحة');
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$back = isset($_POST['back']) && in_array($_POST['back'], array('request_form.php', 'my_requests.php', 'dept_inbox.php', 'accountant_desk.php', 'finance_gateway.php', 'request_view.php'), true)
    ? $_POST['back'] : 'my_requests.php';

$S = function ($k) { $v = trim((string)($_POST[$k] ?? '')); return $v; };
$Dn = function ($k) { $v = trim((string)($_POST[$k] ?? '')); return $v === '' ? null : $v; };
$In = function ($k) { $v = trim((string)($_POST[$k] ?? '')); return $v === '' ? null : intval($v); };

// ═══ 1) إنشاء/تحديث مسودة — قالب النموذج الموحّد (§5) ═══
if ($action === 'create' || $action === 'update_draft') {
    $source_module = $S('source_module');
    $routing = finreq_routing_row($gate, $source_module);
    if (!$routing || intval($routing['is_active']) !== 1) {
        finreq_redirect('request_form.php', '❌ الإدارة غير مفعلة في بوابة الطلبات بعد');
    }
    if (!finreq_role_is($routing, $role, 'requester')) {
        finreq_redirect('request_form.php', '❌ دورك ليس من أدوار الإنشاء لهذه الإدارة');
    }
    $cat = finreq_catalog();
    $type = $S('request_type');
    if (!isset($cat[$type]) || !$cat[$type]['active']) {
        finreq_redirect('request_form.php', '❌ نوع الطلب غير مفعل في هذه المرحلة');
    }
    $amount = floatval($S('amount'));
    $justification = $S('justification');
    $need_class = in_array($S('need_class'), array('planned', 'unplanned', 'urgent', 'emergency'), true) ? $S('need_class') : 'planned';
    // بوابة التبرير الصفرية (3.0): لا طلب بلا «لماذا» وتصنيف حاجة
    if ($justification === '' || $amount <= 0) {
        finreq_redirect('request_form.php', '❌ المبرر والمبلغ الموجب شرطا الحفظ (بوابة التبرير)');
    }
    $beneficiary_type = in_array($S('beneficiary_type'), array('supplier', 'employee', 'customer', 'internal', 'other'), true) ? $S('beneficiary_type') : 'other';

    $data = array(
        'request_type' => $type,
        'source_module' => $source_module,
        'requester_id' => $In('requester_id'),
        'beneficiary_type' => $beneficiary_type,
        'beneficiary_ref' => $In('beneficiary_ref'),
        'beneficiary_name' => $Dn('beneficiary_name'),
        'amount' => $amount,
        'currency' => $Dn('currency') ?: 'SDG',
        'payment_method' => in_array($S('payment_method'), array('cash', 'bank', 'transfer', 'cheque'), true) ? $S('payment_method') : null,
        'statement' => $Dn('statement'),
        'justification' => $justification,
        'source_ref' => $Dn('source_ref'),
        'project_id' => $In('project_id'),
        'equipment_id' => $In('equipment_id'),
        'contract_id' => $In('contract_id'),
        'cost_center' => $Dn('cost_center'),
        'needed_by' => $Dn('needed_by'),
        'priority' => in_array($S('priority'), array('normal', 'high', 'critical'), true) ? $S('priority') : 'normal',
        'need_class' => $need_class,
        'notes' => $Dn('notes'),
    );

    try {
        if ($action === 'create') {
            $data['request_no'] = finreq_next_no($gate);
            $data['created_by'] = $user_id;
            if ($data['requester_id'] === null) { $data['requester_id'] = $user_id; }
            $new_id = 0;
            $gate->runInTransaction(function ($g) use ($data, &$new_id, $user_id) {
                $new_id = intval($g->insert('fin_requests', $data));
                finreq_log($g, $new_id, 'create', 'إنشاء الطلب ' . $data['request_no'],
                    null, null, ($data['requester_id'] !== $user_id ? $data['requester_id'] : null));
            });
            finreq_redirect('request_form.php?id=' . $new_id, '✅ حفظ الطلب مسودة برقم ' . $data['request_no'] . ' — أرفق مستنداته ثم أرسله');
        } else {
            $id = intval($_POST['id'] ?? 0);
            $req = finreq_fetch($gate, $id);
            if (!$req) { finreq_redirect('my_requests.php', '❌ الطلب غير موجود'); }
            // أقفال المرحلة (§8.5): التحرير الكامل للمسودة والمُعاد فقط، ولمنشئه (أو السوبر)
            if (!in_array($req['state'], array('draft', 'returned'), true)) {
                finreq_redirect('request_view.php?id=' . $id, '❌ الطلب تجاوز مرحلة التحرير — التعديل بإعادة موثقة فقط');
            }
            if (!$is_super && intval($req['created_by']) !== $user_id) {
                finreq_redirect('my_requests.php', '❌ التحرير لمنشئ الطلب حصرا');
            }
            unset($data['request_type'], $data['source_module']); // النوع والإدارة لا يتبدلان بعد الميلاد
            $gate->runInTransaction(function ($g) use ($data, $id, $req) {
                $g->update('fin_requests', $data, array('id' => $id));
                finreq_log($g, $id, 'edit', 'تحديث بيانات ' . ($req['state'] === 'returned' ? 'الطلب المعاد' : 'المسودة'),
                    'amount=' . $req['amount'], 'amount=' . $data['amount']);
            });
            finreq_redirect('request_form.php?id=' . $id, '✅ حدثت بيانات الطلب');
        }
    } catch (\Throwable $t) {
        error_log('finreq create/update: ' . $t->getMessage());
        finreq_redirect('request_form.php', '❌ تعذر الحفظ: ' . $t->getMessage());
    }
}

// ═══ 2) إرفاق مستند (شرط العبور §3.1) ═══
if ($action === 'attach_doc') {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect('my_requests.php', '❌ الطلب غير موجود'); }
    if (!in_array($req['state'], array('draft', 'returned', 'under_review'), true)) {
        finreq_redirect('request_view.php?id=' . $id, '❌ لا إرفاق بعد الاعتماد الإداري');
    }
    $doc_types = finreq_doc_types();
    $doc_type = isset($doc_types[$S('doc_type')]) ? $S('doc_type') : 'other';
    $upload_err = null;
    $file_ref = finreq_upload('doc_file', $upload_err);
    if ($file_ref === null) {
        finreq_redirect('request_form.php?id=' . $id, '❌ ' . ($upload_err ?: 'تعذر رفع الملف (الأنواع: صور/PDF بحد 5MB)'));
    }
    try {
        $gate->runInTransaction(function ($g) use ($id, $doc_type, $file_ref, $user_id) {
            $g->insert('fin_request_documents', array(
                'request_id' => $id,
                'doc_type' => $doc_type,
                'file_ref' => $file_ref,
                'original_kind' => 'electronic',
                'uploaded_by' => $user_id,
            ));
            finreq_log($g, $id, 'attach', 'إرفاق مستند: ' . $doc_type . ' → ' . $file_ref);
        });
        finreq_redirect('request_form.php?id=' . $id, '✅ أرفق المستند');
    } catch (\Throwable $t) {
        error_log('finreq attach: ' . $t->getMessage());
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذر الإرفاق');
    }
}

// ═══ 3) بنود الطلب (شراء متعدد الأصناف §11.2) ═══
if ($action === 'add_line' || $action === 'delete_line') {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect('my_requests.php', '❌ الطلب غير موجود'); }
    if (!in_array($req['state'], array('draft', 'returned'), true)) {
        finreq_redirect('request_view.php?id=' . $id, '❌ البنود تحرر في المسودة/المعاد فقط');
    }
    try {
        if ($action === 'add_line') {
            $item = $S('item'); $qty = floatval($S('qty') ?: 1); $unit_price = floatval($S('unit_price'));
            if ($item === '' || $qty <= 0) { finreq_redirect('request_form.php?id=' . $id, '❌ بيان البند والكمية مطلوبان'); }
            $gate->runInTransaction(function ($g) use ($id, $item, $qty, $unit_price) {
                $g->insert('fin_request_lines', array(
                    'request_id' => $id, 'item' => $item, 'qty' => $qty,
                    'unit' => trim((string)($_POST['unit'] ?? '')) ?: null, 'unit_price' => $unit_price,
                    'note' => trim((string)($_POST['line_note'] ?? '')) ?: null,
                ));
                finreq_log($g, $id, 'edit', 'إضافة بند: ' . $item . ' ×' . $qty);
            });
            finreq_redirect('request_form.php?id=' . $id, '✅ أضيف البند');
        } else {
            $line_id = intval($_POST['line_id'] ?? 0);
            $gate->runInTransaction(function ($g) use ($id, $line_id) {
                $g->deleteChild('fin_request_lines', $line_id, 'fin_requests', $id, 'request_id', 'حذف بند مسودة D05');
                finreq_log($g, $id, 'edit', 'حذف البند #' . $line_id);
            });
            finreq_redirect('request_form.php?id=' . $id, '🗑️ حذف البند');
        }
    } catch (\Throwable $t) {
        error_log('finreq lines: ' . $t->getMessage());
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذرت العملية على البنود');
    }
}

// ═══ 4) انتقالات الدورة (المحرّك بأدوار التوجيه) ═══
$machine_actions = array(
    'submit' => 'submit', 'resubmit' => 'resubmit', 'withdraw' => null,
    'dept_approve' => 'dept_approve', 'return_request' => null, 'reject' => 'reject_review',
);
if (array_key_exists($action, $machine_actions)) {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect($back, '❌ الطلب غير موجود'); }
    $routing = finreq_routing_row($gate, strval($req['source_module']));
    if (!$routing) { finreq_redirect($back, '❌ لا صف توجيه لإدارة الطلب'); }
    $reason = $S('reason');

    // تخصيص فعل المحرّك بحسب الحالة الراهنة
    $m_action = $machine_actions[$action];
    if ($action === 'withdraw') {
        $m_action = ($req['state'] === 'draft') ? 'withdraw_draft' : 'withdraw_review';
        if (!$is_super && intval($req['created_by']) !== $user_id) {
            finreq_redirect($back, '❌ السحب لمنشئ الطلب حصرا وقبل الاعتماد الإداري');
        }
        if ($reason === '') { finreq_redirect($back, '❌ سبب السحب إلزامي'); }
    }
    if ($action === 'return_request') {
        $m_action = ($req['state'] === 'pending_approval') ? 'return_acct' : 'return_review';
        if ($reason === '') { finreq_redirect($back, '❌ سبب الإعادة إلزامي — يقرؤه المنشئ كاملا'); }
    }
    if ($action === 'reject') {
        $classes = finreq_rejection_classes();
        $rclass = isset($classes[$S('rejection_class')]) ? $S('rejection_class') : '';
        if ($reason === '' || $rclass === '') {
            finreq_redirect($back, '❌ الرفض يستلزم تصنيفا وسببا مسجلين (§8.5)');
        }
    }
    // M-45: **من أنشأ لا يعتمد** — قيدًا عامًّا في الخادم لا بإخفاء زر
    if (in_array($action, array('dept_approve', 'approve', 'acct_approve', 'finance_approve'), true)) {
        require_once __DIR__ . '/../includes/self_approval_guard.php';
        $creator = intval($req['requester_id'] ?? 0) ?: intval($req['created_by'] ?? 0);
        $blocked = ems_no_self_approval($conn, $creator, $user_id,
            'الطلب المالي ' . strval($req['request_no'] ?? ('#' . $id)), $company_id ?? 0);
        if ($blocked !== null) { finreq_redirect($back, '❌ ' . $blocked['reason']); }
    }
    if ($action === 'submit' || $action === 'resubmit') {
        // بوابة العبور الصفرية: المستند شرطٌ صارمٌ من اليوم الأول
        if (!finreq_docs_satisfied($gate, $req)) {
            $cat = finreq_catalog();
            finreq_redirect('request_form.php?id=' . $id,
                '❌ المستند شرط العبور: أرفق ' . $cat[$req['request_type']]['docs_label'] . ' قبل الإرسال');
        }
        if (strval($req['justification'] ?? '') === '') {
            finreq_redirect('request_form.php?id=' . $id, '❌ بوابة التبرير: لا طلب بلا «لماذا»');
        }
    }

    try {
        $machine = finreq_machine($conn, $routing);
        $result = $machine->apply($id, $m_action, $user_id, array('note' => $reason !== '' ? $reason : null));

        // السجل الإلحاقي + الحقول المرافقة + ختم SLA للمرحلة الجديدة (قاعدة الساعة §8.1)
        // + نشر حقيقة الانتقال على الجذر المحايد (§9.3 — بعطالة transition_id الحتمية)
        $gate->runInTransaction(function ($g) use ($conn, $action, $id, $req, $reason, $S, $user_id, $result) {
            if ($action === 'submit' || $action === 'resubmit') {
                finreq_log($g, $id, $action === 'submit' ? 'submit' : 'resubmit',
                    $reason !== '' ? $reason : 'إرسال الطلب للمراجعة', $result['from'], $result['to']);
                finreq_stamp_sla($g, $id, 'dept_review', strval($req['need_class']));
                finreq_publish_fact($conn, $req, 'request.submitted', 'fact:st:' . intval($result['transition_id']),
                    array('resubmit' => $action === 'resubmit'));
                finreq_notify($g, 'dept_manager',
                    'طلب جديد للمراجعة: ' . $req['request_no'] . ' (' . $req['source_module'] . ')',
                    'FinRequests/dept_inbox.php');
                // كشف التكرار التحذيري (§8.4) بعد الإرسال
                $dup = finreq_duplicate_check($g, $req);
                if ($dup) {
                    $g->update('fin_requests', array('duplicate_flag' => 1), array('id' => $id));
                    finreq_log($g, $id, 'duplicate_check',
                        'تحذير تكرار: يشابه ' . $dup['request_no'] . ' (النوع والمستفيد والمبلغ ±5% خلال 7 أيام)');
                }
            } elseif ($action === 'dept_approve') {
                $g->update('fin_requests', array('decided_by' => $user_id), array('id' => $id));
                finreq_log($g, $id, 'dept_approve', $reason !== '' ? $reason : 'اعتماد إداري — فتح باب المالية', $result['from'], $result['to']);
                finreq_stamp_sla($g, $id, 'acct_review', strval($req['need_class']));
                finreq_publish_fact($conn, $req, 'request.dept_approved', 'fact:st:' . intval($result['transition_id']));
                finreq_notify($g, 'dept_accountant',
                    'معتمد إداريا بانتظار ولادة حدثه: ' . $req['request_no'],
                    'FinRequests/accountant_desk.php');
            } elseif ($action === 'return_request') {
                finreq_log($g, $id, 'return', $reason, $result['from'], $result['to']);
            } elseif ($action === 'reject') {
                $g->update('fin_requests', array(
                    'rejection_class' => $S('rejection_class'),
                    'decided_by' => $user_id,
                    'decision_ref' => trim((string)($_POST['decision_ref'] ?? '')) ?: null,
                ), array('id' => $id));
                finreq_log($g, $id, 'reject', $reason, $result['from'], $result['to']);
            } elseif ($action === 'withdraw') {
                finreq_log($g, $id, 'withdraw', $reason, $result['from'], $result['to']);
            }
        });

        // BR-CEO-05 (M-00): بعد نجاح الإرسال يُقاس حدا DEC-01 ③ — والتجاوز
        // يرفع صفًّا آليًّا لشاشة الاعتماد الأعلى (fail-soft: لا يعطّل الإرسال)
        if ($action === 'submit' || $action === 'resubmit') {
            $fresh = finreq_fetch($gate, $id);
            if ($fresh) { finreq_gm_escalate($conn, $gate, $fresh); }
        }

        $msgs = array(
            'submit' => '✅ أرسل الطلب للمراجعة الإدارية',
            'resubmit' => '✅ استكمل الطلب وأعيد إرساله بالرقم نفسه',
            'dept_approve' => '✅ اعتمد إداريا — الطلب لدى محاسب الإدارة',
            'return_request' => '↩️ أعيد الطلب لمنشئه بالسبب المسجل',
            'reject' => '⛔ رفض الطلب بسبب موثق — نهاية مسجلة لا حذف',
            'withdraw' => '🗂️ سحب الطلب بسبب مسجل ويبقى في السجل',
        );
        finreq_redirect($back . '?id=' . $id, $msgs[$action]);
    } catch (\Throwable $t) {
        error_log('finreq transition ' . $action . ': ' . $t->getMessage());
        finreq_redirect($back . ($id ? '?id=' . $id : ''), '❌ ' . $t->getMessage());
    }
}

// ═══ 5) مكتب المحاسب: ضبط التصنيف والأبعاد وولادة الحدث (§3.2 — نقطة الالتحام) ═══
if ($action === 'acct_forward') {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect('accountant_desk.php', '❌ الطلب غير موجود'); }
    if (strval($req['state']) !== 'pending_approval') {
        finreq_redirect('accountant_desk.php', '❌ لا يعبر المالية إلا طلب معتمد إداريا (قاعدة العبور)');
    }
    if (!empty($req['event_id'])) {
        finreq_redirect('request_view.php?id=' . $id, 'ℹ️ الحدث مولود مسبقا لهذا الطلب (عطالة الناشر)');
    }
    if (!$is_super && $role !== '18' && $role !== '17') {
        finreq_redirect('accountant_desk.php', '❌ ولادة الحدث لمحاسب الإدارة (18) حصرا');
    }
    // قاعدة الحساب (§5): التصنيف المحاسبي شأن المحاسب لا الطالب
    $account_id = intval($_POST['account_id'] ?? 0);
    if ($account_id <= 0) {
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ قاعدة الحساب: حدد الحساب من الدليل قبل الولادة');
    }
    $acct = $gate->selectOne('fin_chart_of_accounts', array('columns' => array('id'), 'where' => array('id' => $account_id)));
    if (!$acct) {
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ الحساب غير موجود في دليل شركتك');
    }
    // ── بوابة الميزانية (§3.4-②): بندٌ من موازنة الشركة أو «خارج الموازنة» موثَّقًا؛
    //    وتجاوز المتبقي لا يعبر إلا باستثناءٍ معتمدٍ (§8.3) — الفحص قبل الولادة. ──
    $budget_line_id = $In('budget_line_id');
    $budget_line = null;
    if ($budget_line_id !== null && $budget_line_id > 0) {
        $budget_line = $gate->selectOne('fin_budget_lines', array('where' => array('id' => $budget_line_id)));
        if (!$budget_line) {
            finreq_redirect('accountant_desk.php?id=' . $id, '❌ بند الموازنة غير موجود في موازنة شركتك');
        }
        $remaining = round(floatval($budget_line['planned_amount']) - floatval($budget_line['actual_amount']), 2);
        if (floatval($req['amount']) > $remaining && intval($req['is_exception']) !== 1) {
            finreq_log($gate, $id, 'note', 'بوابة الميزانية: ردت الولادة — المبلغ '
                . number_format(floatval($req['amount']), 2) . ' يتجاوز متبقي بند «' . $budget_line['category'] . '» ('
                . number_format($remaining, 2) . ') ولا استثناء معتمدا');
            finreq_redirect('accountant_desk.php?id=' . $id,
                '⛔ بوابة الميزانية: المبلغ يتجاوز متبقي البند (' . number_format($remaining, 2)
                . ') — التجاوز يستلزم استثناء معتمدا من المدير المالي (§8.3)');
        }
    }
    try {
        $ev_result = null;
        $gate->runInTransaction(function ($g) use ($conn, $gate, $id, $account_id, $user_id, &$ev_result, $S, $In, $Dn, $budget_line) {
            // ضبط المحاسب: الحساب والأبعاد وبند الموازنة (soft)
            $refine = array('account_id' => $account_id);
            if ($budget_line) { $refine['budget_line_id'] = intval($budget_line['id']); }
            if ($Dn('cost_center') !== null) { $refine['cost_center'] = $Dn('cost_center'); }
            if ($In('project_id') !== null) { $refine['project_id'] = $In('project_id'); }
            if ($In('equipment_id') !== null) { $refine['equipment_id'] = $In('equipment_id'); }
            $g->update('fin_requests', $refine, array('id' => $id));
            finreq_log($g, $id, 'acct_review', 'ضبط التصنيف والأبعاد (حساب #' . $account_id . ')');

            $fresh = $g->selectOne('fin_requests', array('where' => array('id' => $id)));
            $ev_result = finreq_birth_event($conn, $g, $fresh, $user_id);
            finreq_log($g, $id, 'publish',
                'ولادة الحدث المالي ' . $ev_result['event_no'] . ' (id=' . $ev_result['id'] . ') بمرجع الطلب — دورة D04 بدأت',
                null, 'event_id=' . $ev_result['id']);
            finreq_stamp_sla($g, $id, 'finance', strval($fresh['need_class']));

            // استهلاك بند الموازنة ذرّيًّا مع الولادة + رابط الأثر (fin_event_links)
            if ($budget_line) {
                $line_now = $g->selectOne('fin_budget_lines', array('where' => array('id' => intval($budget_line['id']))));
                $new_actual = round(floatval($line_now['actual_amount']) + floatval($fresh['amount']), 2);
                $g->update('fin_budget_lines', array('actual_amount' => $new_actual), array('id' => intval($line_now['id'])));
                $g->insert('fin_event_links', array(
                    'parent_kind' => 'request', 'parent_ref' => $id,
                    'event_id' => intval($ev_result['id']),
                    'effect_type' => 'budget_consumption',
                    'target_table' => 'fin_budget_lines', 'target_id' => intval($line_now['id']),
                ));
                finreq_log($g, $id, 'note', 'خصم من بند «' . $line_now['category'] . '»',
                    'actual=' . $line_now['actual_amount'], 'actual=' . $new_actual);
            } else {
                finreq_log($g, $id, 'note', 'بوابة الميزانية: ولد الحدث خارج بنود الموازنة — موثق بقرار المحاسب (§3.0)');
            }
            finreq_notify($g, 'finance_manager',
                'ولد الحدث ' . $ev_result['event_no'] . ' من الطلب ' . $fresh['request_no'],
                'FinRequests/finance_gateway.php');
        });
        finreq_redirect('accountant_desk.php', '✅ ولد الحدث المالي ' . ($ev_result ? $ev_result['event_no'] : '') . ' بمرجع الطلب — يتابع دورته في D04 وتشتق حالة الطلب منه');
    } catch (\Throwable $t) {
        error_log('finreq acct_forward: ' . $t->getMessage());
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ تعذرت الولادة: ' . $t->getMessage());
    }
}

// ═══ 6) حالات الإيقاف (§8.4): إلغاء / تعليق / استئناف / دمج المكرّرات ═══
if (in_array($action, array('cancel', 'suspend', 'resume', 'merge'), true)) {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect($back, '❌ الطلب غير موجود'); }
    $routing = finreq_routing_row($gate, strval($req['source_module']));
    if (!$routing) { finreq_redirect($back, '❌ لا صف توجيه لإدارة الطلب'); }
    $reason = $S('reason');
    if ($reason === '' && $action !== 'resume') {
        finreq_redirect($back, '❌ السبب إلزامي لحالات الإيقاف (§8.4)');
    }

    try {
        $machine = finreq_machine($conn, $routing);

        if ($action === 'cancel') {
            // مدير الإدارة قبل وصول المالية (لا حدث)، والمدير المالي بعده
            if (!empty($req['event_id']) && !$is_super && $role !== '17') {
                finreq_redirect($back, '❌ بعد ولادة الحدث الإلغاء للمدير المالي حصرا — مع معالجة الأثر في D04');
            }
            // قاعدة المسار المركّب (§6.2): لا يضيع فرعٌ بلا أصلٍ يُسأل عنه —
            // أصلٌ له فروعٌ حيّة لا يُلغى قبل حسم فروعه
            if (finreq_has_live_children($gate, $id)) {
                finreq_redirect($back, '❌ للطلب فروع حية (مسار مركب) — احسم الفروع أولا ثم ألغ الأصل');
            }
            $result = $machine->apply($id, 'cancel_pending', $user_id, array('note' => $reason));
            $gate->runInTransaction(function ($g) use ($id, $reason, $result, $req) {
                finreq_log($g, $id, 'cancel', $reason
                    . (!empty($req['event_id']) ? ' — قاعدة الأثر الرجعي: عالج حدث D04 #' . intval($req['event_id']) . ' (عكس قيد/تسوية)' : ''),
                    $result['from'], $result['to']);
            });
            finreq_redirect($back, '⛔ ألغي الطلب بسبب موثق' . (!empty($req['event_id']) ? ' — لا تنس معالجة أثره في D04' : ''));
        }

        if ($action === 'suspend') {
            $m_action = ($req['state'] === 'pending_approval') ? 'suspend_pending' : 'suspend_review';
            $result = $machine->apply($id, $m_action, $user_id, array('note' => $reason));
            $gate->runInTransaction(function ($g) use ($id, $reason, $result) {
                finreq_log($g, $id, 'suspend', $reason, $result['from'], $result['to']);
            });
            finreq_redirect($back, '⏸️ علق الطلب بقرار موثق — يستأنف بمثله');
        }

        if ($action === 'resume') {
            // الاستئناف إلى مرحلته السابقة المدوَّنة في قيد التعليق (old_value)
            $last = $gate->select('fin_request_events', array(
                'columns' => array('old_value'),
                'where' => array('request_id' => $id, 'event_type' => 'suspend'),
                'orderBy' => 'id DESC', 'limit' => 1,
            ));
            $target = ($last && $last[0]['old_value'] === 'pending_approval') ? 'resume_pending' : 'resume_review';
            $result = $machine->apply($id, $target, $user_id, array('note' => $reason !== '' ? $reason : 'استئناف'));
            $gate->runInTransaction(function ($g) use ($id, $reason, $result) {
                finreq_log($g, $id, 'resume', $reason !== '' ? $reason : 'استئناف بقرار موثق', $result['from'], $result['to']);
            });
            finreq_redirect($back, '▶️ استؤنف الطلب إلى مرحلته السابقة');
        }

        if ($action === 'merge') {
            // دمج مكرّرٍ في طلبٍ قائمٍ بمرجع merged_into_id (قرار محاسب الإدارة)
            $target_no = $S('merge_into_no');
            $target = $gate->selectOne('fin_requests', array('where' => array('request_no' => $target_no)));
            if (!$target || intval($target['id']) === $id) {
                finreq_redirect($back, '❌ حدد رقم الطلب الأصل الصحيح للدمج');
            }
            if (!empty($req['event_id'])) {
                finreq_redirect($back, '❌ لا يدمج طلب ولد حدثه — عالج بالإلغاء ومعالجة الأثر');
            }
            $m_action = ($req['state'] === 'pending_approval') ? 'merge_pending' : 'merge_review';
            $result = $machine->apply($id, $m_action, $user_id, array('note' => 'دمج في ' . $target['request_no']));
            $gate->runInTransaction(function ($g) use ($id, $target, $reason, $result) {
                $g->update('fin_requests', array('merged_into_id' => intval($target['id'])), array('id' => $id));
                finreq_log($g, $id, 'merge', $reason . ' — دمج في ' . $target['request_no'], $result['from'], $result['to']);
                finreq_log($g, intval($target['id']), 'note', 'دمج فيه الطلب المكرر #' . $id);
            });
            finreq_redirect($back, '🔗 دمج المكرر في ' . $target['request_no'] . ' وأقفل بمرجعه');
        }
    } catch (\Throwable $t) {
        error_log('finreq halt ' . $action . ': ' . $t->getMessage());
        finreq_redirect($back, '❌ ' . $t->getMessage());
    }
}

// ═══ 6ب) المسار المركّب (§6.2): تفريع دفعةٍ عن أصلٍ معتمدٍ إداريًّا ═══
//        «طلب شراءٍ واحد يتفرّع: الشراء + دفعة مقدّمة + دفعة نهائية» —
//        التفريع جدولة أداءٍ ماليةٌ فيملكه محاسب الإدارة (18) والمدير المالي (17)؛
//        الفرع يرث مستندات أصله وبواباته الإدارية المستوفاة (لا قفز فوق المصدر —
//        الأصل عبر بوابتي التبرير والاعتماد) ويولد «بانتظار الاعتماد» ليلد حدثه
//        من مكتب المحاسب كأي طلب. سقف النسب: مجموع الفروع لا يتجاوز مبلغ الأصل.
if ($action === 'split_request') {
    if (!$is_super && $role !== '17' && $role !== '18') {
        finreq_redirect($back, '❌ التفريع جدولة أداء مالية — لمحاسب الإدارة والمدير المالي');
    }
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect($back, '❌ الطلب غير موجود'); }
    if (!in_array($req['state'], array('pending_approval', 'approved', 'posted'), true)) {
        finreq_redirect('request_form.php?id=' . $id, '❌ التفريع لأصل معتمد إداريا فصاعدا (بواباته مستوفاة)');
    }
    if (!empty($req['parent_request_id'])) {
        finreq_redirect('request_form.php?id=' . $id, '❌ الفرع لا يتفرع — التفريع عن الأصل مباشرة (شجرة بمستوى واحد)');
    }
    $child_amount = round(floatval($S('child_amount')), 2);
    $child_label = $S('child_label');
    if ($child_amount <= 0 || $child_label === '') {
        finreq_redirect('request_form.php?id=' . $id, '❌ مبلغ الفرع الموجب ووصفه (مثل: دفعة مقدمة 30%) إلزاميان');
    }
    // سقف المجموع: فروعه القائمة (غير الملغاة/المرفوضة) + الجديد ≤ مبلغ الأصل
    $kids = finreq_children($gate, $id);
    $kids_sum = 0.0;
    foreach ($kids as $k) {
        if (!in_array($k['state'], array('rejected', 'withdrawn', 'cancelled', 'expired', 'merged'), true)) {
            $kids_sum += floatval($k['amount']);
        }
    }
    if ($kids_sum + $child_amount > floatval($req['amount']) + 0.01) {
        finreq_redirect('request_form.php?id=' . $id,
            '❌ سقف التفريع: مجموع الفروع (' . number_format($kids_sum + $child_amount, 2)
            . ') يتجاوز مبلغ الأصل (' . number_format(floatval($req['amount']), 2) . ')');
    }
    try {
        $child_id = 0;
        $child_no = finreq_next_no($gate);
        $gate->runInTransaction(function ($g) use ($conn, $req, $id, $child_no, $child_amount, $child_label, $user_id, &$child_id) {
            // الفرع: طلبٌ كاملٌ برقمه يرث هوية أصله التشغيلية ويولد بانتظار الاعتماد
            $child_id = intval($g->insert('fin_requests', array(
                'request_no' => $child_no,
                'parent_request_id' => $id,
                'request_type' => finreq_child_type($req['beneficiary_type']),
                'source_module' => strval($req['source_module']),
                'requester_id' => intval($req['requester_id'] ?? 0) ?: intval($req['created_by']),
                'beneficiary_type' => strval($req['beneficiary_type']),
                'beneficiary_ref' => $req['beneficiary_ref'] !== null ? intval($req['beneficiary_ref']) : null,
                'beneficiary_name' => $req['beneficiary_name'] !== null ? strval($req['beneficiary_name']) : null,
                'amount' => $child_amount,
                'currency' => strval($req['currency']),
                'payment_method' => $req['payment_method'] !== null ? strval($req['payment_method']) : null,
                'justification' => $child_label . ' — فرع من ' . $req['request_no'] . ' (مسار مركب §6.2)',
                'source_ref' => strval($req['request_no']),
                'project_id' => $req['project_id'] !== null ? intval($req['project_id']) : null,
                'equipment_id' => $req['equipment_id'] !== null ? intval($req['equipment_id']) : null,
                'contract_id' => $req['contract_id'] !== null ? intval($req['contract_id']) : null,
                'cost_center' => $req['cost_center'] !== null ? strval($req['cost_center']) : null,
                'need_class' => strval($req['need_class']),
                'state' => 'pending_approval',
                'created_by' => $user_id,
            )));
            // وراثة المستندات: نسخ صفوف مستندات الأصل (نفس الملفات — إثباتها إثباته)
            foreach ($g->select('fin_request_documents', array('where' => array('request_id' => $id))) as $doc) {
                $g->insert('fin_request_documents', array(
                    'request_id' => $child_id,
                    'doc_type' => strval($doc['doc_type']),
                    'file_ref' => strval($doc['file_ref']),
                    'original_kind' => strval($doc['original_kind']),
                    'uploaded_by' => intval($doc['uploaded_by'] ?? 0) ?: $user_id,
                ));
            }
            finreq_log($g, $child_id, 'create',
                'ولد فرعا من ' . $req['request_no'] . ' («' . $child_label . '») — بوابتا التبرير والاعتماد مستوفاتان في الأصل، ومستنداته موروثة');
            finreq_stamp_sla($g, $child_id, 'acct_review', strval($req['need_class']));
            finreq_log($g, $id, 'note', 'تفرع عنه ' . $child_no . ' («' . $child_label . '» بمبلغ ' . number_format($child_amount, 2) . ') — §6.2');
            finreq_publish_fact($conn, array_merge($req, array('id' => $child_id, 'request_no' => $child_no, 'amount' => $child_amount)),
                'request.submitted', 'fact:split:' . $child_id, array('split_of' => $req['request_no'], 'label' => $child_label));
            finreq_notify($g, 'dept_accountant', 'فرع جديد بانتظار ولادة حدثه: ' . $child_no . ' من ' . $req['request_no'], 'FinRequests/accountant_desk.php');
        });
        finreq_redirect('request_form.php?id=' . $id, '🌿 تفرع ' . $child_no . ' («' . $child_label . '») — لدى محاسب الإدارة لولادة حدثه');
    } catch (\Throwable $t) {
        error_log('finreq split: ' . $t->getMessage());
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذر التفريع: ' . $t->getMessage());
    }
}

// ═══ 7) الاستثناءات (§8.3): الطارئ يُطلب موثَّقًا ويعتمده المدير المالي حصرًا ═══
if ($action === 'exception_request') {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect($back, '❌ الطلب غير موجود'); }
    if (!in_array($req['state'], array('under_review', 'pending_approval'), true)) {
        finreq_redirect($back, '❌ الاستثناء لطلب في دورته النشطة فقط');
    }
    if (strval($req['need_class']) !== 'emergency') {
        finreq_redirect($back, '❌ التنفيذ المسبق للطارئ (emergency) حصرا — العاجل مساره مضغوط المدد لا مسقط البوابات');
    }
    if (intval($req['is_exception']) === 1) {
        finreq_redirect($back, 'ℹ️ الاستثناء معتمد مسبقا لهذا الطلب');
    }
    $routing = finreq_routing_row($gate, strval($req['source_module']));
    $is_owner = intval($req['created_by']) === $user_id;
    if (!$is_super && !$is_owner && !finreq_role_is($routing, $role, 'manager')) {
        finreq_redirect($back, '❌ طلب الاستثناء لصاحب الطلب أو مدير إدارته');
    }
    $reason = $S('reason');
    if ($reason === '') { finreq_redirect($back, '❌ مبرر الطارئ إلزامي — يقرؤه المدير المالي'); }
    try {
        $gate->runInTransaction(function ($g) use ($id, $reason, $req) {
            finreq_log($g, $id, 'exception_requested', 'طلب تنفيذ طارئ (§8.3): ' . $reason);
            finreq_notify($g, 'finance_manager',
                'طلب استثناء طارئ بانتظار قرارك: ' . $req['request_no'],
                'FinRequests/finance_gateway.php');
        });
        finreq_redirect($back . '?id=' . $id, '🚨 سجل طلب الطارئ — القرار للمدير المالي حصرا (§8.3)');
    } catch (\Throwable $t) {
        error_log('finreq exception_request: ' . $t->getMessage());
        finreq_redirect($back, '❌ تعذر تسجيل طلب الاستثناء');
    }
}

if ($action === 'exception_decide') {
    // الشرط الأول من ثلاثية الطارئ: اعتمادٌ موثَّقٌ من المدير المالي حصرًا
    if (!$is_super && $role !== '17') {
        finreq_redirect('finance_gateway.php', '❌ قرار الاستثناء للمدير المالي (17) حصرا — §8.3');
    }
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect('finance_gateway.php', '❌ الطلب غير موجود'); }
    if (intval($req['is_exception']) === 1) {
        finreq_redirect('finance_gateway.php', 'ℹ️ الاستثناء معتمد مسبقا');
    }
    $decision = $S('decision') === 'approve' ? 'approve' : 'deny';
    $reason = $S('reason');
    if ($reason === '') { finreq_redirect('finance_gateway.php', '❌ سبب القرار إلزامي للتدقيق'); }
    try {
        $gate->runInTransaction(function ($g) use ($conn, $id, $req, $decision, $reason, $user_id) {
            if ($decision === 'approve') {
                $g->update('fin_requests', array(
                    'is_exception' => 1,
                    'exception_type' => 'emergency_execute',
                    'exception_approved_by' => $user_id,
                ), array('id' => $id));
                // الشرط الثاني: حدث exception إلزاميٌّ في السجل بنوعه وسببه
                finreq_log($g, $id, 'exception',
                    'استثناء طارئ معتمد (emergency_execute): ' . $reason
                    . ' — الشرط الثالث: استكمال الدورة رجعيا خلال 72 ساعة (تتعقبه الحوكمة الزمنية)',
                    null, 'is_exception=1');
                finreq_publish_fact($conn, $req, 'request.exception', 'fact:exc:' . $id,
                    array('exception_type' => 'emergency_execute', 'approved_by' => $user_id));
                finreq_notify($g, 'treasurer',
                    'مأذون بالأداء المسبق (طارئ §8.3): ' . $req['request_no'] . ' — الدورة تستكمل رجعيا',
                    'FinRequests/finance_gateway.php');
            } else {
                finreq_log($g, $id, 'exception_denied', 'رفض الاستثناء الطارئ: ' . $reason);
            }
        });
        finreq_redirect('finance_gateway.php', $decision === 'approve'
            ? '🚨 اعتمد الطارئ — يجوز الأداء قبل اكتمال الاعتماد وتستكمل الدورة خلال 72 ساعة'
            : '⛔ رفض طلب الاستثناء بسبب مسجل');
    } catch (\Throwable $t) {
        error_log('finreq exception_decide: ' . $t->getMessage());
        finreq_redirect('finance_gateway.php', '❌ تعذر تنفيذ القرار');
    }
}

finreq_redirect($back, '❌ فعل غير معروف');
