<?php

namespace App\Services\Risk;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/RiskEvents.php';

/**
 * RiskService — قلب M-16 (update0011 · DEC-G نافذ · أُكمل 2026-08-08)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحراس الحاكمة المنفَّذة هنا (لا في الشاشات):
 *   RK-03  لا إغلاق بلا دليل تنفيذ + إعادة تقييم + قبول بالسقف — closeRisk().
 *   RK-04  مصفوفة السلطة الخمسية — acceptRisk() ترفض القبول فوق السقف وتصعّد.
 *   RK-05  الإشارة تُفرز (تُهمل بوسم/تُربط/تُنشئ/تُصعَّد) — triageSignal().
 *   RK-07  الضابط الحرج: المتحقق ≠ المالك — verifyControl().
 *   RK-08  الحرج يصعَّد للرئيس آليًّا في اليوم نفسه — escalate() من كل مسار.
 *   ورقة 32: مفتاح التكرار (وحدة+كيان+سبب جذري+نطاق) بنافذة الوحدة الزمنية —
 *            dedupCandidates() يعرض المطابق والدمج بقرار المحلل mergeRisks().
 *   لا حذف إطلاقًا — لا توجد دالة حذف في هذه الخدمة عمدًا.
 *
 * الأثر العابر (§7-3 · §10): لا نداءَ مباشرًا إلى جدولِ إدارةٍ أخرى من هنا.
 * كلُّ فعلٍ كاتبٍ ينشر حقيقتَه بـRiskEvents::fire() ومن يعنيه يستهلكها.
 * والاستثناءُ الوحيدُ المصرَّحُ به: work_items عبر WorkItemService — وهو مساحةُ
 * العملِ الشخصيةُ المشتركةُ في المنصة لا جدولُ إدارةٍ (§15-9: الإجراءُ المسنَدُ
 * يظهر في مهامِّ مسؤوله)، ويُكتب بخدمتِه المالكةِ لا بإدراجٍ مباشر.
 */
class RiskService
{
    /** مصفوفة السلطة (ورقة 27): المستوى ⇒ السلطة الدنيا اللازمة للقبول */
    const AUTHORITY_MATRIX = array(
        'منخفض' => 'risk_owner',
        'متوسط' => 'owner_with_analyst',
        'مرتفع' => 'deputy',
        'حرج'   => 'ceo',
        // «محظور» غائب عمدًا — لا يُقبل بحال (RK-04)
    );

    /** دورية المراجعة بالمستوى (ورقة 27) بالأيام */
    const REVIEW_DAYS = array('منخفض' => 365, 'متوسط' => 182, 'مرتفع' => 91, 'حرج' => 30);

    /**
     * §11-3 ⇄ §13-1: وحدةُ المخاطرِ الإحدى عشرةَ إلى مجالِ الشهيةِ الثمانية.
     * المطابقةُ ليست واحدًا لواحدٍ عمدًا: الوحدةُ تصنيفُ منشأِ الخطرِ، والمجالُ
     * تصنيفُ ما يُقايَض عليه. فالتشغيلُ والصيانةُ والنقلُ والإمدادُ كلُّها تُقاس
     * بشهيةِ «التشغيلِ والإنتاج»، والسلامةُ لها مجالُها الذي لا تُرفع شهيتُه بحال.
     */
    const RU_DOMAIN = array(
        'RU-01' => 'السمعة',              // المؤسسيةُ والاستراتيجيةُ — أثرُها سمعيٌّ أولًا
        'RU-02' => 'التشغيل والإنتاج',
        'RU-03' => 'التشغيل والإنتاج',
        'RU-04' => 'الأفراد والقدرة',
        'RU-05' => 'التشغيل والإنتاج',
        'RU-06' => 'التشغيل والإنتاج',
        'RU-07' => 'التعاقد والتجارة',
        'RU-08' => 'المال والتعرض',
        'RU-09' => 'المال والتعرض',
        'RU-10' => 'السلامة والصحة',      // ◆ أرضيةٌ لا تتغير
        'RU-11' => 'البيانات والتقنية',   // ◆ أرضيةٌ في التسرب
    );

    /** تحويل الدرجة (احتمال × أقصى أثر) إلى مستوى — مصفوفة 5×5 قياسية */
    public static function levelFromScore($likelihood, $impactMax)
    {
        $score = (int) $likelihood * (int) $impactMax;
        if ($score >= 20) { return 'حرج'; }
        if ($score >= 12) { return 'مرتفع'; }
        if ($score >= 6)  { return 'متوسط'; }
        return 'منخفض';
    }

    /** مفتاح التكرار المركب (ورقة 32) — الإدارة العارضة لا تدخل المفتاح عمدًا */
    public static function dedupKey($ruId, $entityType, $entityId, $rootCause, $scopeRefType, $scopeRefId)
    {
        $norm = function ($s) {
            $s = trim(mb_strtolower((string) $s, 'UTF-8'));
            return preg_replace('~\s+~u', ' ', $s);
        };
        return sha1(implode('|', array(
            (int) $ruId,
            $norm($entityType) . '#' . (int) $entityId,
            $norm($rootCause),
            $norm($scopeRefType) . '#' . (int) $scopeRefId,
        )));
    }

    /** المرشحون للدمج: نفس المفتاح داخل نافذة الوحدة — «خطر واحد بزاويتين» */
    public static function dedupCandidates(\mysqli $db, $companyId, $key, $ruId)
    {
        $win = 90;
        if ($st = $db->prepare('SELECT dedup_window_days FROM risk_units WHERE id = ? AND company_id = ?')) {
            $st->bind_param('ii', $ruId, $companyId);
            $st->execute();
            if ($r = $st->get_result()->fetch_assoc()) { $win = (int) $r['dedup_window_days']; }
            $st->close();
        }
        $rows = array();
        $st = $db->prepare("SELECT id, risk_code, title, state, current_level, created_at
                              FROM risk_register
                             WHERE company_id = ? AND dedup_key = ? AND merged_into_id IS NULL
                               AND state <> 'closed'
                               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $st->bind_param('isi', $companyId, $key, $win);
        $st->execute();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $rows[] = $x; }
        $st->close();
        return $rows;
    }

    /**
     * إنشاء إشارة — بعطالتين:
     *   • الميدانية بـsync_uuid (§7-2: «الإعادةُ ترجع مرجعَ الأولِ ولا تُنشئ ثانيًا»).
     *   • الآليةُ بـrule_key (§13-5: قاعدةٌ واحدةٌ لواقعةٍ واحدةٍ لا تُطلق إشارتين).
     * وكلتاهما تعود idempotent=true بمرجعِ الأولى — لا استثناءَ ولا صفٌّ جديد.
     */
    public static function createSignal(\mysqli $db, $companyId, array $d, $userId)
    {
        if (!empty($d['sync_uuid'])) {
            $st = $db->prepare('SELECT id FROM risk_signals WHERE sync_uuid = ?');
            $st->bind_param('s', $d['sync_uuid']);
            $st->execute();
            if ($x = $st->get_result()->fetch_assoc()) { $st->close(); return array('id' => (int) $x['id'], 'idempotent' => true); }
            $st->close();
        }
        if (!empty($d['rule_key'])) {
            $st = $db->prepare('SELECT id FROM risk_signals WHERE company_id = ? AND rule_key = ?');
            $st->bind_param('is', $companyId, $d['rule_key']);
            $st->execute();
            if ($x = $st->get_result()->fetch_assoc()) { $st->close(); return array('id' => (int) $x['id'], 'idempotent' => true); }
            $st->close();
        }
        $st = $db->prepare('INSERT INTO risk_signals
            (company_id, sg_code, rule_key, source, title, details, ru_hint_id, entity_type, entity_id,
             scope_ref_type, scope_ref_id, root_cause, site_id, shift_ar, equipment_id, photo_ref, sync_uuid, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $sg = isset($d['sg_code']) ? $d['sg_code'] : null;
        $ruleKey = !empty($d['rule_key']) ? (string) $d['rule_key'] : null;
        $source = in_array($d['source'] ?? 'manual', array('auto', 'manual', 'field'), true) ? $d['source'] : 'manual';
        $ru = !empty($d['ru_hint_id']) ? (int) $d['ru_hint_id'] : null;
        $et = $d['entity_type'] ?? null; $ei = !empty($d['entity_id']) ? (int) $d['entity_id'] : null;
        $srt = $d['scope_ref_type'] ?? null; $sri = !empty($d['scope_ref_id']) ? (int) $d['scope_ref_id'] : null;
        $rc = (string) ($d['root_cause'] ?? '');
        $site = !empty($d['site_id']) ? (int) $d['site_id'] : null;
        $shift = $d['shift_ar'] ?? null;
        $eq = !empty($d['equipment_id']) ? (int) $d['equipment_id'] : null;
        $photo = $d['photo_ref'] ?? null; $uuid = $d['sync_uuid'] ?? null;
        $title = (string) $d['title']; $details = $d['details'] ?? null;
        $st->bind_param('isssssisisisisissi', $companyId, $sg, $ruleKey, $source, $title, $details, $ru, $et, $ei,
            $srt, $sri, $rc, $site, $shift, $eq, $photo, $uuid, $userId);
        $st->execute();
        $id = $db->insert_id;
        $st->close();
        RiskEvents::fire($db, $companyId, 'RiskSignalRaised', $id, array(
            'sg_code' => $sg, 'source' => $source, 'title' => $title,
            'ru_hint_id' => $ru, 'root_cause' => $rc, 'field' => ($source === 'field'),
        ), $userId);
        return array('id' => (int) $id, 'idempotent' => false);
    }

    /**
     * الفرز (RK-05): dismiss | link | convert | escalate — بسبب مكتوب دائمًا.
     * الإهمال يوسم ولا يُحذف. والربط يفتح إعادة تقييم للقائم لا خطرًا جديدًا.
     */
    public static function triageSignal(\mysqli $db, $companyId, $signalId, $decision, $reason, $userId, array $extra = array())
    {
        $st = $db->prepare('SELECT * FROM risk_signals WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $signalId, $companyId);
        $st->execute();
        $sig = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$sig) { throw new \RuntimeException('RSK-404: الإشارة غير موجودة في نطاقك'); }
        if ($sig['state'] !== 'pending') { throw new \RuntimeException('RSK-409: الإشارة مفروزة سلفًا (' . $sig['state'] . ')'); }
        if (trim((string) $reason) === '') { throw new \RuntimeException('RSK-422: قرار الفرز بسببه المكتوب — لا فرز صامتًا'); }

        $linkedRiskId = null;
        $newState = null;
        switch ($decision) {
            case 'dismiss':
                $newState = 'dismissed';
                break;
            case 'link':
                $linkedRiskId = (int) ($extra['risk_id'] ?? 0);
                if ($linkedRiskId <= 0) { throw new \RuntimeException('RSK-422: الربط يتطلب الخطر القائم'); }
                // «ويحدَّث تقييم الخطر القائم لا يُنشأ جديد» — يفتح إعادة التقييم
                $up = $db->prepare("UPDATE risk_register SET state = 'reassessment' WHERE id = ? AND company_id = ? AND state <> 'closed'");
                $up->bind_param('ii', $linkedRiskId, $companyId);
                $up->execute(); $up->close();
                $newState = 'linked';
                break;
            case 'convert':
                $risk = null;
                $risk = self::createRisk($db, $companyId, array(
                    'ru_id' => (int) ($extra['ru_id'] ?: $sig['ru_hint_id']),
                    'title' => $extra['title'] ?: $sig['title'],
                    'description' => $sig['details'],
                    'scope_type' => $extra['scope_type'] ?? 'إداري',
                    'scope_ref_type' => $sig['scope_ref_type'], 'scope_ref_id' => $sig['scope_ref_id'],
                    'entity_type' => $sig['entity_type'], 'entity_id' => $sig['entity_id'],
                    'root_cause' => $extra['root_cause'] ?: $sig['root_cause'],
                    'owner_unit_id' => $extra['owner_unit_id'] ?? null,
                ), $userId, !empty($extra['force_duplicate']));
                if (empty($risk['id'])) {
                    // مطابقٌ قائم داخل النافذة — لا تحويل أعمى (ورقة 32: الدمج بقرار)
                    $dupCodes = implode('، ', array_map(function ($d) { return $d['risk_code']; }, $risk['duplicates']));
                    throw new \RuntimeException('RSK-409: خطر مطابق قائم (' . $dupCodes . ') — اربط الإشارة به أو أكد الإنشاء بقرار مسبَّب');
                }
                $linkedRiskId = $risk['id'];
                $newState = 'converted';
                break;
            case 'escalate':
                // «تعرض حرج لا يحتمل الدورة — يصل للرئيس في اليوم نفسه»
                self::escalate($db, $companyId, null, $signalId, 'فرز: تعرض حرج — ' . $reason, 'ceo', $userId);
                $newState = 'escalated';
                break;
            default:
                throw new \RuntimeException('RSK-422: قرار فرز غير معروف');
        }

        $st = $db->prepare('UPDATE risk_signals SET state = ?, triage_by = ?, triage_reason = ?, triaged_at = NOW(), linked_risk_id = ? WHERE id = ?');
        $st->bind_param('sisii', $newState, $userId, $reason, $linkedRiskId, $signalId);
        $st->execute(); $st->close();
        RiskEvents::fire($db, $companyId, 'RiskSignalTriaged', $signalId, array(
            'decision' => $decision, 'state' => $newState,
            'reason' => $reason, 'risk_id' => $linkedRiskId,
        ), $userId);
        return array('state' => $newState, 'risk_id' => $linkedRiskId);
    }

    /** إنشاء خطر (بعد الفرز) — بفحص التكرار المركب أولًا */
    public static function createRisk(\mysqli $db, $companyId, array $d, $userId, $forceDuplicate = false)
    {
        $ruId = (int) $d['ru_id'];
        if ($ruId <= 0) { throw new \RuntimeException('RSK-422: وحدة المخاطر إلزامية للتصنيف'); }
        $key = self::dedupKey($ruId, $d['entity_type'] ?? '', $d['entity_id'] ?? 0,
            $d['root_cause'] ?? '', $d['scope_ref_type'] ?? '', $d['scope_ref_id'] ?? 0);
        $dups = self::dedupCandidates($db, $companyId, $key, $ruId);
        if (!empty($dups) && !$forceDuplicate) {
            return array('id' => 0, 'duplicates' => $dups, 'dedup_key' => $key,
                'hint' => 'خطر بالمفتاح نفسه داخل النافذة — اربط الإشارة به أو أكد الإنشاء بقرار محلل مسبَّب');
        }
        // الرمز التسلسلي RSK-000001 لكل شركة — بالمُرقِّمِ الواحدِ لا باستعلامٍ
        // ثانٍ هنا (INJ-0631: النسخةُ المحليةُ كانت تقتطع بالموضعِ فتلتقط بادئةً
        // غريبةً وتلفُّ إلى 1.8e19). الحسابُ والحراسةُ في nextCode وحدَها.
        $code = self::nextCode($db, $companyId, 'risk_register', 'risk_code', 'RSK-', 6);

        $st = $db->prepare('INSERT INTO risk_register
            (company_id, risk_code, ru_id, title, description, scope_type, scope_ref_type, scope_ref_id,
             entity_type, entity_id, root_cause, owner_unit_id, risk_owner_user_id, dedup_key, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $title = (string) $d['title']; $desc = $d['description'] ?? null;
        $scopeType = in_array($d['scope_type'] ?? '', array('مؤسسي', 'إداري', 'مشروعي', 'موقعي'), true) ? $d['scope_type'] : 'إداري';
        $srt = $d['scope_ref_type'] ?? null; $sri = !empty($d['scope_ref_id']) ? (int) $d['scope_ref_id'] : null;
        $et = $d['entity_type'] ?? null; $ei = !empty($d['entity_id']) ? (int) $d['entity_id'] : null;
        $rc = (string) ($d['root_cause'] ?? '');
        /* ── INJ-0577 · الوحدةُ المالكةُ تُتحقَّق لا تُقبل رقمًا ─────────────────
             كان الحقلُ رقمًا حرًّا في الشاشة و`(int)` في الخدمة — فأيُّ رقمٍ يُحفظ:
             وحدةُ شركةٍ أخرى، أو رقمٌ لا وجودَ له. والوصلُ `LEFT JOIN org_units`
             يبتلع الخطأَ صامتًا فيظهر الخطرُ بمالكٍ فارغٍ لا بخطأ.
             فيُرفض هنا كلُّ ما ليس وحدةً نشطةً **لهذا الكيان**. */
        $ou = !empty($d['owner_unit_id']) ? (int) $d['owner_unit_id'] : null;
        if ($ou !== null) {
            $chk = $db->prepare('SELECT unit_id FROM org_units
                                  WHERE unit_id = ? AND company_id = ? AND COALESCE(active,1) = 1');
            $chk->bind_param('ii', $ou, $companyId);
            $chk->execute();
            $found = (bool) $chk->get_result()->fetch_row();
            $chk->close();
            if (!$found) {
                return array('id' => 0, 'duplicates' => array(), 'error' => 'RSK-UNIT-422',
                    'hint' => 'الإدارة المالكة ليست وحدةً نشطةً في هيكل هذا الكيان');
            }
        }
        $rowner = !empty($d['risk_owner_user_id']) ? (int) $d['risk_owner_user_id'] : null;
        $st->bind_param('isissssisisiisi', $companyId, $code, $ruId, $title, $desc, $scopeType,
            $srt, $sri, $et, $ei, $rc, $ou, $rowner, $key, $userId);
        $st->execute();
        $id = (int) $db->insert_id;
        $st->close();
        RiskEvents::fire($db, $companyId, 'RiskRegistered', $id, array(
            'risk_code' => $code, 'ru_id' => $ruId, 'title' => $title,
            'scope_type' => $scopeType, 'owner_unit_id' => $ou, 'root_cause' => $rc,
        ), $userId);
        // المرحلة ٣ «التصنيف» تقع مع التسجيل هنا (الوحدةُ والنطاقُ إلزاميان في
        // الإدراج) — فتُنشر حقيقتُها منفصلةً ليستهلكها من يراقب التصنيفَ وحدَه.
        RiskEvents::fire($db, $companyId, 'RiskClassified', $id, array(
            'ru_id' => $ruId, 'scope_type' => $scopeType, 'risk_code' => $code,
        ), $userId);
        if ($rowner) {
            RiskEvents::fire($db, $companyId, 'RiskOwnerAssigned', $id, array(
                'risk_owner_user_id' => $rowner, 'owner_unit_id' => $ou, 'at_creation' => true,
            ), $userId);
        }
        return array('id' => $id, 'risk_code' => $code, 'duplicates' => array());
    }

    /**
     * تقييم (متأصل/متبقٍ/مستهدف) — إدراج نسخة مؤرخة فقط (RK-03)،
     * وتحديث المستوى الجاري على السجل + التصعيد الآلي للحرج (RK-08).
     */
    /** وجود الخطر في نطاق الشركة — تفشل به كل الأفعال المرجعية قبل أي كتابة */
    public static function requireRisk(\mysqli $db, $companyId, $riskId)
    {
        $st = $db->prepare('SELECT id FROM risk_register WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $ok = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ok) { throw new \RuntimeException('RSK-404: الخطر غير موجود في نطاقك'); }
    }

    public static function assess(\mysqli $db, $companyId, $riskId, $type, $likelihood, array $impacts, $confidence, $technique, $userId, $note = null, $challengedBy = null)
    {
        self::requireRisk($db, $companyId, (int) $riskId);
        if (!in_array($type, array('inherent', 'residual', 'target'), true)) { throw new \RuntimeException('RSK-422: نوع تقييم غير معروف'); }
        $likelihood = max(1, min(5, (int) $likelihood));
        $impactMax = 0;
        foreach ($impacts as $v) { $impactMax = max($impactMax, max(0, min(5, (int) $v))); }
        if ($impactMax === 0) { throw new \RuntimeException('RSK-422: التقييم بالأبعاد الثمانية — بعد واحد على الأقل'); }
        $score = $likelihood * $impactMax;
        $level = self::levelFromScore($likelihood, $impactMax);
        $json = json_encode($impacts, JSON_UNESCAPED_UNICODE);
        $conf = in_array($confidence, array('عالية', 'متوسطة', 'منخفضة'), true) ? $confidence : 'متوسطة';

        // §9-1 المرجعُ الأب: النسخةُ السابقةُ من نوعِ التقييمِ نفسِه — فتُقرأ سلسلةُ
        // تطورِ الخطرِ عبرَ الزمنِ حلقةً حلقةً (شرطُ مراجعةٍ مهنيةٍ · §12 صدرًا).
        $st = $db->prepare("SELECT id FROM risk_assessments
                             WHERE company_id = ? AND risk_id = ? AND assess_type = ?
                             ORDER BY assessed_at DESC, id DESC LIMIT 1");
        $st->bind_param('iis', $companyId, $riskId, $type);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $parentRef = $prev ? ('ASMT-' . (int) $prev['id']) : '';

        $st = $db->prepare('INSERT INTO risk_assessments
            (company_id, risk_id, assess_type, likelihood, impacts_json, impact_max, score, level, confidence, technique, assessed_by, challenged_by, note, parent_ref)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->bind_param('iisisiisssiiss', $companyId, $riskId, $type, $likelihood, $json, $impactMax, $score, $level, $conf, $technique, $userId, $challengedBy, $note, $parentRef);
        $st->execute();
        $assessmentId = (int) $db->insert_id;
        $st->close();

        $evName = $type === 'inherent' ? 'RiskAssessedInherent'
                : ($type === 'residual' ? 'RiskAssessedResidual' : 'RiskAssessedTarget');
        // العطالةُ بلاحقةِ معرّفِ النسخة: التقييمُ يتكرر على الخطرِ نفسِه نسخًا،
        // فمفتاحٌ حتميٌّ من (المفتاح+الكيان) وحدَه لاختزل الثانيةَ في الأولى.
        RiskEvents::fire($db, $companyId, $evName, $riskId, array(
            'assessment_id' => $assessmentId, 'assess_type' => $type,
            'likelihood' => $likelihood, 'impact_max' => $impactMax, 'score' => $score,
            'level' => $level, 'confidence' => $conf, 'technique' => $technique,
            'impacts' => $impacts, 'parent_ref' => $parentRef,
            'challenged_by' => $challengedBy ? (int) $challengedBy : null,
        ), $userId, (string) $assessmentId);

        // المتبقي يحكم المستوى الجاري وقرار القبول؛ المتأصل يحكمه قبل وجود متبقٍّ
        if ($type !== 'target') {
            $stateAfter = $type === 'inherent' ? 'inherent_assessed' : 'residual_assessed';
            $reviewDays = self::REVIEW_DAYS[$level] ?? 182;
            $up = $db->prepare("UPDATE risk_register
                                   SET current_level = ?, state = ?, control_effectiveness = ?, confidence = ?,
                                       review_due = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                                 WHERE id = ? AND company_id = ?");
            $eff = self::aggregateEffectiveness($db, $companyId, $riskId);
            $up->bind_param('ssssiii', $level, $stateAfter, $eff, $conf, $reviewDays, $riskId, $companyId);
            $up->execute(); $up->close();
            if ($level === 'حرج' || $level === 'محظور') {
                self::escalate($db, $companyId, $riskId, null,
                    'تقييم ' . ($type === 'inherent' ? 'متأصل' : 'متبقٍ') . ' بمستوى ' . $level, 'ceo', $userId);
            }
            // المرحلة ٩ «مقارنةُ الشهية»: حكمٌ آليٌّ يتبع كلَّ تقييمٍ — لا يُدخَل يدويًّا.
            if ($type === 'residual') {
                self::compareAppetite($db, $companyId, $riskId, $userId);
            }
        } else {
            $up = $db->prepare("UPDATE risk_register SET target_level = ? WHERE id = ? AND company_id = ?");
            $up->bind_param('sii', $level, $riskId, $companyId);
            $up->execute(); $up->close();
        }
        return array('level' => $level, 'score' => $score, 'assessment_id' => $assessmentId);
    }

    /**
     * §12-3 فعاليةُ الضوابطِ المجمَّعة: أضعفُ ما ثبت على ضوابطِ الخطرِ المرتبطة.
     * «وجودُ الضابطِ في السجلِّ لا يخفض الدرجة» (RK-07) — فالخطرُ بلا ضابطٍ مرتبطٍ
     * فعاليتُه «غيرُ مُثبَت» لا «فعّال»، والأضعفُ يحكم لا المتوسطُ الذي يجمّل.
     */
    public static function aggregateEffectiveness(\mysqli $db, $companyId, $riskId)
    {
        $st = $db->prepare("SELECT c.effectiveness, COUNT(*) n
                              FROM risk_control_links l
                              JOIN risk_controls c ON c.id = l.control_id AND c.company_id = l.company_id
                             WHERE l.company_id = ? AND l.risk_id = ?
                             GROUP BY c.effectiveness");
        $st->bind_param('ii', $companyId, $riskId);
        $st->execute();
        $seen = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $seen[(string) $x['effectiveness']] = (int) $x['n']; }
        $st->close();
        if (empty($seen)) { return 'غير مثبت'; }
        foreach (array('غير فعال', 'غير مثبت', 'فعال جزئيا', 'فعال') as $weakest) {
            if (isset($seen[$weakest])) { return $weakest; }
        }
        return 'غير مثبت';
    }

    /**
     * المرحلة ٩ «مقارنةُ الشهية» — محرّكُ الشهيةِ (§12-1 · §13-1).
     * الحكمُ آليٌّ لا يُدخَل: يقارن المستوى الجاري بشهيةِ المجالِ وحدِّ تحمله.
     * والأرضياتُ الثلاثُ (السلامةُ · الالتزامُ القانونيُّ · تسربُ البيانات) لا
     * تُرفع بحال — فالحرجُ فيها «محظور» لا «فوق الشهية».
     */
    public static function compareAppetite(\mysqli $db, $companyId, $riskId, $userId)
    {
        $st = $db->prepare("SELECT rr.current_level, ru.ru_code
                              FROM risk_register rr
                              JOIN risk_units ru ON ru.id = rr.ru_id AND ru.company_id = rr.company_id
                             WHERE rr.id = ? AND rr.company_id = ?");
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return null; }
        $level = (string) $row['current_level'];
        if ($level === '') { return null; }

        $domain = self::RU_DOMAIN[(string) $row['ru_code']] ?? 'التشغيل والإنتاج';
        $isFloor = false;
        $st = $db->prepare('SELECT immutable_floor FROM risk_appetite WHERE company_id = ? AND domain = ?');
        $st->bind_param('is', $companyId, $domain);
        $st->execute();
        if ($ap = $st->get_result()->fetch_assoc()) { $isFloor = (int) $ap['immutable_floor'] === 1; }
        $st->close();
        $row['domain'] = $domain;

        $rank = array('منخفض' => 1, 'متوسط' => 2, 'مرتفع' => 3, 'حرج' => 4, 'محظور' => 5);
        $lv = $rank[$level] ?? 0;
        if ($isFloor && $lv >= 4) {
            // §13-1: «لا يُقبل خطرٌ حرجٌ في السلامةِ بحال» — والمحظورُ لا يُقبل أصلًا.
            $verdict = 'محظور';
        } elseif ($lv >= 5) {
            $verdict = 'محظور';
        } elseif ($lv >= 4) {
            $verdict = 'فوق حد التحمل';
        } elseif ($lv >= 3) {
            $verdict = 'فوق الشهية';
        } else {
            $verdict = 'داخل الشهية';
        }
        $up = $db->prepare("UPDATE risk_register SET appetite_verdict = ?, appetite_checked_at = NOW() WHERE id = ? AND company_id = ?");
        $up->bind_param('sii', $verdict, $riskId, $companyId);
        $up->execute(); $up->close();

        RiskEvents::fire($db, $companyId, 'RiskAppetiteCompared', $riskId, array(
            'level' => $level, 'domain' => $row['domain'], 'ru_code' => $row['ru_code'],
            'immutable_floor' => $isFloor, 'verdict' => $verdict,
        ), $userId, $level . ':' . $verdict);

        if ($verdict === 'محظور' || $verdict === 'فوق حد التحمل') {
            self::escalate($db, $companyId, $riskId, null,
                'محرّك الشهية: ' . $verdict . ' في مجال ' . (string) $row['domain'], 'ceo', $userId);
        }
        return $verdict;
    }

    /** تحقق ضابط (RK-07): الحرج يفرض متحققًا مستقلًّا ≠ المالك */
    public static function verifyControl(\mysqli $db, $companyId, $controlId, $result, $evidenceText, $userId)
    {
        $st = $db->prepare('SELECT owner_user_id, is_critical FROM risk_controls WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $controlId, $companyId);
        $st->execute();
        $ctl = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ctl) { throw new \RuntimeException('RSK-404: الضابط غير موجود'); }
        if ((int) $ctl['is_critical'] === 1 && (int) $ctl['owner_user_id'] === (int) $userId) {
            throw new \RuntimeException('RSK-403: الضابط الحرج لا يتحقق مالكه من نفسه — متحقق مستقل إلزامًا');
        }
        if (!in_array($result, array('فعال', 'فعال جزئيا', 'غير فعال'), true)) { throw new \RuntimeException('RSK-422: نتيجة تحقق غير معروفة'); }
        if (trim((string) $evidenceText) === '') { throw new \RuntimeException('RSK-422: التحقق بدليل لا بادعاء (RK-07)'); }

        $ins = $db->prepare("INSERT INTO risk_control_evidence (company_id, control_id, kind, evidence_text, result, submitted_by)
                             VALUES (?,?,'verification',?,?,?)");
        $ins->bind_param('iissi', $companyId, $controlId, $evidenceText, $result, $userId);
        $ins->execute();
        $evidenceId = (int) $db->insert_id;
        $ins->close();

        $up = $db->prepare("UPDATE risk_controls
                               SET effectiveness = ?, last_verified_at = CURDATE(), last_verify_result = ?, last_verified_by = ?,
                                   next_verify_due = DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                             WHERE id = ? AND company_id = ?");
        $up->bind_param('ssiii', $result, $evidenceText, $userId, $controlId, $companyId);
        $up->execute(); $up->close();

        RiskEvents::fire($db, $companyId, 'ControlVerified', $controlId, array(
            'result' => $result, 'evidence_id' => $evidenceId,
            'is_critical' => (int) $ctl['is_critical'] === 1, 'verified_by' => (int) $userId,
        ), $userId, (string) $evidenceId);

        // «فعاليةُ الضابطِ تُحدِّث درجةَ الخطرِ المتبقي» (§7-2) — والضوابطُ المرتبطةُ
        // تُعاد مقارنتُها بالشهية، فالفعاليةُ عنصرُ قياسٍ لا وسمُ عرض.
        self::refreshLinkedRisks($db, $companyId, $controlId, $userId);

        // SG-10 وrisk.control.fail: فشلُ ضابطٍ حرجٍ ⇒ حدثٌ مستقلٌّ + إشارةٌ + تصعيدٌ
        // فوريٌّ في اليومِ نفسِه. «لا يُعكس — يُغلق بإجراءٍ تصحيحيٍّ متحقَّقٍ منه» (§7-1).
        if ($result === 'غير فعال' && (int) $ctl['is_critical'] === 1) {
            self::failCriticalControl($db, $companyId, $controlId, $evidenceText, $userId);
        }
        return true;
    }

    /**
     * risk.control.fail (§7-1 · §7-2) — فعلٌ مستقلٌّ لا فرعُ تحقق:
     * «تصعيدٌ فوريٌّ في اليومِ نفسِه ورفعُ الخطرِ المتبقي» · الحدثُ CriticalControlFailed
     * يستهلكه الرئيسُ التنفيذيُّ والمخاطرُ والإدارةُ المالكة · ولا عكسَ له بطبيعته.
     */
    public static function failCriticalControl(\mysqli $db, $companyId, $controlId, $reason, $userId)
    {
        $st = $db->prepare('SELECT control_code, is_critical, owner_user_id, hico_event, fail_action FROM risk_controls WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $controlId, $companyId);
        $st->execute();
        $ctl = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ctl) { throw new \RuntimeException('RSK-404: الضابط غير موجود'); }
        if ((int) $ctl['is_critical'] !== 1) {
            throw new \RuntimeException('RSK-422: فعلُ الفشلِ للضابطِ الحرجِ وحدَه — وغيرُه يُسجَّل تحققًا غيرَ فعّال');
        }
        if (trim((string) $reason) === '') { throw new \RuntimeException('RSK-422: فشلُ الضابطِ بسببِه المكتوب'); }

        $up = $db->prepare("UPDATE risk_controls SET effectiveness = 'غير فعال', last_verify_result = ?, last_verified_at = CURDATE() WHERE id = ? AND company_id = ?");
        $up->bind_param('sii', $reason, $controlId, $companyId);
        $up->execute(); $up->close();

        // SG-10 بمفتاحِ قاعدةٍ يمنع تكرارَ الإشارةِ لنفس الفشلِ في اليوم
        $sig = self::createSignal($db, $companyId, array(
            'sg_code' => 'SG-10', 'source' => 'auto',
            'rule_key' => 'SG-10:ctl' . (int) $controlId . ':' . gmdate('Y-m-d'),
            'title' => 'فشل ضابط حرج ' . (string) $ctl['control_code'],
            'details' => $reason . ($ctl['fail_action'] ? ' · إجراء الفشل: ' . $ctl['fail_action'] : ''),
            'root_cause' => 'فشل ضابط حرج',
        ), $userId);

        $escId = self::escalate($db, $companyId, null, ($sig['id'] ?: null),
            'SG-10: فشل ضابط حرج ' . (string) $ctl['control_code'] . ' — ' . $reason, 'ceo', $userId);

        RiskEvents::fire($db, $companyId, 'CriticalControlFailed', $controlId, array(
            'control_code' => $ctl['control_code'], 'reason' => $reason,
            'hico_event' => $ctl['hico_event'], 'fail_action' => $ctl['fail_action'],
            'signal_id' => $sig['id'], 'escalation_id' => $escId,
            'reversible' => false,
        ), $userId, gmdate('Y-m-d'));

        self::refreshLinkedRisks($db, $companyId, $controlId, $userId);
        return array('signal_id' => $sig['id'], 'escalation_id' => $escId);
    }

    /**
     * تحديثُ فعاليةِ الضوابطِ المجمَّعةِ على كلِّ خطرٍ مرتبطٍ بهذا الضابطِ وإعادةُ
     * حكمِ الشهيةِ عليه. لا يكتب تقييمًا جديدًا: التقييمُ فعلُ إنسانٍ بأبعادِه
     * الثمانية (RK-03)، وهذا تحديثُ عنصرِ قياسٍ محسوبٍ لا درجةٍ مخترعة.
     */
    public static function refreshLinkedRisks(\mysqli $db, $companyId, $controlId, $userId)
    {
        $st = $db->prepare('SELECT risk_id FROM risk_control_links WHERE company_id = ? AND control_id = ?');
        $st->bind_param('ii', $companyId, $controlId);
        $st->execute();
        $ids = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $ids[] = (int) $x['risk_id']; }
        $st->close();
        foreach ($ids as $rid) {
            $eff = self::aggregateEffectiveness($db, $companyId, $rid);
            $up = $db->prepare('UPDATE risk_register SET control_effectiveness = ? WHERE id = ? AND company_id = ?');
            $up->bind_param('sii', $eff, $rid, $companyId);
            $up->execute(); $up->close();
            self::compareAppetite($db, $companyId, $rid, $userId);
        }
        return count($ids);
    }

    /**
     * القبول الرسمي (RK-04): يفحص السلطة على المستوى الجاري ويرفض ما فوق السقف
     * ويصعّد آليًّا. «المحظور» لا يُقبل بحال.
     * @param string $actorAuthority سلطة الفاعل: risk_owner|analyst|deputy|ceo
     */
    public static function acceptRisk(\mysqli $db, $companyId, $riskId, $actorAuthority, $userId, $note = null, $analystReviewBy = null, $compensatingCtl = null)
    {
        $st = $db->prepare('SELECT current_level, state, owner_unit_id, risk_owner_user_id
                              FROM risk_register WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $risk = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$risk) { throw new \RuntimeException('RSK-404: الخطر غير موجود'); }

        /* ══ INJ-0110 · القبولُ حقُّ مالكِ الخطرِ لا حقُّ كلِّ ذي سلطة ══════════
             نصُّ القبول: «بحساب دور ٢٥ أرسل `risk_accept` لخطرٍ `owner_unit_id=3`
             (المالية): **يجب ٤٠٣ برمز `RSK-403-OWNER`**؛ وبحساب مالكِ الخطرِ
             نفسِه: يجب ٢٠٠».
             وكانت المصفوفةُ تفحص **السلطةَ** ولا تفحص **الملكية**: فمن يملك
             سلطةَ «مالكِ خطر» يقبل خطرَ إدارةٍ أخرى — والقبولُ إقرارٌ بتحمّلِ
             الأثر، ولا يتحمّله من لا يقع عليه.
           ◆ **والرمزُ مميِّزٌ لا عامّ**: `RSK-403` وحدَه لا يفرّق بين خمسةِ
             رفوضٍ في هذه الدالّة — والفاحصُ يحتاج أن يعرف **أيَّها وقع**.
           ◆ ونوّابُ الرئيسِ والرئيسُ يعبرون: سلطتُهم فوق الوحدةِ لا داخلَها. */
        $__ownerUnit = isset($risk['owner_unit_id']) ? (int) $risk['owner_unit_id'] : 0;
        if ($__ownerUnit > 0 && !in_array((string) $actorAuthority, array('deputy', 'ceo'), true)) {
            /* ◆ **ووحدةُ الفاعلِ تُشتقُّ من دورِه** بالدالّةِ القائمةِ في
                 `Risk/_risk_common.php` — لا من عمودٍ في `users` (لا وجودَ له).
                 فمصدرُ الوحدةِ واحدٌ للشاشةِ وللخدمة، ولا يتفرّقان. */
            $__myUnit = 0;
            $__rcp = dirname(dirname(dirname(__DIR__))) . '/Risk/_risk_common.php';
            if (!function_exists('risk_role_org_unit') && is_file($__rcp)) { @include_once $__rcp; }
            if (function_exists('risk_role_org_unit')) {
                $__uq = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
                if ($__uq) {
                    $__uid = (int) $userId;
                    $__uq->bind_param('i', $__uid);
                    if ($__uq->execute()) {
                        $__ur = $__uq->get_result()->fetch_row();
                        if ($__ur) { $__myUnit = (int) risk_role_org_unit((string) $__ur[0]); }
                    }
                    $__uq->close();
                }
            }
            $__isOwner = ((int) ($risk['risk_owner_user_id'] ?? 0) === (int) $userId);
            if (!$__isOwner && $__myUnit !== $__ownerUnit) {
                throw new \RuntimeException('RSK-403-OWNER: الخطرُ مملوكٌ لوحدةٍ أخرى (#'
                    . $__ownerUnit . ') — قبولُه إقرارٌ بتحمّلِ أثرِه، ولا يتحمّله من لا يقع عليه');
            }
        }
        $level = (string) $risk['current_level'];
        if ($level === '' || $level === null) { throw new \RuntimeException('RSK-409: لا قبول قبل تقييم متبقٍّ معتمد'); }
        if ($level === 'محظور') {
            self::escalate($db, $companyId, $riskId, null, 'محاولة قبول خطر محظور — إيقاف النشاط', 'ceo', $userId);
            throw new \RuntimeException('RSK-403: المحظور لا يُقبل بحال — صُعِّد فورًا');
        }
        $need = self::AUTHORITY_MATRIX[$level];
        $rank = array('risk_owner' => 1, 'owner_with_analyst' => 2, 'analyst' => 2, 'deputy' => 3, 'ceo' => 4);
        $have = $rank[$actorAuthority] ?? 0;
        if ($need === 'owner_with_analyst') {
            // المتوسط: مالك الخطر بمراجعة محلل — إما فاعل أعلى أو مالك + مراجع محلل
            if ($have < 2 && empty($analystReviewBy)) {
                throw new \RuntimeException('RSK-403: المتوسط يقبله المالك بمراجعة محلل المخاطر — أرفق المراجعة');
            }
        } elseif ($have < $rank[$need]) {
            $to = $need === 'ceo' ? 'ceo' : 'deputy';
            self::escalate($db, $companyId, $riskId, null, 'محاولة قبول ' . $level . ' بسلطة أدنى — تصعيد آلي', $to, $userId);
            throw new \RuntimeException('RSK-403: قبول ' . $level . ' يتطلب سلطة ' . $need . ' — صُعِّد آليًّا (RK-04)');
        }
        $reviewDays = self::REVIEW_DAYS[$level];
        $auth = $need;
        $authorityRef = 'مصفوفة الاعتماد §14-2 · ' . $level . ' ⇒ ' . $need;
        $parentRef = 'RISK-' . (int) $riskId;
        $compensating = (string) ($compensatingCtl ?? '');
        $st = $db->prepare('INSERT INTO risk_acceptances
            (company_id, risk_id, level_at_acceptance, authority, accepted_by, analyst_review_by, review_due, note,
             authority_ref, parent_ref, compensating_ctl)
            VALUES (?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?,?,?)');
        $st->bind_param('iissiiissss', $companyId, $riskId, $level, $auth, $userId, $analystReviewBy, $reviewDays, $note,
            $authorityRef, $parentRef, $compensating);
        $st->execute();
        $acceptanceId = (int) $db->insert_id;
        $st->close();

        $up = $db->prepare("UPDATE risk_register SET state = 'accepted', review_due = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id = ? AND company_id = ?");
        $up->bind_param('iii', $reviewDays, $riskId, $companyId);
        $up->execute(); $up->close();

        RiskEvents::fire($db, $companyId, 'RiskAccepted', $riskId, array(
            'acceptance_id' => $acceptanceId, 'level' => $level, 'authority' => $auth,
            'authority_ref' => $authorityRef, 'review_days' => $reviewDays,
            'analyst_review_by' => $analystReviewBy ? (int) $analystReviewBy : null,
            'compensating_ctl' => $compensating,
        ), $userId, (string) $acceptanceId);
        return true;
    }

    /** الإغلاق (RK-03): دليل تنفيذ + إعادة تقييم بعد آخر معالجة + قبول بالسقف */
    public static function closeRisk(\mysqli $db, $companyId, $riskId, $actorAuthority, $userId, $note = null)
    {
        // ① دليل تنفيذ معالجة موثَّق
        $st = $db->prepare("SELECT COUNT(*) c FROM risk_treatments
                             WHERE company_id = ? AND risk_id = ? AND state = 'verified'");
        $st->bind_param('ii', $companyId, $riskId);
        $st->execute();
        $verified = (int) $st->get_result()->fetch_assoc()['c'];
        $st->close();
        if ($verified === 0) {
            throw new \RuntimeException('RSK-403-CLOSE1: لا إغلاق بلا معالجة منفَّذة بدليل مقبول من المتحقق (RK-03)');
        }
        // ② إعادة تقييم بعد آخر معالجة موثقة
        $st = $db->prepare("SELECT
              (SELECT MAX(assessed_at) FROM risk_assessments WHERE company_id = ? AND risk_id = ? AND assess_type IN ('residual','target')) last_assess,
              (SELECT MAX(verified_at) FROM risk_treatments WHERE company_id = ? AND risk_id = ? AND state = 'verified') last_treat");
        $st->bind_param('iiii', $companyId, $riskId, $companyId, $riskId);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
        $st->close();
        if (empty($t['last_assess']) || (!empty($t['last_treat']) && $t['last_assess'] < $t['last_treat'])) {
            throw new \RuntimeException('RSK-403-CLOSE2: يلزم إعادة تقييم بعد تنفيذ المعالجة — والحكم على المتبقي');
        }
        // ③ قبول بالسقف على المستوى الجاري
        self::acceptRisk($db, $companyId, $riskId, $actorAuthority, $userId, 'قبول إغلاق: ' . (string) $note);
        $up = $db->prepare("UPDATE risk_register SET state = 'closed' WHERE id = ? AND company_id = ?");
        $up->bind_param('ii', $riskId, $companyId);
        $up->execute(); $up->close();
        RiskEvents::fire($db, $companyId, 'RiskClosed', $riskId, array(
            'verified_treatments' => $verified, 'authority' => $actorAuthority,
            'note' => (string) $note, 'evidence_gate' => 'CLOSE1+CLOSE2+CLOSE3',
        ), $userId);
        return true;
    }

    /** إعادةُ الفتحِ (§7-1): «الخطرُ يعود للدورةِ بتقييمٍ جديدٍ يحفظ التاريخَ كلَّه» */
    public static function reopenRisk(\mysqli $db, $companyId, $riskId, $reason, $userId)
    {
        if (trim((string) $reason) === '') { throw new \RuntimeException('RSK-422: إعادةُ الفتحِ بسببِها المكتوب'); }
        $st = $db->prepare("SELECT state FROM risk_register WHERE id = ? AND company_id = ?");
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$r) { throw new \RuntimeException('RSK-404: الخطر غير موجود'); }
        if ((string) $r['state'] !== 'closed') { throw new \RuntimeException('RSK-409: المفتوحُ لا يُعاد فتحه'); }
        $up = $db->prepare("UPDATE risk_register SET state = 'reopened' WHERE id = ? AND company_id = ?");
        $up->bind_param('ii', $riskId, $companyId);
        $up->execute(); $up->close();
        RiskEvents::fire($db, $companyId, 'RiskReopened', $riskId, array(
            'reason' => $reason, 'reversible' => false,
        ), $userId, gmdate('YmdHis'));
        return true;
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * §7-1 الأفعالُ التي أُكملت في 2026-08-08 — كلٌّ بعقده وحارسه وحدثه
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * risk.review — المراجعةُ الدوريةُ (المرحلة ١٣ · §7-2).
     * «مراجعةٌ جديدةٌ تحفظ السابقة» — إدراجٌ فقط بمرجعٍ أبٍ للسابقة.
     * والقرارُ أحدُ ثلاثة: استمرارٌ أو إغلاقٌ أو تصعيد — ولا مراجعةَ بلا قرار.
     * وحدُّ المحلل (§14-1): «لا يملك الخطرَ ولا يقبله» — فقرارُ الإغلاقِ هنا توصيةٌ
     * تفتح بوابةَ الإغلاقِ ولا تُغلق: الإغلاقُ يبقى بـcloseRisk وحارسِه الثلاثي.
     */
    public static function reviewRisk(\mysqli $db, $companyId, $riskId, $decision, $findings, $userId, $triggerKind = 'دورية')
    {
        self::requireRisk($db, $companyId, (int) $riskId);
        if (!in_array($decision, array('استمرار', 'إغلاق', 'تصعيد'), true)) {
            throw new \RuntimeException('RSK-422: قرارُ المراجعةِ أحدُ ثلاثةٍ: استمرارٌ أو إغلاقٌ أو تصعيد');
        }
        if (trim((string) $findings) === '') {
            throw new \RuntimeException('RSK-422: المراجعةُ بشاهدِها المكتوب — لا مراجعةَ صامتة');
        }
        if (!in_array($triggerKind, array('دورية', 'حدث', 'فشل ضابط', 'تجاوز مؤشر'), true)) { $triggerKind = 'دورية'; }

        $st = $db->prepare('SELECT current_level FROM risk_register WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $level = (string) ($st->get_result()->fetch_assoc()['current_level'] ?? '');
        $st->close();

        $st = $db->prepare('SELECT review_code, level_after FROM risk_reviews WHERE company_id = ? AND risk_id = ? ORDER BY id DESC LIMIT 1');
        $st->bind_param('ii', $companyId, $riskId);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $parentRef = $prev ? (string) $prev['review_code'] : '';
        $levelBefore = $prev ? (string) $prev['level_after'] : $level;

        $code = self::nextCode($db, $companyId, 'risk_reviews', 'review_code', 'RVW-', 5);
        $reviewDays = self::REVIEW_DAYS[$level] ?? 182;
        $st = $db->prepare('INSERT INTO risk_reviews
            (company_id, risk_id, review_code, trigger_kind, level_before, level_after, decision, findings_ar,
             next_review_due, reviewed_by, parent_ref)
            VALUES (?,?,?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?)');
        $st->bind_param('iissssssiis', $companyId, $riskId, $code, $triggerKind, $levelBefore, $level,
            $decision, $findings, $reviewDays, $userId, $parentRef);
        $st->execute();
        $reviewId = (int) $db->insert_id;
        $st->close();

        $newState = $decision === 'تصعيد' ? 'reassessment' : 'monitoring';
        $up = $db->prepare("UPDATE risk_register SET state = ?, review_due = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id = ? AND company_id = ? AND state <> 'closed'");
        $up->bind_param('siii', $newState, $reviewDays, $riskId, $companyId);
        $up->execute(); $up->close();

        if ($decision === 'تصعيد') {
            self::escalate($db, $companyId, $riskId, null, 'مراجعة ' . $code . ': ' . $findings, 'deputy', $userId);
        }
        RiskEvents::fire($db, $companyId, 'RiskReviewed', $riskId, array(
            'review_id' => $reviewId, 'review_code' => $code, 'trigger_kind' => $triggerKind,
            'decision' => $decision, 'level_before' => $levelBefore, 'level_after' => $level,
            'parent_ref' => $parentRef,
        ), $userId, (string) $reviewId);
        return array('id' => $reviewId, 'review_code' => $code, 'decision' => $decision);
    }

    /**
     * risk.incident.log — تسجيلُ واقعة (§7-1 · §11-2).
     * «تصحيحُ التسجيلِ بمرجعٍ لا حذفًا» — والتصحيحُ واقعةٌ جديدةٌ تشير للأصل.
     * «وإن حققت خطرًا قائمًا أُعيد تقييمُه» — فالربطُ يفتح إعادةَ التقييمِ حتمًا.
     */
    public static function logIncident(\mysqli $db, $companyId, array $d, $userId)
    {
        $itype = (string) ($d['itype'] ?? 'واقعة');
        if (!in_array($itype, array('واقعة', 'واقعة كادت تقع', 'واقعة خسارة'), true)) {
            throw new \RuntimeException('RSK-422: نوعُ الواقعةِ أحدُ ثلاثةٍ — ولا تُخلط (§11-2)');
        }
        if (trim((string) ($d['title'] ?? '')) === '') { throw new \RuntimeException('RSK-422: عنوانُ الواقعةِ إلزاميّ'); }
        $occurred = (string) ($d['occurred_at'] ?? '');
        if ($occurred === '' || !preg_match('~^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$~', $occurred)) {
            throw new \RuntimeException('RSK-422: وقتُ الواقعةِ لا وقتُ التسجيل — بصيغةٍ صحيحة');
        }
        $occurred = str_replace('T', ' ', $occurred);
        if (strlen($occurred) === 10) { $occurred .= ' 00:00:00'; }
        if (strlen($occurred) === 16) { $occurred .= ':00'; }

        $realized = !empty($d['realized_risk_id']) ? (int) $d['realized_risk_id'] : null;
        if ($realized) { self::requireRisk($db, $companyId, $realized); }

        $code = self::nextCode($db, $companyId, 'risk_incidents', 'incident_code', 'INC-', 6);
        $st = $db->prepare('INSERT INTO risk_incidents
            (company_id, incident_code, itype, ru_id, title, details, occurred_at, site_id, equipment_id,
             entity_type, entity_id, root_cause, injury_count, downtime_hours, loss_estimate, currency,
             realized_risk_id, signal_id, corrected_by_ref, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ru = !empty($d['ru_id']) ? (int) $d['ru_id'] : null;
        $title = (string) $d['title']; $details = $d['details'] ?? null;
        $site = !empty($d['site_id']) ? (int) $d['site_id'] : null;
        $eq = !empty($d['equipment_id']) ? (int) $d['equipment_id'] : null;
        $et = (string) ($d['entity_type'] ?? ''); $ei = !empty($d['entity_id']) ? (int) $d['entity_id'] : null;
        $rc = (string) ($d['root_cause'] ?? '');
        $inj = (int) ($d['injury_count'] ?? 0);
        $down = (float) ($d['downtime_hours'] ?? 0);
        $loss = ($d['loss_estimate'] === '' || $d['loss_estimate'] === null) ? null : (float) $d['loss_estimate'];
        $cur = (string) ($d['currency'] ?? '');
        $sigId = !empty($d['signal_id']) ? (int) $d['signal_id'] : null;
        $corr = (string) ($d['corrected_by_ref'] ?? '');
        $st->bind_param('isssssssisisidsssiss', $companyId, $code, $itype, $ru, $title, $details, $occurred,
            $site, $eq, $et, $ei, $rc, $inj, $down, $loss, $cur, $realized, $sigId, $corr, $userId);
        $st->execute();
        $id = (int) $db->insert_id;
        $st->close();

        // SG-14 «واقعةٌ كادت تقع»: أيُّ بلاغٍ منها يولّد إشارةً — «من أهمِّ مصادرِ
        // الإشاراتِ ولا تُهمَل» (§11-2). ولا تُنشئ خطرًا: تدخل الفرزَ (RK-05).
        if ($itype === 'واقعة كادت تقع') {
            self::createSignal($db, $companyId, array(
                'sg_code' => 'SG-14', 'source' => 'auto',
                'rule_key' => 'SG-14:inc' . $id,
                'title' => 'واقعة كادت تقع — ' . $title,
                'details' => (string) $details, 'root_cause' => $rc,
                'ru_hint_id' => $ru, 'site_id' => $site, 'equipment_id' => $eq,
            ), $userId);
        }
        // «واقعةٌ تُحقق خطرًا قائمًا فيُعاد تقييمُه» (§14-5) — إعادةُ التقييمِ حتميةٌ
        // لا اختياريةٌ: الحالةُ تُقلب فيقف الخطرُ عند بوابةِ تقييمٍ جديد.
        if ($realized) {
            $up = $db->prepare("UPDATE risk_register SET state = 'reassessment' WHERE id = ? AND company_id = ? AND state <> 'closed'");
            $up->bind_param('ii', $realized, $companyId);
            $up->execute(); $up->close();
        }
        RiskEvents::fire($db, $companyId, 'IncidentLogged', $id, array(
            'incident_code' => $code, 'itype' => $itype, 'occurred_at' => $occurred,
            'realized_risk_id' => $realized, 'injury_count' => $inj,
            'downtime_hours' => $down, 'loss_estimate' => $loss, 'currency' => $cur,
            'financial_posting' => false, // RK-06: تعرضٌ مقدَّرٌ لا قيد — الماليةُ تقرر
        ), $userId);
        return array('id' => $id, 'incident_code' => $code);
    }

    /**
     * risk.appetite.set — اعتمادُ الشهية (§7-1: «الرئيسُ التنفيذيُّ حصرًا»).
     * «اعتمادُ شهيةٍ جديدةٍ يحفظ السابقة» — النصُّ السابقُ يُنقل إلى prev_appetite_ar.
     * «ولا تُرفع شهيةُ السلامةِ ولا الالتزامِ القانونيِّ ولا تسربِ البياناتِ بحال»
     * — والأرضيةُ ترفض التعديلَ بنيويًّا لا بتنبيه.
     */
    public static function setAppetite(\mysqli $db, $companyId, $domain, $appetiteAr, $toleranceAr, $planMode, $actorAuthority, $userId)
    {
        if ($actorAuthority !== 'ceo') {
            throw new \RuntimeException('RSK-403: الشهيةُ يعتمدها الرئيسُ التنفيذيُّ حصرًا (§13-1) — والإداراتُ تقترح ولا تقرر');
        }
        $st = $db->prepare('SELECT id, immutable_floor, appetite_ar FROM risk_appetite WHERE company_id = ? AND domain = ?');
        $st->bind_param('is', $companyId, $domain);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { throw new \RuntimeException('RSK-404: مجالُ شهيةٍ غيرُ معروف'); }
        if ((int) $row['immutable_floor'] === 1) {
            throw new \RuntimeException('RSK-403: أرضيةٌ لا تتغير بحال — السلامةُ والالتزامُ القانونيُّ وتسربُ البيانات (§13-2)');
        }
        if (trim((string) $appetiteAr) === '') { throw new \RuntimeException('RSK-422: نصُّ الشهيةِ إلزاميّ'); }
        $mode = in_array($planMode, array('النمو والتوسع', 'التثبيت والكفاءة', 'الحماية والانكماش'), true) ? $planMode : '';

        $prev = (string) $row['appetite_ar'];
        $up = $db->prepare("UPDATE risk_appetite
                               SET appetite_ar = ?, tolerance_ar = ?, plan_mode = ?, prev_appetite_ar = ?,
                                   updated_by = ?, approved_by = ?, approved_at = NOW(),
                                   authority_ref = 'الرئيس التنفيذي — §13-1'
                             WHERE id = ? AND company_id = ?");
        $up->bind_param('ssssiiii', $appetiteAr, $toleranceAr, $mode, $prev, $userId, $userId, $row['id'], $companyId);
        $up->execute(); $up->close();

        RiskEvents::fire($db, $companyId, 'RiskAppetiteSet', (int) $row['id'], array(
            'domain' => $domain, 'appetite' => $appetiteAr, 'tolerance' => $toleranceAr,
            'plan_mode' => $mode, 'prev_appetite' => $prev, 'consumer' => 'المنصة كلها',
        ), $userId, gmdate('YmdHis'));

        // «وتُعاد حدودُ التصعيدِ آليًّا عند تغييرها» (§13-2) — فكلُّ خطرٍ مفتوحٍ في
        // المجالِ يُعاد حكمُ شهيتِه فورًا، ولا يبقى حكمٌ محسوبٌ على شهيةٍ ملغاة.
        $affected = self::recompareDomain($db, $companyId, $domain, $userId);
        return array('domain' => $domain, 'recompared' => $affected);
    }

    /** إعادةُ حكمِ الشهيةِ على كلِّ خطرٍ مفتوحٍ في مجالٍ — تبعُ تغييرِ الشهية */
    public static function recompareDomain(\mysqli $db, $companyId, $domain, $userId)
    {
        $codes = array();
        foreach (self::RU_DOMAIN as $ru => $dom) { if ($dom === $domain) { $codes[] = $ru; } }
        if (empty($codes)) { return 0; }
        $in = implode(',', array_fill(0, count($codes), '?'));
        $sql = "SELECT rr.id FROM risk_register rr
                  JOIN risk_units ru ON ru.id = rr.ru_id AND ru.company_id = rr.company_id
                 WHERE rr.company_id = ? AND rr.state <> 'closed' AND rr.merged_into_id IS NULL
                   AND ru.ru_code IN ($in)";
        $st = $db->prepare($sql);
        $st->bind_param('i' . str_repeat('s', count($codes)), ...array_merge(array($companyId), $codes));
        $st->execute();
        $ids = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $ids[] = (int) $x['id']; }
        $st->close();
        foreach ($ids as $rid) { self::compareAppetite($db, $companyId, $rid, $userId); }
        return count($ids);
    }

    /**
     * risk.taxonomy.define — تعريفُ التصنيف (§7-1: مديرُ المخاطر).
     * «تعديلُ التصنيفِ بترحيلٍ لا بحذف» — فالوحدةُ تُعطَّل ولا تُحذف، ولا تُعطَّل
     * وعليها مخاطرُ مفتوحةٌ (وإلا صارت مخاطرُ بلا تصنيفٍ يحكمها).
     */
    public static function defineTaxonomy(\mysqli $db, $companyId, $ruId, array $d, $userId)
    {
        $st = $db->prepare('SELECT ru_code, name_ar, active FROM risk_units WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $ruId, $companyId);
        $st->execute();
        $ru = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$ru) { throw new \RuntimeException('RSK-404: وحدةُ مخاطرَ غيرُ موجودة'); }

        $wantActive = isset($d['active']) ? (int) (bool) $d['active'] : (int) $ru['active'];
        if ($wantActive === 0 && (int) $ru['active'] === 1) {
            $st = $db->prepare("SELECT COUNT(*) c FROM risk_register WHERE company_id = ? AND ru_id = ? AND state <> 'closed' AND merged_into_id IS NULL");
            $st->bind_param('ii', $companyId, $ruId);
            $st->execute();
            $open = (int) $st->get_result()->fetch_assoc()['c'];
            $st->close();
            if ($open > 0) {
                throw new \RuntimeException('RSK-409: لا تعطيلَ لوحدةٍ عليها ' . $open . ' خطرًا مفتوحًا — رحّلها أولًا (التعديلُ بترحيلٍ لا بحذف)');
            }
        }
        $win = isset($d['dedup_window_days']) ? max(1, min(3650, (int) $d['dedup_window_days'])) : null;
        $name = isset($d['name_ar']) && trim((string) $d['name_ar']) !== '' ? (string) $d['name_ar'] : (string) $ru['name_ar'];
        $cov = isset($d['coverage']) ? (string) $d['coverage'] : null;
        $std = isset($d['ref_standard']) ? (string) $d['ref_standard'] : null;

        $sets = array('name_ar = ?', 'active = ?');
        $types = 'si';
        $vals = array($name, $wantActive);
        if ($win !== null) { $sets[] = 'dedup_window_days = ?'; $types .= 'i'; $vals[] = $win; }
        if ($cov !== null) { $sets[] = 'coverage = ?'; $types .= 's'; $vals[] = $cov; }
        if ($std !== null) { $sets[] = 'ref_standard = ?'; $types .= 's'; $vals[] = $std; }
        $types .= 'ii';
        $vals[] = $ruId; $vals[] = $companyId;
        $st = $db->prepare('UPDATE risk_units SET ' . implode(', ', $sets) . ' WHERE id = ? AND company_id = ?');
        $st->bind_param($types, ...$vals);
        $st->execute(); $st->close();

        RiskEvents::fire($db, $companyId, 'RiskTaxonomyDefined', $ruId, array(
            'ru_code' => $ru['ru_code'], 'name_ar' => $name, 'active' => $wantActive,
            'dedup_window_days' => $win, 'consumer' => 'المنصة كلها',
        ), $userId, gmdate('YmdHis'));
        return true;
    }

    /**
     * risk.kri.threshold — ضبطُ حدِّ مؤشر (§7-2).
     * «الحدودُ الجديدةُ تسري على القراءاتِ القادمةِ لا الماضية» — فلا تُعاد قراءةٌ
     * تاريخيةٌ ولا تُعاد حسبةُ حالةٍ سابقة، والحالةُ الجاريةُ تُحسب من القيمةِ الحالية.
     */
    public static function setKriThreshold(\mysqli $db, $companyId, $kriId, $warnNum, $criticalNum, $direction, $userId)
    {
        $st = $db->prepare('SELECT name_ar, current_value FROM risk_kris WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $kriId, $companyId);
        $st->execute();
        $k = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$k) { throw new \RuntimeException('RSK-404: المؤشر غير موجود'); }
        $dir = $direction === 'تنازلي' ? 'تنازلي' : 'تصاعدي';
        $w = ($warnNum === '' || $warnNum === null) ? null : (float) $warnNum;
        $c = ($criticalNum === '' || $criticalNum === null) ? null : (float) $criticalNum;
        if ($w !== null && $c !== null) {
            // الحرجُ أشدُّ من الإنذارِ دائمًا — والاتجاهُ يحدد معنى «أشد».
            $bad = ($dir === 'تصاعدي') ? ($c < $w) : ($c > $w);
            if ($bad) { throw new \RuntimeException('RSK-422: الحدُّ الحرجُ أشدُّ من الإنذارِ باتجاهِ المؤشر'); }
        }
        $up = $db->prepare('UPDATE risk_kris SET warn_num = ?, critical_num = ?, direction = ? WHERE id = ? AND company_id = ?');
        $up->bind_param('ddsii', $w, $c, $dir, $kriId, $companyId);
        $up->execute(); $up->close();

        RiskEvents::fire($db, $companyId, 'KRIThresholdSet', $kriId, array(
            'name' => $k['name_ar'], 'warn_num' => $w, 'critical_num' => $c,
            'direction' => $dir, 'applies_to' => 'القراءات القادمة لا الماضية',
        ), $userId, gmdate('YmdHis'));
        return true;
    }

    /**
     * risk.report.export — التصديرُ (§8: «ليس قارئًا لا يكتب»).
     * يكتب سجلَّ تصديرٍ بتسعةِ بنودٍ إلزامًا — والحقولُ المحجوبةُ بالصلاحيةِ تُعلَن
     * في السجلِّ لا تُخفى، فيُعرف ما خرج وما لم يخرج ولِمَ.
     */
    public static function logExport(\mysqli $db, $companyId, array $d, $userId)
    {
        $screen = (string) ($d['screen_code'] ?? '');
        if ($screen === '') { throw new \RuntimeException('RSK-422: سجلُّ التصديرِ بشاشتِه'); }
        $st = $db->prepare('INSERT INTO risk_export_log
            (company_id, exported_by, actor_capacity, screen_code, view_key, columns_text, filters_text, blocked_text, row_count, fmt)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $cap = (string) ($d['actor_capacity'] ?? '');
        $view = (string) ($d['view_key'] ?? 'default');
        $cols = (string) ($d['columns_text'] ?? '');
        $filters = (string) ($d['filters_text'] ?? '');
        $blocked = (string) ($d['blocked_text'] ?? '');
        $rows = (int) ($d['row_count'] ?? 0);
        $fmt = (string) ($d['fmt'] ?? 'xlsx');
        $st->bind_param('iissssssis', $companyId, $userId, $cap, $screen, $view, $cols, $filters, $blocked, $rows, $fmt);
        $st->execute();
        $id = (int) $db->insert_id;
        $st->close();
        RiskEvents::fire($db, $companyId, 'RiskReportExported', $id, array(
            'screen_code' => $screen, 'view_key' => $view, 'row_count' => $rows,
            'blocked_text' => $blocked, 'fmt' => $fmt, 'consumer' => 'الحوكمة والالتزام',
        ), $userId, (string) $id);
        return array('id' => $id);
    }

    /**
     * risk.field.sync — مزامنةُ الإشاراتِ المعلَّقة (§7-2).
     * «المعلَّقُ يُرفع بمفتاحِه — والإعادةُ ترجع مرجعَ الأولِ ولا تُنشئ ثانيًا».
     * فكلُّ عنصرٍ يمرُّ بعطالةِ sync_uuid في createSignal، والحصيلةُ تُعلن الجديدَ
     * والمعادَ صريحًا — فلا يظن الميدانُ أن المعادَ ضاع.
     */
    public static function syncFieldSignals(\mysqli $db, $companyId, array $items, $userId)
    {
        $created = array(); $idempotent = array(); $failed = array();
        foreach ($items as $i => $it) {
            if (empty($it['sync_uuid'])) { $failed[] = array('i' => $i, 'reason' => 'بلا مفتاح مزامنة'); continue; }
            if (trim((string) ($it['title'] ?? '')) === '') { $failed[] = array('i' => $i, 'reason' => 'بلا عنوان'); continue; }
            try {
                $it['source'] = 'field';
                $r = self::createSignal($db, $companyId, $it, $userId);
                if (!empty($r['idempotent'])) { $idempotent[] = array('sync_uuid' => $it['sync_uuid'], 'id' => $r['id']); }
                else { $created[] = array('sync_uuid' => $it['sync_uuid'], 'id' => $r['id']); }
            } catch (\Throwable $ex) { ems_catch_ignored($ex, __METHOD__, 'مزامنةُ إشارةٍ ميدانيةٍ واحدةٍ فشلت — بقيةُ الدفعةِ تُزامَن، والفاشلةُ تُعاد بمعرِّفها');
                $failed[] = array('i' => $i, 'reason' => $ex->getMessage());
            }
        }
        // الحدثُ يُنشر على أولِ إشارةٍ في الدفعةِ (كيانُ الحدثِ إشارةٌ لا دفعة) —
        // وحصيلةُ الدفعةِ كلُّها في payload. ودفعةٌ صفرُ نتيجةٍ لا تُنشر حدثًا.
        $anchor = !empty($created) ? $created[0]['id'] : (!empty($idempotent) ? $idempotent[0]['id'] : 0);
        if ($anchor > 0) {
            RiskEvents::fire($db, $companyId, 'RiskSignalsSynced', $anchor, array(
                'created' => count($created), 'idempotent' => count($idempotent),
                'failed' => count($failed), 'uuids' => array_column($created, 'sync_uuid'),
            ), $userId, gmdate('YmdHis') . ':' . count($created));
        }
        return array('created' => $created, 'idempotent' => $idempotent, 'failed' => $failed);
    }

    /**
     * gov.rsk.attest — تصديقُ مراجعةِ الوصولِ (§7-2).
     * «المديرُ يصدّق على قائمةِ فريقه — والتصديقُ لا يمنح صلاحيةً بل يشهد بصحتها».
     * فلا كتابةَ في جداولِ الصلاحياتِ من هنا إطلاقًا: شهادةٌ فقط بحدثِها.
     */
    public static function attestAccessReview(\mysqli $db, $companyId, $scopeCode, $headcount, $note, $userId)
    {
        if (trim((string) $scopeCode) === '') { throw new \RuntimeException('RSK-422: نطاقُ التصديقِ إلزاميّ'); }
        $ev = RiskEvents::fire($db, $companyId, 'AccessReviewAttested', (int) $userId, array(
            'scope_code' => $scopeCode, 'headcount' => (int) $headcount, 'note' => (string) $note,
            'grants_permission' => false, // يشهد بصحتها ولا يمنحها
            'consumer' => 'الحوكمة والالتزام',
        ), $userId, gmdate('Y-m-d') . ':' . $scopeCode);
        return array('event' => $ev ? (int) $ev['id'] : 0);
    }

    /** سعةُ أعمدةِ الرموزِ الأربعةِ جميعًا — `varchar(16)` (انظر CODE_TABLES) */
    const CODE_LEN = 16;

    /**
     * جداولُ الترقيمِ المسموحة: الجدولُ ⇒ عمودُ رمزِه. قائمةُ سماحٍ صلبةٌ لأنَّ
     * اسمَ الجدولِ والعمودِ يُركَّبان في نصِّ الاستعلامِ ولا يُربَطان.
     */
    const CODE_TABLES = array(
        'risk_register'  => 'risk_code',
        'risk_reviews'   => 'review_code',
        'risk_incidents' => 'incident_code',
        'risk_committee' => 'minute_code',
    );

    /**
     * رمزٌ تسلسليٌّ لكل شركةٍ على جدولٍ وعمودٍ — **المُرقِّمُ الوحيدُ في الوحدة**.
     * ═══════════════════════════════════════════════════════════════════════
     * INJ-0631 · «المُرقِّمُ يقرأ نمطَه لا موضِعَه»
     *
     * كان الاقتطاعُ بالموضعِ `SUBSTRING(code, strlen(prefix)+1)` — يفترض أنَّ كلَّ
     * صفٍّ في العمودِ يحمل البادئةَ المنتظَرة. فحين حملت صفوفٌ مبذورةٌ بادئةً
     * أطولَ بحرفٍ (`RISK-00004` مقابل `RSK-`) عاد الاقتطاعُ بـ`'-00004'`،
     * و**MariaDB تلفُّ السالبَ إلى مُتمِّمِه الموجب بتنبيهٍ 1105 لا بخطأ**:
     *
     *     CAST('-00004' AS UNSIGNED) = 18446744073709551612
     *
     * فيصير «التالي» ‏1.8e19 والرمزُ `RSK-9223372036854775807` — ثلاثةٌ وعشرون
     * حرفًا في عمودٍ سعتُه ستةَ عشر. و`sql_mode` خالٍ في تركيبِ التشغيل، فالقاعدةُ
     * **تبتر بصمتٍ**: أوّلُ إنشاءٍ ينجح برمزٍ مشوَّهٍ يُحفظ، وما بعده يصطدم
     * بمفتاحِ التفرُّدِ فيُردُّ. عيبٌ لا يُعلن عن نفسِه في السطرِ الذي أنشأه.
     *
     * فالحكمُ هنا ثلاثيّ:
     *   ① **النمطُ يرشِّح**: لا يدخل المتوسطَ إلا ما طابق `^PREFIX[0-9]+$` —
     *      فالبادئةُ الغريبةُ تصير خاملةً بلا حاجةٍ إلى مسِّ صفوفِها.
     *   ② **حدٌّ صريحٌ للرقم**: أيُّ لفٍّ مستقبليٍّ يُرمى ولا يُبنى عليه رمز.
     *   ③ **الطولُ يُقاس قبلَ الإدراج**: فلا يبلغ القاعدةَ رمزٌ تبتره صامتةً.
     *
     * ولا نسخةَ ثانيةَ من هذا الحساب: `createRisk` تناديه ولا تكتب استعلامَها —
     * فمُرقِّمان في ملفٍ واحدٍ يتفرَّقان بأوّلِ إصلاحٍ يصيب أحدَهما.
     */
    public static function nextCode(\mysqli $db, $companyId, $table, $column, $prefix, $width)
    {
        if (!isset(self::CODE_TABLES[$table]) || self::CODE_TABLES[$table] !== $column) {
            throw new \RuntimeException('RSK-500: جدولٌ أو عمودٌ خارجَ قائمةِ الترقيم');
        }
        // البادئةُ تُركَّب في نمطِ REGEXP — فتُحصر في حروفٍ لاتينيةٍ وشَرطةٍ ختامية
        if (!preg_match('~^[A-Z]{2,8}-$~', (string) $prefix)) {
            throw new \RuntimeException('RSK-500: بادئةُ ترقيمٍ غيرُ مقبولة: ' . $prefix);
        }
        $len = strlen($prefix) + 1;
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(`$column`, $len) AS UNSIGNED)), 0) + 1 nx
                  FROM `$table`
                 WHERE company_id = ? AND `$column` REGEXP ?";
        $pattern = '^' . $prefix . '[0-9]+$';
        $st = $db->prepare($sql);
        $st->bind_param('is', $companyId, $pattern);
        $st->execute();
        $nx = (int) $st->get_result()->fetch_assoc()['nx'];
        $st->close();
        if ($nx < 1 || $nx > 99999999) {
            throw new \RuntimeException('RSK-500: تسلسلُ ' . $table . ' خارجَ المدى (' . $nx . ') — لا يُبنى عليه رمز');
        }
        $code = $prefix . str_pad((string) $nx, $width, '0', STR_PAD_LEFT);
        if (strlen($code) > self::CODE_LEN) {
            throw new \RuntimeException('RSK-500: رمزٌ أطولُ من سعةِ العمود: ' . $code);
        }
        return $code;
    }

    /** الدمج بقرار المحلل (ورقة 32) — الصف المدموج يبقى أثرًا لا يُحذف */
    public static function mergeRisks(\mysqli $db, $companyId, $srcRiskId, $dstRiskId, $reason, $userId)
    {
        if ((int) $srcRiskId === (int) $dstRiskId) { throw new \RuntimeException('RSK-422: لا دمج ذاتي'); }
        if (trim((string) $reason) === '') { throw new \RuntimeException('RSK-422: الدمج بقرار محلل مسبَّب'); }
        $st = $db->prepare("UPDATE risk_register SET merged_into_id = ?, state = 'closed' WHERE id = ? AND company_id = ? AND merged_into_id IS NULL");
        $st->bind_param('iii', $dstRiskId, $srcRiskId, $companyId);
        $st->execute();
        $ok = $db->affected_rows > 0;
        $st->close();
        if ($ok) {
            $up = $db->prepare("UPDATE risk_register SET state = 'reassessment' WHERE id = ? AND company_id = ?");
            $up->bind_param('ii', $dstRiskId, $companyId);
            $up->execute(); $up->close();
            RiskEvents::fire($db, $companyId, 'RiskMerged', (int) $dstRiskId, array(
                'merged_from' => (int) $srcRiskId, 'reason' => $reason,
                'note' => 'الصفُّ المدموجُ يبقى أثرًا لا يُحذف',
            ), $userId, (string) $srcRiskId);
        }
        return $ok;
    }

    /** التصعيد الآلي (RK-08) — لا أحد يخفيه ولا مدير المخاطر نفسه */
    public static function escalate(\mysqli $db, $companyId, $riskId, $signalId, $reason, $toAuthority, $userId)
    {
        $to = in_array($toAuthority, array('risk_manager', 'deputy', 'ceo'), true) ? $toAuthority : 'risk_manager';
        $st = $db->prepare('INSERT INTO risk_escalations (company_id, risk_id, signal_id, reason_ar, to_authority, is_auto) VALUES (?,?,?,?,?,1)');
        $st->bind_param('iiiss', $companyId, $riskId, $signalId, $reason, $to);
        $st->execute();
        $escId = (int) $db->insert_id;
        $st->close();
        // كيانُ الحدثِ الخطرُ إن وُجد وإلا التصعيدُ نفسُه (إشارةٌ صُعِّدت قبل التسجيل).
        RiskEvents::fire($db, $companyId, 'RiskEscalated', ($riskId ? (int) $riskId : $escId), array(
            'escalation_id' => $escId, 'to_authority' => $to, 'reason' => $reason,
            'signal_id' => $signalId ? (int) $signalId : null,
            'is_auto' => true, 'suppressible' => false, // §7-2: «لا يملك أحدٌ منعَه»
        ), $userId, (string) $escId);
        return $escId;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ما استُخرج من الأسطحِ امتثالًا لـCS-05 «لا عبارةَ كتابةٍ في ملفِّ سطح»
       (AC-F6). المنطقُ كما كان حرفًا بحرف — التغييرُ في **موضعِه** لا في أثره.
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * كنسُ المعالجاتِ المتأخرة — من `Risk/risk_treatments.php`.
     *
     * ◆ عاطلٌ بطبعِه: الشرطُ `state IN ('planned','in_progress')` يجعل النداءَ
     *   المتكرِّرَ بلا أثرٍ إضافيّ، فيُستدعى عند كلِّ فتحٍ للشاشةِ بلا ضرر.
     * ◆ ولا يمسُّ المنجَزَ ولا الملغى: التأخُّرُ حالةٌ لِما لم يُنجَز بعد.
     *
     * @return int عددُ ما تحوّل إلى «متأخر»
     */
    public static function sweepOverdueTreatments($conn, $companyId)
    {
        $st = $conn->prepare(
            "UPDATE risk_treatments
                SET state = 'overdue'
              WHERE company_id = ?
                AND state IN ('planned','in_progress')
                AND due_date < CURDATE()"
        );
        if (!$st) { error_log('RiskService::sweepOverdueTreatments prepare: ' . $conn->error); return 0; }
        $cid = (int) $companyId;
        $st->bind_param('i', $cid);
        if (!$st->execute()) {
            error_log('RiskService::sweepOverdueTreatments execute: ' . $st->error);
            $st->close();
            return 0;
        }
        $n = $st->affected_rows;
        $st->close();
        return (int) $n;
    }

    /**
     * إنشاءُ محضرِ لجنةِ مخاطرَ مسوَّدةً — من `Risk/risk_committee.php`.
     *
     * ◆ `parent_ref` يحمل رمزَ المحضرِ السابقِ فتنتظم السلسلةُ زمنيًّا —
     *   وهو ما يجعل «المحاضرَ» سجلًّا متصلًا لا صفوفًا متفرقة.
     *
     * @return string|null رمزُ المحضرِ المُنشأ، أو null عند الفشل
     */
    public static function createCommitteeMinute($conn, $companyId, array $in, $userId)
    {
        $cid  = (int) $companyId;
        $code = self::nextCode($conn, $cid, 'risk_committee', 'minute_code', 'CMT-', 5);

        $prevSt = $conn->prepare(
            "SELECT COALESCE(MAX(minute_code),'') c FROM risk_committee WHERE company_id = ?"
        );
        $prev = '';
        if ($prevSt) {
            $prevSt->bind_param('i', $cid);
            if ($prevSt->execute()) {
                $row = $prevSt->get_result()->fetch_assoc();
                $prev = (string) ($row['c'] ?? '');
            }
            $prevSt->close();
        }

        $st = $conn->prepare(
            'INSERT INTO risk_committee
                (company_id, minute_code, meeting_date, cycle_ar, attendees_ar, agenda_ar,
                 resolutions_ar, risks_reviewed, parent_ref, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$st) { error_log('RiskService::createCommitteeMinute prepare: ' . $conn->error); return null; }

        $md = (string) ($in['meeting_date']   ?? '');
        $cy = (string) ($in['cycle_ar']       ?? 'ربع سنوي');
        $at = (string) ($in['attendees_ar']   ?? '');
        $ag = (string) ($in['agenda_ar']      ?? '');
        $rs = (string) ($in['resolutions_ar'] ?? '');
        $rv = (int)    ($in['risks_reviewed'] ?? 0);
        $uid = (int) $userId;

        $st->bind_param('issssssisi', $cid, $code, $md, $cy, $at, $ag, $rs, $rv, $prev, $uid);
        $ok = $st->execute();
        if (!$ok) { error_log('RiskService::createCommitteeMinute execute: ' . $st->error); }
        $st->close();
        return $ok ? $code : null;
    }

    /**
     * اعتمادُ محضرٍ — من `Risk/risk_committee.php`.
     *
     * ◆ الشرطُ `state='draft'` هو حارسُ «المعتمَدُ لا يُعتمد مرتين»: يُنفَّذ في
     *   الجملةِ نفسِها لا بفحصٍ سابقٍ عليها، فلا تتسلّل مسابقةٌ زمنيةٌ بينهما.
     *
     * @return bool true إن تحوّل صفٌّ فعلًا
     */
    public static function approveCommitteeMinute($conn, $companyId, $minuteId, $approverId)
    {
        $st = $conn->prepare(
            "UPDATE risk_committee
                SET state = 'approved', approved_by = ?, approved_at = NOW(),
                    authority_ref = 'الرئيس التنفيذي — المرحلة ٨'
              WHERE id = ? AND company_id = ? AND state = 'draft'"
        );
        if (!$st) { error_log('RiskService::approveCommitteeMinute prepare: ' . $conn->error); return false; }
        $aid = (int) $approverId; $mid = (int) $minuteId; $cid = (int) $companyId;
        $st->bind_param('iii', $aid, $mid, $cid);
        if (!$st->execute()) {
            error_log('RiskService::approveCommitteeMinute execute: ' . $st->error);
            $st->close();
            return false;
        }
        $n = $st->affected_rows;
        $st->close();
        return $n > 0;
    }
}
