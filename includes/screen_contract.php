<?php
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
    /** «ما هذه الشاشة؟» — سطرُ غرضٍ وخطواتٌ قابلةٌ للطي، في رأس كل شاشة. */
    function ems_screen_about($purpose, array $steps = array())
    {
        static $printed = false;
        $id = 'emsAbout' . substr(md5((string) $purpose), 0, 6);
        echo '<div class="card" style="border-inline-start:4px solid #e2b93b;margin-bottom:12px">'
           . '<div class="card-body" style="padding:10px 14px">'
           . '<a href="#" onclick="var b=document.getElementById(\'' . $id . '\');'
           . 'b.style.display=b.style.display===\'none\'?\'block\':\'none\';return false"'
           . ' style="font-weight:800;text-decoration:none"><i class="fa fa-circle-question"></i>'
           . ' ما هذه الشاشة؟</a>'
           . '<div id="' . $id . '" style="display:none;margin-top:8px;color:#555">'
           . '<p>' . htmlspecialchars((string) $purpose) . '</p>';
        if ($steps) {
            echo '<ol style="margin:6px 18px">';
            foreach ($steps as $s) { echo '<li>' . htmlspecialchars((string) $s) . '</li>'; }
            echo '</ol>';
        }
        echo '</div></div></div>';
        if (!$printed) { $printed = true; ems_state_offline_bar(); }
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
     * «ما هذه الشاشة؟» مشتقًّا آليًّا (E-03 · الاكتساح) — من سجل الشاشة نفسه:
     * اسمُ الموديول + موضعُها في قائمة الدور (المرحلة/المجموعة). الشاشاتُ ذات
     * السطر المصوغ يدويًّا لا تستدعي هذا — اليدويُّ أبلغ حيث وُجد.
     */
    function ems_screen_about_auto($conn)
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $rel = ltrim(preg_replace('~^.*?/ems/~', '', strtr($script, chr(92), '/')), '/');
        if ($rel === '') { return; }
        $name = ''; $place = '';
        try {
            $st = $conn->prepare("SELECT id, name FROM modules WHERE code = ? OR code LIKE ?
                                   ORDER BY (code = ?) DESC, CHAR_LENGTH(code) ASC LIMIT 1");
            $tail = '%/' . basename($rel);
            $st->bind_param('sss', $rel, $tail, $rel);
            $st->execute();
            $m = $st->get_result()->fetch_assoc();
            $st->close();
            if ($m) { $name = (string) $m['name']; }
            $role = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
            if ($role > 0) {
                $st = $conn->prepare("SELECT lg.stage_title, lg.name gname FROM nav_items ni
                                       JOIN link_groups lg ON lg.id = ni.group_id
                                      WHERE ni.role_id = ? AND ni.route = ? AND ni.active = 1 LIMIT 1");
                $st->bind_param('is', $role, $rel);
                $st->execute();
                $g = $st->get_result()->fetch_assoc();
                $st->close();
                if ($g) {
                    $place = trim((string) $g['stage_title']);
                    if ((string) $g['gname'] !== '' && (string) $g['gname'] !== $place) {
                        $place .= ($place !== '' ? ' ← ' : '') . $g['gname'];
                    }
                }
            }
        } catch (\Throwable $t) { /* السطر إرشاد — لا يُسقط الشاشة */ }
        if ($name === '') { $name = basename($rel, '.php'); }
        $purpose = 'شاشة «' . $name . '»' . ($place !== '' ? ' — ضمن ' . $place . ' في قائمتك' : '')
                 . '. البياناتُ فيها تخضع لعزل شركتك وصلاحياتِ دورك.';
        ems_screen_about($purpose);
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
