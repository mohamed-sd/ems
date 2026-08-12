<?php
/**
 * 2027_02_06 — رمزُ الصلاحيةِ لعناصرِ التنقُّلِ: اشتقاقٌ لا اختراع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ 95 عنصرًا في `nav_items` يحمل `module_id` و**لا يحمل `permission_code`**.
 *   وعنصرٌ بلا رمزِ صلاحيةٍ لا يُرشَّح بمنحةٍ — فإمّا يُعرض لمن لا يملكه وإمّا
 *   يُحجب عمّن يملكه، والاثنان عيب.
 *
 * ◆ **والقاعدةُ مقيسةٌ لا مُختارة**: `permission_code = modules.code` في
 *   **1517 صفًّا مطابقًا وصفرِ صفٍّ مختلف**. فالإسنادُ اشتقاقٌ حتميٌّ من
 *   الموديولِ نفسِه — لا قيمةَ تُخترع ولا اجتهادَ في أيٍّ منها.
 *
 * ◆ ولا يُلمَس صفٌّ يحمل رمزًا: الشرطُ `permission_code IS NULL OR = ''` حصرًا.
 * ◆ وما لا موديولَ له يبقى بلا رمز — وهو الصواب: الثوابتُ (الرئيسيةُ ونحوها)
 *   لا تُرشَّح بمنحة، والفاحصُ نفسُه يستثنيها نصًّا («والثوابتُ وحدَها بلا فحص»).
 * ◆ مُتحمِّلٌ للتكرار · ويُقاس الباقي بعد الإسناد.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ رمزُ الصلاحيةِ لعناصرِ التنقُّل ══\n";

$before = (int) $db->query("SELECT COUNT(*) FROM nav_items
                             WHERE COALESCE(module_id,0) > 0
                               AND (permission_code IS NULL OR permission_code = '')")->fetch_row()[0];
echo "  بلا رمزٍ ولها موديول: {$before}\n";

/* التحقّقُ من القاعدةِ **قبل** تطبيقِها — لا تُشتقُّ قيمةٌ على فرضية */
$chk = $db->query("SELECT SUM(n.permission_code = m.code) same, SUM(n.permission_code <> m.code) diff
                     FROM nav_items n JOIN modules m ON m.id = n.module_id
                    WHERE n.permission_code IS NOT NULL AND n.permission_code <> ''")->fetch_assoc();
echo '  القاعدةُ المقيسة: مطابقٌ ' . (int) $chk['same'] . ' · مختلفٌ ' . (int) $chk['diff'] . "\n";
if ((int) $chk['diff'] > 0) {
    fwrite(STDERR, "✘ القاعدةُ ليست حتمية — لا يُشتقُّ رمزٌ على قاعدةٍ لها استثناءات\n");
    exit(1);
}

if ($before > 0) {
    if ($db->query("UPDATE nav_items n JOIN modules m ON m.id = n.module_id
                       SET n.permission_code = m.code
                     WHERE (n.permission_code IS NULL OR n.permission_code = '')
                       AND COALESCE(m.code,'') <> ''") === false) {
        fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
    }
    echo '  ✔ أُسند الرمزُ لـ' . $db->affected_rows . " عنصرًا من رمزِ موديولِه\n";
} else { echo "  ○ لا عنصرَ يحتاج إسنادًا\n"; }

$after = (int) $db->query("SELECT COUNT(*) FROM nav_items
                            WHERE COALESCE(module_id,0) > 0
                              AND (permission_code IS NULL OR permission_code = '')")->fetch_row()[0];
echo "  الباقي بلا رمزٍ وله موديول: {$after}\n";
if ($after > 0) {
    $r = $db->query("SELECT n.id, n.route, n.module_id, m.code FROM nav_items n
                      LEFT JOIN modules m ON m.id = n.module_id
                     WHERE COALESCE(n.module_id,0) > 0
                       AND (n.permission_code IS NULL OR n.permission_code = '') LIMIT 10");
    while ($x = $r->fetch_assoc()) {
        echo '    ⚠ #' . $x['id'] . ' ' . $x['route'] . ' → موديول ' . $x['module_id']
           . ' رمزُه «' . (string) $x['code'] . "»\n";
    }
}

/* ما لا موديولَ له — يُعدُّ ولا يُلمَس (ثوابتُ القائمة) */
$consts = (int) $db->query("SELECT COUNT(*) FROM nav_items
                            WHERE COALESCE(module_id,0) = 0")->fetch_row()[0];
echo "  ثوابتٌ بلا موديولٍ (تبقى بلا رمزٍ بحق): {$consts}\n";

echo "\n" . ($after === 0
    ? "✅ كلُّ عنصرٍ له موديولٌ يحمل رمزَ صلاحيتِه.\n"
    : "⚠ بقي {$after} — يُعلَن\n");
exit($after === 0 ? 0 : 1);
