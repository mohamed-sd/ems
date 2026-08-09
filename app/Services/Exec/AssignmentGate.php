<?php
/**
 * بوابةُ التكليف — AssignmentGate (PROP-01 §٥ · CEO-Y0121 · CEO-Y0122)
 * ═══════════════════════════════════════════════════════════════════════════
 * تحرس حكمين لا ثالثَ لهما في هذا النطاق:
 *
 *  ① CEO-Y0121 — «◆ ولا يسري تكليفُ شخصٍ على أيِّ مسمًّى قياديٍّ أو رقابيٍّ في
 *     المنصةِ كلِّها قبلَ موافقةِ الرئيسِ الموثَّقة · **والموافقةُ سجلٌّ لا رسالةٌ**
 *     ولا يمنح التكليفُ صلاحيةً واحدةً قبلها.»
 *     فـ`isEffective()` هي البابُ الذي تسأله طبقةُ الصلاحياتِ قبلَ المنح.
 *
 *  ② CEO-Y0122 — «ويُفحص تعارضُ الواجباتِ واستقلالُ الوظيفةِ الرقابيةِ **آليًّا
 *     قبلَ عرضِ طلبِ التكليفِ على الرئيس** — والطلبُ الذي يُنشئ تعارضًا لا يُعرض
 *     حتى يُحسم.»
 *     فالطلبُ المتعارضُ يقف عند `blocked` ولا يبلغ صندوقَ الرئيسِ أصلًا.
 *
 * وحدُّها — CEO-Y0124: «مكتبُ الرئيسِ يقرر ولا ينفّذ». فهذه البوابةُ تُسجِّل
 * القرارَ وتمنع السريان، ولا تكتب صلاحيةً ولا قيدًا.
 */

namespace App\Services\Exec;

class AssignmentGate
{
    /** الرئيسُ التنفيذيُّ — صاحبُ القرارِ حصرًا (roles.id = 9). */
    const ROLE_CEO = 9;

    /** المسمياتُ التي لا تسري بلا موافقة: القياديُّ والرقابي. */
    const NEEDS_APPROVAL = array('leadership', 'oversight');

    /**
     * ◆ الأدوارُ القياديةُ والرقابيةُ في المنصة — ومن كان منها لزمته الموافقة.
     * قياديٌّ: من يقود إدارةً أو يملك سقفًا. رقابيٌّ: من يراجع أو يحكم على غيره.
     */
    const LEADERSHIP_ROLES = array(1, 2, 3, 4, 6, 12, 13, 16, 17, 19, 23, 24, 26, 27, 28, 32);
    const OVERSIGHT_ROLES  = array(15, 20, 22, 29, 30, 31, 33, 34, 35);

    /* ═══════════════════════════════════════════════════════════════════════
       ① السريان — البابُ الذي تسأله الصلاحيات
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * أيسري تكليفُ هذا الشخصِ على هذا المسمّى؟
     * fail-closed: المسمّى القياديُّ أو الرقابيُّ **بلا موافقةٍ سارية** = لا.
     * وما ليس قياديًّا ولا رقابيًّا لا تلزمه موافقةٌ فيمرُّ.
     */
    public static function isEffective(\mysqli $conn, $co, $userId, $roleId)
    {
        $kind = self::kindOfRole($roleId);
        if (!in_array($kind, self::NEEDS_APPROVAL, true)) {
            return array('ok' => true, 'code' => 200, 'reason' => 'مسمًّى لا يحتاج موافقةَ الرئيس', 'kind' => $kind);
        }
        $st = $conn->prepare(
            "SELECT assignment_no, decided_at, authority_ref FROM exec_assignments
              WHERE company_id = ? AND subject_user_id = ? AND role_id = ?
                AND state = 'approved'
                AND (effective_from IS NULL OR effective_from <= CURDATE())
                AND (effective_to   IS NULL OR effective_to   >= CURDATE())
              ORDER BY decided_at DESC LIMIT 1");
        if (!$st) { return self::fail(500, 'تعذّر فحصُ سريانِ التكليف'); }
        $co = (int) $co; $userId = (int) $userId; $roleId = (int) $roleId;
        $st->bind_param('iii', $co, $userId, $roleId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return self::fail(403, 'تكليفٌ بلا موافقةِ الرئيسِ لا يمنح صلاحيةً واحدة (CEO-Y0121)');
        }
        return array('ok' => true, 'code' => 200, 'kind' => $kind,
                     'reason' => 'سارٍ بموافقةِ الرئيس ' . $row['assignment_no'],
                     'assignment_no' => $row['assignment_no'], 'decided_at' => $row['decided_at']);
    }

    /** نوعُ المسمّى: قياديٌّ أو رقابيٌّ أو غيرُهما. */
    public static function kindOfRole($roleId)
    {
        $roleId = (int) $roleId;
        if (in_array($roleId, self::OVERSIGHT_ROLES, true))  { return 'oversight'; }
        if (in_array($roleId, self::LEADERSHIP_ROLES, true)) { return 'leadership'; }
        return 'other';
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ② الطلب — والفحصُ الآليُّ قبلَ العرض
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يطلب تكليفًا. يُفحص التعارضُ آليًّا فورًا:
     *   نظيفٌ  → presented (يبلغ صندوقَ الرئيس)
     *   متعارضٌ → blocked   (لا يُعرض حتى يُحسم — CEO-Y0122)
     */
    public static function request(\mysqli $conn, array $ctx)
    {
        $co   = intval($ctx['company_id'] ?? 0);
        $subj = intval($ctx['subject_user_id'] ?? 0);
        $role = intval($ctx['role_id'] ?? 0);
        $by   = intval($ctx['requested_by'] ?? 0);
        if ($co <= 0 || $subj <= 0 || $role <= 0 || $by <= 0) {
            return self::fail(422, 'طلبُ التكليفِ يحتاج الكيانَ والمكلَّفَ والمسمّى وطالبَه');
        }

        $kind = self::kindOfRole($role);
        $chk  = self::checkConflicts($conn, $co, $subj, $role);
        $state = $chk['clean'] ? 'presented' : 'blocked';

        $no = (string) ($ctx['assignment_no'] ?? ('ASG-' . $co . '-' . $subj . '-' . $role . '-' . date('ymdHis')));
        $sql = "INSERT INTO exec_assignments
                  (company_id, assignment_no, subject_user_id, subject_name, role_id, role_name,
                   assignment_kind, scope_note, requested_by, requested_at,
                   conflict_state, conflict_detail, checked_at, state, effective_from, effective_to)
                VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,NOW(),?,?,?)";
        $st = $conn->prepare($sql);
        if (!$st) { return self::fail(500, 'تعذّر تسجيلُ الطلب: ' . $conn->error); }
        $sname = mb_substr((string) ($ctx['subject_name'] ?? self::userName($conn, $subj)), 0, 160);
        $rname = mb_substr((string) ($ctx['role_name'] ?? self::roleName($conn, $role)), 0, 120);
        $scope = mb_substr((string) ($ctx['scope_note'] ?? ''), 0, 300);
        $cs    = $chk['clean'] ? 'clean' : 'conflict';
        $cd    = mb_substr($chk['detail'], 0, 600);
        $from  = isset($ctx['effective_from']) && $ctx['effective_from'] !== '' ? (string) $ctx['effective_from'] : null;
        $to    = isset($ctx['effective_to'])   && $ctx['effective_to']   !== '' ? (string) $ctx['effective_to']   : null;
        $vals  = array($co, $no, $subj, $sname, $role, $rname, $kind, $scope, $by, $cs, $cd, $state, $from, $to);
        $types = 'i' . 'ss' . 'i' . 'ss' . 'ss' . 'i' . 'ss' . 'sss';
        if (strlen($types) !== count($vals)) {
            return self::fail(500, sprintf('انزياحُ وسائط: أنواع %d · قيم %d', strlen($types), count($vals)));
        }
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) {
            $e = $st->errno; $m = $st->error; $st->close();
            return self::fail($e === 1062 ? 409 : 500, 'تعذّر تسجيلُ الطلب: ' . $m);
        }
        $id = $st->insert_id;
        $st->close();

        return array('ok' => true, 'code' => 200, 'id' => $id, 'assignment_no' => $no,
                     'state' => $state, 'kind' => $kind, 'conflict' => !$chk['clean'],
                     'reason' => $chk['clean']
                        ? 'الطلبُ نظيفٌ وعُرض على الرئيس'
                        : 'طلبٌ يُنشئ تعارضًا فلا يُعرض حتى يُحسم (CEO-Y0122): ' . $chk['detail']);
    }

    /**
     * فحصُ تعارضِ الواجباتِ واستقلالِ الوظيفةِ الرقابية.
     * يقرأ الأزواجَ من `sec_sod_pairs` بيانًا لا كودًا — فتغييرُ المصفوفةِ
     * لا يحتاج مسَّ هذا الملف.
     */
    public static function checkConflicts(\mysqli $conn, $co, $userId, $newRoleId)
    {
        $held = self::rolesHeldBy($conn, $co, $userId);
        $all  = array_values(array_unique(array_merge($held, array((int) $newRoleId))));
        $hits = array();

        $r = $conn->query("SELECT code, func_a, func_b, roles_a, roles_b, why, severity
                             FROM sec_sod_pairs WHERE active = 1 AND severity = 'block'");
        while ($r && $p = $r->fetch_assoc()) {
            $A = self::csvInts($p['roles_a']);
            $B = self::csvInts($p['roles_b']);
            if (!$A || !$B) { continue; }
            $inA = array_intersect($all, $A);
            $inB = array_intersect($all, $B);
            if (!$inA || !$inB) { continue; }
            /* الزوجُ ينطبق فقط إن كان المسمّى الجديدُ طرفًا فيه — وإلا فالتعارضُ
               قائمٌ قبلَ هذا الطلبِ ولا يُنسب إليه. */
            if (!in_array((int) $newRoleId, $A, true) && !in_array((int) $newRoleId, $B, true)) { continue; }
            $hits[] = $p['code'] . ' « ' . $p['func_a'] . ' » مع « ' . $p['func_b'] . ' »';
        }

        /* IAF-0006 · FACC-0070: استقلالُ المراجعِ الداخليِّ شرطٌ لا زوجٌ —
           فلا يُجمع مع أيِّ دورٍ تنفيذيٍّ أو اعتماديٍّ مهما كان. */
        $auditor = 33;
        if (in_array($auditor, $all, true) && count($all) > 1) {
            $hits[] = 'IAF-0006 المراجعُ الداخليُّ لا يُجمع مع أيِّ دورٍ تنفيذيٍّ أو اعتماديّ';
        }

        return array('clean' => !$hits, 'detail' => implode(' · ', $hits), 'roles' => $all);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ③ القرار — الرئيسُ حصرًا
       ═══════════════════════════════════════════════════════════════════════ */

    /** موافقةُ الرئيسِ — ولا تقع على طلبٍ محجوبٍ بتعارض. */
    public static function decide(\mysqli $conn, array $ctx)
    {
        $co  = intval($ctx['company_id'] ?? 0);
        $no  = trim((string) ($ctx['assignment_no'] ?? ''));
        $by  = intval($ctx['decided_by'] ?? 0);
        $dec = (string) ($ctx['decision'] ?? '');
        if ($co <= 0 || $no === '' || $by <= 0 || !in_array($dec, array('approved', 'rejected'), true)) {
            return self::fail(422, 'القرارُ يحتاج الكيانَ ورقمَ التكليفِ وقرارًا وفاعلًا');
        }
        /* CEO-Y0121: «الرئيسُ حصرًا» — ولا نائبَ ولا مديرَ ماليّ. */
        if (self::roleOfUser($conn, $by) !== self::ROLE_CEO) {
            return self::fail(403, 'موافقةُ التكليفِ للرئيسِ التنفيذيِّ حصرًا (CEO-Y0121)');
        }

        $row = self::fetch($conn, $co, $no);
        if ($row === null) { return self::fail(404, 'تكليفٌ غيرُ موجود: ' . $no); }
        if ($row['state'] === 'blocked' || $row['conflict_state'] === 'conflict') {
            return self::fail(409, 'طلبٌ يُنشئ تعارضًا لا يُعرض ولا يُقرَّر حتى يُحسم (CEO-Y0122)');
        }
        if (in_array($row['state'], array('approved', 'rejected'), true)) {
            return self::fail(409, 'التكليفُ مقرَّرٌ سلفًا: ' . $row['state']);
        }

        $st = $conn->prepare(
            "UPDATE exec_assignments
                SET state = ?, decided_by = ?, decided_at = NOW(),
                    decision_reason = ?, authority_ref = ?
              WHERE company_id = ? AND assignment_no = ? AND state IN ('presented','draft')");
        if (!$st) { return self::fail(500, 'تعذّر حفظُ القرار'); }
        $reason = mb_substr((string) ($ctx['decision_reason'] ?? ''), 0, 400);
        $auth   = mb_substr((string) ($ctx['authority_ref'] ?? ('قرارُ الرئيسِ ' . date('Y-m-d'))), 0, 120);
        $st->bind_param('sissis', $dec, $by, $reason, $auth, $co, $no);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        if ($n < 1) { return self::fail(409, 'لم يتغير شيءٌ — راجع حالةَ التكليف'); }

        return array('ok' => true, 'code' => 200,
                     'reason' => $dec === 'approved' ? 'سرى التكليفُ بموافقةِ الرئيس' : 'رُفض التكليف');
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    /** الأدوارُ التي يحملها الشخصُ فعلًا: دورُه القائمُ + تكليفاتُه السارية. */
    public static function rolesHeldBy(\mysqli $conn, $co, $userId)
    {
        $out = array();
        $r = self::roleOfUser($conn, $userId);
        if ($r > 0) { $out[] = $r; }
        $st = $conn->prepare(
            "SELECT DISTINCT role_id FROM exec_assignments
              WHERE company_id = ? AND subject_user_id = ? AND state = 'approved'
                AND (effective_to IS NULL OR effective_to >= CURDATE())");
        if ($st) {
            $co = (int) $co; $userId = (int) $userId;
            $st->bind_param('ii', $co, $userId);
            $st->execute();
            $rs = $st->get_result();
            while ($x = $rs->fetch_assoc()) { $out[] = (int) $x['role_id']; }
            $st->close();
        }
        return array_values(array_unique($out));
    }

    /**
     * دورُ المستخدم. ◆ گوتشا: `users` تحمل الدورَ في عمودين — `role` نصًّا
     *   (وهو ما تقرؤه المصادقة) و`role_id` رقمًا مملوءًا جزئيًّا.
     */
    public static function roleOfUser(\mysqli $conn, $userId)
    {
        $st = $conn->prepare("SELECT COALESCE(NULLIF(role_id,0), CAST(NULLIF(role,'') AS UNSIGNED)) rid
                                FROM users WHERE id = ? LIMIT 1");
        if (!$st) { return 0; }
        $userId = (int) $userId;
        $st->bind_param('i', $userId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['rid']) : 0;
    }

    private static function fetch(\mysqli $conn, $co, $no)
    {
        $st = $conn->prepare("SELECT * FROM exec_assignments
                               WHERE company_id = ? AND assignment_no = ? LIMIT 1");
        if (!$st) { return null; }
        $co = (int) $co;
        $st->bind_param('is', $co, $no);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function userName(\mysqli $conn, $id)
    {
        $st = $conn->prepare("SELECT COALESCE(NULLIF(name,''), username) n FROM users WHERE id = ? LIMIT 1");
        if (!$st) { return ''; }
        $id = (int) $id;
        $st->bind_param('i', $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? (string) $r['n'] : '';
    }

    private static function roleName(\mysqli $conn, $id)
    {
        $st = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
        if (!$st) { return ''; }
        $id = (int) $id;
        $st->bind_param('i', $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? (string) $r['name'] : '';
    }

    private static function csvInts($s)
    {
        $out = array();
        foreach (explode(',', (string) $s) as $p) {
            $p = trim($p);
            if ($p !== '' && ctype_digit($p)) { $out[] = (int) $p; }
        }
        return $out;
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }
}
