<?php
/**
 * 2027_01_16_fix_fn01_nav_doors.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-01 تصحيحُ الباب — گوتشا مرصودةٌ بالتصييرِ الحيّ لا بالمراجعة.
 *
 * ◆ الخطأ: أُسند البابُ `FIN` لشاشاتِ **المحاسبة** (`Finance/…`). و`FIN` في هذا
 *   النظامِ ليس «المالية» بل **«التمويل»** — وهو خلفَ بوابةِ المجالِ المقيَّد
 *   (DEC-01 ② · FIN-01 §1.1): `getUnifiedNavItems` **يحذف كلَّ عنصرٍ بابُه FIN**
 *   لمن لا يملك منحةً فرديةً في `OwnershipDomainGuard`. فالنتيجةُ أن ستةً
 *   وعشرين عنصرًا مؤهَّلًا في القاعدةِ صُيِّر منها ثلاثة — والقائمةُ تبدو
 *   «مبنيةً» وهي محجوبةٌ ببوابةٍ لا تخصُّها.
 *
 * ◆ الدرس (وهو عينُ حكمِ GT-01): **عدُّ الصفوفِ يكذب — والشاهدُ التصييرُ الحيّ**.
 *   لولا `tools/fix_probe_sidebar.php` الذي يُصيِّر ويعدُّ `<a>` لمرَّ الخطأ خضراء.
 *
 * ◆ الأبوابُ الصحيحةُ بدلالتها:
 *     Finance/acc_*  → DAILY  عملٌ يوميٌّ للمحاسب
 *     Finance/ob_*   → REC    سجلُّ الالتزاماتِ سجلٌّ رئيسي
 *     Finance/ctrl_* → GOV    رقابةٌ وإشرافٌ وحدودُ سلطة
 *     Finance/tre_*  → GOV    سقوفٌ وفصلُ واجبات
 *     Audit/iaf_*    → GOV    المراجعةُ الداخلية
 *     Portal/ceo_*   → REP    تقاريرُ الجهةِ المشرفة
 *     main/*         → HOME
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$MAP = array(
    "route LIKE 'Finance/acc\\_%'"  => 'DAILY',
    "route LIKE 'Finance/ob\\_%'"   => 'REC',
    "route LIKE 'Finance/ctrl\\_%'" => 'GOV',
    "route LIKE 'Finance/tre\\_%'"  => 'GOV',
    "route LIKE 'Audit/iaf\\_%'"    => 'GOV',
    "route LIKE 'Portal/ceo\\_%'"   => 'REP',
    "route LIKE 'main/%'"           => 'HOME',
);

$db->begin_transaction();
try {
    $total = 0;
    foreach ($MAP as $cond => $door) {
        $sql = "UPDATE nav_items SET door = '" . $db->real_escape_string($door) . "'
                 WHERE role_id IN (31,32,33) AND {$cond} AND door <> '" . $db->real_escape_string($door) . "'";
        if (!$db->query($sql)) { throw new RuntimeException('door update: ' . $db->error); }
        $n = $db->affected_rows;
        $total += $n;
        if ($n > 0) { echo "[FN-01] {$cond} → {$door}: {$n} صفًّا\n"; }
    }
    // ما بقي على FIN بلا تصنيفٍ أعلاه: بابُه SET (إعداداتٌ وتدقيق) لا التمويل.
    if (!$db->query("UPDATE nav_items SET door = 'SET'
                      WHERE role_id IN (31,32,33) AND door = 'FIN'")) {
        throw new RuntimeException('fallback door: ' . $db->error);
    }
    $rest = $db->affected_rows;
    if ($rest > 0) { echo "[FN-01] بقيةُ FIN → SET: {$rest} صفًّا\n"; }
    $db->commit();
    echo "[FN-01] أبوابٌ صُحِّحت: " . ($total + $rest) . "\n";
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

/* ── إثباتٌ حيّ: صفرُ عنصرٍ بابُه FIN لهذه الأدوار ─────────────────────── */
$left = (int) $db->query("SELECT COUNT(*) FROM nav_items WHERE role_id IN (31,32,33) AND door = 'FIN'")->fetch_row()[0];
if ($left > 0) { throw new RuntimeException("بقي {$left} عنصرًا خلفَ بوابةِ التمويل"); }
echo "[FN-01] صفرُ عنصرٍ خلفَ بوابةِ التمويل ✔\n";
