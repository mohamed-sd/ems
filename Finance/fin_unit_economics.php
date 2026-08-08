<?php
/**
 * Finance/fin_unit_economics.php — ربحية المعدة الواحدة (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_unit_economics.php',
    'title' => 'ربحية المعدة الواحدة',
    'icon' => 'fas fa-truck-monster',
    'about' => 'إيرادُ المعدةِ وتكلفتُها وهامشُها وتكلفةُ ساعتِها — بالبُعد D5 المعدة. والمعدةُ بهامشٍ سالبٍ ثلاثةَ أشهرٍ تُنشر إشارةً للأسطولِ والتشغيل.',
    'notes' => array (
  0 => 'الهامشُ محسوبٌ من الاعترافاتِ لا من تقديرٍ يدوي',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $eqs = array();
    $r = $conn->query("SELECT DISTINCT l.equipment_id, e.equipment_code, e.equipment_name
                         FROM fin_journal_lines l
                         LEFT JOIN equipments e ON e.id = l.equipment_id
                        WHERE l.company_id = {$company_id} AND l.equipment_id IS NOT NULL
                        ORDER BY l.equipment_id LIMIT 200");
    if ($r) { while ($x = $r->fetch_assoc()) { $eqs[] = $x; } }
    $rows = array();
    foreach ($eqs as $e) {
        $m = \App\Services\Finance\FinAnalysisService::margins($conn, $company_id, $period,
            array('equipment_id' => (int) $e['equipment_id']));
        $rows[] = array('eq' => $e, 'm' => $m);
    }
?>
    <div class="card"><div class="card-body table-responsive">
        <?php if (!$rows): ?>
            <?php ems_state_empty('لا معداتٍ لها قيودٌ في هذه الفترة — الربحيةُ تُقاس بعد أولِ قيدٍ بالبُعد D5'); ?>
        <?php else: ?>
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>المعدة</th><th>الكود</th><th>الإيراد الصافي M1</th><th>الهامش الإجمالي M2</th>
                <th>الربح التشغيلي M3</th><th>قبل الضريبة M4</th><th>الصافي M5</th>
                <th>نسبة الهامش</th><th>الحالة</th><th>الفترة</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x):
                $m1 = $x['m']['M1']['value']; $m2 = $x['m']['M2']['value']; $m5 = $x['m']['M5']['value'];
                $pct = ($m1 !== null && abs($m1) > 0.0001 && $m2 !== null) ? round($m2 / $m1 * 100, 2) : null;
                $neg = ($m2 !== null && $m2 < 0);
            ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($x['eq']['equipment_name'] ?? '—')); ?></td>
                    <td style="font-family:monospace"><?php echo htmlspecialchars((string)($x['eq']['equipment_code'] ?? $x['eq']['equipment_id'])); ?></td>
                    <?php foreach (array('M1','M2','M3','M4','M5') as $k): $v = $x['m'][$k]['value']; ?>
                    <td><?php echo $v !== null ? number_format($v, 2) : '—'; ?></td>
                    <?php endforeach; ?>
                    <td><?php echo $pct !== null ? $pct . '٪' : '—'; ?></td>
                    <td><span class="badge <?php echo $neg ? 'badge-danger' : 'badge-success'; ?>"><?php echo $neg ? 'هامش سالب' : 'موجب'; ?></span></td>
                    <td><?php echo htmlspecialchars($period); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
<?php
}

require __DIR__ . '/../includes/fin_analysis_shell.php';
