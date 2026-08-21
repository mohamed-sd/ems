<?php
/**
 * tests/injfix01_orphan_delivery_proof.php — INJ-FIX-01 · GAP-08
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ ثلاثة**: تتبُّعٌ زمنيٌّ للجذورِ المفقودة · وسببٌ مكتوبٌ لكلِّ حالة ·
 *   **وقيدٌ يمنع التكرار**. والثالثُ **يُجرَّب حيًّا** — فقيدٌ يُعلَن ولا يُجرَّب
 *   توثيقٌ لا قيد.
 *
 * ◆ ويُنظَّف أثرُ الفحصِ في كلِّ الأحوال — «حزامٌ سلبيٌّ لا يكنس نفسَه يُلوِّث المقام».
 *
 * التشغيل: php tests/injfix01_orphan_delivery_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① صفرُ يتيمٍ حيّ ══════════════════════════════════════════════════════ */
echo "══ ① صفرُ تسليمٍ يتيمٍ في الجدولِ الحيّ ══\n";
$q = $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries` d
                    LEFT JOIN `ems_business_events` e ON e.`id` = d.`event_id`
                    WHERE d.`event_id` IS NOT NULL AND e.`id` IS NULL");
$live = $q ? (int) $q->fetch_row()[0] : -1;
chk($live === 0, "تسليماتٌ بلا جذر: {$live}");

/* ══ ② لكلِّ مؤرشَفٍ سببٌ مكتوب ═══════════════════════════════════════════ */
echo "\n══ ② لكلِّ حالةٍ سببٌ مكتوب ══\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_event_delivery_orphans'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ الأيتامِ غيرُ موجود — تُشغَّل الهجرة 2027_09_05');
} else {
    $tot = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_delivery_orphans`")->fetch_row()[0];
    $noWhy = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_delivery_orphans`
                                  WHERE COALESCE(`orphan_reason`,'') = ''")->fetch_row()[0];
    chk($noWhy === 0, "صفرُ مؤرشَفٍ بلا سبب — {$noWhy} من {$tot}");
    $q = $conn->query("SELECT LEFT(`orphan_reason`,34) r, COUNT(*) n
                         FROM `ems_event_delivery_orphans` GROUP BY r ORDER BY n DESC");
    while ($q && $x = $q->fetch_assoc()) { printf("     · %-40s %d\n", $x['r'], $x['n']); }
    $q = $conn->query("SELECT MIN(`updated_at`), MAX(`updated_at`) FROM `ems_event_delivery_orphans`");
    $x = $q->fetch_row();
    chk($x[0] !== null, "تتبُّعٌ زمنيٌّ محفوظ: {$x[0]} ← {$x[1]}");
}

/* ══ ③ القيدُ قائمٌ **ويمنع فعلًا** ═══════════════════════════════════════ */
echo "\n══ ③ القيدُ يُجرَّب حيًّا لا يُقرَأ من المخطَّط ══\n";
$r = $conn->query("SELECT `DELETE_RULE` FROM information_schema.REFERENTIAL_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_evdeliv_event'");
$rule = ($r && $row = $r->fetch_row()) ? $row[0] : null;
chk($rule !== null, 'القيدُ `fk_evdeliv_event` مُعلَنٌ في المخطَّط' . ($rule ? " · ON DELETE {$rule}" : ''));

$PROBE = '__gap08_probe__';
$conn->query("DELETE FROM `ems_event_deliveries` WHERE `consumer` = '{$PROBE}'");   /* كنسٌ قبليّ */
$ghost = 999999999;
$conn->query("INSERT INTO `ems_event_deliveries` (`consumer`,`event_id`,`state`)
              VALUES ('{$PROBE}', {$ghost}, 'published')");
$blocked = ($conn->errno !== 0);
$errno = $conn->errno;
$conn->query("DELETE FROM `ems_event_deliveries` WHERE `consumer` = '{$PROBE}'");   /* كنسٌ بعديٌّ دائمًا */
$leak = (int) $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries`
                             WHERE `consumer` = '{$PROBE}'")->fetch_row()[0];
chk($blocked, "**القيدُ منع** إدراجَ تسليمٍ بجذرٍ غيرِ موجود (errno {$errno})");
chk($leak === 0, "أثرُ الفحصِ مكنوسٌ — {$leak} صفًّا باقيًا");

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-08', true, 'صفرُ تسليمِ حدثٍ يتيم — والمؤرشَفُ محفوظٌ قبلَ الحذفِ وقيدٌ متسلسلٌ يمنع عودتَه', $bad);

exit($bad === 0 ? 0 : 1);
