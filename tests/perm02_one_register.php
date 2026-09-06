<?php
/**
 * tests/perm02_one_register.php — سجلٌّ واحدٌ يحكم الظهورَ والوصولَ والقراءةَ
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أعطابٍ بنيويّةٍ كُشفت بالدراسةِ وعُولجت، وهذا شاهدُها:
 *   ① **السوبر `-1`** كان يبلغ السقوطَ الأخيرَ في `get_module_permissions`
 *      فيُقرأ **منعًا كاملًا** — عيبٌ نائمٌ لأنّ صفرَ حسابٍ يحمله اليوم.
 *   ② **دوالُّ إرثيّةٌ** (`check_permission` · `get_user_permissions` وما يبني
 *      عليهما) كانت تقرأ جدولَ الدورِ القديمَ رأسًا — بصفرِ نداءٍ إنتاجيٍّ
 *      اليومَ، **وفخًّا** لمن يناديها غدًا.
 *   ③ **المُصيِّرُ الحاكم** كان يُصرِّح بالرابطِ من الجدولِ القديمِ وحدَه بينما
 *      يحكم الوصولَ قالبُ المستخدم — سجلّانِ يقرِّران أمرًا واحدًا.
 *
 * ⛔ **والقياسُ بالمقارنةِ بمسارِ القرارِ نفسِه** لا بجدولٍ: كلُّ تأكيدٍ هنا
 *   يقابل جوابَ `get_module_permissions` حرفًا — فمن خالفه فهو مسارٌ ثانٍ.
 *
 * التشغيل: php tests/perm02_one_register.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/navarch_renderer.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

/* الحالُ يُستعاد حتمًا — المسبارُ يبدّل الجلسةَ ليقيس بهويّاتٍ عدّة. */
$prevSession = isset($_SESSION['user']) ? $_SESSION['user'] : null;
register_shutdown_function(function () use ($prevSession) {
    if ($prevSession === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prevSession; }
});

fwrite(STDOUT, "\n══ PERM-02 — سجلٌّ واحدٌ لا سجلّان ══\n");

/* ═══ ① السوبر ═══════════════════════════════════════════════════════════ */
head('① السوبر مصرَّحٌ به في مصدرِ القرارِ الواحد');
$anyModule = (int) $conn->query("SELECT id FROM modules ORDER BY id LIMIT 1")->fetch_row()[0];
$_SESSION['user'] = array('id' => 999999, 'role' => '-1', 'company_id' => 4, 'name' => 'super probe');
$p = get_module_permissions($conn, $anyModule);
chk(!empty($p['can_view']) && !empty($p['can_add']) && !empty($p['can_edit']) && !empty($p['can_delete']),
    '★ الدور -1 يفتح الشاشةَ بالأعلامِ الأربعة');
chk(count(get_user_permissions($conn)) === 0,
    'وخريطةُ الصلاحيّاتِ فارغةٌ له عمدًا', 'حكمُه في مصدرِ القرارِ لا في خريطةٍ موازية');
$live = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role='-1'")->fetch_row()[0];
chk($live === 0, 'ولا حسابَ حيًّا يحمله اليومَ فالعيبُ كان نائمًا', $live . ' حسابًا');

/* ═══ ② لا مسارَ قرارٍ ثانٍ في الدوالِّ الإرثيّة ═══════════════════════════ */
head('② الدوالُّ الإرثيّةُ تفوّض ولا تحكم');
$u = $conn->query("SELECT id, role FROM users
                    WHERE is_deleted=0 AND status='active' AND company_id=4
                      AND EXISTS (SELECT 1 FROM gov_authority_grants g
                                   WHERE g.user_id = users.id AND g.revoked_at IS NULL)
                    ORDER BY id LIMIT 1")->fetch_assoc();
chk($u !== null, 'وُجد مستخدمٌ مغطًّى بقالبٍ نافذٍ للقياس');
$_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                          'company_id' => 4, 'name' => 'legacy probe');

$mods = array();
$q = $conn->query("SELECT id FROM modules ORDER BY id LIMIT 60");
while ($q && ($x = $q->fetch_row())) { $mods[] = (int) $x[0]; }

$diff = 0; $checked = 0;
foreach ($mods as $mid) {
    $truth = get_module_permissions($conn, $mid);
    foreach (array('view', 'add', 'edit', 'delete') as $verb) {
        $checked++;
        if (check_permission($conn, $mid, $verb) !== !empty($truth['can_' . $verb])) { $diff++; }
    }
}
chk($diff === 0, '★ `check_permission` يطابق مسارَ القرارِ حرفًا',
    $diff . ' فرقًا من ' . $checked . ' زوجًا');

$map = get_user_permissions($conn);
$mapDiff = 0; $mapChecked = 0;
foreach ($mods as $mid) {
    $truth = get_module_permissions($conn, $mid);
    $seen = isset($map[$mid]) ? !empty($map[$mid]['can_view']) : false;
    $mapChecked++;
    if ($seen !== !empty($truth['can_view'])) { $mapDiff++; }
}
chk($mapDiff === 0, '★ خريطةُ `get_user_permissions` تطابق الحارسَ',
    $mapDiff . ' فرقًا من ' . $mapChecked . ' شاشةً');

/* ضابطٌ سالبٌ: شاشةٌ خارجَ القالبِ لا تُفتح بأيٍّ من الطريقين. */
$outside = null;
foreach ($mods as $mid) {
    $t = get_module_permissions($conn, $mid);
    if (empty($t['can_view'])) { $outside = $mid; break; }
}
if ($outside !== null) {
    chk(!check_permission($conn, $outside, 'view') && empty($map[$outside]['can_view']),
        '⛔ سالب: شاشةٌ يمنعها الحارسُ تُمنع في الطريقَين', 'الوحدة #' . $outside);
} else {
    chk(true, 'لا شاشةَ ممنوعةً في العيّنة فالضابطُ السالبُ غيرُ منطبقٍ هنا');
}

/* ═══ ③ الظهورُ لا يفترق عن الوصول ═══════════════════════════════════════ */
head('③ المُصيِّرُ الحاكمُ يُقاطَع بالطبقةِ الحاكمة');
$live = array();
$q = $conn->query("SELECT u.id, u.role FROM users u
                    WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4
                      AND EXISTS (SELECT 1 FROM gov_authority_grants g
                                   WHERE g.user_id=u.id AND g.revoked_at IS NULL)
                    ORDER BY u.id LIMIT 25");
while ($q && ($x = $q->fetch_assoc())) { $live[] = $x; }
chk(count($live) > 0, 'وُجد مستخدمون أحياءُ للقياس', count($live) . ' مستخدمًا');

$shown = 0; $denied = 0;
foreach ($live as $lu) {
    $_SESSION['user'] = array('id' => (int) $lu['id'], 'role' => (string) $lu['role'],
                              'company_id' => 4, 'name' => 'nav probe');
    $ws = function_exists('navarch_role_workspace') ? navarch_role_workspace($conn, (int) $lu['role']) : null;
    if (!$ws) { continue; }
    $routes = navarch_authorized_routes($conn, (int) $lu['role']);
    foreach ($routes as $route => $_) {
        $st = $conn->prepare("SELECT m.id FROM nav_items n JOIN modules m ON m.id = n.module_id
                               WHERE n.role_id = ? AND n.active = 1
                                 AND LOWER(REPLACE(n.route,'.php','')) = ? LIMIT 1");
        $rid = (int) $lu['role'];
        $st->bind_param('is', $rid, $route);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        if (!$row) { continue; }
        $shown++;
        $t = get_module_permissions($conn, (int) $row[0]);
        if (empty($t['can_view'])) { $denied++; }
    }
}
chk($denied === 0, '★ صفرُ رابطٍ مُصرَّحٍ بتصييرِه يردُّه الحارس',
    $denied . ' من ' . $shown . ' رابطًا');

fwrite(STDOUT, "\n" . str_repeat('─', 70) . "\n");
fwrite(STDOUT, "   النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "   ✔ PERM02_ONE_REGISTER = PASS\n" : "   ✘ PERM02_ONE_REGISTER = FAIL\n");
exit($FAIL === 0 ? 0 : 1);
