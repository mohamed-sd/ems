<?php
/**
 * app/Services/Revenue/ClaimDisputeService.php — نزاعُ بند المستخلص (M-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-03 §3-⑤: «**اعتراضُ العميل على بندٍ يحوّله متنازَعًا عليه بسببٍ ومستند**
 * — **والبقيةُ تمضي للفوترة، ولا يُجمَّد المستخلصُ كلُّه**».
 * §4: «بندٌ محددٌ **بسببٍ ومستند** · Disputed **بعدّاده** — والبقيةُ تمضي».
 *
 * ── أربعُ قواعدَ تحكم كلَّ نزاعٍ هنا ────────────────────────────────────────
 * ① **بسببٍ ومستند**: 422 خدمةً و`CHECK` بنيويًّا — واعتراضٌ بلا بيّنةٍ **رأي**.
 * ② **والبقيةُ تمضي**: النزاعُ **يخصّ بندَه** ولا يجمّد المستخلص — والمجاميعُ
 *    تُعاد بلا احتسابه (سلوكٌ قائمٌ في `claim_recalc` يُصان لا يُعاد بناؤه).
 * ③ **والحسمُ قرارٌ يُسجَّل لا وسمٌ يُمحى**: `upheld` يُسقط البندَ نهائيًّا،
 *    و`rejected` **يعيده محتسَبًا** — وكلاهما بسببٍ مكتوبٍ وحاسمٍ معلوم.
 * ④ **ولا نزاعَ على مفوتَر**: بعد صدور الفاتورة **التصحيحُ بإشعار** (M-03) —
 *    فالنزاعُ بابُ ما قبلَ المستند لا بابٌ لنقضه.
 */

namespace App\Services\Revenue;

class ClaimDisputeService
{
    const RESOLUTIONS = array('upheld', 'rejected');
    const RESOLUTION_LABELS = array('upheld' => 'أُقرَّ الاعتراض (البند يسقط)',
                                    'rejected' => 'رُدَّ الاعتراض (البند يعود)');
    const LEGACY_REF = 'legacy_no_ref';

    // ═════════════════════════════════════════════════════════════════════
    // ① الرفع
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,open_count:int} */
    public static function raise($conn, $gate, $companyId, $lineId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'open_count' => 0);
        $line = self::line($gate, (int) $lineId);
        if (!$line) { $out['code'] = 404; $out['reason'] = 'البندُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $line['dispute_state'] === 'open') {
            $out['code'] = 409; $out['reason'] = 'البندُ متنازَعٌ عليه سلفًا — والحسمُ قرارٌ لا رفعٌ ثانٍ'; return $out;
        }

        $block = self::assertDisputable($gate, (int) $line['claim_id']);
        if ($block !== null) { return array_merge($out, $block); }

        $reason = isset($args['reason']) ? trim((string) $args['reason']) : '';
        $doc    = isset($args['doc_ref']) ? trim((string) $args['doc_ref']) : '';
        if ($reason === '' || $doc === '') {
            $out['code'] = 422;
            $out['reason'] = '**الاعتراضُ بسببٍ ومستندٍ معًا** — «بندٌ محددٌ بسببٍ ومستند» (ENT-03 §3-⑤)؛ '
                           . 'واعتراضٌ بلا بيّنةٍ رأيٌ لا نزاع';
            return $out;
        }
        if ($doc === self::LEGACY_REF || $reason === self::LEGACY_REF) {
            $out['code'] = 422; $out['reason'] = '«' . self::LEGACY_REF . '» وسمُ الموروث لا يُكتب لجديد'; return $out;
        }

        try {
            $gate->update('claim_lines', array(
                'dispute_flag'    => 1,
                'dispute_state'   => 'open',
                'dispute_reason'  => mb_substr($reason, 0, 255),
                'dispute_doc_ref' => mb_substr($doc, 0, 120),
                'disputed_by'     => (int) $actor ?: null,
                'disputed_at'     => date('Y-m-d H:i:s'),
                'resolution'      => null,
                'resolution_note' => null,
                'resolved_by'     => null,
                'resolved_at'     => null,
            ), array('id' => (int) $lineId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الرفع: ' . $t->getMessage(); return $out;
        }

        self::recalc($gate, (int) $line['claim_id']);
        self::audit($conn, $companyId, $actor, 'dispute_raise', (int) $lineId,
            array('dispute_state' => (string) $line['dispute_state']),
            array('dispute_state' => 'open', 'reason' => $reason, 'doc' => $doc));

        $out['ok'] = true; $out['code'] = 200;
        $out['open_count'] = self::openCount($gate, (int) $line['claim_id']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الحسم
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,open_count:int,net:float} */
    public static function resolve($conn, $gate, $companyId, $lineId, $resolution, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'open_count' => 0, 'net' => 0.0);
        $line = self::line($gate, (int) $lineId);
        if (!$line) { $out['code'] = 404; $out['reason'] = 'البندُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $line['dispute_state'] !== 'open') {
            $out['code'] = 409;
            $out['reason'] = 'لا نزاعَ مفتوحًا على هذا البند (حالُه: ' . $line['dispute_state'] . ')';
            return $out;
        }

        $block = self::assertDisputable($gate, (int) $line['claim_id']);
        if ($block !== null) { return array_merge($out, $block); }

        $resolution = trim((string) $resolution);
        if (!in_array($resolution, self::RESOLUTIONS, true)) {
            $out['code'] = 422; $out['reason'] = 'قرارُ الحسم: upheld أو rejected'; return $out;
        }
        $note = trim((string) $note);
        if ($note === '') {
            $out['code'] = 422;
            $out['reason'] = '**الحسمُ يلزمه سببٌ مكتوب** — قرارٌ يُسقط بندًا أو يعيده لا يكون صامتًا';
            return $out;
        }

        try {
            $gate->update('claim_lines', array(
                'dispute_state'   => 'resolved',
                'resolution'      => $resolution,
                'resolution_note' => mb_substr($note, 0, 255),
                'resolved_by'     => (int) $actor ?: null,
                'resolved_at'     => date('Y-m-d H:i:s'),
                // ③ العَلَمُ مرآةُ القرار: أُقرَّ ⇒ يسقط · رُدَّ ⇒ يعود محتسَبًا
                'dispute_flag'    => ($resolution === 'upheld') ? 1 : 0,
            ), array('id' => (int) $lineId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحسم: ' . $t->getMessage(); return $out;
        }

        $net = self::recalc($gate, (int) $line['claim_id']);
        self::audit($conn, $companyId, $actor, 'dispute_resolve', (int) $lineId,
            array('dispute_state' => 'open'),
            array('dispute_state' => 'resolved', 'resolution' => $resolution, 'note' => $note));

        $out['ok'] = true; $out['code'] = 200; $out['net'] = $net;
        $out['open_count'] = self::openCount($gate, (int) $line['claim_id']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ حرّاسٌ وقراءات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * ④ **لا نزاعَ على مفوتَر** — «التصحيحُ إشعارٌ بمرجع المستخلص الأصل» (§3-⑥).
     * @return ?array{code:int,reason:string}
     */
    public static function assertDisputable($gate, $claimId)
    {
        require_once __DIR__ . '/TaxInvoiceService.php';
        $blocked = TaxInvoiceService::assertEditable($gate, (int) $claimId);
        if ($blocked !== null) {
            return array('code' => 423,
                'reason' => 'صدرت فاتورةُ هذا المستخلص — و**النزاعُ بابُ ما قبلَ المستند**؛ '
                          . 'التصحيحُ بعده **بإشعارٍ دائنٍ أو مدين** (ENT-03 §3-⑥)');
        }
        return null;
    }

    /** عدّادُ النزاع المفتوح — «Disputed **بعدّاده**» (§4). */
    public static function openCount($gate, $claimId)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'claim_lines')),
                "SELECT COUNT(*) AS n FROM claim_lines l
                  WHERE {TENANT_SCOPE} AND l.claim_id = ? AND l.dispute_state = 'open'",
                array((int) $claimId));
            return $rows ? (int) $rows[0]['n'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    /** أسطرُ المستخلص بحال نزاعها — للشاشة. */
    public static function linesOf($gate, $claimId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'claim_lines')),
                "SELECT l.* FROM claim_lines l
                  WHERE {TENANT_SCOPE} AND l.claim_id = ? ORDER BY l.id",
                array((int) $claimId));
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * ② «والبقيةُ تمضي» — المجاميعُ تُعاد بلا احتساب المتنازَع عليه.
     * (سلوكٌ قائمٌ في `claim_recalc` **يُصان ولا يُعاد بناؤه**.)
     */
    private static function recalc($gate, $claimId)
    {
        require_once dirname(__DIR__, 3) . '/Contracts/claim_helpers.php';
        return claim_recalc($gate, (int) $claimId);
    }

    private static function line($gate, $lineId)
    {
        try { return $gate->selectOne('claim_lines', array('where' => array('id' => (int) $lineId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'claim_lines', $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
