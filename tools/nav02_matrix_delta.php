<?php
/**
 * C-01 · مصفوفةُ المالك ↔ الحي — تقريرُ الدلتا (update0006)
 * ──────────────────────────────────────────────────────────
 * يقرأ docs/sources/NAV-02_matrix.xlsx (255 صفًّا × 18 عمودًا) ويطابق كلَّ
 * صفٍّ بمساره في nav_items الحية، ويكتب docs/nav02/matrix_delta.csv بحالة:
 *   applied        — الإجراءُ المطلوبُ واقعٌ فعلًا في الحي
 *   pending        — يلزم تطبيقُه (مع تفصيل النوع)
 *   not_in_nav     — الصفُّ ليس في التنقل أصلًا (بطاقاتٌ ومعالجات)
 *   strange_unresolved — غريبٌ لم يُحسم (يقرؤه الفحص ⑪)
 * التشغيل: php tools/nav02_matrix_delta.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean(); // گوتشا: config.php يبتلع مخرجَ CLI
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$root = dirname(__DIR__);

/** الإدارةُ المالكة (نصُّ المصفوفة) ← أدوارُها في النظام — من خريطة الأدوار الـ25 */
function dept_roles($dept)
{
    $map = array(
        'التشغيل'            => array(1),
        'المبيعات'           => array(2, 12), 'المبيعات والعقود' => array(2, 12),
        'المالية'            => array(3, 17, 18, 19, 20, 21, 22),
        'المشغّلون'          => array(4), 'القوى العاملة' => array(4),
        'الحركة والتشغيل'    => array(5, 6), 'الموقع' => array(5, 6),
        'الصيانة'            => array(7),
        'الأسطول'            => array(8),
        'الموارد البشرية'    => array(10, 14),
        'الموردون'           => array(11),
        'النقل'              => array(13, 23), 'النقل والترحيل' => array(13, 23),
        'الحوكمة'            => array(15), 'الصلاحيات' => array(15),
        'المشتريات'          => array(16, 25), 'المشتريات والمخازن' => array(16, 25), 'المخازن' => array(16, 25),
        'البلاغات'           => array(24), 'مركز البلاغات' => array(24),
        'التمويل'            => array(26),
    );
    foreach ($map as $k => $v) if (mb_strpos($dept, $k) !== false) return $v;
    return array();
}

/* ── قراءةُ xlsx بلا مكتبة — ZipArchive + DOM ───────────────────────────── */
$z = new ZipArchive();
if ($z->open($root . '/docs/sources/NAV-02_matrix.xlsx') !== true) { fwrite(STDERR, "لا مصفوفة\n"); exit(1); }
$ss = array();
$ssx = $z->getFromName('xl/sharedStrings.xml');       // قد يغيب — النصوصُ inline عندئذٍ
if ($ssx) {
    $d = new DOMDocument(); $d->loadXML($ssx);
    foreach ($d->getElementsByTagName('si') as $si) { $t=''; foreach ($si->getElementsByTagName('t') as $n) $t .= $n->textContent; $ss[] = $t; }
}
$d2 = new DOMDocument(); $d2->loadXML($z->getFromName('xl/worksheets/sheet1.xml'));
$rows = array();
foreach ($d2->getElementsByTagName('row') as $ri => $row) {
    $cells = array();
    foreach ($row->getElementsByTagName('c') as $c) {
        // العمودُ من مرجع الخلية (A..R) حتى لا تنزاح الفراغات
        $ref = $c->getAttribute('r'); preg_match('/^([A-Z]+)/', $ref, $mm);
        $col = 0; foreach (str_split($mm[1]) as $ch) $col = $col*26 + (ord($ch)-64);
        $t = $c->getAttribute('t');
        if ($t === 'inlineStr') {
            $val = ''; foreach ($c->getElementsByTagName('t') as $n) $val .= $n->textContent;
        } else {
            $v = $c->getElementsByTagName('v')->item(0); $val = $v ? $v->textContent : '';
            if ($t === 's') $val = $ss[(int)$val] ?? '';
        }
        $cells[$col-1] = $val;
    }
    $rows[] = $cells;
}
$header = $rows[0]; $data = array_slice($rows, 1);
// الأعمدة: 0#=رقم 1=الاسم الحالي 2=الاسم الجديد 3=الإدارة المالكة 4=الملف 5=المسار
//          6=الإدارات الحالية 7=غريب؟ 8=المجموعة الجديدة 9=طريقة الظهور 10=الشاشة الأم
//          11=الإجراء 12=المسار البديل 13=الصلاحية 14=اختبار 15=التصنيف 16=في السايدبار؟ 17=عدد الإدارات

/* ── الحيّ: المساراتُ النشطة والمحوَّلة ──────────────────────────────────── */
$live = array();   // route → [roles]
$r = mysqli_query($conn, "SELECT route, GROUP_CONCAT(DISTINCT role_id) roles FROM nav_items WHERE active=1 GROUP BY route");
while ($x = mysqli_fetch_assoc($r)) $live[strtolower(trim($x['route']))] = $x['roles'];
$redirected = array(); $redirTargets = array();
$r = mysqli_query($conn, "SELECT old_route, new_route FROM nav_redirects WHERE active=1");
while ($x = mysqli_fetch_assoc($r)) {
    $redirected[strtolower(trim($x['old_route']))] = 1;
    $redirTargets[strtolower(trim($x['new_route']))] = 1;
}
// روابطُ مساحة عملي الحيةُ في g1 وحدَها = منقولةٌ صحيحًا
$g1only = array();
$r = mysqli_query($conn, "SELECT n.route, SUM(lg.group_code='g1') g1c, COUNT(*) tot
                          FROM nav_items n JOIN link_groups lg ON lg.id=n.group_id
                          WHERE n.active=1 GROUP BY n.route");
while ($x = mysqli_fetch_assoc($r)) if ((int)$x['g1c'] === (int)$x['tot']) $g1only[strtolower(trim($x['route']))] = 1;

/* ── المطابقة ────────────────────────────────────────────────────────────── */
@mkdir($root . '/docs/nav02', 0777, true);
$fh = fopen($root . '/docs/nav02/matrix_delta.csv', 'w');
fwrite($fh, "\xEF\xBB\xBF"); // BOM للعربية في Excel
fputcsv($fh, array('المسار','الاسم','الإجراءُ المطلوب','الحالة','تفصيل'));
$stats = array();
foreach ($data as $row) {
    $route  = strtolower(trim($row[5] ?? ''));
    $name   = trim($row[1] ?? '');
    $action = trim($row[11] ?? '');
    $inNav  = trim($row[16] ?? '');
    $status = 'pending'; $note = '';

    $liveHit = isset($live[$route]);
    $redirHit = isset($redirected[$route]);

    if ($route === '' || $inNav !== 'نعم') {
        // خارج التنقل أصلًا — بطاقاتٌ ومعالجاتٌ وتقارير تُفتح من سجلاتها
        $status = 'not_in_nav';
        $note = $action;
    } elseif (mb_strpos($action, 'إبقاء') !== false) {
        // إبقاءٌ مع تجميع — التجميعُ واقعٌ (كلُّ الروابط في مجموعات)
        $status = $liveHit ? 'applied' : ($redirHit ? 'applied' : 'pending');
        $note = $liveHit ? 'حيٌّ في مجموعته' : ($redirHit ? 'مسارٌ محوَّل' : 'غائبٌ عن الحي');
    } elseif (mb_strpos($action, 'يُصنَّف') !== false) {
        // صفوفُ القرار — قرارُنا المسجَّل: تقريرٌ إن كان تصنيفُه تقريرًا، وإلا يبقى خارج القائمة (بطاقة)
        $cls = trim($row[15] ?? '');
        if (mb_strpos($cls, 'تقرير') !== false) { $status = 'pending'; $note = 'classify:report'; }
        else { $status = 'applied'; $note = 'بطاقةٌ تُفتح من سجلها — خارج القائمة أصلًا'; }
    } elseif (mb_strpos($action, 'مركز التقارير') !== false) {
        $status = (!$liveHit || $redirHit) ? 'applied' : 'pending';
        $note = 'move:reports';
    } elseif (mb_strpos($action, 'مساحة عملي') !== false) {
        // منقولٌ صحيحًا إن غاب أو حُوّل أو بقي حيًّا في g1 (باب مساحة عملي) وحدَها
        $status = (!$liveHit || $redirHit || isset($g1only[$route])) ? 'applied' : 'pending';
        $note = 'move:workspace';
    } elseif (mb_strpos($action, 'دمج') !== false) {
        // الناجي من الدمج (وجهةُ تحويلٍ) يبقى حيًّا — ولا يُعدّ معلَّقًا
        $status = (!$liveHit || $redirHit || isset($redirTargets[$route])) ? 'applied' : 'pending';
        $note = 'merge:' . (mb_strpos($action,'العقد')!==false ? 'contract' : (mb_strpos($action,'المورد')!==false ? 'supplier' : 'dup_name'));
    } elseif (mb_strpos($action, 'إلغاء') !== false) {
        // Settings/settings.php ناجٍ بقرار: هو مدخلُ g8 نفسُه لا وسيطةٌ فوقه (اجتهاد J-06)
        $status = (!$liveHit || $redirHit || isset($redirTargets[$route])
                   || $route === 'settings/settings.php') ? 'applied' : 'pending';
        $note = 'drop:intermediate';
    } elseif (mb_strpos($action, 'إنشاء') !== false) {
        // العنصرُ الإلزاميُّ منشأٌ إن صار مسارُه حيًّا — والاسمُ الحاليُّ «غير موجود» لا يُعتمد عليه
        $status = $liveHit ? 'applied' : 'pending';
        $note = 'create:' . trim($row[2] ?? $name);
    } elseif (mb_strpos($action, 'خارج القائمة') !== false) {
        $status = $liveHit ? 'pending' : 'applied';
        $note = 'keep_out';
    } else { $note = $action; }

    // الغريبُ غيرُ المحسوم (للفحص ⑪): يعيش في قائمةِ دورٍ **ليس من إدارة مالكه**
    // — فالوجودُ في قائمة المالك بعد إعادة التوزيع ليس غرابةً (update0004 وزّعت بالملكية)
    if (trim($row[7] ?? '') === 'نعم ⚠' && $liveHit && $status === 'applied'
        && mb_strpos($action, 'إبقاء') !== false) {
        $ownerDept  = trim($row[3] ?? '');
        $ownerRoles = dept_roles($ownerDept);
        $liveRoles  = array_map('intval', explode(',', $live[$route]));
        $foreign    = $ownerRoles ? array_diff($liveRoles, $ownerRoles) : array();
        if ($ownerRoles && $foreign) {
            $status = 'strange_unresolved';
            $note = 'حيٌّ في أدوارٍ غيرِ مالكه: ' . implode('،', $foreign);
        } else {
            $note = 'في قوائم مالكه وحدَها بعد إعادة التوزيع';
        }
    }
    $stats[$status] = ($stats[$status] ?? 0) + 1;
    fputcsv($fh, array($row[5] ?? '', $name, $action, $status, $note));
}
fclose($fh);
echo "════ دلتا مصفوفة NAV-02 ════\n";
foreach ($stats as $k => $v) echo "  $k: $v\n";
echo "كُتب docs/nav02/matrix_delta.csv (" . count($data) . " صفًّا)\n";
