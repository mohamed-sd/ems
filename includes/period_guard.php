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

        // ④ AC-E01-05 «لا إقفالَ لمبدئي»: أحداثُ الفترة الموسومةُ مبدئيةً
        //    (fin_event_grades.grade='provisional') تمنع الإقفال حتى تُرقَّى
        //    نهائيةً بمستندها — الدرجةُ جدولٌ جانبيٌّ (هجرة 2026_11_17) حتى
        //    تنقضي نافذةُ تجميد المخطط فيُطوى عمودًا إن شاء المالك.
        $t = $conn->query("SHOW TABLES LIKE 'fin_event_grades'");
        if ($t && $t->num_rows > 0) {
            $r = $conn->query("SELECT COUNT(*) c FROM fin_event_grades g
                JOIN fin_financial_events ev ON ev.id = g.event_id
                WHERE g.company_id = {$companyId} AND g.grade = 'provisional'
                  AND COALESCE(ev.is_deleted,0) = 0
                  AND DATE(COALESCE(ev.occurred_at, ev.created_at)) BETWEEN '{$s}' AND '{$e}'");
            $n = $r ? intval($r->fetch_assoc()['c']) : 0;
            if ($n > 0) {
                $blockers[] = array('label' => 'أحداثٌ بدرجة أثرٍ مبدئيةٍ لم تُرقَّ (AC-E01-05)', 'count' => $n,
                                    'link' => 'events_list_fin.php');
            }
        }

        /* ③ فروقُ مطابقةٍ بنكيةٍ مفتوحة — الجدولُ يُبنى في H-13؛ غيابُه = صفرٌ معلَن
         * ═══════════════════════════════════════════════════════════════════
         * ◆ **كان هذا الحارسُ ميتًا ولم يشتعل قطُّ.** خمسةُ أسماءٍ خاطئةٍ في
         *   استعلامٍ واحد — قِيست بـ`SHOW COLUMNS` لا بالحدس:
         *     `m.status`      ⇒ العمودُ `m.state`
         *     `'Difference'`  ⇒ قيمةُ الـENUM `'open_difference'`
         *     `m.line_id`     ⇒ `m.statement_line_id`
         *     `l.line_id`     ⇒ `l.id`
         *     `l.value_date`  ⇒ `l.txn_date`
         *   و`config.php` يضبط mysqli على **عدمِ الرمي**، فالاستعلامُ يعود
         *   `false` صامتًا و`$n` يصير 0 — فيُقرأ «لا فروقَ مفتوحة» وهو خطأُ
         *   استعلامٍ. أي أنَّ فترةً كان يمكن إقفالُها وفيها فروقٌ بنكيةٌ مفتوحة.
         * ◆ **وموضعان يسجّلان الفرقَ لا واحد**: `bank_recon_matches.state` (سجلُّ
         *   المطابقة) و`bank_statement_lines.match_state` (وسمُ السطر). فسطرٌ
         *   موسومٌ `difference` بلا صفِّ مطابقةٍ فرقٌ مفتوحٌ كذلك — فيُحسَب
         *   الاثنان **بلا تكرارِ عدٍّ** (`NOT EXISTS` على الثاني).
         * ◆ ويُفحَص مُرجَعُ الاستعلامِ: `null` تعني **فشلًا يُعلَن** لا صفرًا يُسكَت
         *   عنه — فحارسٌ لا يعرف أنه أعمى أخطرُ من حارسٍ غائب.
         * ═══════════════════════════════════════════════════════════════════ */
        $t = $conn->query("SHOW TABLES LIKE 'bank_recon_matches'");
        if ($t && $t->num_rows > 0) {
            $q = $conn->query("SELECT
                  (SELECT COUNT(*) FROM bank_recon_matches m
                     JOIN bank_statement_lines l2 ON l2.id = m.statement_line_id
                    WHERE m.company_id = {$companyId} AND m.state = 'open_difference'
                      AND l2.txn_date BETWEEN '{$s}' AND '{$e}')
                + (SELECT COUNT(*) FROM bank_statement_lines l
                    WHERE l.company_id = {$companyId} AND l.match_state = 'difference'
                      AND l.txn_date BETWEEN '{$s}' AND '{$e}'
                      AND NOT EXISTS (SELECT 1 FROM bank_recon_matches m2
                                       WHERE m2.statement_line_id = l.id)) AS c");
            if ($q === false) {
                /* الفشلُ يُعلَن حاجبًا — فلا يُقفَل دورٌ ماليٌّ بحارسٍ أعمى */
                $blockers[] = array(
                    'label' => 'تعذّر فحصُ فروقِ المطابقةِ البنكية — يُعالَج قبل الإقفال',
                    'count' => -1, 'link' => 'bank_recon_fin.php');
            } else {
                $n = intval($q->fetch_assoc()['c']);
                if ($n > 0) {
                    $blockers[] = array('label' => 'فروقُ مطابقةٍ بنكيةٍ مفتوحة', 'count' => $n,
                                        'link' => 'bank_recon_fin.php');
                }
            }
        }
        return $blockers;
    }
}

if (!function_exists('ems_event_grade_set')) {
    /**
     * AC-E01-05: وسم حدثٍ ماليٍّ بدرجة أثره — provisional لا يُقفل عليه،
     * والترقية إلى final تُختم بفاعلها ولحظتها. idempotent بمفتاح الحدث.
     */
    function ems_event_grade_set(\mysqli $conn, $companyId, $eventId, $grade, $reason = '', $actorId = 0)
    {
        $grade = $grade === 'final' ? 'final' : 'provisional';
        $st = $conn->prepare("INSERT INTO fin_event_grades (company_id, event_id, grade, reason, created_by,
                                  finalized_at, finalized_by)
                              VALUES (?, ?, ?, ?, ?,
                                  IF(? = 'final', NOW(), NULL), IF(? = 'final', ?, NULL))
                              ON DUPLICATE KEY UPDATE grade = VALUES(grade), reason = VALUES(reason),
                                  finalized_at = IF(VALUES(grade)='final', COALESCE(finalized_at, NOW()), NULL),
                                  finalized_by = IF(VALUES(grade)='final', COALESCE(finalized_by, VALUES(finalized_by)), NULL)");
        if (!$st) { error_log('[event-grade] prepare: ' . $conn->error); return false; }
        $co = intval($companyId);
        $ev = intval($eventId);
        $ac = intval($actorId);
        $st->bind_param('iississi', $co, $ev, $grade, $reason, $ac, $grade, $grade, $ac);
        $ok = $st->execute();
        if (!$ok) { error_log('[event-grade] execute: ' . $st->error); }
        $st->close();
        return (bool) $ok;
    }
}
