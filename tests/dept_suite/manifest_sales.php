<?php
/**
 * tests/dept_suite/manifest_sales.php — مانيفستُ إدارةِ المبيعاتِ التعاقديةِ والعقود
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **هذا الملفُّ بياناتٌ لا كود**. المحرّكُ (`engine.php`) لا يعرف المبيعاتِ
 *   إطلاقًا؛ كلُّ ما يخصُّها هنا. وتعميمُ الأداةِ على إدارةٍ أخرى = نسخُ هذا
 *   الملفِّ وتغييرُ محتواه، بلا لمسِ المحرّك.
 *
 * ◆ **مصدرُ قائمةِ الأسطح**: `repair01_screen_registry` حيث `owner_code='DEP-01'`
 *   و`on_disk=1` — ستةٌ وأربعون سطحًا. لا اجتهادَ في العضوية.
 *
 * ◆ **عمقُ الفحصِ يختلف بحسبِ ما يملكه السطحُ فعلًا، ويُصرَّح به**:
 *   ① `crud` كاملٌ — عرضٌ وإضافةٌ وتعديلٌ وحذفٌ واختبارٌ سالب.
 *   ② `add`  فقط — أسطحٌ تُنشئ ولا تحذف (الحذفُ فيها ليس عمليةً موجودةً أصلًا).
 *   ③ بلا `crud` — أسطحُ قراءةٍ ولوحاتٍ وتقارير. تُفحص تصييرًا وعلامةَ محتوى.
 *   وسطحٌ بلا حذفٍ يُسجَّل `NA` لا `FAIL` — وإلّا صار التقريرُ أحمرَ كذبًا.
 *
 * ◆ **الأسطحُ التابعةُ تُقاس بالأساسِ المبذور**: `{CONTRACT}` و`{QUOTATION}`
 *   و`{CLIENT}` عقدٌ وعرضٌ وعميلٌ **جددٌ فارغون** يُبذرون لهذه التشغيلةِ وحدَها.
 *   فأيُّ صفٍّ يشير إليهم صفُّنا يقينًا — ولا حاجةَ لوسمٍ نصّيٍّ في كلِّ جدول.
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'sales',
'dept_ar' => 'إدارة المبيعات التعاقدية والعقود',
'user'    => 'مشرف المبيعات',
'pass'    => '12345678',

// ── جداولُ الكنسِ بالوسمِ النصّيّ (جدول => عمودُ الوسم) ────────────────────
'sweep' => array(
    'clients'              => 'client_code',
    'products'             => 'product_code',
    'pricelists'           => 'pricelist_code',
    'units_of_measure'     => 'uom_code',
    'tenders'              => 'tender_code',
    'activities'           => 'activity_code',
    'contract_events'      => 'event_code',
    'contract_commitments' => 'commitment_code',
    'readiness_lines'      => 'readiness_code',
    'quotations'           => 'quotation_code',
    'opportunities'        => 'opp_code',
    'rate_books'           => 'name',
    'party_contacts'       => 'contact_name',
    'project'              => 'project_code',
),

// ── جداولُ الأبناءِ التي تُكنَس بمعرِّفِ العقدِ/العرضِ المبذور ──────────────
'sweep_by' => array(
    array('table' => 'contract_obligations',    'col' => 'client_contract_id', 'fix' => 'CONTRACT'),
    array('table' => 'contract_guarantees',     'col' => 'contract_id',        'fix' => 'CONTRACT'),
    array('table' => 'client_contract_lines',   'col' => 'contract_id',        'fix' => 'CONTRACT'),
    array('table' => 'sal_quotation_lines',     'col' => 'quotation_id',       'fix' => 'QUOTATION'),
    array('table' => 'sal_quotation_revisions', 'col' => 'quotation_id',       'fix' => 'QUOTATION'),
),

'screens' => array(

// ══════════════════════════════════════════════════════════════════════════
// ① العملاءُ والبياناتُ المرجعية
// ══════════════════════════════════════════════════════════════════════════

array(
  'route' => 'Clients/clients.php', 'label' => 'سجل العملاء', 'group' => 'العملاء',
  'view'  => array('must_contain' => array('clientForm')),
  'crud'  => array(
    'add' => array(
      'post' => array('client_id' => 0, 'client_code' => '{MARK}-C2', 'client_name' => 'عميلُ فحصٍ {MARK}',
        'entity_type' => 'شركة', 'sector_category' => 'تعدين', 'phone' => '0900000001',
        'email' => 'qa@test.local', 'whatsapp' => '0900000001', 'status' => 'نشط'),
      'verify' => array('table' => 'clients', 'where' => "client_code='{MARK}-C2' AND is_deleted=0"),
    ),
    'edit' => array(
      'post' => array('client_id' => '{ID}', 'client_code' => '{MARK}-C2', 'client_name' => 'عميلٌ معدَّلٌ {MARK}',
        'entity_type' => 'شركة', 'sector_category' => 'تعدين', 'phone' => '0977777777',
        'email' => 'qa@test.local', 'whatsapp' => '0900000001', 'status' => 'متوقف'),
      'verify' => array('table' => 'clients', 'where' => "id={ID} AND phone='0977777777' AND status='متوقف'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'clients', 'where' => "id={ID} AND is_deleted=0")),
    'guard' => array(
      'post' => array('client_id' => 0, 'client_code' => '{MARK}-NEG', 'client_name' => 'يجب ألا يُنشأ',
        'entity_type' => 'شركة', 'sector_category' => 'تعدين', 'phone' => '0', 'email' => 'x@y.z',
        'whatsapp' => '0', 'status' => 'نشط'),
      'verify' => array('table' => 'clients', 'where' => "client_code='{MARK}-NEG'"),
    ),
  ),
),

array(
  'route' => 'Clients/products.php', 'label' => 'كتالوج الخدمات وبنود البيع', 'group' => 'البيانات المرجعية',
  'view'  => array('must_contain' => array('product_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('prod_id' => 0, 'product_code' => '{MARK}-PRD', 'name' => 'خدمةُ فحصٍ {MARK}',
        'product_type' => 'خدمة', 'revenue_model' => 'hourly', 'default_uom' => 'ساعة',
        'standard_price' => '100', 'currency' => 'SDG', 'description' => 'بندُ فحصٍ آليّ'),
      'verify' => array('table' => 'products', 'where' => "product_code='{MARK}-PRD'"),
    ),
    'edit' => array(
      'post' => array('prod_id' => '{ID}', 'product_code' => '{MARK}-PRD', 'name' => 'خدمةٌ معدَّلةٌ {MARK}',
        'product_type' => 'خدمة', 'revenue_model' => 'hourly', 'default_uom' => 'ساعة',
        'standard_price' => '250', 'currency' => 'SDG', 'description' => 'وصفٌ معدَّل'),
      'verify' => array('table' => 'products', 'where' => "id={ID} AND standard_price=250"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'products', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('prod_id' => 0, 'product_code' => '{MARK}-NEGP', 'name' => 'يجب ألا يُنشأ',
        'product_type' => 'خدمة', 'revenue_model' => 'hourly', 'default_uom' => 'ساعة',
        'standard_price' => '1', 'currency' => 'SDG', 'description' => ''),
      'verify' => array('table' => 'products', 'where' => "product_code='{MARK}-NEGP'"),
    ),
  ),
),

array(
  'route' => 'Clients/pricelists.php', 'label' => 'قوائم التسعير المعتمدة', 'group' => 'البيانات المرجعية',
  'view'  => array('must_contain' => array('pricelist_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('pl_id' => 0, 'pricelist_code' => '{MARK}-PL', 'name' => 'قائمةُ فحصٍ {MARK}',
        'currency' => 'SDG', 'revenue_model' => 'hourly', 'base_price' => '50', 'notes' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'pricelists', 'where' => "pricelist_code='{MARK}-PL'"),
    ),
    'edit' => array(
      'post' => array('pl_id' => '{ID}', 'pricelist_code' => '{MARK}-PL', 'name' => 'قائمةٌ معدَّلةٌ {MARK}',
        'currency' => 'SDG', 'revenue_model' => 'hourly', 'base_price' => '75', 'notes' => 'معدَّل'),
      'verify' => array('table' => 'pricelists', 'where' => "id={ID} AND base_price=75"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'pricelists', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('pl_id' => 0, 'pricelist_code' => '{MARK}-NEGL', 'name' => 'يجب ألا يُنشأ',
        'currency' => 'SDG', 'revenue_model' => 'hourly', 'base_price' => '1', 'notes' => ''),
      'verify' => array('table' => 'pricelists', 'where' => "pricelist_code='{MARK}-NEGL'"),
    ),
  ),
),

array(
  'route' => 'Clients/units_of_measure.php', 'label' => 'وحدات القياس والتحويل', 'group' => 'البيانات المرجعية',
  'view'  => array('must_contain' => array('uom_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('uom_id' => 0, 'uom_code' => '{MARK}-UOM', 'name' => 'وحدةُ فحصٍ {MARK}',
        'symbol' => 'و', 'category' => 'زمن', 'factor' => '1', 'notes' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'units_of_measure', 'where' => "uom_code='{MARK}-UOM'"),
    ),
    'edit' => array(
      'post' => array('uom_id' => '{ID}', 'uom_code' => '{MARK}-UOM', 'name' => 'وحدةٌ معدَّلةٌ {MARK}',
        'symbol' => 'ز', 'category' => 'زمن', 'factor' => '2', 'notes' => 'معدَّل'),
      'verify' => array('table' => 'units_of_measure', 'where' => "id={ID} AND factor=2"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'units_of_measure', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('uom_id' => 0, 'uom_code' => '{MARK}-NEGU', 'name' => 'يجب ألا يُنشأ',
        'symbol' => 'x', 'category' => 'زمن', 'factor' => '1', 'notes' => ''),
      'verify' => array('table' => 'units_of_measure', 'where' => "uom_code='{MARK}-NEGU'"),
    ),
  ),
),

array(
  'route' => 'Clients/rate_books.php', 'label' => 'دفتر الأسعار بالشرائح', 'group' => 'البيانات المرجعية',
  'view'  => array('must_contain' => array('rb_action')),
  'crud'  => array(
    'add' => array(
      'post' => array('rb_action' => 'save_book', 'book_id' => 0, 'name' => '{MARK} دفترُ فحص',
        'currency' => 'SDG', 'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}',
        'state' => 'مسودة', 'client_id' => '{CLIENT}', 'note' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'rate_books', 'where' => "name LIKE '{MARK}%'"),
    ),
    // ◆ **لا يُقاس التعديلُ باعتمادِ الدفتر**: الشاشةُ تفصل الواجباتِ («من أنشأ لا
    //   يعتمد»)، فرفضُ الاعتمادِ هنا **سلوكٌ صحيح** لا عطب. فيُقاس التعديلُ بحقلٍ
    //   لا حارسَ عليه — وإلّا سجّلنا انضباطًا سليمًا فشلًا دائمًا.
    'edit' => array(
      'post' => array('rb_action' => 'save_book', 'book_id' => '{ID}', 'name' => '{MARK} دفترٌ معدَّل',
        'currency' => 'SDG', 'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}',
        'state' => 'مسودة', 'client_id' => '{CLIENT}', 'note' => 'ملحوظةٌ معدَّلة'),
      'verify' => array('table' => 'rate_books', 'where' => "id={ID} AND name LIKE '%معدَّل%'"),
    ),
    'delete' => null,
    'guard'  => array(
      'post' => array('rb_action' => 'save_book', 'book_id' => 0, 'name' => '{MARK}NEG دفترٌ ممنوع',
        'currency' => 'SDG', 'valid_from' => '{TODAY}', 'state' => 'مسودة', 'note' => ''),
      'verify' => array('table' => 'rate_books', 'where' => "name LIKE '{MARK}NEG%'"),
    ),
  ),
),

// ══════════════════════════════════════════════════════════════════════════
// ② الفرصةُ والعرض
// ══════════════════════════════════════════════════════════════════════════

array(
  'route' => 'Opportunities/opportunities.php', 'label' => 'سجل الفرص البيعية', 'group' => 'الفرصة',
  'view'  => array('must_contain' => array('opp_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('opp_id' => 0, 'opp_code' => '{MARK}-OP2', 'title' => 'فرصةُ فحصٍ {MARK}',
        'client_id' => '{CLIENT}', 'source' => 'فحصٌ آليّ', 'stage' => 'جديدة', 'revenue_model' => 'hourly',
        'sector_category' => 'تعدين', 'state_region' => 'الخرطوم', 'expected_revenue' => '1000',
        'currency' => 'SDG', 'probability' => '50', 'expected_close_date' => '{NEXTYEAR}',
        'attractiveness' => 'متوسطة', 'strategy_fit' => 'متوسط', 'study_decision' => 'متابعة',
        'funding_needed' => '0', 'win_reason' => '', 'lost_reason' => '', 'review_notes' => '', 'notes' => ''),
      'verify' => array('table' => 'opportunities', 'where' => "opp_code='{MARK}-OP2'"),
    ),
    'edit' => array(
      'post' => array('opp_id' => '{ID}', 'opp_code' => '{MARK}-OP2', 'title' => 'فرصةٌ معدَّلةٌ {MARK}',
        'client_id' => '{CLIENT}', 'source' => 'فحصٌ آليّ', 'stage' => 'مؤهلة', 'revenue_model' => 'hourly',
        'sector_category' => 'تعدين', 'state_region' => 'الخرطوم', 'expected_revenue' => '5000',
        'currency' => 'SDG', 'probability' => '80', 'expected_close_date' => '{NEXTYEAR}',
        'attractiveness' => 'عالية', 'strategy_fit' => 'عالي', 'study_decision' => 'متابعة',
        'funding_needed' => '0', 'win_reason' => '', 'lost_reason' => '', 'review_notes' => '', 'notes' => ''),
      'verify' => array('table' => 'opportunities', 'where' => "id={ID} AND stage='مؤهلة' AND expected_revenue=5000"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'opportunities', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('opp_id' => 0, 'opp_code' => '{MARK}-NEGO', 'title' => 'يجب ألا تُنشأ',
        'stage' => 'جديدة', 'currency' => 'SDG', 'revenue_model' => 'hourly'),
      'verify' => array('table' => 'opportunities', 'where' => "opp_code='{MARK}-NEGO'"),
    ),
  ),
),

array(
  'route' => 'Clients/quotations.php', 'label' => 'سجل العروض', 'group' => 'العرض',
  'view'  => array('must_contain' => array('quotation_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('quo_id' => 0, 'quotation_code' => '{MARK}-QU2', 'client_id' => '{CLIENT}',
        'opportunity_id' => '{OPPORTUNITY}', 'currency' => 'SDG', 'amount_total' => '1000',
        'validity_date' => '{NEXTYEAR}', 'payment_terms' => 'نقدًا', 'state' => 'مسودة', 'notes' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'quotations', 'where' => "quotation_code='{MARK}-QU2'"),
    ),
    'edit' => array(
      'post' => array('quo_id' => '{ID}', 'quotation_code' => '{MARK}-QU2', 'client_id' => '{CLIENT}',
        'opportunity_id' => '{OPPORTUNITY}', 'currency' => 'SDG', 'amount_total' => '7500',
        'validity_date' => '{NEXTYEAR}', 'payment_terms' => 'آجل', 'state' => 'مقدم', 'notes' => 'معدَّل'),
      'verify' => array('table' => 'quotations', 'where' => "id={ID} AND amount_total=7500 AND state='مقدم'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'quotations', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('quo_id' => 0, 'quotation_code' => '{MARK}-NEGQ', 'client_id' => '{CLIENT}',
        'currency' => 'SDG', 'amount_total' => '1', 'state' => 'مسودة'),
      'verify' => array('table' => 'quotations', 'where' => "quotation_code='{MARK}-NEGQ'"),
    ),
  ),
),

array(
  'route' => 'Clients/quotation_lines.php', 'label' => 'بنود العروض', 'group' => 'العرض',
  'view'  => array('params' => 'quotation={QUOTATION}', 'must_contain' => array('quotation_id')),
  'crud'  => array(
    'add' => array(
      'post' => array('add_line' => '1', 'quotation_id' => '{QUOTATION}', 'product_id' => '',
        'description' => 'بندُ فحصٍ {MARK}', 'qty' => '5', 'unit_type' => 'hour',
        'unit_price' => '120', 'currency' => 'SDG', 'discount_pct' => '0'),
      'verify' => array('table' => 'sal_quotation_lines', 'where' => "quotation_id={QUOTATION}"),
    ),
    'edit'   => null,
    'delete' => null,
    'guard'  => array(
      'post' => array('add_line' => '1', 'quotation_id' => '{QUOTATION}',
        'description' => 'بندٌ ممنوعٌ {MARK}NEG', 'qty' => '1', 'unit_type' => 'hour',
        'unit_price' => '1', 'currency' => 'SDG'),
      'verify' => array('table' => 'sal_quotation_lines', 'where' => "quotation_id={QUOTATION} AND description LIKE '%NEG%'"),
    ),
  ),
),

array(
  'route' => 'Clients/quotation_negotiation.php', 'label' => 'التفاوض ومراجعات العرض', 'group' => 'العرض',
  'view'  => array('params' => 'quotation={QUOTATION}', 'must_contain' => array('event_kind')),
  'crud'  => array(
    'add' => array(
      'post' => array('log_event' => '1', 'quotation_id' => '{QUOTATION}', 'event_kind' => 'sent',
        'party' => 'us', 'note' => 'تفاوضُ فحصٍ {MARK}', 'amount_before' => '1000',
        'amount_after' => '900', 'currency' => 'SDG', 'valid_until' => '{NEXTYEAR}'),
      'verify' => array('table' => 'sal_quotation_revisions', 'where' => "quotation_id={QUOTATION}"),
    ),
    'edit' => null, 'delete' => null,
    'guard' => array(
      'post' => array('log_event' => '1', 'quotation_id' => '{QUOTATION}', 'event_kind' => 'sent',
        'party' => 'us', 'note' => 'ممنوعٌ {MARK}NEG', 'currency' => 'SDG'),
      'verify' => array('table' => 'sal_quotation_revisions', 'where' => "quotation_id={QUOTATION} AND note LIKE '%NEG%'"),
    ),
  ),
),

array(
  'route' => 'Opportunities/client_need_rfq.php', 'label' => 'احتياج العميل وطلب العرض', 'group' => 'الفرصة',
  'view'  => array('params' => 'opportunity={OPPORTUNITY}', 'must_contain' => array('service_type')),
  'crud'  => null,
),

array(
  'route' => 'Clients/tenders.php', 'label' => 'المناقصات', 'group' => 'الفرصة',
  'view'  => array('must_contain' => array('tender_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('tnd_id' => 0, 'tender_code' => '{MARK}-TND', 'name' => 'مناقصةُ فحصٍ {MARK}',
        'authority_id' => '', 'opportunity_id' => '{OPPORTUNITY}', 'closing_date' => '{NEXTYEAR}',
        'participation_state' => 'إعداد', 'result' => 'قيد التقييم', 'result_reason' => '', 'notes' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'tenders', 'where' => "tender_code='{MARK}-TND'"),
    ),
    'edit' => array(
      'post' => array('tnd_id' => '{ID}', 'tender_code' => '{MARK}-TND', 'name' => 'مناقصةٌ معدَّلةٌ {MARK}',
        'authority_id' => '', 'opportunity_id' => '{OPPORTUNITY}', 'closing_date' => '{NEXTYEAR}',
        'participation_state' => 'مقدمة', 'result' => 'قيد التقييم', 'result_reason' => '', 'notes' => 'معدَّل'),
      'verify' => array('table' => 'tenders', 'where' => "id={ID} AND participation_state='مقدمة'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'tenders', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('tnd_id' => 0, 'tender_code' => '{MARK}-NEGT', 'name' => 'يجب ألا تُنشأ',
        'participation_state' => 'إعداد', 'result' => 'قيد التقييم'),
      'verify' => array('table' => 'tenders', 'where' => "tender_code='{MARK}-NEGT'"),
    ),
  ),
),

array(
  'route' => 'Clients/readiness_lines.php', 'label' => 'مراجعة ما قبل التعاقد', 'group' => 'العرض',
  'view'  => array('must_contain' => array('readiness_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('rdl_id' => 0, 'readiness_code' => '{MARK}-RDL', 'contract_ref' => '{CONTRACT}',
        'name' => 'جاهزية الأسطول', 'state' => 'مجتاز', 'source_ref' => 'فحصٌ {MARK}',
        'required' => '10', 'available' => '10', 'gap_note' => ''),
      'verify' => array('table' => 'readiness_lines', 'where' => "readiness_code='{MARK}-RDL'"),
    ),
    'edit' => array(
      'post' => array('rdl_id' => '{ID}', 'readiness_code' => '{MARK}-RDL', 'contract_ref' => '{CONTRACT}',
        'name' => 'جاهزية الأسطول', 'state' => 'فجوة', 'source_ref' => 'فحصٌ {MARK}',
        'required' => '20', 'available' => '5', 'gap_note' => 'فجوةُ فحص'),
      'verify' => array('table' => 'readiness_lines', 'where' => "id={ID} AND state='فجوة' AND required=20"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'readiness_lines', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('rdl_id' => 0, 'readiness_code' => '{MARK}-NEGR', 'contract_ref' => '{CONTRACT}',
        'name' => 'جاهزية الأسطول', 'state' => 'مجتاز', 'required' => '1', 'available' => '1'),
      'verify' => array('table' => 'readiness_lines', 'where' => "readiness_code='{MARK}-NEGR'"),
    ),
  ),
),

array(
  'route' => 'Clients/activities.php', 'label' => 'الأنشطة والمتابعات', 'group' => 'الفرصة',
  'view'  => array('must_contain' => array('activity_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('act_id' => 0, 'activity_code' => '{MARK}-ACT', 'activity_type' => 'زيارة عميل',
        'entity_type' => 'client', 'entity_id' => '{CLIENT}', 'subject' => 'نشاطُ فحصٍ {MARK}',
        'activity_date' => '{TODAY}', 'assigned_user_id' => '', 'is_negotiation' => '0',
        'outcome' => '', 'notes' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'activities', 'where' => "activity_code='{MARK}-ACT'"),
    ),
    'edit' => array(
      'post' => array('act_id' => '{ID}', 'activity_code' => '{MARK}-ACT', 'activity_type' => 'هاتفي',
        'entity_type' => 'client', 'entity_id' => '{CLIENT}', 'subject' => 'نشاطٌ معدَّلٌ {MARK}',
        'activity_date' => '{TODAY}', 'assigned_user_id' => '', 'is_negotiation' => '1',
        'outcome' => 'تمّ', 'notes' => 'معدَّل'),
      'verify' => array('table' => 'activities', 'where' => "id={ID} AND activity_type='هاتفي'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'activities', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('act_id' => 0, 'activity_code' => '{MARK}-NEGA', 'activity_type' => 'زيارة عميل',
        'entity_type' => 'client', 'entity_id' => '{CLIENT}', 'subject' => 'ممنوع', 'activity_date' => '{TODAY}'),
      'verify' => array('table' => 'activities', 'where' => "activity_code='{MARK}-NEGA'"),
    ),
  ),
),

array(
  'route' => 'Clients/client_contacts.php', 'label' => 'جهات اتصال العملاء', 'group' => 'العملاء',
  'view'  => array('params' => 'client={CLIENT}', 'must_contain' => array('pc_action')),
  'crud'  => array(
    'add' => array(
      'post' => array('pc_action' => 'add', 'contact_name' => '{MARK} مسؤولُ فحص',
        'job_title' => 'مدير', 'phone' => '0900000009', 'email' => 'qa2@test.local',
        'authority_kind' => '—', 'note' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'party_contacts', 'where' => "contact_name LIKE '{MARK}%' AND COALESCE(is_deleted,0)=0"),
    ),
    // ◆ معرِّفُ الصفِّ هنا `pc_id` لا `id` — العُدّةُ المشتركةُ تقرأه بهذا الاسم.
    'edit' => array(
      'post' => array('pc_action' => 'edit', 'pc_id' => '{ID}', 'contact_name' => '{MARK} مسؤولٌ معدَّل',
        'job_title' => 'مدير عام', 'phone' => '0911111119', 'email' => 'qa2@test.local',
        'authority_kind' => '—', 'note' => 'معدَّل'),
      'verify' => array('table' => 'party_contacts', 'where' => "id={ID} AND phone='0911111119'"),
    ),
    'delete' => array('post' => array('pc_action' => 'delete', 'pc_id' => '{ID}'),
      'verify' => array('table' => 'party_contacts', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('pc_action' => 'add', 'contact_name' => '{MARK}NEG ممنوع',
        'job_title' => 'x', 'phone' => '0', 'email' => 'x@y.z', 'authority_kind' => '—'),
      'verify' => array('table' => 'party_contacts', 'where' => "contact_name LIKE '{MARK}NEG%'"),
    ),
  ),
),

array(
  'route' => 'Clients/client_profile.php', 'label' => 'بطاقة العميل', 'group' => 'العملاء',
  // ◆ العلامةُ ثابتةٌ في السطحِ لا مبنيّةٌ على قيمةٍ مبذورة: الوضعُ السريعُ
  //   يستعير عميلًا قائمًا لا يحمل وسمَنا، فعلامةُ الوسمِ تُخفق فيه بلا عطب.
  'view'  => array('params' => 'id={CLIENT}', 'must_contain' => array('بطاقة العميل')),
  'crud'  => null,
),

array(
  'route' => 'movement/client_tree.php', 'label' => 'شجرة حسابات العميل', 'group' => 'العملاء',
  'view'  => array('params' => 'client_id={CLIENT}'), 'crud' => null,
),

array(
  'route' => 'Projects/projects.php', 'label' => 'سجل المشاريع', 'group' => 'العملاء',
  'view'  => array('must_contain' => array('project_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('id' => 0, 'client_id' => '{CLIENT}', 'project_code' => '{MARK}-PRJ',
        'mine_code' => '', 'project_name' => 'مشروعُ فحصٍ ثانٍ {MARK}', 'location' => 'موقعُ فحص',
        'total' => '0', 'category' => '', 'sub_sector' => '', 'state' => 'الخرطوم', 'region' => '',
        'nearest_market' => '', 'latitude' => '', 'longitude' => '', 'status' => 'نشط'),
      'verify' => array('table' => 'project', 'where' => "project_code='{MARK}-PRJ'"),
    ),
    'edit' => array(
      'post' => array('id' => '{ID}', 'client_id' => '{CLIENT}', 'project_code' => '{MARK}-PRJ',
        'mine_code' => '', 'project_name' => 'مشروعٌ معدَّلٌ {MARK}', 'location' => 'موقعٌ معدَّل',
        'total' => '0', 'category' => '', 'sub_sector' => '', 'state' => 'الخرطوم', 'region' => '',
        'nearest_market' => '', 'latitude' => '', 'longitude' => '', 'status' => 'نشط'),
      'verify' => array('table' => 'project', 'where' => "id={ID} AND location='موقعٌ معدَّل'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'project', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('id' => 0, 'client_id' => '{CLIENT}', 'project_code' => '{MARK}-NEGJ',
        'project_name' => 'يجب ألا يُنشأ', 'location' => 'x', 'total' => '0', 'status' => 'نشط'),
      'verify' => array('table' => 'project', 'where' => "project_code='{MARK}-NEGJ'"),
    ),
  ),
),

// ══════════════════════════════════════════════════════════════════════════
// ③ ملفُّ العقد
// ══════════════════════════════════════════════════════════════════════════

array(
  'route' => 'Clients/contract_events.php', 'label' => 'سجل حركة العقد', 'group' => 'العقد',
  'view'  => array('must_contain' => array('event_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('evt_id' => 0, 'event_code' => '{MARK}-EVT', 'contract_id' => '{CONTRACT}',
        'event_type' => 'أمر تغيير', 'party' => 'العميل', 'event_date' => '{TODAY}',
        'state' => 'مفتوح', 'description' => 'حدثُ فحصٍ {MARK}'),
      'verify' => array('table' => 'contract_events', 'where' => "event_code='{MARK}-EVT'"),
    ),
    'edit' => array(
      'post' => array('evt_id' => '{ID}', 'event_code' => '{MARK}-EVT', 'contract_id' => '{CONTRACT}',
        'event_type' => 'أمر تغيير', 'party' => 'الشركة', 'event_date' => '{TODAY}',
        'state' => 'مغلق', 'description' => 'حدثٌ معدَّل'),
      'verify' => array('table' => 'contract_events', 'where' => "id={ID} AND state='مغلق'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'contract_events', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('evt_id' => 0, 'event_code' => '{MARK}-NEGE', 'contract_id' => '{CONTRACT}',
        'event_type' => 'أمر تغيير', 'state' => 'مفتوح', 'event_date' => '{TODAY}'),
      'verify' => array('table' => 'contract_events', 'where' => "event_code='{MARK}-NEGE'"),
    ),
  ),
),

array(
  'route' => 'Clients/contract_commitments.php', 'label' => 'التزامات العقود', 'group' => 'العقد',
  'view'  => array('must_contain' => array('commitment_code')),
  'crud'  => array(
    'add' => array(
      'post' => array('cmt_id' => 0, 'commitment_code' => '{MARK}-CMT', 'contract_ref' => '{CONTRACT}',
        'party_scope' => 'client', 'commitment_type' => 'equipment_count', 'unit_type' => 'hour',
        'qty' => '10', 'period' => 'monthly', 'obliged_party' => 'company',
        'shortfall_rule' => 'invoice_actual', 'surplus_rule' => 'same_price',
        'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}', 'note' => 'التزامُ فحصٍ {MARK}'),
      'verify' => array('table' => 'contract_commitments', 'where' => "commitment_code='{MARK}-CMT'"),
    ),
    'edit' => array(
      'post' => array('cmt_id' => '{ID}', 'commitment_code' => '{MARK}-CMT', 'contract_ref' => '{CONTRACT}',
        'party_scope' => 'client', 'commitment_type' => 'equipment_count', 'unit_type' => 'hour',
        'qty' => '25', 'period' => 'monthly', 'obliged_party' => 'company',
        'shortfall_rule' => 'penalty', 'surplus_rule' => 'same_price',
        'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}', 'note' => 'معدَّل'),
      'verify' => array('table' => 'contract_commitments', 'where' => "id={ID} AND qty=25 AND shortfall_rule='penalty'"),
    ),
    'delete' => array('get' => 'delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'contract_commitments', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('cmt_id' => 0, 'commitment_code' => '{MARK}-NEGC', 'contract_ref' => '{CONTRACT}',
        'party_scope' => 'client', 'commitment_type' => 'equipment_count', 'qty' => '1',
        'period' => 'monthly', 'obliged_party' => 'company', 'shortfall_rule' => 'invoice_actual',
        'surplus_rule' => 'same_price'),
      'verify' => array('table' => 'contract_commitments', 'where' => "commitment_code='{MARK}-NEGC'"),
    ),
  ),
),

array(
  // ◆ سجلٌّ **للقراءةِ فقط بقرارِ D02**: يُغذَّى من إجراءاتِ العقدِ لا يدًا.
  //   فالإضافةُ والتعديلُ والحذفُ `NA` — والحارسُ يثبت أن الرفضَ خادميٌّ فعلًا.
  'route' => 'Clients/contract_amendments.php', 'label' => 'ملاحق العقود وتجديداتها', 'group' => 'العقد',
  'view'  => array('must_contain' => array('amendment_code')),
  'crud'  => array(
    'add' => null, 'edit' => null, 'delete' => null,
    'guard' => array(
      'post' => array('amd_id' => 0, 'amendment_code' => '{MARK}-NEGM', 'contract_id' => '{CONTRACT}',
        'amend_type' => 'تمديد', 'amend_date' => '{TODAY}', 'reason' => 'يجب أن يُرفض'),
      'verify' => array('table' => 'contract_amendments', 'where' => "amendment_code='{MARK}-NEGM'"),
    ),
  ),
),

array(
  'route' => 'Contracts/contract_obligations.php', 'label' => 'مصفوفة التزامات العقد', 'group' => 'العقد',
  'view'  => array('params' => 'contract={CONTRACT}', 'must_contain' => array('save_obligation')),
  'crud'  => array(
    'add' => array(
      'post' => array('save_obligation' => '1', 'client_contract_id' => '{CONTRACT}', 'obligation_id' => 0,
        'obligation_type' => 'fuel', 'obligor' => 'client', 'effect_on_billing' => 'billable_standby',
        'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}'),
      'verify' => array('table' => 'contract_obligations', 'where' => "client_contract_id={CONTRACT}"),
    ),
    'edit' => array(
      'post' => array('save_obligation' => '1', 'client_contract_id' => '{CONTRACT}', 'obligation_id' => '{ID}',
        'obligation_type' => 'fuel', 'obligor' => 'company', 'effect_on_billing' => 'non_billable',
        'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}'),
      'verify' => array('table' => 'contract_obligations', 'where' => "id={ID} AND obligor='company'"),
    ),
    'delete' => array('get' => 'contract={CONTRACT}&delete_id={ID}&csrf_token={CSRF}',
      'verify' => array('table' => 'contract_obligations', 'where' => "id={ID} AND COALESCE(is_deleted,0)=0")),
    'guard' => array(
      'post' => array('save_obligation' => '1', 'client_contract_id' => '{CONTRACT}', 'obligation_id' => 0,
        'obligation_type' => 'permits_safety', 'obligor' => 'client', 'effect_on_billing' => 'non_billable',
        'valid_from' => '{TODAY}'),
      'verify' => array('table' => 'contract_obligations', 'where' => "client_contract_id={CONTRACT} AND obligation_type='permits_safety'"),
    ),
  ),
),

array(
  'route' => 'Contracts/contract_guarantees.php', 'label' => 'ضمانات العقد', 'group' => 'العقد',
  'view'  => array('params' => 'contract={CONTRACT}', 'must_contain' => array('g_action')),
  'crud'  => array(
    'add' => array(
      'post' => array('g_action' => 'add', 'contract_id' => '{CONTRACT}', 'kind' => 'bank_guarantee',
        'nature' => 'off_balance', 'deductible' => '0', 'amount' => '1000', 'percent_value' => '',
        'currency' => 'SDG', 'issuer' => 'بنكُ فحصٍ {MARK}', 'instrument_ref' => '{MARK}-GRT',
        'issue_date' => '{TODAY}', 'expiry_date' => '{NEXTYEAR}', 'due_release_date' => '',
        'release_condition' => '', 'note' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'contract_guarantees', 'where' => "contract_id={CONTRACT}"),
    ),
    'edit' => array(
      'post' => array('g_action' => 'state', 'contract_id' => '{CONTRACT}', 'gid' => '{ID}',
        'state' => 'active', 'state_reason' => 'تفعيلُ فحص', 'state_at' => '{TODAY}'),
      'verify' => array('table' => 'contract_guarantees', 'where' => "id={ID} AND state='active'"),
    ),
    'delete' => null,
    'guard'  => array(
      'post' => array('g_action' => 'add', 'contract_id' => '{CONTRACT}', 'kind' => 'insurance',
        'nature' => 'off_balance', 'amount' => '1', 'currency' => 'SDG',
        'issuer' => 'ممنوع', 'instrument_ref' => '{MARK}-NEGG', 'issue_date' => '{TODAY}'),
      'verify' => array('table' => 'contract_guarantees', 'where' => "contract_id={CONTRACT} AND kind='insurance'"),
    ),
  ),
),

array(
  'route' => 'Contracts/contract_lines.php', 'label' => 'بنود عقد العميل وقيمته', 'group' => 'العقد',
  'view'  => array('params' => 'contract={CONTRACT}', 'must_contain' => array('cl_action')),
  'crud'  => array(
    // ◆ يُصدِّر معرِّفَ البندِ باسم `{LINE}` — «الخطةُ الشهرية» و«خطةُ الموارد»
    //   لا تُصيّران نموذجَهما إلّا على بندٍ محدَّد.
    'export' => 'LINE',
    'add' => array(
      // ◆ `exempt` عمدًا: «الضريبةُ سطرٌ بمرجعها» (M-03 §5) — فبندٌ خاضعٌ بلا رمزٍ
      //   ضريبيٍّ يُردُّ 422 وهو **صوابُ المنتَج**. والفحصُ يقيس المسارَ لا يكسر القاعدة.
      'post' => array('cl_action' => 'add', 'contract_id' => '{CONTRACT}', 'source_commitment_id' => '',
        'pricing_model' => 'hour', 'description' => 'بندُ عقدِ فحصٍ {MARK}', 'qty_contracted' => '100',
        'unit_price' => '250', 'currency' => 'SDG', 'valid_from' => '{TODAY}', 'valid_to' => '{NEXTYEAR}',
        'tax_status' => 'exempt', 'tax_code_id' => '', 'note' => 'فحصٌ آليّ'),
      'verify' => array('table' => 'client_contract_lines', 'where' => "contract_id={CONTRACT}"),
    ),
    'edit' => array(
      // سريانُ النسخةِ الجديدةِ **بعدَ** سريانِ القائمة — شرطٌ صحيحٌ في المنتَج.
      'post' => array('cl_action' => 'reprice', 'contract_id' => '{CONTRACT}', 'line_id' => '{ID}',
        'new_price' => '300', 'effective_from' => '{TOMORROW}'),
      'verify' => array('table' => 'client_contract_lines', 'where' => "contract_id={CONTRACT} AND unit_price=300"),
    ),
    'delete' => null,
    'guard'  => array(
      'post' => array('cl_action' => 'add', 'contract_id' => '{CONTRACT}', 'pricing_model' => 'ton',
        'description' => 'بندٌ ممنوعٌ {MARK}NEG', 'qty_contracted' => '1', 'unit_price' => '1',
        'currency' => 'SDG', 'valid_from' => '{TODAY}', 'tax_status' => 'exempt'),
      'verify' => array('table' => 'client_contract_lines', 'where' => "contract_id={CONTRACT} AND description LIKE '%NEG%'"),
    ),
  ),
),

array('route' => 'Contracts/contract_baseline.php', 'label' => 'خط أساس العقد', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('bl_action')), 'crud' => null),
array('route' => 'Contracts/contract_lifecycle.php', 'label' => 'اقتصاد دورة حياة العقد', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('lc_action')), 'crud' => null),
array('route' => 'Contracts/contract_monthly_plan.php', 'label' => 'الجدول الشهري للعقد', 'group' => 'العقد',
      'view' => array('params' => 'line={LINE}', 'must_contain' => array('mp_action')), 'crud' => null),
array('route' => 'Contracts/contract_payment_schedule.php', 'label' => 'خطة دفع العقد', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('ps_action')), 'crud' => null),
array('route' => 'Contracts/contract_resource_plan.php', 'label' => 'خطة موارد العقد', 'group' => 'العقد',
      'view' => array('params' => 'line={LINE}', 'must_contain' => array('rp_action')), 'crud' => null),
array('route' => 'Contracts/contract_sites.php', 'label' => 'نطاقات العقد التشغيلية', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('cs_action')), 'crud' => null),
array('route' => 'Contracts/price_terms.php', 'label' => 'شروط تعديل السعر', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('pt_action')), 'crud' => null),
array('route' => 'Contracts/penalties.php', 'label' => 'الجزاءات والحوافز التعاقدية', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('pen_action')), 'crud' => null),
array('route' => 'Contracts/plan_actual_link.php', 'label' => 'ربط الخطة بالفعلي', 'group' => 'العقد',
      'view' => array('params' => 'contract={CONTRACT}', 'must_contain' => array('pal_action')), 'crud' => null),
array('route' => 'Contracts/contracts_details.php', 'label' => 'ملف عقد المشروع', 'group' => 'العقد',
      'view' => array('params' => 'id={CONTRACT}'), 'crud' => null),
array('route' => 'Contracts/contract_coverage.php', 'label' => 'احتياجات التغطية', 'group' => 'العقد',
      'view' => array('params' => 'contract_id={CONTRACT}'), 'crud' => null),
array('route' => 'Contracts/commercial_board.php', 'label' => 'اللوحة التجارية للعقود', 'group' => 'لوحات وتقارير',
      'view' => array(), 'crud' => null),

// ══════════════════════════════════════════════════════════════════════════
// ④ المطالبةُ والتشغيلُ واللوحاتُ والتقارير
// ══════════════════════════════════════════════════════════════════════════

array('route' => 'Contracts/unit_client_match.php', 'label' => 'مطابقة بيانات العميل', 'group' => 'المطالبة',
      'view' => array(), 'crud' => null),
array('route' => 'Contracts/unit_statement_client.php', 'label' => 'كشف التايم شيت للعميل', 'group' => 'المطالبة',
      'view' => array(), 'crud' => null),
array('route' => 'Operations/unbilled.php', 'label' => 'الأعمال غير المفوترة', 'group' => 'المطالبة',
      'view' => array('must_contain' => array('cmp03_action')), 'crud' => null),
array('route' => 'Operations/equipment_quota.php', 'label' => 'توزيع وحدات المورد على معداته', 'group' => 'التشغيل',
      'view' => array(), 'crud' => null),
array('route' => 'Operations/sites_board.php', 'label' => 'لوحة المواقع', 'group' => 'التشغيل',
      'view' => array('params' => 'site={SITE}'), 'crud' => null),
array('route' => 'Operations/swap_request.php', 'label' => 'طلب تبديل', 'group' => 'التشغيل',
      'view' => array('params' => 'site={SITE}'), 'crud' => null),
array('route' => 'Portal/business_models.php', 'label' => 'نماذج العمل ووحدات القياس', 'group' => 'البيانات المرجعية',
      'view' => array('must_contain' => array('cmp03_action')), 'crud' => null),
array('route' => 'Governance/gov_dept_sal.php', 'label' => 'حوكمة المبيعات والعقود', 'group' => 'لوحات وتقارير',
      'view' => array(), 'crud' => null),
array('route' => 'Risk/risk_dept_sal.php', 'label' => 'مخاطر المبيعات والعقود', 'group' => 'لوحات وتقارير',
      'view' => array(), 'crud' => null),
array('route' => 'Reports/contract_report.php', 'label' => 'تقرير العقد', 'group' => 'لوحات وتقارير',
      'view' => array(), 'crud' => null),
array('route' => 'Reports/contractall.php', 'label' => 'تقرير كل العقود', 'group' => 'لوحات وتقارير',
      'view' => array(), 'crud' => null),

), // screens
);
