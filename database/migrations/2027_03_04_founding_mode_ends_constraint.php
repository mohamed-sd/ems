<?php
/**
 * 2027_03_04 — حارسٌ مفقودٌ جعل مسبارًا سالبًا يترك وضعَ التأسيسِ مُشتعلًا
 * ═══════════════════════════════════════════════════════════════════════════
 * **السلسلةُ كاملةً كما قِستها**:
 *
 * ① القيدُ `chk_fm_ends` **معلَنٌ في المصدر**
 *    (`2026_08_02_sec01_structure.sql:339`): `enabled = 0 OR ends_at IS NOT NULL`
 *    — أي **لا وضعَ تأسيسٍ مشتعلٌ بلا أفقٍ زمنيّ**. وهو **مفقودٌ من القاعدةِ
 *    الحيّة** (سقط في إعادةِ بناءِ 2026-08-03 مع القواعدِ الأخرى).
 *
 * ② و`tests/sec_structure_test.php:95` **مسبارٌ سالبٌ متعمَّد**:
 *      UPDATE founding_mode SET enabled = 1, ends_at = NULL WHERE mode = 'discovery'
 *    ينتظر أن **تردَّه القاعدة**. ولأن القيدَ مفقودٌ **نجحت الكتابة** — فرسب
 *    الفاحصُ (بحقٍّ: الحارسُ غائب) **وترك وضعَ التأسيسِ مشتعلًا بلا نهاية**.
 *
 * ③ وأثرُ ذلك **حقيقيٌّ ومحدَّد**: `PositionService::audit()`
 *    (`app/Services/Security/PositionService.php:240`) يقرأ `enabled = 1` وحدَه
 *    فيوسم **كلَّ صفٍّ في سجلِّ تغييرِ الصلاحيات** بـ`founding_mode = 1` —
 *    أي أن قراراتِ الصلاحياتِ تُسجَّل كأنها جرت في طورِ التأسيسِ **إلى الأبد**.
 *    (ولا بوابةَ تُرتخي: `isDiscoveryOpen()` تشترط `ends_at >= NOW()` فـ`NULL`
 *     تُقرأ مُغلقةً — فالخللُ في **صدقِ السجل** لا في تخفيفِ الحراسة.)
 *
 * **العلاجُ يُغلق البابَ لا العَرَض**: يُسوَّى الصفُّ الحيُّ (وبانرُه يحمل وسمَ
 * فاحصٍ `FB10T…` — أي لم يكن قرارَ تأسيسٍ قطّ)، ثم يُنصَّب القيدُ، **فيصير
 * المسبارُ السالبُ مردودًا كما أُريد له** ولا يستطيع فاحصٌ ولا مسارُ رمزٍ أن
 * يترك الوضعَ مشتعلًا بعد اليوم.
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

/* ── ① الحالُ قبل المسّ ─────────────────────────────────────────────────── */
echo "── ① وضعُ التأسيسِ الآن\n";
$r = $db->query('SELECT mode_id, mode, enabled, started_at, ends_at, banner_text FROM founding_mode ORDER BY mode_id');
$rows = array();
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
foreach ($rows as $x) {
    echo "     [{$x['mode_id']}] {$x['mode']} · enabled={$x['enabled']}"
       . ' · ends_at=' . ($x['ends_at'] === null ? 'NULL' : $x['ends_at'])
       . ' · «' . mb_substr((string) $x['banner_text'], 0, 46) . "»\n";
}
$viol = (int) $one('SELECT COUNT(*) FROM founding_mode WHERE enabled = 1 AND ends_at IS NULL');
echo "     مشتعلٌ بلا أفقٍ زمنيّ: {$viol}\n";

$hasC = (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                     WHERE constraint_schema = DATABASE() AND table_name = 'founding_mode'
                       AND constraint_name = 'chk_fm_ends'");
echo '     القيدُ chk_fm_ends: ' . ($hasC > 0 ? "قائم\n" : "**مفقود**\n");

/* ── ② أثرُه على صدقِ السجل — يُعلَن ولا يُكتَم ─────────────────────────── */
$stamped = (int) $one('SELECT COUNT(*) FROM permission_audit_events WHERE founding_mode = 1');
$total   = (int) $one('SELECT COUNT(*) FROM permission_audit_events');
echo "── ② سجلُّ تغييرِ الصلاحيات: {$stamped} من {$total} صفًّا موسومٌ «طورُ التأسيس»\n";
if ($viol > 0 && $stamped > 0) {
    echo "     ⚠ وما وُسم أثناء الاشتعالِ الخاطئ **لا يُعاد كتابتُه** — السجلُّ\n"
       . "       insert-only بطبيعتِه، وتغييرُ ماضيه أسوأُ من وسمٍ خاطئٍ معلَن.\n";
}

/* ── ③ التسوية: صفٌّ بانرُه وسمُ فاحصٍ لم يكن قرارَ تأسيس ─────────────────── */
if ($viol > 0) {
    $ok = $db->query("UPDATE founding_mode
                         SET enabled = 0, ends_at = NULL,
                             closure_ref = CONCAT('2027_03_04: أُطفئ — اشتعالٌ خلّفه مسبارٌ سالبٌ ',
                                                  'نجح لغيابِ chk_fm_ends، لا قرارَ تأسيس')
                       WHERE enabled = 1 AND ends_at IS NULL");
    if (!$ok) { $fail[] = 'التسوية: ' . $db->error; }
    echo '── ③ أُطفئ ' . ($ok ? $db->affected_rows : 0) . " صفًّا (بسببٍ مكتوبٍ في closure_ref)\n";
} else {
    echo "── ③ لا صفَّ مشتعلًا بلا أفق — لا تسوية\n";
}

/* ── ④ القيدُ يُنصَّب ─────────────────────────────────────────────────────── */
if ($hasC === 0) {
    $left = (int) $one('SELECT COUNT(*) FROM founding_mode WHERE enabled = 1 AND ends_at IS NULL');
    if ($left > 0) {
        $fail[] = "لا يُنصَّب قيدٌ فوقَ {$left} مخالفٍ حيّ";
    } else {
        $ok = $db->query('ALTER TABLE founding_mode ADD CONSTRAINT chk_fm_ends
                          CHECK (enabled = 0 OR ends_at IS NOT NULL)');
        if (!$ok) { $fail[] = 'chk_fm_ends: ' . $db->error; }
        echo '── ④ القيدُ chk_fm_ends: ' . ($ok ? "نُصِّب\n" : "تعذّر — {$db->error}\n");
    }
} else {
    echo "── ④ القيدُ قائمٌ سلفًا\n";
}

/* ── ⑤ الشاهدُ: المسبارُ السالبُ نفسُه يجب أن يُردَّ الآن ─────────────────── */
echo "── ⑤ الشاهدُ المُشغَّل — المسبارُ السالبُ الذي كان ينجح\n";
$probe = @$db->query("UPDATE founding_mode SET enabled = 1, ends_at = NULL WHERE mode = 'discovery'");
echo '     UPDATE enabled=1, ends_at=NULL ⇒ '
   . ($probe === false ? "**مردودٌ** ✔ (" . mb_substr($db->error, 0, 46) . ")\n" : "مقبولٌ ✘ — القيدُ لا يمنع\n");
if ($probe !== false) {
    $db->query("UPDATE founding_mode SET enabled = 0 WHERE mode = 'discovery'");
    $fail[] = 'القيدُ لا يمنع اشتعالًا بلا أفق';
}

// والمسموحُ يمرُّ: اشتعالٌ **بأفقٍ** مقبولٌ ثم يُعاد
$okWith = @$db->query("UPDATE founding_mode SET enabled = 1, ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                        WHERE mode = 'discovery'");
echo '     واشتعالٌ **بأفقِ 30 يومًا** ⇒ ' . ($okWith ? "مقبولٌ ✔ (القيدُ يمنع الأبدَ لا التأسيس)\n" : "مردودٌ ✘\n");
if (!$okWith) { $fail[] = 'القيدُ يمنع تأسيسًا مشروعًا'; }
$db->query("UPDATE founding_mode SET enabled = 0, ends_at = NULL WHERE mode = 'discovery'");

$after = (int) $one('SELECT COUNT(*) FROM founding_mode WHERE enabled = 1 AND ends_at IS NULL');
echo "     مشتعلٌ بلا أفقٍ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقي {$after} مشتعلًا بلا أفق"; }

$checks = (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                       WHERE constraint_schema = DATABASE() AND constraint_type = 'CHECK'");
echo "     قواعدُ الحمايةِ الحيّةُ كلُّها: {$checks}\n";

echo "\n" . (empty($fail)
    ? "✅ لا وضعَ تأسيسٍ بلا أفقٍ بعد اليوم — والمسبارُ السالبُ يُردُّ كما أُريد له، فلا يترك اشتعالًا خلفه.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
