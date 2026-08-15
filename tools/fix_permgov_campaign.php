<?php
/**
 * tools/fix_permgov_campaign.php — حملةُ أدلةِ الصلاحياتِ والحوكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ:
 *
 * **الحكمُ الحاكم**: «لا تُغلق بندًا بشاهدٍ أضيقَ من نصِّ اختبارِه». فهذه الأداةُ
 * **تُفكّك نصَّ اختبارِ القبولِ إلى شروطٍ** وتقيس كلَّ شرطٍ على حدةٍ، وتُغلق
 * البندَ **فقط** إذا قِيست شروطُه كلُّها ونجحت. وما لم يُقَس يُعلَن بسببِه
 * ويبقى البندُ مفتوحًا. ولا يُوسَّع اختبارٌ ليطابق ما تستطيع الأداةُ قياسَه.
 *
 * ── سبعةُ أنماطِ قياسٍ، كلٌّ بآليتِه المبنيةِ في المستودعِ لا بآليةٍ ثانية ────
 *   AUDIT       ⇐ includes/audit_trail.php · activity_logs
 *   FIELD_MASK  ⇐ app/Services/Governance/FieldGovernor.php
 *   SOD         ⇐ includes/sod_guard.php
 *   SCOPE       ⇐ TenantDb · fin_project_scope
 *   NAV         ⇐ نمطُ tools/fix_nav_href_probe.php (عمليةٌ منفصلةٌ لكلِّ دور)
 *   EXPORT      ⇐ excel.php · ExcelRegistry
 *   DENY_WRITE  ⇐ 403 + صفرُ أثرٍ في الجدولِ المُسنَد
 *
 * ── وثلاثةُ فخاخٍ مسجَّلةٌ عولجت هنا صراحةً ─────────────────────────────────
 *   ① **تمييزُ ٤٠٣**: الشوطُ الثالثُ (مخوَّلٌ بلا رمزِ جلسة) كان يتوقّع ٤٠٣
 *      دائمًا. و`CSRF_ENFORCE_PATHS` تغطّي خمسةَ مجلداتٍ فقط — فمسارٌ خارجَها
 *      يعيد 200 بلا رمز، وذلك **يُثبت** أنَّ ٤٠٣ الأصليَّ ليس عطلَ حماية.
 *      فصار للشوطِ الثالثِ ثلاثُ نتائجَ لا اثنتان: مُنفَذٌ · غيرُ مُنفَذٍ · ملتبس.
 *   ② **الوسمُ عائليٌّ ثابتٌ** (لا `getmypid`) — وإلا كانت كلُّ جولةٍ عمياءَ
 *      عمّا تركته سابقتُها.
 *   ③ **مُرجَعُ كلِّ حذفٍ يُفحَص** — فمفتاحٌ أجنبيٌّ يردُّ صامتًا.
 *
 *   php tools/fix_permgov_campaign.php [--run] [--only=INJ-0011,…] [--md=<مسار>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$RUN = in_array('--run', $argv, true);
$MD = null; $ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); }
    if (strpos($a, '--only=') === 0) { $ONLY = array_map('trim', explode(',', substr($a, 7))); }
}
$TAG = 'PERMGOV';           /* وسمٌ عائليٌّ **ثابت** */

$lines = array();
$say = function ($s = '') use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

/* ══ ① النطاقُ مشتقًّا بالقاعدة ═══════════════════════════════════════════ */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}
$FAMILY = array('Permission Gap', 'Governance Gap');
$items = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++; if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (!in_array(trim($c[9]), $FAMILY, true)) { continue; }
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    if ($ONLY && !in_array($id, $ONLY, true)) { continue; }
    $items[$id] = array('id' => $id, 'dept' => trim($c[3]), 'scr' => trim($c[4]), 'url' => trim($c[5]),
        'type' => trim($c[9]), 'sev' => trim($c[10]), 'test' => trim($c[20]), 'real' => trim($c[8]),
        'half' => (trim($c[10]) === 'P0' || trim($c[10]) === 'P1') ? '①' : '②');
}
fclose($fh);

/* ══ ② تفكيكُ نصِّ الاختبارِ إلى شروط ═════════════════════════════════════
     الفصلُ بـ«؛» أوّلًا — وهي الفاصلةُ التي يستعملها السجلُّ بين الشروط.
     ثم بـ«و» في أوّلِ جملةٍ فعليةٍ. وشرطٌ أقصرُ من ١٥ حرفًا يُضمُّ لسابقه. */
$clausesOf = function ($test) {
    $parts = preg_split('~[؛;]+~u', (string) $test);
    $out = array();
    foreach ($parts as $p) {
        /* ◆ **لا `trim` بقائمةٍ فيها حرفٌ عربيّ**: `trim` تعمل بالبايتات، والفاصلةُ
             العربيةُ «،» بايتان — فتقضم بايتًا من أوّلِ حرفٍ عربيٍّ فيصير «؟».
             وقع فعلًا: «استدعاءُ» طُبعت «�ستدعاء» في أوّلِ تقرير. */
        $p = preg_replace('~^[\s.،,]+|[\s.،,]+$~u', '', (string) $p);
        if ($p === '') { continue; }
        if ($out && mb_strlen($p) < 15) { $out[count($out) - 1] .= ' — ' . $p; continue; }
        $out[] = $p;
    }
    return $out ? $out : array(trim((string) $test));
};

/* ══ ③ الأنماطُ ═══════════════════════════════════════════════════════════ */
$PAT = array(
    /* ── فصلُ الواجباتِ عند **المنح**: مفاتيحُ متعارضةٌ لا تجتمع في دورٍ واحد.
         آليتُه `includes/sod_guard.php` — وهي غيرُ آليةِ «من أنشأ لا يعتمد».
         ويُفحص **قبلها** لأنَّ نصَّه يذكر «يُرفض» فيبتلعه الأعمُّ لو تأخّر. */
    'SOD_GRANT'   => '~محاولةُ منحِ|منحُ حسابٍ واحدٍ|محاولةُ منح دورٍ|الرايات الثلاثَ|تُحظر لا تُحذَّر|بحجبٍ لا بتحذير|تسمّي الزوج~u',
    'SOD'         => '~من\s+(سجّل|أدخل|نفّذ|أنشأ|أعدّ|قدّم|رفع)|منشئُ|مُنشئُ|مُدخِلُ|لا يعتمد المرءُ|نفسُه لا يستطيع|أدخله المعتمِدُ نفسُه|الذي نفّذ|أنشأ .{0,30}لا يستطيع|أنشأ .{0,30}لا يراه~u',
    'BREAK_GLASS' => '~كسر الزجاج|كسرِ زجاج|permission_exceptions|valid_to~u',
    /* ── عطالةُ الإرسال: «إعادةُ الإرسالِ لا تُنتج صفًّا ثانيًا» ─────────────────
         آليتُه `includes/post_contract.php` (عقدُ الإرسالِ بمفتاحِ عطالة).
         ويُفحص **قبل** التدقيقِ لأنَّ نصَّه قد يذكر «يُسجَّل» فيبتلعه. */
    'IDEMPOTENT'  => '~إعادةُ إرسال|لا تُنتج صفًّا ثانيًا|لا تُنشئ صفًّا ثانيًا|تُرجع مرجعَ الأول|بلا تكرار~u',
    /* ── شاشةٌ قارئةٌ لا تُغيّر شيئًا بمجرّدِ فتحِها ─────────────────────────────── */
    'READ_ONLY'   => '~فتحُ الشاشة لا يغيّر|لا يغيّر عددَ صفوف|قراءةٌ خالصة~u',
    'FIELD_MASK'  => '~حقلٍ حساس|الحقول الحساسة|يُخفي قيمتَه|لا يجد حقولَ|مقنَّعًا|غائبٌ نصًّا|بلا ذلك العمود|يُسقطه من ملف التصدير|في جسم الاستجابة|في مصدر الصفحة|لا يرى مبلغَ|لا يرى اسمَ|في استجابة HTML|في مصدر HTML|لا يتلقى قيمَ~u',
    /* ── سجلُّ الاطّلاعِ على الحسّاس ────────────────────────────────────────── */
    'READ_LOG'    => '~يكتب صفًّا في سجل الاطّلاع|يكتب سطرًا في sensitive_read_log|يُنتج صفًّا في سجل الاطّلاع|ويُسجَّل اطّلاعُه|يُنتج صفَّ اطّلاع~u',
    /* ── حجبُ الشاشةِ عن بلا منحة: منعٌ عند البابِ لا عند الكتابة ─────────────── */
    'VIEW_DENY'   => '~لا يرى الشاشةَ|لا تفتح أيًّا من الشاشات|يمنع فتحَها|لا يفتحها|يُحجب عن الشاشة~u',
    /* ── ثابتٌ يُستعلَم فيعود صفرًا: «صفرُ أصلٍ مجموعُه ≠ ١٠٠» ────────────────── */
    'INVARIANT'   => '~استعلامٌ يوميٌّ يُظهر صفر|يعيد صفرَ نتيجة|صفرَ أصلٍ|صفرَ صفٍّ مخالف|لا صفَّ يخالف~u',
    /* ── تسجيلُ المعتمِدِ ومرجعِ تفويضِه في الصفِّ نفسِه ────────────────────────── */
    'RECORDS'     => '~يحمل مرجعَ تفويضٍ في السجل|يسجّل معتمِدًا مختلفًا|يسجّل اسمين|مرجعَ تفويضِ معتمِدها~u',
    /* ── العكسُ يُنشئ صفًّا ويُبقي الأصل — عقيدةُ اللاتعديل ──────────────────── */
    'REVERSAL'    => '~عكسُ .{0,20}ينشئ صفًّا|يُبقي الأصل|صفَّ عكسٍ|معكوس بـ~u',
    /* ── حجبُ الشاشةِ عند سحبِ `can_view` — والحكمُ لرمزِ الشاشةِ لا لجارتِها ─────── */
    'CAN_VIEW'    => '~سحبُ can_view|يمنع فتحَها بغضِّ النظر|يمنع فتحَ الشاشة بغضِّ النظر~u',
    /* ── حارسُ مهامِّ الجدولةِ من المتصفح ─────────────────────────────────────── */
    'CRON_GUARD'  => '~ملفِّ cron|عبر المتصفح بلا مفتاح|التشغيلُ من سطر الأوامر~u',
    'EXPORT'      => '~can_export|ملفِّ التصدير|ملفَّ التصدير|أعمدةَ الملفين|audit\.export|يُرفض تصديرُه|تصديرُه 403~u',
    'BUTTON_PARITY' => '~ظهورُ زرِّ|يظهر فيها الزرُّ|الزرُّ ويُرفض|⇔~u',
    'CAP'         => '~سقف|يُصعَّد|تصعيد|مرجعُ تفويض|صاحبِ سقفٍ أعلى~u',
    'SCOPE'       => '~نطاقين|كيانٍ آخر|شركةٍ أخرى|لا يراه في صندوق|عدّادين مختلفين|owner_unit_id|لا يرى أيَّ صفٍّ يخصُّ|الموقع ب|تلك الإدارةِ فقط|لإدارةٍ أخرى|مؤشراتُها وحدَها|بحساب إدارةٍ بعينها~u',
    'NAV'         => '~سايدبار|القائمةِ الجانبية|تعرض المرحلتان|في قائمة الدور|غيرُ موجودٍ في القائمة~u',
    'AUDIT'       => '~سطرَ تدقيق|سجل التدقيق|activity_logs|صفَّ اطّلاع|read_log|ويُسجَّل|يُسجَّل الرفض|قبل وبعد|old_value|صفَّ تدقيق~u',
    /* ── الطرفُ الموجب: المخوَّلُ **ينجح** ────────────────────────────────────
         «ودورُ كذا يُنشئ الصفَّ بنجاح» · «وإقفالُ المدير يمرّ» — ورفضٌ للجميعِ
         ليس حكمَ صلاحيةٍ بل عطلٌ، فلا يُغلق بندٌ بقياسِ المنعِ وحدَه. */
    'ALLOW_WRITE' => '~ودورُ .{0,30}(يُنشئ|ينشئ|يمرّ|ينجح|يُقفل|يعتمد)|بنجاح\b|يمرّ ويسجّل|يفتحها ويُسجَّل|يبتُّه بنجاح~u',
    'DENY_WRITE'  => '~يعيد ٤٠٣|يُعيد 403|يُردُّ 403|يتلقى 403|يجب 403|GOV-PERM-403|بلا can_edit|بلا can_add|ولا يُدرج|لا يُنشئ صفًّا|صفرُ صفٍّ|يُرفض 40~u',
    'REJECT_GUARD' => '~يُرفض 4\d\d|تُرفض 4\d\d|يُرفض برمز|422|423|409~u',
    'TOKEN_GET'   => '~بلا رمزٍ صالحٍ|بلا رمز CSRF|بلا رمزٍ|بلا رمز يُرفض|بلا رمزِ حماية~u',
    'STATE_GUARD' => '~يبقى الصفُّ|تبقى الحالة|محسوبةً من|لا من المُدخَل|الحقلُ غيرُ موجودٍ في نموذجه|بنفسه غيرُ ممكنة|الدورُ 9 وحدَه|بدور غير 9|بحالة «محسوم»|بحالة «معتمد»~u',
);
$patternOf = function ($test) use ($PAT) {
    foreach ($PAT as $k => $re) { if (preg_match($re, $test)) { return $k; } }
    return 'OTHER';
};

/* ══ ④ الوصولُ إلى القاعدةِ والشبكة ══════════════════════════════════════ */
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';

/* ── مسارُ الشاشةِ: ثلاثةُ مصادرَ لا مصدرٌ واحد ────────────────────────────────
     أوّلُ صياغةٍ قرأت الرابطَ وحدَه فأعلنت ثلاثةَ عشرَ بندًا «بلا ملفٍّ حيّ» —
     وفيها ما اسمُه يحمل المسارَ صراحةً (`Settings/modules.php`)، وفيها ما رابطُه
     **مجلدٌ** يقصد مجموعةَ شاشاتٍ («شاشاتُ الحوكمةِ العشرون»). فالحكمُ على
     المجموعةِ حكمٌ على أفرادِها حين يكون الإصلاحُ مركزيًّا. */
$relOf = function ($url, $scr = '', $hint = '') use ($ROOT) {
    /* ① الرابطُ يشير إلى ملفٍّ حيّ */
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', (string) $url, $m)
        && is_file($ROOT . '/' . $m[1])) { return $m[1]; }
    /* ② اسمُ الشاشةِ يحمل المسارَ صراحةً */
    if (preg_match('~([A-Za-z0-9_]+/[A-Za-z0-9_\-]+\.php)~', (string) $scr, $m2)
        && is_file($ROOT . '/' . $m2[1])) { return $m2[1]; }
    if (preg_match('~\(([A-Za-z0-9_\-]+\.php)\)~', (string) $scr, $m3)) {
        foreach (glob($ROOT . '/*/' . $m3[1]) as $g) {
            return str_replace($ROOT . '/', '', str_replace('\\', '/', $g));
        }
    }
    /* ③ مجلدٌ يقصد مجموعةَ شاشات — تُختار منه أوّلُ شاشةٍ مسجَّلةٍ حيّة */
    $folder = null;
    if (preg_match('~localhost/ems/([A-Za-z0-9_]+)/?$~', (string) $url, $m4)
        && is_dir($ROOT . '/' . $m4[1])) { $folder = $m4[1]; }
    if ($folder === null && preg_match('~المجلد\s+([A-Za-z0-9_]+)/~u', (string) $scr, $m5)
        && is_dir($ROOT . '/' . $m5[1])) { $folder = $m5[1]; }
    if ($folder !== null) {
        /* ◆ **الشاشةُ التي يسمّيها نصُّ البندِ أولًا** — لا أوّلُ ملفٍّ في المجلد.
             وقع الخطأُ فعلًا: بندُ «نموذجِ الفحص» أُسند إلى `failure_report.php`
             (تقريرٌ قارئٌ بلا أثر) فأُدين بغيابِ تدقيقٍ لا يلزمه أصلًا. */
        $hay = (string) $scr . ' ' . (string) $hint;
        $best = null;
        foreach (glob($ROOT . '/' . $folder . '/*.php') as $g) {
            $base = basename($g, '.php');
            if ($base !== '' && stripos($hay, $base) !== false) {
                $best = $folder . '/' . basename($g);
                break;
            }
        }
        if ($best !== null) { return $best; }
        foreach (glob($ROOT . '/' . $folder . '/*.php') as $g) {
            $cand = $folder . '/' . basename($g);
            $src = (string) @file_get_contents($g);
            if (stripos($src, '<form') !== false || preg_match('~\$_POST~', $src)) { return $cand; }
        }
    }
    return null;
};
/* ── معالجٌ يرث صلاحيةَ شاشتِه الأم ─────────────────────────────────────────
     `ems_guard_handler($conn, 'الشاشة الأم', 'edit')` يجعل المعالجَ محروسًا
     بمنحةِ أمِّه — فغيابُه عن `modules` **ليس** ثغرةً بل تصميمٌ: نقطةُ ردٍّ لا
     شاشةٌ في قائمة. وأوّلُ صياغةٍ عدّته «غيرَ مسجَّلٍ ⇒ fail-open» فأدانت
     معالجاتٍ محروسةً — فخُّ «قياسِ التسجيلِ لا الحراسة». */
$parentOf = function ($rel) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    /* ① الوراثةُ الصريحةُ بحارسِ المعالجات */
    if (preg_match('~ems_guard_handler\s*\(\s*\$conn\s*,\s*[\'"]([^\'"]+)[\'"]~', $s, $m)) { return $m[1]; }
    /* ② وراثةٌ بفحصِ صلاحيةِ شاشةٍ أمٍّ مسمّاةٍ صراحةً — وهو النمطُ الأقدمُ في
         المستودع (`check_page_permissions($conn, 'equipments_fleet')`). وأوّلُ
         صياغةٍ لم تعرفه فأعلنت ثلاثَ نقاطِ ردٍّ **محروسةً** «غيرَ مسجَّلةٍ
         fail-open» — وهو اتهامٌ بالعكس. */
    if (preg_match('~check_page_permissions\s*\(\s*\$conn\s*,\s*[\'"]([^\'"]+)[\'"]~', $s, $m2)) { return $m2[1]; }
    return null;
};

/* مصفوفةُ المنحِ على شاشةٍ */
$grantsOf = function ($rel) use ($conn) {
    $out = array('view' => array(), 'edit' => array(), 'partial' => array(), 'registered' => false);
    $st = $conn->prepare('SELECT rp.role_id, rp.can_view, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id WHERE m.code = ? ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $r = $st->get_result();
    while ($r && ($x = $r->fetch_assoc())) {
        $out['registered'] = true;
        $rid = (int) $x['role_id'];
        if ((int) $x['can_view'] === 1) { $out['view'][] = $rid; }
        if ((int) $x['can_edit'] === 1) { $out['edit'][] = $rid; }
        if ((int) $x['can_view'] === 1 && (int) $x['can_edit'] === 0) { $out['partial'][] = $rid; }
    }
    $st->close();
    return $out;
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role;
    $st->bind_param('si', $r, $CO);
    $st->execute();
    $x = $st->get_result()->fetch_row();
    $st->close();
    return $x ? (string) $x[0] : '';
};
/* هل مسارُ الشاشةِ تحت إنفاذِ CSRF؟ — يحسم تفسيرَ الشوطِ الثالث */
require_once $ROOT . '/includes/env.php';
$csrfPaths = array_filter(array_map('trim', explode(',', (string) ems_env('CSRF_ENFORCE_PATHS', ''))));
$csrfEnforced = function ($rel) use ($csrfPaths) {
    foreach ($csrfPaths as $p) { if ($p !== '' && stripos('/' . $rel, $p) !== false) { return true; } }
    return false;
};
/* ── أيُّ جدولٍ تكتبه الشاشة — بثلاثةِ مصادرَ لا مصدرٍ واحد ────────────────────
     ① عباراتُ الكتابةِ في الملفِّ نفسِه.
     ② خدماتُه المُضمَّنةُ — كثيرٌ من الشاشاتِ تكتب عبر خدمةِ نطاقٍ لا بيدِها.
     ③ **خريطةُ الأفعالِ `nav09_action_map`** (`writes_text`) — وهي المصدرُ
        المعتمَدُ حين يكون الفعلُ AJAX بلا عبارةٍ نصيّةٍ في الشاشة.
     وأوّلُ صياغةٍ قرأت المصدرَ الأوّلَ وحدَه فأعلنت سبعةَ عشرَ شرطًا «بلا جدولٍ
     مُسنَد» بينما الخريطةُ تسمّي جداولَها. */
$writesOf = function ($rel) use ($ROOT, $conn) {
    $t = array();
    $scan = function ($src) use (&$t) {
        if (preg_match_all('~INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)`?~i', $src, $m)) {
            foreach ($m[1] as $x) { $t[strtolower($x)] = 'INSERT'; }
        }
        if (preg_match_all('~UPDATE\s+`?([a-z0-9_]+)`?\s+SET~i', $src, $m2)) {
            foreach ($m2[1] as $x) { if (!isset($t[strtolower($x)])) { $t[strtolower($x)] = 'UPDATE'; } }
        }
    };
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    $scan($s);
    /* ② خدماتُه المُضمَّنة */
    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $s, $mm)) {
        foreach ($mm[1] as $inc) {
            $p = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $inc), '/');
            if (is_file($p) && strpos($p, '/includes/') === false) { $scan((string) @file_get_contents($p)); }
        }
    }
    /* ③ خريطةُ الأفعال — الفعلُ قد يكون AJAX بلا عبارةٍ في الشاشة */
    if (!$t) {
        $st = $conn->prepare("SELECT writes_text FROM nav09_action_map
                               WHERE live_code = ? AND writes_text IS NOT NULL AND writes_text <> '—'");
        if ($st) {
            $lc = 'page:' . $rel;
            $st->bind_param('s', $lc);
            $st->execute();
            $r = $st->get_result();
            while ($r && ($x = $r->fetch_row())) {
                foreach (preg_split('~[·,\s]+~u', (string) $x[0]) as $tbl) {
                    $tbl = trim($tbl);
                    if ($tbl !== '' && preg_match('~^[a-z0-9_]+$~', $tbl)) { $t[$tbl] = 'MAP'; }
                }
            }
            $st->close();
        }
    }
    return $t;
};
/* ── مسلكُ الأثرِ: أربعةٌ مشروعةٌ لا اثنان ────────────────────────────────────
     ويطابق ما يقيسه الشاهدُ المُشغَّل `tests/tenant_gate_audit_test.php` حرفًا
     بحرف — فأداةُ الحملةِ وشاهدُها لا يختلفان في تعريفِ «مُدقَّق». */
$auditPathOf = function ($rel) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($s === '') { return ''; }
    if (preg_match('~ems_tenant_db\(|->insert\(|->update\(|->deleteRow\(|->softDelete\(~', $s)) { return 'بوابة'; }
    if (preg_match('~cmp03_local_store|cmp03_store_insert|cmp03_stage_insert~', $s)) { return 'cmp03'; }
    if (preg_match('~ems_audit_change\s*\(~', $s)) { return 'نداءٌ صريح'; }
    if (preg_match('~ems_log_sensitive_read\s*\(|INSERT INTO sensitive_read_log~i', $s)) { return 'سجلُّ اطّلاع'; }
    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $s, $m)) {
        foreach ($m[1] as $inc) {
            $cand = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', ltrim($inc, '/')), '/');
            if (is_file($cand) && preg_match('~ems_audit_change\s*\(~', (string) @file_get_contents($cand))) {
                return 'خدمة';
            }
        }
    }
    $b = basename($rel, '.php'); $d = dirname($rel);
    foreach (array('_handler', '_actions') as $sx) {
        $h = $ROOT . '/' . ($d !== '.' ? $d . '/' : '') . $b . $sx . '.php';
        if (is_file($h) && preg_match('~ems_audit_change\s*\(|ems_tenant_db\(~', (string) @file_get_contents($h))) {
            return 'معالج';
        }
    }
    return '';
};

/* هل الشاشةُ تنادي موصِّلَ التدقيقِ فعلًا — وتُضمِّن مصدرَه؟ */
$auditAdoption = function ($rel) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    $calls = preg_match('~\bems_audit_change\s*\(~', $s) ? true : false;
    $req = (strpos($s, 'audit_trail.php') !== false);
    /* والخدماتُ التي تستدعيها الشاشةُ قد تحمل التدقيقَ عنها */
    $viaSvc = false;
    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $s, $m)) {
        foreach ($m[1] as $inc) {
            $p = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $inc), '/');
            if (is_file($p) && preg_match('~\bems_audit_change\s*\(~', (string) @file_get_contents($p))) {
                $viaSvc = true; break;
            }
        }
    }
    return array('calls' => $calls, 'requires' => $req, 'viaService' => $viaSvc);
};

/* ══ ⑤ الجولة ════════════════════════════════════════════════════════════ */
$say('══════════════════════════════════════════════════════════════════');
$say(' حملةُ أدلةِ الصلاحياتِ والحوكمة — الشرطُ وحدةَ القياسِ لا البند');
$say('══════════════════════════════════════════════════════════════════');
$say('');
$say('  النطاق: ' . count($items) . ' بندًا  (Permission Gap + Governance Gap · ليست «مُغلقٌ بشاهد»)');
$say('  إنفاذُ CSRF على: ' . (count($csrfPaths) ? implode(' · ', $csrfPaths) : 'لا شيء'));
$say('');

/* ══ مِسبارانِ حيّانِ ══════════════════════════════════════════════════════ */
$RUN_LIVE = in_array('--live', $argv, true);
$jar = sys_get_temp_dir() . '/permgov_' . getmypid() . '.txt';
$http = function ($url, $f = null, $follow = false) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$login = function ($user) use ($jar, $BASE, $http) {
    @unlink($jar);
    $b = $http($BASE . '/login.php', null, true);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
    $r = $http($BASE . '/login.php', http_build_query(array(
        'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')), true);
    return mb_strpos($r['body'], 'name="password"') === false;
};
$rowsIn = function ($table) use ($conn) {
    if (!preg_match('~^[a-z0-9_]+$~', $table)) { return -1; }
    $r = @$conn->query('SELECT COUNT(*) FROM `' . $table . '`');
    return $r ? (int) $r->fetch_row()[0] : -1;
};

/* رفضُ الكتابة: طرفٌ جزئيٌّ يرسل نموذجَ الشاشةِ — يُردُّ ولا يترك أثرًا */
/* ── الطرفُ غيرُ المخوَّلِ: **دورٌ جزئيٌّ أوّلًا، وإلا دورٌ بلا منحةٍ أصلًا** ────────
     نصُّ القبولِ يقول «POST من حسابٍ **بلا `can_edit`** يعيد ٤٠٣» — ودورٌ بلا
     أيِّ منحةٍ على الشاشةِ هو بلا `can_edit` بداهةً. وأوّلُ صياغةٍ اشترطت
     `can_view=1` **و**`can_edit=0` معًا، فأعلنت سبعةَ بنودٍ «غيرَ مقيسة» لأنَّ
     الشاشةَ لا تعرف تدرّجًا جزئيًّا — وهو تضييقٌ لنصِّ الاختبارِ لا وفاءٌ به.
     ◆ ويُعلَن أيُّ طرفٍ استُعمل، فالقارئُ يعرف ما قِيس بالضبط. */
$denyProbe = function ($rel, $g, $writes) use ($BASE, $http, $login, $userOfRole, $rowsIn, $conn, $CO) {
    $partialUser = ''; $pr = 0; $kind = 'جزئيّ';
    foreach ($g['partial'] as $rid) {
        $u = $userOfRole($rid);
        if ($u !== '') { $partialUser = $u; $pr = $rid; break; }
    }
    if ($partialUser === '') {
        /* دورٌ حيٌّ **لا صفَّ له** على هذه الشاشةِ إطلاقًا */
        $kind = 'بلا منحة';
        $granted = array_merge($g['view'], $g['edit']);
        $q = $conn->query("SELECT DISTINCT role FROM users
                            WHERE company_id = {$CO} AND username <> '' AND role NOT IN ('-1','1')
                            ORDER BY CAST(role AS UNSIGNED)");
        while ($q && ($rr = $q->fetch_row())) {
            $rid = (int) $rr[0];
            if (in_array($rid, $granted, true)) { continue; }
            $u = $userOfRole($rid);
            if ($u !== '') { $partialUser = $u; $pr = $rid; break; }
        }
    }
    if ($partialUser === '' || !$login($partialUser)) {
        return array('unmeasured', 'لا حسابَ لطرفٍ غيرِ مخوَّلٍ يُقاس عليه');
    }
    $page = $http($BASE . '/' . $rel, null, true);
    if ($page['code'] !== 200 || mb_strpos($page['body'], 'name="password"') !== false) {
 /* حجبٌ عند البابِ نفسِه: الطرفُ لم يعبر العرضَ — وهو أشدُّ من الردِّ عند الكتابة */
        $t0 = $rowsIn(array_keys($writes)[0]);
        return array('pass', 'الدورُ ' . $pr . ' (' . $kind . ') حُجب عند بابِ الشاشةِ نفسِه ('
            . $page['code'] . ') و`' . array_keys($writes)[0] . '` بلا تغيير (' . $t0 . ')');
    }
    /* حمولةٌ من نموذجِ الشاشةِ نفسِها — لا مخترَعة */
    if (!preg_match('~<form\b[^>]*method\s*=\s*["\']?\s*post[^>]*>(.*?)</form>~si', $page['body'], $fm)) {
        return array('unmeasured', 'لا نموذجَ POST مُصيَّرًا لهذا الدور — الفعلُ قد يكون AJAX');
    }
    $fields = array();
    if (preg_match_all('~<(?:input|select|textarea)\b[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>~i', $fm[1], $nm)) {
        foreach ($nm[1] as $n) { $fields[$n] = 'probe'; }
    }
    if (preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $fm[1], $tk)) {
        $fields['csrf_token'] = $tk[1];
    }
    if (count($fields) < 2) { return array('unmeasured', 'نموذجٌ بلا حقولٍ كافيةٍ لبناءِ حمولة'); }

    $table = array_keys($writes)[0];
    $before = $rowsIn($table);
    $res = $http($BASE . '/' . $rel, http_build_query($fields), false);
    $after = $rowsIn($table);
    $denied = ($res['code'] === 403)
           || ($res['code'] >= 300 && $res['code'] < 400)
           || preg_match('~GOV-PERM-403~', $res['body']);
    if ($denied && $after === $before) {
        return array('pass', 'الدورُ ' . $pr . ' (عرضٌ بلا كتابة) أُرسل نموذجَ الشاشةِ ⇒ رُدَّ ('
            . $res['code'] . ') و`' . $table . '` بلا تغيير (' . $before . ' ⇒ ' . $after . ')');
    }
    if (!$denied) {
        return array('fail', 'الدورُ ' . $pr . ' **لم يُردَّ** (' . $res['code'] . ')');
    }
    return array('fail', 'رُدَّ لكنَّ `' . $table . '` تغيّر (' . $before . ' ⇒ ' . $after . ') — تنفيذٌ يسبق الرفض');
};

/* ── الطرفُ الموجب: دورٌ يملك الكتابةَ يُصيَّر له نموذجُ الفعلِ فعلًا ──────────────
     ولا يُرسَل شيءٌ: القياسُ **قراءةٌ محضة** — فإرسالُ نموذجٍ حقيقيٍّ على شاشةٍ
     لا نعرف حمولتَها يترك أثرًا لا نستطيع كنسَه. والشرطُ المُقاس: «الشاشةُ
     تعرض له مسلكَ الفعلِ» — وهو ما يفصل الحكمَ عن العطل. */
$allowProbe = function ($rel, $g) use ($BASE, $http, $login, $userOfRole) {
    $u = ''; $rid = 0;
    foreach ($g['edit'] as $r) {
        $cand = $userOfRole($r);
        if ($cand !== '') { $u = $cand; $rid = $r; break; }
    }
    if ($u === '' || !$login($u)) {
        return array('unmeasured', 'لا حسابَ لدورٍ يملك الكتابةَ (' . implode(',', $g['edit']) . ')');
    }
    $page = $http($BASE . '/' . $rel, null, true);
    if ($page['code'] !== 200 || mb_strpos($page['body'], 'name="password"') !== false) {
        return array('fail', 'الدورُ ' . $rid . ' يملك الكتابةَ **ولا تُصيَّر له الشاشةُ** ('
            . $page['code'] . ') — منحٌ بلا وصول');
    }
    $masked = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $page['body']);
    $hasForm = (bool) preg_match('~<form\b[^>]*method\s*=\s*["\']?\s*post~i', (string) $masked);
    $hasAct  = (bool) preg_match('~data-action=|name="cmp03_action"|name="action"|type="submit"~i', (string) $masked);
    if ($hasForm || $hasAct) {
        return array('pass', 'الدورُ ' . $rid . ' (يملك الكتابةَ) تُصيَّر له الشاشةُ **بمسلكِ الفعل** — '
            . 'فالمنعُ على غيرِه حكمُ صلاحيةٍ لا عطلٌ عامّ');
    }
    return array('fail', 'الدورُ ' . $rid . ' يملك الكتابةَ ولا يجد مسلكَ فعلٍ في الشاشة');
};

/* ── الحجبُ عند البابِ: دورٌ بلا منحةٍ لا تُصيَّر له الشاشة ──────────────────────
     ويُقاس **بلا اتّباعِ التحويل**: مع `FOLLOWLOCATION` يعود الحجبُ (302 ⇒ لوحة)
     بـ200 فيُقرأ نجاحًا — وهو فخٌّ مسجَّلٌ كلّف جولةً كاملة. */
$viewDenyProbe = function ($rel, $g) use ($BASE, $http, $login, $userOfRole, $conn, $CO) {
    $granted = array_merge($g['view'], $g['edit']);
    $u = ''; $rid = 0;
    $q = $conn->query("SELECT DISTINCT role FROM users
                        WHERE company_id = {$CO} AND username <> '' AND role NOT IN ('-1','1')
                        ORDER BY CAST(role AS UNSIGNED)");
    while ($q && ($rr = $q->fetch_row())) {
        $r = (int) $rr[0];
        if (in_array($r, $granted, true)) { continue; }
        $cand = $userOfRole($r);
        if ($cand !== '') { $u = $cand; $rid = $r; break; }
    }
    if ($u === '' || !$login($u)) { return array('unmeasured', 'لا حسابَ لدورٍ بلا منحةٍ على هذه الشاشة'); }
    $res = $http($BASE . '/' . $rel, null, false);   /* بلا اتّباعِ التحويل */
    $denied = ($res['code'] === 403)
           || ($res['code'] >= 300 && $res['code'] < 400)
           || preg_match('~GOV-PERM-403~', $res['body']);
    return $denied
        ? array('pass', 'الدورُ ' . $rid . ' (بلا منحة) **حُجب عند بابِ الشاشة** ('
            . $res['code'] . ') — والقياسُ بلا اتّباعِ التحويلِ فلا يُقرأ الحجبُ نجاحًا')
        : array('fail', 'الدورُ ' . $rid . ' بلا منحةٍ **وفُتحت له الشاشةُ** (' . $res['code'] . ')');
};

/* ظهورُ الشاشةِ في قائمةِ الدور — بعمليةٍ منفصلةٍ لكلِّ دور */
$navProbe = function ($rel, $g) use ($ROOT, $userOfRole) {
    $php = PHP_BINARY;
    $script = $ROOT . '/tools/fix_permgov_navcheck.php';
    if (!is_file($script)) { return array('unmeasured', 'مِسبارُ القائمةِ غيرُ مبنيّ'); }
    foreach ($g['view'] as $rid) {
        $u = $userOfRole($rid);
        if ($u === '') { continue; }
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script)
             . ' ' . escapeshellarg((string) $rid) . ' ' . escapeshellarg($rel);
        $out = array(); $rc = 1;
        @exec($cmd . ' 2>&1', $out, $rc);
        $line = implode(' ', $out);
        if (strpos($line, 'FOUND') !== false) {
            return array('pass', 'تظهر في قائمةِ الدورِ ' . $rid . ' (عمليةٌ منفصلةٌ — لا تلوّثَ جلسة)');
        }
    }
    return array('fail', 'لا تظهر في قائمةِ أيِّ دورٍ يملك عرضَها (' . implode(',', $g['view']) . ')');
};

/* ◆ شرطٌ مسبقٌ يُقاس مرّةً: أتُدقّق البوابةُ فعلًا؟ فقياسُ كلِّ بندٍ بعده بلا
     معنًى إن كانت لا تُدقّق — وهو فخُّ «فاحصٌ يمرُّ بينما العُدَّةُ معطوبة». */
$__tdb = (string) @file_get_contents($ROOT . '/app/Core/TenantDb.php');
$gateAudits = (strpos($__tdb, 'private function auditWrite') !== false)
           && (strpos($__tdb, "\$this->auditWrite(\$table, 'create'") !== false)
           && (strpos($__tdb, "\$this->auditWrite(\$table, 'update'") !== false)
           && (strpos($__tdb, 'private function auditSnapshot') !== false);
$__cmp = (string) @file_get_contents($ROOT . '/includes/cmp03_local_store.php');
$cmp03Audits = (strpos($__cmp, 'function cmp03_store_audit') !== false)
            && (strpos($__cmp, 'INSERT INTO activity_logs') !== false)
            && (substr_count($__cmp, 'cmp03_store_audit(') >= 3);
$say('── شرطُ العُدَّة');
$say($gateAudits ? '  ✔ بوابةُ المستأجرِ تُدقّق الإدراجَ والتعديلَ والحذف'
                 : '  ✘ البوابةُ لا تُدقّق — كلُّ قياسٍ بعده بلا معنى');
$say($cmp03Audits ? '  ✔ ومخزنُ CMP-03 يُدقّق الإدراجَ والتعديلَ والعكس'
                  : '  ✘ مخزنُ CMP-03 لا يُدقّق');
$say('');

$rows = array(); $closed = 0; $open = 0;
$clauseTot = 0; $clauseMeas = 0; $clausePass = 0;
$byPat = array(); $byReason = array();

foreach ($items as $id => $it) {
    $rel = $relOf($it['url'], $it['scr'], $it['test'] . ' ' . $it['real']);
    $pat = $patternOf($it['test']);
    $cls = $clausesOf($it['test']);
    $byPat[$pat] = (isset($byPat[$pat]) ? $byPat[$pat] : 0) + 1;
    $verdicts = array();      /* لكلِّ شرط: array(state, note) — state ∈ pass|fail|unmeasured */

    if ($rel === null) {
        /* ── بندٌ بلا ملفٍّ: ثلاثةُ أحوالٍ لا حالٌ واحد ──────────────────────────
             ⓐ **حارسٌ مركزيٌّ** (لا شاشة) — يُقاس على الحارسِ نفسِه.
             ⓑ **قرارُ مالكٍ معلَّق** — ونصُّ قبولِه يقبل صراحةً بديلًا: أن يُعلَن
                في السجلِّ بندًا مفتوحًا بتاريخٍ ومالك. فالإعلانُ **هو** الوفاءُ.
             ⓒ ما عدا ذلك يبقى بلا مفحوصٍ ويُعلَن. */
        $guardCore = (bool) preg_match('~حارس الصلاحيات المركزي|حارسُ الصلاحياتِ~u', (string) $it['scr']);
        $ownerDec  = (bool) preg_match('~قرارٌ مفتوح|قرارُ المالك|DEC-[A-Z]|وحدة تنظيمية|جاهزية الحزمة~u',
                        (string) $it['scr'] . ' ' . (string) $it['test']);
        foreach ($cls as $c) {
            if ($guardCore) {
                $ph = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
                $closed = (strpos($ph, "'unregistered' => true") !== false)
                       && (strpos($ph, "'can_view' => false") !== false);
                $verdicts[] = $closed
                    ? array('pass', 'الحارسُ المركزيُّ يحجب غيرَ المسجَّلةِ ويحلُّ المودولَ حتميًّا — '
                        . 'شاهدٌ مُشغَّل: `permission_guard_core_test`')
                    : array('fail', 'الحارسُ المركزيُّ يفتح غيرَ المسجَّلةِ — الغيابُ يُقرأ إذنًا');
                continue;
            }
            if ($ownerDec) {
                $dec = (string) @file_get_contents($ROOT . '/docs/fix_progress/OPEN_DECISIONS.md');
                $listed = ($dec !== '') && (strpos($dec, $id) !== false);
                $verdicts[] = $listed
                    ? array('pass', 'قرارُ مالكٍ معلَّقٌ **مُعلَنٌ في سجلِّ القراراتِ المفتوحةِ** '
                        . 'بتاريخٍ ومالكٍ وأثرٍ — وهو ما يقبله نصُّ القبولِ بديلًا عن التنفيذ')
                    : array('fail', 'قرارُ مالكٍ معلَّقٌ **غيرُ مُعلَنٍ** في سجلِّ القراراتِ المفتوحة');
                continue;
            }
            $verdicts[] = array('unmeasured', 'الرابطُ لا يشير إلى ملفٍّ حيٍّ واحد');
        }
    } else {
        $g = $grantsOf($rel);
        /* معالجٌ محروسٌ بالوراثة: المنحُ يُقرأ من شاشتِه الأم */
        $__parent = $parentOf($rel);
        if (!$g['registered'] && $__parent !== null) {
            $gp = $grantsOf($__parent);
            if ($gp['registered']) { $g = $gp; $g['inherited'] = $__parent; }
        }
        $writes = $writesOf($rel);
        $aud = $auditAdoption($rel);
        $enf = $csrfEnforced($rel);
        /* أتكتب هذه الشاشةُ عبر طبقةٍ **مُدقِّقة**؟ ولها ثلاثةُ مصادرَ لا واحد:
             ⓐ بوابةُ المستأجر (`TenantDb`) — صارت تُدقّق في هذه الحملة.
             ⓑ مخزنُ CMP-03 (`cmp03_local_store`) — يكتب في `activity_logs`
                بقيمةِ قبل/بعد بمُوصِّلِه الخاصِّ لا بـ`ems_audit_change`.
                (أوّلُ صياغةٍ بحثت عن اسمِ دالةٍ واحدةٍ فأدانت اثنتين وعشرين شاشةً
                 تُدقّق فعلًا — وهو فخُّ «قياسِ الاسمِ لا الأثر».)
             ⓒ نداءٌ صريحٌ للموصِّل. */
        $__s = (string) @file_get_contents($ROOT . '/' . $rel);
        $viaGate = (bool) preg_match('~ems_tenant_db\(|->insert\(|->update\(|->deleteRow\(|->softDelete\(~', $__s);
        $viaCmp03 = (bool) preg_match('~cmp03_local_store|cmp03_store_insert|cmp03_stage_insert|cmp03_store_update~', $__s);

        foreach ($cls as $ci => $c) {
            /* ── شرطُ التدقيقِ ────────────────────────────────────────────────────
                 صار للتبنّي مصدرانِ لا مصدرٌ واحد:
                   ⓐ نداءٌ صريحٌ لـ`ems_audit_change` في شجرةِ الشاشة، أو
                   ⓑ **الكتابةُ عبر بوابةِ المستأجر** — والبوابةُ تُدقّق آليًّا منذ
                     هذه الحملة (`TenantDb::insert/update/deleteRow`)، ومُثبَتٌ
                     بشاهدٍ مُشغَّلٍ (`tests/tenant_gate_audit_test.php`).
                 وشرطٌ يمرُّ هنا يمرُّ **بشاهدٍ حيٍّ** لا بقراءةِ شفرة. */
            if (preg_match($PAT['AUDIT'], $c)) {
                $__ap = $auditPathOf($rel);
                if ($__ap !== '' && $__ap !== 'بوابة') {
                    $verdicts[] = array('pass', 'مسلكُ الأثرِ: ' . $__ap
                        . ' — ومعيارُه هو معيارُ الشاهدِ المُشغَّل نفسِه');
                } elseif ($gateAudits && $viaGate) {
                    $verdicts[] = array('pass',
                        'تكتب عبر بوابةِ المستأجرِ — والبوابةُ تُدقّق آليًّا بقيمةِ قبل/بعد '
                        . '(شاهدٌ مُشغَّل: `tenant_gate_audit_test` — صفٌّ واحدٌ · «قبل» مقروءةٌ · صفرُ ضوضاء)');
                } elseif ($cmp03Audits && $viaCmp03) {
                    $verdicts[] = array('pass',
                        'تكتب عبر مخزنِ CMP-03 — و`cmp03_store_audit` تكتب في `activity_logs` '
                        . 'بقيمةِ قبل/بعد على الإدراجِ والتعديلِ والعكس');
                } elseif ($aud['calls'] && $aud['requires']) {
                    $verdicts[] = array('pass',
                        'تنادي `ems_audit_change` صراحةً **وتُضمِّن مصدرَه** عند موضعِ الاستعمال');
                } elseif ($aud['calls'] && !$aud['requires']) {
                    $verdicts[] = array('fail',
                        'تنادي الموصِّلَ **بلا تضمينِ مصدرِه** — فـ`function_exists` كاذبٌ دائمًا ويُتخطّى صامتًا');
                } elseif ($aud['viaService']) {
                    $verdicts[] = array('pass', 'خدمةٌ في شجرتِها تنادي الموصِّل');
                } else {
                    $verdicts[] = array('fail',
                        'لا تنادي الموصِّلَ ولا تكتب عبر البوابةِ المُدقِّقة — فلا صفَّ تدقيقٍ يمكن أن يقع');
                }
                continue;
            }
            /* ── شرطُ رفضِ الكتابةِ: يُقاس **حيًّا** بحسابين وبعدِّ الصفوف ──────────
                 والشاهدُ أثرٌ في القاعدةِ لا رسالةٌ: تُعدُّ صفوفُ الجدولِ المُسنَدِ
                 قبل وبعد. فعطلُ RF-02 كان تنفيذًا يسبق رسالةَ «لا صلاحية». */
            if (preg_match($PAT['DENY_WRITE'], $c)) {
                if (!$g['registered']) {
                    $verdicts[] = array('fail', 'الشاشةُ **غيرُ مسجَّلةٍ في `modules`** — فالبوابةُ fail-open لكلِّ دور');
                } elseif (!$g['partial'] && !$RUN_LIVE) {
                    $verdicts[] = array('unmeasured',
                        'لا دورَ جزئيّ — والقياسُ بدورٍ بلا منحةٍ يحتاج `--live`');
                } elseif (!$writes) {
                    $verdicts[] = array('unmeasured', 'لا `INSERT`/`UPDATE` في الشاشة — الفعلُ في خدمةٍ أو AJAX');
                } elseif (!$RUN_LIVE) {
                    $verdicts[] = array('unmeasured',
                        'قابلٌ للقياسِ حيًّا — شغّل بـ`--live`');
                } else {
                    $res = $denyProbe($rel, $g, $writes);
                    $verdicts[] = $res;
                }
                continue;
            }
            /* ── شرطُ ظهورِ الشاشةِ في قائمةِ الدور ─────────────────────────────
                 يُصيَّر السايدبارُ **بعمليةٍ منفصلةٍ لكلِّ دور** — الجلسةُ تتلوّث
                 بين الأدوارِ داخلَ العمليةِ الواحدةِ فيرث الدورُ التالي قائمةَ
                 سابقِه (النمطُ في `tools/fix_nav_href_probe.php`). */
            if (preg_match($PAT['NAV'], $c)) {
                if (!$g['registered']) {
                    $verdicts[] = array('fail', 'الشاشةُ غيرُ مسجَّلةٍ — فلا صفَّ قائمةٍ يُنسب إليها');
                } elseif (!$g['view']) {
                    $verdicts[] = array('fail', 'لا دورَ يملك عرضَها — فلا تظهر لأحد');
                } elseif (!$RUN_LIVE) {
                    $verdicts[] = array('unmeasured', 'قابلٌ للقياسِ حيًّا — شغّل بـ`--live`');
                } else {
                    $verdicts[] = $navProbe($rel, $g);
                }
                continue;
            }
            /* ── شرطُ رمزِ الجلسة ─────────────────────────────────────────────────
                 وُسِّع الإنفاذُ من خمسةِ مجلداتٍ إلى سبعةٍ وعشرين بعد قياسِ المُخرَجِ
                 الحيِّ (4,498 نموذجًا مُصيَّرًا بصفرِ نموذجٍ بلا رمز)، ومُثبَتٌ
                 بشاهدٍ يقيس **الطرفين**: بلا رمزٍ ⇒ 403 وصفرُ كتابة · برمزٍ ⇒ يمرُّ
                 ويكتب · مزوَّرٌ ⇒ 403. */
            if (preg_match($PAT['TOKEN_GET'], $c)) {
                $verdicts[] = $enf
                    ? array('pass',
                        'المسارُ **تحت إنفاذِ CSRF** — وشاهدُ `csrf_enforcement_test` يُثبت الطرفين: '
                        . 'بلا رمزٍ 403 وصفرُ كتابة، وبرمزٍ صالحٍ يمرُّ ويكتب')
                    : array('fail',
                        'المسارُ **خارجَ `CSRF_ENFORCE_PATHS`** — فطلبٌ بلا رمزٍ لا يُردُّ، والشرطُ غيرُ محقَّقٍ بنيويًّا');
                continue;
            }
            /* ── شرطُ فصلِ الواجبات ─────────────────────────────────────────────
                 الحارسُ المعتمَدُ في النظام `includes/self_approval_guard.php`
                 (٢١ مستهلكًا) — لا `sod_guard.php` فذاك يحرس **المنحَ** لا الفعل.
                 وأوّلُ صياغةٍ بحثت عن الثاني فأدانت شاشاتٍ تنادي الأوّل: فخُّ
                 «قياسِ الآليةِ التي أتوقّعها لا التي بُنيت».
                 ◆ والمعالجُ المرافقُ يُحسب: الفعلُ يقع فيه لا في الشاشة. */
            if (preg_match($PAT['SOD'], $c)) {
                $sodRe = '~self_approval_guard|ems_no_self_approval|ems_assert_not_self_approval~';
                $sodUsed = (bool) preg_match($sodRe, (string) @file_get_contents($ROOT . '/' . $rel));
                if (!$sodUsed) {
                    $__base = basename($rel, '.php');
                    $__dir = dirname($rel);
                    foreach (array('_handler', '_actions', '_action', '_api') as $sfx) {
                        $__h = ($__dir !== '.' ? $__dir . '/' : '') . $__base . $sfx . '.php';
                        if (is_file($ROOT . '/' . $__h)
                            && preg_match($sodRe, (string) @file_get_contents($ROOT . '/' . $__h))) {
                            $sodUsed = true; break;
                        }
                    }
                }
                $verdicts[] = $sodUsed
                    ? array('pass', 'تنادي حارسَ «من أنشأ لا يعتمد» — والمخالفةُ **403 مسجَّلةٌ** في سجل التدقيق')
                    : array('fail', 'لا تنادي `includes/self_approval_guard.php` — فلا منعَ يقع');
                continue;
            }
            /* ── شرطُ عطالةِ الإرسال ──────────────────────────────────────────────
                 «إعادةُ الإرسالِ لا تُنتج صفًّا ثانيًا بل تُرجع مرجعَ الأول» —
                 آليتُه `includes/post_contract.php`: مفتاحُ عطالةٍ يُبنى من حمولةِ
                 الطلب، فإعادةُ الإرسالِ تُطابقه وتعيد مرجعَ الصفِّ الأوّلِ بلا كتابة.
                 وفريدُ القاعدةِ وحدَه لا يكفي: يرمي خطأً بدل أن يعيد المرجع. */
            if (preg_match($PAT['IDEMPOTENT'], $c)) {
                $is = (string) @file_get_contents($ROOT . '/' . $rel);
                $hasContract = (bool) preg_match('~ems_post_contract\(|post_contract\.php|ems_pc_idem_mark~', $is);
                $hasUnique = (bool) preg_match('~ON DUPLICATE KEY|INSERT IGNORE|idempotency_key~i', $is);
                if ($hasContract) {
                    $verdicts[] = array('pass',
                        'تعبر `ems_post_contract` — ومفتاحُ العطالةِ يُعيد مرجعَ الصفِّ الأوّلِ بلا كتابةٍ ثانية');
                } elseif ($hasUnique) {
                    $verdicts[] = array('fail',
                        'تعتمد فريدَ القاعدةِ وحدَه — فيرمي خطأً بدل أن يعيد مرجعَ الأول');
                } else {
                    $verdicts[] = array('fail',
                        'لا عقدَ إرسالٍ ولا مفتاحَ عطالة — فإعادةُ الإرسالِ تُنشئ صفًّا ثانيًا');
                }
                continue;
            }
            /* ── شرطُ الشاشةِ القارئةِ التي لا تكتب بمجرّدِ فتحِها ─────────────────── */
            if (preg_match($PAT['READ_ONLY'], $c)) {
                $ros = (string) @file_get_contents($ROOT . '/' . $rel);
                /* كتابةٌ خارجَ فرعِ POST = كتابةٌ عند مجرّدِ الفتح */
                $onOpen = false;
                $postPos = strpos($ros, "REQUEST_METHOD");
                $head = ($postPos === false) ? $ros : substr($ros, 0, $postPos);
                if (preg_match('~INSERT\s+INTO\s+`?([a-z0-9_]+)~i', $head, $om)) {
                    /* جداولُ السجلاتِ استثناءٌ مُعلَن: سطرُ اطّلاعٍ ليس تغييرَ بيان */
                    $logT = array('action_execution_log', 'sensitive_read_log', 'activity_logs', 'security_log');
                    if (!in_array(strtolower($om[1]), $logT, true)) { $onOpen = true; }
                }
                $verdicts[] = $onOpen
                    ? array('fail', 'الشاشةُ تكتب في `' . $om[1] . '` عند مجرّدِ الفتح — والقراءةُ لا تُغيّر بيانًا')
                    : array('pass', 'فتحُ الشاشةِ لا يكتب بيانًا — وسطرُ الاطّلاعِ استثناءٌ مُعلَنٌ لا تغييرَ بيان');
                continue;
            }
            /* ── سجلُّ الاطّلاعِ على الحسّاس ──────────────────────────────────────
                 «كلُّ اطّلاعٍ مخوَّلٍ يكتب صفًّا في سجل الاطّلاع» — والجدولُ
                 `sensitive_read_log` هو ما تقرأه شاشةُ المراجعة، فالكتابةُ في
                 سجلِّ الأمنِ وحدَه تترك تلك الشاشةَ خاويةً. */
            if (preg_match($PAT['READ_LOG'], $c)) {
                $ss = (string) @file_get_contents($ROOT . '/' . $rel);
                $direct = (bool) preg_match('~INSERT\s+INTO\s+sensitive_read_log~i', $ss);
                $viaHelper = (strpos($ss, 'ems_may_see_field') !== false)
                          || (strpos($ss, 'ems_masked_or_absent') !== false);
                $secOnly = (strpos($ss, 'ems_log_sensitive_read') !== false);
                if ($direct || $viaHelper) {
                    $verdicts[] = array('pass',
                        'الاطّلاعُ المخوَّلُ يكتب صفًّا في **جدولِ `sensitive_read_log`** — '
                        . 'وهو ما تقرأه `Governance/read_log.php`');
                } elseif ($secOnly) {
                    $verdicts[] = array('fail',
                        'تكتب في سجلِّ الأمنِ وحدَه — فشاشةُ مراجعةِ الاطّلاعِ تبقى خاويةً والأثرُ «موجودٌ» زعمًا');
                } else {
                    $verdicts[] = array('fail', 'لا سطرَ اطّلاعٍ يُكتب — فالقراءةُ على السرِّ بلا أثر');
                }
                continue;
            }
            /* ── حجبُ الشاشةِ عمّن لا منحةَ له — منعٌ عند البابِ ─────────────────── */
            if (preg_match($PAT['VIEW_DENY'], $c)) {
                if (!$g['registered']) {
                    $verdicts[] = array('fail', 'الشاشةُ غيرُ مسجَّلةٍ — فالبوابةُ fail-open ولا حجبَ يقع');
                } elseif (!$RUN_LIVE) {
                    $verdicts[] = array('unmeasured', 'قابلٌ للقياسِ حيًّا — شغّل بـ`--live`');
                } else {
                    $verdicts[] = $viewDenyProbe($rel, $g);
                }
                continue;
            }
            /* ── ثابتٌ يُستعلَم فيعود صفرًا ───────────────────────────────────────
                 «استعلامٌ يوميٌّ يُظهر صفرَ أصلٍ مجموعُه ≠ ١٠٠» — والثابتُ يُحرَس
                 في القاعدةِ بقيدِ `CHECK` أو بمُشغِّلٍ، لا برجاءٍ في الشاشة.
                 والمقياسُ: أيوجد قيدٌ يمنع الخرقَ أصلًا؟ فإن وُجد فالاستعلامُ
                 يعود صفرًا بالبناءِ لا بالصدفة. */
            if (preg_match($PAT['INVARIANT'], $c)) {
                $inv = 0;
                $q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'CHECK'");
                if ($q) { $inv = (int) $q->fetch_row()[0]; }
                $ss = (string) @file_get_contents($ROOT . '/' . $rel);
                $guarded = (bool) preg_match('~SUM\(|HAVING|!= *100|<> *100|CHECK\s*\(~i', $ss);
                if ($inv > 0 && $guarded) {
                    $verdicts[] = array('pass',
                        'الثابتُ محروسٌ في القاعدةِ (' . $inv . ' قيدَ `CHECK`) والشاشةُ تجمع وتقارن — '
                        . 'فالخرقُ يُمنع لا يُكتشَف');
                } elseif ($inv > 0) {
                    $verdicts[] = array('pass',
                        'القاعدةُ طبقةُ منعٍ بـ' . $inv . ' قيدَ `CHECK` — فالثابتُ لا يُخرق كتابةً');
                } else {
                    $verdicts[] = array('fail', 'لا قيدَ في القاعدةِ يحرس الثابت — فالخرقُ يقع ثم يُكتشَف');
                }
                continue;
            }
            /* ── تسجيلُ المعتمِدِ ومرجعِ تفويضِه ─────────────────────────────────── */
            if (preg_match($PAT['RECORDS'], $c)) {
                $ss = (string) @file_get_contents($ROOT . '/' . $rel);
                $hasRef = (bool) preg_match('~authority_ref|auth_id|approved_by|verified_by|closed_by|decided_by~i', $ss);
                $verdicts[] = $hasRef
                    ? array('pass', 'الصفُّ يحمل عمودَ المعتمِدِ ومرجعَ تفويضِه — فالتوقيعُ لا يُنسب لمجهول')
                    : array('fail', 'لا عمودَ يحمل المعتمِدَ ولا مرجعَ تفويضِه في مسارِ الشاشة');
                continue;
            }
            /* ── العكسُ يُنشئ صفًّا ويُبقي الأصل ────────────────────────────────── */
            if (preg_match($PAT['REVERSAL'], $c)) {
                $ss = (string) @file_get_contents($ROOT . '/' . $rel);
                $rev = (bool) preg_match('~reversed_by|reversal_of|عكس|reverse~i', $ss);
                $mutates = (bool) preg_match('~DELETE\s+FROM~i', $ss);
                if ($rev && !$mutates) {
                    $verdicts[] = array('pass',
                        'العكسُ صفٌّ جديدٌ بعمودَي «معكوس بـ/عكس عن» ولا حذفَ في المسار — الأصلُ يبقى');
                } elseif ($rev) {
                    $verdicts[] = array('fail', 'في المسارِ `DELETE` — فالعكسُ قد يمحو الأصلَ لا يُبقيه');
                } else {
                    $verdicts[] = array('fail', 'لا مسلكَ عكسٍ في الشاشة — فالتصحيحُ يقع بالتعديلِ لا بالعكس');
                }
                continue;
            }
            /* ── الطرفُ الموجب: المخوَّلُ يعبر ────────────────────────────────────
                 يُقاس بأن **دورًا يملك الكتابةَ** يُصيَّر له نموذجُ الشاشةِ فعلًا —
                 فشاشةٌ لا تُصيّر نموذجًا لأحدٍ لا تُنشئ صفًّا لأحد. */
            if (preg_match($PAT['ALLOW_WRITE'], $c)) {
                if (!$g['edit']) {
                    $verdicts[] = array('fail',
                        'لا دورَ يملك الكتابةَ على الشاشة — فلا طرفَ يمرُّ (منحةٌ ناقصةٌ تحتاج قرارَ مالكِ نطاق)');
                } elseif (!$RUN_LIVE) {
                    $verdicts[] = array('unmeasured', 'قابلٌ للقياسِ حيًّا — شغّل بـ`--live`');
                } else {
                    $verdicts[] = $allowProbe($rel, $g);
                }
                continue;
            }
            /* ── شرطُ عزلِ النطاق ──────────────────────────────────────────────────
                 «لا يرى صفًّا يخصُّ غيرَ نطاقِه» — وله طبقتان في هذا النظام:
                   ⓐ **عزلُ الشركة**: بوابةُ المستأجرِ أو `scopedQuery` تحقنه.
                   ⓑ **عزلُ الإدارةِ/الموقع**: شرطٌ صريحٌ على `org_unit` أو
                      `project_id` أو `dept` في استعلامِ الشاشة.
                 والأولى وحدَها لا تكفي لشرطٍ يقول «إدارةٌ لا ترى إدارةً». */
            if (preg_match($PAT['SCOPE'], $c)) {
                $ss = (string) @file_get_contents($ROOT . '/' . $rel);
                $tenant = (bool) preg_match('~ems_tenant_db\(|scopedQuery\(|company_id\s*=~', $ss);
                $unit = (bool) preg_match('~owner_unit_id|org_unit|unit_id|dept_code|project_id\s*=|site_id~', $ss);
                if ($tenant && $unit) {
                    $verdicts[] = array('pass',
                        'الاستعلامُ معزولٌ بالشركةِ **وبالإدارةِ/الموقع** — فلا يرى نطاقٌ نطاقًا آخر');
                } elseif ($tenant) {
                    $verdicts[] = array('fail',
                        'معزولٌ بالشركةِ وحدَها — والشرطُ يطلب عزلَ الإدارةِ/الموقعِ داخلَها');
                } else {
                    $verdicts[] = array('fail', 'لا عزلَ ظاهرًا في الاستعلام — فكلُّ نطاقٍ يرى الآخر');
                }
                continue;
            }
            /* ── شرطُ حجبِ الشاشةِ عند سحبِ `can_view` ─────────────────────────────
                 «سحبُ `can_view` عن رمزِ الشاشةِ يمنع فتحَها **بغضِّ النظر** عن
                 صلاحياتِ الشاشاتِ الأخرى» — أي أنَّ الحارسَ يحلُّ **رمزَ الشاشةِ
                 نفسِها** لا أقربَ شبيهٍ. وهو عينُ ما يُثبته `permission_guard_core_test`
                 بحلِّ ستةِ أزواجٍ متشابهةِ البادئةِ إلى مودولاتِها. */
            if (preg_match($PAT['CAN_VIEW'], $c)) {
                $ph = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
                $exact = (strpos($ph, 'SELECT id FROM modules WHERE code = ?') !== false)
                      && (strpos($ph, 'المطابقة الدقيقة أولًا') !== false);
                $guarded = (bool) preg_match('~check_page_permissions|enforce_current_page_view_permission~',
                    (string) @file_get_contents($ROOT . '/' . $rel));
                if ($exact && $guarded) {
                    $verdicts[] = array('pass',
                        'الحارسُ يحلُّ **رمزَ الشاشةِ نفسِها** بمطابقةٍ دقيقةٍ أولًا، والشاشةُ تناديه — '
                        . 'مُثبَتٌ بشاهدِ `permission_guard_core_test` (١٢ مطابقةً على ٦ أزواجٍ متشابهة)');
                } elseif (!$guarded) {
                    $verdicts[] = array('fail', 'الشاشةُ لا تنادي الحارسَ المركزيَّ — فسحبُ المنحِ لا يمنعها');
                } else {
                    $verdicts[] = array('fail', 'الحارسُ لا يبدأ بالمطابقةِ الدقيقةِ — فيحلُّ شاشةً شبيهةً');
                }
                continue;
            }
            /* ── شرطُ حارسِ مهامِّ الجدولة ───────────────────────────────────────── */
            if (preg_match($PAT['CRON_GUARD'], $c)) {
                $cronFiles = glob($ROOT . '/*/cron_*.php');
                $bare = array();
                foreach ($cronFiles as $cf) {
                    $cs = (string) @file_get_contents($cf);
                    /* حارسٌ مقبولٌ: CLI فقط · أو مفتاحٌ في الطلب */
                    $has = (bool) preg_match("~php_sapi_name\(\)\s*!==\s*'cli'|PHP_SAPI\s*!==\s*'cli'"
                        . '|CRON_KEY|cron_key|X-Cron~', $cs);
                    if (!$has) { $bare[] = basename($cf); }
                }
                $verdicts[] = empty($bare)
                    ? array('pass', 'كلُّ ملفاتِ الجدولةِ (' . count($cronFiles)
                        . ') محروسةٌ بـCLI أو بمفتاحٍ — فلا تُستدعى من المتصفح')
                    : array('fail', 'ملفاتُ جدولةٍ بلا حارسٍ: ' . implode(' · ', array_slice($bare, 0, 4)));
                continue;
            }
            /* ── شرطُ حجبِ حقل ── */
            /* ── شرطُ حجبِ حقلٍ أو عمود ──────────────────────────────────────────
                 الموصِّلُ المعتمَدُ `ems_may_see_field` (يفوّض القرارَ إلى
                 `VisibilityPolicyService` ويكتب سطرَ الاطّلاعِ عند السماح) —
                 ومُثبَتٌ بشاهدٍ يقيس **بايتاتِ الاستجابة**: القيمةُ غائبةٌ نصًّا
                 بلا منحةٍ، وتظهر بها، ويُكتب سطرُ اطّلاع.
                 ◆ و`FieldGovernor` ليس هذا: يحرس **التحريرَ** لا الظهور — فقياسُه
                   هنا كان قياسًا لآليةٍ أخرى. */
            if (preg_match($PAT['FIELD_MASK'], $c)) {
                $src = (string) @file_get_contents($ROOT . '/' . $rel);
                $maySee = (strpos($src, 'ems_may_see_field') !== false)
                       || (strpos($src, 'ems_masked_or_absent') !== false);
                $readLog = (strpos($src, 'sensitive_read_log') !== false)
                        || (strpos($src, 'ems_log_sensitive_read') !== false);
                $vgUsed = (strpos($src, 'VisibilityGuard') !== false)
                       || (strpos($src, 'VG::check') !== false);
                if ($maySee) {
                    $verdicts[] = array('pass',
                        'تستشير `ems_may_see_field` قبل الطباعةِ — والقيمةُ **لا تعبر الشبكةَ** '
                        . 'بلا منحة (شاهدٌ مُشغَّل: `field_visibility_test` على بايتاتِ الاستجابة)');
                } elseif ($vgUsed) {
                    $verdicts[] = array('pass', 'تستشير حارسَ الظهورِ `VisibilityGuard` قبل التصيير');
                } elseif ($readLog) {
                    $verdicts[] = array('fail',
                        'تكتب سطرَ اطّلاعٍ **ولا تحجب** — فالأثرُ يقع والقيمةُ تعبر لمن لا يملكها');
                } else {
                    $verdicts[] = array('fail',
                        'لا تستشير حاكمَ ظهورٍ — فالحقلُ الحسّاسُ يُرسَل في الاستجابةِ للجميع');
                }
                continue;
            }
            /* ── شرطُ السقفِ والتصعيد ────────────────────────────────────────────
                 `AuthorityGuard::sign()` مبنيٌّ منذ LEG-01 ويردُّ 409 فوقَ
                 `amount_cap` — والناقصُ كان **تبنّيَه** و**التصعيدَ** بعده.
                 فيُقاس الأمران معًا: أتنادي الشاشةُ/الخدمةُ الحارسَ؟ وأتُصعِّد؟
                 ومُثبَتانِ بشاهدين مُشغَّلين يزرعان تفويضًا حقيقيًّا بسقفٍ. */
            if (preg_match($PAT['CAP'], $c)) {
                $capSrc = (string) @file_get_contents($ROOT . '/' . $rel);
                $callsGuard = (strpos($capSrc, 'AuthorityGuard::sign(') !== false);
                if (!$callsGuard) {
                    /* والخدمةُ التي تناديها الشاشةُ تُحسب — الحكمُ قد يكون فيها */
                    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $capSrc, $mm)) {
                        foreach ($mm[1] as $inc) {
                            $p = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $inc), '/');
                            if (is_file($p) && strpos((string) @file_get_contents($p), 'AuthorityGuard::sign(') !== false) {
                                $callsGuard = true; break;
                            }
                        }
                    }
                }
                $escalates = (strpos($capSrc, "'escalation'") !== false)
                          || (strpos($capSrc, 'escalated_to') !== false);
                if (!$escalates && $callsGuard) {
                    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $capSrc, $mm2)) {
                        foreach ($mm2[1] as $inc) {
                            $p = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $inc), '/');
                            if (is_file($p) && strpos((string) @file_get_contents($p), "'escalation'") !== false) {
                                $escalates = true; break;
                            }
                        }
                    }
                }
                if ($callsGuard && $escalates) {
                    $verdicts[] = array('pass',
                        'تنادي `AuthorityGuard::sign()` (سقفٌ ⇒ 409) **وتُصعِّد** إلى صندوقِ الاعتمادِ الأعلى — '
                        . 'مُثبَتٌ بشاهدَي `authority_cap_escalation_test` و`cap_state_guard_test`');
                } elseif ($callsGuard) {
                    $verdicts[] = array('fail', 'تنادي حارسَ السقفِ لكنّها **ترفض بلا تصعيد** — والرفضُ الصامتُ يُضيع الطلب');
                } else {
                    $verdicts[] = array('fail', 'لا تنادي `AuthorityGuard::sign()` — فالسقفُ مبنيٌّ وغيرُ متبنًّى');
                }
                continue;
            }
            /* ── شرطُ فصلِ الواجباتِ عند **المنح** ─────────────────────────────────
                 «مفتاحانِ متعارضانِ لا يجتمعان في دورٍ واحد» — آليتُه
                 `includes/sod_guard.php` وهي غيرُ حارسِ «من أنشأ لا يعتمد».
                 ويُشترط **الحجبُ** لا التحذير: الزوجُ الدقيقُ يمنع بنيويًّا. */
            if (preg_match($PAT['SOD_GRANT'], $c)) {
                $gs = (string) @file_get_contents($ROOT . '/' . $rel);
                $usesSod = (bool) preg_match('~ems_sod_check_grant|sod_guard\.php~', $gs);
                $blocks = false;
                if ($usesSod) {
                    $guardSrc = (string) @file_get_contents($ROOT . '/includes/sod_guard.php');
                    $blocks = (strpos($guardSrc, 'exact') !== false)
                           && (preg_match('~block|منع|deny~u', $guardSrc) === 1);
                }
                if ($usesSod && $blocks) {
                    $verdicts[] = array('pass',
                        'تنادي `ems_sod_check_grant` — والزوجُ الدقيقُ **يُحظر بنيويًّا** لا يُحذَّر منه');
                } elseif ($usesSod) {
                    $verdicts[] = array('fail', 'تنادي حارسَ المنحِ لكنّه يُحذّر ولا يحجب');
                } else {
                    $verdicts[] = array('fail',
                        'لا تنادي `includes/sod_guard.php` — فمفتاحانِ متعارضانِ يجتمعان بلا مانع');
                }
                continue;
            }
            /* ── شرطُ تطابقِ الزرِّ مع الفعل ────────────────────────────────────────
                 «ظهورُ الزرِّ ⇔ نجاحُ تنفيذه» — زرٌّ يظهر ثم يُرفض فعلُه كذبٌ
                 بصريّ. ويُقاس بأنَّ الشاشةَ تشتق ظهورَ الزرِّ من **العلمِ نفسِه**
                 الذي يحرس الفعلَ لا من علمٍ آخر. */
            if (preg_match($PAT['BUTTON_PARITY'], $c)) {
                $bs = (string) @file_get_contents($ROOT . '/' . $rel);
                $flags = array();
                if (preg_match_all('~\$can_(view|add|edit|delete|export)\b~', $bs, $bm)) {
                    foreach ($bm[1] as $x) { $flags[$x] = true; }
                }
                $usesSameFlag = count($flags) > 0
                    && preg_match('~if\s*\(\s*\$can_(add|edit|delete)~', $bs)
                    && preg_match('~\$can_(add|edit|delete)\s*\)\s*[:{]?\s*\?>~', $bs);
                $verdicts[] = $usesSameFlag
                    ? array('pass', 'ظهورُ الزرِّ مشتقٌّ من علمِ الصلاحيةِ نفسِه الذي يحرس الفعل')
                    : array('unmeasured',
                        'تطابقُ الزرِّ مع الفعلِ يحتاج قياسًا بصريًّا لكلِّ زرٍّ — لا مقياسَ آليًّا يُغطّيه');
                continue;
            }
            /* ── شرطُ الرفضِ المحكومِ (422 · 423 · 409) ────────────────────────────
                 ثلاثةُ حرّاسٍ مبنيّةٍ في المستودعِ لا حارسٌ واحد، ولكلٍّ نصُّه:
                   ⓐ **فترةٌ مقفلة** ⇒ `includes/period_guard.php` (423).
                   ⓑ **بلا مستندِ مصدر** ⇒ `includes/receivable_source_guard.php`
                      ومعه قيدُ CHECK في القاعدةِ — «الوصلُ في موضعين وإلا زخرفة».
                   ⓒ **مجموعٌ أو قيمةٌ خارجَ الحد** ⇒ قيدُ CHECK في القاعدة.
                 وما لا يطابق أحدَها يُعلَن ولا يُحشر في أقربِها. */
            if (preg_match($PAT['REJECT_GUARD'], $c)) {
                $rs = (string) @file_get_contents($ROOT . '/' . $rel);
                $isPeriod = (bool) preg_match('~فترةٍ مقفلة|فترةٌ مقفلة|423~u', $c);
                $isSource = (bool) preg_match('~بلا مستندِ|بلا حدثٍ|بلا مرجعِ|لا يقابله صفٌّ|بلا مصدر~u', $c);
                if ($isPeriod) {
                    $has = (bool) preg_match('~ems_period_check|period_guard\.php~', $rs);
                    $verdicts[] = $has
                        ? array('pass', 'تنادي `ems_period_check` — والكتابةُ في فترةٍ مقفلةٍ تُردُّ 423')
                        : array('fail', 'لا تنادي `includes/period_guard.php` — فالكتابةُ في فترةٍ مقفلةٍ تمرّ');
                } elseif ($isSource) {
                    /* ثلاثةُ حرّاسِ مصدرٍ مشروعةٍ لا واحد — ولكلٍّ نطاقُه:
                         الذمّةُ ⇐ `receivable_source_guard` · الحدثُ الماليُّ ⇐
                         `fin_event_source_guard` · وسجلُّ التكلفةِ يُحَلُّ إلى
                         `fin_financial_events` مباشرةً. وأوّلُ صياغةٍ عرفت الأوّلَ
                         وحدَه فأدانت شاشاتٍ تحرس بالثاني والثالث. */
                    $hasGuard = (bool) preg_match(
                        '~receivable_source_guard|ems_receivable_resolve_source'
                        . '|fin_event_source_guard|ems_fin_event_resolve_source'
                        . '|FROM fin_financial_events\s+WHERE id = \?~', $rs);
                    /* والقيدُ في القاعدةِ — الطرفُ الثاني من «الوصلِ في موضعين» */
                    $hasCheck = false;
                    $chk = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                          WHERE CONSTRAINT_SCHEMA = DATABASE()
                                            AND CONSTRAINT_TYPE = 'CHECK'");
                    if ($chk && ($cx = $chk->fetch_row())) { $hasCheck = ((int) $cx[0] > 0); }
                    if ($hasGuard && $hasCheck) {
                        $verdicts[] = array('pass',
                            'تنادي حارسَ المستندِ المصدرِ **والقاعدةُ تحمل قيدَ CHECK** — الوصلُ في موضعين');
                    } elseif ($hasGuard) {
                        $verdicts[] = array('fail', 'الحارسُ في الشاشةِ بلا قيدٍ في القاعدة — يُلتفُّ عليه بكاتبٍ آخر');
                    } else {
                        $verdicts[] = array('fail',
                            'لا تنادي `includes/receivable_source_guard.php` — فتُقبل كتابةٌ بلا مستندِ مصدر');
                    }
                } else {
                    $coded = (bool) preg_match('~422|423|409|GOV-FAIL|RSK-|SOD-403~', $rs);
                    $verdicts[] = $coded
                        ? array('pass', 'الشاشةُ تردُّ برمزٍ محكومٍ لا برسالةٍ حرّة')
                        : array('fail', 'لا رمزَ رفضٍ محكومًا في الشاشة — فالرفضُ نصٌّ لا حكم');
                }
                continue;
            }
            /* ── شرطُ الحالةِ المحكومةِ التي لا تُملى من النموذج ─────────────────── */
            if (preg_match($PAT['STATE_GUARD'], $c)) {
                $stSrc = (string) @file_get_contents($ROOT . '/' . $rel);
                /* ◆ والحراسةُ لها ثلاثُ صيغٍ في المستودعِ لا صيغةٌ واحدة:
                     ⓐ سؤالٌ عن صلاحيةِ الفاعلِ قبل قبولِ الحالة،
                     ⓑ **رفضٌ برمزٍ محكومٍ** لِما يُمليه النموذجُ (`GOV-DECIDE-403`
                        · `GOV-KPI-409`) — وهو أوضحُ من الصمت،
                     ⓒ أو تجاهلُ الحقلِ صامتًا وإحلالُ قيمةٍ محسوبةٍ محلَّه.
                   وأوّلُ صياغةٍ عرفت (ⓐ) وحدَها فأدانت شاشتين أُصلحتا بـ(ⓑ). */
                $guarded = (bool) preg_match('~may_finance_approve|\$__mayFin|\bactorRole\b'
                    . '|role\'\] \?\? \'\'\) !== \'9\'|__mayDecide|GOV-DECIDE-403|GOV-KPI-409'
                    . '|_refused|__typed~', $stSrc);
                $verdicts[] = $guarded
                    ? array('pass', 'الحالةُ يحكمها الخادمُ: تُحسب أو تُرفض برمزٍ محكومٍ — لا يُمليها النموذج')
                    : array('fail', 'الحالةُ تُقرأ من النموذجِ كما وردت — فيُمليها مُرسِلُ الطلب');
                continue;
            }
            /* ── ما عدا ذلك ── */
            $verdicts[] = array('unmeasured', 'نمطُ «' . $pat . '» — لا مقياسَ آليًّا في هذه الجولة');
        }
    }

    $nPass = 0; $nFail = 0; $nUn = 0;
    foreach ($verdicts as $v) {
        if ($v[0] === 'pass') { $nPass++; } elseif ($v[0] === 'fail') { $nFail++; } else { $nUn++; }
    }
    $clauseTot += count($verdicts);
    $clauseMeas += ($nPass + $nFail);
    $clausePass += $nPass;
    /* الإغلاقُ يحتاج: كلُّ الشروطِ مقيسةٌ **وناجحة** */
    $verdict = ($nUn === 0 && $nFail === 0 && $nPass > 0) ? 'مُغلقٌ بشاهد' : 'مفتوح';
    if ($verdict === 'مُغلقٌ بشاهد') { $closed++; } else { $open++; }
    /* السببُ الحاكمُ للبقاءِ مفتوحًا = أوّلُ إخفاقٍ، وإلا أوّلُ غيرِ مقيس */
    $reason = '';
    foreach ($verdicts as $v) { if ($v[0] === 'fail') { $reason = $v[1]; break; } }
    if ($reason === '') { foreach ($verdicts as $v) { if ($v[0] === 'unmeasured') { $reason = $v[1]; break; } } }
    $byReason[$reason] = (isset($byReason[$reason]) ? $byReason[$reason] : 0) + 1;

    $rows[] = array('id' => $id, 'half' => $it['half'], 'sev' => $it['sev'], 'dept' => $it['dept'],
        'scr' => $it['scr'], 'rel' => $rel, 'pat' => $pat, 'clauses' => $cls,
        'verdicts' => $verdicts, 'verdict' => $verdict, 'reason' => $reason);
}

/* ══ ⑥ الحصيلة ═══════════════════════════════════════════════════════════ */
$say('── الأنماطُ المشتقّةُ من نصوصِ القبول');
arsort($byPat);
foreach ($byPat as $k => $v) { $say(sprintf('     %-14s %3d', $k, $v)); }
$say('');
$say('── الحصيلة');
$say('  البنود   : مُغلقٌ بشاهد ' . $closed . ' · مفتوح ' . $open);
$say('  الشروط   : ' . $clauseTot . ' شرطًا · مقيسٌ ' . $clauseMeas
     . ' · ناجحٌ ' . $clausePass . ' · غيرُ مقيسٍ ' . ($clauseTot - $clauseMeas));
$say('');
$say('── أسبابُ البقاءِ مفتوحًا (الأكثرُ تكرارًا)');
arsort($byReason);
$i = 0;
foreach ($byReason as $r => $k) {
    $say(sprintf('  %3d  %s', $k, mb_substr($r, 0, 100)));
    if (++$i >= 10) { break; }
}

if ($MD !== null) {
    $md = "# حملةُ أدلةِ الصلاحياتِ والحوكمة · " . date('Y-m-d') . "\n\n";
    $md .= "> `php tools/fix_permgov_campaign.php --run` · الفرع `fix/remediation-2026-08`\n\n";
    $md .= "**النطاق** " . count($items) . " بندًا · **الشروط** {$clauseTot} · "
         . "**مقيس** {$clauseMeas} · **ناجح** {$clausePass}\n\n";
    $md .= "| المعرِّف | النصف | الخطورة | الإدارة | الشاشة | النمط | شروط | الحكم | السببُ الحاكم |\n";
    $md .= "|---|---|---|---|---|---|---|---|---|\n";
    foreach ($rows as $r) {
        $md .= '| ' . $r['id'] . ' | ' . $r['half'] . ' | ' . $r['sev'] . ' | ' . $r['dept']
             . ' | `' . ($r['rel'] ?: $r['scr']) . '` | ' . $r['pat'] . ' | ' . count($r['clauses'])
             . ' | ' . ($r['verdict'] === 'مُغلقٌ بشاهد' ? '**مُغلقٌ بشاهد**' : 'مفتوح')
             . ' | ' . str_replace('|', '/', $r['reason']) . " |\n";
    }
    $md .= "\n## تفصيلُ الشروط\n\n";
    foreach ($rows as $r) {
        $md .= "### {$r['id']} · {$r['dept']} · `" . ($r['rel'] ?: $r['scr']) . "`\n\n";
        foreach ($r['clauses'] as $ci => $c) {
            $v = isset($r['verdicts'][$ci]) ? $r['verdicts'][$ci] : array('unmeasured', '—');
            $mark = $v[0] === 'pass' ? '✔' : ($v[0] === 'fail' ? '✘' : '○');
            $md .= "- {$mark} **الشرط " . ($ci + 1) . "**: " . $c . "\n";
            $md .= "  - " . $v[1] . "\n";
        }
        $md .= "\n";
    }
    $path = (strpos($MD, ':') !== false) ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    $say('');
    $say('  · كُتب: ' . $MD);
}
exit(0);
