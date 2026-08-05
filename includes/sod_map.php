<?php
/**
 * includes/sod_map.php — خريطةُ فصل الواجبات: من الرمز المعنوي إلى الواقع الحي
 * ───────────────────────────────────────────────────────────────────────────
 * مصفوفةُ `sod_conflicts` تتكلم بأربعةٍ وعشرين رمزًا معنويًّا (`supplier.bank.update`)
 * لا يعرفها النظامُ الحي — الذي يتكلم بـ(شاشة × راية). وهذه الخريطةُ تترجم،
 * وهي **المصدرُ الواحد** الذي يقرؤه الفاحصُ والحارسُ فلا يتفرّق الحكم.
 *
 * درجةُ الدقة لكل رمز — تُعلَن ولا تُخفى (E-04 · قاعدة عدم التلفيق):
 *   exact  — للرمز شاشةٌ ورايةٌ تخصّه وحدَه.
 *   approx — الرايةُ أوسعُ من الرمز (الاعتمادُ يقع على راية «تعديل» مثلًا،
 *            أو رمزان يتقاسمان شاشةً واحدة) — **يُبلَّغ ولا يُمنع** حتى تدقّ
 *            الأبعادُ الأربعة والأفعالُ الستة عشر (E-04 SEC-013 · DEC-SEC-K).
 *   absent — لا شاشةَ له في سجل الشاشات — لا يُقاس ولا يُدَّعى قياسُه.
 *
 * قرار المالك 2026-08-06: «تُعلَّم تقريبية وتُبلَّغ» · و«مسحٌ يكشف ثم منع».
 */

if (!function_exists('ems_sod_map')) {
    /** @return array<string,array{screen:?string,action:?string,grade:string,note:string}> */
    function ems_sod_map()
    {
        $m = function ($screen, $action, $grade, $note = '') {
            return array('screen' => $screen, 'action' => $action, 'grade' => $grade, 'note' => $note);
        };
        return array(
            // ① دورة المورد
            'supplier.create'          => $m('Suppliers/suppliers.php',                'create', 'exact'),
            'supplier.bank.update'     => $m('Suppliers/supplier_documents.php',       'update', 'exact',
                                             'شاشةٌ مخصصةٌ لوثائق المورد وحسابه البنكي'),
            'supplier.payment.approve' => $m('Finance/payments_fin.php',               'update', 'approx',
                                             'الاعتمادُ يقع على راية «تعديل» في المدفوعات والخزينة'),
            // ② دورة الشراء
            'proc.request'             => $m('Procurement/requests_proc.php',          'create', 'exact'),
            'proc.award'               => $m('Procurement/orders_proc.php',            'create', 'approx',
                                             'الترسيةُ = إنشاءُ أمر شراء — والشاشةُ تحمل غيرَه'),
            'proc.receive'             => $m('Procurement/receipt_custody_proc.php',   'create', 'exact'),
            'proc.disburse'            => $m('Procurement/issue_proc.php',             'create', 'exact'),
            // ③ الساعات والمستخلص
            'timesheet.entry'          => $m('Timesheet/timesheet_type.php',           'create', 'exact'),
            'timesheet.approve'        => $m(null, null, 'absent',
                                             'Approvals/hours_approval.php غيرُ مسجَّلةٍ في سجل الشاشات'),
            'claim.create'             => $m('Contracts/claims.php',                   'create', 'exact'),
            // ④ دورة المسيّر
            'employee.create'          => $m('Employees/employees.php',                'create', 'exact'),
            'payroll.salary.update'    => $m('Employees/employee_contracts_details.php','update', 'approx',
                                             'الأجرُ في ملف عقد الموظف — والرايةُ تشمل بقيةَ بنوده'),
            'payroll.run'              => $m('Workforce/payroll_runs.php',             'create', 'exact'),
            // ⑤ إخفاء التحصيل
            'invoice.create'           => $m('Contracts/tax_invoices.php',             'create', 'exact'),
            'receipt.create'           => $m('Contracts/collections.php',              'create', 'approx',
                                             'لا شاشةَ سندِ قبضٍ مستقلة — الأقربُ الذممُ والتحصيل'),
            'bank.reconcile'           => $m('Finance/bank_reconciliation_fin.php',    'update', 'exact'),
            // ⑥ الصلاحية الذاتية
            'permission.create'        => $m('Settings/role_permissions.php',          'create', 'exact'),
            'permission.approve'       => $m('Governance/access_review.php',           'update', 'approx',
                                             'الاعتمادُ في دورة المراجعة الدورية'),
            'permission.apply'         => $m('Settings/role_permissions.php',          'update', 'approx',
                                             'يتقاسم شاشةَ الإنشاء — الرايةُ لا تفرّق'),
            // ⑦ نقل الملكية
            'ownership.share.create'   => $m(null, null, 'absent', 'لا شاشةَ لحصص الملكية في السجل'),
            'ownership.transfer.approve' => $m(null, null, 'absent', 'لا شاشةَ لنقل الحصص في السجل'),
            // ⑧ الفترة والقيد
            'period.open'              => $m('Finance/periods_fin.php',                'update', 'approx',
                                             'الفتحُ والإقفالُ في شاشةٍ واحدة'),
            'journal.entry'            => $m('Finance/journal_form_fin.php',           'create', 'exact'),
            'period.close.approve'     => $m('Finance/periods_fin.php',                'update', 'approx',
                                             'يتقاسم شاشةَ الفتح — الرايةُ لا تفرّق'),
        );
    }
}

if (!function_exists('ems_sod_flag_of')) {
    /** الفعلُ القانوني ← عمودُ الراية في role_permissions */
    function ems_sod_flag_of($action)
    {
        $f = array('screen_view' => 'can_view', 'create' => 'can_add',
                   'update' => 'can_edit', 'delete_draft' => 'can_delete');
        return isset($f[$action]) ? $f[$action] : null;
    }
}

if (!function_exists('ems_sod_codes_of_role')) {
    /** الرموزُ المعنويةُ التي يحملها دورٌ فعلًا — بحسب الخريطة والمصدر الحي. */
    function ems_sod_codes_of_role(mysqli $conn, $roleId)
    {
        static $cache = array();
        $roleId = (int) $roleId;
        if (isset($cache[$roleId])) { return $cache[$roleId]; }
        $held = array();
        foreach (ems_sod_map() as $code => $t) {
            if ($t['grade'] === 'absent' || $t['screen'] === null) { continue; }
            $flag = ems_sod_flag_of($t['action']);
            if ($flag === null) { continue; }
            $e = mysqli_real_escape_string($conn, $t['screen']);
            $r = mysqli_query($conn,
                "SELECT rp.{$flag} f FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                  WHERE m.code = '{$e}' AND rp.role_id = {$roleId} LIMIT 1");
            if ($r && ($x = mysqli_fetch_assoc($r)) && (int) $x['f'] === 1) { $held[$code] = 1; }
        }
        $cache[$roleId] = $held;
        return $held;
    }
}

if (!function_exists('ems_sod_pair_grade')) {
    /** درجةُ الزوج = أسوأُ درجاتِ رموزه (absent يغلب approx يغلب exact). */
    function ems_sod_pair_grade(array $codes)
    {
        $map = ems_sod_map();
        $g = 'exact';
        foreach ($codes as $c) {
            $cg = isset($map[$c]) ? $map[$c]['grade'] : 'absent';
            if ($cg === 'absent') { return 'absent'; }
            if ($cg === 'approx') { $g = 'approx'; }
        }
        return $g;
    }
}

if (!function_exists('ems_sod_split_codes')) {
    /** «a+b» → ['a','b'] */
    function ems_sod_split_codes($side)
    {
        $out = array();
        foreach (explode('+', (string) $side) as $p) { $p = trim($p); if ($p !== '') { $out[] = $p; } }
        return $out;
    }
}
