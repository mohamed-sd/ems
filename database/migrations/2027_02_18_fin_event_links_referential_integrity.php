<?php
/**
 * 2027_02_18 — تكاملُ دفترِ روابطِ المال المرجعيُّ (fin_event_links)
 * ═══════════════════════════════════════════════════════════════════════════
 * **المكشوفُ بالقياس** — لا بالفرض:
 *   ① `fin_event_links` — دفترُ العطالةِ الذي يمنع تكرارَ النشرِ ويُسنِد كلَّ
 *      أثرٍ ماليٍّ إلى حدثِه — كان بلا **أيِّ** مفتاحٍ أجنبيّ (صفر). فحمل **ثمانيةَ
 *      صفوفٍ** تشير إلى `event_id` **لا وجودَ له** (`fin_financial_events` يبدأ
 *      من #46 وهذه في النطاقِ #12..#525).
 *   ② الجذرُ في المنتجِ لا في البيانة: `EffectFanout::forUnitRecord` كان يتبنّى
 *      مرساةَ `revenue_event_id`/`supplier_due_id` **بلا التحقُّقِ من هدفِها**،
 *      فيربط رابطًا معلَّقًا و**يتخطّى نشرَ الحدثِ الحقيقيِّ** ثم يُبلِّغ
 *      `adopted` — أي **نجاحًا**. ومرساةٌ واحدةٌ فاسدةٌ تسري إلى خمسةِ مواضع.
 *      **أُصلح في المنتجِ (`stale_anchors`) قبلَ هذه الهجرة.**
 *   ③ عشرةُ صفوفٍ تجريبيةٍ في `fin_unit_records` (#663..#672) تحمل مراسيَ
 *      ملفَّقةً (11..19 — لا يحلُّ منها واحد) وصفَّان بلا `created_by`؛ مقابلَ
 *      الموروثةِ (#13..#22) التي **تحلُّ مراسيها 8/8** وتحمل ثلاثيةَ المالِ
 *      كاملةً. فالعقدُ الذي تجسِّده الموروثةُ هو المقياس، لا العدد.
 *
 * **القرارُ**: لا حذفَ صفٍّ واحدٍ (حكمُ المالك). الادّعاءُ الكاذبُ يصير `NULL`
 * **معلَنًا** بـ`void_reason` — فيبقى الأثرُ (النوعُ والهدفُ والزمنُ) شاهدًا
 * ويسقط ما لا يحلُّ. ثم يُقفَل البابُ بمفتاحٍ أجنبيٍّ حقيقيّ.
 *
 * **وحالةُ الصفِّ تتبع مالَه**: صفٌّ يقول `approved` وما له مالٌ يحلُّ يعود
 * `pending` — فلا مسارَ اعتمادٍ في الشاشةِ اليوم (`unit_records_fin.php` مجمّدةٌ
 * للقراءة)، **ولا يُلفَّق حدثٌ ماليٌّ لسدِّ فراغ**.
 *
 * مُتحمِّلٌ للتكرار · وينتهي بشاهدٍ مُشغَّلٍ ومسبارٍ سالبٍ يثبت أن المفتاحَ يمنع.
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

/* ══ ① القياسُ قبلَ المسّ ═════════════════════════════════════════════════ */
echo "── ① القياسُ قبلَ المسّ\n";
$DANGLING = 'SELECT COUNT(*) FROM fin_event_links l
              LEFT JOIN fin_financial_events e ON e.id = l.event_id
             WHERE l.event_id IS NOT NULL AND e.id IS NULL';
$before = (int) $one($DANGLING);
echo "  روابطُ مالٍ معلَّقةٌ (تشير إلى حدثٍ معدوم): {$before}\n";
$r = $db->query('SELECT l.parent_kind k, COUNT(*) c, MIN(l.event_id) mn, MAX(l.event_id) mx
                   FROM fin_event_links l
                   LEFT JOIN fin_financial_events e ON e.id = l.event_id
                  WHERE l.event_id IS NOT NULL AND e.id IS NULL
                  GROUP BY l.parent_kind ORDER BY c DESC');
while ($r && ($x = $r->fetch_assoc())) {
    echo "     {$x['k']} = {$x['c']}  (حدثٌ #{$x['mn']}..#{$x['mx']})\n";
}

/* ══ ② عمودُ الإعلانِ — الإبطالُ يُعلن سببَه ولا يُخفيه ═══════════════════ */
$has = (int) $one("SELECT COUNT(*) FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'fin_event_links'
                      AND column_name = 'void_reason'");
if ($has === 0) {
    $ok = $db->query("ALTER TABLE fin_event_links
                      ADD COLUMN void_reason VARCHAR(60) NULL
                      COMMENT 'سببُ إبطالِ المرساة — الرابطُ باقٍ شاهدًا وevent_id ساقط'");
    if (!$ok) { $fail[] = 'void_reason: ' . $db->error; }
    echo '── ② العمودُ void_reason: ' . ($ok ? "أُضيف\n" : "تعذّر — {$db->error}\n");
} else {
    echo "── ② العمودُ void_reason قائمٌ سلفًا\n";
}

/* ══ ③ إبطالُ ما لا يحلُّ — بلا حذفِ صفٍّ واحد ════════════════════════════ */
$ok = $db->query("UPDATE fin_event_links l
                    LEFT JOIN fin_financial_events e ON e.id = l.event_id
                     SET l.void_reason = 'legacy_no_event', l.event_id = NULL
                   WHERE l.event_id IS NOT NULL AND e.id IS NULL");
if (!$ok) { $fail[] = 'الإبطال: ' . $db->error; }
echo '── ③ أُبطلت ' . ($ok ? $db->affected_rows : 0) . " مرساةً — والصفوفُ كلُّها باقيةٌ شاهدة\n";

/* ══ ③-ب كنسُ مسبارٍ سابقٍ ابتلعه الـENUM صامتًا ═══════════════════════════
     `parent_kind` من نوعِ `ENUM` و`sql_mode` **فارغٌ** على هذا المحرِّك، فقيمةٌ
     خارجَ القائمةِ تُخزَّن `''` بلا خطأ — فمسبارُ جولةٍ سابقةٍ نجا بنوعِ أبٍ
     فارغٍ ونجا من كنسِه (كان يُكنَس بالاسمِ الذي **لم يُخزَّن**). فيُكنَس هنا
     بما هو مقيسٌ فعلًا: نوعُ أبٍ فارغٌ لا معنى له في هذا الدفتر. */
$ok = $db->query("DELETE FROM fin_event_links WHERE parent_kind = ''");
echo '── ③-ب كُنس ' . ($ok ? $db->affected_rows : 0) . " صفَّ مسبارٍ بنوعِ أبٍ فارغٍ (ابتلعه ENUM صامتًا)\n";

/* ══ ④ توحيدُ النوعِ قبلَ المفتاح ═════════════════════════════════════════
     `event_id` كان `int(10) unsigned` والهدفُ `fin_financial_events.id` هو
     `int(11)` — والمفتاحُ الأجنبيُّ يلزمه تطابقُ النوعِ حرفيًّا، فردَّ المحرِّكُ
     `errno 150`. والقيمُ كلُّها موجبةٌ فالتحويلُ بلا فقد. */
$ct = (string) $one("SELECT column_type FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'fin_event_links'
                        AND column_name = 'event_id'");
$tgt = (string) $one("SELECT column_type FROM information_schema.columns
                       WHERE table_schema = DATABASE() AND table_name = 'fin_financial_events'
                         AND column_name = 'id'");
if ($ct !== $tgt) {
    $neg = (int) $one('SELECT COUNT(*) FROM fin_event_links WHERE event_id < 0');
    if ($neg > 0) {
        $fail[] = "توحيدُ النوع: {$neg} قيمةً سالبة";
    } else {
        $ok = $db->query("ALTER TABLE fin_event_links MODIFY event_id {$tgt} NULL");
        if (!$ok) { $fail[] = 'توحيدُ النوع: ' . $db->error; }
        echo "── ④-أ نوعُ event_id: {$ct} ⇒ {$tgt} " . ($ok ? "✔\n" : "✘ {$db->error}\n");
    }
} else {
    echo "── ④-أ نوعُ event_id مطابقٌ للهدفِ سلفًا ({$ct})\n";
}

/* ══ ④ المفتاحُ الأجنبيُّ الذي لم يكن ════════════════════════════════════ */
$has = (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                    WHERE table_schema = DATABASE() AND table_name = 'fin_event_links'
                      AND constraint_name = 'fk_fel_event'");
if ($has === 0) {
    $ok = $db->query('ALTER TABLE fin_event_links
                      ADD CONSTRAINT fk_fel_event FOREIGN KEY (event_id)
                      REFERENCES fin_financial_events (id) ON DELETE RESTRICT ON UPDATE CASCADE');
    if (!$ok) { $fail[] = 'fk_fel_event: ' . $db->error; }
    echo '── ④ المفتاحُ fk_fel_event: ' . ($ok ? "نُصِّب\n" : "تعذّر — {$db->error}\n");
} else {
    echo "── ④ المفتاحُ fk_fel_event قائمٌ سلفًا\n";
}

/* ══ ⑤ مراسي fin_unit_records الملفَّقةُ تسقط ════════════════════════════ */
echo "── ⑤ مراسي fin_unit_records\n";
foreach (array('revenue_event_id' => 'fin_financial_events',
               'supplier_due_id'  => 'fin_dues') as $col => $tbl) {
    $ok = $db->query("UPDATE fin_unit_records u
                        LEFT JOIN {$tbl} t ON t.id = u.{$col}
                         SET u.{$col} = NULL
                       WHERE u.{$col} IS NOT NULL AND t.id IS NULL");
    if (!$ok) { $fail[] = "{$col}: " . $db->error; }
    echo "     {$col}: أُسقطت " . ($ok ? $db->affected_rows : 0) . " مرساةً لا تحلُّ\n";
}

/* ══ ⑥ الإسنادُ — صفٌّ بلا مُنشِئٍ يُسنَد إلى مُنشِئِ دفعتِه لا إلى مُخترَع ══ */
$owner = (int) $one('SELECT created_by FROM fin_unit_records
                      WHERE created_by IS NOT NULL AND id BETWEEN 663 AND 672
                      GROUP BY created_by ORDER BY COUNT(*) DESC LIMIT 1');
if ($owner > 0) {
    $ok = $db->query("UPDATE fin_unit_records SET created_by = {$owner}
                       WHERE created_by IS NULL AND id BETWEEN 663 AND 672");
    if (!$ok) { $fail[] = 'الإسناد: ' . $db->error; }
    echo '── ⑥ أُسند ' . ($ok ? $db->affected_rows : 0) . " صفًّا إلى مُنشِئِ دفعتِه (#{$owner})\n";
} else {
    echo "── ⑥ لا مُنشِئَ للدفعةِ يُقاس — الإسنادُ متروكٌ معلَنًا (لا يُلفَّق مستخدم)\n";
}

/* ══ ⑦ الحالةُ تتبع المال ═══════════════════════════════════════════════ */
$ok = $db->query("UPDATE fin_unit_records u
                     SET u.match_state = 'pending'
                   WHERE u.match_state = 'approved'
                     AND COALESCE(u.is_deleted, 0) = 0
                     AND u.revenue_event_id IS NULL
                     AND NOT EXISTS (SELECT 1 FROM fin_event_links l
                                      WHERE l.parent_kind = 'unit_record' AND l.parent_ref = u.id
                                        AND l.event_id IS NOT NULL)");
if (!$ok) { $fail[] = 'الحالة: ' . $db->error; }
echo '── ⑦ أُعيد ' . ($ok ? $db->affected_rows : 0) . " صفًّا من «معتمَد» إلى «معلَّق» — لا مالَ يحلُّ له\n";

/* ══ ⑧ القيدُ — ولا يُكتَب قيدٌ فوقَ مخالفٍ حيّ ═════════════════════════ */
$viol = (int) $one("SELECT COUNT(*) FROM fin_unit_records
                     WHERE COALESCE(is_deleted,0) = 0 AND match_state = 'approved'
                       AND (revenue_event_id IS NULL OR created_by IS NULL)");
$has = (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                    WHERE table_schema = DATABASE() AND table_name = 'fin_unit_records'
                      AND constraint_name = 'chk_unit_approved_has_money'");
if ($has > 0) {
    echo "── ⑧ القيدُ chk_unit_approved_has_money قائمٌ سلفًا\n";
} elseif ($viol === 0) {
    $ok = $db->query("ALTER TABLE fin_unit_records
                      ADD CONSTRAINT chk_unit_approved_has_money CHECK (
                          match_state <> 'approved' OR is_deleted <> 0
                          OR (revenue_event_id IS NOT NULL AND created_by IS NOT NULL))");
    if (!$ok) { $fail[] = 'chk_unit_approved_has_money: ' . $db->error; }
    echo '── ⑧ القيدُ chk_unit_approved_has_money: ' . ($ok ? "نُصِّب\n" : "تعذّر — {$db->error}\n");
} else {
    echo "── ⑧ القيدُ مؤجَّلٌ **معلَنًا** — {$viol} مخالفًا حيًّا (لا قيدَ فوقَ مخالف)\n";
}

/* ══ ⑨ الشاهدُ المُشغَّلُ والمسبارُ السالب ═══════════════════════════════ */
echo "── ⑨ الشاهدُ المُشغَّل\n";
$after = (int) $one($DANGLING);
echo "     روابطُ معلَّقةٌ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقيت {$after} رابطًا معلَّقًا"; }

/* المسبارُ يُكنَس بمفتاحِه المُرجَعِ لا بقيمةٍ ظنَّ أنه كتبها: `parent_kind`
   من نوعِ ENUM، وقيمةٌ خارجَ قائمتِه تُخزَّن `''` صامتًا مع `sql_mode` فارغ —
   فكنسٌ بالاسمِ يُخطئ صفَّه ويتركه. ويُستعمل نوعٌ **صحيحٌ** من القائمةِ ابتداءً. */
$ghost = (int) $one('SELECT COALESCE(MAX(id),0) FROM fin_financial_events') + 999999;
$probe = $db->query("INSERT INTO fin_event_links
                     (company_id, parent_kind, parent_ref, effect_type, target_table, target_id, event_id)
                     VALUES (4, 'event', 0, 'revenue_event', 'fin_financial_events', 0, {$ghost})");
echo "     مسبارٌ سالب: حدثٌ ملفَّقٌ #{$ghost} " . ($probe === false ? "مرفوضٌ ✔\n" : "مقبولٌ ✘\n");
if ($probe !== false) {
    $pid = (int) $db->insert_id;
    if ($pid > 0) { $db->query("DELETE FROM fin_event_links WHERE id = {$pid}"); }
    $left = (int) $one("SELECT COUNT(*) FROM fin_event_links WHERE event_id = {$ghost}");
    echo "     وكنسُ المسبارِ بمفتاحِه (#{$pid}): باقٍ {$left}\n";
    $fail[] = 'المفتاحُ الأجنبيُّ لا يمنع — لا إعلانَ نجاحٍ كاذب';
}

$noMoney = (int) $one("SELECT COUNT(*) FROM fin_unit_records u
                        WHERE COALESCE(u.is_deleted,0) = 0 AND u.match_state = 'approved'
                          AND NOT EXISTS (SELECT 1 FROM fin_event_links l
                                           WHERE l.parent_kind = 'unit_record' AND l.parent_ref = u.id
                                             AND l.event_id IS NOT NULL)");
echo "     صفوفٌ معتمَدةٌ بلا مالٍ يحلُّ: {$noMoney} " . ($noMoney === 0 ? "✔\n" : "✘\n");
if ($noMoney !== 0) { $fail[] = "{$noMoney} صفًّا معتمَدًا بلا مال"; }

$voided = (int) $one("SELECT COUNT(*) FROM fin_event_links WHERE void_reason = 'legacy_no_event'");
echo "     مُبطَلٌ معلَنٌ باقٍ شاهدًا: {$voided}\n";
$fks = (int) $one("SELECT COUNT(*) FROM information_schema.table_constraints
                    WHERE table_schema = DATABASE() AND table_name = 'fin_event_links'
                      AND constraint_type = 'FOREIGN KEY'");
echo "     مفاتيحُ fin_event_links الأجنبية: {$fks}\n";

echo "\n" . (empty($fail)
    ? "✅ دفترُ روابطِ المالِ صار مرجعيًّا: {$before} ادّعاءً معدومًا أُبطل معلَنًا، ومفتاحٌ أجنبيٌّ يمنع تكرارَه.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
