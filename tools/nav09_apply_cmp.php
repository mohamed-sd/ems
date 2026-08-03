<?php
/**
 * tools/nav09_apply_cmp.php — تطبيقُ CMP-01 بوصفها قرارَ المالك (توضيح 2026-08-03)
 * ───────────────────────────────────────────────────────────────────────────
 * القاعدة: «المُقرَّرُ يُنقل، وغيرُ المُقرَّر لا يُلمس».
 *   - رابطٌ في «خارج الوثيقة» حكمت عليه CMP «مطبَّقة» وسمّت ملفَه القانوني
 *     → يُدمج بمقابله: تبطيلٌ + تحويلٌ بعدّادٍ من مساره القديم إلى القانوني.
 *   - «لا مقابل لها» (تحتاج قرارًا) أو غائبٌ عن CMP → يبقى في «خارج الوثيقة».
 * لا يُحذف التبويبُ — يتقلص فقط. --apply للتنفيذ (الافتراض معاينة).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

/* ورقةُ CMP لكل عائلة أدوار (قرارُ الشاشة يسري على أدوار إدارتها كلِّها) */
$SHEET_ROLES = array(
    'ادارة التشغيل' => array(1), 'ادارة الموردين' => array(2, 8),
    'ادارة الاسطول' => array(3, 10), 'ادارة الموارد البشرية' => array(4),
    'إدارة الموقع' => array(6, 7), 'الإدارة التنفيذية' => array(9),
    'ادارة المبيعات' => array(12), 'ادارة الصيانة' => array(13, 14),
    'إدارة الصلاحيات' => array(15), 'إدارة المشتريات' => array(16),
    'إدارة المالية' => array(17, 18, 19, 20, 21, 22),
    'إدارة النقل والترحيل' => array(23), 'إدارة البلاغات' => array(24),
    'أمين المستودع' => array(25), 'إدارة التمويل' => array(26),
    'القوى التشغيلية' => array(27),
);

/* القاموس: قانوني → مساره الحي */
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }

/* أعضاءُ «خارج الوثيقة» الأحياء مفهرسين بـ(دور، اسمُ الملف الأخير) */
$others = array();
$r = mysqli_query($conn, "SELECT ni.id, ni.role_id, ni.label_ar, ni.route FROM nav_items ni
                          JOIN link_groups lg ON lg.id = ni.group_id
                          WHERE lg.group_code LIKE 'n9s99_others%' AND ni.active = 1");
while ($x = mysqli_fetch_assoc($r)) {
    $base = strtolower(basename(preg_replace('/[#?].*$/', '', $x['route'])));
    $others[$x['role_id'] . '|' . $base][] = $x;
}

$wb = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(__DIR__ . '/../tmp_CMP01.xlsx');
$wb->setReadDataOnly(true);
$wb = $wb->load(__DIR__ . '/../tmp_CMP01.xlsx');

$merged = 0; $undecided = 0; $noTarget = array(); $done = array();
foreach ($wb->getSheetNames() as $sheet) {
    if (!isset($SHEET_ROLES[$sheet])) { continue; }
    $roles = $SHEET_ROLES[$sheet];
    foreach ($wb->getSheetByName($sheet)->toArray(null, true, false, false) as $row) {
        $tab = trim((string) ($row[1] ?? ''));
        if (mb_strpos($tab, 'خارج الوثيقة') === false) { continue; } // قرارُنا محصورٌ بأعضائها
        $file = strtolower(trim((string) ($row[3] ?? '')));
        $verdict = trim((string) ($row[5] ?? ''));
        $canonical = trim((string) ($row[7] ?? ''));
        if ($file === '') { continue; }
        $determined = (mb_strpos($verdict, 'مطبَّقة') !== false || mb_strpos($verdict, 'مطبقة') !== false)
                      && $canonical !== '' && $canonical !== '—';
        if (!$determined) { $undecided++; continue; }
        $target = null;
        if (isset($map[$canonical]) && $map[$canonical]['state'] !== 'soon' && $map[$canonical]['real_path'] !== null) {
            $target = $map[$canonical]['real_path'];
        }
        if ($target === null) { $noTarget[] = "$sheet: $file → $canonical (قانونيُّه غيرُ حيٍّ بعد)"; continue; }
        foreach ($roles as $role) {
            foreach (($others[$role . '|' . $file] ?? array()) as $item) {
                if (isset($done[$item['id']])) { continue; }
                $done[$item['id']] = 1;
                $merged++;
                if ($APPLY) {
                    mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id = {$item['id']}");
                    $old = mysqli_real_escape_string($conn, preg_replace('/[#?].*$/', '', $item['route']));
                    $new = mysqli_real_escape_string($conn, $target);
                    if ($old !== $new) {
                        mysqli_query($conn, "INSERT INTO nav_redirects (old_route, new_route, active, hits)
                                             VALUES ('$old', '$new', 1, 0)
                                             ON DUPLICATE KEY UPDATE new_route = '$new', active = 1");
                    }
                }
            }
        }
    }
}

$r = mysqli_query($conn, "SELECT COUNT(*) FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
                          WHERE lg.group_code LIKE 'n9s99_others%' AND ni.active = 1");
$remain = mysqli_fetch_row($r)[0];
echo ($APPLY ? '═ نُفِّذ ═' : '═ معاينة ═') . "\n";
echo "دُمج بتحويلٍ إلى قانونيّه: $merged\n";
echo "غيرُ مُقرَّرٍ (يبقى بلا مساس): $undecided صفًّا في أوراق CMP\n";
echo "مُقرَّرٌ لكن قانونيُّه «قريبًا» — لا يُنفَّذ الآن: " . count($noTarget) . "\n";
foreach (array_slice($noTarget, 0, 10) as $t) { echo "  ⏳ $t\n"; }
echo "المتبقي في «خارج الوثيقة»" . ($APPLY ? ' بعد التنفيذ' : ' حاليًّا') . ": $remain\n";
