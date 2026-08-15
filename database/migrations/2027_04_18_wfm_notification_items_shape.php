<?php
/**
 * 2027_04_18_wfm_notification_items_shape.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مهامٌّ قائمةٌ لا يراها أصحابُها — تسويةُ شكلِ عناصرِ التنبيهات
 *
 * ── المقيس ───────────────────────────────────────────────────────────────
 * `WorkItemService::createTaskForNotification()` كانت تُدرج في `work_items`
 * **رأسًا متجاوزةً `create()`**، فكتبت `source_type='notification'`
 * و`status='open'`: الأولُ **خارجَ المصادرِ الأربعةَ عشرَ**، والثاني **خارجَ
 * الحالاتِ الخمسَ عشرةَ**.
 *
 * وأثرُ ذلك ليس تسميةً: عروضُ `Portal/my_tasks.php` العشرةُ كلُّها ترشِّح
 * بالحالة، و`open` لا تطابق واحدًا منها. فالصفُّ مكتوبٌ ومُسنَدٌ ومعدودٌ في
 * لوحاتِ المدير — **ولا يظهر لصاحبِه في أيِّ عرض**. مهمةٌ موجودةٌ لا تُرى
 * أسوأُ من مهمةٍ لم تُنشأ: الأولى تُحاسَبُ ولا تُنفَّذ.
 *
 * ── ما يفعله هذا الملف ───────────────────────────────────────────────────
 *   ① `source_type` ⇐ `SRC-14` (طارئةٌ تشغيلية) — وهو مصدرُ التنبيهِ المحوَّلِ
 *      نفسُه في `Portal/notifications.php`، فلا مصدرانِ لأصلٍ واحد.
 *   ② `status` ⇐ `assigned` — فتدخل «اليوم» وتُستلَم وتُنفَّذ كغيرِها.
 *   ③ `org_unit_id` ⇐ 1 حيث كان فارغًا (النطاقُ شرطٌ في الحارسِ السباعي).
 *   ④ `verifier_user_id` ⇐ مديرُ المنفِّذ ثم حوكمةُ شركتِه — وبدونه **لا سبيل
 *      لإقفالِ العنصرِ أصلًا** لأن المالكَ هو المنفِّذُ نفسُه في هذه الصفوف.
 *
 * ◆ **و`due_at` تُترك كما هي (NULL)**: لم تُوضع لها مهلةٌ قط، واختراعُ مهلةٍ
 *   رجعيةٍ من `created_at` يجعلها **متأخرةً لحظةَ كتابتِها** فيكنسها المحرّكُ
 *   إلى `overdue` ويُصعّدها لمديرين لم يُخطَروا بها يومًا. والفراغُ هنا يُقرأ
 *   صحيحًا: «اليوم» تقبل `due_at IS NULL` صراحةً — فتظهر ولا تُدين أحدًا.
 *
 * ◆ ولا يُلفَّق متحقِّق: ما انقطع سُلَّمُه يُترك ويُعلَن عددُه.
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
require_once $ROOT . '/includes/resolve_manager.php';

echo "══ عناصرُ التنبيهاتِ تأخذ شكلَ العناصر ══\n\n";

$r = $conn->query("SELECT COUNT(*) FROM work_items WHERE source_type = 'notification' OR status = 'open'");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n === 0) { exit("  · لا صفَّ بالشكلِ القديم — لا شيءَ يُسوّى\n"); }
echo "  صفوفٌ بالشكلِ القديم: {$n}\n";

// ④ المتحقِّق أوَّلًا — فمعرفتُنا بمن يعجز عن الحلِّ تسبق تغييرَ الحالة
$fixedV = 0; $noV = 0;
$q = $conn->query("SELECT id, company_id, assigned_user_id, created_by
                     FROM work_items
                    WHERE (source_type = 'notification' OR status = 'open')
                      AND (verifier_user_id IS NULL OR verifier_user_id = 0)");
$rows = array();
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
foreach ($rows as $x) {
    $exec = (int) $x['assigned_user_id'];
    $v = $exec > 0 ? ems_resolve_verifier($conn, $exec, (int) $x['company_id']) : null;
    if ($v === null || (int) $v === $exec) {
        $by = (int) $x['created_by'];
        $v = ($by > 0 && $by !== $exec) ? $by : null;
    }
    if ($v === null) { $noV++; continue; }
    $st = $conn->prepare("UPDATE work_items SET verifier_user_id = ? WHERE id = ?");
    $vi = (int) $v; $id = (int) $x['id'];
    $st->bind_param('ii', $vi, $id);
    if ($st->execute() && $st->affected_rows > 0) { $fixedV++; }
    $st->close();
}
echo "  ④ متحقِّقٌ مُحدَّد: {$fixedV}" . ($noV > 0 ? " · تعذّر لـ{$noV} (سُلَّمٌ منقطع — تُترك ولا يُلفَّق شاهد)" : '') . "\n";

// ③ النطاق ثم ① المصدر ثم ② الحالة
$conn->query("UPDATE work_items SET org_unit_id = 1
               WHERE (source_type = 'notification' OR status = 'open')
                 AND (org_unit_id IS NULL OR org_unit_id = 0)");
echo '  ③ نطاقٌ مملوء: ' . $conn->affected_rows . "\n";

$conn->query("UPDATE work_items SET source_type = 'SRC-14' WHERE source_type = 'notification'");
echo '  ① مصدرٌ ضمن الأربعةَ عشرَ: ' . $conn->affected_rows . "\n";

$conn->query("UPDATE work_items
                 SET status = 'assigned',
                     status_reason = CONCAT_WS(' · ', NULLIF(status_reason,''), 'تسويةُ شكلٍ: كانت open خارجَ الحالاتِ الخمسَ عشرة')
               WHERE status = 'open'");
echo '  ② حالةٌ ضمن الخمسَ عشرةَ: ' . $conn->affected_rows . "\n";

// الشاهد: صفرُ صفٍّ خارجَ التعدادين
$r = $conn->query("SELECT COUNT(*) FROM work_items
                    WHERE source_type NOT IN ('SRC-01','SRC-02','SRC-03','SRC-04','SRC-05','SRC-06','SRC-07',
                                              'SRC-08','SRC-09','SRC-10','SRC-11','SRC-12','SRC-13','SRC-14')
                       OR status NOT IN ('draft','scheduled','assigned','accepted','in_progress','blocked',
                                         'done_pending_verify','closed_accepted','returned','rejected',
                                         'cancelled','reassigned','delegated','overdue','reopened')");
$left = $r ? (int) $r->fetch_row()[0] : -1;
echo "\n  الشاهد — صفوفٌ خارجَ التعدادين بعدَ التسوية: {$left}\n";
echo $left === 0 ? "\n✔ تمّت\n" : "\n✘ بقيَ ما هو خارجُ التعدادين — راجع\n";
