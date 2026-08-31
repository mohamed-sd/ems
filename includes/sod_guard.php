<?php
require_once __DIR__ . '/permissions_helper.php'; // FINAL_CLOSE ⑦ — المصدر الواحد
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-827
/**
 * حارس فصل الواجبات عند المنح — includes/sod_guard.php (E-04 RB-04 · دَينُ «الوصل»)
 * ───────────────────────────────────────────────────────────────────────────
 * كان الفصلُ مسحًا بعديًّا (sod_sweep) — وهذا وصلُه بمسار المنح نفسِه:
 * قبل حفظِ صلاحيةٍ لدورٍ تُحاكى الحالةُ بعد المنح، فإن اكتمل بها زوجٌ محظور:
 *   - زوجٌ **دقيقُ** الدرجة (exact) ⇒ **منعٌ بنيويٌّ** يسمّي الزوجَ وضابطَه
 *     التعويضي («مسحٌ يكشف ثم منع» — قرار المالك 2026-08-06: الدقيقُ بلغ المنع).
 *   - زوجٌ **تقريبيٌّ** (approx) ⇒ يمضي المنحُ **ويُبلَّغ** ويُسجَّل
 *     (SOD_GRANT_WARN) — لا يُمنع حتى تدقَّ الأبعادُ (DEC-SEC-K).
 * المصدرُ الواحد: sod_conflicts + خريطة sod_map — لا منطقَ ثانيًا هنا.
 */

require_once __DIR__ . '/sod_map.php';

if (!function_exists('ems_sod_codes_of_role_with')) {
    /** رموزُ الدور **بعد** تطبيق منحٍ مقترحٍ على موديولٍ بعينه (محاكاةٌ لا كتابة). */
    function ems_sod_codes_of_role_with(mysqli $conn, $roleId, $moduleId, array $flags)
    {
        $roleId = (int) $roleId;
        $moduleId = (int) $moduleId;
        // مسار الموديول المقترح
        $code = null;
        $r = mysqli_query($conn, "SELECT code FROM modules WHERE id = {$moduleId} LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) { $code = ltrim(str_replace('\\', '/', (string) $x['code']), '/'); }

        $held = array();
        foreach (ems_sod_map() as $sodCode => $t) {
            if ($t['grade'] === 'absent' || $t['screen'] === null) { continue; }
            $flag = ems_sod_flag_of($t['action']);
            if ($flag === null) { continue; }
            // الموديول محل المنح: القيمة من المقترح لا من القاعدة
            if ($code !== null && ltrim($t['screen'], '/') === $code) {
                if (!empty($flags[$flag])) { $held[$sodCode] = 1; }
                continue;
            }
            // FINAL_CLOSE ⑦: القراءة من المصدر الواحد لا باستعلام خاص
            if (perm_flag_for_screen($conn, $roleId, $t['screen'], $flag) === 1) { $held[$sodCode] = 1; }
        }
        return $held;
    }
}

if (!function_exists('ems_sod_check_grant')) {
    /**
     * فحصُ منحٍ مقترح. @return array{block:?array, warnings:array}
     *   block   = الزوج الدقيق المكتمل (يمنع الحفظ) أو null
     *   warnings= أزواجٌ تقريبيةٌ تكتمل (تمضي وتُبلَّغ)
     */
    function ems_sod_check_grant(mysqli $conn, $roleId, $moduleId, array $flags)
    {
        $out = array('block' => null, 'warnings' => array());
        // منحٌ كلُّه أصفار (سحبٌ) لا يكمل زوجًا — يمضي
        if (empty($flags['can_add']) && empty($flags['can_edit']) && empty($flags['can_delete']) && empty($flags['can_view'])) {
            return $out;
        }
        $held = ems_sod_codes_of_role_with($conn, $roleId, $moduleId, $flags);
        $r = mysqli_query($conn, "SELECT sod_id, conflict_code, name_ar, permission_a, permission_b,
                                         severity, compensating_control
                                    FROM sod_conflicts WHERE active = 1 ORDER BY sod_id");
        while ($r && ($p = mysqli_fetch_assoc($r))) {
            $A = ems_sod_split_codes($p['permission_a']);
            $B = ems_sod_split_codes($p['permission_b']);
            $grade = ems_sod_pair_grade(array_merge($A, $B));
            if ($grade === 'absent') { continue; }
            $hasA = true; foreach ($A as $c) { if (empty($held[$c])) { $hasA = false; break; } }
            if (!$hasA) { continue; }
            $hasB = true; foreach ($B as $c) { if (empty($held[$c])) { $hasB = false; break; } }
            if (!$hasB) { continue; }
            $hit = array('sod_id' => (int) $p['sod_id'], 'code' => $p['conflict_code'],
                         'name' => $p['name_ar'], 'severity' => $p['severity'],
                         'control' => $p['compensating_control'], 'grade' => $grade);
            if ($grade === 'exact') { $out['block'] = $hit; return $out; }
            $out['warnings'][] = $hit;
        }
        return $out;
    }
}
