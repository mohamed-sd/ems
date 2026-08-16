<?php
/**
 * ra05_http_sweep.php — المسحُ الحيُّ السلوكيُّ لكلِّ الشاشاتِ بدورين (GET فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① دخولٌ فعليٌّ عبر HTTP بدورِ مالكٍ (1) وبقارئٍ ماليٍّ محدود (22).
 * ② GET لكلِّ شاشةٍ مسجَّلةٍ في modules: يقاس رمزُ الحالة · عطبُ PHP الظاهر ·
 *    حضورُ القشرةِ (topbar) · جدولُ DataTables · إعادةُ التوجيه.
 * ③ ولغيرِ المخوَّل: الشاشاتُ التي لا يملك دورُه عرضَها يجب ألا تُصيَّر (403/302).
 * ④ اختباران سلبيان بلا أثر: POST بلا رمزِ CSRF بفعلٍ وهميٍّ (يُتوقَّع 403) —
 *    على شاشتَي عيّنة؛ وGET لشاشةٍ غيرِ مسجَّلةٍ (يُتوقَّع الحجب لا التصيير).
 * ◆ لا POST يحمل فعلًا حقيقيًّا — القراءةُ سيدةُ هذا المسح.
 * المخرَج: evidence/http_sweep.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$BASE = 'http://localhost/ems';
$PASS = '12345678';

function jar(string $name): string {
    $p = sys_get_temp_dir() . '/ra05_' . $name . '.cookie';
    @unlink($p);
    return $p;
}
function req(string $url, string $cookieJar, array $post = null, bool $withToken = false, string $token = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieJar, CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 25, CURLOPT_ENCODING => '',
    ]);
    if ($post !== null) {
        if ($withToken && $token !== '') { $post['csrf_token'] = $token; }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redir = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return [$code, $body, $redir];
}
function csrfFrom(string $html): string {
    if (preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)/', $html, $m)) { return $m[1]; }
    if (preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']csrf_token/', $html, $m)) { return $m[1]; }
    if (preg_match('/meta name=["\']csrf-token["\'] content=["\']([^"\']+)/', $html, $m)) { return $m[1]; }
    return '';
}
function login(string $user, string $pass): ?string {
    $j = jar(md5($user));
    [$c1, $b1] = req('http://localhost/ems/login.php', $j);
    $tok = csrfFrom($b1);
    [$c2, $b2, $r2] = req('http://localhost/ems/login.php', $j, ['username' => $user, 'password' => $pass], true, $tok);
    /* نجاحُ الدخول: إعادةُ توجيهٍ إلى داخل النظام */
    if ($c2 === 302 || strpos($b2, 'dashboard') !== false) { return $j; }
    /* محاولةٌ بلا رمزٍ (إن كان الدخولُ خارجَ الإنفاذ) */
    [$c3, , $r3] = req('http://localhost/ems/login.php', $j, ['username' => $user, 'password' => $pass]);
    if ($c3 === 302) { return $j; }
    fwrite(STDERR, "فشل دخول $user (c2=$c2 c3=$c3)\n");
    return null;
}

$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');

/* الشاشات المسجَّلة + مَن يملك عرضَها */
$screens = [];
$r = $db->query("SELECT m.id, m.code FROM modules m WHERE m.code LIKE '%.php'");
while ($x = $r->fetch_assoc()) { $screens[$x['code']] = ['id' => (int) $x['id'], 'roles' => []]; }
$r = $db->query('SELECT m.code, rp.role_id FROM role_permissions rp JOIN modules m ON m.id=rp.module_id WHERE rp.can_view=1');
while ($x = $r->fetch_assoc()) { if (isset($screens[$x['code']])) { $screens[$x['code']]['roles'][(int) $x['role_id']] = true; } }

/* الدخول بالدورين */
$admin = login('محمد', $PASS);          /* دور 1 — الأوسع */
$reader = login('fin.reader@equipation.sd', $PASS); /* دور 22 — قارئ مالي محدود */
if (!$admin) { fwrite(STDERR, "لا جلسةَ مالك — إيقاف\n"); exit(2); }

$out = ['measured_at' => date('c'), 'role1' => [], 'role22' => [], 'negative' => [], 'stats' => []];
$badMark = '/Fatal error|Parse error|Uncaught|Warning: mysqli|Deprecated:/u';

$n = 0; $tot = count($screens);
foreach ($screens as $code => $meta) {
    $n++;
    $url = $BASE . '/' . str_replace(' ', '%20', $code);
    [$c, $b, $rd] = req($url, $admin);
    $rec = ['http' => $c];
    if ($c === 200) {
        $rec['bytes'] = strlen($b);
        $rec['fatal'] = (bool) preg_match($badMark, $b);
        $rec['shell'] = (strpos($b, 'ems-topbar') !== false) || (strpos($b, 'insidebar') !== false) || (strpos($b, 'sidebar') !== false);
        $rec['table'] = (strpos($b, 'dataTable') !== false) || (strpos($b, 'table') !== false);
        if ($rec['fatal'] && preg_match($badMark . '', $b, $mm)) { $rec['fatal_sig'] = $mm[0]; }
    } elseif ($c === 302) { $rec['to'] = basename(parse_url($rd, PHP_URL_PATH) ?? ''); }
    $out['role1'][$code] = $rec;
    if ($n % 50 === 0) { fwrite(STDERR, "[$n/$tot]\n"); }
}

/* الدورُ 22: عيّنةُ الحجب — الشاشاتُ التي **لا** يملكها الدورُ (المتوقَّع: لا 200 بمحتوى) */
if ($reader) {
    $denied = 0; $leaked = []; $checked = 0;
    foreach ($screens as $code => $meta) {
        if (isset($meta['roles'][22])) { continue; }        /* يملكها — ليست عيّنةَ حجب */
        if ($checked >= 120) { break; }                      /* عيّنةٌ كافيةٌ معلَنة */
        $checked++;
        [$c, $b] = req($BASE . '/' . $code, $reader);
        $blocked = ($c !== 200) || (strpos($b, 'غير مصرح') !== false) || (strlen($b) < 600)
                   || (strpos($b, 'dashboard') !== false && strpos($b, 'location') !== false);
        if ($blocked) { $denied++; } else { $leaked[] = $code . ' (http=' . $c . ', bytes=' . strlen($b) . ')'; }
        $out['role22'][$code] = ['http' => $c, 'blocked' => $blocked];
    }
    $out['stats']['role22_checked'] = $checked;
    $out['stats']['role22_denied'] = $denied;
    $out['stats']['role22_leaks'] = $leaked;
}

/* الاختباران السلبيان (بلا أثرٍ يمكن أن يُكتب) */
[$c, $b] = req($BASE . '/Financing/fin_models.php', $admin, ['__ra_probe__' => '1', 'action' => '__ra_probe__']);
$out['negative']['csrf_missing_post_fin_models'] = ['http' => $c, 'expected' => 403,
    'verdict' => $c === 403 ? 'الإنفاذ يعمل (والنموذج الأصلي بلا رمزٍ سيُرفض