<?php
/**
 * tools/lib/repair01_w15_screens_def.php — تعريفُ أسطحِ الموجةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ سطحٍ هنا إسقاطٌ على جدولِ مالكٍ آخر** (‏قيدُ المالك §١): `owner`
 *   رمزُ الإدارةِ المالكةِ للجدولِ المقروء — ⛔ **ولا سطحَ يقرأ من مخزنٍ
 *   أنشأته هذه المرحلة**، لأنَّها لم تُنشئ مخزنًا.
 *
 * ◆ **والنوّابُ لا أسطحَ لهم هنا** وهو **قرارٌ مقصود** (‏§٤-٣ · `W15-D-03`):
 *   «‏لا ثلاثةَ أنظمة» — فمتطلَّباتُ النوّابِ تُخدَم **بالأسطحِ نفسِها**
 *   ونطاقُها يتغيّر بمحرّكِ النطاقِ الواحد. وسطحٌ ثانٍ للنائبِ بنسخةٍ من
 *   شيفرةِ الرئيسِ **هو عينُ ما نُهي عنه**.
 *
 * ◆ `scope_col` عمودُ النطاقِ الذي يُقيَّد به النائب · و`self_col` عمودُ صاحبِ
 *   الصفِّ في أسطحِ المساحةِ الشخصيّة — الحلقةُ `Record Scope` من السلسلة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

function repair01_w15_screen_defs()
{
    return array(

/* ══════════════ التقاريرُ الدوريّةُ للقيادة ═══════════════════════════════ */

'Portal/exec_daily_report.php' => array(
    'title' => 'التقرير اليومي التنفيذي', 'icon' => 'fa fa-calendar-day',
    'table' => 'site_day', 'idcol' => 'id', 'order' => 'day_date DESC, id DESC', 'limit' => 400,
    'scope_col' => 'project_id', 'owner' => 'DEP-12', 'owner_ar' => 'إدارة الموقع',
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'تقرير يومي مجمع عن المواقع والمشروعات يتجمع من يوميات المواقع المقفلة ولا يدخل هنا رقم',
    'empty' => array('لا يوميات مقفلة بعد', 'التقرير يتجمع من يوميات المواقع ولا يكتب هنا'),
    'cards' => array(
        array('count', '', '', 'عدد اليوميات'),
        array('eq', 'state', 'closed', 'يوميات مقفلة'),
        array('eq', 'state', 'open', 'يوميات مفتوحة'),
        array('distinct', 'project_id', '', 'المشروعات المشمولة'),
    ),
    'cols' => array(
        array('day_date', 'اليوم', 't'),
        array('project_id', 'المشروع', 'i'),
        array('site_id', 'الموقع', 'i'),
        array('state', 'الحالة', 's'),
        array('closed_at', 'وقت الإقفال', 't'),
        array('close_note', 'ملاحظة الإقفال', 't'),
    ),
),

'Portal/exec_daily_stops.php' => array(
    'title' => 'تفصيل توقفات اليوم', 'icon' => 'fa fa-hourglass-half',
    'table' => 'ops_stop_register', 'idcol' => 'id', 'order' => 'stop_date DESC, id DESC', 'limit' => 600,
    'scope_col' => 'project_id', 'owner' => 'DEP-11', 'owner_ar' => 'إدارة التشغيل',
    'back'  => array('exec_daily_report.php', 'التقرير اليومي التنفيذي'),
    'note'  => 'كل نوع توقف صف مستقل بساعاته ومسؤوله ومرجعه ولا عناصر متعددة في خلية',
    'empty' => array('لا توقفات مسجلة', 'التوقف صف بنوعه وساعاته لا خلاصة'),
    'cards' => array(
        array('count', '', '', 'عدد وقائع التوقف'),
        array('sumf', 'hours', '', 'مجموع الساعات'),
        array('eq', 'decision', 'pending', 'وقائع تنتظر التصنيف'),
        array('distinct', 'ops_state', '', 'أنواع التوقف'),
    ),
    'cols' => array(
        array('stop_date', 'اليوم', 't'),
        array('project_id', 'المشروع', 'i'),
        array('equipment_id', 'المعدة', 'i'),
        array('ops_state', 'نوع التوقف', 's'),
        array('hours', 'الساعات', 'n'),
        array('resp_party', 'الطرف المسؤول', 's'),
        array('obligation_type', 'أثر الالتزام', 's'),
        array('decision', 'الحالة', 's'),
    ),
),

'Portal/exec_daily_deviations.php' => array(
    'title' => 'انحرافات وقرارات اليوم', 'icon' => 'fa fa-triangle-exclamation',
    'table' => 'ctl_deviation', 'idcol' => 'id', 'order' => 'occurred_at DESC, id DESC', 'limit' => 400,
    'scope_col' => 'project_id', 'owner' => 'DEP-11', 'owner_ar' => 'إدارة التشغيل',
    'back'  => array('exec_daily_report.php', 'التقرير اليومي التنفيذي'),
    'note'  => 'كل انحراف صف مستقل بمسؤوله والانحراف يبقى عند مالكه ولا تفتح له حالة حوكمة تلقائيا',
    'empty' => array('لا انحرافات مسجلة', 'الانحراف يبقى عند مالكه ويعرض هنا'),
    'cards' => array(
        array('count', '', '', 'عدد الانحرافات'),
        array('filled', 'risk_ref', '', 'انحرافات فتحت محفزا للمخاطر'),
        array('filled', 'governance_ref', '', 'انحرافات فتحت حالة حوكمة'),
        array('distinct', 'owner_dept', '', 'الإدارات المالكة'),
    ),
    'cols' => array(
        array('deviation_no', 'رقم الانحراف', 't'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('deviation_kind', 'النوع', 's'),
        array('occurred_at', 'وقت الوقوع', 't'),
        array('duration_hours', 'المدة', 'n'),
        array('classification', 'التصنيف', 's'),
        array('rule_code', 'القاعدة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_weekly_report.php' => array(
    'title' => 'التقرير الأسبوعي التنفيذي', 'icon' => 'fa fa-chart-line',
    'table' => 'ops_stop_register', 'idcol' => 'id', 'order' => 'stop_date DESC, id DESC', 'limit' => 800,
    'scope_col' => 'project_id', 'owner' => 'DEP-11', 'owner_ar' => 'إدارة التشغيل',
    'back'  => array('exec_daily_report.php', 'التقرير اليومي التنفيذي'),
    'note'  => 'مقارنة اسبوعية بمحاورها تقرأ من وقائع التشغيل مباشرة ولا تخزن نسخة اسبوعية',
    'empty' => array('لا وقائع في المدى', 'المقارنة تشتق من الوقائع ولا تخزن'),
    'cards' => array(
        array('count', '', '', 'وقائع المدى'),
        array('sumf', 'hours', '', 'مجموع ساعات التوقف'),
        array('eq', 'billable', '1', 'وقائع قابلة للتحميل'),
        array('distinct', 'equipment_id', '', 'المعدات المتأثرة'),
    ),
    'cols' => array(
        array('stop_date', 'اليوم', 't'),
        array('project_id', 'المشروع', 'i'),
        array('ops_state', 'المحور', 's'),
        array('hours', 'الساعات', 'n'),
        array('resp_party', 'الطرف المسؤول', 's'),
        array('decision', 'الحالة', 's'),
    ),
),

'Portal/exec_monthly_pack.php' => array(
    'title' => 'التقرير الشهري التنفيذي', 'icon' => 'fa fa-calendar-check',
    'table' => 'fin_monthly_close', 'idcol' => 'id', 'order' => 'accounting_month DESC, id DESC', 'limit' => 300,
    'scope_col' => 'entity_id', 'owner' => 'DEP-05', 'owner_ar' => 'الإدارة المالية',
    'back'  => array('exec_weekly_report.php', 'التقرير الأسبوعي التنفيذي'),
    'note'  => 'حزمة الشهر تتجمع من اقفالات الادارات ومراجعات النواب والقيادة تستلم ولا تقفل',
    'empty' => array('لا إقفالات شهرية بعد', 'الحزمة تتجمع من إقفالات الإدارات'),
    'cards' => array(
        array('count', '', '', 'عدد الإقفالات'),
        array('eq', 'state', 'approved', 'إقفالات معتمدة'),
        array('eq', 'rollforward_ok', '1', 'إقفالات متصلة بالسابق'),
        array('distinct', 'accounting_month', '', 'الأشهر المشمولة'),
    ),
    'cols' => array(
        array('accounting_month', 'الشهر', 't'),
        array('close_code', 'رقم الإقفال', 't'),
        array('entity_id', 'الكيان', 'i'),
        array('open_balance', 'الرصيد الافتتاحي', 'n'),
        array('due_in_month', 'المستحق', 'n'),
        array('paid_in_month', 'المسدد', 'n'),
        array('close_balance', 'الرصيد الختامي', 'n'),
        array('state', 'الحالة', 's'),
    ),
),

/* ══════════════ صناديقُ القرار ═══════════════════════════════════════════ */

'Portal/exec_raised_requests.php' => array(
    'title' => 'الطلبات المرفوعة إلى القيادة', 'icon' => 'fa fa-inbox',
    'table' => 'fin_requests', 'idcol' => 'id', 'order' => 'sla_due_at, id DESC', 'limit' => 500,
    'scope_col' => 'project_id', 'owner' => 'DEP-05', 'owner_ar' => 'الإدارة المالية',
    'where' => array('state' => 'pending_approval'),
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'اسقاط فوق سجلات الادارات المالكة والقرار يمر بمحرك الاعتماد نفسه لا من هنا',
    'empty' => array('لا طلبات تنتظر القيادة', 'الصندوق إسقاط فوق سجلات مالكيها'),
    'cards' => array(
        array('count', '', '', 'طلبات تنتظر القرار'),
        array('sumf', 'amount', '', 'مجموع القيم'),
        array('eq', 'priority', 'critical', 'طلبات حرجة'),
        array('distinct', 'source_module', '', 'الإدارات المصدر'),
    ),
    'cols' => array(
        array('request_no', 'رقم الطلب', 't'),
        array('source_module', 'الإدارة المصدر', 's'),
        array('request_type', 'النوع', 's'),
        array('beneficiary_name', 'المستفيد', 't'),
        array('amount', 'القيمة', 'n'),
        array('currency', 'العملة', 't'),
        array('needed_by', 'المهلة', 't'),
        array('escalation_level', 'مستوى التصعيد', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_contract_registry.php' => array(
    'title' => 'سجل العقود الموحد', 'icon' => 'fa fa-file-signature',
    'table' => 'contracts', 'idcol' => 'id', 'order' => 'contract_signing_date DESC, id DESC', 'limit' => 500,
    'scope_col' => 'project_id', 'owner' => 'DEP-01', 'owner_ar' => 'إدارة المبيعات التعاقدية والعقود',
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'نافذة قراءة واحدة تجمع العقود من سجلات مالكيها والقيادة لا تملك العقد ولا سجله',
    'empty' => array('لا عقود مسجلة', 'السجل نافذة قراءة فوق سجلات مالكيها'),
    'cards' => array(
        array('count', '', '', 'عدد العقود'),
        array('eq', 'contract_status', 'نافذ', 'عقود نافذة'),
        array('eq', 'contract_status', 'مقفل', 'عقود مقفلة'),
        array('filled', 'signing_authority_ref', '', 'عقود بمرجع سلطة توقيع'),
    ),
    'cols' => array(
        array('id', 'رقم العقد', 'i'),
        array('second_party', 'الطرف الآخر', 't'),
        array('contract_signing_date', 'تاريخ التوقيع', 't'),
        array('actual_start', 'البداية', 't'),
        array('actual_end', 'النهاية', 't'),
        array('price_currency_contract', 'العملة', 't'),
        array('signing_authority_ref', 'مرجع سلطة التوقيع', 't'),
        array('contract_status', 'الحالة', 's'),
    ),
),

'Portal/exec_redline_breaches.php' => array(
    'title' => 'تجاوزات الخطوط الحمراء', 'icon' => 'fa fa-ban',
    'table' => 'exception_requests', 'idcol' => 'req_id', 'order' => 'req_id DESC', 'limit' => 400,
    'scope_col' => 'company_id', 'owner' => 'DEP-08', 'owner_ar' => 'إدارة الحوكمة والالتزام',
    'where' => array('risk_level' => 'legal_forbidden'),
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'طلبات استثناء قبل التنفيذ من قاعدة مانعة وتظل مغلقة حتى يصدر القرار بسلطته',
    'empty' => array('لا طلبات تجاوز', 'القاعدة المانعة تبقى مغلقة حتى قرار بسلطته'),
    'cards' => array(
        array('count', '', '', 'عدد طلبات التجاوز'),
        array('eq', 'state', 'Pending', 'تنتظر القرار'),
        array('eq', 'state', 'Approved', 'صدر فيها قرار'),
        array('distinct', 'guard_code', '', 'القواعد المطلوب تجاوزها'),
    ),
    'cols' => array(
        array('req_id', 'رقم الطلب', 'i'),
        array('guard_code', 'القاعدة', 't'),
        array('reason', 'السبب', 't'),
        array('scope_type', 'نطاق الطلب', 's'),
        array('valid_from', 'من', 't'),
        array('valid_to', 'إلى', 't'),
        array('expected_impact', 'الأثر المتوقع', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_critical_exceptions.php' => array(
    'title' => 'الاستثناءات الحرجة', 'icon' => 'fa fa-circle-exclamation',
    'table' => 'exception_requests', 'idcol' => 'req_id', 'order' => 'req_id DESC', 'limit' => 400,
    'scope_col' => 'company_id', 'owner' => 'DEP-08', 'owner_ar' => 'إدارة الحوكمة والالتزام',
    'where' => array('risk_level' => 'high'),
    'back'  => array('exec_redline_breaches.php', 'تجاوزات الخطوط الحمراء'),
    'note'  => 'حالات حرجة واقعة وصلت للقمة لقبول تعرض او قرار علاج والقرار بسلطته لا بموقعه',
    'empty' => array('لا استثناءات حرجة', 'الحالة الحرجة تصل بسلطتها لا بموقعها'),
    'cards' => array(
        array('count', '', '', 'عدد الاستثناءات الحرجة'),
        array('eq', 'state', 'Pending', 'تنتظر القرار'),
        array('eq', 'state', 'Active', 'استثناءات سارية'),
        array('eq', 'state', 'Expired', 'استثناءات منتهية'),
    ),
    'cols' => array(
        array('req_id', 'رقم الطلب', 'i'),
        array('guard_code', 'القاعدة', 't'),
        array('reason', 'السبب', 't'),
        array('risk_level', 'مستوى التعرض', 's'),
        array('valid_to', 'تاريخ الانتهاء', 't'),
        array('usage_count', 'مرات الاستخدام', 'i'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_reserved_matters.php' => array(
    'title' => 'المسائل المحجوزة', 'icon' => 'fa fa-lock',
    'table' => 'gov_policy', 'idcol' => 'id', 'order' => 'policy_no, version_no DESC', 'limit' => 300,
    'scope_col' => 'company_id', 'owner' => 'DEP-08', 'owner_ar' => 'إدارة الحوكمة والالتزام',
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'ما حجزته وثائق الشركة لجهة حاكمة لا يقرر تنفيذيا مهما بلغت الحاجة وهذه نافذة تعريف واحالة',
    'empty' => array('لا مسائل محجوزة مسجلة', 'المسألة المحجوزة تعرف وتحال ولا تقرر هنا'),
    'cards' => array(
        array('count', '', '', 'عدد الوثائق الحاكمة'),
        array('eq', 'state', 'effective', 'وثائق نافذة'),
        array('filled', 'review_due', '', 'وثائق لها موعد مراجعة'),
        array('distinct', 'owner_dept', '', 'الإدارات المالكة'),
    ),
    'cols' => array(
        array('policy_no', 'رقم الوثيقة', 't'),
        array('title_ar', 'الموضوع', 't'),
        array('domain_ar', 'المجال', 't'),
        array('owner_dept', 'الإدارة المالكة', 't'),
        array('effective_from', 'تاريخ النفاذ', 't'),
        array('review_due', 'موعد المراجعة', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_escalations.php' => array(
    'title' => 'التصعيدات العليا', 'icon' => 'fa fa-arrow-up-right-dots',
    'table' => 'ticket_escalations', 'idcol' => 'esc_id', 'order' => 'esc_id DESC', 'limit' => 400,
    'scope_col' => 'company_id', 'owner' => 'DEP-10', 'owner_ar' => 'إدارة البلاغات',
    'where' => array('level' => 'exec'),
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'صف موحد لما وصل للقمة من مصادره ولا سجل مكرر ولا اعادة انشاء للواقعة',
    'empty' => array('لا تصعيدات وصلت للقمة', 'التصعيد يقرأ من مصدره ولا ينسخ'),
    'cards' => array(
        array('count', '', '', 'عدد التصعيدات'),
        array('eq', 'triggered_by', 'sla_breach', 'تصعيد بتجاوز المهلة'),
        array('eq', 'triggered_by', 'safety', 'تصعيد بسبب السلامة'),
        array('distinct', 'ws_id', '', 'المسارات المتأثرة'),
    ),
    'cols' => array(
        array('esc_id', 'رقم التصعيد', 'i'),
        array('ws_id', 'المسار', 'i'),
        array('level', 'المستوى', 's'),
        array('triggered_by', 'سبب التصعيد', 's'),
        array('at', 'وقت التصعيد', 't'),
    ),
),

'Portal/exec_crisis_command.php' => array(
    'title' => 'قيادة الأزمات والطوارئ', 'icon' => 'fa fa-tower-broadcast',
    'table' => 'exec_decisions', 'idcol' => 'id', 'order' => 'raised_date DESC, id DESC', 'limit' => 200,
    'scope_col' => 'company_id', 'owner' => 'EX-CEO', 'owner_ar' => 'سجل قرارات القيادة',
    'where' => array('issue_type' => 'أزمة'),
    'back'  => array('exec_escalations.php', 'التصعيدات العليا'),
    'note'  => 'تفعيل قيادي فوق الحدث لا تكرار له والحدث يبقى عند مصدره وهذه اضافة قرار لا نسخة واقعة',
    'empty' => array('لا تفعيل قائم', 'التفعيل يقع فوق الحدث ولا ينسخه'),
    'cards' => array(
        array('count', '', '', 'عدد حالات التفعيل'),
        array('eq', 'status', 'مفتوح', 'تفعيل قائم'),
        array('filled', 'assigned_dept', '', 'حالات لها إدارة مكلفة'),
        array('filled', 'authority_ref', '', 'حالات بمرجع سلطة'),
    ),
    'cols' => array(
        array('decision_no', 'رقم القرار', 't'),
        array('raised_date', 'تاريخ الرفع', 't'),
        array('raising_dept', 'الإدارة الرافعة', 't'),
        array('issue_desc', 'الموضوع', 't'),
        array('assigned_dept', 'الإدارة المكلفة', 't'),
        array('exec_deadline', 'المهلة', 't'),
        array('authority_ref', 'مرجع السلطة', 't'),
        array('status', 'الحالة', 's'),
    ),
),

/* ══════════════ القرارُ والحوكمةُ العليا ═════════════════════════════════ */

'Portal/exec_strategic_decisions.php' => array(
    'title' => 'القرارات الاستراتيجية', 'icon' => 'fa fa-chess',
    'table' => 'exec_decisions', 'idcol' => 'id', 'order' => 'raised_date DESC, id DESC', 'limit' => 300,
    'scope_col' => 'company_id', 'owner' => 'EX-CEO', 'owner_ar' => 'سجل قرارات القيادة',
    'where' => array('issue_type' => 'استراتيجي'),
    'back'  => array('ceo_board.php', 'لوحة القيادة التنفيذية'),
    'note'  => 'سجل منفصل عن اليومي ولا يصل القرار للقمة الا بعد مراجعات الادارات وتوصية نيابية',
    'empty' => array('لا قرارات استراتيجية', 'القرار يصل بعد مراجعاته لا قبلها'),
    'cards' => array(
        array('count', '', '', 'عدد القرارات'),
        array('eq', 'status', 'مفتوح', 'قرارات مفتوحة'),
        array('filled', 'chosen_option', '', 'قرارات باختيار محسوم'),
        array('filled', 'authority_ref', '', 'قرارات بمرجع سلطة'),
    ),
    'cols' => array(
        array('decision_no', 'رقم القرار', 't'),
        array('raised_date', 'تاريخ الرفع', 't'),
        array('raising_dept', 'الإدارة الرافعة', 't'),
        array('issue_desc', 'الموضوع', 't'),
        array('options_text', 'البدائل', 't'),
        array('chosen_option', 'الاختيار', 't'),
        array('choice_reason', 'مبرر الاختيار', 't'),
        array('status', 'الحالة', 's'),
    ),
),

'Portal/exec_leadership_appointments.php' => array(
    'title' => 'موافقات التعيين في المسميات القيادية', 'icon' => 'fa fa-user-tie',
    'table' => 'exec_assignments', 'idcol' => 'id', 'order' => 'requested_at DESC, id DESC', 'limit' => 300,
    'scope_col' => 'company_id', 'owner' => 'DEP-07', 'owner_ar' => 'إدارة الموارد البشرية',
    'where' => array('assignment_kind' => 'leadership'),
    'back'  => array('ceo_assignments.php', 'التكليفات والإنابات المؤقتة'),
    'note'  => 'التعيين القيادي بقاعدة سلطته ومعه افصاح الاطراف ذات العلاقة قبل القرار',
    'empty' => array('لا طلبات تعيين قيادي', 'الطلب يصل بإفصاحه لا بدونه'),
    'cards' => array(
        array('count', '', '', 'عدد الطلبات'),
        array('eq', 'state', 'presented', 'طلبات معروضة للقرار'),
        array('eq', 'conflict_state', 'conflict', 'طلبات عليها تعارض'),
        array('filled', 'authority_ref', '', 'طلبات بمرجع سلطة'),
    ),
    'cols' => array(
        array('assignment_no', 'رقم الطلب', 't'),
        array('subject_name', 'المرشح', 't'),
        array('role_name', 'المسمى', 't'),
        array('scope_note', 'النطاق', 't'),
        array('conflict_state', 'حالة التعارض', 's'),
        array('effective_from', 'من', 't'),
        array('effective_to', 'إلى', 't'),
        array('state', 'الحالة', 's'),
    ),
),

'Portal/exec_meeting_decisions.php' => array(
    'title' => 'قرارات الاجتماعات', 'icon' => 'fa fa-clipboard-list',
    'table' => 'exec_decisions', 'idcol' => 'id', 'order' => 'decision_date DESC, id DESC', 'limit' => 400,
    'scope_col' => 'company_id', 'owner' => 'EX-CEO', 'owner_ar' => 'سجل قرارات القيادة',
    'back'  => array('exec_strategic_decisions.php', 'القرارات الاستراتيجية'),
    'note'  => 'كل قرار صف مستقل بمرجع اجتماعه ومالكه وادارته ولا قرارات متعددة في خلية',
    'empty' => array('لا قرارات مسجلة', 'القرار صف مستقل بمرجع اجتماعه'),
    'cards' => array(
        array('count', '', '', 'عدد القرارات'),
        array('filled', 'parent_ref', '', 'قرارات بمرجع اجتماع'),
        array('filled', 'assigned_dept', '', 'قرارات لها إدارة مكلفة'),
        array('filled', 'followup_date', '', 'قرارات لها موعد متابعة'),
    ),
    'cols' => array(
        array('decision_no', 'رقم القرار', 't'),
        array('parent_ref', 'مرجع الاجتماع', 't'),
        array('decision_date', 'تاريخ القرار', 't'),
        array('issue_desc', 'الموضوع', 't'),
        array('chosen_option', 'القرار', 't'),
        array('assigned_dept', 'الإدارة المكلفة', 't'),
        array('followup_date', 'موعد المتابعة', 't'),
        array('status', 'الحالة', 's'),
    ),
),

'Portal/exec_actions_followup.php' => array(
    'title' => 'متابعة القرارات التنفيذية', 'icon' => 'fa fa-list-check',
    'table' => 'exec_decisions', 'idcol' => 'id', 'order' => 'followup_date, id DESC', 'limit' => 500,
    'scope_col' => 'company_id', 'owner' => 'EX-CEO', 'owner_ar' => 'سجل قرارات القيادة',
    'back'  => array('exec_meeting_decisions.php', 'قرارات الاجتماعات'),
    'note'  => 'سجل موحد يجمع ما صدر من الاجتماعات والدوري والاستراتيجي والتصعيدات بمرجع كل منها',
    'empty' => array('لا قرارات قيد المتابعة', 'المتابعة تجمع بمرجعها ولا تنشئ سجلا ثانيا'),
    'cards' => array(
        array('count', '', '', 'عدد القرارات المتابعة'),
        array('eq', 'status', 'مفتوح', 'قرارات مفتوحة'),
        array('eq', 'status', 'مغلق', 'قرارات مغلقة'),
        array('empty', 'followup_date', '', 'قرارات بلا موعد متابعة'),
    ),
    'cols' => array(
        array('decision_no', 'رقم القرار', 't'),
        array('issue_type', 'المصدر', 's'),
        array('issue_desc', 'الموضوع', 't'),
        array('assigned_dept', 'الإدارة المكلفة', 't'),
        array('exec_deadline', 'المهلة', 't'),
        array('followup_date', 'موعد المتابعة', 't'),
        array('status', 'الحالة', 's'),
    ),
),

'Portal/exec_delegations.php' => array(
    'title' => 'الإنابات والتفويضات', 'icon' => 'fa fa-user-shield',
    'table' => 'gov_delegations', 'idcol' => 'delegation_id', 'order' => 'valid_from DESC, delegation_id DESC',
    'limit' => 300, 'scope_col' => 'company_id', 'owner' => 'DEP-08', 'owner_ar' => 'إدارة الحوكمة والالتزام',
    'back'  => array('ceo_assignments.php', 'التكليفات والإنابات المؤقتة'),
    'note'  => 'الانابة لا تغير الهيكل والمصدر النافذ سجل التفويضات عند الحوكمة والمنتهية ليست انابة',
    'empty' => array('لا إنابات مسجلة', 'الإنابة مدة بنطاقها لا صفة دائمة'),
    'cards' => array(
        array('count', '', '', 'عدد الإنابات'),
        array('empty', 'revoked_at', '', 'إنابات غير ملغاة'),
        array('filled', 'revoked_at', '', 'إنابات ملغاة'),
        array('filled', 'reason', '', 'إنابات لها سبب مكتوب'),
    ),
    'cols' => array(
        array('delegation_id', 'رقم الإنابة', 'i'),
        array('from_user', 'صاحب الإنابة', 'i'),
        array('to_user', 'النائب', 'i'),
        array('valid_from', 'من', 't'),
        array('valid_to', 'إلى', 't'),
        array('reason', 'السبب', 't'),
        array('revoked_at', 'تاريخ الإلغاء', 't'),
    ),
),

/* ══════════════ مساحةُ عملي ══════════════════════════════════════════════ */

'Portal/my_reports.php' => array(
    'title' => 'بلاغاتي', 'icon' => 'fa fa-bullhorn',
    'table' => 'tickets', 'idcol' => 'id', 'order' => 'id DESC', 'limit' => 300,
    'scope_col' => 'project_id', 'self_col' => 'reporter_user_id',
    'owner' => 'DEP-10', 'owner_ar' => 'إدارة البلاغات',
    'back'  => array('my_portal.php', 'بوابتي'),
    'note'  => 'صاحب البلاغ يرى بلاغاته وحالتها والتسجيل الجديد من باب البلاغات لا من هنا',
    'empty' => array('لا بلاغات لك', 'البلاغ يسجل من بابه ويعرض هنا'),
    'cards' => array(
        array('count', '', '', 'عدد بلاغاتي'),
        array('eq', 'head_state', 'open', 'بلاغات مفتوحة'),
        array('eq', 'head_state', 'closed', 'بلاغات مغلقة'),
        array('eq', 'priority', 'critical', 'بلاغات حرجة'),
    ),
    'cols' => array(
        array('ticket_no', 'رقم البلاغ', 't'),
        array('call_date', 'تاريخ البلاغ', 't'),
        array('operational_summary', 'الملخص', 't'),
        array('priority', 'الأولوية', 's'),
        array('stage', 'المرحلة', 's'),
        array('response_due_at', 'مهلة الاستجابة', 't'),
        array('resolution_due_at', 'مهلة المعالجة', 't'),
        array('head_state', 'الحالة', 's'),
    ),
),

    );
}
