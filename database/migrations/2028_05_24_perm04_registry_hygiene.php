<?php
/**
 * 2028_05_24_perm04_registry_hygiene.php — نظافةُ السجلّاتِ الحاكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أشقٍّ كشفها الجردُ، وكلُّها **تلوّثُ سجلًّا يقرؤه حارسٌ حيّ**:
 *
 * ① **صفٌّ دخيلٌ في سجلِّ أنواعِ الاعتماد**: `FIN_-000` حقلُ أدوارِه **نصٌّ حرٌّ**
 *    («أُثبت من واقع التشغيل الميداني · UAT-2026») لا أرقامُ أدوار. وبوّابةُ
 *    الاعتمادِ تقرأ `allowed_roles` وتفكّكه بالفواصل — فصفٌّ كهذا يُنتج قائمةَ
 *    أدوارٍ من كلماتٍ عربيّة، ولا يطابقها دورٌ أبدًا. بقيّةُ استيرادٍ لا نوعُ
 *    اعتماد.
 * ② **ثمانيةُ صفوفٍ دخيلةٍ في سجلِّ تعارضِ الاعتماد**: رموزُ طرفَيها كلماتٌ
 *    عربيّةٌ مبتورةٌ («أُثبت من» · «بناءً عل»). و`conflictsOf` تقرؤها في كلِّ
 *    تسجيلِ قرار — فتُقارَن برموزِ `APR-*` ولا تطابق، لكنّها تُثقل السجلَّ
 *    وتُربك كلَّ قارئٍ يعُدُّ «التركيباتِ المُعلَنة».
 *    ⛔ **ولا تُحذف بل تُعطَّل**: `active=0` يُبقيها مقروءةً أثرًا للاستيرادِ
 *      الذي أدخلها، ويُخرجها من كلِّ قرارٍ حيّ.
 * ③ **142 مسودّةَ قالبٍ بلا بندٍ واحدٍ إطلاقًا**: أصدافٌ مبذورةٌ من ورقةِ
 *    الدفترِ ولم تُملأ قطُّ. تُثقل قائمةَ الكونسولِ وتُربك من يبحث عن قالب.
 *    تُقاعَد **بالخدمةِ لا بصفٍّ يدويّ**، فيبقى لكلِّ إقعادٍ سطرُه.
 *    ◆ **والمسودّةُ التي فيها بندٌ تبقى**: قد تكون عملًا جاريًا.
 *
 * التشغيل: php database/migrations/2028_05_24_perm04_registry_hygiene.php
 * العكس:   php database/migrations/2028_05_24_perm04_registry_hygiene_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/permissions_helper.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

use App\Services\Security\PolicyWriteService as PW;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

echo "══ PERM-04 · نظافةُ السجلّاتِ الحاكمة ═══════════════════════════════\n";

/* ═══ ① و② الصفوفُ الدخيلةُ تُعطَّل ولا تُحذف ═══════════════════════════ */
$t1 = $one("SELECT COUNT(*) FROM fin_approval_types WHERE active=1 AND code NOT LIKE 'APR-%'");
$conn->query("UPDATE fin_approval_types SET active = 0 WHERE active = 1 AND code NOT LIKE 'APR-%'");
printf("   ① أنواعُ اعتمادٍ دخيلةٌ عُطِّلت: %d\n", $conn->affected_rows);

$t2 = $one("SELECT COUNT(*) FROM fin_approval_conflicts WHERE active=1 AND (apr_a NOT LIKE 'APR-%' OR apr_b NOT LIKE 'APR-%')");
$conn->query("UPDATE fin_approval_conflicts SET active = 0
               WHERE active = 1 AND (apr_a NOT LIKE 'APR-%' OR apr_b NOT LIKE 'APR-%')");
printf("   ② تعارضاتٌ دخيلةٌ عُطِّلت: %d\n", $conn->affected_rows);

/* ═══ ③ المسودّاتُ الفارغةُ تُقاعَد بالخدمة ═════════════════════════════ */
$adminId = $one("SELECT id FROM users WHERE role='15' AND is_deleted=0 AND status='active' AND company_id=4 ORDER BY id LIMIT 1");
if ($adminId < 1) { exit("⛔ لا حساب حي بالدور 15.\n"); }
$_SESSION['user'] = array('id' => $adminId, 'role' => '15', 'company_id' => 4, 'name' => 'perm04 hygiene');

$empty = array();
$q = $conn->query("SELECT p.profile_id, p.profile_code FROM gov_role_profiles p
                    WHERE p.state = 'draft'
                      AND NOT EXISTS (SELECT 1 FROM gov_profile_items i WHERE i.profile_id = p.profile_id)");
while ($q && ($x = $q->fetch_assoc())) { $empty[] = $x; }
printf("\n   ③ مسودّاتٌ بلا بندٍ واحد: %d\n", count($empty));

$R = 'PERM-04: صدفة مسودة بلا بند واحد — تقاعد لتنظيف قائمة الكونسول';
$ok = 0; $bad = 0; $firstErr = '';
foreach ($empty as $p) {
    $r = PW::retireProfile($conn, (int) $p['profile_id'], $R, $adminId);
    if (!empty($r['ok'])) { $ok++; } else { $bad++; if ($firstErr === '') { $firstErr = $p['profile_code'] . ': ' . $r['msg']; } }
}
printf("      أُقعِدت: %d · تعذّرت: %d%s\n", $ok, $bad, $bad ? (' — ' . $firstErr) : '');

/* ═══ ④ الشاهد ═══════════════════════════════════════════════════════════ */
echo "\n══ الشاهد ══════════════════════════════════════════════════════════\n";
printf("   أنواعُ اعتمادٍ نافذةٌ الآن: %d (كلُّها APR-*: %s)\n",
    $one("SELECT COUNT(*) FROM fin_approval_types WHERE active=1"),
    $one("SELECT COUNT(*) FROM fin_approval_types WHERE active=1 AND code NOT LIKE 'APR-%'") === 0 ? 'نعم' : 'لا ⛔');
printf("   تعارضاتٌ نافذةٌ الآن: %d (كلُّها معياريّة: %s)\n",
    $one("SELECT COUNT(*) FROM fin_approval_conflicts WHERE active=1"),
    $one("SELECT COUNT(*) FROM fin_approval_conflicts WHERE active=1 AND (apr_a NOT LIKE 'APR-%' OR apr_b NOT LIKE 'APR-%')") === 0 ? 'نعم' : 'لا ⛔');
printf("   مسودّاتٌ باقية: %d · منها فارغة: %d\n",
    $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='draft'"),
    $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='draft'
           AND NOT EXISTS(SELECT 1 FROM gov_profile_items i WHERE i.profile_id=p.profile_id)"));
$cov = $one("SELECT COUNT(DISTINCT u.id) FROM users u
              JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
              JOIN gov_role_profiles pr ON pr.profile_id=g.profile_id AND pr.state='active'
             WHERE u.is_deleted=0 AND u.status='active' AND u.company_id=4");
$live = $one("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND status='active' AND company_id=4");
printf("   التغطية: %d من %d\n", $cov, $live);
if ($cov !== $live) { exit("\n⛔ سقطت التغطية.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والسجلُّ الحاكمُ لا يحمل ما لا يحكم.\n";
