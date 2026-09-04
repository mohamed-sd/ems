<?php
/**
 * tests/perm01_render_vs_guard.php — الرابطُ المُصيَّرُ وحكمُ الحارس (PERM-01 §7-④)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ نصًّا**: «فرقٌ بين الرابطِ المباشرِ وحكمِ الحارس = صفر». ومعناه:
 *   ما يراه المستخدمُ في قائمتِه يفتحه — ولا رابطَ يقود إلى 403.
 *
 * ⛔ **والقياسُ بالتصييرِ الحيِّ لا بجداولِه**: القائمةُ تُبنى بـ`navarch_render`
 *   (المُصيِّرُ الحاكم) لا بقراءةِ `nav_items` مباشرةً — فصفٌّ قائمٌ في الجدولِ
 *   قد لا يُصيَّر، وحكمٌ على غيرِ المُصيَّرِ حكمٌ على غيرِ محلِّه.
 *
 * ⛔ **وصفرٌ بلا ضابطٍ سالبٍ أخضرُ كاذب**: مسبارٌ لا يرى شيئًا يُخرج صفرًا
 *   كمسبارٍ لا عطبَ أمامه. فيُكسَر الحالُ عمدًا — يُطفأ بندُ قالبٍ لرابطٍ
 *   مُصيَّر — ويُتحقَّق أنَّ العدَّ **تحرَّك**، ثمَّ يُستعاد.
 *
 * ⛔ **والاستعادةُ في `register_shutdown_function`**: تُسجَّل قبلَ التغييرِ فلا
 *   تُعلَّق على نتيجةِ الفحصِ ولا على اكتمالِ السكربت.
 *
 * التشغيل: php tests/perm01_render_vs_guard.php
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
fwrite(STDOUT, "\n══ PERM-01 §7-④ — لا رابطَ يُصيَّر ثمَّ يردُّه بابُه ══\n");

/** خريطةُ المسارِ المُطبَّعِ ⇐ معرِّفِ الوحدة. */
$modByRoute = array();
$modCodeById = array();
$r = $conn->query("SELECT id, code FROM modules WHERE code LIKE '%.php'");
while ($x = $r->fetch_row()) {
    $modByRoute[strtolower(navarch_norm_route($x[1]))] = (int) $x[0];
    $modCodeById[(int) $x[0]] = (string) $x[1];
}

/**
 * يمسح المستخدمين ويعيد [عددُ المفحوص, عددُ المردود, أمثلة, غيرُ المحلول].
 */
$sweep = function ($conn, $modByRoute, $onlyUser = 0) {
    $users = array();
    $w = $onlyUser > 0 ? " AND id = " . (int) $onlyUser : '';
    $r = $conn->query("SELECT id, role, company_id FROM users
                        WHERE is_deleted=0 AND status='active' AND company_id=4{$w} ORDER BY id");
    while ($x = $r->fetch_assoc()) { $users[] = $x; }
    $checked = 0; $denied = 0; $unres = 0; $ex = array();
    $prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    foreach ($users as $u) {
        $_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                                  'company_id' => (int) $u['company_id'], 'name' => 'render probe');
        $ws = navarch_role_workspace($conn, (int) $u['role']);
        $tree = navarch_render($conn, $ws, (int) $u['role'], array('include_shell' => false));
        foreach ((isset($tree['groups']) ? $tree['groups'] : array()) as $g) {
            foreach ((isset($g['items']) ? $g['items'] : array()) as $it) {
                $k = strtolower(navarch_norm_route($it['route']));
                if (!isset($modByRoute[$k])) { $unres++; continue; }
                $checked++;
                if (empty(get_module_permissions($conn, $modByRoute[$k])['can_view'])) {
                    $denied++;
                    if (count($ex) < 5) { $ex[] = '#' . $u['id'] . ' ⟵ ' . $it['route']; }
                }
            }
        }
    }
    if ($prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev; }
    return array($checked, $denied, $ex, $unres, count($users));
};

head('① المسحُ الحيُّ — كلُّ مستخدمٍ حيٍّ وكلُّ رابطٍ يُصيَّر له');
list($checked, $denied, $ex, $unres, $nUsers) = $sweep($conn, $modByRoute, 0);
chk($nUsers >= 50, 'مستخدمون أحياءُ مُسحوا — وصفرُ ممسوحٍ ليس نتيجة', "عدد={$nUsers}");
chk($checked >= 500, 'روابطُ مُصيَّرةٌ فُحصت فعلًا', "عدد={$checked}");
chk($unres === 0, 'ولا مسارَ مُصيَّرٍ يعجز عن الحلِّ إلى وحدة', "غيرُ محلول={$unres}");
chk($denied === 0, '★★ **لا رابطَ يُصيَّر ثمَّ يردُّه بابُه**',
    $denied === 0 ? "صفرٌ من {$checked}" : implode(' | ', $ex));

head('② **الضابطُ السالب** — أيرى المسبارُ عطبًا لو وقع؟');
/* ⛔ **والضحيّةُ تُنتزَع من المُصيَّرِ نفسِه لا من `nav_items`**: صفٌّ في الجدولِ
     قد لا يُصيَّر — فكسرُه لا يُحرِّك المسبارَ ويُقرأ «المسبارُ أعمى» وهو سليم.
     (وقع مقيسًا: أُطفئ بندٌ مأخوذٌ من الجدولِ فبقي العدُّ صفرًا لأنَّ رابطَه
     أصلًا خارجَ الشجرةِ المُصيَّرة.) فتُقرأ الشجرةُ أوّلًا ثمَّ يُختار منها. */
$victim = null;
$__prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$__ur = $conn->query("SELECT id, role, company_id FROM users
                       WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id");
while ($victim === null && ($__u = $__ur->fetch_assoc())) {
    $_SESSION['user'] = array('id' => (int) $__u['id'], 'role' => (string) $__u['role'],
                              'company_id' => (int) $__u['company_id'], 'name' => 'victim pick');
    $__ws = navarch_role_workspace($conn, (int) $__u['role']);
    $__tree = navarch_render($conn, $__ws, (int) $__u['role'], array('include_shell' => false));
    $__routes = array();
    foreach ((isset($__tree['groups']) ? $__tree['groups'] : array()) as $__g) {
        foreach ((isset($__g['items']) ? $__g['items'] : array()) as $__it) {
            /* ◆ **المطابقةُ برمزِ الوحدةِ لا بنصِّ المسار**: `item_ref` رمزٌ في
                 `modules.code`، والمسارُ المُصيَّرُ قد يحمل بادئةً أو معاملًا —
                 فمطابقةُ النصِّ بالنصِّ لا تجد شيئًا وتُقرأ «لا ضحيّة». */
            $__k = strtolower(navarch_norm_route($__it['route']));
            if (isset($modByRoute[$__k])) { $__routes[] = $modCodeById[$modByRoute[$__k]]; }
        }
    }
    if (!$__routes) { continue; }
    $__in = array();
    foreach ($__routes as $__rt) { $__in[] = "'" . $conn->real_escape_string($__rt) . "'"; }
    $__q = $conn->query("
      SELECT i.item_id, i.item_ref
        FROM gov_authority_grants g
        JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
        JOIN gov_profile_items i ON i.profile_id = p.profile_id
             AND i.item_kind = 'screen' AND i.allow = 1
       WHERE g.user_id = " . (int) $__u['id'] . " AND g.revoked_at IS NULL
         AND (g.valid_to IS NULL OR g.valid_to > NOW())
         AND i.item_ref IN (" . implode(',', $__in) . ") LIMIT 1");
    if ($__q && ($__x = $__q->fetch_assoc())) {
        $victim = array('uid' => (int) $__u['id'], 'item_id' => (int) $__x['item_id'],
                        'item_ref' => $__x['item_ref'], 'role' => $__u['role']);
    }
}
if ($__prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $__prev; }
chk($victim !== null, 'وُجد زوجٌ مُصيَّرٌ يصلح للكسرِ المتعمَّد',
    $victim ? ('مستخدم #' . $victim['uid'] . ' · ' . $victim['item_ref']) : 'لا شيء');

if ($victim) {
    $ITEM = (int) $victim['item_id'];
    /* ⛔ الاستعادةُ تُسجَّل **قبلَ** التغيير. */
    register_shutdown_function(static function () use ($conn, $ITEM) {
        $conn->query("UPDATE gov_profile_items SET allow = 1 WHERE item_id = {$ITEM}");
    });

    $conn->query("UPDATE gov_profile_items SET allow = 0 WHERE item_id = {$ITEM}");
    $flipped = $conn->affected_rows;
    chk($flipped === 1, 'أُطفئ البندُ فعلًا — وبلا كسرٍ لا معنى للضابط', "صفوف={$flipped}");

    list($c2, $d2, $ex2, , ) = $sweep($conn, $modByRoute, (int) $victim['uid']);
    chk($d2 >= 1, '★★ **المسبارُ رصد العطبَ المصنوع** — فصفرُه أعلاه قياسٌ لا عمًى',
        "مردودٌ بعدَ الكسر={$d2} من {$c2}" . ($ex2 ? ' · ' . $ex2[0] : ''));

    $conn->query("UPDATE gov_profile_items SET allow = 1 WHERE item_id = {$ITEM}");
    list($c3, $d3, , , ) = $sweep($conn, $modByRoute, (int) $victim['uid']);
    chk($d3 === 0, '★ وبالاستعادةِ عاد الصفرُ — فالأثرُ من الكسرِ لا من المسبار',
        "مردودٌ بعدَ الاستعادة={$d3} من {$c3}");
}

head('③ الجلسةُ لم تتسرَّب');
chk(!isset($_SESSION['user']), '★ لا جلسةَ مسبارٍ باقيةٌ بعدَ المسح');

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ RENDER_VS_GUARD = PASS\n"
    : "✘ رابطٌ يُصيَّر ويردُّه بابُه\n");
exit($FAIL === 0 ? 0 : 1);
