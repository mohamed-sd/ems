<?php
/**
 * 2028_01_07_rpr02_platform_surface.php — ربطُ سطحِ المنصّةِ بقدرتِه أو نطاقِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` **#١٣** و`RPR-03` **#٨** (‏وهما سؤالٌ واحدٌ
 *   بقارئَين): **٣٠ سطحًا** حكمُ ملكيّتِها `PLATFORM_SHARED`، **وسجلُّ القدراتِ
 *   المنصّيّةِ فيه صفٌّ واحدٌ** هو الرمزُ نفسُه ⇒ *«لا واحدةَ منها مسجَّلةٌ
 *   بمعرِّفِها وقاعدةِ ظهورِها»*.
 *
 * ◆ **وما كان ناقصًا ليس البياناتِ بل الربط**: الثلاثون تحمل **كلُّها** قاعدةَ
 *   ظهورٍ وصنفَ ظهورٍ وسياسةَ صلاحيّةٍ وصنفَ حارسٍ ودورَ مالكٍ **مقيسةً**
 *   (٣٠/٣٠)، و**١٨** منها تحمل رمزَ مالكٍ أيضًا. ⇒ **فالموضعُ يربط ما هو مقيسٌ
 *   ولا يخترع بيانًا**.
 *
 * ◆ **وثلاثُ قواعدَ للربط**:
 *   **P1 · `DECLARED_SCOPE_OWNER`** — رمزُ المالكِ **نطاقٌ مُعلَنٌ** من الواحدِ
 *        والعشرين (`DEP-01..17` · `EX-CEO` · `EX-DVP` · `WS-MY` · `IAF`) ⇒
 *        السطحُ **ليس بلا مالك**، و`PLATFORM_SHARED` فيه **صفةُ ظهورٍ عابرٍ
 *        للأدوارِ لا فراغُ ملكيّة**. ⛔ **والخلطُ بينهما هو أصلُ العطب.**
 *   **P2 · `CAPABILITY_BOUND`** — بلا مالكٍ، ومسارُه أو وسمُه يطابق **قدرةً
 *        واحدةً لا غير** من قدراتِ `AMD-01` §٤·٧ الثمان.
 *   **P3 · `UNBOUND_DECLARED`** — بلا مالكٍ ولا قدرةٍ مميِّزة (‏أو قدرتان
 *        فأكثرُ) ⇒ **يُعلَن مفتوحًا بمرشَّحيه**، ولا يُلحق بأوّلِ مصادفة.
 *
 * ⛔ **والتسجيلُ ليس اعتمادًا** — و#١٣ يشترط تبريرًا **معتمَدًا**. فعمودُ
 *   `approval_state` يولد `AWAITING_OWNER` لكلِّ صفّ، **ولا تُقرأ الكتابةُ
 *   إغلاقًا**: من يملك وسمَ الاعتمادِ بلا مرجعٍ يملك تصفيرَ #١٣ بجملةٍ واحدة.
 *   ⇒ فقاعدةٌ صلبةٌ: **`APPROVED` يوجب مرجعَ قرارِ مالكٍ غيرَ فارغ**.
 *
 * التشغيل: php database/migrations/2028_01_07_rpr02_platform_surface.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_platform_surface` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `screen_id`        VARCHAR(12) NOT NULL COMMENT 'السطح — والمعرف هو الربط',
  `label_ar`         VARCHAR(190) NOT NULL DEFAULT '',
  `route`            VARCHAR(190) NOT NULL DEFAULT '',
  `bind_rule`        ENUM('P1_DECLARED_SCOPE_OWNER','P2_CAPABILITY_BOUND','P3_UNBOUND_DECLARED')
                     NOT NULL COMMENT 'قاعدة الربط — والمقياس يعلن ايتها',
  `scope_code`       VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'النطاق المعلن حين يكون المالك نطاقا',
  `capability_code`  VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'قدرة AMD-01 4-7 حين يربط بقدرة',
  `visibility_class` VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'صنف الظهور مقيسا',
  `visibility_rule`  VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'قاعدة الظهور مقيسة',
  `permission_policy` VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'سياسة الصلاحية مقيسة',
  `guard_kind`       VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'صنف الحارس مقيسا',
  `owner_role`       VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'دور المالك مقيسا',
  `approval_state`   ENUM('AWAITING_OWNER','APPROVED') NOT NULL DEFAULT 'AWAITING_OWNER'
                     COMMENT 'التسجيل ليس اعتمادا — و13 يشترط معتمدا',
  `owner_decision_ref` VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مرجع قرار المالك — لازم للاعتماد',
  `witness`          VARCHAR(600) NOT NULL COMMENT 'شاهد الربط — ولا صف بلا شاهد',
  `snapshot_id`      VARCHAR(48) NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_screen` (`screen_id`),
  KEY `ix_rule` (`bind_rule`),
  KEY `ix_state` (`approval_state`),
  CONSTRAINT `chk_ps_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_ps_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_ps_approved` CHECK (`approval_state` = 'AWAITING_OWNER'
                                   OR `owner_decision_ref` <> ''),
  CONSTRAINT `chk_ps_payload`  CHECK ((`bind_rule` = 'P1_DECLARED_SCOPE_OWNER' AND `scope_code` <> '')
                                   OR (`bind_rule` = 'P2_CAPABILITY_BOUND' AND `capability_code` <> '')
                                   OR (`bind_rule` = 'P3_UNBOUND_DECLARED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 12-13 · ربط سطح المنصة بقدرته او نطاقه — بقاعدة وشاهد لكل سطح'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_surface")->fetch_row()[0];
echo "  ✔ `repair01_platform_surface` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ أربعُ قواعدَ صلبة: شاهدٌ · لقطةٌ · **اعتمادٌ يوجب مرجعَ قرار** · وصنفٌ يُلزِم حمولتَه\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ الربطِ مفتوحٌ — و`repair01_platform_capabilities` لم يُمَسّ\n";
