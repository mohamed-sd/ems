<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/tax_invoices.php — الفاتورةُ الضريبية (M-03)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-03 §6: «تفاصيل: الفاتورةُ **بحقولها النظامية ورقمِها التسلسلي** · **زرُّ
 * الطباعة الرسمية** · **لا تعديلَ بعد الإصدار** — والتصحيحُ زرُّ «إشعار
 * دائن/مدين»».
 *
 * فلا حقلَ تعديلٍ هنا ألبتة: عرضٌ · طباعةٌ · وإلغاءٌ ضريبيٌّ بسببٍ مكتوب.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Revenue/TaxInvoiceService.php';

use App\Services\Revenue\TaxInvoiceService as TIS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Contracts/tax_invoices.php';
$can_view = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_edit = true;
} else {
    /* `RPR-03` §٦ — **المسارُ الواحد**: القرارُ من `check_page_permissions()`
           لا من استعلامٍ خاصٍّ بهذا الملفّ. **والفرقُ طبقةُ القوالب**
           (`GOV-AUTH-01`): القراءةُ الخامّةُ لا ترى القالبَ النافذَ، فتُخفى
           الشاشةُ من السايدبارِ وتُفتح بالرابطِ المباشر.
        ⛔ **وفرعُ السوبر أدمن أعلاه لم يُمَسّ** — والأسماءُ كما كانت. */
    $__perm = check_page_permissions($conn, $MODULE_CODE);
    $can_view = (bool) $__perm['can_view'];
    $can_edit = (bool) $__perm['can_edit'];
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الفواتير الضريبية ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('tax invoices super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('tax_invoices.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['ti_action'] ?? '') === 'cancel') {
    if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $r = TIS::cancel($conn, $gate, $company_id, intval($_POST['invoice_id'] ?? 0),
        strval($_POST['cancel_reason'] ?? ''), $uid);
    $redirect($r['ok'] ? 'ألغيت الفاتورة ضريبيا بسببها ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

$invoices = TIS::listAll($gate, 200);
$open = intval($_GET['open'] ?? 0);
$openInv = null;
foreach ($invoices as $i) { if (intval($i['id']) === $open) { $openInv = $i; break; } }
if ($openInv === null && $invoices) { $openInv = $invoices[0]; }

$page_title = 'إيكوبيشن | الفاتورة الضريبية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('contract', 'المستخلصات والفواتير');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'الفاتورة الضريبية'; $header_icon = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    $header_back = array('href' => 'claims.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'المستخلصات');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    // UXW-01 ⑫: شاشةُ دورةٍ اعتماديةٍ تنطق بحالتِها الحية (صادرة · ملغاة) — فتُعلن خطوتَها التالية
    echo ems_next_step('الفاتورة الصادرة تحصل في ذمة العميل — وتصحيحها بإشعار دائن/مدين لا بتعديلها');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا فواتير ضريبية صادرة بعد', 'الفاتورة تصدر آليا عند إجازة مستخلص من شاشة المستخلصات');
    ?>

    <div class="card"><div class="card-body">
        <p class="ti-note">
            الفاتورة تصدر <strong>من المستخلص المعتمد وحده</strong> برقم
            <strong>تسلسلي نظامي لكل (شركة × سنة)</strong>، و<strong>لا تعديل بعد الإصدار</strong>:
            التصحيح <strong>بإشعار دائن/مدين</strong>، والإلغاء الضريبي يلزمه سبب مكتوب
            و<strong>رقم الملغاة لا يعاد استعماله</strong>.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> الفواتير الصادرة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap ti-w100">
            <thead><tr><th>الرقم التسلسلي</th><th>مرجع المستخلص</th><th>العميل</th><th>فترة الإقرار</th>
                <!-- CMP-03 مراجعة عكسية: تاريخ الإصدار والضريبتان أعمدة قائمة بقيمها الحقيقية
                     (كانت قيمة الضريبة تعرض تحت رأس «الإجمالي قبل الضريبة» ملتبسة) -->
                <th>تاريخ الإصدار</th>
                <th>الصافي</th><th>الإجمالي قبل الضريبة</th><th>نسبة الضريبة</th><th>قيمة الضريبة</th><th>الإجمالي</th><th>الحالة</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم الفاتورة</th>
                <th class="ems-fn-th" data-fn="1">الرقم الضريبي للعميل</th>
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">رقم محضر الإقفال</th>
                <th class="ems-fn-th" data-fn="1">وصف الخدمة</th>
                <th class="ems-fn-th" data-fn="1">المعادل بعملة الدفاتر</th>
                <th class="ems-fn-th" data-fn="1">أصدرها</th>
                <th class="ems-fn-th" data-fn="1">رقم الفاتورة الضريبية</th>
                <th class="ems-fn-th" data-fn="1">الفاتورة التجارية</th>
                <th class="ems-fn-th" data-fn="1">رقمنا الضريبي</th>
                <th class="ems-fn-th" data-fn="1">الوعاء الضريبي</th>
                <th class="ems-fn-th" data-fn="1">رقم الإقرار</th>
                <th class="ems-fn-th" data-fn="1">تاريخ التقديم</th>
                <th class="ems-fn-th none" data-fn="1">حالة السداد</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                </tr></thead>
            <tbody>
            <?php foreach ($invoices as $i): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$i['serial_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)($i['claim_no'] ?? ('#' . intval($i['claim_id'])))); ?></td>
                    <td><?php echo htmlspecialchars((string)($i['client_name'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($i['period_from'] ?? '')); ?>
                        → <?php echo htmlspecialchars((string)($i['period_to'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($i['issued_at'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$i['net_amount']); ?></td>
                    <td><?php // الإجمالي قبل الضريبة = الإجمالي − الضريبة (لا الضريبة كما كان ملتبسًا)
                        echo htmlspecialchars(number_format((float)$i['total_amount'] - (float)$i['tax_amount'], 2)); ?></td>
                    <td><?php echo $i['tax_rate'] !== null ? htmlspecialchars((string)$i['tax_rate']) . '٪' : '—'; ?>
                        <?php if ($i['tax_code'] !== null): ?><small>(<?php echo htmlspecialchars((string)$i['tax_code']); ?>)</small><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$i['tax_amount']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$i['total_amount']); ?>
                        <?php echo htmlspecialchars((string)$i['currency']); ?></strong></td>
                    <td><?php echo (string)$i['state'] === 'issued'
                        ? "<span class='badge badge-success'>صادرة</span>"
                        : "<span class='badge badge-danger' title='" . htmlspecialchars((string)$i['cancel_reason']) . "'>ملغاة</span>"; ?></td>
                    <td><a class="btn-primary" href="tax_invoices.php?open=<?php echo intval($i['id']); ?>">اعرضها</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($openInv !== null):
        $fields = json_decode((string)$openInv['tax_fields_json'], true);
        if (!is_array($fields)) { $fields = array(); }
        // مواءمةُ PLAN-03 §5: الفاتورةُ تقرأ **بندَ البيع** لكل سطرٍ من مصدره
        // الحي (claim_lines بمفتاح P-09) — وما لا بندَ له يُعلَن غيرَ موصول.
        $invLines = TIS::linesOf($gate, intval($openInv['claim_id']));
    ?>
    <div class="card" id="printable"><div class="card-header"><h5><i class="fa fa-print"></i>
        فاتورة ضريبية <?php echo htmlspecialchars((string)$openInv['serial_no']); ?></h5></div>
    <div class="card-body">
        <div class="table-container">
        <table class="alltables display nowrap ti-w100" data-no-dt="1">
            <tbody>
            <tr><th>البائع</th><td><?php echo htmlspecialchars((string)($fields['seller_name'] ?? '—')); ?>
                — رقم ضريبي: <?php echo htmlspecialchars((string)($fields['seller_tax_no'] ?? '—')); ?></td></tr>
            <tr><th>المشتري</th><td><?php echo htmlspecialchars((string)($fields['buyer_name'] ?? '—')); ?>
                — رقم ضريبي: <?php echo htmlspecialchars((string)($fields['buyer_tax_no'] ?? '—')); ?>
                <?php echo htmlspecialchars((string)($fields['buyer_address'] ?? '')); ?></td></tr>
            <tr><th>المستخلص والفترة</th><td><?php echo htmlspecialchars((string)($fields['claim_no'] ?? '')); ?>
                · <?php echo htmlspecialchars((string)($fields['period_from'] ?? '')); ?>
                → <?php echo htmlspecialchars((string)($fields['period_to'] ?? '')); ?></td></tr>
            <tr><th>تاريخ الإصدار</th><td><?php echo htmlspecialchars((string)$openInv['issued_at']); ?></td></tr>
            <tr><th>قيمة الضريبة</th><td><?php echo htmlspecialchars((string)$openInv['net_amount']); ?>
                <?php echo htmlspecialchars((string)$openInv['currency']); ?></td></tr>
            <tr><th>نسبة الضريبة</th><td><?php echo htmlspecialchars((string)$openInv['tax_amount']); ?>
                <?php if ($openInv['tax_code'] !== null): ?>
                    (<?php echo htmlspecialchars((string)$openInv['tax_code']); ?>
                    · <?php echo htmlspecialchars((string)$openInv['tax_rate']); ?>٪)
                <?php endif; ?></td></tr>
            <tr><th>القيمة شاملة الضريبة</th><td><strong><?php echo htmlspecialchars((string)$openInv['total_amount']); ?>
                <?php echo htmlspecialchars((string)$openInv['currency']); ?></strong></td></tr>
            </tbody>
        </table>
        </div>

        <?php if ($invLines): ?>
        <h6 class="ti-mt14"><i class="fa fa-list-ol"></i> أسطر المستخلص ببند بيعها
            <small class="ti-muted">(تقرأ من مصدرها الحي — والفاتورة لا تخزن أسطرا)</small></h6>
        <div class="table-container">
        <table class="alltables display nowrap ti-w100" data-no-dt="1">
            <thead><tr><th>بند البيع</th><th>تاريخ الاستحقاق</th><th>المعدة</th><th>وحدة القياس</th>
                <th>الكمية المفوترة</th><th>سعر الوحدة</th><th>القيمة</th></tr></thead>
            <tbody>
            <?php foreach ($invLines as $ln): ?>
                <tr>
                    <td><?php if ($ln['contract_line_id'] !== null && intval($ln['contract_line_id']) > 0): ?>
                            <strong>بند <?php echo intval($ln['sale_line_no']); ?></strong>
                            — <?php echo htmlspecialchars((string)($ln['sale_line_desc'] ?? '')); ?>
                            <small>(<?php echo htmlspecialchars((string)($ln['sale_line_model'] ?? '')); ?>
                                · <?php echo htmlspecialchars((string)($ln['sale_tax_status'] ?? '')); ?>)</small>
                        <?php else: ?>
                            <span class="badge badge-warning">⚠ غير موصول ببند بيع</span>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string)$ln['work_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)$ln['equipment_ref']); ?></td>
                    <td><?php echo htmlspecialchars((string)$ln['unit_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$ln['qty']); ?></td>
                    <td><?php echo htmlspecialchars((string)$ln['unit_price']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$ln['amount']); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <div class="ti-actions">
            <button type="button" class="btn-primary" onclick="window.print()"><i class="fa fa-print"></i> الطباعة الرسمية</button>
            <a class="btn-primary" href="notes.php?invoice_no=<?php echo rawurlencode((string)$openInv['serial_no']); ?>">
                <i class="fa fa-file-circle-plus"></i> إشعار دائن/مدين (التصحيح)</a>
        </div>

        <?php if ($can_edit && (string)$openInv['state'] === 'issued'): ?>
        <form method="post" class="ems-form ti-mt14">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="ti_action" value="cancel">
            <input type="hidden" name="invoice_id" value="<?php echo intval($openInv['id']); ?>">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_122_ab841">سبب الإلغاء الضريبي <span class="ti-req">*</span></label>
                    <input type="text" name="cancel_reason" maxlength="255" required id="emsf_122_ab841"></div>
            </div>
            <p class="ti-warn">الإلغاء لا يمحو صفا و<strong>لا يعيد استعمال رقمه</strong> —
                والتصحيح العادي <strong>بإشعار</strong> لا بإلغاء.</p>
            <div><button type="submit" class="btn-primary"><i class="fa fa-ban"></i> ألغ ضريبيا</button></div>
        </form>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
</body>
</html>
