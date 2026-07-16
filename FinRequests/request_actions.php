<?php
/**
 * بوابة الطلب المالي D05 — معالج الأفعال (POST + إعادة توجيه، نمط كرت المعدة)
 * الأفعال: create/update_draft/attach_doc/submit/withdraw/recommend/dept_approve/
 *          return_request/reject/acct_forward — كلها عبر بوابة العزل، وقرارات
 *          الحالة عبر محرّك StateMachine بأدوار صفّ التوجيه، والسجل إلحاقي.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin request actions super') : ems_tenant_db();

function finreq_redirect($to, $msg)
{
    header('Location: ' . $to . (strpos($to, '?') === false ? '?' : '&') . 'msg=' . urlencode($msg));
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
        finreq_redirect('request_form.php', '❌ الإدارة غير مفعّلةٍ في بوابة الطلبات بعد');
    }
    if (!finreq_role_is($routing, $role, 'requester')) {
        finreq_redirect('request_form.php', '❌ دورك ليس من أدوار الإنشاء لهذه الإدارة');
    }
    $cat = finreq_catalog();
    $type = $S('request_type');
    if (!isset($cat[$type]) || !$cat[$type]['active']) {
        finreq_redirect('request_form.php', '❌ نوع الطلب غير مفعّلٍ في هذه المرحلة');
    }
    $amount = floatval($S('amount'));
    $justification = $S('justification');
    $need_class = in_array($S('need_class'), array('planned', 'unplanned', 'urgent', 'emergency'), true) ? $S('need_class') : 'planned';
    // بوابة التبرير الصفرية (3.0): لا طلب بلا «لماذا» وتصنيف حاجة
    if ($justification === '' || $amount <= 0) {
        finreq_redirect('request_form.php', '❌ المبرّر والمبلغ الموجب شرطا الحفظ (بوابة التبرير)');
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
            finreq_redirect('request_form.php?id=' . $new_id, '✅ حُفظ الطلب مسودةً برقم ' . $data['request_no'] . ' — أرفق مستنداته ثم أرسله');
        } else {
            $id = intval($_POST['id'] ?? 0);
            $req = finreq_fetch($gate, $id);
            if (!$req) { finreq_redirect('my_requests.php', '❌ الطلب غير موجود'); }
            // أقفال المرحلة (§8.5): التحرير الكامل للمسودة والمُعاد فقط، ولمنشئه (أو السوبر)
            if (!in_array($req['state'], array('draft', 'returned'), true)) {
                finreq_redirect('request_view.php?id=' . $id, '❌ الطلب تجاوز مرحلة التحرير — التعديل بإعادةٍ موثقة فقط');
            }
            if (!$is_super && intval($req['created_by']) !== $user_id) {
                finreq_redirect('my_requests.php', '❌ التحرير لمنشئ الطلب حصرًا');
            }
            unset($data['request_type'], $data['source_module']); // النوع والإدارة لا يتبدلان بعد الميلاد
            $gate->runInTransaction(function ($g) use ($data, $id, $req) {
                $g->update('fin_requests', $data, array('id' => $id));
                finreq_log($g, $id, 'edit', 'تحديث بيانات ' . ($req['state'] === 'returned' ? 'الطلب المعاد' : 'المسودة'),
                    'amount=' . $req['amount'], 'amount=' . $data['amount']);
            });
            finreq_redirect('request_form.php?id=' . $id, '✅ حُدّثت بيانات الطلب');
        }
    } catch (\Throwable $t) {
        error_log('finreq create/update: ' . $t->getMessage());
        finreq_redirect('request_form.php', '❌ تعذّر الحفظ: ' . $t->getMessage());
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
    $file_ref = finreq_upload('doc_file');
    if ($file_ref === null) {
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذّر رفع الملف (الأنواع: صور/PDF بحد 5MB)');
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
        finreq_redirect('request_form.php?id=' . $id, '✅ أُرفق المستند');
    } catch (\Throwable $t) {
        error_log('finreq attach: ' . $t->getMessage());
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذّر الإرفاق');
    }
}

// ═══ 3) بنود الطلب (شراء متعدد الأصناف §11.2) ═══
if ($action === 'add_line' || $action === 'delete_line') {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect('my_requests.php', '❌ الطلب غير موجود'); }
    if (!in_array($req['state'], array('draft', 'returned'), true)) {
        finreq_redirect('request_view.php?id=' . $id, '❌ البنود تُحرَّر في المسودة/المعاد فقط');
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
            finreq_redirect('request_form.php?id=' . $id, '✅ أُضيف البند');
        } else {
            $line_id = intval($_POST['line_id'] ?? 0);
            $gate->runInTransaction(function ($g) use ($id, $line_id) {
                $g->deleteChild('fin_request_lines', $line_id, 'fin_requests', $id, 'request_id', 'حذف بند مسودة D05');
                finreq_log($g, $id, 'edit', 'حذف البند #' . $line_id);
            });
            finreq_redirect('request_form.php?id=' . $id, '🗑️ حُذف البند');
        }
    } catch (\Throwable $t) {
        error_log('finreq lines: ' . $t->getMessage());
        finreq_redirect('request_form.php?id=' . $id, '❌ تعذّرت العملية على البنود');
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
    if (!$routing) { finreq_redirect($back, '❌ لا صفّ توجيهٍ لإدارة الطلب'); }
    $reason = $S('reason');

    // تخصيص فعل المحرّك بحسب الحالة الراهنة
    $m_action = $machine_actions[$action];
    if ($action === 'withdraw') {
        $m_action = ($req['state'] === 'draft') ? 'withdraw_draft' : 'withdraw_review';
        if (!$is_super && intval($req['created_by']) !== $user_id) {
            finreq_redirect($back, '❌ السحب لمنشئ الطلب حصرًا وقبل الاعتماد الإداري');
        }
        if ($reason === '') { finreq_redirect($back, '❌ سبب السحب إلزامي'); }
    }
    if ($action === 'return_request') {
        $m_action = ($req['state'] === 'pending_approval') ? 'return_acct' : 'return_review';
        if ($reason === '') { finreq_redirect($back, '❌ سبب الإعادة إلزامي — يقرؤه المنشئ كاملًا'); }
    }
    if ($action === 'reject') {
        $classes = finreq_rejection_classes();
        $rclass = isset($classes[$S('rejection_class')]) ? $S('rejection_class') : '';
        if ($reason === '' || $rclass === '') {
            finreq_redirect($back, '❌ الرفض يستلزم تصنيفًا وسببًا مسجَّلين (§8.5)');
        }
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
        $gate->runInTransaction(function ($g) use ($action, $id, $req, $reason, $S, $user_id, $result) {
            if ($action === 'submit' || $action === 'resubmit') {
                finreq_log($g, $id, $action === 'submit' ? 'submit' : 'resubmit',
                    $reason !== '' ? $reason : 'إرسال الطلب للمراجعة', $result['from'], $result['to']);
                finreq_stamp_sla($g, $id, 'dept_review', strval($req['need_class']));
                // كشف التكرار التحذيري (§8.4) بعد الإرسال
                $dup = finreq_duplicate_check($g, $req);
                if ($dup) {
                    $g->update('fin_requests', array('duplicate_flag' => 1), array('id' => $id));
                    finreq_log($g, $id, 'duplicate_check',
                        'تحذير تكرار: يشابه ' . $dup['request_no'] . ' (النوع والمستفيد والمبلغ ±5% خلال 7 أيام)');
                }
            } elseif ($action === 'dept_approve') {
                $g->update('fin_requests', array('decided_by' => $user_id), array('id' => $id));
                finreq_log($g, $id, 'dept_approve', $reason !== '' ? $reason : 'اعتماد إداري — فُتح باب المالية', $result['from'], $result['to']);
                finreq_stamp_sla($g, $id, 'acct_review', strval($req['need_class']));
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

        $msgs = array(
            'submit' => '✅ أُرسل الطلب للمراجعة الإدارية',
            'resubmit' => '✅ استُكمل الطلب وأُعيد إرساله بالرقم نفسه',
            'dept_approve' => '✅ اعتُمد إداريًّا — الطلب لدى محاسب الإدارة',
            'return_request' => '↩️ أُعيد الطلب لمنشئه بالسبب المسجَّل',
            'reject' => '⛔ رُفض الطلب بسببٍ موثَّق — نهايةٌ مسجَّلة لا حذف',
            'withdraw' => '🗂️ سُحب الطلب بسببٍ مسجَّل ويبقى في السجل',
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
        finreq_redirect('accountant_desk.php', '❌ لا يعبر المالية إلا طلبٌ معتمدٌ إداريًّا (قاعدة العبور)');
    }
    if (!empty($req['event_id'])) {
        finreq_redirect('request_view.php?id=' . $id, 'ℹ️ الحدث مولودٌ مسبقًا لهذا الطلب (عطالة الناشر)');
    }
    if (!$is_super && $role !== '18' && $role !== '17') {
        finreq_redirect('accountant_desk.php', '❌ ولادة الحدث لمحاسب الإدارة (18) حصرًا');
    }
    // قاعدة الحساب (§5): التصنيف المحاسبي شأن المحاسب لا الطالب
    $account_id = intval($_POST['account_id'] ?? 0);
    if ($account_id <= 0) {
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ قاعدة الحساب: حدّد الحساب من الدليل قبل الولادة');
    }
    $acct = $gate->selectOne('fin_chart_of_accounts', array('columns' => array('id'), 'where' => array('id' => $account_id)));
    if (!$acct) {
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ الحساب غير موجودٍ في دليل شركتك');
    }
    try {
        $ev_result = null;
        $gate->runInTransaction(function ($g) use ($conn, $gate, $id, $account_id, $user_id, &$ev_result, $S, $In, $Dn) {
            // ضبط المحاسب: الحساب والأبعاد وبند الموازنة (soft)
            $refine = array('account_id' => $account_id);
            if ($In('budget_line_id') !== null) { $refine['budget_line_id'] = $In('budget_line_id'); }
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
        });
        finreq_redirect('accountant_desk.php', '✅ وُلد الحدث المالي ' . ($ev_result ? $ev_result['event_no'] : '') . ' بمرجع الطلب — يتابع دورته في D04 وتُشتق حالة الطلب منه');
    } catch (\Throwable $t) {
        error_log('finreq acct_forward: ' . $t->getMessage());
        finreq_redirect('accountant_desk.php?id=' . $id, '❌ تعذّرت الولادة: ' . $t->getMessage());
    }
}

// ═══ 6) حالات الإيقاف (§8.4): إلغاء / تعليق / استئناف / دمج المكرّرات ═══
if (in_array($action, array('cancel', 'suspend', 'resume', 'merge'), true)) {
    $id = intval($_POST['id'] ?? 0);
    $req = finreq_fetch($gate, $id);
    if (!$req) { finreq_redirect($back, '❌ الطلب غير موجود'); }
    $routing = finreq_routing_row($gate, strval($req['source_module']));
    if (!$routing) { finreq_redirect($back, '❌ لا صفّ توجيهٍ لإدارة الطلب'); }
    $reason = $S('reason');
    if ($reason === '' && $action !== 'resume') {
        finreq_redirect($back, '❌ السبب إلزاميٌّ لحالات الإيقاف (§8.4)');
    }

    try {
        $machine = finreq_machine($conn, $routing);

        if ($action === 'cancel') {
            // مدير الإدارة قبل وصول المالية (لا حدث)، والمدير المالي بعده
            if (!empty($req['event_id']) && !$is_super && $role !== '17') {
                finreq_redirect($back, '❌ بعد ولادة الحدث الإلغاء للمدير المالي حصرًا — مع معالجة الأثر في D04');
            }
            $result = $machine->apply($id, 'cancel_pending', $user_id, array('note' => $reason));
            $gate->runInTransaction(function ($g) use ($id, $reason, $result, $req) {
                finreq_log($g, $id, 'cancel', $reason
                    . (!empty($req['event_id']) ? ' — قاعدة الأثر الرجعي: عالج حدث D04 #' . intval($req['event_id']) . ' (عكس قيدٍ/تسوية)' : ''),
                    $result['from'], $result['to']);
            });
            finreq_redirect($back, '⛔ أُلغي الطلب بسببٍ موثَّق' . (!empty($req['event_id']) ? ' — لا تنسَ معالجة أثره في D04' : ''));
        }

        if ($action === 'suspend') {
            $m_action = ($req['state'] === 'pending_approval') ? 'suspend_pending' : 'suspend_review';
            $result = $machine->apply($id, $m_action, $user_id, array('note' => $reason));
            $gate->runInTransaction(function ($g) use ($id, $reason, $result) {
                finreq_log($g, $id, 'suspend', $reason, $result['from'], $result['to']);
            });
            finreq_redirect($back, '⏸️ عُلّق الطلب بقرارٍ موثَّق — يُستأنف بمثله');
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
                finreq_log($g, $id, 'resume', $reason !== '' ? $reason : 'استئنافٌ بقرارٍ موثَّق', $result['from'], $result['to']);
            });
            finreq_redirect($back, '▶️ استؤنف الطلب إلى مرحلته السابقة');
        }

        if ($action === 'merge') {
            // دمج مكرّرٍ في طلبٍ قائمٍ بمرجع merged_into_id (قرار محاسب الإدارة)
            $target_no = $S('merge_into_no');
            $target = $gate->selectOne('fin_requests', array('where' => array('request_no' => $target_no)));
            if (!$target || intval($target['id']) === $id) {
                finreq_redirect($back, '❌ حدّد رقم الطلب الأصل الصحيح للدمج');
            }
            if (!empty($req['event_id'])) {
                finreq_redirect($back, '❌ لا يُدمج طلبٌ وُلد حدثه — عالج بالإلغاء ومعالجة الأثر');
            }
            $m_action = ($req['state'] === 'pending_approval') ? 'merge_pending' : 'merge_review';
            $result = $machine->apply($id, $m_action, $user_id, array('note' => 'دمج في ' . $target['request_no']));
            $gate->runInTransaction(function ($g) use ($id, $target, $reason, $result) {
                $g->update('fin_requests', array('merged_into_id' => intval($target['id'])), array('id' => $id));
                finreq_log($g, $id, 'merge', $reason . ' — دُمج في ' . $target['request_no'], $result['from'], $result['to']);
                finreq_log($g, intval($target['id']), 'note', 'دُمج فيه الطلب المكرّر #' . $id);
            });
            finreq_redirect($back, '🔗 دُمج المكرّر في ' . $target['request_no'] . ' وأُقفل بمرجعه');
        }
    } catch (\Throwable $t) {
        error_log('finreq halt ' . $action . ': ' . $t->getMessage());
        finreq_redirect($back, '❌ ' . $t->getMessage());
    }
}

finreq_redirect($back, '❌ فعل غير معروف');
