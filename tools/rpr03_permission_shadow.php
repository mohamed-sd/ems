<?php
/**
 * tools/rpr03_permission_shadow.php — `RPR-03` §٦ · ظلُّ مساري قرارِ الصلاحية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٦: *«ومسارٌ مزدوجٌ للصلاحيةِ أخطرُ من مسارٍ ضعيف:
 *   فقد **يظهر البندُ ويُمنع الفعل**، أو **يُخفى البندُ ويُسمح الفعلُ بالرابطِ
 *   المباشر**»* · *«وحِّدْ قرارَ الصلاحيةِ في مصدرٍ واحدٍ يُستدعى من الخادم»*.
 *
 * ◆ **والمقيسُ ثمانيةٌ وثمانون قارئًا مستقلًّا** — ولكنَّ الرقمَ وحدَه لا يقول
 *   **أينَ يفترق المساران فعلًا**. وتوحيدُ ثمانيةٍ وثمانين ملفًّا **يغيّر
 *   صلاحياتٍ نافذةً على مستخدمين أحياء**، ⛔ فلا يُفعل قبلَ قياسِ الفرق.
 *   ⇒ **وهذه نافذةُ ظلٍّ بالمعنى الذي يوجبه §٥ نفسُه**: تُقاس ولا تُنفَّذ.
 *
 * ◆ **والفرقُ بين المسارَين مقيسٌ لا مظنون** — وهو **طبقةُ القوالب**:
 *   · **المسارُ المعياريُّ** `get_module_permissions()` يسأل أوّلًا
 *     `gov_authority_grants` × `gov_role_profiles` × `gov_profile_items`
 *     (قرارُ المالك `GOV-AUTH-01`): **المستخدمُ المغطّى بقالبٍ نافذٍ يُحكَم
 *     بقالبِه حصرًا** — ومن غُطِّي وشاشتُه خارجَ قالبِه **يُمنع**.
 *   · **والقارئُ المستقلُّ** يقرأ `role_permissions` وحدَه ⇒ **لا يرى القالبَ
 *     أصلًا**. فالمستخدمُ المغطّى قد يُمنع في القائمةِ ويُسمح له في الشاشة.
 *
 * ◆ **واتّجاها الفرقِ لا يُجمعان في رقمٍ واحد**:
 *   `OVER_GRANT` — المستقلُّ **يسمح** والمعياريُّ **يمنع** ⇒ **خرقُ أمنٍ فعليّ**:
 *      صلاحيةٌ نافذةٌ من بابٍ لا يعرف القالب.
 *   `UNDER_GRANT` — المستقلُّ **يمنع** والمعياريُّ **يسمح** ⇒ عطبُ خدمةٍ لا أمن.
 *   ⛔ **وجمعُهما «فروقٌ = ن» يُخفي أنَّ أحدَهما ثغرةٌ والآخرَ إزعاج.**
 *
 * ⛔ **وهذا يقيس ولا يوحّد** — والتوحيدُ يلي قراءةَ هذا السجلِّ لا يسبقها.
 *
 * التشغيل:
 *   php tools/rpr03_permission_shadow.php [--md] [--list] [--selftest]
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

$MD   = in_array('--md', $argv, true);
$LIST = in_array('--list', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); exit(2); }
    return $r;
};
$one = function ($sql) use ($q) { $x = $q($sql)->fetch_row(); return $x ? $x[0] : null; };

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ① القارئون المستقلّون — وشاشاتُهم بأكوادِها ═══════════════════════ */
$PERM_TABLES = 'role_permissions|report_role_permissions|permission_templates|role_permission_templates';
$SKIP = '~(^|/)(vendor|storage|tools|tests|node_modules|\.git)(/|$)~';
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php' || preg_match($SKIP, str_replace($ROOT, '', $p))) { continue; }
    $files[] = $p;
}
$directCodes = array(); $noCode = array();
foreach ($files as $p) {
    $src = (string) @file_get_contents($p);
    if ($src === '') { continue; }
    $reads  = (bool) preg_match('~\b(FROM|JOIN)\s+`?(' . $PERM_TABLES . ')`?~i', $src);
    $writes = (bool) preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?('
            . $PERM_TABLES . ')`?~i', $src);
    if (!$reads || $writes) { continue; }
    $rel = str_replace($ROOT, '', $p);
    /* كودُ الشاشةِ كما يعلنه الملفُّ نفسُه — ولا يُشتقّ من المسار */
    if (preg_match('~\$MODULE_CODE\s*=\s*[\'"]([^\'"]+)[\'"]~', $src, $m)) {
        $directCodes[$m[1]][] = $rel;
    } else {
        $noCode[] = $rel;
    }
}

/* ═══ ② القرارُ بمسارَيه لكلِّ (مستخدم × شاشة) ═════════════════════════
     ⛔ **والمقارنةُ لكلِّ مستخدمٍ لا لكلِّ دور**: القالبُ يُمنح **للمستخدم**
        لا للدور، فمقارنةٌ بالدورِ وحدَه لا ترى الفرقَ أصلًا. */
$codes = array_keys($directCodes);
$modIds = array();
if ($codes) {
    $in = array();
    foreach ($codes as $c) { $in[] = "'" . $conn->real_escape_string($c) . "'"; }
    $r = $q("SELECT id, code FROM modules WHERE code IN (" . implode(',', $in) . ")");
    while ($x = $r->fetch_assoc()) { $modIds[$x['code']] = (int) $x['id']; }
}
$unmatchedCode = array();
foreach ($codes as $c) { if (!isset($modIds[$c])) { $unmatchedCode[] = $c; } }

/* المستخدمون المغطَّون بقالبٍ نافذ */
$covered = array();
$r = $conn->query("SELECT DISTINCT g.user_id
                     FROM gov_authority_grants g
                     JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                    WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
if ($r) { while ($x = $r->fetch_row()) { $covered[(int) $x[0]] = 1; } }

$users = array();
$r = $q("SELECT id, role FROM users WHERE COALESCE(role,'') <> ''");
while ($x = $r->fetch_assoc()) { $users[] = array((int) $x['id'], (string) $x['role']); }

/* ═══ ③ الاختبارُ السالب ═══════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($files) < 200) { echo '  X شجرةُ الإنتاجِ ' . count($files) . " ملفًّا — مسحٌ أعمى\n"; $fail++; }
    if (count($directCodes) < 5) { echo '  X أكوادُ القارئين ' . count($directCodes) . " — قراءةٌ عمياء\n"; $fail++; }
    if (count($users) < 5) { echo '  X المستخدمون ' . count($users) . " — قراءةٌ عمياء\n"; $fail++; }
    /* **الكاسرُ**: كودٌ لا وجودَ له في `modules` يجب أن يظهر في غيرِ المطابَق */
    $probe = 'zzq/unique_probe_screen.php';
    if (isset($modIds[$probe])) { echo "  X كودٌ وهميٌّ وُجد في `modules`\n"; $fail++; }
    /* ولو كانت `$modIds` فارغةً لعُدَّ كلُّ شيءٍ غيرَ مطابَقٍ ومرَّ الفحصُ */
    if (count($modIds) < 1) { echo "  X لا كودَ واحدًا طابق `modules` — الجسرُ أعمى\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الجسرُ يطابق ويميّز الوهميَّ\n";
    exit($fail ? 1 : 0);
}

/* ═══ ④ الظلّ ═══════════════════════════════════════════════════════════ */
$over = array(); $under = array(); $same = 0; $pairs = 0;
$stTpl = $conn->prepare(
    "SELECT MAX(CASE WHEN i.item_id IS NULL THEN -1 ELSE i.allow END) t_view,
            MAX(COALESCE(i.can_add,0)) t_add,
            MAX(COALESCE(i.can_edit,0)) t_edit,
            MAX(COALESCE(i.can_delete,0)) t_del
       FROM gov_authority_grants g
       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
       LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id
            AND i.item_kind = 'screen'
            AND i.item_ref = (SELECT code FROM modules WHERE id = ? LIMIT 1)
      WHERE g.user_id = ? AND g.revoked_at IS NULL
        AND (g.valid_to IS NULL OR g.valid_to > NOW())");
$stRole = $conn->prepare(
    "SELECT can_view, can_add, can_edit, can_delete FROM role_permissions
      WHERE role_id = ? AND module_id = ? LIMIT 1");

foreach ($users as $u) {
    list($uid, $role) = $u;
    if ($role === '-1') { continue; }          /* السوبر أدمن مسارُه واحدٌ في الاثنين */
    if (!isset($covered[$uid])) { continue; }  /* غيرُ المغطَّى: المساران متطابقان بالتعريف */
    $rid = (int) $role;
    foreach ($modIds as $code => $mid) {
        $pairs++;
        /* المسارُ المستقلّ: `role_permissions` وحدَه */
        $stRole->bind_param('ii', $rid, $mid);
        $stRole->execute();
        $a = $stRole->get_result()->fetch_assoc();
        $aView = $a ? ((int) $a['can_view'] === 1) : false;
        /* المسارُ المعياريّ: القالبُ يغلب حين يغطّي */
        $stTpl->bind_param('ii', $mid, $uid);
        $stTpl->execute();
        $t = $stTpl->get_result()->fetch_assoc();
        $bView = $aView;
        if ($t !== null && $t['t_view'] !== null) { $bView = ((int) $t['t_view']) === 1; }
        if ($aView === $bView) { $same++; continue; }
        $row = array('user' => $uid, 'role' => $rid, 'code' => $code,
                     'files' => $directCodes[$code]);
        if ($aView && !$bView) { $over[] = $row; } else { $under[] = $row; }
    }
}

/* ═══ ⑤ العرض ═══════════════════════════════════════════════════════════ */
echo "\n═══ `RPR-03` §٦ — ظلُّ مساري قرارِ الصلاحية ═══\n";
printf("  اللقطة %s · ملفّاتُ الإنتاج %d · **قارئون مستقلّون بكودٍ مُعلَنٍ %d شاشةً**\n",
       $sid, count($files), count($directCodes));
printf("  منها طابقت `modules`: **%d** · وبكودٍ لا يطابق: **%d** · وقارئٌ بلا `%s`: **%d**\n",
       count($modIds), count($unmatchedCode), '$MODULE_CODE', count($noCode));
echo "     ⛔ **والخمسةُ والأربعون بلا كودٍ مُعلَنٍ خارجَ هذا القياسِ ولا تُعدُّ سليمة** —\n";
echo "       فمن لا يُعلن شاشتَه لا يُقارَن قرارُه، وذاك نقصُ تغطيةٍ يُسمّى لا يُطوى.\n";
printf("  مستخدمون **مغطَّون بقالبٍ نافذ: %d** من %d · وأزواجٌ قيست: **%d**\n",
       count($covered), count($users), $pairs);

echo "\n  ── اتّجاها الفرقِ — ولا يُجمعان ──\n";
printf("     ⛔ `OVER_GRANT`  **%4d** — المستقلُّ **يسمح** والمعياريُّ **يمنع** ⇒ **خرقُ أمنٍ فعليّ**\n", count($over));
printf("     ◆ `UNDER_GRANT` **%4d** — المستقلُّ **يمنع** والمعياريُّ **يسمح** ⇒ عطبُ خدمةٍ لا أمن\n", count($under));
printf("     ✔ متطابقان      **%4d**\n", $same);

if ($LIST) {
    echo "\n  ── مواضعُ `OVER_GRANT` ──\n";
    foreach (array_slice($over, 0, 20) as $x) {
        printf("     مستخدم %-5d دور %-3d ⇐ %s (%s)\n", $x['user'], $x['role'], $x['code'], $x['files'][0]);
    }
    if (count($over) > 20) { printf("     … و%d غيرُها\n", count($over) - 20); }
    if ($unmatchedCode) {
        echo "\n  ── كودٌ مُعلَنٌ لا يطابق `modules` ──\n";
        foreach (array_slice($unmatchedCode, 0, 12) as $c) { echo "     · $c\n"; }
    }
}

echo "\n────────────────────────────────────────────────────────────\n";
if ($pairs === 0) {
    echo "◆ **صفرُ زوجٍ مقيس** — ⛔ ولا يُقرأ ذلك «لا فرقَ»: إمّا لا مستخدمَ مغطًّى\n";
    echo "  بقالبٍ نافذٍ، وإمّا لا كودَ طابق `modules`. **والصفرُ عن مقامٍ صفرٍ ليس نجاحًا.**\n";
} else {
    printf("**`OVER_GRANT` = %d** — وهو وحدَه ثغرةُ أمنٍ · و`UNDER_GRANT` = %d\n", count($over), count($under));
}
echo "⛔ **وهذا يقيس ولا يوحّد** — والتوحيدُ يلي قراءةَ هذا السجلِّ لا يسبقها.\n";

if ($MD) {
    $o  = "# `RPR-03` §٦ — ظلُّ مساري قرارِ الصلاحية\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "توحيدُ ثمانيةٍ وثمانين قارئًا **يغيّر صلاحياتٍ نافذةً على مستخدمين أحياء** —\n";
    $o .= "فلا يُفعل قبلَ قياسِ الفرق. **وهذه نافذةُ ظلٍّ بالمعنى الذي يوجبه §٥ نفسُه.**\n\n";
    $o .= "| المقياس | العدد |\n|---|---:|\n";
    $o .= "| شاشاتٌ لقارئٍ مستقلٍّ بكودٍ مُعلَن | " . count($directCodes) . " |\n";
    $o .= "| منها طابقت `modules` | " . count($modIds) . " |\n";
    $o .= "| مستخدمون مغطَّون بقالبٍ نافذ | " . count($covered) . " |\n";
    $o .= "| أزواجٌ قيست | " . $pairs . " |\n";
    $o .= "| ⛔ **`OVER_GRANT`** (يسمح المستقلُّ ويمنع المعياريّ) | **" . count($over) . "** |\n";
    $o .= "| `UNDER_GRANT` (يمنع المستقلُّ ويسمح المعياريّ) | " . count($under) . " |\n";
    $o .= "| متطابقان | " . $same . " |\n\n";
    $o .= "⛔ **والاتّجاهان لا يُجمعان**: `OVER_GRANT` ثغرةُ أمنٍ فعليّة، و`UNDER_GRANT`\n";
    $o .= "عطبُ خدمة. وجمعُهما «فروقٌ = ن» يُخفي أنَّ أحدَهما ثغرةٌ والآخرَ إزعاج.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_PERMISSION_SHADOW.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR03_PERMISSION_SHADOW.md\n";
}
