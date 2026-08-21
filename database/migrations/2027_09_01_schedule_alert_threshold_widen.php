<?php
/**
 * 2027_09_01_schedule_alert_threshold_widen.php
 *   عتبةُ إنذارِ الجدولةِ لا تتسع لدوريتِها — INJ-FIX-01 · الحاجز ③ (المجدول)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ بنيويٌّ لا إعداديّ.** `alert_after_seconds` من نوعِ
 *   `SMALLINT UNSIGNED` — أقصاهُ **٦٥٥٣٥ ثانيةً = ١٨٫٢ ساعة**. ودوريةُ
 *   `statement_build` **يوميّةٌ (٢٤ ساعة)** و`depreciation_run` **شهريّةٌ (~٧٢٠)**.
 *   ⇒ **العتبةُ الصحيحةُ غيرُ قابلةٍ للتعبيرِ أصلًا**، ولذلك وقفت الثلاثُ عند
 *   ٦٥٥٣٥ بالضبط: **سقفُ العمودِ لا قرارُ مهندس**.
 *
 * ◆ **وأثرُه أن الإنذارَ يصير ضجيجًا**: عتبةٌ أقصرُ من الدوريةِ **تُنذر يقينًا**
 *   في كلِّ فترةٍ صحيحة — فالشهريةُ تُنذر تسعةً وعشرين يومًا من ثلاثين وهي
 *   سليمة. ⇒ يُتجاهَل الإنذارُ ⇒ **فحين تتوقف مهمةٌ ماليةٌ فعلًا لا ينتبه أحد.**
 *   و`cron_jobs.php` يقول في ترويستِه «والصمتُ أخطرُ من الفشل» — وهذا صمتٌ
 *   بضجيجٍ لا بسكوت.
 *
 * ◆ **الحكمُ الثابتُ بعدَ هذه الهجرة:** `alert_after_seconds` **> دوريةِ الجدولة**.
 *   وأيُّ عتبةٍ أقصرُ من دوريتِها إنذارٌ كاذبٌ بالبناء.
 *
 * ◆ ولا تُمسُّ منطقُ التشغيلِ ولا مواعيدُه — عتبةُ الإنذارِ وحدَها.
 *
 * التشغيل:  php database/migrations/2027_09_01_schedule_alert_threshold_widen.php
 * الرجوع :  php database/migrations/2027_09_01_schedule_alert_threshold_widen.php --revert
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

/**
 * دوريةُ التعبيرِ الزمنيِّ بالثواني — تقديرٌ محافظٌ يكفي لعتبةِ إنذار.
 * ولا يُرجَع صفرٌ أبدًا: تعبيرٌ لا يُفهَم يُعامَل يوميًّا (الأحوطُ للإنذار).
 */
function cron_period_seconds($expr)
{
    $f = preg_split('/\s+/', trim((string) $expr));
    if (count($f) !== 5) { return 86400; }
    list($mi, $ho, $dm, $mo, $dw) = $f;
    if (strpos($mi, '*/') === 0) { return max(60, (int) substr($mi, 2) * 60); }
    if ($mi === '*')             { return 60; }
    /* دقيقةٌ مثبَّتة ⇒ الدوريةُ تتحدَّد بما فوقَها */
    if (strpos($ho, '*/') === 0) { return max(3600, (int) substr($ho, 2) * 3600); }
    if ($ho === '*')             { return 3600; }
    if ($dm === '*' && $mo === '*' && $dw === '*') { return 86400; }        /* يوميّ */
    if ($dm === '*' && $mo === '*' && $dw !== '*') { return 604800; }       /* أسبوعيّ */
    if ($dm !== '*' && $mo === '*')                { return 2678400; }      /* شهريّ (31 يومًا) */
    return 31622400;                                                        /* سنويّ */
}
/** المهلةُ = الدوريةُ + فسحةٌ بقدرِ ربعِها (بحدٍّ أدنى ساعة) — فلا تُنذر السليمة */
function alert_for($period) { return (int) ($period + max(3600, (int) ($period / 4))); }

/* ══ الرجوع ═══════════════════════════════════════════════════════════════ */
if (in_array('--revert', $argv, true)) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ems_job_schedule_alert_backup'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $q = $conn->query("SELECT id, alert_before FROM `ems_job_schedule_alert_backup`");
        $n = 0;
        while ($q && $x = $q->fetch_assoc()) {
            /* لا تُعاد قيمةٌ تتجاوز سقفَ SMALLINT — فالعمودُ سيضيق ثانيةً */
            $v = min(65535, (int) $x['alert_before']);
            $st = $conn->prepare("UPDATE `ems_job_schedule` SET `alert_after_seconds`=? WHERE `id`=?");
            $st->bind_param('ii', $v, $x['id']);
            $st->execute(); $n += $st->affected_rows; $st->close();
        }
        echo "↺ أُعيدت العتباتُ لـ{$n} جدولة\n";
        $conn->query("DROP TABLE `ems_job_schedule_alert_backup`");
    }
    $conn->query("ALTER TABLE `ems_job_schedule`
                  MODIFY `alert_after_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 3600");
    echo "↺ ضُيِّق العمودُ إلى SMALLINT UNSIGNED\n";
    exit(0);
}

/* ══ ① نسخةُ الرجوع ═══════════════════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `ems_job_schedule_alert_backup` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `alert_before` INT UNSIGNED NOT NULL,
    `saved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("INSERT INTO `ems_job_schedule_alert_backup` (`id`,`alert_before`)
              SELECT `id`, `alert_after_seconds` FROM `ems_job_schedule`
              ON DUPLICATE KEY UPDATE `alert_before` = VALUES(`alert_before`)");
echo "① حُفظت العتباتُ السابقة\n";

/* ══ ② توسيعُ العمود ══════════════════════════════════════════════════════ */
if (!$conn->query("ALTER TABLE `ems_job_schedule`
                   MODIFY `alert_after_seconds` INT UNSIGNED NOT NULL DEFAULT 3600
                   COMMENT 'يجب أن تتجاوز دوريةَ الجدولة — وإلا فالإنذارُ كاذبٌ بالبناء'")) {
    exit("✘ تعذّر توسيعُ العمود: {$conn->error}\n");
}
echo "② وُسِّع alert_after_seconds: SMALLINT ⇐ INT UNSIGNED (السقف 65,535 ⇐ 4,294,967,295)\n";

/* ══ ③ عتبةٌ صحيحةٌ لكلِّ جدولةٍ دوريتُها تتجاوز عتبتَها ═════════════════ */
$rows = array();
$q = $conn->query("SELECT `id`,`job_type`,`cron_expr`,`alert_after_seconds` FROM `ems_job_schedule` ORDER BY `id`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$fixed = 0;
echo "───────────────────────────────────────────────────────────────\n";
foreach ($rows as $r) {
    $per = cron_period_seconds($r['cron_expr']);
    $cur = (int) $r['alert_after_seconds'];
    if ($cur > $per) {
        printf("  · %-18s %-14s دورية=%-9s عتبة=%-9s ✔ تكفي\n",
            $r['job_type'], $r['cron_expr'], $per, $cur);
        continue;
    }
    $new = alert_for($per);
    $st = $conn->prepare("UPDATE `ems_job_schedule` SET `alert_after_seconds`=? WHERE `id`=?");
    $st->bind_param('ii', $new, $r['id']);
    if (!$st->execute()) { echo "  ✘ {$r['job_type']}: {$st->error}\n"; $st->close(); continue; }
    $st->close(); $fixed++;
    printf("  ◆ %-18s %-14s دورية=%-9s عتبة=%s ⇐ %s\n",
        $r['job_type'], $r['cron_expr'], $per, $cur, $new);
}
echo "───────────────────────────────────────────────────────────────\n";
echo "③ صُحِّحت {$fixed} عتبةً كانت **أقصرَ من دوريتِها** — أي تُنذر يقينًا وهي سليمة\n";

/* ══ ④ الحالةُ بعدَ التصحيح ═══════════════════════════════════════════════ */
$q = $conn->query("SELECT `job_type`,`cron_expr`,`alert_after_seconds`,`last_success_at`,
                          TIMESTAMPDIFF(SECOND,`last_success_at`,NOW()) late
                     FROM `ems_job_schedule` WHERE `is_active`=1 ORDER BY `id`");
$stillLate = array();
while ($q && $x = $q->fetch_assoc()) {
    if ($x['last_success_at'] !== null && (int) $x['late'] > (int) $x['alert_after_seconds']) {
        $stillLate[] = $x['job_type'] . ' (متأخرة ' . round($x['late'] / 3600, 1) . 'س · العتبة '
                     . round($x['alert_after_seconds'] / 3600, 1) . 'س)';
    }
}
echo "④ **إنذارٌ حقيقيٌّ بعدَ التصحيح: " . count($stillLate) . "**\n";
foreach ($stillLate as $s) { echo "   ◆ {$s}\n"; }
echo "◆ وما بقي منذرًا الآن **تأخُّرٌ حقيقيٌّ** — لا ضجيجَ سقفِ عمود.\n";
