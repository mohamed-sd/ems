<?php
/**
 * 2027_03_20 — قواميسُ الأمنِ الملفَّقةُ تُزال، والناقصُ يُعاد من مصدرِه الحاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * كُشف بقياسِ بابِ ٤-٢ من وثيقةِ OWF-01 ثم تُحقِّق بالقياسِ المباشر، وهو **يفسّر
 * سقوطَ `tests/sec013_test.php` حرفيًّا**:
 *   · «قاموس الأفعال 16 (20)» — و`sec_actions` فيه **16 سليمًا + 4 ملفَّقة**.
 *   · «قاموس النطاقات 9 (20)» — و`sec_scopes` فيه **9 سليمًا + 11 ملفَّقة**.
 * فالفاحصُ كان صادقًا، والعطبُ في البياناتِ لا فيه.
 *
 * ── والتلفيقُ ليس تجميليًّا في **قاموسِ أمن** ────────────────────────────────
 * · `sec_sod_pairs`: سبعةُ صفوفٍ ملفَّقةٍ كلُّها `active=1`، **ثلاثةٌ منها
 *   `severity='block'`** و`roles_a/roles_b` جملةٌ عربيةٌ («مُدرجٌ ضمن الدورة
 *   الشهرية · UAT-2026»). وهي تدخل فعلًا حلقةَ `AssignmentGate::checkConflicts`
 *   (تقرأ `active=1 AND severity='block'`) — **ولا تُنجّي إلا أنَّ `csvInts`
 *   تُرجع فراغًا فيقع `continue`**. أي أنَّ سلامةَ فصلِ المهامِّ **عرَضيةٌ لا
 *   مقصودة**: أيُّ تغييرٍ في مُحوِّلِ النصِّ إلى أعدادٍ يُحوّلها قواعدَ حجبٍ حقيقية.
 * · و11 نطاقًا وهميًّا في `sec_scopes` بـ`narrowness` **10..20 فوق `site`=6** —
 *   فأيُّ حكمٍ «الأضيقُ يشمله الأوسع» يُقاس على سلّمٍ مُفسَد.
 * · وصفٌّ نشطٌ في `fin_acc_specializations` رمزُه `FIN_-000` و`name_ar` **اسمُ
 *   شخصٍ** — يشغل «التخصصَ الحاديَ عشرَ» الذي تطلبه الوثيقةُ لمحاسبِ الموقع.
 * · و20 صفًّا في `sec_sod_denials` — فمن يقيس «أثرَ الحجب» يقيس على بذرٍ.
 *
 * ── والناقصُ **يُعاد من مصدرِه لا يُكتب بيدٍ** ────────────────────────────────
 * المصدرُ الحاكمُ للقاموسَين هو `2026_11_18_sec013_dimensions.sql` وفيه
 * `INSERT IGNORE` لعشرينَ فعلًا وعشرينَ نطاقًا. فتُقرأ عبارتاه وتُنفَّذان — فلا
 * يُكتب هنا سجلٌّ ثانٍ للقاموس (وسجلّان يتفرَّقان دائمًا).
 *
 * ◆ والبصمةُ `CODE_-000NN` لا قائمةَ معرِّفات: فتصمد لو أُعيد المولِّدُ بأرقامٍ أخرى.
 * ◆ وجسُّ تمييزٍ إلزاميٌّ: لو لمست البصمةُ رمزًا سليمًا تُوقَف الهجرةُ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    if ($r === false) { fwrite(STDERR, 'استعلامٌ فشل: ' . $db->error . "\n"); return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
/** بصمةُ المولِّد: رمزٌ ينتهي بـ`_-` ثم أرقام */
$FAB = "REGEXP '_-0*[0-9]+$'";

/* ── ① الجداولُ وأعمدةُ رمزِها — أُثبِتت بـSHOW COLUMNS لا بالحدس ─────────────
     (`sec_actions` و`sec_scopes` **بلا `id` وبلا `company_id`** — فمفتاحُهما
      رمزُهما، وأوّلُ استعلامٍ لي طلب `company_id` فعاد `false` صامتًا فقرأتُه
      «لا صفوف». الأسماءُ تُثبَت.) */
$TARGETS = array(
    'sec_actions'             => 'action_code',
    'sec_scopes'              => 'scope_code',
    'sec_sod_pairs'           => 'code',
    'sec_sod_denials'         => 'pair_code',
    'fin_acc_specializations' => 'code',
);

echo "── ① الحالةُ قبل الإزالة:\n";
$before = array();
foreach ($TARGETS as $t => $col) {
    $all = $one("SELECT COUNT(*) FROM `{$t}`");
    $fab = $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` {$FAB}");
    if ($all === null || $fab === null) { exit(1); }
    $before[$t] = array('all' => (int) $all, 'fab' => (int) $fab);
    printf("   %-26s كلٌّ %4d · ملفَّقٌ %3d\n", $t, $all, $fab);
}

/* ── ② جسُّ تمييزٍ: البصمةُ لا تلمس رمزًا سليمًا معروفًا ─────────────────────── */
$SAFE = array('sec_actions' => array('approve', 'create', 'edit', 'export', 'screen_view'),
              'sec_scopes'  => array('company', 'site', 'project', 'unit', 'own'),
              'sec_sod_pairs' => array('SOD-01', 'SOD-13'));
$bad = 0;
foreach ($SAFE as $t => $codes) {
    $in = "'" . implode("','", array_map(array($db, 'real_escape_string'), $codes)) . "'";
    $hit = $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$TARGETS[$t]}` IN ({$in}) AND `{$TARGETS[$t]}` {$FAB}");
    if ($hit === null) { exit(1); }
    if ((int) $hit !== 0) { $bad++; echo "   ✘ البصمةُ تلمس رمزًا سليمًا في {$t}\n"; }
}
echo "── ② جسُّ تمييزٍ: رموزٌ سليمةٌ داخلَ البصمة = {$bad}\n";
if ($bad !== 0) { fwrite(STDERR, "البصمةُ غيرُ مميِّزةٍ — تُوقَف الهجرةُ ولا يُزال شيء.\n"); exit(1); }

/* ── ③ الإزالةُ — الأبناءُ قبل الآباء (الحجبُ يرجع إلى الزوج) ────────────────── */
$order = array('sec_sod_denials', 'sec_sod_pairs', 'sec_actions', 'sec_scopes', 'fin_acc_specializations');
$allOk = true;
foreach ($order as $t) {
    $col = $TARGETS[$t];
    $want = $before[$t]['fab'];
    if ($want === 0) { printf("   %-26s لا ملفَّقَ — لا تغيير\n", $t); continue; }
    $ok = $db->query("DELETE FROM `{$t}` WHERE `{$col}` {$FAB}");
    if ($ok === false) {
        printf("   %-26s ✘ حذفٌ فشل: %s\n", $t, substr($db->error, 0, 60));
        $allOk = false;
        continue;
    }
    $gone = $db->affected_rows;
    $left = $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` {$FAB}");
    printf("   %-26s حُذف %3d من %3d · باقٍ %s %s\n", $t, $gone, $want, $left,
           ((int) $gone === $want && (int) $left === 0) ? '✔' : '✘');
    if ((int) $gone !== $want || (int) $left !== 0) { $allOk = false; }
}
if (!$allOk) { fwrite(STDERR, "لم تكتمل الإزالةُ كما تُتوقَّع\n"); exit(1); }

/* ── ④ الناقصُ يُعاد من المصدرِ الحاكمِ — لا يُكتب بيدٍ ─────────────────────── */
$srcSql = $ROOT . '/database/migrations/2026_11_18_sec013_dimensions.sql';
if (!is_file($srcSql)) {
    fwrite(STDERR, "المصدرُ الحاكمُ غائبٌ: {$srcSql}\n"); exit(1);
}
$sqlDoc = (string) file_get_contents($srcSql);
$restored = 0;
foreach (array('sec_actions', 'sec_scopes') as $t) {
    /* عبارةُ الإدراجِ كاملةً حتى الفاصلةِ المنقوطة — و`INSERT IGNORE` عاطلةٌ */
    if (!preg_match('~INSERT\s+IGNORE\s+INTO\s+`?' . $t . '`?[^;]*;~is', $sqlDoc, $m)) {
        echo "   ⚠ لم تُعثر عبارةُ إدراجٍ لـ{$t} في المصدر — يُعلَن ولا يُخترع\n";
        continue;
    }
    $ok = $db->query($m[0]);
    if ($ok === false) {
        echo "   ✘ إعادةُ {$t} فشلت: " . substr($db->error, 0, 70) . "\n";
        $allOk = false;
        continue;
    }
    $added = $db->affected_rows;
    $restored += max(0, $added);
    printf("   %-26s أُعيد من المصدرِ %3d صفًّا\n", $t, $added);
}

/* ── ⑤ الحصيلةُ: كلُّ قاموسٍ عشرونَ سليمًا وصفرُ ملفَّق ────────────────────────── */
echo "── ⑤ بعد:\n";
$fail = 0;
foreach ($TARGETS as $t => $col) {
    $all = (int) $one("SELECT COUNT(*) FROM `{$t}`");
    $fab = (int) $one("SELECT COUNT(*) FROM `{$t}` WHERE `{$col}` {$FAB}");
    printf("   %-26s كلٌّ %4d · ملفَّقٌ %3d %s\n", $t, $all, $fab, $fab === 0 ? '✔' : '✘');
    if ($fab !== 0) { $fail++; }
}
$actN = (int) $one('SELECT COUNT(*) FROM sec_actions');
$scpN = (int) $one('SELECT COUNT(*) FROM sec_scopes');
echo "── ⑥ القاموسان: أفعالٌ {$actN} · نطاقاتٌ {$scpN} (المصدرُ يُعلن 20 لكلٍّ)\n";
if ($fail !== 0 || !$allOk) { fwrite(STDERR, "بقي ملفَّقٌ أو فشلت إعادةٌ\n"); exit(1); }

echo "\n✅ قواميسُ الأمنِ نظيفةٌ، والناقصُ أُعيد من مصدرِه الحاكمِ لا بيدٍ ثانية.\n";
exit(0);
