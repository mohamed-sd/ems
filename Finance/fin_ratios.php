<?php
/**
 * Finance/fin_ratios.php — لوحة النسب المالية (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_ratios.php',
    'title' => 'لوحة النسب المالية',
    'icon' => 'fas fa-chart-line',
    'about' => 'أربعٌ وأربعون نسبةً في إحدى عشرةَ مجموعةً — محسوبةٌ من القيودِ لا من إدخالٍ يدويّ. وكلُّ نسبةٍ بحدِّ إنذارٍ وحدٍّ حرجٍ ومالكٍ ودورية، والرقمُ يفتح مصدرَه.',
    'notes' => array (
  0 => 'المقامُ صفرٌ ⇒ شرطةٌ لا صفرٌ كاذب — والحالةُ «غيرُ مقيسة»',
  1 => 'انقر النسبةَ لتتعمّق إلى حساباتِها ثم قيودِها ثم وقائعِها المصدر',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rows = array();
    $r = $conn->query("SELECT t.ratio_code, t.group_code, t.name_ar, t.unit_ar, t.limit_text,
                              t.cadence, t.owner_role, v.result_value, v.status_flag, v.trend_direction,
                              v.numerator_value, v.denominator_value
                         FROM fin_ratio_targets t
                         LEFT JOIN fin_ratio_values v
                                ON v.company_id = t.company_id AND v.ratio_code = t.ratio_code
                               AND v.period = '" . $conn->real_escape_string($period) . "'
                               AND v.scope_kind = 'company' AND v.state = 'computed'
                        WHERE t.company_id = {$company_id} AND t.active = 1
                        ORDER BY t.group_code, t.ratio_code");
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    $byFlag = array('ok' => 0, 'warn' => 0, 'critical' => 0, 'unmeasured' => 0);
    foreach ($rows as $x) { $f = $x['status_flag'] ?: 'unmeasured'; $byFlag[$f] = ($byFlag[$f] ?? 0) + 1; }
?>
    <div class="card"><div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <span class="badge badge-success" style="font-size:14px;padding:7px 12px">ضمن الحد: <strong><?php echo $byFlag['ok']; ?></strong></span>
        <span class="badge badge-warning" style="font-size:14px;padding:7px 12px">إنذار: <strong><?php echo $byFlag['warn']; ?></strong></span>
        <span class="badge badge-danger" style="font-size:14px;padding:7px 12px">حرج: <strong><?php echo $byFlag['critical']; ?></strong></span>
        <span class="badge badge-secondary" style="font-size:14px;padding:7px 12px">غير مقيسة: <strong><?php echo $byFlag['unmeasured']; ?></strong></span>
        <?php if ($can_write): ?>
        <button class="ems-btn-primary" onclick="faPost('ratio_compute', {})">احتساب النسب للفترة</button>
        <button class="ems-btn-secondary" onclick="faPost('signal_raise', {}, function(j){ alert('قواعدُ فُحصت: ' + j.checked + ' · إشاراتٌ رُفعت: ' + j.raised.length); location.reload(); })">تقييم إشارات الإنذار</button>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-body table-responsive">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>المجموعة</th><th>النسبة</th><th>القيمة</th><th>الوحدة</th>
                <th>الحالة</th><th>الاتجاه</th><th>الحد</th><th>الدورية</th><th>المالك</th>
                <th>البسط</th><th>المقام</th><th></th><th>الكيان</th><th>تاريخ القياس</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x):
                $flag = $x['status_flag'] ?: 'unmeasured';
                $cls = array('ok'=>'badge-success','warn'=>'badge-warning','critical'=>'badge-danger','unmeasured'=>'badge-secondary')[$flag];
                $lbl = array('ok'=>'ضمن الحد','warn'=>'إنذار','critical'=>'حرج','unmeasured'=>'غير مقيسة')[$flag];
                $tr = array('up'=>'▲','down'=>'▼','flat'=>'▬','na'=>'—')[$x['trend_direction'] ?: 'na'];
            ?>
                <tr>
                    <td style="font-family:monospace"><?php echo htmlspecialchars($x['ratio_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['group_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td><strong><?php echo $x['result_value'] !== null ? number_format((float)$x['result_value'], 2) : '—'; ?></strong></td>
                    <td><?php echo htmlspecialchars($x['unit_ar']); ?></td>
                    <td><span class="badge <?php echo $cls; ?>"><?php echo $lbl; ?></span></td>
                    <td><?php echo $tr; ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars($x['limit_text']); ?></td>
                    <td><?php echo htmlspecialchars($x['cadence']); ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars($x['owner_role']); ?></td>
                    <td><?php echo $x['numerator_value'] !== null ? number_format((float)$x['numerator_value'], 2) : '—'; ?></td>
                    <td><?php echo $x['denominator_value'] !== null ? number_format((float)$x['denominator_value'], 2) : '—'; ?></td>
                    <td><a class="btn-primary" href="fin_ratio_detail.php?ratio=<?php echo urlencode($x['ratio_code']); ?>&period=<?php echo urlencode($period); ?>">تعمّق ▸</a></td>
                    <td><?php echo (int) $company_id; ?></td>
                    <td><?php echo htmlspecialchars($period); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
<?php
}

require __DIR__ . '/../includes/fin_analysis_shell.php';
