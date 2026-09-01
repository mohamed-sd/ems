<?php
/**
 * tools/navarch/closure.php — مقاييسُ إغلاقِ `NAV_ARCH_02_CLEAN` §٣
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولا رقمَ في تقريرٍ بلا أداةٍ تُشغَّل** [[measure-token-must-exist]]:
 *   هذه الأداةُ تُخرج المقاماتِ الأربعةَ التي يقيس بها البرومتُ الإغلاقَ،
 *   **وكلٌّ منها بشرطِه المكتوبِ في السطرِ نفسِه** — فلا يُقرأ صفرٌ على أنّه
 *   بلوغٌ وهو غيابُ قياس.
 *
 * ◆ **`PLACEMENT_WITHOUT_GROUP` مقامُه أصنافُ الدورةِ وحدَها** (§9):
 *   المُصيِّرُ لا يطلب رأسَ طيٍّ إلّا من `PRIMARY` و`SECONDARY_APPROVED` —
 *   وهما وحدَهما ما «يدخل Business Workspace Sidebar الطبيعي». وما عداهما
 *   **خارجَ مقامِ الدورةِ بحكمِ الدستور**: `GLOBAL_SHELL` (§10) · `PERSONAL`
 *   (§11) · `CONTEXTUAL_ACTION` و`TAB_CHILD` و`DIRECT_ONLY` و`UTILITY`
 *   و`EXECUTIVE_PROJECTION` (§9). ⛔ **فعدُّها في المقامِ يجعل الصفرَ مستحيلًا
 *   بتعريفِه** — رقمٌ يصف قارئَه لا مقروءَه.
 *
 * ◆ **و«لا صفَّ بلا حكم» يُقاس بمقياسِه هو**: `PLACEMENT_WITHOUT_RULING` —
 *   فالخروجُ من مقامِ الدورةِ **لا يُعفي من الحكمِ المكتوب** (§4 · §19):
 *   لكلِّ صفٍّ `reason_code` و`governing_source`.
 *
 * التشغيل: php tools/navarch/closure.php [--gate]
 *   `--gate` ⇒ يرسُب إن لم تبلغ المقاماتُ الأربعةُ أهدافَها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = in_array('--gate', $argv, true);

$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    return $r ? (int) $r->fetch_row()[0] : -1;
};

/* ⛔ **مفرداتُ الدورةِ تُقرأ من المخطَّطِ لا تُكتب هنا** — فمفردةٌ تُضاف إلى
   `placement_type` ولا يعلمها المقياسُ تُسقط صفوفًا صامتةً
   [[enum-vocabulary-consumers]]. والمقياسُ يُعلن ما لم يعرفه. */
$CYCLE = array('PRIMARY', 'SECONDARY_APPROVED');
$OUT_OF_CYCLE = array('GLOBAL_SHELL', 'PERSONAL', 'CONTEXTUAL_ACTION', 'TAB_CHILD',
                      'DIRECT_ONLY', 'UTILITY', 'EXECUTIVE_PROJECTION');
$known = array_merge($CYCLE, $OUT_OF_CYCLE);
$unknown = array();
$r = $conn->query("SELECT DISTINCT placement_type FROM nav_workspace_placements");
while ($x = $r->fetch_row()) { if (!in_array($x[0], $known, true)) { $unknown[] = $x[0]; } }

$inCycle = "'" . implode("','", $CYCLE) . "'";

$M = array();
$M['PLACEMENT_WITHOUT_GROUP'] = $one(
    "SELECT COUNT(*) FROM nav_workspace_placements
      WHERE status = 'ACTIVE' AND group_id IS NULL AND placement_type IN ({$inCycle})");
$M['PLACEMENT_WITHOUT_RULING'] = $one(
    "SELECT COUNT(*) FROM nav_workspace_placements
      WHERE status = 'ACTIVE'
        AND (reason_code IS NULL OR reason_code = ''
          OR governing_source IS NULL OR governing_source = '')");
/* ⛔ **والدورُ «نشطٌ» بروابطِه المقيسةِ لا بحقلِ حالةٍ**: `roles.status` يقول
   `1` لدورٍ مدموجٍ صفرِ الروابط — فالنشاطُ يُقاس بـ`nav_items` الحيّة. */
$M['ACTIVE_ROLE_WITHOUT_WORKSPACE'] = $one(
    "SELECT COUNT(*) FROM roles r
      WHERE NOT EXISTS (SELECT 1 FROM nav_ws_roles w WHERE w.role_id = r.id)
        AND (SELECT COUNT(*) FROM nav_items n WHERE n.role_id = r.id AND n.active = 1) > 0");
$bound = $one("SELECT COUNT(DISTINCT role_id) FROM nav_ws_roles");
$all   = $one("SELECT COUNT(*) FROM roles");

echo "══ NAV-ARCH-02 · مقاييسُ الإغلاق (‏§٣ من البرومت) ══\n\n";
printf("  %-34s = %-6d الهدف 0   %s\n", 'PLACEMENT_WITHOUT_GROUP',
       $M['PLACEMENT_WITHOUT_GROUP'], $M['PLACEMENT_WITHOUT_GROUP'] === 0 ? '✔' : '✖');
printf("  %-34s = %-6d الهدف 0   %s\n", 'PLACEMENT_WITHOUT_RULING',
       $M['PLACEMENT_WITHOUT_RULING'], $M['PLACEMENT_WITHOUT_RULING'] === 0 ? '✔' : '✖');
printf("  %-34s = %-6d الهدف 0   %s\n", 'ACTIVE_ROLE_WITHOUT_WORKSPACE',
       $M['ACTIVE_ROLE_WITHOUT_WORKSPACE'], $M['ACTIVE_ROLE_WITHOUT_WORKSPACE'] === 0 ? '✔' : '✖');
printf("  %-34s = %d/%d   %s\n", 'ROLES_BOUND_TO_WORKSPACE', $bound, $all,
       $bound === $all ? '✔' : '✖');

echo "\n  ── مواضعُ خارجَ مقامِ الدورةِ — بحكمٍ مكتوبٍ لكلِّ صفّ ──\n";
$r = $conn->query("SELECT placement_type, reason_code, COUNT(*) n
                     FROM nav_workspace_placements
                    WHERE status = 'ACTIVE' AND group_id IS NULL
                    GROUP BY 1, 2 ORDER BY n DESC");
$tot = 0;
while ($x = $r->fetch_assoc()) {
    printf("     %-22s %-34s %4d\n", $x['placement_type'], $x['reason_code'], $x['n']);
    $tot += (int) $x['n'];
}
printf("     %-22s %-34s %4d\n", '', 'المجموع', $tot);

if ($unknown) {
    echo "\n  ⛔ أصنافٌ لا يعرفها المقياس (‏تُصنَّف ولا تُبتلَع): "
       . implode(' · ', $unknown) . "\n";
}

echo "\n  ── الأدوارُ الفرعيّةُ وأمّهاتُها ──\n";
$r = $conn->query("SELECT w.workspace_id, w.role_id, w.parent_role_id, r.name
                     FROM nav_ws_roles w LEFT JOIN roles r ON r.id = w.role_id
                    WHERE w.binding = 'SECONDARY' ORDER BY w.workspace_id, w.role_id");
while ($x = $r->fetch_assoc()) {
    printf("     %-12s دور %-3d أب=%-5s %s\n", $x['workspace_id'], $x['role_id'],
           $x['parent_role_id'] ?: '—', $x['name']);
}

$ok = ($M['PLACEMENT_WITHOUT_GROUP'] === 0 && $M['PLACEMENT_WITHOUT_RULING'] === 0
    && $M['ACTIVE_ROLE_WITHOUT_WORKSPACE'] === 0 && $bound === $all && !$unknown);
echo "\n" . ($ok ? "✔ مقاماتُ §٣ الأربعةُ بالغةٌ\n" : "✖ مقامٌ لم يبلغ\n");

$out = $ROOT . '/docs/REPAIR01_20260823/navarch/NAV_ARCH_CLOSURE_METRICS.json';
file_put_contents($out, json_encode(array(
    'measured_at' => date('c'), 'metrics' => $M,
    'roles_bound' => $bound, 'roles_total' => $all,
    'out_of_cycle_ruled' => $tot, 'unknown_types' => $unknown, 'pass' => $ok
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  ⇒ {$out}\n";

if ($gate && !$ok) { exit(1); }
exit(0);
