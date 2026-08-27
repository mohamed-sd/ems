<?php
/**
 * tests/w135_expired_delegation_denied.php — التفويضُ المنتهي لا يمنح سلطة
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-27 · G5**: «مع اختبارٍ سلبيٍّ على الأقلِّ لـ: `Direct
 * URL` · `Unauthorized Action` · `SoD` · **`Expired Delegation`**».
 * وثلاثةٌ منها لها شواهدُ قائمةٌ، والرابعُ لم يكن له — فهذا هو.
 *
 * **وأمرُ المالكِ الأوّلُ · البند 33**: «كلُّ `Delegation` … **وينتهي تلقائيًّا**.
 * ولا يتحوّل التفويضُ إلى تغييرٍ دائمٍ في `Role`.»
 *
 * ◆ **والاختبارُ يزرع ويقيس ويكنس** — لا يقرأ صفًّا قائمًا ويحكم. فالجدولُ خالٍ
 *   اليومَ (صفرُ صفٍّ)، **وشاهدٌ يقرأ خلاءً يُخضِرُّ على تطابقِ لا شيء**.
 *
 * ◆ **وأربعُ زوايا لا زاويةٌ واحدة**: منتهٍ بالتاريخ · لم يبدأ بعد · ملغًى
 *   بحالتِه · وحيٌّ صحيح. فلو ردَّ الحارسُ **كلَّ** شيءٍ لبدا يقظًا وهو أعمى —
 *   والزاويةُ الرابعةُ هي التي تفرّق بين حارسٍ وجدارٍ مصمت.
 *
 * التشغيل: php tests/w135_expired_delegation_denied.php
 * الخروج : 0 الحارسُ يقظ · 1 ثغرة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Work/WorkItemService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

use App\Services\Work\WorkItemService as W;

$MARK = 'W135DLG';
$ok = 0; $bad = 0;
$chk = function ($cond, $label, $detail = '') use (&$ok, &$bad) {
    if ($cond) { $ok++; echo "  ✔ $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
    else       { $bad++; echo "  ✘ $label" . ($detail !== '' ? " — $detail" : '') . "\n"; }
};

echo "══ G5 · التفويضُ المنتهي لا يمنح سلطة ══\n";

/* ═══ ⓪ كنسٌ قبلَ الزرعِ لا بعدَه وحدَه ═══════════════════════════════════
     ⛔ فجولةٌ سابقةٌ ماتت في منتصفِها تترك صفوفًا تُفسد هذه. */
$conn->query("DELETE FROM work_delegations WHERE scope_ref LIKE '$MARK%'");

/* ═══ ① زرعُ أربعِ حالاتٍ ═══════════════════════════════════════════════ */
$CASES = array(
    array('EXPIRED',   'منتهٍ بالتاريخ',      "DATE_SUB(NOW(), INTERVAL 30 DAY)", "DATE_SUB(NOW(), INTERVAL 1 DAY)",  'active',  false),
    array('FUTURE',    'لم يبدأ بعد',         "DATE_ADD(NOW(), INTERVAL 5 DAY)",  "DATE_ADD(NOW(), INTERVAL 30 DAY)", 'active',  false),
    array('REVOKED',   'ملغًى بحالتِه',        "DATE_SUB(NOW(), INTERVAL 5 DAY)",  "DATE_ADD(NOW(), INTERVAL 30 DAY)", 'revoked', false),
    array('LIVE',      'حيٌّ صحيحٌ في مدّتِه',  "DATE_SUB(NOW(), INTERVAL 5 DAY)",  "DATE_ADD(NOW(), INTERVAL 30 DAY)", 'active',  true),
);
$co = (int) ($conn->query("SELECT MIN(company_id) FROM work_delegations_seed_archive")->fetch_row()[0] ?: 1);
$seeded = 0;
foreach ($CASES as $c) {
    $ref = $MARK . '-' . $c[0];
    $sql = "INSERT INTO work_delegations
              (company_id, kind, from_user_id, to_user_id, scope_ref, starts_at, ends_at, status, created_by, created_at)
            VALUES ($co, 'authority', 1, 2, '" . $conn->real_escape_string($ref) . "',
                    {$c[2]}, {$c[3]}, '" . $conn->real_escape_string($c[4]) . "', 1, NOW())";
    if ($conn->query($sql) === true) { $seeded++; }
    else { echo "  ⚠ تعذّر زرعُ {$c[0]}: " . $conn->error . "\n"; }
}
$chk($seeded === 4, 'زُرعت أربعُ حالاتٍ للقياس', "$seeded من 4");
if ($seeded !== 4) {
    $conn->query("DELETE FROM work_delegations WHERE scope_ref LIKE '$MARK%'");
    echo "\nالنتيجة: تعذّر الزرعُ — لا حكمَ على حارسٍ لم يُختبَر ✘\n";
    exit(1);
}

/* ═══ ② القياس — كلُّ زاويةٍ على حِدة ═══════════════════════════════════ */
foreach ($CASES as $c) {
    $ref  = $MARK . '-' . $c[0];
    $live = W::isDelegationLive($conn, $ref);
    if ($c[5]) {
        $chk($live === true, "الحيُّ الصحيحُ **يُقبَل** — {$c[1]}", 'وإلّا فالحارسُ جدارٌ مصمتٌ لا حارس');
    } else {
        $chk($live === false, "**يُردّ** — {$c[1]}", 'والقبولُ هنا ثغرةُ سلطة');
    }
}

/* ═══ ③ ولا يتحوّل التفويضُ إلى تغييرٍ دائمٍ في الدور (البند 33) ═════════ */
$roleTouched = (int) $conn->query("SELECT COUNT(*) FROM users u
    JOIN work_delegations d ON d.to_user_id = u.id
   WHERE d.scope_ref LIKE '$MARK%' AND u.role_id IS NOT NULL
     AND u.updated_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)")->fetch_row()[0];
$chk($roleTouched === 0, 'التفويضُ لم يمسَّ دورَ المُفوَّضِ إليه', "دورٌ تغيّر $roleTouched");

/* ═══ ④ الكنسُ ثمَّ إثباتُ النظافةِ بالقياسِ لا بالنيّة ═══════════════════ */
$conn->query("DELETE FROM work_delegations WHERE scope_ref LIKE '$MARK%'");
$left = (int) $conn->query("SELECT COUNT(*) FROM work_delegations WHERE scope_ref LIKE '$MARK%'")->fetch_row()[0];
$chk($left === 0, 'كُنس أثرُ الشاهدِ من الجدولِ الحيّ', "باقٍ $left");

echo "\n──────────────────────────────────────────────────────────────\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
echo ($bad === 0
    ? "الحكم: التفويضُ المنتهي لا يمنح سلطةً — والحيُّ يمنحها ✔\n"
    : "الحكم: ثغرةٌ في سريانِ التفويض ✘\n");
exit($bad === 0 ? 0 : 1);
