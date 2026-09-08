<?php
/**
 * 2028_06_09 — ربطُ مديري الحركةِ بالدورِ 5 عبرَ منفذِ الكتابةِ المحروس
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تكملةُ `2028_06_08`: المساحةُ DEP-18 تُصيَّر 22/22 لكنَّها بلا مستخدم.
 *   وثلاثةُ حساباتٍ اسمُها «حركة» على الدورِ 6 لم تدخلِ النظامَ قطُّ.
 *
 * ⛔ **ولا تُكتب جداولُ السياسةِ بصفٍّ يدويّ** (PERM-01-DEC §0-3 نصًّا).
 *   ⚠ **ومحاولتي الأولى خالفت هذا**: أنشأت `TGT-R5` بـ`INSERT` خامٍّ بحالةِ
 *     `active` — فمرَّت لأنَّ القادحَ `BEFORE UPDATE` لا يرى الإدراج. وذاك
 *     قالبٌ **نافذٌ بلا سجلِّ اعتماد**. فيُنظَّف أثرُه هنا (‏لم يحمل منحةً قطُّ
 *     ولا سجلَّ تفعيل) ثمَّ يُعاد تأليفُه **من بابِه**: مسودّةٌ ⇐ بنودٌ ⇐
 *     اعتمادٌ بمعتمدٍ معلوم ⇐ تفعيلٌ ⇐ إسناد.
 *
 * ⭐ **والتجميدُ يُرفَع بإذنِ المالكِ ويُعاد** — لا يُلتَفُّ عليه:
 *   القادحُ `trg_perm01_grant_freeze` **`BEFORE INSERT` فقط**، فكان يمكن
 *   الالتفافُ بـ`UPDATE` على منحةٍ قائمة. ⛔ **ولم يُفعَل**. فالبوّابتان
 *   تُفتحان بـ`setFreeze()` بسببٍ مكتوبٍ يُسجَّل في `perm_change_log`،
 *   **وتُغلقان في كلِّ مخرجٍ** نجحت الهجرةُ أو رسبت — والإغلاقُ **يُقاس**.
 *   والنافذةُ أضيقُ ما يمكن: التأليفُ والبنودُ والاعتمادُ **قبلَ** الفتح.
 *
 * ⭐ **و`item_ref` هويّةُ الوحدةِ لا المسار**: `setProfileItem` ترفض رمزًا لا
 *   يقابل `modules.code`، والحارسُ يطابقها كذلك. والفرقُ حقيقيٌّ في واحدة:
 *   `main/org_assignments.php` رمزُها `admin/org_assignments.php`.
 *
 * ◆ **والمعتمِدُ إنسانٌ مسمًّى**: الخدمةُ ترفض `actorId=0` للاعتماد. والمستخدم
 *   **56 «حسابات · عمار الفاتح بشير» (الدور 15 — مديرُ الصلاحيّات)** هو معتمِدُ
 *   هذا السجلِّ القائمُ (33 اعتمادًا سابقًا بـ`PERM-02-CONSOLE`).
 *
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

const ROLE     = 5;
const OLD_ROLE = 6;
const OLD_PROF = 475;      /* TGT-R6 — قالبُ إدارةِ الموقع */
const CODE     = 'TGT-R5';
const APPROVER = 56;       /* الدور 15 — مديرُ الصلاحيّات، معتمِدُ هذا السجل */

$USERS = array(11, 18, 70);
$WHY   = 'استعادة إدارة الحركة والتشغيل — بأمر المالك 2028_06_09';
$in    = implode(',', array_map('intval', $USERS));

/* رموزُ الوحداتِ للاثنتين والعشرين ⇐ أعلامُها منقولةٌ من TGT-R6 حيثُ وُجدت */
$REFS = array(
    'movement/map_page.php', 'Contracts/contract_sites.php', 'Contracts/contract_monthly_plan.php',
    'Contracts/contract_resource_plan.php', 'Operations/containers.php', 'Projects/sites.php',
    'main/project_users.php', 'admin/org_assignments.php', 'movement/movement_operations.php',
    'Timesheet/timesheet_type.php', 'Approvals/hours_approval.php', 'Reports/exceptions_report.php',
    'FinRequests/dept_inbox.php', 'FinRequests/request_form.php', 'Finance/events_list_fin.php',
    'Finance/unit_records_fin.php', 'Finance/budget_form_fin.php', 'Finance/cost_report_fin.php',
    'Reports/reports.php', 'main/role_board.php', 'Settings/settings.php', 'main/my_workspace.php',
);

$LOGF = $ROOT . '/database/migrations/.2028_06_09.out';
$buf  = "── ربطُ مديري الحركةِ عبرَ المنفذِ المحروس ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

/* ── حالةُ البوّابتين قبلَ المساس — تُعاد إليها حتمًا ─────────────────────── */
$was = array();
foreach (array(P::FREEZE_GRANTS, P::FREEZE_ACTIVATION) as $s) {
    $was[$s] = (int) $one("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
}
$restore = function () use ($conn, $was, $WHY, &$buf) {
    foreach ($was as $s => $a) {
        $c = $conn->query("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
        if ($c && (int) $c->fetch_row()[0] === $a) { $buf .= "  ↩ «{$s}» على حالها ({$a})\n"; continue; }
        $r = P::setFreeze($conn, $s, $a, 'إعادة التجميد بعد نافذة الاستعادة — ' . $WHY, 0);
        $buf .= "  ↩ «{$s}» ⇐ {$a} : " . ($r['ok'] ? 'أُعيدت' : 'فشل: ' . $r['msg']) . "\n";
    }
};
$die = function ($m) use (&$buf, $restore, $LOGF) {
    $buf .= "  ✘ {$m}\n"; $restore();
    file_put_contents($LOGF, $buf . "رسبت الهجرةُ — والبوّاباتُ أُعيدت\n");
    exit(1);
};

/* ⛔ حارسٌ: لا يُنقَل حسابٌ استُعمل فعلًا */
if ((int) $one("SELECT COUNT(*) FROM users WHERE id IN ({$in}) AND last_login_at IS NOT NULL") > 0) {
    $die('حسابٌ دخل النظامَ سابقًا — لا نقلَ بلا قرارٍ صريح');
}

/* ═══ ① تنظيفُ أثرِ المحاولةِ الخامّة — إن بقي ═════════════════════════════ */
$stale = (int) $one("SELECT profile_id FROM gov_role_profiles
                      WHERE profile_code='" . CODE . "'
                        AND profile_id NOT IN (SELECT profile_id FROM gov_profile_activation_approval)");
if ($stale) {
    $held = (int) $one("SELECT COUNT(*) FROM gov_authority_grants WHERE profile_id={$stale}");
    if ($held > 0) { $die("القالب {$stale} حمل منحًا — لا يُحذف"); }
    $conn->query("DELETE FROM gov_profile_items WHERE profile_id={$stale}");
    $conn->query("DELETE FROM gov_role_profiles WHERE profile_id={$stale}");
    $log("① أثرُ المحاولةِ الخامّة (القالب {$stale}) أُزيل — صفرُ منحةٍ حملها");
} else { $log('① لا أثرَ خامًّا'); }

/* ═══ ② التأليفُ من الباب — مسودّةٌ ثمَّ بنودٌ ثمَّ اعتماد ═════════════════ */
$pid = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code='" . CODE . "'");
if (!$pid) {
    $r = P::createProfile($conn, array(
        'profile_code' => CODE, 'title_ar' => 'هدف الدليل — إدارة الحركة والتشغيل',
        'grade' => 'G3', 'dept_code' => 'هدف الدليل', 'data_scope' => 'ادارته',
        'fixed_rule' => 'قالب واحد لكل دور ولا اتحاد — والدرجة لا تفرق في ورقة الدليل فالفرق على الافعال',
    ), 'تأليف قالب إدارة الحركة والتشغيل — ' . $WHY, APPROVER);
    if (!$r['ok']) { $die('تأليفُ القالبِ فشل: ' . $r['msg']); }
    $pid = (int) $r['id'];
    $log("② القالب {$pid} · " . CODE . " مسودّةً");
} else { $log("② القالب {$pid} قائم"); }

$cI = 0;
foreach ($REFS as $ref) {
    $src = $conn->query("SELECT can_add, can_edit, can_delete FROM gov_profile_items
                          WHERE profile_id=" . OLD_PROF . " AND item_kind='screen'
                            AND item_ref='" . $conn->real_escape_string($ref) . "' LIMIT 1");
    $a = ($src && $src->num_rows) ? $src->fetch_assoc() : array('can_add' => 0, 'can_edit' => 0, 'can_delete' => 0);
    $r = P::setProfileItem($conn, $pid, $ref, array(
        'allow' => 1, 'can_add' => (int) $a['can_add'],
        'can_edit' => (int) $a['can_edit'], 'can_delete' => (int) $a['can_delete'],
    ), 'بند من قائمة إدارة الحركة والتشغيل الأصلية — ' . $WHY, APPROVER);
    if (!$r['ok'] && $r['code'] !== 'NOTHING') { $die("بندُ «{$ref}» فشل: " . $r['code'] . ' — ' . $r['msg']); }
    if ($r['ok']) { $cI++; }
}
$log("② بنودٌ ضُبطت: {$cI} · مسموحةٌ الآن: "
    . $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$pid} AND item_kind='screen' AND allow=1"));

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$pid}") === 'draft') {
    $r = P::approveProfile($conn, $pid,
        'اعتماد قالب إدارة الحركة والتشغيل — 22 شاشة منقولة حرفا من قائمة الدور 6 قبل هجرة org_v4', APPROVER);
    if (!$r['ok'] && $r['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $r['msg']); }
    $log('② اعتماد: ' . $r['msg']);
}

/* ═══ ③ نافذةُ الصيانة — أضيقُ ما يمكن ════════════════════════════════════ */
foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { $log("③ «{$s}» مفتوحةٌ سلفًا"); continue; }
    $r = P::setFreeze($conn, $s, 0,
        'نافذة صيانة محدودة لربط إدارة الحركة والتشغيل بحساباتها — تغلق فور الفراغ · ' . $WHY, APPROVER);
    if (!$r['ok']) { $die("فتحُ «{$s}» فشل: " . $r['msg']); }
    $log("③ «{$s}» فُتحت وسُجِّل سببُها");
}

/* ═══ ④ تفعيلٌ ثمَّ نقلُ المنحةِ ثمَّ نقلُ الحسابات ═══════════════════════ */
if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$pid}") !== 'active') {
    $r = P::activateProfile($conn, $pid, $WHY, APPROVER);
    if (!$r['ok']) { $die('التفعيلُ فشل: ' . $r['msg']); }
    $log('④ تفعيل: ' . $r['msg']);
}
foreach ($USERS as $u) {
    $u = (int) $u;
    $g = $one("SELECT grant_id FROM gov_authority_grants
                WHERE user_id={$u} AND profile_id=" . OLD_PROF . " AND revoked_at IS NULL LIMIT 1");
    if ($g) {
        $r = P::revokeGrant($conn, (int) $g, 'انتقال الحساب إلى إدارة الحركة والتشغيل — ' . $WHY, APPROVER);
        if (!$r['ok']) { $die("سحبُ منحةِ u{$u} فشل: " . $r['msg']); }
    }
    if ($one("SELECT grant_id FROM gov_authority_grants
               WHERE user_id={$u} AND profile_id={$pid} AND revoked_at IS NULL")) { $log("④ u{$u} ممنوحٌ سلفًا"); continue; }
    $r = P::assignProfile($conn, $u, $pid, $WHY, APPROVER);
    if (!$r['ok']) { $die("إسنادُ u{$u} فشل: " . $r['code'] . ' — ' . $r['msg']); }
    $log("④ u{$u} ⇐ " . CODE . ' : ' . $r['msg']);
}
$conn->query("UPDATE users SET role=" . ROLE . " WHERE id IN ({$in}) AND role=" . OLD_ROLE);
$log('④ حساباتٌ نُقلت ' . OLD_ROLE . '⇒' . ROLE . ': ' . $conn->affected_rows);

/* ═══ ⑤ إغلاقُ النافذة — في كلِّ الأحوال ═════════════════════════════════ */
$restore();

/* ═══ ⑥ تحقُّقٌ ذاتيّ — والإغلاقُ يُقاس لا يُفترَض ════════════════════════ */
$u5  = (int) $one("SELECT COUNT(*) FROM users WHERE role=" . ROLE);
$g5  = (int) $one("SELECT COUNT(DISTINCT user_id) FROM gov_authority_grants
                    WHERE profile_id={$pid} AND revoked_at IS NULL");
$act = (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$pid}");
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$fzW = count(array_filter($was));
$log("⑥ الدور 5: مستخدمون={$u5} · ممنوحون={$g5} · القالب={$act} · بوّاباتٌ مجمَّدة={$fz} (المرجع {$fzW})");
if ($u5 !== 3 || $g5 !== 3 || $act !== 'active' || $fz !== $fzW) {
    file_put_contents($LOGF, $buf . "التحقُّقُ لم يطابق\n"); exit(1);
}
file_put_contents($LOGF, $buf . "اكتمل الربط — والبوّاباتُ أُعيدت إلى حالها\n");
exit(0);
