<?php
/**
 * 2028_04_17_navarch02_lifecycle_heads.php — رؤوسُ الطيِّ الناقصةُ للأهدافِ
 * المعتمَدةِ بعدَ الدليل (§8 · §16 · §21-④ · NAV_ARCH_02_CLEAN §١·٣-٢)
 * @migration-objects: nav_lifecycle_groups rows
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ قبلَ الحكم**: عشرةُ مواضعَ `PRIMARY` نشطةٍ بلا مجموعة، وكلُّها
 *   `APPROVED_POST_GUIDE_ADDITION` بسندٍ مكتوبٍ سابقٍ لهذه الجولة
 *   (`INJ-SAL-ALIGN-01` · `INJ-SUP-ALIGN-01` · `RPR-NAV-SEC-01` ·
 *   `REPAIR01-OPS-11`). و**المُصيِّرُ يحجب الموضعَ بلا رأسِ طيٍّ** (§23-①)،
 *   فأهدافٌ معتمَدةٌ تختفي صامتةً — وهو ما يمنعه §4.
 *
 * ⭐ **والرأسُ يُقرأ ولا يُخترَع**: `gov_target_nav.group_ar` — الجدولُ
 *   المستهدَفُ المنشورُ بسندٍ — يسمّي مجموعةَ كلِّ مسار. **وثمانيةٌ من
 *   العشرةِ مجموعتُها مُعلَنةٌ بدورٍ مربوطٍ بالمساحةِ نفسِها**، غيرَ أنَّ
 *   الاسمَ لا رأسَ له في `nav_lifecycle_groups` بعد. ⇒ **يُنشأ الرأسُ في
 *   مساحتِه** بالاسمِ المُعلَنِ حرفًا.
 *
 * ⛔ **وهذا ليس استعارةَ مجموعةِ إدارةٍ** (§13 · §42): الرأسُ يُنشأ **في
 *   المساحةِ التي أعلنه دورُها**، لا يُنقل من دورةِ مساحةٍ أخرى.
 *   ⇒ **واختبارُ §13 مصدرُ الإعلانِ لا تشابهُ الاسم**: «التخطيط والتوزيع»
 *   مُعلَنٌ **بدورِ `DEP-11`** لمسارَين في `DEP-12` و`DEP-13` — ⇒ **فلا
 *   يُنشأ لهما رأس**، ويبقيانِ بحكمَيهما المكتوبَين.
 *
 * ◆ **المقيسُ حرفًا**: عشرةٌ تحتاج رأسًا ⇒ **أربعةُ رؤوسٍ أُنشئت** تحلُّ
 *   ثمانيةً (‏`DEP-04` «الإعدادات المرجعية» ×4 · `DEP-02` «المرجعيات
 *   والحوكمة» · `DEP-01` «البيانات المرجعية والتقارير» و«الحوكمة والضوابط»
 *   ×2)، **واثنانِ يبقيانِ بلا رأسٍ بحكمٍ مُسمًّى** لا بصمت.
 *
 * ⛔ **ولا يُنشأ رأسٌ إلّا إن كان له مسارٌ يحتاجه**: القائمةُ مُشتقّةٌ من
 *   المواضعِ النشطةِ بلا مجموعةٍ وحدَها — لا من كلِّ أسماءِ `gov_target_nav`.
 * والعكسُ في `_down.php`.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/navarch_renderer.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/** التسويةُ نفسُها التي يستعملها `classify.php` — الهمزةُ والتاءُ المربوطة */
$nz = function ($s) {
    $s = (string) $s;
    $s = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $s);
    $s = strtr($s, array('أ'=>'ا','إ'=>'ا','آ'=>'ا','ى'=>'ي','ة'=>'ه','ؤ'=>'و','ئ'=>'ي'));
    $s = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
};

/* ① أدوارُ كلِّ مساحةٍ — `PRIMARY` و`SECONDARY` معًا */
$wsRoles = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles
                    ORDER BY (binding = 'PRIMARY') DESC, role_id");
while ($x = $r->fetch_assoc()) { $wsRoles[$x['workspace_id']][] = (int) $x['role_id']; }

/* ② رؤوسُ الطيِّ القائمةُ لكلِّ مساحةٍ — بالاسمِ المُسوّى */
$heads = array(); $maxSort = array();
$r = $conn->query("SELECT id, workspace_id, group_key, label_ar, sort_no FROM nav_lifecycle_groups");
while ($x = $r->fetch_assoc()) {
    $w = $x['workspace_id'];
    foreach (array($nz($x['group_key']), $nz($x['label_ar'])) as $k) {
        $heads[$w][$k] = (int) $x['id'];
    }
    if (!isset($maxSort[$w]) || (int) $x['sort_no'] > $maxSort[$w]) { $maxSort[$w] = (int) $x['sort_no']; }
}

/* ③ إعلاناتُ `gov_target_nav` — دورٌ ⇒ مسارٌ مُسوًّى ⇒ اسمُ المجموعة */
$decl = array();
$r = $conn->query("SELECT role_id, route, group_ar FROM gov_target_nav
                    WHERE route IS NOT NULL AND route <> '' AND group_ar IS NOT NULL AND group_ar <> ''");
while ($x = $r->fetch_assoc()) {
    if (strncmp((string) $x['route'], 'GAP:', 4) === 0) { continue; }
    $k = navarch_norm_route($x['route']);
    if ($k !== '' && !isset($decl[(int) $x['role_id']][$k])) {
        $decl[(int) $x['role_id']][$k] = (string) $x['group_ar'];
    }
}

/* ④ المواضعُ النشطةُ التي تحتاج رأسًا — `PRIMARY`/`SECONDARY_APPROVED` وحدَها */
$need = array();
$r = $conn->query("SELECT workspace_id, route FROM nav_workspace_placements
                    WHERE group_id IS NULL AND status = 'ACTIVE'
                      AND placement_type IN ('PRIMARY','SECONDARY_APPROVED')
                      AND route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_assoc()) { $need[] = $x; }
echo "◆ مواضعُ دورةٍ بلا رأس: " . count($need) . "\n";

$ins = $conn->prepare("INSERT INTO nav_lifecycle_groups
    (workspace_id, group_key, label_ar, sort_no, source_ref, active)
    VALUES (?,?,?,?,?,1)");
$made = array(); $n = 0; $blocked = array();
foreach ($need as $x) {
    $ws = $x['workspace_id']; $k = navarch_norm_route($x['route']);
    $g = null; $byRole = 0;
    foreach (isset($wsRoles[$ws]) ? $wsRoles[$ws] : array() as $rid) {
        if (isset($decl[$rid][$k])) { $g = $decl[$rid][$k]; $byRole = $rid; break; }
    }
    if ($g === null) {
        $blocked[] = $ws . ' · ' . $x['route'] . ' — لا إعلانَ من دورٍ مربوطٍ بهذه المساحة';
        continue;
    }
    $key = $nz($g);
    if (isset($heads[$ws][$key])) { continue; }                 /* رأسٌ قائمٌ سلفًا */
    if (isset($made[$ws][$key])) { continue; }
    /* ⛔ **ولا يُحجَب الرأسُ لتشابهِ اسمٍ في مساحةٍ أخرى**: §13 يمنع أن تصير
       دورةُ إدارةٍ بندًا في دورةِ أخرى — **لا أن يتشابه اسمُ رأسَين**.
       والمقيسُ يحسمها: «اللوحة — خارج الدورة» رأسٌ في **ثماني عشرةَ مساحةً**،
       و`uq_ws_group` مفتاحُه `(workspace_id, group_key)` — فالرؤوسُ خاصّةٌ
       بمساحاتِها بحكمِ المخطَّط. ⇒ **اختبارُ §13 الحقيقيُّ هو مصدرُ الإعلان**:
       الدورُ المُعلِنُ مربوطٌ بهذه المساحةِ نفسِها (‏حلقةُ `$wsRoles[$ws]`
       أعلاه) — ومَن لا إعلانَ له من دورٍ مربوطٍ بها **لا يُنشأ له رأس**،
       وهو ما يستبعد `operations/daily_plan` و`operations/distribution_space`
       (‏مُعلَنانِ بدورِ `DEP-11` لمسارَين خارجَها) [[measure-blind-spots]]. */
    $sort = (isset($maxSort[$ws]) ? $maxSort[$ws] : 0) + 1;
    $maxSort[$ws] = $sort;
    $src = 'NAV-ARCH-02 §16 · gov_target_nav.group_ar بدورِ ' . $byRole . ' المربوطِ بـ' . $ws
         . ' · هدفٌ معتمَدٌ بعدَ الدليلِ بلا رأسِ طيّ';
    $ins->bind_param('sssis', $ws, $key, $g, $sort, $src);
    if ($ins->execute()) {
        $gid = $conn->insert_id;
        $heads[$ws][$key] = $gid; $made[$ws][$key] = 1; $n++;
        echo "+ {$ws} رأس {$gid} «{$g}» sort={$sort} ⇐ دور {$byRole}\n";
    } else { echo "x {$ws} «{$g}»: " . $conn->error . "\n"; }
}
$ins->close();
echo "= أُنشئ {$n} رأسَ طيٍّ\n";
foreach (array_unique($blocked) as $b) { echo "· لم يُنشأ: {$b}\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
