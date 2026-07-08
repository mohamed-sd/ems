<?php
/**
 * جسر المنصب — ADR-07 · K6 (خطة المرحلة 1 §2)
 * ───────────────────────────────────────────────────────────────────────────
 * «الصلاحية على المنصب» طبقةً فوق نظام الأدوار القائم — **جسرٌ لا استبدال**:
 *   • users.role يبقى مصدر السلوك الحالي لكل الشاشات القائمة كما هو.
 *   • المنصب يمنح دور نظامٍ (positions.role_id → roles) — فالصلاحيات القائمة
 *     (role_permissions/modules) تعمل بلا أي تغيير حين يتبنّى مسارٌ الجسرَ.
 *   • users.position_id = NULL ⇒ fallback كامل إلى users.role (السلوك الحالي).
 *
 * ── عقد الاستهلاك التدريجي (لا إجبار للشاشات القائمة) ──────────────────────
 *  المرحلة الآن (K6): الجسر خاملٌ — لا مستهلك حيًّا؛ الإثبات عبر حزمة الاختبار.
 *  التبنّي اللاحق يجري في الحُرّاس المركزية لا الشاشات:
 *   1) حارس الفعل (ADR-06) والحُرّاس المركزية يستبدلون قراءة الدور بـ
 *      ems_user_effective_role() خلف علَم EMS_POSITION_BRIDGE
 *      (off افتراضًا → monitor يقارن نتيجة الجسر بالدور المباشر ويسجّل أي فرقٍ
 *      position_role_mismatch → enforce) — نمط أعلام العائلة نفسه.
 *   2) شاشات إدارة المناصب (الدور 15) تُبنى ضمن دفعات K9 عبر بوابة العزل.
 *   3) الشاشات القائمة لا تُعدَّل إطلاقًا لهذا الغرض — تكسب المنصب تلقائيًا
 *      يوم يمرّ فحصُها بالحارس المركزي، وحتى ذلك الحين users.role هو الفاعل.
 *
 * ملاحظة تمييز: «المنصب» وصوليٌّ تنظيمي (يمنح دور نظام) — يختلف عن
 * job_titles (مسمّيات HR) وemployee_roles (أدوار موظفين HR)؛ الربط بهما
 * اختياري عبر positions.job_title_id. وusers.role_id القديم شبه الفارغ
 * أثريٌّ لا يقرؤه الجسر ولا يكتبه.
 */

if (!function_exists('ems_user_effective_role')) {

    /**
     * الدور الفعّال للمستخدم: دور منصبه إن وُجد منصبٌ صالح، وإلا users.role.
     * لا يغيّر أي سلوكٍ قائم — دالة قراءةٍ صرفة يستهلكها الحُرّاس لاحقًا.
     *
     * الصلاحية «الصالحة» للمنصب: صفٌّ قائم غير محذوفٍ ناعمًا، مفعَّل،
     * ومن **نفس شركة المستخدم** (منصب شركةٍ أخرى = تجاهل + قيد أمني).
     *
     * @return array{role:string, source:string, position_id:?int, position_name:?string}
     *         source: 'position' | 'role' (fallback) | 'role_x_company' (منصب مرفوض عبر-الشركات)
     */
    function ems_user_effective_role(\mysqli $conn, $userId)
    {
        $userId = intval($userId);
        $sql = "SELECT u.role AS user_role, u.company_id AS user_company, u.position_id,
                       p.role_id AS pos_role, p.name AS pos_name, p.company_id AS pos_company,
                       p.is_active, COALESCE(p.is_deleted, 0) AS pos_deleted
                FROM users u
                LEFT JOIN positions p ON p.id = u.position_id
                WHERE u.id = {$userId} LIMIT 1";
        $res = $conn->query($sql);
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) {
            return array('role' => '', 'source' => 'role', 'position_id' => null, 'position_name' => null);
        }

        $fallback = array(
            'role' => strval($row['user_role']),
            'source' => 'role',
            'position_id' => null,
            'position_name' => null,
        );

        if ($row['position_id'] === null || $row['pos_role'] === null) {
            return $fallback; // لا منصب (أو منصب محذوف صفًّا) — السلوك القائم
        }
        if (intval($row['pos_deleted']) === 1 || intval($row['is_active']) !== 1) {
            return $fallback; // منصب معطّل/مؤرشف — fallback صامت
        }
        if (intval($row['pos_company']) !== intval($row['user_company'])) {
            // عزل الشركات: منصبٌ من شركةٍ أخرى لا يمنح شيئًا — ويُرصَد
            if (function_exists('log_security_event')) {
                log_security_event('position_cross_company_refused',
                    'user=' . $userId . ' position=' . intval($row['position_id'])
                    . ' pos_company=' . intval($row['pos_company']) . ' user_company=' . intval($row['user_company']));
            }
            $fallback['source'] = 'role_x_company';
            return $fallback;
        }

        return array(
            'role' => strval($row['pos_role']), // نصًّا — كنمط الجلسة والمقارنات الصارمة القائمة
            'source' => 'position',
            'position_id' => intval($row['position_id']),
            'position_name' => strval($row['pos_name']),
        );
    }
}
