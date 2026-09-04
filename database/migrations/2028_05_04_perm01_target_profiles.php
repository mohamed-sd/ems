<?php
/**
 * 2028_05_04_perm01_target_profiles.php — القوالبُ المستهدَفةُ من المصدرِ الحاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §3-⑤. **الهدفُ يُؤلَّف من الدليلِ لا من الواقعِ القائم.**
 *
 * ◆ **المصدرُ الحاكمُ `nav_placements`** — مستورَدٌ آليًّا من ورقةِ كلِّ إدارةٍ في
 *   «01 · الدليل المعماري.xlsx» بـ`tools/navr_import_guide.php`. و**422 موضعًا
 *   منها تطابق `modules.code` بنسبةِ مئةٍ بالمئة**، فالجسرُ إلى الصلاحيةِ تامٌّ
 *   بلا تخمين.
 *
 * ⛔ **و`gov_target_nav` مرفوضٌ مصدرًا**: 682 من 725 صفًّا فيه `RENDER-ALIGN` —
 *   أي هدفٌ **مولَّدٌ من الحالةِ القائمة**، وهو عينُ ما يمنع الأمرُ البناءَ عليه.
 *   والدليلُ المقيس: الدوران 34 و35 فيه ثلاثةُ بنودٍ شخصيّةٍ لا غير.
 *
 * ◆ **والرابطُ `nav_ws_roles`** يربط الأدوارَ الخمسةَ والثلاثين كلَّها بمساحاتِها
 *   — فلا دورَ بلا مساحةٍ ولا مساحةَ تُخمَّن.
 *
 * ◆ **و«مساحتي» بنودٌ إلزاميّةٌ لكلِّ حساب** بنصِّ الأمر: «سياسةُ مساحة عملي
 *   تُنفَّذ لا تُسأل — بنودُها إلزاميّةٌ لكلِّ حسابٍ بلا استثناء». فسبعةُ بنودِها
 *   تُضاف إلى كلِّ هدف.
 *
 * ⛔ **ولا يُمَسُّ قالبٌ حيٌّ ولا منحةٌ ولا صلاحيّةُ أحد**: هذه الهجرةُ **تكتب
 *   سجلَّ أهدافٍ منفصلًا** يُقارَن ويُراجَع. والتحوّلُ إليه قرارٌ لاحقٌ بعدَ
 *   الظلِّ والفرزِ — وهو ما يوجبه ترتيبُ الأمرِ نفسُه.
 *
 * ◆ **والدرجةُ ليست في الدليل**: ورقةُ الإدارةِ تعرّف شاشاتِ الإدارةِ لا درجاتِها.
 *   فالهدفُ هنا **بمساحةٍ لا بدرجة**، وفرقُ الدرجاتِ يقع على الأفعالِ لا الشاشات
 *   — وهو **مسمًّى لا مخمَّن**.
 *
 * التشغيل: php database/migrations/2028_05_04_perm01_target_profiles.php
 * العكس:   php database/migrations/2028_05_04_perm01_target_profiles_down.php
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$fail = function ($w) { exit("رفض التشغيل: {$w}\n"); };

echo "══ ⓪ الحرّاسُ — ولا كتابةَ قبلَ اجتيازِها ══════════════════════════════\n";
$plc = (int) $one("SELECT COUNT(*) FROM nav_placements WHERE active = 1");
$mtc = (int) $one("SELECT COUNT(*) FROM nav_placements t WHERE t.active = 1
                     AND EXISTS(SELECT 1 FROM modules m WHERE m.code = t.route)");
printf("   مواضعُ الدليلِ النشِطة            %-5s\n", $plc);
printf("   منها تطابق modules.code          %-5s %s\n", $mtc, ($plc === $mtc ? 'مطابقة تامة' : 'ناقصة'));
if ($plc < 1) { $fail('nav_placements فارغ — لا دليلَ يُبنى عليه'); }
if ($mtc !== $plc) { $fail('موضعٌ لا يطابق سجل الوحدات — الجسر ناقص فلا يُخمَّن'); }

$wsr = (int) $one("SELECT COUNT(DISTINCT role_id) FROM nav_ws_roles");
$rls = (int) $one("SELECT COUNT(*) FROM roles");
printf("   أدوارٌ مربوطةٌ بمساحةٍ في nav_ws_roles %-5s من %s %s\n", $wsr, $rls, ($wsr === $rls ? 'تام' : 'ناقص'));
if ($wsr !== $rls) { $fail('دورٌ بلا مساحةٍ حاكمة — لا يُخمَّن'); }

$my = (int) $one("SELECT COUNT(DISTINCT route) FROM nav_placements WHERE workspace_id = 'WS-MY' AND active = 1");
printf("   بنودُ «مساحتي» الإلزاميّة        %-5s\n", $my);
if ($my < 1) { $fail('مساحتي بلا بنود — والأمرُ يجعلها إلزاميّةً لكلِّ حساب'); }
echo "   الحرّاسُ الأربعةُ مجتازة\n";

echo "\n══ ① سجلُّ الأهداف ═════════════════════════════════════════════════════\n";
$conn->query("CREATE TABLE IF NOT EXISTS `perm01_target_profile` (
    `workspace_id` VARCHAR(20) NOT NULL,
    `name_ar` VARCHAR(120) NOT NULL DEFAULT '',
    `screens_n` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `roles_csv` VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'أدوارُ هذه المساحةِ من nav_ws_roles',
    `source_ref` VARCHAR(160) NOT NULL DEFAULT '',
    `built_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`workspace_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 §3-5 — الهدف المعياري بمساحة من ورقة الدليل'");
echo "   perm01_target_profile\n";

$conn->query("CREATE TABLE IF NOT EXISTS `perm01_target_item` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workspace_id` VARCHAR(20) NOT NULL,
    `module_code` VARCHAR(160) NOT NULL,
    `origin` VARCHAR(20) NOT NULL DEFAULT 'GUIDE' COMMENT 'GUIDE او MY — ومساحتي الزامية',
    `source_ref` VARCHAR(160) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_target_item` (`workspace_id`, `module_code`),
    KEY `ix_ws` (`workspace_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 §3-5 — بنود الهدف: شاشة واحدة لكل صف'");
echo "   perm01_target_item\n";

/* إعادةُ بناءٍ كاملةٌ في كلِّ تشغيل — فالهدفُ إسقاطٌ للدليلِ لا سجلٌّ يتراكم. */
$conn->query("DELETE FROM `perm01_target_item`");
$conn->query("DELETE FROM `perm01_target_profile`");

echo "\n══ ② البناءُ من الدليل ═══════════════════════════════════════════════\n";
$SRC = 'guide:nav_placements';
$wsList = array();
$q = $conn->query("SELECT w.workspace_id, w.name_ar FROM nav_workspaces w
                    WHERE EXISTS(SELECT 1 FROM nav_placements p WHERE p.workspace_id = w.workspace_id AND p.active = 1)
                    ORDER BY w.workspace_id");
while ($r = $q->fetch_assoc()) { $wsList[$r['workspace_id']] = $r['name_ar']; }

foreach ($wsList as $ws => $nm) {
    $wsEsc = $conn->real_escape_string($ws);
    /* بنودُ المساحةِ من الدليل. */
    $conn->query("INSERT IGNORE INTO perm01_target_item (workspace_id, module_code, origin, source_ref)
                  SELECT '{$wsEsc}', m.code, 'GUIDE', CONCAT('{$SRC}:', p.workspace_id)
                    FROM nav_placements p JOIN modules m ON m.code = p.route
                   WHERE p.workspace_id = '{$wsEsc}' AND p.active = 1");
    /* و«مساحتي» إلزاميّةٌ لكلِّ مساحةٍ سواها. */
    if ($ws !== 'WS-MY') {
        $conn->query("INSERT IGNORE INTO perm01_target_item (workspace_id, module_code, origin, source_ref)
                      SELECT '{$wsEsc}', m.code, 'MY', '{$SRC}:WS-MY (الزامية بنص الامر)'
                        FROM nav_placements p JOIN modules m ON m.code = p.route
                       WHERE p.workspace_id = 'WS-MY' AND p.active = 1");
    }
    $n = (int) $one("SELECT COUNT(*) FROM perm01_target_item WHERE workspace_id = '{$wsEsc}'");
    $roles = (string) $one("SELECT GROUP_CONCAT(role_id ORDER BY role_id) FROM nav_ws_roles WHERE workspace_id = '{$wsEsc}'");
    $st = $conn->prepare("INSERT INTO perm01_target_profile (workspace_id, name_ar, screens_n, roles_csv, source_ref)
                          VALUES (?, ?, ?, ?, ?)");
    $sr = $SRC . ' + WS-MY';
    $st->bind_param('ssiss', $ws, $nm, $n, $roles, $sr);
    $st->execute(); $st->close();
    printf("   %-12s %-32s شاشات=%-4s أدوار=%s\n", $ws, mb_substr($nm, 0, 30), $n, $roles ?: '—');
}

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$ok = true;
$tp = (int) $one("SELECT COUNT(*) FROM perm01_target_profile");
$ti = (int) $one("SELECT COUNT(*) FROM perm01_target_item");
printf("   أهدافٌ مبنيّة: %d مساحةً · %d بندًا\n", $tp, $ti);

$orphan = (int) $one("SELECT COUNT(*) FROM perm01_target_item t
                       WHERE NOT EXISTS(SELECT 1 FROM modules m WHERE m.code = t.module_code)");
printf("   بندٌ لا يطابق سجلَّ الوحدات: %d %s\n", $orphan, $orphan === 0 ? 'صفر' : 'خلل');
if ($orphan !== 0) { $ok = false; }

$noMy = (int) $one("SELECT COUNT(*) FROM perm01_target_profile p
                     WHERE p.workspace_id <> 'WS-MY'
                       AND (SELECT COUNT(*) FROM perm01_target_item i
                             WHERE i.workspace_id = p.workspace_id AND i.origin = 'MY') <> {$my}");
printf("   مساحةٌ بلا بنودِ «مساحتي» الإلزاميّة: %d %s\n", $noMy, $noMy === 0 ? 'صفر' : 'خلل');
if ($noMy !== 0) { $ok = false; }

/* ضابطٌ موجب: لم يُمَسَّ شيءٌ من الحيّ. */
$act = (int) $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state = 'active'");
$itm = (int) $one("SELECT COUNT(*) FROM gov_profile_items");
$grt = (int) $one("SELECT COUNT(*) FROM gov_authority_grants");
printf("   ضابطٌ موجب: قوالبُ نافذة=%d (28) · بنودٌ=%d (3924) · منح=%d (77) %s\n",
    $act, $itm, $grt, ($act === 28 && $itm === 3924 && $grt === 77) ? 'لم يُمَسّ الحيّ' : 'مُسَّ ما لا يخصُّنا');
if ($act !== 28 || $itm !== 3924 || $grt !== 77) { $ok = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($ok ? "تمت — والاهداف مبنية من الدليل ولم يمس الحي. الظل: tools/perm01_target_shadow.php\n"
                 : "سقط شاهد — راجع اعلاه\n");
