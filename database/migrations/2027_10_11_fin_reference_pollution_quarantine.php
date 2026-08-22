<?php
/**
 * 2027_10_11_fin_reference_pollution_quarantine.php
 *   حجرُ صفوفِ التلوّثِ في مرجعيَّتَين ماليتَين — INJ-EXEC-01 §11
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الكشف**: الفاحصُ العكسيُّ u12 أعلن فرقَين عدديَّين — الإشاراتُ «16 في
 *   الوثيقةِ · 20 حيّةً» وأنواعُ العقودِ «18 · 20». والفرقُ **ليس تقادمَ وثيقةٍ**
 *   كما بدا أوّلًا: الصفوفُ الستةُ الزائدةُ تحمل **أسماءَ أشخاص** في حقلِ اسمِ
 *   القاعدةِ ورموزًا مشوَّهةً (`FIN_-00017`..`FIN_-00020`) وعباراتِ حشوٍ من
 *   عائلةِ `UAT-2026` في حقولِ الحساباتِ وقواعدِ الترحيل.
 *
 * ◆ **وهي حيّةٌ لا خاملة**:
 *   · `app/Services/Finance/FinAnalysisService.php:204` يقرأ قواعدَ الإشاراتِ
 *     بـ`active = 1` ⇒ **محركُ الإنذارِ المبكرِ يستهلك الأربعةَ الآن**،
 *     بعتباتٍ فارغةٍ ورموزِ نسبٍ لا وجودَ لها، وبخطورةٍ «حرج» و«مرتفع».
 *   · `Finance/fin_early_warning.php:25` يعرضها للمستخدمِ بلا ترشيحِ `active`.
 *   · وصفَّا أنواعِ العقودِ `capitalizes = 1` و`accounts_csv` **جملةٌ عربيةٌ لا
 *     أكوادَ حسابات** — ولو مرَّ رمزُهما في مسارِ الترحيلِ لأنتج حسابًا باطلًا.
 *
 * ◆ **والسجلُّ كان يعرفها ولم يفعل**: `gov_pollution_findings` رصدها في
 *   2026-08-18 بحكمِ `SUSPECT` و`action_taken = NONE` — **والرصدُ بلا فعلٍ
 *   ليس حمايةً**. فيُحسم الحكمُ هنا إلى `TEST_POLLUTION` ويُسجَّل ما فُعل.
 *
 * ◆ **ولا حذفَ مدمّر**: الحجرُ بـ`active = 0` لا بحذفِ الصفّ — فالأثرُ
 *   التدقيقيُّ يبقى ومَن أدخلها يبقى مقروءًا، والرجوعُ يعيدها كما كانت.
 *
 * التشغيل:  php database/migrations/2027_10_11_fin_reference_pollution_quarantine.php
 * الرجوع :  php database/migrations/2027_10_11_fin_reference_pollution_quarantine.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ◆ **المطابقةُ بالنمطِ لا بالمعرِّف**: الأرقامُ تختلف بين النسخ، والنمطُ
 *   `FIN_-000%` هو بصمةُ المولِّدِ نفسِها. و`_` محرفُ بدلٍ في LIKE فيُهرَّب. */
$PAT = 'FIN\\_-000%';
$ACT = 'QUARANTINED_2027_10_11 — active=0 · لا حذف';

if (in_array('--revert', $argv, true)) {
    $n1 = 0; $n2 = 0;
    $st = $conn->prepare("UPDATE `fin_signal_rules` SET `active` = 1 WHERE `signal_code` LIKE ?");
    $st->bind_param('s', $PAT); $st->execute(); $n1 = $st->affected_rows; $st->close();
    $st = $conn->prepare("UPDATE `fin_contract_types` SET `active` = 1 WHERE `type_code` LIKE ?");
    $st->bind_param('s', $PAT); $st->execute(); $n2 = $st->affected_rows; $st->close();
    $conn->query("UPDATE `gov_pollution_findings` SET `verdict` = 'SUSPECT', `action_taken` = 'NONE'
                   WHERE `table_name` IN ('fin_signal_rules','fin_contract_types')");
    printf("↺ أُعيد التفعيل: إشارات=%d · أنواعُ عقود=%d · وأُعيد حكمُ السجلِّ إلى SUSPECT (%d)\n",
           $n1, $n2, $conn->affected_rows);
    exit(0);
}

/* ── ① ما قبلَ الحجر: يُعرَض ما سيُمَسُّ بالاسمِ لا بالعدد ────────────────── */
echo "① الصفوفُ المرشَّحةُ للحجرِ — تُعرَض قبلَ أن تُمَسّ:\n";
$targets = 0;
foreach (array(
    array('fin_signal_rules',   'signal_code', 'name_ar'),
    array('fin_contract_types', 'type_code',   'name_ar'),
) as $t) {
    $st = $conn->prepare("SELECT `id`, `{$t[1]}`, `{$t[2]}`, `active`
                            FROM `{$t[0]}` WHERE `{$t[1]}` LIKE ? ORDER BY `id`");
    $st->bind_param('s', $PAT); $st->execute();
    $res = $st->get_result();
    while ($res && $x = $res->fetch_assoc()) {
        printf("   %-20s #%-4s %-14s %-28s نشط=%s\n",
               $t[0], $x['id'], $x[$t[1]], mb_substr((string) $x[$t[2]], 0, 26), $x['active']);
        $targets++;
    }
    $st->close();
}
if ($targets === 0) { echo "   (لا صفَّ مطابقٌ للنمط — لا شيءَ يُفعَل)\n"; }

/* ── ② حارسٌ قبلَ المسّ: لا يُحجَر صفٌّ سليمُ الرمز ────────────────────────
 * ◆ لو أخطأ النمطُ فالتقط رمزًا معياريًّا (`FS-`/`EC-`/`FC-`) لتوقّفت الهجرة —
 *   فالحجرُ فعلٌ على مرجعيةٍ ماليةٍ لا يُجرَّب على العمياء. */
$bad = 0;
foreach (array(array('fin_signal_rules', 'signal_code'), array('fin_contract_types', 'type_code')) as $t) {
    $st = $conn->prepare("SELECT COUNT(*) FROM `{$t[0]}`
                           WHERE `{$t[1]}` LIKE ?
                             AND (`{$t[1]}` LIKE 'FS-%' OR `{$t[1]}` LIKE 'EC-%' OR `{$t[1]}` LIKE 'FC-%')");
    $st->bind_param('s', $PAT); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    $bad += (int) $n;
}
if ($bad > 0) {
    exit("\n⛔ توقّف: النمطُ التقط {$bad} صفًّا برمزٍ معياريّ — لا يُحجَر تعريفٌ سليم\n");
}
echo "② حارسُ النمط: **صفرُ رمزٍ معياريٍّ في المصيدة** — الحجرُ آمن\n";

/* ── ③ الحجرُ — بلا حذف ───────────────────────────────────────────────── */
$st = $conn->prepare("UPDATE `fin_signal_rules` SET `active` = 0 WHERE `signal_code` LIKE ? AND `active` = 1");
$st->bind_param('s', $PAT); $st->execute(); $q1 = $st->affected_rows; $st->close();
$st = $conn->prepare("UPDATE `fin_contract_types` SET `active` = 0 WHERE `type_code` LIKE ? AND `active` = 1");
$st->bind_param('s', $PAT); $st->execute(); $q2 = $st->affected_rows; $st->close();
printf("③ حُجِر: قواعدُ إشارة=%d · أنواعُ عقود=%d — **بلا حذفِ صفٍّ واحد**\n", $q1, $q2);

/* ── ④ السجلُّ يُحسم: من «مشتبَه بلا فعل» إلى «تلوّثُ اختبارٍ وقد فُعل» ──── */
$st = $conn->prepare("UPDATE `gov_pollution_findings`
                         SET `verdict` = 'TEST_POLLUTION', `action_taken` = ?
                       WHERE `table_name` IN ('fin_signal_rules','fin_contract_types')");
$st->bind_param('s', $ACT); $st->execute();
printf("④ حُسم في سجلِّ التلوّث: %d صفَّ رصدٍ — TEST_POLLUTION · %s\n", $st->affected_rows, $ACT);
$st->close();

/* ── ⑤ ما بعدَ الحجر: التعريفاتُ النشطةُ تطابق الوثيقةَ ────────────────── */
$q = $conn->query("SELECT (SELECT COUNT(*) FROM `fin_signal_rules`   WHERE `active` = 1),
                          (SELECT COUNT(*) FROM `fin_contract_types` WHERE `active` = 1)");
list($sig, $ct) = $q ? $q->fetch_row() : array(-1, -1);
printf("⑤ النشطُ بعدَ الحجر: إشارات=%s (الوثيقة 16 %s) · أنواعُ عقود=%s (الوثيقة 18 %s)\n",
       $sig, ((int) $sig === 16 ? '✔' : '✘'), $ct, ((int) $ct === 18 ? '✔' : '✘'));
echo "   ◆ والمحجورُ باقٍ في الجدولِ بأثرِه التدقيقيِّ — يُقرأ ولا يُستهلَك.\n";

ems_migration_recorded(__FILE__, $conn, 0);
