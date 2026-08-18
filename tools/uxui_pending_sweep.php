<?php
/**
 * tools/uxui_pending_sweep.php — كنّاسةُ إغلاقِ المعلَّقِ بقاعدةِ الثلاثةِ أيام
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تفويضُ المالكِ رابعًا: «من وافق أو عدَّل خلال ثلاثةِ أيامٍ يُثبَّت قرارُه ·
 *   ومن لم يردَّ خلالها يُعتمد المقترحُ تلقائيًّا بوسم auto-approved by silence
 *   ويبقى قابلًا للتعديلِ لاحقًا من الشاشة. لا توقف العملَ لأحد».
 * ◆ ما تفعله الكنّاسة عند حلولِ المهلة:
 *   ① الطلبُ المعلَّقُ الذي انقضت مهلتُه ⇒ decision=auto_approved_by_silence
 *   ② وصفُّه في nav_canonical يُرقَّى PENDING_OWNER ⇒ APPROVED فيولّده المولّدُ
 *      بالاسمِ والموضعِ المعياريَّين (ولا يُرقّى PENDING_OWNER_MERGE — فالدمجُ
 *      فعلٌ لا تصويت: يبقى بمسارِه حتى تنفيذِ الدمجِ وإعادةِ التوجيه).
 *   ③ ويبقى قابلًا للنقض: reopened_at من الشاشةِ يعيده معلَّقًا بلا فقد.
 * ◆ اسمُ المعتمِدِ لا يُكتب نصًّا أبدًا — decided_by معرِّفُ مستخدمٍ يُقرأ من
 *   الجلسة، والصمتُ يُسجَّل بلا معرِّفٍ (NULL) لأنه غيابُ فاعلٍ لا فاعل.
 *
 * التشغيل:
 *   php tools/uxui_pending_sweep.php            تقريرُ الحالِ بلا كتابة
 *   php tools/uxui_pending_sweep.php --apply    ينفّذ الترقيةَ عند انقضاءِ المهلة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$APPLY = in_array('--apply', $argv, true);

$now = date('Y-m-d H:i:s');
$q = $conn->query("SELECT decision, COUNT(*) c FROM nav_pending_closure GROUP BY decision");
echo "════ إغلاقُ المعلَّقِ — الحالُ الآن ({$now}) ════\n";
while ($x = $q->fetch_assoc()) { echo "  {$x['decision']}: {$x['c']}\n"; }

$due = $conn->query("SELECT c.id, c.route, c.owner_dept, c.proposed_label, n.status
                       FROM nav_pending_closure c
                       JOIN nav_canonical n ON n.route = c.route
                      WHERE c.decision = 'pending' AND c.due_at <= NOW() AND c.reopened_at IS NULL");
$rows = array();
while ($x = $due->fetch_assoc()) { $rows[] = $x; }
echo "  انقضت مهلتُها ولم يردَّ مديرُها: " . count($rows) . "\n";

if (empty($rows)) { echo "لا شيءَ يُكنَس الآن.\n"; exit(0); }
foreach (array_slice($rows, 0, 10) as $r) { echo "   · {$r['owner_dept']} — {$r['route']} «{$r['proposed_label']}»\n"; }
if (count($rows) > 10) { echo "   … و" . (count($rows) - 10) . " غيرَها\n"; }

if (!$APPLY) { echo "\n(تقريرٌ فقط — أضف --apply للتنفيذ)\n"; exit(0); }

$conn->begin_transaction();
$promoted = 0; $marked = 0;
foreach ($rows as $r) {
    $st = $conn->prepare("UPDATE nav_pending_closure SET decision='auto_approved_by_silence', decided_at=NOW(), decided_by=NULL WHERE id=? AND decision='pending'");
    $st->bind_param('i', $r['id']); $st->execute();
    if ($st->affected_rows > 0) { $marked++; }
    /* الدمجُ فعلٌ لا تصويت: لا يُرقّى بالصمت */
    if ($r['status'] === 'PENDING_OWNER') {
        $up = $conn->prepare("UPDATE nav_canonical SET status='APPROVED', derivation=CONCAT(derivation,' · auto-approved by silence ',DATE_FORMAT(NOW(),'%Y-%m-%d')) WHERE route=? AND status='PENDING_OWNER'");
        $up->bind_param('s', $r['route']); $up->execute();
        if ($up->affected_rows > 0) { $promoted++; }
    }
}
$conn->commit();
echo "\n✔ وُسمت بالصمت: {$marked} · ورُقِّيت إلى APPROVED: {$promoted}\n";
echo "  (وما بقي PENDING_OWNER_MERGE لا يُرقّى بالصمتِ — الدمجُ فعلٌ يُنفَّذ ويُعاد توجيهُه)\n";
echo "  وكلُّها قابلةٌ للنقضِ من الشاشةِ بـreopened_at بلا فقدِ رابط.\n";
