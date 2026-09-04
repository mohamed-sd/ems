<?php
/**
 * tools/injint01/gap11_shadow.php — ظلُّ GAP-11: ما الذي سيتغيّر لو أُغلقت؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قراءةٌ محضةٌ لا تغيّر تجربةَ مستخدمٍ واحد** (‏§42: OBSERVE → SHADOW …).
 *   تُصيَّر السلطتانِ جنبًا إلى جنبٍ لكلِّ دورٍ ويُطبع الفرقُ بندًا بندًا.
 *
 * ◆ **السلطتانِ المتفرِّقتان**:
 *   ① **سلطةُ السايدبار** — `navarch_authorized_routes()` تسأل `role_permissions`
 *      **خامًّا** عبر `perm_nav_view_exists_sql()`.
 *   ② **سلطةُ الحارس** — `get_module_permissions()` تُطبِّق فوقَها **طبقةَ
 *      القوالب** (`gov_authority_grants`): «المغطّى بقالبٍ نافذٍ يُحكَم بقالبِه
 *      حصرًا — لا شاشةَ خارجَ القالب».
 *   ⇐ فالفرقُ بينهما **هو** GAP-11، وهو ما ينكشف هنا بالعدد.
 *
 * ⛔ **ولا يُقاس هذا بالدورِ وحدَه**: طبقةُ القوالبِ تُربَط بالمستخدمِ لا بالدور،
 *   فدورانِ متطابقانِ في `role_permissions` يفترقان لاختلافِ قالبِ مستخدمَيهما.
 *   ⇐ فيُنصَّب **مستخدمٌ حقيقيٌّ لكلِّ دورٍ** وتُستدعى الدالّةُ نفسُها التي
 *      تستدعيها الشاشة — لا محاكاةٌ لمنطقِها.
 *
 * ⛔ **والفرقُ اتّجاهان لا اتّجاهٌ واحد**:
 *   · `WOULD_DISAPPEAR` — ظاهرٌ الآنَ في القائمةِ ويمنعه الحارس. **هذا هو
 *     الخطر**: الموظّفُ يفقد رابطًا يستعمله اليوم.
 *   · `WOULD_APPEAR` — يسمح به الحارسُ ولا يظهر. نقصُ ملاحةٍ لا خطرَ فيه.
 *
 * التشغيل: php tools/injint01/gap11_shadow.php [--role=N] [--verbose]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$ONLY = 0; $VERBOSE = false;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--role=(\d+)$/', $a, $m)) { $ONLY = (int) $m[1]; }
    if ($a === '--verbose') { $VERBOSE = true; }
}

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($conn->connect_errno) { exit('تعذّر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;
$_SESSION = array();
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/navarch_renderer.php';

$rows = function ($q) use ($conn) { $r = $conn->query($q); $o = array(); if (!$r) { echo '  SQL: ' . $conn->error . "\n"; return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

echo "══ ظلُّ GAP-11 — قراءةٌ محضة ══\n";
echo "  السلطةُ ①: navarch_authorized_routes()  ⇐ role_permissions خامًّا\n";
echo "  السلطةُ ②: get_module_permissions()     ⇐ + طبقةُ gov_authority_grants\n\n";

/* ═══ ① لكلِّ دورٍ مستخدمٌ حقيقيٌّ — فالقالبُ يُربَط بالمستخدمِ لا بالدور ═════ */
$roles = $rows("SELECT r.id, r.name AS role_name,
                       (SELECT u.id FROM users u
                         WHERE (u.role = r.id OR u.role_id = r.id) AND u.status = 'active'
                         ORDER BY u.id LIMIT 1) uid
                  FROM roles r
                 WHERE EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = r.id AND n.active = 1)
                 ORDER BY r.id");

$grand = array('roles' => 0, 'nav' => 0, 'disappear' => 0, 'appear' => 0, 'norole' => 0, 'covered' => 0);
$detail = array();

foreach ($roles as $r) {
    $rid = (int) $r['id'];
    if ($ONLY && $rid !== $ONLY) { continue; }
    if (!$r['uid']) { $grand['norole']++; continue; }

    /* ── نُصِّبُ جلسةَ المستخدمِ كما تفعل الشاشةُ حرفًا ── */
    $_SESSION = array('user' => array('id' => (int) $r['uid'], 'role' => $rid));

    /* أَمغطًّى بقالبٍ نافذ؟ — الفرقُ لا يقع إلا للمغطَّى */
    /* ⛔ **ووجودُ صفِّ منحٍ ليس تغطية**: الملغى والمنتهي لا يحكمان. */
    $covRow = $rows("SELECT COUNT(*) n FROM gov_authority_grants g
                      WHERE g.user_id = " . (int) $r['uid'] . "
                        AND g.revoked_at IS NULL
                        AND (g.valid_from IS NULL OR g.valid_from <= NOW())
                        AND (g.valid_to   IS NULL OR g.valid_to   >= NOW())");
    $covered = $covRow && (int) $covRow[0]['n'] > 0;
    if ($covered) { $grand['covered']++; }

    /* ── السلطةُ ①: ما يُصرِّح به السايدبارُ اليومَ ── */
    $navAuth = navarch_authorized_routes($conn, $rid);

    /* ── السلطةُ ②: حكمُ الحارسِ المركزيِّ على كلِّ مسارٍ من مساراتِ الدور ── */
    $items = $rows("SELECT n.route, n.module_id FROM nav_items n
                     WHERE n.role_id = $rid AND n.active = 1");
    $dis = array(); $app = array();
    foreach ($items as $it) {
        $norm = navarch_norm_route($it['route']);
        $inNav = isset($navAuth[$norm]);
        $mid = (int) $it['module_id'];
        if ($mid <= 0) { continue; }             /* بلا وحدةٍ فحارسُه في وجهتِه */
        $perm = get_module_permissions($conn, $mid);
        $inGuard = !empty($perm['can_view']);
        if ($inNav && !$inGuard) { $dis[] = $it['route']; }
        if (!$inNav && $inGuard) { $app[] = $it['route']; }
    }
    $grand['roles']++; $grand['nav'] += count($items);
    $grand['disappear'] += count($dis); $grand['appear'] += count($app);
    $detail[] = array('id' => $rid, 'name' => $r['role_name'], 'uid' => (int) $r['uid'],
        'covered' => $covered, 'items' => count($items), 'dis' => $dis, 'app' => $app);
}
$_SESSION = array();

/* ═══ ② العرض ══════════════════════════════════════════════════════════ */
printf("%-5s %-30s %-7s %-6s %-7s %-7s\n", 'دور', 'الاسم', 'مغطًّى', 'بنود', 'يختفي', 'يظهر');
echo str_repeat('─', 74) . "\n";
foreach ($detail as $d) {
    if (!$d['dis'] && !$d['app'] && !$VERBOSE) { continue; }
    printf("%-5s %-30s %-7s %-6s %-7s %-7s\n", $d['id'], mb_substr($d['name'], 0, 28),
        $d['covered'] ? 'نعم' : 'لا', $d['items'], count($d['dis']), count($d['app']));
    foreach (array_slice($d['dis'], 0, 6) as $x) { echo "        ⛔ يختفي: $x\n"; }
    if (count($d['dis']) > 6) { printf("        … و%d رابطًا آخر\n", count($d['dis']) - 6); }
    foreach (array_slice($d['app'], 0, 4) as $x) { echo "        ＋ يظهر : $x\n"; }
    if (count($d['app']) > 4) { printf("        … و%d رابطًا آخر\n", count($d['app']) - 4); }
}

echo "\n══ الحصيلة ══\n";
printf("  أدوارٌ مقيسة              : %d\n", $grand['roles']);
printf("  منها مغطًّى بقالبٍ نافذ    : %d\n", $grand['covered']);
printf("  أدوارٌ بلا حسابٍ حيٍّ (تُخطَّى): %d\n", $grand['norole']);
printf("  بنودُ ملاحةٍ مفحوصة        : %d\n", $grand['nav']);
printf("\n  WOULD_DISAPPEAR = %d   ⇐ الخطر: روابطُ عملٍ ظاهرةٌ اليومَ يمنعها الحارس\n", $grand['disappear']);
printf("  WOULD_APPEAR    = %d   ⇐ نقصُ ملاحةٍ لا خطرَ فيه\n", $grand['appear']);
echo "\n";
/* ⛔ **وصفرُ فرقٍ على صفرِ مقيسٍ ليس تطابقًا بل عمًى**: لو سقط الاستعلامُ أو لم
   يُقَس دورٌ واحد، خرج «متطابقان» أخضرَ كاذبًا. فالمقامُ يُفحَص قبلَ البسط. */
if ($grand['roles'] === 0 || $grand['nav'] === 0) {
    echo "⛔ SHADOW_DENOMINATOR = 0 — لم يُقَس شيءٌ، فلا حكمَ. افحص الاستعلامَ لا النتيجة.\n";
    exit(2);
}
if ($grand['disappear'] === 0 && $grand['appear'] === 0) {
    echo "✔ السلطتانِ متطابقتانِ على كلِّ ما قِيس — إغلاقُ GAP-11 لا يغيّر ما يراه أحد.\n";
} else {
    echo "⚠ السلطتانِ تفترقان — ولا يُقلَب شيءٌ قبل حكمِ المالكِ على القائمةِ أعلاه.\n";
}
echo "⛔ ولم يُكتب سطرٌ ولم تُمَسَّ جلسةُ مستخدمٍ — ظلٌّ لا قلب.\n";
