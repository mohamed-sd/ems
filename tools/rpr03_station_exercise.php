<?php
/**
 * tools/rpr03_station_exercise.php — ممارسةُ المحطّتَين الصامتتَين (البند ⑧)
 * ═══════════════════════════════════════════════════════════════════════════
 * `RPR-03` §٨·٢: «البناءُ الذي لم يُمارَس مرّةً واحدةً لا يُعرَف أصحيحٌ هو أم
 * لا … ومعاملةٌ واحدةٌ حقيقيّةٌ تكفي لفتحِ الطريق — وتُمرَّر ضمنَ رحلتِها لا
 * منفردة». المحطّتان: **الخزينة** و**مخالفاتُ الموردين**.
 *
 * ◆ ⛔ **لا رقمَ من عندي**: كلا المعاملتَين إسقاطُ واقعةٍ مسجَّلةٍ سلفًا:
 *   ① الخزينة — الطلبُ الماليُّ `FIN_-00009` (id=574) نقديٌّ بحالةِ
 *      `collected`: المالُ قُبض نقدًا بسجلِّ البوّابةِ **ولا حركةَ خزينةٍ
 *      تعكسه** — فتُقيَّد حركةُ القبضِ بمرجعِه ومبلغِه وعملتِه **مقروءةً من
 *      صفِّه حيًّا**، عبرَ `TreasuryCycleService::recordMove` (مسارُ الشاشةِ
 *      نفسُه). والصندوقُ يُنشأ برصيدِ افتتاحٍ **صفر** (لا قيمةَ مُخترَعة).
 *   ② المخالفة — تقييمُ الموردِ 1612 يحمل بندَ `incidents` مقيسًا
 *      (`supplier_evaluation_lines`) — واقعةُ سلامةٍ مرصودةٌ بلا سجلِّ
 *      مخالفةٍ يعكسها — فتُسجَّل عبرَ `CapabilityService::recordViolation`
 *      (مسارُ الشاشةِ نفسُه) بجزاءٍ **صفر**: فالجزاءُ الماليُّ قرارُ
 *      اعتمادٍ لا قرارُ راصد.
 * ◆ والفاعلُ مستخدمٌ حقيقيٌّ (id=4 — مُقرِّرُ الطلبِ الماليِّ نفسِه).
 *
 * التشغيل: php tools/rpr03_station_exercise.php [--apply]
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
$APPLY = in_array('--apply', $argv, true);

$CO = 4; $ACTOR = 4;
$_SESSION = array('user' => array('id' => $ACTOR, 'company_id' => $CO, 'role' => '17'));
require_once $ROOT . '/includes/tenant_scope.php';
require_once $ROOT . '/app/Services/Treasury/TreasuryCycleService.php';
require_once $ROOT . '/app/Services/Align/CapabilityService.php';

use App\Services\Treasury\TreasuryCycleService as TRE;
use App\Services\Align\CapabilityService as CAP;

$gate = ems_tenant_db();

echo "═══ البند ⑧ — ممارسةُ المحطّتَين الصامتتَين" . ($APPLY ? '' : ' · DRY') . " ═══\n";

/* ── ① الخزينة: قبضُ FIN_-00009 المقبوضِ فعلًا ─────────────────────────── */
$fr = $conn->query("SELECT id, request_no, amount, currency, payment_method, state, decided_by
                      FROM fin_requests WHERE id = 574 AND company_id = $CO")->fetch_assoc();
if (!$fr || $fr['state'] !== 'collected' || $fr['payment_method'] !== 'cash') {
    exit("⛔ واقعةُ التأريضِ تغيّرت — لا يُقيَّد شيء\n");
}
echo "  ① الواقعة: {$fr['request_no']} · {$fr['amount']} {$fr['currency']} · نقدًا · collected\n";

$exists = $conn->query("SELECT COUNT(*) c FROM tre_cash_move WHERE ref_kind='fin_request' AND ref_id=574")->fetch_assoc()['c'];
if ((int) $exists > 0) {
    echo "     ◆ الحركةُ مقيَّدةٌ سلفًا — عطالةٌ تصون التكرار\n";
} elseif ($APPLY) {
    $box = $gate->selectOne('tre_cash_box', array('where' => array('code' => 'BOX-MAIN')));
    if (!$box) {
        $boxId = $gate->insert('tre_cash_box', array(
            'code' => 'BOX-MAIN', 'name' => 'الصندوق الرئيسي',
            'currency' => (string) $fr['currency'], 'custodian_id' => $ACTOR, 'site_id' => 0,
            'opening_balance' => 0, 'is_active' => 1, 'created_by' => $ACTOR,
        ));
        echo "     ✔ أُنشئ الصندوقُ BOX-MAIN (id=$boxId) برصيدِ افتتاحٍ صفر\n";
    } else { $boxId = (int) $box['id']; }
    $res = TRE::recordMove($gate, array(
        'vessel_kind' => TRE::VESSEL_BOX, 'vessel_id' => $boxId, 'direction' => 'in',
        'amount' => (float) $fr['amount'], 'currency' => (string) $fr['currency'],
        'ref_kind' => 'fin_request', 'ref_id' => 574,
        'note' => 'قيد قبض الطلب ' . $fr['request_no'] . ' المقبوض نقدا بحالة collected — FINAL_CLOSE 8: المحطة تمارس بواقعة مسجلة لا باختراع',
    ), $ACTOR);
    echo $res['ok'] ? "     ✔ قُيِّدت حركةُ القبضِ (move_id={$res['move_id']}) بمرجعِ الطلبِ ومبلغِه الحيّ\n"
                    : "     ✘ {$res['code']}: " . (isset($res['detail']) ? $res['detail'] : '') . "\n";
}

/* ── ② المخالفة: واقعةُ incidents المقيسةُ للموردِ 1612 ────────────────── */
$ev = $conn->query("SELECT l.id line_id, l.evaluation_id, l.measurable, l.weight, e.supplier_id, e.period_from
                      FROM supplier_evaluation_lines l JOIN supplier_evaluations e ON e.id = l.evaluation_id
                     WHERE l.indicator = 'incidents' AND l.company_id = $CO
                     ORDER BY (l.measurable + 0) DESC LIMIT 1")->fetch_assoc();
if (!$ev) { exit("⛔ لا واقعةَ incidents مقيسةً — لا تُخترع مخالفة\n"); }
echo "  ② الواقعة: تقييم #{$ev['evaluation_id']} سطر #{$ev['line_id']} — incidents مقيسة {$ev['measurable']} للمورد {$ev['supplier_id']}\n";
if ($APPLY) {
    $res = CAP::recordViolation($conn, $gate, $CO, array(
        'supplier_id' => (int) $ev['supplier_id'], 'violation_kind' => 'safety',
        'occurred_on' => substr((string) $ev['period_from'], 0, 10),
        'description' => 'حوادث مقيسة في تقييم المورد رقم ' . $ev['evaluation_id'] . ' (بند incidents = ' . $ev['measurable'] . ') — تسجيل المخالفة اسقاط للواقعة المرصودة، FINAL_CLOSE 8',
        'penalty_amount' => 0, 'currency' => '',
        'evidence_ref' => 'supplier_evaluations#' . $ev['evaluation_id'] . ' · line#' . $ev['line_id'],
    ), $ACTOR);
    echo "     " . ($res['ok'] ? '✔' : ($res['code'] === 200 ? '◆ عطالة:' : '✘')) . " {$res['reason']} ({$res['code']})\n";
}

/* ── القياسُ بعدًا ──────────────────────────────────────────────────────── */
$tre = 0;
$r = $conn->query("SHOW TABLES LIKE 'tre\\_%'");
while ($x = $r->fetch_row()) {
    $tre += (int) $conn->query("SELECT COUNT(*) c FROM {$x[0]}")->fetch_assoc()['c'];
}
$vio = (int) $conn->query("SELECT COUNT(*) c FROM sup_violations")->fetch_assoc()['c'];
echo "  ── بعدَ الممارسة: صفوفُ الخزينة $tre · صفوفُ المخالفات $vio\n";
echo ($tre > 0 && $vio > 0) ? "✔ المحطّتان لم تعودا صامتتَين\n" : ($APPLY ? "⛔ محطّةٌ ما زالت صامتة\n" : "◆ DRY\n");
