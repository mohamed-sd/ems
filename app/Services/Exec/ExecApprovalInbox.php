<?php
namespace App\Services\Exec;

/**
 * app/Services/Exec/ExecApprovalInbox.php — بابُ صندوقِ الاعتمادِ الأعلى (RPR-02 §5·9)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §5·9: *«لكلِّ حقيقةِ أعمالٍ **مالكٌ قانونيٌّ واحد** ·
 *   ⛔ **ولا تُنشأ ولا تُعدَّل من مصدرَين مستقلَّين**»*.
 *
 * ◆ **والحالُ قبلَ هذا الباب**: `exec_approvals` — صندوقُ اعتمادِ القيادة —
 *   كان يُكتب بجملةِ `INSERT` خامّةٍ من **أربعةِ أسطحٍ في ثلاثِ إداراتٍ**:
 *   `Portal/ceo_approvals.php` (‏EX-CEO · المالك) و`Contracts/penalties.php`
 *   (‏DEP-01) و`Procurement/po_match.php` و`Procurement/requests_proc.php`
 *   (‏DEP-16). ⇒ **ثلاثُ إداراتٍ تُنشئ حقيقةً واحدةً بلا عقد** — وهو بعينُه
 *   ما يعدّه المقياسُ **#11** («كتابةٌ تعبر حدودَ إدارةٍ بلا عقد»).
 *
 * ◆ **والعقدُ ليس وثيقةً تُكتب بل مسارُ كتابةٍ يُقاس** (`ADR-15`): الإدارةُ
 *   التي يتجاوز طلبُها سقفَ تفويضِها **تُصعِّد** — ولا تكتب في صندوقِ غيرِها.
 *   وهذا البابُ هو `escalate()`: مدخلٌ واحدٌ للتصعيدِ من أيِّ إدارة.
 *
 * ◆ **والكتابةُ ببوابةِ العزلِ لا بجملةٍ خامّة** (`FR-SEC-006` · `GAP-29`):
 *   `TenantDb::insert()` تحقن `company_id` من سياقِ الجلسةِ وترفض تمريرَ
 *   غيرِه (‏«محاولةُ تزوير»). ⇒ **فلا استعلامَ خامًّا في هذا الملفّ**، ولا
 *   يرتفع خطُّ أساسِ السقّاطةِ بملفٍّ جديد.
 *
 * ⛔ **والحكمُ يبقى عند المُصعِّد**: متى يُصعَّد ولماذا **سياسةُ إدارتِه**
 *   (`AuthorityGuard` يردُّ 409 فتُصعِّد). **والكتابةُ وحدَها** تعود إلى هنا.
 *
 * ⛔ **ولا يُقرَّر هنا مبلغٌ ولا عملةٌ ولا حالة** — كلُّها من المُصعِّد بقيمتِه
 *   المقيسة. **وتاريخُ الاستلامِ من ساعةِ القاعدةِ لا من ساعةِ التطبيق**:
 *   `CURDATE()` كما كان في الجملِ الأربعِ قبلَ التوحيد.
 *
 * ◆ **وقرارُ الصندوقِ لا يُكتب من هنا**: التعديلُ (‏اعتمادٌ أو رفض) فعلُ
 *   المالكِ في `Portal/ceo_approvals.php` — والبابُ للإيداعِ لا للحسم.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class ExecApprovalInbox
{
    /**
     * إيداعُ تصعيدٍ في صندوقِ الاعتمادِ الأعلى.
     *
     * @param array $in  company_id · request_no · doc_type · document · raise_reason
     *                   · amount · [requesting_dept] · [currency] · [status]
     *                   · [source_kind] · [created_by] · [created_by_name]
     *                   · [received_date] — والافتراضُ تاريخُ القاعدةِ اليوم
     * @return array{ok:bool, id:int, reason:string}
     */
    public static function escalate(\mysqli $conn, array $in)
    {
        $rq = trim((string) (isset($in['request_no']) ? $in['request_no'] : ''));
        if ($rq === '') { return array('ok' => false, 'id' => 0, 'reason' => 'رقم الطلب إلزامي'); }

        /* التاريخُ من القاعدةِ لا من ساعةِ التطبيق — والفترةُ المحاسبيةُ تُقرأ به */
        $recv = isset($in['received_date']) && (string) $in['received_date'] !== ''
              ? (string) $in['received_date']
              : self::dbToday($conn);

        $row = array(
            'request_no'    => $rq,
            'received_date' => $recv,
            /* ⛔ **ولا حالةٌ تُخترع**: الفراغُ يعني افتراضَ المخطَّطِ نفسِه */
            'status'        => isset($in['status']) && (string) $in['status'] !== ''
                             ? (string) $in['status'] : 'قيد المراجعة',
        );
        foreach (array('doc_type', 'document', 'requesting_dept', 'raise_reason',
                       'amount', 'currency', 'source_kind', 'created_by_name') as $k) {
            if (isset($in[$k]) && (string) $in[$k] !== '') { $row[$k] = (string) $in[$k]; }
        }
        if (isset($in['created_by']) && (int) $in['created_by'] > 0) {
            $row['created_by'] = (int) $in['created_by'];
        }

        try {
            $gate = \ems_tenant_db();
            $gate->insert('exec_approvals', $row);
        } catch (\Throwable $e) {
            \error_log('ExecApprovalInbox::escalate — ' . $e->getMessage());
            return array('ok' => false, 'id' => 0, 'reason' => $e->getMessage());
        }
        return array('ok' => true, 'id' => (int) $conn->insert_id, 'reason' => '');
    }

    /** تاريخُ اليومِ بساعةِ القاعدة — ⛔ ولا يُؤخذ من ساعةِ التطبيق. */
    private static function dbToday(\mysqli $conn)
    {
        $r = $conn->query('SELECT CURDATE()');
        $x = $r ? $r->fetch_row() : null;
        return ($x && isset($x[0])) ? (string) $x[0] : null;
    }
}
