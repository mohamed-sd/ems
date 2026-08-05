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

/* ③ الختم: ما اكتملت أطرافُه بعد العتبة لا يبقى بأسطرٍ بلا حكمٍ مختوم (UN-05) */
$r = mysqli_query($conn,
    "SELECT COUNT(DISTINCT ue.id) n
       FROM unit_entries ue
       JOIN unit_time_log l ON l.entry_id = ue.id
      WHERE ue.state IN ('parties_approved','sales_approved')
        AND ue.updated_at >= '{$CUTOFF}'
        AND NOT " . ems_uc_prechain_sql('ue') . "
        AND l.decided_at IS NULL
        AND (l.objection_state IS NULL OR l.objection_state='none')");
$sealMiss = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : 0;
$ok3 = ($sealMiss === 0);
if (!$ok3) { $fail++; }
echo ($ok3 ? '✔' : '✘') . " ③ مكتملُ الأطراف بعد العتبة بأسطرٍ بلا ختم : {$sealMiss}\n";

/* ④ سلامة المرآة: قرارُ اعتمادٍ حيٌّ بعد العتبة بلا سطرِ سلسلةٍ يقابله */
$r = mysqli_query($conn,
    "SELECT COUNT(*) n
       FROM timesheet_approvals ta
       JOIN unit_entries ue ON ue.sync_uuid = CONCAT('ts:', ta.timesheet_id)
      WHERE ta.status = 1 AND ta.approved_at >= '{$CUTOFF}'
        AND ta.approval_level IN (1,2,3,4)
        AND NOT EXISTS (
            SELECT 1 FROM unit_approvals ua
             WHERE ua.entry_id = ue.id
               AND ua.stage = ELT(ta.approval_level, 'site', 'supplier', 'fleet', 'operator'))");
$mirrorMiss = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : 0;
$ok4 = ($mirrorMiss === 0);
if (!$ok4) { $fail++; }
echo ($ok4 ? '✔' : '✘') . " ④ اعتمادٌ حيٌّ بعد العتبة بلا مرآةِ سلسلة : {$mirrorMiss}\n";

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
