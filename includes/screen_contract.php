<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * includes/screen_contract.php — عقدُ الشاشة الموحّد (M-44)
 * ───────────────────────────────────────────────────────────────────────────
 * UI-01 §3 · UX-00 §5: مكوّنُ «ما هذه الشاشة؟» + الحالاتُ الخمس (تحميلٌ ·
 * فارغةٌ · خطأٌ · نجاحٌ · دون اتصال) **مكوّنًا واحدًا** — كان القياسُ:
 * «صفرُ شاشةٍ تنفّذه؛ والحالةُ الفارغة نصُّ DataTables العام بلا زرِّ
 * خطوةٍ أولى».
 *
 * الاستعمال:
 *   ems_screen_about('غرضُ الشاشة…', ['خطوة ١', 'خطوة ٢']);
 *   ems_state_empty('لا بياناتٍ لهذه الفترة', 'أضف أولَ عنصر', 'form.php');
 *   ems_state_error('تعذّرت القراءة', 'أعد المحاولة', '');
 *   ems_state_success('حُفظ ✅');   ems_state_loading();
 * والحالةُ «دون اتصال» تُحقن آليًّا (شريطٌ يظهر عند انقطاع الشبكة).
 */

if (!function_exists('ems_screen_about')) {
    /**
     * «عن الشاشة» — بطاقةُ تعريفٍ واحدةٌ لكل شاشة (UI-01 §3 · UX-00 §5).
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ العيبُ الذي عولج (قرار المالك 2026-08-09): كانت الدالةُ **تطبع بطاقةً
     *   فورًا في موضع ندائها**. و312 شاشةً من أصل 358 تناديها **قبل فتح
     *   `<div class="main">`، أي بعد `insidebar` مباشرةً — فتُصيَّر البطاقةُ
     *   خارجَ عمودِ المحتوى فتلتصق بحافة الشاشة معلَّقةً، لا فوق الجدول حيث
     *   تُقرأ. ونقلُ النداء في 312 ملفًّا عربيًّا مُرحِّلٌ آليٌّ يمسّ نصًّا —
     *   وهو أخطرُ من العيب.
     *
     * ◆ العلاجُ بنيويٌّ لا تجميلي: تُصدِر الدالةُ الآن **`<template>`** لا
     *   ترميزًا مرئيًّا. ومحتوى `<template>` **لا يُصيَّر أينما وُضع** بحكم
     *   المواصفة — فموضعُ النداء صار بلا أثرٍ بنيويًّا في 358 شاشةً دفعةً
     *   واحدةً بلا لمسِ ملفٍّ واحد. ثم يبني `assets/js/ems-screen-about.js`
     *   البطاقةَ في موضعها الصحيح: تحتَ الرأس الموحَّد (`.main_head`) مباشرةً
     *   وفوقَ أولِ محتوى — ويزرع زرَّ «عن الشاشة» في `.head_actions`.
     *
     * ◆ لماذا الرأسُ الموحَّد مرساةً موثوقة: قِيس فوُجد أن **357 من 358** شاشةً
     *   تناديها تُضمّن `includes/page_header.php` (والوحيدةُ الشاذّةُ هي هذا
     *   الملفُّ نفسُه). فالمرساةُ حاضرةٌ عمليًّا دائمًا، وللمُصيِّر ارتدادٌ إلى
     *   أول عنصرٍ في `.main` إن غابت.
     *
     * ◆ **اليدويُّ يغلب المشتقَّ مهما كان ترتيبُ النداء** — وهذا ليس ترفًا:
     *   أربعٌ وعشرون شاشةً تنادي `ems_screen_about_auto()` في صدرها (بعد
     *   `insidebar` مباشرةً) ثم تنادي النصَّ المصوغَ باليد لاحقًا داخلَ
     *   المحتوى. فقاعدةُ «أولُ نداءٍ يفوز» كانت **تبتلع النصَّ المصوغ** وتُبقي
     *   العامَّ — انحدارٌ صامتٌ لا يظهر في الشاشة: بطاقةٌ سليمةُ الشكل بنصٍّ
     *   أفقر. فيُصدَر القالبان موسومَين بمصدرِهما، ويختار المُصيِّرُ اليدويَّ
     *   إن وُجد. (كشفه `tools/screen_about_audit.php` بعد أن مرّت عيّنةُ تسعِ
     *   شاشاتٍ خضراءَ — والعيّنةُ لا تشهد للثلاثمئة.)
     *
     * ◆ **نصٌّ تعريفيٌّ فقط** (قرار المالك 2026-08-09): البطاقةُ دليلُ مستخدمٍ
     *   مصغَّر — تشرح **ما هذه الشاشة وما فيها** لمن يفتحها أولَ مرة. ولا تذكر
     *   مَن يعمل عليها ولا مهامَّها ولا الصلاحيات: تلك بياناتُ حوكمةٍ موضعُها
     *   شاشاتُ الحوكمة، وإقحامُها هنا يحوّل التعريفَ إلى تقريرٍ يُتصفَّح.
     *
     * @param string $purpose النصُّ التعريفيُّ للشاشة
     * @param array  $steps   (مهجورٌ — لا يُصيَّر؛ يبقى للتوافق مع 69 نداءً قائمًا)
     * @param string $rule    (مهجورٌ — لا يُصيَّر؛ يبقى للتوافق)
     * @param array  $opts    ['src' => 'manual'|'auto']
     */
    function ems_screen_about($purpose, array $steps = array(), $rule = '', $opts = array())
    {
        static $emitted = array();
        static $registryTried = false;
        if (is_string($opts)) { $opts = array('src' => $opts); }   // توافقٌ مع النداء القديم
        $src = isset($opts['src']) ? (string) $opts['src'] : 'manual';
        if (!in_array($src, array('registry', 'manual', 'auto'), true)) { $src = 'manual'; }
        if (isset($emitted[$src])) { return; }   // قالبٌ واحدٌ لكلِّ مصدر
        $emitted[$src] = true;

        /* ◆ السجلُّ هو المرجع (قرار المالك 2026-08-09): يُصدَر قالبُ `screen_about`
           من أوّلِ نداءٍ **أيًّا كان مصدرُه** — فالشاشاتُ التي تصوغ نصَّها بيدها
           ولا تنادي المشتقَّ أصلًا يبلغها السجلُّ أيضًا. وترتيبُ الترجيح عند
           المُصيِّر: **السجل ← نصُّ الملف ← الاشتقاق**.
           ◆ ولا يُحرَس «هل جُرِّب السجل؟» بـ`isset($emitted['registry'])`:
             `isset()` على قيمةٍ `false` **تعود true**، فكان النداءُ العائدُ
             يرتدّ فارغًا ولا يُصدِر السجلَّ أبدًا — علَمٌ مستقلٌّ أصدق. */
        if ($src !== 'registry' && !$registryTried && isset($GLOBALS['conn'])) {
            $registryTried = true;                             // محاولةٌ واحدةٌ لا أكثر
            $relKey = trim(preg_replace('~^.*?/ems/~', '', strtr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '\\', '/')), '/');
            if ($relKey !== '') {
                try {
                    $rs = $GLOBALS['conn']->prepare("SELECT description FROM screen_about
                                                      WHERE screen_path = ? AND active = 1 LIMIT 1");
                    if ($rs) {
                        $rs->bind_param('s', $relKey);
                        $rs->execute();
                        $row = $rs->get_result()->fetch_assoc();
                        $rs->close();
                        if ($row && trim((string) $row['description']) !== '') {
                            ems_screen_about((string) $row['description'], array(), '', array('src' => 'registry'));
                        }
                    }
                } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'السجلُّ قد لا يكون مُرحَّلًا — لا تُسقط الشاشة'); /* السجلُّ قد لا يكون مُرحَّلًا — لا تُسقط الشاشة */ }
            }
        }

        $purpose = trim((string) $purpose);
        if ($purpose === '') { return; }

        /* فقراتٌ تُفصل بسطرٍ فارغ — فالنصُّ الطويلُ يُقرأ مقاطعَ لا كتلةً واحدة */
        $body = '';
        foreach (preg_split('/\R{2,}/u', $purpose) as $para) {
            $para = trim($para);
            if ($para === '') { continue; }
            $body .= '<p class="ems-about__purpose">'
                   . htmlspecialchars($para, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($body === '') { return; }

        /* مفتاحُ الذاكرة مسارُ الشاشة: «أولُ فتحٍ يشرح، وما بعده يُطوى» لكلِّ
           شاشةٍ على حدة — لا علمًا واحدًا يكتم النظامَ كلَّه بعد أولِ صرف. */
        $key = trim(preg_replace('~^.*?/ems/~', '', strtr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '\\', '/')), '/');

        echo '<template class="ems-about-tpl" data-src="' . $src . '"'
           . ' data-screen-key="'
           . htmlspecialchars($key !== '' ? $key : 'screen', ENT_QUOTES, 'UTF-8') . '">'
           . $body . '</template>';

        /* شريطُ «دون اتصال» مرةً واحدةً للصفحة مهما تعدّدت القوالب */
        static $bar = false;
        if (!$bar) { $bar = true; ems_state_offline_bar(); }
    }
}

if (!function_exists('ems_state_empty')) {
    /** الفارغة — **بزرِّ الخطوة الأولى** لا نصِّ DataTables العام. */
    function ems_state_empty($message, $ctaLabel = '', $ctaHref = '')
    {
        echo '<div style="text-align:center;padding:34px;color:#888">'
           . '<i class="fa fa-inbox" style="font-size:2rem;color:#d9cfae"></i>'
           . '<p style="margin:10px 0">' . htmlspecialchars((string) $message) . '</p>';
        if ($ctaLabel !== '' && $ctaHref !== '') {
            echo '<a class="btn-save" href="' . htmlspecialchars((string) $ctaHref) . '">'
               . '<i class="fa fa-plus"></i> ' . htmlspecialchars((string) $ctaLabel) . '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('ems_state_error')) {
    /** الخطأ — بسببه **وزرِّ علاجه**، لا صفحةَ بيضاء. */
    function ems_state_error($message, $ctaLabel = 'أعد المحاولة', $ctaHref = '')
    {
        echo '<div class="alert alert-danger" style="display:flex;justify-content:space-between;align-items:center">'
           . '<span><i class="fa fa-triangle-exclamation"></i> '
           . htmlspecialchars((string) $message) . '</span>';
        if ($ctaLabel !== '') {
            $href = $ctaHref !== '' ? $ctaHref : ($_SERVER['REQUEST_URI'] ?? '');
            echo '<a class="btn-save" href="' . htmlspecialchars((string) $href) . '">'
               . htmlspecialchars((string) $ctaLabel) . '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('ems_state_success')) {
    function ems_state_success($message)
    {
        echo '<div class="alert alert-success"><i class="fa fa-circle-check"></i> '
           . htmlspecialchars((string) $message) . '</div>';
    }
}

if (!function_exists('ems_state_loading')) {
    /** هيكلٌ نابضٌ موحّد — لا دوّاماتٍ متفرقة. */
    function ems_state_loading($rows = 3)
    {
        echo '<div aria-busy="true">';
        for ($i = 0; $i < max(1, (int) $rows); $i++) {
            echo '<div style="height:18px;margin:8px 0;border-radius:6px;'
               . 'background:linear-gradient(90deg,#f0ece0 25%,#faf7ee 50%,#f0ece0 75%);'
               . 'background-size:200% 100%;animation:emsPulse 1.2s infinite"></div>';
        }
        echo '</div><style>@keyframes emsPulse{0%{background-position:200% 0}100%{background-position:-200% 0}}</style>';
    }
}

if (!function_exists('ems_state_offline_bar')) {
    /** «دون اتصال» — شريطٌ يظهر آليًّا عند انقطاع الشبكة ويختفي بعودتها. */
    function ems_state_offline_bar()
    {
        echo '<div id="emsOfflineBar" style="display:none;position:fixed;top:0;right:0;left:0;'
           . 'z-index:9999;background:#8a1f1f;color:#fff;text-align:center;padding:6px;font-weight:700">'
           . '⚠ لا اتصالَ بالشبكة — ما تكتبه محفوظٌ محليًّا (المسودةُ التلقائية) ولن يضيع</div>'
           . '<script>window.addEventListener("offline",function(){'
           . 'document.getElementById("emsOfflineBar").style.display="block"});'
           . 'window.addEventListener("online",function(){'
           . 'document.getElementById("emsOfflineBar").style.display="none"});</script>';
    }
}

if (!function_exists('ems_screen_about_auto')) {
    /**
     * تعريفُ الشاشة من **سجلِّ التعريفات** `screen_about` — نصٌّ مكتوبٌ لكلِّ
     * شاشة، يشرح ما هي وما فيها كدليلِ مستخدمٍ مصغَّر.
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ لماذا سجلٌّ في القاعدة لا نصٌّ في كلِّ ملف: التعريفاتُ **محتوًى يُحرَّر**
     *   لا شيفرةٌ تُنشر — فمراجعتُها وتصحيحُها لا يحتاجان لمسَ 358 ملفًّا ولا
     *   إعادةَ نشر. والسجلُّ يُولَّد من مصادرِ النظام الحية بـ
     *   `tools/screen_about_generate.php` ثم يُنقَّح، ولكلِّ صفٍّ **مصدرُه
     *   معلَنٌ** (`source`) فيُعرف المكتوبُ بيدٍ من المشتقّ.
     *
     * ◆ الارتدادُ عند غياب الصفّ: تعريفٌ أدنى من اسم الشاشة وإدارتِها — ولا
     *   تُخترع لها وظيفةٌ لا مصدرَ لها.
     */
    function ems_screen_about_auto($conn)
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $rel = ltrim(preg_replace('~^.*?/ems/~', '', strtr($script, chr(92), '/')), '/');
        if ($rel === '') { return; }

        /* السجلُّ يُصدَر من `ems_screen_about` نفسِها لكلِّ نداءٍ أوّل — فلا
           يُكرَّر هنا. ما يبقى لهذه الدالة: **الارتدادُ** حين لا صفَّ للشاشة. */
        $text = '';
        {
            $name = ''; $dept = '';
            try {
                $st = $conn->prepare("SELECT name FROM modules WHERE code = ? OR code LIKE ?
                                       ORDER BY (code = ?) DESC, CHAR_LENGTH(code) ASC LIMIT 1");
                $tail = '%/' . basename($rel);
                $st->bind_param('sss', $rel, $tail, $rel);
                $st->execute();
                if ($m = $st->get_result()->fetch_assoc()) { $name = trim((string) $m['name']); }
                $st->close();

                $st = $conn->prepare("SELECT owner_dept, title_ar FROM nav09_file_map WHERE real_path = ? LIMIT 1");
                $st->bind_param('s', $rel);
                $st->execute();
                if ($d = $st->get_result()->fetch_assoc()) {
                    $dept = trim((string) $d['owner_dept']);
                    if ($name === '') { $name = trim((string) $d['title_ar']); }
                }
                $st->close();
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'التعريفُ إرشادٌ — لا يُسقط الشاشة'); /* التعريفُ إرشادٌ — لا يُسقط الشاشة */ }

            if ($name === '') { $name = basename($rel, '.php'); }
            $text = 'شاشة «' . $name . '»' . ($dept !== '' ? ' — ضمن نطاق ' . $dept : '') . '.';
        }

        // مصدرٌ «مشتق»: يُصيَّر فقط إن لم تُصغ الشاشةُ تعريفَها بيدها (أيًّا كان الترتيب)
        ems_screen_about($text, array(), '', array('src' => 'auto'));
    }
}

if (!function_exists('ems_shell_axes')) {
    /**
     * CM-00 (DEC-E · update0010) — اشتقاقُ محاورِ الغلافِ الحاكمِ من مصادرها الحية
     * وبذرُها لـinheader (data-ems-ax-*). تُستدعى قبل تضمين inheader:
     *   ems_shell_axes($perms);                          // الاشتقاق القياسي
     *   ems_shell_axes($perms, array('edit' => 'locked')); // تجاوزٌ من آلة الحالة/إقفال الفترة
     * AX-2 الصلاحية من محرّك الصلاحيات · AX-3 التحرير منه ومن تجاوز الشاشة ·
     * AX-1/4/5 افتراضيةً هنا ويحدّثها العميل (الجلبُ والاتصالُ والحداثةُ لحظية).
     */
    function ems_shell_axes($perms = null, array $override = array())
    {
        /* لا يُدَّعى ما لم يُقَس: شاشةٌ لم تمرِّر صلاحياتِها المحلولةَ لا يُنسب
           إليها حكمُ صلاحيةٍ — المحورُ يعلن «غيرُ مقيس» فيُطوى في سطرِ السياقِ
           بدلَ أن يُعرض «قراءة» بلا مصدر. */
        $measured = is_array($perms);
        $canView = $measured ? !empty($perms['can_view']) : true;
        $canWrite = $measured && (!empty($perms['can_edit']) || !empty($perms['can_add']) || !empty($perms['can_delete']));
        $ax = array(
            'data' => 'data',
            'permission' => !$measured ? 'unmeasured' : ($canWrite ? 'full' : ($canView ? 'partial' : 'none')),
            'edit' => $canWrite ? 'editable' : 'readonly',
            'connection' => 'online',
            'freshness' => 'fresh',
        );
        foreach ($override as $k => $v) { $ax[$k] = $v; }
        $GLOBALS['EMS_AX'] = $ax;
        return $ax;
    }
}
