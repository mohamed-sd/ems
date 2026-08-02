<?php
/**
 * Maintenance/failure_report.php — تقريرُ الأعطال (M-35 · الشاشة 197)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-04 §3: تقريرُ التكرار **من التصنيف الموحّد** (M-31) — أوامرُ الصيانة
 * والبلاغاتُ على محورٍ واحد (`main_category_code`) فلا يفسد التقريرَ
 * جدولان متوازيان. قراءةٌ خالصةٌ بلا أثر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super     = ($current_role === '-1');
$company_id   = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-01-01');
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
$f = $conn->real_escape_string($from);
$t = $conn->real_escape_string($to);
$co = $company_id;

// أوامرُ الصيانة على المحور الموحّد — عبر failure_codes مباشرة
$orders = array();
$r = $conn->query("SELECT COALESCE(NULLIF(fc.main_category_code,''),'بلا تصنيف') code,
                          COALESCE(NULLIF(fc.main_category_name,''),'بلا تصنيف — يُعلَن') name,
                          COUNT(*) n, ROUND(COALESCE(SUM(o.total_cost),0),2) cost,
                          ROUND(COALESCE(SUM(o.downtime_hours),0),1) downtime
                     FROM mnt_order o
                     LEFT JOIN failure_codes fc ON fc.id = o.failure_code_id
                    WHERE o.company_id={$co} AND COALESCE(o.is_deleted,0)=0
                      AND DATE(o.created_at) BETWEEN '{$f}' AND '{$t}'
                    GROUP BY code, name ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) { $orders[] = $x; }

// البلاغاتُ على المحور نفسِه — عبر وصلة M-31 (`failure_main_code`)
$tix = array();
$r = $conn->query("SELECT COALESCE(NULLIF(tc.failure_main_code,''),'بلا وصلة (موروث يُعلَن)') code,
                          COUNT(*) n
                     FROM tickets tk
                     LEFT JOIN ticket_categories tc ON tc.id = tk.category_id
                    WHERE tk.company_id={$co} AND tk.call_date BETWEEN '{$f}' AND '{$t}'
                    GROUP BY code ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) { $tix[$x['code']] = (int) $x['n']; }

$page_title = 'إيكوبيشن | تقرير الأعطال';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تقرير الأعطال — التصنيف الموحد'; $header_icon = 'fa fa-chart-column';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('تكرارُ الأعطال بالتصنيف الموحّد (M-31): أوامرُ الصيانة والبلاغاتُ على '
        . 'محور main_category واحدٍ — فلا يفسد التقريرَ جدولان متوازيان. '
        . 'وما لا وصلةَ له يُعلَن «موروثًا» ولا يُخفى.',
        array('حدّد الفترة', 'اقرأ الأكثرَ تكرارًا وكلفةً أولًا'));
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <label>من</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
            <label>إلى</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
            <button type="submit" class="btn-save"><i class="fa fa-filter"></i> اعرض</button>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-ol"></i>
        التكرارُ بالفئة الرئيسية — <?php echo count($orders); ?> فئة</h5></div>
    <div class="card-body">
        <?php if (!$orders): ems_state_empty('لا أوامرَ في الفترة', 'وسّع المدة', '?from=' . date('Y-01-01', strtotime('-1 year'))); else: ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>الفئة (الموحّدة)</th><th>أوامرُ الصيانة</th><th>بلاغاتُها</th>
                <th>التكلفة</th><th>ساعات التوقف</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars((string)$o['name']); ?></strong>
                        <small style="color:#888"><?php echo htmlspecialchars((string)$o['code']); ?></small></td>
                    <td><?php echo intval($o['n']); ?></td>
                    <td><?php echo intval($tix[(string)$o['code']] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string)$o['cost']); ?></td>
                    <td><?php echo htmlspecialchars((string)$o['downtime']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php $orphanTix = 0;
        foreach ($tix as $code => $n) { if (strpos((string)$code, 'موروث') !== false) { $orphanTix = $n; } }
        if ($orphanTix > 0): ?>
            <p style="color:#a15c00;margin-top:8px">⚠ <?php echo $orphanTix; ?> بلاغًا بتصنيفٍ
                **بلا وصلةٍ للموحّد** — موروثٌ يُعلَن ولا يُمحى (M-31)</p>
        <?php endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
