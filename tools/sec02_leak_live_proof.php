<?php
/**
 * sec02_leak_live_proof.php — إثباتٌ حيٌّ لإغلاقِ B2 (اختبارٌ سلبيّ)
 * ═══════════════════════════════════════════════════════════════════════════
 * الفحصُ الساكنُ يقرأ نصَّ الملف؛ وهذا يفتح الشاشةَ **بحسابٍ حقيقيٍّ غيرِ مخوَّل**
 * ويقرأ ما يعود. فالحكمُ على السلوكِ لا على النص.
 *   المتوقَّع: تحويلٌ (302) أو 403 أو رسالةُ منعٍ — **لا قشرةَ ولا بيانات**.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/sec02_';

$SCREENS = array(
    'Employees/showcontractemployee.php',
    'Reports/contract_report.php',
    'Reports/contractall.php',
    'Reports/driverAndsupplerscontract.php',
    'Suppliers/showcontractsuppliers.php',
    'Suppliers/suppliers_details.php',
    'Timesheet/timesheet_type.php',
);
$ACC = array(
    'قارئٌ ماليّ (دور 22)' => array('u' => 'fin.reader@equipation.sd', 'p' => '12345678', 'expect' => 'deny'),
    'إدارةُ الموقع (دور 6)' => array('u' => 'موقع', 'p' => '12345678', 'expect' => 'any'),
);

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hl = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $h = substr((string) $r, 0, $hl); $b = substr((string) $r, $hl);
    return array('code' => $code, 'body' => $b,
                 'loc' => preg_match('/^Location:\s*(.+)$/mi', $h, $m) ? trim($m[1]) : null);
}

$fails = 0;
foreach ($ACC as $label => $a) {
    $jar = $JAR . md5($a['u']); @unlink($jar);
    echo "\n══ $label ══\n";
    $g = http($BASE . '/login.php', $jar);
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) { echo "  ✘ تعذّر الدخول\n"; $fails++; continue; }
    $lg = http($BASE . '/login.php', $jar, array('csrf_token' => $m[1], 'username' => $a['u'], 'password' => $a['p']));
    if (!in_array($lg['code'], array(301, 302, 303), true) || stripos((string) $lg['loc'], 'login.php') !== false) {
        echo "  ✘ فشل الدخول\n"; $fails++; continue;
    }

    foreach ($SCREENS as $s) {
        $r = http($BASE . '/' . $s, $jar);
        $redirected = in_array($r['code'], array(301, 302, 303), true);
        $denied = ($r['code'] === 403) || (stripos($r['body'], 'لا تملك صلاحية') !== false)
               || (stripos($r['body'], 'غير مصرح') !== false) || (stripos($r['body'], 'GOV-PERM-403') !== false);
        /* التسريبُ الحقيقيُّ: 200 بقشرةٍ أو بجدولِ بيانات */
        $leak = ($r['code'] === 200 && !$denied
                 && (stripos($r['body'], '<table') !== false || stripos($r['body'], 'insidebar') !== false
                     || strlen($r['body']) > 20000));
        /* «تسريب» حكمٌ لا وصف: صفحةٌ كاملةٌ لمن **يُتوقَّع منعُه**. أما المخوَّلُ
           فرؤيتُه الصفحةَ هي الصواب — ووسمُها تسريبًا يجعل المخرَجَ يكذب. */
        $verdict = $redirected ? 'تحويل ⇐ ' . basename((string) $r['loc'])
                 : ($denied ? 'منعٌ صريح'
                 : ($leak ? ($a['expect'] === 'deny' ? '✘ تسريب' : '✔ يراها (مخوَّل)')
                 : 'رمز ' . $r['code'] . ' · ' . strlen($r['body']) . ' بايت'));
        printf("  %-44s %s\n", $s, $verdict);
        if ($a['expect'] === 'deny' && $leak) { $fails++; }
    }
}

echo "\n" . ($fails === 0
    ? "✔ صفرُ تسريبٍ للقارئِ غيرِ المخوَّل — B2 مُغلقٌ سلوكيًّا\n"
    : "✘ ما زال هناك $fails تسريبًا\n");
exit($fails === 0 ? 0 : 1);
