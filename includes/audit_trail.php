<?php
/**
 * سجلُّ التدقيق بقيم قبل/بعد — N-02 (2026-07-30)
 * ───────────────────────────────────────────────────────────────────────────
 * «من غيّر ماذا ومتى بقيمٍ قبل/بعد» — PLAN-01 §4.
 *
 * البنيةُ قائمةٌ عريضًا (activity_logs: 24 عمودًا فيها old_value/new_value)
 * لكن **صفرَ صفٍّ يحمل قيمةَ "قبل"** (مقيس 2026-07-30): الوسيطُ HTTP يسجّل
 * الطلبَ لا التغيير. هذا الموصلُ يسدّ ذلك في مواضع الخنق الحساسة:
 * العقود · التسويات · القيود · الصلاحيات — ويصلح لأي كاتبٍ جديد.
 *
 * القواعد:
 *   • يُسجَّل **المتغيّرُ فقط** (diff) لا الصفُّ كاملًا — فالسجل يُقرأ لا يُنقَّب.
 *   • لا يرمي أبدًا: فشلُ التدقيق يُسجَّل في error_log ولا يقطع العملية —
 *     لكن غيابَه يظهر في اختبار N-02 فلا يبقى صامتًا.
 *   • CLI-safe: السياقُ من الوسائط أولًا ثم الجلسة إن حضرت.
 */

if (!function_exists('ems_audit_change')) {
    /**
     * تسجيلُ تغييرٍ بقيم قبل/بعد.
     *
     * @param \mysqli $conn
     * @param string  $module   مجالُ التدقيق (contracts · settlements · journal · permissions …)
     * @param string  $screen   الشاشة/الجدول الذي وقع فيه التغيير
     * @param string  $action   الفعل (state_transition · post · update · grant …)
     * @param int     $recordId معرّفُ الصف المتغيّر
     * @param array   $old      القيمُ قبل (خريطة حقل => قيمة)
     * @param array   $new      القيمُ بعد
     * @param array   $ctx      اختياري: company_id · user_id · contract_id · note
     * @return bool
     */
    function ems_audit_change(\mysqli $conn, $module, $screen, $action, $recordId, array $old, array $new, array $ctx = array())
    {
        try {
            // diff: المتغيّرُ فقط — والمفاتيحُ الجديدة كلُّها تغييرٌ من عدم
            $changedOld = array();
            $changedNew = array();
            foreach ($new as $k => $v) {
                $ov = array_key_exists($k, $old) ? $old[$k] : null;
                if ((string) $ov !== (string) $v) {
                    $changedOld[$k] = $ov;
                    $changedNew[$k] = $v;
                }
            }
            // حقولٌ زالت (كانت ولم تعد)
            foreach ($old as $k => $v) {
                if (!array_key_exists($k, $new) && $v !== null && $v !== '') {
                    $changedOld[$k] = $v;
                    $changedNew[$k] = null;
                }
            }
            if (empty($changedOld) && empty($changedNew)) {
                return true; // لا تغييرَ = لا صفَّ ضوضاء
            }

            $sess = (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : array();
            $companyId = isset($ctx['company_id']) ? intval($ctx['company_id'])
                       : (isset($sess['company_id']) ? intval($sess['company_id']) : 0);
            $userId    = isset($ctx['user_id']) ? intval($ctx['user_id'])
                       : (isset($sess['id']) ? intval($sess['id']) : 0);
            $roleId    = isset($sess['role']) ? intval($sess['role']) : null;
            $contract  = isset($ctx['contract_id']) ? intval($ctx['contract_id']) : null;
            if (!empty($ctx['note'])) { $changedNew['_note'] = (string) $ctx['note']; }

            $st = $conn->prepare(
                "INSERT INTO activity_logs
                    (company_id, contract_id, user_id, role_id, ip_address,
                     screen_name, module_name, action_type, field_name, record_id,
                     old_value, new_value, http_method, url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$st) { error_log('[audit-trail] prepare: ' . $conn->error); return false; }
            $ip     = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli';
            $fields = mb_substr(implode(',', array_keys($changedNew)), 0, 255);
            $oldJ   = json_encode($changedOld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newJ   = json_encode($changedNew, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $method = (PHP_SAPI === 'cli') ? 'CLI' : (isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '');
            $url    = isset($_SERVER['REQUEST_URI']) ? mb_substr((string) $_SERVER['REQUEST_URI'], 0, 500) : '';
            $rid    = intval($recordId);
            $st->bind_param('iiiisssssissss',
                $companyId, $contract, $userId, $roleId, $ip,
                $screen, $module, $action, $fields, $rid,
                $oldJ, $newJ, $method, $url);
            if (!$st->execute()) {
                error_log('[audit-trail] insert: ' . $st->error);
                $st->close();
                return false;
            }
            $st->close();
            return true;
        } catch (\Throwable $t) {
            error_log('[audit-trail] ' . $t->getMessage());
            return false;
        }
    }
}
