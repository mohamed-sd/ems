<?php
/**
 * 2027_09_24_chain_screens_scope_narrow.php
 *   تضييقُ نطاقِ شاشاتِ الماليةِ والخزينة — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفه الحزامُ السلبيّ**: وراثةُ قوالبِ الأختِ صحيحةٌ في الموضعِ
 *   وواسعةٌ في الحق. فـ`payments_fin.php` **سطحٌ مشتركٌ** يراه عشرةُ قوالبَ
 *   (التشغيلُ والموارد والمشترياتُ والموردون والنقلُ والقدرة)، فورثت شاشتا
 *   **الخزينة** — سجلُّ المستفيدين ودفعاتُ الدفع — العشرةَ كلَّها. وقِيس حيًّا:
 *   دورُ التشغيلِ فتح اثنتين من ست.
 *
 * ◆ **وهذا يناقض نصَّ الوثيقةِ نفسِه**: «لا شاشةَ ماليةٍ أصليةٌ داخلَ التشغيلِ
 *   أو الموردين» و«المحاسبةُ ليست الخزينة» — والخزينةُ تملك التنفيذَ النقديَّ
 *   وحدَها. ⇒ يُضيَّق النطاقُ إلى قوالبِ المالية.
 *
 * ◆ **وشاشاتُ الذممِ الثلاثُ والاعتمادُ النهائيّ** ورثت `WRK-G7` من بوابةِ
 *   الاستحقاق — والقوى التشغيليةُ **طرفٌ في الاستحقاقِ لا في الاعترافِ
 *   بالإيراد**. فتُضيَّق كذلك.
 *
 * ◆ **وتصحيحُ الوحداتِ يبقى واسعًا بحقّ**: سلسلتُه الثلاثيةُ تلزمها أطرافٌ
 *   ثلاثة — التشغيلُ والمبيعاتُ والموردون — فاتساعُه **هو الصواب** لا خرق.
 *
 * ◆ **ولا يُحذف صفٌّ خارجَ هذه الجولة**: القيدُ على `seeded_from` وحدَه.
 *
 * التشغيل:  php database/migrations/2027_09_24_chain_screens_scope_narrow.php
 * الرجوع :  php database/migrations/2027_09_24_chain_screens_scope_narrow.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$SEED = 'INJ-CHAIN-CLOSE-01';
$NARROW = array('Finance/unit_fin_final.php', 'Finance/ar_accrual_gen.php',
                'Finance/ar_completion_cert.php', 'Finance/ar_claim_invoice.php',
                'Finance/tre_beneficiary.php', 'Finance/tre_pay_batch.php');
$in = "'" . implode("','", array_map(function ($x) use ($conn) {
        return $conn->real_escape_string($x); }, $NARROW)) . "'";

if (in_array('--revert', $argv, true)) {
    /* الرجوعُ يعيد الاتساع: يُعاد بذرُ القوالبِ من الأختِ بهجرةِ 2027_09_23 */
    $conn->query("UPDATE `gov_profile_items` SET `allow` = 1
                   WHERE `seeded_from` = '{$SEED}' AND `item_ref` IN ({$in})");
    echo "↺ أُعيد الاتساعُ إلى {$conn->affected_rows} بندًا — وأعد تشغيلَ 2027_09_23 للتحقّق\n";
    exit(0);
}

/* ما قبلَ التضييق — يُقاس ليُقارَن */
$q = $conn->query("SELECT COUNT(*) FROM `gov_profile_items`
                    WHERE `seeded_from` = '{$SEED}' AND `item_ref` IN ({$in}) AND `allow` = 1");
$before = $q ? (int) $q->fetch_row()[0] : -1;

/* التضييق: تُحذف بنودُ القوالبِ غيرِ المالية — والحذفُ داخلَ بذرِ هذه الجولةِ حصرًا */
$st = $conn->prepare(
  "DELETE i FROM `gov_profile_items` i
     JOIN `gov_role_profiles` pr ON pr.`profile_id` = i.`profile_id`
    WHERE i.`seeded_from` = ? AND i.`item_ref` IN ({$in})
      AND pr.`profile_code` NOT LIKE 'FIN-%'");
$st->bind_param('s', $SEED);
$st->execute();
$removed = $st->affected_rows;
$st->close();

$q = $conn->query("SELECT COUNT(*) FROM `gov_profile_items`
                    WHERE `seeded_from` = '{$SEED}' AND `item_ref` IN ({$in})");
$after = $q ? (int) $q->fetch_row()[0] : -1;

printf("① بنودُ قوالبِ شاشاتِ الماليةِ والخزينة: %d ⇐ **%d** (حُذف %d قالبًا غيرَ ماليّ)\n",
       $before, $after, $removed);

$q = $conn->query("SELECT i.`item_ref`, GROUP_CONCAT(pr.`profile_code` ORDER BY pr.`profile_code`) g
                     FROM `gov_profile_items` i
                     JOIN `gov_role_profiles` pr ON pr.`profile_id` = i.`profile_id`
                    WHERE i.`seeded_from` = '{$SEED}' GROUP BY i.`item_ref` ORDER BY i.`item_ref`");
echo "② النطاقُ بعدَ التضييق:\n";
while ($q && $r = $q->fetch_row()) { printf("   %-36s %s\n", $r[0], $r[1]); }
echo "   ◆ وتصحيحُ الوحداتِ يبقى واسعًا **بحقّ** — سلسلتُه الثلاثيةُ تلزمها\n";
echo "     أطرافٌ ثلاثة: التشغيلُ والمبيعاتُ والموردون.\n";

ems_migration_recorded(__FILE__, $conn, 0);
