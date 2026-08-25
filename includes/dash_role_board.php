<?php
/**
 * includes/dash_role_board.php — لوحةُ الدورِ داخلَ «الرئيسية» (UX-01 §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * `main/dashboard.php` صارت لوحةَ إدارةِ التشغيل بدلَ `main/role_board.php`
 * (قرار المالك 2026-08-21). والمكوّناتُ السبعةُ هي هي — المحتوى من المحرّك
 * الموحّد `roleBoardBuild()` لا من نسخةٍ ثانية — وإنما يتبدّل **الثوب**:
 *
 *   • `role_board_widgets.php` يصيّرها بـ`card`/`badge` وألوانٍ مثبَّتةٍ في
 *     السطر — وهو ثوبُ اللوحةِ العامة.
 *   • هذا القالبُ يصيّرها بـ`shot-ops-*` (assets/css/ems-screens.css) —
 *     أبيضُ بحدِّ `--c-cfcfcf` واستدارةِ 20 كبطاقاتِ الرسمِ في اللوحة،
 *     والتمييزُ `--dash-yellow` كما في شرائحِ الجلسة. فلا ينكسر تصميمُها.
 *
 * ◆ مؤشراتُ ① تُصيَّر بمكوّنِ `ems_kpi_card` نفسِه الذي تستعمله اللوحةُ العامة
 *   (SH-09: سبعةُ حقولٍ إلزامًا · لا رقمَ بلا تعمّق)، فينالها الشكلُ الموحَّدُ
 *   الذي ينال بطاقاتِ `.shot-stat-card` في هذه الصفحةِ عبر `ems-statcards.js`.
 *
 * يتوقّع: $dash_board (مخرَجُ roleBoardBuild) · اختياريًّا $today.
 * ⑥ نبضُ الأداءِ و⑦ عملي الأخير يُصيَّران في شبكةِ الرسومِ من الصفحةِ نفسِها.
 */
if (empty($dash_board)) { return; }
require_once __DIR__ . '/kpi_card.php';

$dash_ops_today = isset($today) ? $today : date('Y-m-d');

/* عدَدُ أعمدةِ المؤشرات: صفٌّ واحدٌ كاملٌ ما دام العدَدُ خمسةً فأقل، وإلا
   أربعةٌ — وهو إيقاعُ الصفحةِ نفسُه (الأرقامُ الكبيرةُ والصناديقُ والبلاطاتُ
   وشرائحُ الجلسةِ أربعةٌ). والمقيسُ: سبعةُ مؤشراتِ الموارد البشرية كانت
   تُصَفُّ 5+2 فتُخلّف ثلاثَ خاناتٍ فارغةً في السطرِ الثاني — وبأربعةٍ تصير
   4+3 بخانةٍ واحدة. (والمدى 2..6 هو ما يفهمه `data-cols` في ems-statcards.css.) */
$dash_ops_n    = count($dash_board['cards']);
$dash_ops_cols = ($dash_ops_n <= 1) ? 2 : (($dash_ops_n <= 5) ? $dash_ops_n : 4);
?>
<section class="shot-ops" aria-label="<?= htmlspecialchars($dash_board['title'], ENT_QUOTES, 'UTF-8') ?>">
  <div class="shot-ops-head">
    <h3 class="shot-ops-title">
      <i class="<?= htmlspecialchars($dash_board['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
      <?= htmlspecialchars($dash_board['title'], ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <span class="shot-ops-note">
      <i class="fas fa-mug-hot"></i>
      أسئلة أول اليوم لدورك — اضغط أي رقم لفتح مصدره (<?= htmlspecialchars($dash_ops_today, ENT_QUOTES, 'UTF-8') ?>)
    </span>
  </div>

  <?php /* ① مؤشراتُ اليوم — عقدُ البطاقةِ السباعيُّ كاملًا، والوحدةُ والفترةُ
           حقيقيّتان: الاستعلاماتُ COUNT فالوحدةُ «سجل»، وتُقرأ الآنَ فالفترةُ
           «لحظي». والمقارنةُ تُعلن غيابَها ولا تُلفَّق. */ ?>
  <div class="shot-ops-kpis" data-cols="<?= (int) $dash_ops_cols ?>">
    <?php foreach ($dash_board['cards'] as $c):
      echo ems_kpi_card(array(
        'title'  => $c['label'],
        'value'  => $c['display'],
        'unit'   => $c['unit'],
        'period' => $c['period'],
        'status' => in_array($c['tone'], array('ok', 'warn', 'err'), true) ? $c['tone'] : 'neutral',
        'drill'  => $c['href'],
        'icon'   => $c['icon'],
        'scope'  => $c['scope'],
      ));
    endforeach; ?>
  </div>

  <div class="shot-ops-grid">

    <?php /* ② مهامي — ما يجب فعلُه الآن، والصفريُّ اختفى في المحرّك */ ?>
    <div class="shot-ops-box">
      <h4 class="shot-ops-box-title"><i class="fas fa-list-check"></i> مهامي</h4>
      <?php if (empty($dash_board['tasks'])): ?>
        <p class="shot-ops-empty">لا مهام عاجلة الآن ✔</p>
      <?php else: foreach ($dash_board['tasks'] as $t): ?>
        <a class="shot-ops-row" href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="shot-ops-row-text">
            <i class="<?= htmlspecialchars($t['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span><?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="shot-ops-num"><?= (int) $t['count'] ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <?php /* ③ موافقاتي — العدّادُ من شاراتِ السايدبار نفسِها (تعريفٌ واحد) */ ?>
    <div class="shot-ops-box">
      <h4 class="shot-ops-box-title"><i class="fas fa-inbox"></i> موافقاتي</h4>
      <?php if (empty($dash_board['approvals'])): ?>
        <p class="shot-ops-empty">لا شيء ينتظر اعتمادك ✔</p>
      <?php else: foreach ($dash_board['approvals'] as $a): ?>
        <a class="shot-ops-row" href="<?= htmlspecialchars($a['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="shot-ops-row-text">
            <i class="<?= htmlspecialchars($a['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span><?= htmlspecialchars($a['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="shot-ops-num is-warn"><?= (int) $a['count'] ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <?php /* ④ التنبيهات — لا تنبيهَ بلا سببٍ وقفزة (UX-01 §9) */ ?>
    <div class="shot-ops-box">
      <h4 class="shot-ops-box-title"><i class="fas fa-bell"></i> التنبيهات</h4>
      <?php if (empty($dash_board['alerts'])): ?>
        <p class="shot-ops-empty">لا متأخر ولا حرج الآن ✔</p>
      <?php else: foreach ($dash_board['alerts'] as $al):
        $alCls = ($al['tone'] === 'err') ? 'is-err' : 'is-warn'; ?>
        <a class="shot-ops-row" href="<?= htmlspecialchars($al['href'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($al['label'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="shot-ops-row-text">
            <i class="fas fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($al['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </span>
          <span class="shot-ops-num <?= $alCls ?>"><?= (int) $al['count'] ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <?php /* ⑤ إنشاء سريع — أكثرُ ثلاثِ عملياتٍ استعمالًا لهذا المستخدم */ ?>
    <div class="shot-ops-box">
      <h4 class="shot-ops-box-title"><i class="fas fa-bolt"></i> إنشاء سريع</h4>
      <?php if (empty($dash_board['quick'])): ?>
        <p class="shot-ops-empty">لا عمليات يومية مسجلة لدورك بعد</p>
      <?php else: foreach ($dash_board['quick'] as $qk): ?>
        <a class="shot-ops-act" href="../<?= htmlspecialchars($qk['route'], ENT_QUOTES, 'UTF-8') ?>">
          <i class="<?= htmlspecialchars(!empty($qk['icon']) ? $qk['icon'] : 'fa fa-link', ENT_QUOTES, 'UTF-8') ?>"></i>
          <span><?= htmlspecialchars($qk['label_ar'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      <?php endforeach; endif; ?>
    </div>

  </div>

  <?php
  /* جزءٌ خاصٌّ باللوحةِ إن أعلنته في إعدادها (`view`): ما لا يسعه نحوُ المكوّنات
     السبعةِ ولا يجوز فقدُه عند النقل — «جدولُ القرارِ اليومي» للمالية،
     و«معداتٌ بانتظارِ قطع» للمشتريات. المسارُ من جذرِ التطبيق، والإعلانُ
     في الإعدادِ لا في القالب — فيبقى القالبُ عامًّا لا يعرف دورًا بعينه. */
  if (!empty($dash_board['view'])) {
      $dash_view_file = dirname(__DIR__) . '/' . $dash_board['view'];
      if (is_file($dash_view_file)) { include $dash_view_file; }
  }
  ?>
</section>
