<?php
require_once __DIR__ . '/permissions_helper.php'; // FINAL_CLOSE ⑦ — المصدر الواحد
/**
 * includes/sec013.php — محلّل أبعاد الصلاحية الأربعة (SEC-013 · E-04)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ من طبقة القوالب (المصدر الجديد SEC-01) وتنقيحاتها الرباعية
 * (template_permission_dims): تركيبة الدور = عائلته + مستواه + مسماه
 * (عرف perm_templates_build) والنسخ published وحدها.
 * الحكم: deny يغلب · grant لازم · فعلٌ بلا بندٍ = رفض (fail-closed) —
 * والنطاق يُقارن بضِيقه: بند «موقع» لا يجيز طلبًا بنطاق «شركة».
 * الإنفاذ خلف EMS_SEC013 (off|monitor|enforce — الافتراض off حتى قلب
 * EMS_PERM_SOURCE يوم 2026-08-19): off لا يغير سلوكًا · monitor يسجل
 * WOULD_DENY · enforce يحجب — فالبناء مرة واحدة والقلب سطر.
 */

require_once __DIR__ . '/role_taxonomy.php';

if (!function_exists('ems_sec013_mode')) {
    function ems_sec013_mode()
    {
        $m = strtolower(trim((string) (function_exists('env') ? env('EMS_SEC013', 'off') : (getenv('EMS_SEC013') ?: 'off'))));
        return in_array($m, array('monitor', 'enforce'), true) ? $m : 'off';
    }
}

if (!function_exists('ems_sec013_role_versions')) {
    /** نسخ القوالب المنشورة لتركيبة الدور (عائلة + مستوى + مسمى) */
    function ems_sec013_role_versions(\mysqli $conn, $roleId)
    {
        static $cache = array();
        $roleId = intval($roleId);
        if (isset($cache[$roleId])) { return $cache[$roleId]; }
        $keys = array();
        $f = ems_role_family($roleId);
        if ($f) { $keys[] = $f; }
        $l = ems_role_level($roleId);
        if ($l) { $keys[] = $l; }
        $t = ems_role_title_key($roleId);
        if ($t) { $keys[] = $t; }
        if (!$keys) { return $cache[$roleId] = array(); }
        // FINAL_CLOSE ⑦: نسخ القوالب المنشورة من المصدر الواحد
        return $cache[$roleId] = perm_published_template_versions($conn, $keys);
    }
}

if (!function_exists('ems_sec013_allowed')) {
    /**
     * الحكم الرباعي: أيؤذن للدور بهذا الفعل على هذه الشاشة بهذا النطاق؟
     * @param string $screen     كود الشاشة (مثل Clients/clients.php)
     * @param string $actionCode من قاموس الستة عشر (sec_actions)
     * @param string $ctxScope   نطاق الطلب (من التسعة — الافتراض company)
     * @return array{allowed:bool, reason:string, cap:?float, doc_type:?string}
     */
    function ems_sec013_allowed(\mysqli $conn, $roleId, $screen, $actionCode, $ctxScope = 'company')
    {
        $out = array('allowed' => false, 'reason' => '', 'cap' => null, 'doc_type' => null);
        $vers = ems_sec013_role_versions($conn, $roleId);
        if (!$vers) { $out['reason'] = 'لا قوالب منشورة لتركيبة الدور'; return $out; }
        $in = implode(',', $vers);
        $st = $conn->prepare(
            "SELECT d.effect, d.scope_code, d.amount_cap, d.doc_type, s.narrowness
               FROM template_permissions tp
               JOIN template_permission_dims d ON d.tp_id = tp.tp_id AND d.action_code = ?
               JOIN sec_scopes s ON s.scope_code = d.scope_code
              WHERE tp.template_version_id IN ({$in})
                AND tp.permission_code LIKE CONCAT(?, ':%')");
        if (!$st) { $out['reason'] = 'خطأ إعداد'; return $out; }
        $st->bind_param('ss', $actionCode, $screen);
        $st->execute();
        $rs = $st->get_result();
        $ctxNarrow = 1;
        $r = mysqli_query($conn, "SELECT narrowness FROM sec_scopes WHERE scope_code = '"
            . $conn->real_escape_string($ctxScope) . "'");
        if ($r && ($x = mysqli_fetch_row($r))) { $ctxNarrow = intval($x[0]); }
        $granted = false;
        while ($row = $rs->fetch_assoc()) {
            // بند deny يغلب فورًا
            if ($row['effect'] === 'deny') {
                $st->close();
                $out['reason'] = 'بند منع صريح';
                return $out;
            }
            // بند grant يجيز إن كان نطاقه أوسع من نطاق الطلب أو مساويًا له
            // (بند «موقع» ضيّق لا يجيز طلبًا على مستوى «شركة»)
            if (intval($row['narrowness']) <= $ctxNarrow) {
                $granted = true;
                if ($row['amount_cap'] !== null) { $out['cap'] = floatval($row['amount_cap']); }
                if ($row['doc_type'] !== null) { $out['doc_type'] = (string) $row['doc_type']; }
            }
        }
        $st->close();
        $out['allowed'] = $granted;
        $out['reason'] = $granted ? 'بند منح نافذ بنطاقه' : 'لا بند يجيز هذا الفعل بهذا النطاق (fail-closed)';
        return $out;
    }
}

if (!function_exists('ems_sec013_gate')) {
    /**
     * بوابة الإنفاذ خلف العلم: off تمرّر · monitor تسجل WOULD_DENY وتمرّر ·
     * enforce ترفض. تُستدعى من الحراس بعد القلب — البناء اليوم والقلب سطر.
     */
    function ems_sec013_gate(\mysqli $conn, $roleId, $screen, $actionCode, $ctxScope = 'company')
    {
        $mode = ems_sec013_mode();
        if ($mode === 'off') { return array('ok' => true, 'mode' => 'off'); }
        $v = ems_sec013_allowed($conn, $roleId, $screen, $actionCode, $ctxScope);
        if ($v['allowed']) { return array('ok' => true, 'mode' => $mode); }
        if ($mode === 'monitor') {
            error_log("[SEC013][WOULD_DENY] role={$roleId} {$screen} {$actionCode}@{$ctxScope} — " . $v['reason']);
            return array('ok' => true, 'mode' => 'monitor', 'would_deny' => true);
        }
        return array('ok' => false, 'mode' => 'enforce', 'reason' => $v['reason']);
    }
}
