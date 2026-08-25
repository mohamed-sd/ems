<?php
/**
 * Risk/risk_acceptance.php — القبول والاستثناءات (M-16 · الشاشة ١٠)
 * ─────────────────────────────────────────────────────────────────────────
 * «قبولُ الخطرِ ليس إهمالًا — بل قرارٌ موقَّعٌ بمهلةِ مراجعة» (§11-2). ولكلِّ
 * قرارٍ سلطتُه من مصفوفةِ الاعتماد (§14-2): المنخفضُ لمالكه · المتوسطُ بمراجعةِ
 * محلل · المرتفعُ للنائب · الحرجُ للرئيسِ حصرًا · والمحظورُ لا يُقبل بحال.
 * وهذه الشاشةُ سجلُّ القراراتِ ومهلُ مراجعتها — والتسجيلُ من ملفِّ الخطرِ حيث
 * الدرجةُ الجاريةُ والحارسُ معًا.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$scopeSql = risk_scope_sql($RISK_FULL, $RISK_ORG_UNIT, 'rr');
$fLate = isset($_GET['late']) && $_GET['late'] === '1';

$w = " WHERE a.company_id = {$company_id} {$scopeSql}";
if ($fLate) { $w .= ' AND a.review_due < CURDATE()'; }

$rows = array();
$res = $conn->query("SELECT a.*, rr.risk_code, rr.title, rr.current_level, rr.state risk_state,
                            rr.appetite_verdict, ru.ru_code,
                            u.name acceptor, an.name analyst, wd.name withdrawer,
                            DATEDIFF(CURDATE(), a.review_due) late_days
                       FROM risk_acceptances a
                       JOIN risk_register rr ON rr.id = a.risk_id AND rr.company_id = a.company_id
                       JOIN risk_units ru ON ru.id = rr.ru_id
                       LEFT JOIN users u ON u.id = a.accepted_by
                       LEFT JOIN users an ON an.id = a.analyst_review_by
                       LEFT JOIN users wd ON wd.id = a.withdrawn_by
                       {$w} ORDER BY a.review_due ASC, a.id DESC LIMIT 500");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$AUTH_AR = array('risk_owner' => 'مالك الخطر', 'owner_with_analyst' => 'المالك بمراجعة محلل',
    'analyst' => 'محلل المخاطر', 'deputy' => 'النائب المختص', 'ceo' => 'الرئيس التنفيذي');

$stats = array('total' => count($rows), 'late' => 0, 'ceo' => 0, 'withdrawn' => 0, 'over' => 0);
foreach ($rows as $x) {
    if ((int) $x['late_days'] > 0 && empty($x['withdrawn_at'])) { $stats['late']++; }
    if ($x['authority'] === 'ceo') { $stats['ceo']++; }
    if (!empty($x['withdrawn_at'])) { $stats['withdrawn']++; }
    if (in_array((string) $x['appetite_verdict'], array('فوق الشهية', 'فوق حد التحمل', 'محظور'), true)) { $stats['over']++; }
}

$page_title = 'إيكوبيشن | القبول والاستثناءات';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('risk', 'القبول والتصعيد');
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'القبول والاستثناءات';
    $header_icon = 'fas fa-file-signature';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'قرارات القبول في نطاقك',
        'القرارات' => $stats['total'],
        'تجاوزت مهلة المراجعة' => $stats['late'],
        'بسلطة الرئيس' => $stats['ceo'],
        'فوق الشهية' => $stats['over'],
        'مسحوبة' => $stats['withdrawn'],
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'قرار قبول موقع بسلطة محددة ومهلة مراجعة وضوابط معوضة — لا إهمال ولا صمت. '
        . 'والسلطة من مصفوفة الاعتماد: الحرج للرئيس حصرا والمحظور لا يقبل بحال (RK-04).',
        array('محاولة قبول فوق السقف ترفض وتصعد آليا — ولو وقع إداريا',
              'مهلة المراجعة بالدرجة: سنويا للمنخفض وشهريا للحرج (§14-2)',
              'سحب القبول بقرار من الجهة نفسها أو أعلى — لا حذف للقرار'));
    echo ems_next_step('مراجعة القرار قبل مهلته — وإعادة التقييم أو السحب من ملف الخطر', 'risk_register.php');
    echo ems_states_bundle('لا قرارات قبول ضمن هذا الترشيح', 'القبول يسجل من ملف الخطر حيث الدرجة الجارية وحارس السلطة');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <form method="get" class="ems-toolbar rsk-filter-form">
        <label class="rsk-filter-label"><input type="checkbox" name="late" value="1" aria-label="تجاوزت مهلة المراجعة فقط" <?php echo $fLate ? 'checked' : ''; ?>>
            تجاوزت مهلة المراجعة فقط</label>
        <button type="submit" class="ems-btn-secondary"><i class="fa fa-filter"></i> ترشيح</button>
    </form>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="acEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('acEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا قرارات قبول في نطاقك — والقبول يسجل من ملف الخطر حيث الدرجة الجارية وحارس السلطة',
            createHref: 'risk_register.php', createLabel: 'إلى السجل المركزي'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped rsk-w100">
            <thead><tr>
                <th>الخطر</th><th>الوحدة</th><th>الدرجة عند القبول</th><th>حكم الشهية</th>
                <th>السلطة</th><th>مرجع التفويض</th><th>المقر</th><th>مراجعة المحلل</th>
                <th>ضوابط معوضة</th><th>مهلة المراجعة</th><th>التأخر</th>
                <th>الملاحظة</th><th>المرجع الأب</th><th>الحالة</th><th>فتح</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x):
                $late = (int) $x['late_days'] > 0 && empty($x['withdrawn_at']); ?>
                <tr<?php echo $late ? ' class="rsk-row-late"' : ''; ?>>
                    <td><strong><?php echo htmlspecialchars($x['risk_code']); ?></strong>
                        <div class="rsk-subnote"><?php echo htmlspecialchars(mb_substr((string) $x['title'], 0, 40)); ?></div></td>
                    <td><?php echo htmlspecialchars((string) $x['ru_code']); ?></td>
                    <td><?php $lv = (string) $x['level_at_acceptance'];
                        $cls = ($lv === 'حرج' || $lv === 'محظور') ? 'badge-danger' : ($lv === 'مرتفع' ? 'badge-warning' : 'badge-secondary'); ?>
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($lv); ?></span></td>
                    <td><?php echo htmlspecialchars((string) $x['appetite_verdict'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($AUTH_AR[$x['authority']] ?? (string) $x['authority']); ?></td>
                    <td class="rsk-fs74"><?php echo htmlspecialchars((string) $x['authority_ref'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['acceptor'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['analyst'] ?: '—'); ?></td>
                    <td class="rsk-fs76"><?php echo htmlspecialchars((string) $x['compensating_ctl'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars((string) $x['review_due']); ?></td>
                    <td><?php echo $late ? '<span class="badge badge-warning">' . (int) $x['late_days'] . ' يوما</span>' : '—'; ?></td>
                    <td class="rsk-cell-w200"><?php echo htmlspecialchars(mb_substr((string) $x['note'], 0, 80) ?: '—'); ?></td>
                    <td class="rsk-fs74"><?php echo htmlspecialchars((string) $x['parent_ref'] ?: '—'); ?></td>
                    <td><?php echo !empty($x['withdrawn_at'])
                            ? '<span class="badge badge-secondary">مسحوب</span>' : '<span class="badge badge-success">نافذ</span>'; ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="risk_card.php?id=<?php echo (int) $x['risk_id']; ?>">ملف الخطر</a>
                        <?php
                        /* ◆ INJ-0391 · **زرُّ السحبِ للنافذِ وحدَه**: العمودانِ
                             `withdrawn_by/at` كانا يُصيَّرانِ ولا يُكتبان — فالجدولُ
                             يعرض «مسحوب/نافذ» ولا سبيلَ إلى السحب. والآن للنافذِ بابٌ. */
                        if (empty($x['withdrawn_at'])): ?>
                        <form method="post" action="risk_actions.php" class="ems-inline-form">
                            <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                            <input type="hidden" name="action" value="risk_accept_withdraw">
                            <input type="hidden" name="acceptance_id" value="<?php echo (int) $x['id']; ?>">
                            <input type="text" name="note" required minlength="3" class="ems-note-inline"
                                   placeholder="سبب السحب">
                            <button type="submit" class="btn btn-sm btn-secondary"
                                    title="سحب القبول — حركة تختم بمن سحبها ومتى، ولا تمحو القرار الأول">
                                اسحب القبول
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>
</div>
</body>
</html>
