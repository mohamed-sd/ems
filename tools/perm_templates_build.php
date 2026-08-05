<?php
/**
 * tools/perm_templates_build.php — ملء قوالب الصلاحيات اشتقاقًا من القائم (E-04 RB-01)
 * ───────────────────────────────────────────────────────────────────────────
 * الخطوة ① من مسار توحيد نظامَي الصلاحيات (قرار المالك 2026-08-06):
 * «نملأ القوالب ببنودها — تُشتق من الـ1,120 صفًّا القائمة، فلا نبدأ من فراغ».
 *
 * التفكيك ثلاثيُّ الطبقات كما في E-04 SEC-002 (العائلة + المستوى + المسمّى):
 *   ① العائلة  = ما تشترك فيه **كلُّ** أدوار الإدارة
 *   ② المستوى  = ما تشترك فيه كلُّ أدوار المستوى — ناقصًا ما حملته عائلاتُها
 *   ③ المسمّى  = باقي صلاحيات الدور وحدَه
 * واتحادُ الثلاثة = صلاحياتُ الدور حرفًا — فلا تُفقد راية ولا تُزاد.
 * وهذا شرطُ المرحلة ③ (الظل): المشتقُّ يجب أن يوافق القديمَ صفرَ فرق.
 *
 * المفردات: permission_code = «مسارُ الشاشة:الفعل» بمفردات PermSourceService
 * العشر — فالمقارنة تسأل الجانبين بالمفتاح نفسِه فلا التباس.
 *
 * التشغيل:  php tools/perm_templates_build.php --diff | --apply | --rebuild
 *   --rebuild: يمسح بنودَ النسخ المولَّدة (approval_ref=OWNER-2026-08-06)
 *   ثم يعيد الاشتقاق — يُستعمل بعد أي تعديلٍ على role_permissions
 *   كي لا تبقى القوالبُ أوسعَ من الحي (فالإضافةُ وحدَها لا تحذف الزائد).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$REBUILD = in_array('--rebuild', $argv, true);
$APPLY = $REBUILD || in_array('--apply', $argv, true);
if ($REBUILD) {
    $del = mysqli_query($conn,
        "DELETE tp FROM template_permissions tp
          JOIN permission_template_versions v ON v.ver_id = tp.template_version_id
         WHERE v.approval_ref = 'OWNER-2026-08-06'");
    echo "أُعيد البناء: مُسح " . mysqli_affected_rows($conn) . " بندًا مولَّدًا قبل الاشتقاق.\n\n";
}

/** الرايةُ الأربع ← فعلُها القانوني (مفردات legacyDecision) وبُعدُها */
$FLAGS = array(
    'can_view'   => array('screen_view',  'visibility'),
    'can_add'    => array('create',       'action'),
    'can_edit'   => array('update',       'action'),
    'can_delete' => array('delete_draft', 'action'),
);

// الخريطةُ من المصدر الواحد — يقرؤها جسرُ الهوية أيضًا فلا يتفرّق التصنيف
require_once __DIR__ . '/../includes/role_taxonomy.php';
$FAMILY = array();
$rr = mysqli_query($conn, "SELECT id FROM roles");
while ($rx = mysqli_fetch_assoc($rr)) {
    $f = ems_role_family((int) $rx['id']);
    if ($f !== null) { $FAMILY[(int) $rx['id']] = $f; }
}
function levelKeyOf($roleId, $lvl) { return ems_role_level($roleId, $lvl); }

// ── ① قراءة الواقع: صلاحياتُ كل دورٍ كمجموعةِ رموز ──────────────────────
$roleName = array(); $roleLevel = array();
$r = mysqli_query($conn, "SELECT id, name, level FROM roles ORDER BY id");
while ($x = mysqli_fetch_assoc($r)) { $roleName[(int) $x['id']] = $x['name']; $roleLevel[(int) $x['id']] = (int) $x['level']; }

$rolePerms = array();   // role => [code => 1]
$r = mysqli_query($conn, "SELECT rp.role_id, m.code, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                            FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code IS NOT NULL AND m.code <> ''");
$totalGrants = 0;
while ($x = mysqli_fetch_assoc($r)) {
    $rid = (int) $x['role_id'];
    foreach ($FLAGS as $flag => $fa) {
        if ((int) $x[$flag] === 1) { $rolePerms[$rid][$x['code'] . ':' . $fa[0]] = 1; $totalGrants++; }
    }
}

// ── ② التفكيك: العائلة ثم المستوى ثم المسمّى ────────────────────────────
$famRoles = array();
foreach ($FAMILY as $rid => $fam) { if (isset($rolePerms[$rid])) { $famRoles[$fam][] = $rid; } }

$famPerms = array();
foreach ($famRoles as $fam => $rids) {
    if (count($rids) < 2) { continue; }          // عائلةٌ بدورٍ واحد: لا اشتراكَ يُستخرج
    $inter = null;
    foreach ($rids as $rid) {
        $inter = ($inter === null) ? $rolePerms[$rid] : array_intersect_key($inter, $rolePerms[$rid]);
    }
    if ($inter) { $famPerms[$fam] = $inter; }
}

$lvlRoles = array();
foreach ($rolePerms as $rid => $_) { $lvlRoles[levelKeyOf($rid, $roleLevel[$rid] ?? 1)][] = $rid; }
$lvlPerms = array();
foreach ($lvlRoles as $lvl => $rids) {
    if (count($rids) < 2) { continue; }
    $inter = null;
    foreach ($rids as $rid) {
        $own = $rolePerms[$rid];
        $fam = $FAMILY[$rid] ?? null;
        if ($fam !== null && isset($famPerms[$fam])) { $own = array_diff_key($own, $famPerms[$fam]); }
        $inter = ($inter === null) ? $own : array_intersect_key($inter, $own);
    }
    if ($inter) { $lvlPerms[$lvl] = $inter; }
}

$titlePerms = array();
foreach ($rolePerms as $rid => $own) {
    $rest = $own;
    $fam = $FAMILY[$rid] ?? null;
    if ($fam !== null && isset($famPerms[$fam])) { $rest = array_diff_key($rest, $famPerms[$fam]); }
    $lvl = levelKeyOf($rid, $roleLevel[$rid] ?? 1);
    if (isset($lvlPerms[$lvl])) { $rest = array_diff_key($rest, $lvlPerms[$lvl]); }
    $titlePerms[$rid] = $rest;
}

// ── ③ فحصُ السلامة: الاتحادُ يساوي الأصلَ لكلِّ دور ─────────────────────
$lossy = array();
foreach ($rolePerms as $rid => $own) {
    $u = $titlePerms[$rid];
    $fam = $FAMILY[$rid] ?? null;
    if ($fam !== null && isset($famPerms[$fam])) { $u += $famPerms[$fam]; }
    $lvl = levelKeyOf($rid, $roleLevel[$rid] ?? 1);
    if (isset($lvlPerms[$lvl])) { $u += $lvlPerms[$lvl]; }
    if (count($u) !== count($own) || array_diff_key($u, $own) || array_diff_key($own, $u)) {
        $lossy[] = $rid;
    }
}

// ── التقرير ─────────────────────────────────────────────────────────────
echo "الواقع: " . count($rolePerms) . " دورًا · {$totalGrants} منحةً (راية×شاشة)\n\n";
echo "── العائلة (المشترك بين كل أدوارها)\n";
foreach ($famPerms as $fam => $p) { printf("   %-18s %4d بندًا  (أدوارها: %s)\n", $fam, count($p), implode(',', $famRoles[$fam])); }
if (!$famPerms) echo "   (لا شيء)\n";
echo "\n── المستوى (المشترك بعد طرح العائلة)\n";
foreach ($lvlPerms as $lvl => $p) { printf("   %-18s %4d بندًا  (%d دورًا)\n", $lvl, count($p), count($lvlRoles[$lvl])); }
if (!$lvlPerms) echo "   (لا شيء)\n";
echo "\n── المسمّى (الباقي لكل دور)\n";
$sumT = 0;
foreach ($titlePerms as $rid => $p) { $sumT += count($p); printf("   role_%-3d %-26s %4d بندًا\n", $rid, mb_substr($roleName[$rid], 0, 24), count($p)); }

$sumF = 0; foreach ($famPerms as $p) { $sumF += count($p); }
$sumL = 0; foreach ($lvlPerms as $p) { $sumL += count($p); }
echo "\n── الحصيلة\n";
echo "   بنودُ العائلة: {$sumF} · المستوى: {$sumL} · المسمّى: {$sumT} · المجموع: " . ($sumF + $sumL + $sumT) . "\n";
echo "   فحصُ السلامة (الاتحاد = الأصل لكل دور): " . (empty($lossy) ? "✔ صفرُ فقدٍ أو زيادة" : "✘ اختلاف في: " . implode(',', $lossy)) . "\n";

if (!empty($lossy)) { echo "\n✘ لا يُطبَّق مع وجود فقد.\n"; exit(1); }
if (!$APPLY) { echo "\n(معاينةٌ — التطبيق بـ --apply)\n"; exit(0); }

// ── ④ التطبيق: قالبٌ لكل مسمّى دورٍ + نسخةٌ منشورةٌ لكل قالبٍ + بنودُها ──
function tplId($conn, $kind, $key, $ceiling = 0) {
    $k = mysqli_real_escape_string($conn, $key);
    $r = mysqli_query($conn, "SELECT tpl_id FROM permission_templates WHERE tpl_kind='{$kind}' AND key_code='{$k}' LIMIT 1");
    if ($r && ($x = mysqli_fetch_assoc($r))) { return (int) $x['tpl_id']; }
    mysqli_query($conn, "INSERT INTO permission_templates (tpl_kind, key_code, is_ceiling, active) VALUES ('{$kind}','{$k}'," . (int) $ceiling . ",1)")
        or die('✘ tpl: ' . mysqli_error($conn) . "\n");
    return mysqli_insert_id($conn);
}
function publishedVersion($conn, $tplId, $reason) {
    $r = mysqli_query($conn, "SELECT ver_id FROM permission_template_versions WHERE tpl_id={$tplId} AND state='published' LIMIT 1");
    if ($r && ($x = mysqli_fetch_assoc($r))) { return (int) $x['ver_id']; }
    $rs = mysqli_real_escape_string($conn, $reason);
    mysqli_query($conn, "INSERT INTO permission_template_versions (tpl_id, version, effective_from, state, approval_ref, change_reason)
                         VALUES ({$tplId}, 1, CURDATE(), 'published', 'OWNER-2026-08-06', '{$rs}')")
        or die('✘ ver: ' . mysqli_error($conn) . "\n");
    return mysqli_insert_id($conn);
}
function putItems($conn, $verId, array $codes) {
    $n = 0;
    foreach (array_keys($codes) as $code) {
        $dim = (substr($code, -12) === ':screen_view') ? 'visibility' : 'action';
        $ce = mysqli_real_escape_string($conn, $code);
        $ex = mysqli_query($conn, "SELECT tp_id FROM template_permissions WHERE template_version_id={$verId} AND permission_code='{$ce}' LIMIT 1");
        if ($ex && mysqli_num_rows($ex)) { continue; }
        mysqli_query($conn, "INSERT INTO template_permissions (template_version_id, dimension, permission_code, effect)
                             VALUES ({$verId}, '{$dim}', '{$ce}', 'grant')") or die('✘ tp: ' . mysqli_error($conn) . "\n");
        $n++;
    }
    return $n;
}

$made = 0;
foreach ($famPerms as $fam => $codes) {
    $t = tplId($conn, 'family', $fam);
    $v = publishedVersion($conn, $t, 'اشتقاقٌ من role_permissions — المشترك بين أدوار العائلة');
    $made += putItems($conn, $v, $codes);
}
foreach ($lvlPerms as $lvl => $codes) {
    $t = tplId($conn, 'level', $lvl);
    $v = publishedVersion($conn, $t, 'اشتقاقٌ من role_permissions — المشترك بين أدوار المستوى بعد طرح العائلة');
    $made += putItems($conn, $v, $codes);
}
foreach ($titlePerms as $rid => $codes) {
    if (!$codes) { continue; }
    $t = tplId($conn, 'title', ems_role_title_key($rid));
    $v = publishedVersion($conn, $t, 'اشتقاقٌ من role_permissions — باقي صلاحيات الدور ' . $rid);
    $made += putItems($conn, $v, $codes);
}
echo "\nطُبِّق: {$made} بندًا جديدًا.\n";
$x = mysqli_fetch_assoc(mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM permission_templates) t,
    (SELECT COUNT(*) FROM permission_template_versions) v, (SELECT COUNT(*) FROM template_permissions) p"));
echo "الآن: قوالب={$x['t']} · نسخ={$x['v']} · بنود={$x['p']}\n";
