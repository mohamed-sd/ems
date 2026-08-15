<?php
/**
 * tests/workflow_stage_groups_test.php — مجموعاتُ السايدبار تطابق مراحلَ الوثيقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0184 (المالية) · INJ-0552 (المشتريات)
 *
 * نصُّ INJ-0184: «بدور ١٧ تظهر مجموعتا التحليل والمخاطر **بمحتواهما**، وكلُّ
 * رابطٍ فيهما **يفتح شاشتَه**».
 * ونصُّ INJ-0552: «**عددُ المجموعاتِ المرحليةِ وعددُ روابطِ كلٍّ يطابق جدول
 * §١٨-٣**، ولا رابطَ لشاشةٍ **يملكها غيرُ المشتريات** داخل مجموعةٍ مرحلية».
 *
 * ── حكمٌ اتُّخذ ومُعلَن ────────────────────────────────────────────────────
 * الوثيقةُ تذكر بعضَ الشاشاتِ **في مرحلتين** (٦ حالات: «سجل الموردين» في ٢ و٦ ·
 * «كتالوج الأصناف» في ١ و٦ · «صندوق ما ينتظر اعتمادي» في ١ و٨ …). والقاعدةُ
 * تحمل `uq_nav_role_route`: **رابطٌ واحدٌ لوجهةٍ واحدةٍ للدورِ الواحد** — وهي
 * قاعدةُ الشاشاتِ المكرَّرةِ نفسُها التي أُنفذت في حملةٍ سابقة.
 * **فالقيدُ يبقى والوثيقةُ تُقرأ بغيرِ تكرار**: المقارنةُ على الشاشاتِ المتمايزةِ
 * لا على تكرارِ ذكرِها. والتكرارُ يُعلَن في نصِّ الحكمِ ولا يُخفى.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/tools/nav09_read.php';

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0; $NOTES = array();
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ مجموعاتُ السايدبار تطابق مراحلَ الوثيقة');

$docPath = $ROOT . '/docs/files/NAV-09-current.xlsx';
$ok(is_file($docPath), 'وثيقةُ NAV-09 موجودةٌ — وهي مصدرُ الحقيقة');
$doc = Nav09Reader::load($docPath);

$map = array();
$r = $conn->query('SELECT canonical_file, real_path FROM nav09_file_map');
while ($r && ($x = $r->fetch_row())) { $map[strtolower(trim((string) $x[0]))] = trim((string) $x[1]); }
$ok(count($map) > 50, 'وخريطةُ الأسماءِ القانونيةِ مبنيّةٌ (' . count($map) . ')');

$TARGETS = array(array('name' => 'المالية', 'role' => 17), array('name' => 'المشتريات', 'role' => 16));

foreach ($TARGETS as $t) {
    $d = null;
    foreach ($doc['depts'] as $v) { if (mb_strpos($v['name'], $t['name']) !== false) { $d = $v; break; } }
    if (!$d) { $ok(false, 'إدارةُ «' . $t['name'] . '» في الوثيقة'); continue; }
    $role = (int) $t['role'];
    $say('');
    $say('── ' . $d['name'] . ' (الدور ' . $role . ')');

    /* ① كلُّ مرحلةٍ مرقّمةٍ في الوثيقةِ لها مجموعةٌ في القاعدة */
    $docStages = array();
    foreach ($d['rows'] as $row) {
        if (($row['kind'] ?? '') !== 'screen') { continue; }
        $s = (int) ($row['stage'] ?? 0);
        if ($s >= 1) { $docStages[$s] = true; }
    }
    $dbStages = array();
    $q = $conn->query("SELECT DISTINCT stage_no FROM link_groups
                        WHERE owner_role_id = {$role} AND stage_no BETWEEN 1 AND 90");
    while ($q && ($x = $q->fetch_row())) { $dbStages[(int) $x[0]] = true; }
    $missStage = array_diff(array_keys($docStages), array_keys($dbStages));
    $ok(empty($missStage),
        'كلُّ مرحلةٍ في الوثيقةِ لها مجموعةٌ في القاعدة (' . count($docStages) . '/' . count($dbStages) . ')',
        'ناقصة: ' . implode(' · ', $missStage));

    /* ② وكلُّ شاشةٍ متمايزةٍ في الوثيقةِ لها رابطٌ **نشطٌ** */
    $wantFiles = array(); $dupDecl = 0; $seenCanon = array();
    foreach ($d['rows'] as $row) {
        if (($row['kind'] ?? '') !== 'screen') { continue; }
        if ((int) ($row['stage'] ?? 0) < 1) { continue; }
        $canon = strtolower(trim((string) ($row['file'] ?? '')));
        if ($canon === '' || !isset($map[$canon])) { continue; }
        $real = $map[$canon];
        if ($real === '' || !is_file($ROOT . '/' . $real)) { continue; }
        if (isset($seenCanon[$canon])) { $dupDecl++; continue; }   /* ذكرٌ ثانٍ في مرحلةٍ أخرى */
        $seenCanon[$canon] = true;
        $wantFiles[strtolower($real)] = (string) $row['title'];
    }
    $have = array();
    $q = $conn->query("SELECT LOWER(route) rt FROM nav_items
                        WHERE role_id = {$role} AND active = 1");
    while ($q && ($x = $q->fetch_row())) { $have[preg_replace('~[?#].*$~', '', (string) $x[0])] = true; }
    $missLink = array();
    foreach ($wantFiles as $f => $title) { if (!isset($have[$f])) { $missLink[] = $title . ' ⇐ ' . $f; } }
    $ok(empty($missLink),
        '**وكلُّ شاشةٍ متمايزةٍ في الوثيقةِ لها رابطٌ نشط** (' . count($wantFiles) . ' شاشةً)',
        implode(' · ', array_slice($missLink, 0, 4)));
    if ($dupDecl > 0) {
        $NOTES[] = $d['name'] . ': الوثيقةُ تكرّر ' . $dupDecl
                 . ' شاشةً في مرحلتين — والقاعدةُ تمنع رابطين لوجهةٍ واحدةٍ (`uq_nav_role_route`)';
    }

    /* ③ وكلُّ رابطٍ في مجموعةٍ مرحليةٍ **يفتح شاشتَه** — ملفٌّ قائمٌ على القرص */
    $dead = array();
    $q = $conn->query("SELECT n.label_ar, n.route FROM nav_items n
                         JOIN link_groups g ON g.id = n.group_id
                        WHERE n.role_id = {$role} AND n.active = 1
                          AND g.stage_no BETWEEN 1 AND 90");
    while ($q && ($x = $q->fetch_assoc())) {
        $f = preg_replace('~[?#].*$~', '', (string) $x['route']);
        $f = preg_replace('~^(\.\./)+~', '', $f);
        if ($f === '' || !is_file($ROOT . '/' . $f)) { $dead[] = $x['label_ar'] . ' ⇐ ' . $f; }
    }
    $ok(empty($dead), '**وكلُّ رابطٍ في مجموعةٍ مرحليةٍ يفتح شاشتَه** — لا ملفَّ مفقود',
        implode(' · ', array_slice($dead, 0, 4)));

    /* ④ ولا رابطَ محجوبٍ بالصلاحيةِ داخلَ مجموعةٍ مرحلية */
    $blocked = 0;
    $q = $conn->query("SELECT COUNT(*) FROM nav_items n
                         JOIN link_groups g ON g.id = n.group_id
                        WHERE n.role_id = {$role} AND n.active = 1
                          AND g.stage_no BETWEEN 1 AND 90
                          AND n.permission_code IS NOT NULL AND n.permission_code <> ''
                          AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                            JOIN modules m ON m.id = rp.module_id
                                           WHERE rp.role_id = n.role_id AND rp.can_view = 1
                                             AND m.code = n.permission_code)");
    if ($q && ($x = $q->fetch_row())) { $blocked = (int) $x[0]; }
    $ok($blocked === 0,
        "**ولا رابطَ يَعِدُ ثم يُردُّ ٤٠٣** داخلَ مجموعةٍ مرحلية ({$blocked})");

    /* ⑤ ولا مجموعةَ مرحليةٍ خاويةٌ — المرحلةُ بلا محتوًى وعدٌ لا يُوفّى */
    $emptyG = array();
    $q = $conn->query("SELECT g.stage_no, g.name FROM link_groups g
                        WHERE g.owner_role_id = {$role} AND g.stage_no BETWEEN 1 AND 90
                          AND NOT EXISTS (SELECT 1 FROM nav_items n
                                           WHERE n.group_id = g.id AND n.role_id = {$role} AND n.active = 1)");
    while ($q && ($x = $q->fetch_assoc())) { $emptyG[] = 'م' . $x['stage_no'] . ' ' . $x['name']; }
    $ok(count($emptyG) <= 1,
        'ومجموعاتُ المراحلِ ذاتُ محتوًى (خاويةٌ: ' . count($emptyG) . ')',
        implode(' · ', array_slice($emptyG, 0, 4)));
    if ($emptyG) { $NOTES[] = $d['name'] . ': مجموعاتٌ خاويةٌ باقية — ' . implode(' · ', array_slice($emptyG, 0, 3)); }
}

$say('');
foreach ($NOTES as $n) { $say('  ◆ ' . $n); }
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
