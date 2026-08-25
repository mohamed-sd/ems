<?php
/**
 * Fleet/asset_use_rights.php — حقُّ الاستخدامِ التشغيليّ (RPR-W05 · FLEET-09)
 * ───────────────────────────────────────────────────────────────────────────
 * **الملكيّةُ متعاقبةٌ لا متزامنة** (‏`FLEET-09` نصًّا) — والسطحُ يعرض ما قِيس
 * لا ما يُشتهى: كلُّ حقٍّ بحصّتِه وفترتِه، و**مجموعُ الحصصِ المتزامنةِ في نافذةِ
 * بدايتِه** موسومًا. النافذةُ التي يتجاوز مجموعُها المئةَ تُعرَض
 * `W5_CONCURRENT_CLAIM_OPEN` — لا تُخفى ولا تُدهَس؛ وحسمُها عند مالكِها
 * (التمويل · W11).
 *
 * ◆ **ولماذا يعنينا**: مَن يملك حقَّ الاستخدامِ هو مَن يفوتر ساعةَ الآلةِ ومَن
 *   يتحمّل التزامَ جاهزيّتِها. فمدّعيانِ في نافذةٍ واحدةٍ فاتورتانِ لساعةٍ واحدة.
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

$pp = check_page_permissions($conn, 'Fleet/asset_use_rights.php');
$can_view = $pp['can_view']; $can_add = $pp['can_add'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset use rights super') : ems_tenant_db();
ALS::setEventConnection($conn);
$uid = intval($_SESSION['user']['id'] ?? 0);
$KINDS = array('company' => 'الشركة', 'financier' => 'مموّل', 'supplier' => 'مورد', 'client' => 'عميل');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grant' && $can_add) {
    $r = ALS::grantUseRight($gate, array(
        'equipment_id' => intval($_POST['equipment_id'] ?? 0),
        'holder_kind'  => trim($_POST['holder_kind'] ?? 'company'),
        'holder_ref_id' => intval($_POST['holder_ref_id'] ?? 0),
        'holder_name'  => trim($_POST['holder_name'] ?? ''),
        'percent'      => (float) ($_POST['percent'] ?? 100),
        'valid_from'   => trim($_POST['valid_from'] ?? ''),
        'valid_to'     => trim($_POST['valid_to'] ?? ''),
        'doc_ref'      => trim($_POST['doc_ref'] ?? ''),
        'source_register' => 'declared',
        'granted_by'   => $uid,
    ));
    $msg = $r['ok']
        ? ('✅ سجل حق الاستخدام — التزامن المقيس ' . $r['concurrency_pct'] . '٪ (' . $r['rule'] . ')')
        : ('❌ ' . $r['reason']);
    ems_gov_flash_redirect('asset_use_rights.php', $msg, 'GOV-OK-200', ''); exit();
}

$rows = array(); $equip = array();
try { $rows = $gate->select('asset_use_right', array('orderBy' => 'equipment_id ASC, valid_from ASC', 'limit' => 500)); }
catch (\Throwable $t) { error_log('asset_use_rights.php list: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 400)) as $e) { $equip[(int) $e['id']] = trim(($e['code'] ?? '') . ' — ' . ($e['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_use_rights.php equip: ' . $t->getMessage()); }

$openN = 0; foreach ($rows as $r) { if ($r['concurrency_rule'] === 'W5_CONCURRENT_CLAIM_OPEN') { $openN++; } }

$page_title = 'إيكوبيشن | حق الاستخدام التشغيلي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'حق الاستخدام التشغيلي — الملكية متعاقبة لا متزامنة'; $header_icon = 'fa fa-handshake'; $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'منح حق'); }
    $header_back = array('href' => 'asset_intake.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'طلب الإدخال');
    include('../includes/page_header.php'); ?>
    <?php if (!empty($_GET['msg'])): $ok = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $ok ? 'is-success' : 'is-error' ?>"><i class="fas <?= $ok ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">حقوق مسجلة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $openN ?></div><div class="ems-stat-label">حق متزامن مفتوح</div></div>
    </div>
    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا حقوق استخدام مسجلة بعد', 'امنح أول حق بزر «منح حق» في رأس الشاشة'); ?>

    <form id="gForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="grant">
        <div class="card-header"><h5><i class="fas fa-plus"></i> منح حق استخدام في فترة</h5></div>
        <div class="ems-form-grid">
            <div class="field"><label for="w5_ur_eq">الأصل</label><select name="equipment_id" id="w5_ur_eq" required>
                <option value="">—</option><?php foreach ($equip as $eid => $en): ?><option value="<?= (int) $eid ?>"><?= htmlspecialchars($en) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_ur_k">صفة الحائز</label><select name="holder_kind" id="w5_ur_k">
                <?php foreach ($KINDS as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="w5_ur_n">اسم الحائز</label><input type="text" name="holder_name" id="w5_ur_n"></div>
            <div class="field"><label for="w5_ur_r">مرجع الحائز</label><input type="number" name="holder_ref_id" id="w5_ur_r"></div>
            <div class="field"><label for="w5_ur_p">الحصة ٪</label><input type="number" step="0.01" name="percent" id="w5_ur_p" value="100" required></div>
            <div class="field"><label for="w5_ur_f">من تاريخ</label><input type="date" name="valid_from" id="w5_ur_f" required></div>
            <div class="field"><label for="w5_ur_t">إلى تاريخ</label><input type="date" name="valid_to" id="w5_ur_t"></div>
            <div class="field"><label for="w5_ur_d">مرجع المستند</label><input type="text" name="doc_ref" id="w5_ur_d"></div>
        </div>
        <div class="ems-form-actions"><button type="submit" class="add-btn"><i class="fas fa-save"></i> منح</button></div>
    </form>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>إجراءات</th><th>الأصل</th><th>الحائز</th><th>الصفة</th><th>الحصة ٪</th>
            <th>من</th><th>إلى</th><th>مجموع المتزامن ٪</th><th>حكم التزامن</th><th>مصدر القياس</th><th>المستند</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): $open = ($r['concurrency_rule'] === 'W5_CONCURRENT_CLAIM_OPEN'); ?>
            <tr>
                <td><div class="action-btns"><span class="action-btn" title="<?= htmlspecialchars((string) $r['concurrency_note']) ?>"><i class="fas <?= $open ? 'fa-triangle-exclamation' : 'fa-check' ?>"></i></span></div></td>
                <td><?= htmlspecialchars(isset($equip[(int) $r['equipment_id']]) ? $equip[(int) $r['equipment_id']] : ('#' . (int) $r['equipment_id'])) ?></td>
                <td><strong><?= htmlspecialchars((string) $r['holder_name']) ?></strong></td>
                <td><?= htmlspecialchars(isset($KINDS[$r['holder_kind']]) ? $KINDS[$r['holder_kind']] : (string) $r['holder_kind']) ?></td>
                <td><?= htmlspecialchars((string) $r['percent']) ?></td>
                <td><?= htmlspecialchars((string) $r['valid_from']) ?></td>
                <td><?= htmlspecialchars((string) ($r['valid_to'] ?? '—')) ?></td>
                <td><?= htmlspecialchars((string) $r['concurrency_pct']) ?></td>
                <td><?= htmlspecialchars((string) $r['concurrency_rule']) ?></td>
                <td><small><?= htmlspecialchars((string) $r['source_register'] . ' ' . (string) $r['source_row_ref']) ?></small></td>
                <td><?= htmlspecialchars((string) $r['doc_ref']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11">لا حقوق استخدام بعد.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
</div>
<script>(function(){
    var t = document.getElementById('toggleForm'), f = document.getElementById('gForm');
    if (t && f) { t.addEventListener('click', function(){ f.classList.toggle('allforms-visible'); }); }
})();</script>
</body></html>
