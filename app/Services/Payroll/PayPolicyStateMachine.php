<?php
/**
 * app/Services/Payroll/PayPolicyStateMachine.php — آلةُ حالات سياسة الأجر (E-24)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-06 §8.2 (State السياسة) نصًّا: «آلةُ السياسة: **Draft → Active (بسريانٍ
 * UQ) → Superseded (بسياسةٍ أحدث) → Expired** — و**لا تعديلَ رجعيًّا**: أثرُ
 * الماضي بحكم سياسته النافذة يومَها».
 * §8.3-W4: «سياسةٌ جديدةٌ بسريان أول الشهر ← **Active جديدة وSuperseded
 * للقديمة** · **أثرُ ما قبل السريان بالقديمة (لا رجعية)**».
 *
 * ── أربعُ قواعدَ ─────────────────────────────────────────────────────────────
 * ① **قائمةُ سماحٍ لا قائمةُ منع** (نمط H-02): ما لم يُكتب انتقالًا مرفوضٌ.
 * ② **المسودةُ لا تسعّر**: القارئان يستثنيان `draft` — و«الوصلُ في موضعين
 *    وإلا فالحارسُ زخرفة».
 * ③ **والتفعيلُ يُخلِف ولا يمحو**: القديمةُ تُغلق عند **سريان الجديدة − يوم**
 *    وتصير `superseded` **بخَلَفها مسمًّى** — فأثرُ ما قبل السريان يبقى بحكمها.
 * ④ **ولا تعديلَ على نافذة**: التحريرُ للمسودة وحدَها، و«سياسةٌ جديدةٌ بسريانٍ
 *    لا تعديلٌ رجعي».
 *
 * وقاعدةُ القراءة الحاسمة: `superseded` و`expired` **تُقرآن** — القراءةُ
 * بالتاريخ، وإسقاطُهما يفقد حكمَ الشهر الماضي وهو عينُ ما تمنعه «لا رجعية».
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

class PayPolicyStateMachine
{
    const DRAFT      = 'draft';
    const ACTIVE     = 'active';
    const SUPERSEDED = 'superseded';
    const EXPIRED    = 'expired';

    const ALL = array(self::DRAFT, self::ACTIVE, self::SUPERSEDED, self::EXPIRED);

    /** **قائمةُ سماح** — ما ليس فيها مرفوض. */
    const TRANSITIONS = array(
        self::DRAFT      => array(self::ACTIVE),
        self::ACTIVE     => array(self::SUPERSEDED, self::EXPIRED),
        self::SUPERSEDED => array(self::EXPIRED),
        self::EXPIRED    => array(),   // نهائيةٌ بلا رجوع
    );

    /** ما **يُقرأ** في الاحتساب — والمسودةُ وحدَها خارجه. */
    const READABLE = array(self::ACTIVE, self::SUPERSEDED, self::EXPIRED);

    const LABELS_AR = array(
        self::DRAFT => 'مسودة', self::ACTIVE => 'نافذة',
        self::SUPERSEDED => 'مستبدَلة', self::EXPIRED => 'منتهية',
    );

    /** الأسسُ التي يفهمها المحرّك (§8.2) — و`composite` بلا صيغةٍ بعد. */
    const BASES = array('actual', 'standby', 'attendance', 'ton', 'trip', 'meter', 'composite');

    // ═════════════════════════════════════════════════════════════════════
    // ① قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function labelAr($state)
    {
        $s = (string) $state;
        return isset(self::LABELS_AR[$s]) ? self::LABELS_AR[$s] : $s;
    }

    public static function canTransition($from, $to)
    {
        $from = (string) $from; $to = (string) $to;
        if (!isset(self::TRANSITIONS[$from])) { return false; }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    public static function isReadable($state)
    {
        return in_array((string) $state, self::READABLE, true);
    }

    /** التحريرُ للمسودة وحدَها — «لا تعديلَ رجعيًّا». */
    public static function isEditable($state)
    {
        return (string) $state === self::DRAFT;
    }

    /**
     * شرطُ الاستثناء الواحد الذي يستهلكه القارئان — **تعريفٌ واحدٌ لا نسختان**.
     * @param string $alias اسمُ الجدول أو لقبُه في الاستعلام
     */
    public static function sqlReadable($alias = 'p')
    {
        $a = preg_replace('/[^A-Za-z0-9_]/', '', (string) $alias);
        return "({$a}.policy_state <> '" . self::DRAFT . "')";
    }

    public static function policyOf($gate, $id)
    {
        try { return $gate->selectOne('contract_hour_policies', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② التفعيل — وبه يقع الإخلاف
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,superseded:array}
     */
    public static function activate($conn, $gate, $companyId, $policyId, $actor, $note = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'superseded' => array());
        $p = self::policyOf($gate, (int) $policyId);
        if (!$p) { $out['code'] = 404; $out['reason'] = 'السياسةُ غيرُ موجودةٍ في نطاقك'; return $out; }

        $from = (string) $p['policy_state'];
        if ($from === self::ACTIVE) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'السياسةُ نافذةٌ سلفًا'; return $out;
        }
        if (!self::canTransition($from, self::ACTIVE)) {
            $out['code'] = ($from === self::EXPIRED) ? 423 : 422;
            $out['reason'] = 'لا تفعيلَ من «' . self::labelAr($from) . '»'
                . ($from === self::EXPIRED ? ' — نهائيةٌ بلا رجوع؛ **سياسةٌ جديدةٌ بسريانٍ جديد**' : '');
            return $out;
        }

        // ── «بسريانٍ UQ»: بلا تاريخٍ لا يُعرف أيُّ ماضٍ يحكمه ──────────────
        $from_ = $p['effective_from'] !== null ? (string) $p['effective_from'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_)) {
            $out['code'] = 422;
            $out['reason'] = '**تاريخُ السريان إلزاميٌّ للتفعيل** — «Active بسريانٍ UQ» (§8.2): '
                           . 'وبلا تاريخٍ لا يُعرف أيُّ ماضٍ تحكمه ولا ما تُخلِفه';
            return $out;
        }
        if ($p['effective_to'] !== null && (string) $p['effective_to'] < $from_) {
            $out['code'] = 422; $out['reason'] = 'نهايةُ السريان قبل بدايته'; return $out;
        }
        // صفُّ سياسة الأجر يلزمه معدلٌ وعملة — وصفُّ حكم الساعة يحكمه `ruling`
        if ((string) $p['party_scope'] === 'operator') {
            if ($p['rate'] === null || (float) $p['rate'] <= 0) {
                $out['code'] = 422;
                $out['reason'] = '**معدلُ الاستحقاق موجبٌ إلزامي** — سياسةٌ بلا معدلٍ لا تسعّر شيئًا';
                return $out;
            }
            if ($p['currency'] === null || trim((string) $p['currency']) === '') {
                $out['code'] = 422; $out['reason'] = '**العملةُ إلزامية** — ولا جمعَ عملتين'; return $out;
            }
            if (!in_array((string) $p['pay_basis'], self::BASES, true)) {
                $out['code'] = 422;
                $out['reason'] = 'أساسُ الاستحقاق خارج السبعة (§8.2): ' . (string) $p['pay_basis'];
                return $out;
            }
        }
        if ($p['min_amount'] !== null && $p['max_amount'] !== null
            && (float) $p['min_amount'] > (float) $p['max_amount']) {
            $out['code'] = 422; $out['reason'] = 'الحدُّ الأدنى يتجاوز الأقصى'; return $out;
        }

        // ── من تُخلِفهم: النافذاتُ بالمفتاح نفسِه المتداخلُ سريانُها ────────
        $peers = self::activePeers($gate, $p);
        foreach ($peers as $q) {
            if ((string) $q['effective_from'] === $from_) {
                $out['code'] = 409;
                $out['reason'] = 'سياسةٌ نافذةٌ #' . (int) $q['id'] . ' **بالسريان نفسِه** — '
                               . 'الجديدُ بسريانٍ جديدٍ لا بسريانٍ مكرر';
                return $out;
            }
        }

        $cut = date('Y-m-d', strtotime($from_ . ' -1 day'));
        $superseded = array();
        try {
            $gate->runInTransaction(function ($g) use ($p, $peers, $cut, $from_, $actor, $note, &$superseded) {
                foreach ($peers as $q) {
                    // «لا تخلف السياسةُ نفسَها» — حارسٌ في الخدمة لأن CHECK لا
                    // يقدر أن يشير إلى عمود auto_increment (مقيس)
                    if ((int) $q['id'] === (int) $p['id']) { continue; }
                    $upd = array(
                        'policy_state'     => self::SUPERSEDED,
                        'superseded_by'    => (int) $p['id'],
                        'state_changed_at' => date('Y-m-d H:i:s'),
                        'state_changed_by' => (int) $actor ?: null,
                        'state_note'       => mb_substr('أخلفتها السياسةُ #' . (int) $p['id']
                                              . ' بسريان ' . $from_, 0, 200),
                    );
                    // الإغلاقُ عند **سريان الجديدة − يوم**: ما قبله يبقى بحكمها
                    if ($q['effective_to'] === null || (string) $q['effective_to'] > $cut) {
                        $upd['effective_to'] = $cut;
                    }
                    $g->update('contract_hour_policies', $upd, array('id' => (int) $q['id']));
                    $superseded[] = (int) $q['id'];
                }
                $g->update('contract_hour_policies', array(
                    'policy_state'     => self::ACTIVE,
                    'state_changed_at' => date('Y-m-d H:i:s'),
                    'state_changed_by' => (int) $actor ?: null,
                    'state_note'       => mb_substr(trim((string) $note) !== '' ? (string) $note
                                          : 'تفعيلٌ بسريان ' . $from_, 0, 200),
                    'approved_at'      => date('Y-m-d H:i:s'),
                    'approved_by'      => (int) $actor ?: null,
                ), array('id' => (int) $p['id']));
            }, 'تفعيل سياسة أجر ' . (int) $p['id']);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التفعيل: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'activate', (int) $p['id'],
            array('policy_state' => $from),
            array('policy_state' => self::ACTIVE, 'superseded' => $superseded));
        $out['ok'] = true; $out['code'] = 200; $out['superseded'] = $superseded;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الإنهاء
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string} */
    public static function expire($conn, $gate, $companyId, $policyId, $reason, $actor, $asOf = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $p = self::policyOf($gate, (int) $policyId);
        if (!$p) { $out['code'] = 404; $out['reason'] = 'السياسةُ غيرُ موجودةٍ في نطاقك'; return $out; }

        $from = (string) $p['policy_state'];
        if ($from === self::EXPIRED) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'منتهيةٌ سلفًا'; return $out;
        }
        if (!self::canTransition($from, self::EXPIRED)) {
            $out['code'] = 422;
            $out['reason'] = 'لا إنهاءَ من «' . self::labelAr($from) . '» — والمسودةُ لم تحيَ لتنتهي';
            return $out;
        }
        $why = trim((string) $reason);
        if ($why === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببُ الإنهاء إلزامي** — وإنهاءٌ بلا سببٍ يترك حسابًا لا يُفسَّر';
            return $out;
        }
        $day = ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf))
               ? (string) $asOf : date('Y-m-d');

        $upd = array(
            'policy_state'     => self::EXPIRED,
            'state_changed_at' => date('Y-m-d H:i:s'),
            'state_changed_by' => (int) $actor ?: null,
            'state_note'       => mb_substr($why, 0, 200),
        );
        // المفتوحُ الطرفِ يُغلق باليوم — **والماضي المكتوبُ لا يُمسّ**.
        // ولا يسبق الإغلاقُ بدايتَه: سياسةٌ سريانُها لم يبدأ بعدُ تُغلق عند بدايتها.
        if ($p['effective_to'] === null) {
            $start = $p['effective_from'] !== null ? (string) $p['effective_from'] : '';
            $upd['effective_to'] = ($start !== '' && $start > $day) ? $start : $day;
        }

        try {
            $gate->update('contract_hour_policies', $upd, array('id' => (int) $policyId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإنهاء: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'expire', (int) $policyId,
            array('policy_state' => $from), array('policy_state' => self::EXPIRED, 'reason' => $why));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * كنسُ ما انقضى سريانُه — **تصريحٌ بما وقع** لا قرارٌ جديد.
     * @return int عددُ ما صُرِّح
     */
    public static function sweepExpired($gate, $asOf = null)
    {
        $day = ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf))
               ? (string) $asOf : date('Y-m-d');
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('p' => 'contract_hour_policies')),
                "SELECT p.id, p.effective_to FROM contract_hour_policies p
                  WHERE {TENANT_SCOPE} AND p.policy_state IN ('active','superseded')
                    AND p.effective_to IS NOT NULL AND p.effective_to < ?", array($day));
        } catch (\Throwable $t) { return 0; }
        $n = 0;
        foreach ($rows as $r) {
            try {
                $gate->update('contract_hour_policies', array(
                    'policy_state'     => self::EXPIRED,
                    'state_changed_at' => date('Y-m-d H:i:s'),
                    'state_note'       => 'انقضى سريانُها في ' . (string) $r['effective_to']
                                          . ' — تصريحٌ بما وقع لا قرارٌ جديد',
                ), array('id' => (int) $r['id']));
                $n++;
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'E-24 sweepExpired #'); error_log('E-24 sweepExpired #' . $r['id'] . ': ' . $t->getMessage()); }
        }
        return $n;
    }

    /** حارسُ التحرير — يستدعيه كلُّ مسارِ تعديل. */
    public static function guardEdit($gate, $policyId)
    {
        $p = self::policyOf($gate, (int) $policyId);
        if (!$p) { return array('ok' => false, 'code' => 404, 'reason' => 'السياسةُ غيرُ موجودة'); }
        if (!self::isEditable((string) $p['policy_state'])) {
            return array('ok' => false, 'code' => 423,
                'reason' => 'السياسةُ «' . self::labelAr((string) $p['policy_state'])
                    . '» — **لا تعديلَ رجعيًّا** (§8.2): أنشئ سياسةً جديدةً بسريانٍ جديدٍ تُخلِفها');
        }
        return array('ok' => true, 'code' => 200, 'reason' => '');
    }

    // ═════════════════════════════════════════════════════════════════════

    /** النافذاتُ بالمفتاح نفسِه التي يتداخل سريانُها مع سريان الجديدة. */
    private static function activePeers($gate, $p)
    {
        $isOperator = ((string) $p['party_scope'] === 'operator');
        $sql = "SELECT q.id, q.effective_from, q.effective_to FROM contract_hour_policies q
                 WHERE {TENANT_SCOPE} AND q.id <> ? AND q.policy_state = 'active'
                   AND q.party_scope = ?
                   AND (q.effective_to IS NULL OR q.effective_to >= ?)
                   AND (q.effective_from IS NULL OR q.effective_from <= ?)
                   AND ";
        $params = array((int) $p['id'], (string) $p['party_scope'],
                        (string) $p['effective_from'], (string) $p['effective_from']);
        if ($isOperator) {
            $sql .= "q.operator_id <=> ? AND q.work_model <=> ? AND q.pay_basis <=> ?
                     AND q.scope_type <=> ? AND q.scope_id <=> ?";
            $params[] = $p['operator_id']; $params[] = $p['work_model']; $params[] = $p['pay_basis'];
            $params[] = $p['scope_type'];  $params[] = $p['scope_id'];
        } else {
            $sql .= "q.contract_ref <=> ? AND q.ops_state <=> ? AND q.obligation_type <=> ?";
            $params[] = $p['contract_ref']; $params[] = $p['ops_state']; $params[] = $p['obligation_type'];
        }
        $sql .= " ORDER BY q.effective_from DESC, q.id DESC";
        try {
            return $gate->scopedQuery(array('scope' => array('q' => 'contract_hour_policies')), $sql, $params);
        } catch (\Throwable $t) {
            error_log('E-24 activePeers: ' . $t->getMessage());
            return array();
        }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'contract_hour_policies', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
