<?php
/**
 * includes/dept_gov_registry.php — سجلُّ نطاقاتِ حوكمةِ الإدارات (INJ-0171)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطل: مكوّنُ «حوكمة الإدارة» واحدٌ (`includes/dept_gov_space.php`) لكنّ
 *   بلوغَه يحتاج **غلافَ نطاقٍ لكلِّ إدارة**. فبُني غلافان (المالية والحوكمة)
 *   وبقيت بقيةُ الإداراتِ بلا شاشةِ حوكمة — والأسطولُ منها (INJ-0171).
 * ◆ والعلاجُ ليس غلافًا ثالثًا: كلُّ غلافٍ جديدٍ يعيد المشكلةَ لإدارةٍ رابعة.
 *   بل **سجلٌّ واحدٌ** يربط الدورَ بنطاقِه، ومدخلٌ عامٌّ (`Governance/gov_dept.php`)
 *   يقرأ دورَ الجلسةِ منه. فالإدارةُ الجديدةُ تحتاج مدخلةً هنا لا ملفًّا هناك.
 * ◆ والنطاقُ **مُعلَنٌ لا مُستنتَج**: دورٌ بلا مدخلةٍ لا يرى شاشةَ حوكمةٍ فارغةً
 *   ولا شاشةَ إدارةٍ أخرى — يُردُّ برسالةٍ تسمّي السبب (فشلٌ مغلق).
 */

if (!function_exists('ems_dept_gov_registry')) {
    /**
     * @return array<string,array> مفتاحُه رمزُ الإدارةِ، وقيمتُه عقدُ $GOV_DEPT
     *                            ناقصًا `sod_queries` (تُبنى بـcompany_id).
     */
    function ems_dept_gov_registry()
    {
        return array(
            'flt' => array(
                'title'           => 'حوكمة إدارة الأسطول',
                'icon'            => 'fas fa-truck-front',
                'module_like'     => array('Equipments/', 'Fleet/', 'movement/'),
                'team_roles'      => array(3, 10, 11),
                'sensitive_like'  => 'equipment%',
                'events_module'   => 'fleet',
                'attest_endpoint' => '',
                'attest_code'     => 'gov.flt.attest',
                'attest_scope'    => 'gov_dept_flt',
            ),
            'hrm' => array(
                'title'           => 'حوكمة الموارد البشرية',
                'icon'            => 'fas fa-users-gear',
                'module_like'     => array('Employees/', 'Workforce/'),
                'team_roles'      => array(4),
                'sensitive_like'  => 'employee%',
                'events_module'   => 'hr',
                'attest_endpoint' => '',
                'attest_code'     => 'gov.hrm.attest',
                'attest_scope'    => 'gov_dept_hrm',
            ),
            'prc' => array(
                'title'           => 'حوكمة المشتريات',
                'icon'            => 'fas fa-cart-flatbed',
                'module_like'     => array('Procurement/', 'Suppliers/'),
                'team_roles'      => array(16, 2, 8),
                'sensitive_like'  => 'proc%',
                'events_module'   => 'procurement',
                'attest_endpoint' => '',
                'attest_code'     => 'gov.prc.attest',
                'attest_scope'    => 'gov_dept_prc',
            ),
            'trp' => array(
                'title'           => 'حوكمة النقل والترحيل',
                'icon'            => 'fas fa-route',
                'module_like'     => array('Transport/'),
                'team_roles'      => array(23),
                'sensitive_like'  => 'transfer%',
                'events_module'   => 'transport',
                'attest_endpoint' => '',
                'attest_code'     => 'gov.trp.attest',
                'attest_scope'    => 'gov_dept_trp',
            ),
            'mnt' => array(
                'title'           => 'حوكمة الصيانة',
                'icon'            => 'fas fa-screwdriver-wrench',
                'module_like'     => array('Maintenance/'),
                'team_roles'      => array(13, 14),
                'sensitive_like'  => 'mnt%',
                'events_module'   => 'maintenance',
                'attest_endpoint' => '',
                'attest_code'     => 'gov.mnt.attest',
                'attest_scope'    => 'gov_dept_mnt',
            ),
        );
    }
}

if (!function_exists('ems_dept_gov_of_role')) {
    /**
     * رمزُ إدارةِ الدور، أو null إن لم يكن له نطاقٌ **مُعلَن**.
     * ◆ لا استنتاجَ ولا افتراض: الدورُ الذي لا مدخلةَ له يُردُّ ولا يُخمَّن له
     *   نطاقٌ — فتخمينُ النطاقِ في شاشةِ حوكمةٍ يعرض سجلاتِ إدارةٍ ليست إدارتَه.
     */
    function ems_dept_gov_of_role($roleId)
    {
        $roleId = (int) $roleId;
        foreach (ems_dept_gov_registry() as $code => $cfg) {
            if (in_array($roleId, $cfg['team_roles'], true)) { return $code; }
        }
        return null;
    }
}
