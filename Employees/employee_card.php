<?php
/**
 * Employees/employee_card.php — بطاقةُ الموظف (M-48 · الشاشة 201)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-06 §5: البطاقةُ بتبويباتها السبعة — كان `employee_profile.php` قائمةً
 * ناقصةَ التبويبات. قراءةٌ حيةٌ من مالكيها — **والحساسُ (الراتبُ والسلف)
 * خلف حارس الظهور الثلاثي (H-17)** لا خلف إخفاء زر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Portal/VisibilityGuard.php';

use App\Services\Portal\VisibilityGuard as VG;
use App\Services\Portal\CapacityService as CAP;

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super     = ($current_role === '-1');
$company_id   = intval($_SESSION['user']['company_id'] ?? 0);
$uid          = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$co = $company_id;
$gate = $is_super ? ems_tenant_db()->forAllTenants('employee card super') : ems_tenant_db();

$eid = intval($_GET['id'] ?? 0);
$emp = $eid > 0 ? $gate->selectOne('employees', array('where' => array('id' => $eid))) : null;
$tab = in_array(strval($_GET['tab'] ?? '1'), array('1','2','3','4','5','6','7'), true)
     ? strval($_GET['tab'] ?? '1') : '1';

// سياقُ المشاهد للحارس الثلاثي — من صفته الفعّالة (H-15)
$viewerCap = null;
foreach (CAP::activeOf($conn, $gate, $uid) as $vc) {
    if ((string)$vc['state'] === 'active') { $viewerCap = $vc; break; }
}
$viewer = array('account_id' => $uid, 'role' => $current_role,
    'capacity_type' => $viewerCap ? (string)$viewerCap['capacity_type'] : 'employee',
    'scope_type' => $viewerCap ? (string)$viewerCap['scope_type'] : '',
    'scope_id' => $viewerCap ? $viewerCap['scope_id'] : null);
// حسابُ المعروض — من ربط users.employee_id
$subjectAccount = 0;
$sa = $conn->query("SELECT id FROM users WHERE employee_id=" . $eid . " AND company_id={$co} LIMIT 1");
if ($sa && ($sx = $sa->fetch_assoc())) { $subjectAccount = intval($sx['id']); }
$subject = array('account_id' => $subjectAccount ?: $uid);

$TABS = array('1' => 'البيانات', '2' => 'عقده وسجله', '3' => 'صفاته (H-15)',
              '4' => 'إنتاجه', '5' => 'راتبه وسلفه', '6' => 'عهده', '7' => 'تقييمه ونشاطه');

$page_title = 'إيكوبيشن | بطاقة الموظف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ②: أنماطٌ موضعيةٌ نُقلت أصنافًا صفحيةً ببادئةِ الشاشة ecard- */
.ecard-idbar { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.ecard-name { font-size: 1.1rem; }
.ecard-tabs { margin-inline-start: auto; }
.ecard-tab {
    border: 1px solid var(--c-dddddd, #ddd);
    border-radius: 6px;
    padding: 4px 10px;
    margin: 0 2px;
}
.ecard-tab.is-active { background: var(--c-e2b93b, #e2b93b); font-weight: 800; }
.ecard-table { width: 100%; }
.ecard-chips { display: flex; gap: 12px; flex-wrap: wrap; }
.ecard-chip { font-size: 15px; padding: 10px 16px; }
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بطاقة الموظف'; $header_icon = 'fa fa-id-card';
    $header_actions = array();
    $header_back = array('href' => 'employees.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'الموظفون');
    include('../includes/page_header.php');
    ems_screen_about('بطاقة الموظف بتبويباتها السبعة — قراءة من مالكيها؛ والحساس (راتب وسلف) '
        . 'يمر بحارس الظهور الثلاثي: صفة المشاهد ثم علاقته بالمعروض ثم مفتاح HR.', array());
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بيانات بطاقة لهذا الموظف', 'اختر موظفا من سجل الموظفين لتفتح بطاقته بتبويباتها السبعة');
    ?>

    <?php if (!$emp): ems_state_empty('اختر موظفا', 'إلى الموظفين', 'employees.php'); ?>
    <?php else: ?>
    <div class="card"><div class="card-body ecard-idbar">
        <strong class="ecard-name"><?php echo htmlspecialchars((string)$emp['name']); ?></strong>
        <span class="badge badge-secondary"><?php echo htmlspecialchars((string)($emp['employee_code'] ?? '')); ?></span>
        <span class="ecard-tabs">
        <?php foreach ($TABS as $tk => $tl): ?>
            <a class="btn btn-sm ecard-tab<?php echo $tk === $tab ? ' is-active' : ''; ?>"
               href="?id=<?php echo $eid; ?>&tab=<?php echo $tk; ?>"><?php echo $tl; ?></a>
        <?php endforeach; ?></span>
    </div></div>

    <div class="card"><div class="card-body">
    <?php
    switch ($tab) {
        case '1':
            echo '<div class="table-container"><table class="alltables display ecard-table" data-no-dt="1"><tbody>';
            foreach (array('name' => 'الاسم', 'employee_code' => 'الكود', 'employee_type' => 'النوع',
                           'employment_classification' => 'التصنيف', 'phone' => 'الهاتف',
                           'nationality' => 'الجنسية', 'license_number' => 'الرخصة',
                           'license_expiry_date' => 'انتهاء الرخصة', 'start_date' => 'بدء الخدمة') as $k => $lbl) {
                if (array_key_exists($k, $emp)) {
                    echo '<tr><th>' . $lbl . '</th><td>' . htmlspecialchars((string)($emp[$k] ?? '—')) . '</td></tr>';
                }
            }
            echo '</tbody></table></div>'
               . '<p><a class="btn-primary" href="employee_profile.php?id=' . $eid . '">الملف الكامل ▸</a></p>';
            break;
        case '2':
            $rows = array();
            $r = $conn->query("SELECT id, category, relation_type, start_date, end_date, state, version
                                 FROM employee_contracts
                                WHERE company_id={$co} AND employee_id={$eid}
                                  AND COALESCE(is_deleted,0)=0 ORDER BY start_date DESC");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا عقود في السجل الموحد (H-08)', 'إلى السجل', '../Workforce/contract_registry.php'); break; }
            echo '<div class="table-container"><table class="alltables display ecard-table" data-no-dt="1">'
               . '<thead><tr><th>#</th><th>الفئة</th><th>العلاقة</th><th>المدة</th><th>الحال</th><th>نسخة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . intval($x['id']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['category']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['relation_type']) . '</td>'
                   . '<td>' . htmlspecialchars($x['start_date'] . ' → ' . ($x['end_date'] ?: 'مفتوح')) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td>'
                   . '<td>' . intval($x['version']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '3':
            $rows = array();
            $r = $conn->query("SELECT capacity_type, role, scope_type, scope_id, state,
                                      valid_from, valid_to, state_reason
                                 FROM user_capacities
                                WHERE company_id={$co} AND person_id={$eid} ORDER BY id");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا صفات مشتقة — الاشتقاق في شاشة 182', 'إليها', '../user_capacities.php'); break; }
            echo '<div class="table-container"><table class="alltables display ecard-table" data-no-dt="1">'
               . '<thead><tr><th>الصفة</th><th>الدور</th><th>النطاق</th><th>السريان</th><th>الحال</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['capacity_type']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['role']) . '</td>'
                   . '<td>' . htmlspecialchars($x['scope_type'] . ($x['scope_id'] !== null ? (' #' . $x['scope_id']) : '')) . '</td>'
                   . '<td>' . htmlspecialchars($x['valid_from'] . ' → ' . ($x['valid_to'] ?: 'مفتوح')) . '</td>'
                   . '<td>' . ((string)$x['state'] === 'active'
                        ? "<span class='badge badge-success'>نشطة</span>"
                        : ("<span class='badge badge-secondary' title='" . htmlspecialchars((string)$x['state_reason']) . "'>مجمدة</span>")) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '4':
            $r = $conn->query("SELECT award_unit_type, ROUND(SUM(qty_due),2) q, COUNT(*) n
                                 FROM unit_party_awards
                                WHERE company_id={$co} AND party='employee' AND party_ref={$eid}
                                  AND deleted_at IS NULL GROUP BY award_unit_type");
            $any = false;
            echo '<div class="ecard-chips">';
            while ($r && ($x = $r->fetch_assoc())) {
                $any = true;
                echo '<div class="badge badge-secondary ecard-chip">'
                   . htmlspecialchars($x['award_unit_type'] . ': ' . $x['q'] . ' (' . $x['n'] . ' حكما)') . '</div>';
            }
            echo '</div>';
            if (!$any) { ems_state_empty('لا أحكام إنتاج لهذا الموظف — «لا ينطبق» يعلن لا صفرا'); }
            break;
        case '5':
            // الحساسُ خلف الحارس الثلاثي — «لا يرى رواتبَهم إلا بمنحٍ صريح» (USR-01 §4)
            $v = VG::check($conn, $gate, $co, $viewer, 'card.payroll', $subject);
            if ($v['decision'] === 'deny') { ems_state_error('403 — ' . $v['reason']); break; }
            if ($v['decision'] !== 'allow') {
                echo '<div class="alert alert-warning"><i class="fa fa-lock"></i> '
                   . 'قسم الراتب والسلف **محجوب بقرار موثق**: ' . htmlspecialchars($v['reason'])
                   . ' — فتحه بمنح مؤقت من لوحة الظهور (ADM-01)</div>';
                break;
            }
            // M-14 BR-GOV-07: القراءةُ على السرِّ فعلٌ يُسجَّل — بعد السماح لا قبله
            require_once __DIR__ . '/../includes/sensitive_read_log.php';
            ems_log_sensitive_read($conn, 'salary', 'employee:' . $eid, 'Employees/employee_card.php');
            $pr = $conn->query("SELECT pr.period_from, pr.period_to, ROUND(SUM(pl.amount),2) total
                                  FROM payroll_lines pl JOIN payroll_runs pr ON pr.id = pl.run_id
                                 WHERE pl.company_id={$co} AND pl.person_id={$eid}
                                   AND COALESCE(pr.is_deleted,0)=0
                                 GROUP BY pr.id ORDER BY pr.period_from DESC LIMIT 6");
            $any = false;
            while ($pr && ($x = $pr->fetch_assoc())) {
                $any = true;
                echo '<div class="alert alert-info">' . htmlspecialchars($x['period_from'] . ' → '
                   . $x['period_to'] . ' — إجمالي المكونات ' . $x['total']) . '</div>';
            }
            $ad = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(amount),0),2) v
                                 FROM employee_advances
                                WHERE company_id={$co} AND employee_id={$eid}")->fetch_assoc();
            if ($ad && intval($ad['n']) > 0) {
                $any = true;
                echo '<div class="alert alert-info">السلف: ' . intval($ad['n']) . ' بمجموع '
                   . htmlspecialchars((string)$ad['v']) . ' — <a href="../Workforce/employee_advances.php">شاشتها ▸</a></div>';
            }
            if (!$any) { ems_state_empty('لا كشوف ولا سلف بعد'); }
            break;
        case '6':
            $rows = array();
            $r = $conn->query("SELECT item_name, qty_issued, qty_returned, state, transfer_date
                                 FROM proc_custody
                                WHERE company_id={$co} AND holder_name = '"
                                . $conn->real_escape_string((string)$emp['name']) . "'
                                ORDER BY id DESC LIMIT 50");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا عهد مسلمة باسمه'); break; }
            echo '<div class="table-container"><table class="alltables display ecard-table" data-no-dt="1">'
               . '<thead><tr><th>الصنف</th><th>مصروف</th><th>مرجع</th><th>الحال</th><th>التاريخ</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['item_name']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['qty_issued']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['qty_returned']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['transfer_date']) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '7':
            $ev = $conn->query("SELECT e.period_from, e.period_to, e.state, e.final_score
                                  FROM evaluations e JOIN user_capacities uc ON uc.id = e.capacity_id
                                 WHERE e.company_id={$co} AND uc.person_id={$eid}
                                 ORDER BY e.id DESC LIMIT 10");
            $any = false;
            while ($ev && ($x = $ev->fetch_assoc())) {
                $any = true;
                echo '<div class="alert alert-info">تقييم ' . htmlspecialchars($x['period_from'] . ' → '
                   . $x['period_to'] . ' — ' . $x['state']
                   . ($x['final_score'] !== null ? (' · الدرجة ' . $x['final_score']) : '')) . '</div>';
            }
            if ($subjectAccount > 0) {
                $ac = $conn->query("SELECT COUNT(*) n, MAX(at) last_at FROM portal_activity_log
                                     WHERE company_id={$co} AND account_id={$subjectAccount}")->fetch_assoc();
                if ($ac && intval($ac['n']) > 0) {
                    $any = true;
                    echo '<div class="alert alert-info">نشاط بوابته: ' . intval($ac['n'])
                       . ' حدثا · آخره ' . htmlspecialchars((string)$ac['last_at']) . '</div>';
                }
            }
            if (!$any) { ems_state_empty('لا تقييمات ولا نشاط بوابة بعد'); }
            break;
    }
    ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
