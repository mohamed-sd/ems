<?php
/**
 * TicketEffectService — الأثر بالتحقق: الخطوات الأربع (TKT-01 §7 · §12 خدمة ④ · TKT-12)
 * ───────────────────────────────────────────────────────────────────────────
 * ① البلاغ يبدأ زمن التوقف «تحت التحقق» ويفتح طلب فحص لا أمر إصلاح ·
 * ② الفني يتحقق ويشخّص · ③ الصيانة تعتمد السبب الفني فيفتح أمر العمل ·
 * ④ مدير الحركة يعتمد الأثر التشغيلي فيصير الإسناد نهائيًّا ويؤثر في الفوترة.
 * «والطرف المتحمل مبدئي حتى الرابعة» — is_provisional=1.
 */

namespace App\Services\Tickets;

class TicketEffectService
{
    /** الخطوة ① — عند بلاغ العطل: طلب فحص + إسناد مبدئي (T2·T14). */
    public static function onFaultReported(\mysqli $conn, $tkId, $maintenanceWsId, $movementWsId)
    {
        $tkId = intval($tkId);
        $conn->begin_transaction();
        try {
            // طلب فحص لا أمر إصلاح — mnt_order بمصدر «بلاغ» وحالة أولية (فحص)
            $tk = $conn->query("SELECT company_id, equipment_id, project_id, complaint FROM tickets WHERE id = {$tkId}")->fetch_assoc();
            $insRef = 'INS-TK' . $tkId;
            $stmt = $conn->prepare(
                "INSERT INTO ticket_effects (ws_id, effect_type, effect_ref, is_provisional) VALUES (?, 'inspection_request', ?, 0)");
            $mws = intval($maintenanceWsId);
            $stmt->bind_param('is', $mws, $insRef);
            $stmt->execute();
            $stmt->close();
            // زمن التوقف «تحت التحقق» بإسناد مبدئي — على مسار الحركة
            $attrRef = 'ST-TK' . $tkId;
            $stmt = $conn->prepare(
                "INSERT INTO ticket_effects (ws_id, effect_type, effect_ref, is_provisional) VALUES (?, 'stoppage_attribution', ?, 1)");
            $vws = intval($movementWsId);
            $stmt->bind_param('is', $vws, $attrRef);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            return array('ok' => true, 'code' => 201,
                'effects' => array(
                    array('type' => 'inspection_request', 'ref' => $insRef),
                    array('type' => 'stoppage_attribution', 'ref' => $attrRef, 'is_provisional' => true)),
                'reason' => 'طلب فحص لا أمر إصلاح · والتوقف «تحت التحقق» بإسناد مبدئي — صفر أثر على الفوترة قبل الاعتمادين');
        } catch (\Throwable $e) {
            $conn->rollback();
            return array('ok' => false, 'code' => 500, 'reason' => $e->getMessage());
        }
    }

    /** الخطوة ③ — اعتماد الصيانة للسبب الفني: يفتح أمر العمل (لا نهائية بعد). */
    public static function approveTechnicalCause(\mysqli $conn, $tkId, $maintenanceWsId, $approverPersonId, $causeNote = '')
    {
        $tkId = intval($tkId);
        $mws = intval($maintenanceWsId);
        /* ══ INJ-0520 · الحارسُ كان تعليقًا لا حكمًا ═══════════════════════════
             السطرُ التالي كان يقرأ مكلَّفَ المسارِ **ولا يستعمله**: تعليقٌ يقول
             «لا اعتمادَ ذاتٍ» وصفٌّ يُجلَب ثم يُهمل — فمكلَّفُ مسارِ الصيانةِ
             يعتمد سببَ مسارِه الفنيَّ بنفسِه، ويُفتح أمرُ عملٍ بيدٍ واحدة.
             والفرقُ بين «حارسٍ مكتوبٍ» و«حارسٍ يقع» هو هذا البند كلُّه. */
        $w = $conn->query("SELECT assignee_person_id FROM ticket_workstreams WHERE ws_id = {$mws}")->fetch_assoc();
        $p0 = intval($approverPersonId);
        if ($w && intval($w['assignee_person_id']) > 0 && intval($w['assignee_person_id']) === $p0) {
            /* ◆ والرفضُ **يُسجَّل**: منعٌ صامتٌ يُقرأ عطلًا فيُلتَفُّ عليه */
            if (function_exists('ems_log_denial')) {
                @ems_log_denial('TKT-403-SELFCAUSE', 'ticket_workstreams:' . $mws,
                    'مكلف المسار حاول اعتماد سببه الفني بنفسه');
            }
            return array('ok' => false, 'code' => 403,
                'reason' => 'TKT-403-SELFCAUSE: لا يعتمد مكلف المسار سببه الفني — يد ثانية لازمة');
        }
        $woRef = 'WO-TK' . $tkId;
        $stmt = $conn->prepare(
            "INSERT INTO ticket_effects (ws_id, effect_type, effect_ref, is_provisional) VALUES (?, 'work_order', ?, 0)");
        $stmt->bind_param('is', $mws, $woRef);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO ticket_responses (tk_id, ws_id, person_id, response_type, body) VALUES (?, ?, ?, 'reply', ?)");
        $p = intval($approverPersonId);
        $body = 'اعتماد السبب الفني (الخطوة ③): ' . $causeNote . ' — فتح ' . $woRef;
        $stmt->bind_param('iiis', $tkId, $mws, $p, $body);
        $stmt->execute();
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'work_order' => $woRef,
            'reason' => 'السبب الفني معتمد وأمر العمل مفتوح — والإسناد ما زال مبدئيا حتى اعتماد مدير الحركة');
    }

    /**
     * الخطوة ④ — اعتماد مدير الحركة للأثر التشغيلي: الإسناد نهائي (T14).
     * «وعندها فقط يؤثر في الفوترة والاستحقاق».
     */
    public static function approveOperationalImpact(\mysqli $conn, $tkId, $movementWsId, $approverPersonId)
    {
        $tkId = intval($tkId);
        $vws = intval($movementWsId);
        // شرط الترتيب: لا نهائية قبل اعتماد السبب الفني (وجود work_order)
        $wo = intval($conn->query(
            "SELECT COUNT(*) c FROM ticket_effects e JOIN ticket_workstreams w ON w.ws_id = e.ws_id
              WHERE w.tk_id = {$tkId} AND e.effect_type = 'work_order'")->fetch_assoc()['c']);
        if ($wo === 0) {
            return array('ok' => false, 'code' => 409,
                'reason' => 'لا إسناد نهائيا قبل اعتماد الصيانة للسبب الفني — الخطوات الأربع بترتيبها');
        }
        $conn->query("UPDATE ticket_effects e JOIN ticket_workstreams w ON w.ws_id = e.ws_id
                        SET e.is_provisional = 0
                      WHERE w.tk_id = {$tkId} AND e.effect_type = 'stoppage_attribution'");
        $changed = $conn->affected_rows;
        $stmt = $conn->prepare("INSERT INTO ticket_responses (tk_id, ws_id, person_id, response_type, body) VALUES (?, ?, ?, 'reply', ?)");
        $p = intval($approverPersonId);
        $body = 'اعتماد الأثر التشغيلي (الخطوة ④): الإسناد نهائي — يؤثر في الفوترة والاستحقاق الآن';
        $stmt->bind_param('iiis', $tkId, $vws, $p, $body);
        $stmt->execute();
        $stmt->close();
        return array('ok' => true, 'code' => 200, 'finalized' => $changed,
            'reason' => 'الإسناد نهائي بعد الاعتمادين — الطرف المتحمل لم يعد مبدئيا');
    }

    /** أثر يناسب الطبيعة (T18): رد موثق أو إقرار استلام أو قرار عدم إجراء. */
    public static function recordLightEffect(\mysqli $conn, $wsId, $effectType, $personId, $body = '')
    {
        $allowed = array('reply', 'acknowledge', 'info_added', 'no_action', 'decision', 'issue_request', 'purchase_request');
        if (!in_array((string) $effectType, $allowed, true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'نوع أثر غير معرف');
        }
        $wsId = intval($wsId);
        $ref = strtoupper(substr((string) $effectType, 0, 3)) . '-WS' . $wsId . '-' . substr(md5((string) microtime(true)), 0, 4);
        $stmt = $conn->prepare("INSERT INTO ticket_effects (ws_id, effect_type, effect_ref, is_provisional) VALUES (?, ?, ?, 0)");
        $stmt->bind_param('iss', $wsId, $effectType, $ref);
        $stmt->execute();
        $stmt->close();
        if ($body !== '') {
            $tk = $conn->query("SELECT tk_id FROM ticket_workstreams WHERE ws_id = {$wsId}")->fetch_assoc();
            $tkId = intval($tk['tk_id']);
            $rt = in_array($effectType, array('acknowledge', 'no_action'), true)
                ? ($effectType === 'no_action' ? 'no_action_decision' : 'acknowledge') : 'reply';
            $stmt = $conn->prepare("INSERT INTO ticket_responses (tk_id, ws_id, person_id, response_type, body) VALUES (?, ?, ?, ?, ?)");
            $p = intval($personId);
            $stmt->bind_param('iiiss', $tkId, $wsId, $p, $rt, $body);
            $stmt->execute();
            $stmt->close();
        }
        return array('ok' => true, 'code' => 201, 'ref' => $ref);
    }
}
