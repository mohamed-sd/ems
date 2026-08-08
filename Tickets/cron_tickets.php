<?php
/**
 * Tickets/cron_tickets.php — الدورة المجدوَلة لوحدة البلاغات.
 *
 * تُشغَّل عبر جدولة النظام (سطر الأوامر) أو عبر رابطٍ بمفتاح TICKETS_CRON_KEY.
 * بُنيت على نمط الدورة المجدوَلة في وحدة النقل.
 *
 * ثلاث مهام، كلُّها على جداول الوحدة نفسها:
 *   1) تذكير الاستحقاق  — يقترب موعد الإنجاز ضمن مهلة التذكير ولمّا يُنجَز
 *   2) التصعيد التلقائي — تجاوزُ موعد الإنجاز بالمدّة المحدّدة في سلّم التصعيد،
 *      والجهةُ الهدف تُحسب من شجرة الأدوار
 *   3) التوليد الدوري   — قوالبُ حان موعد توليدها (مع مراعاة مهلة التوليد)،
 *      بترقيمٍ خادميٍّ ذرّي
 *
 * الأثر: إشعارٌ في tkt_notifications (بمنع تكرارٍ يوميّ) + حدثٌ على التذكرة +
 * تذاكرُ مُولَّدة — بلا أيّ مساسٍ بجداول خارج الوحدة.
 */
$IS_CLI = (PHP_SAPI === 'cli');
require __DIR__ . '/../config.php';
require_once __DIR__ . '/tkt_helpers.php';

// حارس المتصفح: fail-closed — مفتاحٌ غير مضبوطٍ في .env = لا مسار ويب إطلاقًا.
if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('TICKETS_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        // تنظيفُ المخازن قبل الردّ: حاقنُ الأرقام في config يفتح مخزنًا
        // فيُذيّل «forbidden» بوسوم script — ردُّ الرفض يجب أن يكون نظيفًا.
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/../app/Core/TenantGateException.php';
require_once __DIR__ . '/../app/Core/TenantRegistry.php';
require_once __DIR__ . '/../app/Core/TenantContext.php';
require_once __DIR__ . '/../app/Core/TenantDb.php';
// ServerId لا يُحمَّل تلقائيًا في سياق CLI (لا مُحمِّل تلقائي لأصناف النواة) —
// وتوليد ticket_no يعتمده. استدعاءٌ صريحٌ كبقية أصناف النواة أعلاه.
require_once __DIR__ . '/../app/Core/ServerId.php';

$today  = date('Y-m-d');
$SYS_AUTH = true;   // بلغنا هنا = CLI أو مفتاحٌ تحقّق (الحارس أعلاه fail-closed)
$n_remind = $n_escal = $n_recur = 0;

// تعداد الشركات ذات التذاكر أو القوالب: سياق نظامٍ + forAllTenants المسجَّلة
$enumGate = new \App\Core\TenantDb($conn,
    \App\Core\TenantContext::forSystem(0, 0, defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1', $SYS_AUTH),
    false, 'enforce');
$company_ids = array();
foreach ($enumGate->forAllTenants('tickets cron company enumeration')
             ->select('tickets', array('columns' => array('company_id'))) as $row) {
    $company_ids[intval($row['company_id'])] = true;
}
foreach ($enumGate->forAllTenants('tickets cron template enumeration')
             ->select('ticket_recurrence_templates', array('columns' => array('company_id'))) as $row) {
    $company_ids[intval($row['company_id'])] = true;
}

foreach (array_keys($company_ids) as $cid) {
    // بوابة دورةٍ معزولةٍ بشركةٍ واحدة صريحة — تُحقن في helpers الوحدة
    $cycleGate = new \App\Core\TenantDb($conn,
        \App\Core\TenantContext::forSystem($cid, 0, '', $SYS_AUTH), false, 'enforce');
    tkt_gate_override($cycleGate);

    // ══ 1) تذكير قرب الاستحقاق ══════════════════════════════════════════════
    // السياسة تحمل remind_before_hours؛ التذكير حين يقع «الآن» داخل النافذة.
    $open = $cycleGate->select('tickets', array(
        'columns'  => array('id', 'ticket_no', 'owner_role_id', 'resolution_due_at', 'sla_policy_id', 'stage'),
        'whereRaw' => "resolution_due_at IS NOT NULL AND stage NOT IN ('done','closed','cancelled')",
    ));
    $policies = array();
    foreach ($cycleGate->select('ticket_sla_policies', array(
        'columns' => array('id', 'remind_before_hours'))) as $p) {
        $policies[intval($p['id'])] = $p['remind_before_hours'];
    }

    foreach ($open as $t) {
        $tid = (int) $t['id'];
        $due = strtotime($t['resolution_due_at']);
        $remind_h = ($t['sla_policy_id'] !== null && isset($policies[intval($t['sla_policy_id'])]))
            ? (float) $policies[intval($t['sla_policy_id'])] : 0.0;

        if ($remind_h > 0 && time() < $due && ($due - time()) <= ($remind_h * 3600)) {
            if (tkt_notify('due_soon', 'يقترب استحقاق التذكرة ' . $t['ticket_no'],
                    'موعد الإنجاز: ' . $t['resolution_due_at'], $tid, intval($t['owner_role_id']),
                    'ticket_form.php?id=' . $tid, 'due_soon:' . $tid . ':' . $today)) {
                $n_remind++;
                tkt_log_event($tid, 'reminder', 'تذكير: يقترب موعد الإنجاز (' . $t['resolution_due_at'] . ')');
            }
        }
    }

    // ══ 2) التصعيد التلقائي عند تجاوز الاستحقاق ═════════════════════════════
    $rules = $cycleGate->select('ticket_escalation_rules', array(
        'where' => array('active' => 1), 'orderBy' => 'level_no ASC, id ASC'));
    if (!empty($rules)) {
        foreach ($open as $t) {
            $tid = (int) $t['id'];
            $due = strtotime($t['resolution_due_at']);
            if (time() <= $due) { continue; }                       // لم يتجاوز بعد
            $late_h = (time() - $due) / 3600;

            // أعلى مستوى تصعيدٍ بلغه التأخّر
            $hit = null;
            foreach ($rules as $r) {
                if ($late_h >= (float) $r['escalate_after_hours']) { $hit = $r; }
            }
            if ($hit === null) { continue; }

            // E-14 (UX-07 §5.2): **منعُ تكرار المستوى بنيويًّا** — العمودُ
            // `escalation_level` هو الحكم: مستوًى ≤ المسجَّلِ لا يُشعَر به
            // ثانيةً أبدًا (كان المفتاحُ يوميًّا فيتكرر الإشعارُ نفسُه غدًا).
            if (intval($hit['level_no']) <= intval($t['escalation_level'] ?? 0)) { continue; }

            $target = tkt_escalation_target_role($hit['escalate_to_role'], intval($t['owner_role_id']));
            $dedupe = 'escalation:' . $tid . ':L' . intval($hit['level_no']);
            if (tkt_notify('escalation', 'تصعيد المستوى ' . intval($hit['level_no']) . ' — التذكرة ' . $t['ticket_no'],
                    'تجاوزت موعد الإنجاز بـ' . (int) floor($late_h) . ' ساعة', $tid, $target,
                    'ticket_form.php?id=' . $tid, $dedupe)) {
                $n_escal++;
                $cycleGate->update('tickets',
                    array('escalation_level' => intval($hit['level_no'])),
                    array('id' => $tid));
                tkt_log_event($tid, 'escalation',
                    'تصعيد تلقائي (مستوى ' . intval($hit['level_no']) . ') إلى: ' . $hit['escalate_to_role']
                    . ' — تأخّر ' . (int) floor($late_h) . ' ساعة',
                    null, null, null, $target);
            }
        }
    }

    // ══ 3) التوليد الدوري ═══════════════════════════════════════════════════
    $templates = $cycleGate->select('ticket_recurrence_templates', array(
        'where'    => array('active' => 1),
        'whereRaw' => "DATE_SUB(next_occurrence_date, INTERVAL lead_time_days DAY) <= ?",
        'params'   => array($today),
    ));
    foreach ($templates as $tpl) {
        $tplId = (int) $tpl['id'];
        $type = $cycleGate->selectOne('ticket_types', array(
            'columns' => array('id', 'owner_role_id', 'default_nature'),
            'where'   => array('id' => intval($tpl['ticket_type_id']))));
        if (!$type) { continue; }                                   // نوعٌ محذوف/معطّل — تخطٍّ آمن

        // منع الازدواج: تذكرةٌ مولَّدةٌ لهذا القالب بنفس تاريخ الدورة موجودةٌ سلفًا؟
        $already = (int) $cycleGate->count('tickets', array(
            'whereRaw' => 'recurrence_template_id = ? AND call_date = ?',
            'params'   => array($tplId, $tpl['next_occurrence_date']),
        ));
        if ($already > 0) { continue; }

        // الرقم قبل المعاملة — ارتدادُها كان يتراجع بالعدّاد فيعلق التوليد الدوري.
        try {
            $newNo = tkt_next_ticket_no($conn, $cid);
        } catch (\Throwable $e) {
            error_log('tickets cron number allocation failed (tpl ' . $tplId . '): ' . $e->getMessage());
            continue;
        }
        try {
            $cycleGate->runInTransaction(function ($g) use (
                $conn, $cid, $tpl, $tplId, $type, $newNo
            ) {
                $tid = $g->insert('tickets', array(
                    'ticket_no' => $newNo, 'ticket_type_id' => intval($tpl['ticket_type_id']),
                    'category_id' => ($tpl['category_id'] !== null) ? intval($tpl['category_id']) : null,
                    'stage' => 'routed', 'ticket_nature' => 'recurring',
                    'priority' => $tpl['default_priority'], 'business_impact' => 'admin',
                    'call_date' => $tpl['next_occurrence_date'], 'call_time' => '00:00',
                    'reporting_person' => 'النظام (توليد دوري)',
                    'equipment_id' => ($tpl['equipment_id'] !== null) ? intval($tpl['equipment_id']) : null,
                    'complaint' => $tpl['name'] . ' — تذكرة دورية مُولَّدة تلقائيًا لدورة ' . $tpl['next_occurrence_date'],
                    'owner_role_id' => ($tpl['default_owner_role_id'] !== null)
                        ? intval($tpl['default_owner_role_id']) : intval($type['owner_role_id']),
                    'is_recurring' => 1, 'recurrence_template_id' => $tplId,
                ));
                tkt_apply_sla($g, $tid, intval($tpl['ticket_type_id']), $tpl['default_priority'], 'admin',
                              $tpl['next_occurrence_date'], '00:00');
                $g->insert('ticket_events', array(
                    'ticket_id' => $tid, 'event_type' => 'system',
                    'body' => 'تذكرة دورية مُولَّدة من القالب: ' . $tpl['name'], 'new_value' => 'routed',
                ));
                // دفع الدورة التالية بمقدار الفاصل
                $next = date('Y-m-d', strtotime($tpl['next_occurrence_date']
                    . ' +' . intval($tpl['recurrence_interval']) . ' ' . $tpl['recurrence_unit']));
                $g->update('ticket_recurrence_templates', array('next_occurrence_date' => $next), array('id' => $tplId));
            }, 'tickets cron: recurring generation');
        } catch (\Throwable $e) {
            error_log('tickets cron recurring failed (tpl ' . $tplId . '): ' . $e->getMessage());
            continue;
        }

        $n_recur++;
        tkt_notify('recurring_created', 'تذكرة دورية جديدة: ' . $newNo, $tpl['name'], null,
            ($tpl['default_owner_role_id'] !== null) ? intval($tpl['default_owner_role_id']) : intval($type['owner_role_id']),
            'tickets_list.php', 'recurring:' . $tplId . ':' . $tpl['next_occurrence_date']);
    }

    tkt_gate_override(null);
}

// update0004 · TKT-10: مراقب مهل المسارات المتوازية — تصعيد آلي كل دورة
// (ResponseBreached·ResolveBreached·hold_overdue) بساعة القاعدة
require_once __DIR__ . '/../app/Services/Tickets/SlaMonitor.php';
$slaTotals = array('response_breach' => 0, 'resolve_breach' => 0, 'hold_overdue' => 0);
foreach ($company_ids as $cid) {
    $slaR = \App\Services\Tickets\SlaMonitor::run($conn, (int) $cid);
    foreach ($slaTotals as $k => $v) { $slaTotals[$k] += (int) $slaR[$k]; }
}

$summary = "[tickets-cron] companies=" . count($company_ids)
    . " reminders=$n_remind escalations=$n_escal recurring=$n_recur"
    . " ws_sla=" . $slaTotals['response_breach'] . '/' . $slaTotals['resolve_breach']
    . '/' . $slaTotals['hold_overdue'] . "\n";
echo $summary;
if (function_exists('log_security_event')) {
    log_security_event('tickets_cron_run', trim($summary));
}
