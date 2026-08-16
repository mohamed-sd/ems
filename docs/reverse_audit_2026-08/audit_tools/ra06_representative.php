<?php
/**
 * ra06_representative.php — تدقيقُ الشاشاتِ التمثيليةِ الثماني (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * يسجّل دخولَ المخوَّلِ ثم يجلب كلَّ شاشةٍ تمثيليةٍ ويحلّل بنيتَها الحيّة:
 * القشرةُ الموحّدة · التوبار · السايدبار · بطاقاتُ المؤشرات · الجداول ·
 * النماذج · أزرارُ الأفعال · مرساةُ RTL · بطاقةُ «عن الشاشة» · أثرُ أيِّ خطأ.
 * ◆ التجاوبُ يُقاس بوجودِ meta viewport وقواعدِ @media في القشرة (بلا بكسل).
 * المخرَج: evidence/representative_screens.json
 */
declare(strict_types=1);
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$BASE = 'http://localhost/ems';
$jar  = sys_get_temp_dir() . '/ra06_cookies';
@unlink($jar);

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 25]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $resp = curl_exec($ch); $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'headers' => substr((string) $resp, 0, $hlen), 'body' => substr((string) $resp, $hlen)];
}

/* دخولُ المخوَّل */
$g = http($BASE . '/login.php', $jar);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m);
http($BASE . '/login.php', $jar, ['csrf_token' => $m[1], 'username' => 'محمد', 'password' => '12345678']);

$screens = [
    'لوحة الرئيس التنفيذي' => 'Portal/ceo_board.php',
    'لوحة إدارة التشغيل'   => 'main/dashboard.php',
    'مساحة عملي'           => 'main/my_workspace.php',
    'العقود'               => 'Contracts/contracts.php',
    'التايم شيت'           => 'Timesheet/timesheet.php',
    'أمر الصيانة'          => 'Maintenance/maintenance_orders.php',
    'ملف المورد'           => 'Suppliers/suppliers.php',
    'صندوق الاعتماد'       => 'Approvals/approvals_inbox.php',
];

$out = [];
foreach ($screens as $name => $path) {
    $r = http($BASE . '/' . $path, $jar);
    $b = $r['body'];
    $analysis = [
        'path' => $path, 'code' => $r['code'], 'bytes' => strlen($b),
        'shell_topbar'   => (bool) preg_match('/ems-topbar|class="topbar/i', $b),
        'shell_sidebar'  => (bool) preg_match('/id="sidebar"|class="[^"]*sidebar/i', $b),
        'kpi_cards'      => preg_match_all('/kpi|stat-card|metric-card|بطاقة/i', $b),
        'tables'         => substr_count($b, '<table'),
        'datatable'      => (bool) preg_match('/DataTable|dataTable|ui-unification/i', $b),
        'forms'          => substr_count($b, '<form'),
        'action_buttons' => preg_match_all('/<button|class="[^"]*btn/i', $b),
        'rtl'            => (bool) preg_match('/dir="rtl"|direction:\s*rtl/i', $b),
        'viewport_meta'  => (bool) preg_match('/name="viewport"/i', $b),
        'media_queries'  => preg_match_all('/@media/i', $b),
        'screen_about'   => (bool) preg_match('/عن الشاشة|عن:\s|screen-about|ems-about/i', $b),
        'empty_state'    => (bool) preg_match('/لا توجد|لا يوجد|empty-state|فارغ/i', $b),
        'php_error'      => (bool) preg_match('/Fatal error|Warning:|Notice:|Uncaught|Stack trace/i', $b),
        'redirected'     => in_array($r['code'], [301,302,303], true) ? (preg_match('/^Location:\s*(.+)$/mi', $r['headers'], $mm) ? trim($mm[1]) : '?') : null,
    ];
    $out[$name] = $analysis;
}

file_put_contents($EV . '/representative_screens.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("%-22s %5s %6s %4s %4s %4s %4s %4s %4s %4s\n", 'الشاشة','رمز','بايت','توب','سايد','KPI','جدل','نمذج','RTL','خطأ');
foreach ($out as $name => $a) {
    printf("%-22s %5d %6d %4s %4s %4d %4d %4d %4s %4s\n",
        mb_substr($name, 0, 20), $a['code'], $a['bytes'],
        $a['shell_topbar']?'✔':'—', $a['shell_sidebar']?'✔':'—',
        $a['kpi_cards'], $a['tables'], $a['forms'], $a['rtl']?'✔':'—', $a['php_error']?'⚠':'—');
}
