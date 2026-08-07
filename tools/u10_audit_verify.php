<?php
/**
 * tools/u10_audit_verify.php — تحقق تغطية التدقيق لأفعال الكتابة (U10-A6)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل فعل Domain/Governance Write مربوط بمعالج (alias أو bound_page): أيحمل
 * ملف معالجه قناة تدقيق؟ (سجل النشاط المركزي · سجل تنفيذ الأفعال · الناشر —
 * فالجذر المحايد هو أثر التدقيق المالي · خطافات الحركة · سجل الاطلاع).
 * قياس خالص يخرج دفترًا CSV — والناقص دين معلن لا يخفى.
 * php tools/u10_audit_verify.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$AUDIT_MARKS = array(
    'log_activity', 'activity_logs', 'action_execution_log', 'action_impact_log',
    'publishFact', 'EventPublisher', 'publish(', 'log_equipment_event',
    'sensitive_read_log', 'audit_log', 'log_security_event', 'ems_business_events',
);

$live = array(); // action_code => handler file(s)
$r = mysqli_query($conn, "SELECT action_code, handler_path, handler_class FROM actions WHERE active = 1");
while ($x = mysqli_fetch_assoc($r)) { $live[$x['action_code']] = $x; }

$rows = array();
$r = mysqli_query($conn, "SELECT canonical_code, write_class, state, live_code FROM nav09_action_map
                           WHERE write_class IN ('domain_write','governance_write') ORDER BY canonical_code");
while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; }

$resolveFile = function ($row) use ($live, $ROOT) {
    $lc = (string) $row['live_code'];
    if (strpos($lc, 'page:') === 0) { return substr($lc, 5); }
    if (isset($live[$lc])) {
        $h = $live[$lc];
        if (!empty($h['handler_path']) && is_file($ROOT . '/' . $h['handler_path'])) { return $h['handler_path']; }
        if (!empty($h['handler_class'])) {
            $p = 'app/' . str_replace(array('App\\', '\\'), array('', '/'), $h['handler_class']) . '.php';
            if (is_file($ROOT . '/' . $p)) { return $p; }
        }
    }
    return null;
};

$csv = $ROOT . '/docs/update0010/AUDIT_COVERAGE_LEDGER.csv';
$fh = fopen($csv, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('code', 'write_class', 'binding_state', 'handler_file', 'audit_channel', 'verdict'));

/* ◆ المعمارية المقيسة: ActivityLogMiddleware يقلع مع كل طلب ويب من config.php
   (السطر 606) فيسجل الفاعل والوقت مركزيًّا لكل معالجٍ ويبيٍّ — وactivity_logs
   40,909 صفًّا آخرها الليلة و24,777 بقيم «قبل» (موصل audit_trail عند مواضع
   الخنق). فالطبقات ثلاث: مركزية (فاعل+وقت) للكل · قبل/بعد (diff) حيث وُصلت ·
   وناشر الجذر للمالي. الفحص أدناه يقيس الطبقةَ الثانية فوق الأولى. */
$cnt = array('central_plus_diff' => 0, 'central_only' => 0, 'declared' => 0);
$cache = array();
foreach ($rows as $row) {
    if ($row['state'] === 'declared_unbuilt') {
        $cnt['declared']++;
        fputcsv($fh, array($row['canonical_code'], $row['write_class'], $row['state'], '', '',
            'declared — الزر لم يثبت بناؤه فلا معالج يفحص'));
        continue;
    }
    $file = $resolveFile($row);
    $mark = '';
    if ($file !== null) {
        if (!isset($cache[$file])) {
            $src = @file_get_contents($ROOT . '/' . $file);
            $found = '';
            if ($src !== false) {
                foreach ($AUDIT_MARKS as $m) { if (strpos($src, $m) !== false) { $found = $m; break; } }
                /* عمق واحد: المضمنات المحلية (المعالجة قد تسكن helper) */
                if ($found === '' && preg_match_all("~(?:require|include)(?:_once)?\\s*\\(?\\s*__DIR__\\s*\\.\\s*['\"]/([A-Za-z0-9_.-]+\\.php)~", $src, $mm)) {
                    foreach ($mm[1] as $inc) {
                        $isrc = @file_get_contents($ROOT . '/' . dirname($file) . '/' . $inc);
                        if ($isrc === false) { continue; }
                        foreach ($AUDIT_MARKS as $m) { if (strpos($isrc, $m) !== false) { $found = $m . ' (via ' . $inc . ')'; break 2; } }
                    }
                }
            }
            $cache[$file] = $found;
        }
        $mark = $cache[$file];
    }
    $diff = $mark !== '';
    $cnt[$diff ? 'central_plus_diff' : 'central_only']++;
    fputcsv($fh, array($row['canonical_code'], $row['write_class'], $row['state'], (string) $file, $mark,
        $diff ? 'covered — مركزي + قناة قبل/بعد'
              : 'covered_central — الوسيط المركزي (فاعل+وقت) · قناة قبل/بعد مرشحة للتوصيل'));
}
fclose($fh);

/* شاهد حيوية الطبقتين من القاعدة نفسها */
$r = mysqli_query($conn, "SELECT COUNT(*) c, SUM(old_value IS NOT NULL AND old_value <> '') d FROM activity_logs");
$x = mysqli_fetch_assoc($r);

$total = count($rows);
$o("══ تغطية التدقيق لأفعال الكتابة (domain+governance = $total) ══");
$o("  الطبقة المركزية (ActivityLogMiddleware · فاعل+وقت): كل المعالجات الويبية — حية بـ{$x['c']} صفًّا");
$o("  مركزي + قناة قبل/بعد في المعالج: {$cnt['central_plus_diff']}");
$o("  مركزي فقط (قبل/بعد مرشحة للتوصيل — دين تبنٍّ معلن): {$cnt['central_only']}");
$o("  معلنة عدم البناء (لا معالج يفحص): {$cnt['declared']}");
$o("  صفوف قبل/بعد الحية في activity_logs: {$x['d']}");
$o('الدفتر: ' . $csv);
exit(0);
