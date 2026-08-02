<?php
/**
 * tools/nav01_deliverables.php — مخرجات الموجة ⑮ (NAV-01→03)
 * ───────────────────────────────────────────────────────────────────────────
 * NAV-D1 الجرد الواحد (يغذي SEC-02 وSEC-05 — مرور واحد §2/§11)
 * NAV-D2 مصفوفة الترحيل بالأعمدة السبعة (§11) — قرار مقترح ★ لكل صف
 * NAV-D3 حسم DEC-NAV-E صفًّا صفًّا (دمج قائمتي الموقع 5↔6)
 * التشغيل: php tools/nav01_deliverables.php → docs/nav01/
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
$db->set_charset('utf8mb4');
$OUT = dirname(__DIR__) . '/docs/nav01';
if (!is_dir($OUT)) { mkdir($OUT, 0777, true); }
$today = $db->query('SELECT CURDATE() d')->fetch_assoc()['d'];

// المجموعات الثماني (NAV-01 §4 · DEC-NAV-J: مستوى ثانٍ داخل الأبواب)
$G8 = array('g1' => 'لوحة الإدارة', 'g2' => 'العمل اليومي', 'g3' => 'السجلات والملفات',
    'g4' => 'المراجعة والاعتماد', 'g5' => 'المتابعة والاستثناءات', 'g6' => 'الإغلاق والتسوية',
    'g7' => 'التقارير', 'g8' => 'الإعدادات');

/** اقتراح المجموعة: الباب أولًا ثم كلمات الاسم (المتابعة والإغلاق داخل DAILY/REC). */
function nav_propose_group($door, $label)
{
    $l = (string) $label;
    if (mb_strpos($l, 'لوحة') !== false || mb_strpos($l, 'داشبورد') !== false) { return 'g1'; }
    if (preg_match('/متابعة|مراقبة|انحراف|متأخر|تنبيه|منتهية|استثناء/u', $l)) { return 'g5'; }
    if (preg_match('/إقفال|اقفال|إغلاق|اغلاق|تسوية|تسويات|جرد|مسيّر|مسير/u', $l)) { return 'g6'; }
    if (preg_match('/تقرير|تقارير|كشف|قوائم|ميزان/u', $l)) { return 'g7'; }
    if (preg_match('/إعداد|اعداد|قواعد|أنواع|انواع|نماذج|تصنيف|سياسات|حدود/u', $l)) { return 'g8'; }
    if (preg_match('/اعتماد|موافق|صندوق/u', $l)) { return 'g4'; }
    switch ($door) {
        case 'HOME': return 'g1';
        case 'DAILY': return 'g2';
        case 'REC': return 'g3';
        case 'APPR': return 'g4';
        case 'REP': return 'g7';
        case 'SET': return 'g8';
        default: return 'g3'; // GOV·FIN: سجلات ما لم تدل الكلمات
    }
}

// ═══ NAV-D1 · الجرد الواحد ═══
$rows = $db->query(
    "SELECT n.id, n.role_id, r.name role_name, n.door, n.group_id, n.label_ar, n.route,
            n.sort_order, n.active, m.id module_id, m.code module_code
       FROM nav_items n
       LEFT JOIN roles r ON r.id = n.role_id
       LEFT JOIN modules m ON m.id = n.module_id
      ORDER BY n.role_id, n.door, n.sort_order")->fetch_all(MYSQLI_ASSOC);
$csv = fopen($OUT . '/NAV-D1_inventory.csv', 'w');
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, array('nav_id', 'role_id', 'role_name', 'door', 'group_id', 'label_ar', 'route', 'sort', 'active', 'module_id', 'module_code'));
foreach ($rows as $x) { fputcsv($csv, $x); }
fclose($csv);
echo 'D1 ✔ (' . count($rows) . " صفًّا)\n";

// ═══ NAV-D2 · مصفوفة الترحيل — الأعمدة السبعة ═══
// المكررات بالاسم (لحسم «لا اسم لشاشتين»)
$dupLabels = array();
$r = $db->query("SELECT label_ar FROM nav_items WHERE active=1 GROUP BY label_ar, role_id HAVING COUNT(*) > 1");
while ($r && ($x = $r->fetch_assoc())) { $dupLabels[$x['label_ar']] = true; }

$csv = fopen($OUT . '/NAV-D2_migration_matrix.csv', 'w');
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, array('nav_id', 'role_id', 'door', 'label_ar', 'route',
    '①group_new', '②appearance', '③parent_screen', '④action', '⑤alt_route', '⑥permission', '⑦test'));
$stats = array('keep' => 0, 'merge' => 0, 'legacy' => 0);
foreach ($rows as $x) {
    $g = nav_propose_group($x['door'], $x['label_ar']);
    $action = '★إبقاء';
    $alt = '';
    if (mb_strpos((string) $x['label_ar'], 'قديم') !== false) {
        $action = '★دمج وتحويل مسار بعدّاد (nav_redirects)';
        $alt = 'النسخة الحية للشاشة نفسها';
        $stats['legacy']++;
    } elseif (isset($dupLabels[$x['label_ar']]) && intval($x['active']) === 1) {
        $action = '★مراجعة تكرار الاسم — دمج أو تمييز';
        $stats['merge']++;
    } else {
        $stats['keep']++;
    }
    fputcsv($csv, array($x['id'], $x['role_id'], $x['door'], $x['label_ar'], $x['route'],
        $g . ' ' . $GLOBALS['G8'][$g], '★قائمة', '', $action, $alt,
        'role:' . $x['role_id'], 'المسار القديم يصل بلا خطأ (nav_redirects)'));
}
fclose($csv);
$md = "# NAV-D2 · مصفوفة ترحيل المسارات (§11) — {$today}\n\n"
    . "> «لا يبدأ تنفيذ أي إعادة ترتيب قبل جدول يربط كل مسار بموضعه الجديد» — CSV كامل بالأعمدة السبعة، وكل قرار ★ مقترح ينتظر الاعتماد.\n\n"
    . "| القياس | العدد |\n|---|---|\n"
    . "| صفوف الجرد (nav_items) | " . count($rows) . " |\n"
    . "| إبقاء | {$stats['keep']} |\n"
    . "| مراجعة تكرار اسم | {$stats['merge']} |\n"
    . "| نسخ قديمة تُدمج بتحويل مسار | {$stats['legacy']} |\n\n"
    . "**شرط القبول:** صفر صف بلا مجموعة وطريقة ظهور وإجراء ✔ (المولّد يملأها كلها) · وصفر مسار قديم يعطي خطأ (عبر `nav_redirects` القائم بعدّاده).\n";
file_put_contents($OUT . '/NAV-D2_matrix_summary_ar.md', $md);
echo "D2 ✔\n";

// ═══ NAV-D3 · DEC-NAV-E — دمج قائمتي الموقع صفًّا صفًّا ═══
$r5 = $db->query("SELECT id, door, label_ar, route FROM nav_items WHERE role_id = 5 ORDER BY door, sort_order")->fetch_all(MYSQLI_ASSOC);
$r6routes = array();
$r = $db->query("SELECT route FROM nav_items WHERE role_id = 6");
while ($r && ($x = $r->fetch_assoc())) { $r6routes[strtolower($x['route'])] = true; }
$md = "# NAV-D3 · حسم DEC-NAV-E صفًّا صفًّا — دمج «إدارة الموقع» في «الحركة والتشغيل»\n\n"
    . "> **★القرار المستنتج (ينتظر توقيع المالك):** الباقي قائمة الدور 6 «الحركة والتشغيل» — لأن ORG-01 يجعل مدير الحركة هو مدير الموقع تشغيليًّا، وللدور 6 نوع تكليف `site_manager` في البنية الجديدة والقيد «واحد نشط لكل موقع». "
    . "بروتوكول SCR-01 §5: الأقوى تبقى (6: 20 رابطًا مقابل 5: 17) · وما ينقصها يُنقل · ومسار الملغاة يُحوَّل بعدّاد.\n\n"
    . "| # | رابط الدور 5 (إدارة الموقع) | الباب | موجود عند 6؟ | القرار ★ |\n|---|---|---|---|---|\n";
$i = 0;
$toCopy = 0;
foreach ($r5 as $x) {
    $i++;
    $in6 = isset($r6routes[strtolower($x['route'])]);
    if (!$in6) { $toCopy++; }
    $md .= "| {$i} | {$x['label_ar']} | {$x['door']} | " . ($in6 ? 'نعم' : '**لا**') . " | "
        . ($in6 ? 'يكتفى بنسخة 6' : '★يُنقل إلى 6 ثم توحيد القائمتين') . " |\n";
}
$md .= "\n**الخلاصة:** " . count($r5) . " رابطًا للدور 5 — منها {$toCopy} تُنقل للدور 6، ثم توحَّد قائمة الدور 5 على قائمة 6 (المستخدمون الستة على الدور 5 يرون القائمة الموحدة بلا مساس بحساباتهم) — التنفيذ في NAV-10 (الموجة ⑱).\n";
file_put_contents($OUT . '/NAV-D3_site_roles_merge_ar.md', $md);
echo "D3 ✔ (دور5={$i} · يُنقل {$toCopy})\n";
echo "اكتمل التوليد في docs/nav01/\n";
