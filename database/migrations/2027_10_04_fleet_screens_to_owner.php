<?php
/**
 * 2027_10_04_fleet_screens_to_owner.php
 *   شاشتا الأسطولِ تُسلَّمان لمالكِهما قبلَ رفعِهما من مساحةِ المبيعات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفه تطبيقُ التنقّل**: `fleet_calendar` و`fleet_utilization` مالكُهما
 *   **إدارةُ التشغيل** بنصِّ سجلِّ المساحات، وحكمُ ظهورِهما في مساحةِ المبيعاتِ
 *   `FORBIDDEN` — ومع ذلك **بندُ تنقّلِهما الوحيدُ في الدنيا كان في المبيعات**.
 *   فرفعُهما من هناك بلا تسليمٍ **يجعلهما بلا منفذٍ أصلًا**، وذلك فقدٌ لا دمج.
 *
 * ◆ **فالترتيبُ ملزم**: يُسلَّمان لمالكِهما أوّلًا، ثم يُرفعان من مساحةِ من لا
 *   يملكهما. **والتسليمُ قبلَ الرفعِ لا بعده.**
 *
 * ◆ ولا تُخترع مجموعة: يأخذان مجموعةَ أختِهما في تنقّلِ المالك.
 *
 * التشغيل:  php database/migrations/2027_10_04_fleet_screens_to_owner.php
 * الرجوع :  php database/migrations/2027_10_04_fleet_screens_to_owner.php --revert
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

$OWNER = 1;                                  /* إدارةُ التشغيل */
$ANCHOR = 'Operations/unit_perf.php';        /* أختٌ في تنقّلِ المالك */
$S = array(
    'Operations/fleet_calendar.php'    => 'تقويم الأسطول والحجز',
    'Operations/fleet_utilization.php' => 'استغلال الأسطول ومردوده',
);
$in = "'" . implode("','", array_keys($S)) . "'";

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `nav_items` WHERE `role_id` = {$OWNER} AND `route` IN ({$in})");
    echo "↺ حُذف {$conn->affected_rows} بندًا من تنقّلِ المالك\n";
    exit(0);
}

$a = $conn->query("SELECT `door`,`group_id`,`module_id`,MAX(`sort_order`) so FROM `nav_items`
                    WHERE `role_id` = {$OWNER} AND `route` = '{$ANCHOR}' AND `active` = 1
                    GROUP BY `door`,`group_id`,`module_id` LIMIT 1");
$anc = $a ? $a->fetch_assoc() : null;
if (!$anc) { exit("✘ لا أختَ في تنقّلِ المالك ({$ANCHOR}) — أُوقفت الهجرة\n"); }

$made = 0;
foreach ($S as $route => $label) {
    $c = $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `role_id` = {$OWNER}
                        AND `route` = '" . $conn->real_escape_string($route) . "'");
    if ($c && (int) $c->fetch_row()[0] > 0) { continue; }
    /* الوحدةُ من صفِّ المبيعاتِ نفسِه — فلا تُخترَع وحدةٌ ثانية */
    $m = $conn->query("SELECT `module_id` FROM `nav_items`
                        WHERE `route` = '" . $conn->real_escape_string($route) . "' LIMIT 1");
    $mid = ($m && ($x = $m->fetch_row())) ? ($x[0] === null ? null : (int) $x[0]) : null;
    $ord = (int) $anc['so'] + 1;
    $st = $conn->prepare("INSERT INTO `nav_items`
          (`role_id`,`door`,`group_id`,`module_id`,`label_ar`,`route`,`icon`,`sort_order`,`active`,`permission_code`)
          VALUES (?,?,?,?,?,?,'fa fa-truck-fast',?,1,?)");
    $gid = (int) $anc['group_id'];
    $st->bind_param('isiissis', $OWNER, $anc['door'], $gid, $mid, $label, $route, $ord, $route);
    if ($st->execute()) { $made++; } else { echo "  ✘ {$route}: {$st->error}\n"; }
    $st->close();

    /* والمنحُ للمالكِ — قراءةً وكتابةً كأختِها */
    if ($mid !== null) {
        $g = $conn->prepare("INSERT INTO `role_permissions`
              (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
              VALUES (?,?,1,1,1,0) ON DUPLICATE KEY UPDATE `can_view`=1, `can_edit`=1");
        $g->bind_param('ii', $OWNER, $mid);
        $g->execute(); $g->close();
    }
}
printf("① سُلِّمت %d شاشةً لمالكِها (دور %d)\n", $made, $OWNER);
echo "② والآن يجوز رفعُهما من مساحةِ المبيعات — **بعدَ التسليمِ لا قبلَه**\n";

ems_migration_recorded(__FILE__, $conn, 0);
