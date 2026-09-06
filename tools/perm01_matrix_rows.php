<?php
/**
 * tools/perm01_matrix_rows.php — إلحاقُ شاشاتِ PERM-01 بمصفوفةِ التنقّلِ المعيارية
 * ═══════════════════════════════════════════════════════════════════════════
 * **السجلُّ الثالثُ لسؤالٍ واحد**: `U1` في `tools/uxui_gates.php` لا يقرأ
 * `repair01_screen_registry` ولا `gov_screen_cycle` — بل **ملفَّ
 * `docs/uxui_matrix_20260818.csv`** عبر `uxp_matrix()`. فالسطحُ المُصيَّرُ
 * يحتاج صفًّا في **ثلاثةِ سجلّاتٍ** لا في واحد.
 *
 * ⛔ **والقيمُ منقولةٌ من صفِّ الشاشةِ النظيرةِ في المجموعةِ نفسِها** —
 *   `Governance/break_glass.php` لفتحِ الطوارئ · `Governance/perm_matrix.php`
 *   لشاشاتِ الصلاحيات — ومن سجلِّ المواضعِ الحاكمِ للاسمِ المعياريّ.
 *   والتعريفُ **يصف فعلَ الشاشةِ المقيسَ من شيفرتِها** لا عبارةً عامّة.
 *
 * ◆ **والحالةُ `APPROVED`** لأنَّ الاسمَ المعياريَّ مأخوذٌ من موضعٍ نشطٍ
 *   معتمَدٍ في `nav_workspace_placements` — لا اجتهادَ فيه.
 *
 * التشغيل: php tools/perm01_matrix_rows.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
$CSV   = $ROOT . '/docs/uxui_matrix_20260818.csv';
if (!is_file($CSV)) { exit("⛔ المصفوفةُ مفقودة\n"); }

$SRC = 'PERM-01 · تسجيلٌ لحق بناءً سابقًا — الاسمُ من nav_workspace_placements';

/* route => [الاسم, المجموعة, الإدارة, الترتيب, التعريف, القسم] */
$NEW = array(
    'Governance/auth_profile_edit.php' => array(
        'بناء القوالب', 'إدارة الصلاحيات والأدوار', 'إدارة الصلاحيات', 5,
        'بناءُ قالبِ سلطةٍ لدورٍ يُختار: تُضاف بنودُه وتُنزع، والقالبُ ينفذ على حامليه بعد الاعتماد. تستخدمها إدارة الصلاحيات.',
        'إدارة الصلاحيات والأدوار'),
    'Governance/perm_audit_log.php' => array(
        'محاسبة الصلاحيات', 'إدارة الصلاحيات والأدوار', 'إدارة الصلاحيات', 6,
        'سجلُّ محاسبةِ الصلاحيات: يعرض منحَ السلطةِ وتغيُّراتِها قراءةً فقط — لا يكتب منحًا ولا يعدّله. تستخدمها إدارة الصلاحيات والمراجعة الداخلية.',
        'إدارة الصلاحيات والأدوار'),
    'Governance/break_glass_open.php' => array(
        'فتح الطوارئ', 'الالتزام والامتثال', 'الحوكمة والالتزام', 631,
        'فتحُ حالةِ طوارئ («كسر الزجاج») بسببٍ إلزاميٍّ ومدةٍ قصيرةٍ وأثرٍ مسجَّل. الغرضُ تمكينُ التصرّفِ العاجلِ بموافقتَين. تستخدمها إدارة الصلاحيات.',
        'الحوكمة والالتزام'),
    'Governance/bus_monitor.php' => array(
        'مراقبة ناقل الأحداث', 'الحوكمة والضوابط', 'الحوكمة والالتزام', 632,
        'قراءةٌ حيّةٌ لناقلِ الأحداث: مؤشّراتُ المستهلكين وتأخّرُهم والرسائلُ الميتة. لا تنشر حدثًا ولا تعيد تسليمَه. تستخدمها الحوكمة والمالية.',
        'الحوكمة والالتزام'),
    'Settings/db_backup.php' => array(
        'النسخ الاحتياطي لقاعدة البيانات', 'الحوكمة والضوابط', 'الحوكمة والالتزام', 633,
        'أخذُ نسخةٍ احتياطيّةٍ لقاعدةِ البياناتِ وعرضُ المتاحِ منها وجدولتُها. والاستعادةُ والاستيرادُ خارجَ هذه الشاشةِ عن قصد. تستخدمها الحوكمة.',
        'الحوكمة والالتزام'),
);

$fh = fopen($CSV, 'r');
$hdr = fgetcsv($fh);
$have = array(); $maxN = 0;
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) !== count($hdr)) { continue; }
    $row = array_combine($hdr, $r);
    $have[mb_strtolower(trim($row['route']))] = true;
    $maxN = max($maxN, (int) $row['n']);
}
fclose($fh);
printf("المصفوفةُ الآن: %d مسارًا · أقصى ترقيمٍ %d\n", count($have), $maxN);

$lines = array(); $n = $maxN;
foreach ($NEW as $route => $S) {
    if (isset($have[mb_strtolower($route)])) { printf("  ⟳ قائمٌ سلفًا: %s\n", $route); continue; }
    list($name, $group, $dept, $sort, $def, $section) = $S;
    $n++;
    $cells = array(
        'n' => $n, 'route' => $route, 'current_name' => $name, 'canonical_ar' => $name,
        'canonical_en' => '—', 'old_names' => '—', 'definition' => $def,
        'owner_dept' => $dept, 'level' => '2 — العمليات', 'canonical_group' => $group,
        'sort' => $sort, 'nature' => 'شاشةٌ مستقلة', 'dept_count' => 1, 'depts' => $dept,
        'decision' => 'بُني في جولةِ PERM-01 ثمَّ سُجِّل في موضعِه المعياريّ',
        'status' => 'APPROVED', 'derivation' => $SRC, 'view_of' => '—', 'merge_into' => '—',
        'retirement_status' => 'ACTIVE', 'route_variant' => '—', 'current_label' => $name,
        'current_parent' => $group, 'current_order' => $sort, 'section' => $section,
    );
    $out = array();
    foreach ($hdr as $h) { $out[] = isset($cells[$h]) ? $cells[$h] : '—'; }
    $lines[] = $out;
    printf("  + %-4d %-36s «%s» · %s\n", $n, mb_substr($route, 0, 35), $name, $group);
}
if (!$lines) { echo "\nلا جديدَ يُكتب.\n"; exit(0); }
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* الإلحاقُ بنهايةِ الملفِّ — والسطرُ الأخيرُ قد يكون بلا فاصلِ أسطر */
$raw = (string) file_get_contents($CSV);
if (substr($raw, -1) !== "\n") { $raw .= "\n"; }
$fh = fopen($CSV, 'w');
fwrite($fh, $raw);
foreach ($lines as $out) { fputcsv($fh, $out); }
fclose($fh);
printf("\n✔ أُلحق %d صفًّا\n", count($lines));

/* ═══ التحقّقُ بإعادةِ القراءة ═══ */
$fh = fopen($CSV, 'r'); $h2 = fgetcsv($fh); $ok = 0;
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) !== count($h2)) { continue; }
    $row = array_combine($h2, $r);
    if (isset($NEW[trim($row['route'])])) { $ok++; }
}
fclose($fh);
printf("✔ أُعيدت القراءةُ: %d من %d مسارًا حاضرٌ في المصفوفة\n", $ok, count($NEW));
exit($ok === count($NEW) ? 0 : 1);
