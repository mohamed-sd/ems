<?php
/**
 * محرّك الحالات المركزي — StateMachine (K7 · ADR-08 · خطة §9 المُقرَّة)
 * ───────────────────────────────────────────────────────────────────────────
 * **للوحدات الجديدة والمتبنّين وقت هجرتهم حصرًا** (قرار النطاق الضيّق):
 * الأنظمة الحية الثلاثة — الموافقات الأربع للتايم-شيت (المقدّسة)، ومحرّك
 * approval_* العام (حيّ في movement/Oprators/requests)، وfin_event_flow — لا
 * تُمسّ ولا تُرقّى الآن؛ يتبنّى كلٌّ منها المحرّكَ ضمن دفعة هجرة وحدته (K9/م3)
 * ببوابات golden الخاصة بها.
 *
 * الشكل: صياغة رسمية لنمط fin_event_flow اليدوي القائم — تعريفٌ يحكم كل شيء:
 *   $def = array(
 *     'workflow'     => 'transport_order',
 *     'entity_table' => 'trs_order',          // جدول الكيان (لاتيني)
 *     'state_column' => 'state',
 *     'company_column' => 'company_id',       // null إن لا عزل على الجدول
 *     'states'       => array('draft' => 'مسودة', 'approved' => 'معتمد', ...),
 *     'initial'      => 'draft',
 *     'transitions'  => array(
 *       'approve' => array('from' => 'draft', 'to' => 'approved',
 *                          'roles' => array('17', '19'),   // نصوص كنمط الجلسة
 *                          'guard' => callable|null),      // guard($entityRow): bool
 *     ),
 *   );
 * أكواد الحالات **لاتينية للجديد حصرًا** (ADR-08) — العناوين العربية للعرض فقط،
 * ولا تحويل رجعيًّا لأي ENUM عربية قائمة.
 *
 * الهوية عبر جسر K6 (أول استهلاكٍ حقيقي): المخوَّل يُقيَّم بالدور **الفعّال**
 * (ems_user_effective_role) — منصبٌ صالح يقدَّم على الدور المباشر؛ ومستخدمٌ
 * بلا منصبٍ (كل الـ31 القائمين position_id=NULL) = fallback كاملٌ إلى
 * users.role — **استهلاك الجسر هنا لا يقلب سلوك أي مستخدمٍ قائم** (تأكيد
 * المستخدم الصريح 2026-07-08): الجسر يُستهلك للجديد دون تفعيلٍ قسري على القائم.
 *
 * السباق: انتقالٌ ذرّي بقفلٍ تفاؤلي UPDATE … WHERE state = :from (نمط
 * المالية القائم) — 0 صفوف = انتقالٌ متزامنٌ سبقك → رفضٌ نظيف.
 * التدقيق: قيد append-only في ems_state_transitions مع الدور الفعّال ومصدره.
 * الناقل: نجاح الانتقال يجوز أن ينشر حدث عقدٍ كامل ($opts['event']) عبر
 * EventPublisher بوراثة correlation الممرَّرة — المحرّك عميلُ ناشرٍ لا مقترنٌ به.
 */

namespace App\Core;

require_once __DIR__ . '/StateMachineException.php';

class StateMachine
{
    /** @var \mysqli */
    private $conn;
    /** @var array */
    private $def;

    public function __construct(\mysqli $conn, array $def)
    {
        foreach (array('workflow', 'entity_table', 'state_column', 'states', 'initial', 'transitions') as $k) {
            if (!isset($def[$k]) || $def[$k] === '' || $def[$k] === array()) {
                throw new StateMachineException("تعريف سير العمل ناقص: {$k}");
            }
        }
        foreach (array('entity_table', 'state_column') as $k) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $def[$k])) {
                throw new StateMachineException("معرف غير صالح في التعريف: {$k}");
            }
        }
        if (!isset($def['company_column'])) {
            $def['company_column'] = 'company_id';
        }
        if ($def['company_column'] !== null && !preg_match('/^[A-Za-z0-9_]+$/', $def['company_column'])) {
            throw new StateMachineException('معرف عمود الشركة غير صالح');
        }
        foreach ($def['transitions'] as $action => $t) {
            foreach (array('from', 'to', 'roles') as $k) {
                if (!isset($t[$k])) {
                    throw new StateMachineException("انتقال {$action} ناقص: {$k}");
                }
            }
            if (!isset($def['states'][$t['from']]) || !isset($def['states'][$t['to']])) {
                throw new StateMachineException("انتقال {$action} يشير لحالة خارج التعريف");
            }
        }
        $this->conn = $conn;
        $this->def = $def;
    }

    /** الحالة الراهنة للكيان (أو null إن غاب الصف). */
    public function currentState($entityId)
    {
        $col = $this->def['state_column'];
        $stmt = $this->conn->prepare(
            'SELECT `' . $col . '` FROM `' . $this->def['entity_table'] . '` WHERE `id` = ? LIMIT 1'
        );
        $id = intval($entityId);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row ? strval($row[0]) : null;
    }

    /**
     * تطبيق فعلٍ على كيان. يرمي StateMachineException عند أي خرق؛
     * وعند النجاح يعيد array(from, to, transition_id, event: نتيجة النشر إن طُلب).
     *
     * $opts: note (للتدقيق) · event (مصفوفة حدث عقدٍ كاملة تُنشر بعد النجاح —
     *        مرِّر فيها correlation_id الجذر للوراثة).
     */
    public function apply($entityId, $action, $actorUserId, array $opts = array())
    {
        if (!isset($this->def['transitions'][$action])) {
            throw new StateMachineException('فعل غير معرف في سير العمل: ' . $action);
        }
        $t = $this->def['transitions'][$action];
        $entityId = intval($entityId);
        $actorUserId = intval($actorUserId);

        // ── المخوَّل بالدور الفعّال (جسر K6 — منصبٌ صالح يتقدّم، وإلا users.role) ──
        if (!function_exists('ems_user_effective_role')) {
            require_once dirname(__DIR__, 2) . '/includes/positions.php';
        }
        $eff = ems_user_effective_role($this->conn, $actorUserId);
        if (!in_array($eff['role'], $t['roles'], true)) {
            throw new StateMachineException(
                'فاعل غير مخول: الدور الفعال ' . $eff['role'] . ' (المصدر: ' . $eff['source'] . ') '
                . 'ليس من أدوار الفعل ' . $action
            );
        }

        // ── قراءة الكيان وفحص الحالة (فشل مبكر واضح قبل القفل التفاؤلي) ──
        $companyCol = $this->def['company_column'];
        $cols = '`id`, `' . $this->def['state_column'] . '` AS __state'
              . ($companyCol !== null ? ', `' . $companyCol . '` AS __company' : '');
        $stmt = $this->conn->prepare(
            'SELECT ' . $cols . ' FROM `' . $this->def['entity_table'] . '` WHERE `id` = ? LIMIT 1'
        );
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new StateMachineException('الكيان غير موجود: ' . $this->def['entity_table'] . '#' . $entityId);
        }
        if (strval($row['__state']) !== strval($t['from'])) {
            throw new StateMachineException(
                'حالة فائتة: الفعل ' . $action . ' يتطلب ' . $t['from'] . ' والكيان على ' . $row['__state']
            );
        }

        // ── الحارس الاختياري ──
        if (isset($t['guard']) && is_callable($t['guard']) && !call_user_func($t['guard'], $row)) {
            throw new StateMachineException('الحارس رفض الانتقال: ' . $action);
        }

        // ── الانتقال الذرّي (قفل تفاؤلي — نمط fin_event_flow القائم) ──
        $stmt = $this->conn->prepare(
            'UPDATE `' . $this->def['entity_table'] . '` SET `' . $this->def['state_column'] . '` = ? '
            . 'WHERE `id` = ? AND `' . $this->def['state_column'] . '` = ?'
        );
        $stmt->bind_param('sis', $t['to'], $entityId, $t['from']);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected !== 1) {
            throw new StateMachineException('انتقال متزامن سبقك (القفل التفاؤلي) — أعد القراءة: ' . $action);
        }

        // ── قيد التدقيق append-only ──
        $company = ($companyCol !== null && isset($row['__company'])) ? intval($row['__company']) : null;
        $note = isset($opts['note']) ? substr(strval($opts['note']), 0, 500) : null;
        $stmt = $this->conn->prepare(
            'INSERT INTO `ems_state_transitions`
             (workflow, entity_table, entity_id, company_id, from_state, to_state, action,
              actor_user_id, actor_role, actor_source, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssiisssisss',
            $this->def['workflow'], $this->def['entity_table'], $entityId, $company,
            $t['from'], $t['to'], $action, $actorUserId, $eff['role'], $eff['source'], $note
        );
        $stmt->execute();
        $transitionId = intval($this->conn->insert_id);
        $stmt->close();

        // ── نشر اختياري على الناقل (بعد نجاح الانتقال — وراثة correlation بيد المستدعي) ──
        $eventResult = null;
        if (isset($opts['event']) && is_array($opts['event'])) {
            require_once __DIR__ . '/ServerId.php';
            require_once __DIR__ . '/EventValidationException.php';
            require_once __DIR__ . '/EventPublisher.php';
            $eventResult = EventPublisher::publish($this->conn, $opts['event']);
        }

        return array(
            'from' => $t['from'],
            'to' => $t['to'],
            'transition_id' => $transitionId,
            'actor_role' => $eff['role'],
            'actor_source' => $eff['source'],
            'event' => $eventResult,
        );
    }
}
