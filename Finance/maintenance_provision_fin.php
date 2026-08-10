<?php
/**
 * Finance/maintenance_provision_fin.php — ضبط معدّل مخصّص الصيانة (مروحة الأثر §6.1/§12.6).
 * ───────────────────────────────────────────────────────────────────────────
 * حقلٌ يملؤه المدير المالي يدويًّا: معدّل مخصّص الصيانة لكل ساعة تشغيلٍ فعلية.
 * يُكتب في fin_effect_map.param_value لأثر `metric_update` (مصدرَي الدوام والوحدة)،
 * ويُفعَّل الأثر تلقائيًّا حين يكون المعدّل موجبًا — وإلا يبقى معطّلًا (الحقل فارغ =
 * لا مخصّص = لا تلفيق). المحرّك EffectFanout يحسب: مخصّص = ساعات التشغيل × المعدّل،
 * فيُقيَّد تكلفةَ صيانةٍ على المعدة والمشروع ضمن مروحة يوم الدوام.
 * شاشة مستقلة — عزل شركة عبر البوابة + صلاحية §15 (المدير المالي يضبط السياسة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/maintenance_provision_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض مخصّص الصيانة ❌', 'GOV-PERM-403', '');
    exit();
}

// ── حفظ المعدّل (يُكتب على صفَّي metric_update: الدوام والوحدة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate'])) {
    if (!$can_edit) { ems_gov_flash_redirect('maintenance_provision_fin.php', 'لا توجد صلاحية الضبط ❌', 'GOV-PERM-403', ''); exit(); }
    $raw = trim($_POST['rate']);
    // الحقل الفارغ = تعطيل (لا مخصّص). قيمةٌ موجبة = تفعيل بالمعدّل.
    if ($raw === '') {
        $rate = 0.0;
    } elseif (!is_numeric($raw) || (float)$raw < 0) {
        ems_gov_flash_redirect('maintenance_provision_fin.php', 'المعدّل يجب أن يكون رقماً غير سالب ❌', 'GOV-FAIL-409', ''); exit();
    } else {
        $rate = round((float)$raw, 4);
    }
    $affected = fin_gate($is_super_admin)->update('fin_effect_map', array(
        'param_value' => $rate,
        'is_active'   => $rate > 0 ? 1 : 0,
    ), array('effect_type' => 'metric_update'));

    $msg = $rate > 0
        ? 'تم+ضبط+معدّل+مخصّص+الصيانة+وتفعيله+(' . rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') . '/ساعة)+✅'
        : 'تم+إفراغ+المعدّل+—+مخصّص+الصيانة+معطّل+الآن+✅';
    ems_gov_flash_redirect('maintenance_provision_fin.php', "$msg", 'GOV-INFO-200', ''); exit();
}

// المعدّل الحالي (من أول صف metric_update للشركة)
$cur = fin_gate($is_super_admin)->selectOne('fin_effect_map', array(
    'columns' => array('param_value', 'is_active'),
    'where'   => array('effect_type' => 'metric_update'),
));
$cur_rate   = $cur ? (float)$cur['param_value'] : 0.0;
$cur_active = $cur ? intval($cur['is_active']) === 1 : false;
$cur_rate_display = $cur_rate > 0 ? rtrim(rtrim(number_format($cur_rate, 4, '.', ''), '0'), '.') : '';

// كل آثار المروحة (سياقٌ للعرض — قراءة فقط)
$effects = fin_gate($is_super_admin)->select('fin_effect_map', array('orderBy' => 'source_kind ASC, display_order ASC'));

$page_title = 'إيكوبيشن | قواعد مخصص الصيانة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main fin-mprov-main ems-unified-page-shell">
    <?php
    $header_title = 'قواعد مخصص الصيانة';
    $header_icon  = 'fa fa-screwdriver-wrench';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <span class="badge badge-<?php echo $cur_active ? 'success' : 'secondary'; ?>" style="font-size:13px;padding:6px 12px;">
                <i class="fas fa-<?php echo $cur_active ? 'circle-check' : 'circle-pause'; ?>"></i>
                <?php echo $cur_active ? 'مفعّل' : 'غير مفعّل (الحقل فارغ)'; ?>
            </span>
            <?php if ($cur_active): ?>
                <span style="color:#374151;">المعدّل الحالي: <strong><?php echo htmlspecialchars($cur_rate_display); ?></strong> لكل ساعة تشغيل</span>
            <?php endif; ?>
        </div>
        <p style="color:#4b5563;margin:0 0 14px;line-height:1.7;">
            <i class="fas fa-circle-info"></i>
            هذا المعدّل يُحمِّل على كل معدةٍ <strong>مخصّص صيانة</strong> ضمن مروحة يوم الدوام، محسوبًا على
            <strong>ساعات التشغيل الفعلية</strong>: <code>المخصّص = ساعات التشغيل × المعدّل</code> (بعملة الأساس SDG).
            اترك الحقل فارغًا ليبقى المخصّص معطّلًا — لا يُحتسب رقمٌ حتى تضبط المعدّل بنفسك.
        </p>

        <?php if ($can_edit): ?>
        <form action="" method="post" class="allforms allforms-visible" style="box-shadow:none;padding:0;">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emsf_238_562ad">معدّل مخصّص الصيانة لكل ساعة تشغيل (SDG)</label>
                        <input type="number" name="rate" step="0.0001" min="0" value="<?php echo htmlspecialchars($cur_rate_display); ?>" placeholder="فارغ = معطّل — اكتب المعدّل لتفعيله" id="emsf_238_562ad">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ المعدّل</button>
                <?php if ($cur_active): ?>
                <button type="submit" name="rate" value="" class="btn-cancel" onclick="return confirm('إفراغ المعدّل يوقف احتساب مخصّص الصيانة. متابعة؟')"><i class="fas fa-eraser"></i> إفراغ (تعطيل)</button>
                <?php endif; ?>
            </div>
        </form>
        <?php else: ?>
            <p style="color:#9ca3af;"><i class="fas fa-lock"></i> العرض فقط — ضبط المعدّل من صلاحية المدير المالي.</p>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px;"><i class="fas fa-diagram-project"></i> الآثار المالية المولَّدة وحالتها (للاطّلاع)</h5>
        <div class="table-container">
            <table id="effTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>المصدر</th><th>درجة الأثر</th><th>الحالة</th><th>المعدّل</th><th>السبب/الملاحظة</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم المخصص</th>
                    <th class="ems-fn-th" data-fn="1">الفترة</th>
                    <th class="ems-fn-th" data-fn="1">المعدة أو الفئة</th>
                    <th class="ems-fn-th" data-fn="1">نوع المخصص</th>
                    <th class="ems-fn-th" data-fn="1">أساس التكوين</th>
                    <th class="ems-fn-th" data-fn="1">معادلة الاحتساب</th>
                    <th class="ems-fn-th" data-fn="1">المكوَّن للفترة</th>
                    <th class="ems-fn-th" data-fn="1">الرصيد المتراكم</th>
                    <th class="ems-fn-th" data-fn="1">المستخدَم للفترة</th>
                    <th class="ems-fn-th" data-fn="1">الرصيد المتاح</th>
                    <th class="ems-fn-th" data-fn="1">رقم القيد</th>
                    <th class="ems-fn-th" data-fn="1">مبرر التكوين</th>
                    <th class="ems-fn-th" data-fn="1">اعتمده</th>
                    <th class="ems-fn-th" data-fn="1">المرجع المحاسبي</th>
                    <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $src_ar = array('timesheet' => 'يوم الدوام', 'unit_record' => 'كشف الوحدة');
                    foreach ($effects as $e) {
                        $act = intval($e['is_active']) === 1;
                        $pv  = ($e['param_value'] !== null && (float)$e['param_value'] > 0)
                                ? rtrim(rtrim(number_format((float)$e['param_value'], 4, '.', ''), '0'), '.') : '—';
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($src_ar[$e['source_kind']] ?? $e['source_kind']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$e['effect_label']) . "</td>";
                        echo "<td><span class='badge badge-" . ($act ? 'success' : 'secondary') . "'>" . ($act ? 'مفعّل' : 'معطّل') . "</span></td>";
                        echo "<td>" . $pv . "</td>";
                        echo "<td style='color:#6b7280;font-size:12px;'>" . htmlspecialchars((string)($e['unavailable_reason'] ?? '')) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#effTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, paging: false, searching: false, info: false, order: [],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
});
</script>
</body>
</html>
