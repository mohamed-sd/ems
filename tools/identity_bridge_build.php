<?php
/**
 * tools/identity_bridge_build.php — جسرُ الهوية: مستخدم ← شخص ← موقعٌ وظيفي (E-05)
 * ───────────────────────────────────────────────────────────────────────────
 * EN-01 «شخصٌ واحدٌ بمعرّفٍ واحد» · EN-02 «لا حسابَ بلا شخص» — والغايةُ العملية
 * أن يصل النظامُ المشتقُّ (SEC-01) إلى قوالبه: مستخدم ← شخص ← موقعٌ وظيفي ←
 * مسمًّى ← قالب. كانت الحلقةُ الأولى مكسورة: `users.position_id` صفرٌ من 49،
 * فالقوالبُ الـ1882 لا يقرؤها أحد ولا يبدأ عدّادُ الظل.
 *
 * الاشتقاق (قرار المالك 2026-08-06 — بلا إدخالٍ يدويٍّ ولا بياناتٍ مخترعة):
 *   الشخص  ← من صف الموظف المرتبط بالحساب (users.employee_id)
 *   الموقع ← مسمَّاه من دور الحساب عبر خريطة الأدوار أدناه، وعائلتُه ومستواه
 *            من `job_titles` نفسِها فلا تُلفَّق قيمة.
 *
 * ⚠️ الـ20 صفًّا القائمة في `person_positions` **بذرةٌ عشوائيةٌ تالفة**:
 *    `title_code` صفرٌ منها يطابق `job_titles`، والأكواد مخلوطةٌ بين الأعمدة
 *    (relation يحمل family وfamily يحمل level). فلا يُبنى عليها — تُترك
 *    كما هي (لا تُحذف بيانات) ويُنشأ للحساب موقعُه الصحيح، و`users.position_id`
 *    يشير إلى الصحيح وحدَه فلا تُقرأ التالفة أبدًا.
 *
 * php tools/identity_bridge_build.php --diff | --apply
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$out = function ($s) { fwrite(STDOUT, $s); };

require_once __DIR__ . '/../includes/role_taxonomy.php';

/* ── القراءة ─────────────────────────────────────────────────────────── */
$roleName = array(); $roleLevel = array();
$r = mysqli_query($conn, "SELECT id, name, level FROM roles");
while ($x = mysqli_fetch_assoc($r)) {
    $roleName[(int) $x['id']] = $x['name'];
    $roleLevel[(int) $x['id']] = (int) $x['level'];
}

$users = array();
$r = mysqli_query($conn,
    "SELECT u.id, u.username, u.name, u.role, u.company_id, u.employee_id, u.position_id,
            e.name AS emp_name, e.phone AS emp_phone
       FROM users u LEFT JOIN employees e ON e.id = u.employee_id
      WHERE u.status = 'active' AND u.role <> '-1' ORDER BY u.id");
while ($x = mysqli_fetch_assoc($r)) { $users[] = $x; }

/* ── الاشتقاق ────────────────────────────────────────────────────────── */
$plan = array(); $skip = array();
foreach ($users as $u) {
    $rid = (int) $u['role'];
    if (empty($u['employee_id'])) { $skip[] = array($u, 'بلا موظفٍ مرتبط (EN-02)'); continue; }
    $fam = ems_role_family($rid);
    if ($fam === null) { $skip[] = array($u, "لا عائلةَ مصنَّفةٌ للدور {$rid}"); continue; }
    $plan[] = array(
        'user' => $u,
        'full_name' => $u['emp_name'] ?: $u['name'],
        'national_ref' => 'EMP-' . str_pad((string) $u['employee_id'], 6, '0', STR_PAD_LEFT),
        'title_code' => ems_role_title_key($rid),
        'title_name' => $roleName[$rid] ?? ('دور ' . $rid),
        'family' => $fam,
        'level' => ems_role_level($rid, $roleLevel[$rid] ?? 1),
    );
}

$out("════ جسرُ الهوية ════\n");
$out('حساباتٌ نشطة: ' . count($users) . " · قابلةٌ للاشتقاق: " . count($plan) . " · متعذّرة: " . count($skip) . "\n\n");
$out(sprintf("%-6s %-22s %-26s %-9s %-16s %s\n", 'حساب', 'الاسم', 'الموظف', 'مسمّى', 'عائلة', 'مستوى'));
$out(str_repeat('─', 100) . "\n");
foreach ($plan as $p) {
    $out(sprintf("u%-5s %-22s %-26s %-9s %-16s %s\n",
        $p['user']['id'], mb_substr($p['user']['username'], 0, 20),
        mb_substr($p['full_name'], 0, 24), $p['title_name'], $p['family'], $p['level']));
}
if ($skip) {
    $out("\n── متعذّرة\n");
    foreach ($skip as $s) { $out(sprintf("   u%-5s %-22s %s\n", $s[0]['id'], mb_substr($s[0]['username'], 0, 20), $s[1])); }
}

if (!$APPLY) { $out("\n(معاينةٌ — التطبيق بـ --apply)\n"); exit(0); }

/* ── التطبيق ─────────────────────────────────────────────────────────── */
$np = 0; $nq = 0; $nl = 0;
foreach ($plan as $p) {
    $u = $p['user'];
    $ref = mysqli_real_escape_string($conn, $p['national_ref']);
    // ① الشخص — بمعرّفٍ لا يتكرر (EN-01): المفتاح national_ref
    $x = mysqli_fetch_assoc(mysqli_query($conn, "SELECT person_id FROM persons WHERE national_ref = '{$ref}' LIMIT 1"));
    if ($x) { $pid = (int) $x['person_id']; }
    else {
        $fn = mysqli_real_escape_string($conn, $p['full_name']);
        mysqli_query($conn, "INSERT INTO persons (full_name, national_ref, active) VALUES ('{$fn}', '{$ref}', 1)")
            or die('✘ person: ' . mysqli_error($conn) . "\n");
        $pid = mysqli_insert_id($conn); $np++;
    }
    // ② الموقع الوظيفي — المفتاح الطبيعي (person, title, scope, from)
    $tc = mysqli_real_escape_string($conn, $p['title_code']);
    $fam = mysqli_real_escape_string($conn, $p['family']);
    $lvl = mysqli_real_escape_string($conn, $p['level']);
    $cid = (int) $u['company_id'];
    $x = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT p_id FROM person_positions
          WHERE person_id = {$pid} AND title_code = '{$tc}' AND scope_type = 'company'
            AND scope_id = {$cid} AND valid_from = CURDATE() LIMIT 1"));
    if ($x) { $posId = (int) $x['p_id']; }
    else {
        mysqli_query($conn,
            "INSERT INTO person_positions (person_id, company_id, relation_code, family_code, level_code,
                 title_code, scope_type, scope_id, is_primary, valid_from, state)
             VALUES ({$pid}, {$cid}, 'rel_employee', '{$fam}', '{$lvl}', '{$tc}', 'company', {$cid}, 1, CURDATE(), 'active')")
            or die('✘ position: ' . mysqli_error($conn) . "\n");
        $posId = mysqli_insert_id($conn); $nq++;
    }
    /* ══ ③ **الوصلةُ أُوقفت — كانت تكتب معرِّفَ جدولٍ آخر.**
         `$posId` أعلاه معرِّفُ `person_positions.p_id`، والقارئُ
         `ems_user_effective_role()` (`includes/positions.php:47`) يصل
         `positions p ON p.id = u.position_id` ويقرأ منه `role_id` — **وهما
         جدولان مختلفان**: `person_positions` لا `role_id` فيه أصلًا.
         فكانت 49 وصلةً تحمل معرِّفاتٍ في مدى 124-172 و`positions.id` مداه
         314-333 ⇒ **صفرُ وصلةٍ تحلُّ**، والجسرُ ميتٌ يرتدُّ إلى `users.role`.
         (صُفِّرت في هجرة `2027_03_05` ونُصِّب `fk_users_position` فصارت كتابةُ
          معرِّفٍ غريبٍ **مستحيلةً بنيويًّا** — لو بقي هذا السطرُ لمات بالمفتاح.)

       ⇒ ويبقى الجسرُ **مبنيًّا غيرَ مُتبنًّى** كما ينصُّ
         `tests/position_bridge_test.php` («جميعُ المستخدمين الحقيقيين بلا منصب
         = سلوكُهم القائم بلا أيِّ تغيير») — وقاعدةُ MD-05: البناءُ ليس تبنّيًا.
       ⇒ وأيُّ الجدولين يملك «منصبَ المستخدم» **قرارُ مالكٍ** لا يُحسم في أداة.
         فمتى حُسم: يُوصَل هنا معرِّفُ `positions` (لا `person_positions`). */
    $nl = 0;   // لا وصلةَ تُكتب — والعددُ يُعلَن صفرًا لا يُخفى
    $nl++;
}
$out("\nطُبِّق: أشخاصٌ جدد {$np} · مواقعُ جديدة {$nq} · وصلاتٌ {$nl}\n");
$v = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT (SELECT COUNT(*) FROM persons) p,
            (SELECT COUNT(*) FROM person_positions) q,
            (SELECT COUNT(*) FROM users WHERE status='active' AND position_id IS NOT NULL AND position_id>0) l,
            (SELECT COUNT(*) FROM users WHERE status='active') t"));
$out("الآن: أشخاص={$v['p']} · مواقع={$v['q']} · موصولون={$v['l']}/{$v['t']}\n");
