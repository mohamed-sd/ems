<?php
/**
 * app/Services/Finance/BankReconService.php — المطابقةُ البنكية (H-13)
 * ═══════════════════════════════════════════════════════════════════════════
 * SPEC-01 #19: «**استيرادٌ Idempotent بمفتاح السطر** · **المضاهاةُ الآلية
 * بقاعدتها** · **قرارُ الفرق يولّد قيدَ تسويةٍ بمرجعه** · **فحصُ الإقفال يمنع
 * وفرقٌ مفتوح**» · «المضاهاةُ الآلية بقواعد (**المبلغ + التاريخ ± أيامٍ +
 * المرجع**)» · «أثر: **قيودُ التسوية فقط — بمرجع الفرق**».
 *
 * ── خمسُ قواعد ──────────────────────────────────────────────────────────────
 * ① **الاستيرادُ عاطلٌ بمفتاح السطر**: بصمةُ (كشف × مرجع × تاريخ × اتجاه ×
 *    مبلغ) — فالملفُّ نفسُه يُستورد مرارًا **بصفر سطرٍ مكرر**.
 * ② **والمضاهاةُ بقاعدةٍ تُكتب في السطر**: المرجعُ أولًا (اليقين)، ثم (المبلغُ
 *    + التاريخُ ± أيام) — **والقاعدةُ المطبَّقةُ تُحفظ** فيُعرف لماذا طابق.
 * ③ **ولا يُطابَق سطرٌ مرتين ولا سندٌ مرتين**: `UNIQUE` على سطر البنك، وفحصٌ
 *    على السند قبل الربط.
 * ④ **والفرقُ يُفتح بسببٍ ويُحسم بقرار**: فرقٌ بلا سببٍ **مستحيلٌ بنيويًّا**،
 *    والحسمُ **يولّد قيدَ تسويةٍ بمرجع الفرق** لا يمسّ السندَ الأصلي.
 * ⑤ **ولا إقفالَ وفرقٌ مفتوح**: `close` ترفض **423** وتسمّي الفروقَ المفتوحة.
 */

namespace App\Services\Finance;

class BankReconService
{
    /** نافذةُ التسامح في التاريخ للمضاهاة بالمبلغ (§19: «التاريخ ± أيامٍ»). */
    const DATE_WINDOW_DAYS = 3;

    /**
     * اتجاهُ سطر البنك المقابلُ لاتجاه سند النظام.
     * ⚠ گوتشا مقيسةٌ أثناء البناء: `fin_payments.direction` قيمُه
     * **`disbursement`/`collection`** لا `payment` — و**ENUM يبتلع الخطأ صامتًا**،
     * فبذرٌ بـ`'payment'` يُكتب فارغًا والمضاهاةُ تفشل **بلا رسالة**.
     */
    const DIRECTION_MAP = array('deposit' => 'collection', 'withdrawal' => 'disbursement');

    // ═════════════════════════════════════════════════════════════════════
    // ① الاستيراد — «Idempotent بمفتاح السطر»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @param array $head {bank_account_id, statement_ref, period_from, period_to,
     *                     opening_balance, closing_balance, currency}
     * @param array $lines كلٌّ: {txn_date, description, direction, amount, bank_ref, running_balance}
     * @return array{ok:bool,code:int,reason:string,statement_id:?int,
     *               inserted:int,skipped:int,rejected:array}
     */
    public static function import($conn, $gate, $companyId, array $head, array $lines, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'statement_id' => null,
                     'inserted' => 0, 'skipped' => 0, 'rejected' => array());

        $acc = (int) (isset($head['bank_account_id']) ? $head['bank_account_id'] : 0);
        $ref = trim((string) (isset($head['statement_ref']) ? $head['statement_ref'] : ''));
        $from = (string) (isset($head['period_from']) ? $head['period_from'] : '');
        $to   = (string) (isset($head['period_to']) ? $head['period_to'] : '');
        if ($acc <= 0)  { $out['code'] = 422; $out['reason'] = 'الحسابُ البنكيُّ إلزامي'; return $out; }
        if ($ref === '') {
            $out['code'] = 422;
            $out['reason'] = '**مرجعُ الكشف إلزامي** — وبلا مرجعٍ لا مفتاحَ عطالةٍ للاستيراد';
            return $out;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            $out['code'] = 422; $out['reason'] = 'مدى الكشف غيرُ صالح'; return $out;
        }

        // رأسُ الكشف — عاطلٌ بمفتاحه: الموجودُ يُعاد لا يُكرَّر
        $stmt = null;
        try {
            $stmt = $gate->selectOne('bank_statements', array(
                'whereRaw' => 'bank_account_id = ? AND statement_ref = ?',
                'params' => array($acc, $ref)));
        } catch (\Throwable $t) { $stmt = null; }

        if ($stmt && (string) $stmt['state'] === 'closed') {
            $out['code'] = 423; $out['statement_id'] = (int) $stmt['id'];
            $out['reason'] = 'الكشفُ مقفلٌ — لا استيرادَ عليه («التصحيحُ بعكسٍ لا بمحو»)';
            return $out;
        }

        $sid = $stmt ? (int) $stmt['id'] : null;
        if (!$sid) {
            try {
                $sid = (int) $gate->insert('bank_statements', array(
                    'bank_account_id' => $acc, 'statement_ref' => $ref,
                    'period_from' => $from, 'period_to' => $to,
                    'opening_balance' => round((float) (isset($head['opening_balance']) ? $head['opening_balance'] : 0), 2),
                    'closing_balance' => round((float) (isset($head['closing_balance']) ? $head['closing_balance'] : 0), 2),
                    'currency' => isset($head['currency']) && $head['currency'] !== ''
                                  ? (string) $head['currency'] : 'SDG',
                    'state' => 'imported',
                    'note' => isset($head['note']) ? mb_substr((string) $head['note'], 0, 200) : null,
                    'created_by' => (int) $actor ?: null,
                ));
            } catch (\Throwable $t) {
                $out['code'] = 422; $out['reason'] = 'تعذّر إنشاءُ الكشف: ' . $t->getMessage(); return $out;
            }
        }
        $out['statement_id'] = $sid;

        // ترقيمُ الأسطر **يتابع** ما في الكشف: دفعةٌ ثانيةٌ لا تُعيد الترقيم من 1
        $no = 0;
        try {
            $mx = $gate->scopedQuery(array('scope' => array('l' => 'bank_statement_lines')),
                "SELECT COALESCE(MAX(l.line_no),0) AS m FROM bank_statement_lines l
                  WHERE {TENANT_SCOPE} AND l.statement_id = ?", array($sid));
            $no = $mx ? (int) $mx[0]['m'] : 0;
        } catch (\Throwable $t) { $no = 0; }

        foreach ($lines as $l) {
            $no++;
            $date = (string) (isset($l['txn_date']) ? $l['txn_date'] : '');
            $dir  = (string) (isset($l['direction']) ? $l['direction'] : '');
            $amt  = round((float) (isset($l['amount']) ? $l['amount'] : 0), 2);
            $bref = trim((string) (isset($l['bank_ref']) ? $l['bank_ref'] : ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || !in_array($dir, array('deposit', 'withdrawal'), true) || $amt <= 0) {
                $out['rejected'][] = array('line_no' => $no, 'reason' => 'سطرٌ ناقصُ التاريخ أو الاتجاه أو المبلغ');
                continue;
            }
            if ($bref === '') {
                // «Idempotent **بمفتاح السطر**» — وبلا مرجعٍ لا مفتاح
                $out['rejected'][] = array('line_no' => $no,
                    'reason' => '**سطرٌ بلا مرجعٍ بنكيّ** — ولا يُخترع له مفتاح');
                continue;
            }
            $key = sha1($sid . '|' . $bref . '|' . $date . '|' . $dir . '|' . number_format($amt, 2, '.', ''));
            try {
                $gate->insert('bank_statement_lines', array(
                    'statement_id' => $sid, 'line_no' => $no, 'txn_date' => $date,
                    'description' => isset($l['description']) ? mb_substr((string) $l['description'], 0, 255) : null,
                    'direction' => $dir, 'amount' => $amt,
                    'running_balance' => isset($l['running_balance']) && $l['running_balance'] !== ''
                                         ? round((float) $l['running_balance'], 2) : null,
                    'bank_ref' => mb_substr($bref, 0, 80), 'line_key' => $key,
                    'match_state' => 'unmatched',
                ));
                $out['inserted']++;
            } catch (\Throwable $t) {
                // UQ على `line_key` — «الملفُّ نفسُه يُستورد مرارًا بصفر سطرٍ مكرر»
                $out['skipped']++;
            }
        }

        try {
            $n = $gate->count('bank_statement_lines', array('where' => array('statement_id' => $sid)));
            $gate->update('bank_statements', array('lines_count' => (int) $n), array('id' => $sid));
        } catch (\Throwable $t) { /* عدّادٌ لا يوقف */ }

        self::audit($conn, $companyId, $actor, 'import', $sid, array(),
            array('inserted' => $out['inserted'], 'skipped' => $out['skipped'],
                  'rejected' => count($out['rejected'])));

        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'الكشف #' . $sid . ': أُدرج ' . $out['inserted'] . ' سطرًا · '
                       . 'مكرَّرٌ متخطًّى ' . $out['skipped'] . ' · مرفوضٌ ' . count($out['rejected']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② المضاهاةُ الآلية — «بقاعدتها»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,matched:int,differences:int,none:int}
     */
    public static function autoMatch($conn, $gate, $companyId, $statementId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'matched' => 0,
                     'differences' => 0, 'none' => 0);
        $stmt = self::statementOf($gate, (int) $statementId);
        if (!$stmt) { $out['code'] = 404; $out['reason'] = 'الكشفُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $stmt['state'] === 'closed') {
            $out['code'] = 423; $out['reason'] = 'الكشفُ مقفل'; return $out;
        }

        $lines = array();
        try {
            $lines = $gate->scopedQuery(array('scope' => array('l' => 'bank_statement_lines')),
                "SELECT l.* FROM bank_statement_lines l
                  WHERE {TENANT_SCOPE} AND l.statement_id = ? AND l.match_state = 'unmatched'
                  ORDER BY l.line_no", array((int) $statementId));
        } catch (\Throwable $t) { $lines = array(); }

        foreach ($lines as $l) {
            $cand = self::findCounterpart($gate, $l);
            if ($cand === null) {
                // «بلا نظير» — يُعلَن ولا يُخترع له سند
                self::writeMatch($gate, $companyId, $l, null, 'none',
                    'لا نظيرَ في سندات النظام (مرجعًا ولا مبلغًا بتاريخه ± '
                    . self::DATE_WINDOW_DAYS . ' أيام)', 0.0, 'no_counterpart', $actor);
                $out['none']++;
                continue;
            }
            $sysAmt = round((float) $cand['row']['amount'], 2);
            $bnkAmt = round((float) $l['amount'], 2);
            $isEqual = (abs($bnkAmt - $sysAmt) < 0.005);
            self::writeMatch($gate, $companyId, $l, (int) $cand['row']['id'], 'auto',
                $cand['rule'], $sysAmt, $isEqual ? 'matched' : 'difference', $actor);
            if ($isEqual) { $out['matched']++; } else { $out['differences']++; }
        }

        try { $gate->update('bank_statements', array('state' => 'matching'), array('id' => (int) $statementId)); }
        catch (\Throwable $t) { /* لا يوقف */ }

        self::audit($conn, $companyId, $actor, 'auto_match', (int) $statementId, array(),
            array('matched' => $out['matched'], 'differences' => $out['differences'], 'none' => $out['none']));

        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'مطابقٌ ' . $out['matched'] . ' · فرقٌ ' . $out['differences']
                       . ' · بلا نظير ' . $out['none'];
        return $out;
    }

    /**
     * النظيرُ بقاعدتين مرتَّبتين: **المرجعُ أولًا** (يقين) ثم (المبلغ + التاريخ ± أيام).
     * @return array{row:array,rule:string}|null
     */
    public static function findCounterpart($gate, array $line)
    {
        $dir = (string) $line['direction'];
        $sysDir = isset(self::DIRECTION_MAP[$dir]) ? self::DIRECTION_MAP[$dir] : 'collection';
        $bref = (string) $line['bank_ref'];
        $date = (string) $line['txn_date'];
        $amt  = round((float) $line['amount'], 2);
        $lo = date('Y-m-d', strtotime($date . ' -' . self::DATE_WINDOW_DAYS . ' day'));
        $hi = date('Y-m-d', strtotime($date . ' +' . self::DATE_WINDOW_DAYS . ' day'));

        // ① بالمرجع البنكي — أقوى القواعد، ولا يشترط تطابقَ المبلغ (فالفرقُ يُعلَن)
        $rows = self::payments($gate,
            "p.direction = ? AND p.bank_ref = ? AND p.bank_ref <> 'legacy_no_ref'",
            array($sysDir, $bref));
        $free = self::firstFree($gate, $rows);
        if ($free) { return array('row' => $free, 'rule' => 'المرجعُ البنكيُّ مطابق: ' . $bref); }

        // ② بالمبلغ والتاريخ ± أيام
        $rows = self::payments($gate,
            "p.direction = ? AND ROUND(p.amount,2) = ? AND
             DATE(COALESCE(p.received_on, p.created_at)) BETWEEN ? AND ?",
            array($sysDir, $amt, $lo, $hi));
        $free = self::firstFree($gate, $rows);
        if ($free) {
            return array('row' => $free,
                'rule' => 'المبلغُ ' . $amt . ' والتاريخُ ضمن ±' . self::DATE_WINDOW_DAYS . ' أيام');
        }
        return null;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الفرقُ: يُفتح بسببٍ ويُحسم بقرارٍ يولّد قيدًا
    // ═════════════════════════════════════════════════════════════════════

    /** فتحُ فرقٍ **بسبب** (§19). @return array{ok:bool,code:int,reason:string} */
    public static function openDifference($conn, $gate, $companyId, $matchId, $why, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $m = self::matchOf($gate, (int) $matchId);
        if (!$m) { $out['code'] = 404; $out['reason'] = 'المضاهاةُ غيرُ موجودة'; return $out; }
        $r = trim((string) $why);
        if ($r === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببُ الفرق إلزامي** — «فتحُ فرقٍ بسبب» (§19)، وفرقٌ بلا سببٍ لا يُحسم';
            return $out;
        }
        if (in_array((string) $m['state'], array('resolved', 'rejected'), true)) {
            $out['code'] = 409; $out['reason'] = 'المضاهاةُ محسومةٌ سلفًا (' . $m['state'] . ')'; return $out;
        }
        try {
            $gate->update('bank_recon_matches',
                array('state' => 'open_difference', 'difference_reason' => mb_substr($r, 0, 255)),
                array('id' => (int) $matchId));
            $gate->update('bank_statement_lines', array('match_state' => 'difference'),
                array('id' => (int) $m['statement_line_id']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'open_difference', (int) $matchId,
            array('state' => $m['state']), array('state' => 'open_difference', 'reason' => $r));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * حسمُ الفرق — «**قرارُ الفرق يولّد قيدَ تسويةٍ بمرجعه**».
     * @return array{ok:bool,code:int,reason:string,event_id:?int,amount:float}
     */
    public static function resolveDifference($conn, $gate, $companyId, $matchId, $decision, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'event_id' => null, 'amount' => 0.0);
        $m = self::matchOf($gate, (int) $matchId);
        if (!$m) { $out['code'] = 404; $out['reason'] = 'المضاهاةُ غيرُ موجودة'; return $out; }
        if ((string) $m['state'] !== 'open_difference') {
            $out['code'] = 409;
            $out['reason'] = 'الحسمُ للفروق المفتوحة وحدَها (حالُها: ' . $m['state'] . ')';
            return $out;
        }
        if ($m['adjustment_event_id'] !== null) {
            $out['code'] = 409; $out['event_id'] = (int) $m['adjustment_event_id'];
            $out['reason'] = 'للفرق قيدُ تسويةٍ قائمٌ #' . (int) $m['adjustment_event_id'];
            return $out;
        }
        $decision = (string) $decision;
        if (!in_array($decision, array('adjust', 'reject'), true)) {
            $out['code'] = 422; $out['reason'] = 'القرار: تسويةٌ (adjust) أو رفض (reject)'; return $out;
        }
        $why = trim((string) $note);
        if ($why === '') {
            $out['code'] = 422; $out['reason'] = '**قرارُ الفرق يُسجَّل بسببه** — ولا يكون صامتًا'; return $out;
        }

        $diff = round((float) $m['difference'], 2);
        $eventId = null;
        try {
            $gate->runInTransaction(function ($g) use (&$eventId, $conn, $companyId, $m, $matchId,
                                                       $decision, $why, $diff, $actor) {
                if ($decision === 'adjust' && abs($diff) >= 0.005) {
                    // «أثر: **قيودُ التسوية فقط — بمرجع الفرق**» — والسندُ الأصليُّ لا يُمسّ
                    $eventId = self::publishAdjustment($conn, $companyId, (int) $matchId, $diff, $why, $actor);
                }
                $g->update('bank_recon_matches', array(
                    'state' => ($decision === 'adjust') ? 'resolved' : 'rejected',
                    'adjustment_event_id' => $eventId,
                    'difference_reason' => mb_substr((string) $m['difference_reason'] . ' · قرار: ' . $why, 0, 255),
                    'decided_by' => (int) $actor ?: null,
                    'decided_at' => date('Y-m-d H:i:s'),
                ), array('id' => (int) $matchId));
                $g->update('bank_statement_lines',
                    array('match_state' => ($decision === 'adjust') ? 'matched' : 'no_counterpart'),
                    array('id' => (int) $m['statement_line_id']));
            }, 'حسم فرق مطابقة ' . $matchId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحسم: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'resolve_difference', (int) $matchId,
            array('state' => 'open_difference'),
            array('state' => $decision, 'event_id' => $eventId, 'difference' => $diff, 'note' => $why));

        $out['ok'] = true; $out['code'] = 200; $out['event_id'] = $eventId; $out['amount'] = $diff;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ الإقفال — «لا إقفالَ وفرقٌ مفتوح»
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,open:array} */
    public static function close($conn, $gate, $companyId, $statementId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'open' => array());
        $stmt = self::statementOf($gate, (int) $statementId);
        if (!$stmt) { $out['code'] = 404; $out['reason'] = 'الكشفُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $stmt['state'] === 'closed') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مقفلٌ سلفًا'; return $out;
        }

        $open = array();
        try {
            $open = $gate->scopedQuery(
                array('scope' => array('m' => 'bank_recon_matches'),
                      'enrich' => array('l' => 'bank_statement_lines')),
                "SELECT m.id, m.difference, m.difference_reason, l.line_no, l.bank_ref
                   FROM bank_recon_matches m
                   LEFT JOIN bank_statement_lines l ON l.id = m.statement_line_id
                  WHERE {TENANT_SCOPE} AND l.statement_id = ? AND m.state = 'open_difference'
                  ORDER BY l.line_no", array((int) $statementId));
        } catch (\Throwable $t) { $open = array(); }

        // «كشفٌ نصفُ مضاهًى ليس مقفلًا»: كلُّ سطرٍ غيرِ `matched` مانعٌ —
        // غيرُ المضاهى · وذو الفرق الذي لم يُبتّ · وبلا النظير.
        $pending = 0;
        try {
            $u = $gate->scopedQuery(array('scope' => array('l' => 'bank_statement_lines')),
                "SELECT COUNT(*) AS n FROM bank_statement_lines l
                  WHERE {TENANT_SCOPE} AND l.statement_id = ? AND l.match_state <> 'matched'",
                array((int) $statementId));
            $pending = $u ? (int) $u[0]['n'] : 0;
        } catch (\Throwable $t) { $pending = 0; }

        if ($open || $pending > 0) {
            $out['code'] = 423; $out['open'] = $open;
            $names = array();
            foreach ($open as $o) { $names[] = 'سطر ' . (int) $o['line_no'] . ' (فرق ' . $o['difference'] . ')'; }
            $out['reason'] = '**لا إقفالَ وفرقٌ مفتوح** (§19): '
                . (count($open) > 0 ? (count($open) . ' فرقًا مفتوحًا — ' . implode(' · ', array_slice($names, 0, 5))) : '')
                . ($pending > 0 ? ((count($open) > 0 ? ' · ' : '') . $pending . ' سطرًا لم يستقر على «مطابق»') : '');
            return $out;
        }

        try {
            $gate->update('bank_statements', array(
                'state' => 'closed', 'closed_at' => date('Y-m-d H:i:s'),
                'closed_by' => (int) $actor ?: null,
            ), array('id' => (int) $statementId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإقفال: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'close', (int) $statementId,
            array('state' => $stmt['state']), array('state' => 'closed'));
        $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'أُقفل الكشفُ بصفر فرقٍ مفتوح';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءات
    // ═════════════════════════════════════════════════════════════════════

    /** نسبةُ المطابقة والفروقُ المفتوحة (بطاقتا §19). */
    public static function stats($gate, $statementId)
    {
        $out = array('lines' => 0, 'matched' => 0, 'difference' => 0,
                     'none' => 0, 'unmatched' => 0, 'rate' => 0.0, 'open_diff' => 0);
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'bank_statement_lines')),
                "SELECT l.match_state, COUNT(*) AS n FROM bank_statement_lines l
                  WHERE {TENANT_SCOPE} AND l.statement_id = ? GROUP BY l.match_state",
                array((int) $statementId));
            foreach ($rows as $r) {
                $n = (int) $r['n']; $out['lines'] += $n;
                $k = (string) $r['match_state'];
                if ($k === 'matched') { $out['matched'] = $n; }
                elseif ($k === 'difference') { $out['difference'] = $n; }
                elseif ($k === 'no_counterpart') { $out['none'] = $n; }
                else { $out['unmatched'] = $n; }
            }
            $d = $gate->scopedQuery(
                array('scope' => array('m' => 'bank_recon_matches'),
                      'enrich' => array('l2' => 'bank_statement_lines')),
                "SELECT COUNT(*) AS n FROM bank_recon_matches m
                   LEFT JOIN bank_statement_lines l2 ON l2.id = m.statement_line_id
                  WHERE {TENANT_SCOPE} AND l2.statement_id = ? AND m.state = 'open_difference'",
                array((int) $statementId));
            $out['open_diff'] = $d ? (int) $d[0]['n'] : 0;
        } catch (\Throwable $t) { /* قراءةٌ لا توقف */ }
        if ($out['lines'] > 0) { $out['rate'] = round($out['matched'] * 100.0 / $out['lines'], 2); }
        return $out;
    }

    public static function statements($gate, $limit = 60)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('s' => 'bank_statements'),
                      'enrich' => array('a' => 'fin_bank_accounts')),
                "SELECT s.*, a.name AS account_name, a.bank_name
                   FROM bank_statements s
                   LEFT JOIN fin_bank_accounts a ON a.id = s.bank_account_id
                  WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0
                  ORDER BY s.period_to DESC, s.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    public static function linesOf($gate, $statementId, $limit = 500)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('l' => 'bank_statement_lines'),
                      'enrich' => array('m' => 'bank_recon_matches')),
                "SELECT l.*, m.id AS match_id, m.payment_id, m.match_kind, m.rule_note,
                        m.bank_amount, m.system_amount, m.difference, m.state AS match_row_state,
                        m.difference_reason, m.adjustment_event_id
                   FROM bank_statement_lines l
                   LEFT JOIN bank_recon_matches m ON m.statement_line_id = l.id
                  WHERE {TENANT_SCOPE} AND l.statement_id = ?
                  ORDER BY l.line_no LIMIT " . max(1, (int) $limit), array((int) $statementId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function statementOf($gate, $id)
    {
        try { return $gate->selectOne('bank_statements', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    public static function matchOf($gate, $id)
    {
        try { return $gate->selectOne('bank_recon_matches', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function payments($gate, $whereRaw, array $params)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('p' => 'fin_payments')),
                "SELECT p.id, p.payment_no, p.amount, p.currency, p.bank_ref, p.direction,
                        DATE(COALESCE(p.received_on, p.created_at)) AS p_date
                   FROM fin_payments p
                  WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND " . $whereRaw . "
                  ORDER BY p.id", $params);
        } catch (\Throwable $t) { error_log('H-13 payments: ' . $t->getMessage()); return array(); }
    }

    /** «ولا سندٌ يُطابَق مرتين» — أولُ سندٍ غيرِ مرتبطٍ بمضاهاةٍ حية. */
    private static function firstFree($gate, array $rows)
    {
        foreach ($rows as $r) {
            $taken = null;
            try {
                $taken = $gate->selectOne('bank_recon_matches', array(
                    'whereRaw' => 'payment_id = ? AND state <> ?',
                    'params' => array((int) $r['id'], 'rejected')));
            } catch (\Throwable $t) { $taken = null; }
            if (!$taken) { return $r; }
        }
        return null;
    }

    /**
     * كتابةُ المضاهاة — وحالاتُها الثلاثُ نصُّ §19: «مطابق · فرقٌ بقيمته · بلا نظير».
     * · **مطابق**: المبلغان متساويان ⇒ الصفُّ `matched` والسطرُ `matched`.
     * · **فرقٌ بقيمته**: نظيرٌ وُجد ومبلغُه مختلف ⇒ الصفُّ `matched` **والفرقُ
     *   ظاهرٌ في العمود المولَّد**، والسطرُ `difference` — **والقرارُ بيد إنسان**
     *   (`openDifference` بسببه)، فلا تُملأ حجّةٌ آليًّا نيابةً عنه.
     * · **بلا نظير**: صفٌّ بـ`payment_id = NULL` وحالتُه **`open_difference`**
     *   بسببٍ آليٍّ **يصف الواقعةَ لا يقرّر فيها** — فيحجب الإقفالَ حتى يُحسم.
     */
    private static function writeMatch($gate, $companyId, array $line, $paymentId, $kind,
                                       $rule, $sysAmount, $lineState, $actor)
    {
        $noCounterpart = ($paymentId === null);
        try {
            $gate->insert('bank_recon_matches', array(
                'statement_line_id' => (int) $line['id'],
                'payment_id' => $noCounterpart ? null : (int) $paymentId,
                'match_kind' => $kind,
                'rule_note' => mb_substr((string) $rule, 0, 200),
                'bank_amount' => round((float) $line['amount'], 2),
                'system_amount' => round((float) $sysAmount, 2),
                'state' => $noCounterpart ? 'open_difference' : 'matched',
                'difference_reason' => $noCounterpart
                    ? mb_substr('بلا نظير: ' . (string) $rule, 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) { return false; }
        try {
            $gate->update('bank_statement_lines', array('match_state' => $lineState),
                array('id' => (int) $line['id']));
        } catch (\Throwable $t) { /* لا يوقف */ }
        return true;
    }

    /** قيدُ التسوية — **بمرجع الفرق** لا بمرجع السند. */
    private static function publishAdjustment($conn, $companyId, $matchId, $diff, $why, $actor)
    {
        require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
        $res = \App\Core\EventPublisher::publish($conn, array(
            'event_key'         => 'finance.recon_adjustment.posted',
            'category'          => 'financial',
            'source_module'     => 'treasury',
            'company_id'        => (int) $companyId,
            'entity_type'       => 'bank_recon_match',
            'entity_id'         => (int) $matchId,
            'occurred_at'       => gmdate('Y-m-d H:i:s'),
            'created_by'        => (int) $actor ?: 1,
            'idempotency_key'   => 'recon_adj:' . (int) $matchId,
            'legacy_event_type' => 'settlement',
            'amount'            => round(abs($diff), 2),
            'currency'          => 'SDG',
            'notes'             => 'قيدُ تسويةِ فرقٍ بنكيٍّ — مضاهاة #' . (int) $matchId,
            'payload'           => array('match_id' => (int) $matchId, 'difference' => $diff,
                                         'decision_note' => $why),
        ));
        return (is_array($res) && isset($res['id'])) ? (int) $res['id'] : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'finance', 'bank_statements', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
