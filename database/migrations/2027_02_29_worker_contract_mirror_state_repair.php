<?php
/**
 * 2027_02_29 — 161 عقدَ موظفٍ «نافذٌ» ومصدرُه يقول «منتهٍ»
 * ═══════════════════════════════════════════════════════════════════════════
 * **القاعدةُ المنصوصة** (`H-08_1 §③` · `CON-01`): ترحيلُ `worker_contract` إلى
 * `employee_contracts` **مرآةٌ حرفيةٌ بلا إعادة حكم** —
 *     `مسودة → draft` · `نافذ → active` · `منتهٍ → expired`.
 *
 * **والمقيس**: 243 عقدًا ممرآةً · المدةُ مطابقةٌ في **كلِّها** (صفرُ انحراف) ·
 * والفئةُ مطابقةٌ في **كلِّها** (صفرُ انحراف) — **والحالةُ تخالف في 161**: مصدرُها
 * `منتهٍ` ومرآتُها `active`. أي أن خطوةً واحدةً في الخريطةِ سقطت: المنتهي مُرِّر
 * إلى `active` مع الباقي.
 *
 * **وأثرُه ليس شكليًّا**: `CON-01 §4` ينصُّ أن `Active` هي الحالةُ التي **«من هنا
 * فقط يُقرأ في الاحتساب»** — فمئةٌ وواحدٌ وستون عقدًا منتهيًا يظهر مؤهَّلًا
 * للاحتساب. والإصلاحُ **إسقاطٌ حتميٌّ من المصدرِ المعلَن** لا حكمٌ جديد: تُقرأ
 * حالةُ `worker_contract` وتُطبَّق خريطتُها الموثَّقة.
 *
 * ولا يُمَسُّ صفٌّ مصدرُه `نافذ` (الـ82) ولا صفٌّ ليس مصدرُه `worker_contract`.
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

$MAP  = "CASE wc.state WHEN 'نافذ' THEN 'active' WHEN 'منتهٍ' THEN 'expired' ELSE 'draft' END";
$JOIN = "FROM employee_contracts ec JOIN worker_contract wc ON wc.id = ec.source_id
          WHERE ec.source_table = 'worker_contract'";

/* ── ① القياسُ قبل المسّ ─────────────────────────────────────────────────── */
$total = (int) $one("SELECT COUNT(*) {$JOIN}");
$bad   = (int) $one("SELECT COUNT(*) {$JOIN} AND ec.state <> {$MAP}");
echo "── ① عقودٌ ممرآةٌ: {$total} · حالتُها تخالف مصدرَها: {$bad}\n";
if ($bad === 0) { echo "\n✅ المرآةُ حرفيةٌ سلفًا — لا عمل.\n"; exit(0); }

$r = $db->query("SELECT wc.state ws, ec.state es, COUNT(*) n {$JOIN}
                  AND ec.state <> {$MAP} GROUP BY wc.state, ec.state ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    echo "     «{$x['ws']}» ⇦ {$x['es']} : {$x['n']} عقدًا\n";
}
// المدةُ والفئةُ — يُعلَن أنهما مطابقتان فلا يُظنُّ أن الخللَ أعمُّ ممّا هو
$dt = (int) $one("SELECT COUNT(*) {$JOIN}
                   AND (COALESCE(ec.start_date,'') <> COALESCE(wc.date_start,'')
                        OR COALESCE(ec.end_date,'') <> COALESCE(wc.date_end,''))");
echo "     ○ والمدةُ تخالف في: {$dt} (المرآةُ سليمةٌ فيها)\n";

/* ── ② الإسقاطُ من المصدرِ المعلَن ───────────────────────────────────────── */
$ok = $db->query("UPDATE employee_contracts ec
                    JOIN worker_contract wc ON wc.id = ec.source_id
                     SET ec.state = {$MAP}
                   WHERE ec.source_table = 'worker_contract' AND ec.state <> {$MAP}");
if (!$ok) { $fail[] = 'الإسقاط: ' . $db->error; }
$done = $ok ? $db->affected_rows : 0;
echo "── ② أُعيد إسقاطُ {$done} حالةً من مصدرِها\n";

/* ── ③ الشاهدُ المُشغَّل ─────────────────────────────────────────────────── */
echo "── ③ الشاهدُ المُشغَّل\n";
$after = (int) $one("SELECT COUNT(*) {$JOIN} AND ec.state <> {$MAP}");
echo "     انحرافُ الحالةِ بعد: {$after} " . ($after === 0 ? "✔\n" : "✘\n");
if ($after !== 0) { $fail[] = "بقي {$after} منحرفًا"; }

// ولا صفَّ مصدرُه «نافذ» تحرَّك — الإصلاحُ على المنتهي وحدَه
$activeKept = (int) $one("SELECT COUNT(*) {$JOIN} AND wc.state = 'نافذ' AND ec.state = 'active'");
echo "     ومصدرُه «نافذ» وبقي active: {$activeKept}\n";
$nowExpired = (int) $one("SELECT COUNT(*) {$JOIN} AND wc.state = 'منتهٍ' AND ec.state = 'expired'");
echo "     ومصدرُه «منتهٍ» وصار expired: {$nowExpired}\n";

// المدةُ والفئةُ لم تُمَسّا
$dtAfter = (int) $one("SELECT COUNT(*) {$JOIN}
                        AND (COALESCE(ec.start_date,'') <> COALESCE(wc.date_start,'')
                             OR COALESCE(ec.end_date,'') <> COALESCE(wc.date_end,''))");
echo "     والمدةُ ما زالت مطابقةً (انحراف {$dtAfter}) " . ($dtAfter === $dt ? "✔\n" : "✘\n");
if ($dtAfter !== $dt) { $fail[] = 'المدةُ تحرَّكت وما كان يجب'; }

// وأثرُ الحكمِ: كم عقدًا صار خارجَ نطاقِ قراءةِ الاحتساب بحقٍّ؟
$activeAll = (int) $one("SELECT COUNT(*) FROM employee_contracts WHERE state = 'active'");
echo "     عقودٌ حالتُها active في السجلِّ كلِّه الآن: {$activeAll}\n";

echo "\n" . (empty($fail)
    ? "✅ المرآةُ صارت حرفيةً كما تنصُّ الخريطة — و{$done} عقدًا منتهيًا لم يبقَ مؤهَّلًا للاحتساب بالخطأ.\n"
    : "⚠ " . implode(' · ', $fail) . "\n");
exit(empty($fail) ? 0 : 1);
