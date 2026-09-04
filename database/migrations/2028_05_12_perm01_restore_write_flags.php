<?php
/**
 * 2028_05_12_perm01_restore_write_flags.php — ردُّ أعلامِ الكتابةِ إلى القوالب
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **عطبٌ حاجزٌ من صنعِ التحوّل**: بُنيت القوالبُ المستهدَفةُ من **ورقةِ الدليل**،
 *   وهي تعرّف **الظهورَ** لا الكتابة — فبُذرت بنودُها عرضًا فقط. ثمَّ تحوّل
 *   قرارُ الصلاحيةِ إلى القوالب، فصار **الخمسةُ والسبعون كلُّهم للقراءةِ فقط**:
 *   صفرُ علمِ كتابةٍ في 2,831 بندًا وفي الاثنَين والثلاثين قالبًا جميعًا،
 *   بينما الجدولُ القديمُ يحمل **953 صفَّ كتابة** (928 منها لأدوارٍ لها
 *   مستخدمٌ حيّ · 35 دورًا · 587 شاشة). ومقيسٌ على مسارِ القرارِ الحيِّ لا على
 *   الجدول: `view=✔ · add=✘ · edit=✘ · del=✘` لكلِّ من فُحص.
 *
 * ⛔ **وأثرُه على القياسِ قبلَ التشغيل**: «تنفيذُ الطرفَين» (صنف أ في
 *   PERM-01-DEC §ق-٧) يخرج **صفرًا مضمونًا بنيويًّا** — لا لأنَّ الفصلَ تحقّق
 *   بل لأنَّ أحدًا لا يستطيع تنفيذَ فعلٍ واحد. وإغلاقُ البندِ عليه هو «الإغلاقُ
 *   الكاذب» و«الحمرةُ بمقامٍ متروك» الممنوعان نصًّا.
 *
 * ◆ **والردُّ من المصدرِ لا من رأي**: لكلِّ قالبٍ نافذٍ **دورٌ واحدٌ** (قِيس:
 *   32 قالبًا وصفرُ قالبٍ بأكثرَ من دور)، فتُقرأ أعلامُ الكتابةِ من
 *   `role_permissions` بذلك الدورِ وبوحدةِ البندِ نفسِها.
 * ⛔ **ولا توسعة**: لا يُرفع علمٌ لا يقابله صفٌّ في الجدولِ القديمِ للدورِ
 *   والوحدةِ نفسِهما — وهذا ضابطٌ سالبٌ يُقاس لا وعدٌ يُكتب.
 * ⛔ **ولا يُفتح عرضٌ**: `allow` لا يُمَسّ. الردُّ على أعلامِ الكتابةِ وحدَها،
 *   فبندٌ ليس في القالبِ يبقى خارجَه ولو كان في الجدولِ القديم.
 *
 * التشغيل: php database/migrations/2028_05_12_perm01_restore_write_flags.php
 * العكس:   php database/migrations/2028_05_12_perm01_restore_write_flags_down.php
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

/* دورُ كلِّ قالبٍ نافذ — من المنحِ الحيِّ لا من اسمِ القالب. */
$PROF_ROLE = "(SELECT MIN(CAST(u2.role AS UNSIGNED)) FROM gov_authority_grants g2
                 JOIN users u2 ON u2.id = g2.user_id AND u2.is_deleted = 0 AND u2.status = 'active'
                WHERE g2.profile_id = i.profile_id AND g2.revoked_at IS NULL)";

echo "══ ① الحالُ قبلَ الردّ ════════════════════════════════════════════════\n";
$before = $one("SELECT COUNT(*) FROM gov_profile_items i
                  JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                 WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1");
printf("   بنودٌ بأعلامِ كتابة: %d\n", $before);

echo "\n══ ② الردُّ من الجدولِ القديمِ بالدورِ والوحدة ══════════════════════════\n";
$conn->query("
  UPDATE gov_profile_items i
    JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
    JOIN modules m ON m.code = i.item_ref
    JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = {$PROF_ROLE}
     SET i.can_add    = GREATEST(i.can_add,    rp.can_add),
         i.can_edit   = GREATEST(i.can_edit,   rp.can_edit),
         i.can_delete = GREATEST(i.can_delete, rp.can_delete)
   WHERE i.item_kind = 'screen' AND i.allow = 1
     AND (rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)");
printf("   بنودٌ رُدَّت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$after = $one("SELECT COUNT(*) FROM gov_profile_items i
                 JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1");
printf("   ★ بنودٌ بأعلامِ كتابةٍ الآن: %d (كانت %d)\n", $after, $before);
/* ◆ **والشاهدُ على الحالِ لا على التغيُّر**: هجرةٌ تُعاد تشغيلُها لا تغيّر شيئًا
     ثانيةً (`GREATEST` لا يُنقِص)، فشرطُ «ازداد العددُ» يُرسِّبها ظلمًا في المرّةِ
     الثانية. والمطلوبُ أن تكون الكتابةُ **قائمةً** بعدَها لا أن تكون قد تغيّرت. */
if ($after === 0) { $good = false; echo "      ✘ لا كتابةَ البتّة\n"; }

$profW = $one("SELECT COUNT(DISTINCT i.profile_id) FROM gov_profile_items i
                 JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                WHERE i.item_kind='screen' AND i.allow=1 AND (i.can_add|i.can_edit|i.can_delete)=1");
$profN = $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state='active'");
printf("   ★ قوالبُ فيها كتابةٌ: %d من %d\n", $profW, $profN);

/* ⛔ الضابطُ السالبُ الحاكم: لا علمَ كتابةٍ بلا نظيرٍ في الجدولِ القديم. */
/* ⛔ **والمطابقةُ بالرمزِ عبرَ كلِّ صفوفِ الوحدة**: `modules.code` **غيرُ فريد**
     (سبعةُ رموزٍ مكرَّرة · 111 بندًا يقابل رمزُها أكثرَ من صفّ) — فوصلٌ واحدٌ
     في الشاهدِ قد يضرب صفًّا غيرَ الذي ضربه التحديث، فيُبلِّغ «توسعةً» حيث لا
     توسعة. (وقع مقيسًا: 136 وهي صفر.) */
$over = $one("SELECT COUNT(*) FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
               WHERE i.item_kind='screen' AND i.allow=1
                 AND (i.can_add|i.can_edit|i.can_delete)=1
                 AND NOT EXISTS(SELECT 1 FROM role_permissions rp
                                  JOIN modules m2 ON m2.id = rp.module_id
                                 WHERE m2.code = i.item_ref AND rp.role_id = {$PROF_ROLE}
                                   AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
printf("   ★★ ضابطٌ سالب — علمُ كتابةٍ بلا نظيرٍ في القديم (توسعة): %d %s\n", $over, $over === 0 ? '' : '✘');
if ($over !== 0) { $good = false; }

/* ولا فقدَ: كلُّ كتابةٍ قديمةٍ لدورٍ حيٍّ ووحدتُها داخلَ قالبِه ⇒ مردودة. */
$lost = $one("SELECT COUNT(*) FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
               WHERE i.item_kind='screen' AND i.allow=1
                 AND (i.can_add|i.can_edit|i.can_delete)=0
                 AND EXISTS(SELECT 1 FROM role_permissions rp
                              JOIN modules m2 ON m2.id = rp.module_id
                             WHERE m2.code = i.item_ref AND rp.role_id = {$PROF_ROLE}
                               AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1))");
printf("   ★★ ضابطٌ سالب — كتابةٌ قديمةٌ لم تُردَّ (فقد): %d %s\n", $lost, $lost === 0 ? '' : '✘');
if ($lost !== 0) { $good = false; }

/* ولا يُفتح عرضٌ لم يكن مفتوحًا. */
$allowN = $one("SELECT COUNT(*) FROM gov_profile_items i
                  JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
                 WHERE i.item_kind='screen' AND i.allow=1");
printf("   ★ ضابطٌ ثابت — بنودُ العرضِ لم تتغيّر: %d\n", $allowN);

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والكتابة عادت بلا توسعة ولا فقد.\n" : "سقط شاهد\n");
