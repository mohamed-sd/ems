<?php
/**
 * E-07 — ردمُ فترات التوقف بلا مسؤول من مصفوفة الالتزامات — 2026-07-30
 * ───────────────────────────────────────────────────────────────────────────
 * «التصحيحُ بتعبئةٍ رجعيةٍ مسجَّلةٍ — بمطابقة المصدر لا بتخمين» (SPEC-00 §3.1).
 *
 * الاشتقاقُ الحتمي (لا اختراع):
 *   ① حالةُ التوقف → بندُ الالتزام (خريطةُ النظام نفسِه):
 *        tech_breakdown → equipment_readiness · fuel_logistics_stop → fuel
 *   ② عقدُ الواقعة (unit_time_log.entry_id → unit_entries.contract_id)
 *   ③ الملتزمُ من مصفوفة العقد المعتمدة (contract_obligations.obligor)
 * وما تعذّر حلُّه بهذه السلسلة يُعلَن ولا يُخمَّن.
 *
 * التشغيل:  php database/seeds/e07_stop_resp_backfill.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$dry = in_array('--dry-run', $argv, true);
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$MAP = array('tech_breakdown' => 'equipment_readiness', 'fuel_logistics_stop' => 'fuel');

$rows = array();
$r = $conn->query(
    "SELECT l.id, l.ops_state, l.hours, l.log_date, l.company_id,
            e.contract_id,
            o.obligor
       FROM unit_time_log l
       LEFT JOIN unit_entries e ON e.id = l.entry_id
       LEFT JOIN contract_obligations o
              ON o.client_contract_id = e.contract_id
             AND o.obligation_type = CASE l.ops_state
                     WHEN 'tech_breakdown' THEN 'equipment_readiness'
                     WHEN 'fuel_logistics_stop' THEN 'fuel' END
             AND COALESCE(o.is_deleted,0) = 0
             AND o.approval_state = 'approved'
      WHERE l.resp_party = 'none'
        AND l.ops_state IN ('tech_breakdown','fuel_logistics_stop','supplier_stop',
                            'operator_stop','client_stop','planned_stop','force_majeure')");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

echo "═ E-07 backfill — " . count($rows) . " صفَّ توقفٍ بلا مسؤول ═\n";
$fixed = 0; $unresolved = 0;
foreach ($rows as $x) {
    $obl = $MAP[$x['ops_state']] ?? null;
    if ($x['obligor'] === null || $obl === null) {
        echo "  ✗ صف {$x['id']} ({$x['ops_state']} · {$x['log_date']}): لا حكمَ مصفوفةٍ قابلًا للحل — يُعلَن ولا يُخمَّن\n";
        $unresolved++;
        continue;
    }
    echo "  → صف {$x['id']}: {$x['ops_state']} → بند {$obl} → ملتزمُ العقد {$x['contract_id']} = «{$x['obligor']}»"
       . ($dry ? " [dry-run]" : "") . "\n";
    if (!$dry) {
        $st = $conn->prepare("UPDATE unit_time_log SET resp_party = ?, obligation_type = ? WHERE id = ?");
        $st->bind_param('ssi', $x['obligor'], $obl, $x['id']);
        $ok = $st->execute();
        $st->close();
        if ($ok) {
            $fixed++;
            require_once dirname(__DIR__, 2) . '/includes/audit_trail.php';
            ems_audit_change($conn, 'contracts', 'seeds/e07_stop_resp_backfill', 'backfill',
                intval($x['id']),
                array('resp_party' => 'none', 'obligation_type' => null),
                array('resp_party' => $x['obligor'], 'obligation_type' => $obl,
                      '_note' => 'E-07: من مصفوفة التزامات العقد ' . $x['contract_id']),
                array('company_id' => intval($x['company_id']), 'user_id' => 0));
        }
    }
}
echo "═ النتيجة: أُصلح {$fixed} · تعذّر {$unresolved} · " . ($dry ? "dry-run" : "نُفِّذ") . " ═\n";
exit($unresolved > 0 ? 1 : 0);
