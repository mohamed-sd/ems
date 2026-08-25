<?php
namespace App\Services\Finance;

/**
 * app/Services/Finance/PermissionDerivation.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIN-ACC-01 §٤-٧ (FACC-0071..0081) — **اشتقاقُ الصلاحيةِ من أحدَ عشرَ عاملًا
 * لا من مسمًّى عام**. ونصُّ اختبارِ القبولِ حرفًا:
 *
 *   «اختبارُ اشتقاق: الصلاحيةُ من العواملِ الأحدَ عشرَ لا من مسمًّى عام»
 *
 * وكانت العواملُ مسجَّلةً في `gov_doc_registry` بتغطيةِ `seed` — أي **مكتوبةً
 * لا مُشتقَّة**. وهذا الملفُّ يجعلها تحسب.
 *
 * ◆ البنيةُ: عشرةُ عواملَ موجبةٍ تُضيِّق النطاق، وعاملٌ حاديَ عشرَ **سالبٌ**
 *   (FACC-0081 «− المنعُ الصريح») — والإشارةُ السالبةُ في الوثيقةِ ليست زينة:
 *   المنعُ الصريحُ **نقضٌ لا موازنة**. فمهما اجتمعت العشرةُ موجبةً، منعٌ واحدٌ
 *   صريحٌ يُسقط الصلاحيةَ كلَّها. ولذلك يُحسب أخيرًا ولا يُجمع مع غيره.
 *
 * ◆ ولماذا خدمةٌ لا استعلام: الاشتقاقُ يُنادى من ثلاثةِ مواضعَ (منحُ الصلاحيةِ
 *   · التوجيهُ إلى محاسبٍ · فحصُ سلسلةِ الاعتماد) — وتكرارُه ثلاثًا يجعل ثلاثةَ
 *   تعريفاتٍ تتباعد. والتعريفُ الواحدُ هنا.
 *
 * المصدر: FIN-ACC-01 §٤-٧ · FACC-0071..0081
 */
class PermissionDerivation
{
    /** العواملُ العشرةُ الموجبةُ ثم السالبُ — بترتيبِ الوثيقةِ ومرجعِ كلٍّ. */
    const FACTORS = array(
        'PFACTOR-01' => array('relation',   'العلاقة الوظيفية',          'FACC-0071', 1),
        'PFACTOR-02' => array('family',     'العائلة الوظيفية',          'FACC-0072', 1),
        'PFACTOR-03' => array('spec',       'التخصص المحاسبي',           'FACC-0073', 1),
        'PFACTOR-04' => array('level',      'المستوى الوظيفي',            'FACC-0074', 1),
        'PFACTOR-05' => array('department', 'الإدارة',                    'FACC-0075', 1),
        'PFACTOR-06' => array('entity',     'الكيان',                     'FACC-0076', 1),
        'PFACTOR-07' => array('project',    'المشروع أو مركز التكلفة',   'FACC-0077', 1),
        'PFACTOR-08' => array('assignment', 'التكليف',                    'FACC-0078', 1),
        'PFACTOR-09' => array('cap',        'السقف المالي',              'FACC-0079', 1),
        'PFACTOR-10' => array('validity',   'مدة السريان',               'FACC-0080', 1),
        /* ◆ السالب: نقضٌ لا موازنة. */
        'PFACTOR-11' => array('deny',       'المنع الصريح',              'FACC-0081', -1),
    );

    /**
     * يشتقُّ صلاحيةَ شخصٍ على فعلٍ بعينِه من العواملِ الأحدَ عشرَ.
     *
     * @param array $ctx user_id · company_id · action_code (اختياري) ·
     *                   amount (اختياري) · project_id (اختياري) · department (اختياري)
     * @return array allowed · factors[] لكلٍّ (met · value · why) · denied_by · reason
     */
    public static function derive(\mysqli $conn, array $ctx)
    {
        $uid = intval($ctx['user_id'] ?? 0);
        $co  = intval($ctx['company_id'] ?? 0);
        if ($uid <= 0 || $co <= 0) {
            return array('allowed' => false, 'code' => 422, 'factors' => array(),
                         'reason' => 'الاشتقاق يحتاج الشخص والكيان');
        }
        $action = trim((string) ($ctx['action_code'] ?? ''));
        $amount = isset($ctx['amount']) && $ctx['amount'] !== '' ? (float) $ctx['amount'] : null;

        $u = self::userRow($conn, $uid);
        if ($u === null) {
            return array('allowed' => false, 'code' => 404, 'factors' => array(),
                         'reason' => 'مستخدم غير موجود: ' . $uid);
        }
        $acc = self::accountantRow($conn, $co, $u);
        $F = array();

        /* ① العلاقةُ الوظيفية — أهو موظفٌ مربوطٌ بحسابٍ حيٍّ أصلًا؟ */
        $rel = (int) $u['employee_id'] > 0 && (int) $u['is_active'] === 1;
        $F['PFACTOR-01'] = self::f($rel, $rel ? 'موظف مربوط وحسابه حي' : 'حساب بلا موظف أو غير نشط');

        /* ② العائلةُ الوظيفية — أهو في عائلةِ المالية؟ */
        $famRoles = array(17, 18, 19, 20, 21, 22, 31, 32, 33, 34, 35);
        $inFam = in_array((int) $u['role_id'], $famRoles, true);
        $F['PFACTOR-02'] = self::f($inFam, $inFam ? 'ضمن العائلة المالية' : 'خارج العائلة المالية — الدور ' . $u['role_id']);

        /* ③ التخصصُ المحاسبي — من `fin_accountants.spec_code` لا من مسمّى. */
        $spec = $acc ? (string) $acc['spec_code'] : '';
        $F['PFACTOR-03'] = self::f($spec !== '', $spec !== '' ? 'التخصص ' . $spec : 'بلا تخصص مسند');

        /* ④ المستوى الوظيفي — من `positions` عبر `users.position_id`. */
        $lvl = self::positionName($conn, (int) $u['position_id']);
        $F['PFACTOR-04'] = self::f($lvl !== '', $lvl !== '' ? 'المسمى «' . $lvl . '»' : 'بلا مسمى وظيفي مسجل');

        /* ⑤ الإدارة. */
        $dept = $acc ? (int) $acc['finance_unit_id'] : 0;
        $F['PFACTOR-05'] = self::f($dept > 0, $dept > 0 ? 'وحدة مالية #' . $dept : 'بلا وحدة مالية');

        /* ⑥ الكيان — والعزلُ بنيويٌّ: كيانُ الشخصِ هو كيانُ الطلبِ أو لا صلاحية. */
        $sameCo = ((int) $u['company_id'] === $co);
        $F['PFACTOR-06'] = self::f($sameCo, $sameCo ? 'الكيان ' . $co : 'كيان الشخص ' . $u['company_id'] . ' ≠ ' . $co);

        /* ⑦ المشروعُ أو مركزُ التكلفة — `users.project_id` هو النطاقُ الحي:
             الفارغُ = بلا تقييدِ مشروعٍ · والمملوءُ = هذا المشروعُ وحدَه. */
        $prj = intval($ctx['project_id'] ?? 0);
        $myPrj = (int) $u['project_id'];
        if ($prj <= 0)      { $F['PFACTOR-07'] = self::f(true, 'لا مشروع مطلوبا — العامل غير مقيد'); }
        elseif ($myPrj <= 0) { $F['PFACTOR-07'] = self::f(true, 'بلا تقييد مشروع على الشخص'); }
        else {
            $ok7 = ($myPrj === $prj);
            $F['PFACTOR-07'] = self::f($ok7, $ok7 ? 'المشروع #' . $prj . ' هو نطاقه'
                                               : 'نطاقه المشروع #' . $myPrj . ' لا #' . $prj);
        }

        /* ⑧ التكليف — والتكليفُ لا يسري قبلَ موافقةِ الرئيس (CEO-Y0121). */
        require_once dirname(dirname(__DIR__)) . '/Services/Exec/AssignmentGate.php';
        $needsAsg = \App\Services\Exec\AssignmentGate::kindOfRole((int) $u['role_id']) !== 'none';
        $asgOk = !$needsAsg || \App\Services\Exec\AssignmentGate::isEffective($conn, $co, $uid, (int) $u['role_id']);
        $F['PFACTOR-08'] = self::f($asgOk, $needsAsg
            ? ($asgOk ? 'تكليف ساري المفعول' : 'مسمى قيادي/رقابي بلا تكليف ساري (CEO-Y0121)')
            : 'مسمى لا يحتاج تكليفا');

        /* ⑨ السقفُ المالي — يُفحص حين يحمل الطلبُ مبلغًا. */
        if ($amount === null) { $F['PFACTOR-09'] = self::f(true, 'بلا مبلغ — العامل غير مقيد'); }
        else {
            $cap = $acc ? (float) $acc['review_limit_usd'] : 0.0;
            $ok9 = ($cap > 0 && $amount <= $cap);
            $F['PFACTOR-09'] = self::f($ok9, $ok9
                ? 'المبلغ ' . number_format($amount, 2) . ' ضمن سقفه ' . number_format($cap, 2)
                : 'المبلغ ' . number_format($amount, 2) . ' يتجاوز سقفه ' . number_format($cap, 2));
        }

        /* ⑩ مدةُ السريان — موضعُها `exec_assignments.effective_to` لا عمودٌ في
             بطاقةِ المحاسب: السريانُ صفةُ **التكليف** لا صفةُ الشخص. */
        $validTo = self::assignmentValidTo($conn, $co, $uid, (int) $u['role_id']);
        $ok10 = ($validTo === '' || strtotime($validTo) >= strtotime(date('Y-m-d')));
        $F['PFACTOR-10'] = self::f($ok10, $ok10
            ? ($validTo === '' ? 'سريان مفتوح بلا نهاية' : 'ساري حتى ' . $validTo)
            : 'انتهى سريان تكليفه في ' . $validTo);

        /* ⑪ المنعُ الصريح — **يُحسب أخيرًا وينقض ما قبلَه**. */
        $deny = self::explicitDeny($conn, $co, $u, $action);
        $F['PFACTOR-11'] = self::f($deny === '', $deny === '' ? 'لا منع صريح' : $deny);

        /* الحكم: العشرةُ الموجبةُ **كلُّها** ثم السالبُ ينقض. */
        $positives = 0; $failed = array();
        foreach (self::FACTORS as $code => $meta) {
            if ($meta[3] !== 1) { continue; }
            if (!empty($F[$code]['met'])) { $positives++; } else { $failed[] = $code . ' (' . $meta[1] . ')'; }
        }
        $allowed = ($positives === 10) && ($deny === '');

        return array(
            'allowed' => $allowed, 'code' => $allowed ? 200 : 403,
            'positives' => $positives, 'factors' => $F,
            'denied_by' => $deny !== '' ? 'PFACTOR-11' : ($failed ? $failed[0] : ''),
            'failed' => $failed,
            'reason' => $deny !== ''
                ? 'منع صريح ينقض العوامل العشرة مهما اجتمعت: ' . $deny
                : ($allowed ? 'العوامل العشرة مستوفاة ولا منع صريح'
                            : 'عوامل غير مستوفاة: ' . implode(' · ', array_slice($failed, 0, 4))),
        );
    }

    /** المنعُ الصريح: حدٌّ مُنفَذٌ على دورِه · أو زوجُ فصلِ واجباتٍ حاجب. */
    private static function explicitDeny(\mysqli $conn, $co, array $u, $action)
    {
        $role = (int) $u['role_id'];

        /* ① حدٌّ صريحٌ «لا يملك …» على دورِه، **يُسمّي الفعلَ المطلوبَ نفسَه**.
             ◆ والاشتراطُ على الفعلِ لازم: الحدُّ يمنع فعلًا بعينِه، فقراءتُه
               «هذا الدورُ ممنوعٌ من كلِّ شيء» تُسقط كلَّ محاسبٍ في كلِّ طلب —
               وهذا ما كان يقع قبلَ إضافةِ `action_codes`. */
        if ($action !== '') {
            $st = $conn->prepare("SELECT code, forbidden FROM gov_authority_limits
                                   WHERE active = 1 AND enforce_kind <> 'none'
                                     AND FIND_IN_SET(?, REPLACE(role_ids,' ',''))
                                     AND action_codes <> ''
                                     AND FIND_IN_SET(?, REPLACE(action_codes,' ','')) LIMIT 1");
            if ($st) {
                $r = (string) $role;
                $st->bind_param('ss', $r, $action);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) { return 'حد منفذ ' . $row['code'] . ': ' . mb_substr((string) $row['forbidden'], 0, 90); }
            }
        }

        /* ② زوجُ فصلِ واجباتٍ حاجبٌ يجمعه الشخصُ فعلًا. */
        require_once dirname(dirname(__DIR__)) . '/Services/Exec/AssignmentGate.php';
        $chk = \App\Services\Exec\AssignmentGate::checkConflicts($conn, $co, (int) $u['id'], $role);
        if (empty($chk['clean'])) { return 'تعارض واجبات حاجب: ' . mb_substr((string) $chk['detail'], 0, 90); }

        return '';
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    private static function f($met, $why) { return array('met' => (bool) $met, 'why' => $why); }

    private static function userRow(\mysqli $conn, $uid)
    {
        /* ◆ گوتشا: `users` تحمل الدورَ في عمودين — `role` نصًّا و`role_id` رقمًا،
             وأحدُهما قد يكون صفرًا. فالقراءةُ بالتعويضِ لا بأحدِهما وحدَه. */
        $st = $conn->prepare("SELECT id, company_id, employee_id, position_id, project_id,
                                     CAST(COALESCE(NULLIF(role_id,0), role) AS UNSIGNED) AS role_id,
                                     CASE WHEN status = 'active' AND COALESCE(is_deleted,0) = 0
                                          THEN 1 ELSE 0 END AS is_active
                                FROM users WHERE id = ? LIMIT 1");
        if (!$st) { return null; }
        $uid = (int) $uid;
        $st->bind_param('i', $uid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function accountantRow(\mysqli $conn, $co, array $u)
    {
        $st = $conn->prepare("SELECT spec_code, finance_unit_id, review_limit_usd
                                FROM fin_accountants
                               WHERE company_id = ? AND employee_id = ? AND active = 1
                                 AND COALESCE(is_deleted,0) = 0 LIMIT 1");
        if (!$st) { return null; }
        $co = (int) $co; $emp = (int) $u['employee_id'];
        $st->bind_param('ii', $co, $emp);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function positionName(\mysqli $conn, $posId)
    {
        if ($posId <= 0) { return ''; }
        $st = $conn->prepare("SELECT name FROM positions
                               WHERE id = ? AND is_active = 1 AND COALESCE(is_deleted,0) = 0 LIMIT 1");
        if (!$st) { return ''; }
        $posId = (int) $posId;
        $st->bind_param('i', $posId);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return $row ? (string) $row[0] : '';
    }

    /** نهايةُ سريانِ تكليفِ الشخصِ على دورِه — والفارغُ سريانٌ مفتوح. */
    private static function assignmentValidTo(\mysqli $conn, $co, $uid, $roleId)
    {
        $st = $conn->prepare("SELECT effective_to FROM exec_assignments
                               WHERE company_id = ? AND subject_user_id = ? AND role_id = ?
                                 AND state = 'approved'
                               ORDER BY decided_at DESC LIMIT 1");
        if (!$st) { return ''; }
        $co = (int) $co; $uid = (int) $uid; $roleId = (int) $roleId;
        $st->bind_param('iii', $co, $uid, $roleId);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return ($row && $row[0] !== null && $row[0] !== '0000-00-00') ? (string) $row[0] : '';
    }
}
