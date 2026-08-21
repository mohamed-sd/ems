<?php
/**
 * 2027_09_26_chain_nav_group.php
 *   مجموعةُ تنقّلٍ مستقلةٌ لعقدِ السلسلة — حدُّ القسمِ المقروء (U9)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفته بوابةُ الواجهة**: إدراجُ ستِّ شاشاتِ ماليةٍ في مجموعةِ أختِها
 *   رفع قسمَ دورِ الماليةِ إلى **اثنَي عشرَ عنصرًا والحدُّ تسعة** — فرسبت U9.
 *   والقسمُ الذي يتجاوز الحدَّ **لا يُقرأ**: يصير قائمةً تُمسح لا تُفهم.
 *
 * ◆ **والعلاجُ مجموعةٌ بمعناها لا شدُّ حد**: عقدُ سلسلةِ الأثرِ مرحلةٌ واحدةٌ
 *   في الدورةِ المستندية — من الاستحقاقِ إلى الفاتورةِ إلى الصرف — فتستحق
 *   قسمَها. واسمُها **اسمٌ مؤسسيٌّ اسميّ** لا فعلَ متكلمٍ ولا لفظًا محادثيًّا،
 *   كما توجب لغةُ التسميةِ النافذة.
 *
 * ◆ **ولا يُمَسُّ موضعُ شاشةٍ قائمة**: تُنقل بنودُ هذه الجولةِ وحدَها.
 *
 * التشغيل:  php database/migrations/2027_09_26_chain_nav_group.php
 * الرجوع :  php database/migrations/2027_09_26_chain_nav_group.php --revert
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

$ROUTES = array(
    'Finance/unit_fin_final.php', 'Finance/ar_accrual_gen.php', 'Finance/ar_completion_cert.php',
    'Finance/ar_claim_invoice.php', 'Finance/tre_beneficiary.php', 'Finance/tre_pay_batch.php',
    'Operations/unit_correction.php',
);
$in = "'" . implode("','", array_map(function ($x) use ($conn) {
        return $conn->real_escape_string($x); }, $ROUTES)) . "'";
$GNAME = 'سلسلة الأثر — الاستحقاق والفوترة والصرف';
$GCODE_PREFIX = 'n9c_chain_r';

if (in_array('--revert', $argv, true)) {
    /* تُعاد البنودُ إلى مجموعةِ أختِها ثم تُحذف المجموعة */
    $q = $conn->query("SELECT `id`, `owner_role_id` FROM `link_groups`
                        WHERE `group_code` LIKE '{$GCODE_PREFIX}%'");
    $back = 0;
    while ($q && $g = $q->fetch_assoc()) {
        $role = (int) $g['owner_role_id'];
        $anchor = $conn->query("SELECT `group_id` FROM `nav_items`
                                 WHERE `role_id`={$role} AND `route`='Finance/entitlement_gate.php'
                                   AND `active`=1 LIMIT 1");
        $gid = ($anchor && ($a = $anchor->fetch_row())) ? (int) $a[0] : 0;
        if ($gid > 0) {
            $conn->query("UPDATE `nav_items` SET `group_id`={$gid}
                           WHERE `group_id`=" . (int) $g['id']);
            $back += $conn->affected_rows;
        }
    }
    $conn->query("DELETE FROM `link_groups` WHERE `group_code` LIKE '{$GCODE_PREFIX}%'");
    echo "↺ أُعيد {$back} بندًا · وحُذفت " . $conn->affected_rows . " مجموعة\n";
    exit(0);
}

/* الأدوارُ التي تحمل هذه البنود */
$roles = array();
$q = $conn->query("SELECT DISTINCT `role_id`, `door` FROM `nav_items` WHERE `route` IN ({$in})");
while ($q && $r = $q->fetch_assoc()) { $roles[(int) $r['role_id']] = (string) $r['door']; }

$made = 0; $moved = 0;
foreach ($roles as $role => $door) {
    $code = $GCODE_PREFIX . $role;
    $g = $conn->query("SELECT `id` FROM `link_groups` WHERE `group_code` = '{$code}' LIMIT 1");
    $gid = ($g && ($x = $g->fetch_row())) ? (int) $x[0] : 0;
    if ($gid === 0) {
        /* الترتيبُ بعدَ آخرِ مجموعةٍ لهذا الدور — فلا تتصدَّر قائمتَه */
        $o = $conn->query("SELECT COALESCE(MAX(lg.`display_order`),100) FROM `link_groups` lg
                            JOIN `nav_items` n ON n.`group_id` = lg.`id`
                           WHERE n.`role_id` = {$role}");
        $ord = ($o ? (int) $o->fetch_row()[0] : 100) + 1;
        $st = $conn->prepare("INSERT INTO `link_groups`
              (`name`,`group_code`,`owner_role_id`,`icon`,`display_order`,`stage_no`,`stage_title`,`is_active`)
              VALUES (?,?,?,'fa fa-diagram-project',?,9,?,1)");
        $title = 'سلسلة الأثر — من الوحدة المعتمدة إلى القبض والصرف';
        $st->bind_param('ssiis', $GNAME, $code, $role, $ord, $title);
        if ($st->execute()) { $gid = (int) $st->insert_id; $made++; }
        else { echo "  ✘ مجموعةُ الدور {$role}: {$st->error}\n"; }
        $st->close();
    }
    if ($gid === 0) { continue; }
    $conn->query("UPDATE `nav_items` SET `group_id` = {$gid}
                   WHERE `role_id` = {$role} AND `route` IN ({$in})");
    $moved += $conn->affected_rows;
}

printf("① مجموعاتٌ أُنشئت: %d · بنودٌ نُقلت: %d · أدوار: %d\n", $made, $moved, count($roles));
echo "② الاسمُ: «{$GNAME}» — اسمٌ مؤسسيٌّ اسميٌّ لا فعلَ متكلمٍ فيه\n";

ems_migration_recorded(__FILE__, $conn, 0);
