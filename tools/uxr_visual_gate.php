<?php
/**
 * uxr_visual_gate.php — بوابة القبول البصري الآلية (UXR-01 · U9 · update0011)
 * ═══════════════════════════════════════════════════════════════════════════
 * 16 معيارًا آليًّا تُقاس من ثلاث قنوات: الملفات (بنية القشرة والمكونات) ·
 * القاعدة (نظافة التوليد) · HTTP حي بجلسة مسجَّلة (التصيير الفعلي).
 * منهج القياس معلن مع كل معيار — ما لا يُقاس إلا بمتصفح (بكسلات) يُقاس هنا
 * بوكيله البنيوي المعلن (token/markup) ويكتمل بصريًّا في جولة UAT.
 *
 * التشغيل: php tools/uxr_visual_gate.php   (يخرج 0 عند 16/16)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
$root = dirname(__DIR__);

$results = array();
function gate($no, $name, $ok, $evidence) {
    global $results;
    $results[] = array($no, $name, (bool) $ok, $evidence);
}

/* ── قناة الملفات ─────────────────────────────────────────────────────── */
$shellCss = (string) @file_get_contents($root . '/assets/css/ems-shell.css');
$compsJs  = (string) @file_get_contents($root . '/assets/js/ems-components.js');
$inheader = (string) @file_get_contents($root . '/inheader.php');
$topbar   = (string) @file_get_contents($root . '/includes/topbar.php');
$pageHdr  = (string) @file_get_contents($root . '/includes/page_header.php');
$dash     = (string) @file_get_contents($root . '/main/dashboard.php');
$tsField  = (string) @file_get_contents($root . '/Timesheet/timesheet.php');
$guards   = (string) @file_get_contents($root . '/includes/governance_guard.php')
          . (string) @file_get_contents($root . '/includes/permissions_helper.php');

gate(1, 'AS-01: سقف الشريط العلوي 64px (token)',
    strpos($shellCss, '--ems-shell-topbar-h: 64px') !== false
    && strpos($shellCss, 'max-height: var(--ems-shell-topbar-h)') !== false,
    'ems-shell.css يفرض max-height على .ems-topbar');

gate(3, 'AS-02: مبدّل سياق واحد بدل الحاويات الأربع',
    strpos($topbar, 'emsCtxSwitcher') !== false
    && strpos($topbar, 'الموظف المسؤول:') === false,
    'topbar.php: emsCtxSwitcher موجود والحاويات المنفصلة أُزيلت');

gate(4, 'AS-03: حالتا السايدبار 264/68 (tokens)',
    strpos($shellCss, '--ems-shell-sidebar-w: 264px') !== false
    && strpos($shellCss, '--ems-shell-sidebar-w-mini: 68px') !== false
    && strpos($shellCss, '--sidebar-width-expanded: var(--ems-shell-sidebar-w)') !== false,
    'ems-shell.css يوائم متغيري العرض القائمين على 264/68');

gate(6, 'AS-08/UI-DEF-06: صفر رسالة حوكمة في الرابط (الحراس)',
    strpos($guards, "?msg=' . urlencode") === false
    && substr_count($guards, 'ems_gov_flash_redirect') >= 5,
    'الحارسان يستعملان ems_gov_flash_redirect حصرًا');

gate(7, 'UI-13: حامل الرسائل داخل الشاشة (inheader)',
    strpos($inheader, 'emsGovFlash') !== false
    && strpos($inheader, 'ems_flash_gov') !== false,
    'inheader.php يصيّر ems_flash_gov برمز الخطأ ثم يمسحه');

gate(8, 'AS-04: سطر الأرقام السياقية في رأس الصفحة',
    strpos($pageHdr, 'header_context') !== false
    && strpos($shellCss, '.ems-page-context') !== false
    && strpos($shellCss, '.main_head { max-height: 96px') !== false,
    'page_header.php يدعم $header_context وems-shell يسقف الترويسة');

gate(10, 'UI-05/06: حارس صفر الأعمدة محمَّل مركزيًّا',
    strpos($compsJs, "column-visibility.dt") !== false
    && strpos($inheader, 'ems-components.js') !== false,
    'ems-components.js يعيد آخر عمود فورًا ويُحمَّل من inheader');

gate(11, 'UI-11/12: تمييز «لا بيانات» عن «لا نتائج»',
    strpos($compsJs, 'zeroRecords') !== false
    && strpos($compsJs, 'emptyTable') !== false
    && strpos($compsJs, 'ems-state-empty') !== false
    && strpos($compsJs, 'ems-state-noresults') !== false,
    'لغة DataTables الافتراضية + مكوّنا الحالة منفصلان');

gate(12, 'UI-17: حارس الرسم — لا رسم بلا بيانات',
    strpos($compsJs, 'chartGuard') !== false
    && strpos($compsJs, 'محاور افتراضية') !== false,
    'EmsUI.chartGuard يستبدل الرسمَ الفارغ بحالة UI-11');

gate(13, 'UI-DEF-02: صفر أصفار ملفقة في لوحات الأدوار غير الممثلة',
    strpos($dash, "['fa-users', null,") !== false
    && strpos($dash, '&mdash;') !== false,
    'dashboard.php يعرض «—» بدل صفر كاذب للدور بلا فرع');

gate(15, 'UI-16: شريحة المزامنة ظاهرة في الشاشة الميدانية',
    strpos($tsField, 'tsSyncChip') !== false
    && strpos($compsJs, 'syncChip') !== false,
    'Timesheet/timesheet.php تركّب EmsUI.syncChip');

gate(16, 'AS-07: شبكة 12 وسلّم 4 وسقف 1440 (tokens)',
    strpos($shellCss, '--ems-shell-content-max: 1440px') !== false
    && strpos($shellCss, 'repeat(12, minmax(0, 1fr))') !== false
    && strpos($shellCss, '--ems-sp-1: 4px') !== false,
    'ems-shell.css يعلن الشبكة والسلّم والسقف');

/* ── قناة القاعدة ─────────────────────────────────────────────────────── */
$envFile = $root . '/.env';
$dbName = 'equipation_manage'; $dbHost = '127.0.0.1'; $dbPort = 3307;
if (is_file($envFile)) {
    foreach (file($envFile) as $l) {
        if (preg_match('~^DB_NAME=(.+)$~', trim($l), $m)) { $dbName = trim($m[1]); }
    }
}
$db = @new mysqli($dbHost, 'root', '', $dbName, $dbPort);
if ($db && !$db->connect_errno) {
    $db->set_charset('utf8mb4');
    $r = $db->query("SELECT COUNT(*) c FROM (SELECT ni.role_id, ni.group_id, ni.label_ar, SUBSTRING_INDEX(ni.route,'#',1) b, COUNT(*) n
                     FROM nav_items ni WHERE ni.active=1 GROUP BY 1,2,3,4 HAVING n>1) x");
    $dupSameGroup = intval($r->fetch_assoc()['c']);
    gate(5, 'AS-03/UI-DEF-05: صفر عنصر سايدبار مكرر داخل المجموعة',
        $dupSameGroup === 0, "same-group dups = $dupSameGroup");

    $r = $db->query("SELECT COUNT(*) c FROM job_titles WHERE name REGEXP '-[0-9]{4}$' AND active=1");
    $polluted = intval($r->fetch_assoc()['c']);
    gate(14, 'UI-DEF-01: صفر مسمى وظيفي شخصي نشط',
        $polluted === 0, "person-named active titles = $polluted");
} else {
    gate(5, 'AS-03: صفر عنصر مكرر (قاعدة)', false, 'DB unreachable');
    gate(14, 'UI-DEF-01: مسميات نظيفة (قاعدة)', false, 'DB unreachable');
}

/* ── قناة HTTP الحية (جلسة مسجلة — حساب اختبار الدور 1) ──────────────── */
function uxr_http_login($base, $user, $pass) {
    $jar = sys_get_temp_dir() . '/uxr_gate_' . md5($user) . '.txt';
    @unlink($jar);
    $get = function ($url, $post = null) use ($jar) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
        if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $b = curl_exec($ch); curl_close($ch); return (string) $b;
    };
    $login = $get("$base/login.php");
    $csrf = preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m) ? $m[1] : '';
    $get("$base/login.php", array('username' => $user, 'password' => $pass, 'csrf_token' => $csrf));
    return $get;
}
$base = 'http://localhost/ems';
$http = uxr_http_login($base, 'محمد', '12345678');
$board = $http("$base/main/role_board.php");

$actionsN = preg_match_all('~class="ems-topbar-icon[^"]*"~u', $board, $mm);
gate(2, 'AS-01: ≤5 أفعال عالمية ظاهرة (HTTP حي)',
    $actionsN > 0 && $actionsN <= 5, "topbar icons = $actionsN");

$kpiN  = substr_count($board, 'ems-kpi-card');
$metaN = substr_count($board, 'ems-kpi-meta');
gate(9, 'UI-07: كل بطاقة KPI بعقدها السباعي (HTTP حي)',
    $kpiN > 0 && $metaN >= $kpiN, "kpi=$kpiN meta=$metaN (لوحة الدور 1)");

/* ── التقرير ──────────────────────────────────────────────────────────── */
usort($results, function ($a, $b) { return $a[0] - $b[0]; });
$pass = 0;
foreach ($results as $r) {
    echo ($r[2] ? '✔' : '✘') . ' معيار ' . str_pad($r[0], 2, '0', STR_PAD_LEFT) . " · {$r[1]}\n    ↳ {$r[3]}\n";
    if ($r[2]) { $pass++; }
}
$total = count($results);
echo str_repeat('─', 60) . "\n";

/* ══ ◆ القرارُ الحاكمُ الثاني (MD-04): البوابةُ تُقسَّم ثلاثًا ═══════════════
 * «لا تُقرأ البوابةُ البنيويةُ نجاحًا لالتزامِ الشاشات» — فالبنيويةُ تسأل
 * أتوجد الرموزُ والمكوناتُ في المكتبةِ والقشرة؟ والتبنّي يسأل أتستدعيها
 * الشاشاتُ فعلًا؟ والتدقيقُ يسأل أفُحصت بالمعاييرِ الستةَ عشرَ بصريًّا؟
 * ولا تُعلن G5 خضراءَ قبل الثلاثِ معًا — والإعلانُ بواحدةٍ مخالفةٌ حاكمة.
 */
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');
$components = array('EmsUI.', 'ems_shell_axes', 'EmsDetailsModal',
    'dept_risk_space.php', 'dept_gov_space.php', 'fin_analysis_shell.php');
$adoptDen = 0; $adoptNum = 0;
foreach ($dirs as $d) {
    foreach (glob($root . '/' . $d . '/*.php') as $f) {
        $src = (string) @file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; } // ◆ المقامُ: الشاشاتُ الحيةُ بمظلة السايدبار
        $adoptDen++;
        foreach ($components as $c) {
            if (strpos($src, $c) !== false) { $adoptNum++; break; }
        }
    }
}
$adoptPct = $adoptDen > 0 ? round($adoptNum / $adoptDen * 100, 1) : 0.0;

/* G5-C: التدقيقُ البصريُّ — يحتاج بصرَ إنسانٍ أمام شاشة، ويُقرأ من محاضرِه
   إن وُجدت. ولا يُدَّعى ما لم يُقَس (UXR-0131). */
$auditDir = $root . '/docs/update0012/visual_audit';
$audited = 0;
if (is_dir($auditDir)) {
    foreach (glob($auditDir . '/*.md') as $__m) {
        /* الفهرسُ ليس محضرًا — وإلا صار المقياسُ 355/354 وهو عددٌ لا يُقرأ */
        if (strtolower(basename($__m)) === 'readme.md') { continue; }
        $audited++;
    }
}

$gA = ($pass === $total);
$gB = ($adoptNum === $adoptDen && $adoptDen > 0);
$gC = ($audited >= $adoptDen && $adoptDen > 0);

echo "◆ بوابةُ القبولِ البصريِّ مقسومةٌ ثلاثًا (القرارُ الحاكمُ الثاني · MD-04):\n\n";
echo '  G5-A البنيوية  — أتوجد الرموزُ والمكوناتُ في المكتبةِ والقشرة؟   '
   . ($gA ? '🟢' : '🔴') . "  {$pass}/{$total}\n";
echo '  G5-B التبنّي    — أتستدعي الشاشاتُ المكوناتِ فعلًا؟              '
   . ($gB ? '🟢' : '🔴') . "  {$adoptNum}/{$adoptDen} = {$adoptPct}٪\n";
echo '  G5-C التدقيق   — أفُحصت بالمعاييرِ الستةَ عشرَ بصريًّا؟          '
   . ($gC ? '🟢' : '🔴') . "  {$audited}/{$adoptDen}\n\n";
echo '  ◆ الحكم: G5 ' . ($gA && $gB && $gC ? '🟢 خضراء — الثلاثُ معًا'
    : '🕓 ليست خضراء — والبنيويةُ وحدَها لا تُقرأ نجاحًا للتبنّي (RSK-U5)') . "\n";
echo '  ◆ المقامُ الكاملُ ' . $adoptDen . ' شاشةً حيةً — لا مقامَ مستبعِدًا (MD-01 مُغلق)' . "\n";
echo "  ◆ G5-C بشريةٌ بطبيعتها — تُقاس بمحاضرِ تدقيقٍ في docs/update0012/visual_audit\n";
echo str_repeat('─', 60) . "\n";
echo "بوابة القبول البصري (G5-A البنيوية): $pass/$total\n";
exit($pass === $total ? 0 : 1);
