<?php
/**
 * includes/cmp03_domain_bridge.php — الشاشةُ المولَّدةُ تكتب في **جدولِ مجالها**
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0055 (الحضور) · INJ-0054 (الخصومات) · INJ-0163 (الأعيان المموَّلة)
 *
 * ── العلّةُ الجامعة ───────────────────────────────────────────────────────
 * ولّدت موجةُ CMP-03 اثنتين وأربعين شاشةً على **مخزنٍ بينيٍّ موحَّد** (`scr_*`)
 * وأُطلقت قبل ربطِ كلٍّ منها بجدولِ مجالِها. فصار لكلِّ حقيقةٍ مخزنان:
 * الشاشةُ تكتب في `scr_attendance` **والمسيّرُ يقرأ `attendance_days`** —
 * فيومُ غيابٍ يُسجَّل ولا يخفض ساعةً واحدة.
 *
 * ── ولماذا جسرٌ مركزيٌّ لا تعديلُ خمسِ شاشات ─────────────────────────────
 * `cmp03_store_insert` هي **البابُ الوحيدُ** لكتابةِ جداولِ الشاشات (٤٠٤ مُنادٍ).
 * فوضعُ التحويلِ فيها يحوّل كلَّ شاشةٍ لها جسرٌ **بملفٍّ واحد**، ويترك ما لا
 * جسرَ له على حالِه بلا مساس. وإصلاحُ خمسِ شاشاتٍ يدًا يدًا يُنتج خمسَ صياغاتٍ
 * تتفرّق مع أوّلِ تعديل.
 *
 * ── والقواعدُ التي التزمها هذا الجسر ─────────────────────────────────────
 * ◆ **صفرُ صفٍّ جديدٍ في `scr_*`** لشاشةٍ لها جسر — وهو نصُّ القبولِ حرفًا.
 * ◆ **ولا يُحذف الموروثُ**: صفوفُ `scr_*` القائمةُ تبقى وتُعرض موسومةً
 *   «سجلٌّ سابقٌ للربط» — «المخزنُ الملغى يُحوَّل إلى قارئٍ ولا يُحذف».
 * ◆ **ولا يُخترع مرجع**: رمزُ موظفٍ أو معدةٍ أو مموِّلٍ لا يقابله صفٌّ ⇒ **رفضٌ
 *   معلَنٌ برمزٍ محكوم**، لا كتابةٌ بصفرٍ ولا بـNULL.
 * ◆ **والتكرارُ يردُّه قيدُ القاعدة** (`uq_att_day` · `uq_payroll_deduction`)
 *   لا فحصٌ هنا — ففحصٌ في PHP يُهزم بطلبين متزامنين.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('cmp03_bridge_norm')) {
    /** تطبيعُ التسميةِ — مرآةُ `cmp03_store_norm` فلا يتفرّق مطابقان. */
    function cmp03_bridge_norm($s)
    {
        $s = preg_replace('/\s+/u', ' ', trim((string) $s));
        return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
    }
}

if (!function_exists('cmp03_bridge_pick')) {
    /**
     * قيمةُ تسميةٍ من الحمولةِ بالتطبيع — فلا يُفقد حقلٌ لتشكيلٍ.
     *
     * ◆ ويقبل **قائمةَ مرشَّحاتٍ** لا تسميةً واحدة: تسمياتُ الشاشاتِ المولَّدةِ
     *   تختلف بحرفٍ («التاريخ» · «تاريخ اليوم») — وتسميةٌ واحدةٌ صلبةٌ تُسقط
     *   الحقلَ **صامتةً** عند أوّلِ اختلاف. فالمرشَّحاتُ تُجرَّب بالترتيب.
     */
    function cmp03_bridge_pick(array $payload, $labels, $default = '')
    {
        foreach ((array) $labels as $label) {
            $want = cmp03_bridge_norm($label);
            foreach ($payload as $k => $v) {
                if (cmp03_bridge_norm($k) === $want) {
                    $v = trim((string) $v);
                    if ($v !== '') { return $v; }
                }
            }
        }
        return $default;
    }
}

if (!function_exists('cmp03_bridged_screens')) {
    /** الشاشاتُ التي صارت تكتب في جدولِ مجالها — مصدرٌ واحدٌ يقرؤه الفاحص. */
    function cmp03_bridged_screens()
    {
        return array(
            'attendance.php'      => 'attendance_days',
            'deductions.php'      => 'payroll_deductions',
            'fin_assets.php'      => 'asset_ownership_shares',
            /* ⇐ INJ-0370 · الإذنُ يبقى في جدولِه — والجسرُ يربطه بسجلَّيه
                 ويشتقُّ معتمِدَه، فلا نصوصَ حرّةً في موضعِ مفاتيح. */
            'site_gate_equip.php' => 'scr_site_gate_equip',
            /* ⇐ ثامنًا-٤ · توأمُ الإذنِ للأشخاص — النمطُ نفسُه حرفًا:
                 الإذنُ في جدولِه والمفاتيحُ تُشتقُّ، والمعتمِدُ من الجلسة. */
            'site_gate_person.php' => 'scr_site_gate_person',
        );
    }
}

if (!function_exists('cmp03_bridge_write')) {
    /**
     * يكتب في جدولِ المجالِ بدلَ `scr_*`.
     * @return array|null null ⇒ لا جسرَ لهذه الشاشة (تُكتب كما كانت)
     *                   وإلا {ok:bool, id:int, code:string, msg:string}
     */
    function cmp03_bridge_write(mysqli $conn, $companyId, $canonical, array $payload, $status, $uid)
    {
        $map = cmp03_bridged_screens();
        if (!isset($map[$canonical])) { return null; }
        $companyId = (int) $companyId; $uid = (int) $uid;
        switch ($canonical) {
            case 'attendance.php':  return cmp03_bridge_attendance($conn, $companyId, $payload, $uid);
            case 'deductions.php':  return cmp03_bridge_deduction($conn, $companyId, $payload, $uid);
            case 'fin_assets.php':  return cmp03_bridge_fin_asset($conn, $companyId, $payload, $uid);
            case 'site_gate_equip.php':
                                    return cmp03_bridge_site_gate($conn, $companyId, $payload, $status, $uid);
            case 'site_gate_person.php':
                                    return cmp03_bridge_site_gate_person($conn, $companyId, $payload, $status, $uid);
        }
        return null;
    }
}

if (!function_exists('cmp03_bridge_person')) {
    /** رمزُ موظفٍ ⇒ معرِّفُه — أو صفرٌ (ولا يُخترع). */
    function cmp03_bridge_person(mysqli $conn, $companyId, $code)
    {
        $code = trim((string) $code);
        if ($code === '') { return 0; }
        $st = $conn->prepare('SELECT id FROM employees
                               WHERE company_id = ? AND (employee_code = ? OR id = ?) LIMIT 1');
        if (!$st) { return 0; }
        $n = ctype_digit($code) ? (int) $code : 0;
        $st->bind_param('isi', $companyId, $code, $n);
        $st->execute();
        $r = $st->get_result()->fetch_row();
        $st->close();
        return $r ? (int) $r[0] : 0;
    }
}

if (!function_exists('cmp03_bridge_attendance')) {
    /**
     * ⇐ INJ-0055 · «يومُ غيابٍ يُسجَّل من الشاشة **يظهر في مدخلات الزمن في
     * المسيّر** ويخفض الساعاتِ المحتسبة؛ وصفرُ صفٍّ جديدٍ في `scr_attendance`».
     *
     * ◆ و`status_code` عمودٌ رباعيُّ الحروف: تُترجَم حالةُ الشاشةِ العربيةُ إلى
     *   رمزِها المحكومِ — فـ«غياب» تصير `ABSN` ويقرؤها المسيّرُ غيابًا.
     */
    function cmp03_bridge_attendance(mysqli $conn, $companyId, array $payload, $uid)
    {
        $out = array('ok' => false, 'id' => 0, 'code' => 'ATT-422', 'msg' => '');
        $person = cmp03_bridge_person($conn, $companyId, cmp03_bridge_pick($payload, array('كود الموظف', 'الموظف')));
        if ($person <= 0) {
            $out['msg'] = 'ATT-422: كودُ الموظفِ لا يقابله موظفٌ في كيانك — ولا يُكتب حضورٌ بمرجعٍ مخترَع';
            return $out;
        }
        $date = cmp03_bridge_pick($payload, array('التاريخ', 'تاريخ اليوم', 'تاريخ الحضور'));
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) {
            $out['msg'] = 'ATT-422: تاريخُ اليومِ إلزاميٌّ بصيغة YYYY-MM-DD';
            return $out;
        }
        /* الرمزُ المحكومُ من حالةِ الشاشة — والافتراضُ حضورٌ لا غياب */
        $raw = cmp03_bridge_pick($payload, array('رمز الحالة')) . ' ' . cmp03_bridge_pick($payload, array('وصف الحالة'));
        $code = 'ATTE';
        foreach (array('غياب' => 'ABSN', 'إجاز' => 'LEAV', 'اجاز' => 'LEAV', 'تأخ' => 'LATE',
                       'تاخ' => 'LATE', 'مرض' => 'SICK', 'راحة' => 'REST', 'حاضر' => 'ATTE') as $k => $v) {
            if (mb_strpos($raw, $k) !== false) { $code = $v; break; }
        }
        $doc = cmp03_bridge_pick($payload, array('المستند المؤيد', 'مستند مؤيد', 'المستند'));
        $st = $conn->prepare('INSERT INTO attendance_days
                (company_id, person_id, att_date, status_code, reference_doc, classified_by, classified_at)
              VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$st) { $out['code'] = 'ATT-500'; $out['msg'] = 'ATT-500: ' . $conn->error; return $out; }
        $st->bind_param('iisssi', $companyId, $person, $date, $code, $doc, $uid);
        $ok = $st->execute();
        $errno = (int) $st->errno;
        $st->close();
        if (!$ok) {
            if ($errno === 1062) {
                $out['code'] = 'ATT-409';
                $out['msg']  = 'ATT-409: لهذا الموظفِ يومٌ مسجَّلٌ سلفًا في ' . $date
                             . ' — يومٌ واحدٌ لكلِّ موظفٍ (قيدُ القاعدة)';
                return $out;
            }
            $out['code'] = 'ATT-500'; $out['msg'] = 'ATT-500: تعذّر التسجيل (' . $errno . ')';
            return $out;
        }
        $out['ok'] = true; $out['id'] = (int) $conn->insert_id; $out['code'] = 'ATT-201';
        $out['msg'] = 'سُجّل اليومُ في مدخلاتِ الزمنِ (' . $code . ') — يقرؤه المسيّرُ مباشرةً';
        return $out;
    }
}

if (!function_exists('cmp03_bridge_deduction')) {
    /**
     * ⇐ INJ-0054 · «خصمٌ يُعتمد من الشاشة **يظهر في مقاصّات المسيّر للفترة
     * نفسِها** ويخفض الصافي بمقداره؛ وصفرُ صفٍّ جديدٍ في `scr_deductions`».
     *
     * ◆ و`run_id` مفتاحٌ أجنبيٌّ إلزاميّ: **لا خصمَ بلا جولةِ مسيّرٍ للفترة** —
     *   يُردُّ برمزٍ محكومٍ بدل كتابةِ خصمٍ معلَّقٍ في الهواء.
     */
    function cmp03_bridge_deduction(mysqli $conn, $companyId, array $payload, $uid)
    {
        $out = array('ok' => false, 'id' => 0, 'code' => 'DED-422', 'msg' => '');
        $person = cmp03_bridge_person($conn, $companyId, cmp03_bridge_pick($payload, array('كود الموظف', 'الموظف')));
        if ($person <= 0) {
            $out['msg'] = 'DED-422: كودُ الموظفِ لا يقابله موظفٌ في كيانك';
            return $out;
        }
        $period = cmp03_bridge_pick($payload, array('الشهر', 'الشهر المرجعي', 'الفترة'));
        if (!preg_match('~^\d{4}-\d{2}~', $period)) {
            $out['msg'] = 'DED-422: الشهرُ المرجعيُّ إلزاميٌّ بصيغة YYYY-MM';
            return $out;
        }
        $month = substr($period, 0, 7) . '-01';
        $run = 0;
        $st = $conn->prepare("SELECT id FROM payroll_runs
                               WHERE company_id = ? AND COALESCE(is_deleted,0) = 0
                                 AND ? BETWEEN period_from AND period_to
                               ORDER BY id DESC LIMIT 1");
        if ($st) {
            $st->bind_param('is', $companyId, $month);
            $st->execute();
            $r = $st->get_result()->fetch_row();
            $st->close();
            $run = $r ? (int) $r[0] : 0;
        }
        if ($run <= 0) {
            $out['code'] = 'DED-409';
            $out['msg']  = 'DED-409: لا جولةَ مسيّرٍ تغطّي ' . substr($period, 0, 7)
                         . ' — والخصمُ لا يُعلَّق في الهواء. أنشئ جولةَ الفترةِ أولًا';
            return $out;
        }
        $doc = cmp03_bridge_pick($payload, array('رقم القرار', 'المستند المؤيد'));
        if ($doc === '') {
            $out['msg'] = 'DED-422: رقمُ القرارِ إلزاميٌّ — لا خصمَ بلا مستند (CHECK في القاعدة)';
            return $out;
        }
        $amount = (float) str_replace(array(',', ' '), '', cmp03_bridge_pick($payload, array('قيمة الخصم', 'القيمة')));
        if ($amount <= 0) { $out['msg'] = 'DED-422: قيمةُ الخصمِ يجب أن تكون موجبة'; return $out; }
        /* نوعُ المصدرِ من تعدادِ الجدولِ الحيّ — والافتراضُ `other` لا اختراع */
        $kind = cmp03_bridge_pick($payload, array('نوع الخصم', 'النوع'));
        $src = 'other';
        foreach (array('سلف' => 'advance', 'عهد' => 'advance', 'جزاء' => 'penalty', 'غرام' => 'penalty',
                       'غياب' => 'absence', 'نياب' => 'on_behalf') as $k => $v) {
            if (mb_strpos($kind, $k) !== false) { $src = $v; break; }
        }
        /* `ck_deduction_src` يشترط `source_id > 0` — والمصدرُ هنا قرارُ الموارد
           البشرية، فيُستعمل معرِّفُ الموظفِ سندًا للمصدرِ اليدويّ. */
        $srcId = $person;
        $st = $conn->prepare('INSERT INTO payroll_deductions
                (company_id, run_id, person_id, source_type, source_id, amount, doc_ref, note, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$st) { $out['code'] = 'DED-500'; $out['msg'] = 'DED-500: ' . $conn->error; return $out; }
        $note = 'قرارُ الموارد البشرية · ' . mb_substr(cmp03_bridge_pick($payload, array('سبب الخصم', 'السبب')), 0, 180);
        $st->bind_param('iiisidss', $companyId, $run, $person, $src, $srcId, $amount, $doc, $note);
        $ok = $st->execute();
        $errno = (int) $st->errno;
        $st->close();
        if (!$ok) {
            if ($errno === 1062) {
                $out['code'] = 'DED-409';
                $out['msg']  = 'DED-409: خصمٌ بهذا النوعِ مسجَّلٌ سلفًا لهذا الموظفِ في جولةِ '
                             . $run . ' — لا يتكرر (قيدُ القاعدة)';
                return $out;
            }
            $out['code'] = 'DED-500'; $out['msg'] = 'DED-500: تعذّر التسجيل (' . $errno . ')';
            return $out;
        }
        $out['ok'] = true; $out['id'] = (int) $conn->insert_id; $out['code'] = 'DED-201';
        $out['msg'] = 'سُجّل الخصمُ في مقاصّاتِ جولةِ المسيّرِ #' . $run . ' — يخفض الصافيَ بمقداره';
        return $out;
    }
}

if (!function_exists('cmp03_bridge_site_gate')) {
    /**
     * ⇐ INJ-0370 · «إذنُ دخولِ معدةٍ **يرتبط بمعدةٍ من سجل المعدات وبموقعٍ من
     * سجل المواقع**، وهويةُ المعتمِدِ **تُشتق من الحساب المعتمِد ولا تُكتب يدويًّا**».
     *
     * ◆ والإذنُ يبقى في جدولِه — فهو مستندُ الموقعِ لا نسخةٌ من غيره. الناقصُ
     *   كان **مفاتيحَه**: معدةٌ نصًّا وموقعٌ نصًّا ومعتمِدٌ يكتبه المُدخِلُ بيدِه.
     * ◆ **ولا يُقبل إذنٌ بمرجعٍ لا يقابله صفّ**: رمزُ معدةٍ أو اسمُ موقعٍ مجهولٌ
     *   ⇒ رفضٌ معلَن — فالمفتاحُ المخترَعُ أسوأُ من النصِّ الحرّ.
     * ◆ والمعتمِدُ **من الجلسة** ولا يُقرأ من الحقل: «لا يُوقَّع باسمِ أحدٍ».
     */
    function cmp03_bridge_site_gate(mysqli $conn, $companyId, array $payload, $status, $uid)
    {
        $out = array('ok' => false, 'id' => 0, 'code' => 'SGE-422', 'msg' => '');
        $eqCode = cmp03_bridge_pick($payload, array('كود المعدة', 'المعدة'));
        $eq = 0;
        if ($eqCode !== '') {
            $st = $conn->prepare('SELECT id FROM equipments
                                   WHERE company_id = ? AND (code = ? OR name = ? OR id = ?) LIMIT 1');
            if ($st) {
                $n = ctype_digit($eqCode) ? (int) $eqCode : 0;
                $st->bind_param('issi', $companyId, $eqCode, $eqCode, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $eq = $r ? (int) $r[0] : 0;
            }
        }
        if ($eq <= 0) {
            $out['msg'] = 'SGE-422: كودُ المعدةِ لا يقابله صفٌّ في سجلِّ المعدات — '
                        . 'ولا يُصدَر إذنٌ لمعدةٍ مجهولة';
            return $out;
        }
        $siteName = cmp03_bridge_pick($payload, array('الموقع', 'اسم الموقع'));
        $site = 0;
        if ($siteName !== '') {
            $st = $conn->prepare('SELECT id FROM project
                                   WHERE company_id = ? AND (name = ? OR id = ?) LIMIT 1');
            if ($st) {
                $n = ctype_digit($siteName) ? (int) $siteName : 0;
                $st->bind_param('isi', $companyId, $siteName, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $site = $r ? (int) $r[0] : 0;
            }
        }
        if ($site <= 0) {
            $out['msg'] = 'SGE-422: الموقعُ «' . $siteName . '» لا يقابله موقعٌ في سجلِّ المواقع';
            return $out;
        }
        /* المعتمِدُ من الجلسةِ — والحقلُ النصيُّ يُهمَل إن كُتب */
        $approver = '';
        $st = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $uid);
            $st->execute();
            $r = $st->get_result()->fetch_row();
            $st->close();
            $approver = $r ? (string) $r[0] : '';
        }
        $st = $conn->prepare("INSERT INTO scr_site_gate_equip
                (company_id, no_permit, type_permit, site_name, code_equipment, type_equipment,
                 source_equipment, party_escort, reason_movement, doc_reference,
                 date_movement_planned, date_movement_actual, reading_meter_at_movement,
                 trip_haulage, state_readiness, state_documents,
                 approval_manager_site, approval_manager_operations, approved_date, authority_ref,
                 equipment_id, site_project_id, approved_by_user,
                 status, is_seed, created_by, created_by_name, created_at, updated_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,NOW(),NOW())");
        if (!$st) { $out['code'] = 'SGE-500'; $out['msg'] = 'SGE-500: ' . $conn->error; return $out; }
        $g = function ($labels) use ($payload) { return cmp03_bridge_pick($payload, (array) $labels); };
        $v = array(
            $g('رقم الإذن'), $g('نوع الإذن'), $siteName, $eqCode, $g('نوع المعدة'),
            $g('مصدر المعدة'), $g('الجهة المرافقة'), $g('سبب الحركة'), $g('المستند المرجعي'),
            $g('تاريخ الحركة المخطط'), $g('تاريخ الحركة الفعلي'), $g('قراءة العدّاد عند الحركة'),
            $g('رحلة الترحيل'), $g('حالة الجاهزية'), $g('حالة الوثائق'),
            $approver, $approver, date('Y-m-d'), $g('مرجع التفويض'),
        );
        /* ◆ نوعُ الربطِ يُبنى بالعدِّ لا يُكتب بيد: حرفٌ ناقصٌ واحدٌ يُسقط
             الجملةَ كلَّها (ودرسُ `bind_param` المنزاحِ محفوظٌ في هذا المستودع). */
        $types = 'i' . str_repeat('s', 19) . 'iii' . 's' . 'i' . 's';
        $st->bind_param($types,
            $companyId, $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8],
            $v[9], $v[10], $v[11], $v[12], $v[13], $v[14], $v[15], $v[16], $v[17], $v[18],
            $eq, $site, $uid, $status, $uid, $approver);
        $ok = $st->execute();
        $err = (string) $st->error;
        $st->close();
        if (!$ok) {
            $out['code'] = 'SGE-500';
            $out['msg']  = 'SGE-500: تعذّر إصدارُ الإذن — ' . mb_substr($err, 0, 110);
            return $out;
        }
        $out['ok'] = true; $out['id'] = (int) $conn->insert_id; $out['code'] = 'SGE-201';
        $out['msg'] = 'صدر الإذنُ مرتبطًا بالمعدةِ #' . $eq . ' والموقعِ #' . $site
                    . ' — والمعتمِدُ «' . ($approver !== '' ? $approver : ('حساب #' . $uid))
                    . '» من حسابِك لا من الكتابة';
        return $out;
    }
}

if (!function_exists('cmp03_bridge_fin_asset')) {
    /**
     * ⇐ INJ-0163 · «إضافةُ عينٍ مموَّلةٍ من الشاشة **تظهر فورًا في ملف العملية
     * وفي حصص الملكية**، وصفرُ صفٍّ جديدٍ في `scr_fin_assets`».
     *
     * ◆ والحصةُ **نسبةٌ مشتقّةٌ** من رأسِ مالِ المموِّلِ إلى قيمةِ الشراء — لا
     *   رقمٌ يُكتب بيد. وقادحُ التراكبِ (`trg_share_no_overlap_*`) يحرسها.
     */
    function cmp03_bridge_fin_asset(mysqli $conn, $companyId, array $payload, $uid)
    {
        $out = array('ok' => false, 'id' => 0, 'code' => 'FAS-422', 'msg' => '');
        $assetCode = cmp03_bridge_pick($payload, array('كود العين', 'كود الأصل', 'كود الأصل أو البند'));
        $asset = 0;
        if ($assetCode !== '') {
            $st = $conn->prepare('SELECT id FROM equipments
                                   WHERE company_id = ? AND (code = ? OR name = ? OR id = ?) LIMIT 1');
            if ($st) {
                $n = ctype_digit($assetCode) ? (int) $assetCode : 0;
                $st->bind_param('issi', $companyId, $assetCode, $assetCode, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $asset = $r ? (int) $r[0] : 0;
            }
        }
        if ($asset <= 0) {
            $out['msg'] = 'FAS-422: كودُ الأصلِ لا يقابله أصلٌ في سجلِّ المعدات — ولا تُربط عينٌ مخترَعة';
            return $out;
        }
        $finName = cmp03_bridge_pick($payload, array('الممول', 'اسم الممول', 'المموِّل'));
        $ent = 0;
        if ($finName !== '') {
            $st = $conn->prepare('SELECT entity_id FROM legal_entities WHERE legal_name = ? LIMIT 1');
            if ($st) {
                $st->bind_param('s', $finName);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $ent = $r ? (int) $r[0] : 0;
            }
        }
        if ($ent <= 0) {
            $out['msg'] = 'FAS-422: اسمُ المموِّلِ لا يقابله كيانٌ قانونيٌّ مسجَّل';
            return $out;
        }
        /* عمليةُ التمويلِ اختياريةٌ — وإن ذُكرت وجب أن توجد */
        $opCode = cmp03_bridge_pick($payload, array('عملية التمويل', 'العملية'));
        $op = null;
        if ($opCode !== '') {
            $st = $conn->prepare('SELECT op_id FROM financing_operations
                                   WHERE company_id = ? AND op_code = ? LIMIT 1');
            if ($st) {
                $st->bind_param('is', $companyId, $opCode);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                if (!$r) {
                    $out['msg'] = 'FAS-422: عمليةُ التمويلِ «' . $opCode . '» غيرُ موجودة';
                    return $out;
                }
                $op = (int) $r[0];
            }
        }
        $value = (float) str_replace(array(',', ' '), '', cmp03_bridge_pick($payload, array('قيمة الشراء', 'القيمة')));
        $cap   = (float) str_replace(array(',', ' '), '', cmp03_bridge_pick($payload, array('رأس المال المموَّل', 'رأس مال الممول')));
        $pct   = ($value > 0 && $cap > 0) ? round(min(100, $cap / $value * 100), 2) : 100.00;
        $from  = cmp03_bridge_pick($payload, array('تاريخ الربط', 'التاريخ'));
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $from)) { $from = date('Y-m-d'); }
        $doc = cmp03_bridge_pick($payload, array('رقم السجل', 'رقم القيد'));
        if ($doc === '') { $doc = 'FAS-' . date('Ymd-His'); }

        $st = $conn->prepare("INSERT INTO asset_ownership_shares
                (company_id, asset_id, asset_kind, financier_entity_id, op_id, percent,
                 valid_from, doc_ref, recorded_percent, approved_percent, created_by)
              VALUES (?, ?, 'equipment', ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$st) { $out['code'] = 'FAS-500'; $out['msg'] = 'FAS-500: ' . $conn->error; return $out; }
        $st->bind_param('iiiidssddi', $companyId, $asset, $ent, $op, $pct, $from, $doc, $pct, $pct, $uid);
        $ok = $st->execute();
        $err = (string) $st->error;
        $st->close();
        if (!$ok) {
            $out['code'] = 'FAS-409';
            $out['msg']  = 'FAS-409: تعذّر ربطُ الحصة — ' . mb_substr($err, 0, 120);
            return $out;
        }
        $out['ok'] = true; $out['id'] = (int) $conn->insert_id; $out['code'] = 'FAS-201';
        $out['msg'] = 'رُبطت العينُ بحصةِ ' . $pct . '٪ للمموِّلِ #' . $ent
                    . ($op ? (' في العمليةِ #' . $op) : '') . ' — تظهر في حصصِ الملكيةِ فورًا';
        return $out;
    }
}

if (!function_exists('cmp03_bridge_site_gate_person')) {
    /**
     * ⇐ ثامنًا-٤ · توأمُ `cmp03_bridge_site_gate` للأشخاص.
     *
     * ◆ **الإذنُ يبقى في جدولِه** — فهو مستندُ الموقعِ لا نسخةٌ من غيرِه؛ والناقصُ
     *   كان **مفاتيحَه**: شخصٌ نصًّا وموقعٌ نصًّا ومورِّدٌ نصًّا ومعتمِدٌ بيدِ المُدخِل.
     * ◆ **ولا يُقبل إذنٌ بمرجعٍ لا يقابله صفّ**: رمزُ مشغِّلٍ أو اسمُ موقعٍ مجهولٌ
     *   ⇒ رفضٌ معلَنٌ برمز — **فالمفتاحُ المخترَعُ أسوأُ من النصِّ الحرّ**.
     * ◆ **والمورِّدُ اختياريٌّ قصدًا**: «التبعية» قد تكون الشركةَ نفسَها فلا مورِّدَ
     *   لها. فيُحلُّ إن ذُكر، ويبقى NULL إن لم يُذكرْ — **ولا يُرفض لأجلِه إذن**.
     *   وهذا فرقٌ عن المعدةِ والموقعِ: هذانِ شرطان وذاك وصفٌ.
     * ◆ والمعتمِدُ **من الجلسة** ولا يُقرأ من الحقل: «لا يُوقَّع باسمِ أحدٍ».
     */
    function cmp03_bridge_site_gate_person(mysqli $conn, $companyId, array $payload, $status, $uid)
    {
        $out = array('ok' => false, 'id' => 0, 'code' => 'SGP-422', 'msg' => '');
        $companyId = (int) $companyId; $uid = (int) $uid;

        /* ── الشخصُ من سجلِّ الموظفين: بالرمزِ أو بالاسم ── */
        $opCode = cmp03_bridge_pick($payload, array('كود المشغل', 'كود المشغّل', 'المشغل'));
        $opName = cmp03_bridge_pick($payload, array('الاسم', 'اسم المشغل'));
        $emp = 0;
        if ($opCode !== '' || $opName !== '') {
            $st = $conn->prepare('SELECT id FROM employees
                                   WHERE company_id = ?
                                     AND (employee_code = ? OR name = ? OR id = ?) LIMIT 1');
            if ($st) {
                $needle = ($opCode !== '') ? $opCode : $opName;
                $n = ctype_digit($needle) ? (int) $needle : 0;
                $st->bind_param('issi', $companyId, $needle, $opName, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $emp = $r ? (int) $r[0] : 0;
            }
        }
        if ($emp <= 0) {
            $out['msg'] = 'SGP-422: «' . ($opCode !== '' ? $opCode : $opName)
                        . '» لا يقابله صفٌّ في سجلِّ الموظفين — ولا يُصدَر إذنٌ لشخصٍ مجهول';
            return $out;
        }

        /* ── الموقعُ من سجلِّ المواقع ── */
        $siteName = cmp03_bridge_pick($payload, array('الموقع', 'اسم الموقع'));
        $site = 0;
        if ($siteName !== '') {
            $st = $conn->prepare('SELECT id FROM project
                                   WHERE company_id = ? AND (name = ? OR id = ?) LIMIT 1');
            if ($st) {
                $n = ctype_digit($siteName) ? (int) $siteName : 0;
                $st->bind_param('isi', $companyId, $siteName, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $site = $r ? (int) $r[0] : 0;
            }
        }
        if ($site <= 0) {
            $out['msg'] = 'SGP-422: الموقعُ «' . $siteName . '» لا يقابله موقعٌ في سجلِّ المواقع';
            return $out;
        }

        /* ── المورِّدُ وصفٌ لا شرط: يُحلُّ إن ذُكر ويبقى NULL إن لم يُذكر ── */
        $supName = cmp03_bridge_pick($payload, array('المورد التابع له', 'المورد', 'المورّد'));
        $sup = null;
        if ($supName !== '') {
            $st = $conn->prepare('SELECT id FROM suppliers
                                   WHERE company_id = ? AND (name = ? OR supplier_code = ? OR id = ?) LIMIT 1');
            if ($st) {
                $n = ctype_digit($supName) ? (int) $supName : 0;
                $st->bind_param('issi', $companyId, $supName, $supName, $n);
                $st->execute();
                $r = $st->get_result()->fetch_row();
                $st->close();
                $sup = $r ? (int) $r[0] : null;
            }
        }

        /* ── المعتمِدُ من الجلسةِ — والحقلُ النصيُّ يُهمَل إن كُتب ── */
        $approver = '';
        $st = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $uid);
            $st->execute();
            $r = $st->get_result()->fetch_row();
            $st->close();
            $approver = $r ? (string) $r[0] : '';
        }

        $st = $conn->prepare("INSERT INTO scr_site_gate_person
                (company_id, no_permit, type_permit, site_name, code_operator, name_ar,
                 affiliation, supplier_belongs_has, reason_movement, cycle_rotation,
                 date_start_work, date_end_work, trip_entry_or_exit, housing_allocated,
                 state_license, state_check_medical, attestation_security,
                 approval_manager_site, approval_manager_operations, approved_date,
                 employee_id, site_project_id, supplier_entity_id, approved_by_user,
                 status, is_seed, created_by, created_by_name, created_at, updated_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,NOW(),NOW())");
        if (!$st) { $out['code'] = 'SGP-500'; $out['msg'] = 'SGP-500: ' . $conn->error; return $out; }

        $g = function ($labels) use ($payload) { return cmp03_bridge_pick($payload, (array) $labels); };
        $v = array(
            $g('رقم الإذن'), $g('نوع الإذن'), $siteName, $opCode, $opName,
            $g('التبعية'), $supName, $g('سبب الحركة'), $g('دورة التناوب'),
            $g('تاريخ بداية العمل'), $g('تاريخ نهاية العمل'),
            $g('رحلة الدخول أو الخروج'), $g('السكن المخصص'),
            $g('حالة الرخصة'), $g('حالة الفحص الطبي'), $g('المصادقة الأمنية'),
            $approver, $approver, date('Y-m-d'),
        );
        /* نوعُ الربطِ يُبنى بالعدِّ لا يُكتب بيد — حرفٌ ناقصٌ يُزيح القيمَ كلَّها */
        $types = 'i' . str_repeat('s', 19) . 'iiii' . 's' . 'i' . 's';
        $st->bind_param($types,
            $companyId, $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7], $v[8],
            $v[9], $v[10], $v[11], $v[12], $v[13], $v[14], $v[15], $v[16], $v[17], $v[18],
            $emp, $site, $sup, $uid, $status, $uid, $approver);
        $ok  = $st->execute();
        $err = (string) $st->error;
        $st->close();
        if (!$ok) {
            $out['code'] = 'SGP-500';
            $out['msg']  = 'SGP-500: تعذّر إصدارُ الإذن — ' . mb_substr($err, 0, 110);
            return $out;
        }
        $out['ok'] = true; $out['id'] = (int) $conn->insert_id; $out['code'] = 'SGP-201';
        $out['msg'] = 'صدر الإذنُ مرتبطًا بالشخصِ #' . $emp . ' والموقعِ #' . $site
                    . ($sup ? ' والمورِّدِ #' . $sup : ' (بلا مورِّدٍ — تبعيةٌ داخلية)')
                    . ' — والمعتمِدُ «' . ($approver !== '' ? $approver : ('حساب #' . $uid))
                    . '» من حسابِك لا من الكتابة';
        return $out;
    }
}
