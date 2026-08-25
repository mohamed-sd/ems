<?php
/**
 * خدمة تغيير حالة الوحدات — UnitStateChangeService (GOV-01 §6 · §10-⑥)
 * ───────────────────────────────────────────────────────────────────────────
 * «المرونة في يد المديرين لا في يد النظام — والنظام يشترط المسار»:
 * طلب بنطاقه وسببه ومستنده **وأثره المقدَّر لكل طرف قبل الإرسال** → السلّم
 * الرباعي بترتيبه (مدير الحركة أولًا ولا يبدأ الطلب بدونه) → Applied من
 * Approved حصرًا · والمقيَّد سابقًا يُعكس بمرجعه ولا يُعدَّل (423).
 * الطوارئ (X15): الإدارة العامة تعتمد مباشرة ويُستكمل التوثيق خلال 48 ساعة
 * وإلا عُكس الأثر آليًّا (sweepEmergency).
 */

namespace App\Services\Governance;

require_once __DIR__ . '/../../Core/AuthorityGuard.php';

use App\Core\AuthorityGuard;

class UnitStateChangeService
{
    /** السلّم الرباعي — الترتيب لا يُعكس */
    const LADDER = array(1 => 'movement_manager', 2 => 'dept_manager', 3 => 'finance', 4 => 'general_management');

    // ═══ DEC-01 ③ · حد الأثر الكبير — بأرقام موقَّعة لا معاملات حرة ═══
    /** 5٪ من قيمة المستخلص/التسوية الشهرية للعقد المعني */
    const GM_PCT = 5.0;
    /** أو ما يعادل عشرة آلاف دولار — **أيهما أقل** */
    const GM_USD_CAP = 10000.0;
    /** الحالات السبع حقًّا جوهريًّا مهما صغر المبلغ (DEC-01 ③) */
    const MATERIAL_RIGHTS = array(
        'client_billing_attribution' => 'تغيير إسناد يمس فوترة العميل',
        'supplier_penalty_waiver'    => 'إعفاء من جزاء تعاقدي على مورد',
        'price_outside_mechanism'    => 'تعديل سعر خارج آلية معرَّفة',
        'advance_refund_or_seizure'  => 'رد مقدم أو مصادرة ضمان',
        'contract_termination'       => 'إنهاء عقد أو تعليقه',
        'ownership_share_change'     => 'تغيير حصة ملكية أصل',
        'employee_waiver_3days'      => 'إعفاء موظف من خصم يتجاوز أجر ثلاثة أيام',
    );

    /**
     * DEC-01 ③: هل يلزم اعتماد الإدارة العامة؟ — **يُحسب في الطلب قبل الإرسال
     * لا يُقدَّر بعد التنفيذ**. شرطان: الأثر > min(5٪ من المرجع الشهري، 10,000$
     * معادلًا) · أو حق جوهري من السبعة مهما صغر المبلغ.
     * @param float      $impactAmount   الأثر المالي المقدَّر (بعملة العقد)
     * @param float|null $monthlyRef     قيمة المستخلص/التسوية الشهرية للعقد
     * @param float|null $usdEquivalent  معادل الأثر بالدولار
     * @param string     $materialCode   كود الحق الجوهري إن وُجد ('' = لا شيء)
     * @return array{needs_gm:bool,reason:string}
     */
    public static function assessGmNeed($impactAmount, $monthlyRef = null, $usdEquivalent = null, $materialCode = '')
    {
        $materialCode = (string) $materialCode;
        if ($materialCode !== '' && isset(self::MATERIAL_RIGHTS[$materialCode])) {
            return array('needs_gm' => true,
                'reason' => 'حق جوهري مهما صغر المبلغ: ' . self::MATERIAL_RIGHTS[$materialCode] . ' (DEC-01 ③)');
        }
        $impactAmount = abs((float) $impactAmount);
        $caps = array();
        if ($monthlyRef !== null && (float) $monthlyRef > 0) {
            $caps['pct'] = round((float) $monthlyRef * self::GM_PCT / 100, 2);
        }
        // حد الدولار يُقاس على المعادل الدولاري؛ وبلا معادل يُفترض 1:1 تحفظًا
        $usd = ($usdEquivalent !== null) ? abs((float) $usdEquivalent) : $impactAmount;
        if (!empty($caps)) {
            // أيهما أقل: سقف النسبة (بعملة العقد) مقابل سقف الدولار (على المعادل)
            $pctHit = $impactAmount > $caps['pct'];
            $usdHit = $usd > self::GM_USD_CAP;
            if ($pctHit || $usdHit) {
                return array('needs_gm' => true, 'reason' => $pctHit
                    ? 'الأثر ' . $impactAmount . ' يتجاوز 5٪ من المرجع الشهري (' . $caps['pct'] . ') — DEC-01 ③'
                    : 'المعادل الدولاري ' . $usd . ' يتجاوز 10,000$ — DEC-01 ③');
            }
            return array('needs_gm' => false, 'reason' => 'دون الحدين (5٪ = ' . $caps['pct'] . ' · 10,000$) ولا حق جوهري');
        }
        if ($usd > self::GM_USD_CAP) {
            return array('needs_gm' => true, 'reason' => 'المعادل الدولاري ' . $usd . ' يتجاوز 10,000$ — DEC-01 ③');
        }
        return array('needs_gm' => false, 'reason' => 'دون حد الدولار ولا مرجع شهري ولا حق جوهري');
    }

    /**
     * DEC-01 ① · تعيين تفويض الحركة — **السقف نطاقي لا نقدي** (مواقع لا مبالغ)
     * لأنه يعتمد وقوع الواقعة لا قيمتها. والنائب بمرجع أصيله **وبمدة مكتوبة
     * إلزامًا** — لا نيابة شفوية ولا مفتوحة المدة (422).
     * @param array $a {person_id, entity_id, site_id?|null (NULL = كل المواقع),
     *                  valid_from, valid_to?, doc_ref, delegated_from_auth_id?}
     */
    public static function appointMovement(\mysqli $conn, $companyId, array $a)
    {
        $isDeputy = !empty($a['delegated_from_auth_id']);
        if ($isDeputy && (empty($a['valid_to']) || (string) $a['valid_to'] === '')) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'النائب المفوض بمدة وسقف مكتوبين — لا تفويض شفوي ولا مفتوح المدة (DEC-01 ①)');
        }
        foreach (array('person_id', 'entity_id', 'valid_from', 'doc_ref') as $f) {
            if (empty($a[$f])) { return array('ok' => false, 'code' => 422, 'reason' => 'حقل إلزامي: ' . $f); }
        }
        $stmt = $conn->prepare(
            "INSERT INTO signing_authorities (company_id, person_id, entity_id, auth_type, amount_cap, currency, scope_type, scope_id, delegated_from_auth_id, valid_from, valid_to, doc_ref, state)
             VALUES (?, ?, ?, 'operational', NULL, NULL, ?, ?, ?, ?, ?, ?, 'active')");
        $companyId = intval($companyId);
        $pid = intval($a['person_id']); $eid = intval($a['entity_id']);
        $scopeType = isset($a['site_id']) && $a['site_id'] !== null && $a['site_id'] !== '' ? 'site' : null;
        $scopeId = ($scopeType !== null) ? intval($a['site_id']) : null;
        $del = $isDeputy ? intval($a['delegated_from_auth_id']) : null;
        $vf = (string) $a['valid_from'];
        $vt = !empty($a['valid_to']) ? (string) $a['valid_to'] : null;
        $doc = (string) $a['doc_ref'];
        $stmt->bind_param('iiisiisss', $companyId, $pid, $eid, $scopeType, $scopeId, $del, $vf, $vt, $doc);
        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close();
            return array('ok' => false, 'code' => 422, 'reason' => 'تعذر التعيين: ' . $err);
        }
        $id = intval($stmt->insert_id); $stmt->close();
        return array('ok' => true, 'code' => 201, 'auth_id' => $id,
            'reason' => ($isDeputy ? 'نائب مفوض' : 'تفويض حركة') . ' #' . $id
                . ' — نطاقه ' . ($scopeType === 'site' ? 'الموقع #' . $scopeId : 'كل المواقع') . ' (سقف نطاقي لا نقدي)');
    }

    /**
     * DEC-01 ①: هل يملك المعتمِد تفويضَ حركةٍ نافذًا يغطي نطاق الطلب؟
     * من له تفويضات operational → يجب أن يغطي أحدُها النطاق (403 خلافه)؛
     * ومن لا تفويضات له إطلاقًا → يمضي على سلوك النمط النافذ (AuthorityGuard).
     */
    public static function movementScopeCovers(\mysqli $conn, $companyId, $personId, $scopeType, $scopeId)
    {
        $companyId = intval($companyId); $personId = intval($personId);
        $stmt = $conn->prepare(
            "SELECT scope_type, scope_id FROM signing_authorities
              WHERE company_id = ? AND person_id = ? AND auth_type = 'operational' AND state = 'active'
                AND valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())");
        $stmt->bind_param('ii', $companyId, $personId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (empty($rows)) { return null; } // لا تفويضات — يُترك للنمط النافذ
        foreach ($rows as $r) {
            if ($r['scope_type'] === null || $r['scope_type'] === '') { return true; } // عام: كل المواقع
            if ((string) $scopeType === 'site' && (string) $r['scope_type'] === 'site'
                && intval($r['scope_id']) === intval($scopeId)) { return true; }
            if ((string) $scopeType !== 'site') { return true; } // نطاق غير موقعي — يكفي تفويض تشغيلي نافذ
        }
        return false;
    }

    /**
     * إنشاء طلب تغيير — الأثر المقدَّر لكل طرف إلزامي قبل الإرسال.
     * @return array{ok:bool,code:int,reason:string,chg_id:int,needs_gm:bool}
     */
    public static function request(\mysqli $conn, $companyId, array $a)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'chg_id' => 0, 'needs_gm' => false);
        foreach (array('scope_type', 'scope_id', 'date_from', 'date_to', 'field_changed', 'value_before', 'value_after', 'reason', 'doc_ref', 'estimated_impact', 'requested_by') as $f) {
            if (!isset($a[$f]) || $a[$f] === '' || $a[$f] === null) {
                $out['code'] = 422; $out['reason'] = 'حقل إلزامي مفقود: ' . $f . ' — لا طلب بلا نطاق وسبب ومستند وأثر مقدر';
                return $out;
            }
        }
        if (!is_array($a['estimated_impact']) || empty($a['estimated_impact'])) {
            $out['code'] = 422; $out['reason'] = 'الأثر المقدر لكل طرف إلزامي — يحسب قبل الإرسال لا بعده';
            return $out;
        }
        $companyId = intval($companyId);
        $stmt = $conn->prepare(
            'INSERT INTO unit_state_changes (company_id, scope_type, scope_id, date_from, date_to, field_changed, value_before, value_after, reason, doc_ref, estimated_impact_json, state, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', ?)');
        $st = (string) $a['scope_type']; $sid = intval($a['scope_id']);
        $df = (string) $a['date_from']; $dt = (string) $a['date_to'];
        $fc = (string) $a['field_changed']; $vb = (string) $a['value_before']; $va = (string) $a['value_after'];
        $rs = (string) $a['reason']; $doc = (string) $a['doc_ref'];
        $imp = json_encode($a['estimated_impact'], JSON_UNESCAPED_UNICODE);
        $by = intval($a['requested_by']);
        $stmt->bind_param('isissssssssi', $companyId, $st, $sid, $df, $dt, $fc, $vb, $va, $rs, $doc, $imp, $by);
        if (!$stmt->execute()) { $out['code'] = 422; $out['reason'] = 'تعذر الإنشاء: ' . $stmt->error; $stmt->close(); return $out; }
        $out['chg_id'] = intval($stmt->insert_id);
        $stmt->close();
        // DEC-01 ③: الحد محسوب في الطلب قبل الإرسال — 5٪ أو 10,000$ أيهما أقل،
        // والحقوق الجوهرية السبعة مهما صغر المبلغ. (المعاملان القديمان يبقيان
        // تجاوزًا صريحًا للرفع لا للخفض — الدرجة تُرفع لا تُخفض.)
        $gm = self::assessGmNeed(
            isset($a['impact_amount']) ? (float) $a['impact_amount'] : 0.0,
            isset($a['monthly_ref_value']) ? (float) $a['monthly_ref_value'] : null,
            isset($a['impact_usd_equivalent']) ? (float) $a['impact_usd_equivalent'] : null,
            isset($a['material_right_code']) ? (string) $a['material_right_code'] : ''
        );
        $out['needs_gm'] = $gm['needs_gm'] || !empty($a['exceeds_threshold']) || !empty($a['touches_material_right']);
        $out['gm_reason'] = $gm['reason'];
        $out['ok'] = true; $out['code'] = 201;
        $out['reason'] = 'طلب #' . $out['chg_id'] . ' — السلم: مدير الحركة ← الإدارة المعنية ← المالية'
            . ($out['needs_gm'] ? ' ← الإدارة العامة (أثر كبير أو حق جوهري)' : '');
        return $out;
    }

    /**
     * خطوة موافقة — لا تُفتح خطوة قبل اكتمال ما قبلها (409)، ومدير الحركة أول
     * السلّم (بدونه 422)، وApplied من Approved حصرًا.
     */
    public static function approveStep(\mysqli $conn, $companyId, $chgId, $seqNo, $personId, $decision = 'approve', $note = '', $needsGm = false)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => '');
        $chgId = intval($chgId); $seqNo = intval($seqNo); $personId = intval($personId);
        $stmt = $conn->prepare('SELECT * FROM unit_state_changes WHERE chg_id = ? LIMIT 1');
        $stmt->bind_param('i', $chgId);
        $stmt->execute();
        $chg = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$chg) { $out['code'] = 404; $out['reason'] = 'الطلب غير موجود'; return $out; }
        if ((string) $chg['state'] !== 'Pending') { $out['code'] = 409; $out['reason'] = 'الطلب ليس معلقا — حاله ' . $chg['state']; return $out; }
        if (!isset(self::LADDER[$seqNo])) { $out['code'] = 422; $out['reason'] = 'خطوة خارج السلم الرباعي'; return $out; }

        // الترتيب: كل الخطوات السابقة مكتملة موافَقةً
        $stmt = $conn->prepare('SELECT seq_no FROM change_approvals WHERE chg_id = ? AND decision = \'approve\'');
        $stmt->bind_param('i', $chgId);
        $stmt->execute();
        $done = array_map(function ($r) { return intval($r['seq_no']); }, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmt->close();
        for ($i = 1; $i < $seqNo; $i++) {
            if (!in_array($i, $done, true)) {
                $out['code'] = ($i === 1) ? 422 : 409;
                $out['reason'] = ($i === 1)
                    ? 'بلا موافقة مدير الحركة لا يبدأ الطلب — هو مالك الواقعة التشغيلية'
                    : 'خطوة قبل سابقتها — تعلق حتى تكتمل السابقة (' . self::LADDER[$i] . ')';
                return $out;
            }
        }
        if (in_array($seqNo, $done, true)) { $out['code'] = 409; $out['reason'] = 'الخطوة موقعة سلفا'; return $out; }

        // DEC-01 ①: خطوة مدير الحركة — السقف نطاقي: تفويضه يجب أن يغطي
        // نطاق الطلب (الموقع/المشروع)؛ ومن لا تفويض حركة له يمضي على النمط النافذ.
        if ($seqNo === 1) {
            $covers = self::movementScopeCovers($conn, $companyId, $personId, (string) $chg['scope_type'], intval($chg['scope_id']));
            if ($covers === false) {
                $out['code'] = 403;
                $out['reason'] = 'تفويض الحركة لا يغطي هذا النطاق — السقف نطاقي (مواقع لا مبالغ · DEC-01 ①)؛ يعتمدها صاحب النطاق أو نائبه المفوض بمدته';
                return $out;
            }
        }

        $sig = AuthorityGuard::sign($conn, array(
            'company_id' => intval($companyId), 'document_type' => 'unit_state_change', 'document_id' => $chgId,
            'step' => self::LADDER[$seqNo], 'person_id' => $personId,
            'created_by_person_id' => intval($chg['requested_by']),
        ));
        if (!$sig['ok']) { return array_merge($out, array('code' => $sig['code'], 'reason' => $sig['reason'])); }

        $role = self::LADDER[$seqNo];
        $stmt = $conn->prepare('INSERT INTO change_approvals (chg_id, seq_no, approver_person_id, role, auth_id, decision, reason) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $authId = $sig['auth_id'];
        $stmt->bind_param('iiisiss', $chgId, $seqNo, $personId, $role, $authId, $decision, $note);
        $stmt->execute();
        $stmt->close();

        if ($decision === 'reject') {
            $conn->query("UPDATE unit_state_changes SET state = 'Rejected' WHERE chg_id = {$chgId}");
            $out['ok'] = true; $out['code'] = 200; $out['state'] = 'Rejected'; $out['reason'] = 'رفض بسبب محكوم';
            return $out;
        }
        $required = $needsGm ? 4 : 3;
        if ($seqNo >= $required) {
            $conn->query("UPDATE unit_state_changes SET state = 'Approved' WHERE chg_id = {$chgId}");
            $out['state'] = 'Approved';
            $out['reason'] = 'اكتمل السلم — الطلب معتمد وجاهز للتطبيق';
        } else {
            $out['state'] = 'Pending';
            $out['reason'] = 'موافقة ' . $seqNo . '/' . $required . ' (' . $role . ')';
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * التطبيق — من Approved حصرًا. وإن كان الأثر السابق **مقيَّدًا** (posted_ref)
     * فالمسار عكسٌ بمرجعه لا تعديل (423 على التعديل المباشر).
     */
    public static function apply(\mysqli $conn, $companyId, $chgId, $actor, $priorEffectPosted = false, $reversalRef = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $chgId = intval($chgId);
        $stmt = $conn->prepare('SELECT state FROM unit_state_changes WHERE chg_id = ? LIMIT 1');
        $stmt->bind_param('i', $chgId);
        $stmt->execute();
        $chg = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$chg) { $out['code'] = 404; $out['reason'] = 'غير موجود'; return $out; }
        if ((string) $chg['state'] !== 'Approved') {
            $out['code'] = 422; $out['reason'] = 'لا Applied إلا من Approved — الحال ' . $chg['state'];
            return $out;
        }
        if ($priorEffectPosted && ($reversalRef === null || $reversalRef === '')) {
            $out['code'] = 423;
            $out['reason'] = 'الأثر السابق مقيد — **يعكس بمرجعه ولا يعدل**، مرر reversal_ref لحدث العكس';
            return $out;
        }
        $ref = $reversalRef !== null ? "'" . $conn->real_escape_string((string) $reversalRef) . "'" : 'NULL';
        $conn->query("UPDATE unit_state_changes SET state = 'Applied', applied_at = NOW(), reversal_ref = {$ref} WHERE chg_id = {$chgId}");
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'طبق — الحالة الأصلية والجديدة محفوظتان معا بسببهما وموافقيهما'
            . ($priorEffectPosted ? ' · والقديم عكس بمرجع ' . $reversalRef : '');
        return $out;
    }

    /**
     * الطوارئ (GOV-01 §4.1-④ · X15): الإدارة العامة تعتمد مباشرة — ويُستكمل
     * توثيق الثلاث خلال 48 ساعة وإلا عُكس الأثر آليًّا.
     */
    public static function emergencyApply(\mysqli $conn, $companyId, $chgId, $gmPersonId)
    {
        $chgId = intval($chgId);
        $sig = AuthorityGuard::sign($conn, array(
            'company_id' => intval($companyId), 'document_type' => 'unit_state_change', 'document_id' => $chgId,
            'step' => 'general_management_emergency', 'person_id' => intval($gmPersonId),
        ));
        if (!$sig['ok']) { return array('ok' => false, 'code' => $sig['code'], 'reason' => $sig['reason']); }
        $conn->query("UPDATE unit_state_changes SET state = 'Applied', applied_at = NOW(), reversal_ref = 'EMERGENCY-PENDING-DOCS' WHERE chg_id = {$chgId} AND state IN ('Pending','Approved')");
        return array('ok' => true, 'code' => 200, 'reason' => 'طوارئ: طبق باعتماد الإدارة العامة — يستكمل توثيق الثلاث خلال 48 ساعة وإلا عكس آليا');
    }

    /** كنس الطوارئ: 48 ساعة بلا اكتمال الثلاث → عكس آلي ورفع للمراجعة. */
    public static function sweepEmergency(\mysqli $conn)
    {
        $rows = $conn->query(
            "SELECT c.chg_id, c.company_id FROM unit_state_changes c
              WHERE c.state = 'Applied' AND c.reversal_ref = 'EMERGENCY-PENDING-DOCS'
                AND c.applied_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
                AND (SELECT COUNT(*) FROM change_approvals a WHERE a.chg_id = c.chg_id AND a.decision = 'approve' AND a.seq_no <= 3) < 3"
        )->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) {
            $id = intval($r['chg_id']);
            $conn->query("UPDATE unit_state_changes SET state = 'Reversed', reversal_ref = 'EMERGENCY-AUTOREVERSED' WHERE chg_id = {$id}");
            $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                          VALUES (" . intval($r['company_id']) . ", 'finance_manager', 'طوارئ #{$id}: لم يستكمل التوثيق خلال 48 ساعة — عكس الأثر آليا ورفع للمراجعة', 'admin/bus_monitor.php')");
        }
        return count($rows);
    }

    /**
     * DEC-01 ③ (المراجعة): يُراجَع الحد بعد ثلاثة أشهر **بالبيانات لا بالرأي**:
     * ما يصل الإدارة العامة > 10 طلبات شهريًّا → الحد منخفض ويُرفع؛
     * وأقل من 2 → مرتفع ويُخفض. العدّ من خطوات السلّم الرابعة.
     * @return array{months:array<string,int>,recommendation:string}
     */
    public static function gmLoadReview(\mysqli $conn, $companyId, $months = 3)
    {
        $companyId = intval($companyId); $months = max(1, intval($months));
        $stmt = $conn->prepare(
            "SELECT DATE_FORMAT(a.at, '%Y-%m') ym, COUNT(*) c
               FROM change_approvals a
               JOIN unit_state_changes c2 ON c2.chg_id = a.chg_id
              WHERE c2.company_id = ? AND a.seq_no = 4
                AND a.at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
              GROUP BY ym ORDER BY ym");
        $stmt->bind_param('ii', $companyId, $months);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $byMonth = array();
        foreach ($rows as $r) { $byMonth[(string) $r['ym']] = intval($r['c']); }
        $max = empty($byMonth) ? 0 : max($byMonth);
        if ($max > 10) {
            $rec = 'الحد منخفض (' . $max . ' طلبا في شهر > 10) — يرفع بقرار لاحق بالأرقام';
        } elseif ($max < 2) {
            $rec = 'الحد مرتفع (' . $max . ' < 2 شهريا) — يخفض بقرار لاحق بالأرقام';
        } else {
            $rec = 'الحد مناسب (' . $max . ' شهريا ضمن [2،10]) — لا تعديل';
        }
        return array('months' => $byMonth, 'recommendation' => $rec);
    }
}
