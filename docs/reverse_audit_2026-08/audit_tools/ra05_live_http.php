<?php
/**
 * ra05_live_http.php — مسحٌ سلوكيٌّ حيٌّ عبر HTTP بدورين (قراءةٌ فقط · GET حصرًا)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يسجّل دخولَ حسابين حقيقيين في co4 عبر login.php (يقرأ رمزَ CSRF من الصفحة
 *   ثم يرسله) — مخوَّلٌ (الدور 1 «محمد») وقارئٌ ضيّق (الدور 22 fin.reader).
 * ◆ ثم GET لكلِّ شاشةٍ مسجَّلةٍ في modules ولكلِّ رابطِ تنقّلٍ حي — ويسجّل:
 *   رمزَ الحالة · هل حُوِّل إلى دخول/لوحة · وجودَ علاماتِ عطلٍ (Fatal/Warning/
 *   Notice/stack) · وجودَ القشرةِ الموحّدة · عددَ الجداولِ والنماذج.
 * ◆ لا يرسل أيَّ POST إلى مساراتِ المنتج — فالقياسُ لا يغيّر بيانات.
 * المخرَج: evidence/live_http_admin.jsonl · live_http_reader.jsonl · live_http.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$BASE = 'http://localhost/ems';
$COOKJAR = sys_get_temp_dir() . '/ra05_cookies_';

$accounts = [
    'admin'  => ['user' => 'محمد',                    'pass' => '12345678', 'role' => 1],
    'reader' => ['user' => 'fin.reader@equipation.sd', 'pass' => '12345678', 'role' => 22],
];

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr((string) $resp, 0, $hlen);
    $body = substr((string) $resp, $hlen);
    $loc = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    return ['code' => $code, 'body' => $body, 'location' => $loc];
}

function login(string $base, string $jar, array $acc): array {
    @unlink($jar);
    $g = http($base . '/login.php', $jar);
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) {
        return ['ok' => false, 'why' => 'no_csrf'];
    }
    $p = http($base . '/login.php', $jar, [
        'csrf_token' => $m[1], 'username' => $acc['user'], 'password' => $acc['pass'],
    ]);
    /* نجاحُ الدخولِ = تحويلٌ بعيدًا عن login.php (إلى لوحةٍ أو صفحةِ بدء) */
    $ok = in_array($p['code'], [301, 302, 303], true) && stripos((string) $p['location'], 'login.php') === false;
    return ['ok' => $ok, 'code' => $p['code'], 'location' => $p['location']];
}

/* أهدافُ المسح: مسارات modules + روابط nav الحية (بلا معالجات/كرون) */
$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$targets = [];
$r = $db->query("SELECT DISTINCT code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_row()) {
    $c = str_replace('\\', '/', $x[0]);
    if (preg_match('~(get_|ajax_)|_handler\.php$|_ajax\.php$|cron_|/logout~', $c)) { continue; }
    if (is_file($ROOT . '/' . $c)) { $targets[$c] = 'module'; }
}
$r = $db->query("SELECT DISTINCT route FROM nav_items WHERE active=1 AND route LIKE '%.php%'");
while ($x = $r->fetch_row()) {
    $c = preg_replace('/[?#].*$/', '', str_replace(['../', './'], '', $x[0]));
    if ($c === '' || strpos($c, 'http') === 0) { continue; }
    if (is_file($ROOT . '/' . $c) && !isset($targets[$c])) { $targets[$c] = 'nav'; }
}
ksort($targets);

$summary = ['base' => $BASE, 'targets' => count($targets), 'logins' => [], 'roles' => []];

foreach ($accounts as $tag => $acc) {
    $jar = $COOKJAR . $tag;
    $lg = login($BASE, $jar, $acc);
    $summary['logins'][$tag] = $lg;
    if (empty($lg['ok'])) { fwrite(STDERR, "دخول $tag فشل: " . json_encode($lg, JSON_UNESCAPED_UNICODE) . "\n"); continue; }

    $jl = fopen($EV . '/live_http_' . $tag . '.jsonl', 'w');
    $agg = ['ok200' => 0, 'redirect_login' => 0, 'redirect_dash' => 0, 'forbidden' => 0,
            'server_error' => 0, 'php_error_marker' => 0, 'has_shell' => 0, 'other' => 0, 'total' => 0];
    $n = 0; $tot = count($targets);
    foreach ($targets as $path => $src) {
        $n++;
        $res = http($BASE . '/' . $path, $jar);
        $body = $res['body'];
        $err  = (bool) preg_match('/Fatal error|Parse error|Warning:|Notice:|Uncaught|mysqli_sql_exception|Stack trace/i', $body);
        $shell = (bool) preg_match('/ems-topbar|insidebar|id="sidebar"|class="ems-/i', $body);
        $rec = [
            'path' => $path, 'src' => $src, 'code' => $res['code'],
            'redirect' => $res['location'],
            'php_error' => $err, 'shell' => $shell,
            'tables' => substr_count($body, '<table'), 'forms' => substr_count($body, '<form'),
            'bytes' => strlen($body),
        ];
        fwrite($jl, json_encode($rec, JSON_UNESCAPED_UNICODE) . "\n");
        $agg['total']++;
        if ($err) { $agg['php_error_marker']++; }
        if (in_array($res['code'], [301, 302, 303], true)) {
            if (stripos((string) $res['location'], 'login') !== false) { $agg['redirect_login']++; }
            else { $agg['redirect_dash']++; }
        } elseif ($res['code'] === 403) { $agg['forbidden']++; }
        elseif ($res['code'] >= 500) { $agg['server_error']++; }
        elseif ($res['code'] === 200) { $agg['ok200']++; if ($shell) { $agg['has_shell']++; } }
        else { $agg['other']++; }
        if ($n % 50 === 0) { fwrite(STDERR, "[$tag $n/$tot]\n"); }
    }
    fclose($jl);
    $summary['roles'][$tag] = $agg;
    fwrite(STDERR, "$tag انتهى: " . json_encode($agg, JSON_UNESCAPED_UNICODE) . "\n");
}

file_put_contents($EV . '/live_http.json', json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "الأهداف: " . count($targets) . "\n";
foreach ($summary['roles'] as $tag => $a) {
    printf("%-7s: 200=%d (بقشرة %d) · ⮡دخول=%d · ⮡لوحة=%d · 403=%d · 5xx=%d · أثرُ خطأ=%d\n",
        $tag, $a['ok200'], $a['has_shell'], $a['redirect_login'], $a['redirect_dash'],
        $a['forbidden'], $a['server_error'], $a['php_error_marker']);
}
