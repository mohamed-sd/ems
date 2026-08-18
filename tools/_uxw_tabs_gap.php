<?php
/* tools/_uxw_tabs_gap.php — شاشاتٌ مسجَّلةٌ في رحلةِ كيانٍ ولا تحمل شريطَها (مسبارُ قراءة) */
require_once __DIR__ . '/../includes/entity_tabs.php';
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$reg = ems_entity_tabs_registry();
$missing = array(); $have = 0; $dead = array();
foreach ($reg as $ent => $def) {
    foreach ($def['tabs'] as $label => $route) {
        if ($route === '') continue;
        $p = $ROOT . '/' . $route;
        if (!is_file($p)) { $dead[] = "$ent :: $label :: $route"; continue; }
        $s = file_get_contents($p);
        /* الشريطُ إمّا نداءٌ صريحٌ في الشاشة، أو ضبطُ $ENTITY_KEY ليُخرجه الغلافُ
           (eng01_screen_view / fin_analysis_shell) — والثاني لا يذكر اسمَ الدالة */
        if (strpos($s, 'ems_entity_tabs') !== false || preg_match('/\$ENTITY_KEY\s*=/u', $s)) { $have++; continue; }
        $missing[] = array($ent, $label, $route);
    }
}
printf("شاشاتٌ في سجلِّ الرحلاتِ تحمل الشريط: %d\n", $have);
printf("شاشاتٌ في السجلِّ بلا شريط: %d\n", count($missing));
foreach ($missing as $m) printf("  %-12s %-24s %s\n", $m[0], $m[1], $m[2]);
if ($dead) { printf("\nمساراتٌ في السجلِّ بلا ملف: %d\n", count($dead)); foreach ($dead as $d) echo "  $d\n"; }
