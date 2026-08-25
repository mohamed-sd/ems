<?php
/**
 * tools/repair01_w2_project.php
 *   REPAIR01 · W02 — **إسقاطُ حكمِ الدفترِ على السجلِّ الحيّ**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدفترُ حكمٌ والسجلُّ الحيُّ إسقاطُه** (W01_CLOSURE §٧). وW01 أغلقت الحكمَ
 *   ولم تُسقطه، ووُكِّل الإسقاطُ إلى هذه المرحلةِ لأنّ الترتيبَ الحاكمَ:
 *   الملكيّةُ ← المسارُ ← السايدبار.
 *
 * ◆ **وإسقاطان لا واحد — وحكمُهما مختلف**:
 *   ① `resp_role` من `repair01_surfaces` إلى `gov_screen_cycle` — **يُنفَّذ**.
 *      ملءُ عمودٍ قائمٍ بقيمةٍ يحملها الدفترُ سلفًا: لا يفتح وصولًا ولا يغلقه،
 *      ولا يُصيَّر إلّا في ترويسةِ الشاشة. وعكسُه سطرٌ واحد (`--revert`).
 *   ② سحبُ ٢١٤ ظهورًا محرَّمًا إلى مالكِه — **يُقاس ولا يُنفَّذ هنا**، ويُسجَّل
 *      قرارًا. والسببُ في `--report` أدناه وفي `W02_CLOSURE §٧`.
 *
 * ◆ **ولا يُحذف صفٌّ ولا يُقلَب قفلٌ في وضعِ التقرير**: `--report` قراءةٌ محضة.
 *
 * التشغيل:
 *   php tools/repair01_w2_project.php --report     # الافتراضيّ — قياسٌ بلا كتابة
 *   php tools/repair01_w2_project.php --apply      # ① وحدَه
 *   php tools/repair01_w2_project.php --revert     # إرجاعُ ①
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w2_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');

$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$EMPTY  = "TRIM(resp_role) IN ('', '—', '-')";
function e2(mysqli $c, $s) { return "'" . $c->real_escape_string((string) $s) . "'"; }

echo "═══ REPAIR01 · W02 — إسقاطُ الدفترِ على الحيّ ═══\n";

/* ═══ ① الدورُ المسؤولُ: الدفترُ ⇐ سجلُّ الدورةِ الحيّ ═══════════════════ */
$pairs = array();     /* (screen_file, dept_legacy, stage_name) ⇐ resp_role */
$r = $conn->query("SELECT screen_file, dept_legacy, stage_name, resp_role, role_source
                   FROM repair01_surfaces WHERE role_source <> ''");
while ($r && $x = $r->fetch_assoc()) {
    $k = $x['screen_file'] . '|' . $x['dept_legacy'] . '|' . $x['stage_name'];
    $pairs[$k] = $x;
}
echo '① أحكامُ الدورِ في الدفتر: ' . count($pairs) . "\n";

$liveEmpty = (int) ($conn->query("SELECT COUNT(*) FROM gov_screen_cycle WHERE $EMPTY")->fetch_row()[0] ?? 0);
echo "   وسجلُّ الدورةِ الحيُّ بلا دورٍ مسؤول: $liveEmpty صفًّا\n";

if ($REVERT) {
    /* الإرجاعُ يُفرَّغ **ما مُلئ بهذه الأداةِ وحدَه** — بالمطابقةِ مع الدفتر،
       لا بإفراغِ كلِّ فارغٍ كان قبلَها (وهو ما يمحو أحكامًا سابقة). */
    $n = 0;
    foreach ($pairs as $k => $x) {
        list($f, $d, $s) = explode('|', $k, 3);
        $sql = "UPDATE gov_screen_cycle SET resp_role = '' WHERE screen_file = " . e2($conn, $f)
             . " AND dept_name = " . e2($conn, $d) . " AND stage_name = " . e2($conn, $s)
             . " AND resp_role = " . e2($conn, $x['resp_role']);
        if ($conn->query($sql)) { $n += $conn->affected_rows; }
    }
    echo "◀ أُرجع: $n صفًّا إلى الفراغ\n";
    exit(0);
}

$would = 0; $conflict = 0; $miss = 0;
foreach ($pairs as $k => $x) {
    list($f, $d, $s) = explode('|', $k, 3);
    $q = $conn->query("SELECT COUNT(*) n, SUM($EMPTY) e FROM gov_screen_cycle
                       WHERE screen_file = " . e2($conn, $f) . " AND dept_name = " . e2($conn, $d)
                     . " AND stage_name = " . e2($conn, $s));
    $row = $q ? $q->fetch_assoc() : null;
    if (!$row || (int) $row['n'] === 0) { $miss++; continue; }
    if ((int) $row['e'] === 0) { $conflict++; continue; }   /* مملوءٌ سلفًا — لا يُدهَس */
    $would += (int) $row['e'];
    if ($APPLY) {
        $conn->query("UPDATE gov_screen_cycle SET resp_role = " . e2($conn, $x['resp_role'])
            . " WHERE screen_file = " . e2($conn, $f) . " AND dept_name = " . e2($conn, $d)
            . " AND stage_name = " . e2($conn, $s) . " AND $EMPTY");
    }
}
echo '   ' . ($APPLY ? '✔ مُلئ' : '· سيُملأ') . ": $would صفًّا · مملوءٌ سلفًا فلم يُدهَس $conflict · بلا صفٍّ مقابلٍ $miss\n";
if ($APPLY) {
    $after = (int) ($conn->query("SELECT COUNT(*) FROM gov_screen_cycle WHERE $EMPTY")->fetch_row()[0] ?? 0);
    echo "   وبعده: $after صفًّا بلا دورٍ مسؤول\n";
}

/* ═══ ② سحبُ الظهورِ المحرَّم — قياسٌ لا تنفيذ ══════════════════════════ */
echo "\n② سحبُ الظهورِ المحرَّمِ إلى مالكِه — **قياسٌ لا تنفيذ**\n";
$q = $conn->query("SELECT r.role_id, o.space_role, o.route, o.screen,
                          MAX(CASE WHEN n.active = 1 THEN 1 ELSE 0 END) live,
                          COUNT(n.id) rows_n
                   FROM repair01_ownership o
                   JOIN gov_space_roles r ON r.space_ar = o.space_role
                   LEFT JOIN nav_items n ON n.role_id = r.role_id AND n.route = o.route
                   WHERE o.w1_verdict = 'REVOKED_TO_OWNER'
                   GROUP BY r.role_id, o.space_role, o.route, o.screen");
$tot = 0; $live = 0; $noRow = 0; $byRole = array();
while ($q && $x = $q->fetch_assoc()) {
    $tot++;
    if ((int) $x['rows_n'] === 0) { $noRow++; continue; }
    if ((int) $x['live'] === 1) { $live++; $byRole[$x['space_role']] = ($byRole[$x['space_role']] ?? 0) + 1; }
}
echo "   أحكامُ السحب: $tot زوجًا (دورٌ × مسار) · **حيٌّ الآن $live** · بلا صفٍّ في التنقّلِ $noRow\n";
arsort($byRole);
foreach ($byRole as $sp => $n) { echo '     · ' . str_pad($sp, 26) . " $n\n"; }

$why = 'تنفيذُ السحبِ يُطفئ ' . $live . ' رابطًا حيًّا في قوائمِ ' . count($byRole)
     . ' مساحةٍ دفعةً واحدة. وهو **تغييرُ وصولٍ حيّ** خارجَ المهامِّ الأربعِ في §٤ من '
     . 'ملفِّ هذه المرحلة (السجلُّ · الأشباحُ · توليدُ السايدبار · الحارس)، ونمطُ '
     . 'الحملةِ المقيسُ يمنع الإنفاذَ قبلَ نافذةِ ظلٍّ ومراجعةِ فروق. والأداةُ '
     . 'مبنيّةٌ ومقيسةٌ — ينقصها قرارُ تشغيلٍ من المالك.';
$conn->query("INSERT INTO repair01_w2_decisions
  (decision_id, stage, topic, question, ruling, rationale, evidence, scope_rows, status)
  VALUES ('W2-D-03','W02','تنفيذُ سحبِ الظهورِ المحرَّمِ في الصلاحياتِ الحيّة',
    'متى تُطفأ الروابطُ التي حكمت W01 بسحبها إلى مالكِها — وهل يُغلق معها المقصدُ أم يبقى؟',
    'مقيسٌ ومسجَّلٌ ولم يُنفَّذ في W02 — ينتظر قرارَ تشغيلٍ ونافذةَ ظلّ',
    " . e2($conn, $why) . ",
    'repair01_ownership · w1_verdict=REVOKED_TO_OWNER × gov_space_roles × nav_items',
    $live, 'RECORDED_PENDING_OWNER')
  ON DUPLICATE KEY UPDATE scope_rows = VALUES(scope_rows), rationale = VALUES(rationale)");
echo "   ⇐ سُجِّل القرارُ W2-D-03 (نطاقُه $live)\n";

if (!$APPLY) { echo "\n(وضعُ التقرير) لم يُكتب في السجلِّ الحيِّ شيء.\n"; }
