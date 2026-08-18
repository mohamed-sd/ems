<?php
/**
 * 2027_05_30_lad01_ladders_breakglass_caps.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تكليفُ المالك — البنودُ ① و② و③ و⑦
 * ───────────────────────────────────────────────────────────────────────────
 * ① سلاليمُ LD-01…LD-07 بالدورةِ المعتمدةِ حرفًا — **بعدَ حذفِ المحاسبين**
 *   من المراحلِ التشغيليةِ قبلَ بوابةِ المالية. و`LAD-01` كما وردت تضع
 *   «محاسبَ الموقع» مراجعًا **ومعتمِدًا مشاركًا** في LD-01، و«محاسبَ الموردين»
 *   مراجعًا في LD-03 — وكلاهما يسقط بنصِّ التكليف.
 *
 * ② Break Glass: طالبُ الاستثناءِ لا يعتمده — ولو كان الرئيسَ التنفيذيَّ نفسَه.
 *   فتُعرَّف **جهةُ اعتمادٍ بديلةٌ مستقلة** ويمنع القيدُ الاعتمادَ الذاتيَّ
 *   بنيويًّا لا بالتذكير.
 *
 * ③ السقوفُ التسعة: `cap_state` ثلاثيّ، و**غيرُ المحسومِ يوقف السلّمَ**
 *   (fail-closed) — لا يمرُّ بسقفٍ مفتوحٍ ولا بصفرٍ صامت.
 *
 * ⑦ قاعدةُ التعارض: الأنواعُ الموروثةُ الأربعةُ في `chk_job_type`
 *   (`payroll_bind`·`periodic_cron`·`bank_recon`·`batch_loop`) **صفرُ صفٍّ لكلٍّ**
 *   — قيمٌ ميتةٌ لا كائناتٌ قائمة. فتُزال ولا تُثبَّت، وتبقى ثمانيةُ TS-01 وحدَها.
 *
 * ◆ ولا يُحذف صفٌّ بيانات: الإزالةُ هنا من **قائمةِ قيدٍ** لا من جدولِ وقائع.
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
$run = function (string $s, string $l) use ($conn): bool {
    if ($conn->query($s)) { echo "   ✔ $l\n"; return true; }
    echo "   ✗ $l — " . $conn->error . "\n"; return false;
};
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "\n═══ تكليفُ المالك · البنود ① ② ③ ⑦ ═══\n";

// ═══════════════════════════════════════════════════════════════════════════
// ① سجلُّ السلاليم — بالاسمين اللذين سمّتهما GOV-24 (gov_ladders / _steps)
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ① سجلُّ سلاليمِ الاعتماد\n";

$run("CREATE TABLE IF NOT EXISTS `gov_ladders` (
        `ladder_code` VARCHAR(12) NOT NULL COMMENT 'LD-01 … LD-09',
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = كلُّ كيانٍ نشط',
        `slug` VARCHAR(48) NOT NULL COMMENT 'unit_daily_approve …',
        `name_ar` VARCHAR(160) NOT NULL,
        `cycle_no` TINYINT UNSIGNED NULL COMMENT 'موضعُه في الدورةِ التسعية — NULL لغيرِ التايم شيت',
        `escalate_after_hours` SMALLINT UNSIGNED NULL COMMENT 'تجاوزُها تصعيدٌ لا إغلاق',
        `cap_kind` ENUM('none','scope','amount') NOT NULL DEFAULT 'none',
        `cap_amount` DECIMAL(16,2) NULL,
        `cap_currency` VARCHAR(8) NULL,
        `cap_state` ENUM('resolved','unresolved','not_applicable') NOT NULL DEFAULT 'unresolved'
            COMMENT '③ غيرُ المحسومِ يوقف السلّمَ — fail-closed',
        `payload_note` VARCHAR(255) NULL COMMENT 'ما يكتبه المحرّكُ بعدَ الاكتمال',
        `doc_ref` VARCHAR(40) NOT NULL DEFAULT 'LAD-01',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ladder_code`),
        KEY `ix_ld_cycle` (`cycle_no`),
        -- ③ سقفٌ **محسومٌ** يلزمه رقمٌ وعملة. أما غيرُ المحسومِ فحالةٌ مشروعةٌ
        -- ومقصودة: هي بعينُها الـfail-closed الذي يوقف السلّمَ حتى يُعتمد الرقم.
        -- (القيدُ الأولُ اشترط resolved على كلِّ سقفِ مبلغٍ فناقضَ البندَ ③ نفسَه.)
        CONSTRAINT `chk_ld_cap` CHECK (
            `cap_state` <> 'resolved'
            OR `cap_kind` <> 'amount'
            OR (`cap_amount` IS NOT NULL AND `cap_currency` IS NOT NULL)
        )
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='LAD-01: سلاليمُ الاعتماد — والسقفُ غيرُ المحسومِ يوقف السلّم'", 'gov_ladders');

$run("CREATE TABLE IF NOT EXISTS `gov_ladder_steps` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عمودُ العزل TS-02 — 0 = كلُّ كيانٍ نشط (يرثه من سلّمِه)',
        `ladder_code` VARCHAR(12) NOT NULL,
        `step_no` TINYINT UNSIGNED NOT NULL,
        `actor_code` VARCHAR(40) NOT NULL COMMENT 'رمزُ الفاعلِ الوظيفيّ',
        `actor_name_ar` VARCHAR(120) NOT NULL,
        `step_kind` ENUM('entry','review','approve','system') NOT NULL,
        `is_accountant` TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '① محاسبٌ — ممنوعٌ في المراحلِ التشغيليةِ قبلَ بوابةِ المالية',
        `is_finance_gate` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هذه الخطوةُ بوابةُ المالية أو بعدَها',
        `may_approve` TINYINT(1) NOT NULL DEFAULT 0,
        `forbid_note` VARCHAR(255) NULL COMMENT 'قيدُ منعٍ صريحٌ على هذه الخطوة',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ld_step` (`ladder_code`,`step_no`),
        KEY `ix_ld` (`ladder_code`),
        KEY `ix_company` (`company_id`),
        CONSTRAINT `fk_ld_step` FOREIGN KEY (`ladder_code`) REFERENCES `gov_ladders`(`ladder_code`) ON DELETE CASCADE,
        CONSTRAINT `chk_step_entry_no_approve` CHECK (`step_kind` <> 'entry' OR `may_approve` = 0),
        CONSTRAINT `chk_step_accountant` CHECK (`is_accountant` = 0 OR `is_finance_gate` = 1)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='① لا محاسبَ في مرحلةٍ تشغيليةٍ قبلَ بوابةِ المالية — يفرضه chk_step_accountant'", 'gov_ladder_steps');

// ── الدورةُ المعتمدةُ التسعيةُ موزَّعةً على السلاليمِ السبعة ─────────────────
$LADDERS = array(
    // code    slug                    name_ar                              cycle  escalate cap_kind  payload
    array('LD-01','unit_daily_approve',  'اعتمادُ التايم شيتِ اليوميّ',      2, 48, 'scope',  'حالةُ الوحدةِ «معتمدةٌ يوميًّا»'),
    array('LD-02','unit_client_match',   'مطابقةُ العميلِ على الوحدات',      3, 72, 'none',   'حالةُ المطابقةِ + فتحُ اعتمادِ الموردين'),
    array('LD-03','unit_supplier_approve','اعتمادُ وحداتِ الموردين',          5, 48, 'scope',  'حصةُ الموردِ مستهلَكةٌ باللقطة'),
    array('LD-04','unit_workforce_approve','اعتمادُ وحداتِ المشغّلين',        6, 48, 'scope',  'استحقاقُ المشغّلِ مفتوح'),
    array('LD-05','unit_fin_prelim',     'الاعتمادُ الماليُّ الأوليّ',        7, 24, 'amount', 'الواقعةُ مؤهَّلةٌ للفوترة'),
    array('LD-06','unit_client_invoice', 'إصدارُ المستخلصِ والفاتورة',        8, 72, 'amount', 'مستخلصٌ وفاتورةٌ صادرة'),
    array('LD-07','unit_fin_final',      'الاعتمادُ الماليُّ النهائيّ',       9, 24, 'amount', 'القيدُ مُرحَّلٌ والإيرادُ معترَفٌ به'),
);

$li = $conn->prepare(
    "INSERT INTO `gov_ladders`
        (`ladder_code`,`company_id`,`slug`,`name_ar`,`cycle_no`,`escalate_after_hours`,
         `cap_kind`,`cap_state`,`payload_note`,`doc_ref`,`is_active`)
     VALUES (?,0,?,?,?,?,?, CASE WHEN ?='amount' THEN 'unresolved' ELSE 'not_applicable' END, ?, 'LAD-01', 1)
     ON DUPLICATE KEY UPDATE `slug`=VALUES(`slug`), `name_ar`=VALUES(`name_ar`),
        `cycle_no`=VALUES(`cycle_no`), `escalate_after_hours`=VALUES(`escalate_after_hours`),
        `cap_kind`=VALUES(`cap_kind`), `payload_note`=VALUES(`payload_note`), `is_active`=1"
);
foreach ($LADDERS as $L) {
    list($code, $slug, $name, $cyc, $esc, $cap, $pay) = $L;
    $li->bind_param('sssiisss', $code, $slug, $name, $cyc, $esc, $cap, $cap, $pay);
    $li->execute();
}
$li->close();
echo "   ✔ " . count($LADDERS) . " سلالمَ مسجَّلةً بالدورةِ التسعية\n";

// ── الخطواتُ — ولا محاسبَ قبلَ بوابةِ المالية ────────────────────────────────
// step_kind: entry=مدخل · review=مراجع · approve=معتمد · system=آليّ
$STEPS = array(
    // LD-01 · «مدخل الوحدات → مدير الموقع» — ◆ محاسبُ الموقعِ محذوفٌ بنصِّ التكليف
    array('LD-01',1,'unit_entry_clerk','مدخل الوحدات','entry',0,0,0,'◆ مدخلُ الوحداتِ ممنوعٌ من الاعتماد'),
    array('LD-01',2,'site_manager','مدير الموقع','approve',0,0,1,'يعتمد الواقعةَ اليوميةَ — ولا يعتمد ما أدخله بنفسِه'),
    // LD-02 · مطابقةُ العميل ثم قرارُ مدير المبيعات
    array('LD-02',1,'client_supervisor','مشرف العميل المسجَّل في العقد','review',0,0,0,null),
    array('LD-02',2,'sales_manager','مدير المبيعات','approve',0,0,1,'◆ ممنوعٌ من تجاوزِ رفضٍ صريحٍ من العميل'),
    // LD-03 · ◆ محاسبُ الموردين محذوف
    array('LD-03',1,'containers_engine','محرّك الحاويات','system',0,0,0,null),
    array('LD-03',2,'suppliers_officer','مسؤول الموردين','approve',0,0,1,null),
    // LD-04 · ◆ محاسبُ الرواتب محذوف
    array('LD-04',1,'workforce_officer','مسؤول القوى التشغيلية','approve',0,0,1,null),
    // LD-05 · بوابةُ المالية — وهنا يجوز المحاسب
    array('LD-05',1,'finance_accountant','محاسب المالية','review',1,1,0,null),
    array('LD-05',2,'finance_manager','مدير الإدارة المالية','approve',0,1,1,null),
    // LD-06 · الفاتورةُ وقبولُ العميل
    array('LD-06',1,'finance_accountant','محاسب المالية','review',1,1,0,null),
    array('LD-06',2,'client_acceptance','قبول العميل','approve',0,1,1,'لا فاتورةَ نافذةٌ بلا قبولٍ مسجَّل'),
    // LD-07 · الاعتمادُ الماليُّ النهائي
    array('LD-07',1,'finance_manager','مدير الإدارة المالية','review',0,1,0,null),
    array('LD-07',2,'cfo','المدير المالي','approve',0,1,1,'◆ لا يعتمد من أعدَّ القيدَ نفسَه'),
);
$conn->query("DELETE FROM `gov_ladder_steps` WHERE `ladder_code` LIKE 'LD-0%'");
$si = $conn->prepare(
    "INSERT INTO `gov_ladder_steps`
        (`company_id`,`ladder_code`,`step_no`,`actor_code`,`actor_name_ar`,`step_kind`,
         `is_accountant`,`is_finance_gate`,`may_approve`,`forbid_note`)
     VALUES (0,?,?,?,?,?,?,?,?,?)"
);
$sn = 0;
foreach ($STEPS as $S) {
    list($c, $n, $ac, $an, $k, $isAcc, $isFin, $may, $note) = $S;
    $si->bind_param('sisssiiis', $c, $n, $ac, $an, $k, $isAcc, $isFin, $may, $note);
    if ($si->execute()) { $sn++; } else { echo "   ✗ $c/$n — " . $si->error . "\n"; }
}
$si->close();
echo "   ✔ $sn خطوةً — وقيدُ chk_step_accountant يرفض محاسبًا قبلَ بوابةِ المالية\n";

$acc = (int) $one("SELECT COUNT(*) FROM `gov_ladder_steps` WHERE `is_accountant`=1 AND `is_finance_gate`=0");
echo "   · محاسبونَ في مراحلَ تشغيلية: $acc   [المتوقَّع 0]\n";

// ═══════════════════════════════════════════════════════════════════════════
// ② Break Glass — جهةُ اعتمادٍ بديلةٌ مستقلة، ولا اعتمادَ ذاتيّ
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ② Break Glass — طالبُ الاستثناءِ لا يعتمده ولو كان الرئيسَ التنفيذيّ\n";
foreach (array(
    array('requester_role_id',  "SMALLINT UNSIGNED NULL COMMENT 'دورُ الطالب — بنيويٌّ لا نصّ'"),
    array('approver_role_id',   "SMALLINT UNSIGNED NULL COMMENT 'دورُ المعتمِدِ الأول'"),
    array('approver2_role_id',  "SMALLINT UNSIGNED NULL COMMENT 'دورُ المعتمِدِ الثاني — يدان لا يد'"),
    array('alternate_authority',"VARCHAR(120) NULL COMMENT '② الجهةُ البديلةُ حين يكون الطالبُ هو المعتمِدَ المعتاد'"),
    array('alternate_reason',   "VARCHAR(255) NULL COMMENT 'سببُ اللجوءِ إلى البديل'"),
) as $c) {
    if (!$hasCol('scr_break_glass', $c[0])) {
        $run("ALTER TABLE `scr_break_glass` ADD COLUMN `{$c[0]}` {$c[1]}", "scr_break_glass.{$c[0]}");
    } else { echo "   · {$c[0]} موجودٌ سلفًا\n"; }
}

// ◆ القيدُ البنيويّ: لا يعتمد الطالبُ طلبَ نفسِه — ومتى تساوى الدوران وجب بديلٌ معلَن
$hasChk = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='scr_break_glass'
                         AND CONSTRAINT_NAME='chk_bg_no_self_approval'");
if ($hasChk === 0) {
    $run("ALTER TABLE `scr_break_glass` ADD CONSTRAINT `chk_bg_no_self_approval` CHECK (
            `requester_role_id` IS NULL
            OR (
                (`approver_role_id`  IS NULL OR `approver_role_id`  <> `requester_role_id`)
                AND (`approver2_role_id` IS NULL OR `approver2_role_id` <> `requester_role_id`)
            )
            OR (`alternate_authority` IS NOT NULL AND `alternate_reason` IS NOT NULL)
          )", 'scr_break_glass · CHECK chk_bg_no_self_approval');
}
// ◆ ويدان لا يد: معتمدانِ مختلفان متى وُجدا
$hasChk2 = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='scr_break_glass'
                          AND CONSTRAINT_NAME='chk_bg_two_hands'");
if ($hasChk2 === 0) {
    $run("ALTER TABLE `scr_break_glass` ADD CONSTRAINT `chk_bg_two_hands` CHECK (
            `approver_role_id` IS NULL OR `approver2_role_id` IS NULL
            OR `approver_role_id` <> `approver2_role_id`
          )", 'scr_break_glass · CHECK chk_bg_two_hands');
}

// جهةُ الاعتمادِ البديلةُ المعلَنةُ حين يكون الطالبُ الرئيسَ التنفيذيّ (الدور 9)
$run("CREATE TABLE IF NOT EXISTS `gov_alternate_authority` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `when_requester_role_id` SMALLINT UNSIGNED NOT NULL COMMENT 'الدورُ الذي يتعذّر اعتمادُه لنفسِه',
        `alternate_kind` ENUM('role','committee','board') NOT NULL DEFAULT 'committee',
        `alternate_role_id` SMALLINT UNSIGNED NULL,
        `alternate_label` VARCHAR(120) NOT NULL,
        `quorum` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'يدان لا يد',
        `doc_ref` VARCHAR(40) NOT NULL DEFAULT 'LAD-01',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_alt` (`company_id`,`when_requester_role_id`),
        CONSTRAINT `chk_alt_independent` CHECK (
            `alternate_role_id` IS NULL OR `alternate_role_id` <> `when_requester_role_id`
        ),
        CONSTRAINT `chk_alt_quorum` CHECK (`quorum` >= 2)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='② جهةُ اعتمادٍ بديلةٌ مستقلة — فلا يكون الرئيسُ معتمِدًا وحيدًا وممنوعًا معًا'",
    'gov_alternate_authority');

$conn->query(
    "INSERT INTO `gov_alternate_authority`
        (`company_id`,`when_requester_role_id`,`alternate_kind`,`alternate_role_id`,`alternate_label`,`quorum`)
     VALUES
        (0, 9,  'committee', NULL, 'لجنةُ الحوكمةِ والتدقيقِ — عضوان مستقلّان', 2),
        (0, 32, 'role',      20,   'المراجعُ والمدققُ المالي',                  2),
        (0, 15, 'committee', NULL, 'لجنةُ الحوكمةِ والتدقيق',                   2)
     ON DUPLICATE KEY UPDATE `alternate_label`=VALUES(`alternate_label`), `is_active`=1"
);
echo "   ✔ جهاتٌ بديلةٌ معلَنة: " . (int) $one("SELECT COUNT(*) FROM `gov_alternate_authority` WHERE `is_active`=1") . "\n";

// ═══════════════════════════════════════════════════════════════════════════
// ③ السقوفُ التسعة — fail-closed حتى تُعتمد رسميًّا
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ③ السقوفُ — لا معاملةَ بسقفٍ غيرِ محسوم\n";
if (!$hasCol('fin_authority_caps', 'cap_state')) {
    $run("ALTER TABLE `fin_authority_caps`
            ADD COLUMN `cap_state` ENUM('resolved','unresolved') NOT NULL DEFAULT 'unresolved'
                COMMENT '③ غيرُ المحسومِ يوقف — لا يمرّ',
            ADD COLUMN `approved_by_owner` VARCHAR(120) NULL COMMENT 'مَن اعتمد الرقمَ رسميًّا',
            ADD COLUMN `approved_at` DATETIME NULL",
        'fin_authority_caps · cap_state');
}
// سقفٌ بمبلغٍ موجبٍ ومرجعِ سلطةٍ = محسوم؛ وما عداه يبقى موقوفًا
$conn->query("UPDATE `fin_authority_caps`
                 SET `cap_state`='resolved'
               WHERE `max_amount` IS NOT NULL AND `max_amount` > 0
                 AND `authority_ref` IS NOT NULL AND `authority_ref` <> ''");
echo "   ✔ محسومة: " . $conn->affected_rows . "\n";
$unres = (int) $one("SELECT COUNT(*) FROM `fin_authority_caps` WHERE `cap_state`='unresolved'");
echo "   · غيرُ محسومةٍ (موقوفةٌ fail-closed): $unres\n";

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `v_caps_unresolved` AS
      SELECT 'fin_authority_caps' AS src, `id`, `company_id`, `scope_kind`, `scope_ref`,
             `apr_code`, `max_amount`, `currency`, `cap_state`
        FROM `fin_authority_caps` WHERE `cap_state` <> 'resolved'
      UNION ALL
      SELECT 'gov_ladders', NULL, `company_id`, 'ladder', `ladder_code`,
             `slug`, `cap_amount`, `cap_currency`, `cap_state`
        FROM `gov_ladders` WHERE `cap_kind`='amount' AND `cap_state` <> 'resolved'",
    'VIEW v_caps_unresolved');

// ═══════════════════════════════════════════════════════════════════════════
// ⑦ إزالةُ الأنواعِ الموروثةِ الميتةِ من القائمةِ المغلقة
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ⑦ القائمةُ المغلقةُ — ثمانيةُ TS-01 وحدَها\n";
$legacy = array('payroll_bind', 'periodic_cron', 'bank_recon', 'batch_loop');
$live = 0;
foreach ($legacy as $t) {
    $c = (int) $one("SELECT COUNT(*) FROM `ems_job_queue` WHERE `job_type`='" . $conn->real_escape_string($t) . "'");
    printf("   · %-16s صفوف=%-4s %s\n", $t, $c, $c ? '◄ مستعمَل — لا يُزال' : 'ميت');
    $live += $c;
}
if ($live === 0) {
    $conn->query("ALTER TABLE `ems_job_queue` DROP CONSTRAINT `chk_job_type`");
    $run("ALTER TABLE `ems_job_queue` ADD CONSTRAINT `chk_job_type` CHECK (
            `job_type` IN ('fin_posting','capacity_rollup','depreciation_run','statement_build',
                           'alert_dispatch','event_retry','settlement_recalc','pilot_monitor')
          )", 'chk_job_type ⇐ ثمانيةُ TS-01 وحدَها (أُزيلت 4 قيمٍ ميتة)');
} else {
    echo "   ! قيمٌ موروثةٌ مستعمَلةٌ فعلًا — يُسجَّل التعارضُ ولا تُزال بلا ترحيل\n";
}

echo "\n═══ اكتمل ═══\n\n";
