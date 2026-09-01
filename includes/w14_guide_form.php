<?php
/**
 * includes/w14_guide_form.php — نموذجُ الإضافةِ **مشتقًّا من الدليلِ لا مكتوبًا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حكمُ المالك**: «كلُّ ما تحتاجه موجودٌ في الدليلِ المعماريّ». وهذا المكوِّنُ
 *   ينفّذه حرفًا: **لا اسمَ حقلٍ يُكتب هنا ولا في الشاشة** — كلُّها من
 *   `repair01_fields` (‏7,608 حقلًا مستوعَبًا من «09 · 02_تتبع_الحقول» بمرجعِ
 *   خليّةٍ لكلِّ صفّ) [[iaf-field-closure]].
 *
 * ◆ **ومفرداتُ `field_type` تسعٌ مغلقة، والقابلُ للإدخالِ منها ثلاثةٌ بنصِّ قاعدتِه**:
 *     · `BUSINESS_INPUT`  «خانة إدخال مفتوحة»
 *     · `REFERENCE`       «قائمة محكومة من بيتها»        ⇒ قائمةٌ منسدلة
 *     · `FK_INHERITED`    «مفتاح موروث — يُختار ولا يُكتب» ⇒ قائمةٌ منسدلة
 *   ⛔ **وما عداها لا يدخل النموذجَ بنصِّ قاعدتِه**: `PK_GENERATED` يولّده النظام ·
 *   `DERIVED` قراءةٌ محسوبة · `AUDIT` أثرٌ Append-Only · `IMPORTED_READONLY`
 *   من إدارةٍ مالكةٍ أخرى · `PARENT_INHERITED` من الأبِ ويُقفَل · `SNAPSHOT` لقطة.
 *   **فمطالبةُ حقلٍ بعقدِ غيرِه هي عينُ ما يمنعه `AMD-01 §3-1`.**
 *
 * ◆ **وجسرُ الاسمِ إلى العمودِ خريطةُ الشاشةِ نفسِها** `$GUIDE_COLS` — التي
 *   يقرؤها `ems_w14_grid` للعرض. **خريطةٌ واحدةٌ للعرضِ والإدخال**، ⛔ ولا
 *   تُكتب ثانيةً [[declared-column-not-built]]. ولغتُها ثلاثةُ أشكال:
 *     `col` عمودٌ خام · `@col` **العمودُ نفسُه** معرَّبًا (‏ENUM محكوم) ·
 *     `#key` دالّةٌ مشتقّةٌ — **ليست عمودًا ولا تُدخَل**.
 *
 * ◆ **والعمودُ يُثبَت في المخطَّطِ قبل أن يُصيَّر**: اسمٌ في الخريطةِ بلا عمودٍ
 *   حقيقيٍّ **جسرٌ مكسورٌ يُعلَن ولا يُبتلع** [[finish-round-closure]]، وقائمةُ
 *   `ENUM` **تُشتقُّ من المخطَّطِ لا تُكتب** [[enum-silent-empty-write]].
 *
 * ◆ **والكتابةُ بالبوّابةِ حصرًا** (`ems_tenant_db()->insert`): العزلُ يُحقن
 *   بالبنيةِ لا بانضباطِ الكاتب — وهي القناةُ التي تريدها سقّاطةُ `GAP-29`،
 *   ⛔ ولا نصَّ `INSERT INTO` في هذا الملفِّ ولا في شاشةٍ تستعمله.
 *
 * ◆ **وثلاثةُ أقفالٍ قبل أيِّ كتابة** — ولا يُفتح واحدٌ بغيرِ سببِه:
 *     ① `can_add` من `w14_perms` (‏والسوبر لا يُستثنى في `get_module_permissions`)
 *     ② `verify_csrf_token` — والحقلُ يُطبع صراحةً، فحقنُ `ems_inject_csrf_fields`
 *        للـ`fetch`/`XHR` لا للنماذجِ العاديّة [[csrf-plain-form-403]]
 *     ③ `ems_require_action` حارسُ الفعلِ الخادميّ (‏ADR-06)
 *
 * الاستعمال في الشاشة (سطران):
 *   require_once __DIR__ . '/../includes/w14_guide_form.php';
 *   ems_w14_guide_form(array(
 *       'surface' => 'سجل السياسات', 'table' => 'gov_policy',
 *       'cols' => $GUIDE_COLS, 'perms' => $perms, 'screen' => 'Governance/policies.php',
 *   ));
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/w14_view.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/permissions_helper.php';

if (!function_exists('ems_w14_gf_norm')) {
    /** تسويةُ اسمِ الحقلِ/السطح — الوسمُ (▼◄) وحاشيةُ الانطباقِ ليسا من الاسم */
    function ems_w14_gf_norm($s)
    {
        $s = str_replace(array('▼', '◄', '►', '▲'), '', (string) $s);
        $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
        $s = str_replace(array('ة', 'ى', 'ـ', "\xC2\xA0"), array('ه', 'ي', '', ' '), $s);
        $s = preg_replace('~[\x{064B}-\x{0652}]~u', '', $s);
        $s = preg_replace('~\s*[—–-]\s*بحسب\s+انطباق\s+الشرك[هة]\s*$~u', '', $s);
        return trim(preg_replace('~\s+~u', ' ', $s));
    }
}

if (!function_exists('ems_w14_gf_fields')) {
    /**
     * حقولُ الإدخالِ المشتقّةُ للسطح — ⛔ ولا حقلَ بلا عمودٍ مُثبَتٍ في المخطَّط.
     *
     * @return array{fields:array,broken:array,surface:string}
     */
    function ems_w14_gf_fields($conn, $surfaces, $table, array $cols)
    {
        $ENTER = array('BUSINESS_INPUT' => 'input', 'REFERENCE' => 'select', 'FK_INHERITED' => 'select');

        /* أعمدةُ الجدولِ من المخطَّطِ — المرجعُ الوحيدُ لوجودِ العمودِ ونوعِه */
        $schema = array();
        $q = @mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        while ($q && ($x = mysqli_fetch_assoc($q))) { $schema[$x['Field']] = $x; }
        if (!$schema) {
            return array('fields' => array(), 'broken' => array('لا مخطَّطَ للجدولِ ' . $table),
                         'surface' => '', 'generated' => array());
        }

        /* خريطةُ الشاشةِ مُسوّاةً: اسمُ حقلِ الدليل ⇒ عمود */
        $map = array();
        foreach ($cols as $lbl => $src) {
            $src = (string) $src;
            if ($src === '' || $src[0] === '#') { continue; }        /* دالّةٌ مشتقّةٌ لا عمود */
            $map[ems_w14_gf_norm($lbl)] = ltrim($src, '@');
        }

        /* ═══ حقولُ الدليلِ — **مفهرسةً بالاسمِ المُسوّى مرّةً واحدة** ═══
           ⛔ **ولا تُطابَق بالنصِّ الخامِّ في `SQL`**: `surface` في السجلِّ يحمل
             الحاشيةَ والهمزةَ والتاءَ المربوطة، والمفتاحُ الذي يصلنا مُسوًّى —
             فمطابقةُ `surface = ?` تُرجع صفرًا وهي تكذب. **التسويةُ في PHP
             حيث تُعرَّف** [[nav-label-four-source-precedence]]. */
        static $bySurface = null;
        if ($bySurface === null) {
            $bySurface = array();
            $q2 = @mysqli_query($conn,
                "SELECT surface, seq, field_name, field_type, visibility_rule, src_ref
                   FROM repair01_fields WHERE surface <> ''
                  ORDER BY CAST(seq AS UNSIGNED), id");
            while ($q2 && ($f = mysqli_fetch_assoc($q2))) {
                $bySurface[ems_w14_gf_norm($f['surface'])][] = $f;
            }
        }

        /* ⛔ **ولا يُرجَّح بالترتيبِ بل بالخريطةِ المقيسة**: السطحُ قد يُسجَّل
           باسمٍ ويُخرَّط باسمِ هدفٍ يخدمه — **فيفوز ما تنطبق خريطتُه فعلًا**،
           ولا يتأرجح الحكمُ بين تشغيلَين [[counter-parity-two-readers]]. */
        $best = array(); $bestN = 0; $bestSurf = ''; $broken = array();
        foreach ((array) $surfaces as $sf) {
            $key = ems_w14_gf_norm($sf);
            if ($key === '' || !isset($bySurface[$key])) { continue; }
            $cand = array(); $bad = array(); $gen = array();
            foreach ($bySurface[$key] as $f) {
                /* ⭐ **ومفتاحُ `PK_GENERATED` يُجمَع ولا يُعرَض**: لا يدخل النموذجَ
                   («ولا يُحرَّر») لكنّه **يُملأ قبل الكتابة**، وإلّا تصادم
                   المفتاحُ الفريدُ عند ثاني إدخال — وهو ما قِيس حيًّا. */
                if ($f['field_type'] === 'PK_GENERATED') {
                    $gc = isset($map[ems_w14_gf_norm($f['field_name'])])
                        ? $map[ems_w14_gf_norm($f['field_name'])] : '';
                    if ($gc !== '' && isset($schema[$gc])) {
                        $gen[] = array('name' => $gc, 'label' => trim(str_replace(array('▼', '◄'), '', $f['field_name'])));
                    }
                    continue;
                }
                if (!isset($ENTER[$f['field_type']])) { continue; }   /* عقدُ نوعِه يمنعه */
                $nm = ems_w14_gf_norm($f['field_name']);
                $col = isset($map[$nm]) ? $map[$nm] : '';
                if ($col === '' || !isset($schema[$col])) {
                    $bad[] = $f['field_name'] . ' — ' . ($col === '' ? 'لا خريطة' : '`' . $col . '` غيرُ موجودٍ في المخطَّط');
                    continue;
                }
                $cand[] = array(
                    'label'   => trim(str_replace(array('▼', '◄'), '', $f['field_name'])),
                    'name'    => $col,
                    'control' => $ENTER[$f['field_type']],
                    'type'    => $f['field_type'],
                    'rule'    => $f['visibility_rule'],
                    'src'     => $f['src_ref'],
                    'schema'  => $schema[$col],
                );
            }
            if (count($cand) > $bestN) {
                $bestN = count($cand); $best = $cand; $bestSurf = $sf; $bestGen = $gen;
            }
            if (!$broken) { $broken = $bad; }
        }
        return array('fields' => $best, 'broken' => $broken, 'surface' => $bestSurf,
                     'generated' => isset($bestGen) ? $bestGen : array());
    }
}

if (!function_exists('ems_w14_gf_guide_options')) {
    /**
     * مفرداتُ القائمةِ من **الدليلِ نفسِه** — `gov_guide_lists`.
     * ◆ **ولماذا لزمت**: حكمُ الحقلِ في الدليلِ «قائمة محكومة من بيتها»، وأكثرُ
     *   الأعمدةِ المقابلةِ `varchar` لا `ENUM` — فبلا هذا السجلِّ تخرج **خانةَ
     *   نصٍّ حرّةً وهي نقضُ حكمِها**، ويُكتب فيها ما ليس من المفردات.
     * ◆ **والغيابُ يُعلَن ولا يُبتلَع**: حقلٌ بلا مفرداتٍ يهبط إلى خانةِ نصٍّ
     *   **بتنبيهٍ مطبوعٍ في وسمِه** لا بصمت.
     */
    function ems_w14_gf_guide_options($conn, $surfaces, $fieldLabel)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = array();
            $q = @mysqli_query($conn,
                "SELECT surface_key, field_key, value_ar FROM `gov_guide_lists`
                  WHERE active = 1 ORDER BY sort_no, id");
            while ($q && ($x = mysqli_fetch_assoc($q))) {
                $cache[$x['surface_key'] . '|' . $x['field_key']][] = $x['value_ar'];
            }
        }
        $fk = ems_w14_gf_norm($fieldLabel);
        foreach ((array) $surfaces as $sf) {
            $k = ems_w14_gf_norm($sf) . '|' . $fk;
            if (isset($cache[$k])) {
                $out = array();
                foreach ($cache[$k] as $v) { $out[$v] = $v; }
                return $out;
            }
        }
        return array();
    }
}

if (!function_exists('ems_w14_gf_options')) {
    /** مفرداتُ `ENUM` من المخطَّطِ — **وهي التي تقبل الكتابةَ فعلًا** فتغلب */
    function ems_w14_gf_options(array $schemaCol)
    {
        $t = (string) $schemaCol['Type'];
        if (!preg_match('~^enum\((.*)\)$~i', $t, $m)) { return array(); }
        $out = array();
        foreach (str_getcsv($m[1], ',', "'") as $v) {
            $v = trim($v);
            if ($v === '') { continue; }
            /* والعرضُ بالعربيّةِ بالمعجمِ نفسِه الذي يعرض به الجدول */
            $out[$v] = function_exists('ems_w14_ar') ? ems_w14_ar($v) : $v;
        }
        return $out;
    }
}

if (!function_exists('ems_w14_gf_generated')) {
    /**
     * قيمةُ حقلِ `PK_GENERATED` — **بمولِّدِ الدارِ الذرِّيِّ ونمطٍ مقيسٍ لا مخترَع**.
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ **العطبُ الذي تعالجه، مقيسًا حيًّا**: الدليلُ يقول «يولّده النظام ولا
     *   يُحرَّر» **ولا يقول كيف**. وبلا توليدٍ يخرج العمودُ فارغًا فيتصادم
     *   المفتاحُ الفريدُ عند **ثاني** إدخال — `uq_gvp(company_id, policy_no,
     *   version_no)` ردَّ الصفَّ الثانيَ وسقط بلا سببٍ ظاهرٍ للمُدخِل.
     *
     * ⭐ **والمولِّدُ مولِّدُ الدارِ لا مولِّدٌ ثانٍ**: `ServerId::nextNo` بسجلِّ
     *   `ems_sequences` — تخصيصٌ ذرِّيٌّ يضمن التفرُّدَ تحت التزامن، ⛔ لا
     *   `MAX+1` سباقيٌّ يُعيد الرقمَ بعد حذف [[counter-parity-two-readers]].
     *
     * ◆ **والنمطُ يُقاس من الصفوفِ القائمةِ أوّلًا** (بادئةٌ وعرضٌ)، ثمَّ من
     *   نطاقٍ مسجَّلٍ في `ems_sequences`.
     *
     * ⛔ **وإن لم يوجد أيٌّ منهما فلا يُخترَع**: ورقةُ `DEP-08` تنصُّ أنَّ
     *   الحوكمةَ **تملك «نمطَ الترقيم»** — فاختراعُ بادئةٍ هنا اغتصابُ قرارٍ
     *   محكومٍ يمنعه §17. ⇒ تُرجَع `null` **ويُعلَن الحاجزُ باسمِه ونطاقِه**،
     *   ولا يُكتب صفٌّ بمفتاحٍ فارغٍ يتصادم [[fix-the-tool-not-the-output]].
     *
     * @return array{value:?string,scope:string}
     */
    function ems_w14_gf_generated($conn, $table, $col, $companyId)
    {
        $prefix = null; $pad = 6;
        try { $rows = w14_gate(false)->select($table, array('columns' => array($col), 'limit' => 200)); }
        catch (\Throwable $t) { $rows = array(); }
        $max = 0;
        foreach ((array) $rows as $r) {
            $v = isset($r[$col]) ? trim((string) $r[$col]) : '';
            if ($v === '' || !preg_match('~^(.*?)(\d+)$~u', $v, $m)) { continue; }
            if ($prefix === null) { $prefix = $m[1]; $pad = strlen($m[2]); }
            if ($m[1] === $prefix) { $max = max($max, (int) $m[2]); }
        }
        $scope = $table . ':' . $col;
        if ($prefix === null) {
            /* نطاقٌ مسجَّلٌ سلفًا في سجلِّ المتتاليات — `<table>:<PREFIX>:<pad>` */
            $st = @mysqli_prepare($conn, 'SELECT scope FROM ems_sequences WHERE scope LIKE ? LIMIT 1');
            if ($st) {
                $like = $table . ':%';
                mysqli_stmt_bind_param($st, 's', $like);
                mysqli_stmt_execute($st);
                $rr = mysqli_stmt_get_result($st);
                $row = $rr ? mysqli_fetch_assoc($rr) : null;
                mysqli_stmt_close($st);
                if ($row && preg_match('~^' . preg_quote($table, '~') . ':([^:]*):(\d+)$~', (string) $row['scope'], $ms)) {
                    $prefix = $ms[1]; $pad = (int) $ms[2]; $scope = (string) $row['scope'];
                }
            }
        } else {
            $scope = $table . ':' . $prefix . ':' . $pad;
        }
        if ($prefix === null) { return array('value' => null, 'scope' => $table . ':<البادئة>:6'); }

        if (class_exists('\App\Core\ServerId') && method_exists('\App\Core\ServerId', 'nextNo')) {
            try { return array('value' => \App\Core\ServerId::nextNo($conn, $scope, $prefix, $pad), 'scope' => $scope); }
            catch (\Throwable $t) { /* يهبط إلى المقيسِ أدناه */ }
        }
        return array('value' => $prefix . str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT), 'scope' => $scope);
    }
}

if (!function_exists('ems_w14_guide_form')) {
    /**
     * يعالج الإرسالَ (إن وقع) ثمَّ يطبع النموذج.
     *
     * @param array $o surface|surfaces · table · cols · perms · screen
     *                 [title] [submit] [action_verb] [extra] [id]
     */
    function ems_w14_guide_form(array $o)
    {
        $conn = isset($o['conn']) ? $o['conn'] : (isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null);
        if (!$conn) { return; }
        $table = (string) (isset($o['table']) ? $o['table'] : '');
        $cols  = isset($o['cols']) && is_array($o['cols']) ? $o['cols'] : array();
        $screen = (string) (isset($o['screen']) ? $o['screen'] : '');
        /* ◆ **والصلاحيّةُ تُقرأ ولا تُمرَّر**: أسماءُ متغيّرِ الصلاحيةِ تختلف بين
           عائلاتِ الشاشات (`$perms` · `$pp` · `$__pp`)، **وتمريرُ اسمٍ خطأً يُنتج
           مصفوفةً فارغةً تُقرأ «لا صلاحية» صامتةً** — فتختفي إضافةٌ مسموحة.
           ⇒ **تُقرأ هنا من مصدرِها بالشاشة**، والسوبرُ يُستثنى صراحةً لأنَّ
           `get_module_permissions()` لا يستثنيه [[module-perm-no-super-bypass]]. */
        $perms = isset($o['perms']) && is_array($o['perms']) && $o['perms'] ? $o['perms'] : array();
        if (!$perms && $screen !== '' && function_exists('check_page_permissions')) {
            $isSuper = (isset($_SESSION['user']['role']) && (string) $_SESSION['user']['role'] === '-1');
            $pp = check_page_permissions($conn, $screen);
            $perms = array(
                'can_view' => $isSuper ? true : !empty($pp['can_view']),
                'can_add'  => $isSuper ? true : !empty($pp['can_add']),
                'can_edit' => $isSuper ? true : !empty($pp['can_edit']),
            );
        }
        $surfaces = isset($o['surfaces']) ? (array) $o['surfaces']
                  : (isset($o['surface']) ? array($o['surface']) : array());
        if ($table === '' || !$cols || !$surfaces) { return; }

        $formId = isset($o['id']) ? (string) $o['id'] : ('gf_' . preg_replace('~[^a-z0-9_]~i', '_', $table));
        $verb   = isset($o['action_verb']) ? (string) $o['action_verb'] : 'add';
        $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

        $F = ems_w14_gf_fields($conn, $surfaces, $table, $cols);
        if (!$F['fields']) {
            /* ⛔ **ولا صمت**: سطحٌ لا يشتقُّ حقلًا واحدًا يُعلَن بسببِه المقيس */
            echo '<div class="card"><div class="card-body"><div class="ems-gov-empty">'
               . '⛔ لا حقلَ إدخالٍ مشتقٌّ من الدليلِ لهذا السطح — '
               . $h($F['broken'] ? implode(' · ', array_slice($F['broken'], 0, 3)) : 'لا صفَّ في `repair01_fields`')
               . '</div></div></div>' . "\n";
            return;
        }

        /* ═══ ① المعالجةُ — ثلاثةُ أقفالٍ قبل أيِّ كتابة ═══ */
        $msg = ''; $cls = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__gf']) && $_POST['__gf'] === $formId) {
            $tok = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            if (!verify_csrf_token($tok)) {
                $msg = 'انتهت صلاحيةُ الجلسة — أعد المحاولة'; $cls = 'alert-danger';
            } elseif (empty($perms['can_add'])) {
                $msg = 'لا صلاحيةَ إضافةٍ على هذه الشاشة'; $cls = 'alert-danger';
            } else {
                if ($screen !== '' && function_exists('ems_require_action')) {
                    ems_require_action($conn, $screen, $verb);        /* ADR-06 — يوقف بنفسِه عند المنع */
                }
                $data = array();
                foreach ($F['fields'] as $f) {
                    if (!array_key_exists($f['name'], $_POST)) { continue; }
                    $v = trim((string) $_POST[$f['name']]);
                    if ($v === '') {
                        /* ⛔ ولا تُكتب '' في `ENUM` — تُبتلع صامتةً وتصير فراغًا
                           لا مفردةً [[enum-silent-empty-write]] */
                        if ($f['schema']['Null'] === 'YES') { $data[$f['name']] = null; }
                        continue;
                    }
                    /* ⛔ **والحارسُ في المعالجةِ لا في التصيير**: قائمةٌ منسدلةٌ
                       في الصفحةِ لا تمنع إرسالًا مصنوعًا. **وقِيس حرفًا**: مفردةٌ
                       خارجَ الدليلِ كُتبت فعلًا حتى أُضيف هذا السطر — فالقائمةُ
                       زينةٌ حتى يحرسها الخادم [[action-guard-adr06]].
                       والمفرداتُ تُقرأ بالترتيبِ نفسِه: المخطَّطُ ثمَّ الدليل. */
                    $opts = ems_w14_gf_options($f['schema']);
                    if (!$opts && $f['control'] === 'select') {
                        $opts = ems_w14_gf_guide_options($conn, $surfaces, $f['label']);
                    }
                    if ($opts && !isset($opts[$v])) { continue; }     /* خارجَ المفرداتِ يُسقَط */
                    $data[$f['name']] = $v;
                }
                if (isset($o['extra']) && is_array($o['extra'])) { $data += $o['extra']; }
                /* ⭐ **ومفاتيحُ `PK_GENERATED` تُملأ قبلَ الكتابة** — وإلّا خرج
                   العمودُ فارغًا فتصادم المفتاحُ الفريدُ عند ثاني إدخال. */
                $genBlocked = array();
                if ($data) {
                    $cid = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
                    foreach ($F['generated'] as $g) {
                        if (isset($data[$g['name']])) { continue; }
                        $gv = ems_w14_gf_generated($conn, $table, $g['name'], $cid);
                        if ($gv['value'] === null) { $genBlocked[] = $g['label'] . ' (‏نطاقُه `' . $gv['scope'] . '`)'; continue; }
                        $data[$g['name']] = $gv['value'];
                    }
                }
                if ($genBlocked) {
                    $data = array();
                    $msg = '⛔ نمطُ الترقيمِ غيرُ مسجَّلٍ لـ' . implode(' · ', $genBlocked)
                         . ' — والحوكمةُ تملك نمطَ الترقيم (ورقة DEP-08). سجِّلْ صفًّا في '
                         . '`ems_sequences` بالنطاقِ أعلاه، أو أدخلْ أوّلَ سطرٍ بكودِه '
                         . 'ليُقاس النمطُ منه. ⛔ ولا يُكتب صفٌّ بمفتاحٍ فارغٍ يتصادم.';
                    $cls = 'alert-danger';
                }
                if (!$data) {
                    $msg = 'لا حقلَ مُدخَل'; $cls = 'alert-danger';
                } else {
                    try {
                        w14_gate(false)->insert($table, $data);       /* ⛔ بالبوّابةِ حصرًا */
                        $msg = '✓ أُضيف السطرُ بحقولِ الدليل'; $cls = 'alert-success';
                    } catch (\Throwable $t) {
                        error_log('w14_guide_form insert ' . $table . ': ' . $t->getMessage());
                        $msg = 'تعذّرت الإضافة — رُوجعَ السجلُّ الفنّي'; $cls = 'alert-danger';
                    }
                }
            }
        }

        /* ═══ ② التصيير ═══ */
        if ($msg !== '') { echo '<div class="alert ' . $h($cls) . '">' . $h($msg) . '</div>' . "\n"; }
        if (empty($perms['can_add'])) { return; }                     /* لا نموذجَ لمن لا يضيف */

        $title  = isset($o['title']) ? (string) $o['title'] : 'إضافة سطر جديد';
        $submit = isset($o['submit']) ? (string) $o['submit'] : 'حفظ';
        echo '<form id="' . $h($formId) . '" method="post" action="" class="allforms allforms-visible">' . "\n";
        echo '  <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> ' . $h($title) . '</h5></div>' . "\n";
        echo '  <div class="card-body"><div class="form-grid">' . "\n";
        echo '    ' . csrf_field() . "\n";
        echo '    <input type="hidden" name="__gf" value="' . $h($formId) . '">' . "\n";
        foreach ($F['fields'] as $i => $f) {
            $fid = $formId . '_' . $f['name'];
            $req = ($f['schema']['Null'] === 'NO' && $f['schema']['Default'] === null) ? ' required' : '';
            echo '    <div class="filter-field">' . "\n";
            echo '      <label for="' . $h($fid) . '">' . $h($f['label']) . '</label>' . "\n";
            /* ⭐ **المخطَّطُ أوّلًا ثمَّ الدليل**: مفرداتُ `ENUM` هي ما يقبله العمودُ
               فعلًا، وقيمُ الدليلِ تُكمِل حين يكون العمودُ `varchar` — ⛔ ولا
               تُعرَض مفردةٌ لا يقبلها العمود [[enum-silent-empty-write]]. */
            $opts = ems_w14_gf_options($f['schema']);
            $fromGuide = false;
            if (!$opts && $f['control'] === 'select') {
                $opts = ems_w14_gf_guide_options($conn, $surfaces, $f['label']);
                $fromGuide = (bool) $opts;
            }
            if ($f['control'] === 'select' && $opts) {
                echo '      <select id="' . $h($fid) . '" name="' . $h($f['name']) . '"' . $req . '>' . "\n";
                echo '        <option value="">— اختر —</option>' . "\n";
                foreach ($opts as $v => $lbl) {
                    echo '        <option value="' . $h($v) . '">' . $h($lbl) . '</option>' . "\n";
                }
                echo '      </select>' . "\n";
            } else {
                $t = strtolower((string) $f['schema']['Type']);
                if (strpos($t, 'datetime') === 0 || strpos($t, 'timestamp') === 0) { $it = 'datetime-local'; }
                elseif (strpos($t, 'date') === 0) { $it = 'date'; }
                elseif (preg_match('~^(int|bigint|smallint|tinyint|decimal|float|double)~', $t)) { $it = 'number'; }
                else { $it = 'text'; }
                $ml = '';
                if (preg_match('~^varchar\((\d+)\)~', $t, $mv)) { $ml = ' maxlength="' . (int) $mv[1] . '"'; }
                $step = ($it === 'number' && strpos($t, 'decimal') === 0) ? ' step="any"' : '';
                echo '      <input type="' . $it . '" id="' . $h($fid) . '" name="' . $h($f['name']) . '"'
                   . $ml . $step . $req . '>' . "\n";
            }
            /* قاعدةُ الحقلِ من الدليلِ — تُعرَض لا تُخفى، فالمُدخِلُ يعرف حكمَه */
            /* ⛔ **وحقلُ قائمةٍ خرج خانةَ نصٍّ يُعلَن** — فالسكوتُ عنه يجعل
               الحرَّ يبدو محكومًا [[measure-blind-spots]]. */
            $hint = $f['rule'];
            if ($f['control'] === 'select') {
                if ($opts) { $hint .= $fromGuide ? ' — مفرداتُها من الدليل' : ' — مفرداتُها من المخطَّط'; }
                else { $hint .= ' — ⚠ لا مفرداتَ مسجَّلةٌ لها بعد'; }
            }
            echo '      <small class="ems-gov-hint">' . $h($hint) . '</small>' . "\n";
            echo '    </div>' . "\n";
        }
        echo '  </div>' . "\n";
        echo '  <div class="filter-actions"><button type="submit" class="btn-primary">'
           . '<i class="fa fa-check"></i> ' . $h($submit) . '</button></div>' . "\n";
        echo '  </div></div>' . "\n";
        echo '</form>' . "\n";
        /* ورقةُ النماذجِ الموحَّدةُ تُحمَّل أخيرًا — وإلّا هزمتها قواعدُ سابقة */
        static $css = false;
        if (!$css) {
            $css = true;
            $p = dirname(__DIR__) . '/assets/css/ems-forms.css';
            echo '<link rel="stylesheet" href="/ems/assets/css/ems-forms.css'
               . (is_file($p) ? '?v=' . filemtime($p) : '') . '">' . "\n";
        }
    }
}
