<?php
/**
 * app/Services/Finance/FinAnalysisService.php — التحليلُ الماليُّ والنسبُ والقوائم
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: M-10 v5 المرحلةُ ٩ · MAP-7 الورقتان 35 و36 · COA §05.
 * الأحكامُ المنفَّذةُ حرّاسًا:
 *   · النسبُ محسوبةٌ من القيودِ لا من إدخالٍ يدويّ — والبسطُ والمقامُ أكوادُ شجرة.
 *   · المقامُ صفرٌ ⇒ النتيجةُ NULL وحالتُها «غيرُ مقيسة» — شرطةٌ لا صفرٌ كاذب.
 *   · التدفقاتُ غيرُ المباشرةِ تتوازن مع تغيرِ النقديةِ الفعليِّ أو تُرفض.
 *   · حقوقُ الملكية: الختاميُّ = الافتتاحيُّ + الحركاتُ أو تُرفض.
 *   · كلُّ توليدٍ نسخةٌ تشير لسابقتها — ولا كتابةَ فوقَ نسخة.
 *   · الإشارةُ تُنشر لإدارةِ المخاطرِ فتدخل الفرزَ الرباعي — ولا تبقى في المالية.
 */

namespace App\Services\Finance;

require_once __DIR__ . '/CoaService.php';
require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/Risk/RiskService.php';

use App\Core\EventPublisher;

class FinAnalysisService
{
    /** مستوياتُ الهامشِ الخمسةُ (COA §05) — أكوادُها من الشجرةِ لا من نصّ. */
    const MARGINS = array(
        'M1' => array('label' => 'الإيرادُ الصافي',     'plus' => array('41', '42'), 'minus' => array()),
        'M2' => array('label' => 'الهامش الإجمالي',    'plus' => array('41'),       'minus' => array('51')),
        'M3' => array('label' => 'الربح التشغيلي',     'plus' => array('41'),       'minus' => array('51', '52', '53', '55')),
        'M4' => array('label' => 'الربح قبل الضريبة',  'plus' => array('41', '42'), 'minus' => array('51', '52', '53', '55', '54', '56')),
        'M5' => array('label' => 'الربح الصافي',       'plus' => array('41', '42'), 'minus' => array('51', '52', '53', '55', '54', '56', '5603')),
    );

    public static function nextCode(\mysqli $db, $companyId, $table, $column, $prefix, $width = 6)
    {
        $allowed = array('fin_project_pl' => 'pl_code', 'fin_cashflow' => 'cf_code');
        if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
            throw new \RuntimeException('FIN-500: جدول خارج قائمة الترقيم');
        }
        $len = strlen($prefix) + 2;
        $st = $db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(`$column`, $len) AS UNSIGNED)),0)+1 nx
                              FROM `$table` WHERE company_id = ?");
        $st->bind_param('i', $companyId);
        $st->execute();
        $nx = (int) $st->get_result()->fetch_assoc()['nx'];
        $st->close();
        return $prefix . '-' . str_pad((string) $nx, $width, '0', STR_PAD_LEFT);
    }

    /* ═══ مستوياتُ الهامش M1..M5 ═════════════════════════════════════════ */
    public static function margins(\mysqli $db, $companyId, $period, array $scope = array())
    {
        $out = array();
        foreach (self::MARGINS as $code => $m) {
            $v = 0.0; $measured = false;
            foreach ($m['plus'] as $c) {
                $b = CoaService::balance($db, $companyId, array($c), $period, $scope);
                if ($b) { $v += $b['balance']; $measured = $measured || $b['n'] > 0; }
            }
            foreach ($m['minus'] as $c) {
                $b = CoaService::balance($db, $companyId, array($c), $period, $scope);
                if ($b) { $v -= $b['balance']; $measured = $measured || $b['n'] > 0; }
            }
            $out[$code] = array('label' => $m['label'], 'value' => $measured ? round($v, 2) : null);
        }
        return $out;
    }

    /* ═══ fin.ratio.compute — النسبُ الأربعُ والأربعون ════════════════════ */
    public static function computeRatios(\mysqli $db, $companyId, $period, $actor, array $scope = array())
    {
        $scopeKind = !empty($scope['project_id']) ? 'project'
            : (!empty($scope['contract_id']) ? 'contract'
            : (!empty($scope['equipment_id']) ? 'equipment' : 'company'));
        $scopeRef = (string) ($scope['project_id'] ?? $scope['contract_id'] ?? $scope['equipment_id'] ?? '');

        $rules = array();
        $r = $db->query("SELECT * FROM fin_ratio_targets
                          WHERE company_id = {$companyId} AND active = 1
                          ORDER BY ratio_code, version_no DESC");
        while ($x = $r->fetch_assoc()) {
            if (!isset($rules[$x['ratio_code']])) { $rules[$x['ratio_code']] = $x; }
        }
        $margins = self::margins($db, $companyId, $period, $scope);

        $done = 0; $warn = 0; $crit = 0; $unmeasured = 0;
        foreach ($rules as $code => $t) {
            $num = self::sumCodes($db, $companyId, $t['numerator_codes'], $period, $scope, $margins);
            $den = self::sumCodes($db, $companyId, $t['denominator_codes'], $period, $scope, $margins);

            $result = null;
            if ($num !== null && $den !== null && abs($den) > 0.0000001) {
                $result = $num / $den;
                if (mb_strpos((string) $t['unit_ar'], '٪') !== false) { $result *= 100; }
                $result = round($result, 4);
            } elseif ($num !== null && ($t['denominator_codes'] === '' || $den === null)) {
                $result = round($num, 4); // نسبةٌ بقيمةٍ مطلقةٍ كفجوةِ السيولة
            }

            $flag = 'unmeasured';
            if ($result !== null) {
                $flag = 'ok';
                $op = (string) $t['warn_op'];
                $wv = $t['warn_value'] !== null ? (float) $t['warn_value'] : null;
                $cv = $t['critical_value'] !== null ? (float) $t['critical_value'] : null;
                if ($cv !== null && (($op === 'lt' && $result < $cv) || ($op === 'gt' && $result > $cv))) { $flag = 'critical'; }
                elseif ($wv !== null && (($op === 'lt' && $result < $wv) || ($op === 'gt' && $result > $wv))) { $flag = 'warn'; }
            }
            if ($flag === 'critical') { $crit++; } elseif ($flag === 'warn') { $warn++; }
            elseif ($flag === 'unmeasured') { $unmeasured++; }

            // ◆ إعادةُ الحسابِ نسخةٌ تشير لسابقتها — والمفتاحُ يحمل رقمَ النسخةِ
            //   فلا يتصادم مهما أُعيد التشغيلُ في الثانيةِ نفسِها.
            $base = 'ratio:' . $code . ':' . $period . ':' . $scopeKind . ':' . $scopeRef;
            $trend = self::trendOf($db, $companyId, $code, $period, $scopeKind, $scopeRef, $result);
            $prev = null;
            $st = $db->prepare("SELECT id FROM fin_ratio_values
                                 WHERE company_id = ? AND ratio_code = ? AND period = ?
                                   AND scope_kind = ? AND scope_ref = ? AND state = 'computed'
                                 ORDER BY id DESC LIMIT 1");
            $st->bind_param('issss', $companyId, $code, $period, $scopeKind, $scopeRef);
            $st->execute();
            $prevRow = $st->get_result()->fetch_assoc();
            $st->close();
            if ($prevRow) {
                $prev = (int) $prevRow['id'];
                $db->query("UPDATE fin_ratio_values SET state = 'superseded' WHERE id = {$prev}");
            }
            $st = $db->prepare("SELECT COUNT(*) c FROM fin_ratio_values
                                 WHERE company_id = ? AND idempotency_key LIKE CONCAT(?, '%')");
            $st->bind_param('is', $companyId, $base);
            $st->execute();
            $ver = 1 + (int) $st->get_result()->fetch_assoc()['c'];
            $st->close();
            $idem = $base . ':v' . $ver;
            $srcNote = 'البسط ' . $t['numerator_codes'] . ' ÷ المقام ' . $t['denominator_codes']
                . ' · الفترة ' . $period;
            $st = $db->prepare("INSERT INTO fin_ratio_values
                (company_id, ratio_code, period, scope_kind, scope_ref, numerator_value,
                 denominator_value, result_value, unit_ar, status_flag, trend_direction,
                 source_note, computed_by, supersedes_id, idempotency_key)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param('issssdddssssiis', $companyId, $code, $period, $scopeKind, $scopeRef,
                $num, $den, $result, $t['unit_ar'], $flag, $trend, $srcNote, $actor, $prev, $idem);
            $st->execute();
            if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
            $st->close();
            $done++;
        }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.ratios.computed', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_ratio_run', 'entity_id' => 0,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'ratios:' . $period . ':' . $scopeKind . ':' . $scopeRef . ':' . gmdate('YmdHis'),
            'source_ref' => 'FR-' . $period,
            'payload' => array('event_name' => 'FinancialRatiosComputed',
                'consumers' => array('الرئيس', 'النائب المالي', 'المخاطر'),
                'computed' => $done, 'warn' => $warn, 'critical' => $crit, 'unmeasured' => $unmeasured),
        ));
        return array('period' => $period, 'computed' => $done, 'warn' => $warn,
            'critical' => $crit, 'unmeasured' => $unmeasured, 'margins' => $margins);
    }

    /** جمعُ أكوادِ بسطٍ أو مقامٍ — ويفهم رموزَ الهامشِ M1..M5 كما في الوثيقة. */
    private static function sumCodes(\mysqli $db, $companyId, $codesCsv, $period, array $scope, array $margins)
    {
        $codes = array_values(array_filter(array_map('trim', explode(',', (string) $codesCsv))));
        if (!$codes) { return null; }
        $sum = 0.0; $any = false;
        foreach ($codes as $c) {
            if (isset($margins[$c])) {
                if ($margins[$c]['value'] !== null) { $sum += $margins[$c]['value']; $any = true; }
                continue;
            }
            $b = CoaService::balance($db, $companyId, array($c), $period, $scope);
            if ($b !== null) { $sum += $b['balance']; if ($b['n'] > 0) { $any = true; } }
        }
        return $any ? round($sum, 4) : null;
    }

    private static function trendOf(\mysqli $db, $companyId, $code, $period, $kind, $ref, $result)
    {
        if ($result === null) { return 'na'; }
        $st = $db->prepare("SELECT result_value FROM fin_ratio_values
                             WHERE company_id = ? AND ratio_code = ? AND scope_kind = ? AND scope_ref = ?
                               AND period < ? AND result_value IS NOT NULL
                             ORDER BY period DESC LIMIT 1");
        $st->bind_param('issss', $companyId, $code, $kind, $ref, $period);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return 'na'; }
        $p = (float) $row['result_value'];
        if (abs($result - $p) < 0.0001) { return 'flat'; }
        return $result > $p ? 'up' : 'down';
    }

    /* ═══ fin.signal.raise — الإشاراتُ الستَّ عشرةَ ═══════════════════════ */
    public static function evaluateSignals(\mysqli $db, $companyId, $period, $actor)
    {
        $rules = array();
        $r = $db->query("SELECT * FROM fin_signal_rules WHERE company_id = {$companyId} AND active = 1");
        while ($x = $r->fetch_assoc()) { $rules[] = $x; }

        $raised = array(); $checked = 0;
        foreach ($rules as $rule) {
            $checked++;
            $rc = (string) $rule['ratio_code'];
            if ($rc === '') { continue; }
            $st = $db->prepare("SELECT result_value FROM fin_ratio_values
                                 WHERE company_id = ? AND ratio_code = ? AND period = ?
                                   AND scope_kind = 'company' AND state = 'computed'
                                 ORDER BY id DESC LIMIT 1");
            $st->bind_param('iss', $companyId, $rc, $period);
            $st->execute();
            $cur = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$cur || $cur['result_value'] === null) { continue; }
            $v = (float) $cur['result_value'];
            $op = (string) $rule['operator'];
            $thr = $rule['threshold'] !== null ? (float) $rule['threshold'] : null;
            $fire = false;

            if ($op === 'lt' && $thr !== null) { $fire = $v < $thr; }
            elseif ($op === 'gt' && $thr !== null) { $fire = $v > $thr; }
            elseif ($op === 'decline_streak') {
                $n = (int) $rule['streak_periods'];
                $st = $db->prepare("SELECT result_value FROM fin_ratio_values
                                     WHERE company_id = ? AND ratio_code = ? AND scope_kind = 'company'
                                       AND result_value IS NOT NULL AND state = 'computed'
                                     ORDER BY period DESC LIMIT ?");
                $lim = $n + 1;
                $st->bind_param('isi', $companyId, $rc, $lim);
                $st->execute();
                $seq = array();
                $res = $st->get_result();
                while ($x = $res->fetch_assoc()) { $seq[] = (float) $x['result_value']; }
                $st->close();
                if (count($seq) >= $n + 1) {
                    $fire = true;
                    for ($i = 0; $i < $n; $i++) { if (!($seq[$i] < $seq[$i + 1])) { $fire = false; break; } }
                }
            } elseif ($op === 'delta_gt' && $thr !== null) {
                $st = $db->prepare("SELECT result_value FROM fin_ratio_values
                                     WHERE company_id = ? AND ratio_code = ? AND scope_kind = 'company'
                                       AND period < ? AND result_value IS NOT NULL AND state='computed'
                                     ORDER BY period DESC LIMIT 1");
                $st->bind_param('iss', $companyId, $rc, $period);
                $st->execute();
                $prev = $st->get_result()->fetch_assoc();
                $st->close();
                if ($prev) { $fire = abs($v - (float) $prev['result_value']) > $thr; }
            }
            if (!$fire) { continue; }

            // ◆ تُنشر لإدارةِ المخاطرِ فتدخل الفرزَ الرباعي — ولا تبقى في المالية.
            // ولا مكوّنَ جديدًا لوظيفةٍ قائمة: الإشارةُ تُنشأ بخدمةِ M-16 نفسِها
            // بحرّاسها (العطالةُ · نافذةُ التكرارِ · الفرزُ الرباعي).
            $idem = 'finsig:' . $rule['signal_code'] . ':' . $period;
            $title = $rule['name_ar'] . ' — ' . $period;
            $details = 'قاعدة: ' . $rule['rule_expr'] . ' · القيمة المقيسة: ' . $v
                . ' · الشدة: ' . $rule['severity'] . ' · الوجهة: ' . $rule['destination_ar'];
            $sig = \App\Services\Risk\RiskService::createSignal($db, $companyId, array(
                'source' => 'auto',
                'rule_key' => $idem,
                'title' => $title,
                'details' => $details,
                'root_cause' => 'إشارة إنذار مالي آلية من محرك النسب (' . $rule['signal_code'] . ')',
                'sync_uuid' => $idem,
            ), $actor);
            $sigId = (int) ($sig['id'] ?? 0);
            if (!empty($sig['idempotent'])) {
                $raised[] = array('code' => $rule['signal_code'], 'signal_id' => $sigId, 'idempotent' => true);
                continue;
            }

            EventPublisher::publishFact($db, array(
                'event_key' => 'finance.signal.raised', 'category' => 'financial',
                'source_module' => 'finance', 'company_id' => (int) $companyId,
                'entity_type' => 'risk_signal', 'entity_id' => $sigId,
                'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
                'idempotency_key' => 'evt:' . $idem, 'source_ref' => $rule['signal_code'],
                'payload' => array('event_name' => 'RiskSignalRaised',
                    'consumers' => array('إدارة المخاطر'), 'severity' => $rule['severity'],
                    'ratio' => $rc, 'value' => $v, 'destination' => $rule['destination_ar']),
            ));
            $raised[] = array('code' => $rule['signal_code'], 'severity' => $rule['severity'],
                'value' => $v, 'signal_id' => $sigId, 'idempotent' => false);
        }
        return array('checked' => $checked, 'raised' => $raised);
    }

    /* ═══ fin.project.pl — قائمةُ دخلِ المشروع (S3) ═══════════════════════ */
    public static function generateProjectPL(\mysqli $db, $companyId, $projectId, $period, $actor, $basis = '')
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) { throw new \RuntimeException('FIN-422: المشروع إلزامي'); }
        $scope = array('project_id' => $projectId);
        $rev = CoaService::balance($db, $companyId, array('41', '42'), $period, $scope);
        $dc  = CoaService::balance($db, $companyId, array('51'), $period, $scope);
        $revenue = $rev ? $rev['balance'] : 0.0;
        $direct = $dc ? $dc['balance'] : 0.0;

        // الحصةُ المحمَّلةُ من 52 بأساسِ تحميلٍ معلَن (نسبةُ إيرادِ المشروعِ للكل)
        $allTotal = CoaService::balance($db, $companyId, array('41', '42'), $period);
        $ga = CoaService::balance($db, $companyId, array('52'), $period);
        $share = 0.0;
        $basisText = $basis !== '' ? $basis : 'نسبة إيراد المشروع إلى إجمالي الإيراد في الفترة';
        if ($allTotal && abs($allTotal['balance']) > 0.0001 && $ga) {
            $share = round($ga['balance'] * ($revenue / $allTotal['balance']), 2);
        }
        $gross = round($revenue - $direct, 2);
        $op = round($gross - $share, 2);
        $pct = abs($revenue) > 0.0001 ? round($op / $revenue * 100, 4) : null;

        $idem = 'ppl:' . $projectId . ':' . $period;
        $st = $db->prepare("SELECT id, pl_code FROM fin_project_pl
                             WHERE company_id = ? AND idempotency_key LIKE CONCAT(?, '%')
                               AND state = 'generated' ORDER BY id DESC LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $key = $idem . ($prev ? ':r' . time() : '');
        $code = self::nextCode($db, $companyId, 'fin_project_pl', 'pl_code', 'PPL');
        $lines = json_encode(array('revenue_codes' => '41,42', 'direct_codes' => '51',
            'overhead_code' => '52', 'basis' => $basisText), JSON_UNESCAPED_UNICODE);
        $sup = $prev ? (int) $prev['id'] : null;
        $auth = 'المحاسب يولد والمدير المالي يراجع (M-10 §7-1)';
        $parent = 'PROJECT-' . $projectId;
        $st = $db->prepare("INSERT INTO fin_project_pl
            (company_id, pl_code, project_id, period, revenue_total, direct_cost_total,
             allocated_overhead, allocation_basis, gross_margin, operating_profit, margin_pct,
             lines_json, supersedes_id, generated_by, authority_ref, parent_ref, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('isisdddsdddsiisss', $companyId, $code, $projectId, $period,
            $revenue, $direct, $share, $basisText, $gross, $op, $pct, $lines, $sup, $actor,
            $auth, $parent, $key);
        $st->execute();
        if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
        $id = (int) $db->insert_id;
        $st->close();
        if ($prev) { $db->query("UPDATE fin_project_pl SET state='superseded' WHERE id=" . (int) $prev['id']); }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.project_pl.generated', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_project_pl', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $key, 'amount' => $op, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'ProjectPLGenerated',
                'consumers' => array('التشغيل', 'المخاطر', 'المالكون')),
        ));
        return array('id' => $id, 'pl_code' => $code, 'revenue' => $revenue,
            'direct_cost' => $direct, 'overhead' => $share, 'gross_margin' => $gross,
            'operating_profit' => $op, 'margin_pct' => $pct);
    }

    /* ═══ fin.cashflow.generate — S4 بالطريقة غير المباشرة ═══════════════ */
    public static function generateCashflow(\mysqli $db, $companyId, $period, $actor, $tolerance = 0.01)
    {
        // ◆ الطريقةُ غيرُ المباشرةُ مبنيةٌ على معادلةِ الميزانيةِ نفسِها فتتوازن
        //   بالبناءِ لا بالتقريب: الأصولُ = الالتزاماتُ + حقوقُ الملكيةِ + النتيجة.
        //   ولذلك تُقسَّم البنودُ قسمةً لا تتداخل: مجمَّعُ الإهلاكِ يُضاف إلى
        //   التشغيليِّ غيرَ نقديٍّ ويُستبعَد من الاستثماريِّ فلا يُحتسب مرتين.
        $ACCUM_DEP   = array('1305', '1402');                    // مجمَّعا الإهلاكِ والاستنفاد
        $NONCASH_CA  = array('1103', '1104', '1105', '1106', '1107', '1108', '1109');
        $CL_NOPROV   = array('2101', '2102', '2103', '2104', '2105', '2106', '2107', '2108');
        $PROVISIONS  = array('2109', '2202');                    // مخصصاتٌ غيرُ نقدية
        $NCA_GROSS   = array('1201', '1202', '1301', '1302', '1303', '1304', '1306', '1401');
        $NCL_FIN     = array('2201', '2301', '2302', '2303', '2304', '2305');
        $EQUITY_ALL  = array('3');

        $margins = self::margins($db, $companyId, $period);
        $net = $margins['M5']['value'] !== null ? (float) $margins['M5']['value'] : 0.0;
        $prevPeriod = date('Y-m', strtotime($period . '-01 -1 month'));

        /** حركةُ الفترةِ لمجموعةِ أكوادٍ = رصيدُها في الفترةِ (الحركةُ لا التراكم). */
        $delta = function ($codes) use ($db, $companyId, $period) {
            $sum = 0.0;
            foreach ((array) $codes as $c) {
                $b = CoaService::balance($db, $companyId, array($c), $period);
                if ($b) { $sum += $b['balance']; }
            }
            return round($sum, 2);
        };

        // إضافةٌ غيرُ نقدية: مجمَّعُ الإهلاكِ رصيدُه دائنٌ (سالبٌ في الأصول)
        $adjDep  = round(-$delta($ACCUM_DEP), 2);
        $adjProv = $delta($PROVISIONS);

        // رأسُ المالِ العامل: الأصلُ يزيد ⇒ نقدٌ يخرج · والالتزامُ يزيد ⇒ يدخل
        $wc = array(
            'receivables' => round(-$delta(array('1104')), 2),
            'inventory'   => round(-$delta(array('1106')), 2),
            'payables'    => round($delta(array('2101')), 2),
        );
        $otherCa = array_values(array_diff($NONCASH_CA, array('1104', '1106')));
        $otherCl = array_values(array_diff($CL_NOPROV, array('2101')));
        $wcOther = round(-$delta($otherCa) + $delta($otherCl), 2);

        $operating = round($net + $adjDep + $adjProv
            + $wc['receivables'] + $wc['inventory'] + $wc['payables'] + $wcOther, 2);
        $investing = round(-$delta($NCA_GROSS), 2);
        $financing = round($delta($NCL_FIN) + $delta($EQUITY_ALL), 2);
        $netChange = round($operating + $investing + $financing, 2);

        $close = $delta(array('1101', '1102'));
        $cashPre = CoaService::balance($db, $companyId, array('1101'), $prevPeriod);
        $cashPre2 = CoaService::balance($db, $companyId, array('1102'), $prevPeriod);
        $open = round(($cashPre ? $cashPre['balance'] : 0.0) + ($cashPre2 ? $cashPre2['balance'] : 0.0), 2);
        $actual = $close; // حركةُ النقديةِ في الفترةِ نفسِها
        $diff = round($netChange - $actual, 2);
        $ok = abs($diff) <= (float) $tolerance ? 1 : 0;

        // ◆ تتوازن أو تُرفض — والرفضُ برمزٍ محكومٍ يبيّن الفرق
        if (!$ok) {
            throw new \RuntimeException('FIN-CF-422: قائمة التدفقات لا تتوازن مع تغير النقدية الفعلي — '
                . 'المحسوب ' . number_format($netChange, 2) . ' والفعلي ' . number_format($actual, 2)
                . ' والفرق ' . number_format($diff, 2) . ' · تراجع تصنيفات نشاط التدفق (R4)');
        }

        $idem = 'cfs:' . $period;
        $st = $db->prepare("SELECT id FROM fin_cashflow WHERE company_id = ? AND idempotency_key LIKE CONCAT(?, '%')
                             AND state = 'generated' ORDER BY id DESC LIMIT 1");
        $st->bind_param('is', $companyId, $idem);
        $st->execute();
        $prev = $st->get_result()->fetch_assoc();
        $st->close();
        $key = $idem . ($prev ? ':r' . time() : '');
        $code = self::nextCode($db, $companyId, 'fin_cashflow', 'cf_code', 'CFS');
        $lines = json_encode(array('method' => 'indirect', 'prev_period' => $prevPeriod),
            JSON_UNESCAPED_UNICODE);
        $sup = $prev ? (int) $prev['id'] : null;
        $auth = 'المدير المالي (M-10 §7-1)';
        $parent = 'PERIOD-' . $period;
        $st = $db->prepare("INSERT INTO fin_cashflow
            (company_id, cf_code, period, net_profit, adj_depreciation, adj_provisions, adj_other,
             wc_receivables, wc_inventory, wc_payables, wc_other, operating_net, investing_net,
             financing_net, net_change, cash_open, cash_close, actual_change, balance_diff,
             balance_ok, lines_json, supersedes_id, generated_by, authority_ref, parent_ref, idempotency_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $zero = 0.0;
        $st->bind_param('issddddddddddddddddisiisss', $companyId, $code, $period, $net,
            $adjDep, $adjProv, $zero, $wc['receivables'], $wc['inventory'], $wc['payables'], $wcOther,
            $operating, $investing, $financing, $netChange, $open, $close, $actual, $diff,
            $ok, $lines, $sup, $actor, $auth, $parent, $key);
        $st->execute();
        if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
        $id = (int) $db->insert_id;
        $st->close();
        if ($prev) { $db->query("UPDATE fin_cashflow SET state='superseded' WHERE id=" . (int) $prev['id']); }

        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.cashflow.generated', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_cashflow', 'entity_id' => $id,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'evt:' . $key, 'amount' => $netChange, 'currency' => 'SDG',
            'source_ref' => $code,
            'payload' => array('event_name' => 'CashFlowStatementGenerated',
                'consumers' => array('الرئيس', 'النائب المالي', 'الممولون'), 'balanced' => true),
        ));
        return array('id' => $id, 'cf_code' => $code, 'operating' => $operating,
            'investing' => $investing, 'financing' => $financing, 'net_change' => $netChange,
            'actual_change' => $actual, 'balance_diff' => $diff, 'balanced' => true);
    }

    /* ═══ fin.equity.generate — S5 ═══════════════════════════════════════ */
    public static function generateEquity(\mysqli $db, $companyId, $period, $actor, $tolerance = 0.01)
    {
        $prevPeriod = date('Y-m', strtotime($period . '-01 -1 month'));
        $comps = array();
        $r = $db->query("SELECT code, name FROM fin_chart_of_accounts
                          WHERE company_id = {$companyId} AND is_canonical = 1
                            AND account_type = 'equity' AND acc_level = 3 ORDER BY code");
        while ($x = $r->fetch_assoc()) { $comps[$x['code']] = $x['name']; }

        $rows = array(); $bad = array();
        foreach ($comps as $code => $name) {
            $cur = CoaService::balance($db, $companyId, array($code), $period);
            $pre = CoaService::balance($db, $companyId, array($code), $prevPeriod);
            $closing = $cur ? $cur['balance'] : 0.0;
            $opening = $pre ? $pre['balance'] : 0.0;
            $delta = round($closing - $opening, 2);
            $add = $delta > 0 ? $delta : 0.0;
            $ded = $delta < 0 ? abs($delta) : 0.0;
            $computed = round($opening + $add - $ded, 2);
            $ok = abs($computed - $closing) <= (float) $tolerance ? 1 : 0;
            if (!$ok) { $bad[] = $code; }
            $rows[] = array($code, $name, $opening, $add, $ded, 0.0, $closing, $computed, $ok);
        }
        if ($bad) {
            throw new \RuntimeException('FIN-EQ-422: بند لا يتوازن — الختامي ≠ الافتتاحي + الحركات في: '
                . implode(' · ', $bad));
        }

        $code0 = 'EQS-' . str_replace('-', '', $period);
        $saved = 0;
        foreach ($rows as $rw) {
            list($ccode, $cname, $open, $add, $ded, $tr, $close, $computed, $ok) = $rw;
            $idem = 'eqs:' . $period . ':' . $ccode;
            $db->query("UPDATE fin_equity SET state='superseded'
                         WHERE company_id={$companyId} AND idempotency_key LIKE '"
                         . $db->real_escape_string($idem) . "%' AND state='generated'");
            $key = $idem . ':' . gmdate('YmdHis');
            $st = $db->prepare("INSERT INTO fin_equity
                (company_id, eq_code, period, component_code, component_name, opening_balance,
                 additions, deductions, transfers, closing_balance, computed_closing, balance_ok,
                 generated_by, authority_ref, parent_ref, idempotency_key)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $auth = 'المدير المالي (M-10 §7-1)';
            $parent = 'PERIOD-' . $period;
            $st->bind_param('issssddddddiisss', $companyId, $code0, $period, $ccode, $cname,
                $open, $add, $ded, $tr, $close, $computed, $ok, $actor, $auth, $parent, $key);
            $st->execute();
            if ($st->errno) { $e = $st->error; $st->close(); throw new \RuntimeException('FIN-500: ' . $e); }
            $st->close();
            $saved++;
        }
        EventPublisher::publishFact($db, array(
            'event_key' => 'finance.equity.generated', 'category' => 'financial',
            'source_module' => 'finance', 'company_id' => (int) $companyId,
            'entity_type' => 'fin_equity', 'entity_id' => 0,
            'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
            'idempotency_key' => 'eqs:run:' . $period . ':' . gmdate('YmdHis'),
            'source_ref' => $code0,
            'payload' => array('event_name' => 'EquityStatementGenerated',
                'consumers' => array('الرئيس', 'الشركاء', 'التمويل'), 'components' => $saved),
        ));
        return array('eq_code' => $code0, 'components' => $saved, 'balanced' => true);
    }

    /* ═══ قوائمُ القراءة: المركزُ الماليُّ ودخلُ الشركة (S1 · S2) ═════════ */
    public static function balanceSheet(\mysqli $db, $companyId, $period)
    {
        $out = array('assets' => array(), 'liabilities' => array(), 'equity' => array(),
            'totals' => array('assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0));
        $r = $db->query("SELECT code, name, account_type FROM fin_chart_of_accounts
                          WHERE company_id = {$companyId} AND is_canonical = 1 AND acc_level = 2
                          ORDER BY code");
        while ($x = $r->fetch_assoc()) {
            $b = CoaService::balance($db, $companyId, array($x['code']), $period);
            $v = $b ? $b['balance'] : 0.0;
            $bucket = $x['account_type'] === 'asset' ? 'assets'
                : ($x['account_type'] === 'liability' ? 'liabilities'
                : ($x['account_type'] === 'equity' ? 'equity' : null));
            if ($bucket === null) { continue; }
            $out[$bucket][] = array('code' => $x['code'], 'name' => $x['name'], 'value' => $v);
            $out['totals'][$bucket] += $v;
        }
        foreach ($out['totals'] as $k => $v) { $out['totals'][$k] = round($v, 2); }
        // ◆ نتيجةُ الفترةِ غيرِ المقفلةِ بندٌ من حقوقِ الملكيةِ اقتصاديًّا وإن لم
        //   تُرحَّل قيدًا بعد — وإظهارُها صريحةً يجعل المعادلةَ تُقرأ صفرًا بحق،
        //   وإخفاؤها يجعلها تبدو مختلّةً وهي سليمة.
        $m = self::margins($db, $companyId, $period);
        $result = $m['M5']['value'] !== null ? (float) $m['M5']['value'] : 0.0;
        $out['equity'][] = array('code' => '3204', 'name' => 'نتيجة الفترة الحالية (غير مقفلة)',
            'value' => round($result, 2), 'unclosed' => true);
        $out['totals']['equity'] = round($out['totals']['equity'] + $result, 2);
        $out['unclosed_result'] = round($result, 2);
        $out['equation_diff'] = round($out['totals']['assets']
            - ($out['totals']['liabilities'] + $out['totals']['equity']), 2);
        return $out;
    }

    public static function incomeStatement(\mysqli $db, $companyId, $period, array $scope = array())
    {
        $lines = array();
        $r = $db->query("SELECT code, name, account_type FROM fin_chart_of_accounts
                          WHERE company_id = {$companyId} AND is_canonical = 1 AND acc_level = 2
                            AND account_type IN ('revenue','expense') ORDER BY code");
        while ($x = $r->fetch_assoc()) {
            $b = CoaService::balance($db, $companyId, array($x['code']), $period, $scope);
            $lines[] = array('code' => $x['code'], 'name' => $x['name'],
                'type' => $x['account_type'], 'value' => $b ? $b['balance'] : 0.0);
        }
        return array('lines' => $lines, 'margins' => self::margins($db, $companyId, $period, $scope));
    }
}
