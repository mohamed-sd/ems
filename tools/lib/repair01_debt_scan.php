<?php
/**
 * tools/lib/repair01_debt_scan.php
 *   ماسحُ أصنافِ الدَّينِ الثمانيةِ — REPAIR01 §٧١ · **مصدرٌ واحدٌ لا مصدران**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا مكتبةٌ لا نسخةٌ في كلِّ أداة**: السقّاطةُ تعدُّ، والبوّابةُ تتحقق أنّ
 *   الأصنافَ الثمانيةَ كلَّها مقيسة. ولو نسخ كلٌّ منهما منطقَ المسحِ لتفرّقت
 *   الأرقامُ بصمتٍ عند أوّلِ تعديل — وهو ما وقع قبلًا في عدّادٍ وعارضٍ في
 *   ملفَّين. **فالماسحُ واحدٌ وله مستهلكان.**
 *
 * ◆ **والكاشفُ لا يرصد مفرداتِه هو**: كلُّ أداةٍ تحمل نمطَ الكشفِ نفسَه
 *   (`tools/` · `tests/` · هذه المكتبة) مستبعَدةٌ بالبنية — وإلّا صار الكاشفُ
 *   أكبرَ مصادرِ الدَّينِ الذي يقيسه.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (‏_CONTEXT §قواعد القياس ٣): الأنماطُ
 *   ترسو على أسماءِ الدوالِّ والجداولِ والمفاتيحِ، لا على نصٍّ عربيٍّ يظهر في
 *   رسالةِ خطأٍ فيُخضِرَّ كذبًا.
 *
 * الأصنافُ الثمانية (§٧١):
 *   RP-01 شاشةٌ بلا سجلّ          RP-05 منطقُ اعتمادٍ محليّ
 *   RP-02 مسارٌ بلا مالك          RP-06 حدثٌ خارجَ Publisher
 *   RP-03 قارئُ صلاحيةٍ محليّ      RP-07 بحثٌ خارجَ Canonical Registry
 *   RP-04 SQL خامٌّ في مسارِ إدارة  RP-08 حقلٌ مشتقٌّ بلا قاعدة
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** الأصنافُ الثمانيةُ بأسمائها ومالكيها — مصدرُ العدِّ في البوّابةِ والسقّاطة. */
function repair01_debt_classes()
{
    return array(
        'RP-01' => array(
            'label' => 'شاشةٌ حيّةٌ بلا صفٍّ في سجلِّ الشاشات',
            'owner' => 'W02 — تُسجَّل في gov_screen_cycle أو تُتقاعَد',
            'why'   => 'الشاشةُ خارجَ السجلِّ لا دورةَ لها ولا مالكَ ولا رأسَ صفحةٍ محكوم',
        ),
        'RP-02' => array(
            'label' => 'مسارُ تنقّلٍ حيٌّ بلا إدارةٍ مالكة',
            'owner' => 'W01/W02 — يُسنَد لمالكِه أو يُعطَّل',
            'why'   => 'المسارُ بلا مالكٍ يظهر لمن لا يملكه — وهو أصلُ الظهورِ المحرَّم',
        ),
        'RP-03' => array(
            'label' => 'قارئُ صلاحيةٍ محليٌّ في شاشة',
            'owner' => 'فريقُ الأمن — يُحوَّل إلى الحارسِ المركزي',
            'why'   => 'قراءةُ الدورِ من الجلسةِ داخلَ الشاشةِ تتخطّى أقفالَ المنحِ الأربعة',
        ),
        'RP-04' => array(
            'label' => 'استعلامٌ خامٌّ في مسارِ إدارة',
            'owner' => 'فريقُ البيانات — يُحوَّل إلى scopedQuery/المستودع',
            'why'   => 'عزلُ المستأجِرِ في مسارِ إدارةٍ بانضباطِ المطوِّرِ لا بالبوّابة',
        ),
        'RP-05' => array(
            'label' => 'منطقُ اعتمادٍ محليٌّ خارجَ محرّكِ الاعتماد',
            'owner' => 'فريقُ الدورة — يُحوَّل إلى approval_workflow/السلّم',
            'why'   => 'اعتمادٌ يُكتب في الشاشةِ لا يمرُّ بفصلِ الواجباتِ ولا بالسلّم',
        ),
        'RP-06' => array(
            'label' => 'كتابةُ حدثٍ خارجَ EventPublisher',
            'owner' => 'ENG-01 — تُحوَّل إلى EventPublisher::publishFact',
            'why'   => 'حدثٌ بلا Publisher بلا عطالةٍ ولا عقدِ أثرٍ ولا مروحةٍ ذرية',
        ),
        'RP-07' => array(
            'label' => 'بحثٌ حرٌّ في شاشةٍ خارجَ السجلِّ المعياريّ',
            'owner' => 'فريقُ البحث — يُحوَّل إلى main/global_search.php',
            'why'   => 'بحثٌ محليٌّ يرى ما لا يراه السجلُّ — فيتسرَّب صفٌّ خارجَ النطاق',
        ),
        'RP-08' => array(
            'label' => 'حقلٌ مشتقٌّ يُحسب في الشاشةِ بلا قاعدةٍ مسجَّلة',
            'owner' => 'W03 — يُنقل إلى مصدرِ القاعدة',
            'why'   => 'رقمٌ يُحسب في شاشتَين بطريقتَين يتفرَّق بصمتٍ عند أوّلِ تعديل',
        ),
    );
}

/** المستبعَدُ مُعلَنٌ لا مُخفى — والكاشفُ من ضمنِه. */
function repair01_debt_skips()
{
    return array('/storage/', '/vendor/', '/.git/', '/docs/', '/node_modules/',
                 '/examples/', '/tests/', '/tools/', '/database/', '/logs/',
                 '/install/', '/scripts/', '/user_guide/', '/assets/');
}

/**
 * السجلُّ المعياريُّ للبحث — **مستثنًى من RP-07 بالبنية**.
 * ◆ «الكاشفُ يرصد مفرداتِه هو»: سجلُّ البحثِ نفسُه أكثفُ ملفٍّ في النظامِ
 *   استعمالًا لـ`LIKE` مع مصطلحِ طلب — وعدُّه يجعل المرجعَ أكبرَ المخالفين.
 */
function repair01_canonical_search() { return 'main/global_search.php'; }

/** مساراتُ الإدارةِ — RP-04 يقيس فيها وحدَها. */
function repair01_admin_paths()
{
    return array('admin/', 'Settings/', 'Governance/', 'company/', 'Audit/', 'Risk/');
}

/**
 * الشاشاتُ الحيّةُ المقيسة: ملفُّ PHP إنتاجيٌّ يحمل القشرةَ (`insidebar`).
 * @return array<string,string> المسارُ النسبيُّ ⇐ المصدر
 */
function repair01_debt_files($ROOT)
{
    $ROOT = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($ROOT, '/\\'));
    $SKIP = repair01_debt_skips();
    $out  = array();
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
        $p = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
        $rel = substr($p, strlen($ROOT));                 /* يبدأ بشرطةٍ أمامية */
        $skip = false;
        foreach ($SKIP as $s) { if (strpos($rel, $s) === 0 || strpos($rel, $s) !== false) { $skip = true; break; } }
        if ($skip) { continue; }
        $src = (string) file_get_contents($p);
        if (strpos($src, 'insidebar') === false) { continue; }
        $out[ltrim($rel, '/')] = $src;
    }
    ksort($out);
    return $out;
}

/**
 * القياسُ الثمانيّ.
 *
 * @param string      $ROOT جذرُ المستودع
 * @param mysqli|null $conn اتصالٌ للقراءة — RP-01 وRP-02 يحتاجانه؛ وبدونِه
 *                          يُعادان `null` **ولا يُعادان صفرًا**: صفرٌ كاذبٌ
 *                          أسوأُ من غيابٍ مُعلَن.
 * @return array{counts:array<string,int|null>, hits:array<string,array<string,int>>, files:int}
 */
function repair01_debt_measure($ROOT, $conn = null)
{
    $files  = repair01_debt_files($ROOT);
    $counts = array();
    $hits   = array();
    foreach (array_keys(repair01_debt_classes()) as $k) { $counts[$k] = 0; $hits[$k] = array(); }

    $ADMIN = repair01_admin_paths();

    /* ── ما يُقرأ من القاعدةِ مرّةً واحدة ─────────────────────────────────── */
    $registered = null;   /* أسماءُ ملفّاتِ الشاشاتِ المسجَّلة */
    $ownedFile  = null;   /* ملفُّ شاشةٍ ⇐ له إدارةٌ مالكةٌ غيرُ فارغة */
    if ($conn instanceof mysqli) {
        $registered = array(); $ownedFile = array();
        $q = $conn->query("SELECT screen_file, dept_name FROM gov_screen_cycle");
        while ($q && $r = $q->fetch_row()) {
            $bn = strtolower(basename((string) $r[0]));
            if ($bn === '' || $bn === '.php') { continue; }
            $registered[$bn] = true;
            if (trim((string) $r[1]) !== '') { $ownedFile[$bn] = true; }
        }
    }

    foreach ($files as $rel => $src) {
        $isAdmin = false;
        foreach ($ADMIN as $a) { if (strpos($rel, $a) === 0) { $isAdmin = true; break; } }

        /* RP-01 — شاشةٌ حيّةٌ بلا صفٍّ في السجل. ملفٌّ واحدٌ = دَينٌ واحد. */
        if ($registered !== null) {
            $bn = strtolower(basename($rel));
            if (!isset($registered[$bn])) { $counts['RP-01']++; $hits['RP-01'][$rel] = 1; }
        }

        /* RP-03 — قارئُ صلاحيةٍ محليّ: الدورُ يُقرأ من الجلسةِ **ويُقارَن** داخلَ
           الشاشة. والقيدُ «يُقارَن» مقصود: عرضُ اسمِ الدورِ ليس قرارَ صلاحية،
           والدَّينُ هو القرار. والمفتاحُ مُعشَّشٌ في هذا النظام
           (`$_SESSION['user']['role']`) — وقياسُ الجذرِ وحدَه يعطي صفرًا كاذبًا. */
        $c = preg_match_all(
            '~\$_SESSION\s*\[\s*[\'"](?:user[\'"]\s*\]\s*\[\s*[\'"])?(?:role|role_id|role_name|user_role|is_super_admin)[\'"]\s*\]'
            . '\s*(?:\?\?\s*[^;,)]{0,20})?\s*(?:===|==|!==|!=|<=|>=|<|>)'
            . '|in_array\s*\(\s*[^,]{0,60}\$_SESSION\s*\[\s*[\'"](?:user[\'"]\s*\]\s*\[\s*[\'"])?(?:role|role_id)[\'"]~i', $src);
        if ($c) { $counts['RP-03'] += $c; $hits['RP-03'][$rel] = $c; }

        /* RP-04 — استعلامٌ خامٌّ في مسارِ إدارة: `->query("…SELECT/UPDATE/…`
           في admin/Settings/Governance/company/Audit/Risk وحدَها. */
        if ($isAdmin) {
            $c = preg_match_all(
                '~->\s*query\s*\(\s*["\'][^"\']*\b(?:SELECT|UPDATE|INSERT\s+INTO|DELETE\s+FROM)\b~i', $src);
            if ($c) { $counts['RP-04'] += $c; $hits['RP-04'][$rel] = $c; }
        }

        /* RP-05 — منطقُ اعتمادٍ محليّ: كتابةُ حالةِ اعتمادٍ في الشاشةِ نفسِها
           بدل محرّكِ الاعتماد. ثلاثةُ مراسٍ لا واحد — فمرسًى واحدٌ يُعمي القياسَ
           عن الصيغتَين الأخريَين: عمودُ اعتمادٍ صريح · قيمةُ اعتمادٍ حرفية
           (إنجليزيةً أو عربية) · تحديثُ حالةٍ بعلامةِ استفهامٍ داخلَ الشاشة. */
        $c = preg_match_all(
            '~\bSET\b[^;\'"]{0,200}\b(?:approval_status|approve_status|is_approved|approved_by|approved_at)\s*=~i', $src)
           + preg_match_all(
            '~\bstatus\s*=\s*[\'"]?(?:approved|APPROVED|معتمد|مُعتمد)~iu', $src)
           + preg_match_all(
            '~\bUPDATE\b[^;]{0,200}\bstatus\s*=\s*\?~i', $src);
        if ($c) { $counts['RP-05'] += $c; $hits['RP-05'][$rel] = $c; }

        /* RP-06 — حدثٌ خارجَ Publisher: كتابةٌ مباشرةٌ في دفترِ الحقائقِ أو
           في دفترِ الوقائعِ المالية. الجذرُ المحايد ADR-15. */
        $c = preg_match_all(
            '~INSERT\s+(?:IGNORE\s+)?INTO\s+`?(?:ems_business_events|fin_financial_events)`?~i', $src);
        if ($c) { $counts['RP-06'] += $c; $hits['RP-06'][$rel] = $c; }

        /* RP-07 — بحثٌ حرٌّ خارجَ السجلِّ المعياريّ: الشاشةُ تقرأ مصطلحَ بحثٍ
           من الطلبِ وتبني به شرطَ LIKE بنفسِها. الشرطان معًا لا أحدُهما —
           فمرشِّحُ جدولٍ ليس بحثًا حرًّا. */
        if ($rel !== repair01_canonical_search()
            && preg_match('~\$_(?:GET|POST|REQUEST)\s*\[\s*[\'"](?:q|search|term|keyword|kw)[\'"]\s*\]~i', $src)
            && preg_match('~\bLIKE\b~i', $src)) {
            $c = preg_match_all('~\bLIKE\b~i', $src);
            $counts['RP-07'] += $c; $hits['RP-07'][$rel] = $c;
        }

        /* RP-08 — حقلٌ مشتقٌّ يُحسب في الشاشة: تعبيرٌ مشتقٌّ مُسمّى بـAS داخلَ
           استعلامٍ في الشاشة. القاعدةُ مكانُها مصدرُ القاعدةِ لا الشاشة. */
        $c = preg_match_all(
            '~\b(?:SUM|COUNT|AVG|MIN|MAX|COALESCE|IFNULL|ROUND|DATEDIFF|TIMESTAMPDIFF|GROUP_CONCAT|CASE)\s*\([^;]{0,400}?\)\s+AS\s+`?[A-Za-z_][A-Za-z0-9_]*`?~is', $src);
        if ($c) { $counts['RP-08'] += $c; $hits['RP-08'][$rel] = $c; }
    }

    /* RP-02 — مسارُ تنقّلٍ حيٌّ بلا إدارةٍ مالكة (يُقاس من السجلِّ لا من الشيفرة). */
    if ($conn instanceof mysqli && $ownedFile !== null) {
        $n = 0;
        $q = $conn->query("SELECT DISTINCT route FROM nav_items WHERE active = 1 AND route <> ''");
        while ($q && $r = $q->fetch_row()) {
            $route = (string) $r[0];
            $path  = preg_replace('~[?#].*$~', '', $route);
            $bn    = strtolower(basename($path));
            if ($bn === '' || substr($bn, -4) !== '.php') { continue; }
            if (!isset($ownedFile[$bn])) { $n++; $hits['RP-02'][$route] = 1; }
        }
        $counts['RP-02'] = $n;
    } else {
        $counts['RP-01'] = null;
        $counts['RP-02'] = null;
    }

    return array('counts' => $counts, 'hits' => $hits, 'files' => count($files));
}
