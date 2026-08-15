<?php
/**
 * Portal/ceo_reports.php — تقارير الإدارة التنفيذية الثمانية (M-00 §12)
 * ───────────────────────────────────────────────────────────────────────────
 * كلُّ تقريرٍ مشتقٌّ حيًّا من مصدره الحقيقي — لا مخزنَ وسيطًا ولا تلفيق:
 * ① اللوحة الجامعة (عقود·مشاريع·حقائق §11·متابعات) ② معلَّقات الاعتماد الأعلى
 * (الشاشة البينية + الطلبات بيد التنفيذيين + خطوات السلسلة) ③ العقود بحالاتها
 * الاثنتي عشرة وآخر التوقيعات من الجذر ④ المشاريع وآخر المفتوح ⑤ القرارات
 * ومتابعاتها SRC-10 ⑥ نبض المال (حقائق 30 يومًا) ⑦ المخاطر المفتوحة
 * ⑧ خريطة السقوف والموازنات (ملكية التوجيه + عناوين الموازنة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/screen_contract.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'غير مصرح', 'GOV-PERM-403', '');
    exit();
}

$__pp = check_page_permissions($conn, 'Portal/ceo_reports.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/ceo_reports.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

$coW = $is_super_admin && $company_id <= 0 ? '' : ' AND company_id = ' . $company_id;
$coW2 = $is_super_admin && $company_id <= 0 ? '' : ' WHERE company_id = ' . $company_id;
$coWc = $is_super_admin && $company_id <= 0 ? '' : ' AND c.company_id = ' . $company_id;

/** استعلام قائمة آمن القراءة — يعيد مصفوفة صفوف (فارغة عند أي عطل) */
function cr_rows(mysqli $conn, $sql) {
    try {
        $r = $conn->query($sql);
        if (!$r) { error_log('ceo_reports: ' . $conn->error . ' — ' . mb_substr($sql, 0, 120)); return array(); }
        $out = array();
        while ($w = $r->fetch_assoc()) { $out[] = $w; }
        return $out;
    } catch (\Throwable $t) { error_log('ceo_reports: ' . $t->getMessage()); return array(); }
}

/* ① اللوحة الجامعة */
$kContracts = cr_rows($conn, "SELECT contract_status st, COUNT(*) c FROM contracts
                               WHERE COALESCE(is_deleted,0)=0 {$coW} GROUP BY contract_status ORDER BY c DESC");
$kProjects  = cr_rows($conn, "SELECT COALESCE(NULLIF(status,''),'بلا حالة') st, COUNT(*) c FROM project
                               WHERE COALESCE(is_deleted,0)=0 {$coW} GROUP BY st ORDER BY c DESC");
$facts = array();
foreach (cr_rows($conn, "SELECT event_key k, COUNT(*) c FROM ems_business_events
                          WHERE event_key IN ('contract.signed','project.chartered','exec.decision.made','exec.approval.granted')
                            {$coW} GROUP BY event_key") as $w) { $facts[$w['k']] = (int) $w['c']; }
$src10 = cr_rows($conn, "SELECT status, COUNT(*) c FROM work_items
                          WHERE source_type='SRC-10' {$coW} GROUP BY status");

/* ② معلَّقات الاعتماد الأعلى — المصادر الثلاثة (الجدول الأصلي بعد اللحاق) */
$pInterim = cr_rows($conn, "SELECT id, request_no, document, doc_type, status FROM exec_approvals
                             WHERE status IN ('مسودة','قيد المراجعة','مؤجل')
                               {$coW} ORDER BY id DESC LIMIT 15");
$pReqs = cr_rows($conn, "SELECT r.id, r.request_no, r.title, r.status, r.created_at
                          FROM requests r JOIN users u ON u.id = r.current_holder_user_id AND u.role = '9'
                          WHERE r.status IN ('submitted','routed','in_approval') " . str_replace('company_id', 'r.company_id', $coW) . "
                          ORDER BY r.id DESC LIMIT 15");
$pLinks = cr_rows($conn, "SELECT al.source_ref, al.step_no, al.sla_due_at
                           FROM approval_links al JOIN users u ON u.id = al.approver_user_id AND u.role = '9'
                           WHERE al.status = 'pending' " . str_replace('company_id', 'al.company_id', $coW) . "
                           ORDER BY al.sla_due_at ASC LIMIT 15");

/* ③ العقود: آخر التوقيعات من الجذر (الحقيقة أصلًا) */
$signedLast = cr_rows($conn, "SELECT e.entity_id cid, e.occurred_at, e.payload
                               FROM ems_business_events e
                               WHERE e.event_key = 'contract.signed' " . str_replace('company_id', 'e.company_id', $coW) . "
                               ORDER BY e.id DESC LIMIT 10");

/* ④ المشاريع: آخر المفتوح من الجذر */
$charteredLast = cr_rows($conn, "SELECT e.entity_id pid, e.occurred_at, e.payload
                                  FROM ems_business_events e
                                  WHERE e.event_key = 'project.chartered' " . str_replace('company_id', 'e.company_id', $coW) . "
                                  ORDER BY e.id DESC LIMIT 10");

/* ⑤ القرارات ومتابعاتها (الجدول الأصلي بعد اللحاق) */
$decRows = cr_rows($conn, "SELECT id, decision_no, issue_type, issue_desc, est_impact, currency,
                                   assigned_dept, exec_deadline, status
                              FROM exec_decisions {$coW2} ORDER BY id DESC LIMIT 15");
$decFollow = cr_rows($conn, "SELECT source_ref, status, due_at FROM work_items
                              WHERE source_type='SRC-10' {$coW} ORDER BY id DESC LIMIT 15");

/* ⑥ نبض المال: حقائق الجذر المالية آخر 30 يومًا */
$finPulse = cr_rows($conn, "SELECT event_key k, COUNT(*) c FROM ems_business_events
                             WHERE category = 'financial' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               {$coW} GROUP BY event_key ORDER BY c DESC LIMIT 12");

/* ══ ⑦ المخاطر المفتوحة — ⇐ INJ-0411 ═══════════════════════════════════════
     نصُّ القبول: «بعد إغلاقِ خطرٍ في `Risk/risk_card.php` يجب أن **ينقص مؤشرُ
     المخاطر المفتوحة** في `Portal/ceo_reports.php` بواحد».
   ── ما كان: يُشتقُّ المؤشرُ من `exec_decisions` — **سجلِّ قراراتِ الرئيس** —
     بمطابقةِ نصِّ حالةٍ («مسودة» · «قيد الدراسة»). فإغلاقُ خطرٍ في السجلِّ
     المركزيِّ لا يحرّكه أبدًا، ورقمانِ لمعنًى واحدٍ في شاشتين.
   ◆ **والسجلُّ المركزيُّ الواحدُ للمخاطر** (`risk_register`) هو المصدر — كما
     تُصرّح الوثيقةُ ويقرأ به `Portal/ceo_board.php` سلفًا (INJ-0129).
   ◆ وقراراتُ الرئيسِ تبقى في بابها ⑤ — فهي مستندٌ قائمٌ بذاته لا مخاطر. */
$openRisk = cr_rows($conn,
    "SELECT rr.id, rr.risk_code AS decision_no, rr.title AS issue_desc,
            rr.current_level AS est_impact, rr.state AS status, rr.created_at AS raised_date,
            ou.name_ar AS assigned_dept
       FROM risk_register rr
       LEFT JOIN org_units ou ON ou.unit_id = rr.owner_unit_id AND ou.company_id = rr.company_id
      WHERE rr.state <> 'closed' AND rr.merged_into_id IS NULL
        " . str_replace('company_id', 'rr.company_id', $coW) . "
      ORDER BY FIELD(rr.current_level,'محظور','حرج','مرتفع','متوسط','منخفض'), rr.id DESC
      LIMIT 50");

/* ⑧ خريطة السقوف: ملكية التوجيه المالي + عناوين الموازنات */
$routing = cr_rows($conn, "SELECT request_kind, owner_dept, COUNT(*) c FROM fin_request_routing
                            GROUP BY request_kind, owner_dept ORDER BY request_kind LIMIT 20");
$budgets = cr_rows($conn, "SELECT b.budget_no, b.status, COUNT(l.id) lines_c
                            FROM fin_budgets b LEFT JOIN fin_budget_lines l ON l.budget_id = b.id
                            WHERE 1=1 " . str_replace('company_id', 'b.company_id', $coW) . "
                            GROUP BY b.id ORDER BY b.id DESC LIMIT 10");

$page_title = 'إيكوبيشن | تقارير الإدارة التنفيذية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';

function cr_p($row, $key) {
    $p = is_array($row['payload']) ? $row['payload'] : (json_decode((string) $row['payload'], true) ?: array());
    return isset($p[$key]) && $p[$key] !== '' ? (string) $p[$key] : '—';
}
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'تقارير الإدارة التنفيذية الثمانية';
    $header_icon = 'fa fa-chart-line';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ems_screen_about('ثمانيةُ تقارير M-00 مشتقةٌ حيًّا من مصادرها — العقودُ والمشاريعُ من جداولها، والوقائعُ من الجذر المحايد، والمعلَّقاتُ من مصادرها الثلاثة، والمتابعاتُ من المحرّك.');
    ?>

    <!-- ① اللوحة الجامعة -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-gauge-high"></i> ① اللوحة الجامعة</h5></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
            <?php
            $sumC = 0; foreach ($kContracts as $w) { $sumC += (int) $w['c']; }
            $sumP = 0; foreach ($kProjects as $w) { $sumP += (int) $w['c']; }
            $openF = 0; $doneF = 0;
            foreach ($src10 as $w) {
                if (in_array($w['status'], array('closed_accepted', 'cancelled'), true)) { $doneF += (int) $w['c']; }
                else { $openF += (int) $w['c']; }
            }
            $cards = array(
                array('العقود (كلها)', $sumC, 'fa-file-contract'),
                array('حقائق التوقيع', $facts['contract.signed'] ?? 0, 'fa-signature'),
                array('المشاريع', $sumP, 'fa-diagram-project'),
                array('حقائق فتح المشاريع', $facts['project.chartered'] ?? 0, 'fa-folder-open'),
                array('قرارات محسومة (§11)', $facts['exec.decision.made'] ?? 0, 'fa-gavel'),
                array('اعتمادات عليا (§11)', $facts['exec.approval.granted'] ?? 0, 'fa-stamp'),
                array('متابعات مفتوحة', $openF, 'fa-bell'),
                array('متابعات منجزة', $doneF, 'fa-check-double'),
            );
            foreach ($cards as $c): ?>
            <div style="border:1px solid var(--ems-border,#e5e5e5);border-radius:10px;padding:12px;text-align:center">
                <div style="font-size:22px;font-weight:700"><?php echo (int) $c[1]; ?></div>
                <div class="text-muted" style="font-size:12px"><i class="fa <?php echo $c[2]; ?>"></i> <?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-muted" style="margin-top:8px;font-size:12px">المصدر: contracts · project · الجذر المحايد ems_business_events · محرّك العمل (SRC-10)</div>
    </div></div>

    <!-- ② معلَّقات الاعتماد الأعلى -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-hourglass-half"></i> ② معلَّقات الاعتماد الأعلى (المصادر الثلاثة)</h5></div>
    <div class="card-body">
        <table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>المصدر</th><th>المرجع</th><th>البيان</th><th>الحالة/المهلة</th></tr></thead><tbody>
            <?php if (!$pInterim && !$pReqs && !$pLinks): ?>
                <tr><td colspan="4" class="text-center text-muted">لا معلَّقَ الآن — صفرُ انتظارٍ أمام القمة</td></tr>
            <?php else: ?>
                <?php foreach ($pInterim as $w): ?>
                <tr><td>شاشة الاعتمادات</td><td><?php echo htmlspecialchars((string) ($w['request_no'] ?: ('#' . $w['id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(trim((string) $w['document'] . ' · ' . (string) $w['doc_type'], ' ·'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $w['status'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endforeach; foreach ($pReqs as $w): ?>
                <tr><td>طلب بيد تنفيذي</td><td><?php echo htmlspecialchars((string) $w['request_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $w['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $w['status'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endforeach; foreach ($pLinks as $w): ?>
                <tr><td>خطوة سلسلة</td><td><?php echo htmlspecialchars((string) $w['source_ref'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>الخطوة <?php echo (int) $w['step_no']; ?></td>
                    <td><?php echo htmlspecialchars((string) $w['sla_due_at'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody></table>
        <div class="text-muted" style="font-size:12px">الوجهة التنفيذية: <a href="approvals_inbox.php">موافقاتي</a> · <a href="ceo_approvals.php">شاشة الاعتماد الأعلى</a></div>
    </div></div>

    <!-- ③ العقود -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-file-signature"></i> ③ العقود — الحالات وآخر التوقيعات</h5></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1.4fr;gap:14px">
        <table class="alltables display no-datatable"><thead><tr><th>الحالة</th><th>العدد</th></tr></thead><tbody>
            <?php foreach ($kContracts as $w): ?>
            <tr><td><?php echo htmlspecialchars((string) $w['st'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $w['c']; ?></td></tr>
            <?php endforeach; if (!$kContracts): ?><tr><td colspan="2" class="text-muted">لا عقود</td></tr><?php endif; ?>
        </tbody></table>
        <table class="alltables display no-datatable"><thead><tr><th>عقد</th><th>الطرف الثاني</th><th>وقت الواقعة</th></tr></thead><tbody>
            <?php foreach ($signedLast as $w): $p = json_decode((string) $w['payload'], true) ?: array(); ?>
            <tr><td>#<?php echo (int) $w['cid']; ?></td>
                <td><?php echo htmlspecialchars((string) ($p['second_party'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['occurred_at'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; if (!$signedLast): ?><tr><td colspan="3" class="text-muted">لا وقائعَ توقيعٍ بعدُ — تُنشر آليًّا عند بلوغ «موقَّع»</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <!-- ④ المشاريع -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-diagram-project"></i> ④ المشاريع — الحالات وآخر المفتوح</h5></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1.4fr;gap:14px">
        <table class="alltables display no-datatable"><thead><tr><th>الحالة</th><th>العدد</th></tr></thead><tbody>
            <?php foreach ($kProjects as $w): ?>
            <tr><td><?php echo htmlspecialchars((string) $w['st'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $w['c']; ?></td></tr>
            <?php endforeach; if (!$kProjects): ?><tr><td colspan="2" class="text-muted">لا مشاريع</td></tr><?php endif; ?>
        </tbody></table>
        <table class="alltables display no-datatable"><thead><tr><th>مشروع</th><th>الاسم</th><th>وقت الواقعة</th></tr></thead><tbody>
            <?php foreach ($charteredLast as $w): $p = json_decode((string) $w['payload'], true) ?: array(); ?>
            <tr><td>#<?php echo (int) $w['pid']; ?></td>
                <td><?php echo htmlspecialchars((string) ($p['name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['occurred_at'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; if (!$charteredLast): ?><tr><td colspan="3" class="text-muted">لا وقائعَ فتحٍ بعدُ — تُنشر آليًّا عند إدراج مشروع</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <!-- ⑤ القرارات ومتابعاتها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-gavel"></i> ⑤ القرارات التنفيذية ومتابعاتها (SRC-10)</h5></div>
    <div class="card-body">
        <table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>القرار</th><th>القضية</th><th>المكلَّف</th><th>المهلة</th><th>حالة الصف</th><th>متابعته</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead><tbody>
            <?php
            $followByRef = array();
            foreach ($decFollow as $f) { $followByRef[$f['source_ref']] = $f; }
            foreach ($decRows as $w):
                // مرجع المتابعة بعد اللحاق EXDC-{id} (والقديم CMP03-{id} احتياط)
                $fu = $followByRef['EXDC-' . (int) $w['id']] ?? ($followByRef['CMP03-' . (int) $w['id']] ?? null); ?>
            <tr><td><?php echo htmlspecialchars((string) ($w['decision_no'] ?: ('EXDC-' . $w['id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(mb_substr((string) $w['issue_desc'], 0, 60), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($w['assigned_dept'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($w['exec_deadline'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $fu ? htmlspecialchars($fu['status'] . ' · ' . $fu['due_at'], ENT_QUOTES, 'UTF-8') : '—'; ?></td></tr>
            <?php endforeach; if (!$decRows): ?><tr><td colspan="6" class="text-center text-muted">لا قراراتَ بعدُ — تُقيَّد من شاشة القرارات</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <!-- ⑥ نبض المال -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-coins"></i> ⑥ نبض المال — وقائع 30 يومًا من الجذر</h5></div>
    <div class="card-body">
        <table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>الواقعة</th><th>العدد</th></tr></thead><tbody>
            <?php foreach ($finPulse as $w): ?>
            <tr><td dir="ltr" style="text-align:right"><?php echo htmlspecialchars((string) $w['k'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $w['c']; ?></td></tr>
            <?php endforeach; if (!$finPulse): ?><tr><td colspan="2" class="text-center text-muted">لا وقائعَ ماليةً في الثلاثين يومًا</td></tr><?php endif; ?>
        </tbody></table>
        <div class="text-muted" style="font-size:12px">التفصيل بشاشات المالية — هذه نبضٌ جامعٌ من ems_business_events (category=financial)</div>
    </div></div>

    <!-- ⑦ المخاطر المفتوحة -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-triangle-exclamation"></i> ⑦ القضايا بلا حسم (سجل المخاطر)</h5></div>
    <div class="card-body">
        <table class="alltables display no-datatable" style="width:100%"><thead>
            <tr><th>المرجع</th><th>النوع</th><th>القضية</th><th>الأثر المقدَّر</th><th>الحالة</th></tr></thead><tbody>
            <?php /* INJ-0411: المرجعُ رمزُ السجلِّ المركزيِّ والنقرُ يفتح بطاقتَه —
                     فالرقمُ والمصدرُ واحدٌ لا رقمان في شاشتين. */ ?>
            <?php foreach ($openRisk as $w): ?>
            <tr><td><a href="../Risk/risk_card.php?id=<?php echo (int) $w['id']; ?>"><?php
                    echo htmlspecialchars((string) ($w['decision_no'] ?: ('RSK-' . (int) $w['id'])), ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string) ($w['assigned_dept'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(mb_substr((string) $w['issue_desc'], 0, 70), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['est_impact'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['status'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; if (!$openRisk): ?><tr><td colspan="5" class="text-center text-muted">لا قضيةَ مفتوحةً — كلُّ المرفوع محسوم</td></tr><?php endif; ?>
        </tbody></table>
    </div></div>

    <!-- ⑧ السقوف والموازنات -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i> ⑧ خريطة السقوف — ملكية التوجيه والموازنات</h5></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <table class="alltables display no-datatable"><thead><tr><th>نوع الطلب المالي</th><th>الإدارة المالكة</th></tr></thead><tbody>
            <?php foreach ($routing as $w): ?>
            <tr><td><?php echo htmlspecialchars((string) $w['request_kind'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['owner_dept'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; if (!$routing): ?><tr><td colspan="2" class="text-muted">خريطة التوجيه فارغة</td></tr><?php endif; ?>
        </tbody></table>
        <table class="alltables display no-datatable"><thead><tr><th>الموازنة</th><th>الحالة</th><th>بنودها</th></tr></thead><tbody>
            <?php foreach ($budgets as $w): ?>
            <tr><td><?php echo htmlspecialchars((string) $w['budget_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $w['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $w['lines_c']; ?></td></tr>
            <?php endforeach; if (!$budgets): ?><tr><td colspan="3" class="text-muted">لا موازناتِ للشركة</td></tr><?php endif; ?>
        </tbody></table>
        <div class="text-muted" style="font-size:12px;grid-column:1/-1">حدودُ DEC-01 (5٪/10k$ للإدارة العامة · حدا الصافي ⅓+½) مُنفذةٌ في محرّك المنح — هذه خريطةُ الملكية</div>
    </div></div>
</div>

