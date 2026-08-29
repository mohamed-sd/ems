<?php
/**
 * 2028_01_04_rpr02_grain_fact_scope.php — طبقةُ الحبّةِ ومداها عمودًا لا نثرًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `rpr02_grain_measure.php` يفرّق منذ بنائه بين **الخاصِّ
 *   والمشترك** ويكتب الفرقَ في الشاهدِ نصًّا (`[OWN]` / `[SHARED_KIT]` ·
 *   `INFRA_ONLY`)، **ويطبّق الفرقَ على خرقِ «حبّتين» وحدَه** (`G4`) بنصِّ رأسِه:
 *   *«وخرقُ «حبّتين» لا يُحكَم إلّا على الخاصّ — لأنَّ نسبةَ جداولِ عُدّةٍ
 *   يشتملها أحدَ عشرَ سطحًا إلى كلِّ واحدٍ منها أنتجت ٢٦٥ خرقًا كاذبًا»*.
 *
 * ⛔ **لكنَّ لوحةَ §١٢ لا تطبّقه**: مقاييسُ **#٩** و**#١٠** و**#١١** تقرأ
 *   `grain_entity` **بلا نظرٍ إلى طبقتِه** — فعاد الخرقُ الكاذبُ نفسُه طبقةً
 *   أعلى. والمقيس: **٧٧ من ٢٠٨** سطحَ كتابةٍ كيانُه من **كِيتٍ مشتركٍ** أو
 *   **بنيةٍ صِرفة**، ومنها `ems_post_idempotency` و`guard_denials`
 *   و`fin_event_effects` — **وهذه ليست حقائقَ أعمالٍ يملكها سطح**.
 *
 * ◆ **والعلاجُ عمودٌ لا مِصفاةُ نصّ**: قراءةُ `[SHARED_KIT]` بـ`LIKE` على شاهدٍ
 *   نثريٍّ **مقياسٌ على شكلِ عبارةٍ لا على حقيقة** — يسقط بتغييرِ صياغةٍ واحدة.
 *   ⇒ فالطبقةُ والمدى يصيران **عمودَين مفهرسَين** يكتبهما المقياسُ نفسُه.
 *
 * ⚠ **والوسمُ لا يُشتقّ من الطبقةِ وحدَها**: `G1_CMP03_DECLARED` و
 *   `G1B_SELF_DECLARED` **يُعلن فيهما السطحُ جدولَه في ملفِّه**، فالطبقةُ
 *   المكتوبةُ في شاهدِهما لا أثرَ لها في الحكم — **والمُعلَنُ ذاتيًّا خاصٌّ ولو
 *   قيست جداولُه من كِيت**. ⇒ فعمودُ `grain_fact_scope` يحمل **الخلاصةَ**
 *   (`OWN_FACT` / `SHARED_KIT` / `INFRA_ONLY`) لا الطبقةَ الخام.
 *
 * التشغيل: php database/migrations/2028_01_04_rpr02_grain_fact_scope.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$r = $conn->query("SHOW COLUMNS FROM `repair01_screen_registry` LIKE 'grain_fact_scope'");
if (!$r || !$r->num_rows) {
    $ok = $conn->query("ALTER TABLE `repair01_screen_registry`
        ADD COLUMN `grain_tier` ENUM('OWN','SHARED_KIT','NONE') NOT NULL DEFAULT 'NONE'
            COMMENT 'الطبقة الخام: هل قيست جداول السطح من مصدره الخاص ام من كيت مشترك',
        ADD COLUMN `grain_fact_scope` ENUM('OWN_FACT','SHARED_KIT','INFRA_ONLY','NONE')
            NOT NULL DEFAULT 'NONE'
            COMMENT 'خلاصة الحكم: هل الكيان حقيقة اعمال يملكها هذا السطح — وعليها وحدها تقاس 9 و10 و11',
        ADD KEY `ix_fact_scope` (`grain_fact_scope`)");
    if (!$ok) { exit("✘ تعذّر إضافةُ الأعمدة: {$conn->error}\n"); }
    echo "  ✔ أُضيفت `grain_tier` و`grain_fact_scope`\n";
} else {
    echo "  ◆ العمودان قائمان سلفًا — ولا يُعاد إنشاؤهما\n";
}

echo "  ◆ **ولم تُملأ قيمةٌ واحدة** — يملؤها `rpr02_grain_measure.php --apply` بقياسٍ حيّ\n";
echo "    ⛔ ولا تُشتقّ هنا من الشاهدِ النثريِّ: مِصفاةُ نصٍّ في هجرةٍ تُجمِّد شكلَ عبارة\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الطبقةِ والمدى مفتوحٌ — و`grain_entity` لم يُمَسّ\n";
