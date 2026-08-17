<?php
/**
 * 2027_06_16_govauth01_partial_switch.php
 * ═══════════════════════════════════════════════════════════════════════════
 * التبديلُ الفعليُّ للمزروعِ فقط — قرارُ المالكِ 2026-08-17:
 *   ① إعادةُ بذرِ بنودِ القوالبِ المزروعةِ من الحيِّ (يمتصُّ انجرافَ ما سُجِّل
 *     بعدَ البذرِ الأول — 18 فرقًا مقيسًا كلُّها شاشاتُ GOV-AUTH الخمس).
 *   ② تفعيلُ القوالبِ المزروعةِ وحدَها draft→active — والـ143 بلا نظيرٍ تبقى
 *     مسودةً حتى قرارِ المالكِ صفًّا صفًّا.
 *   ③ القراءةُ في get_module_permissions (طرفُ الكود): المستخدمُ المغطًّى
 *     بقالبٍ نافذٍ يُحكَم بقالبِه حصرًا («لا شاشةَ خارجَ القالب») — وغيرُ
 *     المغطَّى على القائمِ كما هو. سلامةُ الفشل: أيُّ خللٍ ⇒ المسارُ القائم.
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

echo "\n▐ ① إعادةُ بذرِ المزروعِ من الحيِّ — امتصاصُ الانجراف\n";
$before = (int) $one("SELECT COUNT(*) FROM gov_profile_items");
$conn->query(
    "INSERT IGNORE INTO gov_profile_items
        (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
     SELECT 0, seeded.profile_id, 'screen', m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete,
            seeded.seeded_from
       FROM (SELECT DISTINCT profile_id, seeded_from FROM gov_profile_items
              WHERE seeded_from LIKE 'role_permissions:%') seeded
       JOIN role_permissions rp ON rp.role_id = SUBSTRING_INDEX(seeded.seeded_from, ':', -1) AND rp.can_view = 1
       JOIN modules m ON m.id = rp.module_id");
$added = (int) $one("SELECT COUNT(*) FROM gov_profile_items") - $before;
echo "   ✔ بنودٌ أُضيفت بإعادةِ البذر: {$added}\n";

echo "\n▐ ② تفعيلُ المزروعِ وحدَه\n";
$conn->query("UPDATE gov_role_profiles p SET p.state = 'active'
               WHERE p.state = 'draft'
                 AND EXISTS (SELECT 1 FROM gov_profile_items i WHERE i.profile_id = p.profile_id)");
printf("   ✔ قوالبُ نافذة: %s · مسودةٌ بانتظارِ قرارِ المالك: %s\n",
    $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='active'"),
    $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='draft'"));

printf("\n   · مستخدمون محكومون الآن بقالبِهم: %s من %s نشطًا\n",
    $one("SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
           JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
          WHERE g.revoked_at IS NULL"),
    $one("SELECT COUNT(*) FROM users WHERE status=1"));
echo "   ◆ طرفُ القراءةِ يُقلب في includes/permissions_helper.php — والفاحصُ tools/govauth_parity_probe.php شاهدُ التكافؤ\n";
echo "\n✔ التبديلُ الجزئيُّ نافذٌ على المزروع\n";
