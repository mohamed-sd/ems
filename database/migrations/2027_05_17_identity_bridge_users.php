<?php
/**
 * 2027_05_17_identity_bridge_users.php
 * ═══════════════════════════════════════════════════════════════════════════
 * جسرُ الهويةِ E-05: كلُّ حسابٍ نشطٍ يقف خلفَه شخصٌ بموقعٍ نشط
 *
 * القياس: 77/77 حسابًا نشطًا بلا جسرٍ — نموذجُ الهويةِ (persons ⇐
 * person_positions) مبنيٌّ (69 شخصًا · 69 موقعًا بنمطِ rel_employee/fam_ops/
 * lvl_dept_mgr/title=role_N) و`users.position_id` **صفرُ ربطٍ** — الجسرُ لم
 * يُمدَّ قط. والمدُّ على النمطِ الحيِّ نفسِه:
 *   ① شخصٌ لكلِّ حسابٍ (مطابقةً بالاسمِ إن وُجد وإلا يُنشأ) —
 *   ② موقعٌ نشطٌ بكيانِ الحسابِ وعنوانِ دورِه (title_code=role_N كما في الحي) —
 *   ③ `users.position_id = p_id`.
 * ◆ عاطلة: من له جسرٌ لا يُمَسّ. والحسابُ المعطَّلُ (role=-1) خارجَ الحكم.
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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

echo "══ جسرُ الهويةِ للحساباتِ النشطة ══\n\n";
/* الجسرُ الحقيقيُّ (مرآةُ e05 المصوَّب): شخصٌ نشطٌ بموقعٍ بعنوانِ دورِ الحسابِ في كيانِه —
   فـusers.position_id مقيّدٌ بنيويًّا بجدولِ positions ولا يصلح جسرًا */
$BRIDGE = "SELECT COUNT(*) FROM users u WHERE u.status='active' AND u.role<>'-1'
           AND NOT EXISTS (
               SELECT 1 FROM person_positions p
                 JOIN persons pe ON pe.person_id=p.person_id AND pe.active=1
                WHERE p.state='active' AND p.company_id=u.company_id
                  AND p.title_code=CONCAT('role_', u.role))";
$before = (int) $one($BRIDGE);
echo "  بلا جسرٍ قبلًا: $before\n";

$rs = $conn->query("SELECT u.id, u.name, u.company_id, u.role FROM users u
                    WHERE u.status='active' AND u.role<>'-1'
                      AND NOT EXISTS (
                          SELECT 1 FROM person_positions p
                            JOIN persons pe ON pe.person_id=p.person_id AND pe.active=1
                           WHERE p.state='active' AND p.company_id=u.company_id
                             AND p.title_code=CONCAT('role_', u.role))");
$made = 0; $matched = 0;
$stP = $conn->prepare("INSERT INTO persons (full_name, active, created_at, updated_at) VALUES (?, 1, NOW(), NOW())");
/* level_code مفتاحٌ أجنبيٌّ إلى قاموسِ HR — يُقرأ منه ولا يُخترع
   (lvl_member ليس فيه؛ lvl_executor هو رتبةُ العضوِ المنفِّذ) */
$lvl = (string) $one("SELECT code FROM hr_dictionaries WHERE code IN ('lvl_executor','lvl_officer') ORDER BY code LIMIT 1");
if ($lvl === '') { exit("  ✘ لا رمزَ مستوًى صالحًا في القاموس\n"); }
echo "  مستوى الجسر: $lvl\n";
$stPos = $conn->prepare("INSERT INTO person_positions
    (person_id, company_id, relation_code, family_code, level_code, title_code,
     scope_type, scope_id, is_primary, valid_from, state, created_at, updated_at)
    VALUES (?, ?, 'rel_employee', 'fam_ops', '" . $conn->real_escape_string($lvl) . "', ?, 'company', ?, 1, CURDATE(), 'active', NOW(), NOW())");
$stU = $conn->prepare("UPDATE users SET position_id=? WHERE id=?");

while ($x = $rs->fetch_assoc()) {
    $uid = (int) $x['id'];
    $co  = (int) $x['company_id'];
    $nm  = trim((string) $x['name']) ?: ('حساب #' . $uid);
    $title = 'role_' . (int) $x['role'];

    /* الشخصُ بالاسمِ إن وُجد وإلا يُنشأ */
    $stF = $conn->prepare("SELECT person_id FROM persons WHERE full_name=? AND active=1 LIMIT 1");
    $stF->bind_param('s', $nm); $stF->execute();
    $pid = (int) ($stF->get_result()->fetch_row()[0] ?? 0);
    $stF->close();
    if ($pid) { $matched++; }
    else {
        $stP->bind_param('s', $nm);
        if (!$stP->execute()) { echo "  ✘ شخص «$nm»: {$conn->error}\n"; continue; }
        $pid = (int) $conn->insert_id;
        $made++;
    }
    /* عاطلة: موقعٌ نشطٌ بنفسِ (الشخص × الكيان × العنوان) لا يُكرَّر */
    $stH = $conn->prepare("SELECT 1 FROM person_positions WHERE person_id=? AND company_id=? AND title_code=? AND state='active' LIMIT 1");
    $stH->bind_param('iis', $pid, $co, $title);
    $stH->execute();
    $hasPos = (bool) $stH->get_result()->fetch_row();
    $stH->close();
    if ($hasPos) { continue; }
    $stPos->bind_param('iisi', $pid, $co, $title, $co);
    if (!$stPos->execute()) { echo "  ✘ موقعُ «$nm»: {$conn->error}\n"; }
}
$stP->close(); $stPos->close(); $stU->close();

$after = (int) $one($BRIDGE);
printf("  أشخاصٌ أُنشئوا: %d · طُوبقوا بالاسم: %d · بلا جسرٍ بعدًا: %d (المتوقَّع 0)\n", $made, $matched, $after);
echo ($after === 0 ? "\n✔ تمّت\n" : "\n✘ بقي بلا جسر\n");
if ($after !== 0) { exit(1); }
