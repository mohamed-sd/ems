<?php
/**
 * 2027_08_28_event_rulings_decide.php
 *   الحكمُ على الأنواعِ الثمانيةِ والخمسين — INJ-FIX-01 · GAP-05 · GAP-06
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القرارُ مُتَّخَذٌ بتفويضٍ صريحٍ من المالك (2026-08-21)**، ومبناهُ قياسٌ لا رأي.
 *
 * ◆ **المعيارُ الذي يفصل**: «حدثُ أعمالٍ له مستهلكٌ **فعليٌّ بأثرٍ مقيس** ·
 *   أو حدثُ تدقيقٍ **معلَنٌ رسميًّا** لا يحتاج مستهلكًا».
 *   والمستهلكُ الفعليُّ في هذا النظامِ **مستهلكان لا غير**: `finance` و
 *   `finance_routing` المسجَّلان بـ`register()` في `cron_events.php`. وكلاهما
 *   يقرأ من `fin_financial_events`.
 *   ⇐ فالنوعُ الذي **يبلغ الإسقاطَ الماليَّ** له مستهلكٌ بأثرٍ مقيس ⇒ `business`.
 *     والذي **لا يبلغه** يُكتب في الجذرِ ولا يقرؤه أحد ⇒ `audit`.
 *
 * ◆ **ولماذا هذا إعلانٌ لا تنازل**: البديلُ أن تبقى سبعةٌ وأربعون نوعًا «بلا
 *   حكم» — تُقرأ في الوثائقِ ناقلًا وهي في العملِ سجلّ. **والمرفوضُ في نصِّ
 *   البرنامجِ هو بقاؤها بلا إعلان**، لا كونُها تدقيقًا. فالإعلانُ يجعل الوثيقةَ
 *   تصف ما يجري.
 *
 * ◆ **والحكمُ قابلٌ للنقض بدليل**: من بنى لنوعٍ مستهلكَ أعمالٍ بأثرٍ مقيسٍ
 *   نقل حكمَه إلى `business` — والسجلُّ يحمل `decided_at` و`decided_by` فيُعرف
 *   متى تغيّر ولماذا. **فالتدقيقُ حكمٌ لا مقبرة.**
 *
 * ◆ **ولا يُقرأ هذا إغلاقًا للأحداثِ كمجال**: يبقى `ems_event_subscriptions`
 *   **بلا قارئٍ في الإنتاج** (91 اشتراكًا لا يُنفِّذها شيء) — وذاك عيبٌ آخرُ
 *   يُغلق بوصلِ قارئٍ في الموجةِ ج، لا بحكمٍ على نوع.
 *
 * التشغيل:  php database/migrations/2027_08_28_event_rulings_decide.php
 * الرجوع :  php database/migrations/2027_08_28_event_rulings_decide.php --revert
 * الشاهد :  php tests/injfix01_event_ruling_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$BY = 'INJ-FIX-01 · تفويضُ المالك 2026-08-21';

if (in_array('--revert', $argv, true)) {
    $conn->query("UPDATE `gov_event_rulings`
                     SET `ruling` = NULL, `reason` = NULL, `decided_at` = NULL, `decided_by` = NULL
                   WHERE `decided_by` = '" . $conn->real_escape_string($BY) . "'");
    echo "↺ رُفع الحكمُ عن {$conn->affected_rows} نوعًا\n";
    exit(0);
}

/* ══ ① حدثُ أعمال: يبلغ الإسقاطَ الماليَّ فيقرؤه مستهلكٌ مسجَّل ══════════ */
$rBiz = 'حدثُ أعمال: يبلغ `fin_financial_events` فيقرؤه المستهلكان المسجَّلان '
      . '(`finance` و`finance_routing`) بأثرٍ ماليٍّ مقيس.';
$st = $conn->prepare(
    "UPDATE `gov_event_rulings`
        SET `ruling` = 'business', `reason` = ?, `decided_at` = NOW(), `decided_by` = ?
      WHERE `in_projection` = 1 AND `ruling` IS NULL");
$st->bind_param('ss', $rBiz, $BY);
$st->execute();
$biz = $st->affected_rows;
$st->close();
echo "① حدثُ أعمال: {$biz} نوعًا\n";

/* ══ ② حدثُ تدقيق: يُكتب في الجذرِ ولا يبلغ الإسقاطَ فلا يقرؤه مستهلك ═════ */
$rAud = 'حدثُ تدقيقٍ معلَنٌ رسميًّا: يُكتب في `ems_business_events` ولا يبلغ الإسقاطَ '
      . 'الماليّ، فلا مستهلكَ مسجَّلًا يقرؤه. يُحفظ للتدقيقِ وإعادةِ البناء ولا يحتاج '
      . 'مستهلكًا. ويُنقل إلى `business` متى بُني له مستهلكٌ بأثرٍ مقيس.';
$st = $conn->prepare(
    "UPDATE `gov_event_rulings`
        SET `ruling` = 'audit', `reason` = ?, `decided_at` = NOW(), `decided_by` = ?
      WHERE `in_projection` = 0 AND `ruling` IS NULL");
$st->bind_param('ss', $rAud, $BY);
$st->execute();
$aud = $st->affected_rows;
$st->close();
echo "② حدثُ تدقيق: {$aud} نوعًا\n";

/* ══ ③ استيثاق ═══════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$r = $conn->query("SELECT `ruling`, COUNT(*) n FROM `gov_event_rulings` GROUP BY `ruling`");
while ($r && $x = $r->fetch_assoc()) {
    printf("  %-10s %s\n", $x['ruling'] === null ? '(بلا حكم)' : $x['ruling'], $x['n']);
}
$r = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `ruling` IS NULL");
$left = $r ? (int) $r->fetch_row()[0] : -1;
echo "بلا حكمٍ بعد: {$left}\n";
echo "◆ و`ems_event_subscriptions` ما يزال بلا قارئٍ في الإنتاج — عيبٌ آخرُ لا يُغلقه هذا.\n";
echo "الشاهد: php tests/injfix01_event_ruling_proof.php\n";
