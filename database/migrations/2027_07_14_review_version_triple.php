<?php
/**
 * 2027_07_14_review_version_triple.php — المراجعةُ تُقيَّد بالنسخةِ التي رُوجعت
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ قرارُ المالك (ف١٣): «أضِفْ `commit_hash` و`component_version` و
 *   `visual_baseline_version` إلى سجلِّ المراجعةِ المستقلة، **واجعلِ البوابةَ
 *   ترفض ثلاثيًّا غيرَ مطابق**».
 *
 * ◆ والعطبُ الذي يسدُّه صريح: مراجعةٌ بشريةٌ تمَّت على نسخةٍ، ثم يُبنى فوقَها
 *   عشرون التزامًا، فتُقرأ المراجعةُ القديمةُ شاهدًا على شيفرةٍ **لم يرَها
 *   المراجِعُ قطُّ**. فالشهادةُ تُصبح صحيحةً في نصِّها كاذبةً في مدلولِها.
 *
 * ◆ فالثلاثيُّ يُصبح **جزءًا من هويةِ الشهادة**: تغيُّرُ أيِّ واحدٍ منه يُبطل
 *   انطباقَها، ولا يُسنِد ترقيةً. وليس إبطالًا للشهادةِ نفسِها — هي محفوظةٌ
 *   بتاريخِها ونسختِها؛ إنما يمتنع **انسحابُها على غيرِ ما شهدت له**.
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

if (!$has('gov_independent_reviews', 'commit_hash')) {
    $conn->query("ALTER TABLE gov_independent_reviews
        ADD `commit_hash` VARCHAR(40) NOT NULL DEFAULT ''
            COMMENT 'التزامُ الشيفرةِ الذي رآه المراجِعُ — لا الذي يُقرأ اليوم'
            AFTER component_version,
        ADD `visual_baseline_version` VARCHAR(40) NOT NULL DEFAULT ''
            COMMENT 'وسمُ خطِّ الأساسِ البصريِّ لحظةَ المراجعة'
            AFTER commit_hash");
}

/* ── القيدُ: شهادةٌ بلا ثلاثيٍّ كاملٍ لا تُقبل أصلًا ── */
$conn->query("DROP TRIGGER IF EXISTS `trg_review_version_triple`");
$ok = $conn->query("CREATE TRIGGER `trg_review_version_triple`
    BEFORE INSERT ON `gov_independent_reviews` FOR EACH ROW
    BEGIN
        IF NEW.component_version = '' OR NEW.component_version IS NULL
           OR NEW.commit_hash = '' OR NEW.commit_hash IS NULL
           OR NEW.visual_baseline_version = '' OR NEW.visual_baseline_version IS NULL THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'شهادةٌ بلا ثلاثيِّ نسخةٍ كاملٍ مرفوضة: المكوّناتُ · الالتزامُ · خطُّ الأساسِ البصريّ — فالشهادةُ تخصُّ نسخةً لا شيفرةً مطلقة';
        END IF;
        IF CHAR_LENGTH(NEW.commit_hash) < 7 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'بصمةُ الالتزامِ أقصرُ من سبعةِ محارفَ — لا تُعيِّن نسخةً';
        END IF;
    END");
if (!$ok) { echo "⚠ القادحُ لم يُنشأ ({$conn->error}) — والبوابةُ تفحصه برمجيًّا\n"; }

echo "════ ثلاثيُّ نسخةِ المراجعة ════\n";
$r = $conn->query("SELECT COUNT(*) c FROM gov_independent_reviews")->fetch_assoc();
echo "  شهاداتٌ مسجَّلة: {$r['c']}\n";
echo "✔ commit_hash و visual_baseline_version أُضيفا — والشهادةُ تخصُّ نسختَها\n";
