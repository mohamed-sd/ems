<?php
/**
 * 2027_02_19 — تصحيحُ قيدٍ كتبتُه أنا: «المعتمَدُ له مالٌ» خطأٌ **في الزمن**
 * ═══════════════════════════════════════════════════════════════════════════
 * هجرةُ `2027_02_18` نصَّبت `chk_unit_approved_has_money`:
 *     match_state = 'approved' ⇒ revenue_event_id IS NOT NULL AND created_by IS NOT NULL
 * وقد صدَق على البيانةِ القائمةِ كلِّها — **ثم رسب على مسارِ الكتابة**:
 *
 *   `EffectFanout` لا يُنشئ المالَ ثم يعتمد؛ بل **الصفُّ يُنشأ `approved` أوّلًا**
 *   ثم تنشر المروحةُ إيرادَه ومستحقَّه وتكلفتَه وتكتب مرساتَه بـUPDATE لاحق.
 *   فالقيدُ كان يطلب **الأثرَ قبلَ سببِه**، فردَّ الإدراجَ فصار `insert_id=0`
 *   صامتًا (`sql_mode` فارغ) فانهار `effect_fanout_test` على `$unit=null`.
 *
 * **الحكمُ**: «المعتمَدُ له مالٌ» قاعدةٌ صحيحةٌ لكنها **عبرَ جدولين وبعدَ
 * اكتمالِ المروحة** — ولا تُعبِّر عنها `CHECK` بطبيعتها. فمقامُها فاحصٌ يقيس
 * البيانةَ القائمة (`party_units_screen_test`)، لا طبقةُ منعٍ تكسر مسارًا سليمًا.
 *
 * ويبقى في القاعدةِ **النصفُ الذي هو حقًّا شرطُ كتابة**: لا اعتمادَ بلا فاعلٍ.
 * وهو الذي كان مخروقًا فعلًا (#672 كان `approved` بـ`created_by = NULL`).
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
$hasC = function ($name) use ($one) {
    return (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                        WHERE table_schema = DATABASE() AND table_name = 'fin_unit_records'
                          AND constraint_name = '{$name}'") > 0;
};

/* ── ① رفعُ القيدِ الخاطئِ في الزمن ─────────────────────────────────────── */
if ($hasC('chk_unit_approved_has_money')) {
    $ok = $db->query('ALTER TABLE fin_unit_records DROP CONSTRAINT chk_unit_approved_has_money');
    if (!$ok) { $fail[] = 'الرفع: ' . $db->error; }
    echo '── ① القيدُ chk_unit_approved_has_money: ' . ($ok ? "رُفع\n" : "تعذّر — {$db->error}\n");
} else {
    echo "── ① القيدُ مرفوعٌ سلفًا\n";
}

/* ── ② القيدُ الصحيح: لا اعتمادَ بلا فاعل ───────────────────────────────── */
$viol = (int) $one("SELECT COUNT(*) FROM fin_unit_records
                     WHERE COALESCE(is_deleted,0) = 0 AND match_state = 'approved'
                       AND created_by IS NULL");
if ($hasC('chk_unit_approved_has_actor')) {
    echo "── ② القيدُ chk_unit_approved_has_actor قائمٌ سلفًا\n";
} elseif ($viol === 0) {
    $ok = $db->query("ALTER TABLE fin_unit_records
                      ADD CONSTRAINT chk_unit_approved_has_actor CHECK (
                          match_state <> 'approved' OR is_deleted <> 0 OR created_by IS NOT NULL)");
    if (!$ok) { $fail[] = 'chk_unit_approved_has_actor: ' . $db->error; }
    echo '── ② القيدُ chk_unit_approved_has_actor: ' . ($ok ? "نُصِّب\n" : "تعذّر — {$db->error}\n");
} else {
    echo "── ② مؤجَّلٌ معلَنًا — {$viol} صفًّا معتمَدًا بلا فاعل\n";
}

/* ── ③ الشاهدُ المُشغَّل: المسارُ السليمُ يمرُّ · والمخالفُ يُردّ ─────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$CO = (int) $one('SELECT company_id FROM fin_unit_records ORDER BY id LIMIT 1');
$mark = 'MIG0219_' . getmypid();

// (أ) مسارُ المروحةِ السليم: يُنشأ `approved` بلا مرساةٍ ثم تُكتب لاحقًا
$sql = "INSERT INTO fin_unit_records
        (company_id, record_no, record_date, project_id, work_model, ops_qty, approved_qty,
         match_state, created_by)
        VALUES ({$CO}, '{$mark}_A', '2026-07-10', 1, 'hour', 5, 5, 'approved', 72)";
$a = $db->query($sql);
$aid = (int) $db->insert_id;
echo '     (أ) اعتمادٌ بفاعلٍ بلا مرساةٍ بعد — ' . ($a && $aid > 0 ? "مقبولٌ ✔ (مسارُ المروحة)\n" : "مردودٌ ✘ — {$db->error}\n");
if (!$a || $aid === 0) { $fail[] = 'المسارُ السليمُ مردود: ' . $db->error; }

// (ب) المخالفُ الحقيقيّ: اعتمادٌ بلا فاعل
$sql = "INSERT INTO fin_unit_records
        (company_id, record_no, record_date, project_id, work_model, ops_qty, approved_qty,
         match_state, created_by)
        VALUES ({$CO}, '{$mark}_B', '2026-07-10', 1, 'hour', 5, 5, 'approved', NULL)";
$b = $db->query($sql);
$bid = (int) $db->insert_id;
echo '     (ب) اعتمادٌ بلا فاعلٍ — ' . ($b === false ? "مردودٌ ✔\n" : "مقبولٌ ✘ (القيدُ لا يمنع)\n");
if ($b !== false) { $fail[] = 'اعتمادٌ بلا فاعلٍ مقبول — القيدُ لا يحرس'; }

// كنسُ المسبارَين بمفاتيحِهما المُرجَعةِ لا بقيمةٍ ظُنَّت
foreach (array($aid, $bid) as $pid) {
    if ($pid > 0) { $db->query("DELETE FROM fin_unit_records WHERE id = {$pid}"); }
}
$left = (int) $one("SELECT COUNT(*) FROM fin_unit_records WHERE record_no LIKE '{$mark}%'");
echo "     كُنس المسبارُ: باقٍ {$left} " . ($left === 0 ? "✔\n" : "✘\n");
if ($left !== 0) { $fail[] = "بقيت {$left} صفَّ مسبار"; }

echo "\n" . (empty($fail)
    ? "✅ القيدُ صار يقيس شرطَ الكتابةِ الحقيقيَّ: لا اعتمادَ بلا فاعل — ومسارُ المروحةِ يمرُّ.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
