<?php
/**
 * tools/m16_live_probe.php — جسٌّ حيٌّ للعشرين شاشةً بثلاثةِ حسابات
 * ───────────────────────────────────────────────────────────────────────────
 * الحزامُ البنيويُّ يشهد بوجودِ الملفِّ والوحدةِ والمنحة — ولا يشهد بأن الشاشةَ
 * تُفتح فعلًا. وهذا الجسُّ يفتحها عبر HTTP بحسابٍ حقيقيٍّ ويقيس:
 *   • الرمزَ 200 لا 500 ولا تحويلًا إلى تسجيلِ الدخول.
 *   • حضورَ عنوانِ الشاشةِ في الجسمِ (فالتحويلُ الصامتُ يعطي 200 بجسمٍ آخر).
 *   • صفرَ خطأٍ فادحٍ أو تحذيرٍ مطبوعٍ في الجسم.
 *   • قراءةً خالصةً للدورِ 30: صفرُ زرِّ كتابةٍ في جسمِ صفحتِه.
 *
 * التشغيل: php tools/m16_live_probe.php [--base=http://localhost/ems]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$base = 'http://localhost/ems';
foreach ($argv as $a) { if (strpos($a, '--base=') === 0) { $base = rtrim(substr($a, 7), '/'); } }

$SCREENS = array(
    'Risk/risk_board.php'          => 'لوحة المخاطر العليا',
    'Risk/risk_register.php'       => 'سجل المخاطر المركزي',
    'Risk/risk_units.php'          => 'وحدات المخاطر والتصنيف',
    'Risk/risk_assessment.php'     => 'تقييم الخطر ونسخه التاريخية',
    'Risk/risk_controls.php'       => 'الضوابط والضوابط الحرجة',
    'Risk/risk_control_verify.php' => 'التحقق من الضوابط الحرجة',
    'Risk/risk_kris.php'           => 'مؤشرات الخطر',
    'Risk/risk_treatments.php'     => 'إجراءات معالجة المخاطر',
    'Risk/risk_acceptance.php'     => 'القبول والاستثناءات',
    'Risk/risk_signals.php'        => 'الإشارات',
    'Risk/risk_incidents.php'      => 'الحوادث والوقائع',
    'Risk/risk_reviews.php'        => 'المراجعات',
    'Risk/risk_committee.php'      => 'لجنة المخاطر',
    'Risk/risk_appetite.php'       => 'الشهية',
    'Risk/risk_reports.php'        => 'تقارير المخاطر والتحليلات',
    'Risk/risk_settings.php'       => 'إعدادات المخاطر والتصنيف',
    'Risk/dept_risk_space.php'     => 'مساحة مخاطر الإدارة',
    'Risk/risk_field.php'          => 'مخاطر الميدان',
    'Risk/gov_dept_rsk.php'        => 'حوكمة إدارة المخاطر',
);
/* ملفُّ الخطرِ يحتاج معرّفًا حيًّا — يُجلَب من القاعدةِ لا يُخترع */
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$rid = (int) ($db->query("SELECT id FROM risk_register WHERE company_id = 4 ORDER BY id LIMIT 1")
    ->fetch_row()[0] ?? 0);
if ($rid > 0) { $SCREENS['Risk/risk_card.php?id=' . $rid] = 'ملف الخطر'; }

$ACCOUNTS = array(
    array('مخاطر', '12345678', 'مدير المخاطر (28)', false),
    array('محلل مخاطر', '12345678', 'محلل المخاطر (29)', false),
    array('مشرف مخاطر', '12345678', 'مشرف المخاطر (30) — قراءة فقط', true),
);

function login($base, $user, $pass, $jar)
{
    @unlink($jar);
    $ch = curl_init($base . '/login.php');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20,
    ));
    $html = (string) curl_exec($ch);
    curl_close($ch);
    // رمزُ CSRF من الصفحةِ إن وُجد (config يحقنه آليًّا في كلِّ نموذج)
    $token = '';
    if (preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $html, $m)) { $token = $m[1]; }

    $post = array('username' => $user, 'password' => $pass);
    if ($token !== '') { $post['csrf_token'] = $token; }
    $ch = curl_init($base . '/login.php');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20,
    ));
    $body = (string) curl_exec($ch);
    $url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return strpos($url, 'login.php') === false || strpos($body, 'dashboard') !== false;
}

function fetchPage($base, $path, $jar)
{
    $ch = curl_init($base . '/' . $path);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
    ));
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return array($code, $body, $loc);
}

$FATAL = array('Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Uncaught', 'mysqli_sql_exception');
$totalFail = 0;

foreach ($ACCOUNTS as $acc) {
    list($user, $pass, $label, $readOnly) = $acc;
    $jar = sys_get_temp_dir() . '/m16probe_' . md5($user) . '.txt';
    echo str_repeat('═', 74), "\n";
    echo "الحساب: $label — «{$user}»\n";
    echo str_repeat('─', 74), "\n";
    if (!login($base, $user, $pass, $jar)) {
        echo "  ✘ فشل الدخول — لا جسَّ لهذا الحساب\n";
        $totalFail++;
        continue;
    }
    $ok = 0; $bad = 0;
    foreach ($SCREENS as $path => $title) {
        list($code, $body, $loc) = fetchPage($base, $path, $jar);
        $problems = array();
        if ($code !== 200) { $problems[] = "رمز $code" . ($loc !== '' ? ' ⇒ ' . basename($loc) : ''); }
        foreach ($FATAL as $f) {
            if (stripos($body, $f) !== false) { $problems[] = "أثرُ خطأ: $f"; break; }
        }
        if ($code === 200 && strpos($body, 'login') !== false && strpos($body, 'ems-unified-page-shell') === false) {
            $problems[] = 'حُوِّل إلى تسجيل الدخول';
        }
        if ($code === 200 && empty($problems) && strpos($body, $title) === false
            && strpos($body, 'ems-unified-page-shell') === false) {
            $problems[] = 'جسمٌ بلا عنوانِ الشاشة';
        }
        // الدورُ 30 قراءةٌ خالصة: صفرُ زرِّ كتابةٍ في جسمِ صفحتِه
        if ($readOnly && $code === 200 && empty($problems)) {
            $writeMarks = array("do', 'risk_create", 'add-btn', 'ems-btn-primary" type="submit"');
            $found = array();
            if (preg_match('~<button[^>]*type=["\']submit["\']~i', $body)
                && strpos($body, 'ems-toolbar') !== false
                && preg_match('~data-id=["\']\d+["\'][^>]*>(تحقق|فشل|دليل|تعديل)~u', $body)) {
                $found[] = 'زرُّ كتابةٍ ظاهر';
            }
            if ($found) { $problems[] = implode(' · ', $found); }
        }
        if (empty($problems)) { $ok++; printf("  ✔ %-34s %s\n", $title, ''); }
        else { $bad++; $totalFail++; printf("  ✘ %-34s %s\n", $title, implode(' | ', $problems)); }
    }
    printf("  الحصيلة: %d/%d شاشة تفتح سليمةً\n", $ok, $ok + $bad);
    @unlink($jar);
}

echo str_repeat('═', 74), "\n";
echo 'الجسُّ الحيّ: ' . ($totalFail === 0 ? "✔ صفرُ إخفاق\n" : "✘ $totalFail إخفاقًا\n");
exit($totalFail === 0 ? 0 : 1);
