<?php
/**
 * حوكمة الكيانات الموسَّعة — EntityGovernanceService (LEG-01 §3 §5 · الأنماط ③④)
 * ───────────────────────────────────────────────────────────────────────────
 * OwnershipService: شجرة الملكية في تاريخ · **قيد المئة المشروط**: يُفرض
 *   Σ=100 عند ownership_completeness=full وحده؛ وجزئي ≤100؛ ومجهول يُسجَّل
 *   المعلوم موسومًا بالنقص — «فرض المئة على المجهول يدفع لبيانات ملفَّقة» ·
 *   التغيير علاقة جديدة بتاريخها (لا أثر رجعي 423) · تضارب المصالح يُكشف.
 * ExpiryMonitor: مسح التراخيص والكفالات والتفويضات — ExpiringSoon بإشعار،
 *   والانتهاء يُسقط/يعلّم آليًّا.
 * الأنماط ③④: أعلام تفعيل لا بنية — enablePattern يقلب عناصر النمط لنطاقه.
 */

namespace App\Core;

class EntityGovernanceService
{
    /** عناصر كل نمط (LEG-01 §7) — الترقية قلب أعلام لا هجرة */
    const PATTERN_ELEMENTS = array(
        2 => array('internal_parties'),
        3 => array('internal_parties', 'external_accounts'),
        4 => array('internal_parties', 'external_accounts', 'signing_caps', 'joint_signing', 'licenses', 'guarantees'),
    );

    /**
     * تغيير وسم اكتمال الملكية — EN-04 (E-05 · AC-E05-04): «كامل» لا يُمنح
     * إلا ومجموعُ الحصص النافذة 100٪ بالضبط، فالوسمُ شهادةٌ لا أمنية.
     * (كان الوسمُ يُحرَّر إدخالًا حرًّا فيُعلَن «كامل» على هيكلٍ ناقص —
     * والفاحص ③ في e05_checks يلتقط الخرق بعديًّا؛ هذه البوابة تمنعه قبليًّا.)
     */
    public static function setCompleteness(\mysqli $conn, $entityId, $mode)
    {
        $entityId = intval($entityId);
        $mode = (string) $mode;
        if (!in_array($mode, array('full', 'partial', 'unknown'), true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'وسم اكتمال غير معروف: ' . $mode);
        }
        $r = $conn->query('SELECT ownership_completeness FROM legal_entities WHERE entity_id = ' . $entityId);
        $ent = $r ? $r->fetch_assoc() : null;
        if (!$ent) { return array('ok' => false, 'code' => 404, 'reason' => 'الكيان غير موجود'); }
        if ((string) $ent['ownership_completeness'] === $mode) {
            return array('ok' => true, 'code' => 200, 'changed' => false); // عطالة: الوسم قائم
        }
        if ($mode === 'full') {
            $sum = 0.0;
            foreach (self::treeAt($conn, $entityId, date('Y-m-d')) as $row) { $sum += (float) $row['percent']; }
            if (abs($sum - 100.0) > 0.005) {
                return array('ok' => false, 'code' => 422,
                    'reason' => 'لا يوسم «كامل الهيكل»: Σ الحصص النافذة ' . number_format($sum, 2)
                              . ' (الفارق ' . sprintf('%+0.2f', $sum - 100) . ') — أكمل الهيكل إلى 100 ثم وسم');
            }
        }
        $stmt = $conn->prepare('UPDATE legal_entities SET ownership_completeness = ? WHERE entity_id = ?');
        $stmt->bind_param('si', $mode, $entityId);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        return $ok ? array('ok' => true, 'code' => 200, 'changed' => true)
                   : array('ok' => false, 'code' => 422, 'reason' => $err);
    }

    /** إضافة علاقة ملكية — قيد المئة المشروط بحسب ownership_completeness. */
    public static function addOwnership(\mysqli $conn, array $a)
    {
        foreach (array('owner_type', 'owner_id', 'owned_entity_id', 'percent', 'valid_from') as $f) {
            if (!isset($a[$f]) || $a[$f] === '') { return array('ok' => false, 'code' => 422, 'reason' => 'حقل إلزامي: ' . $f); }
        }
        $owned = intval($a['owned_entity_id']);
        $r = $conn->query('SELECT ownership_completeness FROM legal_entities WHERE entity_id = ' . $owned);
        $ent = $r ? $r->fetch_assoc() : null;
        if (!$ent) { return array('ok' => false, 'code' => 404, 'reason' => 'الكيان غير موجود'); }
        $mode = (string) $ent['ownership_completeness'];

        $from = (string) $a['valid_from'];
        $current = self::treeAt($conn, $owned, $from);
        $sum = (float) $a['percent'];
        foreach ($current as $row) { $sum += (float) $row['percent']; }

        if ($mode === 'full' && $sum - 100.0 > 0.005) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'كيان «كامل الهيكل»: Σ ' . number_format($sum, 2) . ' (الفارق ' . sprintf('%+0.2f', $sum - 100) . ') — يفرض 100 بالضبط');
        }
        if ($mode === 'partial' && $sum - 100.0 > 0.005) {
            return array('ok' => false, 'code' => 422, 'reason' => 'كيان «جزئي»: Σ لا تتجاوز 100 — الفعلي ' . number_format($sum, 2));
        }
        $stmt = $conn->prepare(
            'INSERT INTO entity_ownership (owner_type, owner_id, owned_entity_id, percent, ownership_kind, valid_from, valid_to, doc_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ot = (string) $a['owner_type']; $oid = intval($a['owner_id']);
        $pct = (float) $a['percent'];
        $kind = isset($a['ownership_kind']) ? (string) $a['ownership_kind'] : 'shares';
        $to = isset($a['valid_to']) && $a['valid_to'] !== '' ? (string) $a['valid_to'] : null;
        $doc = isset($a['doc_ref']) ? (string) $a['doc_ref'] : null;
        $stmt->bind_param('siidssss', $ot, $oid, $owned, $pct, $kind, $from, $to, $doc);
        if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); return array('ok' => false, 'code' => 422, 'reason' => $err); }
        $id = intval($stmt->insert_id);
        $stmt->close();
        $flag = ($mode === 'unknown') ? ' — الهيكل مجهول: المعلوم يسجل موسوما بالنقص لا ملفقا' : '';
        // تضارب المصالح: مالكٌ شخصٌ هو موظف/مستخدم عندنا — كشفٌ وتنبيه لا منع
        $conflict = false;
        if ($ot === 'person') {
            $r = $conn->query('SELECT id FROM users WHERE id = ' . $oid . ' LIMIT 1');
            if ($r && $r->fetch_row()) {
                $conflict = true;
                $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                              VALUES (1, 'finance_manager', 'تضارب مصالح مكشوف: المستخدم #{$oid} يملك حصة في الكيان #{$owned} — القرار للحوكمة', 'main/dashboard.php')");
            }
        }
        return array('ok' => true, 'code' => 201, 'own_id' => $id, 'conflict_detected' => $conflict,
            'reason' => 'سجلت العلاقة (Σ عند ' . $from . ' = ' . number_format($sum, 2) . ')' . $flag);
    }

    /** بيع/تغيير: إنهاء السابقة بتاريخ وفتح الجديدة من التاريخ نفسه — لا أثر رجعي. */
    public static function transferOwnership(\mysqli $conn, $ownId, $newOwnerType, $newOwnerId, $onDate, $docRef)
    {
        if (trim((string) $docRef) === '') { return array('ok' => false, 'code' => 422, 'reason' => 'مستند البيع إلزامي'); }
        $ownId = intval($ownId);
        $r = $conn->query('SELECT * FROM entity_ownership WHERE own_id = ' . $ownId);
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row) { return array('ok' => false, 'code' => 404, 'reason' => 'العلاقة غير موجودة'); }
        if ($row['valid_to'] !== null) {
            return array('ok' => false, 'code' => 423, 'reason' => 'علاقة منتهية سلفا — لا تعدل نسبة قائمة بأثر رجعي، والتغيير علاقة جديدة بتاريخها');
        }
        if ($onDate <= $row['valid_from']) {
            return array('ok' => false, 'code' => 423, 'reason' => 'تاريخ الانتقال قبل بدء العلاقة — لا أثر رجعي');
        }
        $prevEnd = date('Y-m-d', strtotime($onDate . ' -1 day'));
        $conn->query("UPDATE entity_ownership SET valid_to = '" . $conn->real_escape_string($prevEnd) . "' WHERE own_id = {$ownId}");
        $stmt = $conn->prepare(
            'INSERT INTO entity_ownership (owner_type, owner_id, owned_entity_id, percent, ownership_kind, valid_from, doc_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ot = (string) $newOwnerType; $oid = intval($newOwnerId);
        $owned = intval($row['owned_entity_id']); $pct = (float) $row['percent'];
        $kind = (string) $row['ownership_kind']; $from = (string) $onDate; $doc = (string) $docRef;
        $stmt->bind_param('siidsss', $ot, $oid, $owned, $pct, $kind, $from, $doc);
        $stmt->execute();
        $newId = intval($stmt->insert_id);
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'new_own_id' => $newId,
            'reason' => 'أنهيت حصة البائع ' . $prevEnd . ' وفتحت حصة المشتري ' . $onDate . ' — Σ باق كما هو');
    }

    /** شجرة الملكية في تاريخ. */
    public static function treeAt(\mysqli $conn, $entityId, $onDate)
    {
        $entityId = intval($entityId);
        $stmt = $conn->prepare(
            'SELECT * FROM entity_ownership WHERE owned_entity_id = ? AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)');
        $od = (string) $onDate;
        $stmt->bind_param('iss', $entityId, $od, $od);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** ExpiryMonitor — التراخيص والكفالات والتفويضات: تنبيه مبكر + انتهاء آلي. */
    public static function expirySweep(\mysqli $conn)
    {
        $alerts = 0;
        // انتهاء آلي
        $conn->query("UPDATE entity_licenses SET state = 'expired' WHERE state = 'active' AND expiry_date < CURDATE()");
        $conn->query("UPDATE guarantees SET state = 'expired' WHERE state = 'active' AND expiry_date < CURDATE()");
        $conn->query("UPDATE signing_authorities SET state = 'revoked' WHERE state = 'active' AND valid_to IS NOT NULL AND valid_to < CURDATE()");
        // تنبيه مبكر بحسب alert_days
        foreach (array(
            array('entity_licenses', 'lic_id', 'ترخيص'),
            array('guarantees', 'gtee_id', 'كفالة'),
        ) as $t) {
            $r = $conn->query(
                "SELECT {$t[1]} id, expiry_date FROM {$t[0]}
                  WHERE state = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL alert_days DAY)");
            while ($row = $r->fetch_assoc()) {
                $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                              VALUES (1, 'finance_manager', 'ExpiringSoon: {$t[2]} #{$row['id']} ينتهي في {$row['expiry_date']}', 'main/dashboard.php')");
                $alerts++;
            }
        }
        return $alerts;
    }

    /** ترقية نطاق (كيان/عقد) إلى نمط — قلب أعلام لا هجرة (G8). */
    public static function enablePattern(\mysqli $conn, $pattern, $scopeType, $scopeId, $reason, $actor)
    {
        $pattern = intval($pattern);
        if (!isset(self::PATTERN_ELEMENTS[$pattern])) {
            return array('ok' => false, 'code' => 422, 'reason' => 'الأنماط ②③④ — والنمط ① هو الافتراض (كله مطفأ)');
        }
        $n = 0;
        foreach (self::PATTERN_ELEMENTS[$pattern] as $el) {
            $stmt = $conn->prepare(
                'INSERT INTO governance_flags (element_code, scope_type, scope_id, enabled, reason, set_by)
                 VALUES (?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE enabled = 1, reason = VALUES(reason)');
            $st = (string) $scopeType; $sid = intval($scopeId); $rs = (string) $reason; $act = intval($actor);
            $stmt->bind_param('ssisi', $el, $st, $sid, $rs, $act);
            $stmt->execute();
            $stmt->close();
            $n++;
        }
        return array('ok' => true, 'code' => 200, 'elements' => $n,
            'reason' => 'النمط ' . $pattern . ' مفعل لنطاقه وحده — وبقية النطاقات كما هي (G7/G8)');
    }
}
