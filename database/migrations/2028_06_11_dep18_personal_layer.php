<?php
/**
 * 2028_06_11 — الطبقةُ الشخصيّةُ لـDEP-18 · وإصدارٌ ثانٍ من قالبِ الدور 5
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيس**: `2028_06_09` بنى **دورةَ الإدارةِ وحدَها** (22 بندًا)
 *   ونسي أنَّ لكلِّ مساحةٍ **طبقةً شخصيّةً** إلى جانبِها: إحدى وعشرون مساحةً
 *   تحمل `PERSONAL` (من 1 إلى 14 صفًّا)، وDEP-18 كانت **صفرًا**.
 *   فرسّب `perm01_render_vs_guard`: «24 من 675 بلاطةً مردودةٌ عند 3 مستخدمًا»
 *   — أي **ثماني بلاطاتٍ في «مساحة عملي» × ثلاثةِ حسابات**، فقدوها بانتقالِهم
 *   من قالبِ الدورِ 6 إلى قالبٍ لا يحملها.
 *
 * ◆ **والقشرةُ ليست منها**: «الرئيسية» و«المراسلات» تُصيَّران من
 *   `nav_canonical.anchor_key` لا من موضعٍ — ولذلك ظهرتا رغمَ صفرِ `GLOBAL_SHELL`.
 *
 * ⭐ **ولا يُعدَّل قالبٌ نافذٌ في مكانِه** (‏`PolicyWriteService` نصًّا: «بل
 *   يُصدَر إصدارٌ جديدٌ ويُرحَّل حاملوه») — فالإصلاحُ **نسخةٌ ثانيةٌ**:
 *   `TGT-R5` ⇐ نسخٌ ⇐ `TGT-R5-V2` مسودّةً ⇐ عشرةُ بنودٍ شخصيّةٍ ⇐ اعتمادٌ ⇐
 *   تفعيلٌ ⇐ ترحيلُ الحاملين ⇐ تقاعدُ الأوّل. **لا `UPDATE` على نافذ.**
 *
 * ◆ **والمنقولُ ما تحمله المساحاتُ الأخرى لا ما أستحسنه**: خمسةُ مواضعَ
 *   `PERSONAL` بمجموعتَي `WS-MY` (‏«الملف الشخصي» 103 · «العمل اليومي» 104)
 *   كما في DEP-12 حرفًا — وما كان منها في دورةِ DEP-18 سلفًا **لا يُكرَّر**
 *   (`main/my_workspace` · `finrequests/request_form` · `main/project_users`).
 *   وأعلامُ البنودِ العشرةِ منقولةٌ من `TGT-R6` — لا تُخترَع.
 *
 * ⭐ **والتجميدُ يُرفَع بإذنِ المالكِ ويُعاد في كلِّ مخرج** — والإغلاقُ يُقاس.
 * ◆ **مُعاوَدة**: كلُّ خطوةٍ تفحص حالتَها. التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';

use App\Services\Security\PolicyWriteService as P;

const WS       = 'DEP-18';
const OLDC     = 'TGT-R5';
const NEWC     = 'TGT-R5-V2';
const OLD_PROF = 475;
const APPROVER = 56;

$USERS = array(11, 18, 70);
$WHY   = 'الطبقة الشخصية لإدارة الحركة والتشغيل — بأمر المالك 2028_06_11';
$in    = implode(',', array_map('intval', $USERS));

/* خمسةُ مواضعَ شخصيّةٍ — route · مجموعة WS-MY · ترتيب · تسمية */
$PERS = array(
    array('portal/my_achievement', 103, 7,  'مؤشرات الإنجاز الشخصي'),
    array('portal/my_portal',      103, 8,  'البوابة الشخصية'),
    array('user_capacities',       103, 9,  'الصفات الوظيفية والتبديل بينها'),
    array('portal/my_tasks',       104, 10, 'المهام المسنَدة'),
    array('portal/my_reports',     104, 12, 'البلاغات المسجَّلة'),
);
/* عشرةُ رموزٍ تفتحها بلاطاتُ «مساحة عملي» وطبقةُ WS-MY */
$REFS = array(
    'Portal/approvals_inbox.php', 'Portal/my_achievement.php', 'Portal/my_portal.php',
    'Portal/my_requests.php', 'Portal/my_tasks.php', 'Portal/notifications.php',
    'Portal/my_reports.php', 'Tickets/ticket_contextual_open.php',
    'user_capacities.php', 'main/dashboard.php',
);

$LOGF = $ROOT . '/database/migrations/.2028_06_11.out';
$buf  = "── الطبقةُ الشخصيّةُ لـ" . WS . " ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$esc  = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };

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
$die = function ($m) use (&$buf, $restore, $LOGF) {
    $buf .= "  ✘ {$m}\n"; $restore();
    file_put_contents($LOGF, $buf . "رسبت الهجرةُ — والبوّاباتُ أُعيدت\n"); exit(1);
};

/* ═══ ① المواضعُ الشخصيّة ═════════════════════════════════════════════════ */
$cP = 0;
foreach ($PERS as $p) {
    list($rt, $gid, $sn, $lab) = $p;
    if ($one("SELECT placement_id FROM nav_workspace_placements
               WHERE workspace_id='" . WS . "' AND route=" . $esc($rt))) { continue; }
    $pid = 'WP-' . strtoupper(substr(md5(WS . '|P|' . $rt), 0, 16));
    if (!$conn->query("INSERT INTO nav_workspace_placements
        (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
         canonical_label, governing_source, source_ref, reason_code, effective_from,
         effective_to, status, version, created_by, approved_by, legacy_ref)
        VALUES (" . $esc($pid) . ",NULL,'" . WS . "',{$gid},'PERSONAL',{$sn}," . $esc($rt) . ","
        . $esc($lab) . ",'NAV-ARCH-02 §11 — الطبقةُ الشخصيّةُ في مِسمارِها لا في دورةِ الإدارة',"
        . $esc($WHY) . ",'PERSONAL_PARITY_S11',CURDATE(),NULL,'ACTIVE',1,
        'database/migrations/2028_06_11_dep18_personal_layer.php',NULL,NULL)")) {
        $die('إدراجُ موضعٍ شخصيٍّ فشل: ' . $conn->error);
    }
    $cP++;
}
$log("① مواضعُ شخصيّةٌ أُضيفت: {$cP} · الإجمالي: "
    . $one("SELECT COUNT(*) FROM nav_workspace_placements
             WHERE workspace_id='" . WS . "' AND placement_type='PERSONAL' AND status='ACTIVE'"));

/* ═══ ② الإصدارُ الثاني — نسخٌ ثمَّ بنودٌ ثمَّ اعتماد ══════════════════════ */
$old = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code=" . $esc(OLDC));
$new = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code=" . $esc(NEWC));
if (!$new) {
    if (!$old) { $die('القالب ' . OLDC . ' غير موجود'); }
    $r = P::cloneProfile($conn, $old, NEWC, 'إصدارٌ ثانٍ يحمل الطبقةَ الشخصيّة — ' . $WHY, APPROVER);
    if (!$r['ok']) { $die('النسخُ فشل: ' . $r['msg']); }
    $new = (int) $r['id'];
    $log("② نُسخ إلى {$new} · " . NEWC . ' : ' . $r['msg']);
} else { $log("② الإصدارُ الثاني {$new} قائم"); }

$cI = 0;
foreach ($REFS as $ref) {
    $src = $conn->query("SELECT can_add, can_edit, can_delete FROM gov_profile_items
                          WHERE profile_id=" . OLD_PROF . " AND item_kind='screen'
                            AND item_ref=" . $esc($ref) . " LIMIT 1");
    $a = ($src && $src->num_rows) ? $src->fetch_assoc() : array('can_add' => 0, 'can_edit' => 0, 'can_delete' => 0);
    $r = P::setProfileItem($conn, $new, $ref, array(
        'allow' => 1, 'can_add' => (int) $a['can_add'],
        'can_edit' => (int) $a['can_edit'], 'can_delete' => (int) $a['can_delete'],
    ), 'بند الطبقة الشخصية — ' . $WHY, APPROVER);
    if (!$r['ok'] && $r['code'] !== 'NOTHING') { $die("بندُ «{$ref}» فشل: " . $r['code'] . ' — ' . $r['msg']); }
    if ($r['ok']) { $cI++; }
}
$log("② بنودٌ شخصيّةٌ ضُبطت: {$cI} · إجماليُّ المسموح: "
    . $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$new} AND item_kind='screen' AND allow=1"));

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}") === 'draft') {
    $r = P::approveProfile($conn, $new,
        'اعتماد الإصدار الثاني — 22 شاشة دورة + 10 بنود الطبقة الشخصية بأعلام قالب الدور 6', APPROVER);
    if (!$r['ok'] && $r['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $r['msg']); }
    $log('② اعتماد: ' . $r['msg']);
}

/* ═══ ③ نافذةُ الصيانة ════════════════════════════════════════════════════ */
foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { $log("③ «{$s}» مفتوحةٌ سلفًا"); continue; }
    $r = P::setFreeze($conn, $s, 0, 'نافذة صيانة محدودة لترحيل حاملي قالب الحركة والتشغيل — ' . $WHY, APPROVER);
    if (!$r['ok']) { $die("فتحُ «{$s}» فشل: " . $r['msg']); }
    $log("③ «{$s}» فُتحت");
}

/* ═══ ④ تفعيلٌ ثمَّ ترحيلُ الحاملين ثمَّ تقاعدُ الأوّل ═════════════════════ */
if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}") !== 'active') {
    $r = P::activateProfile($conn, $new, $WHY, APPROVER);
    if (!$r['ok']) { $die('التفعيلُ فشل: ' . $r['msg']); }
    $log('④ تفعيل: ' . $r['msg']);
}
foreach ($USERS as $u) {
    $u = (int) $u;
    $g = $one("SELECT grant_id FROM gov_authority_grants
                WHERE user_id={$u} AND profile_id={$old} AND revoked_at IS NULL LIMIT 1");
    if ($g) {
        $r = P::revokeGrant($conn, (int) $g, 'ترحيل إلى الإصدار الثاني — ' . $WHY, APPROVER);
        if (!$r['ok']) { $die("سحبُ منحةِ u{$u} فشل: " . $r['msg']); }
    }
    if ($one("SELECT grant_id FROM gov_authority_grants
               WHERE user_id={$u} AND profile_id={$new} AND revoked_at IS NULL")) { continue; }
    $r = P::assignProfile($conn, $u, $new, $WHY, APPROVER);
    if (!$r['ok']) { $die("إسنادُ u{$u} فشل: " . $r['code'] . ' — ' . $r['msg']); }
    $log("④ u{$u} ⇐ " . NEWC);
}
if ($old && (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$old}") === 'active') {
    $r = P::retireProfile($conn, $old, 'حلَّ محلَّه الإصدارُ الثاني — ' . $WHY, APPROVER);
    $log('④ تقاعدُ ' . OLDC . ' : ' . ($r['ok'] ? $r['msg'] : $r['msg']));
}

/* ═══ ⑤ إغلاقُ النافذة ════════════════════════════════════════════════════ */
$restore();

/* ═══ ⑥ تحقُّقٌ ذاتيّ ═══════════════════════════════════════════════════════ */
$g5  = (int) $one("SELECT COUNT(DISTINCT user_id) FROM gov_authority_grants
                    WHERE profile_id={$new} AND revoked_at IS NULL");
$itm = (int) $one("SELECT COUNT(*) FROM gov_profile_items
                    WHERE profile_id={$new} AND item_kind='screen' AND allow=1");
$per = (int) $one("SELECT COUNT(*) FROM nav_workspace_placements
                    WHERE workspace_id='" . WS . "' AND placement_type='PERSONAL' AND status='ACTIVE'");
$act = (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}");
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$fzW = count(array_filter($was));
$log("⑥ ممنوحون={$g5} · بنودٌ مسموحة={$itm} · مواضعُ شخصيّة={$per} · الحال={$act} · مجمَّدة={$fz} (المرجع {$fzW})");
if ($g5 !== 3 || $itm !== 32 || $per !== 5 || $act !== 'active' || $fz !== $fzW) {
    file_put_contents($LOGF, $buf . "التحقُّقُ لم يطابق\n"); exit(1);
}
file_put_contents($LOGF, $buf . "اكتملت الطبقةُ الشخصيّة — والبوّاباتُ أُعيدت\n");
exit(0);
