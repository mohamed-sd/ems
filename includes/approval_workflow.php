<?php
/**
 * [قناة محرّك الاعتمادات المحكومة — دفعة ز · 2026-07-16]
 * جداول approval_requests/approval_steps/approval_workflow_rules مصنّفة T_RESTRICTED في
 * سجل البوابة: بوابة المستأجر ترفضها للشاشات، وهذا المحرّك هو قناتها الوحيدة (سابقة
 * ناقل الأحداث ems_* ومحرّك الصيانة). المنفّذ العام approval_execute_payload يطبّق
 * حمولاتٍ أنشأتها الشاشات بعد فحص ملكيةٍ عبر البوابة وقت الإنشاء، ولا يُنفَّذ إلا
 * باكتمال سلسلة موافقات الأدوار — فالوصول الخام هنا داخل القناة لا خرقًا لها.
 */
if (!defined('APPROVAL_WORKFLOW_INCLUDED')) {
    define('APPROVAL_WORKFLOW_INCLUDED', true);
}

if (!function_exists('approval_now')) {
    function approval_now() {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('approval_response')) {
    function approval_response($success, $message, $extra = []) {
        return array_merge([
            'success' => (bool)$success,
            'message' => $message
        ], $extra);
    }
}

if (!function_exists('approval_get_user_id')) {
    function approval_get_user_id() {
        return isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    }
}

if (!function_exists('approval_get_user_role')) {
    function approval_get_user_role() {
        return isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    }
}

if (!function_exists('approval_user_can_match_role')) {
    function approval_user_can_match_role($role_required, $user_role) {
        $roles = array_map('trim', explode(',', strval($role_required)));
        $roles = array_filter($roles, function($r) { return $r !== ''; });

        if ($user_role === EMS_ROLE_SUPER_ADMIN) {
            return true;
        }

        return in_array(strval($user_role), $roles, true);
    }
}

if (!function_exists('approval_db_begin')) {
    function approval_db_begin($conn) {
        if (function_exists('mysqli_begin_transaction')) {
            return mysqli_begin_transaction($conn);
        }
        return mysqli_query($conn, 'START TRANSACTION');
    }
}

if (!function_exists('approval_db_commit')) {
    function approval_db_commit($conn) {
        return mysqli_commit($conn);
    }
}

if (!function_exists('approval_db_rollback')) {
    function approval_db_rollback($conn) {
        return mysqli_rollback($conn);
    }
}

if (!function_exists('approval_valid_identifier')) {
    function approval_valid_identifier($value) {
        return preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1;
    }
}

if (!function_exists('approval_bind_types_from_values')) {
    function approval_bind_types_from_values($values) {
        $types = '';
        foreach ($values as $v) {
            if (is_int($v)) {
                $types .= 'i';
            } elseif (is_float($v)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}

if (!function_exists('approval_stmt_execute')) {
    function approval_stmt_execute($conn, $sql, $values = []) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        if (!empty($values)) {
            $types = approval_bind_types_from_values($values);
            $refs = [];
            $bindParams = [];
            $bindParams[] = $types;

            foreach ($values as $key => $val) {
                $refs[$key] = $val;
                $bindParams[] = &$refs[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        return $stmt;
    }
}

if (!function_exists('approval_get_workflow_rules')) {
    function approval_get_workflow_rules($entity_type, $action, $conn) {
        $entity_type = mysqli_real_escape_string($conn, $entity_type);
        $action = mysqli_real_escape_string($conn, $action);

        /* ══ مصدرُ قاعدةِ الاعتمادِ الوحيد: سلاليمُ الحوكمة (ج · 2026-08-19) ══
           كان المحرّكُ يقرأ `approval_workflow_rules` — جدولًا **بلا حاكم**،
           بينما `gov_ladders` وثيقةٌ محكومةٌ **بلا منفِّذ**. فما في الوثيقةِ لا
           يُنفَّذ وما يُنفَّذ لا تحكمه وثيقة.
           ◆ والآن يقرأ `v_approval_rules_effective` المشتقَّ من السلاليمِ
             وخطواتِها، بدورٍ مجسورٍ من `gov_ladder_actor_roles` (موضعُ سلطةٍ
             لا اسمُ شخص). و`may_approve` تميّز خطوةَ **الاعتماد** من خطوةِ
             الإدخالِ والمراجعة — فيُفرض الترتيبُ واليدُ كما في الوثيقة.
           ◆ والمصدرُ القديمُ عُطِّل وقُيِّد بـCHECK فلا يُحيا صامتًا.
           ◆ وارتدادٌ **معلَنٌ لا صامت**: سلّمٌ غيرُ معرَّفٍ يُرجع صفرَ قواعدَ
             فيرفع المحرّكُ استثناءَه المنصوصَ («لا توجد مراحلُ موافقة») —
             ولا يسقط لمصدرٍ ثانٍ. */
        $sql = "SELECT role_required, step_order, may_approve, actor_code, ladder_code
                FROM v_approval_rules_effective
                WHERE entity_type = '$entity_type'
                  AND is_active = 1
                  AND role_required <> -99
                ORDER BY step_order ASC";

        $result = mysqli_query($conn, $sql);
        $rules = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rules[] = [
                    'role_required' => $row['role_required'],
                    'step_order' => intval($row['step_order'])
                ];
            }
        }

        if (!empty($rules)) {
            return $rules;
        }

        /* ═══════════════════════════════════════════════════════════════════
         * INJ/WF — «سلّمٌ بلا قاعدةٍ مسجَّلةٍ سلّمٌ مخترَع»
         * ═══════════════════════════════════════════════════════════════════
         * ◆ العطلُ المقيس: `approval_workflow_rules` فيه 23 صفًّا نشطًا
         *   **عشرون منها نصوصُ ملاحظاتِ UAT** حُشرت في خاناتِ
         *   `entity_type`/`action`/`role_required`؛ فلا قاعدةَ حقيقيةً تُطابِق
         *   أيَّ نوعِ كيانٍ حيّ. والأنواعُ الجاريةُ فعلًا (`timesheet:approve`
         *   بـ12 طلبًا · `contract:renewal` · `project:update`) **كلُّها تسقط
         *   هنا** فتصير سلّمًا من **خطوةٍ واحدة**؛ ومنشئُ الطلبِ يُعتمَد له
         *   تلقائيًّا إن طابق دورَها ⇒ **اعتمادُ ذاتٍ في نقرةٍ واحدة**.
         * ◆ ولا يُقلَب هذا فجأةً: ثمانيةُ مساراتٍ حيةٍ تتوقف. فيُحكَم بعلمٍ
         *   على نمطِ البيتِ (`off` · `monitor` · `enforce`) كما في
         *   `EMS_ORG_AUTHORITY` و`EMS_U13_*`:
         *     off      — الاحتياطُ كما كان بلا أثر.
         *     monitor  — الاحتياطُ يعمل **وكلُّ استعمالٍ له يُسجَّل** في
         *                `guard_denials` فيصير الدَّينُ مقيسًا لا مُخمَّنًا. (الافتراض)
         *     enforce  — لا سلّمَ بلا قاعدةٍ مسجَّلة: تُرجع الدالةُ فراغًا،
         *                و`approval_create_request` يرفض («لا توجد مراحل
         *                موافقة معرفة») — fail-closed.
         * ◆ والقلبُ إلى enforce **قرارُ مالكٍ** يسبقه تسجيلُ قواعدَ للأزواجِ
         *   الثمانيةِ: كم يدًا لكلٍّ؟ وهو سؤالُ سياسةٍ لا سؤالُ كود.
         * ═══════════════════════════════════════════════════════════════════ */
        $mode = function_exists('ems_env') ? strtolower((string) ems_env('EMS_APPROVAL_RULES', 'monitor')) : 'monitor';
        $lookup_key = trim($entity_type) . ':' . trim($action);

        $fallback_map = [
            'equipment:deactivate_equipment' => '4,-1',
            'equipment:reactivate_equipment' => '4,-1',
            'driver:activate_driver' => '3,-1',
            'driver:deactivate_driver' => '3,-1',
            'driver:reactivate_driver' => '3,-1'
        ];
        $named = isset($fallback_map[$lookup_key]);

        if ($mode === 'enforce') {
            approval_record_rule_gap($conn, $lookup_key, 'enforce_denied');
            return [];   // المُنادي يرفض — ولا يُخترع سلّم
        }
        if ($mode !== 'off') {
            approval_record_rule_gap($conn, $lookup_key, $named ? 'fallback_named' : 'fallback_default');
        }

        if ($named) {
            return [
                ['role_required' => $fallback_map[$lookup_key], 'step_order' => 1]
            ];
        }

        return [
            ['role_required' => EMS_ROLE_SUPER_ADMIN, 'step_order' => 1]
        ];
    }
}

if (!function_exists('approval_record_rule_gap')) {
    /**
     * تسجيلُ «سلّمٌ بلا قاعدة» في `guard_denials` — ليُقاس الدَّينُ لا يُوصَف.
     * ◆ لا يُوقف المسارَ ولا يُبتلع: تعذّرُ التسجيلِ يُكتب في سجلِّ الأخطاء
     *   (السجلُّ تابعٌ لا شرط — CS-12).
     */
    function approval_record_rule_gap($conn, $lookupKey, $reason) {
        require_once __DIR__ . '/catch_log.php';   // قناةُ إعلانِ التجاهلِ المقصود (CS-12)
        $prev = mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $stmt = mysqli_prepare($conn,
                /* FR-GOV-004 — الفعلُ عمودٌ لا استنتاج: القيدُ يردُّ الفارغَ. */
                'INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code, verb, at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())');
            if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
            $co     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
            $guard  = 'approval_rules_missing';
            $person = approval_get_user_id();
            $ref    = mb_substr((string) $lookupKey, 0, 120);
            $rc     = (string) $reason;
            /* FR-GOV-004 — الفعلُ الذي رُفض هنا **اعتمادٌ**: الحارسُ يمنع سلّمَ
               اعتمادٍ بلا قاعدة. ويُمرَّر عمودًا لا يُستنتَج من نصِّ السبب. */
            $verb   = 'approve';
            mysqli_stmt_bind_param($stmt, 'isisss', $co, $guard, $person, $ref, $rc, $verb);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (\Throwable $e) {
            /* ◆ انحدارٌ أحدثتُه أمسِ وكشفته بوابةُ AC-F10: كان هذا الجسمُ
                 `error_log` وحدَه — و«كتلةُ استثناءٍ مبتلَعة» عيبٌ محكومٌ في هذا
                 النطاق. والتجاهلُ **مقصودٌ** هنا (السجلُّ تابعٌ لا شرط: تعذّرُ
                 تسجيلِ الدَّينِ لا يجوز أن يمنع سلّمَ اعتماد)، فيُعلَن بالقناةِ
                 المسجَّلةِ لذلك لا بتعليقٍ حرّ. */
            ems_catch_ignored($e, __FUNCTION__,
                'CS-12: تسجيلُ دَينِ «سلّمٌ بلا قاعدة» تابعٌ لا شرط — لا يُوقف مسارَ الاعتماد.');
        }
        mysqli_report($prev);
    }
}

if (!function_exists('approval_get_request_by_id')) {
    function approval_get_request_by_id($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT * FROM approval_requests WHERE id = $request_id LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('approval_get_next_pending_step')) {
    function approval_get_next_pending_step($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT * FROM approval_steps
                WHERE request_id = $request_id AND status = 'pending'
                ORDER BY step_order ASC LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return null;
        }
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('approval_are_all_steps_approved')) {
    /**
     * أكلُّ خطواتِ السلّمِ معتمدة؟
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ **العطلُ المقيس — صدقٌ خلوًّا (vacuous truth):** كان الشرطُ
     *   `COUNT(status <> 'approved') = 0` وحدَه، وهو **صادقٌ على صفرِ خطوة**.
     *   فطلبٌ بلا أيِّ توقيعٍ يُقرأ «كلُّ خطواتِه معتمدة» ⇒ `finalize` ينفّذ
     *   حمولتَه ويَسِمُه `approved`. **اعتمادٌ كاملٌ بصفرِ يد.**
     * ◆ والأثرُ ليس نظريًّا: في القاعدةِ الآن **أربعةُ طلباتٍ بلا خطوةٍ واحدة**،
     *   اثنان منها `approved` **و`executed_at` مكتوب** — أي أن حمولتَيهما
     *   نُفِّذتا، وأحدُهما بتاريخِ 2024-01-28.
     * ◆ والعلاجُ شرطٌ ثانٍ: **خطوةٌ واحدةٌ على الأقل**. فالسلّمُ الذي لا درجةَ
     *   فيه ليس سلّمًا مكتملًا بل سلّمًا **غيرَ مبنيّ**.
     * ◆ وفشلُ الاستعلامِ يعود `false` كما كان — لا يُقرأ إذنًا (fail-closed).
     */
    function approval_are_all_steps_approved($request_id, $conn) {
        $request_id = intval($request_id);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(status = 'approved') AS ok
                  FROM approval_steps WHERE request_id = $request_id";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        $total = intval($row['total']);
        if ($total === 0) {
            return false;   // صفرُ خطوةٍ ليس اكتمالًا — وهذا عينُ ما كان يفلت
        }
        return intval($row['ok']) === $total;
    }
}

if (!function_exists('approval_steps_count')) {
    /** عددُ خطواتِ طلبٍ — يُستخدم في الرسالةِ التي تشرح المنع. */
    function approval_steps_count($request_id, $conn) {
        $request_id = intval($request_id);
        $r = mysqli_query($conn, "SELECT COUNT(*) c FROM approval_steps WHERE request_id = $request_id");
        if (!$r) { return -1; }
        $row = mysqli_fetch_assoc($r);
        return intval($row['c']);
    }
}

if (!function_exists('approval_execute_db_operation')) {
    function approval_execute_db_operation($operation, $conn) {
        $db_action = isset($operation['db_action']) ? $operation['db_action'] : '';
        $table = isset($operation['table']) ? $operation['table'] : '';

        if (!approval_valid_identifier($table)) {
            return approval_response(false, 'اسم الجدول غير صالح');
        }

        if ($db_action === 'update') {
            $data = isset($operation['data']) && is_array($operation['data']) ? $operation['data'] : [];
            $where = isset($operation['where']) && is_array($operation['where']) ? $operation['where'] : [];

            if (empty($data) || empty($where)) {
                return approval_response(false, 'بيانات تحديث غير مكتملة');
            }

            $set_parts = [];
            $where_parts = [];
            $values = [];

            foreach ($data as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم عمود غير صالح في update');
                }
                $set_parts[] = "$key = ?";
                $values[] = $value;
            }

            foreach ($where as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم شرط غير صالح في update');
                }
                $where_parts[] = "$key = ?";
                $values[] = $value;
            }

            $sql = "UPDATE $table SET " . implode(', ', $set_parts) . " WHERE " . implode(' AND ', $where_parts);
            $stmt = approval_stmt_execute($conn, $sql, $values);

            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ update: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ التحديث');
        }

        if ($db_action === 'insert') {
            $data = isset($operation['data']) && is_array($operation['data']) ? $operation['data'] : [];
            if (empty($data)) {
                return approval_response(false, 'بيانات الإدراج غير مكتملة');
            }

            $columns = [];
            $placeholders = [];
            $values = [];

            foreach ($data as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم عمود غير صالح في insert');
                }
                $columns[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }

            $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = approval_stmt_execute($conn, $sql, $values);
            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ insert: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ الإدراج');
        }

        if ($db_action === 'delete') {
            $where = isset($operation['where']) && is_array($operation['where']) ? $operation['where'] : [];
            if (empty($where)) {
                return approval_response(false, 'شروط الحذف غير مكتملة');
            }

            $where_parts = [];
            $values = [];

            foreach ($where as $key => $value) {
                if (!approval_valid_identifier($key)) {
                    return approval_response(false, 'اسم شرط غير صالح في delete');
                }
                $where_parts[] = "$key = ?";
                $values[] = $value;
            }

            $sql = "DELETE FROM $table WHERE " . implode(' AND ', $where_parts);
            $stmt = approval_stmt_execute($conn, $sql, $values);
            if (!$stmt) {
                return approval_response(false, 'فشل تنفيذ delete: ' . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
            return approval_response(true, 'تم تنفيذ الحذف');
        }

        return approval_response(false, 'نوع عملية قاعدة البيانات غير مدعوم');
    }
}

if (!function_exists('approval_payload_tokens')) {
    /**
     * INJ-0219 — رموزُ ما لا يُعرَف وقتَ إنشاءِ الطلب
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ العطل: الحمولةُ تُجمَّد وقتَ **إنشاءِ** الطلب، والمعتمِدُ الأخيرُ ورقمُ
     *   الطلبِ ولحظةُ الاكتمالِ لا تُعرَف إلا وقتَ **الاكتمال**. فمن أراد أن
     *   يكتب «اعتمده فلان» اضطُرَّ إلى مسارٍ ثانٍ يكتبه بعد المحرّك — وهذا هو
     *   المسارُ الموازي الذي تمنعه ترويسةُ هذا الملف نصًّا.
     * ◆ فالحلُّ أن يملأها **المحرّكُ نفسُه** لحظةَ التنفيذ: تبقى الكتابةُ في
     *   يدٍ واحدةٍ — يدِ السلّمِ المكتمل — ولا يُنشأ مسارٌ موازٍ.
     *
     * @return array<string,mixed> رمزٌ ← قيمةٌ (المعتمِدُ عددٌ لا نصٌّ فيُقيَّد i)
     */
    function approval_payload_tokens($request, $conn) {
        $request_id = intval($request['id']);
        /* المعتمِدُ الأخيرُ: صاحبُ أعلى خطوةٍ معتمَدةٍ — لا أوّلُ من وقّع */
        $sql = "SELECT approved_by FROM approval_steps
                WHERE request_id = $request_id AND status = 'approved' AND approved_by IS NOT NULL
                ORDER BY step_order DESC LIMIT 1";
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return [
            '{{request_id}}'    => $request_id,
            '{{final_approver}}' => $row ? intval($row['approved_by']) : null,
            '{{requested_by}}'  => intval($request['requested_by']),
            '{{now}}'           => approval_now(),
            /* عمودُ تاريخٍ (DATE) لا يقبل لحظةً كاملةً في النمطِ الصارم — رمزٌ خاصٌّ به */
            '{{today}}'         => date('Y-m-d'),
        ];
    }
}

if (!function_exists('approval_substitute_tokens')) {
    /** استبدالُ الرموزِ في قيمِ عمليةٍ واحدة — **مطابقةٌ تامةٌ لا داخلَ نصّ**،
     *  فقيمةٌ نصيةٌ تحوي الرمزَ عرضًا لا تُمسّ، ورمزٌ بلا قيمةٍ يُعلن ولا يُخمَّن. */
    function approval_substitute_tokens(array $operation, array $tokens, &$missing) {
        foreach (['data', 'where'] as $part) {
            if (!isset($operation[$part]) || !is_array($operation[$part])) { continue; }
            foreach ($operation[$part] as $k => $v) {
                if (!is_string($v) || !array_key_exists($v, $tokens)) { continue; }
                if ($tokens[$v] === null) { $missing[] = $v; continue; }
                $operation[$part][$k] = $tokens[$v];
            }
        }
        return $operation;
    }
}

if (!function_exists('approval_execute_payload')) {
    function approval_execute_payload($request, $conn) {
        $payload = json_decode($request['payload'], true);
        if (!is_array($payload)) {
            return approval_response(false, 'payload غير صالح');
        }

        $operations = isset($payload['operations']) && is_array($payload['operations']) ? $payload['operations'] : [];
        if (empty($operations)) {
            return approval_response(false, 'لا توجد عمليات لتنفيذها');
        }

        $tokens = approval_payload_tokens($request, $conn);
        foreach ($operations as $operation) {
            $missing = [];
            $operation = approval_substitute_tokens($operation, $tokens, $missing);
            if (!empty($missing)) {
                /* fail-closed: رمزٌ لم يُحَلَّ يعني كتابةً ناقصةَ السند — تُرفض
                   ولا تُكتب بقيمةٍ مخترعة، والقيدُ البنيويُّ كان سيرفضها لاحقًا. */
                return approval_response(false, 'سندٌ ناقصٌ لا يُخمَّن: ' . implode('، ', array_unique($missing)));
            }
            $result = approval_execute_db_operation($operation, $conn);
            if (empty($result['success'])) {
                return $result;
            }
        }

        return approval_response(true, 'تم تنفيذ الطلب النهائي بنجاح');
    }
}

if (!function_exists('approval_finalize_if_completed')) {
    function approval_finalize_if_completed($request_id, $conn) {
        $request = approval_get_request_by_id($request_id, $conn);
        if (!$request) {
            return approval_response(false, 'طلب الموافقة غير موجود');
        }

        if ($request['status'] !== 'pending') {
            return approval_response(false, 'الطلب ليس في حالة انتظار');
        }

        if (!approval_are_all_steps_approved($request_id, $conn)) {
            /* ◆ التمييزُ بين «سلّمٌ لم يكتمل» و«سلّمٌ لم يُبنَ»: الأولُ حالٌ طبيعيةٌ
                 تُنتظر، والثاني **عطلٌ** يجب أن يُعلن لا أن يُقرأ انتظارًا. */
            $cnt = approval_steps_count($request_id, $conn);
            if ($cnt === 0) {
                return approval_response(false,
                    'طلبٌ بلا أيِّ خطوةِ سلّمٍ — لا يُعتمد ولا يُنفَّذ (سلّمٌ غيرُ مبنيٍّ لا سلّمٌ مكتمل)');
            }
            $next = approval_get_next_pending_step($request_id, $conn);
            $next_order = $next ? intval($next['step_order']) : null;
            mysqli_query($conn, "UPDATE approval_requests SET current_step = " . ($next_order === null ? 'NULL' : $next_order) . ", updated_at = NOW() WHERE id = " . intval($request_id));
            return approval_response(true, 'لا تزال هناك مراحل معلقة');
        }

        $executeResult = approval_execute_payload($request, $conn);
        if (empty($executeResult['success'])) {
            return $executeResult;
        }

        $request_id = intval($request_id);
        $sql = "UPDATE approval_requests
                SET status = 'approved',
                    current_step = NULL,
                    approved_at = NOW(),
                    executed_at = NOW(),
                    updated_at = NOW()
                WHERE id = $request_id";

        if (!mysqli_query($conn, $sql)) {
            return approval_response(false, 'تم تنفيذ البيانات ولكن فشل تحديث حالة الطلب: ' . mysqli_error($conn));
        }

        return approval_response(true, 'تم اعتماد وتنفيذ الطلب بنجاح');
    }
}

if (!function_exists('approval_create_request')) {
    function approval_create_request($entity_type, $entity_id, $action, $payload, $requested_by, $conn) {
        $entity_type = trim($entity_type);
        $entity_id = intval($entity_id);
        $action = trim($action);
        $requested_by = intval($requested_by);

        if ($entity_type === '' || $entity_id <= 0 || $action === '' || $requested_by <= 0) {
            return approval_response(false, 'بيانات طلب الموافقة غير مكتملة');
        }

        if (!is_array($payload)) {
            return approval_response(false, 'payload يجب أن يكون مصفوفة');
        }

        $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($payload_json === false) {
            return approval_response(false, 'فشل تحويل payload إلى JSON');
        }

        approval_db_begin($conn);

        try {
            $entity_type_esc = mysqli_real_escape_string($conn, $entity_type);
            $action_esc = mysqli_real_escape_string($conn, $action);

            $dupSql = "SELECT id FROM approval_requests
                       WHERE entity_type = '$entity_type_esc'
                         AND entity_id = $entity_id
                         AND action = '$action_esc'
                         AND status = 'pending'
                       LIMIT 1";
            $dupResult = mysqli_query($conn, $dupSql);
            if ($dupResult && mysqli_num_rows($dupResult) > 0) {
                $dupRow = mysqli_fetch_assoc($dupResult);
                approval_db_rollback($conn);
                return approval_response(false, 'يوجد طلب موافقة معلق مسبقاً لنفس العملية', [
                    'request_id' => intval($dupRow['id'])
                ]);
            }

            $insertSql = "INSERT INTO approval_requests (entity_type, entity_id, action, payload, requested_by, current_step, status, created_at)
                          VALUES (?, ?, ?, ?, ?, 1, 'pending', NOW())";
            $stmt = approval_stmt_execute($conn, $insertSql, [$entity_type, $entity_id, $action, $payload_json, $requested_by]);
            if (!$stmt) {
                throw new Exception('فشل إنشاء طلب الموافقة: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $request_id = intval(mysqli_insert_id($conn));
            $rules = approval_get_workflow_rules($entity_type, $action, $conn);

            if (empty($rules)) {
                throw new Exception('لا توجد مراحل موافقة معرفة لهذا النوع من العمليات');
            }

            foreach ($rules as $rule) {
                $step_order = intval($rule['step_order']);
                $role_required = strval($rule['role_required']);

                $stepSql = "INSERT INTO approval_steps (request_id, role_required, step_order, status, created_at)
                            VALUES (?, ?, ?, 'pending', NOW())";
                $stepStmt = approval_stmt_execute($conn, $stepSql, [$request_id, $role_required, $step_order]);
                if (!$stepStmt) {
                    throw new Exception('فشل إنشاء مراحل الموافقة: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($stepStmt);
            }

            $requester_role = approval_get_user_role();
            $firstStep = approval_get_next_pending_step($request_id, $conn);

            if ($firstStep && approval_user_can_match_role($firstStep['role_required'], $requester_role)) {
                $step_id = intval($firstStep['id']);
                $autoSql = "UPDATE approval_steps
                            SET status = 'approved', approved_by = $requested_by, approved_at = NOW(), note = 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)'
                            WHERE id = $step_id";

                if (!mysqli_query($conn, $autoSql)) {
                    throw new Exception('فشل الاعتماد التلقائي للمرحلة الأولى');
                }
            }

            $finalizeResult = approval_finalize_if_completed($request_id, $conn);
            if (empty($finalizeResult['success'])) {
                throw new Exception($finalizeResult['message']);
            }

            $requestAfter = approval_get_request_by_id($request_id, $conn);
            $status = $requestAfter ? $requestAfter['status'] : 'pending';

            approval_db_commit($conn);

            return approval_response(true, $status === 'approved' ? 'تم اعتماد الطلب وتنفيذه مباشرة' : 'تم إرسال الطلب للموافقة', [
                'request_id' => $request_id,
                'status' => $status
            ]);
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_approve_request')) {
    function approval_approve_request($request_id, $approved_by, $conn, $note = '') {
        $request_id = intval($request_id);
        $approved_by = intval($approved_by);

        if ($request_id <= 0 || $approved_by <= 0) {
            return approval_response(false, 'بيانات الاعتماد غير صحيحة');
        }

        approval_db_begin($conn);

        try {
            $request = approval_get_request_by_id($request_id, $conn);
            if (!$request) {
                throw new Exception('طلب الموافقة غير موجود');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('لا يمكن اعتماد طلب غير معلق');
            }

            $step = approval_get_next_pending_step($request_id, $conn);
            if (!$step) {
                throw new Exception('لا توجد مرحلة معلقة للاعتماد');
            }

            $user_role = approval_get_user_role();
            if (!approval_user_can_match_role($step['role_required'], $user_role)) {
                throw new Exception('ليس لديك صلاحية لاعتماد هذه المرحلة');
            }

            /* INJ-0219 — «بيدين مختلفتين»: لا يدَ تمشي خطوتين في سلّمٍ واحد.
               ═══════════════════════════════════════════════════════════════
               ◆ العطل المقيس: `approval_user_can_match_role` تُرجع true للسوبر
                 على **أيِّ** خطوة، و`approval_create_request` تعتمد الخطوةَ
                 الأولى تلقائيًّا لمنشئِ الطلبِ إن طابق دورَها. فسلّمٌ من ثلاثِ
                 خطواتٍ يمشيه شخصٌ واحدٌ من أوّله إلى آخره — والسلّمُ حينها
                 عددُ نقراتٍ لا عددُ أيدٍ.
               ◆ والقاعدةُ عامةٌ لا خاصةٌ بالخصوم: اعتمادُ خطوتين بيدٍ واحدةٍ لم
                 يكن مقصودًا في أيِّ نوعِ كيان. وهي **آمنةٌ للسلاليمِ ذاتِ
                 الخطوةِ الواحدة** (لا خطوةَ سابقةً فلا رفض)، فلا تنقلب على
                 العشرين مسارًا القائمة.
               ◆ وبها يصير عددُ الأيدي = عددَ الخطواتِ المسجَّلةِ بنيويًّا؛
                 فتسجيلُ ثلاثِ خطواتٍ للخصمِ يعني ثلاثَ أيدٍ لا واحدة. */
            $priorSql = "SELECT step_order FROM approval_steps
                         WHERE request_id = $request_id AND status = 'approved'
                           AND approved_by = $approved_by LIMIT 1";
            $priorRes = mysqli_query($conn, $priorSql);
            if ($priorRes === false) {
                throw new Exception('تعذّر فحصُ أيدي السلّم: ' . mysqli_error($conn));
            }
            $prior = mysqli_fetch_assoc($priorRes);
            if ($prior) {
                throw new Exception('يدٌ واحدةٌ لا تمشي خطوتين في سلّمٍ واحد — اعتمدتَ الخطوةَ '
                    . intval($prior['step_order']) . ' من هذا الطلب، والخطوةُ '
                    . intval($step['step_order']) . ' ليدٍ أخرى (403)');
            }

            $step_id = intval($step['id']);
            $note_esc = mysqli_real_escape_string($conn, $note);

            $sql = "UPDATE approval_steps
                    SET status = 'approved', approved_by = $approved_by, approved_at = NOW(), note = '$note_esc'
                    WHERE id = $step_id";

            if (!mysqli_query($conn, $sql)) {
                throw new Exception('فشل تحديث مرحلة الموافقة: ' . mysqli_error($conn));
            }

            $finalizeResult = approval_finalize_if_completed($request_id, $conn);
            if (empty($finalizeResult['success'])) {
                throw new Exception($finalizeResult['message']);
            }

            approval_db_commit($conn);

            $requestAfter = approval_get_request_by_id($request_id, $conn);
            return approval_response(true, $requestAfter && $requestAfter['status'] === 'approved' ? 'تم الاعتماد النهائي وتنفيذ العملية' : 'تم اعتماد المرحلة بنجاح', [
                'request_status' => $requestAfter ? $requestAfter['status'] : 'pending'
            ]);
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_reject_request')) {
    function approval_reject_request($request_id, $rejected_by, $conn, $reason = '') {
        $request_id = intval($request_id);
        $rejected_by = intval($rejected_by);

        if ($request_id <= 0 || $rejected_by <= 0) {
            return approval_response(false, 'بيانات الرفض غير صحيحة');
        }

        approval_db_begin($conn);

        try {
            $request = approval_get_request_by_id($request_id, $conn);
            if (!$request) {
                throw new Exception('طلب الموافقة غير موجود');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('لا يمكن رفض طلب غير معلق');
            }

            $step = approval_get_next_pending_step($request_id, $conn);
            if (!$step) {
                throw new Exception('لا توجد مرحلة معلقة للرفض');
            }

            $user_role = approval_get_user_role();
            if (!approval_user_can_match_role($step['role_required'], $user_role)) {
                throw new Exception('ليس لديك صلاحية رفض هذه المرحلة');
            }

            $step_id = intval($step['id']);
            $reason_esc = mysqli_real_escape_string($conn, $reason);

            $sqlStep = "UPDATE approval_steps
                        SET status = 'rejected', approved_by = $rejected_by, approved_at = NOW(), note = '$reason_esc'
                        WHERE id = $step_id";
            if (!mysqli_query($conn, $sqlStep)) {
                throw new Exception('فشل تحديث خطوة الرفض: ' . mysqli_error($conn));
            }

            $sqlReq = "UPDATE approval_requests
                       SET status = 'rejected',
                           rejection_reason = '$reason_esc',
                           rejected_at = NOW(),
                           current_step = NULL,
                           updated_at = NOW()
                       WHERE id = $request_id";
            if (!mysqli_query($conn, $sqlReq)) {
                throw new Exception('فشل تحديث حالة الطلب: ' . mysqli_error($conn));
            }

            approval_db_commit($conn);
            return approval_response(true, 'تم رفض الطلب بنجاح');
        } catch (Exception $ex) {
            approval_db_rollback($conn);
            return approval_response(false, $ex->getMessage());
        }
    }
}

if (!function_exists('approval_build_requests_where')) {
    /** بناء شرط قائمة الطلبات (فلتر الحالة + رؤية الدور) — داخل القناة حصرًا */
    function approval_build_requests_where($status_filter, $user_role, $user_id, $conn) {
        $where = "1=1";
        if ($status_filter !== 'all') {
            $status_esc = mysqli_real_escape_string($conn, $status_filter);
            $where .= " AND ar.status = '$status_esc'";
        }
        $user_id = intval($user_id);
        if ($user_role !== '-1') {
            $role_esc = mysqli_real_escape_string($conn, strval($user_role));
            $where .= " AND (
        ar.requested_by = $user_id
        OR EXISTS (
            SELECT 1 FROM approval_steps aps
            WHERE aps.request_id = ar.id
              AND aps.status = 'pending'
              AND (FIND_IN_SET('$role_esc', aps.role_required) > 0)
        )
        OR EXISTS (
            SELECT 1 FROM approval_steps aps_done
            WHERE aps_done.request_id = ar.id
              AND aps_done.approved_by = $user_id
              AND aps_done.status IN ('approved', 'rejected')
        )
    )";
        }
        return $where;
    }
}

if (!function_exists('approval_fetch_requests_for_listing')) {
    /** قراءة قائمة الطلبات لشاشة الاعتمادات — جداول القناة المقيّدة تُقرأ هنا لا في الشاشة */
    function approval_fetch_requests_for_listing($status_filter, $user_role, $user_id, $conn) {
        $where = approval_build_requests_where($status_filter, $user_role, $user_id, $conn);
        $sql = "SELECT ar.*,
               u.username AS requester_name,
               aps.role_required AS pending_role,
               aps.step_order AS pending_step
        FROM approval_requests ar
        LEFT JOIN users u ON u.id = ar.requested_by
        LEFT JOIN approval_steps aps
            ON aps.request_id = ar.id
           AND aps.status = 'pending'
           AND aps.step_order = (
               SELECT MIN(s2.step_order)
               FROM approval_steps s2
               WHERE s2.request_id = ar.id
                 AND s2.status = 'pending'
           )
        WHERE $where
        ORDER BY ar.id DESC";
        $result = mysqli_query($conn, $sql);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) { $rows[] = $row; }
        }
        return $rows;
    }
}

if (!function_exists('approval_fetch_request_stats')) {
    /** إحصاءات الطلبات بحسب الحالة لنفس نطاق الرؤية */
    function approval_fetch_request_stats($status_filter, $user_role, $user_id, $conn) {
        $where = approval_build_requests_where($status_filter, $user_role, $user_id, $conn);
        $stats_sql = "SELECT
        status,
        COUNT(*) as count
    FROM approval_requests ar
    WHERE ($where)
    GROUP BY status;";
        $stats_result = mysqli_query($conn, $stats_sql);
        $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        if ($stats_result) {
            while ($stat = mysqli_fetch_assoc($stats_result)) {
                $stats[$stat['status']] = $stat['count'];
            }
        }
        return $stats;
    }
}

if (!function_exists('approval_build_simple_update_payload')) {
    function approval_build_simple_update_payload($table, $where, $new_data, $old_data = []) {
        return [
            'summary' => [
                'table' => $table,
                'operation' => 'update',
                'old_values' => $old_data,
                'new_values' => $new_data
            ],
            'operations' => [
                [
                    'db_action' => 'update',
                    'table' => $table,
                    'where' => $where,
                    'data' => $new_data
                ]
            ]
        ];
    }
}

if (!function_exists('approval_build_simple_delete_payload')) {
    function approval_build_simple_delete_payload($table, $where, $old_data = []) {
        return [
            'summary' => [
                'table' => $table,
                'operation' => 'delete',
                'old_values' => $old_data,
                'new_values' => null
            ],
            'operations' => [
                [
                    'db_action' => 'delete',
                    'table' => $table,
                    'where' => $where
                ]
            ]
        ];
    }
}
?>