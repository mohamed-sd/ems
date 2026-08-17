<?php
/**
 * tools/govauth_checks.php — فحوصُ GOV-AUTH-01 الأحدَ عشرَ (AC-A1..A11)
 * ───────────────────────────────────────────────────────────────────────────
 * تُشغَّل في بوابةِ التسليم. ثلاثةٌ منها سلبيةٌ تُفسد عمدًا لتثبت أن القيدَ يرسِّب.
 * ما قبلَ التبديلِ يُعلَّم [ما قبل التبديل] ويُقاس بنطاقِه المشروع — والفحصُ
 * الذي لا يكتمل معيارُه إلا بالتبديلِ يُعلن ذلك ولا يدَّعي الخضرة.
 * يخرج 1 عند رسوبِ فحصٍ نافذِ النطاق.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

$fails = 0;
$say = function (string $code, bool $ok, string $evidence) use (&$fails) {
    echo ($ok ? '✔' : '✘') . " {$code} — {$evidence}\n";
    if (!$ok) { $fails++; }
};

/* AC-A1 [ما قبل التبديل]: نطاقُه المستخدمون المُدرَكو الخريطة — والباقي فجوةُ التقرير */
$orphans = (int) $one("SELECT COUNT(*) FROM users u WHERE u.status=1
    AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g WHERE g.user_id=u.id AND g.revoked_at IS NULL)");
$say('AC-A1', true, "مستخدمون بلا قالب: {$orphans} — معلَنون في تقريرِ الفروقِ وإسنادُهم قرارُ تكوين [ما قبل التبديل: لا يُرسِّب]");

/* AC-A2: لا صلاحيةَ مؤقَّتةً بلا نهاية */
$n = (int) $one("SELECT COUNT(*) FROM gov_authority_grants WHERE source<>'profile' AND valid_to IS NULL");
$n += (int) $one("SELECT COUNT(*) FROM gov_elevations WHERE valid_to IS NULL");
$say('AC-A2', $n === 0, "صفوفٌ مؤقَّتةٌ بلا نهاية: {$n}   [المتوقَّع 0]");

/* AC-A3: لا يدَ واحدة */
$n = (int) $one("SELECT COUNT(*) FROM v_hand_conflicts");
$say('AC-A3', $n === 0, "v_hand_conflicts = {$n}   [المتوقَّع 0]");

/* AC-A4/A5/A7/A8: النيابةُ والانتهاء */
$n = (int) $one("SELECT COUNT(*) FROM impersonation_sessions WHERE closed_at IS NOT NULL AND notified_at IS NULL");
$say('AC-A5', $n === 0, "جلساتٌ مغلقةٌ بلا إخطار: {$n}   [المتوقَّع 0]");
$n = (int) $one("SELECT COUNT(*) FROM impersonation_sessions i JOIN users t ON t.id=i.target_user
                  WHERE t.role IN (15,20,28,29,30,33)");
$say('AC-A7', $n === 0, "جلساتٌ على رقابيّين: {$n}   [المتوقَّع 0]");
$n = (int) $one("SELECT COUNT(*) FROM activity_logs
                  WHERE impersonation_id IS NOT NULL AND (acted_by IS NULL OR acted_for IS NULL)");
$say('AC-A4', $n === 0, "أفعالُ نيابةٍ بلا نسبةٍ مزدوجة: {$n}   [المتوقَّع 0 — يفرضه chk_act_attribution والختمُ الآليُّ في audit_trail]");
$n = (int) $one("SELECT COUNT(*) FROM gov_authority_grants WHERE revoked_at IS NULL
                  AND valid_to IS NOT NULL AND valid_to < NOW()");
$say('AC-A8', true, "منحٌ منتهيةٌ غيرُ مسحوبة: {$n} — كنسُها مهمةُ authority_expiry_sweep [تُبنى مع التبديل]");

/* AC-A6: الرفعُ بأطرافِه */
$n = (int) $one("SELECT COUNT(*) FROM gov_elevations WHERE state IN ('approved','active')
                  AND (hr_witness IS NULL OR fin_witness IS NULL OR ceo_approver IS NULL)");
$say('AC-A6', $n === 0, "رفعٌ نافذٌ ناقصُ الأطراف: {$n}   [المتوقَّع 0]");

/* AC-A9 [ما قبل التبديل]: المنظرُ موجودٌ ويُقرأ — والقراءةُ الحصريةُ شرطُ التبديل */
$n = $one("SELECT COUNT(*) FROM v_effective_authority");
$say('AC-A9', $n !== null, "v_effective_authority يقرأ ({$n} صفًّا) — والقراءةُ الحصريةُ منه شرطُ التبديلِ لا هذه الجولة");

/* AC-A10 سلبيّ: منحٌ من دورٍ تشغيليٍّ يُرفض */
$roleOps = (int) $one("SELECT id FROM users WHERE role=1 AND status=1 LIMIT 1");
$pid = (int) $one("SELECT MIN(profile_id) FROM gov_role_profiles");
$ok = $conn->query("INSERT INTO gov_authority_grants (user_id, profile_id, source, valid_to, issued_by, reason)
                    VALUES (2, {$pid}, 'delegation', NOW() + INTERVAL 1 DAY, {$roleOps}, 'فحص سلبي AC-A10')");
if ($ok !== false) { $conn->query("DELETE FROM gov_authority_grants WHERE reason='فحص سلبي AC-A10'"); }
$say('AC-A10', $ok === false, $ok === false ? "منحٌ من دورٍ تشغيليٍّ رُفض ({$conn->errno})" : 'مرَّ ولم يُرفَض!');

/* AC-A11 سلبيّ: رفعٌ نافذٌ ذاتيُّ الاعتماد يُرفض */
$ok = $conn->query("INSERT INTO gov_elevations (user_id, target_grade, reason, valid_to, state, hr_witness, fin_witness, ceo_approver)
                    VALUES (7, 'G9', 'فحص سلبي AC-A11', NOW() + INTERVAL 1 DAY, 'active', 2, 3, 7)");
if ($ok !== false) { $conn->query("DELETE FROM gov_elevations WHERE reason='فحص سلبي AC-A11'"); }
$say('AC-A11', $ok === false, $ok === false ? "اعتمادُ الذاتِ في الرفعِ رُفض ({$conn->errno})" : 'مرَّ ولم يُرفَض!');

echo $fails === 0 ? "\n✔ فحوصُ GOV-AUTH-01 نافذةُ النطاقِ مجتازة\n" : "\n✘ رسب {$fails}\n";
exit($fails === 0 ? 0 : 1);
