<?php
/**
 * tools/repair01_w2_apply.php
 *   REPAIR01 · W02 — بناءُ السجلِّ المعياريِّ وحسمُ الأشباح
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عاديُّ التشغيل** (idempotent): يُعاد بلا أثرٍ جانبيّ. ومفاتيحُ كلِّ صفٍّ
 *   **أعمالٌ لا أرقامُ صفوف** — `screen_id` يُحفظ عبر الجولاتِ ولا يُعاد
 *   ترقيمُه (⛔ المحظورُ الثالثُ في §٥ من ملفِّ المرحلة).
 *
 * ◆ **والترتيبُ هو ترتيبُ §٣٥ ولا يُخالَف**:
 *     ① Screen_ID ← ② Route ← ③ Owner ← ④ Lifecycle ← ⑤ Parent/Tab
 *     ← ⑥ Visibility ← ⑦ الحارس ← ⑧ حسمُ الأشباح ← ⑨ ربطُ دفترِ الأسطح
 *   وكلُّ خطوةٍ تقرأ ما قبلَها ولا تقرأ ما بعدَها — فلا حلقةٌ خفيّة.
 *
 * ◆ **ولا قيمةَ بلا قاعدةٍ مُعلَنة**: كلُّ عمودٍ يحمل `*_rule` باسمِ قاعدتِه؛
 *   وما لا قاعدةَ له **يُسجَّل قرارًا** في `repair01_w2_decisions` ويُشار إليه
 *   بـ`W2_DECISION:…` — لا يُخمَّن ولا يُترك فارغًا (نمطُ W01 §٤-٣).
 *
 * ◆ **والأشباحُ تُوسَم ولا تُحذف**: الحذفُ يمحو الدليل. كلُّ ملفٍّ من الـ١٦٠
 *   إمّا `RETIRE_*` بعذرٍ مكتوبٍ أو `MOVED_TO_TARGET_GAPS` بموجتِه — والموجةُ
 *   من قاعدةِ W00 نفسِها (`repair01_w2_wave_for_code`) لا من اجتهادٍ هنا.
 *
 * التشغيل:  php tools/repair01_w2_apply.php  [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w2_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');

$DRY = in_array('--dry', $argv, true);
function say($s) { echo $s . "\n"; }
function q(mysqli $c, $sql) { $r = $c->query($sql); if ($r === false) { echo "SQL ✘ {$c->error}\n  $sql\n"; } return $r; }
function esc(mysqli $c, $s) { return $c->real_escape_string((string) $s); }
function esc2(mysqli $c, $s) { return "'" . $c->real_escape_string((string) $s) . "'"; }

say("═══ REPAIR01 · W02 — بناءُ السجلِّ المعياريِّ ═══");

/* ═══════════════════════════════════════════════════════════════════════════
 * ما يُقرأ مرّةً واحدة
 * ═════════════════════════════════════════════════════════════════════════ */
$phpFiles = repair01_w2_php_files($ROOT);
$incMap   = repair01_w2_include_map($phpFiles);
$bearers  = repair01_w2_shell_bearers($incMap);
$live = repair01_w2_live_screens($ROOT);                     /* المسارُ ⇐ شيفرةٌ بلا تعليقات */
$liveLc = array();                                           /* المسارُ بحروفٍ صغيرة ⇐ المسارُ الأصليّ */
foreach ($live as $rel => $clean) { $liveLc[strtolower($rel)] = $rel; }
$phpLc = array();
foreach ($phpFiles as $rel => $clean) { $phpLc[strtolower($rel)] = $rel; }
say('الشاشاتُ الحيّةُ على القرص: ' . count($live) . ' (من ' . count($phpFiles) . ' ملفَّ PHP إنتاجيّ · حاملُ قشرةٍ ' . count($bearers) . ')');

/* دفترُ الأسطح — الحبّةُ فيه (شاشةٌ × دورة)، فتُطوى إلى الشاشة */
/* ⚠ المفتاحُ **بحروفٍ صغيرة**: نظامُ الملفّاتِ هنا لا يفرِّق حالةَ الحرف،
   و`nav_items` تكتب `clients/clients.php` حيث يكتب القرصُ `Clients/clients.php`.
   ومفتاحٌ حسّاسٌ للحالةِ يجعل الشاشةَ الواحدةَ **صفَّين** فيتضخَّم المقامُ
   (٩٧٢ بدل ٤٧٥) وتنقسم أحكامُها بينهما. */
$surf = array();          /* مفتاحُ الشاشةِ ⇐ صفٌّ ممثِّل */
$surfRows = array();      /* مفتاحُ الشاشةِ ⇐ [ids] */
$r = q($conn, "SELECT id, screen_file, disk_path, on_disk, canonical_code, resp_role, screen_title,
                      stage_kind, stage_order, stage_name, layer_name, src_ref
               FROM repair01_surfaces ORDER BY id");
while ($r && $x = $r->fetch_assoc()) {
    $key = $x['on_disk'] ? strtolower(repair01_w2_norm_route($x['disk_path']))
                         : 'ghost:' . strtolower($x['screen_file']);
    $surfRows[$key][] = (int) $x['id'];
    if (!isset($surf[$key])) { $surf[$key] = $x; }
}
say('مفاتيحُ دفترِ الأسطح: ' . count($surf) . ' (من ' . array_sum(array_map('count', $surfRows)) . ' صفًّا)');

/* السجلُّ المعياريُّ للتنقّلِ — المالكُ والاسم */
$canon = array();
$r = q($conn, "SELECT route, canonical_ar, owner_dept, status FROM nav_canonical");
while ($r && $x = $r->fetch_assoc()) { $canon[strtolower(repair01_w2_norm_route($x['route']))] = $x; }

/* جسرُ المسمّياتِ الحيّةِ إلى الرموزِ المعيارية
   ⛔ **والوحدةُ المشقوقةُ تُستثنى من هذه الخريطة** (‏RPR-W10 · `W10-D-03`):
      مفتاحُها اسمٌ حيٌّ **مكرَّرٌ صفَّين** في الجسر (‏شقٌّ أيمنُ وشقٌّ أيسر)، وخريطةٌ
      بمفتاحِ الاسمِ تجعل آخرَ صفٍّ يدهس أوّلَه — **فيصير الشقُّ محسومًا بترتيبِ
      الصفوفِ لا بمعناه**، وهو ما أسند واحدًا وخمسينَ سطحًا إلى الشقِّ الخطأ صامتًا.
      والحلُّ الصحيحُ في `repair01_w10_split` سطحًا سطحًا بقاعدةٍ ومرساة. */
$cross = array(); $splitNames = array();
$r = q($conn, "SELECT legacy_name, canonical_code, verdict FROM repair01_dept_crosswalk");
while ($r && $x = $r->fetch_assoc()) {
    $nm = trim($x['legacy_name']);
    if ($x['verdict'] === 'SPLIT') { $splitNames[$nm] = true; continue; }
    $cross[$nm] = $x['canonical_code'];
}
/* حلُّ الشقِّ من دفترِ W10 — والغيابُ يترك المالكَ للقاعدةِ التالية لا يخمّنه */
$w10Split = array();
$hasSplit = q($conn, "SHOW TABLES LIKE 'repair01_w10_split'");
if ($hasSplit && $hasSplit->num_rows > 0) {
    $r = q($conn, "SELECT route, resolved_code FROM repair01_w10_split WHERE route <> ''");
    while ($r && $x = $r->fetch_assoc()) {
        $w10Split[strtolower(repair01_w2_norm_route($x['route']))] = $x['resolved_code'];
    }
}
/** رمزُ الإدارةِ من اسمٍ حيٍّ — والمشقوقُ يُحَلُّ بمسارِه لا باسمِه */
$resolveDept = function ($nm, $routeKey) use (&$cross, &$splitNames, &$w10Split, &$deptByName) {
    if (isset($splitNames[$nm])) { return $w10Split[$routeKey] ?? ''; }
    return $cross[$nm] ?? ($deptByName[$nm] ?? '');
};
$deptByName = array();
$r = q($conn, "SELECT canonical_code, name_ar FROM repair01_departments");
while ($r && $x = $r->fetch_assoc()) { $deptByName[trim($x['name_ar'])] = $x['canonical_code']; }

/* مساراتُ التنقّلِ الحيّة */
$navActive = array(); $navAny = array();
$r = q($conn, "SELECT route, active, role_id, label_ar FROM nav_items");
while ($r && $x = $r->fetch_assoc()) {
    $k = strtolower(repair01_w2_norm_route($x['route']));
    if ($k === '' || substr($k, -4) !== '.php') { continue; }
    $navAny[$k][] = $x;
    if ((int) $x['active'] === 1) { $navActive[$k][] = $x; }
}

/* مساحاتُ الأدوارِ — الدورُ ⇐ المسمّى الحيّ */
$roleSpace = array();
$r = q($conn, "SELECT role_id, space_ar FROM gov_space_roles");
while ($r && $x = $r->fetch_assoc()) { $roleSpace[(int) $x['role_id']] = trim($x['space_ar']); }

/* عذرُ الإخفاء — الأبُ حين يكون البندُ تبويبًا */
$hidden = array();
$r = q($conn, "SELECT route, reachable, label_ar FROM gov_nav_hidden_log");
while ($r && $x = $r->fetch_assoc()) { $hidden[strtolower(repair01_w2_norm_route($x['route']))] = $x; }

/* سجلُّ الدورةِ الحيّ — من له صفٌّ فيه */
$cycleFiles = array();
$r = q($conn, "SELECT DISTINCT screen_file FROM gov_screen_cycle");
while ($r && $x = $r->fetch_row()) { $cycleFiles[strtolower(basename((string) $x[0]))] = true; }

/* ═══ ① Screen_ID + ② Route — بناءُ المقامِ ثمّ الترقيم ══════════════════ */
$U = array();   /* مفتاحٌ ⇐ صفُّ السجلّ */

/* (أ) دفترُ الأسطحِ — المصدرُ الأولُ وأوسعُه حكمًا */
foreach ($surf as $key => $x) {
    $isGhost = !((int) $x['on_disk']);
    $U[$key] = array(
        'screen_file' => $isGhost ? $x['screen_file'] : basename(repair01_w2_norm_route($x['disk_path'])),
        'route'       => $isGhost ? null : repair01_w2_norm_route($x['disk_path']),
        'route_rule'  => $isGhost ? 'NOT_BUILT_NO_ROUTE' : 'SURFACE_DISK_PATH',
        'on_disk'     => $isGhost ? 0 : 1,
        'origin'      => 'SURFACES',
        'src_ref'     => (string) $x['src_ref'],
        'title'       => (string) $x['screen_title'],
        'owner_code'  => (string) $x['canonical_code'],
        'owner_role'  => (string) $x['resp_role'],
        'owner_rule'  => 'W01_SURFACE_REGISTER',
    );
}
/* (ب) الشاشاتُ الحيّةُ على القرصِ التي لا صفَّ لها في الدفتر — والقرصُ هو
       **مرجعُ حالةِ الحرف**: مسارُه يغلب ما كتبه السجلُّ أو التنقّل. */
foreach ($live as $rel => $clean) {
    $route = repair01_w2_norm_route($rel);
    $key = strtolower($route);
    if (isset($U[$key])) { $U[$key]['on_disk'] = 1; $U[$key]['route'] = $route; continue; }
    $U[$key] = array(
        'screen_file' => basename($route), 'route' => $route, 'route_rule' => 'DISK_SCAN',
        'on_disk' => 1, 'origin' => 'DISK', 'src_ref' => 'قياسٌ حيٌّ على القرص',
        'title' => '', 'owner_code' => '', 'owner_role' => '', 'owner_rule' => '',
    );
}
/* (ج) مساراتُ التنقّلِ الحيّةُ التي لا ملفَّ لها ولا صفَّ — تُسجَّل ولا تُخفى */
foreach ($navActive as $k => $rows) {
    if (isset($U[$k])) { continue; }
    $onDisk = is_file($ROOT . '/' . $k) ? 1 : 0;
    $U[$k] = array(
        'screen_file' => basename($k), 'route' => $k, 'route_rule' => 'NAV_ACTIVE_ROUTE',
        'on_disk' => $onDisk, 'origin' => 'NAV', 'src_ref' => 'nav_items · active=1',
        'title' => (string) $rows[0]['label_ar'], 'owner_code' => '', 'owner_role' => '', 'owner_rule' => '',
    );
}
say('مقامُ السجلِّ المعياريّ: ' . count($U) . ' شاشة');

/* الترقيم: المُعرَّفُ سلفًا يُحفظ · والجديدُ من أعلى رقمٍ + ١ بترتيبِ المفتاح */
$existing = array(); $maxNo = 0;
$r = q($conn, "SELECT screen_id, screen_file, route FROM repair01_screen_registry");
while ($r && $x = $r->fetch_assoc()) {
    $k = ($x['route'] !== null && $x['route'] !== '')
        ? strtolower(repair01_w2_norm_route($x['route']))
        : 'ghost:' . strtolower($x['screen_file']);
    $existing[$k] = $x['screen_id'];
    $n = (int) substr($x['screen_id'], 4);
    if ($n > $maxNo) { $maxNo = $n; }
}
$keys = array_keys($U); sort($keys, SORT_STRING);
$assignedNew = 0;
foreach ($keys as $k) {
    if (isset($existing[$k])) { $U[$k]['screen_id'] = $existing[$k]; continue; }
    $U[$k]['screen_id'] = sprintf('SCR-%04d', ++$maxNo); $assignedNew++;
}
say("① المُعرِّف: محفوظٌ " . (count($U) - $assignedNew) . " · جديدٌ $assignedNew");

/* ═══ ③ Owner — المالكُ بمصدرٍ مُعلَنٍ لكلِّ صفّ ═════════════════════════ */
$ownerStat = array(); $ownerless = array();
foreach ($keys as $k) {
    if ($U[$k]['owner_code'] !== '') { $ownerStat['W01_SURFACE_REGISTER'] = ($ownerStat['W01_SURFACE_REGISTER'] ?? 0) + 1; continue; }
    $lc = strtolower((string) $U[$k]['route']);
    /* ب) السجلُّ المعياريُّ للتنقّلِ يسمّي إدارةً مالكة */
    if (isset($canon[$lc]) && trim((string) $canon[$lc]['owner_dept']) !== '') {
        $nm = trim($canon[$lc]['owner_dept']);
        $code = $resolveDept($nm, $lc);
        if ($code !== '') {
            $U[$k]['owner_code'] = $code; $U[$k]['owner_role'] = $nm;
            $U[$k]['owner_rule'] = 'NAV_CANONICAL_OWNER';
            $ownerStat['NAV_CANONICAL_OWNER'] = ($ownerStat['NAV_CANONICAL_OWNER'] ?? 0) + 1;
            continue;
        }
    }
    /* ج) مساحةُ الدورِ الذي يحمل المسارَ حيًّا — دورٌ واحدٌ لا غير */
    if (isset($navActive[$lc])) {
        $spaces = array();
        foreach ($navActive[$lc] as $row) {
            $sp = $roleSpace[(int) $row['role_id']] ?? '';
            if ($sp !== '') { $spaces[$sp] = true; }
        }
        if (count($spaces) === 1) {
            $nm = key($spaces);
            $code = $resolveDept($nm, $lc);
            if ($code !== '') {
                $U[$k]['owner_code'] = $code; $U[$k]['owner_role'] = $nm;
                $U[$k]['owner_rule'] = 'NAV_SOLE_ROLE_SPACE';
                $ownerStat['NAV_SOLE_ROLE_SPACE'] = ($ownerStat['NAV_SOLE_ROLE_SPACE'] ?? 0) + 1;
                continue;
            }
        }
    }
    $ownerless[$k] = true;    /* يُحسم بالوراثةِ من الأبِ في ⑤، وإلّا فقرارٌ */
}

/* ═══ ④ Lifecycle ═════════════════════════════════════════════════════════ */
foreach ($keys as $k) {
    $u =& $U[$k];
    if (!$u['on_disk']) { $u['lifecycle'] = 'GHOST_TARGET'; $u['lifecycle_rule'] = 'NOT_ON_DISK'; unset($u); continue; }
    $bn = strtolower($u['screen_file']);
    if (isset($cycleFiles[$bn])) { $u['lifecycle'] = 'LIVE_REGISTERED'; $u['lifecycle_rule'] = 'IN_GOV_SCREEN_CYCLE'; }
    else { $u['lifecycle'] = 'LIVE_UNREGISTERED'; $u['lifecycle_rule'] = 'RP01_NO_CYCLE_ROW'; }
    unset($u);
}

/* ═══ ⑤ Parent / Tab ══════════════════════════════════════════════════════
   ثلاثةُ مصادرَ مرتَّبة — ولا يُخترع أبٌ بالتشابهِ الاسميّ:
     ① عذرُ الإخفاءِ المسجَّل `TAB_IN_PARENT` (وأبوه المِرساةُ التي تفتحه)
     ② سجلُّ تبويباتِ الكيانات `ems_entity_tabs_registry()`
     ③ المِرساةُ المقيسة: شاشةٌ حيّةٌ تحمل رابطًا حرفيًّا إليه — وتُفضَّل
        الشاشةُ التي هي نفسُها بندُ قائمة (البلوغُ يمرُّ بالمِرساةِ لا بالإخوة). */
$byFile = array();  /* اسمُ الملفِّ ⇐ [مفاتيح] */
foreach ($keys as $k) { $byFile[strtolower($U[$k]['screen_file'])][] = $k; }

$tabsReg = array();
if (is_file($ROOT . '/includes/entity_tabs.php')) {
    require_once $ROOT . '/includes/entity_tabs.php';
    if (function_exists('ems_entity_tabs_registry')) {
        foreach (ems_entity_tabs_registry() as $ent => $def) {
            $paths = array();
            foreach ((array) ($def['tabs'] ?? array()) as $p) {
                $p = strtolower(repair01_w2_norm_route((string) $p));
                if ($p !== '' && substr($p, -4) === '.php') { $paths[] = $p; }
            }
            if (count($paths) > 1) { $tabsReg[$ent] = $paths; }
        }
    }
}
/* المِرساةُ المقيسة: أيُّ شاشةٍ حيّةٍ تحمل رابطًا حرفيًّا إلى ملفٍّ آخر */
$anchors = array();   /* هدفٌ (اسمُ ملف) ⇐ [مصادر] */
foreach ($live as $rel => $clean) {
    $srcKey = strtolower(repair01_w2_norm_route($rel));
    if (preg_match_all('~href\s*=\s*["\'][^"\'>]{0,200}?([A-Za-z0-9_]+\.php)~i', $clean, $m)) {
        foreach (array_unique($m[1]) as $t) {
            $t = strtolower($t);
            if ($t === strtolower(basename($srcKey))) { continue; }
            $anchors[$t][$srcKey] = true;
        }
    }
}

$parentStat = array();
foreach ($keys as $k) {
    $u =& $U[$k];
    $u['parent_screen_id'] = ''; $u['parent_rule'] = '';
    if (!$u['on_disk']) { unset($u); continue; }
    $lc = strtolower((string) $u['route']);
    $bn = strtolower($u['screen_file']);
    $parentKey = '';
    $rule = '';
    /* ① عذرُ الإخفاء */
    if (isset($hidden[$lc]) && $hidden[$lc]['reachable'] === 'TAB_IN_PARENT') {
        $rule = 'HIDDEN_LOG_TAB_IN_PARENT';
    }
    /* ② سجلُّ تبويباتِ الكيانات — أوّلُ تبويبٍ هو المِرساةُ الأمّ */
    if ($parentKey === '') {
        foreach ($tabsReg as $paths) {
            $i = array_search($lc, $paths, true);
            if ($i !== false && $i > 0 && isset($U[$paths[0]])) { $parentKey = $paths[0]; $rule = 'ENTITY_TABS_REGISTRY'; break; }
        }
    }
    /* ③ المِرساةُ المقيسة — ولا تُطبَّق إلّا على من لا بندَ قائمةٍ حيًّا له */
    if ($parentKey === '' && !isset($navActive[$lc]) && isset($anchors[$bn])) {
        $cands = array_keys($anchors[$bn]);
        $menuCands = array();
        foreach ($cands as $c) { if (isset($navActive[$c])) { $menuCands[] = $c; } }
        $pick = $menuCands ?: $cands;
        sort($pick, SORT_STRING);
        if (count($pick) >= 1 && isset($U[$pick[0]])) {
            $parentKey = $pick[0];
            $rule = $menuCands ? 'ANCHOR_MENU_PARENT' : 'ANCHOR_SCREEN_PARENT';
        }
    }
    if ($parentKey !== '' && $parentKey !== $k) {
        $u['parent_screen_id'] = $U[$parentKey]['screen_id'];
        $u['parent_rule'] = $rule;
        $u['__parent_key'] = $parentKey;
        $parentStat[$rule] = ($parentStat[$rule] ?? 0) + 1;
    } elseif ($rule === 'HIDDEN_LOG_TAB_IN_PARENT') {
        $u['parent_rule'] = 'HIDDEN_LOG_TAB_NO_ANCHOR';   /* عذرٌ مسجَّلٌ بلا أبٍ مقيس */
        $parentStat['HIDDEN_LOG_TAB_NO_ANCHOR'] = ($parentStat['HIDDEN_LOG_TAB_NO_ANCHOR'] ?? 0) + 1;
    }
    unset($u);
}

/* وراثةُ المالكِ من الأبِ لمن لا مصدرَ له — بعد ⑤ لأنّها تحتاجه */
foreach ($keys as $k) {
    if (!isset($ownerless[$k])) { continue; }
    $pk = $U[$k]['__parent_key'] ?? '';
    if ($pk !== '' && ($U[$pk]['owner_code'] ?? '') !== '') {
        $U[$k]['owner_code'] = $U[$pk]['owner_code'];
        $U[$k]['owner_role'] = $U[$pk]['owner_role'];
        $U[$k]['owner_rule'] = 'PARENT_INHERIT:' . $U[$pk]['screen_id'];
        $ownerStat['PARENT_INHERIT'] = ($ownerStat['PARENT_INHERIT'] ?? 0) + 1;
        unset($ownerless[$k]);
    }
}
/* وما بقي بلا مصدرٍ يُسجَّل قرارًا — لا يُخمَّن ولا يُترك فارغًا */
$W2D01 = 'W2-D-01';
foreach ($keys as $k) {
    if (!isset($ownerless[$k])) { continue; }
    $U[$k]['owner_code'] = '';
    $U[$k]['owner_role'] = 'W2_DECISION:' . $W2D01;
    $U[$k]['owner_rule'] = 'W2_DECISION:' . $W2D01;
    $ownerStat['W2_DECISION'] = ($ownerStat['W2_DECISION'] ?? 0) + 1;
}
say('③ المالك: ' . json_encode($ownerStat, JSON_UNESCAPED_UNICODE));
say('⑤ الأب/التبويب: ' . json_encode($parentStat, JSON_UNESCAPED_UNICODE));

/* ═══ ⑥ Visibility — والظهورُ ليس صلاحية (§٣٦) ═══════════════════════════ */
$ANCHOR_ROUTES = array('main/role_board.php', 'chats/index.php', 'main/dashboard.php');
$visStat = array();
foreach ($keys as $k) {
    $u =& $U[$k];
    $lc = strtolower((string) $u['route']);
    if (!$u['on_disk'])                              { $v = 'NOT_BUILT';   $rule = 'NOT_ON_DISK'; }
    elseif (in_array($lc, $ANCHOR_ROUTES, true))     { $v = 'ANCHOR';      $rule = 'SHELL_ANCHOR'; }
    elseif (isset($navActive[$lc]))                  { $v = 'MENU_ITEM';   $rule = 'NAV_ITEMS_ACTIVE'; }
    elseif ($u['parent_screen_id'] !== '')           { $v = 'TAB_CHILD';   $rule = $u['parent_rule']; }
    else                                             { $v = 'DIRECT_ONLY'; $rule = 'NO_NAV_NO_PARENT'; }
    $u['visibility_class'] = $v; $u['visibility_rule'] = $rule;
    $visStat[$v] = ($visStat[$v] ?? 0) + 1;
    unset($u);
}
say('⑥ صنفُ الظهور: ' . json_encode($visStat, JSON_UNESCAPED_UNICODE));

/* ═══ ⑦ الحارس — مقيسٌ من الشيفرةِ لا مدَّعًى ═══════════════════════════ */
$guardStat = array();
foreach ($keys as $k) {
    $u =& $U[$k];
    $u['guard_kind'] = ''; $u['guard_evidence'] = '';
    if ($u['on_disk']) {
        $lc = strtolower((string) $u['route']);
        if (isset($liveLc[$lc])) {
            $rel = $liveLc[$lc];
            $g = repair01_w2_guard($rel, $live[$rel], $incMap[$rel] ?? array(), $bearers);
            $u['guard_kind'] = $g['kind']; $u['guard_evidence'] = $g['evidence'];
        } elseif (isset($phpLc[$lc])) {
            /* ملفٌّ إنتاجيٌّ لا يحمل قشرةً: مُحوِّلٌ أو غلافٌ أو نقطةُ طلب */
            $rel = $phpLc[$lc];
            $g = repair01_w2_guard($rel, $phpFiles[$rel], $incMap[$rel] ?? array(), $bearers);
            $u['guard_kind'] = ($g['kind'] === 'NONE') ? 'NOT_A_SCREEN' : $g['kind'];
            $u['guard_evidence'] = ($g['kind'] === 'NONE')
                ? 'لا يحمل قشرةً ولا يُحوِّل — خارجَ مقامِ حارسِ العرض'
                : $g['evidence'];
        } else {
            $u['guard_kind'] = 'NOT_A_SCREEN';
            $u['guard_evidence'] = 'خارجَ مقامِ المسحِ الإنتاجيّ (' . implode(' · ', repair01_w2_skip_dirs()) . ')';
        }
    }
    $guardStat[$u['guard_kind'] ?: '—'] = ($guardStat[$u['guard_kind'] ?: '—'] ?? 0) + 1;
    unset($u);
}
say('⑦ الحارس: ' . json_encode($guardStat, JSON_UNESCAPED_UNICODE));

/* ═══ ⑧ حسمُ الأشباح — كلُّ ملفٍّ بحكمٍ وعذرٍ مكتوب ═════════════════════ */
$ghostStat = array(); $gapRows = array();
foreach ($keys as $k) {
    $u =& $U[$k];
    if ($u['on_disk']) { $u['ghost_verdict'] = ''; $u['ghost_why'] = ''; unset($u); continue; }
    $bn = strtolower($u['screen_file']);
    /* (أ) نظيرٌ مبنيٌّ يحمل الاسمَ نفسَه على القرص ⇒ تقاعُدٌ بالنظير */
    $twin = '';
    foreach ($byFile[$bn] ?? array() as $other) {
        if ($other !== $k && $U[$other]['on_disk']) { $twin = $other; break; }
    }
    if ($twin !== '') {
        $u['ghost_verdict'] = 'RETIRE_BUILT_TWIN';
        $u['ghost_why'] = 'ملفٌّ مبنيٌّ يحمل الاسمَ نفسَه قائمٌ على القرص (' . $U[$twin]['route']
                        . ') — فالصفُّ تكرارُ تسميةٍ لا سطحٌ غائب.';
        $u['lifecycle'] = 'GHOST_RETIRED'; $u['lifecycle_rule'] = 'BUILT_TWIN_ON_DISK';
    } else {
        $wave = repair01_w2_wave_for_code($u['owner_code']);
        $u['ghost_verdict'] = 'MOVED_TO_TARGET_GAPS';
        $u['ghost_why'] = 'صفرُ أثرٍ في تاريخِ git (W00 §ملحق) ⇒ لم يُبنَ قطُّ ولم يُحذف — '
                        . 'سطحٌ مستهدَفٌ سُجِّل في خانةِ «المبنيّ». يُنقل إلى دفترِ الفجواتِ '
                        . 'بموجةِ مالكِه ' . ($u['owner_code'] ?: '—') . ' ⇐ ' . ($wave ?: 'بلا موجة') . '.';
        $u['lifecycle'] = 'GHOST_TARGET'; $u['lifecycle_rule'] = 'NEVER_BUILT_TO_GAPS';
        $gapRows[] = array(
            'unit'    => $u['owner_code'],
            'surface' => ($u['title'] !== '' ? $u['title'] : $u['screen_file']),
            'wave'    => $wave,
            'src_ref' => $u['src_ref'],
            'file'    => $u['screen_file'],
            'sid'     => $u['screen_id'],
        );
    }
    $ghostStat[$u['ghost_verdict']] = ($ghostStat[$u['ghost_verdict']] ?? 0) + 1;
    unset($u);
}
say('⑧ الأشباح: ' . json_encode($ghostStat, JSON_UNESCAPED_UNICODE));

if ($DRY) { say("\n(--dry) لم يُكتب شيء."); exit(0); }

/* ═══════════════════════════════════════════════════════════════════════════
 * الكتابة
 * ═════════════════════════════════════════════════════════════════════════ */
$ins = 0; $upd = 0;
foreach ($keys as $k) {
    $u = $U[$k];
    $route = ($u['route'] === null || $u['route'] === '') ? 'NULL' : "'" . esc($conn, $u['route']) . "'";
    $sql = "INSERT INTO repair01_screen_registry
      (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
       lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
       on_disk, origin, ghost_verdict, ghost_why, guard_kind, guard_evidence, w2_why, src_ref)
      VALUES ('" . esc($conn, $u['screen_id']) . "','" . esc($conn, $u['screen_file']) . "'," . $route . ",
              '" . esc($conn, $u['route_rule']) . "','" . esc($conn, $u['owner_code']) . "',
              '" . esc($conn, $u['owner_role']) . "','" . esc($conn, $u['owner_rule']) . "',
              '" . esc($conn, $u['lifecycle']) . "','" . esc($conn, $u['lifecycle_rule']) . "',
              '" . esc($conn, $u['parent_screen_id']) . "','" . esc($conn, $u['parent_rule']) . "',
              '" . esc($conn, $u['visibility_class']) . "','" . esc($conn, $u['visibility_rule']) . "',
              " . (int) $u['on_disk'] . ",'" . esc($conn, $u['origin']) . "',
              '" . esc($conn, $u['ghost_verdict']) . "','" . esc($conn, $u['ghost_why']) . "',
              '" . esc($conn, $u['guard_kind']) . "','" . esc($conn, $u['guard_evidence']) . "',
              '" . esc($conn, $u['title']) . "','" . esc($conn, $u['src_ref']) . "')
      ON DUPLICATE KEY UPDATE
        screen_file=VALUES(screen_file), route=VALUES(route), route_rule=VALUES(route_rule),
        /* ⛔ **الاشتقاقُ لا يدهس القرار** (RPR-AMD01): مصفوفةُ الدراسةِ تُدرِج
             السطحَ الواحدَ تحت أكثرَ من إدارةٍ **عمدًا** (‏ظهورٌ في مساحةٍ لا
             مِلكيّة · م ١١٤)، وهذه الكتلةُ تختار أحدَها بأسبقيّةٍ آليّة. وقد
             حكمت `W15 §٤-٢` في **11 سطحًا** بعينِها أنَّ مالكَها إدارتُها لا
             مكتبُ الرئيس (*«إنجازي سطحُ مساحةٍ شخصيّةٍ لا سطحَ قيادة»* ·
             *«القوائمُ الماليّةُ تملكها الإدارةُ المالية»*) وسجّلت النقلةَ في
             `repair01_w15_nav_moves`.
             ⇒ فكانت W02 تُعيدها `EX-CEO` في كلِّ تشغيل و`W15` تردُّها، **فلا
             تبلغ السلسلةُ نقطةَ ثبات**: قِيس 31 صفًّا يتأرجح بين التشغيلَين.
           ⛔ **فالحكمُ المسجَّلُ يُصان والباقي يُشتقّ** — والحاجبُ لا يضعف:
             سطحٌ بلا حكمٍ مسجَّلٍ يأخذ اشتقاقَ W02 كما كان. */
        /* ⚠ **والعلامةُ هي القاعدةُ المسجَّلةُ لا صنفُ الحكم**: قُيِّد الحارسُ
             أوّلًا بـ`ownership_verdict='DOMAIN_SOURCE'`، **فأفلت منه شبحان**
             (`fin_statements.php` · `margin.php`) صنفُهما `RETIRE` وقد نقلت
             `W15` ملكيّتَهما أيضًا — فظلَّا يتأرجحان. وقِيس ذلك حقنًا: تشغيلُ
             W02 يعيدهما `EX-CEO` وحدَهما. ⇒ **المُعوَّلُ عليه `verdict_rule`** —
             فهو أثرُ القرارِ المكتوبِ أيًّا كان صنفُ الحكم. */
        owner_code = IF(verdict_rule LIKE 'RPR-W15%', owner_code, VALUES(owner_code)),
        owner_role=VALUES(owner_role), owner_rule=VALUES(owner_rule),
        lifecycle=VALUES(lifecycle), lifecycle_rule=VALUES(lifecycle_rule),
        parent_screen_id=VALUES(parent_screen_id), parent_rule=VALUES(parent_rule),
        visibility_class=VALUES(visibility_class), visibility_rule=VALUES(visibility_rule),
        on_disk=VALUES(on_disk),
        /* ⛔ **ختمُ النموِّ حقيقةٌ تاريخيّةٌ لا اشتقاقٌ من حالِ القرصِ اليوم**
             (RPR-AMD01). كان `origin=VALUES(origin)` — و«الكونُ» أعلاه يمنح
             `DISK` لكلِّ ملفٍّ حيٍّ بلا صفِّ سطح. فحين تُعاد W02 **بعد** أن بنت
             موجةٌ لاحقةٌ ملفَّاتِها، يجدها المسحُ على القرصِ فيدهس ختمَها:
             قِيس ٢٠٢٦-٠٨-٢٨ أنَّ **18 صفًّا فقدت `origin='W11'` وصارت `DISK`**،
             فارتفع مقامُ الأساسِ 651 ⇒ 669 وسقط `W3-14` و`W8-18` و`W11-26`…
             ⇒ **ما خُتم بموجةٍ يبقى مختومًا**، وما لا ختمَ له يأخذ اشتقاقَ W02.
             والحاجبُ لا يضعف: الصفُّ الجديدُ يُشتقّ كما كان. */
        origin = IF(origin REGEXP '^W[0-9]{2}$', origin, VALUES(origin)),
        ghost_verdict=VALUES(ghost_verdict), ghost_why=VALUES(ghost_why),
        guard_kind=VALUES(guard_kind), guard_evidence=VALUES(guard_evidence),
        w2_why=VALUES(w2_why), src_ref=VALUES(src_ref)";
    if (q($conn, $sql)) { if ($conn->affected_rows === 1) { $ins++; } else { $upd++; } }
}
say("السجلُّ المعياريّ: أُدرج $ins · حُدِّث $upd");

/* ⑨ ربطُ دفترِ الأسطحِ بالسجلّ — مرجعٌ لا مُعرِّفٌ ثانٍ */
$linked = 0;
foreach ($surfRows as $key => $ids) {
    if (!isset($U[$key])) { continue; }
    $sid = esc($conn, $U[$key]['screen_id']);
    if (q($conn, "UPDATE repair01_surfaces SET screen_id='$sid' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")")) {
        $linked += count($ids);
    }
}
say("⑨ صفوفُ الأسطحِ الموصولةُ بالسجلّ: $linked");

/* الأشباحُ المنقولةُ إلى دفترِ الفجوات — بوسمِ المرحلةِ فلا تمتزج بالـ١٧٤ */
q($conn, "DELETE FROM repair01_target_gaps WHERE origin_stage='W02'");
$moved = 0;
foreach ($gapRows as $g) {
    $sql = "INSERT INTO repair01_target_gaps (unit, surface_name, built_counterpart, verdict, src_ref,
                origin_stage, origin_note, wave_stage)
            VALUES ('" . esc($conn, $g['unit']) . "','" . esc($conn, $g['surface']) . "','',
                    'Build — سطحٌ مستهدَفٌ لم يُبنَ قطّ (نُقل من خانةِ المبنيّ في W02)',
                    '" . esc($conn, $g['src_ref']) . "','W02',
                    '" . esc($conn, $g['sid'] . ' · ' . $g['file']) . "','" . esc($conn, $g['wave']) . "')";
    if (q($conn, $sql)) { $moved++; }
}
say("الأشباحُ المنقولةُ إلى دفترِ الفجوات: $moved");

/* قرارُ المرحلة — الشاشةُ بلا مالكٍ في أيِّ مصدر */
$noOwner = 0;
foreach ($keys as $k) { if (strpos((string) $U[$k]['owner_rule'], 'W2_DECISION:') === 0) { $noOwner++; } }
$sql = "INSERT INTO repair01_w2_decisions
   (decision_id, stage, topic, question, ruling, rationale, evidence, scope_rows, status)
   VALUES ('$W2D01','W02','مالكُ الشاشةِ حين تصمت المصادرُ الثلاثة',
     'مَن يملك شاشةً لا صفَّ لها في دفترِ الأسطحِ ولا إدارةً مالكةً في السجلِّ المعياريِّ للتنقّلِ ولا مساحةَ دورٍ واحدةً تحملها ولا أبًا ترثه؟',
     'تُسجَّل بمؤشِّرٍ إلى هذا القرارِ ولا يُخمَّن لها مالك — والحسمُ في موجةِ الإدارةِ التي تظهر فيها',
     'الخانةُ الفارغةُ تمرُّ في العدِّ فتُخضِرُّ البوّابةَ كذبًا؛ والمؤشِّرُ يحمل السؤالَ ومَن يملك حسمَه. نمطُ W1-D-01 نفسُه.',
     'repair01_screen_registry · owner_rule LIKE ''W2_DECISION:%''', $noOwner, 'RECORDED_PENDING_OWNER')
   ON DUPLICATE KEY UPDATE scope_rows=VALUES(scope_rows), rationale=VALUES(rationale)";
q($conn, $sql);
say("قرارُ $W2D01: $noOwner شاشةً بلا مالكٍ في أيِّ مصدر");

/* قرارُ المرحلة — صفٌّ في دفترِ الأسطحِ مسارُه على القرصِ **وليس شاشةً**.
   هذه مصالحةُ W00 حين طابقت اسمَ سطحٍ باسمِ ملفٍّ ليس شاشة (مكتبةُ مورِّدٍ أو
   نقطةُ واجهةٍ برمجيّة). والصفوفُ تبقى موسومةً ولا تُحذف — والحذفُ يمحو الدليل. */
$notScreen = array();
foreach ($keys as $k) { if (($U[$k]['guard_kind'] ?? '') === 'NOT_A_SCREEN') { $notScreen[] = $U[$k]['route']; } }
$W2D02 = 'W2-D-02';
$ns_why = 'مصالحةُ W00 طابقت اسمَ السطحِ باسمِ الملفِّ فأصابت خمسةَ مواضعَ ليست شاشاتٍ: '
        . implode(' · ', array_slice($notScreen, 0, 5))
        . '. فمقامُ «المبنيِّ» (٤٠٧ صفًّا · ٢٢٤ ملفًّا) يحمل خمسةَ ملفّاتٍ لا تُصيَّر شاشةً — '
        . 'والرقمُ يُقرأ صحيحًا وهو خطأٌ ما لم يُعلَن. تُوسَم ولا تُحذف، ويُحسم موضعُها '
        . 'في موجةِ مالكِها حيث يُبنى السطحُ المستهدَفُ أو يُتقاعد.';
q($conn, "INSERT INTO repair01_w2_decisions
   (decision_id, stage, topic, question, ruling, rationale, evidence, scope_rows, status)
   VALUES ('$W2D02','W02','مسارٌ في دفترِ الأسطحِ يشير إلى ما ليس شاشة',
     'ما حكمُ صفِّ سطحٍ وُسم «مبنيًّا» ومسارُه ملفٌّ قائمٌ لا يُصيَّر شاشةً (مكتبةُ مورِّدٍ · نقطةُ واجهةٍ برمجيّة · مُهيِّئُ إدارة)؟',
     'يُوسَم NOT_A_SCREEN في السجلِّ ويُعلَن بهذا القرار — ولا يُحذف ولا يُحسب شاشةً محروسة',
     " . esc2($conn, $ns_why) . ",
     'repair01_screen_registry · guard_kind = ''NOT_A_SCREEN''', " . count($notScreen) . ", 'RECORDED_PENDING_OWNER')
   ON DUPLICATE KEY UPDATE scope_rows=VALUES(scope_rows), rationale=VALUES(rationale)");
say("قرارُ $W2D02: " . count($notScreen) . ' مسارًا ليس شاشةً');

say("\nتمّ.");
