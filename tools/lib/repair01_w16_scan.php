<?php
/**
 * tools/lib/repair01_w16_scan.php — عُدَّةُ قياسِ المرحلةِ السادسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولكلِّ مقياسٍ مقامُه**: كلُّ دالّةٍ هنا تعيد `array(num, den, den_name)`
 *   — ⛔ **ولا تعيد نسبةً**. النسبةُ تُحسب عند العرضِ من رقمَين مطبوعَين،
 *   فالرقمُ بلا مقامٍ لا يُثبت شيئًا.
 *
 * ◆ **والمقامُ كاملٌ لا مختار**: نطاقٌ لا أداةَ له يعود `NOT_MEASURED`
 *   ⛔ **ولا يعود صفرًا** — وهذه هي القاعدةُ التي تحرسها `chk_w16_sc_den`.
 *
 * ◆ **والنسبةُ إلى النطاقِ تُشتقُّ من البادئةِ الرقميّةِ أوّلًا** لأنّها حاسمة،
 *   ثمَّ من جدولِ الأسماءِ حين لا بادئة. **وما لا يُنسَب يُعَدُّ ويُعلَن**
 *   (`unit_unmapped`) ⛔ **ولا يُطرح صامتًا من المقام**.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** النطاقاتُ التي تُنشَر لها المقاماتُ التسعة. */
function repair01_w16_domains()
{
    return array(
        'DEP-01', 'DEP-02', 'DEP-03', 'DEP-04', 'DEP-05', 'DEP-06', 'DEP-07', 'DEP-08',
        'DEP-09', 'DEP-10', 'DEP-11', 'DEP-12', 'DEP-13', 'DEP-14', 'DEP-15', 'DEP-16',
        'DEP-17', 'EX-CEO', 'EX-DVP', 'IAF', 'WS-MY', 'PLATFORM',
    );
}

/** أسماءُ الوحداتِ الحيّةِ ⇐ الرمزُ المعياريّ — حين لا بادئةَ رقميّة. */
function repair01_w16_unit_names()
{
    return array(
        'إدارة المبيعات التعاقدية والعقود' => 'DEP-01',
        'المبيعات والعقود'                 => 'DEP-01',
        'إدارة الموردين'                   => 'DEP-02',
        'إدارة التمويل والممولين'          => 'DEP-03',
        'التمويل والملكية'                 => 'DEP-03',
        'إدارة الأسطول والأصول'            => 'DEP-04',
        'الأسطول'                          => 'DEP-04',
        'إدارة الأسطول'                    => 'DEP-04',
        'الإدارة المالية'                  => 'DEP-05',
        'إدارة الخزينة'                    => 'DEP-06',
        'إدارة الموارد البشرية'            => 'DEP-07',
        'الموارد البشرية'                  => 'DEP-07',
        'الحوكمة والالتزام'                => 'DEP-08',
        'إدارة الحوكمة والالتزام'          => 'DEP-08',
        'إدارة المخاطر'                    => 'DEP-09',
        'إدارة المخاطر المؤسسية'           => 'DEP-09',
        'إدارة البلاغات'                   => 'DEP-10',
        'مركز البلاغات'                    => 'DEP-10',
        'إدارة التشغيل'                    => 'DEP-11',
        'التشغيل'                          => 'DEP-11',
        'إدارة الموقع'                     => 'DEP-12',
        'الموقع'                           => 'DEP-12',
        'إدارة القوى التشغيلية'            => 'DEP-13',
        'القوى التشغيلية'                  => 'DEP-13',
        'إدارة الصيانة'                    => 'DEP-14',
        'إدارة النقل والترحيل'             => 'DEP-15',
        'النقل والترحيل'                   => 'DEP-15',
        'إدارة المشتريات'                  => 'DEP-16',
        'إدارة المشتريات التشغيلية'        => 'DEP-16',
        'إدارة المخازن'                    => 'DEP-17',
        'المخازن'                          => 'DEP-17',
        'مساحة الرئيس التنفيذي'            => 'EX-CEO',
        'مساحة النواب'                     => 'EX-DVP',
        'المراجعة الداخلية المستقلة'       => 'IAF',
        'المراجع الداخلي المستقل'          => 'IAF',
        'مساحة عملي'                       => 'WS-MY',
        'مساحة العمل الشخصية'              => 'WS-MY',
    );
}

/**
 * وحدةٌ حيّةٌ ⇐ رمزٌ معياريّ. تعود `''` حين لا تُنسَب — **والعائدُ الفارغُ يُعَدُّ**.
 * ⛔ والبادئةُ الرقميّةُ تغلب الاسمَ لأنّها حاسمةٌ ولا تتأثّر بتغيُّرِ التسمية.
 */
function repair01_w16_unit_code($unit)
{
    $u = trim((string) $unit);
    if ($u === '') { return ''; }
    /* مُركَّبٌ بشرطةٍ أو قوسٍ — لا يُنسَب لواحدٍ فيُعلَن غيرَ منسوب */
    if (strpos($u, '/') !== false || strpos($u, '(') !== false || strpos($u, '—') === 0) { return ''; }
    if (preg_match('~^(\d{1,2})\s~u', $u, $m)) {
        $n = (int) $m[1];
        if ($n >= 1 && $n <= 17) { return sprintf('DEP-%02d', $n); }
    }
    if (preg_match('~^E1\s~u', $u)) { return 'EX-CEO'; }
    if (preg_match('~^E2\s~u', $u)) { return 'EX-DVP'; }
    if (preg_match('~^AS\s~u', $u)) { return 'IAF'; }
    if (preg_match('~^WS\s~u', $u)) { return 'WS-MY'; }
    $names = repair01_w16_unit_names();
    $bare  = preg_replace('~^(?:\d{1,2}|E1|E2|AS|WS)\s+~u', '', $u);
    if (isset($names[$bare])) { return $names[$bare]; }
    if (isset($names[$u])) { return $names[$u]; }
    return '';
}

/** استعلامٌ يعيد قيمةً واحدة. */
function repair01_w16_one($conn, $sql)
{
    $r = @$conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}

/** جداولُ دفاترِ الموجاتِ التي تحمل `requirement_id` ومِرساةً. */
function repair01_w16_scope_books()
{
    return array(3, 4, 5, 7, 8, 9, 11, 12, 13, 14, 15);
}

/**
 * الاتّحادُ الحيُّ لكلِّ متطلَّبٍ مع مِرساتِه — يُبنى من دفاترِ الموجاتِ لا من نسخة.
 * يعود: array(requirement_id => array('unit','code','anchor','wave'))
 */
function repair01_w16_requirement_anchors($conn)
{
    $out = array();
    $r = @$conn->query("SELECT requirement_id, unit, stage_no FROM repair01_requirements");
    while ($r && ($x = $r->fetch_assoc())) {
        $out[$x['requirement_id']] = array(
            'unit'   => $x['unit'],
            'code'   => repair01_w16_unit_code($x['unit']),
            'stage'  => (int) $x['stage_no'],
            'anchor' => '',
        );
    }
    foreach (repair01_w16_scope_books() as $n) {
        $q = @$conn->query("SELECT requirement_id, anchor_screen_id FROM repair01_w{$n}_scope");
        while ($q && ($x = $q->fetch_assoc())) {
            if (!isset($out[$x['requirement_id']])) { continue; }
            if (trim((string) $x['anchor_screen_id']) === '') { continue; }
            $out[$x['requirement_id']]['anchor'] = $x['anchor_screen_id'];
        }
    }
    return $out;
}

/**
 * التغطيةُ الآليّةُ المقيسةُ فعلًا: كم دفترَ رحلةٍ · كم محطّةً · كم عابرة.
 * ⛔ **ولا واحدٌ منها يحمل مُعرِّفَ متطلَّب** — فالتغطيةُ قائمةٌ **ولا تُنسَب**
 *   إلى نطاق، وهذا يُعلَن ولا يُكتب صفرًا في محورِ الاختبار.
 */
function repair01_w16_journey_totals($conn)
{
    $books = 0; $all = 0; $pass = 0;
    foreach (array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15) as $n) {
        $t = "repair01_w{$n}_journey";
        $c = @$conn->query("SHOW TABLES LIKE '$t'");
        if (!$c || $c->num_rows === 0) { continue; }
        $books++;
        $cols = array();
        $q = @$conn->query("SHOW COLUMNS FROM `$t`");
        while ($q && ($x = $q->fetch_assoc())) { $cols[$x['Field']] = true; }
        $n1 = repair01_w16_one($conn, "SELECT COUNT(*) FROM `$t`");
        $all += (int) $n1;
        if (isset($cols['passed'])) {
            $pass += (int) repair01_w16_one($conn, "SELECT COUNT(*) FROM `$t` WHERE passed = 1");
        } elseif (isset($cols['verdict'])) {
            $pass += (int) repair01_w16_one($conn, "SELECT COUNT(*) FROM `$t` WHERE verdict = 'PASS'");
        }
    }
    return array('books' => $books, 'stations' => $all, 'passed' => $pass);
}

/** المتطلَّباتُ التي ظهرت في دفترِ رحلةٍ — مقامُ محورِ الاختبار. */
function repair01_w16_journey_requirements($conn)
{
    $seen = array();
    foreach (array(3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15) as $n) {
        $t = "repair01_w{$n}_journey";
        $c = @$conn->query("SHOW COLUMNS FROM `$t` LIKE 'requirement_id'");
        if (!$c || $c->num_rows === 0) { continue; }
        $q = @$conn->query("SELECT DISTINCT requirement_id FROM `$t` WHERE requirement_id <> ''");
        while ($q && ($x = $q->fetch_row())) { $seen[$x[0]] = true; }
    }
    return $seen;
}

/** انتقالاتُ آلاتِ الحالةِ في كلِّ دفاترِ الموجات — بمقامِها الكامل. */
function repair01_w16_state_entities($conn)
{
    $ent = array();
    foreach (array(6, 7, 8, 10, 11, 12, 13, 14, 15) as $n) {
        $t = "repair01_w{$n}_states";
        $q = @$conn->query("SELECT DISTINCT entity FROM `$t`");
        while ($q && ($x = $q->fetch_row())) { $ent[$x[0]] = true; }
    }
    return $ent;
}

/** المحاورُ التسعةُ المنصوصةُ في البندِ ٦٤ — بتعريفِ بسطِها ومقامِها وحدِّ أداتِها. */
function repair01_w16_axis_defs()
{
    return array(
        array('STRUCTURAL', 1, 'بنيوي',
            'متطلبات النطاق التي لها مرساة في دفتر موجتها وسطح المرساة مسجل بحكم ملكية',
            'كل متطلبات النطاق في سجل المتطلبات',
            'تقيس وجود المرساة وحكم الملكية - ولا تقيس صحة ما بني داخل السطح'),
        array('NAVIGATION', 2, 'ملاحة',
            'اسطح النطاق الحية التي لها مسمى معياري وصنف ظهور مبني ومسار',
            'كل اسطح النطاق الحية في السجل المعياري',
            'تقيس صلاحية القيد للعرض - ولا تقيس ان البند ظاهر فعلا لدور بعينه'),
        array('FIELD', 3, 'حقول',
            'متطلبات النطاق التي لها حقل واحد على الاقل في سجل الحقول',
            'كل متطلبات النطاق في سجل المتطلبات',
            'تقيس وجود الحقول لا تطابقها مع ما يعرضه السطح'),
        array('WORKFLOW', 4, 'دورة عمل',
            'متطلبات النطاق التي مرساتها تحمل مرجع الة حالة',
            'كل متطلبات النطاق في سجل المتطلبات',
            'تقيس الربط المسجل بين السطح والالة - وانتقالات الموجات غير المنسوبة تعلن بعددها ولا تحسب'),
        array('INTEGRATION', 5, 'تكامل',
            'احداث النطاق التي حالة عقد اثرها مسجلة',
            'كل احداث النطاق في سجل الاحداث',
            'تقيس تسجيل عقد الاثر لا تنفيذه عند المستهلك'),
        array('DATA', 6, 'بيانات',
            'لا اداة قياس لجاهزية البيانات في هذه الحملة',
            'لا مقام - غير مقيس',
            'غير مقيس باعلان: جاهزية البيانات تحتاج فحص محتوى حي لم تبنه الحملة'),
        array('TEST', 7, 'اختبار',
            'متطلبات النطاق التي ظهرت في دفتر رحلة موجة',
            'كل متطلبات النطاق في سجل المتطلبات',
            'تقيس التغطية الالية وحدها - والقبول البشري محور مستقل'),
        array('HUMAN_UAT', 8, 'قبول بشري',
            'محطات رحلة الاثبات التي عبرها فاعل حقيقي بزمنه ودليله',
            'محطات الرحلة المسجلة لهذا النطاق',
            'لا يخضر بسكربت ولا ببذرة بيانات - والقاعدة تمنع الاعلان بلا فاعل'),
        array('ACCEPTANCE', 9, 'قبول نهائي',
            'متطلبات النطاق المستوفية للبنيوي والحقول والاختبار والقبول البشري معا',
            'كل متطلبات النطاق في سجل المتطلبات',
            'تم = متطلب مقبول بدليل - ولا تعني ان الشاشة موجودة'),
    );
}
