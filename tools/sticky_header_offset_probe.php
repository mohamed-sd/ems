<?php
/**
 * tools/sticky_header_offset_probe.php — مِحكُّ إزاحةِ الترويسةِ اللاصقة.
 * يُعيدُ بناءَ بنيةِ `Suppliers/supplierscontracts_details.php` حرفيًّا
 * (‎.table-responsive-wrapper > table.modern-table‎) ويحمّل أوراقَ النظامِ نفسَها،
 * ثم يقيسُ إزاحةَ خليةِ الترويسةِ عن صفِّها.
 * لماذا مِحكٌّ لا قياسٌ على الشاشةِ نفسِها؟ لأن `Suppliers/supplierscontracts_details.php`
 * محجوبةٌ عن بعضِ الأدوار، فتعذّر قياسُها حيًّا. والمِحكُّ يُعيد بناءَ بنيتِها حرفيًّا
 * ويحمّل أوراقَ النظامِ نفسَها — فيقيس ما كان سيُقاس عليها.
 *
 * جُرِّب في الاتجاهَين: بإلغاءِ التصفيرِ على الغلافِ تعود الإزاحةُ 56 ويقع التراكب،
 * وبإعادتِه تصير 0 بلا تراكب. فالمِحكُّ يلتقط العطلَ ولا يكتفي بتأكيدِ السلامة.
 *
 * الاستعمال: افتح /ems/tools/sticky_header_offset_probe.php — المتوقَّع offset = 0.
 * ولمحاكاةِ ما قبلَ الإصلاح من الطرفيّة:
 *   document.querySelector(.table-responsive-wrapper)
 *     .style.setProperty(--ems-sticky-top,56px); __probe()
 */
$css = dirname(__FILE__) . '/assets/css/';
$v = function ($f) use ($css) { return is_file($css . $f) ? ('?v=' . filemtime($css . $f)) : ''; };
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>مِحكُّ إزاحةِ الترويسةِ اللاصقة</title>
<link rel="stylesheet" href="/ems/assets/css/design-tokens.css<?php echo $v('design-tokens.css'); ?>">
<link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css<?php echo $v('ems.main.all.style.css'); ?>">
<link rel="stylesheet" href="/ems/assets/css/ems-screens.css<?php echo $v('ems-screens.css'); ?>">
<link rel="stylesheet" href="/ems/assets/css/ems-tables.css<?php echo $v('ems-tables.css'); ?>">
<style>
  /* ما يضبطه ui-unification.js وقتَ التشغيل من ارتفاعِ الشريطِ العلويّ */
  :root { --ems-sticky-top: 56px; }
  body { margin: 0; padding-top: 56px; }
  .fake-topbar { position: fixed; top: 0; inset-inline: 0; height: 56px; background: #e2e2e2; }
</style>
</head>
<body class="ems-site">
<div class="fake-topbar"></div>

<!-- بنيةُ التوأمِ حرفيًّا: Suppliers/supplierscontracts_details.php:487 -->
<div class="main contracts-main contracts-details-page ems-unified-page-shell">
  <div class="page-wrapper">
    <div class="table-section">
      <div class="table-responsive-wrapper">
        <table class="modern-table alltables alltable" data-no-dt="1" id="probeTable">
          <thead><tr>
            <th class="sorting">#</th><th class="sorting">نوع المعدة</th><th class="sorting">الحجم</th>
          </tr></thead>
          <tbody>
            <tr><td>1</td><td>قلاب</td><td>300</td></tr>
            <tr><td>2</td><td>حفار</td><td>420</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<pre id="out" style="padding:16px;font:14px/1.7 monospace;direction:ltr;text-align:left"></pre>
<script>
(function () {
  function measure() {
    var t = document.getElementById('probeTable');
    var tr = t.tHead.rows[0], th = tr.cells[0];
    var body = t.tBodies[0].rows[0];
    return {
      stickyTopVar: getComputedStyle(document.documentElement).getPropertyValue('--ems-sticky-top').trim(),
      thResolvedTop: getComputedStyle(th).top,
      offset: Math.round(th.getBoundingClientRect().top - tr.getBoundingClientRect().top),
      overlapsFirstRow: Math.round(th.getBoundingClientRect().bottom) > Math.round(body.getBoundingClientRect().top),
      headBg: getComputedStyle(th).backgroundColor,
      headColor: getComputedStyle(th).color
    };
  }
  window.__probe = measure;
  document.getElementById('out').textContent = JSON.stringify(measure(), null, 2);
})();
</script>
</body>
</html>
