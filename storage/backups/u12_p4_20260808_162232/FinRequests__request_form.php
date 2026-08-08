<?php
/** بوابة الطلب المالي D05 — النموذج الموحّد (§5): إنشاء وتحرير المسودة/المعاد + المستندات + البنود */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin request form super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/request_form.php');
if (!$is_super && !$__pp['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا صلاحية لبوابة الطلبات'));
    exit();
}

$my_departments = finreq_departments_for_creator($gate, $role);

// مستخدمو الشركة للإدخال النيابي (§13.1 — يُوثَّق صاحب الطلب والمُدخِل معًا)
$company_users = array();
try {
    $company_users = $gate->select('users', array(
        'columns' => array('id', 'username'),
        'where' => array('status' => 'active'),
        'orderBy' => 'username ASC', 'limit' => 300,
    ));
} catch (\Throwable $t) { /* الحقل اختياري */ }
$catalog = finreq_catalog();
$doc_types = finreq_doc_types();

$req = null; $docs = array(); $lines = array(); $timeline = array();
$rid = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($rid > 0) {
    $req = finreq_fetch($gate, $rid);
    if ($req) {
        $docs = $gate->select('fin_request_documents', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
        $lines = $gate->select('fin_request_lines', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
        $timeline = $gate->select('fin_request_events', array('where' => array('request_id' => $rid), 'orderBy' => 'id ASC'));
    }
}
$editable = $req ? in_array($req['state'], array('draft', 'returned'), true) && ($is_super || intval($req['created_by']) === $user_id) : true;
// صلاحية الإضافة تحكم زرَّ فتح النموذج ونفسَ النموذج (نمط شاشات المجموعة أ)
$can_add = $is_super || !empty($__pp['can_add']);
// النموذج مخفيٌّ افتراضيًا (.allforms) ويُفتح بالزر؛ وعند تحرير مسودةٍ/معادٍ يُفتح فورًا
$form_visible = ($req !== null);

$page_title = 'إيكوبيشن | الطلب المالي الموحّد';
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = $req ? ('الطلب المالي ' . htmlspecialchars($req['request_no'])) : 'طلب مالي جديد';
    $header_icon    = 'fa fa-file-circle-plus';
    $header_actions = array();
    // زر فتح نموذج الإنشاء — محكومٌ بصلاحية الإضافة، ولا يظهر أثناء تحرير طلبٍ قائم
    if ($can_add && !$req && $my_departments) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => 'إنشاء طلب مالي');
    }
    $header_actions[] = array('href' => 'my_requests.php', 'class' => 'add-btn', 'icon' => 'fa fa-list-check', 'label' => 'طلباتي');
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php // حارس «لا إدارة مفعّلة» يخص الإنشاء فقط — عرض طلبٍ قائمٍ متاحٌ لكل
          // ممنوح الصلاحية (المحاسب والمالية يفتحان أي طلبٍ من صناديقهما) ?>
    <?php if (!$my_departments && !$req): ?>
        <div class="card"><div class="card-body">
            <h5>⛔ لا إدارة مفعّلةً لدورك في بوابة الطلبات المالية بعد</h5>
            <p>الإنشاء متاحٌ لأدوار الإدارات المفعّلة في جدول التوجيه — راجع الإدارة المالية.</p>
        </div></div>
    <?php else: ?>

    <?php if ($req): ?>
        <?php
        /* شريط الرحلة (الدستور §5: «أعلى شاشة كل معاملة») — أولُ ما يراه
           فاتحُ الطلب: أين وصل، ومَن عليه الدور، وما سببُ الإعادة إن أُعيد. */
        require_once __DIR__ . '/../includes/journey_bar.php';
        $__jrt = finreq_routing_row($gate, $req['source_module']);
        ems_journey_bar(finreq_journey($gate, $req, $timeline, $__jrt));
        ?>
        <div class="card" style="margin-bottom:14px;">
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
                <div><strong>الحالة:</strong> <?php echo finreq_state_badge($req['state']); ?></div>
                <div><strong>النوع:</strong> <?php echo htmlspecialchars($catalog[$req['request_type']]['label'] ?? $req['request_type']); ?></div>
                <div><strong>الإدارة:</strong> <?php $rt = finreq_routing_row($gate, $req['source_module']); echo htmlspecialchars($rt ? $rt['module_label'] : $req['source_module']); ?></div>
                <?php if (intval($req['duplicate_flag']) === 1): ?><div><span class="badge bg-warning">⚠️ تحذير تكرار</span></div><?php endif; ?>
                <?php if (intval($req['is_exception']) === 1): ?><div><span class="badge bg-danger">🚨 استثناء طارئ معتمد — الدورة تُستكمل خلال 72 ساعة</span></div><?php endif; ?>
                <?php if (!empty($req['event_id'])): ?><div><strong>الحدث المالي:</strong> #<?php echo intval($req['event_id']); ?></div><?php endif; ?>
                <?php if (!empty($req['parent_request_id'])):
                    $__parent = $gate->selectOne('fin_requests', array('columns' => array('request_no'), 'where' => array('id' => intval($req['parent_request_id']))));
                ?>
                    <div><span class="badge bg-info">🌿 فرعٌ من
                        <a href="request_form.php?id=<?php echo intval($req['parent_request_id']); ?>" style="color:inherit;text-decoration:underline;">
                            <?php echo htmlspecialchars($__parent ? $__parent['request_no'] : ('#' . intval($req['parent_request_id']))); ?></a></span></div>
                <?php endif; ?>
                <?php
                // §8.3: الطارئ غير المستثنى بعد — صاحبه (أو مدير إدارته أو السوبر) يطلب التنفيذ المسبق
                $can_ask_exception = $req && strval($req['need_class']) === 'emergency'
                    && intval($req['is_exception']) !== 1
                    && in_array($req['state'], array('under_review', 'pending_approval'), true)
                    && ($is_super || intval($req['created_by']) === $user_id || finreq_role_is($rt, $role, 'manager'));
                ?>
                <?php if ($can_ask_exception): ?>
                    <form action="request_actions.php" method="post" style="display:flex;gap:8px;align-items:center;"
                          onsubmit="var x=prompt('مبرّر التنفيذ الطارئ (يقرؤه المدير المالي):');if(!x)return false;this.reason.value=x;">
                        <input type="hidden" name="action" value="exception_request">
                        <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                        <input type="hidden" name="back" value="request_form.php">
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-bolt"></i> طلب تنفيذٍ طارئ (§8.3)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // المسار المركّب (§6.2): شجرة الفروع + نموذج التفريع (للمحاسب 18 والمدير المالي 17)
    if ($req && empty($req['parent_request_id'])):
        $__kids = finreq_children($gate, intval($req['id']));
        $__can_split = ($is_super || $role === '17' || $role === '18')
            && in_array($req['state'], array('pending_approval', 'approved', 'posted'), true);
        if ($__kids || $__can_split):
    ?>
        <div class="card" style="margin-bottom:14px;border-right:4px solid #0e7490;">
            <div class="card-header"><h5><i class="fa fa-code-branch"></i> المسار المركّب — فروع الطلب (§6.2)</h5></div>
            <div class="card-body">
                <?php if ($__kids): $__ksum = 0.0; ?>
                    <?php foreach ($__kids as $kk): $__ksum += in_array($kk['state'], array('rejected','withdrawn','cancelled','expired','merged'), true) ? 0 : floatval($kk['amount']); ?>
                        <div style="padding:6px 0;border-bottom:1px dashed #e3d9c6;">
                            🌿 <strong><a href="request_form.php?id=<?php echo intval($kk['id']); ?>"><?php echo htmlspecialchars($kk['request_no']); ?></a></strong>
                            · <?php echo htmlspecialchars($catalog[$kk['request_type']]['label'] ?? $kk['request_type']); ?>
                            · <?php echo number_format(floatval($kk['amount']), 2); ?>
                            · <?php echo finreq_state_badge($kk['state']); ?>
                            <span style="color:#6b4e2a;font-size:.9em;"><?php echo htmlspecialchars($kk['justification'] ?? ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:8px;font-weight:700;">مجموع الفروع الحيّة: <?php echo number_format($__ksum, 2); ?> من أصل <?php echo number_format(floatval($req['amount']), 2); ?></div>
                <?php endif; ?>
                <?php if ($__can_split): ?>
                    <form action="request_actions.php" method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px;">
                        <input type="hidden" name="action" value="split_request">
                        <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                        <input type="hidden" name="back" value="request_form.php">
                        <div><label>وصف الفرع *</label><input type="text" name="child_label" placeholder="دفعة مقدّمة 30%" required style="min-width:200px;"></div>
                        <div><label>مبلغ الفرع *</label><input type="number" step="0.01" min="0.01" name="child_amount" required style="min-width:140px;"></div>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-code-branch"></i> تفريع دفعة</button>
                    </form>
                    <p style="margin-top:8px;color:#6b4e2a;font-size:.9em;">💡 الفرع يرث مستندات أصله وبواباته الإدارية، ويولد لدى محاسب الإدارة — ولا يُغلق الأصل وفروعه معلّقة.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; endif; ?>

    <?php if (!$req && !$can_add): ?>
        <div class="card"><div class="card-body">
            <h5>⛔ لا تملك صلاحية إنشاء طلبٍ مالي</h5>
            <p>تستطيع متابعة طلباتك القائمة من «طلباتي المالية».</p>
        </div></div>
    <?php endif; ?>

    <?php if ($editable && ($req || $can_add)): ?>
    <?php if (!$req): ?>
        <div class="alert alert-info" id="finreqHint" style="margin-bottom:14px;font-weight:700;">
            <i class="fa fa-arrow-up"></i> اضغط زر <strong>«إنشاء طلب مالي»</strong> أعلى الصفحة لفتح النموذج.
        </div>
    <?php endif; ?>
    <form id="finreqForm" action="request_actions.php" method="post" class="allforms<?php echo $form_visible ? ' allforms-visible' : ''; ?>">
        <input type="hidden" name="action" value="<?php echo $req ? 'update_draft' : 'create'; ?>">
        <?php if ($req): ?><input type="hidden" name="id" value="<?php echo intval($req['id']); ?>"><?php endif; ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-scale-balanced"></i> بوابة التبرير — قبل الإنشاء (3.0)</h5></div>
            <div class="card-body"><div class="form-grid">
                <div>
                    <label>الإدارة صاحبة الاحتياج *</label>
                    <select name="source_module" required <?php echo $req ? 'disabled' : ''; ?>>
                        <?php foreach ($my_departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['source_module']); ?>" <?php echo ($req && $req['source_module'] === $d['source_module']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['module_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($req): ?><input type="hidden" name="source_module" value="<?php echo htmlspecialchars($req['source_module']); ?>"><?php endif; ?>
                </div>
                <div>
                    <label>نوع الطلب *</label>
                    <select name="request_type" required <?php echo $req ? 'disabled' : ''; ?>>
                        <?php foreach ($catalog as $tkey => $t): if (!$t['active']) continue; ?>
                            <option value="<?php echo $tkey; ?>" <?php echo ($req && $req['request_type'] === $tkey) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($req): ?><input type="hidden" name="request_type" value="<?php echo htmlspecialchars($req['request_type']); ?>"><?php endif; ?>
                </div>
                <div>
                    <label>لماذا نحتاج هذا الآن؟ (المبرّر) *</label>
                    <input type="text" name="justification" maxlength="255" required value="<?php echo htmlspecialchars($req['justification'] ?? ''); ?>" placeholder="لا طلب بلا لماذا">
                </div>
                <div>
                    <label>تصنيف الحاجة *</label>
                    <select name="need_class">
                        <?php foreach (array('planned' => 'مخطط', 'unplanned' => 'غير مخطط', 'urgent' => 'عاجل', 'emergency' => 'طارئ') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($req && $req['need_class'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>المرجع المصدري (أمر شراء/بلاغ/عقد)</label>
                    <input type="text" name="source_ref" maxlength="60" value="<?php echo htmlspecialchars($req['source_ref'] ?? ''); ?>">
                </div>
                <div>
                    <label>البديل المدروس إن وُجد (ملاحظات)</label>
                    <input type="text" name="notes" maxlength="255" value="<?php echo htmlspecialchars($req['notes'] ?? ''); ?>">
                </div>
                <?php if (!$req && $company_users): ?>
                <div>
                    <label>الإدخال نيابةً عن (اختياري — يُوثَّق الطرفان §13.1)</label>
                    <select name="requester_id">
                        <option value="">— أنا صاحب الطلب —</option>
                        <?php foreach ($company_users as $cu): if (intval($cu['id']) === $user_id) { continue; } ?>
                            <option value="<?php echo intval($cu['id']); ?>"><?php echo htmlspecialchars($cu['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div></div>
        </div>

        <div class="card">
            <div class="card-header"><h5><i class="fa fa-user-tag"></i> بيانات الطلب والمستفيد (§5)</h5></div>
            <div class="card-body"><div class="form-grid">
                <div>
                    <label>نوع المستفيد *</label>
                    <select name="beneficiary_type">
                        <?php foreach (array('supplier' => 'مورد', 'employee' => 'موظف', 'customer' => 'عميل', 'internal' => 'داخلي', 'other' => 'آخر') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($req && $req['beneficiary_type'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>اسم الجهة المستفيدة</label>
                    <input type="text" name="beneficiary_name" maxlength="160" value="<?php echo htmlspecialchars($req['beneficiary_name'] ?? ''); ?>">
                </div>
                <div>
                    <label>مرجع المستفيد (معرّف المورد/الموظف)</label>
                    <input type="number" name="beneficiary_ref" min="1" value="<?php echo htmlspecialchars($req['beneficiary_ref'] ?? ''); ?>">
                </div>
                <div>
                    <label>المبلغ *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required value="<?php echo htmlspecialchars($req['amount'] ?? ''); ?>">
                </div>
                <div>
                    <label>العملة</label>
                    <input type="text" name="currency" maxlength="8" value="<?php echo htmlspecialchars($req['currency'] ?? 'SDG'); ?>">
                </div>
                <div>
                    <label>طريقة الدفع المقترحة</label>
                    <select name="payment_method">
                        <option value="">— تقرّرها الخزينة —</option>
                        <?php foreach (array('cash' => 'نقدًا', 'bank' => 'بنكي', 'transfer' => 'تحويل', 'cheque' => 'شيك') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($req && ($req['payment_method'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>البيان</label>
                    <input type="text" name="statement" maxlength="255" value="<?php echo htmlspecialchars($req['statement'] ?? ''); ?>">
                </div>
                <div>
                    <label>تاريخ الاستحقاق المطلوب</label>
                    <input type="date" name="needed_by" value="<?php echo htmlspecialchars($req['needed_by'] ?? ''); ?>">
                </div>
                <div>
                    <label>الأولوية</label>
                    <select name="priority">
                        <?php foreach (array('normal' => 'اعتيادية', 'high' => 'مرتفعة', 'critical' => 'حرجة') as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($req && $req['priority'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>المشروع (اختياري)</label>
                    <input type="number" name="project_id" min="1" value="<?php echo htmlspecialchars($req['project_id'] ?? ''); ?>">
                </div>
                <div>
                    <label>المعدة (اختياري)</label>
                    <input type="number" name="equipment_id" min="1" value="<?php echo htmlspecialchars($req['equipment_id'] ?? ''); ?>">
                </div>
                <div>
                    <label>مركز التكلفة (اختياري)</label>
                    <input type="text" name="cost_center" maxlength="60" value="<?php echo htmlspecialchars($req['cost_center'] ?? ''); ?>">
                </div>
            </div>
            <div style="margin-top:14px;">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $req ? 'تحديث البيانات' : 'حفظ مسودة برقمٍ خادمي'; ?></button>
            </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($req): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-paperclip"></i> المستندات — شرط العبور (<?php echo htmlspecialchars($catalog[$req['request_type']]['docs_label']); ?>)</h5></div>
            <div class="card-body">
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>النوع</th><th>الملف</th><th>رافعه</th><th>التاريخ</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($docs as $d): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($doc_types[$d['doc_type']] ?? $d['doc_type']); ?></td>
                            <td><a href="../<?php echo htmlspecialchars($d['file_ref']); ?>" target="_blank">عرض المستند</a></td>
                            <td><?php echo intval($d['uploaded_by']); ?></td>
                            <td><?php echo htmlspecialchars($d['uploaded_at']); ?></td>
                        </tr>
                    <?php endforeach; if (!$docs): ?>
                        <tr><td colspan="5">لا مستندات بعد — <strong>لن يغادر الطلب المسودة بلا مستنده الإلزامي</strong></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($editable || $req['state'] === 'under_review'): ?>
                <form action="request_actions.php" method="post" enctype="multipart/form-data" class="allforms allforms-visible" style="margin-top:10px;">
                    <input type="hidden" name="action" value="attach_doc">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <div class="form-grid">
                        <div>
                            <label>نوع المستند</label>
                            <select name="doc_type">
                                <?php foreach ($doc_types as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo in_array($k, $catalog[$req['request_type']]['docs'], true) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>الملف (صور/PDF · 5MB)</label>
                            <input type="file" name="doc_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        </div>
                        <div style="align-self:end;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> إرفاق</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($catalog[$req['request_type']]['has_lines'])): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-list-ol"></i> بنود الطلب (§11.2)</h5></div>
            <div class="card-body">
                <table class="table table-bordered no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>البند</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th><th>الإجمالي</th><th></th>
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
                    <?php $i = 1; $lines_total = 0; foreach ($lines as $L): $lines_total += floatval($L['line_total']); ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($L['item']); ?></td>
                            <td><?php echo floatval($L['qty']); ?></td>
                            <td><?php echo htmlspecialchars($L['unit'] ?? '-'); ?></td>
                            <td><?php echo number_format(floatval($L['unit_price']), 2); ?></td>
                            <td><strong><?php echo number_format(floatval($L['line_total']), 2); ?></strong></td>
                            <td>
                                <?php if ($editable): ?>
                                <form action="request_actions.php" method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_line">
                                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                                    <input type="hidden" name="line_id" value="<?php echo intval($L['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف البند؟');"><i class="fa fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($lines): ?><tr><td colspan="5"><strong>مجموع البنود</strong></td><td colspan="2"><strong><?php echo number_format($lines_total, 2); ?></strong></td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php if ($editable): ?>
                <form action="request_actions.php" method="post" class="allforms allforms-visible">
                    <input type="hidden" name="action" value="add_line">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <div class="form-grid">
                        <div><label>البند *</label><input type="text" name="item" maxlength="200" required></div>
                        <div><label>الكمية *</label><input type="number" step="0.01" min="0.01" name="qty" value="1" required></div>
                        <div><label>الوحدة</label><input type="text" name="unit" maxlength="20"></div>
                        <div><label>سعر الوحدة</label><input type="number" step="0.01" min="0" name="unit_price" value="0"></div>
                        <div style="align-self:end;"><button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> إضافة بند</button></div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($editable): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-paper-plane"></i> الإرسال والسحب</h5></div>
            <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
                <form action="request_actions.php" method="post">
                    <input type="hidden" name="action" value="<?php echo $req['state'] === 'returned' ? 'resubmit' : 'submit'; ?>">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <input type="hidden" name="back" value="request_form.php">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> <?php echo $req['state'] === 'returned' ? 'إعادة الإرسال بالرقم نفسه' : 'إرسال للمراجعة الإدارية'; ?></button>
                </form>
                <form action="request_actions.php" method="post" style="display:flex;gap:8px;">
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden" name="id" value="<?php echo intval($req['id']); ?>">
                    <input type="hidden" name="back" value="request_form.php">
                    <input type="text" name="reason" placeholder="سبب السحب (إلزامي)" required style="min-width:220px;">
                    <button type="submit" class="btn btn-outline-danger"><i class="fa fa-box-archive"></i> سحب الطلب</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h5><i class="fa fa-timeline"></i> سجل الطلب الإلحاقي (§8.6 — لا يُمحى)</h5></div>
            <div class="card-body">
                <table class="table table-striped no-datatable" data-no-dt="1">
                    <thead><tr><th>#</th><th>الحدث</th><th>الفاعل</th><th>البيان</th><th>قبل → بعد</th><th>التوقيت</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($timeline as $ev): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><code><?php echo htmlspecialchars($ev['event_type']); ?></code></td>
                            <td><?php echo intval($ev['actor_user_id']); ?><?php echo $ev['on_behalf_of'] ? ' (نيابةً عن ' . intval($ev['on_behalf_of']) . ')' : ''; ?></td>
                            <td><?php echo htmlspecialchars($ev['body'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(($ev['old_value'] !== null || $ev['new_value'] !== null) ? (($ev['old_value'] ?? '—') . ' → ' . ($ev['new_value'] ?? '—')) : ''); ?></td>
                            <td><?php echo htmlspecialchars($ev['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// إظهار/إخفاء نموذج الإنشاء بزر الرأس (#toggleForm) — نمط شاشات المجموعة أ:
// النموذج .allforms مخفيٌّ افتراضيًا بالـCSS ويُفتح بإضافة .allforms-visible.
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }
    ready(function () {
        var btn  = document.getElementById('toggleForm');
        var form = document.getElementById('finreqForm');
        var hint = document.getElementById('finreqHint');
        if (!btn || !form) { return; }

        function setState(open) {
            form.classList.toggle('allforms-visible', open);
            btn.classList.toggle('is-active', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (hint) { hint.style.display = open ? 'none' : ''; }
            if (open) { form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }

        setState(form.classList.contains('allforms-visible'));
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            setState(!form.classList.contains('allforms-visible'));
        });
    });
})();
</script>
</body>
</html>
