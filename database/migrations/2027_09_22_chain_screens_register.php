<?php
/**
 * 2027_09_22_chain_screens_register.php
 *   تسجيلُ شاشاتِ عقدِ السلسلةِ في التنقّلِ والصلاحيات — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الشاشةُ غيرُ المسجَّلةِ لا تُفتح**: `enforce_current_page_view_permission`
 *   يقرأ `modules` و`role_permissions`، والقائمةُ تُبنى من `nav_items`.
 *   فملفٌّ على القرصِ بلا هذه الثلاثةِ **شاشةٌ لا يصل إليها أحد**.
 *
 * ◆ **ولا يُخترَع موضعٌ**: كلُّ شاشةٍ تأخذ **مجموعةَ أختِها** لكلِّ دور —
 *   المجموعةُ نفسُها التي تسكنها الشاشةُ المرجعُ في تنقّلِ ذلك الدور. فلا
 *   مجموعةٌ جديدةٌ ولا ترتيبٌ يدويٌّ مخترَع.
 *
 * ◆ **والمنحُ للمالكِ لا للجميع**: مالكُ العمليةِ في سجلِّ عقدِ السلسلةِ هو مَن
 *   يُمنَح — ومَن يقرأ فقط لا يُمنَح كتابةً.
 *
 * التشغيل:  php database/migrations/2027_09_22_chain_screens_register.php
 * الرجوع :  php database/migrations/2027_09_22_chain_screens_register.php --revert
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

/* route, label, anchor(سطحٌ أختٌ يُؤخذ موضعُه), owner_role, write_roles[] */
$S = array(
array('Finance/unit_fin_final.php',     'الاعتماد المالي النهائي',            'Finance/entitlement_gate.php', 19, array(17, 19)),
array('Finance/ar_accrual_gen.php',     'توليد استحقاقات عقد العميل',         'Finance/entitlement_gate.php', 17, array(17, 19)),
array('Finance/ar_completion_cert.php', 'شهادة الإنجاز الشهرية',              'Finance/entitlement_gate.php', 17, array(17, 19)),
array('Finance/ar_claim_invoice.php',   'فاتورة المطالبة وإحالتها',           'Finance/entitlement_gate.php', 17, array(17, 19)),
array('Finance/tre_beneficiary.php',    'سجل المستفيدين والحسابات البنكية',   'Finance/payments_fin.php',     17, array(17, 19)),
array('Finance/tre_pay_batch.php',      'دفعات الدفع والتنفيذ',               'Finance/payments_fin.php',     17, array(17, 19)),
array('Operations/unit_correction.php', 'تصحيح الوحدات بالسلسلة الثلاثية',    'Operations/unit_perf.php',      1, array(1, 5, 6)),
);

if (in_array('--revert', $argv, true)) {
    foreach ($S as $r) {
        $route = $r[0];
        $conn->query("DELETE FROM `nav_items` WHERE `route` = '" . $conn->real_escape_string($route) . "'");
        $q = $conn->query("SELECT `id` FROM `modules` WHERE `code` = '" . $conn->real_escape_string($route) . "'");
        if ($q && ($m = $q->fetch_row())) {
            $conn->query("DELETE FROM `role_permissions` WHERE `module_id` = " . (int) $m[0]);
            $conn->query("DELETE FROM `modules` WHERE `id` = " . (int) $m[0]);
        }
    }
    echo "↺ أُزيلت شاشاتُ السلسلةِ من السجلِّ والتنقّلِ والصلاحيات\n";
    exit(0);
}

$modN = 0; $navN = 0; $permN = 0; $noAnchor = array();
foreach ($S as $r) {
    list($route, $label, $anchor, $ownerRole, $writeRoles) = $r;

    /* ① الوحدة */
    $q = $conn->query("SELECT `id` FROM `modules` WHERE `code` = '" . $conn->real_escape_string($route) . "'");
    $mid = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : 0;
    if ($mid === 0) {
        $st = $conn->prepare("INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`) VALUES (?,?,?,1)");
        $st->bind_param('ssi', $label, $route, $ownerRole);
        if ($st->execute()) { $mid = (int) $st->insert_id; $modN++; }
        else { echo "  ✘ وحدةُ {$route}: {$st->error}\n"; }
        $st->close();
    }
    if ($mid === 0) { continue; }

    /* ② موضعُ التنقّل — مجموعةُ الأختِ لكلِّ دور، ولا مجموعةَ مخترَعة */
    /* ◆ **والبابُ يُورَث لا يُخترَع**: `nav_items.door` محروسٌ بـCHECK بتسعةِ
     *   رموزٍ لا غير (HOME · DAILY · APPR · REC · REP · SET · GOV · FIN · RISK).
     *   فقيمةٌ مخترَعةٌ تُردُّ بالخطأ 4025 — **ويُبتلع صامتًا إن لم يُقرأ مُرجَعُ
     *   التنفيذ**. فيُؤخذ بابُ الأختِ كما يُؤخذ موضعُها. */
    $q = $conn->query("SELECT `role_id`, `group_id`, `door`, MAX(`sort_order`) AS so
                         FROM `nav_items` WHERE `route` = '" . $conn->real_escape_string($anchor) . "'
                          AND `active` = 1 GROUP BY `role_id`, `group_id`, `door`");
    $placed = 0;
    while ($q && $x = $q->fetch_assoc()) {
        $role = (int) $x['role_id'];
        $grp  = (int) $x['group_id'];
        $door = (string) $x['door'];
        $ord  = (int) $x['so'] + 1;
        $chk = $conn->query("SELECT 1 FROM `nav_items` WHERE `role_id`={$role}
                              AND `route`='" . $conn->real_escape_string($route) . "' LIMIT 1");
        if ($chk && $chk->num_rows) { continue; }
        $st = $conn->prepare("INSERT INTO `nav_items`
              (`role_id`,`door`,`group_id`,`module_id`,`label_ar`,`route`,`icon`,`sort_order`,`active`,`permission_code`)
              VALUES (?,?,?,?,?,?,'fa fa-diagram-project',?,1,?)");
        $st->bind_param('isiissis', $role, $door, $grp, $mid, $label, $route, $ord, $route);
        if ($st->execute()) { $navN++; $placed++; }
        else { echo "  ✘ بندُ {$route} للدور {$role}: {$st->errno} {$st->error}\n"; }
        $st->close();
    }
    if ($placed === 0) { $noAnchor[] = $route . ' (أختُه ' . $anchor . ')'; }

    /* ③ المنحُ للمالكِ ومَن يكتب — والقراءةُ لمن يظهر له البند */
    foreach ($writeRoles as $role) {
        $st = $conn->prepare("INSERT INTO `role_permissions`
              (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
              VALUES (?,?,1,1,1,0)
              ON DUPLICATE KEY UPDATE `can_view`=1, `can_add`=1, `can_edit`=1");
        $st->bind_param('ii', $role, $mid);
        if ($st->execute()) { $permN++; }
        $st->close();
    }
    $q2 = $conn->query("SELECT DISTINCT `role_id` FROM `nav_items` WHERE `route`='"
                       . $conn->real_escape_string($route) . "'");
    while ($q2 && $x = $q2->fetch_row()) {
        $role = (int) $x[0];
        if (in_array($role, $writeRoles, true)) { continue; }
        $st = $conn->prepare("INSERT INTO `role_permissions`
              (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
              VALUES (?,?,1,0,0,0)
              ON DUPLICATE KEY UPDATE `can_view`=1");
        $st->bind_param('ii', $role, $mid);
        if ($st->execute()) { $permN++; }
        $st->close();
    }
}

printf("① وحدات: %d · بنودُ تنقّل: %d · منحُ صلاحية: %d\n", $modN, $navN, $permN);
if ($noAnchor) { echo "  ⚠ بلا موضعٍ (أختُه غيرُ مُدرَجة): " . implode(' · ', $noAnchor) . "\n"; }

/* ── ② سجلُّ عقدِ السلسلةِ يُحدَّث بما بُني فعلًا ─────────────────────────── */
$BUILT = array(9 => 'Finance/unit_fin_final.php', 13 => 'Operations/unit_correction.php',
               16 => 'Finance/ar_accrual_gen.php', 17 => 'Finance/ar_completion_cert.php',
               18 => 'Finance/ar_claim_invoice.php', 25 => 'Finance/tre_pay_batch.php');
$upd = 0;
foreach ($BUILT as $node => $route) {
    if (!is_file($ROOT . '/' . $route)) { continue; }   /* لا يُوسَم مبنيًّا ما ليس على القرص */
    $st = $conn->prepare("UPDATE `gov_chain_nodes` SET `build_state`='BUILT', `carrier_route`=?
                           WHERE `node_no`=?");
    $st->bind_param('si', $route, $node);
    if ($st->execute()) { $upd += $st->affected_rows; }
    $st->close();
}
printf("② وُسِمت %d عقدةً مبنيةً — والوسمُ مشروطٌ بوجودِ الملفِّ على القرص\n", $upd);
$q = $conn->query("SELECT `build_state`, COUNT(*) FROM `gov_chain_nodes` GROUP BY `build_state`");
while ($q && $x = $q->fetch_row()) { printf("   %-20s %s\n", $x[0], $x[1]); }

ems_migration_recorded(__FILE__, $conn, 0);
