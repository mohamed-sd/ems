<?php
/**
 * 2027_10_03_align_nav_apply.php
 *   تطبيقُ التنقّلِ المستهدَف — المبيعاتُ ١٣ والموردون ١٤ في ستِّ مجموعات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدمجُ في الواجهةِ لا في السجل**: لا صفَّ يُحذف. البندُ الذي يصير تبويبًا
 *   يُخفى من القائمةِ (`active = 0`) **ومسارُه يبقى مفتوحًا** — وقد جُسَّ ذلك
 *   قبلَ التطبيق: مسارُ الصلاحيةِ لا يسأل `nav_items` أصلًا.
 *
 * ◆ **وكلُّ ما يُخفى يُقيَّد بموضعِه السابق** في `gov_nav_hidden_log` — فالرجوعُ
 *   يعيد الصفَّ إلى مجموعتِه وترتيبِه حرفًا، لا إلى «مكانٍ ما».
 *
 * ◆ **والمرساتان لا تُمَسّان**: «الرئيسية» و«المراسلات» مفروضتان بقرارِ مالكٍ
 *   سابق، وخارجَ مقامِ الثلاثةَ عشرَ والأربعةَ عشر.
 *
 * ◆ **ولا يُخفى بندٌ لمسارٍ لا منفذَ له**: يُقاس لكلِّ مسارٍ مُخفًى أنَّه إمّا
 *   تبويبٌ في ملفٍّ أمٍّ قائم، أو نشطٌ لدورٍ آخر، أو مملوكٌ لإدارةٍ أخرى بحكمٍ
 *   مكتوب. وما لا ينطبق عليه شيءٌ من ذلك **يُترك ظاهرًا ويُعلَن**.
 *
 * التشغيل:  php database/migrations/2027_10_03_align_nav_apply.php
 * الرجوع :  php database/migrations/2027_10_03_align_nav_apply.php --revert
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

$ANCHORS = array('main/role_board.php', 'chats/index.php');
$GPREFIX = 'n9t_align_r';

$conn->query("CREATE TABLE IF NOT EXISTS `gov_nav_hidden_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `role_id`    INT NOT NULL,
  `nav_id`     INT NOT NULL,
  `route`      VARCHAR(160) NOT NULL,
  `label_ar`   VARCHAR(120) NOT NULL,
  `group_before` INT NULL,
  `sort_before`  INT NULL,
  `doc_code`   VARCHAR(24) NOT NULL,
  `reachable`  VARCHAR(40) NOT NULL COMMENT 'TAB_IN_PARENT | ACTIVE_FOR_OTHER_ROLE | OWNED_ELSEWHERE',
  `hidden_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_hidden` (`nav_id`),
  KEY `ix_hidden_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ما أُخفي من التنقّلِ بموضعِه السابق — الرجوعُ يعيده حرفًا'");

if (in_array('--revert', $argv, true)) {
    $q = $conn->query("SELECT `nav_id`,`group_before`,`sort_before` FROM `gov_nav_hidden_log`");
    $back = 0;
    while ($q && $r = $q->fetch_assoc()) {
        $st = $conn->prepare("UPDATE `nav_items` SET `active` = 1, `group_id` = ?, `sort_order` = ?
                               WHERE `id` = ?");
        $g = $r['group_before'] === null ? null : (int) $r['group_before'];
        $s = (int) $r['sort_before']; $i = (int) $r['nav_id'];
        $st->bind_param('iii', $g, $s, $i);
        $st->execute(); $back += $st->affected_rows; $st->close();
    }
    $conn->query("DELETE FROM `gov_nav_hidden_log`");
    $conn->query("DELETE FROM `link_groups` WHERE `group_code` LIKE '{$GPREFIX}%'");
    echo "↺ أُعيد {$back} بندًا إلى موضعِه السابق · وحُذفت مجموعاتُ الجولة\n";
    exit(0);
}

/* ══ ① المجموعاتُ الستُّ لكلِّ دور ═══════════════════════════════════════ */
$groups = array();   /* role|group_no => link_group id */
$q = $conn->query("SELECT DISTINCT `role_id`,`group_no`,`group_ar` FROM `gov_target_nav`
                    ORDER BY `role_id`,`group_no`");
$mk = 0;
while ($q && $r = $q->fetch_assoc()) {
    $role = (int) $r['role_id']; $gno = (int) $r['group_no'];
    $code = $GPREFIX . $role . '_' . $gno;
    $g = $conn->query("SELECT `id` FROM `link_groups` WHERE `group_code` = '{$code}' LIMIT 1");
    $gid = ($g && ($x = $g->fetch_row())) ? (int) $x[0] : 0;
    if ($gid === 0) {
        $st = $conn->prepare("INSERT INTO `link_groups`
              (`name`,`group_code`,`owner_role_id`,`icon`,`display_order`,`stage_no`,`stage_title`,`is_active`)
              VALUES (?,?,?,'fa fa-layer-group',?,?,?,1)");
        $ord = 100 + $gno;
        $st->bind_param('ssiiis', $r['group_ar'], $code, $role, $ord, $gno, $r['group_ar']);
        if ($st->execute()) { $gid = (int) $st->insert_id; $mk++; }
        $st->close();
    }
    $groups[$role . '|' . $gno] = $gid;
}
printf("① مجموعاتٌ مستهدَفةٌ جاهزة: %d (أُنشئ %d)\n", count($groups), $mk);

/* ══ ② البنودُ المستهدَفةُ في مواضعِها ═══════════════════════════════════ */
$keep = array();     /* role => [route => true] */
$q = $conn->query("SELECT * FROM `gov_target_nav` ORDER BY `role_id`,`group_no`,`item_no`");
$upd = 0; $add = 0;
while ($q && $r = $q->fetch_assoc()) {
    $role = (int) $r['role_id'];
    $gid  = $groups[$role . '|' . (int) $r['group_no']];
    $ord  = ((int) $r['group_no']) * 100 + (int) $r['item_no'];
    $keep[$role][$r['route']] = true;

    $ex = $conn->prepare("SELECT `id`,`door`,`module_id` FROM `nav_items`
                           WHERE `role_id` = ? AND `route` = ? LIMIT 1");
    $ex->bind_param('is', $role, $r['route']); $ex->execute();
    $row = $ex->get_result()->fetch_assoc(); $ex->close();
    if ($row) {
        $st = $conn->prepare("UPDATE `nav_items` SET `group_id` = ?, `sort_order` = ?,
                               `label_ar` = ?, `active` = 1 WHERE `id` = ?");
        $id = (int) $row['id'];
        $st->bind_param('iisi', $gid, $ord, $r['item_ar'], $id);
        if ($st->execute()) { $upd++; }
        $st->close();
    } else {
        /* لا صفَّ لهذا الدور — يُنشأ ببابِ أختِه ووحدتِها */
        $sib = $conn->query("SELECT `door`,`module_id` FROM `nav_items`
                              WHERE `route` = '" . $conn->real_escape_string($r['route']) . "'
                                AND `active` = 1 LIMIT 1");
        $s = $sib ? $sib->fetch_assoc() : null;
        if (!$s) { echo "   ⚠ لا مرجعَ لبابِ {$r['route']} — لم يُنشأ\n"; continue; }
        $st = $conn->prepare("INSERT INTO `nav_items`
              (`role_id`,`door`,`group_id`,`module_id`,`label_ar`,`route`,`icon`,`sort_order`,`active`,`permission_code`)
              VALUES (?,?,?,?,?,?,'fa fa-list',?,1,?)");
        $mid = $s['module_id'] === null ? null : (int) $s['module_id'];
        $st->bind_param('isiissis', $role, $s['door'], $gid, $mid, $r['item_ar'], $r['route'], $ord, $r['route']);
        if ($st->execute()) { $add++; } else { echo "   ✘ {$r['route']}: {$st->error}\n"; }
        $st->close();
    }
}
printf("② بنودٌ في مواضعِها: حُدِّث %d · أُنشئ %d\n", $upd, $add);

/* ══ ③ ما عداها يُخفى — بعدَ إثباتِ منفذِه ══════════════════════════════ */
/* منافذُ الوصولِ الثلاثة: تبويبٌ في ملفٍّ أمّ · نشطٌ لدورٍ آخر · مملوكٌ بحكمٍ مكتوب */
$tabRoutes = array();
foreach (array('contract_file_tabs.php', 'supplier_file_tabs.php', 'financing_file_tabs.php',
               'entity_tabs.php', 'sales_family_tabs.php') as $kit) {
    $src = (string) @file_get_contents($ROOT . '/includes/' . $kit);
    if (preg_match_all("~'([A-Za-z][A-Za-z0-9_]*/[A-Za-z0-9_]+\.php)~", $src, $m)) {
        foreach ($m[1] as $rt) { $tabRoutes[$rt] = $kit; }
    }
}
$ownedElsewhere = array();
$q = $conn->query("SELECT `book_file`,`real_route`,`owner_dept` FROM `gov_screen_path_map`
                    WHERE `ruling` IN ('OWNED_ELSEWHERE','MERGED_INTO','VIEW_OF')");
while ($q && $r = $q->fetch_assoc()) { if ($r['real_route']) { $ownedElsewhere[$r['real_route']] = 1; } }

$log = $conn->prepare("INSERT INTO `gov_nav_hidden_log`
      (`role_id`,`nav_id`,`route`,`label_ar`,`group_before`,`sort_before`,`doc_code`,`reachable`)
      VALUES (?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE `reachable` = VALUES(`reachable`)");
$hide = $conn->prepare("UPDATE `nav_items` SET `active` = 0 WHERE `id` = ?");

$hidden = 0; $left = array();
foreach (array(12 => 'INJ-SAL-ALIGN-01', 2 => 'INJ-SUP-ALIGN-01') as $role => $doc) {
    $q = $conn->query("SELECT `id`,`route`,`label_ar`,`group_id`,`sort_order` FROM `nav_items`
                        WHERE `role_id` = {$role} AND `active` = 1");
    while ($q && $r = $q->fetch_assoc()) {
        $rt   = (string) $r['route'];
        $base = preg_replace('/[?\#].*$/', '', $rt);
        if (in_array($base, $ANCHORS, true)) { continue; }
        if (isset($keep[$role][$rt]))        { continue; }

        /* منفذٌ بديلٌ مُثبَت؟ */
        $why = '';
        if (isset($tabRoutes[$base]))      { $why = 'TAB_IN_PARENT'; }
        elseif (isset($ownedElsewhere[$base])) { $why = 'OWNED_ELSEWHERE'; }
        else {
            $o = $conn->query("SELECT COUNT(*) FROM `nav_items`
                                WHERE `route` = '" . $conn->real_escape_string($rt) . "'
                                  AND `active` = 1 AND `role_id` <> {$role}");
            if ($o && (int) $o->fetch_row()[0] > 0) { $why = 'ACTIVE_FOR_OTHER_ROLE'; }
        }
        if ($why === '') { $left[] = "دور {$role} · {$rt}"; continue; }

        $id = (int) $r['id']; $gb = (int) $r['group_id']; $sb = (int) $r['sort_order'];
        $log->bind_param('iissiiss', $role, $id, $rt, $r['label_ar'], $gb, $sb, $doc, $why);
        $log->execute();
        $hide->bind_param('i', $id);
        if ($hide->execute()) { $hidden++; }
    }
}
$log->close(); $hide->close();
printf("③ أُخفي %d بندًا — كلٌّ بمنفذٍ بديلٍ مُثبَت\n", $hidden);
$q = $conn->query("SELECT `reachable`, COUNT(*) FROM `gov_nav_hidden_log` GROUP BY `reachable` ORDER BY 2 DESC");
while ($q && $r = $q->fetch_row()) { printf("   %-24s %s\n", $r[0], $r[1]); }
if ($left) {
    printf("④ **تُرك ظاهرًا (بلا منفذٍ بديلٍ مُثبَت): %d**\n", count($left));
    foreach (array_slice($left, 0, 20) as $l) { echo "   · {$l}\n"; }
    echo "   ◆ ولا يُخفى بندٌ لمسارٍ لا منفذَ له — **الإخفاءُ حينئذٍ فقدٌ لا دمج**.\n";
} else {
    echo "④ لا بندَ تُرك — كلُّ ما خرج له منفذ\n";
}

/* ══ ⑤ الحصيلة ════════════════════════════════════════════════════════ */
foreach (array(12 => 13, 2 => 14) as $role => $target) {
    $q = $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `role_id` = {$role} AND `active` = 1
                        AND `route` NOT IN ('main/role_board.php','chats/index.php')");
    $n = $q ? (int) $q->fetch_row()[0] : -1;
    $q = $conn->query("SELECT COUNT(DISTINCT `group_id`) FROM `nav_items`
                        WHERE `role_id` = {$role} AND `active` = 1
                          AND `route` NOT IN ('main/role_board.php','chats/index.php')");
    $g = $q ? (int) $q->fetch_row()[0] : -1;
    printf("⑤ دور %-3d بنودٌ **%d/%d** · مجموعات **%d/6** %s\n",
           $role, $n, $target, $g, ($n === $target && $g === 6) ? '✔' : '✘');
}

ems_migration_recorded(__FILE__, $conn, 0);
