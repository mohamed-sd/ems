<?php
/**
 * tools/repair01_w14_screens.php — مولِّدُ أسطحِ المرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولِّدُ ملفّاتٍ لا مولِّدُ ترتيب**: يكتب ملفَّ الشاشةِ على القرصِ بقالبٍ
 *   واحدٍ لكلِّ أسطحِ الموجة — فلا يتفرَّق حارسٌ ولا بوّابةُ عزلٍ بين واحدٍ
 *   وآخر. ⛔ **ولا يكتب بندَ قائمةٍ ولا ترتيبًا** — ذاك من السجلِّ في
 *   `repair01_w14_apply.php` وحدَه.
 *
 * ◆ **وكلُّ سطحٍ يمسُّ جدولَه بالاسمِ مقتبَسًا** — فمِسبارُ المِرساةِ في
 *   `repair01_w14_prove_anchor` يثبته من القرصِ لا من الدعوى.
 *
 * ◆ **والقراءةُ كلُّها عبرَ بوّابةِ العزل** (`w14_rows`) — ⛔ لا استعلامَ خامٌّ
 *   في سطحٍ جديد (`FR-SEC-006` · `GAP-29`).
 *
 * ◆ **ونقاءُ لغةِ الواجهةِ شرطُ توليدٍ لا مراجعةٌ لاحقة** (‏قيدُ المالك §٨):
 *   لا اسمَ جدولٍ ولا مفتاحٍ ولا مصطلحٍ تقنيٍّ في نصٍّ مُصيَّر، ولا تشكيلَ،
 *   ولا نقطتَين في اسمِ عنصر.
 *
 * التشغيل: php tools/repair01_w14_screens.php [--force]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$FORCE = in_array('--force', $argv, true);

/* عمودٌ يُعرَض: [اسم العمود، المسمّى العربي، النوع]
   النوع: t نصّ · i عدد صحيح · n عدد عشريّ · s رمز حالة يُعرَض من القاموس */
$SCREENS = array(

/* ══════════════ الحوكمةُ والالتزام ══════════════════════════════════════ */

'Governance/gov_board.php' => array(
    'title' => 'لوحة الحوكمة والالتزام', 'icon' => 'fa fa-scale-balanced',
    'table' => 'gov_breach', 'order' => 'id DESC', 'limit' => 300,
    'back'  => array('../main/dashboard.php', 'الرئيسية'),
    'note'  => 'قراءة حية مشتقة من حالات الحوكمة والاجراءات ولا ادخال فيها',
    'empty' => array('لا حالات حوكمة مسجلة', 'اللوحة قراءة مشتقة لا شاشة ادخال'),
    'cards' => array(
        array('count', '', '', 'عدد حالات الحوكمة'),
        array('eq', 'state', 'opened', 'حالات مفتوحة'),
        array('eq', 'severity', 'critical', 'حالات حرجة'),
        array('filled', 'action_no', '', 'حالات لها إجراء تصحيحي'),
    ),
    'cols' => array(
        array('case_no', 'رقم الحالة', 't'),
        array('title_ar', 'الموضوع', 't'),
        array('opened_basis', 'أساس الفتح', 's'),
        array('severity', 'الخطورة', 's'),
        array('control_ref', 'الضابط المكسور', 't'),
        array('action_no', 'الإجراء التصحيحي', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/policies.php' => array(
    'title' => 'سجل السياسات', 'icon' => 'fa fa-book',
    'table' => 'gov_policy', 'order' => 'policy_no, version_no DESC', 'limit' => 500,
    'back'  => array('entities_registry.php', 'سجل الشركات والكيانات'),
    'note'  => 'كل قاعدة منع وكل مسار اعتماد يستند لسياسة نافذة بإصدارها ولا سياسة بلا مالك',
    'empty' => array('لا سياسات مسجلة', 'السياسة إصدار بمالكه لا نص متداول'),
    'cards' => array(
        array('count', '', '', 'عدد إصدارات السياسات'),
        array('eq', 'state', 'effective', 'سياسات نافذة'),
        array('eq', 'state', 'draft', 'مسودات'),
        array('filled', 'review_due', '', 'سياسات لها موعد مراجعة'),
    ),
    'cols' => array(
        array('policy_no', 'رقم السياسة', 't'),
        array('version_no', 'الإصدار', 'i'),
        array('title_ar', 'العنوان', 't'),
        array('domain_ar', 'المجال', 't'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('effective_from', 'تاريخ النفاذ', 't'),
        array('review_due', 'موعد المراجعة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/obligations.php' => array(
    'title' => 'الالتزامات التنظيمية', 'icon' => 'fa fa-gavel',
    'table' => 'gov_obligation', 'order' => 'next_due, obligation_no', 'limit' => 500,
    'back'  => array('policies.php', 'سجل السياسات'),
    'note'  => 'كل التزام على الشركة مسجل بجهته ودوريته ومالكه وهو أساس تقويم الامتثال',
    'empty' => array('لا التزامات مسجلة', 'الالتزام سطر بجهته ودوريته لا تذكرة'),
    'cards' => array(
        array('count', '', '', 'عدد الالتزامات'),
        array('eq', 'state', 'monitored', 'التزامات قيد المراقبة'),
        array('eq', 'state', 'breached', 'التزامات مخالفة'),
        array('distinct', 'authority_ar', '', 'الجهات المفروض منها'),
    ),
    'cols' => array(
        array('obligation_no', 'رقم الالتزام', 't'),
        array('title_ar', 'الالتزام', 't'),
        array('authority_ar', 'الجهة', 't'),
        array('periodicity', 'الدورية', 's'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('next_due', 'الاستحقاق التالي', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/compliance_calendar.php' => array(
    'title' => 'تقويم الامتثال', 'icon' => 'fa fa-calendar-check',
    'table' => 'gov_compliance_due', 'order' => 'due_date, obligation_no', 'limit' => 800,
    'back'  => array('obligations.php', 'الالتزامات التنظيمية'),
    'note'  => 'مشتق من الالتزامات والتراخيص والإقرارات وما تأخر يعلم ولا إدخال حر فيه',
    'empty' => array('لا استحقاقات', 'التقويم مشتق من الالتزام لا مكتوب يدويا'),
    'cards' => array(
        array('count', '', '', 'عدد الاستحقاقات'),
        array('eq', 'state', 'due', 'استحقاقات قائمة'),
        array('eq', 'state', 'late', 'استحقاقات متأخرة'),
        array('distinct', 'owner_dept', '', 'الإدارات المسؤولة'),
    ),
    'cols' => array(
        array('obligation_no', 'الالتزام', 't'),
        array('due_date', 'تاريخ الاستحقاق', 't'),
        array('owner_dept', 'الإدارة المسؤولة', 't'),
        array('derived_from', 'مصدر الاشتقاق', 't'),
        array('settled_ref', 'مرجع التنفيذ', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/regulatory_filings.php' => array(
    'title' => 'التقديمات النظامية', 'icon' => 'fa fa-file-arrow-up',
    'table' => 'gov_filing', 'order' => 'due_date DESC, filing_no', 'limit' => 500,
    'back'  => array('licenses_guarantees.php', 'التراخيص والكفالات'),
    'note'  => 'كل إقرار وتقديم نظامي بموعده وإيصاله والمتأخر يعلم ويصعد',
    'empty' => array('لا تقديمات مسجلة', 'التقديم سطر بإيصاله لا خانة تعليم'),
    'cards' => array(
        array('count', '', '', 'عدد التقديمات'),
        array('eq', 'state', 'acknowledged', 'تقديمات باستلام موثق'),
        array('eq', 'state', 'late', 'تقديمات متأخرة'),
        array('distinct', 'authority_ar', '', 'الجهات المستقبلة'),
    ),
    'cols' => array(
        array('filing_no', 'رقم التقديم', 't'),
        array('obligation_no', 'الالتزام', 't'),
        array('authority_ar', 'الجهة', 't'),
        array('period_label', 'الفترة', 't'),
        array('due_date', 'الموعد', 't'),
        array('submitted_at', 'تاريخ التقديم', 't'),
        array('receipt_ref', 'الإيصال', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/sod_conflicts.php' => array(
    'title' => 'فصل الواجبات المتعارضة', 'icon' => 'fa fa-code-branch',
    'table' => 'gov_sod_conflict', 'order' => 'conflict_code, id DESC', 'limit' => 500,
    'back'  => array('auth_profiles.php', 'الأدوار وقوالب صلاحياتها'),
    'note'  => 'التعارض يعرف مرة ويكشف دوما ولا يجمع فاعل واحد طرفي عملية واحدة',
    'empty' => array('لا تعارضات معرفة', 'التعارض قاعدة تعرف مرة لا ملاحظة عابرة'),
    'cards' => array(
        array('count', '', '', 'عدد التعارضات'),
        array('eq', 'state', 'detected', 'تعارضات مكتشفة'),
        array('eq', 'state', 'accepted', 'تعارضات مقبولة باستثناء'),
        array('distinct', 'process_key', '', 'العمليات المشمولة'),
    ),
    'cols' => array(
        array('conflict_code', 'رمز التعارض', 't'),
        array('title_ar', 'التعارض', 't'),
        array('side_a', 'الطرف الأول', 't'),
        array('side_b', 'الطرف الثاني', 't'),
        array('detected_role_id', 'الدور المكتشف', 'i'),
        array('detected_user_id', 'المستخدم المكتشف', 'i'),
        array('exception_no', 'الاستثناء', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/conflict_disclosures.php' => array(
    'title' => 'تضارب المصالح', 'icon' => 'fa fa-user-shield',
    'table' => 'gov_conflict_disclosure', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('auth_profiles.php', 'الأدوار وقوالب صلاحياتها'),
    'note'  => 'الإفصاح واجب والقرار للحوكمة ولا يشارك صاحب الإفصاح في قرار محل التضارب',
    'empty' => array('لا إفصاحات مسجلة', 'الإفصاح سطر بقراره لا استمارة تحفظ'),
    'cards' => array(
        array('count', '', '', 'عدد الإفصاحات'),
        array('eq', 'state', 'disclosed', 'إفصاحات قيد التقييم'),
        array('eq', 'state', 'recused', 'حالات تجنيب'),
        array('eq', 'state', 'rejected', 'حالات مرفوضة'),
    ),
    'cols' => array(
        array('disclosure_no', 'رقم الإفصاح', 't'),
        array('person_id', 'صاحب الإفصاح', 'i'),
        array('nature_ar', 'طبيعة التضارب', 't'),
        array('counterparty_ar', 'الطرف المقابل', 't'),
        array('assessed_by', 'المقيم', 'i'),
        array('decision', 'القرار', 's'),
        array('recused_from', 'التجنيب عن', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/related_parties.php' => array(
    'title' => 'الأطراف ذات العلاقة', 'icon' => 'fa fa-handshake',
    'table' => 'gov_related_party', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('conflict_disclosures.php', 'تضارب المصالح'),
    'note'  => 'كل تعامل مع طرف ذي علاقة يمر بإفصاح إلزامي ويوسم بين الكيانات منذ إنشائه',
    'empty' => array('لا أطراف ذات علاقة', 'الطرف سطر بتعامله لا اسم في ملاحظة'),
    'cards' => array(
        array('count', '', '', 'عدد الأطراف'),
        array('eq', 'intercompany_flag', '1', 'تعاملات بين كيانات المجموعة'),
        array('filled', 'disclosure_no', '', 'تعاملات لها إفصاح'),
        array('eq', 'state', 'active', 'أطراف نشطة'),
    ),
    'cols' => array(
        array('party_no', 'رقم الطرف', 't'),
        array('party_name', 'اسم الطرف', 't'),
        array('relation_ar', 'صفة العلاقة', 't'),
        array('deal_ref', 'مرجع التعامل', 't'),
        array('deal_amount', 'قيمة التعامل', 'n'),
        array('disclosure_no', 'الإفصاح', 't'),
        array('intercompany_flag', 'بين كيانات المجموعة', 'i'),
        array('transaction_type', 'نوع المعاملة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/gifts_hospitality.php' => array(
    'title' => 'الهدايا والضيافة', 'icon' => 'fa fa-gift',
    'table' => 'gov_gift_disclosure', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('conflict_disclosures.php', 'تضارب المصالح'),
    'note'  => 'الإفصاح فوق الحد المضبوط إلزامي والقبول أو الرد بقرار وفق السياسة',
    'empty' => array('لا إفصاحات هدايا', 'الإفصاح سطر بقراره لا خانة تعليم'),
    'cards' => array(
        array('count', '', '', 'عدد الإفصاحات'),
        array('eq', 'state', 'accepted', 'هدايا مقبولة'),
        array('eq', 'state', 'returned', 'هدايا مردودة'),
        array('distinct', 'person_id', '', 'الأشخاص المفصحون'),
    ),
    'cols' => array(
        array('gift_no', 'رقم الإفصاح', 't'),
        array('person_id', 'المفصح', 'i'),
        array('gift_kind', 'النوع', 's'),
        array('giver_ar', 'الجهة المانحة', 't'),
        array('est_value', 'القيمة التقديرية', 'n'),
        array('currency', 'العملة', 't'),
        array('decision', 'القرار', 's'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/conduct_acknowledgements.php' => array(
    'title' => 'إقرارات مدونة السلوك', 'icon' => 'fa fa-file-signature',
    'table' => 'gov_conduct_ack', 'order' => 'code_version DESC, employee_id', 'limit' => 800,
    'back'  => array('policies.php', 'سجل السياسات'),
    'note'  => 'كل موظف يقر بمدونة السلوك عند التعيين وعند كل إصدار جديد والناقص يعلم',
    'empty' => array('لا إقرارات مسجلة', 'الإقرار سطر بدليله لا خانة اختيار'),
    'cards' => array(
        array('count', '', '', 'عدد الإقرارات'),
        array('eq', 'state', 'acknowledged', 'إقرارات مكتملة'),
        array('eq', 'state', 'overdue', 'إقرارات متأخرة'),
        array('distinct', 'code_version', '', 'الإصدارات المشمولة'),
    ),
    'cols' => array(
        array('employee_id', 'الموظف', 'i'),
        array('code_version', 'إصدار المدونة', 't'),
        array('policy_no', 'السياسة', 't'),
        array('due_date', 'الموعد', 't'),
        array('acked_at', 'تاريخ الإقرار', 't'),
        array('evidence_ref', 'الدليل', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/approval_ladders.php' => array(
    'title' => 'سلاليم الاعتماد', 'icon' => 'fa fa-stairs',
    'table' => 'gov_ladders', 'order' => 'ladder_code', 'limit' => 500,
    'back'  => array('authority_caps.php', 'سقوف الصلاحية'),
    'note'  => 'السلم يعرف هنا بمستوياته وشروط انتقاله ويقرأ في محرك الاعتماد',
    'empty' => array('لا سلاليم معرفة', 'السلم تعريف بمستوياته لا رقم في شاشة'),
    'cards' => array(
        array('count', '', '', 'عدد السلاليم'),
        array('eq', 'is_active', '1', 'سلاليم نافذة'),
        array('eq', 'cap_state', 'unresolved', 'سلاليم بسقف غير محسوم'),
        array('distinct', 'entity_type', '', 'الكيانات المشمولة'),
    ),
    'cols' => array(
        array('ladder_code', 'رمز السلم', 't'),
        array('name_ar', 'الاسم', 't'),
        array('entity_type', 'الكيان', 't'),
        array('action', 'الإجراء', 't'),
        array('cap_kind', 'نوع السقف', 's'),
        array('cap_amount', 'قيمة السقف', 'n'),
        array('cap_state', 'حالة السقف', 's'),
        array('doc_ref', 'مرجع الوثيقة', 't'),
    ),
),

'Governance/integrity_reports.php' => array(
    'title' => 'بلاغات النزاهة المحمية', 'icon' => 'fa fa-shield-halved',
    'table' => 'gov_integrity_report', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('exceptions.php', 'طلبات الاستثناء'),
    'note'  => 'قناة محمية بسرية مشددة وهوية المبلغ محجوبة إلا لمستوى مخول ولا انتقام',
    'empty' => array('لا بلاغات مسجلة', 'البلاغ سطر برمزه لا اسم يعرض'),
    'cards' => array(
        array('count', '', '', 'عدد البلاغات'),
        array('eq', 'is_anonymous', '1', 'بلاغات بهوية محجوبة'),
        array('eq', 'state', 'received', 'بلاغات قبل الفرز'),
        array('eq', 'state', 'referred', 'بلاغات محالة'),
    ),
    'cols' => array(
        array('report_no', 'رقم البلاغ', 't'),
        array('channel', 'القناة', 't'),
        array('is_anonymous', 'هوية محجوبة', 'i'),
        array('subject_ar', 'الموضوع', 't'),
        array('received_at', 'تاريخ الاستلام', 't'),
        array('triage_at', 'تاريخ الفرز', 't'),
        array('referred_to', 'أحيل إلى', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/investigations.php' => array(
    'title' => 'التحقيقات', 'icon' => 'fa fa-magnifying-glass',
    'table' => 'gov_investigation', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('integrity_reports.php', 'بلاغات النزاهة المحمية'),
    'note'  => 'التحقيق بتكليف وصلاحيات ونطاق ولكل نوع مالكه والنتيجة ترفع لجهة أثرها',
    'empty' => array('لا تحقيقات مسجلة', 'التحقيق ملف بتكليفه لا محضر متداول'),
    'cards' => array(
        array('count', '', '', 'عدد التحقيقات'),
        array('eq', 'inv_kind', 'INTEGRITY', 'تحقيقات نزاهة'),
        array('eq', 'inv_kind', 'SPECIAL_INDEPENDENT', 'تحقيقات مستقلة بتكليف'),
        array('eq', 'conflict_flag', '1', 'تحقيقات بها تعارض معلن'),
    ),
    'cols' => array(
        array('inv_no', 'رقم التحقيق', 't'),
        array('inv_kind', 'النوع', 's'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('origin', 'المصدر', 's'),
        array('mandate_doc_ref', 'التكليف المكتوب', 't'),
        array('investigator_id', 'المحقق', 'i'),
        array('recusal_of', 'التنحي عن', 't'),
        array('referred_to', 'أحيل إلى', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/breaches.php' => array(
    'title' => 'سجل الإخلالات', 'icon' => 'fa fa-triangle-exclamation',
    'table' => 'gov_breach', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('investigations.php', 'التحقيقات'),
    'note'  => 'كل إخلال بقاعدة أو التزام يسجل بأثره ومعالجته ولا يغلق بلا إجراء ودليل',
    'empty' => array('لا إخلالات مسجلة', 'الإخلال حالة بأساسها لا ملاحظة'),
    'cards' => array(
        array('count', '', '', 'عدد الإخلالات'),
        array('eq', 'state', 'opened', 'إخلالات مفتوحة'),
        array('eq', 'state', 'closed', 'إخلالات مغلقة'),
        array('filled', 'deviation_no', '', 'إخلالات لها انحراف مرجعي'),
    ),
    'cols' => array(
        array('case_no', 'رقم الحالة', 't'),
        array('title_ar', 'الموضوع', 't'),
        array('opened_basis', 'أساس الفتح', 's'),
        array('control_ref', 'الضابط المكسور', 't'),
        array('policy_no', 'السياسة', 't'),
        array('deviation_no', 'الانحراف المرجعي', 't'),
        array('severity', 'الخطورة', 's'),
        array('action_no', 'الإجراء التصحيحي', 't'),
        array('close_evidence', 'دليل الإغلاق', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/corrective_actions.php' => array(
    'title' => 'الإجراءات التصحيحية', 'icon' => 'fa fa-screwdriver-wrench',
    'table' => 'gov_corrective_action', 'order' => 'due_date, action_no', 'limit' => 500,
    'back'  => array('breaches.php', 'سجل الإخلالات'),
    'note'  => 'كل إجراء بمالك ومهلة ودليل إغلاق والمتأخر يتصدر ويصعد',
    'empty' => array('لا إجراءات مسجلة', 'الإجراء سطر بمهلته لا نية مكتوبة'),
    'cards' => array(
        array('count', '', '', 'عدد الإجراءات'),
        array('eq', 'state', 'overdue', 'إجراءات متأخرة'),
        array('eq', 'state', 'verified', 'إجراءات متحقق منها'),
        array('distinct', 'owner_dept', '', 'الإدارات المالكة'),
    ),
    'cols' => array(
        array('action_no', 'رقم الإجراء', 't'),
        array('title_ar', 'الإجراء', 't'),
        array('source_kind', 'المصدر', 's'),
        array('source_ref', 'مرجع المصدر', 't'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('owner_person', 'المسؤول', 'i'),
        array('due_date', 'المهلة', 't'),
        array('evidence_ref', 'دليل الإغلاق', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Governance/audit_followup.php' => array(
    'title' => 'متابعة نتائج المراجعة', 'icon' => 'fa fa-list-check',
    'table' => 'gov_audit_followup', 'order' => 'plan_due, followup_no', 'limit' => 500,
    'back'  => array('corrective_actions.php', 'الإجراءات التصحيحية'),
    'note'  => 'تتابع خطة الإدارة ومهلها والمتكرر يعلم ونتيجة المراجعة تبقى عند المراجعة',
    'empty' => array('لا متابعات مسجلة', 'المتابعة خطة إدارة لا نتيجة مراجعة'),
    'cards' => array(
        array('count', '', '', 'عدد المتابعات'),
        array('eq', 'follow_state', 'overdue', 'متابعات متأخرة'),
        array('eq', 'follow_state', 'escalated', 'متابعات مصعدة'),
        array('eq', 'finding_source', 'external', 'ملاحظات من مراجعة خارجية'),
    ),
    'cols' => array(
        array('followup_no', 'رقم المتابعة', 't'),
        array('finding_no', 'الملاحظة', 't'),
        array('finding_source', 'مصدر الملاحظة', 's'),
        array('mgmt_plan_ar', 'خطة الإدارة', 't'),
        array('plan_owner_dept', 'الإدارة المسؤولة', 't'),
        array('plan_due', 'مهلة الخطة', 't'),
        array('recurrence_no', 'مرات التكرار', 'i'),
        array('follow_state', 'الحالة', 's'),
    ),
),

'Governance/committees.php' => array(
    'title' => 'اللجان وحوكمة الاجتماعات', 'icon' => 'fa fa-users-rectangle',
    'table' => 'gov_committee', 'order' => 'committee_code', 'limit' => 300,
    'back'  => array('doc_types.php', 'سجل أنواع المستندات'),
    'note'  => 'اللجان النافذة بتشكيلها وصلاحياتها ودورية انعقادها',
    'empty' => array('لا لجان مسجلة', 'اللجنة تشكيل بميثاقه لا اسم في محضر'),
    'cards' => array(
        array('count', '', '', 'عدد اللجان'),
        array('eq', 'state', 'active', 'لجان نافذة'),
        array('eq', 'state', 'dissolved', 'لجان منحلة'),
        array('filled', 'charter_ref', '', 'لجان لها ميثاق'),
    ),
    'cols' => array(
        array('committee_code', 'رمز اللجنة', 't'),
        array('name_ar', 'الاسم', 't'),
        array('mandate_ar', 'الاختصاص', 't'),
        array('charter_ref', 'الميثاق', 't'),
        array('chair_person', 'رئيس اللجنة', 'i'),
        array('member_count', 'عدد الأعضاء', 'i'),
        array('meeting_cycle', 'دورية الانعقاد', 's'),
        array('state', 'الحالة', 's'),
    ),
),

/* ══════════════ إدارةُ المخاطر ═════════════════════════════════════════ */

'Risk/risk_taxonomy.php' => array(
    'title' => 'تصنيف المخاطر', 'icon' => 'fa fa-sitemap',
    'table' => 'rsk_taxonomy', 'order' => 'family_code, depth_no, node_code', 'limit' => 500,
    'back'  => array('risk_settings.php', 'إعدادات المخاطر'),
    'note'  => 'الشجرة الحاكمة للعائلات الأربع وكل خطر يسند لعقدة واحدة ولا نص حر',
    'empty' => array('لا عقد تصنيف', 'التصنيف شجرة معتمدة لا قائمة تكتب'),
    'cards' => array(
        array('count', '', '', 'عدد العقد'),
        array('distinct', 'family_code', '', 'العائلات المستعملة'),
        array('eq', 'state', 'active', 'عقد نافذة'),
        array('eq', 'depth_no', '1', 'عقد الجذر'),
    ),
    'cols' => array(
        array('node_code', 'رمز العقدة', 't'),
        array('family_code', 'العائلة', 's'),
        array('category_ar', 'الفئة', 't'),
        array('type_ar', 'النوع', 't'),
        array('parent_code', 'العقدة الأم', 't'),
        array('depth_no', 'المستوى', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Risk/risk_events.php' => array(
    'title' => 'أحداث المخاطر والخسائر', 'icon' => 'fa fa-bolt',
    'table' => 'rsk_event', 'order' => 'occurred_at DESC, id DESC', 'limit' => 500,
    'back'  => array('risk_register.php', 'سجل المخاطر المؤسسي'),
    'note'  => 'الحدث يقرأ مصدره بمرجعه ولا ينسخه والتوقف يسجل في مصدره التشغيلي',
    'empty' => array('لا أحداث مسجلة', 'الحدث مرجع لمصدره لا نسخة منه'),
    'cards' => array(
        array('count', '', '', 'عدد الأحداث'),
        array('eq', 'event_kind', 'loss', 'أحداث خسارة'),
        array('eq', 'event_kind', 'near_miss', 'أحداث كادت تقع'),
        array('filled', 'deviation_no', '', 'أحداث لها انحراف مرجعي'),
    ),
    'cols' => array(
        array('event_no', 'رقم الحدث', 't'),
        array('risk_code', 'الخطر', 't'),
        array('family_code', 'العائلة', 's'),
        array('event_kind', 'نوع الحدث', 's'),
        array('source_module', 'المصدر', 't'),
        array('source_ref', 'مرجع المصدر', 't'),
        array('deviation_no', 'الانحراف المرجعي', 't'),
        array('loss_amount', 'قيمة الخسارة', 'n'),
        array('occurred_at', 'تاريخ الوقوع', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Risk/risk_escalations.php' => array(
    'title' => 'تصعيدات المخاطر', 'icon' => 'fa fa-arrow-up-right-dots',
    'table' => 'risk_escalations', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('risk_acceptance.php', 'قبول المخاطر'),
    'note'  => 'الاختراق الحرج أو خروج الشهية أو تأخر المعالجة الجوهري يصعد بمساره',
    'empty' => array('لا تصعيدات مسجلة', 'التصعيد واقعة بسببها لا تنبيه يمر'),
    'cards' => array(
        array('count', '', '', 'عدد التصعيدات'),
        array('eq', 'is_auto', '1', 'تصعيدات آلية'),
        array('filled', 'acknowledged_by', '', 'تصعيدات مستلمة'),
        array('distinct', 'to_authority', '', 'الجهات المصعد إليها'),
    ),
    'cols' => array(
        array('risk_id', 'الخطر', 'i'),
        array('reason_ar', 'سبب التصعيد', 't'),
        array('to_authority', 'الجهة', 's'),
        array('is_auto', 'آلي', 'i'),
        array('acknowledged_by', 'المستلم', 'i'),
        array('acknowledged_at', 'تاريخ الاستلام', 't'),
        array('created_at', 'تاريخ التصعيد', 't'),
    ),
),

'Risk/risk_closure.php' => array(
    'title' => 'سجل الإغلاق والأدلة', 'icon' => 'fa fa-circle-check',
    'table' => 'rsk_closure', 'order' => 'id DESC', 'limit' => 500,
    'back'  => array('risk_acceptance.php', 'قبول المخاطر'),
    'note'  => 'لا يغلق الخطر إلا بإثبات ومن اقترح الإغلاق لا يعتمده',
    'empty' => array('لا إغلاقات مسجلة', 'الإغلاق إثبات بدليله لا قرار صامت'),
    'cards' => array(
        array('count', '', '', 'عدد الإغلاقات'),
        array('eq', 'state', 'approved', 'إغلاقات معتمدة'),
        array('eq', 'state', 'reopened', 'إغلاقات أعيد فتحها'),
        array('filled', 'evidence_ref', '', 'إغلاقات لها دليل'),
    ),
    'cols' => array(
        array('closure_no', 'رقم الإغلاق', 't'),
        array('risk_code', 'الخطر', 't'),
        array('closure_basis', 'أساس الإغلاق', 's'),
        array('reassessment_ref', 'إعادة التقييم', 't'),
        array('evidence_ref', 'الدليل', 't'),
        array('proposed_by', 'المقترح', 'i'),
        array('approved_by', 'المعتمد', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

/* ══════════════ المراجعةُ الداخليّة ════════════════════════════════════ */

'Audit/iaf_overview.php' => array(
    'title' => 'لوحة المراجعة الداخلية', 'icon' => 'fa fa-clipboard-check',
    'table' => 'iaf_findings', 'order' => 'id DESC', 'limit' => 300,
    'back'  => array('../main/dashboard.php', 'الرئيسية'),
    'note'  => 'قراءة حية مشتقة من الملاحظات ومتابعتها ولا إدخال فيها',
    'empty' => array('لا ملاحظات مسجلة', 'اللوحة قراءة مشتقة لا شاشة إدخال'),
    'cards' => array(
        array('count', '', '', 'عدد الملاحظات'),
        array('eq', 'state', 'open', 'ملاحظات مفتوحة'),
        array('eq', 'severity', 'critical', 'ملاحظات حرجة'),
        array('eq', 'state', 'escalated', 'ملاحظات مصعدة'),
    ),
    'cols' => array(
        array('finding_no', 'رقم الملاحظة', 't'),
        array('title', 'الملاحظة', 't'),
        array('auditee_dept', 'الجهة الخاضعة', 't'),
        array('severity', 'الدرجة', 's'),
        array('action_due', 'مهلة المعالجة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Audit/iaf_audit_programs.php' => array(
    'title' => 'برامج المراجعة', 'icon' => 'fa fa-diagram-project',
    'table' => 'iaf_program', 'order' => 'program_no, step_no', 'limit' => 500,
    'back'  => array('iaf_engagements.php', 'مهام المراجعة'),
    'note'  => 'البرنامج يربط الهدف بالاختبار ولكل خطوة هدفها وأسلوبها وحجم عينتها ومنفذها',
    'empty' => array('لا برامج مسجلة', 'البرنامج خطوات بأهدافها لا قائمة مهام'),
    'cards' => array(
        array('count', '', '', 'عدد خطوات البرامج'),
        array('eq', 'state', 'approved', 'خطوات معتمدة'),
        array('eq', 'state', 'completed', 'خطوات منجزة'),
        array('distinct', 'engagement_no', '', 'المهام المشمولة'),
    ),
    'cols' => array(
        array('program_no', 'رقم البرنامج', 't'),
        array('step_no', 'الخطوة', 'i'),
        array('engagement_no', 'المهمة', 't'),
        array('objective_ar', 'الهدف', 't'),
        array('test_method', 'أسلوب الاختبار', 's'),
        array('population_ar', 'المجتمع', 't'),
        array('sample_size', 'حجم العينة', 'i'),
        array('sampling_basis', 'منهجية السحب', 't'),
        array('performer_id', 'المنفذ', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Audit/iaf_evidence_requests.php' => array(
    'title' => 'طلبات الأدلة', 'icon' => 'fa fa-inbox',
    'table' => 'iaf_evidence_request', 'order' => 'due_date, request_no', 'limit' => 500,
    'back'  => array('iaf_audit_programs.php', 'برامج المراجعة'),
    'note'  => 'الدليل يطلب رسميا بمهلة والتأخر في التزويد واقعة تسجل وتصعَّد',
    'empty' => array('لا طلبات أدلة', 'الطلب سطر بمهلته لا رسالة'),
    'cards' => array(
        array('count', '', '', 'عدد الطلبات'),
        array('eq', 'state', 'overdue', 'طلبات متأخرة'),
        array('eq', 'state', 'escalated', 'طلبات مصعدة'),
        array('distinct', 'auditee_dept', '', 'الجهات الخاضعة'),
    ),
    'cols' => array(
        array('request_no', 'رقم الطلب', 't'),
        array('engagement_no', 'المهمة', 't'),
        array('auditee_dept', 'الجهة الخاضعة', 't'),
        array('item_ar', 'المطلوب', 't'),
        array('due_date', 'المهلة', 't'),
        array('provided_at', 'تاريخ التزويد', 't'),
        array('delay_days', 'أيام التأخر', 'i'),
        array('escalation_level', 'مستوى التصعيد', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Audit/iaf_test_samples.php' => array(
    'title' => 'العينات ونتائج الاختبارات', 'icon' => 'fa fa-vials',
    'table' => 'iaf_sample', 'order' => 'program_no, step_no, sample_no', 'limit' => 800,
    'back'  => array('iaf_audit_programs.php', 'برامج المراجعة'),
    'note'  => 'العينة تسحب بمنهجية معلنة من مجتمع معرَّف وكل مفردة بنتيجتها',
    'empty' => array('لا مفردات عينة', 'المفردة سطر بنتيجتها لا خلاصة'),
    'cards' => array(
        array('count', '', '', 'عدد المفردات'),
        array('eq', 'test_result', 'exception', 'مفردات بها استثناء'),
        array('eq', 'test_result', 'pass', 'مفردات مطابقة'),
        array('filled', 'finding_no', '', 'مفردات أنتجت ملاحظة'),
    ),
    'cols' => array(
        array('sample_no', 'رقم المفردة', 't'),
        array('program_no', 'البرنامج', 't'),
        array('step_no', 'الخطوة', 'i'),
        array('item_ref', 'مرجع المفردة', 't'),
        array('test_result', 'النتيجة', 's'),
        array('exception_ar', 'الاستثناء', 't'),
        array('tested_by', 'المختبر', 'i'),
        array('finding_no', 'الملاحظة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Audit/iaf_function_risks.php' => array(
    'title' => 'مخاطر وظيفة المراجعة', 'icon' => 'fa fa-user-secret',
    'table' => 'iaf_function_risk', 'order' => 'risk_no', 'limit' => 300,
    'back'  => array('iaf_quality.php', 'تقييم الجودة'),
    'note'  => 'مخاطر الوظيفة نفسها ترفع لخط الرفع بالميثاق لا للإدارة التنفيذية',
    'empty' => array('لا مخاطر مسجلة', 'الخطر سطر بمعالجته لا ملاحظة'),
    'cards' => array(
        array('count', '', '', 'عدد المخاطر'),
        array('eq', 'risk_kind', 'INDEPENDENCE_LOSS', 'مخاطر فقد الاستقلال'),
        array('eq', 'state', 'treated', 'مخاطر معالجة'),
        array('filled', 'reported_to', '', 'مخاطر مرفوعة'),
    ),
    'cols' => array(
        array('risk_no', 'رقم الخطر', 't'),
        array('risk_kind', 'النوع', 's'),
        array('title_ar', 'الخطر', 't'),
        array('level_ar', 'المستوى', 't'),
        array('treatment_ar', 'المعالجة', 't'),
        array('reported_to', 'خط الرفع', 's'),
        array('review_due', 'موعد المراجعة', 't'),
        array('state', 'الحالة', 's'),
    ),
),
);

$written = 0; $skipped = 0;
foreach ($SCREENS as $route => $s) {
    $path = $ROOT . '/' . $route;
    if (is_file($path) && !$FORCE) { echo "  ↷ $route قائم\n"; $skipped++; continue; }
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }

    $up = str_repeat('../', substr_count($route, '/'));
    $cards = '';
    foreach ($s['cards'] as $c) {
        switch ($c[0]) {
            case 'count':    $expr = 'count($rows)'; break;
            case 'eq':       $expr = 'ems_w14_count($rows, "' . $c[1] . '", "' . $c[2] . '")'; break;
            case 'distinct': $expr = 'ems_w14_distinct($rows, "' . $c[1] . '")'; break;
            case 'filled':   $expr = 'ems_w14_filled($rows, "' . $c[1] . '")'; break;
            case 'empty':    $expr = 'ems_w14_empty($rows, "' . $c[1] . '")'; break;
            case 'sumf':     $expr = 'ems_w14_num(ems_w14_sumf($rows, "' . $c[1] . '"))'; break;
            default:         $expr = '0';
        }
        $cards .= '        <div class="ems-stat-card"><div class="ems-stat-value"><?= ' . $expr
                . ' ?></div><div class="ems-stat-label">' . $c[3] . "</div></div>\n";
    }
    $head = ''; $body = '';
    foreach ($s['cols'] as $col) {
        $head .= '<th>' . $col[1] . '</th>';
        switch ($col[2]) {
            case 'i': $cell = '(int) $r["' . $col[0] . '"]'; break;
            case 'n': $cell = 'ems_w14_num($r["' . $col[0] . '"])'; break;
            case 's': $cell = 'ems_w14_state((string) $r["' . $col[0] . '"])'; break;
            default:  $cell = 'ems_w14_txt($r["' . $col[0] . '"])';
        }
        $body .= '                    <td><?= ' . $cell . " ?></td>\n";
    }

    $php = "<?php\n"
        . "/**\n"
        . " * " . $route . " — " . $s['title'] . " (RPR-W14)\n"
        . " * ───────────────────────────────────────────────────────────────────────────\n"
        . " * " . $s['note'] . "\n"
        . " *\n"
        . " * ◆ **الحبّةُ `Legal Entity`** (‏`DEC-OPEN-03`): القراءةُ تمرُّ ببوّابةِ المستأجرِ\n"
        . " *   التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر.\n"
        . " *\n"
        . " * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ نطاقِه\n"
        . " *   بحارسِه ورمزِ ردِّه، والشاشةُ تعرض ما وقع. **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد.**\n"
        . " */\n"
        . "require_once __DIR__ . '/" . $up . "includes/session_bootstrap.php';\n"
        . "session_start();\n"
        . "if (!isset(\$_SESSION['user'])) { header('Location: " . $up . "login.php'); exit(); }\n"
        . "include '" . $up . "config.php';\n"
        . "include '" . $up . "includes/permissions_helper.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/w14_view.php';\n"
        . "\n"
        . "\$ctx = w14_ctx();\n"
        . "\$is_super = \$ctx['is_super'];\n"
        . "\$company_id = \$ctx['company_id'];\n"
        . "if (!\$is_super && \$company_id <= 0) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$perms = w14_perms(\$conn, '" . $route . "', \$is_super);\n"
        . "if (empty(\$perms['can_view'])) {\n"
        . "    ems_gov_flash_redirect('" . $up . "main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');\n"
        . "    exit();\n"
        . "}\n"
        . "\n"
        . "\$rows = w14_rows(\$is_super, '" . $s['table'] . "',\n"
        . "                 array('orderBy' => '" . $s['order'] . "', 'limit' => " . (int) $s['limit'] . "));\n"
        . "\n"
        . "\$page_title = 'إيكوبيشن | " . $s['title'] . "';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php';\n"
        . "ems_shell_axes(isset(\$perms) ? \$perms : null);\n"
        . "include '" . $up . "inheader.php';\n"
        . "include '" . $up . "insidebar.php';\n"
        . "require_once __DIR__ . '/" . $up . "includes/screen_contract.php'; if (isset(\$conn)) { ems_screen_about_auto(\$conn); }\n"
        . "?>\n"
        . "<div class=\"main ems-unified-page-shell\">\n"
        . "    <?php \$header_title = '" . $s['title'] . "'; \$header_icon = '" . $s['icon'] . "'; \$header_actions = array();\n"
        . "    \$header_back = array('href' => '" . $s['back'][0] . "', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => '" . $s['back'][1] . "');\n"
        . "    include('" . $up . "includes/page_header.php'); ?>\n"
        . "\n"
        . "    <div class=\"ems-stat-cards\">\n"
        . $cards
        . "    </div>\n"
        . "\n"
        . "    <?php require_once __DIR__ . '/" . $up . "includes/ux_components.php';\n"
        . "    echo ems_states_bundle('" . $s['empty'][0] . "', '" . $s['empty'][1] . "'); ?>\n"
        . "\n"
        . "    <div class=\"table-wrap\"><table class=\"data-table\">\n"
        . "        <thead><tr>" . $head . "</tr></thead>\n"
        . "        <tbody>\n"
        . "        <?php if (\$rows): foreach (\$rows as \$r): ?>\n"
        . "            <tr>\n"
        . $body
        . "            </tr>\n"
        . "        <?php endforeach; endif; ?>\n"
        . "        </tbody>\n"
        . "    </table></div>\n"
        . "</div>\n"
        . "</body></html>\n";

    file_put_contents($path, $php);
    echo "  ✔ $route\n";
    $written++;
}
printf("\nأسطحٌ مكتوبة %d · قائمةٌ %d\n", $written, $skipped);
