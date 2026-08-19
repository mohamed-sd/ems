<?php
/**
 * 2027_08_02_training_context_carry.php — سياقُ التدريبِ يُحمل إلى آخرِ السلسلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (سادسًا): «حساباتُ التدريبِ يجب أن تحملَ سياقَ التدريبِ **إلى آخرِ
 *   السلسلة** مع **بوابةٍ تُثبت صفرَ تسرّب**».
 *
 * ◆ **والسلسلةُ مقيسةٌ لا مفترَضة**: `correlation_id` موجودٌ في **جدولَين اثنَين
 *   فقط** في المخططِ كلِّه — `ems_business_events` (الجذرُ المحايدُ · ADR-15)
 *   و`fin_financial_events`. **فآخرُ السلسلةِ معلومٌ وقصير**، والحدُّ الذي
 *   يُثبَت عليه صفرُ التسرّبِ حدٌّ حقيقيٌّ لا دعوى.
 *
 * ◆ **والوسمُ يُشتقُّ ولا يُمرَّر**: الحقيقةُ تأخذ `is_training` من **`created_by`
 *   لحظةَ الكتابة** لا من متغيّرِ جلسةٍ يمرُّ عبر الطبقات. فلا يُنسى في نداءٍ،
 *   ولا يُزوَّر بحمولةٍ، ولا ينكسر إذا استُدعي الناشرُ من CLI. **وما يُشتقُّ في
 *   موضعٍ واحدٍ لا يتفرّق؛ وما يُمرَّر عبر عشرةِ نداءاتٍ يسقط في أحدِها.**
 *
 * ◆ **والافتراضُ صفرٌ لا واحد**: الحسابُ عاديٌّ حتى يُعلَنَ تدريبيًّا — فمن أُنشئ
 *   قبلَ هذه الهجرةِ لا يصير تدريبيًّا بالسهو، **والخطأُ في هذا الاتجاهِ أرحمُ**:
 *   حقيقةٌ إنتاجيةٌ تُوسَم تدريبًا تختفي من التقارير، وذلك فقدٌ صامت.
 *
 * ◆ ولا حذفَ ولا تعديلَ لصفٍّ قائم — إضافةُ عمودَين وفهرسَين فقط.
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

function hascol(mysqli $c, string $t, string $col): bool {
    $st = $c->prepare("SELECT 1 FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->bind_param('ss', $t, $col); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
}
function hasidx(mysqli $c, string $t, string $ix): bool {
    $st = $c->prepare("SELECT 1 FROM information_schema.STATISTICS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1");
    $st->bind_param('ss', $t, $ix); $st->execute();
    $n = $st->get_result()->num_rows; $st->close(); return $n > 0;
}

$plan = array(
    array('users', 'is_training',
          "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'حسابُ تدريبٍ — كلُّ ما يكتبه يُوسَم ولا يدخل الإنتاج'",
          'ix_users_training'),
    /* ◆ **ولحظةُ الإعلانِ لازمةٌ لا ترفٌ**: `is_training` حالةٌ **راهنةٌ** تتغيّر،
       ووسمُ الحقيقةِ يسجّل الحالةَ **لحظةَ الكتابة**. فمقارنةُ المخزونِ القديمِ
       بالعلمِ الراهنِ تُنتج مخالفاتٍ وهمية: حسابٌ يُعلَن تدريبيًّا اليومَ تصير
       كلُّ حقائقِه السابقةِ «متسرّبة» وهي إنتاجيةٌ صحيحة. **وقد وقعَ هذا فعلًا:
       أعطت البوابةُ 7 مخالفاتٍ كلُّها وهمٌ من هذا الباب.** فتُسجَّل اللحظةُ،
       ولا يُحاسَب على وسمٍ إلا ما كُتب بعدَها. */
    array('users', 'training_since',
          "DATETIME NULL DEFAULT NULL COMMENT 'لحظةُ إعلانِ الحسابِ تدريبيًّا — ولا يُحاسَب على وسمٍ ما كُتب قبلَها'",
          'ix_users_training_since'),
    array('ems_business_events', 'is_training',
          "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يُشتقُّ من users.is_training للكاتبِ لحظةَ الكتابة — لا يُمرَّر'",
          'ix_ebe_training'),
    array('fin_financial_events', 'is_training',
          "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'يرثُ وسمَ الحقيقةِ الجذرِ بـcorrelation_id'",
          'ix_ffe_training'),
);

$added = 0; $idx = 0; $errs = 0;
foreach ($plan as $row) {
    list($t, $col, $spec, $ix) = $row;
    if (!hascol($conn, $t, $col)) {
        if ($conn->query("ALTER TABLE `{$t}` ADD `{$col}` {$spec}")) { $added++; }
        else { echo "  ✘ {$t}.{$col}: {$conn->error}\n"; $errs++; continue; }
    }
    if (!hasidx($conn, $t, $ix)) {
        if ($conn->query("ALTER TABLE `{$t}` ADD INDEX `{$ix}` (`{$col}`)")) { $idx++; }
        else { echo "  ◆ {$t}.{$ix}: " . mb_substr($conn->error, 0, 80) . "\n"; }
    }
}

echo "══ حملُ سياقِ التدريب ══\n";
echo "  أُضيف: {$added} عمودًا · {$idx} فهرسًا\n";
$have = 0;
foreach ($plan as $row) { if (hascol($conn, $row[0], $row[1])) { $have++; } }
echo "  الحاضرُ الآن: {$have} من " . count($plan) . "\n";

/* ══ حسابُ التدريبِ الأولُ يُعلَن صراحةً لا يُخمَّن ══════════════════════ */
$q = $conn->query("SELECT COUNT(*) FROM users WHERE is_training = 1");
$n = $q ? (int) $q->fetch_row()[0] : 0;
echo "  حساباتُ التدريبِ المُعلَنةُ الآن: {$n}"
   . ($n === 0 ? " — **ولا يُعلَن حسابٌ تدريبيًّا إلا بقرارِك**: تعليمُ حسابٍ\n"
                . "    يُخرج كلَّ ما يكتبه من التقارير، فهو قرارُ حوكمةٍ لا خطوةُ تهيئة.\n"
                : "\n");
echo ($errs === 0 && $have === count($plan) ? "✔ تمّ\n" : "✘ ناقص\n");
exit(($errs === 0 && $have === count($plan)) ? 0 : 1);
