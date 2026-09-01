<?php
/**
 * Fleet/asset_exit.php — خروجُ الأصلِ مؤقّتًا ودائمًا (RPR-W05 · FLEET-21/22)
 * ───────────────────────────────────────────────────────────────────────────
 * **الأصلُ يخرج ويعود — والعودةُ تُسجَّل في واقعتِها لا في كرتٍ جديد**
 * (‏`FLEET-21` نصًّا). و**الخروجُ الدائمُ لا عودةَ منه**: العائدُ بعده كرتٌ
 * جديدٌ بطلبِ إدخالٍ جديد، والمحاولةُ تُردُّ `PERMANENT_EXIT_NO_RETURN`.
 *
 * ◆ **والأثرُ الماليُّ مرجعٌ من المالية — والأسطولُ يوثّق الواقعة** (‏`FLEET-22`):
 *   `chk_aex_perm` يمنع خروجًا دائمًا بلا `finance_ref` **في القاعدة**، وليس
 *   نصيحةً في الشاشة.
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

$pp = check_page_permissions($conn, 'Fleet/asset_exit.php');
$can_view = $pp['can_view']; $can_add = $pp['can_add']; $can_edit = $pp['can_edit'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset exit super') : ems_tenant_db();
ALS::setEventConnection($conn);
$uid = intval($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($can_add || $can_edit)) {
    $act = (string) ($_POST['action'] ?? '');
    $msg = '⚠ لم ينفذ';
    if ($act === 'exit' && $can_add) {
        $r = ALS::exitAsset($gate, array(
            'equipment_id'    => intval($_POST['equipment_id'] ?? 0),
            'exit_kind'       => trim($_POST['exit_kind'] ?? 'temporary'),
            'reason_code'     => trim($_POST['reason_code'] ?? ''),
            'exit_date'       => trim($_POST['exit_date'] ?? ''),
            'expected_return' => trim($_POST['expected_return'] ?? ''),
            'disposal_kind'   => trim($_POST['disposal_kind'] ?? ''),
            'finance_ref'     => trim($_POST['finance_ref'] ?? ''),
            'doc_ref'         => trim($_POST['doc_ref'] ?? ''),
            'decided_by'      => $uid,
        ));
        $msg = $r['ok'] ? '✅ سجلت واقعة الخروج' : ('❌ ' . $r['reason']);
    } elseif ($act === 'return' && $can_edit) {
        $r = ALS::returnAsset($gate, intval($_POST['exit_id'] ?? 0), trim($_POST['actual_return'] ?? ''), $uid);
        $msg = $r['ok'] ? '✅ سجلت العودة' : ('❌ ' . $r['reason'] . ($r['reason_code'] !== '' ? ' [' . $r['reason_code'] . ']' : ''));
    }
    ems_gov_flash_redirect('asset_exit.php', $msg, 'GOV-OK-200', ''); exit();
}

$rows = array(); $equip = array();
try { $rows = $gate->select('asset_exit', array('orderBy' => 'exit_date DESC, id DESC', 'limit' => 400)); }
catch (\Throwable $t) { error_log('asset_exit.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 400)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' — ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_exit.php equip: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | خروج الأصل';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'خروج الأصل — المؤقت يعود والدائم لا'; $header_icon = 'fa fa-right-from-bracket'; $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'واقعة خروج'); }
    $header_back = array('href' => 'asset_assignments.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الإسناد والحركة');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $ok ? 'is-success' : 'is-error' ?>"><i class="fas <?= $ok ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا وقائع خروج مسجلة بعد', 'سجل أول واقعة بزر «واقعة خروج» في رأس الشاشة'); ?>

    <form id="xForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="exit">
        <div class="card-header"><h5><i class="fas fa-plus"></i> واقعة خروج</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_x_eq">الأصل</label><select name="equipment_id" id="w5_x_eq" required>
                <option value="">—</option><?php foreach ($equip as $eid => $en): ?><option value="<?= (int) $eid ?>"><?= htmlspecialchars($en) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_x_k">نوع الخروج</label><select name="exit_kind" id="w5_x_k">
                <option value="temporary">مؤقت</option><option value="permanent">دائم</option>
            </select></div>
            <div class="field"><label for="w5_x_r">سبب الخروج</label><input type="text" name="reason_code" id="w5_x_r" required></div>
            <div class="field"><label for="w5_x_d">تاريخ الخروج</label><input type="date" name="exit_date" id="w5_x_d" required></div>
            <div class="field"><label for="w5_x_e">العودة المتوقعة (للمؤقت)</label><input type="date" name="expected_return" id="w5_x_e"></div>
            <div class="field"><label for="w5_x_p">نوع الاستبعاد (للدائم)</label><input type="text" name="disposal_kind" id="w5_x_p"></div>
            <div class="field"><label for="w5_x_f">المرجع المالي (للدائم)</label><input type="text" name="finance_ref" id="w5_x_f"></div>
            <div class="field"><label for="w5_x_o">مرجع المستند</label><input type="text" name="doc_ref" id="w5_x_o"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> تسجيل</button></div>
    </form>

    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_asset_exit')); ?>
    <?php
    /* ◆ **أعمدةُ الورقةِ حرفًا** (GOV_EXEC §12): السطحُ يخدم هدفَين على حبّةٍ
         واحدةٍ مميَّزةٍ بنوعِ الخروج — `FLEET-21` المؤقّت و`FLEET-22` الدائم.
         فالرؤوسُ اتحادُ حقلَيهما، والصفُّ يعرض ما يخصُّ نوعَه ويترك ما لا يخصُّه «—».
       ◆ و«__»-البادئةُ قيمةٌ تُشتقُّ في الشاشةِ لا عمودٌ في الجدول. */
    $GUIDE_COLS = array(
        'رقم الخروج' => 'exit_code',
        'كود الأصل' => '__equip',
        'نوع الخروج' => '__kind',
        'تاريخ الخروج' => 'exit_date',
        'قراءة العداد' => 'meter_reading',
        'الجهة التي سحبت' => 'withdrawing_party',
        'المبرر' => 'justification',
        'مرجع القرار أو الإخطار' => 'decision_notice_ref',
        'المدة المتوقعة (يوم)' => 'expected_days',
        'تاريخ العودة المتوقع' => 'expected_return',
        'تاريخ العودة الفعلي' => 'actual_return',
        'مرجع إعادة الخدمة' => 'return_service_ref',
        'أيام الخروج الفعلية' => 'actual_out_days',
        'الانحراف عن المتوقع' => 'deviation_days',
        'الأثر على الوحدة التعاقدية' => 'contract_unit_effect',
        'أبلغ العميل؟' => 'client_notified',
        'الأصل البديل' => 'substitute_asset',
        'حالة الخروج' => 'state',
        'رقم الاستبعاد' => 'disposal_code',
        'تاريخ قرار الاستبعاد' => 'disposal_decision_date',
        'تاريخ الخروج الفعلي' => 'actual_exit_date',
        'طريقة الاستبعاد' => 'disposal_kind',
        'سبب الاستبعاد' => 'disposal_reason',
        'مرجع الحالة الفنية' => 'technical_state_ref',
        'المشتري أو المستلم' => 'buyer_receiver',
        'علاقة المشتري' => 'buyer_relation',
        'قراءة العداد النهائية' => 'final_meter',
        'صافي الحصيلة' => 'net_proceeds',
        'العملة' => 'currency_ref',
        'التكلفة (مرجع)' => 'cost_ref',
        'مجمع الإهلاك (مرجع)' => 'accum_depr_ref',
        'القيمة الدفترية (مرجع)' => 'book_value_ref',
        'المكسب أو الخسارة' => 'gain_loss',
        'مرجع محضر البيع' => 'sale_minutes_ref',
        'مرجع القيد المحاسبي' => 'journal_ref',
        'مرجع نقل الملكية' => 'title_transfer_ref',
        'موافقة الملاك' => 'owners_approval',
        'أخليت الوحدة التعاقدية؟' => 'unit_vacated',
        'المراجع' => 'reviewer',
        'تاريخ الاعتماد' => 'approved_at',
        'أساس السجل' => 'record_basis',
        'مرجع المصدر' => 'src_ref',
        'حالة البيانات' => 'data_state',
        'السبب' => 'reason_code',
        'المرجع المالي' => 'finance_ref',
    );
    ?>
    <table id="emsList_asset_exit" class="data-table">
        <thead><tr><th>إجراءات</th>
            <?php foreach ($GUIDE_COLS as $__lbl => $__k): ?><th><?= htmlspecialchars(ems_guide_label($__lbl), ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><div class="action-btns">
                    <?php if ($can_edit && $r['exit_kind'] === 'temporary' && $r['state'] === 'open'): ?>
                        <a href="javascript:void(0)" class="action-btn edit w5-return" data-id="<?= (int) $r['id'] ?>" title="تسجيل العودة"><i class="fas fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div></td>
                <?php foreach ($GUIDE_COLS as $__lbl => $__k):
                    if ($__k === '__equip')     { $__v = isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id']); }
                    elseif ($__k === '__kind')  { $__v = ($r['exit_kind'] === 'permanent') ? 'دائم' : 'مؤقت'; }
                    else                        { $__v = isset($r[$__k]) ? (string) $r[$__k] : ''; }
                    if (trim((string) $__v) === '') { $__v = '—'; } ?>
                <td<?= $__v === '—' ? ' class="ems-gov-empty"' : '' ?>><?= htmlspecialchars((string) $__v, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="<?= count($GUIDE_COLS) + 1 ?>">لا وقائع خروج بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <form id="rForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="return"><input type="hidden" name="exit_id" value="0">
        <div class="card-header"><h5><i class="fas fa-rotate-left"></i> تسجيل عودة من خروج مؤقت</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_r_d">تاريخ العودة الفعلي</label><input type="date" name="actual_return" id="w5_r_d" required></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> تسجيل</button></div>
    </form>
</div>
<script>(function(){
    var t = document.getElementById('toggleForm'), f = document.getElementById('xForm');
    if (t && f) { t.addEventListener('click', function(){ f.classList.toggle('allforms-visible'); }); }
    var rf = document.getElementById('rForm');
    document.querySelectorAll('.w5-return').forEach(function(b){
        b.addEventListener('click', function(){
            if (!rf) return;
            rf.querySelector('[name="exit_id"]').value = this.dataset.id;
            rf.classList.add('allforms-visible');
            window.scrollTo({top:(rf.offsetTop||0)-20, behavior:'smooth'});
        });
    });
})();</script>
</body></html>
