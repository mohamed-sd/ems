<?php
/**
 * بوابةُ الاعتمادِ الرباعية — ApprovalGate (FIN-ACC-01 §٤-٧ · PROP-01 §٤-١ ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * تحرس حكمًا واحدًا: **الأنواعُ الأربعةُ لا يُغني أحدُها عن الآخر** — وشاهدُه
 * «صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة» (PROP-01 §٧-٢ ③).
 *
 *   APR-1  اعتمادُ الحاجةِ أو الطلب    · الإدارةُ المالكة   · «أنحتاج هذا فعلًا؟»
 *   APR-2  الاعتمادُ الموازنيُّ والمحاسبي · المحاسبُ ورئيسُ الحسابات · «أتوجد موازنةٌ
 *          وأصحيحةٌ المعالجة؟» — ◆ مراجعةٌ لا ترخيصٌ بالدفع
 *   APR-3  اعتمادُ الالتزامِ أو الدفع  · صاحبُ السقف       · «أنأذن بالتزامِ المال؟»
 *   APR-4  تنفيذُ الدفع                · الخزينةُ والبنوك   · «أنُخرج المالَ فعلًا؟»
 *          ◆ تنفّذ ما اعتُمد ولا تعتمد ما تنفّذ
 *
 * وأربعةُ حُرَّاسٍ بنيوية:
 *  ① الترتيب     — لا يُقفز نوعٌ: APR-n يحتاج APR-1..n-1 معتمَدةً قبله.
 *  ② التعارض     — FACC-0044: ولا يُجمع اثنان في شخصٍ واحدٍ حيث يتعارضان.
 *                  والأزواجُ في `fin_approval_conflicts` بيانًا لا كودًا.
 *  ③ الدور       — FMGR-0004: المديرُ الماليُّ لا يعتمد موازنيًّا نيابةً عن
 *                  رئيسِ الحساباتِ ولا عن محاسبِ الإدارة.
 *  ④ السقف       — CEO-Y0120: ما تجاوز السقفَ لا يُنفَّذ قبلَ قرارِ من فوقَه،
 *                  والسقفُ يُجمَّد لحظةَ القرارِ فلا يُعاد قراءتُه بعد تغييره.
 *
 * ◆ الحدُّ: البوابةُ **لا تقيّد ولا تدفع** — تُسجِّل القرارَ وتمنع التخطي.
 */

namespace App\Services\Finance;

class ApprovalGate
{
    /** ترتيبُ السلسلةِ — يُقرأ من القاعدةِ ولا يُثبَّت هنا إلا احتياطًا. */
    const ORDER = array('APR-1', 'APR-2', 'APR-3', 'APR-4');

    /**
     * يسجّل اعتمادًا من نوعٍ واحدٍ على مستند.
     *
     * @param array $ctx company_id · source_kind · source_ref · apr_code ·
     *                   actor_user_id · actor_role_id · decision (approved|rejected|escalated)
     *                   · amount · currency · actor_capacity · reason_code · note
     */
    public static function record(\mysqli $conn, array $ctx)
    {
        $co   = intval($ctx['company_id'] ?? 0);
        $kind = trim((string) ($ctx['source_kind'] ?? ''));
        $ref  = trim((string) ($ctx['source_ref'] ?? ''));
        $apr  = trim((string) ($ctx['apr_code'] ?? ''));
        $who  = intval($ctx['actor_user_id'] ?? 0);
        $dec  = (string) ($ctx['decision'] ?? 'approved');
        if ($co <= 0 || $kind === '' || $ref === '' || $apr === '' || $who <= 0) {
            return self::fail(422, 'الاعتمادُ يحتاج الكيانَ والمستندَ ونوعَه وفاعلَه');
        }
        if (!in_array($dec, array('approved', 'rejected', 'escalated'), true)) {
            return self::fail(422, 'قرارٌ غيرُ معرَّف: ' . $dec);
        }

        $type = self::typeOf($conn, $apr);
        if ($type === null) { return self::fail(404, "نوعُ اعتمادٍ غيرُ معرَّف: {$apr}"); }

        /* ── حارسُ الدور (FMGR-0004) ─────────────────────────────────────── */
        $roleId = self::actorRole($conn, $who, $ctx);
        if (trim($type['allowed_roles']) !== '') {
            $allowed = array_filter(array_map('trim', explode(',', $type['allowed_roles'])));
            if (!in_array((string) $roleId, $allowed, true)) {
                return self::fail(403, $type['title'] . ' — لا يملكه هذا الدور: صاحبُه '
                                     . $type['owner_label'] . ' (FMGR-0004)');
            }
        }

        /* ── حارسُ الترتيب: لا يُقفز نوعٌ ───────────────────────────────── */
        if ($dec === 'approved') {
            $have = self::approvedSet($conn, $co, $kind, $ref);
            foreach (self::typesBefore($conn, $type['seq']) as $prior) {
                if (!in_array($prior, $have, true)) {
                    return self::fail(409, $type['title'] . ' لا يقع قبلَ ' . $prior
                                         . ' — والأنواعُ لا يُغني أحدُها عن الآخر (FACC-0044)');
                }
            }
        }

        /* ── حارسُ التعارض (FACC-0044) ──────────────────────────────────── */
        foreach (self::conflictsOf($conn, $apr) as $other) {
            $prev = self::actorOf($conn, $co, $kind, $ref, $other['code']);
            if ($prev > 0 && $prev === $who) {
                return self::fail(409, 'الشخصُ نفسُه لا يجمع ' . $apr . ' و' . $other['code']
                                     . ' — ' . $other['rule_text'] . ' (' . $other['doc_ref'] . ')');
            }
        }

        /* ── حارسُ السقف (CEO-Y0120) ────────────────────────────────────── */
        $amount = isset($ctx['amount']) && $ctx['amount'] !== '' ? (float) $ctx['amount'] : null;
        $cap    = null;
        if ((int) $type['needs_cap'] === 1 && $dec === 'approved') {
            if ($amount === null) {
                return self::fail(422, $type['title'] . ' لا يقع بلا مبلغٍ يُقاس على السقف');
            }
            $cap = self::capFor($conn, $co, $who, $roleId, $apr);
            if ($cap === null) {
                return self::fail(403, $type['title'] . ' لا يُمنح بلا سقفٍ معلَنٍ لصاحبِه (APR-3)');
            }
            if ($amount > (float) $cap['max_amount']) {
                return self::fail(409, sprintf(
                    'المبلغُ %s يتجاوز سقفَ صاحبِه %s — ولا يُنفَّذ قبلَ قرارِ من فوقَه (CEO-Y0120)',
                    number_format($amount, 2), number_format((float) $cap['max_amount'], 2)));
            }
        }

        /* ── التسجيل: النوعُ الواحدُ مرةً واحدةً على المستندِ الواحد ────── */
        $sql = "INSERT INTO fin_approval_chain
                  (company_id, source_kind, source_ref, apr_code, decision, actor_user_id,
                   actor_role_id, actor_capacity, amount, currency, cap_at_decision,
                   reason_code, note, decided_at, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)";
        $st = $conn->prepare($sql);
        if (!$st) { return self::fail(500, 'تعذّر تسجيلُ الاعتماد: ' . $conn->error); }
        $rid = $roleId > 0 ? $roleId : null;
        $capV = $cap !== null ? (float) $cap['max_amount'] : null;
        $capacity = mb_substr((string) ($ctx['actor_capacity'] ?? $type['owner_label']), 0, 120);
        $cur  = (string) ($ctx['currency'] ?? 'USD');
        $rc   = mb_substr((string) ($ctx['reason_code'] ?? ''), 0, 60);
        $note = mb_substr((string) ($ctx['note'] ?? ''), 0, 400);
        $by   = intval($ctx['created_by'] ?? $who);
        $vals  = array($co, $kind, $ref, $apr, $dec, $who, $rid, $capacity, $amount, $cur, $capV, $rc, $note, $by);
        $types = 'i' . 'ssss' . 'ii' . 's' . 'd' . 's' . 'd' . 'ss' . 'i';
        if (strlen($types) !== count($vals)) {
            return self::fail(500, sprintf('انزياحُ وسائط: أنواع %d · قيم %d', strlen($types), count($vals)));
        }
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) {
            $e = $st->errno; $msg = $st->error; $st->close();
            if ($e === 1062) {
                return self::fail(409, $type['title'] . ' مسجَّلٌ على هذا المستندِ مرةً — والنوعُ لا يتكرر');
            }
            return self::fail(500, 'تعذّر تسجيلُ الاعتماد: ' . $msg);
        }
        $id = $st->insert_id;
        $st->close();

        return array('ok' => true, 'code' => 200, 'id' => $id,
                     'reason' => $type['title'] . ' سُجِّل', 'cap' => $capV);
    }

    /**
     * الحارسُ قبلَ التنفيذ: «صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة».
     * fail-closed — ما نقص نوعٌ من الثلاثةِ الأولى لا يُنفَّذ.
     */
    public static function assertComplete(\mysqli $conn, $co, $kind, $ref)
    {
        $have = self::approvedSet($conn, $co, $kind, $ref);
        $need = self::typesBefore($conn, 4);   // APR-1 · APR-2 · APR-3
        $miss = array_values(array_diff($need, $have));
        if ($miss) {
            return self::fail(409, 'لا يُنفَّذ الطلبُ بلا: ' . implode(' · ', $miss)
                                 . ' — والأنواعُ الأربعةُ لا تُدمج (FACC-0044)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'السلسلةُ كاملةٌ — ' . implode(' · ', $have));
    }

    /** حالةُ الأنواعِ الأربعةِ على مستندٍ — للعرضِ في الشاشة. */
    public static function status(\mysqli $conn, $co, $kind, $ref)
    {
        $out = array();
        foreach (self::allTypes($conn) as $t) {
            $out[$t['code']] = array('title' => $t['title'], 'seq' => (int) $t['seq'],
                                     'decision' => null, 'actor_user_id' => null, 'decided_at' => null);
        }
        $st = $conn->prepare("SELECT apr_code, decision, actor_user_id, actor_capacity, amount, decided_at
                                FROM fin_approval_chain
                               WHERE company_id = ? AND source_kind = ? AND source_ref = ?");
        if ($st) {
            $st->bind_param('iss', $co, $kind, $ref);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                if (isset($out[$r['apr_code']])) { $out[$r['apr_code']] = array_merge($out[$r['apr_code']], $r); }
            }
            $st->close();
        }
        return $out;
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    private static function allTypes(\mysqli $conn)
    {
        $out = array();
        $r = $conn->query("SELECT code, seq, title, owner_label, allowed_roles, needs_cap
                             FROM fin_approval_types WHERE active = 1 ORDER BY seq");
        while ($r && $x = $r->fetch_assoc()) { $out[] = $x; }
        return $out;
    }

    private static function typeOf(\mysqli $conn, $code)
    {
        $st = $conn->prepare("SELECT code, seq, title, owner_label, allowed_roles, needs_cap
                                FROM fin_approval_types WHERE code = ? AND active = 1 LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('s', $code);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /** الأنواعُ التي ترتيبُها أقلُّ من هذا — يجب أن تسبقه معتمَدة. */
    private static function typesBefore(\mysqli $conn, $seq)
    {
        $out = array();
        $st = $conn->prepare("SELECT code FROM fin_approval_types
                               WHERE active = 1 AND seq < ? ORDER BY seq");
        if (!$st) { return $out; }
        $seq = (int) $seq;
        $st->bind_param('i', $seq);
        $st->execute();
        $rs = $st->get_result();
        while ($x = $rs->fetch_assoc()) { $out[] = $x['code']; }
        $st->close();
        return $out;
    }

    private static function approvedSet(\mysqli $conn, $co, $kind, $ref)
    {
        $out = array();
        $st = $conn->prepare("SELECT apr_code FROM fin_approval_chain
                               WHERE company_id = ? AND source_kind = ? AND source_ref = ?
                                 AND decision = 'approved'");
        if (!$st) { return $out; }
        $st->bind_param('iss', $co, $kind, $ref);
        $st->execute();
        $rs = $st->get_result();
        while ($x = $rs->fetch_assoc()) { $out[] = $x['apr_code']; }
        $st->close();
        return $out;
    }

    private static function actorOf(\mysqli $conn, $co, $kind, $ref, $apr)
    {
        $st = $conn->prepare("SELECT actor_user_id FROM fin_approval_chain
                               WHERE company_id = ? AND source_kind = ? AND source_ref = ?
                                 AND apr_code = ? LIMIT 1");
        if (!$st) { return 0; }
        $st->bind_param('isss', $co, $kind, $ref, $apr);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['actor_user_id']) : 0;
    }

    /** الأنواعُ المتعارضةُ مع هذا — الزوجُ يُقرأ من الجانبين. */
    private static function conflictsOf(\mysqli $conn, $apr)
    {
        $out = array();
        $st = $conn->prepare("SELECT apr_a, apr_b, rule_text, doc_ref FROM fin_approval_conflicts
                               WHERE active = 1 AND (apr_a = ? OR apr_b = ?)");
        if (!$st) { return $out; }
        $st->bind_param('ss', $apr, $apr);
        $st->execute();
        $rs = $st->get_result();
        while ($x = $rs->fetch_assoc()) {
            $other = ($x['apr_a'] === $apr) ? $x['apr_b'] : $x['apr_a'];
            $out[] = array('code' => $other, 'rule_text' => $x['rule_text'], 'doc_ref' => $x['doc_ref']);
        }
        $st->close();
        return $out;
    }

    /**
     * دورُ الفاعل. ◆ گوتشا: الدورُ في `users` عمودان — `role` نصًّا (وهو ما
     *   تقرؤه المصادقة) و`role_id` رقمًا مملوءًا جزئيًّا. فالقراءةُ منهما معًا.
     */
    private static function actorRole(\mysqli $conn, $userId, array $ctx)
    {
        if (!empty($ctx['actor_role_id'])) { return intval($ctx['actor_role_id']); }
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

    /** السقفُ النافذُ للفاعل: بالشخصِ أولًا ثم بدورِه — والأدقُّ يغلب. */
    private static function capFor(\mysqli $conn, $co, $userId, $roleId, $apr)
    {
        $st = $conn->prepare(
            "SELECT max_amount, currency, escalates_to_role, scope_kind
               FROM fin_authority_caps
              WHERE company_id = ? AND apr_code = ? AND active = 1
                AND ((scope_kind = 'user' AND scope_ref = ?) OR (scope_kind = 'role' AND scope_ref = ?))
                AND (effective_from IS NULL OR effective_from <= CURDATE())
                AND (effective_to   IS NULL OR effective_to   >= CURDATE())
              ORDER BY (scope_kind = 'user') DESC, max_amount DESC LIMIT 1");
        if (!$st) { return null; }
        $u = (string) $userId; $r = (string) $roleId;
        $st->bind_param('isss', $co, $apr, $u, $r);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function fail($code, $reason)
    {
        return array('ok' => false, 'code' => $code, 'reason' => $reason);
    }
}
