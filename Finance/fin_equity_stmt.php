<?php
/**
 * Finance/fin_equity_stmt.php — قائمة التغيرات في حقوق الملكية (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_equity_stmt.php',
    'title' => 'قائمة التغيرات في حقوق الملكية',
    'icon' => 'fas fa-scale-balanced',
    'about' => 'الرصيدُ الافتتاحيُّ ثم الحركاتُ ثم الختاميُّ لكل بند — والختاميُّ = الافتتاحيُّ + الحركاتُ أو تُرفض.',
    'notes' => array (
  0 => 'رأسُ المالِ والاحتياطياتُ والمحتجزةُ والتوزيعاتُ من الجذر 3',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rows = array();
    $r = $conn->query("SELECT * FROM fin_equity WHERE company_id = {$company_id}
                         AND period = '" . $conn->real_escape_string($period) . "' AND state = 'generated'
                       ORDER BY component_code");
    if ($r) { while ($x = $r->fetch_assoc()) { $rows[] = $x; } }
?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php echo ems_states_bundle('لا بياناتٍ لهذه الفترةِ المالية', 'غيّر الفترةَ أو تحقق من ترحيلِ القيود'); ?>
    <?php if ($can_write): ?>
    <div class="card"><div class="card-body">
        <button class="ems-btn-primary" onclick="faPost('equity_generate', {}, function(j){ alert('وُلّدت ' + j.eq_code + ' · بنود: ' + j.components + ' — متوازنة ✔'); location.reload(); })">توليد القائمة للفترة</button>
        <span class="fa-note">◆ بندٌ لا يتوازن يوقف التوليدَ برمزٍ يسمّيه</span>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-body table-responsive">
        <?php if (!$rows): ?>
            <?php ems_state_empty('لا قائمةَ حقوقِ ملكيةٍ لهذه الفترة'); ?>
        <?php else: ?>
        <table class="alltables display nowrap fa-table-full">
            <thead><tr><th>الرقم</th><th>البند</th><th>الاسم</th><th>الرصيد الافتتاحي</th>
                <th>الإضافات</th><th>الخصومات</th><th>التحويلات</th><th>الرصيد الختامي</th>
                <th>المحسوب</th><th>متوازن؟</th><th>الفترة</th><th>العملة</th><th>مرجع التفويض</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['eq_code']); ?></td>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['component_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['component_name']); ?></td>
                    <?php foreach (array('opening_balance','additions','deductions','transfers',
                        'closing_balance','computed_closing') as $f): ?>
                    <td><?php echo number_format((float)$x[$f],2); ?></td>
                    <?php endforeach; ?>
                    <td><span class="badge <?php echo (int)$x['balance_ok']?'badge-success':'badge-danger'; ?>"><?php echo (int)$x['balance_ok']?'✔':'✘'; ?></span></td>
                    <td><?php echo htmlspecialchars($x['period']); ?></td>
                    <td><?php echo htmlspecialchars($x['currency']); ?></td>
                    <td class="fa-fs72"><?php echo htmlspecialchars($x['authority_ref']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
<?php
}

/* القشرةُ الموحَّدةُ هي التي تُنفِّذ include '../inheader.php' ثم insidebar بعد الحارسِ (البوابة ٤) */
require __DIR__ . '/../includes/fin_analysis_shell.php';
