<?php
/**
 * 2027_07_07_decision_actor_or_source.php — «لا حسمَ بلا فاعلٍ **أو** مصدرٍ حاكم»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ عيبٌ من صنعِ الهجرةِ السابقةِ رصدتُه بالقياسِ فورَ تشغيلِها ولم أُخفِه:
 *   القادحُ `trg_nav_provisional_scope` يرفض أيَّ صفٍّ حالتُه APPROVED و
 *   `decided_by IS NULL`. و**281 صفًّا** كذلك — قرارُها في مصفوفةِ المالكِ v3
 *   لا في الجدول. فالقادحُ كان سيمنع أيَّ تحديثٍ لاحقٍ لها (تسميةً أو ترتيبًا)
 *   ويعطّل السجلَّ من حيث أراد حمايتَه.
 *
 * ◆ والتصحيحُ لا بانتحالِ فاعلٍ (يخالف قاعدةَ المالكِ الرابعة: «اسمُ المعتمِدِ
 *   يُقرأ من دفترِ الخطوات ولا يُكتب نصًّا أبدًا») بل بتمييزِ حالتَين:
 *   ① قرارٌ بفاعلٍ بشريّ ⇒ `decided_by` مُعرِّفُه من الجلسة.
 *   ② قرارٌ بمصدرٍ حاكمٍ موثَّق ⇒ `decision_source` مرجعُ الوثيقةِ والصفّ.
 *   وأحدُهما إلزاميٌّ للحسم — والفراغُ من الاثنَين هو الصمتُ المرفوض.
 *
 * ◆ والقادحُ يفحص **الانتقالَ** لا الحالة: صفٌّ محسومٌ سابقًا يُحدَّث لسببٍ آخرَ
 *   يمرُّ، ومن يُنقل الآنَ إلى الحسمِ بلا فاعلٍ ولا مصدرٍ يُرفض. (وهذا الفرقُ
 *   بين حراسةِ الفعلِ وحراسةِ الحالةِ — والثانيةُ تجمّد ما تحرسه.)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$has = function ($t, $c) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                         AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}'");
    return $r && $r->num_rows > 0;
};

if (!$has('nav_canonical', 'decision_source')) {
    $conn->query("ALTER TABLE nav_canonical
        ADD `decision_source` VARCHAR(190) NULL
            COMMENT 'مرجعُ القرارِ الوثائقيُّ حين لا فاعلَ بشريّ — أحدُهما إلزاميٌّ للحسم'
            AFTER `decided_by`");
}

/* ◆ الترتيبُ مقصود: القادحُ السابقُ يحرس **الحالةَ** لا الانتقال، فيرفض هذا
     التصحيحَ نفسَه (281 صفًّا حالتُها APPROVED و decided_by NULL). فيُنزع أولًا
     ثم يُصحَّح ثم يُركَّب محكمًا — وإلا منع الحارسُ إصلاحَ نفسِه صامتًا. */
$conn->query("DROP TRIGGER IF EXISTS `trg_nav_provisional_scope`");

/* الـ281 قرارُها مصفوفةُ المالكِ v3 — يُسجَّل مرجعُها ولا يُخترع لها فاعل */
$conn->query("UPDATE nav_canonical
                 SET decision_source = CONCAT('مصفوفةُ التنقلِ المعياريةِ v3 (2026-08-18) · صفّ ', COALESCE(matrix_row,'—'))
               WHERE decision_state = 'APPROVED' AND decided_by IS NULL AND decision_source IS NULL");
$sourced = $conn->affected_rows;

/* القادحُ يفحص الانتقالَ لا الحالة */
$ok = $conn->query("CREATE TRIGGER `trg_nav_provisional_scope`
    BEFORE UPDATE ON `nav_canonical` FOR EACH ROW
    BEGIN
        IF NEW.application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION'
           AND NEW.provisional_reversible <> 1 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'التطبيقُ المؤقَّتُ محصورٌ في التسميةِ والموضعِ — ولا يُطبَّق بلا اعتراضٍ في الصلاحياتِ والسلاليمِ والسقوفِ وفصلِ الواجبات';
        END IF;
        IF NEW.decision_state IN ('APPROVED','REJECTED')
           AND COALESCE(OLD.decision_state,'') <> NEW.decision_state
           AND NEW.decided_by IS NULL
           AND (NEW.decision_source IS NULL OR NEW.decision_source = '') THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'حسمٌ بلا فاعلٍ ولا مصدرٍ حاكمٍ مرفوض — الصمتُ لا يُرقِّي قرارًا';
        END IF;
    END");
if (!$ok) { exit("✗ القادحُ: {$conn->error}\n"); }

/* ── الإثبات الحيُّ: القادحُ يمنع الصمتَ ويسمح بالتحديثِ المشروع ── */
echo "صفوفٌ سُجِّل مرجعُ قرارِها: {$sourced}\n";

/* ① تحديثٌ مشروعٌ لصفٍّ محسومٍ سابقًا (تغييرُ ترتيبٍ) — يجب أن يمرَّ */
$row = $conn->query("SELECT route, sort_no FROM nav_canonical WHERE decision_state='APPROVED' LIMIT 1")->fetch_assoc();
$okUpd = $conn->query("UPDATE nav_canonical SET sort_no = sort_no WHERE route = '" . $conn->real_escape_string($row['route']) . "'");
echo "① تحديثُ صفٍّ محسومٍ سابقًا: " . ($okUpd ? "مرَّ ✔" : "مُنع ✗ — {$conn->error}") . "\n";

/* ② نقلُ صفٍّ معلَّقٍ إلى APPROVED بلا فاعلٍ ولا مصدر — يجب أن يُرفض */
$pend = $conn->query("SELECT route FROM nav_canonical WHERE decision_state='PENDING_OWNER' LIMIT 1")->fetch_assoc();
$r2 = $conn->query("UPDATE nav_canonical SET decision_state='APPROVED', decided_by=NULL, decision_source=NULL
                     WHERE route = '" . $conn->real_escape_string($pend['route']) . "'");
echo "② ترقيةٌ بالصمت: " . ($r2 ? "مرَّت ✗ (عيب!)" : "مُنعت ✔ — {$conn->error}") . "\n";

/* ③ تطبيقٌ مؤقَّتٌ لصفٍّ غيرِ قابلٍ للعكس — يجب أن يُرفض */
$conn->query("UPDATE nav_canonical SET provisional_reversible = 0 WHERE route = '" . $conn->real_escape_string($pend['route']) . "'");
$r3 = $conn->query("UPDATE nav_canonical SET application_state='PROVISIONALLY_APPLIED_NO_OBJECTION'
                     WHERE route = '" . $conn->real_escape_string($pend['route']) . "'");
echo "③ تطبيقٌ مؤقَّتٌ خارجَ النطاق: " . ($r3 ? "مرَّ ✗ (عيب!)" : "مُنع ✔ — {$conn->error}") . "\n";
$conn->query("UPDATE nav_canonical SET provisional_reversible = 1 WHERE route = '" . $conn->real_escape_string($pend['route']) . "'");

$bad = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical
                            WHERE decision_state IN ('APPROVED','REJECTED')
                              AND decided_by IS NULL AND (decision_source IS NULL OR decision_source='')")->fetch_assoc()['c'];
echo "\nمحسومٌ بلا فاعلٍ ولا مصدر: {$bad}\n";
if ($bad > 0 || $r2 || $r3 || !$okUpd) { exit("✗ الحرسُ غيرُ محكم\n"); }
echo "✔ الحرسُ محكمٌ بشاهدٍ مُشغَّل: المشروعُ يمرُّ · والصمتُ يُرفض · وخارجُ النطاقِ يُرفض\n";
