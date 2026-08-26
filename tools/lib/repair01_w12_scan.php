<?php
/**
 * tools/lib/repair01_w12_scan.php — مقاييسُ المرحلةِ الثانيةَ عشرة (RPR-W12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ إعلان**: كلُّ دالّةٍ هنا **تعيد القياسَ من الحيِّ**
 *   ولا تقرأ ما خزّنَته بوّابةٌ سابقة. والمقامُ يُعاد بناؤه في كلِّ نداء.
 *
 * ◆ **وبياناتُ المرحلةِ سجلٌّ لا حرفيّاتٌ متناثرة**: المِرساةُ والسطحُ والحالةُ
 *   وفصلُ الواجباتِ والعتبةُ والحدثُ — كلُّها مصفوفاتٌ مسمّاةٌ هنا تقرؤها
 *   أداةُ الاشتقافِ والبوّابةُ والرحلةُ والفحصُ السلبيُّ **من موضعٍ واحد**.
 *
 * ◆ **ومحورا هذه المرحلةِ يُقاسان بنيويًّا لا بالنيّة**:
 *   ① **إقفالٌ يخدم معنيَين** — يُقاس على خمسِ جبهات: صنفٌ غريبٌ في جدولِ
 *     صنفٍ · شهريٌّ ليس شهرًا تقويميًّا · تعاقديٌّ بلا رقمِ فترةٍ تعاقديّة ·
 *     نهائيٌّ يتكرّر لعمليةٍ · ومستهلكٌ يقرأ صنفَين **لغرضٍ واحد**.
 *   ② **تصميمٌ مقيَّدٌ ببياناتٍ تاريخية** — يُقاس على خمسٍ أيضًا: صفُّ طبقةٍ
 *     تاريخيّةٍ في جدولِ المستقبل · قدرةٌ خُفِّضت لتناسبَ المجمَّع · عمودُ
 *     نموذجٍ إلزاميٌّ صار يقبل العدم · تخصيصٌ من غيرِ أمرٍ · ومجمَّعٌ بلا حجّيّة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════════════════════════════
   ① أدواتُ قياسٍ عامّة
   ══════════════════════════════════════════════════════════════════════════ */

function repair01_w12_one(mysqli $c, $sql)
{
    $r = @$c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

function repair01_w12_table_exists(mysqli $c, $t)
{
    $r = @$c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w12_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w12_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w12_check_exists(mysqli $c, $t, $name)
{
    $n = (int) repair01_w12_one($c, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                      WHERE CONSTRAINT_SCHEMA = DATABASE()
                                        AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                                        AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $n > 0;
}

/**
 * **العمودُ يحمل الكيانَ ولا يقبل العدم** — الحبّةُ لا تُثبَت بوجودِ العمودِ
 * وحدَه: عمودٌ يقبل `NULL` يسمح بصفٍّ بلا كيانٍ قانونيّ (‏درسُ W11).
 */
function repair01_w12_entity_scoped(mysqli $c, $t)
{
    if (!repair01_w12_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE 'company_id'");
    $x = $r ? $r->fetch_assoc() : null;
    return $x && strtoupper((string) $x['Null']) === 'NO';
}

/** العتباتُ المسجَّلة — تُقرأ ولا تُكتب */
function repair01_w12_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w12_table_exists($c, 'repair01_w12_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w12_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/** حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ */
function repair01_w12_guard_of($ROOT, $route)
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
   `step`: موضعُ السطحِ من **دورةِ عملِ التمويل** — تأسيسٌ ⇐ دورةٌ ⇐ تعاقدٌ ⇐
           أصولٌ ⇐ ماليّةٌ وإقفالاتٌ ⇐ حوكمةٌ ⇐ مرجعيّاتٌ وتقارير.
           ⛔ ولا الأبجديّةُ ولا تاريخُ الإنشاءِ يرتّبان السايدبار.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w12_anchors()
{
    return array(
        /* ── اللوحة — خارج الدورة ─────────────────────────────────────── */
        'FIN-25' => array('route' => 'Financing/financing_board.php', 'probe' => 'financing_operations',
                          'kind' => 'TABLE', 'step' => 0,
                          'why' => 'لوحة المحفظة - مؤشر مشتق من العمليات والاقساط والانحرافات'),
        /* ── التأسيس ──────────────────────────────────────────────────── */
        'FIN-01' => array('route' => 'Financing/financiers_registry.php', 'probe' => 'legal_entities',
                          'kind' => 'TABLE', 'step' => 1,
                          'why' => 'سجل الممولين - الممول كيان قانوني بدوره ولا سجل ثان للكيان'),
        'FIN-02' => array('route' => 'Financing/fin_financier_contacts.php', 'probe' => 'fin_financier_contact',
                          'kind' => 'TABLE', 'step' => 2,
                          'why' => 'جهات اتصال الممول والمفوض - نسخ مؤرخة والتفويض بمستنده'),
        'FIN-03' => array('route' => 'Financing/fin_due_diligence.php', 'probe' => 'fin_financier_document',
                          'kind' => 'TABLE', 'step' => 3,
                          'why' => 'وثائق التاهيل والعناية الواجبة - لا تعاقد مع ممول بلا وثيقة محققة'),
        /* ── الدورة ───────────────────────────────────────────────────── */
        'FIN-04' => array('route' => 'Financing/fin_needs.php', 'probe' => 'fin_funding_need',
                          'kind' => 'TABLE', 'step' => 4,
                          'why' => 'الحاجة التمويلية تسبق العرض - ومن رفعها لا يعتمدها'),
        'FIN-05' => array('route' => 'Financing/fin_offers.php', 'probe' => 'fin_funding_offer',
                          'kind' => 'TABLE', 'step' => 5,
                          'why' => 'عرض التمويل بطبقة اصدارات - التفاوض نسخ لا دهس للسابق'),
        'FIN-06' => array('route' => 'Financing/fin_precontract_review.php', 'probe' => 'fin_precontract_review',
                          'kind' => 'TABLE', 'step' => 6,
                          'why' => 'مراجعة ما قبل التعاقد براي القانوني والمالية والمخاطر كل بمسؤوله'),
        /* ── التعاقد ──────────────────────────────────────────────────── */
        'FIN-07' => array('route' => 'Financing/fin_contracts.php', 'probe' => 'fin_finance_contract',
                          'kind' => 'TABLE', 'step' => 7,
                          'why' => 'عقد تمويل واحد بمستنده - ومن اعده لا يوقعه'),
        'FIN-08' => array('route' => 'Financing/fin_contract_terms.php', 'probe' => 'fin_contract_term',
                          'kind' => 'TABLE', 'step' => 8,
                          'why' => 'بنود العقد سطور بمرجع بندها في المستند لا اعمدة مخترعة'),
        'FIN-09' => array('route' => 'Financing/fin_covenants.php', 'probe' => 'fin_contract_covenant',
                          'kind' => 'TABLE', 'step' => 9,
                          'why' => 'مصفوفة الالتزامات - كل التزام بقاعدة قياسه وعتبته من السجل'),
        'FIN-10' => array('route' => 'Financing/operation_profile.php', 'probe' => 'financing_operations',
                          'kind' => 'TABLE', 'step' => 10,
                          'why' => 'عملية التمويل - الكيان الاب الذي تتفرع عنه الاقساط والاقفالات'),
        /* ── الأصول ───────────────────────────────────────────────────── */
        'FIN-11' => array('route' => 'Equipments/fin_assets.php', 'probe' => 'cmp03_store_rows',
                          'kind' => 'SERVICE', 'step' => 11,
                          'why' => 'الاعيان المولة - علاقة التمويل فقط والماستر بالاسطول'),
        'FIN-12' => array('route' => 'Financing/owners_registry.php', 'probe' => 'asset_ownership_shares',
                          'kind' => 'TABLE', 'step' => 12,
                          'why' => 'حصص الملكية وحق الانتفاع - حصة زمنية واحدة'),
        /* ── المالية · الأقساط ثمَّ الإقفالاتُ الثلاثة ────────────────── */
        'FIN-13' => array('route' => 'Financing/installments.php', 'probe' => 'financing_installments',
                          'kind' => 'TABLE', 'step' => 13,
                          'why' => 'جدول الاقساط - قسط واحد بتاريخ استحقاقه ومكوناته'),
        'FIN-14' => array('route' => 'Financing/fin_financier_dues.php', 'probe' => 'financing_installments',
                          'kind' => 'TABLE', 'step' => 14,
                          'why' => 'استحقاقات الممول مشتقة من الاقساط لا مكتوبة بيد'),
        'FIN-15' => array('route' => 'Financing/fin_contract_close.php', 'probe' => 'fin_contract_close',
                          'kind' => 'TABLE', 'step' => 15,
                          'why' => 'الاقفال التعاقدي كيان مستقل - ممول × عملية × فترة تعاقدية'),
        'FIN-16' => array('route' => 'Financing/fin_monthly_close.php', 'probe' => 'fin_monthly_close',
                          'kind' => 'TABLE', 'step' => 16,
                          'why' => 'الاقفال الشهري كيان مستقل - شهر تقويمي بقيد القاعدة لا فترة تعاقدية'),
        'FIN-17' => array('route' => 'Financing/fin_payment_orders.php', 'probe' => 'fin_payment_order',
                          'kind' => 'TABLE', 'step' => 17,
                          'why' => 'امر الدفع المستقبلي بطبقته - والتاريخي المجمع في جدوله لا هنا'),
        'FIN-18' => array('route' => 'Financing/fin_payment_allocation.php', 'probe' => 'fin_payment_allocation',
                          'kind' => 'TABLE', 'step' => 18,
                          'why' => 'تخصيص السداد على الاقساط من امر دفع لا من صف مجمع'),
        'FIN-19' => array('route' => 'Financing/cost_allocation.php', 'probe' => 'financing_operations',
                          'kind' => 'TABLE', 'step' => 19,
                          'why' => 'توزيع السداد والتكلفة على مستهلكي العملية - مشتق'),
        'FIN-20' => array('route' => 'Financing/fin_capital_balance.php', 'probe' => 'fin_contract_close',
                          'kind' => 'TABLE', 'step' => 20,
                          'why' => 'رصيد راس المال والعائد مشتق كليا من الاقساط والتخصيصات'),
        /* ── الحوكمة ──────────────────────────────────────────────────── */
        'FIN-21' => array('route' => 'Financing/fin_changes.php', 'probe' => 'cmp03_store_rows',
                          'kind' => 'SERVICE', 'step' => 21,
                          'why' => 'تعديلات التمويل واعادة الجدولة - نسخ مؤرخة لا دهس'),
        'FIN-22' => array('route' => 'Financing/deviations.php', 'probe' => 'financing_deviations',
                          'kind' => 'TABLE', 'step' => 22,
                          'why' => 'الانحرافات والمتاخرات - وانحراف مفتوح يحجب الاقفال النهائي'),
        'FIN-23' => array('route' => 'Financing/asset_disposal.php', 'probe' => 'asset_ownership_shares',
                          'kind' => 'TABLE', 'step' => 23,
                          'why' => 'انتقال الملكية والخروج - واقعة بمستندها لا حالة صامتة'),
        'FIN-24' => array('route' => 'Financing/fin_final_close.php', 'probe' => 'fin_final_close',
                          'kind' => 'TABLE', 'step' => 24,
                          'why' => 'الاقفال النهائي كيان مستقل - عملية واحدة مرة واحدة باخلاء طرف'),
        /* ── المرجعيات والتقارير ──────────────────────────────────────── */
        'FIN-26' => array('route' => 'Financing/fin_ref_dictionary.php', 'probe' => 'fin_ref_list',
                          'kind' => 'TABLE', 'step' => 26,
                          'why' => 'القوائم وقاموس البيانات - تعريف حقل واحد بمالكه'),
        'FIN-27' => array('route' => 'Financing/fin_migration_map.php', 'probe' => 'fin_legacy_payment_aggregate',
                          'kind' => 'TABLE', 'step' => 27,
                          'why' => 'خريطة الترحيل ومصفوفة التسوية - الطبقة التاريخية بحجيتها ومرجع صفها'),
        'FIN-28' => array('route' => 'Financing/fin_close_audit.php', 'probe' => 'fin_close_link',
                          'kind' => 'TABLE', 'step' => 28,
                          'why' => 'تقرير المراجعة والاغلاق - يقرا الاقفالات الثلاثة منفصلة وروابطها'),
    );
}

/** إثباتُ المِرساةِ من القرصِ — لا من دعوى السجلّ */
function repair01_w12_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W12_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY',
                              'rule' => 'W12_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W12_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W12_ANCHOR_UNPROVEN');
    }
    /* ⚠ **الرسوُّ على الاسمِ مقتبَسًا لا على جزءٍ منه** (‏درسُ `W11-22`):
         `fin_contract_close_removed` يحوي `fin_contract_close` نصًّا، فبحثٌ
         بلا حدِّ كلمةٍ يُخضِرُّ الحاجبَ وقد نُزع المكشوف. */
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?(?![A-Za-z0-9_])~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W12_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W12_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W12_ANCHOR_UNPROVEN');
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ══════════════════════════════════════════════════════════════════════════
   `sort` هو **موضعُ السطحِ من دورةِ التمويل** — لا الأبجديّةُ ولا الإنشاء.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w12_new_surfaces()
{
    return array(
        array('route' => 'Financing/fin_financier_contacts.php', 'ar' => 'جهات اتصال الممولين والمفوضين',
              'icon' => 'fa fa-address-book', 'group' => 'التأسيس', 'sort' => 2, 'step' => 2,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financiers_registry.php',
              'req' => 'FIN-02', 'doc' => 'سجل جهة اتصال او تفويض بمستنده',
              'next' => 'اعتماد المفوض بالتوقيع على العقد', 'cons' => 'التمويل والقانوني', 'fin' => 'لا'),
        array('route' => 'Financing/fin_due_diligence.php', 'ar' => 'وثائق التأهيل والعناية الواجبة',
              'icon' => 'fa fa-file-circle-check', 'group' => 'التأسيس', 'sort' => 3, 'step' => 3,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financiers_registry.php',
              'req' => 'FIN-03', 'doc' => 'وثيقة تاهيل محققة بتاريخ انتهائها',
              'next' => 'فتح باب العروض للممول المؤهل', 'cons' => 'التمويل والمالية والحوكمة', 'fin' => 'لا'),
        array('route' => 'Financing/fin_needs.php', 'ar' => 'فرص واحتياجات التمويل',
              'icon' => 'fa fa-lightbulb', 'group' => 'الدورة', 'sort' => 4, 'step' => 4,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financing_board.php',
              'req' => 'FIN-04', 'doc' => 'حاجة تمويلية معتمدة بمبررها',
              'next' => 'طلب عروض من الممولين المؤهلين', 'cons' => 'التمويل والادارة الطالبة', 'fin' => 'لا'),
        array('route' => 'Financing/fin_offers.php', 'ar' => 'عروض التمويل والتفاوض',
              'icon' => 'fa fa-file-signature', 'group' => 'الدورة', 'sort' => 5, 'step' => 5,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financing_board.php',
              'req' => 'FIN-05', 'doc' => 'عرض تمويل باصداره ومستنده',
              'next' => 'مراجعة ما قبل التعاقد', 'cons' => 'التمويل والمالية', 'fin' => 'لا'),
        array('route' => 'Financing/fin_precontract_review.php', 'ar' => 'مراجعة ما قبل التعاقد',
              'icon' => 'fa fa-clipboard-check', 'group' => 'الدورة', 'sort' => 6, 'step' => 6,
              /* ⚠ **الشقيقُ يُقاس ببلوغِه لا بقربِه الموضوعيّ**: `gov_dept_cap.php`
                 سطحُ حوكمةٍ `DIRECT_ONLY` بصفرِ بندِ قائمةٍ نشط، فنسخُ بلوغِه
                 يترك السطحَ الجديدَ **بلا بندٍ في السايدبار** — والخطوةُ الأولى
                 تُقرأ `NO_NAV_ITEM`. فالشقيقُ لوحةُ المحفظةِ ببنودِها الحيّة. */
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financing_board.php',
              'req' => 'FIN-06', 'doc' => 'محضر مراجعة براي كل جهة',
              'next' => 'توقيع العقد او حجبه بسبب مكتوب', 'cons' => 'التمويل والقانوني والمخاطر', 'fin' => 'لا'),
        array('route' => 'Financing/fin_contracts.php', 'ar' => 'سجل عقود التمويل',
              'icon' => 'fa fa-file-contract', 'group' => 'التعاقد', 'sort' => 7, 'step' => 7,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financing_operation_new.php',
              'req' => 'FIN-07', 'doc' => 'عقد تمويل موقع بمستنده',
              'next' => 'فتح عملية التمويل وتوليد جدول الاقساط', 'cons' => 'التمويل والمالية', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_contract_terms.php', 'ar' => 'بنود وشروط التمويل',
              'icon' => 'fa fa-list-check', 'group' => 'التعاقد', 'sort' => 8, 'step' => 8,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/fin_contracts.php',
              'req' => 'FIN-08', 'doc' => 'بند تعاقدي بمرجعه في المستند',
              'next' => 'قياس الالتزامات على بنودها', 'cons' => 'التمويل والقانوني', 'fin' => 'لا'),
        array('route' => 'Financing/fin_covenants.php', 'ar' => 'مصفوفة الالتزامات التمويلية',
              'icon' => 'fa fa-scale-balanced', 'group' => 'التعاقد', 'sort' => 9, 'step' => 9,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/fin_contracts.php',
              'req' => 'FIN-09', 'doc' => 'التزام بقاعدة قياسه ودوريته',
              'next' => 'رصد الاخلال او التنازل بمستنده', 'cons' => 'التمويل والمالية والحوكمة', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_financier_dues.php', 'ar' => 'استحقاقات الممول',
              'icon' => 'fa fa-hand-holding-dollar', 'group' => 'المالية', 'sort' => 14, 'step' => 14,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-14', 'doc' => 'قراءة مشتقة لاستحقاق الممول',
              'next' => 'اصدار امر الدفع', 'cons' => 'التمويل والمالية والخزينة', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_contract_close.php', 'ar' => 'الإقفالات التعاقدية',
              'icon' => 'fa fa-file-invoice', 'group' => 'المالية', 'sort' => 15, 'step' => 15,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-15', 'doc' => 'اقفال تعاقدي معتمد بفترته التعاقدية',
              'next' => 'ضم الاقفالات التعاقدية الى اقفال الشهر', 'cons' => 'التمويل والمالية', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_monthly_close.php', 'ar' => 'الإقفالات الشهرية وكشف الحساب',
              'icon' => 'fa fa-calendar-check', 'group' => 'المالية', 'sort' => 16, 'step' => 16,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-16', 'doc' => 'اقفال شهري معتمد ومطابق لكشف الممول',
              'next' => 'مطابقة كشف الممول ثم الاقفال النهائي عند الاستيفاء',
              'cons' => 'التمويل والمالية', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_payment_orders.php', 'ar' => 'أوامر الدفع والسداد الفعلي',
              'icon' => 'fa fa-money-check-dollar', 'group' => 'المالية', 'sort' => 17, 'step' => 17,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-17', 'doc' => 'امر دفع معتمد ثم منفذ بمرجعه البنكي',
              'next' => 'تخصيص المنفذ على الاقساط', 'cons' => 'التمويل والخزينة والمالية', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_payment_allocation.php', 'ar' => 'تخصيص السداد على الأقساط',
              'icon' => 'fa fa-arrows-split-up-and-left', 'group' => 'المالية', 'sort' => 18, 'step' => 18,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-18', 'doc' => 'سطر تخصيص على قسط',
              'next' => 'تحديث رصيد الاقفال التعاقدي', 'cons' => 'التمويل والمالية', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_capital_balance.php', 'ar' => 'رصيد رأس المال والعائد',
              'icon' => 'fa fa-chart-line', 'group' => 'المالية', 'sort' => 20, 'step' => 20,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/installments.php',
              'req' => 'FIN-20', 'doc' => 'قراءة مشتقة لرصيد العملية',
              'next' => 'قراءة موقف الاقفال النهائي', 'cons' => 'التمويل والمالية والقيادة', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_final_close.php', 'ar' => 'إقفال التمويل',
              'icon' => 'fa fa-flag-checkered', 'group' => 'الحوكمة', 'sort' => 24, 'step' => 24,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/deviations.php',
              'req' => 'FIN-24', 'doc' => 'اخلاء طرف او شهادة اقفال نهائي',
              'next' => 'اقفال العملية ونقل الملكية', 'cons' => 'التمويل والمالية والحوكمة', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_ref_dictionary.php', 'ar' => 'القوائم وقاموس البيانات',
              'icon' => 'fa fa-book', 'group' => 'المرجعيات', 'sort' => 26, 'step' => 26,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/fin_models.php',
              'req' => 'FIN-26', 'doc' => 'تعريف حقل او قائمة بمالكه',
              'next' => 'اعتماد التعريف مرجعا للشاشات', 'cons' => 'التمويل والحوكمة', 'fin' => 'لا'),
        array('route' => 'Financing/fin_migration_map.php', 'ar' => 'خريطة الترحيل ومصفوفة التسوية',
              'icon' => 'fa fa-right-left', 'group' => 'المرجعيات', 'sort' => 27, 'step' => 27,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/fin_models.php',
              'req' => 'FIN-27', 'doc' => 'سطر ترحيل بحجيته ومرجع صفه',
              'next' => 'قراءة الطبقة التاريخية موسومة لا مخلوطة', 'cons' => 'التمويل وهندسة النظم', 'fin' => 'نعم'),
        array('route' => 'Financing/fin_close_audit.php', 'ar' => 'تقرير المراجعة والإغلاق',
              'icon' => 'fa fa-magnifying-glass-chart', 'group' => 'التقارير', 'sort' => 28, 'step' => 28,
              'owner' => 'DEP-03', 'role' => 'إدارة التمويل', 'sibling' => 'Financing/financing_board.php',
              'req' => 'FIN-28', 'doc' => 'تقرير يقرا الاقفالات الثلاثة منفصلة',
              'next' => 'رفع الموقف للقيادة', 'cons' => 'التمويل والمالية والمراجعة', 'fin' => 'نعم'),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ③-ب إعادةُ صوغِ اسمِ مجموعةِ الدورةِ — بجدولٍ بمفتاحِ نصِّه لا بنمط
   ══════════════════════════════════════════════════════════════════════════
   ◆ «اللوحة — خارج الدورة (Overview)» **شرحُ موضعٍ لا مسمّى شاشة**: شرطةُ
     زخرفةٍ ومصطلحٌ لاتينيٌّ وشرحٌ بين قوسَين — وسجلُّ المسمّياتِ يردُّه
     بـ`TECH_TERM`، **والردُّ صحيحٌ لا عائق** (‏سابقةُ W11).
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w12_group_rewrites()
{
    return array(
        'اللوحة — خارج الدورة (Overview)' => 'اللوحة',
    );
}

function repair01_w12_group_ar($raw)
{
    $rw = repair01_w12_group_rewrites();
    $raw = trim((string) $raw);
    return isset($rw[$raw]) ? $rw[$raw] : $raw;
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ أحداثُ النطاق — عقدُها يُكتب قبلَ أوّلِ إطلاق
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w12_stage_events()
{
    return array(
        'fin.contract.signed', 'fin.schedule.generated', 'fin.order.approved',
        'fin.order.executed', 'fin.payment.allocated', 'fin.contract.closed',
        'fin.monthly.closed', 'fin.final.closed', 'fin.deviation.raised',
        'fin.ownership.transferred',
    );
}

/** الناشرُ لكلِّ حدثٍ — مُثبَتٌ من القرصِ لا مُعلَنٌ فقط */
function repair01_w12_event_publisher($code)
{
    return 'app/Services/Financing/FinancingCycleService.php';
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ رموزُ الردِّ التي تُنفِّذ فصلَ الواجبات — مقيسةٌ من القرص
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w12_sod_codes()
{
    $svc = 'app/Services/Financing/FinancingCycleService.php';
    return array(
        'fin.need.approve'      => array('code' => 'SAME_ACTOR_RAISE_AND_APPROVE_NEED', 'file' => $svc),
        'fin.contract.sign'     => array('code' => 'SAME_ACTOR_PREPARE_AND_SIGN', 'file' => $svc),
        'fin.order.approve'     => array('code' => 'SAME_ACTOR_REQUEST_AND_APPROVE_ORDER', 'file' => $svc),
        'fin.order.execute'     => array('code' => 'EXECUTE_WITHOUT_APPROVED_ORDER', 'file' => $svc),
        'fin.contract.close'    => array('code' => 'SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE', 'file' => $svc),
        'fin.monthly.close'     => array('code' => 'SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY', 'file' => $svc),
        'fin.final.close'       => array('code' => 'SAME_ACTOR_PREPARE_AND_APPROVE_FINAL', 'file' => $svc),
        'fin.deviation.resolve' => array('code' => 'SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION', 'file' => $svc),
        'fin.covenant.waive'    => array('code' => 'WAIVE_WITHOUT_AUTHORITY', 'file' => $svc),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ **المحورُ الأوّل — إقفالٌ واحدٌ يخدم معنيَين**
   ══════════════════════════════════════════════════════════════════════════
   خمسُ جبهاتٍ تُقاس من الحيِّ في كلِّ نداء. والمجموعُ هو الرقمُ الذي تطلب
   البوّابةُ أن يكون **صفرًا**.
   ══════════════════════════════════════════════════════════════════════════ */

/** جداولُ الإقفالِ الثلاثةُ وصنفُ كلٍّ منها */
function repair01_w12_close_tables()
{
    return array(
        'fin_contract_close' => 'CONTRACTUAL',
        'fin_monthly_close'  => 'MONTHLY',
        'fin_final_close'    => 'FINAL',
    );
}

/** ① صنفٌ غريبٌ في جدولِ صنفٍ — صفٌّ واحدٌ يحمل معنًى ليس معناه */
function repair01_w12_alien_kind_rows(mysqli $c)
{
    $n = 0;
    foreach (repair01_w12_close_tables() as $t => $kind) {
        if (!repair01_w12_table_exists($c, $t)) { continue; }
        $n += (int) repair01_w12_one($c, "SELECT COUNT(*) FROM `$t`
                                           WHERE close_kind <> '" . $c->real_escape_string($kind) . "'");
    }
    return $n;
}

/** ② شهريٌّ ليس شهرًا تقويميًّا — فترةٌ تعاقديّةٌ متنكّرةٌ في وعاءِ الشهر */
function repair01_w12_monthly_not_calendar(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_monthly_close')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_monthly_close
                                        WHERE DAYOFMONTH(month_start) <> 1
                                           OR month_end <> LAST_DAY(month_start)
                                           OR accounting_month <> DATE_FORMAT(month_start, '%Y-%m')");
}

/** ③ تعاقديٌّ بلا رقمِ فترتِه التعاقديّة — فيصير شهرًا مقنَّعًا */
function repair01_w12_contract_without_period(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_contract_close')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_contract_close
                                        WHERE contract_period_no <= 0");
}

/** ④ نهائيٌّ يتكرّر لعمليةٍ — والنهائيُّ مرّةٌ واحدةٌ بحدِّه */
function repair01_w12_final_duplicated(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_final_close')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COALESCE(SUM(n - 1), 0) FROM
        (SELECT COUNT(*) n FROM fin_final_close GROUP BY company_id, op_id HAVING COUNT(*) > 1) x");
}

/** ⑤ مستهلكٌ يقرأ صنفَين **لغرضٍ واحد** — عينُ خلطِ المعنيَين عند القارئ */
function repair01_w12_consumer_dual_kind(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_close_consumption')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM
        (SELECT consumer_key, purpose FROM fin_close_consumption
          GROUP BY consumer_key, purpose HAVING COUNT(DISTINCT close_kind) > 1) x");
}

/** **المقيسُ الحاكمُ الأوّل** — مجموعُ الجبهاتِ الخمس */
function repair01_w12_close_dual_role(mysqli $c)
{
    return repair01_w12_alien_kind_rows($c)
         + repair01_w12_monthly_not_calendar($c)
         + repair01_w12_contract_without_period($c)
         + repair01_w12_final_duplicated($c)
         + repair01_w12_consumer_dual_kind($c);
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑦ **المحورُ الثاني — تصميمٌ مقيَّدٌ ببياناتٍ تاريخية**
   ══════════════════════════════════════════════════════════════════════════ */

/** أعمدةُ نموذجِ المستقبلِ التي **لا يجوز أن تقبل العدم** */
function repair01_w12_future_required_cols()
{
    return array('company_id', 'order_code', 'op_id', 'entity_id', 'requested_at',
                 'requested_by', 'requested_amount', 'currency', 'state');
}

/** ① صفُّ طبقةٍ تاريخيّةٍ في جدولِ المستقبل */
function repair01_w12_legacy_in_future(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_payment_order')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_payment_order WHERE source_kind <> 'FUTURE'");
}

/** ② قدرةٌ في النموذجِ خُفِّضت لتناسبَ ما تستطيعه الصفوفُ المجمَّعة */
function repair01_w12_capability_downgraded(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'repair01_w12_layers')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM repair01_w12_layers
                                        WHERE constrained_by_legacy = 1");
}

/** ③ عمودُ نموذجٍ إلزاميٌّ صار يقبل العدم — تخفيضٌ صامتٌ في المخطَّط */
function repair01_w12_future_col_nullable(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_payment_order')) { return 0; }
    $bad = 0;
    foreach (repair01_w12_future_required_cols() as $col) {
        $r = @$c->query("SHOW COLUMNS FROM `fin_payment_order` LIKE '" . $c->real_escape_string($col) . "'");
        $x = $r ? $r->fetch_assoc() : null;
        if (!$x || strtoupper((string) $x['Null']) !== 'NO') { $bad++; }
    }
    return $bad;
}

/** ④ تخصيصٌ بلا أمرِ دفعٍ — أي تخصيصٌ من صفٍّ مجمَّعٍ تاريخيّ */
function repair01_w12_allocation_without_order(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_payment_allocation')) { return 0; }
    $orphan = (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_payment_allocation a
                                           LEFT JOIN fin_payment_order o ON o.id = a.order_id
                                          WHERE a.order_id <= 0 OR o.id IS NULL");
    $legacy = (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_legacy_payment_aggregate
                                           WHERE allocatable <> 0");
    return $orphan + $legacy;
}

/** ⑤ مجمَّعٌ تاريخيٌّ بلا حجّيّةٍ ولا مرجعِ صفّ — رقمٌ بلا سند */
function repair01_w12_legacy_without_evidence(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_legacy_payment_aggregate')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_legacy_payment_aggregate
                                        WHERE evidence_grade = '' OR source_row_ref = '' OR layer <> 'LEGACY'");
}

/** **المقيسُ الحاكمُ الثاني** — مجموعُ الجبهاتِ الخمس */
function repair01_w12_design_constrained(mysqli $c)
{
    return repair01_w12_legacy_in_future($c)
         + repair01_w12_capability_downgraded($c)
         + repair01_w12_future_col_nullable($c)
         + repair01_w12_allocation_without_order($c)
         + repair01_w12_legacy_without_evidence($c);
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑧ مقاييسُ نطاقٍ أخرى — تُعاد في كلِّ نداءٍ ولا تُخزَّن
   ══════════════════════════════════════════════════════════════════════════ */

/** جداولُ النطاقِ التي يجب أن تحمل الكيانَ غيرَ قابلٍ للعدم */
function repair01_w12_entity_tables()
{
    return array(
        'fin_financier_contact', 'fin_financier_document', 'fin_ref_list',
        'fin_funding_need', 'fin_funding_offer', 'fin_precontract_review',
        'fin_finance_contract', 'fin_contract_term', 'fin_contract_covenant',
        'fin_contract_close', 'fin_monthly_close', 'fin_final_close',
        'fin_close_link', 'fin_payment_order', 'fin_legacy_payment_aggregate',
        'fin_payment_allocation',
    );
}

/** جداولُ الدورةِ الحيّةُ التي يشترط القرارُ فيها كيانًا */
function repair01_w12_live_entity_tables()
{
    return array('financing_operations', 'financing_deviations');
}

/** إقفالٌ معتمَدٌ بلا معتمِدٍ أو باعتمادِ مُعِدِّه — فصلُ الواجباتِ مقيسًا */
function repair01_w12_close_sod_breach(mysqli $c)
{
    $n = 0;
    foreach (array('fin_contract_close', 'fin_monthly_close', 'fin_final_close') as $t) {
        if (!repair01_w12_table_exists($c, $t)) { continue; }
        $n += (int) repair01_w12_one($c, "SELECT COUNT(*) FROM `$t`
                                           WHERE state = 'approved'
                                             AND (approved_by = 0 OR approved_by = prepared_by)");
    }
    return $n;
}

/** إقفالٌ نهائيٌّ معتمَدٌ وفي العمليةِ انحرافٌ حاجبٌ مفتوح */
function repair01_w12_final_over_open_deviation(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_final_close')) { return 0; }
    if (!repair01_w12_col_exists($c, 'financing_deviations', 'final_close_block')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_final_close f
                                        WHERE f.state = 'approved'
                                          AND (f.open_deviations_n > 0 OR f.open_dues_n > 0
                                               OR f.clearance_doc_ref = ''
                                               OR f.last_periodic_close_id = 0)");
}

/** إقفالٌ شهريٌّ معتمَدٌ ولا إقفالَ تعاقديٌّ مربوطٌ به — الشهرُ يضمُّ ولا يخترع */
function repair01_w12_monthly_without_contract_link(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_monthly_close')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_monthly_close m
                                        WHERE m.state = 'approved'
                                          AND m.contract_closes_n <> (
                                              SELECT COUNT(*) FROM fin_close_link l
                                               WHERE l.parent_kind = 'MONTHLY' AND l.parent_id = m.id
                                                 AND l.child_kind = 'CONTRACTUAL')");
}

/** ترحيلُ الأرصدةِ: افتتاحيُّ الفترةِ يساوي ختاميَّ سابقتِها */
function repair01_w12_rollforward_broken(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_contract_close')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_contract_close a
                                        JOIN fin_contract_close b
                                          ON b.company_id = a.company_id AND b.op_id = a.op_id
                                         AND b.contract_period_no = a.contract_period_no - 1
                                       WHERE ABS(a.open_principal - b.close_principal) > 0.005
                                          OR ABS(a.open_profit - b.close_profit) > 0.005");
}

/** أمرُ دفعٍ منفَّذٌ بلا طلبِ اعترافٍ عند الماليّة — §48 مقيسًا لا مُدَّعًى */
function repair01_w12_executed_without_recognition(mysqli $c)
{
    if (!repair01_w12_table_exists($c, 'fin_payment_order')) { return 0; }
    if (!repair01_w12_table_exists($c, 'acc_recognition_request')) { return 0; }
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM fin_payment_order o
                                        LEFT JOIN acc_recognition_request r
                                               ON r.id = o.recognition_request_id
                                       WHERE o.state = 'executed'
                                         AND (o.recognition_request_id = 0 OR r.id IS NULL)");
}

/**
 * ⛔ **النطاقُ يطلب الاعترافَ ولا يكتب قيدًا** (§48) — يُقاس من القرصِ:
 * أيُّ ملفٍّ في النطاقِ يكتب في دفترِ القيدِ مباشرةً.
 */
function repair01_w12_scope_writes_journal($ROOT)
{
    $hits = array();
    $dirs = array($ROOT . '/Financing', $ROOT . '/app/Services/Financing');
    foreach ($dirs as $d) {
        if (!is_dir($d)) { continue; }
        foreach (glob($d . '/*.php') as $f) {
            $src = (string) file_get_contents($f);
            if (preg_match('~(INSERT\s+INTO|UPDATE)\s+`?fin_journal_(entries|lines)`?(?![A-Za-z0-9_])~i', $src)
                || preg_match('~->insert\(\s*[\'"]fin_journal_(entries|lines)[\'"]~i', $src)) {
                $hits[] = str_replace($ROOT . '/', '', str_replace('\\', '/', $f));
            }
        }
    }
    return $hits;
}

/**
 * ⛔ **عتبةٌ رقميّةٌ صلبةٌ في أدواتِ النطاقِ وخدمتِه** — كلُّها من السجلّ.
 * ⚠ **والسهمُ `=>` يحوي `>`** فيُقرأ مقارنةً وهو ليس كذلك (‏عطبُ W11 المقيس):
 *   فيُنظَر إلى ما **قبلَ** الرمزِ ويُستثنى السهمُ والمساواةُ والمُعامِلاتُ المركَّبة.
 */
function repair01_w12_hardcoded_thresholds($ROOT)
{
    $files = array_merge(
        glob($ROOT . '/tools/repair01_w12_*.php') ?: array(),
        glob($ROOT . '/tools/lib/repair01_w12_*.php') ?: array(),
        glob($ROOT . '/app/Services/Financing/*.php') ?: array()
    );
    $hits = array();
    foreach ($files as $f) {
        $src = (string) file_get_contents($f);
        $lines = explode("\n", $src);
        foreach ($lines as $i => $ln) {
            if (preg_match_all('~(.)(>=|<=|>|<)\s*([0-9]{3,})~', $ln, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    if ($m[1] === '=' || $m[1] === '<' || $m[1] === '>' || $m[1] === '!') { continue; }
                    $hits[] = basename($f) . ':' . ($i + 1);
                }
            }
        }
    }
    return $hits;
}

/**
 * الرمزُ يبقى لاتينيًّا في السجلِّ ويُعرَض عربيًّا — مقامُ القاموس.
 *
 * ⚠ **والمقامُ رمزانِ لا واحد** (‏كشفَه الفحصُ السلبيّ): لو اقتُصر على **القيمِ
 *   الحيّةِ في الجداول** لصار المقامُ خاويًا ما دامت الجداولُ فارغةً، فيُخضِرُّ
 *   الحاجبُ على العدمِ ولا يسقط عند نزعِ مسمًّى. فيُضاف إليه **الرموزُ التي
 *   أعلنَتها الموجةُ نفسُها** — وهي مقامٌ ثابتٌ لا يخلو، فالحاجبُ يقظٌ من أوّلِ
 *   يومٍ لا من أوّلِ صفٍّ يُكتب.
 */
function repair01_w12_dict_missing(mysqli $c)
{
    $miss = array();
    /* ① الرموزُ المُعلَنةُ في هذه الموجةِ — مقامٌ لا يخلو */
    $r0 = @$c->query("SELECT raw_code FROM repair01_w6_code_dict
                       WHERE src_ref LIKE 'RPR-W12%' AND (display_ar = '' OR display_ar IS NULL)");
    while ($r0 && $x0 = $r0->fetch_assoc()) { $miss[] = (string) $x0['raw_code']; }

    /* ② والقيمُ الحيّةُ في جداولِ النطاقِ — تنمو مع الاستعمال */
    $need = array();
    $cols = array(
        array('fin_contract_close', 'state'), array('fin_monthly_close', 'state'),
        array('fin_final_close', 'state'), array('fin_payment_order', 'state'),
        array('fin_funding_need', 'state'), array('fin_funding_offer', 'state'),
        array('fin_finance_contract', 'state'), array('fin_contract_covenant', 'state'),
        array('fin_legacy_payment_aggregate', 'evidence_grade'),
        array('fin_payment_allocation', 'part_kind'),
    );
    foreach ($cols as $x) {
        if (!repair01_w12_table_exists($c, $x[0])) { continue; }
        $r = @$c->query("SELECT DISTINCT `{$x[1]}` v FROM `{$x[0]}` WHERE `{$x[1]}` <> ''");
        while ($r && $y = $r->fetch_assoc()) { $need[(string) $y['v']] = true; }
    }
    foreach (array_keys($need) as $code) {
        $n = (int) repair01_w12_one($c, "SELECT COUNT(*) FROM repair01_w6_code_dict
                                          WHERE raw_code = '" . $c->real_escape_string($code) . "'
                                            AND display_ar <> ''");
        if ($n === 0) { $miss[] = $code; }
    }
    return array_values(array_unique($miss));
}

/** مقامُ القاموسِ المُعلَن — يُطبَع مع الحكمِ فلا يُقرأ صفرٌ على خلاء */
function repair01_w12_dict_declared(mysqli $c)
{
    return (int) repair01_w12_one($c, "SELECT COUNT(*) FROM repair01_w6_code_dict
                                        WHERE src_ref LIKE 'RPR-W12%'");
}
