<?php
/**
 * app/Services/Capacity/CapacityOutbox.php — صادرُ مجال القدرات (CAP-26/27)
 * ═══════════════════════════════════════════════════════════════════════════
 * DEC-CAP-B: «نمطُ الصادر يحلّ تباعدَ نظامين، ونشرُنا كتابةٌ في القاعدة
 * نفسِها — والغرضُ الوحيدُ إمرارُ C28»:
 *   · enqueue(): **كتابةُ صفٍّ داخل المعاملة — لا نشرٌ** (§14-⑤).
 *   · drain(): عاملُ النشر بعد COMMIT — يعيد الفاشلَ **تصاعديًّا** (2^attempts
 *     دقيقة بساعة القاعدة)، والعطالةُ تمرّ بمفتاح الصف نفسِه إلى publishFact
 *     فلا يتضاعف حدثٌ ولا تُستهلك حصةٌ ثانيةً (C28).
 * العلَم: EMS_CAP_OUTBOX (off·on) — off: الكتابةُ تبقى والعاملُ لا ينشر.
 */

namespace App\Services\Capacity;

require_once __DIR__ . '/../../Core/EventPublisher.php';

use App\Core\EventPublisher;

class CapacityOutbox
{
    const MAX_ATTEMPTS = 8; // بعدها failed معلَنة — لا إعادةَ صامتةً بلا سقف

    /** وضعُ الصادر من العلم الواحد. */
    public static function mode()
    {
        $v = function_exists('ems_env') ? strtolower((string) ems_env('EMS_CAP_OUTBOX', 'off')) : 'off';
        return $v === 'on' ? 'on' : 'off';
    }

    /**
     * §14-⑤: صفُّ الصادر داخل المعاملة — يُستدعى من CapacityAtomicCommit حصرًا
     * بالبوابة المعاملاتية نفسِها. لا نشرَ هنا أبدًا.
     * @return array{ok:bool,code:int,reason:string,obx_id:?int}
     */
    public static function enqueue($g, array $e)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'obx_id' => null);
        $key = isset($e['event_key']) ? (string) $e['event_key'] : '';
        if (!in_array($key, CapacityEvents::ALL, true)) {
            $out['code'] = 422;
            $out['reason'] = 'حدث خارج قاموس مجال القدرات الستة (CAP-29): ' . $key;
            return $out;
        }
        $idem = isset($e['idempotency_key']) ? substr((string) $e['idempotency_key'], 0, 64) : '';
        if ($idem === '') { $out['code'] = 422; $out['reason'] = 'مفتاح العطالة إلزامي (CAP-30)'; return $out; }
        if (!isset($e['created_by']) || (int) $e['created_by'] <= 0) {
            $out['code'] = 422; $out['reason'] = 'الفاعل إلزامي موجب — عقد الناشر §9 يرفض غيره';
            return $out;
        }
        try {
            $obxId = (int) $g->insert('capacity_outbox', array(
                'event_key'       => $key,
                'entity_type'     => isset($e['entity_type']) ? (string) $e['entity_type'] : 'unit_entry',
                'entity_id'       => isset($e['entity_id']) ? (int) $e['entity_id'] : 0,
                'quantity'        => isset($e['quantity']) && $e['quantity'] !== '' ? (float) $e['quantity'] : null,
                'unit'            => isset($e['unit']) ? (string) $e['unit'] : null,
                'payload_json'    => json_encode(isset($e['payload']) && is_array($e['payload']) ? $e['payload'] : array(),
                                                 JSON_UNESCAPED_UNICODE),
                'idempotency_key' => $idem,
                'created_by'      => isset($e['created_by']) ? (int) $e['created_by'] : null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'صف الصادر مقيد بهذا المفتاح — لا ازدواج';
                return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 201; $out['obx_id'] = $obxId;
        return $out;
    }

    /**
     * عاملُ النشر — بعد COMMIT خارجَ المعاملة (§14-⑥): ينشر المعلَّقَ المستحقَّ
     * وقتُه، والفاشلُ يُجدول تصاعديًّا. C28: إعادةُ المحاولة تنجح ولا تُستهلك
     * الحصةُ ثانيةً — النشرُ لا يمسّ الدفترَ أصلًا والعطالةُ تمنع حدثًا ثانيًا.
     * @return array{ok:bool,drained:int,published:int,retried:int,failed:int}
     */
    public static function drain($conn, $gate, $limit = 50, $force = false)
    {
        $out = array('ok' => true, 'drained' => 0, 'published' => 0, 'retried' => 0, 'failed' => 0);
        if (!$force && self::mode() !== 'on') {
            $out['ok'] = false;
            return $out; // العلمُ مطفأ — لا نشرَ (لا-عملية موثَّقة)
        }
        $rows = $gate->scopedQuery(array('scope' => array('o' => 'capacity_outbox')),
            "SELECT o.* FROM capacity_outbox o
              WHERE {TENANT_SCOPE} AND o.state = 'pending'
                AND (o.next_attempt_at IS NULL OR o.next_attempt_at <= NOW())
              ORDER BY o.obx_id LIMIT " . max(1, (int) $limit), array());
        foreach ($rows as $r) {
            $out['drained']++;
            $payload = json_decode((string) $r['payload_json'], true);
            if (!is_array($payload)) { $payload = array(); }
            try {
                $res = EventPublisher::publishFact($conn, array(
                    'company_id'      => (int) $r['company_id'],
                    'event_key'       => (string) $r['event_key'],
                    'category'        => 'operational',
                    'source_module'   => 'capacity',
                    'entity_type'     => (string) $r['entity_type'],
                    'entity_id'       => (int) $r['entity_id'],
                    'created_by'      => (int) $r['created_by'],
                    'occurred_at'     => (string) $r['created_at'],
                    'quantity'        => $r['quantity'],
                    'unit'            => $r['unit'],
                    'payload'         => $payload,
                    'idempotency_key' => (string) $r['idempotency_key'],
                ));
                if ($res === null) {
                    throw new \RuntimeException('جذر الأحداث مطفأ (EMS_EVENT_ROOT) — يبقى الصف معلقا');
                }
                $gate->update('capacity_outbox', array(
                    'state' => 'published',
                    'published_event_id' => (int) $res['id'],
                    'published_at' => date('Y-m-d H:i:s'),
                    'last_error' => null,
                ), array('obx_id' => (int) $r['obx_id']));
                $out['published']++;
            } catch (\Throwable $t) {
                $attempts = (int) $r['attempts'] + 1;
                $upd = array(
                    'attempts' => $attempts,
                    'last_error' => mb_substr($t->getMessage(), 0, 255),
                );
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $upd['state'] = 'failed'; // معلَنٌ لا صامت — يظهر للمطابقة
                    $out['failed']++;
                } else {
                    // تصاعديًّا: 2^attempts دقيقة — بساعة القاعدة لا PHP
                    $mins = (int) pow(2, $attempts);
                    $d = $conn->query("SELECT DATE_ADD(NOW(), INTERVAL {$mins} MINUTE) t")->fetch_assoc();
                    $upd['next_attempt_at'] = (string) $d['t'];
                    $out['retried']++;
                }
                $gate->update('capacity_outbox', $upd, array('obx_id' => (int) $r['obx_id']));
            }
        }
        return $out;
    }
}
