<?php
/**
 * Operations/fleet_calendar.php — تقويمُ الأسطول والحجز (RENTAL-CORE ①)
 * ───────────────────────────────────────────────────────────────────────────
 * «بماذا أستطيع أن أَعِد؟» — الشاشةُ تجيب قبل أن يَعِد المندوبُ لا بعده:
 *   ① سعةُ كل فئةٍ في نافذةٍ زمنية (إجمالي · متاح · مشغول).
 *   ② المعداتُ المتاحةُ فعلًا في تلك النافذة.
 *   ③ حجزُ نافذةٍ على معدةٍ بعينها — والحارسُ يرفض التعارض **قبل** الحفظ.
 * الفحصُ في AvailabilityService لا هنا، فيسري على كل مستدعٍ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Rental/AvailabilityService.php';

use App\Services\Rental\AvailabilityService as AV;

if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }

if (!function_exists('fc_e')) {
    function fc_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fc_back')) {
    function fc_back($msg, $q = '') {
        header('Location: fleet_calendar.php?' . ($q !== '' ? $q . '&' : '') . 'msg=' . urlencode($msg));
        exit();
    }
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) { header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.')); exit(); }
$uid = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

$fc_gate = ems_tenant_db();
$fc_perms = get_current_page_permissions($conn);
$can_add    = !empty($fc_perms['can_add']);
$can_edit   = !empty($fc_perms['can_edit']);
$can_delete = !empty($fc_perms['can_delete']);

// الرمزُ من المصدر المركزي — لا رمزَ موازٍ لكل شاشة (عرفُ CSRF المركزي ADR-05)
$fc_csrf = function_exists('generate_csrf_token')
    ? generate_csrf_token()
    : (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '');

$FC_STATES = array('مبدئي', 'مؤكَّد', 'محوَّل لعقد', 'منتهٍ', 'ملغى');

// ── النافذةُ الزمنية ───────────────────────────────────────────────────────
$vd = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); };
$from = $vd($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d');
$to   = $vd($_GET['to'] ?? '')   ? $_GET['to']   : date('Y-m-d', strtotime('+30 days'));
if (strtotime($to) < strtotime($from)) { $to = $from; }
$type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;
$qs = 'from=' . urlencode($from) . '&to=' . urlencode($to) . ($type_filter ? '&type=' . $type_filter : '');

// ══════════════════════════════════════════════════════════════════════════
// الأفعال
// ══════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($posted === '' || !hash_equals($fc_csrf, $posted)) {
        fc_back('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌', $qs);
    }
    $action = (string) ($_POST['fc_action'] ?? '');

    // ── حفظُ حجز ──────────────────────────────────────────────────────────
    if ($action === 'save') {
        $rid = intval($_POST['res_id'] ?? 0);
        if (($rid > 0 && !$can_edit) || ($rid === 0 && !$can_add)) {
            fc_back('لا توجد صلاحية لهذا الإجراء ❌', $qs);
        }
        $eqId   = intval($_POST['equipment_id'] ?? 0);
        $typeId = intval($_POST['equipment_type_id'] ?? 0);
        $qty    = max(1, intval($_POST['qty'] ?? 1));
        $sd     = (string) ($_POST['start_date'] ?? '');
        $ed     = (string) ($_POST['end_date'] ?? '');
        $state  = (string) ($_POST['state'] ?? 'مبدئي');

        if (!$vd($sd) || !$vd($ed)) { fc_back('تواريخُ الحجز غير صالحة ❌', $qs); }
        if (strtotime($ed) < strtotime($sd)) { fc_back('نهايةُ الحجز قبل بدايته ❌', $qs); }
        if (!in_array($state, $FC_STATES, true)) { fc_back('حالةُ الحجز غير صالحة ❌', $qs); }
        if ($eqId <= 0 && $typeId <= 0) { fc_back('اختر معدةً بعينها أو فئةً بعدد ❌', $qs); }

        // الحارسُ الجوهري: لا حجزَ على نافذةٍ مشغولة
        if ($eqId > 0) {
            $eqRow = $fc_gate->selectOne('equipments', array('columns' => array('id', 'code'), 'where' => array('id' => $eqId)));
            if ($eqRow === null) { fc_back('المعدةُ المحددة خارج نطاق شركتك ❌', $qs); }
            $conf = AV::conflictsFor($fc_gate, $eqId, $sd, $ed, $rid);
            if (count($conf['operations'])) {
                fc_back('المعدةُ مشغولةٌ بتشغيلٍ سارٍ في هذه النافذة — لا حجز ❌', $qs);
            }
            if (count($conf['reservations'])) {
                $c0 = $conf['reservations'][0];
                fc_back('تعارضٌ مع الحجز ' . $c0['reservation_no'] . ' (' . $c0['start_date'] . ' → ' . $c0['end_date'] . ') ❌', $qs);
            }
        }

        $clientId = intval($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $cRow = $fc_gate->selectOne('clients', array('columns' => array('id'), 'where' => array('id' => $clientId)));
            if ($cRow === null) { fc_back('العميلُ المحدد خارج نطاق شركتك ❌', $qs); }
        }
        $oppId = intval($_POST['opportunity_id'] ?? 0);
        if ($oppId > 0) {
            $oRow = $fc_gate->selectOne('opportunities', array('columns' => array('id'), 'where' => array('id' => $oppId)));
            if ($oRow === null) { $oppId = 0; }
        }

        $data = array(
            'equipment_id'      => $eqId > 0 ? $eqId : null,
            'equipment_type_id' => $typeId > 0 ? $typeId : null,
            'qty'               => $eqId > 0 ? 1 : $qty,
            'client_id'         => $clientId > 0 ? $clientId : null,
            'opportunity_id'    => $oppId > 0 ? $oppId : null,
            'start_date'        => $sd,
            'end_date'          => $ed,
            'state'             => $state,
            'purpose'           => trim((string) ($_POST['purpose'] ?? '')),
            'note'              => trim((string) ($_POST['note'] ?? '')),
        );

        try {
            if ($rid > 0) {
                $own = $fc_gate->selectOne('fleet_reservations', array('columns' => array('id'), 'where' => array('id' => $rid)));
                if ($own === null) { fc_back('لا يمكنك تعديل حجزٍ لا يتبع شركتك ❌', $qs); }
                $data['updated_at'] = date('Y-m-d H:i:s');
                $fc_gate->update('fleet_reservations', $data, array('id' => $rid));
                fc_back('حُدِّث الحجز ✅', $qs);
            }
            $data['reservation_no'] = AV::nextReservationNo($fc_gate, $company_id);
            $data['created_by'] = $uid;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['is_deleted'] = 0;
            $fc_gate->insert('fleet_reservations', $data);
            fc_back('حُجزت النافذة ' . $data['reservation_no'] . ' ✅', $qs);
        } catch (\Throwable $t) {
            error_log('fleet_calendar save: ' . $t->getMessage());
            fc_back('تعذّر حفظُ الحجز ❌', $qs);
        }
    }

    // ── إلغاءُ حجز (حالةٌ لا حذف) ─────────────────────────────────────────
    if ($action === 'cancel') {
        if (!$can_edit) { fc_back('لا توجد صلاحية لهذا الإجراء ❌', $qs); }
        $rid = intval($_POST['res_id'] ?? 0);
        try {
            $own = $fc_gate->selectOne('fleet_reservations', array('columns' => array('id'), 'where' => array('id' => $rid)));
            if ($own === null) { fc_back('الحجزُ خارج نطاق شركتك ❌', $qs); }
            $fc_gate->update('fleet_reservations',
                array('state' => 'ملغى', 'updated_at' => date('Y-m-d H:i:s')), array('id' => $rid));
            fc_back('أُلغي الحجز — والسجلُّ باقٍ ✅', $qs);
        } catch (\Throwable $t) {
            error_log('fleet_calendar cancel: ' . $t->getMessage());
            fc_back('تعذّر الإلغاء ❌', $qs);
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════
// القراءة
// ══════════════════════════════════════════════════════════════════════════
$capacity = AV::capacityByType($fc_gate, $from, $to);
$free     = AV::freeEquipment($fc_gate, $from, $to, $type_filter, 400);

$reservations = array();
try {
    $reservations = $fc_gate->scopedQuery(
        array('scope' => array('r' => 'fleet_reservations'),
              'enrich' => array('e' => 'equipments', 'c' => 'clients', 't' => 'equipments_types')),
        "SELECT r.*, e.code AS eq_code, e.name AS eq_name, c.client_name, t.type AS type_name
           FROM fleet_reservations r
           LEFT JOIN equipments e ON e.id = r.equipment_id
           LEFT JOIN clients c ON c.id = r.client_id
           LEFT JOIN equipments_types t ON t.id = r.equipment_type_id
          WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
            AND r.start_date <= ? AND r.end_date >= ?
          ORDER BY r.start_date DESC, r.id DESC",
        array($to, $from)
    );
} catch (\Throwable $t) { error_log('fleet_calendar list: ' . $t->getMessage()); }

$types = array();
try {
    $types = $fc_gate->scopedQuery(array('scope' => array('t' => 'equipments_types')),
        "SELECT t.id, t.type FROM equipments_types t ORDER BY t.type", array());
} catch (\Throwable $t) {
    $r = @mysqli_query($conn, "SELECT id, type FROM equipments_types WHERE status='active' ORDER BY type");
    while ($r && ($x = mysqli_fetch_assoc($r))) { $types[] = $x; }
}

$clients = array();
try {
    $clients = $fc_gate->scopedQuery(array('scope' => array('c' => 'clients')),
        "SELECT c.id, c.client_code, c.client_name FROM clients c
          WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0 ORDER BY c.client_name", array());
} catch (\Throwable $t) { error_log('fleet_calendar clients: ' . $t->getMessage()); }

$opps = array();
try {
    $opps = $fc_gate->scopedQuery(array('scope' => array('o' => 'opportunities')),
        "SELECT o.id, o.opp_code, o.title FROM opportunities o
          WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
            AND o.stage NOT IN ('فوز','خسارة','مستبعدة') ORDER BY o.id DESC LIMIT 100", array());
} catch (\Throwable $t) { error_log('fleet_calendar opps: ' . $t->getMessage()); }

$span_days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
$tot_all = 0; $tot_free = 0;
foreach ($capacity as $c) { $tot_all += $c['total']; $tot_free += $c['free']; }

$page_title = 'إيكوبيشن | تقويم الأسطول والحجز';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
<?php
$header_title = 'تقويمُ الأسطول والحجز';
$header_icon = 'fa fa-calendar-check';
$header_actions = array();
if ($can_add) {
    $header_actions[] = array('id' => 'fcToggleForm', 'class' => 'add-btn',
        'icon' => 'fa fa-plus', 'label' => 'حجزٌ جديد');
}
$header_back = array('href' => '../main/role_board.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
include('../includes/page_header.php');
if (function_exists('ems_screen_about')) {
    ems_screen_about(
        'التأجيرُ يؤجّر الأصلَ نفسه مرارًا — فالسؤالُ الأول: أمتاحةٌ من كذا إلى كذا؟ '
        . 'هذه الشاشةُ تجيب قبل أن تَعِد: سعةُ كل فئةٍ في النافذة، والمعداتُ المتاحةُ فعلًا، '
        . 'وحجزُ نافذةٍ يرفضه الحارسُ إن تعارضت مع تشغيلٍ سارٍ أو حجزٍ قائم.',
        array('حدّد النافذة الزمنية', 'اقرأ سعةَ الفئة قبل الوعد', 'احجز ثم حوّل إلى عقد')
    );
}
?>
  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-info" style="margin:10px 0"><?php echo fc_e($_GET['msg']); ?></div>
  <?php endif; ?>

  <div class="card"><div class="card-body">
    <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <div><label>من</label><input type="date" name="from" value="<?php echo fc_e($from); ?>" class="form-control"></div>
      <div><label>إلى</label><input type="date" name="to" value="<?php echo fc_e($to); ?>" class="form-control"></div>
      <div><label>الفئة</label>
        <select name="type" class="form-control">
          <option value="0">— كل الفئات —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo (int) $t['id']; ?>" <?php echo $type_filter === (int) $t['id'] ? 'selected' : ''; ?>>
              <?php echo fc_e($t['type']); ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> اعرض</button></div>
      <div style="color:#666;font-size:.9rem">النافذة <b><?php echo (int) $span_days; ?></b> يومًا ·
        متاحٌ <b style="color:#2C6749"><?php echo (int) $tot_free; ?></b> من <?php echo (int) $tot_all; ?></div>
    </form>
  </div></div>

  <!-- ① سعةُ الفئات -->
  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-layer-group"></i> سعةُ الفئات في النافذة</h5>
    <div class="table-responsive"><table class="table table-sm" data-no-dt="1">
      <thead><tr><th>الفئة</th><th>الإجمالي</th><th>المتاح</th><th>المشغول</th><th style="width:34%">نسبةُ الإتاحة</th></tr></thead>
      <tbody>
      <?php foreach ($capacity as $c):
        $pct = $c['total'] > 0 ? round(100 * $c['free'] / $c['total']) : 0; ?>
        <tr>
          <td><?php echo fc_e($c['type_name']); ?></td>
          <td><?php echo (int) $c['total']; ?></td>
          <td style="color:#2C6749;font-weight:700"><?php echo (int) $c['free']; ?></td>
          <td style="color:#9A3412"><?php echo (int) $c['busy']; ?></td>
          <td>
            <div style="background:#eee;border-radius:3px;height:16px;position:relative">
              <div style="background:#2C6749;height:16px;border-radius:3px;width:<?php echo (int) $pct; ?>%"></div>
            </div>
            <small><?php echo (int) $pct; ?>٪</small>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($capacity)): ?>
        <tr><td colspan="5" class="text-center text-muted">لا معداتٍ في نطاقك</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div></div>

  <!-- ② نموذجُ الحجز -->
  <?php if ($can_add): ?>
  <div class="card allforms" id="fcFormCard" style="margin-top:14px;display:none"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-plus"></i> حجزُ نافذة</h5>
    <form method="post" class="ems-form">
      <input type="hidden" name="csrf_token" value="<?php echo fc_e($fc_csrf); ?>">
      <input type="hidden" name="fc_action" value="save">
      <input type="hidden" name="res_id" id="fc_res_id" value="0">
      <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <div><label>المعدة (حجزٌ بعينها)</label>
          <select name="equipment_id" id="fc_eq" class="form-control">
            <option value="0">— بلا تحديد (احجز فئةً) —</option>
            <?php foreach ($free as $e): ?>
              <option value="<?php echo (int) $e['id']; ?>">
                <?php echo fc_e($e['code'] . ' — ' . $e['name'] . ' (' . ($e['type_name'] ?: 'غير مصنَّفة') . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">القائمةُ تعرض المتاحَ في النافذة أعلاه فقط</small>
        </div>
        <div><label>أو الفئة</label>
          <select name="equipment_type_id" class="form-control">
            <option value="0">— بلا فئة —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?php echo (int) $t['id']; ?>"><?php echo fc_e($t['type']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>العدد (عند حجز الفئة)</label>
          <input type="number" name="qty" min="1" value="1" class="form-control"></div>
        <div><label>من *</label><input type="date" name="start_date" required value="<?php echo fc_e($from); ?>" class="form-control"></div>
        <div><label>إلى *</label><input type="date" name="end_date" required value="<?php echo fc_e($to); ?>" class="form-control"></div>
        <div><label>الحالة</label>
          <select name="state" class="form-control">
            <?php foreach (array('مبدئي', 'مؤكَّد') as $s): ?>
              <option value="<?php echo fc_e($s); ?>"><?php echo fc_e($s); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>العميل</label>
          <select name="client_id" class="form-control">
            <option value="0">— بلا عميل —</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?php echo (int) $c['id']; ?>"><?php echo fc_e($c['client_name']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>الفرصة</label>
          <select name="opportunity_id" class="form-control">
            <option value="0">— بلا فرصة —</option>
            <?php foreach ($opps as $o): ?>
              <option value="<?php echo (int) $o['id']; ?>"><?php echo fc_e($o['opp_code'] . ' — ' . $o['title']); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>الغرض/الموقع</label><input type="text" name="purpose" class="form-control" maxlength="160"></div>
        <div style="grid-column:1/-1"><label>ملاحظات</label><input type="text" name="note" class="form-control" maxlength="255"></div>
      </div>
      <div style="margin-top:12px">
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> احجز</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('fcFormCard').style.display='none'">إلغاء</button>
      </div>
    </form>
  </div></div>
  <?php endif; ?>

  <!-- ③ الحجوزات -->
  <div class="card" style="margin-top:14px"><div class="card-body">
    <h5 style="margin:0 0 10px"><i class="fa fa-bookmark"></i> حجوزاتُ النافذة</h5>
    <div class="table-responsive"><table class="table display" id="fcTable">
      <thead><tr>
        <th>رقم الحجز</th><th>المعدة/الفئة</th><th>العميل</th><th>من</th><th>إلى</th>
        <th>الأيام</th><th>الحالة</th><th>الغرض</th><th>إجراءات</th>
      </tr></thead>
      <tbody>
      <?php foreach ($reservations as $r):
        $days = (int) ((strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400) + 1;
        $what = $r['eq_code'] !== null && $r['eq_code'] !== ''
            ? $r['eq_code'] . ' — ' . $r['eq_name']
            : (($r['type_name'] ?: 'غير محدد') . ' × ' . (int) $r['qty']);
        $tone = array('مبدئي' => '#8A5712', 'مؤكَّد' => '#2C6749', 'محوَّل لعقد' => '#2F5D6E',
                      'منتهٍ' => '#6B6355', 'ملغى' => '#9A3412'); ?>
        <tr>
          <td><?php echo fc_e($r['reservation_no']); ?></td>
          <td><?php echo fc_e($what); ?></td>
          <td><?php echo fc_e($r['client_name'] ?: '—'); ?></td>
          <td><?php echo fc_e($r['start_date']); ?></td>
          <td><?php echo fc_e($r['end_date']); ?></td>
          <td><?php echo $days; ?></td>
          <td><span style="color:<?php echo $tone[$r['state']] ?? '#333'; ?>;font-weight:700">
              <?php echo fc_e($r['state']); ?></span></td>
          <td><?php echo fc_e($r['purpose'] ?: '—'); ?></td>
          <td>
            <?php if ($can_edit && $r['state'] !== 'ملغى'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('إلغاءُ الحجز؟ السجلُّ يبقى.')">
              <input type="hidden" name="csrf_token" value="<?php echo fc_e($fc_csrf); ?>">
              <input type="hidden" name="fc_action" value="cancel">
              <input type="hidden" name="res_id" value="<?php echo (int) $r['id']; ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="إلغاء">
                <i class="fa fa-ban"></i></button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (!count($reservations)): ?>
      <p class="text-muted" style="margin-top:8px">لا حجوزاتٍ في هذه النافذة — والحجزُ يمنع الوعدَ بما لا تملك.</p>
    <?php endif; ?>
  </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
$(function () {
    $('#fcToggleForm').on('click', function (e) {
        e.preventDefault();
        var c = document.getElementById('fcFormCard');
        if (c) { c.style.display = (c.style.display === 'none' ? '' : 'none'); }
    });
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#fcTable')) {
        $('#fcTable').DataTable({ stateSave: false, pageLength: 25, order: [[3, 'desc']] });
    }
});
</script>

