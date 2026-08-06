<?php
/**
 * tools/wfm_checks.php — حزام محرّك العمل الشخصي (WFM-01 · AC-WFM-01..14)
 * ───────────────────────────────────────────────────────────────────────────
 * أصفارٌ حاكمة (تحجب مع --enforce):
 *  ① مهمةٌ بلا مصدرٍ من الأربعة عشر أو بلا مرجع (AC-WFM-01)
 *  ② عنصرٌ حيٌّ بلا مالكٍ أو بلا منفِّذ/دور (AC-WFM-02)
 *  ③ إنجازٌ حيٌّ بلا دليل (AC-WFM-05)
 *  ④ إنجازٌ من مصدرٍ خارج الثمانية (WF-03)
 *  ⑤ تفويضٌ «نشطٌ» منقضي المدة — انكشاف تعطل الكانس (AC-WFM-10)
 *  ⑥ مهمةٌ متجاوزةٌ مهلتَها بلا صفِّ تصعيد (AC-WFM-09)
 *  ⑦ طلبٌ مغلقٌ بلا صفِّ الرد التسعة (WF-05)
 *  ⑧ طلبٌ حيٌّ بلا حاملٍ معلوم (AC-WFM-07)
 * وعدّاداتٌ تبليغية لا تحجب.
 * الاستعمال: php tools/wfm_checks.php [--enforce]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ENFORCE = in_array('--enforce', $argv, true);

$fail = 0;
function chk(mysqli $c, $label, $sql) {
    global $fail;
    $r = mysqli_query($c, $sql);
    $n = $r ? intval(mysqli_fetch_row($r)[0]) : -1;
    $ok = ($n === 0);
    if (!$ok) { $fail++; }
    fwrite(STDOUT, ($ok ? '✔' : '✘') . " {$label} : {$n}\n");
    return $n;
}

fwrite(STDOUT, "════ فحوص WFM — محرّك العمل الشخصي ════\n");

chk($conn, "① مهمةٌ بلا مصدرٍ معتمَدٍ أو بلا مرجع",
    "SELECT COUNT(*) FROM work_items
      WHERE source_type NOT IN ('SRC-01','SRC-02','SRC-03','SRC-04','SRC-05','SRC-06','SRC-07',
                                'SRC-08','SRC-09','SRC-10','SRC-11','SRC-12','SRC-13','SRC-14')
         OR source_ref = '' OR source_ref IS NULL");

chk($conn, "② عنصرٌ حيٌّ بلا مالكٍ أو منفِّذ/دور",
    "SELECT COUNT(*) FROM work_items
      WHERE status NOT IN ('closed_accepted','cancelled','rejected')
        AND (owner_user_id = 0 OR owner_user_id IS NULL
             OR ((assigned_user_id IS NULL OR assigned_user_id = 0)
                 AND (assigned_role_id IS NULL OR assigned_role_id = 0)
                 AND status NOT IN ('draft','scheduled')))");

chk($conn, "③ إنجازٌ حيٌّ بلا دليل",
    "SELECT COUNT(*) FROM achievement_records
      WHERE reversed_at IS NULL AND (evidence_ref = '' OR evidence_ref IS NULL)");

chk($conn, "④ إنجازٌ من مصدرٍ خارج الثمانية",
    "SELECT COUNT(*) FROM achievement_records
      WHERE source_kind NOT IN ('task','request','approval','work_order','unit','claim','ticket','corrective')");

chk($conn, "⑤ تفويضٌ «نشطٌ» منقضي المدة (كانسٌ معطَّل؟)",
    "SELECT COUNT(*) FROM work_delegations
      WHERE status = 'active' AND ends_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");

chk($conn, "⑥ متأخرةٌ بلا صفِّ تصعيد",
    "SELECT COUNT(*) FROM work_items wi
      WHERE wi.status = 'overdue'
        AND NOT EXISTS (SELECT 1 FROM work_escalations we
                         WHERE we.item_kind = 'work_item' AND we.item_ref = wi.id)");

chk($conn, "⑦ طلبٌ مغلقٌ بلا الرد التسعة",
    "SELECT COUNT(*) FROM requests rq
      WHERE rq.status = 'closed'
        AND NOT EXISTS (SELECT 1 FROM request_responses rr WHERE rr.request_id = rq.id)");

chk($conn, "⑧ طلبٌ حيٌّ بلا حاملٍ معلوم",
    "SELECT COUNT(*) FROM requests
      WHERE status IN ('routed','in_approval','approved','executing')
        AND (current_holder_user_id IS NULL OR current_holder_user_id = 0)");

/* عدّادات تبليغية — لا تحجب */
$r = mysqli_query($conn, "SELECT COUNT(*) FROM request_types WHERE status = 'active'");
fwrite(STDOUT, "· أنواعُ الطلبات النافذة: " . intval(mysqli_fetch_row($r)[0]) . "/62\n");
$r = mysqli_query($conn, "SELECT COUNT(*) FROM personal_notifications
                           WHERE requires_action = 1 AND task_item_id IS NULL
                             AND created_at < DATE_SUB(NOW(), INTERVAL 72 HOUR) AND read_at IS NULL");
fwrite(STDOUT, "· تنبيهُ فعلٍ معمِّرٌ (72س+) بلا قراءةٍ ولا مهمة: " . intval(mysqli_fetch_row($r)[0]) . " (تبليغ)\n");

fwrite(STDOUT, "──────────────────────────────────────────────\n");
if ($fail === 0) { fwrite(STDOUT, "الحكم: ✔ صفرٌ في الحاكمة\n"); exit(0); }
fwrite(STDOUT, "الحكم: ✘ {$fail} خرقًا" . ($ENFORCE ? ' — الدمجُ ممنوع' : '') . "\n");
exit($ENFORCE ? 1 : 0);
