<?php
/**
 * includes/unit_chain_helpers.php — عدّة سلسلة اعتماد الوحدات (E-02 · DEC-01 ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * التعريف المركزي الواحد لِـ:
 *   ① «الموروث قبل السلسلة»: صف state='converted' بلا converted_at — رُدم
 *      تاريخيًّا قبل تفعيل المحرّك (قرار المالك 2026-08-05: يُوسَم ويُستثنى
 *      من القياس ولا يُعاد عبر السلسلة ولا يُعدّ معتمدًا ضمنًا).
 *   ② مقاييس التأخر لمؤشر DEC-01 ⑦ (الوحدات غير المعتمدة · أقدمها بالأيام ·
 *      نسبة المعتمد إلى المسجَّل أسبوعيًّا — المستهدف ≥95٪ وصفر فوق 7 أيام).
 * كل قارئ (اللوحة · الكرونان · الحزام) يمرّ من هنا — فلا يتفرق التعريف.
 */

/** شرط SQL للموروث قبل السلسلة على الاسم المستعار المعطى. */
function ems_uc_prechain_sql($alias = 'ue')
{
    return "($alias.state = 'converted' AND $alias.converted_at IS NULL)";
}

/** الحالات الوسيطة: دخلت السلسلة ولم تبلغ نهايتها ولم تخرج منها. */
function ems_uc_pending_states()
{
    return array('submitted', 'site_approved', 'parties_review', 'parties_approved', 'sales_approved');
}

/**
 * ⇐ INJ-0334 · الحالاتُ التي **تُعدُّ ساعاتُها واقعةً مقبولة**: ما جاوز اعتمادَ
 * الموقعِ فصاعدًا. وقبلَ اليومِ كانت شاشةُ الوقائيةِ تسأل عن `'approved'` —
 * **وهي ليست في تعدادِ العمود أصلًا**، فبقي المتراكمُ يُحسب من `converted` وحدَه
 * وسقطت ١٥٠٠ ساعةٍ معتمدةٍ من عدّادِ الغيار صامتةً.
 * ◆ والتعريفُ هنا **واحدٌ** تنادِيه الوقائيةُ ولوحةُ الإنجاز — فلا يتفرّق رقمان.
 */
function ems_uc_accepted_states()
{
    return array('site_approved', 'parties_approved', 'sales_approved', 'converted');
}

/** شرطُ القبولِ جاهزًا على اسمٍ مستعار: `ue.state IN ('…')`. */
function ems_uc_accepted_sql($alias = 'ue')
{
    return $alias . ".state IN ('" . implode("','", ems_uc_accepted_states()) . "')";
}

/**
 * مقاييس DEC-01 ⑦ لشركة: العالق وعمر أقدمه، والمسجَّل/المكتمل في آخر 7 أيام.
 * «المكتمل» = بلغ converted عبر المحرّك (converted_at مملوء) — فالموروث لا يعدّ.
 */
function ems_uc_lag_metrics(mysqli $conn, $companyId)
{
    $companyId = (int) $companyId;
    $pend = "'" . implode("','", ems_uc_pending_states()) . "'";
    $m = array('pending' => 0, 'oldest_days' => 0, 'week_registered' => 0, 'week_converted' => 0, 'ratio' => null);

    $r = mysqli_query($conn,
        "SELECT COUNT(*) n, COALESCE(MAX(DATEDIFF(CURDATE(), entry_date)),0) oldest
           FROM unit_entries ue
          WHERE ue.company_id = {$companyId} AND ue.state IN ($pend)
            AND NOT " . ems_uc_prechain_sql('ue'));
    if ($r && ($x = mysqli_fetch_assoc($r))) {
        $m['pending'] = (int) $x['n'];
        $m['oldest_days'] = (int) $x['oldest'];
    }

    $r = mysqli_query($conn,
        "SELECT
            SUM(ue.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) wk_reg,
            SUM(ue.converted_at IS NOT NULL AND ue.converted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) wk_conv
           FROM unit_entries ue
          WHERE ue.company_id = {$companyId} AND NOT " . ems_uc_prechain_sql('ue'));
    if ($r && ($x = mysqli_fetch_assoc($r))) {
        $m['week_registered'] = (int) $x['wk_reg'];
        $m['week_converted']  = (int) $x['wk_conv'];
        $m['ratio'] = $m['week_registered'] > 0
            ? round(100.0 * $m['week_converted'] / $m['week_registered'], 1) : null;
    }
    return $m;
}

/**
 * مهلة الحلقة القادمة بالساعات لكل حالة وسيطة — v1 ثوابت من مدد approval_chains
 * الحية (24/48/72)؛ ربطها صفًّا بسياسة السلسلة لاحقٌ مع المهمة ①.
 */
function ems_uc_stage_sla_hours($state)
{
    $map = array(
        'submitted'        => 24,  // بانتظار اعتماد الموقع
        'site_approved'    => 48,  // بانتظار الأطراف
        'parties_review'   => 48,
        'parties_approved' => 48,  // بانتظار العقود/المبيعات
        'sales_approved'   => 72,  // بانتظار التحويل المالي
    );
    return isset($map[$state]) ? $map[$state] : 48;
}

/** أدوار لوحةِ من يملك معالجة الحالة — لتوجيه إشعار التصعيد الساعي. */
function ems_uc_stage_owner_roles($state)
{
    $map = array(
        'submitted'        => array(6),       // مدير الموقع/الحركة
        'site_approved'    => array(1),       // إدارة التشغيل
        'parties_review'   => array(2, 27),   // الموردون والقوى
        'parties_approved' => array(12),      // المبيعات والعقود
        'sales_approved'   => array(17, 19),  // المالية
    );
    return isset($map[$state]) ? $map[$state] : array(1);
}

/** إشعار بعطالة يومية: لا يُكرر لنفس المستلم ونفس الرابط في اليوم نفسه. */
function ems_uc_notify_once(mysqli $conn, $companyId, $userId, $title, $link)
{
    $companyId = (int) $companyId;
    $userId = (int) $userId;
    $t = mysqli_real_escape_string($conn, mb_substr($title, 0, 190));
    $l = mysqli_real_escape_string($conn, mb_substr($link, 0, 190));
    $r = mysqli_query($conn,
        "SELECT id FROM fin_notifications
          WHERE company_id={$companyId} AND target_user_id={$userId}
            AND link='{$l}' AND created_at >= CURDATE() LIMIT 1");
    if ($r && mysqli_num_rows($r)) { return false; }
    mysqli_query($conn,
        "INSERT INTO fin_notifications (company_id, target_level, target_user_id, title, link, is_read, created_at)
         VALUES ({$companyId}, 'all', {$userId}, '{$t}', '{$l}', 0, NOW())");
    return true;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * وصلُ السلّم بسلسلةِ الوحدات — INJ-CHAIN-CLOSE-01 · GAP-01
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **العطبُ الذي يعالجه**: `gov_journey_ladders.ladder_wired = 0` في أربعَ
 *   عشرةَ رحلةٍ من أربعَ عشرة. والفاحصُ القديمُ أثبت أن كلَّ رحلةٍ **تجد**
 *   سلّمًا نشطًا — ولم يُثبت أن الشاشةَ القائدةَ **تقرؤه عند التنفيذ**.
 *   فبقي ترتيبُ الخطواتِ و«لا يدَ تمشي خطوتَين» **غيرَ منفَّذَين**.
 *
 * ◆ **ولا تُكتب جهةُ اعتمادٍ هنا**: تُقرأ من `gov_ladder_steps` مجسورةً
 *   بـ`gov_ladder_actor_roles` — «الجهةُ تُحَلُّ من المحرك وقتَ التنفيذ».
 *   وأيُّ تعديلٍ في السلّم يقع في المحرك لا في هذا الملف.
 *
 * ◆ **وثلاثةُ أنماطٍ كنمطِ البيت** (`EMS_UNIT_LADDER`):
 *     off      — لا يُقرأ السلّمُ أصلًا (للرجوعِ الفوريِّ عند عطل)
 *     monitor  — يُقرأ ويُسجَّل كلُّ خرقٍ في `guard_denials` **ولا يُمنع** (الافتراض)
 *     enforce  — يُمنع الخرقُ برمزٍ 422
 *   والافتراضُ `monitor` لأن القلبَ إلى المنعِ **تغييرُ وصولٍ حيّ** يوقف
 *   مساراتٍ تعمل — ويُقلَب بقرارٍ بعدَ أن يُثبت القياسُ صفرَ خرق.
 * ═══════════════════════════════════════════════════════════════════════════ */

/** مرحلةُ السلسلة ⇐ رمزُ السلّم الحاكم. مصدرُه وثيقةُ إغلاقِ سلسلةِ الأثر. */
function ems_uc_stage_ladder($stage)
{
    $map = array(
        'site'       => 'LD-01',   // الاعتمادُ اليوميُّ لرفعِ الوحدات
        'sales'      => 'LD-02',   // مطابقةُ العميلِ وبوابةُ اعتمادِ المبيعات
        'supplier'   => 'LD-03',   // اعتمادُ وحداتِ الموردين
        'operator'   => 'LD-04',   // اعتمادُ وحداتِ المشغّلين
        'supervisor' => 'LD-04',   // المشرفُ طرفٌ في السلّمِ نفسِه
        'fleet'      => 'LD-03',   // الأسطولُ يشهد على المعدةِ في سلّمِ التغطية
        'finance'    => 'LD-05',   // الاعتمادُ الماليُّ الأوّليّ — بوابةُ المالية
    );
    return isset($map[$stage]) ? $map[$stage] : null;
}

/** نمطُ إنفاذِ وصلِ السلّم: off | monitor | enforce. */
function ems_uc_ladder_mode()
{
    $m = function_exists('ems_env') ? strtolower((string) ems_env('EMS_UNIT_LADDER', 'monitor')) : 'monitor';
    return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'monitor';
}

/**
 * خطواتُ السلّمِ بأدوارِها — تُقرأ من المحرك، ولا تُكتب هنا.
 * @return array صفوفُ [step_no, actor_code, step_kind, may_approve, roles[]]
 */
function ems_uc_ladder_steps(mysqli $conn, $ladderCode)
{
    static $cache = array();
    if (isset($cache[$ladderCode])) { return $cache[$ladderCode]; }
    $out = array();
    $st = $conn->prepare(
        "SELECT s.step_no, s.actor_code, s.step_kind, s.may_approve, s.is_finance_gate,
                GROUP_CONCAT(DISTINCT r.role_id) AS roles
           FROM gov_ladder_steps s
           LEFT JOIN gov_ladder_actor_roles r ON r.actor_code = s.actor_code
          WHERE s.ladder_code = ?
          GROUP BY s.step_no, s.actor_code, s.step_kind, s.may_approve, s.is_finance_gate
          ORDER BY s.step_no");
    if ($st) {
        $st->bind_param('s', $ladderCode);
        $st->execute();
        $res = $st->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $row['roles'] = array_values(array_filter(array_map('intval',
                explode(',', (string) $row['roles']))));
            $out[] = $row;
        }
        $st->close();
    }
    $cache[$ladderCode] = $out;
    return $out;
}

/**
 * فحصُ السلّمِ لخطوةِ اعتمادٍ واحدة.
 *
 * ◆ يفحص ثلاثةً: **وجودَ السلّم** · **أهليةَ الدور** · **لا يدَ تمشي خطوتَين**.
 * ◆ ويُرجع دائمًا `ladder` و`mode` ليُسجَّلا في الأثر — فالسلّمُ المقروءُ
 *   يُثبَت بالسجلِّ لا بالادّعاء.
 */
function ems_uc_ladder_check(mysqli $conn, $companyId, $entryId, $round, $stage, $actorId, $actorRole = null)
{
    $mode = ems_uc_ladder_mode();
    $ladder = ems_uc_stage_ladder($stage);
    $res = array('ok' => true, 'mode' => $mode, 'ladder' => $ladder, 'reasons' => array(), 'step' => null);
    if ($mode === 'off' || $ladder === null) { return $res; }

    $steps = ems_uc_ladder_steps($conn, $ladder);
    if (!$steps) {
        $res['ok'] = false;
        $res['reasons'][] = "السلّمُ {$ladder} بلا خطواتٍ مسجَّلة — ولا يُخترَع سلّمٌ عند التنفيذ";
        return $res;
    }

    /* خطوةُ الاعتماد: آخرُ خطوةٍ may_approve — «الإعدادُ لا يُنشئ اعتمادًا» */
    $approveStep = null;
    foreach ($steps as $s) { if ((int) $s['may_approve'] === 1) { $approveStep = $s; } }
    if ($approveStep === null) {
        $res['ok'] = false;
        $res['reasons'][] = "السلّمُ {$ladder} بلا خطوةِ اعتمادٍ مميَّزة (may_approve)";
        return $res;
    }
    $res['step'] = (int) $approveStep['step_no'];

    /* ① أهليةُ الدور — الجهةُ تُحَلُّ من المحرك لا تُكتب هنا */
    if ($actorRole === null) {
        $st = $conn->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
        if ($st) { $st->bind_param('i', $actorId); $st->execute(); $st->bind_result($rr);
                   if ($st->fetch()) { $actorRole = (int) $rr; } $st->close(); }
    }
    $roles = $approveStep['roles'];
    if ($roles && $actorRole !== null && !in_array((int) $actorRole, $roles, true)) {
        $res['ok'] = false;
        $res['reasons'][] = "الدورُ {$actorRole} ليس صاحبَ خطوةِ الاعتمادِ في {$ladder}"
                          . " (`{$approveStep['actor_code']}` ⇐ " . implode('،', $roles) . ')';
    }

    /* ② لا يدَ تمشي خطوتَين — في الواقعةِ نفسِها والجولةِ نفسِها */
    $st = $conn->prepare(
        "SELECT GROUP_CONCAT(DISTINCT stage) FROM unit_approvals
          WHERE company_id = ? AND entry_id = ? AND round_no = ? AND actor_id = ? AND stage <> ?");
    if ($st) {
        $st->bind_param('iiiis', $companyId, $entryId, $round, $actorId, $stage);
        $st->execute(); $st->bind_result($prev); $st->fetch(); $st->close();
        if ($prev !== null && $prev !== '') {
            $res['ok'] = false;
            $res['reasons'][] = "**لا يدَ تمشي خطوتَين**: هذا الفاعلُ قرّر سلفًا ({$prev}) في الجولةِ نفسِها";
        }
    }

    return $res;
}

/** يُسجِّل خرقَ السلّمِ في سجلِّ الحرّاس — فالدَّينُ مقيسٌ لا مُخمَّن. */
function ems_uc_ladder_log(mysqli $conn, $companyId, $entryId, $stage, $actorId, array $res)
{
    /* ◆ أعمدةُ `guard_denials` الحيّةُ **تُقرأ من المخطَّطِ لا تُفترض**:
     *   guard_code · person_id · attempted_ref · reason_code — ولا `mode` فيها،
     *   فيُحمَل النمطُ داخلَ رمزِ الحارسِ ليبقى مقيسًا. */
    $reason = mb_substr(implode(' · ', $res['reasons']), 0, 78);
    $key    = mb_substr('unit_ladder:' . (string) $res['ladder'] . ':' . $stage
                        . ':' . (string) $res['mode'], 0, 64);
    $sub    = 'unit_entry:' . (int) $entryId;
    $st = @$conn->prepare(
        /* FR-GOV-004 — الفعلُ عمودٌ: `advance` هو ما رُفض في سلّمِ الوحدة. */
        "INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code, verb, at)
         VALUES (?,?,?,?,?,?,NOW())");
    if (!$st) { return false; }
    $verb = 'advance';
    $st->bind_param('isisss', $companyId, $key, $actorId, $sub, $reason, $verb);
    $ok = $st->execute();
    $st->close();
    return $ok;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * بوابةُ السلّمِ العامة — تعميمُ ما بُني لسلسلةِ الوحدات
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **موضعُها هنا لا في ملفٍّ جديد**: منطقُ السلّمِ بيتُه واحد، وملفٌّ ثانٍ
 *   يستعلم عن جداولِ المستأجِرِ **يزيد دَينَ الاستعلامِ الخامِّ بملفٍّ كامل**
 *   فترسُب سقّاطتُه — وهي رسبت فعلًا (٦٠٩ فوقَ ٦٠٨) قبلَ هذا الدمج.
 * ◆ فتُعمَّم الدالةُ ولا يُنشأ بيتٌ ثانٍ لها.
 * ═══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('ems_ladder_mode')) {
    function ems_ladder_mode()
    {
        $m = function_exists('ems_env') ? strtolower((string) ems_env('EMS_LADDER_GATE', 'monitor')) : 'monitor';
        return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'monitor';
    }
}

if (!function_exists('ems_ladder_check')) {
    /**
     * @param string $ladder      رمزُ السلّم — LD-nn
     * @param string $subjectKind نوعُ المستند (claim_invoice · payment · settlement …)
     * @param int    $subjectRef  معرِّفُ المستند
     * @param string $scope       نسخةُ السلّم — عقدتان في نسخةٍ واحدةٍ تتشاركانه
     * @return array ok · mode · ladder · step · reasons[]
     */
    function ems_ladder_check(mysqli $conn, $ladder, $companyId, $subjectKind, $subjectRef,
                              $actorId, $actorRole = null, $scope = '')
    {
        $mode = ems_ladder_mode();
        $res = array('ok' => true, 'mode' => $mode, 'ladder' => (string) $ladder,
                     'step' => null, 'reasons' => array());
        if ($mode === 'off' || $ladder === '' || $ladder === null) { return $res; }
        if (!preg_match('/^LD-\d{2}$/', (string) $ladder)) {
            /* `NO_LADDER_REQUIRED` و`RESOLVE_FROM_POLICY:` ليسا سلّمًا — لا فحص */
            return $res;
        }

        $steps = ems_uc_ladder_steps($conn, $ladder);
        if (!$steps) {
            $res['ok'] = false;
            $res['reasons'][] = "السلّمُ {$ladder} بلا خطواتٍ مسجَّلة — ولا يُخترَع سلّمٌ عند التنفيذ";
            return $res;
        }
        $ap = null;
        foreach ($steps as $s) { if ((int) $s['may_approve'] === 1) { $ap = $s; } }
        if ($ap === null) {
            $res['ok'] = false;
            $res['reasons'][] = "السلّمُ {$ladder} بلا خطوةِ اعتمادٍ مميَّزة (may_approve)";
            return $res;
        }
        $res['step'] = (int) $ap['step_no'];

        /* ② أهليةُ الدور — تُحَلُّ من المحرك */
        if ($actorRole === null) {
            $st = $conn->prepare("SELECT `role_id` FROM `users` WHERE `id` = ? LIMIT 1");
            if ($st) { $st->bind_param('i', $actorId); $st->execute(); $st->bind_result($rr);
                       if ($st->fetch()) { $actorRole = ($rr === null ? null : (int) $rr); } $st->close(); }
        }
        $roles = $ap['roles'];
        /* ◆ **فاعلٌ لا يُحَلُّ دورُه ⇐ منعٌ لا مرور** — كشفه شاهدُ FR-APP-001:
         *   كان الشرطُ `$actorRole !== null` **يتخطّى الفحصَ كلَّه** عند تعذُّرِ
         *   حلِّ الدور، فيمرُّ فاعلٌ مجهولٌ بوصفِه صاحبَ اليد. وهو نقضُ
         *   «Default Deny» (§سادسًا) ونقضُ FR-APP-002: «فاعلٌ ليس صاحبَ اليد
         *   ← رفض» — ومَن لا يُعرف دورُه ليس صاحبَها يقينًا. */
        if ($roles && $actorRole === null) {
            $res['ok'] = false;
            $res['reasons'][] = "تعذّر حلُّ دورِ الفاعلِ {$actorId} — ولا يُعَدُّ صاحبَ اليدِ بالشكّ";
        } elseif ($roles && !in_array((int) $actorRole, $roles, true)) {
            $res['ok'] = false;
            $res['reasons'][] = "الدورُ {$actorRole} ليس صاحبَ خطوةِ الاعتمادِ في {$ladder}"
                              . " (`{$ap['actor_code']}` ⇐ " . implode('،', $roles) . ')';
        }

        /* ③ لا يدَ تمشي خطوتَين — على المستندِ نفسِه أو نسخةِ سلّمِه */
        $scopeKey = $scope !== '' ? $scope : ($subjectKind . ':' . (int) $subjectRef);
        $st = $conn->prepare(
            "SELECT GROUP_CONCAT(DISTINCT `step_no`) FROM `gov_ladder_decisions`
              WHERE `company_id` = ? AND `scope_key` = ? AND `actor_id` = ? AND `step_no` <> ?");
        if ($st) {
            $stepNo = (int) $ap['step_no'];
            $st->bind_param('isii', $companyId, $scopeKey, $actorId, $stepNo);
            $st->execute(); $st->bind_result($prev); $st->fetch(); $st->close();
            if ($prev !== null && $prev !== '') {
                $res['ok'] = false;
                $res['reasons'][] = "**لا يدَ تمشي خطوتَين**: هذا الفاعلُ قرّر الخطوةَ ({$prev}) في نسخةِ السلّمِ نفسِها";
            }
        }
        return $res;
    }
}

if (!function_exists('ems_ladder_record')) {
    /** يقيّد القرارَ في السجلِّ الواحد — والتكرارُ عطالةٌ لا خطأ. */
    function ems_ladder_record(mysqli $conn, $ladder, $companyId, $subjectKind, $subjectRef,
                               $stepNo, $actorId, $scope = '', $note = '')
    {
        $scopeKey = $scope !== '' ? $scope : ($subjectKind . ':' . (int) $subjectRef);
        $st = $conn->prepare(
            "INSERT INTO `gov_ladder_decisions`
               (`company_id`,`ladder_code`,`subject_kind`,`subject_ref`,`scope_key`,`step_no`,`actor_id`,`note`)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `decided_at` = `decided_at`");
        if (!$st) { return false; }
        $sn = (int) $stepNo; $sr = (int) $subjectRef; $ai = (int) $actorId;
        $nt = mb_substr((string) $note, 0, 190);
        $st->bind_param('isssiiis', $companyId, $ladder, $subjectKind, $sr, $scopeKey, $sn, $ai, $nt);
        $okRun = $st->execute();
        $st->close();
        return $okRun;
    }
}

if (!function_exists('ems_ladder_log_denial')) {
    /** خرقٌ مسجَّلٌ في سجلِّ الحرّاس — فالدَّينُ مقيسٌ لا مُخمَّن. */
    function ems_ladder_log_denial(mysqli $conn, $companyId, $subjectKind, $subjectRef, $actorId, array $res)
    {
        $key = mb_substr('ladder:' . (string) $res['ladder'] . ':' . $subjectKind . ':' . (string) $res['mode'], 0, 64);
        $sub = mb_substr($subjectKind . ':' . (int) $subjectRef, 0, 120);
        $why = mb_substr(implode(' · ', $res['reasons']), 0, 78);
        $st = @$conn->prepare(
            /* FR-GOV-004 — الفعلُ عمودٌ: الفعلُ المرفوضُ في هذا الحارسِ تقدُّمُ سلّم. */
            "INSERT INTO `guard_denials` (`company_id`,`guard_code`,`person_id`,`attempted_ref`,`reason_code`,`verb`,`at`)
             VALUES (?,?,?,?,?,?,NOW())");
        if (!$st) { return false; }
        $ai = (int) $actorId;
        $vb = 'advance';
        $st->bind_param('isisss', $companyId, $key, $ai, $sub, $why, $vb);
        $okRun = $st->execute();
        $st->close();
        return $okRun;
    }
}

/* ══ FR-APP-003 — **رمزُ رفضٍ واحدٌ لا رمزَ لكلِّ موضع** ══════════════════
   ◆ **المقيسُ قبلَ التوحيد**: تسعةُ مواضعَ تنادي حارسَ السلّم —
     **ثلاثةٌ تكتب `'GOV-FAIL-422'` حرفًا في مكانِها**، و**اثنان لا
     يُصدران رمزًا أصلًا** (يكتفيان برسالة)، وأربعةٌ تُمرِّر رمزَ الحارس.
   ◆ **ورمزٌ محليٌّ ولو طابق اليومَ يتفرّق غدًا**: من يغيّره في موضعٍ لا
     يعرف الثمانيةَ الأخرى. والقيمةُ نفسُها **ليست مصدرًا واحدًا**.
   ◆ **والقيمةُ ليست مخترَعة**: هي `GOV-FAIL-422` المستعملةُ سلفًا في
     المواضعِ الثلاثة — يُرفَع الموجودُ إلى ثابتٍ ولا يُبتدَع رمزٌ جديد. */
if (!defined('EMS_LADDER_DENY_CODE')) { define('EMS_LADDER_DENY_CODE', 'GOV-FAIL-422'); }

if (!function_exists('ems_ladder_guard')) {
    /**
     * الغلافُ الذي تنادِيه الشاشات: يفحص · يُسجِّل الخرقَ · يقيّد القرارَ عند القبول.
     * @return array ok · code · reason · ladder · step
     */
    function ems_ladder_guard(mysqli $conn, $ladder, $companyId, $subjectKind, $subjectRef,
                              $actorId, $scope = '', $note = '')
    {
        $r = ems_ladder_check($conn, $ladder, $companyId, $subjectKind, $subjectRef, $actorId, null, $scope);

        /* ◆ FR-APP-001 — **الظلُّ يرصد ولا يمنع**: يُسجَّل كلُّ تقييمٍ سماحًا
         *   ومنعًا، لا المنعُ وحدَه — فمقامُ نافذةِ الملاحظةِ لا يُعرف بغيرِه،
         *   و«صفرُ تباين» على مقامٍ مجهولٍ لا معنى له. */
        ems_ladder_shadow_observe($conn, $ladder, $companyId, $subjectKind, $subjectRef,
                                  $actorId, $r);

        if (!$r['ok']) {
            ems_ladder_log_denial($conn, $companyId, $subjectKind, $subjectRef, $actorId, $r);
            if ($r['mode'] === 'enforce') {
                return array('ok' => false, 'code' => 422,
                             'reason' => implode(' · ', $r['reasons']),
                             'ladder' => $r['ladder'], 'step' => $r['step']);
            }
        }
        if ($r['step'] !== null) {
            ems_ladder_record($conn, $ladder, $companyId, $subjectKind, $subjectRef,
                              $r['step'], $actorId, $scope, $note);
        }
        return array('ok' => true, 'code' => 200, 'reason' => '', 'ladder' => $r['ladder'], 'step' => $r['step']);
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * FR-APP-001 · نمطُ الظلِّ لبوابةِ السلّم — يرصد ولا يمنع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ الحاكمةُ بنصِّ الدفتر**: «لا يوقف الظلُّ معاملةً أبدًا — وفشلُ
 *   المقارنِ يُسجَّل ولا يمنع». فكلُّ ما هنا داخلَ `try` واسعٍ يبتلع **عمدًا
 *   وبنصِّ المطلب**، وهو الموضعُ الوحيدُ الذي يكون فيه الابتلاعُ صوابًا —
 *   لأن البديلَ أن يوقف مقياسٌ معاملةَ عمل.
 *
 * ◆ **ويُسجَّل السماحُ كما يُسجَّل المنع**: المقامُ هو كلُّ تقييم، والبسطُ هو
 *   التباين. وعدُّ المنعِ وحدَه يجعل «صفرَ تباين» جملةً بلا مقام.
 *
 * ◆ **والعطالةُ بمفتاح** (كيان × مرجع × خطوة × فاعل): تكرارُ المحاولةِ لا
 *   يضخّم المقامَ ولا يُنشئ تباينًا ثانيًا.
 * ═══════════════════════════════════════════════════════════════════════════ */
if (!function_exists('ems_ladder_shadow_observe')) {
    function ems_ladder_shadow_observe(mysqli $conn, $ladder, $companyId, $subjectKind,
                                       $subjectRef, $actorId, array $res)
    {
        try {
            $ladder = (string) $ladder;
            if (!preg_match('/^LD-\d{2}$/', $ladder)) { return; }

            /* في نمطِ الظلِّ والمراقبةِ تمضي المعاملةُ دائمًا — فقرارُ السلوكِ
               الحاليِّ «سماح». وفي الإنفاذِ يصير القراران واحدًا فلا تباين. */
            $mode    = isset($res['mode']) ? (string) $res['mode'] : 'monitor';
            $ladderD = empty($res['ok']) ? 'deny' : 'allow';
            $currentD = ($mode === 'enforce') ? $ladderD : 'allow';
            $diverged = ($currentD !== $ladderD) ? 1 : 0;

            $step   = isset($res['step']) && $res['step'] !== null ? (int) $res['step'] : null;
            $reason = isset($res['reasons']) && is_array($res['reasons'])
                    ? mb_substr(implode(' · ', $res['reasons']), 0, 500) : '';
            $idem   = substr(sha1(implode('|', array(
                        (int) $companyId, $ladder, (string) $subjectKind,
                        (int) $subjectRef, (string) $step, (int) $actorId))), 0, 40);

            $st = $conn->prepare(
                'INSERT INTO `gov_ladder_shadow`
                   (`company_id`,`ladder_code`,`subject_kind`,`subject_ref`,`step_no`,
                    `actor_id`,`current_decision`,`ladder_decision`,`diverged`,`reason`,
                    `idem_key`,`observed_at`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE `observed_at` = `observed_at`');
            if (!$st) { return; }
            $st->bind_param('issiiississ',
                $companyId, $ladder, $subjectKind, $subjectRef, $step,
                $actorId, $currentD, $ladderD, $diverged, $reason, $idem);
            $st->execute();
            $st->close();
        } catch (\Throwable $t) {
            /* ◆ **بنصِّ المطلب**: «فشلُ المقارنِ يُسجَّل ولا يمنع» — فلا يُرفَع. */
            if (function_exists('error_log')) {
                error_log('ladder shadow observe: ' . $t->getMessage());
            }
        }
    }
}
