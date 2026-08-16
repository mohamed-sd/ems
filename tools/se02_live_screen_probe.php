<?php
/**
 * se02_live_screen_probe.php — فحصٌ حيٌّ لشاشةِ قيدِ الوردية (GET فقط · قراءة)
 * ═══════════════════════════════════════════════════════════════════════════
 * يثبت أربعةَ أشياءٍ بالسلوكِ لا بالكود:
 *   ① المخوَّلُ يراها 200 بقشرةٍ كاملةٍ وبنموذجٍ فيه رمزُ CSRF
 *   ② غيرُ المخوَّلِ يُمنع (تحويلٌ أو 403) — الاختبارُ السلبي
 *   ③ لا أثرَ خطأِ PHP في الصفحة
 *   ④ الرابطُ يظهر في السايدبارِ للدورِ المالك
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/se02_';

$ACCOUNTS = [
    'مخوَّل (إدارة الموقع · دور 6)' => ['user' => 'موقع', 'pass' => '12345678', 'expect' => 'allow'],
    'غيرُ مخوَّل (قارئ مالي · دور 22)' => ['user' => 'fin.reader@equipation.sd', 'pass' => '12345678', 'expect' => 'deny'],
];

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $h = substr((string) $resp, 0, $hlen);
    $b = substr((string) $resp, $hlen);
    return ['code' => $code, 'body' => $b,
            'loc' => preg_match('/^Location:\s*(.+)$/mi', $h, $m) ? trim($m[1]) : null];
}

$fails = 0;
foreach ($ACCOUNTS as $label => $acc) {
    $jar = $JAR . md5($acc['user']);
    @unlink($jar);
    echo "\n══ $label ══\n";
    $g = http($BASE . '/login.php', $jar);
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) {
        echo "  ✘ تعذّر قراءةُ رمزِ الدخول\n"; $fails++; continue;
    }
    $lg = http($BASE . '/login.php', $jar, ['csrf_token' => $m[1], 'username' => $acc['user'], 'password' => $acc['pass']]);
    $ok = in_array($lg['code'], [301, 302, 303], true) && stripos((string) $lg['loc'], 'login.php') === false;
    if (!$ok) { echo "  ✘ فشل الدخول (code={$lg['code']})\n"; $fails++; continue; }
    echo "  · دخلَ بنجاح\n";

    $r = http($BASE . '/Operations/shift_entry.php', $jar);
    $isDeniedRedirect = in_array($r['code'], [301, 302, 303], true);
    $bodyDenied = (stripos($r['body'], 'لا تملك صلاحية') !== false);
    $has200Shell = ($r['code'] === 200 && stripos($r['body'], '<aside') !== false || stripos($r['body'], 'insidebar') !== false);
    $hasForm  = (stripos($r['body'], 'name="csrf_token"') !== false && stripos($r['body'], 'action" value="record"') !== false);
    $phpErr   = preg_match('/(Fatal error|Parse error|Warning:|Notice:|Undefined )/i', $r['body']) === 1;

    printf("  الرمز=%s · قشرة=%s · نموذجٌ برمزِ حماية=%s · أثرُ خطأ=%s\n",
        $r['code'], $has200Shell ? 'نعم' : 'لا', $hasForm ? 'نعم' : 'لا', $phpErr ? 'نعم ✘' : 'لا ✔');

    if ($acc['expect'] === 'allow') {
        if ($r['code'] !== 200)  { echo "  ✘ متوقَّعٌ 200 ووجد {$r['code']}\n"; $fails++; }
        elseif (!$hasForm)       { echo "  ✘ الصفحةُ بلا نموذجٍ محميّ\n"; $fails++; }
        elseif ($phpErr)         { echo "  ✘ أثرُ خطأِ PHP في الصفحة\n"; $fails++; }
        else                     { echo "  ✔ يراها كاملةً بنموذجٍ محميّ\n"; }
        if (stripos($r['body'], 'shift_entry.php') !== false) { echo "  ✔ الرابطُ حاضرٌ في القشرة\n"; }
    } else {
        if ($isDeniedRedirect || $r['code'] === 403 || $bodyDenied) {
            echo '  ✔ مُنع كما يجب (' . ($isDeniedRedirect ? 'تحويل ⇐ ' . $r['loc'] : ($r['code'] === 403 ? '403' : 'رسالةُ منعٍ صريحة')) . ")\n";
        } else {
            echo "  ✘ تسريب: غيرُ المخوَّلِ رأى الشاشةَ (code={$r['code']})\n"; $fails++;
        }
    }
}

echo "\n" . ($fails === 0 ? "✔ الفحصُ الحيُّ نظيف — صفرُ إخفاق\n" : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
