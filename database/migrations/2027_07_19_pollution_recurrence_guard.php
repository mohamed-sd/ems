<?php
/**
 * 2027_07_19_pollution_recurrence_guard.php — الخطوةُ ⑤: قيدٌ يمنع عودتَه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (2026-08-19 · سادسًا) — الخطوةُ الخامسةُ لكلِّ قيمة:
 *   «**ثم قيدٌ يمنع عودتَه**».
 *
 * ◆ والعائقُ الذي أخّرها: قيدُ `CHECK` يُفحص على **كلِّ الصفوف** لحظةَ إضافتِه،
 *   فرُفض فعلًا على `deduction_types` **لأن الصفوفَ الملوَّثةَ قائمة**. فصار
 *   الترتيبُ يبدو: نظِّفْ أولًا ثم قيِّد — والتنظيفُ يلزمه مصدرٌ حاكمٌ لا يوجد.
 *
 * ◆ **والمخرَجُ قادحُ إدراجٍ لا قيدُ جدول**: `BEFORE INSERT` يحكم **الوارِدَ
 *   الجديدَ وحدَه** ولا يمسُّ صفًّا قائمًا. فالعودةُ تُمنع اليومَ، والقائمُ
 *   يبقى معزولًا في سجلِّه حتى يقرِّر المالكُ مصيرَه. وهذا هو المعنى الدقيقُ
 *   لـ«قيدٌ يمنع **عودتَه**» — لا «قيدٌ يحذف ما مضى».
 *
 * ◆ **والنطاقُ المجالاتُ المحميةُ أولًا** (14 عمودًا في 14 جدولًا): القراراتُ
 *   الماليةُ · الالتزاماتُ القانونيةُ · سلاليمُ الاعتمادِ · السقوفُ المالية.
 *   لأن جملةً بشريةً في `entity_type` هناك تُفسِد مسارَ اعتمادٍ أو قيدًا ماليًّا،
 *   لا مجرَّدَ عرضٍ. وما عداها يُعلَن ويُترك لقرارِ التوسعة.
 *
 * ◆ **والشرطُ يُقاس لا يُوصف**: قيمةٌ فيها فراغٌ **و**حرفٌ عربيٌّ في خانةِ رمزٍ
 *   أو تعداد. والرموزُ المشروعةُ لا فراغَ فيها ولا عربية.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ── ① فصلُ «لا نعرف» عن «نعرف وننتظر قرارَك» ───────────────────────────── */
$conn->query("UPDATE gov_test_data_isolation
                 SET resolution = 'PENDING_OWNER'
               WHERE source_ref LIKE 'UAT-%' AND resolution = 'PENDING_SOURCE'");
$toOwner = $conn->affected_rows;

/* ── ② قادحُ إدراجٍ لكلِّ عمودٍ في مجالٍ محميّ ───────────────────────────── */
$targets = array();
$r = $conn->query("SELECT DISTINCT table_name, column_name, policy_domain
                     FROM gov_test_data_isolation
                    WHERE policy_domain <> 'OPERATIONAL_DATA'
                    ORDER BY table_name");
while ($r && ($x = $r->fetch_assoc())) { $targets[] = $x; }

$made = 0; $failed = array();
foreach ($targets as $t) {
    $tbl = $t['table_name']; $col = $t['column_name'];
    if (!preg_match('~^[A-Za-z0-9_]+$~', $tbl) || !preg_match('~^[A-Za-z0-9_]+$~', $col)) { continue; }
    $trg = 'trg_nopollute_' . substr(md5($tbl . '.' . $col), 0, 16);
    $conn->query("DROP TRIGGER IF EXISTS `{$trg}`");
    $msg = 'قيمةٌ نصيةٌ بشريةٌ في خانةِ رمزٍ أو تعداد — ممنوعةٌ بقيد (جردُ التلوث · مجالٌ محميّ)';
    $ok = $conn->query("CREATE TRIGGER `{$trg}` BEFORE INSERT ON `{$tbl}` FOR EACH ROW
        BEGIN
            IF NEW.`{$col}` IS NOT NULL
               AND NEW.`{$col}` LIKE '% %'
               AND NEW.`{$col}` REGEXP '[\u0600-\u06FF]' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}';
            END IF;
        END");
    if ($ok) { $made++; } else { $failed[] = "{$tbl}.{$col}: " . $conn->error; }
}

echo "════ الخطوةُ ⑤ — قيدٌ يمنع العودة ════\n";
echo "  فُصل «نعرفُه اختبارًا وننتظر قرارَك»: {$toOwner} صفًّا ⇐ PENDING_OWNER\n";
echo "  قوادحُ إدراجٍ في المجالاتِ المحمية: {$made} من " . count($targets) . "\n";
foreach ($failed as $f) { echo "  ⚠ {$f}\n"; }
$byRes = $conn->query("SELECT resolution, COUNT(*) c FROM gov_test_data_isolation GROUP BY resolution");
while ($byRes && ($x = $byRes->fetch_assoc())) { echo "    · {$x['resolution']}: {$x['c']}\n"; }
echo "◆ القادحُ يحكم الوارِدَ الجديدَ وحدَه — والقائمُ معزولٌ لا محذوف\n";
