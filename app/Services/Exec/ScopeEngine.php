<?php
namespace App\Services\Exec;

/* **حلّالُ السلطةِ التنظيميُّ قائمٌ من `ORG-01`** — يُستدعى ولا يُعاد بناؤه. */
require_once __DIR__ . '/../Org/OrgAuthorityResolver.php';

use App\Core\TenantDb;

/**
 * app/Services/Exec/ScopeEngine.php — محرّكُ النطاقِ والسلطة (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **محرّكٌ واحدٌ لا ثلاثة** (‏§٤-٣ من نصِّ المرحلة): الرئيسُ والنائبُ والموظّفُ
 *   يمرُّون بهذا المحرّكِ نفسِه، ويختلف **النطاقُ الافتراضيُّ** لا الشيفرة.
 *   ⛔ **ولا نظامَ اعتمادٍ مستقلٍّ لكلِّ نائب.**
 *
 * ◆ **والرؤيةُ لا تساوي السلطة** (‏الأمرُ الأوّل · البند 27 · قيدُ المالك §٢):
 *   دالّتانِ منفصلتانِ لا دالّةٌ واحدة —
 *   `visibility()` تجيب **ماذا يرى**، و`authority()` تجيب **ماذا يستطيع أن
 *   يقرّر**. والرئيسُ قد يرى الشركةَ كلَّها و`authority()` تردُّه عن معاملةٍ
 *   بعينِها لأنَّ سلطتَها ليست له. **والدمجُ بينهما هو العطبُ نفسُه** الذي
 *   جعل مكتبَ الرئيسِ يملك معاملاتِ الإدارات.
 *
 * ◆ **والسلطةُ سلسلةٌ لا رابطٌ ظاهر** (‏البنود 20–35 · قيدُ المالك §٤):
 *   `Identity → Company → Department → Project → Site → Record → Field →
 *    Action → State → Authority → Delegation` — وكلُّ حلقةٍ تُقاس، ⛔ **ولا
 *   يكفي أنَّ الرابطَ ظاهرٌ في القائمة**.
 *
 * ◆ **والسلطةُ تُقرأ من سجلِّها لا من ثابتٍ في الشيفرة**: `gov_authority_limits`
 *   و`gov_authority_grants` و`gov_delegations` و`fin_approval_matrix`.
 *   ⛔ **ولا اسمَ شخصٍ صلبًا** — `Role + Scope` لا `فلانٌ يعتمد`.
 *   ⛔ **ولا عتبةٌ رقميّةٌ مكتوبةٌ هنا** — المصفوفةُ تُقرأ ولا تُخترَع، وغيابُها
 *   يردُّ برمزِ `AUTHORITY_NOT_CONFIGURED` ولا يُفترَض.
 *
 * ◆ **والتفويضُ منتهي المدّةِ ليس تفويضًا**: `valid_to` و`revoked_at` يُقرآنِ
 *   في كلِّ نداءٍ — فالتفويضُ حالةٌ لا صفّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class ScopeEngine
{
    /** المساحاتُ الثلاثُ — رمزُها المعياريُّ من `repair01_departments`. */
    const SPACE_CEO = 'EX-CEO';
    const SPACE_VP  = 'EX-DVP';
    const SPACE_MY  = 'WS-MY';

    /** رموزُ الردِّ — تُقارَن في الشيفرةِ ولا تُعرَض للمستخدم. */
    const OK                       = 'OK';
    const DENIED_NO_AUTHORITY      = 'DENIED_NO_AUTHORITY';
    const DENIED_OUT_OF_SCOPE      = 'DENIED_OUT_OF_SCOPE';
    const DENIED_DELEGATION_EXPIRED = 'DENIED_DELEGATION_EXPIRED';
    const AUTHORITY_NOT_CONFIGURED = 'AUTHORITY_NOT_CONFIGURED';

    /**
     * **ماذا يرى** — النطاقُ المرئيُّ لا المخوَّل.
     *
     * يعيد: `space` · `all_departments` · `projects` (‏فارغةٌ = بلا حصر) ·
     *        `sites` · `chain` (‏حلقاتُ السلسلةِ المقيسةُ بالترتيب).
     *
     * ⛔ **ولا يُقرأ منه حقُّ فعل** — هذه رؤيةٌ وحدَها.
     */
    public static function visibility(\mysqli $conn, array $user)
    {
        $space   = self::spaceOf($conn, $user);
        $userId  = isset($user['id']) ? (int) $user['id'] : 0;
        $company = isset($user['company_id']) ? (int) $user['company_id'] : 0;

        $chain = array('Identity' => $userId, 'Company' => $company);

        if ($space === self::SPACE_CEO) {
            $chain['Department'] = 'ALL';
            $chain['Project']    = 'ALL';
            $chain['Site']       = 'ALL';
            return array(
                'space' => $space, 'user_id' => $userId, 'company_id' => $company,
                'all_departments' => true, 'departments' => array(),
                'projects' => array(), 'sites' => array(),
                'read_only_outside' => false, 'chain' => $chain,
            );
        }

        if ($space === self::SPACE_VP) {
            $dep = self::deputyDepartments($user, $userId);
            $prj = self::projectsOf($user, $userId);
            $chain['Department'] = $dep ? implode(',', $dep) : 'NONE';
            $chain['Project']    = $prj ? implode(',', $prj) : 'NONE';
            $chain['Site']       = 'BY_PROJECT';
            /* **الرؤيةُ أوسعُ من السلطةِ وظيفةً لا وصفًا** (‏VP-02): النائبُ يقرأ
               الشركةَ كلَّها ويقرّر في نطاقِه — فالعلَمُ `read_only_outside`
               يفصل القراءةَ عن القرارِ في الواجهةِ والخدمةِ معًا. */
            return array(
                'space' => $space, 'user_id' => $userId, 'company_id' => $company,
                'all_departments' => true, 'departments' => $dep,
                'projects' => $prj, 'sites' => array(),
                'read_only_outside' => true, 'chain' => $chain,
            );
        }

        $prj = self::projectsOf($user, $userId);
        $chain['Department'] = 'SELF';
        $chain['Project']    = $prj ? implode(',', $prj) : 'SELF';
        $chain['Site']       = 'BY_PROJECT';
        $chain['Record']     = 'OWN_ROWS_ONLY';
        return array(
            'space' => $space, 'user_id' => $userId, 'company_id' => $company,
            'all_departments' => false, 'departments' => array(),
            'projects' => $prj, 'sites' => array(),
            'read_only_outside' => true, 'chain' => $chain,
        );
    }

    /**
     * **ماذا يستطيع أن يقرّر** — السلطةُ من سجلِّها لا من الرؤية.
     *
     * @param string $actionKey رمزُ الفعلِ كما هو في `gov_authority_limits.action_codes`
     * @param array  $ctx       `amount` · `event_type` · `dept` · `project_id` — اختياريّة
     * @return array `verdict` · `rule_id` · `why` · `required_level`
     */
    public static function authority(\mysqli $conn, array $user, $actionKey, array $ctx = array())
    {
        $userId  = isset($user['id']) ? (int) $user['id'] : 0;
        $company = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        $roleId  = isset($user['role']) ? (int) $user['role'] : 0;

        /* ⓪ **حلّالُ السلطةِ التنظيميُّ أوّلًا — ولا محرّكَ ثانيًا** (‏§٤-٣):
              `OrgAuthorityResolver` قائمٌ من `ORG-01` ويحمل التكليفَ ونطاقَه
              وسقفَه ونائبَه وسقوطَه بانتهاءِ المدّة. فهذا المحرّكُ **يستدعيه
              ولا يعيد بناءَه** — ⛔ وبناءُ حلّالٍ ثانٍ هنا هو «ثلاثةُ أنظمة»
              بعينِها. وحين لا تكليفَ تنظيميًّا للشخصِ أصلًا ننزل إلى طبقةِ
              قواعدِ `AAM` أدناه، **ولا نفترض نعم**. */
        if ($userId > 0 && isset($ctx['scope_type'], $ctx['scope_id'])) {
            $personId = self::personOf($conn, $userId, $user);
            if ($personId > 0 && class_exists('\\App\\Services\\Org\\OrgAuthorityResolver')) {
                $org = \App\Services\Org\OrgAuthorityResolver::can($conn, $personId, $company, array(
                    'scope_type' => $ctx['scope_type'],
                    'scope_id'   => $ctx['scope_id'],
                    'capability' => $actionKey,
                    'amount'     => isset($ctx['amount']) ? $ctx['amount'] : null,
                ));
                if (!empty($org['ok'])) {
                    return array('verdict' => self::OK,
                                 'rule_id' => 'ORG-ASG-' . (int) $org['asg_id'],
                                 'why' => '', 'required_level' => '');
                }
                if ((int) $org['code'] === 409) {
                    return array('verdict' => self::DENIED_NO_AUTHORITY,
                                 'rule_id' => 'ORG-ASG-' . (int) $org['asg_id'],
                                 'why' => (string) $org['reason'], 'required_level' => '');
                }
            }
        }

        /* ① القاعدةُ في سجلِّ حدودِ السلطة — والغائبةُ لا تُفترَض.
              ⚠ **والقراءةُ عبرَ بوّابةِ العزلِ لا باستعلامٍ خامّ** (‏`FR-SEC-006`):
                الكيانُ يُحقَن ولا يُمرَّر، والمطابقةُ على رمزِ الفعلِ تقع في
                الشيفرةِ لا في `LIKE` — فالرمزُ الجزئيُّ يطابق رمزًا أطولَ منه. */
        $rule = null;
        foreach (self::gateSelect($user, 'gov_authority_limits',
                     array('where' => array('active' => 1), 'orderBy' => 'seq', 'limit' => 500)) as $cand) {
            $codes = array_map('trim', explode(',', (string) $cand['action_codes']));
            if (in_array((string) $actionKey, $codes, true)) { $rule = $cand; break; }
        }
        if (!$rule) {
            return array('verdict' => self::AUTHORITY_NOT_CONFIGURED, 'rule_id' => '',
                         'why' => 'لا قاعدة سلطة مسجلة لهذا الفعل', 'required_level' => '');
        }

        /* ② الممنوعُ صراحةً يسبق المسموح — والمنعُ لا يُستثنى بتفويض. */
        if ((string) $rule['forbidden'] !== '' && strpos((string) $rule['forbidden'], (string) $actionKey) !== false) {
            return array('verdict' => self::DENIED_NO_AUTHORITY, 'rule_id' => (string) $rule['code'],
                         'why' => 'الفعل ممنوع بقاعدة نافذة', 'required_level' => '');
        }

        /* ③ الدورُ في قائمةِ القاعدةِ — أو تفويضٌ سارٍ ينوب عنه. */
        $roles = array_filter(array_map('trim', explode(',', (string) $rule['role_ids'])));
        $byRole = $roles ? in_array((string) $roleId, $roles, true) : false;
        $byDeleg = false; $delegExpired = false;
        if (!$byRole) {
            $d = self::activeDelegation($user, $userId);
            if ($d['found']) { $byDeleg = true; }
            elseif ($d['expired']) { $delegExpired = true; }
        }
        if ($delegExpired) {
            return array('verdict' => self::DENIED_DELEGATION_EXPIRED, 'rule_id' => (string) $rule['code'],
                         'why' => 'التفويض منتهي المدة', 'required_level' => '');
        }
        if (!$byRole && !$byDeleg) {
            return array('verdict' => self::DENIED_NO_AUTHORITY, 'rule_id' => (string) $rule['code'],
                         'why' => 'الدور خارج قائمة القاعدة ولا تفويض ساري', 'required_level' => '');
        }

        /* ④ العتبةُ من مصفوفةِ الاعتمادِ حين يحمل السياقُ قيمة —
              ⛔ **ولا رقمَ يكتبه المبرمج**؛ وغيابُ الصفِّ يردُّ ولا يفترض. */
        $level = '';
        if (isset($ctx['amount']) && $ctx['amount'] !== null && $ctx['amount'] !== '') {
            $amt  = (float) $ctx['amount'];
            $etyp = isset($ctx['event_type']) ? (string) $ctx['event_type'] : 'any';
            $lv = null;
            foreach (self::gateSelect($user, 'fin_approval_matrix',
                         array('where' => array('active' => 1), 'orderBy' => 'sequence', 'limit' => 500)) as $row) {
                $et = (string) $row['event_type'];
                if ($et !== $etyp && $et !== 'any') { continue; }
                if ((float) $row['min_amount'] > $amt) { continue; }
                if ($row['max_amount'] !== null && (float) $row['max_amount'] < $amt) { continue; }
                $lv = $row; break;
            }
            if ($lv === null) {
                return array('verdict' => self::AUTHORITY_NOT_CONFIGURED, 'rule_id' => (string) $rule['code'],
                             'why' => 'لا مستوى معتمد لهذه القيمة في مصفوفة الاعتماد',
                             'required_level' => '');
            }
            $level = (string) $lv['required_level'];
        }

        return array('verdict' => self::OK, 'rule_id' => (string) $rule['code'],
                     'why' => '', 'required_level' => $level);
    }

    /**
     * قيدُ النطاقِ على قراءةٍ حيّة — يُحقَن في `where` لا في نصِّ الصفحة.
     * يعيد مصفوفةً فارغةً حين لا حصر (‏الرئيس)، فالقراءةُ تبقى بلا شرطٍ زائد.
     */
    public static function readWhere(array $vis, $projectCol = 'project_id')
    {
        if (!empty($vis['all_departments']) && empty($vis['projects'])) { return array(); }
        if (empty($vis['projects'])) { return array(); }
        return array($projectCol => $vis['projects']);
    }

    /**
     * المساحةُ التي ينتمي إليها المستخدم — **من سجلَّين قائمَين لا من اسمِه**.
     *
     * ◆ **القيادةُ من ملفِّ الدورِ الحَوكميّ**: `gov_role_profiles.dept_code`
     *   يحمل مكتبَ الرئيسِ والنوّاب، والدورُ الذي يقع تحته قياديّ.
     *
     * ◆ **والنيابةُ تكليفٌ بنطاقٍ لا صفٌّ في جدولِ الأدوار** — وهو نصُّ المالكِ
     *   حرفًا: `Deputy Role + Authority Scope`. فالنائبُ من يحمل تكليفَ إشرافٍ
     *   **معتمَدًا ساريًا بنطاقٍ مكتوب** — ⛔ **ولا اسمَ شخصٍ في شيفرة**،
     *   ولا دورَ ثابتٍ يُخترَع قبل أن يسمّيَ المالكُ نوّابَه.
     */
    public static function spaceOf(\mysqli $conn, array $user)
    {
        $roleId = isset($user['role']) ? (string) $user['role'] : '';
        $userId = isset($user['id']) ? (int) $user['id'] : 0;
        $company = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        if ($roleId === '-1') { return self::SPACE_CEO; }
        $rid = (int) $roleId;
        if ($rid <= 0) { return self::SPACE_MY; }

        /* ① تكليفُ الإشرافِ السارِي يسبق ملفَّ الدور — فالنيابةُ حالةٌ لا صفة. */
        if ($userId > 0 && self::hasOversightAssignment($user, $userId)) {
            return self::SPACE_VP;
        }

        /* ② مساحةُ الدورِ من سجلِّها ثمَّ الجسرُ المعياريُّ إلى رمزِها —
              ⛔ **ولا رقمَ دورٍ مكتوبٌ في الشيفرة**: الاسمُ الحيُّ يتغيّر
              والرمزُ المعياريُّ لا يتغيّر، والجسرُ هو الذي يترجم. */
        $r = $conn->query("SELECT c.canonical_code
                             FROM gov_space_roles s
                             JOIN repair01_dept_crosswalk c ON c.legacy_name = s.space_ar
                            WHERE s.role_id = " . $rid . "
                              AND c.canonical_code IN ('" . self::SPACE_CEO . "','" . self::SPACE_VP . "')
                            ORDER BY c.canonical_code LIMIT 1");
        if ($r && $r->num_rows) {
            $cc = (string) $r->fetch_assoc()['canonical_code'];
            if ($cc === self::SPACE_CEO) { return self::SPACE_CEO; }
            if ($cc === self::SPACE_VP)  { return self::SPACE_VP; }
        }
        return self::SPACE_MY;
    }

    /* ── حلقاتُ السلسلة ─────────────────────────────────────────────────── */

    /**
     * **قراءةٌ عبرَ بوّابةِ العزلِ وحدَها** (‏`FR-SEC-006` · `GAP-29`).
     * ⛔ **ولا استعلامَ خامٍّ على جدولِ مستأجِرٍ في هذه الخدمة** — والكيانُ
     *   يُحقَن ولا يُمرَّر. والبوّابةُ تأتي مع الفاعلِ إن كان النداءُ من نظامٍ
     *   بلا جلسة، وإلّا فبوّابةُ الجلسة.
     */
    private static function gateSelect(array $user, $table, array $opt)
    {
        $gate = (isset($user['gate']) && $user['gate'] !== null) ? $user['gate'] : \ems_tenant_db();
        try { return $gate->select($table, $opt); }
        catch (\Throwable $t) { \error_log('w15 scope ' . $table . ': ' . $t->getMessage()); return array(); }
    }

    /** تكليفُ إشرافٍ معتمَدٌ سارٍ — ومنتهي المدّةِ ليس تكليفًا. */
    private static function hasOversightAssignment(array $user, $userId)
    {
        return count(self::oversightRows($user, $userId)) > 0;
    }

    /** صفوفُ تكليفِ الإشرافِ السارية — والفلترةُ في الشيفرةِ بعدَ قراءةِ البوّابة. */
    private static function oversightRows(array $user, $userId)
    {
        $out = array();
        $today = date('Y-m-d');
        foreach (self::gateSelect($user, 'exec_assignments',
                     array('where' => array('subject_user_id' => (int) $userId,
                                            'assignment_kind' => 'oversight',
                                            'state' => 'approved'),
                           'orderBy' => 'id DESC', 'limit' => 200)) as $x) {
            if ($x['revoked_at'] !== null) { continue; }
            if (trim((string) $x['scope_note']) === '') { continue; }
            if ($x['effective_to'] !== null && (string) $x['effective_to'] < $today) { continue; }
            $out[] = $x;
        }
        return $out;
    }

    private static function deputyDepartments(array $user, $userId)
    {
        $out = array();
        foreach (self::oversightRows($user, $userId) as $x) {
            $v = trim((string) $x['scope_note']);
            if ($v !== '' && !in_array($v, $out, true)) { $out[] = $v; }
        }
        return $out;
    }

    /** الشخصُ خلفَ الحساب — `users.employee_id` ثمّ سجلُّ الأشخاص (‏W03). */
    public static function personOf(\mysqli $conn, $userId, array $user = array())
    {
        $u = self::gateSelect($user, 'users', array('where' => array('id' => (int) $userId), 'limit' => 1));
        if (!$u) { return 0; }
        $emp = (int) $u[0]['employee_id'];
        if ($emp <= 0) { return 0; }
        $p = self::gateSelect($user, 'persons', array('where' => array('employee_id' => $emp), 'limit' => 1));
        return $p ? (int) $p[0]['person_id'] : 0;
    }

    /** مشاريعُ المستخدمِ — من عمودِ الربطِ في سجلِّ المستخدمين. */
    private static function projectsOf(array $user, $userId)
    {
        $out = array();
        $u = self::gateSelect($user, 'users', array('where' => array('id' => (int) $userId), 'limit' => 1));
        if (!$u) { return $out; }
        foreach (preg_split('/[,\s]+/', (string) $u[0]['project_id']) as $p) {
            $p = (int) $p;
            if ($p > 0) { $out[] = $p; }
        }
        return $out;
    }

    /** تفويضٌ سارٍ الآن — والمنتهي يُميَّز عن المعدوم فالردُّ يختلف. */
    private static function activeDelegation(array $user, $userId)
    {
        $expired = false;
        foreach (self::gateSelect($user, 'gov_delegations',
                     array('where' => array('to_user' => (int) $userId),
                           'orderBy' => 'valid_from DESC', 'limit' => 20)) as $x) {
            if ($x['revoked_at'] !== null) { $expired = true; continue; }
            if ($x['valid_to'] !== null && strtotime((string) $x['valid_to']) < time()) { $expired = true; continue; }
            return array('found' => true, 'expired' => false);
        }
        return array('found' => false, 'expired' => $expired);
    }
}
