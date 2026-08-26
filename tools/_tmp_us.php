<?php
$f = __DIR__ . '/repair01_w135_scan.php';
$s = file_get_contents($f);
$P = array();

/* ② أحكامُ الملكيّة — تُقرأ من عمودِها الجديد */
$P[] = array(
"\$have = array();\nforeach (\$rows(\"SELECT DISTINCT w1_verdict v FROM repair01_ownership WHERE w1_verdict <> ''\") as \$x) { \$have[] = \$x['v']; }",
"\$have = array();\nforeach (\$rows(\"SELECT DISTINCT ownership_verdict v FROM repair01_screen_registry WHERE ownership_verdict <> ''\") as \$x) { \$have[] = \$x['v']; }");

/* ②b — التكرارُ يُقاس على التصنيفِ الجديد */
$P[] = array(
"\$srcDup = \$n(\"SELECT COUNT(*) FROM (SELECT route FROM repair01_ownership\n              WHERE ownership_kind = 'SOURCE' GROUP BY route HAVING COUNT(DISTINCT owner_dept) > 1) z\");",
"\$srcDup = \$n(\"SELECT COUNT(*) FROM (SELECT LOWER(screen_file) f FROM repair01_screen_registry\n              WHERE surface_kind = 'SOURCE' GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z\");");

/* ③ التقاطعاتُ — تُقاس بما صُنِّف لا بغيابِ العمود */
$P[] = array(
"\$noKind = \$n(\"SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') <> ''\n              AND owner_code IN ('DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17')\");\nprintf(\"     أسطحُ الإداراتِ العشرِ المتقاطعة: %d — ولا عمودَ Source/Projection يحملها بعد\n\", \$noKind);",
"\$TEN = \"'DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17'\";\n\$xAll  = \$n(\"SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN (\$TEN)\");\n\$xKind = \$n(\"SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN (\$TEN) AND surface_kind <> ''\");\nprintf(\"     أسطحُ الإداراتِ العشرِ المتقاطعة: %d · منها مُصنَّفٌ %d\n\", \$xAll, \$xKind);");

/* ③ الخاتمة — الحكمُ صار يُقاس */
$P[] = array(
"\$open++;\necho \"     ◆ الحكمُ غيرُ مسجَّلٍ لأيِّ سطح — لا عمودَ تصنيفٍ Source/Projection في السجلّ\n\";",
"if (!\$line('3a', 'سطحٌ متقاطعٌ مُصنَّفٌ مصدرًا أو إسقاطًا', \$xKind, \$xAll)) { \$open++; }");

/* ④ الخريطة — الأعمدةُ الجديدة */
$P[] = array(
"    'Canonical Screen ID' => 'screen_id', 'Canonical Arabic Label' => '—',\n    'Domain Owner' => 'owner_code', 'Source/Projection' => '—',\n    'Canonical Route' => 'route', 'Lifecycle Position' => 'lifecycle',\n    'Server View Guard' => 'guard_kind', 'Server Action Guard' => '—',\n    'Permission Policy' => '—', 'Grain' => '—', 'Source of Truth' => '—', 'State Model ref' => '—',",
"    'Canonical Screen ID' => 'screen_id', 'Canonical Arabic Label' => 'canonical_label_ar',\n    'Domain Owner' => 'owner_code', 'Source/Projection' => 'surface_kind',\n    'Canonical Route' => 'route', 'Lifecycle Position' => 'lifecycle',\n    'Server View Guard' => 'guard_kind', 'Server Action Guard' => 'action_guard',\n    'Permission Policy' => 'permission_policy', 'Grain' => 'grain_ar',\n    'Source of Truth' => 'source_of_truth', 'State Model ref' => 'state_model_ref',");

/* ⑤b — الحقولُ الخمسةُ صارت قائمة */
$P[] = array(
"if (!\$line('5b', 'حقولُ البند 11 التسعةُ في جدولِ القرارات', \$fields + 4, 9, 'ناقصٌ ' . (5 - \$fields))) { \$open++; }",
"if (!\$line('5b', 'حقولُ البند 11 التسعةُ في جدولِ القرارات', \$fields + 4, 9, 'ناقصٌ ' . (5 - \$fields))) { \$open++; }\n\$auditRows = \$n(\"SELECT COUNT(*) FROM repair01_decision_audit\");\nif (!\$line('5c', 'أحكامُ المراجعةِ العكسيّةِ مقيَّدةٌ في جدولِها', (\$auditRows > 0 ? 1 : 0), 1, \"صفوف \$auditRows\")) { \$open++; }");

/* ⑦ تصنيفُ دَينِ المالية */
$P[] = array(
"echo \"     ◆ ولا عمودَ تصنيفٍ (TARGET/SOURCE/PROJECTION/DUPLICATE/MERGE/RETIRE/READ_ONLY) بعد\n\";",
"\$finCls = \$n(\"SELECT COUNT(*) FROM repair01_screen_registry\n               WHERE owner_code IN ('DEP-05','DEP-06') AND finance_debt_class <> ''\");\nif (!\$line('7b', 'سطحٌ ماليٌّ مُصنَّفٌ لتسييجِ دَينِه', \$finCls, \$finAll)) { \$open++; }");

/* ⑧ الأشباح — تُقاس بعمودِ القرارِ الجديد */
$P[] = array(
"\$decided = \$n(\"SELECT COUNT(*) FROM repair01_target_gaps WHERE verdict IN ('BUILD','MERGE','TAB','PROJECTION','RETIRE','NOT_APPLICABLE')\");",
"\$decided = \$n(\"SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition <> ''\");");

/* المقامُ صار 15 بندًا */
$P[] = array("printf(\"بنودٌ مستوفاةٌ اليوم: %d · **باقٍ عملٌ في %d**\n\", 13 - \$open, \$open);",
             "printf(\"بنودٌ مستوفاةٌ اليوم: %d · **باقٍ عملٌ في %d**\n\", 16 - \$open, \$open);");

foreach ($P as $i => $p) {
    if (strpos($s, $p[0]) === false) { echo "✘ ترقيعٌ " . ($i + 1) . " لم يُطابَق\n"; exit(1); }
    $s = str_replace($p[0], $p[1], $s);
}
file_put_contents($f, $s);
echo "✔ حُدِّث المقياس — تسعةُ مواضع\n";
