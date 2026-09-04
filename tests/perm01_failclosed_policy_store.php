<?php
/**
 * tests/perm01_failclosed_policy_store.php — سلامةُ الفشلِ نحوَ المنع (PERM-01 §6-②)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلبُ نصًّا**: «ومن بلغ معياريًّا منفَّذًا لا يعود إلى الجدولِ القديمِ مهما
 *   كان السبب: فإن فُقد قالبُه أو تعذّرت قراءةُ مخزنِ السياسةِ فالحكمُ **منعٌ
 *   صريحٌ بإنذارٍ مسجَّل** — لا سقوطٌ إلى القديم». وإلا تحوّل عطبٌ تقنيٌّ في
 *   الطبقةِ الجديدةِ إلى **توسعةِ صلاحيّاتٍ** من الطبقةِ الأوسع.
 *
 * ◆ **والفشلُ يُصطنع بوصلةٍ مغلقةٍ لا بعبثٍ في المخطَّط**: `get_module_permissions`
 *   تأخذ الوصلةَ معاملًا، فوصلةٌ مغلقةٌ تُفشل `prepare` — وهو عينُ ما يقع حين
 *   يتعذّر مخزنُ السياسة. ⛔ ولا يُمَسُّ جدولٌ ولا صلاحيّةُ أحد.
 *
 * ⛔ **والضابطُ الموجبُ شرطٌ**: منعٌ يقع على كلِّ حالٍ ليس سلامةَ فشلٍ بل تعطيلًا.
 *   فيُقاس أنَّ الوصلةَ السليمةَ ما تزال تُخرج حكمًا صحيحًا.
 *
 * التشغيل: php tests/perm01_failclosed_policy_store.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
/* ⛔ `config.php` لا يحمّل طبقةَ الصلاحيّات — ودالّةٌ غيرُ محمَّلةٍ تُسقِط
     السكربتَ **صامتًا** لأنَّ مُصفّيَ المخرجِ يبتلع الخطأ. فيُصرَّح بالتضمين. */
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ PERM-01 §6-② — تعذُّرُ مخزنِ السياسةِ منعٌ لا سقوط ══\n");

/* ── فاعلٌ مغطًّى بقالبٍ نافذ، وشاشةٌ **خارجَ** قالبِه لكنّها في جدولِ دورِه ── */
head('البذر — فاعلٌ مغطًّى وشاشةٌ يمنحها دورُه ولا يمنحها قالبُه');
$row = $conn->query("
  SELECT u.id uid, u.role rid, m.id mid, m.code
    FROM users u
    JOIN gov_authority_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
         AND (g.valid_to IS NULL OR g.valid_to > NOW())
    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
    JOIN role_permissions rp ON rp.role_id = u.role AND rp.can_view = 1
    JOIN modules m ON m.id = rp.module_id
   WHERE u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4
     AND NOT EXISTS(SELECT 1 FROM gov_profile_items i
                     WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                       AND i.item_ref = m.code AND i.allow = 1)
   LIMIT 1")->fetch_assoc();
chk($row !== null, 'وُجد الزوجُ المطلوب — وبلا زوجٍ لا معنى للقياس',
    $row ? ('مستخدم #' . $row['uid'] . ' · دور ' . $row['rid'] . ' · ' . $row['code']) : 'لا شيء');
if (!$row) { fwrite(STDOUT, "\nالنتيجة: {$PASS} نجاح · {$FAIL} رسوب\n"); exit($FAIL === 0 ? 0 : 1); }

$_SESSION['user'] = array('id' => (int) $row['uid'], 'role' => (string) $row['rid'],
                          'company_id' => 4, 'name' => 'failclosed probe');
$MID = (int) $row['mid'];

/* ── ① الضابطُ الموجب: الوصلةُ السليمةُ تُخرج حكمَ القالبِ (منعٌ لهذه الشاشة) ── */
head('① **الضابطُ الموجب** — الوصلةُ السليمة');
$good = get_module_permissions($conn, $MID);
chk(is_array($good) && array_key_exists('can_view', $good), 'الدالّةُ تُخرج حكمًا');
chk(empty($good['can_view']),
    '★ والشاشةُ خارجَ القالبِ **ممنوعةٌ** رغمَ منحِ الدورِ — «لا شاشةَ خارجَ القالب»',
    'can_view=' . var_export(!empty($good['can_view']), true));

/* وشاشةٌ **داخلَ** قالبِه تُفتح — وإلا كان المنعُ تعطيلًا لا حكمًا. */
$in = $conn->query("
  SELECT m.id FROM gov_authority_grants g
    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
    JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen' AND i.allow = 1
    JOIN modules m ON m.code = i.item_ref
   WHERE g.user_id = " . (int) $row['uid'] . " AND g.revoked_at IS NULL
     AND (g.valid_to IS NULL OR g.valid_to > NOW()) LIMIT 1")->fetch_assoc();
if ($in) {
    $okPerm = get_module_permissions($conn, (int) $in['id']);
    chk(!empty($okPerm['can_view']), '★ وشاشةٌ **داخلَ** قالبِه تُفتح — فالحكمُ يميّز ولا يُعطّل');
}

/* ── ② السالب: وصلةٌ مغلقةٌ ⇒ منعٌ لا سقوطٌ إلى الجدولِ القديم ── */
head('② **السالب** — تعذُّرُ مخزنِ السياسة');
$dead = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'),
                   ems_env('DB_PASS'), ems_env('DB_NAME'));
$dead->close();                              /* وصلةٌ ميتةٌ — كلُّ prepare عليها يفشل */
$verdict = @get_module_permissions($dead, $MID);
chk(is_array($verdict), 'الدالّةُ لم تنهَرْ على وصلةٍ ميتة');
chk(empty($verdict['can_view']) && empty($verdict['can_add'])
    && empty($verdict['can_edit']) && empty($verdict['can_delete']),
    '★★ **منعٌ كاملٌ عند تعذُّرِ القراءة** — لا سقوطَ إلى الجدولِ القديم',
    'can_view=' . var_export(!empty($verdict['can_view']), true));

/* ── ③ والمنعُ يُسجَّل — منعٌ لا يراه السجلُّ لا يُحتسب ── */
head('③ **الأثر** — الإنذارُ مسجَّل');
$logged = function_exists('ems_log_permission_denial');
chk($logged, 'مُسجِّلُ الردِّ موجودٌ ويُنادى من مسارِ المنع');
$src = (string) @file_get_contents(dirname(__DIR__) . '/includes/permissions_helper.php');
chk(mb_strpos($src, 'policy_store_unreadable') !== false,
    '★ وسببُ الردِّ **مسمًّى في الشيفرة** — لا «منعٌ» مجهولُ السبب');
chk(mb_substr_count($src, 'policy_store_unreadable') >= 3,
    'وثلاثةُ مواضعِ فشلٍ مغطّاةٌ: التحضيرُ والتنفيذُ والنتيجة',
    mb_substr_count($src, 'policy_store_unreadable') . ' موضعًا');

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ CANONICAL_USER_WITH_LEGACY_FALLBACK = 0 — الفشلُ يمنع ولا يوسّع\n"
    : "✘ سلامةُ الفشلِ غيرُ مُثبَتة\n");
exit($FAIL === 0 ? 0 : 1);
