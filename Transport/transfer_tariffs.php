<?php
/**
 * Transport/transfer_tariffs.php — تعرفةُ الترحيل وتسعيرُ الأوامر (M-52)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-02 §3-④: «**أمرُ الترحيل المسلَّم · بتعرفته**» — والشاشةُ تكتب **التعرفة**
 * وتُسعّر بها، ولا تقبل مبلغًا مكتوبًا بيد: «المبلغُ يُقرأ من مصدره لا يُكتب».
 * ولوحُ «المسلَّمُ غير المسعَّر» يجعل الفجوةَ **ظاهرةً لا مضمَرة**.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Transport/TransferTariffService.php';

use App\Services\Transport\TransferTariffService as TTS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$MODULE_CODE = 'Transport/transfer_tariffs.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+تعرفة+الترحيل+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('transfer tariffs super') : ems_tenant_db();
$redirect = function ($msg) { header("Location: transfer_tariffs.php?msg=" . rawurlencode($msg)); exit(); };

$MODELS = TTS::MODEL_LABEL_AR;

// ── إضافةُ تعرفة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['tar_action'] ?? '') === 'add') {
    if (!$can_add) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $model = strval($_POST['pricing_model'] ?? '');
    $rate  = trim(strval($_POST['rate'] ?? ''));
    $from  = strval($_POST['effective_from'] ?? '');
    if (!isset($MODELS[$model]))                                     { $redirect('نموذجُ تسعيرٍ غير معروف ❌'); }
    if ($rate === '' || !is_numeric($rate) || (float) $rate <= 0)     { $redirect('المعدّلُ رقمٌ موجبٌ إلزامي ❌'); }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))                  { $redirect('تاريخُ السريان إلزامي ❌'); }

    $opt = function ($k) { $v = intval($_POST[$k] ?? 0); return $v > 0 ? $v : null; };
    $num = function ($k) {
        $v = trim(strval($_POST[$k] ?? ''));
        return ($v !== '' && is_numeric($v)) ? round((float) $v, 2) : null;
    };
    $to = strval($_POST['effective_to'] ?? '');
    try {
        $gate->insert('transfer_tariffs', array(
            'supplier_id'      => $opt('supplier_id'),
            'transfer_type_id' => $opt('transfer_type_id'),
            'from_location_id' => $opt('from_location_id'),
            'to_location_id'   => $opt('to_location_id'),
            'pricing_model'    => $model,
            'rate'             => round((float) $rate, 4),
            'currency'         => mb_substr(trim(strval($_POST['currency'] ?? 'SDG')), 0, 8) ?: 'SDG',
            'min_amount'       => $num('min_amount'),
            'max_amount'       => $num('max_amount'),
            'effective_from'   => $from,
            'effective_to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null,
            'state'            => 'active',
            'note'             => mb_substr(trim(strval($_POST['note'] ?? '')), 0, 200) ?: null,
            'created_by'       => $uid,
        ));
        $redirect('أُضيفت التعرفة ✅');
    } catch (\Throwable $t) {
        $redirect('تعذّرت الإضافة: ' . $t->getMessage() . ' ❌');
    }
}

// ── إنهاءُ تعرفة (لا حذف — «جمِّد للقراءة») ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['tar_action'] ?? '') === 'end') {
    if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    try {
        $gate->update('transfer_tariffs', array('state' => 'ended'),
                      array('id' => intval($_POST['tariff_id'] ?? 0)));
        $redirect('أُنهيت التعرفةُ — وتبقى في السجل حاكمةً لما سُعّر بها ✅');
    } catch (\Throwable $t) { $redirect('تعذّر الإنهاء ❌'); }
}

// ── تسعيرُ أمرٍ مسلَّم ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['tar_action'] ?? '') === 'price') {
    if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $r = TTS::priceOrder($conn, $gate, $company_id, intval($_POST['order_id'] ?? 0), $uid,
                         strval($_POST['reprice_reason'] ?? ''));
    $redirect($r['ok']
        ? ('سُعّر الأمرُ بـ' . $r['amount'] . ' — ' . $r['note'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

// ── كتابةُ المسافة (لازمةٌ لنموذج الكيلومتر) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['tar_action'] ?? '') === 'distance') {
    if (!$can_edit) { $redirect('لا توجد صلاحية لهذا الإجراء ❌'); }
    $km = trim(strval($_POST['distance_km'] ?? ''));
    if ($km === '' || !is_numeric($km) || (float) $km <= 0) { $redirect('المسافةُ رقمٌ موجب ❌'); }
    try {
        $gate->update('transfer_orders', array('distance_km' => round((float) $km, 2)),
                      array('id' => intval($_POST['order_id'] ?? 0)));
        $redirect('سُجّلت المسافة ✅');
    } catch (\Throwable $t) { $redirect('تعذّر الحفظ ❌'); }
}

$tariffs = TTS::tariffs($gate);
$orders  = TTS::deliveredOrders($gate);

$suppliers = array(); $types = array(); $locs = array();
try {
    $suppliers = $gate->scopedQuery(array('scope' => array('s' => 'suppliers')),
        "SELECT s.id, s.name FROM suppliers s WHERE {TENANT_SCOPE}
           AND COALESCE(s.is_deleted,0)=0 ORDER BY s.name");
} catch (\Throwable $t) { $suppliers = array(); }
try {
    $types = $gate->scopedQuery(array('scope' => array('t' => 'transfer_types')),
        "SELECT t.id, t.name FROM transfer_types t WHERE {TENANT_SCOPE}
           AND COALESCE(t.is_deleted,0)=0 ORDER BY t.name");
} catch (\Throwable $t) { $types = array(); }
try {
    $locs = $gate->scopedQuery(array('scope' => array('l' => 'trs_locations')),
        "SELECT l.id, l.name FROM trs_locations l WHERE {TENANT_SCOPE}
           AND COALESCE(l.is_deleted,0)=0 ORDER BY l.name");
} catch (\Throwable $t) { $locs = array(); }

$unpriced = 0;
foreach ($orders as $o) { if ($o['tariff_amount'] === null && $o['charge_supplier_id'] !== null) { $unpriced++; } }

$page_title = 'إيكوبيشن | تعرفة الترحيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تعرفة الترحيل وتسعير الأوامر'; $header_icon = 'fa fa-money-bill-transfer';
    $header_actions = array();
    $header_back = array('href' => 'transfer_orders_list.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'أوامر الترحيل');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0 0 10px;">
            <i class="fas fa-circle-info"></i>
            <strong>أمرُ الترحيل المسلَّم بتعرفته</strong> هو مصدرُ تحميل النقل على المورد (ENT-02 §3-④).
            و<strong>لا تحميلَ بلا تعرفةٍ مكتوبة</strong>: بلا تعرفةٍ منطبقةٍ يُرفض التسعيرُ بسببه —
            و<strong>تكلفتُنا الداخلية</strong> في «بنود التكلفة» شيءٌ آخر لا يُحمَّل على المورد.
            و<strong>الأخصُّ يغلب</strong>: موردٌ بعينه ← مسارٌ ← نوعٌ ← الأعمّ.
        </p>
        <?php if ($unpriced > 0): ?>
            <span class="badge badge-warning" style="padding:6px 12px;">
                <i class="fas fa-triangle-exclamation"></i>
                <?php echo $unpriced; ?> أمرًا مسلَّمًا على مورد <strong>بلا تسعير</strong> — لا يدخل أيٌّ منها تسويةً
            </span>
        <?php endif; ?>
    </div></div>

    <?php if ($can_add): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> تعرفةٌ جديدة</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
            <input type="hidden" name="tar_action" value="add">
            <div class="form-grid">
                <div class="form-group"><label>المورد <small>— فارغٌ = الأعمّ</small></label>
                    <select name="supplier_id"><option value="0">— أي مورد —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo intval($s['id']); ?>"><?php echo htmlspecialchars((string)$s['name']); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>نوعُ الترحيل</label>
                    <select name="transfer_type_id"><option value="0">— أي نوع —</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo intval($t['id']); ?>"><?php echo htmlspecialchars((string)$t['name']); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>من موقع</label>
                    <select name="from_location_id"><option value="0">— أي مبدأ —</option>
                        <?php foreach ($locs as $l): ?>
                            <option value="<?php echo intval($l['id']); ?>"><?php echo htmlspecialchars((string)$l['name']); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>إلى موقع</label>
                    <select name="to_location_id"><option value="0">— أي منتهى —</option>
                        <?php foreach ($locs as $l): ?>
                            <option value="<?php echo intval($l['id']); ?>"><?php echo htmlspecialchars((string)$l['name']); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>نموذجُ التسعير <span style="color:#c00">*</span></label>
                    <select name="pricing_model" required>
                        <?php foreach ($MODELS as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                        <?php endforeach; ?></select></div>
                <div class="form-group"><label>المعدّل <span style="color:#c00">*</span></label>
                    <input type="number" name="rate" step="0.0001" min="0.0001" required></div>
                <div class="form-group"><label>العملة</label>
                    <input type="text" name="currency" value="SDG" maxlength="8"></div>
                <div class="form-group"><label>حدٌّ أدنى</label>
                    <input type="number" name="min_amount" step="0.01" min="0"></div>
                <div class="form-group"><label>حدٌّ أقصى</label>
                    <input type="number" name="max_amount" step="0.01" min="0"></div>
                <div class="form-group"><label>سريان من <span style="color:#c00">*</span></label>
                    <input type="date" name="effective_from" required></div>
                <div class="form-group"><label>سريان إلى</label>
                    <input type="date" name="effective_to"></div>
                <div class="form-group"><label>مرجعُ التعرفة</label>
                    <input type="text" name="note" maxlength="200" placeholder="بندُ العقد أو مرجعُ الاعتماد"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-save"><i class="fa fa-save"></i> أضف التعرفة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-table-list"></i> التعرفات</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>#</th><th>المورد</th><th>النوع</th><th>المسار</th><th>النموذج</th>
                <th>المعدّل</th><th>الحدود</th><th>السريان</th><th>الحال</th>
                <?php if ($can_edit) echo '<th>إجراء</th>'; ?>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($tariffs as $t):
                $route = (($t['from_name'] ?? null) !== null ? htmlspecialchars((string)$t['from_name']) : 'أي')
                       . ' ← ' . (($t['to_name'] ?? null) !== null ? htmlspecialchars((string)$t['to_name']) : 'أي');
                $lim = ($t['min_amount'] !== null ? '≥' . $t['min_amount'] : '')
                     . (($t['min_amount'] !== null && $t['max_amount'] !== null) ? ' · ' : '')
                     . ($t['max_amount'] !== null ? '≤' . $t['max_amount'] : '');
            ?>
                <tr<?php echo (string)$t['state'] === 'ended' ? " style='opacity:.55'" : ''; ?>>
                    <td><?php echo intval($t['id']); ?></td>
                    <td><?php echo $t['supplier_id'] !== null
                        ? htmlspecialchars((string)($t['supplier_name'] ?? ('#' . intval($t['supplier_id']))))
                        : '<em>الأعمّ</em>'; ?></td>
                    <td><?php echo $t['transfer_type_id'] !== null
                        ? htmlspecialchars((string)($t['type_name'] ?? '#' . intval($t['transfer_type_id']))) : '—'; ?></td>
                    <td><?php echo $route; ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($MODELS[(string)$t['pricing_model']] ?? (string)$t['pricing_model']); ?></span></td>
                    <td><strong><?php echo rtrim(rtrim(number_format((float)$t['rate'], 4, '.', ''), '0'), '.'); ?></strong>
                        <?php echo htmlspecialchars((string)$t['currency']); ?></td>
                    <td><?php echo $lim !== '' ? htmlspecialchars($lim) : '—'; ?></td>
                    <td style="direction:ltr"><?php echo htmlspecialchars((string)$t['effective_from']); ?>
                        → <?php echo htmlspecialchars((string)($t['effective_to'] ?? '…')); ?></td>
                    <td><?php echo (string)$t['state'] === 'active'
                        ? '<span class="badge badge-success">سارية</span>'
                        : '<span class="badge badge-secondary">منتهية</span>'; ?></td>
                    <?php if ($can_edit): ?>
                    <td><?php if ((string)$t['state'] === 'active'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="tar_action" value="end">
                            <input type="hidden" name="tariff_id" value="<?php echo intval($t['id']); ?>">
                            <button type="submit" class="badge badge-danger" style="border:0;padding:5px 10px"
                                onclick="return confirm('إنهاءُ التعرفة؟ تبقى في السجل حاكمةً لما سُعّر بها.');">
                                <i class="fa fa-stop"></i> أنهِ</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-truck-fast"></i>
        الأوامرُ المسلَّمة — وتسعيرُها بالتعرفة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الأمر</th><th>المرحلة</th><th>المورد المحمَّل</th><th>التاريخ</th>
                <th>المسافة</th><th>التسعير</th><?php if ($can_edit) echo '<th>إجراء</th>'; ?></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$o['order_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$o['stage']); ?></td>
                    <td><?php echo $o['charge_supplier_id'] !== null
                        ? htmlspecialchars((string)($o['supplier_name'] ?? ('#' . intval($o['charge_supplier_id']))))
                        : '<span class="badge badge-secondary">لا تحميلَ على مورد</span>'; ?></td>
                    <td><?php echo htmlspecialchars((string)$o['d_date']); ?></td>
                    <td><?php if ($o['distance_km'] !== null): ?>
                            <?php echo htmlspecialchars((string)$o['distance_km']); ?> كم
                        <?php elseif ($can_edit): ?>
                            <form method="post" style="display:flex;gap:5px">
                                <input type="hidden" name="tar_action" value="distance">
                                <input type="hidden" name="order_id" value="<?php echo intval($o['id']); ?>">
                                <input type="number" name="distance_km" step="0.01" min="0.01"
                                       style="width:80px" placeholder="كم">
                                <button type="submit" class="badge badge-secondary" style="border:0;padding:5px 8px">حفظ</button>
                            </form>
                        <?php else: ?>—<?php endif; ?></td>
                    <td style="white-space:normal"><?php echo $o['tariff_amount'] !== null
                        ? ('<strong>' . htmlspecialchars((string)$o['tariff_amount']) . ' '
                           . htmlspecialchars((string)$o['tariff_currency']) . '</strong><div><small>'
                           . htmlspecialchars((string)$o['tariff_note']) . '</small></div>')
                        : '<span class="badge badge-warning">بلا تسعير</span>'; ?></td>
                    <?php if ($can_edit): ?>
                    <td><?php if ($o['charge_supplier_id'] === null): ?>—
                        <?php elseif ($o['tariff_amount'] === null): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="tar_action" value="price">
                            <input type="hidden" name="order_id" value="<?php echo intval($o['id']); ?>">
                            <button type="submit" class="badge badge-success" style="border:0;padding:5px 10px">
                                <i class="fa fa-calculator"></i> سعّر بالتعرفة</button>
                        </form>
                        <?php else: ?>
                        <form method="post" style="display:flex;gap:5px;align-items:center">
                            <input type="hidden" name="tar_action" value="price">
                            <input type="hidden" name="order_id" value="<?php echo intval($o['id']); ?>">
                            <input type="text" name="reprice_reason" maxlength="90" required
                                   placeholder="حجّةُ إعادة التسعير" style="width:140px">
                            <button type="submit" class="badge badge-warning" style="border:0;padding:5px 10px">
                                أعِد التسعير</button>
                        </form>
                        <?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
