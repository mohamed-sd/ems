<?php
// بدء output buffering من البداية لمنع أي output غير متوقع
ob_start();

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

include '../config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'معرف المشغل مفقود'], JSON_UNESCAPED_UNICODE));
}

$employee_id = intval($_GET['id']);

// التحقق من صلاحيات الشركة وعزل البيانات
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// العزل عبر البوابة — يستبدل فحص العمود وسُلَّم created_by الاحتياطي
$emp_data_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('employee data super view') : ems_tenant_db();
try {
    $driver = $emp_data_gate->selectOne('employees', array('where' => array('id' => $employee_id)));
} catch (\Throwable $t) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE));
}

if ($driver !== null) {

    /* ══ INJ-0004 (P0) — «الحقلُ الحساسُ يُحجب في الخادمِ لا في العرض» ═══════
       كانت هذه النقطةُ ترسل **الصفَّ كاملًا** — بما فيه `monthly_salary` ورقمُ
       الهوية — لأيِّ جلسةٍ مصادَقةٍ بلا فحصِ صلاحيةٍ إطلاقًا. وحارسُ الرؤيةِ
       كان مطبَّقًا على بطاقةِ الموظفِ وحدَها، والشاشةُ الأمُّ ونقطتُها تكشفان
       الحقلَ نفسَه. ◆ والحجبُ هنا **حذفٌ من الاستجابة** لا إخفاءٌ في العرض
       (CS-10) — فالمفتاحُ يغيب عن JSON أصلًا.
       ◆ والفشلُ مغلق: أيُّ حكمٍ غيرِ `allow` (أو غيابُ الحارس) يحجب. */
    require_once __DIR__ . '/../app/Services/Portal/VisibilityGuard.php';
    require_once __DIR__ . '/../includes/sensitive_read_log.php';

    /**
     * حقولُ الأجورِ والهويةِ والصحةِ الحساسةُ في صفِّ الموظف.
     * ◆ گوتشا التقطها الفحصُ الحيُّ: أُدرجت أولًا أسماءٌ **مفترَضة**
     *   (`national_id` · `id_number` · `passport_no`) لا وجودَ لها في الجدول،
     *   والعمودُ الحقيقيُّ اسمُه `identity_number` — فبقيت الهويةُ تُرسَل كاملةً
     *   والحجبُ يبدو ناجحًا. **الأسماءُ تُقرأ من الجدولِ لا تُخمَّن.**
     */
    $ged_sensitive = array(
        // الأجور
        'monthly_salary', 'salary_type',
        // الهوية
        'identity_number', 'identity_type', 'identity_expiry_date', 'identity_photo',
        // الصحة (M-14: بياناتٌ شخصيةٌ لا تُعرض إلا بمنح)
        'health_status', 'health_issues', 'medical_report_path', 'medical_fitness_status',
        'birth_date',
        // أسماءٌ محتملةٌ في تحويراتٍ أخرى للجدول — تُحذف إن وُجدت ولا تضرّ إن غابت
        'salary', 'basic_salary', 'daily_wage', 'bank_account', 'iban',
    );

    $ged_allowed = false;
    $ged_reason  = 'حارسُ الرؤيةِ غيرُ متاح — حجبٌ افتراضيّ (فشلٌ مغلق)';
    try {
        if (class_exists('\\App\\Services\\Portal\\VisibilityGuard')) {
            $ged_viewer = array(
                'account_id'    => intval($_SESSION['user']['id'] ?? 0),
                'role'          => $current_role,
                'capacity_type' => 'employee',
                'scope_type'    => '', 'scope_id' => null,
            );
            $ged_subject = array('account_id' => $employee_id);
            $ged_v = \App\Services\Portal\VisibilityGuard::check(
                $conn, $emp_data_gate, $company_id, $ged_viewer, 'card.payroll', $ged_subject);
            $ged_allowed = (($ged_v['decision'] ?? '') === 'allow');
            $ged_reason  = (string) ($ged_v['reason'] ?? '');
        }
    } catch (\Throwable $t) {
        // CS-12: لا يُبتلع — يُسجَّل ويبقى الحجبُ قائمًا.
        error_log('get_employee_data VisibilityGuard: ' . $t->getMessage());
    }

    if ($ged_allowed) {
        // M-14 BR-GOV-07: القراءةُ على السرِّ فعلٌ يُسجَّل — **بعد** السماحِ لا قبلَه.
        ems_log_sensitive_read($conn, 'salary', 'employee:' . $employee_id, 'Employees/get_employee_data.php');
    } else {
        foreach ($ged_sensitive as $ged_f) { unset($driver[$ged_f]); }
    }

    // تنظيف output buffer وطباعة JSON فقط
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => true,
        'driver' => $driver,
        // ◆ الحجبُ يُعلَن ولا يُضمَر — فالمستهلكُ يعرف أن حقلًا نُزع لا أنه فارغ.
        'redacted' => $ged_allowed ? array() : $ged_sensitive,
        'redaction_reason' => $ged_allowed ? '' : $ged_reason,
    ], JSON_UNESCAPED_UNICODE));
} else {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'المشغل غير موجود أو ليس لديك صلاحية الوصول إليه'
    ], JSON_UNESCAPED_UNICODE));
}
