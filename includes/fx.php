<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * includes/fx.php — العملة ومعادلُها الموحّد (FES-01 §3.1 · §3.3)
 * ───────────────────────────────────────────────────────────────────────────
 * مصدرٌ واحدٌ لثلاثة أسئلة: ما عملةُ الأساس؟ · ما رمزُ هذه العملة المكتوبة؟ ·
 * كم تساوي بعملة الأساس في تاريخٍ بعينه؟
 *
 * **لغتان للعملة في النظام — وهذا مقصود**: طبقةُ العقود تكتب الاسمَ العربي
 * («دولار» · «جنيه») لأن المستخدم يختاره من قائمة، وطبقةُ المال تكتب الرمزَ
 * المعياري (`USD` · `SDG`). والترجمةُ عند الحدّ — كما تفعل المروحةُ منذ نشأتها
 * (`EffectFanout::CONTRACT_CURRENCY`). هذا الملفُّ يعمّم تلك الترجمة فلا يبقى
 * مستهلكٌ يمرّر الاسمَ العربي خامًا إلى جدولٍ ماليّ.
 *
 * **قاعدةُ عدم التلفيق نافذةٌ هنا**: لا سعرَ مفترَضًا ولا عملةَ افتراضية. ما لا
 * سعرَ له في تاريخه يرجع `ok=false` بسببه — ولا يُحسب بواحدٍ ولا بآخر سعرٍ
 * معروف. فالمعادلُ الغائب أصدقُ من معادلٍ مخترَع.
 *
 * **دلالة السعر**: `rate_to_base` = كم وحدةَ أساسٍ يساوي واحدٌ من العملة، فيكون
 *     base = ROUND(amount × rate, 2)
 * ضربًا لا قسمةً — نصُّ FES-01 §3.3 حرفًا.
 *
 * @package EMS\Core
 */

if (!function_exists('ems_fx_currencies')) {
    /**
     * العملاتُ المسجَّلة لشركة السياق: الرمز => صفُّه. تُقرأ مرةً لكل طلب.
     *
     * @return array<string,array>
     */
    function ems_fx_currencies($reload = false)
    {
        static $cache = null;
        if ($cache !== null && !$reload) { return $cache; }

        $cache = array();
        $rows = ems_tenant_db()->select('fin_currencies', array(
            'columns' => array('code', 'name_ar', 'symbol', 'decimals', 'is_base', 'active'),
            'orderBy' => 'sort_order',
        ));
        foreach ($rows as $r) {
            $cache[(string) $r['code']] = $r;
        }
        return $cache;
    }
}

if (!function_exists('ems_parse_decimal')) {
    /**
     * قراءةُ رقمٍ عشريٍّ كما يكتبه مستخدمٌ عربيّ — لا كما تشتهيه لوحةُ المفاتيح.
     *
     * `<input type="number">` يرفض بصمتٍ ما لا يوافق محلّيةَ المتصفح: الفاصلةَ
     * العشرية العربية (٫) والأرقامَ الهندية (٠١٢…) والفاصلةَ اللاتينية — فيبدو
     * الحقلُ وكأنه «لا يقبل الكسور». فالحقلُ صار نصيًّا والتطبيعُ هنا:
     *   · الأرقامُ الهندية والفارسية → لاتينية
     *   · الفاصلةُ العربية ٫ والفاصلةُ , → نقطة
     *   · فواصلُ الآلاف (٬ ومسافات ومسافةٌ رفيعة) تُسقط
     *
     * @param  string     $raw المكتوب
     * @return float|null      الرقم، أو null إن لم يكن رقمًا (تعذّرٌ معلَن)
     */
    function ems_parse_decimal($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') { return null; }

        // أرقامٌ هندية (٠-٩) وفارسية (۰-۹) → لاتينية
        $map = array(
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        );
        $s = strtr($s, $map);

        // فواصلُ الآلاف تُسقط (٬ · مسافة عادية · مسافة رفيعة U+202F · NBSP)
        $s = str_replace(array('٬', ' ', "\xE2\x80\xAF", "\xC2\xA0"), '', $s);
        // الفاصلةُ العشرية بأشكالها → نقطة
        $s = str_replace(array('٫', ','), '.', $s);

        if (!preg_match('~^[+-]?(\d+(\.\d*)?|\.\d+)$~', $s)) { return null; }
        return (float) $s;
    }
}

if (!function_exists('ems_fx_cache_clear')) {
    /**
     * إسقاطُ الذاكرة المؤقتة للعملات.
     *
     * الذاكرةُ تعيش عمرَ الطلب، وهو الصحيحُ في الشاشات — إلا في طلبٍ **يسجّل
     * عملةً ثم يستعملها**: شاشةُ العملات نفسُها، والاختباراتُ التي تبذر عملتَها.
     * فبعد أي كتابةٍ على `fin_currencies` تُستدعى هذه لتُقرأ من جديد.
     */
    function ems_fx_cache_clear()
    {
        ems_fx_currencies(true);
    }
}

if (!function_exists('ems_fx_base_currency')) {
    /**
     * عملةُ الأساس لشركة السياق — أي العملةُ التي يُقاس بها كلُّ شيء.
     * مصدرُها `fin_currencies.is_base` المشتقُّ أصلًا من `admin_companies.currency`.
     *
     * @return string|null الرمز، أو null إن لم تُسجَّل عملةُ أساسٍ بعد.
     */
    function ems_fx_base_currency()
    {
        foreach (ems_fx_currencies() as $code => $row) {
            if (!empty($row['is_base'])) { return (string) $code; }
        }
        return null;
    }
}

if (!function_exists('ems_fx_code')) {
    /**
     * تطبيعُ ما كُتب في خانة العملة إلى رمزٍ **مسجَّل**.
     *
     * يقبل: الرمزَ نفسَه (`USD`) · الاسمَ العربي كما في السجل («الدولار الأمريكي»)
     * · الأسماءَ الشائعة في طبقة العقود («دولار» · «جنيه» · «يورو» · «ريال»).
     *
     * @param  string $label المكتوب في العمود
     * @return string|null   الرمز المسجَّل، أو null إن لم يُعرَف (تعذّرٌ معلَن)
     */
    function ems_fx_code($label)
    {
        $label = trim((string) $label);
        if ($label === '') { return null; }

        $currencies = ems_fx_currencies();

        // ① رمزٌ مسجَّلٌ حرفيًّا (بلا حساسيةٍ لحالة الأحرف)
        foreach ($currencies as $code => $row) {
            if (strcasecmp($label, (string) $code) === 0) { return (string) $code; }
        }

        // ② الاسمُ العربي أو الرمزُ المختصر كما سُجّلا
        foreach ($currencies as $code => $row) {
            if ($label === trim((string) $row['name_ar'])) { return (string) $code; }
            if ($row['symbol'] !== null && $label === trim((string) $row['symbol'])) {
                return (string) $code;
            }
        }

        // ③ أسماءُ طبقة العقود — نفسُ خريطة المروحة حرفيًّا
        //    (EffectFanout::CONTRACT_CURRENCY — تُبقى متطابقةً معها)
        $aliases = array(
            'جنيه'  => 'SDG',
            'دولار' => 'USD',
            'يورو'  => 'EUR',
            'ريال'  => 'SAR',
        );
        if (isset($aliases[$label])) {
            $code = $aliases[$label];
            // لا يُقبل الاسمُ المستعار إلا إن كانت عملتُه **مسجَّلةً** فعلًا،
            // وإلا صار الرمزُ ورقةً بلا رصيدٍ ترفضها المفاتيحُ الأجنبية.
            return isset($currencies[$code]) ? $code : null;
        }

        return null;
    }
}

if (!function_exists('ems_fx_rate')) {
    /**
     * السعرُ النافذ لعملةٍ في تاريخٍ: آخرُ سعرٍ سريانُه سابقٌ للتاريخ أو مساوٍ له.
     *
     * @param  string      $code رمزُ العملة (مطبَّعًا)
     * @param  string|null $date تاريخُ الواقعة Y-m-d — الافتراضُ اليوم
     * @return float|null        السعر، أو null إن لم يُدخل سعرٌ يغطّي التاريخ
     */
    function ems_fx_rate($code, $date = null)
    {
        $code = (string) $code;
        if ($code === '') { return null; }
        $date = ($date === null || $date === '') ? date('Y-m-d') : substr((string) $date, 0, 10);

        $row = ems_tenant_db()->scopedQuery(
            array('scope' => array('r' => 'fin_fx_rates')),
            "SELECT r.rate_to_base
               FROM fin_fx_rates r
              WHERE {TENANT_SCOPE}
                AND r.currency_code = ?
                AND r.effective_from <= ?
                AND COALESCE(r.is_deleted, 0) = 0
              ORDER BY r.effective_from DESC
              LIMIT 1",
            array($code, $date)
        );

        return $row ? (float) $row[0]['rate_to_base'] : null;
    }
}

if (!function_exists('ems_fx_to_base')) {
    /**
     * المعادلُ بعملة الأساس — بنتيجةٍ معلنةٍ لا صامتة.
     *
     * @param  float       $amount المبلغ بعملته
     * @param  string      $label  العملة كما كُتبت (رمزًا أو اسمًا)
     * @param  string|null $date   تاريخُ الواقعة
     * @return array ok · code · rate · base · reason
     */
    function ems_fx_to_base($amount, $label, $date = null)
    {
        $out = array('ok' => false, 'code' => null, 'rate' => null, 'base' => null, 'reason' => '');

        $code = ems_fx_code($label);
        if ($code === null) {
            $out['reason'] = 'عملةٌ غير مسجَّلة: ' . trim((string) $label);
            return $out;
        }
        $out['code'] = $code;

        if ($amount === null || $amount === '') {
            $out['reason'] = 'لا مبلغَ يُعادَل';
            return $out;
        }

        $rate = ems_fx_rate($code, $date);
        if ($rate === null) {
            $out['reason'] = 'لا سعرَ صرفٍ لـ' . $code . ' في ' .
                             (($date === null || $date === '') ? 'تاريخ اليوم' : substr((string) $date, 0, 10));
            return $out;
        }

        $out['ok']   = true;
        $out['rate'] = $rate;
        $out['base'] = round(((float) $amount) * $rate, 2);
        return $out;
    }
}

if (!function_exists('ems_fx_revalue_open_dues')) {
    /**
     * إعادةُ التقييم الدورية للأرصدة المفتوحة — M-40 (FES §3.3).
     * ───────────────────────────────────────────────────────────────────────
     * «إعادةُ التقييم الدورية للأرصدة المفتوحة تولّد قيدَ فرقٍ آليًّا بمرجعها —
     * لا تعديلَ الأصل»: الذممُ المفتوحة (fin_dues بلا تسوية) بعملةٍ غير الأساس
     * يُحسب معادلُها بسعر يوم التقييم؛ الفرقُ عن معادلها المثبَّت يُجمع قيدَ
     * فرقٍ واحدًا (مسودة) على حساب «فروق عملة» مقابل حساب الذمم باتجاهها.
     *
     * قاعدةُ عدم التلفيق: لا حسابَ «فروق عملة» (كود 5900) في الدليل ⇒ يُعلَن
     * skipped_no_account بتقريره الكامل ولا يُخترع حسابٌ بديل.
     *
     * @param  string|null $asOf يومُ التقييم (الافتراض اليوم)
     * @return array{status:string, diffs:array, total_diff:float, journal_id:?int, reason:string}
     */
    function ems_fx_revalue_open_dues(\mysqli $conn, $companyId, $asOf = null, $actor = 0)
    {
        $out = array('status' => 'noop', 'diffs' => array(), 'total_diff' => 0.0,
                     'journal_id' => null, 'reason' => '');
        $companyId = intval($companyId);
        $asOf = ($asOf === null || $asOf === '') ? date('Y-m-d') : substr((string) $asOf, 0, 10);
        // SQL مباشرٌ لا بوابة: المحرّكُ يُستدعى من cron/CLI بلا سياق جلسة
        $bq = $conn->prepare("SELECT code FROM fin_currencies
            WHERE company_id = ? AND is_base = 1 AND COALESCE(is_deleted,0) = 0 LIMIT 1");
        $bq->bind_param('i', $companyId);
        $bq->execute();
        $brow = $bq->get_result()->fetch_assoc();
        $bq->close();
        $base = $brow ? (string) $brow['code'] : null;
        if ($base === null) { $out['reason'] = 'لا عملةَ أساسٍ مسجَّلة'; return $out; }
        $rateAt = function ($code, $date) use ($conn, $companyId) {
            $rq = $conn->prepare("SELECT rate_to_base FROM fin_fx_rates
                WHERE company_id = ? AND currency_code = ? AND COALESCE(is_deleted,0) = 0
                  AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1");
            if (!$rq) { return null; }
            $rq->bind_param('iss', $companyId, $code, $date);
            $rq->execute();
            $rr = $rq->get_result()->fetch_assoc();
            $rq->close();
            return $rr ? (float) $rr['rate_to_base'] : null;
        };

        // الأرصدةُ المفتوحة: ذممٌ بلا تسويةٍ بعملةٍ غير الأساس ولها معادلٌ مثبَّت
        $st = $conn->prepare(
            "SELECT id, party_type, party_ref, direction, amount, currency, fx_rate, base_amount
               FROM fin_dues
              WHERE company_id = ? AND COALESCE(is_deleted, 0) = 0
                AND settlement_id IS NULL AND currency <> ?
                AND base_amount IS NOT NULL");
        if (!$st) { $out['reason'] = $conn->error; return $out; }
        $st->bind_param('is', $companyId, $base);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $totalDiff = 0.0;
        foreach ($rows as $r) {
            $rate = $rateAt((string) $r['currency'], $asOf);
            if ($rate === null) { continue; } // لا سعرَ ليومه — غيابٌ معلَنٌ يظهر في التقرير الناقص
            $newBase = round(((float) $r['amount']) * $rate, 2);
            $diff = round($newBase - (float) $r['base_amount'], 2);
            if (abs($diff) < 0.01) { continue; }
            // الفرقُ باتجاه الذمة: خصمٌ (دائنٌ علينا) يزيد بالفرق الموجب
            $signed = ((string) $r['direction'] === 'debit') ? -$diff : $diff;
            $totalDiff += $signed;
            $out['diffs'][] = array(
                'due_id' => intval($r['id']), 'party_type' => (string) $r['party_type'],
                'party_ref' => (string) $r['party_ref'], 'direction' => (string) $r['direction'],
                'currency' => (string) $r['currency'], 'amount' => (float) $r['amount'],
                'old_base' => (float) $r['base_amount'], 'new_base' => $newBase, 'diff' => $diff,
            );
        }
        $totalDiff = round($totalDiff, 2);
        $out['total_diff'] = $totalDiff;
        if (empty($out['diffs'])) { $out['status'] = 'noop'; $out['reason'] = 'لا فرقَ تقييمٍ — الأسعارُ كما ثُبّتت'; return $out; }
        if (abs($totalDiff) < 0.01) { $out['status'] = 'zero_net'; $out['reason'] = 'الفروقُ تتصافر'; return $out; }

        // قيدُ الفرق: حسابُ «فروق عملة» (5900) مقابل حساب الذمم — بلا مساس الأصل
        require_once dirname(__DIR__) . '/Finance/fin_helpers.php';
        $fxAcc = null;
        try {
            $row = fin_gate(false)->selectOne('fin_chart_of_accounts', array(
                'columns' => array('id'), 'where' => array('code' => '5900', 'is_postable' => 1)));
            $fxAcc = $row ? intval($row['id']) : null;
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $fxAcc'); $fxAcc = null; }
        if ($fxAcc === null) {
            $out['status'] = 'skipped_no_account';
            $out['reason'] = 'لا حسابَ «فروق عملة» (5900) في الدليل — أضِفه ثم أعد التقييم (لا يُخترع حساب)';
            return $out;
        }
        $apAcc = fin_account_by_code($conn, $companyId, '2100', 'liability');
        if (!$apAcc) { $out['status'] = 'skipped_no_account'; $out['reason'] = 'لا حسابَ ذممٍ (2100)'; return $out; }

        $absDiff = abs($totalDiff);
        // خسارةُ تقييم (الالتزامُ زاد): مدين فروق عملة / دائن ذمم — والربحُ عكسُه
        $debitAcc  = ($totalDiff > 0) ? $fxAcc : $apAcc;
        $creditAcc = ($totalDiff > 0) ? $apAcc : $fxAcc;
        $memo = 'إعادةُ تقييمٍ دورية ' . $asOf . ' — ' . count($out['diffs']) . ' رصيدًا مفتوحًا (FES §3.3)';
        $entryNo = fin_gen_code($conn, 'fin_journal_entries', 'FIN-JV', $companyId);
        $jid = null;
        try {
            fin_gate(false)->runInTransaction(function ($g) use (&$jid, $entryNo, $asOf, $absDiff, $memo, $actor, $debitAcc, $creditAcc, $base) {
                $jid = $g->insert('fin_journal_entries', array(
                    'entry_no' => $entryNo, 'event_id' => null, 'posting_date' => date('Y-m-d'),
                    'txn_date' => $asOf, 'currency' => $base, 'fx_rate' => 1.0, 'base_amount' => $absDiff,
                    'total_debit' => $absDiff, 'total_credit' => $absDiff,
                    'memo' => $memo, 'state' => 'draft',
                    'created_by' => intval($actor) > 0 ? intval($actor) : null,
                ));
                $g->insert('fin_journal_lines', array(
                    'entry_id' => $jid, 'account_id' => $debitAcc, 'debit' => $absDiff, 'credit' => 0, 'memo' => $memo));
                $g->insert('fin_journal_lines', array(
                    'entry_id' => $jid, 'account_id' => $creditAcc, 'debit' => 0, 'credit' => $absDiff, 'memo' => $memo));
            }, 'fx revaluation diff entry');
        } catch (\Throwable $t) {
            error_log('fx revalue journal: ' . $t->getMessage());
            $out['status'] = 'failed'; $out['reason'] = 'تعذّر قيدُ الفرق'; return $out;
        }
        $out['status'] = 'entry_created';
        $out['journal_id'] = intval($jid);
        $out['reason'] = 'قيدُ فرقٍ مسودة ' . $entryNo . ' بقيمة ' . $absDiff . ' ' . $base . ' — الأصلُ لم يُمسّ';
        return $out;
    }
}
