<?php
/**
 * se05_lock_proof.php — إثباتُ القفلِ والقادحِ معًا (اختبارٌ سلبيٌّ مزدوج)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكمُ مفروضٌ بطبقتين، ولكلٍّ نطاقُها — فتُختبر كلٌّ في نطاقِها وحدَها:
 *   ① القادحُ ق-18: تاريخٌ ≥ 2026-08-05 · يستثني الحالاتِ المنتهية → رمز 1644
 *   ② القفلُ uq_shift_ue: بلا بوابةِ تاريخ → يمسك ما قبلَ 08-05 برمز 1062
 *   ③ ولا يمنع أيُّهما بديلًا مشروعًا بعدَ رفضِ الأصل (مواءمةُ 2027_04_23)
 *   ④ والبذورُ الموسومةُ تتعايش
 * وكلُّ ما يكتبه يُنظَّف بالوسم.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');

$MARK = 'se05-proof';
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };
$fails = 0;
$ok = function (bool $c, string $m) use (&$fails) { echo ($c ? '  ✔ ' : '  ✘ ') . $m . "\n"; if (!$c) { $fails++; } };

$CO = (int) $one("SELECT company_id FROM unit_entries ORDER BY id DESC LIMIT 1");
$EQ = 999901;
$OLD = '2025-01-15';   // قبلَ بوابةِ القادح — القفلُ وحدَه يحرس
$NEW = '2026-08-16';   // بعدَها — القادحُ يحرس
$before = (int) $one("SELECT COUNT(*) FROM unit_entries");

$ins = function (string $date, string $state = 'draft') use ($db, $CO, $EQ, $MARK) {
    $st = $db->prepare("INSERT INTO unit_entries
        (company_id, entry_no, entry_date, equipment_id, shift, unit_type, qty,
         record_basis, state, source_ref, note, created_at)
        VALUES (?, ?, ?, ?, 'day', 'hour', 8.00, 'contract', ?, 'SE05', ?, NOW())");
    if (!$st) { return ['ok' => false, 'errno' => 0, 'error' => 'prepare: ' . $db->error, 'id' => 0]; }
    $no = 'SE05-' . substr(md5($date . $state . microtime(true)), 0, 12);
    $note = $MARK . ' ' . $date;
    $st->bind_param('ississ', $CO, $no, $date, $EQ, $state, $note);
    $exec = $st->execute();
    $errno = $st->errno; $error = $st->error;   // قبلَ close — $db->errno يصفر بعدَها
    $id = $exec ? (int) $db->insert_id : 0;
    $st->close();
    return ['ok' => $exec, 'errno' => $errno, 'error' => $error, 'id' => $id];
};

echo "══ إثباتُ الطبقتين — كيان=$CO · آلية=$EQ ══\n";
echo '  تحتَ القفلِ الآن: ' . number_format((int) $one("SELECT COUNT(*) FROM unit_entries WHERE shift_slot_key IS NOT NULL")) . " صفًّا\n";

/* ── ① القفلُ وحدَه: تاريخٌ قبلَ بوابةِ القادح ───────────────────── */
echo "\n── ① تاريخٌ قبلَ 2026-08-05 — القادحُ صامتٌ فالقفلُ وحدَه يحرس ──\n";
$a1 = $ins($OLD);
$ok($a1['ok'], 'الأولُ مرّ' . ($a1['ok'] ? '' : ' — ' . $a1['error']));
$a2 = $ins($OLD);
$ok(!$a2['ok'], 'التكرارُ رُفض');
$ok((int) $a2['errno'] === 1062, 'برمز 1062 (من القفلِ لا من القادح) — وجد: ' . $a2['errno']);
echo '      · ' . mb_substr((string) $a2['error'], 0, 100) . "\n";

/* ── ② القادحُ: تاريخٌ بعدَ البوابة ──────────────────────────────── */
echo "\n── ② تاريخٌ بعدَ 2026-08-05 — القادحُ يسبق ──\n";
$b1 = $ins($NEW);
$ok($b1['ok'], 'الأولُ مرّ');
$b2 = $ins($NEW);
$ok(!$b2['ok'], 'التكرارُ رُفض');
$ok((int) $b2['errno'] === 1644, 'برمز 1644 (من قادحِ ق-18) — وجد: ' . $b2['errno']);

/* ── ③ البديلُ المشروعُ بعدَ الرفضِ لا يُمنع ───────────────────────── */
echo "\n── ③ بعدَ رفضِ الأصلِ يجوز إدخالُ بديلٍ لنفسِ الخانة ──\n";
$upd = $db->query("UPDATE unit_entries SET state='rejected' WHERE id=" . (int) $a1['id']);
$ok((bool) $upd, 'حُوِّل الأصلُ إلى rejected');
$key = $one("SELECT shift_slot_key FROM unit_entries WHERE id=" . (int) $a1['id']);
$ok($key === null, 'مفتاحُ القفلِ أفرغ نفسَه تلقائيًّا (STORED يُعاد حسابُه) فتحرّرت الخانة');
$a3 = $ins($OLD);
$ok($a3['ok'], 'البديلُ مرّ — القفلُ لم يعد أشدَّ من الحكم' . ($a3['ok'] ? '' : ' ✘ ' . $a3['error']));

/* ── ④ البذورُ تتعايش ───────────────────────────────────────────── */
echo "\n── ④ البذورُ الموسومةُ لم يمسَّها القفل ──\n";
$dupSeed = (int) $one("SELECT COUNT(*) FROM (SELECT company_id, entry_date, shift, equipment_id
                       FROM unit_entries WHERE seed_tag IS NOT NULL GROUP BY 1,2,3,4 HAVING COUNT(*) > 1) d");
$ok($dupSeed === 120, "ما زالت $dupSeed مجموعةً مبذورةً متعايشة");
$ok((int) $one("SELECT COUNT(*) FROM unit_entries WHERE seed_tag IS NOT NULL") === 9880, 'المبذورُ ثابتٌ عند 9,880');

/* ── ⑤ التنظيف ─────────────────────────────────────────────────── */
echo "\n── ⑤ التنظيف ──\n";
$db->query("DELETE FROM unit_entries WHERE note LIKE '$MARK%'");
echo '      · حُذف ' . $db->affected_rows . " صفًّا كتبه المسبار\n";
$after = (int) $one("SELECT COUNT(*) FROM unit_entries");
$ok($after === $before, 'unit_entries عادت إلى ' . number_format($before));
$ok((int) $one("SELECT COUNT(*) FROM unit_entries WHERE note LIKE '$MARK%'") === 0, 'صفرُ بقية');

echo "\n" . ($fails === 0
    ? "✔ الطبقتان تعملان في نطاقَيهما، والبديلُ المشروعُ لا يُمنع — صفرُ إخفاق\n"
    : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
