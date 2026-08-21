<?php
/**
 * بوابة الطلب المالي D05 — المساعدات المركزية (المرحلتان 1+2)
 * ─────────────────────────────────────────────────────────────────────────
 * القاموس (الأنواع العشرة ومستنداتها §4) · التوجيه (fin_request_routing —
 * صناديق أدوارٍ بمفتاح تفعيلٍ لكل إدارة) · الترقيم الخادمي FR-سنة-تسلسل ·
 * محرّك الحالات (StateMachine — انتقالات البشر بأدوارها، والنشر حصريًّا عند
 * ولادة الحدث) · اشتقاق حالة الطلب من حالة حدثه (D04 → §9 خادميًّا حصرًا) ·
 * السجل الإلحاقي fin_request_events · رفع المستندات إلى storage/finreq.
 * كل الوصول عبر بوابة العزل ems_tenant_db() — لا استعلام خامًّا هنا.
 */

if (!defined('FINREQ_HELPERS_INCLUDED')) {
    define('FINREQ_HELPERS_INCLUDED', true);
}

require_once __DIR__ . '/../app/Core/StateMachine.php';
require_once __DIR__ . '/../app/Core/EventPublisher.php';

/** الأنواع العشرة (§4): التسمية، الأيقونة، أنواع المستندات المقبولة شرطًا للعبور،
 *  نوع حدث D04 القديم (legacy_event_type) — النشطة في المرحلة الأولى صرف+شراء. */
function finreq_catalog()
{
    return array(
        'purchase' => array(
            'label' => 'طلب شراء', 'icon' => 'fa fa-cart-shopping', 'active' => true,
            'docs' => array('quote', 'invoice'), 'docs_label' => 'عرض سعر أو فاتورة/أمر شراء',
            'legacy_event_type' => 'expense', 'has_lines' => true,
        ),
        'disbursement' => array(
            'label' => 'طلب صرف (مصروف)', 'icon' => 'fa fa-money-bill-wave', 'active' => true,
            'docs' => array('invoice', 'receipt'), 'docs_label' => 'فاتورة أو إيصال',
            'legacy_event_type' => 'expense', 'has_lines' => false,
        ),
        'advance' => array(
            'label' => 'طلب سلفة', 'icon' => 'fa fa-hand-holding-dollar', 'active' => true,
            'docs' => array('statement'), 'docs_label' => 'إقرار سلفةٍ وجدولة خصم',
            'legacy_event_type' => 'payable', 'has_lines' => false,
        ),
        'supplier_payment' => array(
            'label' => 'طلب دفعة مورد', 'icon' => 'fa fa-truck-fast', 'active' => true,
            'docs' => array('statement'), 'docs_label' => 'كشف حسابٍ مسوًّى',
            'legacy_event_type' => 'payable', 'has_lines' => false,
        ),
        'employee_payment' => array(
            'label' => 'طلب دفعة موظف', 'icon' => 'fa fa-user-check', 'active' => true,
            'docs' => array('statement'), 'docs_label' => 'كشف مستحقاتٍ معتمَد',
            'legacy_event_type' => 'payroll', 'has_lines' => false,
        ),
        'transfer' => array(
            'label' => 'طلب تحويل', 'icon' => 'fa fa-right-left', 'active' => true,
            'docs' => array('statement', 'other'), 'docs_label' => 'بيان الحسابين والغرض',
            'legacy_event_type' => 'settlement', 'has_lines' => false,
        ),
        'settlement' => array(
            'label' => 'طلب تسوية', 'icon' => 'fa fa-scale-balanced', 'active' => true,
            'docs' => array('statement'), 'docs_label' => 'كشف ما له وما عليه',
            'legacy_event_type' => 'settlement', 'has_lines' => true,
        ),
        'refund' => array(
            'label' => 'طلب استرداد', 'icon' => 'fa fa-rotate-left', 'active' => true,
            'docs' => array('receipt', 'other'), 'docs_label' => 'مستند الصرف الأصلي وسببه',
            'legacy_event_type' => 'expense', 'has_lines' => false,
        ),
        'discount' => array(
            'label' => 'طلب خصم', 'icon' => 'fa fa-percent', 'active' => true,
            'docs' => array('statement', 'other'), 'docs_label' => 'مستند الاستحقاق وسبب الخصم',
            'legacy_event_type' => 'settlement', 'has_lines' => false,
        ),
        'collection' => array(
            'label' => 'طلب تحصيل', 'icon' => 'fa fa-file-invoice-dollar', 'active' => true,
            'docs' => array('invoice'), 'docs_label' => 'فاتورة/مستخلص بمصادقة العميل',
            'legacy_event_type' => 'receivable', 'has_lines' => false,
        ),
        'other' => array(
            'label' => 'طلب آخر', 'icon' => 'fa fa-file-lines', 'active' => true,
            'docs' => array(), 'docs_label' => 'مستندٌ مؤيّدٌ واحدٌ على الأقل',
            'legacy_event_type' => 'enterprise', 'has_lines' => false,
        ),
    );
}

/** الحالات الست عشرة (§9) بعناوينها وألوان شاراتها */
function finreq_states()
{
    return array(
        'draft'            => array('label' => 'مسودة',            'badge' => 'secondary'),
        'under_review'     => array('label' => 'تحت المراجعة',      'badge' => 'info'),
        'pending_approval' => array('label' => 'بانتظار الاعتماد',  'badge' => 'warning'),
        'approved'         => array('label' => 'معتمد',             'badge' => 'primary'),
        'rejected'         => array('label' => 'مرفوض',             'badge' => 'danger'),
        'returned'         => array('label' => 'معاد للاستكمال',    'badge' => 'warning'),
        'posted'           => array('label' => 'مقيد',              'badge' => 'primary'),
        'paid'             => array('label' => 'مدفوع',             'badge' => 'success'),
        'collected'        => array('label' => 'محصل',              'badge' => 'success'),
        'closed'           => array('label' => 'مغلق',              'badge' => 'dark'),
        'archived'         => array('label' => 'مؤرشف',             'badge' => 'dark'),
        'withdrawn'        => array('label' => 'مسحوب',             'badge' => 'secondary'),
        'cancelled'        => array('label' => 'ملغى',              'badge' => 'danger'),
        'suspended'        => array('label' => 'معلّق',             'badge' => 'warning'),
        'expired'          => array('label' => 'منتهٍ',             'badge' => 'secondary'),
        'merged'           => array('label' => 'مدموج',             'badge' => 'secondary'),
    );
}

/** أنواع المستندات (§11.3) */
function finreq_doc_types()
{
    return array(
        'quote' => 'عرض سعر', 'invoice' => 'فاتورة/أمر شراء', 'statement' => 'كشف/بيان',
        'contract' => 'عقد', 'receipt' => 'إيصال', 'delivery_note' => 'إذن تسليم',
        'photo' => 'صورة مؤيدة', 'other' => 'أخرى',
    );
}

/** تصنيفات الرفض الإلزامية (§11.1) */
function finreq_rejection_classes()
{
    return array(
        'incomplete_docs' => 'مستندات ناقصة', 'no_budget' => 'خارج الميزانية',
        'policy_violation' => 'مخالفة سياسة', 'duplicate' => 'طلب مكرر',
        'not_justified' => 'بلا مبرر كافٍ', 'other' => 'سبب آخر',
    );
}

/** صفوف التوجيه المفعّلة للشركة الحالية */
function finreq_active_routing($gate)
{
    return $gate->select('fin_request_routing', array(
        'where' => array('is_active' => 1), 'orderBy' => 'module_label ASC',
    ));
}

/** صف التوجيه لإدارةٍ بعينها (مفعّلة أو لا) */
function finreq_routing_row($gate, $source_module)
{
    return $gate->selectOne('fin_request_routing', array(
        'where' => array('source_module' => strval($source_module)),
    ));
}

/** إدارات المستخدم المفعّلة التي يجوز له الإنشاء لها (دوره ضمن requester_roles) —
 *  والسوبر يرى كل المفعّل. */
function finreq_departments_for_creator($gate, $role)
{
    $role = strval($role);
    $rows = finreq_active_routing($gate);
    if ($role === '-1') { return $rows; }
    $mine = array();
    foreach ($rows as $r) {
        $allowed = array_map('trim', explode(',', strval($r['requester_roles'])));
        if (in_array($role, $allowed, true)) { $mine[] = $r; }
    }
    return $mine;
}

/** هل الدور مراجعُ (رئيس مباشر) أو معتمدُ (مدير إدارة) صفِّ توجيه؟ */
function finreq_role_is($routing, $role, $kind)
{
    if ($role === '-1') { return true; }
    if (!$routing) { return false; }
    if ($kind === 'reviewer') { return strval($routing['reviewer_role_id']) === strval($role); }
    if ($kind === 'manager') { return strval($routing['manager_role_id']) === strval($role); }
    if ($kind === 'requester') {
        $allowed = array_map('trim', explode(',', strval($routing['requester_roles'])));
        return in_array(strval($role), $allowed, true);
    }
    return false;
}

/** الترقيم الخادمي: FR-سنة-تسلسلٌ لكل شركة (فريدٌ بقيد uq_req_no، بإعادة محاولةٍ عند السباق) */
function finreq_next_no($gate)
{
    $year = date('Y');
    $rows = $gate->select('fin_requests', array(
        'columns' => array('request_no'),
        'whereRaw' => 'request_no LIKE ?', 'params' => array('FR-' . $year . '-%'),
        'orderBy' => 'id DESC', 'limit' => 1,
    ));
    $seq = 0;
    if ($rows && preg_match('/^FR-\d{4}-(\d+)$/', $rows[0]['request_no'], $m)) {
        $seq = intval($m[1]);
    }
    return 'FR-' . $year . '-' . str_pad(strval($seq + 1), 4, '0', STR_PAD_LEFT);
}

/** قيد السجل الإلحاقي (fin_request_events — لا UPDATE ولا DELETE عليه) */
function finreq_log($gate, $request_id, $event_type, $body = null, $old = null, $new = null, $on_behalf_of = null)
{
    $actor = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : null;
    $gate->insert('fin_request_events', array(
        'request_id' => intval($request_id),
        'event_type' => strval($event_type),
        'actor_user_id' => $actor,
        'on_behalf_of' => $on_behalf_of !== null ? intval($on_behalf_of) : null,
        'body' => $body !== null ? strval($body) : null,
        'old_value' => $old !== null ? substr(strval($old), 0, 120) : null,
        'new_value' => $new !== null ? substr(strval($new), 0, 120) : null,
    ));
}

/** محرّك حالات الطلب — انتقالات البشر (v1: إرسال/إعادة/استكمال/اعتماد إداري/رفض/سحب)
 *  الأدوار تُحقن من صفّ التوجيه؛ حالات الإيقاف الخمس الباقية تُفعَّل في مرحلتها (§8.4). */
function finreq_machine($conn, $routing)
{
    $labels = array();
    foreach (finreq_states() as $k => $v) { $labels[$k] = $v['label']; }
    $requesters = array_map('trim', explode(',', strval($routing['requester_roles'])));
    $requesters[] = '-1';
    $reviewer = strval($routing['reviewer_role_id']);
    $manager  = strval($routing['manager_role_id']);
    $def = array(
        'workflow' => 'fin_request',
        'entity_table' => 'fin_requests',
        'state_column' => 'state',
        'company_column' => 'company_id',
        'states' => $labels,
        'initial' => 'draft',
        'transitions' => array(
            'submit'         => array('from' => 'draft',            'to' => 'under_review',     'roles' => $requesters),
            'resubmit'       => array('from' => 'returned',         'to' => 'under_review',     'roles' => $requesters),
            'dept_approve'   => array('from' => 'under_review',     'to' => 'pending_approval', 'roles' => array($manager, '-1')),
            'return_review'  => array('from' => 'under_review',     'to' => 'returned',         'roles' => array($reviewer, $manager, '-1')),
            'return_acct'    => array('from' => 'pending_approval', 'to' => 'returned',         'roles' => array('18', '-1')),
            'reject_review'  => array('from' => 'under_review',     'to' => 'rejected',         'roles' => array($manager, '-1')),
            'withdraw_draft' => array('from' => 'draft',            'to' => 'withdrawn',        'roles' => $requesters),
            'withdraw_review'=> array('from' => 'under_review',     'to' => 'withdrawn',        'roles' => $requesters),
            // حالات الإيقاف (§8.4): الإلغاء بعد الاعتماد الإداري — مدير الإدارة قبل وصول
            // المالية والمدير المالي بعده (المعالج يميز بوجود event_id)؛ التعليق/الاستئناف
            // بقرار المدير المالي؛ والدمج قرار محاسب الإدارة للمكرّرات.
            'cancel_pending' => array('from' => 'pending_approval', 'to' => 'cancelled',        'roles' => array($manager, '17', '-1')),
            'suspend_review' => array('from' => 'under_review',     'to' => 'suspended',        'roles' => array('17', '-1')),
            'suspend_pending'=> array('from' => 'pending_approval', 'to' => 'suspended',        'roles' => array('17', '-1')),
            'resume_review'  => array('from' => 'suspended',        'to' => 'under_review',     'roles' => array('17', '-1')),
            'resume_pending' => array('from' => 'suspended',        'to' => 'pending_approval', 'roles' => array('17', '-1')),
            'merge_review'   => array('from' => 'under_review',     'to' => 'merged',           'roles' => array('18', '-1')),
            'merge_pending'  => array('from' => 'pending_approval', 'to' => 'merged',           'roles' => array('18', '-1')),
        ),
    );
    return new \App\Core\StateMachine($conn, $def);
}

/** جدول SLA (§8.1) بالساعات — 24/7 فعلية (قرار v1)؛ العمودان عاجل/طارئ من تصنيف الحاجة */
function finreq_sla_hours($stage, $need_class)
{
    $table = array(
        //                 اعتيادي · عاجل · طارئ
        'dept_review' => array(8,  4,  4),   // مراجعة الرئيس المباشر واعتماد الإدارة (كتلة تحت المراجعة)
        'acct_review' => array(24, 12, 4),   // مكتب محاسب الإدارة
        'finance'     => array(48, 24, 8),   // التحقق والاعتماد المالي (D04)
    );
    if (!isset($table[$stage])) { return null; }
    $idx = ($need_class === 'urgent') ? 1 : (($need_class === 'emergency') ? 2 : 0);
    return $table[$stage][$idx];
}

/** ختم استحقاق المرحلة: المدة تلتصق بالمرحلة لا بالطلب (قاعدة الساعة §8.1) —
 *  كل انتقالٍ يعيد ضبط sla_due_at لمالكها الجديد ويصفّر مستوى التصعيد. */
function finreq_stamp_sla($gate, $request_id, $stage, $need_class)
{
    $hours = finreq_sla_hours($stage, $need_class);
    if ($hours === null) { return; }
    $gate->update('fin_requests', array(
        'sla_due_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
        'escalation_level' => 0,
    ), array('id' => intval($request_id)));
}

/** عدّادات شارات السايدبار حسب الدور: صندوق الإدارة · مكتب المحاسب · المعاد لمنشئه */
function finreq_badge_counts($gate, $role, $user_id)
{
    // مفتاحُ «المعادِ لمنشئه» صار على الشاشةِ الموحَّدة بعدَ الدمج (2026-08-21)
    $out = array('FinRequests/dept_inbox.php' => 0, 'FinRequests/accountant_desk.php' => 0, 'FinRequests/request_form.php' => 0);
    try {
        $role = strval($role);
        $routes = finreq_active_routing($gate);
        $mods = array();
        foreach ($routes as $rt) {
            if ($role === '-1' || finreq_role_is($rt, $role, 'reviewer') || finreq_role_is($rt, $role, 'manager')) {
                $mods[] = strval($rt['source_module']);
            }
        }
        if ($mods) {
            $ph = implode(',', array_fill(0, count($mods), '?'));
            $out['FinRequests/dept_inbox.php'] = intval($gate->count('fin_requests', array(
                'whereRaw' => "state = 'under_review' AND source_module IN ($ph)", 'params' => $mods,
            )));
        }
        if ($role === '18' || $role === '17' || $role === '-1') {
            $out['FinRequests/accountant_desk.php'] = intval($gate->count('fin_requests', array(
                'whereRaw' => "state = 'pending_approval' AND event_id IS NULL", 'params' => array(),
            )));
        }
        $out['FinRequests/request_form.php'] = intval($gate->count('fin_requests', array(
            'whereRaw' => "state = 'returned' AND (created_by = ? OR requester_id = ?)",
            'params' => array(intval($user_id), intval($user_id)),
        )));
    } catch (\Throwable $t) { /* شارات فقط — لا تعطّل الواجهة */ }
    return $out;
}

/** فحص «المستند شرط العبور» (قاعدة البوابة الصفرية — صارمة من اليوم الأول):
 *  مستندٌ واحدٌ على الأقل من الأنواع المقبولة لنوع الطلب (أو أيّ نوعٍ إن كان الكتالوج مفتوحًا). */
function finreq_docs_satisfied($gate, $request)
{
    $cat = finreq_catalog();
    $type = strval($request['request_type']);
    $required = isset($cat[$type]) ? $cat[$type]['docs'] : array();
    $docs = $gate->select('fin_request_documents', array(
        'columns' => array('doc_type'),
        'where' => array('request_id' => intval($request['id'])),
    ));
    if (!$docs) { return false; }
    if (!$required) { return true; }
    foreach ($docs as $d) {
        if (in_array($d['doc_type'], $required, true)) { return true; }
    }
    return false;
}

/** كشف التكرار (§8.4): نفس النوع والمستفيد ومبلغٌ ضمن ±5% خلال 7 أيام — علمٌ تحذيري لا مانع */
function finreq_duplicate_check($gate, $request)
{
    $amount = floatval($request['amount']);
    $low = $amount * 0.95; $high = $amount * 1.05;
    $rows = $gate->select('fin_requests', array(
        'columns' => array('id', 'request_no'),
        'whereRaw' => 'id <> ? AND request_type = ? AND beneficiary_type = ?
                       AND COALESCE(beneficiary_ref,0) = ? AND amount BETWEEN ? AND ?
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       AND state NOT IN (\'rejected\',\'withdrawn\',\'expired\',\'merged\',\'cancelled\')',
        'params' => array(intval($request['id']), strval($request['request_type']),
            strval($request['beneficiary_type']), intval($request['beneficiary_ref'] ?? 0), $low, $high),
        'limit' => 1,
    ));
    return $rows ? $rows[0] : null;
}

/** ولادة الحدث المالي (نقطة الالتحام event_id — §3.2) عبر الناشر المؤسسي على
 *  اتصال المعاملة نفسه: عطالة الناشر تمنع الازدواج، والطلب يبقى «بانتظار الاعتماد»
 *  حتى يعتمده D04 (الاشتقاق §9). تُستدعى داخل runInTransaction حصرًا. */
function finreq_birth_event($conn, $gate, $request, $actor_user_id)
{
    $cat = finreq_catalog();
    $type = strval($request['request_type']);
    $legacy = isset($cat[$type]) ? $cat[$type]['legacy_event_type'] : 'enterprise';
    $result = \App\Core\EventPublisher::publish($conn, array(
        'event_key' => 'finance.request.forwarded',
        'category' => 'financial',
        'source_module' => strval($request['source_module']) === 'general' ? 'finance' : strval($request['source_module']),
        'company_id' => intval($request['company_id']),
        'entity_type' => 'fin_request',
        'entity_id' => intval($request['id']),
        'occurred_at' => gmdate('Y-m-d H:i:s'),
        'created_by' => intval($actor_user_id),
        'legacy_event_type' => $legacy,
        'amount' => floatval($request['amount']),
        'currency' => strval($request['currency']),
        'source_ref' => strval($request['request_no']),
        'project_id' => $request['project_id'] ?: null,
        'contract_id' => $request['contract_id'] ?: null,
        'equipment_id' => $request['equipment_id'] ?: null,
        'notes' => $request['statement'] !== null ? strval($request['statement']) : null,
        'payload' => array(
            'request_no' => strval($request['request_no']),
            'request_type' => $type,
            'beneficiary_type' => strval($request['beneficiary_type']),
            'beneficiary_ref' => $request['beneficiary_ref'] !== null ? intval($request['beneficiary_ref']) : null,
            'beneficiary_name' => $request['beneficiary_name'] !== null ? strval($request['beneficiary_name']) : null,
            'justification' => $request['justification'] !== null ? strval($request['justification']) : null,
            'need_class' => strval($request['need_class']),
            'account_id' => $request['account_id'] !== null ? intval($request['account_id']) : null,
            'cost_center' => $request['cost_center'] !== null ? strval($request['cost_center']) : null,
        ),
    ));
    $gate->update('fin_requests', array('event_id' => intval($result['id'])), array('id' => intval($request['id'])));
    return $result;
}

/** اشتقاق حالة الطلب من حالة حدثه (§9 — خادميًّا حصرًا، يُستدعى عند كل عرض):
 *  approved→approved · posted→posted · settled→(paid|collected) · closed→closed · rejected→rejected */
function finreq_sync_state($gate, $request)
{
    if (empty($request['event_id'])) { return $request; }
    // حارس الحالات الختامية: المؤرشف (⑬) وقرارات الإيقاف الموثقة (§8.4) لا
    // يقلبها الاشتقاق — الأرشفة انقضاء الدورة، والسحب/الإلغاء/الدمج/الانتهاء
    // قراراتٌ بأسبابٍ مسجلة يمحوها القلبُ الآلي لو سمحنا به.
    if (in_array(strval($request['state']),
        array('archived', 'withdrawn', 'cancelled', 'merged', 'expired'), true)) {
        return $request;
    }
    $ev = $gate->selectOne('fin_financial_events', array(
        'columns' => array('id', 'state'),
        'where' => array('id' => intval($request['event_id'])),
        'includeDeleted' => true,
    ));
    if (!$ev) { return $request; }
    $map = array(
        'approved' => 'approved',
        'posted' => 'posted',
        'settled' => (strval($request['request_type']) === 'collection') ? 'collected' : 'paid',
        'closed' => 'closed',
        'rejected' => 'rejected',
    );
    $evState = strval($ev['state']);
    if (!isset($map[$evState])) { return $request; }
    $derived = $map[$evState];
    if (strval($request['state']) === $derived) { return $request; }
    // قاعدة المسار المركّب (§6.2): لا يُغلق الأصلُ وفروعُه معلّقة — الإقفال
    // المشتق يُمسَك حتى تبلغ كل الفروع حالاتها الختامية (يبقى على حالته).
    if ($derived === 'closed' && finreq_has_live_children($gate, intval($request['id']))) {
        return $request;
    }
    // انتقال اشتقاقٍ آلي — يُدوَّن في السجل بنوع system بقيمتيه قبل/بعد
    $gate->update('fin_requests', array('state' => $derived), array('id' => intval($request['id'])));
    finreq_log($gate, intval($request['id']), 'system',
        'اشتقاق الحالة من حدث D04 #' . intval($ev['id']), strval($request['state']), $derived);
    $request['state'] = $derived;
    return $request;
}

/** تحويل اختزال php.ini («2M»/«8M»/«512K») إلى بايتات. */
function finreq_ini_bytes($v)
{
    $v = trim((string)$v);
    if ($v === '') { return 0; }
    $unit = strtolower($v[strlen($v) - 1]);
    $num = (float)$v;
    switch ($unit) {
        case 'g': $num *= 1024; // no break — يتراكم للأدنى
        case 'm': $num *= 1024; // no break
        case 'k': $num *= 1024; // no break
    }
    return (int)$num;
}

/** الحدُّ الفعلي لرفع ملفٍ واحدٍ بالميغابايت — أصغر قيمتَي upload_max_filesize
 *  وpost_max_size وسقف التطبيق (5MB)، لا الرقم المُعلَن في الشاشة وحده. */
function finreq_upload_limit_mb()
{
    $cands = array(
        finreq_ini_bytes(ini_get('upload_max_filesize')),
        finreq_ini_bytes(ini_get('post_max_size')),
        5 * 1024 * 1024,
    );
    $cands = array_filter($cands, function ($x) { return $x > 0; });
    $eff = $cands ? min($cands) : 5 * 1024 * 1024;
    return round($eff / (1024 * 1024), 1);
}

/** رفع مستندٍ إلى التخزين المحمي storage/finreq مع سببِ فشلٍ صريحٍ في $err (بدل
 *  الصمت): يميّز «تجاوزَ حدِّ الخادم» عن «صيغةٍ غير مدعومة» عن «خطأ تخزين». */
function finreq_upload($field, &$err = null)
{
    $err = null;
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        // جسمُ الطلب قد يكون تجاوز post_max_size فأفرغ PHP كلَّ $_FILES
        $clen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $pmax = finreq_ini_bytes(ini_get('post_max_size'));
        $err = ($clen > 0 && $pmax > 0 && $clen > $pmax)
            ? 'حجم الإرسال (' . round($clen / 1048576, 1) . 'م) تجاوز حدَّ الخادم (' . round($pmax / 1048576, 1) . 'م) — الملف كبيرٌ جدًّا'
            : 'لم يصل أيُّ ملفٍ إلى الخادم';
        return null;
    }
    $e = intval($_FILES[$field]['error']);
    if ($e !== UPLOAD_ERR_OK) {
        switch ($e) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $err = 'حجم الملف أكبر من حدِّ الخادم (' . finreq_upload_limit_mb()
                    . ' ميغابايت) — اضغط الصورة/الـPDF أو صوّرها بجودةٍ أقلّ ثم أعد المحاولة';
                break;
            case UPLOAD_ERR_NO_FILE:
                $err = 'لم تُختَر صورةٌ أو مستندٌ للرفع';
                break;
            case UPLOAD_ERR_PARTIAL:
                $err = 'انقطع رفع الملف قبل اكتماله — أعد المحاولة';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                $err = 'تعذّر على الخادم حفظ الملف المؤقت (إعداد المجلد المؤقت)';
                break;
            default:
                $err = 'تعذّر رفع الملف (رمز خطأٍ ' . $e . ')';
        }
        return null;
    }
    $dir = __DIR__ . '/../storage/finreq/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (function_exists('validate_file_upload')) {
        $check = validate_file_upload($_FILES[$field], array('jpg', 'jpeg', 'png', 'webp', 'pdf'), 5 * 1024 * 1024);
        if (empty($check['valid'])) {
            $err = !empty($check['errors'])
                ? implode(' · ', $check['errors'])
                : 'صيغةٌ غير مدعومة — المسموح: صور JPG/PNG/WEBP أو PDF بحدّ 5 ميغابايت';
            return null;
        }
    }
    $name = function_exists('generate_safe_filename')
        ? generate_safe_filename($_FILES[$field]['name'])
        : (bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION)));
    if (@move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name)) {
        return 'storage/finreq/' . $name;
    }
    $err = 'تعذّر حفظ الملف على الخادم — راجع صلاحيات مجلد التخزين';
    return null;
}

/** نشر حقيقة دورة طلبٍ على الجذر المحايد (§9.3 — حقيقةٌ بلا إسقاطٍ مالي).
 *  العطالة حتمية بمفتاح المستدعي (transition/سياق)؛ تُستدعى داخل معاملة السجل
 *  نفسها. لا ترمي أبدًا — فشل الحقيقة يُدوَّن error_log ولا يكسر العملية. */
function finreq_publish_fact($conn, $request, $event_key, $idem, array $extra = array())
{
    try {
        $actor = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id'])
            : (intval($request['created_by'] ?? 0) ?: 1);
        $payload = array_merge(array(
            'request_no' => strval($request['request_no']),
            'request_type' => strval($request['request_type']),
            'need_class' => strval($request['need_class']),
        ), $extra);
        return \App\Core\EventPublisher::publishFact($conn, array(
            'event_key' => strval($event_key),
            'category' => 'financial',
            'source_module' => strval($request['source_module']) === 'general' ? 'finance' : strval($request['source_module']),
            'company_id' => intval($request['company_id']),
            'entity_type' => 'fin_request',
            'entity_id' => intval($request['id']),
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => $actor,
            'idempotency_key' => strval($idem),
            'amount' => floatval($request['amount']),
            'currency' => strval($request['currency']),
            'source_ref' => strval($request['request_no']),
            'project_id' => !empty($request['project_id']) ? intval($request['project_id']) : null,
            'contract_id' => !empty($request['contract_id']) ? intval($request['contract_id']) : null,
            'equipment_id' => !empty($request['equipment_id']) ? intval($request['equipment_id']) : null,
            'payload' => $payload,
        ));
    } catch (\Throwable $t) {
        error_log('finreq fact ' . $event_key . ': ' . $t->getMessage());
        return null;
    }
}

/** إشعارٌ موجَّهٌ عبر fin_notifications القائم (§12.3) — مرآة fin_notify بلا
 *  اعتمادٍ متبادلٍ بين الوحدتين، بمنع تكرار نفس العنوان لنفس المستوى يوميًّا. */
function finreq_notify($gate, $target_level, $title, $link = null)
{
    try {
        $title = mb_substr(strval($title), 0, 200);
        $dup = $gate->count('fin_notifications', array(
            'where' => array('target_level' => strval($target_level), 'title' => $title),
            'whereRaw' => 'DATE(created_at) = CURDATE()', 'params' => array(),
        ));
        if (intval($dup) > 0) { return; }
        $gate->insert('fin_notifications', array(
            'target_level' => strval($target_level), 'title' => $title,
            'link' => $link !== null ? strval($link) : null,
        ));
    } catch (\Throwable $t) { /* إشعارٌ فقط — لا يكسر العملية */ }
}

/** خريطة نوع الطلب/الإدارة → فئة بند الموازنة (بوابة الميزانية §3.4-②) —
 *  افتراضٌ ذكيٌّ يظل المحاسب حرًّا في تجاوزه من القائمة. */
function finreq_budget_category_for($request_type, $source_module)
{
    if (strval($source_module) === 'maintenance') { return 'maintenance'; }
    if (strval($source_module) === 'transport') { return 'transport'; }
    $map = array(
        'purchase' => 'procurement', 'supplier_payment' => 'procurement',
        'employee_payment' => 'salaries', 'advance' => 'salaries',
        'collection' => 'revenue', 'disbursement' => 'operational_need',
    );
    return isset($map[strval($request_type)]) ? $map[strval($request_type)] : 'other';
}

/** بنود موازنة الشركة مع المتبقي المحسوب (planned - actual) للعرض والفحص */
function finreq_budget_lines($gate)
{
    $out = array();
    try {
        foreach ($gate->select('fin_budget_lines', array(
            'columns' => array('id', 'budget_id', 'line_kind', 'category', 'planned_amount', 'actual_amount'),
            'orderBy' => 'category ASC',
        )) as $l) {
            $l['remaining'] = round(floatval($l['planned_amount']) - floatval($l['actual_amount']), 2);
            $out[] = $l;
        }
    } catch (\Throwable $t) { /* لا موازنة = قائمة فارغة */ }
    return $out;
}

/** كشف التجزئة (§8.5): طلباتٌ حيّةٌ لنفس المستفيد والنوع خلال 30 يومًا —
 *  تُجمع قيمتها لتحديد مستوى الاعتماد الأعلى (عرضٌ تحذيريٌّ للمحاسب). */
function finreq_fragmentation($gate, $request)
{
    try {
        $rows = $gate->select('fin_requests', array(
            'columns' => array('id', 'amount'),
            'whereRaw' => 'id <> ? AND request_type = ? AND COALESCE(beneficiary_name,\'\') = ?
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                           AND state NOT IN (\'rejected\',\'withdrawn\',\'expired\',\'merged\',\'cancelled\')',
            'params' => array(intval($request['id']), strval($request['request_type']),
                strval($request['beneficiary_name'] ?? '')),
        ));
        if (!$rows) { return null; }
        $total = floatval($request['amount']);
        foreach ($rows as $r) { $total += floatval($r['amount']); }
        return array('count' => count($rows) + 1, 'total' => $total);
    } catch (\Throwable $t) { return null; }
}

/** أزمنة المراحل من سجل الانتقالات — قناة قراءةٍ موثَّقة لجدول T_RESTRICTED
 *  (ems_state_transitions يُكتب عبر StateMachine حصرًا؛ هذه قراءةٌ إحصائية
 *  معزولةٌ بالشركة عبر عمود company_id الذي يختمه المحرك في كل قيد). */
function finreq_stage_timings($conn, $company_id)
{
    $out = array();
    $sql = "SELECT to_state, COUNT(*) AS entries,
                   ROUND(AVG(mins), 0) AS avg_mins, ROUND(MAX(mins), 0) AS max_mins
              FROM (
                    SELECT to_state,
                           TIMESTAMPDIFF(MINUTE, created_at,
                               LEAD(created_at) OVER (PARTITION BY entity_id ORDER BY id)) AS mins
                      FROM ems_state_transitions
                     WHERE workflow = 'fin_request' AND company_id = ?
                   ) t
             WHERE mins IS NOT NULL
             GROUP BY to_state";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return $out; }
    $cid = intval($company_id);
    $stmt->bind_param('i', $cid);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $out[$row['to_state']] = $row; }
    }
    $stmt->close();
    return $out;
}

/** فروع الطلب (المسار المركّب §6.2) — أبناؤه المباشرون بحالاتهم */
function finreq_children($gate, $parent_id)
{
    return $gate->select('fin_requests', array(
        'columns' => array('id', 'request_no', 'request_type', 'state', 'amount', 'currency', 'justification'),
        'where' => array('parent_request_id' => intval($parent_id)),
        'orderBy' => 'id ASC',
    ));
}

/** هل للطلب فروعٌ حيّة؟ (قاعدة المسار المركّب: «لا يُغلق الأصلُ وفروعُه معلّقة») —
 *  الحيّ = كل ما ليس في حالةٍ ختامية. */
function finreq_has_live_children($gate, $parent_id)
{
    $n = $gate->count('fin_requests', array(
        'whereRaw' => "parent_request_id = ? AND state NOT IN
            ('closed','archived','rejected','withdrawn','cancelled','expired','merged','paid','collected')",
        'params' => array(intval($parent_id)),
    ));
    return intval($n) > 0;
}

/** نوع الطلب الفرعي المشتق من مستفيد الأصل (الفرع دفعةٌ بطبيعته) */
function finreq_child_type($beneficiary_type)
{
    $map = array('supplier' => 'supplier_payment', 'employee' => 'employee_payment');
    return isset($map[strval($beneficiary_type)]) ? $map[strval($beneficiary_type)] : 'disbursement';
}

/** جلب طلبٍ معزولًا + اشتقاق حالته (الاستدعاء المعياري لكل الشاشات) */
function finreq_fetch($gate, $id)
{
    $row = $gate->selectOne('fin_requests', array('where' => array('id' => intval($id))));
    if ($row) { $row = finreq_sync_state($gate, $row); }
    return $row;
}

/* ═══════════════════════════════════════════════════════════════════════════
   الشاشةُ الموحَّدة (`request_form.php`) — مجموعاتُ الحالةِ وبناءُ رابطِ الترشيح
   ───────────────────────────────────────────────────────────────────────────
   مصدرٌ واحدٌ تقرؤه بطاقاتُ الإحصاءِ وقائمةُ المرشِّحِ والترشيحُ نفسُه، فلا
   تفترق البطاقةُ عن الجدولِ في تعريفِ «قيدَ الدورة».
   ═══════════════════════════════════════════════════════════════════════════ */

/** مجموعاتُ حالاتِ الطلبِ التي تُقاس بها البطاقاتُ ويُرشَّح بها الجدول. */
function finreq_state_groups()
{
    return array(
        'draft'    => array('label' => 'مسودات',           'icon' => 'fa fa-pen-ruler',           'tone' => 'stats-slate',   'states' => array('draft')),
        'cycle'    => array('label' => 'قيدَ الدورة',       'icon' => 'fa fa-hourglass-half',      'tone' => 'stats-orange',  'states' => array('under_review', 'pending_approval', 'suspended')),
        'returned' => array('label' => 'معادةٌ للاستكمال',  'icon' => 'fa fa-rotate-left',         'tone' => 'stats-danger',  'states' => array('returned')),
        'approved' => array('label' => 'معتمدةٌ أو مقيَّدة', 'icon' => 'fa fa-circle-check',        'tone' => 'stats-primary', 'states' => array('approved', 'posted')),
        'settled'  => array('label' => 'مدفوعةٌ أو مُغلقة', 'icon' => 'fa fa-hand-holding-dollar', 'tone' => 'stats-success', 'states' => array('paid', 'collected', 'closed', 'archived')),
        'refused'  => array('label' => 'مرفوضةٌ أو مسحوبة', 'icon' => 'fa fa-circle-xmark',        'tone' => 'stats-purple',  'states' => array('rejected', 'cancelled', 'withdrawn', 'expired', 'merged')),
    );
}

/**
 * سلسلةُ استعلامٍ تحفظ المرشِّحاتِ القائمةَ وتُبدِّل ما طُلب تبديلُه — فنقرُ
 * بطاقةٍ يغيّر الحالةَ وحدَها ولا يُسقط بحثًا ولا مدًى زمنيًّا كتبه المستخدم.
 * المفاتيحُ **قائمةُ سماحٍ صلبة**: ما ليس فيها لا يُنقَل ولو جاء في الرابط.
 *
 * @param array $override مفتاحٌ => قيمة (القيمةُ الفارغةُ تعني حذفَ المرشِّح)
 * @return string '' أو '?a=1&b=2' مهرَّبةً للوسمِ
 */
function finreq_qs(array $override = array())
{
    $keys = array('state', 'type', 'q', 'from', 'to');
    $out = array();
    foreach ($keys as $k) {
        $v = array_key_exists($k, $override)
            ? strval($override[$k])
            : (isset($_GET[$k]) ? strval($_GET[$k]) : '');
        $v = trim($v);
        if ($v !== '') { $out[$k] = $v; }
    }
    if (!$out) { return ''; }
    return '?' . htmlspecialchars(http_build_query($out), ENT_QUOTES, 'UTF-8');
}

/** شارة حالةٍ جاهزة للعرض */
function finreq_state_badge($state)
{
    $states = finreq_states();
    $s = isset($states[$state]) ? $states[$state] : array('label' => $state, 'badge' => 'secondary');
    return '<span class="badge bg-' . $s['badge'] . '">' . htmlspecialchars($s['label']) . '</span>';
}

/** اسمُ دورٍ من سجل الأدوار — بذاكرةٍ محليةٍ فلا يتكرر الاستعلام في الصفحة. */
function finreq_role_name($gate, $role_id)
{
    static $cache = array();
    $rid = intval($role_id);
    if ($rid <= 0) { return ''; }
    if (array_key_exists($rid, $cache)) { return $cache[$rid]; }
    $name = '';
    try {
        $row = $gate->selectOne('roles', array('columns' => array('name'), 'where' => array('id' => $rid)));
        if ($row && isset($row['name'])) { $name = strval($row['name']); }
    } catch (\Throwable $t) { /* الاسم زينةُ عرضٍ — لا يعطّل الشريط */ }
    return $cache[$rid] = $name;
}

/**
 * بانيةُ شريط رحلة الطلب المالي (الدستور §5/§9 · UX-01 §6.1 · UX-02 §5-①).
 * ─────────────────────────────────────────────────────────────────────────
 * تقرأ ما هو مسجَّلٌ فعلًا ولا تخترع: أزمنةُ المراحل من `fin_request_events`
 * (سجلٌّ إلحاقي)، وأصحابُها من صفّ التوجيه وسجل الأدوار، والتأخرُ من
 * `sla_due_at` المختوم لكل مرحلة (§8.1). وما لا مصدرَ له يُترك فارغًا.
 *
 * سبعُ مراحلَ تطابق حالاتِ النظام حرفيًّا — لا مرحلةَ ملفَّقة:
 *   ① الإنشاء (draft) ② اعتماد الإدارة (under_review) ③ مكتب المحاسب
 *   (pending_approval بلا حدث) ④ الاعتماد المالي (بحدثٍ) ⑤ القيد (posted)
 *   ⑥ الصرف/التحصيل (paid|collected) ⑦ الإغلاق (closed)
 *
 * @param  array      $timeline صفوف fin_request_events مرتّبةً تصاعديًّا
 * @param  array|null $routing  صفّ التوجيه لإدارة الطلب (لاسم المعتمِد)
 * @return array عقد ems_journey_bar()
 */
function finreq_journey($gate, array $req, array $timeline = array(), $routing = null)
{
    require_once __DIR__ . '/../includes/journey_bar.php';   // ems_journey_ago

    $state = strval($req['state']);
    $isCollection = (strval($req['request_type']) === 'collection');

    // ── ① أزمنةُ الإنجاز من السجل الإلحاقي (لا من تخمين) ────────────────
    $at = array();          // رقم المرحلة => وقت إنجازها
    $lastReturn = null;     // آخر إعادةٍ بسببها
    foreach ($timeline as $ev) {
        $type = strval($ev['event_type']);
        $new  = strval($ev['new_value']);
        $when = strval($ev['created_at']);
        if ($type === 'submit' || $type === 'resubmit') { $at[1] = $when; }
        elseif ($type === 'dept_approve')               { $at[2] = $when; }
        elseif ($type === 'publish')                    { $at[3] = $when; }
        elseif ($type === 'return')                     { $lastReturn = $ev; }
        elseif ($type === 'system') {
            // انتقالاتُ الاشتقاق من حدث D04 — قيمتُها الجديدة اسمُ الحالة
            if     ($new === 'approved') { $at[4] = $when; }
            elseif ($new === 'posted')   { $at[5] = $when; }
            elseif ($new === 'paid' || $new === 'collected') { $at[6] = $when; }
            elseif ($new === 'closed')   { $at[7] = $when; }
        }
    }

    // ── ② المرحلة الحالية من الحالة الفعلية ─────────────────────────────
    //   pending_approval تنقسم مرحلتين بوجود الحدث: بلا حدثٍ = مكتب المحاسب،
    //   وبحدثٍ = المالية — وهو تمييزُ finreq_badge_counts نفسُه لا اجتهادٌ جديد.
    $hasEvent = !empty($req['event_id']);
    $stopped  = array('rejected', 'withdrawn', 'cancelled', 'expired', 'merged');
    $current  = 0;
    if     ($state === 'draft' || $state === 'returned')  { $current = 1; }
    elseif ($state === 'under_review')                    { $current = 2; }
    elseif ($state === 'pending_approval')                { $current = $hasEvent ? 4 : 3; }
    elseif ($state === 'approved')                        { $current = 5; }
    elseif ($state === 'posted')                          { $current = 6; }
    elseif ($state === 'paid' || $state === 'collected')  { $current = 7; }
    elseif ($state === 'closed' || $state === 'archived') { $current = 8; }   // كلُّها منجَزة
    elseif ($state === 'suspended')                       { $current = $hasEvent ? 4 : ($at ? 2 : 1); }
    elseif (in_array($state, $stopped, true)) {
        // توقفٌ: آخرُ مرحلةٍ لها زمنٌ هي ما بلغه فعلًا، وما بعدها مُطفأ
        for ($k = 7; $k >= 1; $k--) { if (isset($at[$k])) { $current = $k + 1; break; } }
        if ($current === 0) { $current = 1; }
    }

    // ── ③ أصحابُ المراحل — من التوجيه وسجل الأدوار لا من نصٍّ مكتوب ─────
    $deptLabel = ($routing && !empty($routing['module_label'])) ? strval($routing['module_label']) : '';
    $mgrName   = $routing ? finreq_role_name($gate, $routing['manager_role_id']) : '';
    $ownerMgr  = ($mgrName !== '') ? $mgrName : ($deptLabel !== '' ? ('مدير ' . $deptLabel) : '');

    $defs = array(
        1 => array('label' => 'الإنشاء والإرسال', 'owner' => 'مقدّم الطلب'),
        2 => array('label' => 'اعتماد الإدارة',   'owner' => $ownerMgr),
        3 => array('label' => 'مكتب المحاسب',     'owner' => finreq_role_name($gate, 18)),
        4 => array('label' => 'الاعتماد المالي',  'owner' => finreq_role_name($gate, 17)),
        5 => array('label' => 'القيد',            'owner' => finreq_role_name($gate, 17)),
        6 => array('label' => $isCollection ? 'التحصيل' : 'الصرف', 'owner' => finreq_role_name($gate, 21)),
        7 => array('label' => 'الإغلاق',          'owner' => ''),
    );

    // ── ④ التأخر: مهلةُ المرحلة المختومة (§8.1) ─────────────────────────
    $isStopped = in_array($state, $stopped, true);
    $overdue = (!empty($req['sla_due_at'])
        && strtotime(strval($req['sla_due_at'])) < time()
        && !$isStopped
        && !in_array($state, array('closed', 'archived'), true));

    $stages = array();
    foreach ($defs as $k => $d) {
        if     ($k <  $current) { $status = 'done'; }
        elseif ($k === $current) { $status = $isStopped ? 'off' : 'current'; }
        else                     { $status = $isStopped ? 'off' : 'todo'; }
        $row = array('label' => $d['label'], 'status' => $status);
        if (isset($at[$k]))    { $row['at'] = $at[$k]; }
        if ($d['owner'] !== '') { $row['owner'] = $d['owner']; }
        if ($status === 'current' && $overdue) { $row['overdue'] = true; }
        $stages[] = $row;
    }

    $j = array('stages' => $stages);

    // ── ⑤ سطر الخطوة التالية — «باسم صاحبها» ────────────────────────────
    if (!$isStopped && $current >= 1 && $current <= 7) {
        // بدايةُ المرحلة الحالية = زمنُ آخر إنجازٍ قبلها، وإلا فإنشاءُ الطلب
        $since = strval($req['created_at']);
        for ($k = $current - 1; $k >= 1; $k--) { if (isset($at[$k])) { $since = $at[$k]; break; } }
        $j['next'] = array(
            'label'   => $defs[$current]['label'],
            'owner'   => $defs[$current]['owner'],
            'since'   => $since,
            'overdue' => $overdue,
        );
    }

    // ── ⑥ اللافتة: إعادةٌ بسببها · توقفٌ · اكتمال ───────────────────────
    $states = finreq_states();
    if ($state === 'returned') {
        $j['banner'] = array(
            'kind'  => 'return',
            'title' => 'أُعيد إليك لاستكمال:',
            'text'  => $lastReturn ? strval($lastReturn['body']) : 'راجع الحقول الناقصة وأعد الإرسال.',
            'meta'  => ($lastReturn ? (ems_journey_ago($lastReturn['created_at']) . ' · ') : '')
                       . 'بالرقم نفسه ' . strval($req['request_no']),
        );
    } elseif ($isStopped) {
        $j['banner'] = array(
            'kind'  => 'stop',
            'title' => 'توقفت الرحلة: ' . (isset($states[$state]) ? $states[$state]['label'] : $state),
            'text'  => ($state === 'rejected' && !empty($req['rejection_class']))
                       ? ('تصنيف الرفض: ' . strval($req['rejection_class'])) : '',
        );
    } elseif ($state === 'suspended') {
        $j['banner'] = array('kind' => 'stop', 'title' => 'الطلب معلَّق',
            'text' => 'الرحلة متوقفةٌ مؤقتًا بقرار الإدارة المالية حتى الاستئناف.');
    } elseif ($state === 'closed' || $state === 'archived') {
        $j['banner'] = array(
            'kind'  => 'done',
            'title' => ($state === 'closed') ? 'اكتملت الرحلة وأُغلق الطلب.' : 'اكتملت الرحلة وأُرشف الطلب.',
            'meta'  => isset($at[7]) ? ems_journey_ago($at[7]) : '',
        );
    }

    return $j;
}

/**
 * BR-CEO-05 (M-00): الرفعُ الآلي للاعتماد الأعلى عند تجاوز حدَّي DEC-01 ③
 * — يُقاس عند الإرسال لا بعد التنفيذ: الأثرُ > min(5٪ من المرجع الشهري للعقد،
 * 10,000$ معادلًا). قاعدةُ عدم التلفيق نافذة: بلا سعر صرفٍ لا حدَّ دولاريًّا،
 * وبلا عقدٍ مرجعيٍّ لا حدَّ نسبيًّا — وما لا يُقاس بأحدهما يُتخطى معلَنًا.
 * الرفع = صفٌّ حقيقيٌّ في شاشة الاعتماد الأعلى (idempotent برقم الطلب)
 * + تنبيهٌ للتنفيذي. فشلُه لا يعطّل الإرسال (fail-soft بسجل).
 * @return true رُفع · false دون الحدين · null لم يُقَس (معلَن)
 */
function finreq_gm_escalate(mysqli $conn, $gate, array $req)
{
    try {
        require_once __DIR__ . '/../app/Services/Governance/UnitStateChangeService.php';
        require_once __DIR__ . '/../includes/fx.php';

        $amount = abs((float) $req['amount']);
        $cur    = strval($req['currency'] ?: '');
        if ($amount <= 0) { return null; }

        // ── سقفُ الإدارة المعلن (M-00 §5-1 · exec_dept_caps) — الحدُّ الأول ──
        // «تجاوزُ سقف الإدارة يرفع المستندَ آليًّا» — السقفُ الساري لإدارة
        // الطلب بعملته؛ وإن لم يُعلَن سقفٌ قِيسَ على حدَّي DEC-01 ③ أدناه.
        $DEPT_AR = array(
            'sales' => 'المبيعات والعقود', 'suppliers' => 'إدارة الموردين',
            'workforce' => 'الموارد البشرية', 'procurement' => 'المشتريات',
            'maintenance' => 'الصيانة', 'treasury' => 'المالية والخزينة',
            'assets' => 'إدارة الأسطول', 'movement' => 'إدارة التشغيل',
            'transport' => 'إدارة التشغيل', 'projects' => 'إدارة التشغيل',
            'sites' => 'إدارة التشغيل', 'general' => 'المالية والخزينة',
            'admin' => 'المالية والخزينة',
        );
        $deptAr = $DEPT_AR[strval($req['source_module'])] ?? null;
        $deptCap = null;
        if ($deptAr !== null) {
            $st = $conn->prepare("SELECT cap_amount FROM exec_dept_caps
                WHERE company_id = ? AND dept_name = ? AND currency = ?
                  AND effective_from <= CURDATE()
                  AND (effective_to IS NULL OR effective_to >= CURDATE())
                ORDER BY effective_from DESC LIMIT 1");
            $coQ = intval($req['company_id']);
            $st->bind_param('iss', $coQ, $deptAr, $cur);
            $st->execute();
            $w = $st->get_result()->fetch_assoc();
            $st->close();
            if ($w !== null) { $deptCap = (float) $w['cap_amount']; }
        }
        $deptCapHit = ($deptCap !== null && $amount > $deptCap);

        // المرجع الشهري من العقد المربوط (بعملة العقد — التصريح في سبب الرفع)
        $monthlyRef = null;
        if (!empty($req['contract_id'])) {
            $st = $conn->prepare("SELECT total_contract_permonth FROM contracts WHERE id = ? LIMIT 1");
            $cid = intval($req['contract_id']);
            $st->bind_param('i', $cid);
            $st->execute();
            $w = $st->get_result()->fetch_assoc();
            $st->close();
            if ($w && (float) $w['total_contract_permonth'] > 0) { $monthlyRef = (float) $w['total_contract_permonth']; }
        }

        // المعادل الدولاري: عملةُ الأساس معادلُها ذاتُها، وسواها عبر جدول الأسعار
        // — لا سعرَ فلا حدَّ دولاريًّا (لا 1:1 ملفَّقة). التحويل بمعزلٍ (بوابة
        // الأسعار جلسية وقد تغيب في CLI) وغيابُه لا يُسقط التقدير النسبي.
        $usd = null;
        try {
            $code = function_exists('ems_fx_code') ? ems_fx_code($cur) : null;
            $baseC = function_exists('ems_fx_base_currency') ? ems_fx_base_currency() : null;
            if ($code !== null && $baseC !== null && $code === $baseC) {
                $usd = $amount; // الأساس نفسه — تعادلٌ لا تحويل
            } elseif ($code !== null && function_exists('ems_fx_to_base')) {
                $fx = ems_fx_to_base($amount, $cur, date('Y-m-d'));
                if (is_array($fx) && !empty($fx['ok'])) { $usd = (float) $fx['base']; }
            }
        } catch (\Throwable $t) {
            error_log('finreq_gm_escalate fx: ' . $t->getMessage());
        }

        if (!$deptCapHit && $usd === null && $monthlyRef === null) {
            error_log('finreq_gm_escalate: ' . $req['request_no'] . ' لا سقفَ معلنًا ولا سعر ولا مرجع شهري — لم يُقَس (معلَن)');
            return null;
        }

        if ($deptCapHit) {
            $assess = array('needs_gm' => true, 'reason' =>
                'BR-CEO-05: تجاوزُ سقف ' . $deptAr . ' المعلن ('
                . number_format($deptCap, 2) . ' ' . $cur . ') — رفعٌ آليٌّ لا اختياري');
        } else {
            $assess = \App\Services\Governance\UnitStateChangeService::assessGmNeed(
                $amount, $monthlyRef, ($usd !== null ? $usd : 0.0), '');
            if (empty($assess['needs_gm'])) { return false; }
        }

        // idempotent: صفُّ الاعتماد الأعلى بمرجع الطلب المصدري لا يتكرر (يشمل resubmit)
        // — الوجهة صارت الجدول الأصلي exec_approvals (لحاق CMP03_FOLLOWUP 2026-11-14)
        $co = intval($req['company_id']);
        $no = strval($req['request_no']);
        $srcId = intval($req['id'] ?? 0);
        $st = $conn->prepare("SELECT id FROM exec_approvals
                               WHERE company_id = ? AND (source_request_id = ? OR request_no = ?) LIMIT 1");
        $st->bind_param('iis', $co, $srcId, $no);
        $st->execute();
        $dupe = $st->get_result()->fetch_assoc();
        $st->close();
        if ($dupe) { return true; }

        // السقفُ والتجاوزُ رقمان حين يُقاسان (سقف الإدارة أو حد النسبة) —
        // وما لا يُقاس يُترك فارغًا معلَنًا وتفصيلُه في سبب الرفع لا تلفيقًا
        $capNum = $deptCapHit ? $deptCap : (($monthlyRef !== null) ? round($monthlyRef * 0.05, 2) : null);
        $overNum = ($capNum !== null) ? round($amount - $capNum, 2) : null;
        $capS = ($capNum !== null) ? (string) $capNum : null;   // NULL من هنا لا NULLIF
        $overS = ($overNum !== null) ? (string) $overNum : null; // (خلط الترتيبات على الويب)
        $docTxt = mb_substr(strval($req['statement'] ?: $req['justification']), 0, 160);
        $typeTxt = 'طلب مالي (' . strval($req['request_type']) . ')';
        $deptTxt = ($deptAr !== null) ? $deptAr : strval($req['source_module']);
        $needBy = strval($req['needed_by'] ?: '48 ساعة');
        $today = date('Y-m-d');
        $amountS = (string) $amount;
        $creator = $deptCapHit ? 'آلي — BR-CEO-05 (سقف الإدارة المعلن)' : 'آلي — DEC-01 ③ (بوابة الطلب المالي)';
        $uid = intval($req['created_by'] ?: 0);
        $st = $conn->prepare("INSERT INTO exec_approvals
            (company_id, request_no, received_date, doc_type, document, requesting_dept,
             raise_reason, amount, currency, dept_cap, overage, deadline,
             status, source_request_id, source_kind, is_seed, created_by, created_by_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'قيد المراجعة', ?, 'آلي', 0, ?, ?)");
        $st->bind_param('isssssssssssiis',
            $co, $no, $today, $typeTxt, $docTxt, $deptTxt,
            $assess['reason'], $amountS, $cur, $capS, $overS, $needBy,
            $srcId, $uid, $creator);
        $st->execute() or error_log('finreq_gm_escalate insert: ' . $st->error);
        $rowId = intval($conn->insert_id);
        $st->close();

        if (function_exists('log_security_event')) {
            log_security_event('CEO_CEILING_ESCALATED', $no . ' → EXAP-' . $rowId . ' — ' . $assess['reason']);
        }
        // تنبيه التنفيذي الأول الحي في الشركة (fail-soft)
        try {
            require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
            $r9 = $conn->query("SELECT id FROM users WHERE role = '9' AND company_id = {$co}
                                 AND COALESCE(status,'active') = 'active' ORDER BY id LIMIT 1");
            $w9 = $r9 ? $r9->fetch_assoc() : null;
            if ($w9) {
                \App\Services\Work\WorkItemService::notifyUser($conn, $co, intval($w9['id']),
                    'تجاوزُ سقفٍ رفع آليًّا للاعتماد الأعلى: ' . $no,
                    $assess['reason'], 'Portal/ceo_approvals.php', true, $uid ?: 1);
            }
        } catch (\Throwable $t) { error_log('finreq_gm_escalate notify: ' . $t->getMessage()); }
        return true;
    } catch (\Throwable $t) {
        error_log('finreq_gm_escalate ' . strval($req['request_no'] ?? '?') . ': ' . $t->getMessage());
        return null;
    }
}
