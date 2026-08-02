<?php
/**
 * tools/nav_seven_guard.php — حارس السبعة (NAV-01 §10-② · NAV-06)
 * ───────────────────────────────────────────────────────────────────────────
 * «سبعة عناصر في المجموعة حد قاطع لا توصية — وما زاد يتحول حتمًا إلى تبويب
 * أو تقرير أو عنصر داخل ملف — ولا استثناء ولا هامش».
 * يفحص كل (دور × مجموعة) ويطبع المخالفات — ويخرج 1 إن وُجدت (لأحزمة القبول).
 * التشغيل: php tools/nav_seven_guard.php [--fix-report]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');

$res = $db->query(
    "SELECT n.role_id, r.name role_name, g.name group_name, g.group_code, COUNT(*) items,
            GROUP_CONCAT(n.label_ar ORDER BY n.sort_order SEPARATOR ' · ') labels
       FROM nav_items n
       JOIN link_groups g ON g.id = n.group_id
       LEFT JOIN roles r ON r.id = n.role_id
      WHERE n.active = 1
      GROUP BY n.role_id, n.group_id
     HAVING items > 7
      ORDER BY items DESC");
$violations = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();

$total = $db->query("SELECT COUNT(DISTINCT CONCAT(role_id, ':', group_id)) c FROM nav_items WHERE active = 1 AND group_id IS NOT NULL")->fetch_assoc();
$orphans = $db->query("SELECT COUNT(*) c FROM nav_items WHERE active = 1 AND group_id IS NULL")->fetch_assoc();

echo "حارس السبعة: " . intval($total['c']) . " (دور×مجموعة) مفحوصة · بلا مجموعة: " . intval($orphans['c']) . "\n";
if (!$violations) {
    echo "✔ صفر مجموعة تتجاوز سبعة عناصر ظاهرة — القبول متحقق\n";
    exit(0);
}
echo "✘ " . count($violations) . " مخالفة (المجموعة فوق سبعة):\n";
foreach ($violations as $v) {
    echo "  · دور {$v['role_id']} ({$v['role_name']}) — {$v['group_name']} [{$v['group_code']}]: {$v['items']} عنصرًا\n";
    echo "    " . mb_substr($v['labels'], 0, 160) . "\n";
}
echo "الحكم: ما زاد يتحول تبويبًا في شاشة أم أو تقريرًا في المركز — لا يُحذف (NAV-01 §10-②)\n";
exit(1);
