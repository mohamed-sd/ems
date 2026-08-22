<?php
/**
 * tests/injfrd66_xc03_actor_role_test.php — شاهدُ XC-03 ①: حلُّ دورِ الفاعل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هذا شرطٌ قبلَ إعلانِ الإنفاذ**: بوابةُ السلّمِ fail-closed بنصِّ
 *   FR-APP-001 — «مَن لا يُعرف دورُه ليس صاحبَ اليدِ بالشكّ». وكانت تحلُّ
 *   الدورَ من `users.role_id` وحدَه، وهو **فارغٌ في 31 من 76** مستخدمًا.
 *   فلو أُعلنت `EMS_LADDER_GATE=enforce` **لمُنع أولئك من كلِّ اعتماد** —
 *   منعًا بعطبِ قراءةٍ لا بقاعدةِ حوكمة. ومنهم الحسابُ **الوحيدُ** لإدارةِ
 *   الموردين (#5) الذي يقوم عليه معيارُ XC-05.
 *
 * ◆ **إيجابيٌّ**: كلُّ مستخدمٍ حيٍّ يُحَلُّ دورُه.
 * ◆ **سالبٌ**  : فاعلٌ مجهولٌ يبقى NULL — فـfail-closed محفوظٌ ولم يُفتح باب.
 * ◆ **وسالبٌ ثانٍ**: لا تعارضَ بين العمودَين حيث امتلآ معًا — فالضمُّ يُصلح
 *   ولا يقلب حكمًا قائمًا.
 *
 * التشغيل: php tests/injfrd66_xc03_actor_role_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unit_chain_helpers.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$scalar = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};

echo "① القياسُ الذي أوجبَ الإصلاح:\n";
$total = $scalar("SELECT COUNT(*) FROM users WHERE is_deleted=0");
$ridNull = $scalar("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND role_id IS NULL");
$rescued = $scalar("SELECT COUNT(*) FROM users WHERE is_deleted=0 AND role_id IS NULL AND CAST(role AS UNSIGNED)>0");
printf("   · مستخدمون %d · role_id فارغٌ في %d · يُنقذهم role: %d\n", $total, $ridNull, $rescued);
$check($rescued === $ridNull, 'كلُّ من فرغ role_id لديه يُحَلُّ من role');

echo "\n② إيجابيٌّ — كلُّ مستخدمٍ حيٍّ يُحَلُّ دورُه:\n";
$unresolved = array();
$res = @mysqli_query($conn, "SELECT id, name FROM users WHERE is_deleted=0 ORDER BY id");
while ($res && ($u = mysqli_fetch_assoc($res))) {
    if (ems_resolve_actor_role($conn, (int) $u['id']) === null) {
        $unresolved[] = "#{$u['id']} {$u['name']}";
    }
}
$check(empty($unresolved), sprintf('صفرُ فاعلٍ يتعذّر حلُّ دورِه (%d مستخدمًا)', $total));
foreach ($unresolved as $x) { echo "      ✘ {$x}\n"; }

echo "\n③ سالبٌ — fail-closed محفوظٌ لفاعلٍ مجهول:\n";
$ghost = $scalar("SELECT IFNULL(MAX(id),0)+1000 FROM users");
$check(ems_resolve_actor_role($conn, $ghost) === null, "فاعلٌ غيرُ موجودٍ (#{$ghost}) ⇐ NULL لا دورَ مخترَع");
$check(ems_resolve_actor_role($conn, 0) === null, 'فاعلٌ بمعرِّفٍ صفرٍ ⇐ NULL');
$check(ems_resolve_actor_role($conn, -7) === null, 'فاعلٌ بمعرِّفٍ سالبٍ ⇐ NULL');

echo "\n④ سالبٌ — الضمُّ لا يقلب حكمًا قائمًا:\n";
$conflict = $scalar("SELECT COUNT(*) FROM users
                      WHERE is_deleted=0 AND role_id IS NOT NULL AND role_id <> CAST(role AS UNSIGNED)");
$check($conflict === 0, "صفرُ تعارضٍ بين العمودَين حيث امتلآ معًا (قِيس {$conflict})");

/* من كان role_id مملوءًا يجب أن يبقى حكمُه هو هو */
$drift = 0;
$res = @mysqli_query($conn, "SELECT id, role_id FROM users WHERE is_deleted=0 AND role_id IS NOT NULL");
while ($res && ($u = mysqli_fetch_assoc($res))) {
    if (ems_resolve_actor_role($conn, (int) $u['id']) !== (int) $u['role_id']) { $drift++; }
}
$check($drift === 0, "من كان role_id مملوءًا بقي حكمُه كما كان ({$drift} انحرافًا)");

echo "\n⑤ إيجابيٌّ — الحسابُ الذي يقوم عليه XC-05:\n";
$r5 = ems_resolve_actor_role($conn, 5);
$check($r5 === 2, 'الحسابُ #5 «مسؤول الموردين» يُحَلُّ دورُه إلى 2 (كان NULL قبلَ الإصلاح)');

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-03①' : '✘ XC-03①', $pass, $fail);
exit($fail === 0 ? 0 : 1);
