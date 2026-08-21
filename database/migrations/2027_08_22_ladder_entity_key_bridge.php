<?php
/**
 * 2027_08_22_ladder_entity_key_bridge.php
 *   مفتاحُ السلالم — وصلُ نوعِ الكيانِ بسلّمِه · INJ-FIX-01 · الموجة أ ③ · GAP-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السببُ الجذريُّ مقيسٌ لا موصوف**: `v_approval_rules_effective` يُخرج
 *   `entity_type` من `gov_ladders.slug` — ثلاثةَ عشرَ رمزًا حوكميًّا
 *   (`unit_daily_approve` · `proc_request_approve` · …). والشاشاتُ تنادي
 *   `approval_create_request()` بأنواعٍ أخرى تمامًا (`timesheet` · `contract` ·
 *   `driver` · `equipment` · `scr_deductions` · `project`).
 *   **فالتقاطعُ صفرٌ حرفًا**، فيُرجع المحرّكُ صفرَ قواعدَ ويسقط كلُّ اعتمادٍ
 *   إلى احتياطِ خطوةٍ واحدة. فليست السلاليمُ ناقصةً — بل لها طريقان لا يلتقيان.
 *
 * ◆ **ولا جدولَ جسرٍ ثالث**: `gov_journey_ladders` يحمل سلفًا
 *   (`ladder_code` ⇄ `driver_route`) لأربعَ عشرةَ رحلة. فالناقصُ عمودُ المفتاحِ
 *   لا سجلٌّ جديد — ويُضاف إليه `entity_type`/`action` فيصير **جسرًا واحدًا
 *   بثلاثةِ مفاتيح**: الرحلةُ والمسارُ ونوعُ الكيان. وإنشاءُ جدولٍ رابعٍ هنا
 *   كان سيكرّر المرضَ الذي يعالجه هذا البرنامج.
 *
 * ◆ **والمنظرُ يُوسَّع اتحادًا لا استبدالًا**: صفوفُ `slug` تبقى كما هي
 *   (لا يُكسر قارئٌ قائم) ويُضاف إليها صفوفُ الربطِ المُعلَن. فمن كان يقرأ
 *   بالـ`slug` يبقى يقرأ، ومن ينادي بنوعِ الكيانِ يجد سلّمَه.
 *
 * ◆ **ولا يُبذَر إلا ما يُطابِق يقينًا**: `timesheet` ⇐ `LD-01` وحدَه —
 *   واسمُ السلّمِ نصًّا «اعتمادُ التايم شيتِ اليوميّ»، وهو سلّمُ رحلتَي
 *   `Approvals/hours_approval.php` في السجل. وأمّا `contract` و`project` و
 *   `scr_deductions` و`driver` و`equipment` **فلا سلّمَ لها في `gov_ladders`
 *   أصلًا**، وإنشاءُ سلّمٍ سؤالُ سياسةٍ («كم يدًا؟ وبأيِّ سقف؟») لا سؤالُ كود
 *   — فتُسجَّل `BLOCKED_OWNER_INPUT` ولا تُخترع لها سلاليم.
 *
 * ◆ **والاحتياطُ يبقى عاملًا** — نصُّ المالك: «لا ترفع شبكةَ الأمانِ قبلَ
 *   إثباتِ البديلِ تحتَ حملٍ حقيقيّ». فلا تمسُّ هذه الهجرةُ `EMS_APPROVAL_RULES`
 *   ولا تُطفئ سقوطًا واحدًا. والإطفاءُ في الموجةِ ج بعدَ الظلّ.
 *
 * التشغيل:  php database/migrations/2027_08_22_ladder_entity_key_bridge.php
 * الرجوع :  php database/migrations/2027_08_22_ladder_entity_key_bridge.php --revert
 * الشاهد :  php tests/injfix01_ladder_key_intersection_proof.php
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

$revert = in_array('--revert', $argv, true);

/** المنظرُ الأصليُّ — يُعاد حرفًا عند الرجوع. */
$VIEW_ORIGINAL = "CREATE OR REPLACE SQL SECURITY DEFINER VIEW `v_approval_rules_effective` AS
    SELECT `l`.`ladder_code` AS `ladder_code`, `l`.`slug` AS `entity_type`,
           `s`.`step_no` AS `step_order`, COALESCE(`a`.`role_id`, -99) AS `role_required`,
           `s`.`actor_code` AS `actor_code`, `s`.`actor_name_ar` AS `actor_name_ar`,
           `s`.`step_kind` AS `step_kind`, `s`.`may_approve` AS `may_approve`,
           `l`.`name_ar` AS `ladder_name`, `l`.`cap_kind` AS `cap_kind`,
           `l`.`cap_amount` AS `cap_amount`, `l`.`cap_currency` AS `cap_currency`,
           `l`.`cap_state` AS `cap_state`, `l`.`escalate_after_hours` AS `escalate_after_hours`,
           `l`.`is_active` AS `is_active`
      FROM ((`gov_ladders` `l`
             JOIN `gov_ladder_steps` `s` ON (`s`.`ladder_code` = `l`.`ladder_code`))
             LEFT JOIN `gov_ladder_actor_roles` `a` ON (`a`.`actor_code` = `s`.`actor_code`))
     WHERE `l`.`is_active` = 1";

if ($revert) {
    $conn->query($VIEW_ORIGINAL);
    echo "↺ المنظر: " . ($conn->errno ? "فشل ({$conn->error})" : "أُعيد إلى تعريفِه الأصليّ") . "\n";
    foreach (array('entity_type', 'action') as $col) {
        $conn->query("ALTER TABLE `gov_journey_ladders` DROP COLUMN `{$col}`");
        echo "↺ العمود {$col}: " . ($conn->errno ? "لم يُحذف ({$conn->error})" : "حُذف") . "\n";
    }
    exit(0);
}

/* ══ ① عمودا المفتاحِ في الجسرِ القائم ═══════════════════════════════════ */
$have = array();
$r = $conn->query("SHOW COLUMNS FROM `gov_journey_ladders`");
while ($r && $x = $r->fetch_assoc()) { $have[$x['Field']] = true; }

foreach (array('entity_type' => 'VARCHAR(64) NULL', 'action' => 'VARCHAR(64) NULL') as $col => $def) {
    if (isset($have[$col])) { echo "① العمود `{$col}`: قائمٌ سلفًا\n"; continue; }
    $conn->query("ALTER TABLE `gov_journey_ladders` ADD COLUMN `{$col}` {$def}");
    if ($conn->errno) { exit("✘ فشلَ إضافةُ `{$col}`: {$conn->error}\n"); }
    echo "① العمود `{$col}`: أُضيف\n";
}

/* ══ ② الربطُ المُعلَن — ما يُطابِق يقينًا وحدَه ═══════════════════════════ */
$BINDINGS = array(
    /* نوعُ الكيان   الفعل        السلّم    السبب */
    array('timesheet', 'approve', 'LD-01', 'اسمُ السلّمِ نصًّا «اعتمادُ التايم شيتِ اليوميّ» وهو سلّمُ رحلتَي Approvals/hours_approval.php'),
);
$bound = 0;
foreach ($BINDINGS as $b) {
    list($et, $ac, $lc, $why) = $b;
    $st = $conn->prepare("UPDATE `gov_journey_ladders` SET `entity_type` = ?, `action` = ? WHERE `ladder_code` = ?");
    $st->bind_param('sss', $et, $ac, $lc);
    if (!$st->execute()) { exit("✘ فشلَ الربطُ {$et}⇐{$lc}: " . $st->error . "\n"); }
    echo "② ربط: {$et}:{$ac} ⇐ {$lc} ({$st->affected_rows} صفًّا) — {$why}\n";
    $bound += $st->affected_rows;
    $st->close();
}

/* ══ ③ توسيعُ المنظرِ اتحادًا — ولا يُكسر قارئٌ قائم ═════════════════════ */
$conn->query("CREATE OR REPLACE SQL SECURITY DEFINER VIEW `v_approval_rules_effective` AS
    SELECT `l`.`ladder_code`, `l`.`slug` AS `entity_type`,
           `s`.`step_no` AS `step_order`, COALESCE(`a`.`role_id`, -99) AS `role_required`,
           `s`.`actor_code`, `s`.`actor_name_ar`, `s`.`step_kind`, `s`.`may_approve`,
           `l`.`name_ar` AS `ladder_name`, `l`.`cap_kind`, `l`.`cap_amount`,
           `l`.`cap_currency`, `l`.`cap_state`, `l`.`escalate_after_hours`, `l`.`is_active`
      FROM ((`gov_ladders` `l`
             JOIN `gov_ladder_steps` `s` ON (`s`.`ladder_code` = `l`.`ladder_code`))
             LEFT JOIN `gov_ladder_actor_roles` `a` ON (`a`.`actor_code` = `s`.`actor_code`))
     WHERE `l`.`is_active` = 1
    UNION
    SELECT `l`.`ladder_code`, `j`.`entity_type` AS `entity_type`,
           `s`.`step_no` AS `step_order`, COALESCE(`a`.`role_id`, -99) AS `role_required`,
           `s`.`actor_code`, `s`.`actor_name_ar`, `s`.`step_kind`, `s`.`may_approve`,
           `l`.`name_ar` AS `ladder_name`, `l`.`cap_kind`, `l`.`cap_amount`,
           `l`.`cap_currency`, `l`.`cap_state`, `l`.`escalate_after_hours`, `l`.`is_active`
      FROM (((`gov_journey_ladders` `j`
             JOIN `gov_ladders` `l` ON (`l`.`ladder_code` = `j`.`ladder_code`))
             JOIN `gov_ladder_steps` `s` ON (`s`.`ladder_code` = `l`.`ladder_code`))
             LEFT JOIN `gov_ladder_actor_roles` `a` ON (`a`.`actor_code` = `s`.`actor_code`))
     WHERE `l`.`is_active` = 1 AND `j`.`entity_type` IS NOT NULL AND `j`.`entity_type` <> ''");
if ($conn->errno) { exit("✘ فشلَ توسيعُ المنظر: {$conn->error}\n"); }
echo "③ المنظر: وُسِّع اتحادًا (slug ∪ الربطُ المُعلَن)\n";

/* ══ ④ استيثاقُ التقاطعِ فورًا ═══════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
$r = $conn->query("SELECT DISTINCT entity_type FROM `v_approval_rules_effective` ORDER BY entity_type");
$inView = array();
while ($r && $x = $r->fetch_assoc()) { $inView[] = $x['entity_type']; }
echo "أنواعُ الكيانِ في المنظر: " . count($inView) . "\n";

$r = $conn->query("SELECT COUNT(*) FROM `v_approval_rules_effective` WHERE entity_type = 'timesheet'");
echo "قواعدُ `timesheet` بعدَ الوصل: " . ($r ? $r->fetch_row()[0] : '?') . " خطوة\n";
echo "◆ والاحتياطُ لم يُمسّ: EMS_APPROVAL_RULES كما هو.\n";
echo "الشاهد: php tests/injfix01_ladder_key_intersection_proof.php\n";
