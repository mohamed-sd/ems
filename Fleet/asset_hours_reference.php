<?php
/**
 * Fleet/asset_hours_reference.php — مرجعُ ساعاتِ التشغيلِ للإهلاك (FLEET-30)
 * ───────────────────────────────────────────────────────────────────────────
 * **مشتقٌّ كليًّا — لا إدخال** (‏`FLEET-30` نصًّا: «الأسطولُ/التشغيلُ يملك
 * الساعاتِ والعدّادَ، والتكلفةُ للمالية») — Grain: **أصلٌ × شهر**.
 *
 * ◆ **المصدرُ الواحد**: `asset_hour_reconciliations` — يكتبه
 *   `AssetHoursService` وحدَه (‏من `unit_time_log` المعتمدةِ)، وهذه الشاشةُ
 *   **قراءةٌ صِرفٌ**: لا POST ولا فعلَ كتابةٍ واحدًا.
 * ◆ **وقاعدةُ كلِّ مشتقٍّ مسمّاةٌ في الشاشة** (‏نمطُ `asset_readiness`):
 *   - يُهلك؟ = ملكيّةُ الشركةِ وطريقةُ إهلاكٍ معلنة — **معدّةُ الموردِ لا
 *     تُهلك عندنا** (‏CK-18).
 *   - الساعاتُ المتراكمةُ = مجموعُ `hours_from_shifts` حتى الشهرِ نفسِه للأصل.
 *   - المتبقيةُ = `useful_life_hours` − المتراكمة (‏لطريقةِ ساعاتِ التشغيل).
 *   - حالةُ الترحيل = `depreciation_amount` مقيَّدٌ ⇒ «رُحِّل» · ساعاتٌ غيرُ
 *     مُهلكةٍ > 0 ⇒ «بانتظار الترحيل».
 * ◆ نوعُ الملكيّةِ **قراءةٌ من إدارةٍ مالكةٍ أخرى** (‏الأسطول ⇐ سجلُّ الأصل)
 *   كما ينصُّ الملفُّ التصميميُّ (`IMPORTED_READONLY`).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Fleet/asset_hours_reference.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('asset hours reference super') : ems_tenant_db();
$period = isset($_GET['period']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['period']) : '';
$assetQ = isset($_GET['asset']) ? intval($_GET['asset']) : 0;

$rows = array(); $periods = array(); $equip = array();
try {
    $opts = array('orderBy' => 'period DESC, equipment_id ASC', 'limit' => 500);
    $w = array();
    if ($period !== '') { $w['period'] = $period; }
    if ($assetQ > 0) { $w['equipment_id'] = $assetQ; }
    if ($w) { $opts['where'] = $w; }
    $rows = $gate->select('asset_hour_reconciliations', $opts);
} catch (\Throwable $t) { error_log('asset_hours_reference list: ' . $t->getMessage()); }
try { foreach ($gate->select('asset_hour_reconciliations', array('columns' => array('period'), 'orderBy' => 'period DESC', 'limit' => 400)) as $p) { $periods[(string) $p['period']] = true; } }
catch (\Throwable $t) { error_log('asset_hours_reference periods: ' . $t->getMessage()); }
try { foreach ($gate->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id ASC', 'limit' => 500)) as $e0) { $equip[(int) $e0['id']] = trim(($e0['code'] ?? '') . ' — ' . ($e0['name'] ?? '')); } }
catch (\Throwable $t) { error_log('asset_hours_reference equip: ' . $t->getMessage()); }

/* **المتراكمةُ حتى الشهرِ نفسِه** — تُحسب من الصفوفِ المعروضةِ نفسِها بقراءةٍ
   ثانيةٍ للأصلِ كي لا تتأثّر بمرشِّحِ الفترة: المصدرُ واحدٌ والقاعدةُ ظاهرة. */
$cumByAsset = array();
try {
    foreach ($gate->select('asset_hour_reconciliations', array('columns' => array('equipment_id', 'period', 'hours_from_shifts'), 'orderBy' => 'period ASC', 'limit' => 5000)) as $r0) {
        $eq0 = (int) $r0['equipment_id'];
        $prev = isset($cumByAsset[$eq0]) ? $cumByAsset[$eq0] : array();
        $last = $prev ? end($prev) : 0.0;
        $prev[(string) $r0['period']] = $last + (float) $r0['hours_from_shifts'];
        $cumByAsset[$eq0] = $prev;
    }
} catch (\Throwable $t) { error_log('asset_hours_reference cum: ' . $t->getMessage()); }

$n = count($rows); $sumMonth = 0.0; $posted = 0; $noDep = 0;
foreach ($rows as $r0) {
    $sumMonth += (float) $r0['hours_from_shifts'];
    if ($r0['depreciation_amount'] !== null) { $posted++; }
    if ((string) $r0['owner_type'] === 'supplier') { $noDep++; }
}

$page_title = 'إيكوبيشن | مرجع ساعات التشغيل للإهلاك';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'مرجع ساعات التشغيل للإهلاك: أصل في شهر، مشتق كليا بلا إدخال'; $header_icon = 'fa fa-clock-rotate-left'; $header_actions = array();
    $header_back = array('href' => 'readiness_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الجاهزية');
    include('../includes/page_header.php'); ?>
    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $n ?></div><div class="ems-stat-label">صفوف مشتقة، اصل في شهر</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($sumMonth, 1) ?></div><div class="ems-stat-label">ساعات الشهر المعروضة من الورديات المعتمدة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $posted ?></div><div class="ems-stat-label">صفوف رحل اهلاكها بقيد ذي قيمة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= $noDep ?></div><div class="ems-stat-label">ملكية مورد لا تهلك عندنا</div></div>
    </div>

    <div class="ems-filter-box">
        <form method="get" class="ems-filters">
            <div class="ems-filter-item">
                <label>الشهر</label>
                <select name="period" onchange="this.form.submit()">
                    <option value="">كل الشهور</option>
                    <?php foreach (array_keys($periods) as $p0): ?>
                    <option value="<?= htmlspecialchars($p0) ?>" <?= $p0 === $period ? 'selected' : '' ?>><?= htmlspecialchars($p0) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ems-filter-item">
                <label>الأصل</label>
                <select name="asset" onchange="this.form.submit()">
                    <option value="0">كل الأصول</option>
                    <?php foreach ($equip as $eid => $enm): ?>
                    <option value="<?= $eid ?>" <?= $eid === $assetQ ? 'selected' : '' ?>><?= htmlspecialchars($enm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr>
                    <th>كود السجل</th>
                    <th>كود الأصل</th>
                    <th>الشهر</th>
                    <th>نوع الملكية</th>
                    <th>يهلك؟</th>
                    <th>الطاقة الإنتاجية (ساعة)</th>
                    <th>الساعات المتراكمة</th>
                    <th>الساعات المتبقية</th>
                    <th>ساعات الشهر</th>
                    <th>حالة الترحيل</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r0):
                $eq0 = (int) $r0['equipment_id'];
                $own = (string) $r0['owner_type'];
                $dep = ($own === 'company' && (string) $r0['depr_method'] !== '');
                $cum = isset($cumByAsset[$eq0][(string) $r0['period']]) ? $cumByAsset[$eq0][(string) $r0['period']] : (float) $r0['hours_from_shifts'];
                $life = (int) $r0['useful_life_hours'];
                $left = ($dep && $life > 0) ? max(0, $life - $cum) : null;
                $post = ($r0['depreciation_amount'] !== null) ? 'رحل'
                      : (((float) $r0['hours_undepreciated']) > 0 ? 'بانتظار الترحيل' : 'غير منطبق');
            ?>
                <tr>
                    <td><?= (int) $r0['rec_id'] ?></td>
                    <td><?= htmlspecialchars(isset($equip[$eq0]) ? $equip[$eq0] : ('#' . $eq0)) ?></td>
                    <td><?= htmlspecialchars((string) $r0['period']) ?></td>
                    <td><?= $own === 'supplier' ? 'مورد' : 'الشركة' ?></td>
                    <td><?= $dep ? 'نعم' : 'لا: ' . ($own === 'supplier' ? 'معدة مورد' : 'بلا طريقة اهلاك') ?></td>
                    <td><?= $life > 0 ? number_format($life) : 'غير منطبق' ?></td>
                    <td><?= number_format($cum, 1) ?></td>
                    <td><?= $left === null ? 'غير منطبق' : number_format($left, 1) ?></td>
                    <td><?= number_format((float) $r0['hours_from_shifts'], 1) ?></td>
                    <td><?= htmlspecialchars($post) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="10">لا صفوف مشتقة بعد. المصدر سجل مطابقة ساعات الاصول، يملؤه محرك الساعات من الورديات المعتمدة</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        <strong>قواعد الاشتقاق بمصادرها لا بارقام معلقة:</strong>
        (يهلك؟) = ملكية الشركة وطريقة اهلاك معلنة، ومعدة المورد لا تهلك عندنا.
        (المتراكمة) = مجموع ساعات الورديات المعتمدة حتى الشهر نفسه للاصل.
        (المتبقية) = الطاقة الانتاجية ناقص المتراكمة، لطريقة ساعات التشغيل.
        (حالة الترحيل) = قيد اهلاك بقيمة يعني رحل، وساعات غير مهلكة تعني بانتظار الترحيل.
        المصدر الواحد سجل مطابقة ساعات الاصول، ولا ادخال من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
