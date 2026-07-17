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
    $out = array('FinRequests/dept_inbox.php' => 0, 'FinRequests/accountant_desk.php' => 0, 'FinRequests/my_requests.php' => 0);
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
        $out['FinRequests/my_requests.php'] = intval($gate->count('fin_requests', array(
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

/** رفع مستندٍ إلى التخزين المحمي storage/finreq (نمط كرت المعدة حرفيًا) */
function finreq_upload($field)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $dir = __DIR__ . '/../storage/finreq/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (function_exists('validate_file_upload')) {
        $check = validate_file_upload($_FILES[$field], array('jpg', 'jpeg', 'png', 'webp', 'pdf'), 5 * 1024 * 1024);
        if (empty($check['valid'])) { return null; }
    }
    $name = function_exists('generate_safe_filename')
        ? generate_safe_filename($_FILES[$field]['name'])
        : (bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION)));
    if (@move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name)) {
        return 'storage/finreq/' . $name;
    }
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

/** شارة حالةٍ جاهزة للعرض */
function finreq_state_badge($state)
{
    $states = finreq_states();
    $s = isset($states[$state]) ? $states[$state] : array('label' => $state, 'badge' => 'secondary');
    return '<span class="badge bg-' . $s['badge'] . '">' . htmlspecialchars($s['label']) . '</span>';
}
