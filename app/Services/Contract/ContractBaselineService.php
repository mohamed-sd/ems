<?php
/**
 * app/Services/Contract/ContractBaselineService.php — خطُّ الأساس (P-10)
 * ═══════════════════════════════════════════════════════════════════════════
 * الملحق §3-`P-10`: «**دورةُ حالة خط الأساس** (Draft→Reviewed→Approved→Locked→
 * Amended→Superseded) + علَمُ `EMS_BASELINE_GATE` **يمنع الفوترة قبل القفل**» ·
 * PLAN-03 §3.6: «عند الاعتماد **تُقفل كلُّ المكوّنات** — **ومن هنا فقط تبدأ
 * الفوترة**» · §9-⑱: «فوترةٌ قبل قفل خط الأساس: **تُرفض**».
 *
 * ── ⚠ والملحق §2-② مُلزِمٌ نصًّا ────────────────────────────────────────────
 * القاعدةُ **تسري على الجديد لا على القائم**. العقودُ العشرةُ القائمةُ **تُفوتر
 * كما هي**، والعلَمُ **يبدأ مطفأً**، ثم يُفعَّل **لعقدٍ رائدٍ واحدٍ** بعد اكتمال
 * خط أساسه، ثم يُعمَّم. **ولا يُقلب على الجميع دفعةً واحدة — فخُّ `E-08` نفسُه.**
 *
 * ── لماذا حالةٌ ثانيةٌ والعقدُ له حالة ──────────────────────────────────────
 * `contracts.contract_status` (H-02) **حالةُ العلاقة التجارية**: نافذٌ · معلَّقٌ ·
 * منتهٍ. وهذه **حالةُ اكتمال المكوّنات**. فعقدٌ «نافذ» قد لا يكون له **بندُ بيعٍ
 * واحد** — **ويُفوتر اليوم**. والخلطُ بينهما يجعل «الاعتماد» كلمةً بمعنيين.
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **قائمةُ سماحٍ لا قائمةُ منع** — نمطُ `H-02` حرفيًّا: ما لم يُذكر **مرفوض**.
 * ② **ولا قفلَ بفجوة** — `readiness()` تعدّ المكوّنات الستة، والقفلُ **422
 *    بالفجوات مسمّاةً**.
 * ③ **والقفلُ يُثبّت ما قُفل** — بصمةٌ `sha1` لحالة المكوّنات، فيُعرف إن تغيّر
 *    شيءٌ بعده.
 * ④ **والبوابةُ تبدأ مطفأة** — و`monitor` تسجّل ولا تمنع، و`enforce` **لعقودٍ
 *    مسمّاةٍ فقط**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/ContractLineService.php';

class ContractBaselineService
{
    const STATES = array('draft', 'reviewed', 'approved', 'locked', 'amended', 'superseded');

    const STATE_AR = array(
        'draft' => 'مسودة', 'reviewed' => 'مُراجَع', 'approved' => 'معتمَد',
        'locked' => 'مقفل', 'amended' => 'مُعدَّل بملحق', 'superseded' => 'مُستبدَل',
    );

    /** **قائمةُ سماحٍ لا منع** — وما لم يُذكر هنا مرفوضٌ (نمطُ H-02). */
    const ALLOWED = array(
        'draft'      => array('reviewed'),
        'reviewed'   => array('approved', 'draft'),
        'approved'   => array('locked', 'reviewed'),
        'locked'     => array('amended'),
        'amended'    => array('superseded'),
        'superseded' => array(),
    );

    /** المكوّناتُ الستةُ التي يقفل عليها خطُّ الأساس. */
    const COMPONENTS = array(
        'lines' => 'بنودُ البيع (P-02)',
        'monthly_plan' => 'الجدولُ الشهري (P-03)',
        'plan_sealed' => 'ختمُ الجدول — Σ = المتعاقَد',
        'resource_plan' => 'خطةُ الموارد (P-04)',
        'payment_schedule' => 'خطةُ الدفع (P-05)',
        'sites' => 'نطاقُ التنفيذ (P-01)',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① الجاهزية — والفجوةُ تُسمّى
    // ═════════════════════════════════════════════════════════════════════

    /**
     * جاهزيةُ المكوّنات الستة — **قبل الاعتماد لا بعده**.
     * @return array{ok:bool,components:array,gaps:array,counts:array,fingerprint:string,note:string}
     */
    public static function readiness($gate, $contractId)
    {
        $o = array('ok' => false, 'components' => array(), 'gaps' => array(),
                   'counts' => array(), 'fingerprint' => '', 'note' => '');
        $contractId = (int) $contractId;
        $lines = ContractLineService::linesOf($gate, $contractId, false);
        $nLines = count($lines);
        $o['counts']['lines'] = $nLines;
        $o['components']['lines'] = ($nLines > 0);
        if ($nLines === 0) { $o['gaps'][] = self::COMPONENTS['lines'] . ': **لا بندَ بيعٍ نافذ**'; }

        $months = 0; $sealed = 0; $unsealed = array();
        foreach ($lines as $l) {
            $n = self::countRows($gate, 'contract_monthly_plan', 'line_id', (int) $l['id']);
            $months += $n;
            if ($l['plan_sealed_version'] !== null) { $sealed++; }
            else { $unsealed[] = '#' . (int) $l['line_no']; }
        }
        $o['counts']['plan_months'] = $months;
        $o['counts']['plan_sealed'] = $sealed;
        $o['components']['monthly_plan'] = ($nLines > 0 && $months > 0);
        if ($nLines > 0 && $months === 0) {
            $o['gaps'][] = self::COMPONENTS['monthly_plan'] . ': **لا شهرَ مخطَّطٌ واحد**';
        }
        $o['components']['plan_sealed'] = ($nLines > 0 && $sealed === $nLines);
        if ($nLines > 0 && $sealed < $nLines) {
            $o['gaps'][] = self::COMPONENTS['plan_sealed'] . ': **' . count($unsealed)
                . ' بندًا غيرَ مختوم** (' . implode(' · ', $unsealed) . ')';
        }

        $res = 0;
        foreach ($lines as $l) { $res += self::countRows($gate, 'contract_resource_plan', 'line_id', (int) $l['id']); }
        $o['counts']['resource_rows'] = $res;
        // خطةُ الموارد **ليست شرطًا لكلِّ عقد** (المقطوعُ لا طاقةَ له) — فتُعلَن
        // ولا تمنع، وهذا فرقٌ مقصودٌ بين «ناقص» و«غيرِ منطبق».
        $o['components']['resource_plan'] = true;

        $pay = self::countRows($gate, 'contract_payment_schedule', 'contract_id', $contractId, "AND t.effective_to IS NULL");
        $o['counts']['payment_rows'] = $pay;
        $o['components']['payment_schedule'] = ($pay > 0);
        if ($pay === 0) { $o['gaps'][] = self::COMPONENTS['payment_schedule'] . ': **لا خطةَ دفعٍ نافذة**'; }

        $sites = self::countRows($gate, 'contract_operational_sites', 'contract_id', $contractId,
                                 "AND COALESCE(t.is_deleted,0)=0");
        $o['counts']['sites'] = $sites;
        $o['components']['sites'] = ($sites > 0);
        if ($sites === 0) { $o['gaps'][] = self::COMPONENTS['sites'] . ': **لا نطاقَ تنفيذٍ مسجَّل**'; }

        $o['ok'] = empty($o['gaps']);
        $o['fingerprint'] = sha1(json_encode(array($contractId, $o['counts'])));
        $o['note'] = $o['ok']
            ? '**المكوّناتُ مكتملة** — ' . $nLines . ' بندًا · ' . $months . ' شهرًا · '
              . $pay . ' سطرَ دفعٍ · ' . $sites . ' نطاقًا'
            : '**' . count($o['gaps']) . ' فجوةً**: ' . implode(' · ', $o['gaps']);
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الدورة
    // ═════════════════════════════════════════════════════════════════════

    /** خطُّ الأساس النافذُ لعقد — ويُنشأ مسودةً عند أول طلب. */
    public static function current($gate, $contractId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('b' => 'contract_baseline')),
                "SELECT b.* FROM contract_baseline b
                  WHERE {TENANT_SCOPE} AND b.contract_id = ? AND COALESCE(b.is_deleted,0)=0
                    AND b.state <> 'superseded'
                  ORDER BY b.version DESC LIMIT 1", array((int) $contractId));
            return $r ? $r[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function open($conn, $gate, $companyId, $contractId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => 0, 'version' => 0);
        $cur = self::current($gate, (int) $contractId);
        if ($cur) {
            $out['ok'] = true; $out['code'] = 200; $out['id'] = (int) $cur['id'];
            $out['version'] = (int) $cur['version'];
            $out['reason'] = 'خطُّ أساسٍ قائمٌ بالنسخة ' . (int) $cur['version']
                           . ' وحالُه «' . self::STATE_AR[(string) $cur['state']] . '» — **فعلٌ عاطل**';
            return $out;
        }
        try {
            $id = (int) $gate->insert('contract_baseline', array(
                'contract_id' => (int) $contractId, 'version' => 1, 'state' => 'draft',
                'created_by' => (int) $actor ?: null));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'baseline_open', $id, array(), array('contract' => $contractId));
        $out['ok'] = true; $out['code'] = 200; $out['id'] = $id; $out['version'] = 1;
        $out['reason'] = 'فُتح خطُّ الأساس **مسودةً**';
        return $out;
    }

    /**
     * انتقالٌ في الدورة — **قائمةُ سماحٍ لا منع**.
     * @return array{ok:bool,code:int,reason:string,state:string}
     */
    public static function transition($conn, $gate, $companyId, $contractId, $to, $actor, $note = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => '');
        $to = (string) $to;
        if (!in_array($to, self::STATES, true)) {
            $out['code'] = 422; $out['reason'] = 'حالٌ غيرُ معروف: ' . $to; return $out;
        }
        $b = self::current($gate, (int) $contractId);
        if (!$b) { $out['code'] = 404; $out['reason'] = 'لا خطَّ أساسٍ لهذا العقد — افتحه أولًا'; return $out; }
        $from = (string) $b['state'];
        $out['state'] = $from;
        if ($from === $to) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'الحالُ «' . self::STATE_AR[$to] . '» كما هو — **فعلٌ عاطل**'; return $out;
        }
        $allowed = isset(self::ALLOWED[$from]) ? self::ALLOWED[$from] : array();
        if (!in_array($to, $allowed, true)) {
            $out['code'] = 422;
            $out['reason'] = '**انتقالٌ غيرُ مشروع**: ' . self::STATE_AR[$from] . ' ← ' . self::STATE_AR[$to]
                . ' — والمشروعُ من هنا: '
                . ($allowed ? implode(' · ', array_map(function ($s) { return self::STATE_AR[$s]; }, $allowed))
                            : '**لا شيء (نهائية)**');
            return $out;
        }

        $data = array('state' => $to,
                      'state_note' => (trim((string) $note) === '') ? null : mb_substr(trim((string) $note), 0, 255));
        $now = date('Y-m-d H:i:s');
        if ($to === 'reviewed') { $data['reviewed_by'] = (int) $actor ?: null; $data['reviewed_at'] = $now; }
        if ($to === 'approved') {
            // ولا يعتمد المرءُ ما راجع — **يدان لا يدٌ واحدة** (نظيرُ المستخلص)
            if ((int) $b['reviewed_by'] > 0 && (int) $b['reviewed_by'] === (int) $actor) {
                $out['code'] = 422;
                $out['reason'] = '**لا يعتمد خطَّ الأساس من راجعه** — الاعتمادُ يدٌ ثانية'; return $out;
            }
            $data['approved_by'] = (int) $actor ?: null; $data['approved_at'] = $now;
        }
        if ($to === 'locked') {
            // ② **ولا قفلَ بفجوة**
            $r = self::readiness($gate, (int) $contractId);
            if (!$r['ok']) {
                $out['code'] = 422;
                $out['reason'] = '**لا يُقفل خطُّ أساسٍ بفجوة** — ' . $r['note'];
                return $out;
            }
            $data['locked_by'] = (int) $actor ?: null; $data['locked_at'] = $now;
            $data['fingerprint'] = $r['fingerprint'];
            $data['comp_lines'] = (int) $r['counts']['lines'];
            $data['comp_plan_months'] = (int) $r['counts']['plan_months'];
            $data['comp_plan_sealed'] = (int) $r['counts']['plan_sealed'];
            $data['comp_resource_rows'] = (int) $r['counts']['resource_rows'];
            $data['comp_payment_rows'] = (int) $r['counts']['payment_rows'];
            $data['comp_sites'] = (int) $r['counts']['sites'];
        }
        if (in_array($to, array('amended', 'superseded'), true) && trim((string) $note) === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببُ التعديل إلزامي** — ولا يُفتح ملحقٌ صامتًا'; return $out;
        }

        try { $gate->update('contract_baseline', $data, array('id' => (int) $b['id'])); }
        catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الانتقال: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'baseline_state', (int) $b['id'],
            array('state' => $from), array('state' => $to, 'note' => $note));
        $out['ok'] = true; $out['code'] = 200; $out['state'] = $to;
        $out['reason'] = 'صار خطُّ الأساس «' . self::STATE_AR[$to] . '»'
            . ($to === 'locked' ? ' — **ومن هنا تبدأ الفوترة**' : '');
        return $out;
    }

    /** ملحقٌ يفتح **نسخةً جديدةً** والقديمةُ تُستبدل — والقديمةُ تبقى. */
    public static function amend($conn, $gate, $companyId, $contractId, $reason, $amendmentId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'version' => 0);
        $b = self::current($gate, (int) $contractId);
        if (!$b) { $out['code'] = 404; $out['reason'] = 'لا خطَّ أساسٍ لهذا العقد'; return $out; }
        if ((string) $b['state'] !== 'locked') {
            $out['code'] = 409;
            $out['reason'] = '**لا يُعدَّل إلا مقفل** — وحالُه «' . self::STATE_AR[(string) $b['state']] . '»';
            return $out;
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            $out['code'] = 422; $out['reason'] = '**سببُ الملحق إلزامي**'; return $out;
        }
        $old = (int) $b['version'];
        try {
            $gate->runInTransaction(function ($g) use ($b, $contractId, $reason, $amendmentId, $actor, $old) {
                $g->update('contract_baseline',
                    array('state' => 'amended', 'state_note' => mb_substr($reason, 0, 255)),
                    array('id' => (int) $b['id']));
                $g->update('contract_baseline', array('state' => 'superseded'), array('id' => (int) $b['id']));
                $g->insert('contract_baseline', array(
                    'contract_id' => (int) $contractId, 'version' => $old + 1, 'state' => 'draft',
                    'state_note' => mb_substr($reason, 0, 255),
                    'amendment_id' => ((int) $amendmentId > 0) ? (int) $amendmentId : null,
                    'supersedes_baseline_id' => (int) $b['id'],
                    'created_by' => (int) $actor ?: null));
            }, 'ملحق خط أساس ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الملحق: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'baseline_amend', (int) $b['id'],
            array('version' => $old), array('version' => $old + 1, 'reason' => $reason));
        $out['ok'] = true; $out['code'] = 200; $out['version'] = $old + 1;
        $out['reason'] = 'فُتحت النسخة ' . ($old + 1) . ' **مسودةً** — **والنسخة ' . $old
                       . ' مُستبدَلةٌ وباقيةٌ بسببها**';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ البوابة — **وتبدأ مطفأة**
    // ═════════════════════════════════════════════════════════════════════

    /** وضعُ البوابة: `off` (افتراضًا) · `monitor` · `enforce`. */
    public static function gateMode()
    {
        if (!function_exists('ems_env')) { return 'off'; }
        $m = strtolower(trim((string) ems_env('EMS_BASELINE_GATE', 'off')));
        return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'off';
    }

    /** العقودُ الرائدةُ المسمّاة — **ولا يُقلب على الجميع دفعةً واحدة**. */
    public static function pilotContracts()
    {
        if (!function_exists('ems_env')) { return array(); }
        $raw = trim((string) ems_env('EMS_BASELINE_GATE_CONTRACTS', ''));
        if ($raw === '') { return array(); }
        $o = array();
        foreach (explode(',', $raw) as $p) {
            $p = (int) trim($p);
            if ($p > 0) { $o[] = $p; }
        }
        return $o;
    }

    /**
     * **حارسُ الفوترة**: «فوترةٌ قبل قفل خط الأساس تُرفض» (§9-⑱) —
     * **بحدود §2-②**: مطفأٌ افتراضًا، ومُنفَّذٌ **لعقودٍ مسمّاةٍ فقط**.
     *
     * @return array{allow:bool,code:int,mode:string,reason:string,state:?string}
     */
    public static function billingGate($gate, $contractId)
    {
        $mode = self::gateMode();
        $b = self::current($gate, (int) $contractId);
        $state = $b ? (string) $b['state'] : null;
        $locked = ($state === 'locked');
        $o = array('allow' => true, 'code' => 200, 'mode' => $mode, 'state' => $state, 'reason' => '');

        if ($mode === 'off') {
            $o['reason'] = '**الحارسُ مطفأ** (§2-②: القاعدةُ تسري على الجديد لا على القائم)';
            return $o;
        }
        $pilot = self::pilotContracts();
        if (!in_array((int) $contractId, $pilot, true)) {
            $o['reason'] = '**العقدُ خارجَ الرائدة** — والحارسُ لا يُقلب على الجميع دفعةً واحدة (فخُّ E-08)';
            return $o;
        }
        if ($locked) {
            $o['reason'] = 'خطُّ الأساس **مقفل** — والفوترةُ من هنا تبدأ';
            return $o;
        }
        if ($mode === 'monitor') {
            $o['reason'] = '⚠ **مراقبة**: خطُّ الأساس غيرُ مقفل ('
                . ($state !== null ? self::STATE_AR[$state] : 'لا خطَّ أساسٍ أصلًا')
                . ') — **سُجّل ولم يُمنع**';
            self::log($contractId, $o['reason']);
            return $o;
        }
        $o['allow'] = false; $o['code'] = 423;
        $o['reason'] = '**لا فوترةَ قبل قفل خط الأساس** — حالُه '
            . ($state !== null ? self::STATE_AR[$state] : '**غيرُ مفتوحٍ أصلًا**')
            . ' (PLAN-03 §9-⑱)';
        self::log($contractId, $o['reason']);
        return $o;
    }

    public static function versionsOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('b' => 'contract_baseline')),
                "SELECT b.* FROM contract_baseline b
                  WHERE {TENANT_SCOPE} AND b.contract_id = ?
                  ORDER BY b.version DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function countRows($gate, $table, $col, $val, $extra = '')
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('t' => $table)),
                "SELECT COUNT(*) AS n FROM `" . $table . "` t
                  WHERE {TENANT_SCOPE} AND t.`" . $col . "` = ? " . $extra, array((int) $val));
            return $r ? (int) $r[0]['n'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    private static function log($contractId, $msg)
    {
        error_log('[EMS_BASELINE_GATE] contract=' . (int) $contractId . ' — ' . $msg);
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_baseline', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
