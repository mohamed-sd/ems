<?php
/**
 * tools/injfix01_golden_promotion_gate.php
 *   بوابةُ ترقيةِ الشاشاتِ الذهبيةِ التسع — INJ-FIX-01 · GAP-23
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «١٠/١٠ باجتيازِ بوابةِ الترقيةِ التسعِ — **إلزامًا لا خيارًا** ·
 *   وللمالكِ أن يختار بين بدائلَ ناجحةٍ أو يوقف الترقيةَ أو يطلب إعادةَ الاختبار
 *   · **وليس له أن يُرقّي راسبًا** في صفرِ الفقدِ أو الوصولِ أو الأمنِ أو
 *   الاختبارِ البشريّ».
 *
 * ◆ **فالتسعُ موضوعيةٌ يشغّلها المنفِّذ، والعاشرُ بشريٌّ لا يملكه**: اختبارٌ
 *   مستقلٌّ بيدِ مستخدمٍ حقيقيّ. **ولا يُدَّعى اجتيازُه ولا يُطوى** — يُسجَّل
 *   بندًا معلَّقًا باسمِ مالكِه، فالشاشةُ تبقى `pending` حتى يقع.
 *
 * ◆ **ولا يُرقّى راسب**: من رسب في أيِّ بوابةٍ من الأربعِ غيرِ القابلةِ للتجاوز
 *   (فقد · وصول · أمن · بشريّ) **لا يُوسَم مقبولًا بحالٍ**.
 *
 * التشغيل: php tools/injfix01_golden_promotion_gate.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT  = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* أسماءُ البواباتِ التسعِ وأيُّها غيرُ قابلٍ للتجاوز */
$GATES = array(
    'G1_EXISTS'      => array('الوجودُ على القرص',            false),
    'G2_ZERO_LOSS'   => array('صفرُ الفقد',                   true),
    'G3_REACHABLE'   => array('الوصولُ — رابطٌ نشطٌ يبلغها',  true),
    'G4_SECURITY'    => array('الأمنُ — حارسٌ قبلَ التصيير',  true),
    'G5_CSRF'        => array('حمايةُ الطلبِ في النماذج',      false),
    'G6_ENCODING'    => array('الترميزُ سليمٌ بلا تلف',        false),
    'G7_SHELL'       => array('القشرةُ الموحَّدة',             false),
    'G8_OWNERSHIP'   => array('مِلكيةٌ نهائيةٌ محسومة',        false),
    'G9_TABLE_STYLE' => array('تصميمُ الجداولِ الموحَّد',      false),
);

/* ══ الشاشاتُ العشر ═══════════════════════════════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT `id`,`screen_file`,`title_ar`,`state`,`pattern_state` FROM `gov_golden_approvals` ORDER BY `id`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

/* مراجعُ القياسِ — تُقرأ مرةً */
$navRoutes = array();
$q = $conn->query("SELECT DISTINCT `route` FROM `nav_items` WHERE `active` = 1");
while ($q && $x = $q->fetch_row()) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $x[0])));
    if ($b !== '') { $navRoutes[$b] = 1; }
}
$owned = array();
$q = $conn->query("SELECT `route` FROM `gov_ownership_rulings`");
while ($q && $x = $q->fetch_row()) {
    $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $x[0])));
    if ($b !== '') { $owned[$b] = 1; }
}
$reg = array();
$rf = $ROOT . '/docs/baseline_20260821/extract/screen_registry.json';
if (is_file($rf)) {
    foreach ((array) json_decode((string) file_get_contents($rf), true) as $r) {
        $b = mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) ($r['route'] ?? ''))));
        if ($b !== '') { $reg[$b] = $r; }
    }
}

echo "══ بوابةُ ترقيةِ الشاشاتِ الذهبية — " . count($rows) . " شاشات ══\n";
echo str_repeat('─', 100) . "\n";

$allPass = 0; $failing = array();
foreach ($rows as $r) {
    $rel  = (string) $r['screen_file'];
    $abs  = $ROOT . '/' . $rel;
    $base = mb_strtolower(basename($rel));
    $src  = is_file($abs) ? (string) file_get_contents($abs) : '';
    $res  = array();

    $res['G1_EXISTS']    = is_file($abs);
    /* صفرُ الفقد: الشاشةُ ما تزال مسجَّلةً في السجلِّ الموحَّد */
    $res['G2_ZERO_LOSS'] = isset($reg[$base]);
    $res['G3_REACHABLE'] = isset($navRoutes[$base]);
    /* ◆ **الأمنُ يُقاس بحارسِ النظامِ لا بقائمةٍ خمّنتُها**: أولُ صياغةٍ طلبت
     *   `check_permission` وأخواتِها **فرسبت العشرُ كلُّها** — والعشرُ محروسةٌ
     *   فعلًا بـ`enforce_current_page_view_permission` و`check_page_permissions`
     *   ولم تكونا في قائمتي. **وبوابةٌ ترسُب على الجميعِ تقيس نفسَها لا النظام.**
     *   والحراسةُ طبقتان: حارسُ جلسةٍ يردُّ غيرَ الداخل، وحارسُ صلاحيةٍ يردُّ
     *   الداخلَ غيرَ المخوَّل — ويلزمان معًا.
     * ◆ **ويُتتبَّع الحارسُ خلالَ غلافِ وحدتِه**: `Risk/risk_register.php` يحرس
     *   بـ`risk_guard_screen()` — وهي في `Risk/_risk_common.php` **تنادي
     *   `check_page_permissions` نفسَها**. فطلبُ نداءٍ حرفيٍّ في الملفِّ يرسُب
     *   غلافًا مشروعًا. فيُتبَع مستوًى واحدٌ من التضمينِ داخلَ مجلَّدِ الشاشة. */
    $PERM_RE = '/(enforce_current_page_view_permission|check_page_permissions|check_view_permission'
             . '|check_permission|get_module_permissions|action_guard|has_any_permission)\s*\(/';
    /* ◆ والتتبُّعُ يشمل الطبقتَين معًا: `Risk/risk_register.php` **لا يفحص الجلسةَ
     *   ولا الصلاحيةَ في متنِه** — كلتاهما داخلَ `risk_guard_screen`. فيُبنى
     *   «مصدرٌ فعّالٌ» = متنُ الشاشةِ + أغلفةُ مجلَّدِها التي تُنادى منها. */
    $eff = $src;
    foreach (glob(dirname($abs) . '/_*.php') as $helper) {
        $hs = (string) @file_get_contents($helper);
        if (!preg_match_all('/function\s+(\w+)/', $hs, $all)) { continue; }
        foreach ($all[1] as $fn) {
            if (preg_match('/\b' . preg_quote($fn, '/') . '\s*\(/', $src)) { $eff .= "\n" . $hs; break; }
        }
    }
    $SESS_RE = "/\\\$_SESSION\s*\[\s*['\"]user['\"]\s*\]|require_login\s*\(|session_bootstrap/";
    $res['G4_SECURITY'] = (preg_match($SESS_RE, $eff) && preg_match($PERM_RE, $eff));
    /* CSRF: إن كان فيه نموذجُ POST فيلزمه الحقل */
    $hasPost = (bool) preg_match('/<form[^>]*method\s*=\s*["\']?post/i', $src);
    $res['G5_CSRF']      = !$hasPost || (strpos($src, 'csrf_field') !== false || strpos($src, 'csrf_token') !== false);
    /* الترميز: لا تسلسلَ موجَبيك */
    $res['G6_ENCODING']  = !preg_match('/[\x{00C3}][\x{0080}-\x{00BF}]{1,2}/u', $src);
    $res['G7_SHELL']     = (strpos($src, 'insidebar') !== false) || (strpos($src, 'u13_screen_kit') !== false);
    $res['G8_OWNERSHIP'] = isset($owned[$base])
        || (isset($reg[$base]) && in_array((string) ($reg[$base]['owner_basis'] ?? ''), array('RULING', 'CONSENSUS'), true));
    /* تصميمُ الجداول: إن كان فيه جدولٌ فليكن بالصنفِ الموحَّد */
    $hasTable = (strpos($src, '<table') !== false);
    $res['G9_TABLE_STYLE'] = !$hasTable || (bool) preg_match('/class\s*=\s*["\'][^"\']*\b(ems-table|table)\b/i', $src);

    $pass = 0; $failed = array(); $hardFail = array();
    foreach ($GATES as $g => $meta) {
        if (!empty($res[$g])) { $pass++; continue; }
        $failed[] = $g;
        if ($meta[1]) { $hardFail[] = $g; }
    }
    printf("  #%-2s %-34s %d/9%s\n", $r['id'], mb_substr($rel, 0, 34), $pass,
        $failed ? '  ✘ ' . implode(' · ', $failed) : '  ✔');
    if ($pass === 9) { $allPass++; }
    else { $failing[] = $rel . ' (' . implode(',', $failed) . ')'; }

    if ($APPLY) {
        /* ◆ لا يُوسَم مقبولًا إلا مجتازُ التسعِ كلِّها — ولا يُتجاوز راسبٌ صلبٌ بحال */
        $st = ($pass === 9 && !$hardFail) ? 'VISUAL_PATTERN_APPROVED' : 'PENDING';
        $basis = ($pass === 9 && !$hardFail) ? 'OBJECTIVE_GATE' : null;
        $note  = ($pass === 9)
            ? 'GAP-23: اجتازت البواباتِ الموضوعيةَ التسع — **ويبقى العاشرُ: اختبارٌ بشريٌّ مستقل**'
            : 'GAP-23: رسبت في ' . implode(' · ', $failed) . ' — ولا تُرقّى راسبة';
        $s2 = $conn->prepare("UPDATE `gov_golden_approvals`
                                 SET `pattern_state` = ?, `approval_basis` = ?,
                                     `basis_ref` = 'tools/injfix01_golden_promotion_gate.php',
                                     `owner_note` = ?, `decided_at` = NOW()
                               WHERE `id` = ?");
        $s2->bind_param('sssi', $st, $basis, $note, $r['id']);
        $s2->execute(); $s2->close();
    }
}
echo str_repeat('─', 100) . "\n";
printf("◆ **اجتازت التسعَ الموضوعية: %d من %d**\n", $allPass, count($rows));
foreach ($failing as $f) { echo "   ✘ {$f}\n"; }
echo "◆ **والعاشرُ لا يملكه المنفِّذ**: اختبارٌ بشريٌّ مستقلٌّ بيدِ مستخدمٍ حقيقيّ —\n";
echo "   يُسجَّل معلَّقًا باسمِه ولا يُدَّعى اجتيازُه. فالشاشةُ تبقى `pending` حتى يقع.\n";
if (!$APPLY) { echo "◆ للتسجيل: php tools/injfix01_golden_promotion_gate.php --apply\n"; }
