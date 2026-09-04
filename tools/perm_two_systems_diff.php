<?php
/**
 * tools/perm_two_systems_diff.php — فارقُ نظامَي الصلاحيةِ على المستخدمين الأحياء
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **وحدةُ العدِّ هي الكودُ لا صفُّ الوحدة**: القالبُ يحكم بـ`item_ref =
 *   modules.code` بينما `role_permissions` يحكم بـ`module_id`. و`modules.code`
 *   **ليس فريدًا ولا مفهرسًا** (773 صفًّا · 755 كودًا · `main/project_users.php`
 *   عشرةُ صفوفٍ صفٌّ لكلِّ مالك) — فالعدُّ بصفِّ الوحدةِ يحسب الشاشةَ الواحدةَ
 *   عشرَ مرّاتٍ ويُضخِّم الفارق. والمستخدمُ يفتح شاشةً لا صفَّ جدولٍ، فالكودُ
 *   هو المقام. ⛔ وتُطبع الوحدتان معًا كي لا يُقرأ الرقمُ الأكبرُ مرّةً أخرى
 *   على أنّه الفارق.
 *
 * ◆ **والحياةُ تُعرَّف مرّةً واحدةً للبسطِ والمقام** — `is_deleted=0` و
 *   `status='active'` و`company_id` — وإلا خرجت نسبةٌ لا يقابلها مستخدمٌ صحيح.
 *
 * ◆ **والاتّجاهانِ لا يُجمعان**: `role_only` (الدورُ يمنح والقالبُ يحجب) عطبُ
 *   خدمةٍ في الاتّجاهِ الآمن، و`tpl_only` (القالبُ يفتح والدورُ يغلق) توسعةٌ لم
 *   يُصدرها قرار. جمعُهما في رقمٍ واحدٍ يُخفي أيَّهما تحرَّك.
 *
 * التشغيل: php tools/perm_two_systems_diff.php [--company=4] [--top=12]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

$CO = 4; $TOP = 12;
foreach ($argv as $a) {
    if (preg_match('/^--company=(\d+)$/', $a, $m)) { $CO = (int) $m[1]; }
    if (preg_match('/^--top=(\d+)$/', $a, $m))     { $TOP = (int) $m[1]; }
}

$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

/* الحياةُ والتغطيةُ — تعريفٌ واحدٌ يُعاد استعمالُه في كلِّ استعلام. */
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = $CO";
$COV  = "EXISTS(SELECT 1 FROM gov_authority_grants g
                  JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                 WHERE g.user_id = u.id AND g.revoked_at IS NULL
                   AND (g.valid_to IS NULL OR g.valid_to > NOW()))";

echo "══ المقام — من يُقاس أصلًا؟ ════════════════════════════════════════════\n";
$live    = $one("SELECT COUNT(*) FROM users u WHERE $LIVE");
$covered = $one("SELECT COUNT(*) FROM users u WHERE $LIVE AND $COV");
$mods    = $one('SELECT COUNT(*) FROM modules');
$codes   = $one('SELECT COUNT(DISTINCT code) FROM modules');
printf("   مستخدمون أحياءُ (شركة %d)            %d\n", $CO, $live);
printf("   منهم مغطَّون بقالبٍ نافذٍ            %d  (%.1f%%)\n", $covered, 100 * $covered / max(1, $live));
printf("   صفوفُ `modules`                       %d\n", $mods);
printf("   منها أكوادٌ متمايزة                 %d   ⇐ الفارقُ %d كودًا مكرَّرًا\n",
       $codes, $mods - $codes);

/* ── الفارقُ محسوبًا بالوحدتَين ─────────────────────────────────────────── */
$diff = function ($byCode) use ($db, $LIVE, $COV) {
    $unit = $byCode
        ? "(SELECT DISTINCT code FROM modules) c"
        : "modules c";
    $tv = $byCode
        ? "i.item_ref = c.code"
        : "i.item_ref = c.code";
    $rvJoin = $byCode
        ? "JOIN modules m2 ON m2.id = rp.module_id WHERE rp.role_id = u.role AND m2.code = c.code"
        : "WHERE rp.role_id = u.role AND rp.module_id = c.id";
    $sql = "SELECT SUM(tv=1 AND rv=1) ba, SUM(tv=0 AND rv=0) bd,
                   SUM(tv=1 AND rv=0) tpl, SUM(tv=0 AND rv=1) rol, COUNT(*) pairs
              FROM (SELECT
                      (SELECT COALESCE(MAX(i.allow),0)
                         FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state='active'
                         JOIN gov_profile_items i ON i.profile_id = p.profile_id
                              AND i.item_kind = 'screen' AND $tv
                        WHERE g.user_id = u.id AND g.revoked_at IS NULL
                          AND (g.valid_to IS NULL OR g.valid_to > NOW())) tv,
                      (SELECT COALESCE(MAX(rp.can_view),0) FROM role_permissions rp $rvJoin) rv
                    FROM users u CROSS JOIN $unit
                   WHERE $LIVE AND $COV) x";
    $r = $db->query($sql);
    return $r ? $r->fetch_assoc() : null;
};

echo "\n══ الفارقُ — والوحدتان معًا كي لا يُقرأ الأكبرُ فارقًا ═════════════════\n";
$byCode = $diff(true);
$byRow  = $diff(false);
if (!$byCode || !$byRow) { exit("⛔ تعذّر القياس: " . $db->error . "\n"); }

printf("   %-26s %10s %10s %10s %10s %9s\n", 'وحدةُ العدّ', 'أزواج', 'سماحٌ معًا', 'منعٌ معًا', '②>①', '①>②');
printf("   %-26s %10s %10s %10s %10s %9s\n", 'بالكودِ المتمايز ✔',
       number_format($byCode['pairs']), number_format($byCode['ba']),
       number_format($byCode['bd']), number_format($byCode['tpl']), number_format($byCode['rol']));
printf("   %-26s %10s %10s %10s %10s %9s\n", 'بصفِّ الوحدةِ (مضخَّم)',
       number_format($byRow['pairs']), number_format($byRow['ba']),
       number_format($byRow['bd']), number_format($byRow['tpl']), number_format($byRow['rol']));
printf("\n   الاتّفاقُ بالكود: %.2f%%   ·  التضخُّمُ من الأكوادِ المكرَّرة: %s زوجًا\n",
       100 * ($byCode['ba'] + $byCode['bd']) / max(1, $byCode['pairs']),
       number_format($byRow['tpl'] - $byCode['tpl']));

echo "\n   ٢ يسمح و١ يمنع = " . number_format($byCode['tpl']) . "  ⇐ توسعةٌ لم يُصدرها قرار\n";
/* ⚠ **ودلالةُ الاتّجاهِ الثاني تنقلب باكتمالِ التغطية**: ما دام في الأحياءِ
     من يحكمه الجدولُ القديمُ فارتفاعُ `١>٢` **حجبُ منحٍ حيّةٍ** — عطبُ خدمة.
     أمّا إذا صارت التغطيةُ تامّةً فلا أحدَ يحكمه ذلك الجدول، ويصير سجلًّا
     تاريخيًّا بنصِّ PERM-01 §6-⑨ — فالفارقُ حينئذٍ **أثرُ التضييقِ المقصود**
     لا حجبًا. ⛔ ورقمٌ واحدٌ بدلالتَين يُقرأ خطأً إن لم يُقيَّد بمقامِه. */
$fullCover = ($covered === $live && $live > 0);
echo "   ١ يسمح و٢ يمنع = " . number_format($byCode['rol']) . "  ⇐ "
   . ((int) $byCode['rol'] === 0
        ? "صفر — لا ممنوحَ محجوب"
        : ($fullCover
             ? "أثرُ التضييقِ المقصود — والتغطيةُ تامّةٌ فلا يحكم الجدولُ القديمُ أحدًا (سجلٌّ تاريخيّ · §6-9)"
             : "⛔ ارتفعَ عن صفرٍ ومن الأحياءِ من يحكمه الجدولُ القديم: منحٌ حيّةٌ تُحجب")) . "\n";

/* ── أين تتركّز التوسعةُ؟ بالدورِ والقالب ───────────────────────────────── */
echo "\n══ تركُّزُ التوسعةِ — بالدورِ وقالبِه (بالكودِ المتمايز) ══════════════\n";
/* ⚠ الترتيبُ بحاصلِ ضربِ عمودَين مجمَّعَين يُردُّ في MariaDB
   («Reference not supported (reference to group function)») — فيُلَفُّ التجميعُ
   في جدولٍ مشتقٍّ ويُرتَّب من خارجِه. */
$q = $db->query(
  "SELECT *, widen * holders AS impact FROM (
    SELECT u.role rid, r.name rname, p.profile_code pc,
            COUNT(DISTINCT u.id) holders,
            (SELECT COUNT(DISTINCT m.code) FROM role_permissions rp
               JOIN modules m ON m.id = rp.module_id
              WHERE rp.role_id = u.role AND rp.can_view = 1) own,
            (SELECT COUNT(*) FROM (SELECT DISTINCT code FROM modules) c
              WHERE EXISTS(SELECT 1 FROM gov_profile_items i
                            WHERE i.profile_id = p.profile_id AND i.item_kind='screen'
                              AND i.allow = 1 AND i.item_ref = c.code)
                AND NOT EXISTS(SELECT 1 FROM role_permissions rp2
                                 JOIN modules m2 ON m2.id = rp2.module_id
                                WHERE rp2.role_id = u.role AND m2.code = c.code
                                  AND rp2.can_view = 1)) widen
       FROM gov_authority_grants g
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
       JOIN users u ON u.id = g.user_id AND $LIVE
       LEFT JOIN roles r ON r.id = u.role
      WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
      GROUP BY u.role, r.name, p.profile_code, p.profile_id
      HAVING widen > 0
   ) z ORDER BY impact DESC, widen DESC LIMIT $TOP");
printf("   %-9s %-4s %-28s %7s %7s %7s %9s\n", 'القالب', 'دور', 'الاسم', 'حاملون', 'دورُه', 'قالبُه+', 'الأثر');
if ($q) {
    while ($x = $q->fetch_assoc()) {
        printf("   %-9s %-4s %-28s %7s %7s %7s %9s\n",
            $x['pc'], $x['rid'], mb_substr((string) $x['rname'], 0, 26),
            $x['holders'], $x['own'], $x['widen'], $x['widen'] * $x['holders']);
    }
} else { echo "   ⛔ " . $db->error . "\n"; }

/* ── ضابطٌ موجبٌ: أيرى الكاشفُ توسعةً نعلمها يقينًا؟ ───────────────────── */
echo "\n══ الضابطُ الموجب — أيرى الكاشفُ ما نعلمه؟ ═══════════════════════════\n";
$probe = $db->query(
    "SELECT COUNT(*) n FROM (SELECT DISTINCT code FROM modules) c
      WHERE EXISTS(SELECT 1 FROM gov_profile_items i
                     JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state='active'
                    WHERE i.item_kind='screen' AND i.allow=1 AND i.item_ref = c.code
                      AND p.profile_code = 'FIN-G3')
        AND NOT EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                        WHERE rp.role_id = 34 AND m.code = c.code AND rp.can_view = 1)");
$pv = $probe ? (int) $probe->fetch_row()[0] : -1;
$hasBank = $one("SELECT COUNT(*) FROM gov_authority_grants g
                   JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                   JOIN users u ON u.id=g.user_id AND $LIVE
                  WHERE u.role=34 AND p.profile_code='FIN-G3'
                    AND g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to>NOW())");
if ($hasBank > 0) {
    printf("   الدور 34 على FIN-G3 · فجوةٌ متوقَّعةٌ موجبة: %s %s\n",
           $pv, $pv > 0 ? '✔ الكاشفُ يرى' : '⛔ صفرٌ على حالةٍ معلومةٍ — الكاشفُ أعمى');
} else {
    printf("   الدور 34 لم يعُد على FIN-G3 (فُصل) — والفجوةُ النظريّةُ للقالبِ %s\n", $pv);
    echo "   ◆ فالضابطُ يتحوّل: تُقاس فجوةُ الدورِ الفعليّةُ في جدولِ التركُّزِ أعلاه.\n";
}

echo "\n────────────────────────────────────────────────────────────────────────\n";
echo "الوحدةُ المعتمَدةُ: **الكودُ المتمايز**. وأيُّ استشهادٍ برقمِ صفِّ الوحدةِ خطأٌ مقاس.\n";
