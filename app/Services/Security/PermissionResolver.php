<?php
/**
 * PermissionResolver — محرك الاشتقاق (SEC-01 §12 خدمة ① · SEC-14/SEC-15)
 * ───────────────────────────────────────────────────────────────────────────
 * «الصلاحية تُشتق ولا تُمنح» (§1): يجمع الطبقات السبع ثم يطبّق deny فوقها:
 *   علاقة (سقف) + عائلة + مستوى + مسمًّى + جهة + نطاق + تكليف (ORG-01)
 *   + الاستثناءات المعتمدة + المنح الحساس − المنع الصريح = الفعلية.
 *
 * قواعد الدمج (§4.2): المنع يغلب المنح · الأقل سقفًا يسري · النطاقان
 * يتقاطعان ولا يتحدان · ولا يرفع الاستثناء سقفَ نوع العلاقة (إلا كسر زجاج).
 * Validation: صلاحية بلا نطاق → 422.
 * SEC-15: rebuild() يعيد بناء effective_permissions — جدول مشتق لا يُحرَّر.
 */

namespace App\Services\Security;

class PermissionResolver
{
    /**
     * قرار سماح/منع بمصدر الحكم.
     * @return array{allowed:bool, code:int, sources:array, denies:array,
     *               amount_cap:?float, scope:string, reason:string}
     */
    public static function resolve(\mysqli $conn, $personId, $companyId, $permissionCode, $scopeType = null, $scopeId = null, $date = null)
    {
        $out = array('allowed' => false, 'code' => 200, 'sources' => array(), 'denies' => array(),
            'amount_cap' => null, 'scope' => '', 'reason' => '');
        if ($scopeType === null || $scopeType === '' || $scopeId === null) {
            $out['code'] = 422;
            $out['reason'] = 'الصلاحية بلا نطاق مرفوضة بنيويا (SEC-01 §4.2)';
            return $out;
        }
        $out['scope'] = $scopeType . ':' . $scopeId;
        $date = $date ?: self::dbNow($conn);
        $all = self::gather($conn, intval($personId), intval($companyId), $date);

        $grants = array();
        $denies = array();
        $asgAny = false;      // أللشخص تكليفٌ يحمل هذه الصلاحية بأي نطاق؟
        $asgCovers = false;   // وهل يغطي أحدُ تكليفاته النطاقَ المطلوب؟
        foreach ($all as $s) {
            if ($s['permission_code'] !== $permissionCode && $s['permission_code'] !== '*') { continue; }
            $covers = self::scopeCovers($conn, $s['scope_rule'], (string) $scopeType, intval($scopeId), intval($companyId));
            if ($s['source_kind'] === 'assignment') {
                $asgAny = true;
                if ($covers) { $asgCovers = true; }
            }
            if (!$covers) { continue; }
            if ($s['effect'] === 'deny') { $denies[] = $s; continue; }
            $grants[] = $s;
        }

        // §4.2-③ التقاطع لا الاتحاد: قالب «كل المواقع» وتكليف «الموقع 18» ← الموقع 18 وحده.
        // فوجود تكليفٍ يحمل الصلاحيةَ يقيّد منحَ القوالب إلى نطاق التكليف (S17).
        if ($asgAny && !$asgCovers) {
            $out['denies'][] = array('source_kind' => 'assignment', 'source_ref' => 'scope_intersection',
                'note' => 'النطاق خارج تكليفه — النطاقان يتقاطعان ولا يتحدان (§4.2-③)');
            $out['reason'] = 'deny: النطاق المطلوب خارج نطاق التكليف — التقاطع لا الاتحاد';
            return $out;
        }

        // سقف نوع العلاقة: إن كان لقالب العلاقة محتوًى منشور فهو يحدّ ما يُسمح —
        // ولا يرفعه استثناء إلا كسر زجاج نافذ (§4.2-⑥)
        $ceiling = self::relationCeiling($conn, intval($personId), intval($companyId), $permissionCode, $date);
        if ($ceiling === 'blocked') {
            $bg = false;
            foreach ($grants as $g) {
                if ($g['source_kind'] === 'exception' && !empty($g['is_break_glass'])) { $bg = true; break; }
            }
            if (!$bg) {
                $out['denies'][] = array('source_kind' => 'relation', 'source_ref' => 'ceiling',
                    'note' => 'سقف نوع العلاقة يمنعها مهما منحت القوالب الأخرى');
                $out['reason'] = 'deny: سقف نوع العلاقة';
                return $out;
            }
        }

        if ($denies) {
            $out['denies'] = $denies;
            $out['reason'] = 'deny: المنع يغلب المنح دائما — من ' . $denies[0]['source_kind'] . ':' . $denies[0]['source_ref'];
            return $out;
        }
        if (!$grants) {
            $out['reason'] = 'لا مصدر يمنحها — الصلاحية نتيجة لا هبة';
            return $out;
        }
        // الأقل سقفًا يسري (§4.2-②): من السقوف المعرَّفة على المصادر المانحة
        $cap = null;
        foreach ($grants as $g) {
            if ($g['amount_cap'] !== null) {
                $cap = $cap === null ? floatval($g['amount_cap']) : min($cap, floatval($g['amount_cap']));
            }
        }
        $out['allowed'] = true;
        $out['sources'] = $grants;
        $out['amount_cap'] = $cap;
        $out['reason'] = 'منحت من ' . count($grants) . ' مصدرا — والنطاق تقاطع لا اتحاد';
        return $out;
    }

    /**
     * كل مصادر الشخص (منحًا ومنعًا) في تاريخ — الطبقات السبع + الاستثناءات + المنح الحساس.
     * @return array صفوف: {permission_code, scope_rule, amount_cap, effect, source_kind, source_ref, is_break_glass?}
     */
    public static function gather(\mysqli $conn, $personId, $companyId, $date)
    {
        $rows = array();
        $d = $conn->real_escape_string(substr($date, 0, 10));

        // ① المراكز النافذة → قوالب (علاقة كأرضية إن لم تكن سقفًا · عائلة · مستوى · مسمًّى)
        $res = $conn->query(
            "SELECT p.p_id, p.relation_code, p.family_code, p.level_code, p.title_code,
                    p.scope_type, p.scope_id
               FROM person_positions p
              WHERE p.person_id = {$personId} AND p.company_id = {$companyId}
                AND p.state = 'active' AND p.valid_from <= '{$d}'
                AND (p.valid_to IS NULL OR p.valid_to >= '{$d}')");
        $positions = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        foreach ($positions as $p) {
            $posScope = $p['scope_type'] . ':' . $p['scope_id'];
            foreach (array(array('relation', $p['relation_code']), array('family', $p['family_code']),
                           array('level', $p['level_code']), array('title', 'jt:' . $p['title_code'])) as $lay) {
                $kind = $lay[0];
                $key = $kind === 'title' ? $p['title_code'] : $lay[1];
                $tpl = self::publishedContent($conn, $kind, $key, $d);
                foreach ($tpl as $t) {
                    // النطاقان يتقاطعان: قاعدة القالب إن وُجدت وإلا نطاق المركز
                    $rows[] = array(
                        'permission_code' => $t['permission_code'],
                        'scope_rule' => self::intersectScope($t['scope_rule'], $posScope),
                        'amount_cap' => $t['amount_cap'],
                        'effect' => $t['effect'],
                        'source_kind' => $kind,
                        'source_ref' => $key . '@pos#' . $p['p_id'],
                    );
                }
            }
        }

        // ② التكليفات النافذة (ORG-01 §2⑦ — الطبقة السابعة)
        require_once dirname(__DIR__) . '/Org/OrgAuthorityResolver.php';
        foreach (\App\Services\Org\OrgAuthorityResolver::resolve($conn, $personId, $companyId, substr($date, 0, 10)) as $a) {
            foreach ($a['capabilities'] as $c) {
                $rows[] = array(
                    'permission_code' => $c['capability_code'],
                    'scope_rule' => $a['scope_type'] . ':' . $a['scope_id'],
                    'amount_cap' => $c['amount_cap'],
                    'effect' => 'grant',
                    'source_kind' => 'assignment',
                    'source_ref' => 'ASG-' . $a['asg_id'],
                );
            }
        }

        // ③ الاستثناءات النافذة (منحًا أو منعًا — وكسر الزجاج معلَّم)
        $res = $conn->query(
            "SELECT ex_id, permission_code, scope_rule, effect, is_break_glass
               FROM permission_exceptions
              WHERE person_id = {$personId} AND company_id = {$companyId} AND state = 'active'
                AND valid_from <= '{$date}' AND valid_to >= '{$date}'");
        while ($res && ($x = $res->fetch_assoc())) {
            $rows[] = array('permission_code' => $x['permission_code'], 'scope_rule' => $x['scope_rule'],
                'amount_cap' => null, 'effect' => $x['effect'], 'source_kind' => 'exception',
                'source_ref' => 'EX-' . $x['ex_id'], 'is_break_glass' => intval($x['is_break_glass']));
        }

        // ④ المنح الحساس الدائم (§1.1-②)
        $res = $conn->query(
            "SELECT gr_id, permission_code, scope_rule FROM sensitive_access_grants
              WHERE person_id = {$personId} AND company_id = {$companyId} AND state = 'active'
                AND granted_from <= '{$d}'");
        while ($res && ($x = $res->fetch_assoc())) {
            $rows[] = array('permission_code' => $x['permission_code'], 'scope_rule' => $x['scope_rule'],
                'amount_cap' => null, 'effect' => 'grant', 'source_kind' => 'grant',
                'source_ref' => 'GR-' . $x['gr_id']);
        }

        return $rows;
    }

    /**
     * SEC-15 · إعادة بناء المشتق — «يُعاد بناؤه عند أي تغيير في مصدر من مصادره».
     * @return int عدد الصفوف المبنية
     */
    public static function rebuild(\mysqli $conn, $personId, $companyId)
    {
        $personId = intval($personId);
        $companyId = intval($companyId);
        $now = self::dbNow($conn);
        $rows = self::gather($conn, $personId, $companyId, $now);
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM effective_permissions WHERE person_id = {$personId} AND company_id = {$companyId}");
            $stmt = $conn->prepare(
                "INSERT INTO effective_permissions (company_id, person_id, permission_code, scope_rule, amount_cap, source_kind, source_ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?)");
            $n = 0;
            // المنع لا يُخزن سطرًا فعليًّا — المشتق «ما يملكه» بعد الدمج
            $denyCodes = array();
            foreach ($rows as $r) { if ($r['effect'] === 'deny') { $denyCodes[$r['permission_code']] = true; } }
            foreach ($rows as $r) {
                if ($r['effect'] === 'deny' || isset($denyCodes[$r['permission_code']])) { continue; }
                $sk = $r['source_kind'] === 'grant' ? 'grant' : $r['source_kind'];
                $sr = (string) $r['scope_rule'];
                $cap = $r['amount_cap'] !== null ? floatval($r['amount_cap']) : null;
                $stmt->bind_param('isssdss', $companyId, $personId, $r['permission_code'], $sr, $cap, $sk, $r['source_ref']);
                $stmt->execute();
                $n++;
            }
            $stmt->close();
            $conn->commit();
            return $n;
        } catch (\Throwable $e) {
            $conn->rollback();
            return -1;
        }
    }

    /** محتوى النسخة المنشورة النافذة لقالب (kind, key). */
    public static function publishedContent(\mysqli $conn, $kind, $key, $date)
    {
        $stmt = $conn->prepare(
            "SELECT tp.permission_code, tp.scope_rule, tp.amount_cap, tp.effect
               FROM permission_templates t
               JOIN permission_template_versions v ON v.tpl_id = t.tpl_id AND v.state = 'published'
                AND (v.effective_from IS NULL OR v.effective_from <= ?)
                AND (v.effective_to IS NULL OR v.effective_to >= ?)
               JOIN template_permissions tp ON tp.template_version_id = v.ver_id
              WHERE t.tpl_kind = ? AND t.key_code = ? AND t.active = 1");
        $d = substr($date, 0, 10);
        $stmt->bind_param('ssss', $d, $d, $kind, $key);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** سقف العلاقة: 'blocked' إن كان لقالب علاقةٍ سقفيٍّ محتوًى منشورٌ لا يشمل الصلاحية. */
    private static function relationCeiling(\mysqli $conn, $personId, $companyId, $permissionCode, $date)
    {
        $d = substr($date, 0, 10);
        $res = $conn->query(
            "SELECT DISTINCT r.relation_code FROM person_relationships r
              WHERE r.person_id = {$personId} AND r.company_id = {$companyId} AND r.state = 'active'
                AND r.valid_from <= '{$d}' AND (r.valid_to IS NULL OR r.valid_to >= '{$d}')");
        $rels = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        foreach ($rels as $rel) {
            $content = self::publishedContent($conn, 'relation', $rel['relation_code'], $date);
            if (!$content) { continue; } // سقف بلا محتوى منشور = لا تقييد بعد
            $inCeiling = false;
            foreach ($content as $c) {
                if (($c['permission_code'] === $permissionCode || $c['permission_code'] === '*') && $c['effect'] === 'grant') {
                    $inCeiling = true;
                    break;
                }
            }
            if (!$inCeiling) { return 'blocked'; }
        }
        return 'open';
    }

    /** أتغطي قاعدةُ نطاق المصدر النطاقَ المطلوب؟ — التقاطع لا الاتحاد. */
    public static function scopeCovers(\mysqli $conn, $scopeRule, $reqType, $reqId, $companyId)
    {
        if ($scopeRule === null || $scopeRule === '' || $scopeRule === '*') { return true; }
        foreach (explode('|', (string) $scopeRule) as $piece) {
            $piece = trim($piece);
            if ($piece === '') { continue; }
            $parts = explode(':', $piece, 2);
            $sType = $parts[0];
            $sId = isset($parts[1]) ? intval($parts[1]) : 0;
            if ($sType === $reqType && ($sId === intval($reqId) || $sId === 0)) { return true; }
            if ($sType === 'company') { return true; }
            if ($sType === 'project' && $reqType === 'site') {
                $r = $conn->query("SELECT project_id FROM sites WHERE id = " . intval($reqId) . " LIMIT 1");
                $row = $r ? $r->fetch_assoc() : null;
                if ($row && intval($row['project_id']) === $sId) { return true; }
            }
            if ($sType === 'site_group' && $sId === 0 && $reqType === 'site') { return true; }
        }
        return false;
    }

    /** تقاطع قاعدة القالب مع نطاق المركز — الأضيق يسري. */
    private static function intersectScope($templateRule, $positionScope)
    {
        if ($templateRule === null || $templateRule === '' || $templateRule === '*') { return $positionScope; }
        // قاعدة قالب أضيق أو مخصصة تبقى كما هي — والتغطية الفعلية تفحصها scopeCovers
        return $templateRule;
    }

    public static function dbNow(\mysqli $conn)
    {
        $r = $conn->query('SELECT NOW() n')->fetch_assoc();
        return $r['n'];
    }
}
