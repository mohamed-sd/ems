<?php

namespace App\Services\Risk;

/**
 * RiskService — قلب M-16 (update0011 · DEC-G نافذ)
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

    /** إنشاء إشارة — الميدانية بعطالة sync_uuid (إعادة المزامنة ترجع مرجع الأولى) */
    public static function createSignal(\mysqli $db, $companyId, array $d, $userId)
    {
        if (!empty($d['sync_uuid'])) {
            $st = $db->prepare('SELECT id FROM risk_signals WHERE sync_uuid = ?');
            $st->bind_param('s', $d['sync_uuid']);
            $st->execute();
            if ($x = $st->get_result()->fetch_assoc()) { $st->close(); return array('id' => (int) $x['id'], 'idempotent' => true); }
            $st->close();
        }
        $st = $db->prepare('INSERT INTO risk_signals
            (company_id, sg_code, source, title, details, ru_hint_id, entity_type, entity_id,
             scope_ref_type, scope_ref_id, root_cause, site_id, shift_ar, equipment_id, photo_ref, sync_uuid, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $sg = isset($d['sg_code']) ? $d['sg_code'] : null;
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
        $st->bind_param('issssisisisisissi', $companyId, $sg, $source, $title, $details, $ru, $et, $ei,
            $srt, $sri, $rc, $site, $shift, $eq, $photo, $uuid, $userId);
        $st->execute();
        $id = $db->insert_id;
        $st->close();
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
        // الرمز التسلسلي RSK-000001 لكل شركة
        $st = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(risk_code, 5) AS UNSIGNED)), 0) + 1 nx FROM risk_register WHERE company_id = ?");
        $st->bind_param('i', $companyId);
        $st->execute();
        $nx = (int) $st->get_result()->fetch_assoc()['nx'];
        $st->close();
        $code = 'RSK-' . str_pad($nx, 6, '0', STR_PAD_LEFT);

        $st = $db->prepare('INSERT INTO risk_register
            (company_id, risk_code, ru_id, title, description, scope_type, scope_ref_type, scope_ref_id,
             entity_type, entity_id, root_cause, owner_unit_id, risk_owner_user_id, dedup_key, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $title = (string) $d['title']; $desc = $d['description'] ?? null;
        $scopeType = in_array($d['scope_type'] ?? '', array('مؤسسي', 'إداري', 'مشروعي', 'موقعي'), true) ? $d['scope_type'] : 'إداري';
        $srt = $d['scope_ref_type'] ?? null; $sri = !empty($d['scope_ref_id']) ? (int) $d['scope_ref_id'] : null;
        $et = $d['entity_type'] ?? null; $ei = !empty($d['entity_id']) ? (int) $d['entity_id'] : null;
        $rc = (string) ($d['root_cause'] ?? '');
        $ou = !empty($d['owner_unit_id']) ? (int) $d['owner_unit_id'] : null;
        $rowner = !empty($d['risk_owner_user_id']) ? (int) $d['risk_owner_user_id'] : null;
        $st->bind_param('isissssisisiisi', $companyId, $code, $ruId, $title, $desc, $scopeType,
            $srt, $sri, $et, $ei, $rc, $ou, $rowner, $key, $userId);
        $st->execute();
        $id = (int) $db->insert_id;
        $st->close();
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

        $st = $db->prepare('INSERT INTO risk_assessments
            (company_id, risk_id, assess_type, likelihood, impacts_json, impact_max, score, level, confidence, technique, assessed_by, challenged_by, note)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->bind_param('iisisiisssiis', $companyId, $riskId, $type, $likelihood, $json, $impactMax, $score, $level, $conf, $technique, $userId, $challengedBy, $note);
        $st->execute(); $st->close();

        // المتبقي يحكم المستوى الجاري وقرار القبول؛ المتأصل يحكمه قبل وجود متبقٍّ
        if ($type !== 'target') {
            $stateAfter = $type === 'inherent' ? 'inherent_assessed' : 'residual_assessed';
            $reviewDays = self::REVIEW_DAYS[$level] ?? 182;
            $up = $db->prepare("UPDATE risk_register
                                   SET current_level = ?, state = ?, review_due = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                                 WHERE id = ? AND company_id = ?");
            $up->bind_param('ssiii', $level, $stateAfter, $reviewDays, $riskId, $companyId);
            $up->execute(); $up->close();
            if ($level === 'حرج' || $level === 'محظور') {
                self::escalate($db, $companyId, $riskId, null,
                    'تقييم ' . ($type === 'inherent' ? 'متأصل' : 'متبقٍ') . ' بمستوى ' . $level, 'ceo', $userId);
            }
        }
        return array('level' => $level, 'score' => $score);
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
        $ins->execute(); $ins->close();

        $up = $db->prepare("UPDATE risk_controls
                               SET effectiveness = ?, last_verified_at = CURDATE(), last_verify_result = ?, last_verified_by = ?,
                                   next_verify_due = DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                             WHERE id = ? AND company_id = ?");
        $up->bind_param('ssiii', $result, $evidenceText, $userId, $controlId, $companyId);
        $up->execute(); $up->close();

        // SG-10: فشل ضابط حرج ⇒ تصعيد فوري + إشارة آلية
        if ($result === 'غير فعال' && (int) $ctl['is_critical'] === 1) {
            self::createSignal($db, $companyId, array(
                'sg_code' => 'SG-10', 'source' => 'auto',
                'title' => 'فشل ضابط حرج #' . $controlId,
                'details' => $evidenceText, 'root_cause' => 'فشل ضابط حرج',
            ), $userId);
            self::escalate($db, $companyId, null, null, 'SG-10: فشل ضابط حرج #' . $controlId, 'ceo', $userId);
        }
        return true;
    }

    /**
     * القبول الرسمي (RK-04): يفحص السلطة على المستوى الجاري ويرفض ما فوق السقف
     * ويصعّد آليًّا. «المحظور» لا يُقبل بحال.
     * @param string $actorAuthority سلطة الفاعل: risk_owner|analyst|deputy|ceo
     */
    public static function acceptRisk(\mysqli $db, $companyId, $riskId, $actorAuthority, $userId, $note = null, $analystReviewBy = null)
    {
        $st = $db->prepare('SELECT current_level, state FROM risk_register WHERE id = ? AND company_id = ?');
        $st->bind_param('ii', $riskId, $companyId);
        $st->execute();
        $risk = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$risk) { throw new \RuntimeException('RSK-404: الخطر غير موجود'); }
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
        $st = $db->prepare('INSERT INTO risk_acceptances
            (company_id, risk_id, level_at_acceptance, authority, accepted_by, analyst_review_by, review_due, note)
            VALUES (?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL ? DAY),?)');
        $st->bind_param('iissiiis', $companyId, $riskId, $level, $auth, $userId, $analystReviewBy, $reviewDays, $note);
        $st->execute(); $st->close();

        $up = $db->prepare("UPDATE risk_register SET state = 'accepted', review_due = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id = ? AND company_id = ?");
        $up->bind_param('iii', $reviewDays, $riskId, $companyId);
        $up->execute(); $up->close();
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
        return true;
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
        }
        return $ok;
    }

    /** التصعيد الآلي (RK-08) — لا أحد يخفيه ولا مدير المخاطر نفسه */
    public static function escalate(\mysqli $db, $companyId, $riskId, $signalId, $reason, $toAuthority, $userId)
    {
        $to = in_array($toAuthority, array('risk_manager', 'deputy', 'ceo'), true) ? $toAuthority : 'risk_manager';
        $st = $db->prepare('INSERT INTO risk_escalations (company_id, risk_id, signal_id, reason_ar, to_authority, is_auto) VALUES (?,?,?,?,?,1)');
        $st->bind_param('iiiss', $companyId, $riskId, $signalId, $reason, $to);
        $st->execute(); $st->close();
        return (int) $db->insert_id;
    }
}
