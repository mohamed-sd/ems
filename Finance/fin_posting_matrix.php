<?php
/**
 * Finance/fin_posting_matrix.php — مصفوفة الترحيل من الإدارات (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_posting_matrix.php',
    'title' => 'مصفوفة الترحيل من الإدارات',
    'icon' => 'fas fa-table-cells',
    'about' => 'الحسابُ يُشتق من نوعِ الواقعةِ ونموذجِ العملِ ونوعِ العقد — ولا يُختار يدويًّا. ولكل إدارةٍ حدثُها المصدرُ وحسابا الإيرادِ والتكلفةِ وأبعادُها الإلزاميةُ وبوابتُها.',
    'notes' => array (
  0 => 'تعديلُ المصفوفةِ نسخةٌ جديدة — والعكسُ إعادةُ السابقة',
  1 => 'الإداراتُ لا تكتب قيدًا — تنشر حدثًا والماليةُ تقيّد',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rows = array();
    $r = $conn->query("SELECT * FROM fin_posting_matrix WHERE company_id = {$company_id} AND active = 1
                        ORDER BY rule_code");
    if ($r) { while ($x = $r->fetch_assoc()) { $rows[] = $x; } }
?>
    <style>
        .fa-mono{font-family:monospace}
        .fa-fs78{font-size:.78rem}
        .fa-fs76{font-size:.76rem}
        .fa-fs74{font-size:.74rem}
        .fa-table-full{width:100%}
    </style>
    <?php echo ems_states_bundle('لا صفوفَ نشطةً في مصفوفةِ الترحيل', 'تُزرع المصفوفةُ مع تهيئةِ الشركةِ — وتعديلُها نسخةٌ جديدةٌ من هذه الشاشة'); ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="alltables display nowrap fa-table-full">
            <thead><tr><th>الرمز</th><th>الإدارة</th><th>الحدث المصدر</th><th>حسابات الإيراد</th>
                <th>حسابات التكلفة</th><th>الأبعاد الإلزامية</th><th>البوابة</th>
                <th>الحكم الحاكم</th><th>النسخة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td class="fa-mono"><strong><?php echo htmlspecialchars($x['rule_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($x['dept_ar']); ?></td>
                    <td class="fa-fs78"><?php echo htmlspecialchars($x['source_event']); ?></td>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['revenue_accounts']); ?></td>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['cost_accounts']); ?></td>
                    <td class="fa-mono fa-fs74"><?php echo htmlspecialchars($x['required_dims']); ?></td>
                    <td class="fa-fs76"><?php echo htmlspecialchars($x['gate_ar']); ?></td>
                    <td class="fa-fs74"><?php echo htmlspecialchars($x['governing_rule']); ?></td>
                    <td>v<?php echo (int)$x['version_no']; ?></td>
                    <td><?php if ($can_write): ?>
                        <button class="ems-btn-secondary" onclick="faSetMatrix('<?php echo htmlspecialchars($x['rule_code']); ?>','<?php echo htmlspecialchars($x['revenue_accounts']); ?>','<?php echo htmlspecialchars($x['cost_accounts']); ?>')">تعديل</button>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <script>
    function faSetMatrix(code, rev, cost) {
        var r = prompt('حسابات الإيراد للصف ' + code + ':', rev);
        if (r === null) { return; }
        var c = prompt('حسابات التكلفة:', cost);
        if (c === null) { return; }
        faPost('posting_matrix_set', { rule_code: code, revenue_accounts: r, cost_accounts: c });
    }
    </script>
<?php
}

require __DIR__ . '/../includes/fin_analysis_shell.php';
