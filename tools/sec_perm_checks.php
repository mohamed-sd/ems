<?php
/**
 * tools/sec_perm_checks.php — حزامُ الحوكمة (SEC-GOV) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * أربعةُ فحوصٍ تحرس ما أُغلق في 2026-08-06، فلا يعود بانحراف بيانات:
 *
 *   ①  AC-GOV-01 · شاشةُ حوكمةٍ لا يملك عليها صلاحيةً إلا مالكُها أو إدارةُ
 *       الحسابات (1 · 15).                                            [ح-02]
 *   ②  AC-GOV-02 · لا صلاحيةَ كتابةٍ (add/edit/delete) على موديولٍ خارج
 *       القائمة الجانبية للدور — ما لم يكن مالكَه أو استثناءً معلَنًا. [ح-03]
 *   ③  AC-GOV-03 · كلُّ شاشةِ حوكمةٍ على القرص مسجَّلةٌ في modules — فالشاشةُ
 *       غيرُ المسجَّلة يمرُّ عليها الحارسُ المركزي شفافًا.              [ح-01]
 *   ④  AC-GOV-04 · كلُّ شاشةِ حوكمةٍ تستدعي بوابةَ governance_guard فعلًا.
 *
 * php tools/sec_perm_checks.php [--verbose]
 * الخروج: 0 نظيف · 1 خرقٌ قائم.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/roles.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$VERBOSE = in_array('--verbose', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROOT = dirname(__DIR__);

// ── السياسةُ نفسُها الواردة في هجرة 2026_08_06 وincludes/governance_guard.php ──
/** شاشاتُ الحوكمة — تُفحص بنيويًّا (مسجَّلةٌ ومحروسة) في ③ و④. */
$GOV_SCREENS = array(
    'main/users.php', 'main/all_assistants.php', 'Settings/roles.php',
    'Settings/role_permissions.php', 'Settings/modules.php', 'Settings/guard_classification.php',
);
/**
 * الشاشاتُ التي يقرّر role_permissions وصولَها — وحدَها تخضع لقاعدة «الحوكمةُ
 * لمالكها» ①. guard_classification.php مستثناةٌ: حارسُها قائمةُ أدوارٍ صلبةٌ في
 * الكود (1 · 19 · سوبر) لا تقرأ role_permissions أصلًا، فنزعُ صفِّها لا يحجب
 * ولا يعني شيئًا — وإسقاطُها هنا يمنع خرقًا كاذبًا دائمًا.
 */
$GOV_PERM_SCOPED = array(
    'main/users.php', 'main/all_assistants.php', 'Settings/roles.php',
    'Settings/role_permissions.php', 'Settings/modules.php',
);
$ADMIN_ROLES = array(EMS_ROLE_OPERATIONS_MGR, EMS_ROLE_PERMISSIONS_MGR);
$OPEN_SCREENS = array(
    'Tickets/ticket_contextual_open.php', 'Tickets/ticket_form.php', 'Tickets/tickets_list.php',
    'Maintenance/breakdowns.php', 'chats/index.php', 'main/dashboard.php', 'main/role_board.php',
);
$PERSONAL_PREFIX = array('Portal/', 'main/my_', 'main/profile.php', 'Settings/change_password.php', 'user_capacities.php');
$REDIRECT_ALIAS = array(
    'Timesheet/timesheet_type.php' => 'Timesheet/timesheet.php',
    // M-16: ملف الخطر شاشة تعمق تُفتح من السجل المركزي — بابها باب السجل
    'Risk/risk_card.php' => 'Risk/risk_register.php',
);

$modules = array();
$q = mysqli_query($conn, "SELECT id, code, owner_role_id FROM modules");
while ($r = mysqli_fetch_assoc($q)) { $modules[intval($r['id'])] = $r; }

$navOf = function ($rid) use ($conn) {
    $set = array();
    $q = mysqli_query($conn, "SELECT DISTINCT route FROM nav_items WHERE role_id = " . intval($rid));
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            if (!empty($r['route'])) {
                $p = parse_url($r['route'], PHP_URL_PATH);
                if ($p) { $set[basename($p)] = 1; }
            }
        }
    }
    return $set;
};
$isPersonal = function ($code) use ($PERSONAL_PREFIX) {
    foreach ($PERSONAL_PREFIX as $p) {
        if (strpos($code, $p) === 0 || basename($code) === $p) { return true; }
    }
    return false;
};
$subOfNav = function ($code, $nav) use ($REDIRECT_ALIAS) {
    $bn = basename($code);
    if (isset($REDIRECT_ALIAS[$code]) && isset($nav[basename($REDIRECT_ALIAS[$code])])) { return true; }
    $stem = preg_replace('/\.php$/', '', $bn);
    foreach (array_keys($nav) as $navBn) {
        $navStem = preg_replace('/\.php$/', '', $navBn);
        if ($navStem !== '' && $stem !== $navStem && strpos($stem, $navStem) === 0) { return true; }
    }
    return false;
};

$violations = array('gov' => array(), 'write' => array(), 'unreg' => array(), 'noguard' => array());

$roles = mysqli_query($conn, "SELECT id, name FROM roles WHERE (status='1' OR status=1) ORDER BY id");
while ($ro = mysqli_fetch_assoc($roles)) {
    $rid = intval($ro['id']);
    $ridStr = (string) $rid;
    $nav = $navOf($rid);
    $q = mysqli_query($conn, "SELECT module_id, can_view, can_add, can_edit, can_delete
                              FROM role_permissions WHERE role_id = $rid");
    while ($rp = mysqli_fetch_assoc($q)) {
        $mid = intval($rp['module_id']);
        if (!isset($modules[$mid])) { continue; }
        $code  = $modules[$mid]['code'];
        $owner = (string) intval($modules[$mid]['owner_role_id']);
        $v = intval($rp['can_view']);
        $w = (intval($rp['can_add']) || intval($rp['can_edit']) || intval($rp['can_delete']));

        if (in_array($code, $GOV_PERM_SCOPED, true)) {
            if ($owner !== $ridStr && !in_array($ridStr, $ADMIN_ROLES, true) && ($v || $w)) {
                $violations['gov'][] = sprintf('دور %-3s (%s) على %s #%s', $rid, mb_substr((string)$ro['name'], 0, 20), $code, $mid);
            }
            continue;
        }
        // شاشةُ حوكمةٍ بحارسٍ كوديٍّ (لا تقرأ role_permissions): لا ① ولا ②.
        if (in_array($code, $GOV_SCREENS, true)) { continue; }
        if (!$w) { continue; }
        if ($owner === $ridStr) { continue; }
        if (in_array($code, $OPEN_SCREENS, true)) { continue; }
        if ($isPersonal($code)) { continue; }
        if (isset($nav[basename($code)])) { continue; }
        if ($subOfNav($code, $nav)) { continue; }
        $violations['write'][] = sprintf('دور %-3s (%s) يكتب في %s #%s بلا باب', $rid, mb_substr((string)$ro['name'], 0, 20), $code, $mid);
    }
}

// ③ + ④ · تسجيلُ شاشات الحوكمة واستدعاؤها للبوابة
foreach ($GOV_SCREENS as $code) {
    $path = $ROOT . '/' . $code;
    if (!is_file($path)) { continue; }

    $st = $conn->prepare("SELECT id FROM modules WHERE code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    if (!$st->get_result()->fetch_assoc()) {
        $violations['unreg'][] = $code . ' غيرُ مسجَّلةٍ في modules — الحارسُ المركزي يمرُّ عليها شفافًا';
    }

    // بوابةٌ مقبولةٌ = إحدى ثلاث: بوابةُ الحوكمة · فحصُ صلاحيةِ صفحةٍ صريحٌ مع
    // can_view · قائمةُ أدوارٍ صلبةٌ ترد 403. (الثلاثةُ قائمةٌ فعلًا في النظام.)
    $src = (string) file_get_contents($path);
    $hasGate = (strpos($src, 'ems_require_governance_screen') !== false)
        || (strpos($src, 'can_view') !== false
            && (strpos($src, 'get_current_page_permissions') !== false
                || strpos($src, 'check_page_permissions') !== false))
        || (strpos($src, '403') !== false && preg_match('/in_array\(\s*\$role/', $src) === 1);
    if (!$hasGate) {
        $violations['noguard'][] = $code . ' لا تستدعي بوابةَ صلاحيةٍ صريحةً قبل معالجاتها';
    }
}

$labels = array(
    'gov'     => '① AC-GOV-01 · شاشةُ حوكمةٍ بيد غير مالكها',
    'write'   => '② AC-GOV-02 · كتابةٌ بلا باب',
    'unreg'   => '③ AC-GOV-03 · شاشةُ حوكمةٍ غيرُ مسجَّلة',
    'noguard' => '④ AC-GOV-04 · شاشةُ حوكمةٍ بلا بوابةٍ في الكود',
);
$total = 0;
$o('══ حزامُ الحوكمة SEC-GOV ══');
foreach ($labels as $k => $label) {
    $n = count($violations[$k]);
    $total += $n;
    $o(sprintf('  %-46s %s', $label, $n === 0 ? '✓ نظيف' : '✗ ' . $n . ' خرقًا'));
    if ($n && ($VERBOSE || $n <= 12)) {
        foreach ($violations[$k] as $line) { $o('       - ' . $line); }
    } elseif ($n) {
        $o('       (شغّل --verbose للتفصيل)');
    }
}
$o('');
if ($total === 0) {
    $o('النتيجة: 4/4 نظيف.');
    exit(0);
}
$o('النتيجة: ' . $total . ' خرقًا قائمًا.');
exit(1);
