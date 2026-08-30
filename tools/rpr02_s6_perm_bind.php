<?php
/**
 * tools/rpr02_s6_perm_bind.php — `RPR-02` §٦ · **س٦** الظهورُ بالصلاحيّةِ لا بلا فحص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦ الخطوةُ السادسة: *«صحِّحِ الظهورَ بالصلاحيّةِ —
 *   ولا تُضحَّى الصلاحيّةُ لأجلِ ترتيبٍ أجمل»*. والمقيسُ **موضعان** على مسارٍ
 *   واحد.
 *
 * ◆ **والعطبُ ثقبُ تجاوزٍ لا فرقُ تنسيق**: `getUnifiedNavItems` تنصُّ
 *   `permission_code IS NULL` ⇒ **ظهورٌ بلا فحص**. فبندٌ بلا رمزٍ **يُرى بلا
 *   منح** — والمسارُ نفسُه مفحوصٌ عند بقيّةِ الأدوار. ⇒ **صفٌّ واحدٌ يُقرأ
 *   بقاعدتَين.**
 *
 * ◆ **والرمزُ يُنسخ من إخوتِه على المسارِ نفسِه** ⛔ **لا يُخترع**: إن لم يكن
 *   للمسارِ أخٌ حيٌّ مفحوصٌ فلا سندَ للرمزِ — **ويُوقَف ويُسمّى**.
 *
 * ⛔ **وسدُّ الثقبِ فعلٌ أمنيٌّ · والمنحُ فعلُ مالك**: من لا `can_view` له
 *   **يختفي عنه البندُ** بعدَ السدّ — وهو نطقُ نموذجِ الصلاحيةِ المغلَقِ
 *   افتراضًا. **والأثرُ يُقاس بعددِه ويُعلَن**، ⛔ **ولا يُلفَّق منحٌ ليبقى
 *   البندُ ظاهرًا** ([[screen-grant-four-locks]]).
 *
 * التشغيل:
 *   php tools/rpr02_s6_perm_bind.php [--apply] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا يُسدُّ ثقبٌ بلا لقطة\n"); }

/* ═══ ① الرمزُ السائدُ لكلِّ مسارٍ — من إخوتِه الأحياءِ المفحوصين ═════════ */
$peer = array();
$r = $conn->query("SELECT LOWER(TRIM(BOTH '/' FROM route)) rt, permission_code, module_id, COUNT(*) n
                     FROM nav_items
                    WHERE active = 1 AND permission_code IS NOT NULL AND permission_code <> ''
                    GROUP BY rt, permission_code, module_id ORDER BY n DESC");
while ($r && ($x = $r->fetch_assoc())) {
    if (!isset($peer[$x['rt']])) {
        $peer[$x['rt']] = array('code' => $x['permission_code'], 'mod' => $x['module_id'], 'n' => (int) $x['n']);
    }
}

/* ═══ ② البنودُ الحيّةُ بلا رمز ═══════════════════════════════════════════ */
$plan = array(); $held = array();
$r = $conn->query("SELECT id, role_id, route, label_ar, module_id FROM nav_items
                    WHERE active = 1 AND (permission_code IS NULL OR permission_code = '')");
while ($r && ($x = $r->fetch_assoc())) {
    $rt = strtolower(trim((string) $x['route'], '/'));
    if (!isset($peer[$rt])) { $held[] = array('it' => $x, 'why' => 'لا أخَ حيًّا مفحوصًا على المسارِ نفسِه — فلا سندَ للرمز'); continue; }
    if ($x['module_id'] === null || (int) $x['module_id'] <= 0) {
        $held[] = array('it' => $x, 'why' => 'بلا `module_id` — و`chk_nav_items_module_or_code` تمنع الرمزَ بلا وحدة');
        continue;
    }
    $g = (int) $conn->query("SELECT COUNT(*) FROM role_permissions
                              WHERE role_id = " . (int) $x['role_id'] . "
                                AND module_id = " . (int) $x['module_id'] . " AND can_view = 1")->fetch_row()[0];
    $plan[] = array('it' => $x, 'rt' => $rt, 'peer' => $peer[$rt], 'grant' => $g > 0);
}

/* ═══ ③ الاختبارُ السالب ════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    $N = (int) $conn->query("SELECT COUNT(*) FROM nav_items WHERE active = 1")->fetch_row()[0];
    if ($N < 100) { echo "  X المقامُ الحيُّ $N — القراءةُ لم تتمّ\n"; $fail++; }
    if (count($peer) < 50) { echo '  X خريطةُ الرموزِ ' . count($peer) . " مسارًا — مصفاةٌ عمياء\n"; $fail++; }
    /* **الكاسرُ ①**: ⛔ لا رمزَ يُكتب بلا أخٍ حيٍّ يسنده */
    foreach ($plan as $p) {
        if (!isset($peer[$p['rt']]) || $peer[$p['rt']]['n'] < 1) { echo "  X خطّةٌ بلا سندٍ من أخ\n"; $fail++; break; }
    }
    /* **الكاسرُ ②**: مسارٌ وهميٌّ لا يجد سندًا ([[measure-token-must-exist]]) */
    if (isset($peer['zzq/absent_route_probe.php'])) { echo "  X مسارٌ وهميٌّ وُجد في خريطةِ الرموز\n"; $fail++; }
    /* **الكاسرُ ③**: ⛔ ولا صفَّ في الخطّةِ يحمل رمزًا سلفًا */
    foreach ($plan as $p) {
        if (trim((string) $p['it']['module_id']) === '') { echo "  X خطّةٌ بلا وحدة\n"; $fail++; break; }
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — لا رمزَ بلا أخٍ يسنده، ولا وحدةَ وهميّة\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-02` §٦ · **س٦** — الظهورُ بالصلاحيّةِ لا بلا فحص ═══\n";
printf("  اللقطة %s · بنودٌ حيّةٌ **بلا رمزِ صلاحية** %d · ⇒ **خطّةٌ %d** · موقوفٌ %d\n",
       ($sid !== '' ? $sid : 'DRY'), count($plan) + count($held), count($plan), count($held));
$loses = 0;
foreach ($plan as $p) {
    printf("    · دور %-3d %-30s ⇐ رمزُ إخوتِه «%s» (وحدة %s · %d دورًا مفحوصًا) · منحٌ قائم: %s\n",
           $p['it']['role_id'], $p['it']['route'], $p['peer']['code'], $p['peer']['mod'], $p['peer']['n'],
           $p['grant'] ? 'نعم' : '⚠ **لا**');
    if (!$p['grant']) { $loses++; }
}
foreach ($held as $h) { echo "    ⛔ موقوفٌ: دور {$h['it']['role_id']} {$h['it']['route']} — {$h['why']}\n"; }
if ($loses) {
    printf("\n  ⚠ **وأثرُ السدِّ مقيسٌ سلفًا**: %d موضعًا **بلا منحٍ قائم** ⇒ يختفي البندُ عن دورِه\n", $loses);
    echo "     ⛔ **والمنحُ فعلُ مالكٍ لا فعلُنا** — يُسمّى بندًا مستقلًّا ولا يُلفَّق.\n";
}
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ ⑤ التطبيق ═════════════════════════════════════════════════════════ */
if ($APPLY) {
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $p) {
        $wit = 'س٦ · ثقبُ ظهورٍ بلا فحص: `permission_code IS NULL` تعني في `getUnifiedNavItems` ظهورًا بلا منح. '
             . 'والرمزُ منسوخٌ من إخوتِه الأحياءِ على المسارِ نفسِه (' . $p['peer']['n'] . ' دورًا مفحوصًا · وحدة '
             . $p['peer']['mod'] . ') ⛔ لا مخترَعًا. ومنحُ الدورِ قائمٌ: ' . ($p['grant'] ? 'نعم' : 'لا — فيختفي البندُ حتى يمنحَ المالك');
        $sql = "INSERT INTO `repair01_nav_perm_bind`
                (nav_item_id, role_id, route, module_id, after_code, peer_roles, had_grant, witness, snapshot_id, applied_at)
                VALUES (" . (int) $p['it']['id'] . ", " . (int) $p['it']['role_id'] . ", '" . $e($p['it']['route']) . "', "
              . (int) $p['it']['module_id'] . ", '" . $e($p['peer']['code']) . "', " . (int) $p['peer']['n'] . ", "
              . ($p['grant'] ? 1 : 0) . ", '" . $e($wit) . "', '" . $e($sid) . "', NOW())
                ON DUPLICATE KEY UPDATE after_code = VALUES(after_code), witness = VALUES(witness), applied_at = NOW()";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ تعذّر التسجيل: {$conn->error}\n"); }
        if (!$conn->query("UPDATE `nav_items` SET `permission_code` = '" . $e($p['peer']['code'])
                        . "' WHERE `id` = " . (int) $p['it']['id'])) {
            $conn->query('ROLLBACK'); exit("✘ تعذّر السدّ: {$conn->error}\n");
        }
        $n++;
    }
    $conn->query('COMMIT');
    printf("\n  ✔ **سُدَّ %d ثقبًا** — والظهورُ صار مفحوصًا\n", $n);
    printf("  ⚠ ومنها **%d** بلا منحٍ قائم ⇒ **بندٌ مسمًّى لقرارِ المالك**: منحُ `can_view` أو تأكيدُ المنع\n", $loses);
}
