<?php
/**
 * Portal/project_charter.php — قرار فتح مشروع جديد (M-00 §8-2 — على جدولها الأصلي)
 * ───────────────────────────────────────────────────────────────────────────
 * الورقة المالكة: 00 · الإدارة التنفيذية · الأعمدة 27 بترتيب المستند وطبقة
 * الحوكمة بشرائحها. القرارات في الجدول الأصلي `exec_project_charters` (هجرة
 * 2026_11_14 — أُنجز لحاق CMP03_FOLLOWUP) معزولةً بالكيان، وقرارُ الفتح يقع
 * بفعل «اعتماد الفتح» فيولّد أثرَه الخماسي ④-٤ آليًّا، والمعتمَدُ محصَّنٌ
 * بقادح BR-CEO-08 في القاعدة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/gov_columns.php';
require_once '../includes/m00_exec_helpers.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-INFO-200', '');
    exit();
}

// حارس الشاشة (M-14 BR-GOV-01): can_view من modules — والسوبر يمر
$__pp = check_page_permissions($conn, 'Portal/project_charter.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/project_charter.php');
    header('Location: ../main/dashboard.php?msg=' . urlencode($__why));
    exit();
}
if (!$is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($__pp['can_add']) && empty($__pp['can_edit'])) {
    http_response_code(403);
    exit('غير مصرح بالكتابة في هذه الشاشة');
}
$COLS   = array (
  0 => 'الكيان',
  1 => 'رقم القرار',
  2 => 'اسم المشروع',
  3 => 'العميل',
  4 => 'العقد',
  5 => 'الموقع أو المواقع',
  6 => 'نموذج العمل',
  7 => 'وحدة العمل',
  8 => 'الكمية المتعاقدة',
  9 => 'تاريخ البدء المخطط',
  10 => 'المدة',
  11 => 'المعدات المطلوبة',
  12 => 'المشغّلون المطلوبون',
  13 => 'مصدر المعدات',
  14 => 'احتياج التمويل',
  15 => 'مركز التكلفة',
  16 => 'مدير الموقع المعيَّن',
  17 => 'صلاحياته',
  18 => 'إفادة التشغيل',
  19 => 'إفادة المبيعات',
  20 => 'إفادة القوى',
  21 => 'إفادة المالية',
  22 => 'إفادة الأسطول',
  23 => 'إفادة التمويل',
  24 => 'المعتمِد — الاسم والصفة',
  25 => 'تاريخ الاعتماد',
  26 => 'الحالة',
);
/* أعمدة الجدول الأصلي بترتيب حقول الفورم f0..f25 (الأخير الحالة) */
$DB_FIELDS = array(
    'decision_no', 'project_name', 'client', 'contract_ref', 'sites_text',
    'work_model', 'work_unit', 'contracted_qty', 'planned_start', 'duration',
    'equipment_needed', 'operators_needed', 'equipment_source', 'financing_need',
    'cost_center', 'site_manager', 'manager_powers', 'cert_operations',
    'cert_sales', 'cert_workforce', 'cert_finance', 'cert_fleet', 'cert_financing',
    'approver_name', 'approval_date',
);
/* خريطة عرض: فهرس عمود المستند → عمود القاعدة (null = الكيان) */
$COLDB = array_merge(array(null), $DB_FIELDS, array('__status'));

/* الإفادات الخمس اللازمة للعرض (BR-CEO-03) — والتمويل سادسةٌ إن لزم */
$FIVE_CERTS = array(
    'cert_operations' => 'إفادة التشغيل',
    'cert_sales'      => 'إفادة المبيعات',
    'cert_workforce'  => 'إفادة القوى',
    'cert_finance'    => 'إفادة المالية',
    'cert_fleet'      => 'إفادة الأسطول',
);

/* ── الحفظ: فورم الإضافة الموحد → الجدول الأصلي ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'add') {
    $in = array();
    foreach ($DB_FIELDS as $i => $col) { $in[$col] = trim((string) ($_POST['f' . $i] ?? '')); }
    $status = trim((string) ($_POST['f25'] ?? '')) ?: 'مسودة';

    // ═══ BR-CEO-03: لا فتحَ مشروعٍ بقرارٍ فردي — الإفاداتُ الخمسُ لازمةٌ للعرض ═══
    // صفُّ قرارِ فتحٍ (حالة مفتوح/معتمد أو قرارٌ مؤرَّخ) بلا الإفادات الخمس
    // كاملةً يُرفض ويُسمّى الناقص — والإفادةُ رأيٌ ملزمٌ للعرض والقرارُ للتنفيذي.
    $isOpening = in_array($status, array('مفتوح', 'معتمد', 'قرار فتح'), true)
              || ($in['approval_date'] !== '');
    if ($isOpening) {
        $missing = array();
        foreach ($FIVE_CERTS as $col => $lbl) {
            if ($in[$col] === '') { $missing[] = $lbl; }
        }
        if ($missing) {
            header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode(
                'BR-CEO-03: لا يُعرض قرارُ الفتح — الإفادات الناقصة: ' . implode(' · ', $missing) . ' ❌'));
            exit();
        }
    }
    $creator = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);
    // الفارغ NULL من هنا لا NULLIF في SQL — خلطُ الترتيبات على اتصال الويب يرفضها
    foreach ($in as $k => $v) { if ($v === '') { $in[$k] = null; } }
    $st = $conn->prepare("INSERT INTO exec_project_charters
        (company_id, decision_no, project_name, client, contract_ref, sites_text,
         work_model, work_unit, contracted_qty, planned_start, duration,
         equipment_needed, operators_needed, equipment_source, financing_need,
         cost_center, site_manager, manager_powers, cert_operations, cert_sales,
         cert_workforce, cert_finance, cert_fleet, cert_financing, approver_name,
         approval_date, status, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
    $st->bind_param('issssssssssssssssssssssssssis',
        $company_id, $in['decision_no'], $in['project_name'], $in['client'],
        $in['contract_ref'], $in['sites_text'], $in['work_model'], $in['work_unit'],
        $in['contracted_qty'], $in['planned_start'], $in['duration'],
        $in['equipment_needed'], $in['operators_needed'], $in['equipment_source'],
        $in['financing_need'], $in['cost_center'], $in['site_manager'],
        $in['manager_powers'], $in['cert_operations'], $in['cert_sales'],
        $in['cert_workforce'], $in['cert_finance'], $in['cert_fleet'],
        $in['cert_financing'], $in['approver_name'], $in['approval_date'],
        $status, $uid, $creator);
    $ok = $st->execute();
    $st->close();
    header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($ok ? 'حُفظ الصف ✅' : 'تعذر الحفظ ❌'));
    exit();
}

/* ── اعتماد الفتح: فعل M-00 ④-٤ — الأثر الخماسي بقرارٍ واحد ─────────────
 * «المشروعُ يُفتح بمركز تكلفته · ومديرُ الموقع يُعيَّن بصلاحياته · والمواقعُ
 * تُنشأ · والموارد تُحجز — ولا يقع شيءٌ من هذا بقرارٍ فردي» (SCN-967-ج·د):
 * BR-CEO-03 يُعاد فحصُه هنا، والقرارُ للإدارة التنفيذية وحدها، والتوليدُ
 * آليٌّ: project + fin_cost_centers + sites + مهمةُ حجز الموارد، ويُنشر
 * project.chartered بعطالة القرار. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['cmp03_action'] ?? '') === 'charter') {
    $goBack = function ($m) { header('Location: ' . basename(__FILE__) . '?msg=' . rawurlencode($m)); exit(); };
    $actorRole = strval($_SESSION['user']['role'] ?? '');
    if (!$is_super_admin && $actorRole !== '9') {
        http_response_code(403);
        exit('اعتمادُ فتح المشروع قرارُ الإدارة التنفيذية وحدها');
    }
    $rowId = intval($_POST['row'] ?? 0);
    if ($rowId <= 0) { $goBack('اختر قرارًا للاعتماد ❌'); }

    $st = $conn->prepare("SELECT * FROM exec_project_charters WHERE id = ?"
        . ($is_super_admin && $company_id <= 0 ? '' : ' AND company_id = ?'));
    if ($is_super_admin && $company_id <= 0) { $st->bind_param('i', $rowId); }
    else { $st->bind_param('ii', $rowId, $company_id); }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $goBack('القرارُ غير موجودٍ في نطاقك ❌'); }
    if (in_array((string) $row['status'], array('مفتوح', 'مغلق'), true)) {
        $goBack('القرارُ ' . $row['status'] . ' سلفًا — لا قرارَ على قرار (BR-CEO-08) ❌');
    }

    // BR-CEO-03: الإفادات الخمس كاملةً قبل القرار — والناقص يُسمّى
    $missing = array();
    foreach ($FIVE_CERTS as $col => $lbl) {
        if (trim((string) ($row[$col] ?? '')) === '') { $missing[] = $lbl; }
    }
    if ($missing) {
        $goBack('BR-CEO-03: لا يُعتمد الفتح — الإفادات الناقصة: ' . implode(' · ', $missing) . ' ❌');
    }

    $rowCo = (int) $row['company_id'];
    $pname = trim((string) ($row['project_name'] ?? '')) ?: ('مشروع ' . $row['decision_no']);
    $client = trim((string) ($row['client'] ?? '')) ?: '—';
    $sitesText = trim((string) ($row['sites_text'] ?? ''));
    $actorName = trim((string) ($_SESSION['user']['name'] ?? '')) ?: ('مستخدم #' . $uid);

    $conn->begin_transaction();
    try {
        // ① المشروع
        $st = $conn->prepare("INSERT INTO project
            (company_id, name, client, location, project_code, total, status, created_by)
            VALUES (?, ?, ?, ?, ?, '0', 1, ?)");
        $st->bind_param('issssi', $rowCo, $pname, $client, $sitesText, $row['decision_no'], $uid);
        if (!$st->execute()) { throw new \RuntimeException('project: ' . $st->error); }
        $projectId = (int) $conn->insert_id;
        $st->close();

        // ② مركز التكلفة — رمزُ القرار إن لم يُحدَّد رمزٌ سلفًا
        $ccCode = trim((string) ($row['cost_center'] ?? '')) ?: ('CC-PRJ-' . $rowId);
        $ccName = 'مركز تكلفة ' . $pname;
        $st = $conn->prepare("INSERT INTO fin_cost_centers
            (company_id, code, name, center_type, owner_module, level, active, created_by)
            VALUES (?, ?, ?, 'cost', 'projects', 0, 1, ?)");
        $st->bind_param('issi', $rowCo, $ccCode, $ccName, $uid);
        if (!$st->execute()) { throw new \RuntimeException('cost_center: ' . $st->error); }
        $ccId = (int) $conn->insert_id;
        $st->close();

        // ③ المواقع — من نص «الموقع أو المواقع» مفصولًا بالنقطة الوسيطة
        $siteNames = array_values(array_filter(array_map('trim', preg_split('/[·,]+/u', $sitesText))));
        if (!$siteNames) { $siteNames = array($pname); }
        $st = $conn->prepare("INSERT INTO sites
            (company_id, project_id, name, site_kind, status, is_default)
            VALUES (?, ?, ?, 'site', 1, ?)");
        $siteIds = array();
        foreach ($siteNames as $k => $sn) {
            $isDef = $k === 0 ? 1 : 0;
            $st->bind_param('iisi', $rowCo, $projectId, $sn, $isDef);
            if (!$st->execute()) { throw new \RuntimeException('site: ' . $st->error); }
            $siteIds[] = (int) $conn->insert_id;
        }
        $st->close();

        // ⑤ القرار نفسه: مفتوحٌ بمرجعَي التوليد — ④ التعيينُ في عمودَي المدير
        // وصلاحياتِه (قائمان في الصف) والقادحُ يصونهما بعد الفتح
        $apprName = trim((string) ($row['approver_name'] ?? '')) ?: ($actorName . ' (الإدارة التنفيذية)');
        $apprDate = date('Y-m-d');
        $st = $conn->prepare("UPDATE exec_project_charters
            SET status = 'مفتوح', project_id = ?, cost_center_id = ?, cost_center = ?,
                approver_name = ?, approval_date = ?
            WHERE id = ?");
        $st->bind_param('iisssi', $projectId, $ccId, $ccCode, $apprName, $apprDate, $rowId);
        if (!$st->execute()) { throw new \RuntimeException('charter: ' . $st->error); }
        $st->close();

        $conn->commit();
    } catch (\Throwable $t) {
        $conn->rollback();
        $goBack('تعذر الأثرُ الخماسي — أُلغي القرارُ كاملًا: ' . $t->getMessage() . ' ❌');
    }

    // ⑤ حجزُ الموارد: مهمةُ متابعةٍ ملزمةٌ بمهلةٍ على المعدات والمشغّلين المطلوبين
    try {
        require_once dirname(__DIR__) . '/app/Services/Work/WorkItemService.php';
        \App\Services\Work\WorkItemService::create($conn, array(
            'company_id' => $rowCo, 'source_type' => 'SRC-10', 'source_ref' => 'EXPC-' . $rowId,
            'source_screen' => 'Portal/project_charter.php',
            'owner_user_id' => $uid, 'assigned_user_id' => $uid,
            'org_unit_id' => 1, 'project_id' => $projectId, 'site_id' => $siteIds[0] ?? 0,
            'title' => 'حجز موارد المشروع — ' . (string) $row['decision_no'],
            'deliverable' => 'حجز: ' . (string) ($row['equipment_needed'] ?? '—')
                           . ' · ' . (string) ($row['operators_needed'] ?? '—')
                           . ' (المصدر: ' . (string) ($row['equipment_source'] ?? '—') . ')',
            'evidence_required' => 'تخصيصات المعدات والمشغّلين على مواقع المشروع #' . $projectId,
            'due_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'priority' => 'P1', 'created_by' => $uid, 'parent_ref' => 'EXPC-' . $rowId,
        ));
    } catch (\Throwable $t) { error_log('charter reserve #' . $rowId . ': ' . $t->getMessage()); }

    // §11 ProjectChartered: القرارُ الحقيقي حقيقةٌ تُنشر بعطالته
    if ((int) $row['is_seed'] === 0) {
        try {
            require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key'       => 'project.chartered',
                'category'        => 'operational',
                'source_module'   => 'projects',
                'company_id'      => $rowCo,
                'entity_type'     => 'project',
                'entity_id'       => $projectId,
                'project_id'      => $projectId,
                'occurred_at'     => gmdate('Y-m-d H:i:s'),
                'created_by'      => $uid ?: 1,
                'idempotency_key' => 'project_charter:EXPC-' . $rowId,
                'notes'           => 'فتحُ مشروع: ' . $pname . ' (' . (string) $row['decision_no'] . ')',
                'payload'         => array(
                    'charter_ref'  => 'EXPC-' . $rowId,
                    'decision_no'  => (string) $row['decision_no'],
                    'project_id'   => $projectId,
                    'cost_center'  => $ccCode,
                    'sites'        => $siteNames,
                    'site_manager' => (string) ($row['site_manager'] ?? ''),
                    'powers'       => (string) ($row['manager_powers'] ?? ''),
                ),
            ));
        } catch (\Throwable $t) { error_log('charter fact #' . $rowId . ': ' . $t->getMessage()); }
    }
    $goBack('فُتح المشروع #' . $projectId . ' بمركز تكلفته ' . $ccCode . ' و' . count($siteNames) . ' موقعًا ✅');
}

/* ── القراءة: صفوف الكيان من الجدول الأصلي ──────────────────────────────── */
$rows = array();
$sql = "SELECT * FROM exec_project_charters"
     . ($is_super_admin && $company_id <= 0 ? '' : ' WHERE company_id = ?')
     . ' ORDER BY id DESC LIMIT 500';
$st = $conn->prepare($sql);
if (!($is_super_admin && $company_id <= 0)) { $st->bind_param('i', $company_id); }
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$govCtx = ems_gov_ctx();
$entityName = $govCtx['values']['entity'] ?? '—';

/** قيمة خلية بفهرس عمود المستند — الحوكمة الآلية حية وسائرها من الجدول أو «—» */
function m00_cell_at($idx, $row, $entityName, $COLDB) {
    $col = $COLDB[$idx] ?? null;
    if ($col === null) { return $entityName; }
    if ($col === '__status') { return (string) $row['status']; }
    $v = isset($row[$col]) ? trim((string) $row[$col]) : '';
    return $v !== '' ? $v : '—';
}

$page_title = 'إيكوبيشن | قرار فتح مشروع جديد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'قرار فتح مشروع جديد';
    $header_icon = 'fa fa-folder-plus';
    $header_actions = array(
        array('tag' => 'button', 'id' => 'cmp03AddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة', 'title' => 'إضافة صف جديد', 'attrs' => 'type="button"'),
    );
    $header_back = false;
    include '../includes/page_header.php';
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    ?>

    <!-- فورم الإضافة الموحد (ems-forms) — مطويٌّ حتى زرِّ الرأس -->
    <form method="post" action="" class="allforms" id="cmp03AddForm">
        <input type="hidden" name="cmp03_action" value="add">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-plus"></i> إضافة — قرار فتح مشروع جديد</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>رقم القرار</label>
                    <input type="text" name="f0" required maxlength="190"></div>
                <div class="form-group"><label>اسم المشروع</label>
                    <input type="text" name="f1" maxlength="190"></div>
                <div class="form-group"><label>العميل</label>
                    <input type="text" name="f2" maxlength="190"></div>
                <div class="form-group"><label>العقد</label>
                    <input type="text" name="f3" maxlength="190"></div>
                <div class="form-group"><label>الموقع أو المواقع</label>
                    <input type="text" name="f4" maxlength="190"></div>
                <div class="form-group"><label>نموذج العمل</label>
                    <input type="text" name="f5" maxlength="190"></div>
                <div class="form-group"><label>وحدة العمل</label>
                    <input type="text" name="f6" maxlength="190"></div>
                <div class="form-group"><label>الكمية المتعاقدة</label>
                    <input type="text" inputmode="decimal" name="f7" placeholder="0"></div>
                <div class="form-group"><label>تاريخ البدء المخطط</label>
                    <input type="date" name="f8"></div>
                <div class="form-group"><label>المدة</label>
                    <input type="text" name="f9" maxlength="190"></div>
                <div class="form-group"><label>المعدات المطلوبة</label>
                    <input type="text" name="f10" maxlength="190"></div>
                <div class="form-group"><label>المشغّلون المطلوبون</label>
                    <input type="text" name="f11" maxlength="190"></div>
                <div class="form-group"><label>مصدر المعدات</label>
                    <input type="text" name="f12" maxlength="190"></div>
                <div class="form-group"><label>احتياج التمويل</label>
                    <input type="text" name="f13" maxlength="190"></div>
                <div class="form-group"><label>مركز التكلفة</label>
                    <input type="text" name="f14" maxlength="190"></div>
                <div class="form-group"><label>مدير الموقع المعيَّن</label>
                    <input type="text" name="f15" maxlength="190"></div>
                <div class="form-group"><label>صلاحياته</label>
                    <input type="text" name="f16" maxlength="190"></div>
                <div class="form-group"><label>إفادة التشغيل</label>
                    <input type="text" name="f17" maxlength="190"></div>
                <div class="form-group"><label>إفادة المبيعات</label>
                    <input type="text" name="f18" maxlength="190"></div>
                <div class="form-group"><label>إفادة القوى</label>
                    <input type="text" name="f19" maxlength="190"></div>
                <div class="form-group"><label>إفادة المالية</label>
                    <input type="text" name="f20" maxlength="190"></div>
                <div class="form-group"><label>إفادة الأسطول</label>
                    <input type="text" name="f21" maxlength="190"></div>
                <div class="form-group"><label>إفادة التمويل</label>
                    <input type="text" name="f22" maxlength="190"></div>
                <div class="form-group"><label>المعتمِد — الاسم والصفة</label>
                    <input type="text" name="f23" maxlength="190"></div>
                <div class="form-group"><label>تاريخ الاعتماد</label>
                    <input type="date" name="f24"></div>
                <div class="form-group"><label>الحالة</label>
                    <select name="f25"><option value="مسودة">مسودة</option><option value="قيد الإفادات">قيد الإفادات</option><option value="جاهز للقرار">جاهز للقرار</option><option value="مؤجل">مؤجل</option></select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" id="cmp03CancelBtn"><i class="fa fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <?php
    // لوحة اعتماد الفتح (M-00 ④-٤) — للإدارة التنفيذية وحدها وعلى المكتمل إفاداتُه
    $charterable = array();
    foreach ($rows as $r) {
        if (in_array((string) $r['status'], array('مفتوح', 'مغلق', 'ملغي'), true)) { continue; }
        $complete = true;
        foreach ($FIVE_CERTS as $col => $lbl) {
            if (trim((string) ($r[$col] ?? '')) === '') { $complete = false; break; }
        }
        if ($complete) { $charterable[] = $r; }
    }
    $canCharter = $is_super_admin || strval($_SESSION['user']['role'] ?? '') === '9';
    if ($canCharter && $charterable): ?>
    <form method="post" action="" class="allforms allforms-visible" id="cmp03CharterForm">
        <input type="hidden" name="cmp03_action" value="charter">
        <div class="card"><div class="card-header">
            <h5><i class="fa fa-folder-open"></i> اعتماد الفتح — الأثر الخماسي: مشروع · مركز تكلفة · مواقع · مدير · حجز</h5>
        </div><div class="card-body">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>القرار المكتمل إفاداتُه الخمس</label>
                    <select name="row" required>
                        <?php foreach ($charterable as $d):
                            $lbl = 'EXPC-' . intval($d['id'])
                                 . ' — ' . (string) $d['decision_no']
                                 . ' · ' . (string) ($d['project_name'] ?? '—')
                                 . ' (' . (string) $d['status'] . ')'; ?>
                        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div></div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="btn-save"><i class="fa fa-stamp"></i> اعتماد الفتح</button>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
        <table class="alltables display" id="project_charterTable">
            <thead><tr>
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th>رقم القرار</th>
            <th>اسم المشروع</th>
            <th>العميل</th>
            <th>العقد</th>
            <th>الموقع أو المواقع</th>
            <th>نموذج العمل</th>
            <th>وحدة العمل</th>
            <th>الكمية المتعاقدة</th>
            <th>تاريخ البدء المخطط</th>
            <th>المدة</th>
            <th>المعدات المطلوبة</th>
            <th>المشغّلون المطلوبون</th>
            <th>مصدر المعدات</th>
            <th>احتياج التمويل</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th>مدير الموقع المعيَّن</th>
            <th>صلاحياته</th>
            <th>إفادة التشغيل</th>
            <th>إفادة المبيعات</th>
            <th>إفادة القوى</th>
            <th>إفادة المالية</th>
            <th class="ems-fn-th none" data-fn="1">إفادة الأسطول</th>
            <th class="ems-fn-th none" data-fn="1">إفادة التمويل</th>
            <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="27" class="text-center text-muted">لا بياناتَ بعدُ — أضف أول صفٍّ بزر «إضافة»</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr<?php echo $r['is_seed'] ? ' data-seed="1"' : ''; ?>>
                    <?php foreach (array_keys($COLS) as $i): $v = m00_cell_at($i, $r, $entityName, $COLDB); ?>
                    <td<?php echo $v === '—' ? ' class="ems-gov-empty"' : ''; ?>><?php echo htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>

<script>
(function () {
    var btn = document.getElementById('cmp03AddBtn');
    var form = document.getElementById('cmp03AddForm');
    var cancel = document.getElementById('cmp03CancelBtn');
    if (btn && form) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            form.classList.toggle('allforms-visible');
            if (form.classList.contains('allforms-visible')) {
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (cancel && form) {
        cancel.addEventListener('click', function () { form.classList.remove('allforms-visible'); });
    }
})();
</script>
