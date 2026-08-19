<?php
/**
 * Finance/fin_project_pl.php — قائمة دخل المشروع (M-10 v5 · المرحلة ٩ التحليل المالي والنسب)
 * ─────────────────────────────────────────────────────────────────────────
 * ◆ مولَّدةٌ على قشرةِ التحليلِ الواحدة (includes/fin_analysis_shell.php):
 * حارسٌ واحدٌ وغلافٌ حاكمٌ واحدٌ وشريطُ فترةٍ واحدٌ لعشرِ شاشات — «لا مكوّنَ
 * جديدًا لوظيفةٍ قائمة» (المبدأ ١١). والجسمُ وحدَه يختلف.
 */
$FA_SCREEN = array(
    'file' => 'fin_project_pl.php',
    'title' => 'قائمة دخل المشروع',
    'icon' => 'fas fa-diagram-project',
    'about' => 'الإيرادُ والتكلفةُ المباشرةُ بالبُعد D2 والحصةُ المحمَّلةُ بأساسِ تحميلٍ معلَن — تُنتَج من الأبعادِ لا من شجرةٍ منفصلة.',
    'notes' => array (
  0 => 'أساسُ التحميلِ يُعلَن ولا يُخترع — والتوليدُ نسخةٌ تشير لسابقتها',
),
    'context' => array(),
    'filters' => '',
);

/** جسمُ الشاشةِ — تُستدعى من القشرةِ بعد الحارسِ والغلاف */
function fa_render_body($conn, $company_id, $period, $can_write, $uid)
{
    $projects = array();
    $r = $conn->query("SELECT id, name FROM projects WHERE company_id = {$company_id} ORDER BY id DESC LIMIT 100");
    if ($r) { while ($x = $r->fetch_assoc()) { $projects[] = $x; } }
    $rows = array();
    $r = $conn->query("SELECT p.*, pr.name project_name FROM fin_project_pl p
                        LEFT JOIN projects pr ON pr.id = p.project_id
                       WHERE p.company_id = {$company_id} AND p.period = '" . $conn->real_escape_string($period) . "'
                       ORDER BY p.id DESC");
    if ($r) { while ($x = $r->fetch_assoc()) { $rows[] = $x; } }
?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <?php echo ems_states_bundle('لا بياناتٍ لهذه الفترةِ المالية', 'غيّر الفترةَ أو تحقق من ترحيلِ القيود'); ?>
    <?php if ($can_write && $projects): ?>
    <div class="card"><div class="card-body fa-gen-bar">
        <label class="fa-field-label" for="faProj">المشروع
            <select id="faProj" class="form-control form-control-sm" aria-label="المشروع المراد توليد قائمة دخله">
                <?php foreach ($projects as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="ems-btn-primary" onclick="faPost('project_pl', { project_id: document.getElementById('faProj').value })">توليد قائمة الدخل</button>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-body table-responsive">
        <?php if (!$rows): ?>
            <?php ems_state_empty('لا قوائمَ مولَّدةً لهذه الفترة'); ?>
        <?php else: ?>
        <table class="alltables display nowrap fa-table-full">
            <thead><tr><th>الرقم</th><th>المشروع</th><th>الفترة</th><th>العملة</th><th>الإيراد</th><th>التكلفة المباشرة</th>
                <th>الحصة المحمَّلة</th><th>أساس التحميل</th><th>الهامش الإجمالي</th><th>الربح التشغيلي</th>
                <th>نسبة الهامش</th><th>الحالة</th><th>مولّدها</th><th>مرجع التفويض</th><th>المرجع الأب</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['pl_code']); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['project_name'] ?? $x['project_id'])); ?></td>
                    <td><?php echo htmlspecialchars($x['period']); ?></td>
                    <td><?php echo htmlspecialchars($x['currency']); ?></td>
                    <td><?php echo number_format((float)$x['revenue_total'],2); ?></td>
                    <td><?php echo number_format((float)$x['direct_cost_total'],2); ?></td>
                    <td><?php echo number_format((float)$x['allocated_overhead'],2); ?></td>
                    <td class="fa-fs74"><?php echo htmlspecialchars($x['allocation_basis']); ?></td>
                    <td><?php echo number_format((float)$x['gross_margin'],2); ?></td>
                    <td><strong><?php echo number_format((float)$x['operating_profit'],2); ?></strong></td>
                    <td><?php echo $x['margin_pct'] !== null ? $x['margin_pct'].'٪' : '—'; ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($x['state']); ?></span></td>
                    <td><?php echo (int)$x['generated_by']; ?></td>
                    <td class="fa-fs72"><?php echo htmlspecialchars($x['authority_ref']); ?></td>
                    <td class="fa-mono"><?php echo htmlspecialchars($x['parent_ref']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
<?php
}

/* UXW-01 §8-2: موضعُ الشاشةِ من رحلةِ المشروع — القشرةُ تُخرِج الشريط */
$ENTITY_KEY = 'project';
$ENTITY_TAB = 'الميزانيةُ والإنجاز';

/* القشرةُ الموحَّدةُ هي التي تُنفِّذ include '../inheader.php' ثم insidebar بعد الحارسِ (البوابة ٤) */
require __DIR__ . '/../includes/fin_analysis_shell.php';
