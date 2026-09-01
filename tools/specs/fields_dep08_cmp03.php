<?php
/**
 * tools/specs/fields_dep08_cmp03.php — إغلاقُ حقولِ `DEP-08` · الأسرةُ الثانية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **أسرةُ `CMP-03`**: أسطحٌ تكتب حمولةً (تسميةٌ ⇐ قيمة) وتقرؤها بالتسميةِ
 *   نفسِها من `cmp03_store_rows`. **فتغييرُ التسميةِ في السطحِ وحدَه يقطع
 *   مفتاحَ الخليّةِ عن قيمتِها** — «—» في كلِّ صفّ، وهو عطبُ
 *   [[declared-column-not-built]] بيدٍ جديدة.
 *   ⇒ **فالعرضُ يقرأ العمودَ** (`cmp03_store_raw`) والخريطةُ تربط اسمَ الورقةِ
 *   بالعمودِ مباشرةً. ⛔ **والكتابةُ لم تُمَسّ**: نموذجُ الإضافةِ ما زال يمرُّ
 *   بـ`cmp03_store_insert` بخريطةِ السجلِّ المولَّدةِ نفسِها، فلا سجلٌّ يُحرَّر
 *   بيدٍ ولا مولِّدٌ يُخالَف.
 *
 * ◆ **وأسطحٌ بعينِها تقرأ من جدولِ مجالِها** (‏التراخيصُ والتفويضُ والكياناتُ
 *   والتمرينُ والمحاولاتُ الممنوعة) — فمرساتُها ومصدرُ صفوفِها مصرَّحان.
 *
 * ◆ ⛔ **وأعمدةُ الحوكمةِ المشتركةُ لا تُلفَّق**: ما ليس في الورقةِ لا يُعرَض،
 *   وما في الورقةِ ولا نظيرَ له يأخذ عمودًا بهجرةٍ ولها عكسُها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return array(

'dept' => 'DEP-08',
'screens' => array(

/* ═══ GOV-14 · تصنيف قواعد المنع ══════════════════════════════════════════ */
array(
    'req' => 'GOV-14', 'route' => 'Governance/guards.php', 'table' => 'scr_guards',
    'anchor' => '<table class="alltables display" id="guardsTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'guardsTable', 'empty' => 'لا قاعدة منع مسجلة بعد',
    'map' => array(
        'كود القاعدة' => 'code_guard',
        'اسم القاعدة' => 'name_protection',
        'وصف المنع' => 'message_denial',
        'الصنف' => 'category',
        'حالة العلم' => 'state_flag',
        'الشاشات المنفذة عليها' => 'screens_affected',
        'مرجع الاستثناء المسموح' => '+exception_ref:VARCHAR(120) NULL DEFAULT NULL',
        'وقائع الإنفاذ' => '#denials',
        'حالة القاعدة' => 'status_label',
        'المنشئ' => 'created_by_name',
        'تاريخ الإنشاء' => 'created_at',
        'مرجع المصدر' => 'authority_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* وقائعُ الإنفاذ: كم مرّةً منعت هذه القاعدةُ فعلًا — من سجلِّ المحاولاتِ
           الممنوعةِ نفسِه، ⛔ لا من عدّادٍ يُكتب بيد. */
        'denials' => function ($r) { return ems_w14_denial_count($r['code_guard']); },
    );
PHP
),

/* ═══ GOV-17 · صلاحية الطوارئ اللحظية ═════════════════════════════════════ */
array(
    'req' => 'GOV-17', 'route' => 'Governance/break_glass.php', 'table' => 'scr_break_glass',
    'anchor' => '<table class="alltables display" id="break_glassTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'break_glassTable', 'empty' => 'لا منح طوارئ مسجل بعد',
    'map' => array(
        'رقم المنح' => 'no_request',
        'المستفيد' => 'requester_name_capacity_role',
        'النطاق الممنوح' => 'permission_required',
        'المبرر' => 'reason_emergency',
        'مانح الصلاحية' => '#granter',
        'من وقت' => 'time_grant',
        'إلى وقت' => 'time_expiry',
        'مدة المنح' => 'duration_permission',
        'الأفعال المنفذة تحتها' => 'count_actions_executed_under_it',
        'المراجعة اللاحقة' => '#review',
        'نتيجة المراجعة' => 'result_review',
        'حالة المنح' => 'status_label',
        'المنشئ' => 'created_by_name',
        'تاريخ الإنشاء' => 'created_at',
        'المراجع' => 'approver_second',
        'تاريخ الاعتماد' => 'date_review',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* مانحُ الصلاحيةِ: الموافقُ الأوّلُ، ومعه الجهةُ البديلةُ إن لجئ إليها
           (‏الطالبُ هو المعتمِدُ المعتاد) — فاليدُ الثانيةُ تُعلَن لا تُطوى. */
        'granter' => function ($r) {
            $a = trim((string) $r['approver_first']);
            $b = trim((string) $r['alternate_authority']);
            return ($a !== '' && $b !== '') ? ($a . ' / ' . $b) : ($a !== '' ? $a : $b);
        },
        'review' => function ($r) {
            $t = trim((string) $r['report_review']);
            $d = trim((string) $r['date_review']);
            return ($t !== '' && $d !== '') ? ($t . ' / ' . $d) : ($t !== '' ? $t : $d);
        },
    );
PHP
),

/* ═══ GOV-21 · طلبات الاستثناء ════════════════════════════════════════════ */
array(
    'req' => 'GOV-21', 'route' => 'Governance/exceptions.php', 'table' => 'scr_exceptions',
    'anchor' => '<table class="alltables display" id="exceptionsTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'exceptionsTable', 'empty' => 'لا طلب استثناء مسجل بعد',
    'map' => array(
        'رقم الاستثناء' => 'no_request',
        'تاريخ الطلب' => 'date_request',
        'الطالب' => 'created_by_name',
        'الإدارة' => 'dept_requesting',
        'قاعدة المنع المستهدفة' => 'protection_exempted',
        'المبرر' => 'reason_exception',
        'درجة الخطورة' => 'grade_severity',
        'من تاريخ' => 'period_from',
        'إلى تاريخ' => 'period_to',
        'المعتمد بحسب الخطورة' => '#approvers',
        'شروط الاستثناء' => 'docs_supporting',
        'مراجعة منتصف المدة' => '#midterm',
        'حالة الاستثناء' => 'status_label',
        'تاريخ الإنشاء' => 'created_at',
        'المراجع' => 'approvers',
        'تاريخ الاعتماد' => 'approved_date',
        'مرجع المصدر' => 'authority_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* المعتمِدُ بحسبِ الخطورة: الموافقاتُ المطلوبةُ بدرجتِها — والدرجةُ
           تحدّد العدد، فالحقلان يُقرآن معًا لا مفترقَين. */
        'approvers' => function ($r) {
            $q = trim((string) $r['approvals_required']);
            $g = trim((string) $r['grade_severity']);
            return ($q !== '' && $g !== '') ? ($q . ' (' . $g . ')') : ($q !== '' ? $q : '');
        },
        /* مراجعةُ منتصفِ المدّة: نقطةُ المنتصفِ بين بدءِ الاستثناءِ وانتهائه */
        'midterm' => function ($r) { return ems_w14_midpoint($r['period_from'], $r['period_to']); },
    );
PHP
),

/* ═══ GOV-19 · سياسات الحقول الحساسة ══════════════════════════════════════ */
array(
    'req' => 'GOV-19', 'route' => 'Governance/sensitive_fields.php', 'table' => 'scr_sensitive_fields',
    'anchor' => '<table class="alltables display" id="sensitive_fieldsTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'sensitive_fieldsTable', 'empty' => 'لا سياسة حقل حساس مسجلة بعد',
    'map' => array(
        'معرف السياسة' => 'no_policy',
        'الحقل' => 'field_name',
        'الاسم التقني للحقل' => '+field_key:VARCHAR(120) NULL DEFAULT NULL',
        'الجدول أو الكيان المصدر' => 'table_name',
        'مفتاح الربط مكتمل؟' => '#bound',
        'حالة ربط السياسة' => '#bind_state',
        'السطح المالك' => '+owner_screen:VARCHAR(190) NULL DEFAULT NULL',
        'فئة الحساسية' => 'classification_sensitivity',
        'قاعدة الإظهار' => 'policy_masking',
        'الأدوار التي تراه كاملا' => 'from_visible_to',
        'الأدوار التي تراه مقنعا' => '+masked_roles:VARCHAR(300) NULL DEFAULT NULL',
        'يظهر في التصدير؟' => 'exportable_flag',
        'مرجع الأساس النظامي' => 'basis_statutory',
        'حالة السياسة' => 'status_label',
        'المنشئ' => 'created_by_name',
        'تاريخ الإنشاء' => 'created_at',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* مفتاحُ الربطِ مكتملٌ حين يُعرَف الجدولُ والحقلُ التقنيُّ معًا —
           فسياسةٌ بحقلٍ بلا جدولٍ لا تُنفَّذ على شيء. */
        'bound' => function ($r) {
            $ok = trim((string) $r['table_name']) !== '' && trim((string) $r['field_key']) !== '';
            return $ok ? 'نعم' : 'لا';
        },
        'bind_state' => function ($r) {
            $ok = trim((string) $r['table_name']) !== '' && trim((string) $r['field_key']) !== '';
            return $ok ? 'مربوطة بمفتاح كامل' : 'بانتظار اكتمال المفتاح';
        },
    );
PHP
),

/* ═══ GOV-27 · سجل أنواع المستندات ════════════════════════════════════════ */
array(
    'req' => 'GOV-27', 'route' => 'Governance/doc_types.php', 'table' => 'scr_doc_types',
    'anchor' => '<table class="alltables display" id="doc_typesTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'doc_typesTable', 'empty' => 'لا نوع مستند مسجل بعد',
    'map' => array(
        'كود النوع' => 'code_type',
        'اسم نوع المستند' => 'name_doc',
        'الإدارة المالكة' => 'dept_owning',
        'نمط الترقيم' => 'pattern_numbering',
        'دورية التسلسل' => 'periodicity_sequence',
        'صيغة الرقم' => 'prefix_numbering',
        'آخر رقم مولد' => '+last_number:VARCHAR(60) NULL DEFAULT NULL',
        'حالة النوع' => 'status_label',
        'المنشئ' => 'created_by_name',
        'تاريخ الإنشاء' => 'created_at',
    ),
),

/* ═══ GOV-28 · تفسير مصدر الصلاحية ════════════════════════════════════════ */
array(
    'req' => 'GOV-28', 'route' => 'Governance/perm_explain.php', 'table' => 'scr_perm_explain',
    'anchor' => '<table class="alltables display" id="perm_explainTable">',
    'rows' => "cmp03_store_raw(\$conn, \$CANONICAL, (\$is_super_admin && \$company_id <= 0) ? 0 : \$company_id)",
    'grid_id' => 'perm_explainTable', 'empty' => 'لا استعلام تفسير مسجل بعد',
    'map' => array(
        'معرف الاستعلام' => '#query_id',
        'المستخدم' => 'account_ref',
        'السطح/الفعل' => '#surface_action',
        'النتيجة' => 'result_final',
        'مصدر القرار الأول' => '#first_source',
        'القالب المطبق' => 'grant_source_2',
        'الدور' => '+role_ref:VARCHAR(120) NULL DEFAULT NULL',
        'التفويض الساري' => '+delegation_ref:VARCHAR(120) NULL DEFAULT NULL',
        'قاعدة المنع المطبقة' => 'source_denial',
        'إصدار السياسة وقت القرار' => '+policy_version:VARCHAR(60) NULL DEFAULT NULL',
        'وقت القرار' => 'date_check',
        'مرجع سجل الرفض' => '+denial_log_ref:VARCHAR(120) NULL DEFAULT NULL',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* معرّفُ الاستعلامِ مشتقٌّ من صفِّه — رقمٌ واحدٌ لا رقمان */
        'query_id' => function ($r) { return 'PEX-' . str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT); },
        'surface_action' => function ($r) {
            $s = trim((string) $r['screen_ref']);
            $a = trim((string) $r['action_ref']);
            return ($s !== '' && $a !== '') ? ($s . ' / ' . $a) : ($s !== '' ? $s : $a);
        },
        /* مصدرُ القرارِ الأوّلُ بحكمِه — فمصدرٌ بلا حكمٍ لا يفسّر شيئًا */
        'first_source' => function ($r) {
            $s = trim((string) $r['grant_source_1']);
            $g = trim((string) $r['its_ruling']);
            return ($s !== '' && $g !== '') ? ($s . ' (' . $g . ')') : ($s !== '' ? $s : $g);
        },
    );
PHP
),

),
);
