<?php
/**
 * Risk/risk_assessment.php — تقييم الخطر ونسخُه التاريخية (M-16 §6-2 · الشاشة ٥)
 * ─────────────────────────────────────────────────────────────────────────
 * شاشةٌ طويلةٌ (٢٤ عمودًا) بمناظرَ إلزامية CM-09 وزرِّ إظهارِ الكل CM-10.
 * «التقييماتُ السابقةُ لا تُكتب فوقها — بل تُحفظ نسخًا تاريخيةً مؤرَّخةً
 * بمُقيِّمِها» (RK-03): فهذه الشاشةُ سجلٌّ append-only لا نموذجُ تعديل، وتتبعُ
 * تطورِ الخطرِ عبرَ الزمنِ شرطُ مراجعةٍ مهنية (§12).
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/_risk_views.php';
ems_shell_axes($__pp);

$view = risk_current_view('risk_assessment');
$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT, 'rr');

$fRisk = isset($_GET['risk']) ? (int) $_GET['risk'] : 0;
$fType = isset($_GET['atype']) ? (string) $_GET['atype'] : '';
$TYPE_AR = array('inherent' => 'متأصل', 'residual' => 'متبقٍّ', 'target' => 'مستهدف');

$w = " WHERE a.company_id = {$company_id} {$scopeSql}";
if ($fRisk > 0) { $w .= ' AND a.risk_id = ' . $fRisk; }
if (isset($TYPE_AR[$fType])) { $w .= " AND a.assess_type = '" . $conn->real_escape_string($fType) . "'"; }

$rows = array();
$res = $conn->query("SELECT a.*, rr.risk_code, rr.title risk_title, ru.ru_code,
                            u.name assessor, c.name challenger, ap.name approver
                       FROM risk_assessments a
                       JOIN risk_register rr ON rr.id = a.risk_id AND rr.company_id = a.company_id
                       JOIN risk_units ru ON ru.id = rr.ru_id
                       LEFT JOIN users u ON u.id = a.assessed_by
                       LEFT JOIN users c ON c.id = a.challenged_by
                       LEFT JOIN users ap ON ap.id = a.approved_by
                       {$w} ORDER BY a.assessed_at DESC, a.id DESC LIMIT 800");
while ($x = $res->fetch_assoc()) {
    $x['impacts'] = json_decode((string) $x['impacts_json'], true) ?: array();
    $rows[] = $x;
}

$stats = array('total' => count($rows), 'challenged' => 0, 'chains' => array());
foreach ($rows as $x) {
    if (!empty($x['challenged_by'])) { $stats['challenged']++; }
    $stats['chains'][$x['risk_id']] = true;
}

$page_title = 'إيكوبيشن | تقييم الخطر ونسخه التاريخية';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تقييم الخطر ونسخه التاريخية';
    $header_icon = 'fas fa-chart-simple';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'نسخ التقييم في نطاقك',
        'النسخ' => $stats['total'],
        'مخاطر مقيَّمة' => count($stats['chains']),
        'خضعت للتحدي' => $stats['challenged'],
        'المنظر' => $view === 'all' ? 'كل الأعمدة' : 'مختصر',
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'سجلٌّ تاريخيٌّ لا يُكتب فوقه (RK-03): كلُّ تقييمٍ نسخةٌ مؤرَّخةٌ بمُقيِّمِها ومرجعِها الأب. '
        . 'التقييمُ يُتحدَّى قبل اعتماده — والمتبقي وحدَه يُبنى عليه قرارُ القبول.',
        array('الأبعادُ الثمانيةُ لا تُدمج في رقمٍ واحد — والسلامةُ لا تُقايَض بالمال (§12-2)',
              'درجةُ الثقةِ تُعلَن ولا تُخفى (§12-3)',
              'التسجيلُ من ملفِّ الخطرِ — وهذه الشاشةُ للقراءةِ والمقارنةِ التاريخية'));
    risk_view_bar('risk_assessment', $view, array_filter(array(
        'risk' => $fRisk ?: null, 'atype' => $fType ?: null)));
    ?>

    <form method="get" class="ems-toolbar" style="align-items:flex-end">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <label style="font-size:.8rem">الخطر (id)
            <input name="risk" type="number" class="form-control" value="<?php echo $fRisk ?: ''; ?>" style="max-width:130px"></label>
        <label style="font-size:.8rem">النوع
            <select name="atype" class="form-control">
                <option value="">الكل</option>
                <?php foreach ($TYPE_AR as $k => $v): ?>
                <option value="<?php echo $k; ?>" <?php echo $fType === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select></label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="asEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('asEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا تقييمات في نطاقك بعد — التقييمُ يُسجَّل من ملفِّ الخطرِ بأبعادِه الثمانية',
            createHref: 'risk_register.php', createLabel: 'إلى السجل المركزي'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الخطر</th><th>النوع</th>
                <?php if (risk_col_visible('risk_assessment', $view, 'likelihood')): ?><th>الاحتمال</th><?php endif; ?>
                <?php foreach ($RISK_IMPACT_DIMS as $k => $label):
                    if (risk_col_visible('risk_assessment', $view, 'impact_' . $k)): ?>
                    <th title="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars(mb_substr($label, 0, 8)); ?></th>
                <?php endif; endforeach; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'score')): ?><th>الدرجة</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'level')): ?><th>المستوى</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'confidence')): ?><th>الثقة</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'technique')): ?><th>التقنية</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'assessor')): ?><th>المُقيِّم</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'challenger')): ?><th>المتحدِّي</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'approved_at')): ?><th>الاعتماد</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'authority_ref')): ?><th>مرجع التفويض</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'assessed_at')): ?><th>تاريخ التقييم</th><?php endif; ?>
                <?php if (risk_col_visible('risk_assessment', $view, 'parent_ref')): ?><th>النسخة السابقة</th><?php endif; ?>
                <th>فتح</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($x['risk_code']); ?></strong>
                        <div style="font-size:.72rem;opacity:.7"><?php echo htmlspecialchars(mb_substr((string) $x['risk_title'], 0, 40)); ?></div></td>
                    <td><?php echo $TYPE_AR[$x['assess_type']] ?? $x['assess_type']; ?></td>
                    <?php if (risk_col_visible('risk_assessment', $view, 'likelihood')): ?>
                    <td><?php echo (int) $x['likelihood']; ?></td><?php endif; ?>
                    <?php foreach ($RISK_IMPACT_DIMS as $k => $label):
                        if (risk_col_visible('risk_assessment', $view, 'impact_' . $k)): ?>
                        <td><?php echo isset($x['impacts'][$k]) ? (int) $x['impacts'][$k] : '—'; ?></td>
                    <?php endif; endforeach; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'score')): ?>
                    <td><?php echo (int) $x['score']; ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'level')): ?>
                    <td><?php $lv = (string) $x['level'];
                        $cls = ($lv === 'حرج' || $lv === 'محظور') ? 'badge-danger' : ($lv === 'مرتفع' ? 'badge-warning' : 'badge-secondary'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($lv); ?></span></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'confidence')): ?>
                    <td><?php echo htmlspecialchars((string) $x['confidence']); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'technique')): ?>
                    <td style="font-size:.76rem"><?php echo htmlspecialchars((string) $x['technique']); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'assessor')): ?>
                    <td><?php echo htmlspecialchars((string) $x['assessor'] ?: '—'); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'challenger')): ?>
                    <td><?php echo htmlspecialchars((string) $x['challenger'] ?: '—'); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'approved_at')): ?>
                    <td><?php echo htmlspecialchars((string) ($x['approver'] ?: '') . ' ' . (string) ($x['approved_at'] ?: '—')); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'authority_ref')): ?>
                    <td style="font-size:.74rem"><?php echo htmlspecialchars((string) $x['authority_ref'] ?: '—'); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'assessed_at')): ?>
                    <td><?php echo htmlspecialchars((string) $x['assessed_at']); ?></td><?php endif; ?>
                    <?php if (risk_col_visible('risk_assessment', $view, 'parent_ref')): ?>
                    <td style="font-size:.74rem"><?php echo htmlspecialchars((string) $x['parent_ref'] ?: 'أول نسخة'); ?></td><?php endif; ?>
                    <td><a class="btn btn-sm btn-outline-dark" href="risk_card.php?id=<?php echo (int) $x['risk_id']; ?>">ملف الخطر</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>
</div>
</body>
</html>
