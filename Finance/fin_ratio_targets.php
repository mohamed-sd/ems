<?php
/**
 * Finance/fin_ratio_targets.php — حدود النسب وأهدافها (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_ratio_targets.php',
    'title' => 'حدود النسب وأهدافها',
    'icon' => 'fas fa-bullseye',
    'about' => 'لكل نسبةٍ حدُّ إنذارٍ وحدٌّ حرجٌ وهدفٌ ومالكٌ ودورية — ولا نسبةَ تُعرض بلا حد. واعتمادُ الحدِّ لنائبِ الرئيسِ للشؤون المالية والاستثمار.',
    'notes' => array (
  0 => 'تعديلُ الحدِّ نسخةٌ جديدةٌ تشير للسابقة — والعكسُ إعادةُ الحدِّ السابقِ بقرار',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rows = array();
    $r = $conn->query("SELECT * FROM fin_ratio_targets WHERE company_id = {$company_id} AND active = 1
                        ORDER BY group_code, ratio_code");
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
?>
    <div class="card"><div class="card-body table-responsive">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>النسبة</th><th>المجموعة</th><th>الوحدة</th><th>اتجاه الحد</th>
                <th>حد الإنذار</th><th>الحد الحرج</th><th>الهدف</th><th>نص الحد</th>
                <th>الدورية</th><th>المالك</th><th>النسخة</th><th>المعتمِد</th><th>مرجع التفويض</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td style="font-family:monospace"><?php echo htmlspecialchars($x['ratio_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td><?php echo htmlspecialchars($x['group_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['unit_ar']); ?></td>
                    <td><?php echo htmlspecialchars($x['warn_op']); ?></td>
                    <td><?php echo $x['warn_value'] !== null ? $x['warn_value'] : '—'; ?></td>
                    <td><?php echo $x['critical_value'] !== null ? $x['critical_value'] : '—'; ?></td>
                    <td><?php echo $x['target_value'] !== null ? $x['target_value'] : '—'; ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars($x['limit_text']); ?></td>
                    <td><?php echo htmlspecialchars($x['cadence']); ?></td>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars($x['owner_role']); ?></td>
                    <td>v<?php echo (int) $x['version_no']; ?></td>
                    <td><?php echo $x['approved_by'] ? (int) $x['approved_by'] : '—'; ?></td>
                    <td style="font-size:.72rem"><?php echo htmlspecialchars($x['authority_ref']); ?></td>
                    <td><?php if ($can_write): ?>
                        <button class="ems-btn-secondary" onclick="faSetTarget('<?php echo htmlspecialchars($x['ratio_code']); ?>')">اعتماد حد</button>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <script>
    function faSetTarget(code) {
        var w = prompt('حدُّ الإنذارِ للنسبة ' + code + ' (اتركه فارغًا لإبقائه):', '');
        if (w === null) { return; }
        var c = prompt('الحدُّ الحرج:', '');
        if (c === null) { return; }
        var t = prompt('الهدفُ المعتمد:', '');
        if (t === null) { return; }
        faPost('ratio_target_set', { ratio_code: code, warn_value: w, critical_value: c, target_value: t });
    }
    </script>
<?php
}

require __DIR__ . '/../includes/fin_analysis_shell.php';
