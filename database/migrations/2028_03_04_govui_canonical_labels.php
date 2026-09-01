<?php
/**
 * 2028_03_04_govui_canonical_labels.php — `CURRENT_UI_LABEL = EFFECTIVE_CANONICAL_LABEL`
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_targets(canonical_title) + nav_canonical(canonical_ar)
 *                     + repair01_screen_registry(canonical_label_ar) + nav_items(label_ar)
 *                     + modules(name) + log:govui_label_log
 *
 * ◆ **الاسمُ يُقرأ من الملفِّ الحاكمِ لا من قائمةٍ منقولةٍ بيدي** (§2 · §7):
 *   الهجرةُ تفتح `01 · الدليل المعماري` و`02 · القيادة` وتقرأ عنوانَ البطاقةِ
 *   بمرساتِه — فلا قائمةَ ثانيةٌ تتفرّق عن أصلِها [[fix-the-tool-not-the-output]].
 *
 * ◆ **والنسبُ لا يُمَسّ**: المطابقةُ بـ`(workspace, order)` ⇒ `target_id`،
 *   و`target_id` **لا يتغيّر سطرًا واحدًا** — يتغيّر `canonical_title` وحدَه.
 *   (§4: `TARGET_LINEAGE_BROKEN_BY_RENAME = 0`.)
 *
 * ◆ **وأربعةُ مخازنِ عرضٍ تُكتب معًا** لأنَّ أيًّا منها قد يغلب في التصيير:
 *   `nav_canonical.canonical_ar` (APPROVED يغلب) · `repair01_screen_registry`
 *   · `nav_items.label_ar` · `modules.name`. **ومخزنٌ واحدٌ يُترك يجعل المُصيَّرَ
 *   يخالف السجلَّ** — وهو الأخضرُ الكاذبُ الذي تحذّر منه §16.
 *
 * ◆ ⛔ **ويُستثنى صنفان بحكمِهما**:
 *   ① **ابنُ تبويبٍ يتقاسم مسارَ أبيه** — اسمُه في صفحتِه لا في القائمة، وكتابتُه
 *      على المسارِ تدهس اسمَ الأبِ (§8).
 *   ② **مسارٌ متعدِّدُ السياق** — ظهر في مساحتَين باسمَين حاكمَين مختلفَين
 *      (§5: «لا Route واحدة = Group عالمية واحدة»). فاسمُه العالميُّ يبقى،
 *      **واسمُ السياقِ يأتي من `nav_targets` في التصيير** (طبقةُ السياقِ في
 *      `unified_nav.php`)، ويُقيَّد الصفُّ `CONTEXT_SPLIT` ليُقرأ لا ليُبتلع.
 *
 * التشغيل: php database/migrations/2028_03_04_govui_canonical_labels.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/tools/govui_lib.php';
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

$conn->query("CREATE TABLE IF NOT EXISTS `govui_label_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_id` varchar(24) NOT NULL,
  `store` varchar(64) NOT NULL COMMENT 'الجدولُ والعمودُ الذي كُتب',
  `store_key` varchar(200) NOT NULL COMMENT 'مفتاحُ الصفِّ في مخزنِه',
  `old_label` varchar(190) NOT NULL DEFAULT '',
  `new_label` varchar(190) NOT NULL DEFAULT '',
  `source_ref` varchar(190) NOT NULL COMMENT 'الملفُّ والورقةُ والصفُّ الحاكم',
  `reason` varchar(190) NOT NULL DEFAULT '',
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_gll_t` (`target_id`),
  KEY `ix_gll_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_UI_EXEC §7 · UI_NAMING_CHANGELOG: كلُّ اسمٍ كُتب بقيمتِه السابقةِ وسندِه'");

/* ◆ **عطبُ عُدّةٍ كُشف بالقياسِ وأُصلح في العُدّةِ لا في مخرَجِها**: كان العمودُ
   `store` أربعين حرفًا، واسمُ المخزنِ `repair01_screen_registry.canonical_label_ar`
   ثلاثةٌ وأربعون — **فبُتر صامتًا** فلم يمسكه `switch` في سكربتِ العكس، فبقيت
   مئةُ صفٍّ في سجلِّ الشاشاتِ بلا طريقِ رجوع. والتوسعةُ لاحقةٌ تُطبَّق على
   القائمِ أيضًا [[data-mismatch-campaign]] — «الرقمُ يُقرأ صحيحًا وهو خطأ». */
$conn->query("ALTER TABLE `govui_label_log` MODIFY `store` varchar(64) NOT NULL
              COMMENT 'الجدولُ والعمودُ الذي كُتب'");

/* ═══ ① الحاكمُ من الملفَّين ═══ */
$cards = govui_target_cards($ROOT);
$byOrder = array();
$r = $conn->query("SELECT target_id, workspace_id, target_order, canonical_title FROM nav_targets");
while ($x = $r->fetch_assoc()) { $byOrder[$x['workspace_id'] . '#' . (int) $x['target_order']] = $x; }

/* ═══ ② الموضعُ ═══ */
$plc = array();
$r = $conn->query("SELECT target_id, screen_id, route, placement_type, workspace_id FROM nav_placements");
while ($x = $r->fetch_assoc()) { if ($x['target_id']) { $plc[$x['target_id']] = $x; } }
$lc = function ($s) { return strtolower(ltrim((string) $s, '/')); };

/* ═══ ③ التصنيفُ: مالكُ المسارِ · ابنُ تبويبٍ عليه · متعدِّدُ السياق ═══ */
$rows = array();                                    /* tid ⇒ [ws, order, canonical, srcref] */
foreach ($cards as $ws => $list) {
    foreach ($list as $c) {
        $k = $ws . '#' . $c['order'];
        if (!isset($byOrder[$k])) { continue; }
        $rows[$byOrder[$k]['target_id']] = array('ws' => $ws, 'order' => $c['order'],
            'label' => $c['name_raw'], 'stored' => $byOrder[$k]['canonical_title'],
            'src' => $c['sheet'] . '·صف ' . $c['row']);
    }
}
$routeTargets = array();
foreach ($rows as $tid => $d) {
    if (!isset($plc[$tid]) || !$plc[$tid]['route']) { continue; }
    $routeTargets[$lc($plc[$tid]['route'])][$tid] = $d;
}
/* ◆ **مالكُ اسمِ المسارِ واحدٌ لا اثنان** — وثلاثُ حالاتٍ لا رابعَ لها:
     ① مساحتان بأسماءٍ مختلفةٍ على مسارٍ واحد ⇒ `CONTEXT_SPLIT`: **لا يُكتب
        اسمٌ عالميٌّ**، والسياقُ يُصيَّر من `nav_targets` (§5).
     ② مساحةٌ واحدةٌ وفيها **بندانِ في القائمة** على مسارٍ واحد ⇒ عطبٌ يُسمّى
        ولا يُكتب اسم (§9) — وقد حُسمت كلُّها في هجرةِ الوصلِ قبلَ هذه.
     ③ مساحةٌ واحدةٌ وبندٌ واحدٌ وبقيّتُهم تبويباتٌ/مباشرٌ ⇒ **الاسمُ للبندِ**
        وأبناؤه أسماؤهم في صفحاتِهم (§8). ⛔ **وابنُ تبويبٍ لا يمنع أباه اسمَه** */
$owner = array(); $skipWhy = array();
foreach ($routeTargets as $rt => $set) {
    $wsSet = array(); foreach ($set as $tid => $d) { $wsSet[$d['ws']] = 1; }
    $labels = array(); foreach ($set as $tid => $d) { $labels[$d['label']] = 1; }
    if (count($wsSet) > 1 && count($labels) > 1) {
        foreach ($set as $tid => $d) { $skipWhy[$tid] = 'CONTEXT_SPLIT — المسارُ في مساحتَين باسمَين حاكمَين (§5)'; }
        continue;
    }
    $menus = array();
    foreach ($set as $tid => $d) { if ($plc[$tid]['placement_type'] === 'MENU_ITEM') { $menus[] = $tid; } }
    sort($menus);
    if (count($menus) > 1 && count($labels) > 1) {
        foreach ($set as $tid => $d) { $skipWhy[$tid] = 'SAME_WS_TWO_MENU_ITEMS — بندانِ في القائمةِ على مسارٍ واحد (§9)'; }
        continue;
    }
    $ids = array_keys($set); sort($ids);
    $own = count($menus) >= 1 ? $menus[0] : $ids[0];
    $owner[$own] = $rt;
    foreach ($ids as $tid) {
        if ($tid === $own) { continue; }
        $skipWhy[$tid] = 'CHILD_ON_PARENT_ROUTE — اسمُه في تبويبِ أبيه لا في القائمة (§8)';
    }
}

/* ═══ ③ب استردادُ حالةِ ما قبلَ الجولةِ في سجلِّ الشاشات ═══
   ◆ **لِمَ**: أوّلُ تشغيلٍ لهذه الهجرةِ كتب مئةً وواحدًا في
     `repair01_screen_registry` وقيدُها بُتر بعطبِ العمودِ أعلاه، فلم يردَّها
     العكسُ — فصار المخزنُ متقدِّمًا على قيدِه وبلا طريقِ رجوع.
   ◆ **والاستردادُ مقيسٌ لا مفترَض**: قِستُ الثباتَ «اسمُ السجلِّ = اسمُ
     `nav_canonical`» على المساراتِ **التي لم تمسَّها الجولةُ**: **539 من 541**
     (والشاذّان `chats/index.php` و`main/user_profile.php` مسمَّيان بخلافِه
     ولا يدخلان مدى الجولة). فالردُّ من `nav_canonical` بعدَ عكسِه ردٌّ إلى
     القيمةِ التي كانت، بشاهدٍ لا بظنّ.
   ◆ **والشرطُ ضيّق**: صفٌّ يساوي **الحاكمَ الجديد** بينما `nav_canonical`
     ما زال على اسمٍ آخرَ — وهو أثرُ التشغيلِ المبتورِ وحدَه. */
$restored = 0;
foreach ($owner as $tid => $rt) {
    $d = $rows[$tid]; $sid = (string) $plc[$tid]['screen_id']; $route = $plc[$tid]['route'];
    if ($sid === '') { continue; }
    $st = $conn->prepare("SELECT g.canonical_label_ar AS reg, n.canonical_ar AS canon
                            FROM repair01_screen_registry g
                            JOIN nav_canonical n ON LOWER(n.route) = LOWER(?)
                           WHERE g.screen_id = ?");
    $st->bind_param('ss', $route, $sid); $st->execute();
    $g = $st->get_result()->fetch_assoc(); $st->close();
    if (!$g) { continue; }
    if ($g['reg'] === $d['label'] && $g['canon'] !== $d['label']) {
        $st = $conn->prepare("UPDATE repair01_screen_registry SET canonical_label_ar = ? WHERE screen_id = ?");
        $st->bind_param('ss', $g['canon'], $sid);
        $st->execute(); $st->close();
        $restored++;
    }
}
if ($restored) { echo "  ⟲ استُرِدَّت {$restored} خانةَ اسمٍ في سجلِّ الشاشاتِ إلى ما قبلَ الجولةِ قبلَ إعادةِ الكتابة\n"; }

/* ═══ ④ الكتابةُ ═══ */
$log = $conn->prepare("INSERT INTO govui_label_log
    (target_id, store, store_key, old_label, new_label, source_ref, reason) VALUES (?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare log: {$conn->error}\n"); }
$write = function ($tid, $store, $key, $old, $new, $src, $reason) use ($log) {
    if ((string) $old === (string) $new) { return false; }
    $log->bind_param('sssssss', $tid, $store, $key, $old, $new, $src, $reason);
    $log->execute();
    return true;
};

$cntTarget = 0; $cntCanon = 0; $cntReg = 0; $cntNav = 0; $cntMod = 0; $skipped = 0;

/* ④-أ اسمُ الهدفِ في سجلِّ الأهداف — يشمل الكونَ كلَّه (413) */
foreach ($rows as $tid => $d) {
    if ($d['stored'] === $d['label']) { continue; }
    $st = $conn->prepare("UPDATE nav_targets SET canonical_title = ? WHERE target_id = ?");
    $st->bind_param('ss', $d['label'], $tid);
    if (!$st->execute()) { exit("⛔ nav_targets {$tid}: {$conn->error}\n"); }
    $st->close();
    $write($tid, 'nav_targets.canonical_title', $tid, $d['stored'], $d['label'], $d['src'], 'إعادةُ تسميةٍ في الحزمةِ الحاكمة');
    $cntTarget++;
}

/* ④-ب مخازنُ العرضِ الأربعةُ لمالكِ المسار */
foreach ($owner as $tid => $rt) {
    $d = $rows[$tid]; $new = $d['label'];
    $route = $plc[$tid]['route']; $sid = (string) $plc[$tid]['screen_id'];

    /* nav_canonical */
    $st = $conn->prepare("SELECT canonical_ar FROM nav_canonical WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $g = $st->get_result()->fetch_assoc(); $st->close();
    if ($g && $write($tid, 'nav_canonical.canonical_ar', $route, $g['canonical_ar'], $new, $d['src'], 'المعروضُ يُقرأ منه أوّلًا')) {
        $st = $conn->prepare("UPDATE nav_canonical SET canonical_ar = ? WHERE LOWER(route) = LOWER(?)");
        $st->bind_param('ss', $new, $route);
        if (!$st->execute()) { exit("⛔ nav_canonical {$route}: {$conn->error}\n"); }
        $st->close(); $cntCanon++;
    }
    /* repair01_screen_registry */
    if ($sid !== '') {
        $st = $conn->prepare("SELECT canonical_label_ar FROM repair01_screen_registry WHERE screen_id = ?");
        $st->bind_param('s', $sid); $st->execute();
        $g = $st->get_result()->fetch_assoc(); $st->close();
        if ($g && $write($tid, 'repair01_screen_registry.canonical_label_ar', $sid, $g['canonical_label_ar'], $new, $d['src'], 'سجلُّ الشاشاتِ للمبنيّ')) {
            $st = $conn->prepare("UPDATE repair01_screen_registry SET canonical_label_ar = ? WHERE screen_id = ?");
            $st->bind_param('ss', $new, $sid);
            if (!$st->execute()) { exit("⛔ registry {$sid}: {$conn->error}\n"); }
            $st->close(); $cntReg++;
        }
    }
    /* nav_items — كلُّ دورٍ يحمل المسار · والعمودُ 64 حرفًا فيُقصُّ ما يفيض ويُقيَّد */
    $navLabel = mb_substr($new, 0, 64);
    $st = $conn->prepare("SELECT id, role_id, label_ar FROM nav_items WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $rs = $st->get_result(); $navRows = array();
    while ($x = $rs->fetch_assoc()) { $navRows[] = $x; }
    $st->close();
    foreach ($navRows as $x) {
        if ((string) $x['label_ar'] === $navLabel) { continue; }
        $st = $conn->prepare("UPDATE nav_items SET label_ar = ? WHERE id = ?");
        $st->bind_param('si', $navLabel, $x['id']);
        if (!$st->execute()) { exit("⛔ nav_items {$x['id']}: {$conn->error}\n"); }
        $st->close();
        $write($tid, 'nav_items.label_ar', 'id=' . $x['id'] . '·role=' . $x['role_id'], $x['label_ar'], $navLabel, $d['src'], 'بندُ قائمةِ الدور');
        $cntNav++;
    }
    /* modules.name */
    $st = $conn->prepare("SELECT id, name FROM modules WHERE LOWER(code) = LOWER(?)");
    $st->bind_param('s', $route); $st->execute();
    $g = $st->get_result()->fetch_assoc(); $st->close();
    if ($g && $write($tid, 'modules.name', 'id=' . $g['id'], $g['name'], mb_substr($new, 0, 100), $d['src'], 'اسمُ الوحدةِ في سجلِّ الصلاحيات')) {
        $nm = mb_substr($new, 0, 100);
        $st = $conn->prepare("UPDATE modules SET name = ? WHERE id = ?");
        $st->bind_param('si', $nm, $g['id']);
        if (!$st->execute()) { exit("⛔ modules {$g['id']}: {$conn->error}\n"); }
        $st->close(); $cntMod++;
    }
}

/* ④-ج المستثنى — يُقيَّد بحكمِه ولا يُبتلع */
foreach ($skipWhy as $tid => $why) {
    if (!isset($rows[$tid])) { continue; }
    $d = $rows[$tid];
    $store0 = 'SKIPPED';
    $key0 = isset($plc[$tid]['route']) ? (string) $plc[$tid]['route'] : '';
    $old0 = ''; $new0 = $d['label']; $src0 = $d['src'];
    $log->bind_param('sssssss', $tid, $store0, $key0, $old0, $new0, $src0, $why);
    $log->execute();
    $skipped++;
}

printf("nav_targets %d · nav_canonical %d · registry %d · nav_items %d · modules %d · مستثنًى %d\n",
    $cntTarget, $cntCanon, $cntReg, $cntNav, $cntMod, $skipped);
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
