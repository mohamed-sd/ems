<?php
/**
 * tools/lib/repair01_w8_contracts.php — عقودُ الأثرِ لأحداثِ المبيعاتِ والموردين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** (§٥ · §٧): العقدُ يحمل المصدرَ
 *   والمحفِّزَ والحمولةَ الدنيا و**كلَّ مستهلكٍ فعليٍّ بالاسم** وأثرَ كلٍّ منهم
 *   والشروطَ المسبقةَ وسياسةَ الإعادةِ ومفتاحَ منعِ التكرارِ وسلوكَ الفشلِ
 *   والتعويض.
 *
 * ◆ **والمستهلكُ يُسمَّى ولا يُعمَّم**: «كلُّ المستهلكين» ليست قائمةَ مستهلكين.
 *
 * ◆ **والأحداثُ العشرةُ هنا هي ما تنشره الشيفرةُ فعلًا** — مُثبَتةٌ من القرصِ
 *   في `repair01_w8_stage_event_emitters()` وتعيد البوّابةُ إثباتَها. فالوحدتانِ
 *   **مرجعيّتانِ** (§19) وعقدُ الأثرِ هنا **توثيقُ ما يجري** لا تصميمُ ما سيجري.
 *
 * ◆ **و`publishFact` لا `publish` في مسارِ المستخلص** (قرارُ المالك 2026-07-28):
 *   المروحةُ تعترف بالإيرادِ عند الوحدة، والمستخلصُ **يفوتر** — فنشرُ المستخلصِ
 *   قيدَ إيرادٍ ثانيًا ازدواجٌ صريح. ولذلك أثرُ `billing.claim.invoiced` عند
 *   الماليّةِ **ذمّةٌ** لا قيدُ إيراد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); }

function repair01_w8_contract_narrative()
{
    return array(

    /* ══ 01 المبيعات · دورةُ العقد ═══════════════════════════════════════ */
    'contract.signed' => array(
        'name' => 'توقيعُ عقدِ العميل',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/contracts.php · ContractStateMachine::transition ⇐ ContractSignedEffects::apply',
        'trigger' => 'العقد ينتقل الى موقع في الة الحالة — والتوقيع واقعة بمستندها لا تحديث عمود',
        'payload' => 'company_id · contract_id · contract_status · signed_at · actor · amount · currency',
        'pre' => 'عقد قائم في نطاق الكيان · انتقال مشروع في الة الحالة · فاعل معروف · مستند توقيع مرجعه محفوظ',
        'idem' => 'contract:signed:{contract_id} — والمفتاح كيان بعقد فلا توقيعان لواقعة واحدة',
        'effect' => 'commercial',
        'consumers' => array(
            'op_containers @ 11 إدارة التشغيل',
            'fin_financial_events @ 05 الإدارة المالية',
            'contract_snapshots @ 01 إدارة المبيعات',
            'work_items @ 00 مساحة عملي',
        ),
        'effects' => array(
            'op_containers ⇐ يفتح راس حاوية رئيسية ولا حاوية تفتح الا من عقد نافذ · دليل: MEASURED (op_containers.contract_id)',
            'fin_financial_events ⇐ يدخل الالتزام بنمط المروحة والعطالة بوصلة fin_event_links · دليل: MEASURED (fin_effect_map.contract_signed)',
            'contract_snapshots ⇐ لقطة العقد عند التوقيع مرجعا للملاحق اللاحقة · دليل: MEASURED (contract_snapshots.contract_id)',
            'work_items ⇐ بند عمل للتشغيل بفتح الحاوية · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي التوقيع — الصف في contracts هو الحقيقة والحدث اشعارها؛ ويعاد النشر بالمفتاح نفسه بلا تكرار',
        'comp' => 'لا محو: العقد الموقع يعدل بملحق او يفسخ بواقعة مستقلة — ولا يعاد الى مسودة',
        'src' => 'W08 §٤ · SAL-11 سجل عقود المشاريع · H-02 الة حالة العقد',
    ),
    'contract.state.changed' => array(
        'name' => 'انتقالُ حالةِ عقدِ العميل',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/contract_lifecycle.php · ContractStateMachine::transition',
        'trigger' => 'انتقال مشروع في قائمة السماح — والقائمة تمنع الانتقال غير المصرح به قبل النشر لا بعده',
        'payload' => 'company_id · contract_id · from_state · to_state · reason · actor · occurred_at',
        'pre' => 'عقد قائم · الانتقال في قائمة السماح · سبب مكتوب للتعليق والفسخ · pause_state_before محفوظة عند التعليق وحده',
        'idem' => 'contract:state:{contract_id}:{to_state}:{occurred_at} — والواقعة بزمنها فلا يبتلع تكرار انتقالا حقيقيا',
        'effect' => 'commercial',
        'consumers' => array(
            'contract_lifecycle_events @ 01 إدارة المبيعات',
            'op_containers @ 11 إدارة التشغيل',
            'claims @ 01 إدارة المبيعات',
            'risk_signals @ 09 إدارة المخاطر',
        ),
        'effects' => array(
            'contract_lifecycle_events ⇐ سطر واقعة بحالتيه وسببه وفاعله · دليل: MEASURED (contract_lifecycle_events.contract_id)',
            'op_containers ⇐ التعليق يوقف استهلاك الحاوية ولا يقفلها · دليل: DECLARED_ACTIVE',
            'claims ⇐ لا مستخلص جديد على عقد مقفل او مصفى · دليل: MEASURED (claim_gate)',
            'risk_signals ⇐ الفسخ والتعليق اشارتا مخاطر تجارية · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يرجع الحالة — الصف في contracts هو الحقيقة؛ ويعاد النشر بمفتاحه',
        'comp' => 'التصحيح انتقال معاكس مشروع بسببه المكتوب — ولا تعديل رجعي على عمود الحالة',
        'src' => 'W08 §٤ · SAL-11 · SAL-19 · H-02 اثنتا عشرة حالة',
    ),
    'contract.advance.received' => array(
        'name' => 'استلامُ دفعةٍ مقدَّمةٍ على العقد',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/claims.php · advance_record ⇐ Contracts/advance_helpers.php',
        'trigger' => 'المالية تسجل مقدما مقبوضا على عقد نافذ — والاستقطاع لاحقا من المستخلصات بسقفه',
        'payload' => 'company_id · contract_id · advance_no · amount · currency · received_at · actor',
        'pre' => 'عقد نافذ · مبلغ موجب · عملة العقد · رقم مقدم لا يتكرر في الكيان',
        'idem' => 'contract:advance:{advance_no} — والرقم مفتاح منع التكرار لا المعرف الداخلي',
        'effect' => 'financial',
        'consumers' => array(
            'contract_advances @ 01 إدارة المبيعات',
            'claim_lines @ 01 إدارة المبيعات',
            'fin_receivables @ 05 الإدارة المالية',
        ),
        'effects' => array(
            'contract_advances ⇐ رصيد المقدم يرتفع والمستردُّ يخصم منه · دليل: MEASURED (contract_advances.contract_id)',
            'claim_lines ⇐ بند استقطاع سالب بسقف الاقل من النسبة والرصيد · دليل: MEASURED (advance_recovery)',
            'fin_receivables ⇐ المقدم يقلل صافي المطالبة لا الاعتراف · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يمحو القبض — والصف في contract_advances دليل قائم يعاد نشره',
        'comp' => 'الالغاء واقعة مستقلة (advance_cancel) بسببها — ولا حذف لصف قبض',
        'src' => 'W08 §٤ · SAL-11 · M-01 دفتر مقدم العقد',
    ),

    /* ══ 01 المبيعات · المستخلصُ والفوترة ════════════════════════════════ */
    'billing.claim.invoiced' => array(
        'name' => 'إجازةُ المستخلصِ وفوترتُه',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/claims.php · claim_approve ⇐ Contracts/claim_helpers.php',
        'trigger' => 'مستخلص مقدم يجيزه المخول — والاجازة هي التي تفتح الذمة لا انشاء المستخلص',
        'payload' => 'company_id · claim_id · contract_id · client_id · invoice_no · net_amount · tax_amount · currency',
        'pre' => 'مستخلص مقدم بحالة submitted · عقد قائم غير مقفل · بند واحد على الاقل يسنده · مجيز غير مقدمه',
        'idem' => 'claim:invoiced:{claim_id} — ورقم الفاتورة فريد في الكيان فوقه حارس ثان',
        'effect' => 'financial',
        'consumers' => array(
            'fin_receivables @ 05 الإدارة المالية',
            'fin_client_statements @ 05 الإدارة المالية',
            'credit_debit_notes @ 01 إدارة المبيعات',
            'contract_guarantees @ 01 إدارة المبيعات',
        ),
        'effects' => array(
            'fin_receivables ⇐ ذمة مدينة على العميل بصافي المستخلص وضريبته — **لا قيد ايراد** فالمروحة اعترفت عند الوحدة · دليل: MEASURED (claims.receivable_id)',
            'fin_client_statements ⇐ طبقة المستخلصات في كشف العميل ترتفع · دليل: MEASURED (ClientStatementService::LAYERS)',
            'credit_debit_notes ⇐ الاشعار الدائن لا يفتح الا على مستخلص مفوتر · دليل: MEASURED (cdnote_for_claim)',
            'contract_guarantees ⇐ رصيد المحتجز يتراكم من retention_amount · دليل: MEASURED (ContractGuaranteeService)',
        ),
        'fail' => 'فشل فتح الذمة لا يلغي الاجازة — والمستخلص المفوتر بلا ذمة عطب مقيس يرفعه فحص الانحدار SAL_CLAIM_FIN_HANDOVER',
        'comp' => 'التصحيح اشعار دائن او مدين بمرجعه — ولا تعديل على مبلغ مستخلص مفوتر',
        'src' => 'W08 §٤ · SAL-18 المطالبات والتسليم للمالية · §48',
    ),
    'billing.note.credit_issued' => array(
        'name' => 'إصدارُ إشعارٍ دائن',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/claims.php · note_approve ⇐ Contracts/note_helpers.php',
        'trigger' => 'تصحيح ينقص مستخلصا مفوترا — والاشعار واقعة مستقلة لا تعديل على المستخلص',
        'payload' => 'company_id · note_id · claim_id · kind · amount · currency · reason · actor',
        'pre' => 'مستخلص مفوتر او محصل · مبلغ لا يتجاوز صافيه · سبب مكتوب · معتمد غير منشئه',
        'idem' => 'note:{note_id} — والاشعار واقعة واحدة لا تتكرر',
        'effect' => 'financial',
        'consumers' => array(
            'fin_receivables @ 05 الإدارة المالية',
            'claims @ 01 إدارة المبيعات',
            'fin_client_statements @ 05 الإدارة المالية',
        ),
        'effects' => array(
            'fin_receivables ⇐ الذمة تنقص بمبلغ الاشعار وقد تصير مسددة جزئيا · دليل: MEASURED (cdnote_approved_total)',
            'claims ⇐ صافي المستخلص المؤثر يقرا مطروحا منه الاشعارات المعتمدة · دليل: MEASURED (cdnote_claim_net)',
            'fin_client_statements ⇐ طبقة التصحيح في كشف العميل · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يلغي الاشعار — ويعاد النشر بمفتاحه',
        'comp' => 'الالغاء واقعة (cdnote_cancel) بسببها — ولا حذف',
        'src' => 'W08 §٤ · SAL-18 · M-02 الاشعارات الدائنة والمدينة',
    ),
    'billing.note.debit_issued' => array(
        'name' => 'إصدارُ إشعارٍ مدين',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/claims.php · note_approve ⇐ Contracts/note_helpers.php',
        'trigger' => 'تصحيح يزيد مستخلصا مفوترا — والاشعار واقعة مستقلة لا تعديل على المستخلص',
        'payload' => 'company_id · note_id · claim_id · kind · amount · currency · reason · actor',
        'pre' => 'مستخلص مفوتر او محصل · سبب مكتوب · معتمد غير منشئه',
        'idem' => 'note:{note_id} — والاشعار واقعة واحدة لا تتكرر',
        'effect' => 'financial',
        'consumers' => array(
            'fin_receivables @ 05 الإدارة المالية',
            'claims @ 01 إدارة المبيعات',
        ),
        'effects' => array(
            'fin_receivables ⇐ الذمة ترتفع بمبلغ الاشعار المدين · دليل: MEASURED (cdnote_approved_total)',
            'claims ⇐ صافي المستخلص المؤثر يقرا مضافا اليه الاشعار المدين · دليل: MEASURED (cdnote_claim_net)',
        ),
        'fail' => 'فشل النشر لا يلغي الاشعار — ويعاد النشر بمفتاحه',
        'comp' => 'الالغاء واقعة (cdnote_cancel) بسببها — ولا حذف',
        'src' => 'W08 §٤ · SAL-18 · M-02',
    ),
    'retention.released' => array(
        'name' => 'ردُّ ضمانِ حسنِ التنفيذ',
        'unit' => '01 إدارة المبيعات التعاقدية والعقود',
        'screen' => 'Contracts/contract_guarantees.php · claim_retention_release ⇐ Contracts/claim_helpers.php',
        'trigger' => 'قرار بشري بعد تجاوز نهاية العقد — والنظام يحتجز ويعرض الرصيد والرد قرار موثق',
        'payload' => 'company_id · contract_id · claim_id · released · actual_end · released_by · currency',
        'pre' => 'عقد تجاوز actual_end · رصيد محتجز موجب · الدور المخول وحده · لم يرد سلفا',
        'idem' => 'retention:release:contract:{contract_id} — ورد واحد لكل عقد',
        'effect' => 'financial',
        'consumers' => array(
            'claims @ 01 إدارة المبيعات',
            'contract_guarantees @ 01 إدارة المبيعات',
            'fin_receivables @ 05 الإدارة المالية',
        ),
        'effects' => array(
            'claims ⇐ مستخلص ختامي جديد بفترة يوم الانتهاء يمر بدورة الحياة كاملة · دليل: MEASURED (claims.notes retention_release)',
            'contract_guarantees ⇐ رصيد المحتجز يصير صفرا · دليل: MEASURED (claim_retention_balance)',
            'fin_receivables ⇐ **لا قيد ايراد جديد** — الاحتجاز خصم مطالبة لا خصم اعتراف فرده لا يخلق ايرادا · دليل: MEASURED (claim_lines.event_id IS NULL)',
        ),
        'fail' => 'فشل النشر لا يلغي المستخلص الختامي — والصف دليل قائم',
        'comp' => 'لا تراجع: الرد كامل ولا خصم للغرامات المعلقة فقد خصمت في مستخلصها',
        'src' => 'W08 §٤ · SAL-18 · SAL-19 · ق-20',
    ),

    /* ══ 02 الموردون ═════════════════════════════════════════════════════ */
    'procurement.supplier_contract.state_changed' => array(
        'name' => 'انتقالُ حالةِ عقدِ المورد',
        'unit' => '02 إدارة الموردين',
        'screen' => 'Suppliers/supplierscontracts.php · SupplierContractService::transition',
        'trigger' => 'انتقال مشروع على راس السجل الموحد — والجدول الحي يسقط عليه بمصدره لا ينازعه',
        'payload' => 'company_id · contract_id · supplier_id · from_state · to_state · reason · actor',
        'pre' => 'عقد مورد قائم في السجل الموحد · الانتقال في قائمة السماح · مورد قائم · سبب مكتوب للفسخ',
        'idem' => 'sup:contract:state:{contract_id}:{to_state} — والانتقال الى الحالة نفسها لا يتكرر',
        'effect' => 'commercial',
        'consumers' => array(
            'supplier_contracts @ 02 إدارة الموردين',
            'supplier_contract_lines @ 02 إدارة الموردين',
            'seat_assignments @ 11 إدارة التشغيل',
            'settlements @ 02 إدارة الموردين',
        ),
        'effects' => array(
            'supplier_contracts ⇐ الحالة تتقدم على راس السجل الموحد وحده · دليل: MEASURED (supplier_contracts.state)',
            'supplier_contract_lines ⇐ لا بند جديد على عقد مقفل او مصفى · دليل: DECLARED_ACTIVE',
            'seat_assignments ⇐ الفسخ يخلي خانات المورد ويفتح احلالا · دليل: DECLARED_ACTIVE',
            'settlements ⇐ لا تسوية جديدة على عقد مصفى · دليل: DECLARED_ACTIVE',
        ),
        'fail' => 'فشل النشر لا يرجع الحالة — والصف في supplier_contracts هو الحقيقة',
        'comp' => 'التصحيح انتقال معاكس مشروع بسببه — ولا تعديل رجعي',
        'src' => 'W08 §٤ · SUP-08 سجل عقود الموردين · H-08 السجل الموحد',
    ),
    'procurement.supplier_contract.termination_revoked' => array(
        'name' => 'إلغاءُ فسخِ عقدِ المورد',
        'unit' => '02 إدارة الموردين',
        'screen' => 'Suppliers/supplierscontracts.php · SupplierContractService::revokeTermination',
        'trigger' => 'قرار مخول يرجع عقدا مفسوخا الى سريانه — والالغاء واقعة بفاعلها لا محو للفسخ',
        'payload' => 'company_id · contract_id · supplier_id · revoked_from · restored_to · reason · actor',
        'pre' => 'عقد في حالة فسخ · مخول غير من فسخه · سبب مكتوب · لم تقفل تسويات الفسخ',
        'idem' => 'sup:contract:revoke:{contract_id} — والالغاء واقعة واحدة لكل فسخ',
        'effect' => 'commercial',
        'consumers' => array(
            'supplier_contracts @ 02 إدارة الموردين',
            'supplier_contract_closures @ 02 إدارة الموردين',
        ),
        'effects' => array(
            'supplier_contracts ⇐ الحالة تعود الى ما قبل الفسخ بقاعدة مكتوبة · دليل: MEASURED (supplier_contracts.state)',
            'supplier_contract_closures ⇐ اقفال التصفية المفتوح يلغى بسببه ولا يحذف · دليل: MEASURED (supplier_contract_closures.contract_id)',
        ),
        'fail' => 'فشل النشر لا يرجع الفسخ — والصف هو الحقيقة',
        'comp' => 'لا محو: الفسخ والغاؤه واقعتان قائمتان في السجل',
        'src' => 'W08 §٤ · SUP-28 الملاحق والتصفية والاغلاق',
    ),
    'settlement.approved' => array(
        'name' => 'اعتمادُ تسويةِ المورد',
        'unit' => '02 إدارة الموردين',
        'screen' => 'Suppliers/settlements.php · SettlementService::approve',
        'trigger' => 'تسوية مراجعة يعتمدها المخول — والاعتماد هو الذي يفتح طلب الدفع او الذمة',
        'payload' => 'company_id · settlement_id · party_type · party_ref · period · currency · net_amount · net_direction',
        'pre' => 'تسوية بحالة review · طرف قائم في سجله · لا اعتراض مفتوح · معتمد غير معدها',
        'idem' => 'settlement:approved:{settlement_id} — واعتماد واحد لكل تسوية',
        'effect' => 'financial',
        'consumers' => array(
            'fin_requests @ 06 إدارة الخزينة',
            'fin_dues @ 05 الإدارة المالية',
            'fin_receivables @ 05 الإدارة المالية',
            'supplier_advance_recoveries @ 02 إدارة الموردين',
        ),
        'effects' => array(
            'fin_requests ⇐ الموجب يفتح طلب دفع ولا صرف بلا اعتماد · دليل: MEASURED (settlements.payment_request_id)',
            'fin_dues ⇐ استحقاق المورد يقفل بالتسوية المعتمدة · دليل: DECLARED_ACTIVE',
            'fin_receivables ⇐ **السالب يفتح ذمة مدينة على المورد ولا يبتلع** · دليل: MEASURED (settlements.receivable_due_id)',
            'supplier_advance_recoveries ⇐ المستقطع من السلفة يقيد بسقفه · دليل: MEASURED (supplier_advance_recoveries)',
        ),
        'fail' => 'فشل النشر لا يرجع الاعتماد — والصف في settlements هو الحقيقة ويعاد النشر بمفتاحه',
        'comp' => 'التصحيح تسوية معدلة باصدار جديد — ولا تعديل على تسوية معتمدة',
        'src' => 'W08 §٤ · SUP-22 التسويات وكشف الحساب · SUP-23 طلبات الدفع',
    ),
    );
}
