<?php
/**
 * Finance/fin_early_warning.php — الإنذار المالي المبكر (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_early_warning.php',
    'title' => 'الإنذار المالي المبكر',
    'icon' => 'fas fa-triangle-exclamation',
    'about' => 'ست عشرة قاعدة إنذار تقاس آليا — وكل إشارة تنشر لإدارة المخاطر فتدخل الفرز الرباعي ولا تبقى في المالية.',
    'notes' => array (
  0 => 'الإشارة ليست خطرا — فليس كل ما يبلغ عنه خطرا (RK-05)',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $rules = array();
    $r = $conn->query("SELECT * FROM fin_signal_rules WHERE company_id = {$company_id} AND active = 1 ORDER BY signal_code");
    while ($x = $r->fetch_assoc()) { $rules[] = $x; }
    $sigs = array();
    $r = $conn->query("SELECT sg_code, title, state, created_at, rule_key FROM risk_signals
                        WHERE company_id = {$company_id} AND rule_key LIKE 'finsig:%'
                        ORDER BY id DESC LIMIT 100");
    while ($x = $r->fetch_assoc()) { $sigs[] = $x; }
?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php echo ems_states_bundle('لا توجد بيانات لهذه الفترة', 'غير الفترة أو تحقق من توفر السجلات'); ?>
    <div class="card"><div class="card-body fa-kpi-row">
        <span class="badge badge-info fa-kpi-badge">قواعد: <strong><?php echo count($rules); ?></strong></span>
        <span class="badge badge-warning fa-kpi-badge">إشارات مرفوعة: <strong><?php echo count($sigs); ?></strong></span>
        <?php if ($can_write): ?>
        <button class="ems-btn-primary" onclick="faPost('signal_raise', {}, function(j){ alert('فحصت ' + j.checked + ' قاعدة · رفعت ' + j.raised.length + ' إشارة'); location.reload(); })">تقييم القواعد للفترة</button>
        <?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body table-responsive">
        <h6>قواعد الإنذار الست عشرة</h6>
        <?php if (!$rules): /* حالةُ الفراغِ تُعرض بدل جدولٍ خالٍ */ ?>
            <?php ems_state_empty('لا قواعد إنذار معرفة لهذا الكيان بعد'); ?>
        <?php else: ?>
        <table class="alltables display nowrap fa-table-full">
            <thead><tr><th>الرمز</th><th>الإشارة</th><th>القاعدة</th><th>النسبة</th><th>المعامل</th>
                <th>الحد</th><th>الشدة</th><th>الوجهة</th><th>الدورية</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $x): ?>
                <tr>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['signal_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?></td>
                    <td class="fa-mono fa-cell-sm"><?php echo htmlspecialchars($x['rule_expr']); ?></td>
                    <td><?php echo htmlspecialchars($x['ratio_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['operator']); ?></td>
                    <td><?php echo $x['threshold'] !== null ? $x['threshold'] : '—'; ?></td>
                    <td><span class="badge <?php echo $x['severity']==='حرج'?'badge-danger':($x['severity']==='مرتفع'?'badge-warning':'badge-secondary'); ?>"><?php echo htmlspecialchars($x['severity']); ?></span></td>
                    <td class="fa-cell-sm"><?php echo htmlspecialchars($x['destination_ar']); ?></td>
                    <td><?php echo htmlspecialchars($x['cadence']); ?></td>
                    <td><?php echo (int)$x['active'] ? 'نشطة' : 'معطلة'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
    <div class="card fa-card-gap"><div class="card-body table-responsive">
        <h6>الإشارات المرفوعة لإدارة المخاطر</h6>
        <?php if (!$sigs): ?>
            <?php ems_state_empty('لا إشارات مرفوعة بعد — شغل التقييم للفترة'); ?>
        <?php else: ?>
        <table class="table table-sm table-striped fa-table-full" data-no-dt="1">
            <thead><tr><th>الرمز</th><th>العنوان</th><th>الحالة في الفرز</th><th>المفتاح</th><th>الوقت</th></tr></thead>
            <tbody>
            <?php foreach ($sigs as $x): ?>
                <tr>
                    <td class="fa-mono"><?php echo htmlspecialchars((string)$x['sg_code']); ?></td>
                    <td><?php echo htmlspecialchars($x['title']); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($x['state']); ?></span></td>
                    <td class="fa-mono fa-cell-xs"><?php echo htmlspecialchars((string)$x['rule_key']); ?></td>
                    <td><small><?php echo htmlspecialchars($x['created_at']); ?></small></td>
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
