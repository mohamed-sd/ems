<?php
/**
 * 2027_02_12 — تفعيلُ صفَّي تنقُّلٍ ممنوحَين لدورِ التمويل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيس**: `nav_items.active = 0` في **318 صفًّا**، و**275 منها موديولُه
 *   ممنوحٌ `can_view` لدورِه** — أي شاشةٌ مسموحةٌ بلا بابٍ في القائمة. وقاعدةُ
 *   النظامِ المُعلَنة: «التبعيةُ تحدد القائمةَ **والصلاحيةُ ترشّح**» — فالمنحةُ
 *   هي المُرشِّح، و`active` مقبضٌ إداريٌّ لا حكمُ صلاحيةٍ ثانٍ.
 *
 * ◆ **ولا يُفعَّل الـ275 جملةً هنا**: أثرُه يُرى في قائمةِ كلِّ مستخدم، وبعضُه
 *   قد يكون إخفاءً مقصودًا بيدِ إداريٍّ (`admin/permissions/nav_items.php`).
 *   فذاك قرارُ مالكٍ يُسأل عنه صريحًا، لا يُمرَّر في هجرة.
 *
 * ◆ **ويُفعَّل هنا ما يسمّيه فاحصٌ صراحةً وحدَه** — شاشتان لدورِ التمويل (26):
 *     `Financing/financing_operation_new.php` (211) — `fin26_role_test`:
 *        «FIN: إنشاء عملية تمويل» و«بالمنحة: باب FIN يُصيَّر بشاشتيه»
 *     `Reports/approval_lag_report.php` (212) — «REP: مؤشر 212»
 *   وكلتاهما **ممنوحةٌ** لدورِه، وإحداهما فعلُه الأساسيُّ نفسُه: دورُ التمويلِ
 *   بلا «إنشاءِ عمليةِ تمويل» دورٌ بلا عمل.
 *
 * ◆ ولا يُلمَس صفٌّ بلا منحة: الشرطُ يفحص `role_permissions.can_view` قبلَ كلِّ
 *   تفعيل — فلا يُعرض بابٌ لمن لا يملكه.
 * ◆ مُتحمِّلٌ للتكرار.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ROLE = 26;
$ROUTES = array(
    'Financing/financing_operation_new.php',
    'Reports/approval_lag_report.php',
);

echo "══ تفعيلُ صفوفٍ ممنوحةٍ للدور {$ROLE} ══\n";
$on = 0; $skipped = array();
foreach ($ROUTES as $rt) {
    $st = $db->prepare("SELECT n.id, n.module_id,
                          EXISTS(SELECT 1 FROM role_permissions p
                                  WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                    AND COALESCE(p.can_view,0) = 1) g
                          FROM nav_items n WHERE n.role_id = ? AND n.route = ?");
    $st->bind_param('is', $ROLE, $rt);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $skipped[] = $rt . ' (لا صفَّ)'; continue; }
    if ((int) $row['g'] !== 1) { $skipped[] = $rt . ' (**بلا منحةِ عرض** — لا يُفعَّل)'; continue; }
    $db->query('UPDATE nav_items SET active = 1 WHERE id = ' . (int) $row['id'] . ' AND active = 0');
    if ($db->affected_rows > 0) { $on++; echo "  ✔ {$rt} (موديول {$row['module_id']})\n"; }
    else { echo "  ○ {$rt} نشطٌ سلفًا\n"; }
}
foreach ($skipped as $s) { echo "  ⚠ {$s}\n"; }

echo "\n── الباقي: ديْنٌ مُعلَنٌ بانتظارِ قرارِ مالك\n";
$rest = (int) $db->query("SELECT COUNT(*) FROM nav_items n
                           WHERE n.active = 0 AND COALESCE(n.module_id,0) > 0
                             AND EXISTS(SELECT 1 FROM role_permissions p
                                         WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                           AND COALESCE(p.can_view,0) = 1)")->fetch_row()[0];
echo "  صفوفٌ غيرُ نشطةٍ وموديولُها **ممنوحٌ**: {$rest}\n";
echo "  ⇒ شاشةٌ مسموحةٌ بلا بابٍ في القائمة. تفعيلُها جملةً يُرى في قائمةِ كلِّ\n";
echo "     مستخدمٍ — فهو قرارُ مالكٍ لا قرارُ مُرحِّل.\n";
$r = $db->query("SELECT n.role_id, COUNT(*) c FROM nav_items n
                  WHERE n.active = 0 AND COALESCE(n.module_id,0) > 0
                    AND EXISTS(SELECT 1 FROM role_permissions p
                                WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                  AND COALESCE(p.can_view,0) = 1)
                  GROUP BY n.role_id ORDER BY c DESC LIMIT 8");
while ($x = $r->fetch_assoc()) { echo '      دور ' . str_pad($x['role_id'], 5) . $x['c'] . " صفًّا\n"; }

echo "\n✅ فُعِّل {$on} — وما بقي مُعلَنٌ لا مُمرَّر.\n";
exit(0);
