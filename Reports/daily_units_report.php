<?php
/**
 * Reports/daily_units_report.php — سجلُّ وتقريرُ الوحدات اليومية (M-29 · الشاشة 195)
 * ───────────────────────────────────────────────────────────────────────────
 * SPEC-03 بطاقة 6: «سجلٌّ تشغيليٌّ واحدٌ + تقريرٌ في مركز التقارير — تُستوعب
 * تقاريرُ الساعات القديمة» · «الكمياتُ بوحداتها المنفصلة» — قراءةٌ واشتقاق،
 * لا أثر. والمسمّى الجديدُ من مصفوفة أسماء UX-03 §7 («سجل الوحدات اليومية»
 * لا «عرض ساعات العمل»).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Operations/OperationsBoardService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Operations\OperationsBoardService as OBS;

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
$prj  = intval($_GET['project_id'] ?? 0);
$rep = OBS::dailyUnitsReport($conn, $company_id, $from, $to, $prj);

$page_title = 'إيكوبيشن | سجل الوحدات اليومية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ①②: أنماطُ سجلِّ الوحداتِ اليومية — rpt-du */
.rpt-du-filter{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.rpt-du-prj{width:100px}
.rpt-du-totals{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px}
.rpt-du-total-badge{font-size:15px;padding:8px 14px}
.rpt-du-unit-note{color:var(--c-a15c00, #a15c00);align-self:center}
.rpt-du-table{width:100%}
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'سجل الوحدات اليومية'; $header_icon = 'fa fa-table-list';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('السجلُّ التحليليُّ الواحد لوحدات العمل بوحداتها المنفصلة — يستوعب '
        . 'تقاريرَ الساعات القديمة الثلاثة. قراءةٌ واشتقاقٌ بلا أثر — ولا تُجمع وحدتان في رقم.',
        array('حدّد الفترةَ والمشروع', 'اقرأ المجاميعَ بوحدةٍ وحدة'));
    // UXW-01 ⑨: حالتا التحميلِ والخطأِ (حالةُ الفراغِ قائمةٌ أسفلُ) — مخفيتانِ افتراضًا
    echo ems_state('loading', 'جارٍ تحميلُ سجلِّ الوحداتِ اليومية', '', '', true);
    echo ems_state('error', 'تعذّر عرضُ سجلِّ الوحدات', 'أعد المحاولةَ — وإن استمر الخللُ أبلغ عن مشكلةٍ من هذه الشاشة', '', true);
    ?>

    <div class="card"><div class="card-body">
        <form method="get" class="rpt-du-filter">
            <label for="emsf_452_2098b">من</label><input type="date" name="from" id="emsf_452_2098b" value="<?php echo htmlspecialchars($from); ?>">
            <label for="emsf_453_8f10a">إلى</label><input type="date" name="to" id="emsf_453_8f10a" value="<?php echo htmlspecialchars($to); ?>">
            <label for="emsf_454_5931c">المشروع</label><input type="number" name="project_id" min="0" id="emsf_454_5931c" placeholder="الكل" class="rpt-du-prj"
                value="<?php echo $prj ?: ''; ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اعرض</button>
        </form>
        <div class="rpt-du-totals">
            <?php foreach ($rep['totals_by_unit'] as $ut => $q): ?>
                <div class="badge badge-secondary rpt-du-total-badge">
                    <?php echo htmlspecialchars($ut); ?>: <strong><?php echo htmlspecialchars((string)$q); ?></strong></div>
            <?php endforeach; ?>
            <?php if (count($rep['totals_by_unit']) > 1): ?>
                <small class="rpt-du-unit-note">⚠ المجاميعُ بوحدةٍ وحدة — ولا تُجمع وحدتان في رقم</small>
            <?php endif; ?>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <?php if (!$rep['rows']): ems_state_empty('لا وحداتٍ في هذه الفترة', 'وسّع المدة',
            '?from=' . date('Y-01-01') . '&to=' . $to); else: ?>
        <div class="table-container"><table class="alltables display nowrap rpt-du-table">
            <thead><tr><th>التاريخ</th><th>المشروع</th><th>المعدة</th><th>المورد</th>
                <th>المشغّل</th><th>الوحدة</th><th>الكمية</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($rep['rows'] as $x): ?>
                <tr><td><?php echo htmlspecialchars((string)$x['entry_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['project'] ?? ('#' . $x['project_id']))); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['equipment'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['supplier'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($x['operator'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)$x['unit_type']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$x['qty']); ?></strong></td>
                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars((string)$x['state']); ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
