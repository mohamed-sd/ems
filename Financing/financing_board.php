<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Financing/financing_board.php — لوحة إدارة التمويل (FIN-26 · الشاشة 214)
 * ───────────────────────────────────────────────────────────────────────────
 * لوحة الدور المستقل (26) — نمط لوحة أمين المستودع (M-50): أسئلة صباحه:
 * كم ممولًا نشطًا؟ ما العمليات النافذة واستحقاقها القائم؟ ما أقساط الثلاثين
 * يومًا؟ ما الانحرافات المفتوحة؟ — قراءة وقفز بلا أثر.
 * البيانات خلف بوابة المجال المقيَّد (FIN-01 §1.1): بلا منحة فردية نافذة
 * تُعرض القشرة بإعلان الحجب **ولا يُجلب رقم واحد** (fail-closed على البيانات
 * لا على المسكن — فهذه «الرئيسية» يهبط عليها الدور من الدخول).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';

require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$uid = intval($_SESSION['user']['id'] ?? 0);
$is_super = ($role === '-1');
// مسكن الدور 26 — ويفتحه أيضًا من يدخل باب التمويل أصلًا (1 · 19 · السوبر)
if (!$is_super && !in_array($role, array(EMS_ROLE_FINANCING_MGR, '1', '19'), true)) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لوحة إدارة التمويل لدورها ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$co = ems_scope_company($conn);

$canOwner = $is_super || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_OWNER);
$canTerms = $is_super || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_TERMS);
$granted = $canOwner || $canTerms
    || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_VALUE);

$financiers = $opsActive = $devOpen = 0;
$balances = array();
$due30 = array();
if ($granted) {
    // سطر اطّلاع — فتح اللوحة قراءةً لبيانات المجال المقيَّد
    $st = $conn->prepare("INSERT INTO sensitive_read_log (person_id, element_code, subject_type, subject_id, ip, result) VALUES (?, 'ownership.financing_board', 'screen', 214, ?, 'allowed')");
    $ip = strval($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $st->bind_param('is', $uid, $ip);
    $st->execute();
    $st->close();

    $financiers = intval($conn->query(
        "SELECT COUNT(DISTINCT r.entity_id) c FROM entity_roles r
          WHERE r.role = 'financier' AND (r.valid_to IS NULL OR r.valid_to >= CURDATE())")->fetch_assoc()['c']);
    $opsActive = intval($conn->query(
        "SELECT COUNT(*) c FROM financing_operations WHERE state = 'active'")->fetch_assoc()['c']);
    $devOpen = intval($conn->query(
        "SELECT COUNT(*) c FROM financing_deviations WHERE state <> 'closed'")->fetch_assoc()['c']);

    if ($canTerms) {
        // الاستحقاق القائم بكل عملة — لا تُجمع عملتان في رقم
        $q = $conn->query("SELECT currency, SUM(outstanding_balance) bal FROM financing_operations
                            WHERE state = 'active' GROUP BY currency");
        while ($q && ($b = $q->fetch_assoc())) { $balances[] = $b; }
        $q = $conn->query("SELECT i.currency, COUNT(*) n, SUM(i.amount_total) total
                             FROM financing_installments i
                             JOIN financing_operations o ON o.op_id = i.op_id AND o.state = 'active'
                            WHERE i.state <> 'paid' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY i.currency");
        while ($q && ($b = $q->fetch_assoc())) { $due30[] = $b; }
    }
}

$page_title = 'إيكوبيشن | لوحة إدارة التمويل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة إدارة التمويل'; $header_icon = 'fa fa-money-bill-trend-up';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('لوحة الدور المستقل (26): الممولون النشطون والعمليات النافذة واستحقاقها القائم '
        . 'بكل عملة وأقساط الثلاثين يومًا والانحرافات المفتوحة — قراءة وقفز إلى موضع الفعل. '
        . 'البيانات خلف بوابة المجال المقيَّد: بلا منحة فردية لا يُجلب رقم، وكل فتح بسطر اطّلاع.',
        array('الاستحقاق والأقساط لمن يملك رؤية الشروط', 'الممول الجديد يُنشأ كيانًا في الحوكمة'));
    ?>

    <?php if (!$granted): ?>
    <div class="card"><div class="card-body">
        <div class="alert alert-warning" style="margin:0">
            <strong>باب التمويل خلف بوابة المجال المقيَّد (FIN-01 §1.1).</strong><br>
            لا منحة <code>ownership.*</code> نافذة لحسابك — فلا يُجلب من بيانات الملكية والتمويل شيء
            (fail-closed). المنح فردي بقرار من شاشة «منح المجال المقيَّد» في باب الحوكمة (الأدوار 1 · 19).
        </div>
    </div></div>
    <?php else: ?>
    <div class="card"><div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <a class="badge badge-secondary" style="font-size:15px;padding:8px 14px;text-decoration:none"
           href="financiers_registry.php">الممولون النشطون: <strong><?php echo $financiers; ?></strong> ▸</a>
        <a class="badge badge-secondary" style="font-size:15px;padding:8px 14px;text-decoration:none"
           href="financiers_registry.php">عمليات نافذة: <strong><?php echo $opsActive; ?></strong> ▸</a>
        <div class="badge <?php echo $devOpen ? 'badge-danger' : 'badge-success'; ?>" style="font-size:15px;padding:8px 14px">
            انحرافات مفتوحة: <strong><?php echo $devOpen; ?></strong></div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i>
        الاستحقاق القائم وأقساط الثلاثين يومًا — بكل عملة<?php echo $canTerms ? '' : ' (خلف صلاحية الشروط)'; ?></h5></div>
    <div class="card-body">
        <?php if (!$canTerms): ?>
            <em>محجوب — يلزم <code>ownership.finance_terms</code> (المنح فردي لا بالعضوية في الإدارة)</em>
        <?php elseif (empty($balances) && empty($due30)): ems_state_empty('لا عمليات نافذة ولا أقساط مستحقة — البنية حية بلا حركة بعد'); else: ?>
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
            <thead><tr><th>العملة</th><th>الرصيد القائم</th><th>أقساط 30 يومًا (عددًا)</th><th>أقساط 30 يومًا (قيمةً)</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">كود العملية</th>
              <th class="ems-fn-th" data-fn="1">الممول</th>
              <th class="ems-fn-th" data-fn="1">نموذج التمويل</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التوقيع</th>
              <th class="ems-fn-th" data-fn="1">تاريخ النفاذ</th>
              <th class="ems-fn-th" data-fn="1">تاريخ نهاية العملية</th>
              <th class="ems-fn-th" data-fn="1">الأعيان المموَّلة</th>
              <th class="ems-fn-th" data-fn="1">قيمة شراء العين</th>
              <th class="ems-fn-th" data-fn="1">مصدر قيمة الشراء</th>
              <th class="ems-fn-th" data-fn="1">رأس المال المموَّل</th>
              <th class="ems-fn-th" data-fn="1">مصدر رأس المال</th>
              <th class="ems-fn-th" data-fn="1">نسبة المقدم</th>
              <th class="ems-fn-th" data-fn="1">قيمة المقدم</th>
              <th class="ems-fn-th" data-fn="1">نسبة الأرباح</th>
              <th class="ems-fn-th" data-fn="1">قيمة الأرباح</th>
              <th class="ems-fn-th" data-fn="1">إجمالي السداد</th>
              <th class="ems-fn-th" data-fn="1">المدة بالأشهر</th>
              <th class="ems-fn-th" data-fn="1">عدد الأقساط</th>
              <th class="ems-fn-th none" data-fn="1">قيمة القسط</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ أول قسط</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ آخر قسط</th>
              <th class="ems-fn-th none" data-fn="1">اعتمده</th>
              <th class="ems-fn-th none" data-fn="1">وقّعه</th>
              <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
              <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
              <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
            <tbody>
            <?php
            $cur = array();
            foreach ($balances as $b) { $cur[$b['currency']]['bal'] = (float) $b['bal']; }
            foreach ($due30 as $d) { $cur[$d['currency']]['n'] = intval($d['n']); $cur[$d['currency']]['total'] = (float) $d['total']; }
            foreach ($cur as $c => $v): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c); ?></strong></td>
                    <td><?php echo number_format($v['bal'] ?? 0, 2); ?></td>
                    <td><?php echo intval($v['n'] ?? 0); ?></td>
                    <td><?php echo number_format($v['total'] ?? 0, 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <p style="margin-top:10px">
            <a href="financiers_registry.php" class="btn-primary">سجل الممولين ▸</a>
            <a href="financing_operation_new.php" class="btn-primary">+ إنشاء عملية تمويل (النموذج أولًا)</a>
        </p>
    </div></div>
    <?php endif; ?>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
