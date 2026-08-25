<?php
/**
 * tools/lib/repair01_w3_contracts.php — عقودُ الأثرِ لأحداثِ الكياناتِ الأمّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المستهلِكُ لا يُكتب هنا**: «كلُّ مستهلكٍ فعليٍّ بالاسم» يُقرأ من
 *   `gov_effect_map` و`ems_event_subscriptions` — سجلَّين حيَّين يحملان
 *   المستهلِكَ ومساحتَه ومستنَدَه و**درجةَ دليلِه** (`MEASURED` ·
 *   `DECLARED_ACTIVE` · `DECLARED_INACTIVE`). فكتابةُ قائمةِ مستهلكينَ يدويًّا
 *   هنا تصنع مصدرًا ثانيًا يتفرَّق عن الحيِّ صامتًا.
 *
 * ◆ **والمُعلَنُ هنا ما لا يحمله سجلٌّ حيّ**: المحفِّزُ · الحمولةُ الدنيا ·
 *   الشروطُ المسبقة · سلوكُ الفشلِ · التعويض. ولكلٍّ `src` يعود إلى الشيفرةِ
 *   التي تُطلقه — لا إلى رأيٍ.
 *
 * ◆ **ونطاقُ الأحداثِ مقيسٌ لا مختار**: `entity_type` في `ems_business_events`
 *   يُترجَم إلى مفتاحٍ من الثلاثةَ عشر؛ وكلُّ `event_key` حيٍّ على كيانٍ أمٍّ
 *   **يجب** أن يحمل عقدًا. فالمقامُ من القاعدةِ لا من قائمةٍ في هذا الملفّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** `entity_type` الحيُّ ⇐ المفتاحُ الذي يخصّه — مقامُ أحداثِ W03. */
function repair01_w3_entity_key_map()
{
    return array(
        'timesheet'                    => 'Timesheet_ID',
        'unit_entry'                   => 'Unit_ID',
        'fin_unit_record'              => 'Unit_ID',
        'fin_asset'                    => 'Asset_ID',
        'equipment'                    => 'Asset_ID',
        'employee_contract'            => 'Person_ID',
        'employee_contract_amendment'  => 'Person_ID',
        'employee'                     => 'Person_ID',
        'project'                      => 'Project_ID',
        'site'                         => 'Site_ID',
        'mnt_order'                    => 'Maintenance_Order_ID',
        'transfer_order'               => 'Transport_Order_ID',
        'transfer_request'             => 'Transport_Request_ID',
        'ticket'                       => 'Ticket_ID',
    );
}

/**
 * الجزءُ المُعلَنُ من العقد — لكلِّ `event_key` حيّ.
 * الحقول: name · unit · screen · idem · trigger · payload · pre · fail · comp · effect · src
 */
function repair01_w3_contract_narrative()
{
    return array(
        'workforce.contract_state.changed' => array(
            'name' => 'تغيّرُ حالةِ عقدِ الموظف', 'unit' => '07 الموارد / 13 القوى',
            'screen' => 'Workforce/contract_registry.php',
            'idem' => 'employee_contract_id + from_state + to_state + occurred_at',
            'trigger' => 'انتقالٌ مقبولٌ في آلةِ حالةِ عقدِ الموظف — ولا يُطلَق على انتقالٍ مرفوض',
            'payload' => 'Company_ID · Person_ID · employee_contract_id · from_state · to_state · effective_date · actor',
            'pre' => 'العقدُ قائمٌ · الانتقالُ في قائمةِ السماحِ · الفاعلُ مخوَّلٌ بالانتقالِ نفسِه',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — والحالةُ في العقدِ لا تتراجع',
            'comp' => 'انتقالٌ عكسيٌّ مسجَّلٌ بسببِه لا حذفُ الحدث',
            'effect' => 'UPDATE_STATUS + COMPLIANCE_EFFECT',
            'src' => 'app/Services/Contract/EmployeeContractStateMachine.php'),
        'workforce.contract.amended' => array(
            'name' => 'ملحقٌ على عقدِ الموظف', 'unit' => '07 الموارد / 13 القوى',
            'screen' => 'Workforce/contract_registry.php',
            'idem' => 'amendment_id',
            'trigger' => 'اعتمادُ ملحقٍ يغيّر أجرًا أو مدّةً أو بندًا في عقدٍ نافذ',
            'payload' => 'Company_ID · Person_ID · employee_contract_id · amendment_id · نوع التغيير · تاريخ النفاذ',
            'pre' => 'العقدُ نافذٌ · الملحقُ معتمَدٌ · تاريخُ النفاذِ لا يسبق بدايةَ العقد',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'ملحقٌ مضادٌّ بسببِه — والإسقاطُ حتميٌّ يُعاد بناؤه',
            'effect' => 'PROJECTION + COMPLIANCE_EFFECT',
            'src' => 'app/Services/Contract/EmployeeContractAmendmentService.php'),
        'expense.depreciation.recorded' => array(
            'name' => 'قيدُ إهلاكِ فترة', 'unit' => '04 الأسطول / 05 المالية',
            'screen' => 'Finance/depr_run.php',
            'idem' => 'fin_asset_id + period',
            'trigger' => 'تشغيلُ احتسابِ إهلاكِ فترةٍ ماليّةٍ مفتوحة',
            'payload' => 'Company_ID · Asset_ID · fin_asset_id · الفترة · الأساس · القيمة · العملة',
            'pre' => 'الفترةُ مفتوحةٌ · الأصلُ موصولٌ بمعدّةٍ (fin_assets.equipment_id) · سياسةُ الإهلاكِ مسجَّلة',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — ولا قيدَ جزئيّ',
            'comp' => 'قيدٌ عكسيٌّ بالفترةِ نفسِها لا حذف',
            'effect' => 'JOURNAL + REPORTING_EFFECT',
            'src' => 'app/Services/Finance/DepreciationService.php'),
        'operations.unit.approved' => array(
            'name' => 'اعتمادُ وحدةِ عمل', 'unit' => '11 التشغيل / 12 الموقع',
            'screen' => 'Operations/shift_entry.php',
            'idem' => 'unit_record_id + round_no',
            'trigger' => 'اكتمالُ سلسلةِ الاعتمادِ على وحدةِ عملٍ مرفوعة',
            'payload' => 'Company_ID · Unit_ID · Project_ID · Asset_ID · Person_ID · الكمية · التاريخ',
            'pre' => 'الوحدةُ مرفوعةٌ · السلسلةُ كاملةٌ · الطرفُ المخوَّلُ اعتمد',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — والوحدةُ تبقى معتمَدةً في دفترِها',
            'comp' => 'تصحيحٌ بجولةٍ جديدةٍ (round_no) لا تعديلٌ في مكانِه',
            'effect' => 'UPDATE_STATUS + UNLOCK',
            'src' => 'app/Services/EffectFanout.php'),
        'revenue.unit.recognized' => array(
            'name' => 'اعترافٌ بإيرادِ وحدة', 'unit' => '11 التشغيل / 05 المالية',
            'screen' => 'Timesheet/timesheet.php',
            'idem' => 'source_event_id',
            'trigger' => 'اعتمادُ وحدةِ عملٍ قابلةٍ للفوترةِ على عقدٍ نافذ',
            'payload' => 'Company_ID · Unit_ID أو Timesheet_ID · Project_ID · contract_id · الكمية · السعر · العملة',
            'pre' => 'العقدُ نافذٌ · التسعيرُ اليوميُّ مسجَّلٌ · الوحدةُ معتمَدةٌ وغيرُ مفوترة',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — والاعترافُ لا يتكرَّر بمفتاحِ العطالة',
            'comp' => 'إشعارٌ دائنٌ مسجَّلٌ بسببِه لا عكسُ الحدث',
            'effect' => 'REVENUE_RECOGNITION + PROJECTION',
            'src' => 'app/Services/EffectFanout.php'),
        'expense.maintenance.recorded' => array(
            'name' => 'تكلفةُ أمرِ صيانة', 'unit' => '14 الصيانة / 05 المالية',
            'screen' => 'Maintenance/orders.php',
            'idem' => 'mnt_order_id + closed_at',
            'trigger' => 'إقفالُ أمرِ صيانةٍ بتكلفةٍ محسوبة',
            'payload' => 'Company_ID · Maintenance_Order_ID · Asset_ID · طرفُ التحميل · العمالةُ والقطعُ والخارجيّ',
            'pre' => 'الأمرُ مقفلٌ · طرفُ التحميلِ محدَّدٌ · الأصلُ موصولٌ بـAsset_ID',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'إعادةُ فتحِ الأمرِ تُنشئ حدثًا جديدًا بجولةٍ لا تعدّل السابق',
            'effect' => 'EXPENSE + PROJECTION',
            'src' => 'app/Services/EffectFanout.php'),
        'project.chartered' => array(
            'name' => 'اعتمادُ ميثاقِ مشروع', 'unit' => '01 المبيعات / E1 الرئيس التنفيذي',
            'screen' => 'Portal/project_charter.php',
            'idem' => 'project_id + charter_version',
            'trigger' => 'توقيعُ ميثاقِ المشروعِ من صاحبِ الصلاحية',
            'payload' => 'Company_ID · Project_ID · نسخةُ الميثاق · المالكُ · التاريخ',
            'pre' => 'المشروعُ مسجَّلٌ بـProject_ID · العقدُ قائمٌ · المالكُ مخوَّل',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'نسخةُ ميثاقٍ جديدةٌ تُبطل السابقةَ بالإشارةِ لا بالحذف',
            'effect' => 'UNLOCK + REPORTING_EFFECT',
            'src' => 'Portal/project_charter.php'),
        'equipment.hour_logged' => array(
            'name' => 'تسجيلُ ساعةِ تشغيلِ معدّة', 'unit' => '04 الأسطول / 12 الموقع',
            'screen' => 'Timesheet/timesheet.php',
            'idem' => 'timesheet_id',
            'trigger' => 'حفظُ تايم شيتٍ لمعدّةٍ ومشغّلٍ وتاريخٍ ووردية',
            'payload' => 'Company_ID · Asset_ID · Person_ID · Timesheet_ID · Shift_ID · الساعاتُ · العدّاد',
            'pre' => 'المعدّةُ مسجَّلةٌ بـAsset_ID · المشغّلُ بـPerson_ID · الورديةُ قائمة',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — والساعاتُ في الدفترِ لا تتراجع',
            'comp' => 'تصحيحُ التايم شيتِ يُنشئ حدثًا جديدًا بمفتاحِ عطالةٍ جديد',
            'effect' => 'DERIVE + PROJECTION',
            'src' => 'app/Services/EffectFanout.php'),
        'attribution.decided' => array(
            'name' => 'حكمُ نسبةِ الكمية', 'unit' => '11 التشغيل',
            'screen' => 'Contracts/unit_client_match.php',
            'idem' => 'unit_entry_id + decided_at',
            'trigger' => 'حكمُ صاحبِ الصلاحيةِ على نسبةِ كميّةِ وحدةٍ إلى واقعتِها',
            'payload' => 'Company_ID · Unit_ID · الكميةُ القابلةُ للفوترةِ · سببُ الحكمِ · الحاكم',
            'pre' => 'الوحدةُ قائمةٌ · الحكمُ مسبَّبٌ · الحاكمُ ليس مُدخِلَ الوحدة',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'حكمٌ جديدٌ يُسجَّل ويُبطل السابقَ بالإشارة',
            'effect' => 'DERIVE + UPDATE_STATUS',
            'src' => 'app/Services/Unit/TimesheetEntryService.php'),
        'capacity.consumed' => array(
            'name' => 'استهلاكُ طاقةِ التزام', 'unit' => '11 التشغيل / 01 المبيعات',
            'screen' => 'Contracts/contract_commitments.php',
            'idem' => 'contract_commitment_id + unit_entry_id',
            'trigger' => 'ربطُ وحدةِ عملٍ معتمَدةٍ بالتزامٍ تعاقديٍّ يستهلك طاقتَه',
            'payload' => 'Company_ID · Unit_ID · contract_commitment_id · الكميةُ المستهلَكة',
            'pre' => 'الالتزامُ قائمٌ وله رصيدٌ · الوحدةُ معتمَدةٌ · لا استهلاكَ مزدوج',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'ردُّ الطاقةِ بحركةٍ مضادّةٍ مسجَّلةٍ لا بحذفِ الحركة',
            'effect' => 'CONSUME + DASHBOARD_REFRESH',
            'src' => 'app/Services/Capacity/CapacityEvents.php'),
        'operations.unit.chain_completed' => array(
            'name' => 'اكتمالُ سلسلةِ اعتمادِ وحدة', 'unit' => '11 التشغيل / 12 الموقع',
            'screen' => 'Operations/shift_entry.php',
            'idem' => 'unit_entry_id + round_no',
            'trigger' => 'اعتمادُ آخرِ طرفٍ في سلسلةِ الوحدة — والسلسلةُ هي المسار',
            'payload' => 'Company_ID · Unit_ID · round_no · أطرافُ السلسلةِ وأزمنتُها',
            'pre' => 'كلُّ أطرافِ السلسلةِ اعتمدت · لا طرفَ اعتمد نيابةً عن آخر',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'جولةٌ جديدةٌ تفتح السلسلةَ من أوّلِها',
            'effect' => 'UNLOCK + PROJECTION',
            'src' => 'app/Services/Unit/TimesheetEntryService.php'),
        'operations.unit.stage_approved' => array(
            'name' => 'اعتمادُ طرفٍ في سلسلةِ وحدة', 'unit' => '11 التشغيل / 12 الموقع',
            'screen' => 'Operations/shift_entry.php',
            'idem' => 'unit_entry_id + round_no + stage_key',
            'trigger' => 'اعتمادُ طرفٍ واحدٍ من أطرافِ السلسلة',
            'payload' => 'Company_ID · Unit_ID · round_no · الطرفُ · الفاعلُ · الزمن',
            'pre' => 'الوحدةُ مرفوعةٌ · الطرفُ مخوَّلٌ · لم يعتمدْ في هذه الجولةِ سلفًا',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة',
            'comp' => 'سحبُ الاعتمادِ يُسجَّل حدثًا مضادًّا بسببِه',
            'effect' => 'UPDATE_STATUS',
            'src' => 'app/Services/Unit/TimesheetEntryService.php'),
        'operations.unit.submitted' => array(
            'name' => 'رفعُ وحدةِ عمل', 'unit' => '12 الموقع',
            'screen' => 'Operations/shift_entry.php',
            'idem' => 'unit_entry_id + round_no',
            'trigger' => 'رفعُ وحدةِ عملٍ يوميّةٍ من قيدِ الوردية',
            'payload' => 'Company_ID · Unit_ID · Project_ID · Site_ID · Asset_ID · Person_ID · Shift_ID · الكمية',
            'pre' => 'الورديةُ مفتوحةٌ · المعدّةُ والمشغّلُ مسجَّلانِ بمفتاحَيهما · الموقعُ بـSite_ID',
            'fail' => 'خمسُ محاولاتٍ ثمّ صندوقُ الرسائلِ الميتة — والقيدُ محفوظٌ في دفترِه',
            'comp' => 'مراجعةٌ بجولةٍ جديدةٍ لا تعديلٌ في مكانِه',
            'effect' => 'UPDATE_STATUS + DERIVE',
            'src' => 'app/Services/Unit/TimesheetEntryService.php'),
    );
}

/**
 * المستهلِكونَ بالاسمِ وأثرُ كلٍّ منهم — **من السجلَّينِ الحيَّينِ لا من قائمة**.
 * يعيد array('consumers' => [...], 'effects' => [...], 'retry' => '…')
 */
function repair01_w3_measure_consumers(mysqli $c, $eventKey)
{
    $k = $c->real_escape_string($eventKey);
    $consumers = array(); $effects = array();
    $r = $c->query("SELECT consumer_key, consumer_space, consumer_doc, evidence, evidence_n
                      FROM gov_effect_map WHERE event_key = '$k' ORDER BY consumer_key");
    while ($r && $x = $r->fetch_assoc()) {
        $consumers[] = $x['consumer_key'] . ' @ ' . ($x['consumer_space'] !== '' ? $x['consumer_space'] : 'بلا مساحة معلنة');
        $effects[]   = $x['consumer_key'] . ' ⇐ ' . $x['consumer_doc'] . ' · دليل: ' . $x['evidence']
                     . ($x['evidence'] === 'DECLARED_INACTIVE' ? ' ⛔ مسجل ومعطل — لا أثر واصل' : '');
    }
    $retry = array();
    $r = $c->query("SELECT consumer_key, max_attempts, timeout_seconds, is_active
                      FROM ems_event_subscriptions WHERE event_code = '$k' ORDER BY consumer_key");
    while ($r && $x = $r->fetch_assoc()) {
        $retry[] = $x['consumer_key'] . ': ' . (int) $x['max_attempts'] . '× / '
                 . (int) $x['timeout_seconds'] . 'ث' . ((int) $x['is_active'] === 1 ? ' نشط' : ' معطل');
    }
    return array('consumers' => $consumers, 'effects' => $effects,
                 'retry' => $retry ? implode(' · ', $retry) : 'بلا اشتراك مسجل');
}
