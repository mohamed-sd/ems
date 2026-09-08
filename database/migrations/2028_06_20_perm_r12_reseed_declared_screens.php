<?php
/**
 * 2028_06_20 — إعادةُ بذرِ شاشاتِ دورِ المبيعات (12) المُسقَطةِ في تحوُّلِ القوالب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: فحصُ الإداراتِ الحيُّ (`tests/dept_suite --dept=sales`)
 *   رصد **27 شاشةً كانت تُفتَح في خطِّ الأساسِ المحفوظ وصارت تُردُّ بالحارس**
 *   إلى لوحةِ التحكم. والسببُ أنَّ تحوُّلَ `PERM-01` بذر قالبَ الدورِ 12 بـ**69
 *   بندًا** بينما يمنحه السجلُّ الأصليُّ `role_permissions` **180 شاشة** — فسقط
 *   في النقلِ ما لم يُنقَل، والحارسُ يقرأ القالبَ فيردُّ ما أسقطه.
 *
 * ◆ **ومجموعةُ الإصلاحِ محسومةٌ بدليلَين لا باجتهاد** — وكلُّ شاشةٍ هنا:
 *   ① كانت **PASS في خطِّ الأساسِ المحفوظ** (‏`baseline/sales.json`) — فالتصميمُ
 *      الجديدُ نفسُه يُقرُّ أنها تُفتَح، لا ذاكرةٌ قديمة؛ **و**
 *   ② لها `role_permissions(12).can_view = 1` — فهي ممنوحةٌ أصلًا لا مُخترَعة.
 *   ⛔ **وما نقصه أحدُ الدليلَين لا يُبذَر**: ثلاثُ شاشاتِ تشغيلٍ رُدَّت أيضًا
 *      (`Operations/equipment_quota` · `sites_board` · `swap_request`) **بلا
 *      `can_view` أصلًا** — فمنعُها صحيحٌ وتُترَك. وتسعٌ وعشرون شاشةً يعلنها
 *      `nav_items` للدورِ ولم تكن في الأساسِ **تُترَك لقرارِ المالك** — فتوسيعُ
 *      صلاحيةٍ بلا إقرارٍ من التصميمِ الجديدِ منحٌ زائدٌ لا إصلاح.
 *
 * ◆ **والرايةُ تُنقَل كما كانت**: `can_add/edit/delete` تُقرأ حيًّا من
 *   `role_permissions` لا تُسوَّى بـ«رؤيةٍ فقط» — فالاستعادةُ تُعيد المنحَ
 *   الأصليَّ بحرفِه، ولا تُنقِصه ولا تزيده.
 *
 * ⭐ **والدورةُ بالبابِ المحروس** (‏`PolicyWriteService` — لأنَّ النافذَ لا
 *   يُحرَّر): نسخٌ ⇒ بنودٌ ⇒ اعتمادٌ ⇒ تفعيلٌ ⇒ ترحيلُ الحاملين ⇒ تقاعدُ السابق.
 *   **والتجميدُ يُرفَع في نافذةِ صيانةٍ ويُعاد ويُقاس إغلاقُه.**
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

const ROLE_ID  = 12;
const APPROVER = 56;

/* ── مجموعةُ الاستعادة: منكسرةٌ في الفحصِ الحيِّ ∧ ممنوحةٌ في السجلِّ الأصلي ── */
$RESEED = array(
    'Clients/pricelists.php',
    'Clients/units_of_measure.php',
    'Clients/rate_books.php',
    'Clients/tenders.php',
    'Clients/readiness_lines.php',
    'Clients/activities.php',
    'Clients/contract_events.php',
    'Clients/contract_commitments.php',
    'Clients/contract_amendments.php',
    'Contracts/contract_obligations.php',
    'Contracts/contract_guarantees.php',
    'Contracts/contract_lines.php',
    'Contracts/contract_baseline.php',
    'Contracts/contract_monthly_plan.php',
    'Contracts/contract_payment_schedule.php',
    'Contracts/contract_resource_plan.php',
    'Contracts/contract_sites.php',
    'Contracts/price_terms.php',
    'Contracts/penalties.php',
    'Contracts/plan_actual_link.php',
    'Contracts/contract_coverage.php',
    'Contracts/unit_client_match.php',
    'Contracts/unit_statement_client.php',
    'Operations/unbilled.php',
    'Portal/business_models.php',
    'Reports/contract_report.php',
    'Reports/contractall.php',
);

$WHY  = 'استعادة شاشات دور المبيعات المسقطة في تحول القوالب — 2028_06_20';
$LOGF = $ROOT . '/database/migrations/.2028_06_20.out';
$buf  = "── استعادةُ شاشاتِ دورِ المبيعات (12) ──\n";
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
/* ◆ **والهجرةُ تُعلن ما فعلت على الشاشة** — لا في ملفٍّ وحدَه: عقدُ المُرحِّلِ
     يردُّ ملفًّا خرج بصفرٍ ولم يُخرِج حرفًا (‏«سكربتٌ قائمٌ بذاته يُعلن ما فعل»). */
$emit = function ($tail) use (&$buf, $LOGF) {
    $out = $buf . $tail;
    file_put_contents($LOGF, $out);
    echo $out;
};
$die = function ($m) use (&$buf, $restore, $emit) {
    $buf .= "  ✘ {$m}\n"; $restore();
    $emit("رسبت الهجرة — والبوابات أُعيدت\n"); exit(1);
};

/* ═══ ① القالبُ النافذُ الذي يحمله شاغلو الدور (لا «أحدثُ نسخةٍ» — بل المحمول) ═══ */
$held = array();
$r = $conn->query("SELECT DISTINCT g.profile_id
                     FROM gov_authority_grants g
                     JOIN users u ON u.id = g.user_id
                     JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                    WHERE u.role = " . ROLE_ID . " AND u.status = 'active' AND COALESCE(u.is_deleted,0) = 0
                      AND g.revoked_at IS NULL");
while ($r && ($x = $r->fetch_row())) { $held[] = (int) $x[0]; }
if (count($held) !== 1) { $die('شاغلو الدور على ' . count($held) . ' قالبًا نافذًا — يلزم قرارُ توحيدٍ أوّلًا'); }
$cur = $held[0];
$curCode = (string) $one("SELECT profile_code FROM gov_role_profiles WHERE profile_id={$cur}");
$curCnt  = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$cur} AND item_kind='screen' AND allow=1");
$log("① القالبُ المحمول = #{$cur} · {$curCode} · بنودٌ مسموحة={$curCnt}");

/* ═══ ② ما ينقص فعلًا (مُعاوَدة: المبذورُ سلفًا يُتخطّى) ═══ */
$add = array();
foreach ($RESEED as $code) {
    $e = $esc($code);
    $inTpl = (int) $one("SELECT COUNT(*) FROM gov_profile_items
                          WHERE profile_id={$cur} AND item_kind='screen' AND allow=1 AND item_ref={$e}");
    if ($inTpl > 0) { continue; }
    $rp = $conn->query("SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                          FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                         WHERE rp.role_id=" . ROLE_ID . " AND m.code={$e} LIMIT 1");
    $f = $rp ? $rp->fetch_assoc() : null;
    /* ⛔ الدليلُ الثاني شرطٌ لا زينة: بلا `can_view` في السجلِّ الأصليِّ لا تُبذَر */
    if (!$f || (int) $f['can_view'] !== 1) { $log("   ⚠ {$code}: بلا can_view في السجل الأصلي — تُتخطّى"); continue; }
    $add[$code] = $f;
}
$log('② ينقصه = ' . count($add) . ' من ' . count($RESEED) . ' مرشَّحة');
if (!$add) { $emit("مبذورٌ سلفًا — لا عمل\n"); exit(0); }

/* ═══ ③ إصدارٌ جديدٌ برمزٍ متسلسلٍ غيرِ مستعمَل ═══ */
$n = 1;
while ($one("SELECT profile_id FROM gov_role_profiles WHERE profile_code='TGT-R12-L3-S" . $n . "'")) { $n++; }
$code = 'TGT-R12-L3-S' . $n;
$r = P::cloneProfile($conn, $cur, $code, $WHY, APPROVER);
if (!$r['ok']) { $die('النسخُ فشل: ' . $r['msg']); }
$new = (int) $r['id'];
$log("③ نُسخ إلى #{$new} · {$code}");

/* ═══ ④ البنودُ بالرايةِ الأصليّةِ كما كانت ═══ */
$ca = 0;
foreach ($add as $codeRef => $f) {
    $x = P::setProfileItem($conn, $new, $codeRef, array(
        'allow'      => 1,
        'can_add'    => (int) $f['can_add'],
        'can_edit'   => (int) $f['can_edit'],
        'can_delete' => (int) $f['can_delete'],
    ), 'استعادة شاشة مسقطة في تحول القوالب — الراية من السجل الأصلي', APPROVER);
    if ($x['ok']) { $ca++; }
    elseif ($x['code'] !== 'NOTHING') { $die("إضافةُ «{$codeRef}» فشلت: " . $x['msg']); }
}
$newCnt = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$new} AND item_kind='screen' AND allow=1");
$log("④ أُضيف={$ca} · المسموحُ الآن={$newCnt} (كان {$curCnt})");

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$new}") === 'draft') {
    $x = P::approveProfile($conn, $new, 'اعتماد إصدار مستعيد لشاشات الدور المسقطة', APPROVER);
    if (!$x['ok'] && $x['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $x['msg']); }
}

/* ═══ ⑤ نافذةُ صيانةٍ: تفعيلٌ ثمَّ ترحيلُ الحاملين ثمَّ تقاعدُ السابق ═══ */
foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { continue; }
    $x = P::setFreeze($conn, $s, 0, 'نافذة صيانة لترحيل قالب دور المبيعات — ' . $WHY, APPROVER);
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
    if (!$x['ok']) { $die('الإسنادُ فشل: ' . $x['code'] . ' — ' . $x['msg']); }
}
$log('⑤ رُحِّل حاملون = ' . count($holders));
P::retireProfile($conn, $cur, 'حلَّ محلَّه ' . $code, APPROVER);

$restore();

/* ═══ ⑥ تحقُّقٌ ذاتيّ — والصفرُ لا يُقرأ نجاحًا حتى يُثبَت أنه مقيس ═══ */
$stillHeld = (int) $one("SELECT COUNT(*) FROM gov_authority_grants g JOIN users u ON u.id=g.user_id
                          WHERE u.role=" . ROLE_ID . " AND u.status='active' AND COALESCE(u.is_deleted,0)=0
                            AND g.revoked_at IS NULL AND g.profile_id={$new}");
$missing = 0;
foreach (array_keys($add) as $codeRef) {
    $missing += ((int) $one("SELECT COUNT(*) FROM gov_profile_items
                              WHERE profile_id={$new} AND item_kind='screen' AND allow=1 AND item_ref=" . $esc($codeRef)) === 0) ? 1 : 0;
}
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$fzW = count(array_filter($was));
$log("⑥ حاملون على الجديد={$stillHeld} (المتوقع " . count($holders) . ") · بنودٌ لم تُبذَر={$missing} · مجمَّدة={$fz} (المرجع {$fzW})");
if ($missing !== 0 || $stillHeld !== count($holders) || $fz !== $fzW || $newCnt !== $curCnt + $ca) {
    $emit("التحقُّقُ لم يطابق\n"); exit(1);
}
$emit("اكتملت الاستعادة\n");
exit(0);
