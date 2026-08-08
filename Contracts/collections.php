<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/collections.php — الذممُ والتحصيل (M-05)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-03 §6: «قائمة: **الذممُ بأعمارها ملوّنةً** · **تحصيلٌ بمرجعٍ إلزامي** ·
 * **تخصيصُ الدفعة ظاهرٌ** · متابعةٌ بموعدٍ تالٍ».
 * §4: «التحصيلُ الجزئي — **أقدمُ فاتورةٍ أولًا** ما لم يحدد العميلُ مرجعًا صريحًا».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Revenue/CollectionService.php';

use App\Services\Revenue\CollectionService as COL;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Contracts/collections.php';
$can_view = $can_add = false;
if ($is_super_admin) {
    $can_view = $can_add = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الذمم والتحصيل ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('collections super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('collections.php', $msg, 'GOV-INFO-200', ''); exit(); };

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['col_action'] ?? '') === 'record') {
    if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $r = COL::record($conn, $gate, $company_id, array(
        'client_id'     => $_POST['client_id'] ?? 0,
        'amount'        => $_POST['amount'] ?? 0,
        'bank_ref'      => $_POST['bank_ref'] ?? '',
        'received_on'   => $_POST['received_on'] ?? '',
        'receivable_id' => $_POST['receivable_id'] ?? 0,
        'currency'      => $_POST['currency'] ?? 'USD',
        'method'        => $_POST['method'] ?? 'bank',
        'memo'          => $_POST['memo'] ?? '',
    ), $uid);
    if ($r['ok']) {
        $msg = 'سُجّل القبضُ وخُصّص ' . $r['allocated']
             . ' على ' . count($r['allocations']) . ' ذمّة';
        if ($r['unallocated'] > 0) { $msg .= ' · ' . $r['reason']; }
        if ($r['claims_touched']) {
            $st = array();
            foreach ($r['claims_touched'] as $c) { $st[] = '#' . $c['claim_id'] . '→' . $c['state']; }
            $msg .= ' · ارتدَّت حالةُ: ' . implode(' · ', $st);
        }
        $redirect($msg . ' ✅');
    }
    $redirect($r['code'] . ' — ' . $r['reason'] . ' ❌');
}

// ── P-07: تخصيصُ سندٍ قائمٍ على **أهدافٍ متعددة** — توسعةُ M-05 لا بابٌ ثانٍ ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['col_action'] ?? '') === 'allocate') {
    if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $targets = array();
    foreach (($_POST['t_kind'] ?? array()) as $i => $k) {
        $amt = trim(strval($_POST['t_amount'][$i] ?? ''));
        $ref = intval($_POST['t_ref'][$i] ?? 0);
        if ($amt === '' || $ref <= 0) { continue; }
        $targets[] = array('target_kind' => strval($k), 'target_ref' => $ref,
                           'amount' => $amt, 'note' => strval($_POST['t_note'][$i] ?? ''));
    }
    $r = COL::allocate($conn, $gate, $company_id, intval($_POST['payment_id'] ?? 0), $targets, $uid);
    $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

$clients = array();
try {
    $clients = $gate->scopedQuery(array('scope' => array('c' => 'clients')),
        "SELECT c.id, c.client_name FROM clients c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.client_name");
} catch (\Throwable $t) { $clients = array(); }

$filterClient = intval($_GET['client_id'] ?? 0);
$ageing = COL::ageing($gate, $filterClient);

$recent = array();
try {
    $recent = $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
        "SELECT p.id, p.payment_no, p.amount, p.currency, p.bank_ref, p.received_on, p.state
           FROM fin_payments p
          WHERE {TENANT_SCOPE} AND p.direction = 'collection' AND COALESCE(p.is_deleted,0)=0
          ORDER BY p.id DESC LIMIT 30");
} catch (\Throwable $t) { $recent = array(); }

// P-08: الفروقُ الأربعةُ **منفصلةً** وسجلُّ فروق الصرف
require_once __DIR__ . '/../app/Services/Finance/FxSettlementService.php';
$four = \App\Services\Finance\FxSettlementService::fourfold($gate, $filterClient);
$fxRows = \App\Services\Finance\FxSettlementService::differencesOf($gate, '', 50);

// P-07: السنداتُ ذاتُ رصيدٍ غيرِ مخصَّص — **الفجوةُ تُرى**
$unallocated = COL::unallocatedPayments($gate);
$PAY = intval($_GET['payment_id'] ?? 0);
$payInfo = $PAY > 0 ? COL::unallocatedOf($gate, $PAY) : null;
$payAllocs = $PAY > 0 ? COL::allocationsOf($gate, $PAY) : array();
$TARGET_AR = COL::TARGET_AR;

$page_title = 'إيكوبيشن | الذمم والتحصيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الذمم والتحصيل'; $header_icon = 'fa fa-hand-holding-dollar';
    $header_actions = array();
    $header_back = array('href' => 'client_statement.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'كشف حساب العميل');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-money-check-dollar"></i> تسجيلُ قبض</h5></div>
    <div class="card-body">
        <p style="color:#666">
            <strong>المرجعُ البنكيُّ إلزامي</strong> («قبضٌ بمرجعٍ بنكيٍّ أو سند» §4)، و<strong>لا يُقبض
            المرجعُ نفسُه بالمبلغ نفسِه في اليوم نفسِه مرتين</strong>. والتخصيصُ
            <strong>لأقدم فاتورةٍ أولًا</strong> ما لم تُحدَّد ذمّةٌ بعينها — <strong>وكلُّ تخصيصٍ سطرٌ يُرى</strong>.
        </p>
        <form method="post" class="ems-form">
            <input type="hidden" name="col_action" value="record">
            <div class="form-grid">
                <div class="form-group"><label>العميل <span style="color:#c00">*</span></label>
                    <select name="client_id" required>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>">
                                <?php echo htmlspecialchars((string)$c['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>المبلغ <span style="color:#c00">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" required></div>
                <div class="form-group"><label>المرجع البنكي / السند <span style="color:#c00">*</span></label>
                    <input type="text" name="bank_ref" maxlength="120" required></div>
                <div class="form-group"><label>تاريخ القبض <span style="color:#c00">*</span></label>
                    <input type="date" name="received_on" required></div>
                <div class="form-group"><label>ذمّةٌ بعينها <small>— فارغٌ = أقدمُ فاتورةٍ أولًا</small></label>
                    <select name="receivable_id">
                        <option value="0">— أقدمُ فاتورةٍ أولًا —</option>
                        <?php foreach ($ageing as $r): if ((float)$r['outstanding'] <= 0) { continue; } ?>
                            <option value="<?php echo intval($r['id']); ?>">
                                <?php echo htmlspecialchars((string)$r['doc_ref']); ?>
                                — متبقٍّ <?php echo htmlspecialchars((string)$r['outstanding']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>العملة</label><input type="text" name="currency" value="USD" maxlength="8"></div>
                <div class="form-group"><label>الطريقة</label><input type="text" name="method" value="bank" maxlength="30"></div>
                <div class="form-group"><label>بيان</label><input type="text" name="memo" maxlength="200"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> سجّل القبض</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock"></i> الذممُ بأعمارها</h5></div>
    <div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <label>ترشيحٌ بعميل:</label>
            <select name="client_id" onchange="this.form.submit()">
                <option value="0">— الكل —</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo intval($c['id']); ?>" <?php echo $filterClient === intval($c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$c['client_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>مرجع التفويض</th><th>المبلغ</th><th>المحصَّل</th><th>المتبقي</th>
                <th>العمر (يوم)</th><th>الحالة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">العميل</th>
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">قيمة الفاتورة</th>
                <th class="ems-fn-th" data-fn="1">الرصيد</th>
                <th class="ems-fn-th" data-fn="1">العملة المقبوضة فعلًا</th>
                <th class="ems-fn-th" data-fn="1">سعر الصرف عند القبض</th>
                <th class="ems-fn-th" data-fn="1">المعادل بعملة الدفاتر</th>
                <th class="ems-fn-th" data-fn="1">إتاحة النقد للاستخدام</th>
                <th class="ems-fn-th" data-fn="1">قيود تحويل قائمة</th>
                <th class="ems-fn-th" data-fn="1">أيام التأخر</th>
                <th class="ems-fn-th" data-fn="1">شريحة العمر</th>
                <th class="ems-fn-th" data-fn="1">حالة التحصيل</th>
                <th class="ems-fn-th" data-fn="1">خطة التحصيل</th>
                <th class="ems-fn-th" data-fn="1">المسؤول</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                </tr></thead>
            <tbody>
            <?php foreach ($ageing as $r):
                $age = intval($r['age_days']);
                $cls = 'badge-success';
                if ($age >= 90) { $cls = 'badge-danger'; }
                elseif ($age >= 60) { $cls = 'badge-warning'; }
                elseif ($age >= 30) { $cls = 'badge-secondary'; }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($r['doc_ref'] ?? ('#' . intval($r['id'])))); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['amount']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['collected']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$r['outstanding']); ?></strong></td>
                    <td><span class="badge <?php echo $cls; ?>"><?php echo $age; ?></span></td>
                    <td><?php echo htmlspecialchars((string)$r['state']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i> آخرُ المقبوضات وتخصيصُها</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>رقم الفاتورة</th><th>المرجع البنكي</th><th>تاريخ الفاتورة</th><th>المبلغ</th>
                <th>الحال</th><th>التخصيص</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $p): $alloc = COL::allocationsOf($gate, intval($p['id'])); ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$p['payment_no']); ?></td>
                    <td><?php echo (string)$p['bank_ref'] === 'legacy_no_ref'
                        ? '<span class="badge badge-warning">موروثٌ بلا مرجع</span>'
                        : htmlspecialchars((string)$p['bank_ref']); ?></td>
                    <td><?php echo htmlspecialchars((string)($p['received_on'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$p['amount']); ?>
                        <?php echo htmlspecialchars((string)$p['currency']); ?></td>
                    <td><?php echo htmlspecialchars((string)$p['state']); ?></td>
                    <td><?php if (!$alloc): ?>
                            <span class="badge badge-warning">بلا تخصيصٍ مسجَّل</span>
                        <?php else: foreach ($alloc as $a): ?>
                            <div><?php echo htmlspecialchars((string)$a['doc_ref']); ?>:
                                <strong><?php echo htmlspecialchars((string)$a['amount']); ?></strong>
                                <small>(<?php echo (string)$a['basis'] === 'explicit'
                                    ? 'مرجعٌ صريح' : 'أقدمُ فاتورةٍ أولًا'; ?>)</small></div>
                        <?php endforeach; endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <!-- ═══ P-08: الفروقُ الأربعةُ — أربعةُ أبوابٍ لا مجموعٌ واحد ═══ -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-code-compare"></i>
        الفروقُ الأربعة — <strong>لا تُخلط ولا تُجمع في رقم</strong></h5></div>
    <div class="card-body">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:210px">
                <div style="color:#6b7280;font-size:.85em">① رصيدٌ غيرُ مسدد <small>(بعملة كل ذمّة)</small></div>
                <div style="font-size:1.15em;font-weight:700"><?php
                    $__p = array();
                    foreach ($four['unpaid'] as $__c => $__v) {
                        $__p[] = $__v . ' ' . ($__c !== '' ? htmlspecialchars((string)$__c) : '؟');
                    }
                    echo $__p ? implode(' · ', $__p) : 'صفر'; ?></div>
                <small>بيتُه <code>fin_receivables.outstanding</code></small>
            </div>
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:190px">
                <div style="color:#6b7280;font-size:.85em">② فرقُ صرفٍ <strong>محقَّق</strong></div>
                <div style="font-size:1.15em;font-weight:700"><?php
                    echo $four['realized'] . ' ' . htmlspecialchars((string)$four['functional']); ?></div>
                <small>عند السداد — بالعملة الوظيفية</small>
            </div>
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:190px">
                <div style="color:#6b7280;font-size:.85em">③ فرقُ صرفٍ <strong>غيرُ محقَّق</strong></div>
                <div style="font-size:1.15em;font-weight:700"><?php
                    echo $four['unrealized'] . ' ' . htmlspecialchars((string)$four['functional']); ?></div>
                <small><strong>تقديرٌ لا يُقفل ذمّةً ولا يمسّ رصيدًا</strong></small>
            </div>
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:10px 16px;min-width:190px">
                <div style="color:#6b7280;font-size:.85em">④ زيادةُ سداد</div>
                <div style="font-size:1.15em;font-weight:700"><?php echo $four['overpayment']; ?></div>
                <small><strong>رصيدٌ دائنٌ للعميل لا إيراد</strong></small>
            </div>
        </div>
        <?php if ($fxRows): ?>
        <div class="table-container" style="margin-top:12px">
            <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th>النوع</th><th>المصدر</th><th>عملة الفاتورة</th><th>المبلغ</th>
                    <th>سعرُ الاعتراف</th><th>سعرُ السداد</th><th>تاريخ الاستحقاق</th><th>البيان</th></tr></thead>
                <tbody>
                <?php foreach ($fxRows as $d): ?>
                    <tr><td><span class="badge <?php echo (string)$d['kind'] === 'realized'
                            ? 'badge-info' : 'badge-secondary'; ?>">
                            <?php echo (string)$d['kind'] === 'realized' ? 'محقَّق' : 'غيرُ محقَّق'; ?></span></td>
                        <td><?php echo htmlspecialchars((string)$d['source_kind']); ?>
                            #<?php echo intval($d['source_ref']); ?></td>
                        <td><?php echo htmlspecialchars((string)$d['from_currency']); ?></td>
                        <td><strong><?php echo htmlspecialchars((string)$d['amount']); ?></strong>
                            <?php echo htmlspecialchars((string)$d['functional_currency']); ?></td>
                        <td><?php echo htmlspecialchars((string)($d['rate_from'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)($d['rate_to'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)$d['occurred_on']); ?></td>
                        <td style="white-space:normal"><small><?php
                            echo htmlspecialchars((string)($d['note'] ?? '—')); ?></small></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div></div>

    <!-- ═══ P-07: أهدافُ التخصيص الخمسة والرصيدُ غيرُ المخصَّص ═══ -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-scale-balanced"></i>
        تخصيصٌ على أهدافٍ متعددة — <strong>والرصيدُ غيرُ المخصَّص ظاهرٌ لا يختفي</strong></h5></div>
    <div class="card-body">
        <p style="color:#4b5563;line-height:1.8">
            <i class="fas fa-circle-info"></i>
            السندُ الواحدُ يُخصَّص على <strong>خمسةِ أنواعٍ من الأهداف</strong>:
            مقدَّمٌ · فاتورةٌ · معلَمٌ · محتجَزٌ · ختامية.
            و<strong>Σ التخصيصات لا تتجاوز السند أبدًا</strong>،
            و<strong>ما بقي رصيدٌ دائنٌ للعميل لا إيراد</strong> — يُعلَن ويُسوّى بقرار.
        </p>

        <?php if ($unallocated): ?>
        <div class="table-container" style="margin-bottom:14px">
            <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th>السند</th><th>المرجع</th><th>التاريخ</th><th>المبلغ</th>
                    <th>المخصص المكوَّن</th><th><strong>غيرُ المخصَّص</strong></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($unallocated as $p): ?>
                    <tr style="background:#fff7ed">
                        <td><?php echo htmlspecialchars((string)$p['payment_no']); ?></td>
                        <td><?php echo htmlspecialchars((string)$p['bank_ref']); ?></td>
                        <td><?php echo htmlspecialchars((string)($p['received_on'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)$p['amount']); ?>
                            <?php echo htmlspecialchars((string)$p['currency']); ?></td>
                        <td><?php echo htmlspecialchars((string)$p['allocated_amount']); ?></td>
                        <td><span class="badge badge-warning">
                            <?php echo htmlspecialchars((string)$p['unallocated_amount']); ?></span></td>
                        <td><a class="action-btn" href="?payment_id=<?php echo intval($p['id']); ?>">
                            <i class="fa fa-scale-balanced"></i> خصّص</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p><em>لا سندَ فيه رصيدٌ غيرُ مخصَّص — <strong>وΣ التخصيصات = السند في كلٍّ</strong>.</em></p>
        <?php endif; ?>

        <?php if ($payInfo !== null): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            <span class="badge badge-secondary" style="padding:6px 12px">مبلغُ السند
                <?php echo $payInfo['amount'] . ' ' . htmlspecialchars((string)$payInfo['currency']); ?></span>
            <span class="badge badge-info" style="padding:6px 12px">المخصَّص
                <?php echo $payInfo['allocated']; ?></span>
            <span class="badge <?php echo $payInfo['unallocated'] > 0.004
                ? 'badge-warning' : 'badge-success'; ?>" style="padding:6px 12px">
                غيرُ المخصَّص <?php echo $payInfo['unallocated']; ?></span>
        </div>
        <?php if ($payAllocs): ?>
        <div class="table-container" style="margin-bottom:10px">
            <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th>الهدف</th><th>المرجع</th><th>المبلغ</th><th>الأساس</th><th>ملاحظة</th></tr></thead>
                <tbody>
                <?php foreach ($payAllocs as $a): ?>
                    <tr><td><?php echo htmlspecialchars($TARGET_AR[(string)$a['target_kind']]
                            ?? (string)$a['target_kind']); ?></td>
                        <td>#<?php echo intval($a['target_ref']); ?>
                            <?php echo $a['doc_ref'] !== null
                                ? (' — ' . htmlspecialchars((string)$a['doc_ref'])) : ''; ?></td>
                        <td><?php echo htmlspecialchars((string)$a['amount']); ?></td>
                        <td><?php echo (string)$a['basis'] === 'explicit'
                            ? 'مرجعٌ صريح' : 'أقدمُ فاتورةٍ أولًا'; ?></td>
                        <td style="white-space:normal"><?php
                            echo htmlspecialchars((string)($a['note'] ?? '—')); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($can_add && $payInfo['unallocated'] > 0.004): ?>
        <form method="post" class="ems-form">
            <input type="hidden" name="col_action" value="allocate">
            <input type="hidden" name="payment_id" value="<?php echo $PAY; ?>">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:6px">
                <div class="form-group"><label>الهدف <?php echo $i + 1; ?></label>
                    <select name="t_kind[<?php echo $i; ?>]">
                        <?php foreach ($TARGET_AR as $k => $v): ?>
                            <option value="<?php echo $k; ?>"
                                <?php echo $k === 'invoice' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>مرجعُه</label>
                    <input type="number" name="t_ref[<?php echo $i; ?>]" style="width:120px"
                           placeholder="رقمُ الذمّة أو سطرِ الخطة"></div>
                <div class="form-group"><label>المبلغ</label>
                    <input type="number" step="0.01" name="t_amount[<?php echo $i; ?>]" style="width:120px"></div>
                <div class="form-group" style="min-width:200px"><label>ملاحظة</label>
                    <input type="text" name="t_note[<?php echo $i; ?>]" maxlength="255"></div>
            </div>
            <?php endfor; ?>
            <button type="submit" class="btn-save"><i class="fa fa-scale-balanced"></i>
                خصّص — <strong>وΣ لا تتجاوز السند</strong></button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
