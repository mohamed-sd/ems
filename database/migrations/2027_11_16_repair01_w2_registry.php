<?php
/**
 * 2027_11_16_repair01_w2_registry.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 · W02 — **السجلُّ المعياريُّ للشاشات** ودفترُ قراراتِ المرحلة.
 *
 * ◆ **لماذا جدولٌ جديدٌ لا أعمدةٌ في `repair01_surfaces`**: حبّةُ الأسطحِ
 *   **(شاشةٌ × دورةُ إدارة)** — ٦٦٤ صفًّا لـ٣٨٤ ملفًّا؛ فالشاشةُ الواحدةُ فيه
 *   أربعةُ صفوفٍ حين تظهر في أربعِ دورات. وحبّةُ السجلِّ المعياريِّ **الشاشةُ
 *   نفسُها**: مُعرِّفٌ واحدٌ ومسارٌ واحدٌ وحارسٌ واحد. وكتابةُ `screen_id` في
 *   دفترِ الأسطحِ وحدَه تجعل المُعرِّفَ يتكرَّر أربعًا ثم يُقاس «١٠٠٪» على مقامٍ
 *   ليس مقامَ الشاشات.
 *   ⇐ فالجدولان معًا: `repair01_surfaces.screen_id` **مرجعٌ** إلى السجلّ،
 *     والسجلُّ `repair01_screen_registry` هو الحبّةُ الحاكمة.
 *
 * ◆ **ولماذا مقامُ السجلِّ أوسعُ من ٣٨٤**: المقامُ اتحادُ ثلاثةِ مصادرَ لا
 *   مصدرٍ واحد — دفترُ الأسطحِ (٣٨٤ ملفًّا) · الشاشاتُ الحيّةُ على القرصِ
 *   (تحمل القشرة) · مساراتُ التنقّلِ الحيّةُ في `nav_items`. والاقتصارُ على
 *   الأولِ يترك `RP-01` (٢٥٤ شاشةً بلا صفٍّ) خارجَ السجلِّ الذي يُفترض أن
 *   يحكمَها — وهو عينُ الدَّينِ الذي تقيسه السقّاطة.
 *
 * ◆ **والترتيبُ في الأعمدةِ هو ترتيبُ §٣٥**: مُعرِّفٌ ← مسارٌ ← مالكٌ ← موضعٌ
 *   من الدورةِ ← أبٌ/تبويبٌ ← صنفُ ظهور. كلُّ عمودٍ يحمل قاعدتَه (`*_rule`)
 *   — ولا قيمةَ بلا مصدرٍ يعود إليها (‏_CONTEXT §المصدرُ الواحد).
 *
 * ◆ **و`repair01_w2_decisions` جدولٌ جديدٌ** للسببِ الذي أنشأ `repair01_w1_decisions`:
 *   `repair01_decisions` مُجمَّدٌ عند ١٠٨ والبوّابةُ `G0-02` تُثبّت مقامَه.
 *
 * التشغيل: php database/migrations/2027_11_16_repair01_w2_registry.php
 *          (⛔ لا `migrate.php up` — الهجرةُ بمسارِها الكاملِ منفردةً)
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function w2_col_exists(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w2_table_exists(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

$done = 0; $had = 0; $err = 0;

/* ═══ ① السجلُّ المعياريُّ للشاشات — الحبّةُ: شاشةٌ واحدة ═══════════════════ */
$ddl = "
CREATE TABLE IF NOT EXISTS `repair01_screen_registry` (
  `screen_id`        VARCHAR(12)  NOT NULL COMMENT 'المعرف المعياري SCR-nnnn — ثابت لا يعاد ترقيمه',
  `screen_file`      VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'اسم الملف — مفتاح سجل الدورة الحي',
  `route`            VARCHAR(200) NULL DEFAULT NULL COMMENT 'المسار المعياري Dir/file.php — NULL لما لم يُبنَ (والفراغ لا يصلح: uq_route يمنع تكراره)',
  `route_rule`       VARCHAR(48)  NOT NULL DEFAULT '',
  `owner_code`       VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الرمز المعياري للادارة المالكة',
  `owner_role`       VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'الدور المسؤول عن الشاشة',
  `owner_rule`       VARCHAR(48)  NOT NULL DEFAULT '',
  `lifecycle`        ENUM('LIVE_REGISTERED','LIVE_UNREGISTERED','GHOST_TARGET','GHOST_RETIRED') NOT NULL DEFAULT 'LIVE_UNREGISTERED',
  `lifecycle_rule`   VARCHAR(48)  NOT NULL DEFAULT '',
  `parent_screen_id` VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'الاب حين تكون تبويبا فيه',
  `parent_rule`      VARCHAR(48)  NOT NULL DEFAULT '',
  `visibility_class` ENUM('MENU_ITEM','TAB_CHILD','DIRECT_ONLY','ANCHOR','NOT_BUILT') NOT NULL DEFAULT 'DIRECT_ONLY',
  `visibility_rule`  VARCHAR(48)  NOT NULL DEFAULT '',
  `on_disk`          TINYINT(1)   NOT NULL DEFAULT 0,
  `origin`           VARCHAR(24)  NOT NULL DEFAULT '' COMMENT 'SURFACES / DISK / NAV',
  `ghost_verdict`    VARCHAR(32)  NOT NULL DEFAULT '',
  `ghost_why`        VARCHAR(400) NOT NULL DEFAULT '',
  `guard_kind`       VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'SELF_EARLY / SHARED_SHELL / SHELL / NONE',
  `guard_evidence`   VARCHAR(255) NOT NULL DEFAULT '',
  `w2_why`           VARCHAR(400) NOT NULL DEFAULT '',
  `src_ref`          VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at`       DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`screen_id`),
  UNIQUE KEY `uq_route` (`route`),
  KEY `k_file` (`screen_file`),
  KEY `k_life` (`lifecycle`),
  KEY `k_vis`  (`visibility_class`),
  KEY `k_guard`(`guard_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='REPAIR01 W02 — canonical screen registry: one screen per row'";
$was = w2_table_exists($conn, 'repair01_screen_registry');
if ($conn->query($ddl) === false) { $err++; echo "✘ repair01_screen_registry : {$conn->error}\n"; }
elseif ($was) { $had++; echo "= repair01_screen_registry (قائم)\n"; }
else { $done++; echo "✔ repair01_screen_registry\n"; }

/* ═══ ② مرجعُ دفترِ الأسطحِ إلى السجلّ ═════════════════════════════════════
   عمودٌ **مرجعيٌّ** لا مُعرِّفٌ ثانٍ: ٦٦٤ صفًّا تشير إلى ٣٨٤ مُعرِّفًا. */
if (w2_col_exists($conn, 'repair01_surfaces', 'screen_id')) { $had++; echo "= repair01_surfaces.screen_id (قائم)\n"; }
elseif ($conn->query("ALTER TABLE `repair01_surfaces` ADD COLUMN `screen_id` VARCHAR(12) NOT NULL DEFAULT '', ADD KEY `k_scr` (`screen_id`)") === false) {
    $err++; echo "✘ repair01_surfaces.screen_id : {$conn->error}\n";
} else { $done++; echo "✔ repair01_surfaces.screen_id\n"; }

/* ═══ ③ وسمُ الشبحِ المنقولِ في دفترِ الفجوات ═══════════════════════════════
   الشبحُ المنقولُ يجب أن يُميَّز عمّا كان في الدفترِ سلفًا — وإلا امتزج
   ١٧٤ سطحًا مستهدَفًا أصليًّا بما نُقل اليومَ ولا يُعرف أيُّهما أيّ. */
$gaps = array(
    'origin_stage' => "VARCHAR(8) NOT NULL DEFAULT ''",
    'origin_note'  => "VARCHAR(255) NOT NULL DEFAULT ''",
    'wave_stage'   => "VARCHAR(8) NOT NULL DEFAULT ''",
);
foreach ($gaps as $c => $type) {
    if (w2_col_exists($conn, 'repair01_target_gaps', $c)) { $had++; echo "= repair01_target_gaps.$c (قائم)\n"; continue; }
    if ($conn->query("ALTER TABLE `repair01_target_gaps` ADD COLUMN `$c` $type") === false) {
        $err++; echo "✘ repair01_target_gaps.$c : {$conn->error}\n";
    } else { $done++; echo "✔ repair01_target_gaps.$c\n"; }
}

/* ═══ ④ دفترُ قراراتِ المرحلة ═══════════════════════════════════════════════ */
$ddl2 = "
CREATE TABLE IF NOT EXISTS `repair01_w2_decisions` (
  `decision_id` VARCHAR(32) NOT NULL,
  `stage`       VARCHAR(8)  NOT NULL DEFAULT 'W02',
  `topic`       VARCHAR(160) NOT NULL DEFAULT '',
  `question`    VARCHAR(400) NOT NULL DEFAULT '',
  `ruling`      VARCHAR(400) NOT NULL DEFAULT '',
  `rationale`   TEXT NULL,
  `evidence`    VARCHAR(400) NOT NULL DEFAULT '',
  `scope_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      ENUM('RECORDED_PENDING_OWNER','OWNER_APPROVED') NOT NULL DEFAULT 'RECORDED_PENDING_OWNER',
  `decided_at`  DATETIME NULL,
  PRIMARY KEY (`decision_id`), KEY `k_stage` (`stage`), KEY `k_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$was2 = w2_table_exists($conn, 'repair01_w2_decisions');
if ($conn->query($ddl2) === false) { $err++; echo "✘ repair01_w2_decisions : {$conn->error}\n"; }
elseif ($was2) { $had++; echo "= repair01_w2_decisions (قائم)\n"; }
else { $done++; echo "✔ repair01_w2_decisions\n"; }

echo "\nأُضيف: $done  ·  قائمٌ سلفًا: $had  ·  أخطاء: $err\n";
exit($err === 0 ? 0 : 1);
