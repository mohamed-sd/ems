<?php
/**
 * محرّكُ التوجيهِ المالي — RoutingEngine (FIN-OBL-01 §٤-١٥ · §٤-١ · §٤-٢ · §٤-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * يجيب سؤالًا واحدًا: **إلى من يذهب هذا الطلب؟** — والجوابُ من المصفوفةِ لا من
 * المُطلِق.
 *
 *  - OBL-0001  كلُّ واقعةٍ ذاتِ أثرٍ ماليٍّ تُوجَّه آليًّا إلى محاسبِ تخصصِها
 *              بمصفوفةٍ معتمدة · ولا يختار المُطلِقُ إلى من يذهب طلبُه ·
 *              ولا تصل الخزينةُ قبلَ مرورِها بمحاسبِ التخصصِ ورئيسِ الحسابات.
 *  - OBL-0002  التوجيهُ يقع **بحدثٍ منشورٍ لا بنداءٍ مباشر**: الإدارةُ المصدرُ
 *              تنشر والماليةُ تستهلك — فلا نداءَ مباشرٌ من إدارةٍ إلى جدولٍ ماليّ.
 *  - OBL-0003  الطلبُ الموجَّهُ يظهر في مهامِّ محاسبِ التخصصِ فورًا بمهلة —
 *              ولا يبقى في إدارتِه المصدرِ منتظرًا.
 *  - OBL-0020  RT-17 «الحكمُ الجامع»: أيُّ واقعةٍ ذاتِ أثرٍ ماليٍّ بلا مسارٍ
 *              خاصٍّ تُوجَّه بالتخصصِ المسنَدِ للإدارةِ ونوعِ الواقعة.
 *  - OBL-0307  والحدثُ ذو الأثرِ الماليِّ الذي لا مُطلِقَ له **ثغرةٌ تُسجَّل
 *              عيبًا لا تُهمَل** — فـ`route()` تُرجع سببًا لا صمتًا.
 *
 * والمرتجَع (§٤-٢ · §٤-٣):
 *  - BR-01  لكلِّ ما يُرسَل مرتجَعٌ مقابلٌ إلى مصدرِه — والانتظارُ الصامتُ
 *           أسوأُ من الرفض.
 *  - BR-02  المرتجَعُ يظهر في **مهامِّ** المستلِمِ لا في بريدٍ منفصل.
 *  - BR-03  سببُ الرفضِ **برمزٍ محكومٍ** من `fin_reason_codes` لا بنصٍّ حر.
 *  - BR-04  المرتجَعُ يحمل مرجعَ الطلبِ الأصليِّ ولا ينشأ منفصلًا.
 *  - BR-05  المرتجَعُ الجماعيُّ يُوجَّه لمالكِه لا للإداراتِ كلِّها.
 *  - BR-06  ولا يُلغى مرتجَعٌ بإلغاءِ الطلبِ — بل يُغلق بسببِ الإلغاء.
 *
 * ◆ الحدُّ: هذا المحرّكُ **لا يعتمد ولا يقيّد ولا يدفع** — يوجّه ويُشهد فقط.
 *   والاعتمادُ في أنواعِه الأربعة (APR-1..4) والقيدُ عند شرطِ معيارِه.
 */

namespace App\Services\Finance;

use App\Services\Work\WorkItemService;

class RoutingEngine
{
    /** مهلةُ ظهورِ الطلبِ في مهامِّ المحاسبِ (ساعات) — OBL-0003 «فورًا بمهلة». */
    const TASK_SLA_HOURS = 24;

    /** أولويةُ مهمةِ المراجعةِ المحاسبية (WorkItemService::PRIORITY_SLA). */
    const TASK_PRIORITY = 'P2';

    /** مصدرُ عنصرِ العمل — أحدُ المصادرِ الأربعةَ عشرَ المعرَّفة. */
    const WORK_SOURCE = 'SRC-07';

    /** رئيسُ الحسابات — وجهةُ التصعيدِ حين لا حاملَ للتخصص (includes/roles.php). */
    const ROLE_CHIEF_ACCOUNTANT = 31;

    /* ═══════════════════════════════════════════════════════════════════════
       التوجيه
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يوجّه واقعةً ماليةً إلى محاسبِ تخصصِها.
     *
     * @param \mysqli $conn
     * @param array   $ctx  المطلوب: company_id · source_kind · source_ref
     *                      واختياري: trigger_key أو route_code · source_dept ·
     *                      title · event_ref · created_by · org_unit_id ·
     *                      project_id · site_id · owner_user_id
     * @return array{ok:bool, code:int, reason:string, route?:array,
     *               accountant_id?:int, work_item_id?:int, log_id?:int}
     */
    public static function route(\mysqli $conn, array $ctx)
    {
        $co  = intval($ctx['company_id'] ?? 0);
        $ref = trim((string) ($ctx['source_ref'] ?? ''));
        $kind = trim((string) ($ctx['source_kind'] ?? ''));
        if ($co <= 0 || $ref === '' || $kind === '') {
            return self::fail(422, 'التوجيه يحتاج الكيان ونوع المستند ومرجعه');
        }

        /* ① المسار: بالرمزِ الصريحِ أو بمفتاحِ الحدثِ — ثم الاحتياطيةُ RT-17. */
        $route = self::resolveRoute($conn, $ctx);
        if ($route === null) {
            /* OBL-0307: الثغرةُ تُسجَّل عيبًا لا تُهمَل. */
            return self::fail(404, 'لا مطلق معرف لهذه الواقعة — ثغرة تسجل عيبا (OBL-0307)');
        }

        /* ② التخصص: من المسار · أو بالإدارةِ ونوعِ الواقعةِ في الاحتياطية. */
        $spec = $route['target_spec'];
        $via  = 'matrix';
        if ($route['kind'] === 'fallback' || $spec === '') {
            $spec = self::specForDepartment($conn, $co, (string) ($ctx['source_dept'] ?? $route['source_dept']));
            $via  = 'fallback';
            if ($spec === '') {
                return self::fail(404, 'الحكم الجامع لا يجد تخصصا مسندا لهذه الإدارة (RT-17)');
            }
        }

        /* ③ المحاسبُ المسنَدُ للتخصصِ في هذا الكيان.
           ◆ وإن لم يكن للتخصصِ حاملٌ يمكن بلوغُه — تُصعَّد الواقعةُ إلى رئيسِ
             الحسابات ولا تسقط: BR-01 «الانتظارُ الصامتُ أسوأُ من الرفض»،
             وسلسلةُ كلِّ مسارٍ تمرُّ برئيسِ الحساباتِ أصلًا فليس هذا اختراعَ
             وجهةٍ بل بلوغُ الحلقةِ التاليةِ في السلسلةِ نفسِها. */
        $accountantId = self::accountantFor($conn, $co, $spec);
        $escalated    = false;
        $toRoleId     = null;
        if ($accountantId <= 0) {
            /* أولًا: شخصٌ يحمل رئاسةَ الحسابات في هذا الكيان. */
            $accountantId = self::firstUserWithRole($conn, $co, self::ROLE_CHIEF_ACCOUNTANT);
            $escalated = true; $via = 'escalated';
            if ($accountantId <= 0) {
                /* وإلا فإلى **الدور** نفسِه: `work_items` تقبل مستلِمًا دورًا لا
                   شخصًا — وهو الأصحُّ للوثيقةِ التي تتكلم عن مواضعَ لا أشخاص.
                   فمن يحمل الدورَ يلتقطها، ولا تسقط الواقعةُ لغيابِ تعيينٍ فردي. */
                $toRoleId = self::ROLE_CHIEF_ACCOUNTANT;
            }
        }

        /* ◆ العطالة قبل التوليد: `fin_routing_log` عليه مفتاحٌ فريدٌ فيُحدَّث ولا
             يتكرر — أما **المهمةُ فتتكرر** لو وُلِّدت بلا فحص. وإعادةُ استهلاكِ
             حدثٍ (مصالِحٌ · إعادةُ تشغيلِ كرون · محاولةٌ ثانيةٌ بعد فشل) كانت
             تُغرِق مساحةَ عملِ المحاسبِ بنسخٍ من الطلبِ نفسِه.
             فالمهمةُ القائمةُ لهذا المستندِ والمسارِ تُعاد ولا تُخلق ثانيةً. */
        $priorWi = self::existingWorkItem($conn, $co, $kind, $ref, $route['code']);
        if ($priorWi > 0) {
            return array('ok' => true, 'code' => 200, 'reason' => 'موجه سلفا — أعيدت مهمته ولم تكرر',
                'route' => $route, 'spec' => $spec, 'escalated' => $escalated,
                'accountant_id' => $accountantId, 'to_role_id' => $toRoleId,
                'work_item_id' => $priorWi, 'log_id' => self::logIdFor($conn, $co, $kind, $ref, $route['code']),
                'duplicate' => true);
        }

        /* ④ المهمة: OBL-0003 — تظهر في مهامِّه فورًا بمهلة، لا في بريد.
           المالكُ حين لا مستلِمَ معيَّنًا هو مُطلِقُ الطلب: فالإدارةُ المصدرُ تملك
           طلبَها حتى تتسلمه الماليةُ — ولا يُترك بلا مالك (WF-02). */
        $workItemId = null;
        $ownerId    = intval($ctx['owner_user_id'] ?? 0);
        if ($ownerId <= 0) { $ownerId = $accountantId > 0 ? $accountantId : intval($ctx['created_by'] ?? 0); }
        if (($accountantId > 0 || $toRoleId !== null) && $ownerId > 0) {
            $title = (string) ($ctx['title'] ?? ('مراجعة محاسبية: ' . $route['trigger_ar']));
            $res = WorkItemService::create($conn, array(
                'company_id'       => $co,
                'item_type'        => 'task',
                'title'            => mb_substr($title, 0, 300),
                'details'          => 'المسار ' . $route['code'] . ' · التخصص ' . $spec
                                    . ' · شرط الإطلاق: ' . $route['launch_cond']
                                    . ' · السلسلة: ' . $route['chain'],
                'source_type'      => self::WORK_SOURCE,
                'source_ref'       => $kind . ':' . $ref,
                'source_screen'    => (string) ($ctx['source_screen'] ?? ''),
                'action_code'      => $route['trigger_key'],
                'event_ref'        => (string) ($ctx['event_ref'] ?? ''),
                'org_unit_id'      => $ctx['org_unit_id'] ?? null,
                'project_id'       => $ctx['project_id'] ?? null,
                'site_id'          => $ctx['site_id'] ?? null,
                'assigned_user_id' => $accountantId > 0 ? $accountantId : null,
                'assigned_role_id' => $toRoleId,
                'owner_user_id'    => $ownerId,
                'due_at'           => date('Y-m-d H:i:s', time() + self::TASK_SLA_HOURS * 3600),
                'deliverable'      => 'مراجعة مستندية ومحاسبية وموازنية ثم رفع لرئيس الحسابات',
                'evidence_required' => 'قرار المحاسب في سجل المعاملة ومرتجع إلى مصدرها',
                'priority'         => self::TASK_PRIORITY,
                'created_by'       => intval($ctx['created_by'] ?? 0),
                'created_capacity' => 'محرك التوجيه المالي',
            ));
            if (!empty($res['ok'])) { $workItemId = intval($res['id'] ?? 0) ?: null; }
        }

        /* ⑤ الشهادة: الصفُّ في سجلِّ التوجيهِ هو دليلُ عدمِ التخطي. */
        $logId = self::writeLog($conn, $co, $route, $spec, $accountantId, $workItemId, $via, $ctx);

        if ($workItemId === null) {
            /* لا مهمةَ تولَّدت — فالواقعةُ بلا مستلِم. ولا يُبتلع هذا صمتًا:
               يُرجَع خللًا بشاهدٍ مكتوبٍ في سجلِّ التوجيه (BR-01). */
            return array('ok' => false, 'code' => 409,
                'reason' => 'التخصص ' . $spec . ' لم تبلغه الواقعة ولا بلغت رئاسة الحسابات — '
                          . 'ولا يترك الطلب صامتا (BR-01)',
                'route' => $route, 'spec' => $spec, 'log_id' => $logId);
        }

        return array(
            'ok' => true, 'code' => 200,
            'reason' => $escalated
                ? 'التخصص ' . $spec . ' بلا حامل — فصعد إلى رئاسة الحسابات'
                : 'وجه إلى ' . $spec . ' وظهر في مهام محاسبه',
            'route' => $route, 'spec' => $spec, 'escalated' => $escalated,
            'accountant_id' => $accountantId, 'to_role_id' => $toRoleId,
            'work_item_id' => $workItemId, 'log_id' => $logId,
        );
    }

    /** يحلّ المسار: بالرمزِ الصريحِ · فبمفتاحِ الحدث · فبالاحتياطيةِ RT-17. */
    public static function resolveRoute(\mysqli $conn, array $ctx)
    {
        $code = trim((string) ($ctx['route_code'] ?? ''));
        $key  = trim((string) ($ctx['trigger_key'] ?? ''));

        if ($code !== '') {
            $r = self::fetchRoute($conn, 'code = ?', 's', array($code));
            if ($r) { return $r; }
        }
        if ($key !== '') {
            $r = self::fetchRoute($conn, 'trigger_key = ?', 's', array($key));
            if ($r) { return $r; }
        }
        /* RT-17 — الحكمُ الجامع. */
        return self::fetchRoute($conn, "kind = 'fallback'", '', array());
    }

    private static function fetchRoute(\mysqli $conn, $where, $types, $vals)
    {
        $sql = "SELECT code, kind, trigger_ar, trigger_key, source_dept, launch_cond,
                       target_spec, target_label, accounts, dims, chain, guard_rule, doc_ref
                  FROM fin_routing_matrix
                 WHERE active = 1 AND ($where)
                 ORDER BY (kind = 'route') DESC LIMIT 1";
        $st = $conn->prepare($sql);
        if (!$st) { return null; }
        if ($types !== '') { $st->bind_param($types, ...$vals); }
        if (!$st->execute()) { $st->close(); return null; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * التخصصُ المسنَدُ لإدارةٍ — يُستعمل في الحكمِ الجامعِ وحدَه.
     * يُشتقُّ من المصفوفةِ نفسِها: أكثرُ تخصصٍ تُوجِّه إليه مساراتُ هذه الإدارة.
     * ◆ فلا جدولَ إسنادٍ موازٍ يُخترع — المصفوفةُ مصدرُ الحقيقةِ الوحيد.
     */
    public static function specForDepartment(\mysqli $conn, $co, $dept)
    {
        $dept = trim((string) $dept);
        if ($dept === '') { return ''; }
        $st = $conn->prepare(
            "SELECT target_spec, COUNT(*) c
               FROM fin_routing_matrix
              WHERE active = 1 AND kind = 'route' AND target_spec <> ''
                AND (source_dept = ? OR source_dept LIKE CONCAT('%', ?, '%'))
              GROUP BY target_spec ORDER BY c DESC, target_spec ASC LIMIT 1");
        if (!$st) { return ''; }
        $st->bind_param('ss', $dept, $dept);
        if (!$st->execute()) { $st->close(); return ''; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        /* ما لا إدارةَ له من الوقائعِ العامةِ يقع على التسويات — ACC-10 نطاقُها
           «التسوياتُ والمعلقاتُ والحساباتُ العامة» وهي مصبُّ ما لا تخصصَ له. */
        return $row ? (string) $row['target_spec'] : 'ACC-10';
    }

    /** محاسبُ التخصصِ في هذا الكيان — users.id أو 0 إن لم يُسنَد بعد. */
    public static function accountantFor(\mysqli $conn, $co, $spec)
    {
        $st = $conn->prepare(
            "SELECT u.id
               FROM fin_accountants a
               JOIN users u ON u.employee_id = a.employee_id
              WHERE a.company_id = ? AND a.spec_code = ? AND a.active = 1
                AND (a.is_deleted IS NULL OR a.is_deleted = 0)
              ORDER BY a.id LIMIT 1");
        if (!$st) { return 0; }
        $st->bind_param('is', $co, $spec);
        if (!$st->execute()) { $st->close(); return 0; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['id']) : 0;
    }

    /** المهمةُ المولَّدةُ سلفًا لهذا المستندِ والمسار — أو 0 إن لم تُولَّد بعد. */
    private static function existingWorkItem(\mysqli $conn, $co, $kind, $ref, $routeCode)
    {
        $st = $conn->prepare(
            "SELECT l.work_item_id FROM fin_routing_log l
               JOIN work_items w ON w.id = l.work_item_id
              WHERE l.company_id = ? AND l.source_kind = ? AND l.source_ref = ?
                AND l.route_code = ? AND l.work_item_id IS NOT NULL
              LIMIT 1");
        if (!$st) { return 0; }
        $co = (int) $co;
        $st->bind_param('isss', $co, $kind, $ref, $routeCode);
        if (!$st->execute()) { $st->close(); return 0; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['work_item_id']) : 0;
    }

    /** معرّفُ صفِّ سجلِّ التوجيهِ القائمِ لهذا المستندِ والمسار. */
    private static function logIdFor(\mysqli $conn, $co, $kind, $ref, $routeCode)
    {
        $st = $conn->prepare("SELECT id FROM fin_routing_log
                               WHERE company_id = ? AND source_kind = ? AND source_ref = ?
                                 AND route_code = ? LIMIT 1");
        if (!$st) { return 0; }
        $co = (int) $co;
        $st->bind_param('isss', $co, $kind, $ref, $routeCode);
        if (!$st->execute()) { $st->close(); return 0; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['id']) : 0;
    }

    /**
     * أولُ مستخدمٍ حيٍّ يحمل دورًا في هذا الكيان — وجهةُ التصعيد.
     * ◆ گوتشا: الدورُ في `users` محفوظٌ في عمودين: `role` نصًّا (وهو ما تقرؤه
     *   المصادقةُ في `company/auth.php`) و`role_id` رقمًا (مملوءٌ جزئيًّا).
     *   فالمطابقةُ على أحدِهما وحدَه تُسقط حاملينَ حقيقيين.
     */
    public static function firstUserWithRole(\mysqli $conn, $co, $roleId)
    {
        $st = $conn->prepare(
            "SELECT id FROM users
              WHERE company_id = ? AND (role_id = ? OR role = ?)
                AND (is_deleted IS NULL OR is_deleted = 0) AND status = 'active'
              ORDER BY id LIMIT 1");
        if (!$st) { return 0; }
        $rs = (string) $roleId;
        $st->bind_param('iis', $co, $roleId, $rs);
        if (!$st->execute()) { $st->close(); return 0; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['id']) : 0;
    }

    private static function writeLog(\mysqli $conn, $co, array $route, $spec, $accId, $wiId, $via, array $ctx)
    {
        $sql = "INSERT INTO fin_routing_log
                  (company_id, route_code, trigger_key, source_kind, source_ref, source_dept,
                   target_spec, accountant_id, work_item_id, event_ref, resolved_by, manual_reason,
                   routed_at, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)
                ON DUPLICATE KEY UPDATE target_spec = VALUES(target_spec),
                  accountant_id = VALUES(accountant_id), work_item_id = VALUES(work_item_id),
                  resolved_by = VALUES(resolved_by), routed_at = NOW()";
        $st = $conn->prepare($sql);
        if (!$st) { return 0; }
        $rc   = $route['code'];
        $tk   = $route['trigger_key'];
        $sk   = (string) $ctx['source_kind'];
        $sr   = (string) $ctx['source_ref'];
        $sd   = mb_substr((string) ($ctx['source_dept'] ?? $route['source_dept']), 0, 160);
        $acc  = $accId > 0 ? $accId : null;
        $wi   = $wiId > 0 ? $wiId : null;
        $ev   = (string) ($ctx['event_ref'] ?? '');
        $mr   = mb_substr((string) ($ctx['manual_reason'] ?? ''), 0, 300);
        $by   = intval($ctx['created_by'] ?? 0);
        /* ◆ 13 عمودًا — والأنواعُ تُبنى مقطعًا مقطعًا لا سلسلةً تُعدُّ بالعين:
             انزياحُ حرفٍ واحدٍ هنا يكتب المرجعَ في خانةِ الرقمِ صامتًا. */
        $vals  = array($co, $rc, $tk, $sk, $sr, $sd, $spec, $acc, $wi, $ev, $via, $mr, $by);
        $types = 'i' . str_repeat('s', 6) . 'ii' . 'sss' . 'i';
        self::assertArity($types, $vals, 'fin_routing_log');
        $st->bind_param($types, ...$vals);
        $ok = $st->execute();
        $id = $ok ? $st->insert_id : 0;
        $st->close();
        return $id;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       المرتجَع — BR-01..BR-06
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * يُطلق مرتجَعًا إلى مصدرِ الطلب.
     *
     * @param array $ctx المطلوب: company_id · notice_code · source_kind · source_ref
     *                   وحين يكون المرتجَعُ رفضًا: reason_code من `fin_reason_codes`
     *                   واختياري: to_user_id · to_role_id · to_label · reason_note ·
     *                   source_stage · link · created_by · org_unit_id
     */
    public static function backflow(\mysqli $conn, array $ctx)
    {
        $co   = intval($ctx['company_id'] ?? 0);
        $code = trim((string) ($ctx['notice_code'] ?? ''));
        $kind = trim((string) ($ctx['source_kind'] ?? ''));
        $ref  = trim((string) ($ctx['source_ref'] ?? ''));
        if ($co <= 0 || $code === '' || $kind === '' || $ref === '') {
            /* BR-04: المرتجَعُ يحمل مرجعَ الطلبِ الأصليِّ ولا ينشأ منفصلًا. */
            return self::fail(422, 'المرتجع لا ينشأ بلا مرجع طلبه الأصلي (BR-04)');
        }

        $notice = self::fetchNotice($conn, $code);
        if ($notice === null) { return self::fail(404, "مرتجع غير معرف: $code"); }

        /* BR-03: الرفضُ برمزِ سببٍ محكومٍ لا بنصٍّ حر. */
        $reason = trim((string) ($ctx['reason_code'] ?? ''));
        if (self::noticeNeedsReason($code)) {
            if ($reason === '') {
                return self::fail(422, 'رفض بلا رمز سبب محكوم — والنص الحر لا يصنف ولا يقاس (BR-03)');
            }
            if (!self::reasonExists($conn, $reason)) {
                /* ◆ گوتشا: `«$reason»` — المحرفُ `»` يُبتلع في اسمِ المتغيّرِ
                     فيصير `$reason»` غيرَ معرَّف. القوسان المعقوفان إلزامٌ هنا. */
                return self::fail(422, "رمز السبب «{$reason}» غير معرف في القاموس المحكوم (BR-03)");
            }
        }

        $toUser  = intval($ctx['to_user_id'] ?? 0);
        $toRole  = intval($ctx['to_role_id'] ?? 0);
        $toLabel = mb_substr((string) ($ctx['to_label'] ?? $notice['destination']), 0, 200);

        /* BR-02: ما يستوجب فعلًا يولّد مهمةً بمهلةٍ ومسؤول. */
        $wiId = null;
        if ($notice['needs_action'] && $toUser > 0) {
            $res = WorkItemService::create($conn, array(
                'company_id'       => $co,
                'item_type'        => 'task',
                'title'            => mb_substr($notice['title'] . ' — ' . $ref, 0, 300),
                'details'          => $notice['rule_text'] . ($reason !== '' ? ' · السبب: ' . $reason : '')
                                    . ($ctx['reason_note'] ?? '' ? ' · ' . $ctx['reason_note'] : ''),
                'source_type'      => self::WORK_SOURCE,
                'source_ref'       => $kind . ':' . $ref,
                'action_code'      => 'fin.backflow.' . strtolower(str_replace('-', '', $code)),
                'org_unit_id'      => $ctx['org_unit_id'] ?? null,
                'project_id'       => $ctx['project_id'] ?? null,
                'site_id'          => $ctx['site_id'] ?? null,
                'assigned_user_id' => $toUser,
                'owner_user_id'    => $toUser,
                'due_at'           => date('Y-m-d H:i:s', time() + self::TASK_SLA_HOURS * 3600),
                'deliverable'      => 'تصحيح ما نبه عليه وإعادة الطلب إلى دورته',
                'evidence_required' => 'أثر التصحيح في سجل الطلب الأصلي',
                'priority'         => self::TASK_PRIORITY,
                'created_by'       => intval($ctx['created_by'] ?? 0),
                'created_capacity' => 'مرتجع المالية إلى الإدارات',
            ));
            if (!empty($res['ok'])) { $wiId = intval($res['id'] ?? 0) ?: null; }
        } elseif ($toUser > 0) {
            WorkItemService::notifyUser($conn, $co, $toUser, $notice['title'],
                $notice['rule_text'], (string) ($ctx['link'] ?? ''), 0, intval($ctx['created_by'] ?? 0));
        }

        $sql = "INSERT INTO fin_backflow_log
                  (company_id, notice_code, source_kind, source_ref, source_stage, to_user_id,
                   to_role_id, to_label, reason_code, reason_note, work_item_id, state, fired_at, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'open',NOW(),?)";
        $st = $conn->prepare($sql);
        if (!$st) { return self::fail(500, 'تعذر تسجيل المرتجع: ' . $conn->error); }
        $stage = mb_substr((string) ($ctx['source_stage'] ?? ''), 0, 80);
        $note  = mb_substr((string) ($ctx['reason_note'] ?? ''), 0, 400);
        $tu = $toUser > 0 ? $toUser : null;
        $tr = $toRole > 0 ? $toRole : null;
        $by = intval($ctx['created_by'] ?? 0);
        /* ◆ 12 عمودًا — مقطعًا مقطعًا لا عدًّا بالعين. */
        $vals  = array($co, $code, $kind, $ref, $stage, $tu, $tr, $toLabel, $reason, $note, $wiId, $by);
        $types = 'i' . 'ssss' . 'ii' . 'sss' . 'ii';
        self::assertArity($types, $vals, 'fin_backflow_log');
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) { $e = $st->error; $st->close(); return self::fail(500, 'تعذر تسجيل المرتجع: ' . $e); }
        $id = $st->insert_id;
        $st->close();

        return array('ok' => true, 'code' => 200, 'reason' => 'أطلق المرتجع ' . $code,
                     'backflow_id' => $id, 'work_item_id' => $wiId);
    }

    /**
     * BR-06: إلغاءُ الطلبِ **يُغلق** مرتجَعاتِه بسببِ الإلغاءِ ولا يحذفها —
     * فالسجلُّ يبقى للتدقيقِ ويُعرف من ألغى ولماذا.
     */
    public static function closeOnCancel(\mysqli $conn, $co, $kind, $ref, $why, $by)
    {
        $st = $conn->prepare(
            "UPDATE fin_backflow_log
                SET state = 'closed_cancelled', close_reason = ?, closed_at = NOW()
              WHERE company_id = ? AND source_kind = ? AND source_ref = ? AND state = 'open'");
        if (!$st) { return 0; }
        $why = mb_substr((string) $why, 0, 300);
        $st->bind_param('siss', $why, $co, $kind, $ref);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        return $n;
    }

    /**
     * BR-06 — الوجهُ الآخر: المرتجَعُ الذي عُولِج يُغلق **بسببٍ مسجَّلٍ وفاعلٍ
     * معروف**، ولا يُحذف ولا يذوي مفتوحًا. وهذا هو الفعلُ الذي تملكه شاشةُ
     * المرتجَعات — لا حذفًا ولا تعديلَ سبب.
     */
    public static function resolveBackflow(\mysqli $conn, array $ctx)
    {
        $co  = intval($ctx['company_id'] ?? 0);
        $id  = intval($ctx['backflow_id'] ?? 0);
        $by  = intval($ctx['closed_by'] ?? 0);
        $why = trim((string) ($ctx['close_reason'] ?? ''));
        if ($co <= 0 || $id <= 0) { return self::fail(422, 'الإغلاق يحتاج رقم المرتجع'); }
        /* BR-06: «والإغلاقُ بسببٍ مسجَّل» — فالسببُ شرطٌ لا زينة. */
        if ($why === '') { return self::fail(422, 'لا يغلق مرتجع بلا سبب مسجل (BR-06)'); }

        $st = $conn->prepare(
            "UPDATE fin_backflow_log
                SET state = 'closed_done', close_reason = ?, closed_at = NOW()
              WHERE id = ? AND company_id = ? AND state IN ('open','acted')");
        if (!$st) { return self::fail(500, 'تعذر الإغلاق: ' . $conn->error); }
        $why = mb_substr($why, 0, 300);
        $st->bind_param('sii', $why, $id, $co);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        if ($n <= 0) { return self::fail(409, 'لا مرتجع مفتوح بهذا الرقم — أو أغلق سلفا'); }

        /* ◆ المهمةُ المولَّدةُ معه **لا تُمسُّ من هنا**: لها آلةُ حالٍ في
             WorkItemService، ودفعُها بـUPDATE مباشرٍ يلتفُّ على انتقالاتِها.
             فنُبلِّغ عنها ولا نحرّكها — والمنفِّذُ يُغلقها من مهامِّه. */
        $pending = (int) self::scalarInt($conn,
            "SELECT COUNT(*) FROM fin_backflow_log b JOIN work_items wi ON wi.id = b.work_item_id
              WHERE b.id = " . $id . " AND b.company_id = " . $co
          . "   AND wi.status NOT IN ('closed_accepted','cancelled','rejected')");

        return array('ok' => true, 'code' => 200, 'closed_by' => $by, 'task_still_open' => $pending,
                     'reason' => 'أغلق المرتجع بسبب مسجل — ولم يحذف (BR-06)'
                               . ($pending > 0 ? ' · ومهمته لا تزال مفتوحة يغلقها منفذها' : ''));
    }

    /* ═══════════════════════════════════════════════════════════════════════
       الحارس — الشاهدُ على «لا تصل الخزينةَ بلا محاسبِ تخصصها»
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * OBL-0001: يُستدعى قبلَ إدراجِ أيِّ واقعةٍ في دفعةِ الخزينة.
     * fail-closed: ما لا شاهدَ لتوجيهِه لا يمرُّ.
     */
    public static function assertRouted(\mysqli $conn, $co, $kind, $ref)
    {
        $st = $conn->prepare(
            "SELECT target_spec FROM fin_routing_log
              WHERE company_id = ? AND source_kind = ? AND source_ref = ? LIMIT 1");
        if (!$st) { return self::fail(500, 'تعذر فحص التوجيه'); }
        $st->bind_param('iss', $co, $kind, $ref);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return self::fail(409, 'لا تصل الخزينة واقعة لم تمر بمحاسب تخصصها (OBL-0001)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'مرت ب' . $row['target_spec'], 'spec' => $row['target_spec']);
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    /** المرتجَعاتُ التي هي رفضٌ أو نقصٌ — وحدَها توجب رمزَ سبب (BR-03). */
    private static function noticeNeedsReason($code)
    {
        return in_array($code, array('BF-01', 'BF-02', 'BF-14', 'BF-15'), true);
    }

    private static function reasonExists(\mysqli $conn, $code)
    {
        $st = $conn->prepare("SELECT 1 FROM fin_reason_codes WHERE code = ? AND active = 1 LIMIT 1");
        if (!$st) { return false; }
        $st->bind_param('s', $code);
        $st->execute();
        $ok = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    private static function fetchNotice(\mysqli $conn, $code)
    {
        $st = $conn->prepare(
            "SELECT code, title, fires_when, destination, rule_text, needs_action
               FROM fin_backflow_notices WHERE code = ? AND active = 1 LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('s', $code);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) { $row['needs_action'] = (int) $row['needs_action']; }
        return $row ?: null;
    }

    /** عددٌ واحدٌ من استعلامٍ — و**الفشلُ يُرفع** فلا يمرُّ صفرٌ كاذبٌ عن استعلامٍ ميت. */
    private static function scalarInt(\mysqli $conn, $sql)
    {
        $r = $conn->query($sql);
        if ($r === false) { throw new \RuntimeException('استعلام فاشل: ' . $conn->error); }
        $row = $r->fetch_row();
        $r->free();
        return $row ? (int) $row[0] : 0;
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }

    /**
     * ◆ حارسُ الانزياح: عدُّ الوسائطِ بالعينِ مصيدةُ أخطاءٍ **صامتة** — حرفٌ
     *   زائدٌ في سلسلةِ الأنواعِ يكتب النصَّ في خانةِ الرقمِ بلا خطأٍ ظاهر.
     *   فيُفحص الطولُ قبلَ كلِّ ربطٍ ويُرمى صريحًا عند الاختلاف.
     */
    private static function assertArity($types, array $vals, $label)
    {
        if (strlen($types) !== count($vals)) {
            throw new \LengthException(sprintf(
                'انزياح وسائط في %s — أنواع %d · قيم %d', $label, strlen($types), count($vals)));
        }
    }
}
