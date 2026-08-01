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
    // العنوانُ يحمل (الوثيقةَ × مرحلةَ 7أيام) — فالإشعارُ لا يتكرر أبدًا لهذه المرحلة
    $title = mb_substr('⚠ تصعيدٌ E-11: وثيقة #' . intval($x['doc_id']) . ' (' . $x['doc_type']
           . ' ' . $x['doc_no'] . ') تنتهي خلال ' . intval($x['days_left'])
           . ' أيام — مرحلةُ 7d', 0, 200);
    $dup = $conn->query("SELECT id FROM fin_notifications
                          WHERE company_id = " . intval($x['company_id']) . "
                            AND title = '" . $conn->real_escape_string($title) . "' LIMIT 1");
    if ($dup && $dup->fetch_assoc()) { continue; }
    $st = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                          VALUES (?, 'fleet_records', ?, 'Equipments/equipments.php')");
    if ($st) {
        $co = intval($x['company_id']);
        $st->bind_param('is', $co, $title);
        if ($st->execute()) { $n_escalated++; }
        $st->close();
    }
}
fwrite(STDOUT, "E-11 escalation: {$n_escalated} إشعارَ تصعيدٍ جديدًا (مرحلة 7 أيام)\n");
exit(0);
