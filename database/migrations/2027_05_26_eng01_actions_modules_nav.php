<?php
/**
 * 2027_05_26_eng01_actions_modules_nav.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · التسجيلُ قبلَ البناء — الأفعالُ والوحداتُ وروابطُ التنقّل
 * ───────────────────────────────────────────────────────────────────────────
 * «◆ وكلُّ فعلٍ يُسجَّل في قاموسِ الأفعالِ قبلَ شاشتِه — وإلا أُغلقت الشاشةُ ولم تعمل»
 * «◆ ووحدةُ صلاحياتٍ لكلِّ شاشةٍ قبلَ رابطِها»
 *
 * الموضعُ من GOV-24 §٥-١١ «نراقب الأحداثَ والمهام» بمجموعاتِها الثلاث:
 *   ناقلُ الأحداث : صندوق الأحداث الصادر · تسليمات الأحداث وحالاتها · لوحة الناقل
 *   طابورُ المهام : طابور المهام · جدولة المهام الدورية
 *   الاستعادة     : الاستعادة ومحضرها
 *
 * ◆ فروقٌ عن الوثيقةِ يفرضها المخططُ الحيّ (TSP-0003 — والمخطّطُ أصدق):
 *  ① ترقيمُ المراحل: GOV-24 تجعلها المرحلةَ ٨، والحيُّ لدورِ الحوكمة (15) يحمل
 *    عشرَ مراحلَ (0..9) بعناوينَ من قَصّةٍ أقدمَ من الوثيقة. فتُضاف مرحلةً ١٠
 *    — وهي الأخيرةُ في الحالين كما في الوثيقة — بعنوانِ الوثيقةِ فعلًا كما هو.
 *  ② مسارُ الملفات: TS-01 وGOV-24 تذكرانِ الاسمَ مجرَّدًا (bus_outbox.php)،
 *    والحيُّ يضع شاشاتِ الحوكمةِ في Governance/ والماليةَ في Finance/.
 *  ③ شاشتانِ من العشرِ أفعالٍ خارجَ GOV-24 (asset_hours_link · depr_run) لأن
 *    GOV-24 سايدبارُ الحوكمةِ وحدَه. وموضعُهما من الحيِّ لا من اجتهاد:
 *    مجموعةُ «الإهلاك» القائمةُ لدورِ المالية 19 (link_groups#3666).
 *  ④ فعلٌ حاديَ عشرَ (depr.reverse) يفرضه الحيُّ: أربعةٌ وعشرون فعلًا ماليًّا
 *    كلُّها بعكسٍ مسمًّى، وdepr.run ماليٌّ فلا يُسجَّل بلا عكس.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

const R_GOV = 15;   // إدارة الصلاحيات — مالكُ سايدبار الحوكمة (GOV-24)
const R_FIN = 19;   // مدير الإدارة المالية — مالكُ مجموعة الإهلاك
const GRP_DEPR = 3666; // link_groups «الإهلاك» — stage 4 «رابعًا: التكلفة والتحميل»

echo "\n═══ ENG-01 · التسجيلُ قبلَ البناء ═══\n";

// ═══════════════════════════════════════════════════════════════════════════
// ① مجموعاتُ السايدبار الثلاثُ — بأسمائِها من GOV-24 حرفًا
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ① مجموعاتُ المرحلةِ من GOV-24 §٥-١١\n";
$STAGE_NO    = 10;
$STAGE_TITLE = 'عاشرًا: نراقب الأحداثَ والمهام';
$GROUPS = [
    'n9s16_10_32_r15' => ['name' => 'ناقلُ الأحداث', 'icon' => 'fa fa-tower-broadcast', 'ord' => 1032],
    'n9s16_10_33_r15' => ['name' => 'طابورُ المهام', 'icon' => 'fa fa-list-check',      'ord' => 1033],
    'n9s16_10_34_r15' => ['name' => 'الاستعادة',      'icon' => 'fa fa-clock-rotate-left','ord' => 1034],
];
$gid = [];
foreach ($GROUPS as $code => $g) {
    $st = $conn->prepare("SELECT id FROM `link_groups` WHERE `group_code`=? AND `owner_role_id`=?");
    $r15 = R_GOV; $st->bind_param('si', $code, $r15); $st->execute();
    $row = $st->get_result()->fetch_row(); $st->close();
    if ($row) { $gid[$code] = (int) $row[0]; echo "   · $code موجودٌ سلفًا (id={$row[0]})\n"; continue; }
    $st = $conn->prepare(
        "INSERT INTO `link_groups` (`name`,`group_code`,`owner_role_id`,`icon`,`display_order`,`stage_no`,`stage_title`,`is_active`)
         VALUES (?,?,?,?,?,?,?,1)"
    );
    $rid = R_GOV; $sn = $STAGE_NO;
    $st->bind_param('ssisiis', $g['name'], $code, $rid, $g['icon'], $g['ord'], $sn, $STAGE_TITLE);
    $st->execute(); $gid[$code] = (int) $conn->insert_id; $st->close();
    echo "   ✔ $code → «{$g['name']}» (id={$gid[$code]}) stage=$STAGE_NO\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// ② وحداتُ الصلاحياتِ الثمان — modules.code هو مفتاحُ الفحص
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ② وحداتُ الصلاحياتِ الثمان (قبلَ الرابطِ وقبلَ الشاشة)\n";
$SCREENS = [
    // code (المسار)                      اسمُ GOV-24                     الدور    المجموعة            الأيقونة                    الترتيب
    ['Governance/bus_outbox.php',     'صندوق الأحداث الصادر',      R_GOV, 'n9s16_10_32_r15', 'fa fa-inbox',              1],
    ['Governance/bus_deliveries.php', 'تسليمات الأحداث وحالاتها',  R_GOV, 'n9s16_10_32_r15', 'fa fa-truck-fast',         2],
    ['Governance/bus_board.php',      'لوحة الناقل',                R_GOV, 'n9s16_10_32_r15', 'fa fa-gauge-high',         3],
    ['Governance/job_queue.php',      'طابور المهام',               R_GOV, 'n9s16_10_33_r15', 'fa fa-list-check',         1],
    ['Governance/job_schedule.php',   'جدولة المهام الدورية',      R_GOV, 'n9s16_10_33_r15', 'fa fa-calendar-days',      2],
    ['Governance/dr_restore.php',     'الاستعادة ومحضرها',         R_GOV, 'n9s16_10_34_r15', 'fa fa-clock-rotate-left',  1],
    // خارجَ GOV-24 — موضعُهما من الحيِّ: مجموعةُ «الإهلاك» لدورِ المالية
    ['Finance/asset_hours_link.php',  'ربط الأصل بساعات تشغيله',   R_FIN, null,               'fa fa-link',               27],
    ['Finance/depr_run.php',          'احتساب إهلاك الفترة',       R_FIN, null,               'fa fa-calculator',         28],
];

$modId = [];
foreach ($SCREENS as [$code, $name, $role, $grpCode, $icon, $ord]) {
    $st = $conn->prepare("SELECT id FROM `modules` WHERE `code`=?");
    $st->bind_param('s', $code); $st->execute();
    $row = $st->get_result()->fetch_row(); $st->close();
    if ($row) { $modId[$code] = (int) $row[0]; echo "   · $code وحدةٌ قائمة (id={$row[0]})\n"; continue; }
    $st = $conn->prepare(
        "INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`group_id`,`is_link`,`is_quick`,`icon`,`display_order`)
         VALUES (?,?,?,NULL,'0',0,?,?)"
    );
    $st->bind_param('ssisi', $name, $code, $role, $icon, $ord);
    $st->execute(); $modId[$code] = (int) $conn->insert_id; $st->close();
    echo "   ✔ وحدةٌ #{$modId[$code]} ← $code «$name»\n";
}

// المنحُ: المالكُ يرى ويكتب · والمراجعُ الداخليُّ والتنفيذيةُ يريان فقط
echo "\n   منحُ الصلاحيات:\n";
$grant = $conn->prepare(
    "INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
     VALUES (?,?,?,?,?,0)
     ON DUPLICATE KEY UPDATE `can_view`=VALUES(`can_view`),`can_add`=VALUES(`can_add`),`can_edit`=VALUES(`can_edit`)"
);
$granted = 0;
foreach ($SCREENS as [$code, , $role]) {
    $m = $modId[$code];
    // المالك: عرضٌ وإضافةٌ وتعديل
    $v = 1; $a = 1; $e = 1;
    $grant->bind_param('iiiii', $role, $m, $v, $a, $e); $grant->execute(); $granted++;
    // قراءةٌ فقط: 9 التنفيذية · 20 المراجع المالي · 33 المراجع الداخلي المستقل
    foreach ([9, 20, 33] as $ro) {
        $v = 1; $a = 0; $e = 0;
        $grant->bind_param('iiiii', $ro, $m, $v, $a, $e); $grant->execute(); $granted++;
    }
}
$grant->close();
echo "   ✔ $granted منحًا (المالكُ يكتب · 9 و20 و33 يقرؤون)\n";

// ═══════════════════════════════════════════════════════════════════════════
// ③ روابطُ التنقّل — بعدَ الوحدةِ لا قبلَها (chk_nav_items_module_or_code)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ③ روابطُ التنقّل في مواضعِها من GOV-24\n";
$nav = $conn->prepare(
    "INSERT INTO `nav_items` (`role_id`,`door`,`group_id`,`module_id`,`label_ar`,`route`,`icon`,`sort_order`,`permission_code`,`active`)
     VALUES (?,?,?,?,?,?,?,?,?,1)
     ON DUPLICATE KEY UPDATE `group_id`=VALUES(`group_id`), `module_id`=VALUES(`module_id`),
                             `label_ar`=VALUES(`label_ar`), `sort_order`=VALUES(`sort_order`),
                             `permission_code`=VALUES(`permission_code`), `active`=1"
);
$navN = 0;
foreach ($SCREENS as [$code, $name, $role, $grpCode, $icon, $ord]) {
    $g    = $grpCode !== null ? $gid[$grpCode] : GRP_DEPR;
    $door = $role === R_GOV ? 'GOV' : 'DAILY';
    $m    = $modId[$code];
    $nav->bind_param('isiissssi', $role, $door, $g, $m, $name, $code, $icon, $ord, $code);
    if ($nav->execute()) { $navN++; echo "   ✔ [$door] grp=$g  «$name» → $code\n"; }
    else { echo "   ✗ $code — " . $nav->error . "\n"; }
}
$nav->close();
echo "   ✔ $navN رابطًا\n";

// ═══════════════════════════════════════════════════════════════════════════
// ④ قاموسُ الأفعال — العشرةُ من TS-01 §٤-١٥ + العكسُ الذي يفرضه الحيّ
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ④ قاموسُ الأفعال\n";
$ACTIONS = [
    // code                 name_ar                       screen                          class                                              method       write fin  reverse
    ['bus.event.publish',   'نشرُ حدثٍ في الصندوق',      'Governance/bus_outbox.php',     'App\\Services\\Bus\\EventOutboxPublisher',        'publish',     1, 0, null],
    ['bus.deliver',         'تسليمُ حدثٍ بالعطالة',      'Governance/bus_deliveries.php', 'App\\Services\\Bus\\EventDeliveryWorker',         'deliverOne',  1, 0, 'bus.dlq.decide'],
    ['bus.dlq.decide',      'قرارٌ في صندوقِ الموتى',    'Governance/bus_deliveries.php', 'App\\Services\\Bus\\EventDeliveryWorker',         'decideDlq',   1, 0, null],
    ['bus.board.view',      'عرضُ لوحةِ الناقل',         'Governance/bus_board.php',      null,                                              null,          0, 0, null],
    ['job.enqueue',         'إدراجُ مهمةٍ آليًّا',         'Governance/job_queue.php',      'App\\Services\\Queue\\JobQueueService',           'enqueue',     1, 0, null],
    ['job.claim',           'التقاطُ مهمةٍ بقفل',        'Governance/job_queue.php',      'App\\Services\\Queue\\JobQueueService',           'claimAtomic', 1, 0, null],
    ['job.schedule.define', 'تعريفُ جدولةٍ دورية',       'Governance/job_schedule.php',   'App\\Services\\Queue\\JobScheduleService',        'define',      1, 0, null],
    ['dr.restore.drill',    'تجربةُ استعادةٍ لنقطةِ زمن','Governance/dr_restore.php',     'App\\Services\\Dr\\RestoreDrillService',          'record',      1, 0, null],
    ['asset.hours.link',    'ربطُ أصلٍ بساعاته',         'Finance/asset_hours_link.php',  'App\\Services\\Assets\\AssetHoursService',        'link',        1, 0, null],
    ['depr.run',            'احتسابُ إهلاكِ الفترة',     'Finance/depr_run.php',          'App\\Services\\Assets\\DepreciationRunService',   'run',         1, 1, 'depr.reverse'],
    // ◆ يفرضه الحيّ: كلُّ فعلٍ ماليٍّ بعكسٍ مسمًّى (24/24 اليوم)
    ['depr.reverse',        'عكسُ إهلاكِ فترةٍ بمرجعِه', 'Finance/depr_run.php',          'App\\Services\\Assets\\DepreciationRunService',   'reverse',     1, 1, 'depr.run'],
];

$act = $conn->prepare(
    "INSERT INTO `actions`
        (`action_code`,`name_ar`,`module_id`,`placement`,`handler_class`,`handler_method`,
         `is_write`,`guards_json`,`reverse_action_code`,`is_financial`,`owner_doc`,`active`)
     VALUES (?,?,?,?,?,?,?,?,?,?,'TS-01',1)
     ON DUPLICATE KEY UPDATE `name_ar`=VALUES(`name_ar`), `module_id`=VALUES(`module_id`),
                             `handler_class`=VALUES(`handler_class`), `handler_method`=VALUES(`handler_method`),
                             `is_write`=VALUES(`is_write`), `guards_json`=VALUES(`guards_json`),
                             `reverse_action_code`=VALUES(`reverse_action_code`),
                             `is_financial`=VALUES(`is_financial`), `active`=1"
);
$actN = 0;
foreach ($ACTIONS as [$code, $name, $screen, $class, $method, $isWrite, $isFin, $rev]) {
    $m = $modId[$screen] ?? null;
    $placement = $isWrite ? 'row' : 'header';
    // حرّاسُ الفحصِ بترتيبِهم المعلَن — وفعلُ كتابةٍ بلا حرّاس يُرفض
    $guards = $isWrite
        ? json_encode(['session', 'screen_guard', 'action_guard', 'csrf', 'tenant_scope'], JSON_UNESCAPED_UNICODE)
        : json_encode(['session', 'screen_guard'], JSON_UNESCAPED_UNICODE);
    $act->bind_param('ssisssissi', $code, $name, $m, $placement, $class, $method, $isWrite, $guards, $rev, $isFin);
    if ($act->execute()) { $actN++; printf("   ✔ %-22s → %s\n", $code, $screen); }
    else { echo "   ✗ $code — " . $act->error . "\n"; }
}
$act->close();
echo "   ✔ $actN فعلًا مسجَّلًا\n";

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ التحقُّق: لا رابطَ بلا وحدةٍ ولا فعلَ كتابةٍ بلا حرّاس
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑤ تحقُّقٌ بعدَ التسجيل\n";
$dead = (int) $one(
    "SELECT COUNT(*) FROM `nav_items` n
      WHERE n.`route` IN ('Governance/bus_outbox.php','Governance/bus_deliveries.php','Governance/bus_board.php',
                          'Governance/job_queue.php','Governance/job_schedule.php','Governance/dr_restore.php',
                          'Finance/asset_hours_link.php','Finance/depr_run.php')
        AND (n.`module_id` IS NULL OR NOT EXISTS (SELECT 1 FROM `modules` m WHERE m.id = n.`module_id`))"
);
echo "   · روابطُ ميتةٌ (بلا وحدة): $dead   [المتوقَّع 0]\n";
$noguard = (int) $one(
    "SELECT COUNT(*) FROM `actions`
      WHERE `owner_doc`='TS-01' AND `is_write`=1 AND (`guards_json` IS NULL OR `guards_json`='')"
);
echo "   · أفعالُ كتابةٍ بلا حرّاس: $noguard   [المتوقَّع 0]\n";
$nofin = (int) $one(
    "SELECT COUNT(*) FROM `actions`
      WHERE `owner_doc`='TS-01' AND `is_financial`=1 AND (`reverse_action_code` IS NULL OR `reverse_action_code`='')"
);
echo "   · أفعالٌ ماليةٌ بلا عكس: $nofin   [المتوقَّع 0]\n";
$navCnt = (int) $one("SELECT COUNT(*) FROM `nav_items` WHERE `route` LIKE '%bus_%' OR `route` LIKE '%job_%'
                       OR `route` LIKE '%dr_restore%' OR `route` LIKE '%asset_hours_link%' OR `route` LIKE '%depr_run%'");
echo "   · روابطُ المحرّكاتِ في التنقّل: $navCnt\n";

echo "\n═══ اكتمل التسجيل — والشاشاتُ تُبنى بعدَه ═══\n\n";
