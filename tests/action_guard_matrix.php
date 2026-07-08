<?php
/**
 * K10 — مصفوفة دور×نقطة لحارس الفعل (ADR-06) — «دلالة السجل لا مجرد خلوّه»
 * ───────────────────────────────────────────────────────────────────────────
 * تُقيّم قرار الحارس نفسه (نفس دوال الصلاحيات ونفس بيانات role_permissions)
 * لكل دورٍ نشطٍ على كل نقطة سجل — **تقييم القرار لا تنفيذ الأثر** (نمط مراقبة
 * K5): صفر استدعاء معالجات، صفر كتابة، صفر تلويث سجل أمني.
 *
 * التصنيف: خلية denied تكون would-block حقيقيًا فقط إن كانت النقطة تُستدعى
 * من سطحٍ مشترك (topbar/includes/assets أو موديول آخر) يصل إليه الدور دون
 * صلاحية الشاشة الأم؛ أما المستدعاة حصرًا من شاشات موديولها فحجبها دفاعُ
 * عمقٍ لا كسرُ مسارٍ شرعي (الدور بلا view لا يفتح الشاشة أصلًا).
 *
 * التشغيل: php tests/action_guard_matrix.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/action_guard.php';
mysqli_report(MYSQLI_REPORT_OFF);

$registry = ems_action_guard_registry();

// ── 1) الأدوار النشطة (مستخدم واحد ممثلًا لكل دور؛ السوبر يمرّ بالتصميم) ──
$roles = array();
$res = $conn->query("SELECT u.role, MIN(u.id) AS sample_user, COUNT(*) AS n, COALESCE(r.name,'?') AS rname
                     FROM users u LEFT JOIN roles r ON r.id = u.role
                     WHERE u.status = 'active'
                     GROUP BY u.role, r.name");
while ($x = $res->fetch_assoc()) {
    $superRole = defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1';
    if (strval($x['role']) === strval($superRole)) { continue; }
    $roles[] = $x;
}
usort($roles, function ($a, $b) { return intval($a['role']) - intval($b['role']); });

// ── 2) خريطة الوصول الساكنة: من يستدعي كل نقطة؟ (سطح مشترك أم موديول محلي) ──
$root = dirname(__DIR__);
$callers = array(); // suffix => array of referencing files (خارج ملف النقطة ذاته)
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$sources = array();
foreach ($rii as $f) {
    if (!$f->isFile()) { continue; }
    $p = strtolower(str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1)));
    if (preg_match('#^(\.git|logs|vendor|database|docs|tests|uploads|\.ssdiff)/#', $p)) { continue; }
    if (!preg_match('/\.(php|js)$/', $p)) { continue; }
    $sources[$p] = file_get_contents($f->getPathname());
}
foreach ($registry as $suffix => $def) {
    $base = basename($suffix);
    $dir = dirname($suffix);           // مثل maintenance
    $qualified = $dir . '/' . $base;   // مثل maintenance/get_breakdown_count.php
    $refs = array(); $global = array();
    foreach ($sources as $p => $src) {
        if (substr($p, -strlen($suffix)) === $suffix) { continue; }          // الملف نفسه
        if ($p === 'includes/action_guard.php') { continue; }                // السجل يذكر الكل — ليس مستدعيًا
        if ($p === 'config.php') { continue; }                               // سلاسل الrate-limiter — ليست نداءً
        if (stripos($src, $base) === false) { continue; }
        $refs[] = $p;
        // النداء النسبي المجرد يحلّ إلى دليل الصفحة ذاتها — لا يعبر الموديولات.
        // العبور الحقيقي = ذكرٌ مؤهَّلٌ بالمسار (دليل/ملف) من ملفٍ خارج الدليل.
        $inside = (stripos($p, $dir . '/') === 0);
        if (!$inside && stripos($src, $qualified) !== false) { $global[] = $p; }
    }
    $callers[$suffix] = array('all' => $refs, 'global' => $global);
}

// ── 3) التقييم: نفس منطق الحارس (registry → verbs → check_page_permissions) ──
$AUTO_VERBS = array('view', 'add', 'edit', 'delete');
$cells = 0; $allowed_cells = 0; $denied = array();
foreach ($roles as $r) {
    $_SESSION['user'] = array('id' => intval($r['sample_user']), 'role' => strval($r['role']));
    foreach ($registry as $suffix => $def) {
        if ($def['action'] === 'public') { $cells++; $allowed_cells++; continue; }
        $verbs = $def['action'] === 'auto' ? $AUTO_VERBS : array($def['action']);
        foreach ($verbs as $verb) {
            $cells++;
            $key = 'can_' . $verb;
            $ok = false;
            foreach ($def['modules'] as $code) {
                $perms = check_page_permissions($conn, $code);
                if (!empty($perms[$key])) { $ok = true; break; }
            }
            if ($ok) { $allowed_cells++; }
            else {
                // سطح مشترك لا يكون would-block إلا إن كان الدور يفتح صفحة
                // المستدعي أصلًا — ببوابة الصفحة الحقيقية: شاشات Approvals
                // تُبوَّب بقائمة الثوابت الصريحة (ADR-07) لا بصلاحية الموديول؛
                // والباقي بview موديول صفحة النداء (تقدير أعلى تحفّظًا).
                $reach = 'module-local'; $via = '';
                foreach ($callers[$suffix]['global'] as $callerPage) {
                    if (strpos($callerPage, 'approvals/') === 0) {
                        $opens = in_array(strval($r['role']), EMS_ROLES_HOURS_APPROVAL_ACCESS, true);
                    } else {
                        $callerModule = ucfirst(dirname($callerPage)) . '/';
                        $cp = check_page_permissions($conn, $callerModule);
                        $opens = !empty($cp['can_view']);
                    }
                    if ($opens) { $reach = 'GLOBAL-SURFACE'; $via = $callerPage; break; }
                    $reach = 'global-unreachable'; // عابرة لكن الدور لا يفتح صفحتها
                }
                $denied[] = array('role' => $r['role'], 'rname' => $r['rname'],
                                  'suffix' => $suffix, 'verb' => $verb, 'via' => $via,
                                  'reach' => $reach);
            }
        }
    }
}
unset($_SESSION['user']);

// ── 4) التقرير ──
printf("أدوار مُقيَّمة: %d · خلايا (دور×نقطة×فعل): %d · مسموح: %d · مرفوض: %d\n",
    count($roles), $cells, $allowed_cells, count($denied));

$globalHits = array_values(array_filter($denied, function ($d) { return $d['reach'] === 'GLOBAL-SURFACE'; }));
printf("\n== would-block حقيقية (الدور يفتح صفحة المستدعي والنقطة تُحجب عنه): %d ==\n", count($globalHits));
foreach ($globalHits as $d) {
    printf("  !! role=%s(%s) %s verb=%s — عبر: %s\n", $d['role'], $d['rname'], $d['suffix'], $d['verb'], $d['via']);
}
if (!$globalHits) { echo "  (لا شيء — كل الحجب المتبقي على مسارات لا يصلها الدور أصلًا)\n"; }

echo "\n== حجب دفاع-عمق (الدور لا يفتح الشاشة المستدعية أصلًا) ==\n";
$byEndpoint = array();
foreach ($denied as $d) { if ($d['reach'] !== 'GLOBAL-SURFACE') { $byEndpoint[$d['suffix']][] = $d['role'] . ':' . $d['verb']; } }
ksort($byEndpoint);
foreach ($byEndpoint as $suffix => $list) {
    printf("  %-50s denied=%d\n", $suffix, count($list));
}

// نقاط لا مرجع لها إطلاقًا (ميتة؟) — للعلم
echo "\n== نقاط بلا أي مستدعٍ في الشيفرة (للمراجعة) ==\n";
$none = 0;
foreach ($callers as $suffix => $c) { if (empty($c['all'])) { printf("  ? %s\n", $suffix); $none++; } }
if (!$none) { echo "  (لا شيء)\n"; }

exit(count($globalHits) === 0 ? 0 : 1);
