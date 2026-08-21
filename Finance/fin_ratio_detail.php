<?php
/**
 * Finance/fin_ratio_detail.php — تفصيل نسبة مالية (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
require_once __DIR__ . '/../includes/sensitive_read_log.php'; // INJ-FIX-01 §أ② — نقطةُ قرارِ الحقلِ الحساسِ في العرض
$FA_SCREEN = array(
    'file' => 'fin_ratio_detail.php',
    'title' => 'تفصيل نسبة مالية',
    'icon' => 'fas fa-magnifying-glass-chart',
    'about' => 'التعمّقُ من النسبةِ إلى حساباتِها ثم قيودِها ثم وقائعِها المصدر — ورقمٌ لا يُتعمَّق فيه لا يُقرَّر عليه.',
    'notes' => array (
  0 => 'البسطُ والمقامُ أكوادُ شجرةٍ معلنةٌ لا صيغةٌ خفية',
),
    'context' => array(),
    'filters' => '<label class="fa-field-label">النسبة <input type="text" name="ratio" value="" class="form-control form-control-sm" placeholder="FR-01" aria-label="رمز النسبة المالية المطلوب تفصيلها"></label>',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php echo ems_states_bundle('لا قياسَ لهذه النسبةِ في هذه الفترة', 'غيّر الفترةَ أو تحقق من احتسابِ النسبِ للفترة'); ?>
<?php
    $code = isset($_GET['ratio']) ? preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['ratio'])) : 'FR-01';
    $st = $conn->prepare("SELECT * FROM fin_ratio_targets WHERE company_id = ? AND ratio_code = ?
                           ORDER BY version_no DESC LIMIT 1");
    $st->bind_param('is', $company_id, $code);
    $st->execute();
    $t = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$t) { ems_state_empty('لا نسبةَ بهذا الرمز — اختر من لوحة النسب'); return; }
    $st = $conn->prepare("SELECT * FROM fin_ratio_values WHERE company_id = ? AND ratio_code = ?
                            AND period = ? AND scope_kind = 'company' ORDER BY id DESC LIMIT 1");
    $st->bind_param('iss', $company_id, $code, $period);
    $st->execute();
    $v = $st->get_result()->fetch_assoc();
    $st->close();
    $accs = array();
    foreach (array('numerator_codes' => 'بسط', 'denominator_codes' => 'مقام') as $f => $side) {
        foreach (array_filter(array_map('trim', explode(',', (string) $t[$f]))) as $c) {
            $b = \App\Services\Finance\CoaService::balance($conn, $company_id, array($c), $period);
            $acc = \App\Services\Finance\CoaService::account($conn, $company_id, $c);
            $accs[] = array('side' => $side, 'code' => $c, 'name' => $acc['name'] ?? '—',
                'stmt' => $acc['statement_code'] ?? '', 'flow' => $acc['cashflow_activity'] ?? '',
                'bal' => $b ? $b['balance'] : null, 'n' => $b ? $b['n'] : 0);
        }
    }
?>
    <div class="card"><div class="card-body">
        <h6><?php echo htmlspecialchars($t['name_ar']); ?> — <?php echo htmlspecialchars($code); ?></h6>
        <table class="table table-sm"><tbody>
            <tr><td>الصيغة</td><td><strong><?php echo htmlspecialchars($t['formula_ar']); ?></strong></td></tr>
            <tr><td>حسابات البسط</td><td class="fa-mono"><?php echo htmlspecialchars($t['numerator_codes']); ?></td></tr>
            <tr><td>حسابات المقام</td><td class="fa-mono"><?php echo htmlspecialchars($t['denominator_codes']); ?></td></tr>
            <tr><td>قيمة البسط</td><td><?php echo $v && $v['numerator_value'] !== null ? number_format((float)$v['numerator_value'],2) : '—'; ?></td></tr>
            <tr><td>قيمة المقام</td><td><?php echo $v && $v['denominator_value'] !== null ? number_format((float)$v['denominator_value'],2) : '—'; ?></td></tr>
            <tr><td>النتيجة</td><td><strong><?php echo $v && $v['result_value'] !== null ? ems_sensitive_display($conn, "fin_ratio_values.result_value", number_format((float)$v["result_value"],4), "ratio:" . htmlspecialchars($code), "تفصيل النسبة") : '— غير مقيسة'; ?></strong> <?php echo htmlspecialchars($t['unit_ar']); ?></td></tr>
            <tr><td>الحد والمالك</td><td><?php echo htmlspecialchars($t['limit_text'] . ' · ' . $t['owner_role']); ?></td></tr>
            <tr><td>الاتجاه عبر الفترات</td><td><?php echo htmlspecialchars($v['trend_direction'] ?? 'na'); ?></td></tr>
            <tr><td>الأبعاد المطبَّقة</td><td>D1 الكيان · الفترة <?php echo htmlspecialchars($period); ?></td></tr>
        </tbody></table>
    </div></div>
    <div class="card"><div class="card-body table-responsive">
        <h6>الحسابات المكوِّنة — والقيودُ خلفها</h6>
        <table class="table table-sm table-striped fa-table-full" data-no-dt="1">
            <thead><tr><th>الجهة</th><th>الكود</th><th>الحساب</th><th>القائمة</th><th>نشاط التدفق</th><th>الرصيد</th><th>عدد القيود</th></tr></thead>
            <tbody>
            <?php foreach ($accs as $a): ?>
                <tr>
                    <td><?php echo $a['side']; ?></td>
                    <td class="fa-mono"><?php echo htmlspecialchars($a['code']); ?></td>
                    <td><?php echo htmlspecialchars($a['name']); ?></td>
                    <td><?php echo htmlspecialchars($a['stmt']); ?></td>
                    <td><?php echo htmlspecialchars($a['flow']); ?></td>
                    <td><?php echo $a['bal'] !== null ? number_format($a['bal'],2) : '—'; ?></td>
                    <td><?php echo (int) $a['n']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
<?php
}

/* القشرةُ الموحَّدةُ هي التي تُنفِّذ include '../inheader.php' ثم insidebar بعد الحارسِ (البوابة ٤) */
require __DIR__ . '/../includes/fin_analysis_shell.php';
