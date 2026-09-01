<?php
/**
 * 2028_03_03_govui_wiring_rulings.php — أحكامُ الوصلِ: كلُّ هدفٍ إلى سطحِه
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_placements(route,screen_id,placement_type) + log:govui_wiring_log
 *
 * ◆ **لماذا قبلَ التسمية** (§22 · `FIND ROOT CAUSE`): إعادةُ تسميةِ سطحٍ موصولٍ
 *   بالخطأ **تُثبّت الانزياحَ**؛ فالوصلُ يُحسم أوّلًا ثمَّ يُسمّى ما استقرّ.
 *
 * ◆ **وكلُّ حكمٍ بشاهدِه المكتوبِ في صفِّه** — ⛔ ولا حكمَ بلا شاهد. والشواهدُ
 *   ثلاثةُ أصنافٍ لا رابعَ لها:
 *   ① **قيدٌ في `nav_canonical`** (دمجٌ سابقٌ مسجَّلٌ بنصِّه).
 *   ② **اسمُ السطحِ في `repair01_screen_registry`** مطابقًا للحاكمِ حرفًا أو جذرًا.
 *   ③ **نصُّ بطاقةِ الدليلِ نفسِها** (الغرضُ · موقعُها في الدورة · نوعُ الشاشة).
 *
 * ◆ **والمعرِّفُ لا يُمَسّ**: `target_id` ثابتٌ في كلِّ صفٍّ — يتغيّر **موضعُه**
 *   لا هويّتُه (§4 · `TARGET_LINEAGE_BROKEN_BY_RENAME = 0`).
 *
 * التشغيل: php database/migrations/2028_03_03_govui_wiring_rulings.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$conn->query("CREATE TABLE IF NOT EXISTS `govui_wiring_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_id` varchar(24) NOT NULL,
  `old_route` varchar(160) DEFAULT NULL,
  `old_screen_id` varchar(12) DEFAULT NULL,
  `old_type` varchar(16) NOT NULL DEFAULT '',
  `new_route` varchar(160) DEFAULT NULL,
  `new_screen_id` varchar(12) DEFAULT NULL,
  `new_type` varchar(16) NOT NULL DEFAULT '',
  `witness` varchar(400) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gwl_t` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_UI_EXEC §9: قيدُ رجوعِ أحكامِ الوصل'");

/* target_id => [route|null, placement_type, witness] — والمعرِّفُ ثابتٌ في كلِّ صفّ */
$R = array(
    /* ── DEP-10 · انزياحُ ثلاثةِ أهدافٍ في مركزِ البلاغات ───────────────── */
    'NT-DEP-10-002' => array('Tickets/tickets_list.php', 'MENU_ITEM',
        'شاهد①: nav_canonical يقيّد أنَّ Tickets/dept_inbox.php («صندوق بلاغات الإدارة» حرفًا) «يُدمج في Tickets/tickets_list.php تبويبًا موجَّهة لإدارتي — والملفُّ يبقى مُحوِّلًا»'),
    'NT-DEP-10-003' => array('Tickets/ticket_sla_config.php', 'MENU_ITEM',
        'شاهد③: غرضُ البطاقة «مصفوفة SLA: زمن الاستجابة وزمن الحل وسلّم التصعيد لكل تركيبة نوع × أولوية × إدارة» — وهو موضوعُ ticket_sla_config حرفًا؛ وكان موصولًا بكتالوجِ الأنواع (الهدف 05)'),
    'NT-DEP-10-004' => array('Tickets/ticket_contextual_open.php', 'MENU_ITEM',
        'شاهد②: اسمُ السطحِ في سجلِّ الشاشات «أبلغ عن مشكلة من هذه الشاشة» = «الإبلاغ السياقي من داخل الشاشة»؛ وكان موصولًا بـtkt_parties'),
    /* ── DEP-14 · انزياحُ ثلاثةِ أهدافٍ في الصيانة ─────────────────────── */
    'NT-DEP-14-010' => array('Maintenance/work_orders.php', 'TAB_CHILD',
        'شاهد③: «بنود أمر العمل» ابنُ أمرِ العملِ (الهدف 08 · Maintenance/work_orders.php) لا ابنُ طلبِ صرفِ القطع'),
    'NT-DEP-14-011' => array('Maintenance/part_requests.php', 'MENU_ITEM',
        'شاهد②: سجلُّ الشاشات يسمّي part_requests «طلبات صرف القطع» = «طلب صرف القطع لأمر العمل»؛ وكان موصولًا بالإصلاحِ الخارجيِّ (الهدف 12)'),
    'NT-DEP-14-013' => array('Maintenance/preventive_plans.php', 'MENU_ITEM',
        'شاهد②: سجلُّ الشاشات يسمّي preventive_plans «خطط الصيانة الوقائية» = «الخطة الوقائية بالساعات»؛ وكان موصولًا بالعنايةِ اليوميّةِ (الهدف 14)'),
    /* ── DEP-02 · هدفانِ على مسارٍ واحد ────────────────────────────────── */
    'NT-DEP-02-018' => array('Suppliers/shares_coverage.php', 'MENU_ITEM',
        'شاهد②: سجلُّ الشاشات يسمّي shares_coverage «سجل الحصص والتغطية التعاقدية» = «قياس التغطية والعجز والفائض»؛ وكان يتقاسم supplier_entitlements مع الهدف 20'),
    /* ── مراحلُ داخلَ مستندٍ واحد: بابٌ واحدٌ والبقيّةُ لا تُبنَد (§8 · §9) ─ */
    'NT-DEP-04-004' => array(null, 'DIRECT_ONLY',
        'شاهد③: «موقعها في الدورة» يجعلها مرحلةً تاليةً على مستندِ الإدخالِ نفسِه — والسطحُ واحدٌ (fleet/asset_intake.php) فلا بندَ ثانيًا لمسارٍ واحد'),
    'NT-DEP-04-005' => array(null, 'DIRECT_ONLY',
        'شاهد③: أمرُ التفتيشِ مرحلةٌ داخلَ مستندِ إدخالِ الأصل — سطحٌ واحدٌ بأربعِ مراحل'),
    'NT-DEP-04-011' => array(null, 'DIRECT_ONLY',
        'شاهد③: التفعيلُ وإعادةُ الخدمةِ خاتمةُ مستندِ الإدخال — سطحٌ واحدٌ بأربعِ مراحل'),
    'NT-DEP-04-013' => array(null, 'DIRECT_ONLY',
        'شاهد③: حركةُ الموقعِ والمشروعِ سجلُّ حركةٍ داخلَ شاشةِ التخصيصِ (الهدف 12) — مسارٌ واحد'),
    'NT-DEP-04-020' => array(null, 'DIRECT_ONLY',
        'شاهد③: الجاهزيّةُ الشهريّةُ منظرٌ داخلَ الملخّصِ التشغيليِّ الشهريِّ (الهدف 19) — مسارٌ واحد'),
    'NT-DEP-04-022' => array(null, 'DIRECT_ONLY',
        'شاهد③: الخروجُ الدائمُ نوعُ خروجٍ داخلَ مستندِ الخروجِ نفسِه (الهدف 21) — مسارٌ واحد'),
    'NT-DEP-05-023' => array(null, 'DIRECT_ONLY',
        'شاهد③: إقفالُ الفترةِ فعلٌ داخلَ شاشةِ التقويمِ المحاسبيِّ للفتراتِ (الهدف 04) — مسارٌ واحد'),
    'NT-WS-MY-007' => array(null, 'TAB_CHILD',
        'شاهد②: سجلُّ الشاشات يصنّف Settings/change_password.php تبويبًا، ويُبلَغ من بطاقةِ الملفِّ الشخصيِّ main/profile.php — لا بندَ قائمةٍ مستقلًّا'),
    /* ── هدفٌ مستقلٌّ لم يُبنَ: يُعلَن ولا يُلبَس ثوبَ غيرِه ───────────────── */
    'NT-DEP-07-003' => array('', 'NOT_BUILT',
        'شاهد②+③: workforce/hr_workforce_report.php سطحُ **تقريرٍ** يخدم الهدف 24 «تقرير القوى العاملة» باسمِه المخزَّن؛ و«خطة القوى العاملة» سطحُ تخطيطٍ مستقلٌّ لا وجودَ له على القرص — فيُعلَن NOT_BUILT ولا يُسمّى التقريرُ باسمِ الخطة'),
    /* ── EX-DVP · ستُّ مواضعَ: ثلاثةٌ لها أسطحُها ونظائرُ ثلاثةٍ إسقاطًا (§13) ── */
    'NT-EX-DVP-001' => array('Portal/vp_dashboard.php', 'MENU_ITEM',
        'شاهد②: Portal/vp_dashboard.php مبنيٌّ على القرصِ ومسجَّلٌ «لوحة قيادة النائب» وغيرُ موصولٍ بأيِّ هدف'),
    'NT-EX-DVP-005' => array('Portal/vp_monthly_review.php', 'MENU_ITEM',
        'شاهد②: Portal/vp_monthly_review.php مسجَّلٌ «المراجعة الشهرية للنائب» — مطابقةٌ حرفيّةٌ للحاكم'),
    'NT-EX-DVP-006' => array('Portal/vp_approval_inbox.php', 'MENU_ITEM',
        'شاهد②: Portal/vp_approval_inbox.php مسجَّلٌ «صندوق اعتمادات النائب» — مطابقةٌ حرفيّةٌ للحاكم'),
    'NT-EX-DVP-007' => array('Portal/exec_financial_approvals.php', 'PROJECTION',
        'شاهد③: بطاقةُ النوّابِ نصًّا «بمحرك واحد Deputy_Role/Scope — لا نظام اعتماد مستقلًّا لكل نائب: نفس المحرك ونفس الشاشات» — فالسطحُ إسقاطٌ بنطاقِ النائبِ لا نسخةٌ ثانية (§13: «ولا تنسخ … اذا كان المطلوب Projection فقط»)'),
    'NT-EX-DVP-008' => array('Portal/exec_redline_breaches.php', 'PROJECTION',
        'شاهد③: «الخط الأحمر ضمن الصلاحية — المسار تحدده القاعدة لا الشاشة (ن08)» — سطحُ التجاوزاتِ نفسُه بنطاقِ النائب'),
    'NT-EX-DVP-009' => array('Portal/exec_escalations.php', 'PROJECTION',
        'شاهد③: «التصعيد ضمن النطاق … Escalated_To_CEO يُسجَّل عند التجاوز (ن09)» — سطحُ التصعيداتِ نفسُه بنطاقِ النائب'),
);

$sel = $conn->prepare("SELECT route, screen_id, placement_type FROM nav_placements WHERE target_id = ?");
$log = $conn->prepare("INSERT INTO govui_wiring_log
    (target_id, old_route, old_screen_id, old_type, new_route, new_screen_id, new_type, witness)
    VALUES (?,?,?,?,?,?,?,?)");
if (!$sel || !$log) { exit("⛔ prepare: {$conn->error}\n"); }

$n = 0; $skip = 0;
foreach ($R as $tid => $rule) {
    list($route, $type, $witness) = $rule;
    $sel->bind_param('s', $tid); $sel->execute();
    $cur = $sel->get_result()->fetch_assoc();
    if (!$cur) { echo "  ⚠ لا موضعَ لـ{$tid} — يُتخطّى\n"; $skip++; continue; }

    $newRoute = $cur['route']; $newScreen = $cur['screen_id'];
    if ($route === '') { $newRoute = null; $newScreen = null; }   /* NOT_BUILT: يُفرَّغ الموضع */
    elseif ($route !== null) {
        $st = $conn->prepare("SELECT screen_id, route FROM repair01_screen_registry WHERE LOWER(route) = LOWER(?)");
        $st->bind_param('s', $route); $st->execute();
        $g = $st->get_result()->fetch_assoc(); $st->close();
        if (!$g) { echo "  ⛔ {$tid}: لا صفَّ في سجلِّ الشاشاتِ لـ{$route}\n"; $skip++; continue; }
        $newRoute = $g['route']; $newScreen = $g['screen_id'];
    }
    $up = $conn->prepare("UPDATE nav_placements SET route = ?, screen_id = ?, placement_type = ?,
                                 source_ref = CONCAT(LEFT(source_ref,120), ' · GOV_UI_EXEC§9')
                           WHERE target_id = ?");
    $up->bind_param('ssss', $newRoute, $newScreen, $type, $tid);
    if (!$up->execute()) { exit("⛔ update {$tid}: {$conn->error}\n"); }
    $up->close();
    $log->bind_param('ssssssss', $tid, $cur['route'], $cur['screen_id'], $cur['placement_type'],
        $newRoute, $newScreen, $type, $witness);
    $log->execute();
    printf("  ✔ %-16s %-11s %s\n", $tid, $type, $newRoute === null ? '— بلا مسار' : $newRoute);
    $n++;
}
echo "المطبَّق: {$n} حكمَ وصلٍ · المتخطّى: {$skip}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
