<?php
/**
 * 2027_12_20_rpr02_platform_register.php — `PLATFORM` يُنقل إلى سجلٍّ مستقلّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحكمُ نصُّ الأمرِ لا اجتهادي** — `MASTER_EXEC` §٥·٢: *«فاعدُدْ كم تجميعًا
 *   في العُدّةِ يَعُدُّ ذلك الجدولَ مقامًا للإدارات: **فإن عدَّ أحدُها ٢٢ فالصفُّ
 *   يُفسد مقامًا ⇒ يُنقل إلى سجلٍّ مستقلّ**. ⛔ ولا تتركْه على حالِه بلا حكمٍ
 *   مسجَّل»*.
 *
 * ◆ **والعدُّ تمَّ ونتيجتُه مقيسة** — `master_exec_phase0_measure.php`:
 *   · `tools/repair01_w135_gate.php` G1 يشترط «خارجَ التسلسل = ٤» **ويقيس ٥
 *     فيرسُب** — وهو أحدُ الحواجبِ الثمانيةِ الحمراء. **فالصفُّ يحجب بنفسِه.**
 *   · `tools/baseline_xlsx_build.php:154` يطبع **٢٢** تحت عنوانٍ يُعدِّد
 *     **٢١ رمزًا** نصًّا — فيكذب بمقامِه صامتًا.
 *   ⇒ **مقامان فاسدان** ⇒ النقلُ واجبٌ بالقاعدةِ لا بالرأي.
 *
 * ◆ **وأشدُّ من ذلك مقيس**: أسطحُ `owner_code='PLATFORM'` في السجلِّ الرسميِّ
 *   **صفر** لا اثنتا عشرةَ كما في خطِّ الأساس. ⇒ **رمزٌ يُفسد مقامَ الإداراتِ
 *   وهو بلا سكّانٍ أصلًا** — فلا ثمنَ لنقلِه ولا حجّةَ لبقائه.
 *
 * ◆ **والنقلُ لا يمحو**: يُنشأ `repair01_platform_capabilities` بأعمدةِ سجلِّ
 *   الاستئنافِ نفسِها (`AMD-01` المرحلة ٦)، ويُودَع فيه الرمزُ بحكمِه وتاريخِه
 *   **قبل** أن يُحذف من جدولِ الإدارات. ⛔ **ولا حذفَ قبلَ إيداع.**
 *
 * ◆ **ولا مفتاحَ أجنبيَّ يمنع**: قِيس — صفرُ مفتاحٍ أجنبيٍّ يشير إلى
 *   `repair01_departments`. وقيمةُ `PLATFORM` باقيةٌ حيثُ تُستعمل علامةً
 *   (`repair01_w15_scope.backing_owner` ٦ · `repair01_w16_scorecard.domain_code` ٩)
 *   **ولا تتأثّر** — فالرمزُ يبقى مفردةً صالحةً وإن لم يكن إدارةً.
 *
 * التشغيل: php database/migrations/2027_12_20_rpr02_platform_register.php
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

/* ① السجلُّ المستقلُّ — بأعمدةِ سجلِّ الاستئنافِ نفسِها ─────────────────── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_platform_capabilities` (
  `capability_code` VARCHAR(32)  NOT NULL COMMENT 'رمز القدرة المنصية المشتركة',
  `name_ar`         VARCHAR(160) NOT NULL,
  `tech_owner`      VARCHAR(120) NOT NULL DEFAULT ''
                    COMMENT 'مالك تقني مسمى شخصا — RPR-02 §4-4 المعيار الثالث',
  `policy_owner`    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مالك السياسة',
  `last_closed`     VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'اخر متطلب مغلق بالدليل',
  `first_open`      VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'اول متطلب مفتوح',
  `blocker`         VARCHAR(190) NOT NULL DEFAULT '',
  `blocker_level`   VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'درجة من الست',
  `blocker_valid`   VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'هل الحاجز صحيح ولماذا',
  `resume_point`    VARCHAR(255) NOT NULL DEFAULT '',
  `next_action`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'فعل واحد محدد لا خطة',
  `moved_from`      VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'من اين نقل — ولا نقل بلا اثر',
  `moved_why`       VARCHAR(400) NOT NULL DEFAULT '',
  `snapshot_id`     VARCHAR(48)  NOT NULL DEFAULT '',
  `updated_at`      DATETIME     NULL,
  PRIMARY KEY (`capability_code`),
  CONSTRAINT `chk_pc_moved` CHECK (`moved_from` = '' OR `moved_why` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 §5-2 و AMD-01 المرحلة 6 — سجل القدرات المنصية المستقل · ولا تعد PLATFORM ادارة 22'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "  ✔ `repair01_platform_capabilities`\n";

/* ② الإيداعُ قبلَ الحذف — ⛔ ولا حذفَ قبلَ إيداع ────────────────────────── */
$r = $conn->query("SELECT canonical_code, name_ar, sector, note
                     FROM repair01_departments WHERE canonical_code = 'PLATFORM'");
if ($r && $r->num_rows) {
    $d = $r->fetch_assoc();
    $why = 'RPR-02 §5-2: عد المقامات فوجد اثنان فاسدان — repair01_w135_gate G1 يشترط '
         . '«خارج التسلسل = 4» ويقيس 5 فيرسب · و baseline_xlsx_build.php:154 يطبع 22 '
         . 'تحت عنوان يعدد 21 رمزا. واسطح owner_code=PLATFORM في السجل صفر.';
    $st = $conn->prepare("INSERT INTO `repair01_platform_capabilities`
        (capability_code, name_ar, moved_from, moved_why, updated_at)
        VALUES ('PLATFORM', ?, 'repair01_departments.canonical_code', ?, NOW())
        ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), moved_why = VALUES(moved_why),
                                updated_at = NOW()");
    $st->bind_param('ss', $d['name_ar'], $why);
    if (!$st->execute()) { exit("✘ تعذّر الإيداع: {$conn->error}\n"); }
    echo "  ✔ أُودع الرمزُ بحكمِه وسببِه **قبل** الحذف\n";

    /* ⛔ **ولا يُحذف إلّا بعد التحقّقِ من أنّه أُودع فعلًا** */
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_capabilities
                                 WHERE capability_code = 'PLATFORM'")->fetch_row()[0];
    if ($back !== 1) { exit("⛔ **لم يُودَع** — ولا حذفَ بلا إيداعٍ متحقَّقٍ منه\n"); }

    if (!$conn->query("DELETE FROM `repair01_departments` WHERE canonical_code = 'PLATFORM'")) {
        exit("✘ تعذّر الحذف: {$conn->error}\n");
    }
    echo "  ✔ حُذف من `repair01_departments` — بعدَ إيداعٍ متحقَّقٍ منه\n";
} else {
    echo "  ◆ الرمزُ منقولٌ سلفًا\n";
}

/* ③ الحصيلةُ بعدًّا ────────────────────────────────────────────────────── */
$all = (int) $conn->query("SELECT COUNT(*) FROM repair01_departments")->fetch_row()[0];
$dep = (int) $conn->query("SELECT COUNT(*) FROM repair01_departments
                            WHERE canonical_code LIKE 'DEP-%'")->fetch_row()[0];
$out = $all - $dep;
printf("\n  المقامُ الآن: الكلُّ **%d** · `DEP-%%` **%d** · خارجَ التسلسل **%d**\n", $all, $dep, $out);
echo $all === 21 && $dep === 17 && $out === 4
    ? "  🟢 **٢١ لا ٢٢** — والمقامُ صار كما ينصُّ الأمر\n"
    : "  ⛔ **المقامُ ليس ٢١/١٧/٤** — يُراجَع\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقل الرمزُ — ولم يُترك على حالِه بلا حكمٍ مسجَّل\n";
