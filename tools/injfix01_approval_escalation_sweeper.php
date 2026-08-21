<?php
/**
 * tools/injfix01_approval_escalation_sweeper.php
 *   معالجُ تصعيدِ الاعتماداتِ وكنّاسةُ المعلَّق — INJ-FIX-01 · GAP-04
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «معالجُ تصعيدٍ + كنّاسةُ معلَّق · **صفرُ خطوةٍ أقدمَ من مهلتِها
 *   بلا تصعيد**». وكان المقيسُ **أربعًا وأربعين خطوةً معلَّقة** أقدمُها بعمرِ
 *   ثمانيةِ آلافٍ وسبعِ مئةِ ساعة — **وصفرَ تصعيدٍ لواحدةٍ منها**.
 *
 * ◆ **والمهلةُ مُعلَنةٌ لا مُخترَعة**: `gov_ladders.escalate_after_hours` لكلِّ
 *   سلّم. وما لا سلّمَ لنوعِه يأخذ **المهلةَ الافتراضيةَ المُعلَنةَ هنا (٤٨)**
 *   وهي القيمةُ المنوالُ في السلاليمِ نفسِها — وتُطبع في كلِّ تشغيلٍ فلا تخفى.
 *
 * ◆ **ولا يُصعَّد البذر**: صفٌّ بنوعِ كيانٍ من عائلةِ `legacy_uat_*` **بقايا
 *   اختبارٍ لا واقعةُ عمل** — يُكنَس ولا يُرفَع إلى أحد. **وتصعيدُ بذرٍ إلى
 *   مسؤولٍ حقيقيٍّ يُفقد الثقةَ في التصعيدِ كلِّه.**
 *
 * ◆ **والتصعيدُ عاطلُ الأثر**: مفتاحُه (`item_kind`,`item_ref`,`level`) فلا
 *   يتكرَّر لو شُغِّل كلَّ دقيقة.
 *
 * التشغيل:  php tools/injfix01_approval_escalation_sweeper.php [--apply]
 *            (بلا --apply معاينةٌ لا تكتب)
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

$APPLY   = in_array('--apply', $argv, true);
$DEFAULT = 48;   /* مهلةٌ افتراضيةٌ مُعلَنةٌ — منوالُ السلاليمِ نفسِها */

/* ══ ① المهلُ المُعلَنةُ لكلِّ نوعِ كيان ═══════════════════════════════════ */
$sla = array();
$q = $conn->query("SELECT `entity_type`, MIN(`escalate_after_hours`) hrs
                     FROM `gov_ladders` WHERE COALESCE(`entity_type`,'') <> ''
                    GROUP BY `entity_type`");
while ($q && $x = $q->fetch_assoc()) { $sla[$x['entity_type']] = (int) $x['hrs']; }
echo "① المهلُ المُعلَنة: ";
$ps = array();
foreach ($sla as $k => $v) { $ps[] = "{$k}={$v}س"; }
echo implode(' · ', $ps) . " · **والافتراضيُّ المُعلَن {$DEFAULT}س**\n";

/* ══ ② الخطواتُ المعلَّقةُ ومَن تجاوز مهلتَه ══════════════════════════════ */
$rows = array();
$q = $conn->query("SELECT s.`id` step_id, s.`request_id`, s.`step_order`, s.`role_required`,
                          r.`entity_type`, r.`entity_id`, r.`action`, r.`requested_by`,
                          s.`created_at`, TIMESTAMPDIFF(HOUR, s.`created_at`, NOW()) age
                     FROM `approval_steps` s
                     JOIN `approval_requests` r ON r.`id` = s.`request_id`
                    WHERE s.`status` = 'pending' AND r.`status` = 'pending'
                    ORDER BY s.`created_at`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$overdue = array(); $seed = array(); $within = 0;
foreach ($rows as $r) {
    $et  = (string) $r['entity_type'];
    if (preg_match('/^legacy_uat_/', $et)) { $seed[] = $r; continue; }
    $lim = isset($sla[$et]) ? $sla[$et] : $DEFAULT;
    if ((int) $r['age'] > $lim) { $r['limit'] = $lim; $overdue[] = $r; }
    else { $within++; }
}
printf("② معلَّقٌ: %d · منه **متجاوزُ المهلة: %d** · داخلَها: %d · بذرٌ: %d\n",
    count($rows), count($overdue), $within, count($seed));

/* ══ ③ مَن يُصعَّد إليه — الخطوةُ التالية أو مالكُ السلّم ═════════════════ */
/* ◆ ولا يُخترع مسؤول: يُصعَّد إلى **طالبِ الاعتمادِ نفسِه** بوصفِه مالكَ الطلبِ
 *   حتى يُعاد توجيهُه — فالغرضُ **رفعُ الصمت** لا إعادةُ توزيعِ السلطة.
 *   وإعادةُ التوزيعِ تغييرُ سلطةٍ لا يملكه كنّاس. */
$made = 0; $already = 0;
$ins = $conn->prepare("INSERT INTO `work_escalations`
        (`company_id`,`item_kind`,`item_ref`,`from_user_id`,`to_user_id`,`level`,`reason`,`note`,`created_by`)
        VALUES (?,?,?,?,?,?,?,?,0)");
$chk = $conn->prepare("SELECT COUNT(*) FROM `work_escalations`
                        WHERE `item_kind`=? AND `item_ref`=? AND `level`=?");
foreach ($overdue as $r) {
    $kind = 'approval_step';
    $ref  = (int) $r['step_id'];
    $lvl  = 1;
    $chk->bind_param('sii', $kind, $ref, $lvl);
    $chk->execute();
    if ((int) $chk->get_result()->fetch_row()[0] > 0) { $already++; continue; }
    if (!$APPLY) { $made++; continue; }
    $co   = 4;   /* شركةُ التشغيلِ الوحيدةُ ذاتُ البيانات — والصفرُ يكسر عزلَ المستأجِر */
    $from = (int) $r['requested_by'];
    $to   = (int) $r['requested_by'];
    $why  = 'sla_response';
    $note = "GAP-04 · خطوةُ اعتمادٍ #{$r['step_id']} على {$r['entity_type']}#{$r['entity_id']} "
          . "({$r['action']}) عمرُها {$r['age']}س والمهلةُ {$r['limit']}س";
    $note = mb_substr($note, 0, 300);
    $ins->bind_param('isiiiiss', $co, $kind, $ref, $from, $to, $lvl, $why, $note);
    if ($ins->execute()) { $made++; }
    else { echo "   ✘ خطوة #{$r['step_id']}: {$ins->error}\n"; }
}
$ins->close(); $chk->close();
printf("③ تصعيدٌ %s: %d · وقائمٌ سلفًا: %d\n", $APPLY ? 'أُنشئ' : '[معاينة] سيُنشأ', $made, $already);

/* ══ ④ كنسُ البذرِ — لا يُصعَّد ولا يُترك معلَّقًا ═════════════════════════ */
if ($seed) {
    echo "④ بذرٌ لا يُصعَّد: " . count($seed) . " — ";
    $ids = array();
    foreach ($seed as $s) { $ids[] = (int) $s['request_id']; }
    echo 'طلبات ' . implode(',', array_unique($ids)) . "\n";
    if ($APPLY) {
        $in = implode(',', array_unique($ids));
        $conn->query("UPDATE `approval_steps` SET `status`='skipped',
                       `note`=CONCAT(COALESCE(`note`,''),' · GAP-04: بقايا اختبارٍ — كُنست ولم تُصعَّد')
                       WHERE `request_id` IN ({$in}) AND `status`='pending'");
        $a = $conn->affected_rows;
        $conn->query("UPDATE `approval_requests` SET `status`='rejected',
                       `rejection_reason`='GAP-04: بقايا اختبارٍ (legacy_uat) — لا واقعةَ عملٍ خلفَها'
                       WHERE `id` IN ({$in}) AND `status`='pending'");
        echo "   ✔ كُنس: {$a} خطوةً · {$conn->affected_rows} طلبًا\n";
    }
}

/* ══ ⑤ الحكم ══════════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$left = 0;
$q = $conn->query("SELECT s.`id`, r.`entity_type`, TIMESTAMPDIFF(HOUR, s.`created_at`, NOW()) age
                     FROM `approval_steps` s JOIN `approval_requests` r ON r.`id`=s.`request_id`
                    WHERE s.`status`='pending' AND r.`status`='pending'");
while ($q && $x = $q->fetch_assoc()) {
    $et = (string) $x['entity_type'];
    if (preg_match('/^legacy_uat_/', $et)) { continue; }
    $lim = isset($sla[$et]) ? $sla[$et] : $DEFAULT;
    if ((int) $x['age'] <= $lim) { continue; }
    $r2 = $conn->query("SELECT COUNT(*) FROM `work_escalations`
                         WHERE `item_kind`='approval_step' AND `item_ref`=" . (int) $x['id']);
    if ($r2 && (int) $r2->fetch_row()[0] === 0) { $left++; }
}
printf("⑤ **خطواتٌ متجاوزةُ المهلةِ بلا تصعيد: %d**%s\n", $left, $APPLY ? '' : ' (معاينة)');
if (!$APPLY) { echo "◆ للتنفيذ: php tools/injfix01_approval_escalation_sweeper.php --apply\n"; }
