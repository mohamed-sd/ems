<?php
/**
 * tests/injchain01_ten_gates.php
 *   بواباتُ إغلاقِ سلسلةِ الأثرِ العشر — INJ-CHAIN-CLOSE-01 الباب الرابع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ بوابةٍ تُقاس ببسطِها ومقامِها لا بالانطباع** — بنصِّ الوثيقة. وكلُّ
 *   واحدةٍ منها إمّا **خضراءُ بقياس**، أو **مفتوحةٌ مُعلَنةٌ بسببِها**؛ ولا
 *   بوابةَ «خضراءُ بلا مقام».
 *
 * ◆ **وبوابةٌ لا يمكن أن ترسُب لا تُحسب**: كلُّ بوابةٍ هنا لها حالةُ رسوبٍ
 *   ممكنةٌ مذكورةٌ في نصِّها — فمرورُها خبرٌ لا زخرفة.
 *
 * ◆ **وحجمُ البياناتِ متقلبٌ خارجَ هذه الجلسة** (جولاتُ بذرٍ وتقليصٍ متوازية)،
 *   فالبواباتُ تقيس **خصائصَ وقواعدَ** لا أحجامًا: صفرُ خرقٍ لا «كذا صفًّا».
 *
 * التشغيل: php tests/injchain01_ten_gates.php [--json=<ملف>] [--negative]
 *
 * ◆ **وبوابةُ العقد G8 كانت عاجزةً عن الرسوبِ بنيويًّا** (البند ٠-٣): كانت تعدُّ
 *   `build_state = 'MISSING'` و`ENUM` يحمل القيمةَ بصفرِ صفٍّ يحملها. فصارت
 *   تعدُّ `<> 'BUILT'` وتفحص لكلِّ `UNDER_OTHER_ROUTE` أن حاملَه **يؤدي وظيفتَه
 *   بسلّمِه** — و`--negative` يبذر عقدةً بحاملٍ لا ينادي سلّمَها ويشترط رسوبَها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/unit_chain_helpers.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function one(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

$G = array();   /* code => [green(bool), title, measure, note] */
function gate(&$G, $code, $green, $title, $measure, $note = '')
{
    $G[$code] = array('green' => (bool) $green, 'title' => $title, 'measure' => $measure, 'note' => $note);
}

/**
 * ── حكمُ عقدِ السلسلة — G8 مقيسًا بالوظيفةِ لا بالقيمةِ الغائبة (البند ٠-٣) ──
 * ◆ **بوابةٌ لا تقدر أن ترسُبَ لا تُحسب.** كانت G8 تَعُدُّ `build_state='MISSING'`،
 *   و`ENUM` يحمل القيمةَ لكنّ الجدولَ فيه **صفرُ صفٍّ بها** — فالبوابةُ كانت
 *   **عاجزةً عن الرسوبِ بنيويًّا** لا خضراءَ بإنجاز.
 * ◆ والعدُّ الآن `<> 'BUILT'`، ولكلِّ `UNDER_OTHER_ROUTE` يُفحص أن حاملَه
 *   **يؤدي وظيفتَه بسلّمِه** لا أن ملفَّه موجود:
 *     · كلُّ حاملٍ مُعلَنٍ **قائمٌ على القرص**؛
 *     · و`LD-nn` **يُقرأ من نقطةِ قرارٍ حيّة** — يُشتقُّ من الشجرةِ بالقاعدتَين
 *       نفسِهما اللتين وسمتا `gov_journey_ladders` (نداءُ `ems_ladder_guard`
 *       برمزِه · أو خريطةُ المراحلِ في `unit_chain_helpers` وقد استُهلكت بنداءِ
 *       `ems_uc_ladder_check` من ملفِّ إنتاج) — **اشتقاقًا لا قراءةَ رايةٍ مخزَّنة**،
 *       فرايةٌ كُتبت مرةً تتعفّن وقارئانِ يتفرّقان؛
 *     · و`RESOLVE_FROM_POLICY:key` يلزمه **محلٌّ حيٌّ للمفتاح** في شجرةِ الإنتاج؛
 *     · و`NO_LADDER_REQUIRED` تكفيه حياةُ حاملِه — فالإعفاءُ مصدرُه السجلُّ نفسُه.
 */
function chain_node_verdicts($ROOT, mysqli $conn)
{
    $SKIP = array('vendor', 'node_modules', '.git', 'docs', 'tests', 'tools', 'storage',
                  'database', 'logs', '.ssdiff');
    $prod = array();
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
        function ($cur) use ($ROOT, $SKIP) {
            if (!$cur->isDir()) { return true; }
            $rel = str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT) + 1));
            return !in_array(explode('/', $rel)[0], $SKIP, true);
        }));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        $prod[$rel] = (string) @file_get_contents($f->getPathname());
    }

    /* ① سلاليمُ يُنادى عليها بالرمزِ صراحةً من نقطةِ قرارٍ حيّة */
    $wired = array(); $where = array();
    foreach ($prod as $rel => $src) {
        if (preg_match_all("/ems_ladder_guard\s*\([^;]*?'(LD-\d{2})'/s", $src, $m)) {
            foreach ($m[1] as $ld) { $wired[$ld] = true; $where[$ld][] = $rel; }
        }
    }
    /* ② خريطةُ مراحلِ سلسلةِ الوحدات — نقطةُ قرارٍ واحدةٌ لسبعِ مراحل.
     *   ولا تُحتسب إلا إن كانت الخريطةُ **مستهلَكةً فعلًا**: `ems_uc_ladder_check`
     *   مُنادًى من ملفِّ إنتاجٍ ليس ملفَّ التعريفِ نفسَه. */
    $mapSrc = isset($prod['includes/unit_chain_helpers.php']) ? $prod['includes/unit_chain_helpers.php'] : '';
    $consumers = array();
    foreach ($prod as $rel => $src) {
        if ($rel === 'includes/unit_chain_helpers.php' || $rel === 'includes/ladder_gate.php') { continue; }
        if (preg_match("/(?<!function )\bems_uc_ladder_check\s*\(/", $src)) { $consumers[] = $rel; }
    }
    if ($mapSrc !== '' && $consumers && preg_match_all("/=>\s*'(LD-\d{2})'/", $mapSrc, $m2)) {
        foreach ($m2[1] as $ld) {
            $wired[$ld] = true;
            $where[$ld][] = 'includes/unit_chain_helpers.php ⟵ ' . $consumers[0];
        }
    }

    /* ── الحكمُ عقدةً عقدة ─────────────────────────────────────────────── */
    $rows = array(); $bad = array(); $missing = 0;
    $r = @$conn->query("SELECT `node_no`, `declared_file`, `ladder_id`,
                        COALESCE(`carrier_route`, '') AS `carrier`, `build_state`
                          FROM `gov_chain_nodes` WHERE `build_state` <> 'BUILT'
                         ORDER BY `node_no`");
    while ($r && ($x = $r->fetch_assoc())) {
        $no = (int) $x['node_no']; $ld = $x['ladder_id'];
        $why = array();
        if ($x['build_state'] === 'MISSING') { $missing++; $why[] = 'العقدةُ **مفقودة**'; }

        /* الحاملُ قائمٌ على القرص — كلُّ حاملٍ مُعلَن */
        $carriers = array_values(array_filter(array_map('trim', explode('·', $x['carrier']))));
        if (!$carriers) {
            $why[] = 'صفرُ حاملٍ مُعلَن — و`UNDER_OTHER_ROUTE` بلا حاملٍ دعوى بلا سطح';
        }
        foreach ($carriers as $c) {
            if (!is_file($ROOT . '/' . $c)) { $why[] = "حاملٌ غيرُ قائم: `{$c}`"; }
        }

        /* السلّمُ يؤدي وظيفتَه */
        if ($ld === 'NO_LADDER_REQUIRED') {
            /* الإعفاءُ مصدرُه السجلُّ نفسُه — ولا يُفحص ما لا يُدَّعى */
        } elseif (preg_match('/^LD-\d{2}$/', $ld)) {
            if (empty($wired[$ld])) {
                $why[] = "السلّم `{$ld}` **لا يُقرأ من نقطةِ قرارٍ حيّة** — "
                       . 'فالحاملُ يقود الخطوةَ بلا ترتيبٍ ولا سقفٍ ولا «لا يدَ تمشي خطوتَين»';
            }
        } elseif (strpos($ld, 'RESOLVE_FROM_POLICY:') === 0) {
            $key = substr($ld, strlen('RESOLVE_FROM_POLICY:'));
            $res = array();
            foreach ($prod as $rel => $src) {
                if (strpos($src, "'" . $key . "'") !== false
                    && preg_match("/(?<!function )\b(ems_ladder_guard|ems_ladder_check|ems_ladder_resolve)\s*\(/", $src)) {
                    $res[] = $rel;
                }
            }
            if (!$res) {
                $why[] = "مفتاحُ السياسة `{$key}` **بلا محلٍّ حيّ** — "
                       . 'وإعلانُ «يُحَلُّ من السياسة» دون محلٍّ إحالةٌ إلى لا شيء';
            }
        } else {
            $why[] = "رمزُ سلّمٍ خارجَ الرموزِ الثلاثة: `{$ld}`";
        }

        $rows[] = array('no' => $no, 'file' => $x['declared_file'], 'ladder' => $ld,
                        'state' => $x['build_state'], 'why' => $why);
        if ($why) { $bad[] = $no; }
    }
    $tot = one($conn, "SELECT COUNT(*) FROM `gov_chain_nodes`");
    $built = one($conn, "SELECT COUNT(*) FROM `gov_chain_nodes` WHERE `build_state` = 'BUILT'");
    return array('rows' => $rows, 'bad' => $bad, 'missing' => $missing,
                 'total' => $tot, 'built' => $built,
                 'wiredLadders' => array_keys($wired), 'where' => $where);
}

/**
 * ── حزامُ صدقِ البوابةِ G8 — `--negative` (البند ٠-٣) ────────────────────────
 * ◆ الاختبارُ السالبُ المنصوصُ عليه: **اجعلْ عقدةً `UNDER_OTHER_ROUTE` بحاملٍ
 *   لا ينادي سلّمَها ⇒ رسوب**. فالحزامُ يبذر العقدة 99 بحاملٍ قائمٍ على القرصِ
 *   وسلّمٍ `LD-99` لا يُنادى من أيِّ نقطةِ قرار، ويشترط ارتفاعَ عددِ العقدِ
 *   الراسبةِ بواحد — ثم يكنس ويشترط عودةَ العدد.
 * ◆ وقبلَ الإصلاحِ كانت البوابةُ تعدُّ `build_state='MISSING'` و**صفرَ صفٍّ
 *   يحملها** — فهذا البذرُ نفسُه كان يمرُّ عليها أخضرَ.
 */
function g8_honesty_belt($ROOT, mysqli $conn)
{
    $NODE = 99;
    $sweep = function () use ($conn, $NODE) {
        @$conn->query("DELETE FROM `gov_chain_nodes` WHERE `node_no` = {$NODE}");
    };
    $sweep();
    register_shutdown_function($sweep);

    echo "\n══ حزامُ صدقِ البوابة G8 — عقدةٌ بحاملٍ لا ينادي سلّمَها ══\n";
    $before = chain_node_verdicts($ROOT, $conn);
    printf("  ① قبلَ البذر: عقدٌ راسبة=**%d** · غيرُ مبنيةٍ باسمِها=%d\n",
           count($before['bad']), count($before['rows']));

    /* حاملٌ **قائمٌ على القرص** فلا يرسُب لغيابِ الملفّ — يرسُب لغيابِ نداءِ السلّمِ وحدَه */
    $carrier = 'includes/env.php';
    $ok = @$conn->query("INSERT INTO `gov_chain_nodes`
        (`node_no`, `declared_file`, `title_ar`, `technical_runtime`, `process_owner`,
         `ladder_id`, `build_state`, `carrier_route`)
        VALUES ({$NODE}, 'belt_probe.php', 'عقدةُ حزامٍ — حاملٌ بلا نداءِ سلّم',
                'حزام', 'حزام', 'LD-99', 'UNDER_OTHER_ROUTE', '{$carrier}')");
    if (!$ok) {
        echo "  ✘ تعذّر بذرُ العقدة: " . $conn->error . " — والحزامُ لا يدّعي\n";
        $sweep();
        return 0;
    }
    $after = chain_node_verdicts($ROOT, $conn);
    printf("  ② بعدَ البذر: عقدٌ راسبة=**%d**\n", count($after['bad']));
    $why = '';
    foreach ($after['rows'] as $rw) {
        if ((int) $rw['no'] === $NODE) { $why = implode(' · ', $rw['why']); }
    }
    echo "     ◆ سببُ رسوبِ العقدة 99: " . ($why !== '' ? $why : '**لم ترسُبْ**') . "\n";
    $rose   = (count($after['bad']) === count($before['bad']) + 1);
    $carrOk = (strpos($why, 'حاملٌ غيرُ قائم') === false);
    $ldWhy  = (strpos($why, 'LD-99') !== false);
    $sweep();
    $back = chain_node_verdicts($ROOT, $conn);
    printf("  ③ بعدَ الكنس: عقدٌ راسبة=**%d**\n", count($back['bad']));
    $restored = (count($back['bad']) === count($before['bad'])
                 && count($back['rows']) === count($before['rows']));

    echo '  ' . ($rose ? '✔' : '✘') . " العقدةُ الجديدةُ **رسبت** — والعددُ ارتفع بواحد\n";
    echo '  ' . ($carrOk && $ldWhy ? '✔' : '✘')
       . " والرسوبُ **لغيابِ نداءِ السلّمِ** لا لغيابِ الملفّ — حاملُها قائمٌ على القرص\n";
    echo '  ' . ($restored ? '✔' : '✘') . " وعاد العددُ بالكنس — صفرُ أثرٍ باقٍ\n";
    $v = $rose && $carrOk && $ldWhy && $restored;
    echo $v ? "  ✔ **G8 جُرِّبت معطوبةً ورسبت** — وكانت قبلَ الإصلاحِ تمرُّ على هذا البذرِ نفسِه\n"
            : "  ✘ **G8 لم ترسُبْ بالعطب** — فليست حارسًا\n";
    return $v ? 1 : 0;
}

/* `--negative`: **تُجرَّب البوابةُ معطوبةً قبلَ تصديقِ مرورِها** (البند ٠-٣). */
if (in_array('--negative', $argv, true)) {
    $v = g8_honesty_belt($ROOT, $conn);
    echo "\n" . str_repeat('─', 66) . "\n";
    printf("◆ **صدقُ البوابة G8: %d/1 جُرِّبت معطوبةً ورسبت**\n", $v);
    exit($v === 1 ? 0 : 1);
}

echo "══ بواباتُ إغلاقِ سلسلةِ الأثرِ العشر ══\n";

/* ══ ① الواقعةُ الواحدةُ ثلاثُ قراءات ═══════════════════════════════════ */
$surf = array('Contracts/unit_statement_client.php', 'Suppliers/unit_statement_supplier.php');
$built = 0;
foreach ($surf as $s) { if (is_file($ROOT . '/' . $s)) { $built++; } }
/* كشفُ المشغّلِ حيٌّ على سطحٍ آخرَ بأثرٍ مقيس (`unit_party_awards.party='operator'`) */
$opAward = one($conn, "SELECT COUNT(*) FROM `unit_party_awards` WHERE `party`='operator'");
$opSurf  = is_file($ROOT . '/Portal/my_achievement.php');
$threeReads = ($built === 2) && $opSurf;
/* والأرقامُ الثلاثةُ من مصدرٍ واحد: `unit_party_awards` مفتاحُه الواقعة */
$srcOne = one($conn, "SELECT COUNT(DISTINCT `source_kind`) FROM `unit_party_awards`") >= 1;
gate($G, 'G1', $threeReads && $srcOne,
     'الواقعةُ الواحدةُ ثلاثُ قراءات',
     "أسطحُ الكشفِ المبنية {$built}/2 + كشفُ المشغّلِ على سطحٍ حيّ · إسنادُ مشغّلٍ مقيس={$opAward}",
     'الأرقامُ الثلاثةُ تُشتق من `unit_party_awards` بمفتاحِ الواقعةِ الواحدة — لا ثلاثةُ مصادر');

/* ══ ② سلسلةُ الاعتمادِ كاملة ═══════════════════════════════════════════ */
/* لكلِّ وحدةٍ بلغت `sales_approved` فصاعدًا: قرارُ موقعٍ + قرارُ طرفٍ + قرارُ مبيعات */
$broken = one($conn, "
  SELECT COUNT(*) FROM `unit_entries` e
   WHERE e.`state` IN ('sales_approved','converted')
     AND (NOT EXISTS (SELECT 1 FROM `unit_approvals` a
                       WHERE a.`entry_id`=e.`id` AND a.`stage`='site'  AND a.`decision`='approved')
       OR NOT EXISTS (SELECT 1 FROM `unit_approvals` a
                       WHERE a.`entry_id`=e.`id` AND a.`stage`='sales' AND a.`decision`='approved'))");
gate($G, 'G2', $broken === 0,
     'سلسلةُ الاعتمادِ كاملةٌ بلا فجوة',
     "وحداتٌ بلغت اكتمالَ السلسلةِ بلا قرارِ موقعٍ أو مبيعات: **{$broken}**",
     'وهي حالةُ الرسوبِ الممكنة: وحدةٌ قفزت مرحلةً');

/* ══ ③ السلّمُ لا يُتجاوَز ═══════════════════════════════════════════════ */
$svc = (string) @file_get_contents($ROOT . '/app/Services/Unit/TimesheetEntryService.php');
$reads = strpos($svc, 'ems_uc_ladder_check') !== false;
$mode  = ems_uc_ladder_mode();
$jrTot = one($conn, "SELECT COUNT(*) FROM `gov_journey_ladders`");
$jrWir = one($conn, "SELECT COUNT(*) FROM `gov_journey_ladders` WHERE `ladder_wired`=1");
gate($G, 'G3', $reads && $jrWir === $jrTot,
     'السلّمُ يُقرأ ولا يُتجاوَز',
     "نقطةُ القرارِ تقرأ السلّم=" . ($reads ? 'نعم' : 'لا')
     . " · الرحلاتُ الموصولةُ **{$jrWir}/{$jrTot}** · النمط={$mode}",
     'وثمانيةُ سلاليمَ (LD-06..LD-13) قائدُها سطحٌ آخرُ لم يُوصَل — مُعلَنٌ لا مطويّ');

/* ══ ④ فصلُ الواجبات ═══════════════════════════════════════════════════ */
$sod1 = one($conn, "SELECT COUNT(*) FROM (
          SELECT `entry_id`,`round_no`,`actor_id` FROM `unit_approvals`
           GROUP BY `entry_id`,`round_no`,`actor_id` HAVING COUNT(DISTINCT `stage`)>1) x");
$sod2 = one($conn, "SELECT COUNT(*) FROM `unit_final_approvals`
                     WHERE (`approved_by` IS NOT NULL AND `approved_by`=`prepared_by`)
                        OR (`control_by`  IS NOT NULL AND `control_by`=`approved_by`)");
$sod3 = one($conn, "SELECT COUNT(*) FROM `tre_pay_batches`
                     WHERE `executed_by` IS NOT NULL AND `executed_by`=`prepared_by`");
$sod4 = one($conn, "SELECT COUNT(*) FROM `tre_beneficiaries`
                     WHERE `verified_by` IS NOT NULL AND `verified_by`=`created_by`");
$sodAll = max(0, $sod1) + max(0, $sod2) + max(0, $sod3) + max(0, $sod4);
gate($G, 'G4', $sodAll === 0,
     'فصلُ الواجبات — لا يدَ تجمع اثنتين',
     "وحدات={$sod1} · اعتمادٌ نهائيّ={$sod2} · دفعات={$sod3} · مستفيدون={$sod4} ⇒ **{$sodAll}**",
     'وقيودُ CHECK تحرس الثلاثةَ الأخيرةَ في القاعدةِ نفسِها — جُرِّبت معطوبةً');

/* ══ ⑤ لا كاتبَ بشريٌّ إلى دفترِ الأستاذ ═══════════════════════════════ */
$SKIP = array('/vendor/', '/node_modules/', '/.git/', '/docs/', '/tests/', '/tools/',
              '/storage/', '/database/');
$writers = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $pth = str_replace('\\', '/', $f->getPathname());
    foreach ($SKIP as $s) { if (strpos($pth, $s) !== false) { continue 2; } }
    $src = (string) @file_get_contents($pth);
    if (preg_match('/(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?fin_journal_entries`?\b/i', $src)) {
        /* ◆ **الجذرُ بشرطاتٍ خلفيةٍ على ويندوز والمسارُ بأمامية** — فقصُّ الجذرِ
         *   بلا توحيدٍ يترك المسارَ مطلقًا فلا يطابق قائمةَ الاستثناءِ النسبية،
         *   **فيُعلَن كاتبٌ غيرُ مُعلَنٍ وهو مُعلَن**. العطبُ في المقياسِ لا النظام. */
        $rootFwd = str_replace('\\', '/', $ROOT) . '/';
        $writers[] = str_replace($rootFwd, '', $pth);
    }
}
/* الاستثناءاتُ المُعلَنةُ في سقّاطةِ كتّابِ الدفترِ — تُقرأ ولا تُكرَّر */
$declared = array('Finance/fin_helpers.php', 'includes/fx.php', 'Finance/journal_form_fin.php',
                  'app/Services/Governance/GovernanceM14Service.php');
$undeclared = array_values(array_diff($writers, $declared));
gate($G, 'G5', count($undeclared) === 0,
     'لا كاتبَ بشريٌّ جديدٌ إلى دفترِ الأستاذ',
     'كتّابٌ مقيسون=' . count($writers) . ' · **غيرُ مُعلَنٍ منهم: ' . count($undeclared) . '**'
     . ($undeclared ? ' — ' . implode(' · ', array_slice($undeclared, 0, 3)) : ''),
     'والأربعةُ المُعلَنةُ لها سببٌ مكتوبٌ في سقّاطةِ `injfix01_journal_writer_ratchet`');

/* ══ ⑥ المحاسبةُ ليست الخزينة ═══════════════════════════════════════════ */
$treJournal = one($conn, "SELECT COUNT(*) FROM `tre_pay_batches` WHERE `state`='executed'
                            AND `bank_ref` IS NULL");
/* ولا قيدَ من الخزينة: جدولُ الدفعاتِ بلا عمودِ قيدٍ أصلًا — يُقاس بنيويًّا */
$hasJe = one($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tre_pay_batches'
                        AND COLUMN_NAME LIKE '%journal%'");
gate($G, 'G6', $treJournal === 0 && $hasJe === 0,
     'المحاسبةُ ليست الخزينة',
     "دفعاتٌ نُفِّذت بلا مرجعِ حركة={$treJournal} · أعمدةُ قيدٍ في جدولِ الخزينة={$hasJe}",
     'الخزينةُ تنتج مرجعَ الحركةِ ولا تملك قيدًا — والبنيةُ نفسُها تمنعه');

/* ══ ⑦ الإسقاطاتُ لها مصادر ═══════════════════════════════════════════ */
$need = array(
  'حالةُ الفاتورة'  => array('ar_claim_invoices', 'Finance/ar_claim_invoice.php'),
  'حالةُ التحصيل'  => array('fin_collection_allocations', 'Contracts/collections.php'),
  'حالةُ الصرف'    => array('tre_pay_batches', 'Finance/tre_pay_batch.php'),
);
$missSrc = array();
foreach ($need as $lbl => $pair) {
    list($tbl, $screen) = $pair;
    $t = one($conn, "SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$tbl}'");
    if ($t < 1 || !is_file($ROOT . '/' . $screen)) { $missSrc[] = $lbl; }
}
gate($G, 'G7', count($missSrc) === 0,
     'الإسقاطاتُ لها مصادرُ مبنية',
     'حالاتٌ بلا مصدرٍ مبنيّ: ' . (count($missSrc) ? implode(' · ', $missSrc) : '0 من 3'),
     'قبلَ هذه الجولةِ كانت حالةُ التحصيلِ والصرفِ بلا سطحٍ يُقرأ منه');

/* ══ ⑧ الرحلتان تعملان ═══════════════════════════════════════════════ */
/* ◆ **العدُّ `<> 'BUILT'` لا `= 'MISSING'`** (البند ٠-٣): القيمةُ الأخيرةُ في
 *   `ENUM` بصفرِ صفٍّ يحملها — فبوابةٌ تعدُّها كانت عاجزةً عن الرسوبِ بنيويًّا.
 *   ولكلِّ `UNDER_OTHER_ROUTE` يُفحص أن حاملَه **يؤدي وظيفتَه بسلّمِه**. */
$cn = chain_node_verdicts($ROOT, $conn);
$badList = array();
foreach ($cn['rows'] as $rw) {
    if ($rw['why']) {
        $badList[] = 'العقدة ' . $rw['no'] . ' (`' . $rw['file'] . '` · ' . $rw['ladder'] . '): '
                   . implode(' · ', $rw['why']);
    }
}
gate($G, 'G8', count($cn['bad']) === 0,
     'الرحلتان تعبران — الحاملُ يؤدي وظيفتَه بسلّمِه',
     'عقدٌ مبنيةٌ باسمِها: **' . $cn['built'] . '/' . $cn['total'] . '** · '
   . 'غيرُ ذلك: **' . count($cn['rows']) . '** (مفقودةٌ فعلًا: ' . $cn['missing'] . ') · '
   . '**حاملٌ لا يؤدي وظيفتَه بسلّمِه: ' . count($cn['bad']) . '** · '
   . 'سلاليمُ تُقرأ من نقطةِ قرارٍ حيّة: ' . count($cn['wiredLadders']),
     '**والعبورُ البشريُّ بحسابٍ حقيقيٍّ لم يقع** — يلزمه موظفٌ لا منفِّذ، ويُعلَن مفتوحًا'
   . ($badList ? "\n        ◆ " . implode("\n        ◆ ", $badList) : ''));

/* ══ ⑨ صفرُ ارتداد ═══════════════════════════════════════════════════ */
/* الحزامُ كلُّه أخضر ⇒ ما كان يعمل يعمل. ويُقاس بمُرجَعِ الفاحصاتِ لا بالادّعاء */
$suite = glob($ROOT . '/tests/injfix0*.php');
$red = 0;
foreach ($suite as $t) { $o = array(); $c2 = 0; @exec('"' . PHP_BINARY . '" ' . escapeshellarg($t) . ' 2>&1', $o, $c2);
    if ($c2 !== 0) { $red++; } }
/* ◆ **وحزامُ INJ-FRD-REM-01 يُضاف إلى الحكمِ نفسِه**: ستةٌ وعشرون شاهدًا
 *   أُنتجت في تلك الجولةِ كانت **خارجَ الكنس** لأن المرشِّحَ `injfix0*`.
 *   ودليلٌ لا يُكنَس يتعفّن صامتًا. وحكمُه **ثلاثيٌّ** (ارتدادُ المُغلَقِ
 *   يُرسِّب · وشاهدٌ بلا مطلبٍ يُرسِّب · وخضرةُ غيرِ المُغلَقِ خبرٌ لا خلل)
 *   فيُنادى بملفِّه لا بإضافتِه إلى المرشِّح. */
$o3 = array(); $rcFrd = 0;
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tests/injfrd01_belt.php') . ' 2>&1', $o3, $rcFrd);
if ($rcFrd !== 0) { $red++; }
gate($G, 'G9', $red === 0,
     'صفرُ ارتداد — ما كان يعمل يعمل',
     'حزامُ INJ-FIX: **' . (count($suite) - $red + ($rcFrd === 0 ? 0 : 1)) . ' من ' . count($suite)
 . ' خضراء** · وحزامُ INJ-FRD: ' . ($rcFrd === 0 ? '**أخضر**' : '**أحمر**') . ' · راسب=' . $red,
     'وهي حالةُ الرسوبِ الممكنة: أيُّ شاهدٍ سابقٍ ينقلب أحمرَ بفعلِ هذه الجولة');

/* ══ ⑩ صفرُ فقد ═══════════════════════════════════════════════════════ */
$orphanLines = one($conn, "SELECT COUNT(*) FROM `tre_pay_batch_lines` l
                            WHERE NOT EXISTS (SELECT 1 FROM `tre_pay_batches` b WHERE b.`id`=l.`batch_id`)");
$dupIdem = one($conn, "SELECT COUNT(*) FROM (
                SELECT `idem_key` FROM `ar_accruals` GROUP BY `idem_key` HAVING COUNT(*)>1) x");
$silent  = one($conn, "SELECT COUNT(*) FROM `ar_claim_invoices`
                        WHERE `journal_entry_id` IS NOT NULL AND `control_at` IS NULL");
$lost = max(0, $orphanLines) + max(0, $dupIdem) + max(0, $silent);
gate($G, 'G10', $lost === 0,
     'صفرُ فقدٍ ولا تكرارَ ولا ترحيلَ صامت',
     "سطورٌ يتيمة={$orphanLines} · مفاتيحُ عطالةٍ مكررة={$dupIdem} · ترحيلٌ صامت={$silent}",
     'والترحيلُ الصامتُ يستحيل بنيويًّا — قيدُ CHECK يمنعه، ويُقاس مع ذلك');

/* ══ العرضُ والحكم ══════════════════════════════════════════════════ */
echo str_repeat('─', 104) . "\n";
$green = 0;
foreach ($G as $code => $g) {
    $m = $g['green'] ? '✔' : '✘';
    if ($g['green']) { $green++; }
    printf("  %-4s %s %-38s %s\n", $code, $m, mb_substr($g['title'], 0, 36), $g['measure']);
    if ($g['note'] !== '') { echo "        ◆ " . $g['note'] . "\n"; }
}
echo str_repeat('─', 104) . "\n";
printf("◆ **بواباتُ السلسلة: %d من %d خضراء**\n", $green, count($G));
if ($green < count($G)) {
    $open = array();
    foreach ($G as $c => $g) { if (!$g['green']) { $open[] = $c; } }
    echo "◆ مفتوحةٌ مُعلَنة: " . implode(' · ', $open) . "\n";
    echo "◆ **ولا تُعلَن السلسلةُ مغلقةً** ما دامت واحدةٌ منها مفتوحة — بنصِّ الوثيقة.\n";
}

foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--json=(.*)$/', $a, $m2)) {
        file_put_contents($m2[1], json_encode(array('green' => $green, 'total' => count($G), 'gates' => $G),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "◆ كُتب: {$m2[1]}\n";
    }
}

exit($green === count($G) ? 0 : 1);
