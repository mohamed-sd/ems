<?php
/**
 * tools/govui_decision_ui_impact.php — `APPROVED_DECISION_WITH_UNPROPAGATED_UI_IMPACT` (§15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بنصِّ §15**: «راجع 09 بحثًا عن أيِّ قرارٍ يؤثّر على اسمٍ أو ملكيةٍ أو دورٍ
 *   أو `Placement` أو `Screen` أو `Field` أو `State` أو `Approval` أو
 *   `Visibility`. ولكلِّ قرارٍ معتمد: `Decision ⇒ Impact Targets ⇒ Runtime`.
 *   المعيار `APPROVED_DECISION_WITH_UNPROPAGATED_UI_IMPACT = 0`».
 *
 * ◆ **والمقامُ ليس القراراتِ كلَّها**: قرارٌ لا يذكر سطحًا متأثِّرًا لا أثرَ
 *   واجهةٍ له فلا يدخل المقامَ — ويُسمّى استبعادُه. فالمقامُ **القراراتُ
 *   المعتمَدةُ التي تذكر أسطحًا متأثِّرةً** في `affected_screens`،
 *   **ودرجةُ حلِّ الجسرِ عمودٌ يُقرأ لا استبعادٌ يُخفي**.
 *
 * ◆ **والحكمُ من مِجَسِّ التشغيلِ نفسِه** (`gov_decision_propagation`) — فلا
 *   مِجَسَّ ثانيًا يتفرّق عنه. والأحكامُ: `RUNTIME_VERIFIED` · `RUNTIME_PRESENT`
 *   محسوبانِ منتشرَين · و`UNPROPAGATED` هو البسطُ المطلوبُ تصفيرُه ·
 *   و`TARGET_PROPAGATED_BUILD_PENDING` و`BLOCKED_OWNER_VALUES` **بحاجزَيهما
 *   المسمَّيَين** خارجَ البسطِ وداخلَ المقامِ مُعلَنَين.
 *
 * التشغيل: php tools/govui_decision_ui_impact.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

/* ◆ **والمقامُ يُوسَّع بشاهدٍ لا بتضييقٍ يُخضِّر**: جسرُ المعرِّفِ لم يحلَّ إلّا
   **خمسةَ قراراتٍ إلى معرِّفِ شاشة** (وسبعةٌ وستّون إلى وحدةٍ أو صنفِ نطاق،
   وخمسةٌ وستّون بلا حلّ). فلو قِيس على الخمسةِ لخرج صفرٌ من مقامٍ يكاد يكون
   فارغًا — وهو الأخضرُ الكاذبُ بعينِه [[measure-token-must-exist]].
   ⇒ **فالمقامُ كلُّ قرارٍ معتمَدٍ يذكر أسطحًا متأثِّرةً** (113)، والحكمُ من
   مِجَسِّ التشغيلِ نفسِه، **ودرجةُ الحلِّ عمودٌ يُقرأ لا استبعادٌ يُخفي**. */
$sql = "SELECT d.decision_id, d.domain, d.question, d.affected_screens, d.approved_at,
               COALESCE(NULLIF(GROUP_CONCAT(DISTINCT b.resolution ORDER BY b.resolution SEPARATOR '+'), ''), 'NO_BRIDGE') AS resolution,
               COUNT(DISTINCT NULLIF(b.screen_id, '')) AS screens,
               COALESCE(MAX(p.verdict), 'NO_PROBE') AS verdict,
               MAX(p.basis) AS basis
          FROM repair01_decisions d
          LEFT JOIN repair01_decision_screen_bridge b ON b.decision_id = d.decision_id
          LEFT JOIN gov_decision_propagation p ON p.decision_id = d.decision_id
         WHERE d.status = 'APPROVED'
           AND d.affected_screens <> '' AND d.affected_screens <> '—'
         GROUP BY d.decision_id
         ORDER BY d.decision_id";
$rows = array(); $r = $conn->query($sql);
if (!$r) { exit("⛔ {$conn->error}
"); }
while ($x = $r->fetch_assoc()) { $rows[] = $x; }

$by = array();
foreach ($rows as $x) { $by[$x['verdict']] = (isset($by[$x['verdict']]) ? $by[$x['verdict']] : 0) + 1; }
ksort($by);
$den = count($rows);
$unprop = isset($by['UNPROPAGATED']) ? $by['UNPROPAGATED'] : 0;
$noProbe = isset($by['NO_PROBE']) ? $by['NO_PROBE'] : 0;

printf("═ APPROVED_DECISION_WITH_UNPROPAGATED_UI_IMPACT = %d ═\n", $unprop + $noProbe);
printf("  المقام: %d قرارًا معتمَدًا يذكر أسطحًا متأثِّرة · والمستبعَدُ قرارٌ لا يذكر سطحًا\n", $den);
foreach ($by as $v => $n) { printf("    %-34s %d\n", $v, $n); }
$open = array();
foreach ($rows as $x) {
    if ($x['verdict'] === 'UNPROPAGATED' || $x['verdict'] === 'NO_PROBE') { $open[] = $x; }
}
if ($open) {
    echo "\n  ⛔ غيرُ منتشرٍ بأسمائه:\n";
    foreach ($open as $x) {
        printf("     · %-16s [%s] %s — أسطحٌ %d\n", $x['decision_id'], $x['verdict'],
            mb_substr((string) $x['question'], 0, 60), $x['screens']);
    }
}

if ($MD) {
    $snap = 'BL-' . date('Ymd') . '-' . trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
    $md = "# `GOVUI_DECISION_UI_IMPACT` — أثرُ القراراتِ المعتمَدةِ في الواجهة (§15)\n\n"
        . "> مولَّدٌ حيًّا بـ`tools/govui_decision_ui_impact.php` · اللقطة **{$snap}** · " . date('Y-m-d H:i') . "\n"
        . "> **المقامُ** قرارٌ معتمَدٌ **يذكر أسطحًا متأثِّرة** — وقرارٌ بلا سطحٍ خارجَ المقامِ بإعلانِه.\n"
        . "> ⛔ **ولا يُضيَّق المقامُ إلى المحلولِ بمعرِّفٍ (خمسةٌ فقط) فيخرج صفرٌ من فراغ** — ودرجةُ الحلِّ عمودٌ يُقرأ.\n\n"
        . "| المقياس | القيمة |\n|---|---|\n"
        . "| `APPROVED_DECISION_WITH_UNPROPAGATED_UI_IMPACT` | **" . ($unprop + $noProbe) . "/{$den}** |\n";
    foreach ($by as $v => $n) { $md .= "| `{$v}` | {$n} |\n"; }
    $md .= "\n## الصفوف\n\n| `Decision_ID` | المجال | حلُّ الجسر | أسطحٌ بمعرِّف | حكمُ التشغيل | سندُ المِجَسّ |\n|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $md .= "| `{$x['decision_id']}` | {$x['domain']} | `{$x['resolution']}` | {$x['screens']} | `{$x['verdict']}` | "
             . str_replace('|', '¦', mb_substr((string) $x['basis'], 0, 120)) . " |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_DECISION_UI_IMPACT.md', $md);
    echo "⇐ docs/REPAIR01_20260823/GOVUI_DECISION_UI_IMPACT.md\n";
}
