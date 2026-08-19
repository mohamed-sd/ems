<?php
/**
 * Finance/fin_contract_margin.php — هامش العقد ونموذج العمل (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_contract_margin.php',
    'title' => 'هامش العقد ونموذج العمل',
    'icon' => 'fas fa-file-invoice-dollar',
    'about' => 'الهامشُ لكل عقدٍ ولكل نموذجِ عملٍ — الساعةُ والطنُّ والمترُ لا تُخلط. والعقدُ بهامشٍ سالبٍ يُنشر إشارةً للمبيعاتِ والمخاطر.',
    'notes' => array (
  0 => 'نموذجُ العملِ بُعدٌ D7 — والهامشُ يُقاس لكلٍّ منفصلًا (R5)',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $models = array('hour' => 'تأجير بالساعة', 'ton' => 'نقل بالطن', 'meter' => 'تخريم بالمتر');
    $rows = array();
    foreach ($models as $k => $label) {
        $m = \App\Services\Finance\FinAnalysisService::margins($conn, $company_id, $period,
            array('business_model' => $k));
        $rows[] = array('key' => $k, 'label' => $label, 'm' => $m);
    }
    $ctr = array();
    $r = $conn->query("SELECT DISTINCT l.contract_id FROM fin_journal_lines l
                        WHERE l.company_id = {$company_id} AND l.contract_id IS NOT NULL LIMIT 100");
    if ($r) { while ($x = $r->fetch_assoc()) { $ctr[] = (int) $x['contract_id']; } }
?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php echo ems_states_bundle('لا توجد بياناتٌ لهذه الفترة', 'غيّر الفترةَ أو تحقق من توفرِ السجلات'); ?>
    <div class="card"><div class="card-body table-responsive">
        <h6>الهامش بنموذج العمل — الثلاثة لا تُخلط</h6>
        <table class="table table-sm table-striped fa-table-full" data-no-dt="1">
            <thead><tr><th>النموذج</th><th>M1 الإيراد</th><th>M2 الإجمالي</th><th>M3 التشغيلي</th>
                <th>M4 قبل الضريبة</th><th>M5 الصافي</th><th>نسبة الهامش</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x):
                $m1 = $x['m']['M1']['value']; $m2 = $x['m']['M2']['value'];
                $pct = ($m1 !== null && abs($m1) > 0.0001 && $m2 !== null) ? round($m2/$m1*100, 2) : null; ?>
                <tr>
                    <td><strong><?php echo $x['label']; ?></strong></td>
                    <?php foreach (array('M1','M2','M3','M4','M5') as $k): $v = $x['m'][$k]['value']; ?>
                    <td><?php echo $v !== null ? number_format($v,2) : '—'; ?></td>
                    <?php endforeach; ?>
                    <td><?php echo $pct !== null ? $pct.'٪' : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <div class="card fa-card-gap"><div class="card-body table-responsive">
        <h6>الهامش بالعقد (D8)</h6>
        <?php if (!$ctr): ?>
            <?php ems_state_empty('لا عقودَ لها قيودٌ بالبُعد D8 في هذه الفترة'); ?>
        <?php else: ?>
        <table class="table table-sm table-striped fa-table-full" data-no-dt="1">
            <thead><tr><th>العقد</th><th>M1</th><th>M2</th><th>M3</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($ctr as $cid):
                $m = \App\Services\Finance\FinAnalysisService::margins($conn, $company_id, $period, array('contract_id' => $cid));
                $neg = ($m['M2']['value'] !== null && $m['M2']['value'] < 0); ?>
                <tr>
                    <td class="fa-mono">CTR-<?php echo $cid; ?></td>
                    <?php foreach (array('M1','M2','M3') as $k): $v = $m[$k]['value']; ?>
                    <td><?php echo $v !== null ? number_format($v,2) : '—'; ?></td>
                    <?php endforeach; ?>
                    <td><span class="badge <?php echo $neg?'badge-danger':'badge-success'; ?>"><?php echo $neg?'سالب — إشارة':'موجب'; ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
<?php
}

/* القشرةُ الموحَّدةُ للتحليل — وهي التي تنفّذ include inheader.php والحارسَ وشريطَ الفترة */
require __DIR__ . '/../includes/fin_analysis_shell.php';
