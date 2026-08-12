<?php
/**
 * 2027_03_08 — الواجهةُ الموحّدةُ للأعطال (VIEW) + تصنيفاتٌ أسماؤها أسماءُ أشخاص
 * ═══════════════════════════════════════════════════════════════════════════
 * **عطبان في موضعٍ واحدٍ — كلاهما موثَّقٌ قرارُه سلفًا:**
 *
 * ① **الواجهةُ فُقدت في سحقِ المخطَّط.** `docs/reports/m31_taxonomy_reconciliation.md:10`
 *    ينصُّ: «الواجهة: `unified_fault_taxonomy` (**VIEW** · 83 فئة) — يقرأ منها
 *    تقريرُ التكرار (M-35 · الشاشة 197)». والمقيسُ في القاعدةِ الحيّة:
 *    **`BASE TABLE` فارغٌ** بأعمدةِ الإسقاطِ نفسِها
 *    (`code` · `name` · `equipment_type` · `source`) — وهي بصمةُ
 *    **بديلِ `mysqldump` عن رؤيةٍ بلا صلاحيةِ `SHOW VIEW`**. وهي سابقةٌ مسجَّلةٌ
 *    عُولجت اليومَ نفسَه في `2027_02_16` (`client_contracts`).
 *    والمصدرُ حيٌّ وكافٍ: `failure_codes` فيه **402 صفًّا** و**83** ثنائيةً
 *    متمايزةً (نوعُ معدةٍ × فئةٌ رئيسية) — أي العددُ المنصوصُ حرفيًّا.
 *
 * ② **واثنا عشرَ صفًّا في `ticket_categories` أسماؤها أسماءُ أشخاص**
 *    (`TICK-00009` … `TICK-00020`) و`failure_main_code` فيها **يشير إلى رمزِ
 *    الصفِّ نفسِه** — لا إلى فئةِ عطلٍ حقيقية. وهو **عينُ عطبِ مستوردِ UAT-2026**
 *    الذي عُولج اليومَ في `job_titles` و`pay_models` و`ticket_types`.
 *    والثمانيةُ الموثَّقةُ (ids 1..8) **سليمةٌ وموصولةٌ صحيحًا**:
 *    MEC · HYD · ELE · BDY · COL · TRK · PMP · و«غير ذلك» بـNULL **عمدًا**
 *    («لا يُخترع له كود» — نصُّ التقرير).
 *    فالدخيلُ يُفسد حكمًا صحيحًا: `mnt_tickets_group_test:46` يشترط **7 موصولةً
 *    وواحدًا معلَنًا** بلا وصلة.
 *
 * **القرار**: لا حذفَ صفٍّ — الدخيلُ يُعطَّل ويُوسَم ويُصفَّر مرجعُه الذاتيُّ
 * الكاذب، فيخرج من كلِّ قراءةٍ حاكمةٍ ويبقى شاهدًا.
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

/* ══ ① الواجهة ═══════════════════════════════════════════════════════════ */
$type = (string) $one("SELECT table_type FROM information_schema.tables
                        WHERE table_schema = DATABASE() AND table_name = 'unified_fault_taxonomy'");
$rows = ($type === '') ? -1 : (int) $one('SELECT COUNT(*) FROM unified_fault_taxonomy');
$src  = (int) $one('SELECT COUNT(DISTINCT equipment_type, main_category_code) FROM failure_codes');
echo "── ① unified_fault_taxonomy: نوعٌ={$type} · صفوفٌ={$rows} · والمصدرُ يعطي {$src} فئة\n";

if ($src < 80) {
    $fail[] = "المصدرُ يعطي {$src} فئةً فقط — لا تُبنى واجهةٌ على مصدرٍ ناقص";
} elseif ($type === 'BASE TABLE' || $type === '') {
    if ($type === 'BASE TABLE' && $rows > 0) {
        $fail[] = "الجدولُ فيه {$rows} صفًّا — لا يُسقَط جدولٌ بمحتوى";
    } else {
        if ($type === 'BASE TABLE' && !$db->query('DROP TABLE unified_fault_taxonomy')) {
            $fail[] = 'إسقاطُ الجدولِ الفارغ: ' . $db->error;
        }
        $view = "CREATE VIEW unified_fault_taxonomy AS
                 SELECT DISTINCT fc.main_category_code AS code,
                        fc.main_category_name AS name,
                        fc.equipment_type AS equipment_type,
                        'failure_codes' AS source
                   FROM failure_codes fc
                  WHERE fc.main_category_code IS NOT NULL AND fc.main_category_code <> ''";
        if (!$db->query($view)) { $fail[] = 'إنشاءُ الرؤية: ' . $db->error; }
        else { echo "── ② الرؤيةُ أُنشئت من `failure_codes`\n"; }
    }
} else {
    echo "── ② الرؤيةُ قائمةٌ سلفًا ({$type})\n";
}

/* ══ ③ التصنيفاتُ الدخيلة ════════════════════════════════════════════════ */
$DOC8 = array('engine', 'hydraulic', 'electrical', 'welding', 'ac', 'tire', 'oil_change', 'other');
$in8 = "'" . implode("','", $DOC8) . "'";
$alien = (int) $one("SELECT COUNT(*) FROM ticket_categories
                      WHERE code NOT IN ({$in8}) AND COALESCE(active,1) = 1");
$selfRef = (int) $one('SELECT COUNT(*) FROM ticket_categories
                        WHERE failure_main_code IS NOT NULL AND failure_main_code = code');
echo "── ③ تصنيفاتٌ خارجَ الثمانيةِ الموثَّقةِ وفعّالة: {$alien} · ومرجعُها الذاتيُّ الكاذب: {$selfRef}\n";

$used = (int) $one("SELECT COUNT(*) FROM tickets t
                     JOIN ticket_categories c ON c.id = t.category_id
                    WHERE c.code NOT IN ({$in8})");
echo '     وبلاغاتٌ تستعمل الدخيل: ' . ($used >= 0 ? $used : 'ع/م') . "\n";

if ($alien > 0) {
    $ok = $db->query("UPDATE ticket_categories
                         SET active = 0,
                             failure_main_code = NULL,
                             applies_to = CONCAT(COALESCE(applies_to,''),
                                 CASE WHEN COALESCE(applies_to,'') = '' THEN '' ELSE ' · ' END,
                                 '2027_03_08: مُحتجَزٌ — اسمُ شخصٍ في قاموسٍ (مستوردُ UAT) ومرجعٌ ذاتيٌّ كاذب')
                       WHERE code NOT IN ({$in8}) AND COALESCE(active,1) = 1");
    if (!$ok) { $fail[] = 'الحجز: ' . $db->error; }
    echo '── ④ حُجز ' . ($ok ? $db->affected_rows : 0) . " تصنيفًا دخيلًا (تعطيلٌ + تصفيرُ المرجعِ + سببٌ مكتوب)\n";
}

/* ══ ⑤ الشاهدُ المُشغَّل ═══════════════════════════════════════════════════ */
echo "── ⑤ الشاهدُ المُشغَّل\n";
$vt = (string) $one("SELECT table_type FROM information_schema.tables
                      WHERE table_schema = DATABASE() AND table_name = 'unified_fault_taxonomy'");
$vn = ($vt === '') ? 0 : (int) $one('SELECT COUNT(*) FROM unified_fault_taxonomy');
echo "     الواجهة: نوعٌ={$vt} · فئاتٌ={$vn} " . ($vt === 'VIEW' && $vn >= 80 ? "✔\n" : "✘\n");
if ($vt !== 'VIEW' || $vn < 80) { $fail[] = "الواجهةُ {$vt} بـ{$vn} فئة"; }

$mapped = (int) $one('SELECT COUNT(*) FROM ticket_categories WHERE failure_main_code IS NOT NULL');
$orphan = (int) $one('SELECT COUNT(*) FROM ticket_categories WHERE failure_main_code IS NULL');
echo "     موصولٌ={$mapped} · بلا وصلةٍ={$orphan} " . ($mapped === 7 ? "✔\n" : "✘\n");
if ($mapped !== 7) { $fail[] = "الموصولُ {$mapped} لا 7"; }

// وكلُّ وصلةٍ تشير إلى فئةٍ **موجودةٍ** في المصدر — لا إلى رمزِ نفسِها
$badLink = (int) $one("SELECT COUNT(*) FROM ticket_categories c
                        WHERE c.failure_main_code IS NOT NULL
                          AND NOT EXISTS (SELECT 1 FROM failure_codes f
                                           WHERE f.main_category_code = c.failure_main_code)");
echo "     وصلةٌ إلى فئةٍ لا وجودَ لها: {$badLink} " . ($badLink === 0 ? "✔\n" : "✘\n");
if ($badLink !== 0) { $fail[] = "{$badLink} وصلةً إلى فئةٍ معدومة"; }

$kept = (int) $one("SELECT COUNT(*) FROM ticket_categories WHERE COALESCE(active,1) = 0");
echo "     محتجَزٌ شاهدًا (لم يُحذف): {$kept}\n";

echo "\n" . (empty($fail)
    ? "✅ الواجهةُ عادت رؤيةً بـ{$vn} فئةً من مصدرِها، والقاموسُ صار الثمانيةَ الموثَّقةَ وحدَها.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
