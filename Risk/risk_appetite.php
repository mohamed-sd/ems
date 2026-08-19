<?php
/**
 * Risk/risk_appetite.php — الشهية وحدود التحمل (M-16 · ورقة 25)
 * يحددها الرئيس التنفيذي — والأرضيات الثلاث لا تتغير بحال (السلامة · القانون ·
 * تسرب البيانات). الإدارات تقترح ولا تقرر.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
$__ro = $__pp;
$__ro['can_edit'] = 0; $__ro['can_add'] = 0; // العرض قراءة — والتغيير قرار مالك خارج الشاشة
ems_shell_axes($__ro);

$rows = array();
$r = $conn->query("SELECT * FROM risk_appetite WHERE company_id = {$company_id} AND plan_mode IS NULL ORDER BY immutable_floor DESC, id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$page_title = 'إيكوبيشن | شهية المخاطر';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'شهية المخاطر وحدود التحمل';
    $header_icon = 'fas fa-scale-balanced';
    $header_actions = array();
    $header_back = array();
    $header_context = array('المجالات' => count($rows), 'الأرضيات الثابتة' => '3 (لا تتغير بحال)', 'المصدر' => 'ورقة 25 بقرار الرئيس');
    include('../includes/page_header.php');
    ems_screen_about('مستوى الخطر الذي تقبله الشركة سعيًا لأهدافها — يحدده الرئيس بحسب الخطة العامة، '
        . 'وأنماط الخطة الثلاثة (نمو/تثبيت/حماية) تعدل الشهيات المتغيرة ولا تمس الأرضيات.',
        array('حد التحمل أضيق من الشهية وأدق منها — وعنده يبدأ التصعيد'));
    echo ems_states_bundle('لا مجالاتِ شهيةٍ مبذورةً لهذا الكيان', 'شهيةُ المخاطرِ تُبذر بقرارِ الرئيسِ — والأرضياتُ الثلاثُ لا تتغير بحال');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped rsk-w100">
            <thead><tr><th>المجال</th><th>الشهية</th><th>حد التحمل</th><th>المخوَّل</th><th>التغيّر</th><th>أرضية ثابتة</th></tr></thead>
            <tbody><?php foreach ($rows as $a): ?>
            <tr <?php echo (int) $a['immutable_floor'] === 1 ? 'class="rsk-floor-row"' : ''; ?>>
                <td><b><?php echo htmlspecialchars($a['domain']); ?></b></td>
                <td><?php echo htmlspecialchars($a['appetite_ar']); ?></td>
                <td><?php echo htmlspecialchars($a['tolerance_ar']); ?></td>
                <td><?php echo htmlspecialchars($a['authority_ar']); ?></td>
                <td><?php echo htmlspecialchars($a['changeable_ar']); ?></td>
                <td><?php echo (int) $a['immutable_floor'] === 1 ? '<span class="badge badge-danger">لا تتغير بحال</span>' : '—'; ?></td>
            </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div></div>
    <div class="ems-card rsk-plans-card">
        <h6>أنماط الخطة الثلاثة (تعديل الشهية بقرار المالك — لا من هذه الشاشة)</h6>
        <ul class="rsk-plans-list">
            <li><b>النمو والتوسع:</b> تُرفع شهية التعاقد والتشغيل والأفراد والتمويل — ولا تُرفع السلامة ولا القانون ولا تسرب البيانات، وتُشدَّد الضوابط المعوِّضة كلما رُفعت.</li>
            <li><b>التثبيت والكفاءة:</b> تُخفض شهية التعاقد الجديد والتمويل والتوسع الجغرافي — وتُرفع دقة المتابعة والوقائية، والتركيز على تقليل المتبقي لا قبوله.</li>
            <li><b>الحماية والانكماش:</b> تُخفض الشهية في المجالات كلها — وتُرفع حدود التصعيد فيصل للرئيس ما كان يُحسم أدنى، ويُراجَع كل قبول سابق.</li>
        </ul>
    </div>
</div>
</body>
</html>
