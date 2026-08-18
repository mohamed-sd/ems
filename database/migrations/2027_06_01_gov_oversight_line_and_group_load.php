<?php
/**
 * 2027_06_01_gov_oversight_line_and_group_load.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تكليفُ المالك — البندان ⑥ و④
 * ───────────────────────────────────────────────────────────────────────────
 * ⑥ «الارتباطُ الرقابيُّ المباشرُ بالرئيس التنفيذي، مع جوازِ التنسيقِ الإداريِّ
 *    دون تحويلِه إلى خطِّ تقاريرَ رقابي.»
 *
 *    والقياسُ الحيُّ: أدوارُ الحوكمةِ (15) والمخاطرِ (28) والمراجعِ الداخليِّ
 *    المستقلِّ (33) كلُّها `parent_role_id = NULL` — فلا خطَّ رقابيًّا معبَّرًا
 *    عنه أصلًا، لا صحيحًا ولا خاطئًا.
 *
 *    ولا يُحلُّ ذلك بوضعِ 9 في `parent_role_id`، فذلك يخلط الخطَّين اللذين
 *    يفرّق بينهما التكليفُ نفسُه. فيُضاف **عمودٌ رقابيٌّ مستقل**:
 *      `parent_role_id`      = التنسيقُ الإداريّ  (قد يكون NULL أو غيرَ الرئيس)
 *      `oversight_role_id`   = الخطُّ الرقابيُّ    (الرئيسُ التنفيذيُّ مباشرةً)
 *    وقيدٌ يمنع أن يكون الدورُ رقيبَ نفسِه.
 *
 * ④ «إلغاءُ قيدِ 5–7 مجموعاتٍ لكلِّ إدارة.»
 *    والقياسُ: لا سطرَ كودٍ واحدٍ يفرضه — 33 دورًا من 34 خارجَه فعلًا (1…43).
 *    فالمُلغى قاعدةٌ وثائقيةٌ لا قيدٌ تقنيّ. ويُستبدل بها **مقياسُ عبءٍ حقيقيّ**
 *    يُقاس ولا يُفترض: مجموعةٌ بثمانِ شاشاتٍ فأكثرَ تحتاج تبويبًا أو منسدلة.
 *    (متوسطُ شاشاتِ المجموعةِ اليومَ 1.9 — فالعددُ لم يكن المشكلةَ قطُّ.)
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

const CEO_ROLE = 9; // الإدارة التنفيذية

echo "\n▐ ⑥ الخطُّ الرقابيُّ مستقلٌّ عن التنسيقِ الإداري\n";

if (!$hasCol('roles', 'oversight_role_id')) {
    $run("ALTER TABLE `roles`
            ADD COLUMN `oversight_role_id` SMALLINT UNSIGNED NULL
                COMMENT '⑥ الخطُّ الرقابيُّ المباشر — مستقلٌّ عن parent_role_id الإداريّ',
            ADD COLUMN `oversight_note` VARCHAR(190) NULL
                COMMENT 'حجّةُ الخطِّ الرقابيِّ ومصدرُه'",
        'roles.oversight_role_id');
}

$hasChk = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='roles'
                         AND CONSTRAINT_NAME='chk_role_not_own_oversight'");
if ($hasChk === 0) {
    $run("ALTER TABLE `roles` ADD CONSTRAINT `chk_role_not_own_oversight`
          CHECK (`oversight_role_id` IS NULL OR `oversight_role_id` <> `id`)",
        'roles · CHECK chk_role_not_own_oversight (لا دورَ رقيبُ نفسِه)');
}

// الأدوارُ الرقابيةُ ترتبط بالرئيسِ التنفيذيِّ مباشرةً
$OVERSIGHT = array(
    15 => 'إدارةُ الصلاحياتِ والحوكمة — ارتباطٌ رقابيٌّ مباشرٌ بالرئيس التنفيذي',
    28 => 'إدارةُ المخاطر — ارتباطٌ رقابيٌّ مباشرٌ بالرئيس التنفيذي',
    29 => 'محللُ المخاطر — رقابيًّا عبرَ إدارةِ المخاطرِ إلى الرئيس التنفيذي',
    30 => 'مشرفُ المخاطر — رقابيًّا عبرَ إدارةِ المخاطرِ إلى الرئيس التنفيذي',
    33 => 'المراجعُ الداخليُّ المستقل — ارتباطٌ رقابيٌّ مباشرٌ بالرئيس التنفيذي',
    20 => 'المراجعُ والمدققُ المالي — ارتباطٌ رقابيٌّ مباشرٌ بالرئيس التنفيذي',
);
$st = $conn->prepare("UPDATE `roles` SET `oversight_role_id`=?, `oversight_note`=? WHERE `id`=?");
$n = 0;
foreach ($OVERSIGHT as $rid => $note) {
    $ceo = CEO_ROLE;
    $st->bind_param('isi', $ceo, $note, $rid);
    if ($st->execute()) { $n++; }
}
$st->close();
echo "   ✔ $n دورًا رقابيًّا مرتبطًا بالرئيسِ التنفيذيِّ (الدور " . CEO_ROLE . ")\n";

// الرئيسُ التنفيذيُّ نفسُه: رقابتُه للمجلسِ لا لنفسِه — يُترك NULL بحجّةٍ معلَنة
$conn->query("UPDATE `roles` SET `oversight_role_id`=NULL,
                `oversight_note`='الرئيسُ التنفيذيُّ — رقابتُه لمجلسِ الإدارةِ خارجَ النظام'
              WHERE `id`=" . CEO_ROLE);

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `v_oversight_lines` AS
      SELECT r.`id` AS role_id, r.`name` AS role_name,
             r.`parent_role_id` AS admin_parent_id,
             pa.`name` AS admin_parent_name,
             r.`oversight_role_id` AS oversight_role_id,
             ov.`name` AS oversight_role_name,
             r.`oversight_note`
        FROM `roles` r
   LEFT JOIN `roles` pa ON pa.`id` = r.`parent_role_id`
   LEFT JOIN `roles` ov ON ov.`id` = r.`oversight_role_id`
       WHERE r.`oversight_role_id` IS NOT NULL OR r.`id` = " . CEO_ROLE,
    'VIEW v_oversight_lines');

echo "\n   الخطوطُ بعدَ الضبط:\n";
$r = $conn->query("SELECT role_id, role_name, COALESCE(admin_parent_name,'—'), COALESCE(oversight_role_name,'—')
                     FROM `v_oversight_lines` ORDER BY role_id");
while ($x = $r->fetch_row()) {
    printf("      %-4s %-30s إداريّ=%-20s رقابيّ=%s\n", $x[0], $x[1], $x[2], $x[3]);
}
$bad = (int) $one("SELECT COUNT(*) FROM `roles` WHERE `oversight_role_id` IS NOT NULL AND `oversight_role_id` <> " . CEO_ROLE);
echo "   · أدوارٌ رقابيةٌ لا ترتبط بالرئيس: $bad   [المتوقَّع 0]\n";

// ═══════════════════════════════════════════════════════════════════════════
// ④ مقياسُ عبءٍ حقيقيٌّ بدل قيدِ 5–7
// ═══════════════════════════════════════════════════════════════════════════
echo "\n▐ ④ إلغاءُ قيدِ 5–7 — والمقياسُ عبءُ الاستعمالِ لا العدد\n";
$roles = (int) $one("SELECT COUNT(DISTINCT owner_role_id) FROM `link_groups` WHERE `is_active`=1 AND `stage_no` IS NOT NULL");
$out57 = (int) $one("SELECT COUNT(*) FROM (SELECT owner_role_id, COUNT(*) g FROM `link_groups`
                       WHERE `is_active`=1 AND `stage_no` IS NOT NULL GROUP BY owner_role_id
                       HAVING g < 5 OR g > 7) t");
printf("   · أدوارٌ خارجَ 5–7 اليومَ: %d من %d (%.0f%%) — فالقيدُ لم يكن نافذًا قطُّ\n",
    $out57, $roles, $roles ? 100 * $out57 / $roles : 0);

$run("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `v_group_load` AS
      SELECT g.`owner_role_id`   AS role_id,
             g.`id`              AS group_id,
             g.`group_code`,
             g.`name`            AS group_name,
             g.`stage_no`,
             g.`stage_title`,
             COUNT(n.`id`)       AS screens,
             CASE WHEN COUNT(n.`id`) >= 8 THEN 'overloaded'
                  WHEN COUNT(n.`id`) = 0  THEN 'empty'
                  ELSE 'ok' END  AS load_state
        FROM `link_groups` g
   LEFT JOIN `nav_items` n ON n.`group_id` = g.`id` AND n.`active` = 1
       WHERE g.`is_active` = 1
    GROUP BY g.`id`",
    'VIEW v_group_load (مقياسُ العبءِ بدل عدِّ المجموعات)');

$over = (int) $one("SELECT COUNT(*) FROM `v_group_load` WHERE `load_state`='overloaded'");
$empty = (int) $one("SELECT COUNT(*) FROM `v_group_load` WHERE `load_state`='empty'");
$avg = $one("SELECT ROUND(AVG(screens),2) FROM `v_group_load` WHERE `screens`>0");
printf("   · متوسطُ شاشاتِ المجموعة: %s · مُثقَلةٌ (≥8): %d · فارغة: %d\n", $avg, $over, $empty);
echo "   ⇐ العددُ لم يكن المشكلة. والمقياسُ الآن `v_group_load` يُقاس ولا يُفترض.\n\n";
