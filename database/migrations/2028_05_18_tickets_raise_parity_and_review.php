<?php
/**
 * 2028_05_18_tickets_raise_parity_and_review.php — رفعُ البلاغِ ومراجعةُ مصدرِ البذر
 * ═══════════════════════════════════════════════════════════════════════════
 * تكملةُ `2028_05_17_tickets_read_all_profiles.php` بعدَ قراءةِ حاجبَين احمرَّا،
 * **وشرطُ كلٍّ مقروءٌ قبلَ علاجِه** لا رقمُه وحدَه:
 *
 * ◆ **㉝ «أعلامُ كتابةٍ ساقطة» = 31** — والمقيسُ أنَّها **`can_add` وحدَها**
 *   (صفرُ تحريرٍ وصفرُ حذف) على شاشتَي الرفعِ لا غير:
 *   `ticket_form.php` ×28 و`ticket_contextual_open.php` ×3.
 *   والجدولُ القديمُ كان يمنحها، وحكمُ المالكِ نصًّا **«ترفع الإدارةُ بلاغًا»**
 *   — فالرفعُ إضافةٌ، ومنعُه يُفرِغ الفكرةَ من نصفِها.
 *   ⇒ تُعاد **مطابقةً للقديمِ حرفًا لا أوسعَ منه**:
 *     `ticket_form.php` القديمُ يمنحها 32 من 32 ⇒ الكلُّ يرفع.
 *     `ticket_contextual_open.php` القديمُ يمنحها 22 من 32 ⇒ الاثنان والعشرون،
 *     والعشرةُ الباقيةُ ترفع من الاستمارةِ وهي مفتوحةٌ لها.
 *   ⛔ **ولا تُمَسُّ `can_edit` ولا `can_delete`**: انتقالاتُ الحالةِ في
 *     `ticket_form.php` محروسةٌ بـ`$can_manage` التي تشترط دورَ البلاغاتِ أو
 *     المديرَ الأعلى — فالإضافةُ ترفع بلاغًا ولا تنقل حالتَه.
 *
 * ◆ **③ «قالبٌ مبذورٌ من أكثرَ من مصدرٍ بلا مراجعة» = 32** — وهذا **ليس عطبًا
 *   بل بابًا مصمَّمًا**: `perm01_seed_source_review` (‏§10-3) يستثني المصدرَ
 *   المُراجَعَ **بسببٍ ودليل**. فالجولةُ تُقيَّد فيه كما قُيِّد `link_closure:equipments`
 *   قبلَها. ولا يُرفع خطُّ أساسٍ ولا يُسجَّل استثناءٌ صامت.
 *
 * التشغيل: php database/migrations/2028_05_18_tickets_raise_parity_and_review.php
 * العكس:   php database/migrations/2028_05_18_tickets_raise_parity_and_review_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$MARK = 'tkt_read_all:2028_05_17';
if ($one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from = '" . $conn->real_escape_string($MARK) . "'") < 1) {
    exit("⛔ لا أثرَ لجولةِ 2028_05_17 — شغّلها أوّلًا.\n");
}
/* الدورُ الممثِّلُ للقالبِ — التعبيرُ نفسُه الذي يقيس به الحاجبُ ㉝، فلا يُقارَن
   بمقامٍ غيرِ مقامِه. */
$prRole = "(SELECT MIN(CAST(u2.role AS UNSIGNED)) FROM gov_authority_grants g2
              JOIN users u2 ON u2.id = g2.user_id AND u2.is_deleted = 0 AND u2.status = 'active'
             WHERE g2.profile_id = i.profile_id AND g2.revoked_at IS NULL)";

echo "══ ① رفعُ البلاغِ — مطابقةً للقديمِ لا أوسعَ ═══════════════════════════\n";
$total = 0;
foreach (array('Tickets/ticket_form.php', 'Tickets/ticket_contextual_open.php') as $code) {
    $c = $conn->real_escape_string($code);
    $conn->query(
        "UPDATE gov_profile_items i
            JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
             SET i.can_add = 1
           WHERE i.item_kind = 'screen' AND i.item_ref = '{$c}'
             AND i.seeded_from = '" . $conn->real_escape_string($MARK) . "'
             AND i.can_add = 0
             AND EXISTS (SELECT 1 FROM role_permissions rp
                           JOIN modules m2 ON m2.id = rp.module_id
                          WHERE m2.code = i.item_ref AND rp.role_id = {$prRole} AND rp.can_add = 1)");
    $n = $conn->affected_rows;
    $total += $n;
    printf("   %-38s رُفعت الاضافة في: %d صفًّا\n", $code, $n);
}
printf("   المجموع: %d\n", $total);

echo "\n══ ② قيدُ مصدرِ البذرِ في سجلِّ المراجعة ═══════════════════════════════\n";
$reason   = 'حكم المالك: كل الادوار تقرا البلاغات لان الادارة ترفع بلاغا وتتابعه حتى الاقفال — قراءة للمتابعة واضافة للرفع، بلا تحرير ولا حذف';
$evidence = '2028_05_17_tickets_read_all_profiles.php + 2028_05_18_tickets_raise_parity_and_review.php';
$st = $conn->prepare(
    "INSERT IGNORE INTO perm01_seed_source_review
       (profile_id, seeded_from, reason, evidence, reviewed_by)
     SELECT DISTINCT i.profile_id, ?, ?, ?, 0
       FROM gov_profile_items i
       JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
      WHERE i.seeded_from = ?");
$st->bind_param('ssss', $MARK, $reason, $evidence, $MARK);
$st->execute();
printf("   صفوفُ مراجعةٍ قُيِّدت: %d\n", $st->affected_rows);
$st->close();

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$actv = "JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'";
$wLost = $one("SELECT COUNT(*) FROM gov_profile_items i {$actv}
                WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=0
                  AND EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                              WHERE m2.code=i.item_ref AND rp.role_id={$prRole}
                                AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
$wOver = $one("SELECT COUNT(*) FROM gov_profile_items i {$actv}
                WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1
                  AND NOT EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m2 ON m2.id=rp.module_id
                                  WHERE m2.code=i.item_ref AND rp.role_id={$prRole}
                                    AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
printf("   ㉝ فقدٌ: %d · توسعةٌ: %d (المستهدف صفر وصفر)\n", $wLost, $wOver);

$unrev = $one("SELECT COUNT(*) FROM (
        SELECT i.profile_id FROM gov_profile_items i
          JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
         WHERE NOT EXISTS(SELECT 1 FROM perm01_seed_source_review r
                           WHERE r.profile_id = i.profile_id AND r.seeded_from = i.seeded_from)
         GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
printf("   ③ قوالبُ متعدِّدةُ المصادرِ بلا مراجعة: %d (المستهدف صفر)\n", $unrev);

/* ⓐ ولا تحريرَ ولا حذفَ وُلد في هذه الجولةِ ولا في سابقتِها */
$wr = $one("SELECT COUNT(*) FROM gov_profile_items
             WHERE seeded_from = '" . $conn->real_escape_string($MARK) . "'
               AND (can_edit = 1 OR can_delete = 1)");
printf("   رايات تحرير او حذف وُلدت: %d (المستهدف صفر)\n", $wr);

/* ⓑ والحكمُ الحيُّ: دورٌ عاديٌّ يرفع ولا ينقل حالةً */
require_once $ROOT . '/includes/permissions_helper.php';
$mid = $one("SELECT id FROM modules WHERE code = 'Tickets/ticket_form.php' LIMIT 1");
$t = ems_permission_trace($conn, $mid, 889);
printf("   المستخدم 889 (دور 15) على استمارة البلاغ: يقرأ=%s · يضيف=%s · يحرر=%s\n",
       !empty($t['perms']['can_view']) ? 'نعم' : 'لا',
       !empty($t['perms']['can_add']) ? 'نعم' : 'لا',
       !empty($t['perms']['can_edit']) ? 'نعم' : 'لا');

$good = ($wLost === 0 && $wOver === 0 && $unrev === 0 && $wr === 0
         && !empty($t['perms']['can_view']) && !empty($t['perms']['can_add'])
         && empty($t['perms']['can_edit']));
if (!$good) { exit("\n⛔ الشاهدُ لم يتحقّق — لا تلتزمْ قبلَ المراجعة.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ.\n";
