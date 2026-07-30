<?php
/**
 * app/Services/Payroll/PayrollRunService.php — بوابةُ اللقطة (H-09-①)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §2:
 *  · «**بوابةٌ واحدة** — لا يقرأ أيُّ احتسابٍ جداولَ العقود مباشرةً».
 *  · «**كلُّ سطر احتسابٍ يحمل لقطتَه** ومرجعَها في العقد والملحق؛ فأيُّ تغيّرٍ
 *    لاحقٍ في العقد **لا يمسّ ما احتُسب**، ويُكشف أيُّ تلاعبٍ بمقارنة البصمة».
 * ENT-01 §5: «**Blocked بقائمة الموانع وروابطها — لا احتسابَ ناقصٌ صامت**».
 * ENT-01 §8-Validation: دورةٌ مكررةٌ **409** · فترةٌ مقفلة **423** · لقطةٌ
 * مفقودة ⇒ **Blocked** لا احتسابٌ افتراضي.
 *
 * ── حدودُ الشريحة ① (PLAN-01 §6.1) ─────────────────────────────────────────
 * هذه الشريحةُ **بوابةٌ لا محرّكُ احتساب**. تحتسب ما لا يحتاج زمنًا ولا إنتاجًا
 * (مبلغٌ ثابتٌ · نسبةٌ من الأساسي) وتُسجّل ما عداه سطرًا بحالة `pending_slice`
 * **معلَنًا بسببه** — بيتُه المسارُ الزمني ② والإنتاجي ③. فالنقصُ يُرى ولا
 * يُبتلع صفرًا.
 */

namespace App\Services\Payroll;

require_once dirname(__DIR__) . '/Contract/ContractSnapshotService.php';
require_once dirname(__DIR__) . '/Contract/EmployeeContractStateMachine.php';

use App\Services\Contract\ContractSnapshotService;
use App\Services\Contract\EmployeeContractStateMachine;

class PayrollRunService
{
    /** فئاتُ CON-01 §2 + `all`. */
    const CATEGORIES = array('all', 'permanent', 'project', 'operator', 'supplier_worker');

    /** حالاتُ ENT-01 §8 السبع نصًّا. */
    const STATES = array('Open', 'Calculated', 'Blocked', 'Review', 'Approved', 'Paid', 'Closed');

    /** ما يُحتسب في الشريحة ① — لا يحتاج زمنًا ولا إنتاجًا. */
    const SLICE1_METHODS = array('fixed_amount', 'pct_basic');

    /** المسارُ من فئة العقد (ENT-01 §3). */
    const PATH_BY_CATEGORY = array(
        'permanent' => 'institutional', 'project' => 'project',
        'operator' => 'project', 'supplier_worker' => 'project',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① فتحُ الدورة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,run_id:?int}
     */
    public static function openRun($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'run_id' => null);

        $from = isset($args['period_from']) ? trim((string) $args['period_from']) : '';
        $to   = isset($args['period_to']) ? trim((string) $args['period_to']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $out['code'] = 422; $out['reason'] = 'مدةُ الدورة إلزاميةٌ بتاريخين صالحين'; return $out;
        }
        if ($to < $from) { $out['code'] = 422; $out['reason'] = 'نهايةُ المدة قبل بدايتها'; return $out; }

        $cat = isset($args['category_filter']) ? trim((string) $args['category_filter']) : 'all';
        if (!in_array($cat, self::CATEGORIES, true)) {
            $out['code'] = 422; $out['reason'] = 'فئةٌ من خارج قائمة CON-01 §2'; return $out;
        }

        // ── فترةٌ مقفلة → 423 (ENT-01 §8 نصًّا · حارسُ M-39) ────────────────
        require_once dirname(__DIR__, 3) . '/includes/period_guard.php';
        $pg = ems_period_check($conn, $companyId, $to);
        if (empty($pg['ok'])) {
            $out['code'] = 423;
            $out['reason'] = 'فترةُ ' . $to . ' مقفلةٌ — لا تُفتح دورةُ مسيّرٍ عليها: ' . $pg['reason'];
            return $out;
        }

        // ── دورةٌ قائمةٌ للمفتاح → 409 **بمرجعها** ──────────────────────────
        $dup = self::runByKey($gate, $from, $to, $cat);
        if ($dup) {
            $out['code'] = 409;
            $out['reason'] = 'دورةٌ قائمةٌ لهذا المفتاح (#' . (int) $dup['id'] . ' · ' . $dup['state'] . ')';
            $out['run_id'] = (int) $dup['id'];
            return $out;
        }

        try {
            $out['run_id'] = (int) $gate->insert('payroll_runs', array(
                'period_from' => $from, 'period_to' => $to,
                'category_filter' => $cat,
                'project_filter' => isset($args['project_filter']) && (int) $args['project_filter'] > 0
                                    ? (int) $args['project_filter'] : null,
                'state' => 'Open',
                'note' => 'الشريحة ① — بوابةُ اللقطة (الاحتسابُ الزمنيُّ والإنتاجيُّ في ②③)',
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'دورةٌ قائمةٌ للمفتاح (سباقُ كتابة)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر الفتح: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'open', (int) $out['run_id'], array(),
            array('period' => $from . '→' . $to, 'category' => $cat));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② البوابة — ربطُ اللقطات وتوليدُ الأسطر
    // ═════════════════════════════════════════════════════════════════════

    /**
     * ربطُ لقطةِ كل عقدٍ مؤهَّلٍ وتوليدُ أسطره — **ذريًّا لكل عقد**.
     *
     * لكل عقدٍ: لقطةٌ عبر البوابة الواحدة (`ContractSnapshotService`) بتاريخ
     * **نهاية المدة** — فإن تعذّرت فالعقدُ **مانعٌ مسجَّلٌ بسببه** ولا يُحتسب
     * بقيمٍ افتراضية. ثم سطرٌ لكل (مكوّنٍ × جهةِ تحمّل) بنسبتها من اللقطة.
     *
     * @return array{ok:bool,code:int,reason:string,persons:int,lines:int,blocked:int,state:string}
     */
    public static function bindSnapshots($conn, $gate, $companyId, $runId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'persons' => 0, 'lines' => 0, 'blocked' => 0, 'state' => '');
        $runId = (int) $runId;
        $run = self::runOf($gate, $runId);
        if (!$run) { $out['code'] = 404; $out['reason'] = 'الدورةُ غير موجودةٍ في نطاقك'; return $out; }
        if (!in_array((string) $run['state'], array('Open', 'Blocked'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'الدورةُ «' . $run['state'] . '» — الربطُ للمفتوحة أو الممنوعة حصرًا';
            return $out;
        }

        // إعادةُ الربط تبني من الصفر (الأسطرُ مشتقّةٌ لا وقائعُ مستقلة) —
        // والدورةُ المعتمَدةُ محميةٌ بالحارس أعلاه فلا يُمسّ محسوبٌ أُجيز.
        try {
            $conn->query("DELETE FROM payroll_lines WHERE run_id = " . $runId);
            $conn->query("DELETE FROM payroll_run_blocks WHERE run_id = " . $runId);
        } catch (\Throwable $t) { /* لا يوقف */ }

        $contracts = self::eligibleContracts($gate, $run);
        $persons = array(); $lines = 0; $blocked = 0; $excluded = 0;

        foreach ($contracts as $c) {
            $cid = (int) $c['id'];
            $pid = (int) $c['employee_id'];

            // ── «عقدٌ غيرُ نافذٍ بالفترة → **يُستبعد بسببٍ مكتوب**» ──────────
            // استبعادٌ لا منع (ENT-01 §8): مسودةٌ في السجل ليست عطبًا يوقف
            // دورةً — تُكتب خارجَ النطاق بسببها وتمضي الدورة.
            if (!EmployeeContractStateMachine::isReadable((string) $c['state'])) {
                self::block($gate, $runId, $cid, $pid, 'contract_not_readable', 422,
                    'عقدٌ غيرُ نافذٍ بالفترة (' . EmployeeContractStateMachine::labelAr((string) $c['state'])
                    . ') — يُستبعد بسببٍ مكتوب', 'excluded');
                $excluded++;
                continue;
            }

            // ── البوابةُ الواحدة: لا قراءةَ مباشرةً لجداول العقود ──────────
            $snap = ContractSnapshotService::snapshotFor($conn, $gate, $companyId, $cid, $run['period_to'], $actor);
            if (empty($snap['ok'])) {
                self::block($gate, $runId, $cid, $pid, 'snapshot_missing', (int) $snap['code'],
                    'لقطةٌ متعذّرة: ' . $snap['reason']);
                $blocked++;
                continue;
            }
            $snapId = (int) $snap['id'];

            $payload = self::payloadOf($gate, $snapId);
            if ($payload === null) {
                self::block($gate, $runId, $cid, $pid, 'snapshot_unreadable', 422,
                    'اللقطةُ #' . $snapId . ' غيرُ قابلةٍ للقراءة — لا احتسابَ بقيمٍ افتراضية');
                $blocked++;
                continue;
            }

            $components = isset($payload['components']) ? $payload['components'] : array();
            $bearersMap = isset($payload['cost_bearers']) ? $payload['cost_bearers'] : array();

            // ── Σ نسب التحمّل ≠ 100 → مانعٌ بمرجعه (ENT-01 §8) ─────────────
            $badBearer = null;
            foreach ($components as $pc) {
                $key = 'component#' . $pc['id'];
                $rows = isset($bearersMap[$key]) ? $bearersMap[$key] : array();
                if (!$rows) { continue; }        // بلا شجرةٍ = إشارةُ المكوّن المفردة (H-08-②)
                $sum = 0.0;
                foreach ($rows as $b) { $sum += (float) $b['percent']; }
                if (abs($sum - 100.0) > 0.001) { $badBearer = array($key, round($sum, 2)); break; }
            }
            if ($badBearer !== null) {
                self::block($gate, $runId, $cid, $pid, 'bearer_sum_invalid', 422,
                    'Σ نسب التحمّل للمكوّن ' . $badBearer[0] . ' = ' . $badBearer[1] . '٪ لا 100٪');
                $blocked++;
                continue;
            }

            $path = isset(self::PATH_BY_CATEGORY[(string) $c['category']])
                    ? self::PATH_BY_CATEGORY[(string) $c['category']] : 'institutional';

            // الأساسيُّ الثابتُ — مرجعُ نسبِ `pct_basic` (من اللقطة لا من الجداول)
            $basic = 0.0;
            foreach ($components as $pc) {
                if ((string) $pc['calc_method'] === 'fixed_amount' && (string) $pc['component_type'] === 'basic') {
                    $basic += (float) $pc['value'];
                }
            }

            $made = self::materialize($conn, $gate, $companyId, $runId, $pid, $cid, $snapId,
                                      $path, $components, $bearersMap, $basic, $actor);
            if ($made < 0) {
                self::block($gate, $runId, $cid, $pid, 'materialize_failed', 422, 'تعذّر توليدُ الأسطر ذريًّا');
                $blocked++;
                continue;
            }
            $lines += $made;
            $persons[$pid] = true;
        }

        // **المنعُ وحدَه يوقف الدورة** — والاستبعادُ يُكتب ويمضي (ENT-01 §8).
        $state = $blocked > 0 ? 'Blocked' : 'Calculated';
        try {
            $gate->update('payroll_runs', array(
                'persons_count' => count($persons), 'lines_count' => $lines,
                'blocked_count' => $blocked, 'state' => $state,
                'version' => (int) $run['version'] + 1,
            ), array('id' => $runId));
        } catch (\Throwable $t) { /* الأسطرُ كُتبت — الرأسُ يُحدَّث بأفضل جهد */ }

        self::audit($conn, $companyId, $actor, 'bind_snapshots', $runId,
            array('state' => $run['state']),
            array('state' => $state, 'lines' => $lines, 'blocked' => $blocked, 'excluded' => $excluded));

        $out['ok'] = true; $out['code'] = 200;
        $out['persons'] = count($persons); $out['lines'] = $lines;
        $out['blocked'] = $blocked; $out['excluded'] = $excluded; $out['state'] = $state;
        $out['reason'] = $blocked > 0
            ? ($blocked . ' عقدًا ممنوعًا بقائمة موانعه — لا احتسابَ ناقصٌ صامت'
               . ($excluded > 0 ? (' · و' . $excluded . ' مستبعَدًا بسببٍ مكتوب') : ''))
            : ('كلُّ عقدٍ مؤهَّلٍ رُبط بلقطته'
               . ($excluded > 0 ? (' · و' . $excluded . ' مستبعَدًا بسببٍ مكتوب') : ''));
        return $out;
    }

    /**
     * توليدُ أسطر عقدٍ واحدٍ من لقطته — معاملةٌ واحدة.
     * سطرٌ لكل (مكوّنٍ × جهةِ تحمّل)؛ وبلا شجرةِ جهاتٍ سطرٌ واحدٌ بلا جهة.
     * @return int عددُ الأسطر أو -1 عند التعذر
     */
    private static function materialize($conn, $gate, $companyId, $runId, $personId, $contractId,
                                        $snapshotId, $path, array $components, array $bearersMap,
                                        $basic, $actor)
    {
        $made = 0;
        try {
            $gate->runInTransaction(function ($g) use ($runId, $personId, $contractId, $snapshotId, $path,
                                                       $components, $bearersMap, $basic, &$made) {
                foreach ($components as $pc) {
                    $ref = 'component#' . $pc['id'];
                    $method = (string) $pc['calc_method'];

                    // ── ما تحتسبه الشريحة ① ────────────────────────────────
                    $amount = null; $qty = null; $rate = null;
                    $calcState = 'pending_slice';
                    $note = 'يحتاج مدخلَ زمنٍ أو إنتاج — بيتُه المسارُ الزمني ② أو الإنتاجي ③';

                    if ($method === 'fixed_amount') {
                        $qty = 1.00; $rate = round((float) $pc['value'], 4);
                        $amount = round((float) $pc['value'], 2);
                        $calcState = 'computed';
                        $note = 'مبلغٌ ثابتٌ من اللقطة';
                    } elseif ($method === 'pct_basic') {
                        $qty = 1.00; $rate = round((float) $pc['rate'], 4);
                        $amount = round($basic * ((float) $pc['rate'] / 100.0), 2);
                        $calcState = 'computed';
                        $note = 'نسبةٌ من الأساسيّ الثابت (' . round($basic, 2) . ') باللقطة';
                    }

                    $bearers = isset($bearersMap[$ref]) ? $bearersMap[$ref] : array();
                    if (!$bearers) {
                        // شجرةٌ غائبة = الإشارةُ المفردة على المكوّن (H-08-②)
                        $bearers = array(array(
                            'bearer_type' => isset($pc['cost_bearer_type']) ? $pc['cost_bearer_type'] : null,
                            'bearer_id' => isset($pc['cost_bearer_id']) ? $pc['cost_bearer_id'] : null,
                            'percent' => 100.00,
                        ));
                    }

                    foreach ($bearers as $b) {
                        $pct = round((float) $b['percent'], 2);
                        $g->insert('payroll_lines', array(
                            'run_id' => $runId, 'person_id' => $personId, 'contract_id' => $contractId,
                            'snapshot_id' => $snapshotId, 'path' => $path,
                            'component_ref' => $ref,
                            'component_type' => (string) $pc['component_type'],
                            'calc_method' => $method,
                            'qty' => $qty, 'rate' => $rate,
                            'amount' => $amount !== null ? round($amount * $pct / 100.0, 2) : null,
                            'bearer_type' => $b['bearer_type'] !== null ? (string) $b['bearer_type'] : null,
                            'bearer_id' => !empty($b['bearer_id']) ? (int) $b['bearer_id'] : null,
                            'percent' => $pct,
                            'calc_state' => $calcState,
                            'note' => mb_substr($note, 0, 255),
                        ));
                        $made++;
                    }
                }
                return true;
            }, 'H-09-1 materialize contract#' . $contractId);
        } catch (\Throwable $t) {
            error_log('H-09-1 materialize #' . $contractId . ': ' . $t->getMessage());
            return -1;
        }
        return $made;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ البرهان — «تغيّرٌ لاحقٌ في العقد لا يمسّ ما احتُسب»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * التحقق من ثبات أسطر الدورة: كلُّ سطرٍ يحمل لقطةً، وبصمةُ كل لقطةٍ
     * تطابق مضمونَها (كشفُ التلاعب — ENT-01 §2).
     * @return array{ok:bool,lines:int,without_snapshot:int,tampered:array}
     */
    public static function verifyImmutability($gate, $runId)
    {
        $out = array('ok' => true, 'lines' => 0, 'without_snapshot' => 0, 'tampered' => array());
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.id, l.snapshot_id FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ?", array((int) $runId));
        } catch (\Throwable $t) { $rows = array(); }
        $out['lines'] = count($rows);
        $seen = array();
        foreach ($rows as $r) {
            $sid = (int) $r['snapshot_id'];
            if ($sid <= 0) { $out['without_snapshot']++; $out['ok'] = false; continue; }
            if (isset($seen[$sid])) { continue; }
            $seen[$sid] = true;
            $v = ContractSnapshotService::verify($gate, $sid);
            if (empty($v['ok'])) { $out['tampered'][] = $sid; $out['ok'] = false; }
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءاتٌ ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * العقودُ المرشَّحةُ للدورة — **المتقاطعةُ مع المدة أيًّا كانت حالتُها**.
     *
     * الحالةُ تُحكم في PHP لا في SQL عمدًا: «عقدٌ غيرُ نافذٍ بالفترة **يُستبعد
     * بسببٍ مكتوب**» (ENT-01 §8) — ولو رشّحه الاستعلامُ خارجًا لما وُجد سببٌ
     * يُكتب ولا صفٌّ يُقرأ، فيصير الاستبعادُ صمتًا لا بيانًا.
     */
    public static function eligibleContracts($gate, array $run)
    {
        $params = array();
        $catClause = '';
        if ((string) $run['category_filter'] !== 'all') {
            $catClause = ' AND c.category = ?';
            $params[] = (string) $run['category_filter'];
        }
        $params[] = (string) $run['period_to'];
        $params[] = (string) $run['period_from'];
        try {
            return $gate->scopedQuery(array('scope' => array('c' => 'employee_contracts')),
                "SELECT c.id, c.employee_id, c.category, c.state, c.start_date, c.end_date
                   FROM employee_contracts c
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0" . $catClause . "
                    AND (c.start_date IS NULL OR c.start_date <= ?)
                    AND (c.end_date IS NULL OR c.end_date >= ?)
                  ORDER BY c.id", $params);
        } catch (\Throwable $t) { return array(); }
    }

    public static function runOf($gate, $runId)
    {
        try { return $gate->selectOne('payroll_runs', array('where' => array('id' => (int) $runId))); }
        catch (\Throwable $t) { return null; }
    }

    public static function runByKey($gate, $from, $to, $cat)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'payroll_runs')),
                "SELECT r.id, r.state FROM payroll_runs r
                  WHERE {TENANT_SCOPE} AND r.period_from = ? AND r.period_to = ?
                    AND r.category_filter = ? AND COALESCE(r.is_deleted,0)=0 LIMIT 1",
                array((string) $from, (string) $to, (string) $cat));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function linesOf($gate, $runId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.* FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ?
                  ORDER BY l.person_id, l.component_ref, l.id", array((int) $runId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function blocksOf($gate, $runId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('b' => 'payroll_run_blocks')),
                "SELECT b.* FROM payroll_run_blocks b
                  WHERE {TENANT_SCOPE} AND b.run_id = ? ORDER BY b.id", array((int) $runId));
        } catch (\Throwable $t) { return array(); }
    }

    /** مضمونُ لقطةٍ مفكوكًا — القراءةُ من اللقطة لا من الجداول الحية. */
    private static function payloadOf($gate, $snapshotId)
    {
        $s = null;
        try { $s = $gate->selectOne('contract_snapshots', array('where' => array('id' => (int) $snapshotId))); }
        catch (\Throwable $t) { $s = null; }
        if (!$s) { return null; }
        $p = json_decode((string) $s['snapshot_json'], true);
        return is_array($p) ? $p : null;
    }

    /**
     * صفٌّ في «قائمة الموانع وروابطها» — بنوعه:
     * `excluded` خارجُ النطاق بسببٍ مكتوب (لا يوقف الدورة) ·
     * `blocked` عطبٌ يوقفها حتى يُعالَج (ENT-01 §8 يفرّق بينهما نصًّا).
     */
    private static function block($gate, $runId, $contractId, $personId, $code, $http, $reason, $kind = 'blocked')
    {
        try {
            $gate->insert('payroll_run_blocks', array(
                'run_id' => (int) $runId, 'contract_id' => (int) $contractId,
                'person_id' => (int) $personId ?: null,
                'kind' => $kind === 'excluded' ? 'excluded' : 'blocked',
                'block_code' => (string) $code, 'block_http' => (int) $http,
                'reason' => mb_substr((string) $reason, 0, 255),
            ));
        } catch (\Throwable $t) { /* المفتاحُ الفريد يمنع التكرار */ }
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'payroll_runs', $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
