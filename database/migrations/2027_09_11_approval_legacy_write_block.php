<?php
/**
 * 2027_09_11_approval_legacy_write_block.php
 *   المرحلةُ السادسة — الكتابةُ في المسارِ القديمِ ممنوعةٌ بقيد — INJ-FIX-01 · GAP-02
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «مصفوفةُ التوحيدِ لمجالِ الاعتمادِ مملوءةٌ · **والقلبُ بلغ
 *   المرحلةَ السادسة**». والسادسةُ في بروتوكولِ القلبِ السباعيِّ هي **منعُ
 *   الكاتبِ القديم**.
 *
 * ◆ **ولماذا يجوز بلوغُها الآن بلا نافذةِ ظلّ**: نافذةُ الظلِّ تُثبت أن الجديدَ
 *   **يطابق** القديمَ قبلَ إطفائِه. والمقيسُ أن `approval_workflow_rules`
 *   **ثلاثةٌ وعشرون صفًّا كلُّها `is_active = 0`** — أي **صفرُ قاعدةٍ نافذة**،
 *   وكتّابُه هجراتٌ لا شفرةُ تشغيل. **فلا شيءَ في القديمِ يُقارَن به.**
 *   ⇒ والظلُّ على مسارٍ فارغٍ يقيس الفراغَ ويُسمّيه تطابقًا — وهو أسوأُ من
 *     تركِه: يمنح ثقةً لم تُكتسب.
 *
 * ◆ **والقيدُ يمنع العودةَ لا الوجود**: الصفوفُ تبقى مرجعًا تاريخيًّا، ويُمنع
 *   **تنشيطُ** أيِّ صفٍّ منها أو إدراجُ صفٍّ نافذٍ جديد. فالمسارُ القديمُ
 *   **يُقرأ ولا يُكتب نافذًا** — وهو نصُّ المرحلةِ السادسة.
 *
 * ◆ **ولا يُطفأ القارئ**: ثلاثةَ عشرَ قارئًا ما يزالون يقرؤونه — وإطفاؤهم
 *   المرحلةُ السابعة (التقاعد)، ولها شرطُها. **والقيدُ لا يكسرهم**: القراءةُ
 *   تعمل، والكتابةُ النافذةُ وحدَها تُردّ.
 *
 * التشغيل:  php database/migrations/2027_09_11_approval_legacy_write_block.php
 * الرجوع :  php database/migrations/2027_09_11_approval_legacy_write_block.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$CK = 'chk_awr_legacy_write_blocked';

if (in_array('--revert', $argv, true)) {
    $conn->query("ALTER TABLE `approval_workflow_rules` DROP CHECK `{$CK}`");
    echo ($conn->errno ? "↺ لم يكن القيدُ موجودًا\n" : "↺ أُسقط القيدُ {$CK}\n");
    exit(0);
}

/* ══ ① لا يُوضَع القيدُ فوقَ عطب — يُتحقَّق أن القديمَ فارغٌ فعلًا ═══════════ */
$q = $conn->query("SELECT COUNT(*) FROM `approval_workflow_rules` WHERE `is_active` <> 0");
$live = $q ? (int) $q->fetch_row()[0] : -1;
$q = $conn->query("SELECT COUNT(*) FROM `approval_workflow_rules`");
$all = $q ? (int) $q->fetch_row()[0] : 0;
echo "① المسارُ القديم: {$all} صفًّا · **نافذٌ منها: {$live}**\n";
if ($live !== 0) {
    exit("✘ فيه {$live} قاعدةً نافذة — **لا يُمنع الكاتبُ قبلَ نافذةِ ظلٍّ تُثبت التطابق**. أُوقفت الهجرة\n");
}

/* ══ ② والمسارُ القانونيُّ حيٌّ — وإلا كان المنعُ قطعًا لا قلبًا ═══════════ */
$q = $conn->query("SELECT COUNT(*) FROM `gov_ladders`");
$lad = $q ? (int) $q->fetch_row()[0] : 0;
$q = $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`");
$stp = $q ? (int) $q->fetch_row()[0] : 0;
echo "② المسارُ القانونيّ: {$lad} سلّمًا · {$stp} خطوة\n";
if ($lad === 0 || $stp === 0) {
    exit("✘ المسارُ القانونيُّ غيرُ مأهول — **المنعُ حينئذٍ قطعٌ لا قلب**. أُوقفت الهجرة\n");
}

/* ══ ③ القيد ══════════════════════════════════════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='approval_workflow_rules'
                      AND CONSTRAINT_NAME='{$CK}'");
if ($q && (int) $q->fetch_row()[0] > 0) {
    echo "③ القيدُ قائمٌ سلفًا\n";
} else {
    if (!$conn->query("ALTER TABLE `approval_workflow_rules`
            ADD CONSTRAINT `{$CK}` CHECK (`is_active` = 0)")) {
        exit("✘ تعذّر وضعُ القيد: {$conn->error}\n");
    }
    echo "③ وُضع القيدُ {$CK}: `is_active = 0` — **لا قاعدةَ نافذةٌ تُكتب في القديم**\n";
}

/* ══ ④ يُجرَّب حيًّا — قيدٌ يُعلَن ولا يُجرَّب توثيقٌ لا قيد ═════════════════ */
$conn->query("UPDATE `approval_workflow_rules` SET `is_active` = 1 WHERE `id` = (
    SELECT * FROM (SELECT MIN(`id`) FROM `approval_workflow_rules`) t)");
$blocked = ($conn->errno !== 0);
$errno = $conn->errno;
echo "④ " . ($blocked
    ? "✔ **جُرِّب فرُدّ**: محاولةُ تنشيطِ قاعدةٍ قديمةٍ رُفضت (errno {$errno})\n"
    : "✘ **لم يُردّ** — القيدُ لا يمنع فعلًا\n");
if (!$blocked) {
    $conn->query("UPDATE `approval_workflow_rules` SET `is_active` = 0");
    echo "   (أُعيد الصفُّ إلى غيرِ نافذ)\n";
}

/* ══ ⑤ والقراءةُ لم تُكسر ═════════════════════════════════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM `approval_workflow_rules`");
$rd = $q ? (int) $q->fetch_row()[0] : -1;
echo "⑤ القراءةُ تعمل: {$rd} صفًّا مقروءًا — **والقارئُ لا يُطفأ في السادسة**\n";
echo "◆ وإطفاءُ القرّاءِ الثلاثةَ عشرَ هو المرحلةُ السابعةُ (التقاعد) ولها شرطُها.\n";
