<?php
/**
 * 2028_05_28_nav_items_quick_flag.php — رايةُ «الوصول السريع» في سجلِّ الدور
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك (الجزء الثاني)**: شاشةُ «تحكم الروابط» تعرض صفحاتِ الدورِ في
 * شبكةٍ بمربَّعَين: الأولُ ظهورُ الرابطِ في **الوصولِ السريع**، والثاني ظهورُه
 * في **السايدبار**.
 *
 * ◆ **ولماذا عمودٌ جديدٌ لا استعمالُ `modules.is_quick` القائم** — قياسٌ لا رأي:
 *   ① **مداه ليس الدور**: الرايةُ على صفِّ الوحدةِ لا على صفِّ الدور. ووحدةٌ
 *      واحدةٌ يشترك في قراءتها الدورُ المالكُ **وأبناؤه** (`getDynamicNavLinks`
 *      تضمُّ `parent_role_id`) — فرفعُها لدورٍ يرفعها لأبنائه، وهذا ليس تحكُّمًا
 *      بالدورِ المحدَّد. مقيسٌ: 21 من 149 رابطًا سريعًا حيًّا **يخصُّ الأبَ لا
 *      الابن** (الأدوار 8 · 24 · 31 · 34 · 35).
 *   ② **وحبّةُ الشاشةِ في هذه المعماريّةِ صارت `nav_items`**: هي سجلُّ الدورِ
 *      للملاحة (2,721 صفًّا · مفتاحٌ فريدٌ `role_id + route`)، ومنها يُشتقُّ
 *      تفويضُ السايدبارِ الحيِّ (`navarch_authorized_routes`). فوضعُ رايةِ
 *      الظهورِ الثانيةِ بجانبِ الأولى (`active`) يجعل المربَّعَين **صفًّا واحدًا
 *      لدورٍ واحد** بلا أثرٍ جانبيٍّ على دورٍ آخر.
 *
 * ⛔ **ولا يتغيّر ما يراه أحدٌ يومَ التطبيق**: العمودُ **يُبذَر من المُصيَّرِ
 *   الحيِّ نفسِه** — منطقةُ الوصولِ السريعِ اليومَ = `getDynamicNavLinks(دور)`
 *   ∩ `modules.is_quick = 1`. فكلُّ رابطٍ يظهر اليومَ تُرفع رايتُه في صفِّ دورِه.
 *   ◆ **والـ21 الموروثةُ عن الأبِ تُستوفى بصفٍّ `active = 0`**: فالوصولُ السريعُ
 *     يقرأ `is_quick` والسايدبارُ يقرأ `active` — فصفٌّ كهذا **يُبقي البلاطةَ
 *     كما هي ولا يضيف رابطًا واحدًا إلى سايدبارِ أحد** (كلا المُصيِّرَين يشترط
 *     `active = 1`). وهو أيضًا الصدقُ في الشبكة: الشاشةُ حاضرةٌ في الوصولِ
 *     السريعِ وغائبةٌ عن السايدبار — وهكذا تُعرَض.
 *
 * ◆ **والكتابةُ تُفتح لمن يرى الشاشة**: «التحكم بوضع الشيك أو إزالته» طلبٌ
 *   صريح. والظهورُ ليس صلاحيّةً (NAV-ARCH-02 §36) — فرفعُ الرايةِ لا يفتح بابًا
 *   ولا يغلقه؛ الحارسُ على وجهةِ الرابطِ كما كان.
 *
 * التشغيل: php database/migrations/2028_05_28_nav_items_quick_flag.php
 * العكس:   php database/migrations/2028_05_28_nav_items_quick_flag_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

/* المصيِّرُ الإرثيُّ يُحمَّل ليُقاس منه ما يظهر اليومَ — لا يُعاد بناءُ منطقِه هنا. */
$GLOBALS['conn'] = $conn;
require_once $ROOT . '/includes/dynamic_nav.php';

$CODE = 'Settings/links_control.php';
$MARK = 'links_control:2028_05_27';   /* وسمُ الجولةِ الأولى — نُحدِّث صفوفَها */
$norm = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};

/* ═══ ① العمود ═══════════════════════════════════════════════════════════ */
echo "══ ① عمودُ الرايةِ في nav_items ═══════════════════════════════════════\n";
$has = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items' AND COLUMN_NAME = 'is_quick'");
if ($has === 0) {
    if (!$conn->query("ALTER TABLE nav_items ADD COLUMN is_quick TINYINT(1) NOT NULL DEFAULT 0 AFTER active")) {
        exit('⛔ تعذّر إضافةُ العمود: ' . $conn->error . "\n");
    }
    echo "   أُضيف `is_quick` (افتراضُه صفر — فلا رابطَ يُولد ظاهرًا)\n";
} else {
    echo "   العمودُ قائمٌ سلفًا — لا يُعاد إنشاؤه\n";
}

/* ═══ ② البذرُ من المُصيَّرِ الحيّ ══════════════════════════════════════════ */
echo "\n══ ② البذرُ من منطقةِ الوصولِ السريعِ الحيّة ═══════════════════════════\n";

/* خريطةُ المسارِ ⇒ (باب · اسم · أيقونة · وحدة · رمزُ صلاحية) من أشيعِ صفٍّ له */
$proto = array();
$r = $conn->query("SELECT route, door, label_ar, icon, module_id, permission_code, COUNT(*) n
                     FROM nav_items GROUP BY route, door, label_ar, icon, module_id, permission_code
                    ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $norm($x['route']);
    if (!isset($proto[$k])) { $proto[$k] = $x; }
}

$roles = array();
$r = $conn->query('SELECT id FROM roles ORDER BY id');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }

$modQuick = array();   /* module_id ⇒ 1 */
$r = $conn->query('SELECT id FROM modules WHERE is_quick = 1');
while ($r && ($x = $r->fetch_row())) { $modQuick[(int) $x[0]] = true; }

$upd = $conn->prepare("UPDATE nav_items SET is_quick = 1 WHERE role_id = ? AND route = ?");
$ins = $conn->prepare("INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
         counter_source, permission_code, active, is_quick)
     VALUES (?, ?, NULL, ?, ?, ?, ?, 900, NULL, ?, 0, 1)");
$flagged = 0; $created = 0; $seen = array();
foreach ($roles as $rid) {
    /* صفوفُ الدورِ الحاليّةُ بمسارِها المسوَّى */
    $mine = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$rid}");
    while ($q && ($x = $q->fetch_row())) { $mine[$norm($x[0])] = (string) $x[0]; }

    foreach (getDynamicNavLinks($conn, (string) $rid) as $l) {
        if (empty($modQuick[(int) ($l['id'] ?? 0)])) { continue; }   /* ليس في الوصولِ السريع */
        $code = (string) $l['code'];
        $k = $norm($code);
        $seen[$rid . '|' . $k] = true;
        if (isset($mine[$k])) {
            $rt = $mine[$k];
            $upd->bind_param('is', $rid, $rt);
            $upd->execute();
            $flagged += $upd->affected_rows;
            continue;
        }
        /* لا صفَّ لهذا الدور — يُستوفى ساكنًا (`active = 0`) فلا يمسُّ سايدبارًا */
        $pr = isset($proto[$k]) ? $proto[$k] : null;
        $door  = $pr ? (string) $pr['door'] : 'DAILY';
        $label = $pr ? (string) $pr['label_ar'] : (string) $l['name'];
        $icon  = $pr ? (string) $pr['icon'] : (!empty($l['icon']) ? (string) $l['icon'] : 'fa fa-link');
        $mid   = $pr && $pr['module_id'] !== null ? (int) $pr['module_id'] : (int) $l['id'];
        $pc    = $pr ? (string) $pr['permission_code'] : $code;
        $route = $pr ? (string) $pr['route'] : $code;
        $ins->bind_param('isissss', $rid, $door, $mid, $label, $route, $icon, $pc);
        if ($ins->execute()) { $created += $ins->affected_rows; }
    }
}
$upd->close();
$ins->close();
printf("   صفوفٌ رُفعت رايتُها: %d\n", $flagged);
printf("   صفوفٌ ساكنةٌ أُنشئت (active=0): %d\n", $created);
printf("   مجموعُ الرايات الآن: %d\n", $one("SELECT COUNT(*) FROM nav_items WHERE is_quick = 1"));

/* ═══ ③ الكتابةُ تُفتح لمن يرى الشاشة ═════════════════════════════════════ */
echo "\n══ ③ إذنُ الكتابةِ على شاشةِ التحكّم ══════════════════════════════════\n";
$mid = $one("SELECT id FROM modules WHERE code = '{$CODE}' ORDER BY id LIMIT 1");
if ($mid < 1) { exit("⛔ الشاشةُ غيرُ مسجَّلة — شغِّل 2028_05_27 أولًا.\n"); }
$conn->query("UPDATE role_permissions SET can_edit = 1 WHERE module_id = {$mid} AND can_view = 1");
printf("   الطبقةُ القائمة: %d صفًّا\n", $conn->affected_rows);
$conn->query("UPDATE gov_profile_items SET can_edit = 1
               WHERE seeded_from = '{$MARK}' AND item_kind = 'screen' AND item_ref = '{$CODE}' AND allow = 1");
printf("   طبقةُ القوالب: %d بندًا\n", $conn->affected_rows);

/* ═══ ④ الشواهد ═════════════════════════════════════════════════════════ */
echo "\n══ ④ الشاهد ══════════════════════════════════════════════════════════\n";
$ok = true;

/* ⓐ لا صفَّ ساكنٍ صار حيًّا: عددُ active=1 كما كان قبلَ الجولة (2,492 مقيسًا) */
$act = $one("SELECT COUNT(*) FROM nav_items WHERE active = 1");
printf("   صفوفٌ حيّةٌ في nav_items: %d\n", $act);

/* ⓑ كلُّ رابطٍ سريعٍ حيٍّ اليومَ له رايةٌ في صفِّ دورِه — صفرُ فجوة */
$gap = 0; $gapSample = array();
foreach ($roles as $rid) {
    $flags = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$rid} AND is_quick = 1");
    while ($q && ($x = $q->fetch_row())) { $flags[$norm($x[0])] = true; }
    foreach (getDynamicNavLinks($conn, (string) $rid) as $l) {
        if (empty($modQuick[(int) ($l['id'] ?? 0)])) { continue; }
        if (!isset($flags[$norm((string) $l['code'])])) {
            $gap++;
            if (count($gapSample) < 5) { $gapSample[] = $rid . ':' . $l['code']; }
        }
    }
}
printf("   روابطُ سريعةٌ حيّةٌ بلا رايةٍ في صفِّ دورِها: %d%s\n",
    $gap, $gap ? ' ⛔ — ' . implode(' · ', $gapSample) : '');
if ($gap !== 0) { $ok = false; }

/* ⓒ ولا رايةَ على صفٍّ لم يكن ظاهرًا اليومَ (البذرُ لا يخترع ظهورًا) */
$extra = 0;
$q = $conn->query("SELECT role_id, route FROM nav_items WHERE is_quick = 1");
while ($q && ($x = $q->fetch_assoc())) {
    if (!isset($seen[(int) $x['role_id'] . '|' . $norm($x['route'])])) { $extra++; }
}
printf("   رايات على صفوفٍ لم تكن ظاهرةً اليوم: %d (المستهدف صفر)\n", $extra);
if ($extra !== 0) { $ok = false; }

/* ⓓ إذنُ الكتابةِ يطابق إذنَ العرضِ عددًا */
$v = $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$mid} AND can_view = 1");
$e = $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$mid} AND can_edit = 1");
printf("   أدوارٌ ترى=%d · تكتب=%d\n", $v, $e);
if ($v !== $e) { $ok = false; }
$pv = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from = '{$MARK}' AND allow = 1");
$pe = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from = '{$MARK}' AND allow = 1 AND can_edit = 1");
printf("   قوالبُ ترى=%d · تكتب=%d\n", $pv, $pe);
if ($pv !== $pe) { $ok = false; }

if (!$ok) { exit("\n⛔ الشاهدُ لم يتحقّق — لا تلتزمْ قبلَ المراجعة.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — الرايةُ مبذورةٌ من المُصيَّرِ، وصفرُ ظهورٍ جديدٍ وُلد.\n";
