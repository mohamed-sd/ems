<?php
/**
 * عطالة المستهلك على مستوى المستند — ProcessedOperations (N-06 ركن ③)
 * ───────────────────────────────────────────────────────────────────────────
 * جدول `processed_operations` بمفتاح (المستهلك × المستند × الأثر) يمنع تكرار
 * المستهلكين عند إعادة التشغيل — ولو حمل المستندَ الواحد أكثرُ من حدث.
 *
 * الاستعمال داخل معالج المستهلك، قبل إحداث الأثر وفي معاملته نفسها:
 *   if (!ProcessedOperations::claim($conn, 'finance', 'fin_unit_record', $id, 'revenue', $eventId)) {
 *       return; // معالَجٌ سلفًا — عطالة
 *   }
 *
 * تمييز الطبقات الثلاث (لا تُخلط):
 *   • fin_event_links: عطالة توليد الأثر داخل المروحة (الناشر).
 *   • ems_processed_events: عطالة الاستهلاك الموزَّع (consumer × event_uuid).
 *   • processed_operations (هذا): عطالة المستهلك على (المستند × الأثر) —
 *     صمام الأمان عند إعادة التشغيل أو تكرار الحدث لمستندٍ واحد.
 */

namespace App\Core;

class ProcessedOperations
{
    /**
     * يدّعي معالجة (مستهلك × مستند × أثر). Insert-only.
     * @return bool true = أول ادعاء (امضِ) · false = معالَجٌ سلفًا (توقف بلا أثر)
     */
    public static function claim(\mysqli $conn, $consumer, $docType, $docId, $effectKind, $eventId = null)
    {
        $stmt = $conn->prepare(
            'INSERT IGNORE INTO `processed_operations` (`consumer`, `doc_type`, `doc_id`, `effect_kind`, `event_id`)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            throw new \RuntimeException('ProcessedOperations: prepare فشل — ' . $conn->error);
        }
        $docId = intval($docId);
        $eid = ($eventId !== null) ? intval($eventId) : null;
        $stmt->bind_param('ssisi', $consumer, $docType, $docId, $effectKind, $eid);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('ProcessedOperations: execute فشل — ' . $err);
        }
        $claimed = ($stmt->affected_rows === 1);
        $stmt->close();
        return $claimed;
    }

    /** هل عولج (مستهلك × مستند × أثر) سلفًا؟ قراءةٌ بلا ادعاء. */
    public static function isProcessed(\mysqli $conn, $consumer, $docType, $docId, $effectKind)
    {
        $stmt = $conn->prepare(
            'SELECT 1 FROM `processed_operations` WHERE consumer = ? AND doc_type = ? AND doc_id = ? AND effect_kind = ? LIMIT 1'
        );
        $docId = intval($docId);
        $stmt->bind_param('ssis', $consumer, $docType, $docId, $effectKind);
        $stmt->execute();
        $found = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $found;
    }
}
