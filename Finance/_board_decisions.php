<?php
/**
 * Finance/_board_decisions.php — «جدولُ القرارِ اليومي» (§12.5)
 * ═══════════════════════════════════════════════════════════════════════════
 * كان في جسدِ `Finance/cfo_daily_board_fin.php`، ولمّا نُقلت اللوحةُ إلى
 * «الرئيسية» (قرارُ المالك 2026-08-21) كاد يسقط — فاستُخرج جزءًا يُعلَن في
 * إعدادِ الدور (`view`) ويُصيَّر داخلَ اللوحة. صفوفُه الخمسةُ هي هي، وأرقامُه
 * تُقرأ من بطاقاتِ اللوحةِ نفسِها فلا يُحسب رقمٌ مرتين ولا يتفرّق قارئان.
 *
 * يتوقّع: $dash_board (مخرَجُ roleBoardBuild).
 */
if (empty($dash_board['cards'])) { return; }

/** قيمةُ بطاقةٍ بعنوانها كما عُرضت (نصًّا منسَّقًا) — لا إعادةَ استعلام. */
$finCard = function ($label) use ($dash_board) {
    foreach ($dash_board['cards'] as $c) {
        if ($c['label'] === $label) { return $c['display']; }
    }
    return '—';
};

$finRows = array(
    array('ماذا نصرف اليوم؟', 'المسوّى الجاهز (' . $finCard('المسوّى الجاهز للصرف') . ') مقابل النقد (' . $finCard('النقد المتاح (البنوك)') . ')', '../Finance/payments_fin.php', 'المدفوعات'),
    array('ماذا نحصّل اليوم؟', 'الذمم المتأخرة (' . $finCard('الذمم المتأخرة') . ')', '../Finance/dues_fin.php', 'الذمم'),
    array('هل نحتاج تمويلًا؟', 'صافي الأسبوع (' . $finCard('صافي الأسبوع المتوقّع') . ') وأقساط 7 أيام (' . $finCard('أقساط تمويل خلال 7 أيام') . ')', '../Finance/cash_forecast_fin.php', 'السيولة'),
    array('هل التشغيل يربح؟', 'هامش الوحدة الجاري (' . $finCard('هامش الوحدة الجاري') . ')', '../Finance/unit_records_fin.php', 'كشف الوحدات'),
    array('أين نتدخّل؟', 'انحرافات فوق الحد (' . $finCard('انحرافات فوق 10%') . ')', '../Finance/budget_form_fin.php', 'الميزانيات'),
);
?>
<div class="shot-ops-box shot-ops-wide">
  <h4 class="shot-ops-box-title"><i class="fas fa-clipboard-check"></i> جدول القرار اليومي</h4>
  <div class="table-container">
    <table class="alltables" data-no-dt="hard">
      <thead><tr><th>القرار</th><th>المؤشر</th><th>أين يُتّخذ</th></tr></thead>
      <tbody>
        <?php foreach ($finRows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r[1], ENT_QUOTES, 'UTF-8') ?></td>
          <td><a href="<?= htmlspecialchars($r[2], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r[3], ENT_QUOTES, 'UTF-8') ?></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
