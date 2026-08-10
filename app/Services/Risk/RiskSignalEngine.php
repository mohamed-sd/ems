<?php

namespace App\Services\Risk;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/RiskService.php';

/**
 * RiskSignalEngine — قواعدُ إشاراتِ الخطرِ الستَّ عشرةَ (§13-5)
 * ═══════════════════════════════════════════════════════════════════════════
 * «الإشارةُ ليست خطرًا — وتمرُّ بفرزٍ بأربعةِ قرارات» (RK-05). فهذا المحرّكُ يرصد
 * ولا يسجّل خطرًا أبدًا: كلُّ ما يُنتجه إشارةٌ في risk_signals تنتظر محللًا.
 * و«التحويلُ الآليُّ لكل بلاغٍ وعطلٍ إلى خطرٍ يُنتج آلافَ المخاطرِ المزعجةِ ويُفقد
 * السجلَّ معناه» — فلا سطرَ هنا يكتب في risk_register.
 *
 * العطالة: كلُّ قاعدةٍ تحمل rule_key حتميًّا من (الرمز + الكيان + نافذةِ الزمن).
 * فتشغيلُ المحرّكِ مرتين في اليومِ لا يُنتج إشارتين لنفس الواقعة — والفريدُ
 * uq_sig_rule يصدُّ الثانيةَ بنيويًّا لا بفحصٍ متسامح.
 *
 * القواعدُ الأربعَ عشرةَ المرصودةُ هنا آليًّا · واثنتان تُطلقان من مسارِ الفعلِ
 * نفسِه لأنهما لحظيتان لا دوريتان:
 *   SG-10 فشلُ ضابطٍ حرج ← RiskService::failCriticalControl (تصعيدٌ في اليومِ نفسه)
 *   SG-14 واقعةٌ كادت تقع ← RiskService::logIncident
 * فالمجموعُ ١٦/١٦ — ولا قاعدةَ بلا منفِّذ.
 *
 * حدُّ الصدق: القاعدةُ تُقرأ من جدولِها المالكِ قراءةً فقط — لا يكتب هذا المحرّكُ
 * في جدولِ إدارةٍ أخرى (§7-3). وما لا مصدرَ له في القاعدةِ الحيةِ يُعلَن معطَّلًا
 * بسببِه في التقرير، ولا يُدَّعى تنفيذُه.
 */
class RiskSignalEngine
{
    /** الوحدةُ المرشَّحةُ لكل قاعدة (§13-5 العمود الأخير) — ru_code لا id */
    const RULE_UNIT = array(
        'SG-01' => 'RU-03', 'SG-02' => 'RU-02', 'SG-03' => 'RU-03', 'SG-04' => 'RU-04',
        'SG-05' => 'RU-06', 'SG-06' => 'RU-08', 'SG-07' => 'RU-07', 'SG-08' => 'RU-08',
        'SG-09' => 'RU-06', 'SG-10' => 'RU-10', 'SG-11' => 'RU-02', 'SG-12' => 'RU-11',
        'SG-13' => 'RU-11', 'SG-14' => 'RU-10', 'SG-15' => 'RU-01', 'SG-16' => 'RU-08',
    );

    /**
     * تشغيلُ كلِّ القواعدِ الدوريةِ لشركةٍ. يعود تقريرًا بكلِّ قاعدةٍ وما أنتجت.
     * @param bool $dry جفافًا: يعدُّ ولا يكتب — لفحصِ القاعدةِ قبل تفعيلها
     */
    public static function runAll(\mysqli $db, $companyId, $userId = 0, $dry = false)
    {
        $report = array();
        foreach (array('sg01', 'sg02', 'sg03', 'sg04', 'sg05', 'sg06', 'sg07', 'sg08',
                       'sg09', 'sg11', 'sg12', 'sg13', 'sg16') as $m) {
            $code = strtoupper(substr($m, 0, 2)) . '-' . substr($m, 2);
            try {
                $report[$code] = self::$m($db, $companyId, $userId, $dry);
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'مِرقابُ إشاراتٍ واحدٌ فشل — بقيةُ المراقيبِ تعمل، ونتيجتُه تغيب من تقريرِ الجولة');
                $report[$code] = array('ok' => false, 'raised' => 0, 'reason' => $t->getMessage());
            }
        }
        return $report;
    }

    /** معرّفُ وحدةِ المخاطرِ من رمزها — مرشَّحٌ للإشارةِ لا حكمٌ نهائي (الفرزُ يحكم) */
    private static function unitId(\mysqli $db, $companyId, $ruCode)
    {
        static $cache = array();
        $k = $companyId . '|' . $ruCode;
        if (isset($cache[$k])) { return $cache[$k]; }
        $st = $db->prepare('SELECT id FROM risk_units WHERE company_id = ? AND ru_code = ?');
        $st->bind_param('is', $companyId, $ruCode);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $cache[$k] = $r ? (int) $r['id'] : null;
    }

    /** إطلاقُ إشارةٍ آليةٍ بمفتاحِ قاعدةٍ — العطالةُ تصدُّ التكرارَ صمتًا */
    private static function raise(\mysqli $db, $companyId, $sg, $ruleKey, array $d, $userId, $dry)
    {
        if ($dry) { return false; }
        $d['sg_code'] = $sg;
        $d['source'] = 'auto';
        $d['rule_key'] = $ruleKey;
        $d['ru_hint_id'] = self::unitId($db, $companyId, self::RULE_UNIT[$sg] ?? 'RU-01');
        $r = RiskService::createSignal($db, $companyId, $d, $userId ?: 1);
        return empty($r['idempotent']);
    }

    /** نتيجةٌ موحَّدةٌ لكلِّ قاعدة */
    private static function res($raised, $seen, $note = '')
    {
        return array('ok' => true, 'raised' => $raised, 'matched' => $seen, 'note' => $note);
    }

    /* ═══ SG-01 · عطلٌ متكررٌ لمعدةٍ واحدة: ثلاثةُ أعطالٍ في تسعين يومًا ═══ */
    public static function sg01(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT b.equipment_id, COUNT(*) n, MAX(b.report_datetime) last_at,
                       COALESCE(e.code, CONCAT('#', b.equipment_id)) eq_code
                  FROM mnt_breakdown b
                  LEFT JOIN equipments e ON e.id = b.equipment_id AND e.company_id = b.company_id
                 WHERE b.company_id = ? AND b.is_deleted = 0
                   AND b.equipment_id IS NOT NULL
                   AND b.report_datetime >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                 GROUP BY b.equipment_id, e.code
                HAVING n >= 3";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            // النافذةُ تُقرَّب لأسبوعٍ في المفتاح: القاعدةُ تبقى مطلقةً مرةً في الأسبوع
            // لا مرةً كلَّ تشغيلٍ، فلا يغرق الصندوقُ بإشارةٍ لكل يومٍ للمعدةِ نفسها.
            $key = 'SG-01:eq' . (int) $x['equipment_id'] . ':' . gmdate('oW');
            if (self::raise($db, $companyId, 'SG-01', $key, array(
                'title' => 'عطل متكرر — المعدة ' . $x['eq_code'] . ' (' . (int) $x['n'] . ' أعطال في 90 يومًا)',
                'details' => 'آخر عطل: ' . $x['last_at'] . ' · العدد في النافذة: ' . (int) $x['n'],
                'root_cause' => 'تكرار أعطال معدة',
                'entity_type' => 'equipment', 'entity_id' => (int) $x['equipment_id'],
                'equipment_id' => (int) $x['equipment_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'mnt_breakdown · ٣ أعطال/٩٠ يومًا');
    }

    /* ═══ SG-02 · توقفٌ فوق الحدِّ في وردية ═══ */
    public static function sg02(\mysqli $db, $companyId, $userId, $dry)
    {
        // حدُّ التوقفِ المعلَن: ثمانيُ ساعاتٍ في أمرِ صيانةٍ واحدٍ = ورديةٌ كاملةٌ ضائعة.
        $sql = "SELECT o.id, o.equipment_id, o.downtime_hours, o.work_end,
                       COALESCE(e.code, CONCAT('#', o.equipment_id)) eq_code
                  FROM mnt_order o
                  LEFT JOIN equipments e ON e.id = o.equipment_id AND e.company_id = o.company_id
                 WHERE o.company_id = ? AND o.is_deleted = 0
                   AND o.downtime_hours >= 8
                   AND o.work_end >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-02:ord' . (int) $x['id'];
            if (self::raise($db, $companyId, 'SG-02', $key, array(
                'title' => 'توقف فوق الحد — ' . $x['eq_code'] . ' (' . (float) $x['downtime_hours'] . ' ساعة)',
                'details' => 'أمر الصيانة #' . (int) $x['id'] . ' · انتهى: ' . (string) $x['work_end'],
                'root_cause' => 'تجاوز حد التوقف المعلن',
                'entity_type' => 'equipment', 'entity_id' => (int) $x['equipment_id'],
                'equipment_id' => (int) $x['equipment_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'mnt_order.downtime_hours ≥ ٨ ساعات');
    }

    /* ═══ SG-03 · صيانةٌ وقائيةٌ متأخرة: تجاوزُ الموعدِ بعشرةِ أيام ═══ */
    public static function sg03(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT p.id, p.code, p.name, p.equipment_id, p.next_due_date,
                       DATEDIFF(CURDATE(), p.next_due_date) late_days
                  FROM mnt_plan p
                 WHERE p.company_id = ? AND p.is_deleted = 0 AND p.state = 'active'
                   AND p.next_due_date IS NOT NULL
                   AND DATEDIFF(CURDATE(), p.next_due_date) >= 10";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-03:plan' . (int) $x['id'] . ':' . (string) $x['next_due_date'];
            if (self::raise($db, $companyId, 'SG-03', $key, array(
                'title' => 'صيانة وقائية متأخرة ' . (int) $x['late_days'] . ' يومًا — ' . (string) $x['code'],
                'details' => (string) $x['name'] . ' · الموعد: ' . (string) $x['next_due_date'],
                'root_cause' => 'تأخر صيانة وقائية',
                'entity_type' => 'equipment', 'entity_id' => (int) $x['equipment_id'],
                'equipment_id' => !empty($x['equipment_id']) ? (int) $x['equipment_id'] : null,
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'mnt_plan.next_due_date + ١٠ أيام');
    }

    /* ═══ SG-04 · رخصةٌ أو وثيقةٌ تنتهي: ثلاثون يومًا قبل الانتهاء ═══ */
    public static function sg04(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT d.doc_id, d.subject_type, d.subject_id, d.doc_type, d.doc_no, d.expiry_date,
                       DATEDIFF(d.expiry_date, CURDATE()) days_left
                  FROM equipment_documents d
                 WHERE d.company_id = ? AND d.is_deleted = 0
                   AND d.expiry_date IS NOT NULL
                   AND DATEDIFF(d.expiry_date, CURDATE()) <= 30";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $left = (int) $x['days_left'];
            $key = 'SG-04:doc' . (int) $x['doc_id'] . ':' . (string) $x['expiry_date'];
            if (self::raise($db, $companyId, 'SG-04', $key, array(
                'title' => ($left < 0 ? 'وثيقة منتهية' : 'وثيقة تنتهي بعد ' . $left . ' يومًا')
                           . ' — ' . (string) $x['doc_type'] . ' ' . (string) $x['doc_no'],
                'details' => 'الانتهاء: ' . (string) $x['expiry_date'] . ' · الموضوع: '
                             . (string) $x['subject_type'] . '#' . (int) $x['subject_id'],
                'root_cause' => 'انتهاء وثيقة أو رخصة',
                'entity_type' => (string) $x['subject_type'], 'entity_id' => (int) $x['subject_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'equipment_documents.expiry_date ≤ ٣٠ يومًا');
    }

    /* ═══ SG-05 · تأخرُ الموردِ في الإحلال: تجاوزُ المهلةِ التعاقدية ═══ */
    public static function sg05(\mysqli $db, $companyId, $userId, $dry)
    {
        // الإحلالُ يقع حين تتوقف معدةُ موردٍ ولم يُستبدَل: أمرُ صيانةٍ مفتوحٌ على
        // معدةٍ مملوكةٍ لمورّدٍ تجاوز مهلةً معلَنةً (١٤ يومًا) بلا إنهاء.
        $sql = "SELECT o.id, o.equipment_id, o.charge_supplier_id, o.work_start,
                       DATEDIFF(CURDATE(), DATE(o.work_start)) late_days,
                       COALESCE(e.code, CONCAT('#', o.equipment_id)) eq_code
                  FROM mnt_order o
                  LEFT JOIN equipments e ON e.id = o.equipment_id AND e.company_id = o.company_id
                 WHERE o.company_id = ? AND o.is_deleted = 0
                   AND o.charge_supplier_id IS NOT NULL AND o.charge_supplier_id > 0
                   AND o.work_end IS NULL AND o.work_start IS NOT NULL
                   AND DATEDIFF(CURDATE(), DATE(o.work_start)) >= 14";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-05:ord' . (int) $x['id'] . ':' . gmdate('oW');
            if (self::raise($db, $companyId, 'SG-05', $key, array(
                'title' => 'تأخر المورد في الإحلال ' . (int) $x['late_days'] . ' يومًا — ' . $x['eq_code'],
                'details' => 'أمر مفتوح #' . (int) $x['id'] . ' على حساب المورد ' . (int) $x['charge_supplier_id'],
                'root_cause' => 'تأخر مورد عن مهلة الإحلال',
                'entity_type' => 'supplier', 'entity_id' => (int) $x['charge_supplier_id'],
                'equipment_id' => (int) $x['equipment_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'mnt_order مفتوح على مورّد ≥ ١٤ يومًا');
    }

    /* ═══ SG-06 · ذمةٌ تجاوزت الحدَّ: مستحقٌّ متأخرٌ فوقَ حدٍّ معلَن ═══ */
    public static function sg06(\mysqli $db, $companyId, $userId, $dry)
    {
        // لا عمودَ حدٍّ ائتمانيٍّ في القاعدةِ الحية — فالحدُّ المعلَنُ هنا سلوكيّ:
        // ذمةٌ قائمةٌ تجاوزت تاريخَ استحقاقِها بثلاثين يومًا. وهذا يُعلَن صريحًا في
        // ملاحظةِ القاعدةِ ولا يُدَّعى أنه «الحدُّ المعتمد».
        $sql = "SELECT r.customer_entity_id, SUM(r.outstanding) total, COUNT(*) n,
                       MIN(r.due_date) oldest
                  FROM fin_receivables r
                 WHERE r.company_id = ? AND r.is_deleted = 0
                   AND r.outstanding > 0 AND r.due_date IS NOT NULL
                   AND r.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY r.customer_entity_id";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-06:cust' . (int) $x['customer_entity_id'] . ':' . gmdate('Y-m');
            if (self::raise($db, $companyId, 'SG-06', $key, array(
                'title' => 'ذمة متأخرة فوق الحد — العميل #' . (int) $x['customer_entity_id'],
                'details' => 'القائم المتأخر: ' . (float) $x['total'] . ' على ' . (int) $x['n']
                             . ' مستندًا · أقدم استحقاق: ' . (string) $x['oldest'],
                'root_cause' => 'تجاوز حد التحصيل المعلن',
                'entity_type' => 'customer', 'entity_id' => (int) $x['customer_entity_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'fin_receivables متأخر > ٣٠ يومًا (حدٌّ سلوكيٌّ لا عمودُ ائتمان)');
    }

    /* ═══ SG-07 · عقدٌ يقترب من الانتهاءِ بلا تجديد: تسعون يومًا ═══ */
    public static function sg07(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT c.id, c.actual_end, DATEDIFF(c.actual_end, CURDATE()) days_left
                  FROM contracts c
                 WHERE c.company_id = ? AND c.actual_end IS NOT NULL
                   AND DATEDIFF(c.actual_end, CURDATE()) BETWEEN 0 AND 90";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-07:ct' . (int) $x['id'] . ':' . (string) $x['actual_end'];
            if (self::raise($db, $companyId, 'SG-07', $key, array(
                'title' => 'عقد ينتهي بعد ' . (int) $x['days_left'] . ' يومًا بلا تجديد — #' . (int) $x['id'],
                'details' => 'نهاية العقد: ' . (string) $x['actual_end'],
                'root_cause' => 'اقتراب انتهاء عقد بلا تجديد',
                'entity_type' => 'contract', 'entity_id' => (int) $x['id'],
                'scope_ref_type' => 'contract', 'scope_ref_id' => (int) $x['id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'contracts.actual_end ≤ ٩٠ يومًا');
    }

    /* ═══ SG-08 · انحرافُ سعرِ الصرفِ عن نطاقِ التحمل ═══ */
    public static function sg08(\mysqli $db, $companyId, $userId, $dry)
    {
        // النطاق: تغيّرٌ ≥ ١٠٪ بين سعرٍ نافذٍ وسابقِه المباشرِ لنفس العملة.
        // السابقُ المباشرُ لا أيُّ سابقٍ: وإلا قِيس سعرُ اليومِ على سعرِ سنةٍ ماضيةٍ
        // فبدا كلُّ شيءٍ انحرافًا. ولذلك ترتيبُ الصفوفِ ونافذةُ الثلاثين يومًا معًا.
        $sql = "SELECT a.currency_code, a.rate_to_base new_rate, a.effective_from,
                       (SELECT b.rate_to_base FROM fin_fx_rates b
                         WHERE b.company_id = a.company_id AND b.currency_code = a.currency_code
                           AND b.is_deleted = 0 AND b.effective_from < a.effective_from
                         ORDER BY b.effective_from DESC, b.id DESC LIMIT 1) old_rate
                  FROM fin_fx_rates a
                 WHERE a.company_id = ? AND a.is_deleted = 0
                   AND a.effective_from >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 HAVING old_rate IS NOT NULL AND old_rate > 0
                    AND ABS(new_rate - old_rate) / old_rate * 100 >= 10";
        $st = $db->prepare($sql);
        if (!$st) {
            return array('ok' => true, 'raised' => 0, 'matched' => 0,
                'note' => 'معطَّلة بسببها: مخطَّطُ أسعارِ الصرفِ لا يوافق القاعدة');
        }
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $old = (float) $x['old_rate']; $new = (float) $x['new_rate'];
            $pct = $old > 0 ? abs($new - $old) / $old * 100 : 0;
            $key = 'SG-08:' . (string) $x['currency_code'] . ':' . (string) $x['effective_from'];
            if (self::raise($db, $companyId, 'SG-08', $key, array(
                'title' => 'انحراف سعر صرف ' . round($pct, 1) . '٪ — ' . (string) $x['currency_code'],
                'details' => 'من ' . $old . ' إلى ' . $new . ' نفاذًا من ' . (string) $x['effective_from'],
                'root_cause' => 'انحراف سعر صرف عن نطاق التحمل',
                'entity_type' => 'currency', 'entity_id' => 0,
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'fin_fx_rates تغيّر ≥ ١٠٪ عن السابق المباشر');
    }

    /* ═══ SG-09 · مخزونٌ حرجٌ تحت الحد: بلوغُ حدِّ إعادةِ الطلب ═══ */
    public static function sg09(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT i.id, i.code, i.name, i.min_qty,
                       COALESCE(SUM(CASE WHEN m.move_type IN ('in','return') THEN m.qty
                                         WHEN m.move_type IN ('out','issue','scrap') THEN -m.qty
                                         ELSE 0 END), 0) on_hand
                  FROM proc_item i
                  LEFT JOIN proc_stock_move m ON m.item_id = i.id AND m.company_id = i.company_id
                 WHERE i.company_id = ? AND i.is_deleted = 0 AND i.is_critical = 1
                   AND i.min_qty > 0
                 GROUP BY i.id, i.code, i.name, i.min_qty
                HAVING on_hand <= i.min_qty";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-09:item' . (int) $x['id'] . ':' . gmdate('oW');
            if (self::raise($db, $companyId, 'SG-09', $key, array(
                'title' => 'صنف حرج تحت الحد — ' . (string) $x['code'] . ' ' . (string) $x['name'],
                'details' => 'المتاح: ' . (float) $x['on_hand'] . ' · الحد الأدنى: ' . (float) $x['min_qty'],
                'root_cause' => 'نفاد صنف حرج',
                'entity_type' => 'proc_item', 'entity_id' => (int) $x['id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'proc_item الحرج ≤ min_qty');
    }

    /* ═══ SG-11 · تكرارُ بلاغٍ من النوعِ نفسِه: ثلاثةٌ في ثلاثين يومًا ═══ */
    public static function sg11(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT t.ticket_type_id, COUNT(*) n, MAX(t.call_date) last_at
                  FROM tickets t
                 WHERE t.company_id = ? AND t.ticket_type_id IS NOT NULL
                   AND t.call_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY t.ticket_type_id
                HAVING n >= 3";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-11:tt' . (int) $x['ticket_type_id'] . ':' . gmdate('oW');
            if (self::raise($db, $companyId, 'SG-11', $key, array(
                'title' => 'تكرار بلاغ من النوع نفسه (' . (int) $x['n'] . ' في 30 يومًا) — نوع #' . (int) $x['ticket_type_id'],
                'details' => 'آخر بلاغ: ' . (string) $x['last_at'],
                'root_cause' => 'تكرار بلاغات من نوع واحد',
                'entity_type' => 'ticket_type', 'entity_id' => (int) $x['ticket_type_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'tickets ٣ من النوع/٣٠ يومًا');
    }

    /* ═══ SG-12 · مخالفةُ حوكمةٍ أو تجاوزُ حارس: رفضٌ متكررٌ من الحارس ═══ */
    public static function sg12(\mysqli $db, $companyId, $userId, $dry)
    {
        $sql = "SELECT l.person_id, COUNT(*) n, MAX(l.at) last_at
                  FROM action_execution_log l
                 WHERE l.company_id = ? AND l.denied_by_guard = 1
                   AND l.at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY l.person_id
                HAVING n >= 5";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-12:p' . (int) $x['person_id'] . ':' . gmdate('oW');
            if (self::raise($db, $companyId, 'SG-12', $key, array(
                'title' => 'رفض متكرر من الحارس (' . (int) $x['n'] . ' في أسبوع) — مستخدم #' . (int) $x['person_id'],
                'details' => 'آخر رفض: ' . (string) $x['last_at'],
                'root_cause' => 'محاولات متكررة يرفضها الحارس',
                'entity_type' => 'user', 'entity_id' => (int) $x['person_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'action_execution_log رفضٌ ≥ ٥/أسبوع');
    }

    /* ═══ SG-13 · محاولةُ وصولٍ غيرُ معتادة: تجاوزُ المتوسطِ بضعفين ═══ */
    public static function sg13(\mysqli $db, $companyId, $userId, $dry)
    {
        // المتوسطُ مرجعٌ حيٌّ لا رقمٌ مخترع: متوسطُ محاولاتِ الشخصِ اليوميةِ في
        // ثلاثين يومًا، ومن تجاوز ضعفَيه أمسِ يُرصد. ومن لا تاريخَ له لا متوسطَ له.
        $sql = "SELECT t.person_id, t.today_n, t.avg_n
                  FROM (
                    SELECT l.person_id,
                           SUM(CASE WHEN DATE(l.at) = CURDATE() THEN 1 ELSE 0 END) today_n,
                           COUNT(*) / 30 avg_n
                      FROM action_execution_log l
                     WHERE l.company_id = ? AND l.at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     GROUP BY l.person_id
                  ) t
                 WHERE t.avg_n >= 1 AND t.today_n >= t.avg_n * 2 AND t.today_n >= 10";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-13:p' . (int) $x['person_id'] . ':' . gmdate('Y-m-d');
            if (self::raise($db, $companyId, 'SG-13', $key, array(
                'title' => 'وصول غير معتاد — مستخدم #' . (int) $x['person_id'],
                'details' => 'اليوم: ' . (int) $x['today_n'] . ' محاولة · المتوسط اليومي: '
                             . round((float) $x['avg_n'], 1),
                'root_cause' => 'نمط وصول غير معتاد',
                'entity_type' => 'user', 'entity_id' => (int) $x['person_id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'action_execution_log اليومُ ≥ ٢× المتوسط');
    }

    /* ═══ SG-16 · انحرافُ التكلفةِ عن الميزانية: تجاوزُ النطاقِ المعتمد ═══ */
    public static function sg16(\mysqli $db, $companyId, $userId, $dry)
    {
        // النطاقُ المعتمد: انحرافٌ ≥ ١٠٪ على بندِ ميزانيةٍ في ميزانيةٍ معتمدة.
        $sql = "SELECT l.id, l.category, l.planned_amount, l.actual_amount, l.variance_pct,
                       b.budget_no, b.fiscal_year, b.period_no
                  FROM fin_budget_lines l
                  JOIN fin_budgets b ON b.id = l.budget_id AND b.company_id = l.company_id
                 WHERE l.company_id = ? AND b.is_deleted = 0 AND b.state = 'approved'
                   AND l.planned_amount > 0
                   AND ABS(COALESCE(l.variance_pct, 0)) >= 10";
        $st = $db->prepare($sql);
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = $st->get_result();
        $raised = 0; $seen = 0;
        while ($x = $rows->fetch_assoc()) {
            $seen++;
            $key = 'SG-16:bl' . (int) $x['id'] . ':' . gmdate('Y-m');
            if (self::raise($db, $companyId, 'SG-16', $key, array(
                'title' => 'انحراف تكلفة ' . round((float) $x['variance_pct'], 1) . '٪ — '
                           . (string) $x['category'],
                'details' => 'الميزانية ' . (string) $x['budget_no'] . ' · المخطط: '
                             . (float) $x['planned_amount'] . ' · الفعلي: ' . (float) $x['actual_amount'],
                'root_cause' => 'انحراف التكلفة عن الميزانية المعتمدة',
                'entity_type' => 'budget_line', 'entity_id' => (int) $x['id'],
            ), $userId, $dry)) { $raised++; }
        }
        $st->close();
        return self::res($raised, $seen, 'fin_budget_lines انحرافٌ ≥ ١٠٪');
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * المرحلة ١٢ «المراقبة» — محرّكُ المؤشرات: «تُقرأ من النظامِ آليًّا»
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * قارئاتُ المؤشراتِ الآلية: مفتاحُ المصدرِ ⇒ استعلامُ قيمةٍ واحدة.
     * المؤشرُ الذي لا مفتاحَ له يبقى «يدويًّا» معلَنًا — و§12-3 تقول «تُعلَن ولا
     * تُخفى»، فلا يُدَّعى أنه آليٌّ وهو مُدخَلٌ بيدٍ.
     */
    const KRI_READERS = array(
        'open_critical_risks' => "SELECT COUNT(*) v FROM risk_register
                                   WHERE company_id = ? AND state <> 'closed' AND merged_into_id IS NULL
                                     AND current_level IN ('حرج','محظور')",
        'pending_signals'     => "SELECT COUNT(*) v FROM risk_signals
                                   WHERE company_id = ? AND state = 'pending'",
        'overdue_treatments'  => "SELECT COUNT(*) v FROM risk_treatments
                                   WHERE company_id = ? AND state NOT IN ('verified')
                                     AND due_date < CURDATE()",
        'ineffective_controls' => "SELECT COUNT(*) v FROM risk_controls
                                    WHERE company_id = ? AND active = 1
                                      AND effectiveness IN ('غير فعال','غير مثبت')",
        'critical_ctl_overdue' => "SELECT COUNT(*) v FROM risk_controls
                                    WHERE company_id = ? AND active = 1 AND is_critical = 1
                                      AND (next_verify_due IS NULL OR next_verify_due < CURDATE())",
        'risks_over_appetite' => "SELECT COUNT(*) v FROM risk_register
                                   WHERE company_id = ? AND state <> 'closed' AND merged_into_id IS NULL
                                     AND appetite_verdict IN ('فوق الشهية','فوق حد التحمل','محظور')",
        'reviews_overdue'     => "SELECT COUNT(*) v FROM risk_register
                                   WHERE company_id = ? AND state <> 'closed' AND merged_into_id IS NULL
                                     AND review_due IS NOT NULL AND review_due < CURDATE()",
        'equip_breakdowns_90d' => "SELECT COUNT(*) v FROM mnt_breakdown
                                    WHERE company_id = ? AND is_deleted = 0
                                      AND report_datetime >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        'overdue_receivables' => "SELECT COUNT(*) v FROM fin_receivables
                                   WHERE company_id = ? AND is_deleted = 0 AND outstanding > 0
                                     AND due_date < CURDATE()",
        'expiring_docs_30d'   => "SELECT COUNT(*) v FROM equipment_documents
                                   WHERE company_id = ? AND is_deleted = 0 AND expiry_date IS NOT NULL
                                     AND DATEDIFF(expiry_date, CURDATE()) BETWEEN 0 AND 30",
    );

    /**
     * قراءةُ المؤشراتِ الآليةِ وحساب حالتِها وتصعيدُ الحرج (المرحلتان ١٢ و١٣).
     * «بلوغُ الحدِّ الحرج يولّد إشارةَ SG-15 آليًّا» — والإشارةُ بمفتاحِ يومٍ لا تتكرر.
     */
    public static function readKris(\mysqli $db, $companyId, $userId = 0, $dry = false)
    {
        $st = $db->prepare("SELECT id, name_ar, source_key, warn_num, critical_num, direction
                              FROM risk_kris
                             WHERE company_id = ? AND active = 1 AND read_mode = 'آلي' AND source_key <> ''");
        $st->bind_param('i', $companyId);
        $st->execute();
        $kris = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $kris[] = $x; }
        $st->close();

        $out = array('read' => 0, 'breached' => 0, 'skipped' => 0, 'details' => array());
        foreach ($kris as $k) {
            $key = (string) $k['source_key'];
            if (!isset(self::KRI_READERS[$key])) {
                $out['skipped']++;
                $out['details'][] = array('id' => (int) $k['id'], 'name' => $k['name_ar'],
                    'skipped' => 'لا قارئَ لمفتاح ' . $key);
                continue;
            }
            $q = $db->prepare(self::KRI_READERS[$key]);
            $q->bind_param('i', $companyId);
            $q->execute();
            $val = (float) ($q->get_result()->fetch_assoc()['v'] ?? 0);
            $q->close();

            $state = 'ok';
            $up = (string) $k['direction'] === 'تصاعدي';
            $crit = ($k['critical_num'] === null) ? null : (float) $k['critical_num'];
            $warn = ($k['warn_num'] === null) ? null : (float) $k['warn_num'];
            if ($crit !== null && (($up && $val >= $crit) || (!$up && $val <= $crit))) { $state = 'critical'; }
            elseif ($warn !== null && (($up && $val >= $warn) || (!$up && $val <= $warn))) { $state = 'warn'; }

            if (!$dry) {
                $vs = (string) $val;
                $u = $db->prepare("UPDATE risk_kris SET current_value = ?, kri_state = ?, last_read_at = NOW(), last_read_by = ? WHERE id = ? AND company_id = ?");
                $u->bind_param('ssiii', $vs, $state, $userId, $k['id'], $companyId);
                $u->execute(); $u->close();
            }
            $out['read']++;
            $out['details'][] = array('id' => (int) $k['id'], 'name' => $k['name_ar'],
                'value' => $val, 'state' => $state);

            if ($state === 'critical' && !$dry) {
                $out['breached']++;
                RiskService::createSignal($db, $companyId, array(
                    'sg_code' => 'SG-15', 'source' => 'auto',
                    'rule_key' => 'SG-15:kri' . (int) $k['id'] . ':' . gmdate('Y-m-d'),
                    'title' => 'مؤشر بلغ حده الحرج — ' . (string) $k['name_ar'],
                    'details' => 'القيمة المقروءة آليًّا: ' . $val . ' · الحد الحرج: ' . $crit,
                    'root_cause' => 'تجاوز مؤشر خطر حده الحرج',
                    'ru_hint_id' => self::unitId($db, $companyId, 'RU-01'),
                ), $userId ?: 1);
                RiskEvents::fire($db, $companyId, 'KRIBreached', (int) $k['id'], array(
                    'name' => $k['name_ar'], 'value' => $val, 'critical_num' => $crit,
                    'source_key' => $key, 'read_mode' => 'آلي',
                ), $userId ?: 1, gmdate('Y-m-d'));
            }
        }
        return $out;
    }

    /**
     * المراجعاتُ المستحقةُ (المرحلة ١٣): الخطرُ الذي تجاوز مهلةَ مراجعتِه يُصعَّد
     * لمديرِ المخاطر. «الإجراءُ المتأخرُ يظهر في المهامِّ ويُصعَّد» (§14-5) —
     * وهنا الخطرُ لا الإجراء، فالتصعيدُ لمديرِ المخاطرِ لا للرئيس.
     */
    public static function sweepOverdueReviews(\mysqli $db, $companyId, $userId = 0, $dry = false)
    {
        $st = $db->prepare("SELECT id, risk_code, current_level, review_due
                              FROM risk_register
                             WHERE company_id = ? AND state <> 'closed' AND merged_into_id IS NULL
                               AND review_due IS NOT NULL AND review_due < CURDATE()");
        $st->bind_param('i', $companyId);
        $st->execute();
        $rows = array();
        $res = $st->get_result();
        while ($x = $res->fetch_assoc()) { $rows[] = $x; }
        $st->close();
        $n = 0;
        foreach ($rows as $r) {
            if ($dry) { $n++; continue; }
            // عطالةٌ بالشهر: لا يُصعَّد الخطرُ نفسُه كلَّ يومٍ حتى تُجرى مراجعتُه.
            $key = 'RVWDUE:' . (int) $r['id'] . ':' . gmdate('Y-m');
            $chk = $db->prepare("SELECT id FROM risk_escalations
                                  WHERE company_id = ? AND risk_id = ? AND reason_ar LIKE ?
                                    AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
            $like = '%' . $key . '%';
            $chk->bind_param('iis', $companyId, $r['id'], $like);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($exists) { continue; }
            RiskService::escalate($db, $companyId, (int) $r['id'], null,
                'مراجعة مستحقة متأخرة (' . (string) $r['review_due'] . ') — ' . $key,
                'risk_manager', $userId ?: 1);
            $n++;
        }
        return array('overdue' => count($rows), 'escalated' => $n);
    }
}
