<?php
/**
 * FinancingModelService — نماذجُ التمويلِ: **مخزنٌ واحدٌ لا مخزنان** (INJ-0003)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيب (P0): شاشةُ «نماذج التمويل ومعالجتها» كانت تكتب في المخزنِ البينيِّ
 *   ‎scr_fin_models‎ عبر ‎cmp03_store_insert‎، بينما **جدولُ المجالِ الحقيقيُّ**
 *   ‎financing_models‎ هو الذي يقرؤه منشئُ العملية (‎financing_operation_new:62‎)
 *   ويتحقق منه ‎FinancingService::createOperation‎ ويرفض 422 إن لم يجد النموذج.
 *   ⇒ نموذجٌ يُضاف من شاشتِه **لا يوجد** حين يُنشأ به عملية. وهذا نقضٌ لحكمِ
 *   «لكلِّ بيانٍ موضعٌ واحدٌ يُنشأ فيه ويُعدَّل، وكلُّ عرضٍ آخرَ له قراءةٌ لا نسخة».
 *
 * ◆ وگوتشا موثَّقةٌ تحكم التنفيذ: **ENUM يبتلع القيمةَ الغريبةَ صامتًا** —
 *   والشاشةُ كانت تُدخل نصًّا حرًّا. فكلُّ حقلٍ محكومٍ بـENUM يُتحقق منه هنا
 *   مقابلَ قائمتِه، والقيمةُ الخارجةُ **تُرفض برسالةٍ** لا تُكتب فارغةً.
 *
 * ◆ والجدولُ مرجعٌ عامٌّ بلا ‎company_id‎ — فالكتابةُ فيه فعلُ حوكمةٍ لا فعلُ
 *   كيان: تُحرَس بصلاحيةِ الشاشةِ وتُسجَّل بفاعلِها.
 */

declare(strict_types=1);

namespace App\Services\Financing;

class FinancingModelService
{
    /** القوائمُ المحكومةُ — مرآةُ ENUM في الجدولِ حرفًا بحرف. */
    const LEGAL_OWNER = array(
        'transfers' => 'تنتقل الملكية', 'stays' => 'تبقى للممول',
        'shared'    => 'مشتركة',        'none'  => 'لا أثر',
    );
    const BENEFICIARY = array('us' => 'نحن', 'financier' => 'الممول', 'shared' => 'مشترك');
    const RECOGNITION = array(
        'owned_asset'    => 'أصل مملوك',
        'right_of_use'   => 'حق استخدام',
        'liability_only' => 'التزام فقط',
    );

    /** @var \mysqli */
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    /** كلُّ النماذجِ — **المصدرُ الواحدُ** الذي يقرؤه منشئُ العملية أيضًا. */
    public function all(bool $activeOnly = false): array
    {
        $sql = "SELECT model_code, name_ar, legal_owner_effect, economic_beneficiary,
                       accounting_recognition, depreciation_bearer, security_interest_holder,
                       policy_doc_ref, approved_by, approved_at, active
                  FROM financing_models"
             . ($activeOnly ? ' WHERE active = 1' : '')
             . ' ORDER BY model_code';
        $out = array();
        $r = $this->conn->query($sql);
        while ($r && ($x = $r->fetch_assoc())) { $out[] = $x; }
        return $out;
    }

    /**
     * إضافةُ نموذجٍ أو تحديثُه — بقيمٍ محكومةٍ لا نصٍّ حرّ.
     *
     * @return array{ok:bool,msg:string,code:string}
     */
    public function upsert(array $in, int $actorId): array
    {
        $code = trim((string) ($in['model_code'] ?? ''));
        $name = trim((string) ($in['name_ar'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z0-9_]{2,32}$/', $code)) {
            return array('ok' => false, 'code' => '', 'msg' => 'رمزُ النموذجِ إلزاميٌّ بأحرفٍ لاتينيةٍ صغيرةٍ وأرقامٍ وشرطةٍ سفلية (422)');
        }
        if ($name === '') { return array('ok' => false, 'code' => $code, 'msg' => 'اسمُ النموذجِ إلزامي (422)'); }

        // ◆ القيمُ المحكومة: الخارجُ عن القائمةِ **يُرفض** ولا يُكتب فارغًا.
        $enums = array(
            'legal_owner_effect'     => self::LEGAL_OWNER,
            'economic_beneficiary'   => self::BENEFICIARY,
            'accounting_recognition' => self::RECOGNITION,
        );
        $vals = array();
        foreach ($enums as $col => $allowed) {
            $v = trim((string) ($in[$col] ?? ''));
            if (!isset($allowed[$v])) {
                return array('ok' => false, 'code' => $code,
                    'msg' => "قيمةُ «{$col}» خارجَ القائمةِ المحكومة — اختر من: " . implode('، ', array_values($allowed)) . ' (422)');
            }
            $vals[$col] = $v;
        }

        $bearer = trim((string) ($in['depreciation_bearer'] ?? ''));
        if ($bearer === '') { return array('ok' => false, 'code' => $code, 'msg' => 'حاملُ الإهلاك إلزامي (422)'); }
        $holder = trim((string) ($in['security_interest_holder'] ?? ''));
        $policy = trim((string) ($in['policy_doc_ref'] ?? ''));
        if ($policy === '') { return array('ok' => false, 'code' => $code, 'msg' => 'المرجعُ المحاسبيُّ إلزامي — لا نموذجَ بلا سندِ سياسة (422)'); }
        $active = !empty($in['active']) ? 1 : 0;

        $this->conn->begin_transaction();
        try {
            $st = $this->conn->prepare(
                "INSERT INTO financing_models
                   (model_code, name_ar, legal_owner_effect, economic_beneficiary,
                    accounting_recognition, depreciation_bearer, security_interest_holder,
                    policy_doc_ref, approved_by, approved_at, active)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)
                 ON DUPLICATE KEY UPDATE
                    name_ar = VALUES(name_ar),
                    legal_owner_effect = VALUES(legal_owner_effect),
                    economic_beneficiary = VALUES(economic_beneficiary),
                    accounting_recognition = VALUES(accounting_recognition),
                    depreciation_bearer = VALUES(depreciation_bearer),
                    security_interest_holder = VALUES(security_interest_holder),
                    policy_doc_ref = VALUES(policy_doc_ref),
                    active = VALUES(active)");
            if (!$st) { throw new \RuntimeException('prepare: ' . $this->conn->error); }
            $holderOrNull = ($holder === '' ? null : $holder);
            $st->bind_param('ssssssssii',
                $code, $name, $vals['legal_owner_effect'], $vals['economic_beneficiary'],
                $vals['accounting_recognition'], $bearer, $holderOrNull, $policy, $actorId, $active);
            if (!$st->execute()) { throw new \RuntimeException('execute: ' . $st->error); }
            $st->close();

            // ◆ إثباتٌ بعدَ الكتابةِ لا افتراض: ENUM قد يبتلع صامتًا فنقرأ المكتوب.
            $chk = $this->conn->prepare("SELECT legal_owner_effect, economic_beneficiary, accounting_recognition
                                           FROM financing_models WHERE model_code = ? LIMIT 1");
            if (!$chk) { throw new \RuntimeException('verify prepare: ' . $this->conn->error); }
            $chk->bind_param('s', $code);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();
            foreach ($vals as $col => $want) {
                if (!$row || (string) $row[$col] !== $want) {
                    throw new \RuntimeException("ابتلعت القاعدةُ قيمةَ {$col} — المطلوب «{$want}» والمكتوب «"
                        . (string) ($row[$col] ?? '') . '»');
                }
            }

            $this->conn->commit();
            return array('ok' => true, 'code' => $code,
                'msg' => 'حُفظ النموذجُ «' . $name . '» (' . $code . ') في جدولِ المجال — ويظهر فورًا في إنشاءِ العملية ✅');
        } catch (\Throwable $e) {
            $this->conn->rollback();
            error_log('FinancingModelService::upsert: ' . $e->getMessage());
            return array('ok' => false, 'code' => $code, 'msg' => 'تعذّر الحفظ — لم يُكتب شيء (ERR-FIN-1050): ' . $e->getMessage());
        }
    }

    /**
     * CS-08 — لا حذف: التعطيلُ فعلٌ يُبقي السجلَّ ويمنع الاستعمالَ الجديد.
     * @return array{ok:bool,msg:string}
     */
    public function deactivate(string $code, int $actorId): array
    {
        $st = $this->conn->prepare("UPDATE financing_models SET active = 0 WHERE model_code = ?");
        if (!$st) { return array('ok' => false, 'msg' => 'تعذّر التعطيل (500)'); }
        $st->bind_param('s', $code);
        $ok = $st->execute();
        $n = $st->affected_rows;
        $st->close();
        if (!$ok)    { return array('ok' => false, 'msg' => 'تعذّر التعطيل (500)'); }
        if ($n <= 0) { return array('ok' => false, 'msg' => 'نموذجٌ غيرُ موجودٍ أو معطَّلٌ سلفًا (404)'); }
        return array('ok' => true, 'msg' => 'عُطِّل النموذجُ ' . $code . ' — والسجلُّ باقٍ (لا حذف)');
    }
}
