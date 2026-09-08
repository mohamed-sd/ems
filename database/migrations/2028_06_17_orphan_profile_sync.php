<?php
/**
 * 2028_06_17 — مصالحةُ قالبِ المراجعةِ مع سجلِّ اليتامى (إصدارٌ جديد)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا إصدارٌ لا تعديل**: `TGT-ORPHAN` نافذٌ، و`setProfileItem` ترفض
 *   تحريرَ النافذِ («‏بل يُصدَر إصدارٌ جديدٌ ويُرحَّل حاملوه»). فكلَّما تغيَّر
 *   السجلُّ — بتسجيلِ هويّةٍ أو بخروجِ شاشةٍ من اليُتم — تُصالَح البنودُ هنا.
 *
 * ◆ **والمصالحةُ في اتّجاهَين**:
 *   ① يتيمٌ بهويّةٍ خارجَ القالب ⇒ يُضاف `allow=1` (فيُفتَح للمراجعة)
 *   ② بندٌ في القالبِ لم يعد يتيمًا ⇒ `allow=0` **ولا يُحذف صفُّه**
 *      (‏عُرفُ البيت: «نُظر فيها فمُنعت» أنفعُ من غيابٍ لا يُفرَّق فيه بين
 *      «لم تُدرَس» و«رُفعت»).
 *
 * ⭐ والدورةُ كاملةً بالبابِ المحروس: نسخٌ ⇒ بنودٌ ⇒ اعتمادٌ ⇒ تفعيلٌ ⇒
 *   ترحيلُ الحاملين ⇒ تقاعدُ السابق. **والتجميدُ يُرفَع ويُعاد ويُقاس إغلاقُه.**
 * ◆ **مُعاوَدة**: إن كان القالبُ مطابقًا للسجلِّ أصلًا تخرج بلا عمل.
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

const BASE     = 'TGT-ORPHAN';
const APPROVER = 56;
$WHY  = 'مصالحة قالب مراجعة الشاشات مع سجل اليتامى — 2028_06_17';
$LOGF = $ROOT . '/database/migrations/.2028_06_17.out';
$buf  = "── مصالحةُ قالبِ المراجعة ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

$was = array();
foreach (array(P::FREEZE_GRANTS, P::FREEZE_ACTIVATION) as $s) {
    $was[$s] = (int) $one("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
}
$restore = function () use ($conn, $was, $WHY, &$buf) {
    foreach ($was as $s => $a) {
        $c = $conn->query("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
        if ($c && (int) $c->fetch_row()[0] === $a) { $buf .= "  ↩ «{$s}» على حالها ({$a})\n"; continue; }
        $r = P::setFreeze($conn, $s, $a, 'إعادة التجميد بعد المصالحة — ' . $WHY, 0);
        $buf .= "  ↩ «{$s}» ⇐ {$a} : " . ($r['ok'] ? 'أُعيدت' : 'فشل: ' . $r['msg']) . "\n";
    }
};
$die = function ($m) use (&$buf, $restore, $LOGF) {
    $buf .= "  ✘ {$m}\n"; $restore();
    file_put_contents($LOGF, $buf . "رسبت\n"); exit(1);
};

$cur = (int) $one("SELECT profile_id FROM gov_role_profiles
                    WHERE profile_code LIKE '" . BASE . "%' AND state='active'
                    ORDER BY profile_id DESC LIMIT 1");
if (!$cur) { $die('لا قالبَ نافذًا يبدأ بـ' . BASE); }

/* ما ينقص وما يزيد */
$add = array(); $drop = array();
$r = $conn->query("SELECT o.module_code FROM gov_orphan_screens o
                    WHERE o.decision<>'WIRED' AND o.module_code IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                                       WHERE i.profile_id={$cur} AND i.item_kind='screen'
                                         AND i.item_ref=o.module_code AND i.allow=1)");
while ($r && ($x = $r->fetch_row())) { $add[] = $x[0]; }
$r = $conn->query("SELECT item_ref FROM gov_profile_items
                    WHERE profile_id={$cur} AND item_kind='screen' AND allow=1
                      AND item_ref NOT IN (SELECT module_code FROM gov_orphan_screens
                                            WHERE module_code IS NOT NULL AND decision<>'WIRED')");
while ($r && ($x = $r->fetch_row())) { $drop[] = $x[0]; }
$log("القالب النافذ = {$cur} · ينقصه = " . count($add) . " · يزيد فيه = " . count($drop));
if (!$add && !$drop) { file_put_contents($LOGF, $buf . "مطابقٌ سلفًا — لا عمل\n"); exit(0); }

/* إصدارٌ جديدٌ برمزٍ متسلسل */
$n = 2;
while ($one("SELECT profile_id FROM gov_role_profiles WHERE profile_code='" . BASE . "-V{$n}'")) { $n++; }
$code = BASE . '-V' . $n;
$r = P::cloneProfile($conn, $cur, $code, $WHY, APPROVER);
if (!$r['ok']) { $die('النسخُ فشل: ' . $r['msg']); }
$new = (int) $r['id'];
$log("نُسخ إلى {$new} · {$code}");

$ca = 0; $cd = 0;
foreach ($add as $ref) {
    $x = P::setProfileItem($conn, $new, $ref,
        array('allow' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0),
        'يتيمٌ جديدٌ تحت المراجعة — رؤيةٌ فقط', APPROVER);
    if ($x['ok']) { $ca++; } elseif ($x['code'] !== 'NOTHING') { $die("إضافةُ «{$ref}» فشلت: " . $x['msg']); }
}
foreach ($drop as $ref) {
    $x = P::setProfileItem($conn, $new, $ref,
        array('allow' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0),
        'لم تعد يتيمةً — تُستبعَد ولا يُحذف صفُّها', APPROVER);
    if ($x['ok']) { $cd++; }
}
$log("أُضيف={$ca} · استُبعد={$cd} · المسموحُ الآن = "
    . $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$new} AND item_kind='screen' AND allow=1"));

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}") === 'draft') {
    $x = P::approveProfile($conn, $new, 'اعتماد إصدار مصالح مع سجل اليتامى', APPROVER);
    if (!$x['ok'] && $x['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $x['msg']); }
}

foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { continue; }
    $x = P::setFreeze($conn, $s, 0, 'نافذة صيانة لترحيل قالب المراجعة — ' . $WHY, APPROVER);
    if (!$x['ok']) { $die("فتحُ «{$s}» فشل: " . $x['msg']); }
}
$x = P::activateProfile($conn, $new, $WHY, APPROVER);
if (!$x['ok']) { $die('التفعيلُ فشل: ' . $x['msg']); }

$holders = array();
$r = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                    WHERE profile_id={$cur} AND revoked_at IS NULL");
while ($r && ($h = $r->fetch_assoc())) { $holders[] = $h; }
foreach ($holders as $h) {
    $x = P::revokeGrant($conn, (int) $h['grant_id'], 'ترحيل إلى ' . $code, APPROVER);
    if (!$x['ok']) { $die('السحبُ فشل: ' . $x['msg']); }
    $x = P::assignProfile($conn, (int) $h['user_id'], $new, $WHY, APPROVER);
    if (!$x['ok']) { $die('الإسنادُ فشل: ' . $x['msg']); }
}
$log('رُحِّل حاملون = ' . count($holders));
P::retireProfile($conn, $cur, 'حلَّ محلَّه ' . $code, APPROVER);

$restore();

$itm = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$new} AND item_kind='screen' AND allow=1");
$orp = (int) $one("SELECT COUNT(*) FROM gov_orphan_screens WHERE decision<>'WIRED' AND module_code IS NOT NULL");
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$log("تحقُّق: بنودٌ مسموحة={$itm} · يتامى بهويّة={$orp} · مجمَّدة={$fz} (المرجع " . count(array_filter($was)) . ")");
if ($itm !== $orp || $fz !== count(array_filter($was))) {
    file_put_contents($LOGF, $buf . "التحقُّقُ لم يطابق\n"); exit(1);
}
file_put_contents($LOGF, $buf . "اكتملت المصالحة\n");
exit(0);
