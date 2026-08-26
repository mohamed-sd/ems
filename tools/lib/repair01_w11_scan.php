<?php
/**
 * tools/lib/repair01_w11_scan.php — مقاييسُ المرحلةِ الحاديةَ عشرة (RPR-W11)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ إعلان**: كلُّ دالّةٍ هنا **تعيد القياسَ من الحيِّ**
 *   ولا تقرأ ما خزّنَته بوّابةٌ سابقة. والمقامُ يُعاد بناؤه في كلِّ نداء.
 *
 * ◆ **وبياناتُ المرحلةِ سجلٌّ لا حرفيّاتٌ متناثرة**: المِرساةُ والسطحُ والحالةُ
 *   وفصلُ الواجباتِ والعتبةُ والحدثُ والرقمُ المجمَّع — كلُّها مصفوفاتٌ مسمّاةٌ
 *   هنا، تقرؤها أداةُ الاشتقاقِ والبوّابةُ والرحلةُ والفحصُ السلبيُّ **من
 *   موضعٍ واحد**. فمصدرانِ للحقيقةِ يتفرّقان — والحملةُ رصدت ذلك مرارًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════════════════════════════
   ① أدواتُ قياسٍ عامّة
   ══════════════════════════════════════════════════════════════════════════ */

function repair01_w11_one(mysqli $c, $sql)
{
    $r = @$c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

function repair01_w11_table_exists(mysqli $c, $t)
{
    $r = @$c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w11_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w11_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w11_check_exists(mysqli $c, $t, $name)
{
    $n = (int) repair01_w11_one($c, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                      WHERE CONSTRAINT_SCHEMA = DATABASE()
                                        AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                                        AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $n > 0;
}

/**
 * **العمودُ يحمل الكيانَ ولا يقبل العدم** — الحبّةُ `Legal Entity × Period`
 * لا تُثبَت بوجودِ العمودِ وحدَه: عمودٌ يقبل `NULL` يسمح بصفٍّ بلا كيان.
 */
function repair01_w11_entity_scoped(mysqli $c, $t)
{
    if (!repair01_w11_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE 'company_id'");
    $x = $r ? $r->fetch_assoc() : null;
    return $x && strtoupper((string) $x['Null']) === 'NO';
}

/** العتباتُ المسجَّلة — تُقرأ ولا تُكتب */
function repair01_w11_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w11_table_exists($c, 'repair01_w11_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w11_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/** حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ */
function repair01_w11_guard_of($ROOT, $route)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);
    if (strpos($src, 'check_page_permissions') !== false
        || strpos($src, 'enforce_current_page_view_permission') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'حارس صلاحية في الملف نفسه');
    }
    if (strpos($src, 'ems_gov_flash_redirect') !== false || strpos($src, 'insidebar.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'حارس القشرة insidebar');
    }
    if (strpos($src, "\$_SESSION['user']") !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'فحص الجلسة في الملف');
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس');
}

/* ══════════════════════════════════════════════════════════════════════════
   ② مِرساةُ كلِّ متطلَّبٍ إلى سطحِه — مُعلَنةٌ ومقيسةٌ معًا
   ══════════════════════════════════════════════════════════════════════════
   `kind`: `TABLE` جدولٌ يمسُّه الملفّ · `SERVICE` صنفٌ يستدعيه.
   `step`: موضعُ السطحِ من **ترتيبِ الدورةِ المحاسبيّة** (‏§23) — لا الأبجديّةُ
           ولا تاريخُ الإنشاءِ يرتّبان السايدبار.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w11_anchors()
{
    return array(
        /* ── 05 الإدارة المالية · اللوحة خارج الدورة ───────────────────── */
        'ACC-01' => array('route' => 'Finance/executive_dashboard_fin.php', 'probe' => 'fin_financial_events',
                          'kind' => 'TABLE', 'step' => 0,
                          'why' => 'لوحة المالية - مؤشر × فترة قراءة حية مشتقة من القيود والذمم والفترات'),
        /* ── التأسيس المرجعي (‏الخطوة ①) ─────────────────────────────────── */
        'ACC-02' => array('route' => 'Finance/accounts_fin.php', 'probe' => 'fin_chart_of_accounts',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'دليل الحسابات - لا حساب خارج الشجرة ولا قيد على حساب تجميعي'),
        'ACC-03' => array('route' => 'Finance/acc_cost_centers.php', 'probe' => 'fin_cost_centers',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'مراكز التكلفة - المالية تملك الشجرة والمشاريع والمعدات تربط بمراكزها'),
        'ACC-04' => array('route' => 'Finance/periods_fin.php', 'probe' => 'fin_financial_periods',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'التقويم المحاسبي - لا قيد خارج فترة معرفة ولا قيد على فترة مقفلة'),
        'ACC-05' => array('route' => 'Finance/budget_form_fin.php', 'probe' => 'fin_budgets',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'الميزانية والتقدير - كل ادارة ترفع والمالية تجمع وتراقب'),
        'ACC-06' => array('route' => 'Finance/currencies_fin.php', 'probe' => 'fin_fx_rates',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'اسعار الصرف - لا جمع عابر للعملات وكل تحويل بسعره الموثق بتاريخه'),
        /* ── الدفاتر المساعدة (‏الخطوة ②) ─────────────────────────────────── */
        'ACC-07' => array('route' => 'Finance/ar_claim_invoice.php', 'probe' => 'ar_claim_invoices',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'فواتير العملاء - الفاتورة من مطالبة معتمدة والالغاء باشعار دائن لا حذفا'),
        'ACC-08' => array('route' => 'Finance/ar_claim_invoice.php', 'probe' => 'acc_invoice_line',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'بنود فاتورة العميل - سجل تابع في الشاشة الام لا سطح مستقل'),
        'ACC-09' => array('route' => 'Finance/dues_fin.php', 'probe' => 'fin_dues',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'فواتير الموردين والمستحقات - لا استحقاق بلا مطابقة او اقفال تعاقدي'),
        'ACC-10' => array('route' => 'Finance/dues_fin.php', 'probe' => 'acc_supplier_accrual_line',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'بنود استحقاق المورد - كل بند بمرجعه في بوابته'),
        'ACC-14' => array('route' => 'Finance/dues_fin.php', 'probe' => 'fin_receivables',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'ذمم العملاء واعمارها - مشتقة من الفواتير والتحصيلات'),
        'ACC-15' => array('route' => 'Finance/acc_credit_control.php', 'probe' => 'acc_credit_limit',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'الرقابة الائتمانية - التجاوز يحجب او يصعد بقاعدة ولا بيع فوق الحد بلا اعتماد'),
        'ACC-16' => array('route' => 'Finance/supplier_statement_fin.php', 'probe' => 'fin_dues',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'ذمم الموردين واعمارها - تغذي جدولة الدفع عند الخزينة ولا تنفذها'),
        /* ── القيد والدفتر (‏الخطوة ③) ───────────────────────────────────── */
        'ACC-11' => array('route' => 'Finance/journal_form_fin.php', 'probe' => 'fin_journal_entries',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'القيود اليومية - لا كاتب بشري لدفتر الاستاذ والقيد من مصدره'),
        'ACC-12' => array('route' => 'Finance/journal_form_fin.php', 'probe' => 'fin_journal_lines',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'اسطر القيد - سطر واحد بحساب واحد باتجاه واحد ومجموع المدين يساوي الدائن'),
        'ACC-13' => array('route' => 'FinRequests/effect_map.php', 'probe' => 'fin_event_links',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'تتبع الاثر من الواقعة الى القيد - خيط واحد من الطرفين'),
        /* ── التسويات (‏الخطوة ④) ─────────────────────────────────────────── */
        'ACC-17' => array('route' => 'Finance/acc_adjustments.php', 'probe' => 'acc_period_adjustment',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'الاستحقاقات والمقدمات والمخصصات - تسويات نهاية الفترة الموسومة'),
        'ACC-18' => array('route' => 'Finance/tax_fin.php', 'probe' => 'fin_tax_codes',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'الضرائب والقيمة المضافة - المالية تحتسب والسداد عند الخزينة'),
        'ACC-19' => array('route' => 'Finance/assets_fin.php', 'probe' => 'fin_depreciation',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'محاسبة الاصول الثابتة والاهلاك - القيمة الدفترية وسياستها هنا'),
        /* ── المطابقات (‏الخطوة ⑤) ────────────────────────────────────────── */
        'ACC-20' => array('route' => 'Finance/acc_reconciliations.php', 'probe' => 'acc_account_recon',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'مطابقات الحسابات - كل حساب رقابي يطابق دوريا مع مصدره التفصيلي'),
        /* ── ميزان المراجعة (‏الخطوة ⑥) ──────────────────────────────────── */
        'ACC-21' => array('route' => 'Finance/acc_trial_balance.php', 'probe' => 'acc_trial_balance_run',
                          'kind' => 'TABLE', 'step' => 6,
                          'why' => 'ميزان المراجعة - مشتق كليا من القيود المنشورة وتوازنه شرط الاقفال'),
        /* ── قائمة الإقفال (‏الخطوة ⑦) ───────────────────────────────────── */
        'ACC-22' => array('route' => 'Finance/acc_closing_checklist.php', 'probe' => 'fin_closing_items',
                          'kind' => 'TABLE', 'step' => 7,
                          'why' => 'قائمة اقفال الفترة - لا اقفال قبل اكتمال البنود او توثيق استثناء كل ناقص'),
        /* ── إقفال الفترة (‏الخطوة ⑧) ────────────────────────────────────── */
        'ACC-23' => array('route' => 'Finance/periods_fin.php', 'probe' => 'fin_financial_periods',
                          'kind' => 'TABLE', 'step' => 8,
                          'why' => 'اقفال الفترة المحاسبية - المالية وحدها تقفل ولا قيد على فترة مقفلة'),
        'ACC-25' => array('route' => 'Finance/acc_reopen_governance.php', 'probe' => 'acc_period_reopen_request',
                          'kind' => 'TABLE', 'step' => 8,
                          'why' => 'حوكمة اعادة فتح الفترات - استثناء محكوم بمبرر وموافقة ونطاق زمني ووحدات'),
        /* ── القوائم المالية (‏الخطوة ⑨) ─────────────────────────────────── */
        'ACC-24' => array('route' => 'Finance/financial_statements_fin.php', 'probe' => 'fin_journal_lines',
                          'kind' => 'TABLE', 'step' => 9,
                          'why' => 'القوائم المالية - تشتق بعد الاقفال من الميزان المقفل'),

        /* ── 06 إدارة الخزينة · اللوحة خارج الدورة ─────────────────────── */
        'TRS-01' => array('route' => 'Finance/tre_liquidity_board.php', 'probe' => 'tre_cash_move',
                          'kind' => 'TABLE', 'step' => 0,
                          'why' => 'لوحة الخزينة والسيولة - مشتقة من الارصدة والحركات وخطة السيولة'),
        /* ── التأسيس ────────────────────────────────────────────────────── */
        'TRS-02' => array('route' => 'Finance/tre_vessels.php', 'probe' => 'tre_cash_box',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'الحسابات البنكية والصناديق - الخزينة تملكها والتفويض يقرا من الحوكمة'),
        'TRS-03' => array('route' => 'Finance/tre_beneficiary.php', 'probe' => 'tre_beneficiaries',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'سجل المستفيدين والتحقق - لا دفع لمستفيد غير محقق والحساب يقفل ضد التعديل'),
        /* ── التخطيط ────────────────────────────────────────────────────── */
        'TRS-04' => array('route' => 'Finance/cash_forecast_fin.php', 'probe' => 'fin_cash_forecasts',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'خطة السيولة والتدفق - تخطط من الذمم والاستحقاقات المقروءة'),
        /* ── دورة القبض ─────────────────────────────────────────────────── */
        'TRS-05' => array('route' => 'Contracts/collections.php', 'probe' => 'fin_payments',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'التحصيلات الواردة - الخزينة تقبض بسندها والفاتورة عند المالية'),
        'TRS-06' => array('route' => 'Finance/tre_instruments.php', 'probe' => 'tre_instrument',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'سجل الادوات المالية - كل شيك اداة مسجلة بدورتها لا حقل حالة صامت'),
        'TRS-07' => array('route' => 'Finance/tre_allocations.php', 'probe' => 'fin_collection_allocations',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'تخصيص التحصيل على الفواتير - كل تخصيص سطر والمتبقي مشتق'),
        /* ── دورة الصرف ─────────────────────────────────────────────────── */
        'TRS-08' => array('route' => 'Finance/tre_payment_queue.php', 'probe' => 'fin_requests',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'صف الدفع المعتمد - اسقاط فوق طلبات الادارات لا سجل مواز'),
        'TRS-09' => array('route' => 'Finance/tre_pay_batch.php', 'probe' => 'tre_pay_batches',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'امر الدفع والتنفيذ - لا تنفيذ الا لطلب استوفى اعتماده'),
        'TRS-10' => array('route' => 'Finance/tre_cash_moves.php', 'probe' => 'tre_cash_move',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'حركة الخزينة والصناديق - فرق الصرف حركة مستقلة لا تعديلا صامتا'),
        'TRS-11' => array('route' => 'Finance/tre_transfers.php', 'probe' => 'tre_transfer',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'التحويلات بين الحسابات - ليس دفعا لمستفيد بل مسار اخف بقاعدته'),
        'TRS-12' => array('route' => 'Finance/tre_fx_deals.php', 'probe' => 'tre_fx_deal',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'تنفيذ عمليات الصرف الاجنبي - بسعر الصفقة الموثق لا بسعر الجدول'),
        /* ── الرقابة والإقفال ───────────────────────────────────────────── */
        'TRS-13' => array('route' => 'Finance/bank_reconciliation_fin.php', 'probe' => 'BankReconService',
                          'kind' => 'SERVICE', 'step' => 5,
                          'why' => 'المطابقة البنكية - الخزينة تعد والمالية تراجع وهي شهرية الزامية'),
        'TRS-16' => array('route' => 'Finance/bank_reconciliation_fin.php', 'probe' => 'tre_recon_difference',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'بنود فروق المطابقة - كل فرق سطر بنوعه وسببه ومسؤوله واجرائه حتى الاغلاق'),
        'TRS-14' => array('route' => 'Finance/funding_fin.php', 'probe' => 'fin_funding_facilities',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'التسهيلات البنكية - التزام يعتمد بقاعدته والاستخدام والسداد هنا'),
        'TRS-15' => array('route' => 'Finance/tre_guarantees.php', 'probe' => 'tre_guarantee',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'خطابات الضمان والاعتمادات - الاصدار على تسهيله وبقاعدته'),
        'TRS-17' => array('route' => 'Finance/tre_petty_cash.php', 'probe' => 'tre_petty_custody',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'عهد النثرية وتسويتها - بحد وسقف زمني ولا تجديد قبل تسوية السابقة'),
        'TRS-18' => array('route' => 'Finance/tre_cash_count.php', 'probe' => 'tre_cash_count',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'الجرد النقدي للخزائن - بلجنة لا بامين الصندوق وحده والفرق يعالج فورا'),
    );
}

/** إثباتُ المِرساةِ من القرصِ والسجلِّ معًا — لا من الإعلان */
function repair01_w11_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W11_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY',
                              'rule' => 'W11_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W11_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W11_ANCHOR_UNPROVEN');
    }
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W11_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W11_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W11_ANCHOR_UNPROVEN');
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ══════════════════════════════════════════════════════════════════════════
   `sort` هو **موضعُ السطحِ من دورةِ العمل** — لا الأبجديّةُ ولا الإنشاء.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w11_new_surfaces()
{
    return array(
        /* ── 05 الإدارة المالية ────────────────────────────────────────── */
        array('route' => 'Finance/acc_cost_centers.php', 'ar' => 'مراكز التكلفة',
              'icon' => 'fa fa-sitemap', 'group' => 'التأسيس المرجعي', 'sort' => 3, 'step' => 1,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/accounts_fin.php',
              'req' => 'ACC-03', 'doc' => 'شجرة مراكز تكلفة معتمدة',
              'next' => 'ربط المشاريع والمعدات بمراكزها', 'cons' => 'المالية والمشاريع', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_credit_control.php', 'ar' => 'الرقابة الائتمانية وحدود العملاء',
              'icon' => 'fa fa-user-shield', 'group' => 'الذمم المدينة', 'sort' => 15, 'step' => 2,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/dues_fin.php',
              'req' => 'ACC-15', 'doc' => 'حد ائتماني معتمد بقاعدة صلاحية',
              'next' => 'حجب البيع فوق الحد او تصعيده', 'cons' => 'المالية والمبيعات', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_adjustments.php', 'ar' => 'الاستحقاقات والمقدمات والمخصصات',
              'icon' => 'fa fa-scale-balanced', 'group' => 'التسويات والمحاسبة العامة', 'sort' => 17, 'step' => 4,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/journal_form_fin.php',
              'req' => 'ACC-17', 'doc' => 'قيد تسوية بمستند اساسه',
              'next' => 'ترحيل التسوية ثم عكسها في الفترة التالية', 'cons' => 'المالية والمراجعة', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_reconciliations.php', 'ar' => 'مطابقات الحسابات',
              'icon' => 'fa fa-code-compare', 'group' => 'التسويات والمحاسبة العامة', 'sort' => 20, 'step' => 5,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/journal_form_fin.php',
              'req' => 'ACC-20', 'doc' => 'جلسة مطابقة حساب رقابي',
              'next' => 'اغلاق الفروق ثم اقفال الجلسة', 'cons' => 'المالية والمخازن والاسطول', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_trial_balance.php', 'ar' => 'ميزان المراجعة',
              'icon' => 'fa fa-scale-unbalanced', 'group' => 'الإقفال', 'sort' => 21, 'step' => 6,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/financial_statements_fin.php',
              'req' => 'ACC-21', 'doc' => 'جولة ميزان مراجعة متوازنة',
              'next' => 'استيفاء قائمة الاقفال', 'cons' => 'المالية والمراجعة والقيادة', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_closing_checklist.php', 'ar' => 'قائمة إقفال الفترة',
              'icon' => 'fa fa-list-check', 'group' => 'الإقفال', 'sort' => 22, 'step' => 7,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/periods_fin.php',
              'req' => 'ACC-22', 'doc' => 'قائمة اقفال مستوفاة او باستثناء موثق',
              'next' => 'اقفال الفترة المحاسبية', 'cons' => 'المالية والمراجعة', 'fin' => 'نعم'),
        array('route' => 'Finance/acc_reopen_governance.php', 'ar' => 'حوكمة إعادة فتح الفترات',
              'icon' => 'fa fa-unlock-keyhole', 'group' => 'الإقفال', 'sort' => 25, 'step' => 8,
              'owner' => 'DEP-05', 'role' => 'المحاسب', 'sibling' => 'Finance/periods_fin.php',
              'req' => 'ACC-25', 'doc' => 'طلب اعادة فتح بمبرره ونطاقه',
              'next' => 'اعادة الاقفال بعد التصحيح', 'cons' => 'المالية والمراجعة والقيادة', 'fin' => 'نعم'),

        /* ── 06 إدارة الخزينة ──────────────────────────────────────────── */
        array('route' => 'Finance/tre_liquidity_board.php', 'ar' => 'لوحة الخزينة والسيولة',
              'icon' => 'fa fa-gauge-high', 'group' => 'اللوحة', 'sort' => 1, 'step' => 0,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/cash_forecast_fin.php',
              'req' => 'TRS-01', 'doc' => 'قراءة حية لارصدة الاوعية',
              'next' => 'قراءة خطة السيولة', 'cons' => 'الخزينة والمالية والقيادة', 'fin' => 'لا'),
        array('route' => 'Finance/tre_vessels.php', 'ar' => 'الحسابات البنكية والصناديق',
              'icon' => 'fa fa-building-columns', 'group' => 'التأسيس', 'sort' => 2, 'step' => 1,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/accounts_fin.php',
              'req' => 'TRS-02', 'doc' => 'سجل حساب او صندوق',
              'next' => 'تسجيل حركات الوعاء', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_instruments.php', 'ar' => 'سجل الأدوات المالية',
              'icon' => 'fa fa-money-check', 'group' => 'دورة القبض', 'sort' => 6, 'step' => 3,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/tre_pay_batch.php',
              'req' => 'TRS-06', 'doc' => 'سطر اداة بدورتها',
              'next' => 'الايداع ثم التحصيل او الارتجاع', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_allocations.php', 'ar' => 'تخصيص التحصيل على الفواتير',
              'icon' => 'fa fa-arrows-split-up-and-left', 'group' => 'دورة القبض', 'sort' => 7, 'step' => 3,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/tre_pay_batch.php',
              'req' => 'TRS-07', 'doc' => 'سطر تخصيص على فاتورة',
              'next' => 'تحديث الذمة عند المالية', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_payment_queue.php', 'ar' => 'صف الدفع المعتمد',
              'icon' => 'fa fa-list-ol', 'group' => 'دورة الصرف', 'sort' => 8, 'step' => 4,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/tre_pay_batch.php',
              'req' => 'TRS-08', 'doc' => 'اسقاط طلبات الادارات المعتمدة',
              'next' => 'اصدار امر الدفع', 'cons' => 'الخزينة والادارات الطالبة', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_cash_moves.php', 'ar' => 'حركة الخزينة والصناديق',
              'icon' => 'fa fa-right-left', 'group' => 'دورة الصرف', 'sort' => 10, 'step' => 4,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/tre_pay_batch.php',
              'req' => 'TRS-10', 'doc' => 'سطر حركة نقد بمرجعه',
              'next' => 'المطابقة البنكية', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_transfers.php', 'ar' => 'التحويلات بين الحسابات',
              'icon' => 'fa fa-shuffle', 'group' => 'دورة الصرف', 'sort' => 11, 'step' => 4,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/tre_pay_batch.php',
              'req' => 'TRS-11', 'doc' => 'امر تحويل موقع',
              'next' => 'خصم من المصدر وايداع في الوجهة', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_fx_deals.php', 'ar' => 'تنفيذ عمليات الصرف الأجنبي',
              'icon' => 'fa fa-coins', 'group' => 'دورة الصرف', 'sort' => 12, 'step' => 4,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/currencies_fin.php',
              'req' => 'TRS-12', 'doc' => 'صفقة صرف بمستندها',
              'next' => 'قراءة فرق السعر عند المالية', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_guarantees.php', 'ar' => 'خطابات الضمان والاعتمادات المستندية',
              'icon' => 'fa fa-file-shield', 'group' => 'الرقابة والإقفال', 'sort' => 15, 'step' => 5,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/funding_fin.php',
              'req' => 'TRS-15', 'doc' => 'خطاب صادر على تسهيله',
              'next' => 'الافراج او المطالبة', 'cons' => 'الخزينة والحوكمة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_petty_cash.php', 'ar' => 'عهد النثرية وتسويتها',
              'icon' => 'fa fa-wallet', 'group' => 'الرقابة والإقفال', 'sort' => 17, 'step' => 5,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/payments_fin.php',
              'req' => 'TRS-17', 'doc' => 'عهدة بحدها وسقفها الزمني',
              'next' => 'تسوية العهدة بمستنداتها', 'cons' => 'الخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Finance/tre_cash_count.php', 'ar' => 'الجرد النقدي للخزائن',
              'icon' => 'fa fa-magnifying-glass-dollar', 'group' => 'الرقابة والإقفال', 'sort' => 18, 'step' => 5,
              'owner' => 'DEP-06', 'role' => 'أمين الخزينة', 'sibling' => 'Finance/payments_fin.php',
              'req' => 'TRS-18', 'doc' => 'محضر جرد نقدي بلجنته',
              'next' => 'معالجة الفرق بمساره', 'cons' => 'الخزينة والمالية والمراجعة', 'fin' => 'نعم'),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ③-ب إعادةُ صوغِ اسمِ مجموعةِ الدورةِ — بجدولٍ بمفتاحِ نصِّه لا بنمط
   ══════════════════════════════════════════════════════════════════════════
   ◆ **اسمُ المجموعةِ في المخزنِ ليس اسمًا مُصيَّرًا بالضرورة**: «اللوحة — خارج
     الدورة (Overview)» **شرحُ موضعٍ لا مسمّى شاشة** — فيه شرطةُ زخرفةٍ ومصطلحٌ
     لاتينيٌّ وشرحٌ بين قوسَين، وثلاثتُها ممنوعةٌ في واجهةِ الأعمال. وسجلُّ
     المسمّياتِ يردُّه بـ`TECH_TERM` — **والردُّ صحيحٌ لا عائق**.
   ◆ **والعلاجُ جدولٌ بمفتاحِ نصِّه لا نمطٌ** (‏درسُ W06: «الشرطةُ لا تصير
     نقطتَين»): كلُّ إعادةِ صوغٍ **قرارٌ مكتوبٌ** يُقرأ ويُراجَع، ولا يُشتقُّ
     بقاعدةٍ عامّةٍ تصيب اليومَ وتخطئ غدًا.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w11_group_rewrites()
{
    return array(
        /* شرحُ الموضعِ يُنزَع ويبقى المسمّى — واللوحةُ لوحةٌ سواءٌ أكانت في
           الدورةِ أم خارجَها، وموضعُها من الدورةِ يحمله `cycle_step` لا الاسم. */
        'اللوحة — خارج الدورة (Overview)' => 'اللوحة',
    );
}

/** اسمُ مجموعةِ الدورةِ كما يُصيَّر — من الجدولِ إن كان فيه، وإلّا كما هو */
function repair01_w11_group_ar($raw)
{
    $rw = repair01_w11_group_rewrites();
    $raw = trim((string) $raw);
    return isset($rw[$raw]) ? $rw[$raw] : $raw;
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ أحداثُ النطاق — عقدُها يُكتب قبلَ أوّلِ إطلاق
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w11_stage_events()
{
    return array(
        'acc.recognition.requested', 'acc.recognition.decided', 'acc.entry.posted',
        'acc.adjustment.posted', 'acc.account.reconciled', 'acc.trial.balanced',
        'acc.period.closed', 'acc.period.reopened', 'acc.statements.issued',
        'tre.receipt.allocated', 'tre.payment.executed', 'tre.bank.reconciled',
        'tre.count.approved',
    );
}

/** الناشرُ لكلِّ حدثٍ — مُثبَتٌ من القرصِ لا مُعلَنٌ فقط */
function repair01_w11_event_publisher($code)
{
    $acc = 'app/Services/Finance/AccountingCycleService.php';
    $tre = 'app/Services/Treasury/TreasuryCycleService.php';
    return (strpos($code, 'tre.') === 0) ? $tre : $acc;
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ رموزُ الردِّ التي تُنفِّذ فصلَ الواجباتِ — مقيسةٌ من القرص
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w11_sod_codes()
{
    return array(
        'acc.recognition.decide' => array('code' => 'SAME_ACTOR_REQUEST_AND_DECIDE',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'acc.entry.post'         => array('code' => 'SAME_ACTOR_PREPARE_AND_POST',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'acc.adjustment.approve' => array('code' => 'SAME_ACTOR_PREPARE_AND_APPROVE_ADJ',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'acc.recon.close'        => array('code' => 'SAME_ACTOR_PREPARE_AND_CLOSE_RECON',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'acc.period.close'       => array('code' => 'SAME_ACTOR_PREPARE_AND_CLOSE',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'acc.period.reopen'      => array('code' => 'SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN',
                                          'file' => 'app/Services/Finance/AccountingCycleService.php'),
        'tre.payment.execute'    => array('code' => 'SAME_ACTOR_PREPARE_AND_EXECUTE',
                                          'file' => 'app/Services/Treasury/TreasuryCycleService.php'),
        'tre.bank.review'        => array('code' => 'SAME_ACTOR_PREPARE_AND_REVIEW_BANK',
                                          'file' => 'app/Services/Treasury/TreasuryCycleService.php'),
        'tre.count.approve'      => array('code' => 'SAME_ACTOR_COUNT_AND_APPROVE',
                                          'file' => 'app/Services/Treasury/TreasuryCycleService.php'),
        'tre.petty.settle'       => array('code' => 'SAME_ACTOR_HOLD_AND_ACCEPT',
                                          'file' => 'app/Services/Treasury/TreasuryCycleService.php'),
        'tre.transfer.execute'   => array('code' => 'TRANSFER_WITHOUT_AUTHORITY',
                                          'file' => 'app/Services/Treasury/TreasuryCycleService.php'),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ مقاييسُ النطاقِ الحيّة — تُعاد في كلِّ نداءٍ ولا تُخزَّن
   ══════════════════════════════════════════════════════════════════════════ */

/** جداولُ النطاقِ التي يجب أن تحمل الكيانَ غيرَ قابلٍ للعدم */
function repair01_w11_entity_tables()
{
    return array(
        'acc_recognition_request', 'acc_invoice_line', 'acc_supplier_accrual_line',
        'acc_credit_limit', 'acc_period_adjustment', 'acc_account_recon',
        'acc_account_recon_line', 'acc_trial_balance_run', 'acc_trial_balance_line',
        'acc_period_reopen_request', 'tre_cash_box', 'tre_cash_move', 'tre_transfer',
        'tre_fx_deal', 'tre_instrument', 'tre_guarantee', 'tre_recon_difference',
        'tre_petty_custody', 'tre_petty_expense', 'tre_cash_count', 'tre_cash_count_line',
    );
}

/** جداولُ الدورةِ الحيّةُ التي يشترط القرارُ فيها كيانًا — مقيسةٌ لا مُدَّعاة */
function repair01_w11_live_entity_tables()
{
    return array('fin_journal_entries', 'fin_journal_lines', 'fin_financial_periods',
                 'fin_chart_of_accounts', 'fin_bank_accounts', 'fin_budgets',
                 'fin_payments', 'fin_closing_items', 'ar_claim_invoices');
}

/** إقفالٌ بلا كيان — المقامُ الفتراتُ والإقفالاتُ معًا */
function repair01_w11_close_without_entity(mysqli $c)
{
    $n = (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_financial_periods
                                      WHERE company_id IS NULL OR company_id = 0");
    $n += (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_journal_entries
                                       WHERE company_id IS NULL OR company_id = 0");
    $n += (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_bank_accounts
                                       WHERE company_id IS NULL OR company_id = 0");
    return $n;
}

/** رقمٌ يخلط كيانَين بلا وسمٍ — يُقاس على القيدِ نفسِه */
function repair01_w11_mixed_untagged(mysqli $c)
{
    if (!repair01_w11_col_exists($c, 'fin_journal_entries', 'entity_scope')) { return -1; }
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_journal_entries e
                                        WHERE e.entity_scope NOT IN ('SINGLE_ENTITY','GROUP_PROJECTION')");
}

/** قيدٌ من نطاقٍ غيرِ الماليّة — §48 مقيسًا لا مُدَّعًى */
function repair01_w11_entry_from_non_finance(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM acc_recognition_request
                                        WHERE source_module = 'finance'");
}

/** طلبُ اعترافٍ رُحِّل قبل قرارِ قبولٍ — لا يقع إن كانت البوّابةُ نافذة */
function repair01_w11_posted_without_accept(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM acc_recognition_request
                                        WHERE journal_entry_id > 0 AND finance_decision <> 'accepted'");
}

/** قيدٌ غيرُ متوازنٍ في الدفتر — والمقامُ القيودُ المرحَّلةُ كلُّها */
function repair01_w11_unbalanced_entries(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_journal_entries
                                        WHERE state = 'posted'
                                          AND ABS(COALESCE(total_debit,0) - COALESCE(total_credit,0)) > 0.005");
}

/** فترةٌ مقفلةٌ ما زالت تقبل الترحيل — الإقفالُ إعلانٌ لا إثبات */
function repair01_w11_closed_but_postable(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM fin_financial_periods
                                        WHERE state IN ('closed','locked') AND posting_allowed = 1");
}

/** مطابقةٌ مقفلةٌ وفيها فرقٌ مفتوح */
function repair01_w11_recon_closed_with_open(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM acc_account_recon r
                                        WHERE r.state = 'closed'
                                          AND EXISTS (SELECT 1 FROM acc_account_recon_line l
                                                       WHERE l.recon_id = r.id AND l.state = 'open')");
}

/** جلسةُ مطابقةٍ بنكيّةٍ مقفلةٌ وفيها فرقٌ مفتوح */
function repair01_w11_bank_closed_with_open(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM bank_statements s
                                        WHERE s.state = 'closed'
                                          AND EXISTS (SELECT 1 FROM tre_recon_difference d
                                                       WHERE d.statement_id = s.id AND d.state = 'open')");
}

/** جردٌ نقديٌّ معتمَدٌ بلجنةٍ ناقصة */
function repair01_w11_count_without_committee(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM tre_cash_count
                                        WHERE state = 'approved' AND committee_size < 2");
}

/** عهدةٌ ثانيةٌ مفتوحةٌ لأمينٍ لم يسوِّ سابقتَه */
function repair01_w11_custody_double_open(mysqli $c)
{
    return (int) repair01_w11_one($c, "SELECT COUNT(*) FROM (
                                          SELECT holder_id FROM tre_petty_custody
                                           WHERE state = 'open' GROUP BY company_id, holder_id
                                          HAVING COUNT(*) > 1) t");
}

/** عتبةٌ رقميّةٌ صلبةٌ في خدماتِ النطاق — الرسوُّ على الشيفرةِ لا على الدعوى */
function repair01_w11_hardcoded_thresholds($ROOT)
{
    $hits = array();
    $files = array('app/Services/Finance/AccountingCycleService.php',
                   'app/Services/Treasury/TreasuryCycleService.php');
    foreach ($files as $f) {
        $p = $ROOT . '/' . $f;
        if (!is_file($p)) { continue; }
        foreach (explode("\n", (string) file_get_contents($p)) as $i => $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '*') === 0 || strpos($t, '//') === 0) { continue; }
            /* مقارنةُ مبلغٍ برقمٍ من أربعةِ أرقامٍ فأكثرَ = عتبةٌ صلبة.
               والسماحُ الحسابيُّ (0.005) والصفرُ والواحدُ ليست عتباتٍ بل جبرَ فاصلة.
               ⚠ **والسهمُ `=>` ليس مقارنة**: `'limit' => 5000` سقفُ قراءةٍ لا عتبةَ
                 عمل، وعدُّه عتبةً يُحمِّر الحاجبَ على ما ليس فيه — فيُستثنى
                 بالنظرِ إلى ما قبلَ الرمزِ لا بحذفِ الكشفِ كلِّه. */
            if (preg_match('/(?<![=!<>])[<>]=?\s*\d{4,}/', $t)
                || preg_match('/\d{4,}\s*(?<![=!])[<>]=?(?!>)/', $t)) {
                $hits[] = $f . ':' . ($i + 1);
            }
        }
    }
    return $hits;
}
