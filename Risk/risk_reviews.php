<?php
/**
 * Risk/risk_reviews.php — المراجعات وقرارات القبول (M-16 · المرحلتان 11 و13)
 * القبول ليس إهمالًا: قرار موقَّع بمهلة مراجعة — والمتأخر عنها يظهر هنا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT);
$due = array();
$r = $conn->query("SELECT rr.id, rr.risk_code, rr.title, rr.current_level, rr.review_due
                     FROM risk_register rr
                    WHERE rr.company_id={$company_id} AND rr.state<>'closed' AND rr.merged_into_id IS NULL
                      AND rr.review_due IS NOT NULL AND rr.review_due <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) {$scopeSql}
                    ORDER BY rr.review_due LIMIT 200");
while ($x = $r->fetch_assoc()) { $due[] = $x; }

$acc = array();
$r = $conn->query("SELECT a.*, rr.risk_code, rr.title, u.name acceptor
                     FROM risk_acceptances a JOIN risk_register rr ON rr.id = a.risk_id
                     LEFT JOIN users u ON u.id = a.accepted_by
                    WHERE a.company_id={$company_id} {$scopeSql} ORDER BY a.created_at DESC LIMIT 300");
while ($x = $r->fetch_assoc()) { $acc[] = $x; }

$page_title = 'إيكوبيشن | مراجعات المخاطر وقراراتها';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'المراجعات وقرارات القبول';
    $header_icon = 'fas fa-stamp';
    $header_actions = array();
    $header_back = array();
    $header_context = array('مراجعات مستحقة (14 يومًا)' => count($due), 'قرارات القبول' => count($acc));
    include('../includes/page_header.php');
    ems_screen_about('دورية المراجعة بالمستوى: الحرج شهريًّا والمرتفع ربعيًّا والمتوسط نصف سنوي والمنخفض سنويًّا — '
        . 'وإعادة التقييم تحفظ السابق ولا تمحوه (RK-03).', array());
    echo ems_states_bundle('لا مراجعاتٍ مستحقةً ولا قراراتِ قبولٍ في نطاقك', 'المهلُ تُحتسب من مستوى الخطرِ — والقبولُ يُسجَّل من ملفِّ الخطر');
    ?>
<?php require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('risk', 'سجلُّ المراجعات'); ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
    <div class="ems-grid">
        <div class="ems-card ems-col-5">
            <h6>مراجعات مستحقة أو متأخرة</h6>
            <table class="table table-sm table-striped">
                <thead><tr><th>الخطر</th><th>المستوى</th><th>الموعد</th><th></th></tr></thead>
                <tbody><?php foreach ($due as $d): ?>
                <tr<?php echo $d['review_due'] < date('Y-m-d') ? ' class="rsk-row-overdue"' : ''; ?>>
                    <td><?php echo $d['risk_code']; ?></td>
                    <td><?php echo $d['current_level'] ?: '—'; ?></td>
                    <td><?php echo $d['review_due']; ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="risk_card.php?id=<?php echo (int) $d['id']; ?>">إعادة تقييم</a></td>
                </tr>
                <?php endforeach; if (empty($due)): ?><tr><td colspan="4" class="text-muted">لا مستحقات</td></tr><?php endif; ?></tbody>
            </table>
        </div>
        <div class="ems-card ems-col-7">
            <h6>سجل قرارات القبول الرسمية (RK-04)</h6>
            <div class="table-responsive"><table class="table table-sm table-striped">
                <thead><tr><th>الخطر</th><th>المستوى</th><th>السلطة</th><th>القابل</th><th>مراجعة قبل</th><th>التاريخ</th></tr></thead>
                <tbody><?php foreach ($acc as $a): ?>
                <tr><td><?php echo $a['risk_code']; ?></td><td><?php echo $a['level_at_acceptance']; ?></td>
                    <td><?php echo $a['authority']; ?></td><td><?php echo htmlspecialchars((string) $a['acceptor']); ?></td>
                    <td><?php echo $a['review_due']; ?></td><td><?php echo $a['created_at']; ?></td></tr>
                <?php endforeach; if (empty($acc)): ?><tr><td colspan="6" class="text-muted">لا قرارات بعد</td></tr><?php endif; ?></tbody>
            </table></div>
        </div>
    </div>
</div>
</body>
</html>
