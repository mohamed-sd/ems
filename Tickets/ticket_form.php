<?php
/**
 * Tickets/ticket_form.php — استمارة البلاغ وشاشة إدارته.
 *
 * وضعان:
 *   • إنشاء (بلا ?id) — بخطوةٍ واحدة: اختيارُ النوع يُسنِد الإدارة المالكة
 *     فتُوجَّه التذكرة فورًا. متاحٌ لكل مستخدم مسجَّل (كشاشة المراسلات).
 *   • عرض وإدارة (?id=N) — أربع طبقات: المصدر · التصنيف والوزن · سير العمل
 *     والملكية · التتبّع (خطّ الزمن والمرفقات والتعليق).
 *
 * دورة الحياة: الانتقالات بالأزرار حصرًا وبيد فريق البلاغات؛ والإدارات
 * المنفِّذة والمُبلِّغ يتابعون ويعلّقون فقط. كل انتقالٍ أو تحويلٍ يُقيَّد حدثًا
 * دائمًا؛ والتحويل يُسجَّل في ticket_transfers بسببٍ إلزامي؛ والإلغاء وإعادة
 * الفتح يحتاجان صلاحيةً أعلى وسببًا إلزاميًّا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx             = tkt_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];
$current_role_id = intval($ctx['role']);
$is_tickets_mgr  = ($ctx['role'] === EMS_ROLE_TICKETS_MGR);

if ($company_id <= 0 && !$is_super_admin) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', '');
    exit();
}

$conditions = tkt_machine_conditions();
$natures    = tkt_natures();
$stages_map = tkt_stages();
$priorities = tkt_priorities();
$impacts    = tkt_impacts();
$roles_map  = tkt_roles_map();
$ev_labels  = tkt_event_type_labels();

// صلاحيات فريق البلاغات على هذه الشاشة (بقية المستخدمين: عرضٌ وتعليقٌ فقط)
$perms = tkt_page_perms($conn, 'Tickets/ticket_form.php', $is_super_admin);
$can_manage = ($is_super_admin || $is_tickets_mgr) && $perms['can_edit'];
$can_admin  = ($is_super_admin || $is_tickets_mgr) && $perms['can_delete'];

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0);
$ticket = null;
if ($ticket_id > 0) {
    $ticket = tkt_gate($is_super_admin)->selectOne('tickets', array('where' => array('id' => $ticket_id)));
    if (!$ticket || !tkt_can_view_ticket($ticket, $ctx)) {
        ems_gov_flash_redirect('tickets_list.php', 'التذكرة غير موجودة أو خارج نطاقك ❌', 'GOV-SCOPE-403', '');
        exit();
    }
}
$self_url = 'ticket_form.php' . ($ticket_id > 0 ? ('?id=' . $ticket_id) : '');
$is_final = $ticket && in_array($ticket['stage'], array('closed', 'cancelled'), true);

// ══════════════════════════════════════════════════════════════════════════
// ① إنشاء بلاغ (بلا id) — متاحٌ لكل مستخدم مسجّل
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket_id === 0 && isset($_POST['complaint'])) {
    if ($company_id <= 0) { ems_gov_flash_redirect('ticket_form.php', 'لا يمكن الإبلاغ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }
    $type_id          = intval($_POST['ticket_type_id'] ?? 0);
    $complaint        = trim($_POST['complaint'] ?? '');
    $reporting_person = trim($_POST['reporting_person'] ?? '');
    $reporter_contact = trim($_POST['reporter_contact'] ?? '');
    $equipment_id     = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $project_id       = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $machine_condition = trim($_POST['machine_condition'] ?? '');
    $meter_reading    = trim($_POST['meter_reading'] ?? '');
    if ($machine_condition !== '' && !array_key_exists($machine_condition, $conditions)) { $machine_condition = ''; }
    $meter_val = ($meter_reading === '') ? null : floatval($meter_reading);

    if ($type_id <= 0 || $complaint === '' || $reporting_person === '') {
        ems_gov_flash_redirect('ticket_form.php', 'النوع والوصف واسم المُبلِّغ إلزامية ❌', 'GOV-FAIL-409', ''); exit();
    }
    $type = tkt_gate(false)->selectOne('ticket_types', array(
        'columns' => array('id', 'owner_role_id', 'default_nature'),
        'where'   => array('id' => $type_id, 'active' => 1)));
    if (!$type) { ems_gov_flash_redirect('ticket_form.php', 'نوع البلاغ غير صالح ❌', 'GOV-FAIL-409', ''); exit(); }

    // الرقم يُخصَّص قبل المعاملة: تخصيصه بداخلها كان يجعل أيَّ ارتدادٍ
    // يتراجع بالعدّاد فتعلق الشاشة تطلب رقمًا محجوزًا في كل محاولةٍ تالية.
    try {
        $ticket_no = tkt_next_ticket_no($conn, $company_id);
    } catch (\Throwable $e) {
        error_log('ticket number allocation failed: ' . $e->getMessage());
        ems_gov_flash_redirect('ticket_form.php', 'تعذر إصدار رقم البلاغ ❌', 'GOV-FAIL-409', ''); exit();
    }
    try {
        tkt_gate(false)->runInTransaction(function ($g) use (
            $conn, $company_id, $type, $type_id, $complaint, $reporting_person, $reporter_contact,
            $equipment_id, $project_id, $machine_condition, $meter_val,
            $current_user_id, $current_role_id, $ticket_no
        ) {
            $tid = $g->insert('tickets', array(
                'ticket_no' => $ticket_no, 'ticket_type_id' => $type_id,
                'stage' => 'routed',   // التوجيه فوريٌّ بحسب النوع المختار
                'ticket_nature' => $type['default_nature'],
                'call_date' => date('Y-m-d'), 'call_time' => date('H:i'),
                'reporting_person' => $reporting_person,
                'reporter_contact' => ($reporter_contact === '') ? null : $reporter_contact,
                'reporter_user_id' => $current_user_id,
                'project_id' => $project_id, 'equipment_id' => $equipment_id,
                'machine_condition' => ($machine_condition === '') ? null : $machine_condition,
                'meter_reading' => $meter_val, 'complaint' => $complaint,
                'owner_role_id' => intval($type['owner_role_id']),
                'created_by' => $current_user_id,
            ));
            $g->insert('ticket_events', array(
                'ticket_id' => $tid, 'event_type' => 'system',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'إنشاء البلاغ وتوجيهه تلقائيًا بحسب النوع', 'new_value' => 'routed',
            ));
            $g->insert('ticket_watchers', array(
                'ticket_id' => $tid, 'user_id' => $current_user_id,
                'role_id' => $current_role_id, 'watch_reason' => 'reporter',
            ));
            // مواعيد الاستحقاق تُحسب عند الإنشاء من السياسة الأكثر تحديدًا
            tkt_apply_sla($g, $tid, $type_id, 'normal', 'admin', date('Y-m-d'), date('H:i'));
        }, 'create ticket (one-step routed)');
    } catch (\Throwable $e) {
        error_log('ticket create failed: ' . $e->getMessage());
        ems_gov_flash_redirect('ticket_form.php', 'حدث خطأ أثناء تسجيل البلاغ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('tickets_list.php', 'تم تسجيل البلاغ ' . $ticket_no . ' وتوجيهه بنجاح ✅', 'GOV-OK-200', '');
    exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ② انتقالات دورة الحياة — بيد فريق البلاغات حصرًا
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'transition') {
    $do     = trim($_POST['do'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $tr_map = tkt_transitions();
    if (!isset($tr_map[$do])) { ems_gov_redirect("Location: $self_url&msg=انتقال+غير+معروف+❌"); exit(); }
    $tr = $tr_map[$do];
    $allowed = ($tr['need'] === 'delete') ? $can_admin : $can_manage;
    if (!$allowed) { ems_gov_redirect("Location: $self_url&msg=الانتقالات+بيد+فريق+البلاغات+❌"); exit(); }
    if (!tkt_transition_allows($tr, $ticket['stage'])) { ems_gov_redirect("Location: $self_url&msg=المرحلة+الحالية+لا+تسمح+بهذا+الانتقال+❌"); exit(); }
    if ($tr['reason'] && $reason === '') { ems_gov_redirect("Location: $self_url&msg=السبب+إلزامي+لهذا+الانتقال+❌"); exit(); }
    // التذكرة الرئيسية لا تُغلق قبل فروعها — حارسٌ خادميٌّ لا واجهيّ
    if ($do === 'close') {
        $open_children = tkt_open_children_count($ticket_id);
        if ($open_children > 0) {
            ems_gov_redirect("Location: $self_url&msg=" . urlencode('لا يمكن الإغلاق: ' . $open_children . ' فرعًا ما زال مفتوحًا ❌')); exit();
        }
        // ولا تُغلق ومسارٌ إلزاميٌّ مفتوح — وإلا انفصل رأسُ البلاغ عن تنفيذه
        $open_ws = tkt_open_mandatory_ws_count(tkt_gate(false), $ticket_id);
        if ($open_ws > 0) {
            ems_gov_redirect("Location: $self_url&msg=" . urlencode('لا يمكن الإغلاق: ' . $open_ws . ' مسارًا إلزاميًّا ما زال مفتوحًا ❌')); exit();
        }
    }

    try {
        tkt_gate(false)->runInTransaction(function ($g) use ($ticket, $ticket_id, $tr, $do, $reason, $current_user_id, $current_role_id) {
            $upd = array('stage' => $tr['to']);
            if ($tr['to'] === 'in_progress' && empty($ticket['first_action_at'])) {
                $upd['first_action_at'] = date('Y-m-d H:i:s');          // لقياس زمن الاستجابة
            }
            if ($tr['to'] === 'closed') {
                /* P1-B — «من رفع البلاغَ لا يُقفله»: الإقفالُ شهادةُ معالجةٍ من
                   الجهةِ المعالِجة، لا من المُبلِّغ. (ونمطُ «لا تُغلق من الإدارةِ
                   نفسِها» هو عينُه في ملاحظاتِ المراجعة.) */
                require_once __DIR__ . '/../includes/self_approval_guard.php';
                $__sa = ems_no_self_approval($conn, intval($ticket['created_by'] ?? 0), intval($current_user_id),
                    'بلاغٌ ' . (string) ($ticket['ticket_no'] ?? ('#' . ($ticket['id'] ?? ''))),
                    intval($ticket['company_id'] ?? 0));
                if ($__sa !== null) {
                    ems_gov_flash_redirect('tickets_list.php', $__sa['reason'], 'GOV-PERM-403',
                        'الإقفالُ من الجهةِ المعالِجةِ لا من المُبلِّغ');
                    exit();
                }
                $upd['close_date'] = date('Y-m-d');
                $upd['close_time'] = date('H:i');
                $upd['closed_by']  = $current_user_id;
            }
            // التوجيه يستكمل ما ينقص البلاغ القادم من المسار البرمجي:
            // إدارةً مالكةً من نوعه إن كان بلا مالك، ومهلةً إن لم تُحسب له.
            if ($do === 'route' && intval($ticket['owner_role_id']) <= 0) {
                $ty = $g->selectOne('ticket_types', array(
                    'columns' => array('owner_role_id'),
                    'where'   => array('id' => intval($ticket['ticket_type_id']))));
                if ($ty && intval($ty['owner_role_id']) > 0) { $upd['owner_role_id'] = intval($ty['owner_role_id']); }
            }
            $g->update('tickets', $upd, array('id' => $ticket_id));
            if ($do === 'route' && empty($ticket['resolution_due_at'])) {
                tkt_apply_sla($g, $ticket_id, intval($ticket['ticket_type_id']), $ticket['priority'],
                              $ticket['business_impact'], $ticket['call_date'], $ticket['call_time']);
            }
            // الرأس يتبع المرحلة للبلاغات عديمة المسارات — وإلا بقي مفتوحًا بعد الإغلاق
            tkt_sync_head_state($g, $ticket_id, $tr['to']);
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'status_change',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => $tr['label'] . ($reason !== '' ? ' — السبب: ' . $reason : ''),
                'old_value' => $ticket['stage'], 'new_value' => $tr['to'],
            ));
        }, 'ticket transition ' . $do);
    } catch (\Throwable $e) {
        error_log('ticket transition failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+تنفيذ+الانتقال+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('تم: ' . $tr['label'] . ' ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ③ تحويل الملكية — قيدٌ دائمٌ بسببٍ إلزامي
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'transfer') {
    if (!$can_manage) { ems_gov_redirect("Location: $self_url&msg=التحويل+بيد+فريق+البلاغات+❌"); exit(); }
    if ($is_final)    { ems_gov_redirect("Location: $self_url&msg=لا+تحويل+بعد+الإغلاق/الإلغاء+❌"); exit(); }
    $to_role = intval($_POST['to_role_id'] ?? 0);
    $reason  = trim($_POST['reason'] ?? '');
    if (!in_array($to_role, tkt_owner_role_ids(), true)) { ems_gov_redirect("Location: $self_url&msg=الإدارة+الهدف+غير+صالحة+❌"); exit(); }
    if ($reason === '') { ems_gov_redirect("Location: $self_url&msg=سبب+التحويل+إلزامي+❌"); exit(); }
    if ($to_role === intval($ticket['owner_role_id'])) { ems_gov_redirect("Location: $self_url&msg=التذكرة+لدى+هذه+الإدارة+أصلًا+❌"); exit(); }

    try {
        tkt_gate(false)->runInTransaction(function ($g) use ($ticket, $ticket_id, $to_role, $reason, $current_user_id, $current_role_id, $roles_map) {
            $from_role = intval($ticket['owner_role_id']);
            $g->update('tickets', array('owner_role_id' => $to_role, 'assigned_user_id' => null), array('id' => $ticket_id));
            $g->insert('ticket_transfers', array(
                'ticket_id' => $ticket_id,
                'from_role_id' => $from_role, 'to_role_id' => $to_role,
                'from_user_id' => ($ticket['assigned_user_id'] !== null) ? intval($ticket['assigned_user_id']) : null,
                'transferred_by' => $current_user_id, 'reason' => $reason,
            ));
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'transfer',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'تحويل الملكية — السبب: ' . $reason,
                'old_value' => tkt_label($roles_map, $from_role), 'new_value' => tkt_label($roles_map, $to_role),
            ));
        }, 'ticket ownership transfer');
    } catch (\Throwable $e) {
        error_log('ticket transfer failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+التحويل+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('تم تحويل الملكية إلى ' . tkt_label($roles_map, $to_role) . ' ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ④ إلغاء التذكرة — حالةٌ تُسجَّل لا محوٌ للسجل، بصلاحيةٍ أعلى وسببٍ إلزامي
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'cancel') {
    if (!$can_admin) { ems_gov_redirect("Location: $self_url&msg=الإلغاء+بصلاحية+مدير+البلاغات+❌"); exit(); }
    if ($is_final)   { ems_gov_redirect("Location: $self_url&msg=التذكرة+منتهية+أصلًا+❌"); exit(); }
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') { ems_gov_redirect("Location: $self_url&msg=سبب+الإلغاء+إلزامي+❌"); exit(); }
    try {
        tkt_gate(false)->runInTransaction(function ($g) use ($ticket, $ticket_id, $reason, $current_user_id, $current_role_id) {
            $g->update('tickets', array('stage' => 'cancelled'), array('id' => $ticket_id));
            // الإلغاء إجهاضٌ إداريٌّ يعلو على المسارات: تُقفَل إداريًّا (لا تُحذف)
            // ليتّسق الرأسُ مع تنفيذه بدل أن يبقى مسارٌ حيًّا لبلاغٍ ملغى.
            tkt_close_open_workstreams($g, $ticket_id);
            tkt_sync_head_state($g, $ticket_id, 'cancelled');
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'status_change',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'إلغاء التذكرة — السبب: ' . $reason,
                'old_value' => $ticket['stage'], 'new_value' => 'cancelled',
            ));
        }, 'ticket cancel');
    } catch (\Throwable $e) {
        error_log('ticket cancel failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+الإلغاء+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('أُلغيت التذكرة (تبقى في السجل للتدقيق) ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ⑤ ضبط التصنيف والوزن والإسناد — فريق البلاغات، قبل إغلاق التذكرة
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'refine') {
    if (!$can_manage) { ems_gov_redirect("Location: $self_url&msg=الصقل+بيد+فريق+البلاغات+❌"); exit(); }
    if ($is_final)    { ems_gov_redirect("Location: $self_url&msg=الحقول+مقفولة+بعد+الإغلاق/الإلغاء+❌"); exit(); }
    $category_id  = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $priority     = trim($_POST['priority'] ?? 'normal');
    $impact       = trim($_POST['business_impact'] ?? 'admin');
    $prod_crit    = isset($_POST['production_critical']) ? 1 : 0;
    $assigned     = !empty($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : null;
    $service_team = trim($_POST['service_team'] ?? '');
    $issue_status = trim($_POST['issue_status'] ?? '');
    if (!array_key_exists($priority, $priorities)) { $priority = 'normal'; }
    if (!array_key_exists($impact, $impacts))      { $impact = 'admin'; }
    if ($service_team !== '' && !in_array($service_team, array('internal', 'external_workshop'), true)) { $service_team = ''; }
    try {
        tkt_gate(false)->runInTransaction(function ($g) use ($ticket, $ticket_id, $category_id, $priority, $impact, $prod_crit, $assigned, $service_team, $issue_status, $current_user_id, $current_role_id) {
            $g->update('tickets', array(
                'category_id' => $category_id, 'priority' => $priority,
                'business_impact' => $impact, 'production_critical' => $prod_crit,
                'assigned_user_id' => $assigned,
                'service_team' => ($service_team === '') ? null : $service_team,
                'issue_status' => ($issue_status === '') ? null : $issue_status,
            ), array('id' => $ticket_id));
            // تغيّرُ الوزن أو الأولوية يستوجب إعادة حساب مواعيد الاستحقاق
            tkt_apply_sla($g, $ticket_id, intval($ticket['ticket_type_id']), $priority, $impact,
                          $ticket['call_date'], $ticket['call_time']);
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'note',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'صقل التصنيف/الوزن/الإسناد وإعادة حساب الاستحقاق (فريق البلاغات)',
            ));
        }, 'ticket refine');
    } catch (\Throwable $e) {
        error_log('ticket refine failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+الحفظ+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('حُفظ التصنيف والإسناد ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ⑤ب تفريع تذكرة — فرعٌ مربوطٌ بالأصل يُوجَّه بحسب نوعه الخاص
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'branch') {
    if (!$can_manage) { ems_gov_redirect("Location: $self_url&msg=التفريع+بيد+فريق+البلاغات+❌"); exit(); }
    if ($is_final)    { ems_gov_redirect("Location: $self_url&msg=لا+تفريع+بعد+الإغلاق/الإلغاء+❌"); exit(); }
    if ($ticket['parent_id'] !== null) { ems_gov_redirect("Location: $self_url&msg=لا+تفريع+من+تذكرةٍ+فرعية+❌"); exit(); }
    $child_type = intval($_POST['child_type_id'] ?? 0);
    $child_desc = trim($_POST['child_complaint'] ?? '');
    if ($child_type <= 0 || $child_desc === '') { ems_gov_redirect("Location: $self_url&msg=نوع+الفرع+ووصفه+إلزاميان+❌"); exit(); }
    $ctype = tkt_gate(false)->selectOne('ticket_types', array(
        'columns' => array('id', 'owner_role_id', 'default_nature'),
        'where'   => array('id' => $child_type, 'active' => 1)));
    if (!$ctype) { ems_gov_redirect("Location: $self_url&msg=نوع+الفرع+غير+صالح+❌"); exit(); }

    // رقم الفرع يُخصَّص قبل المعاملة لذات سبب الإنشاء (ارتدادٌ = عدّادٌ متراجع).
    try {
        $child_no = tkt_next_ticket_no($conn, $company_id);
    } catch (\Throwable $e) {
        error_log('ticket branch number allocation failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+إصدار+رقم+الفرع+❌"); exit();
    }
    try {
        tkt_gate(false)->runInTransaction(function ($g) use (
            $conn, $company_id, $ticket, $ticket_id, $ctype, $child_type, $child_desc,
            $current_user_id, $current_role_id, $child_no
        ) {
            $cid_new = $g->insert('tickets', array(
                'ticket_no' => $child_no, 'ticket_type_id' => $child_type,
                'stage' => 'routed', 'ticket_nature' => $ctype['default_nature'],
                'priority' => $ticket['priority'], 'business_impact' => $ticket['business_impact'],
                'call_date' => date('Y-m-d'), 'call_time' => date('H:i'),
                'reporting_person' => $ticket['reporting_person'],
                'reporter_user_id' => $current_user_id,
                'project_id' => $ticket['project_id'], 'equipment_id' => $ticket['equipment_id'],
                'complaint' => $child_desc,
                'owner_role_id' => intval($ctype['owner_role_id']),
                'parent_id' => $ticket_id, 'ticket_role' => 'child',
                'created_by' => $current_user_id,
            ));
            tkt_apply_sla($g, $cid_new, $child_type, $ticket['priority'], $ticket['business_impact'], date('Y-m-d'), date('H:i'));
            // الأصل يصير رئيسية
            $g->update('tickets', array('is_parent' => 1, 'ticket_role' => 'parent'), array('id' => $ticket_id));
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'system',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'تفريع تذكرة: ' . $child_no . ' — ' . $child_desc,
            ));
            $g->insert('ticket_events', array(
                'ticket_id' => $cid_new, 'event_type' => 'system',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'فرعٌ مُولَّد عن التذكرة الرئيسية ' . $ticket['ticket_no'], 'new_value' => 'routed',
            ));
            $g->insert('ticket_watchers', array(
                'ticket_id' => $cid_new, 'user_id' => $current_user_id,
                'role_id' => $current_role_id, 'watch_reason' => 'reporter',
            ));
        }, 'ticket branch (create child)');
    } catch (\Throwable $e) {
        error_log('ticket branch failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+التفريع+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('أُنشئ الفرع ' . $child_no . ' ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ⑤ج إصدار أمر صيانة من التذكرة
//
// يُنشئ أمرًا في وحدة الصيانة ويربط التذكرة به عبر linked_ref (ربطٌ لا تكرار
// للبيانات) ويسجّل حدثًا في خطّ الزمن. الصلاحية: فريق البلاغات أو مستخدم
// الصيانة. لا يُصدَر أمرٌ ثانٍ لتذكرةٍ مرتبطةٍ سلفًا.
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'issue_mnt_order') {
    require_once __DIR__ . '/../Maintenance/mnt_helpers.php';
    $is_mnt_user = function_exists('mnt_user_is_maintenance') ? mnt_user_is_maintenance($conn) : false;
    if (!$can_manage && !$is_mnt_user) {
        ems_gov_redirect("Location: $self_url&msg=إصدار+أمر+الصيانة+لفريق+البلاغات+أو+الصيانة+❌"); exit();
    }
    if ($is_final) { ems_gov_redirect("Location: $self_url&msg=لا+إصدار+بعد+الإغلاق/الإلغاء+❌"); exit(); }
    if (!empty($ticket['linked_ref_id']) && $ticket['linked_ref_table'] === 'mnt_order') {
        ems_gov_redirect("Location: $self_url&msg=التذكرة+مرتبطةٌ+بأمر+صيانة+مسبقًا+❌"); exit();
    }

    $new_order_id = 0;
    try {
        tkt_gate(false)->runInTransaction(function ($g) use ($conn, $company_id, $ticket, $ticket_id, $current_user_id, $current_role_id, &$new_order_id) {
            $code = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
            $new_order_id = $g->insert('mnt_order', array(
                'code'         => $code,
                'equipment_id' => ($ticket['equipment_id'] !== null) ? intval($ticket['equipment_id']) : null,
                'project_id'   => ($ticket['project_id'] !== null) ? intval($ticket['project_id']) : null,
                'source'       => 'بلاغ',
                'state'        => 'بلاغ',
                'created_by'   => $current_user_id,
            ));
            $g->update('tickets', array(
                'linked_ref_table' => 'mnt_order', 'linked_ref_id' => $new_order_id,
            ), array('id' => $ticket_id));
            $g->insert('ticket_events', array(
                'ticket_id' => $ticket_id, 'event_type' => 'system',
                'actor_user_id' => $current_user_id, 'actor_role_id' => $current_role_id,
                'body' => 'صدر أمر صيانة من هذا البلاغ: ' . $code,
                'new_value' => 'mnt_order#' . $new_order_id,
            ));
        }, 'issue maintenance order from ticket');
    } catch (\Throwable $e) {
        error_log('issue mnt order from ticket failed: ' . $e->getMessage());
        ems_gov_redirect("Location: $self_url&msg=تعذر+إصدار+أمر+الصيانة+❌"); exit();
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('صدر أمر الصيانة وارتبط بالبلاغ ✅')); exit();
}

// ══════════════════════════════════════════════════════════════════════════
// ⑥ تعليق ومرفق — متاحٌ لكل من يرى التذكرة (سجلٌّ واحدٌ للتواصل)
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket && ($_POST['action'] ?? '') === 'comment') {
    $body = trim($_POST['body'] ?? '');
    $has_file = isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($body === '' && !$has_file) { ems_gov_redirect("Location: $self_url&msg=اكتب+تعليقًا+أو+أرفق+ملفًا+❌"); exit(); }
    if ($body !== '') {
        tkt_log_event($ticket_id, 'communication', $body, null, null, $current_user_id, $current_role_id);
    }
    if ($has_file) {
        $rel = tkt_save_attachment($ticket_id, $_FILES['attachment'], $current_user_id);
        if ($rel !== null) {
            tkt_log_event($ticket_id, 'attachment', 'أُرفق ملف: ' . $rel, null, null, $current_user_id, $current_role_id);
        }
    }
    ems_gov_redirect("Location: $self_url&msg=" . urlencode('أُضيف إلى سجل التواصل ✅')); exit();
}

$page_title = $ticket ? ('إيكوبيشن | تذكرة ' . $ticket['ticket_no']) : 'إيكوبيشن | بلاغ جديد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// بيانات العرض للتذكرة المفتوحة
$type_row = $cat_row = null; $events = $attachments = array();
if ($ticket) {
    $type_row = tkt_gate(false)->selectOne('ticket_types', array('columns' => array('name'), 'where' => array('id' => intval($ticket['ticket_type_id']))));
    if ($ticket['category_id'] !== null) {
        $cat_row = tkt_gate(false)->selectOne('ticket_categories', array('columns' => array('name'), 'where' => array('id' => intval($ticket['category_id']))));
    }
    $events = tkt_gate($is_super_admin)->select('ticket_events', array(
        'where' => array('ticket_id' => $ticket_id), 'orderBy' => 'id ASC'));
    $attachments = tkt_gate($is_super_admin)->select('ticket_attachments', array(
        'where' => array('ticket_id' => $ticket_id), 'orderBy' => 'id ASC'));
    $children = tkt_gate($is_super_admin)->select('tickets', array(
        'columns' => array('id', 'ticket_no', 'stage', 'owner_role_id', 'complaint'),
        'where' => array('parent_id' => $ticket_id), 'orderBy' => 'id ASC'));
    $transfers = tkt_gate($is_super_admin)->select('ticket_transfers', array(
        'where' => array('ticket_id' => $ticket_id), 'orderBy' => 'id ASC'));
}
?>

<div class="main tkt-form-main ems-unified-page-shell">
    <?php
    $header_title = $ticket ? ('تذكرة ' . $ticket['ticket_no']) : 'تسجيل بلاغ جديد';
    $header_icon  = 'fa fa-tower-observation';
    $header_actions = array();
    $header_actions[] = array('tag' => 'a', 'href' => 'tickets_list.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-list', 'label' => 'قائمة البلاغات');
    if ($ticket === null) {
        // زر القائمة يكفي
    }
    $header_back = array('href' => 'tickets_list.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بلاغَ بهذا الرقم — أو لم يُسجَّل بعدُ', 'ارجع إلى قائمةِ البلاغاتِ واختر بلاغًا، أو سجّل بلاغًا جديدًا من هذه الشاشة');
    ?>
    <style>
    /* UXW-01 ②: أنماطُ شاشةِ البلاغِ الثابتة — بادئةُ الشاشة tkt-frm- */
    .tkt-frm-desc-group { margin-top: 10px; }
    .tkt-frm-op-head    { margin: 14px 0 8px; }
    .tkt-frm-subtable   { width: 100%; }
    </style>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('ticket', 'السياقُ والمصدر'); ?>

    <?php tkt_msg_banner(); ?>

<?php if ($ticket !== null):
    /* شريط الرحلة (الدستور §5: «أعلى شاشة كل معاملة» · UX-01 §6.3) — أينَ
       وصل البلاغ، ومَن عليه الدور، وما سببُ وقفتِه إن كان موقوفًا. */
    require_once __DIR__ . '/../includes/journey_bar.php';
    ems_journey_bar(tkt_journey($ticket, $events));
endif; ?>

<?php if ($ticket === null): ?>
    <!-- ═══ وضع الإنشاء — بخطوة واحدة ═══ -->
    <form id="tktForm" action="" method="post" class="allforms allforms-visible">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-bullhorn"></i> بيانات البلاغ — الإدخال الأدنى (يُوجَّه تلقائيًا بحسب النوع)</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="f_type">نوع البلاغ <span class="required">*</span></label>
                        <select name="ticket_type_id" id="f_type" required><?php echo tkt_type_options(); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_511_b950d">اسم المُبلِّغ <span class="required">*</span></label>
                        <input type="text" name="reporting_person" id="emsf_511_b950d" required value="<?php echo htmlspecialchars((string)($_SESSION['user']['name'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emsf_512_b0d62">رقم التواصل</label>
                        <input type="text" name="reporter_contact" id="emsf_512_b0d62" value="<?php echo htmlspecialchars((string)($_SESSION['user']['phone'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emsf_513_f69dc">المعدة (اختياري)</label>
                        <select name="equipment_id" id="emsf_513_f69dc"><?php echo tkt_equipment_options(); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_514_30189">المشروع/الموقع (اختياري)</label>
                        <select name="project_id" id="emsf_514_30189"><?php echo tkt_project_options(); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_515_d6c7b">حالة المعدة وقت البلاغ</label>
                        <select name="machine_condition" id="emsf_515_d6c7b">
                            <option value="">— غير محدد —</option>
                            <?php foreach ($conditions as $k => $v): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_516_8c811">قراءة العدّاد</label>
                        <input type="number" step="0.01" min="0" name="meter_reading" placeholder="مثال: 12450.5" id="emsf_516_8c811">
                    </div>
                </div>
                <div class="form-group tkt-frm-desc-group">
                    <label for="emsf_517_77a94">وصف المشكلة / الطلب كما ورد <span class="required">*</span></label>
                    <textarea name="complaint" rows="4" required placeholder="صف المشكلة أو الطلب بوضوح..." id="emsf_517_77a94"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> تسجيل البلاغ</button>
                <a href="tickets_list.php" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

<?php else: ?>
    <!-- ═══ وضع العرض والإدارة ═══ -->

    <?php
    /* ① الرأسُ الموجز — هويةُ التذكرةِ وحقائقُها التي تُقرأ قبل أيِّ شيء:
         ما المشكلةُ · أين هي · ما وزنُها · هل كسرت مهلتَها. */
    $__overdue   = tkt_is_overdue($ticket);
    $__headline  = trim(preg_replace('~\s+~u', ' ', (string) $ticket['complaint']));
    if (mb_strlen($__headline) > 130) { $__headline = mb_substr($__headline, 0, 130) . '…'; }
    if ($__headline === '') { $__headline = 'بلاغٌ بلا وصفٍ مكتوب'; }
    ?>
    <section class="tkt-hero">
        <div class="tkt-hero__top">
            <span class="tkt-hero__no"><i class="fa fa-hashtag" aria-hidden="true"></i> <?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
            <h2 class="tkt-hero__title"><?php echo htmlspecialchars($__headline); ?></h2>
        </div>
        <div class="tkt-hero__chips">
            <?php echo tkt_stage_badge($ticket['stage']); ?>
            <?php echo tkt_stage_mini($ticket['stage'], $__overdue); ?>
            <span class="tkt-chip"><i class="fa fa-building-shield" aria-hidden="true"></i>
                <?php echo htmlspecialchars(tkt_label($roles_map, intval($ticket['owner_role_id']))); ?></span>
            <span class="tkt-chip<?php echo $ticket['priority'] === 'critical' ? ' is-danger' : ($ticket['priority'] === 'high' ? ' is-warn' : ''); ?>">
                <i class="fa fa-flag" aria-hidden="true"></i>
                <?php echo htmlspecialchars(tkt_label($priorities, $ticket['priority'])); ?></span>
            <span class="tkt-chip"><i class="fa fa-scale-balanced" aria-hidden="true"></i>
                <?php echo htmlspecialchars(tkt_label($impacts, $ticket['business_impact'])); ?></span>
            <?php if (intval($ticket['production_critical']) === 1): ?>
                <span class="tkt-chip is-danger"><i class="fa fa-bolt" aria-hidden="true"></i> يوقف الإنتاج</span>
            <?php endif; ?>
            <?php if ($__overdue): ?>
                <span class="tkt-chip is-danger"><i class="fa fa-hourglass-end" aria-hidden="true"></i> كسر مهلة الإنجاز</span>
            <?php elseif ($ticket['close_date']): ?>
                <span class="tkt-chip is-ok"><i class="fa fa-circle-check" aria-hidden="true"></i> أُغلق <?php echo htmlspecialchars((string) $ticket['close_date']); ?></span>
            <?php endif; ?>
        </div>
    </section>

    <div class="tkt-layout">
    <div class="tkt-col tkt-col--main">

        <!-- ما المشكلة -->
        <section class="tkt-panel">
            <div class="tkt-panel__head">
                <i class="fa fa-bullhorn" aria-hidden="true"></i>
                <h3>وصف المشكلة كما ورد</h3>
                <span class="tkt-chip"><i class="fa fa-lock" aria-hidden="true"></i> لا يُعدَّل بعد التسجيل</span>
            </div>
            <div class="tkt-panel__body">
                <div class="tkt-quote"><?php echo htmlspecialchars($ticket['complaint']); ?></div>
                <?php if ($ticket['issue_status']): ?>
                    <div class="tkt-op__k tkt-frm-op-head"><i class="fa fa-screwdriver-wrench" aria-hidden="true"></i> حالة المعالجة — تُقرأ من الإدارة المنفِّذة</div>
                    <div class="tkt-quote tkt-quote--status"><?php echo htmlspecialchars($ticket['issue_status']); ?></div>
                <?php endif; ?>
            </div>
        </section>

    <?php if ($can_manage && !$is_final): ?>
    <!-- ② صقل التصنيف والوزن والإسناد (فريق البلاغات) -->
    <section class="tkt-panel">
      <div class="tkt-panel__head">
        <i class="fa fa-sliders" aria-hidden="true"></i>
        <h3>التصنيف والوزن والإسناد</h3>
        <span class="tkt-chip"><i class="fa fa-user-shield" aria-hidden="true"></i> فريق البلاغات</span>
      </div>
      <form method="post" action="<?php echo htmlspecialchars($self_url); ?>" class="allforms allforms-visible">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="refine">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
        <div class="tkt-panel__body">
            <div class="form-grid">
                <div class="form-group"><label for="tkfCategory">التصنيف الفني</label>
                    <select name="category_id" id="tkfCategory">
                        <option value="">— بلا تصنيف —</option>
                        <?php
                        $cats = tkt_gate(false)->select('ticket_categories', array('columns' => array('id', 'name'), 'where' => array('active' => 1), 'orderBy' => 'id ASC'));
                        foreach ($cats as $c) {
                            $sel = (intval($ticket['category_id']) === intval($c['id'])) ? ' selected' : '';
                            echo '<option value="' . intval($c['id']) . '"' . $sel . '>' . htmlspecialchars($c['name']) . '</option>';
                        }
                        ?>
                    </select></div>
                <div class="form-group"><label for="emsf_518_11300">الأولوية</label>
                    <select name="priority" id="emsf_518_11300"><?php foreach ($priorities as $k => $v): ?><option value="<?php echo $k; ?>"<?php echo $ticket['priority'] === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="emsf_519_4395b">الوزن التشغيلي</label>
                    <select name="business_impact" id="emsf_519_4395b"><?php foreach ($impacts as $k => $v): ?><option value="<?php echo $k; ?>"<?php echo $ticket['business_impact'] === $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>يوقف الإنتاج؟</label>
                    <label class="switch-inline"><input type="checkbox" name="production_critical" aria-label="البلاغُ يوقف الإنتاج" value="1"<?php echo intval($ticket['production_critical']) === 1 ? ' checked' : ''; ?>> نعم</label></div>
                <div class="form-group"><label for="tkfAssigned">المسؤول المُسنَد</label>
                    <select name="assigned_user_id" id="tkfAssigned"><?php echo tkt_user_options(intval($ticket['assigned_user_id'])); ?></select></div>
                <div class="form-group"><label for="emsf_520_70558">فريق المعالجة</label>
                    <select name="service_team" id="emsf_520_70558">
                        <option value="">— غير محدد —</option>
                        <option value="internal"<?php echo $ticket['service_team'] === 'internal' ? ' selected' : ''; ?>>داخلي</option>
                        <option value="external_workshop"<?php echo $ticket['service_team'] === 'external_workshop' ? ' selected' : ''; ?>>ورشة خارجية</option>
                    </select></div>
            </div>
            <div class="form-group"><label for="emsf_521_49bd2">حالة المعالجة (تُقرأ من الإدارة المنفِّذة)</label>
                <textarea name="issue_status" rows="2" id="emsf_521_49bd2"><?php echo htmlspecialchars((string)$ticket['issue_status']); ?></textarea></div>
            <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ التصنيف</button></div>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <?php
    // ③ سير العمل: أزرار الانتقالات المتاحة من المرحلة الحالية
    $avail = array();
    foreach (tkt_transitions() as $key => $tr) {
        if (!tkt_transition_allows($tr, $ticket['stage'])) { continue; }
        $allowed = ($tr['need'] === 'delete') ? $can_admin : $can_manage;
        if ($allowed) { $avail[$key] = $tr; }
    }
    ?>
    <?php if (!empty($avail) || ($can_manage && !$is_final) || ($can_admin && !$is_final)): ?>
    <section class="tkt-panel">
      <div class="tkt-panel__head">
        <i class="fa fa-diagram-project" aria-hidden="true"></i>
        <h3>ما الخطوة التالية؟</h3>
        <?php if (!empty($avail)): ?>
            <span class="tkt-chip is-ok"><i class="fa fa-arrow-turn-down" aria-hidden="true"></i>
                <?php echo count($avail) === 1 ? 'إجراءٌ متاحٌ واحد' : count($avail) . ' إجراءاتٍ متاحة'; ?></span>
        <?php endif; ?>
      </div>
      <div class="tkt-panel__body">
        <?php if (!empty($avail)): ?>
        <div class="tkt-acts">
            <?php foreach ($avail as $key => $tr): ?>
                <form method="post" action="<?php echo htmlspecialchars($self_url); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="transition">
                    <input type="hidden" name="do" value="<?php echo htmlspecialchars($key); ?>">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                    <?php if ($tr['reason']): ?>
                        <input type="text" name="reason" required placeholder="السبب (إلزامي)" aria-label="السبب (إلزامي)">
                    <?php endif; ?>
                    <button type="submit" class="btn-primary">
                        <i class="fas <?php echo $tr['icon']; ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($tr['label']); ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="tkt-empty"><i class="fa fa-circle-check" aria-hidden="true"></i>
            لا انتقالَ متاحٌ لك من هذه المرحلة.</div>
        <?php endif; ?>

        <div class="tkt-ops">
        <?php if ($can_manage && !$is_final): ?>
        <div class="tkt-op">
            <div class="tkt-op__k"><i class="fa fa-right-left" aria-hidden="true"></i> تحويل الملكية إلى إدارةٍ أخرى</div>
            <form method="post" action="<?php echo htmlspecialchars($self_url); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="transfer">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <label class="sr-only" for="emsf_522_086d6">الإدارة المستلِمة</label>
                <select name="to_role_id" required id="emsf_522_086d6">
                    <?php foreach (tkt_owner_role_ids() as $rid): ?>
                        <option value="<?php echo $rid; ?>"<?php echo intval($ticket['owner_role_id']) === $rid ? ' disabled' : ''; ?>><?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="reason" required placeholder="سبب التحويل (إلزامي — يُقيَّد في السجل)" aria-label="سبب التحويل (إلزامي — يُقيَّد في السجل)">
                <button type="submit" class="btn-primary"><i class="fas fa-right-left" aria-hidden="true"></i> تحويل</button>
            </form>
        </div>
        <?php endif; ?>

        <?php
        // إتاحة زر إصدار أمر الصيانة لفريق البلاغات أو مستخدم الصيانة
        $__is_mnt_user = false;
        if (file_exists(__DIR__ . '/../Maintenance/mnt_helpers.php')) {
            require_once __DIR__ . '/../Maintenance/mnt_helpers.php';
            $__is_mnt_user = function_exists('mnt_user_is_maintenance') ? mnt_user_is_maintenance($conn) : false;
        }
        $__linked = (!empty($ticket['linked_ref_id']) && $ticket['linked_ref_table'] === 'mnt_order');
        ?>
        <?php if (($can_manage || $__is_mnt_user) && !$is_final): ?>
        <div class="tkt-op">
            <div class="tkt-op__k"><i class="fa fa-wrench" aria-hidden="true"></i> التنفيذ في الصيانة</div>
            <?php if ($__linked): ?>
                <div class="tkt-op__linked">
                    <i class="fas fa-link" aria-hidden="true"></i>
                    <span>مرتبطٌ بأمر صيانة رقم <strong>#<?php echo intval($ticket['linked_ref_id']); ?></strong></span>
                    <a class="action-btn edit" href="../Maintenance/orders.php?id=<?php echo intval($ticket['linked_ref_id']); ?>" title="فتح أمر الصيانة"><i class="fas fa-up-right-from-square" aria-hidden="true"></i></a>
                </div>
            <?php else: ?>
                <form method="post" action="<?php echo htmlspecialchars($self_url); ?>"
                      onsubmit="return confirm('إصدار أمر صيانة من هذا البلاغ وربطه به؟');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="issue_mnt_order">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-wrench" aria-hidden="true"></i> إصدار أمر صيانة</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($can_manage && !$is_final && $ticket['parent_id'] === null): ?>
        <div class="tkt-op">
            <div class="tkt-op__k"><i class="fa fa-code-branch" aria-hidden="true"></i> تفريعُ تذكرةٍ لإدارةٍ أخرى</div>
            <form method="post" action="<?php echo htmlspecialchars($self_url); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="branch">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <label class="sr-only" for="emsf_524_branch">نوع الفرع</label>
                <select name="child_type_id" required id="emsf_524_branch"><?php echo tkt_type_options(); ?></select>
                <input type="text" name="child_complaint" required placeholder="وصف الفرع (ما المطلوب من الإدارة الأخرى؟)" aria-label="وصف الفرع (ما المطلوب من الإدارة الأخرى؟)">
                <button type="submit" class="btn-primary"><i class="fas fa-code-branch" aria-hidden="true"></i> إنشاء فرع</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($can_admin && !$is_final): ?>
        <div class="tkt-op tkt-op--danger">
            <div class="tkt-op__k"><i class="fa fa-ban" aria-hidden="true"></i> إلغاء التذكرة (مكرَّرة أو غير صحيحة) — تبقى في السجلِّ للتدقيق ولا تُحذف</div>
            <form method="post" action="<?php echo htmlspecialchars($self_url); ?>"
                  onsubmit="return confirm('إلغاء التذكرة؟ تبقى في السجل للتدقيق ولا تُحذف.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <label class="sr-only" for="emsf_523_187bf">سبب الإلغاء</label>
                <input type="text" name="reason" required placeholder="سبب الإلغاء (إلزامي)" id="emsf_523_187bf">
                <button type="submit" class="btn-secondary"><i class="fas fa-ban" aria-hidden="true"></i> إلغاء التذكرة</button>
            </form>
        </div>
        <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($children)): ?>
    <!-- الفروع: التذكرة الرئيسية لا تُغلق قبل إغلاقها -->
    <section class="tkt-panel">
      <div class="tkt-panel__head">
        <i class="fa fa-code-branch" aria-hidden="true"></i>
        <h3>الفروع</h3>
        <?php $oc = tkt_open_children_count($ticket_id);
              echo $oc > 0
                ? "<span class='tkt-chip is-warn'><i class='fa fa-lock' aria-hidden='true'></i> $oc مفتوح — يمنع الإغلاق</span>"
                : "<span class='tkt-chip is-ok'><i class='fa fa-circle-check' aria-hidden='true'></i> كلها مغلقة</span>"; ?>
      </div>
      <div class="tkt-panel__body tkt-scroll">
        <table class="alltables no-datatable tkt-frm-subtable">
            <thead><tr><th>فتح</th><th>رقم الفرع</th><th>المرحلة</th><th>الإدارة المالكة</th><th>الوصف</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
            <tbody>
            <?php foreach ($children as $ch): ?>
                <tr>
                    <td><a href="ticket_form.php?id=<?php echo intval($ch['id']); ?>" class="action-btn edit"><i class="fas fa-up-right-from-square"></i></a></td>
                    <td><strong><?php echo htmlspecialchars($ch['ticket_no']); ?></strong></td>
                    <td><?php echo tkt_stage_badge($ch['stage']); ?></td>
                    <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($ch['owner_role_id']))); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr((string)$ch['complaint'], 0, 70)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($transfers)): ?>
    <!-- سجل انتقال الملكية — قيدٌ دائمٌ لا يُمحى -->
    <section class="tkt-panel">
      <div class="tkt-panel__head">
        <i class="fa fa-right-left" aria-hidden="true"></i>
        <h3>سجل انتقال الملكية</h3>
        <span class="tkt-chip"><i class="fa fa-shield-halved" aria-hidden="true"></i> قيدٌ دائم</span>
      </div>
      <div class="tkt-panel__body tkt-scroll">
        <table class="alltables no-datatable tkt-frm-subtable">
            <thead><tr><th>من</th><th>إلى</th><th>الوقت</th><th>السبب</th></tr></thead>
            <tbody>
            <?php foreach ($transfers as $tf): ?>
                <tr>
                    <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($tf['from_role_id']))); ?></td>
                    <td><strong><?php echo htmlspecialchars(tkt_label($roles_map, intval($tf['to_role_id']))); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$tf['transfer_datetime']); ?></td>
                    <td><?php echo htmlspecialchars((string)$tf['reason']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- ④ التتبع والتدقيق: خط الزمن + التعليق -->
    <section class="tkt-panel">
      <div class="tkt-panel__head">
        <i class="fa fa-timeline" aria-hidden="true"></i>
        <h3>ماذا جرى — سجلُّ الأحداثِ والتواصل</h3>
        <span class="tkt-chip"><i class="fa fa-shield-halved" aria-hidden="true"></i> لا يُحرَّر ولا يُحذَف</span>
      </div>
      <div class="tkt-panel__body">
        <div class="tkt-tl">
            <?php if (empty($events)): ?>
                <div class="tkt-empty"><i class="fa fa-inbox" aria-hidden="true"></i> لا أحداثَ بعد — أوّلُ تعليقٍ يبدأ السجل.</div>
            <?php endif; ?>
            <?php foreach ($events as $ev):
                /* رمزُ العقدةِ يتبع نوعَ الحدث: انتقالٌ في المسار · كلامٌ بين الأطراف
                   · ما عداه أثرٌ نظامي. تصنيفٌ بصريٌّ لا معنًى جديد. */
                $__t = (string) $ev['event_type'];
                $__isMove = ($__t === 'status_change' || $__t === 'transfer' || $__t === 'reclassified');
                $__isTalk = ($__t === 'comment' || $__t === 'attachment');
                $__cls = $__isMove ? 'is-stage' : ($__isTalk ? 'is-talk' : '');
                $__ico = $__isMove ? 'fa-arrow-right-arrow-left' : ($__isTalk ? 'fa-comment-dots' : 'fa-gear');
            ?>
                <div class="tkt-tl__item">
                    <span class="tkt-tl__dot <?php echo $__cls; ?>" aria-hidden="true"><i class="fas <?php echo $__ico; ?>"></i></span>
                    <div class="tkt-tl__meta">
                        <span class="tkt-tl__what"><?php echo htmlspecialchars(tkt_label($ev_labels, $ev['event_type'])); ?></span>
                        <span><?php echo htmlspecialchars((string)$ev['created_at']); ?></span>
                        <?php if ($ev['actor_role_id'] !== null): ?>
                            <span>· <?php echo htmlspecialchars(tkt_label($roles_map, intval($ev['actor_role_id']))); ?></span>
                        <?php endif; ?>
                        <?php if ($ev['old_value'] !== null || $ev['new_value'] !== null): ?>
                            <span class="tkt-tl__move"><?php echo htmlspecialchars(tkt_label($stages_map, (string)$ev['old_value']) ?: (string)$ev['old_value']); ?> ← <?php echo htmlspecialchars(tkt_label($stages_map, (string)$ev['new_value']) ?: (string)$ev['new_value']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($ev['body'] !== null && $ev['body'] !== ''): ?>
                        <div class="tkt-tl__body"><?php echo htmlspecialchars($ev['body']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?php echo htmlspecialchars($self_url); ?>" enctype="multipart/form-data" class="tkt-composer">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
            <label class="sr-only" for="emsf_525_comment">تعليقٌ يظهر لكل الأطراف</label>
            <textarea name="body" id="emsf_525_comment" rows="3" placeholder="أضف تعليقًا أو تواصلًا يظهر لكل الأطراف…"></textarea>
            <div class="tkt-composer__bar">
                <label class="sr-only" for="emsf_526_file">مرفق</label>
                <input class="tkt-file" type="file" name="attachment" id="emsf_526_file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                <span class="tkt-composer__hint">صورةٌ أو PDF — حتى 8 م.ب</span>
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> إضافة</button>
            </div>
        </form>
      </div>
    </section>

    </div><!-- /tkt-col--main -->

    <aside class="tkt-col tkt-col--side">
        <!-- خصائصُ التذكرة — تُقرأ ولا تُملأ -->
        <section class="tkt-panel">
          <div class="tkt-panel__head">
            <i class="fa fa-circle-info" aria-hidden="true"></i>
            <h3>خصائص التذكرة</h3>
          </div>
          <div class="tkt-panel__body">
            <div class="tkt-meta">
                <div class="tkt-meta__row"><span class="tkt-meta__k">النوع</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars($type_row ? $type_row['name'] : '—'); ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">الطبيعة</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars(tkt_label($natures, $ticket['ticket_nature'])); ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">التصنيف الفني</span>
                    <span class="tkt-meta__v"><?php echo $cat_row ? htmlspecialchars($cat_row['name']) : '<span class="muted">بلا تصنيف</span>'; ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">الإدارة المالكة</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars(tkt_label($roles_map, intval($ticket['owner_role_id']))); ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">فريق المعالجة</span>
                    <span class="tkt-meta__v"><?php echo $ticket['service_team'] === 'internal' ? 'داخلي' : ($ticket['service_team'] === 'external_workshop' ? 'ورشة خارجية' : '<span class="muted">غير محدد</span>'); ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">المُبلِّغ</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars($ticket['reporting_person']);
                        echo $ticket['reporter_contact'] ? ' <span class="muted">· ' . htmlspecialchars($ticket['reporter_contact']) . '</span>' : ''; ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">تاريخ البلاغ</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars($ticket['call_date'] . ' ' . (string)$ticket['call_time']); ?></span></div>
                <div class="tkt-meta__row"><span class="tkt-meta__k">موعد الإنجاز</span>
                    <span class="tkt-meta__v"><?php
                        echo $ticket['resolution_due_at']
                            ? htmlspecialchars($ticket['resolution_due_at'])
                            : '<span class="muted">بلا سياسة استحقاق</span>';
                        echo $__overdue ? ' ' . tkt_overdue_badge($ticket) : ''; ?></span></div>
                <?php if ($ticket['close_date']): ?>
                <div class="tkt-meta__row"><span class="tkt-meta__k">الإغلاق</span>
                    <span class="tkt-meta__v"><?php echo htmlspecialchars($ticket['close_date'] . ' ' . (string)$ticket['close_time']); ?></span></div>
                <?php endif; ?>
            </div>
          </div>
        </section>

        <?php if (!empty($attachments)): ?>
        <section class="tkt-panel">
          <div class="tkt-panel__head">
            <i class="fa fa-paperclip" aria-hidden="true"></i>
            <h3>المرفقات</h3>
            <span class="tkt-chip"><?php echo count($attachments); ?></span>
          </div>
          <div class="tkt-panel__body">
            <div class="tkt-files">
                <?php foreach ($attachments as $a): ?>
                    <a href="<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" rel="noopener" class="tkt-file-pill">
                        <i class="fas <?php echo $a['file_type'] === 'document' ? 'fa-file-pdf' : 'fa-image'; ?>" aria-hidden="true"></i>
                        مرفق #<?php echo intval($a['id']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>
    </aside>
    </div><!-- /tkt-layout -->
<?php endif; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
</body>
</html>
