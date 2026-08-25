<?php
/**
 * app/Services/Finance/CoaService.php — الشجرةُ والأبعادُ ومصفوفةُ الترحيل
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: EQUIPATION-COA-01 و MAP-7 الورقةُ 37.
 * الأحكامُ المنفَّذةُ هنا حرّاسًا في الخادمِ لا وصفًا:
 *   · R9  كلُّ قيدٍ يحمل الأبعادَ التي يلزمها حسابُه — والناقصُ يُرفض برمز.
 *   · R8  لا حسابَ يُنشأ لواقعةٍ يمكن تمييزُها ببُعد — والإنشاءُ يُرفض.
 *   · R2  العهدةُ حسابٌ واحدٌ والشخصُ بُعد D6 — واسمُ الشخصِ في الاسمِ يُرفض.
 *   · مصفوفةُ الترحيل: الحسابُ يُشتق من (الإدارةِ × نموذجِ العملِ × نوعِ العقد)
 *     ولا يُختار يدويًّا — واختيارُه خارجَ المصفوفةِ يُرفض.
 *   · المستوى ١ و٢ تجميعيان: لا يُقيَّد عليهما (is_postable = 0).
 */

namespace App\Services\Finance;

class CoaService
{
    /** الأبعادُ التسعةُ بأسمائها — والترتيبُ ترتيبُ الوثيقة (§02). */
    const DIMS = array(
        'D1' => 'الكيان', 'D2' => 'المشروع', 'D3' => 'الموقع', 'D4' => 'مركز التكلفة',
        'D5' => 'المعدة', 'D6' => 'الطرف المقابل', 'D7' => 'نموذج العمل',
        'D8' => 'العقد', 'D9' => 'نوع العقد',
    );

    /** عمودُ سطرِ القيدِ الذي يحمل كلَّ بُعد. */
    const DIM_COLUMN = array(
        'D1' => 'company_id', 'D2' => 'project_id', 'D3' => 'site_id',
        'D4' => 'cost_center_id', 'D5' => 'equipment_id', 'D6' => 'counterparty_id',
        'D7' => 'business_model', 'D8' => 'contract_id', 'D9' => 'contract_type_code',
    );

    /** كلماتٌ تدلُّ على اسمِ شخصٍ في اسمِ حساب — R2 يرفضها. */
    const PERSON_HINTS = array('Custody', 'عهدة', 'عهدة', 'سلفة');

    /** الحسابُ القانونيُّ بكودِه — أو null. */
    public static function account(\mysqli $db, $companyId, $code)
    {
        $st = $db->prepare("SELECT id, code, name, account_type, acc_level, parent_code,
                                   balance_nature, statement_code, statement_line,
                                   cashflow_activity, required_dims, is_postable
                              FROM fin_chart_of_accounts
                             WHERE company_id = ? AND code = ? AND is_canonical = 1 LIMIT 1");
        $st->bind_param('is', $companyId, $code);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * حارسُ الأبعاد (R9): يرفض القيدَ الذي ينقصه بُعدٌ يلزم حسابَه.
     * @param array $dims قيمُ الأبعادِ المقدَّمة — المفاتيحُ D1..D9
     * @throws \RuntimeException COA-DIM-422 بالبُعدِ الناقصِ باسمه
     */
    public static function assertDims(\mysqli $db, $companyId, $accountCode, array $dims)
    {
        $acc = self::account($db, $companyId, $accountCode);
        if (!$acc) { throw new \RuntimeException('COA-404: حساب خارج الشجرة القانونية — ' . $accountCode); }
        if ((int) $acc['is_postable'] !== 1) {
            throw new \RuntimeException('COA-LEVEL-422: المستوى ' . $acc['acc_level']
                . ' تجميعي لا يقيد عليه — ' . $accountCode);
        }
        $need = array_filter(array_map('trim', explode(',', (string) $acc['required_dims'])));
        $missing = array();
        foreach ($need as $d) {
            $v = $dims[$d] ?? null;
            if ($v === null || $v === '' || $v === 0 || $v === '0') { $missing[] = $d . ' ' . (self::DIMS[$d] ?? ''); }
        }
        if ($missing) {
            throw new \RuntimeException('COA-DIM-422: القيد ينقصه بعد يلزم حسابه — ' . implode(' · ', $missing));
        }
        return $acc;
    }

    /**
     * حارسُ R8/R2 عند إنشاءِ حسابٍ جديد: لا حسابَ لواقعةٍ يميزها بُعدٌ،
     * ولا اسمَ شخصٍ في الشجرة.
     */
    public static function assertCreatable(\mysqli $db, $companyId, $code, $name)
    {
        foreach (self::PERSON_HINTS as $h) {
            if (mb_stripos($name, $h) !== false) {
                throw new \RuntimeException('COA-R2-422: لا اسم شخص في دليل الحسابات — '
                    . 'العهدة حساب واحد (1103) والشخص بعد D6');
            }
        }
        // R8: كودٌ بأربعِ خاناتٍ فأكثرَ تحت مستوًى ثالثٍ قائمٍ = تفصيلٌ يميّزه بُعد
        if (strlen(preg_replace('/\D/', '', $code)) > 4) {
            throw new \RuntimeException('COA-R8-422: لا حساب ينشأ لواقعة يمكن تمييزها ببعد — '
                . 'استعمل المستوى الثالث مع أبعاده');
        }
        return true;
    }

    /**
     * مصفوفةُ الترحيل: الحسابُ يُشتق من الإدارةِ ونوعِ الأثرِ ونموذجِ العملِ
     * ونوعِ العقد — ولا يُختار يدويًّا.
     *
     * @param string $ruleCode رمزُ صفِّ المصفوفة (OPS · SAL · SUP …)
     * @param string $side     'revenue' أو 'cost'
     * @return array{code:string,rule:array} الحسابُ المشتقُّ وصفُّه الحاكم
     */
    public static function resolveAccount(\mysqli $db, $companyId, $ruleCode, $side, array $ctx = array())
    {
        $st = $db->prepare("SELECT * FROM fin_posting_matrix
                             WHERE company_id = ? AND rule_code = ? AND active = 1
                             ORDER BY version_no DESC LIMIT 1");
        $st->bind_param('is', $companyId, $ruleCode);
        $st->execute();
        $rule = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$rule) { throw new \RuntimeException('COA-MATRIX-404: لا صف ترحيل للرمز ' . $ruleCode); }

        $field = $side === 'revenue' ? 'revenue_accounts' : 'cost_accounts';
        $codes = array_values(array_filter(array_map('trim',
            preg_split('/[,·\s]+/u', (string) $rule[$field]))));
        $codes = array_values(array_filter($codes, function ($c) { return preg_match('/^\d{2,4}$/', $c); }));
        if (!$codes) {
            throw new \RuntimeException('COA-MATRIX-422: الصف ' . $ruleCode . ' بلا حساب ' . $side);
        }

        // نموذجُ العملِ يحسم بين أكوادِ الإيرادِ الثلاثة (D7)
        $model = (string) ($ctx['business_model'] ?? '');
        $byModel = array('hour' => '4101', 'ton' => '4102', 'meter' => '4103');
        if ($side === 'revenue' && isset($byModel[$model]) && in_array($byModel[$model], $codes, true)) {
            return array('code' => $byModel[$model], 'rule' => $rule);
        }
        // نوعُ عقدِ الموظفِ يحسم بين أكوادِ تكلفةِ المشغّلين (D9)
        $ctype = (string) ($ctx['contract_type_code'] ?? '');
        if ($side === 'cost' && $ctype !== '') {
            $st = $db->prepare("SELECT accounts_csv FROM fin_contract_types
                                 WHERE company_id = ? AND type_code = ? AND active = 1 LIMIT 1");
            $st->bind_param('is', $companyId, $ctype);
            $st->execute();
            $ct = $st->get_result()->fetch_assoc();
            $st->close();
            if ($ct) {
                foreach (array_map('trim', explode(',', (string) $ct['accounts_csv'])) as $c) {
                    if ($c !== '' && in_array($c, $codes, true)) {
                        return array('code' => $c, 'rule' => $rule);
                    }
                }
            }
        }
        return array('code' => $codes[0], 'rule' => $rule);
    }

    /** رصيدُ حسابٍ (أو مجموعةِ أكوادٍ بالبادئة) لفترةٍ ونطاقِ أبعاد. */
    public static function balance(\mysqli $db, $companyId, $codes, $period = null, array $scope = array())
    {
        $codes = is_array($codes) ? $codes : array_filter(array_map('trim', explode(',', (string) $codes)));
        if (!$codes) { return null; }
        $like = array();
        foreach ($codes as $c) {
            $c = preg_replace('/\D/', '', $c);
            if ($c === '') { continue; }
            $like[] = "a.code LIKE '" . $db->real_escape_string($c) . "%'";
        }
        if (!$like) { return null; }
        $w = "l.company_id = " . (int) $companyId . " AND (" . implode(' OR ', $like) . ")";
        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $w .= " AND DATE_FORMAT(COALESCE(e.posting_date, e.created_at), '%Y-%m') = '"
                . $db->real_escape_string($period) . "'";
        }
        foreach (array('project_id' => 'l.project_id', 'equipment_id' => 'l.equipment_id',
                       'contract_id' => 'l.contract_id') as $k => $col) {
            if (!empty($scope[$k])) { $w .= " AND {$col} = " . (int) $scope[$k]; }
        }
        if (!empty($scope['business_model'])) {
            $w .= " AND l.business_model = '" . $db->real_escape_string($scope['business_model']) . "'";
        }
        $q = "SELECT COALESCE(SUM(l.debit),0) dr, COALESCE(SUM(l.credit),0) cr, COUNT(*) n
                FROM fin_journal_lines l
                JOIN fin_chart_of_accounts a ON a.id = l.account_id
                LEFT JOIN fin_journal_entries e ON e.id = l.entry_id
               WHERE {$w}";
        $r = $db->query($q);
        if (!$r) { return null; }
        $x = $r->fetch_assoc();
        $dr = (float) $x['dr']; $cr = (float) $x['cr'];
        $nature = self::natureOf($codes[0]);
        $bal = $nature === 'credit' ? ($cr - $dr) : ($dr - $cr);
        return array('debit' => $dr, 'credit' => $cr, 'balance' => round($bal, 2), 'n' => (int) $x['n']);
    }

    /** طبيعةُ الرصيدِ من الجذر: 1 و5 مدينان · 2 و3 و4 دائنة. */
    public static function natureOf($code)
    {
        $root = substr(preg_replace('/\D/', '', (string) $code), 0, 1);
        return in_array($root, array('2', '3', '4'), true) ? 'credit' : 'debit';
    }
}
