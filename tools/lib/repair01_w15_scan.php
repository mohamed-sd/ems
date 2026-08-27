<?php
/**
 * tools/lib/repair01_w15_scan.php — مكتبةُ قياسِ المرحلةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تقيس ولا تُخزِّن حكمًا**: كلُّ دالّةٍ هنا تُعيد بناءَ ما تقيسه من القرصِ
 *   والمخطَّطِ في كلِّ نداء، فالبوّابةُ لا تقرأ ما خزّنَته أداةُ الاشتقاق.
 *
 * ◆ **وحاجبُ الكتابةِ يقرأ الشيفرةَ نفسَها** لا الدعوى: يفتح ملفَّ كلِّ سطحٍ
 *   وخدمةٍ في مساحاتِ هذه الموجةِ ويرصد `INSERT`/`UPDATE`/`DELETE`/`REPLACE`
 *   ونداءاتِ بوّابةِ العزلِ الكاتبة (`insert`/`update`/`deleteRow`).
 *
 * ◆ **والقاعدةُ منعٌ افتراضيّ**: كلُّ جدولٍ **ليس** في قائمةِ سجلّاتِ المساحةِ
 *   نفسِها ولا في طبقةِ المنصّةِ **يُعَدُّ جدولَ نطاقٍ آخر**، والكتابةُ فيه من
 *   مساحةِ القيادةِ أو مساحةِ عملي **ممنوعة**. ⛔ **ولا سماحَ بالسكوت** —
 *   والقائمةُ معلَنةٌ بسببِ كلِّ صفٍّ فيها، فالسماحُ قرارٌ مكتوبٌ لا إغفال.
 *
 * ◆ **وكاشفُ الحارسِ يتبع تضمينًا محلّيًّا مستوًى واحدًا** (‏درسُ W14 ②):
 *   كاشفٌ يقرأ ملفَّ الشاشةِ وحدَه يقرأ العطبَ في المُنقَّى وهو في الكاشف.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('repair01_w15_one')) {

/** قيمةٌ مفردةٌ من استعلام — والعدمُ null لا صفر. */
function repair01_w15_one(mysqli $conn, $sql)
{
    $r = @$conn->query($sql);
    if (!$r) { return null; }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
}

/** مسمّى المجموعةِ العربيُّ — من مفتاحِ دورةِ العمل. */
function repair01_w15_group_ar($key)
{
    $m = array(
        'vision'    => 'الرؤية الشاملة',
        'periodic'  => 'التقارير الدورية',
        'decision'  => 'صناديق القرار',
        'governance'=> 'القرار والحوكمة العليا',
        'scope'     => 'الرؤية بالنطاق',
        'followup'  => 'المتابعة',
        'personal'  => 'مساحتي الشخصية',
        'daily'     => 'عملي اليومي',
    );
    return isset($m[$key]) ? $m[$key] : $key;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① سجلّاتُ المساحاتِ نفسِها وطبقةُ المنصّة — **معلَنةٌ بسببِها**
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * جداولُ يجوز لمساحةِ الموجةِ أن تكتب فيها — **وكلُّ ما عداها ممنوع**.
 * والسببُ مكتوبٌ لكلِّ صفّ، فالسماحُ قرارٌ يُراجَع لا إغفالٌ يمرّ.
 */
function repair01_w15_write_allowlist()
{
    return array(
        /* سجلّاتُ مكتبِ الرئيسِ نفسِه — قائمةٌ قبل هذه المرحلةِ ولم تُنشئها،
           والقرارُ فيها قرارُ القيادةِ لا معاملةُ إدارة. */
        'exec_decisions'         => 'سجل قرار القيادة نفسه - قائم قبل المرحلة',
        'exec_approvals'         => 'صندوق اعتماد القيادة نفسه - قائم قبل المرحلة',
        'exec_contract_signings' => 'سجل توقيع القيادة نفسه - قائم قبل المرحلة',
        'exec_project_charters'  => 'سجل قرار فتح المشروع - قائم قبل المرحلة',
        'exec_assignments'       => 'سجل تكليفات القيادة - قائم قبل المرحلة',
        'exec_matter_opinions'   => 'افادات المراجعة على قرار القيادة - ابن سجل القرار',
        /* طبقةُ المنصّةِ المشتركة — ليست جدولَ نطاقٍ ولا تملكها إدارة. */
        'personal_notifications' => 'اشعار شخصي - طبقة منصة',
        'portal_elements'        => 'مكونات البوابة - طبقة منصة',
        'action_execution_log'   => 'اثر تنفيذ فعل - طبقة منصة',
        'work_items'             => 'محرك بنود العمل - طبقة منصة',
        'task_evidence'          => 'دليل انجاز بند عمل - طبقة منصة',
        'audit_log'              => 'اثر تدقيق - طبقة منصة',
        'audit_changes'          => 'اثر تغيير - طبقة منصة',
        'gov_export_log'         => 'اثر تصدير - طبقة منصة',
        'guard_denials'          => 'اثر منع حارس - طبقة منصة',
    );
}

/** ملفّاتُ مساحاتِ هذه الموجة — أسطحُها وخدماتُها. */
function repair01_w15_space_files($ROOT)
{
    $out = array();
    $dirs = array(
        'Portal'                 => 'EX-CEO',
        'app/Services/Exec'      => 'EX-CEO',
        'app/Services/Workspace' => 'WS-MY',
    );
    foreach ($dirs as $d => $space) {
        $full = $ROOT . '/' . $d;
        if (!is_dir($full)) { continue; }
        foreach (scandir($full) as $f) {
            if (substr($f, -4) !== '.php') { continue; }
            $out[$d . '/' . $f] = $space;
        }
    }
    /* أسطحُ المساحةِ الشخصيّةِ خارجَ `Portal` — بأسمائها المسجَّلة. */
    foreach (array('main/my_workspace.php', 'main/dashboard.php', 'main/profile.php',
                   'user_capacities.php') as $f) {
        if (is_file($ROOT . '/' . $f)) { $out[$f] = 'WS-MY'; }
    }
    return $out;
}

/**
 * **يقرأ الشيفرةَ ويرصد كلَّ كتابة** — نصًّا خامًّا وعبرَ بوّابةِ العزل.
 * @return array صفوف: file · space · verb · table · line · verdict · why
 */
function repair01_w15_scan_writes($ROOT)
{
    $allow = repair01_w15_write_allowlist();
    $rows  = array();
    foreach (repair01_w15_space_files($ROOT) as $rel => $space) {
        $path = $ROOT . '/' . $rel;
        if (!is_file($path)) { continue; }
        $lines = file($path);
        if ($lines === false) { continue; }
        foreach ($lines as $i => $ln) {
            /* ⓐ الكتابةُ النصّيّةُ الخام */
            if (preg_match_all('~\b(INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-zA-Z_][a-zA-Z_0-9]*)`?~i',
                               $ln, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $verb = strtoupper(preg_replace('~\s+~', ' ', $hit[1]));
                    $tbl  = $hit[2];
                    /* `UPDATE` داخلَ `ON DUPLICATE KEY UPDATE` ليست كتابةً على جدولٍ ثانٍ */
                    if (stripos($ln, 'DUPLICATE KEY UPDATE') !== false && stripos($hit[1], 'UPDATE') === 0) { continue; }
                    $rows[] = repair01_w15_write_row($rel, $space, $verb, $tbl, $i + 1, $allow);
                }
            }
            /* ⓑ الكتابةُ عبرَ بوّابةِ العزل */
            if (preg_match_all('~->\s*(insert|update|deleteRow|softDelete|replaceChildren|deleteChild)\s*\(\s*[\'"]([a-zA-Z_][a-zA-Z_0-9]*)[\'"]~',
                               $ln, $m2, PREG_SET_ORDER)) {
                foreach ($m2 as $hit) {
                    $rows[] = repair01_w15_write_row($rel, $space, 'GATE_' . strtoupper($hit[1]),
                                                     $hit[2], $i + 1, $allow);
                }
            }
        }
    }
    return $rows;
}

function repair01_w15_write_row($rel, $space, $verb, $tbl, $line, array $allow)
{
    $ok = isset($allow[$tbl]);
    return array(
        'file' => $rel, 'space' => $space, 'verb' => $verb, 'table' => $tbl, 'line' => $line,
        'verdict' => $ok ? 'OWN_REGISTER' : 'FORBIDDEN',
        'why' => $ok ? $allow[$tbl]
                     : 'كتابة من مساحة القيادة او مساحة عملي في جدول نطاق اخر',
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② المِرساةُ — تُثبَت من القرصِ لا تُعلَن
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * يثبت أنَّ السطحَ قائمٌ على القرصِ **ويمسُّ جدولَه بالاسمِ مقتبَسًا**.
 * @return array `verdict` · `why`
 */
function repair01_w15_prove_anchor(mysqli $conn, $ROOT, array $a)
{
    $route = isset($a['route']) ? (string) $a['route'] : '';
    if ($route === '') { return array('verdict' => 'NO_ROUTE', 'why' => 'لا مسار'); }
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('verdict' => 'FILE_MISSING', 'why' => 'لا ملف على القرص'); }
    $probe = isset($a['probe']) ? (string) $a['probe'] : '';
    if ($probe === '') { return array('verdict' => 'ANCHORED', 'why' => ''); }
    $src = (string) file_get_contents($path);
    if (strpos($src, "'" . $probe . "'") !== false || strpos($src, '"' . $probe . '"') !== false
        || strpos($src, '`' . $probe . '`') !== false || strpos($src, ' ' . $probe . ' ') !== false) {
        return array('verdict' => 'ANCHORED', 'why' => '');
    }
    /* التضمينُ المحلّيُّ مستوًى واحدًا — فالمِسبارُ قد يقع في عدّةِ السطحِ لا فيه */
    foreach (repair01_w15_local_includes($ROOT, $route) as $inc) {
        $s2 = (string) @file_get_contents($inc);
        if ($s2 !== '' && (strpos($s2, "'" . $probe . "'") !== false
                        || strpos($s2, '"' . $probe . '"') !== false)) {
            return array('verdict' => 'ANCHORED', 'why' => 'المسبار في عدة السطح');
        }
    }
    return array('verdict' => 'PROBE_MISSING', 'why' => 'لا يمس ' . $probe);
}

/** تضميناتٌ محلّيّةٌ مستوًى واحدًا — بمسارٍ نسبيٍّ محلول. */
function repair01_w15_local_includes($ROOT, $route)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array(); }
    $src = (string) file_get_contents($path);
    $out = array();
    if (preg_match_all('~(?:require_once|require|include_once|include)\s*[( ]\s*(?:__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]~',
                       $src, $m)) {
        $base = dirname($path);
        foreach ($m[1] as $rel) {
            $p = realpath($base . '/' . ltrim($rel, '/'));
            if ($p !== false && is_file($p)) { $out[] = $p; }
        }
    }
    return $out;
}

/**
 * حارسُ العرضِ الخادميُّ — **يتبع التضمينَ المحلّيَّ مستوًى واحدًا**.
 * @return array `kind` · `evidence`
 */
function repair01_w15_guard_of($ROOT, $route)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => ''); }
    $src = (string) file_get_contents($path);
    if (strpos($src, 'check_page_permissions') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'check_page_permissions');
    }
    if (strpos($src, 'enforce_current_page_view_permission') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'enforce_current_page_view_permission');
    }
    foreach (repair01_w15_local_includes($ROOT, $route) as $inc) {
        $s2 = (string) @file_get_contents($inc);
        if (strpos($s2, 'check_page_permissions') !== false) {
            return array('kind' => 'VIA_KIT', 'evidence' => basename($inc));
        }
    }
    return array('kind' => 'NONE', 'evidence' => '');
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — ثمانيةَ عشرَ سطحًا مختومًا `W15`
   ═══════════════════════════════════════════════════════════════════════════ */

function repair01_w15_new_surfaces()
{
    require_once __DIR__ . '/repair01_w15_screens_def.php';
    $defs = repair01_w15_screen_defs();
    $meta = repair01_w15_surface_meta();
    $out  = array();
    foreach ($defs as $route => $d) {
        $m = isset($meta[$route]) ? $meta[$route] : array();
        $out[] = array(
            'route'   => $route,
            'ar'      => $d['title'],
            'icon'    => $d['icon'],
            'owner'   => isset($m['space']) ? $m['space'] : 'EX-CEO',
            'backing' => $d['table'],
            'bowner'  => $d['owner'],
            'group'   => isset($m['group']) ? $m['group'] : 'decision',
            'sort'    => isset($m['sort']) ? $m['sort'] : 50,
            'req'     => isset($m['req']) ? $m['req'] : '',
            'sibling' => isset($m['sibling']) ? $m['sibling'] : 'Portal/ceo_board.php',
            'doc'     => isset($m['doc']) ? $m['doc'] : 'قراءة مشتقة بلا مستند',
            'role'    => isset($m['role']) ? $m['role'] : 'الإدارة التنفيذية',
            'next'    => isset($m['next']) ? $m['next'] : 'قراءة',
            'cons'    => isset($m['cons']) ? $m['cons'] : 'القيادة',
            'fin'     => isset($m['fin']) ? $m['fin'] : 'لا أثر مالي مباشر',
        );
    }
    return $out;
}

/** بياناتُ كلِّ سطحِ نموٍّ — المتطلَّبُ والمجموعةُ وموضعُه من دورةِ العمل. */
function repair01_w15_surface_meta()
{
    return array(
'Portal/exec_daily_report.php' => array('req' => 'CEO-03', 'space' => 'EX-CEO', 'group' => 'periodic',
    'sort' => 11, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'تقرير يومي تنفيذي', 'next' => 'قراءة', 'cons' => 'القيادة والنواب'),
'Portal/exec_daily_stops.php' => array('req' => 'CEO-04', 'space' => 'EX-CEO', 'group' => 'periodic',
    'sort' => 12, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'تفصيل توقفات اليوم', 'next' => 'قراءة', 'cons' => 'القيادة وإدارة التشغيل'),
'Portal/exec_daily_deviations.php' => array('req' => 'CEO-05', 'space' => 'EX-CEO', 'group' => 'periodic',
    'sort' => 13, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'انحرافات وقرارات اليوم', 'next' => 'قراءة', 'cons' => 'القيادة وإدارة المخاطر'),
'Portal/exec_weekly_report.php' => array('req' => 'CEO-06', 'space' => 'EX-CEO', 'group' => 'periodic',
    'sort' => 14, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'تقرير أسبوعي تنفيذي', 'next' => 'قراءة', 'cons' => 'القيادة والنواب'),
'Portal/exec_monthly_pack.php' => array('req' => 'CEO-07', 'space' => 'EX-CEO', 'group' => 'periodic',
    'sort' => 15, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'حزمة الشهر التنفيذية', 'next' => 'قراءة', 'cons' => 'القيادة والنواب والمالية'),
'Portal/exec_raised_requests.php' => array('req' => 'CEO-08', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 21, 'sibling' => 'Portal/ceo_approvals.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'صندوق الطلبات المرفوعة', 'next' => 'قرار عند مالكه', 'cons' => 'القيادة'),
'Portal/exec_contract_registry.php' => array('req' => 'CEO-12', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 24, 'sibling' => 'Portal/ceo_contracts.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'سجل العقود الموحد', 'next' => 'قراءة', 'cons' => 'القيادة والمبيعات'),
'Portal/exec_redline_breaches.php' => array('req' => 'CEO-13', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 25, 'sibling' => 'Portal/ceo_risk.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'طلب تجاوز قاعدة مانعة', 'next' => 'قرار عند الحوكمة', 'cons' => 'القيادة والحوكمة'),
'Portal/exec_reserved_matters.php' => array('req' => 'CEO-14', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 26, 'sibling' => 'Portal/ceo_risk.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'إحالة مسألة محجوزة', 'next' => 'إحالة', 'cons' => 'القيادة والحوكمة'),
'Portal/exec_critical_exceptions.php' => array('req' => 'CEO-15', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 27, 'sibling' => 'Portal/ceo_risk.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'استثناء حرج', 'next' => 'قرار عند الحوكمة', 'cons' => 'القيادة والحوكمة والمخاطر'),
'Portal/exec_escalations.php' => array('req' => 'CEO-17', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 28, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'صف التصعيدات العليا', 'next' => 'قرار عند مالكه', 'cons' => 'القيادة'),
'Portal/exec_crisis_command.php' => array('req' => 'CEO-18', 'space' => 'EX-CEO', 'group' => 'decision',
    'sort' => 29, 'sibling' => 'Portal/ceo_risk.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'تفعيل قيادة أزمة', 'next' => 'تفعيل', 'cons' => 'القيادة والتشغيل'),
'Portal/exec_strategic_decisions.php' => array('req' => 'CEO-19', 'space' => 'EX-CEO', 'group' => 'governance',
    'sort' => 31, 'sibling' => 'Portal/ceo_risk.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'قرار استراتيجي', 'next' => 'قرار', 'cons' => 'القيادة والإدارات'),
'Portal/exec_leadership_appointments.php' => array('req' => 'CEO-23', 'space' => 'EX-CEO', 'group' => 'governance',
    'sort' => 35, 'sibling' => 'Portal/ceo_assignments.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'موافقة تعيين قيادي', 'next' => 'قرار', 'cons' => 'القيادة والموارد البشرية والحوكمة'),
'Portal/exec_meeting_decisions.php' => array('req' => 'CEO-25', 'space' => 'EX-CEO', 'group' => 'governance',
    'sort' => 37, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'قرار اجتماع', 'next' => 'متابعة', 'cons' => 'القيادة والإدارات'),
'Portal/exec_actions_followup.php' => array('req' => 'CEO-26', 'space' => 'EX-CEO', 'group' => 'governance',
    'sort' => 38, 'sibling' => 'Portal/ceo_board.php', 'role' => 'الإدارة التنفيذية',
    'doc' => 'سجل متابعة القرارات', 'next' => 'إغلاق بدليل', 'cons' => 'القيادة والنواب والإدارات'),
'Portal/exec_delegations.php' => array('req' => 'VP-12', 'space' => 'EX-DVP', 'group' => 'followup',
    'sort' => 41, 'sibling' => 'Portal/ceo_assignments.php', 'role' => 'نواب الرئيس',
    'doc' => 'سجل الإنابات', 'next' => 'قراءة', 'cons' => 'النواب والحوكمة'),
'Portal/my_reports.php' => array('req' => 'MY-05', 'space' => 'WS-MY', 'group' => 'daily',
    'sort' => 53, 'sibling' => 'Portal/my_tasks.php', 'role' => 'صاحب الحساب',
    'doc' => 'بلاغاتي', 'next' => 'قراءة', 'cons' => 'صاحب البلاغ وإدارة البلاغات'),
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ المِرساةُ لكلِّ متطلَّبٍ من الخمسةِ والأربعين
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * ⚠ **متطلَّباتُ النوّابِ تُخدَم بالأسطحِ نفسِها** بنطاقٍ يحسمه محرّكُ النطاق —
 *   وهو نصُّ §٤-٣: «⛔ لا ثلاثةَ أنظمة». وسطحٌ ثانٍ للنائبِ بنسخةٍ من شيفرةِ
 *   الرئيسِ **هو عينُ ما نُهي عنه**، فقاعدةُ الربطِ هنا `W15_SAME_ENGINE_SCOPED`.
 */
function repair01_w15_anchors()
{
    $A = function ($space, $route, $probe, $backing, $bowner, $rule, $why, $group, $step) {
        return array('space' => $space, 'route' => $route, 'probe' => $probe,
                     'backing' => $backing, 'bowner' => $bowner,
                     'rule' => $rule, 'why' => $why, 'group' => $group, 'step' => $step);
    };
    $SAME  = 'W15_SAME_ENGINE_SCOPED';
    $NEW   = 'W15_NEW_PROJECTION';
    $LIVE  = 'W15_LIVE_SURFACE';

    return array(
/* ── مساحةُ الرئيسِ التنفيذيّ ─────────────────────────────────────────── */
'CEO-01' => $A('EX-CEO', 'Portal/ceo_board.php', 'exec_board_snapshots', 'exec_board_snapshots', 'EX-CEO',
    $LIVE, 'سطح قائم واللقطة الدورية اغلقت فصار يقرأ حيا', 'vision', 1),
'CEO-02' => $A('EX-CEO', 'Portal/dept_achievement.php', '', 'project', 'DEP-11',
    $LIVE, 'سطح قائم يعرض الادارات والمشروعات بطاقة حالة', 'vision', 2),
'CEO-03' => $A('EX-CEO', 'Portal/exec_daily_report.php', 'site_day', 'site_day', 'DEP-12',
    $NEW, 'اسقاط يومي فوق يوميات المواقع المقفلة', 'periodic', 11),
'CEO-04' => $A('EX-CEO', 'Portal/exec_daily_stops.php', 'ops_stop_register', 'ops_stop_register', 'DEP-11',
    $NEW, 'صف ذري لكل نوع توقف', 'periodic', 12),
'CEO-05' => $A('EX-CEO', 'Portal/exec_daily_deviations.php', 'ctl_deviation', 'ctl_deviation', 'DEP-11',
    $NEW, 'الانحراف يبقى عند مالكه ويعرض هنا', 'periodic', 13),
'CEO-06' => $A('EX-CEO', 'Portal/exec_weekly_report.php', 'ops_stop_register', 'ops_stop_register', 'DEP-11',
    $NEW, 'مقارنة اسبوعية تشتق من الوقائع ولا تخزن', 'periodic', 14),
'CEO-07' => $A('EX-CEO', 'Portal/exec_monthly_pack.php', 'fin_monthly_close', 'fin_monthly_close', 'DEP-05',
    $NEW, 'حزمة الشهر تتجمع من اقفالات الادارات', 'periodic', 15),
'CEO-08' => $A('EX-CEO', 'Portal/exec_raised_requests.php', 'fin_requests', 'fin_requests', 'DEP-05',
    $NEW, 'صندوق موحد فوق سجلات الادارات المالكة', 'decision', 21),
'CEO-09' => $A('EX-CEO', 'Portal/ceo_approvals.php', 'exec_approvals', 'exec_approvals', 'EX-CEO',
    $LIVE, 'سطح قائم والسلطة صارت من سجلها لا من رقم دور', 'decision', 22),
'CEO-10' => $A('EX-CEO', 'Portal/ceo_contracts.php', 'exec_contract_signings', 'exec_contract_signings', 'EX-CEO',
    $LIVE, 'سطح قائم والسلطة صارت من سجلها لا من رقم دور', 'decision', 23),
'CEO-11' => $A('EX-CEO', 'Portal/contract_review.php', '', 'exec_contract_signings', 'EX-CEO',
    $LIVE, 'سطح قائم لملاحظات المراجعة قبل التوقيع', 'decision', 23),
'CEO-12' => $A('EX-CEO', 'Portal/exec_contract_registry.php', 'contracts', 'contracts', 'DEP-01',
    $NEW, 'نافذة قراءة واحدة فوق سجلات مالكي العقود', 'decision', 24),
'CEO-13' => $A('EX-CEO', 'Portal/exec_redline_breaches.php', 'exception_requests', 'exception_requests', 'DEP-08',
    $NEW, 'طلب تجاوز قبل التنفيذ من قاعدة مانعة', 'decision', 25),
'CEO-14' => $A('EX-CEO', 'Portal/exec_reserved_matters.php', 'gov_policy', 'gov_policy', 'DEP-08',
    $NEW, 'ما حجزته وثائق الشركة لجهة حاكمة', 'decision', 26),
'CEO-15' => $A('EX-CEO', 'Portal/exec_critical_exceptions.php', 'exception_requests', 'exception_requests', 'DEP-08',
    $NEW, 'حالات حرجة واقعة وصلت للقمة', 'decision', 27),
'CEO-16' => $A('EX-CEO', 'Portal/ceo_audit_reports.php', '', 'exec_audit_reports', 'IAF',
    $LIVE, 'سطح قائم لتقارير التاكيد المستقل بلا وساطة', 'decision', 28),
'CEO-17' => $A('EX-CEO', 'Portal/exec_escalations.php', 'ticket_escalations', 'ticket_escalations', 'DEP-10',
    $NEW, 'صف موحد لما وصل للقمة بلا سجل مكرر', 'decision', 28),
'CEO-18' => $A('EX-CEO', 'Portal/exec_crisis_command.php', 'exec_decisions', 'exec_decisions', 'EX-CEO',
    $NEW, 'تفعيل قيادي فوق الحدث لا تكرار له', 'decision', 29),
'CEO-19' => $A('EX-CEO', 'Portal/exec_strategic_decisions.php', 'exec_decisions', 'exec_decisions', 'EX-CEO',
    $NEW, 'سجل استراتيجي منفصل عن اليومي', 'governance', 31),
'CEO-20' => $A('EX-CEO', 'Portal/project_charter.php', 'exec_project_charters', 'exec_project_charters', 'EX-CEO',
    $LIVE, 'سطح قائم وتوليد المشروع انتقل لخدمة مالكه', 'governance', 32),
'CEO-21' => $A('EX-CEO', 'admin/org_structure.php', '', 'org_units', 'DEP-07',
    $LIVE, 'سطح قائم لقرار البنية الدائمة', 'governance', 33),
'CEO-22' => $A('EX-CEO', 'Portal/ceo_assignments.php', 'exec_assignments', 'exec_assignments', 'EX-CEO',
    $LIVE, 'سطح قائم للتكليف المؤقت بمدته ونطاقه', 'governance', 34),
'CEO-23' => $A('EX-CEO', 'Portal/exec_leadership_appointments.php', 'exec_assignments', 'exec_assignments', 'DEP-07',
    $NEW, 'موافقة التعيين القيادي مع افصاح الاطراف', 'governance', 35),
'CEO-24' => $A('EX-CEO', '', '', '', '',
    'W15_DEFERRED_OWNER', 'سجل اجتماعات القيادة يحتاج جدول حقيقة جديدا وهذه المرحلة لا تنشئه', 'governance', 36),
'CEO-25' => $A('EX-CEO', 'Portal/exec_meeting_decisions.php', 'exec_decisions', 'exec_decisions', 'EX-CEO',
    $NEW, 'كل قرار صف مستقل بمرجع اجتماعه', 'governance', 37),
'CEO-26' => $A('EX-CEO', 'Portal/exec_actions_followup.php', 'exec_decisions', 'exec_decisions', 'EX-CEO',
    $NEW, 'سجل موحد يجمع بمرجعه ولا ينشئ سجلا ثانيا', 'governance', 38),

/* ── مساحةُ النوّاب — **الأسطحُ نفسُها بنطاقٍ يحسمه المحرّك** ────────────── */
'VP-01' => $A('EX-DVP', 'Portal/ceo_board.php', 'exec_board_snapshots', 'exec_board_snapshots', 'EX-CEO',
    $SAME, 'نفس محرك اللوحة والنطاق الافتراضي يتغير بالنائب', 'scope', 1),
'VP-02' => $A('EX-DVP', 'Portal/dept_achievement.php', '', 'project', 'DEP-11',
    $SAME, 'نفس السطح والرؤية اوسع من السلطة بعلم معلن', 'scope', 2),
'VP-03' => $A('EX-DVP', 'Portal/exec_daily_report.php', 'site_day', 'site_day', 'DEP-12',
    $SAME, 'نفس محرك اليومي بصفوفه الكاملة ضمن النطاق', 'periodic', 11),
'VP-04' => $A('EX-DVP', 'Portal/exec_weekly_report.php', 'ops_stop_register', 'ops_stop_register', 'DEP-11',
    $SAME, 'نفس شاشة المراجعة الاسبوعية بالنطاق', 'periodic', 14),
'VP-05' => $A('EX-DVP', 'Portal/exec_monthly_pack.php', 'fin_monthly_close', 'fin_monthly_close', 'DEP-05',
    $SAME, 'مراجعة النائب جزء من تجهيز حزمة الشهر', 'periodic', 15),
'VP-06' => $A('EX-DVP', 'Portal/exec_raised_requests.php', 'fin_requests', 'fin_requests', 'DEP-05',
    $SAME, 'لا نظام اعتماد مستقلا لكل نائب', 'decision', 21),
'VP-07' => $A('EX-DVP', 'Portal/ceo_approvals.php', 'exec_approvals', 'exec_approvals', 'EX-CEO',
    $SAME, 'نفس الصندوق والمصفوفة تحدد من يستلم', 'decision', 22),
'VP-08' => $A('EX-DVP', 'Portal/exec_critical_exceptions.php', 'exception_requests', 'exception_requests', 'DEP-08',
    $SAME, 'نفس محرك الاستثناء والمسار تحدده القاعدة', 'decision', 27),
'VP-09' => $A('EX-DVP', 'Portal/exec_escalations.php', 'ticket_escalations', 'ticket_escalations', 'DEP-10',
    $SAME, 'نفس صف التصعيد بالنطاق والمتجاوز يصعد', 'decision', 28),
'VP-10' => $A('EX-DVP', 'Portal/my_tasks.php', '', 'work_items', 'PLATFORM',
    $SAME, 'ما ينتظر فعل النائب صف موحد من كل الشاشات', 'followup', 39),
'VP-11' => $A('EX-DVP', 'Portal/exec_actions_followup.php', 'exec_decisions', 'exec_decisions', 'EX-CEO',
    $SAME, 'نفس سجل المتابعة بالنطاق حتى الاغلاق بدليل', 'followup', 40),
'VP-12' => $A('EX-DVP', 'Portal/exec_delegations.php', 'gov_delegations', 'gov_delegations', 'DEP-08',
    $NEW, 'المصدر النافذ سجل التفويضات عند الحوكمة', 'followup', 41),

/* ── مساحةُ عملي ─────────────────────────────────────────────────────── */
'MY-01' => $A('WS-MY', 'Portal/my_achievement.php', '', 'work_items', 'PLATFORM',
    $LIVE, 'سطح قائم الزامي لكل حساب بلا استثناء', 'personal', 51),
'MY-02' => $A('WS-MY', 'Portal/my_portal.php', '', 'portal_elements', 'PLATFORM',
    $LIVE, 'سطح قائم ومكوناته تحدد بالدور', 'personal', 52),
'MY-03' => $A('WS-MY', 'Portal/my_tasks.php', 'work_items', 'work_items', 'PLATFORM',
    $LIVE, 'المهام تصل من محركات النظام ولا تخترع', 'daily', 51),
'MY-04' => $A('WS-MY', 'Portal/my_requests.php', 'gov_request_type', 'gov_request_type', 'DEP-08',
    $LIVE, 'مطلق واسقاط والسجل ينشأ عند مالكه', 'daily', 52),
'MY-05' => $A('WS-MY', 'Portal/my_reports.php', 'tickets', 'tickets', 'DEP-10',
    $NEW, 'صاحب البلاغ يرى بلاغاته وحالتها', 'daily', 53),
'MY-06' => $A('WS-MY', 'user_capacities.php', '', 'user_capacities', 'PLATFORM',
    $LIVE, 'سطح قائم لصفات الحساب والتبديل بينها', 'personal', 54),
'MY-07' => $A('WS-MY', 'Settings/change_password.php', '', 'users', 'PLATFORM',
    $LIVE, 'سطح قائم وامان الحساب فعل شخصي', 'personal', 55),
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ أنواعُ الطلباتِ الثلاثةُ — بالقاعدةِ الرباعيّة
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * ⚠ **ثلاثةُ أنواعٍ لا اثنانِ وستّون**: هذه هي التي **يملك نطاقُها خدمةَ
 *   إنشاءٍ مقيسةً على القرص**. والباقي في القاموسِ العامِّ دَينٌ معدودٌ في
 *   `Enterprise Debt Closure` — ⛔ **ولا يُسجَّل نوعٌ بلا خدمةِ مالكٍ قائمة**،
 *   فالتسجيلُ بلا خدمةٍ يعِد بتوجيهٍ لا ينفَّذ.
 */
function repair01_w15_launcher_types()
{
    return array(
        array(
            'type_code' => 'HR_LEAVE',
            'name_ar'   => 'طلب إجازة',
            'owner'     => 'DEP-13',
            'authority' => 'AAM-HR-LEAVE',
            'routing'   => 'ROUTE_HR_LEAVE_TO_WORKFORCE',
            'perm'      => 'ROLE_GRANT_VIA_MODULE',
            'table'     => 'worker_leave_absence',
            'service'   => 'App\\Services\\Workforce\\LeaveRequestService::createFromLauncher',
            'user_col'  => 'created_by',
        ),
        array(
            'type_code' => 'MNT_REQUEST',
            'name_ar'   => 'طلب صيانة',
            'owner'     => 'DEP-14',
            'authority' => 'AAM-MNT-REQUEST',
            'routing'   => 'ROUTE_MNT_REQUEST_TO_MAINTENANCE',
            'perm'      => 'ROLE_GRANT_VIA_MODULE',
            'table'     => 'mnt_breakdown',
            'service'   => 'App\\Services\\Maintenance\\MaintenanceCycleService::createFromLauncher',
            'user_col'  => 'reported_by',
        ),
        array(
            'type_code' => 'TICKET_REPORT',
            'name_ar'   => 'بلاغ',
            'owner'     => 'DEP-10',
            'authority' => 'AAM-TICKET-REPORT',
            'routing'   => 'ROUTE_TICKET_TO_REPORTS_DESK',
            'perm'      => 'ROLE_GRANT_VIA_MODULE',
            'table'     => 'tickets',
            'service'   => 'App\\Services\\Tickets\\TicketRouter::createFromLauncher',
            'user_col'  => 'reporter_user_id',
        ),
    );
}

/** يثبت أنَّ خدمةَ المالكِ **قائمةٌ فعلًا** — والدعوى بلا صنفٍ وطريقةٍ لا تُقاس. */
function repair01_w15_service_exists($ROOT, $spec)
{
    list($cls, $method) = array_pad(explode('::', (string) $spec, 2), 2, '');
    $rel = str_replace('\\', '/', $cls);
    $rel = preg_replace('~^App/~', 'app/', $rel) . '.php';
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { return false; }
    $src = (string) file_get_contents($path);
    return $method === '' || strpos($src, 'function ' . $method) !== false;
}

}
