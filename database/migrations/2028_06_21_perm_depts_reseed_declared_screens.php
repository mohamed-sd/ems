<?php
/**
 * 2028_06_21 — استعادةُ شاشاتِ الإداراتِ المُسقَطةِ في تحوُّلِ القوالب (تعميمُ 06_20)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ نفسُه بعدَ المبيعات**: تحوُّلُ `PERM-01` بذر قوالبَ الأدوارِ ناقصةً،
 *   فكلُّ إدارةٍ تفقد شاشاتٍ **يمنحها سجلُّها الأصليُّ** ويعدّها **مانيفستُ
 *   إدارتِها** من نصيبها. والنمطُ الصارخُ أنَّ **جُلَّ الإداراتِ تفتقد شاشتَي
 *   الحوكمةِ والمخاطرِ الخاصّتَين بها** (`gov_dept_*` · `risk_dept_*`) — وهو
 *   إسقاطٌ منهجيٌّ في البذرِ لا قرارُ تضييق.
 *
 * ◆ **والمنهجُ مُصادَقٌ لا مُفترَض**: في المبيعات — وهي الإدارةُ الوحيدةُ التي
 *   لها خطُّ أساسٍ محفوظٌ (‏حقيقةٌ أرضيّة) — أعطى معيارُ
 *   **«في مانيفستِ الإدارة ∧ `role_permissions.can_view=1`»** المجموعةَ نفسَها
 *   حرفًا (27 = 27) التي أعطاها فرقُ خطِّ الأساس. فتطبيقُه على بقيّةِ
 *   الإداراتِ استنساخُ حكمٍ مُختبَرٍ لا اجتهادٌ جديد.
 *
 * ⛔ **وما نقصه دليلٌ لا يُبذَر**: شاشةٌ في المانيفستِ **بلا `can_view`** في
 *   السجلِّ الأصليِّ تبقى ممنوعةً (منعُها صحيح)، وشاشةٌ ليست في مانيفستِ
 *   إدارتِها لا تُفتَح لها — فالتوسعةُ بلا إقرارٍ منحٌ زائدٌ لا إصلاح.
 * ◆ **والرايةُ تُنقَل كما كانت**: `can_add/edit/delete` من السجلِّ الأصليِّ حيًّا.
 *
 * ⭐ **والدورةُ بالبابِ المحروس لكلِّ دور**: نسخٌ ⇒ بنودٌ ⇒ اعتمادٌ ⇒ تفعيلٌ ⇒
 *   ترحيلُ الحاملين ⇒ تقاعدُ السابق. **والتجميدُ يُرفَع مرّةً ويُعاد ويُقاس.**
 * ◆ **مُعاوَدة**: تشغيلٌ ثانٍ يجد صفرَ نقصٍ ويخرج بنجاحٍ بلا عمل.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
use App\Services\Security\PolicyWriteService as P;

const APPROVER = 56;
$WHY  = 'استعادة شاشات الإدارات المسقطة في تحول القوالب — 2028_06_21';
$LOGF = $ROOT . '/database/migrations/.2028_06_21.out';
$buf  = "── استعادةُ شاشاتِ الإداراتِ المُسقَطة ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$esc  = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };

$emit = function ($tail) use (&$buf, $LOGF) { $out = $buf . $tail; file_put_contents($LOGF, $out); echo $out; };

$was = array();
foreach (array(P::FREEZE_GRANTS, P::FREEZE_ACTIVATION) as $s) {
    $was[$s] = (int) $one("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
}
$restore = function () use ($conn, $was, $WHY, &$buf) {
    foreach ($was as $s => $a) {
        $c = $conn->query("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
        if ($c && (int) $c->fetch_row()[0] === $a) { $buf .= "  ↩ «{$s}» على حالها ({$a})\n"; continue; }
        $r = P::setFreeze($conn, $s, $a, 'إعادة التجميد بعد نافذة الصيانة — ' . $WHY, 0);
        $buf .= "  ↩ «{$s}» ⇐ {$a} : " . ($r['ok'] ? 'أُعيدت' : 'فشل: ' . $r['msg']) . "\n";
    }
};
$die = function ($m) use (&$buf, $restore, $emit) {
    $buf .= "  ✘ {$m}\n"; $restore();
    $emit("رسبت الهجرة — والبوابات أُعيدت\n"); exit(1);
};

/* ═══ ① جردُ النقصِ لكلِّ إدارةٍ من مانيفستِها والسجلِّ الأصليِّ والقالبِ المحمول ═══ */
$plan = array();
foreach (glob($ROOT . '/tests/dept_suite/manifest_*.php') as $mf) {
    $dept = substr(basename($mf, '.php'), 9);
    $M = @include $mf;
    if (!is_array($M) || empty($M['user']) || empty($M['screens'])) { continue; }
    $ur = $conn->query("SELECT id, role FROM users WHERE username=" . $esc($M['user'])
                     . " AND COALESCE(is_deleted,0)=0 AND status='active' LIMIT 1");
    $u = $ur ? $ur->fetch_assoc() : null;
    if (!$u) { $log("⚠ {$dept}: لا مستخدمَ «{$M['user']}» — تُخطّى"); continue; }
    $uid = (int) $u['id']; $role = (int) $u['role'];
    $pid = (int) $one("SELECT g.profile_id FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                        WHERE g.user_id={$uid} AND g.revoked_at IS NULL LIMIT 1");
    if (!$pid) { $log("⚠ {$dept}: لا قالبَ نافذًا لحاملِه — تُخطّى"); continue; }

    $miss = array();
    foreach ($M['screens'] as $s) {
        $rt = isset($s['route']) ? (string) $s['route'] : '';
        if ($rt === '') { continue; }
        $e = $esc($rt);
        /* ⛔ الدليلُ الثاني شرط: بلا `can_view` في السجلِّ الأصليِّ لا تُبذَر */
        $rp = $conn->query("SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                              FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                             WHERE rp.role_id={$role} AND m.code={$e} LIMIT 1");
        $f = $rp ? $rp->fetch_assoc() : null;
        if (!$f || (int) $f['can_view'] !== 1) { continue; }
        $inTpl = (int) $one("SELECT COUNT(*) FROM gov_profile_items
                              WHERE profile_id={$pid} AND item_kind='screen' AND allow=1 AND item_ref={$e}");
        if ($inTpl > 0) { continue; }
        $miss[$rt] = $f;
    }
    if ($miss) { $plan[$dept] = array('role' => $role, 'pid' => $pid, 'miss' => $miss); }
}
$total = 0; foreach ($plan as $p) { $total += count($p['miss']); }
$log('① إداراتٌ ينقصها = ' . count($plan) . ' · شاشاتٌ ناقصة = ' . $total);
if (!$plan) { $emit("مبذورٌ سلفًا — لا عمل\n"); exit(0); }

/* ═══ ② نافذةُ صيانةٍ واحدةٌ لكلِّ الأدوار ═══ */
foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { continue; }
    $r = P::setFreeze($conn, $s, 0, 'نافذة صيانة لاستعادة شاشات الإدارات — ' . $WHY, APPROVER);
    if (!$r['ok']) { $die("فتحُ «{$s}» فشل: " . $r['msg']); }
}

/* ═══ ③ لكلِّ إدارة: نسخٌ ⇒ بنودٌ ⇒ اعتمادٌ ⇒ تفعيلٌ ⇒ ترحيلٌ ⇒ تقاعد ═══ */
$doneD = 0; $doneS = 0;
foreach ($plan as $dept => $p) {
    $cur = (int) $p['pid'];
    $curCode = (string) $one("SELECT profile_code FROM gov_role_profiles WHERE profile_id={$cur}");
    $curCnt  = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$cur} AND item_kind='screen' AND allow=1");

    $n = 1;
    while ($one("SELECT profile_id FROM gov_role_profiles WHERE profile_code=" . $esc($curCode . '-R' . $n))) { $n++; }
    $code = $curCode . '-R' . $n;

    $r = P::cloneProfile($conn, $cur, $code, $WHY, APPROVER);
    if (!$r['ok']) { $die("[{$dept}] النسخُ فشل: " . $r['msg']); }
    $new = (int) $r['id'];

    $ca = 0;
    foreach ($p['miss'] as $ref => $f) {
        $x = P::setProfileItem($conn, $new, $ref, array(
            'allow'      => 1,
            'can_add'    => (int) $f['can_add'],
            'can_edit'   => (int) $f['can_edit'],
            'can_delete' => (int) $f['can_delete'],
        ), 'استعادة شاشة إدارة مسقطة في تحول القوالب — الراية من السجل الأصلي', APPROVER);
        if ($x['ok']) { $ca++; }
        elseif ($x['code'] !== 'NOTHING') { $die("[{$dept}] إضافةُ «{$ref}» فشلت: " . $x['msg']); }
    }
    $newCnt = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$new} AND item_kind='screen' AND allow=1");
    if ($newCnt !== $curCnt + $ca) { $die("[{$dept}] العدُّ لم يطابق: {$newCnt} ≠ {$curCnt}+{$ca}"); }

    if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}") === 'draft') {
        $x = P::approveProfile($conn, $new, 'اعتماد إصدار مستعيد لشاشات الإدارة', APPROVER);
        if (!$x['ok'] && $x['code'] !== 'ALREADY_APPROVED') { $die("[{$dept}] الاعتمادُ فشل: " . $x['msg']); }
    }
    $x = P::activateProfile($conn, $new, $WHY, APPROVER);
    if (!$x['ok']) { $die("[{$dept}] التفعيلُ فشل: " . $x['msg']); }

    $holders = array();
    $rh = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                         WHERE profile_id={$cur} AND revoked_at IS NULL");
    while ($rh && ($h = $rh->fetch_assoc())) { $holders[] = $h; }
    foreach ($holders as $h) {
        $x = P::revokeGrant($conn, (int) $h['grant_id'], 'ترحيل إلى ' . $code, APPROVER);
        if (!$x['ok']) { $die("[{$dept}] السحبُ فشل: " . $x['msg']); }
        $x = P::assignProfile($conn, (int) $h['user_id'], $new, $WHY, APPROVER);
        if (!$x['ok']) { $die("[{$dept}] الإسنادُ فشل: " . $x['code'] . ' — ' . $x['msg']); }
    }
    P::retireProfile($conn, $cur, 'حلَّ محلَّه ' . $code, APPROVER);

    $log(sprintf('   %-12s دور=%-3d %s ⇐ %s · أُضيف=%d (%d⇒%d) · حاملون=%d',
        $dept, (int) $p['role'], $curCode, $code, $ca, $curCnt, $newCnt, count($holders)));
    $plan[$dept]['new'] = $new;
    $doneD++; $doneS += $ca;
}

$restore();

/* ═══ ④ تحقُّقٌ ذاتيّ — والصفرُ لا يُقرأ نجاحًا حتى يُثبَت أنه مقيس ═══ */
/* ⛔ **والتحقُّقُ يقيس القالبَ الذي عولج لا مستخدمًا يُنتقى بالدور**: أوّلُ صياغةٍ
     اختارت `أيَّ مستخدمٍ بالدور` فوقعت على حسابٍ **بلا منحةٍ نافذة** (‏دور 1 —
     #1) فعُدَّت اثنتا عشرة شاشةً ناقصةً وهي مبذورةٌ فعلًا: **أخضرُ كاذبٌ
     مقلوبٌ — رسوبٌ من عمى المقياس لا من عطبِ العمل.** */
$left = 0;
foreach ($plan as $dept => $p) {
    if (empty($p['new'])) { continue; }
    $pidNow = (int) $p['new'];
    foreach (array_keys($p['miss']) as $ref) {
        $left += ((int) $one("SELECT COUNT(*) FROM gov_profile_items
                               WHERE profile_id={$pidNow} AND item_kind='screen' AND allow=1 AND item_ref=" . $esc($ref)) === 0) ? 1 : 0;
    }
}
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$fzW = count(array_filter($was));
$log("④ إداراتٌ عولجت={$doneD} · شاشاتٌ استُعيدت={$doneS} · بقيت ناقصةً={$left} · مجمَّدة={$fz} (المرجع {$fzW})");
if ($left !== 0 || $doneS !== $total || $fz !== $fzW) {
    $emit("التحقُّقُ لم يطابق\n"); exit(1);
}
$emit("اكتملت الاستعادة\n");
exit(0);
