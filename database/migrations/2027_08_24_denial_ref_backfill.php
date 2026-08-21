<?php
/**
 * 2027_08_24_denial_ref_backfill.php
 *   سجلُّ رفضٍ بمرجعٍ فارغ — استخراجٌ لا اختراعٌ ولا حذف · INJ-FIX-01 · الموجة ب · GAP-14
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيسُ حيًّا: 24 صفًّا من 5240** في `guard_denials` بـ`attempted_ref = NULL`،
 *   كلُّها من نافذةٍ واحدةٍ (2026-08-10) وبثلاثةِ رموزٍ فقط:
 *       screen_write:Finance/budget_dept.php
 *       screen_write:Portal/dept_achievement.php
 *       screen_write:Tickets/dept_inbox.php
 *
 * ◆ **والجذرُ مُصلَحٌ سلفًا في الكود**: `includes/permissions_helper.php` كان
 *   يمرّر رمزًا مُركَّبًا `screen_write:<path>` **لا وجودَ له في `guard_policies`**،
 *   وسياسةُ الخدمةِ أن المجهولَ يُمنع مغلقًا — فصار كلُّ سطحٍ «حمايةً بلا صنف»
 *   ويُسجَّل رفضٌ بمرجعٍ فارغ. والكودُ اليومَ يتحقّق من وجودِ الرمزِ في الجدولِ
 *   أولًا ويتخطّى ما ليس مصنَّفًا. فالباقي **أثرٌ تاريخيٌّ لعيبٍ زال**.
 *
 * ◆ **ولا يُحذف سجلُّ رفضٍ ولا يُخترَع له مرجع**: المرجعُ **ليس مجهولًا** —
 *   هو داخلَ `guard_code` نفسِه بعدَ النقطتَين. فيُستخرَج منه استخراجًا، فيبقى
 *   الصفُّ كاملًا ويصير حقلُه مملوءًا بما كان يجب أن يحمله أصلًا.
 *   ⇐ لا حذفٌ (فالسجلُّ تدقيقيّ) ولا اختراعٌ (فالقيمةُ مشتقّةٌ من الصفِّ نفسِه).
 *
 * ◆ **وقيدٌ يمنع العودة**: `CHECK` يرفض المرجعَ الفارغَ أو المعدوم.
 *
 * التشغيل:  php database/migrations/2027_08_24_denial_ref_backfill.php
 * الرجوع :  php database/migrations/2027_08_24_denial_ref_backfill.php --revert
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

$CHECK  = 'chk_denial_ref_present';
$MARKER = 'backfilled:';   // بادئةٌ تُبقي الأثرَ مميَّزًا فلا يُقرأ مرجعًا أصليًّا

if (in_array('--revert', $argv, true)) {
    $conn->query("ALTER TABLE `guard_denials` DROP CONSTRAINT `{$CHECK}`");
    echo "↺ القيد: " . ($conn->errno ? "لم يُحذف ({$conn->error})" : "حُذف") . "\n";
    $conn->query("UPDATE `guard_denials` SET `attempted_ref` = NULL
                   WHERE `attempted_ref` LIKE '" . $conn->real_escape_string($MARKER) . "%'");
    echo "↺ أُعيد {$conn->affected_rows} صفًّا إلى مرجعٍ فارغ\n";
    exit(0);
}

/* ══ ① الجرد ═════════════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) FROM `guard_denials` WHERE `attempted_ref` IS NULL OR TRIM(`attempted_ref`) = ''");
$before = $r ? (int) $r->fetch_row()[0] : -1;
echo "① بمرجعٍ فارغٍ قبل: {$before}\n";

/* ══ ② الاستخراج — من `guard_code` بعدَ النقطتَين ═════════════════════════
   ◆ وما لا نقطتَين فيه لا يُخترع له مرجعٌ: يُوسَم بالرمزِ نفسِه، فيبقى
     مقروءًا ويُميَّز أنه مشتقٌّ لا أصليّ. */
$conn->query(
    "UPDATE `guard_denials`
        SET `attempted_ref` = CONCAT('{$MARKER}',
              CASE WHEN LOCATE(':', `guard_code`) > 0
                   THEN SUBSTRING(`guard_code`, LOCATE(':', `guard_code`) + 1)
                   ELSE `guard_code` END)
      WHERE `attempted_ref` IS NULL OR TRIM(`attempted_ref`) = ''");
if ($conn->errno) { exit("✘ فشلَ الاستخراج: {$conn->error}\n"); }
echo "② استُخرج المرجعُ من رمزِ الحارس: {$conn->affected_rows} صفًّا\n";

/* ══ ③ القيد ═════════════════════════════════════════════════════════════ */
$e = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'guard_denials'
                      AND CONSTRAINT_NAME = '{$CHECK}'");
if ($e && (int) $e->fetch_row()[0] > 0) {
    echo "③ القيد: قائمٌ سلفًا\n";
} else {
    $conn->query("ALTER TABLE `guard_denials` ADD CONSTRAINT `{$CHECK}`
                    CHECK (`attempted_ref` IS NOT NULL AND `attempted_ref` <> '')");
    echo "③ القيد: " . ($conn->errno ? "✘ {$conn->error}" : "أُضيف") . "\n";
}

/* ══ ④ استيثاق ═══════════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) FROM `guard_denials` WHERE `attempted_ref` IS NULL OR TRIM(`attempted_ref`) = ''");
$after = $r ? (int) $r->fetch_row()[0] : -1;
$r = $conn->query("SELECT COUNT(*) FROM `guard_denials`");
$tot = $r ? (int) $r->fetch_row()[0] : -1;
echo "───────────────────────────────────────────────────────────────\n";
echo "بمرجعٍ فارغٍ بعد: {$after} · الإجمالي {$tot} (لم يُحذف صفٌّ واحد)\n";
