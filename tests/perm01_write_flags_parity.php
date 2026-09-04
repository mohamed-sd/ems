<?php
/**
 * tests/perm01_write_flags_parity.php — الكتابةُ لا تسقط في التحوّل
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ الذي يمنعه هذا الشاهد وقع فعلًا**: بُنيت القوالبُ من ورقةِ الدليلِ
 *   وهي تعرّف **الظهورَ** لا الكتابة، فبُذرت عرضًا فقط؛ ثمَّ صار القرارُ إليها،
 *   فصار الخمسةُ والسبعون **للقراءةِ فقط** — صفرُ علمِ كتابةٍ في 2,831 بندًا
 *   بينما الجدولُ القديمُ يحمل 953 صفَّ كتابة. ولم يرصده مقياسٌ واحدٌ من
 *   اثنَين وثلاثين، لأنَّ المقاييسَ كانت تسأل عن **العرضِ** وحدَه.
 *
 * ◆ **والتكافؤُ في الاتّجاهَين**: لا فقدَ (كتابةٌ قديمةٌ لبندٍ في القالبِ ولم
 *   تُردّ) **ولا توسعة** (علمُ كتابةٍ لا نظيرَ له في القديم).
 * ⛔ **والمطابقةُ بالرمزِ عبرَ كلِّ صفوفِ الوحدة**: `modules.code` غيرُ فريد —
 *   فوصلٌ واحدٌ يُبلِّغ توسعةً حيث لا توسعة (وقع مقيسًا: 136 وهي صفر).
 * ⛔ **والقياسُ يُختم على مسارِ القرارِ الحيِّ** لا على الجدول: بندٌ في المخزنِ
 *   لا يعني علمًا في يدِ المستخدم.
 *
 * التشغيل: php tests/perm01_write_flags_parity.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function chk($c, $m, $d = '') { $c ? ok($m . ($d !== '' ? " — {$d}" : '')) : bad($m . ($d !== '' ? " — {$d}" : '')); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

/** دورُ القالبِ من المنحِ الحيّ — لا من اسمِه. */
$PR = "(SELECT MIN(CAST(u2.role AS UNSIGNED)) FROM gov_authority_grants g2
          JOIN users u2 ON u2.id = g2.user_id AND u2.is_deleted = 0 AND u2.status = 'active'
         WHERE g2.profile_id = i.profile_id AND g2.revoked_at IS NULL)";
$ACTIVE = "JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'";

fwrite(STDOUT, "\n══ PERM-01 — أعلامُ الكتابةِ لا تسقط في التحوّل ══\n");

head('① الكتابةُ قائمةٌ في القوالب');
$w = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
            WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1");
$v = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
            WHERE i.item_kind='screen' AND i.allow=1");
chk($w > 0, '★★ **بنودٌ بأعلامِ كتابة** — وصفرٌ هنا يعني نظامًا للقراءةِ فقط',
    "كتابة={$w} من عرض={$v}");

$pw = $one("SELECT COUNT(DISTINCT i.profile_id) FROM gov_profile_items i {$ACTIVE}
             WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1");
$pn = $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='active'");
chk($pw >= $pn - 1, 'وأكثرُ القوالبِ فيها كتابة', "{$pw} من {$pn}");

head('② **التكافؤُ مع المصدر** — لا فقدَ ولا توسعة');
$lost = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
               WHERE i.item_kind='screen' AND i.allow=1
                 AND (i.can_add|i.can_edit|i.can_delete)=0
                 AND EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                             WHERE m2.code=i.item_ref AND rp.role_id={$PR}
                               AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
chk($lost === 0, '★★ **صفرُ فقد** — كلُّ كتابةٍ قديمةٍ لبندٍ في القالبِ مردودة', "فقد={$lost}");

$over = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
               WHERE i.item_kind='screen' AND i.allow=1
                 AND (i.can_add|i.can_edit|i.can_delete)=1
                 AND NOT EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                                 WHERE m2.code=i.item_ref AND rp.role_id={$PR}
                                   AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
chk($over === 0, '★★ **صفرُ توسعة** — لا علمَ كتابةٍ بلا نظيرٍ في المصدر', "توسعة={$over}");

head('③ الختمُ على مسارِ القرارِ الحيّ');
$prev = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$r = $conn->query("
  SELECT g.user_id uid, u.role, m.id mid
    FROM gov_authority_grants g
    JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
    JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='screen'
         AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1
    JOIN modules m ON m.code=i.item_ref
    JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active' AND u.company_id=4
   WHERE g.revoked_at IS NULL GROUP BY g.user_id ORDER BY g.user_id LIMIT 10");
$n = 0; $hasW = 0;
while ($x = $r->fetch_assoc()) {
    $_SESSION['user'] = array('id'=>(int)$x['uid'],'role'=>(string)$x['role'],
                              'company_id'=>4,'name'=>'write parity probe');
    $pm = get_module_permissions($conn, (int)$x['mid']);
    $n++;
    if (!empty($pm['can_add']) || !empty($pm['can_edit']) || !empty($pm['can_delete'])) { $hasW++; }
}
if ($prev === null) { unset($_SESSION['user']); } else { $_SESSION['user'] = $prev; }
chk($n >= 5, 'فاعلون فُحصوا فعلًا', "عدد={$n}");
chk($n > 0 && $hasW === $n,
    '★★ **الحارسُ يُخرج الكتابةَ لمن يملكها** — لا في المخزنِ وحدَه',
    "{$hasW} من {$n}");

head('④ **الضابطُ السالب** — أيرصد الشاهدُ فقدًا لو وقع؟');
$victim = $conn->query("SELECT i.item_id FROM gov_profile_items i {$ACTIVE}
                         WHERE i.item_kind='screen' AND i.allow=1
                           AND (i.can_add|i.can_edit|i.can_delete)=1 LIMIT 1")->fetch_assoc();
chk($victim !== null, 'وُجد بندُ كتابةٍ يصلح للكسرِ المتعمَّد');
if ($victim) {
    $ID = (int) $victim['item_id'];
    $row = $conn->query("SELECT can_add, can_edit, can_delete FROM gov_profile_items
                          WHERE item_id={$ID}")->fetch_assoc();
    /* ⛔ الاستعادةُ تُسجَّل قبلَ الكسر. */
    register_shutdown_function(static function () use ($conn, $ID, $row) {
        $conn->query("UPDATE gov_profile_items SET can_add=" . (int) $row['can_add']
            . ", can_edit=" . (int) $row['can_edit'] . ", can_delete=" . (int) $row['can_delete']
            . " WHERE item_id={$ID}");
    });
    $conn->query("UPDATE gov_profile_items SET can_add=0, can_edit=0, can_delete=0 WHERE item_id={$ID}");
    $lost2 = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
                    WHERE i.item_kind='screen' AND i.allow=1
                      AND (i.can_add|i.can_edit|i.can_delete)=0
                      AND EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                                  WHERE m2.code=i.item_ref AND rp.role_id={$PR}
                                    AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
    chk($lost2 >= 1, '★★ **الشاهدُ رصد الفقدَ المصنوع** — فصفرُه أعلاه قياسٌ لا عمًى',
        "فقدٌ بعدَ الكسر={$lost2}");
    $conn->query("UPDATE gov_profile_items SET can_add=" . (int) $row['can_add']
        . ", can_edit=" . (int) $row['can_edit'] . ", can_delete=" . (int) $row['can_delete']
        . " WHERE item_id={$ID}");
    $lost3 = $one("SELECT COUNT(*) FROM gov_profile_items i {$ACTIVE}
                    WHERE i.item_kind='screen' AND i.allow=1
                      AND (i.can_add|i.can_edit|i.can_delete)=0
                      AND EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                                  WHERE m2.code=i.item_ref AND rp.role_id={$PR}
                                    AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
    chk($lost3 === 0, '★ وبالاستعادةِ عاد الصفر', "فقد={$lost3}");
}

fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0 ? "✔ WRITE_FLAGS_PARITY = PASS\n" : "✘ أعلامُ الكتابةِ ساقطة\n");
exit($FAIL === 0 ? 0 : 1);
