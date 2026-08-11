<?php
/**
 * 2027_02_16 — `client_contracts`: منظرٌ صار جدولًا فارغًا فيُعاد منظرًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ عينُ ما يحرسه فاحصُه**: `contract_sites_test` يشترط «لا جدولَ ثانيًا
 *   لغرضٍ واحد» — و`client_contracts` **جدولُ أساسٍ فارغٌ** اليومَ لا منظرًا:
 *     `information_schema.TABLES` ⇒ `BASE TABLE · InnoDB · 0 rows`
 *     `information_schema.VIEWS`  ⇒ لا صفَّ له (والمناظرُ الحيّةُ أربعةٌ فقط)
 *     `SELECT COUNT(*) client_contracts` ⇒ **0** مقابل `contracts` ⇒ 120
 *
 * ◆ **والجذرُ الميكانيكيُّ مقيسٌ لا مُخمَّن**: `database/schema/schema.sql:996`
 *   يكتبه `CREATE TABLE` بأعمدةٍ **كلُّها NULL** — وهو بديلُ `mysqldump` القياسيُّ
 *   لمنظرٍ لا يملك حسابُ التصديرِ صلاحيةَ `SHOW VIEW` عليه. ثم استيرادُ الدمبِ
 *   أحلَّ الجدولَ الفارغَ محلَّ المنظر. فالخللُ في **قناةِ التصدير** لا في
 *   الهجرات — ولذلك لم يكشفه فحصُ انحرافِ مخطَّط: المرجعُ والقاعدةُ متفقان على
 *   الخطأ نفسِه.
 *
 * ◆ **والتعريفُ منقولٌ من نسخةٍ حيّةٍ لا مُخترَع**
 *   (`auto_pre_up_20260731_212517`): `contracts` LEFT JOIN
 *   `contract_operational_sites` حيث `is_primary = 1` وغيرُ محذوف، مع ثلاثةِ
 *   أعمدةِ نطاقٍ (`primary_scope_id` · `primary_site_id` · `primary_scope_name`).
 *
 * ◆ **ويُكتب بـ`c.*` لا بقائمةِ الأعمدةِ التسعةِ والأربعين** — فالقائمةُ
 *   المجمَّدةُ **تعفّنت مرةً بالفعل**: النسخةُ الأصليةُ لا تحمل
 *   `signing_authority_ref` ولا ما أُضيف بعده. والمنظرُ الذي يُصان يدويًّا
 *   عمودًا عمودًا يتخلّف عن جدولِه حتمًا.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ المنظرُ بعده: نوعُه · وعددُه = عددُ العقود ·
 *   وأعمدةُ النطاقِ الثلاثةُ حاضرةٌ ومملوءةٌ لمن له نطاقٌ رئيس.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ `client_contracts` — منظرٌ لا جدول ══\n";

$t = $db->query("SELECT TABLE_TYPE FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_contracts'");
$type = $t ? (string) ($t->fetch_row()[0] ?? '') : '';
echo '  الحالُ الآن: ' . ($type === '' ? 'غيرُ موجود' : $type) . "\n";

if ($type === 'BASE TABLE') {
    /* الجدولُ البديلُ فارغٌ بطبيعته (بديلُ mysqldump) — يُقاس قبلَ رفعِه */
    $n = (int) $db->query('SELECT COUNT(*) FROM client_contracts')->fetch_row()[0];
    echo "  صفوفُه: {$n}\n";
    if ($n > 0) {
        fwrite(STDERR, "✘ الجدولُ يحمل {$n} صفًّا — لا يُرفع جدولٌ فيه بيانةٌ. يُعلَن ويُوقف.\n");
        exit(1);
    }
    if ($db->query('DROP TABLE `client_contracts`') === false) {
        fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
    }
    echo "  ✔ رُفع الجدولُ البديلُ (فارغٌ — لا بيانةَ فُقدت)\n";
}

$sql = "CREATE OR REPLACE VIEW `client_contracts` AS
        SELECT c.*,
               cos.id         AS primary_scope_id,
               cos.site_id    AS primary_site_id,
               cos.scope_name AS primary_scope_name
          FROM contracts c
          LEFT JOIN contract_operational_sites cos
                 ON cos.contract_id = c.id
                AND cos.is_primary = 1
                AND COALESCE(cos.is_deleted, 0) = 0";
if ($db->query($sql) === false) { fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1); }
echo "  ✔ أُنشئ المنظرُ بـ`c.*` — لا قائمةَ أعمدةٍ تتخلّف عن جدولِها\n";

/* ══ الجسّ ═══════════════════════════════════════════════════════════════ */
echo "\n── الجسّ\n";
$t = $db->query("SELECT TABLE_TYPE FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_contracts'");
$type2 = $t ? (string) ($t->fetch_row()[0] ?? '') : '';
echo '  ' . ($type2 === 'VIEW' ? '✔' : '✘') . " نوعُه: {$type2}\n";

$cc = (int) $db->query('SELECT COUNT(*) FROM client_contracts')->fetch_row()[0];
$ct = (int) $db->query('SELECT COUNT(*) FROM contracts')->fetch_row()[0];
echo '  ' . ($cc === $ct ? '✔' : '✘') . " العددُ نفسُه: {$cc} = {$ct}\n";

$cols = array();
$r = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_contracts'");
while ($r && ($x = $r->fetch_row())) { $cols[] = $x[0]; }
$need = array('primary_scope_id', 'primary_site_id', 'primary_scope_name');
$miss = array_values(array_diff($need, $cols));
echo '  ' . (!$miss ? '✔' : '✘') . ' أعمدةُ النطاقِ الثلاثةُ حاضرة'
   . ($miss ? ' — الناقص: ' . implode(' · ', $miss) : '') . "\n";
echo '  ○ وأعمدةُ العقدِ كلُّها: ' . count($cols) . " عمودًا (بـ`c.*` فلا تتخلّف)\n";

$filled = (int) $db->query('SELECT COUNT(*) FROM client_contracts WHERE primary_scope_id IS NOT NULL')
                   ->fetch_row()[0];
echo "  ○ عقودٌ لها نطاقٌ رئيسٌ مملوء: {$filled}\n";

/* ══ وحتى لا يعود: قناةُ التصديرِ هي الجذر ═══════════════════════════════ */
echo "\n── الجذرُ الباقي (يُعلَن)\n";
echo "  ◆ `database/schema/schema.sql` يكتب هذا المنظرَ `CREATE TABLE` — بديلُ\n";
echo "    mysqldump لمنظرٍ بلا صلاحيةِ `SHOW VIEW`. فما لم تُصدَّر المناظرُ\n";
echo "    مناظرَ، أعاد كلُّ استيرادٍ العيبَ نفسَه. يلزمه منحُ `SHOW VIEW` لحسابِ\n";
echo "    التصديرِ أو التصديرُ بحسابٍ يملكها — وهو إصلاحُ **قناةٍ** لا هجرة.\n";

$ok = ($type2 === 'VIEW' && $cc === $ct && !$miss);
echo "\n" . ($ok ? "✅ منظرٌ واحدٌ لغرضٍ واحد — ولا جدولَ ثانيًا يشوّش.\n" : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
