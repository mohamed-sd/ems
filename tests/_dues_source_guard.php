<?php
/**
 * tests/_dues_source_guard.php — مُقرِّرُ «لا خصمَ بلا مستندِ مصدرٍ» المشترك
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0192 · INJ-0193
 *
 * **لماذا ملفٌّ واحدٌ لا ثلاثةُ نُسَخ**: أربعةُ فواحصَ **تفترض** القيدَ
 * `ck_dues_debit_source` — تُمرّر `source_doc_type` في كلِّ خصمٍ تُدرجه — ولا
 * تُثبته إلا واحدةٌ. والافتراضُ غيرُ المُصرَّحِ **يمتثل ولا يحرس**: رفعُ القيدِ
 * من القاعدةِ يُبقيها خضراء، فتشهد لبنيةٍ زالت.
 *
 * ── والمعيارُ نصًّا (INJ-0192 · حقلُ «معيار القبول») ────────────────────────
 *   «تشغيلُ الاختباراتِ **الأربعة** يعطي رمزَ خروجٍ 0 على قاعدةٍ مهاجَرة،
 *    ويعطي **1** على قاعدةٍ **بلا القيد**.»
 * وهو مقيسٌ لا مُدَّعًى: رُفع القيدُ فعلًا فأعطت `dues_source_doc_test` واحدًا
 * وأعطت الثلاثُ **صفرًا** — فالثلاثُ ممتثلةٌ لا حارسة.
 *
 * ── ولماذا مُقرِّرٌ لا نسخٌ ثلاث ─────────────────────────────────────────────
 * لو كُتب التحقّقُ في كلِّ فاحصٍ لصار في المستودعِ **أربعةُ تعريفاتٍ** لمعنًى
 * واحدٍ تتفرّق عند أوّلِ تعديل — وهو عينُ الداءِ الذي كُتبت لأجلِه هذه الحزمة.
 * فالتعريفُ هنا واحدٌ، وكلُّ فاحصٍ **يطويه في عدّادِه** فيرث رمزَ خروجِه.
 *
 * ── وكيف يُثبِت **بنيويًّا** لا تطبيقيًّا ─────────────────────────────────────
 * لا يكفي أن يُردَّ الإدراج — فقد يردُّه فحصٌ تطبيقيٌّ في الخدمة. فيُحكَم على
 * **اسمِ القيدِ في نصِّ الخطأ** وعلى **رقمِه** (4025 MariaDB · 3819 MySQL)،
 * ويُدرَج بجملةٍ خامٍّ تتجاوز كلَّ طبقةِ خدمةٍ — فما رُدَّ إلا بالقاعدة.
 * و`config.php` يضبط mysqli على **عدمِ الرمي** فالحكمُ على المُرجَعِ صراحةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_assert_dues_source_guard')) {
    /**
     * يُرجع array('pass'=>int,'fail'=>int,'lines'=>array<string>)
     * فيطويها المُنادي في عدّادَيه — ولا يطبع ولا يُنهي بنفسِه.
     */
    function ems_assert_dues_source_guard(mysqli $conn, $companyId = 4)
    {
        /* ── وضعُ الإبلاغِ يُحيَّد ثم يُردّ ─────────────────────────────────────
           مُقرِّرٌ مشترَكٌ يُنادى من فواحصَ تختلف أوضاعُها: منها ما يرث مِقبضَ
           `config.php` — وهو مضبوطٌ على **عدمِ الرمي** — ومنها ما يبني
           `new mysqli` بنفسِه فيرث **رميَ PHP 8** الافتراضيّ. والخطوةُ ②
           تُفشِل إدراجًا **عمدًا**، فلو تُرك الوضعُ لرمى الاستثناءُ في النوعِ
           الثاني فمات المِسبارُ بـ255 قبل أن يُصدر حكمًا (وهو ما وقع فعلًا).
           فيُحيَّد الوضعُ هنا ويُردُّ عند الخروج — فالحكمُ على المُرجَعِ في
           الحالين، ولا يُغيّر المُقرِّرُ سلوكَ مُناديه بعد انتهائه. */
        $prevReport = mysqli_report(MYSQLI_REPORT_OFF);

        $pass = 0; $fail = 0; $lines = array();
        $ok = function ($cond, $label, $why = '') use (&$pass, &$fail, &$lines) {
            if ($cond) { $pass++; $lines[] = '  ✔ ' . $label; }
            else { $fail++; $lines[] = '  ✘ ' . $label . ($why !== '' ? '  ⟵ ' . $why : ''); }
            return (bool) $cond;
        };

        /* ── ① القيدُ **مُعلَنٌ في القاعدةِ** بالاسمِ وبالمنطوق ──────────────── */
        $clause = null;
        $r = $conn->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE()
                              AND CONSTRAINT_NAME = 'ck_dues_debit_source'");
        if ($r !== false && $r->num_rows) { $x = $r->fetch_row(); $clause = (string) $x[0]; }
        $ok($clause !== null,
            'قيدُ `ck_dues_debit_source` مُعلَنٌ في القاعدة',
            'الفاحصُ يفترضه في كلِّ خصمٍ يُدرجه — فغيابُه يجعله ممتثلًا لا حارسًا');
        if ($clause !== null) {
            $c = str_replace(array('`', ' '), '', mb_strtolower($clause));
            $ok(mb_strpos($c, 'source_doc_type') !== false && mb_strpos($c, 'debit') !== false,
                '   ومنطوقُه يربط الخصمَ بمستندِ المصدر (' . mb_substr($clause, 0, 60) . ')',
                'قيدٌ بالاسمِ نفسِه يحرس شيئًا آخرَ يشهد زورًا');
        }

        /* ── ② ويردُّ **فعلًا** خصمًا بلا مستندٍ — بجملةٍ خامٍّ تتجاوز الخدمة ── */
        $co = (int) $companyId;
        $per = '2099-12';   /* مدًى لا يمسُّ بيانًا حيًّا */
        $conn->query("DELETE FROM fin_dues WHERE company_id = {$co} AND period_ref = '{$per}'");
        $st = $conn->prepare(
            'INSERT INTO fin_dues (company_id, party_type, party_ref, direction, amount,
                                   currency, period_ref, source_doc_type, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW())');
        $rejected = false; $errno = 0; $err = '';
        if ($st) {
            $pt = 'supplier'; $pr = 0; $dir = 'debit'; $amt = 1.00; $cur = 'USD';
            $st->bind_param('isisdss', $co, $pt, $pr, $dir, $amt, $cur, $per);
            $rejected = ($st->execute() === false);
            $errno = (int) $st->errno;
            $err = (string) $st->error;
            $st->close();
        }
        $ok($rejected,
            '**وخصمٌ بلا مستندِ مصدرٍ مردودٌ فعلًا** — لا نصًّا',
            'القيدُ معلَنٌ ولا يردُّ ⇒ زخرفةٌ (وmysqli هنا لا يرمي فالحكمُ على المُرجَع)');
        $ok($errno === 4025 || $errno === 3819,
            '   والرادُّ **قيدُ CHECK بعينِه** لا سببٌ آخر (errno ' . $errno . ')',
            'رفضٌ لسببٍ آخرَ — عمودٌ ناقصٌ أو مفتاحٌ — يشهد زورًا');
        $ok($err !== '' && stripos($err, 'ck_dues_debit_source') !== false,
            '   ويُسمّيه نصُّ الخطأ — فالمنعُ **بنيويٌّ لا تطبيقيّ**',
            'منعٌ من طبقةِ الخدمةِ يزول بمسارٍ آخرَ يكتب مباشرةً');

        /* ── ③ ولا يمنع خصمًا **بمستندٍ** — قيدٌ يمنع كلَّ شيءٍ ليس حارسًا ──── */
        $st2 = $conn->prepare(
            'INSERT INTO fin_dues (company_id, party_type, party_ref, direction, amount,
                                   currency, period_ref, source_doc_type, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
        $accepted = false;
        if ($st2) {
            $pt = 'supplier'; $pr = 0; $dir = 'debit'; $amt = 1.00; $cur = 'USD';
            $sd = 'settlement';
            $st2->bind_param('isisdsss', $co, $pt, $pr, $dir, $amt, $cur, $per, $sd);
            $accepted = ($st2->execute() !== false);
            $st2->close();
        }
        $ok($accepted,
            '   ويقبل خصمًا **بمستندٍ** — فالقيدُ يميّز ولا يمنع كلَّ شيء',
            'قيدٌ يردُّ الصحيحَ والفاسدَ معًا يُمرَّر بالخطأِ على أنَّه حارس');
        $conn->query("DELETE FROM fin_dues WHERE company_id = {$co} AND period_ref = '{$per}'");

        mysqli_report($prevReport);
        return array('pass' => $pass, 'fail' => $fail, 'lines' => $lines);
    }
}
