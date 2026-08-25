<?php
/**
 * tools/lib/repair01_w5_contracts.php — عقودُ الأثرِ لأحداثِ دورةِ الأصلِ والقوى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** (§٥ · §٧): العقدُ يحمل المصدرَ
 *   والمحفِّزَ والحمولةَ الدنيا و**كلَّ مستهلكٍ فعليٍّ بالاسم** وأثرَ كلٍّ منهم
 *   والشروطَ المسبقةَ وسياسةَ الإعادةِ ومفتاحَ منعِ التكرارِ وسلوكَ الفشلِ
 *   والتعويض.
 *
 * ◆ **وكلُّ أحداثِ هذا النطاقِ لم تُطلَق بعدُ ولا مرّةً واحدة**: المقيسُ حيًّا
 *   **صفرُ صفٍّ** في `ems_business_events` لأيٍّ من كياناتِ الأصل — مع ٢١٩ أصلًا
 *   في السجلّ. فدورةُ الأصلِ اليومَ **لا تُصدر حقيقةً واحدةً يستهلكها أحد**،
 *   والعقدُ هنا يُكتب **قبل أوّلِ إطلاق** كما يوجب §٥. ومتى أُطلق الحدثُ صار
 *   المقيسُ (`repair01_w3_measure_consumers`) هو المكتوب.
 *
 * ◆ **والمستهلكُ يُسمَّى ولا يُعمَّم**: «كلُّ المستهلكين» ليست قائمةَ مستهلكين.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

function repair01_w5_contract_narrative()
{
    return array(

    /* ══ ب · دخولُ الأصل ═════════════════════════════════════════════════ */
    'fleet.asset.intake_requested' => array(
        'name' => 'طلبُ إدخالِ أصل',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_intake.php · AssetLifecycleService::openIntake',
        'trigger' => 'ادارة طالبة ترفع طلب ادخال اصل الى الاسطول — وهنا تبدا دورة الاصل قبل الكرت لا بعده',
        'payload' => 'company_id · intake_id · intake_no · requested_dept · asset_kind · source_type · supplier_id · requested_by · requested_at',
        'pre' => 'كيان قانوني قائم (DEC-OPEN-03) · رقم طلب لا يتكرر في الكيان (uq_asset_intake) · ادارة طالبة برمزها المعياري · طالب معروف',
        'idem' => 'w5:intake:{intake_id} — والمفتاح الفريد (كيان × رقم الطلب) يمنع طلبين للحبة نفسها',
        'effect' => 'operational',
        'consumers' => array(
            'asset_source_check @ 04 إدارة الأسطول والأصول',
            'asset_inspection_order @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'asset_source_check ⇐ يفتح محل واقعة التحقق من المصدر ولا تحقق بلا طلب · دليل: MEASURED (fk_asc_intake)',
            'asset_inspection_order ⇐ امر تفتيش الدخول يعلق بالطلب ولا يصدر قبل التحقق · دليل: MEASURED (chk_aio_target)',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يمنع الطلب من الوقوع — الحالة في asset_intake هي الحقيقة والحدث اشعارها؛ ويعاد النشر بالمفتاح نفسه بلا تكرار',
        'comp' => 'لا محو: الطلب يرفض بسببه المكتوب (chk_ai_reject) ولا يحذف — والرفض دليل يراجع',
        'src' => 'W05 §٥ · FLEET-03 طلب إدخال الأصل',
    ),
    'fleet.asset.source_verified' => array(
        'name' => 'التحقُّقُ من مصدرِ الأصل',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_intake.php · AssetLifecycleService::verifySource',
        'trigger' => 'المستشار القانوني يسجل واقعة تحقق على طلب ادخال — مجتازة او مخفقة',
        'payload' => 'company_id · check_id · intake_id · check_seq · doc_type · doc_ref · owner_declared · owner_legal · verify_result · verified_by',
        'pre' => 'طلب ادخال قائم · المجتازة بمرجع مستند مكتوب (chk_asc_evid) · المخفقة بسبب مكتوب (chk_asc_fail) · قاعدة تحقق مسماة (chk_asc_rule)',
        'idem' => 'w5:source_check:{check_id} — والمفتاح الفريد (الطلب × ترتيب الواقعة) يمنع واقعتين بالترتيب نفسه',
        'effect' => 'operational',
        'consumers' => array(
            'asset_intake @ 04 إدارة الأسطول والأصول',
            'equipments @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'asset_intake ⇐ الحالة تتقدم الى source_verified او الى rejected بسببها · دليل: MEASURED (state_rule)',
            'equipments ⇐ لا ينشا كرت اصل قبل اجتياز هذا التحقق — والمحاولة ترد SOURCE_NOT_VERIFIED · دليل: MEASURED (issueCard)',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الواقعة — والصف في asset_source_check دليل قائم يعاد نشره بمفتاحه',
        'comp' => 'لا تعديل رجعي: واقعة تحقق ثانية بترتيب جديد، والاولى تبقى في السجل',
        'src' => 'W05 §٥ · FLEET-04 التحقق من المصدر',
    ),
    'fleet.asset.inspection_ordered' => array(
        'name' => 'إصدارُ أمرِ تفتيش',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_intake.php · AssetLifecycleService::orderInspection',
        'trigger' => 'مسؤول الاسطول يصدر امر تفتيش باحد الاسباب الخمسة — والتفتيش يبدا بامر لا بزيارة',
        'payload' => 'company_id · order_id · order_no · intake_id او equipment_id · reason · due_date · ordered_by',
        'pre' => 'هدف قائم: طلب ادخال او اصل (chk_aio_target) · سبب من الخمسة المعيارية · امر الدخول بعد اجتياز التحقق (SOURCE_NOT_VERIFIED) · رقم امر لا يتكرر',
        'idem' => 'w5:insp_order:{order_id} — والمفتاح الفريد (كيان × رقم الامر)',
        'effect' => 'operational',
        'consumers' => array(
            'mnt_inspection @ 14 إدارة الصيانة',
            'asset_intake @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'mnt_inspection ⇐ الصيانة تستقبل الامر وتنفذه ببطاقة محفوظة (MNT-05) — والامر لا يصير منفذا بلا بطاقة · دليل: MEASURED (chk_aio_exec)',
            'asset_intake ⇐ الحالة تتقدم الى inspection_ordered ثم inspected عند ربط البطاقة · دليل: MEASURED',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الامر — والصيانة تقرا الامر من سجله لا من الحدث',
        'comp' => 'الالغاء بسبب مكتوب (chk_aio_cancel) لا الحذف — وامر جديد برقمه ان لزم',
        'src' => 'W05 §٥ · FLEET-05 أمر التفتيش · MNT-05 أوامر التفتيش الواردة من الأسطول',
    ),
    'fleet.asset.card_issued' => array(
        'name' => 'إصدارُ كرتِ الأصل',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_intake.php · AssetLifecycleService::issueCard',
        'trigger' => 'اكتمال التحقق من المصدر والتفتيش فيصدر كرت الاصل ويربط بطلبه',
        'payload' => 'company_id · equipment_id · intake_id · issued_by · issued_at',
        'pre' => 'واقعة تحقق مجتازة واحدة على الاقل — والمنع وظيفي في الخدمة لان CHECK لا يقرا جدولا اخر (W5-X-01) · طلب غير مرفوض · اصل قائم',
        'idem' => 'w5:card:{equipment_id} — والحالة card_issued تعيد 200 بلا كتابة ثانية',
        'effect' => 'operational',
        'consumers' => array(
            'equipments @ 04 إدارة الأسطول والأصول',
            'asset_use_right @ 03 إدارة التمويل والممولين',
            'equipment_documents @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'equipments ⇐ lifecycle_state = card_issued بقاعدتها المكتوبة في الصف · دليل: MEASURED (lifecycle_rule)',
            'asset_use_right ⇐ يفتح محل حق الاستخدام التشغيلي — ولا حق بلا اصل يحمله · دليل: MEASURED',
            'equipment_documents ⇐ يفتح محل مستندات الاصل بعلاقة ١:ن · دليل: DECLARED_ACTIVE',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الكرت — والحالة في equipments و asset_intake هي الحقيقة',
        'comp' => 'لا محو للكرت: الخطا يعالج بخروج دائم بمرجعه ثم طلب ادخال جديد',
        'src' => 'W05 §٥ · FLEET-07 كرت الأصل',
    ),
    'fleet.asset.activated' => array(
        'name' => 'تفعيلُ الأصلِ وإعادةُ الخدمة',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_intake.php · AssetLifecycleService::activateAsset',
        'trigger' => 'قرار تفعيل بعد اصدار الكرت — وهو الذي ينقل الاصل الى نشط',
        'payload' => 'company_id · equipment_id · intake_id · activated_by · activated_at',
        'pre' => 'الحالة card_issued حصرا — والتفعيل قبلها يرد CARD_NOT_ISSUED',
        'idem' => 'w5:activate:{equipment_id} — والتفعيل الثاني لا عملية',
        'effect' => 'operational',
        'consumers' => array(
            'asset_assignment @ 04 إدارة الأسطول والأصول',
            'timesheet @ 11 إدارة التشغيل',
            'asset_readiness @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'asset_assignment ⇐ اصل لم يفعل لا يسند — والمحاولة ترد ASSET_NOT_ACTIVE · دليل: MEASURED',
            'timesheet ⇐ ساعة تشغيل لا تقيد الا على اصل نشط — الاثر التجاري: استحقاق قابل للفوترة · دليل: DECLARED_ACTIVE',
            'asset_readiness ⇐ الجاهزية تشتق للاصل النشط بحالته في اخر يوم من الفترة · دليل: MEASURED (lifecycle_state)',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يعيد الاصل الى card_issued — الحالة في الصف هي الحقيقة',
        'comp' => 'الايقاف بخروج مؤقت بسببه لا بتراجع صامت عن التفعيل',
        'src' => 'W05 §٥ · FLEET-11 التفعيل وإعادة الخدمة',
    ),

    /* ══ حقُّ الاستخدامِ والإسناد ═════════════════════════════════════════ */
    'fleet.asset.use_right_granted' => array(
        'name' => 'منحُ حقِّ استخدامٍ تشغيليّ',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_use_rights.php · AssetLifecycleService::grantUseRight',
        'trigger' => 'تسجيل حصة حائز واحد في فترة واحدة — والملكية متعاقبة لا متزامنة',
        'payload' => 'company_id · right_id · equipment_id · holder_kind · holder_key · percent · valid_from · valid_to · doc_ref · concurrency_pct',
        'pre' => 'اصل قائم · حصة في المدى (chk_aur_pct) · فترة صالحة (chk_aur_period) · قاعدة تزامن مسماة (chk_aur_rule) · مفتاح (اصل × حائز × بداية) لا يتكرر',
        'idem' => 'w5:use_right:{right_id} — والمفتاح الفريد يمنع حقين للحبة نفسها والثاني يحدث ولا يضيف',
        'effect' => 'financial',
        'consumers' => array(
            'ar_claim_invoices @ 05 الإدارة المالية',
            'asset_readiness @ 04 إدارة الأسطول والأصول',
            'asset_ownership_shares @ 03 إدارة التمويل والممولين',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'ar_claim_invoices ⇐ حائز حق الاستخدام هو من يفوتر ساعة الالة — الاثر التجاري: وجهة الايراد · دليل: DECLARED_ACTIVE',
            'asset_readiness ⇐ حائز الحق يتحمل التزام الجاهزية في فترته · دليل: DECLARED_ACTIVE',
            'asset_ownership_shares ⇐ النافذة التي يتجاوز مجموعها المئة توسم W5_CONCURRENT_CLAIM_OPEN ولا تدهس — وحسمها عند مالكها (W11) · دليل: MEASURED (concurrency_rule)',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الحق — والصف في asset_use_right هو الحقيقة',
        'comp' => 'لا محو: الحق ينتهي بفترته او يصحح بحق لاحق، والسابق يبقى دليلا',
        'src' => 'W05 §٢ · FLEET-09 حق الاستخدام التشغيلي',
    ),
    'fleet.asset.assigned' => array(
        'name' => 'إسنادُ الأصلِ لموقعٍ أو مشروع',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_assignments.php · AssetLifecycleService::assignAsset',
        'trigger' => 'قرار اسناد اصل نشط الى موقع او مشروع من تاريخ محدد',
        'payload' => 'company_id · assign_id · equipment_id · assign_kind · site_id · project_id · unit_ref · valid_from · assigned_by · decision_ref',
        'pre' => 'اصل نشط (ASSET_NOT_ACTIVE) · غير خارج خروجا دائما (ASSET_PERMANENTLY_EXITED) · هدف قائم (chk_aas_target) · مفتاح (اصل × تاريخ) لا يتكرر',
        'idem' => 'w5:assign:{assign_id} — والمفتاح الفريد (كيان × اصل × بداية) يمنع اسنادين في اليوم نفسه',
        'effect' => 'operational',
        'consumers' => array(
            'timesheet @ 11 إدارة التشغيل',
            'site_day @ 12 إدارة الموقع',
            'fleet_equipment_history @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'timesheet ⇐ ساعة الاصل تنسب الى موقع اسناده لا الى اخر موقع كتب — الاثر التجاري: وجهة تحميل التكلفة · دليل: DECLARED_ACTIVE',
            'site_day ⇐ اليوم الميداني يستقبل قيد الاصل المسند اليه (W04) · دليل: DECLARED_ACTIVE',
            'fleet_equipment_history ⇐ واقعة انتقال تسجل ولا ينشا اصل جديد والتاريخ لا يمحى · دليل: MEASURED',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الاسناد — والسابق انتهى فعلا بسببه المكتوب',
        'comp' => 'الاسناد اللاحق ينهي السابق بسببه (chk_aas_end) — ولا محو لفترة مضت',
        'src' => 'W05 §٢ · FLEET-12 التخصيص على الوحدات · FLEET-13 حركة الموقع والمشروع',
    ),

    /* ══ و · الخروج ══════════════════════════════════════════════════════ */
    'fleet.asset.exited' => array(
        'name' => 'خروجُ الأصلِ مؤقّتًا أو دائمًا',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_exit.php · AssetLifecycleService::exitAsset',
        'trigger' => 'قرار اخراج اصل من الخدمة — مؤقتا بعودة متوقعة او دائما بمرجع مالي',
        'payload' => 'company_id · exit_id · equipment_id · exit_kind · reason_code · exit_date · expected_return · disposal_kind · finance_ref · decided_by',
        'pre' => 'اصل قائم · سبب مرمز (chk_aex_reason) · المؤقت بعودة متوقعة (chk_aex_temp) · الدائم بمرجع مالي وبلا عودة متوقعة (chk_aex_perm)',
        'idem' => 'w5:exit:{exit_id} — والمفتاح الفريد (كيان × اصل × نوع × تاريخ)',
        'effect' => 'financial',
        'consumers' => array(
            'asset_assignment @ 04 إدارة الأسطول والأصول',
            'fin_assets @ 05 الإدارة المالية',
            'asset_readiness @ 04 إدارة الأسطول والأصول',
            'governance_watch @ 08 الحوكمة والالتزام',
        ),
        'effects' => array(
            'asset_assignment ⇐ كل اسناد نشط ينهى بالخروج — لا اصل في موقع وهو خارج منه · دليل: MEASURED',
            'fin_assets ⇐ الخروج الدائم اثره المالي مرجع من المالية والاسطول يوثق الواقعة — الاثر التجاري: ايقاف الاهلاك واثبات الاستبعاد · دليل: DECLARED_ACTIVE',
            'asset_readiness ⇐ حالة الاصل المشتقة تصير retired او out_temporary فتخرج من مقام الجاهزية · دليل: MEASURED (lifecycle_state)',
            'governance_watch ⇐ اشعار حوكمة — لا مستند مالي · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يعيد الاصل الى الخدمة — والصف في asset_exit هو الحقيقة',
        'comp' => 'المؤقت يعالج بتسجيل العودة؛ والدائم لا يعكس — والعائد بعده كرت جديد بطلب ادخال جديد',
        'src' => 'W05 §٥ · FLEET-21 الخروج المؤقت · FLEET-22 الخروج الدائم',
    ),
    'fleet.asset.returned' => array(
        'name' => 'عودةُ الأصلِ من خروجٍ مؤقّت',
        'unit' => '04 إدارة الأسطول والأصول',
        'screen' => 'Fleet/asset_exit.php · AssetLifecycleService::returnAsset',
        'trigger' => 'عودة الاصل فعلا من خروج مؤقت مفتوح',
        'payload' => 'company_id · exit_id · equipment_id · actual_return · decided_by · decided_at',
        'pre' => 'واقعة خروج مؤقتة مفتوحة — والدائم يرد PERMANENT_EXIT_NO_RETURN · تاريخ عودة صالح (chk_aex_ret)',
        'idem' => 'w5:return:{exit_id} — والعودة الثانية لا عملية',
        'effect' => 'operational',
        'consumers' => array(
            'equipments @ 04 إدارة الأسطول والأصول',
            'asset_assignment @ 04 إدارة الأسطول والأصول',
            'asset_readiness @ 04 إدارة الأسطول والأصول',
        ),
        'effects' => array(
            'equipments ⇐ lifecycle_state يعود active بقاعدة W5_LIFECYCLE_FROM_EXIT_RETURN · دليل: MEASURED',
            'asset_assignment ⇐ الاصل يقبل اسنادا جديدا بعد العودة ولا يقبله قبلها · دليل: MEASURED',
            'asset_readiness ⇐ يعود الى مقام الجاهزية من فترة عودته · دليل: MEASURED',
        ),
        'fail' => 'فشل النشر لا يلغي العودة — والصف يحمل actual_return دليلا',
        'comp' => 'لا محو: خطا التاريخ يصحح بقرار مسجل بفاعله ووقته',
        'src' => 'W05 §٥ · FLEET-21 الخروج المؤقت — والعودة تُسجَّل في واقعتها',
    ),
    );
}
