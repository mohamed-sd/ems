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

$REASONS = array('intake' => 'تفتيشُ دخول', 'periodic' => 'تفتيشٌ دوريّ', 'post_repair' => 'ما بعدَ الإصلاح',
                 'pre_exit' => 'ما قبلَ الخروج', 'incident' => 'واقعةٌ طارئة');
$SOURCES = array('owned' => 'مملوكة', 'financed' => 'ممولة', 'supplier_external' => 'مورّدة', 'rented' => 'مستأجرة');
$STATES  = array('draft' => 'مسودة', 'submitted' => 'مرفوع', 'source_verified' => 'مصدرٌ محقَّق',
                 'inspection_ordered' => 'أمرُ تفتيشٍ صادر', 'inspected' => 'مُفتَّش',
                 'card_issued' => 'كرتٌ صادر', 'activated' => 'مُفعَّل', 'rejected' => 'مرفوض');

$act = isset($_POST['action']) ? (string) $_POST['action'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act !== '' && ($can_add || $can_edit)) {
    $msg = '⚠ لم يُنفَّذ'; $code = 'GOV-INFO-200';
    if ($act === 'open_intake' && $can_add) {
        $r = ALS::openIntake($gate, array(
            'intake_no'      => trim($_POST['intake_no'] ?? ''),
            'requested_dept' => trim($_POST['requested_dept'] ?? ''),
            'asset_kind'     => trim($_POST['asset_kind'] ?? ''),
            'source_type'    => trim($_POST['source_type'] ?? 'owned'),
            'requested_by'   => $uid,
            'source_ref'     => 'Fleet/asset_intake.php',
        ));
        $msg = $r['ok'] ? '✅ سُجِّل طلبُ الإدخال' : '❌ ' . $r['reason'];
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
        $msg = $r['ok'] ? '✅ سُجِّلت واقعةُ التحقُّق' : '❌ ' . $r['reason'];
    } elseif ($act === 'order_inspection' && $can_edit) {
        $r = ALS::orderInspection($gate, array(
            'order_no'  => trim($_POST['order_no'] ?? ''),
            'intake_id' => intval($_POST['intake_id'] ?? 0),
            'reason'    => trim($_POST['reason'] ?? 'intake'),
            'due_date'  => trim($_POST['due_date'] ?? ''),
            'ordered_by' => $uid,
        ));
        $msg = $r['ok'] ? '✅ صدر أمرُ التفتيش' : '❌ ' . $r['reason'];
    } elseif ($act === 'issue_card' && $can_edit) {
        $r = ALS::issueCard($gate, intval($_POST['intake_id'] ?? 0), intval($_POST['equipment_id'] ?? 0), $uid);
        $msg = $r['ok'] ? '✅ صدر كرتُ الأصل' : '❌ ' . $r['reason'];
    } elseif ($act === 'activate' && $can_edit) {
        $r = ALS::activateAsset($gate, intval($_POST['intake_id'] ?? 0), $uid);
        $msg = $r['ok'] ? '✅ فُعِّل الأصل' : '❌ ' . $r['reason'];
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
    echo ems_states_bundle('لا طلباتِ إدخالٍ مسجَّلةً بعدُ', 'ابدأْ دورةَ الأصلِ بزرِّ «طلب إدخال» في رأسِ الشاشة'); ?>

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

    <div class="table-wrap"><table class="data-table">
        <thead><tr>
            <th>إجراءات</th><th>رقم الطلب</th><th>الحالة</th><th>قاعدة الحالة</th><th>الإدارة الطالبة</th>
            <th>المصدر</th><th>وقائع التحقق</th><th>أوامر التفتيش</th><th>الأصل</th><th>سبب الرفض</th>
        </tr></thead><tbody>
        <?php if ($rows): foreach ($rows as $r): $iid = (int) $r['id'];
            $ck = isset($checks[$iid]) ? $checks[$iid] : array();
            $od = isset($orders[$iid]) ? $orders[$iid] : array();
            $passed = 0; foreach ($ck as $c) { if ($c['verify_result'] === 'passed') { $passed++; } } ?>
            <tr>
                <td><div class="action-btns">
                    <?php if ($can_edit && in_array($r['state'], array('submitted', 'source_verified'), true)): ?>
                        <a href="javascript:void(0)" class="action-btn edit w5-verify" data-id="<?= $iid ?>" title="تحقُّق من المصدر"><i class="fas fa-file-shield"></i></a>
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
                <td><strong><?= htmlspecialchars((string) $r['intake_no']) ?></strong></td>
                <td><?= htmlspecialchars(isset($STATES[$r['state']]) ? $STATES[$r['state']] : (string) $r['state']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['state_rule']) ?></small></td>
                <td><?= htmlspecialchars((string) $r['requested_dept']) ?></td>
                <td><?= htmlspecialchars(isset($SOURCES[$r['source_type']]) ? $SOURCES[$r['source_type']] : (string) $r['source_type']) ?></td>
                <td><?= count($ck) ?> (مجتازة <?= $passed ?>)</td>
                <td><?= count($od) ?></td>
                <td><?= htmlspecialchars((int) $r['equipment_id'] > 0 && isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : '—') ?></td>
                <td><?= htmlspecialchars((string) $r['reject_reason']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10">لا طلباتِ إدخالٍ بعدُ.</td></tr>
        <?php endif; ?>
        </tbody></table></div>

    <form id="vForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify"><input type="hidden" name="intake_id" value="0">
        <div class="card-header"><h5><i class="fas fa-file-shield"></i> واقعة تحقُّق من المصدر — ولا كرتَ قبلَ اجتيازِها</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_v_res">النتيجة</label><select name="verify_result" id="w5_v_res"><option value="passed">مجتازة</option><option value="failed">مخفقة</option></select></div>
            <div class="field"><label for="w5_v_dt">نوع المستند</label><input type="text" name="doc_type" id="w5_v_dt"></div>
            <div class="field"><label for="w5_v_dr">مرجع المستند</label><input type="text" name="doc_ref" id="w5_v_dr"></div>
            <div class="field"><label for="w5_v_od">المالك المُعلَن</label><input type="text" name="owner_declared" id="w5_v_od"></div>
            <div class="field"><label for="w5_v_ol">المالك القانوني</label><input type="text" name="owner_legal" id="w5_v_ol"></div>
            <div class="field"><label for="w5_v_fr">سبب الإخفاق</label><input type="text" name="fail_reason" id="w5_v_fr"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> تسجيل</button></div>
    </form>

    <form id="oForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order_inspection"><input type="hidden" name="intake_id" value="0">
        <div class="card-header"><h5><i class="fas fa-clipboard-check"></i> أمر تفتيش — والتفتيشُ يبدأ بأمرٍ لا بزيارة</h5></div>
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
