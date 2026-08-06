<?php
/**
 * tests/sec013_test.php — رباعية SEC-013 (الأبعاد الأربعة والأفعال الـ16)
 * بنية: القاموسان مبذوران نصًّا (16 فعلًا · 9 نطاقات) · حساب: إعادة الاشتقاق
 * صفر فجوة · سماح: فعل مشتق لدورٍ مخوَّل يمر بنطاقه · منع: فعل بلا بند يُرفض
 * fail-closed · النطاق: بند شركة يجيز طلب موقع، والعكس ممنوع بنيويًّا ·
 * العلم: off لا يغير سلوكًا وenforce يحجب.
 * التشغيل: php tests/sec013_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../includes/sec013.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$pass = 0; $fail = 0;
function ok($c, $l) { global $pass, $fail; $c ? $pass++ : $fail++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ ') . $l . "\n"); }

/* ① البنية: الستة عشر والتسعة نصًّا */
$a = intval($conn->query("SELECT COUNT(*) FROM sec_actions")->fetch_row()[0]);
$s = intval($conn->query("SELECT COUNT(*) FROM sec_scopes")->fetch_row()[0]);
ok($a === 16, "قاموس الأفعال 16 ({$a})");
ok($s === 9, "قاموس النطاقات 9 ({$s})");
$must = array('screen_view', 'tab_view', 'field_view', 'create', 'edit', 'submit', 'return_fix',
    'approve', 'reject', 'cancel', 'reverse', 'draft_delete', 'export', 'print', 'grant_perm', 'cap_override');
$have = array();
$r = $conn->query("SELECT action_code FROM sec_actions");
while ($x = $r->fetch_row()) { $have[] = $x[0]; }
ok(!array_diff($must, $have), 'الستة عشر بأكوادها الحرفية كاملة');

/* ② الحساب: صفر بند بلا بعد مشتق (SEC-013 نص القبول) */
$gap = intval($conn->query("SELECT COUNT(*) FROM template_permissions tp
    WHERE SUBSTRING_INDEX(tp.permission_code, ':', -1) IN ('screen_view','create','update','delete_draft')
      AND NOT EXISTS (SELECT 1 FROM template_permission_dims d WHERE d.tp_id = tp.tp_id)")->fetch_row()[0]);
ok($gap === 0, "إعادة الحساب من المصدر: صفر فجوة ({$gap})");

/* ③ سماح: دور المبيعات 12 يرى شاشة العملاء (بند مشتق) */
$v = ems_sec013_allowed($conn, 12, 'Clients/clients.php', 'screen_view', 'company');
ok($v['allowed'], 'سماح: 12 × Clients/clients.php × screen_view (' . $v['reason'] . ')');

/* ④ منع fail-closed: فعل إداري بلا بند */
$v = ems_sec013_allowed($conn, 12, 'Clients/clients.php', 'grant_perm', 'company');
ok(!$v['allowed'], 'منع: grant_perm بلا بند يُرفض fail-closed');

/* ⑤ النطاق أهم الأبعاد: بند company (أوسع) يجيز طلبًا بنطاق site (أضيق) */
$v = ems_sec013_allowed($conn, 12, 'Clients/clients.php', 'screen_view', 'site');
ok($v['allowed'], 'بند شركة يجيز طلبًا في نطاق موقع (الأوسع يشمل الأضيق)');

/* ⑥ والعكس: بند site لا يجيز طلب company — نبني بند تجربة ضيقًا ونقيس */
$vers = ems_sec013_role_versions($conn, 12);
$vin = implode(',', $vers);
$tp = $conn->query("SELECT tp.tp_id FROM template_permissions tp
    WHERE tp.permission_code = 'Clients/clients.php:screen_view'
      AND tp.template_version_id IN ({$vin}) LIMIT 1")->fetch_assoc();
$tpid = intval($tp['tp_id']);
$conn->query("INSERT IGNORE INTO template_permission_dims (tp_id, action_code, scope_code, effect, derived_from)
              VALUES ({$tpid}, 'export', 'site', 'grant', 'sec013-test')");
$v1 = ems_sec013_allowed($conn, 12, 'Clients/clients.php', 'export', 'site');
$v2 = ems_sec013_allowed($conn, 12, 'Clients/clients.php', 'export', 'company');
ok($v1['allowed'] && !$v2['allowed'], 'بند موقعٍ يجيز في موقعه ولا يجيز على مستوى الشركة');
$conn->query("DELETE FROM template_permission_dims WHERE derived_from = 'sec013-test'");

/* ⑦ العلم: off يمرر بلا حكم · enforce يحجب الفعل الممنوع */
putenv('EMS_SEC013=off');
$g = ems_sec013_gate($conn, 12, 'Clients/clients.php', 'grant_perm');
ok($g['ok'] === true && $g['mode'] === 'off', 'off: لا تغيير سلوك (القلب يوم 19-08)');
putenv('EMS_SEC013=enforce');
$g = ems_sec013_gate($conn, 12, 'Clients/clients.php', 'grant_perm');
ok($g['ok'] === false && $g['mode'] === 'enforce', 'enforce: الفعل الممنوع يُحجب');
putenv('EMS_SEC013=off');

fwrite(STDOUT, "\n══ النتيجة: {$pass} ناجحة · {$fail} فاشلة ══\n");
exit($fail ? 1 : 0);
