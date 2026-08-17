<?php
/**
 * ImpersonationService — جلسةُ الدخولِ بالنيابة (GOV-AUTH-01 §6-2)
 * ═══════════════════════════════════════════════════════════════════════════
 * «لا يدخل أحدٌ بحسابِ أحد. بل يفتح جلسةَ نيابةٍ باسمِه هو، فيرى ما يرى
 *  المُنابُ عنه ويفعل ما يملك، وكلُّ سطرٍ يُكتب بالفاعلِ الحقيقيِّ ومعه
 *  المُنابُ عنه ومرجعُ الجلسةِ وسببُها.»
 *
 * ◆ الجلسةُ **لا ترفع صلاحيةً**: الفاعلُ يعمل بسلطتِه هو — والتصعيدُ الرأسيُّ
 *   (A3) يغطي مواضعَ من دونَه أصلًا. أثرُها: الوسمُ الظاهرُ والنسبةُ المزدوجةُ
 *   في دفترِ الأفعالِ والإخطارُ الفوري.
 * ◆ الشروطُ البنيويةُ في القاعدةِ (chk_imp_reason · chk_imp_not_self ·
 *   قادحُ trg_imp_not_oversight) — وهنا شرطُ الخطِّ الإداريِّ والمدة.
 * ◆ سقفُ المدةِ 24 ساعةً — قياسًا على قيدِ أسرتِها الحوكميةِ chk_bg_24h
 *   (كسرُ الزجاج): مؤقَّتٌ قصيرٌ بمراجعةٍ، لا تفويضٌ طويل (له gov_delegations).
 */

namespace App\Services\Gov;

class ImpersonationService
{
    const MAX_HOURS = 24;

    /** الأدوارُ التي يجوز لحاملِها فتحُ جلسةٍ خارجَ خطِّه الإداري: التنفيذية والحوكمة */
    const BROAD_ROLES = array(9, 15);

    /**
     * سلطةُ الفاعلِ على الهدف: دورُ الفاعلِ سلفٌ لدورِ الهدفِ في شجرةِ
     * parent_role_id (التصعيدُ الرأسيُّ A3) — أو الفاعلُ من الأدوارِ العريضة.
     */
    public static function mayImpersonate(\mysqli $conn, int $actorRole, int $targetRole): bool
    {
        if (in_array($actorRole, self::BROAD_ROLES, true)) { return true; }
        if ($actorRole === $targetRole) { return false; }
        // صعودٌ من الهدفِ إلى الجذر — عمقُ الشجرةِ الحيِّ ≤ 3
        $cur = $targetRole;
        for ($i = 0; $i < 6; $i++) {
            $r = $conn->query("SELECT parent_role_id FROM roles WHERE id = " . (int) $cur);
            $row = $r ? $r->fetch_row() : null;
            if ($row === null || $row[0] === null) { return false; }
            $cur = (int) $row[0];
            if ($cur === $actorRole) { return true; }
        }
        return false;
    }

    /**
     * فتحُ جلسةٍ — بسببٍ ومدةٍ وإخطارٍ فوريٍّ لصاحبِ الموضع.
     * @return array{ok:bool, reason:string, imp_id?:int}
     */
    public static function open(\mysqli $conn, array $actor, int $targetUserId, string $reason, int $hours)
    {
        $actorId = (int) ($actor['id'] ?? 0);
        $actorRole = (int) ($actor['role'] ?? 0);
        $reason = trim($reason);
        $hours = max(1, min(self::MAX_HOURS, $hours));
        if ($actorId <= 0 || $targetUserId <= 0) { return array('ok' => false, 'reason' => 'أطرافٌ ناقصة'); }
        if ($reason === '') { return array('ok' => false, 'reason' => 'لا جلسةَ بسببٍ فارغ (chk_imp_reason)'); }

        $r = $conn->query("SELECT id, role, username, company_id FROM users WHERE id = " . (int) $targetUserId . " AND status = 1");
        $target = $r ? $r->fetch_assoc() : null;
        if ($target === null) { return array('ok' => false, 'reason' => 'الهدفُ غيرُ موجودٍ أو غيرُ نشط'); }
        if (!self::mayImpersonate($conn, $actorRole, (int) $target['role'])) {
            return array('ok' => false, 'reason' => 'خارجَ خطِّك الإداري — التصعيدُ الرأسيُّ لا يبلغ هذا الموضع');
        }
        $open = $conn->query("SELECT COUNT(*) FROM impersonation_sessions
                               WHERE actor_user = {$actorId} AND closed_at IS NULL AND valid_to > NOW()");
        if ($open && (int) $open->fetch_row()[0] > 0) {
            return array('ok' => false, 'reason' => 'لك جلسةٌ جاريةٌ — تُغلق قبلَ فتحِ غيرِها');
        }

        $st = $conn->prepare(
            "INSERT INTO impersonation_sessions
                (company_id, actor_user, target_user, reason, opened_at, valid_to, notified_at)
             VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL ? HOUR, NOW())");
        if (!$st) { return array('ok' => false, 'reason' => $conn->error); }
        $co = (int) ($target['company_id'] ?? 0);
        $st->bind_param('iiisi', $co, $actorId, $targetUserId, $reason, $hours);
        if (!$st->execute()) {
            $err = $st->error; $st->close();
            return array('ok' => false, 'reason' => $err); // قوادحُ القاعدةِ ترفض الرقابيّين والذات
        }
        $impId = (int) $conn->insert_id;
        $st->close();

        // الإخطارُ الفوريُّ لصاحبِ الموضع — notified_at خُتم مع الفتح (AC-A5)
        $an = $conn->real_escape_string((string) ($actor['username'] ?? ('#' . $actorId)));
        $rs = $conn->real_escape_string(mb_substr($reason, 0, 120));
        $conn->query("INSERT INTO fin_notifications (company_id, target_level, target_user_id, title, link, is_read, created_at)
                      VALUES ({$co}, 'user', {$targetUserId},
                              'فُتحت جلسةُ نيابةٍ في موضعِك: {$an} — بسببِ: {$rs}',
                              'Governance/impersonations.php', 0, NOW())");

        $_SESSION['imp_session'] = array(
            'imp_id' => $impId,
            'target_user' => $targetUserId,
            'target_name' => (string) $target['username'],
            'reason' => $reason,
            'valid_to' => date('Y-m-d H:i', time() + $hours * 3600),
        );
        return array('ok' => true, 'reason' => '', 'imp_id' => $impId);
    }

    /** إغلاقُ الجلسةِ الجارية — بيدِ فاعلِها. */
    public static function close(\mysqli $conn, int $actorId): bool
    {
        $conn->query("UPDATE impersonation_sessions SET closed_at = NOW()
                       WHERE actor_user = " . (int) $actorId . " AND closed_at IS NULL");
        unset($_SESSION['imp_session']);
        return true;
    }

    /** الجلسةُ الجاريةُ للفاعل — من ذاكرةِ الجلسةِ مع تحققِ الصلاحيةِ الزمنية. */
    public static function active(): ?array
    {
        $s = isset($_SESSION['imp_session']) && is_array($_SESSION['imp_session']) ? $_SESSION['imp_session'] : null;
        if ($s === null) { return null; }
        if (strtotime((string) $s['valid_to']) < time()) { unset($_SESSION['imp_session']); return null; }
        return $s;
    }
}
