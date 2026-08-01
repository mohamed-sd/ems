<?php
/**
 * app/Services/Portal/VisibilityPolicyService.php — سياسةُ الظهور (H-16)
 * ═══════════════════════════════════════════════════════════════════════════
 * ADM-01 §2: «الحسابُ يغلب الفئة، والفئةُ تغلب الإدارة/المشروع، وهذه تغلب
 * المورد/العميل، وما لم يُضبط موروثٌ من سياسة الفئة، وما لا سياسةَ له مغلقٌ
 * افتراضيًّا — قاعدةٌ واحدةٌ لا اجتهادَ فيها» · «الحساسُ لا يُفتح إلا بمنحٍ
 * مؤقتٍ بمدةٍ وسببٍ إلزامي» · «لا تغييرَ صامت».
 *
 * ── أربعُ قواعدَ تحكم كلَّ قرارٍ هنا ────────────────────────────────────────
 * ① **ما ليس في القاموس لا يُصيَّر أصلًا** — والضبطُ عليه 422.
 * ② **الأولويةُ مرمَّزةٌ أرقامًا لا اجتهادًا**: 1 الحساب · 2 الفئة ·
 *    3 الإدارة/المشروع · 4 المورد/العميل — الأدنى يغلب، والافتراضُ للعنصر.
 * ③ **الحساسُ بمدةٍ وسبب** (D2) — و**منحُ الذات 403 مسجَّلةً** (D4).
 * ④ **الانتهاءُ كسولٌ** (D5): المنحةُ المنتهية **مغلقةٌ فور القراءة**
 *    ويُقيَّد `GrantExpired` — لا كرونَ يُنتظر.
 */

namespace App\Services\Portal;

require_once __DIR__ . '/CapacityService.php';

class VisibilityPolicyService
{
    const SCOPES = array('account', 'capacity_type', 'department', 'project', 'supplier', 'client');

    /** الأولويةُ المحسومة — الأدنى يغلب (ADM-01 §2 نصًّا) */
    const SCOPE_PRIORITY = array(
        'account' => 1, 'capacity_type' => 2, 'department' => 3,
        'project' => 3, 'supplier' => 4, 'client' => 4,
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① الضبط
    // ═════════════════════════════════════════════════════════════════════

    /**
     * ضبطُ مفتاحٍ (upsert على الفريد) — بحرّاس D2/D4.
     *
     * @param array $args {element_code, scope_type, scope_id, mode, reason, expires_at?}
     * @return array{ok:bool,code:int,reason:string,affected:int}
     */
    public static function setKey($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'affected' => 0);
        $code = trim((string) ($args['element_code'] ?? ''));
        $scopeType = trim((string) ($args['scope_type'] ?? ''));
        $scopeId = trim((string) ($args['scope_id'] ?? ''));
        $mode = trim((string) ($args['mode'] ?? ''));
        $reason = trim((string) ($args['reason'] ?? ''));
        $expires = trim((string) ($args['expires_at'] ?? ''));

        // ① القاموسُ الحكم
        $el = self::element($conn, $code);
        if (!$el || !(int) $el['active']) {
            $out['code'] = 422;
            $out['reason'] = 'العنصرُ «' . $code . '» خارجَ القاموس أو موقوف — '
                           . '**وما ليس في القاموس لا يُصيَّر أصلًا** (ADM-01 §2)';
            return $out;
        }
        if (!in_array($scopeType, self::SCOPES, true) || $scopeId === '') {
            $out['code'] = 422; $out['reason'] = 'نطاقٌ ناقص — النوعُ من الستة ومعرّفُه إلزامي'; return $out;
        }
        if (!in_array($mode, array('open', 'closed', 'inherit'), true)) {
            $out['code'] = 422; $out['reason'] = 'الوضعُ: open · closed · inherit'; return $out;
        }
        if ($mode !== 'inherit' && $reason === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببٌ إلزاميٌّ لكل تغييرٍ** — «لا تغييرَ صامتٌ على خصوصية أحد» (ADM-01)';
            return $out;
        }

        // D2: الحساسُ لا يُفتح إلا بمدةٍ وسبب — ولا منحَ دائمًا
        if ((string) $el['sensitivity'] === 'sensitive' && $mode === 'open') {
            if ($expires === '' || strtotime($expires) === false) {
                $out['code'] = 422;
                $out['reason'] = '**الحساسُ يتطلب مدةً وسببًا** — منحٌ مؤقتٌ ينتهي آليًّا لا فتحٌ دائم (D2)';
                return $out;
            }
            if (strtotime($expires) <= time()) {
                $out['code'] = 422; $out['reason'] = 'مدةُ المنح في الماضي — لا منحَ ميتًا'; return $out;
            }
        }

        // D4: منعُ منح الذات — بنيويًّا ومسجَّلًا
        if ($scopeType === 'account' && (int) $scopeId === (int) $actor && $mode === 'open') {
            self::logRow($conn, $companyId, $code, $scopeType, $scopeId, null, 'denied_self',
                $actor, 'محاولةُ منح الذات — مرفوضةٌ بنيويًّا (D4)', null, 0);
            $out['code'] = 403;
            $out['reason'] = '**لا يمنح الفاعلُ نفسَه ظهورًا** — 403 مسجَّلةٌ (ADM-01 D4)';
            return $out;
        }

        // نطاقُ الحساب يلزم وجودُه (404) — والفئةُ من قاموس H-15
        if ($scopeType === 'account') {
            $r = $conn->query("SELECT id FROM users WHERE id = " . (int) $scopeId . " LIMIT 1");
            if (!$r || !$r->fetch_assoc()) { $out['code'] = 404; $out['reason'] = 'الحسابُ غيرُ موجود'; return $out; }
        }
        if ($scopeType === 'capacity_type'
            && !in_array($scopeId, CapacityService::CAPACITY_TYPES, true)) {
            $out['code'] = 404; $out['reason'] = 'فئةُ صفةٍ مجهولة — القاموسُ في H-15'; return $out;
        }

        // القراءةُ قبل الكتابة — للسجل from_mode
        $old = null;
        try {
            $old = $gate->selectOne('visibility_keys', array('where' => array(
                'element_code' => $code, 'scope_type' => $scopeType, 'scope_id' => $scopeId)));
        } catch (\Throwable $t) { $old = null; }

        try {
            if ($old) {
                $gate->update('visibility_keys', array(
                    'mode' => $mode, 'reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
                    'granted_by' => (int) $actor, 'granted_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : null,
                ), array('id' => (int) $old['id']));
            } else {
                $gate->insert('visibility_keys', array(
                    'company_id' => (int) $companyId,
                    'element_code' => $code, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                    'mode' => $mode, 'reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
                    'granted_by' => (int) $actor,
                    'expires_at' => $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : null,
                ));
            }
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الضبط: ' . $t->getMessage(); return $out;
        }

        // «معاينةُ الأثر»: عددُ الحسابات المتأثرة بالنطاق — يُسجَّل مع الحدث
        $affected = self::affectedCount($conn, $companyId, $scopeType, $scopeId);
        self::logRow($conn, $companyId, $code, $scopeType, $scopeId,
            $old ? (string) $old['mode'] : null, $mode, $actor,
            $reason !== '' ? $reason : null,
            $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : null, $affected);

        $out['ok'] = true; $out['code'] = 200; $out['affected'] = $affected;
        return $out;
    }

    /** عددُ الحسابات التي يطالها النطاق — «سيتأثر N حسابًا» */
    public static function affectedCount($conn, $companyId, $scopeType, $scopeId)
    {
        $co = (int) $companyId;
        switch ($scopeType) {
            case 'account':
                return 1;
            case 'capacity_type':
                $q = "SELECT COUNT(DISTINCT account_id) n FROM user_capacities
                       WHERE company_id={$co} AND capacity_type='" . $conn->real_escape_string($scopeId) . "'
                         AND state='active'";
                break;
            case 'project':
                $q = "SELECT COUNT(DISTINCT account_id) n FROM user_capacities
                       WHERE company_id={$co} AND scope_type='project'
                         AND scope_id=" . (int) $scopeId . " AND state='active'";
                break;
            case 'supplier':
                $q = "SELECT COUNT(DISTINCT account_id) n FROM user_capacities
                       WHERE company_id={$co} AND scope_type='supplier'
                         AND scope_id=" . (int) $scopeId . " AND state='active'";
                break;
            default:
                return 0;
        }
        try { $r = $conn->query($q); $row = $r ? $r->fetch_assoc() : null; return $row ? (int) $row['n'] : 0; }
        catch (\Throwable $t) { return 0; }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القرار — فضُّ الأولوية بمصدره
    // ═════════════════════════════════════════════════════════════════════

    /**
     * القرارُ النافذ لعنصرٍ × سياقِ حساب.
     *
     * @param array $ctx {account_id, capacity_type?, project_id?, supplier_id?,
     *                    client_id?, department?}
     * @return array{visible:bool,mode:string,source:string,reason:?string}
     */
    public static function decide($conn, $gate, $companyId, $elementCode, array $ctx)
    {
        $el = self::element($conn, (string) $elementCode);
        // ① ما ليس في القاموس لا يُصيَّر أصلًا
        if (!$el || !(int) $el['active']) {
            return array('visible' => false, 'mode' => 'closed',
                'source' => 'not_in_dictionary', 'reason' => 'خارجَ القاموس — لا يُصيَّر');
        }

        // نطاقاتُ السياق ⇒ مفاتيحُها المضبوطة
        $wanted = array();
        if (!empty($ctx['account_id']))    { $wanted[] = array('account', (string) (int) $ctx['account_id']); }
        if (!empty($ctx['capacity_type'])) { $wanted[] = array('capacity_type', (string) $ctx['capacity_type']); }
        if (!empty($ctx['department']))    { $wanted[] = array('department', (string) $ctx['department']); }
        if (!empty($ctx['project_id']))    { $wanted[] = array('project', (string) (int) $ctx['project_id']); }
        if (!empty($ctx['supplier_id']))   { $wanted[] = array('supplier', (string) (int) $ctx['supplier_id']); }
        if (!empty($ctx['client_id']))     { $wanted[] = array('client', (string) (int) $ctx['client_id']); }

        $best = null; $bestPrio = 99;
        foreach ($wanted as $w) {
            $k = null;
            try {
                $k = $gate->selectOne('visibility_keys', array('where' => array(
                    'element_code' => (string) $elementCode,
                    'scope_type' => $w[0], 'scope_id' => $w[1])));
            } catch (\Throwable $t) { $k = null; }
            if (!$k || (string) $k['mode'] === 'inherit') { continue; }

            // D5: الانتهاءُ الكسول — المنتهي مغلقٌ فورًا ويُقيَّد
            if ((string) $k['mode'] === 'open' && $k['expires_at'] !== null
                && strtotime((string) $k['expires_at']) <= time()) {
                try {
                    $gate->update('visibility_keys',
                        array('mode' => 'closed',
                              'reason' => 'انتهت مدةُ المنح — أُغلق آليًّا (GrantExpired)'),
                        array('id' => (int) $k['id']));
                    self::logRow($conn, $companyId, (string) $elementCode, $w[0], $w[1],
                        'open', 'grant_expired', 0,
                        'انتهاءُ مدةِ منحٍ — إغلاقٌ آليٌّ بلا تدخل (D5)', null, 0);
                } catch (\Throwable $t) { /* الإغلاقُ المنطقي ماضٍ ولو تعذّر القيد */ }
                $k['mode'] = 'closed';
            }

            $p = self::SCOPE_PRIORITY[$w[0]];
            if ($p < $bestPrio) { $bestPrio = $p; $best = array('key' => $k, 'scope' => $w[0]); }
        }

        if ($best !== null) {
            $mode = (string) $best['key']['mode'];
            return array('visible' => $mode === 'open', 'mode' => $mode,
                'source' => $best['scope'],
                'reason' => $best['key']['reason'] !== null ? (string) $best['key']['reason'] : null);
        }

        // ما لا سياسةَ له: افتراضُ العنصر — والحساسُ مبذورٌ مغلقًا
        $dm = (string) $el['default_mode'];
        return array('visible' => $dm === 'open', 'mode' => $dm,
            'source' => 'element_default', 'reason' => null);
    }

    /** «ماذا يرى هذا الحساب؟» — القرارُ لكل عنصرٍ بمصدره */
    public static function simulate($conn, $gate, $companyId, array $ctx)
    {
        $out = array();
        $r = $conn->query("SELECT element_code FROM portal_elements WHERE active=1 ORDER BY element_code");
        while ($r && ($row = $r->fetch_assoc())) {
            $code = (string) $row['element_code'];
            $out[$code] = self::decide($conn, $gate, $companyId, $code, $ctx);
        }
        return $out;
    }

    /** «من يرى هذا العنصر؟» — الحساباتُ النشطةُ التي قرارُها مفتوح */
    public static function whoSees($conn, $gate, $companyId, $elementCode)
    {
        $sees = array();
        $caps = array();
        try {
            $caps = $gate->scopedQuery(array('scope' => array('c' => 'user_capacities')),
                "SELECT c.account_id, c.capacity_type, c.scope_type, c.scope_id
                   FROM user_capacities c WHERE {TENANT_SCOPE} AND c.state = 'active'");
        } catch (\Throwable $t) { $caps = array(); }
        foreach ($caps as $c) {
            $ctx = array('account_id' => (int) $c['account_id'],
                         'capacity_type' => (string) $c['capacity_type']);
            if ((string) $c['scope_type'] === 'project')  { $ctx['project_id'] = (int) $c['scope_id']; }
            if ((string) $c['scope_type'] === 'supplier') { $ctx['supplier_id'] = (int) $c['scope_id']; }
            if ((string) $c['scope_type'] === 'client')   { $ctx['client_id'] = (int) $c['scope_id']; }
            $d = self::decide($conn, $gate, $companyId, $elementCode, $ctx);
            if ($d['visible']) {
                $sees[(int) $c['account_id']] = array('account_id' => (int) $c['account_id'],
                    'capacity_type' => (string) $c['capacity_type'], 'source' => $d['source']);
            }
        }
        return array_values($sees);
    }

    // ═════════════════════════════════════════════════════════════════════
    // مرافق
    // ═════════════════════════════════════════════════════════════════════

    public static function element($conn, $code)
    {
        $code = $conn->real_escape_string((string) $code);
        try {
            $r = $conn->query("SELECT * FROM portal_elements WHERE element_code = '{$code}' LIMIT 1");
            return $r ? $r->fetch_assoc() : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function elements($conn)
    {
        $out = array();
        $r = $conn->query("SELECT * FROM portal_elements ORDER BY sensitivity DESC, element_code");
        while ($r && ($row = $r->fetch_assoc())) { $out[] = $row; }
        return $out;
    }

    public static function keys($gate, $limit = 500)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('k' => 'visibility_keys')),
                "SELECT k.* FROM visibility_keys k WHERE {TENANT_SCOPE}
                  ORDER BY k.element_code, k.scope_type, k.scope_id LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    public static function auditLog($gate, $limit = 300)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'visibility_audit_log')),
                "SELECT l.* FROM visibility_audit_log l WHERE {TENANT_SCOPE}
                  ORDER BY l.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    /** السجلُّ Insert-only — كتابةٌ مباشرةٌ بلا تعديلٍ ولا حذفٍ لاحق */
    private static function logRow($conn, $companyId, $code, $scopeType, $scopeId,
                                   $fromMode, $toMode, $actor, $reason, $expiresAt, $affected)
    {
        $st = $conn->prepare("INSERT INTO visibility_audit_log
            (company_id, element_code, scope_type, scope_id, from_mode, to_mode,
             actor, reason, expires_at, affected_count)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        if (!$st) { return; }
        $co = (int) $companyId; $ac = (int) $actor; $af = (int) $affected;
        $st->bind_param('isssssissi', $co, $code, $scopeType, $scopeId,
            $fromMode, $toMode, $ac, $reason, $expiresAt, $af);
        $st->execute();
        $st->close();
    }
}
