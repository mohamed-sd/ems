<?php
/**
 * tests/navr_legacy_freeze_proof.php — §٢١ من أمرِ الحوكمةِ الموحَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * «تجميدُ كتّابِ سلطةِ الإرث» — `gov_target_nav` ساقطُ الصلاحيّةِ كهدفٍ
 * (RETIRING بخريطةِ المصادر) فلا يجوز أن ينمو خارجَ المصالحة: كلُّ صفٍّ فيه
 * يحمل حكمًا في `gov_legacy_nav_recon`، وأيُّ كاتبٍ قديمٍ يستيقظ يظهر فورًا
 * صفًّا بلا حكمٍ — وهو عينُ ما يقيسه المقياسُ الرسميُّ
 * `TARGET_DERIVED_FROM_CURRENT_WITHOUT_RULING` كلَّ تشغيلةِ قياس.
 *
 * ثلاثةُ فحوص:
 *   ① المقامُ يُطبع — صفوفُ الإرثِ كلُّها وأحكامُها متساويان عددًا وربطًا.
 *   ② صفرُ صفٍّ بلا حكمٍ (LEFT JOIN يخلو من فراغ).
 *   ③ السالبُ بالحقن — صفُّ مِجَسٍّ يُزرع في الإرثِ بلا حكمٍ
 *     ⇒ **عدّادُ بلا-حكمٍ يتحرّك بواحد** · ثم يُكنس ويُثبَت كنسُه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PROBE_ROUTE = 'tests/navr-g21-probe.php';
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
/** عدُّ صفوفِ الإرثِ التي لا حكمَ لها في سجلِّ المصالحة. */
function withoutRuling(mysqli $c)
{
    $q = $c->query("SELECT COUNT(*) FROM `gov_target_nav` t
                     LEFT JOIN `gov_legacy_nav_recon` r ON r.`gtn_id` = t.`id`
                     WHERE r.`id` IS NULL");
    return $q ? (int) $q->fetch_row()[0] : -1;
}

/* كنسُ المِجَسِّ مضمونٌ ولو انفجر فحص — والفشلُ يُسمّى */
register_shutdown_function(function () use ($conn, $PROBE_ROUTE) {
    $st = $conn->prepare("DELETE FROM `gov_target_nav` WHERE `route` = ?");
    $st->bind_param('s', $PROBE_ROUTE); $st->execute(); $st->close();
    $st = $conn->prepare("SELECT COUNT(*) FROM `gov_target_nav` WHERE `route` = ?");
    $st->bind_param('s', $PROBE_ROUTE); $st->execute();
    $left = (int) $st->get_result()->fetch_row()[0]; $st->close();
    if ($left !== 0) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ صفَّ {$PROBE_ROUTE} يدويًّا\n");
    }
});

echo "══ §٢١ — تجميدُ كتّابِ سلطةِ الإرث: لا نموَّ لِـgov_target_nav خارجَ المصالحة ══\n";

/* ── ① المقامُ والتساوي ─────────────────────────────────────────────────── */
$gtn = (int) $conn->query("SELECT COUNT(*) FROM `gov_target_nav`")->fetch_row()[0];
$rec = (int) $conn->query("SELECT COUNT(DISTINCT `gtn_id`) FROM `gov_legacy_nav_recon`")->fetch_row()[0];
ok($gtn > 0 && $gtn === $rec, '① المقامُ مطبوعٌ والسجلّان متساويان', "إرث {$gtn} = أحكام {$rec}");

/* ── ② صفرُ صفٍّ بلا حكم ────────────────────────────────────────────────── */
$before = withoutRuling($conn);
ok($before === 0, '② كلُّ صفِّ إرثٍ محكومٌ — بلا-حكمٍ = 0', "العدّاد {$before}");

/* ── ③ السالب: كاتبٌ قديمٌ يستيقظ يُرى فورًا ───────────────────────────── */
$cols = [];
$q = $conn->query("SHOW COLUMNS FROM `gov_target_nav`");
while ($r = $q->fetch_assoc()) { $cols[$r['Field']] = $r; }
$roleCol = isset($cols['role_id']) ? 'role_id' : null;
ok($roleCol !== null, '③أ بنيةُ الإرثِ كما توقّعها المِجَسّ', 'role_id موجود');
$st = $conn->prepare("INSERT INTO `gov_target_nav` (`role_id`, `route`) VALUES (0, ?)");
$st->bind_param('s', $PROBE_ROUTE);
$inserted = $st->execute(); $st->close();
ok($inserted, '③ب المِجَسُّ زُرع (محاكاةُ كاتبٍ قديم)', $inserted ? '' : $conn->error);
$after = withoutRuling($conn);
ok($after === $before + 1, '③ج العدّادُ تحرّك بواحدٍ — الاستيقاظُ مرئيٌّ لا صامت', "{$before} ⇒ {$after}");
$st = $conn->prepare("DELETE FROM `gov_target_nav` WHERE `route` = ?");
$st->bind_param('s', $PROBE_ROUTE); $st->execute();
$swept = ($st->affected_rows === 1); $st->close();
ok($swept, '③د المِجَسُّ كُنس وثبت كنسُه');
$final = withoutRuling($conn);
ok($final === 0, '③هـ العدّادُ عاد صفرًا بعد الكنس', "العدّاد {$final}");

echo "\n═ النتيجة: ✔ {$pass} · ✘ {$fail} ═\n";
exit($fail === 0 ? 0 : 1);
