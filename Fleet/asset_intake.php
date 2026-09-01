<?php
/**
 * Fleet/asset_intake.php — طلبُ إدخالِ الأصل ودورتُه (RPR-W05 · FLEET-03/04/05/11)
 * ───────────────────────────────────────────────────────────────────────────
 * **هنا تبدأ دورةُ الأصل — قبلَ الكرتِ لا بعده** (‏`FLEET-03`).
 * والسطحُ يحمل ثلاثَ حبّاتٍ متمايزةٍ لا حبّةً واحدةً بحقولٍ إضافية:
 *   ① طلبُ الإدخال (`asset_intake`) — صفٌّ لكلِّ طلب.
 *   ② واقعةُ التحقُّقِ من المصدر (`asset_source_check`) — Child بعلاقةِ ١:ن،
 *     و**لا يُنشأ كرتُ أصلٍ قبل اجتيازِها** (‏`FLEET-04` نصًّا).
 *   ③ أمرُ التفتيش (`asset_inspection_order`) — التفتيشُ يبدأ بأمرٍ لا بزيارة،
 *     وله خمسةُ أسبابٍ معياريّةٍ لا نصٌّ حرّ (‏`FLEET-05`).
 *
 * ◆ **والكتابةُ كلُّها عبرَ `AssetLifecycleService`** لا عبرَ نموذجٍ يكتب مباشرةً:
 *   آلةُ الحالةِ في الخدمةِ وحدَها، فلا تتفرّق حالةٌ بين شاشةٍ وأداة.
 * ◆ **والوصولُ إلى القاعدةِ عبرَ بوابةِ المستأجِر** — لا استعلامَ خامّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/guide_label.php';
require_once __DIR__ . '/../app/Services/Fleet/AssetLifecycleService.php';

use App\Services\Fleet\AssetLifecycleService as ALS;

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Fleet/asset_intake.php');
$can_view = $pp['can_view']; $can_add = $pp['can_add']; $can_edit = $pp['can_edit'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset intake super') : ems_tenant_db();
ALS::setEventConnection($conn);
$uid = intval($_SESSION['user']['id'] ?? 0);

$REASONS = array('intake' => 'تفتيش دخول', 'periodic' => 'تفتيش دوري', 'post_repair' => 'ما بعد الإصلاح',
                 'pre_exit' => 'ما قبل الخروج', 'incident' => 'واقعة طارئة');
$SOURCES = array('owned' => 'مملوكة', 'financed' => 'ممولة', 'supplier_external' => 'مورّدة', 'rented' => 'مستأجرة');
$STATES  = array('draft' => 'مسودة', 'submitted' => 'مرفوع', 'source_verified' => 'مصدر محقق',
                 'inspection_ordered' => 'أمر تفتيش صادر', 'inspected' => 'مفتش',
                 'card_issued' => 'كرت صادر', 'activated' => 'مفعل', 'rejected' => 'مرفوض');

$act = isset($_POST['action']) ? (string) $_POST['action'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act !== '' && ($can_add || $can_edit)) {
    $msg = '⚠ لم ينفذ'; $code = 'GOV-INFO-200';
    if ($act === 'open_intake' && $can_add) {
        $r = ALS::openIntake($gate, array(
            'intake_no'      => trim($_POST['intake_no'] ?? ''),
            'requested_dept' => trim($_POST['requested_dept'] ?? ''),
            'asset_kind'     => trim($_POST['asset_kind'] ?? ''),
            'source_type'    => trim($_POST['source_type'] ?? 'owned'),
            'requested_by'   => $uid,
            'source_ref'     => 'Fleet/asset_intake.php',
        ));
        $msg = $r['ok'] ? '✅ سجل طلب الإدخال' : '❌ ' . $r['reason'];
    } elseif ($act === 'verify' && $can_edit) {
        $r = ALS::verifySource($gate, intval($_POST['intake_id'] ?? 0), array(
            'doc_type'       => trim($_POST['doc_type'] ?? ''),
            'doc_ref'        => trim($_POST['doc_ref'] ?? ''),
            'owner_declared' => trim($_POST['owner_declared'] ?? ''),
            'owner_legal'    => trim($_POST['owner_legal'] ?? ''),
            'verify_result'  => trim($_POST['verify_result'] ?? 'passed'),
            'fail_reason'    => trim($_POST['fail_reason'] ?? ''),
            'verified_by'    => $uid,
        ));
        $msg = $r['ok'] ? '✅ سجلت واقعة التحقق' : '❌ ' . $r['reason'];
    } elseif ($act === 'order_inspection' && $can_edit) {
        $r = ALS::orderInspection($gate, array(
            'order_no'  => trim($_POST['order_no'] ?? ''),
            'intake_id' => intval($_POST['intake_id'] ?? 0),
            'reason'    => trim($_POST['reason'] ?? 'intake'),
            'due_date'  => trim($_POST['due_date'] ?? ''),
            'ordered_by' => $uid,
        ));
        $msg = $r['ok'] ? '✅ صدر أمر التفتيش' : '❌ ' . $r['reason'];
    } elseif ($act === 'issue_card' && $can_edit) {
        $r = ALS::issueCard($gate, intval($_POST['intake_id'] ?? 0), intval($_POST['equipment_id'] ?? 0), $uid);
        $msg = $r['ok'] ? '✅ صدر كرت الأصل' : '❌ ' . $r['reason'];
    } elseif ($act === 'activate' && $can_edit) {
        $r = ALS::activateAsset($gate, intval($_POST['intake_id'] ?? 0), $uid);
        $msg = $r['ok'] ? '✅ فعل الأصل' : '❌ ' . $r['reason'];
    }
    ems_gov_flash_redirect('asset_intake.php', $msg, $code, ''); exit();
}

$rows = array(); $checks = array(); $orders = array(); $equip = array();
try { $rows = $gate->select('asset_intake', array('orderBy' => 'id DESC', 'limit' => 300)); }
catch (\Throwable $t) { error_log('asset_intake.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('asset_source_check', array('orderBy' => 'id DESC', 'limit' => 600)) as $c) { $checks[(int) $c['intake_id']][] = $c; } }
catch (\Throwable $t) { error_log('asset_intake.php checks: ' . $t->getMessage()); }
try { foreach ($gate->select('asset_inspection_order', array('orderBy' => 'id DESC', 'limit' => 600)) as $o) { if ((int) $o['intake_id'] > 0) { $orders[(int) $o['intake_id']][] = $o; } } }
catch (\Throwable $t) { error_log('asset_intake.php orders: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id DESC', 'limit' => 400)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' — ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_intake.php equip: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | طلب إدخال الأصل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'طلب إدخال الأصل — دورة الدخول'; $header_icon = 'fa fa-truck-ramp-box'; $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب إدخال'); }
    $header_back = array('href' => '../Equipments/equipments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'كرت الأصل');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $ok ? 'is-success' : 'is-error' ?>"><i class="fas <?= $ok ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا طلبات إدخال مسجلة بعد', 'ابدأ دورة الأصل بزر «طلب إدخال» في رأس الشاشة'); ?>

    <form id="iForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="open_intake">
        <div class="card-header"><h5><i class="fas fa-plus"></i> طلب إدخال أصل</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_in_no">رقم الطلب</label><input type="text" name="intake_no" id="w5_in_no" required></div>
            <div class="field"><label for="w5_in_dept">الإدارة الطالبة</label><input type="text" name="requested_dept" id="w5_in_dept" value="DEP-04" required></div>
            <div class="field"><label for="w5_in_kind">نوع الأصل</label><input type="text" name="asset_kind" id="w5_in_kind"></div>
            <div class="field"><label for="w5_in_src">مصدر الأصل</label><select name="source_type" id="w5_in_src">
                <?php foreach ($SOURCES as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button></div>
    </form>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_asset_intake')); ?>
    <?php
    /* ◆ **أعمدةُ الورقةِ حرفًا** (GOV_EXEC §12): السطحُ يخدم أربعةَ أهدافٍ بأربعِ
         حبّاتٍ — الطلبُ (`FLEET-03`) والتحقّقُ (`FLEET-04`) وأمرُ التفتيشِ
         (`FLEET-05`) والتفعيلُ (`FLEET-11`). وهذه رؤوسُ الطلبِ والتفعيلِ معًا،
         فحبّتُهما واحدةٌ: سطرُ الإدخالِ يُفعَّل. */
    $GUIDE_COLS = array(
        'رقم الطلب' => 'intake_no',
        'تاريخ الطلب' => 'request_date',
        'الإدارة الطالبة' => 'requested_dept',
        'طالب الإدخال' => 'requester_name',
        'مصدر الاحتياج' => 'need_source',
        'مرجع عقد العميل' => 'client_contract_ref',
        'رقم المشروع' => 'project_no',
        'كود مصدر القدرة' => 'power_source_code',
        'رمز التصنيف المطلوب' => 'requested_class',
        'المواصفة المطلوبة' => 'requested_spec',
        'العدد المطلوب' => 'requested_count',
        'تاريخ الحاجة' => 'need_date',
        'المبرر التشغيلي' => 'operational_justification',
        'الأثر إن لم يلب' => 'impact_if_unmet',
        'حالة الطلب' => '__state',
        'سبب الرفض' => 'reject_reason',
        'كود الأصل الناتج' => '__asset',
        'رقم التفعيل' => 'activation_code',
        'نوع الواقعة' => 'activation_kind',
        'مرجع الفحص' => 'inspection_ref',
        'مرجع أمر الصيانة' => 'work_order_ref',
        'تاريخ التفعيل' => 'activation_date',
        'قراءة العداد' => 'activation_meter',
        'الموقع' => 'activation_site',
        'المشروع' => 'activation_project',
        'الحالة قبل' => 'state_before',
        'الحالة بعد' => 'state_after',
        'دليل الجاهزية' => 'readiness_evidence',
        'أيام التوقف قبل التفعيل' => 'down_days_before',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'source_ref',
        'حالة البيانات' => 'data_state',
        'قاعدة الحالة' => 'state_rule',
        'المصدر' => '__source',
        'وقائع التحقق' => '__checks',
        'أوامر التفتيش' => '__orders',
    );
    ?>
    <table id="emsList_asset_intake" class="data-table">
        <thead><tr><th>إجراءات</th>
            <?php foreach ($GUIDE_COLS as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead><tbody>
        <?php if ($rows): foreach ($rows as $r): $iid = (int) $r['id'];
            $ck = isset($checks[$iid]) ? $checks[$iid] : array();
            $od = isset($orders[$iid]) ? $orders[$iid] : array();
            $passed = 0; foreach ($ck as $c) { if ($c['verify_result'] === 'passed') { $passed++; } } ?>
            <tr>
                <td><div class="action-btns">
                    <?php if ($can_edit && in_array($r['state'], array('submitted', 'source_verified'), true)): ?>
                        <a href="javascript:void(0)" class="action-btn edit w5-verify" data-id="<?= $iid ?>" title="تحقق من المصدر"><i class="fas fa-file-shield"></i></a>
                    <?php endif; ?>
                    <?php if ($can_edit && $passed > 0 && $r['state'] !== 'activated' && $r['state'] !== 'rejected'): ?>
                        <a href="javascript:void(0)" class="action-btn edit w5-order" data-id="<?= $iid ?>" title="أمر تفتيش"><i class="fas fa-clipboard-check"></i></a>
                        <a href="javascript:void(0)" class="action-btn edit w5-card" data-id="<?= $iid ?>" title="إصدار كرت الأصل"><i class="fas fa-id-card"></i></a>
                    <?php endif; ?>
                    <?php if ($can_edit && $r['state'] === 'card_issued'): ?>
                        <form action="" method="post" class="ems-inline-form"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="activate"><input type="hidden" name="intake_id" value="<?= $iid ?>">
                            <button type="submit" class="action-btn edit" title="تفعيل"><i class="fas fa-play"></i></button>
                        </form>
                    <?php endif; ?>
                </div></td>
                <?php foreach ($GUIDE_COLS as $__lbl => $__k):
                    if ($__k === '__state')       { $__v = isset($STATES[$r['state']]) ? $STATES[$r['state']] : (string) $r['state']; }
                    elseif ($__k === '__source')  { $__v = isset($SOURCES[$r['source_type']]) ? $SOURCES[$r['source_type']] : (string) $r['source_type']; }
                    elseif ($__k === '__checks')  { $__v = count($ck) . ' (مجتازة ' . $passed . ')'; }
                    elseif ($__k === '__orders')  { $__v = (string) count($od); }
                    elseif ($__k === '__asset')   { $__v = ((int) $r['equipment_id'] > 0 && isset($equip[(int) $r['equipment_id']])) ? $equip[(int) $r['equipment_id']] : ''; }
                    else                          { $__v = isset($r[$__k]) ? (string) $r[$__k] : ''; }
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?php
                    echo ($__k === 'intake_no')
                        ? '<strong>' . htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') . '</strong>'
                        : htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS) + 1 ?>">لا طلبات إدخال بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <?php
    /* ◆ الهدفان الآخران لهذا السطح — حبّتاهما مستقلّتان عن الطلب:
         `FLEET-04` واقعةُ التحقّقِ من المصدرِ · `FLEET-05` أمرُ التفتيش.
       ⛔ **ولا تُدمَجان في جدولِ الطلب**: للطلبِ الواحدِ وقائعُ تحقّقٍ عدّةٌ
         وأوامرُ تفتيشٍ عدّة — والدمجُ يخلط الأبَ بالابن (§12 «1:N ⇒ Child»). */
    $GUIDE_COLS_CHECK = array(
        'رقم التحقق' => 'check_code',
        'رقم الطلب' => '__intake',
        'تاريخ التحقق' => 'verified_at',
        'المتحقق' => 'verified_by',
        'كود مصدر القدرة' => 'power_source_code',
        'الطرف المورد' => 'supplying_party',
        'طبيعة الطرف' => 'party_nature',
        'أثبتت الملكية؟' => 'ownership_proven',
        'مرجع إثبات الملكية' => 'ownership_proof_ref',
        'المستندات المطلوبة' => 'docs_required',
        'المستندات المستلمة' => 'docs_received',
        'المستندات الناقصة' => 'docs_missing',
        'نسبة اكتمال المستندات' => 'docs_complete_pct',
        'الشاسيه مطابق للمستند؟' => 'chassis_matches',
        'الشاسيه مكرر بالسجل؟' => 'chassis_duplicate',
        'نتيجة التحقق' => 'verify_result',
        'التحفظات' => 'reservations',
        'مرجع الاستثناء' => 'exception_ref',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'src_ref',
        'حالة البيانات' => 'data_state',
    );
    $GUIDE_COLS_ORDER = array(
        'رقم أمر التفتيش' => 'order_no',
        'كود الأصل' => '__equipo',
        'نوع التفتيش' => 'inspection_type',
        'سبب إصدار الأمر' => 'issue_reason',
        'مرجع الواقعة المسببة' => 'cause_event_ref',
        'مصدر الأمر' => 'issuer_name',
        'تاريخ إصدار الأمر' => 'issue_date',
        'الموعد المستهدف' => 'due_date',
        'الجهة المنفذة' => 'executor_party',
        'المفتش المكلف' => 'assigned_inspector',
        'الموقع المستهدف' => 'target_site',
        'المشروع' => 'project_ref',
        'الأولوية' => 'priority',
        'نطاق التفتيش المطلوب' => 'inspection_scope',
        'حالة الأمر' => 'state',
        'رقم بطاقة التفتيش' => 'card_no',
        'تاريخ التنفيذ الفعلي' => 'actual_exec_date',
        'التأخير عن الموعد (يوم)' => 'delay_days',
        'سبب الإلغاء' => 'cancel_reason',
        'المراجع' => 'reviewer',
        'تاريخ المراجعة' => 'review_date',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'src_ref',
        'حالة البيانات' => 'data_state',
    );
    $__allChecks = array(); foreach ($checks as $__iid => $__l) { foreach ($__l as $__c) { $__c['__iid'] = $__iid; $__allChecks[] = $__c; } }
    $__allOrders = array(); foreach ($orders as $__iid => $__l) { foreach ($__l as $__o) { $__allOrders[] = $__o; } }
    ?>
    <div class="card-header"><h5><i class="fas fa-file-shield"></i> وقائع التحقق من المصدر (حبة مستقلة عن الطلب)</h5></div>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th>
            <?php foreach ($GUIDE_COLS_CHECK as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead><tbody>
        <?php if ($__allChecks): $__n = 0; foreach ($__allChecks as $__c): $__n++; ?>
            <tr><td><?= $__n ?></td>
                <?php foreach ($GUIDE_COLS_CHECK as $__lbl => $__k):
                    $__v = ($__k === '__intake') ? ('#' . (int) $__c['__iid']) : (isset($__c[$__k]) ? (string) $__c[$__k] : '');
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?= htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS_CHECK) + 1 ?>">لا وقائع تحقق بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> أوامر التفتيش (حبة مستقلة عن الطلب)</h5></div>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th>
            <?php foreach ($GUIDE_COLS_ORDER as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead><tbody>
        <?php if ($__allOrders): $__n = 0; foreach ($__allOrders as $__o): $__n++; ?>
            <tr><td><?= $__n ?></td>
                <?php foreach ($GUIDE_COLS_ORDER as $__lbl => $__k):
                    $__v = ($__k === '__equipo')
                         ? ((isset($__o['equipment_id']) && isset($equip[(int) $__o['equipment_id']])) ? $equip[(int) $__o['equipment_id']] : '')
                         : (isset($__o[$__k]) ? (string) $__o[$__k] : '');
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?= htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS_ORDER) + 1 ?>">لا أوامر تفتيش بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <form id="vForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify"><input type="hidden" name="intake_id" value="0">
        <div class="card-header"><h5><i class="fas fa-file-shield"></i> واقعة تحقق من المصدر — ولا كرت قبل اجتيازها</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_v_res">النتيجة</label><select name="verify_result" id="w5_v_res"><option value="passed">مجتازة</option><option value="failed">مخفقة</option></select></div>
            <div class="field"><label for="w5_v_dt">نوع المستند</label><input type="text" name="doc_type" id="w5_v_dt"></div>
            <div class="field"><label for="w5_v_dr">مرجع المستند</label><input type="text" name="doc_ref" id="w5_v_dr"></div>
            <div class="field"><label for="w5_v_od">المالك المعلن</label><input type="text" name="owner_declared" id="w5_v_od"></div>
            <div class="field"><label for="w5_v_ol">المالك القانوني</label><input type="text" name="owner_legal" id="w5_v_ol"></div>
            <div class="field"><label for="w5_v_fr">سبب الإخفاق</label><input type="text" name="fail_reason" id="w5_v_fr"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> تسجيل</button></div>
    </form>

    <form id="oForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order_inspection"><input type="hidden" name="intake_id" value="0">
        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> أمر تفتيش — والتفتيش يبدأ بأمر لا بزيارة</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_o_no">رقم الأمر</label><input type="text" name="order_no" id="w5_o_no" required></div>
            <div class="field"><label for="w5_o_rs">سبب التفتيش</label><select name="reason" id="w5_o_rs">
                <?php foreach ($REASONS as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_o_due">تاريخ الاستحقاق</label><input type="date" name="due_date" id="w5_o_due"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> إصدار</button></div>
    </form>

    <form id="cForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="issue_card"><input type="hidden" name="intake_id" value="0">
        <div class="card-header"><h5><i class="fas fa-id-card"></i> إصدار كرت الأصل</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_c_eq">الأصل</label><select name="equipment_id" id="w5_c_eq" required>
                <option value="">—</option>
                <?php foreach ($equip as $eid => $en): ?><option value="<?= (int) $eid ?>"><?= htmlspecialchars($en) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> إصدار</button></div>
    </form>
</div>
<script>(function(){
    function bind(sel, formId){
        var f = document.getElementById(formId);
        document.querySelectorAll(sel).forEach(function(b){
            b.addEventListener('click', function(){
                if(!f) return;
                f.querySelector('[name="intake_id"]').value = this.dataset.id;
                f.classList.add('allforms-visible');
                window.scrollTo({top:(f.offsetTop||0)-20, behavior:'smooth'});
            });
        });
    }
    var t = document.getElementById('toggleForm'), i = document.getElementById('iForm');
    if (t && i) { t.addEventListener('click', function(){ i.classList.toggle('allforms-visible'); }); }
    bind('.w5-verify','vForm'); bind('.w5-order','oForm'); bind('.w5-card','cForm');
})();</script>
</body></html>
