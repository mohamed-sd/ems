<?php
/**
 * tools/e02_checks.php — حزام محرّك دورة الوحدة (E-02) · v1
 * ───────────────────────────────────────────────────────────────────────────
 * ① AC-E02-02: صفرُ صفٍّ بالطن/المتر بلا سطر زمنٍ — خارج الموروث قبل السلسلة.
 *    الموروث (converted بلا converted_at) يُعدّ مرجعًا وينقص بالاستكمال.
 * ② حارس UN-02 نافذ: علم EMS_E02_HOURS_GUARD ليس off.
 * الاستعمال: php tools/e02_checks.php [--enforce]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/unit_chain_helpers.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ENFORCE = in_array('--enforce', $argv, true);

echo "════ فحوص E-02 — دورة الوحدة ════\n";
$fail = 0;

/* ① الطن/المتر بلا ساعات — الجديدُ بعد تفعيل الحارس صفرًا، والأقدمُ دَينٌ مُهَل
   (موروثُ ما قبل السلسلة + دفعاتُ الردم قبل العتبة) يُعرض عدًّا وينقص بالاستكمال */
$CUTOFF = '2026-08-05'; // تاريخ تفعيل حارس UN-02 — قرار المالك
$q = "SELECT SUM(ue.created_at >= '{$CUTOFF}') fresh,
             SUM(ue.created_at <  '{$CUTOFF}') backlog
        FROM unit_entries ue
       WHERE ue.unit_type IN ('ton','meter')
         AND NOT EXISTS (SELECT 1 FROM unit_time_log l WHERE l.entry_id = ue.id)";
$r = mysqli_query($conn, $q);
$x = $r ? mysqli_fetch_assoc($r) : array('fresh' => '?', 'backlog' => '?');
$fresh = (int) $x['fresh'];
$backlog = (int) $x['backlog'];
$ok1 = ($fresh === 0);
if (!$ok1) { $fail++; }
echo ($ok1 ? '✔' : '✘') . " ① طن/متر بلا ساعاتٍ بعد {$CUTOFF} : {$fresh}"
   . "   (دَينُ ما قبل العتبة: {$backlog} — تقرير docs/E02_TON_METER_MISSING_HOURS_ar.csv)\n";

/* ② علم الحارس */
$flag = function_exists('ems_env') ? (string) ems_env('EMS_E02_HOURS_GUARD', 'enforce') : 'enforce';
$ok2 = ($flag !== 'off');
if (!$ok2) { $fail++; }
echo ($ok2 ? '✔' : '✘') . " ② حارسُ UN-02 نافذ (EMS_E02_HOURS_GUARD={$flag})\n";

echo "──────────────────────────────────────────────\n";
if ($fail === 0) {
    echo "الحكم: ✔ صفرٌ في الحاكمة\n";
    exit(0);
}
echo "الحكم: ✘ {$fail} خرقًا" . ($ENFORCE ? ' — الدمجُ ممنوع' : '') . "\n";
exit($ENFORCE ? 1 : 0);
