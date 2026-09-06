<?php
/**
 * includes/perm_layers.php — أنواعُ بنودِ القالبِ الأربعةُ الباقية (PERM-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * `gov_profile_items.item_kind` مفرداتُه خمسٌ: `screen` · `action` · `cap` ·
 * `scope` · `field`. والمنفَّذُ كان **`screen` وحدَه** — والأربعةُ الأخرى
 * «تصميمُنا نفسه منفَّذًا جزئيًّا» بنصِّ الأمر (PERM-01 §1-2).
 *
 * ◆ **والبندُ مرجعٌ لا تعريفٌ جديد**: القالبُ لا يخترع فعلًا ولا سقفًا ولا
 *   حقلًا — بل **يربط الدورَ بمدخلٍ في سجلٍّ حاكمٍ قائم**:
 *     `action` ⇐ مفرداتُ `gov_authority_limits.action_codes`
 *     `cap`    ⇐ `gov_authority_limits.code`  (السقفُ في سجلِّه لا في القالب)
 *     `field`  ⇐ `sensitive_field_policies.field_code`
 *     `scope`  ⇐ رمزُ مساحةٍ أو إدارةٍ في سجلِّ المواضع
 *   فلا يصير القالبُ سجلًّا ثانيًا يخالف الأوّل. [[impact-bridge-legend-from-source]]
 *
 * ⛔ **وسلامةُ الفشلِ نحوَ المنعِ**: تعذُّرُ القراءةِ يُقرأ «لا يملك» لا «يملك».
 * ⛔ **والحبّةُ مستخدمٌ لا دور**: القوالبُ تُمنح بالفرد، فكلُّ قارئٍ هنا يسأل
 *   بـ`user_id` ويأخذه من الجلسةِ إن لم يُمرَّر.
 * ◆ **والمخبأُ بهويّةِ المستخدم**: مجسّاتُ القياسِ تبدّل الجلسةَ في العمليّةِ
 *   الواحدة، ومخبأٌ ساكنٌ أعمى يُلبس الجميعَ قالبَ أوّلِهم.
 */

if (!function_exists('ems_profile_layer')) {

    /**
     * مجموعةُ ما يسمح به قالبُ المستخدمِ من نوعٍ واحد.
     *
     * @param string $kind action|cap|scope|field|screen
     * @return array<string,array{allow:int,can_add:int,can_edit:int,can_delete:int}>
     */
    function ems_profile_layer($conn, $kind, $userId = null)
    {
        static $cache = array();
        $kind = (string) $kind;
        if (!in_array($kind, array('screen', 'action', 'cap', 'scope', 'field'), true)) {
            return array();
        }
        $uid = ($userId === null)
            ? (isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0)
            : (int) $userId;
        if ($uid <= 0) { return array(); }

        $key = $uid . '|' . $kind;
        if (isset($cache[$key])) { return $cache[$key]; }

        $out = array();
        try {
            $st = $conn->prepare(
                "SELECT i.item_ref,
                        MAX(i.allow) a, MAX(i.can_add) ad, MAX(i.can_edit) ed, MAX(i.can_delete) dl
                   FROM gov_authority_grants g
                   JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                   JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = ?
                  WHERE g.user_id = ? AND g.revoked_at IS NULL
                    AND (g.valid_to IS NULL OR g.valid_to > NOW())
                  GROUP BY i.item_ref"
            );
            if (!$st) { return $cache[$key] = array(); }
            $st->bind_param('si', $kind, $uid);
            if (!$st->execute()) { $st->close(); return $cache[$key] = array(); }
            $r = $st->get_result();
            while ($r && ($x = $r->fetch_assoc())) {
                if ((int) $x['a'] !== 1) { continue; }   /* المستبعَدُ لا يُقرأ سماحًا */
                $out[(string) $x['item_ref']] = array(
                    'allow' => 1, 'can_add' => (int) $x['ad'],
                    'can_edit' => (int) $x['ed'], 'can_delete' => (int) $x['dl']);
            }
            $st->close();
        } catch (\Throwable $t) {
            /* ⛔ الشكُّ يُحسم منعًا: مجموعةٌ فارغةٌ تعني «لا يملك». */
            return $cache[$key] = array();
        }
        return $cache[$key] = $out;
    }

    /**
     * أيملك هذا الفاعلُ فعلًا مُعلَنًا؟ — بندُ `action` في قالبِه.
     *
     * ⛔ **والفعلُ غيرُ المسجَّلِ في السجلِّ الحاكمِ لا يُشتقُّ منه سماح**: من
     *   سأل عن رمزٍ لا وجودَ له في `gov_authority_limits` يُردّ — وإلّا صار
     *   الخطأُ الإملائيُّ بابًا مفتوحًا. [[measure-token-must-exist]]
     *
     * @return bool
     */
    function ems_can_action($conn, $actionCode, $userId = null)
    {
        $actionCode = trim((string) $actionCode);
        if ($actionCode === '') { return false; }
        if (!ems_action_is_declared($conn, $actionCode)) { return false; }
        $set = ems_profile_layer($conn, 'action', $userId);
        return isset($set[$actionCode]);
    }

    /**
     * مفرداتُ الأفعالِ المُعلَنةُ في السجلِّ الحاكم — تُقرأ ولا تُخترع.
     *
     * @return array<string,1>
     */
    function ems_action_vocabulary($conn)
    {
        static $vocab = null;
        if ($vocab !== null) { return $vocab; }
        $vocab = array();
        try {
            $r = @$conn->query("SELECT action_codes FROM gov_authority_limits
                                 WHERE action_codes <> '' AND active = 1");
            while ($r && ($x = $r->fetch_row())) {
                foreach (explode(',', (string) $x[0]) as $c) {
                    $c = trim($c);
                    if ($c !== '') { $vocab[$c] = 1; }
                }
            }
        } catch (\Throwable $t) { $vocab = array(); }
        return $vocab;
    }

    /** أمُعلَنٌ هذا الفعلُ في السجلِّ الحاكم؟ */
    function ems_action_is_declared($conn, $actionCode)
    {
        $v = ems_action_vocabulary($conn);
        return isset($v[trim((string) $actionCode)]);
    }

    /**
     * السقوفُ التي يتقيَّد بها الفاعل — رموزُ حدودٍ في `gov_authority_limits`.
     *
     * ◆ **والقيمةُ في سجلِّها لا في القالب**: القالبُ يقول «هذا الدورُ محكومٌ
     *   بالحدِّ FIN-TRE-01-03»، والحدُّ نصُّه وشرطُه في سجلِّه — فلا رقمَ
     *   مكرَّرٌ في موضعَين يفترقان.
     *
     * @return array<int,array> صفوفُ الحدودِ المنطبقة
     */
    function ems_profile_caps($conn, $userId = null)
    {
        $refs = array_keys(ems_profile_layer($conn, 'cap', $userId));
        if (!$refs) { return array(); }
        $out = array();
        try {
            $in = array();
            foreach ($refs as $c) { $in[] = "'" . $conn->real_escape_string((string) $c) . "'"; }
            $r = @$conn->query("SELECT code, subject_role, forbidden, action_codes,
                                       limit_kind, condition_note, doc_ref
                                  FROM gov_authority_limits
                                 WHERE active = 1 AND code IN (" . implode(',', $in) . ")");
            while ($r && ($x = $r->fetch_assoc())) { $out[] = $x; }
        } catch (\Throwable $t) { return array(); }
        return $out;
    }

    /** مجالاتُ البياناتِ التي يفتحها القالبُ — رموزُ مساحاتٍ أو إدارات. */
    function ems_profile_scopes($conn, $userId = null)
    {
        return array_keys(ems_profile_layer($conn, 'scope', $userId));
    }

    /**
     * أيصل هذا الفاعلُ مساحةَ عملٍ بعينِها؟ — بندُ `scope` في قالبِه.
     *
     * ◆ **ويضيّق ولا يوسّع**: قالبٌ **بلا** بندِ مجالٍ يعني «لم تُنقَل هذه
     *   الطبقةُ لهذا الدورِ بعد» فيبقى الحالُ كما هو؛ وقالبٌ **له** بنودُ مجالٍ
     *   يُحصر فيها حصرًا. فالتشديدُ يقع حين يُؤلَّف البندُ لا قبلَه.
     * ◆ **ومقدارُ ما يُقاس عليه المساحةُ الحاكمة** (`nav_workspace_placements`)
     *   لا نصُّ مسارٍ — فالموضعُ سجلٌّ لا اشتقاق.
     */
    function ems_workspace_allowed($conn, $wsId, $userId = null)
    {
        $scopes = ems_profile_scopes($conn, $userId);
        if (!$scopes) { return true; }
        return in_array((string) $wsId, $scopes, true);
    }

    /**
     * خريطةُ المسارِ إلى مساحتِه الحاكمة — تُقرأ مرّةً لكلِّ طلب.
     *
     * @return array<string,string> route(بلا لاحقة) ⇒ workspace_id
     */
    function ems_route_workspace_map($conn)
    {
        static $map = null;
        if ($map !== null) { return $map; }
        $map = array();
        try {
            $r = @$conn->query("SELECT route, workspace_id FROM nav_workspace_placements
                                 WHERE status = 'ACTIVE'");
            while ($r && ($x = $r->fetch_assoc())) {
                $map[strtolower((string) $x['route'])] = (string) $x['workspace_id'];
            }
        } catch (\Throwable $t) { $map = array(); }
        return $map;
    }

    /**
     * أيرى هذا الفاعلُ حقلًا حسّاسًا؟ — بندُ `field` في قالبِه.
     *
     * ⛔ **ولا يوسِّع سياسةَ الحقلِ بل يضيّقها**: الحقلُ الذي لا سياسةَ له ليس
     *   حسّاسًا فلا يُسأل عنه هنا؛ والحقلُ الحسّاسُ يلزمه **بندٌ في القالب**
     *   فوقَ ما تسمح به سياستُه. فالطبقتان تتقاطعان ولا تُغني إحداهما.
     */
    function ems_field_allowed($conn, $fieldCode, $userId = null)
    {
        $fieldCode = trim((string) $fieldCode);
        if ($fieldCode === '') { return false; }
        $set = ems_profile_layer($conn, 'field', $userId);
        return isset($set[$fieldCode]);
    }

    /**
     * مفرداتُ نوعٍ كما يقرؤها السجلُّ الحاكم — لبناءِ قوائمِ الكونسول.
     *
     * ◆ **والشاشةُ تختار من المُعلَنِ ولا تكتب نصًّا حرًّا**: بندٌ برمزٍ لا
     *   يقابل مدخلًا حاكمًا لا يحكم شيئًا ويُقرأ ضمانًا وهو فراغ.
     *
     * @return array<int,array{ref:string,label:string}>
     */
    function ems_layer_vocabulary($conn, $kind)
    {
        $out = array();
        try {
            if ($kind === 'action') {
                foreach (array_keys(ems_action_vocabulary($conn)) as $c) {
                    $out[] = array('ref' => $c, 'label' => $c);
                }
            } elseif ($kind === 'cap') {
                $r = @$conn->query("SELECT code, subject_role FROM gov_authority_limits
                                     WHERE active = 1 AND code <> '' GROUP BY code, subject_role
                                     ORDER BY code");
                while ($r && ($x = $r->fetch_assoc())) {
                    $out[] = array('ref' => (string) $x['code'], 'label' => (string) $x['subject_role']);
                }
            } elseif ($kind === 'field') {
                /* ◆ **والملغاةُ لا تُعرَض**: سياسةٌ حالُها «ملغاة» حكمٌ مسجَّلٌ
                     بإسقاطِها، فربطُ قالبٍ بها يُحيي ما أُسقط. */
                $r = @$conn->query("SELECT field_code, classification, status
                                      FROM sensitive_field_policies
                                     WHERE status = 'نافذة' ORDER BY field_code");
                while ($r && ($x = $r->fetch_assoc())) {
                    $out[] = array('ref' => (string) $x['field_code'],
                                   'label' => (string) $x['classification']);
                }
            } elseif ($kind === 'scope') {
                $r = @$conn->query("SELECT DISTINCT workspace_id FROM nav_workspace_placements
                                     WHERE status = 'ACTIVE' ORDER BY workspace_id");
                while ($r && ($x = $r->fetch_row())) {
                    $out[] = array('ref' => (string) $x[0], 'label' => (string) $x[0]);
                }
            }
        } catch (\Throwable $t) { return $out; }
        return $out;
    }
}
