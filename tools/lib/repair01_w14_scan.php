<?php
/**
 * tools/lib/repair01_w14_scan.php — مكتبةُ قياسِ المرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ حكم**: كلُّ دالّةٍ هنا **تُعيد القياسَ من الحيِّ**
 *   ولا تقرأ ما خزّنَته أداةُ الاشتقاق. والبوّابةُ والفحصُ السلبيُّ والرحلةُ
 *   ينادونها جميعًا — فمِقياسٌ واحدٌ لا ثلاثة.
 *
 * ◆ **وثلاثةُ نطاقاتٍ لا محرّكٌ واحد** (‏قيدُ المالك §١): `repair01_w14_domains`
 *   يُعلن لكلِّ جدولٍ **نطاقًا واحدًا** بمفتاحٍ فريد، و`repair01_w14_cross_domain_writes`
 *   يقرأ **شيفرةَ الخدماتِ نفسِها** فيرصد كتابةَ نطاقٍ في جدولِ نطاقٍ آخر.
 *   **والعلاقةُ مرجعٌ لا مشاركة** — والمرجعُ قراءةٌ، والقراءةُ لا تُرصَد.
 *
 * ◆ **والمقامُ ثابتٌ لا يخلو** (‏درسُ `W12-27`): النطاقاتُ والقواعدُ والرموزُ
 *   والكياناتُ **تُعلَن هنا** وتُقاس على الحيِّ معًا — فبوّابةٌ تقيس صفرًا من
 *   صفرٍ تمرُّ على تطابقِ لا شيء، وهذه لا تفعل.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة**: أسماءُ الجداولِ والأعمدةِ والقيودِ
 *   والأصنافِ — لا نصوصُ الرسائلِ العربيّة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════════════════════════════
   ① أدواتٌ صغيرة
   ══════════════════════════════════════════════════════════════════════════ */

function repair01_w14_one(mysqli $c, $sql)
{
    $r = @$c->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}

function repair01_w14_table_exists(mysqli $c, $t)
{
    $r = @$c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w14_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w14_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w14_check_exists(mysqli $c, $name)
{
    /* **ومقامُ القيودِ يُقرأ مرّةً واحدةً** — و`information_schema` استعلامُها
       باهظٌ فيُخزَّن في الجلسةِ الواحدةِ ولا يُعاد لكلِّ قيدٍ من خمسةٍ وخمسين. */
    static $all = null;
    if ($all === null) {
        $all = array();
        $r = @$c->query("SELECT CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA = DATABASE()");
        while ($r && $x = $r->fetch_row()) { $all[$x[0]] = true; }
    }
    return isset($all[$name]);
}

/** الحبّةُ `Legal Entity` — **عدمُ قبولِ العدمِ في المخطَّطِ** لا وجودُ العمود */
function repair01_w14_entity_scoped(mysqli $c, $t)
{
    if (!repair01_w14_table_exists($c, $t)) { return false; }
    $n = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
               AND COLUMN_NAME = 'company_id' AND IS_NULLABLE = 'NO'");
    return $n > 0;
}

/**
 * **أين يقع الحارسُ في عُدّةٍ مُضمَّنة** — داخلَ دالّةٍ أم خارجَ كلِّ دالّة.
 * ◆ الخارجُ عن كلِّ دالّةٍ **يُنفَّذ بمجرَّدِ التضمين** فيحرس من ضمّنه.
 * ◆ والداخلُ في دالّةٍ لا يحرس إلّا من **ينادي تلك الدالّةَ باسمِها**.
 * ◆ والقياسُ **بمطابقةِ الأقواسِ** لا بنمطٍ نصّيّ — فقوسٌ في نصٍّ يخدع النمطَ
 *   ولا يخدع العدّاد.
 * @return array{top_level:bool, guarding_fns:string[]}
 */
function repair01_w14_guard_bodies($src)
{
    /* ⚠ **والشرحُ ليس شيفرة**: `* $perms = check_page_permissions(...)` في
         كتلةِ توثيقٍ يُقرأ «حارسٌ يُنفَّذ بالتضمين» — فيصير المساعدُ المركزيُّ
         حارسًا لكلِّ من ضمّنه لأنَّ **مثالًا في تعليقٍ** ذكر اسمَ الدالّة.
         فالتعليقُ يُبيَّض **بمسافاتٍ مساويةِ الطولِ** كي تبقى المواضعُ صادقةً. */
    $blank = function ($m) { return str_repeat(' ', strlen($m[0])); };
    $src = preg_replace_callback('~/\*.*?\*/~s', $blank, $src);
    $src = preg_replace_callback('~//[^\n]*~', $blank, (string) $src);
    $src = preg_replace_callback('~(?<![\'"$])\#[^\n]*~', $blank, (string) $src);
    $src = (string) $src;
    $needle = 'check_page_permissions';
    $fns = array(); $spans = array();
    if (preg_match_all('~\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(~', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $k => $hit) {
            $name = $m[1][$k][0];
            $open = strpos($src, '{', $hit[1] + strlen($hit[0]) - 1);
            if ($open === false) { continue; }
            $depth = 0; $end = null; $len = strlen($src);
            for ($i = $open; $i < $len; $i++) {
                if ($src[$i] === '{') { $depth++; }
                elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
            }
            if ($end === null) { continue; }
            $body = substr($src, $open, $end - $open + 1);
            $spans[] = array($open, $end);
            if (strpos($body, $needle) !== false) { $fns[] = $name; }
        }
    }
    /* الحارسُ خارجَ كلِّ جسمِ دالّةٍ — يُنفَّذ بالتضمينِ وحدَه */
    $top = false; $off = 0;
    while (($p = strpos($src, $needle, $off)) !== false) {
        $off = $p + 1;
        /* ⚠ **وتعريفُ الدالّةِ ليس نداءً لها**: `function check_page_permissions(`
             يقع خارجَ كلِّ جسمِ دالّةٍ، فيُقرأ «حارسٌ يُنفَّذ بالتضمين» وهو
             **تعريفٌ لا تنفيذ** — فيصير المساعدُ المركزيُّ نفسُه حارسًا لكلِّ من
             ضمّنه ولو لم ينادِه. */
        $pre = substr($src, max(0, $p - 24), min(24, $p));
        if (preg_match('~\bfunction\s+$~', $pre)) { continue; }
        $inside = false;
        foreach ($spans as $sp) { if ($p >= $sp[0] && $p <= $sp[1]) { $inside = true; break; } }
        if (!$inside) { $top = true; break; }
    }
    return array('top_level' => $top, 'guarding_fns' => array_values(array_unique($fns)));
}

/**
 * حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ.
 * ⚠ **والكاشفُ يتبع مُضمَّنَه مستوًى واحدًا** (‏درسُ W06: «العطبُ في المُنقّي»):
 *   عشرون سطحًا حيًّا تحرسها `check_page_permissions` **داخلَ عُدّةٍ مُضمَّنة**
 *   (`Risk/_risk_common.php` · `includes/u13_screen_kit.php`)، وكاشفٌ يقرأ ملفَّ
 *   الشاشةِ وحدَه يقول «لا حارس» وهي محروسة — فيُقرأ العطبُ في المُنقَّى وهو في
 *   الكاشف. والاتّباعُ **عامٌّ لا قائمةُ أسماء**: كلُّ `require`/`include` بمسارٍ
 *   محلّيٍّ يُفتَح ويُفحَص، ⛔ **ومستوًى واحدٌ فقط** فلا يدور الكاشفُ في حلقة.
 */
function repair01_w14_guard_of($ROOT, $route, $depth = 0)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);
    /* ⚠ **والحارسُ المتأخّرُ ليس حارسًا**: `insidebar.php` ينادي فحصَ الصلاحيةِ
         عند بنائِه قائمتَه، لكنّه يُضمَّن **بعد** أن بدأ الجسمُ يُصيَّر — فمنعُه
         يقع بعد أن رأى غيرُ المخوَّلِ الصفحةَ. فالمقامُ عند العمقِ صفرٍ **ما قبل
         أوّلِ إخراجٍ** (`inheader.php`)، وما بعده جِوارٌ لا حراسة. */
    if ($depth === 0) {
        $cut = strpos($src, 'inheader.php');
        if ($cut !== false) { $src = substr($src, 0, $cut); }
    }
    if (strpos($src, 'check_page_permissions') !== false
        || strpos($src, 'enforce_current_page_view_permission') !== false) {
        return array('kind' => 'SELF_EARLY',
                     'evidence' => $depth === 0 ? 'حارس صلاحية في الملف نفسه'
                                                : 'حارس صلاحية في عدة مضمنة يستدعيها الملف');
    }
    if ($depth === 0) {
        /* ⚠ **والتضمينُ ليس نداءً** — فملفٌّ يُضمِّن عُدّةً فيها حارسٌ **ولا
             ينادي منها شيئًا** يُقرأ محروسًا وهو مكشوف. فالشرطُ أدقُّ من
             «المُضمَّنُ فيه حارس»: إمّا أن يُنفِّذَ المُضمَّنُ الحارسَ **بمجرَّدِ
             تضمينِه** (‏حارسٌ خارجَ كلِّ دالّة)، وإمّا أن **ينادي الملفُّ دالّةً
             من دوالِّه** هي التي تحرس. وما دون ذلك ليس حراسةً بل جِوار. */
        if (preg_match_all('~(?:require|include)(?:_once)?\s*(?:\(\s*)?[^;]*?[\'"]([^\'";]+\.php)[\'"]~i',
                           $src, $m)) {
            $dir = dirname($path);
            foreach (array_unique($m[1]) as $inc) {
                $inc = ltrim(str_replace('\\', '/', $inc), '/');
                foreach (array($dir . '/' . $inc, $ROOT . '/' . $inc) as $cand) {
                    $real = @realpath($cand);
                    if ($real === false || !is_file($real)) { continue; }
                    $isrc = (string) file_get_contents($real);
                    if (strpos($isrc, 'check_page_permissions') === false
                        && strpos($isrc, 'enforce_current_page_view_permission') === false) { break; }
                    $g = repair01_w14_guard_bodies($isrc);
                    if ($g['top_level']) {
                        return array('kind' => 'SELF_EARLY',
                                     'evidence' => 'عدة مضمنة تنفذ الحارس بمجرد تضمينها');
                    }
                    foreach ($g['guarding_fns'] as $fn) {
                        if (preg_match('~(?<![A-Za-z0-9_$>])' . preg_quote($fn, '~') . '\s*\(~', $src)) {
                            return array('kind' => 'SELF_EARLY',
                                         'evidence' => 'الملف ينادي دالة حارسة في عدة مضمنة');
                        }
                    }
                    break;
                }
            }
        }
    }
    if (strpos($src, 'permissions_helper.php') !== false || strpos($src, 'permit_gate.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'حارس القشرة عبر المساعد المركزي');
    }
    if (strpos($src, 'session_bootstrap.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'اقلاع الجلسة المركزي');
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس في الملف ولا في مضمناته');
}

/* ══════════════════════════════════════════════════════════════════════════
   ② النطاقاتُ الثلاثةُ — إعلانٌ بمقامٍ ثابتٍ يُقاس على الحيّ
   ══════════════════════════════════════════════════════════════════════════
   **كلُّ جدولٍ نطاقٌ واحدٌ لا اثنان.** و`SOURCE` نطاقٌ رابعٌ **ليس رقابيًّا**:
   الانحرافُ وقاعدةُ تصنيفِه يملكهما مالكُ الواقعةِ التشغيليُّ لا أحدُ الثلاثة.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w14_domains()
{
    $GOV = 'app/Services/Governance/GovernanceDomainService.php';
    $RSK = 'app/Services/Risk/RiskDomainService.php';
    $IAF = 'app/Services/Audit/AuditDomainService.php';
    $SRC = 'app/Services/Control/DeviationClassifier.php';
    return array(
        /* ── مصدرٌ تشغيليٌّ: الانحرافُ عند مالكِه ────────────────────────── */
        'ctl_deviation' => array('SOURCE', 'الادارة التشغيلية المالكة للواقعة', 'Deviation_ID', 'SOURCE',
            'الانحراف التشغيلي وواقعته وزمنه ومالك عمليته',
            'لا يملكه نطاق رقابة - والحوكمة والمخاطر تقرآنه بمرجعه',
            'DEP-08 و DEP-09 قراءة بمرجع', $SRC,
            'العطل يبقى Source Event عند التشغيل والصيانة - قرار المالك الثاني'),
        'ctl_classification_rule' => array('SOURCE', 'قاعدة التمييز الثلاثي', 'Rule_Code', 'SOURCE',
            'شرط صيرورة الانحراف تعرضا وشرط صيرورته خرقا وشرط بقائه انحرافا',
            'لا تملكها جهة تستفيد من نتيجتها وحدها',
            'DEP-08 و DEP-09 قراءة بمرجع', $SRC,
            'التصنيف بقاعدة مكتوبة لا باجتهاد مصنف'),
        /* ── إدارةُ المخاطر — الخطُّ الثاني المستقلّ ───────────────────── */
        'risk_register' => array('DEP-09', 'ادارة المخاطر', 'Risk_ID', 'SECOND',
            'تحديد الخطر وتصنيفه وتقييمه والكامن والمتبقي والمعالجة والقبول والتصعيد',
            'لا يملك السياسات ولا خطة المراجعة ولا نتائجها', 'DEP-08 و IAF قراءة', $RSK,
            'مصدر حقيقة المخاطر سجلها وحده - قرار المالك السادس G6'),
        'rsk_taxonomy' => array('DEP-09', 'ادارة المخاطر', 'Taxonomy_Node', 'SECOND',
            'الشجرة الحاكمة للعائلات الاربع', 'لا عائلة خامسة ولا نص حر', 'قراءة', $RSK,
            'العائلات الاربع معتمدة - قيد المالك الثالث'),
        'rsk_trigger' => array('DEP-09', 'ادارة المخاطر', 'Trigger_ID', 'SECOND',
            'محفز الخطر من واقعة تشغيلية بقاعدته وعتبته',
            'لا ينشئ سجل خطر لكل عطل', 'DEP-08 قراءة', $RSK,
            'العطل ينشئ محفزا لا خطرا - قرار المالك الثاني'),
        'rsk_event' => array('DEP-09', 'ادارة المخاطر', 'Risk_Event_ID', 'SECOND',
            'حدث الخطر والخسارة بمرجع مصدره', 'لا ينسخ حمولة المصدر', 'قراءة', $RSK,
            'يقرا احداث المصدر بمرجعها ولا ينسخها - قيد المالك الثالث'),
        'rsk_closure' => array('DEP-09', 'ادارة المخاطر', 'Closure_ID', 'SECOND',
            'اغلاق الخطر باثباته', 'لا يغلق بكتابة نية', 'IAF قراءة', $RSK,
            'لا يغلق الخطر الا باثبات'),
        'risk_assessments' => array('DEP-09', 'ادارة المخاطر', 'Assessment_ID', 'SECOND',
            'الاحتمال والاثر والكامن والمتبقي', 'لا تملكه الحوكمة', 'قراءة', $RSK, 'التقييم عند المخاطر'),
        'risk_controls' => array('DEP-09', 'ادارة المخاطر', 'Control_Link_ID', 'SECOND',
            'ربط الضابط بالخطر ومرجعه', 'لا يملك تشغيل الضابط', 'DEP-08 قراءة', $RSK,
            'الضابط قد يكون قاعدة منع عند الحوكمة - والربط عند المخاطر'),
        'risk_treatments' => array('DEP-09', 'ادارة المخاطر', 'Treatment_ID', 'SECOND',
            'المعالجة باربعة مسارات بمالك ومهلة', 'لا يملك تنفيذ الاجراء عند الادارة', 'قراءة', $RSK, 'خطط المعالجة'),
        'risk_kris' => array('DEP-09', 'ادارة المخاطر', 'KRI_ID', 'SECOND',
            'تعريف المؤشر وعتبتيه وقراءته', 'لا يملك مصدر القياس', 'قراءة', $RSK, 'المؤشرات عند المخاطر'),
        'risk_acceptances' => array('DEP-09', 'ادارة المخاطر', 'Acceptance_ID', 'SECOND',
            'قبول الخطر بسلطة مستواه', 'لا يقبل بلا شهية معتمدة', 'قراءة', $RSK, 'القبول ضمن الشهية'),
        'risk_escalations' => array('DEP-09', 'ادارة المخاطر', 'Escalation_ID', 'SECOND',
            'تصعيد الاختراق الحرج وخروج الشهية', 'لا يملك قرار الجهة المصعد اليها', 'قراءة', $RSK, 'التصعيد بمساره'),
        'risk_reviews' => array('DEP-09', 'ادارة المخاطر', 'Review_ID', 'SECOND',
            'المراجعة الدورية واعادة التقييم', 'ليست مراجعة داخلية ولا تنتج تاكيدا', 'قراءة', $RSK,
            'مراجعة دورية للمخاطر لا Independent Assurance'),
        /* ── الحوكمةُ والالتزام — الخطُّ الثاني ───────────────────────── */
        'gov_breach' => array('DEP-08', 'الحوكمة والالتزام', 'Governance_Case_ID', 'SECOND',
            'خرق الضابط او السياسة او الالتزام بحالته',
            'لا يفتح لانحراف تشغيلي صرف ولا يملك سجل المخاطر', 'DEP-09 و IAF قراءة',
            $GOV, 'حالة الحوكمة باساس من الثمانية - قرار المالك الثاني'),
        'gov_obligation' => array('DEP-08', 'الحوكمة والالتزام', 'Compliance_Obligation_ID', 'SECOND',
            'الالتزام التنظيمي بجهته ودوريته ومالكه', 'لا يملك تنفيذ الالتزام عند الادارة', 'قراءة',
            $GOV, 'اساس تقويم الامتثال'),
        'gov_investigation' => array('DEP-08', 'الحوكمة والالتزام', 'Integrity_Investigation_ID', 'SECOND',
            'تحقيق النزاهة والامتثال بتكليفه ونطاقه',
            'لا يملك التاديبي ولا التقصي التشغيلي ولا يعطى للمراجعة طابورا يوميا',
            'DEP-07 تستقبل الاثر', $GOV, 'ثلاثة انواع بثلاثة ملاك - DEC-OPEN-16'),
        'gov_policy' => array('DEP-08', 'الحوكمة والالتزام', 'Policy_ID', 'SECOND',
            'السياسة واصدارها ونفاذها', 'لا تملكها المراجعة الداخلية', 'قراءة', $GOV,
            'الحوكمة تملك السياسات - قرار المالك السادس'),
        'gov_compliance_due' => array('DEP-08', 'الحوكمة والالتزام', 'Due_ID', 'SECOND',
            'استحقاق امتثال مشتق بمرجع اشتقاقه', 'لا ادخال يدوي بلا التزام', 'قراءة', $GOV, 'تقويم مشتق'),
        'gov_filing' => array('DEP-08', 'الحوكمة والالتزام', 'Filing_ID', 'SECOND',
            'التقديم النظامي بموعده وايصاله', 'لا يملك اعداد المحتوى عند الادارة', 'قراءة', $GOV, 'التقديمات النظامية'),
        'gov_conflict_disclosure' => array('DEP-08', 'الحوكمة والالتزام', 'Disclosure_ID', 'SECOND',
            'الافصاح عن تضارب المصالح وقراره', 'لا يقرر صاحب الافصاح في افصاحه', 'قراءة', $GOV,
            'الافصاح واجب والقرار للحوكمة'),
        'gov_related_party' => array('DEP-08', 'الحوكمة والالتزام', 'Related_Party_ID', 'SECOND',
            'الطرف ذو العلاقة وتعامله موسوما بين الكيانات', 'لا يلغي الدورة التعاقدية للشركة الشقيقة',
            'DEP-05 قراءة', $GOV, 'وسم Intercompany منذ الانشاء - قرار المالك الاول'),
        'gov_gift_disclosure' => array('DEP-08', 'الحوكمة والالتزام', 'Gift_ID', 'SECOND',
            'الهدايا والضيافة فوق الحد المضبوط', 'لا يخترع الحد', 'قراءة', $GOV, 'الحد من السجل'),
        'gov_conduct_ack' => array('DEP-08', 'الحوكمة والالتزام', 'Ack_ID', 'SECOND',
            'اقرار مدونة السلوك عند التعيين وعند كل اصدار', 'لا يملك التعيين عند الموارد', 'DEP-07 قراءة',
            $GOV, 'الناقص يعلم'),
        'gov_sod_conflict' => array('DEP-08', 'الحوكمة والالتزام', 'SoD_Conflict_ID', 'SECOND',
            'حوكمة فصل الواجبات وكشف التعارض', 'لا يملك تنفيذ العملية نفسها', 'قراءة', $GOV,
            'التعارض يعرف مرة ويكشف دوما'),
        'gov_integrity_report' => array('DEP-08', 'الحوكمة والالتزام', 'Integrity_Report_ID', 'SECOND',
            'القناة المحمية وهوية محجوبة', 'لا يكشف الهوية الا لمستوى مخول', 'قراءة', $GOV,
            'سرية مشددة ولا انتقام'),
        'gov_corrective_action' => array('DEP-08', 'الحوكمة والالتزام', 'Action_ID', 'SECOND',
            'متابعة الاجراء التصحيحي بمالك ومهلة ودليل', 'لا يملك تنفيذ الاجراء عند الادارة', 'IAF قراءة',
            $GOV, 'متابعة الاجراء التصحيحي عند الحوكمة'),
        'gov_audit_followup' => array('DEP-08', 'الحوكمة والالتزام', 'Followup_ID', 'SECOND',
            'متابعة خطة الادارة على نتيجة المراجعة',
            'لا يملك النتيجة ولا تقديرها ولا اغلاقها', 'IAF مصدر النتيجة', $GOV,
            'الحوكمة تتابع ولا تعدل - قيد المالك الاول'),
        'gov_committee' => array('DEP-08', 'الحوكمة والالتزام', 'Committee_ID', 'SECOND',
            'اللجان النافذة بتشكيلها وصلاحياتها', 'لا يملك قرار اللجنة', 'قراءة', $GOV, 'حوكمة الاجتماعات'),
        'gov_request_type' => array('DEP-08', 'الحوكمة والالتزام', 'Request_Type_ID', 'SECOND',
            'حوكمة سجل انواع الطلبات وقواعد انشائه وتسميته واصداره وتقاعده',
            'لا يملك توجيه الطلبات اليومية ولا تعريف طلب ادارة', 'كل المجالات قراءة', $GOV,
            'قدرة منصية مركزية - DEC-OPEN-17 وقرار المالك الثالث'),
        /* ── المراجعةُ الداخليّةُ — الخطُّ الثالثُ المستقلّ ─────────────── */
        'iaf_engagements' => array('IAF', 'المراجعة الداخلية المستقلة', 'Audit_Engagement_ID', 'THIRD',
            'المهمة ونطاقها وفريقها وخطها الزمني',
            'لا يملك السياسات ولا الامتثال اليومي ولا AAM ولا تشغيل الضوابط',
            'DEP-08 قراءة لا تعديل', $IAF, 'الحوكمة لا تعطي المراجع نطاقه'),
        'iaf_findings' => array('IAF', 'المراجعة الداخلية المستقلة', 'Audit_Finding_ID', 'THIRD',
            'الملاحظة باركانها الخمسة وتقديرها وتوصيتها ونتيجتها واغلاقها',
            'لا تغيرها الحوكمة ولا تغلقها نيابة عنها', 'DEP-08 متابعة بمرجع', $IAF,
            'استقلال النتيجة - قيد المالك الاول'),
        'iaf_program' => array('IAF', 'المراجعة الداخلية المستقلة', 'Program_ID', 'THIRD',
            'خطوة البرنامج وهدفها واسلوبها وعينتها', 'لا تحدد الحوكمة نطاقها', 'قراءة', $IAF,
            'البرنامج يربط الهدف بالاختبار'),
        'iaf_evidence_request' => array('IAF', 'المراجعة الداخلية المستقلة', 'Evidence_Request_ID', 'THIRD',
            'طلب الدليل بمهلته وتاخره', 'لا يعطي وصولا كاتبا للمعاملات', 'الخاضع للمراجعة يزود', $IAF,
            'Assurance Read Access حسب نطاق المهمة'),
        'iaf_sample' => array('IAF', 'المراجعة الداخلية المستقلة', 'Sample_ID', 'THIRD',
            'مفردة العينة ونتيجتها', 'لا يكتب في سجل المصدر', 'قراءة', $IAF, 'كل مفردة بنتيجتها'),
        'iaf_workpapers' => array('IAF', 'المراجعة الداخلية المستقلة', 'Workpaper_ID', 'THIRD',
            'ورقة العمل تربط الخطوة بالدليل بالاستنتاج', 'لا تعتمد الا بمراجعة رئيس الفريق', 'قراءة', $IAF,
            'اوراق العمل عند المراجعة'),
        'iaf_universe' => array('IAF', 'المراجعة الداخلية المستقلة', 'Auditable_Unit_ID', 'THIRD',
            'الكون الرقابي وترتيبه بالمخاطر', 'لا تحدده الحوكمة', 'قراءة', $IAF, 'اساس الخطة السنوية'),
        'iaf_plan' => array('IAF', 'المراجعة الداخلية المستقلة', 'Audit_Plan_ID', 'THIRD',
            'الخطة السنوية المبنية على المخاطر', 'لا تعتمدها الادارة التنفيذية', 'قراءة', $IAF,
            'تعتمد من المالك او اللجنة'),
        'iaf_independence' => array('IAF', 'المراجعة الداخلية المستقلة', 'Independence_ID', 'THIRD',
            'اقرار الاستقلال شرط كل تكليف', 'لا يراجع احد عملا شارك فيه', 'قراءة', $IAF, 'الاستقلال بالميثاق'),
        'iaf_charter' => array('IAF', 'المراجعة الداخلية المستقلة', 'Charter_ID', 'THIRD',
            'الميثاق يثبت الاستقلال وخط الرفع', 'لا تعدله الادارة التنفيذية', 'قراءة', $IAF, 'خط الرفع للمالك او اللجنة'),
        'iaf_access_log' => array('IAF', 'المراجعة الداخلية المستقلة', 'Access_Event_ID', 'THIRD',
            'واقعة الاطلاع الحساس مضافة فقط', 'لا تحذف ولا تعدل', 'DEP-08 قراءة', $IAF,
            'اي وصول حساس يسجل - قرار المالك السادس'),
        'iaf_quality_reviews' => array('IAF', 'المراجعة الداخلية المستقلة', 'Quality_Review_ID', 'THIRD',
            'تقييم جودة الوظيفة داخليا وخارجيا', 'لا تجريه الجهة الخاضعة للمراجعة', 'قراءة', $IAF,
            'الوظيفة تقيم كما تقيم غيرها'),
        'iaf_function_risk' => array('IAF', 'المراجعة الداخلية المستقلة', 'Function_Risk_ID', 'THIRD',
            'مخاطر وظيفة المراجعة نفسها', 'ليست جزءا من سجل المخاطر المؤسسي', 'قراءة', $IAF,
            'ترفع لخط الرفع بالميثاق لا لسجل DEP-09'),
    );
}

/** رمزُ النطاقِ الذي يملك جدولًا — وفارغٌ إن لم يُعلَن */
function repair01_w14_domain_of($table)
{
    $d = repair01_w14_domains();
    return isset($d[$table]) ? $d[$table][0] : '';
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ مِرساةُ كلِّ متطلَّبٍ — الطريقُ والمِسبارُ وموضعُه من دورةِ العمل
   ══════════════════════════════════════════════════════════════════════════
   `step` **موضعُ السطحِ من دورةِ العمل** — لا الأبجديّةُ ولا تاريخُ الإنشاء.
   دورةُ الحوكمة 0..30 · دورةُ المخاطر 40..52 · دورةُ المراجعة 60..76.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w14_anchors()
{
    return array(
        /* ══ الحوكمةُ والالتزام · اللوحةُ خارجَ الدورة ══════════════════ */
        'GOV-01' => array('route' => 'Governance/gov_board.php', 'probe' => 'gov_breach',
            'kind' => 'TABLE', 'step' => 0, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'لوحة الحوكمة مشتقة من المستندات والتفويضات والاستثناءات وقواعد المنع ولا ادخال'),
        /* ══ الكيانُ والتفويض ══════════════════════════════════════════ */
        'GOV-02' => array('route' => 'Governance/entities_registry.php', 'probe' => 'legal_entities',
            'kind' => 'TABLE', 'step' => 1, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الكيان القانوني اول ما يعرف - لا عملية بلا عقد بين كيانين'),
        'GOV-03' => array('route' => 'Governance/policies.php', 'probe' => 'gov_policy',
            'kind' => 'TABLE', 'step' => 2, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل قاعدة منع وكل مسار اعتماد يستند لسياسة نافذة بإصدارها'),
        'GOV-04' => array('route' => 'Governance/obligations.php', 'probe' => 'gov_obligation',
            'kind' => 'TABLE', 'step' => 3, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل التزام تنظيمي مسجل بجهته ودوريته ومالكه'),
        'GOV-05' => array('route' => 'Governance/compliance_calendar.php', 'probe' => 'gov_compliance_due',
            'kind' => 'TABLE', 'step' => 4, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'التقويم مشتق من الالتزامات والتراخيص والاقرارات ولا ادخال حر'),
        'GOV-06' => array('route' => 'Governance/signing_authority.php', 'probe' => 'signing_authorities',
            'kind' => 'TABLE', 'step' => 5, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'لا توقيع نافذا بلا تفويض ساري بنطاقه وحدوده'),
        /* ══ المستنداتُ والالتزام ══════════════════════════════════════ */
        'GOV-07' => array('route' => 'Governance/licenses_guarantees.php', 'probe' => 'entity_licenses',
            'kind' => 'TABLE', 'step' => 6, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل مستند بصلاحيته وتنبيه اقترابه والحرج المنتهي يفعل قاعدة منع'),
        'GOV-08' => array('route' => 'Governance/regulatory_filings.php', 'probe' => 'gov_filing',
            'kind' => 'TABLE', 'step' => 7, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل تقديم نظامي بموعده وايصاله والمتاخر يعلم ويصعد'),
        /* ══ الأدوارُ والصلاحيات ═══════════════════════════════════════ */
        'GOV-09' => array('route' => 'Governance/auth_profiles.php', 'probe' => 'gov_role_profiles',
            'kind' => 'TABLE', 'step' => 8, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الحوكمة تملك سياسة الادوار بالدور لا بالفرد'),
        'GOV-19' => array('route' => 'Governance/sensitive_fields.php', 'probe' => 'cmp03_local_store',
            'kind' => 'SERVICE', 'step' => 9, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الامن على مستوى الحقل لا الشاشة وحدها'),
        'GOV-28' => array('route' => 'Governance/perm_explain.php', 'probe' => 'perm_explain_live',
            'kind' => 'SERVICE', 'step' => 10, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'لا صلاحية غامضة - لكل سماح او منع سبب يعرض'),
        'GOV-14' => array('route' => 'Governance/guards.php', 'probe' => 'cmp03_local_store',
            'kind' => 'SERVICE', 'step' => 11, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'القاعدة تصنف منعا مطلقا او باستثناء او تنبيها وترصد قبل الانفاذ'),
        'GOV-17' => array('route' => 'Governance/break_glass.php', 'probe' => 'cmp03_local_store',
            'kind' => 'SERVICE', 'step' => 12, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'صلاحية استثنائية بمدة قصيرة ومبرر معلن ومراجعة لاحقة الزامية'),
        'GOV-16' => array('route' => 'Governance/sod_conflicts.php', 'probe' => 'gov_sod_conflict',
            'kind' => 'TABLE', 'step' => 13, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'التعارض يعرف مرة ويكشف دوما ولا يجمع فاعل واحد طرفي عملية'),
        'GOV-10' => array('route' => 'Governance/conflict_disclosures.php', 'probe' => 'gov_conflict_disclosure',
            'kind' => 'TABLE', 'step' => 14, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الافصاح واجب والقرار للحوكمة ولا مشاركة في قرار محل التضارب'),
        'GOV-11' => array('route' => 'Governance/related_parties.php', 'probe' => 'gov_related_party',
            'kind' => 'TABLE', 'step' => 15, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل تعامل مع طرف ذي علاقة يمر بافصاح الزامي وموسوم بين الكيانات'),
        'GOV-12' => array('route' => 'Governance/gifts_hospitality.php', 'probe' => 'gov_gift_disclosure',
            'kind' => 'TABLE', 'step' => 16, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الافصاح فوق الحد المضبوط الزامي والقبول او الرد بقرار'),
        'GOV-13' => array('route' => 'Governance/conduct_acknowledgements.php', 'probe' => 'gov_conduct_ack',
            'kind' => 'TABLE', 'step' => 17, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل موظف يقر بمدونة السلوك عند التعيين وعند كل اصدار جديد'),
        /* ══ الاستثناءُ والرقابة ═══════════════════════════════════════ */
        'GOV-18' => array('route' => 'Governance/approval_ladders.php', 'probe' => 'gov_ladders',
            'kind' => 'TABLE', 'step' => 18, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'السلم يعرف بمستوياته وشروط انتقاله ويقرا من محرك الصلاحيات'),
        'GOV-21' => array('route' => 'Governance/exceptions.php', 'probe' => 'cmp03_local_store',
            'kind' => 'SERVICE', 'step' => 19, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'لا استثناء دائما - كل استثناء بمدة وخطورة ومعتمد بحسبها'),
        'GOV-15' => array('route' => 'Governance/guard_denials.php', 'probe' => 'guard_denials',
            'kind' => 'TABLE', 'step' => 20, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل محاولة مرفوضة تترك اثرا - والنقر الممنوع ليس تحقيقا'),
        'GOV-22' => array('route' => 'Governance/integrity_reports.php', 'probe' => 'gov_integrity_report',
            'kind' => 'TABLE', 'step' => 21, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'قناة محمية بسرية مشددة وهوية المبلغ محجوبة الا لمستوى مخول'),
        'GOV-23' => array('route' => 'Governance/investigations.php', 'probe' => 'gov_investigation',
            'kind' => 'TABLE', 'step' => 22, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'التحقيق بتكليف وصلاحيات ونطاق وثلاثة انواع بثلاثة ملاك'),
        'GOV-24' => array('route' => 'Governance/breaches.php', 'probe' => 'gov_breach',
            'kind' => 'TABLE', 'step' => 23, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل اخلال بقاعدة او التزام يسجل باثره ومعالجته ولا يغلق بلا اجراء'),
        'GOV-25' => array('route' => 'Governance/corrective_actions.php', 'probe' => 'gov_corrective_action',
            'kind' => 'TABLE', 'step' => 24, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'كل اجراء بمالك ومهلة ودليل اغلاق والمتاخر يتصدر ويصعد'),
        'GOV-26' => array('route' => 'Governance/audit_followup.php', 'probe' => 'gov_audit_followup',
            'kind' => 'TABLE', 'step' => 25, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الحوكمة تتابع خطة الادارة ولا تملك نتيجة المراجعة ولا تغلقها'),
        /* ══ الأنظمةُ المرجعيّة ════════════════════════════════════════ */
        'GOV-27' => array('route' => 'Governance/doc_types.php', 'probe' => 'cmp03_local_store',
            'kind' => 'SERVICE', 'step' => 26, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الترقيم من النظام وحده بنمطه المسجل ولا رقم يدوي'),
        'GOV-29' => array('route' => 'Governance/activation_patterns.php', 'probe' => 'EntityGovernanceService',
            'kind' => 'SERVICE', 'step' => 27, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'الحوكمة تملك سياسة انماط التفعيل وتعتمد الترقية بقرارها'),
        'GOV-20' => array('route' => 'Governance/bus_board.php', 'probe' => 'ems_business_events',
            'kind' => 'TABLE', 'step' => 28, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'لا تكامل صامت - المعلق والفاشل والمعاد والميت والمعوض تعرض'),
        'GOV-30' => array('route' => 'Governance/committees.php', 'probe' => 'gov_committee',
            'kind' => 'TABLE', 'step' => 29, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'اللجان النافذة بتشكيلها وصلاحياتها ودورية انعقادها'),
        'GOV-32' => array('route' => 'Governance/dr_restore.php', 'probe' => 'RestoreDrillService',
            'kind' => 'SERVICE', 'step' => 30, 'domain' => 'DEP-08', 'line' => 'SECOND',
            'why' => 'النسخة لا تصدق حتى تستعاد - التمرين الدوري بمحضر موقع'),
        /* ══ إدارةُ المخاطر · اللوحةُ خارجَ الدورة ══════════════════════ */
        'RSK-01' => array('route' => 'Risk/risk_board.php', 'probe' => 'risk_register',
            'kind' => 'TABLE', 'step' => 40, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'لوحة المخاطر مشتقة من السجل والتقييمات والمؤشرات ولا ادخال'),
        'RSK-02' => array('route' => 'Risk/risk_taxonomy.php', 'probe' => 'rsk_taxonomy',
            'kind' => 'TABLE', 'step' => 41, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'الشجرة الحاكمة للعائلات الاربع وكل خطر يسند لعقدة واحدة'),
        'RSK-03' => array('route' => 'Risk/risk_register.php', 'probe' => 'risk_register',
            'kind' => 'TABLE', 'step' => 42, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'الشاشة الام - لا سجلات موازية والخطر يحدد من حدثه الاصلي'),
        'RSK-04' => array('route' => 'Risk/risk_events.php', 'probe' => 'rsk_event',
            'kind' => 'TABLE', 'step' => 43, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'الحدث يقرا مصدره بمرجعه ولا ينسخه - والتوقف يسجل في مصدره التشغيلي'),
        'RSK-05' => array('route' => 'Risk/risk_assessment.php', 'probe' => 'risk_assessments',
            'kind' => 'TABLE', 'step' => 44, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'التقييم دوري وعند الحدث والاحتمال في الاثر ينتج الكامن'),
        'RSK-06' => array('route' => 'Risk/risk_controls.php', 'probe' => 'risk_controls',
            'kind' => 'TABLE', 'step' => 45, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'الضابط قد يكون قاعدة منع عند الحوكمة ويسجل هنا بمرجعه'),
        'RSK-07' => array('route' => 'Risk/risk_treatments.php', 'probe' => 'risk_treatments',
            'kind' => 'TABLE', 'step' => 46, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'المعالجة باربعة مسارات ولا اغلاق بكتابة نية'),
        'RSK-08' => array('route' => 'Risk/risk_kris.php', 'probe' => 'risk_kris',
            'kind' => 'TABLE', 'step' => 47, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'تعريف المؤشر وعتبتيه اعداد يدخل هنا والقراءة الحالية مشتقة'),
        'RSK-09' => array('route' => 'Risk/risk_acceptance.php', 'probe' => 'risk_acceptances',
            'kind' => 'TABLE', 'step' => 48, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'القبول ضمن شهية المخاطر بسلطة مستواه'),
        'RSK-10' => array('route' => 'Risk/risk_escalations.php', 'probe' => 'risk_escalations',
            'kind' => 'TABLE', 'step' => 49, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'الاختراق الحرج او خروج الشهية او تاخر المعالجة يصعد بمساره'),
        'RSK-11' => array('route' => 'Risk/risk_reviews.php', 'probe' => 'risk_register',
            'kind' => 'TABLE', 'step' => 50, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'كل خطر نشط يراجع بدوريته حسب مستواه'),
        'RSK-12' => array('route' => 'Risk/risk_closure.php', 'probe' => 'rsk_closure',
            'kind' => 'TABLE', 'step' => 51, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'لا يغلق الخطر الا باثبات ومن عالج لا يغلق'),
        'RSK-13' => array('route' => 'Risk/risk_reports.php', 'probe' => 'risk_export_log',
            'kind' => 'TABLE', 'step' => 52, 'domain' => 'DEP-09', 'line' => 'SECOND',
            'why' => 'اليومي للحرجة والاسبوعي للعالية والشهري للمحفظة كاملة'),
        /* ══ المراجعةُ الداخليّة · اللوحةُ خارجَ الدورة ═════════════════ */
        'IAF-01' => array('route' => 'Audit/iaf_overview.php', 'probe' => 'iaf_findings',
            'kind' => 'TABLE', 'step' => 60, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'لوحة المراجعة مشتقة من الخطة والمهام والملاحظات ومتابعتها وصفر ادخال'),
        'IAF-02' => array('route' => 'Audit/iaf_charter.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 61, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الميثاق يثبت الاستقلال وخط الرفع للمالك او اللجنة لا للادارة التنفيذية'),
        'IAF-03' => array('route' => 'Audit/iaf_universe.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 62, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الكون الرقابي يعد كل ما يمكن مراجعته ويرتبه بالمخاطر'),
        'IAF-04' => array('route' => 'Audit/iaf_plan.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 63, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الخطة مبنية على المخاطر لا على التناوب وحده وتعتمد من المالك او اللجنة'),
        'IAF-05' => array('route' => 'Audit/iaf_independence.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 64, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'لا يراجع احد عملا شارك فيه واقرار الاستقلال شرط كل تكليف'),
        /* ══ تنفيذُ المهمّةِ وأدلّتُها ══════════════════════════════════ */
        'IAF-06' => array('route' => 'Audit/iaf_engagements.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 65, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الشاشة الام - المهمة من الخطة او بتكليف موثق بنطاقها وفريقها'),
        'IAF-07' => array('route' => 'Audit/iaf_audit_programs.php', 'probe' => 'iaf_program',
            'kind' => 'TABLE', 'step' => 66, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'البرنامج يربط الهدف بالاختبار ولكل خطوة عينتها ومنفذها'),
        'IAF-08' => array('route' => 'Audit/iaf_evidence_requests.php', 'probe' => 'iaf_evidence_request',
            'kind' => 'TABLE', 'step' => 67, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الدليل يطلب رسميا بمهلة والتاخر في التزويد واقعة تسجل وتصعد'),
        'IAF-09' => array('route' => 'Audit/iaf_test_samples.php', 'probe' => 'iaf_sample',
            'kind' => 'TABLE', 'step' => 68, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'العينة تسحب بمنهجية معلنة من مجتمع معرف وكل مفردة بنتيجتها'),
        'IAF-10' => array('route' => 'Audit/iaf_workpapers.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 69, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'ورقة العمل تربط الخطوة بالدليل بالاستنتاج ولا تعتمد الا بمراجعة'),
        'IAF-11' => array('route' => 'Audit/iaf_access_log.php', 'probe' => 'u13_screen_kit',
            'kind' => 'SERVICE', 'step' => 70, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'وصول المراجع للمعلومة الحساسة حق بميثاقه لكنه يسجل مضافا فقط'),
        /* ══ الملاحظاتُ والتوصيات ══════════════════════════════════════ */
        'IAF-12' => array('route' => 'Audit/iaf_findings.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 71, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الملاحظة باركانها الخمسة والحوكمة لا تغير نتيجتها'),
        'IAF-13' => array('route' => 'Audit/iaf_reports.php', 'probe' => 'u13_screen_kit',
            'kind' => 'SERVICE', 'step' => 72, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'التقرير يصدر بعد الاجتماع الختامي ورد الادارة ويرفع لخط الرفع بالميثاق'),
        /* ══ المتابعةُ والإغلاق ════════════════════════════════════════ */
        'IAF-14' => array('route' => 'Audit/iaf_action_plans.php', 'probe' => 'InternalAuditService',
            'kind' => 'SERVICE', 'step' => 73, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'المتابعة دورية حتى الاغلاق والاغلاق بتحقق المراجعة لا بادعاء الجهة'),
        'IAF-15' => array('route' => 'Audit/iaf_escalations.php', 'probe' => 'u13_screen_kit',
            'kind' => 'SERVICE', 'step' => 74, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'التاخر عن المهلة يصعد اليا بسلمه والحرجة المتاخرة تبلغ خط الرفع'),
        'IAF-16' => array('route' => 'Audit/iaf_quality.php', 'probe' => 'u13_screen_kit',
            'kind' => 'SERVICE', 'step' => 75, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'الوظيفة تقيم كما تقيم غيرها تقييما داخليا دوريا وخارجيا مستقلا'),
        /* ══ الحوكمةُ والضوابطُ داخلَ الوظيفةِ نفسِها ═══════════════════ */
        'IAF-17' => array('route' => 'Audit/iaf_function_risks.php', 'probe' => 'iaf_function_risk',
            'kind' => 'TABLE', 'step' => 76, 'domain' => 'IAF', 'line' => 'THIRD',
            'why' => 'مخاطر الوظيفة نفسها ترفع لخط الرفع بالميثاق لا الى سجل المخاطر المؤسسي'),
    );
}

/** إثباتُ المِرساةِ من القرصِ — لا من دعوى السجلّ */
function repair01_w14_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W14_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry
                       WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) {
        return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY',
                     'rule' => 'W14_ANCHOR_UNPROVEN');
    }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W14_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W14_ANCHOR_UNPROVEN');
    }
    /* ⚠ **الرسوُّ على الاسمِ مقتبَسًا لا على جزءٍ منه** (‏درسُ `W11-22`):
         `gov_breach_archive` يحوي `gov_breach` نصًّا، فبحثٌ بلا حدِّ كلمةٍ
         يُخضِرُّ الحاجبَ وقد نُزع المكشوف. */
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?(?![A-Za-z0-9_])~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W14_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W14_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W14_ANCHOR_UNPROVEN');
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ══════════════════════════════════════════════════════════════════════════
   `sort` هو **موضعُ السطحِ من دورةِ العمل** — لا الأبجديّةُ ولا الإنشاء.
   ⛔ **ولا سطحَ خارجَ المتطلَّباتِ الواحدِ والستّين** — لكلِّ صفٍّ هنا `req`.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w14_new_surfaces()
{
    $GOVR = 'إدارة الحوكمة والالتزام';
    $RSKR = 'إدارة المخاطر';
    $IAFR = 'المراجعة الداخلية';
    return array(
        /* ── الحوكمةُ والالتزام ──────────────────────────────────────── */
        array('route' => 'Governance/gov_board.php', 'ar' => 'لوحة الحوكمة والالتزام',
            'icon' => 'fa fa-scale-balanced', 'group' => 'اللوحة', 'sort' => 0, 'step' => 0,
            /* ⚠ **والشقيقُ يُقاس ببلوغِه لا بقربِه الموضوعيّ** (‏درسُ W12 §١-③):
               `gov_dept_gov.php` أقربُ موضوعًا لكنَّه بصفرِ بندِ قائمةٍ نشِط،
               فاشتقاقُ المنحِ منه يبني سطحًا لا يبلغه أحد. */
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/entities_registry.php',
            'req' => 'GOV-01', 'doc' => 'قراءة حية بلا مستند',
            'next' => 'فتح شاشة الدورة المعنية', 'cons' => 'الحوكمة والقيادة', 'fin' => 'لا'),
        array('route' => 'Governance/policies.php', 'ar' => 'سجل السياسات',
            'icon' => 'fa fa-book', 'group' => 'الكيان والتفويض', 'sort' => 2, 'step' => 2,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/entities_registry.php',
            'req' => 'GOV-03', 'doc' => 'وثيقة السياسة بإصدارها',
            'next' => 'نفاذ السياسة', 'cons' => 'كل الإدارات', 'fin' => 'لا'),
        array('route' => 'Governance/obligations.php', 'ar' => 'الالتزامات التنظيمية',
            'icon' => 'fa fa-gavel', 'group' => 'الكيان والتفويض', 'sort' => 3, 'step' => 3,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/entities_registry.php',
            'req' => 'GOV-04', 'doc' => 'سند الالتزام',
            'next' => 'توليد استحقاقات التقويم', 'cons' => 'الحوكمة والمالية', 'fin' => 'لا'),
        array('route' => 'Governance/compliance_calendar.php', 'ar' => 'تقويم الامتثال',
            'icon' => 'fa fa-calendar-check', 'group' => 'الكيان والتفويض', 'sort' => 4, 'step' => 4,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/entities_registry.php',
            'req' => 'GOV-05', 'doc' => 'قراءة مشتقة بلا مستند',
            'next' => 'تنفيذ الاستحقاق أو تصعيده', 'cons' => 'الإدارات المالكة', 'fin' => 'لا'),
        array('route' => 'Governance/regulatory_filings.php', 'ar' => 'التقديمات النظامية',
            'icon' => 'fa fa-file-arrow-up', 'group' => 'المستندات والالتزام', 'sort' => 7, 'step' => 7,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/licenses_guarantees.php',
            'req' => 'GOV-08', 'doc' => 'إيصال الجهة',
            'next' => 'إقفال الاستحقاق', 'cons' => 'الحوكمة والمالية', 'fin' => 'لا'),
        array('route' => 'Governance/sod_conflicts.php', 'ar' => 'فصل الواجبات المتعارضة',
            'icon' => 'fa fa-code-branch', 'group' => 'الأدوار والصلاحيات', 'sort' => 13, 'step' => 13,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/auth_profiles.php',
            'req' => 'GOV-16', 'doc' => 'محضر معالجة التعارض',
            'next' => 'معالجة أو استثناء بمدته', 'cons' => 'الحوكمة والمراجعة الداخلية', 'fin' => 'لا'),
        array('route' => 'Governance/conflict_disclosures.php', 'ar' => 'تضارب المصالح',
            'icon' => 'fa fa-user-shield', 'group' => 'الأدوار والصلاحيات', 'sort' => 14, 'step' => 14,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/auth_profiles.php',
            'req' => 'GOV-10', 'doc' => 'قرار الحوكمة على الإفصاح',
            'next' => 'تجنيب أو ضوابط أو رفض', 'cons' => 'الحوكمة والموارد البشرية', 'fin' => 'لا'),
        array('route' => 'Governance/related_parties.php', 'ar' => 'الأطراف ذات العلاقة',
            'icon' => 'fa fa-handshake', 'group' => 'الأدوار والصلاحيات', 'sort' => 15, 'step' => 15,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/auth_profiles.php',
            'req' => 'GOV-11', 'doc' => 'إفصاح التعامل',
            'next' => 'اعتماد التعامل بإفصاحه', 'cons' => 'الحوكمة والمالية والمشتريات', 'fin' => 'نعم'),
        array('route' => 'Governance/gifts_hospitality.php', 'ar' => 'الهدايا والضيافة',
            'icon' => 'fa fa-gift', 'group' => 'الأدوار والصلاحيات', 'sort' => 16, 'step' => 16,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/auth_profiles.php',
            'req' => 'GOV-12', 'doc' => 'قرار القبول أو الرد',
            'next' => 'قبول أو رد بحسب السياسة', 'cons' => 'الحوكمة', 'fin' => 'لا'),
        array('route' => 'Governance/conduct_acknowledgements.php', 'ar' => 'إقرارات مدونة السلوك',
            'icon' => 'fa fa-file-signature', 'group' => 'الأدوار والصلاحيات', 'sort' => 17, 'step' => 17,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/auth_profiles.php',
            'req' => 'GOV-13', 'doc' => 'إقرار الموظف',
            'next' => 'تعليم الناقص وتصعيده', 'cons' => 'الحوكمة والموارد البشرية', 'fin' => 'لا'),
        array('route' => 'Governance/approval_ladders.php', 'ar' => 'سلاليم الاعتماد',
            'icon' => 'fa fa-stairs', 'group' => 'الاستثناء والرقابة', 'sort' => 18, 'step' => 18,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/authority_caps.php',
            'req' => 'GOV-18', 'doc' => 'وثيقة تعريف السلم',
            'next' => 'قراءة السلم في محرك الاعتماد', 'cons' => 'كل الإدارات', 'fin' => 'لا'),
        array('route' => 'Governance/integrity_reports.php', 'ar' => 'بلاغات النزاهة المحمية',
            'icon' => 'fa fa-shield-halved', 'group' => 'الاستثناء والرقابة', 'sort' => 21, 'step' => 21,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/exceptions.php',
            'req' => 'GOV-22', 'doc' => 'محضر الفرز',
            'next' => 'فرز ثم إحالة إن لزم', 'cons' => 'الحوكمة', 'fin' => 'لا'),
        array('route' => 'Governance/investigations.php', 'ar' => 'التحقيقات',
            'icon' => 'fa fa-magnifying-glass', 'group' => 'الاستثناء والرقابة', 'sort' => 22, 'step' => 22,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/exceptions.php',
            'req' => 'GOV-23', 'doc' => 'تكليف التحقيق ونتيجته',
            'next' => 'إحالة النتيجة لجهة أثرها', 'cons' => 'الحوكمة والموارد البشرية', 'fin' => 'لا'),
        array('route' => 'Governance/breaches.php', 'ar' => 'سجل الإخلالات',
            'icon' => 'fa fa-triangle-exclamation', 'group' => 'الاستثناء والرقابة', 'sort' => 23, 'step' => 23,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/exceptions.php',
            'req' => 'GOV-24', 'doc' => 'ملف حالة الحوكمة',
            'next' => 'إسناد إجراء تصحيحي', 'cons' => 'الحوكمة والمراجعة الداخلية', 'fin' => 'لا'),
        array('route' => 'Governance/corrective_actions.php', 'ar' => 'الإجراءات التصحيحية',
            'icon' => 'fa fa-screwdriver-wrench', 'group' => 'الاستثناء والرقابة', 'sort' => 24, 'step' => 24,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/exceptions.php',
            'req' => 'GOV-25', 'doc' => 'دليل إغلاق الإجراء',
            'next' => 'تحقق ثم إغلاق', 'cons' => 'الإدارات المالكة والحوكمة', 'fin' => 'لا'),
        array('route' => 'Governance/audit_followup.php', 'ar' => 'متابعة نتائج المراجعة',
            'icon' => 'fa fa-list-check', 'group' => 'الاستثناء والرقابة', 'sort' => 25, 'step' => 25,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/exceptions.php',
            'req' => 'GOV-26', 'doc' => 'خطة الإدارة ومهلها',
            'next' => 'تصعيد المتأخر', 'cons' => 'الحوكمة', 'fin' => 'لا'),
        array('route' => 'Governance/committees.php', 'ar' => 'اللجان وحوكمة الاجتماعات',
            'icon' => 'fa fa-users-rectangle', 'group' => 'الأنظمة المرجعية', 'sort' => 29, 'step' => 29,
            'owner' => 'DEP-08', 'role' => $GOVR, 'sibling' => 'Governance/doc_types.php',
            'req' => 'GOV-30', 'doc' => 'ميثاق اللجنة',
            'next' => 'انعقاد بدوريته', 'cons' => 'الحوكمة والقيادة', 'fin' => 'لا'),
        /* ── إدارةُ المخاطر ──────────────────────────────────────────── */
        array('route' => 'Risk/risk_taxonomy.php', 'ar' => 'تصنيف المخاطر',
            'icon' => 'fa fa-sitemap', 'group' => 'التأسيس المرجعي', 'sort' => 41, 'step' => 41,
            'owner' => 'DEP-09', 'role' => $RSKR, 'sibling' => 'Risk/risk_settings.php',
            'req' => 'RSK-02', 'doc' => 'شجرة التصنيف المعتمدة',
            'next' => 'إسناد الخطر إلى عقدة واحدة', 'cons' => 'إدارة المخاطر', 'fin' => 'لا'),
        array('route' => 'Risk/risk_events.php', 'ar' => 'أحداث المخاطر والخسائر',
            'icon' => 'fa fa-bolt', 'group' => 'السجل والتقييم', 'sort' => 43, 'step' => 43,
            'owner' => 'DEP-09', 'role' => $RSKR, 'sibling' => 'Risk/risk_register.php',
            'req' => 'RSK-04', 'doc' => 'قراءة بمرجع المصدر',
            'next' => 'تقييم التعرض', 'cons' => 'إدارة المخاطر والمالية', 'fin' => 'نعم'),
        array('route' => 'Risk/risk_escalations.php', 'ar' => 'تصعيدات المخاطر',
            'icon' => 'fa fa-arrow-up-right-dots', 'group' => 'القرار والإغلاق', 'sort' => 49, 'step' => 49,
            'owner' => 'DEP-09', 'role' => $RSKR, 'sibling' => 'Risk/risk_acceptance.php',
            'req' => 'RSK-10', 'doc' => 'محضر التصعيد',
            'next' => 'قرار الجهة المصعد إليها', 'cons' => 'إدارة المخاطر والقيادة', 'fin' => 'لا'),
        array('route' => 'Risk/risk_closure.php', 'ar' => 'سجل الإغلاق والأدلة',
            'icon' => 'fa fa-circle-check', 'group' => 'القرار والإغلاق', 'sort' => 51, 'step' => 51,
            'owner' => 'DEP-09', 'role' => $RSKR, 'sibling' => 'Risk/risk_acceptance.php',
            'req' => 'RSK-12', 'doc' => 'دليل الإغلاق',
            'next' => 'إقفال الخطر أو إعادة فتحه', 'cons' => 'إدارة المخاطر والمراجعة الداخلية', 'fin' => 'لا'),
        /* ── المراجعةُ الداخليّة ─────────────────────────────────────── */
        array('route' => 'Audit/iaf_overview.php', 'ar' => 'لوحة المراجعة الداخلية',
            'icon' => 'fa fa-clipboard-check', 'group' => 'اللوحة', 'sort' => 60, 'step' => 60,
            'owner' => 'IAF', 'role' => $IAFR, 'sibling' => 'Audit/iaf_charter.php',
            'req' => 'IAF-01', 'doc' => 'قراءة حية بلا مستند',
            'next' => 'فتح المهمة المعنية', 'cons' => 'المراجعة الداخلية والمالك', 'fin' => 'لا'),
        array('route' => 'Audit/iaf_audit_programs.php', 'ar' => 'برامج المراجعة',
            'icon' => 'fa fa-diagram-project', 'group' => 'تنفيذ المهمة وأدلتها', 'sort' => 66, 'step' => 66,
            'owner' => 'IAF', 'role' => $IAFR, 'sibling' => 'Audit/iaf_engagements.php',
            'req' => 'IAF-07', 'doc' => 'برنامج المهمة المعتمد',
            'next' => 'سحب العينة وتنفيذ الاختبار', 'cons' => 'المراجعة الداخلية', 'fin' => 'لا'),
        array('route' => 'Audit/iaf_evidence_requests.php', 'ar' => 'طلبات الأدلة',
            'icon' => 'fa fa-inbox', 'group' => 'تنفيذ المهمة وأدلتها', 'sort' => 67, 'step' => 67,
            'owner' => 'IAF', 'role' => $IAFR, 'sibling' => 'Audit/iaf_engagements.php',
            'req' => 'IAF-08', 'doc' => 'طلب الدليل ومهلته',
            'next' => 'تزويد أو تصعيد التأخر', 'cons' => 'المراجعة والجهة الخاضعة', 'fin' => 'لا'),
        array('route' => 'Audit/iaf_test_samples.php', 'ar' => 'العينات ونتائج الاختبارات',
            'icon' => 'fa fa-vials', 'group' => 'تنفيذ المهمة وأدلتها', 'sort' => 68, 'step' => 68,
            'owner' => 'IAF', 'role' => $IAFR, 'sibling' => 'Audit/iaf_engagements.php',
            'req' => 'IAF-09', 'doc' => 'ورقة نتائج الاختبار',
            'next' => 'رفع ملاحظة عند الاستثناء', 'cons' => 'المراجعة الداخلية', 'fin' => 'لا'),
        array('route' => 'Audit/iaf_function_risks.php', 'ar' => 'مخاطر وظيفة المراجعة',
            'icon' => 'fa fa-user-secret', 'group' => 'الحوكمة والضوابط', 'sort' => 76, 'step' => 76,
            'owner' => 'IAF', 'role' => $IAFR, 'sibling' => 'Audit/iaf_quality.php',
            'req' => 'IAF-17', 'doc' => 'تقرير مخاطر الوظيفة',
            'next' => 'رفع لخط الرفع بالميثاق', 'cons' => 'المراجعة الداخلية والمالك', 'fin' => 'لا'),
    );
}

/**
 * **صنفُ السطحِ وحكمُ ملكيّتِه — مُعلَنانِ لكلِّ سطحٍ لا مُشتقّانِ من اسمِ ملفّ.**
 * ◆ `surface_kind`: `SOURCE` يملك صفوفَه · `PROJECTION` يقرأ ولا يُدخِل.
 * ◆ `ownership_verdict`: من القائمةِ التي ثبّتها `chk_w135_ownv` —
 *   و`AUDIT_ASSURANCE` للخطِّ الثالثِ وحدَه، فالمراجعةُ ليست مجالَ أعمالٍ.
 * ⛔ **ولا يُشتقُّ الصنفُ من لاحقةِ الاسم** (‏`_board`): لوحةٌ قد تكون مصدرًا
 *   ولوحةٌ قد تكون إسقاطًا، والاشتقاقُ من الاسمِ تخمينٌ يُقرأ قياسًا.
 */
function repair01_w14_surface_class($route)
{
    $M = array(
        'Governance/gov_board.php'                 => array('PROJECTION', 'DOMAIN_PROJECTION'),
        'Governance/policies.php'                  => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/obligations.php'               => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/compliance_calendar.php'       => array('PROJECTION', 'DOMAIN_PROJECTION'),
        'Governance/regulatory_filings.php'        => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/sod_conflicts.php'             => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/conflict_disclosures.php'      => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/related_parties.php'           => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/gifts_hospitality.php'         => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/conduct_acknowledgements.php'  => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/approval_ladders.php'          => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/integrity_reports.php'         => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/investigations.php'            => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/breaches.php'                  => array('SOURCE', 'DOMAIN_SOURCE'),
        'Governance/corrective_actions.php'        => array('SOURCE', 'DOMAIN_SOURCE'),
        /* **متابعةُ نتائجِ المراجعةِ إسقاطٌ لا مصدر** — النتيجةُ عند المراجعةِ
           وحدَها، والحوكمةُ تملك صفَّ المتابعةِ ولا تملك ما تتابعه. */
        'Governance/audit_followup.php'            => array('PROJECTION', 'DOMAIN_PROJECTION'),
        'Governance/committees.php'                => array('SOURCE', 'DOMAIN_SOURCE'),
        'Risk/risk_taxonomy.php'                   => array('SOURCE', 'DOMAIN_SOURCE'),
        'Risk/risk_events.php'                     => array('SOURCE', 'DOMAIN_SOURCE'),
        'Risk/risk_escalations.php'                => array('SOURCE', 'DOMAIN_SOURCE'),
        'Risk/risk_closure.php'                    => array('SOURCE', 'DOMAIN_SOURCE'),
        'Audit/iaf_overview.php'                      => array('PROJECTION', 'AUDIT_ASSURANCE'),
        'Audit/iaf_audit_programs.php'                   => array('SOURCE', 'AUDIT_ASSURANCE'),
        'Audit/iaf_evidence_requests.php'          => array('SOURCE', 'AUDIT_ASSURANCE'),
        'Audit/iaf_test_samples.php'                    => array('SOURCE', 'AUDIT_ASSURANCE'),
        'Audit/iaf_function_risks.php'             => array('SOURCE', 'AUDIT_ASSURANCE'),
    );
    return isset($M[$route]) ? $M[$route] : array('', '');
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ مجموعاتُ دورةِ العملِ — بلا تشكيلٍ ولا زخرفةٍ ولا مصطلحٍ تقنيّ
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w14_group_rewrites()
{
    return array(
        'اللوحة — خارج الدورة (Overview)' => 'اللوحة',
        'اللوحة' => 'اللوحة',
        'الكيان والتفويض' => 'الكيان والتفويض',
        'المستندات والالتزام' => 'المستندات والالتزام',
        'الأدوار والصلاحيات' => 'الأدوار والصلاحيات',
        'الاستثناء والرقابة' => 'الاستثناء والرقابة',
        'الأنظمة المرجعية' => 'الأنظمة المرجعية',
        'التأسيس المرجعي' => 'التأسيس المرجعي',
        'السجل والتقييم' => 'السجل والتقييم',
        'المعالجة والمراقبة' => 'المعالجة والمراقبة',
        'القرار والإغلاق' => 'القرار والإغلاق',
        'التقارير' => 'التقارير',
        'الميثاق والخطة' => 'الميثاق والخطة',
        'تنفيذ المهمة وأدلتها' => 'تنفيذ المهمة وأدلتها',
        'الملاحظات والتوصيات' => 'الملاحظات والتوصيات',
        'المتابعة والإغلاق' => 'المتابعة والإغلاق',
        'الحوكمة والضوابط' => 'الحوكمة والضوابط',
    );
}

function repair01_w14_group_ar($raw)
{
    $map = repair01_w14_group_rewrites();
    $raw = (string) $raw;
    if (isset($map[$raw])) { return $map[$raw]; }
    $cut = preg_split('~\s+—\s+~u', $raw);
    $head = trim((string) $cut[0]);
    return isset($map[$head]) ? $map[$head] : $head;
}

/**
 * **اسمُ السطحِ المُصيَّرُ من اسمِ المتطلَّبِ — منقًّى لا منقولًا حرفًا.**
 * ◆ ثلاثةُ ممنوعاتٍ تسري هنا: لاحقةُ «بحسب انطباق الشركة» شرطُ تطبيقٍ لا
 *   اسمُ شاشة · والمصطلحُ اللاتينيُّ بعد الشرطةِ (`Taxonomy` · `Controls` ·
 *   `KRIs` · `Treatment` · `Risk/Loss Events`) رمزٌ تقنيٌّ يُمنَع في الواجهة ·
 *   والشرطةُ **تصير مسافةً ولا تصير نقطتَين** (‏عطبُ W06 المقيس).
 */
function repair01_w14_surface_label($raw)
{
    $s = trim((string) $raw);
    $s = preg_replace('~\s*—\s*بحسب انطباق الشركة\s*$~u', '', $s);
    /* المصطلحُ اللاتينيُّ بعد الشرطةِ يُقصّ — والعربيُّ قبلَها هو الاسم */
    $s = preg_replace('~\s*—\s*[A-Za-z][A-Za-z0-9 /\\\\_.\-]*$~u', '', $s);
    $s = preg_replace('~\s+—\s+~u', ' ', $s);
    return trim(preg_replace('~\s{2,}~u', ' ', $s));
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ محاورُ المرحلةِ الثلاثةُ — كلُّ محورٍ على جبهاتٍ تُعاد في كلِّ نداء
   ══════════════════════════════════════════════════════════════════════════
   ⚠ **وجبهةٌ غيرُ مقيسةٍ تُسقط الحاجبَ ولا تُعَدُّ صفرًا**: كلُّ دالّةٍ هنا
     تُعيد `array('n' => العدد, 'front' => عددُ الجبهاتِ المقيسة, 'detail' => …)`.
   ══════════════════════════════════════════════════════════════════════════ */

/**
 * **① حالةُ حوكمةٍ على انحرافٍ تشغيليٍّ صِرف.**
 * ثلاثُ جبهات: حالةٌ مرتبطةٌ بانحرافٍ مُصنَّفٍ `DEVIATION_ONLY` · حالةٌ
 * مرتبطةٌ بانحرافٍ **لم يُصنَّف بعد** · وحالةٌ بأساسٍ خارجَ الثمانية.
 */
function repair01_w14_gov_case_on_pure_deviation(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_table_exists($c, 'gov_breach') || !repair01_w14_table_exists($c, 'ctl_deviation')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('TABLE_MISSING'));
    }
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_breach b
             JOIN ctl_deviation d ON d.company_id = b.company_id AND d.deviation_no = b.deviation_no
            WHERE b.deviation_no <> '' AND d.classification = 'DEVIATION_ONLY'");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "مرتبطة بانحراف مصنف انحرافا فقط $a";
    $b = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_breach b
             JOIN ctl_deviation d ON d.company_id = b.company_id AND d.deviation_no = b.deviation_no
            WHERE b.deviation_no <> '' AND d.classification = 'PENDING'");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "مرتبطة بانحراف غير مصنف $b";
    $e = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_breach
            WHERE opened_basis NOT IN ('MANDATORY_STEP_IGNORED','NO_ESCALATION','AUTHORITY_EXCEEDED',
                  'MANIPULATION','CONCEALMENT','FORGERY','POLICY_BREACH','CONTROL_BROKEN')");
    $out['front']++; $out['n'] += $e; $out['detail'][] = "باساس خارج الثمانية $e";
    return $out;
}

/**
 * **② نسخُ حدثٍ في المخاطر.**
 * أربعُ جبهات: حدثٌ بلا مرجعِ مصدر · حدثٌ نسخَ نفسَ المصدرِ مرّتَين ·
 * محفِّزٌ بلا مرجعِ مصدر · وسجلُّ خطرٍ فُتح لعطلٍ بلا محفِّزٍ يسنده.
 */
function repair01_w14_risk_event_copies(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_table_exists($c, 'rsk_event') || !repair01_w14_table_exists($c, 'rsk_trigger')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('TABLE_MISSING'));
    }
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM rsk_event
            WHERE source_table = '' OR source_row_id = 0 OR source_module = ''");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "حدث بلا مرجع مصدر $a";
    $b = (int) repair01_w14_one($c, "SELECT COALESCE(SUM(k - 1), 0) FROM (
              SELECT COUNT(*) k FROM rsk_event
               GROUP BY company_id, source_table, source_row_id, event_kind HAVING COUNT(*) > 1) t");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "نسخة مكررة لمصدر واحد $b";
    $t = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM rsk_trigger
            WHERE source_table = '' OR source_row_id = 0 OR threshold_key = ''");
    $out['front']++; $out['n'] += $t; $out['detail'][] = "محفز بلا مرجع مصدر او عتبة $t";
    $p = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM rsk_trigger
            WHERE rule_code = 'UNPLANNED_24H'
              AND downtime_kind IN ('PLANNED_MAINTENANCE','PLANNED_OVERHAUL','CLIENT_STANDBY','OPERATIONAL_STANDBY')");
    $out['front']++; $out['n'] += $p; $out['detail'][] = "محفز اربع وعشرين على مخطط $p";
    return $out;
}

/**
 * **③ تعديلُ الحوكمةِ لنتيجةِ مراجعة.**
 * أربعُ جبهات: واضعُ النتيجةِ من غيرِ `IAF` · مُغلِقُها من غيرِ `IAF` ·
 * نطاقُ برنامجٍ تحدّده الحوكمة · ومتابعةُ حوكمةٍ تحمل عمودَ نتيجةٍ أصلًا.
 */
function repair01_w14_gov_touched_audit_result(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_col_exists($c, 'iaf_findings', 'result_set_by_dept')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('COLUMN_MISSING'));
    }
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM iaf_findings
            WHERE result_set_by_dept NOT IN ('','IAF')");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "واضع نتيجة من غير المراجعة $a";
    $b = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM iaf_findings
            WHERE result_closed_by_dept NOT IN ('','IAF')");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "مغلق نتيجة من غير المراجعة $b";
    $s = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM iaf_program WHERE scope_set_by_dept <> 'IAF'");
    $out['front']++; $out['n'] += $s; $out['detail'][] = "نطاق برنامج من غير المراجعة $s";
    /* **والبنيةُ تُقاس لا النيّة**: متابعةُ الحوكمةِ لا يجوز أن تحمل عمودًا
       يخزّن النتيجةَ أو تقديرَها — فوجودُه وحدَه يفتح بابَ التعديل. */
    $cols = 0;
    foreach (array('finding_severity', 'finding_conclusion', 'finding_rating', 'finding_state') as $col) {
        if (repair01_w14_col_exists($c, 'gov_audit_followup', $col)) { $cols++; }
    }
    $out['front']++; $out['n'] += $cols; $out['detail'][] = "عمود نتيجة في متابعة الحوكمة $cols";
    return $out;
}

/**
 * **④ سطحُ مصدرٍ يملكه أكثرُ من نطاق** (`Shared Master Transaction = 0`).
 * ثلاثُ جبهات: جدولٌ مُعلَنٌ لنطاقَين · جدولٌ حيٌّ في نطاقٍ ومِرساةٌ في آخر ·
 * وجدولُ نطاقٍ بلا خدمةٍ مالكةٍ واحدة.
 */
function repair01_w14_shared_master(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_table_exists($c, 'repair01_w14_domains')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('TABLE_MISSING'));
    }
    /* المفتاحُ الفريدُ يمنع الازدواجَ في القاعدة — والقياسُ يثبت أنَّه يمنعه */
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM (
              SELECT table_name FROM repair01_w14_domains GROUP BY table_name HAVING COUNT(*) > 1) t");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "جدول معلن لنطاقين $a";
    $b = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM repair01_w14_domains
            WHERE domain_code = '' OR service_file = '' OR source_key = ''");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "جدول بلا نطاق او خدمة او مفتاح $b";
    /* والمِرساةُ تعلن نطاقَ سطحِها — فمِرساةٌ في نطاقٍ ومِسبارُها جدولُ نطاقٍ
       آخرَ **مشاركةُ مصدرٍ لا مرجع** */
    $cross = 0; $D = repair01_w14_domains();
    foreach (repair01_w14_anchors() as $rid => $a2) {
        if ($a2['kind'] !== 'TABLE') { continue; }
        $t = $a2['probe'];
        if (!isset($D[$t])) { continue; }
        $own = $D[$t][0];
        if ($own !== 'SOURCE' && $own !== $a2['domain']) { $cross++; }
    }
    $out['front']++; $out['n'] += $cross; $out['detail'][] = "مرساة سطح في نطاق ومسبارها جدول اخر $cross";
    return $out;
}

/**
 * **⑤ كتابةٌ عابرةٌ للنطاقِ في شيفرةِ الخدمات.**
 * ⛔ **المرجعُ قراءةٌ والمشاركةُ كتابة** — فالماسحُ يرصد `insert`/`update`/
 * `deleteRow` وما يقابلها من `SQL` كاتب، ويقارن نطاقَ الجدولِ بنطاقِ الخدمة.
 * والقراءةُ (`select`/`count`/`FROM`) **لا تُرصَد** — فهي عينُ ما أمر به المالك.
 */
function repair01_w14_cross_domain_writes($ROOT)
{
    $D = repair01_w14_domains();
    $svcDomain = array();
    foreach ($D as $t => $d) { $svcDomain[$d[7]] = $d[0]; }
    $hits = array(); $scanned = 0;
    foreach ($svcDomain as $file => $dom) {
        $path = $ROOT . '/' . $file;
        if (!is_file($path)) { $hits[] = basename($file) . ' (‏غير موجود)'; continue; }
        $scanned++;
        $src = (string) file_get_contents($path);
        $found = array();
        /* بوّابةُ العزلِ الكاتبة */
        if (preg_match_all('~->\s*(insert|update|deleteRow|softDelete|replaceChildren)\s*\(\s*[\'"]([a-z0-9_]+)[\'"]~i',
                           $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) { $found[] = $x[2]; }
        }
        /* و`SQL` كاتبٌ مباشر */
        if (preg_match_all('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-z0-9_]+)`?~i',
                           $src, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $x) { $found[] = $x[2]; }
        }
        foreach (array_unique($found) as $t) {
            if (!isset($D[$t])) { continue; }
            if ($D[$t][0] !== $dom) {
                $hits[] = basename($file) . ' ⇐ ' . $t . ' (' . $D[$t][0] . ')';
            }
        }
    }
    return array('n' => count($hits), 'scanned' => $scanned, 'detail' => $hits);
}

/**
 * **⑥ التحقيقُ بمالكِه وبشرطِه.**
 * خمسُ جبهات: نوعٌ عند غيرِ مالكِه · مستقلٌّ بلا تكليف · من سجلِّ المنعِ بلا
 * فرزٍ · تعارضٌ بلا تنحٍّ وسلطةٍ محجوزة · ومحقّقٌ هو موضوعُ التحقيق.
 */
function repair01_w14_investigation_faults(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_table_exists($c, 'gov_investigation')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('TABLE_MISSING'));
    }
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_investigation
            WHERE NOT ((inv_kind='DISCIPLINARY' AND owner_dept='DEP-07')
                    OR (inv_kind='INTEGRITY' AND owner_dept='DEP-08')
                    OR (inv_kind='SPECIAL_INDEPENDENT' AND owner_dept='IAF')
                    OR (inv_kind='OPERATIONAL_FACT' AND owner_dept NOT IN ('DEP-07','DEP-08','IAF')
                        AND owner_dept <> ''))");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "نوع عند غير مالكه $a";
    $b = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_investigation
            WHERE inv_kind = 'SPECIAL_INDEPENDENT' AND mandate_doc_ref = ''");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "مستقل بلا تكليف مكتوب $b";
    $d = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_investigation
            WHERE origin = 'DENIAL' AND triage_ref = ''");
    $out['front']++; $out['n'] += $d; $out['detail'][] = "من سجل المنع بلا فرز $d";
    $r = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_investigation
            WHERE conflict_flag = 1 AND (recusal_of = '' OR reserved_authority_ref = '')");
    $out['front']++; $out['n'] += $r; $out['detail'][] = "تعارض بلا تنح وسلطة محجوزة $r";
    $s = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_investigation
            WHERE investigator_id <> 0 AND investigator_id = subject_person");
    $out['front']++; $out['n'] += $s; $out['detail'][] = "محقق هو موضوع التحقيق $s";
    return $out;
}

/**
 * **⑦ حبّةُ الكيانِ والوسمُ بين الكيانَين.**
 * جبهتان: جدولُ موجةٍ بلا `company_id` غيرِ قابلٍ للعدم · وتعاملٌ بين كيانَين
 * بلا الخماسيِّ الكامل.
 */
function repair01_w14_entity_faults(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    $miss = array();
    foreach (repair01_w14_wave_tables() as $t) {
        if (!repair01_w14_table_exists($c, $t)) { $miss[] = $t . ' (‏غائب)'; continue; }
        if (!repair01_w14_entity_scoped($c, $t)) { $miss[] = $t; }
    }
    $out['front']++; $out['n'] += count($miss);
    $out['detail'][] = 'جدول بلا حبة كيان صلبة ' . count($miss)
        . (count($miss) ? ' ⇐ ' . implode('، ', array_slice($miss, 0, 4)) : '');
    if (repair01_w14_table_exists($c, 'gov_related_party')) {
        $ic = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM gov_related_party
                WHERE intercompany_flag = 1 AND (from_legal_entity_id = 0 OR to_legal_entity_id = 0
                      OR counterparty_entity_id = 0 OR transaction_type = '')");
        $out['front']++; $out['n'] += $ic; $out['detail'][] = "تعامل بين كيانين بلا الخماسي $ic";
    }
    return $out;
}

/** جداولُ الموجةِ التي يلزمها حبّةُ الكيان — مقامٌ ثابتٌ مُعلَن */
function repair01_w14_wave_tables()
{
    return array(
        'ctl_classification_rule', 'ctl_deviation',
        'gov_policy', 'gov_obligation', 'gov_compliance_due', 'gov_filing',
        'gov_conflict_disclosure', 'gov_related_party', 'gov_gift_disclosure', 'gov_conduct_ack',
        'gov_sod_conflict', 'gov_integrity_report', 'gov_investigation', 'gov_breach',
        'gov_corrective_action', 'gov_audit_followup', 'gov_committee', 'gov_request_type',
        'rsk_taxonomy', 'rsk_trigger', 'rsk_event', 'rsk_closure',
        'iaf_program', 'iaf_evidence_request', 'iaf_sample', 'iaf_function_risk',
    );
}

/** قيودُ المخطَّطِ المحوريّةُ — مقامٌ ثابتٌ يُقاس وجودُها في القاعدةِ لا في النصّ */
function repair01_w14_schema_constraints()
{
    return array(
        'chk_ctd_owner_not_control', 'chk_ctd_rule_required', 'chk_ctd_source', 'chk_ctd_only_no_refs',
        'chk_ctlr_tests', 'chk_ctlr_sod',
        'chk_gvb_basis', 'chk_gvb_control', 'chk_gvb_close', 'chk_gvb_hands',
        'chk_gin_kind_owner', 'chk_gin_iaf_mandate', 'chk_gin_denial_triage', 'chk_gin_recusal',
        'chk_gin_self', 'chk_gin_hands',
        'chk_gaf_ref', 'chk_gac_owner', 'chk_gac_close', 'chk_gac_hands',
        'chk_grp_intercompany', 'chk_grp_not_self', 'chk_gcf_self', 'chk_ggf_self',
        'chk_gsc_sides', 'chk_gsc_accept', 'chk_gir_anon', 'chk_gir_triage',
        'chk_grt_gov', 'chk_grt_domain', 'chk_grt_active',
        'chk_rtx_family', 'chk_rtg_rule', 'chk_rtg_planned_excluded', 'chk_rtg_source',
        'chk_rtg_threshold', 'chk_rev_source', 'chk_rev_family', 'chk_rcl_evidence', 'chk_rcl_hands',
        'chk_ifp_scope_not_gov', 'chk_ifp_hands', 'chk_ifr_auditee', 'chk_ifs_tested',
        'chk_ifk_reported', 'chk_iaf_result_dept', 'chk_iaf_close_dept',
        'chk_w14_th_status', 'chk_w14_th_approved_has_value', 'chk_w14_th_approved_has_ref',
        'chk_w14_th_pending_no_value', 'chk_w14_th_test_not_prod',
        'chk_w14_dom_code', 'chk_w14_dom_line', 'chk_w14_def_kind',
    );
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑦ العتبةُ من السجلِّ — ولا رقمَ مكتوبٌ في شيفرةِ النطاق
   ══════════════════════════════════════════════════════════════════════════
   ⚠ **والماسحُ يمسح أدواتِ النطاقِ كلَّها ومنها ملفُّ الفحصِ السلبيِّ نفسُه**
     (‏عطبُ W12 الرابع). فحمولةُ الكسرِ هناك **تُركَّب رقمًا** ولا يُستثنى ملفٌّ.
   ⚠ **والمقياسُ قيمةُ العتبةِ نفسِها لا حجمُ الرقم** (‏تشديدُ W13): مقامُ
     البحثِ **قيمُ السجلِّ** — ٢٤ و٣ و٣٠ … — لا «ثلاثةُ أرقامٍ فأكثر».
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w14_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w14_table_exists($c, 'repair01_w14_thresholds')) { return $out; }
    $r = @$c->query("SELECT threshold_key, value_num, test_value_num, status FROM repair01_w14_thresholds");
    while ($r && $x = $r->fetch_assoc()) { $out[$x['threshold_key']] = $x; }
    return $out;
}

function repair01_w14_hardcoded_thresholds($ROOT, mysqli $c = null, $layer = 'BUSINESS')
{
    $vals = array();
    if ($c instanceof mysqli && repair01_w14_table_exists($c, 'repair01_w14_thresholds')) {
        $r = @$c->query("SELECT value_num, test_value_num FROM repair01_w14_thresholds");
        while ($r && $x = $r->fetch_assoc()) {
            foreach (array('value_num', 'test_value_num') as $k) {
                if ($x[$k] === null) { continue; }
                $v = (float) $x[$k];
                if ($v <= 0) { continue; }
                $vals[] = (string) (int) $v;
            }
        }
    }
    $vals = array_values(array_unique($vals));
    if (!$vals) { return array('n' => 0, 'scanned' => 0, 'detail' => array('NO_THRESHOLD_ROWS')); }

    /* ⚠ **ومقامانِ لا مقامٌ واحد** — والفرقُ بينهما ليس إعفاءً بل معنًى:
       ◆ **طبقةُ الأعمال** (‏أربعُ خدماتٍ وستّةٌ وعشرون سطحًا): رقمٌ في موضعِ
         مقارنةٍ هنا **يقرّر أمرَ عملٍ**، فهو عتبةٌ صلبةٌ ويُشترط صفرًا.
       ◆ **طبقةُ القياس** (‏أدواتُ الموجةِ وبوّابتُها): `=== 3` هناك **عدّادُ
         جبهاتٍ** يقول «قِستُ ثلاثًا» لا «الحدُّ ثلاثة» — فيُعلَن بعددِه
         وسطورِه ⛔ **ولا يُدَّعى صفرًا ولا يُسكَت عنه** (`W14-D-10`). */
    $files = array();
    if ($layer === 'TOOLS') {
        foreach (glob($ROOT . '/tools/repair01_w14_*.php') as $f) { $files[] = $f; }
        foreach (glob($ROOT . '/tools/lib/repair01_w14_*.php') as $f) { $files[] = $f; }
    } else {
        foreach (array('app/Services/Governance/GovernanceDomainService.php',
                       'app/Services/Risk/RiskDomainService.php',
                       'app/Services/Audit/AuditDomainService.php',
                       'app/Services/Control/DeviationClassifier.php',
                       'app/Services/Control/ThresholdRegistry.php') as $f) {
            if (is_file($ROOT . '/' . $f)) { $files[] = $ROOT . '/' . $f; }
        }
        foreach (repair01_w14_new_surfaces() as $s) {
            if (is_file($ROOT . '/' . $s['route'])) { $files[] = $ROOT . '/' . $s['route']; }
        }
    }

    /* ⚠ **والعتبةُ ما قُورِن بها لا ما كُتب من رقم.**
       كاشفُ W13 رسا على **حجمِ الرقم** (‏ثلاثةُ أرقامٍ فأكثر) فمرَّت عتباتُ
       رقمَين. ورسوٌّ على **قيمةِ السجلِّ وحدَها** يقع في العطبِ المقابل:
       `array_slice($x, 0, 3)` عدّادُ حلقةٍ لا عتبةُ أعمال، فيُسقط الحاجبَ
       على بريء. فالمقياسُ هنا **القيمةُ في موضعِ مقارنة** — رقمُ السجلِّ
       ملاصقًا لعاملِ مقارنةٍ — وهو عينُ ما يعنيه «عتبةٌ صلبةٌ في الشيفرة». */
    /* ⚠ **و`=>` ليست عاملَ مقارنة** — وهي سهمُ مصفوفةٍ يسبقه `=`. وكاشفٌ يقرأ
       `>` فيها يقرأ `'limit' => 500` عتبةً صلبةً وهي **سقفُ عرضٍ** لا حدَّ
       قرار، فيسقط الحاجبُ على تسعةَ عشرَ سطحًا بريئًا. فالعاملُ هنا `>` **غيرُ
       مسبوقٍ بـ`=`** — والفرقُ بين سهمٍ ومقارنةٍ حرفٌ واحدٌ يغيّر الحكمَ كلَّه. */
    $v = '(?:' . implode('|', array_map('preg_quote', $vals)) . ')';
    $op = '(?:(?<!=)>=?|<=?|[=!]==?)';
    $re = '~(?:' . $op . '\s*' . $v . '(?![0-9A-Za-z_.]))'
        . '|(?:(?<![0-9A-Za-z_.])' . $v . '\s*' . $op . ')~';
    $hits = array(); $scanned = 0;
    foreach (array_unique($files) as $f) {
        $scanned++;
        $src = (string) file_get_contents($f);
        /* التعليقُ ليس شيفرةً — والعتبةُ في شرحٍ ليست عتبةً في منطق */
        $src = preg_replace('~/\*.*?\*/~s', '', $src);
        $src = preg_replace('~(^|\s)//[^\n]*~', ' ', $src);
        $src = preg_replace('~(^|\s)\#[^\n]*~', ' ', $src);
        foreach (explode("\n", $src) as $i => $ln) {
            if (preg_match($re, $ln, $m)) {
                $hits[] = basename($f) . ':' . ($i + 1) . ' ⇐ ' . trim($m[0]);
            }
        }
    }
    return array('n' => count($hits), 'scanned' => $scanned, 'detail' => $hits);
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑧ فصلُ الواجباتِ وآلاتُ الحالةِ وأحداثُ المرحلة
   ══════════════════════════════════════════════════════════════════════════ */

/** رموزُ ردِّ فصلِ الواجبات — كلٌّ منها **مُنفَّذٌ في خدمةٍ** لا مُعلَنٌ فقط */
function repair01_w14_sod_codes()
{
    return array(
        'ctl.deviation.classify'   => 'CLASSIFY_WITHOUT_WRITTEN_RULE',
        'ctl.deviation.refer'      => 'OWNER_CANNOT_SELF_ESCALATE',
        'gov.policy.approve'       => 'SAME_ACTOR_AUTHOR_AND_APPROVE_POLICY',
        'gov.exception.grant'      => 'SOD_EXCEPTION_WITHOUT_DURATION',
        'gov.breach.open'          => 'BREACH_ON_PURE_DEVIATION',
        'gov.breach.close'         => 'SAME_ACTOR_OPEN_AND_CLOSE_BREACH',
        'gov.investigation.open'   => 'INVESTIGATION_KIND_OUTSIDE_OWNER',
        'gov.investigation.conclude' => 'SAME_ACTOR_OPEN_AND_CONCLUDE',
        'gov.investigation.recuse' => 'CONFLICT_WITHOUT_RECUSAL',
        'gov.action.verify'        => 'SAME_ACTOR_OWN_AND_VERIFY_ACTION',
        'gov.conflict.decide'      => 'SAME_ACTOR_DISCLOSE_AND_DECIDE',
        'gov.gift.decide'          => 'SAME_ACTOR_DISCLOSE_AND_DECIDE_GIFT',
        'gov.followup.touch'       => 'GOVERNANCE_CANNOT_SET_AUDIT_RESULT',
        'rsk.trigger.raise'        => 'TRIGGER_ON_PLANNED_DOWNTIME',
        'rsk.event.record'         => 'RISK_EVENT_WITHOUT_SOURCE_REF',
        'rsk.risk.accept'          => 'ACCEPT_ABOVE_APPETITE',
        'rsk.closure.approve'      => 'SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE',
        'iaf.program.scope'        => 'AUDIT_SCOPE_SET_BY_GOVERNANCE',
        'iaf.program.review'       => 'SAME_ACTOR_PERFORM_AND_REVIEW',
        'iaf.finding.close'        => 'AUDIT_RESULT_CLOSED_OUTSIDE_IAF',
        'iaf.evidence.request'     => 'EVIDENCE_REQUEST_TO_SELF',
    );
}

/** **الكياناتُ التي يلزمها آلةُ حالة** — مقامٌ مُعلَنٌ لا رقمٌ في حاجب */
function repair01_w14_state_entities()
{
    return array(
        'ctl_deviation', 'ctl_classification_rule',
        'gov_policy', 'gov_obligation', 'gov_filing', 'gov_conflict_disclosure',
        'gov_related_party', 'gov_gift_disclosure', 'gov_conduct_ack', 'gov_sod_conflict',
        'gov_integrity_report', 'gov_investigation', 'gov_breach', 'gov_corrective_action',
        'gov_audit_followup', 'gov_committee', 'gov_request_type', 'gov_compliance_due',
        'rsk_taxonomy', 'rsk_trigger', 'rsk_event', 'rsk_closure',
        'iaf_program', 'iaf_evidence_request', 'iaf_sample', 'iaf_function_risk',
    );
}

/** أحداثُ المرحلة — مقامٌ مُعلَنٌ يقارنه الحاجبُ بعقودِ الأثرِ المسجَّلة */
function repair01_w14_stage_events()
{
    return array(
        'ctl.deviation.registered', 'ctl.deviation.classified',
        'rsk.trigger.raised', 'rsk.exposure.opened', 'rsk.event.recorded',
        'rsk.risk.accepted', 'rsk.risk.escalated', 'rsk.risk.closed',
        'gov.policy.effective', 'gov.obligation.due', 'gov.filing.submitted',
        'gov.breach.opened', 'gov.action.assigned', 'gov.action.closed',
        'gov.investigation.concluded', 'gov.integrity.triaged',
        'iaf.program.approved', 'iaf.evidence.overdue', 'iaf.finding.raised', 'iaf.finding.closed',
    );
}

/** رموزُ النطاقِ التي تُعرَض عربيًّا — مقامٌ ثابتٌ لا يخلو (‏درسُ `W12-27`) */
function repair01_w14_declared_codes()
{
    return array(
        /* التمييزُ الثلاثيّ */
        'PENDING', 'DEVIATION_ONLY', 'RISK_EXPOSURE', 'GOVERNANCE_BREACH', 'EXPOSURE_AND_BREACH',
        'registered', 'classified', 'referred', 'retained',
        /* أنواعُ التوقّفِ من قرارِ المالكِ الثاني */
        'UNPLANNED_DOWNTIME', 'PLANNED_MAINTENANCE', 'PLANNED_OVERHAUL',
        'CLIENT_STANDBY', 'OPERATIONAL_STANDBY', 'PREVENTABLE_DOWNTIME', 'TECHNICAL_CAPABILITY_DELAY',
        /* قواعدُ المحفِّز */
        'UNPLANNED_24H', 'SIMPLE_ISSUE_3D', 'RECURRENCE_3X', 'PREVENTABLE',
        'MATERIAL_PRODUCTION_IMPACT', 'TECHNICAL_CAPABILITY_GAP', 'MATERIAL_PROCUREMENT_DELAY',
        'raised', 'triaged', 'converted', 'dismissed',
        /* عائلاتُ المخاطرِ الأربع */
        'OPERATIONAL', 'CAPITAL', 'CUSTOMER_CONTRACTUAL', 'PROCUREMENT_SUPPLY',
        'event', 'near_miss', 'loss', 'recorded', 'assessed', 'linked',
        'RESIDUAL_WITHIN_LIMIT', 'CAUSE_REMOVED', 'SCOPE_ENDED', 'MERGED_INTO_OTHER',
        'proposed', 'evidenced', 'approved', 'closed', 'reopened',
        /* أساسُ حالةِ الحوكمة */
        'MANDATORY_STEP_IGNORED', 'NO_ESCALATION', 'AUTHORITY_EXCEEDED', 'MANIPULATION',
        'CONCEALMENT', 'FORGERY', 'POLICY_BREACH', 'CONTROL_BROKEN',
        'opened', 'investigated', 'action_assigned', 'remediated',
        /* التحقيقات */
        'DISCIPLINARY', 'INTEGRITY', 'OPERATIONAL_FACT', 'SPECIAL_INDEPENDENT',
        'INTEGRITY_REPORT', 'DENIAL', 'BREACH', 'AUDIT_FINDING', 'MANAGEMENT_REQUEST', 'OWNER_ORDER',
        'mandated', 'evidence', 'concluded',
        /* الحوكمةُ عمومًا */
        'draft', 'reviewed', 'effective', 'superseded', 'retired', 'active',
        'monitored', 'met', 'breached', 'due', 'prepared', 'submitted', 'acknowledged', 'late',
        'disclosed', 'mitigated', 'recused', 'rejected', 'overdue', 'exempt',
        'declared', 'verified', 'ended', 'accepted', 'returned', 'declined',
        'defined', 'detected', 'received', 'formed', 'suspended', 'dissolved',
        'gift', 'hospitality', 'travel', 'other',
        'once', 'monthly', 'quarterly', 'semiannual', 'annual', 'on_event', 'on_call', 'weekly',
        'tracking', 'escalated', 'plan_done', 'internal', 'external',
        'mitigate', 'recuse', 'reject', 'accept', 'return', 'decline',
        'assigned', 'in_progress', 'evidence_submitted', 'waived',
        /* المراجعةُ الداخلية */
        'drafted', 'executing', 'completed', 'requested', 'provided',
        'drawn', 'tested', 'pass', 'exception', 'not_applicable',
        'inquiry', 'observation', 'inspection', 'reperformance', 'analytics',
        'INDEPENDENCE_LOSS', 'COMPETENCY_GAP', 'COVERAGE_GAP', 'PLAN_DELAY', 'QUALITY_GAP', 'ACCESS_DENIED',
        'identified', 'treated', 'owner', 'audit_committee',
        /* حالاتُ العتبة */
        'OWNER_APPROVED', 'CONFIG_PENDING',
    );
}

/** الرموزُ المُعلَنةُ التي لا مسمّى عربيًّا لها في القاموسِ المركزيّ */
function repair01_w14_dict_missing(mysqli $c)
{
    if (!repair01_w14_table_exists($c, 'repair01_w6_code_dict')) { return array('DICT_TABLE_MISSING'); }
    /* **ونداءٌ واحدٌ لا مئةٌ وسبعةٌ وخمسون** — والمقامُ هو المُعلَنُ نفسُه */
    $codes = repair01_w14_declared_codes();
    $in = array();
    foreach ($codes as $x) { $in[] = "'" . $c->real_escape_string($x) . "'"; }
    $have = array();
    $r = @$c->query("SELECT raw_code FROM repair01_w6_code_dict
                      WHERE display_ar <> '' AND raw_code IN (" . implode(',', $in) . ")");
    /* ⚠ **ومقارنةُ القاعدةِ لا تفرّق بين حرفٍ كبيرٍ وصغير** — والمقارنةُ في
       الشيفرةِ تفرّق. فمِفتاحُ الخريطةِ يُوحَّد على السفلى كي تُقرأ الإجابةُ
       بالمعنى الذي أجاب به المخزنُ لا بمعنًى أشدَّ اخترعَته الأداة. */
    while ($r && $x = $r->fetch_row()) { $have[mb_strtolower($x[0])] = true; }
    $miss = array();
    foreach ($codes as $code) { if (!isset($have[mb_strtolower($code)])) { $miss[] = $code; } }
    return $miss;
}

/**
 * **⑨ نقاءُ لغةِ الواجهةِ في أسطحِ الموجة** — المعيارُ الموسَّع (‏قيدُ المالك §٨).
 * أربعُ جبهات: تشكيلٌ · نقطتانِ في اسمٍ مُصيَّر · مصطلحٌ تقنيٌّ ظاهرٌ ·
 * واسمُ جدولٍ أو مفتاحٍ في نصِّ واجهة.
 */
function repair01_w14_ui_purity($ROOT)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array(), 'scanned' => 0);
    $files = array();
    foreach (repair01_w14_new_surfaces() as $s) {
        if (is_file($ROOT . '/' . $s['route'])) { $files[] = $ROOT . '/' . $s['route']; }
    }
    $out['scanned'] = count($files);
    $tashkeel = 0; $colon = 0; $tech = 0; $tbl = 0;
    $techWords = array('Grain', 'SoT', 'Rule ID', 'Event Code', 'PK', 'FK', 'DERIVED',
                       'SELECT ', 'INSERT ', 'UPDATE ', 'company_id', 'source_row_id');
    $waveTables = repair01_w14_wave_tables();
    foreach ($files as $f) {
        $src = (string) file_get_contents($f);
        /* ⚠ **والمقامُ كلُّ نصٍّ يبلغ الشاشةَ لا ما بين وسمَين وحدَه**:
             نصُّ الحالةِ الخاليةِ وعنوانُ الرأسِ يُمرَّران **وسائطَ** إلى
             مُصيِّرٍ، فماسحٌ يقرأ ما بين `>` و`<` وحدَه يمرُّ عليها وهي
             معروضةٌ للموظّف. فالمقامُ هنا: ما بين الوسمَين **وكلُّ نصٍّ عربيٍّ
             في الشيفرةِ بعدَ نزعِ التعليق** — والتعليقُ وحدَه خارجُه. */
        $body = preg_replace('~/\*.*?\*/~s', '', $src);
        $body = preg_replace('~(^|\s)//[^\n]*~', ' ', (string) $body);
        $texts = array();
        if (preg_match_all('~>([^<>{}\n]*[\x{0600}-\x{06FF}][^<>{}\n]*)<~u', $src, $m1)) {
            $texts = array_merge($texts, $m1[1]);
        }
        if (preg_match_all('~\'([^\']*[\x{0600}-\x{06FF}][^\']*)\'~u', (string) $body, $m2)) {
            $texts = array_merge($texts, $m2[1]);
        }
        if ($texts) {
            foreach ($texts as $txt) {
                if (preg_match('~[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]~u', $txt)) { $tashkeel++; }
                if (preg_match('~[\x{0600}-\x{06FF}]\s*:\s*[\x{0600}-\x{06FF}]~u', $txt)) { $colon++; }
                foreach ($techWords as $w) { if (strpos($txt, $w) !== false) { $tech++; break; } }
                foreach ($waveTables as $t) {
                    if (preg_match('~(^|[^A-Za-z0-9_])' . preg_quote($t, '~') . '([^A-Za-z0-9_]|$)~', $txt)) {
                        $tbl++; break;
                    }
                }
            }
        }
    }
    $out['front'] = 4;
    $out['n'] = $tashkeel + $colon + $tech + $tbl;
    $out['detail'][] = "تشكيل $tashkeel · نقطتان $colon · مصطلح تقني $tech · اسم جدول $tbl";
    return $out;
}

/**
 * **⑩ القرارُ المؤجَّلُ مسجَّلٌ لا مخمَّن.**
 * جبهتان: مؤجَّلٌ بلا سؤالٍ أو سببِ حاجةٍ · ومؤجَّلٌ بنيويٌّ **بُني رغمَه بلا
 * بيانِ كيف** — فالتأجيلُ يُعلَن بأثرِه لا بذكرِه.
 */
function repair01_w14_deferred_faults(mysqli $c)
{
    $out = array('n' => 0, 'front' => 0, 'detail' => array());
    if (!repair01_w14_table_exists($c, 'repair01_w14_deferred')) {
        return array('n' => -1, 'front' => 0, 'detail' => array('TABLE_MISSING'));
    }
    $a = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM repair01_w14_deferred
            WHERE question = '' OR why_needed = '' OR src_ref = ''");
    $out['front']++; $out['n'] += $a; $out['detail'][] = "مؤجل بلا سؤال او سبب او مرجع $a";
    $b = (int) repair01_w14_one($c, "SELECT COUNT(*) FROM repair01_w14_deferred WHERE built_anyway = ''");
    $out['front']++; $out['n'] += $b; $out['detail'][] = "مؤجل بلا بيان ما بني رغمه $b";
    return $out;
}
