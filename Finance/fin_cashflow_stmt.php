<?php
/**
 * Finance/fin_cashflow_stmt.php — قائمة التدفقات النقدية (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_cashflow_stmt.php',
    'title' => 'قائمة التدفقات النقدية',
    'icon' => 'fas fa-money-bill-transfer',
    'about' => 'بالطريقةِ غيرِ المباشرة: النتيجةُ ثم التسوياتُ ثم التغيرُ في رأسِ المالِ العامل — وتتوازن مع تغيرِ النقديةِ الفعليِّ أو تُرفض.',
    'notes' => array (
  0 => 'تصنيفُ نشاطِ التدفقِ على كل حساب (R4) شرطُ إنتاجِ القائمةِ آليًّا',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rows = array();
    $r = $conn->query("SELECT * FROM fin_cashflow WHERE company_id = {$company_id}
                        ORDER BY period DESC, id DESC LIMIT 24");
    if ($r) { while ($x = $r->fetch_assoc()) { $rows[] = $x; } }
?>
    <style>
        .fa-note{font-size:.78rem;opacity:.75;margin-inline-start:10px}
        .fa-mono{font-family:monospace}
        .fa-table-full{width:100%}
    </style>
    <?php echo ems_states_bundle('لا بياناتٍ لهذه الفترةِ المالية', 'غيّر الفترةَ أو تحقق من ترحيلِ القيود'); ?>
    <?php if ($can_write): ?>
    <div class="card"><div class="card-body">
        <button class="ems-btn-primary" onclick="faPost('cashflow_generate', {}, function(j){ alert('وُلّدت ' + j.cf_code + ' · التشغيلي ' + j.operating + ' · الفرق ' + j.balance_diff + ' — متوازنة ✔'); location.reload(); })">توليد القائمة للفترة</button>
        <span class="fa-note">◆ لا تُحفظ إن لم تتوازن — والرفضُ يبيّن الفرقَ ويقود لتصنيفِ النشاط</span>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-body table-responsive">
        <?php if (!$rows): ?>
            <?php ems_state_empty('لا قوائمَ تدفقاتٍ مولَّدةً بعد'); ?>
        <?php else: ?>
        <table class="alltables display nowrap fa-table-full">
            <thead><tr><th>الرقم</th><th>الفترة</th><th>العملة</th><th>الربح الصافي</th><th>الإهلاك</th><th>المخصصات</th>
                <th>الذمم</th><th>المخزون</th><th>الموردون</th><th>التشغيلي</th><th>الاستثماري</th>
                <th>التمويلي</th><th>صافي التغير</th><th>نقدية الافتتاح</th><th>نقدية الختام</th>
                <th>التغير الفعلي</th><th>الفرق</th><th>متوازنة؟</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['cf_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['period']); ?></td>
                    <td><?php echo htmlspecialchars($x['currency']); ?></td>
                    <?php foreach (array('net_profit','adj_depreciation','adj_provisions','wc_receivables',
                        'wc_inventory','wc_payables','operating_net','investing_net','financing_net',
                        'net_change','cash_open','cash_close','actual_change','balance_diff') as $f): ?>
                    <td><?php echo number_format((float)$x[$f],2); ?></td>
                    <?php endforeach; ?>
                    <td><span class="badge <?php echo (int)$x['balance_ok']?'badge-success':'badge-danger'; ?>"><?php echo (int)$x['balance_ok']?'✔ متوازنة':'✘'; ?></span></td>
                    <td><?php echo htmlspecialchars($x['state']); ?></td>
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
