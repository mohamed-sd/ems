<?php
/**
 * includes/nav_groups.php — الاثنتا عشرةَ مجموعة: تعريفُها ونسبةُ كلِّ مسارٍ إليها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحكمُ المُنفَّذ** (نصُّ المالك 2026-08-17): «لا يزيد عددُ المجموعاتِ عن
 *   **اثنتَي عشرة** حتى يكون التقسيمُ أكثرَ تخصُّصًا · وتوزيعُ الروابطِ بحيث
 *   **يناسب اسمُ المجموعةِ الروابطَ التي بداخلها** · وكلُّ رابطٍ **داخلَ
 *   مجموعة** · و**«الرئيسية» أولُ رابطٍ في كلِّ شاشة**».
 *
 * ─── لماذا اثنتا عشرةَ لا عشر: قياسُ عدمِ مطابقةِ الاسمِ للمحتوى ────────────
 * ثلاثُ مجموعاتٍ من العشرِ كانت **تحمل اسمًا لا يصف نصفَ ما فيها**:
 *   ① «الحوكمة والمخاطر» = ٩٢ مسارًا من **أربعةِ مجالات**: حوكمة ٢١ · تدقيق ١٦
 *      · مخاطر ٣٣ · بلاغات ١٥ — **والبلاغاتُ ليست في الاسمِ أصلًا**.
 *      ⇐ شُقَّت إلى **«الحوكمة والتدقيق»** و**«المخاطر والبلاغات»**.
 *   ② «المالية والخزينة» = ٧٥ مسارًا: محاسبةٌ وإقفالٌ وموازنات (٥٨) + خزينةٌ
 *      وبنوك + **تمويلٌ وملكية (١١) ليست في الاسم**.
 *      ⇐ شُقَّت إلى **«المالية والمحاسبة»** و**«الخزينة والتمويل»**.
 *   ③ «المخازن والتوريد» = ٣٩: **مورّدون ٢٠** ومشترياتٌ ومخازنُ ١٩ —
 *      والمورّدُ ليس مخزنًا. ⇐ سُمِّيت **«الموردون والمشتريات»**.
 *
 * ─── قاعدةُ النسبةِ: المعنى أولًا ثم المجال ────────────────────────────────
 * ◆ المحورُ الأولُ **اسمُ مجموعةِ الدورةِ المستنديةِ** في `nav_canonical` — وصفٌ
 *   عمليٌّ كُتب لكلِّ مسار، يحمل من المعنى ما لا يحمله اسمُ المجلَّد.
 * ◆ وبعضُ الأسماءِ **يصلح لمجالين** («الحوكمة والضوابط» تسكنها شاشاتُ حوكمةٍ
 *   وشاشاتُ مخاطر)، فقيمتُه خريطةُ `دليل ⇐ رمز` بمُفتَرَضٍ `*`.
 * ◆ والاسمانِ المرجعيّانِ العامّان («البيانات المرجعية» ونظيرُه) يُردّان `null`
 *   فيهبط كلٌّ في **مجالِه** لا في الإعدادات — فتخرج تعرفةُ الترحيلِ والورشُ
 *   وكتالوجُ القطعِ من قاعِ الإعدادات إلى عملِها.
 * ◆ **ولا مسارَ بلا سند**: `PIN` · `GROUP:` · `GROUPDIR:` · `LEVEL:` · `DIR:` ·
 *   `FALLBACK` — يُخزَّن في `nav_route_group.basis` ويُقرأ.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_nav_groups_def')) {
    /**
     * الاثنتا عشرةَ بترتيبِ الظهور — من «أنا» إلى الضبط، مرورًا بدورةِ العمل.
     * المفتاحُ رمزٌ ثابتٌ يعيش في `data-group-key` فتُحفظ حالةُ الطيِّ عبرَ الشاشات.
     * @return array code => array('sort','name','icon','open')
     */
    function ems_nav_groups_def()
    {
        return array(
            'MINE'       => array('sort' => 1,  'name' => 'مساحتي',              'icon' => 'fa fa-house',           'open' => 1),
            'DAILY'      => array('sort' => 2,  'name' => 'التشغيل اليومي',      'icon' => 'fa fa-briefcase',       'open' => 0),
            'COMMERCIAL' => array('sort' => 3,  'name' => 'العقود والعملاء',     'icon' => 'fa fa-file-signature',  'open' => 0),
            'ASSETS'     => array('sort' => 4,  'name' => 'المعدات والأسطول',    'icon' => 'fa fa-truck-field',     'open' => 0),
            'PEOPLE'     => array('sort' => 5,  'name' => 'الموظفون والمشغّلون', 'icon' => 'fa fa-users',           'open' => 0),
            'SUPPLY'     => array('sort' => 6,  'name' => 'الموردون والمشتريات', 'icon' => 'fa fa-truck-ramp-box',  'open' => 0),
            'FINANCE'    => array('sort' => 7,  'name' => 'المالية والمحاسبة',   'icon' => 'fa fa-calculator',      'open' => 0),
            'TREASURY'   => array('sort' => 8,  'name' => 'الخزينة والتمويل',    'icon' => 'fa fa-coins',           'open' => 0),
            'GOVERNANCE' => array('sort' => 9,  'name' => 'الحوكمة والتدقيق',    'icon' => 'fa fa-scale-balanced',  'open' => 0),
            'RISK'       => array('sort' => 10, 'name' => 'المخاطر والبلاغات',   'icon' => 'fa fa-shield-halved',   'open' => 0),
            'REPORTS'    => array('sort' => 11, 'name' => 'التقارير والتحليلات', 'icon' => 'fa fa-chart-column',    'open' => 0),
            'SETUP'      => array('sort' => 12, 'name' => 'الإعدادات',           'icon' => 'fa fa-sliders-h',       'open' => 0),
        );
    }
}

if (!function_exists('ems_nav_group_pins')) {
    /**
     * المُثبَّتات — **ما يظهر في أكثرِ من إدارةٍ يسكن مكانًا واحدًا لا يتغير**،
     * وما يخالف اسمَ مجموعتِه المسجَّلَ يُصحَّح هنا بسندٍ مقروء.
     * وهي أعلى القواعدِ رتبةً: لا اسمُ دورةٍ ولا دليلٌ ولا مستوًى يزحزحها.
     */
    function ems_nav_group_pins()
    {
        return array(
            /* ① مساحتي — الرئيسيةُ والمراسلاتُ وكلُّ ما هو «أنا» */
            'main/role_board.php'          => 'MINE',
            'main/dashboard.php'           => 'MINE',
            'main/global_search.php'       => 'MINE',
            'main/my_workspace.php'        => 'MINE',
            'main/profile.php'             => 'MINE',
            'main/user_profile.php'        => 'MINE',
            'main/glossary.php'            => 'MINE',
            'main/soon.php'                => 'MINE',
            'chats/index.php'              => 'MINE',
            'portal/my_tasks.php'          => 'MINE',
            'portal/my_achievement.php'    => 'MINE',
            'portal/my_portal.php'         => 'MINE',
            'portal/my_requests.php'       => 'MINE',
            'portal/my_kpi.php'            => 'MINE',
            'portal/my_certificate.php'    => 'MINE',
            'portal/my_evaluation.php'     => 'MINE',
            'portal/notifications.php'     => 'MINE',
            'portal/workspace.php'         => 'MINE',
            'portal/dept_board.php'        => 'MINE',
            'portal/dept_achievement.php'  => 'MINE',
            'portal/ceo_board.php'         => 'MINE',
            'oprators/select_project.php'  => 'MINE',
            'timesheet/timesheet_type.php' => 'MINE',
            'finrequests/my_requests.php'  => 'MINE',
            'tickets/my_tickets.php'       => 'MINE',
            'user_capacities.php'          => 'MINE',

            /* ①-ب «ما ينتظرني» — قسمٌ داخلَ مساحتي (انظر ems_nav_pin_section) */
            'approvals/requests.php'          => 'MINE',
            'approvals/hours_approval.php'    => 'MINE',
            'approvals/attribution_board.php' => 'MINE',
            'portal/approvals_inbox.php'      => 'MINE',
            'portal/ceo_approvals.php'        => 'MINE',
            'portal/ceo_assignments.php'      => 'MINE',
            'portal/contract_review.php'      => 'MINE',
            'portal/ceo_contracts.php'        => 'MINE',
            'finance/approvals_inbox.php'     => 'MINE',
            'finance/dept_approvals.php'      => 'MINE',
            'finrequests/dept_inbox.php'      => 'MINE',

            /* ② التشغيل اليومي — تصحيحاتُ أحكامٍ خالفَها اسمُ المجموعةِ المسجَّل */
            'maintenance/inspections.php'      => 'DAILY',  /* «استقبالُ البلاغ» وهو فحصٌ فنيٌّ يوميّ */
            'operations/shift_log.php'         => 'DAILY',
            'movement/movement_operations.php' => 'DAILY',
            'portal/project_charter.php'       => 'DAILY',
            'projects/sites.php'               => 'DAILY',
            'transport/transfer_tariffs.php'   => 'DAILY',

            /* ③ العقود والعملاء */
            'clients/tenders.php'             => 'COMMERCIAL',
            'workforce/contract_registry.php' => 'COMMERCIAL',

            /* ②-ب المشاريعُ والمواقعُ معًا في التشغيلِ اليوميّ — كان «المشاريع
                  والمواقع» في العقودِ (قسمُه «العملاء والفرص») و«مواقعُ التنفيذ»
                  في التشغيل، وهما وجهان لشيءٍ واحدٍ فلا يفترقان. */
            'projects/projects.php' => 'DAILY',

            /* ⑦ المالية والمحاسبة */
            'finance/budget_dept.php'      => 'FINANCE',
            'finrequests/request_form.php' => 'FINANCE',

            /* ⑩ المخاطر والبلاغات — البلاغُ وصندوقُه ومساحةُ المخاطرِ لكلِّ إدارة،
                  وشاشتا مخاطرَ سُجِّلتا تحت «الحوكمة والضوابط» وهما مخاطرُ صريحة */
            'tickets/ticket_contextual_open.php' => 'RISK',
            'tickets/tickets_list.php'           => 'RISK',
            'tickets/dept_inbox.php'             => 'RISK',
            'risk/dept_risk_space.php'           => 'RISK',
            'clients/commercial_risks.php'       => 'RISK',
            'portal/ceo_risk.php'                => 'RISK',

            /* ⑨ الحوكمة والتدقيق */
            'governance/impersonations.php' => 'GOVERNANCE',

            /* ⑪ التقارير — مركزا التقاريرِ ثابتان أينما ظهرا */
            'emsreports/index.php' => 'REPORTS',
            'reports/reports.php'  => 'REPORTS',

            /* ⑫ الإعدادات — الحساباتُ والمعاونون */
            'main/project_users.php'  => 'SETUP',
            'main/all_assistants.php' => 'SETUP',
            'main/users.php'          => 'SETUP',
        );
    }
}

if (!function_exists('ems_nav_pin_section')) {
    /**
     * قسمٌ مفروضٌ يغلب عنوانَ المصفوفة. والقيمةُ `'-'` تعني **امحُ القسم**
     * فيصعد الرابطُ إلى صدرِ المجموعةِ المكشوف.
     *
     * ◆ **لِمَ يلزم**: المسارُ المنقولُ بمُثبِّتٍ يحمل معه **عنوانَ قسمِه من
     *   المصفوفة**، وقد يكون عنوانًا من مجالٍ آخر — فيُطبع تحتَ مجموعةٍ رأسٌ
     *   فرعيٌّ يناقضها. قِيس: خمسةَ عشرَ مسارًا كذلك، منها «ميزانية إدارتي»
     *   تحتَ عنوانِ «عمليات التمويل» **داخلَ «المالية والمحاسبة»**، و«الفحصُ
     *   الفنيُّ اليوميّ» تحتَ «استقبال البلاغ» **داخلَ «التشغيل اليومي»**.
     *   ونصُّ المالك صريح: «تأكَّد أن اسمَ المجموعةِ يناسب الروابطَ بداخلها» —
     *   وعنوانُ القسمِ جزءٌ من ذلك الاسم.
     */
    function ems_nav_pin_section($route)
    {
        static $s = array(
            /* «ما ينتظرني» — صناديقُ الاعتمادِ في صدرِ «مساحتي» بعنوانٍ ثابتِ الظهور */
            'approvals/requests.php'          => 'ما ينتظرني',
            'approvals/hours_approval.php'    => 'ما ينتظرني',
            'approvals/attribution_board.php' => 'ما ينتظرني',
            'portal/approvals_inbox.php'      => 'ما ينتظرني',
            'portal/ceo_approvals.php'        => 'ما ينتظرني',
            'portal/ceo_assignments.php'      => 'ما ينتظرني',
            'portal/contract_review.php'      => 'ما ينتظرني',
            'portal/ceo_contracts.php'        => 'ما ينتظرني',
            'finance/approvals_inbox.php'     => 'ما ينتظرني',
            'finance/dept_approvals.php'      => 'ما ينتظرني',
            'finrequests/dept_inbox.php'      => 'ما ينتظرني',

            /* عنوانٌ صحيحٌ بدل عنوانِ مجالٍ آخر */
            'finance/budget_dept.php'    => 'الموازنات',
            'operations/shift_log.php'   => 'التنفيذ اليومي',
            'portal/project_charter.php' => 'التخطيط وتخصيص الموارد',

            /* بلا عنوانٍ — يصعد إلى صدرِ مجموعتِه (عنوانُ مصفوفتِه من مجالٍ آخر) */
            'main/dashboard.php'               => '-',
            'portal/dept_achievement.php'      => '-',
            'tickets/my_tickets.php'           => '-',
            'emsreports/index.php'             => '-',
            'reports/reports.php'              => '-',
            'projects/sites.php'               => '-',
            'projects/projects.php'            => '-',
            'clients/tenders.php'              => '-',
            'clients/commercial_risks.php'     => '-',
            'portal/ceo_risk.php'              => '-',
            'governance/impersonations.php'    => '-',
            'finrequests/request_form.php'     => '-',
            'movement/movement_operations.php' => '-',
            'transport/transfer_tariffs.php'   => '-',
            'maintenance/inspections.php'      => '-',
        );
        $route = strtolower(trim((string) $route));
        return isset($s[$route]) ? $s[$route] : '';
    }
}

if (!function_exists('ems_nav_forced_sections')) {
    /** أقسامٌ يظهر عنوانُها دائمًا ولو قصُرت المجموعة. */
    function ems_nav_forced_sections()
    {
        return array('ما ينتظرني' => true);
    }
}

if (!function_exists('ems_nav_group_by_cycle')) {
    /**
     * **المحورُ الأول**: اسمُ مجموعةِ الدورةِ المستنديةِ ⇐ الرمز.
     *
     * @param string $groupName اسمُ المجموعةِ في `nav_canonical`
     * @param string $dir       مجلَّدُ المسار (لحسمِ الأسماءِ الصالحةِ لمجالين)
     * @return string|null|false رمزٌ · أو `null` (يُحسم بالمجال) · أو `false` (لا حكم)
     */
    function ems_nav_group_by_cycle($groupName, $dir = '')
    {
        static $map = null;
        if ($map === null) {
            $map = array(
                /* ① مساحتي */
                'مساحتي الشخصية' => 'MINE',
                'المركز التنفيذي' => 'MINE',
                'مركز العمل' => 'MINE',
                'ملفي الشخصي' => 'MINE',
                'الاعتمادات والمسائل المرفوعة' => 'MINE',
                'اعتماد الوحدات' => 'MINE',
                'فريق العمل' => 'MINE',

                /* ② التشغيل اليومي */
                'التنفيذ اليومي' => 'DAILY',
                'التخطيط وتخصيص الموارد' => 'DAILY',
                'الحضور والانصراف' => 'DAILY',
                'الاعتمادات الواردة' => 'DAILY',
                'أوامر الترحيل' => 'DAILY',
                'طلبات النقل' => 'DAILY',
                'الصيانة الوقائية' => 'DAILY',
                'التنفيذ والإقفال' => 'DAILY',
                'الانحرافات وإقفال الفترة' => 'DAILY',
                'تقارير وإعدادات الترحيل' => 'DAILY',
                'استقبال البلاغ' => 'DAILY',

                /* ③ العقود والعملاء */
                'العملاء والفرص' => 'COMMERCIAL',
                'العروض والتسعير' => 'COMMERCIAL',
                'العقود وأحكامها' => 'COMMERCIAL',
                'خطط العقد والتزاماته' => 'COMMERCIAL',
                'التغطية التعاقدية' => 'COMMERCIAL',
                'التعاقد والتغطية والحصص' => 'COMMERCIAL',
                'التنفيذ التعاقدي' => 'COMMERCIAL',
                'المستخلصات والفوترة والتحصيل' => 'COMMERCIAL',
                'المستخلصات والذمم' => 'COMMERCIAL',
                'التجديد والإقفال' => 'COMMERCIAL',
                'القرارات المحجوزة' => 'COMMERCIAL',
                'التأجير قصير الأمد' => 'COMMERCIAL',

                /* ④ المعدات والأسطول */
                'سجل الأصول' => 'ASSETS',
                'الجاهزية التشغيلية' => 'ASSETS',
                'الإهلاك والاستبعاد' => 'ASSETS',
                'إدخال الأصل والترميز' => 'ASSETS',
                'أمر العمل والتكليف' => 'ASSETS',
                'الإعدادات والتدقيق' => 'ASSETS',
                /* عطلُ المعدةِ أصلٌ · وتفريعُ البلاغِ بلاغ */
                'استقبال العطل وتشخيصه' => array('equipments' => 'ASSETS', '*' => 'RISK'),

                /* ⑤ الموظفون والمشغّلون */
                'ملف الموظف وعقوده' => 'PEOPLE',
                'سجل المشغّلين' => 'PEOPLE',
                'التكليف على المعدات' => 'PEOPLE',
                'التأهيل والوثائق والحركة' => 'PEOPLE',
                'التكليف والأداء' => 'PEOPLE',
                'متابعة الأداء' => 'PEOPLE',
                'الخصومات والسلف' => 'PEOPLE',
                'إنهاء الخدمة' => 'PEOPLE',
                'المسيّر والدفع' => 'PEOPLE',
                'المعاملات والسجلات' => array('governance' => 'GOVERNANCE', '*' => 'PEOPLE'),
                'العمليات' => array('clients' => 'COMMERCIAL', 'finrequests' => 'FINANCE',
                                    'movement' => 'DAILY', 'chats' => 'MINE', '*' => 'PEOPLE'),

                /* ⑥ الموردون والمشتريات */
                'ملف المورد وتأهيله' => 'SUPPLY',
                'تعاقد الموردين وتسويتهم' => 'SUPPLY',
                'التأهيل والقدرة' => 'SUPPLY',
                'تقييم الأداء والإنهاء' => 'SUPPLY',
                'التسويات والصرف' => 'SUPPLY',
                'التنفيذ والاستحقاق' => 'SUPPLY',
                'العقود والحصص' => 'SUPPLY',
                'توزيع الحصص والحاويات' => 'SUPPLY',
                'الطلبات وحدود المخزون' => 'SUPPLY',
                'طلبات العروض' => 'SUPPLY',
                'الاستلام' => 'SUPPLY',
                'الاستلام والمطابقة' => 'SUPPLY',
                'الجرد' => 'SUPPLY',
                'استقبال الاحتياج' => 'SUPPLY',
                'سجلات وإعدادات المخازن' => 'SUPPLY',

                /* ⑦ المالية والمحاسبة — دفترٌ وإقفالٌ وموازناتٌ وتكلفة */
                'إقفال الفترة' => 'FINANCE',
                'الالتزامات والاستحقاق' => 'FINANCE',
                'الاستحقاق وأحكام الأطراف' => 'FINANCE',
                'القوائم والترحيل المحاسبي' => 'FINANCE',
                'التكلفة والإهلاك والهامش' => 'FINANCE',
                'اقتصاديات الوحدة والعقد' => 'FINANCE',
                'النسب والإنذار المبكر' => 'FINANCE',
                'سجل الحركة المالية' => 'FINANCE',
                'الإشراف والجودة والحدود' => 'FINANCE',
                'المتابعة والموافقات' => 'FINANCE',
                'الإقفال والموازنة' => 'FINANCE',
                'التأسيس والهيكل المؤسسي' => 'FINANCE',
                'الطلبات المالية وموافقاتها' => 'FINANCE',
                'الترحيل والاعتمادات المالية' => 'FINANCE',
                'السجلات الرئيسية' => array('projects' => 'DAILY', 'main' => 'SETUP',
                                            'admin' => 'SETUP', '*' => 'FINANCE'),

                /* ⑧ الخزينة والتمويل — نقدٌ وبنوكٌ وممولون وملكية */
                'الخزينة والبنوك' => 'TREASURY',
                'الخزينة والمطابقة' => 'TREASURY',
                'عمليات التمويل' => 'TREASURY',
                'الممولون والتسجيل' => 'TREASURY',
                'الملكية والضمانات' => 'TREASURY',
                'الأقساط والسداد' => 'TREASURY',
                'الانحرافات والفروق' => 'TREASURY',

                /* ⑨ الحوكمة والتدقيق */
                'الحوكمة' => 'GOVERNANCE',
                'التدقيق والمراجعة' => 'GOVERNANCE',
                'الالتزام والامتثال' => 'GOVERNANCE',
                'الملاحظات والتوصيات' => 'GOVERNANCE',
                'الميثاق والخطة' => 'GOVERNANCE',
                'الميثاق والخطة والمهام' => 'GOVERNANCE',
                'تنفيذ المهمة وأدلتها' => 'GOVERNANCE',
                'لوحة المراجعة الداخلية' => 'GOVERNANCE',
                'السقوف وفصل الواجبات' => 'GOVERNANCE',
                'الاعتمادات العليا' => 'GOVERNANCE',
                'الرقابة العليا' => 'GOVERNANCE',
                /* اسمٌ يسكنه المجالان: ضوابطُ الحوكمةِ وضوابطُ المخاطر */
                'الحوكمة والضوابط' => array('risk' => 'RISK', 'clients' => 'RISK',
                                             'portal' => 'RISK', '*' => 'GOVERNANCE'),

                /* ⑩ المخاطر والبلاغات */
                'سجل المخاطر' => 'RISK',
                'قبول الخطر والتصعيد' => 'RISK',
                'التقييم والقياس' => 'RISK',
                'المراقبة والمؤشرات' => 'RISK',
                'الشهية والمنهج' => 'RISK',
                'لوحة المخاطر المؤسسية' => 'RISK',
                'استقبال البلاغات' => 'RISK',
                'التوجيه الآلي' => 'RISK',
                'متابعة الصيانة' => 'RISK',
                'الإغلاق وانضباطه' => 'RISK',
                'الأنواع والمهل والتصعيد' => 'RISK',

                /* ⑪ التقارير والتحليلات */
                'التقارير والتحليلات' => 'REPORTS',
                'التقارير والتصدير' => 'REPORTS',
                'التقارير للجهة المشرفة' => 'REPORTS',
                'الأداء المؤسسي' => 'REPORTS',
                'لوحة الدور' => 'REPORTS',

                /* ⑫ الإعدادات */
                'الهيكل والأدوار' => 'SETUP',
                'الحسابات والأدوار' => 'SETUP',
                'الكيانات والتراخيص' => 'SETUP',
                'أنواع المستندات وآلات الحالة' => 'SETUP',
                'الأحداث والمهام الخلفية' => 'SETUP',
                'إعدادات النظام وبصمة الإصدار' => 'SETUP',
                'الظهور وضبط البوابة' => 'SETUP',
                'الظهور والبوابة والصفحات' => 'SETUP',
                'الإعدادات' => 'SETUP',

                /* ⟂ اسمانِ مرجعيّانِ عامّان — يُحسمان بالمجال لا هنا */
                'البيانات المرجعية' => null,
                'البيانات المرجعية والإعدادات' => null,
            );
        }
        $g = trim((string) $groupName);
        if (!array_key_exists($g, $map)) { return false; }
        $v = $map[$g];
        if (is_array($v)) {
            $dir = strtolower(trim((string) $dir));
            if ($dir !== '' && isset($v[$dir])) { return $v[$dir]; }
            return isset($v['*']) ? $v['*'] : null;
        }
        return $v;
    }
}

if (!function_exists('ems_nav_group_dirs')) {
    /** **المحورُ الثاني**: الدليلُ يحدد المجالَ حين لا مُثبِّتَ ولا اسمَ دورة. */
    function ems_nav_group_dirs()
    {
        return array(
            'finance' => 'FINANCE', 'finrequests' => 'FINANCE',
            'financing' => 'TREASURY',
            'governance' => 'GOVERNANCE', 'audit' => 'GOVERNANCE',
            'risk' => 'RISK', 'tickets' => 'RISK',
            'procurement' => 'SUPPLY', 'suppliers' => 'SUPPLY',
            'contracts' => 'COMMERCIAL', 'clients' => 'COMMERCIAL', 'opportunities' => 'COMMERCIAL',
            'equipments' => 'ASSETS', 'fleet' => 'ASSETS',
            'employees' => 'PEOPLE', 'workforce' => 'PEOPLE', 'oprators' => 'PEOPLE', 'movement' => 'PEOPLE',
            'operations' => 'DAILY', 'timesheet' => 'DAILY', 'transport' => 'DAILY',
            'maintenance' => 'DAILY', 'projects' => 'DAILY', 'approvals' => 'DAILY', 'portal' => 'DAILY',
            'reports' => 'REPORTS', 'emsreports' => 'REPORTS',
            'settings' => 'SETUP', 'admin' => 'SETUP', 'activitylogs' => 'SETUP', 'main' => 'SETUP',
            'chats' => 'MINE', '(root)' => 'SETUP',
        );
    }
}

if (!function_exists('ems_nav_domain_for_route')) {
    /**
     * مجالُ المسارِ **بالدليلِ وحدَه** — بلا القواعدِ الوظيفية.
     * يُستعمل محورًا ثانيًا لبناءِ المجموعاتِ العابرةِ للمجالات («التقارير»
     * و«الإعدادات») حين تكون عناوينُ مصفوفتِها مفردةً فلا تبني شيئًا.
     */
    function ems_nav_domain_for_route($route)
    {
        $route = strtolower(trim((string) $route));
        $dir = strpos($route, '/') !== false ? substr($route, 0, strpos($route, '/')) : '(root)';
        $dirs = ems_nav_group_dirs();
        return isset($dirs[$dir]) ? $dirs[$dir] : 'DAILY';
    }
}

if (!function_exists('ems_nav_group_for_route')) {
    /**
     * حكمُ المجموعةِ لمسارٍ واحد — بترتيبِ رتبةٍ صريحٍ ومعه سندُه.
     *
     * @param string $route مسارٌ مطبَّعٌ صغيرًا بلا `../` ولا استعلامٍ ولا مرساة
     * @param int    $level `nav_canonical.level_no` (0 إن لا صفَّ له)
     * @param string $group `nav_canonical.group_name` (فراغٌ إن لا صفَّ له)
     * @return array array(group_code, basis)
     */
    function ems_nav_group_for_route($route, $level = 0, $group = '')
    {
        $route = strtolower(trim((string) $route));
        $pins = ems_nav_group_pins();
        if (isset($pins[$route])) { return array($pins[$route], 'PIN'); }

        $dir = strpos($route, '/') !== false ? substr($route, 0, strpos($route, '/')) : '(root)';
        $group = trim((string) $group);

        /* ① التقريرُ تقريرٌ قبلَ مجالِه — والمستوى الرابعُ **هو** طبقةُ التقارير
              في نموذجِ المصفوفةِ نفسِه. وقِيس البديلُ (التقريرُ مع موضوعِه):
              مجموعةُ المالية ٤٢ رابطًا والتقاريرُ رابطٌ واحد. */
        if ((int) $level === 4) { return array('REPORTS', 'LEVEL:4'); }

        /* ② المعنى: اسمُ مجموعةِ الدورةِ المستندية (وبعضُه يُحسم بالدليل) */
        if ($group !== '') {
            $byCycle = ems_nav_group_by_cycle($group, $dir);
            if (is_string($byCycle)) { return array($byCycle, 'GROUP:' . $group); }
            if ($byCycle === null) {  /* مرجعُ مجالٍ — يهبط في مجالِه لا في الإعدادات */
                return array(ems_nav_domain_for_route($route), 'GROUPDIR:' . $group);
            }
        }

        /* ③ مجلَّدُ التقاريرِ لما لا صفَّ له في المصفوفة */
        if ($dir === 'reports' || $dir === 'emsreports') { return array('REPORTS', 'DIR:' . $dir); }

        /* ④ إعداداتُ النظامِ بمجلَّدِها */
        if ($dir === 'settings' || $dir === 'admin' || $dir === 'activitylogs') { return array('SETUP', 'DIR:' . $dir); }

        /* ⑤ المجالُ بالدليل */
        $dirs = ems_nav_group_dirs();
        if (isset($dirs[$dir])) { return array($dirs[$dir], 'DIR:' . $dir); }

        /* ⑥ وما لا يُعرف يهبط في التشغيلِ اليوميِّ ظاهرًا — لا يُخفى ولا يُسقَط */
        return array('DAILY', 'FALLBACK');
    }
}
