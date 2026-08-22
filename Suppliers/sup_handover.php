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
/* CM-00 · UXR P4 — بذرُ محاورِ الغلافِ الحاكمِ من الخادمِ قبلَ التصيير، فيُقرأ
   نطاقُك ومدى صلاحيتِك ولحظةُ القراءةِ في سطرِ سياقِ الرأسِ الموحَّد. */
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($PERMS);
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier', 'العقودُ والحصص');
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell ems-doc-cycle" dir="rtl">
  <?php
  /* ══ الغلافُ والرأسُ الموحَّدان (توحيدُ التصميم 2026-08-17) ═════════════════
     كانت هذه الشاشةُ وحدَها في عائلةِ المورِّدِ تُصيَّر بقشرةِ `content-wrapper`
     و`box` الموروثةِ بدلَ `.main.ems-unified-page-shell` + `page_header.php`،
     ولم يكن الفارقُ شكلًا فحسب — ثلاثةُ مكوّناتٍ مركزيةٍ كانت تسقط صامتةً:
       ① **شريطُ رحلةِ المورِّد**: `ems_entity_tabs` تصفُّ ما نُودِي قبلَ الرأس
          ليصرفَه `page_header` في موضعِه؛ وبلا الرأسِ يبقى الطابورُ معلَّقًا،
          فيُبنى الشريطُ ولا يُطبع (المقيسُ قبلَ التوحيد: صفرُ `ems-entity-tabs`).
       ② **بطاقةُ «عن الشاشة»**: `ems-screen-about.js` يرسو على `.main_head`
          داخلَ `.main`، وبغيابِهما يبقى الـ`<template>` بلا زرٍّ ولا بطاقة.
       ③ **زرُّ «أبلغ عن مشكلة» الطافي**: يُحقن قبلَ `</body>` — و`</body>` لم
          تكن تُطبع أصلًا، لأن `infooter.php` المُضمَّنَ غيرُ موجودٍ في الشجرة. */
  $header_title   = 'تسليمُ الحصص بين الموردين';
  $header_icon    = 'fa fa-right-left';
  $header_actions = array();
  if (!empty($PERMS['can_add'])) {
      $header_actions[] = array('href' => 'javascript:void(0)', 'id' => 'toggleSuphForm',
          'icon' => 'fa fa-plus', 'label' => 'تسليمٌ جديد', 'class' => 'add-btn');
  }
  $header_back = array('href' => 'shares_coverage.php', 'class' => '',
                       'icon' => 'fas fa-arrow-right', 'label' => 'حصصُ الموردين والتغطية');
  
/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'capacity'; $sft_active = 'handover';
include __DIR__ . '/../includes/sales_family_tabs.php';
include __DIR__ . '/../includes/page_header.php';

  echo ems_next_step('تسجيلُ التسليمِ بمستندِه وتاريخِ سريانِه — والحصةُ تنتقل بين الحاويتين فورَ الحفظ');
  echo ems_states_bundle('لا تسليماتِ حصصٍ مسجَّلةً بعد', 'سجّلِ التسليمَ بمستندِه وتاريخِه فيظهر أثرُه هنا');

  /* لافتةُ النتيجةِ بلغةِ النظامِ الموحَّدة (ems-alerts.css) — النغمةُ من الصنف */
  if ($flash !== null) {
      echo '<div class="alert ' . ($flash_ok ? 'alert-success' : 'alert-danger') . '">'
         . '<span><strong>' . ($flash_ok ? 'نجح:' : 'توقف:') . '</strong> '
         . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</span></div>';
  }
  ?>

  <div class="alert alert-info suph-note">
    <i class="fa fa-circle-info"></i>
    <span>هنا نسجّل انتقالَ حصةِ ساعاتٍ من موردٍ إلى آخر — <strong>بمستندٍ وتاريخِ سريان</strong>،
      ولا يُمسُّ شهرٌ مغلق.</span>
  </div>

  <?php if (!empty($PERMS['can_add'])): ?>
  <?php /* النموذجُ مطويٌّ افتراضًا كنظائرِه، ويفتحه زرُّ «+» في الرأس. ويُفتح
           ابتداءً إن ردَّ الحفظُ بخطأٍ — فيرى المستخدمُ موضعَ الخللِ لا شاشةً فارغة. */ ?>
  <form method="post" id="suphForm"
        class="allforms<?= ($flash !== null && !$flash_ok) ? ' allforms-visible' : '' ?>">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header"><h5><i class="fa fa-right-left"></i> سجّل تسليمًا جديدًا</h5></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group">
            <label for="suph_from"><i class="fas fa-truck-arrow-right"></i> الموردُ المسلِّم <span class="suph-req">*</span></label>
            <select name="from_container_id" id="suph_from" required>
              <option value="">— اختر —</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['container_no'] . ' — ' . ($c['legal_name'] ?: 'مورد؟') . ' (متاح ' . number_format((float) $c['allocated_qty']) . ' ساعة)', ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="suph_to"><i class="fas fa-truck-ramp-box"></i> الموردُ المستلِم <span class="suph-req">*</span></label>
            <select name="to_container_id" id="suph_to" required>
              <option value="">— اختر —</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['container_no'] . ' — ' . ($c['legal_name'] ?: 'مورد؟'), ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="suph_qty"><i class="fas fa-hourglass-half"></i> الكميةُ المنقولة (ساعة) <span class="suph-req">*</span></label>
            <input type="number" step="0.01" min="0.01" name="moved_qty" id="suph_qty" required placeholder="مثال: 300">
          </div>
          <div class="form-group">
            <label for="suph_eff"><i class="fas fa-calendar-day"></i> تاريخُ السريان <span class="suph-req">*</span></label>
            <input type="date" name="effective_from" id="suph_eff" required>
          </div>
          <div class="form-group">
            <label for="suph_doc"><i class="fas fa-file-signature"></i> مستندُ التسليم <span class="suph-req">*</span></label>
            <input type="text" name="doc_ref" id="suph_doc" required placeholder="رقمُ المحضرِ أو الخطاب">
          </div>
          <div class="form-group">
            <label for="suph_reason"><i class="fas fa-comment-dots"></i> السبب <span class="suph-req">*</span></label>
            <input type="text" name="reason" id="suph_reason" required placeholder="لماذا يُسلَّم؟">
          </div>
        </div>
        <div class="pu-form-actions">
          <button type="submit" class="btn-primary"><i class="fas fa-save"></i> احفظِ التسليم</button>
          <button type="button" id="suphCancel" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</button>
        </div>
      </div>
    </div>
  </form>
  <?php endif; ?>

  <div class="card">
    <div class="card-header"><h5><i class="fas fa-list"></i> آخرُ التسليمات</h5></div>
    <div class="card-body">
      <div class="table-container">
        <?php /* الجدولُ يُصيَّر دائمًا — والفراغُ تحمله رسالةُ العُدَّةِ المعرَّبةُ
                 نفسُها التي في كلِّ جداولِ النظام، لا فقرةٌ محليةٌ تخصُّ شاشةً. */ ?>
        <table class="alltables display nowrap suph-table">
          <thead>
            <tr>
              <th>#</th>
              <th>التاريخ</th>
              <th>من</th>
              <th>إلى</th>
              <th>الكمية</th>
              <th>المستند</th>
              <th>السبب</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr>
          </thead>
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
      </div>
    </div>
  </div>
</div>

<script>
/* طيُّ النموذجِ وفتحُه — الاصطلاحُ الموحَّد: `.allforms` مطويٌّ و`.allforms-visible` يفتح */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('toggleSuphForm');
        var frm = document.getElementById('suphForm');
        if (btn && frm) {
            btn.addEventListener('click', function () { frm.classList.toggle('allforms-visible'); });
        }
        var cancel = document.getElementById('suphCancel');
        if (cancel && frm) {
            cancel.addEventListener('click', function () { frm.classList.remove('allforms-visible'); });
        }
    });
})();
</script>
</body>
</html>
