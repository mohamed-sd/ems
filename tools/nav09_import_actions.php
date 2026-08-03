<?php
/**
 * tools/nav09_import_actions.php — مستوردُ الورقة 97 (NAV-09 ⓪-6 · حكم ١٤)
 * ───────────────────────────────────────────────────────────────────────────
 * «ربطُ الأحداث من الورقة 97 — ولا يُكتب في الكود.» كلُّ فعلٍ قانونيٍّ يُسجَّل
 * في الخريطة بعقده الكامل، وحالُه:
 *   alias   — له تنفيذٌ حيٌّ عندنا (بكوده نفسِه أو بمواءمةٍ يدوية)
 *   pending — ينتظر بناءَ شاشته — عقدُه محفوظٌ هنا ويُرقّى عندها
 * آمنُ الإعادة، ويطبع فرقَ التحديثات القادمة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* المواءماتُ اليدوية: كودٌ قانونيٌّ → كودُنا الحي */
$ALIAS = array(
    'chain.site'     => 'unit.chain.approve',
    'chain.ops'      => 'unit.chain.approve',
    'chain.complete' => 'unit.chain.approve',
    'stop.attribute' => 'ts.stop.assign',
    'operator.swap'  => 'swap.request',
    'trip.arrive'    => 'transfer.arrive',
    'trip.cost'      => 'transfer.close_cost',
    'period.close'   => 'period.provisions.run',
    'approval.grant' => 'ajax.approvals.hours_approval_handler',
    'ticket.open'    => 'ticket.classify',
    'amend.activate' => 'ajax.contracts.contract_actions_handler',
    'supp.eval'      => 'financing.deviation.close',
);
/* تدقيق: المواءمةُ لا تصح إلا لكودٍ حيٍّ فعلًا */
$live = array();
$r = mysqli_query($conn, "SELECT action_code FROM actions WHERE active = 1");
while ($x = mysqli_fetch_row($r)) { $live[$x[0]] = 1; }

$doc = Nav09Reader::load(dirname(__DIR__) . '/docs/files/NAV-09-current.xlsx');

/* عنوانُ الشاشة → ملفُّها القانوني (من مصفوفة العرض) لربط الفعل بشاشته */
$fileOfTitle = array();
foreach ($doc['matrix'] as $m) { $fileOfTitle[$m['title']] = $m['file']; }

$counts = array('alias' => 0, 'pending' => 0);
foreach ($doc['impact'] as $a) {
    $code = $a['code'];
    $liveCode = null;
    if (isset($live[$code])) { $liveCode = $code; }
    elseif (isset($ALIAS[$code]) && isset($live[$ALIAS[$code]])) { $liveCode = $ALIAS[$code]; }
    $state = $liveCode !== null ? 'alias' : 'pending';
    $counts[$state]++;
    $cf = isset($fileOfTitle[$a['screen']]) ? $fileOfTitle[$a['screen']] : null;
    $q = sprintf(
        "INSERT INTO nav09_action_map (canonical_code, label_ar, screen_title, canonical_file, actor_ar,
             writes_text, event_name, consumers_text, effect_text, reverse_text, live_code, state)
         VALUES ('%s', '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', %s, '%s')
         ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar), screen_title = VALUES(screen_title),
             canonical_file = VALUES(canonical_file), writes_text = VALUES(writes_text),
             event_name = VALUES(event_name), consumers_text = VALUES(consumers_text),
             effect_text = VALUES(effect_text), reverse_text = VALUES(reverse_text),
             live_code = VALUES(live_code), state = VALUES(state)",
        mysqli_real_escape_string($conn, $code),
        mysqli_real_escape_string($conn, $a['label']),
        mysqli_real_escape_string($conn, $a['screen']),
        $cf === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $cf) . "'",
        mysqli_real_escape_string($conn, $a['actor']),
        mysqli_real_escape_string($conn, $a['writes']),
        mysqli_real_escape_string($conn, $a['event']),
        mysqli_real_escape_string($conn, $a['consumers']),
        mysqli_real_escape_string($conn, mb_substr($a['effect'], 0, 500)),
        mysqli_real_escape_string($conn, $a['reverse']),
        $liveCode === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $liveCode) . "'",
        $state);
    mysqli_query($conn, $q) or die('✘ ' . mysqli_error($conn) . "\n");
}
echo "خريطة 97: alias={$counts['alias']} · pending={$counts['pending']}\n";
$r = mysqli_query($conn, "SELECT COUNT(*) FROM nav09_action_map");
echo "الإجمالي: " . mysqli_fetch_row($r)[0] . " فعلًا قانونيًّا\n";
