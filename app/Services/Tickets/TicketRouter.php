<?php
/**
 * TicketRouter — التوجيه الجدولي والفتح السياقي (TKT-01 §4 · §12 خدمة ① · TKT-07)
 * ───────────────────────────────────────────────────────────────────────────
 * «يفتح المسارات الفورية وحدها عند الإنشاء · والشرطية تبقى pending حتى حدث
 * تفعيلها · ويحل المكلف من التكليفات النافذة في ORG-01 بحسب (الموقع × التاريخ
 * × التكليف × النائب × النطاق) — لا من شخص أو وحدة ثابتة في جدول النوع».
 * Validation: نوع غير معرف → 422 · سياق ناقص من شاشة تشغيلية → 422 ·
 * ولا مكلف نافذ → يُسند إلى مدير الحركة بالموقع ويُنبَّه المركز.
 */

namespace App\Services\Tickets;

require_once dirname(__DIR__) . '/Org/OrgAuthorityResolver.php';
require_once dirname(__DIR__) . '/Org/DeputyResolver.php';

use App\Services\Org\OrgAuthorityResolver;

class TicketRouter
{
    /** خريطة دور المسار → أنواع التكليف الحاملة له (موقعيًّا فمركزيًّا) — كمصفوفة PermitGate. */
    public static $roleTypes = array(
        'movement' => array('site_movement_mgr'),
        'maintenance' => array('site_maintenance_officer', 'maintenance_mgr'),
        'operators' => array('site_operators_officer', 'operators_mgr'),
        'warehouse' => array('site_warehouse_keeper', 'warehouse_mgr'),
        'procurement' => array('site_procurement_receiver', 'procurement_ops_mgr'),
        'transport' => array('site_transport_coordinator', 'transport_mgr'),
    );
    /** المجالات بلا أنواع تكليف → users.role. */
    public static $roleFallback = array(
        'hr' => array('4'), 'governance' => array('15'), 'support' => array('24'), 'fleet' => array('3'),
    );

    /**
     * إنشاء بلاغ موجَّه — من زر الإبلاغ السياقي.
     * @param array $d {company_id, type_code|ticket_type_id, description, reporter_person_id,
     *   context:{screen, site_id?, equipment_id?, contract_id?, shift_no?, period_no?, entity_type?, entity_id?},
     *   priority?, is_anonymous?, private_details?}
     */
    public static function create(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'tk_id' => 0, 'priority' => null,
            'routed' => array(), 'pending_conditional' => 0);
        $co = intval($d['company_id'] ?? 0);

        // النوع — 422 لغير المعرف
        $type = null;
        if (!empty($d['type_code'])) {
            $stmt = $conn->prepare("SELECT * FROM ticket_types WHERE code = ? AND active = 1 LIMIT 1");
            $stmt->bind_param('s', $d['type_code']);
            $stmt->execute();
            $type = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } elseif (!empty($d['ticket_type_id'])) {
            $type = $conn->query("SELECT * FROM ticket_types WHERE id = " . intval($d['ticket_type_id']) . " AND active = 1")->fetch_assoc();
        }
        if (!$type) { $out['code'] = 422; $out['reason'] = 'نوع بلاغ غير معرَّف — يُضاف صفًّا لا كودًا'; return $out; }

        // السياق المحمول — شاشة تشغيلية بلا سياق = مخالفة تُعاد (§2)
        $ctx = isset($d['context']) && is_array($d['context']) ? $d['context'] : array();
        $screen = isset($ctx['screen']) ? (string) $ctx['screen'] : '';
        $operationalScreens = array('timesheet', 'movement', 'maintenance', 'warehouse', 'transport', 'operators');
        if (in_array($screen, $operationalScreens, true) && empty($ctx['site_id']) && empty($ctx['equipment_id'])) {
            $out['code'] = 422;
            $out['reason'] = 'سياق ناقص من شاشة تشغيلية — البلاغ يحمل سياقه ولا يُدخل المستخدم ما يعرفه النظام';
            return $out;
        }

        // الأولوية: افتراض النوع · وترتفع آليًّا لمعدة في مقعد تعاقدي · والمبلغ يرفعها ولا يخفضها
        $priority = (string) $type['default_priority'];
        if (!empty($ctx['equipment_id'])) {
            $seat = $conn->query("SELECT 1 FROM seat_assignments WHERE equipment_id = " . intval($ctx['equipment_id'])
                . " AND (end_date IS NULL OR end_date >= CURDATE()) LIMIT 1");
            if ($seat && $seat->num_rows > 0) { $priority = 'critical'; }
        }
        if ((string) $type['nature'] === 'emergency') { $priority = 'critical'; }
        $rank = array('normal' => 1, 'high' => 2, 'critical' => 3);
        if (!empty($d['priority']) && isset($rank[$d['priority']]) && $rank[$d['priority']] > $rank[$priority]) {
            $priority = (string) $d['priority']; // يرفعها ولا يخفضها
        }

        $siteId = intval($ctx['site_id'] ?? 0);
        $conn->begin_transaction();
        try {
            // رقم البلاغ من أعلى لاحقةٍ رقميةٍ قائمةٍ +1 (لا COUNT — فالحذف يوقعه في تصادم UQ)
            $prefix = date('y-m') . '-';
            $maxRow = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(ticket_no, '-', -1) AS UNSIGNED)) mx
                                     FROM tickets WHERE ticket_no LIKE '" . $conn->real_escape_string($prefix) . "%'
                                       AND ticket_no REGEXP '[0-9]+$'")->fetch_assoc();
            $seq = max(8000, intval($maxRow['mx'] ?? 0)) + 1;
            $no = $prefix . $seq;
            $stmt = $conn->prepare(
                "INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority,
                    confidentiality, business_impact, call_date, reporting_person, reporter_user_id, is_anonymous,
                    complaint, operational_summary, private_details, source_screen, source_entity_type, source_entity_id,
                    project_id, site_id, contract_id, shift_no, period_no, equipment_id, owner_role_id, head_state, created_by)
                 SELECT ?, ?, ?, 'new', ?, ?, ?, 'admin', CURDATE(), COALESCE(u.name, 'مجهول'), ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?
                 FROM (SELECT 1) x LEFT JOIN users u ON u.id = ?");
            $legacyNature = in_array($type['nature'], array('incident', 'emergency'), true) ? 'incident'
                : ($type['nature'] === 'problem' ? 'recurring' : 'request');
            $reporter = intval($d['reporter_person_id'] ?? 0);
            $anon = !empty($d['is_anonymous']) && intval($type['allow_anonymous']) === 1 ? 1 : 0;
            $desc = (string) ($d['description'] ?? '');
            $summary = mb_substr((string) ($d['operational_summary'] ?? $desc), 0, 250);
            $private = isset($d['private_details']) ? (string) $d['private_details'] : null;
            if ((string) $type['default_confidentiality'] !== 'normal' && $private === null) {
                // الفصل البنيوي: طبيعة محمية بلا فصل = التفاصيل كلها في الحقل الخاص والملخص عام
                $private = $desc;
                $desc = 'بلاغ ' . $type['name'];
                $summary = 'غير متاح — يحتاج معالجة مختصة';
            }
            $entityType = isset($ctx['entity_type']) ? (string) $ctx['entity_type'] : null;
            $entityId = isset($ctx['entity_id']) ? intval($ctx['entity_id']) : null;
            $projectId = isset($ctx['project_id']) ? intval($ctx['project_id']) : null;
            $contractId = isset($ctx['contract_id']) ? intval($ctx['contract_id']) : null;
            $shiftNo = isset($ctx['shift_no']) ? intval($ctx['shift_no']) : null;
            $periodNo = isset($ctx['period_no']) ? intval($ctx['period_no']) : null;
            $equipmentId = isset($ctx['equipment_id']) ? intval($ctx['equipment_id']) : null;
            $typeId = intval($type['id']);
            $ownerRole = intval($type['owner_role_id']);
            $conf = (string) $type['default_confidentiality'];
            $stmt->bind_param('isissssiissssiiiiiiiiii',
                $co, $no, $typeId, $legacyNature, $priority, $conf, $reporter, $anon,
                $desc, $summary, $private, $screen, $entityType, $entityId,
                $projectId, $siteId, $contractId, $shiftNo, $periodNo, $equipmentId, $ownerRole, $reporter, $reporter);
            if (!$stmt->execute()) { throw new \Exception('فشل إدراج الرأس: ' . $conn->error); }
            $tkId = intval($conn->insert_id);
            $stmt->close();

            // المشاركون: المبلغ
            if ($reporter > 0) {
                $conn->query("INSERT IGNORE INTO ticket_participants (tk_id, person_id, role) VALUES ({$tkId}, {$reporter}, 'reporter')");
            }

            // المسارات: الفورية تُفتح والشرطية pending
            $defs = $conn->query("SELECT * FROM ticket_type_workstreams WHERE ticket_type_id = {$typeId} ORDER BY ws_def_id");
            while ($defs && ($w = $defs->fetch_assoc())) {
                $isImmediate = $w['activation_mode'] === 'immediate';
                $assignee = null;
                if ($isImmediate) {
                    $assignee = self::resolveAssignee($conn, $co, (string) $w['target_role'], $siteId);
                }
                $unitId = null;
                if (!empty($w['target_org_unit_code'])) {
                    $u = $conn->query("SELECT unit_id FROM org_units WHERE company_id = {$co} AND unit_code = '"
                        . $conn->real_escape_string($w['target_org_unit_code']) . "' LIMIT 1")->fetch_assoc();
                    $unitId = $u ? intval($u['unit_id']) : null;
                }
                $respDue = $isImmediate && $w['response_sla_minutes'] !== null
                    ? "DATE_ADD(NOW(), INTERVAL " . intval($w['response_sla_minutes']) . " MINUTE)" : 'NULL';
                $resolveDue = $isImmediate && $w['resolve_sla_minutes'] !== null
                    ? "DATE_ADD(NOW(), INTERVAL " . intval($w['resolve_sla_minutes']) . " MINUTE)" : 'NULL';
                $conn->query(
                    "INSERT INTO ticket_workstreams (tk_id, workstream_type, seq_no, org_unit_id, assignee_person_id,
                        mandatory, state, activation_state, response_due_at, resolve_due_at)
                     VALUES ({$tkId}, '" . $conn->real_escape_string($w['workstream_type']) . "', " . intval($w['seq_no']) . ",
                        " . ($unitId !== null ? $unitId : 'NULL') . ", " . ($assignee !== null ? intval($assignee) : 'NULL') . ",
                        " . intval($w['mandatory']) . ", 'new', '" . ($isImmediate ? 'opened' : 'pending') . "',
                        {$respDue}, {$resolveDue})");
                $wsId = intval($conn->insert_id);
                if ($isImmediate) {
                    $out['routed'][] = array('ws_id' => $wsId, 'type' => $w['workstream_type'],
                        'assignee' => $assignee, 'mandatory' => intval($w['mandatory']));
                    if ($assignee === null) {
                        // لا مكلف نافذ → مدير الحركة بالموقع ويُنبَّه المركز
                        $fallback = self::resolveAssignee($conn, $co, 'movement', $siteId);
                        if ($fallback !== null) {
                            $conn->query("UPDATE ticket_workstreams SET assignee_person_id = " . intval($fallback) . " WHERE ws_id = {$wsId}");
                        }
                        $stmt2 = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                                                 VALUES (?, 'all', ?, 'Tickets/tickets_list.php')");
                        $title = 'بلاغ #' . $tkId . ': لا مكلف نافذ لمسار ' . $w['workstream_type'] . ' — أُسند لمدير الحركة';
                        $stmt2->bind_param('is', $co, $title);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                } else {
                    $out['pending_conditional']++;
                }
            }
            // T9 · بلاغ السلامة/الطارئ: إيقاف فوري بنيوي + تصعيد مباشر للإدارة العامة
            if ((string) $type['nature'] === 'emergency') {
                $ws1 = $conn->query("SELECT ws_id FROM ticket_workstreams WHERE tk_id = {$tkId} AND mandatory = 1 LIMIT 1")->fetch_assoc();
                if ($ws1) {
                    $wsId1 = intval($ws1['ws_id']);
                    $conn->query("INSERT INTO ticket_escalations (ws_id, level, triggered_by, to_person_id)
                                  VALUES ({$wsId1}, 'exec', 'safety', NULL)");
                    $conn->query("INSERT INTO ticket_effects (ws_id, effect_type, effect_ref, is_provisional)
                                  VALUES ({$wsId1}, 'decision', 'SAFETY-STOP-TK{$tkId}', 0)");
                }
                $stmt2 = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                                         VALUES (?, 'all', ?, 'Tickets/tickets_list.php')");
                $title = 'طارئ سلامة #' . $tkId . ': إيقاف فوري بنيوي وتصعيد مباشر للإدارة العامة — لا يُغلق إلا بموافقة السلامة';
                $stmt2->bind_param('is', $co, $title);
                $stmt2->execute();
                $stmt2->close();
            }
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['tk_id'] = $tkId; $out['priority'] = $priority;
            $out['reason'] = 'أُنشئ ووُجّه آليًّا خلال ثانية — ' . count($out['routed']) . ' مسار فوري و'
                . $out['pending_conditional'] . ' شرطي pending';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
    }

    /**
     * حل المكلف من تكليفات ORG-01 النافذة (الموقع × التاريخ × النوع × النائب) —
     * الموقعي لموقعه ثم المركزي، والغائب يفعّل نائبه.
     */
    public static function resolveAssignee(\mysqli $conn, $companyId, $role, $siteId)
    {
        if ($role === null || $role === '') { return null; }
        if (isset(self::$roleTypes[$role])) {
            $types = "'" . implode("','", self::$roleTypes[$role]) . "'";
            $today = OrgAuthorityResolver::dbToday($conn);
            // الموقعي المطابق أولًا ثم المركزي (site_group:0) ثم مشروع يغطي الموقع
            $res = $conn->query(
                "SELECT a.asg_id, a.person_id, a.deputy_person_id, a.state, a.scope_type, a.scope_id
                   FROM org_assignments a
                  WHERE a.company_id = " . intval($companyId) . "
                    AND a.assignment_type_code IN ({$types})
                    AND a.state IN ('active','suspended')
                    AND '{$today}' BETWEEN a.valid_from AND a.valid_to
                  ORDER BY (a.scope_type = 'site' AND a.scope_id = " . intval($siteId) . ") DESC,
                           (a.scope_type = 'site_group') DESC, a.asg_id");
            while ($res && ($a = $res->fetch_assoc())) {
                $covers = ($a['scope_type'] === 'site' && intval($a['scope_id']) === intval($siteId))
                    || ($a['scope_type'] === 'site_group' && intval($a['scope_id']) === 0)
                    || OrgAuthorityResolver::scopeCovers($conn, $a, 'site', intval($siteId));
                if (!$covers && $siteId > 0) { continue; }
                if ($a['state'] === 'active') { return intval($a['person_id']); }
                if ($a['deputy_person_id'] !== null) { return intval($a['deputy_person_id']); } // النائب بمدته وسقفه
            }
            return null;
        }
        if (isset(self::$roleFallback[$role])) {
            $roles = "'" . implode("','", self::$roleFallback[$role]) . "'";
            $r = $conn->query("SELECT id FROM users WHERE company_id = " . intval($companyId)
                . " AND role IN ({$roles}) ORDER BY id LIMIT 1")->fetch_assoc();
            return $r ? intval($r['id']) : null;
        }
        return null;
    }
}
