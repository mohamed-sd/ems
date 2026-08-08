<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Financing/financiers_registry.php — سجل الممولين (FIN-01 §3 · §8-① · الشاشة 210)
 * ───────────────────────────────────────────────────────────────────────────
 * باب التمويل **خلف بوابة المجال المقيَّد** (DEC-01 ② · FIN-01 §1.1):
 * بلا منحة فردية نافذة لا تُفتح الشاشة أصلًا — والعضوية في إدارة ليست صلاحية.
 * الممول **كيان في سجل الكيانات بصفة «ممول» لا سجل موازٍ** — وهذه الشاشة
 * عرضه بنماذجه وعملاته واستحقاقه القائم. وكل فتح بسطر اطّلاع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';

use App\Core\OwnershipDomainGuard;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$role = strval($_SESSION['user']['role'] ?? '');
$uid = intval($_SESSION['user']['id'] ?? 0);
$co = $company_id ?: 4;

// بوابة المجال المقيَّد — fail-closed: منحة فردية أو لا شيء (السوبر خارجها)
$granted = ($role === '-1');
if (!$granted) {
    foreach (array(OwnershipDomainGuard::PERM_OWNER, OwnershipDomainGuard::PERM_TERMS, OwnershipDomainGuard::PERM_VALUE) as $p) {
        if (OwnershipDomainGuard::hasGrant($conn, $co, $uid, $p)) { $granted = true; break; }
    }
}
if (!$granted) {
    ems_gov_flash_redirect('../main/dashboard.php', 'باب التمويل خلف بوابة المجال المقيَّد: الرؤية بمنحة فردية لا بالعضوية في إدارة (FIN-01 §1.1) ❌', 'GOV-PERM-403', 'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');
}
$canTerms = ($role === '-1') || OwnershipDomainGuard::hasGrant($conn, $co, $uid, OwnershipDomainGuard::PERM_TERMS);

// سطر اطّلاع — فتح السجل قراءة لبيانات الملكية
$st = $conn->prepare("INSERT INTO sensitive_read_log (person_id, element_code, subject_type, subject_id, ip, result) VALUES (?, 'ownership.financiers_registry', 'screen', 210, ?, 'allowed')");
$ip = strval($_SERVER['REMOTE_ADDR'] ?? 'cli');
$st->bind_param('is', $uid, $ip);
$st->execute();
$st->close();

$rows = $conn->query(
    "SELECT e.entity_id, e.legal_name, e.base_currency, e.state,
            COUNT(DISTINCT o.op_id) ops,
            COUNT(DISTINCT fa.asset_id) assets,
            MIN(o.signed_date) first_op, MAX(o.signed_date) last_op
       FROM legal_entities e
       JOIN entity_roles r ON r.entity_id = e.entity_id AND r.role = 'financier'
            AND (r.valid_to IS NULL OR r.valid_to >= CURDATE())
       LEFT JOIN financing_operations o ON o.financier_entity_id = e.entity_id
       LEFT JOIN financed_assets fa ON fa.op_id = o.op_id
      GROUP BY e.entity_id ORDER BY ops DESC, e.legal_name"
)->fetch_all(MYSQLI_ASSOC);

// الاستحقاق القائم بكل عملة — لمن يملك رؤية شروط التمويل
$balances = array();
if ($canTerms) {
    $q = $conn->query(
        "SELECT financier_entity_id, currency, SUM(outstanding_balance) bal, COUNT(*) ops
           FROM financing_operations WHERE state IN ('active') GROUP BY financier_entity_id, currency");
    while ($q && ($b = $q->fetch_assoc())) { $balances[intval($b['financier_entity_id'])][] = $b; }
}

$page_title = 'إيكوبيشن | سجل الممولين';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل الممولين'; $header_icon = 'fa fa-hand-holding-dollar';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('الممول كيان في سجل الكيانات بصفة «ممول» — لا سجل موازٍ؛ وهذه الشاشة عرضه بعملياته '
        . 'وأعيانه ومدة علاقته واستحقاقه القائم بكل عملة. الشاشة خلف بوابة المجال المقيَّد: الرؤية '
        . 'بمنحة فردية، وكل فتح بسطر اطّلاع — فالتسرّب يقع بالاطّلاع لا بالتغيير.',
        array('الاستحقاق لمن يملك رؤية الشروط', 'الممول الجديد يُنشأ كيانًا في الحوكمة'));
    ?>
    <div class="card"><div class="card-body">
        <div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">
        <thead><tr><th>كود الممول</th><th>عدد العمليات</th><th>عدد الأعيان</th><th>مدة العلاقة</th><th>الاستحقاق القائم<?php echo $canTerms ? '' : ' (خلف صلاحية الشروط)'; ?></th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الاسم القانوني</th>
              <th class="ems-fn-th" data-fn="1">نوع الممول</th>
              <th class="ems-fn-th" data-fn="1">بلد التسجيل</th>
              <th class="ems-fn-th" data-fn="1">السجل التجاري</th>
              <th class="ems-fn-th" data-fn="1">نماذج التمويل المتعامل بها</th>
              <th class="ems-fn-th" data-fn="1">العملات</th>
              <th class="ems-fn-th" data-fn="1">تصنيف العلاقة</th>
              <th class="ems-fn-th" data-fn="1">شريحة الأهمية</th>
              <th class="ems-fn-th" data-fn="1">أول نشاط</th>
              <th class="ems-fn-th" data-fn="1">آخر نشاط</th>
              <th class="ems-fn-th" data-fn="1">درجة السرية</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead><tbody>
        <?php foreach ($rows as $f): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($f['legal_name']); ?></strong><br><small>#<?php echo intval($f['entity_id']); ?></small></td>
            <td><?php echo intval($f['ops']); ?></td>
            <td><?php echo intval($f['assets']); ?></td>
            <td><small><?php echo $f['first_op'] ? htmlspecialchars($f['first_op'] . ' → ' . $f['last_op']) : '—'; ?></small></td>
            <td>
            <?php if (!$canTerms): ?>
                <em>محجوب — يلزم ownership.finance_terms</em>
            <?php elseif (empty($balances[intval($f['entity_id'])])): ?>
                —
            <?php else: foreach ($balances[intval($f['entity_id'])] as $b): ?>
                <div><?php echo number_format((float) $b['bal'], 2) . ' ' . htmlspecialchars($b['currency']) . ' (' . intval($b['ops']) . ' عملية)'; ?></div>
            <?php endforeach; endif; ?>
            </td>
            <td><span class="badge badge-<?php echo $f['state'] === 'active' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($f['state']); ?></span></td>
        </tr>
        <?php endforeach; if (empty($rows)): ?>
        <tr><td colspan="6">لا كيانات بصفة «ممول» — تُنشأ في <a href="../Governance/entities_registry.php">سجل الكيانات</a> بصفتها</td></tr>
        <?php endif; ?>
        </tbody></table></div>
        <p style="margin-top:10px"><a href="financing_operation_new.php" class="btn-save">+ إنشاء عملية تمويل (النموذج أولًا)</a></p>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
