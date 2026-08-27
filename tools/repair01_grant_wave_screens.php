<?php
/**
 * tools/repair01_grant_wave_screens.php — صلاحيةٌ كاملةٌ لمديرِ الإدارةِ على شاشاتِ موجاتِه
 * ═══════════════════════════════════════════════════════════════════════════
 * **مشكلةُ المالك ②**: «كلُّ الصفحاتِ الجديدةِ التي أُنشئت خلالَ المراحلِ الستَّ
 * عشرةَ تنزل في الإدارةِ المحدَّدةِ **بلا صلاحياتِ وصول** — أحتاج منحَها صلاحيّاتٍ
 * كاملةً للعرضِ والتعديلِ والحذفِ لمديرِ الإدارةِ **مثلَ بقيّةِ الشاشات**».
 *
 * ◆ **والمقيسُ يؤكّد الشكوى حرفًا**: 132 شاشةَ موجاتٍ حيّةً · **صفرٌ منها بصلاحيةٍ
 *   كاملةٍ لمديرِ إدارتِها** · 73 ناقصةٌ (‏عرضٌ فقط غالبًا) · وواحدةٌ بلا صفٍّ أصلًا.
 *
 * ◆ **والنمطُ ليس اختراعًا**: `1/1/1/1` مستعمَلٌ في **209 صفوفٍ** قائمةٍ في النظام
 *   — **فـ«مثلَ بقيّةِ الشاشات» له معنًى مقيسٌ لا مُتخيَّل**.
 *
 * ⛔ **وتوسيعُ الوصولِ فعلٌ حسّاس**: يقتصر على **دورِ الإدارةِ المالكةِ نفسِها**
 *   وعلى **شاشاتِ الموجاتِ في نطاقِها** ⛔ لا دورَ آخرَ ولا شاشةَ خارجَ نطاقِه.
 *   **والإدارةُ التي لا يُحَلُّ دورُها تُعلَن ولا يُخمَّن لها دور.**
 *
 * ⚠ **والدورُ يُحَلُّ من الاسمِ المعياريِّ للإدارة** لا من وصفٍ نثريّ — و`\x{…}`
 *   ليست يونيكود في PHP، **فالتطبيعُ بالأحرفِ نفسِها**.
 *
 * التشغيل: php tools/repair01_grant_wave_screens.php [--apply] [--md]
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

$nz = function ($s) {
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $s);
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

/* ── إسنادٌ صريحٌ لما لا يحلُّه تطابقُ الاسم ─────────────────────────────
   ◆ **والاسمُ المعياريُّ للإدارةِ لا يطابق اسمَ دورِها دائمًا**: «إدارة الخزينة»
     ودورُها «أمين الخزينة» — كلمتان مختلفتان لمعنًى واحد. فتُسنَد صراحةً
     **بسندٍ مكتوبٍ لكلٍّ** ⛔ ولا تُترك للاحتواءِ فيُصيب خطأً.
   ⛔ **ولم يُخترَع دورٌ لما لا دورَ له**: `DEP-08` الحوكمةُ والالتزامُ و`EX-DVP`
     نوّابُ الرئيس **لا دورَ لهما في `roles` أصلًا** (‏٣٥ دورًا فُحصت)،
     و`WS-MY` مساحةُ عملي **ليست إدارةً بل مساحةَ كلِّ مستخدَم**.
     ⇒ **فالثلاثةُ تُرفع للمالكِ ولا تُمنَح بتخمين.** */
$EXPLICIT = array(
    'DEP-06' => array(21, 'أمين الخزينة'),          /* الخزينة — الدور بلفظ الأمين */
    'DEP-17' => array(25, 'أمين المستودع'),         /* المخازن — الدور بلفظ المستودع */
    'EX-CEO' => array(9,  'الإدارة التنفيذية'),     /* الرئيس التنفيذي — الدور بلفظ الإدارة */
    'IAF'    => array(33, 'المراجع الداخلي المستقل'), /* المراجعة الداخلية المستقلة */
);
foreach ($EXPLICIT as $k => $v) { if (!isset($depRole[$k])) { $depRole[$k] = $v; } }

$plan = array(); $unres = array();
$r = $conn->query("SELECT screen_file, route, owner_code, canonical_label_ar
                     FROM repair01_screen_registry
                    WHERE origin REGEXP '^W[0-9]+\$' AND on_disk = 1
                      AND ownership_verdict NOT IN ('RETIRE')
                    ORDER BY owner_code, screen_file");
while ($r && ($x = $r->fetch_assoc())) {
    $dep = (string) $x['owner_code'];
    if (!isset($depRole[$dep])) { $unres[$dep][] = $x['screen_file']; continue; }
    list($rid, $rname) = $depRole[$dep];
    $m = $conn->query("SELECT id FROM modules WHERE code LIKE CONCAT('%', '" . $e(basename($x['screen_file'])) . "') LIMIT 1");
    if (!$m || !$m->num_rows) { $unres['(بلا modules)'][] = $x['screen_file']; continue; }
    $mid = (int) $m->fetch_row()[0];
    $p = $conn->query("SELECT id, can_view, can_add, can_edit, can_delete FROM role_permissions
                        WHERE role_id = $rid AND module_id = $mid LIMIT 1");
    $cur = ($p && $p->num_rows) ? $p->fetch_assoc() : null;
    $full = $cur && $cur['can_view'] && $cur['can_add'] && $cur['can_edit'] && $cur['can_delete'];
    if ($full) { continue; }
    $plan[] = array('file' => $x['screen_file'], 'label' => $x['canonical_label_ar'], 'dep' => $dep,
                    'rid' => $rid, 'rname' => $rname, 'mid' => $mid,
                    'cur' => $cur ? sprintf('%d%d%d%d', $cur['can_view'], $cur['can_add'], $cur['can_edit'], $cur['can_delete']) : 'لا صفّ',
                    'pid' => $cur ? (int) $cur['id'] : 0);
}

echo "\n═══ منحُ شاشاتِ الموجاتِ لمديرِ إدارتِها ═══\n";
printf("  يحتاج منحًا: %d\n", count($plan));
$byDep = array();
foreach ($plan as $x) { $byDep[$x['dep']][] = $x; }
foreach ($byDep as $d => $v) {
    printf("  %-8s %-26s %d شاشة\n", $d, '«' . $v[0]['rname'] . '»', count($v));
}
if ($unres) {
    printf("\n◆ **إداراتٌ لا يُحَلُّ دورُها — تُعلَن ولا يُخمَّن لها دور**:\n");
    foreach ($unres as $d => $v) { printf("     %-14s %d شاشة\n", $d, count($v)); }
}

if ($APPLY) {
    $ins = 0; $upd = 0; $fail = 0;
    foreach ($plan as $x) {
        if ($x['pid']) {
            $ok = $conn->query("UPDATE role_permissions SET can_view = 1, can_add = 1, can_edit = 1, can_delete = 1
                                 WHERE id = " . $x['pid']);
            if ($ok) { $upd++; } else { $fail++; }
        } else {
            $ok = $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                VALUES ({$x['rid']}, {$x['mid']}, 1, 1, 1, 1)");
            if ($ok) { $ins++; } else { $fail++; echo "  ✘ {$x['file']} — " . $conn->error . "\n"; }
        }
    }
    printf("\n✔ حُدِّث %d · أُدرج %d · أخفق %d\n", $upd, $ins, $fail);
    /* ⛔ **والتحقُّقُ بإعادةِ القياسِ لا بعدِّ الكتابات** */
    $left = 0;
    foreach ($plan as $x) {
        $p = $conn->query("SELECT can_view, can_add, can_edit, can_delete FROM role_permissions
                            WHERE role_id = {$x['rid']} AND module_id = {$x['mid']} LIMIT 1");
        $y = ($p && $p->num_rows) ? $p->fetch_assoc() : null;
        if (!$y || !($y['can_view'] && $y['can_add'] && $y['can_edit'] && $y['can_delete'])) { $left++; }
    }
    printf("✔ **إعادةُ القياس**: ما يزال ناقصًا %d من %d\n", $left, count($plan));
}

if ($MD) {
    $o  = "# منحُ شاشاتِ الموجاتِ لمديرِ إدارتِها — مشكلةُ المالك ②\n\n";
    $o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_grant_wave_screens.php --md`\n";
    $o .= "> **والنمطُ `1/1/1/1` مستعمَلٌ في 209 صفوفٍ قائمة** — فـ«مثلَ بقيّةِ الشاشات» مقيسٌ لا مُتخيَّل.\n\n";
    $o .= sprintf("**يحتاج منحًا: %d شاشة**\n\n| الإدارة | الدور | العدد |\n|---|---|---:|\n", count($plan));
    foreach ($byDep as $d => $v) { $o .= sprintf("| `%s` | %s | %d |\n", $d, $v[0]['rname'], count($v)); }
    if ($unres) {
        $o .= "\n## إداراتٌ لا يُحَلُّ دورُها — تُعلَن ولا يُخمَّن لها دور\n\n| الإدارة | شاشات |\n|---|---:|\n";
        foreach ($unres as $d => $v) { $o .= sprintf("| `%s` | %d |\n", $d, count($v)); }
    }
    $o .= "\n## التفصيل\n\n| الشاشة | الاسم | الإدارة | الحالُ قبلَ المنح |\n|---|---|---|---|\n";
    foreach ($plan as $x) {
        $o .= sprintf("| `%s` | %s | %s | `%s` |\n", $x['file'], $x['label'], $x['dep'], $x['cur']);
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/WAVE_SCREEN_GRANTS.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/WAVE_SCREEN_GRANTS.md\n";
}
if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; }
