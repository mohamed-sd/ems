<?php
/**
 * includes/perm_explain_live.php — «لماذا أرى هذا؟ ولماذا لا أراه؟» فوق المصدر الحي
 * ───────────────────────────────────────────────────────────────────────────
 * E-04 SEC-001-د: «من سأل «لماذا يملك فلانٌ هذه الصلاحية؟» وجد جوابًا **مركَّبًا
 * من هذه العناصر** لا «مُنحت له»» · وSEC-031-ي: «يُعرض كلُّ مصدرٍ بحكمه
 * والنتيجةُ النهائية».
 *
 * ⚠️ لماذا مفسِّرٌ ثانٍ؟ `PermissionExplainService` يفسّر طبقةَ SEC-01
 * (`effective_permissions` = 3 صفوف · القوالبُ بلا بنود) وهي **ليست** التي
 * تمنع اليوم. الإنفاذُ الحيُّ على `modules` × `role_permissions` (1120 صفًّا)،
 * فالجوابُ الصادق يُحسب من حيث يقع المنعُ فعلًا. يُستبدَل هذا الملفُّ بالخدمة
 * الكاملة متى امتلأت SEC-01 (محجوبٌ بـDEC-SEC-K) — قرار المالك 2026-08-06.
 */

if (!function_exists('ems_explain_screen_access')) {
    /**
     * @return array{allowed:bool, reason:string, chain:array<int,array{step:string,verdict:string}>,
     *               grantor:?string, module_id:?int}
     */
    function ems_explain_screen_access(mysqli $conn, $roleId, $screenCode, $isSuper = false)
    {
        $roleId = (int) $roleId;
        $chain = array();

        if ($isSuper) {
            return array('allowed' => true, 'reason' => 'المدير الأعلى خارج الترشيح',
                'chain' => array(array('step' => 'الصفة', 'verdict' => 'مديرٌ أعلى — يمرّ')),
                'grantor' => null, 'module_id' => null);
        }

        // ① أالشاشةُ مسجَّلةٌ أصلًا؟ (غيرُ المسجَّلة تُرفض منذ 2026-08-05)
        $e = mysqli_real_escape_string($conn, (string) $screenCode);
        $mod = null;
        $r = mysqli_query($conn, "SELECT id, name, owner_role_id FROM modules WHERE code='{$e}' LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) { $mod = $x; }
        if (!$mod) {
            $chain[] = array('step' => 'تسجيلُ الشاشة', 'verdict' => '✘ غيرُ مسجَّلةٍ في سجل الشاشات');
            return array('allowed' => false,
                'reason' => 'الشاشةُ غيرُ مسجَّلة — فلا تُمنح صلاحيتُها لأحد. تسجيلُها من إدارة الصلاحيات.',
                'chain' => $chain, 'grantor' => 'إدارة الصلاحيات (الدور 15)', 'module_id' => null);
        }
        $chain[] = array('step' => 'تسجيلُ الشاشة', 'verdict' => '✔ مسجَّلةٌ باسم «' . $mod['name'] . '»');

        // ② أللدور صفُّ صلاحيةٍ عليها؟
        $mid = (int) $mod['id'];
        $p = null;
        $r = mysqli_query($conn, "SELECT can_view, can_add, can_edit, can_delete
                                    FROM role_permissions WHERE role_id={$roleId} AND module_id={$mid} LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) { $p = $x; }
        $roleName = '';
        $r = mysqli_query($conn, "SELECT name FROM roles WHERE id={$roleId} LIMIT 1");
        if ($r && ($x = mysqli_fetch_assoc($r))) { $roleName = $x['name']; }

        if (!$p) {
            $chain[] = array('step' => 'صلاحيةُ الدور', 'verdict' => '✘ لا صفَّ صلاحيةٍ للدور «' . $roleName . '»');
            $own = ((int) $mod['owner_role_id'] === $roleId) ? ' — مع أن هذا الدورَ هو المالكُ المعلَن للشاشة' : '';
            return array('allowed' => false,
                'reason' => 'دورُك لا يملك صلاحيةً على هذه الشاشة' . $own . '.',
                'chain' => $chain, 'grantor' => 'إدارة الصلاحيات (الدور 15)', 'module_id' => $mid);
        }
        if (empty($p['can_view'])) {
            $chain[] = array('step' => 'صلاحيةُ الدور', 'verdict' => '✘ الصفُّ موجودٌ و«العرض» مطفأ');
            return array('allowed' => false,
                'reason' => 'صلاحيةُ العرض مطفأةٌ لدورك على هذه الشاشة.',
                'chain' => $chain, 'grantor' => 'إدارة الصلاحيات (الدور 15)', 'module_id' => $mid);
        }
        $acts = array();
        foreach (array('can_add' => 'إضافة', 'can_edit' => 'تعديل', 'can_delete' => 'حذف') as $k => $lbl) {
            if (!empty($p[$k])) { $acts[] = $lbl; }
        }
        $chain[] = array('step' => 'صلاحيةُ الدور',
            'verdict' => '✔ عرضٌ مسموح' . ($acts ? ' · و' . implode(' و', $acts) : ' (قراءةً فقط)'));

        // ③ أالرابطُ في القائمة مربوطٌ بها؟ (فالظهورُ في السايدبار تابعٌ للربط)
        $r = mysqli_query($conn, "SELECT COUNT(*) n FROM nav_items
                                   WHERE role_id={$roleId} AND module_id={$mid} AND active=1");
        $navN = ($r && ($x = mysqli_fetch_assoc($r))) ? (int) $x['n'] : 0;
        $chain[] = array('step' => 'رابطُ القائمة',
            'verdict' => $navN > 0 ? "✔ {$navN} رابطًا في قائمة دورك" : '— لا رابطَ في القائمة (تُفتح بمسارها)');

        return array('allowed' => true, 'reason' => 'مسموح', 'chain' => $chain,
            'grantor' => 'إدارة الصلاحيات (الدور 15)', 'module_id' => $mid);
    }
}

if (!function_exists('ems_deny_message')) {
    /** رسالةُ رفضٍ تحمل سببَها ومن يملك منحَها — لا «لا صلاحية» مجردة. */
    function ems_deny_message(mysqli $conn, $roleId, $screenCode)
    {
        $x = ems_explain_screen_access($conn, $roleId, $screenCode, false);
        $msg = '❌ ' . $x['reason'];
        if (!empty($x['grantor'])) { $msg .= ' — المنحُ من ' . $x['grantor'] . '.'; }
        return $msg;
    }
}
