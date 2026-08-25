<?php
/**
 * tkt_sla_backfill.php — سدُّ مهل البلاغات المفتوحة
 * ───────────────────────────────────────────────────────────────────────────
 * يُعيد حسابَ موعدِ الإنجاز لكل بلاغٍ مفتوحٍ فقده أو بُني على سياسةٍ عُطِّلت:
 *   • بلاغاتُ المسار البرمجي أُنشئت بلا مهلةِ رأسٍ أصلًا، وحلقةُ الكنس تفلتر
 *     «الموعد غير فارغ» — فبقيت خارجَ التذكير والتصعيد وحسابِ التأخّر.
 *   • وبلاغاتٌ رُبطت بسياساتٍ مستوردةٍ خطأً عُطِّلت في ترحيلات التقوية.
 *
 * يستعمل TicketSla — مرجعَ المهلة الواحد — فلا تتكرّر قاعدةُ المطابقة هنا.
 * مُعادُ التشغيل: تكرارُه يعطي النتيجةَ نفسها ولا يمسّ بلاغًا مُهَلْ.
 *
 * التشغيل:  php scripts/tkt_sla_backfill.php [--dry]
 */

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Services/Tickets/TicketSla.php';

use App\Services\Tickets\TicketSla;

$dry = in_array('--dry', $argv, true);

$host = (string) ems_env('DB_HOST', 'localhost');
$port = 3306;
if (strpos($host, ':') !== false) {
    list($host, $p) = explode(':', $host, 2);
    $port = intval($p);
}
$conn = new mysqli($host, (string) ems_env('DB_USER', ''), (string) ems_env('DB_PASS', ''),
                   (string) ems_env('DB_NAME', ''), $port);
if ($conn->connect_errno) {
    fwrite(STDERR, "تعذر الاتصال بالقاعدة: " . $conn->connect_error . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

// المرشَّحون: مفتوحٌ بلا موعدِ إنجاز، أو مرتبطٌ بسياسةٍ لم تعد فعّالة.
$sql = "SELECT t.id, t.company_id, t.ticket_no, t.ticket_type_id, t.priority,
               t.business_impact, t.call_date, t.call_time, t.sla_policy_id
          FROM tickets t
          LEFT JOIN ticket_sla_policies p ON p.id = t.sla_policy_id
         WHERE t.stage NOT IN ('done','closed','cancelled')
           AND (t.resolution_due_at IS NULL
                OR (t.sla_policy_id IS NOT NULL AND COALESCE(p.active, 0) = 0))
         ORDER BY t.company_id, t.id";
$res = $conn->query($sql);
if (!$res) {
    fwrite(STDERR, "فشل الاستعلام: " . $conn->error . "\n");
    exit(1);
}

$total = 0; $applied = 0; $nopolicy = 0;
while ($t = $res->fetch_assoc()) {
    $total++;
    $policy = TicketSla::match($conn, $t['company_id'], $t['ticket_type_id'],
                               $t['priority'], $t['business_impact']);
    if ($policy === null) {
        $nopolicy++;
        printf("  %-14s → لا سياسة مطابقة (يبقى بلا مهلة)\n", $t['ticket_no']);
        continue;
    }
    $due = TicketSla::computeDue($t['call_date'], $t['call_time'], $policy);
    printf("  %-14s → %s (سياسة #%d · %sس)\n", $t['ticket_no'], $due['resolution'],
           $policy['id'], $policy['resolution_hours']);
    if (!$dry) {
        TicketSla::applyHeader($conn, $t['company_id'], $t['id'], $t['ticket_type_id'],
                               $t['priority'], $t['business_impact'], $t['call_date'], $t['call_time']);
        $applied++;
    }
}

printf("\n[sla-backfill]%s مرشح=%d · مهل=%d · بلا سياسة=%d\n",
       $dry ? ' (تجريبي)' : '', $total, $applied, $nopolicy);
