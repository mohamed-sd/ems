<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

/* ── الترتيبُ الحاكم (TS-08): جلسةٌ ثم إعدادٌ ثم حارسُ شاشةٍ ثم حارسُ فعلٍ
   ثم رمزُ حمايةٍ ثم معالجُ POST ثم الاستعلاماتُ ثم العرض ── */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');
$PERMS = check_page_permissions($conn, 'Suppliers/sup_handover.php');

require_once __DIR__ . '/../app/Services/Capacity/HandoverService.php';

$company_id = (int) ($_SESSION['user']['company_id'] ?? 0);
$actor_id   = (int) ($_SESSION['user']['id'] ?? 0);
$flash = null; $flash_ok = false;

/* ── معالجُ POST — الشاشةُ تنادي الخدمةَ والخدمةُ تكتب (TS-09) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($PERMS['can_add'])) {
        $flash = 'لا تملك صلاحيةَ تسجيلِ التسليم — راجِعْ مديرَك.';
    } else {
        require_csrf();
        $res = \App\Services\Capacity\HandoverService::record(ems_tenant_db(), $conn, array(
            'company_id'        => $company_id,
            'from_container_id' => (int) ($_POST['from_container_id'] ?? 0),
            'to_container_id'   => (int) ($_POST['to_container_id'] ?? 0),
            'moved_qty'         => (float) ($_POST['moved_qty'] ?? 0),
            'effective_from'    => (string) ($_POST['effective_from'] ?? ''),
            'doc_ref'           => (string) ($_POST['doc_ref'] ?? ''),
            'reason'            => (string) ($_POST['reason'] ?? ''),
        ), $actor_id);
        $flash_ok = !empty($res['ok']);
        $flash = $flash_ok
            ? 'سُجِّل التسليمُ برقم #' . (int) $res['swap_id'] . ' — وانتقلت الحصةُ بين الحاويتين.'
            : 'لم يُسجَّل: ' . implode(' · ', (array) $res['reasons']);
    }
}

/* ── الاستعلامات (قراءةٌ فقط) ── */
$containers = array();
$st = $conn->prepare(
    "SELECT c.id, c.container_no, c.allocated_qty, c.cap_qty, s.name legal_name
     FROM op_containers c LEFT JOIN suppliers s ON s.id = c.supplier_id
     WHERE c.company_id = ? AND c.is_deleted = 0 AND c.level = 'مورد'
     ORDER BY c.container_no");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $containers[] = $x; }
$st->close();

$swaps = array();
$st = $conn->prepare(
    "SELECT w.id, w.effective_from, w.moved_qty, w.doc_ref, w.reason,
            cf.container_no from_no, ct.container_no to_no
     FROM container_swaps w
     JOIN op_containers cf ON cf.id = w.container_id
     JOIN op_containers ct ON ct.id = w.to_container_id
     WHERE w.company_id = ?
     ORDER BY w.id DESC LIMIT 40");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $swaps[] = $x; }
$st->close();

$page_title = 'إيكوبيشن | تسليمُ الحصص بين الموردين';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier', 'العقودُ والحصص');
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01: أنماطُ الصفحةِ الموضعيةُ رُحِّلت إلى أصنافٍ — والألوانُ رموزٌ حصرًا */
.suph-sub { color: var(--c-666666, #666); margin: 4px 0 0; }
.suph-flash { display: flex; gap: 8px; align-items: center; }
.suph-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.suph-req { color: var(--c-state-danger-strong); }
.suph-span-all { grid-column: 1 / -1; }
.suph-empty-note { color: var(--c-777777, #777); }
</style>
<div class="content-wrapper ems-doc-cycle" dir="rtl">
  <section class="content-header"><h1>تسليمُ الحصص بين الموردين</h1>
    <p class="suph-sub">هنا نسجّل انتقالَ حصةِ ساعاتٍ من موردٍ إلى آخر — بمستندٍ وتاريخٍ، ولا يُمسُّ شهرٌ مغلق.</p>
  </section>
  <section class="content">
    <?php
    echo ems_next_step('تسجيلُ التسليمِ بمستندِه وتاريخِ سريانِه — والحصةُ تنتقل بين الحاويتين فورَ الحفظ');
    echo ems_states_bundle('لا تسليماتِ حصصٍ مسجَّلةً بعد', 'سجّلِ التسليمَ بمستندِه وتاريخِه فيظهر أثرُه هنا');
    ?>

    <?php if ($flash !== null): ?>
      <div class="alert suph-flash <?= $flash_ok ? 'alert-success' : 'alert-danger' ?>">
        <span><?= $flash_ok ? '✔ نجح:' : '✖ توقف:' ?></span>
        <span><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($PERMS['can_add'])): ?>
    <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">سجّل تسليمًا جديدًا</h3></div>
      <form method="post" class="box-body ems-form suph-grid">
        <?= csrf_field() ?>
        <div><label for="suph_from">الموردُ المسلِّم <span class="suph-req">*</span></label>
          <select name="from_container_id" id="suph_from" class="form-control" required>
            <option value="">— اختر —</option>
            <?php foreach ($containers as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['container_no'] . ' — ' . ($c['legal_name'] ?: 'مورد؟') . ' (متاح ' . number_format((float) $c['allocated_qty']) . ' ساعة)', ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label for="suph_to">الموردُ المستلِم <span class="suph-req">*</span></label>
          <select name="to_container_id" id="suph_to" class="form-control" required>
            <option value="">— اختر —</option>
            <?php foreach ($containers as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['container_no'] . ' — ' . ($c['legal_name'] ?: 'مورد؟'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label>الكميةُ المنقولة (ساعة) <span class="suph-req">*</span></label>
          <input type="number" step="0.01" min="0.01" name="moved_qty" class="form-control" required placeholder="مثال: 300 ساعة"></div>
        <div><label for="suph_eff">تاريخُ السريان <span class="suph-req">*</span></label>
          <input type="date" name="effective_from" id="suph_eff" class="form-control" required></div>
        <div><label>مستندُ التسليم <span class="suph-req">*</span></label>
          <input type="text" name="doc_ref" class="form-control" required placeholder="رقمُ المحضرِ أو الخطاب"></div>
        <div><label>السبب <span class="suph-req">*</span></label>
          <input type="text" name="reason" class="form-control" required placeholder="لماذا يُسلَّم؟"></div>
        <div class="suph-span-all"><button type="submit" class="btn btn-primary">احفظِ التسليم</button></div>
      </form>
    </div>
    <?php endif; ?>

    <div class="box"><div class="box-header with-border"><h3 class="box-title">آخرُ التسليمات</h3></div>
      <div class="box-body table-responsive">
        <?php if (!$swaps): ?>
          <p class="suph-empty-note">لا تسليماتَ بعدُ — أولُ تسليمٍ تسجّله يظهر هنا.</p>
        <?php else: ?>
        <table class="table table-bordered table-striped">
          <thead><tr><th>#</th><th>التاريخ</th><th>من</th><th>إلى</th><th>الكمية</th><th>المستند</th><th>السبب</th></tr></thead>
          <tbody>
          <?php foreach ($swaps as $w): ?>
            <tr>
              <td><?= (int) $w['id'] ?></td>
              <td><?= htmlspecialchars((string) $w['effective_from'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $w['from_no'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $w['to_no'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $w['moved_qty'] !== null ? number_format((float) $w['moved_qty'], 2) . ' ساعة' : '—' ?></td>
              <td><?= htmlspecialchars((string) ($w['doc_ref'] ?: '— (تاريخيٌّ قبل الإلزام)'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars(mb_substr((string) $w['reason'], 0, 60), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../infooter.php'; ?>
