<?php
/**
 * 2027_03_01 — تحقُّقٌ: طبيعةُ الضمانِ **يحكمها نوعُه** لا اشتقاقُ نصٍّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **هذه الهجرةُ كُتبت أوّلًا لتُصلح ما ليس عطلًا — وردَّتها القاعدة.**
 *
 * قرأتُ في قبولِ الحزمة: «الافتراضُ الآمن **خارجَ الميزانية**: لا يصير شيءٌ أصلًا
 * بالصدفة»، ورأيتُ سبعَ ضماناتٍ مشتقّةٍ من نصٍّ بـ`nature = 'asset'`
 * و`needs_review = 1`، فهمَمتُ بإعادتها إلى `off_balance` حتى المراجعة.
 *
 * **فردَّها `ck_cg_nature`** — وهو محقٌّ ونصُّه حاكم:
 *     kind = 'cash_retention'  ⇔  nature = 'asset'
 *     kind ≠ 'cash_retention'  ⇔  nature = 'off_balance'
 * أي أن الطبيعةَ **ليست اجتهادًا ولا افتراضًا آمنًا**: هي **دالةُ النوع**.
 * والسبعُ كلُّها `cash_retention` — ومحتجَزٌ نقديٌّ يعود إلينا **أصلٌ حقًّا**، لا
 * أصلٌ «بالصدفة». والقاعدةُ الآمنةُ تخصُّ ما عدا المحتجَزَ النقديّ، وهو ما تحمله
 * التسعةُ الأخرى (`off_balance`).
 *
 * فلا تُصلح هذه الهجرةُ شيئًا — **تُثبت الاتّساقَ وتُدوّن الدرس**: طبقةُ المنعِ في
 * القاعدةِ منعت تعديلًا مبنيًّا على قراءةٍ ناقصةٍ لنصِّ القبول. وشرطُ الفاحصِ
 * صُحِّح ليقيس القاعدةَ الحاكمةَ لا صيغةً مطلقة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$fail = array();
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── ① القاعدةُ الحاكمةُ موجودةٌ في القاعدةِ لا في الظنّ ─────────────────── */
$has = (int) $one("SELECT COUNT(*) FROM information_schema.check_constraints
                    WHERE constraint_schema = DATABASE() AND constraint_name = 'ck_cg_nature'");
echo "── ① القيدُ ck_cg_nature قائم: " . ($has > 0 ? "نعم ✔\n" : "لا ✘\n");
if ($has === 0) { $fail[] = 'القيدُ الحاكمُ غائب'; }

/* ── ② الاتّساقُ يُقاس صفًّا صفًّا ─────────────────────────────────────────── */
$bad = (int) $one("SELECT COUNT(*) FROM contract_guarantees
                    WHERE COALESCE(is_deleted,0) = 0
                      AND ((kind = 'cash_retention' AND nature <> 'asset')
                        OR (kind <> 'cash_retention' AND nature <> 'off_balance'))");
echo "── ② صفوفٌ طبيعتُها تخالف نوعَها: {$bad} " . ($bad === 0 ? "✔\n" : "✘\n");
if ($bad !== 0) { $fail[] = "{$bad} صفًّا مخالفًا"; }

$r = $db->query("SELECT kind, nature, COUNT(*) n,
                        SUM(CASE WHEN source_text IS NOT NULL THEN 1 ELSE 0 END) from_text,
                        SUM(CASE WHEN needs_review = 1 THEN 1 ELSE 0 END) review
                   FROM contract_guarantees WHERE COALESCE(is_deleted,0) = 0
                  GROUP BY kind, nature ORDER BY n DESC");
echo "── ③ التوزيعُ (نوع ⇒ طبيعة · منها مشتقٌّ من نصٍّ · وبانتظارِ إقرار)\n";
while ($r && ($x = $r->fetch_assoc())) {
    echo "     {$x['kind']} ⇒ {$x['nature']} : {$x['n']}"
       . " (من نصٍّ {$x['from_text']} · بانتظارِ إقرار {$x['review']})\n";
}

/* ── ④ المسبارُ السالب: هل يمنع القيدُ فعلًا؟ ────────────────────────────── */
$cid = (int) $one('SELECT contract_id FROM contract_guarantees
                    WHERE COALESCE(is_deleted,0) = 0 LIMIT 1');
$co  = (int) $one('SELECT company_id FROM contract_guarantees
                    WHERE COALESCE(is_deleted,0) = 0 LIMIT 1');
if ($cid > 0) {
    $probe = @$db->query("INSERT INTO contract_guarantees
        (company_id, contract_id, kind, nature, amount, currency, expiry_date, state, created_by)
        VALUES ({$co}, {$cid}, 'bank_guarantee', 'asset', 1, 'USD', '2099-01-01', 'active', 0)");
    echo '── ④ مسبارٌ سالب: ضمانٌ بنكيٌّ كـ«أصل» '
       . ($probe === false ? "مرفوضٌ ✔\n" : "مقبولٌ ✘ — القاعدةُ لا تمنع\n");
    if ($probe !== false) {
        $pid = (int) $db->insert_id;
        if ($pid > 0) { $db->query("DELETE FROM contract_guarantees WHERE id = {$pid}"); }
        $fail[] = 'القيدُ لا يمنع طبيعةً تخالف نوعَها';
    }
} else {
    echo "── ④ لا صفَّ يُبنى عليه مسبار — يُعلَن\n";
}

echo "\n" . (empty($fail)
    ? "✅ الطبيعةُ دالةُ النوعِ بقيدٍ في القاعدة — والمحتجَزُ النقديُّ أصلٌ بحقٍّ لا بالصدفة.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
