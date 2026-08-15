<?php
/**
 * 2027_04_16_readiness_certificate_ref.php
 * ═══════════════════════════════════════════════════════════════════════════
 * شهادةُ الجاهزيةِ تُخزَّن لا تُكتب في سجلٍّ ثم تُنسى — ⇐ INJ-0074
 *
 * نصُّ القبول: «كلُّ معدةٍ في لوحة الجاهزية تعرض **مرجعَ آخر شهادةِ جاهزيةٍ
 * وتاريخَها ومُصدرَها**، والنقرُ عليه يفتح الأمرَ الذي أصدرها».
 *
 * ── المقيس ───────────────────────────────────────────────────────────────
 * `Maintenance/return_to_service.php` **يشترط** شهادةَ الجاهزيةِ ويرفض بدونها
 * (422)، ثم يكتب نصَّها في **سجلِّ التدقيقِ وحدَه** — فلا عمودَ يحملها ولا
 * سبيلَ لعرضِها. فالمستندُ يُطلَب ولا يُخزَّن: **شرطٌ يُستوفى ثم يضيع**.
 *
 * ── وليست دورةَ مستندٍ جديدة ─────────────────────────────────────────────
 * `mnt_order` يحمل سلفًا `closed_at` و`closed_by` — أي **تاريخَ الشهادةِ
 * ومُصدرَها**. الناقصُ **مرجعُها** وحدَه. فالإصلاحُ عمودٌ واحدٌ يحفظ ما يُجمَع
 * فعلًا، لا بناءُ مستندٍ بدورةِ اعتمادٍ جديدة.
 *
 * ◆ ولا يُردَم أثرًا رجعيًّا: الأوامرُ المقفلةُ قبلَ اليومِ شهاداتُها في سجلِّ
 *   التدقيق، ولا تُنسخ إلى العمودِ بادّعاءِ أنَّها كُتبت فيه.
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
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ شهادةُ الجاهزيةِ تُخزَّن ══\n\n";
$r = $conn->query("SHOW COLUMNS FROM mnt_order LIKE 'readiness_cert_ref'");
if ($r && $r->num_rows > 0) {
    echo "  · mnt_order.readiness_cert_ref قائمٌ سلفًا\n";
} elseif ($conn->query("ALTER TABLE mnt_order
        ADD COLUMN `readiness_cert_ref` VARCHAR(190) NULL
        COMMENT 'INJ-0074: مرجعُ شهادةِ الجاهزيةِ الفنية — وتاريخُها ومُصدرُها في closed_at/closed_by'")) {
    echo "  ✔ mnt_order.readiness_cert_ref — والتاريخُ والمُصدرُ من closed_at/closed_by\n";
} else {
    exit("  ✘ تعذّرت الإضافة: {$conn->error}\n");
}

$r = $conn->query("SELECT COUNT(*) FROM mnt_order WHERE state = 'Closed'");
echo '  أوامرُ مقفلةٌ قائمة: ' . ($r ? $r->fetch_row()[0] : '?')
   . " (شهاداتُها في سجلِّ التدقيقِ — لا تُنسخ بادّعاء)\n";
echo "\n✔ تمّت\n";
