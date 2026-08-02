<?php
/**
 * tools/nav_seven_guard.php — حارسُ المعيار الخماسي (NAV-01 v6 §4 · update0007 N-01)
 * ───────────────────────────────────────────────────────────────────────────
 * «الثماني نموذجٌ استرشاديٌّ لا فرض — والمعيارُ الحاكمُ خمسةٌ لا عدد» — نسخ
 * v6 حدَّ السبعة القاطع؛ فالحارسُ يفحص:
 *   ① صفرُ رابطٍ بلا مجموعة (القائمةُ المسطَّحةُ ممنوعة) — **حاكم**.
 *   ② لكل دورٍ لوحةٌ أولى (المجموعةُ الأولى تعرف من أين يبدأ) — **حاكم**.
 *   ③ التكدّسُ الشديد (> 12 عنصرًا ظاهرًا) — **تنبيهٌ يُدار بأدوات التعقيد
 *     الست** (منسدلة · فرعية · تبويبات · إجراءات سجل · تقارير · إظهار بالدور)
 *     — لا منع، فالأوراقُ المستهدفةُ نفسُها تتجاوز السبعة بقرار.
 * يخرج 1 عند مخالفةٍ حاكمةٍ فقط. التشغيل: php tools/nav_seven_guard.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');

$fail = 0;

/* ① القائمةُ المسطَّحة */
$orphans = (int) $db->query("SELECT COUNT(*) c FROM nav_items WHERE active = 1 AND group_id IS NULL")->fetch_assoc()['c'];
$total   = (int) $db->query("SELECT COUNT(DISTINCT CONCAT(role_id, ':', group_id)) c FROM nav_items WHERE active = 1 AND group_id IS NOT NULL")->fetch_assoc()['c'];
echo "حارس المعيار: $total (دور×مجموعة) مفحوصة\n";
if ($orphans > 0) { echo "✘ ① روابطُ بلا مجموعة (قائمةٌ مسطَّحة): $orphans\n"; $fail++; }
else echo "✔ ① صفرُ رابطٍ بلا مجموعة\n";

/* ② لوحةُ البداية لكل دورٍ نشط */
$res = $db->query(
    "SELECT DISTINCT n.role_id FROM nav_items n WHERE n.active = 1
      AND n.role_id NOT IN (
        SELECT DISTINCT n2.role_id FROM nav_items n2
          JOIN link_groups g2 ON g2.id = n2.group_id
         WHERE n2.active = 1 AND g2.display_order = 1)");
$noHome = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
if ($noHome) { echo "✘ ② أدوارٌ بلا مجموعةٍ أولى (لا يُعرف من أين تبدأ): " . count($noHome) . "\n"; $fail++; }
else echo "✔ ② كلُّ دورٍ يبدأ من لوحته\n";

/* ③ التكدّسُ الشديد — تنبيهٌ لا منع (أدواتُ التعقيد الست) */
$res = $db->query(
    "SELECT n.role_id, r.name role_name, g.name group_name, COUNT(*) items
       FROM nav_items n
       JOIN link_groups g ON g.id = n.group_id
       LEFT JOIN roles r ON r.id = n.role_id
      WHERE n.active = 1
      GROUP BY n.role_id, n.group_id
     HAVING items > 12
      ORDER BY items DESC");
$dense = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
if ($dense) {
    echo "⚠ ③ تكدّسٌ شديدٌ (> 12) يُدار بأدوات التعقيد الست — " . count($dense) . " مجموعة:\n";
    foreach ($dense as $v)
        echo "   · دور {$v['role_id']} ({$v['role_name']}) — {$v['group_name']}: {$v['items']} عنصرًا\n";
} else echo "✔ ③ لا تكدّسَ شديدًا\n";

echo $fail === 0 ? "الحكم: ✔ المعيارُ الحاكمُ متحقق\n"
                 : "الحكم: ✘ $fail مخالفةً حاكمة\n";
exit($fail > 0 ? 1 : 0);
