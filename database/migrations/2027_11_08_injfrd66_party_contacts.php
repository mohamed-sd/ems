<?php
/**
 * 2027_11_08_injfrd66_party_contacts.php
 *   SAL-02 · SUP-02 — جهاتُ الاتصالِ والمفوَّضون: بيتٌ واحدٌ لطرفَي التعاقد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **جدولٌ واحدٌ لا جدولان** — وهذا حكمُ الحزمةِ نفسِها في `SUP-29`: «قوائمُ
 *   موحَّدةٌ بأسمائها نفسِها في الإدارتين — **بيتٌ واحدٌ لا نسختان**». وجهةُ
 *   الاتصالِ في المبيعاتِ وجهتُها في الموردين **حقلٌ بحقل**، وجدولانِ لهما
 *   يفترقانِ بأوَّلِ تعديلٍ كما افترقت العملة («دولار» مقابلَ `USD`).
 *
 * ◆ **والتفويضُ حجّيةٌ لا خانةُ تأشير**: نصُّ `SAL-02` «بحقلِ **صفةِ** المفوَّضِ
 *   بالتوقيع **ومداه**»، ونصُّ `SUP-02` «التفويضُ **حقلُ حجيةٍ بمداه**».
 *   ⇐ فمن يُوسَم موقِّعًا **يلزمه مدًى ومستندٌ مرجعيّ** — بقيدٍ في القاعدةِ لا
 *     بيقظةِ شاشة. **وتوقيعٌ بلا مدًى تفويضٌ مفتوحٌ، وهو ما تمنعه الوثيقة.**
 *
 * ◆ **ولا بندَ تنقّلٍ لها**: «تبويبٌ في ملفِّ العميل **لا شاشة**» — والمعيارُ
 *   حرفيًّا «لا بندَ تنقّلٍ لجهات الاتصال». فتُبنى الشاشتانِ **تبويبَين
 *   مبلوغَين من ملفَّيهما**، وصفرُ صفٍّ في `nav_items`.
 *
 * ◆ **ولا `AUTO_INCREMENT` في `CHECK`** ولا `DEFINER` في أيِّ كائنٍ مُصدَّر.
 *
 * التشغيل:  php database/migrations/2027_11_08_injfrd66_party_contacts.php
 * الرجوع :  php database/migrations/2027_11_08_injfrd66_party_contacts.php --revert
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

$REVERT = in_array('--revert', $argv, true);
$T = 'party_contacts';

if ($REVERT) {
    echo "\n══ الرجوع — SAL-02 · SUP-02 ══\n\n";
    $r = $conn->query("SELECT COUNT(*) FROM `{$T}`");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) {
        echo "  ⛔ الجدولُ يحمل {$n} صفًّا — **لا يُسقَط جدولٌ فيه بياناتُ ناس**.\n";
        echo "     أفرغه بقرارٍ مكتوبٍ أوَّلًا إن كان ذلك المراد.\n\n";
        exit(1);
    }
    $conn->query("DROP TABLE IF EXISTS `{$T}`");
    echo "  ✔ أُسقط `{$T}` (وكان فارغًا)\n\n";
    exit(0);
}

echo "\n══ INJ-FRD-01 · SAL-02 · SUP-02 — جهاتُ الاتصالِ والمفوَّضون ══\n\n";

$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$T}'");
if ($r && (int) $r->fetch_row()[0] > 0) {
    echo "  ○ `{$T}` قائمٌ سلفًا — لا شيءَ يُفعَل\n\n";
    exit(0);
}

/* ── الجدول ─────────────────────────────────────────────────────────────
   ◆ **`party_type` + `party_ref` لا مفتاحانِ منفصلان**: هو النمطُ نفسُه الذي
     تستعمله `settlements` في هذا النظام — طرفٌ واحدٌ بنوعِه ومرجعِه. ومفتاحانِ
     (`client_id`/`supplier_id`) أحدُهما فارغٌ أبدًا يفتح بابَ صفٍّ بلا طرفٍ
     وصفٍّ بطرفَين. */
$sql = "CREATE TABLE `{$T}` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`        INT UNSIGNED NOT NULL,
  `party_type`        ENUM('client','supplier') NOT NULL,
  `party_ref`         INT UNSIGNED NOT NULL,
  `contact_name`      VARCHAR(190) NOT NULL,
  `job_title`         VARCHAR(120) NULL,
  `phone`             VARCHAR(40)  NULL,
  `phone_alt`         VARCHAR(40)  NULL,
  `email`             VARCHAR(190) NULL,
  `is_primary`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_signatory`      TINYINT(1) NOT NULL DEFAULT 0,
  `authority_kind`    ENUM('تفويضٌ عام','تفويضٌ خاص','سلطةٌ أصلية','—') NOT NULL DEFAULT '—',
  `authority_scope`   VARCHAR(300) NULL,
  `authority_doc_ref` VARCHAR(120) NULL,
  `authority_from`    DATE NULL,
  `authority_to`      DATE NULL,
  `state`             ENUM('نشط','منتهٍ','ملغى') NOT NULL DEFAULT 'نشط',
  `note`              VARCHAR(300) NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`        TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`        DATETIME NULL,
  `deleted_by`        INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pc_party_name` (`company_id`,`party_type`,`party_ref`,`contact_name`),
  KEY `ix_pc_party` (`company_id`,`party_type`,`party_ref`,`is_deleted`),
  KEY `ix_pc_signatory` (`company_id`,`is_signatory`,`state`),
  /* ◆ **التفويضُ حجّيةٌ بمداه**: من يُوسَم موقِّعًا يلزمه صفةٌ ومدًى ومستند —
       وتوقيعٌ بلا مدًى **تفويضٌ مفتوح**، وهو ما تمنعه الوثيقةُ نصًّا. */
  CONSTRAINT `chk_pc_authority` CHECK (
      `is_signatory` = 0
      OR ( `authority_kind` <> '—'
       AND `authority_scope` IS NOT NULL AND CHAR_LENGTH(`authority_scope`) >= 3
       AND `authority_doc_ref` IS NOT NULL AND CHAR_LENGTH(`authority_doc_ref`) >= 2 )
  ),
  /* ونافذةُ التفويضِ لا تنقلب: نهايةٌ قبلَ بدايةٍ مهلةٌ سالبة */
  CONSTRAINT `chk_pc_window` CHECK (
      `authority_from` IS NULL OR `authority_to` IS NULL OR `authority_to` >= `authority_from`
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sql)) { exit("  ✘ تعذّر الإنشاء: {$conn->error}\n\n"); }
echo "  ✔ أُنشئ `{$T}`\n";

/* ── حُرّاسُ ما بعدَ البناء ─────────────────────────────────────────────── */
echo "\n  ── حُرّاسُ ما بعدَ البناء\n";
$halt = 0;

$r = $conn->query("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$T}'");
$meta = $r ? $r->fetch_assoc() : array();
/* ◆ دمبٌ بلا `ENGINE=` يُنشئ MyISAM على مضيفٍ افتراضُه كذلك — فيُقاس لا يُفترَض */
if (($meta['ENGINE'] ?? '') !== 'InnoDB') { $halt++; printf("     ✘ المحرّك «%s» لا InnoDB\n", $meta['ENGINE'] ?? '—'); }
else { echo "     ✔ InnoDB\n"; }
if (($meta['TABLE_COLLATION'] ?? '') !== 'utf8mb4_unicode_ci') {
    $halt++; printf("     ✘ الترتيب «%s» لا utf8mb4_unicode_ci\n", $meta['TABLE_COLLATION'] ?? '—');
} else { echo "     ✔ utf8mb4_unicode_ci\n"; }

/* ◆ **والقيدُ يُجَسُّ لا يُصدَّق**: `CHECK` مكتوبٌ في الجملةِ قد يُهمَل صامتًا
     على محرّكٍ لا ينفّذه. فتُجرَّب المخالفةُ ويُنتظَر رفضُها. */
$conn->query("INSERT INTO `{$T}` (company_id, party_type, party_ref, contact_name, is_signatory)
              VALUES (0, 'client', 0, 'مسبارُ الحجّية', 1)");
$probe = ($conn->errno !== 0);
if (!$probe) {
    $halt++;
    echo "     ✘ قُبل موقِّعٌ بلا مدًى ولا مستند — القيدُ مكتوبٌ ولا يُنفَّذ\n";
    $conn->query("DELETE FROM `{$T}` WHERE contact_name = 'مسبارُ الحجّية'");
} else {
    echo "     ✔ موقِّعٌ بلا مدًى ولا مستندٍ **مرفوضٌ في القاعدة**\n";
}
$conn->query("INSERT INTO `{$T}` (company_id, party_type, party_ref, contact_name,
                                  is_signatory, authority_kind, authority_scope, authority_doc_ref,
                                  authority_from, authority_to)
              VALUES (0, 'client', 0, 'مسبارُ النافذة', 1, 'تفويضٌ خاص', 'مدًى', 'DOC-1',
                      '2027-01-01', '2026-01-01')");
if ($conn->errno === 0) {
    $halt++;
    echo "     ✘ قُبلت نافذةُ تفويضٍ نهايتُها قبلَ بدايتِها\n";
    $conn->query("DELETE FROM `{$T}` WHERE contact_name = 'مسبارُ النافذة'");
} else {
    echo "     ✔ ونافذةٌ نهايتُها قبلَ بدايتِها **مرفوضة**\n";
}
$r = $conn->query("SELECT COUNT(*) FROM `{$T}`");
$left = $r ? (int) $r->fetch_row()[0] : -1;
if ($left !== 0) { $halt++; printf("     ✘ بقي %d صفَّ مسبارٍ في الجدول\n", $left); }
else { echo "     ✔ وصفرُ صفِّ مسبارٍ باقٍ — الجدولُ يُسلَّم فارغًا\n"; }

if ($halt > 0) {
    echo "\n  ⛔ توقَّفت الهجرةُ عند {$halt} حارسًا — وأُسقط الجدول.\n\n";
    $conn->query("DROP TABLE IF EXISTS `{$T}`");
    exit(1);
}

$r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$T}'");
printf("\n  ○ %s عمودًا · بيتٌ واحدٌ لطرفَي التعاقد\n", $r ? $r->fetch_row()[0] : '؟');
echo "\n  ◆ ولا بندَ تنقّلٍ يُنشأ: «تبويبٌ في الملفِّ لا شاشة» نصُّ المتطلبَين.\n\n";
exit(0);
