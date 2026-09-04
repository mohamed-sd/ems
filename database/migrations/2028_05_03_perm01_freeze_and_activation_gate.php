<?php
/**
 * 2028_05_03_perm01_freeze_and_activation_gate.php — تجميدُ البذرِ وبوّابةُ التفعيل
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §3-① و§4. **بوّابةٌ لا إعلان**: التجميدُ المكتوبُ في وثيقةٍ يخرقه
 * أوّلُ سكربتٍ ينسى قراءتَها — فيُنفَّذ في المخطَّطِ بقادحٍ يردُّ الكتابةَ نفسَها.
 *
 * ◆ **وموضعُه المخطَّطُ لا الشيفرة**: منافذُ الكتابةِ في نظامِ القوالبِ ثلاثُ
 *   أدواتِ سطرِ أوامرٍ وهجراتٌ — لا شاشةَ واحدة. فحارسٌ في PHP لا يبلغها،
 *   والقادحُ يبلغ كلَّ كاتبٍ مهما كان بابُه.
 *
 * ◆ **ثلاثةُ أحكامٍ من §3-① و§4**:
 *   ① لا تُصدَر منحةٌ جديدةٌ ما دام التجميدُ نافذًا.
 *   ② لا يُفعَّل قالبٌ **مبذورٌ من أكثرَ من مصدر** — و27 من 28 النافذةِ كذلك،
 *     فالحارسُ يمنع **الجديدَ** ولا يُسقِط القائمَ (إسقاطُه يقطع 71 مستخدمًا).
 *   ③ لا يُفعَّل قالبُ **مسودّةٍ بلا سجلِّ اعتماد** — و`FTRE-0062` يسنده نصًّا:
 *     «كلُّ تكليفٍ … لا يسري قبل موافقةِ الرئيسِ الموثَّقة · والموافقةُ سجلٌّ
 *     لا رسالة».
 *
 * ⛔ **ولا يُعمَّم الحكمُ على القائم**: القوالبُ النافذةُ اليومَ تبقى نافذةً —
 *   القادحُ على `BEFORE UPDATE` عند **الانتقالِ** إلى `active` لا على وجودِه.
 *
 * ◆ **ورفعُ التجميدِ سطرٌ واحدٌ** (`UPDATE gov_policy_freeze SET active=0`) —
 *   فلا يحتاج نشرَ شيفرةٍ ولا هجرةً عكسيّة.
 *
 * التشغيل: php database/migrations/2028_05_03_perm01_freeze_and_activation_gate.php
 * العكس:   php database/migrations/2028_05_03_perm01_freeze_and_activation_gate_down.php
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
$run = function ($sql, $label) use ($conn) {
    if ($conn->query($sql)) { echo "   ✔ {$label}\n"; return true; }
    echo "   ✘ {$label}: " . $conn->error . "\n"; return false;
};

/* ⛔ **القادحُ يحتاج حسابًا إداريًّا**: `ems_migrator` بلا `SUPER` والتسجيلُ
     الثنائيُّ مُفعَّل، فـ`CREATE TRIGGER` يُردّ — وهذا **مقيسٌ لا مفترَض**: رُدَّت
     المحاولةُ الأولى بنصِّها. والقوادحُ الأربعةُ والثلاثون القائمةُ في القاعدةِ
     أُنشئت بهذا الحسابِ لا بحسابِ الهجرة. */
$adminU = ems_env('DB_ADMIN_USER') ?: $u;
$adminP = ems_env('DB_ADMIN_USER') ? ems_env('DB_ADMIN_PASS') : $p;
$adm = new mysqli($host, $adminU, $adminP, ems_env('DB_NAME'), $port);
if ($adm->connect_errno) { exit("تعذر الاتصال بحساب الادارة لانشاء القوادح: " . $adm->connect_error . "\n"); }
$adm->set_charset('utf8mb4');
$runT = function ($sql, $label) use ($adm) {
    if ($adm->query($sql)) { echo "   [OK] {$label}\n"; return true; }
    echo "   [XX] {$label}: " . $adm->error . "\n"; return false;
};

echo "══ ⓪ سجلّا التجميدِ والاعتماد ══════════════════════════════════════════\n";

$run("CREATE TABLE IF NOT EXISTS `gov_policy_freeze` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `scope_code` VARCHAR(40) NOT NULL COMMENT 'مدى التجميد — grants | profile_activation',
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `reason` VARCHAR(255) NOT NULL DEFAULT '',
        `doc_ref` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'الأمرُ الذي أوجبه',
        `opened_at` DATETIME NOT NULL DEFAULT current_timestamp(),
        `opened_by` INT NOT NULL DEFAULT 0,
        `closed_at` DATETIME DEFAULT NULL,
        `closed_by` INT DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_scope_open` (`scope_code`, `active`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='PERM-01 §3-① — تجميدُ البذرِ والمنحِ حتى إشعار'",
    'gov_policy_freeze');

$run("CREATE TABLE IF NOT EXISTS `gov_profile_activation_approval` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `profile_id` INT UNSIGNED NOT NULL,
        `version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        `approved_by` INT NOT NULL COMMENT 'الجهةُ المعتمِدة — سجلٌّ لا رسالة (FTRE-0062-ب)',
        `approved_at` DATETIME NOT NULL DEFAULT current_timestamp(),
        `reason` VARCHAR(255) NOT NULL DEFAULT '',
        `doc_ref` VARCHAR(60) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_profile_version` (`profile_id`, `version`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='PERM-01 §4 — لا تفعيلَ لمسودّةٍ بلا سجلِّ اعتماد'",
    'gov_profile_activation_approval');

/* ⛔ **والنافذُ اليومَ يُعتمَد أثرًا رجعيًّا لا يُسقَط**: 28 قالبًا نافذًا يحملها
     71 مستخدمًا؛ وإسقاطُها بحجّةِ «بلا اعتماد» يقطعهم. فيُكتب لها سجلُّ اعتمادٍ
     يسمّي سندَه: نفاذٌ سابقٌ للبوّابة — ويُعرف من غيرِه بـ`doc_ref`. */
$seeded = (int) $one("SELECT COUNT(*) FROM gov_profile_activation_approval");
if ($seeded === 0) {
    $conn->query("INSERT IGNORE INTO gov_profile_activation_approval
                    (profile_id, version, approved_by, reason, doc_ref)
                  SELECT profile_id, version, 0,
                         'نفاذٌ سابقٌ لبوّابةِ التفعيل — يُقيَّد ولا يُسقَط',
                         'PERM-01-GRANDFATHER'
                    FROM gov_role_profiles WHERE state = 'active'");
    echo "   ✔ اعتمادٌ رجعيٌّ للقوالبِ النافذة: " . $conn->affected_rows . "\n";
}

echo "\n══ ① القادحان ════════════════════════════════════════════════════════\n";
foreach (array('trg_perm01_grant_freeze', 'trg_perm01_profile_activate') as $t) {
    $adm->query("DROP TRIGGER IF EXISTS `{$t}`");
}
$trgOk = true;

$trgOk = $runT("CREATE TRIGGER `trg_perm01_grant_freeze` BEFORE INSERT ON `gov_authority_grants`
      FOR EACH ROW
      BEGIN
        IF EXISTS (SELECT 1 FROM `gov_policy_freeze`
                    WHERE `scope_code` = 'grants' AND `active` = 1) THEN
          SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'PERM-01: تجميد — لا تصدر منحة جديدة حتى اشعار';
        END IF;
      END", 'trg_perm01_grant_freeze — لا منحةَ جديدةٌ في التجميد') && $trgOk;

$trgOk = $runT("CREATE TRIGGER `trg_perm01_profile_activate` BEFORE UPDATE ON `gov_role_profiles`
      FOR EACH ROW
      BEGIN
        DECLARE src_n INT DEFAULT 0;
        DECLARE apr_n INT DEFAULT 0;
        IF NEW.`state` = 'active' AND OLD.`state` <> 'active' THEN
          IF EXISTS (SELECT 1 FROM `gov_policy_freeze`
                      WHERE `scope_code` = 'profile_activation' AND `active` = 1) THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: تجميد — لا تفعيل قالب حتى اشعار';
          END IF;
          SELECT COUNT(DISTINCT `seeded_from`) INTO src_n
            FROM `gov_profile_items` WHERE `profile_id` = NEW.`profile_id`;
          IF src_n > 1 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: قالب مبذور من اكثر من مصدر لا يفعل قبل مراجعة موثقة';
          END IF;
          SELECT COUNT(*) INTO apr_n FROM `gov_profile_activation_approval`
            WHERE `profile_id` = NEW.`profile_id` AND `version` = NEW.`version`;
          IF apr_n = 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'PERM-01: لا تفعيل لمسودة بلا سجل اعتماد (FTRE-0062)';
          END IF;
        END IF;
      END", 'trg_perm01_profile_activate — ثلاثةُ شروطٍ للتفعيل') && $trgOk;
if (!$trgOk) { exit("لم ينشا قادح — لا يعلن تجميد بلا بوابة تنفذه\n"); }

echo "\n══ ② إنفاذُ التجميد ═══════════════════════════════════════════════════\n";
foreach (array(
    'grants'             => 'PERM-01 §3-① — لا تصدر منحة جديدة حتى اغلاق مسار الاحتواء',
    'profile_activation' => 'PERM-01 §3-① — لا يفعل قالب جديد حتى بناء الهدف المعياري',
) as $scope => $why) {
    $conn->query("INSERT IGNORE INTO `gov_policy_freeze` (`scope_code`, `active`, `reason`, `doc_ref`)
                  VALUES ('" . $conn->real_escape_string($scope) . "', 1, '"
                  . $conn->real_escape_string($why) . "', 'PERM-01')");
    echo "   ✔ تجميدُ «{$scope}» نافذ\n";
}

echo "\n══ ③ الشواهد — والمنعُ يُختبَر لا يُفترَض ═══════════════════════════════\n";
$ok = true;

/* شاهدٌ سالب: منحةٌ جديدةٌ تُردّ. */
$conn->query("INSERT INTO gov_authority_grants (company_id, user_id, profile_id, source, issued_by, reason)
              VALUES (0, 907, 39, 'profile', 0, 'PERM01 probe')");
$e1 = (string) $conn->error;
$blocked1 = (strpos($e1, 'PERM-01') !== false);
echo '   منحةٌ جديدة: ' . ($blocked1 ? '✔ رُدَّت' : '✘ مرّت') . "\n";
$conn->query("DELETE FROM gov_authority_grants WHERE reason = 'PERM01 probe'");
if (!$blocked1) { $ok = false; }

/* شاهدٌ سالب: تفعيلُ مسودّةٍ يُردّ. */
$draft = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE state = 'draft' ORDER BY profile_id LIMIT 1");
if ($draft > 0) {
    /* ⛔ **والمسبارُ يستعيد أثرَه حتمًا**: تشغيلٌ سابقٌ بلا قادحٍ فعَّل مسودّةً
         فعلًا وترك تسعةً وعشرين قالبًا نافذًا. فالاستعادةُ لا تُعلَّق على نتيجةِ الفحص. */
    $before = (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id = {$draft}");
    $conn->query("UPDATE gov_role_profiles SET state = 'active' WHERE profile_id = {$draft}");
    $e2 = (string) $conn->error;
    $blocked2 = (strpos($e2, 'PERM-01') !== false);
    $after = (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id = {$draft}");
    if ($after !== $before) {
        $conn->query("UPDATE gov_role_profiles SET state = '" . $conn->real_escape_string($before)
                   . "' WHERE profile_id = {$draft}");
    }
    echo '   تفعيلُ مسودّةٍ #' . $draft . ': ' . ($blocked2 && $after === $before ? '✔ رُدَّ' : '✘ مرَّ') . "\n";
    if (!$blocked2 || $after !== $before) { $ok = false; }
}

/* ضابطٌ موجب: النافذُ يبقى نافذًا ولا يُمَسّ. */
$act = (int) $one("SELECT COUNT(*) FROM gov_role_profiles WHERE state = 'active'");
$cov = (int) $one("SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
                     JOIN gov_role_profiles pr ON pr.profile_id = g.profile_id AND pr.state = 'active'
                     JOIN users u ON u.id = g.user_id AND u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4
                    WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
echo "   ضابطٌ موجب: قوالبُ نافذة={$act} (المتوقَّع 28) · مغطَّون={$cov} (المتوقَّع 71) "
   . (($act === 28 && $cov === 71) ? "✔ لم يُمَسّ" : "✘ مُسَّ ما لا يخصُّنا") . "\n";
if ($act !== 28 || $cov !== 71) { $ok = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($ok ? "✔ تمّت — والتجميدُ نافذٌ ومُختبَر. رفعُه: UPDATE gov_policy_freeze SET active=0 WHERE scope_code=?\n"
                 : "✘ سقط شاهدٌ — راجعْ أعلاه\n");
