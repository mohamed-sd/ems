<?php
/**
 * tools/e05_checks.php — حزامُ محرّك الهوية والكيانات (E-05) · v1
 * ───────────────────────────────────────────────────────────────────────────
 * ① EN-02 · AC-E05-02: لا حسابَ نشطٌ بلا شخصٍ خلفه (عبر جسر الهوية).
 * ② EN-03 · AC-E05-03: كلُّ جدولِ **بياناتِ مستأجرٍ** يحمل عمود الكيان —
 *    والمرجعيُّ المشترك مستثنًى بقائمةٍ معلَنةٍ لا بالصمت (قرار المالك
 *    2026-08-06: فاحصٌ يصنّف ويُبلّغ · بلا تعديل مخطط والتجميدُ مفعَّل).
 * ③ EN-04 · AC-E05-04: مجموعُ حصص كلِّ كيانٍ موسومٍ «كامل» = 100٪ بالضبط.
 * ④ EN-01: لا شخصَ بمعرّفٍ مكرر.
 *
 * php tools/e05_checks.php [--enforce] [--tables]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ENFORCE = in_array('--enforce', $argv, true);
$LIST = in_array('--tables', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s); };

/**
 * جداولٌ لا تحتاج عمودَ الكيان — مرجعيةٌ للمنصة كلِّها أو بنيةُ نظام.
 * كلُّ ما ليس هنا وليس فيه العمودُ = دَينُ عزلٍ يُعلَن ويُقاس.
 */
$SHARED_PREFIX = array(
    'nav_', 'cmp03_', 'sod_', 'hr_dictionaries', 'permission_template', 'template_permissions',
    'ems_event_', 'ems_processed_', 'admin_', 'super_admin', 'migrations', 'schema_',
);
$SHARED_EXACT = array(
    'roles', 'modules', 'actions', 'role_permissions', 'link_groups', 'job_titles',
    'org_units', 'org_assignment_types', 'nav09_file_map', 'nav09_action_map',
    'permission_templates', 'guard_override_policies', 'sensitive_field_policies',
    'currencies', 'units_of_measure', 'failure_codes', 'doc_types', 'countries',
);
$isShared = function ($t) use ($SHARED_PREFIX, $SHARED_EXACT) {
    if (in_array($t, $SHARED_EXACT, true)) { return true; }
    foreach ($SHARED_PREFIX as $p) { if (strpos($t, $p) === 0) { return true; } }
    return false;
};

$o("════ فحوص E-05 — الهوية والكيانات ════\n");
$fail = 0;

/* ① الحساب بلا شخص */
$r = mysqli_query($conn,
    "SELECT COUNT(*) n FROM users u WHERE u.status='active' AND u.role <> '-1'
       AND NOT EXISTS (SELECT 1 FROM person_positions p WHERE p.p_id = u.position_id AND p.state='active')");
$unbridged = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : -1;
$t = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) n FROM users WHERE status='active' AND role <> '-1'"));
$ok1 = ($unbridged === 0);
if (!$ok1) { $fail++; }
$o(($ok1 ? '✔' : '✘') . " ① حساباتٌ نشطةٌ بلا جسرِ هوية : {$unbridged} من {$t['n']}\n");

/* ② الكيان في كل صف */
$missing = array();
$r = mysqli_query($conn,
    "SELECT t.TABLE_NAME nm FROM information_schema.TABLES t
      WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
        AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                         WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = t.TABLE_NAME
                           AND c.COLUMN_NAME = 'company_id')
      ORDER BY t.TABLE_NAME");
$sharedN = 0;
while ($x = mysqli_fetch_assoc($r)) {
    if ($isShared($x['nm'])) { $sharedN++; continue; }
    $rr = @mysqli_query($conn, "SELECT COUNT(*) n FROM `{$x['nm']}`");
    $rows = $rr ? (int) mysqli_fetch_assoc($rr)['n'] : 0;
    $missing[$x['nm']] = $rows;
}
$debt = count($missing);
$ok2 = ($debt === 0);
if (!$ok2) { $fail++; }
$o(($ok2 ? '✔' : '⚠') . " ② جداولُ مستأجرٍ بلا عمود الكيان : {$debt}"
   . "   (مرجعيٌّ مشتركٌ مستثنًى: {$sharedN})\n");
if ($LIST && $missing) {
    arsort($missing);
    foreach ($missing as $nm => $rows) { $o(sprintf("      %-40s %d صفًّا\n", $nm, $rows)); }
}

/* ③ الملكية مئةٌ بالضبط — على الموسوم «كامل» وحدَه */
$bad = array();
$r = mysqli_query($conn,
    "SELECT e.entity_id, e.legal_name, ROUND(SUM(o.percent),2) s
       FROM legal_entities e
       JOIN entity_ownership o ON o.owned_entity_id = e.entity_id
        AND (o.valid_to IS NULL OR o.valid_to >= CURDATE())
      WHERE e.ownership_completeness = 'full'
      GROUP BY e.entity_id, e.legal_name
     HAVING ABS(SUM(o.percent) - 100) > 0.005");
while ($r && ($x = mysqli_fetch_assoc($r))) { $bad[] = $x; }
$ok3 = (count($bad) === 0);
if (!$ok3) { $fail++; }
$o(($ok3 ? '✔' : '✘') . " ③ كياناتٌ «كاملة» لا تبلغ 100٪ : " . count($bad) . "\n");
foreach ($bad as $x) { $o(sprintf("      #%s %s → Σ=%s%%\n", $x['entity_id'], mb_substr($x['legal_name'], 0, 34), $x['s'])); }

/* ④ لا شخصَ بمعرّفٍ مكرر */
$r = mysqli_query($conn,
    "SELECT COUNT(*) n FROM (SELECT national_ref FROM persons
       WHERE national_ref IS NOT NULL AND national_ref <> ''
       GROUP BY national_ref HAVING COUNT(*) > 1) z");
$dup = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : -1;
$ok4 = ($dup === 0);
if (!$ok4) { $fail++; }
$o(($ok4 ? '✔' : '✘') . " ④ معرّفاتُ أشخاصٍ مكررة : {$dup}\n");

$o(str_repeat('─', 54) . "\n");
// دَينُ العزل (②) يُعلَن ولا يمنع الدمج — إزالتُه تحتاج تعديلَ مخطط والتجميدُ مفعَّل
$blocking = $fail - ($ok2 ? 0 : 1);
if ($fail === 0) { $o("الحكم: ✔ صفرٌ في الحاكمة\n"); exit(0); }
if ($blocking === 0) {
    $o("الحكم: ✔ صفرٌ في الحاكمة — و② دَينُ عزلٍ معلَنٌ لا يحجب (فاحصٌ يُبلّغ)\n");
    exit(0);
}
$o("الحكم: ✘ {$blocking} خرقًا حاكمًا" . ($ENFORCE ? ' — الدمجُ ممنوع' : '') . "\n");
exit($ENFORCE ? 1 : 0);
