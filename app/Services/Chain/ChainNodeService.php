<?php
namespace App\Services\Chain;

/**
 * app/Services/Chain/ChainNodeService.php
 *   خدمةُ عقدِ سلسلةِ الأثرِ الستِّ المبنيةِ في هذه الجولة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **خدمةٌ كنسيةٌ واحدةٌ للقدرةِ الواحدة**: العقدُ التاسعةُ والثالثةَ عشرةَ
 *   والسادسةَ عشرةَ والسابعةَ عشرةَ والثامنةَ عشرةَ والخامسةُ والعشرون —
 *   كلُّها تمرُّ من هنا. **ولا كاتبَ مباشرًا من الشاشة.**
 *
 * ◆ **وكلُّ وصولٍ للبياناتِ ببوابةِ المستأجِر** (`App\Core\TenantDb`) — لا
 *   استعلامَ خامٍّ على جدولِ مستأجِر. فالعزلُ يُحقَن ولا يُكتب، ومحاولةُ تمريرِ
 *   `company_id` مغايرٍ تُردُّ تزويرًا. **والبوابةُ تُمرَّر ولا تُستدعى داخلًا**
 *   لأن `ems_tenant_db()` تلزمها جلسةٌ، فتسقط في المهامِّ الطرفية.
 *
 * ◆ **وثلاثُ قواعدَ تُنفَّذ هنا لا تُوصَف**:
 *   ① **فصلُ الواجبات**: مَن أعدَّ لا يعتمد، ومَن اعتمد لا يُجيز الترحيل،
 *      ومَن أعدَّ الدفعةَ لا ينفّذها. (وقيدُ `CHECK` يسنده في القاعدة.)
 *   ② **لا ترحيلَ قبلَ الإجازة**: `journal_entry_id` لا يُملأ إلا بعدَ
 *      `control_at` — والقيدُ يمنع خلافَه.
 *   ③ **الإعدادُ لا يُنشئ اعتمادًا**: كلُّ انتقالٍ فعلٌ مستقلٌّ بحالتِه.
 *
 * ◆ **والعطالةُ بمفتاحٍ مركَّب**: `idem_key` يمنع وقوعَ الأثرِ مرتين، ولا
 *   يُبتلع خطأُ الإدراج — يُلتقط استثناءُ البوابةِ ويُترجَم رمزًا مفهومًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class ChainNodeService
{
    /** يبني مفتاحَ عطالةٍ مركَّبًا — لا عشوائيّ. */
    public static function idem($parts)
    {
        return mb_substr(implode(':', array_map('strval', (array) $parts)), 0, 96);
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }

    private static function done($code, $reason, $id = 0)
    {
        return array('ok' => true, 'code' => $code, 'reason' => $reason, 'id' => (int) $id);
    }

    /** يُترجم فشلَ الإدراجِ إلى حكمٍ مفهوم — والتكرارُ عطالةٌ لا خطأ. */
    private static function guardedInsert($gate, $table, array $data, $dupMsg)
    {
        try {
            $id = $gate->insert($table, $data);
            return array('ok' => true, 'id' => (int) $id);
        } catch (\Throwable $e) {
            $m = $e->getMessage();
            if (stripos($m, 'duplicate') !== false || strpos($m, '1062') !== false) {
                return array('ok' => false, 'dup' => true, 'msg' => $dupMsg);
            }
            return array('ok' => false, 'dup' => false, 'msg' => $m);
        }
    }

    /* ══ العقدة ٩ — الاعتمادُ الماليُّ النهائيّ · LD-07 ═══════════════════════ */

    public static function prepareFinalApproval($conn, $gate, $companyId, $entryId, $period, $preparedBy)
    {
        $entryId = (int) $entryId; $preparedBy = (int) $preparedBy;
        $period = mb_substr((string) $period, 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) { return self::fail(422, 'الفترةُ بصيغةِ YYYY-MM'); }
        $entry = $gate->selectOne('unit_entries', array('columns' => array('state'),
                                                        'where' => array('id' => $entryId)));
        if (!$entry) { return self::fail(404, 'الواقعةُ غيرُ موجودة'); }
        if (!in_array($entry['state'], array('sales_approved', 'converted'), true)) {
            return self::fail(409, "الاعتمادُ النهائيُّ يتطلب اكتمالَ السلسلةِ التجارية — الحالية: {$entry['state']}");
        }
        $r = self::guardedInsert($gate, 'unit_final_approvals', array(
            'period' => $period, 'entry_id' => $entryId, 'prepared_by' => $preparedBy,
            'state' => 'prepared',
            'idem_key' => self::idem(array('ufa', (int) $companyId, $entryId, $period)),
        ), 'مُعَدٌّ سلفًا لهذه الواقعةِ والفترة — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, 'أُعِدَّ الاعتمادُ الماليُّ النهائيُّ بانتظارِ الاعتماد', $r['id']);
    }

    public static function approveFinal($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'unit_final_approvals', (int) $id, (int) $actor,
            'prepared', 'approved', 'approved_by', 'approved_at', 'prepared_by',
            'الاعتمادُ الماليُّ النهائيُّ وقع — وبقيت إجازةُ الرقابة');
    }

    public static function controlFinal($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'unit_final_approvals', (int) $id, (int) $actor,
            'approved', 'controlled', 'control_by', 'control_at', 'approved_by',
            'أُجيزت الرقابةُ المحاسبية — والترحيلُ لمحرّكِه وحدَه');
    }

    /* ══ العقدة ١٦ — استحقاقُ عقدِ العميل ═════════════════════════════════ */

    public static function prepareAccrual($conn, $gate, $companyId, $data, $preparedBy)
    {
        $preparedBy = (int) $preparedBy;
        $period   = mb_substr((string) ($data['period'] ?? ''), 0, 7);
        $contract = (int) ($data['contract_id'] ?? 0);
        $claim    = (int) ($data['claim_id'] ?? 0);
        $amount   = round((float) ($data['amount'] ?? 0), 2);
        $cur      = mb_substr(trim((string) ($data['currency'] ?? '')), 0, 8);
        $rate     = (float) ($data['fx_rate'] ?? 1);
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) { return self::fail(422, 'الفترةُ بصيغةِ YYYY-MM'); }
        if ($contract <= 0) { return self::fail(422, 'لا استحقاقَ بلا عقدٍ مرجعيّ'); }
        if ($amount <= 0)   { return self::fail(422, 'المبلغُ يجب أن يكون موجبًا'); }
        if (mb_strlen($cur) < 3) { return self::fail(422, 'لا مبلغَ بلا عملة'); }
        if ($rate <= 0) { $rate = 1; }
        $no = 'ACR-' . $period . '-' . str_pad((string) $contract, 6, '0', STR_PAD_LEFT);
        $r = self::guardedInsert($gate, 'ar_accruals', array(
            'accrual_no' => $no, 'period' => $period, 'contract_id' => $contract,
            'claim_id' => $claim > 0 ? $claim : null, 'amount' => $amount, 'currency' => $cur,
            'fx_rate' => $rate, 'base_amount' => round($amount * $rate, 2),
            'prepared_by' => $preparedBy, 'state' => 'prepared',
            'idem_key' => self::idem(array('acr', (int) $companyId, $contract, $period, $claim)),
        ), 'استحقاقٌ مُعَدٌّ سلفًا لهذا العقدِ والفترة — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "أُعِدَّ الاستحقاقُ {$no}", $r['id']);
    }

    public static function controlAccrual($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'ar_accruals', (int) $id, (int) $actor,
            'prepared', 'controlled', 'control_by', 'control_at', 'prepared_by',
            'أُجيز الاستحقاقُ محاسبيًّا — والترحيلُ لمحرّكِه');
    }

    /* ══ العقدة ١٧ — شهادةُ الإنجازِ الشهرية · LD-06 ═════════════════════════ */

    public static function prepareCert($conn, $gate, $companyId, $data, $preparedBy)
    {
        $preparedBy = (int) $preparedBy;
        $period   = mb_substr((string) ($data['period'] ?? ''), 0, 7);
        $contract = (int) ($data['contract_id'] ?? 0);
        $claim    = (int) ($data['claim_id'] ?? 0);
        $qty      = round((float) ($data['approved_qty'] ?? 0), 4);
        $unit     = mb_substr((string) ($data['unit_type'] ?? 'hour'), 0, 16);
        $measure  = mb_substr(trim((string) ($data['measure_ref'] ?? '')), 0, 120);
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) { return self::fail(422, 'الفترةُ بصيغةِ YYYY-MM'); }
        if ($contract <= 0) { return self::fail(422, 'لا شهادةَ بلا عقدٍ مرجعيّ'); }
        if ($qty <= 0) { return self::fail(422, 'الكميةُ المعتمدةُ يجب أن تكون موجبة'); }
        if ($measure === '') { return self::fail(422, 'لا شهادةَ إنجازٍ بلا مرجعِ قياسٍ معتمَد'); }
        $no = 'CRT-' . $period . '-' . str_pad((string) $contract, 6, '0', STR_PAD_LEFT);
        $r = self::guardedInsert($gate, 'ar_completion_certs', array(
            'cert_no' => $no, 'period' => $period, 'contract_id' => $contract,
            'claim_id' => $claim > 0 ? $claim : null, 'approved_qty' => $qty,
            'unit_type' => $unit, 'measure_ref' => $measure, 'prepared_by' => $preparedBy,
            'state' => 'prepared',
            'idem_key' => self::idem(array('crt', (int) $companyId, $contract, $period)),
        ), 'شهادةٌ مُعَدَّةٌ سلفًا لهذا العقدِ والفترة — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "أُعِدَّت الشهادةُ {$no}", $r['id']);
    }

    public static function approveCert($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'ar_completion_certs', (int) $id, (int) $actor,
            'prepared', 'approved', 'approved_by', 'approved_at', 'prepared_by',
            'اعتُمدت شهادةُ الإنجاز — وتُبنى عليها الفاتورة');
    }

    /* ══ العقدة ١٨ — فاتورةُ المطالبةِ وإحالتُها · LD-06 ═════════════════════ */

    public static function prepareClaimInvoice($conn, $gate, $companyId, $data, $preparedBy)
    {
        $preparedBy = (int) $preparedBy;
        $period = mb_substr((string) ($data['period'] ?? ''), 0, 7);
        $claim  = (int) ($data['claim_id'] ?? 0);
        $cert   = (int) ($data['cert_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $cur    = mb_substr(trim((string) ($data['currency'] ?? '')), 0, 8);
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) { return self::fail(422, 'الفترةُ بصيغةِ YYYY-MM'); }
        if ($claim <= 0)  { return self::fail(422, 'لا فاتورةَ مطالبةٍ بلا مطالبة'); }
        if ($amount <= 0) { return self::fail(422, 'المبلغُ يجب أن يكون موجبًا'); }
        if (mb_strlen($cur) < 3) { return self::fail(422, 'لا مبلغَ بلا عملة'); }
        /* ◆ **الفاتورةُ تُبنى على شهادةِ إنجازٍ معتمَدة** — لا على رأسِ المطالبة */
        if ($cert > 0) {
            $c = $gate->selectOne('ar_completion_certs', array('columns' => array('state'),
                                                               'where' => array('id' => $cert)));
            if (!$c) { return self::fail(404, 'شهادةُ الإنجازِ غيرُ موجودة'); }
            if (!in_array($c['state'], array('approved', 'issued'), true)) {
                return self::fail(409, "لا فاتورةَ على شهادةٍ غيرِ معتمَدة — حالتُها: {$c['state']}");
            }
        }
        $no = 'INV-' . $period . '-' . str_pad((string) $claim, 6, '0', STR_PAD_LEFT);
        $r = self::guardedInsert($gate, 'ar_claim_invoices', array(
            'invoice_no' => $no, 'period' => $period, 'claim_id' => $claim,
            'cert_id' => $cert > 0 ? $cert : null, 'amount' => $amount, 'currency' => $cur,
            'prepared_by' => $preparedBy, 'state' => 'prepared',
            'idem_key' => self::idem(array('aci', (int) $companyId, $claim, $period)),
        ), 'فاتورةٌ مُعَدَّةٌ سلفًا لهذه المطالبةِ والفترة — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "أُعِدَّت الفاتورةُ {$no}", $r['id']);
    }

    public static function approveClaimInvoice($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'ar_claim_invoices', (int) $id, (int) $actor,
            'prepared', 'approved', 'approved_by', 'approved_at', 'prepared_by',
            'اعتُمدت الفاتورة — وبقيت إجازةُ الرقابة');
    }

    public static function controlClaimInvoice($conn, $gate, $companyId, $id, $actor)
    {
        return self::advance($gate, 'ar_claim_invoices', (int) $id, (int) $actor,
            'approved', 'controlled', 'control_by', 'control_at', 'approved_by',
            'أُجيزت الفاتورةُ محاسبيًّا');
    }

    /** الإحالةُ لقسمِ التحصيل — بعدَ الإجازةِ لا قبلها. */
    public static function referClaimInvoice($conn, $gate, $companyId, $id, $to, $actor)
    {
        $id = (int) $id;
        if (!in_array($to, array('collections', 'on_hold', 'cancelled'), true)) {
            return self::fail(422, 'وجهةُ الإحالةِ محكومةٌ من قائمةٍ مغلقة');
        }
        $n = $gate->update('ar_claim_invoices',
            array('referred_to' => $to, 'referred_at' => date('Y-m-d H:i:s'), 'state' => 'referred'),
            array('id' => $id, 'state' => 'controlled'));
        if ((int) $n === 0) { return self::fail(409, 'لا تُحال فاتورةٌ لم تُجَز رقابيًّا'); }
        return self::done(200, 'أُحيلت الفاتورةُ إلى ' . $to, $id);
    }

    /* ══ العقدة ١٣ — تصحيحُ الوحداتِ بالسلسلةِ الثلاثية ═══════════════════ */

    public static function openCorrection($conn, $gate, $companyId, $data, $by)
    {
        $by = (int) $by;
        $entry  = (int) ($data['entry_id'] ?? 0);
        $kind   = (string) ($data['correction_kind'] ?? '');
        $field  = (string) ($data['field_changed'] ?? '');
        $before = mb_substr((string) ($data['value_before'] ?? ''), 0, 120);
        $after  = mb_substr((string) ($data['value_after'] ?? ''), 0, 120);
        $reason = mb_substr(trim((string) ($data['reason'] ?? '')), 0, 400);
        if ($entry <= 0) { return self::fail(422, 'لا تصحيحَ بلا واقعة'); }
        if (!in_array($kind, array('adjustment', 'reversal', 'split', 'merge'), true)) {
            return self::fail(422, 'نوعُ التصحيحِ محكومٌ من قائمةٍ مغلقة');
        }
        if (!in_array($field, array('quantity', 'responsible_party', 'time_state', 'classification'), true)) {
            return self::fail(422, 'الحقلُ المُصحَّحُ محكومٌ من قائمةٍ مغلقة');
        }
        if (mb_strlen($reason) < 8) { return self::fail(422, 'لا تصحيحَ بلا سببٍ مكتوبٍ مفهوم'); }
        if ($before === $after) { return self::fail(422, 'لا تصحيحَ بلا تغيير'); }
        $r = self::guardedInsert($gate, 'unit_corrections', array(
            'entry_id' => $entry, 'correction_kind' => $kind, 'field_changed' => $field,
            'value_before' => $before, 'value_after' => $after, 'reason' => $reason,
            'requested_by' => $by, 'state' => 'in_chain',
            'idem_key' => self::idem(array('ucor', (int) $companyId, $entry, $field, $after)),
        ), 'تصحيحٌ مفتوحٌ سلفًا بالقيمةِ نفسِها — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, 'فُتح التصحيحُ — ولا يمرُّ إلا بالأطرافِ الثلاثةِ معًا', $r['id']);
    }

    /** موافقةُ طرفٍ واحد — والاعتمادُ لا يقع إلا باكتمالِ الثلاثة. */
    public static function correctionPartyOk($conn, $gate, $companyId, $id, $party, $actor)
    {
        $map = array('client' => 'client_ok', 'supplier' => 'supplier_ok', 'worker' => 'worker_ok');
        if (!isset($map[$party])) { return self::fail(422, 'الطرفُ محكومٌ: عميل أو مورّد أو مشغّل'); }
        $col = $map[$party];
        $id = (int) $id; $actor = (int) $actor;
        $n = $gate->update('unit_corrections',
            array($col . '_by' => $actor, $col . '_at' => date('Y-m-d H:i:s')),
            array('id' => $id, 'state' => 'in_chain'), '`' . $col . '_at` IS NULL');
        if ((int) $n === 0) { return self::fail(200, 'قرارُ هذا الطرفِ مسجَّلٌ سلفًا أو التصحيحُ ليس في السلسلة'); }

        $full = $gate->update('unit_corrections', array('state' => 'approved'),
            array('id' => $id, 'state' => 'in_chain'),
            '`client_ok_at` IS NOT NULL AND `supplier_ok_at` IS NOT NULL AND `worker_ok_at` IS NOT NULL');
        return self::done(200, (int) $full > 0 ? 'اكتملت السلسلةُ الثلاثيةُ — التصحيحُ معتمَد'
                                               : 'سُجِّل قرارُ الطرف — وبقي غيرُه', $id);
    }

    /* ══ العقدة ٢٥ — دفعاتُ الدفعِ والتنفيذ ══════════════════════════════ */

    public static function openBatch($conn, $gate, $companyId, $data, $by)
    {
        $by = (int) $by;
        $date = (string) ($data['value_date'] ?? '');
        $cur  = mb_substr(trim((string) ($data['currency'] ?? '')), 0, 8);
        $acct = mb_substr(trim((string) ($data['bank_account'] ?? '')), 0, 64);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return self::fail(422, 'تاريخُ القيمةِ بصيغةِ YYYY-MM-DD'); }
        if (mb_strlen($cur) < 3) { return self::fail(422, 'لا دفعةَ بلا عملة'); }
        $no = 'PB-' . str_replace('-', '', $date) . '-' . mb_substr(md5($acct . $cur . (int) $companyId), 0, 6);
        $r = self::guardedInsert($gate, 'tre_pay_batches', array(
            'batch_no' => $no, 'value_date' => $date, 'bank_account' => $acct,
            'currency' => $cur, 'prepared_by' => $by, 'state' => 'draft',
            'idem_key' => self::idem(array('tpb', (int) $companyId, $date, $acct, $cur)),
        ), 'دفعةٌ مفتوحةٌ سلفًا بالتاريخِ والحسابِ نفسِهما — عطالة');
        if (!$r['ok']) { return self::fail(!empty($r['dup']) ? 200 : 500, $r['msg']); }
        return self::done(201, "فُتحت الدفعةُ {$no}", $r['id']);
    }

    /** تجهيزُ الدفعةِ للتنفيذ — خطوةٌ مستقلةٌ فالإعدادُ لا يُنشئ تنفيذًا. */
    public static function readyBatch($conn, $gate, $companyId, $id)
    {
        $id = (int) $id;
        $n = $gate->update('tre_pay_batches', array('state' => 'ready'),
                           array('id' => $id, 'state' => 'draft'));
        if ((int) $n === 0) { return self::fail(409, 'لا تُجهَّز إلا دفعةٌ مسودّة'); }
        return self::done(200, 'جُهِّزت الدفعةُ للتنفيذ — والتنفيذُ بيدٍ غيرِ يدِ المُعِدّ', $id);
    }

    /**
     * التنفيذُ النقديّ — **ينتج مرجعَ الحركة ولا يُنشئ قيدًا**.
     * ومَن أعدَّ الدفعةَ لا ينفّذها (قيدُ `CHECK` يسنده).
     */
    public static function executeBatch($conn, $gate, $companyId, $id, $bankRef, $actor)
    {
        $id = (int) $id; $actor = (int) $actor;
        $bankRef = mb_substr(trim((string) $bankRef), 0, 120);
        if ($bankRef === '') { return self::fail(422, 'لا تنفيذَ بلا مرجعِ حركةٍ بنكيّ'); }
        $b = $gate->selectOne('tre_pay_batches', array(
            'columns' => array('prepared_by', 'state'), 'where' => array('id' => $id)));
        if (!$b) { return self::fail(404, 'الدفعةُ غيرُ موجودة'); }
        if ($b['state'] === 'executed') { return self::fail(200, 'نُفِّذت سلفًا — عطالة'); }
        if ($b['state'] !== 'ready')    { return self::fail(409, "لا تُنفَّذ دفعةٌ حالتُها {$b['state']}"); }
        if ((int) $b['prepared_by'] === $actor) {
            return self::fail(403, '**مَن أعدَّ الدفعةَ لا ينفّذها** — فصلُ الواجباتِ لا يُختصر');
        }
        $n = $gate->update('tre_pay_batches', array(
            'executed_by' => $actor, 'executed_at' => date('Y-m-d H:i:s'),
            'bank_ref' => $bankRef, 'state' => 'executed',
        ), array('id' => $id, 'state' => 'ready'));
        if ((int) $n === 0) { return self::fail(409, 'تعذّر التنفيذ — تغيّرت الحالةُ بين القراءةِ والكتابة'); }
        $gate->update('tre_pay_batch_lines',
            array('line_state' => 'executed', 'bank_ref' => $bankRef),
            array('batch_id' => $id, 'line_state' => 'pending'));
        return self::done(200, 'نُفِّذت الدفعةُ وأُنتج مرجعُ الحركة — ولا قيدَ من الخزينة', $id);
    }

    /* ══ انتقالٌ محروسٌ عام — يُستعمل حيث الشكلُ واحد ═════════════════════ */

    private static function advance($gate, $table, $id, $actor,
                                    $fromState, $toState, $whoCol, $whenCol, $notSameAs, $okMsg)
    {
        $row = $gate->selectOne($table, array('columns' => array('state', $notSameAs),
                                              'where' => array('id' => $id)));
        if (!$row) { return self::fail(404, 'السجلُّ غيرُ موجود'); }
        if ($row['state'] === $toState) { return self::fail(200, 'وقع سلفًا — عطالة'); }
        if ($row['state'] !== $fromState) {
            return self::fail(409, "الانتقالُ يتطلب حالةَ {$fromState} — الحالية: {$row['state']}");
        }
        if ((int) $row[$notSameAs] === (int) $actor) {
            return self::fail(403, '**لا يدَ تجمع خطوتَين** — فصلُ الواجباتِ لا يُختصر');
        }
        $n = $gate->update($table,
            array($whoCol => (int) $actor, $whenCol => date('Y-m-d H:i:s'), 'state' => $toState),
            array('id' => $id, 'state' => $fromState));
        if ((int) $n === 0) { return self::fail(409, 'تغيّرت الحالةُ بين القراءةِ والكتابة'); }
        return self::done(200, $okMsg, $id);
    }
}
