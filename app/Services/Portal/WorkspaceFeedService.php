<?php
/**
 * app/Services/Portal/WorkspaceFeedService.php — تغذيةُ المساحات (H-19)
 * ═══════════════════════════════════════════════════════════════════════════
 * WSP-01 §4: «لا مساحةَ تملك جدولًا ولا تحسب مؤشرًا: الحسابُ في خدمة مالكه
 * والعرضُ هنا» · §7-Validation: «كيانٌ خارج نطاق الصفة → 403 مسجَّلةً ·
 * بطاقةٌ بلا صلاحية → لا تُصيَّر · مؤشرٌ بلا تغذيةٍ → "غيرُ متاح" بمالكه
 * لا صفرًا · فترةٌ غيرُ صالحة → 422».
 *
 * الطبقاتُ المسموحة تُشتق من صفات H-15: «كلُّ طبقةٍ تظهر إن سمحت الصلاحيةُ
 * والنطاق — وما لا يُسمح لا يُعرض في المبدّل أصلًا» (§3).
 */

namespace App\Services\Portal;

require_once __DIR__ . '/CapacityService.php';

class WorkspaceFeedService
{
    const ENTITY_TYPES = array('department', 'project', 'supplier', 'client', 'equipment', 'person');

    // ═════════════════════════════════════════════════════════════════════
    // ① لوحةُ الدخول والطبقاتُ المسموحة (§5 · §3)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أين يُفتح الحسابُ أولًا؟ — «لوحةُ الدخول بحسب الحساب» (§5).
     * @return array{entity_type:string,entity_id:int}
     */
    public static function entryFor($capacity)
    {
        $ctype = (string) ($capacity['capacity_type'] ?? '');
        $scopeType = (string) ($capacity['scope_type'] ?? 'company');
        $scopeId = (int) ($capacity['scope_id'] ?? 0);
        switch ($ctype) {
            case 'project_manager':
                return array('entity_type' => 'project', 'entity_id' => $scopeId);
            case 'supplier_supervisor':
                return array('entity_type' => 'supplier', 'entity_id' => $scopeId);
            case 'client_rep':
                return array('entity_type' => 'client', 'entity_id' => $scopeId);
            case 'operator': case 'technician':
                // «مساحتُه الشخصية لأنها عملُه كلُّه» (§5)
                return array('entity_type' => 'person', 'entity_id' => (int) $capacity['account_id']);
            case 'executive':
                return array('entity_type' => 'department', 'entity_id' => 0); // مساحةُ الشركة المجمّعة
            default:
                if ($scopeType === 'project' && $scopeId > 0) {
                    return array('entity_type' => 'project', 'entity_id' => $scopeId);
                }
                return array('entity_type' => 'person', 'entity_id' => (int) $capacity['account_id']);
        }
    }

    /**
     * الطبقاتُ المسموحةُ للمبدّل — وما لا يُسمح **لا يُعرض أصلًا** (§3).
     * @return array<array{entity_type:string,entity_id:int,label:string}>
     */
    public static function allowedLayers($conn, $gate, $companyId, $accountId)
    {
        $layers = array(array('entity_type' => 'person', 'entity_id' => (int) $accountId, 'label' => 'أنا'));
        foreach (CapacityService::activeOf($conn, $gate, (int) $accountId) as $c) {
            if ((string) $c['state'] !== 'active') { continue; }
            $st = (string) $c['scope_type']; $sid = (int) $c['scope_id'];
            if ($st === 'project' && $sid > 0) {
                $layers[] = array('entity_type' => 'project', 'entity_id' => $sid, 'label' => 'مشروعي #' . $sid);
            } elseif ($st === 'supplier' && $sid > 0) {
                $layers[] = array('entity_type' => 'supplier', 'entity_id' => $sid, 'label' => 'موردي #' . $sid);
            } elseif ($st === 'client' && $sid > 0) {
                $layers[] = array('entity_type' => 'client', 'entity_id' => $sid, 'label' => 'عميلي #' . $sid);
            } elseif ($st === 'company') {
                $layers[] = array('entity_type' => 'department', 'entity_id' => 0, 'label' => 'شركتي');
            }
        }
        // إزالةُ المكرر
        $seen = array(); $out = array();
        foreach ($layers as $l) {
            $k = $l['entity_type'] . ':' . $l['entity_id'];
            if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $l; }
        }
        return $out;
    }

    /** هل الكيانُ داخل نطاق صفات الحساب؟ — الخارجيُّ في نطاقه حصرًا. */
    public static function inScope($conn, $gate, $companyId, $accountId, $entityType, $entityId)
    {
        foreach (self::allowedLayers($conn, $gate, $companyId, $accountId) as $l) {
            if ($l['entity_type'] === $entityType
                && ((int) $l['entity_id'] === (int) $entityId || $l['entity_type'] === 'department')) {
                return true;
            }
        }
        // الداخليُّ بنطاق الشركة يفتح مشاريعَ شركته ومعداتِها وأشخاصَها؛
        // **والخارجيُّ (نطاقُ مورد/عميل) لا يتجاوز نطاقَه** — fail-closed.
        $hasCompanyScope = false; $externalOnly = true;
        foreach (CapacityService::activeOf($conn, $gate, (int) $accountId) as $c) {
            if ((string) $c['state'] !== 'active') { continue; }
            if ((string) $c['scope_type'] === 'company' || (string) $c['scope_type'] === 'project') {
                $hasCompanyScope = true; $externalOnly = false;
            } else { /* supplier/client scope */ }
            if (!in_array((string) $c['capacity_type'],
                array('supplier_supervisor', 'client_rep'), true)) { $externalOnly = false; }
        }
        if ($externalOnly) { return false; }
        return $hasCompanyScope && in_array($entityType, array('project', 'equipment', 'person', 'department'), true);
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② التغذية
    // ═════════════════════════════════════════════════════════════════════

    /**
     * تغذيةُ مساحةٍ — WorkspaceDTO (§7).
     * @return array{ok:bool,code:int,reason:string,entity:array,cards:array,
     *               decisions:array,pulse:array,hidden_cards:array,breadcrumb:array}
     */
    public static function feed($conn, $gate, $companyId, $accountId, $entityType, $entityId, $period = 'today')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'entity' => array(),
                     'cards' => array(), 'decisions' => array(), 'pulse' => array(),
                     'hidden_cards' => array(), 'breadcrumb' => array());
        if (!in_array((string) $entityType, self::ENTITY_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'نوعُ كيانٍ مجهول'; return $out;
        }
        if (!in_array((string) $period, array('today', 'week', 'month'), true)) {
            $out['code'] = 422; $out['reason'] = 'فترةٌ غيرُ صالحة: today · week · month'; return $out;
        }

        // «كيانٌ خارج نطاق الصفة → 403 مسجَّلةً» (W3)
        if (!self::inScope($conn, $gate, $companyId, $accountId, (string) $entityType, (int) $entityId)) {
            self::logNav($conn, $companyId, $accountId, null,
                $entityType . ':' . $entityId, (string) $entityId, 'denied');
            $out['code'] = 403;
            $out['reason'] = 'الكيانُ خارج نطاق صفتك — **403 مسجَّلةٌ** والطبقةُ لا تظهر في مبدّلك أصلًا';
            return $out;
        }

        $co = (int) $companyId;
        $dateCond = $period === 'today' ? 'CURDATE()'
                  : ($period === 'week' ? 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)' : 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)');

        // التخطيطُ بالنوع (أحدثُ نسخة) — والبطاقاتُ من قاموسها
        $codes = array();
        $r = $conn->query("SELECT layout_json FROM workspace_layouts
                            WHERE entity_type='" . $conn->real_escape_string((string) $entityType) . "'
                            ORDER BY version DESC LIMIT 1");
        if ($r && ($row = $r->fetch_assoc())) {
            $codes = json_decode((string) $row['layout_json'], true) ?: array();
        }

        foreach ($codes as $code) {
            $card = null;
            $cr = $conn->query("SELECT * FROM workspace_cards
                                 WHERE code='" . $conn->real_escape_string((string) $code) . "' AND active=1 LIMIT 1");
            $meta = $cr ? $cr->fetch_assoc() : null;
            if (!$meta) { $out['hidden_cards'][] = (string) $code; continue; }

            $value = null; $unit = ''; $link = null; $unavailable = null;
            try {
                switch ((string) $code) {
                    case 'prod.units.period':
                        $w = $entityType === 'project' ? "AND project_id=" . (int) $entityId
                           : ($entityType === 'supplier' || $entityType === 'client' ? "AND 1=1" : "");
                        $q = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(qty),0),2) q FROM unit_entries
                                            WHERE company_id={$co} {$w} AND entry_date >= {$dateCond}");
                        $x = $q ? $q->fetch_assoc() : null;
                        $value = $x ? ($x['q'] . ' كمية · ' . $x['n'] . ' وحدة') : null;
                        $link = 'Operations/units.php' . ($entityType === 'project' ? ('?project_id=' . (int) $entityId) : '');
                        break;
                    case 'decisions.pending':
                        $q = $conn->query("SELECT COUNT(*) n FROM fin_requests
                                            WHERE company_id={$co} AND state IN ('submitted','under_review','pending_approval')");
                        $x = $q ? $q->fetch_assoc() : null;
                        $value = $x ? ($x['n'] . ' بانتظار قرار') : null;
                        $out['decisions'][] = array('box' => 'requests.approval', 'count' => $x ? (int) $x['n'] : 0,
                            'link' => 'Finance/requests_fin.php');
                        $link = 'Finance/requests_fin.php';
                        break;
                    case 'tickets.open':
                        $w = $entityType === 'project' ? "AND project_id=" . (int) $entityId
                           : ($entityType === 'equipment' ? "AND equipment_id=" . (int) $entityId : "");
                        $q = $conn->query("SELECT COUNT(*) n FROM tickets
                                            WHERE company_id={$co} {$w} AND close_date IS NULL");
                        $x = $q ? $q->fetch_assoc() : null;
                        $value = $x ? ($x['n'] . ' بلاغًا مفتوحًا') : null;
                        $link = 'Tickets/tickets_list.php';
                        break;
                    case 'claims.period':
                        $q = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(net_amount),0),2) v FROM claims
                                            WHERE company_id={$co} AND COALESCE(is_deleted,0)=0
                                              AND created_at >= {$dateCond}");
                        $x = $q ? $q->fetch_assoc() : null;
                        $value = $x ? ($x['n'] . ' مستخلصًا · ' . $x['v']) : null;
                        $link = 'Contracts/claims.php';
                        break;
                    case 'stops.by_owner':
                    case 'supplier.capacity':
                    case 'equipment.health':
                    case 'contract.commitment':
                    case 'person.achievement':
                        // «مؤشرٌ بلا تغذيةٍ من مالكه → يظهر "غيرُ متاح" بمالكه لا صفرًا» (W4)
                        $unavailable = 'غيرُ متاحٍ بعدُ — مالكُه ' . $meta['owner_doc']
                                     . ' (' . $meta['source_service'] . ')';
                        break;
                    case 'events.pulse':
                        $q = $conn->query("SELECT event_type, created_at FROM ems_business_events
                                            WHERE company_id={$co} ORDER BY id DESC LIMIT 5");
                        $pulse = array();
                        while ($q && ($x = $q->fetch_assoc())) {
                            $pulse[] = $x['event_type'] . ' · ' . $x['created_at'];
                        }
                        $out['pulse'] = $pulse;
                        $value = count($pulse) . ' أحداثٍ أخيرة';
                        $link = 'Finance/events_list.php';
                        break;
                    default:
                        $unavailable = 'غيرُ متاحٍ — بطاقةٌ بلا باني (مالكُها ' . $meta['owner_doc'] . ')';
                }
            } catch (\Throwable $t) { $unavailable = 'تعذّرت القراءةُ من المصدر'; }

            $out['cards'][] = array(
                'code' => (string) $code,
                'title' => (string) $meta['title_ar'],
                'value' => $unavailable === null ? (string) $value : null,
                'unavailable' => $unavailable,
                'owner_doc' => (string) $meta['owner_doc'],
                'period' => (string) $period,
                'source_link' => $link,
                'live' => (int) $meta['cache_ttl'] === 0,
            );
        }

        $out['entity'] = array('type' => (string) $entityType, 'id' => (int) $entityId);
        $out['breadcrumb'] = self::breadcrumb($entityType, $entityId);
        self::logNav($conn, $companyId, $accountId, null,
            $entityType . ':' . $entityId, (string) $entityId, 'ok');
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** فتاتُ الطريق — «مسارُ الطبقات ظاهرٌ دائمًا ويُنقر للرجوع» (§3). */
    public static function breadcrumb($entityType, $entityId)
    {
        $crumbs = array(array('label' => 'شركتي', 'entity_type' => 'department', 'entity_id' => 0));
        if ((string) $entityType !== 'department') {
            $crumbs[] = array('label' => (string) $entityType . ' #' . (int) $entityId,
                'entity_type' => (string) $entityType, 'entity_id' => (int) $entityId);
        }
        return $crumbs;
    }

    /** جناحُ الفريق — من يقع في نطاق المستخدم بحاله وآخرِ نشاطه (§3). */
    public static function teamWing($conn, $gate, $companyId, $accountId)
    {
        $team = array();
        $co = (int) $companyId; $acc = (int) $accountId;
        // مرؤوسوه المباشرون (users.parent_id) — علاقةُ H-17 نفسُها
        $r = $conn->query("SELECT u.id, u.name, u.status,
                                  (SELECT MAX(at) FROM portal_activity_log l WHERE l.account_id = u.id) last_at
                             FROM users u
                            WHERE u.company_id={$co} AND u.parent_id='{$acc}'
                              AND COALESCE(u.is_deleted,0)=0");
        while ($r && ($row = $r->fetch_assoc())) {
            $team[] = array('account_id' => (int) $row['id'], 'name' => (string) $row['name'],
                'status' => (string) $row['status'],
                'last_activity' => $row['last_at'] !== null ? (string) $row['last_at'] : '—');
        }
        return $team;
    }

    /** LayerSwitched — للقياس (W2)؛ Insert-only. */
    public static function logSwitch($conn, $companyId, $accountId, $fromLayer, $toLayer, $entityRef)
    {
        self::logNav($conn, $companyId, $accountId, $fromLayer, $toLayer, $entityRef, 'ok');
    }

    private static function logNav($conn, $companyId, $accountId, $fromLayer, $toLayer, $entityRef, $result)
    {
        $st = $conn->prepare("INSERT INTO workspace_navigation_log
            (company_id, account_id, from_layer, to_layer, entity_ref, result) VALUES (?,?,?,?,?,?)");
        if (!$st) { return; }
        $co = (int) $companyId; $acc = (int) $accountId;
        $st->bind_param('iissss', $co, $acc, $fromLayer, $toLayer, $entityRef, $result);
        $st->execute();
        $st->close();
    }
}
