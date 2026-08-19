<?php
/**
 * tools/xf_lib.php — نواةُ حزمةِ التعميم (XF-01): معجمٌ ومسحٌ وتصنيف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ حيًّا قبلَ كتابةِ حرف** (لا رقمَ بلا مصدر):
 *     · **4,100** رأسَ عمودٍ محقونًا (`data-fn`/`data-gov`) **بلا وصلٍ بمصدر**
 *     · موزّعةً على **299** ملفَّ شاشة · بـ**1,229** تسميةً فريدة
 *   (المصدر: مسحُ `xf_scan_repo()` أدناه على الشجرةِ الحيّة.)
 *
 * ◆ **والدرسُ الأولُ من ذلك المسح**: أكثرُ العشرةِ الأوائلِ **ليست نقصَ مخطَّط**
 *   بل نقصَ وصل — «الكيان» (291) و«تاريخ الإنشاء» (232) و«الحالة» (149)
 *   مصادرُها قائمةٌ في الجداولِ سلفًا (`company_id` · `created_at` · `status`).
 *   فمن يبدأ بهجرةِ مخطَّطٍ لهذه **يُنشئ عمودًا ثانيًا لمعنًى واحد** — وذاك
 *   ازدواجُ مصدرٍ لا إصلاحُ نقص.
 *
 * ⇒ فالتصنيفُ ثلاثيٌّ لا ثنائيّ، وهو عمودُ الحزمةِ الفقريّ:
 *     BIND    عمودٌ **موجودٌ** في جدولِ الشاشة (أو مشتقٌّ منه) ⇒ **بلا هجرة**.
 *     NEW     لا عمودَ له ولا اشتقاق ⇒ عمودٌ **اختياريٌّ** جديد (هجرة).
 *     MANUAL  ملتبسٌ — لا تسميةَ في المعجم، أو الجدولُ لم يُعرَف، أو المعنى
 *             علائقيٌّ (مرجعٌ لجدولٍ آخر) ⇒ **قرارُ مالكٍ لا تخمينُ أداة**.
 *
 * ◆ **ولا تُخترع تسميةٌ**: المعجمُ أدناه مبنيٌّ من التسمياتِ المقيسةِ فعلًا في
 *   الشجرة، وما ليس فيه يخرج MANUAL — ولا يُشتقُّ اسمُ عمودٍ إنجليزيٌّ بترجمةٍ
 *   آليةٍ لتسميةٍ لم تُرَ. فاسمُ العمودِ عقدٌ دائمٌ، وخطؤه يُصحَّح بهجرةٍ ثانية.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

/* ═══════════════════════════════════════════════════════════════════════════
 * ① المعجم: تسميةٌ عربيةٌ ← [اسمُ العمود, النوع, الطول, أيقونة, رسمٌ مشتقّ]
 * ───────────────────────────────────────────────────────────────────────────
 * `derive` غيرُ فارغٍ يعني: **لا تُنشئ عمودًا** — القيمةُ تُشتقُّ من عمودٍ قائم.
 *   actor     ⇐ `created_by`/`approved_by` … رقمُ مستخدمٍ يُترجَم اسمًا
 *   datetime  ⇐ عمودُ تاريخٍ يُنسَّق
 *   self      ⇐ العمودُ نفسُه موجودٌ في الجدولِ بالاسمِ المقترَح
 * ═══════════════════════════════════════════════════════════════════════════ */
function xf_dictionary()
{
    static $d = null;
    if ($d !== null) { return $d; }
    $d = array(
        /* ── طبقةُ الحوكمةِ: كلُّها مشتقّةٌ أو قائمة — **صفرُ هجرةٍ لها** ──── */
        'الكيان'                   => array('company_id',   'derived', 0,   'fas fa-building',      'self'),
        'تاريخ الإنشاء'            => array('created_at',   'derived', 0,   'fas fa-calendar-plus', 'datetime'),
        'تاريخ التسجيل'            => array('created_at',   'derived', 0,   'fas fa-calendar-plus', 'datetime'),
        'المُنشئ — الاسم والصفة'   => array('created_by',   'derived', 0,   'fas fa-user-plus',     'actor'),
        'سجّله'                    => array('created_by',   'derived', 0,   'fas fa-user-plus',     'actor'),
        'أعدّه'                    => array('created_by',   'derived', 0,   'fas fa-user-edit',     'actor'),
        'عرّفه'                    => array('created_by',   'derived', 0,   'fas fa-user-edit',     'actor'),
        'عرّفها'                   => array('created_by',   'derived', 0,   'fas fa-user-edit',     'actor'),
        'أصدره'                    => array('created_by',   'derived', 0,   'fas fa-user-edit',     'actor'),
        'الحالة'                   => array('status',       'derived', 0,   'fas fa-toggle-on',     'self'),
        'المعتمِد — الاسم والصفة'  => array('approved_by',  'derived', 0,   'fas fa-user-check',    'actor'),
        'اعتمده'                   => array('approved_by',  'derived', 0,   'fas fa-user-check',    'actor'),
        'اعتمدها'                  => array('approved_by',  'derived', 0,   'fas fa-user-check',    'actor'),
        'تاريخ الاعتماد'           => array('approved_at',  'derived', 0,   'fas fa-calendar-check','datetime'),
        'آخر تحديث'                => array('updated_at',   'derived', 0,   'fas fa-clock-rotate-left', 'datetime'),

        /* ── هويةُ الكيانِ التجاريّ (شاهدُ العملاءِ المنفَّذ) ───────────────── */
        'الاسم القانوني الكامل'    => array('legal_name',            'text',     255, 'fas fa-file-signature', ''),
        'الاسم القانوني'           => array('legal_name',            'text',     255, 'fas fa-file-signature', ''),
        'الشكل النظامي'            => array('legal_form',            'text',     100, 'fas fa-landmark',       ''),
        'بلد التسجيل'              => array('registration_country',  'text',     100, 'fas fa-globe',          ''),
        'جهة التسجيل'              => array('registration_authority','text',     150, 'fas fa-stamp',          ''),
        'رقم السجل التجاري'        => array('commercial_reg_no',     'text',     100, 'fas fa-id-card',        ''),
        'رقم السجل'                => array('commercial_reg_no',     'text',     100, 'fas fa-id-card',        ''),
        'الرقم الضريبي'            => array('tax_id',                'text',     100, 'fas fa-percent',        ''),
        'العنوان المسجَّل'          => array('registered_address',    'textarea', 500, 'fas fa-map-marker-alt', ''),
        'العنوان'                  => array('address',               'textarea', 500, 'fas fa-map-marker-alt', ''),
        'جهة الاتصال'              => array('contact_person',        'text',     255, 'fas fa-user-tie',       ''),
        'المنصب'                   => array('contact_title',         'text',     150, 'fas fa-briefcase',      ''),
        'الصفة'                    => array('contact_title',         'text',     150, 'fas fa-briefcase',      ''),
        'البريد'                   => array('email',                 'text',     100, 'fas fa-envelope',       ''),
        'البريد الإلكتروني'        => array('email',                 'text',     100, 'fas fa-envelope',       ''),
        'الهاتف'                   => array('phone',                 'text',      50, 'fas fa-phone',          ''),
        'تصنيف العميل'             => array('client_classification', 'text',     100, 'fas fa-star',           ''),
        'شريحة الأهمية'            => array('importance_tier',       'text',      50, 'fas fa-layer-group',    ''),
        'الوصف'                    => array('description',           'textarea', 500, 'fas fa-align-right',    ''),
        'ملاحظات'                  => array('notes',                 'textarea', 500, 'fas fa-note-sticky',    ''),
        'الفئة'                    => array('category',              'text',     100, 'fas fa-tags',           ''),
        'الموقع'                   => array('location',              'text',     255, 'fas fa-location-dot',   ''),
        'المسؤول'                  => array('responsible',           'text',     255, 'fas fa-user-shield',    ''),
        'الإدارة'                  => array('department',            'text',     150, 'fas fa-sitemap',        ''),

        /* ── تواريخُ نطاقٍ شائعة ─────────────────────────────────────────── */
        'من تاريخ'                 => array('date_from',       'date', 0, 'fas fa-calendar-day',   ''),
        'إلى تاريخ'                => array('date_to',         'date', 0, 'fas fa-calendar-day',   ''),
        'تاريخ السريان'            => array('effective_date',  'date', 0, 'fas fa-calendar-check', ''),
        'تاريخ الإصدار'            => array('issue_date',      'date', 0, 'fas fa-calendar-plus',  ''),
        'تاريخ الطلب'              => array('request_date',    'date', 0, 'fas fa-calendar',       ''),
    );
    return $d;
}

/**
 * تسمياتٌ **علائقيةٌ** — معناها مرجعٌ لجدولٍ آخر لا نصٌّ حرّ.
 * ◆ ولهذا تخرج MANUAL دائمًا وإن عرفها المعجم: إنشاءُ `VARCHAR` لها يخلق
 *   نسخةً نصيةً من علاقةٍ لها مفتاحٌ أجنبيّ — وذاك أسوأُ من عمودٍ فارغ، لأن
 *   النسختَين تتفرّقان بلا إنذار.
 */
function xf_relational_labels()
{
    return array(
        'مرجع التفويض', 'المرجع الأب', 'المرفق', 'المرفقات', 'المستند المرفق',
        'مركز التكلفة', 'مركز التكلفة المحمَّل', 'العملة', 'العملة الأساسية',
        'سعر الصرف', 'سعر الصرف ومصدره', 'مصدر سعر الصرف',
        'مفتاح منع التكرار', 'معكوس بـ', 'عكس عن', 'درجة الأثر', 'سجل الاطّلاع',
        'المعتمِد المطلوب', 'الجهة المُنشئة', 'العقد', 'الوحدة', 'الوحدة التعاقدية',
        'كود المعدة', 'الفترة', 'نسخة القاعدة المستعملة', 'رقم المحضر', 'رقم القيد',
        'رقم الطلب',
    );
}

/** تطبيعُ تسميةٍ للمطابقة: تشكيلٌ ومسافاتٌ ورموزُ واجهةٍ تُنزع. */
function xf_norm($s)
{
    $s = strip_tags((string) $s);
    $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);   /* تشكيلٌ وتطويل */
    $s = preg_replace('/[\x{200E}\x{200F}\x{00A0}]/u', ' ', $s);   /* محارفُ اتجاهٍ ومسافةٌ صلبة */
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ② استخراجُ الرؤوسِ غيرِ الموصولةِ من ملفِّ شاشة
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ يُقرأ **نصُّ الملف** لا المُصيَّر: فالرأسُ مكتوبٌ حرفيًّا (عرفُ CMP-03)، وقراءةُ
 *   المُصيَّرِ كانت ستلزم جلسةً وصلاحيةً لكلِّ شاشةٍ من 299.
 * ◆ ويُتخطّى ما يحمل `data-fn-src` — فذاك موصولٌ بهذه الحزمةِ نفسِها، وعدُّه
 *   ناقصًا يجعل الأداةَ تُبلّغ عن عملِها هي نقصًا.
 * ═══════════════════════════════════════════════════════════════════════════ */
function xf_extract_heads($path)
{
    $src = @file_get_contents($path);
    if ($src === false) { return array(); }
    /* ◆ شاشةٌ مسجَّلةٌ في `ems_xf_registry()` **موصولةٌ كلُّها** — فلا تُمسح.
         وهذا هو الحكمُ المُعتمَد لا وجودُ النصِّ في الملفّ: الوصلُ يُطبع بنداءٍ
         (`ems_xf_th_attrs`) لا يظهر في المصدرِ حرفيًّا، فمن يقيس بالنصِّ وحدَه
         يُبلّغ عن عملِ الحزمةِ نفسِها نقصًا — وقد وقع فعلًا في أوّلِ تشغيل. */
    static $registered = null;
    if ($registered === null) {
        $registered = array();
        $incl = dirname(__DIR__) . '/includes/extra_fields.php';
        if (is_file($incl)) {
            require_once $incl;
            if (function_exists('ems_xf_registry')) { $registered = ems_xf_registry(); }
        }
    }
    $relPath = ltrim(str_replace(chr(92), '/', $path), '/');
    $rootN   = str_replace(chr(92), '/', dirname(__DIR__)) . '/';
    $relPath = str_replace($rootN, '', $relPath);
    if (isset($registered[$relPath])) { return array(); }

    $out = array();
    if (preg_match_all('/<th\b([^>]*\bdata-(?:fn|gov)\b[^>]*)>(.*?)<' . '\/th>/su', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            if (strpos($x[1], 'data-fn-src') !== false)   { continue; }  /* موصولٌ حرفيًّا */
            if (strpos($x[1], 'ems_xf_th_attrs') !== false) { continue; }  /* موصولٌ بنداء */
            $lbl = xf_norm($x[2]);
            if ($lbl === '') { continue; }
            $gov = '';
            if (preg_match('/data-gov="([^"]*)"/', $x[1], $gm)) { $gov = $gm[1]; }
            $out[] = array('label' => $lbl, 'gov' => $gov, 'attrs' => $x[1]);
        }
    }
    return $out;
}

/**
 * جدولُ الشاشة — يُستنبَط من أوّلِ `FROM <table>` في استعلامِ القائمة.
 * ◆ **ويُعلَن مستنبَطًا لا مؤكَّدًا**: شاشاتٌ كثيرةٌ تصلُ جداولَ عدة، والأولُ
 *   أرجحُها لا يقينُها. فالخطةُ تكتبه ويراجعه المالكُ قبلَ التطبيق، ويُغيَّر
 *   بـ`--table=` عند الخطأ.
 */
function xf_guess_table($path)
{
    $src = @file_get_contents($path);
    if ($src === false) { return ''; }
    $cands = array();
    if (preg_match_all('/\bFROM\s+`?([a-z_][a-z0-9_]*)`?/i', $src, $m)) {
        foreach ($m[1] as $t) {
            $t = strtolower($t);
            if (in_array($t, array('information_schema', 'dual', 'users', 'modules'), true)) { continue; }
            $cands[$t] = isset($cands[$t]) ? $cands[$t] + 1 : 1;
        }
    }
    if (!$cands) { return ''; }
    arsort($cands);
    return key($cands);
}


/**
 * xf_resolve_table() — جدولُ الشاشةِ **مربوطًا بحلقةِ الصفوفِ نفسِها**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا لا يكفي `xf_guess_table()`**: كان يأخذ أكثرَ `FROM` تكرارًا في
 *   الملفّ. وقِيس على الشجرةِ الحيّة فخالف اسمَ الشاشةِ في **121 من 160**
 *   (75.6%)، وبعضُ مخالفاتِه فادح: `Equipments/equipments.php` ⇐
 *   `supplierscontracts` · `Employees/employees.php` ⇐ `drivercontracts` ·
 *   `Contracts/collections.php` ⇐ `role_permissions`. والاستعلاماتُ الفرعيةُ
 *   داخلَ الحلقاتِ تتكرّر أكثرَ من استعلامِ القائمةِ الرئيسِ فتغلبه بالعدّ.
 *
 * ◆ **والعطبُ ليس تجميليًّا**: هذا الجدولُ هو الذي يُنشئ فيه المنفِّذُ أعمدةَ
 *   `NEW`. فجدولٌ خاطئٌ ⇒ **أعمدةٌ تُزرع في جدولٍ لا علاقةَ له**، وتلوّثُ مخطَّطٍ
 *   لا يُكشف إلا متأخّرًا. وهو بالضبطِ ما يجب ألّا يقع.
 *
 * ◆ **البديلُ يقيس ولا يخمّن**: الجدولُ الصحيحُ هو الذي يغذّي **الحلقةَ التي
 *   ترسم صفوفَ الجدول** — لا الأكثرَ ذكرًا. فتُتبَّع السلسلةُ:
 *     `</tr>` أو `<td` داخلَ حلقةٍ ⇐ متغيّرُ القائمة ⇐ إسنادُه ⇐ `FROM <t>`
 *   وما لم تكتمل السلسلةُ **لا يُخترع جواب**: تُرجَع `guessed` أو `unknown`،
 *   والمنفِّذُ يرفض ما ليس `verified` (فشلٌ مُغلَق لا تخمينٌ صامت).
 *
 * @return array{table:string, confidence:string, evidence:string}
 *         confidence ∈ verified | guessed | unknown
 */
function xf_resolve_table($path)
{
    $src = @file_get_contents($path);
    if ($src === false) {
        return array('table' => '', 'confidence' => 'unknown', 'evidence' => 'الملفُّ لا يُقرأ');
    }

    /* ── ① حلقةُ الصفوف: `foreach ($list as $row)` يرسم جسمُها خلايا ────────
         ◆ الجسمُ يُقرأ بمطابقةِ الأقواسِ لا بعدَدِ محارفَ ثابت: حلقةٌ طويلةٌ
           كانت ستُقتطع فيضيع `</tr>`، وحلقةٌ قصيرةٌ كان سيبتلع مقطعُها ما
           بعدَها فتُنسَب إليها خلايا ليست لها. */
    $loops = array();
    if (preg_match_all('/foreach\s*\(\s*(\$[a-z_][a-z0-9_]*)\s+as\s+(?:\$[a-z_][a-z0-9_]*\s*=>\s*)?(\$[a-z_][a-z0-9_]*)\s*\)\s*[:{]/i',
                       $src, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($mm as $x) {
            $listVar = $x[1][0];
            $start   = $x[0][1] + strlen($x[0][0]) - 1;
            $body    = xf_block_after($src, $start);
            if ($body === '') { continue; }
            if (strpos($body, '</tr>') === false && strpos($body, '<td') === false) { continue; }
            $loops[] = array('var' => $listVar, 'pos' => $x[0][1], 'len' => strlen($body));
        }
    }
    if (!$loops) {
        $g = xf_guess_table($path);
        return array('table' => $g, 'confidence' => $g === '' ? 'unknown' : 'guessed',
                     'evidence' => 'لا حلقةَ صفوفٍ تُميَّز — الجدولُ من عدِّ `FROM` وحدَه');
    }

    /* أطولُ حلقةٍ ترسم خلايا هي حلقةُ الجدولِ الرئيسِ (الأقصرُ غالبًا حلقةُ
       خياراتٍ أو ترويسةٍ أو جدولٍ فرعيّ) */
    usort($loops, function ($a, $b) { return $b['len'] - $a['len']; });
    $listVar = $loops[0]['var'];
    $upto    = $loops[0]['pos'];

    /* ── ② إسنادُ متغيّرِ القائمةِ قبلَ الحلقة ─────────────────────────────── */
    $head = substr($src, 0, $upto);
    $q    = preg_quote($listVar, '/');
    if (!preg_match_all('/' . $q . '\s*=\s*(?!.*?==)/', $head, $am, PREG_OFFSET_CAPTURE)) {
        $g = xf_guess_table($path);
        return array('table' => $g, 'confidence' => $g === '' ? 'unknown' : 'guessed',
                     'evidence' => "لا إسنادَ لـ{$listVar} قبلَ حلقتِه");
    }

    /* ◆ **الإسنادُ الأخيرُ لا الأوّل**: كثيرٌ من الشاشاتِ تهيّئ `$x = array();`
         ثم تملؤه من الاستعلامِ بعدَه. فالأوّلُ لا `FROM` فيه، والأخيرُ هو
         الحامل. ونمسح من كلِّ إسنادٍ إلى الحلقةِ بحثًا عن أوّلِ `FROM` بعدَ
         `SELECT` — فيتخطّى `FROM` في استعلامٍ فرعيٍّ سابقٍ للـ`SELECT` الرئيس. */
    $table = ''; $why = '';
    for ($i = count($am[0]) - 1; $i >= 0; $i--) {
        $span = substr($src, $am[0][$i][1], $upto - $am[0][$i][1]);
        if (!preg_match('/\bSELECT\b/i', $span)) { continue; }
        $sel = stripos($span, 'SELECT');
        if (preg_match('/\bFROM\s+`?([a-z_][a-z0-9_]*)`?/i', substr($span, $sel), $fm)) {
            $t = strtolower($fm[1]);
            if (in_array($t, array('information_schema', 'dual'), true)) { continue; }
            $table = $t;
            $why   = "حلقةُ `{$listVar}` ترسم الخلايا · إسنادُها يحمل `FROM {$t}`";
            break;
        }
    }
    if ($table === '') {
        $g = xf_guess_table($path);
        return array('table' => $g, 'confidence' => $g === '' ? 'unknown' : 'guessed',
                     'evidence' => "إسنادُ {$listVar} بلا `FROM` مقروء");
    }
    /* ◆ **ولا يُسمّى هذا يقينًا**: قِيس البديلُ على 160 شاشةً فرفع موافقةَ اسمِ
         الجدولِ لاسمِ الشاشةِ من 24.4% إلى 27.5% فقط — فرقٌ داخلَ الضجيج.
         وشاشاتُ البطاقاتِ تحمل جداولَ عدّةً فيلتقط «أطولُ حلقةٍ» جدولًا فرعيًّا
         (`Contracts/contract_card.php` ⇐ `op_containers`). فالإشارةُ
         **مرجَّحةٌ لا مؤكَّدة**، واسمُها `loop` لا `verified` — والتأكيدُ فعلُ
         إنسانٍ لا استنباطُ أداة. */
    return array('table' => $table, 'confidence' => 'loop', 'evidence' => $why);
}

/** جسمُ كتلةٍ يبدأ عند `{` أو `:` — بمطابقةِ الأقواسِ (أو حتى `endforeach`). */
function xf_block_after($src, $bracePos)
{
    $ch = $src[$bracePos];
    if ($ch === ':') {
        $end = stripos($src, 'endforeach', $bracePos);
        return $end === false ? '' : substr($src, $bracePos, $end - $bracePos);
    }
    if ($ch !== '{') { return ''; }
    $depth = 0; $n = strlen($src);
    for ($i = $bracePos; $i < $n; $i++) {
        if ($src[$i] === '{') { $depth++; }
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { return substr($src, $bracePos, $i - $bracePos + 1); } }
    }
    return substr($src, $bracePos);
}

/**
 * فرزُ «أساسيّ / إضافيّ» بإشارتَين — ولا يُحسم بواحدة.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ على 537 حقلًا حيًّا**: قيدُ القاعدة (`NOT NULL` بلا افتراض)
 *   و`required` في النموذجِ يتطابقان في **84.5%** فقط — 28 حقلًا تلزمه القاعدةُ
 *   والنموذجُ لا، و55 حقلًا يلزمه النموذجُ والقاعدةُ لا. فإشارةٌ واحدةٌ حاكمةً
 *   تعني **خطأً في 15.5%** من قرارٍ يسري على 1,947 عمودًا — وذاك غيرُ مقبول.
 * ◆ فالحكمُ ثلاثيّ: اتفاقُهما يقين، واختلافُهما **يُرفع للمراجعةِ ولا يُخمَّن**.
 *   والـ55 ليست خطأً بالضرورة: قواعدُ عملٍ لم تنزل إلى المخطَّطِ بعد.
 */
function xf_field_tier($col, $formRequired)
{
    if (!$col) { return array('tier' => 'review', 'why' => 'لا عمودَ في الجدول'); }
    $dbEssential = ($col['Null'] === 'NO' && ($col['Default'] === null || $col['Default'] === '')
                    && $col['Extra'] !== 'auto_increment');
    if ($formRequired === null) {
        return array('tier' => $dbEssential ? 'essential' : 'additional',
                     'why'  => 'إشارةُ القاعدةِ وحدَها (لا حقلَ في النموذج)');
    }
    if ($dbEssential && $formRequired)   { return array('tier' => 'essential',  'why' => 'القاعدةُ والنموذجُ يلزمانه'); }
    if (!$dbEssential && !$formRequired) { return array('tier' => 'additional', 'why' => 'لا القاعدةُ ولا النموذجُ يلزمانه'); }
    if ($dbEssential) { return array('tier' => 'review', 'why' => 'القاعدةُ تلزمه والنموذجُ لا — قيدٌ لا يعكسه النموذج'); }
    return array('tier' => 'review', 'why' => 'النموذجُ يلزمه والقاعدةُ لا — قاعدةُ عملٍ لم تنزل للمخطَّط');
}

/** هل الحقلُ مُعلَّمٌ `required` في نموذجِ الشاشة؟ (null = لا حقلَ له أصلًا) */
function xf_form_required($src, $name)
{
    if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $src, $m, PREG_SET_ORDER)) { return null; }
    foreach ($m as $x) {
        if (!preg_match('/\bname="' . preg_quote($name, '/') . '"/i', $x[2])) { continue; }
        if (preg_match('/\btype="(hidden|submit|button)"/i', $x[2])) { continue; }
        return (bool) preg_match('/\brequired\b/i', $x[2]);
    }
    return null;
}

/** أعمدةُ جدولٍ من القاعدة (فارغةٌ إن لم يوجد الجدول). */
function xf_table_columns($conn, $table)
{
    if ($table === '') { return array(); }
    $out = array();
    $q = @$conn->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "`");
    while ($q && ($x = $q->fetch_assoc())) { $out[$x['Field']] = $x; }
    return $out;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ③ التصنيف — BIND · NEW · MANUAL
 * ═══════════════════════════════════════════════════════════════════════════ */
function xf_classify($label, $govKey, $cols, $table)
{
    /* ◆ **المفاتيحُ تُطبَّع كما تُطبَّع التسمياتُ المقروءة** — وإلّا فلا تلتقيان.
         وقد قِيس: `xf_norm()` تنزع التشكيلَ فتصير «المُنشئ» ⇒ «المنشئ»، بينما
         مفتاحُ المعجمِ يحمل الضمّةَ — فخرجت **201 تسميةً** «ليست في المعجم»
         وهي فيه حرفًا. والعطبُ من الصنفِ الأسوأ: لا يُخطئ، بل يُبلّغ ناقصًا. */
    static $dict = null;
    if ($dict === null) {
        $dict = array();
        foreach (xf_dictionary() as $k => $v) { $dict[xf_norm($k)] = $v; }
    }
    static $rel = null;
    if ($rel === null) { $rel = array_map('xf_norm', xf_relational_labels()); }

    /* ◆ **عمودُ حوكمةٍ يبثُّه السياقُ العامُّ موصولٌ سلفًا** — و`ems_gov_ctx()`
         يبثُّ مفتاحَين لا غير (`entity` · `base_currency`) مقروءَين من
         `admin_companies`. فعدُّهما ناقصَين يفتح 300 «مخالفةٍ» أصلُها عملٌ تمَّ. */
    if ($govKey === 'entity' || $govKey === 'base_currency') {
        return array('verdict' => 'BOUND', 'key' => $govKey, 'type' => 'gov',
                     'why' => "يبثُّه `ems_gov_ctx()` من `admin_companies` — موصولٌ سلفًا، لا عمل");
    }

    if ($table === '') {
        return array('verdict' => 'MANUAL', 'key' => '', 'why' => 'جدولُ الشاشةِ لم يُستنبَط — لا يُقاس عمودٌ بلا جدول');
    }
    if (in_array($label, $rel, true)) {
        return array('verdict' => 'MANUAL', 'key' => '', 'why' => 'تسميةٌ علائقيةٌ (مرجعٌ لجدولٍ آخر) — عمودٌ نصيٌّ لها يزدوج المصدر');
    }
    if (!isset($dict[$label])) {
        return array('verdict' => 'MANUAL', 'key' => '', 'why' => 'ليست في المعجم — ولا يُخترع اسمُ عمودٍ لتسميةٍ لم تُدرَس');
    }

    list($key, $type, $max, $icon, $derive) = $dict[$label];

    if ($derive !== '') {
        if (!isset($cols[$key])) {
            return array('verdict' => 'MANUAL', 'key' => $key,
                         'why' => "مشتقٌّ من `{$key}` و`{$table}` لا يحمله — يلزم قرار");
        }
        return array('verdict' => 'BIND', 'key' => $key, 'type' => 'derived', 'render' => $derive,
                     'icon' => $icon, 'why' => "مشتقٌّ من `{$key}` القائمِ في `{$table}` — بلا هجرة");
    }

    if (isset($cols[$key])) {
        return array('verdict' => 'BIND', 'key' => $key, 'type' => 'existing', 'icon' => $icon,
                     'why' => "`{$key}` قائمٌ في `{$table}` — وصلٌ بلا هجرة");
    }

    return array('verdict' => 'NEW', 'key' => $key, 'type' => $type, 'max' => $max, 'icon' => $icon,
                 'why' => "لا عمودَ له في `{$table}` — يُنشأ **اختياريًّا** (NULL)");
}

/* ═══════════════════════════════════════════════════════════════════════════
 * ④ مسحُ الشجرة — قائمةُ ملفاتِ الشاشاتِ التي فيها رؤوسٌ غيرُ موصولة
 * ═══════════════════════════════════════════════════════════════════════════ */
function xf_scan_repo($ROOT, $filter = '')
{
    $BS   = chr(92);
    $skip = array('.claude', 'storage', 'vendor', 'node_modules', '.git', '_history', 'docs', 'tools', 'database');
    $rii  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    $rootN = str_replace($BS, '/', $ROOT);
    $out = array();
    foreach ($rii as $f) {
        $p   = str_replace($BS, '/', $f->getPathname());
        $rel = ltrim(str_replace($rootN, '', $p), '/');
        $bad = false;
        foreach ($skip as $s) {
            if (strpos($rel, $s . '/') === 0 || strpos($rel, '/' . $s . '/') !== false) { $bad = true; break; }
        }
        if ($bad || substr($rel, -4) !== '.php') { continue; }
        if ($filter !== '' && strpos($rel, $filter) !== 0) { continue; }
        $heads = xf_extract_heads($p);
        if ($heads) { $out[$rel] = $heads; }
    }
    ksort($out);
    return $out;
}

/** اتصالُ قراءةٍ بالقاعدة (بحسابِ التطبيق — الخطةُ لا تكتب شيئًا). */
function xf_db($ROOT)
{
    require_once $ROOT . '/includes/env.php';
    $host = ems_env('DB_HOST'); $port = 3306;
    if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
    mysqli_report(MYSQLI_REPORT_OFF);
    $c = @new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
    if ($c->connect_errno) { return null; }
    $c->set_charset('utf8mb4');
    return $c;
}
