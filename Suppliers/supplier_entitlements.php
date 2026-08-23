<?php
/**
 * Suppliers/supplier_entitlements.php — SUP-18 · الاستحقاقات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تبويبٌ داخل التسويات مشتقٌّ من الأداءِ والبنود — لا شاشةٌ مستقلة** (نصُّ
 *   المتطلب). فلا بندَ تنقّلٍ له، ويُبلَغ من شريطِ ملفِّ التسويةِ وحدَه.
 *
 * ◆ **وصفرُ حقلِ إدخالٍ في جسمِه** — معيارُه «صفر استحقاقٍ مُدخَلٍ يدويًّا».
 *   والتصفيةُ **روابطُ لا نماذج**: حقلٌ واحدٌ قابلٌ للكتابة يفتح بابًا يمنعه
 *   النصّ، ولو لم يُكتَب فيه شيء.
 *
 * ◆ **ومكوّناتُه الستةُ ثلاثةُ أطرافٍ في عمودَين**: لكلِّ طرفٍ (عميل · مورد ·
 *   مشغّل) **حكمٌ ومبلغ**. والحكمُ نصٌّ مرجعيٌّ لا حالةُ تعداد.
 *
 * ◆ **والمرجعُ المعدومُ يُوسَم لا يُخفى**: `unit_record_id` قد يشير إلى سجلِّ
 *   وحدةٍ **لا وجودَ له** — وهو أخطرُ من «غيرِ معتمَد» لأنَّه يبدو موصولًا.
 *   ووصلةٌ داخليةٌ في العرضِ كانت **تُسقط الصفَّ فتُخفي العطب**؛ فالوصلُ يسريّ
 *   والصفُّ يظهر بوسمِه. (قِيس: الداخليةُ تُبلغ واحدًا والخارجيةُ ثلاثةَ عشر.)
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/entity_tabs.php';
echo ems_entity_tabs('settlement', 'الاستحقاقات');

if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

$ENT_PERIOD = isset($_GET['period']) ? preg_replace('~[^0-9\-]~', '', (string) $_GET['period']) : '';
if (mb_strlen($ENT_PERIOD) > 7) { $ENT_PERIOD = mb_substr($ENT_PERIOD, 0, 7); }

/* ── القراءةُ عبرَ بوابةِ المستأجِر — والوصلُ يسريٌّ ليظهر المرجعُ المعدوم ── */
$gate = ems_tenant_db();
$opts = array('orderBy' => 'period DESC, entitle_code ASC', 'limit' => 300);
if ($ENT_PERIOD !== '') { $opts['where'] = array('period' => $ENT_PERIOD); }
$ENT_ROWS = $gate->select('fin_entitlements', $opts);

/* سجلاتُ الوحداتِ المرجعيةُ — تُقرأ مرّةً ويُطابَق عليها بلا وصلٍ يُسقط صفًّا */
$ENT_UNITS = array();
foreach ($gate->select('fin_unit_records', array('limit' => 2000)) as $u) {
    $ENT_UNITS[(int) $u['id']] = $u;
}
$ENT_PERIODS = array();
foreach ($gate->select('fin_entitlements', array('limit' => 2000)) as $e) {
    if ($e['period'] !== null && $e['period'] !== '') { $ENT_PERIODS[(string) $e['period']] = 1; }
}
krsort($ENT_PERIODS);

$page_title = 'إيكوبيشن | استحقاقات الموردين';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }

$e = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$dangling = 0;
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title   = 'الاستحقاقات';
    $header_icon    = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    $header_back    = array('href' => 'settlements.php', 'class' => '', 'label' => 'التسويات وكشف الحساب');
    include __DIR__ . '/../includes/page_header.php';
    ?>

    <div class="card"><div class="card-body">
        <p class="ent-note">
            <i class="fas fa-circle-info"></i>
            <strong>الاستحقاقُ مشتقٌّ من الأداءِ والبنود — لا يُدخَل يدويًّا.</strong>
            كلُّ صفٍّ هنا مولَّدٌ من <strong>سجلِّ وحدةٍ مرجعيّ</strong>، ومكوّناتُه ستةٌ:
            <strong>ثلاثةُ أطرافٍ</strong> (عميل · مورد · مشغّل) لكلٍّ منها
            <strong>حكمٌ ومبلغ</strong>.
            <br>
            و<strong>صفرُ حقلِ إدخالٍ في هذا التبويب</strong>: التصفيةُ روابطُ لا نماذج —
            فحقلٌ واحدٌ قابلٌ للكتابة يفتح بابًا يمنعه نصُّ المتطلب.
        </p>
    </div></div>

    <?php if ($ENT_PERIODS): ?>
    <div class="card"><div class="card-body">
        <div class="ent-filters">
            <span class="ent-filter-label">الفترة:</span>
            <a class="badge <?php echo $ENT_PERIOD === '' ? 'badge-success' : 'badge-secondary'; ?> ent-chip"
               href="?">الكل</a>
            <?php foreach (array_keys($ENT_PERIODS) as $pv): ?>
                <a class="badge <?php echo $ENT_PERIOD === $pv ? 'badge-success' : 'badge-secondary'; ?> ent-chip"
                   href="?period=<?php echo rawurlencode($pv); ?>"><?php echo $e($pv); ?></a>
            <?php endforeach; ?>
        </div>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list"></i>
        الاستحقاقات — <?php echo count($ENT_ROWS); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap ent-table">
            <thead><tr>
                <th>الرمز</th><th>الفترة</th><th>سجلُّ الوحدة</th>
                <th>حكمُ العميل</th><th>مبلغُ العميل</th>
                <th>حكمُ المورد</th><th>مبلغُ المورد</th>
                <th>حكمُ المشغّل</th><th>مبلغُ المشغّل</th>
                <th>العملة</th><th>الحالة</th>
            </tr></thead>
            <tbody>
            <?php if (!$ENT_ROWS): ?>
                <tr><td colspan="11" class="ent-empty">لا استحقاقَ في هذا النطاق</td></tr>
            <?php endif; ?>
            <?php foreach ($ENT_ROWS as $r):
                $uid  = (int) $r['unit_record_id'];
                $unit = isset($ENT_UNITS[$uid]) ? $ENT_UNITS[$uid] : null;
                if ($unit === null) { $dangling++; }
            ?>
                <tr<?php echo $unit === null ? ' class="ent-row-dangling"' : ''; ?>>
                    <td><?php echo $e($r['entitle_code']); ?></td>
                    <td><?php echo $e($r['period']); ?></td>
                    <td><?php
                        if ($unit === null) {
                            echo '<span class="badge badge-warning" title="مرجعٌ معدوم: سجلُّ الوحدةِ غيرُ موجود">#'
                               . $uid . ' — مرجعٌ معدوم</span>';
                        } else {
                            echo '<span class="badge badge-secondary">' . $e($unit['record_no']) . '</span> '
                               . '<span class="ent-mstate">' . $e($unit['match_state']) . '</span>';
                        } ?></td>
                    <td class="ent-wrap"><?php echo $e($r['client_ruling']); ?></td>
                    <td><?php echo $r['client_amount'] === null ? '—' : $e($r['client_amount']); ?></td>
                    <td class="ent-wrap"><?php echo $e($r['supplier_ruling']); ?></td>
                    <td><?php echo $r['supplier_amount'] === null ? '—' : $e($r['supplier_amount']); ?></td>
                    <td class="ent-wrap"><?php echo $e($r['operator_ruling']); ?></td>
                    <td><?php echo $r['operator_amount'] === null ? '—' : $e($r['operator_amount']); ?></td>
                    <td><?php echo $e($r['currency']); ?></td>
                    <td><?php echo $e($r['state']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($dangling > 0): ?>
        <p class="ent-warn">
            ◆ <strong><?php echo $dangling; ?></strong> استحقاقًا مرجعُه <strong>معدوم</strong> —
            سجلُّ الوحدةِ الذي يشير إليه غيرُ موجود.
            <strong>وهو أخطرُ من «غيرِ معتمَد» لأنَّه يبدو موصولًا</strong>؛
            ووصلٌ داخليٌّ في العرضِ كان سيُسقطه فيُخفي العطب.
        </p>
    <?php endif; ?>
    </div></div>
</div>

<style>
.ent-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
.ent-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ent-filter-label{font-weight:700;font-size:13px}
.ent-chip{padding:6px 12px;text-decoration:none}
.ent-wrap{white-space:normal;max-width:280px}
.ent-empty{text-align:center;color:var(--c-6b7280, #6b7280)}
.ent-row-dangling{background:var(--c-fff7ed, #fff7ed)}
.ent-mstate{font-size:12px;color:var(--c-6b7280, #6b7280)}
.ent-warn{margin:12px 0 0;color:var(--c-92400e, #92400e);line-height:1.8}
</style>
<?php include '../infooter.php'; ?>
