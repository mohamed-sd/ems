<?php
/**
 * Procurement/wh_track_quality.php — جودة بيانات التتبع (RPR-W09 · DEC-OPEN-15 ㉚ ㉛)
 * ───────────────────────────────────────────────────────────────────────────
 * **«لا تجعل وضعَ الانتقالِ عذرًا دائمًا»** (‏نصُّ القرار ㉚): يجب أن نستطيع قياسَ
 * نسبةِ الأصنافِ التي لها دفعاتٌ وتواريخُ تصنيعٍ وصلاحيةٍ وترقيمٌ تسلسليٌّ
 * ورصيدٌ موروثٌ غيرُ متتبَّع — **حتّى نعرف أنَّ البياناتِ تتحسَّن**.
 *
 * ⛔ **وهذه جودةُ بياناتٍ لا حواجب** (㉛): كلُّ رقمٍ هنا **مؤشِّرُ نضجٍ** يُقرأ
 *   ويُتابَع، **ولا يمنع استلامًا ولا صرفًا ولا تحويلًا ولا جردًا**. والخلطُ
 *   بينهما هو الفرقُ بين إدارةِ الجودةِ وتعطيلِ التشغيل.
 *
 * ◆ **وقيدُ النقصِ يُسجَّل ولا يُوقف**: كلُّ عمليّةٍ مضت بنقصٍ اختياريٍّ تركت
 *   سطرًا في `proc_track_gap` — فنعرف أيَّ الأصنافِ يحتاج استكمالَ بياناته.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/w7_codes.php';
require_once __DIR__ . '/../app/Services/Warehouse/TrackingPolicyService.php';

enforce_current_page_view_permission($conn, '../main/dashboard.php');

$is_super = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Procurement/wh_track_quality.php');
$gate = $is_super ? ems_tenant_db()->forAllTenants('track_quality super') : ems_tenant_db();

$q = array('items' => 0);
try { $q = \App\Services\Warehouse\TrackingPolicyService::dataQuality($gate); }
catch (\Throwable $t) { error_log('track_quality: ' . $t->getMessage()); }

$gaps = array(); $items = array();
try { $gaps = $gate->select('proc_track_gap', array('where' => array('resolved' => 0), 'orderBy' => 'id DESC', 'limit' => 300)); }
catch (\Throwable $t) { error_log('track_quality gaps: ' . $t->getMessage()); }
try { foreach ($gate->select('proc_item', array('columns' => array('id', 'name', 'category'), 'limit' => 900)) as $i) { $items[(int) $i['id']] = $i; } }
catch (\Throwable $t) { error_log('track_quality items: ' . $t->getMessage()); }

$n = max(1, (int) $q['items']);
$pct = function ($v) use ($n) { return number_format(($v / $n) * 100, 1); };

$METRICS = array(
    array('أصناف لها سياسة تتبع محلولة', (int) $q['with_policy']),
    array('أصناف بتخصيص على مستواها', (int) $q['item_scoped']),
    array('أصناف تتبع الدفعة', (int) $q['lot_on']),
    array('أصناف تتبع الرقم التسلسلي', (int) $q['serial_on']),
    array('أصناف تتبع تاريخ التصنيع', (int) $q['mfg_on']),
    array('أصناف تتبع الصلاحية', (int) $q['expiry_on']),
    array('أصناف تتبع الضمان', (int) $q['warranty_on']),
    array('أصناف لها دفعات مسجلة فعلا', (int) $q['with_lot_rows']),
    array('أصناف لها أرقام تسلسلية مسجلة فعلا', (int) $q['with_serial_rows']),
);

$page_title = 'إيكوبيشن | جودة بيانات التتبع';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'جودة بيانات التتبع'; $header_icon = 'fa fa-chart-simple'; $header_actions = array();
    $header_back = array('href' => 'proc_track_policy.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سياسة التتبع');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= (int) $q['items'] ?></div><div class="ems-stat-label">أصناف الدليل</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= (int) $q['with_policy'] ?></div><div class="ems-stat-label">لها سياسة محلولة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= (int) $q['required_any'] ?></div><div class="ems-stat-label">خصائص إلزامية</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= (int) $q['open_gaps'] ?></div><div class="ems-stat-label">قيود نقص مفتوحة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= htmlspecialchars(number_format((float) $q['legacy_qty'], 3)) ?></div><div class="ems-stat-label">رصيد موروث غير متتبع</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا أصناف في الدليل',
        'هذه الأرقام مؤشرات نضج تقرأ وتتابع. ولا يمنع أي منها استلاما ولا صرفا'); ?>

    <h3 class="ems-section-title">نسب اكتمال البيانات</h3>
    <?php /* صندوقُ الفلترةِ المعياريُّ — مكوّنٌ واحدٌ مشترَك (‏حكمُ المالك ⑦) */
    require_once __DIR__ . '/../includes/ems_filter_box.php';
    ems_filter_box(array('for' => '#emsList_wh_track_quality')); ?>
    <table id="emsList_wh_track_quality" class="data-table">
        <thead><tr><th>المؤشر</th><th>العدد</th><th>النسبة من الدليل</th></tr></thead>
        <tbody>
        <?php foreach ($METRICS as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m[0]) ?></td>
                <td><?= (int) $m[1] ?></td>
                <td><?= htmlspecialchars($pct((int) $m[1])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <h3 class="ems-section-title">قيود النقص المفتوحة</h3>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الصنف</th><th>الفئة</th><th>العملية</th><th>مرجع العملية</th><th>الناقص</th><th>درجة السياسة</th><th>سجل في</th></tr></thead>
        <tbody>
        <?php if ($gaps): foreach ($gaps as $g): $it = isset($items[(int) $g['item_id']]) ? $items[(int) $g['item_id']] : null; ?>
            <tr>
                <td><?= htmlspecialchars($it ? (string) $it['name'] : ('#' . (int) $g['item_id'])) ?></td>
                <td><?= htmlspecialchars($it ? (string) $it['category'] : '') ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $g['op_kind'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $g['op_ref']) ?></td>
                <td><?= htmlspecialchars((string) $g['missing']) ?></td>
                <td><?= htmlspecialchars(ems_w7_ar((string) $g['policy_level'], $conn)) ?></td>
                <td><?= htmlspecialchars((string) $g['logged_at']) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7">لا قيود نقص مفتوحة</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
