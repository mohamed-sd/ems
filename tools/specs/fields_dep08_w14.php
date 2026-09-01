<?php
/**
 * tools/specs/fields_dep08_w14.php — مواصفةُ إغلاقِ حقولِ `DEP-08` · أسرةُ `W14`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصدرُ الحاكم**: `09 · 02_تتبع_الحقول` — اسمُ الحقلِ وترتيبُه ونوعُه.
 *   والأداةُ **تردُّ هذه المواصفةَ** إن نقص حقلٌ منطبقٌ أو اختلَّ ترتيبُه.
 *
 * ◆ **قاعدةُ الربطِ ثلاثيّةٌ لا رأيٌ**:
 *   ① عمودٌ قائمٌ معناه معنى الحقلِ ⇒ يُربَط، **والاسمُ المعروضُ اسمُ الورقة**
 *      (وهذا هو `WRONG_LABEL` مصحَّحًا في محلِّه: «العنوان» ⇒ «اسم السياسة»).
 *   ② حقلٌ مشتقٌّ من صفوفٍ مقروءةٍ أصلًا ⇒ `#`، **ولا عمودَ لمشتقٍّ**
 *      (عمودٌ لمشتقٍّ يخلق مصدرَ حقيقةٍ ثانيًا).
 *   ③ ما لا نظيرَ له ⇒ `+col:TYPE` عمودٌ بهجرةٍ واسمُ الحقلِ في تعليقِه.
 *
 * ◆ ⛔ **وما ليس في الورقةِ لا يُعرَض** — فالأعمدةُ التي كانت تُعرَض بلا حكمٍ
 *   (‏«موعد المراجعة» · «رمز التعارض» بصيغتِه القديمة) إمّا رُدَّت إلى اسمِ
 *   حقلٍ في الورقةِ أو خرجت. وذاك `UNRULED_EXTRA_FIELD = 0` بالبناء.
 *
 * ◆ **ولوحةُ `GOV-01` ليست هنا**: حبّتُها «مؤشر × فترة» وكانت تقرأ سجلَّ
 *   الإخلالات — فتصحيحُها تغييرُ **مصدرِ القراءة** لا خريطةَ أعمدة، ويُعالَج
 *   في ملفِّه بيدٍ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return array(

'dept' => 'DEP-08',
'screens' => array(

/* ═══ GOV-01 · لوحة الحوكمة والالتزام ═════════════════════════════════════
   ◆ الحبّةُ مؤشرٌ لا حالةُ إخلال — ومصدرُ القراءةِ صُحّح في ملفِّ السطحِ نفسِه. */
array(
    'req' => 'GOV-01', 'route' => 'Governance/gov_board.php', 'table' => 'gov_dashboard_kpi',
    'grid_id' => 'emsList_gov_board', 'empty' => 'لا مؤشر معرف بعد للوحة الحوكمة',
    'map' => array(
        'معرف المؤشر' => 'kpi_id',
        'المؤشر - KPI Catalog' => 'kpi_ref',
        'القيمة' => '#value',
        'الوحدة' => 'uom',
        'الحالة' => '@state',
        'آخر تحديث' => 'updated_on',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* القيمةُ رقمٌ مقيسٌ — والصفرُ قيمةٌ والفراغُ غياب */
        'value' => function ($r) {
            $v = $r['value'];
            return ($v === null || (string) $v === '') ? '' : ems_w14_num($v);
        },
    );
PHP
),

/* ═══ GOV-03 · سجل السياسات ═══════════════════════════════════════════════ */
array(
    'req' => 'GOV-03', 'route' => 'Governance/policies.php', 'table' => 'gov_policy',
    'grid_id' => 'emsList_policies', 'empty' => 'لا سياسات مسجلة',
    'map' => array(
        'كود السياسة' => 'policy_no',
        'اسم السياسة' => 'title_ar',
        'النطاق' => 'domain_ar',
        'الإصدار النافذ' => 'version_no',
        'مالك السياسة' => '#owner',
        'تاريخ النفاذ' => 'effective_from',
        'دورية المراجعة' => 'review_periodicity',
        'آخر مراجعة' => '#last_review',
        'الوثيقة المرفقة' => 'doc_ref',
        'قواعد المنع المستندة' => '#guards',
        'حالة السياسة' => '@state',
        'المنشئ' => '#author',
        'تاريخ الإنشاء' => 'created_at',
        'المراجع' => '#reviewer',
        'المعتمد' => '#approver',
        'تاريخ الاعتماد' => 'approved_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* مالكُ السياسةِ: الإدارةُ ومن يحملها — والاثنان معًا لا أحدُهما */
        'owner' => function ($r) {
            $d = trim((string) $r['owner_dept']);
            $p = ems_w14_person($r['owner_person']);
            return ($d !== '' && $p !== '') ? ($d . ' / ' . $p) : ($d !== '' ? $d : $p);
        },
        /* آخرُ مراجعةٍ: اعتمادُ أحدثِ إصدارٍ سابقٍ للسياسةِ نفسِها — مشتقٌّ من
           الصفوفِ المقروءةِ لا من عمودٍ ثانٍ يُنشأ. */
        'last_review' => function ($r) use ($rows) {
            $best = '';
            foreach ($rows as $o) {
                if ((string) $o['policy_no'] !== (string) $r['policy_no']) { continue; }
                if ((int) $o['version_no'] >= (int) $r['version_no']) { continue; }
                $a = trim((string) $o['approved_at']);
                if ($a !== '' && $a > $best) { $best = $a; }
            }
            return $best;
        },
        'guards'   => function ($r) { return ems_w14_guard_count($r['policy_no']); },
        'author'   => function ($r) { return ems_w14_person($r['authored_by']); },
        'reviewer' => function ($r) { return ems_w14_person($r['reviewed_by']); },
        'approver' => function ($r) { return ems_w14_person($r['approved_by']); },
    );
PHP
),

/* ═══ GOV-05 · تقويم الامتثال ═════════════════════════════════════════════ */
array(
    'req' => 'GOV-05', 'route' => 'Governance/compliance_calendar.php',
    'table' => 'gov_compliance_due',
    'grid_id' => 'emsList_compliance_calendar', 'empty' => 'لا استحقاقات',
    'map' => array(
        'معرف الاستحقاق' => '+due_no:VARCHAR(40) NULL DEFAULT NULL',
        'مصدر الاستحقاق' => 'derived_from',
        'البند' => 'obligation_no',
        'الكيان' => '#entity',
        'تاريخ الاستحقاق' => 'due_date',
        'المسؤول' => '#owner',
        'أيام متبقية/تأخير' => '#days',
        'مرجع الإنجاز' => 'settled_ref',
        'الحالة' => '@state',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'entity' => function ($r) { return ems_w14_company($r['company_id']); },
        'owner'  => function ($r) {
            $d = trim((string) $r['owner_dept']);
            $p = ems_w14_person($r['owner_person']);
            return ($d !== '' && $p !== '') ? ($d . ' / ' . $p) : ($d !== '' ? $d : $p);
        },
        /* المهلةُ لا تُحسب على منجَزٍ — والمرجعُ دليلُ الإنجاز */
        'days' => function ($r) {
            return ems_w14_days_to($r['due_date'], trim((string) $r['settled_ref']) !== '');
        },
    );
PHP
),

/* ═══ GOV-08 · التقديمات النظامية ═════════════════════════════════════════ */
array(
    'req' => 'GOV-08', 'route' => 'Governance/regulatory_filings.php', 'table' => 'gov_filing',
    'grid_id' => 'emsList_regulatory_filings', 'empty' => 'لا تقديمات مسجلة',
    'map' => array(
        'معرف التقديم' => 'filing_no',
        'نوع التقديم' => '+filing_kind:VARCHAR(40) NULL DEFAULT NULL',
        'الجهة' => 'authority_ar',
        'الكيان' => '#entity',
        'الفترة المشمولة' => 'period_label',
        'الموعد النظامي' => 'due_date',
        'تاريخ التقديم الفعلي' => 'submitted_at',
        'إيصال/مرجع التقديم' => 'receipt_ref',
        'مرجع الالتزام' => 'obligation_no',
        'حالة التقديم' => '@state',
        'المنشئ' => '#submitter',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'entity'    => function ($r) { return ems_w14_company($r['company_id']); },
        'submitter' => function ($r) { return ems_w14_person($r['submitted_by']); },
    );
PHP
),

/* ═══ GOV-10 · تضارب المصالح ══════════════════════════════════════════════ */
array(
    'req' => 'GOV-10', 'route' => 'Governance/conflict_disclosures.php',
    'table' => 'gov_conflict_disclosure',
    'grid_id' => 'emsList_conflict_disclosures', 'empty' => 'لا إفصاحات مسجلة',
    'map' => array(
        'معرف الإفصاح' => 'disclosure_no',
        'المفصح' => '#person',
        'صفة المفصح' => '#capacity',
        'طبيعة التضارب' => 'nature_ar',
        'الطرف الآخر' => 'counterparty_ar',
        'العلاقة' => '+relation_ar:VARCHAR(200) NULL DEFAULT NULL',
        'القرار المتأثر المحتمل' => 'recused_from',
        'قرار الحوكمة' => '#decision',
        'الضوابط المفروضة' => '+controls_ar:VARCHAR(400) NULL DEFAULT NULL',
        'مراجعة دورية' => '#next_review',
        'حالة الإفصاح' => '@state',
        'تاريخ الإنشاء' => 'disclosed_at',
        'المراجع' => '#assessor',
        'تاريخ الاعتماد' => '+approved_at:DATETIME NULL DEFAULT NULL',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'person'   => function ($r) { return ems_w14_person($r['person_id']); },
        'capacity' => function ($r) { return ems_w14_person_role($r['person_id']); },
        /* القرارُ ومرجعُه معًا — فقرارٌ بلا مرجعٍ لا يُراجَع */
        'decision' => function ($r) {
            $d = trim((string) $r['decision']);
            $d = $d === '' ? '' : ems_w14_ar($d);
            $x = trim((string) $r['decision_ref']);
            return ($d !== '' && $x !== '') ? ($d . ' (' . $x . ')') : ($d !== '' ? $d : $x);
        },
        /* المراجعةُ الدوريّةُ للإفصاحِ القائم: سنةٌ من تاريخِ الإفصاح */
        'next_review' => function ($r) { return ems_w14_year_after($r['disclosed_at']); },
        'assessor' => function ($r) { return ems_w14_person($r['assessed_by']); },
    );
PHP
),

/* ═══ GOV-11 · الأطراف ذات العلاقة ════════════════════════════════════════ */
array(
    'req' => 'GOV-11', 'route' => 'Governance/related_parties.php', 'table' => 'gov_related_party',
    'grid_id' => 'emsList_related_parties', 'empty' => 'لا أطراف ذات علاقة',
    'map' => array(
        'معرف الطرف' => 'party_no',
        'اسم الطرف' => 'party_name',
        'نوع الصلة' => 'relation_ar',
        'الشخص المرتبط داخليا' => '#person',
        'التعاملات القائمة' => 'deal_ref',
        'قيمة التعاملات' => '#amount',
        'شروط التعامل' => 'transaction_type',
        'مرجع إفصاح AAM' => 'disclosure_no',
        'حالة السطر' => '@state',
        'تاريخ الإنشاء' => 'created_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'person' => function ($r) { return ems_w14_person($r['person_id']); },
        /* القيمةُ بعملتِها — ورقمٌ بلا عملةٍ لا يُقارَن */
        'amount' => function ($r) {
            $v = trim((string) $r['deal_amount']);
            if ($v === '' || (float) $v == 0.0) { return ''; }
            return ems_w14_num($v) . ' ' . trim((string) $r['deal_currency']);
        },
    );
PHP
),

/* ═══ GOV-12 · الهدايا والضيافة ═══════════════════════════════════════════ */
array(
    'req' => 'GOV-12', 'route' => 'Governance/gifts_hospitality.php',
    'table' => 'gov_gift_disclosure',
    'grid_id' => 'emsList_gifts_hospitality', 'empty' => 'لا إفصاحات هدايا',
    'map' => array(
        'معرف الإفصاح' => 'gift_no',
        'المفصح' => '#person',
        'الطرف المقدم/المتلقي' => 'giver_ar',
        'الاتجاه' => '+direction:VARCHAR(24) NULL DEFAULT NULL',
        'الوصف' => '+description_ar:VARCHAR(400) NULL DEFAULT NULL',
        'القيمة التقديرية' => '#amount',
        'فوق حد الإفصاح؟' => '#over',
        'السياق' => '+context_ar:VARCHAR(400) NULL DEFAULT NULL',
        'القرار' => '#decision',
        'حالة الإفصاح' => '@state',
        'تاريخ الإنشاء' => 'disclosed_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'person' => function ($r) { return ems_w14_person($r['person_id']); },
        'amount' => function ($r) {
            $v = trim((string) $r['est_value']);
            if ($v === '' || (float) $v == 0.0) { return ''; }
            return ems_w14_num($v) . ' ' . trim((string) $r['currency']);
        },
        /* الحدُّ في السجلِّ لا في الشاشة: `threshold_key` مفتاحُ الحدِّ المنطبق —
           فوجودُه يعني أن الإفصاحَ لزم، وغيابُه يعني أن الحدَّ لم يُحدَّد بعد.
           ⛔ ولا يُخترع رقمُ حدٍّ هنا. */
        'over' => function ($r) {
            $k = trim((string) $r['threshold_key']);
            if ($k === '') { return ''; }
            return ((float) $r['est_value'] > 0) ? 'نعم' : 'لا';
        },
        'decision' => function ($r) {
            $d = trim((string) $r['decision']);
            $d = $d === '' ? '' : ems_w14_ar($d);
            $p = ems_w14_person($r['decided_by']);
            return ($d !== '' && $p !== '') ? ($d . ' / ' . $p) : ($d !== '' ? $d : $p);
        },
    );
PHP
),

/* ═══ GOV-13 · إقرارات مدونة السلوك ═══════════════════════════════════════ */
array(
    'req' => 'GOV-13', 'route' => 'Governance/conduct_acknowledgements.php',
    'table' => 'gov_conduct_ack',
    'grid_id' => 'emsList_conduct_acknowledgements', 'empty' => 'لا إقرارات مسجلة',
    'map' => array(
        'معرف الإقرار' => '+ack_no:VARCHAR(40) NULL DEFAULT NULL',
        'رقم الموظف' => 'employee_id',
        'إصدار المدونة' => 'code_version',
        'قناة الإقرار' => '+ack_channel:VARCHAR(24) NULL DEFAULT NULL',
        'تاريخ الإقرار' => 'acked_at',
        'حالة الإقرار' => '@state',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array();
PHP
),

/* ═══ GOV-16 · فصل الواجبات المتعارضة ═════════════════════════════════════ */
array(
    'req' => 'GOV-16', 'route' => 'Governance/sod_conflicts.php', 'table' => 'gov_sod_conflict',
    'grid_id' => 'emsList_sod_conflicts', 'empty' => 'لا تعارضات معرفة',
    'map' => array(
        'معرف التعارض' => 'conflict_code',
        'العملية الحرجة' => '#process',
        'الطرف الأول (دور/فعل)' => 'side_a',
        'الطرف الثاني (دور/فعل)' => 'side_b',
        'درجة الخطورة' => '+severity:VARCHAR(24) NULL DEFAULT NULL',
        'مستخدمون اجتمع لديهم الطرفان' => '#detected',
        'قرار المعالجة' => '+treatment_decision:VARCHAR(24) NULL DEFAULT NULL',
        'الضابط التعويضي المعلن' => 'mitigation_ar',
        'مرجع الاستثناء' => 'exception_no',
        'حالة التعارض' => '@state',
        'تاريخ الإنشاء' => 'detected_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* العمليةُ الحرجةُ باسمِها إن كُتب، وبمفتاحِها إن لم يُكتب */
        'process' => function ($r) {
            $t = trim((string) $r['title_ar']);
            return $t !== '' ? $t : trim((string) $r['process_key']);
        },
        /* من اجتمع لديه الطرفان: الشخصُ بصفتِه — لا رقمُ دورٍ عاريًا */
        'detected' => function ($r) {
            $p = ems_w14_person($r['detected_user_id']);
            $c = ems_w14_person_role($r['detected_user_id']);
            return ($p !== '' && $c !== '') ? ($p . ' / ' . $c) : ($p !== '' ? $p : $c);
        },
    );
PHP
),

/* ═══ GOV-22 · بلاغات النزاهة المحمية ═════════════════════════════════════ */
array(
    'req' => 'GOV-22', 'route' => 'Governance/integrity_reports.php',
    'table' => 'gov_integrity_report',
    'grid_id' => 'emsList_integrity_reports', 'empty' => 'لا بلاغات مسجلة',
    'map' => array(
        'رقم البلاغ' => 'report_no',
        'قناة الورود' => '@channel',
        'هوية المبلغ - مقيدة' => '#reporter',
        'موضوع البلاغ' => 'subject_ar',
        'الجهة/الشخص المعني' => 'referred_to',
        'الوصف المقيد' => '+description_ar:VARCHAR(600) NULL DEFAULT NULL',
        'التقييم الأولي' => '#triage',
        'مرجع التحقيق' => 'investigation_no',
        'إجراء الحماية' => '#protection',
        'حالة البلاغ' => '@state',
        'تاريخ الإنشاء' => 'received_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* الهويّةُ مقيَّدةٌ بنصِّ الورقة: الرمزُ يُعرَض والاسمُ لا يُكشف هنا —
           والمجهولُ يُعلَن مجهولًا لا فارغًا. */
        'reporter' => function ($r) {
            if ((int) $r['is_anonymous'] === 1) { return 'بلاغ مجهول المصدر'; }
            $t = trim((string) $r['reporter_token']);
            return $t !== '' ? ('رمز المبلغ ' . $t) : '';
        },
        'triage' => function ($r) {
            $p = ems_w14_person($r['triage_by']);
            $a = trim((string) $r['triage_at']);
            return ($p !== '' && $a !== '') ? ($p . ' / ' . $a) : ($p !== '' ? $p : $a);
        },
        'protection' => function ($r) {
            return ((int) $r['retaliation_flag'] === 1) ? 'حماية من الانتقام مفعلة' : '';
        },
    );
PHP
),

/* ═══ GOV-23 · التحقيقات ══════════════════════════════════════════════════ */
array(
    'req' => 'GOV-23', 'route' => 'Governance/investigations.php', 'table' => 'gov_investigation',
    'grid_id' => 'emsList_investigations', 'empty' => 'لا تحقيقات مسجلة',
    'map' => array(
        'رقم التحقيق' => 'inv_no',
        'مصدر التحقيق' => '@origin',
        'مرجع المصدر' => 'origin_ref',
        'المحقق/اللجنة' => '#investigator',
        'نطاق التحقيق' => 'scope_ar',
        'مستوى السرية' => '+confidentiality:VARCHAR(24) NULL DEFAULT NULL',
        'المدة المقررة' => '+due_period:VARCHAR(40) NULL DEFAULT NULL',
        'النتيجة' => 'conclusion_ar',
        'التوصيات' => '+recommendations_ar:VARCHAR(600) NULL DEFAULT NULL',
        'الإحالات المتفرعة' => 'referred_to',
        'حالة التحقيق' => '@state',
        'تاريخ الإنشاء' => 'created_at',
        'المراجع' => '#concluder',
        'تاريخ الاعتماد' => 'concluded_at',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* المحقّقُ شخصٌ أو لجنةٌ بتكليفِها المكتوب — والتكليفُ شرطُ الفتح */
        'investigator' => function ($r) {
            $p = ems_w14_person($r['investigator_id']);
            $m = trim((string) $r['mandate_doc_ref']);
            return ($p !== '' && $m !== '') ? ($p . ' (' . $m . ')') : ($p !== '' ? $p : $m);
        },
        'concluder' => function ($r) { return ems_w14_person($r['concluded_by']); },
    );
PHP
),

/* ═══ GOV-24 · سجل الإخلالات ══════════════════════════════════════════════ */
array(
    'req' => 'GOV-24', 'route' => 'Governance/breaches.php', 'table' => 'gov_breach',
    'grid_id' => 'emsList_breaches', 'empty' => 'لا إخلالات مسجلة',
    'map' => array(
        'معرف الإخلال' => 'case_no',
        'القاعدة/الالتزام المخل به' => '#rule',
        'مصدر الرصد' => '@opened_basis',
        'الجهة المخلة' => '#opener',
        'وصف الواقعة' => 'title_ar',
        'الأثر المقدر' => '+impact_ar:VARCHAR(400) NULL DEFAULT NULL',
        'درجة الخطورة' => '@severity',
        'الإجراء التصحيحي المتفرع' => 'action_no',
        'تصعيد؟' => '#escalated',
        'حالة الإخلال' => '@state',
        'تاريخ الإنشاء' => 'created_at',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* القاعدةُ المكسورةُ: الضابطُ أوّلًا فالسياسةُ فالالتزام — ولا يُفتح بلا ضابط */
        'rule' => function ($r) {
            foreach (array('control_ref', 'policy_no', 'obligation_no', 'deviation_no') as $k) {
                $v = trim((string) $r[$k]);
                if ($v !== '') { return $v; }
            }
            return '';
        },
        'opener' => function ($r) { return ems_w14_person($r['opened_by']); },
        /* التصعيدُ واقعةٌ مقيسة: فُتِح تحقيقٌ متفرّعٌ عن الإخلال */
        'escalated' => function ($r) {
            return trim((string) $r['investigation_no']) !== '' ? 'نعم' : 'لا';
        },
    );
PHP
),

/* ═══ GOV-25 · الإجراءات التصحيحية ════════════════════════════════════════ */
array(
    'req' => 'GOV-25', 'route' => 'Governance/corrective_actions.php',
    'table' => 'gov_corrective_action',
    'grid_id' => 'emsList_corrective_actions', 'empty' => 'لا إجراءات مسجلة',
    'map' => array(
        'معرف الإجراء' => 'action_no',
        'مصدر الإجراء' => '@source_kind',
        'مرجع المصدر' => 'source_ref',
        'وصف الإجراء' => 'title_ar',
        'المالك' => '#owner',
        'الإدارة' => 'owner_dept',
        'المهلة' => 'due_date',
        'أيام التأخير' => '#days',
        'دليل الإغلاق' => 'evidence_ref',
        'تحقق الفاعلية' => '#verified',
        'حالة الإجراء' => '@state',
        'المنشئ' => '#assigner',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'owner' => function ($r) { return ems_w14_person($r['owner_person']); },
        'days'  => function ($r) {
            return ems_w14_days_to($r['due_date'], trim((string) $r['verified_at']) !== '');
        },
        /* الفاعليّةُ تُتحقَّق بمن ومتى — لا بعلامةٍ مفردة */
        'verified' => function ($r) {
            $p = ems_w14_person($r['verified_by']);
            $a = trim((string) $r['verified_at']);
            return ($p !== '' && $a !== '') ? ($p . ' / ' . $a) : ($p !== '' ? $p : $a);
        },
        'assigner' => function ($r) { return ems_w14_person($r['assigned_by']); },
    );
PHP
),

/* ═══ GOV-26 · متابعة نتائج المراجعة ══════════════════════════════════════ */
array(
    'req' => 'GOV-26', 'route' => 'Governance/audit_followup.php', 'table' => 'gov_audit_followup',
    'grid_id' => 'emsList_audit_followup', 'empty' => 'لا متابعات مسجلة',
    'map' => array(
        'معرف النتيجة' => 'followup_no',
        'جهة المراجعة' => '@finding_source',
        'مرجع التقرير' => 'finding_no',
        'النتيجة' => '+finding_ar:VARCHAR(600) NULL DEFAULT NULL',
        'التصنيف' => '+finding_class:VARCHAR(40) NULL DEFAULT NULL',
        'متكررة؟' => '#recurring',
        'خطة الإدارة' => 'mgmt_plan_ar',
        'المالك' => 'plan_owner_dept',
        'المهلة' => 'plan_due',
        'مرجع الإجراء التصحيحي' => 'action_no',
        'حالة النتيجة' => '@follow_state',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        /* التكرارُ رقمٌ في السجلِّ — والصفرُ ليس تكرارًا */
        'recurring' => function ($r) {
            $n = (int) $r['recurrence_no'];
            return $n > 1 ? ('نعم - ' . $n . ' مرات') : ($n === 1 ? 'لا' : '');
        },
    );
PHP
),

/* ═══ GOV-30 · اللجان وحوكمة الاجتماعات ═══════════════════════════════════ */
array(
    'req' => 'GOV-30', 'route' => 'Governance/committees.php', 'table' => 'gov_committee',
    'grid_id' => 'emsList_committees', 'empty' => 'لا لجان مسجلة',
    'map' => array(
        'كود اللجنة' => 'committee_code',
        'اسم اللجنة' => 'name_ar',
        'الاختصاص' => 'mandate_ar',
        'التشكيل' => '#members',
        'الرئيس' => '#chair',
        'النصاب' => 'quorum_key',
        'دورية الانعقاد' => '@meeting_cycle',
        'مرجع قرار التشكيل' => 'charter_ref',
        'حالة اللجنة' => '@state',
        'مرجع المصدر' => 'src_ref',
    ),
    'derive' => <<<'PHP'
    $D = array(
        'members' => function ($r) {
            $n = (int) $r['member_count'];
            return $n > 0 ? ($n . ' عضوا') : '';
        },
        'chair' => function ($r) { return ems_w14_person($r['chair_person']); },
    );
PHP
),

),
);
