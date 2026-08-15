<?php
/**
 * app/Services/Portal/AchievementService.php — قياسُ الإنجاز بين تاريخين (H-18)
 * ═══════════════════════════════════════════════════════════════════════════
 * USR-01 §6: «مقياسٌ واحدٌ يعمّ كلَّ الفئات بفترةٍ حرة — بالعدد والزمن معًا»
 * والمؤشراتُ السبعة: الطلباتُ · الاعتماداتُ · الزمنُ · الالتزامُ بالمهل ·
 * الإنتاجُ · الانضباطُ · الجودةُ والسلامة.
 *
 * ── ثلاثُ قواعدَ ─────────────────────────────────────────────────────────────
 * ① **التعميمُ بلا تشويه** (U5): مؤشرٌ لا يخصّ الفئةَ يُعلَن «لا ينطبق» —
 *    **لا صفرًا مضلِّلًا**.
 * ② **المصدرُ الواحد**: كلُّ رقمٍ من جدول مالكه (fin_requests · tickets ·
 *    unit_party_awards) — ولا رقمَ يُخترع.
 * ③ **اللقطةُ ببصمتها**: تُحفظ بمفتاح (صفة × من × إلى) وبصمةِ مصادرها —
 *    ومدةٌ تتجاوز سنتين تُقسَّم (422 هنا: تُرفض بطلب تقسيمها).
 */

namespace App\Services\Portal;

require_once __DIR__ . '/../../../includes/catch_log.php';

class AchievementService
{
    /**
     * حسابُ المؤشرات السبعة لصفةٍ بين تاريخين — ويُلقَط اختياريًّا.
     *
     * @return array{ok:bool,code:int,reason:string,metrics:?array,snap_id:?int}
     */
    public static function compute($conn, $gate, $companyId, $capacityId, $from, $to, $persist = false, $actor = 0)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'metrics' => null, 'snap_id' => null);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) || $to < $from) {
            $out['code'] = 422; $out['reason'] = 'مدةٌ غيرُ صالحة (من/إلى)'; return $out;
        }
        // «مدةٌ تتجاوز سنتين → تُقسَّم» (§9.2)
        if (strtotime($to) - strtotime($from) > 2 * 366 * 86400) {
            $out['code'] = 422;
            $out['reason'] = 'المدةُ تتجاوز سنتين — **قسّمها** (USR-01 §9.2: تُقسَّم لا تُحسب جملةً)';
            return $out;
        }

        $cap = null;
        try { $cap = $gate->selectOne('user_capacities', array('where' => array('id' => (int) $capacityId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $cap'); $cap = null; }
        if (!$cap) { $out['code'] = 404; $out['reason'] = 'الصفةُ غيرُ موجودةٍ في نطاقك'; return $out; }

        $accountId = (int) $cap['account_id'];
        $personId  = $cap['person_id'] !== null ? (int) $cap['person_id'] : 0;
        $ctype     = (string) $cap['capacity_type'];
        $co = (int) $companyId;
        $f = $conn->real_escape_string((string) $from);
        $t = $conn->real_escape_string((string) $to);

        $m = array();

        // ── ① الطلبات — من بوابة الطلبات (fin_requests) بمنشئها ─────────────
        $row = self::one($conn, "SELECT COUNT(*) created,
                /* `executed` ليست في التعداد (INJ-0334) — و«المنفَّذُ» في تعدادِ
                   `fin_requests.state` هو `posted` ثم `paid` ثم `collected`. */
                SUM(CASE WHEN state IN ('approved','posted','paid','collected','closed') THEN 1 ELSE 0 END) completed,
                SUM(CASE WHEN state IN ('rejected') THEN 1 ELSE 0 END) rejected,
                SUM(CASE WHEN state IN ('returned') THEN 1 ELSE 0 END) returned
           FROM fin_requests
          WHERE company_id={$co} AND requester_id={$accountId}
            AND DATE(created_at) BETWEEN '{$f}' AND '{$t}'");
        $m['requests'] = array('created' => (int) ($row['created'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'returned' => (int) ($row['returned'] ?? 0));

        // ── ② الاعتمادات — ما بتّ فيه (decided_by) ──────────────────────────
        $row = self::one($conn, "SELECT COUNT(*) decided FROM fin_requests
          WHERE company_id={$co} AND decided_by={$accountId}
            AND DATE(updated_at) BETWEEN '{$f}' AND '{$t}'");
        $m['approvals'] = array('decided' => (int) ($row['decided'] ?? 0));

        // ── ③ الزمن — متوسطُ البتّ وأطولُه بالساعات (من الإنشاء إلى القرار) ──
        $row = self::one($conn, "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at))/60,1) avg_h,
                ROUND(MAX(TIMESTAMPDIFF(MINUTE, created_at, updated_at))/60,1) max_h
           FROM fin_requests
          WHERE company_id={$co} AND decided_by={$accountId} AND state <> 'draft'
            AND DATE(updated_at) BETWEEN '{$f}' AND '{$t}'");
        $m['time'] = array('avg_decision_h' => $row['avg_h'] !== null ? (float) $row['avg_h'] : null,
                           'max_h' => $row['max_h'] !== null ? (float) $row['max_h'] : null);

        // ── ④ الالتزامُ بالمهل — بلاغاتُه المسنَدة وردُّه في مهلتها (SLA) ────
        $row = self::one($conn, "SELECT COUNT(*) total,
                SUM(CASE WHEN first_action_at IS NOT NULL AND response_due_at IS NOT NULL
                          AND first_action_at > response_due_at THEN 1 ELSE 0 END) breached
           FROM tickets
          WHERE company_id={$co} AND assigned_user_id={$accountId}
            AND call_date BETWEEN '{$f}' AND '{$t}'");
        $slaTotal = (int) ($row['total'] ?? 0);
        $m['sla'] = array('total' => $slaTotal, 'breached' => (int) ($row['breached'] ?? 0),
            'rate' => $slaTotal > 0 ? round(1 - ((int) $row['breached'] / $slaTotal), 3) : null);

        // ── ⑤ الإنتاج — للمشغّلين والمشروعيين من حصص الأطراف (M-24/E-09) ────
        if (in_array($ctype, array('operator', 'project_employee', 'technician', 'shift_supervisor'), true)
            && $personId > 0) {
            $row = self::one($conn, "SELECT COUNT(*) awards,
                    ROUND(COALESCE(SUM(qty_due),0),2) qty_due
               FROM unit_party_awards
              WHERE company_id={$co} AND party='employee' AND party_ref={$personId}
                AND DATE(created_at) BETWEEN '{$f}' AND '{$t}' AND deleted_at IS NULL");
            $m['production'] = array('awards' => (int) ($row['awards'] ?? 0),
                                     'qty_due' => (float) ($row['qty_due'] ?? 0));
        } else {
            // ① «لا ينطبق» يُعلَن — لا صفرًا مضلِّلًا (U5)
            $m['production'] = array('not_applicable' => true,
                'note' => 'لا ينطبق على هذه الصفة');
        }

        // ── ⑥ الانضباط — لا حضورَ مركزيًّا في النظام اليوم: يُعلَن لا يُخترع ──
        $m['discipline'] = array('not_applicable' => true,
            'note' => 'لا ينطبق — لا سجلَّ حضورٍ مركزيًّا بعد (يُعلَن ولا يُخترع رقم)');

        // ── ⑦ الجودةُ والسلامة — بلاغاتُه المرفوعة والمغلقة ──────────────────
        $row = self::one($conn, "SELECT COUNT(*) raised,
                /* `'مغلق'` بقيّةٌ من قبلِ تعريبِ التعداد — و`tickets.stage` اليومَ
                   لاتينيٌّ خالص، فالقيمةُ العربيةُ لا تطابق شيئًا أبدًا (INJ-0334). */
                SUM(CASE WHEN stage IN ('closed') OR close_date IS NOT NULL THEN 1 ELSE 0 END) closed
           FROM tickets
          WHERE company_id={$co} AND reporter_user_id={$accountId}
            AND call_date BETWEEN '{$f}' AND '{$t}'");
        $m['quality'] = array('raised' => (int) ($row['raised'] ?? 0),
                              'closed' => (int) ($row['closed'] ?? 0));

        $out['ok'] = true; $out['code'] = 200; $out['metrics'] = $m;

        if ($persist) {
            $json = json_encode($m, JSON_UNESCAPED_UNICODE);
            $fp = sha1($capacityId . '|' . $from . '|' . $to . '|' . $json);
            try {
                $ex = $gate->scopedQuery(array('scope' => array('s' => 'achievement_snapshots')),
                    "SELECT s.id FROM achievement_snapshots s
                      WHERE {TENANT_SCOPE} AND s.capacity_id = ? AND s.period_from = ?
                        AND s.period_to = ? LIMIT 1",
                    array((int) $capacityId, (string) $from, (string) $to));
                if ($ex) {
                    $gate->update('achievement_snapshots', array(
                        'metrics_json' => $json, 'source_fingerprint' => $fp,
                        'computed_at' => date('Y-m-d H:i:s'),
                    ), array('id' => (int) $ex[0]['id']));
                    $out['snap_id'] = (int) $ex[0]['id'];
                } else {
                    $out['snap_id'] = (int) $gate->insert('achievement_snapshots', array(
                        'company_id' => $co, 'person_id' => $personId ?: null,
                        'capacity_id' => (int) $capacityId,
                        'period_from' => (string) $from, 'period_to' => (string) $to,
                        'metrics_json' => $json, 'source_fingerprint' => $fp,
                    ));
                }
            } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'اللقطةُ اختيارية — والحسابُ صحيحٌ بذاته'); /* اللقطةُ اختيارية — والحسابُ صحيحٌ بذاته */ }
        }
        return $out;
    }

    private static function one($conn, $sql)
    {
        try { $r = $conn->query($sql); return $r ? ($r->fetch_assoc() ?: array()) : array(); }
        catch (\Throwable $t) { return array(); }
    }
}
