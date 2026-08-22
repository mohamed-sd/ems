<?php
/**
 * tests/injalign01_gates.php
 *   بواباتُ قبولِ مواءمةِ المبيعاتِ والموردين — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ بوابةٍ ببسطِها ومقامِها لا بالانطباع** — بنصِّ الوثيقتين. وكلُّ واحدةٍ
 *   إمّا **خضراءُ بقياس**، أو **محجوبةٌ بمدخلٍ خارجيٍّ مُعلَن**، أو **مفتوحةٌ
 *   بسببِها**. ولا بوابةَ خضراءُ بلا مقام.
 *
 * ◆ **والمحجوبُ لا يُدَّعى نجاحُه ولا يُعَدُّ رسوبًا**: بوابتا الأعمدة
 *   (589/589 و 717/717) تلزمهما المصنَّفان الحاكمان، وهما **ليسا في الحزمة
 *   المرفقة**. فتُعلَن `BLOCKED_EXTERNAL_INPUT` بمالكِها ومطلوبِها.
 *
 * التشغيل: php tests/injalign01_gates.php [--json=<ملف>] [--negative] [--a8-only]
 *
 * ◆ **وبوابةُ الهرمِ A8 تقيس فعلًا لا وجودَ ملفّ** (البند ٠-١): كانت `is_file()`
 *   على حارسَين خاليَين من الفحصِ أصلًا — فكانت خضراءَ على نظامٍ يقبل حصةً
 *   بلا التزام. و`--negative` **يعطبها عمدًا** ويشترط رسوبَها قبلَ تصديقِ مرورِها.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function one(mysqli $c, $sql) { $r = @$c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; }

$G = array();
function gate(&$G, $doc, $code, $state, $title, $measure, $note = '')
{
    $G[] = array('doc' => $doc, 'code' => $code, 'state' => $state,
                 'title' => $title, 'measure' => $measure, 'note' => $note);
}

/**
 * ── حارسُ الهرمِ مقيسًا بالفعل — A8 (البند ٠-١) ─────────────────────────────
 * «لا حصةَ بلا التزامِ نوعِ معدةٍ في عقدِ عميلٍ نافذ» (CAP-01 §8.2).
 * يعيد: state · score/3 · detail · note. ولا يُعلَن أخضرَ إلا بثلاثةِ أفعال.
 * وإن غابت أرضيةُ القياسِ (لا عقدَ عميلٍ ولا موردَ في شركةٍ واحدة) فالحالُ
 * `BLOCKED` بمالكٍ ومطلوبٍ مُعلَنَين — **ولا يُدَّعى نجاحٌ ولا يُحسب رسوبًا**.
 */
function a8_pyramid_behavior($ROOT, mysqli $conn)
{
    $MARK = 'INJA8PROBE';
    $sweep = function () use ($conn, $MARK) {
        @$conn->query("DELETE l FROM `supplier_contract_lines` l
                        JOIN `supplier_contracts` h ON h.`id` = l.`contract_id`
                       WHERE h.`notes` LIKE '{$MARK}%'");
        @$conn->query("DELETE FROM `supplier_contracts` WHERE `notes` LIKE '{$MARK}%'");
    };
    $sweep();
    register_shutdown_function($sweep);

    /* ③ الصفوفُ الحيةُ المخالفة — تُعَدُّ قبلَ البذر */
    $bad = one($conn, "SELECT COUNT(*) FROM `supplier_contract_lines`
                        WHERE `contract_obligation_ref` IS NULL AND COALESCE(`is_deleted`, 0) = 0");
    $live = one($conn, "SELECT COUNT(*) FROM `supplier_contract_lines`
                         WHERE COALESCE(`is_deleted`, 0) = 0");

    /* أرضيةُ القياس */
    $g = @$conn->query("SELECT k.`company_id` AS co, k.`id` AS cc,
                        (SELECT s.`id` FROM `suppliers` s
                          WHERE s.`company_id` = k.`company_id` ORDER BY s.`id` LIMIT 1) AS sup
                          FROM `contracts` k
                         WHERE COALESCE(k.`is_deleted`, 0) = 0
                           AND EXISTS (SELECT 1 FROM `suppliers` s WHERE s.`company_id` = k.`company_id`)
                         ORDER BY k.`id` LIMIT 1");
    $ground = $g ? $g->fetch_assoc() : null;
    if (!$ground) {
        return array('state' => 'BLOCKED', 'score' => 0,
            'detail' => "لا أرضيةَ للقياس — صفرُ شركةٍ فيها عقدُ عميلٍ ومورّدٌ معًا",
            'note' => 'المطلوب: صفٌّ واحدٌ في `contracts` وآخرُ في `suppliers` لشركةٍ واحدة '
                    . '· المالك: مالكُ البيانات — **ولا يُدَّعى نجاحٌ ولا يُحسب رسوبًا**');
    }
    $CO = (int) $ground['co']; $CC = (int) $ground['cc']; $SUPP = (int) $ground['sup'];
    $ACTOR = 999905;

    require_once $ROOT . '/app/Core/TenantRegistry.php';
    require_once $ROOT . '/app/Core/TenantContext.php';
    require_once $ROOT . '/app/Core/TenantGateException.php';
    require_once $ROOT . '/app/Core/TenantDb.php';
    require_once $ROOT . '/app/Services/Contract/ContractStateMachine.php';
    require_once $ROOT . '/app/Services/Contract/SupplierContractService.php';

    $svc422 = false; $dbRefuses = false; $why = '';
    try {
        $gateDb = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($CO, $ACTOR, '', true));
        $r = \App\Services\Contract\SupplierContractService::createContract($conn, $gateDb, $CO, array(
            'supplier_id' => $SUPP, 'client_contract_id' => $CC,
            'start_date' => '2043-01-01', 'end_date' => '2043-12-31',
            'currency' => 'USD', 'notes' => $MARK . ' عقدُ قياسِ بوابةِ الهرم'), $ACTOR);
        if (empty($r['ok'])) {
            $why = 'تعذّر بذرُ عقدِ القياس: ' . (isset($r['reason']) ? $r['reason'] : '?');
        } else {
            $SCID = (int) $r['contract_id'];

            /* ① الخدمةُ — حصةٌ بلا مرجعِ التزامٍ ⇐ ٤٢٢ */
            $r2 = \App\Services\Contract\SupplierContractService::saveLine($conn, $gateDb, $CO, $SCID,
                array('work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 100), $ACTOR);
            $svc422 = (empty($r2['ok']) && (int) $r2['code'] === 422);
            if (!$svc422) {
                $why = 'الخدمةُ قبلت حصةً بلا مرجعِ التزامٍ (code='
                     . (isset($r2['code']) ? (int) $r2['code'] : '?') . ')';
                if (!empty($r2['line_id'])) {
                    @$conn->query("DELETE FROM `supplier_contract_lines` WHERE `id` = " . (int) $r2['line_id']);
                }
            }

            /* ② القاعدةُ — إدراجٌ مباشرٌ بلا مرجعٍ يلتفُّ على الخدمة */
            $ins = @$conn->query("INSERT INTO `supplier_contract_lines`
                (`company_id`, `contract_id`, `work_model`, `unit`, `unit_price`)
                VALUES ({$CO}, {$SCID}, 'hour', 'ساعة', 1)");
            $dbRefuses = ($ins === false);
            if ($ins) { @$conn->query("DELETE FROM `supplier_contract_lines` WHERE `id` = " . (int) $conn->insert_id); }
        }
    } catch (\Throwable $e) {
        $why = 'استثناءٌ أثناءَ القياس: ' . $e->getMessage();
    }
    $sweep();

    $score = ($svc422 ? 1 : 0) + ($dbRefuses ? 1 : 0) + ($bad === 0 ? 1 : 0);
    $detail = '① الخدمةُ ترد ٤٢٢: ' . ($svc422 ? '✔' : '✘')
            . ' · ② القاعدةُ ترفض الإدراجَ المباشر: ' . ($dbRefuses ? '✔' : '✘')
            . ' · ③ بنودٌ حيةٌ بلا مرجعِ التزام: **' . $bad . '/' . $live . '**';
    $note = 'والإسنادُ الإضافيُّ لا يرفع الحصةَ ولا المستهدف — والقاعدةُ في '
          . '`SupplierContractService::saveLine` موضعًا واحدًا';
    if ($why !== '') { $note .= "\n          ◆ " . $why; }
    return array('state' => ($score === 3) ? 'PASS' : 'OPEN',
                 'score' => $score, 'detail' => $detail, 'note' => $note);
}

/**
 * ── حزامُ صدقِ البوابةِ A8 — `--negative` (البند ٠-١) ────────────────────────
 * ◆ **حارسٌ لم يرسُبْ مرةً واحدةً ليس حارسًا.** فهذا الوضعُ يعطب الشرطَ عمدًا —
 *   يُحيّد فحصَ مرجعِ الالتزامِ في `SupplierContractService::saveLine` — ثم يقيس
 *   البوابةَ مرةً ثانية، ويشترط أن **تنقص** درجتُها. ثم يُعيد المصدرَ كما كان
 *   في `finally` وفي خطّافِ الخروجِ معًا، فلا يبقى عطبٌ ولو انهار التنفيذ.
 * ◆ وما دام الحارسُ غيرَ مبنيٍّ بعد (الدرجةُ السليمةُ نفسُها دونَ ٣) فالحزامُ
 *   **يُعلن ذلك ولا يدّعي إثباتًا** — فالادعاءُ بلا قياسٍ هو العطبُ عينُه.
 */
function a8_honesty_belt($ROOT, mysqli $conn)
{
    $src = $ROOT . '/app/Services/Contract/SupplierContractService.php';
    $intact = a8_pyramid_behavior($ROOT, $conn);
    echo "\n══ حزامُ صدقِ البوابة A8 — تُجرَّب معطوبةً قبلَ تصديقِ مرورِها ══\n";
    printf("  ① الدرجةُ بالمصدرِ السليم: **%d/3** (%s)\n", $intact['score'], $intact['state']);

    if ($intact['score'] !== 3) {
        echo "  ◆ **الحارسُ غيرُ مبنيٍّ بعد** — فلا يُعطَب ما ليس قائمًا، ولا يُدَّعى\n";
        echo "    إثباتٌ لم يُقَس. والمثبَتُ اليومَ أن البوابةَ **ترسُب على العطبِ القائم**\n";
        echo "    حيث كانت تخضرُّ بـ`is_file()` — وتمامُ الحزامِ عند بناءِ الحارس (البند ٢-١).\n";
        return $intact['score'] !== 3 ? 0 : 1;
    }

    $orig = file_get_contents($src);
    $restore = function () use ($src, $orig) { file_put_contents($src, $orig); };
    register_shutdown_function($restore);
    $dropped = false; $broken = null;
    try {
        $needle = "if (\$oblRef === null) {";
        if (strpos($orig, $needle) === false) {
            echo "  ✘ مرساةُ العطبِ غيرُ موجودةٍ في المصدر — الحزامُ لا يدّعي\n";
            $restore();
            return 0;
        }
        file_put_contents($src, str_replace($needle, "if (false) {", $orig));
        $broken = a8_pyramid_behavior_isolated($ROOT, $conn);
        $dropped = ($broken['score'] < $intact['score']);
        printf("  ② الدرجةُ بالحارسِ مُحيَّدًا: **%d/3** (%s)\n", $broken['score'], $broken['state']);
    } catch (\Throwable $e) {
        echo "  ✘ استثناءٌ أثناءَ العطب: " . $e->getMessage() . "\n";
    } finally {
        $restore();
    }
    $after = a8_pyramid_behavior($ROOT, $conn);
    printf("  ③ وأُعيد المصدرُ — الدرجةُ عادت: **%d/3**\n", $after['score']);
    $ok = $dropped && $after['score'] === $intact['score'];
    echo $ok ? "  ✔ **البوابةُ رسبت معطوبةً ومرّت سليمةً** — صدقُها مقيسٌ لا مُدَّعى\n"
             : "  ✘ **البوابةُ لم ترسُبْ بالعطب** — فليست حارسًا\n";
    return $ok ? 1 : 0;
}

/** يُعيد القياسَ في عمليةٍ منفصلةٍ لأن الصنفَ محمَّلٌ سلفًا فلا يُعاد تحميلُه. */
function a8_pyramid_behavior_isolated($ROOT, mysqli $conn)
{
    $php = PHP_BINARY;
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg(__FILE__) . ' --a8-only 2>&1';
    $out = @shell_exec($cmd);
    if (preg_match('/A8SCORE=(\d)/', (string) $out, $m)) {
        return array('score' => (int) $m[1], 'state' => (int) $m[1] === 3 ? 'PASS' : 'OPEN');
    }
    return array('score' => -1, 'state' => 'OPEN');
}

/* ── الأوضاعُ الخاصة — تسبق العرضَ العامّ ─────────────────────────────────── */
/* `--a8-only`: قياسُ بوابةِ الهرمِ وحدَها في عمليةٍ نظيفة (يستعملها الحزامُ السلبيّ
 *   لأن صنفَ الخدمةِ يُحمَّل مرةً واحدةً فلا يُعاد تحميلُه بعدَ تعديلِ مصدرِه). */
if (in_array('--a8-only', $argv, true)) {
    $r = a8_pyramid_behavior($ROOT, $conn);
    echo "A8SCORE={$r['score']}\n";
    exit(0);
}
/* `--negative`: **تُجرَّب البوابةُ معطوبةً قبلَ تصديقِ مرورِها.** */
if (in_array('--negative', $argv, true)) {
    exit(a8_honesty_belt($ROOT, $conn) === 1 ? 0 : 1);
}
$SAL = 'SAL'; $SUP = 'SUP';
$ANCH = "'main/role_board.php','chats/index.php'";

echo "══ بواباتُ قبولِ مواءمةِ المبيعاتِ والموردين ══\n";

foreach (array(
    array($SAL, 'INJ-SAL-ALIGN-01', 12, 26, 589, 13),
    array($SUP, 'INJ-SUP-ALIGN-01', 2,  29, 717, 14),
) as $D) {
    list($k, $doc, $role, $sheets, $cols, $items) = $D;

    /* ── ① التغطية: قرارُ سطحٍ لكلِّ ورقة ─────────────────────────────── */
    $n = one($conn, "SELECT COUNT(*) FROM `gov_sheet_decisions`
                      WHERE `doc_code` = '{$doc}' AND `is_index` = 0");
    $blank = one($conn, "SELECT COUNT(*) FROM `gov_sheet_decisions`
                          WHERE `doc_code` = '{$doc}' AND (`decision` = '' OR `target` = '')");
    gate($G, $k, 'A1', ($n === $sheets && $blank === 0) ? 'PASS' : 'OPEN',
         'التغطية — قرارُ سطحٍ لكلِّ ورقة',
         "**{$n}/{$sheets}** · بلا قرارٍ أو هدف: {$blank}",
         'وورقةُ الفهرسِ لها قرارٌ ولا تُعَدُّ في المقام');

    /* ── ② الأعمدة — محجوبةٌ بمدخلٍ خارجيّ ────────────────────────────── */
    gate($G, $k, 'A2', 'BLOCKED',
         'الأعمدة — حكمُ مصدرٍ لكلِّ عمود',
         "0/{$cols} — **لا يُقاس**",
         'المصنَّفُ الحاكمُ ليس في الحزمة · المطلوب: المصنَّفُ نفسُه ليُقيَّد لكلِّ '
       . 'عمودٍ حكمُ مصدرِه أو سببُ غيابِه · المالك: مالكُ الوثيقة');

    /* ── ③ التنقّل بعد الدمج ──────────────────────────────────────────── */
    $live = one($conn, "SELECT COUNT(*) FROM `nav_items`
                         WHERE `role_id` = {$role} AND `active` = 1 AND `route` NOT IN ({$ANCH})");
    $grp  = one($conn, "SELECT COUNT(DISTINCT `group_id`) FROM `nav_items`
                         WHERE `role_id` = {$role} AND `active` = 1 AND `route` NOT IN ({$ANCH})");
    $empty = one($conn, "SELECT COUNT(*) FROM `link_groups` g
                          WHERE g.`group_code` LIKE 'n9t_align_r{$role}_%'
                            AND NOT EXISTS (SELECT 1 FROM `nav_items` n
                                             WHERE n.`group_id` = g.`id` AND n.`active` = 1)");
    gate($G, $k, 'A3', ($live === $items && $grp === 6 && $empty === 0) ? 'PASS' : 'OPEN',
         'التنقّل بعد الدمج',
         "**{$grp} مجموعات / {$live} بندًا** — المستهدَف 6/{$items} · مجموعةٌ فارغة: {$empty}",
         'والمرساتانِ خارجَ المقامِ بقرارِ مالكٍ سابق');

    /* ── ④ التبويبات — كلُّ مُخفًى له منفذٌ مُثبَت ───────────────────────── */
    $hid  = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log` WHERE `role_id` = {$role}");
    $noWay = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log`
                          WHERE `role_id` = {$role} AND (`reachable` IS NULL OR `reachable` = '')");
    gate($G, $k, 'A4', ($noWay === 0) ? 'PASS' : 'OPEN',
         'التبويبات — صفرُ تبويبٍ مكسور',
         "أُخفي {$hid} بندًا · **بلا منفذٍ مُثبَت: {$noWay}**",
         'والمنفذُ أحدُ ثلاثة: تبويبٌ في ملفٍّ أمّ · نشطٌ لدورٍ آخر · مملوكٌ بحكمٍ مكتوب');

    /* ── ⑤ الملكية — لا شاشةَ إدارةٍ أخرى أصليةً في **مساحةِ هذا الدور** ─────
     * ◆ **فخُّ القياسِ الذي وقعتُ فيه أوّلًا**: `gov_space_appearances` صفٌّ لكلِّ
     *   **ظهورٍ في مساحة**، فسؤالُه عن «أيِّ صفٍّ ممنوعٍ لهذا المسار» يلتقط
     *   منعَه في مساحةِ إدارةٍ أخرى ويحسبه تسريبًا هنا. فـ`clients.php` ممنوعٌ
     *   في مساحةِ التشغيلِ **ومملوكٌ في مساحةِ المبيعات** — والقياسُ الأوّلُ
     *   أعلنه تسريبًا. ⇒ **يُقيَّد بمساحةِ الدورِ نفسِه**. */
    $spaceOf = array(12 => 'ادارة المبيعات', 2 => 'ادارة الموردين');
    $sp = $conn->real_escape_string($spaceOf[$role]);
    $leak = one($conn, "
      SELECT COUNT(*) FROM `nav_items` n
       WHERE n.`role_id` = {$role} AND n.`active` = 1 AND n.`route` NOT IN ({$ANCH})
         AND EXISTS (SELECT 1 FROM `gov_space_appearances` s
                      WHERE s.`route` = n.`route` AND s.`space_ar` = '{$sp}'
                        AND s.`cls` = 'FORBIDDEN'
                        AND s.`owner_kind` <> 'PLATFORM_SHARED')");
    gate($G, $k, 'A5', ($leak === 0) ? 'PASS' : 'OPEN',
         'الملكية — صفرُ تسريبِ شاشةِ إدارةٍ أخرى',
         "بنودٌ ظاهرةٌ حكمُها ممنوعٌ في هذه المساحة: **{$leak}**",
         'والمكوّنُ المركزيُّ المشتركُ (المهامُّ والاعتمادات) ليس تسريبًا — `PLATFORM_SHARED`');
}

/* ── ⑥ الازدواج — لا وظيفةٌ بسطحَين كنسيَّين ولا مسارٌ باسمَين ────────────── */
/* ◆ **والمقامُ هو مقامُ الوثيقة**: «لا مسارَ باسمَين **معياريَّين**» — أي مسارٌ
 *   حالتُه `APPROVED` في السجلِّ الكنسيِّ ويُصيَّر باسمَين. وقياسُ **كلِّ** مسارٍ
 *   بأيِّ اسمَين يخلط الاسمَ المعياريَّ بالاسمِ التاريخيِّ لدورٍ لم يُوحَّد بعد،
 *   فيصنع ثلاثةً وخمسين مخالفةً لا وجودَ لها في حكمِ الوثيقة. */
$dupName = one($conn, "SELECT COUNT(*) FROM (
      SELECT n.`route` FROM `nav_items` n
        JOIN `nav_canonical` c ON c.`route` = n.`route` AND c.`status` = 'APPROVED'
       WHERE n.`active` = 1 AND n.`route` NOT LIKE '%#%'
       GROUP BY n.`route` HAVING COUNT(DISTINCT n.`label_ar`) > 1) x");
$dupCanon = one($conn, "SELECT COUNT(*) FROM (
      SELECT `canonical_ar` FROM `nav_canonical` WHERE `status` = 'APPROVED'
       GROUP BY `canonical_ar` HAVING COUNT(DISTINCT `route`) > 1) x");
gate($G, 'ALL', 'A6', ($dupName === 0 && $dupCanon === 0) ? 'PASS' : 'OPEN',
     'الازدواج — لا مسارٌ باسمَين ولا اسمٌ لمسارَين',
     "مسارٌ بأكثرَ من اسمٍ ظاهر: **{$dupName}** · اسمٌ كنسيٌّ لأكثرَ من مسار: **{$dupCanon}**",
     'ومسارُ المناقصاتِ صار تبويبَ الفرصةِ — والفرصةُ هي الكنسية');

/* ── ⑦ الحدود — لا إدخالَ وحدةٍ داخلَ المبيعاتِ أو الموردين ──────────────── */
$entryScreens = array('Timesheet/timesheet.php', 'Timesheet/timesheet_type.php',
                      'Timesheet/view_timesheet.php', 'Operations/shift_entry.php');
$in = "'" . implode("','", $entryScreens) . "'";
$manual = one($conn, "SELECT COUNT(*) FROM `nav_items`
                       WHERE `role_id` IN (12,2) AND `active` = 1 AND `route` IN ({$in})");
gate($G, 'ALL', 'A7', ($manual === 0) ? 'PASS' : 'OPEN',
     'الحدود — لا إدخالَ ساعةٍ أو طنٍّ في المبيعاتِ أو الموردين',
     "أسطحُ إدخالِ وحداتٍ ظاهرةٌ لهما: **{$manual}**",
     'فالواقعةُ بيتُها التايم شيت — وتُقرأ هنا لا تُدخَل');

/* ── ⑧ الهرم — لا حصةَ بلا التزامٍ ولا معدةَ تُنشئ حصة ──────────────────── */
/* ◆ **البوابةُ تقيس فعلًا لا وجودَ ملفّ** (البند ٠-١ · من عائلةِ GAP-56 — القياسُ يقيس غيرَ ما يدّعي):
 *   كانت `is_file()` على حارسَين — فتخضرُّ ولو كان الملفّانِ خاليَين من الفحصِ
 *   أصلًا، وهذا واقعُهما: `CapacityGuard.php` فيه **صفرُ ذكرٍ** للالتزام.
 *   والمقياسُ الآن ثلاثةُ أفعالٍ تُجرَّب حيًّا:
 *     ① الخدمةُ ترفض حصةً بلا مرجعِ التزامٍ ⇐ ٤٢٢
 *     ② القاعدةُ ترفض الإدراجَ المباشرَ بلا مرجع
 *     ③ صفرُ بندٍ حيٍّ يخالف القاعدة
 *   والبذرُ معزولٌ بوسمِ `INJA8PROBE` وسنةِ 2043 — يُكنس قبلَ المحاولةِ وبعدَها
 *   وعندَ الخروجِ أيًّا كان سببُه، فالكنسُ بالعائلةِ لا بالجلسة. والصفوفُ الحيةُ
 *   تُعَدُّ **قبلَ** البذرِ فلا يلوّثها بذرُ القياسِ نفسُه.
 */
$a8 = a8_pyramid_behavior($ROOT, $conn);
gate($G, $SUP, 'A8', $a8['state'],
     'الهرم — الحصةُ من الالتزامِ لا من المعدة',
     "أفعالٌ محروسةٌ مقيسةً حيًّا: **{$a8['score']}/3** · {$a8['detail']}",
     $a8['note']);

/* ── ⑨ السلامةُ والبيانات — صفرُ ارتدادٍ وصفرُ فقد ─────────────────────── */
$lost = one($conn, "SELECT COUNT(*) FROM `gov_nav_hidden_log` h
                     WHERE NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.`id` = h.`nav_id`)");
gate($G, 'ALL', 'A9', ($lost === 0) ? 'PASS' : 'OPEN',
     'البيانات — صفرُ فقدٍ ولا حذفَ صفّ',
     "صفوفُ تنقّلٍ أُخفيت ثم اختفت من الجدول: **{$lost}**",
     'الإخفاءُ `active = 0` — والصفُّ باقٍ بموضعِه السابقِ في سجلِّ الرجوع');

/* ══ العرضُ والحكم ══════════════════════════════════════════════════ */
echo str_repeat('─', 108) . "\n";
$pass = 0; $open = 0; $blocked = 0;
foreach ($G as $g) {
    $m = $g['state'] === 'PASS' ? '✔' : ($g['state'] === 'BLOCKED' ? '⛔' : '✘');
    if ($g['state'] === 'PASS') { $pass++; } elseif ($g['state'] === 'BLOCKED') { $blocked++; } else { $open++; }
    printf("  %-4s %-4s %s %-38s %s\n", $g['doc'], $g['code'], $m, mb_substr($g['title'], 0, 36), $g['measure']);
    if ($g['note'] !== '') { echo "          ◆ " . $g['note'] . "\n"; }
}
echo str_repeat('─', 108) . "\n";
printf("◆ **خضراء: %d · محجوبة: %d · مفتوحة: %d** — المقام %d\n", $pass, $blocked, $open, count($G));
if ($blocked) {
    echo "◆ **والمحجوبُ لا يُدَّعى ولا يُحسب رسوبًا** — يلزمه مدخلٌ خارجيٌّ مُسمًّى.\n";
}
if ($open) { echo "◆ **ولا تُعلَن الجولةُ مغلقةً** ما دامت بوابةٌ مفتوحةً بلا سبب.\n"; }

foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--json=(.*)$/', $a, $m2)) {
        file_put_contents($m2[1], json_encode(array('pass' => $pass, 'blocked' => $blocked,
            'open' => $open, 'gates' => $G), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "◆ كُتب: {$m2[1]}\n";
    }
}
exit($open === 0 ? 0 : 1);
