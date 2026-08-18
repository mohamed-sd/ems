<?php
/**
 * 2027_07_04_caps_derive_six.php — السلاليمُ الستةُ واشتقاقُ سقوفِها (قرارُ المالك ⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تفويضُ المالكِ (خامسًا): «الستةُ الباقيةُ اشتقّها بالمنهجِ نفسِه الذي اشتققتَ
 *   به الثلاثةَ من تحليلِ 5,069 معاملة، وأدخلْها قيمًا ابتدائيةً في شاشةِ حدودِ
 *   المبالغِ مع أساسِ الاشتقاقِ مكتوبًا في سجلِّها».
 *
 * ◆ وحاجزٌ بنيويٌّ صادفَ التنفيذَ — ولم أتجاوزه: قادحُ قاعدةٍ يرفض أيَّ كتابةٍ
 *   في `gov_cap_history` بغيرِ اعتمادِ المالكِ (الدور 9) برسالةٍ صريحة:
 *   «تعديلُ السقفِ باعتمادِ المالكِ وحدَه». وهو **بوابةٌ** — وقاعدةُ المالكِ
 *   الثالثةُ تمنع إضعافَها، والرابعةُ تمنع كتابةَ اسمِ معتمِدٍ نصًّا. فانتحالُ
 *   هويةِ الدورِ 9 لإدخالِ الرقمِ خرقٌ للاثنتَين معًا.
 *   ولذلك: **يُبنى السلّمُ ويُشتقُّ الرقمُ ويُسجَّل أساسُه — ويُدخَل بضغطةِ
 *   المالكِ من شاشةِ حدودِ المبالغِ** (وهي مبنيةٌ وتعمل: Governance/authority_caps.php).
 *   فالرقمُ جاهزٌ محسوبٌ بأساسِه، ولا ينتظر حسابًا بل توقيعًا واحدًا.
 *
 * ما تفعله الهجرة:
 *   ① تبني السلاليمَ الستةَ LD-08..LD-13 بسلاسلِها **بقاعدةِ الثلاثِ خطوات**
 *      (قرارُ المالكِ ثانيًا: سلّمٌ ≤3 · وخطوةٌ بلا رفضٍ حقيقيٍّ تُحذف وتُستبدل
 *      بإخطار · ولا دورَ جديدًا بلا شخصٍ يملؤه — فكلُّ فاعلٍ هنا دورٌ قائم).
 *   ② تسجّل المقترحَ المشتقَّ في `gov_cap_proposals` بأساسِه الكاملِ (السكّانُ
 *      والمئيناتُ وتاريخُ القياس) — سجلٌّ للعرضِ لا للإنفاذ، فلا يمسُّ القادح.
 *   ③ وما لا سكّانَ له لا يُخترع له رقم: يُسجَّل «لا أساسَ للاشتقاق» ويبقى
 *      السلّمُ cap_unresolved معلَنًا (حالةٌ في قاموسِ الاثنتَي عشرة).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function caps_pctl($conn, $sql) {
    $v = array();
    $r = $conn->query($sql);
    if (!$r) { return array('n' => 0); }
    while ($x = $r->fetch_row()) { $f = (float) $x[0]; if ($f > 0) { $v[] = $f; } }
    if (!$v) { return array('n' => 0); }
    sort($v);
    $n = count($v);
    $at = function ($pp) use ($v, $n) { $i = (int) ceil($pp / 100 * $n) - 1; return $v[max(0, min($i, $n - 1))]; };
    return array('n' => $n, 'p50' => $at(50), 'p90' => $at(90), 'p95' => $at(95), 'p99' => $at(99), 'max' => $v[$n - 1]);
}
define('CAPS_MIN_POP', 20);

/* ── ① السلاليمُ الستةُ بسلاسلِها — كلُّ فاعلٍ دورٌ قائمٌ لا مخترَع ── */
$LAD = array(
    'LD-08' => array('slug' => 'fin_payment_request', 'name' => 'طلبُ الدفعِ والسداد', 'cycle' => 10, 'esc' => 24,
        'pop' => "SELECT amount FROM fin_payments WHERE amount > 0",
        'steps' => array(
            array(1, 'fin_accountant', 'محاسب المالية',        'entry',   0),
            array(2, 'fin_dept_mgr',   'مدير الإدارة المالية', 'approve', 1),
        )),
    'LD-09' => array('slug' => 'treasury_execute', 'name' => 'تنفيذُ الخزينةِ والبنك', 'cycle' => 11, 'esc' => 24,
        'pop' => "SELECT ABS(amount) FROM fin_bank_statement_lines WHERE amount <> 0",
        'steps' => array(
            array(1, 'fin_treasury',  'أمين الخزينة',   'entry',   0),
            array(2, 'fin_cfo',       'المدير المالي',  'approve', 1),
        )),
    'LD-10' => array('slug' => 'proc_request_approve', 'name' => 'اعتمادُ طلبِ الشراء', 'cycle' => 12, 'esc' => 48,
        'pop' => "SELECT 0 FROM proc_request WHERE 0",
        'steps' => array(
            array(1, 'proc_officer',  'مسؤول المشتريات', 'entry',   0),
            array(2, 'proc_mgr',      'مدير المشتريات',  'approve', 1),
        )),
    'LD-11' => array('slug' => 'proc_order_award', 'name' => 'الترسيةُ وأمرُ الشراء', 'cycle' => 13, 'esc' => 48,
        'pop' => "SELECT total_amount FROM proc_order WHERE total_amount > 0",
        'steps' => array(
            array(1, 'proc_officer',  'مسؤول المشتريات',      'entry',   0),
            array(2, 'proc_mgr',      'مدير المشتريات',       'review',  0),
            array(3, 'fin_dept_mgr',  'مدير الإدارة المالية', 'approve', 1),
        )),
    'LD-12' => array('slug' => 'proc_receipt_confirm', 'name' => 'تأكيدُ الاستلامِ والمطابقة', 'cycle' => 14, 'esc' => 48,
        'pop' => "SELECT 0 FROM proc_receipt_custody WHERE 0",
        'steps' => array(
            array(1, 'wh_keeper',     'أمين المستودع',   'entry',   0),
            array(2, 'proc_mgr',      'مدير المشتريات',  'approve', 1),
        )),
    'LD-13' => array('slug' => 'supplier_final_settlement', 'name' => 'التسويةُ النهائيةُ للمورد', 'cycle' => 15, 'esc' => 72,
        'pop' => "SELECT net_amount FROM settlements WHERE net_amount > 0",
        'steps' => array(
            array(1, 'sup_officer',   'مسؤول الموردين',       'entry',   0),
            array(2, 'fin_accountant','محاسب المالية',        'review',  0),
            array(3, 'fin_dept_mgr',  'مدير الإدارة المالية', 'approve', 1),
        )),
);

$insL = $conn->prepare("INSERT INTO gov_ladders (ladder_code, company_id, slug, name_ar, cycle_no, escalate_after_hours, cap_kind, cap_state, doc_ref, is_active)
                        VALUES (?,0,?,?,?,?,'amount','unresolved','LAD-01',1)
                        ON DUPLICATE KEY UPDATE slug=VALUES(slug), name_ar=VALUES(name_ar), cycle_no=VALUES(cycle_no),
                          escalate_after_hours=VALUES(escalate_after_hours), cap_kind=VALUES(cap_kind)");
$insS = $conn->prepare("INSERT IGNORE INTO gov_ladder_steps (company_id, ladder_code, step_no, actor_code, actor_name_ar, step_kind, is_accountant, is_finance_gate, may_approve)
                        VALUES (0,?,?,?,?,?,?,?,?)");
$nl = 0; $ns = 0;
foreach ($LAD as $code => $d) {
    $insL->bind_param('sssii', $code, $d['slug'], $d['name'], $d['cycle'], $d['esc']);
    $insL->execute(); $nl++;
    foreach ($d['steps'] as $s) {
        list($no, $actor, $actorAr, $kind, $mayApprove) = $s;
        $isAcc = (strpos($actor, 'accountant') !== false) ? 1 : 0;
        $isFin = (strpos($actor, 'fin_') === 0) ? 1 : 0;
        $insS->bind_param('sisssiii', $code, $no, $actor, $actorAr, $kind, $isAcc, $isFin, $mayApprove);
        $insS->execute(); $ns++;
    }
}
echo "السلاليمُ المبنيّة: {$nl} · خطواتُها: {$ns}\n";

/* ── ② سجلُّ المقترحاتِ — عرضٌ لا إنفاذ (القادحُ لا يُمَسّ) ── */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_cap_proposals` (
  `ladder_code` VARCHAR(12) NOT NULL,
  `proposed_amount` DECIMAL(16,2) NULL COMMENT 'NULL = لا سكّانَ للاشتقاق — لا يُخترع رقم',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `pop_n` INT NOT NULL,
  `p50` DECIMAL(16,2) NULL, `p90` DECIMAL(16,2) NULL,
  `p95` DECIMAL(16,2) NULL, `p99` DECIMAL(16,2) NULL, `pmax` DECIMAL(16,2) NULL,
  `basis` VARCHAR(600) NOT NULL COMMENT 'أساسُ الاشتقاقِ كاملًا — لا رقمَ بلا مصدر',
  `measured_at` DATETIME NOT NULL,
  `applied_at` DATETIME NULL COMMENT 'يُملأ حين يعتمده المالكُ من الشاشة',
  PRIMARY KEY (`ladder_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='مقترحاتُ السقوفِ المشتقةُ آليًّا — تُعرض في شاشةِ حدودِ المبالغِ ويعتمدها المالك'");

$insP = $conn->prepare("INSERT INTO gov_cap_proposals (ladder_code, proposed_amount, currency, pop_n, p50, p90, p95, p99, pmax, basis, measured_at)
                        VALUES (?,?,'USD',?,?,?,?,?,?,?,NOW())
                        ON DUPLICATE KEY UPDATE proposed_amount=VALUES(proposed_amount), pop_n=VALUES(pop_n),
                          p50=VALUES(p50), p90=VALUES(p90), p95=VALUES(p95), p99=VALUES(p99), pmax=VALUES(pmax),
                          basis=VALUES(basis), measured_at=NOW()");
$today = date('Y-m-d');
$derived = 0; $declared = 0;
echo "\n════ اشتقاقُ السقوفِ — من سكّانٍ أحياءَ لا من تقدير ════\n";
foreach ($LAD as $code => $d) {
    $s = caps_pctl($conn, $d['pop']);
    $n = (int) ($s['n'] ?? 0);
    if ($n < CAPS_MIN_POP) {
        $basis = sprintf('لا سقفَ مشتقًّا: سكّانُ «%s» %d واقعةً — دونَ حدِّ الاشتقاق (%d واقعةً). يبقى unresolved معلَنًا ويضع المالكُ رقمَه من شاشةِ حدودِ المبالغ. (قياسُ %s)',
            $d['name'], $n, CAPS_MIN_POP, $today);
        $null = null;
        $insP->bind_param('sdiddddds', $code, $null, $n, $null, $null, $null, $null, $null, $basis);
        $insP->execute();
        echo "  ◇ {$code} · {$d['name']}: n={$n} — لا اشتقاقَ · يبقى معلَنًا unresolved\n";
        $declared++;
        continue;
    }
    $cap = floor($s['p95'] / 1000) * 1000;
    if ($cap < 1000) { $cap = round($s['p95'], -2); }
    $basis = sprintf('مشتقٌّ آليًّا %s من سكّانِ «%s»: %d واقعةً حيّةً · p50=%s · p90=%s · p95=%s · p99=%s · الأقصى=%s. السقفُ عند p95 مقرَّبًا لأسفل — يمرُّ 95%% من العملِ اليوميِّ ويُرفَع الشاذُّ إلى الدرجةِ الأعلى. يسري على الجديدِ فقط ويُعدَّل من الشاشة.',
        $today, $d['name'], $n, number_format($s['p50'], 0), number_format($s['p90'], 0),
        number_format($s['p95'], 0), number_format($s['p99'], 0), number_format($s['max'], 0));
    $insP->bind_param('sdiddddds', $code, $cap, $n, $s['p50'], $s['p90'], $s['p95'], $s['p99'], $s['max'], $basis);
    $insP->execute();
    printf("  ✔ %s · %s: n=%d · p95=%s ⇒ المقترَح %s USD\n", $code, $d['name'], $n, number_format($s['p95'], 0), number_format($cap, 0));
    $derived++;
}

/* ── ③ الإثبات ── */
$tot = (int) $conn->query("SELECT COUNT(*) c FROM gov_ladders WHERE is_active=1")->fetch_assoc()['c'];
$over = (int) $conn->query("SELECT COUNT(*) c FROM (SELECT ladder_code, COUNT(*) s FROM gov_ladder_steps GROUP BY ladder_code HAVING s > 3) t")->fetch_assoc()['c'];
$props = (int) $conn->query("SELECT COUNT(*) c FROM gov_cap_proposals")->fetch_assoc()['c'];
echo "\nالسلاليمُ النشطةُ الآن: {$tot} · يتجاوز الثلاثَ خطوات: {$over} · المقترحاتُ المسجَّلة: {$props}\n";
echo "مشتقٌّ بسكّانٍ: {$derived} · معلَنٌ بلا سكّانٍ: {$declared}\n";
if ($over > 0) { exit("✗ سلّمٌ يتجاوز الثلاثَ خطوات — يخالف قاعدةَ المالك\n"); }
echo "✔ الستةُ مبنيّةٌ بقاعدةِ الثلاثِ خطوات · وكلُّ رقمٍ بأساسِه · والقادحُ لم يُمَسّ:\n";
echo "  الإدخالُ النهائيُّ بضغطةِ المالكِ من Governance/authority_caps.php (الدور 9).\n";
