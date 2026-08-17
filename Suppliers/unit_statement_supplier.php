<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');

/* كشفُ وحداتِ الموردِ النطاقي (unit.stmt.supplier) — قراءةٌ محضة: الأداءُ من
   v_monthly_performance بالموردِ، والتسويةُ من settlements (F-07/F-08). */
$company_id = (int) ($_SESSION['user']['company_id'] ?? 0);
$rows = array();
$st = $conn->prepare("SELECT v.period, v.supplier_entity_id, s.name supplier_name,
                             SUM(v.run_hours) run_hours, SUM(v.standby_hours) standby_hours,
                             SUM(v.supplier_liable_hours) supplier_liable, SUM(v.days_worked) days_worked
                      FROM v_monthly_performance v
                      LEFT JOIN suppliers s ON s.id = v.supplier_entity_id
                      WHERE v.company_id = ? AND v.supplier_entity_id IS NOT NULL
                      GROUP BY v.period, v.supplier_entity_id, s.name
                      ORDER BY v.period DESC LIMIT 200");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$settle = array();
$st = $conn->prepare("SELECT settlement_no, party_name, period_from, period_to, client_executed_hours,
                             supplier_executed_hours, borne_by_treasury, state
                      FROM settlements
                      WHERE company_id = ? AND is_deleted = 0 AND party_type = 'supplier'
                      ORDER BY id DESC LIMIT 40");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $settle[] = $x; }
$st->close();

$page_title = 'إيكوبيشن | كشفُ وحداتِ المورد';
/* CM-00 · UXR P4 — بذرُ محاورِ الغلافِ الحاكمِ من الخادمِ قبلَ التصيير، فيُقرأ
   نطاقُك ومدى صلاحيتِك ولحظةُ القراءةِ في سطرِ سياقِ الرأسِ الموحَّد.
   ويُمرَّر `null` عمدًا: المحرّكُ يحلُّ الصلاحيةَ من مسارِ السكربتِ بنفسِه
   (INJ-0547 · INJ-0572) — فالتمريرُ اليدويُّ لا يزيد عليه شيئًا. */
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('supplier', 'التنفيذُ والاستحقاق');
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main ems-unified-page-shell" dir="rtl">
  <?php
  /* ══ الغلافُ والرأسُ الموحَّدان (توحيدُ التصميم 2026-08-17) ═════════════════
     كانت هذه الشاشةُ تُصيَّر بقشرةِ `content-wrapper` و`box` الموروثةِ بدلَ
     `.main.ems-unified-page-shell` + `page_header.php` — ولم يكن الفارقُ شكلًا
     فحسب؛ مكوّناتٌ مركزيةٌ كانت تسقط صامتةً (مقيسٌ في المُصيَّرِ قبلَ التوحيد):
       ① **شريطُ رحلةِ المورِّد**: `ems_entity_tabs` **تصفُّ** ما نُودِي قبلَ
          الرأسِ ليصرفَه `page_header.php` في موضعِه (`ems_entity_tabs_flush()`).
          وبلا رأسٍ يبقى الطابورُ معلَّقًا — فيُبنى الشريطُ ولا يُطبع.
          المقيسُ قبلَ التوحيد: **صفرُ `ems-entity-tabs`** في الصفحةِ المُصيَّرة.
       ② **بطاقةُ «عن الشاشة»**: `ems-screen-about.js` يرسو على `.main_head`
          داخلَ `.main` — وبغيابِهما يبقى الـ`<template>` بلا زرٍّ ولا بطاقة.
       ③ **الجداول**: بلا `.alltables` و`.table-container` لا تنالُ الشاشةُ
          عُدَّةَ الجداولِ الموحَّدةَ (ترشيحٌ · ترتيبٌ · اختيارُ أعمدةٍ · زرُّ
          Excel المركزي) ولا طبقةَ الحوكمةِ CMP-03.
     ⇐ المرجعُ الاصطلاحي: `Suppliers/sup_handover.php` (حُوِّلت 2026-08-17)
       و`Contracts/client_statement.php` — توأمُها في جانبِ العميل. */
  $header_title   = 'كشفُ وحداتِ المورد';
  $header_icon    = 'fa fa-file-invoice';
  $header_actions = array();
  $header_back    = array('href' => 'suppliers.php', 'class' => '',
                          'icon' => 'fas fa-arrow-right', 'label' => 'الموردون');
  include __DIR__ . '/../includes/page_header.php';

  echo ems_next_step('اقرأ أداءَ الموردِ شهرًا شهرًا ثم طابقْه بتسويتِه — والمتحمَّلُ من الخزينةِ هو ما لم يُحمَّل على أحد');
  echo ems_states_bundle('لا قيودَ منسوبةً لموردين ضمنَ هذا النطاق', 'سجِّل القيدَ اليوميَّ منسوبًا لمورده ثم عُد لهذا الكشف');
  ?>

  <div class="alert alert-info uss-note">
    <i class="fa fa-circle-info"></i>
    <span>أداءُ كلِّ موردٍ شهرًا شهرًا <strong>من القيدِ اليومي</strong> — وتسوياتُه
      <strong>بالمنفَّذِ المحسوبِ والمتحمَّلِ من الخزينة</strong>. قراءةٌ محضةٌ: لا يُكتب من هنا شيء.</span>
  </div>

  <div class="card">
    <div class="card-header"><h5><i class="fas fa-chart-column"></i> الأداءُ الشهري (من القيدِ اليومي)</h5></div>
    <div class="card-body">
      <div class="table-container">
        <?php /* الجدولُ يُصيَّر دائمًا — والفراغُ تحمله رسالةُ العُدَّةِ المعرَّبةُ
                 نفسُها التي في كلِّ جداولِ النظام، لا فقرةٌ محليةٌ تخصُّ شاشةً. */ ?>
        <table class="alltables display nowrap uss-table">
          <thead>
            <tr>
              <th>الشهر</th>
              <th>المورد</th>
              <th>تشغيلٌ (ساعة)</th>
              <th>استعدادٌ (ساعة)</th>
              <th>تعطلٌ يتحمّله (ساعة)</th>
              <th>أيامُ العمل</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['period'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($r['supplier_name'] ?: ('#' . $r['supplier_entity_id'])), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= number_format((float) $r['run_hours'], 1) ?> ساعة</td>
              <td><?= number_format((float) $r['standby_hours'], 1) ?> ساعة</td>
              <td><?= number_format((float) $r['supplier_liable'], 1) ?> ساعة</td>
              <td><?= (int) $r['days_worked'] ?> يومًا</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <?php /* النصُّ الثانويُّ في الرأسِ يُكتب سطرًا واحدًا كما في
             `Contracts/client_statement.php` — لا صنفًا محليًّا بلا تعريف. */ ?>
    <div class="card-header"><h5><i class="fas fa-scale-balanced"></i> التسويات
      — F-07 منفَّذُ الموردِ · F-08 المتحمَّلُ من الخزينة</h5></div>
    <div class="card-body">
      <div class="table-container">
        <table class="alltables display nowrap uss-table">
          <thead>
            <tr>
              <th>التسوية</th>
              <th>المورد</th>
              <th>الفترة</th>
              <th>منفَّذُ العميل (ساعة)</th>
              <th>منفَّذُ المورد (ساعة)</th>
              <th>المتحمَّلُ من الخزينة (ساعة)</th>
              <th>الحالة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($settle as $r): ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['settlement_no'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $r['party_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['period_from'] . ' ← ' . $r['period_to'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= number_format((float) $r['client_executed_hours'], 1) ?> ساعة</td>
              <td><?= number_format((float) $r['supplier_executed_hours'], 1) ?> ساعة</td>
              <td><?php $b = (float) $r['borne_by_treasury']; ?>
                  <span class="badge <?= $b > 0 ? 'badge-danger' : 'badge-success' ?>">
                    <?= $b > 0 ? ('⚠ ' . number_format($b, 1) . ' ساعة خسارة') : '✔ صفرٌ — لا خسارة' ?></span></td>
              <td><span class="badge badge-info"><?= htmlspecialchars((string) $r['state'], ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
