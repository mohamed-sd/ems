<?php
/**
 * tests/_source_doc_seed.php — بذرةُ مستندِ المصدرِ لذممِ الاختبار (INJ-0036)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ بعد `INJ-0036` لا تُدرَج ذمّةٌ بلا `source_doc_id` (القيد `chk_recv_source_doc`).
 *   وبذورُ الاختبارِ كانت تصنع ذممًا **بلا فاتورةٍ مقابلة** — أي تختبر حالةً
 *   لا تقع في الإنتاج. فبدل إضعافِ القيدِ لتمرَّ البذرة، **تُصحَّح البذرةُ لتشبه
 *   الواقع**: فاتورةٌ صادرةٌ أوّلًا، ثم ذمّةٌ تشير إليها.
 *
 * ◆ وما تصنعه هذه الدالةُ تمحوه بنفسها عند انتهاء العملية — فلا رواسبَ في
 *   `tax_invoices` تُفسد تسلسلَ الفواتير.
 *
 * ◆ والتسلسلُ فريدٌ بـ(شركة × سنة × رقم) و(شركة × رقم) ⇒ يُختار `serial_seq`
 *   من فراغٍ عالٍ (900000+) بعيدًا عن التسلسلِ الحقيقي.
 */

if (!function_exists('seed_source_invoice')) {
    /**
     * يُنشئ (أو يُرجع) فاتورةً ضريبيةً صادرةً تصلح سندًا لذمّةِ اختبار.
     *
     * @return int معرِّفُ `tax_invoices.id` — يُوضع في `fin_receivables.source_doc_id`
     */
    function seed_source_invoice(mysqli $conn, $company, $serialNo, $clientId = 1,
                                 $amount = 0.0, $currency = 'USD', $claimId = 0)
    {
        static $registered = false;
        static $made = array();

        $company  = (int) $company;
        $serialNo = (string) $serialNo;

        $st = $conn->prepare('SELECT id FROM tax_invoices WHERE company_id = ? AND serial_no = ? LIMIT 1');
        $st->bind_param('is', $company, $serialNo);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) { return (int) $row['id']; }

        /* ◆ **عيبٌ مقيسٌ في هذه الدالةِ نفسِها**: `tax_invoices.claim_id` مفتاحٌ
             أجنبيٌّ **إلزاميٌّ** إلى `claims(id)`، وتمريرُ صفرٍ يرفضه
             `Cannot add or update a child row` فتفشل البذرةُ ويُقرأ فشلُها
             عيبًا في الفاحص. فإن لم يُمرَّر مستخلصٌ يُختار مستخلصٌ حقيقيٌّ
             لهذه الشركة. (وكذلك `client_id`.) */
        $claimId = (int) $claimId;
        if ($claimId <= 0) {
            $q = $conn->query("SELECT id, client_id FROM claims WHERE company_id = {$company}
                                ORDER BY id DESC LIMIT 1");
            $cr = $q ? $q->fetch_assoc() : null;
            if ($cr) {
                $claimId = (int) $cr['id'];
                if ((int) $clientId <= 0) { $clientId = (int) $cr['client_id']; }
            }
        }
        if ($claimId <= 0) {
            fwrite(STDERR, "seed_source_invoice: لا مستخلصَ في الشركة {$company} — لا تُبذر فاتورة\n");
            return 0;
        }
        if ((int) $clientId <= 0) {
            $q = $conn->query("SELECT client_id FROM claims WHERE id = {$claimId}");
            $cr = $q ? $q->fetch_row() : null;
            $clientId = $cr ? (int) $cr[0] : 1;
        }

        /* رقمٌ تسلسليٌّ في فراغٍ لا يصطدم بالحقيقي */
        $year = (int) date('Y');
        $seq  = (int) $conn->query("SELECT COALESCE(MAX(serial_seq), 900000) + 1 FROM tax_invoices
                                     WHERE company_id = {$company} AND serial_year = {$year}
                                       AND serial_seq >= 900000")->fetch_row()[0];

        $st = $conn->prepare("INSERT INTO tax_invoices
              (company_id, claim_id, client_id, serial_no, serial_year, serial_seq,
               currency, net_amount, tax_rate, tax_amount, total_amount, state, issued_at, issued_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 'issued', NOW(), 0)");
        $amt = round((float) $amount, 2);
        $cid = (int) $claimId; $cl = (int) $clientId;
        $st->bind_param('iiisiisdd', $company, $cid, $cl, $serialNo, $year, $seq, $currency, $amt, $amt);
        $ok = $st->execute();
        $id = $ok ? (int) $conn->insert_id : 0;
        if (!$ok) { fwrite(STDERR, 'seed_source_invoice: ' . $st->error . "\n"); }
        $st->close();
        if ($id > 0) { $made[] = $id; }

        if (!$registered) {
            $registered = true;
            register_shutdown_function(static function () use ($conn, &$made) {
                if (!$made) { return; }
                /* الذمّةُ تشير إلى الفاتورة — فتُمحى إشارتُها قبلها، وإلا بقي
                   مفتاحٌ معلَّقٌ يُفشِل فحصَ «لا مفتاحَ معلَّق». */
                $ids = implode(',', array_map('intval', $made));
                @$conn->query("DELETE FROM fin_receivables WHERE source_doc_id IN ({$ids})
                                 AND doc_type = 'invoice'");
                @$conn->query("DELETE FROM tax_invoices WHERE id IN ({$ids})");
            });
        }
        return $id;
    }
}
