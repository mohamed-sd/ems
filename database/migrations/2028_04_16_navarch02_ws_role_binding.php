<?php
/**
 * 2028_04_16_navarch02_ws_role_binding.php — ربطُ الأدوارِ الفرعيّةِ بمساحاتِ
 * إداراتِها الأمّ (§6 · §21-② · NAV_ARCH_02_CLEAN §١·٤)
 * @migration-objects: nav_ws_roles.parent_role_id, nav_ws_roles.ruling,
 *                     nav_ws_roles rows (SECONDARY bindings)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ قبلَ الحكم**: ثمانيةَ عشرَ دورًا من خمسةٍ وثلاثين لها ربطٌ
 *   `PRIMARY` بمساحة، **وستةَ عشرَ دورًا نشطًا بلا مساحةٍ يحملون 1,031 رابطًا**
 *   (مقيسٌ بالجمعِ حرفًا: 74+92+58+66+65+83+113+145+109+107+28+30+29+10+11+11).
 *   ومن لا مساحةَ له **لا يُقلَب** (`navarch_cutover_workspace` يرجع `null`)،
 *   فيبقى على المسارِ القديمِ إلى الأبد — وهو ما يمنع بلوغَ `on`.
 *
 * ◆ **⛔ ولا تُنشأ مساحةٌ لأنَّ دورًا موجود** — §6 حرفًا: «ولا يسمح بإنشاء
 *   Department بسبب وجود Role». فالأدوارُ تُربَط **بمساحاتٍ قائمة**.
 *
 * ⭐ **والمصدرُ الحاكمُ للأمِّ في المخطَّطِ نفسِه**: `roles.parent_role_id`.
 *   أربعةَ عشرَ دورًا من الستةَ عشرَ لها أبٌ مسجَّلٌ **وكلُّ أبٍ مربوطٌ
 *   `PRIMARY` بمساحة** — فالمساحةُ تُقرأ ولا تُخترَع. والاثنانِ الباقيانِ
 *   بحكمٍ مكتوبٍ في الصفِّ نفسِه (‏العمود `ruling`).
 *
 * ◆ **و`binding` مفرداتُه قائمةٌ سلفًا**: `enum('PRIMARY','SECONDARY')` —
 *   و`SECONDARY` **لم يُستعمل قطُّ (صفرُ صفّ)**. فالمخطَّطُ يحمل الحلَّ ولا
 *   يحتاج مفردةً جديدةً تُسقِط صفوفًا عند قرّائها [[enum-vocabulary-consumers]].
 *
 * ◆ **و`primary_role` عمودٌ مولَّدٌ** (`if(binding='PRIMARY',role_id,NULL)`)
 *   يخدم `uq_one_primary` — ⛔ **فلا يُكتب فيه**. ولذلك عمودٌ حقيقيٌّ جديدٌ
 *   `parent_role_id` يحمل دورَ الأمِّ الذي بُرِّر به الربطُ الفرعيّ.
 *
 * ⛔ ولا يُحذَف صفٌّ ولا عمودٌ هنا — إضافةٌ محضة. والعكسُ في `_down.php`.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$addCol = function ($table, $col, $ddl) use ($conn) {
    $q = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    if ($q && $q->num_rows) { echo "= {$table}.{$col} قائمٌ سلفًا\n"; return; }
    if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN {$ddl}")) { echo "+ {$table}.{$col}\n"; }
    else { echo "x {$table}.{$col}: " . $conn->error . "\n"; }
};

/* ═══ ① عمودا التبرير ═══════════════════════════════════════════════════════ */
$addCol('nav_ws_roles', 'parent_role_id',
        "`parent_role_id` int(11) DEFAULT NULL COMMENT 'دورُ الأمِّ الذي بُرِّر به الربطُ SECONDARY — من roles.parent_role_id'");
$addCol('nav_ws_roles', 'ruling',
        "`ruling` varchar(400) NOT NULL DEFAULT '' COMMENT 'حكمُ الربطِ مكتوبًا في الصفِّ نفسِه — ⛔ ولا صفَّ بلا حكم'");

/* ═══ ② الربطُ الفرعيُّ — الأمُّ من `roles.parent_role_id` وحدَها ══════════════
   ولا يُربَط دورٌ إلّا إن كان أبوه مربوطًا `PRIMARY` بمساحةٍ نشطة.           */
$prim = array();
$r = $conn->query("SELECT wr.workspace_id, wr.role_id FROM nav_ws_roles wr
                     JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.active = 1
                    WHERE wr.binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $prim[(int) $x['role_id']] = $x['workspace_id']; }

/* ⛔ **وثلاثةُ أحكامٍ مكتوبةٍ لمن لا أبَ له في المخطَّط** — ولا رابعَ لها،
   وكلُّ حكمٍ مقيسٌ بمصدرِه لا مُستنتَجٌ من اسمِ الدور (‏الشرحُ عند كلٍّ أدناه):
   5 ⇒ `DEP-12` باسمِ الدورِ نفسِه · 15 ⇒ `DEP-08` بتقاطعِ الصلاحيّةِ مع ورقتِها ·
   32 ⇒ `DEP-05` بجدولِ §١·٤ من أمرِ الجولة. */
$byRuling = array(
    /* ◆ 5 «إدارة الموقع (قديم — مدمج في 6)» **اسمُه يحمل حكمَه**: الدمجُ في
         الدورِ 6، والدورُ 6 مربوطٌ `PRIMARY` بـ`DEP-12`. صفرُ مستخدمٍ يحمله
         وصفرُ رابطٍ حيٍّ له — فالربطُ لا يُصيِّر شيئًا، لكنَّه يمنع دورًا
         ساكنًا من البقاءِ على المسارِ القديمِ أبدًا بعد بلوغِ `on`. */
    5  => array('DEP-12',      'اسمُ الدورِ في `roles` يحمل حكمَه: «إدارة الموقع (قديم — مدمج في 6)» — والدورُ 6 مربوطٌ PRIMARY بـDEP-12 · صفرُ مستخدمٍ وصفرُ رابطٍ حيّ'),
    /* ◆ 15 «إدارة الصلاحيات» ⇒ `DEP-08` «إدارة الحوكمة والالتزام» **بالقياسِ
         لا بالاسم**: ورقةُ `DEP-08` في الدليلِ ترسم **31 بندَ دورةٍ** بستّةِ
         رؤوسِ طيٍّ، **ولا دورَ يمثّلها** — وهو البندُ الثاني في سجلِّ ما رُفع
         إلى المالكِ في الجولةِ السابقة. والدورُ 15 **مصرَّحٌ له بثلاثين من
         الواحدِ والثلاثين**، والتالي له ثمانيةٌ فقط. وأكبرُ رؤوسِ طيِّه في
         التصييرِ القديمِ «الحوكمة والتدقيق» حرفًا.
       ⛔ **وليس هذا إنشاءَ إدارةٍ لوجودِ دور** (§6): `DEP-08` قائمةٌ في
         `nav_workspaces` بورقةٍ كاملةٍ سلفًا — **الدورُ يُربَط بمساحةٍ قائمة**،
         وهو عينُ ما يأمر به §6.
       ◆ **و`PRIMARY` لأنَّ المساحةَ بلا رائد** — و`uq_one_primary` يضمن الوحدة.
       ⛔ **و`WS-PLATFORM` تبقى بلا دورٍ**: لا ورقةَ لها في الدليلِ ولا دورةَ
         عملٍ — فلا تُصيَّر، ولا يُخترَع لها هدفٌ (§17). */
    15 => array('DEP-08', 'PRIMARY', 'ورقةُ DEP-08 في الدليلِ: 31 بندَ دورةٍ بستّةِ رؤوسٍ ولا دورَ يمثّلها · والدورُ 15 مصرَّحٌ له بـ30 منها (‏التالي 8) · §6: الدورُ يُربَط بمساحةٍ قائمةٍ ولا يُنشئها'),
    32 => array('DEP-05',      'NAV_ARCH_02_CLEAN §١·٤ جدولُ الأدوارِ الفرعيّة — «32 المدير الماليّ ⇒ DEP-05 المالية» · roles.parent_role_id فارغ'),
);

$ins = $conn->prepare(
    "INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref, parent_role_id, ruling)
          VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE binding = VALUES(binding),
                             parent_role_id = VALUES(parent_role_id), ruling = VALUES(ruling)");

/* ⛔ **وصفٌّ سابقٌ نُسخ حكمُه يُزال**: الدورُ 15 رُبط أوّلاً
   بـ`WS-PLATFORM` ثمَّ قِيس أنَّ ورقتَه `DEP-08` — فيُحذف الرباطُ المنسوخ،
   ⛔ ولا يبقى دورٌ بمساحتَين يتأرجح القارئُ بينهما. */
$conn->query("DELETE FROM nav_ws_roles WHERE role_id = 15 AND workspace_id = 'WS-PLATFORM'");

$n = 0; $skipped = array();
$r = $conn->query("SELECT r.id, r.name, r.parent_role_id,
                          (SELECT COUNT(*) FROM nav_items n WHERE n.role_id = r.id AND n.active = 1) links
                     FROM roles r ORDER BY r.id");
while ($x = $r->fetch_assoc()) {
    $rid = (int) $x['id'];
    if (isset($prim[$rid])) { continue; }                 /* له `PRIMARY` سلفًا */
    $par = (int) $x['parent_role_id'];
    $ws = null; $ruling = ''; $parRef = null; $bind = 'SECONDARY';

    if ($par && isset($prim[$par])) {
        $ws = $prim[$par]; $parRef = $par;
        $ruling = 'roles.parent_role_id = ' . $par . ' — والأبُ مربوطٌ PRIMARY بـ' . $ws
                . ' · §6: الأدوارُ تُربَط بمساحاتٍ قائمةٍ ولا تُنشئها';
    } elseif (isset($byRuling[$rid])) {
        $ws = $byRuling[$rid][0];
        /* ثلاثيّةٌ ⇒ الوسطُ رباطٌ صريح */
        if (count($byRuling[$rid]) === 3) { $bind = $byRuling[$rid][1]; $ruling = $byRuling[$rid][2]; }
        else { $ruling = $byRuling[$rid][1]; }
    } else {
        /* ⛔ **ولا صفَّ بلا حكم**: من لا أبَ له ولا رابطَ حيًّا لا يُربَط،
           ويُسمّى هنا بسببِه — 5 «إدارة الموقع (قديم — مدمج في 6)» صفرُ رابط. */
        $skipped[] = $rid . ' «' . $x['name'] . '» روابط=' . (int) $x['links'];
        continue;
    }
    /* `source_ref` يحمل المصدرَ، و`ruling` يحمل الحكمَ نفسَه */
    $src = 'NAV_ARCH_02_CLEAN §١·٤ · roles.parent_role_id';
    $ins->bind_param('sissis', $ws, $rid, $bind, $src, $parRef, $ruling);
    if ($ins->execute()) { $n++; echo "+ دور {$rid} ⇒ {$ws} · {$bind} · أب=" . ($parRef ?: '—') . "\n"; }
    else { echo "x دور {$rid}: " . $conn->error . "\n"; }
}
$ins->close();
echo "= رُبط {$n} دورًا فرعيًّا SECONDARY\n";
foreach ($skipped as $s) { echo "· لم يُربَط: {$s} — بلا أبٍ في المخطَّطِ وبلا رابطٍ حيّ\n"; }

/* ═══ ③ حكمُ الربطِ `PRIMARY` القائمِ يُكتب أيضًا — ولا صفَّ بلا حكم ═════════ */
$conn->query("UPDATE nav_ws_roles SET ruling = CONCAT('§6 — الدورُ يقود المساحةَ ', workspace_id,
                                                      ' · ربطٌ PRIMARY واحدٌ لا يتكرّر (uq_one_primary)')
               WHERE binding = 'PRIMARY' AND ruling = ''");
echo "= أحكامُ PRIMARY: " . $conn->affected_rows . " صفًّا\n";

$q = $conn->query("SELECT binding, COUNT(*) n FROM nav_ws_roles GROUP BY binding");
while ($x = $q->fetch_assoc()) { echo "  {$x['binding']} = {$x['n']}\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
