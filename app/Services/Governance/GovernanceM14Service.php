<?php
/**
 * app/Services/Governance/GovernanceM14Service.php — أفعالُ M-14 المستكملة
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: M-14 — الحوكمة والالتزام v1 (docs/update0012) §7 و§8.
 * الأفعالُ الأربعةُ التي كانت declared_unbuilt:
 *   approval.reject / approval.return — قرارُ الصندوقِ الموحَّدِ يمرُّ بخدمةِ
 *     مصدرِه («لا قرارَ يُنفَّذ هنا» — عقدُ الشاشة) وسجلُّه المحكومُ هنا:
 *     السببُ من قائمةٍ محكومةٍ ويُقاس في تحليل الاختناقات.
 *   denial.review — المنعُ المتكرر يُصنَّف: استثناءٌ أو خطأُ تصنيفٍ أو تجاوز.
 *   org.change — كلُّ تغييرِ هيكلٍ نسخةٌ بلقطتها وقرارِها المرجعيِّ —
 *     والرجوعُ بقرارٍ لا محوًا.
 * عدمُ الرجعية: append-only — ولا دالةَ حذفٍ عمدًا.
 */

namespace App\Services\Governance;

require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';

use App\Core\EventPublisher;

class GovernanceM14Service
{
    /** الأسبابُ المحكومةُ لقرارَي الرفضِ والإعادة — تُقاس في تحليل الاختناقات. */
    const REASONS = array(
        'RSN-BUDGET' => 'تجاوزُ الموازنةِ أو غيابُ الالتزام',
        'RSN-DOCS'   => 'نقصُ المستنداتِ المؤيدة',
        'RSN-AUTH'   => 'خارجَ سقفِ أو نطاقِ التفويض',
        'RSN-DUP'    => 'ازدواجٌ مع مستندٍ قائم',
        'RSN-DATA'   => 'بياناتٌ ناقصةٌ أو متعارضة',
        'RSN-OTHER'  => 'سببٌ آخرُ — البيانُ إلزامي',
    );

    public static function nextCode(\mysqli $db, $companyId, $table, $column, $prefix, $width = 6)
    {
        $allowed = array(
            'gov_approval_decisions' => 'decision_code',
            'gov_denial_reviews'     => 'review_code',
            'org_structure_versions' => 'version_code',
        );
        if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
            throw new \RuntimeException('GOV-500: جدولٌ أو عمودٌ خارجَ قائمةِ الترقيم');
        }
        $len = strlen($prefix) + 2;
        $st = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(`$column`, $len) AS UNSIGNED)), 0) + 1 nx FROM `$table` WHERE company_id = ?");
        $st->bind_param('i', $companyId);
        $st->execute();
        $nx = (int) $st->get_result()->fetch_assoc()['nx'];
        $st->close();
        return $prefix . '-' . str_pad((string) $nx, $width, '0', STR_PAD_LEFT);
    }

    /* ═══ approval.reject / approval.return — بخدمة المصدر وسجلٍّ محكوم ═════ */

    /**
     * القرارُ على مستندٍ من مصادر الصندوق الأربعة. يمرُّ بخدمة المصدر
     * (fin_requests عبر أحداثها · القيدُ بإعادةٍ للتصحيح ...) ويُسجَّل هنا
     * بسببه المحكوم. decision: rejected | returned.
     */
    public static function decideApproval(\mysqli $db, $companyId, $sourceKind, $sourceRef, $decision, $reasonCode, $reasonNote, $actor, $capacity, $authorityRef)
    {
        if (!in_array($decision, array('rejected', 'returned'), true)) {
            throw new \RuntimeException('GOV-422: القرارُ rejected أو returned حصرًا');
        }
        if (!isset(self::REASONS[$reasonCode])) {
            throw new \RuntimeException('GOV-422: السببُ من القائمة المحكومة حصرًا — ' . implode(' · ', array_keys(self::REASONS)));
        }
        if ($reasonCode === 'RSN-OTHER' && trim((string) $reasonNote) === '') {
            throw new \RuntimeException('GOV-422: RSN-OTHER يلزمه بيانُ السبب');
        }
        $sourceRef = trim((string) $sourceRef);
        if ($sourceRef === '') { throw new \RuntimeException('GOV-422: مرجعُ المستندِ إلزاميّ'); }

        // القرارُ يقع في المصدر أولًا — «كلُّ سطرٍ قرارُه في شاشة مالكه بخدمته»
        $ringNo = 1;
        switch ($sourceKind) {
            case 'fin_request':
                $reqId = (int) preg_replace('/\D+/', '', $sourceRef);
                $st = $db->prepare("SELECT id, state, requester_id, created_by FROM fin_requests WHERE id = ? AND company_id = ? LIMIT 1");
                $st->bind_param('ii', $reqId, $companyId);
                $st->execute();
                $req = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$req) { throw new \RuntimeException('GOV-404: الطلبُ الماليُّ غيرُ موجودٍ في نطاقك'); }
                if ((int) $req['requester_id'] === (int) $actor || (int) $req['created_by'] === (int) $actor) {
                    throw new \RuntimeException('GOV-SOD-403: مُنشئُ الطلبِ لا يقرر فيه — فصلُ الواجبات بنيوي');
                }
                // الانتقالُ المعرَّفُ حصرًا: القرارُ على ما ينتظر قرارًا لا على المنفَّذ
                if (!in_array($req['state'], array('draft', 'under_review', 'pending_approval', 'returned'), true)) {
                    throw new \RuntimeException('GOV-409: حالةُ الطلبِ «' . $req['state'] . '» لا تقبل هذا القرار — الانتقالُ غيرُ المعرَّفِ يُرفض');
                }
                $newState = $decision === 'rejected' ? 'rejected' : 'returned';
                $oldState = (string) $req['state'];
                $st = $db->prepare("UPDATE fin_requests SET state = ?, decided_by = ? WHERE id = ? AND company_id = ?");
                $st->bind_param('siii', $newState, $actor, $reqId, $companyId);
                $st->execute();
                $st->close();
                // حدثُ المصدر — يظهر في خط زمن الطلب بقيمتَي قبلَ وبعد (⑨)
                $ev = $db->prepare("INSERT INTO fin_request_events
                    (company_id, request_id, event_type, actor_user_id, body, old_value, new_value, created_at)
                     VALUES (?,?,?,?,?,?,?,NOW())");
                if ($ev) {
                    $et = $decision === 'rejected' ? 'reject' : 'return';
                    $body = $reasonCode . ': ' . (string) (self::REASONS[$reasonCode]) .
                        ($reasonNote !== '' ? ' — ' . $reasonNote : '');
                    $ev->bind_param('iisisss', $companyId, $reqId, $et, $actor, $body, $oldState, $newState);
                    @$ev->execute();
                    $ev->close();
                }
                break;

            case 'journal_entry':
                $st = $db->prepare("SELECT id, state, created_by FROM fin_journal_entries
                                     WHERE entry_no = ? AND company_id = ? AND COALESCE(is_deleted,0) = 0 LIMIT 1");
                $st->bind_param('si', $sourceRef, $companyId);
                $st->execute();
                $je = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$je) { throw new \RuntimeException('GOV-404: القيدُ غيرُ موجودٍ في نطاقك'); }
                if ($je['state'] === 'posted') {
                    throw new \RuntimeException('GOV-409: القيدُ المنشورُ لا يُرفض — التصحيحُ بقيدٍ عاكس (PR-06)');
                }
                $newState = 'draft'; // الإعادةُ والرفضُ يعيدانه مسودةً للتصحيح — والسجلُّ يحفظ القرار
                $st = $db->prepare("UPDATE fin_journal_entries SET state = ? WHERE id = ? AND company_id = ?");
                $st->bind_param('sii', $newState, $je['id'], $companyId);
                $st->execute();
                $st->close();
                break;

            case 'supplier_settlement':
            case 'period_close':
            case 'other':
                // مصادرُ قرارُها في شاشتها بخدمتها — يُسجَّل القرارُ الحوكميُّ
                // هنا سجلًّا محكومًا ويُنفَّذ الأثرُ من شاشة المالك (عقدُ الصندوق).
                break;

            default:
                throw new \RuntimeException('GOV-422: مصدرٌ خارجَ الأنواع الأربعة');
        }

        $idem = 'apd:' . $sourceKind . ':' . $sourceRef . ':' . $decision . ':r' . $ringNo;
        $st = $db->prepare("SELECT id, decision_code FROM gov_approval_decisions
                             WHERE company_id = ? AND idempotency_key = ? LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        if ($prev = $st->get_result()->fetch_assoc()) {
            $st->close();
            return array('idempotent' => true, 'id' => (int) $prev['id'], 'decision_code' => $prev['decision_code']);
        }
        $st->close();

        $code = self::nextCode($db, $companyId, 'gov_approval_decisions', 'decision_code', 'APD');
        $eventName = $decision === 'rejected' ? 'ApprovalRejected' : 'ApprovalReturned';
        $fact = EventPublisher::publishFact($db, array(
            'event_key' => 'governance.approval.' . $decision,
            'category' => 'operational',
            'source_module' => 'governance',
            'company_id' => (int) $companyId,
            'entity_type' => 'gov_approval_decision',
            'entity_id' => 0,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
            'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $idem,
            'source_ref' => $sourceKind . ':' . $sourceRef,
            'payload' => array(
                'event_name' => $eventName,
                'consumers' => $decision === 'rejected' ? array('المُنشئ', 'المسار') : array('المُنشئ'),
                'reason_code' => $reasonCode,
            ),
        ));
        $factId = $fact ? (int) $fact['id'] : null;

        $st = $db->prepare("INSERT INTO gov_approval_decisions
            (company_id, decision_code, source_kind, source_ref, decision, reason_code,
             reason_note, ring_no, decided_by, decided_capacity, authority_ref, parent_ref,
             event_id, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $parent = $sourceKind . ':' . $sourceRef;
        $st->bind_param('issssssiisssis',
            $companyId, $code, $sourceKind, $sourceRef, $decision, $reasonCode,
            $reasonNote, $ringNo, $actor, $capacity, $authorityRef, $parent,
            $factId, $idem);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('GOV-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        return array('idempotent' => false, 'id' => $id, 'decision_code' => $code,
            'decision' => $decision, 'reason' => self::REASONS[$reasonCode]);
    }

    /* ═══ denial.review — تصنيفُ المحاولةِ الممنوعة ═════════════════════════ */

    public static function reviewDenial(\mysqli $db, $companyId, $denialId, $classification, $note, $followUpRef, $actor, $authorityRef)
    {
        $valid = array('يحتاج استثناءً', 'خطأ تصنيف حماية', 'محاولة تجاوز', 'عابر — لا إجراء');
        if (!in_array($classification, $valid, true)) {
            throw new \RuntimeException('GOV-422: التصنيفُ من الأربعة حصرًا');
        }
        $st = $db->prepare("SELECT deny_id, guard_code FROM guard_denials WHERE deny_id = ? AND company_id = ? LIMIT 1");
        $st->bind_param('ii', $denialId, $companyId);
        $st->execute();
        $dn = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$dn) { throw new \RuntimeException('GOV-404: المحاولةُ غيرُ موجودةٍ في نطاقك'); }

        // مراجعةٌ واحدةٌ للمحاولة (uq) — التكرارُ يرجع الأولى (AR-04)
        $st = $db->prepare("SELECT id, review_code FROM gov_denial_reviews
                             WHERE company_id = ? AND denial_id = ? LIMIT 1");
        $st->bind_param('ii', $companyId, $denialId);
        $st->execute();
        if ($prev = $st->get_result()->fetch_assoc()) {
            $st->close();
            return array('idempotent' => true, 'id' => (int) $prev['id'], 'review_code' => $prev['review_code']);
        }
        $st->close();

        $code = self::nextCode($db, $companyId, 'gov_denial_reviews', 'review_code', 'DNR');
        $parent = 'DENY-' . (int) $denialId;
        $guard = (string) ($dn['guard_code'] ?? '');
        $st = $db->prepare("INSERT INTO gov_denial_reviews
            (company_id, review_code, denial_id, guard_code, classification, decision_note,
             follow_up_ref, state, reviewed_by, authority_ref, parent_ref)
            VALUES (?,?,?,?,?,?,?, 'closed', ?,?,?)");
        $st->bind_param('isissssiss', $companyId, $code, $denialId, $guard, $classification,
            $note, $followUpRef, $actor, $authorityRef, $parent);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('GOV-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();
        $db->query("UPDATE gov_denial_reviews SET closed_by = " . (int) $actor . ", closed_at = NOW()
                     WHERE id = {$id} AND company_id = {$companyId}");

        EventPublisher::publishFact($db, array(
            'event_key' => 'governance.denial.reviewed', 'category' => 'operational',
            'source_module' => 'governance', 'company_id' => (int) $companyId,
            'entity_type' => 'gov_denial_review', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'dnr:' . $denialId,
            'source_ref' => $code,
            'payload' => array('event_name' => 'DenialReviewed',
                'consumers' => array('الإدارةُ المعنية'), 'classification' => $classification),
        ));
        return array('idempotent' => false, 'id' => $id, 'review_code' => $code);
    }

    /* ═══ org.change — نسخةُ هيكلٍ بلقطتها وقرارِها ═════════════════════════ */

    public static function orgChange(\mysqli $db, $companyId, $changeKind, $unitId, $decisionRef, $effectiveDate, array $change, $actor, $authorityRef)
    {
        $kinds = array('إنشاء وحدة', 'تعديل وحدة', 'تعطيل وحدة', 'نقل تبعية', 'تعديل مسمى', 'رجوع لنسخة');
        if (!in_array($changeKind, $kinds, true)) {
            throw new \RuntimeException('GOV-422: نوعُ التغيير من الستة حصرًا');
        }
        if (trim((string) $decisionRef) === '') {
            throw new \RuntimeException('GOV-422: قرارُ التغييرِ المرجعيُّ إلزاميٌّ — لا تغييرَ هيكلٍ بلا قرار');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $effectiveDate)) {
            throw new \RuntimeException('GOV-422: تاريخُ السريان YYYY-MM-DD');
        }

        // اللقطةُ قبل التغيير — أساسُ الرجوع
        $snapshot = array();
        $res = $db->query("SELECT unit_id, unit_code, name_ar, layer, parent_unit_id, active
                             FROM org_units WHERE company_id = {$companyId} ORDER BY unit_id");
        if ($res) { while ($x = $res->fetch_assoc()) { $snapshot[] = $x; } }

        // تطبيقُ التغيير على org_units — الانتقالُ المعرَّفُ حصرًا
        $unitId = $unitId ? (int) $unitId : null;
        switch ($changeKind) {
            case 'إنشاء وحدة':
                if (empty($change['unit_code']) || empty($change['name_ar'])) {
                    throw new \RuntimeException('GOV-422: كودُ الوحدةِ واسمُها إلزاميان');
                }
                $st = $db->prepare("INSERT INTO org_units (company_id, unit_code, name_ar, layer, parent_unit_id, active)
                                     VALUES (?,?,?,?,?,1)");
                $layer = (string) ($change['layer'] ?? 'parallel');
                $parent = !empty($change['parent_unit_id']) ? (int) $change['parent_unit_id'] : null;
                $st->bind_param('isssi', $companyId, $change['unit_code'], $change['name_ar'], $layer, $parent);
                $st->execute();
                if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('GOV-500: ' . $err); }
                $unitId = (int) $db->insert_id;
                $st->close();
                break;
            case 'تعديل وحدة':
            case 'تعديل مسمى':
                if (!$unitId) { throw new \RuntimeException('GOV-422: الوحدةُ إلزامية'); }
                if (empty($change['name_ar'])) { throw new \RuntimeException('GOV-422: الاسمُ الجديدُ إلزامي'); }
                $st = $db->prepare("UPDATE org_units SET name_ar = ? WHERE unit_id = ? AND company_id = ?");
                $st->bind_param('sii', $change['name_ar'], $unitId, $companyId);
                $st->execute();
                $st->close();
                break;
            case 'نقل تبعية':
                if (!$unitId || !isset($change['parent_unit_id'])) {
                    throw new \RuntimeException('GOV-422: الوحدةُ والأبُ الجديدُ إلزاميان');
                }
                $newParent = (int) $change['parent_unit_id'] ?: null;
                if ($newParent === $unitId) { throw new \RuntimeException('GOV-422: لا تبعيةَ ذاتية'); }
                $st = $db->prepare("UPDATE org_units SET parent_unit_id = ? WHERE unit_id = ? AND company_id = ?");
                $st->bind_param('iii', $newParent, $unitId, $companyId);
                $st->execute();
                $st->close();
                break;
            case 'تعطيل وحدة':
                if (!$unitId) { throw new \RuntimeException('GOV-422: الوحدةُ إلزامية'); }
                $st = $db->prepare("UPDATE org_units SET active = 0 WHERE unit_id = ? AND company_id = ?");
                $st->bind_param('ii', $unitId, $companyId);
                $st->execute();
                $st->close();
                break;
            case 'رجوع لنسخة':
                // الرجوعُ بقرارٍ: يتطلب version_code سابقًا وتُستعاد لقطتُه
                if (empty($change['revert_to_code'])) {
                    throw new \RuntimeException('GOV-422: نسخةُ الرجوعِ إلزامية');
                }
                $st = $db->prepare("SELECT id, snapshot_json FROM org_structure_versions
                                     WHERE company_id = ? AND version_code = ? LIMIT 1");
                $st->bind_param('is', $companyId, $change['revert_to_code']);
                $st->execute();
                $ver = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$ver) { throw new \RuntimeException('GOV-404: النسخةُ غيرُ موجودة'); }
                $snap = json_decode((string) $ver['snapshot_json'], true) ?: array();
                foreach ($snap as $u) {
                    $st = $db->prepare("UPDATE org_units SET name_ar = ?, layer = ?, parent_unit_id = ?, active = ?
                                         WHERE unit_id = ? AND company_id = ?");
                    $pn = $u['parent_unit_id'] !== null ? (int) $u['parent_unit_id'] : null;
                    $ac = (int) $u['active'];
                    $uidv = (int) $u['unit_id'];
                    $st->bind_param('ssiiii', $u['name_ar'], $u['layer'], $pn, $ac, $uidv, $companyId);
                    $st->execute();
                    $st->close();
                }
                $db->query("UPDATE org_structure_versions SET state = 'reverted'
                             WHERE company_id = {$companyId} AND id > " . (int) $ver['id'] . " AND state = 'applied'");
                break;
        }

        $code = self::nextCode($db, $companyId, 'org_structure_versions', 'version_code', 'ORG');
        $snapJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $changeJson = json_encode($change, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("INSERT INTO org_structure_versions
            (company_id, version_code, change_kind, unit_id, decision_ref, effective_date,
             snapshot_json, change_json, assignments_review_note, changed_by, authority_ref)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $reviewNote = 'التكليفاتُ القائمةُ على الوحدة تُراجَع — org_assignments';
        $st->bind_param('ississsssis',
            $companyId, $code, $changeKind, $unitId, $decisionRef, $effectiveDate,
            $snapJson, $changeJson, $reviewNote, $actor, $authorityRef);
        $st->execute();
        if ($st->errno) { $err = $st->error; $st->close(); throw new \RuntimeException('GOV-500: ' . $err); }
        $id = (int) $db->insert_id;
        $st->close();

        EventPublisher::publishFact($db, array(
            'event_key' => 'governance.org.changed', 'category' => 'operational',
            'source_module' => 'governance', 'company_id' => (int) $companyId,
            'entity_type' => 'org_structure_version', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'org:' . $code,
            'source_ref' => $code,
            'payload' => array('event_name' => 'OrgStructureChanged',
                'consumers' => array('كلُّ الإدارات'), 'kind' => $changeKind, 'unit_id' => $unitId),
        ));
        return array('id' => $id, 'version_code' => $code, 'unit_id' => $unitId);
    }
}
