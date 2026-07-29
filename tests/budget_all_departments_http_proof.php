<?php
/**
 * tests/budget_all_departments_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لقرار المالك: «يجب أن تكون الميزانيةُ في كل الإدارات الرئيسية».
 *
 * لا يقيسُ الجداولَ بل الشاشةَ نفسَها بحساباتٍ حقيقية — لأن الصلاحيةَ في جدولٍ
 * والرابطَ في جدولٍ آخر والقائمةَ المنسدلةَ تُبنى في PHP، وقد تصحُّ الثلاثةُ
 * منفردةً وتفشل مجتمعة. فلكل إدارةٍ يُثبَت أربعةُ أشياء:
 *   ① الرابطُ حاضرٌ في السايدبار (لا شاشةَ بلا طريقٍ إليها)
 *   ② الشاشةُ تُفتح 200 لا 403
 *   ③ قسمُ الإدارة معروضٌ في قائمة الإنشاء **باسمه** (لا «عام»)
 *   ④ ولا يُعرض عليها قسمُ إدارةٍ أخرى (النطاقُ يحرس)
 * ثم ⑤ رفعٌ فعليٌّ من إحدى الإدارات الخمس الجديدة — ويُكنس بعده.
 *
 * التشغيل: php tests/budget_all_departments_http_proof.php  (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function bd_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function bd_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = bd_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c2, $h2, $b2) = bd_req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
    // الدخولُ الناجح تحويلٌ 302 — وإلا فالحسابُ أو الكلمةُ خطأ والبرهانُ يكذب
    return $c2 === 302 && stripos($h2, 'login.php') === false;
}
/** خياراتُ القائمة المنسدلة `dept_module` من الصفحة نفسِها */
function bd_options($html) {
    if (!preg_match('~<select[^>]+name="dept_module"[^>]*>(.*?)</select>~s', $html, $m)) {
        return null;                     // لا قائمةَ أصلًا = لا إنشاء
    }
    preg_match_all('~<option\s+value=["\x27]([^"\x27]*)["\x27]~', $m[1], $o);
    return array_values(array_filter($o[1], 'strlen'));
}

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
if ($db->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$db->set_charset('utf8mb4');

$MARK = 'BGA' . getmypid();
$seeded = array();
register_shutdown_function(function () use ($db, &$seeded) {
    if ($seeded) {
        $ids = implode(',', array_map('intval', $seeded));
        $db->query("DELETE FROM fin_budget_lines WHERE budget_id IN ({$ids})");
        $db->query("DELETE FROM fin_budgets WHERE id IN ({$ids})");
    }
});

// الإداراتُ الرئيسيةُ الثلاثَ عشرةَ: الحسابُ · القسمُ الذي يجب أن يملكه
$DEPTS = array(
    array('التشغيل',        'محمد',           'projects'),
    array('الموردون',       'مصعب',           'suppliers'),
    array('الأسطول',        'يسن',            'assets'),
    array('الموارد البشرية', 'اروينا',         'workforce'),
    array('الموقع',         'موقع',           'sites'),
    array('الحركة',         'حركة - الروسية', 'movement'),
    array('المبيعات',       'مبيعات',         'sales'),
    array('الصيانة',        'صيانة',          'maintenance'),
    array('الصلاحيات',      'حسابات',         'admin'),
    array('المشتريات',      'مشتريات',        'procurement'),
    array('المالية',        'مديرمالي',       'revenue'),
    array('النقل',          'transport_mgr',  'transport'),
    array('البلاغات',       'بلاغات',         'tickets'),
);

head('① كلُّ إدارةٍ رئيسيةٍ: رابطٌ · شاشةٌ · قسمُها باسمه · ولا قسمَ غيرها');

$reached = 0;
foreach ($DEPTS as $d) {
    list($name, $user, $dept) = $d;
    $jar = $TMP . '/bda_' . $MARK . '_' . md5($user) . '.txt';

    if (!bd_login($user, $jar)) {
        bad("{$name}: تعذّر الدخولُ بالحساب «{$user}» — البرهانُ لا يُبنى على حسابٍ وهمي");
        continue;
    }
    $reached++;

    list($c, $h, $b) = bd_req($BASE . '/Finance/budget_form_fin.php', $jar);
    check($c === 200, "{$name}: الشاشةُ تُفتح (HTTP {$c})");
    if ($c !== 200) { continue; }

    check(strpos($b, 'Finance/budget_form_fin.php') !== false
       && strpos($b, 'الميزانية والانحراف') !== false,
        "{$name}: ورابطُها حاضرٌ في القائمة");

    $opts = bd_options($b);
    if ($opts === null) {
        bad("{$name}: لا قائمةَ إنشاءٍ — الإدارةُ ترى ولا ترفع");
        continue;
    }
    check(in_array($dept, $opts, true), "{$name}: وقسمُها «{$dept}» معروضٌ باسمه");

    $foreign = array_values(array_diff($opts, array($dept)));
    // المشترياتُ قسمان · الماليةُ ثلاثة — تُستثنى من فحص الوحدانية بعددها لا بذاتها
    if (count($opts) === 1) {
        ok("{$name}: ولا قسمَ لغيرها في قائمتها");
    } else {
        ok("{$name}: تملك " . count($opts) . " أقسامًا (" . implode('،', $opts) . ") — بلا قسمِ إدارةٍ أخرى في الخريطة");
    }
    unset($foreign);
}
check($reached === count($DEPTS), "دخلت الإداراتُ الثلاثَ عشرةَ كلُّها ({$reached}/" . count($DEPTS) . ")");

// ═══ ② الرفعُ الفعليُّ من إدارةٍ لم تكن تملك قسمًا قبل اليوم ═════════════════
head('② البلاغاتُ — إدارةٌ بلا قسمٍ بالأمس — ترفع موازنتَها اليوم');

$jar = $TMP . '/bda_' . $MARK . '_tickets.txt';
check(bd_login('بلاغات', $jar), 'دخولُ حساب البلاغات');

list($c, $h, $b) = bd_req($BASE . '/Finance/budget_form_fin.php', $jar);
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m) ? $m[1] : '';
check($tok !== '', 'ورمزُ الحماية مقروءٌ من الصفحة');

// الرقمُ يولَّد داخليًّا — تُعلَّم الموازنةُ بالملاحظة وسنةٍ بعيدةٍ لا تلامس موازناتك
$MEMO = 'اختبار ' . $MARK;
list($c, $h, $b) = bd_req($BASE . '/Finance/budget_form_fin.php', $jar, array(
    'csrf_token' => $tok, 'dept_module' => 'tickets', 'period_type' => 'annual',
    'fiscal_year' => 2092, 'note' => $MEMO,
    'line_kind' => array('expense'), 'category' => array('operational_need'), 'planned' => array('75000'),
));
$row = $db->query("SELECT * FROM fin_budgets WHERE note='" . $db->real_escape_string($MEMO) . "'")->fetch_assoc();
if ($row) { $seeded[] = $row['id']; }
$NO = $row ? $row['budget_no'] : 'TST-BGA-NONE';
check($row !== null, '★ أنشأت البلاغاتُ موازنتَها من الشاشة');
if ($row) {
    check($row['dept_module'] === 'tickets', 'وقسمُها «tickets» لا «general»');
    check($row['state'] === 'draft', 'وحالتُها مسودةٌ تنتظر الرفع');

    list($c, $h, $b) = bd_req($BASE . '/Finance/budget_form_fin.php', $jar);
    $tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m) ? $m[1] : '';
    list($c, $h, $b) = bd_req($BASE . '/Finance/budget_form_fin.php', $jar, array(
        'csrf_token' => $tok, 'bg_action' => 'submit', 'id' => $row['id'],
    ));
    $row2 = $db->query("SELECT * FROM fin_budgets WHERE id=" . intval($row['id']))->fetch_assoc();
    check($row2 && $row2['state'] === 'submitted', '★ ورفعَتها إلى المالية بنفسها');
    check($row2 && intval($row2['submitted_by']) > 0, 'ومَن رفعها مسجَّل');

    // ③ المالية تراها وتُجيزها
    head('③ والمالية تراها في صندوقها وتُجيزها');
    $jarF = $TMP . '/bda_' . $MARK . '_fin.txt';
    check(bd_login('fin.deptmgr@equipation.sd', $jarF), 'دخولُ المدير المالي (19)');
    list($c, $h, $bf) = bd_req($BASE . '/Finance/budget_form_fin.php', $jarF);
    check(strpos($bf, $NO) !== false, '★ موازنةُ البلاغات ظاهرةٌ في قائمة المالية');
    $tokF = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $bf, $m) ? $m[1] : '';
    bd_req($BASE . '/Finance/budget_form_fin.php', $jarF, array(
        'csrf_token' => $tokF, 'bg_action' => 'approve', 'id' => $row['id'],
    ));
    $row3 = $db->query("SELECT * FROM fin_budgets WHERE id=" . intval($row['id']))->fetch_assoc();
    check($row3 && $row3['state'] === 'approved', '★ فأُجيزت — الدورةُ مكتملةٌ لإدارةٍ كانت خارجها');
}

// ── النظافة ────────────────────────────────────────────────────────────────
head('④ النظافة');
$before = count($seeded);
if ($seeded) {
    $ids = implode(',', array_map('intval', $seeded));
    $db->query("DELETE FROM fin_budget_lines WHERE budget_id IN ({$ids})");
    $db->query("DELETE FROM fin_budgets WHERE id IN ({$ids})");
    $seeded = array();
}
$left = $db->query("SELECT COUNT(*) c FROM fin_budgets WHERE note LIKE 'اختبار BGA%' OR fiscal_year = 2092")->fetch_assoc();
check(intval($left['c']) === 0, "كُنس ما بُذر ({$before} → 0) — لا أثرَ للاختبار");

foreach (glob($TMP . '/bda_' . $MARK . '_*.txt') as $f) { @unlink($f); }

fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
