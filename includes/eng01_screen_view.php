<?php
/**
 * eng01_screen_view.php — العرضُ المشترَكُ لشاشاتِ المحرّكاتِ الثماني
 * ───────────────────────────────────────────────────────────────────────────
 * يُضمَّن آخرَ الملفِّ (القسم ⑦) بعدَ أن يكون كلُّ ما قبله قد جرى: الجلسةُ
 * والإعدادُ وحارسُ الشاشةِ وحارسُ الفعلِ ورمزُ الحمايةِ ومعالجُ POST.
 *
 * يتوقّع من الشاشة: $PAGE_TITLE · $TILES · $COLS (أو null) · $rows
 * ويوفّر: $flash/$flashKind · csrf_field() في كلِّ نموذج · وزرَّ Excel المركزي.
 *
 * ولا يكتب شيئًا — عرضٌ محض.
 */
if (!isset($PAGE_TITLE)) { $PAGE_TITLE = 'شاشة'; }
if (!isset($TILES) || !is_array($TILES)) { $TILES = array(); }
if (!isset($COLS)) { $COLS = null; }

// ◆ الترويسةُ **والسايدبار** معًا — كما تفعل كلُّ شاشةٍ قائمة.
//   كان التضمينُ يقتصر على inheader فتخرج الشاشةُ بصفرِ روابطٍ ويقف فيها
//   المستخدمُ بلا مخرَج — وهو نقيضُ شرطِ «لا يسأل: أيَّ شاشةٍ أفتح؟».
$__inc = __DIR__ . '/../inheader.php';
if (is_readable($__inc)) { include $__inc; }
$__side = __DIR__ . '/../insidebar.php';
if (is_readable($__side)) { include $__side; }

/* UXW-01 §8-2: شريطُ رحلةِ الكيان — تضبطه الشاشةُ بـ$ENTITY_KEY (ومفتاحُ التبويبِ
   النشطِ $ENTITY_TAB) قبلَ تضمينِ الغلاف، والغلافُ يُخرجه بعدَ السايدبارِ كما
   تفعل الشاشاتُ القائمة. وضعُه في العُدَّةِ لا في الملفِّ يبقيه بعدَ إعادةِ التوليد. */
if (!empty($ENTITY_KEY)) {
    require_once __DIR__ . '/entity_tabs.php';
    echo ems_entity_tabs((string) $ENTITY_KEY, isset($ENTITY_TAB) ? (string) $ENTITY_TAB : '');
}

/* UXW-01 بوابة ٩: حالاتُ التحميلِ والفراغِ والخطأِ من المكوّنِ المركزيِّ —
   في الغلافِ لا في الشاشةِ فتستوفيها شاشاتُ ENG-01 كلُّها دفعةً واحدة
   (كما تفعل عُدّةُ u13_screen_kit) — والشاشةُ تخصِّص نصَّها بـ$EMPTY_TITLE/$EMPTY_HINT */
if (function_exists('ems_states_bundle')) {
    echo ems_states_bundle(
        isset($EMPTY_TITLE) ? $EMPTY_TITLE : 'لا توجد بياناتٌ لهذه الفترة',
        isset($EMPTY_HINT)  ? $EMPTY_HINT  : 'غيّر الفترةَ أو تحقق من توفرِ السجلات'
    );
}
?>
<?php
/* ═══════════════════════════════════════════════════════════════════════════
 * AC-U1 — **القشرةُ الموحَّدةُ والترويسةُ المركزية**
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ كشفه قياسٌ حيٌّ على `Governance/bus_board.php`: الشاشةُ تُصيَّر **بلا
 *   `.main_head` ولا `.ems-unified-page-shell` ولا عنوانِ صفحةٍ أصلًا** —
 *   فتبدو صفحةً عاريةً بعنوانٍ محليٍّ `<h4>` لا يشبه أختَها.
 * ◆ **وثمانُ شاشاتٍ تُصيَّر بهذه العُدّة**، فالإصلاحُ فيها **واحدٌ يعالج ثمانيًا**
 *   — لا ثمانيةُ ترقيعاتٍ يعود العيبُ بعد أولِ توليد.
 * ◆ **والنمطُ منسوخٌ حرفًا من عُدّةِ `u13_screen_kit`** (السطر 324) وشاشاتِ
 *   `dept_risk_space` — فلا يُخترع نمطٌ تاسعٌ لثامنِ شاشة.
 * ◆ **واسمُ الشاشةِ التقنيُّ (`$SCREEN`) لم يعد يُطبع**: بوابةُ اللغةِ U8 تمنع
 *   «مسارَ ملفٍّ أو معرِّفًا تقنيًّا» في واجهةٍ نهائية. وكان يُعرض `small` بجوارِ
 *   العنوان — فحُذف عرضُه، والقيمةُ باقيةٌ في المتغيّرِ لمن يحتاجها برمجيًّا.
 * ═══════════════════════════════════════════════════════════════════════════ */
$header_title   = $PAGE_TITLE;
$header_icon    = isset($SCREEN_ICON) ? $SCREEN_ICON : 'fa fa-diagram-project';
$header_actions = array();
$header_back    = false;
?>
<div class="main ems-unified-page-shell" dir="rtl">
<?php
$__ph = __DIR__ . '/page_header.php';
if (is_readable($__ph)) { include $__ph; }
?>
<div class="container-fluid ems-eng01" dir="rtl">

<?php if (!empty($flash)): ?>
  <div class="alert alert-<?= $flashKind === 'success' ? 'success' : ($flashKind === 'error' ? 'danger' : 'info') ?>">
    <?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if ($TILES): ?>
  <div class="row g-2 mb-3">
    <?php foreach ($TILES as $t): ?>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card h-100"><div class="card-body py-2 px-3">
          <div class="small text-muted"><?= htmlspecialchars((string) $t[0], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="fs-5 fw-bold"><?= htmlspecialchars((string) $t[1], ENT_QUOTES, 'UTF-8') ?></div>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($__canWrite) && !empty($ACTION_FORMS)): ?>
  <div class="card mb-3"><div class="card-body">
    <?= $ACTION_FORMS ?>
  </div></div>
<?php endif; ?>

  <div class="table-responsive">
    <table class="table table-sm table-striped table-hover align-middle" id="eng01Table">
      <thead class="table-light"><tr>
<?php
$headers = $COLS;
$firstRow = null;
$data = array();
if ($rows instanceof mysqli_result) {
    while ($r = $rows->fetch_assoc()) { $data[] = $r; }
}
if ($headers === null) {
    $headers = $data ? array_keys($data[0]) : array();
}
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
}
?>
      </tr></thead>
      <tbody>
<?php if (!$data): ?>
        <tr><td colspan="<?= max(1, count($headers)) ?>" class="text-center text-muted py-4">
          لا صفوفَ لعرضِها بعد
        </td></tr>
<?php else: foreach ($data as $r): ?>
        <tr>
<?php   foreach ($r as $v): ?>
          <td><?= $v === null ? '<span class="text-muted">—</span>'
                              : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?></td>
<?php   endforeach; ?>
        </tr>
<?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <p class="small text-muted mt-2">
    ◆ هذه الشاشةُ لا تكتب في جدولٍ مباشرةً — تنادي خدمةً والخدمةُ تكتب.
    وترتيبُ الحرّاسِ فيها: جلسة ← إعداد ← حارسُ شاشة ← حارسُ فعل ← رمزُ حماية ← معالجُ POST ← عرض.
  </p>
</div>
</div><!-- .main.ems-unified-page-shell — تُغلَق هنا لا في الشاشة، فالغلافُ
          يفتحها ويغلقها معًا. ووسمٌ مفتوحٌ في عُدّةٍ يُخرج المحتوى إلى `body`
          فينكمش العرض — عيبٌ مسجَّلٌ سابقًا في بطاقاتِ الكيانات. -->
<?php
$__foot = __DIR__ . '/../includes/footer.php';
if (is_readable($__foot)) { include $__foot; }
