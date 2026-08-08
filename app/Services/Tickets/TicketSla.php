<?php
/**
 * TicketSla — المرجع الواحد لمطابقة سياسة المهلة وحساب مواعيدها
 * ───────────────────────────────────────────────────────────────────────────
 * كان حسابُ المهلة حبيسَ دالّةٍ إجرائيةٍ تعتمد بوابةَ الجلسة، فتعذّر على
 * المسار البرمجي (الفتحُ السياقي · كسرُ الزجاج · تحويلُ ملاحظة التفتيش)
 * استعمالُها — فنشأ بلاغٌ بلا `resolution_due_at`، وحلقةُ الكرون تفلتر
 * «الموعد غير فارغ»، فبقي خارجَ التذكير والتصعيد وحسابِ التأخّر إلى الأبد.
 *
 * القاعدة هنا واحدةٌ لكل المستدعين: **الأكثر تحديدًا يفوز** —
 * النوع 4 نقاط · الأولوية 2 · أثر العمل 1 · و NULL يعني «عام فلا يُقصي».
 * الساعات تقويميّةٌ لا ساعاتِ دوام (المواقعُ تعمل بورديتين).
 */

namespace App\Services\Tickets;

class TicketSla
{
    /** السياسةُ الأكثر تحديدًا لهذه التوليفة — أو null إن لا سياسة. */
    public static function match(\mysqli $conn, $companyId, $typeId, $priority, $impact)
    {
        $co = intval($companyId);
        $res = $conn->query("SELECT * FROM ticket_sla_policies
                              WHERE active = 1 AND (company_id = {$co} OR company_id IS NULL)
                              ORDER BY id ASC");
        if (!$res) { return null; }

        $best = null;
        $bestScore = -1;
        while ($p = $res->fetch_assoc()) {
            $score = 0;
            if ($p['ticket_type_id'] !== null) {
                if (intval($p['ticket_type_id']) !== intval($typeId)) { continue; }
                $score += 4;
            }
            if ($p['priority'] !== null) {
                if ((string) $p['priority'] !== (string) $priority) { continue; }
                $score += 2;
            }
            if ($p['business_impact'] !== null) {
                if ((string) $p['business_impact'] !== (string) $impact) { continue; }
                $score += 1;
            }
            if ($score > $bestScore) { $bestScore = $score; $best = $p; }
        }
        return $best;
    }

    /** المواعيد من لحظة البلاغ + ساعات السياسة — ساعاتٌ تقويميّة. */
    public static function computeDue($callDate, $callTime, array $policy = null)
    {
        if ($policy === null) {
            return array('response' => null, 'resolution' => null, 'policy_id' => null);
        }
        $ts = strtotime(trim((string) $callDate . ' ' . (string) $callTime));
        if ($ts === false) { $ts = time(); }
        return array(
            'response'   => date('Y-m-d H:i:s', $ts + (int) round(((float) $policy['response_hours']) * 3600)),
            'resolution' => date('Y-m-d H:i:s', $ts + (int) round(((float) $policy['resolution_hours']) * 3600)),
            'policy_id'  => intval($policy['id']),
        );
    }

    /**
     * يطابق ويحسب ويكتب على رأس البلاغ مباشرةً — للمستدعين بلا بوابةِ جلسة
     * (الخدمات والكرون). الكتابة مقيَّدةٌ بالشركة صراحةً حفاظًا على العزل.
     */
    public static function applyHeader(\mysqli $conn, $companyId, $tkId, $typeId, $priority, $impact, $callDate, $callTime)
    {
        $policy = self::match($conn, $companyId, $typeId, $priority, $impact);
        $due = self::computeDue($callDate, $callTime, $policy);

        $stmt = $conn->prepare("UPDATE tickets
                                   SET sla_policy_id = ?, response_due_at = ?, resolution_due_at = ?
                                 WHERE id = ? AND company_id = ?");
        if (!$stmt) { return null; }
        $pid = $due['policy_id'];
        $tk  = intval($tkId);
        $co  = intval($companyId);
        $stmt->bind_param('issii', $pid, $due['response'], $due['resolution'], $tk, $co);
        $stmt->execute();
        $stmt->close();

        return ($due['policy_id'] === null) ? null : $due;
    }
}
