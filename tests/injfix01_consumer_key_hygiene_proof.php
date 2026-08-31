<?php
/**
 * tests/injfix01_consumer_key_hygiene_proof.php — GAP-77 · نظافةُ مفتاحِ المستهلك
 * ═══════════════════════════════════════════════════════════════════════════
 * الكشفُ `F-H14`: 8 مفاتيحَ نصوصًا بشريّةً من بذرِ UAT في `consumer_key` —
 * وعمودٌ كان خارجَ مقامِ GAP-09.
 *   ① صفرُ مفتاحٍ ملوَّثٍ في دفترِ التسليمات.
 *   ② المحجورُ مؤرشَفٌ بهويّتِه لا محذوفٌ صامتًا (سابقةُ GAP-09).
 *   ③ السالبُ بالكسر: إدراجُ مفتاحٍ بجملةٍ بشريّةٍ **تردُّه القاعدةُ نفسُها**
 *     (`chk_evdeliv_key_machine`) — قيدٌ بنيويٌّ لا فاحصٌ يُنسى.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "══ GAP-77 — مفتاحُ المستهلكِ آليٌّ لا جملةٌ بشريّة ══\n";

/* ── ① صفرُ ملوَّث ───────────────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries`
                    WHERE `consumer_key` LIKE '% %' OR `consumer_key` LIKE '%·%'");
$n = $q ? (int) $q->fetch_row()[0] : -1;
ok($n === 0, '① صفرُ مفتاحٍ ملوَّثٍ في دفترِ التسليمات — والمقيسُ عندَ الكشفِ 10 صفوف', "ملوَّث={$n}");

/* ── ② المحجورُ مؤرشَف ──────────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `ems_delivery_key_quarantine`");
$arch = $q ? (int) $q->fetch_row()[0] : -1;
ok($arch === 10, '② المحجورُ مؤرشَفٌ بهويّتِه في جدولِ الحجر — لا حذفَ صامتًا', "محجور={$arch}");
$q = $conn->query("SELECT COUNT(*) FROM `ems_delivery_key_quarantine` WHERE `seed_tag` = 'UAT-2026'");
ok($q && (int) $q->fetch_row()[0] === $arch, 'وكلُّه من بذرِ UAT — صفرُ صفِّ إنتاجٍ حُجر');

/* ── ③ السالبُ — القاعدةُ تعَضّ ──────────────────────────────────────────── */
$ins = $conn->query("INSERT INTO `ems_event_deliveries`
        (`consumer`, `consumer_key`, `event_id`, `outbox_id`, `state`, `attempts`)
    VALUES ('gov077_probe', 'جملة بشرية · تجربة', 1, 0, 'published', 1)");
$err = $conn->error;
ok($ins === false, '③ **القاعدةُ ردَّت المفتاحَ البشريَّ بنفسِها** — قيدٌ لا فاحص',
   $ins === false ? mb_substr($err, 0, 60) : '⛔ قُبل الإدراجُ');
if ($ins !== false) { $conn->query("DELETE FROM `ems_event_deliveries` WHERE `consumer` = 'gov077_probe'"); }
ok(stripos($err, 'chk_evdeliv_key_machine') !== false, 'والردُّ باسمِ قيدِه المسمّى');

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);

require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-77', true, 'مفتاحُ المستهلكِ آليٌّ — الملوَّثُ محجورٌ مؤرشَفٌ وقيدُ المخطَّطِ يمنع العودة', $fail);
exit($fail === 0 ? 0 : 1);
