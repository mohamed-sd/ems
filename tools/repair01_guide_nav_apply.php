<?php
/**
 * tools/repair01_guide_nav_apply.php — تطبيقُ مجموعاتِ الدليلِ وترتيبِها
 * ═══════════════════════════════════════════════════════════════════════════
 * **مشكلةُ المالك ①** وقرارُه **(أ) اسمُ الدليلِ يغلب**.
 *
 * ◆ **ولا يُطبَّق إلّا ما يُحسَم بيقين** — وثلاثةُ أفعالٍ فقط:
 *   ① **إنشاءُ المجموعاتِ الغائبةِ** بأسمائها وترتيبِها لدورِ الإدارة — فعلٌ
 *      **إضافيٌّ** لا يسلب أحدًا شيئًا.
 *   ② **نقلُ الشاشةِ المطابَقةِ إلى مجموعتِها المنصوصة** وترتيبِها — **وموضعُها
 *      يتغيّر ووصولُها لا** ⛔ فلا صلاحيةَ تُمَسّ.
 *   ③ **إعادةُ تسميةٍ بإشارتَين**: تشابهٌ ≥90٪ **وفي الإدارةِ نفسِها**.
 *
 * ⛔ **والتشابهُ وحدَه خطرٌ مُثبَت**: «لوحة المبيعات» ⇔ «لوحة المستودعات» **85٪**
 *   وهما شاشتان مختلفتان، و«مستهدفات الموردين» ⇔ «سلفيات الموردين» **84٪**.
 *   ⇒ **فوحدةُ الإدارةِ شرطٌ ثانٍ لا زينة**، وبها ينجو **ثمانيةٌ** من 269.
 *
 * ⚠ **ولاحقةُ «— بحسب انطباق الشركة» ملحوظةُ انطباقٍ لا اسم**: تُنزَع عند
 *   الكتابة، **فهي سببُ 29 من 31 اسمًا يتجاوز حدَّ الستِّ كلماتِ في `U6`**.
 * ⚠ **والتشكيلُ يُنزَع**: سبعةُ أسماءٍ في الدليلِ مشكولةٌ، **والموجةُ السادسةُ
 *   نزعت التشكيلَ من أكثرَ من ثمانمئةِ صفحة** — فكتابتُه تُسقط `UI-01`.
 *
 * التشغيل: php tools/repair01_guide_nav_apply.php [--apply] [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$SPEC = $ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json';
if (!is_file($SPEC)) { exit("⛔ استخرِج المواصفةَ أوّلًا\n"); }
$spec = json_decode((string) file_get_contents($SPEC), true);

/* اسمٌ صالحٌ للنظام: بلا تشكيلٍ وبلا لاحقةِ الانطباق */
$clean = function ($s) {
    $s = preg_replace('~\s*[—\-–]\s*بحسب انطباق.*$~u', '', (string) $s);
    $s = preg_replace('~[\x{064B}-\x{0652}]~u', '', $s);
    return preg_replace('~\s+~u', ' ', trim($s));
};
$nz = function ($s) use ($clean) {
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $clean($s));
    $s = str_replace('ة', 'ه', $s);
    return preg_replace('~\s+~u', ' ', trim($s));
};

/* ── الإدارة ⇒ دورُها ─────────────────────────────────────────────────────── */
$roles = array();
$r = $conn->query("SELECT id, name FROM roles");
while ($r && ($x = $r->fetch_assoc())) { $roles[$nz($x['name'])] = array((int) $x['id'], $x['name']); }
$depRole = array();
$r = $conn->query("SELECT canonical_code, name_ar FROM repair01_departments");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $nz($x['name_ar']);
    if (isset($roles[$k])) { $depRole[$x['canonical_code']] = $roles[$k]; continue; }
    foreach ($roles as $rk => $rv) {
        if (mb_strpos($rk, $k) !== false || mb_strpos($k, $rk) !== false) { $depRole[$x['canonical_code']] = $rv; break; }
    }
}
/* إسنادٌ صريحٌ لما لا يحلُّه التطابق — بسندٍ مكتوبٍ لكلٍّ */
foreach (array('DEP-06' => 21, 'DEP-17' => 25, 'EX-CEO' => 9, 'IAF' => 33) as $k => $v) {
    if (!isset($depRole[$k])) {
        $q = $conn->query("SELECT id, name FROM roles WHERE id = $v LIMIT 1");
        if ($q && $q->num_rows) { $y = $q->fetch_assoc(); $depRole[$k] = array((int) $y['id'], $y['name']); }
    }
}

/* ── الحالُ الحيّ ─────────────────────────────────────────────────────────── */
$grpOf = array();          /* role_id|اسمٌ مطبَّع ⇒ id */
$r = $conn->query("SELECT id, name, owner_role_id FROM link_groups");
while ($r && ($x = $r->fetch_assoc())) { $grpOf[(int) $x['owner_role_id'] . '|' . $nz($x['name'])] = (int) $x['id']; }

$byDepLabel = array();     /* dep|اسمٌ مطبَّع ⇒ صفُّ السجل */
$byDep = array();
$r = $conn->query("SELECT screen_file, route, owner_code, canonical_label_ar
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')
                      AND COALESCE(canonical_label_ar,'') <> ''");
while ($r && ($x = $r->fetch_assoc())) {
    $byDepLabel[$x['owner_code'] . '|' . $nz($x['canonical_label_ar'])] = $x;
    $byDep[$x['owner_code']][] = $x;
}
$rtn = function ($s) { return strtolower(preg_replace('~\.php$~i', '', ltrim((string) $s, '/'))); };
$navIdx = array();
$q = $conn->query("SELECT id, role_id, group_id, route, sort_order FROM nav_items WHERE COALESCE(route,'') <> ''");
while ($q && ($x = $q->fetch_assoc())) { $navIdx[$rtn($x['route'])][] = $x; }

/* ── الخطّة ───────────────────────────────────────────────────────────────── */
$P = array('newGroup' => array(), 'move' => array(), 'rename' => array(), 'skip' => 0,
           'noRole' => array(), 'prefix' => array(), 'needNav' => array());
foreach ($spec as $code => $d) {
    if (!$d['screens']) { continue; }
    if (!isset($depRole[$code])) { $P['noRole'][$code] = count($d['screens']); continue; }
    list($rid, $rname) = $depRole[$code];
    foreach ($d['groups'] as $g => $ord) {
        $gk = $rid . '|' . $nz($g);
        if (!isset($grpOf[$gk])) { $P['newGroup'][] = array('dep' => $code, 'rid' => $rid, 'name' => $clean($g), 'ord' => $ord); }
    }
    foreach ($d['screens'] as $s) {
        $k = $code . '|' . $nz($s['title']);
        $scr = isset($byDepLabel[$k]) ? $byDepLabel[$k] : null;
        if (!$scr) {
            /* إشارتان: تشابهٌ ≥90 **وفي الإدارةِ نفسِها** */
            $bs = 0; $bl = null;
            foreach ((isset($byDep[$code]) ? $byDep[$code] : array()) as $lv) {
                similar_text($nz($s['title']), $nz($lv['canonical_label_ar']), $pc);
                if ($pc > $bs) { $bs = $pc; $bl = $lv; }
            }
            /* ⛔ **والبادئةُ ليست اسمًا آخرَ بل الاسمَ نفسَه مبتورًا**: ثلاثةُ
                 أسماءٍ في الدليلِ طولُها 59 حرفًا بالضبطِ **وهي بادئةٌ حرفيّةٌ**
                 من اسمِ النظامِ الأطول («والمفوض» من «والمفوضين»).
                 ⇒ **وقرارُ المالكِ (أ) يحسم أيَّ اسمٍ يُختار لا أن يُبتَر
                 المختارُ** — فالكتابةُ هنا تُنقص ولا تُصحّح. */
            $isPrefix = ($bl && mb_strpos($nz($bl['canonical_label_ar']), $nz($s['title'])) === 0
                          && $nz($bl['canonical_label_ar']) !== $nz($s['title']));
            if ($isPrefix) { $P['prefix'][] = array('dep' => $code, 'guide' => $clean($s['title']),
                                                   'live' => $bl['canonical_label_ar']);
                              $scr = $bl; }
            elseif ($bs >= 90 && $bl) {
                $P['rename'][] = array('dep' => $code, 'file' => $bl['screen_file'], 'route' => $bl['route'],
                                       'from' => $bl['canonical_label_ar'], 'to' => $clean($s['title']),
                                       'pc' => round($bs), 'group' => $clean($s['group']), 'ord' => $s['no'], 'rid' => $rid);
                $scr = $bl;
            } else { $P['skip']++; continue; }
        }
        $P['move'][] = array('dep' => $code, 'rid' => $rid, 'route' => $scr['route'],
                             'group' => $clean($s['group']), 'ord' => $s['no'],
                             'label' => $clean($s['title']));
    }
}

echo "\n═══ تطبيقُ الدليلِ المعماريِّ على الملاحة ═══\n";
printf("  ① مجموعاتٌ تُنشَأ:        %d\n", count($P['newGroup']));
printf("  ② شاشاتٌ تُوضَع في مجموعتِها: %d\n", count($P['move']));
printf("  ③ أسماءٌ تُصحَّح بإشارتَين:  %d\n", count($P['rename']));
printf("  ◆ يتجاوز — لا يُحسَم بيقين:  %d\n", $P['skip']);
if ($P['noRole']) {
    echo "  ⛔ إداراتٌ بلا دورٍ محلول: ";
    foreach ($P['noRole'] as $d => $n) { echo "$d($n) "; }
    echo "\n";
}
foreach (array_slice($P['rename'], 0, 8) as $x) {
    printf("     %d%% «%s» ← «%s» (%s)\n", $x['pc'], $x['to'], $x['from'], $x['dep']);
}

if ($APPLY) {
    $gN = 0; $mN = 0; $rN = 0; $er = 0;
    /* ① المجموعات */
    foreach ($P['newGroup'] as $x) {
        $ok = $conn->query("INSERT INTO link_groups (name, owner_role_id, display_order, is_active)
                            VALUES ('" . $e($x['name']) . "', {$x['rid']}, " . ($x['ord'] * 10) . ", 1)");
        if ($ok) { $gN++; $grpOf[$x['rid'] . '|' . $nz($x['name'])] = (int) $conn->insert_id; }
        else { $er++; }
    }
    /* ③ الأسماء — قبل النقلِ ليُكتب الاسمُ الجديدُ في بندِ الملاحة */
    foreach ($P['rename'] as $x) {
        $why = 'قرار المالك (أ): اسم الدليل المعماري يغلب · تشابه ' . $x['pc'] . ' بالمئة وفي الادارة نفسها';
        $ok = $conn->query("UPDATE repair01_screen_registry
            SET canonical_label_ar = '" . $e($x['to']) . "',
                verdict_rule = CONCAT(verdict_rule, ' | " . $e($why) . "')
          WHERE screen_file = '" . $e($x['file']) . "' AND on_disk = 1");
        if ($ok) { $rN++; } else { $er++; }
    }
    /* ② الوضعُ في المجموعة */
    foreach ($P['move'] as $x) {
        $gid = isset($grpOf[$x['rid'] . '|' . $nz($x['group'])]) ? $grpOf[$x['rid'] . '|' . $nz($x['group'])] : 0;
        if (!$gid) { $er++; continue; }
        $k = $rtn($x['route']);
        $rows = isset($navIdx[$k]) ? $navIdx[$k] : array();
        $done = false;
        foreach ($rows as $nv) {
            if ((int) $nv['role_id'] !== $x['rid']) { continue; }
            $conn->query("UPDATE nav_items SET group_id = $gid, sort_order = " . ($x['ord'] * 10) . ",
                          label_ar = '" . $e($x['label']) . "' WHERE id = " . (int) $nv['id']);
            $done = true; $mN++;
        }
        /* ⛔ **ولا يُنشَأ بندُ ملاحةٍ جديدٌ هنا**: إظهارُ شاشةٍ لدورٍ لم يكن يراها
             **توسيعُ وصولٍ حيّ** — يقرّره المالكُ لا أداةُ ترتيب. */
        if (!$done) { $P['needNav'][] = $x['route']; }
    }
    printf("\n✔ مجموعاتٌ %d · نُقل %d · أُعيدت تسميةُ %d · أخفق %d\n", $gN, $mN, $rN, $er);
    if (!empty($P['needNav'])) {
        printf("◆ **بلا بندِ ملاحةٍ لهذا الدور: %d** — وإنشاؤه توسيعُ وصولٍ حيٍّ بقرارِ المالك\n",
            count($P['needNav']));
    }
}

if ($MD) {
    $o  = "# تطبيقُ الدليلِ المعماريِّ على الملاحة — قرارُ المالك (أ)\n\n";
    $o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_guide_nav_apply.php --md`\n";
    $o .= "> **ولا يُطبَّق إلّا ما يُحسَم بيقين** — والتشابهُ وحدَه خطرٌ مُثبَت.\n\n";
    $o .= "| الفعل | العدد |\n|---|---:|\n";
    $o .= sprintf("| ① مجموعاتٌ تُنشَأ | %d |\n", count($P['newGroup']));
    $o .= sprintf("| ② شاشاتٌ تُوضَع في مجموعتِها | %d |\n", count($P['move']));
    $o .= sprintf("| ③ أسماءٌ تُصحَّح بإشارتَين | %d |\n", count($P['rename']));
    $o .= sprintf("| ◆ **لا يُحسَم بيقين** | **%d** |\n\n", $P['skip']);
    $o .= "## الأسماءُ المصحَّحة\n\n| الإدارة | من | إلى | التشابه |\n|---|---|---|---:|\n";
    foreach ($P['rename'] as $x) { $o .= sprintf("| `%s` | %s | **%s** | %d%% |\n", $x['dep'], $x['from'], $x['to'], $x['pc']); }
    $o .= "\n## المجموعاتُ المُنشَأة\n\n| الإدارة | # | الاسم |\n|---|---:|---|\n";
    foreach ($P['newGroup'] as $x) { $o .= sprintf("| `%s` | %d | %s |\n", $x['dep'], $x['ord'], $x['name']); }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_APPLY.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/GUIDE_NAV_APPLY.md\n";
}
if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; }
