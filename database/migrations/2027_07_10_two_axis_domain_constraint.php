<?php
/**
 * 2027_07_10_two_axis_domain_constraint.php — حصرُ التطبيقِ المؤقَّتِ **بقيدٍ** لا بقاعدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · رابعًا): «حارسٌ يمنع استعمالَ التطبيقِ المؤقَّتِ
 *   في الصلاحياتِ والسلاليمِ والسقوفِ وفصلِ الواجباتِ والالتزاماتِ القانونيةِ
 *   والقراراتِ المالية — **قيدًا لا قاعدةً مكتوبة**».
 *
 * ◆ وما كان قبلَ اليوم غيرُ كافٍ: الحصرُ كان معلَّقًا على عَلَمٍ بشريٍّ
 *   (`provisional_reversible`) يضبطه من يكتب الصفَّ — فهو **إقرارٌ** لا قيد.
 *   والقيدُ الحقيقيُّ يشتقُّ المنعَ من **مجالِ الصفِّ نفسِه**: صفٌّ مجالُه
 *   الصلاحياتُ لا يقبل تطبيقًا مؤقَّتًا مهما ضُبط عَلَمُه.
 *
 * ◆ فيُضاف `policy_domain` بستةِ مجالاتٍ محظورةٍ صراحةً + مجالِ التنقلِ المسموح،
 *   ويُشتقُّ ابتداءً من مسارِ الصفِّ ونوعِه (لا يُترك للكاتب)، ثم يحرسه قادحٌ
 *   يرفض التطبيقَ المؤقَّتَ في المحظورِ **وإن كان العلمُ مرفوعًا**.
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
$has = function ($t, $c) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                         AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'");
    return $r && $r->num_rows > 0;
};

if (!$has('nav_canonical', 'policy_domain')) {
    $conn->query("ALTER TABLE nav_canonical
        ADD `policy_domain` ENUM(
            'NAVIGATION_NAMING_POSITION',
            'PERMISSIONS','APPROVAL_LADDERS','FINANCIAL_CAPS',
            'SEGREGATION_OF_DUTIES','LEGAL_OBLIGATIONS','FINANCIAL_DECISIONS'
        ) NOT NULL DEFAULT 'NAVIGATION_NAMING_POSITION'
        COMMENT 'مجالُ الصفّ — والتطبيقُ المؤقَّتُ ممنوعٌ بنيويًّا في الستةِ المحظورة'
        AFTER `provisional_reversible`");
}

/* ── الاشتقاقُ من المسارِ والطبيعةِ لا من إقرارِ الكاتب ── */
$RULES = array(
    'PERMISSIONS'            => array('auth_grants', 'auth_profiles', 'permissions', 'role_perm', 'visibility_guard', 'guard_classification'),
    'APPROVAL_LADDERS'       => array('ladder', 'approval', 'approvals_inbox', 'hours_approval'),
    'FINANCIAL_CAPS'         => array('authority_caps', 'caps', 'limits', 'ctrl_authority'),
    'SEGREGATION_OF_DUTIES'  => array('sod', 'segregation', 'auditor_detach'),
    'LEGAL_OBLIGATIONS'      => array('obl_register', 'obligation', 'compliance', 'legal', 'permit'),
    'FINANCIAL_DECISIONS'    => array('journal', 'payment', 'settlement', 'invoice', 'treasury', 'budget', 'fin_request'),
);
$applied = array();
foreach ($RULES as $domain => $needles) {
    $conds = array();
    foreach ($needles as $n) { $conds[] = "LOWER(route) LIKE '%" . $conn->real_escape_string($n) . "%'"; }
    $sql = "UPDATE nav_canonical SET policy_domain = '{$domain}'
             WHERE policy_domain = 'NAVIGATION_NAMING_POSITION' AND (" . implode(' OR ', $conds) . ")";
    $conn->query($sql);
    $applied[$domain] = $conn->affected_rows;
}
/* وما كان في مجالٍ محظورٍ لا يصلح للتطبيقِ المؤقَّتِ — يُصفَّر عَلَمُه اتساقًا */
$conn->query("UPDATE nav_canonical SET provisional_reversible = 0
               WHERE policy_domain <> 'NAVIGATION_NAMING_POSITION'");

/* ── القادحُ: القيدُ يشتقُّ المنعَ من المجالِ لا من العَلَم ── */
$conn->query("DROP TRIGGER IF EXISTS `trg_nav_provisional_scope`");
$ok = $conn->query("CREATE TRIGGER `trg_nav_provisional_scope`
    BEFORE UPDATE ON `nav_canonical` FOR EACH ROW
    BEGIN
        IF NEW.application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION'
           AND NEW.policy_domain <> 'NAVIGATION_NAMING_POSITION' THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'التطبيقُ المؤقَّتُ ممنوعٌ في هذا المجال: الصلاحياتُ · سلاليمُ الاعتمادِ · السقوفُ الماليةُ · فصلُ الواجباتِ · الالتزاماتُ القانونيةُ · القراراتُ المالية';
        END IF;
        IF NEW.application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION'
           AND NEW.provisional_reversible <> 1 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'التطبيقُ المؤقَّتُ محصورٌ فيما يُعكس — وهذا الصفُّ غيرُ معلَنٍ قابلًا للعكس';
        END IF;
        IF NEW.decision_state IN ('APPROVED','REJECTED')
           AND COALESCE(OLD.decision_state,'') <> NEW.decision_state
           AND NEW.decided_by IS NULL
           AND (NEW.decision_source IS NULL OR NEW.decision_source = '') THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'حسمٌ بلا فاعلٍ ولا مصدرٍ حاكمٍ مرفوض — الصمتُ لا يُرقِّي قرارًا';
        END IF;
    END");
if (!$ok) { exit("✗ القادح: {$conn->error}\n"); }

echo "════ حصرُ المجالِ بقيدٍ ════\n";
foreach ($applied as $d => $n) { if ($n > 0) { printf("  %-24s %d صفًّا\n", $d, $n); } }
$r = $conn->query("SELECT policy_domain, COUNT(*) n FROM nav_canonical GROUP BY policy_domain ORDER BY n DESC");
echo "\n▐ التوزيعُ النهائيّ\n";
while ($x = $r->fetch_assoc()) { printf("  %-30s %s\n", $x['policy_domain'], $x['n']); }
$prot = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE policy_domain <> 'NAVIGATION_NAMING_POSITION'")->fetch_assoc()['c'];
echo "\nصفوفٌ محميةٌ من التطبيقِ المؤقَّت: {$prot}\n";
echo "✔ القيدُ في القادحِ يشتقُّ المنعَ من المجالِ — لا من عَلَمٍ يضبطه الكاتب\n";
