<?php
/**
 * حارسُ الفترة المالية المقفلة — M-39 (UX-02 §15.4 · SPEC-01 #14 · FES §3.1)
 * ───────────────────────────────────────────────────────────────────────────
 * «لا حركةَ على فترةٍ مقفلة» — كلُّ كتابةٍ ماليةٍ تفحص الفترةَ وتُرفض بـ423.
 *
 * القاعدة: الفترةُ الشهرية التي يقع فيها تاريخُ الحركة — تُحجب (423) إن كانت
 * **مقفلةً فعلًا**: `posting_allowed=0` وحالتُها من حالات الإقفال
 * (soft_closed · closed · locked). أمّا `planned` فليست مقفلة — الإقفالُ فعلٌ
 * يقع على ما فُتح، وفترةٌ لم تُفتح بعدُ لا تجمّد وقائعَ التشغيل التي تقع فيها
 * (اجتهادٌ مسجَّل: حجبُ planned كان يُسقط مروحةَ أيامِ عملٍ مشروعة).
 * ولا فترةَ معرَّفةً = يُسمح (فجوةٌ معلَنة — الفتراتُ تُعرَّف سنويًّا).
 *
 * SQL مباشرٌ لا بوابة: يُستدعى من الناشر (App\Core) ومن الشاشات ومن CLI.
 */

if (!function_exists('ems_period_check')) {
    /**
     * @param  \mysqli $conn
     * @param  int     $companyId
     * @param  string  $date تاريخُ الحركة (Y-m-d أو Y-m-d H:i:s)
     * @return array{ok:bool, code:int, reason:string, period_id:?int, state:?string}
     */
    function ems_period_check(\mysqli $conn, $companyId, $date)
    {
        $out = array('ok' => true, 'code' => 200, 'reason' => '', 'period_id' => null, 'state' => null);
        $companyId = intval($companyId);
        $d = substr(trim((string) $date), 0, 10);
        if ($companyId <= 0 || $d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $out; // مدخلٌ ناقص: الحكمُ لغير هذا الحارس
        }
        $st = $conn->prepare(
            "SELECT id, state, posting_allowed FROM fin_financial_periods
              WHERE company_id = ? AND period_type = 'month'
                AND ? BETWEEN start_date AND end_date
              ORDER BY id DESC LIMIT 1");
        if (!$st) { error_log('[period-guard] prepare: ' . $conn->error); return $out; }
        $st->bind_param('is', $companyId, $d);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return $out; } // لا فترةَ معرَّفة — معلَن أعلاه
        $out['period_id'] = intval($row['id']);
        $out['state'] = (string) $row['state'];
        if (intval($row['posting_allowed']) !== 1
            && in_array((string) $row['state'], array('soft_closed', 'closed', 'locked'), true)) {
            $out['ok'] = false;
            $out['code'] = 423;
            $out['reason'] = '423 فترةٌ ماليةٌ مقفلة: ' . $d . ' يقع في فترةٍ حالتُها «'
                . $row['state'] . '» لا تقبل القيد — تُفتح استثنائيًّا من شاشة إقفال الفترات بقرارٍ موثَّق';
        }
        return $out;
    }
}

if (!function_exists('ems_period_close_blockers')) {
    /**
     * موانعُ إقفال الفترة (SPEC-01 #14): «زرُّ الإقفال يمنع حين يوجد غيرُ
     * مرحَّلٍ أو فرقٌ مفتوح — بقائمة الموانع وروابطها».
     *
     * @return array[] كلُّ مانعٍ: {label, count, link}
     */
    function ems_period_close_blockers(\mysqli $conn, $companyId, $periodId)
    {
        $blockers = array();
        $companyId = intval($companyId);
        $periodId = intval($periodId);
        $p = $conn->query("SELECT start_date, end_date FROM fin_financial_periods
                            WHERE id = {$periodId} AND company_id = {$companyId}")->fetch_assoc();
        if (!$p) { return $blockers; }
        $s = $conn->real_escape_string($p['start_date']);
        $e = $conn->real_escape_string($p['end_date']);

        // ① قيودٌ غيرُ مرحَّلة (مسودات) بتاريخ ترحيلٍ داخل الفترة
        $r = $conn->query("SELECT COUNT(*) c FROM fin_journal_entries
            WHERE company_id = {$companyId} AND COALESCE(is_deleted,0) = 0
              AND state = 'draft' AND posting_date BETWEEN '{$s}' AND '{$e}'");
        $n = $r ? intval($r->fetch_assoc()['c']) : 0;
        if ($n > 0) {
            $blockers[] = array('label' => 'قيودٌ غيرُ مرحَّلة', 'count' => $n,
                                'link' => 'journal_form_fin.php');
        }

        // ② أحداثٌ معتمدةٌ لم تُرحَّل قيودُها بعد (وسطُ الدورة — لا تُقفل عليها فترة)
        $r = $conn->query("SELECT COUNT(*) c FROM fin_financial_events
            WHERE company_id = {$companyId} AND COALESCE(is_deleted,0) = 0
              AND state IN ('approved','audited','fin_review')
              AND DATE(COALESCE(occurred_at, created_at)) BETWEEN '{$s}' AND '{$e}'");
        $n = $r ? intval($r->fetch_assoc()['c']) : 0;
        if ($n > 0) {
            $blockers[] = array('label' => 'أحداثٌ في وسط دورتها (معتمدة/مدقَّقة بلا ترحيل)', 'count' => $n,
                                'link' => 'events_list_fin.php');
        }

        // ③ فروقُ مطابقةٍ بنكيةٍ مفتوحة — الجدولُ يُبنى في H-13؛ غيابُه = صفرٌ معلَن
        $t = $conn->query("SHOW TABLES LIKE 'bank_recon_matches'");
        if ($t && $t->num_rows > 0) {
            $r = $conn->query("SELECT COUNT(*) c FROM bank_recon_matches m
                JOIN bank_statement_lines l ON l.line_id = m.line_id
                WHERE m.company_id = {$companyId} AND m.status = 'Difference'
                  AND l.value_date BETWEEN '{$s}' AND '{$e}'");
            $n = $r ? intval($r->fetch_assoc()['c']) : 0;
            if ($n > 0) {
                $blockers[] = array('label' => 'فروقُ مطابقةٍ بنكيةٍ مفتوحة', 'count' => $n,
                                    'link' => 'bank_recon_fin.php');
            }
        }
        return $blockers;
    }
}
