<?php
/**
 * tools/perm01_cycle_register.php — تسجيلُ شاشاتِ جولةِ PERM-01 في دفترِ الدورة
 * ═══════════════════════════════════════════════════════════════════════════
 * **سجلّان لسؤالٍ واحد** ([[two-registers-target-vs-built]]): كتابةُ الصفِّ في
 * `repair01_screen_registry` **لم تُحرّك `RP-01` ولا `RP-02` حرفًا** — لأنَّ
 * `repair01_debt_measure()` يقرأ **`gov_screen_cycle`** (‏`screen_file` ·
 * `dept_name`) لا سجلَّ الشاشات. فالسطحُ المُصيَّرُ يحتاج تسجيلَه في كليهما.
 *
 * ◆ **والقياسُ سمّى السبعةَ ولم يظنَّها**: خمسٌ لها موضعٌ نشطٌ في
 *   `nav_workspace_placements`، وسادسةٌ (`Finance/acc_approval_record.php`)
 *   وسابعةٌ (`Settings/links_control.php`) بُنيتا بلا موضعٍ مُعلَن.
 *
 * ⛔ **ولا تُؤلَّف مرحلةٌ ولا مُخرَجٌ من فراغ**: القيمُ **منقولةٌ من صفِّ الشاشةِ
 *   النظيرةِ في الإدارةِ نفسِها** (`break_glass.php` لفتحِ الطوارئ ·
 *   `perm_matrix.php` لشاشاتِ الصلاحيات)، وما لا نظيرَ له يُوصَف **بفعلِ
 *   الشاشةِ المقيسِ من شيفرتِها** لا بعبارةٍ عامّة.
 *
 * التشغيل: php tools/perm01_cycle_register.php [--apply]
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

$ROWS = array(
    'Governance/auth_profile_edit.php' => array(
        'scr' => 'SCR-0951', 'dept' => 'إدارة الصلاحيات', 'layer' => 'دورة الإدارة',
        'ord' => '4', 'stage' => 'الأدوار والصلاحيات', 'group' => 'إدارة الصلاحيات والأدوار',
        'title' => 'بناء القوالب (auth_profile_edit.php)',
        'in' => 'قالب الدور وبنوده', 'out' => 'قالب سلطة محدث',
        'role' => 'إدارة الصلاحيات', 'next' => 'القالب ينفذ على حامليه بعد الاعتماد',
        'cons' => 'كل الإدارات', 'fin' => 'لا',
    ),
    'Governance/perm_audit_log.php' => array(
        'scr' => 'SCR-0952', 'dept' => 'إدارة الصلاحيات', 'layer' => 'دورة الإدارة',
        'ord' => '6', 'stage' => 'الالتزام والامتثال', 'group' => 'إدارة الصلاحيات والأدوار',
        'title' => 'محاسبة الصلاحيات (perm_audit_log.php)',
        'in' => 'gov_authority_grants', 'out' => 'سجل محاسبة مقروء',
        'role' => 'إدارة الصلاحيات', 'next' => 'قراءة فقط — لا تكتب منحا ولا تعدله',
        'cons' => 'الحوكمة · المراجعة الداخلية', 'fin' => 'لا',
    ),
    'Governance/break_glass_open.php' => array(
        'scr' => 'SCR-0953', 'dept' => 'الحوكمة والالتزام', 'layer' => 'دورة الإدارة',
        'ord' => '6', 'stage' => 'الالتزام والامتثال', 'group' => 'كسر الزجاج والطوارئ',
        'title' => 'فتح الطوارئ (break_glass_open.php)',
        'in' => 'scr_break_glass', 'out' => 'استثناء نافذ بأثر مسجل',
        'role' => 'سلّم الموافقات', 'next' => 'الصلاحية تمنح دقائق معدودة بموافقتين عليا',
        'cons' => 'الحوكمة · الإدارة المعنية', 'fin' => 'لا',
    ),
    'Governance/bus_monitor.php' => array(
        'scr' => 'SCR-BM01', 'dept' => 'الحوكمة والالتزام', 'layer' => 'المرجع والإدارة',
        'ord' => '6', 'stage' => 'الحوكمة والضوابط', 'group' => 'ناقل الأحداث',
        'title' => 'مراقبة ناقل الأحداث (bus_monitor.php)',
        'in' => 'ems_event_deliveries · event_consumers', 'out' => 'مؤشر تأخر ورسائل ميتة',
        'role' => 'الحوكمة والالتزام', 'next' => 'قراءة حية — لا تنشر حدثا ولا تعيد تسليمه',
        'cons' => 'الحوكمة · المالية', 'fin' => 'لا',
    ),
    'Settings/db_backup.php' => array(
        'scr' => 'SCR-BK01', 'dept' => 'الحوكمة والالتزام', 'layer' => 'المرجع والإدارة',
        'ord' => '6', 'stage' => 'الحوكمة والضوابط', 'group' => 'النسخ والاستعادة',
        'title' => 'النسخ الاحتياطي لقاعدة البيانات (db_backup.php)',
        'in' => 'ملفات النسخ على القرص', 'out' => 'نسخة محفوظة وجدولة معلنة',
        'role' => 'الحوكمة والالتزام', 'next' => 'الاستعادة والاستيراد خارج هذه الشاشة عن قصد',
        'cons' => 'الحوكمة', 'fin' => 'لا',
    ),
    'Settings/links_control.php' => array(
        'scr' => '', 'dept' => 'إدارة الصلاحيات', 'layer' => 'دورة الإدارة',
        'ord' => '4', 'stage' => 'الأدوار والصلاحيات', 'group' => 'إدارة الصلاحيات والأدوار',
        'title' => 'ضبط ظهور روابط الدور (links_control.php)',
        'in' => 'روابط الدور ومجموعاتها', 'out' => 'ظهور سايدبار محدث للدور',
        'role' => 'إدارة الصلاحيات', 'next' => 'ضبط ظهور لا منح صلاحية — الحارس لا يقرأ منه',
        'cons' => 'كل الإدارات', 'fin' => 'لا',
    ),
    'Finance/acc_approval_record.php' => array(
        'scr' => '', 'dept' => 'المالية والمحاسبة', 'layer' => 'دورة الإدارة',
        'ord' => '4', 'stage' => 'الاعتماد والترحيل', 'group' => 'القيود والاعتماد',
        'title' => 'محضر اعتماد قيد (acc_approval_record.php)',
        'in' => 'القيد ومستنده', 'out' => 'محضر اعتماد مسجل',
        'role' => 'المالية والمحاسبة', 'next' => 'الاعتماد شرط الترحيل إلى دفتر القيد',
        'cons' => 'المالية · المراجعة الداخلية', 'fin' => 'نعم',
    ),
);

$made = 0; $skip = 0; $miss = array();
foreach ($ROWS as $file => $R) {
    if (!is_file($ROOT . '/' . $file)) { $miss[] = "$file — لا ملفَّ على القرص"; continue; }
    $bn = basename($file);
    $q  = $conn->query("SELECT id FROM gov_screen_cycle WHERE screen_file = '{$e($file)}'
                          OR screen_file LIKE '%/{$e($bn)}' OR screen_file = '{$e($bn)}'");
    if ($q && $q->num_rows) { printf("  ⟳ مسجَّلٌ سلفًا: %s\n", $file); $skip++; continue; }
    printf("  + %-38s %s · %s\n", mb_substr($file, 0, 37), $R['dept'], $R['stage']);
    if (!$APPLY) { continue; }
    $sql = "INSERT INTO gov_screen_cycle
        (company_id, dept_name, layer_name, stage_order, stage_name, group_name,
         screen_title, screen_file, inputs_note, output_doc, resp_role, next_state,
         consumers, fin_impact, stage_kind, screen_id, bridge_rule)
        VALUES (0, '{$e($R['dept'])}', '{$e($R['layer'])}', '{$e($R['ord'])}',
         '{$e($R['stage'])}', '{$e($R['group'])}', '{$e($R['title'])}', '{$e($file)}',
         '{$e($R['in'])}', '{$e($R['out'])}', '{$e($R['role'])}', '{$e($R['next'])}',
         '{$e($R['cons'])}', '{$e($R['fin'])}', 'canonical', '{$e($R['scr'])}',
         '" . ($R['scr'] !== '' ? 'BASENAME_UNIQUE' : '') . "')";
    if (!$conn->query($sql)) { exit("⛔ إدراج {$file}: {$conn->error}\n"); }
    $made++;
}
foreach ($miss as $m) { echo "  ✘ $m\n"; }
printf("\nجديدٌ=%d · قائمٌ سلفًا=%d · متعذِّرٌ=%d\n", $made, $skip, count($miss));
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* ═══ التحقّقُ بإعادةِ القراءة ═══ */
$ok = 0;
foreach (array_keys($ROWS) as $file) {
    $bn = basename($file);
    $q = $conn->query("SELECT dept_name FROM gov_screen_cycle
                        WHERE screen_file = '{$e($file)}' OR screen_file LIKE '%/{$e($bn)}'");
    $r = $q ? $q->fetch_row() : null;
    if ($r && trim((string) $r[0]) !== '') { $ok++; }
}
printf("✔ أُعيدت القراءةُ: %d من %d ملفًّا له صفٌّ بإدارةٍ مالكة\n", $ok, count($ROWS));
exit($ok === count($ROWS) ? 0 : 1);
