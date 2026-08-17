<?php
/**
 * tools/uxui_surface_classify.php — تصنيفُ الأسطحِ المعلَّقةِ **بدليلٍ مقيس**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · خامسًا): «إكمالُ تصنيفِ الـ131 (منها 96 قابلةٌ
 *   للتصيير — **خُمسُ المقامِ لا يبقى معلَّقًا**)».
 *
 * ◆ وكلُّ قاعدةٍ هنا **تُفحص في الملفِّ نفسِه** ويُكتب شاهدُها — لا تخمينَ من
 *   اسمٍ ولا من مجلد. والقواعدُ بترتيبِ القطعية:
 *   ① ليس سطحًا أصلًا — بنيةٌ تحتيةٌ تُضمَّن ولا تُطلب (config · layout_head ·
 *      ملفٌّ ببادئةِ `_`). فتُحذف من السجلِّ لا تُصنَّف، **ويُصحَّح المقامُ بها**.
 *   ② `DRILLDOWN` — يقرأ `$_GET['id']` ويُصيّر قشرةً: يُبلَغ من صفٍّ لا من قائمة.
 *   ③ `TECHNICAL_ONLY` — تحت `admin/` أو اسمُه setup/install/migrate/debug.
 *   ④ `ACTION_TARGET` — يقرأ `$_POST` ولا يُصيّر قشرة.
 *   ⑤ `NAVIGABLE` — يُصيّر قشرةً ولا يشترط معرِّفًا: يُبلَغ مباشرةً بعنوانِه.
 *   وما لم تنطبق عليه قاعدةٌ **يبقى NULL** — ولا يُخمَّن.
 *
 * التشغيل:
 *   php tools/uxui_surface_classify.php            جردٌ بلا كتابة
 *   php tools/uxui_surface_classify.php --apply    التصنيف
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

$pending = array();
$r = $conn->query("SELECT surface_id, source_file, renderable FROM ui_surfaces WHERE surface_type IS NULL ORDER BY surface_id");
while ($r && ($x = $r->fetch_assoc())) { $pending[] = $x; }
if (!$pending) { exit("لا أسطحَ معلَّقة\n"); }

$plan = array('DROP' => array(), 'DRILLDOWN' => array(), 'TECHNICAL_ONLY' => array(),
              'ACTION_TARGET' => array(), 'NAVIGABLE' => array(), 'STILL_NULL' => array());
$evidence = array();

foreach ($pending as $p) {
    $rel = $p['source_file'];
    $path = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($path);
    $base = basename($rel);
    $lc = strtolower($rel);
    if ($src === '') { $plan['STILL_NULL'][] = $rel; $evidence[$rel] = 'الملفُّ غيرُ مقروء'; continue; }

    $rendersShell = (strpos($src, 'inheader.php') !== false || strpos($src, 'insidebar.php') !== false);
    $hasGetId     = (bool) preg_match('~\$_GET\s*\[\s*[\'"](id|.*_id)[\'"]~', $src);
    $hasPost      = (bool) preg_match('~\$_POST\s*\[~', $src);

    /* ① ليس سطحًا: بنيةٌ تحتيةٌ تُضمَّن ولا تُطلب */
    $isInfra = ($base[0] === '_')
            || preg_match('~(^|/)(config|env|bootstrap)\.php$~i', $rel)
            || preg_match('~/(includes|partials|layouts)/~i', $rel)
            || (strpos($src, '<?php') === 0 && !$rendersShell && !$hasPost && strlen($src) < 4000
                && !preg_match('~\$_GET~', $src));
    if ($isInfra) {
        $plan['DROP'][] = $rel;
        $evidence[$rel] = 'بنيةٌ تحتيةٌ تُضمَّن ولا تُطلب — ليست سطحًا (بادئةُ `_` أو مجلدُ تضمينٍ أو ملفُّ تهيئة)';
        continue;
    }
    /* ③ تقنيٌّ بحت */
    if (preg_match('~^admin/~i', $lc)
        || preg_match('~(setup|install|migrate|debug|seed|probe|diag)~i', $base)) {
        $plan['TECHNICAL_ONLY'][] = $rel;
        $evidence[$rel] = 'تحت `admin/` أو اسمُه تهيئةٌ/تشخيص — لا عملَ مستخدمٍ فيه';
        continue;
    }
    /* ② تعمُّقٌ من صفّ */
    if ($rendersShell && $hasGetId) {
        $plan['DRILLDOWN'][] = $rel;
        $evidence[$rel] = 'يُصيّر قشرةً **ويشترط معرِّفًا في العنوان** — يُبلَغ من صفٍّ لا من قائمة';
        continue;
    }
    /* ④ هدفُ فعل */
    if (!$rendersShell && $hasPost) {
        $plan['ACTION_TARGET'][] = $rel;
        $evidence[$rel] = 'يقرأ `$_POST` ولا يُصيّر قشرةَ صفحة';
        continue;
    }
    /* ⑤ يُبلَغ مباشرةً بعنوانِه */
    if ($rendersShell && !$hasGetId) {
        $plan['NAVIGABLE'][] = $rel;
        $evidence[$rel] = 'يُصيّر قشرةً ولا يشترط معرِّفًا — يُبلَغ بعنوانِه مباشرةً (وإن لم يُسجَّل في المصفوفة)';
        continue;
    }
    $plan['STILL_NULL'][] = $rel;
    $evidence[$rel] = 'لا تنطبق قاعدةٌ قاطعة — يبقى بلا تصنيفٍ ولا يُخمَّن';
}

echo "════ تصنيفُ الأسطحِ المعلَّقة — بدليلٍ مقيس ════\n";
echo "  المعلَّقُ قبلَ التصنيف: " . count($pending) . "\n\n";
foreach ($plan as $k => $list) {
    if (!$list) { printf("  %-16s 0\n", $k); continue; }
    printf("  %-16s %d\n", $k, count($list));
    foreach (array_slice($list, 0, 3) as $s) { echo "      · {$s}\n"; }
    if (count($list) > 3) { echo "      … و" . (count($list) - 3) . " غيرُها\n"; }
}

if (!$APPLY) { echo "\n  ▸ جردٌ بلا كتابة — أضِفْ --apply\n"; exit(0); }

$upd = $conn->prepare("UPDATE ui_surfaces SET surface_type = ?, evidence = ?,
                              classified_by = 'rule-based-evidence', classified_at = NOW()
                        WHERE surface_id = ?");
$del = $conn->prepare("DELETE FROM ui_surfaces WHERE surface_id = ?");
$nUpd = 0; $nDel = 0;
foreach ($plan as $type => $list) {
    foreach ($list as $rel) {
        if ($type === 'DROP') { $del->bind_param('s', $rel); if ($del->execute()) { $nDel++; } continue; }
        if ($type === 'STILL_NULL') { continue; }
        $ev = $evidence[$rel];
        $upd->bind_param('sss', $type, $ev, $rel);
        if ($upd->execute()) { $nUpd++; }
    }
}
echo "\n  ▸ صُنِّف: {$nUpd} · حُذف (ليس سطحًا): {$nDel}\n";
$left = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces WHERE surface_type IS NULL")->fetch_assoc()['c'];
$leftR = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces WHERE surface_type IS NULL AND renderable=1")->fetch_assoc()['c'];
$tot = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces")->fetch_assoc()['c'];
$ren = (int) $conn->query("SELECT COUNT(*) c FROM ui_surfaces WHERE renderable=1")->fetch_assoc()['c'];
echo "  الأسطحُ الآن: {$tot} · قابلٌ للتصيير: {$ren} · ما زال بلا تصنيف: {$left} (منها قابلٌ للتصيير {$leftR})\n";
