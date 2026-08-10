<?php
/**
 * حاكمُ الحقول — FieldGovernor (FIN-OBL-01 §٤-١٧ · §٤-٢١ · PROP-01 §٤-١ ⑤⑥)
 * ═══════════════════════════════════════════════════════════════════════════
 * يحرس حكمين:
 *
 *  ⑥ التصنيفُ الرباعي — OBL-0052/0057:
 *     «كلُّ حقلٍ يُوسَم بصنفٍ واحدٍ على الأقلِّ من أربعة · **والحقلُ بلا صنفٍ لا
 *      يُدرَج في شاشةٍ حاكمة** · ◆ والصنفُ يحدد من يُنشئ ومن يقرأ ومن يعدّل —
 *      **لا اسمُ المستخدم**.»
 *     و`canEdit()` هي البابُ: تسأل الصنفَ لا الاسم.
 *
 *  ⑦ التوريثُ — IN-01/IN-03:
 *     «لا يُعاد إدخالُ بيانٍ موجودٍ في مرجعٍ أب · والموروثُ للقراءةِ فقط ·
 *      ومحاولةُ تعديلِه تُرفض برمزٍ **يبيّن مصدرَه**.»
 *     و«تغييرُ الأصلِ يُحدِّث الموروثَ في غيرِ المعتمدِ **ويُنبِّه** في المعتمد —
 *      فالمعتمدُ لا رجعيةَ فيه.»
 *
 * ◆ ترتيبُ الحراسة: التوريثُ يسبق التصنيف. فالحقلُ الموروثُ مرفوضٌ للجميعِ
 *   مهما كان صنفُه ومهما كان دورُ الطالب — «لكلِّ حقلٍ مصدرٌ واحد».
 */

namespace App\Services\Governance;

class FieldGovernor
{
    /* ═══════════════════════════════════════════════════════════════════════
       ⑦ التوريث
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * أهذا الحقلُ موروثٌ؟ وإن كان — من أين؟
     * @return array|null صفُّ التوريثِ أو null
     */
    public static function inheritanceOf(\mysqli $conn, $childEntity, $childField)
    {
        $st = $conn->prepare(
            "SELECT child_entity, child_field, parent_entity, parent_field, label_ar,
                    readonly, on_parent_change, doc_ref
               FROM gov_field_inheritance
              WHERE child_entity = ? AND child_field = ? AND active = 1 LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('ss', $childEntity, $childField);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * IN-01: محاولةُ تعديلِ حقلٍ موروثٍ تُرفض **برمزٍ يبيّن مصدرَه** —
     * والرفضُ يُسجَّل ليُقاس.
     */
    public static function assertNotInherited(\mysqli $conn, array $ctx)
    {
        $entity = trim((string) ($ctx['child_entity'] ?? ''));
        $field  = trim((string) ($ctx['child_field'] ?? ''));
        $inh    = self::inheritanceOf($conn, $entity, $field);
        if ($inh === null || (int) $inh['readonly'] !== 1) {
            return array('ok' => true, 'code' => 200, 'reason' => 'حقلٌ غيرُ موروثٍ فيُحرَّر بحسبِ صنفِه');
        }

        $source = $inh['parent_entity'] . '.' . $inh['parent_field'];
        self::logDenial($conn, $ctx, $source);
        return array('ok' => false, 'code' => 409, 'source' => $source,
            'reason' => 'حقلٌ موروثٌ للقراءةِ فقط — مصدرُه ' . $source
                      . ' « ' . $inh['label_ar'] . ' » · ولا يُدخَل حقلٌ مرتين (IN-01)');
    }

    /**
     * IN-03: تغييرُ الأصلِ يُحدِّث الموروثَ في غيرِ المعتمدِ ويُنبِّه في المعتمد.
     * @return array{cascade:array, notify:array} أسماءُ الحقولِ في كلِّ مسار
     */
    public static function onParentChange(\mysqli $conn, $parentEntity, $parentField, $childApproved)
    {
        $out = array('cascade' => array(), 'notify' => array());
        $st = $conn->prepare(
            "SELECT child_entity, child_field, on_parent_change FROM gov_field_inheritance
              WHERE parent_entity = ? AND parent_field = ? AND active = 1");
        if (!$st) { return $out; }
        $st->bind_param('ss', $parentEntity, $parentField);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $key = $r['child_entity'] . '.' . $r['child_field'];
            /* ◆ المعتمدُ لا رجعيةَ فيه — يُنبَّه ولا يتغير مهما كانت القاعدة. */
            if ($childApproved || $r['on_parent_change'] === 'notify_only') { $out['notify'][] = $key; }
            else { $out['cascade'][] = $key; }
        }
        $st->close();
        return $out;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       ⑥ التصنيفُ الرباعي
       ═══════════════════════════════════════════════════════════════════════ */

    /** صنفُ حقلٍ في شاشةٍ — أو null إن لم يُصنَّف. */
    public static function classOf(\mysqli $conn, $screen, $field)
    {
        $st = $conn->prepare(
            "SELECT f.dc_code, f.label_ar, f.is_sensitive,
                    c.title, c.owner_label, c.create_roles, c.edit_roles, c.edit_mode
               FROM gov_field_class f
               JOIN gov_data_classes c ON c.code = f.dc_code AND c.active = 1
              WHERE f.screen_code = ? AND f.field_key = ? AND f.active = 1 LIMIT 1");
        if (!$st) { return null; }
        $st->bind_param('ss', $screen, $field);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /**
     * OBL-0052: «الحقلُ بلا صنفٍ لا يُدرَج في شاشةٍ حاكمة».
     * fail-closed على الشاشاتِ الحاكمةِ وحدَها — وغيرُها لا يلزمها التصنيف.
     */
    public static function assertClassified(\mysqli $conn, $screen, $field)
    {
        if (!self::isGoverning($conn, $screen)) {
            return array('ok' => true, 'code' => 200, 'reason' => 'شاشةٌ غيرُ حاكمةٍ فلا يلزمها التصنيف');
        }
        $c = self::classOf($conn, $screen, $field);
        if ($c === null) {
            return array('ok' => false, 'code' => 409,
                'reason' => 'حقلٌ بلا صنفٍ لا يُدرَج في شاشةٍ حاكمة: ' . $screen . '.' . $field . ' (OBL-0052)');
        }
        return array('ok' => true, 'code' => 200, 'dc' => $c['dc_code'], 'reason' => $c['title']);
    }

    /**
     * OBL-0057: «الصنفُ يحدد من يُنشئ ومن يقرأ ومن يعدّل — لا اسمُ المستخدم».
     * والترتيب: التوريثُ أولًا · ثم التصنيفُ · ثم طريقةُ التعديل.
     */
    public static function canEdit(\mysqli $conn, array $ctx)
    {
        $screen = trim((string) ($ctx['screen_code'] ?? ''));
        $field  = trim((string) ($ctx['field_key'] ?? ''));
        $roleId = (string) intval($ctx['role_id'] ?? 0);

        /* ① الموروثُ مرفوضٌ للجميع — ولو كان الطالبُ مالكَ الصنف. */
        $entity = (string) ($ctx['child_entity'] ?? $screen);
        $inh = self::inheritanceOf($conn, $entity, $field);
        if ($inh !== null && (int) $inh['readonly'] === 1) {
            self::logDenial($conn, $ctx + array('child_entity' => $entity, 'child_field' => $field),
                            $inh['parent_entity'] . '.' . $inh['parent_field']);
            return array('ok' => false, 'code' => 409,
                'reason' => 'حقلٌ موروثٌ — مصدرُه ' . $inh['parent_entity'] . '.' . $inh['parent_field'] . ' (IN-01)');
        }

        /* ② الصنفُ — والحقلُ بلا صنفٍ في شاشةٍ حاكمةٍ لا يُدرَج أصلًا. */
        $g = self::assertClassified($conn, $screen, $field);
        if (empty($g['ok'])) { return $g; }
        $c = self::classOf($conn, $screen, $field);
        if ($c === null) { return array('ok' => true, 'code' => 200, 'reason' => 'شاشةٌ غيرُ حاكمة'); }

        /* ③ طريقةُ التعديلِ التي يفرضها الصنف. */
        switch ($c['edit_mode']) {
            case 'amendment_only':
                return array('ok' => false, 'code' => 403, 'dc' => $c['dc_code'],
                    'reason' => $c['title'] . ' — التعديلُ بملحقٍ موقَّعٍ لا بتحريرِ حقل (DC-3)');
            case 'decision_only':
                if (!self::roleAllowed($roleId, $c['edit_roles'])) {
                    return array('ok' => false, 'code' => 403, 'dc' => $c['dc_code'],
                        'reason' => $c['title'] . ' — لا يُعدَّل إلا بقرارٍ ماليٍّ معتمدٍ من ' . $c['owner_label'] . ' (DC-4)');
                }
                break;
            case 'proposal':
                if (!self::roleAllowed($roleId, $c['edit_roles'])) {
                    return array('ok' => false, 'code' => 403, 'dc' => $c['dc_code'],
                        'reason' => $c['title'] . ' — الإدارةُ تقترحه والماليةُ تحسمه · مالكُه ' . $c['owner_label'] . ' (DC-2)');
                }
                break;
            default: /* direct */
                if (trim((string) $c['edit_roles']) !== '' && !self::roleAllowed($roleId, $c['edit_roles'])) {
                    return array('ok' => false, 'code' => 403, 'dc' => $c['dc_code'],
                        'reason' => $c['title'] . ' — مالكُه ' . $c['owner_label']);
                }
        }
        return array('ok' => true, 'code' => 200, 'dc' => $c['dc_code'], 'reason' => 'مسموحٌ بصنفِه ' . $c['title']);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       المسحُ الشامل — الشاهدُ على «صفرُ حقلٍ بلا صنف»
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * PROP-01 §٧-٢ ⑤: يمسح كلَّ شاشةٍ حاكمةٍ ويُرجع حقولَها غيرَ المصنَّفة.
     * ◆ يُقاس على أعمدةِ الجدولِ الحقيقيةِ لا على قائمةٍ مكتوبة — فالقائمةُ
     *   المكتوبةُ تتعفن والجدولُ لا يكذب.
     */
    public static function scanUnclassified(\mysqli $conn, array $tableFor = array())
    {
        $out = array();
        $r = $conn->query("SELECT screen_code FROM gov_governing_screens WHERE active = 1");
        while ($r && $s = $r->fetch_assoc()) {
            $screen = $s['screen_code'];
            $table  = isset($tableFor[$screen]) ? $tableFor[$screen] : $screen;
            $cols = array();
            $q = $conn->prepare("SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            if ($q) {
                $q->bind_param('s', $table);
                $q->execute();
                $rs = $q->get_result();
                while ($x = $rs->fetch_assoc()) { $cols[] = $x['c']; }
                $q->close();
            }
            if (!$cols) { continue; }   // لا جدولَ يقابلها — لا حكمَ لنا عليها هنا
            $classified = array();
            $q2 = $conn->prepare("SELECT field_key FROM gov_field_class
                                   WHERE screen_code = ? AND active = 1");
            if ($q2) {
                $q2->bind_param('s', $screen);
                $q2->execute();
                $rs2 = $q2->get_result();
                while ($x = $rs2->fetch_assoc()) { $classified[] = $x['field_key']; }
                $q2->close();
            }
            $miss = array_values(array_diff($cols, $classified, self::TECHNICAL_COLS));
            if ($miss) { $out[$screen] = $miss; }
        }
        return $out;
    }

    /**
     * أعمدةٌ تقنيةٌ لا تحمل معنًى للمراجعِ فلا يلزمها صنف: المفاتيحُ والطوابعُ
     * وأثرُ التدقيقِ ورايةُ النشاط. وما عداها **يلزمه صنف**.
     */
    const TECHNICAL_COLS = array(
        'id', 'company_id', 'created_at', 'updated_at', 'created_by', 'updated_by',
        'is_deleted', 'deleted_at', 'deleted_by', 'active', 'doc_ref', 'sort_order',
    );

    /* ═══════════════════════════════════════════════════════════════════════
       ⑧ حجبُ الحقلِ الحساسِ في الخادم — CS-10 · RF-03 (FIXA-0011 · FIXA-0029)
       ═══════════════════════════════════════════════════════════════════════
       «الحقلُ الذي لا يملكه المستخدمُ **لا يُرسَل في الاستجابةِ أصلًا** ولا يُخفى
        بأسلوبِ عرض.» ◆ والحقلُ الحساسُ يُعرَّف بالسجلِّ لا بالرأي: صفٌّ معتمدٌ في
        ‎scr_sensitive_fields‎ (الجدولُ والحقلُ ورايةُ قابليةِ التصدير) — والمنحُ
        **فرديٌّ** بأدوارٍ صريحةٍ في ‎sensitive_field_policies.allowed_roles_json‎.
       ◆ والفشلُ مغلق: حقلٌ معتمدٌ «لا يُصدَّر» بلا سياسةِ منحٍ ⇒ يُحجب للجميع
        إلا المدير الأعلى. فغيابُ المنحِ منعٌ لا سماح. */

    /**
     * أسماءُ الحقولِ الحساسةِ في جدولٍ — مع رايةِ قابليةِ التصديرِ لكلٍّ.
     * @return array<string,array{exportable:bool,logged:bool,label:string}>
     */
    public static function sensitiveFieldsOf(\mysqli $conn, $table)
    {
        $out = array();
        $st = $conn->prepare(
            "SELECT field_name, exportable_flag, log_views_flag, classification_sensitivity
               FROM scr_sensitive_fields
              WHERE table_name = ? AND status = 'معتمد'");
        if (!$st) { return $out; }
        $st->bind_param('s', $table);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $out[$r['field_name']] = array(
                // ◆ «لا» صريحةٌ تمنع · وأيُّ قيمةٍ غيرِ «نعم» تُقرأ منعًا (فشلٌ مغلق).
                'exportable' => (mb_strpos((string) $r['exportable_flag'], 'نعم') !== false),
                'logged'     => (mb_strpos((string) $r['log_views_flag'], 'نعم') !== false),
                'label'      => (string) $r['classification_sensitivity'],
            );
        }
        $st->close();
        return $out;
    }

    /**
     * أيملك هذا الدورُ منحًا فرديًّا على الحقلِ ‎جدول.حقل‎؟
     *
     * ◆ INJ-0062 · تبنّي `SensitiveFieldGuard` — **مقيِّمُ السياسةِ واحدٌ لا ثلاثة**.
     *   كان هنا تقييمٌ ثانٍ لـ`sensitive_field_policies` مكتوبٌ يدويًّا بينما
     *   `SensitiveFieldGuard::readerAllowed` يقيّمها أصلًا **وبصفرِ مستهلك**.
     *   ومقيِّمان لسياسةٍ واحدةٍ هو عينُ عيبِ «المسارين المتوازيين»: هذا يعرف
     *   الأدوارَ وحدَها، وذاك يعرف الأدوارَ **والمنحَ الفرديةَ النافذة**
     *   (`sensitive_access_grants`) والمديرَ الأعلى. فالتفويضُ إليه ليس تبنّيًا
     *   شكليًّا — بل **يزيد الدقة** ويُلغي النسخة.
     * ◆ وارتدادٌ مُعلَن: إن غاب الصنفُ لسببٍ ما يُقيَّم بالأدوارِ وحدَها — منعًا
     *   لا سماحًا (فشلٌ مغلق).
     */
    public static function roleHasFieldGrant(\mysqli $conn, $table, $field, $roleId, $personId = 0, $companyId = 0)
    {
        $code = $table . '.' . $field;

        $guardFile = dirname(__DIR__) . '/Security/SensitiveFieldGuard.php';
        if (!class_exists('\\App\\Services\\Security\\SensitiveFieldGuard', false) && is_file($guardFile)) {
            require_once $guardFile;
        }
        if (class_exists('\\App\\Services\\Security\\SensitiveFieldGuard', false)) {
            $pol = \App\Services\Security\SensitiveFieldGuard::policy($conn, $code);
            if (!$pol) { return false; }                // لا سياسةَ ⇒ لا منحَ ⇒ منع
            $v = \App\Services\Security\SensitiveFieldGuard::readerAllowed(
                $conn, $pol, (int) $personId, (string) $roleId, (int) $companyId, $code);
            return !empty($v['ok']);
        }

        // ارتدادٌ مُعلَن: الأدوارُ وحدَها بلا المنحِ الفردي.
        $st = $conn->prepare("SELECT allowed_roles_json FROM sensitive_field_policies
                               WHERE field_code = ? LIMIT 1");
        if (!$st) { return false; }
        $st->bind_param('s', $code);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return false; }
        $roles = json_decode((string) $row['allowed_roles_json'], true);
        if (!is_array($roles)) { return false; }
        foreach ($roles as $r) { if ((string) $r === (string) $roleId) { return true; } }
        return false;
    }

    /**
     * CS-10 · البابُ الوحيد: أيُّ أعمدةٍ يحقُّ لهذا الدورِ تصديرُها من هذا الجدول؟
     *
     * @param array $columns أسماءُ الأعمدةِ المطلوبة
     * @return array{allowed:string[],blocked:string[],logged:string[]}
     */
    public static function exportableColumns(\mysqli $conn, $table, $roleId, array $columns, $isSuperAdmin = false)
    {
        $sensitive = self::sensitiveFieldsOf($conn, $table);
        $allowed = array(); $blocked = array(); $logged = array();
        foreach ($columns as $col) {
            if (!isset($sensitive[$col])) { $allowed[] = $col; continue; }
            if ($sensitive[$col]['logged']) { $logged[] = $col; }
            if ($isSuperAdmin) { $allowed[] = $col; continue; }
            $personId  = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
            $companyId = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
            $ok = $sensitive[$col]['exportable']
                || self::roleHasFieldGrant($conn, $table, $col, $roleId, $personId, $companyId);
            if ($ok) { $allowed[] = $col; } else { $blocked[] = $col; }
        }
        return array('allowed' => $allowed, 'blocked' => $blocked, 'logged' => $logged);
    }

    /** أهذه الشاشةُ حاكمة؟ */
    public static function isGoverning(\mysqli $conn, $screen)
    {
        $st = $conn->prepare("SELECT 1 FROM gov_governing_screens
                               WHERE screen_code = ? AND active = 1 LIMIT 1");
        if (!$st) { return false; }
        $st->bind_param('s', $screen);
        $st->execute();
        $ok = (bool) $st->get_result()->fetch_row();
        $st->close();
        return $ok;
    }

    /* ── مساعدات ─────────────────────────────────────────────────────────── */

    private static function roleAllowed($roleId, $csv)
    {
        $csv = trim((string) $csv);
        if ($csv === '') { return true; }
        foreach (explode(',', $csv) as $p) { if (trim($p) === (string) $roleId) { return true; } }
        return false;
    }

    private static function logDenial(\mysqli $conn, array $ctx, $source)
    {
        $st = $conn->prepare(
            "INSERT INTO gov_inheritance_denials
               (company_id, child_entity, child_ref, child_field, source_shown, attempted_by, denied_at)
             VALUES (?,?,?,?,?,?,NOW())");
        if (!$st) { return; }
        $co  = intval($ctx['company_id'] ?? 0);
        $ent = mb_substr((string) ($ctx['child_entity'] ?? ''), 0, 60);
        $ref = mb_substr((string) ($ctx['child_ref'] ?? ''), 0, 120);
        $fld = mb_substr((string) ($ctx['child_field'] ?? ''), 0, 80);
        $src = mb_substr((string) $source, 0, 200);
        $by  = intval($ctx['user_id'] ?? 0);
        $st->bind_param('issssi', $co, $ent, $ref, $fld, $src, $by);
        $st->execute();
        $st->close();
    }
}
