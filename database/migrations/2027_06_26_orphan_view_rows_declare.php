<?php
/**
 * 2027_06_26_orphan_view_rows_declare.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ إصلاحُ كسرٍ صنعتْه هذه الحملةُ نفسُها — بشهادةِ المراجعةِ العكسية:
 *   إعادةُ تفعيلِ الروابطِ اليتيمةِ (هجرة 2027_06_20) أظهرت اثنتي عشرةَ شاشةً
 *   في سايدبارِ إداراتٍ ليست مالكتَها، **بلا صفِّ عرضٍ معلَنٍ** في
 *   `screen_view_rows` — فرسب الفحصُ الحاكمُ ⑪ في `tools/act_checks.php`
 *   (كان صفرًا قبلَ الحملةِ — 12 من 12 من صنعِ العمل).
 *
 * ◆ والعلاجُ إعلانٌ من المصدرِ الحاكمِ لا تخمين: ورقةُ «الروابط اليتيمة» في
 *   الدفترِ تحمل لكلِّ صفٍّ **تصنيفَه الوظيفيَّ الحيَّ** و«المجموعةَ المقترحة»
 *   — وهي بعينِها ما بُني عليه الإسناد. فيُكتب صفُّ عرضٍ من نصِّها:
 *   الإدارةُ مُطَّلعةٌ (`viewer`) على الشاشةِ بنطاقِ إدارتِها لا مالكةً لها.
 *
 * ◆ ولا يُمسُّ مالكٌ قائمٌ ولا صلاحيةٌ نافذة: الصفوفُ إعلانُ رؤيةٍ حوكميّ،
 *   والصلاحيةُ الفعليةُ تبقى كما هي في `role_permissions`.
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

/* الاثنتا عشرةَ كما رصدها الفحصُ ⑪ حرفًا: route @ dept */
$DECLARE = array(
    array('Finance/cost_report_fin.php',        'إدارة التشغيل'),
    array('Equipments/equipments_types.php',    'إدارة التشغيل'),
    array('ActivityLogs/activity_logs.php',     'إدارة التشغيل'),
    array('Contracts/claims.php',               'إدارة التشغيل'),
    array('Contracts/penalties.php',            'إدارة التشغيل'),
    array('Finance/dues_fin.php',               'الموارد البشرية'),
    array('Workforce/contract_registry.php',    'الموارد البشرية'),
    array('Procurement/receipt_custody_proc.php','المشتريات'),
    array('Procurement/master_data_proc.php',   'المشتريات'),
    array('Finance/cost_report_fin.php',        'النقل والترحيل'),
    array('Suppliers/supplier_capacity.php',    'المالية والخزينة'),
    array('Suppliers/supplier_evaluation.php',  'المالية والخزينة'),
);
echo "\n▐ إعلانُ صفوفِ العرضِ للاثنتي عشرة\n";
$ins = $conn->prepare(
    "INSERT INTO screen_view_rows
        (screen_name, canonical_file, route, dept, role_id, role_kind, scope_text, angle,
         columns_text, filters_text, allowed_text, blocked_text, nav_group, active)
     SELECT ?, ?, ?, ?, COALESCE((SELECT id FROM roles WHERE name = ? AND status = 1 LIMIT 1), 0),
            'viewer', 'نطاقُ إدارتِه', 'اطّلاعٌ من مجالِ عملِه اليوميّ',
            '', '', 'قراءة', 'ما يخصُّ غيرَ إدارتِه', '', 1
      WHERE NOT EXISTS (SELECT 1 FROM screen_view_rows s WHERE s.route = ? AND s.dept = ? AND s.active = 1)");
$n = 0;
foreach ($DECLARE as $d) {
    list($route, $dept) = $d;
    $name = (string) $one("SELECT name FROM modules WHERE code = '" . $conn->real_escape_string($route) . "' LIMIT 1");
    if ($name === '') { $name = basename($route, '.php'); }
    $canon = basename($route);
    $ins->bind_param('sssssss', $name, $canon, $route, $dept, $dept, $route, $dept);
    if ($ins->execute() && $conn->affected_rows > 0) { $n++; }
    else if ($conn->errno) { echo "   ✗ {$route} @ {$dept}: {$conn->error}\n"; }
}
$ins->close();
printf("   ✔ أُعلن: %d صفَّ عرضٍ (viewer — قراءةٌ بنطاقِ الإدارة)\n", $n);
printf("   · إجماليُّ صفوفِ العرضِ النشطة: %s\n", $one("SELECT COUNT(*) FROM screen_view_rows WHERE active = 1"));
echo "\n◆ الشاهدُ: أعد تشغيلَ tools/act_checks.php — الفحصُ ⑪ يجب أن يعود صفرًا\n";
