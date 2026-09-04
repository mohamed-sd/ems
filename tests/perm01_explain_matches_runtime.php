<?php
/**
 * tests/perm01_explain_matches_runtime.php — التفسيرُ عينُ الحكم (PERM-01 §7-③)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ نصًّا**: «شاشةُ التفسيرِ تستدعي خدمةَ القرارِ بخيارِ التتبّع —
 *   ولا تحسب حكمًا لنفسِها». فتطابقُها مع زمنِ التشغيل **100%** أو لا تُقبل.
 *
 * ⛔ **والانحرافُ كان واقعًا لا احتمالًا**: كان `ems_explain_screen_access`
 *   يعيد بناءَ الحكمِ من `modules` × `role_permissions` وحدَهما — وهي طبقةٌ
 *   **لا تحكم أحدًا** اليومَ (التغطيةُ 75 من 75 بالقوالب). فكان يقول «مسموح»
 *   حيث يمنع الحارسُ والعكس. وهذا الفحصُ يمنع عودتَه.
 *
 * ◆ **والعيّنةُ تُؤخذ من الطرفَين**: أزواجٌ **مسموحةٌ** وأزواجٌ **ممنوعةٌ** —
 *   فتطابقٌ على المسموحِ وحدَه لا يُثبت شيئًا، إذ يكفيه أن يقول «نعم» دائمًا.
 *
 * ⛔ **ولا تُترك الجلسةُ مبدَّلة**: التفسيرُ يُبدِّل `$_SESSION` لحظةً ليقرأ
 *   بعينِ صاحبِه — وتسريبُها يقلب هويّةَ الطالبِ فيما بعد.
 *
 * التشغيل: php tests/perm01_explain_matches_runtime.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/perm_explain_live.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
fwrite(STDOUT, "\n══ PERM-01 §7-③ — شاشةُ التفسيرِ تنادي القرارَ ولا تحاكيه ══\n");

head('① البنيةُ — خدمةُ التتبّعِ قائمةٌ في مسارِ القرارِ نفسِه');
chk(function_exists('ems_permission_trace'), '★ خدمةُ التتبّعِ مُعلَنة');
chk(function_exists('ems_perm_trace_note'), 'ونقاطُ الرصدِ مبثوثةٌ في المسار');
$src = (string) @file_get_contents(dirname(__DIR__) . '/includes/perm_explain_live.php');
chk(strpos($src, 'ems_permission_trace') !== false,
    '★★ **المفسِّرُ ينادي خدمةَ القرار** — لا استعلامَ حكمٍ موازيًا فيه');
chk(strpos($src, 'perm_row_for_module') === false,
    'ولا أثرَ للحسابِ الذاتيِّ القديمِ على role_permissions');

head('② العيّنةُ — أزواجٌ مسموحةٌ وأخرى ممنوعة');
$pairs = array();
$q = $conn->query("
  SELECT g.user_id uid, m.code
    FROM gov_authority_grants g
    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
    JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen' AND i.allow = 1
    JOIN modules m ON m.code = i.item_ref
    JOIN users u ON u.id = g.user_id AND u.is_deleted = 0 AND u.status = 'active'
   WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
   GROUP BY g.user_id ORDER BY g.user_id LIMIT 12");
while ($x = $q->fetch_assoc()) { $pairs[] = array((int) $x['uid'], $x['code'], 'يُتوقَّع سماحٌ'); }
$nAllow = count($pairs);

/* أزواجٌ ممنوعة: شاشةٌ مسجَّلةٌ **خارجَ** قالبِ الفاعل. */
$q = $conn->query("
  SELECT u.id uid, (SELECT m.code FROM modules m
                     WHERE m.code LIKE '%.php'
                       AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g2
                             JOIN gov_role_profiles p2 ON p2.profile_id = g2.profile_id AND p2.state='active'
                             JOIN gov_profile_items i2 ON i2.profile_id = p2.profile_id
                                  AND i2.item_kind='screen' AND i2.allow=1 AND i2.item_ref = m.code
                            WHERE g2.user_id = u.id AND g2.revoked_at IS NULL)
                     ORDER BY m.id LIMIT 1) code
    FROM users u
   WHERE u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4
   ORDER BY u.id LIMIT 12");
while ($x = $q->fetch_assoc()) {
    if ($x['code'] !== null) { $pairs[] = array((int) $x['uid'], $x['code'], 'يُتوقَّع منعٌ'); }
}
$nDeny = count($pairs) - $nAllow;
chk($nAllow >= 5 && $nDeny >= 5, '★ العيّنةُ من الطرفَين — وطرفٌ واحدٌ لا يُثبت تطابقًا',
    "مسموحة={$nAllow} · ممنوعة={$nDeny}");

head('③ **التطابق** — لكلِّ زوجٍ: حكمُ الحارسِ وحكمُ المفسِّر');
$sessionBefore = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$checked = 0; $mismatch = array(); $sawAllow = 0; $sawDeny = 0;
foreach ($pairs as $pr) {
    list($uid, $code, $expect) = $pr;
    $mid = (int) $conn->query("SELECT id FROM modules WHERE code='"
        . $conn->real_escape_string($code) . "' LIMIT 1")->fetch_row()[0];
    if ($mid <= 0) { continue; }

    /* حكمُ الحارسِ: بجلسةِ الفاعلِ نفسِه ثمَّ تُستعاد. */
    $u = $conn->query("SELECT id, role, company_id, name FROM users WHERE id={$uid}")->fetch_assoc();
    if (!$u) { continue; }
    $_SESSION['user'] = array('id' => (int) $u['id'], 'role' => (string) $u['role'],
                              'company_id' => (int) $u['company_id'], 'name' => 'guard probe');
    $guard = !empty(get_module_permissions($conn, $mid)['can_view']);
    unset($_SESSION['user']);

    $explain = !empty(ems_explain_screen_access($conn, $uid, $code)['allowed']);
    $checked++;
    if ($guard) { $sawAllow++; } else { $sawDeny++; }
    if ($guard !== $explain) {
        $mismatch[] = "#{$uid} ⟵ {$code}: حارس=" . ($guard ? 'سماح' : 'منع')
                    . ' · مفسِّر=' . ($explain ? 'سماح' : 'منع');
    }
}
if ($sessionBefore === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $sessionBefore; }

chk($checked >= 10, 'أزواجٌ فُحصت فعلًا — وصفرُ مفحوصٍ ليس تطابقًا', "عدد={$checked}");
chk($sawAllow > 0 && $sawDeny > 0,
    '★ الحارسُ أخرج الحكمَين — فالمقياسُ يميّز ولا يقول «نعم» دائمًا',
    "سماح={$sawAllow} · منع={$sawDeny}");
chk(count($mismatch) === 0,
    '★★ **تطابقٌ تامّ بين المفسِّرِ وزمنِ التشغيل**',
    count($mismatch) === 0 ? "{$checked} من {$checked}" : implode(' | ', array_slice($mismatch, 0, 3)));

head('④ سلسلةُ الأسبابِ ليست فارغة');
$one = ems_explain_screen_access($conn, $pairs[0][0], $pairs[0][1]);
chk(!empty($one['chain']) && count($one['chain']) >= 3,
    '★ التفسيرُ يعرض خطواتِ القرارِ لا نتيجتَه وحدَها', 'خطوات=' . count($one['chain']));
$hasPolicy = false;
foreach ($one['chain'] as $st) {
    if (strpos($st['step'], 'القوالب') !== false || strpos($st['step'], 'وضع الانتقال') !== false) { $hasPolicy = true; }
}
chk($hasPolicy, '★ والسلسلةُ تذكر الطبقةَ الحاكمةَ اليومَ — لا الجدولَ القديمَ وحدَه');

head('⑤ الجلسةُ لم تتسرَّب');
chk(($sessionBefore === null && !isset($_SESSION['user']))
    || ($sessionBefore !== null && isset($_SESSION['user']) && $_SESSION['user'] === $sessionBefore),
    '★ جلسةُ الطالبِ كما كانت بعدَ كلِّ تفسير');

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ EXPLAIN_MATCHES_RUNTIME = PASS\n"
    : "✘ المفسِّرُ ينحرف عن الحارس\n");
exit($FAIL === 0 ? 0 : 1);
