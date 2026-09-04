<?php
/**
 * tests/perm01_single_decision_source.php — مصدرُ قرارٍ واحد (م-5 · ق-٥)
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC ق-٥: «الحذفُ = إزالةُ الفرعِ القديمِ من دالّةِ القرار. الجدولُ لا
 * يسقط. يبقى مقروءًا **أثرًا لا حكمًا**». و§4 يحرّم «سقوطًا إلى
 * `role_permissions` **لأيِّ مستخدمٍ جديدٍ أو قائم**».
 *
 * ◆ **وشاهدُ الـ75 نُفِّذ لحظةَ الحذفِ لا بعدَه**: التُقطت أحكامُ **58,748**
 *   زوجًا (مستخدمٍ × شاشة) قبلَ الحذف، وأُعيد القياسُ بعدَه — فتغيّر **213
 *   حكمًا كلُّها لمستخدمٍ واحدٍ مسمًّى** (#1 · الشركة 1 المعلَّقة · غيرُ مغطًّى)،
 *   و**صفرٌ للخمسةِ والسبعين** في الشركةِ النافذة.
 *
 * ◆ **وهذا الشاهدُ يحرس الخاصّةَ لا اللقطة**: لقطةٌ تُقارَن مرّةً لا تتكرّر،
 *   فيُقاس بدلَها **الثابتُ**: حكمُ كلِّ مغطًّى = ما يقوله قالبُه حرفًا، وغيرُ
 *   المغطَّى يُمنع. فأيُّ عودةٍ للفرعِ القديمِ تكسره فورًا.
 *
 * التشغيل: php tests/perm01_single_decision_source.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
fwrite(STDOUT, "\n══ م-5 — مصدرُ قرارِ الصلاحيةِ واحدٌ لا اثنان ══\n");

head('① البنيةُ — لا فرعَ قديمًا في دالّةِ القرار');
$src = (string) @file_get_contents(dirname(__DIR__) . '/includes/permissions_helper.php');
$pf = strpos($src, 'function get_module_permissions(');
$pe = $pf === false ? false : strpos($src, "\nfunction ", $pf + 10);
$body = $pf === false ? '' : substr($src, $pf, ($pe === false ? strlen($src) : $pe) - $pf);
chk($body !== '', 'جسمُ دالّةِ القرارِ مقروء', number_format(strlen($body)) . ' حرفًا');
chk($body !== '' && strpos($body, 'role_permissions') === false,
    '★★★ **لا ذكرَ لـrole_permissions في دالّةِ القرار** — انتهى النظامان');
chk(strpos($src, 'role_permissions') !== false,
    '★ والجدولُ ما يزال مقروءًا في الملفّ **أثرًا لا حكمًا** — لم يُسقَط');

head('② المخزنُ ما يزال قائمًا — الحذفُ من الدالّةِ لا من القاعدة');
$rows = (int) $conn->query("SELECT COUNT(*) FROM role_permissions")->fetch_row()[0];
chk($rows > 0, '★ صفوفُ الجدولِ القديمِ باقيةٌ للقراءةِ والمقارنة', "صفوف={$rows}");

head('③ **الثابت** — حكمُ كلِّ مغطًّى = ما يقوله قالبُه حرفًا');
$prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$live = array();
$r = $conn->query("SELECT id, role, company_id FROM users
                    WHERE is_deleted=0 AND status='active' AND company_id=4 ORDER BY id");
while ($x = $r->fetch_assoc()) { $live[] = $x; }
chk(count($live) >= 50, 'مستخدمون أحياءُ للقياس', 'عدد=' . count($live));

$checked = 0; $mismatch = array();
foreach ($live as $u) {
    /* ما يقوله القالبُ حرفًا لهذا الفاعل. */
    $want = array();
    $q = $conn->query("SELECT m.id mid, MAX(i.allow) v, MAX(i.can_add) a,
                              MAX(i.can_edit) e, MAX(i.can_delete) d
                         FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                         JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='screen'
                         JOIN modules m ON m.code=i.item_ref
                        WHERE g.user_id=" . (int) $u['id'] . " AND g.revoked_at IS NULL
                          AND (g.valid_to IS NULL OR g.valid_to > NOW())
                        GROUP BY m.id");
    while ($x = $q->fetch_assoc()) { $want[(int) $x['mid']] = $x; }
    if (!$want) { continue; }

    $_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                              'company_id' => (int) $u['company_id'], 'name' => 'single source probe');
    foreach ($want as $mid => $w) {
        $p = get_module_permissions($conn, $mid);
        $checked++;
        $expView = ((int) $w['v'] === 1);
        if (!empty($p['can_view']) !== $expView
            || (!empty($p['can_add'])    !== ($expView && (int) $w['a'] === 1))
            || (!empty($p['can_edit'])   !== ($expView && (int) $w['e'] === 1))
            || (!empty($p['can_delete']) !== ($expView && (int) $w['d'] === 1))) {
            if (count($mismatch) < 5) { $mismatch[] = '#' . $u['id'] . ' ⟵ وحدة ' . $mid; }
        }
    }
    unset($_SESSION['user']);
}
chk($checked >= 500, 'أزواجٌ فُحصت فعلًا', "عدد={$checked}");
chk(count($mismatch) === 0,
    '★★★ **حكمُ الحارسِ هو حكمُ القالبِ حرفًا** — لا مصدرَ ثانٍ يزيد أو ينقص',
    count($mismatch) === 0 ? "{$checked} من {$checked}" : implode(' · ', $mismatch));

head('④ **الضابطُ السالب** — غيرُ المغطَّى يُمنع ولا يسقط');
$un = $conn->query("SELECT u.id, u.role, u.company_id FROM users u
                     WHERE u.is_deleted=0 AND u.status='active'
                       AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                             JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                            WHERE g.user_id=u.id AND g.revoked_at IS NULL)
                     LIMIT 1")->fetch_assoc();
if ($un) {
    /* شاشةٌ يمنحها الجدولُ القديمُ لدورِه — فلو بقي السقوطُ لفُتحت. */
    $m = $conn->query("SELECT rp.module_id FROM role_permissions rp
                        WHERE rp.role_id = " . (int) $un['role'] . " AND rp.can_view = 1
                        LIMIT 1")->fetch_assoc();
    chk($m !== null, 'وُجدت شاشةٌ يمنحها الجدولُ القديمُ لدورِه — وبلا ذلك لا معنى للضابط',
        $m ? ('وحدة ' . $m['module_id']) : 'لا شيء');
    if ($m) {
        $_SESSION['user'] = array('id' => (int) $un['id'], 'role' => (string) $un['role'],
                                  'company_id' => (int) $un['company_id'], 'name' => 'uncovered probe');
        $p = get_module_permissions($conn, (int) $m['module_id']);
        unset($_SESSION['user']);
        chk(empty($p['can_view']),
            '★★★ **غيرُ المغطَّى يُمنع ولو منحه الجدولُ القديم** — لا سقوطَ لأحد',
            'مستخدم #' . $un['id'] . ' · can_view=' . var_export(!empty($p['can_view']), true));
    }
} else {
    ok('لا مستخدمَ غيرَ مغطًّى — والضابطُ لا محلَّ له');
}
if ($prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev; }

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "✔ SINGLE_DECISION_SOURCE = PASS\n" : "✘ ما يزال ثمّةَ مصدرٌ ثانٍ\n");
exit($FAIL === 0 ? 0 : 1);
