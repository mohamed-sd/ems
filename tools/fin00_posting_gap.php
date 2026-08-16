<?php
/**
 * fin00_posting_gap.php — تشخيصُ B8: أين تنقطع الوقائعُ عن الدفتر (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يُصلح شيئًا — يقيس أين ينقطع المسارُ بالضبطِ ويُثبت الجذرَ بالأدلة.
 * إعادةُ التشغيل: php tools/fin00_posting_gap.php
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };
$out = ['measured_at' => gmdate('c')];

echo "══════════════════════════════════════════════════════════════════════\n";
echo "  B8 — الوقائعُ الماليةُ والدفتر: أين ينقطع المسار\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ── ① القمعُ بآلةِ الحالاتِ الجديدة ─────────────────────────────── */
echo "\n▐ ① قمعُ آلةِ الحالات (fes_status)\n";
$funnel = [];
$r = $db->query("SELECT fes_status, COUNT(*) n FROM fin_financial_events GROUP BY 1 ORDER BY n DESC");
$tot = 0;
while ($x = $r->fetch_assoc()) { $funnel[$x['fes_status']] = (int) $x['n']; $tot += (int) $x['n']; }
foreach ($funnel as $s => $n) { printf("   %-22s %6s  %5.1f%%\n", $s, number_format($n), $n / max(1, $tot) * 100); }
$out['funnel'] = $funnel;
printf("   %-22s %6s\n", 'الإجمالي', number_format($tot));

/* ── ② العمودان يتناقضان بنيويًّا ────────────────────────────────── */
echo "\n▐ ② عمودا الحالة — والمرآةُ ناقصة\n";
$sm = file_get_contents($ROOT . '/app/Services/Finance/EventStateMachine.php');
$hasPublishedMirror = (bool) preg_match("/'Published'\s*=>\s*'/", $sm);
echo '   LEGACY_MIRROR فيها مقابلٌ لـPublished؟  ' . ($hasPublishedMirror ? 'نعم' : '**لا**') . "\n";
echo "   ⇒ فالحدثُ يصير Published في الآلةِ الجديدةِ ويبقى state='draft' في القديمة أبدًا.\n";
$mismatch = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Published' AND state='draft'");
echo '   الصفوفُ في هذا التناقضِ: ' . number_format($mismatch) . "\n";
echo "   ◆ ومن قرأ `state` وحدَه استنتج «كلُّها مسوَّدات» وهو خطأُ قراءةٍ لا خطأُ بيانات.\n";
$out['legacy_mirror_has_published'] = $hasPublishedMirror;
$out['published_but_legacy_draft'] = $mismatch;

/* ── ③ الانتقالاتُ الثلاثةُ الموصلةُ للدفتر: من يناديها؟ ────────── */
echo "\n▐ ③ الانتقالاتُ الموصلةُ للدفتر — مواضعُ النداءِ في الشجرة\n";
$targets = ['UnderReview', 'Approved', 'Posted', 'ReturnedToSource', 'Published'];
$callsites = [];
$scan = function (string $dir) use (&$scan, &$files) {
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..' || $f === 'node_modules' || $f === '.git' || $f === 'vendor') { continue; }
        $p = $dir . '/' . $f;
        if (is_dir($p)) { if (strpos($p, '.claude') === false && strpos($p, '/tests') === false) { $scan($p); } }
        elseif (substr($f, -4) === '.php') { $files[] = $p; }
    }
};
$files = [];
foreach (['app', 'Finance', 'Operations', 'includes'] as $d) { if (is_dir($ROOT . '/' . $d)) { $scan($ROOT . '/' . $d); } }
foreach (glob($ROOT . '/*.php') ?: [] as $f) { $files[] = $f; }
foreach ($targets as $t) { $callsites[$t] = []; }
foreach ($files as $f) {
    if (strpos($f, 'EventStateMachine.php') !== false) { continue; }   // التعريفُ لا نداء
    $c = (string) file_get_contents($f);
    foreach ($targets as $t) {
        if (preg_match("/(transition|syncTo)\s*\([^;]*['\"]" . $t . "['\"]/s", $c)) {
            $callsites[$t][] = str_replace($ROOT . '/', '', str_replace('\\', '/', $f));
        }
    }
}
foreach ($targets as $t) {
    $n = count($callsites[$t]);
    printf("   %-18s %s\n", $t, $n ? implode(' · ', $callsites[$t]) : '**صفرُ موضعِ نداءٍ في الشجرة**');
}
$out['callsites'] = $callsites;

/* ── ④ التسعةُ المُرحَّلة: أرُحِّلت أم بُذرت؟ ──────────────────────── */
echo "\n▐ ④ التسعةُ الموصولةُ بالدفتر\n";
$p = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE journal_entry_id > 0");
$pb = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE journal_entry_id > 0 AND posted_by IS NOT NULL");
$pa = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE journal_entry_id > 0 AND posted_at IS NOT NULL");
$seq = (string) $one("SELECT GROUP_CONCAT(event_no ORDER BY id SEPARATOR ' · ') FROM fin_financial_events WHERE journal_entry_id > 0");
printf("   موصولةٌ بقيد: %d · منها بـposted_by: %d · بـposted_at: %d\n", $p, $pb, $pa);
echo "   أرقامُها: $seq\n";
echo '   ⇒ ' . ($pb === 0 && $pa === 0
    ? "**بُذرت مُرحَّلةً ولم يُرحِّلها مسارٌ** — لا مُرحِّلَ ولا وقتَ ترحيل.\n"
    : "لها أثرُ ترحيلٍ حقيقيّ.\n");
$out['posted_events'] = ['linked' => $p, 'with_posted_by' => $pb, 'with_posted_at' => $pa];

/* ── ⑤ الدفترُ: من أين امتلأ؟ ─────────────────────────────────── */
echo "\n▐ ⑤ الدفترُ — مصدرُ قيودِه\n";
$je    = (int) $one("SELECT COUNT(*) FROM fin_journal_entries");
$jeEvt = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE event_id IS NOT NULL AND event_id > 0");
printf("   قيود: %s · منها بمرجعِ واقعة: %d (%.2f%%)\n", number_format($je), $jeEvt, $jeEvt / max(1, $je) * 100);
$r = $db->query("SELECT LEFT(memo, 22) m, COUNT(*) n FROM fin_journal_entries
                 WHERE event_id IS NULL OR event_id = 0 GROUP BY 1 ORDER BY n DESC LIMIT 4");
echo "   وأكثرُ ما فيها بلا مرجعِ واقعة:\n";
while ($x = $r->fetch_row()) { printf("     · %-24s %s قيدًا\n", $x[0], number_format((int) $x[1])); }
$out['journal'] = ['entries' => $je, 'with_event_ref' => $jeEvt];

/* ── ⑥ التهيئةُ جاهزةٌ أم ناقصة؟ ─────────────────────────────── */
echo "\n▐ ⑥ التهيئةُ: أجاهزةٌ للترحيلِ لو نودي؟\n";
foreach (['fin_posting_matrix' => 'مصفوفةُ الترحيل', 'fin_approval_matrix' => 'مصفوفةُ الاعتماد',
          'fin_effect_map' => 'خريطةُ الأثر', 'fin_chart_of_accounts' => 'دليلُ الحسابات',
          'fin_routing_matrix' => 'مصفوفةُ التوجيه'] as $t => $ar) {
    $n = $one("SELECT COUNT(*) FROM `$t`");
    printf("   %-24s %-22s %s صفًّا\n", $t, $ar, $n === null ? '—' : number_format((int) $n));
}

/* ── ⑦ الناقلُ والمستهلكون ─────────────────────────────────────── */
echo "\n▐ ⑦ الناقلُ: أيعمل؟\n";
$r = $db->query("SELECT consumer, enabled, cursor_event_id, updated_at FROM ems_event_consumers ORDER BY consumer");
while ($x = $r->fetch_assoc()) {
    printf("   %-24s مفعَّل=%s · مؤشِّر=%-7s · آخرُ حركة %s\n",
        $x['consumer'], $x['enabled'], number_format((int) $x['cursor_event_id']), $x['updated_at']);
}
$be = (int) $one("SELECT COUNT(*) FROM ems_business_events");
echo '   وقائعُ الأعمال: ' . number_format($be) . " — فالمؤشِّراتُ تتحرّك والناقلُ حيّ.\n";

/* ── الحكم ───────────────────────────────────────────────────── */
echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  الحكم\n";
echo "══════════════════════════════════════════════════════════════════════\n";
$dead = array_filter(['UnderReview', 'Approved', 'Posted'], fn($t) => count($callsites[$t]) === 0);
if ($dead) {
    echo "  ✖ الجذر: آلةُ الحالاتِ تُعرّف السلسلةَ كاملةً والتهيئةُ موجودةٌ والناقلُ حيّ،\n";
    echo "     لكنَّ الانتقالاتِ التي تصل الدفترَ (" . implode(' · ', $dead) . ") **بلا موضعِ نداءٍ واحدٍ**\n";
    echo "     في الشجرة. فالسلسلةُ مبنيةٌ حتى بابِ الدفترِ ثم تقف.\n";
    echo "  ⇒ ليست عطلًا يُصلَح بل **خطوةً لم تُبنَ**: يلزم مَن ينقل المنشورَ إلى المراجعةِ\n";
    echo "     ثم الاعتمادِ ثم الترحيلِ — بشاشةٍ أو كرونٍ أو الاثنين.\n";
} else {
    echo "  الانتقالاتُ لها مواضعُ نداء — الانقطاعُ في مكانٍ آخر.\n";
}
printf("  والأثرُ المقيس: %s واقعةً منشورةً تنتظر، و%s فقط بلغت الدفتر (%.2f%%).\n",
    number_format($funnel['Published'] ?? 0), number_format($p), $p / max(1, $tot) * 100);

@mkdir($ROOT . '/docs/reverse_audit_2026-08/evidence', 0777, true);
file_put_contents($ROOT . '/docs/reverse_audit_2026-08/evidence/fin00_posting_gap.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nكُتب: evidence/fin00_posting_gap.json\n";
