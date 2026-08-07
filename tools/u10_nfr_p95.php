<?php
/**
 * tools/u10_nfr_p95.php — قياس p95 لأثقل خمس شاشات (U10-F30)
 * ───────────────────────────────────────────────────────────────────────────
 * دخول حقيقي (جلسة PHP cURL — العربية لا تمر بargv) ثم N طلبًا لكل شاشة
 * وحساب p50/p95/max بالمللي ثانية. حساب القياس: user 72 (بيانات تجريبية —
 * كلمة مروره مضبوطة للقياس وهاشه القديم محفوظ للتراجع).
 * php tools/u10_nfr_p95.php [--n=20]
 */
if (PHP_SAPI !== 'cli') { die("CLI only\n"); }
$N = 20;
foreach ($argv as $a) { if (strpos($a, '--n=') === 0) { $N = max(5, (int) substr($a, 4)); } }

$BASE = 'http://localhost/ems';
$PASS = 'U10meas!2026';
/* أثقل الشاشات تقاس كلٌّ بحساب دورٍ يراها (المستخدمان 4 و72 — كلمتاهما مضبوطتان
   للقياس وهاشاهما القديمان محفوظان في docs/update0010/nfr_login_backup_u*.txt) */
$SCREENS = array(
    array('محمد', 'Contracts/contracts.php', 'عقود العملاء (41 عمودًا)'),
    array('مديرمالي', 'Finance/cfo_daily_board_fin.php', 'لوحة المدير المالي'),
    array('محمد', 'Equipments/equipments.php', 'سجل المعدات (46 عمودًا)'),
    array('مديرمالي', 'Finance/journal_form_fin.php', 'القيود اليومية (39 عمودًا)'),
    array('مديرمالي', 'Finance/payments_fin.php', 'طلبات الدفع والسداد'),
    array('محمد', 'main/role_board.php', 'لوحة الدور — احتياط'),
    array('محمد', 'Operations/operations_room.php', 'غرفة العمليات — احتياط'),
);

$jars = array();
$mkReq = function ($jar) {
    return function ($url, $post = null) use ($jar) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
        ));
        if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return array($body, $info);
    };
};
$login = function ($user) use (&$jars, $mkReq, $BASE, $PASS) {
    if (isset($jars[$user])) { return $mkReq($jars[$user]); }
    $jar = tempnam(sys_get_temp_dir(), 'nfrjar');
    $req = $mkReq($jar);
    list($body) = $req($BASE . '/login.php');
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', (string) $body, $m)) { return null; }
    list(, $info) = $req($BASE . '/login.php', array('username' => $user, 'password' => $PASS, 'csrf_token' => $m[1]));
    if ($info['http_code'] !== 302) { return null; }
    $jars[$user] = $jar;
    fwrite(STDOUT, "✔ دخول «{$user}» ناجح\n");
    return $req;
};

/* القياس — تُعتمد خمس شاشات 200 كاملة والباقي احتياط */
$results = array(); $accepted = 0;
foreach ($SCREENS as $S) {
    list($user, $path, $label) = $S;
    if ($accepted >= 5) { break; }
    $req = $login($user);
    if ($req === null) { fwrite(STDOUT, "  ⚠ تعذر دخول {$user} — تُقفز {$path}\n"); continue; }
    $times = array(); $codes = array();
    for ($i = 0; $i < $N; $i++) {
        list(, $info) = $req($BASE . '/' . $path);
        $times[] = $info['total_time'] * 1000.0;
        $codes[$info['http_code']] = ($codes[$info['http_code']] ?? 0) + 1;
    }
    sort($times);
    $ok200 = isset($codes[200]) && $codes[200] === $N;
    $p50 = $times[(int) floor(0.50 * (count($times) - 1))];
    $p95 = $times[(int) floor(0.95 * (count($times) - 1))];
    fwrite(STDOUT, sprintf("  %s %-38s p50=%6.0fms  p95=%6.0fms  max=%6.0fms  http=%s\n",
        $ok200 ? '✔' : '↷', $path, $p50, $p95, end($times), json_encode($codes)));
    if (!$ok200) { continue; }
    $results[] = array($path, $label, $codes, $p50, $p95, end($times));
    $accepted++;
}
foreach ($jars as $j) { @unlink($j); }

/* الدفتر */
$csv = dirname(__DIR__) . '/docs/update0010/NFR_P95_RESULTS.csv';
$fh = fopen($csv, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('screen', 'label', 'n', 'p50_ms', 'p95_ms', 'max_ms', 'http_codes', 'measured_at'));
foreach ($results as $r) { fputcsv($fh, array($r[0], $r[1], $N, round($r[3]), round($r[4]), round($r[5]), json_encode($r[2]), date('Y-m-d H:i'))); }
fclose($fh);
fwrite(STDOUT, "الدفتر: $csv\n");
