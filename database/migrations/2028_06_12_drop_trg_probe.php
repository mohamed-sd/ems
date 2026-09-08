<?php
/**
 * 2028_06_12 — حذفُ `_trg_probe` · جدولُ مسبارٍ يتيم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما هو**: `CREATE TABLE _trg_probe (id INT, PRIMARY KEY(id))` — بقايا
 *   مسبارٍ جُسَّ به امتيازُ إنشاءِ القوادح، ثمَّ نُسي.
 *
 * ◆ **ولماذا وحدَه من أحدَ عشرَ مرشَّحًا**: مسحُ 3,571 ملفًّا صنَّف 457 جدولًا
 *   «صفرَ تعاملٍ في المنتج»، منها 11 لا تمسُّها العُدّةُ أيضًا. ثمَّ ردَّ فحصُ
 *   التبعيّاتِ عشرةً منها — **وهذا وحدَه بلا مانع**:
 *     مفاتيحُ واردة=0 · صادرة=0 · قوادحُ عليه=0 · مَشاهدُ تذكره=0 · صفوف=0
 *     ولا اسمَ له في `TenantRegistry` ولا `MANIFEST.json` ولا البذور.
 *   وذِكرُه الوحيدُ خارجَ `schema.sql` **لقطةُ قياسٍ تاريخيّة**
 *   (`docs/baseline_20260821/extract/db_tables.json`) لا شيفرةٌ تنفَّذ.
 *
 * ⛔ **وما لم يُحذَف ولماذا** (‏الفحصُ ردَّ الحذفَ بسببٍ مسمًّى لكلٍّ):
 *   · `super_admins` + تابعاه — مفتاحان واردان من `admin_audit_log`
 *     (‏**والمنتجُ يكتب فيه**: `request_subscription.php:103`) ومن
 *     `admin_subscription_requests`؛ وفيه صفٌّ حقيقيٌّ واحد. ومجلَّدُ `admin/`
 *     **غائبٌ عن المستودع** والسجلُّ يشير إليه — فهي **يتيمةُ شيفرةٍ لا ميّتة**.
 *   · `gov_test_residue_archive` — **مسارُ عكسِ هجرةٍ قائمة**
 *     (`2027_08_27_leaktest_audit_residue_sweep`: «ولا حذفَ مدمِّر — كلُّ صفٍّ
 *     يُحجَر بلقطتِه قبلَ حذفِه»). حذفُه يهدم شبكةَ أمانٍ مقصودة.
 *   · `shift_patterns` + `shift_period_defs` — جدولا مرجعٍ لعائلةِ WRK-01،
 *     وثالثُها `shift_period_logs` **حيٌّ يُكتَب فيه** من
 *     `app/Services/Workforce/AttendanceService.php:50`.
 *   · `task_dependencies` · `workspace_views` · `workspace_prefs` ·
 *     `worker_restricted_site` — مُعلَنةٌ في `TenantRegistry` وفي وثائقِ
 *     التصميم (‏3 إلى 10 وثائقَ لكلٍّ). حذفُها **إلغاءُ تصميمٍ لا تنظيف** —
 *     وذاك قرارُ مالكٍ لا فعلُ هجرة.
 *
 * ◆ **والعكسُ يعيده حرفًا** — ملفُّ `_down` يُنشئه ببنيتِه نفسِها.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$DB = ems_env('DB_NAME');
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

echo "── حذفُ `_trg_probe` ──\n";

if ((int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA='{$DB}' AND TABLE_NAME='_trg_probe'") === 0) {
    echo "  الجدولُ محذوفٌ سلفًا — لا عمل\n"; exit(0);
}

/* ⛔ **الفحصُ يُعاد لحظةَ الفعلِ لا يُنقَل من تقرير**: حالةُ القاعدةِ قد تغيّرت. */
$blk = 0;
foreach (array(
    'مفاتيحُ واردة' => "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                          WHERE CONSTRAINT_SCHEMA='{$DB}' AND REFERENCED_TABLE_NAME='_trg_probe'",
    'مفاتيحُ صادرة' => "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                          WHERE CONSTRAINT_SCHEMA='{$DB}' AND TABLE_NAME='_trg_probe'
                            AND REFERENCED_TABLE_NAME IS NOT NULL",
    'قوادحُ عليه'   => "SELECT COUNT(*) FROM information_schema.TRIGGERS
                          WHERE TRIGGER_SCHEMA='{$DB}' AND EVENT_OBJECT_TABLE='_trg_probe'",
    'مَشاهدُ تذكره' => "SELECT COUNT(*) FROM information_schema.VIEWS
                          WHERE TABLE_SCHEMA='{$DB}' AND VIEW_DEFINITION LIKE '%_trg_probe%'",
    'صفوفٌ فيه'     => "SELECT COUNT(*) FROM `_trg_probe`",
) as $lab => $q) {
    $v = (int) $one($q);
    echo "  {$lab} = {$v}\n";
    $blk += $v;
}
if ($blk !== 0) { fwrite(STDERR, "ثمّة مانعٌ ({$blk}) — أوقفتُ الحذف\n"); exit(1); }

if (!$conn->query("DROP TABLE `_trg_probe`")) {
    fwrite(STDERR, "الحذفُ فشل: {$conn->error}\n"); exit(1);
}
$gone = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA='{$DB}' AND TABLE_NAME='_trg_probe'") === 0;
$tot  = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA='{$DB}' AND TABLE_TYPE='BASE TABLE'");
echo "  ✔ حُذف — والجداولُ الآن {$tot} (كانت " . ($tot + 1) . ")\n";
if (!$gone) { fwrite(STDERR, "ما زال موجودًا\n"); exit(1); }
exit(0);
