<?php
/**
 * tools/perm01_classify_new_screens.php — تصنيفُ مساحاتِ شاشاتِ الجولة (NF-24)
 * ═══════════════════════════════════════════════════════════════════════════
 * **السجلُّ الرابعُ لسؤالٍ واحد**: `tests/injfix02_space_classification_ratchet.php`
 * يقيس **مسارًا نشطًا في `nav_items` بلا صفٍّ في `gov_space_appearances`** —
 * وهو غيرُ السجلّاتِ الثلاثةِ التي مرَّت (سجلُّ الشاشات · دفترُ الدورة ·
 * مصفوفةُ التنقّل). ⇒ **السطحُ المُصيَّرُ يحتاج أربعةَ سجلّاتٍ لا ثلاثة.**
 *
 * ◆ **و«الانفتاحُ الافتراضيُّ» هو الخطر**: مسارٌ خارجَ سجلِّ التصنيفِ **مفتوحٌ
 *   افتراضًا** — فغيابُ الصفِّ ليس نقصَ توثيقٍ بل بابٌ بلا حكم.
 *
 * ⛔ **والقيمُ منقولةٌ من الصفِّ النظيرِ في المساحةِ نفسِها** (‏`perm_matrix.php`
 *   لشاشاتِ الصلاحيات) ومن `nav_workspace_placements` الحاكمِ للاسمِ والإدارة.
 *
 * التشغيل: php tools/perm01_classify_new_screens.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("⛔ اتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$NOTE = 'PERM-01 · تصنيفٌ لحق بناءً سابقًا — المساحةُ والمالكُ من nav_workspace_placements';
$ROWS = array(
    'Governance/auth_profile_edit.php' => array('إدارة الصلاحيات', 'CONTROL', 'بناء القوالب',
        'إدارة الصلاحيات', 'PLATFORM_SHARED', 'الشاشةُ سطحُ إدارةِ الصلاحياتِ ومالكُها الدورُ 15'),
    'Governance/perm_audit_log.php' => array('إدارة الصلاحيات', 'CONTROL', 'محاسبة الصلاحيات',
        'إدارة الصلاحيات', 'PLATFORM_SHARED', 'سجلُّ محاسبةٍ قراءةً فقط — مالكُه الدورُ 15'),
    'Governance/break_glass_open.php' => array('الحوكمة والالتزام', 'CONTROL', 'فتح الطوارئ',
        'الحوكمة والالتزام', 'PLATFORM_SHARED', 'استثناءٌ نافذٌ بموافقتَين — مالكُه سلّمُ الموافقات'),
    'Governance/bus_monitor.php' => array('الحوكمة والالتزام', 'CONTROL', 'مراقبة ناقل الأحداث',
        'الحوكمة والالتزام', 'PLATFORM_SHARED', 'قراءةٌ حيّةٌ للناقلِ لا تنشر حدثًا — مالكُها الحوكمة'),
    'Settings/db_backup.php' => array('الحوكمة والالتزام', 'CONTROL', 'النسخ الاحتياطي لقاعدة البيانات',
        'الحوكمة والالتزام', 'PLATFORM_SHARED', 'سطحُ بنيةٍ تحتيّةٍ — والاستعادةُ خارجَه عن قصد'),
    'Settings/links_control.php' => array('إدارة الصلاحيات', 'CONTROL', 'ضبط ظهور روابط الدور',
        'إدارة الصلاحيات', 'PLATFORM_SHARED', 'ضبطُ ظهورٍ لا منحُ صلاحية — الحارسُ لا يقرأ منه'),
    'Finance/acc_approval_record.php' => array('المالية والمحاسبة', 'DEPARTMENT', 'محضر اعتماد قيد',
        'المالية والمحاسبة', 'BUSINESS_DEPARTMENT', 'الاعتمادُ شرطُ الترحيلِ إلى دفترِ القيد'),
);

$made = 0; $skip = 0; $miss = array();
foreach ($ROWS as $route => $R) {
    if (!is_file($ROOT . '/' . $route)) { $miss[] = "$route — لا ملفَّ على القرص"; continue; }
    $bn = basename($route);
    $q = $conn->query("SELECT id FROM gov_space_appearances
                        WHERE route = '{$e($route)}' OR route LIKE '%/{$e($bn)}'");
    if ($q && $q->num_rows) { printf("  ⟳ مصنَّفٌ سلفًا: %s\n", $route); $skip++; continue; }
    list($space, $kind, $screen, $dept, $own, $basis) = $R;
    printf("  + %-38s %s · %s\n", mb_substr($route, 0, 37), $space, $kind);
    if (!$APPLY) { continue; }
    /* ◆ `id` عمودٌ إلزاميٌّ **بلا ترقيمٍ تلقائيّ** — فيُشتقُّ من أقصى قائم */
    $q  = $conn->query('SELECT COALESCE(MAX(id),0)+1 FROM gov_space_appearances');
    $nid = (int) $q->fetch_row()[0];
    $sql = "INSERT INTO gov_space_appearances
        (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
         src_class, src_ownership, src_decision, src_note, spaces_count,
         cls, ownership, decision, basis, rule_step, view_fields, updated_at)
        VALUES ({$nid}, '{$e($space)}', '{$e($kind)}', '', '{$e($screen)}', '{$e($route)}',
         '{$e($dept)}', '{$e($own)}', 'PERM-01', 'VALID', 'CONFIRMED', '{$e($NOTE)}', 1,
         'OWNED', 'VALID', 'CONFIRMED', '{$e($basis)}', 1, '', NOW())";
    if (!$conn->query($sql)) { exit("⛔ إدراج {$route}: {$conn->error}\n"); }
    $made++;
}
foreach ($miss as $m) { echo "  ✘ $m\n"; }
printf("\nجديدٌ=%d · مصنَّفٌ سلفًا=%d · متعذِّرٌ=%d\n", $made, $skip, count($miss));
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* ═══ التحقّقُ بإعادةِ القراءة ═══ */
$ok = 0;
foreach (array_keys($ROWS) as $route) {
    $bn = basename($route);
    $q = $conn->query("SELECT id FROM gov_space_appearances
                        WHERE route = '{$e($route)}' OR route LIKE '%/{$e($bn)}'");
    if ($q && $q->num_rows) { $ok++; }
}
printf("✔ أُعيدت القراءةُ: %d من %d مسارًا مصنَّف\n", $ok, count($ROWS));
exit($ok === count($ROWS) ? 0 : 1);
