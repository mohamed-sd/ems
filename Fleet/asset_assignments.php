<?php
/**
 * Fleet/asset_assignments.php — إسنادُ الأصلِ وحركتُه (RPR-W05 · FLEET-12/13)
 * ───────────────────────────────────────────────────────────────────────────
 * **لا يُنشأ أصلٌ جديدٌ عند الانتقالِ والتاريخُ لا يُمحى** (‏`FLEET-13` نصًّا).
 * فالإسنادُ صفٌّ بفترةٍ في `asset_assignment`، والسابقُ **يُنهى بسببِه** ولا
 * يُدهَس؛ وسجلُّ الحركةِ الحيُّ (`fleet_equipment_history`) يُعرَض إلى جانبِه
 * قراءةً لا كتابة.
 *
 * ◆ **وحارسانِ يُردَّان لا يُتجاهَلان**: أصلٌ خرج خروجًا دائمًا لا يُسنَد
 *   (`ASSET_PERMANENTLY_EXITED`)، وأصلٌ لم يُفعَّل لا يُسنَد (`ASSET_NOT_ACTIVE`)
 *   — والتفعيلُ يسبق الإسناد.
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

$pp = check_page_permissions($conn, 'Fleet/asset_assignments.php');
$can_view = $pp['can_view']; $can_add = $pp['can_add'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset assignment super') : ems_tenant_db();
ALS::setEventConnection($conn);
$uid = intval($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign' && $can_add) {
    $r = ALS::assignAsset($gate, array(
        'equipment_id' => intval($_POST['equipment_id'] ?? 0),
        'assign_kind'  => trim($_POST['assign_kind'] ?? 'site'),
        'site_id'      => intval($_POST['site_id'] ?? 0),
        'project_id'   => intval($_POST['project_id'] ?? 0),
        'unit_ref'     => trim($_POST['unit_ref'] ?? ''),
        'valid_from'   => trim($_POST['valid_from'] ?? ''),
        'decision_ref' => trim($_POST['decision_ref'] ?? ''),
        'assigned_by'  => $uid,
    ));
    $msg = $r['ok'] ? '✅ تم الإسناد' : ('❌ ' . $r['reason'] . ($r['reason_code'] !== '' ? ' [' . $r['reason_code'] . ']' : ''));
    ems_gov_flash_redirect('asset_assignments.php', $msg, 'GOV-OK-200', ''); exit();
}

$rows = array(); $equip = array(); $sites = array(); $projects = array(); $hist = array();
try { $rows = $gate->select('asset_assignment', array('orderBy' => 'equipment_id ASC, valid_from DESC', 'limit' => 500)); }
catch (\Throwable $t) { error_log('asset_assignments.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 400)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' — ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_assignments.php equip: ' . $t->getMessage()); }
try { foreach ($gate->select('sites', array('columns' => array('id', 'name'), 'orderBy' => 'id ASC', 'limit' => 300)) as $s) { $sites[(int) $s['id']] = (string) $s['name']; } }
catch (\Throwable $t) { error_log('asset_assignments.php sites: ' . $t->getMessage()); }
try { foreach ($gate->select('project', array('columns' => array('id', 'name'), 'orderBy' => 'id DESC', 'limit' => 300)) as $p) { $projects[(int) $p['id']] = (string) $p['name']; } }
catch (\Throwable $t) { error_log('asset_assignments.php projects: ' . $t->getMessage()); }
try { $hist = $gate->select('fleet_equipment_history', array('orderBy' => 'event_date DESC, id DESC', 'limit' => 120)); }
catch (\Throwable $t) { error_log('asset_assignments.php history: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | إسناد الأصل وحركته';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'إسناد الأصل وحركته — لا أصل جديد عند الانتقال'; $header_icon = 'fa fa-map-location-dot'; $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إسناد'); }
    $header_back = array('href' => 'asset_use_rights.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'حق الاستخدام');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $ok ? 'is-success' : 'is-error' ?>"><i class="fas <?= $ok ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا إسنادات أصول مسجلة بعد', 'أسند أول أصل بزر «إسناد» في رأس الشاشة'); ?>

    <form id="aForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <div class="card-header"><h5><i class="fas fa-plus"></i> إسناد أصل لموقع أو مشروع</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_as_eq">الأصل</label><select name="equipment_id" id="w5_as_eq" required>
                <option value="">—</option><?php foreach ($equip as $eid => $en): ?><option value="<?= (int) $eid ?>"><?= htmlspecialchars($en) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_as_k">نوع الإسناد</label><select name="assign_kind" id="w5_as_k">
                <option value="site">موقع</option><option value="project">مشروع</option><option value="unit">وحدة عمل</option>
            </select></div>
            <div class="field"><label for="w5_as_s">الموقع</label><select name="site_id" id="w5_as_s">
                <option value="">—</option><?php foreach ($sites as $sid => $sn): ?><option value="<?= (int) $sid ?>"><?= htmlspecialchars($sn) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_as_p">المشروع</label><select name="project_id" id="w5_as_p">
                <option value="">—</option><?php foreach ($projects as $pid => $pn): ?><option value="<?= (int) $pid ?>"><?= htmlspecialchars($pn) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_as_u">مرجع وحدة العمل</label><input type="text" name="unit_ref" id="w5_as_u"></div>
            <div class="field"><label for="w5_as_f">من تاريخ</label><input type="date" name="valid_from" id="w5_as_f" required></div>
            <div class="field"><label for="w5_as_d">مرجع القرار</label><input type="text" name="decision_ref" id="w5_as_d"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> إسناد</button></div>
    </form>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_asset_assignments')); ?>
    <?php
    /* ◆ **أعمدةُ الورقةِ حرفًا** (GOV_EXEC §12 · الهدف `FLEET-12` «التخصيص على
         الوحدات»): الاسمُ ⇒ العمود في مصفوفةٍ واحدةٍ يقرؤها الرأسُ والخليّةُ معًا.
       ◆ و«__»-البادئةُ قيمةٌ مُشتقّةٌ في الشاشةِ لا عمودٌ في الجدول. */
    $GUIDE_COLS = array(
        'رقم التخصيص' => 'assign_code',
        'كود الأصل' => '__equip',
        'مفتاح الوحدة' => 'unit_key',
        'كود عقد العميل' => 'client_contract_ref',
        'رقم العميل' => 'client_no',
        'رقم المشروع' => '__project',
        'نموذج العمل' => 'business_model',
        'رقم الآلية في عقد العميل' => 'machine_no_contract',
        'كود المعدة بالمشروع' => 'project_asset_code',
        'صفة التخصيص' => 'assign_kind',
        'مرجع التفعيل' => 'activation_ref',
        'تاريخ بداية التخصيص' => 'valid_from',
        'تاريخ نهاية التخصيص' => 'valid_to',
        'سبب الإنهاء' => 'end_reason',
        'الأصل البديل' => 'substitute_asset',
        'مهلة الإحلال (يوم)' => 'replace_sla_days',
        'أيام التوقف بين الأصلين' => 'gap_days',
        'الساعات المنفذة' => 'executed_hours',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'src_ref',
        'حالة البيانات' => 'data_state',
        'الموقع' => '__site',
        'الحالة' => 'state',
        'مرجع القرار' => 'decision_ref',
    );
    ?>
    <table id="emsList_asset_assignments" class="data-table">
        <thead><tr><th>إجراءات</th>
            <?php foreach ($GUIDE_COLS as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><div class="action-btns"><span class="action-btn" title="<?= htmlspecialchars((string) $r['state']) ?>"><i class="fas <?= $r['state'] === 'active' ? 'fa-circle-play' : 'fa-circle-stop' ?>"></i></span></div></td>
                <?php foreach ($GUIDE_COLS as $__lbl => $__k):
                    if ($__k === '__equip')        { $__v = isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id']); }
                    elseif ($__k === '__site')     { $__v = isset($sites[(int) $r['site_id']]) ? $sites[(int) $r['site_id']] : ''; }
                    elseif ($__k === '__project')  { $__v = isset($projects[(int) $r['project_id']]) ? $projects[(int) $r['project_id']] : ''; }
                    else                           { $__v = isset($r[$__k]) ? (string) $r[$__k] : ''; }
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?= htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS) + 1 ?>">لا إسنادات أصول بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <div class="card-header"><h5><i class="fas fa-clock-rotate-left"></i> سجل حركة الأصول الحي — قراءة لا كتابة</h5></div>
    <?php
    /* ◆ الهدفُ الثاني لهذا السطح — `FLEET-13` «حركة الموقع والمشروع»: حبّتُه
         **واقعةٌ لحظيّةٌ** لا مدّةٌ مفتوحةٌ كالتخصيص، فجدولُه سجلُّ الوقائع. */
    $GUIDE_COLS_MOVE = array(
        'رقم الحركة' => 'move_code',
        'كود الأصل' => '__equip',
        'تسلسل الحركة' => 'move_seq',
        'تاريخ الحركة' => 'event_date',
        'من موقع' => 'from_site',
        'من مشروع' => 'from_project',
        'من وحدة تعاقدية' => 'from_unit',
        'إلى موقع' => 'to_site',
        'إلى مشروع' => 'to_project',
        'إلى وحدة تعاقدية' => 'to_unit',
        'سبب الحركة' => 'move_reason',
        'قراءة العداد عند المغادرة' => 'meter_at_depart',
        'قراءة العداد عند الوصول' => 'meter_at_arrive',
        'مرجع طلب الترحيل' => 'transfer_request_ref',
        'مرجع أمر الترحيل' => 'transfer_order_ref',
        'مرجع فحص ما قبل الحركة' => 'pre_move_check_ref',
        'مرجع فحص ما بعد الحركة' => 'post_move_check_ref',
        'أضرار أثناء النقل' => 'transit_damage',
        'أيام خارج الخدمة' => 'out_of_service_days',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'src_ref',
        'حالة البيانات' => 'data_state',
        'نوع الواقعة' => 'event_type',
        'ساعات العمل' => 'work_hours',
        'ساعات التوقف' => 'down_hours',
        'ملاحظة' => 'note',
    );
    ?>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>#</th>
            <?php foreach ($GUIDE_COLS_MOVE as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if ($hist): $n = 0; foreach ($hist as $h): $n++; ?>
            <tr><td><?= $n ?></td>
                <?php foreach ($GUIDE_COLS_MOVE as $__lbl => $__k):
                    $__v = ($__k === '__equip')
                         ? (isset($equip[(int) $h['equipment_id']]) ? $equip[(int) $h['equipment_id']] : ('#' . (int) $h['equipment_id']))
                         : (isset($h[$__k]) ? (string) $h[$__k] : '');
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?= htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS_MOVE) + 1 ?>">لا وقائع حركة مسجلة.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
<script>(function(){
    var t = document.getElementById('toggleForm'), f = document.getElementById('aForm');
    if (t && f) { t.addEventListener('click', function(){ f.classList.toggle('allforms-visible'); }); }
})();</script>
</body></html>
