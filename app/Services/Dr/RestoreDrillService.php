<?php
/**
 * RestoreDrillService — محاضرُ تجربةِ الاستعادة (ENG-01 · PR-01..PR-06)
 * ───────────────────────────────────────────────────────────────────────────
 * «◆ فنسخةٌ لم تُختبر ليست نسخة» — والمحضرُ يحمل زمنَها والدقيقةَ التي استُعيد
 * إليها وما عاد وما لم يعد. والحكمُ من القياسِ لا من الادعاء: chk_drill_pass
 * في القاعدةِ يرفض حكمًا بالنجاحِ ما لم يكن ما بعدَ اللحظةِ صفرًا وRPO مقيسًا.
 *
 * «◆ ومن لا يعلن هدفَه لا يعرف أوصله أم لا» — فـrpo_target_minutes معلَنٌ
 * وrpo_actual_minutes مقيسٌ في كلِّ تجربة.
 */

namespace App\Services\Dr;

class RestoreDrillService
{
    /**
     * تسجيلُ محضرِ تجربة (dr.restore.drill).
     * لا تكتب الشاشةُ في الجدول — تنادي هذه، وهذه وحدَها تكتب.
     *
     * @return array{ok:bool, reason:string, id?:int, drill_no?:string}
     */
    public static function record(\mysqli $conn, array $in)
    {
        $co      = (int) ($in['company_id'] ?? 1);
        $kind    = (string) ($in['drill_kind'] ?? 'pitr');
        $start   = (string) ($in['started_at'] ?? '');
        $finish  = isset($in['finished_at']) && $in['finished_at'] !== '' ? (string) $in['finished_at'] : null;
        $target  = (string) ($in['target_point'] ?? '');
        $rpoTgt  = (int) ($in['rpo_target_minutes'] ?? 15);
        $rpoAct  = isset($in['rpo_actual_minutes']) && $in['rpo_actual_minutes'] !== ''
            ? (int) $in['rpo_actual_minutes'] : null;
        $rto     = isset($in['rto_actual_seconds']) && $in['rto_actual_seconds'] !== ''
            ? (int) $in['rto_actual_seconds'] : null;
        $before  = isset($in['rows_before']) ? (int) $in['rows_before'] : null;
        $expGone = isset($in['rows_after_expected_gone']) ? (int) $in['rows_after_expected_gone'] : null;
        $actual  = isset($in['rows_after_actual']) ? (int) $in['rows_after_actual'] : null;
        $verdict = isset($in['verdict']) && $in['verdict'] !== '' ? (string) $in['verdict'] : null;
        $blFirst = isset($in['binlog_first_file']) ? (string) $in['binlog_first_file'] : null;
        $blLast  = isset($in['binlog_last_file']) ? (string) $in['binlog_last_file'] : null;
        $evid    = isset($in['evidence_path']) ? (string) $in['evidence_path'] : null;
        $runbook = isset($in['runbook_ref']) ? (string) $in['runbook_ref'] : null;
        $note    = isset($in['operator_note']) ? (string) $in['operator_note'] : null;
        $by      = (int) ($in['actor'] ?? 0);
        $byRole  = (int) ($in['actor_role'] ?? 0);

        if ($start === '')  { return array('ok' => false, 'reason' => 'وقت بدء التجربة إلزامي'); }
        if ($target === '') { return array('ok' => false, 'reason' => 'الدقيقة المستعاد إليها إلزامية — ولا تجربة بلا نقطة زمن'); }
        if ($verdict !== null && !in_array($verdict, array('pass', 'fail', 'aborted'), true)) {
            return array('ok' => false, 'reason' => 'حكم غير معروف');
        }
        if ($verdict === 'pass' && ($actual === null || $actual !== 0)) {
            return array('ok' => false,
                'reason' => 'لا يحكم بالنجاح وما بعد اللحظة عاد — والقيد يرفضه في القاعدة أيضا');
        }

        // رقمُ المحضر: خادميٌّ ومتسلسلٌ لكلِّ كيان — لا يُملى من الشاشة
        $seq = (int) $conn->query(
            "SELECT COUNT(*)+1 FROM `dr_drills` WHERE `company_id`=" . $co
        )->fetch_row()[0];
        $drillNo = 'DR-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $st = $conn->prepare(
            "INSERT INTO `dr_drills`
                (`company_id`,`drill_no`,`drill_kind`,`started_at`,`finished_at`,`target_point`,
                 `rpo_target_minutes`,`rpo_actual_minutes`,`rto_actual_seconds`,
                 `rows_before`,`rows_after_expected_gone`,`rows_after_actual`,`verdict`,
                 `binlog_first_file`,`binlog_last_file`,`evidence_path`,`runbook_ref`,
                 `operator_note`,`created_by`,`created_by_role`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        if (!$st) { return array('ok' => false, 'reason' => 'prepare: ' . $conn->error); }
        $st->bind_param(
            'isssssiiiiiissssssii',
            $co, $drillNo, $kind, $start, $finish, $target,
            $rpoTgt, $rpoAct, $rto,
            $before, $expGone, $actual, $verdict,
            $blFirst, $blLast, $evid, $runbook,
            $note, $by, $byRole
        );
        if (!$st->execute()) {
            $e = $st->error; $st->close();
            return array('ok' => false, 'reason' => $e);
        }
        $id = (int) $conn->insert_id;
        $st->close();
        return array('ok' => true, 'reason' => 'سجل المحضر', 'id' => $id, 'drill_no' => $drillNo);
    }

    /** آخرُ تجربةٍ ناجحةٍ — وعليها يقوم شرطُ «مُجرَّبةٌ شهريًّا» (PR-02). */
    public static function lastPass(\mysqli $conn, $companyId = 1)
    {
        $st = $conn->prepare(
            "SELECT * FROM `dr_drills`
              WHERE `company_id`=? AND `verdict`='pass'
              ORDER BY `started_at` DESC LIMIT 1");
        $st->bind_param('i', $companyId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ?: null;
    }

    /** أيامٌ منذ آخرِ تجربةٍ ناجحة — وnull أي لم تُجرَّب قطُّ. */
    public static function daysSinceLastPass(\mysqli $conn, $companyId = 1)
    {
        $r = self::lastPass($conn, $companyId);
        if (!$r) { return null; }
        $st = $conn->prepare("SELECT TIMESTAMPDIFF(DAY, ?, NOW())");
        $s = (string) $r['started_at'];
        $st->bind_param('s', $s);
        $st->execute();
        $d = (int) $st->get_result()->fetch_row()[0];
        $st->close();
        return $d;
    }
}
