<?php
/**
 * 2027_10_10_align_capabilities_register.php
 *   تسجيلُ القدراتِ الخمسِ المبنيّة — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01 §8
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولا بندَ تنقّلٍ واحدًا في هذه الهجرة** — وهو قرارُ الوثيقتين لا اجتهادي:
 *   الأوراقُ الخمسُ قرارُها في `gov_sheet_decisions` صريح —
 *     ٠٦ «قدرة مفقودة → child of opportunity»
 *     ٠٨ و٠٩ «سجل تابع → child of quotations.php»
 *     م١٩ «قدرة مفقودة → child of settlements»
 *     م٢٣ «قدرة مفقودة → build dashboard»
 *   و`gov_target_nav` يقول في صدرِه: «ولوحةُ الإدارةِ **صفحةُ هبوطٍ للمساحةِ
 *   لا بندًا في مجموعة**». ⇒ **أربعةُ تبويباتٍ ولوحةٌ — والقائمةُ تبقى
 *   ٦/١٣ و٦/١٤ كما أُقفلت.** فإضافتُها بنودًا كانت ستنقض الدمجَ الذي أُثبت.
 *
 * ◆ **والتبويبُ يجب أن يوجد لا أن يُعلَن** — فالمنفذُ مبنيٌّ في المكوّنِ المركزيِّ
 *   `includes/sales_family_tabs.php` بعائلتَين جديدتَين (عرضُ السعرِ · تسويةُ
 *   المورد) وتبويبةٍ في عائلةِ الفرصة، والأبُ نفسُه يحمل الشريط.
 *
 * ◆ **والمنحُ بالملكيةِ لا بالوراثةِ العمياء**: الرؤيةُ تُورَث من الأبِ (فالتبويبُ
 *   يُرى حيثُ يُرى أبوه)، أما **الكتابةُ فبالمالك**. ووراثةُ صفِّ التسوياتِ حرفيًّا
 *   كانت ستجعل **مديرَ الماليةِ وحدَه** مَن يعتمد مخالفةَ مورّد — والماليةُ تنفّذ
 *   نقدًا ولا تقرّر انضباطَ مورّد. فالاعتمادُ لإدارةِ المورّدين وحدَها،
 *   و**فصلُ الواجباتِ يُحرَس بالفاعلِ لا بالدور**: مَن رصد لا يعتمد ولا يُسقط.
 *
 * ◆ **واللوحةُ بلا كتابةٍ لأحد** — لا فعلَ فيها أصلًا، فالمنحُ رؤيةٌ فقط.
 *
 * التشغيل:  php database/migrations/2027_10_10_align_capabilities_register.php
 * الرجوع :  php database/migrations/2027_10_10_align_capabilities_register.php --revert
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

$SRC = 'INJ-ALIGN-CAP-01';

/* route => [label, anchor(الأب), owner_role, addRoles[], editRoles[], canonical_en, sort, owner_dept, output_doc] */
$S = array(
 'Opportunities/client_need_rfq.php' => array(
    'احتياج العميل وطلب العرض', 'Opportunities/opportunities.php', 12, array(12), array(12),
    'Client Need and RFQ', 205, 'المبيعات والعقود', 'احتياجٌ مرفوعٌ يفتح إصدارَ العرض'),
 'Clients/quotation_lines.php' => array(
    'بنود العروض', 'Clients/quotations.php', 12, array(12), array(12),
    'Quotation Lines', 202, 'المبيعات والعقود', 'بنودُ عرضٍ بإجمالٍ محسوبٍ في الخدمة'),
 'Clients/quotation_negotiation.php' => array(
    'التفاوض ومراجعات العرض', 'Clients/quotations.php', 12, array(12), array(12),
    'Quotation Negotiation Log', 203, 'المبيعات والعقود', 'سجلُّ نسخِ العرضِ ووقائعِ تغييرِها'),
 'Suppliers/supplier_violations.php' => array(
    /* ◆ **الدورُ 8 خارجيٌّ لا داخليّ**: منحتُه أوّلًا الاعتمادَ بحسبانِه مشرفَ
     *   إدارةِ الموردين — و`SupplierPortalGuard` كشف أنه **بوابةُ مورّدٍ
     *   خارجيّ** بقائمةِ سماحٍ ضيقةٍ يردُّ ما عداها 404. فمنحُه اعتمادَ
     *   مخالفةِ مورّدٍ يضع يدَ الخصمِ في يدِ الخاضعِ له. ⇒ **أُزيل**.
     *   والفصلُ يبقى قائمًا: بالفاعلِ داخلَ الدورِ 2 لا بدورَين. */
    'المخالفات والجزاءات', 'Suppliers/settlements.php', 2, array(2), array(2),
    'Supplier Violations and Penalties', 155, 'إدارة الموردين', 'مخالفةٌ معتمَدةٌ أثرُها في التسوية'),
 'Suppliers/supplier_board.php' => array(
    'لوحة إدارة الموردين', 'Suppliers/suppliers.php', 2, array(), array(),
    'Supplier Management Board', 101, 'إدارة الموردين', 'قراءةٌ مشتقّةٌ لحظةَ العرض'),
);

/* code, label, screen, file, write_class */
$A = array(
 array('align.client_need.open',           'تسجيلُ احتياجِ عميلٍ على فرصة',   'احتياج العميل وطلب العرض', 'Opportunities/client_need_rfq.php', 'domain_write'),
 array('align.client_need.submit',         'رفعُ الاحتياجِ ليُتاح العرض',      'احتياج العميل وطلب العرض', 'Opportunities/client_need_rfq.php', 'domain_write'),
 array('align.quotation_line.add',         'إضافةُ بندٍ إلى عرضِ سعر',        'بنود العروض',              'Clients/quotation_lines.php',       'domain_write'),
 array('align.quotation_negotiation.log',  'تسجيلُ واقعةِ تفاوضٍ على عرض',    'التفاوض ومراجعات العرض',   'Clients/quotation_negotiation.php', 'domain_write'),
 array('align.supplier_violation.record',  'رصدُ مخالفةِ مورّد',              'المخالفات والجزاءات',      'Suppliers/supplier_violations.php', 'domain_write'),
 array('align.supplier_violation.approve', 'اعتمادُ مخالفةِ مورّدٍ وجزائها',  'المخالفات والجزاءات',      'Suppliers/supplier_violations.php', 'governance_write'),
 array('align.supplier_violation.waive',   'إسقاطُ مخالفةٍ بسببٍ مكتوب',      'المخالفات والجزاءات',      'Suppliers/supplier_violations.php', 'governance_write'),
);

$routesIn = "'" . implode("','", array_map(function ($x) use ($conn) {
    return $conn->real_escape_string($x); }, array_keys($S))) . "'";

/* ─────────────────────────── الرجوع ─────────────────────────────────────── */
if (in_array('--revert', $argv, true)) {
    $codes = array();
    foreach ($A as $r) { $codes[] = "'" . $conn->real_escape_string($r[0]) . "'"; }
    $conn->query("DELETE FROM `nav09_action_map` WHERE `canonical_code` IN (" . implode(',', $codes) . ")");
    echo "↺ أفعال: {$conn->affected_rows}\n";
    $conn->query("DELETE FROM `nav_canonical` WHERE `route` IN ({$routesIn}) AND `derivation` = '{$SRC}'");
    echo "↺ صفوفٌ كنسية: {$conn->affected_rows}\n";
    $conn->query("DELETE FROM `gov_space_appearances` WHERE `src_note` = '{$SRC}'");
    echo "↺ ظهوراتُ مساحات: {$conn->affected_rows}\n";
    $st = $conn->prepare("DELETE FROM `gov_profile_items` WHERE `seeded_from` = ?");
    $st->bind_param('s', $SRC); $st->execute();
    echo "↺ بنودُ قوالب: {$st->affected_rows}\n"; $st->close();
    foreach (array_keys($S) as $route) {
        $q = $conn->query("SELECT `id` FROM `modules` WHERE `code` = '" . $conn->real_escape_string($route) . "'");
        if ($q && ($m = $q->fetch_row())) {
            $conn->query("DELETE FROM `role_permissions` WHERE `module_id` = " . (int) $m[0]);
            $conn->query("DELETE FROM `modules` WHERE `id` = " . (int) $m[0]);
        }
    }
    echo "↺ أُزيلت وحداتُ القدراتِ الخمسِ وصلاحياتُها\n";
    exit(0);
}

/* ── ① الأفعالُ في القاموس — والفعلُ غيرُ المسجَّلِ يُفشل العقدَ حتمًا ────────── */
$ins = $conn->prepare(
 "INSERT INTO `nav09_action_map`
    (`canonical_code`,`label_ar`,`screen_title`,`canonical_file`,`state`,
     `guard_verified`,`guard_evidence`,`idempotency_verified`,`idempotency_evidence`,
     `uat_verified`,`uat_evidence`,`write_class`,`updated_at`)
  VALUES (?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())
  ON DUPLICATE KEY UPDATE
    `label_ar`=VALUES(`label_ar`), `screen_title`=VALUES(`screen_title`),
    `canonical_file`=VALUES(`canonical_file`), `state`=VALUES(`state`),
    `guard_verified`=VALUES(`guard_verified`), `guard_evidence`=VALUES(`guard_evidence`),
    `idempotency_verified`=VALUES(`idempotency_verified`),
    `idempotency_evidence`=VALUES(`idempotency_evidence`),
    `write_class`=VALUES(`write_class`), `updated_at`=NOW()");
$gEv = 'ems_post_contract + enforce_current_page_view_permission';
$iEv = 'idem_key فريدٌ في الجدول + ems_pc_idem_mark';
$nA = 0; $badA = array();
foreach ($A as $r) {
    $ins->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $gEv, $iEv, $r[4]);
    if ($ins->execute()) { $nA++; } else { $badA[] = $r[0] . ': ' . $ins->error; }
}
$ins->close();
printf("① أفعالٌ مسجَّلة: %d/%d\n", $nA, count($A));
foreach ($badA as $b) { echo "  ✘ {$b}\n"; }

/* ── ② الوحدةُ والصلاحيات ────────────────────────────────────────────────
 * الرؤيةُ تُورَث من الأب · والكتابةُ بالمالكِ المُعلَن أعلاه. */
$modN = 0; $permN = 0;
foreach ($S as $route => $r) {
    list($label, $anchor, $ownerRole, $addRoles, $editRoles) = $r;

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

    /* الرؤيةُ لكلِّ مَن يرى الأب — فالتبويبُ يُرى حيثُ يُرى أبوه */
    $viewers = array();
    $q = $conn->query("SELECT rp.`role_id` FROM `modules` m
                         JOIN `role_permissions` rp ON rp.`module_id` = m.`id`
                        WHERE m.`code` = '" . $conn->real_escape_string($anchor) . "'
                          AND rp.`can_view` = 1");
    while ($q && $x = $q->fetch_row()) { $viewers[] = (int) $x[0]; }
    $viewers = array_unique(array_merge($viewers, array($ownerRole)));

    foreach ($viewers as $role) {
        $add  = in_array($role, $addRoles, true) ? 1 : 0;
        $edit = in_array($role, $editRoles, true) ? 1 : 0;
        $st = $conn->prepare("INSERT INTO `role_permissions`
              (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
              VALUES (?,?,1,?,?,0)
              ON DUPLICATE KEY UPDATE `can_view`=1, `can_add`=VALUES(`can_add`), `can_edit`=VALUES(`can_edit`)");
        $st->bind_param('iiii', $role, $mid, $add, $edit);
        if ($st->execute()) { $permN++; }
        $st->close();
    }
    printf("   %-38s رؤية: %-2d · إضافة: %d · تعديل: %d\n",
           $route, count($viewers), count($addRoles), count($editRoles));
}
printf("② وحدات: %d · منحُ صلاحية: %d\n", $modN, $permN);

/* ── ③ بنودُ القوالبِ — القفلُ الرابعُ الذي يُلغي `role_permissions` كليًّا ──── */
$insP = $conn->prepare(
  "INSERT INTO `gov_profile_items`
     (`company_id`,`profile_id`,`item_kind`,`item_ref`,`allow`,`can_add`,`can_edit`,`can_delete`,`seeded_from`)
   VALUES (?,?, 'screen', ?,?,?,?,?,?)");
$selP = $conn->prepare(
  "SELECT `company_id`,`profile_id`,`allow`,`can_add`,`can_edit`,`can_delete`
     FROM `gov_profile_items` WHERE `item_kind` = 'screen' AND `item_ref` = ?");
$chkP = $conn->prepare(
  "SELECT COUNT(*) FROM `gov_profile_items`
    WHERE `item_kind` = 'screen' AND `item_ref` = ? AND `profile_id` = ?");
$madeP = 0; $skipP = 0; $noneP = array();
foreach ($S as $route => $r) {
    $anchor = $r[1];
    $selP->bind_param('s', $anchor);
    $selP->execute();
    $res = $selP->get_result();
    $n = 0;
    while ($res && $x = $res->fetch_assoc()) {
        $pid = (int) $x['profile_id'];
        $chkP->bind_param('si', $route, $pid);
        $chkP->execute(); $chkP->bind_result($have); $chkP->fetch(); $chkP->free_result();
        if ((int) $have > 0) { $skipP++; $n++; continue; }
        $co = (int) $x['company_id']; $al = (int) $x['allow'];
        /* ◆ **اللوحةُ قراءةٌ محضة** — ولا تُورَث لها كتابةٌ ولو ورثها أبوها */
        $ro = ($route === 'Suppliers/supplier_board.php');
        $ad = $ro ? 0 : (int) $x['can_add'];
        $ed = $ro ? 0 : (int) $x['can_edit'];
        $de = 0;                          /* لا حذفَ في أيٍّ من الخمس */
        $insP->bind_param('iisiiiis', $co, $pid, $route, $al, $ad, $ed, $de, $SRC);
        if ($insP->execute()) { $madeP++; $n++; }
        else { echo "  ✘ قالبُ {$route}/{$pid}: {$insP->error}\n"; }
    }
    if ($n === 0) { $noneP[] = $route . ' (أبوه ' . $anchor . ')'; }
}
$insP->close(); $selP->close(); $chkP->close();
printf("③ بنودُ قوالب: أُضيف %d · مُكرَّرٌ %d\n", $madeP, $skipP);
if ($noneP) {
    echo "  ⚠ بلا بندِ قالب: " . implode(' · ', $noneP) . "\n";
    echo "     ◆ ولا يعني انفتاحًا: أبوه نفسُه خارجَ القوالب — والمسارُ `role_permissions`.\n";
}

/* ── ④ التصنيفُ في المساحاتِ — يُورَث من الأبِ بحرفِه ─────────────────────── */
$q = $conn->query("SELECT COALESCE(MAX(`id`),0) FROM `gov_space_appearances`");
$next = $q ? (int) $q->fetch_row()[0] : 0;
$selS = $conn->prepare(
  "SELECT `space_ar`,`space_kind`,`tab_ar`,`owner_dept_ar`,`owner_kind`,
          `cls`,`ownership`,`decision`,`basis`,`rule_step`,`spaces_count`
     FROM `gov_space_appearances` WHERE `route` = ?");
$chkS = $conn->prepare(
  "SELECT COUNT(*) FROM `gov_space_appearances` WHERE `route` = ? AND `space_ar` = ?");
$insS = $conn->prepare(
  "INSERT INTO `gov_space_appearances`
     (`id`,`space_ar`,`space_kind`,`tab_ar`,`screen_ar`,`route`,`owner_dept_ar`,`owner_kind`,
      `src_class`,`src_ownership`,`src_decision`,`src_note`,`spaces_count`,
      `cls`,`ownership`,`decision`,`basis`,`rule_step`,`updated_at`)
   VALUES (?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, NOW())");
$madeS = 0; $skipS = 0; $noneS = array();
foreach ($S as $route => $r) {
    $label = $r[0]; $anchor = $r[1];
    $selS->bind_param('s', $anchor);
    $selS->execute();
    $res = $selS->get_result();
    $n = 0;
    while ($res && $x = $res->fetch_assoc()) {
        $chkS->bind_param('ss', $route, $x['space_ar']);
        $chkS->execute(); $chkS->bind_result($have); $chkS->fetch(); $chkS->free_result();
        if ((int) $have > 0) { $skipS++; $n++; continue; }
        $next++;
        $cls = (string) $x['cls']; $own = (string) $x['ownership']; $dec = (string) $x['decision'];
        $basis = mb_substr('مورَّثٌ من ' . basename($anchor) . ' — ' . (string) $x['basis'], 0, 255);
        $sc = (int) $x['spaces_count']; $rs = (int) $x['rule_step'];
        /* ◆ **ثمانيةَ عشرَ متغيرًا ⇐ ثمانيةَ عشرَ حرفًا** — وحرفٌ منزاحٌ يقسر `cls` عددًا */
        $insS->bind_param('isssssssssssissssi',
            $next, $x['space_ar'], $x['space_kind'], $x['tab_ar'], $label, $route,
            $x['owner_dept_ar'], $x['owner_kind'],
            $cls, $own, $dec, $SRC, $sc,
            $cls, $own, $dec, $basis, $rs);
        if ($insS->execute()) { $madeS++; $n++; }
        else { echo "  ✘ مساحةُ {$route}/{$x['space_ar']}: {$insS->error}\n"; }
    }
    if ($n === 0) { $noneS[] = $route . ' (أبوه ' . $anchor . ')'; }
}
$selS->close(); $chkS->close(); $insS->close();
printf("④ ظهوراتُ مساحات: أُضيف %d · مُكرَّرٌ %d\n", $madeS, $skipS);
if ($noneS) { echo "  ⚠ بلا تصنيفٍ مورَّث: " . implode(' · ', $noneS) . "\n"; }

/* ── ⑤ الصفُّ الكنسيُّ — اسمٌ معياريٌّ واحدٌ لكلِّ مسار ────────────────────── */
$q = $conn->query("SELECT COALESCE(MAX(`id`),0), COALESCE(MAX(`matrix_row`),0) FROM `nav_canonical`");
list($nextId, $nextRow) = $q ? $q->fetch_row() : array(0, 0);
$nextId = (int) $nextId; $nextRow = (int) $nextRow;
$insC = $conn->prepare(
  "INSERT INTO `nav_canonical`
     (`id`,`route`,`canonical_ar`,`canonical_en`,`level_no`,`level_name`,`group_name`,`sort_no`,
      `nature`,`owner_dept`,`status`,`decision_state`,`application_state`,`policy_domain`,
      `derivation`,`retirement_status`,`current_label`,`current_parent`,`matrix_row`,
      `created_at`,`output_doc`,`placement_kind`,`placement_basis`,`space_class`)
   VALUES (?,?,?,?,2,'2 — العمليات',?,?, 'سجلٌّ تابع',?, 'APPROVED','APPROVED','DEPLOYED',
           'NAVIGATION_NAMING_POSITION', ?, 'ACTIVE', ?, ?, ?, NOW(), ?, 'SINGLE', ?, 'OWNED')
   ON DUPLICATE KEY UPDATE
     `group_name`=VALUES(`group_name`), `sort_no`=VALUES(`sort_no`),
     `canonical_ar`=VALUES(`canonical_ar`), `status`=VALUES(`status`)");
$GRP = array(
    'Opportunities/client_need_rfq.php' => 'العروض والتسعير',
    'Clients/quotation_lines.php'       => 'العروض والتسعير',
    'Clients/quotation_negotiation.php' => 'العروض والتسعير',
    'Suppliers/supplier_violations.php' => 'تعاقد الموردين وتسويتهم',
    'Suppliers/supplier_board.php'      => 'تعاقد الموردين وتسويتهم',
);
$BASIS = array(
    'Opportunities/client_need_rfq.php' => 'تبويبٌ في ملفِّ الفرصةِ — قرارُ الورقة ٠٦: سجلٌّ تابعٌ للفرصة',
    'Clients/quotation_lines.php'       => 'تبويبٌ في ملفِّ العرضِ — قرارُ الورقة ٠٨: البنودُ ليست شاشةً مستقلة',
    'Clients/quotation_negotiation.php' => 'تبويبٌ في ملفِّ العرضِ — قرارُ الورقة ٠٩: سجلٌّ تابعٌ لرأسِ العرض',
    'Suppliers/supplier_violations.php' => 'تبويبٌ في ملفِّ التسويةِ — قرارُ الورقة م١٩: سجلٌّ تابعٌ للتسوية',
    'Suppliers/supplier_board.php'      => 'صفحةُ هبوطٍ للمساحةِ لا بندًا في مجموعة — قرارُ الورقة م٢٣',
);
$madeC = 0; $badC = array();
foreach ($S as $route => $r) {
    list($label, $anchor, $ownerRole, $addRoles, $editRoles, $en, $sort, $dept, $outDoc) = $r;
    $chk = $conn->query("SELECT COUNT(*) FROM `nav_canonical` WHERE `route` = '"
                        . $conn->real_escape_string($route) . "'");
    if ($chk && (int) $chk->fetch_row()[0] > 0) { continue; }
    $nextId++; $nextRow++;
    $grp = $GRP[$route]; $basis = $BASIS[$route];
    /* ثلاثةَ عشرَ متغيرًا ⇐ ثلاثةَ عشرَ حرفًا: i s s s s i s s s s i s s */
    $insC->bind_param('issssissssiss',
        $nextId, $route, $label, $en, $grp, $sort, $dept, $SRC, $label, $grp, $nextRow, $outDoc, $basis);
    if ($insC->execute()) { $madeC++; } else { $badC[] = $route . ': ' . $insC->error; }
}
$insC->close();
printf("⑤ صفوفٌ كنسيةٌ أُضيفت: %d\n", $madeC);
foreach ($badC as $b) { echo "  ✘ {$b}\n"; }

/* ── ⑥ الشهادةُ: صفرُ بندِ تنقّلٍ لهذه الخمس — والقائمةُ كما أُقفلت ────────── */
$navAdded = $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `route` IN ({$routesIn})");
$navAdded = $navAdded ? (int) $navAdded->fetch_row()[0] : -1;
printf("⑥ بنودُ تنقّلٍ لهذه الخمس: **%d** (المستهدَف صفر — أربعةُ تبويباتٍ ولوحةُ هبوط)\n", $navAdded);
foreach (array(12 => 13, 2 => 14) as $role => $target) {
    $q = $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `role_id` = {$role} AND `active` = 1
                        AND `route` NOT IN ('main/role_board.php','chats/index.php')");
    $live = $q ? (int) $q->fetch_row()[0] : -1;
    printf("   دور %-2d: %d بندًا · المستهدَف %d %s\n", $role, $live, $target,
           $live === $target ? '✔' : '✘');
}

ems_migration_recorded(__FILE__, $conn, 0);
