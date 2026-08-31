<?php
/**
 * tools/repair01_owner_close.php — إغلاقُ ملكيّةِ W2-D-01 المؤجَّلة (البند ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` §١·٢: 47 سطحًا `owner_code` فارغٌ — **كلٌّ يُسند أو يُصنَّف
 * بشاهدٍ ⇒ صفر**، والاثنا عشرَ المنصّيّةُ إلى `repair01_platform_capabilities`.
 *
 * ◆ قواعدُ الإسنادِ — كلُّها من قنواتٍ مقيسةٍ لا من تشابهِ حروف:
 *   `FC3_PLATFORM_CAPABILITY` حكمُ ملكيّتِه PLATFORM_SHARED مسجَّلٌ سلفًا
 *   `FC3_ENTITY_LEGAL_OWNER`  كيانُه له مالكٌ قانونيٌّ غالبٌ في السجلّ
 *   `FC3_DIR_FAMILY`          مجلّدُ مسارِه ملكيّتُه المقيسةُ غالبةٌ (عمود route)
 *   `FC3_SECTOR_HEAD`         كيانُه تشغيليٌّ موزَّعٌ بين فرعَي DEP-11 ⇒ رأسُ القطاع
 *   `FC3_FUNCTION_FAMILY`     عائلةُ وظيفتِه المسمّاةُ مملوكةٌ لإدارةٍ واحدة
 *   `FC3_SUCCESSOR`           توأمُه الحيُّ الخلَفُ مملوكٌ فيتبعه
 * ◆ ⛔ ولا يُلفَّق مالكٌ تقنيٌّ شخصًا للقدراتِ المنصّيّة — ذلك حاجزُ مالكٍ
 *   (§٥ البند ١) ويُكتب `NEEDS_GOVERNING_SOURCE` مُعلَنًا لا فارغًا.
 *
 * التشغيل: php tools/repair01_owner_close.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$DRY = in_array('--dry', $argv, true);
$SNAP = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

/* ── جدولُ الإسناد: ملفّ ⇒ [مالك، قاعدة، شاهد، قدرة] ────────────────────── */
$CAP_BLOCK = 'NEEDS_GOVERNING_SOURCE (FINAL_CLOSE §5 ب1: مالك تقني مسمى شخصا — حاجز مالك قائم)';
$D = array(
 // القدرات المنصية — حكم ملكيتها PLATFORM_SHARED مسجل سلفا في ownership_verdict
 'main/global_search.php'          => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ البحث المؤسسي احدى قدرات AMD-01 §4-7 المسماة','CAP-SEARCH'),
 'Portal/notifications.php'        => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ الاشعارات المشتركة قدرة مسماة في AMD-01 §4-7','CAP-NOTIFY'),
 'Portal/my_requests.php'          => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ توجيه الطلبات قدرة مسماة في AMD-01 §4-7','CAP-REQ-ROUTE'),
 'Portal/visibility_audit.php'     => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ قدرة الصلاحيات المسماة — كيانه role_permissions بنية صرفة INFRA_ONLY','CAP-PERM'),
 'Portal/visibility_simulator.php' => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ قدرة الصلاحيات المسماة — كيانه role_permissions بنية صرفة INFRA_ONLY','CAP-PERM'),
 'Reports/guard_denials_report.php'=> array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ قدرة الامان المسماة — يعرض guard_denials وهو كيت مشترك','CAP-SEC'),
 'Portal/workspace.php'            => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ قشرة مساحة العمل المشتركة (AMD-01 §4-7: المشتركة عند وجودها)','CAP-WORKSPACE'),
 'Portal/dept_board.php'           => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ لوحة ادارة معممة تصير لكل الادارات — قشرة مساحة العمل','CAP-WORKSPACE'),
 'Portal/my_kpi.php'               => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ مؤشرات شخصية معممة — قشرة مساحة العمل','CAP-WORKSPACE'),
 'Portal/portal_elements.php'      => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ كيانه portal_elements بنية صرفة INFRA_ONLY — قشرة مساحة العمل','CAP-WORKSPACE'),
 'main/user_profile.php'           => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ ملف المستخدم الشخصي معمم لكل الادوار — قشرة مساحة العمل','CAP-WORKSPACE'),
 'main/soon.php'                   => array('PLTF','FC3_PLATFORM_CAPABILITY','حكم الملكية PLATFORM_SHARED مسجل؛ صفحة قريبا المعممة — كيانه nav09_file_map بنية ملاحية','CAP-WORKSPACE'),
 // الموظفون
 'Employees/employee_card.php'        => array('DEP-07','FC3_DIR_FAMILY','مجلد Employees ملكيته المقيسة DEP-07=16 صفا بلا منازع؛ كيانه user_capacities بلا مالك مسجل',''),
 'Employees/showcontractemployee.php' => array('DEP-07','FC3_DIR_FAMILY','مجلد Employees ملكيته المقيسة DEP-07=16 صفا بلا منازع؛ عرض عقد موظف بلا كيان مقيس',''),
 // الاسطول والاصول
 'Equipments/equipments_drivers.php'  => array('DEP-04','FC3_DIR_FAMILY','مجلد Equipments ملكيته المقيسة DEP-04=11 مقابل 3؛ قارئ LIVE_READ لكيان operations الموزع',''),
 'Equipments/fleet_failures.php'      => array('DEP-04','FC3_DIR_FAMILY','مجلد Equipments ملكيته المقيسة DEP-04=11؛ قارئ LIVE_READ — كيانه timesheet_failure_hours مالكه القانوني DEP-05 (SCR-0012) قراءة لا ملكا',''),
 'Equipments/select_project.php'      => array('DEP-04','FC3_DIR_FAMILY','مجلد Equipments ملكيته المقيسة DEP-04=11؛ منتقي مساعد بلا كيان مقيس',''),
 'Fleet/readiness_cert.php'           => array('DEP-04','FC3_DIR_FAMILY','مجلد Fleet ملكيته المقيسة DEP-04=9 بلا منازع؛ قارئ LIVE_READ — كيانه readiness_lines مالكه DEP-01 (SCR-0047) قراءة لا ملكا',''),
 'Reports/equipments_reports.php'     => array('DEP-04','FC3_ENTITY_LEGAL_OWNER','كيانه equipments مالكه الغالب DEP-04 (SCR-0086 OWN_FACT)؛ تبويب تقارير قارئ',''),
 // المالية
 'Finance/daily_pricing_fin.php'      => array('DEP-05','FC3_DIR_FAMILY','مجلد Finance ملكيته المقيسة DEP-05=67؛ والتسعير اليومي وظيفة مالية بقرار مسجل (Daily Pricing) — كيانه contract_price_terms مالكه DEP-01 كتابة تسعير بعقد',''),
 // الحوكمة
 'Governance/read_log.php'            => array('DEP-08','FC3_DIR_FAMILY','مجلد Governance ملكيته المقيسة DEP-08=49؛ سجل قراءة الحساس سطح امني حوكمي (Security Log الرابع)',''),
 'Timesheet/aprovment.php'            => array('DEP-08','FC3_SUCCESSOR','توأمه الحي الخلف Approvals/hours_approval.php مملوك DEP-08؛ كيانه unit_entries يقرؤه LIST اعتمادا لا ملكا',''),
 // الصيانة
 'Maintenance/equipment_hours_preventive.php' => array('DEP-14','FC3_DIR_FAMILY','مجلد Maintenance ملكيته المقيسة DEP-14=11؛ قارئ ساعات للوقاية — كيانه unit_entries مملوك لغيره قراءة',''),
 'Maintenance/failure_report.php'     => array('DEP-14','FC3_ENTITY_LEGAL_OWNER','كيانه mnt_order اسطحه المملوكة كلها DEP-14؛ ومجلد Maintenance ملكيته المقيسة DEP-14=11 — شاهدان متوافقان',''),
 'Maintenance/return_to_service.php'  => array('DEP-14','FC3_DIR_FAMILY','مجلد Maintenance ملكيته المقيسة DEP-14=11؛ اعادة للخدمة بعد الاصلاح — كيانه جدول عتبات حملة W07',''),
 // القوى التشغيلية
 'movement/move_oprators.php'         => array('DEP-13','FC3_FUNCTION_FAMILY','يدير اسناد المشغلين وهو وظيفة القوى التشغيلية؛ كيانه equipments شراكة DEP-04/DEP-13 والوجه المشغلي لصف DEP-13 (SCR-0449)',''),
 // قطاع التشغيل — رأسه DEP-11 وكيانه operations/timesheet موزع بين فرعيه
 'movement/project_drivers.php'  => array('DEP-11','FC3_SECTOR_HEAD','مجلد movement ملكيته المقيسة DEP-11=2؛ كيانه operations موزع بين فرعي DEP-11 (DEP-12/WS-MY بواحد) فيسند لراس القطاع',''),
 'Reports/deliy.php'             => array('DEP-11','FC3_SECTOR_HEAD','تقرير يومية تشغيل قارئ LIVE_READ لكيان operations الموزع بين فرعي DEP-11 — يسند لراس القطاع',''),
 'Reports/deriver.php'           => array('DEP-11','FC3_SECTOR_HEAD','تقرير سائقين قارئ LIVE_READ لكيان operations الموزع بين فرعي DEP-11 — يسند لراس القطاع',''),
 'Timesheet/view_timesheet.php'  => array('DEP-11','FC3_SECTOR_HEAD','عرض دوام قارئ LIVE_READ لكيان operations الموزع — يسند لراس القطاع DEP-11',''),
 'Projects/project_profile.php'  => array('DEP-11','FC3_SECTOR_HEAD','ملف تشغيلة/مشروع قارئ LIVE_READ لكيان operations الموزع — يسند لراس القطاع DEP-11',''),
 'Reports/projects_reports.php'  => array('DEP-11','FC3_SECTOR_HEAD','تبويب تقارير مشاريع قارئ لكيان project الموزع (DEP-01/08/11 بواحد) والحقيقة تشغيلية — راس القطاع',''),
 'Reports/driverAndsupplerscontract.php' => array('DEP-11','FC3_SECTOR_HEAD','تقرير عقود سائقين/موردين قارئ لكيان timesheet الموزع (DEP-01/05/11 بواحد) والحقيقة تشغيلية — راس القطاع',''),
 'Reports/new_reports.php'       => array('DEP-11','FC3_SECTOR_HEAD','تبويب تقارير قارئ لكيان timesheet الموزع — راس القطاع DEP-11',''),
 'Reports/timesheet_reports.php' => array('DEP-11','FC3_SECTOR_HEAD','تبويب تقارير دوام قارئ لكيان timesheet الموزع — راس القطاع DEP-11',''),
 'Reports/timesheetdeliy.php'    => array('DEP-11','FC3_SECTOR_HEAD','تقرير دوام يومي قارئ لكيان timesheet الموزع — راس القطاع DEP-11',''),
 // العقود
 'Reports/contract_report.php'   => array('DEP-01','FC3_ENTITY_LEGAL_OWNER','كيانه contracts مالكه الغالب DEP-01 بستة اسطح OWN_FACT مقابل واحد لغيره',''),
 'Reports/contractall.php'       => array('DEP-01','FC3_ENTITY_LEGAL_OWNER','كيانه contracts مالكه الغالب DEP-01 بستة اسطح OWN_FACT مقابل واحد لغيره',''),
 // المخازن
 'Procurement/wh_returns.php'    => array('DEP-17','FC3_FUNCTION_FAMILY','عائلة wh_ المقيسة سبعة اسطح DEP-17 مقابل واحد؛ مرتجعات مخزنية تكتب proc_stock_move',''),
 // البلاغات
 'Tickets/admin_close.php'            => array('DEP-10','FC3_ENTITY_LEGAL_OWNER','كيانه ticket_workstreams مالكه الوحيد DEP-10 (SCR-0603)؛ ومجلد Tickets ملكيته المقيسة DEP-10=19 — شاهدان',''),
 'Tickets/inquiry.php'                => array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19 بلا منازع؛ استعلام تذاكر',''),
 'Tickets/intake_classify.php'        => array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19؛ يكتب ticket_events وهي كيان دورة التذكرة',''),
 'Tickets/ticket_categories_config.php'=> array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19؛ ضبط وحدة البلاغات — حبته SHARED_KIT ضبط لا معاملة',''),
 'Tickets/ticket_escalation_config.php'=> array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19؛ ضبط تصعيد البلاغات — SHARED_KIT',''),
 'Tickets/ticket_sla_config.php'      => array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19؛ ضبط اتفاقيات الخدمة — SHARED_KIT',''),
 'Tickets/ticket_types_config.php'    => array('DEP-10','FC3_DIR_FAMILY','مجلد Tickets ملكيته المقيسة DEP-10=19؛ ضبط انواع البلاغات — SHARED_KIT',''),
 'Tickets/watchtower.php'             => array('DEP-10','FC3_FUNCTION_FAMILY','برج مراقبة دورة التذاكر (TKT-17 · WatchTowerService في نطاق Tickets)؛ يكتب fin_notifications المملوكة قانونا لDEP-05 ناشرا بعقد لا مالكا',''),
);

/* ── أسماء الإدارات لملء owner_role ─────────────────────────────────────── */
$names = array('PLTF' => 'قدرة منصية مشتركة');
$r = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($x = $r->fetch_assoc()) $names[$x['canonical_code']] = $x['name_ar'];

/* ── القدراتُ المنصّيّةُ الستّ ──────────────────────────────────────────── */
$CAPS = array(
 'CAP-SEARCH'    => 'البحث المؤسسي',
 'CAP-NOTIFY'    => 'الاشعارات المشتركة',
 'CAP-REQ-ROUTE' => 'توجيه الطلبات',
 'CAP-PERM'      => 'قدرة الصلاحيات (عرض ومحاكاة)',
 'CAP-SEC'       => 'قدرة الامان (تقرير الحجب)',
 'CAP-WORKSPACE' => 'قشرة مساحة العمل المشتركة',
);

echo "═══ البند ③ — إغلاقُ ملكيّةِ W2-D-01 المؤجَّلة · لقطة $SNAP" . ($DRY ? ' · DRY' : '') . " ═══\n";
$n0 = (int)$conn->query("SELECT COUNT(*) c FROM repair01_screen_registry WHERE owner_code IS NULL OR owner_code=''")->fetch_assoc()['c'];
echo "  قبلَ الإسناد — بلا مالك: $n0\n";

$r = $conn->query("SELECT screen_id, route, owner_code, owner_rule FROM repair01_screen_registry WHERE owner_code IS NULL OR owner_code='' ORDER BY route");
$rows = array(); while ($x = $r->fetch_assoc()) $rows[] = $x;
$missing = array(); $done = 0; $byRule = array(); $capScreens = array();

$insLog = $conn->prepare("INSERT INTO repair01_owner_close (screen_id, route, before_owner, before_rule, after_owner, assign_rule, witness, capability, snapshot_id, applied_at) VALUES (?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE after_owner=VALUES(after_owner)");
$upd = $conn->prepare("UPDATE repair01_screen_registry SET owner_code=?, owner_role=?, owner_rule=?, w2_why=? WHERE screen_id=?");

foreach ($rows as $x) {
    $route = $x['route'];
    if (!isset($D[$route])) { $missing[] = $route; continue; }
    list($owner, $rule, $wit, $cap) = $D[$route];
    $byRule[$rule] = (isset($byRule[$rule]) ? $byRule[$rule] : 0) + 1;
    if ($cap !== '') $capScreens[$cap][] = $route;
    if (!$DRY) {
        $insLog->bind_param('sssssssss', $x['screen_id'], $route, $x['owner_code'], $x['owner_rule'], $owner, $rule, $wit, $cap, $SNAP);
        if (!$insLog->execute()) { echo "  ✘ قيد {$route}: {$conn->error}\n"; continue; }
        $role = $names[$owner]; $orule = 'FINAL_CLOSE3:' . $rule;
        $why = mb_substr($wit, 0, 400);
        $upd->bind_param('sssss', $owner, $role, $orule, $why, $x['screen_id']);
        if (!$upd->execute()) { echo "  ✘ إسناد {$route}: {$conn->error}\n"; continue; }
    }
    $done++;
}

/* ── تسجيلُ القدراتِ المنصّيّةِ الستِّ في سجلِّها المستقلّ ───────────────── */
if (!$DRY) {
    $insCap = $conn->prepare("INSERT INTO repair01_platform_capabilities
        (capability_code, name_ar, tech_owner, policy_owner, last_closed, first_open, blocker, blocker_level, blocker_valid, resume_point, next_action, moved_from, moved_why, snapshot_id, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE moved_why=VALUES(moved_why), snapshot_id=VALUES(snapshot_id), updated_at=NOW()");
    // capability_code ليس مفتاحا فريدا في المخطط — نظف اولا صفوف الجولة نفسها ثم ادرج
    $conn->query("DELETE FROM repair01_platform_capabilities WHERE moved_from='FINAL_CLOSE-3'");
    foreach ($CAPS as $code => $nm) {
        $screens = isset($capScreens[$code]) ? implode(' · ', $capScreens[$code]) : '';
        $lastClosed = 'FINAL_CLOSE ③: أسطحها مسندة اليها بشاهد';
        $firstOpen  = 'تسمية مالك تقني شخصا';
        $blk = 'FINAL_CLOSE §5 ب1: 12 سطحا بانتظار مالك تقني مسمى شخصا';
        $lvl = 'OWNER_BLOCKER';
        $val = 'صحيح — التسمية قرار مالك ولا تلفق';
        $rp  = 'أسطحها: ' . mb_substr($screens, 0, 200);
        $na  = 'عند صدور التسمية: تعبئة tech_owner/policy_owner واعتماد تبرير RPR-02 #13';
        $mf  = 'FINAL_CLOSE-3';
        $mw  = 'البند ③: تصنيف اسطح PLATFORM_SHARED الى سجل القدرات — ' . $screens;
        $insCap->bind_param('ssssssssssssss', $code, $nm, $CAP_BLOCK, $CAP_BLOCK, $lastClosed, $firstOpen, $blk, $lvl, $val, $rp, $na, $mf, $mw, $SNAP);
        if (!$insCap->execute()) echo "  ✘ قدرة $code: {$conn->error}\n";
    }
    echo "  ✔ سُجِّلت القدراتُ المنصّيّةُ الستُّ في repair01_platform_capabilities\n";
}

$n1 = (int)$conn->query("SELECT COUNT(*) c FROM repair01_screen_registry WHERE owner_code IS NULL OR owner_code=''")->fetch_assoc()['c'];
echo "  أُسند/صُنِّف: $done" . ($missing ? ' · ⛔ بلا حكمٍ في الجدول: ' . count($missing) . ' — ' . implode(' · ', $missing) : '') . "\n";
foreach ($byRule as $k => $v) echo "     $k = $v\n";
echo "  بعدَ الإسناد — بلا مالك: $n1\n";
echo ($n1 === 0 && !$missing) ? "✔ البند ③ بلغ: صفرُ سطحٍ بلا مالك\n" : ($DRY ? "◆ DRY — لم يُكتب شيء\n" : "⛔ بقي $n1\n");
exit(($n1 === 0 && !$missing) || $DRY ? 0 : 1);
