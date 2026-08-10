<?php
/**
 * Contracts/contract_card.php — بطاقةُ العقد الأم (M-47 · الشاشة 200)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-08 §5 · CON-02 §7: البطاقةُ بتبويباتها السبعة — «تجميعُ Views لمصادرَ
 * قائمة» بدل الشاشات المتفرقة (تفاصيل · ملاحق · التزامات · مستخلصات).
 * قراءةٌ حيةٌ بروابط الأصل — لا نسخَ ولا تخزين.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Contract/CommercialBoardService.php';

use App\Services\Contract\CommercialBoardService as CBD;

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super     = ($current_role === '-1');
$company_id   = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$co = $company_id;
$gate = $is_super ? ems_tenant_db()->forAllTenants('contract card super') : ems_tenant_db();

$cid = intval($_GET['id'] ?? 0);
$c = $cid > 0 ? $gate->selectOne('contracts', array('where' => array('id' => $cid))) : null;
$tab = in_array(strval($_GET['tab'] ?? '1'), array('1','2','3','4','5','6','7','8'), true)
     ? strval($_GET['tab'] ?? '1') : '1';

$TABS = array('1' => 'الرأس والحالة', '2' => 'البنود والقيمة', '3' => 'الخطط الثلاث',
              '4' => 'الملاحق والالتزامات', '5' => 'المستخلصات والفواتير',
              '6' => 'الضمانات والمقدمات', '7' => 'الاقتصاد',
              '8' => 'المقاعد والفجوة');

$page_title = 'إيكوبيشن | بطاقة العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بطاقة العقد الأم'; $header_icon = 'fa fa-file-contract';
    $header_actions = array();
    $header_back = array('href' => 'contracts.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'العقود');
    include('../includes/page_header.php');
    ems_screen_about('بطاقةُ العقد الواحدة بتبويباتها السبعة — تجميعُ قراءاتٍ حيةٍ من مصادرها '
        . 'القائمة بروابط أصلها: لا شاشاتٍ متفرقةً بعد اليوم.', array());
    ?>

    <?php if (!$c): ems_state_empty('اختر عقدًا', 'إلى العقود', 'contracts.php'); ?>
    <?php else: ?>
    <div class="card"><div class="card-body" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <strong style="font-size:1.1rem">عقد #<?php echo $cid; ?> —
            <?php echo htmlspecialchars((string)($c['second_party'] ?? '')); ?></strong>
        <span class="badge badge-secondary"><?php echo htmlspecialchars((string)$c['contract_status']); ?></span>
        <span style="margin-inline-start:auto">
        <?php foreach ($TABS as $tk => $tl): ?>
            <a class="btn btn-sm" style="border:1px solid #ddd;border-radius:6px;padding:4px 10px;margin:0 2px;<?php
                echo $tk === $tab ? 'background:#e2b93b;font-weight:800' : ''; ?>"
               href="?id=<?php echo $cid; ?>&tab=<?php echo $tk; ?>"><?php echo $tl; ?></a>
        <?php endforeach; ?></span>
    </div></div>

    <div class="card"><div class="card-body">
    <?php
    switch ($tab) {
        case '1':
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%"><tbody>';
            foreach (array('first_party' => 'الطرف الأول', 'second_party' => 'الطرف الثاني',
                           'contract_status' => 'حالة العلاقة (H-02)',
                           'contract_signing_date' => 'التوقيع', 'actual_start' => 'البدء',
                           'actual_end' => 'الانتهاء', 'price_currency_contract' => 'العملة',
                           'project_id' => 'المشروع') as $k => $lbl) {
                if (array_key_exists($k, $c)) {
                    echo '<tr><th>' . $lbl . '</th><td>' . htmlspecialchars((string)($c[$k] ?? '—')) . '</td></tr>';
                }
            }
            // خطُّ الأساس (P-10) ودورةُ الحياة (P-11) — حالتان لا حالة
            $b = $conn->query("SELECT state FROM contract_baseline WHERE company_id={$co}
                                AND contract_id={$cid} ORDER BY id DESC LIMIT 1")->fetch_assoc();
            echo '<tr><th>خطُّ الأساس (P-10)</th><td>' . htmlspecialchars($b['state'] ?? 'غيرُ مفتوح')
               . ' — <a href="contract_baseline.php">الشاشة ▸</a></td></tr>';
            $lc = $conn->query("SELECT COUNT(*) n FROM contract_lifecycle_events
                                 WHERE company_id={$co} AND contract_id={$cid}")->fetch_assoc();
            echo '<tr><th>وقائعُ دورة الحياة (P-11)</th><td>' . intval($lc['n'])
               . ' — <a href="contract_lifecycle.php">الشاشة ▸</a></td></tr>';
            echo '</tbody></table></div>'
               . '<p><a class="btn-primary" href="contracts_details.php?id=' . $cid . '">التفاصيل الكاملة ▸</a></p>';
            break;
        case '2':
            $rows = array();
            $r = $conn->query("SELECT line_no, pricing_model, description, qty_contracted,
                                      unit_price, currency, state, valid_from, valid_to
                                 FROM client_contract_lines
                                WHERE company_id={$co} AND contract_id={$cid}
                                  AND COALESCE(is_deleted,0)=0 ORDER BY line_no");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا بنودَ بيعٍ — العقدُ قبل خط الأساس', 'افتح البنود', 'contract_lines.php?contract_id=' . $cid); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>بند</th><th>النموذج</th><th>الوصف</th><th>الكمية</th><th>مصدر سعر الصرف</th><th>الحال</th><th>السريان</th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . intval($x['line_no']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['pricing_model']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['description']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['qty_contracted']) . '</td>'
                   . '<td>' . htmlspecialchars($x['unit_price'] . ' ' . $x['currency']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td>'
                   . '<td>' . htmlspecialchars($x['valid_from'] . ' → ' . ($x['valid_to'] ?: 'مفتوح')) . '</td></tr>';
            }
            echo '</tbody></table></div>'
               . '<p><a class="btn-primary" href="contract_lines.php?contract_id=' . $cid . '">شاشة البنود ▸</a></p>';
            break;
        case '3':
            foreach (array(
                array('الجدول الشهري (P-03)', "SELECT COUNT(*) n, MIN(period_month) f, MAX(period_month) t
                       FROM contract_monthly_plan WHERE company_id={$co} AND contract_id={$cid}",
                      'contract_monthly_plan.php'),
                array('خطة الموارد (P-04)', "SELECT COUNT(*) n, NULL f, NULL t
                       FROM contract_resource_plan WHERE company_id={$co} AND contract_id={$cid}",
                      'contract_resource_plan.php'),
                array('خطة الدفع (P-05)', "SELECT COUNT(*) n, NULL f, NULL t
                       FROM contract_payment_schedule WHERE company_id={$co} AND contract_id={$cid}",
                      'contract_payment_schedule.php'),
            ) as $pl) {
                $x = $conn->query($pl[1])->fetch_assoc();
                echo '<div class="alert alert-info" style="display:flex;justify-content:space-between">'
                   . '<span><strong>' . $pl[0] . ':</strong> ' . intval($x['n']) . ' سطرًا'
                   . ($x['f'] !== null ? (' (' . $x['f'] . ' → ' . $x['t'] . ')') : '') . '</span>'
                   . '<a href="' . $pl[2] . '?contract_id=' . $cid . '">الشاشة ▸</a></div>';
            }
            break;
        case '4':
            $am = $conn->query("SELECT COUNT(*) n FROM contract_amendments
                                 WHERE company_id={$co} AND contract_id={$cid}")->fetch_assoc();
            $ob = $conn->query("SELECT COUNT(*) n FROM contract_obligations
                                 WHERE company_id={$co} AND contract_id={$cid}")->fetch_assoc();
            echo '<div class="alert alert-info">الملاحق: <strong>' . intval($am['n'])
               . '</strong> — <a href="contracts_details.php?id=' . $cid . '">شاشتها ▸</a></div>'
               . '<div class="alert alert-info">الالتزامات (المصفوفة): <strong>' . intval($ob['n'])
               . '</strong> — <a href="contract_obligations.php?contract_id=' . $cid . '">شاشتها ▸</a></div>';
            break;
        case '5':
            $rows = array();
            $r = $conn->query("SELECT c.id, c.claim_no, c.state, c.net_amount, c.currency,
                                      c.period_from, c.period_to, t.serial_no
                                 FROM claims c
                                 LEFT JOIN tax_invoices t ON t.claim_id = c.id AND t.state='issued'
                                WHERE c.company_id={$co} AND c.contract_id={$cid}
                                  AND COALESCE(c.is_deleted,0)=0 ORDER BY c.id DESC LIMIT 50");
            while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
            if (!$rows) { ems_state_empty('لا مستخلصاتٍ بعدُ', 'إلى المستخلصات', 'claims.php'); break; }
            echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
               . '<thead><tr><th>مهلة اعتماد المستخلص</th><th>الفترة</th><th>الصافي</th><th>الحال</th><th>فاتورته</th><th></th></tr></thead><tbody>';
            foreach ($rows as $x) {
                echo '<tr><td>' . htmlspecialchars((string)$x['claim_no']) . '</td>'
                   . '<td>' . htmlspecialchars($x['period_from'] . ' → ' . $x['period_to']) . '</td>'
                   . '<td>' . htmlspecialchars($x['net_amount'] . ' ' . $x['currency']) . '</td>'
                   . '<td>' . htmlspecialchars((string)$x['state']) . '</td>'
                   . '<td>' . ($x['serial_no'] !== null
                        ? htmlspecialchars((string)$x['serial_no']) : '—') . '</td>'
                   . '<td><a href="claims.php?open=' . intval($x['id']) . '">افتح ▸</a></td></tr>';
            }
            echo '</tbody></table></div>';
            break;
        case '6':
            $g = $conn->query("SELECT kind, nature, amount, currency, due_release_date, state
                                 FROM contract_guarantees
                                WHERE company_id={$co} AND contract_id={$cid}
                                  AND COALESCE(is_deleted,0)=0");
            $any = false;
            while ($g && ($x = $g->fetch_assoc())) {
                $any = true;
                echo '<div class="alert alert-info">' . htmlspecialchars($x['kind'] . ' (' . $x['nature'] . ') — '
                   . $x['amount'] . ' ' . $x['currency']
                   . ($x['due_release_date'] !== null ? (' · ردُّه ' . $x['due_release_date']) : '')
                   . ' · ' . $x['state'])
                   . ((string)$x['nature'] === 'off_balance'
                      ? ' <span class="badge badge-warning">خارج الميزانية — لا يظهر رقمًا</span>' : '')
                   . '</div>';
            }
            $adv = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(amount),0),2) v
                                  FROM contract_advances
                                 WHERE company_id={$co} AND contract_id={$cid}
                                   AND state='recorded' AND COALESCE(is_deleted,0)=0")->fetch_assoc();
            if (intval($adv['n']) > 0) {
                $any = true;
                require_once __DIR__ . '/advance_helpers.php';
                $ab = advance_balance($gate, $cid);
                echo '<div class="alert alert-info">المقدماتُ: ' . intval($adv['n']) . ' بمجموع '
                   . htmlspecialchars((string)$adv['v']) . ' — **المتبقي '
                   . htmlspecialchars((string)$ab['balance']) . '** (دفتر M-01)</div>';
            }
            if (!$any) { ems_state_empty('لا ضماناتٍ ولا مقدماتٍ لهذا العقد', 'إلى الضمانات', 'contract_guarantees.php'); }
            break;
        case '7':
            $row = CBD::row($gate, $cid);
            if (!$row['ok']) { ems_state_error('تعذّرت قراءةُ اللوحة'); break; }
            echo '<div style="display:flex;gap:12px;flex-wrap:wrap">';
            foreach (array('planned' => 'المخطَّط', 'executed' => 'المنفَّذ',
                           'billed' => 'المفوتَر', 'collected' => 'المحصَّل') as $k => $lbl) {
                echo '<div class="badge badge-secondary" style="font-size:15px;padding:10px 16px">'
                   . $lbl . ': <strong>' . htmlspecialchars((string)$row[$k]) . '</strong></div>';
            }
            echo '</div><p style="margin-top:10px;color:#666">' . htmlspecialchars((string)$row['note'])
               . '</p><p><a class="btn-primary" href="commercial_board.php">اللوحة التجارية الكاملة ▸</a></p>';
            break;
        case '8':
            // N-20 لوحة العقد الكاملة: المقاعد والفجوة والتعاقب + ترويسة الأرقام الأربعة —
            // توسعةُ ملف العقد لا شاشة ثانية (SCR-02 §4.1)، وكل رقمٍ من خدمته المالكة.
            require_once dirname(__DIR__) . '/app/Services/ContractSeatService.php';
            $row = CBD::row($gate, $cid);
            if ($row['ok']) {
                echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">';
                foreach (array('planned' => 'المخطَّط', 'executed' => 'المنفَّذ',
                               'billed' => 'المفوتَر', 'collected' => 'المحصَّل') as $k => $lbl) {
                    echo '<div class="badge badge-secondary" style="font-size:14px;padding:8px 14px">'
                       . $lbl . ': <strong>' . htmlspecialchars((string)$row[$k]) . '</strong></div>';
                }
                echo '</div>';
            }
            $gap = \App\Services\ContractSeatService::seatGap($gate, $co, $cid);
            echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">'
               . '<div class="badge badge-info" style="font-size:14px;padding:8px 14px">المقاعد المتعاقدة: <strong>' . intval($gap['seats_contracted']) . '</strong></div>'
               . '<div class="badge badge-success" style="font-size:14px;padding:8px 14px">المملوءة: <strong>' . intval($gap['seats_filled']) . '</strong></div>'
               . '<div class="badge ' . ($gap['seat_gap'] > 0 ? 'badge-warning' : 'badge-secondary') . '" style="font-size:14px;padding:8px 14px">الفجوة: <strong>' . intval($gap['seat_gap']) . '</strong></div>'
               . '</div>';
            if (!empty($gap['empty_seats'])) {
                echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
                   . '<thead><tr><th>المقعد الفارغ</th><th>نوعه</th><th>دلالته — والمطالبة من العقد لا تُفترض</th></tr></thead><tbody>';
                foreach ($gap['empty_seats'] as $es) {
                    echo '<tr><td>#' . intval($es['seat_no']) . '</td><td>' . htmlspecialchars((string)$es['seat_kind'])
                       . '</td><td>' . htmlspecialchars((string)$es['implication']) . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            // تعاقب المعدات على مقاعد العقد
            $seats = $gate->scopedQuery(array('scope' => array('c2' => 'op_containers')),
                "SELECT c2.id, c2.seat_no, c2.seat_kind FROM op_containers c2
                  WHERE {TENANT_SCOPE} AND c2.contract_id = ? AND c2.seat_no IS NOT NULL
                    AND COALESCE(c2.is_deleted,0)=0 ORDER BY c2.seat_no", array($cid));
            if (empty($seats)) {
                ems_state_empty('لا مقاعدَ معرَّفةً لهذا العقد بعد — تُعرَّف على حاويات المعدات (N-11)', 'إلى الحاويات', 'containers.php');
            } else {
                echo '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
                   . '<thead><tr><th>المقعد</th><th>المعدة</th><th>المُنشئ — الاسم والصفة</th><th>إلى</th><th>سبب الاستبدال</th><th>الصفة</th><th>السائقون</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم العقد</th>
              <th class="ems-fn-th" data-fn="1">عملة التسعير</th>
              <th class="ems-fn-th" data-fn="1">عملة الفوترة</th>
              <th class="ems-fn-th" data-fn="1">عملة التحصيل</th>
              <th class="ems-fn-th" data-fn="1">نسبة المقدم</th>
              <th class="ems-fn-th" data-fn="1">طريقة استهلاك المقدم</th>
              <th class="ems-fn-th" data-fn="1">مهلة السداد</th>
              <th class="ems-fn-th" data-fn="1">دورية الإقفال</th>
              <th class="ems-fn-th" data-fn="1">نسبة محتجز الضمان</th>
              <th class="ems-fn-th" data-fn="1">مدة رد المحتجز</th>
              <th class="ems-fn-th" data-fn="1">حق تعليق العمل</th>
              <th class="ems-fn-th" data-fn="1">شرط الإنهاء</th>
              <th class="ems-fn-th" data-fn="1">حالة الضريبة</th>
              <th class="ems-fn-th" data-fn="1">ثبّتها</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التثبيت</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              </tr></thead><tbody>';
                foreach ($seats as $s) {
                    $succ = \App\Services\ContractSeatService::successionOf($gate, $co, intval($s['id']));
                    if (empty($succ)) {
                        echo '<tr><td>#' . intval($s['seat_no']) . '</td><td colspan="6" style="color:#999">فارغ — لم تجلس فيه معدة</td></tr>';
                        continue;
                    }
                    foreach ($succ as $a) {
                        echo '<tr><td>#' . intval($s['seat_no']) . '</td>'
                           . '<td><a href="../Equipments/equipments.php?id=' . intval($a['equipment_id']) . '">معدة #' . intval($a['equipment_id']) . '</a></td>'
                           . '<td>' . htmlspecialchars((string)$a['date_from']) . '</td>'
                           . '<td>' . ($a['date_to'] !== null ? htmlspecialchars((string)$a['date_to']) : '<em>مفتوح</em>') . '</td>'
                           . '<td>' . htmlspecialchars((string)($a['replace_reason'] ?? '—')) . '</td>'
                           . '<td>' . htmlspecialchars((string)$a['assignment_role']) . '</td>'
                           . '<td>' . intval($a['drivers_count']) . '</td></tr>';
                    }
                }
                echo '</tbody></table></div>';
            }
            // التعطل بأطرافه من سجل الأداء (N-12) — كل فجوة بمالكها المسمّى
            $dt = $gate->scopedQuery(array('scope' => array('m' => 'monthly_performance'),
                                           'enrich' => array('d' => 'monthly_performance_downtime')),
                "SELECT m.period, d.reason_code, d.hours, d.bearer_party FROM monthly_performance m
                   LEFT JOIN monthly_performance_downtime d ON d.perf_id = m.id
                  WHERE {TENANT_SCOPE} AND m.contract_id = ? ORDER BY m.period DESC LIMIT 24", array($cid));
            if (!empty($dt) && $dt[0]['reason_code'] !== null) {
                echo '<h6 style="margin-top:12px">التعطل بأطرافه (سجل الأداء N-12)</h6>'
                   . '<div class="table-container"><table class="alltables display" data-no-dt="1" style="width:100%">'
                   . '<thead><tr><th>الشهر</th><th>السبب</th><th>الساعات</th><th>الطرف المتحمل</th></tr></thead><tbody>';
                foreach ($dt as $x) {
                    if ($x['reason_code'] === null) { continue; }
                    echo '<tr><td>' . htmlspecialchars((string)$x['period']) . '</td><td>' . htmlspecialchars((string)$x['reason_code'])
                       . '</td><td>' . htmlspecialchars((string)$x['hours']) . '</td><td><strong>' . htmlspecialchars((string)$x['bearer_party']) . '</strong></td></tr>';
                }
                echo '</tbody></table></div>';
            }
            break;
    }
    ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
