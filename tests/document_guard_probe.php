<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * مِسبارُ حارس الوثائق — أداةُ تحقّقٍ للمالك (قراءةٌ محضة)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/document_guard_probe.php [عددُ الصفوف]
 *
 * يجيب ثلاثةَ أسئلةٍ في نداءٍ واحد:
 *   ① ما وضعُ الحارس الآن؟ (off · monitor · enforce) وأين يُبدَّل؟
 *   ② ماذا يحكم على صفوف الدوام التي تنتظر اعتمادَ الموقع فعلًا؟
 *   ③ كم صفًّا سيُحجب لو قُلب العلَمُ إلى enforce الآن؟
 *
 * ⚠️ قراءةٌ محضة: لا يكتب صفًّا ولا يغيّر علَمًا ولا يعتمد شيئًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Unit/DocumentGuard.php';

use App\Services\Unit\DocumentGuard;

while (ob_get_level() > 0) { ob_end_clean(); }

$conn  = $GLOBALS['conn'];
$CO    = 4;                                   // شركة co4 — بيئة العمل الحية
$LIMIT = isset($argv[1]) ? max(1, (int) $argv[1]) : 5;

$mode = DocumentGuard::mode();
$label = array('off' => 'مطفأ — صفرُ أثر',
               'monitor' => 'رصدٌ — يقيس ويسجّل ويمرّ',
               'enforce' => 'إلزامٌ — يرفض بـ422');

echo "══════════════════════════════════════════════════════════\n";
echo " حارسُ الوثائق المنتهية — الوضعُ الآن: {$mode}\n";
echo " ({$label[$mode]})\n";
echo " المِفتاح: .env → EMS_DOC_EXPIRY_GUARD\n";
echo "══════════════════════════════════════════════════════════\n";

if ($mode === 'off') {
    echo "\n⚠️  الحارسُ مطفأ — الأحكامُ أدناه لن تُحتسب.\n";
    echo "    اضبط EMS_DOC_EXPIRY_GUARD=monitor في .env ثم أعِد التشغيل.\n\n";
}

// ── ① الأحكامُ على أحدث الصفوف المنتظرة اعتمادَ الموقع ──────────────────
echo "\n── أحكامٌ على أحدث {$LIMIT} صفوفٍ تنتظر اعتمادَ الموقع ──\n";

$st = $conn->prepare(
    "SELECT ts.id, ts.`date`, ts.employee_id, o.equipment AS eq_id, e.name AS eq_name
       FROM timesheet ts
       LEFT JOIN operations  o ON o.id = ts.`operator`
       LEFT JOIN equipments  e ON e.id = o.equipment
      WHERE ts.company_id = ? AND ts.status = 1
      ORDER BY ts.id DESC LIMIT ?"
);
$st->bind_param('ii', $CO, $LIMIT);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

foreach ($rows as $t) {
    $v = DocumentGuard::assertForTimesheet($conn, $CO, (int) $t['id']);
    $eq = ($t['eq_name'] !== null && $t['eq_name'] !== '')
        ? $t['eq_name'] . ' (#' . $t['eq_id'] . ')' : '#' . $t['eq_id'];

    echo "\nصفُّ الدوام #{$t['id']} · {$t['date']} · المعدة {$eq} · المشغّل #{$t['employee_id']}\n";
    echo "  الحكم: " . ($v['ok'] ? "يمرّ ({$v['code']})" : "محجوب ({$v['code']})") . "\n";
    if (empty($v['reasons'])) {
        echo "  ✓ لا وثيقةَ أهليةٍ منتهيةً يومَ العمل\n";
    } else {
        foreach ($v['reasons'] as $why) { echo "  ✗ {$why}\n"; }
    }
}

// ── ② أثرُ القلب إلى enforce — العددُ الحاكم للقرار ──────────────────────
echo "\n── لو قُلب العلَمُ إلى enforce الآن ──\n";

$EQD = "'استمارة','تأمين','فحص دوري','رخصة تشغيل'";
$OPD = "'رخصة قيادة'";
$sql = "SELECT COUNT(*) total,
          SUM(EXISTS(SELECT 1 FROM equipment_documents d
                      WHERE COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة'
                        AND d.subject_type='equipment' AND d.subject_id = o.equipment
                        AND d.doc_type IN ({$EQD})
                        AND d.expiry_date IS NOT NULL AND d.expiry_date < ts.`date`)
           OR EXISTS(SELECT 1 FROM equipment_documents d
                      WHERE COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة'
                        AND d.subject_type='operator' AND d.subject_id = ts.employee_id
                        AND d.doc_type IN ({$OPD})
                        AND d.expiry_date IS NOT NULL AND d.expiry_date < ts.`date`)) blocked
        FROM timesheet ts LEFT JOIN operations o ON o.id = ts.`operator`
       WHERE ts.company_id = {$CO} AND ts.status = 1";
$r = $conn->query($sql)->fetch_assoc();
echo "  صفوفٌ تنتظر اعتمادَ الموقع: {$r['total']}\n";
echo "  منها سيُحجب:               {$r['blocked']}\n";

// ── ③ الوثائقُ الحاجبةُ المنتهية — شرطُ فكِّ الحجب ───────────────────────
echo "\n── الوثائقُ الحاجبةُ المنتهيةُ (شرطُ القلب إلى enforce) ──\n";
$sql = "SELECT subject_type, doc_type, COUNT(*) n FROM equipment_documents
         WHERE COALESCE(is_deleted,0)=0 AND status<>'ملغاة'
           AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
           AND ((subject_type='equipment' AND doc_type IN ({$EQD}))
             OR (subject_type='operator'  AND doc_type IN ({$OPD})))
         GROUP BY subject_type, doc_type";
$res = $conn->query($sql);
$sum = 0;
while ($x = $res->fetch_assoc()) {
    $who = ($x['subject_type'] === 'equipment') ? 'معدات' : 'مشغّلون';
    echo "  {$who} · {$x['doc_type']}: {$x['n']}\n";
    $sum += (int) $x['n'];
}
echo "  ─────────────────────\n  المجموع: {$sum} وثيقةً تحتاج تجديدًا\n\n";
