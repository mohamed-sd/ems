<?php
/**
 * Portal/ceo_assurance_box.php — صندوقُ التأكيدِ المستقلّ (CEO-16)
 * ───────────────────────────────────────────────────────────────────────────
 * **إسقاطٌ بلا تعديل** (‏`CEO-16` نصًّا: «استقلالُ التأكيدِ يقتضي وصولَه
 * للقمّةِ بلا وساطةِ الإدارةِ الخاضعة») — Grain: **تقريرُ/ملاحظةُ مراجعةٍ
 * واردةٌ للرئيس**.
 *
 * ◆ **الوارِدان بلا وساطة**:
 *   ١) تقاريرُ المراجعةِ الصادرةُ (‏`exec_audit_reports` — تصل مباشرةً
 *      أيًّا كان `delivery_path`، ولا يملك أحدٌ فلترتَها: CEO-Y0119).
 *   ٢) الملاحظاتُ المصعَّدةُ للرئيسِ (‏`iaf_findings` حيث
 *      `escalated_to='ceo'` — تصعيدُ المراجعةِ نفسِها لا تصعيدُ الخاضع).
 * ◆ **قراءةٌ صِرفٌ — صفرُ POST**: قرارُ الرئيسِ هنا حالةٌ مشتقّةٌ من السجلِّ
 *   (‏اطّلاعٌ/إقفالٌ/انتظارٌ) لا حقلَ إدخالٍ — «إسقاطٌ بلا تعديل» يغلب
 *   تصنيفَ الحقلِ في الدفتر، وفعلُ القرارِ في شاشاتِ التصعيدِ القائمة.
 * ◆ **المتكررةُ تُشتقُّ لا تُختلق**: ملاحظةٌ يتكرّر مجالُها (`area_code`)
 *   في أكثرَ من ارتباطِ مراجعةٍ = متكررة. و**التعرّضُ المقدَّرُ بلا عمودِ
 *   مصدرٍ بعدُ** — فلا يُعرَض رقمٌ مختلَق (قاعدةُ الجسرِ: لا رقمَ بلا مصدر).
 * ◆ القراءةُ كلُّها `select` معزولًا بواجهةِ البوّابةِ والتجميعُ في
 *   الذاكرة — صفرُ حرفِ SQL على جدولِ مستأجِرٍ في الملفّ (GAP-29).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/ceo_assurance_box.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('ceo assurance box super') : ems_tenant_db();
$kindQ  = isset($_GET['kind']) ? (string) $_GET['kind'] : '';
$stateQ = isset($_GET['state']) ? (string) $_GET['state'] : '';

/* اليومُ من ساعةِ القاعدةِ — لا date() متفرِّقةً (VT-07) */
$today = (string) ($conn->query('SELECT CURDATE()')->fetch_row()[0]);

$reports = array(); $findings = array(); $users = array();
try { $reports = $gate->select('exec_audit_reports', array('limit' => 2000)); } catch (\Throwable $t) { error_log('ceo_assurance reports: ' . $t->getMessage()); }
try { $findings = $gate->select('iaf_findings', array('limit' => 5000)); } catch (\Throwable $t) { error_log('ceo_assurance findings: ' . $t->getMessage()); }
try { foreach ($gate->select('users', array('columns' => array('id', 'name'), 'limit' => 2000)) as $u0) { $users[(int) $u0['id']] = (string) $u0['name']; } }
catch (\Throwable $t) { error_log('ceo_assurance users: ' . $t->getMessage()); }
$uname = function ($id0) use ($users) {
    $id0 = (int) $id0;
    if ($id0 <= 0) { return 'غير منطبق'; }
    return isset($users[$id0]) ? $users[$id0] : ('مستخدم رقم ' . $id0);
};

/* تكرارُ المجال: مجالٌ يرِد في أكثرَ من ارتباطِ مراجعةٍ = ملاحظتُه متكررة */
$areaEng = array();
foreach ($findings as $f0) {
    $ac = (string) $f0['area_code'];
    if ($ac === '') { continue; }
    $areaEng[$ac][(int) $f0['engagement_id']] = true;
}

$FSTATE = array(
    'open' => 'مفتوحة', 'responded' => 'مجاب عنها', 'in_remediation' => 'قيد المعالجة',
    'evidence_submitted' => 'قدم دليلها', 'closed' => 'مقفلة', 'escalated' => 'مصعدة',
);

$rows = array();
foreach ($reports as $r0) {
    $st = $r0['read_at'] !== null ? 'اطلع عليه' : ($r0['received_at'] !== null ? 'مستلم بانتظار الاطلاع' : 'صادر بانتظار الاستلام');
    $rows[] = array(
        'uid'      => 'وارد تقرير رقم ' . (int) $r0['id'],
        'ref'      => (string) $r0['report_no'],
        'kind'     => 'تقرير مراجعة',
        'kkey'     => 'report',
        'auditee'  => (string) $r0['scope_label'],
        'opinion'  => (string) $r0['overall_opinion'],
        'critical' => (string) (int) $r0['findings_critical'],
        'overdue'  => (int) $r0['overdue_escalated'] > 0 ? ('نعم وعددها ' . (int) $r0['overdue_escalated']) : 'لا',
        'repeated' => 'غير منطبق',
        'reco'     => 'في متن التقرير نفسه',
        'decision' => $r0['read_at'] !== null ? 'اطلع عليه الرئيس' : 'بانتظار الاطلاع',
        'assignee' => 'غير منطبق',
        'due'      => 'غير منطبق',
        'follow'   => (string) $r0['period_label'],
        'state'    => $st,
        'pending'  => $r0['read_at'] === null,
        'by'       => $uname($r0['issued_by']),
        'at'       => (string) $r0['issued_at'],
    );
}
foreach ($findings as $f0) {
    if ((string) $f0['escalated_to'] !== 'ceo') { continue; }
    $ac = (string) $f0['area_code'];
    $rep = ($ac !== '' && isset($areaEng[$ac]) && count($areaEng[$ac]) > 1);
    $stk = (string) $f0['state'];
    $late = ((string) $f0['response_due'] !== '' && $f0['response_due'] !== null
             && (string) $f0['response_due'] < $today && $stk !== 'closed');
    $rows[] = array(
        'uid'      => 'وارد ملاحظة رقم ' . (int) $f0['id'],
        'ref'      => (string) $f0['finding_no'],
        'kind'     => 'ملاحظة مصعدة للرئيس',
        'kkey'     => 'finding',
        'auditee'  => (string) $f0['auditee_dept'],
        'opinion'  => 'شدتها ' . (string) $f0['severity'],
        'critical' => (string) $f0['severity'] === 'critical' ? 'هي نفسها حرجة' : 'غير منطبق',
        'overdue'  => $late ? 'نعم' : 'لا',
        'repeated' => $rep ? 'نعم، مجالها متكرر' : 'لا',
        'reco'     => (string) $f0['action_plan'] !== '' ? mb_substr((string) $f0['action_plan'], 0, 120) : 'بلا خطة بعد',
        'decision' => $stk === 'closed' ? 'اقفلت' : 'بانتظار قرار الرئيس',
        'assignee' => $uname($f0['action_owner']),
        'due'      => (string) $f0['action_due'] !== '' && $f0['action_due'] !== null ? (string) $f0['action_due'] : 'بلا مهلة بعد',
        'follow'   => (string) $f0['evidence_ref'] !== '' ? (string) $f0['evidence_ref'] : 'بلا مرجع دليل بعد',
        'state'    => isset($FSTATE[$stk]) ? $FSTATE[$stk] : $stk,
        'pending'  => $stk !== 'closed',
        'by'       => $uname($f0['raised_by']),
        'at'       => (string) $f0['raised_at'],
    );
}
usort($rows, function ($a, $b) { return strcmp($b['at'], $a['at']); });

$nAll = count($rows); $unread = 0; $waitDec = 0; $nCrit = 0;
foreach ($rows as $x0) {
    if ($x0['kkey'] === 'report' && $x0['pending']) { $unread++; }
    if ($x0['kkey'] === 'finding' && $x0['pending']) { $waitDec++; }
    if ($x0['kkey'] === 'finding' && $x0['critical'] !== 'غير منطبق') { $nCrit++; }
}

$view = array();
foreach ($rows as $x0) {
    if ($kindQ !== '' && $x0['kkey'] !== $kindQ) { continue; }
    if ($stateQ === 'pending' && !$x0['pending']) { continue; }
    $view[] = $x0;
}

$page_title = 'إيكوبيشن | صندوق التأكيد المستقل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'صندوق التأكيد المستقل: تقارير المراجعة الداخلية تصل القمة بلا وساطة'; $header_icon = 'fa fa-inbox'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الرئيس');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nAll) ?></div><div class="ems-stat-label">الوارد كله، تقارير وملاحظات مصعدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($unread) ?></div><div class="ems-stat-label">تقارير لم يطلع عليها بعد</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($waitDec) ?></div><div class="ems-stat-label">ملاحظات مصعدة بانتظار القرار</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nCrit) ?></div><div class="ems-stat-label">ملاحظات حرجة واردة</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>نوع الوارد</label>
                <select name="kind" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    <option value="report" <?= $kindQ === 'report' ? 'selected' : '' ?>>تقارير المراجعة</option>
                    <option value="finding" <?= $kindQ === 'finding' ? 'selected' : '' ?>>الملاحظات المصعدة</option>
                </select>
            </div>
            <div class="ems-filter-item">
                <label>الحالة</label>
                <select name="state" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    <option value="pending" <?= $stateQ === 'pending' ? 'selected' : '' ?>>بانتظار الرئيس وحدها</option>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>معرف الوارد</th>
                    <th>المرجع</th>
                    <th>نوع الوارد</th>
                    <th>الجهة الخاضعة</th>
                    <th>الرأي العام او الشدة</th>
                    <th>الملاحظات الحرجة</th>
                    <th>متأخرة؟</th>
                    <th>متكررة؟</th>
                    <th>توصية المراجعة</th>
                    <th>قرار الرئيس</th>
                    <th>المكلف بالتنفيذ</th>
                    <th>مهلة التنفيذ</th>
                    <th>مرجع المتابعة</th>
                    <th>حالة الوارد</th>
                    <th>المنشئ</th>
                    <th>تاريخ الورود</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($view as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['uid']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['auditee']) ?></td>
                    <td><?= htmlspecialchars($x0['opinion']) ?></td>
                    <td><?= htmlspecialchars($x0['critical']) ?></td>
                    <td><?= htmlspecialchars($x0['overdue']) ?></td>
                    <td><?= htmlspecialchars($x0['repeated']) ?></td>
                    <td><?= htmlspecialchars($x0['reco']) ?></td>
                    <td><?= htmlspecialchars($x0['decision']) ?></td>
                    <td><?= htmlspecialchars($x0['assignee']) ?></td>
                    <td><?= htmlspecialchars($x0['due']) ?></td>
                    <td><?= htmlspecialchars($x0['follow']) ?></td>
                    <td><?= htmlspecialchars($x0['state']) ?></td>
                    <td><?= htmlspecialchars($x0['by']) ?></td>
                    <td><?= htmlspecialchars($x0['at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$view): ?>
                <tr><td colspan="16">لا وارد يطابق المرشح. الوارد تقارير المراجعة الصادرة والملاحظات التي صعدتها المراجعة للرئيس</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>اسقاط بلا تعديل، وقواعده مسماة:</strong>
        الوارد نوعان، تقرير مراجعة صادر يصل الرئيس مباشرة ولا يملك احد فلترته، وملاحظة صعدتها المراجعة للرئيس نفسها.
        (متكررة) تعني ان مجال الملاحظة ورد في اكثر من ارتباط مراجعة واحد.
        (قرار الرئيس) حالة مشتقة من السجل، اطلاع او اقفال او انتظار، وفعل القرار في شاشات التصعيد لا هنا.
        والتعرض المقدر بلا عمود مصدر بعد فلا يعرض له رقم مختلق.
        قراءة صرف ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
