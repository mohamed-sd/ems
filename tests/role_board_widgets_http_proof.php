<?php
/**
 * tests/role_board_widgets_http_proof.php
 * برهانُ HTTP لاستبدال الكتل المكررة بالقالب المشترك includes/role_board_widgets.php:
 * لكل دور (مديرمالي/صيانة) — الدخول، ثم تحويلُ main/dashboard.php إلى لوحته،
 * ثم ظهورُ المكوّنات السبعة (المفردات) + معرّفُ سكربت القالب rbPulseInit،
 * ثم غيابُ سكربت Chart المكرر (new Chart المباشر خارج القالب).
 * التشغيل: php tests/role_board_widgets_http_proof.php
 */
$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();

function h_get($url, $jar, $follow = false, &$hdrs = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_TIMEOUT => 40,
    ));
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $hdrs = substr($raw, 0, $hs);
    return array($code, substr($raw, $hs));
}

function h_post($url, $fields, $jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}

$pass = 0; $fail = 0;
function ok($label, $cond, $note = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $label\n"; }
    else       { $fail++; echo "  [FAIL] $label" . ($note !== '' ? " — $note" : '') . "\n"; }
}

// المفردات السبعُ للمكوّنات ①..⑦ كما يكتبها القالب المشترك
$WORDS = array('مهامي', 'موافقاتي', 'التنبيهات', 'إنشاء سريع', 'نبض الأداء', 'عملي الأخير');

$CASES = array(
    array('user' => 'مديرمالي', 'board' => 'Finance/cfo_daily_board_fin.php', 'series' => array('تحصيل', 'صرف')),
    array('user' => 'صيانة',    'board' => 'Maintenance/dashboard_mnt.php',   'series' => array('أُنشئت', 'أُغلقت')),
);

foreach ($CASES as $i => $c) {
    echo "\n== [{$c['user']}] {$c['board']} ==\n";
    $jar = $TMP . "/rbw_proof_$i.txt";
    if (file_exists($jar)) { unlink($jar); }

    // ① الدخول (رمز CSRF من صفحة الدخول نفسها)
    list($lc, $lp) = h_get("$BASE/login.php", $jar);
    $tok = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]*)"/', $lp, $m)) { $tok = $m[1]; }
    list($pc, $ph, $pb) = h_post("$BASE/login.php", array(
        'username' => $c['user'], 'password' => '12345678', 'csrf_token' => $tok,
    ), $jar);
    ok('الدخول ينجح (تحويل بعد POST)', $pc === 302 || $pc === 303, "HTTP $pc");

    // ② التحويل من الرئيسية إلى لوحة الدور
    list($dc, $db) = h_get("$BASE/main/dashboard.php", $jar, false, $dh);
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $dh, $m)) { $loc = trim($m[1]); }
    ok('main/dashboard.php يحوّل إلى ' . basename($c['board']),
        $dc === 302 && strpos($loc, basename($c['board'])) !== false, "HTTP $dc · Location: $loc");

    // ③ اللوحة تُفتح وتحمل المكوّنات السبعة
    list($bc, $bb) = h_get("$BASE/{$c['board']}", $jar, true);
    ok('اللوحة تُفتح 200', $bc === 200, "HTTP $bc");
    foreach ($WORDS as $w) {
        ok("المكوّن «{$w}» ظاهر", strpos($bb, $w) !== false);
    }
    ok('canvas#rbPulse موجود', strpos($bb, 'id="rbPulse"') !== false);

    // ④ سكربتُ القالب حصرًا (rbPulseInit مع polling على Chart) ولا سكربت Chart مكرر
    ok('سكربت القالب rbPulseInit موجود', strpos($bb, 'id="rbPulseInit"') !== false);
    ok('السكربت ينتظر Chart (polling)', strpos($bb, "typeof Chart === 'undefined'") !== false);
    ok('لا سكربت Chart مكرر (new Chart مرة واحدة)', substr_count($bb, 'new Chart(') === 1,
        'العدد=' . substr_count($bb, 'new Chart('));

    // ⑤ سلسلتا النبض الصحيحتان لهذا الدور — كما يكتبهما القالب: json_encode داخل datasets
    foreach ($c['series'] as $s) {
        ok("سلسلة النبض «{$s}» ممرَّرة إلى القالب",
            strpos($bb, 'label: ' . json_encode($s)) !== false, 'المتوقَّع: label: ' . json_encode($s));
    }
    if (file_exists($jar)) { unlink($jar); }
}

echo "\n=====================================\n";
echo "النتيجة: $pass ناجح · $fail فاشل\n";
exit($fail === 0 ? 0 : 1);
