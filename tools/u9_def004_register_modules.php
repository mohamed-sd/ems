<?php
/**
 * tools/u9_def004_register_modules.php — إغلاق DEF-004 (update0009): تسجيل وحدات
 * الصلاحيات الناقصة للشاشات القانونية الـ203
 * ═══════════════════════════════════════════════════════════════════════════
 * العيب: وجهاتٌ قانونيةٌ محميةٌ بحارسها وأرضيةِ الحوكمة لكن بلا وحدةٍ مسجَّلةٍ في
 * modules — فتُحرَم من صفوف can_view/can_edit المفصَّلة ومن الظهور في شاشة
 * الصلاحيات، ويحلّها check_page_permissions تقريبيًّا على موديولِ شبيهٍ (خطر).
 *
 * المنهج (تسجيلٌ إجرائيٌّ بلا قرار — الورقة 09):
 *   ① لكل وجهةٍ في nav09_file_map بلا موديولٍ مطابقٍ حرفيًّا (code = real_path):
 *      تُسجَّل باسمها القانونيِّ من وثيقة NAV-09 ومالكِها دورَ إدارتها الأول.
 *   ② المنح من القائمة المولَّدة لا اجتهادًا: كلُّ دورٍ له رابطٌ حيٌّ على الوجهة
 *      يُمنح can_view — ودورُ الإدارةِ المالكةِ يُمنح view/add/edit (لا delete —
 *      فالحذف يُمنح من شاشة الصلاحيات بقرار).
 *   ③ صفوف nav_items على الوجهة تُربط بالموديول الوليد (module_id + permission_code)
 *      كعرف proc_nav_module_fix ②.
 * idempotent · معاملة واحدة · dry-run افتراضيًّا.
 *
 * php tools/u9_def004_register_modules.php            # عرض فقط
 * php tools/u9_def004_register_modules.php --apply    # تنفيذ
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once __DIR__ . '/nav09_read.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$ROOT = dirname(__DIR__);

/* دورُ كل إدارةٍ الأول — مرآةُ nav09_verify.php + مرادفاتُ أسماءِ ورقة 06 */
$DEPT_PRIMARY_ROLE = array(
    'الإدارة التنفيذية' => 9, 'مكتب الرئيس التنفيذي والنواب' => 9,
    'إدارة الموقع' => 6, 'إدارة التشغيل' => 1,
    'إدارة الصيانة' => 13, 'النقل والترحيل' => 23,
    'المشتريات' => 16, 'إدارة المشتريات التشغيلية' => 16,
    'المخازن' => 25, 'إدارة المخازن' => 25,
    'المبيعات والعقود' => 12, 'إدارة الموردين' => 2,
    'القوى التشغيلية' => 27, 'إدارة الأسطول' => 3, 'الموارد البشرية' => 4,
    'المالية والخزينة' => 17, 'التمويل والملكية' => 26, 'مركز البلاغات' => 24,
    'الحوكمة والالتزام' => 15,
    'مساحة عملي' => null, // WORKSPACE — شخصيةٌ بلا مالكِ إدارة
);

/* ① العنوانُ والمالكُ الرسميان من ملف التتبع (update0009 · الورقة 06 «سجل الشاشات»)
   — لا من أول ظهورٍ في NAV-09، فالشاشةُ المشتركةُ (ميزانية إدارتي) يملكها من
   يملك مضمونَها لا من تظهر عنده أولًا. NAV-09 يبقى احتياطًا لما ليس في الورقة. */
require_once $ROOT . '/vendor/autoload.php';
$title = array(); $ownerDept = array();
$MM = $ROOT . '/docs/update0009/INJAZ-MASTER-MAP-2.xlsx';
if (is_file($MM)) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($MM);
    $reader->setReadDataOnly(true);
    $reader->setLoadSheetsOnly(array('06 — سجل الشاشات'));
    $wb = $reader->load($MM);
    $sh = $wb->getSheetByName('06 — سجل الشاشات');
    if ($sh) {
        foreach ($sh->toArray(null, true, false, false) as $i => $row) {
            $file = trim((string) ($row[2] ?? ''));
            if ($i === 0 || $file === '' || !preg_match('/\.php$/', $file)) { continue; }
            $ownerDept[$file] = trim((string) ($row[1] ?? ''));
            $title[$file] = trim((string) ($row[3] ?? ''));
        }
    }
}
$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');
foreach ($doc['depts'] as $dept) {
    foreach ($dept['rows'] as $row) {
        if ($row['kind'] !== 'screen') { continue; }
        if (!isset($title[$row['file']]) || $title[$row['file']] === '') {
            $title[$row['file']] = $row['title'];
            $ownerDept[$row['file']] = $dept['name'];
        }
    }
}

/* ◆ عدةُ وجهاتٍ قانونيةٍ قد تتشارك ملفًّا حيًّا واحدًا (deviations.php أربعُ شاشات)
   — فالوحدةُ تُسجَّل مرةً لكل real_path والأدوارُ تُدمج، وإلا تكرر الكود */
$missing = array(); // real_path => ['canonicals'=>[], 'title'=>, 'dept'=>]
$r = mysqli_query($conn,
    "SELECT fm.canonical_file, fm.real_path FROM nav09_file_map fm
      WHERE fm.real_path IS NOT NULL AND fm.state <> 'soon'
        AND NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = fm.real_path)
      ORDER BY fm.real_path, fm.canonical_file");
while ($x = mysqli_fetch_assoc($r)) {
    $p = $x['real_path'];
    if (!isset($missing[$p])) { $missing[$p] = array('canonicals' => array()); }
    $missing[$p]['canonicals'][] = $x['canonical_file'];
}
$o('══ DEF-004 — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' · وجهاتٌ قانونيةٌ بلا وحدة: '
   . array_sum(array_map(function ($m) { return count($m['canonicals']); }, $missing))
   . ' على ' . count($missing) . ' ملفًّا حيًّا ══');

$conn->begin_transaction();
$made = 0; $grants = 0; $linked = 0;
try {
    foreach ($missing as $path => $mrow) {
        $cfs = $mrow['canonicals'];
        $cf0 = $cfs[0]; // أولُ القانونيات بترتيبٍ حتميٍّ — عنوانُ الوحدةِ ومالكُها
        $name = isset($title[$cf0]) ? $title[$cf0] : $cf0;
        $dept = isset($ownerDept[$cf0]) ? $ownerDept[$cf0] : null;
        $owner = ($dept !== null && array_key_exists($dept, $DEPT_PRIMARY_ROLE)) ? $DEPT_PRIMARY_ROLE[$dept] : null;

        /* الأدوار التي تصلها القائمة المولَّدة إلى هذه الوجهة */
        $roles = array();
        $st = $conn->prepare("SELECT DISTINCT role_id FROM nav_items WHERE active = 1
                                AND (route = ? OR route LIKE CONCAT(?, '#%'))");
        $st->bind_param('ss', $path, $path);
        $st->execute();
        $rr = $st->get_result();
        while ($y = $rr->fetch_assoc()) { $roles[] = intval($y['role_id']); }
        $st->close();
        if ($owner !== null && !in_array($owner, $roles, true)) { $roles[] = $owner; }

        /* شاشةٌ شخصيةٌ بلا رابطِ إدارةٍ (ملفي · كلمة المرور): رؤيةٌ لكل الأدوار
           — عرفُ مساحةِ عملي (wfm_register_screens · my_tasks #246) */
        $allRoles = false;
        if (!$roles) {
            $allRoles = true;
            $rr = mysqli_query($conn, "SELECT id FROM roles");
            while ($y = mysqli_fetch_assoc($rr)) { $roles[] = intval($y['id']); }
        }

        $o(sprintf('+ %-46s «%s» قانونيات=%d مالك=%s أدوار=%s', $path, $name, count($cfs),
            $owner === null ? '—' : $owner,
            $allRoles ? 'الكل (شخصية)' : '[' . implode('،', $roles) . ']'));

        if (!$APPLY) { continue; }

        $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                              VALUES (?, ?, ?, NULL, 1, 0, 'fa fa-circle-dot', 100)");
        $st->bind_param('ssi', $name, $path, $owner);
        $st->execute() or throw new RuntimeException($st->error);
        $mid = intval($conn->insert_id);
        $st->close();
        $made++;

        foreach ($roles as $rid) {
            $cv = 1; $ca = ($rid === $owner) ? 1 : 0; $ce = ($rid === $owner) ? 1 : 0;
            $st = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                  SELECT ?, ?, ?, ?, ?, 0 FROM DUAL
                                  WHERE NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = ? AND rp.module_id = ?)");
            $st->bind_param('iiiiiii', $rid, $mid, $cv, $ca, $ce, $rid, $mid);
            $st->execute() or throw new RuntimeException($st->error);
            $grants += $conn->affected_rows > 0 ? 1 : 0;
            $st->close();
        }

        $st = $conn->prepare("UPDATE nav_items SET module_id = ?, permission_code = ?
                               WHERE active = 1 AND (route = ? OR route LIKE CONCAT(?, '#%'))
                                 AND (module_id IS NULL OR module_id = 0)");
        $st->bind_param('isss', $mid, $path, $path, $path);
        $st->execute();
        $linked += $conn->affected_rows;
        $st->close();
    }

    if ($APPLY) {
        $conn->commit();
        $o("✔ COMMITTED — موديولات: $made · منح: $grants · صفوف قائمة رُبطت: $linked");
    } else {
        $conn->rollback();
        $o('— dry-run: لا تغيير');
    }
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}

/* الشاهد النهائي (دليل الإغلاق في الورقة 09): كل الوجهات القانونية موحَّدة */
$r = mysqli_query($conn,
    "SELECT COUNT(*) c FROM nav09_file_map fm
      WHERE fm.real_path IS NOT NULL AND fm.state <> 'soon'
        AND NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = fm.real_path)");
$left = intval(mysqli_fetch_assoc($r)['c']);
$r = mysqli_query($conn, "SELECT COUNT(*) c FROM nav09_file_map WHERE real_path IS NOT NULL AND state <> 'soon'");
$tot = intval(mysqli_fetch_assoc($r)['c']);
$o('الشاهد: وجهاتٌ بوحدةٍ مسجَّلة ' . ($tot - $left) . "/$tot" . ($left ? " — متبقٍّ: $left" : ' — DEF-004 مغلق'));
exit($left === 0 ? 0 : 1);
