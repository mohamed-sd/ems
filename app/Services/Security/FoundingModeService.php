<?php
/**
 * FoundingModeService — وضع التأسيس بوضعيه (SEC-01 §7 · §7.3 · §12 خدمة ⑤ · SEC-27/28)
 * ───────────────────────────────────────────────────────────────────────────
 * «وضعان معًا لا أحدهما»: اكتشاف التجربة (توسيع) واختبار الصلاحيات (حقيقي).
 * «لا تفعيل بلا ends_at» (S13) · «الحراس لا يُعطَّلون مهما اتسع التأسيس» ·
 * «كل فعل يوسم founding_mode=1» · والإغلاق بالبروتوكول السداسي وشهادته (S15).
 */

namespace App\Services\Security;

class FoundingModeService
{
    /** تفعيل وضع — ends_at إلزامي (S13: بلا نهاية → 422). */
    public static function activate(\mysqli $conn, $mode, $endsAt, $bannerText = null)
    {
        if (!in_array($mode, array('discovery', 'permission_test'), true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'الوضعان: discovery · permission_test');
        }
        if ($endsAt === null || $endsAt === '') {
            return array('ok' => false, 'code' => 422,
                'reason' => 'لا وضع تأسيس مفتوح المدة — تاريخ النهاية إلزامي من يومه الأول (S13)');
        }
        $stmt = $conn->prepare(
            "UPDATE founding_mode SET enabled = 1, started_at = NOW(), ends_at = ?,
                    banner_text = COALESCE(?, banner_text), closed_by = NULL, closed_at = NULL
             WHERE mode = ?");
        $stmt->bind_param('sss', $endsAt, $bannerText, $mode);
        $ok = $stmt->execute();
        $err = $conn->error;
        $stmt->close();
        if (!$ok) {
            return array('ok' => false, 'code' => 422, 'reason' => 'رفضته القاعدة: ' . $err);
        }
        return array('ok' => true, 'code' => 200,
            'reason' => 'فعل ' . $mode . ' حتى ' . $endsAt . ' — بشريط ظاهر ووسم لكل فعل');
    }

    /** أوضاع التأسيس الحية — للشريط الظاهر في كل شاشة (§7-③). */
    public static function activeBanner(\mysqli $conn)
    {
        $res = $conn->query("SELECT mode, banner_text, ends_at FROM founding_mode
                              WHERE enabled = 1 AND (ends_at IS NULL OR ends_at >= NOW())");
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        if (!$rows) { return null; }
        $parts = array();
        foreach ($rows as $r) {
            $parts[] = ($r['banner_text'] ?: ('وضع التأسيس — ' . $r['mode'])) . ' — ينتهي في ' . $r['ends_at'];
        }
        return implode(' · ', $parts);
    }

    /** أيسري وضع الاكتشاف (التوسيع فيه وحده — §7-①)؟ */
    public static function discoveryActive(\mysqli $conn)
    {
        $r = $conn->query("SELECT 1 FROM founding_mode WHERE mode = 'discovery' AND enabled = 1 AND ends_at >= NOW() LIMIT 1");
        return $r && $r->num_rows > 0;
    }

    /**
     * البروتوكول السداسي (§7.3 · SEC-28) — يجري الخطوات ويعيد شهادة الإغلاق.
     * لا يغلق والشروط منقوصة (S15): صفر حساب بصلاحية تأسيس · صفر متدرب نشط ·
     * كل صلاحية بمصدر مشتق · ولا إغلاق قبل اجتياز وضع اختبار الصلاحيات.
     */
    public static function closeProtocol(\mysqli $conn, $companyId, $closedBy, $closureRef)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'certificate' => null, 'steps' => array());
        $companyId = intval($companyId);
        $closedBy = intval($closedBy);

        // شرط مسبق: اجتياز وضع اختبار الصلاحيات (فُعِّل يومًا وأُنهي أو ساري الإنهاء)
        $pt = $conn->query("SELECT started_at FROM founding_mode WHERE mode = 'permission_test' AND started_at IS NOT NULL")->fetch_assoc();
        if (!$pt) {
            $out['code'] = 409;
            $out['reason'] = 'لا إغلاق قبل اجتياز وضع اختبار الصلاحيات — لم يفعل قط (§7 القاعدة القاطعة)';
            return $out;
        }

        // ① جرد الصلاحيات الفعلية
        $inv = $conn->query("SELECT COUNT(*) c, COUNT(DISTINCT person_id) p FROM effective_permissions WHERE company_id = {$companyId}")->fetch_assoc();
        $out['steps']['①_inventory'] = 'جردت ' . $inv['c'] . ' صلاحية مشتقة ل' . $inv['p'] . ' شخصا';

        // ② القوالب النهائية من المصادر الأربعة — قياس المنشور
        $tpl = $conn->query("SELECT COUNT(DISTINCT tpl_id) c FROM permission_template_versions WHERE state = 'published'")->fetch_assoc();
        $out['steps']['②_templates'] = $tpl['c'] . ' قالبا بإصدار منشور';

        // ③ إخراج المتدربين والحسابات التجريبية — تعطيل لا حذف
        $conn->query("UPDATE person_relationships SET state = 'ended', valid_to = CURDATE()
                      WHERE company_id = {$companyId} AND relation_code = 'rel_trainee' AND state = 'active'");
        $trainees = $conn->affected_rows;
        $out['steps']['③_trainees'] = 'أنهي ' . $trainees . ' علاقة متدرب — أرشفة لا حذف';

        // ④ إعادة التصنيف — كل شخص نشط له مركز بطبقاته
        $unclassified = intval($conn->query(
            "SELECT COUNT(*) c FROM person_relationships r
              WHERE r.company_id = {$companyId} AND r.state = 'active'
                AND NOT EXISTS (SELECT 1 FROM person_positions p
                                 WHERE p.person_id = r.person_id AND p.state = 'active')")->fetch_assoc()['c']);
        $out['steps']['④_reclassify'] = $unclassified === 0
            ? 'كل صاحب علاقة نشطة له مركز بطبقاته'
            : $unclassified . ' علاقة نشطة بلا مركز — تصنف قبل الإغلاق';

        // ⑤ الخفض الجماعي: إسقاط استثناءات التأسيس (البذور الموسومة) دفعة واحدة
        $conn->query("UPDATE permission_exceptions SET state = 'revoked'
                      WHERE company_id = {$companyId} AND state = 'active'
                        AND reason LIKE '%تأسيس%'");
        $dropped = $conn->affected_rows;
        $out['steps']['⑤_mass_reduce'] = 'أسقط ' . $dropped . ' استثناء تأسيس بقرار واحد';

        // ⑥ الشهادة — الشروط الثلاثة
        $foundingGrants = intval($conn->query(
            "SELECT COUNT(*) c FROM permission_exceptions
              WHERE company_id = {$companyId} AND state = 'active' AND reason LIKE '%تأسيس%'")->fetch_assoc()['c']);
        $activeTrainees = intval($conn->query(
            "SELECT COUNT(*) c FROM person_relationships
              WHERE company_id = {$companyId} AND relation_code = 'rel_trainee' AND state = 'active'")->fetch_assoc()['c']);
        $orphanPerms = intval($conn->query(
            "SELECT COUNT(*) c FROM effective_permissions ep
              WHERE ep.company_id = {$companyId} AND ep.source_ref = ''")->fetch_assoc()['c']);

        $pass = ($foundingGrants === 0 && $activeTrainees === 0 && $orphanPerms === 0 && $unclassified === 0);
        $cert = array(
            'zero_founding_accounts' => $foundingGrants === 0,
            'zero_active_trainees' => $activeTrainees === 0,
            'every_permission_derived' => $orphanPerms === 0,
            'all_classified' => $unclassified === 0,
            'permission_test_passed_since' => $pt['started_at'],
            'closed_by' => $closedBy,
            'closure_ref' => $closureRef,
        );
        $out['certificate'] = $cert;
        if (!$pass) {
            $out['code'] = 409;
            $out['reason'] = 'شروط الشهادة منقوصة — لا إغلاق مدعى: '
                . json_encode($cert, JSON_UNESCAPED_UNICODE);
            return $out;
        }

        $stmt = $conn->prepare(
            "UPDATE founding_mode SET enabled = 0, closed_by = ?, closed_at = NOW(), closure_ref = ?
             WHERE enabled = 1 OR closed_at IS NULL");
        $stmt->bind_param('is', $closedBy, $closureRef);
        $stmt->execute();
        $stmt->close();
        require_once __DIR__ . '/PositionService.php';
        PositionService::audit($conn, $companyId, $closedBy, 'revoked', 'founding_mode:all',
            json_encode($cert, JSON_UNESCAPED_UNICODE), 'شهادة إغلاق التأسيس — البروتوكول السداسي', $closedBy);
        $out['ok'] = true;
        $out['code'] = 200;
        $out['reason'] = 'أغلق التأسيس بشهادة موقعة — يوقعها مدير الصلاحيات والمدير التنفيذي';
        return $out;
    }
}
