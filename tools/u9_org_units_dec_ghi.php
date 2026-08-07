<?php
/**
 * tools/u9_org_units_dec_ghi.php — الوحداتُ الثلاثُ المعتمدة (DEC-G/H/I · update0009)
 * ═══════════════════════════════════════════════════════════════════════════
 * الشقُّ البنيويُّ من DEF-013: الوحداتُ تُنشأ في الهيكل الحي بقرارها المعتمد
 * (docs/update0009/DECISIONS_ADOPTED_ar.md)، ودوراتُ عملها وشاشاتُها تدخل عبر
 * مسارِ التغيير الرسمي RL-05 — «لا تُخترع دورةٌ ولا شاشة».
 *   DEC-G  إدارةُ المخاطر — مركزيةٌ مستقلةٌ رقابيةٌ بوصولٍ مباشرٍ للرئيس في الجوهرية
 *   DEC-H  المشترياتُ المركزية — الشراءُ الاستراتيجيُّ والإطاريُّ وما فوقَ سقفِ التشغيلية
 *   DEC-I  شؤونُ العاملين بالمشروعات — العقودُ المشروعيةُ والتعبئةُ والتسريح
 * idempotent (unit_code فريد وظيفيًّا) · dry-run افتراضيًّا.
 *
 * php tools/u9_org_units_dec_ghi.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* [unit_code, name_ar, layer, owner_doc] — مركزياتٌ فلا أبَ لها (كأخواتها) */
$UNITS = array(
    array('risk_mgmt', 'إدارة المخاطر', 'oversight',
        'INJAZ-CORE-01 §3-2 بند 11 · DEC-G معتمد 2026-08-06 — مستقلةٌ بوصولٍ مباشرٍ للرئيس في الجوهرية'),
    array('procurement_central', 'إدارة المشتريات المركزية', 'parallel',
        'INJAZ-CORE-01 §3-2 بند 8 · DEC-H معتمد 2026-08-06 — الاستراتيجيُّ والإطاريُّ وما فوقَ سقفِ التشغيلية'),
    array('project_workforce', 'شؤون العاملين بالمشروعات', 'parallel',
        'INJAZ-CORE-01 §3-2 بند 10 · DEC-I معتمد 2026-08-06 — العقودُ المشروعيةُ والتعبئةُ والتسريح'),
);

$o('══ DEC-G/H/I — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');
foreach ($UNITS as $u) {
    list($code, $name, $layer, $doc) = $u;
    $st = $conn->prepare("SELECT unit_id, active FROM org_units WHERE unit_code = ?");
    $st->bind_param('s', $code);
    $st->execute();
    $x = $st->get_result()->fetch_assoc();
    $st->close();
    if ($x) { $o("= «{$name}» قائمة (#{$x['unit_id']} active={$x['active']})"); continue; }
    $o("+ «{$name}» ($code · $layer · بلا أب — مركزية)");
    if ($APPLY) {
        $st = $conn->prepare("INSERT INTO org_units (company_id, unit_code, name_ar, layer, parent_unit_id, owner_doc, active)
                              VALUES (1, ?, ?, ?, NULL, ?, 1)");
        $st->bind_param('ssss', $code, $name, $layer, $doc);
        $st->execute() or die($st->error . "\n");
        $o('  → #' . $conn->insert_id);
        $st->close();
    }
}
if ($APPLY) {
    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM org_units WHERE active = 1");
    $o('الشاهد: وحداتٌ نشطة = ' . mysqli_fetch_assoc($r)['c'] . ' (15 قائمة + 3 معتمدة = 18)');
}
