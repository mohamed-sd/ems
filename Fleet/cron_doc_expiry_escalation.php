<?php
/**
 * Fleet/cron_doc_expiry_escalation.php — سلّمُ تصعيد الوثائق المنتهية (E-11)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-10 §8.2: التنبيهُ عند 30 يومًا قائمٌ في اللوحة — وهذا **سلّمُه**: بلوغُ
 * 7 أيامٍ يصعّد إشعارًا **لمرةٍ واحدةٍ لكل (وثيقة × مرحلة)** — العطالةُ
 * بعنوانٍ يحمل معرّفَ الوثيقة والمرحلةَ لا بمفتاحٍ يوميٍّ يتكرر (درسُ E-14).
 *
 * التشغيل:  php Fleet/cron_doc_expiry_escalation.php  (CLI · cron يومي)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_doc_expiry_escalation.php'); // INJ-0025: لا تُشغَّل من المتصفّح
while (ob_get_level() > 0) { ob_end_clean(); }

$n_escalated = 0;
$r = $conn->query("SELECT d.doc_id, d.company_id, d.doc_type, d.doc_no, d.expiry_date,
                          d.subject_type, d.subject_id,
                          DATEDIFF(d.expiry_date, CURDATE()) days_left
                     FROM equipment_documents d
                    WHERE COALESCE(d.is_deleted,0)=0 AND d.expiry_date IS NOT NULL
                      AND DATEDIFF(d.expiry_date, CURDATE()) BETWEEN 0 AND 7
                    ORDER BY d.expiry_date");
while ($r && ($x = $r->fetch_assoc())) {
    /* ══ INJ-0167 · «تشغيلُ السكربت سبعةَ أيامٍ متتاليةٍ على الوثيقة نفسِها ينتج
         **إشعارًا واحدًا** لمرحلة 7d» ═══════════════════════════════════════════
         كان يعلن رأسُ الملفِّ أنَّ «العطالةَ بعنوانٍ يحمل معرّفَ الوثيقة والمرحلة»
         — والعنوانُ يحمل معه **`days_left` المتغيّرَ يوميًّا**. فمقارنةُ النصِّ
         لا تطابق شيئًا في اليومِ التالي، والوثيقةُ الواحدةُ تولّد **ثمانيةَ**
         إشعاراتٍ (٠..٧) بدل واحد.
       ◆ فالمفتاحُ صار **ثابتًا**: (الوثيقة × المرحلة) لا نصًّا فيه زمن.
         والعنوانُ يبقى بيانَ عرضٍ حرًّا — لأنَّ **العنوانَ ليس مفتاحًا**.
       ◆ والمقارنةُ بالمفتاحِ داخلَ النصِّ (بادئةٌ محكومة) لأنَّ `fin_notifications`
         بلا عمودِ عطالة — فيُحفَظ المفتاحُ في `link` المحكومِ لا في العنوانِ الحرّ. */
    $idemKey = 'doc:' . intval($x['doc_id']) . ':stage:7d';
    $link    = 'Equipments/equipments.php?doc=' . intval($x['doc_id']) . '&esc=' . rawurlencode($idemKey);
    $title = mb_substr('⚠ تصعيدٌ E-11: وثيقة #' . intval($x['doc_id']) . ' (' . $x['doc_type']
           . ' ' . $x['doc_no'] . ') تنتهي خلال ' . intval($x['days_left'])
           . ' أيام — مرحلةُ 7d', 0, 200);
    $dup = $conn->query("SELECT id FROM fin_notifications
                          WHERE company_id = " . intval($x['company_id']) . "
                            AND link = '" . $conn->real_escape_string($link) . "' LIMIT 1");
    if ($dup && $dup->fetch_assoc()) { continue; }
    $st = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                          VALUES (?, 'fleet_records', ?, ?)");
    if ($st) {
        $co = intval($x['company_id']);
        $st->bind_param('iss', $co, $title, $link);
        if ($st->execute()) { $n_escalated++; }
        $st->close();
    }
}
fwrite(STDOUT, "E-11 escalation: {$n_escalated} إشعارَ تصعيدٍ جديدًا (مرحلة 7 أيام)\n");
exit(0);
