<?php
/**
 * PermissionExplainService — «لماذا يملك فلان هذه الصلاحية؟» (SEC-01 §12 خدمة ⑧ · SEC-16)
 * ───────────────────────────────────────────────────────────────────────────
 * يعيد كل مصدر بحكمه (منح أو منع) والنطاق والسقف والمدة والنتيجة النهائية
 * بعد قواعد الدمج (§4.2) — بصيغة مثال الوثيقة حرفيًّا.
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';

class PermissionExplainService
{
    /**
     * @return array بصيغة GET /permissions/explain
     */
    public static function explain(\mysqli $conn, $personId, $companyId, $permissionCode, $scope)
    {
        $parts = explode(':', (string) $scope, 2);
        $scopeType = $parts[0] !== '' ? $parts[0] : null;
        $scopeId = isset($parts[1]) ? intval($parts[1]) : null;

        $r = PermissionResolver::resolve($conn, $personId, $companyId, $permissionCode, $scopeType, $scopeId);
        if ($r['code'] === 422) {
            return array('allowed' => false, 'reason' => 'no_scope', 'note' => $r['reason']);
        }

        if (!$r['allowed']) {
            $src = $r['denies'] ? $r['denies'][0] : null;
            return array(
                'allowed' => false,
                'reason' => $r['denies'] ? 'deny' : 'no_source',
                'source' => $src ? array(
                    'kind' => $src['source_kind'],
                    'ref' => $src['source_ref'],
                    'note' => isset($src['note']) ? $src['note'] : 'المنع يغلب المنح دائما',
                ) : null,
                'note' => $r['reason'],
            );
        }

        $sources = array();
        $expiryNote = null;
        foreach ($r['sources'] as $s) {
            $row = array('kind' => $s['source_kind'], 'ref' => $s['source_ref']);
            if (!empty($s['scope_rule'])) { $row['scope'] = $s['scope_rule']; }
            // مدة المصدر إن كانت له (تكليف أو استثناء) — «تسقط بانتهاء المصدر»
            if ($s['source_kind'] === 'assignment' && preg_match('/^ASG-(\d+)$/', $s['source_ref'], $m)) {
                $res = $conn->query('SELECT valid_to FROM org_assignments WHERE asg_id = ' . intval($m[1]));
                $x = $res ? $res->fetch_assoc() : null;
                if ($x) { $row['expires'] = $x['valid_to']; $expiryNote = 'تسقط بانتهاء ' . $s['source_ref']; }
            }
            if ($s['source_kind'] === 'exception' && preg_match('/^EX-(\d+)$/', $s['source_ref'], $m)) {
                $res = $conn->query('SELECT valid_to FROM permission_exceptions WHERE ex_id = ' . intval($m[1]));
                $x = $res ? $res->fetch_assoc() : null;
                if ($x) { $row['expires'] = $x['valid_to']; $expiryNote = 'تسقط بانتهاء ' . $s['source_ref']; }
            }
            $sources[] = $row;
        }

        $out = array(
            'allowed' => true,
            'sources' => $sources,
            'denies' => array(),
            'scope' => $r['scope'],
            'amount_cap' => $r['amount_cap'],
        );
        if ($expiryNote !== null) { $out['note'] = $expiryNote; }
        return $out;
    }
}
