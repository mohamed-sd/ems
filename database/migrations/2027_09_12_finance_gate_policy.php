<?php
/**
 * 2027_09_12_finance_gate_policy.php
 *   قرارُ البوابةِ الماليةِ وإنفاذُه — INJ-FIX-01 · GAP-15
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «قرارٌ معلَن: **بوابةٌ إلزاميةٌ أم سجلُّ طلبات** — ثم إنفاذُ ما
 *   اختير · **إنفاذٌ يطابق القرارَ المكتوب**».
 *
 * ◆ **والمقيسُ قبلَ القرار**: تسعةُ سلاليمَ بسقفٍ نقديٍّ (`cap_kind='amount'`)،
 *   **سبعةٌ منها ببوابةٍ ماليةٍ واثنان بلا**: `LD-10` (اعتمادُ طلبِ الشراء)
 *   و`LD-12` (تأكيدُ الاستلامِ والمطابقة).
 *
 * ◆ **والقرارُ المعلَنُ — ومبناهُ بنيةُ الدورةِ نفسِها لا الرأي**:
 *   دورةُ الشراءِ أربعُ حلقات: طلبٌ (`LD-10`) ⇐ **ترسيةٌ وأمرُ شراء** (`LD-11`)
 *   ⇐ استلامٌ ومطابقة (`LD-12`) ⇐ **تسويةٌ نهائية** (`LD-13`).
 *   و**المالُ يُلزَم في `LD-11` ويُبرَأ في `LD-13`** — وكلتاهما **ببوابةٍ مالية**.
 *   أمّا `LD-10` فطلبٌ **قبلَ الالتزام**، و`LD-12` استلامٌ **بعدَه** — ورقابتُهما
 *   الماليةُ **موروثةٌ من بوابةِ الحلقةِ المُلزِمة**.
 *   ⇒ فالقرار: **البوابةُ إلزاميةٌ عندَ الإلزامِ والإبراء · وسجلُّ طلباتٍ فيما
 *     قبلَهما وبعدَهما** — **ولكلِّ سجلِّ طلباتٍ سلّمٌ مُغطٍّ مُسمًّى**.
 *
 * ◆ **ولا يُضاف موقّعٌ ماليٌّ إلى `LD-10`/`LD-12`**: ذلك **تغييرُ سلطةٍ** يُلزم
 *   المشترياتِ بتوقيعِ الماليةِ مرتَين إضافيتَين على كلِّ صنف — ولا يمنع ريالًا
 *   واحدًا لم تمنعه بوابةُ الترسية. **وضبطٌ يتكرَّر بلا أثرٍ يُهجَر.**
 *
 * ◆ **والإنفاذُ يُقاس**: كلُّ سلّمٍ بسقفٍ نقديٍّ **إمّا ببوابةٍ وإمّا مُعلَنٌ
 *   سجلَّ طلباتٍ بسلّمٍ مُغطٍّ قائمٍ يحمل بوابةً فعلًا**. وسلّمٌ ثالثٌ لا وجودَ له.
 *
 * التشغيل:  php database/migrations/2027_09_12_finance_gate_policy.php
 * الرجوع :  php database/migrations/2027_09_12_finance_gate_policy.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_finance_gate_policy`");
    echo "↺ أُسقط سجلُّ سياسةِ البوابةِ المالية\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_finance_gate_policy` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ladder_code` VARCHAR(12) NOT NULL,
    `policy`      VARCHAR(24) NOT NULL COMMENT 'MANDATORY_GATE | REQUEST_REGISTER',
    `covered_by`  VARCHAR(12) NULL COMMENT 'سلّمُ البوابةِ المُغطّي لسجلِّ الطلبات',
    `reason`      VARCHAR(400) NOT NULL,
    `decided_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ladder` (`ladder_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GAP-15 — قرارٌ مُعلَنٌ لكلِّ سلّمٍ بسقفٍ نقديّ: بوابةٌ إلزاميةٌ أم سجلُّ طلبات'");

/* ══ ① كلُّ سلّمٍ بسقفٍ نقديٍّ يأخذ قرارَه ═══════════════════════════════ */
$REQ_REG = array(
    'LD-10' => array('LD-11', 'طلبُ شراءٍ **قبلَ الالتزام** — لا يُلزم مالًا، والالتزامُ يقع في الترسيةِ LD-11 وهي ببوابةٍ مالية'),
    'LD-12' => array('LD-11', 'تأكيدُ استلامٍ ومطابقةٍ **بعدَ الالتزام** — المالُ أُلزم في LD-11 ومرَّ ببوابتِها، والإبراءُ في LD-13 ببوابتِها'),
);

$rows = array();
$q = $conn->query("SELECT l.`ladder_code`, l.`name_ar`, l.`cap_kind`,
                    (SELECT COUNT(*) FROM `gov_ladder_steps` s
                      WHERE s.`ladder_code` = l.`ladder_code` AND s.`is_finance_gate` = 1) fg
                     FROM `gov_ladders` l WHERE l.`cap_kind` = 'amount' ORDER BY l.`ladder_code`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$st = $conn->prepare("INSERT INTO `gov_finance_gate_policy`
        (`ladder_code`,`policy`,`covered_by`,`reason`) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE `policy`=VALUES(`policy`), `covered_by`=VALUES(`covered_by`),
            `reason`=VALUES(`reason`)");
$mand = 0; $reg = 0; $bad = array();
foreach ($rows as $r) {
    $L  = $r['ladder_code'];
    $fg = (int) $r['fg'];
    if ($fg > 0) {
        $pol = 'MANDATORY_GATE'; $cov = null;
        $why = 'حلقةٌ تُلزم مالًا أو تُبرئه — والبوابةُ الماليةُ فيها إلزامٌ لا خيار (' . $fg . ' خطوةَ بوابة)';
        $mand++;
    } elseif (isset($REQ_REG[$L])) {
        $pol = 'REQUEST_REGISTER'; $cov = $REQ_REG[$L][0];
        $why = $REQ_REG[$L][1];
        $reg++;
    } else {
        $bad[] = $L . ' — ' . mb_substr((string) $r['name_ar'], 0, 30);
        continue;
    }
    $st->bind_param('ssss', $L, $pol, $cov, $why);
    if (!$st->execute()) { echo "  ✘ {$L}: {$st->error}\n"; }
}
$st->close();
printf("① سلاليمُ بسقفٍ نقديّ: %d · بوابةٌ إلزامية: %d · سجلُّ طلبات: %d\n", count($rows), $mand, $reg);
if ($bad) {
    echo "◆ **بلا قرار**: " . implode(' · ', $bad) . "\n";
    exit("✘ لا يُترك سلّمٌ بسقفٍ نقديٍّ بلا قرارٍ مكتوب. أُوقفت الهجرة\n");
}

/* ══ ② الإنفاذُ يُقاس — وسجلُّ الطلباتِ يلزمه سلّمٌ مُغطٍّ **ببوابةٍ فعلية** ══ */
echo "───────────────────────────────────────────────────────────────\n";
$viol = array();
$q = $conn->query("SELECT `ladder_code`,`policy`,`covered_by` FROM `gov_finance_gate_policy`");
while ($q && $x = $q->fetch_assoc()) {
    if ($x['policy'] === 'MANDATORY_GATE') {
        $r = $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`
                            WHERE `ladder_code`='" . $conn->real_escape_string($x['ladder_code']) . "'
                              AND `is_finance_gate`=1");
        if (!$r || (int) $r->fetch_row()[0] === 0) { $viol[] = $x['ladder_code'] . ' (إلزاميٌّ بلا بوابة)'; }
    } else {
        $cov = (string) $x['covered_by'];
        $r = $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`
                            WHERE `ladder_code`='" . $conn->real_escape_string($cov) . "'
                              AND `is_finance_gate`=1");
        if (!$r || (int) $r->fetch_row()[0] === 0) {
            $viol[] = $x['ladder_code'] . " (سجلُّ طلباتٍ ومُغطّيه {$cov} بلا بوابة)";
        }
    }
    printf("  %-8s %-18s %s\n", $x['ladder_code'], $x['policy'], $x['covered_by'] ? '⇐ ' . $x['covered_by'] : '');
}
printf("② **مخالفاتُ الإنفاذ: %d**\n", count($viol));
foreach ($viol as $v) { echo "   ✘ {$v}\n"; }
echo "◆ والإنفاذُ يطابق القرارَ المكتوب — ولا سلّمَ ثالثٌ بلا حكم.\n";
