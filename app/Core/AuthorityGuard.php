<?php
/**
 * حارس التفويض بالتوقيع — AuthorityGuard (LEG-01 §4 · §6-③-ب · §9)
 * ───────────────────────────────────────────────────────────────────────────
 * «الاعتماد توقيع له أثر قانوني لا نقرة زر» — يُستدعى من داخل خدمات الاعتماد
 * القائمة قبل كل حفظ، **ولا يُنشئ مسار اعتماد موازيًا**:
 *   • تفويض ساري (المدة تُقرأ من التاريخ — الانتهاء يُسقط الصلاحية آليًّا).
 *   • السقف: فوق amount_cap → 409 برسالة المتاح (رفع للأعلى لا زر معطَّل صامت).
 *   • اعتماد الذات → 403 بنيويًّا.
 *   • كل توقيع (أو منع) سطر في approval_signatures — Insert-only.
 *
 * GovernanceFlagService مدمجة: أعلام أنماط التفعيل — الافتراض النمط ① (مطفأ)،
 * والعقد يغلب الكيان. **العناصر غير المفعَّلة لا تُصيَّر ولا تُطلب ولا تعطِّل.**
 */

namespace App\Core;

class AuthorityGuard
{
    /**
     * فحص + توقيع اعتمادٍ واحد. يُستدعى داخل معاملة خدمة الاعتماد.
     * @param array $a {document_type, document_id, step, person_id, company_id,
     *                  entity_id?, amount?, created_by_person_id? (لمنع اعتماد الذات)}
     * @return array{ok:bool,code:int,reason:string,sig_id:int,auth_id:?int}
     */
    public static function sign(\mysqli $conn, array $a)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'sig_id' => 0, 'auth_id' => null);
        $company = intval($a['company_id']);
        $person = intval($a['person_id']);
        $docType = (string) $a['document_type'];
        $docId = intval($a['document_id']);
        $step = isset($a['step']) ? (string) $a['step'] : 'approve';
        $amount = isset($a['amount']) ? (float) $a['amount'] : null;

        // ① اعتماد الذات — بنيويًّا في كل الصناديق (قيد ⑤)
        if (isset($a['created_by_person_id']) && intval($a['created_by_person_id']) === $person) {
            $out['code'] = 403; $out['reason'] = 'لا يعتمد المرء ما أنشأه — بنيويا';
            self::record($conn, $company, $docType, $docId, $step, $person, null, $amount, 'denied');
            return $out;
        }

        // ①-ب السلطة التنظيمية (update0004 · ORG-01 §7-① · ORG-07) — خلف علم
        //    EMS_ORG_AUTHORITY (off·monitor·enforce): يُستدعى OrgAuthorityResolver
        //    قبل كل اعتماد؛ monitor يسجّل المخالفة في guard_denials ويمضي،
        //    وenforce يرفض 403 — والتوقيع يحمل مرجع التكليف (O8).
        $orgAsgId = null;
        $orgMode = function_exists('ems_env') ? strtolower((string) ems_env('EMS_ORG_AUTHORITY', 'off')) : 'off';
        if ($orgMode === 'monitor' || $orgMode === 'enforce') {
            require_once dirname(__DIR__) . '/Services/Org/OrgAuthorityResolver.php';
            $orgChk = \App\Services\Org\OrgAuthorityResolver::can($conn, $person, $company, array(
                'scope_type' => isset($a['scope_type']) ? $a['scope_type'] : null,
                'scope_id' => isset($a['scope_id']) ? $a['scope_id'] : null,
                'attempted_ref' => $docType . '#' . $docId,
            ));
            if ($orgChk['ok']) {
                $orgAsgId = $orgChk['asg_id'];
            } elseif ($orgMode === 'enforce') {
                $out['code'] = intval($orgChk['code']);
                $out['reason'] = $orgChk['reason'];
                self::record($conn, $company, $docType, $docId, $step, $person, null, $amount, 'denied');
                return $out;
            }
            // monitor: المخالفة سُجِّلت في guard_denials داخل الحلّال — والاعتماد يمضي
        }

        // ② التفويض الساري — النمط ①: إن لم يكن عنصر السقوف مفعَّلًا للكيان/العقد
        //    فالاعتماد الداخلي يمضي بلا تفويض (ويُسجَّل توقيعًا بلا auth_id)؛
        //    وإن فُعّل signing_caps صار التفويض الساري شرطًا (403 بدونه).
        $entityId = isset($a['entity_id']) ? intval($a['entity_id']) : self::tenantEntity($conn, $company);
        $capsOn = self::flagEnabled($conn, 'signing_caps', $entityId, isset($a['contract_id']) ? intval($a['contract_id']) : null);

        $auth = null;
        if ($entityId) {
            $stmt = $conn->prepare(
                "SELECT auth_id, amount_cap, currency FROM signing_authorities
                  WHERE company_id = ? AND person_id = ? AND entity_id = ? AND state = 'active'
                    AND valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())
                  ORDER BY (amount_cap IS NULL) DESC, amount_cap DESC LIMIT 1");
            $stmt->bind_param('iii', $company, $person, $entityId);
            $stmt->execute();
            $auth = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($capsOn) {
            if (!$auth) {
                $out['code'] = 403; $out['reason'] = 'لا تفويض ساريا لهذا الشخص عن الكيان — الاعتماد مرفوض بنيويا';
                self::record($conn, $company, $docType, $docId, $step, $person, null, $amount, 'denied');
                return $out;
            }
            if ($auth['amount_cap'] !== null && $amount !== null && $amount > (float) $auth['amount_cap']) {
                $out['code'] = 409;
                $out['reason'] = 'فوق السقف — المتاح ' . number_format((float) $auth['amount_cap'], 2)
                    . ' ' . $auth['currency'] . '؛ يرفع للمستوى الأعلى';
                self::record($conn, $company, $docType, $docId, $step, $person, intval($auth['auth_id']), $amount, 'denied');
                return $out;
            }
        }

        // ③ سطر التوقيع — Insert-only، وتكرار الخطوة نفسها عاطل (UQ)
        //    ويحمل مرجع تكليف معتمِده إن حُلّ (ORG-01 §8 · O8)
        $authId = $auth ? intval($auth['auth_id']) : null;
        $sigId = self::record($conn, $company, $docType, $docId, $step, $person, $authId, $amount, 'signed', $orgAsgId);
        if ($sigId === 0) {
            // UQ: توقيع قائم لهذه الخطوة — عاطل، يُعاد مرجعه
            $stmt = $conn->prepare('SELECT sig_id FROM approval_signatures WHERE document_type=? AND document_id=? AND person_id=? AND step=? LIMIT 1');
            $stmt->bind_param('siis', $docType, $docId, $person, $step);
            $stmt->execute();
            $ex = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $out['ok'] = true; $out['code'] = 200; $out['sig_id'] = $ex ? intval($ex['sig_id']) : 0;
            $out['auth_id'] = $authId; $out['reason'] = 'توقيع قائم لهذه الخطوة — فعل عاطل';
            return $out;
        }
        $out['ok'] = true; $out['code'] = 201; $out['sig_id'] = $sigId; $out['auth_id'] = $authId;
        $out['reason'] = 'وقع' . ($authId ? ' بمرجع التفويض #' . $authId : ' (نمط ① — عنصر السقوف مطفأ)');
        return $out;
    }

    /** علم تفعيل عنصر حوكمة — العقد يغلب الكيان، والافتراض النمط ① (مطفأ). */
    public static function flagEnabled(\mysqli $conn, $elementCode, $entityId = null, $contractId = null)
    {
        foreach (array(array('contract', $contractId), array('entity', $entityId)) as $probe) {
            if (!$probe[1]) { continue; }
            $stmt = $conn->prepare('SELECT enabled FROM governance_flags WHERE element_code = ? AND scope_type = ? AND scope_id = ? LIMIT 1');
            $sid = intval($probe[1]);
            $stmt->bind_param('ssi', $elementCode, $probe[0], $sid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row !== null) { return intval($row['enabled']) === 1; }
        }
        return false; // الافتراض: النمط ① — مطفأ
    }

    /** كيان المستأجر من حد العزل (tenants) — لا يُشتق من صفة. */
    public static function tenantEntity(\mysqli $conn, $companyId)
    {
        $stmt = $conn->prepare('SELECT entity_id FROM tenants WHERE tenant_id = ? LIMIT 1');
        $companyId = intval($companyId);
        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? intval($row['entity_id']) : null;
    }

    /** مسح انتهاء التفويضات — ExpiryMonitor (يُستدعى من الدوريات). */
    public static function sweepExpiring(\mysqli $conn, $daysAhead = 30)
    {
        $stmt = $conn->prepare(
            "SELECT auth_id, company_id, person_id, valid_to FROM signing_authorities
              WHERE state = 'active' AND valid_to IS NOT NULL AND valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)");
        $daysAhead = intval($daysAhead);
        $stmt->bind_param('i', $daysAhead);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $r) {
            $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                          VALUES (" . intval($r['company_id']) . ", 'finance_manager',
                          'تفويض توقيع #" . intval($r['auth_id']) . " ينتهي في " . $r['valid_to'] . "', 'admin/bus_monitor.php')");
        }
        return count($rows);
    }

    private static function record(\mysqli $conn, $company, $docType, $docId, $step, $person, $authId, $amount, $result, $orgAsgId = null)
    {
        $stmt = $conn->prepare(
            'INSERT IGNORE INTO approval_signatures (company_id, document_type, document_id, step, person_id, auth_id, org_asg_id, amount, ip, result)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli';
        $stmt->bind_param('isisiiidss', $company, $docType, $docId, $step, $person, $authId, $orgAsgId, $amount, $ip, $result);
        $stmt->execute();
        $id = ($stmt->affected_rows === 1) ? intval($stmt->insert_id) : 0;
        $stmt->close();
        return $id;
    }
}
