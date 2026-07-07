<?php
/**
 * لوحة قياس مخالفات CSRF — ADR-05 · المرحلة 0 (مخرَج «لوحة قياس المخالفات»)
 * ───────────────────────────────────────────────────────────────────────────
 * تقرأ logs/security.log وتعرض: المؤشرات، المخالفات حسب الصفحة مع «جاهزية
 * الإنفاذ» لكل مسار، الاتجاه اليومي، وآخر السجلات — مع فصل ضجيج أدوات
 * الاختبار (curl/harness) عن مخالفات المتصفحات الحقيقية التي هي وحدها
 * أساس قرار الحجب. للمدير الأعلى فقط.
 *
 * دورة القرار (docs/CSRF_ROLLOUT_GUIDE_ar.md):
 *   مسار «جاهز» (٧+ أيام بلا مخالفة متصفح) ← يُضاف إلى CSRF_ENFORCE_PATHS
 *   في .env ← اكتمال الكل ← EMS_CSRF_ENFORCE=true.
 */
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'مراقبة CSRF';
$current_page = 'csrf-monitor';

// ── قراءة السجل (بسقف 4MB من نهايته حمايةً من التضخم) ─────────────────────
$log_file = dirname(__DIR__) . '/logs/security.log';
$MAX_READ = 4 * 1024 * 1024;

$raw = '';
$log_exists = is_readable($log_file);
if ($log_exists) {
    $size = filesize($log_file);
    $fh = fopen($log_file, 'rb');
    if ($fh) {
        if ($size > $MAX_READ) {
            fseek($fh, -$MAX_READ, SEEK_END);
            fgets($fh); // تجاهل سطرٍ مبتور
        }
        $raw = stream_get_contents($fh);
        fclose($fh);
    }
}

// ── تحليل الأسطر ────────────────────────────────────────────────────────────
$window_days = intval($_GET['days'] ?? 30);
if (!in_array($window_days, array(7, 30, 90, 0), true)) {
    $window_days = 30;
}
$cutoff = $window_days > 0 ? strtotime("-{$window_days} days") : 0;

$events = array();
$pattern = '/^\[([\d\- :]+)\]\s+\[csrf_violation\].*?IP:\s*(\S+).*?User:\s*(.*?)\s*\|.*?'
    . 'Details:\s*method=(\S+)\s+script=(\S+)\s+token=(\S+)\s*\|\s*UA:\s*(.*)$/u';

foreach (explode("\n", $raw) as $line) {
    if (strpos($line, 'csrf_violation') === false) {
        continue;
    }
    if (!preg_match($pattern, trim($line), $m)) {
        continue;
    }
    $ts = strtotime($m[1]);
    if ($ts === false || ($cutoff && $ts < $cutoff)) {
        continue;
    }
    $ua = trim($m[7]);
    // تصنيف العميل: متصفح حقيقي أم أداة اختبار/سكربت (ضجيج تطويري).
    $is_browser = (stripos($ua, 'Mozilla') !== false);
    $events[] = array(
        'ts' => $ts, 'date' => substr($m[1], 0, 10), 'ip' => $m[2], 'user' => trim($m[3]),
        'method' => $m[4], 'script' => $m[5], 'token' => $m[6],
        'browser' => $is_browser,
    );
}

// ── تجميع ───────────────────────────────────────────────────────────────────
$total_all = count($events);
$total_browser = 0;
$total_harness = 0;
$by_script = array();   // script => [count, invalid, missing, last_ts]  (متصفح فقط)
$by_day = array();      // date => [browser, harness]
$last_browser_ts = 0;

foreach ($events as $ev) {
    if (!isset($by_day[$ev['date']])) {
        $by_day[$ev['date']] = array(0, 0);
    }
    if ($ev['browser']) {
        $total_browser++;
        $by_day[$ev['date']][0]++;
        $s = $ev['script'];
        if (!isset($by_script[$s])) {
            $by_script[$s] = array('count' => 0, 'invalid' => 0, 'missing' => 0, 'last' => 0);
        }
        $by_script[$s]['count']++;
        if ($ev['token'] === 'invalid') { $by_script[$s]['invalid']++; } else { $by_script[$s]['missing']++; }
        if ($ev['ts'] > $by_script[$s]['last']) { $by_script[$s]['last'] = $ev['ts']; }
        if ($ev['ts'] > $last_browser_ts) { $last_browser_ts = $ev['ts']; }
    } else {
        $total_harness++;
        $by_day[$ev['date']][1]++;
    }
}
uasort($by_script, function ($a, $b) { return $b['count'] - $a['count']; });
ksort($by_day);

$days_clean = $last_browser_ts > 0 ? floor((time() - $last_browser_ts) / 86400) : null;
$READY_DAYS = 7;

// ── حالة الإنفاذ الحالية ────────────────────────────────────────────────────
$global_enforce = defined('EMS_CSRF_ENFORCE') && EMS_CSRF_ENFORCE === true;
$enforced_paths = function_exists('ems_csrf_enforced_paths') ? ems_csrf_enforced_paths() : array();

function csrf_mon_path_enforced_view($script, $paths)
{
    foreach ($paths as $p) {
        if (stripos($script, $p) !== false) {
            return true;
        }
    }
    return false;
}

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>مراقبة حماية CSRF</h2>
        <p class="sub">لوحة قياس المخالفات — أساس قرار الحجب المتدرج (ADR-05 · المرحلة 0)</p>
    </div>
    <div class="phead-right">
        <form method="get" style="display:inline">
            <select name="days" class="form-ctrl-sm" onchange="this.form.submit()">
                <option value="7"  <?php echo $window_days === 7  ? 'selected' : ''; ?>>آخر 7 أيام</option>
                <option value="30" <?php echo $window_days === 30 ? 'selected' : ''; ?>>آخر 30 يومًا</option>
                <option value="90" <?php echo $window_days === 90 ? 'selected' : ''; ?>>آخر 90 يومًا</option>
                <option value="0"  <?php echo $window_days === 0  ? 'selected' : ''; ?>>كل السجل</option>
            </select>
        </form>
    </div>
</div>

<?php if (!$log_exists): ?>
<div class="card"><div class="empty-state"><i class="fas fa-file-circle-question"></i><p>ملف security.log غير موجود بعد.</p></div></div>
<?php else: ?>

<!-- ── وضع الإنفاذ الحالي ── -->
<div class="card" style="margin-bottom:18px;padding:14px 18px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
    <div>
        <strong>الوضع العام:</strong>
        <?php if ($global_enforce): ?>
            <span style="color:#16a34a;font-weight:700"><i class="fas fa-shield-halved"></i> حجب شامل (EMS_CSRF_ENFORCE=true)</span>
        <?php else: ?>
            <span style="color:#b45309;font-weight:700"><i class="fas fa-eye"></i> مراقبة وتسجيل</span>
        <?php endif; ?>
    </div>
    <div>
        <strong>مسارات الحجب المتدرج (.env):</strong>
        <?php if (empty($enforced_paths)): ?>
            <span class="text-muted">لا شيء بعد — يُبدأ بالمسارات «الجاهزة» أدناه</span>
        <?php else: ?>
            <?php foreach ($enforced_paths as $p): ?>
                <code style="background:#eef2ff;padding:2px 8px;border-radius:6px;margin-inline-start:4px;"><?php echo e($p); ?></code>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── المؤشرات ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px;">
    <div class="card" style="padding:16px;text-align:center;">
        <div style="font-size:1.9rem;font-weight:800;color:#dc2626;"><?php echo number_format($total_browser); ?></div>
        <div class="text-muted">مخالفات متصفح حقيقية<br><small>(أساس القرار)</small></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;">
        <div style="font-size:1.9rem;font-weight:800;color:#64748b;"><?php echo number_format($total_harness); ?></div>
        <div class="text-muted">ضجيج أدوات اختبار<br><small>(curl/سكربتات — يُهمل)</small></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;">
        <div style="font-size:1.9rem;font-weight:800;color:<?php echo ($days_clean === null || $days_clean >= $READY_DAYS) ? '#16a34a' : '#b45309'; ?>;">
            <?php echo $days_clean === null ? '∞' : number_format($days_clean); ?>
        </div>
        <div class="text-muted">أيام بلا مخالفة متصفح<br><small>(العتبة: <?php echo $READY_DAYS; ?> أيام)</small></div>
    </div>
    <div class="card" style="padding:16px;text-align:center;">
        <div style="font-size:1.9rem;font-weight:800;color:#0B1E3F;"><?php echo number_format(count($by_script)); ?></div>
        <div class="text-muted">صفحة عليها مخالفات<br><small>(ضمن النافذة المحددة)</small></div>
    </div>
</div>

<!-- ── الاتجاه اليومي ── -->
<?php if (!empty($by_day)): ?>
<div class="card" style="margin-bottom:18px;padding:18px;">
    <h3 style="margin:0 0 12px;">الاتجاه اليومي <small class="text-muted">(أزرق غامق = متصفح حقيقي · رمادي = أدوات اختبار)</small></h3>
    <?php $day_max = 1; foreach ($by_day as $d) { $day_max = max($day_max, $d[0] + $d[1]); } ?>
    <div style="display:flex;align-items:flex-end;gap:3px;height:120px;overflow-x:auto;padding-bottom:4px;">
        <?php foreach ($by_day as $date => $d):
            $h1 = (int) round($d[0] / $day_max * 100);
            $h2 = (int) round($d[1] / $day_max * 100); ?>
        <div title="<?php echo e($date . ' — متصفح: ' . $d[0] . ' · أدوات: ' . $d[1]); ?>"
             style="display:flex;flex-direction:column-reverse;width:22px;min-width:22px;height:100%;">
            <div style="font-size:.55rem;text-align:center;color:#64748b;direction:ltr;"><?php echo e(substr($date, 5)); ?></div>
            <div style="background:#0B1E3F;height:<?php echo $h1; ?>%;border-radius:2px 2px 0 0;"></div>
            <div style="background:#cbd5e1;height:<?php echo $h2; ?>%;"></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── حسب الصفحة + جاهزية الإنفاذ ── -->
<div class="card" style="margin-bottom:18px;">
    <div style="padding:14px 18px 0;"><h3 style="margin:0;">المخالفات الحقيقية حسب الصفحة — وجاهزية الحجب</h3></div>
    <?php if (empty($by_script)): ?>
    <div class="empty-state"><i class="fas fa-shield-check"></i>
        <p>لا مخالفات متصفح حقيقية في هذه النافذة — <strong>كل المسارات مرشّحة للحجب المتدرج.</strong></p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table" style="width:100%;">
        <thead><tr>
            <th>الصفحة</th><th>المخالفات</th><th>رمز قديم (invalid)</th><th>بلا رمز (missing)</th>
            <th>آخر مخالفة</th><th>الحالة</th>
        </tr></thead>
        <tbody>
        <?php foreach ($by_script as $script => $st):
            $age_days = floor((time() - $st['last']) / 86400);
            $enforced = csrf_mon_path_enforced_view($script, $enforced_paths); ?>
        <tr>
            <td style="direction:ltr;text-align:right;"><code><?php echo e($script); ?></code></td>
            <td><?php echo number_format($st['count']); ?></td>
            <td><?php echo number_format($st['invalid']); ?></td>
            <td><?php echo number_format($st['missing']); ?></td>
            <td><?php echo e(date('Y-m-d', $st['last'])); ?> <small class="text-muted">(قبل <?php echo $age_days; ?> يوم)</small></td>
            <td>
                <?php if ($global_enforce || $enforced): ?>
                    <span style="color:#16a34a;font-weight:700;"><i class="fas fa-shield-halved"></i> مُنفَذ</span>
                <?php elseif ($age_days >= $READY_DAYS): ?>
                    <span style="color:#16a34a;font-weight:700;"><i class="fas fa-circle-check"></i> جاهز للحجب</span>
                <?php else: ?>
                    <span style="color:#b45309;font-weight:700;"><i class="fas fa-hourglass-half"></i> تحت المراقبة</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── آخر السجلات ── -->
<div class="card">
    <div style="padding:14px 18px 0;"><h3 style="margin:0;">آخر 30 مخالفة (كل الأنواع)</h3></div>
    <?php $recent = array_slice(array_reverse($events), 0, 30); ?>
    <?php if (empty($recent)): ?>
    <div class="empty-state"><i class="fas fa-shield-check"></i><p>لا مخالفات في هذه النافذة إطلاقًا.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table" style="width:100%;">
        <thead><tr><th>الوقت</th><th>المستخدم</th><th>الصفحة</th><th>النوع</th><th>العميل</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $ev): ?>
        <tr>
            <td style="white-space:nowrap;"><?php echo e(date('m-d H:i', $ev['ts'])); ?></td>
            <td><?php echo e($ev['user']); ?></td>
            <td style="direction:ltr;text-align:right;"><code><?php echo e($ev['script']); ?></code></td>
            <td><?php echo $ev['token'] === 'invalid'
                ? '<span style="color:#b45309;">رمز قديم</span>'
                : '<span style="color:#dc2626;">بلا رمز</span>'; ?></td>
            <td><?php echo $ev['browser']
                ? '<strong>متصفح</strong>'
                : '<span class="text-muted">أداة اختبار</span>'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
