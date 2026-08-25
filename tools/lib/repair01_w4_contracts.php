<?php
/**
 * tools/lib/repair01_w4_contracts.php — عقودُ الأثرِ لأحداثِ الحقيقةِ الميدانية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** (§٥ · §٧): العقدُ يحمل المصدرَ
 *   والمحفِّزَ والحمولةَ الدنيا و**كلَّ مستهلكٍ فعليٍّ بالاسم** وأثرَ كلٍّ منهم
 *   والشروطَ المسبقةَ وسياسةَ الإعادةِ ومفتاحَ منعِ التكرارِ وسلوكَ الفشلِ
 *   والتعويض.
 *
 * ◆ **والمستهلكونَ هنا احتياطٌ لا أصل**: `repair01_w3_measure_consumers` تقيسهم
 *   حيًّا من `gov_effect_map`. وقائمةُ `consumers` أدناه تُستعمَل **فقط** لحدثٍ
 *   لم يُطلَق بعد — فالعقدُ يسبق أوّلَ إطلاقٍ ولا مقياسَ قبله. ومتى أُطلق
 *   الحدثُ صار المقيسُ هو المكتوب.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

/** أحداثُ العمودِ الفقريِّ التي تصدر عن هذه المرحلة — عقدُها قبلَ أوّلِ إطلاق */
function repair01_w4_stage_events()
{
    return array('operations.site_day.opened', 'operations.site_day.closed', 'operations.stop.registered');
}

function repair01_w4_contract_narrative()
{
    return array(

    /* ══ أحداثُ اليومِ الميدانيّ — تصدر عن `SiteDayService` ═══════════════ */
    'operations.site_day.opened' => array(
        'name' => 'فتحُ يومِ الموقع',
        'unit' => '12 إدارة الموقع',
        'screen' => 'SiteDayService::openDay',
        'trigger' => 'مدير الموقع يفتح يوم (موقع × تاريخ) بعد قراءة خطة الغد المعتمدة عند التشغيل',
        'payload' => 'company_id · site_id · project_id · day_date · opened_by · opened_at',
        'pre' => 'كيان قانوني قائم (DEC-OPEN-03) · موقع قائم · لا يوم مفتوح للحبة نفسها (uq_site_day)',
        'idem' => 'w4:site_day:{day_id} — والمفتاح الفريد (company × site × date) يمنع يومين للحبة نفسها',
        'effect' => 'operational',
        'consumers' => array(
            'site_day_shift @ 12 إدارة الموقع',
            'unit_entries @ 12 إدارة الموقع',
            'governance_watch @ إدارة المخاطر',
        ),
        'effects' => array(
            'site_day_shift ⇐ لا تفتح وردية إلا داخل يوم مفتوح · دليل: MEASURED (fk_sds_day)',
            'unit_entries ⇐ حارس القيد يقرأ حالة اليوم فيقبل أو يرفض ويقيد · دليل: MEASURED (site_day_attempt)',
            'governance_watch ⇐ إشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يمنع فتح اليوم (publishFact تعيد null صامتة إن أطفئ الجذر) — والفتح حقيقة قائمة في site_day يعاد نشرها بالمفتاح نفسه بلا تكرار',
        'comp' => 'لا تعويض: اليوم لا يحذف. الخطأ يعالج بإقفاله بملاحظة ثم فتح اليوم الصحيح',
        'src' => 'W04 §٢ · SITE-05 فتح يوم الموقع',
    ),
    'operations.site_day.closed' => array(
        'name' => 'إقفالُ يومِ الموقع',
        'unit' => '12 إدارة الموقع',
        'screen' => 'SiteDayService::closeDay',
        'trigger' => 'إقفال اليوم بعد تسليم كل ورديه بمحضرها — ولا إقفال ووردية مفتوحة',
        'payload' => 'company_id · site_id · day_date · closed_by · closed_at · close_note',
        'pre' => 'اليوم في حالة open أو reopened · صفر وردية بحالة open · فاعل معروف (chk_site_day_closed)',
        'idem' => 'w4:site_day:{day_id}:closed — والإقفال الثاني لا عملية: الحالة نفسها تعيد 200',
        'effect' => 'operational',
        'consumers' => array(
            'unit_entries @ 12 إدارة الموقع',
            'site_day_attempt @ 12 إدارة الموقع',
            'governance_watch @ إدارة المخاطر',
        ),
        'effects' => array(
            'unit_entries ⇐ كل قيد بعد الإقفال يرفض بـDAY_CLOSED · دليل: MEASURED (assertOpenForEntry)',
            'site_day_attempt ⇐ كل محاولة مرفوضة تقيد بسببها وفاعلها · دليل: MEASURED',
            'governance_watch ⇐ إشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يعيد فتح اليوم — الحالة في site_day هي الحقيقة والحدث إشعارها',
        'comp' => 'إعادة الفتح بسبب مكتوب (reopenDay) — والقيد chk_site_day_reopen يرد إعادة فتح بلا سبب',
        'src' => 'W04 §٢ · SITE-16 تقرير إقفال يوم الموقع',
    ),
    'operations.stop.registered' => array(
        'name' => 'تسجيلُ واقعةِ توقّف',
        'unit' => '11 إدارة التشغيل',
        'screen' => 'SiteDayService::registerStop',
        'trigger' => 'أول سجل يدعي واقعة توقف بمفتاحها (كيان × يوم × وردية × معدة)',
        'payload' => 'occurrence_key · company_id · stop_date · shift · equipment_id · ops_state · hours · resp_party · obligation_type · authority',
        'pre' => 'مفتاح واقعة صالح (40 محرفا) · سجل مصدر معروف (unit_time_log أو timesheet) · لا صف قائم للمفتاح نفسه',
        'idem' => 'occurrence_key — فهرس فريد: من ادعاها أولا ملكها والثاني مرآة بفارقها ولا صف ثان',
        'effect' => 'operational',
        'consumers' => array(
            'ops_stop_source @ 11 إدارة التشغيل',
            'timesheet @ 11 إدارة التشغيل',
            'governance_watch @ إدارة المخاطر',
        ),
        'effects' => array(
            'ops_stop_source ⇐ قراءة كل سجل مصدر للواقعة نفسها بفارقها المقيس · دليل: MEASURED',
            'timesheet ⇐ الصف يوسم MIRROR أو AUTHORITY فلا تحتسب ساعته مرتين · دليل: MEASURED (stop_register_role)',
            'governance_watch ⇐ إشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'سباق على المفتاح الفريد يعيد قراءة الصف القائم ولا ينشئ ثانيا — والفشل غير ذلك يعيد 500 بلا صف',
        'comp' => 'لا محو: الواقعة تبقى ويحسم فارقها عند مالك الانحراف (W11) — والحسم يكتب في variance_rule لا يدهس الساعات',
        'src' => 'W04 §٤-٣ · OPS-09 قرارات التوقف · SITE-14 قرار الاستعداد أو التعطل',
    ),

    /* ══ أحداثٌ حيّةٌ قائمةٌ على `timesheet` و`unit_entry` ═════════════════ */
    'equipment.hour_logged' => array(
        'name' => 'تسجيلُ ساعةِ معدّة',
        'unit' => '11 إدارة التشغيل',
        'screen' => 'Timesheet/timesheet.php',
        'trigger' => 'اعتماد سجل التايم شيت اليومي لمعدة ووردية وتاريخ',
        'payload' => 'company_id · timesheet_id · equipment_id · date · shift · executed_hours · standby_hours',
        'pre' => 'سجل يومي واحد لا يتكرر للحبة (معدة × وردية × يوم) · ساعات غير سالبة · وردية من مفردات الجسر',
        'idem' => 'timesheet:{id}:hour_logged — وإعادة الاعتماد تعيد المرجع القائم',
        'effect' => 'financial',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'خطأ الإسقاط المالي يترك الحقيقة قائمة في الجذر ويعاد بسياسة الاشتراك — ولا تكتب ساعة ثانية',
        'comp' => 'التصحيح بقيد عكسي بمرجعه (unit_corrections) لا بتعديل الساعة الأصلية',
        'src' => 'W04 §٢ · OPS-06 التايم شيت اليومي',
    ),
    'revenue.unit.recognized' => array(
        'name' => 'الاعترافُ بوحدةِ إيراد',
        'unit' => '11 إدارة التشغيل',
        'screen' => 'Timesheet/timesheet.php',
        'trigger' => 'اكتمال سلسلة اعتماد الوحدة حتى المرحلة المالية',
        'payload' => 'company_id · entry_id · contract_id · unit_type · qty · qty_billable · period',
        'pre' => 'قيد ميداني بوردية (chk_ue_w4_shift) · اعتماد خماسي مكتمل · الكمية محكومة بقرار لا بإدخال',
        'idem' => 'unit_entry:{id}:revenue — والعطالة على fin_event_links',
        'effect' => 'financial',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'فشل الإسقاط المالي يترك الاعتراف معلقا في الجذر ويعاد — ولا يفوتر مرتين',
        'comp' => 'إشعار دائن/مدين بمرجعه (M-02) لا حذف الاعتراف',
        'src' => 'W04 §٢ · OPS-08 اعتماد الوحدات',
    ),
    'attribution.decided' => array(
        'name' => 'حسمُ إسنادِ التوقّف',
        'unit' => '11 إدارة التشغيل',
        'screen' => 'Operations/stops_unattributed.php',
        'trigger' => 'قرار من يتحمل التوقف من مصفوفة التزامات عقد العميل',
        'payload' => 'company_id · occurrence_key · resp_party · obligation_type · billable · decided_by',
        'pre' => 'واقعة توقف مسجلة بمفتاحها · مسؤول من مصفوفة الالتزامات لا من نص حر · فاعل غير مدخل الواقعة',
        'idem' => 'stop:{occurrence_key}:attribution — والقرار الثاني يعدل ولا يضيف',
        'effect' => 'financial',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'فشل الإسقاط يبقي الواقعة pending فوق مهلتها فتصعد — ولا تحسم صامتة',
        'comp' => 'إعادة الحسم بقرار مسجل بفاعله ووقته — والقرار السابق يبقى في السجل',
        'src' => 'W04 §٢ · OPS-09 قرارات التوقف',
    ),
    'capacity.consumed' => array(
        'name' => 'استهلاكُ قدرةٍ تعاقدية',
        'unit' => '11 إدارة التشغيل',
        'screen' => 'Operations/shift_entry.php',
        'trigger' => 'قيد وحدة يستهلك التزام تغطية قائما',
        'payload' => 'company_id · entry_id · cap_obligation_id · cap_measure_code · qty',
        'pre' => 'التزام تغطية قائم بحالة confirmed · قيد ميداني معتمد · كمية موجبة',
        'idem' => 'unit_entry:{id}:capacity — والعطالة على cap_coverage_id',
        'effect' => 'operational',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'فشل الاستهلاك لا يلغي القيد — الفجوة تفتح وتصعد بمهلتها',
        'comp' => 'عكس الاستهلاك بقيد عكسي مرتبط بالقيد الأصلي',
        'src' => 'W04 §٢ · OPS-07 توزيع زمن الوردية',
    ),
    'operations.unit.submitted' => array(
        'name' => 'رفعُ قيدِ وحدة',
        'unit' => '12 إدارة الموقع',
        'screen' => 'Operations/shift_entry.php',
        'trigger' => 'رفع صف الوحدة من draft إلى submitted',
        'payload' => 'company_id · entry_id · entry_date · shift · equipment_id · unit_type · qty · entered_by',
        'pre' => 'يوم موقع مفتوح ووردية مفتوحة (assertOpenForEntry) · قيد ميداني بوردية · كمية موجبة',
        'idem' => 'unit_entry:{id}:submitted',
        'effect' => 'operational',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'الرفع الفاشل يبقي الصف draft — ولا صف نصف مرفوع',
        'comp' => 'الإرجاع (returned) لافتة لا مرحلة — والصف يعود draft بمرجع الإرجاع',
        'src' => 'W04 §٢ · SITE-07 تسجيل وحدات اليوم',
    ),
    'operations.unit.stage_approved' => array(
        'name' => 'اعتمادُ مرحلةٍ في سلسلةِ الوحدة',
        'unit' => '12 إدارة الموقع',
        'screen' => 'Timesheet/timesheet_details.php',
        'trigger' => 'قرار اعتماد في مرحلة من مراحل الاعتماد الخماسي',
        'payload' => 'company_id · entry_id · round_no · stage · decision · actor_id',
        'pre' => 'المرحلة السابقة معتمدة · الفاعل ليس مدخل القيد (فصل الواجبات) · الجولة قائمة',
        'idem' => 'unit_approval:{entry_id}:{round_no}:{stage}',
        'effect' => 'operational',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'فشل التسجيل يبقي المرحلة غير معتمدة — ولا تقدم صامت للحالة',
        'comp' => 'الرفض أو الإرجاع بجولة جديدة — ولا محو لقرار سابق',
        'src' => 'W04 §٢ · SITE-08 محضر اعتماد الموقع',
    ),
    'operations.unit.chain_completed' => array(
        'name' => 'اكتمالُ سلسلةِ اعتمادِ الوحدة',
        'unit' => '12 إدارة الموقع',
        'screen' => 'Timesheet/timesheet_details.php',
        'trigger' => 'اعتماد المرحلة الأخيرة في السلسلة الخماسية',
        'payload' => 'company_id · entry_id · round_no · final_stage · completed_at',
        'pre' => 'كل مراحل الجولة معتمدة · لا مرحلة معلقة ولا مرجعة',
        'idem' => 'unit_entry:{id}:chain:{round_no}',
        'effect' => 'financial',
        'consumers' => array(),
        'effects' => array(),
        'fail' => 'فشل الإسقاط يترك السلسلة مكتملة بلا اعتراف — ويعاد بسياسة الاشتراك',
        'comp' => 'العكس بقيد تصحيح بالسلسلة الثلاثية (Operations/unit_correction.php)',
        'src' => 'W04 §٢ · OPS-08 اعتماد الوحدات',
    ),
    );
}
