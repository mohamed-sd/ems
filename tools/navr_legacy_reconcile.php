<?php
/**
 * tools/navr_legacy_reconcile.php — مصالحةُ إرثِ gov_target_nav (§٢٠·§٢١)
 * ═══════════════════════════════════════════════════════════════════════════
 * يصنّف كلَّ صفٍّ إلى واحدٍ من الستّة — بقاعدةٍ مكتوبةٍ في `basis`:
 *   MATCHES_GOVERNING_TARGET — موضعُه في طبقةِ الدليلِ بمجموعتِه نفسِها.
 *   SUPERSEDED — لمساحةٍ مهاجرةٍ وطبقةُ الدليلِ حسمت له موضعًا مغايرًا
 *                (أو علامةُ GAP: تحملها الأهدافُ NOT_BUILT).
 *   APPROVED_POST_GUIDE_ADDITION — إعلانُ جولةٍ بسندٍ غيرِ RENDER-ALIGN.
 *   VALID_UTILITY — مرساةُ أداةٍ خارجَ الدورة (الرئيسية/المراسلات/مساحتي…).
 *   DUPLICATE — تكرارُ (دور·مسار) بعد أوّلِ صفّ.
 *   CURRENT_ONLY_UNGOVERNED — لا سندَ حاكمًا: **Finding يقترح ولا يعتمد نفسَه**.
 * ⛔ ولا حذفَ — المصالحةُ حكمٌ مسجَّلٌ والإرثُ Evidence.
 * التشغيل: php tools/navr_legacy_reconcile.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/unified_nav.php'; /* uxuiNavBaseRoute */
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* مواضعُ الدليلِ لكلِّ دورٍ PRIMARY: base ⇒ [مجموعاتُه المسنودة] */
$pl = array(); $migratedRole = array();
$q = $conn->query("SELECT wr.role_id, p.route, g.label_ar FROM nav_placements p
    JOIN nav_lifecycle_groups g ON g.id = p.group_id
    JOIN nav_ws_roles wr ON wr.workspace_id = p.workspace_id AND wr.binding = 'PRIMARY'
    WHERE p.active = 1 AND p.route IS NOT NULL");
while ($x = $q->fetch_assoc()) {
    $b = mb_strtolower(uxuiNavBaseRoute($x['route']));
    $pl[(int) $x['role_id']][$b][navr_lr_gz($x['label_ar'])] = true;
    $migratedRole[(int) $x['role_id']] = true;
}
function navr_lr_gz($s)
{
    $s = preg_replace('~\s+~u', ' ', trim((string) $s));
    return strtr($s, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', '–' => '—', '-' => '—'));
}

$UTIL = array('main/role_board.php', 'chats/index.php', 'main/profile.php', 'portal/my_tasks.php',
              'portal/my_reports.php', 'portal/notifications.php');

$seen = array(); $tot = array();
$q = $conn->query("SELECT id, role_id, route, group_ar, doc_code FROM gov_target_nav ORDER BY id");
$rows = array();
while ($x = $q->fetch_assoc()) { $rows[] = $x; }
echo "══ مصالحةُ إرثِ الملاحة (" . count($rows) . " صفًّا) " . ($APPLY ? '(كتابة)' : '(قياس)') . " ══\n";
foreach ($rows as $x) {
    $rid = (int) $x['role_id'];
    $isGap = strncmp((string) $x['route'], 'GAP:', 4) === 0;
    $b = $isGap ? '' : mb_strtolower(uxuiNavBaseRoute($x['route']));
    $k = $rid . '|' . ($isGap ? $x['route'] : $b);
    $verdict = ''; $basis = '';
    if (isset($seen[$k])) {
        $verdict = 'DUPLICATE';
        $basis = 'تكرارُ (دور·مسار) بعد الصفِّ #' . $seen[$k];
    } elseif ($isGap) {
        $verdict = 'SUPERSEDED';
        $basis = 'علامةُ فجوةٍ (GAP:) — طبقةُ الأهدافِ الجديدةُ تحملها صفوفَ NOT_BUILT بهويّتِها (nav_targets)';
    } elseif (in_array($b, $UTIL, true)) {
        $verdict = 'VALID_UTILITY';
        $basis = 'مرساةُ أداةٍ خارجَ الدورةِ بإعلانِها المعماريّ (الدستور §6 وأحكام مساحتي)';
    } elseif (isset($pl[$rid][$b])) {
        if (isset($pl[$rid][$b][navr_lr_gz($x['group_ar'])])) {
            $verdict = 'MATCHES_GOVERNING_TARGET';
            $basis = 'موضعُه في طبقةِ الدليلِ (nav_placements) بمجموعتِه نفسِها';
        } else {
            $verdict = 'SUPERSEDED';
            $basis = 'المساحةُ مهاجرةٌ وطبقةُ الدليلِ حسمت موضعَه في: ' . implode('/', array_keys($pl[$rid][$b]));
        }
    } elseif (strncmp((string) $x['doc_code'], 'RENDER-ALIGN', 12) !== 0) {
        $verdict = 'APPROVED_POST_GUIDE_ADDITION';
        $basis = 'إعلانُ جولةٍ بسندٍ مكتوبٍ (' . $x['doc_code'] . ') — يُراجَع عند استيرادِ نطاقِه';
    } elseif (isset($migratedRole[$rid])) {
        $verdict = 'CURRENT_ONLY_UNGOVERNED';
        $basis = 'RENDER-ALIGN لمساحةٍ مهاجرةٍ ولا موضعَ له في الدليل — Finding يقترح ولا يعتمد نفسَه Target';
    } else {
        $verdict = 'CURRENT_ONLY_UNGOVERNED';
        $basis = 'RENDER-ALIGN لدورٍ غيرِ مهاجرٍ بعدُ — يُصالَح عند استيرادِ نطاقِه (خريطة المصادر)';
    }
    $seen[$k] = $x['id'];
    $tot[$verdict] = ($tot[$verdict] ?? 0) + 1;
    if ($APPLY) {
        $conn->query("INSERT INTO gov_legacy_nav_recon (gtn_id, role_id, route, group_ar, doc_code, verdict, basis)
            VALUES (" . (int) $x['id'] . ", {$rid}, '" . $e($x['route']) . "', '" . $e($x['group_ar']) . "',
                    '" . $e($x['doc_code']) . "', '" . $e($verdict) . "', '" . $e($basis) . "')
            ON DUPLICATE KEY UPDATE verdict = VALUES(verdict), basis = VALUES(basis), reconciled_at = NOW()");
    }
}
echo "\n── الأحكام ──\n";
ksort($tot);
foreach ($tot as $k => $n) { printf("  %-32s %d\n", $k, $n); }
$sum = array_sum($tot);
printf("  الجملة %d من %d — والمقامُ يطابق: %s\n", $sum, count($rows), $sum === count($rows) ? '✔' : '⛔');
if (!$APPLY) { echo "\nقياسٌ فقط — أعد بـ--apply.\n"; }
