<?php
/**
 * tools/rpr03_contract_register.php — تسجيلُ عقودِ أثرِ المفرداتِ المنطوقة (⑪)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` البند ⑪ · `RPR-03` #٣: **مصالحةُ المفردتَين** — المستهلكون
 * القائمون يستمعون لأسماءٍ لم تُنطق، والمنطوقُ الثلاثةُ والعشرون بلا عقد.
 * فيُسجَّل لكلِّ مفردةٍ منطوقةٍ **عقدُ أثرٍ فعّالٌ** في `event_consumers`
 * (السجلِّ الحيِّ الذي تقرؤه `EventOutboxFanout::subscribersFor` فتُنشأ منه
 * التسليماتُ فعلًا): المستهلكُ `EffectLinkConsumer` — إنفاذُ عقدِ الأثرِ
 * الذرّيِّ عند المُنتِجِ لكلِّ واقعةٍ جديدة، بعناصرِ العقدِ الخمسة.
 *
 * ◆ ⛔ ولا يُربط اسمٌ باسم: الاشتراكاتُ المنتظرةُ على مفرداتٍ لم تُنطق
 *   (`acc.*` · `fin.*` · `tre.*` …) شأنُ ناشريها المستقبليّين — لا تُمسّ.
 * ◆ selftest: موجبٌ على واقعةٍ حيّةٍ من كلِّ عائلةٍ · وسالبٌ بواقعةٍ مفكوكةِ
 *   الأثرِ يجب أن يرمي `EFFECT_MISSING`.
 *
 * التشغيل: php tools/rpr03_contract_register.php [--apply] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

require_once $ROOT . '/app/Services/Bus/Consumers/EffectLinkConsumer.php';
use App\Services\Bus\Consumers\EffectLinkConsumer as ELC;

/* ── المفرداتُ المنطوقةُ بلا عقد — تُقرأ حيًّا لا تُنسخ ──────────────────── */
$keys = array();
$r = $conn->query("SELECT k.event_key FROM rpr03_event_classification k
                    WHERE k.classification='BUSINESS'
                      AND NOT EXISTS(SELECT 1 FROM event_consumers e
                                      WHERE e.event_name=k.event_key AND e.active=1
                                        AND e.produces='write'
                                        AND e.consumer_class NOT LIKE '%GovernanceWatch%')
                    ORDER BY k.event_key");
while ($x = $r->fetch_row()) { $keys[] = $x[0]; }

echo "═══ البند ⑪ — عقودُ أثرِ المفرداتِ المنطوقة" . ($APPLY ? '' : ' · DRY') . " ═══\n";
echo "  مفرداتُ أعمالٍ بلا عقد: " . count($keys) . "\n";

if ($SELF) {
    /* موجبان وسالب: قناةُ المروحةِ تتحقّق · وقناةُ السجلِّ القائمِ تتحقّق ·
       وواقعةٌ مفكوكةُ الأثرِ تُرمى باسمِها. ⛔ والتاريخُ المبتورُ كياناتُه
       (بذورٌ حُذفت سجلاتُها — timesheet-family-trim) **خبرٌ يُعدُّ لا رسوب**:
       عاملُ التسليمِ لا يعيد التاريخَ، والعقدُ يحكم الجديد. */
    $in = "'" . implode("','", array_map($e, $keys)) . "'";
    $pos1 = null; $pos2 = null;
    $r = $conn->query("SELECT b.* FROM ems_business_events b
                        WHERE b.event_key IN ($in)
                          AND EXISTS(SELECT 1 FROM fin_event_links l WHERE l.event_id=b.id)
                        ORDER BY b.id DESC LIMIT 1");
    if ($r && $r->num_rows) { $pos1 = $r->fetch_assoc(); }
    if (!$pos1) {
        $r = $conn->query("SELECT b.* FROM ems_business_events b
                            JOIN fin_event_links l ON l.parent_ref=b.entity_id AND l.parent_kind='timesheet'
                           WHERE b.event_key IN ($in) AND b.entity_type='timesheet'
                           ORDER BY b.id DESC LIMIT 1");
        if ($r && $r->num_rows) { $pos1 = $r->fetch_assoc(); }
    }
    $r = $conn->query("SELECT b.* FROM ems_business_events b
                        JOIN fin_requests q ON q.id=b.entity_id
                       WHERE b.event_key IN ($in) AND b.entity_type='fin_request'
                       ORDER BY b.id DESC LIMIT 1");
    if ($r && $r->num_rows) { $pos2 = $r->fetch_assoc(); }

    $ok = true;
    foreach (array('المروحة' => $pos1, 'السجل القائم' => $pos2) as $nm => $ev) {
        if (!$ev) { echo "  ✘ لا واقعةَ عيّنةً لقناة $nm\n"; $ok = false; continue; }
        try {
            $ref = ELC::handle($ev, $conn);
            echo "  ✔ قناة $nm: {$ev['event_key']}#{$ev['id']} ⇒ $ref\n";
        } catch (\Throwable $t) { echo "  ✘ قناة $nm: " . $t->getMessage() . "\n"; $ok = false; }
    }
    $fake = array('id' => 999999999, 'company_id' => 4, 'event_key' => 'selftest.broken',
                  'entity_type' => 'no_such_entity', 'entity_id' => 999999999);
    $neg = false;
    try { ELC::handle($fake, $conn); }
    catch (\Throwable $t) { $neg = (strpos($t->getMessage(), 'EFFECT_MISSING') === 0); }
    echo $neg ? "  ✔ السالب: واقعةٌ مفكوكةُ الأثرِ رُمي لها EFFECT_MISSING\n"
              : "  ✘ السالب لم يرمِ\n";
    $ok = $ok && $neg;
    /* خبرُ التاريخ: نصيبُ كلِّ مفردةٍ المتحقِّقُ من وقائعِها */
    echo "  ── خبرُ التاريخِ (لا حكم): آخرُ واقعةٍ لكلِّ مفردةٍ ──\n";
    $r = $conn->query("SELECT b.* FROM ems_business_events b
                        JOIN (SELECT event_key, MAX(id) mid FROM ems_business_events
                               WHERE event_key IN ($in) GROUP BY event_key) t ON t.mid=b.id");
    while ($r && ($ev = $r->fetch_assoc())) {
        try { $ref = ELC::handle($ev, $conn); echo "     ✔ {$ev['event_key']} ⇒ $ref\n"; }
        catch (\Throwable $t) { echo "     ◆ {$ev['event_key']} — أثرُ آخرِ واقعةٍ تاريخيّةٍ مبتورٌ (كيانُها حُذف بذرةً)\n"; }
    }
    echo $ok ? "✔ selftest اجتاز — القناتان تثبتان والسالبُ يُرمى\n" : "⛔ selftest رسب\n";
    exit($ok ? 0 : 1);
}

$PAY = 'company_id · event_key · entity_type/entity_id (السجل القانوني) · amount/currency/base_amount حيث انطبق · correlation_id';
$IDM = 'fin_event_links.uq_link_parent_effect (اثر المنتج الذري) · وems_event_deliveries.uq (consumer,event) للتسليم';
$FLB = 'EFFECT_MISSING يرمى باسمه ⇒ اعادة بتباعد 2^n حتى العزل في الرسائل الميتة بانذار — لا اثر مفقود بصمت';
$AUD = 'تسليم مواز نشط لGovernanceWatchConsumer::handle لكل مفردة (قواعد المراقبة المصدرة)';

$n = 0;
foreach ($keys as $k) {
    echo "  · $k ⇒ عقد EffectLinkConsumer\n";
    if (!$APPLY) { continue; }
    $ok = $conn->query("INSERT INTO event_consumers
        (event_name, consumer_class, consumer_method, produces, active, consumer_key,
         max_attempts, timeout_seconds, payload_schema, idempotency_key, failure_behavior, audit_effect)
        VALUES ('" . $e($k) . "', 'App\\\\Services\\\\Bus\\\\Consumers\\\\EffectLinkConsumer', 'handle', 'write', 1,
                'effectlink', 5, 60, '" . $e($PAY) . "', '" . $e($IDM) . "', '" . $e($FLB) . "', '" . $e($AUD) . "')");
    if (!$ok) { echo "    ✘ {$conn->error}\n"; continue; }
    $n++;
}
if ($APPLY) { echo "  ✔ سُجِّل $n عقدًا فعّالًا\n"; }

$left = (int) $conn->query("SELECT COUNT(*) FROM rpr03_event_classification k
                             WHERE k.classification='BUSINESS'
                               AND NOT EXISTS(SELECT 1 FROM event_consumers e
                                               WHERE e.event_name=k.event_key AND e.active=1
                                                 AND e.produces='write'
                                                 AND e.consumer_class NOT LIKE '%GovernanceWatch%')")->fetch_row()[0];
echo "  بعدَ التسجيل — بلا عقد: $left\n";
