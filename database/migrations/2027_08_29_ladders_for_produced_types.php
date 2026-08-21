<?php
/**
 * 2027_08_29_ladders_for_produced_types.php
 *   سلاليمُ الأنواعِ الأربعةِ المنتَجةِ بلا سلّم — INJ-FIX-01 · GAP-01 · GAP-04
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القرارُ مُتَّخَذٌ بتفويضٍ صريحٍ من المالك (2026-08-21)** — ومبناهُ **ما أعلنه
 *   الكودُ فعلًا**، لا رأيٌ جديد:
 *
 *   | النوع            | اليدُ المعتمِدةُ (مصدرُها)                        |
 *   |------------------|---------------------------------------------------|
 *   | `driver`         | الدور 3 — من `fallback_map` في محرّكِ الاعتماد     |
 *   | `equipment`      | الدور 4 — من `fallback_map` نفسِه                  |
 *   | `scr_deductions` | 4 ⇐ 1 ⇐ 19 — بنصِّ هجرة INJ-0219 حرفًا            |
 *   | `contract`       | الدور 19 — الأثرُ ماليٌّ فالمعتمِدُ ماليّ           |
 *
 * ◆ **ولا سلّمَ بيدٍ واحدة**: الاحتياطُ القائمُ يُرجع **خطوةً واحدةً**، ومنشئُ
 *   الطلبِ يُعتمَد له تلقائيًّا إن طابق دورَها ⇒ **اعتمادُ ذاتٍ في نقرة**.
 *   فيُضاف لكلِّ سلّمٍ **يدُ إعدادٍ** (`may_approve = 0`) هي **الدورُ المالكُ
 *   للشاشةِ التي تُنشئ الطلبَ** — مقيسًا من `role_permissions.can_edit`:
 *     · `driver`/`equipment` ⇐ الدور 1 (يملك `movement/*` و`Oprators/*`)
 *     · `contract`           ⇐ الدور 12 (يملك `Contracts/contracts.php`)
 *   فتصير كلُّ يدٍ معتمِدةٍ **هي السلطةُ المُعلَنةُ سلفًا**، ويدُ الإعدادِ مالكَ
 *   الشاشة — ولا يُخترع موضعُ سلطةٍ لم يكن.
 *
 * ◆ **وملاحظةٌ مسجَّلةٌ لا تُصحَّح هنا**: `fallback_map` يجعل **السائقَ** للأسطول (3)
 *   و**المعدةَ** للموارد البشرية (4) — وهو مقلوبٌ في الظاهر. **ولم يُبدَّل**:
 *   تبديلُه تغييرُ سياسةٍ لا إصلاحُ عيب، والحفاظُ على السلوكِ المُعلَنِ أسلم.
 *   ويُترك للمالكِ إن أراد قلبَه بقرارٍ مكتوب.
 *
 * ◆ **والسقفُ `none` لا `unresolved`**: هذه أفعالُ **تغييرِ حالةٍ** لا مدفوعات،
 *   ولا مبلغَ يُقاس عليه سقف. و«السقفُ غيرُ المحسومِ يوقف المعاملة» — فإعلانُه
 *   `unresolved` كان سيوقف تجديدَ العقودِ وتعطيلَ المعداتِ كلَّها بلا سبب.
 *
 * ◆ **وموضعُ الربطِ واحدٌ لا اثنان**: كان ربطُ `timesheet` في
 *   `gov_journey_ladders` (هجرة 2027_08_22). وإضافةُ الأربعةِ هناك كانت
 *   ستزيد **مقامَ الرحلاتِ من ١٤ إلى ١٨** وهي ليست رحلات. فيُنقل الربطُ إلى
 *   `gov_ladders` نفسِه — **السلّمُ يُعلن ما يحكمه** — ويُفرَّغ عمودا الرحلات.
 *   فمصدرُ الربطِ واحدٌ ومقامُ الرحلاتِ يبقى أربعَ عشرة.
 *
 * التشغيل:  php database/migrations/2027_08_29_ladders_for_produced_types.php
 * الرجوع :  php database/migrations/2027_08_29_ladders_for_produced_types.php --revert
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

$CO = 4;

/** رموزُ أدوارٍ تلزم ولا وجودَ لها — تُضاف بسببِها المكتوب. */
$NEW_ACTORS = array(
    array('fleet_mgr', 3, 'إدارةُ الأسطول — يدُ الاعتمادِ المُعلَنةُ للسائق في fallback_map'),
);

/** السلاليمُ الأربعةُ وخطواتُها — actor_code · اسمٌ عربيٌّ · أيعتمد؟ */
$LADDERS = array(
    array('code' => 'LD-14', 'slug' => 'contract_renewal', 'name' => 'تجديدُ العقدِ وتعديلُه',
          'entity' => 'contract', 'action' => 'renewal',
          'steps' => array(
              array('sales_manager', 'إدارةُ المبيعات — إعدادٌ ومراجعة', 0),
              array('fin_dept_mgr',  'مدير الإدارة المالية — الاعتماد',   1))),
    array('code' => 'LD-15', 'slug' => 'driver_state_change', 'name' => 'تفعيلُ السائقِ وتعطيلُه',
          'entity' => 'driver', 'action' => '',
          'steps' => array(
              array('site_mgr',  'إدارةُ التشغيل — إعدادٌ من شاشةِ الحركة', 0),
              array('fleet_mgr', 'إدارةُ الأسطول — الاعتماد',               1))),
    array('code' => 'LD-16', 'slug' => 'equipment_state_change', 'name' => 'تفعيلُ المعدةِ وتعطيلُها',
          'entity' => 'equipment', 'action' => '',
          'steps' => array(
              array('site_mgr',          'إدارةُ التشغيل — إعدادٌ من شاشةِ الحركة', 0),
              array('workforce_officer', 'الموارد البشرية — الاعتماد',              1))),
    array('code' => 'LD-17', 'slug' => 'deduction_approve', 'name' => 'اعتمادُ الخصمِ على الموظف',
          'entity' => 'scr_deductions', 'action' => 'approve',
          'steps' => array(
              array('workforce_officer', 'مراجعةُ الموارد البشرية',   0),
              array('site_mgr',          'اعتمادُ الإدارةِ المختصة',  0),
              array('fin_dept_mgr',      'الاعتمادُ المالي',          1))),
);

/* ══════════════════════════ الرجوع ══════════════════════════════════════ */
if (in_array('--revert', $argv, true)) {
    foreach ($LADDERS as $l) {
        $conn->query("DELETE FROM `gov_ladder_steps` WHERE `ladder_code` = '{$l['code']}'");
        $conn->query("DELETE FROM `gov_ladders`      WHERE `ladder_code` = '{$l['code']}'");
    }
    foreach ($NEW_ACTORS as $a) {
        $conn->query("DELETE FROM `gov_ladder_actor_roles` WHERE `actor_code` = '{$a[0]}'");
    }
    $conn->query("UPDATE `gov_journey_ladders` SET `entity_type` = 'timesheet', `action` = 'approve'
                   WHERE `ladder_code` = 'LD-01'");
    foreach (array('entity_type', 'action') as $c) {
        $conn->query("ALTER TABLE `gov_ladders` DROP COLUMN `{$c}`");
    }
    echo "↺ رجوع: أُزيلت السلاليمُ الأربعةُ وأُعيد الربطُ إلى gov_journey_ladders\n";
    exit(0);
}

/* ══ ① موضعُ الربطِ الواحد ════════════════════════════════════════════════ */
$have = array();
$r = $conn->query("SHOW COLUMNS FROM `gov_ladders`");
while ($r && $x = $r->fetch_assoc()) { $have[$x['Field']] = true; }
foreach (array('entity_type' => 'VARCHAR(64) NULL', 'action' => 'VARCHAR(64) NULL') as $c => $d) {
    if (isset($have[$c])) { echo "① `gov_ladders`.`{$c}`: قائمٌ سلفًا\n"; continue; }
    $conn->query("ALTER TABLE `gov_ladders` ADD COLUMN `{$c}` {$d}");
    if ($conn->errno) { exit("✘ فشلَ إضافةُ `{$c}`: {$conn->error}\n"); }
    echo "① `gov_ladders`.`{$c}`: أُضيف\n";
}
/* نقلُ ربطِ التايم شيت إلى موضعِه الواحد ثم تفريغُ القديم */
$conn->query("UPDATE `gov_ladders` SET `entity_type` = 'timesheet', `action` = 'approve'
               WHERE `ladder_code` = 'LD-01'");
$conn->query("UPDATE `gov_journey_ladders` SET `entity_type` = NULL, `action` = NULL");
echo "① نُقل ربطُ `timesheet` إلى `gov_ladders` وفُرِّغ عمودا الرحلات\n";

/* ══ ② رموزُ الأدوارِ الناقصة ══════════════════════════════════════════════ */
foreach ($NEW_ACTORS as $a) {
    list($code, $role, $basis) = $a;
    $st = $conn->prepare("INSERT INTO `gov_ladder_actor_roles` (`actor_code`,`role_id`,`basis`)
                          SELECT ?, ?, ? FROM DUAL
                           WHERE NOT EXISTS (SELECT 1 FROM `gov_ladder_actor_roles` WHERE `actor_code` = ?)");
    $st->bind_param('siss', $code, $role, $basis, $code);
    $st->execute();
    echo "② رمزُ دور `{$code}` ⇐ {$role}: " . ($conn->affected_rows > 0 ? 'أُضيف' : 'قائمٌ سلفًا') . "\n";
    $st->close();
}

/* ══ ③ السلاليمُ وخطواتُها ════════════════════════════════════════════════ */
foreach ($LADDERS as $l) {
    $st = $conn->prepare(
        "INSERT INTO `gov_ladders`
           (`ladder_code`,`company_id`,`slug`,`name_ar`,`cycle_no`,`escalate_after_hours`,
            `cap_kind`,`cap_state`,`entity_type`,`action`,`is_active`)
         SELECT ?, ?, ?, ?, 1, 48, 'none', 'not_applicable', ?, ?, 1 FROM DUAL
          WHERE NOT EXISTS (SELECT 1 FROM `gov_ladders` WHERE `ladder_code` = ?)");
    $act = $l['action'];
    $st->bind_param('sisssss', $l['code'], $CO, $l['slug'], $l['name'], $l['entity'], $act, $l['code']);
    if (!$st->execute()) { exit("✘ فشلَ سلّمُ {$l['code']}: " . $st->error . "\n"); }
    $made = $conn->affected_rows;
    $st->close();

    $n = 0;
    foreach ($l['steps'] as $i => $s) {
        list($actor, $nameAr, $mayApprove) = $s;
        $stepNo = $i + 1;
        $st = $conn->prepare(
            "INSERT INTO `gov_ladder_steps`
               (`company_id`,`ladder_code`,`step_no`,`actor_code`,`actor_name_ar`,`step_kind`,`may_approve`)
             SELECT ?, ?, ?, ?, ?, ?, ? FROM DUAL
              WHERE NOT EXISTS (SELECT 1 FROM `gov_ladder_steps`
                                 WHERE `ladder_code` = ? AND `step_no` = ?)");
        $kind = $mayApprove ? 'approve' : 'review';
        $st->bind_param('isissiisi', $CO, $l['code'], $stepNo, $actor, $nameAr, $kind, $mayApprove, $l['code'], $stepNo);
        if (!$st->execute()) { exit("✘ فشلَت خطوةُ {$l['code']}#{$stepNo}: " . $st->error . "\n"); }
        $n += $conn->affected_rows;
        $st->close();
    }
    printf("③ %s %-24s (%s) — سلّم=%s · خطوات=%s\n",
           $l['code'], $l['slug'], $l['entity'], $made ? 'جديد' : 'قائم', $n);
}

/* ══ ④ المنظرُ يقرأ الربطَ من موضعِه الواحد ═══════════════════════════════ */
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
    SELECT `l`.`ladder_code`, `l`.`entity_type` AS `entity_type`,
           `s`.`step_no` AS `step_order`, COALESCE(`a`.`role_id`, -99) AS `role_required`,
           `s`.`actor_code`, `s`.`actor_name_ar`, `s`.`step_kind`, `s`.`may_approve`,
           `l`.`name_ar` AS `ladder_name`, `l`.`cap_kind`, `l`.`cap_amount`,
           `l`.`cap_currency`, `l`.`cap_state`, `l`.`escalate_after_hours`, `l`.`is_active`
      FROM ((`gov_ladders` `l`
             JOIN `gov_ladder_steps` `s` ON (`s`.`ladder_code` = `l`.`ladder_code`))
             LEFT JOIN `gov_ladder_actor_roles` `a` ON (`a`.`actor_code` = `s`.`actor_code`))
     WHERE `l`.`is_active` = 1 AND `l`.`entity_type` IS NOT NULL AND `l`.`entity_type` <> ''");
if ($conn->errno) { exit("✘ فشلَ المنظر: {$conn->error}\n"); }
echo "④ المنظر: يقرأ الربطَ من `gov_ladders` — موضعٌ واحد\n";

/* ══ ⑤ استيثاق ═══════════════════════════════════════════════════════════ */
echo "───────────────────────────────────────────────────────────────\n";
foreach (array('timesheet', 'contract', 'driver', 'equipment', 'scr_deductions') as $e) {
    $r = $conn->query("SELECT COUNT(*) c, SUM(`may_approve`) a, COUNT(DISTINCT `role_required`) d
                         FROM `v_approval_rules_effective` WHERE `entity_type` = '{$e}'");
    $x = $r ? $r->fetch_assoc() : array('c' => 0, 'a' => 0, 'd' => 0);
    printf("  %-16s خطوات=%-3s يدُ اعتماد=%-3s أدوارٌ متمايزة=%s\n", $e, $x['c'], $x['a'], $x['d']);
}
echo "الشاهد: php tests/injfix01_ladder_key_intersection_proof.php\n";
