<?php
/**
 * tools/fix_rf01_surfaces.php — RF-01 ① جردُ الأسطحِ غيرِ المسجَّلةِ في modules
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكم (FIXA-0017/0018): «شغّلْ مسحًا يحصر الشاشاتِ ويسجّلها في modules
 *   **قبلَ** نشرِ الإصلاح — ولا تنشرِ الإصلاحَ قبلَ التسجيلِ، فالمنعُ الفوريُّ
 *   يُعطّل أربعينَ شاشةً حية».
 *
 * ◆ ما يقيسه: كلُّ ملفِّ سطحٍ (‎.php‎ يُضمِّن insidebar ⇒ يبلغ حارسَ العرض)
 *   يُحَلُّ بالمنطقِ نفسِه الذي يستعمله get_module_id_by_script_path — مطابقةٌ
 *   تامةٌ للمسارِ النسبيِّ · ثم اسمُ الملفِّ · ثم الذيلُ المسبوقُ بـ«/».
 * ◆ ما لا يقيسه: نقاطُ AJAX والمساعدون الذين لا يُضمّنون insidebar — فهم لا
 *   يبلغون حارسَ العرضِ أصلًا، وحمايتُهم من action_guard لا من هذا الحارس.
 *
 * التشغيل:
 *   php tools/fix_rf01_surfaces.php            → التقرير
 *   php tools/fix_rf01_surfaces.php --sql      → عبارات التسجيل المقترحة
 *   php tools/fix_rf01_surfaces.php --check    → وضعُ الفاحص: يرسب فوق صفر (خروج 1)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$mode = 'report';
foreach ($argv as $a) {
    if ($a === '--sql')   { $mode = 'sql'; }
    if ($a === '--check') { $mode = 'check'; }
}

require_once __DIR__ . '/fix_lib.php';
$db = fix_db();

/* ── ① جمعُ الأسطح ─────────────────────────────────────────────────────── */
$surfaces = fix_surface_files($ROOT);

/* ── ② حلُّ كلِّ سطحٍ إلى موديول بمنطقِ الحارسِ نفسِه ─────────────────── */
$unregistered = array();
$registered   = 0;
foreach ($surfaces as $rel) {
    if (fix_resolve_module_id($db, $rel) !== null) { $registered++; continue; }
    $unregistered[] = $rel;
}
sort($unregistered);

/* ── ③ الإخراج ────────────────────────────────────────────────────────── */
$total = count($surfaces);
$miss  = count($unregistered);

if ($mode === 'sql') {
    echo "-- RF-01 ① تسجيلُ الأسطحِ غيرِ المسجَّلةِ ({$miss} سطحًا) — يُنفَّذ قبلَ قلبِ الحارس\n";
    foreach ($unregistered as $rel) {
        $name = fix_screen_title($ROOT . '/' . $rel, $rel);
        echo "INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)\n"
           . "  SELECT " . fix_q($db, $name) . ", " . fix_q($db, $rel) . ", NULL, NULL, 0, 0, 'fa fa-file', 999\n"
           . "  WHERE NOT EXISTS (SELECT 1 FROM modules WHERE code = " . fix_q($db, $rel) . ");\n";
    }
    exit(0);
}

printf("الأسطحُ الممسوحة .......... %d\n", $total);
printf("المسجَّلةُ في modules ....... %d\n", $registered);
printf("◆ غيرُ المسجَّلة ........... %d\n", $miss);
echo str_repeat('─', 72) . "\n";
foreach ($unregistered as $rel) { echo '  ' . $rel . "\n"; }

if ($mode === 'check') {
    echo str_repeat('─', 72) . "\n";
    if ($miss === 0) { echo "✔ AC-F1-a · صفرُ سطحٍ غيرِ مسجَّل — الحارسُ يفشل مغلقًا بلا تعطيلِ شاشةٍ حية\n"; exit(0); }
    echo "✘ AC-F1-a · {$miss} سطحًا غيرَ مسجَّلٍ — قلبُ الحارسِ يُعطّلها. سجّلْها أولًا.\n";
    exit(1);
}
exit(0);
