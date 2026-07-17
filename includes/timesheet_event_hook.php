<?php
/**
 * خطّاف حدث اعتماد الساعات — K5 (ADR-10 · الهيكل العامل الحدثي)
 * ───────────────────────────────────────────────────────────────────────────
 * يُستدعى من hours_approval_handler.php **بعد نجاح إدراج الموافقة الرابعة
 * الأخيرة حصرًا** (نقطة الاكتمال المُثبتة: approval_level=4). إضافة معزولة —
 * لا يملك أي سلطةٍ على مسار الاعتماد: كل فشلٍ هنا يُبتلع ويُسجَّل، والاعتماد
 * يكتمل دائمًا (قرار خطة K5 §8.2: الاعتماد وظيفة أساسية والنشر أثر جانبي).
 *
 * العلَم EMS_EVENT_HOOKS (نمط أعلام العائلة — off افتراضًا):
 *   off     : لا شيء إطلاقًا (سلوك ما قبل K5 حرفيًا).
 *   monitor : يحلّ المراجع ويبني الحدث ويسجّل ما «كان سيُنشر» — بلا أي كتابة.
 *   publish : نشرٌ فعلي عبر EventPublisher (عطالة بجيل الاعتماد).
 *
 * جيل العطالة: المفتاح يتضمن معرّف صف الموافقة الرابعة (a{id}) — إعادةُ تسليمٍ
 * لنفس الجيل = duplicate؛ ورفضٌ يصفّر السلسلة ثم اعتمادٌ جديد = جيلٌ جديد =
 * حدثٌ جديد بالقيم المصحّحة (قرار §8.2). payload يحمل approval_id للمُصالِح
 * وكاشف الأحداث اليتيمة في cron_events.php.
 *
 * حلّ المراجع (أول تطبيق فعلي لسياسة ADR-09 عند النشر): وصفة JOINs شاشة
 * الاعتماد نفسها. المتعذّر حلُّه نصيًّا → NULL في المرجع + القيمة الخام في
 * payload + قيد REF_RESOLUTION_MISS في السجل — لا تخمين ولا إيقاف.
 *
 * الدلالة المُقرّة: quantity=executed_hours · unit=hour · occurred_at=يوم العمل
 * t.date (لا لحظة الاعتماد) · amount=0 **placeholder واعٍ لا إيراد صفري**
 * (payload.valuation='unpriced' — التسعير محرّك مرحلة 3).
 */

if (!function_exists('ems_timesheet_event_hook')) {

    function ems_timesheet_event_hook(\mysqli $conn, $tsId, $level4RowId, $actorUserId)
    {
        try {
            $mode = function_exists('ems_env') ? (string) ems_env('EMS_EVENT_HOOKS', 'off') : 'off';
            if ($mode !== 'monitor' && $mode !== 'publish') {
                return; // off — صفر أثر
            }

            $tsId = intval($tsId);
            $gen  = intval($level4RowId);

            // ── حلّ المراجع بوصفة JOINs شاشة الاعتماد نفسها (قراءة واحدة) ──
            $sql = "SELECT t.id, t.company_id, t.`date`, t.executed_hours, t.total_work_hours,
                           t.shift_hours, t.standby_hours, t.extra_hours_total, t.total_fault_hours,
                           t.tons_count, t.trips_count, t.meters_count,
                           t.operator AS operator_raw, t.employee_id AS employee_raw,
                           o.id AS op_id, o.project_id AS project_raw, o.contract_id AS contract_raw,
                           e.id AS equipment_id, e.suppliers AS supplier_id,
                           p.id AS project_id, emp.id AS employee_id_num
                    FROM timesheet t
                    LEFT JOIN operations o ON o.id = t.operator
                    LEFT JOIN equipments e ON e.id = o.equipment
                    LEFT JOIN project    p ON p.id = o.project_id
                    LEFT JOIN employees emp ON emp.id = t.employee_id
                    WHERE t.id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new \RuntimeException('hook prepare failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $tsId);
            $stmt->execute();
            $t = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$t) {
                throw new \RuntimeException('timesheet row vanished: ' . $tsId);
            }

            // مراجع متعذّرة الحل (خام موجود ↔ رقمي غائب) → قيد ADR-09
            $misses = array();
            if ((string) $t['operator_raw'] !== '' && $t['op_id'] === null)          { $misses[] = 'operator→operations'; }
            if ((string) $t['employee_raw'] !== '' && $t['employee_id_num'] === null) { $misses[] = 'employee_id→employees'; }
            if ($t['op_id'] !== null && (string) $t['project_raw'] !== '' && $t['project_id'] === null) { $misses[] = 'operations.project_id→project'; }
            if (!empty($misses) && function_exists('log_security_event')) {
                log_security_event('REF_RESOLUTION_MISS',
                    'timesheet=' . $tsId . ' misses=' . implode(',', $misses)
                    . ' raw={op:' . $t['operator_raw'] . ',emp:' . $t['employee_raw'] . ',proj:' . $t['project_raw'] . '}');
            }

            require_once __DIR__ . '/../app/Core/ServerId.php';
            require_once __DIR__ . '/../app/Core/EventValidationException.php';
            require_once __DIR__ . '/../app/Core/EventPublisher.php';

            $idem = \App\Core\ServerId::idempotencyKey('equipment.hour_logged', 'timesheet', $tsId) . ':a' . $gen;

            $event = array(
                'event_key'       => 'equipment.hour_logged',
                'category'        => 'operational',
                'source_module'   => 'movement',
                'company_id'      => intval($t['company_id']),
                'entity_type'     => 'timesheet',
                'entity_id'       => $tsId,
                'occurred_at'     => $t['date'] . ' 00:00:00', // يوم العمل الفعلي — لا لحظة الاعتماد
                'created_by'      => intval($actorUserId),      // الفاعل: معتمِد المستوى الرابع
                'quantity'        => (float) $t['executed_hours'],
                'unit'            => 'hour',
                'amount'          => 0,                          // placeholder واعٍ — انظر payload.valuation
                'equipment_id'    => $t['equipment_id'] !== null ? intval($t['equipment_id']) : null,
                'supplier_entity_id' => $t['supplier_id'] !== null ? intval($t['supplier_id']) : null,
                'project_id'      => $t['project_id'] !== null ? intval($t['project_id']) : null,
                'operator_employee_id' => $t['employee_id_num'] !== null ? intval($t['employee_id_num']) : null,
                'contract_id'     => null, // الشاشة نفسها لا تحلّه (خطة §8.1) — الخام في payload
                'source_ref'      => 'TS-' . $tsId,
                'idempotency_key' => $idem,
                'payload'         => array(
                    'approval_id'   => $gen, // جيل الاعتماد — للمُصالِح وكاشف اليتيم
                    'valuation'     => 'unpriced', // amount=0 لم يُسعَّر بعد — ليس إيرادًا صفريًا
                    'hours'         => array(
                        'executed' => (float) $t['executed_hours'],
                        'total'    => (float) $t['total_work_hours'],
                        'shift'    => (float) $t['shift_hours'],
                        'standby'  => (float) $t['standby_hours'],
                        'extra'    => (float) $t['extra_hours_total'],
                        'faults'   => (float) $t['total_fault_hours'],
                    ),
                    'production'    => array(
                        'tons' => (float) $t['tons_count'], 'trips' => intval($t['trips_count']), 'meters' => (float) $t['meters_count'],
                    ),
                    'raw_refs'      => array( // القيم النصية كما هي — لا تخمين (ADR-09)
                        'operator' => (string) $t['operator_raw'],
                        'employee' => (string) $t['employee_raw'],
                        'project'  => (string) $t['project_raw'],
                        'contract' => (string) $t['contract_raw'],
                    ),
                    'work_date'     => (string) $t['date'],
                ),
            );

            if ($mode === 'monitor') {
                if (function_exists('log_security_event')) {
                    log_security_event('EVENT_HOOK_MONITOR',
                        'would-publish equipment.hour_logged ts=' . $tsId . ' gen=' . $gen
                        . ' idem=' . $idem
                        . ' refs={eq:' . var_export($event['equipment_id'], true)
                        . ',sup:' . var_export($event['supplier_entity_id'], true)
                        . ',proj:' . var_export($event['project_id'], true)
                        . ',emp:' . var_export($event['operator_employee_id'], true) . '}'
                        . ' qty=' . $event['quantity'] . ' occurred=' . $event['occurred_at']);
                }
                return;
            }

            // publish
            $r = \App\Core\EventPublisher::publish($conn, $event);
            if (function_exists('log_security_event')) {
                log_security_event('EVENT_HOOK_PUBLISHED',
                    'equipment.hour_logged ts=' . $tsId . ' gen=' . $gen
                    . ' event_id=' . $r['id'] . ' dup=' . ($r['duplicate'] ? '1' : '0')
                    . ' corr=' . $r['correlation_id']);
            }

            // ── D02 §5: بوابة التحويل المالي — من يخلق المال؟ ──
            // gate=on: الاعتماد التشغيلي يكتمل ولا مالَ بعد؛ اليوم يدخل طابور
            // المالية وتُنشأ المروحة بختمها حصرًا (Finance/unit_records_fin).
            // gate=off: السلوك السابق — المروحة تنطلق هنا تلقائيًّا.
            // العلَم هو نقطة التراجع الوحيدة: قلبُه يعيد السلوك القديم فورًا.
            $convertGate = function_exists('ems_env')
                && strtolower((string) ems_env('EMS_UNIT_CONVERT_GATE', 'off')) === 'on';
            if ($convertGate) {
                if (function_exists('log_security_event')) {
                    log_security_event('FANOUT_TS_DEFERRED',
                        'ts=' . $tsId . ' اكتمل الاعتماد التشغيلي — بانتظار التحويل المالي');
                }
                return; // الحقيقة نُشرت على الناقل؛ والأثر المالي بيد المالية
            }

            // ── D02 م1-①: مروحة أثر يوم الدوام — بعد نشر الجذر، في معاملتها ──
            // الذرّية الخاصة (كل الآثار أو لا شيء)، بعطالة fin_event_links —
            // فتعمل أيضًا عند إعادة التسليم (dup) لشفاء مروحةٍ فاتت. فشلُها
            // يُبتلع ويُسجَّل (الاعتماد أساسيٌّ والمروحة أثرٌ جانبي)، وكنسُ
            // cron_events يلتقط ما فات ضمن نافذته.
            try {
                require_once __DIR__ . '/../app/Core/TenantGateException.php';
                require_once __DIR__ . '/../app/Core/TenantRegistry.php';
                require_once __DIR__ . '/../app/Core/TenantContext.php';
                require_once __DIR__ . '/../app/Core/TenantDb.php';
                require_once __DIR__ . '/../app/Services/EffectFanout.php';
                if (isset($_SESSION['user']['id']) && function_exists('ems_tenant_db')) {
                    $fanGate = ems_tenant_db(); // سياق الشاشة (المعالج تحقق أن الصف لشركة الجلسة)
                } else {
                    // سياق خادمي (cron/CLI) — بوابةٌ معزولةٌ بشركة الصف نفسها (عقد §11)
                    $sysOk = (PHP_SAPI === 'cli') || ((string) ems_env('EVENTS_CRON_KEY', '') !== '');
                    $fanGate = new \App\Core\TenantDb($conn,
                        \App\Core\TenantContext::forSystem(intval($t['company_id']), 0, '', $sysOk));
                }
                $fanRes = null;
                $fanGate->runInTransaction(function ($g) use (&$fanRes, $conn, $tsId, $actorUserId) {
                    $fanRes = \App\Services\EffectFanout::forTimesheetId($conn, $g, $tsId, intval($actorUserId));
                }, 'timesheet effect fan-out ' . $tsId);
                if ($fanRes && function_exists('log_security_event')) {
                    log_security_event('FANOUT_TS',
                        'ts=' . $tsId . ' effects=' . count($fanRes['effects'])
                        . ' skipped=' . count($fanRes['skipped'])
                        . ' revision=' . (!empty($fanRes['revision_pending']) ? '1' : '0'));
                }
            } catch (\Throwable $fx) {
                if (function_exists('log_security_event')) {
                    log_security_event('FANOUT_TS_FAILED',
                        'timesheet=' . $tsId . ' err=' . substr($fx->getMessage(), 0, 300));
                }
            }
        } catch (\Throwable $x) {
            // الاعتماد لا يُكسر أبدًا — الفشل يُسجَّل ويُصالَح لاحقًا (cron_events).
            if (function_exists('log_security_event')) {
                log_security_event('EVENT_PUBLISH_FAILED',
                    'timesheet=' . intval($tsId) . ' gen=' . intval($level4RowId)
                    . ' err=' . substr($x->getMessage(), 0, 300));
            }
        }
    }
}
