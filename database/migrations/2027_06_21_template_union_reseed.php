<?php
/**
 * 2027_06_21_template_union_reseed.php
 * ═══════════════════════════════════════════════════════════════════════════
 * القاعدة: **القالبُ لا يُنقص حاملَه الجديدَ شيئًا كان يملكه** — فبنودُ قوالبِ
 * الدرجاتِ الماليةِ تُوسَّع اتحادًا من منحِ الأدوارِ الملحقةِ حديثًا نفسِها
 * (17→G7 · 20→G6 · 21→G3 · 34→G3 · 35→G3) كما بُذر G2 من 22.
 * الخسائرُ تزول بنيويًّا (القالبُ ⊇ الحيِّ لكلِّ ملحق) — ومكاسبُ التوحيدِ
 * (ما يملكه نظيرُ الدرجةِ ولا يملكه الدورُ) تبقى وتُعلن في تقريرِ الفروق.
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

$PAIRS = array(array(17, 'G7'), array(20, 'G6'), array(21, 'G3'), array(34, 'G3'), array(35, 'G3'));
$total = 0;
foreach ($PAIRS as $pr) {
    list($rid, $grade) = $pr;
    $pid = (int) $one("SELECT profile_id FROM gov_role_profiles
                        WHERE dept_code = 'المالية والخزينة' AND grade = '{$grade}' AND version = 1");
    if ($pid <= 0) { echo "   ⚠ لا قالبَ لـG{$grade}\n"; continue; }
    $conn->query(
        "INSERT IGNORE INTO gov_profile_items
            (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
         SELECT 0, {$pid}, 'screen', m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete,
                CONCAT('role_permissions:', {$rid})
           FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
          WHERE rp.role_id = {$rid} AND rp.can_view = 1");
    printf("   ✔ %s ∪ منحُ الدور %d: +%d بندًا\n", $grade, $rid, $conn->affected_rows);
    $total += $conn->affected_rows;
}
printf("✔ اتحادُ البنود: +%d — والخسائرُ المقيسةُ يجب أن تكون صفرًا الآن\n", $total);
$loss = (int) $one(
    "SELECT COUNT(*) FROM role_permissions rp
       JOIN modules m ON m.id = rp.module_id
       JOIN (SELECT 17 rid, 'G7' g UNION SELECT 20,'G6' UNION SELECT 21,'G3'
             UNION SELECT 22,'G2' UNION SELECT 34,'G3' UNION SELECT 35,'G3') map ON map.rid = rp.role_id
       JOIN gov_role_profiles p ON p.dept_code = 'المالية والخزينة' AND p.grade = map.g AND p.version = 1
      WHERE rp.can_view = 1
        AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                         WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                           AND i.item_ref = m.code AND i.allow = 1)");
printf($loss === 0 ? "✔ صفرُ خسارةٍ: القالبُ ⊇ الحيِّ لكلِّ ملحق\n" : "✗ خسائرُ باقية: %d!\n", $loss);
