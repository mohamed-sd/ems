<?php
/**
 * محرّك الإنجاز — AchievementService (WFM-01 §5-6 · WF-03 · الورقة 11)
 * ───────────────────────────────────────────────────────────────────────────
 * «الإنجازُ يُشتق ولا يُدخَل»: ثمانيةُ مصادرَ حصرًا، وخمسةُ موانع، وعكسٌ آليٌّ
 * إن عُكس الأصل. منع التضاعف **بنيويٌّ** بالمفتاح الفريد
 * (source_kind, source_ref, person_user_id, attribution) — لا اتفاقًا سلوكيًّا.
 */

namespace App\Services\Work;

class AchievementService
{
    /** المصادر الثمانية (الورقة 11) — ولا تاسعَ لها */
    const SOURCES = array('task', 'request', 'approval', 'work_order', 'unit', 'claim', 'ticket', 'corrective');

    /**
     * اشتقاق — يرفض ما ليس من الثمانية وما بلا دليل (الموانع بنيوية).
     * @return array{ok:bool,code:int,id?:int,reason?:string}
     */
    public static function derive(\mysqli $conn, array $a)
    {
        if (!in_array((string) ($a['source_kind'] ?? ''), self::SOURCES, true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'مصدرٌ خارج الثمانية: ' . ($a['source_kind'] ?? '—'));
        }
        if (trim((string) ($a['evidence_ref'] ?? '')) === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'صفرُ إنجازٍ بلا دليل (AC-WFM-05)');
        }
        if (empty($a['person_user_id']) || empty($a['company_id'])) {
            return array('ok' => false, 'code' => 422, 'reason' => 'الشخص والكيان إلزاميان');
        }
        $st = $conn->prepare("INSERT INTO achievement_records
            (company_id, source_kind, source_ref, person_user_id, attribution, weight_pct,
             title, evidence_ref, event_ref, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"); // عطالة: القائم يُعاد مرجعه
        $co = intval($a['company_id']);
        $sk = (string) $a['source_kind'];
        $sr = (string) $a['source_ref'];
        $pu = intval($a['person_user_id']);
        $at = in_array(($a['attribution'] ?? ''), array('executive', 'supervisory', 'decision'), true)
            ? (string) $a['attribution'] : 'executive';
        $w = isset($a['weight_pct']) ? round((float) $a['weight_pct'], 2) : 100.00;
        $ti = mb_substr((string) ($a['title'] ?? ('إنجاز ' . $sk . ' ' . $sr)), 0, 300);
        $ev = mb_substr((string) $a['evidence_ref'], 0, 200);
        $er = isset($a['event_ref']) ? (string) $a['event_ref'] : null;
        $by = intval($a['created_by'] ?? 0);
        $st->bind_param('ississsssi', $co, $sk, $sr, $pu, $at, $w, $ti, $ev, $er, $by);
        if (!$st->execute()) { $e = $st->error; $st->close(); return array('ok' => false, 'code' => 422, 'reason' => $e); }
        $id = $st->insert_id;
        $st->close();
        WorkItemService::notifyUser($conn, $co, $pu, 'أُضيف إنجازٌ لسجلك', $ti,
            'Portal/my_achievement.php', false, $by);
        return array('ok' => true, 'code' => 200, 'id' => $id);
    }

    /**
     * الاشتقاق من مهمةٍ مقبولة (الورقة 11 · سطر «مهمة مكتملة ومقبولة»):
     * المنفِّذ تنفيذيًّا والمتحقِّق إشرافيًّا — والنوعان لا يُجمعان في المؤشر
     * العام (قرار 7). النسب التفصيلية عند تعددٍ من achievement_attributions.
     */
    public static function deriveFromTask(\mysqli $conn, array $item, $verifierUserId)
    {
        $co = intval($item['company_id']);
        $ref = (string) intval($item['id']);
        $evidence = trim((string) ($item['evidence_ref'] ?? ''));
        if ($evidence === '') { $evidence = 'قبول المتحقِّق — سجل التدقيق #' . $ref; }
        $out = array();
        $executor = intval($item['assigned_user_id']);
        if ($executor > 0) {
            // نسبٌ مقررةٌ من المكلِّف إن وُجدت (قرار 7) وإلا 100٪ للمنفِّذ
            $shares = array();
            $st = $conn->prepare("SELECT person_user_id, share_pct, share_kind FROM achievement_attributions
                                   WHERE company_id = ? AND work_item_ref = ?");
            $st->bind_param('is', $co, $ref);
            $st->execute();
            $rs = $st->get_result();
            while ($x = $rs->fetch_assoc()) { $shares[] = $x; }
            $st->close();
            if (!$shares) { $shares[] = array('person_user_id' => $executor, 'share_pct' => 100.00, 'share_kind' => 'executive'); }
            foreach ($shares as $s) {
                $out[] = self::derive($conn, array(
                    'company_id' => $co, 'source_kind' => 'task', 'source_ref' => $ref,
                    'person_user_id' => intval($s['person_user_id']),
                    'attribution' => ($s['share_kind'] === 'decision') ? 'decision' : 'executive',
                    'weight_pct' => (float) $s['share_pct'],
                    'title' => (string) $item['title'], 'evidence_ref' => $evidence,
                    'created_by' => intval($verifierUserId),
                ));
            }
        }
        $verifier = intval($verifierUserId);
        if ($verifier > 0 && $verifier !== $executor) {
            $out[] = self::derive($conn, array(
                'company_id' => $co, 'source_kind' => 'task', 'source_ref' => $ref,
                'person_user_id' => $verifier, 'attribution' => 'supervisory',
                'title' => 'تحقق وإغلاق: ' . $item['title'], 'evidence_ref' => $evidence,
                'created_by' => $verifier,
            ));
        }
        return $out;
    }

    /**
     * العكس الآلي (AC-WFM-14): عُكس الأصل أو أُعيد فتحه ⇒ يُعكس الإنجاز
     * ويُحدَّث المؤشر فورًا — لا يبقى في السجل ولا في المؤشر.
     */
    public static function reverseForSource(\mysqli $conn, $companyId, $sourceKind, $sourceRef, $reason, $actor)
    {
        $st = $conn->prepare("UPDATE achievement_records
                                 SET reversed_at = NOW(), reverse_reason = ?
                               WHERE company_id = ? AND source_kind = ? AND source_ref = ?
                                 AND reversed_at IS NULL");
        $reason = mb_substr((string) $reason, 0, 300);
        $co = intval($companyId);
        $sk = (string) $sourceKind;
        $sr = (string) $sourceRef;
        $st->bind_param('siss', $reason, $co, $sk, $sr);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        if ($n > 0) {
            $r = $conn->prepare("SELECT DISTINCT person_user_id FROM achievement_records
                                  WHERE company_id = ? AND source_kind = ? AND source_ref = ?");
            $r->bind_param('iss', $co, $sk, $sr);
            $r->execute();
            $rs = $r->get_result();
            while ($x = $rs->fetch_assoc()) {
                WorkItemService::notifyUser($conn, $co, intval($x['person_user_id']),
                    'عُكس إنجازٌ من سجلك بسبب: ' . $reason, $sk . ' ' . $sr, 'Portal/my_achievement.php', false, intval($actor));
            }
            $r->close();
        }
        return $n;
    }

    /** مؤشر شخصٍ بين تاريخين — الإنجاز الحي وحده (المعكوس خارج المؤشر) */
    public static function personSummary(\mysqli $conn, $companyId, $userId, $from, $to)
    {
        $st = $conn->prepare("SELECT attribution, COUNT(*) n, COALESCE(SUM(weight_pct),0) w
                                FROM achievement_records
                               WHERE company_id = ? AND person_user_id = ?
                                 AND reversed_at IS NULL
                                 AND recognized_at >= ? AND recognized_at < DATE_ADD(?, INTERVAL 1 DAY)
                               GROUP BY attribution");
        $co = intval($companyId);
        $u = intval($userId);
        $st->bind_param('iiss', $co, $u, $from, $to);
        $st->execute();
        $rs = $st->get_result();
        $out = array('executive' => 0, 'supervisory' => 0, 'decision' => 0, 'total' => 0);
        while ($x = $rs->fetch_assoc()) {
            $out[$x['attribution']] = intval($x['n']);
            $out['total'] += intval($x['n']);
        }
        $st->close();
        return $out;
    }
}
