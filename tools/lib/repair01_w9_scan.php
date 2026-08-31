<?php
/**
 * tools/lib/repair01_w9_scan.php — مقاييسُ المرحلةِ التاسعة (RPR-W09)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ إعلان**: كلُّ دالّةٍ هنا **تعيد القياسَ من الحيِّ**
 *   ولا تقرأ ما خزّنَته بوّابةٌ سابقة. والمقامُ يُعاد بناؤه في كلِّ نداء.
 *
 * ◆ **وبياناتُ المرحلةِ سجلٌّ لا حرفيّاتٌ متناثرة**: المِرساةُ والسطحُ والحالةُ
 *   وفصلُ الواجباتِ والعتبةُ والحدثُ والمؤجَّل — كلُّها مصفوفاتٌ مسمّاةٌ هنا،
 *   تقرؤها أداةُ الاشتقافِ والبوّابةُ والفحصُ السلبيُّ **من موضعٍ واحد**.
 *   فمصدرانِ للحقيقةِ يتفرّقان — والحملةُ رصدت ذلك مرارًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════════════════════════════
   ① أدواتُ قياسٍ عامّة
   ══════════════════════════════════════════════════════════════════════════ */

function repair01_w9_one(mysqli $c, $sql)
{
    $r = @$c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

function repair01_w9_table_exists(mysqli $c, $t)
{
    $r = @$c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w9_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w9_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w9_check_exists(mysqli $c, $t, $name)
{
    $n = (int) repair01_w9_one($c, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                     WHERE CONSTRAINT_SCHEMA = DATABASE()
                                       AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $n > 0;
}

/** العتباتُ المسجَّلة — تُقرأ ولا تُكتب */
function repair01_w9_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w9_table_exists($c, 'repair01_w9_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w9_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/** حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ */
function repair01_w9_guard_of($ROOT, $route)
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
   `kind`: `TABLE` جدولٌ يمسُّه الملفّ · `SERVICE` صنفٌ يستدعيه · `GAP` لم يُبنَ.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w9_anchors()
{
    return array(
        /* ── 16 إدارة المشتريات · اللوحة ───────────────────────────────── */
        'PRC-01' => array('route' => 'Procurement/dashboard_proc.php', 'probe' => 'proc_order', 'kind' => 'TABLE',
                          'why' => 'لوحة المشتريات — مؤشر × فترة قراءة حية مشتقة من الطلبات والاوامر والمطابقات'),
        /* ── الطلب والتجميع ────────────────────────────────────────────── */
        'PRC-02' => array('route' => 'Procurement/requests_proc.php', 'probe' => 'proc_request', 'kind' => 'TABLE',
                          'why' => 'طلب الشراء — راس بجهته الطالبة وبنوده تابعة له لا حقل تجميعي'),
        'PRC-03' => array('route' => 'Procurement/requests_proc.php', 'probe' => 'proc_request_line', 'kind' => 'TABLE',
                          'why' => 'بنود طلب الشراء — بند × طلب سجل تابع في الشاشة الام لا سطح مستقل'),
        'PRC-04' => array('route' => 'Procurement/proc_packages.php', 'probe' => 'proc_package', 'kind' => 'TABLE',
                          'why' => 'تجميع الطلبات وخطة الشراء — حزمة × فترة بسبب تجميع مكتوب'),
        'PRC-05' => array('route' => 'Procurement/proc_packages.php', 'probe' => 'proc_package_member', 'kind' => 'TABLE',
                          'why' => 'اعضاء الحزمة — جدول وسيط والطلب لا يضم الى حزمتين'),
        /* ── العروض والترسية ───────────────────────────────────────────── */
        'PRC-06' => array('route' => 'Procurement/proc_rfq.php', 'probe' => 'proc_rfq', 'kind' => 'TABLE',
                          'why' => 'طلب العروض — طلب × حزمة والمظروف لا يقرا قبل موعد فتحه'),
        'PRC-07' => array('route' => 'Procurement/proc_rfq.php', 'probe' => 'proc_rfq_invite', 'kind' => 'TABLE',
                          'why' => 'دعوات الموردين — سطر بردها والصمت يسجل صمتا لا رفضا'),
        'PRC-08' => array('route' => 'Procurement/proc_offers.php', 'probe' => 'proc_offer', 'kind' => 'TABLE',
                          'why' => 'العروض المستلمة — راس العرض كيان بصلاحيته وعملته ووقت تسليمه'),
        'PRC-09' => array('route' => 'Procurement/proc_offers.php', 'probe' => 'proc_offer_line', 'kind' => 'TABLE',
                          'why' => 'بنود العروض — تقابل بندا ببند ببنود الطلب والبديل يعلن ولا يقارن كمطابق'),
        'PRC-10' => array('route' => 'Procurement/proc_award_minutes.php', 'probe' => 'proc_award', 'kind' => 'TABLE',
                          'why' => 'محضر المقارنة والترسية — محضر واحد لكل طلب والفائز غير الادنى يلزمه سبب'),
        /* ── الأمر والتوريد ────────────────────────────────────────────── */
        'PRC-11' => array('route' => 'Procurement/orders_proc.php', 'probe' => 'proc_order', 'kind' => 'TABLE',
                          'why' => 'امر الشراء — راس بمورده وبنوده تابعة؛ ولا امر بلا محضر او سبب مباشرة'),
        'PRC-12' => array('route' => 'Procurement/orders_proc.php', 'probe' => 'proc_order_line', 'kind' => 'TABLE',
                          'why' => 'بنود امر الشراء — بند × امر سجل تابع'),
        'PRC-13' => array('route' => 'Procurement/proc_po_amendments.php', 'probe' => 'proc_po_amendment', 'kind' => 'TABLE',
                          'why' => 'استثناءات الشراء وتعديلات الاوامر — سطر بمساره الحوكمي والمقفل لا يعدل'),
        'PRC-14' => array('route' => 'Procurement/proc_delivery_track.php', 'probe' => 'proc_delivery_event', 'kind' => 'TABLE',
                          'why' => 'متابعة التوريد والاستلام — حدث × امر ومدة التاخر مشتقة لا مدخلة'),
        /* ── المطابقة والإقفال ─────────────────────────────────────────── */
        'PRC-15' => array('route' => 'Procurement/po_match.php', 'probe' => 'proc_invoice_match', 'kind' => 'TABLE',
                          'why' => 'المطابقة الثلاثية — مطابقة × فاتورة × امر والفرق خارج العتبة بقرار مسبب'),
        'PRC-16' => array('route' => 'Procurement/proc_supplier_eval.php', 'probe' => 'proc_supplier_eval', 'kind' => 'TABLE',
                          'why' => 'تقييم اداء التوريد — مورد × فترة سطر مشتق بقاعدة اشتقاق مكتوبة'),

        /* ── 17 إدارة المخازن · اللوحة ─────────────────────────────────── */
        'WH-01'  => array('route' => 'Procurement/warehouse_board.php', 'probe' => 'proc_stock_move', 'kind' => 'TABLE',
                          'why' => 'لوحة المخازن — مؤشر × مخزن × فترة قراءة حية'),
        /* ── التأسيس المرجعي ───────────────────────────────────────────── */
        'WH-02'  => array('route' => 'Procurement/warehouses.php', 'probe' => 'proc_warehouse', 'kind' => 'TABLE',
                          'why' => 'سجل المخازن وانواعها — مخزن سطر واحد'),
        'WH-03'  => array('route' => 'Procurement/wh_custodians.php', 'probe' => 'proc_wh_custodian', 'kind' => 'TABLE',
                          'why' => 'اسناد امناء المخازن (حزمة -3) — سجل تابع بفترته والنافذ اليوم يشتق منه'),
        'WH-04'  => array('route' => 'Procurement/items_proc.php', 'probe' => 'proc_item', 'kind' => 'TABLE',
                          'why' => 'دليل الاصناف — دليل موحد واحد ولا صنف بتكويدين؛ واعلام التتبع مشتقة من سجل الفئات'),
        /* ── دورة الاستلام ─────────────────────────────────────────────── */
        'WH-05'  => array('route' => 'Procurement/wh_receipt.php', 'probe' => 'proc_receipt_custody', 'kind' => 'TABLE',
                          'why' => 'سند الادخال والفحص — راس بمورده وامره وبنوده تابعة'),
        'WH-06'  => array('route' => 'Procurement/wh_receipt.php', 'probe' => 'proc_receipt_line', 'kind' => 'TABLE',
                          'why' => 'بنود سند الادخال — الوارد والمقبول والمرفوض وبيانات التتبع بالانطباق'),
        'WH-07'  => array('route' => 'Procurement/stock_proc.php', 'probe' => 'proc_stock_state', 'kind' => 'TABLE',
                          'why' => 'ارصدة المخزون بحالاتها — صنف × مخزن × حالة سطر مشتق لا يكتب بيد'),
        'WH-08'  => array('route' => 'Procurement/wh_hazmat.php', 'probe' => 'proc_hazmat_control', 'kind' => 'TABLE',
                          'why' => 'ضوابط المواد الخطرة — سجل لكل صنف لا قائمة صلبة والتصريح شرط صرف'),
        /* ── دورة الصرف ────────────────────────────────────────────────── */
        'WH-09'  => array('route' => 'Procurement/wh_issue_requests.php', 'probe' => 'proc_issue_request', 'kind' => 'TABLE',
                          'why' => 'طلبات الصرف الواردة — طلب × جهة والجهة تطلب والمخزن يصرف'),
        'WH-10'  => array('route' => 'Procurement/wh_issue_requests.php', 'probe' => 'proc_issue_request_line', 'kind' => 'TABLE',
                          'why' => 'بنود طلب الصرف — غير بنود السند وخفض المعتمد بسبب مكتوب'),
        'WH-11'  => array('route' => 'Procurement/issue_proc.php', 'probe' => 'proc_issue', 'kind' => 'TABLE',
                          'why' => 'سند الصرف — راس بمستلمه وبنوده تابعة'),
        'WH-12'  => array('route' => 'Procurement/issue_proc.php', 'probe' => 'proc_issue_line', 'kind' => 'TABLE',
                          'why' => 'بنود سند الصرف — بند × سند سجل تابع'),
        'WH-13'  => array('route' => 'Procurement/receipt_custody_proc.php', 'probe' => 'proc_custody', 'kind' => 'TABLE',
                          'why' => 'العهد والمرتجعات — عهدة × مستلم بمرتجعها ومستهلكها'),
        'WH-14'  => array('route' => 'Procurement/wh_transfer.php', 'probe' => 'proc_transfer', 'kind' => 'TABLE',
                          'why' => 'التحويل بين المخازن — امر × مخزنين والمرسل ليس المستلم'),
        'WH-15'  => array('route' => 'Procurement/wh_transfer.php', 'probe' => 'proc_transfer_line', 'kind' => 'TABLE',
                          'why' => 'بنود التحويل — بند × امر وفرق الاستلام بسبب مكتوب'),
        /* ── الرقابة والإقفال ──────────────────────────────────────────── */
        'WH-16'  => array('route' => 'Procurement/wh_count.php', 'probe' => 'proc_count_session', 'kind' => 'TABLE',
                          'why' => 'الجرد ومعالجة الفروقات — جلسة × مخزن وبنود الفروق تابعة'),
        'WH-17'  => array('route' => 'Procurement/wh_count.php', 'probe' => 'proc_count_line', 'kind' => 'TABLE',
                          'why' => 'بنود الجرد — صنف × جلسة بقرار تسوية مسبب والدفتري مشتق لا مكتوب'),
        'WH-18'  => array('route' => 'Procurement/reordering_proc.php', 'probe' => 'proc_orderpoint', 'kind' => 'TABLE',
                          'why' => 'حدود الطلب واعادة التزويد — صنف × مخزن سطر تنبيه مشتق'),
        'WH-19'  => array('route' => 'Procurement/wh_month_close.php', 'probe' => 'proc_wh_close', 'kind' => 'TABLE',
                          'why' => 'الاقفال الشهري — شهر × مخزن اقفال واحد بمعادلة تنطبق'),
    );
}

/** إثباتُ المِرساةِ من القرصِ والسجلِّ معًا — لا من الإعلان */
function repair01_w9_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['kind'] === 'GAP' || $a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W9_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY', 'rule' => 'W9_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W9_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W9_ANCHOR_UNPROVEN');
    }
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?\b~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W9_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W9_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W9_ANCHOR_UNPROVEN');
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w9_new_surfaces()
{
    return array(
        array('route' => 'Procurement/proc_packages.php', 'ar' => 'تجميع الطلبات وخطة الشراء',
              'icon' => 'fa fa-layer-group', 'group' => 'الطلب والتجميع', 'sort' => 4,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/requests_proc.php',
              'req' => 'PRC-04 · PRC-05', 'doc' => 'حزمة شراء معتمدة',
              'next' => 'اصدار طلب عروض على الحزمة', 'cons' => 'المشتريات والمالية', 'fin' => 'لا'),
        array('route' => 'Procurement/proc_rfq.php', 'ar' => 'طلب العروض ودعوات الموردين',
              'icon' => 'fa fa-envelope-open-text', 'group' => 'العروض والترسية', 'sort' => 6,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/requests_proc.php',
              'req' => 'PRC-06 · PRC-07', 'doc' => 'طلب عروض ودعوات مرسلة',
              'next' => 'استلام العروض ثم فتح المظاريف', 'cons' => 'المشتريات والموردون', 'fin' => 'لا'),
        array('route' => 'Procurement/proc_offers.php', 'ar' => 'عروض الموردين المستلمة',
              'icon' => 'fa fa-file-invoice', 'group' => 'العروض والترسية', 'sort' => 8,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/rfq_compare_award.php',
              'req' => 'PRC-08 · PRC-09', 'doc' => 'عرض مورد مسجل ببنوده',
              'next' => 'المقارنة ومحضر الترسية', 'cons' => 'المشتريات ولجنة الترسية', 'fin' => 'لا'),
        array('route' => 'Procurement/proc_award_minutes.php', 'ar' => 'محضر المقارنة والترسية',
              'icon' => 'fa fa-gavel', 'group' => 'العروض والترسية', 'sort' => 10,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/rfq_compare_award.php',
              'req' => 'PRC-10', 'doc' => 'محضر ترسية معتمد',
              'next' => 'اصدار امر الشراء على المحضر', 'cons' => 'المشتريات والمالية والمراجعة', 'fin' => 'نعم'),
        array('route' => 'Procurement/proc_po_amendments.php', 'ar' => 'استثناءات الشراء وتعديلات الأوامر',
              'icon' => 'fa fa-pen-to-square', 'group' => 'الأمر والتوريد', 'sort' => 13,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/orders_proc.php',
              'req' => 'PRC-13', 'doc' => 'سطر تعديل بمساره الحوكمي',
              'next' => 'اعتماد التعديل او رده', 'cons' => 'المشتريات والمالية والمراجعة', 'fin' => 'نعم'),
        array('route' => 'Procurement/proc_delivery_track.php', 'ar' => 'متابعة التوريد والاستلام',
              'icon' => 'fa fa-truck-fast', 'group' => 'الأمر والتوريد', 'sort' => 14,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/orders_proc.php',
              'req' => 'PRC-14', 'doc' => 'سطر متابعة توريد',
              'next' => 'سند ادخال المخزن', 'cons' => 'المشتريات والمخازن', 'fin' => 'لا'),
        array('route' => 'Procurement/proc_supplier_eval.php', 'ar' => 'تقييم أداء التوريد',
              'icon' => 'fa fa-ranking-star', 'group' => 'المطابقة والإقفال', 'sort' => 16,
              'owner' => 'DEP-16', 'role' => 'مسؤول المشتريات', 'sibling' => 'Procurement/suppliers_proc.php',
              'req' => 'PRC-16', 'doc' => 'سطر تقييم مشتق',
              'next' => 'قراءة لجنة التاهيل', 'cons' => 'المشتريات والقيادة', 'fin' => 'لا'),
        array('route' => 'Procurement/wh_hazmat.php', 'ar' => 'ضوابط المواد الخطرة والمتفجرات',
              'icon' => 'fa fa-triangle-exclamation', 'group' => 'دورة الاستلام', 'sort' => 7,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/items_proc.php',
              'req' => 'WH-08', 'doc' => 'سطر ضوابط صنف خطر',
              'next' => 'بوابة صرف بتصريح', 'cons' => 'المخازن والسلامة', 'fin' => 'لا'),
        array('route' => 'Procurement/wh_issue_requests.php', 'ar' => 'طلبات الصرف الواردة',
              'icon' => 'fa fa-inbox', 'group' => 'دورة الصرف', 'sort' => 8,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/issue_proc.php',
              'req' => 'WH-09 · WH-10', 'doc' => 'طلب صرف معتمد',
              'next' => 'سند صرف من المخزن', 'cons' => 'المخازن والجهة الطالبة', 'fin' => 'نعم'),
        array('route' => 'Procurement/wh_month_close.php', 'ar' => 'الإقفال الشهري للمخازن',
              'icon' => 'fa fa-lock', 'group' => 'الرقابة والإقفال', 'sort' => 18,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/wh_count.php',
              'req' => 'WH-19', 'doc' => 'سطر اقفال شهري متوازن',
              'next' => 'ترحيل الرصيد الى المالية', 'cons' => 'المخازن والمالية', 'fin' => 'نعم'),

        /* ── أسطحُ جوابِ `DEC-OPEN-15` (2026-08-26) ──────────────────────────
           ◆ **«المرونةُ في الإعداداتِ لا في الكود»** (‏القاعدةُ ㉗): سياسةُ
             التتبّعِ سطحٌ تديره الإدارةُ المخوَّلةُ لا تعديلٌ برمجيّ.
           ◆ **والجودةُ تُقاس ولا تحجب** (‏㉚ و㉛): سطحُ النِّسَبِ **مؤشِّرُ نضجٍ**
             لا حاجبَ تشغيل. */
        array('route' => 'Procurement/proc_track_policy.php', 'ar' => 'سياسة تتبع الأصناف',
              'icon' => 'fa fa-sliders', 'group' => 'التأسيس المرجعي', 'sort' => 4,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/items_proc.php',
              'req' => 'WH-04', 'doc' => 'سياسة تتبع بنسختها وتاريخ سريانها',
              'next' => 'حل السياسة على الاصناف', 'cons' => 'المخازن والمشتريات والصيانة', 'fin' => 'لا'),
        array('route' => 'Procurement/wh_lots.php', 'ar' => 'سجل الدفعات',
              'icon' => 'fa fa-layer-group', 'group' => 'دورة الاستلام', 'sort' => 6,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/wh_receipt.php',
              'req' => 'WH-06', 'doc' => 'سطر دفعة بكميتها وتواريخها',
              'next' => 'ترتيب الصرف بالصلاحية', 'cons' => 'المخازن والجودة', 'fin' => 'لا'),
        array('route' => 'Procurement/wh_serials.php', 'ar' => 'سجل الأرقام التسلسلية',
              'icon' => 'fa fa-barcode', 'group' => 'دورة الاستلام', 'sort' => 7,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/wh_receipt.php',
              'req' => 'WH-06', 'doc' => 'سطر قطعة بدورة حياتها',
              'next' => 'ربط القطعة باصلها وعهدتها', 'cons' => 'المخازن والصيانة والاسطول', 'fin' => 'لا'),
        array('route' => 'Procurement/wh_track_quality.php', 'ar' => 'جودة بيانات التتبع',
              'icon' => 'fa fa-chart-simple', 'group' => 'الرقابة والإقفال', 'sort' => 19,
              'owner' => 'DEP-17', 'role' => 'أمين المخزن', 'sibling' => 'Procurement/wh_count.php',
              'req' => 'WH-04', 'doc' => 'سطر مؤشر نضج مشتق',
              'next' => 'رفع سياسة صنف الى الالزام عند النضج', 'cons' => 'المخازن والقيادة', 'fin' => 'لا'),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ أحداثُ النطاق — عقدُها يُكتب قبلَ أوّلِ إطلاق
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w9_stage_events()
{
    return array(
        'PRC_PACKAGE_JOINED', 'PRC_RFQ_INVITED', 'PRC_OFFER_RECEIVED', 'PRC_RFQ_OPENED',
        'PRC_AWARD_DRAFTED', 'PRC_AWARD_APPROVED', 'PRC_ORDER_ANCHORED', 'PRC_ORDER_DIRECT',
        'PRC_ORDER_AMENDED', 'PRC_DELIVERY_LOGGED', 'PRC_INVOICE_MATCHED', 'PRC_VARIANCE_DECIDED',
        'PRC_SUPPLIER_EVALUATED',
        'WH_RECEIPT_LINE_ADDED', 'WH_ISSUE_REQUEST_APPROVED', 'WH_ISSUED',
        'WH_TRANSFER_SENT', 'WH_TRANSFER_RECEIVED', 'WH_COUNT_APPROVED', 'WH_MONTH_CLOSED',
    );
}

/** كياناتُ آلاتِ الحالةِ في النطاق */
function repair01_w9_entity_types()
{
    return array('proc_package', 'proc_rfq', 'proc_offer', 'proc_award', 'proc_order',
                 'proc_issue_request', 'proc_transfer', 'proc_count_session', 'proc_wh_close');
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ المؤجَّلُ بحاجبٍ مفتوح — موضعُ السؤالِ محفوظًا لا منسيًّا
   ══════════════════════════════════════════════════════════════════════════
   ◆ **أمرُ المالكِ نصًّا** (2026-08-26): «قدم لي السؤال في شكل رسالة نصية
     وساجيبك عليه لاحقا احفظ مكانه واكمل المرحلة بدونه».
   ◆ ولذلك: **البنيةُ تُبنى كاملةً وتُفحص سلبيًّا**، والفئاتُ وحدَها تنتظر.
     وكلُّ صفٍّ هنا يحمل **استعلامَ إثباتٍ** يقيس أنَّ الانتظارَ ما زال قائمًا —
     فلا يُصدَّق سردٌ ولا يُنسى بندٌ.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w9_deferred_rows()
{
    /* ⚠ **استعلامُ الإثباتِ تغيَّر مع جوابِ المالكِ — والتغييرُ مُعلَنٌ لا صامت.**
       ═════════════════════════════════════════════════════════════════════
       كُتبت هذه الاستعلاماتُ أوّلَ مرّةٍ على بنيةِ W09: عَلَمٌ ثنائيٌّ في
       `proc_item_track_rule`. وجوابُ `DEC-OPEN-15` (2026-08-26) **استبدل
       الشكلَ**: ثلاثيٌّ في `proc_track_policy` بمستويَين وثماني خصائص.
       فالاستعلامُ القديمُ يقيس جدولًا لم يعد يحمل الحقيقة — **وإبقاؤه يجعل
       البندَ ينتظر إلى الأبد**، وهو عمًى لا تشدُّد.
       ⛔ **والجديدُ ليس تخفيفًا**: كلُّ استعلامٍ هنا **صفرٌ قبل الجوابِ
       وغيرُ صفرٍ بعده** — يقيس أنَّ الانتظارَ انتهى فعلًا لا أنّه أُعلن منتهيًا. */
    return array(
        array(
            'defer_key' => 'W9-DEF-01',
            'requirement_id' => 'WH-04',
            'blocked_by' => 'DEC-OPEN-15',
            'part_built' => 'سجل proc_track_policy بمستويي الفئة والصنف وثماني خصائص ونسخ مؤرخة · واعمدة الدرجة الثلاثية في proc_item محلولة من السجل · ودالة resolve تحل بالاسبقية والتاريخ معا',
            'part_waiting' => 'افتراضات الفئات نفسها: اي درجة لكل خاصية في كل فئة. وهي صفوف سجل لا تغيير مخطط',
            'resume_step' => 'ابذر صفا في proc_track_policy لكل فئة من جدول جواب المالك بنسختها وتاريخ سريانها ثم حل السياسة الى اعمدة كل صنف بـmaterialize ثم ارفع consumed',
            'probe_sql' => "SELECT COUNT(*) FROM proc_track_policy WHERE scope_kind = 'CATEGORY'",
        ),
        array(
            'defer_key' => 'W9-DEF-02',
            'requirement_id' => 'WH-06',
            'blocked_by' => 'DEC-OPEN-15',
            'part_built' => 'اعمدة lot_no و serial_no و expiry_date و mfg_date و warranty_until في بند سند الادخال · وكيانا proc_lot و proc_serial · وسجل proc_track_gap قيد جودة لا حاجب عمل · وcheckOperation بثلاثة احكام تمر وتسجل وتمنع',
            'part_waiting' => 'اشتعال المسار عمليا — فما دام لا صنف يحمل درجة غير معطلة لا يسجل نقص ولا يقاس اكتمال',
            'resume_step' => 'بعد بذر الفئات تحقق ان صنفا واحدا على الاقل يحمل درجة غير معطلة ثم شغل رحلة استلام بلا بيانات تتبع وتحقق ان الحكم gap لا block ثم ارفع consumed',
            'probe_sql' => "SELECT COUNT(*) FROM proc_item WHERE track_lot_level <> 'OFF'"
                         . " OR track_serial_level <> 'OFF' OR track_mfg_level <> 'OFF'"
                         . " OR track_expiry_level <> 'OFF' OR track_warranty_level <> 'OFF'",
        ),
        array(
            'defer_key' => 'W9-DEF-03',
            'requirement_id' => 'WH-11',
            'blocked_by' => 'DEC-OPEN-15',
            'part_built' => 'expiryVerdict بثلاثة مستويات انفاذ · وsuggestIssueOrder بسلسلة ارتداد FEFO ثم FIFO ثم الكمية · وproc_expiry_override يوجب دور السياسة لا دور المنفذ · وproc_requalification بدورة اعادة التاهيل',
            'part_waiting' => 'سياسة المنتهي نفسها ومن يملك تجاوزها وترتيب الصرف لكل فئة',
            'resume_step' => 'املا expiry_enforce و issue_policy و override_authority في كل سياسة فئة تتبع صلاحيتها ثم جرب صرف منتهي وتحقق من الحكم ثم ارفع consumed',
            'probe_sql' => "SELECT COUNT(*) FROM proc_track_policy WHERE expiry <> 'OFF'",
        ),
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ قياساتُ البوّابةِ — كلُّها تُعيد البناءَ ولا تقرأ المخزَّن
   ══════════════════════════════════════════════════════════════════════════ */

/** أوامرُ شراءٍ بلا سندٍ تنافسيٍّ ولا سببِ مباشرة — العطبُ المقيسُ في W09 */
function repair01_w9_orders_without_basis(mysqli $c)
{
    $out = array();
    if (!repair01_w9_col_exists($c, 'proc_order', 'award_minute_id')) { return $out; }
    $r = $c->query("SELECT id, code, base_amount FROM proc_order
                     WHERE COALESCE(award_minute_id,0) = 0 AND COALESCE(direct_reason,'') = ''
                       AND COALESCE(is_deleted,0) = 0");
    while ($r && $x = $r->fetch_assoc()) { $out[] = $x; }
    return $out;
}

/** عدّاداتُ الحزمِ — يُعاد اشتقاقُها وتُقارَن بالمخزَّن */
function repair01_w9_package_drift(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_package')) { return $bad; }
    $r = $c->query("SELECT p.id, p.code, p.member_count,
                           (SELECT COUNT(*) FROM proc_package_member m WHERE m.package_id = p.id) AS live
                      FROM proc_package p WHERE COALESCE(p.is_deleted,0) = 0");
    while ($r && $x = $r->fetch_assoc()) {
        if ((int) $x['member_count'] !== (int) $x['live']) { $bad[] = $x; }
    }
    return $bad;
}

/** الأدنى في محضرِ الترسيةِ — يُعاد اشتقاقُه من العروضِ ويُقارَن */
function repair01_w9_award_drift(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_award')) { return $bad; }
    $r = $c->query("SELECT a.id, a.minute_no, a.winner_id, a.is_lowest, a.award_why,
                           (SELECT o.supplier_id FROM proc_offer o WHERE o.rfq_id = a.rfq_id
                             ORDER BY o.base_amount ASC, o.id ASC LIMIT 1) AS live_low
                      FROM proc_award a");
    while ($r && $x = $r->fetch_assoc()) {
        $liveLow = (int) $x['live_low'];
        if ($liveLow === 0) { continue; }
        $shouldBe = ($liveLow === (int) $x['winner_id']) ? 1 : 0;
        if ((int) $x['is_lowest'] !== $shouldBe) { $bad[] = $x + array('should' => $shouldBe); }
        elseif ($shouldBe === 0 && trim((string) $x['award_why']) === '') { $bad[] = $x + array('should' => 0); }
    }
    return $bad;
}

/** أيّامُ التأخّرِ في متابعةِ التوريد — تُعاد من الوعدِ والواقع */
function repair01_w9_delay_drift(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_delivery_event')) { return $bad; }
    $r = $c->query("SELECT e.id, e.delay_days, e.event_date, o.expected_delivery_date
                      FROM proc_delivery_event e JOIN proc_order o ON o.id = e.order_id");
    while ($r && $x = $r->fetch_assoc()) {
        $p = (string) $x['expected_delivery_date']; $a = (string) $x['event_date'];
        $live = ($p !== '' && $a !== '' && $a > $p) ? (int) floor((strtotime($a) - strtotime($p)) / 86400) : 0;
        if ((int) $x['delay_days'] !== $live) { $bad[] = $x + array('live' => $live); }
    }
    return $bad;
}

/** أرصدةُ الحالاتِ — تُعاد من الحركاتِ وتُقارَن بالمخزَّن */
function repair01_w9_stock_state_drift(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_stock_state')) { return $bad; }
    $svc = dirname(dirname(__DIR__)) . '/app/Services/Procurement/StockMoveService.php';
    if (is_file($svc)) { require_once $svc; }
    if (!class_exists('\App\Services\Procurement\StockMoveService')) { return $bad; }
    $r = $c->query("SELECT s.id, s.item_id, s.warehouse_id, s.state_key, s.qty
                      FROM proc_stock_state s WHERE s.state_key = 'GOOD'");
    while ($r && $x = $r->fetch_assoc()) {
        /* ⛔ **قائمةُ الوارد لا تُكتب هنا** — تُقرأ من `StockMoveService::INBOUND`
             وهي مصدرُ القادحِ نفسِه (‏هجرة 2027-04-14). ونسخةٌ ثانيةٌ في أداةِ
             قياسٍ تجعل البوّابةَ تصدّق حسابًا يخالف ما تنفّذه القاعدة. */
        $in = "'" . implode("','", array_map(function ($v) use ($c) {
            return $c->real_escape_string($v);
        }, \App\Services\Procurement\StockMoveService::INBOUND)) . "'";
        $live = (float) repair01_w9_one($c, "SELECT COALESCE(SUM(CASE
                    WHEN move_type IN ($in) THEN qty ELSE -qty END),0)
                  FROM proc_stock_move WHERE item_id = " . (int) $x['item_id']
                . " AND warehouse_id = " . (int) $x['warehouse_id']);
        if (abs($live - (float) $x['qty']) > 0.001) { $bad[] = $x + array('live' => $live); }
    }
    return $bad;
}

/** فروقُ جردٍ بلا قرارِ تسويةٍ مُسبَّب في جلسةٍ معتمَدة */
function repair01_w9_count_open_diffs(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_count_line')) { return $bad; }
    $r = $c->query("SELECT l.id, l.session_id, l.item_id, l.qty_diff, l.settle_action, l.settle_why
                      FROM proc_count_line l JOIN proc_count_session s ON s.id = l.session_id
                     WHERE s.state = 'approved' AND ABS(l.qty_diff) > 0.0001
                       AND (COALESCE(l.settle_action,'') = '' OR COALESCE(l.settle_why,'') = '')");
    while ($r && $x = $r->fetch_assoc()) { $bad[] = $x; }
    return $bad;
}

/** إقفالٌ بمعادلةٍ لا تنطبق */
function repair01_w9_close_unbalanced(mysqli $c)
{
    $bad = array();
    if (!repair01_w9_table_exists($c, 'proc_wh_close')) { return $bad; }
    $r = $c->query("SELECT id, warehouse_id, period_ym, open_value, in_value, out_value, adj_value,
                           close_value, balanced FROM proc_wh_close WHERE state = 'closed'");
    while ($r && $x = $r->fetch_assoc()) {
        $live = (float) $x['open_value'] + (float) $x['in_value'] - (float) $x['out_value'] + (float) $x['adj_value'];
        if (abs($live - (float) $x['close_value']) > 0.01 || (int) $x['balanced'] !== 1) {
            $bad[] = $x + array('live' => $live);
        }
    }
    return $bad;
}

/** مقارنةُ عتبةٍ صلبةٌ في أداةٍ أو خدمةٍ من النطاق — العتبةُ من السجلِّ وحدَه */
function repair01_w9_hardcoded_thresholds($ROOT)
{
    $hits = array();
    $files = array(
        $ROOT . '/app/Services/Procurement/ProcurementCycleService.php',
        $ROOT . '/app/Services/Warehouse/WarehouseCycleService.php',
    );
    foreach ($files as $f) {
        if (!is_file($f)) { continue; }
        foreach (file($f) as $i => $line) {
            if (preg_match('/^\s*(\*|\/\/|\/\*)/', $line)) { continue; }
            /* مقارنةُ عتبةٍ = متغيّرٌ يُقارَن برقمٍ ذي معنًى تجاريّ (‏> 100) */
            if (preg_match('~[\$\w\]\)]\s*(>=|<=|>|<)\s*([1-9]\d{2,})(?!\s*\))~', $line, $m)) {
                $hits[] = basename($f) . ':' . ($i + 1) . ' ⇐ ' . trim(mb_substr($line, 0, 60));
            }
        }
    }
    return $hits;
}
