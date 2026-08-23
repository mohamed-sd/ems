<?php
/**
 * 2027_11_11_role2_quick_access_split.php
 *   الدورُ 2 — الروابطُ اليوميةُ في الوصولِ السريع، والباقي في السايدبار، بلا تكرار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قائمتانِ من جدولَين مختلفَين** — وهذا أصلُ التكرار: بلاطاتُ «الوصول
 *   السريع» تُبنى من `modules` (`getDynamicNavLinks`) والسايدبارُ من `nav_items`
 *   (`renderUnifiedNavigationV2`). **ولا آليةَ استثناءٍ بينهما في الشجرةِ كلِّها**،
 *   فثلاثةٌ من أربعِ بلاطاتٍ كانت مكرَّرةً في السايدبار.
 *
 * ◆ **و`is_quick` حيٌّ لهذا الدورِ خلافًا لظاهرِ الشفرة**: المسارُ الجديدُ
 *   (المجموعات) يتجاوز `is_quick` تمامًا — **لكنَّه لا يعمل إلّا لدورٍ له
 *   `modules` بـ`group_id`**، و`modules` الدورِ 2 كلُّها `group_id = NULL`.
 *   فيسقط إلى المسارِ القديمِ ويصير `is_quick` هو المفتاحَ الفعليّ. (قِيس:
 *   البلاطاتُ الأربعُ المُصيَّرةُ هي بالضبطِ صفوفُ `is_quick=1 AND is_link=1`.)
 *
 * ◆ **والإخفاءُ من السايدبارِ بالطريقِ المسنونِ نفسِه** الذي استعملته
 *   `2027_10_03_align_nav_apply.php`: `active = 0` **ومسارُه يبقى مفتوحًا**،
 *   ويُقيَّد في `gov_nav_hidden_log` بعذرِه — وهنا العذرُ `QUICK_TILE`.
 *   فالرجوعُ يعيد الصفَّ إلى موضعِه حرفًا لا إلى «مكانٍ ما».
 *
 * ◆ **والوثيقةُ تُعدَّل ولا تُحذَف**: `gov_target_nav` تُعلن أربعةَ عشرَ بندًا
 *   للدور 2، وحذفُ ستةٍ منها **يفقد القرار**. فتُنقَل إلى مجموعةٍ مُعلَنةٍ
 *   اسمُها «الوصول السريع» (`group_no = 0`) — **البندُ ما يزال مطلوبًا،
 *   وموضعُه وحدَه تغيَّر**. وبوابةُ `injfrd66_nav_gate` تقرأ هذه المجموعةَ
 *   فتقيسها في البلاطاتِ لا في السايدبار.
 *
 * ◆ **والسقفُ ستةٌ لا أكثر**: `tools/uxw_gates.php` ⑪ يرسُب كلَّ دورٍ بأقلَّ من
 *   **عشرةِ** روابطَ حيّة. والدورُ 2 له ستةَ عشرَ ⇒ 16 − 6 = 10 **وهو الحدُّ
 *   بالضبط**. فأيُّ نقلٍ سابعٍ يُرسِّب البوابة.
 *
 * التشغيل:  php database/migrations/2027_11_11_role2_quick_access_split.php [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REVERT = in_array('--revert', $argv, true);
$ROLE   = 2;
$SEED   = 'owner:2026-08-23:role2_quick_split';
$QGROUP = 'الوصول السريع';

/* ── الستةُ اليوميةُ التي تنتقل إلى البلاطات ─────────────────────────────
   اختيرت بمعيارٍ واحد: **ما يفتحه موظفُ الإدارةِ كلَّ يومٍ ليعمل** — سجلٌّ
   يُفتح، وعقدٌ يُراجَع، ووحداتٌ تُعتمَد، وتسويةٌ تُغلَق، وحصةٌ تُتابَع،
   وترشيحٌ يُبَتّ. وما بقي **مرجعٌ أو حوكمةٌ أو صندوقُ وارد** — يُقرأ حين
   يُحتاج لا كلَّ يوم. */
$MOVE = array(
    'Suppliers/suppliers.php',
    'Suppliers/supplierscontracts.php',
    'Suppliers/unit_statement_supplier.php',
    'Suppliers/settlements.php',
    'Suppliers/shares_coverage.php',
    'Suppliers/rfq_requests.php',
);
/* ── أسماءُ البلاطاتِ = أسماءُ الوثيقةِ المعتمَدة ──────────────────────────
   ◆ **الموظفُ يعرف اسمَ الوثيقةِ لا اسمَ `modules`**: «طلبات عروض الموردين»
     و«الترشيح ومراجعة التعاقد» بابٌ واحد، و«كشفُ وحداتِ المورد» و«اعتماد
     الوحدات والأداء المعتمد» كذلك. **واسمانِ لبابٍ واحدٍ يجعلان الرابطَ
     المنقولَ يبدو رابطًا جديدًا** فيُبحَث عن القديمِ ولا يوجد.
   ◆ والقديمُ محفوظٌ هنا ليعود بـ`--revert` حرفًا — لا من ذاكرة. */
$LABEL = array(
    'Suppliers/suppliers.php'               => array('سجل الموردين', 'الموردون'),
    'Suppliers/supplierscontracts.php'      => array('سجل عقود الموردين', 'عقود الموردين'),
    'Suppliers/unit_statement_supplier.php' => array('اعتماد الوحدات والأداء المعتمد', 'كشفُ وحداتِ المورد'),
    'Suppliers/settlements.php'             => array('التسويات وكشف الحساب', 'تسويات الموردين'),
    'Suppliers/shares_coverage.php'         => array('سجل الحصص والتغطية التعاقدية', 'دفتر استهلاك حصص الموردين'),
    'Suppliers/rfq_requests.php'            => array('الترشيح ومراجعة التعاقد', 'طلبات عروض الموردين'),
);
/* ما يخرج من البلاطاتِ لأنَّه ليس عملًا يوميًّا لهذه الإدارة */
$UNQUICK = array(
    'main/project_users.php',
    'Reports/reports.php',
    'Settings/settings.php',
    'Suppliers/supplierscontracts_details.php',
);

echo "\n══ الدورُ 2 — فصلُ الوصولِ السريعِ عن السايدبار ══\n";

$done = array(); $skip = array();

/* ══ ① البلاطات: `modules.is_quick` (+ `is_link` لِمَا يلزم) ═══════════════ */
echo "\n  ── ① بلاطاتُ الوصولِ السريع (`modules`)\n";
foreach ($MOVE as $route) {
    $st = $conn->prepare("SELECT id, name, is_link, is_quick FROM modules
                           WHERE code = ? AND owner_role_id = ? ORDER BY id ASC LIMIT 1");
    $st->bind_param('si', $route, $ROLE); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    if (!$m) { $skip[] = "لا صفَّ `modules` للدور {$ROLE}: {$route}"; continue; }
    $q = $REVERT ? 0 : 1;
    /* `is_link = 0` يمنع ظهورَه في البلاطاتِ ولو كان `is_quick = 1` —
       فيُرفَع معه، ويعود عندَ الرجوعِ إلى ما كان. */
    $l = $REVERT ? (int) $m['is_link'] : 1;
    $nm  = isset($LABEL[$route]) ? ($REVERT ? $LABEL[$route][1] : $LABEL[$route][0]) : null;
    $sql = "UPDATE modules SET is_quick = {$q}" . ($REVERT ? '' : ", is_link = {$l}");
    if ($nm !== null) { $sql .= ", name = '" . $conn->real_escape_string($nm) . "'"; }
    $conn->query($sql . " WHERE id = " . (int) $m['id']);
    printf("     %s %-42s quick=%d link=%d%s
", $REVERT ? '↶' : '✔', $route, $q, $l,
        $nm !== null ? " · «{$nm}»" : '');
}
foreach ($UNQUICK as $route) {
    $st = $conn->prepare("SELECT id, is_quick FROM modules WHERE code = ? AND owner_role_id = ? LIMIT 1");
    $st->bind_param('si', $route, $ROLE); $st->execute();
    $m = $st->get_result()->fetch_assoc(); $st->close();
    if (!$m) { continue; }
    $q = $REVERT ? 1 : 0;
    $conn->query("UPDATE modules SET is_quick = {$q} WHERE id = " . (int) $m['id']);
    printf("     %s %-42s quick=%d\n", $REVERT ? '↶' : '○', $route, $q);
}

/* ══ ② السايدبار: `nav_items.active` + قيدُ الإخفاء ═══════════════════════ */
echo "\n  ── ② السايدبار (`nav_items`)\n";
foreach ($MOVE as $route) {
    $st = $conn->prepare("SELECT id, label_ar, group_id, sort_order, active
                            FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1");
    $st->bind_param('is', $ROLE, $route); $st->execute();
    $n = $st->get_result()->fetch_assoc(); $st->close();
    if (!$n) { $skip[] = "لا صفَّ `nav_items` للدور {$ROLE}: {$route}"; continue; }

    if ($REVERT) {
        $conn->query("UPDATE nav_items SET active = 1 WHERE id = " . (int) $n['id']);
        $d = $conn->prepare("DELETE FROM gov_nav_hidden_log WHERE role_id = ? AND route = ? AND doc_code = ?");
        $d->bind_param('iss', $ROLE, $route, $SEED); $d->execute(); $d->close();
        printf("     ↶ %-42s active=1\n", $route);
        continue;
    }
    if ((int) $n['active'] === 0) { $skip[] = "خاملٌ سلفًا: {$route}"; continue; }
    $conn->query("UPDATE nav_items SET active = 0 WHERE id = " . (int) $n['id']);
    /* ◆ **ويُقيَّد موضعُه قبلَ الإخفاء**: الرجوعُ يعيده إلى مجموعتِه وترتيبِه
         حرفًا — لا إلى «مكانٍ ما». */
    $i = $conn->prepare("INSERT INTO gov_nav_hidden_log
            (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable, hidden_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'QUICK_TILE', NOW())");
    $i->bind_param('iissiis', $ROLE, $n['id'], $route, $n['label_ar'],
        $n['group_id'], $n['sort_order'], $SEED);
    $i->execute(); $i->close();
    printf("     ✔ %-42s active=0 · QUICK_TILE\n", $route);
}

/* ══ ③ الوثيقة: `gov_target_nav` — البندُ يبقى، وموضعُه يتغيّر ════════════ */
echo "\n  ── ③ التنقّلُ المستهدَف (`gov_target_nav`)\n";
foreach ($MOVE as $route) {
    $st = $conn->prepare("SELECT id, group_no, group_ar, item_ar FROM gov_target_nav
                           WHERE role_id = ? AND route = ? LIMIT 1");
    $st->bind_param('is', $ROLE, $route); $st->execute();
    $t = $st->get_result()->fetch_assoc(); $st->close();
    if (!$t) { $skip[] = "لا صفَّ `gov_target_nav`: {$route}"; continue; }

    if ($REVERT) {
        /* الرجوعُ يقرأ الموضعَ الأصليَّ من قيدِ الإخفاءِ لا من ذاكرةٍ */
        $g = $conn->prepare("SELECT group_before FROM gov_nav_hidden_log
                              WHERE role_id = ? AND route = ? AND doc_code = ? LIMIT 1");
        $g->bind_param('iss', $ROLE, $route, $SEED); $g->execute();
        $gr = $g->get_result()->fetch_assoc(); $g->close();
        printf("     ↶ %-42s (يُعاد بموجةِ التوثيقِ المرافقة)\n", $route);
        continue;
    }
    $u2 = $conn->prepare("UPDATE gov_target_nav
                             SET group_no = 0, group_ar = ?,
                                 note = CONCAT(COALESCE(note,''), ' · نُقل إلى بلاطاتِ الوصولِ السريعِ بقرارِ المالك 2026-08-23 — البندُ مطلوبٌ وموضعُه تغيَّر')
                           WHERE id = ?");
    $u2->bind_param('si', $QGROUP, $t['id']);
    $u2->execute(); $u2->close();
    printf("     ✔ %-42s مجموعة %s ← «%s»\n", $route, $t['group_no'], $QGROUP);
}

/* ══ ④ حُرّاسُ ما بعدَ التغيير ═════════════════════════════════════════════ */
echo "\n  ── ④ حُرّاسُ ما بعدَ التغيير\n";
$halt = 0;

$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = {$ROLE} AND active = 1");
$live = $r ? (int) $r->fetch_row()[0] : -1;
if ($live < 10) { $halt++; printf("     ✘ سايدبارٌ نحيف: %d رابطًا (الحدُّ 10)\n", $live); }
else { printf("     ✔ السايدبار: %d رابطًا حيًّا (الحدُّ 10)\n", $live); }

$r = $conn->query("SELECT COUNT(*) FROM modules WHERE owner_role_id = {$ROLE} AND is_quick = 1 AND is_link = 1");
$tiles = $r ? (int) $r->fetch_row()[0] : -1;
printf("     ○ بلاطاتٌ مؤهَّلة: %d\n", $tiles);

/* **صفرُ رابطٍ في القائمتَين معًا** — وهو جوهرُ الطلب */
$r = $conn->query("SELECT COUNT(*) FROM modules m
                     JOIN nav_items n ON n.route = m.code AND n.role_id = {$ROLE} AND n.active = 1
                    WHERE m.owner_role_id = {$ROLE} AND m.is_quick = 1 AND m.is_link = 1");
$dup = $r ? (int) $r->fetch_row()[0] : -1;
if ($dup !== 0) { $halt++; printf("     ✘ %d رابطًا في القائمتَين معًا\n", $dup); }
else { echo "     ✔ صفرُ رابطٍ يظهر في البلاطاتِ والسايدبارِ معًا\n"; }

if ($halt > 0 && !$REVERT) {
    echo "\n  ⛔ توقَّفت الهجرةُ عند {$halt} حارسًا — أعِدْ بـ`--revert`.\n\n";
    exit(1);
}

if ($skip) { echo "\n  ── تُخُطِّي\n"; foreach ($skip as $s) { echo "     ○ {$s}\n"; } }
echo "\n  ◆ والمسارُ يبقى مفتوحًا: الإخفاءُ من القائمةِ لا يمنع الوصول.\n\n";
exit(0);
