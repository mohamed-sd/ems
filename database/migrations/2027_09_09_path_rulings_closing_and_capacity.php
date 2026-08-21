<?php
/**
 * 2027_09_09_path_rulings_closing_and_capacity.php
 *   أحكامُ المساراتِ — INJ-FIX-01 · GAP-24 و GAP-31
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **GAP-24** «جدولان يحملان معنى الإقفال · إخراجُ الوهميِّ أو وصلُه بالحقيقيّ».
 *   والحسمُ **بعدَدِ قرّاءِ الإنتاجِ لا بالانطباع**:
 *     · `fin_financial_periods` — **أحدَ عشرَ قارئًا** ومنهم `EventPublisher`
 *       نفسُه (يتحقّق أن الفترةَ مفتوحةٌ قبلَ النشر) ⇒ **هو سلطةُ الإقفال**.
 *     · `scr_monthly_close` — **قارئان فقط** وكلاهما إعلانُ سجلٍّ لا قرار
 *       (`TenantRegistry` و`cmp03_registry`) ⇒ **وهميُّ السلطة**.
 *     · `fin_closing_items` — بنودُ قائمةِ فحصٍ تابعةٌ للفترةِ لا سلطةٌ ثانية.
 *
 * ◆ **ويُخرَج الوهميُّ من الخدمةِ ولا يُحذف**: فيه عشرون صفَّ إقفالٍ تشغيليٍّ
 *   (موقعٌ وعقدٌ وساعات) وهي بيانٌ حقيقيٌّ وإن لم يكن سلطةً. **فالحذفُ فقدٌ
 *   والإبقاءُ بلا حكمٍ لبسٌ** — والحكمُ المكتوبُ يرفع الاثنين.
 *
 * ◆ **GAP-31** «صادرُ السعةِ ومحرّكا تصعيدٍ مساراتٌ لم تُعَدّ · حكمٌ لكلٍّ ·
 *   صفرُ مسارٍ بلا حكمٍ مكتوب». فتُحكَم الثلاثةُ بأثرِها المقيس.
 *
 * التشغيل:  php database/migrations/2027_09_09_path_rulings_closing_and_capacity.php
 * الرجوع :  php database/migrations/2027_09_09_path_rulings_closing_and_capacity.php --revert
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

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_path_rulings`");
    echo "↺ أُسقط سجلُّ أحكامِ المسارات\n";
    exit(0);
}

$conn->query("CREATE TABLE IF NOT EXISTS `gov_path_rulings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `gap`           VARCHAR(16)  NOT NULL,
    `path_key`      VARCHAR(96)  NOT NULL,
    `ruling`        VARCHAR(32)  NOT NULL
        COMMENT 'AUTHORITY | NON_AUTHORITATIVE | SUBORDINATE | ACTIVE_ENGINE | STAGING_ONLY',
    `authority_of`  VARCHAR(96)  NULL COMMENT 'إن كان تابعًا فمن يحكمه',
    `prod_readers`  SMALLINT UNSIGNED NOT NULL,
    `evidence`      VARCHAR(300) NOT NULL,
    `reason`        VARCHAR(500) NOT NULL,
    `decided_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_path` (`path_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GAP-24 و GAP-31 — حكمٌ مكتوبٌ لكلِّ مسارٍ يحمل معنًى حاكمًا'");

$R = array(
    /* ── GAP-24 ── */
    array('GAP-24', 'fin_financial_periods', 'AUTHORITY', null, 11,
        'EventPublisher · HandoverService · ContractSignedEffects · periods_fin وغيرُها',
        'سلطةُ الإقفالِ الوحيدة — وناشرُ الأحداثِ نفسُه يتحقّق منها قبلَ النشر، فلا واقعةَ تُنشر في فترةٍ مقفلة'),
    array('GAP-24', 'scr_monthly_close', 'NON_AUTHORITATIVE', 'fin_financial_periods', 2,
        'TenantRegistry (إعلانُ جدول) · cmp03_registry (سجلّ) — وصفرُ قارئِ قرار',
        'سجلُّ إقفالٍ تشغيليٌّ بالموقعِ والعقدِ والساعات — **بيانٌ حقيقيٌّ لا سلطة**. أُخرج من الخدمةِ حكمًا ولم يُحذف: الحذفُ فقدٌ والإبقاءُ بلا حكمٍ لبس'),
    array('GAP-24', 'fin_closing_items', 'SUBORDINATE', 'fin_financial_periods', 2,
        'periods_fin.php — بنودُ قائمةِ فحصٍ داخلَ الفترة',
        'بنودُ قائمةِ فحصِ الإقفالِ تابعةٌ لفترتِها بـperiod_id — ليست سلطةً ثانيةً بل تفصيلُ الأولى'),
    /* ── GAP-31 ── */
    array('GAP-31', 'capacity_outbox', 'STAGING_ONLY', 'ems_business_events', 1,
        'CapacityService — كاتبٌ واحدٌ وصفرُ مستهلكٍ يقرأ منه',
        'صادرُ السعةِ **مرحلةُ تهيئةٍ لا مسارُ نشرٍ ثانٍ**: يُكتب فيه ثم يُنشر بـEventPublisher إلى الجذرِ الواحد ems_business_events. ولا يُقرأ منه إسقاطٌ — فليس مسارًا موازيًا يُطفأ بل مخزنٌ وسيط'),
    array('GAP-31', 'work_escalations_engine', 'ACTIVE_ENGINE', null, 2,
        'WorkItemService · Operations/cron_wfm_engine.php — و2,008 بندًا مفتوحًا',
        'محرّكُ تصعيدِ بنودِ العملِ حيٌّ بأثرٍ مقيس — يُعَدُّ مسارًا حاكمًا قائمًا ولا يُطفأ'),
    array('GAP-31', 'ticket_escalations_engine', 'ACTIVE_ENGINE', null, 3,
        'SlaMonitor · TicketRouter · TicketStateService — و74 تصعيدًا بـ20 قاعدةً نشطة',
        'محرّكُ تصعيدِ البلاغاتِ حيٌّ بقواعدَ نشطةٍ وأثرٍ مقيس — مسارٌ حاكمٌ قائم'),
);

$st = $conn->prepare("INSERT INTO `gov_path_rulings`
        (`gap`,`path_key`,`ruling`,`authority_of`,`prod_readers`,`evidence`,`reason`)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE `ruling`=VALUES(`ruling`), `authority_of`=VALUES(`authority_of`),
            `prod_readers`=VALUES(`prod_readers`), `evidence`=VALUES(`evidence`), `reason`=VALUES(`reason`)");
$n = 0;
foreach ($R as $x) {
    $st->bind_param('ssssiss', $x[0], $x[1], $x[2], $x[3], $x[4], $x[5], $x[6]);
    if ($st->execute()) { $n++; }
    else { echo "✘ {$x[1]}: {$st->error}\n"; }
}
$st->close();
echo "① أحكامُ المسارات: {$n} مسارًا\n";

/* ══ ② الوهميُّ يُعلَن في الجدولِ نفسِه — فمن يفتحه يقرأ حكمَه ═══════════ */
$conn->query("ALTER TABLE `scr_monthly_close`
    COMMENT='GAP-24: سجلُّ إقفالٍ تشغيليٌّ — **ليس سلطةَ الإقفال**. السلطةُ fin_financial_periods'");
echo "② وُسم `scr_monthly_close` في تعليقِ الجدولِ نفسِه\n";
$conn->query("ALTER TABLE `fin_financial_periods`
    COMMENT='GAP-24: **سلطةُ الإقفالِ الوحيدة** — EventPublisher يتحقّق منها قبلَ نشرِ أيِّ واقعة'");
echo "   ووُسمت `fin_financial_periods` سلطةً\n";

echo "───────────────────────────────────────────────────────────────\n";
$q = $conn->query("SELECT `gap`,`path_key`,`ruling`,`prod_readers` FROM `gov_path_rulings` ORDER BY `gap`,`path_key`");
while ($q && $x = $q->fetch_assoc()) {
    printf("  %-8s %-28s %-20s قرّاء=%d\n", $x['gap'], $x['path_key'], $x['ruling'], $x['prod_readers']);
}
echo "◆ وصفرُ مسارٍ من هذه بلا حكمٍ مكتوبٍ ومُسنَدٍ إلى قرّائِه المقيسين.\n";
