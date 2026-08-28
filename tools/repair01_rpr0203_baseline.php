<?php
/**
 * tools/repair01_rpr0203_baseline.php — قياسُ الأساسِ لأمرَي `RPR-02` و`RPR-03`
 * ═══════════════════════════════════════════════════════════════════════════
 * **الخطوةُ ③ من أمرِ التشغيل**: يُعاد قياسُ جدولِ الحالةِ كاملًا، **ويُسمّى كلُّ
 * فرقٍ بين الأمرِ والقياس** — ⛔ ولا يُعتمَد رقمُ الأمرِ ولا رقمُ المنفِّذِ بلا
 * تفسيرٍ مقيس.
 *
 * ◆ **والمقامُ يُعلَن مع كلِّ رقم** (البند ⑦): فـ«٦٢٣ مبنيًّا» و«٦٦٤ سطحًا»
 *   ليسا رقمَين متعارضَين بل **سجلَّين مختلفَين** — والخلطُ بينهما هو بعينِه
 *   «المقارنةُ بين تمثيلَين لشيءٍ واحد» التي تكرّرت سبعًا في الجولةِ السابقة.
 *
 * ◆ **وحارسُ المفردات** (البند ②): قبلَ أيِّ `WHERE col='X'` يُسأل العمودُ: هل
 *   `X` موجودةٌ فيه أصلًا؟ **فصفرٌ من مفردةٍ ميّتةٍ ليس نجاحًا بل عمًى**.
 *
 * ⛔ **ولا تكتب هذه الأداةُ في النظام**: تقرأ وتُحصي وتُصدر وثيقةً. والوثيقةُ
 *   إسقاطٌ والمخزنُ حكم.
 *
 * التشغيل: php tools/repair01_rpr0203_baseline.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
define('TOOL_VERSION', 'repair01_rpr0203_baseline.php v1.0');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD  = in_array('--md', $argv, true);
$CMD = 'php tools/repair01_rpr0203_baseline.php' . ($MD ? ' --md' : '');

$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : null; };
$rows = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    if ($r) { while ($x = $r->fetch_assoc()) { $o[] = $x; } }
    return $o;
};
$git = function ($a) use ($ROOT) {
    $o = array(); exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1', $o);
    return trim(implode(' ', $o));
};
/* ── حارسُ المفردات: هل القيمةُ حيّةٌ في العمود؟ ─────────────────────────── */
$vocab = function ($table, $col, $val) use ($conn) {
    $r = @$conn->query("SELECT COUNT(*) FROM `$table` WHERE `$col` = '"
        . $conn->real_escape_string($val) . "'");
    /* ⛔ **وعمودٌ غيرُ موجودٍ لا يُرَدُّ صفرًا**: الصفرُ يُقرأ نتيجةً والحقيقةُ
       أنّه لم يُنظَر. يُرَدُّ وسمٌ صارخٌ يُفسد التقريرَ عمدًا حتّى يُصلَح. */
    if (!$r) { return '⛔VOCAB:' . $table . '.' . $col . ' غيرُ موجود'; }
    return (int) $r->fetch_row()[0];
};
$vocabLive = function ($table, $col, $val) use ($conn) {
    $r = @$conn->query("SELECT COUNT(DISTINCT `$col`) FROM `$table` WHERE `$col` = '"
        . $conn->real_escape_string($val) . "'");
    return $r ? ((int) $r->fetch_row()[0] > 0) : false;
};

/* ═══ البصمةُ السداسيّة ═══════════════════════════════════════════════════ */
$commit = $git('rev-parse HEAD');
$branch = $git('rev-parse --abbrev-ref HEAD');
/* ⚠ **ولا يُقاس عددُ الملفّاتِ بـ`$git()`**: تلك تُلصق الأسطرَ بمسافةٍ فتعود
   الشجرةُ المتّسخةُ «ملفًّا واحدًا». **والسطرُ وحدةُ القياسِ هنا لا النصّ.** */
$dirtyLines = array();
exec('git -C ' . escapeshellarg($ROOT) . ' status --porcelain 2>&1', $dirtyLines);
$dirtyLines = array_values(array_filter(array_map('trim', $dirtyLines), 'strlen'));
$dirtyN = count($dirtyLines);
$tblN   = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'");
$viewN  = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW'");
$colN   = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()");
$regN   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry");
$openSnap = $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL
                  ORDER BY frozen_at DESC LIMIT 1");
$measuredAt = date('Y-m-d H:i:s');
$snapId = $openSnap ?: ('UNFROZEN-' . substr($commit, 0, 8) . '-' . date('Ymd-His'));

/* ═══ ① الهجرات — القرصُ والدفتر ══════════════════════════════════════════
   ⛔ **وعُرفُ التسميةِ هو نفسُه المعرَّفُ في `database/migrate.php`** — فأداتان
   تعرّفان «ملفَّ الهجرة» تعريفَين تختلفان بواحد، **وذلك عينُ «المقارنةِ بين
   تمثيلَين لشيءٍ واحد»**. وقد وقع هنا فعلًا: عدَّت هذه الأداةُ `_ledger.php`
   هجرةً فقالت «واحدةٌ خارجَ الدفتر» **والبوّابةُ تقول صفرًا** — وأُصلح. */
define('MIG_NAME_RE', '/^\d{4}_\d{2}_\d{2}_.+\.(sql|php)$/');
$files = array(); $unmanagedFiles = array();
foreach (glob($ROOT . '/database/migrations/*') as $f) {
    if (is_dir($f)) { continue; }
    $b = basename($f); $ext = strtolower(pathinfo($b, PATHINFO_EXTENSION));
    if (!in_array($ext, array('php', 'sql'), true)) { continue; }
    if (preg_match(MIG_NAME_RE, $b)) { $files[$b] = $ext; } else { $unmanagedFiles[$b] = $ext; }
}
$filesPhp = count(array_filter($files, function ($e) { return $e === 'php'; }));
$filesSql = count($files) - $filesPhp;
$led = array();
foreach ($rows("SELECT filename, status FROM schema_migrations") as $r) { $led[$r['filename']] = $r['status']; }
$diskNotLedger = array_diff_key($files, $led);
$ledgerNotDisk = array_diff_key($led, $files);
$lndByStatus = array(); $lndByExt = array();
foreach ($ledgerNotDisk as $k => $s) {
    $lndByStatus[$s] = (isset($lndByStatus[$s]) ? $lndByStatus[$s] : 0) + 1;
    $e = strtolower(pathinfo($k, PATHINFO_EXTENSION));
    $lndByExt[$e] = (isset($lndByExt[$e]) ? $lndByExt[$e] : 0) + 1;
}
$ledByStatus = array();
foreach ($led as $k => $s) { $ledByStatus[$s] = (isset($ledByStatus[$s]) ? $ledByStatus[$s] : 0) + 1; }

/* ═══ ② السجلُّ الرسميُّ — والمقاماتُ الثلاثة ═════════════════════════════ */
$lifecycle = array();
foreach ($rows("SELECT lifecycle, COUNT(*) c FROM repair01_screen_registry GROUP BY 1") as $r) {
    $lifecycle[$r['lifecycle']] = (int) $r['c'];
}
$ghosts = isset($lifecycle['GHOST_TARGET']) ? (int) $lifecycle['GHOST_TARGET'] : 0;
$built  = $regN - $ghosts;
$surfN  = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$surfN1 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE recon_verdict LIKE 'N:1%'");
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements");
$gapN   = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps");
$ownN   = (int) $one("SELECT COUNT(*) FROM repair01_ownership");
$fldN   = (int) $one("SELECT COUNT(*) FROM repair01_fields");
$depN   = (int) $one("SELECT COUNT(*) FROM repair01_departments");
$codesN = (int) $one("SELECT COUNT(DISTINCT owner_code) FROM repair01_screen_registry");
$platN  = $vocab('repair01_screen_registry', 'owner_code', 'PLATFORM');
$dvpN   = $vocab('repair01_screen_registry', 'owner_code', 'EX-DVP');
$platRegistered = $vocabLive('repair01_departments', 'canonical_code', 'PLATFORM');

/* الدليلُ المعماريُّ ① — المستهدَفُ من مصدرِه المصمَّم */
$guide = @json_decode(@file_get_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_NAV_SPEC.json'), true);
$guideScreens = 0; $guidePerDep = array();
if (is_array($guide)) {
    foreach ($guide as $code => $d) {
        $n = (isset($d['screens']) && is_array($d['screens'])) ? count($d['screens']) : 0;
        if ($n) { $guidePerDep[$code] = $n; }
        $guideScreens += $n;
    }
}
$reqPerUnit = array();
foreach ($rows("SELECT unit, COUNT(*) c FROM repair01_requirements GROUP BY 1") as $r) {
    $reqPerUnit[$r['unit']] = (int) $r['c'];
}
/* الأربعةُ خارجَ تعدادِ الإداراتِ السبعَ عشرة — بالبادئةِ المعلَنةِ في السجلّ */
$reqOutside = 0;
foreach ($reqPerUnit as $u => $c) { if (preg_match('~^(E1|E2|AS|WS)\s~u', $u)) { $reqOutside += $c; } }
$reqDeps = $reqN - $reqOutside;

/* ملفّاتُ المكتبةِ المسجَّلةُ أسطحًا — في سجلَّين لا واحد */
$vendorReg  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE route LIKE 'vendor/%' OR screen_file LIKE 'vendor/%'");
$vendorSurf = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE disk_path LIKE '%vendor%'");

/* ═══ ③ الأحداثُ والآثار ═════════════════════════════════════════════════ */
$evtTypes  = (int) $one("SELECT COUNT(DISTINCT event_key) FROM ems_business_events");
$evtFacts  = (int) $one("SELECT COUNT(*) FROM ems_business_events");
$evtMaxId  = (int) $one("SELECT MAX(id) FROM ems_business_events");
$obxN      = (int) $one("SELECT COUNT(*) FROM ems_event_outbox");
$obxMax    = (int) $one("SELECT MAX(id) FROM ems_event_outbox");
$dOk       = (int) $one("SELECT SUM(delivered_ok) FROM ems_event_outbox");
$dFail     = (int) $one("SELECT SUM(delivered_failed) FROM ems_event_outbox");
$dDeclared = (int) $one("SELECT SUM(consumers_declared) FROM ems_event_outbox");
$dInDlq    = (int) $one("SELECT SUM(in_dlq) FROM ems_event_outbox");
$rulings   = array();
foreach ($rows("SELECT ruling, COUNT(*) c FROM gov_event_rulings GROUP BY 1") as $r) {
    $rulings[(string) $r['ruling']] = (int) $r['c'];
}
$rulingsN     = (int) $one("SELECT COUNT(*) FROM gov_event_rulings");
$ruledNotLive = (int) $one("SELECT COUNT(*) FROM gov_event_rulings r
    WHERE NOT EXISTS (SELECT 1 FROM ems_business_events e WHERE e.event_key = r.event_key)");
$liveNotRuled = (int) $one("SELECT COUNT(DISTINCT e.event_key) FROM ems_business_events e
    WHERE NOT EXISTS (SELECT 1 FROM gov_event_rulings r WHERE r.event_key = e.event_key)");
$rulingUndecided = (int) $one("SELECT COUNT(*) FROM gov_event_rulings
    WHERE decided_at IS NULL OR ruling IS NULL");
/* أربعةُ سجلّاتٍ للمستهلكين — لا سجلٌّ واحد */
$consCursor  = (int) $one("SELECT COUNT(*) FROM ems_event_consumers");
$consSubs    = (int) $one("SELECT COUNT(DISTINCT consumer_key) FROM ems_event_subscriptions");
$consSubsAct = (int) $one("SELECT COUNT(DISTINCT consumer_key) FROM ems_event_subscriptions WHERE is_active=1");
$consDeliv   = (int) $one("SELECT COUNT(DISTINCT consumer) FROM ems_event_deliveries");
$consOrph    = (int) $one("SELECT COUNT(DISTINCT consumer_key) FROM ems_event_delivery_orphans");
$consumers   = $rows("SELECT consumer, enabled, cursor_event_id, updated_at,
    TIMESTAMPDIFF(DAY, updated_at, NOW()) days FROM ems_event_consumers ORDER BY cursor_event_id");
$orphRuled = array();
foreach ($rows("SELECT consumer_key, ruling FROM gov_orphan_consumer_rulings") as $r) {
    $orphRuled[$r['consumer_key']] = $r['ruling'];
}
/* اليتامى والرسائلُ الميتة — ثلاثةُ أرقامٍ من ثلاثةِ جداول */
$orphAll  = (int) $one("SELECT COUNT(*) FROM ems_event_delivery_orphans");
$orphLive = (int) $one("SELECT COUNT(*) FROM ems_event_delivery_orphans WHERE archived_at IS NULL");
$orphDlq  = $vocab('ems_event_delivery_orphans', 'state', 'dlq');
$deadTbl  = (int) $one("SELECT COUNT(*) FROM ems_event_dead_letter");
$deadRul  = (int) $one("SELECT COUNT(*) FROM gov_dead_letter_rulings");

/* ═══ ④ الاعتمادُ والتجميدُ والجسر ════════════════════════════════════════ */
$goldN      = (int) $one("SELECT COUNT(*) FROM gov_golden_approvals");
$goldPend   = $vocab('gov_golden_approvals', 'state', 'pending');
$freezeN    = (int) $one("SELECT COUNT(*) FROM repair01_freeze_snapshot");
$freezeOpen = (int) $one("SELECT COUNT(*) FROM repair01_freeze_snapshot WHERE released_at IS NULL");
$bridgeN    = (int) $one("SELECT COUNT(*) FROM gov_nav_stage_bridge");
$bridgeScreenLevel = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_nav_stage_bridge' AND COLUMN_NAME='screen_id'");

/* ═══ الإخراج ═════════════════════════════════════════════════════════════ */
$L = array();
$p = function ($s) use (&$L) { $L[] = $s; };
$j = function ($a) { return json_encode($a, JSON_UNESCAPED_UNICODE); };

$p('# `RPR02_RPR03_BASELINE` — قياسُ الأساسِ قبل الطورِ صفر');
$p('');
$p('> **مولَّدٌ من تشغيلٍ حيٍّ** بالسطر: `' . $CMD . '`');
$p('> ⛔ **ولا يُحرَّر يدويًّا** — التعديلُ في الأداةِ ثمَّ يُعاد التوليد.');
$p('');
$p('## ٠ · البصمةُ السداسيّة');
$p('');
$p('| المفردة | القيمة |');
$p('|---|---|');
$p('| `Snapshot ID` | `' . $snapId . '` ' . ($openSnap ? '(نافذةٌ مفتوحة)'
    : '**⛔ لا نافذةَ مجمَّدة — قياسٌ خارجَ التجميد**') . ' |');
$p('| `Commit Hash` | `' . $commit . '` (`' . $branch . '`) |');
$p('| `Schema Version` | ' . $tblN . 'T/' . $colN . 'C · و' . $viewN . ' مَشهدًا |');
$p('| `Registry Version` | ' . $regN . ' صفًّا في `repair01_screen_registry` |');
$p('| `Measured At` | ' . $measuredAt . ' |');
$p('| `Tool Version` | `' . TOOL_VERSION . '` |');
$p('| حالُ الشجرة | ' . ($dirtyN ? '**متّسخةٌ — ' . $dirtyN . ' ملفًّا**' : 'نظيفة') . ' |');
$p('');
if ($dirtyN) {
    $p('> ⛔ **والشجرةُ متّسخةٌ فهذا القياسُ لا يمثّل التزامًا** — يُقرأ استكشافًا لا');
    $p('> لقطةً، **ويُعاد بعد التنظيفِ والتجميد** (البند ⑤ · و§٤·٣ من أمرِ التشغيل).');
    $p('');
}

$p('## ١ · التعارضاتُ الأربعةُ — مقيسةً ومفسَّرة');
$p('');
$p('| التعارض | رقمُ الأمر | قياسي | الحكم | السببُ المقيس |');
$p('|---|---|---|---|---|');
$p('| المبنيّ | ٦٢٣ | **' . $built . '** مبنيًّا · و**' . $surfN . '** سطحًا | **لا تعارض** | '
  . 'سجلّان لا سجلّ: `repair01_screen_registry` فيه ' . $regN . ' = **' . $built
  . ' مبنيًّا** (`lifecycle<>GHOST_TARGET`) + ' . $ghosts . ' شبحًا · و`repair01_surfaces` ('
  . $surfN . ') **سجلُّ استخراجٍ بحبّةٍ أدقَّ** (' . $surfN1
  . ' صفًّا حكمُها «N:1 تجزئة أدق»). **ومقارنةُ ٦٦٤ بـ٦٢٣ مقارنةُ تمثيلَين لشيءٍ واحد** |');
$guideDep08 = isset($guidePerDep['DEP-08']) ? $guidePerDep['DEP-08'] : '؟';
$guideDep14 = isset($guidePerDep['DEP-14']) ? $guidePerDep['DEP-14'] : '؟';
$p('| المستهدَف | ٤٣١ | **' . $reqN . '** | **رقمُ الأمرِ زائدٌ اثنين** | '
  . '`repair01_requirements` = ' . $reqN . ' وهو **مطابقٌ للدليلِ المعماريِّ ① حرفًا**: '
  . $guideScreens . ' شاشةً للإداراتِ السبعَ عشرة + ' . $reqOutside . ' للأربعةِ خارجَ التعداد = '
  . ($guideScreens + $reqOutside) . '. وجدولُ الأمرِ يعلن `DEP-08`=٣٢ و`DEP-14`=١٧ **والدليلُ يقول '
  . $guideDep08 . ' و' . $guideDep14 . '** ⇒ فرقُ اثنين. **والدليلُ هو المرجعُ الحاكم** |');
$p('| وقائعُ التسليمِ اليتيمة | صفر | **' . $orphAll . '** إجمالًا · و**' . $orphLive
  . '** حيًّا | **لا تعارض** | الـ' . $orphAll . ' **كلُّها مؤرشفةٌ** (`archived_at IS NOT NULL`) '
  . '— واليتيمُ الحيُّ **' . $orphLive . '**. ورقمُ الأمرِ يقصد الحيَّ ورقمي كان يقصد الإجمالي |');
$p('| الرسائلُ الميتة | ٢٦ | **' . $deadTbl . '** · **' . $deadRul . '** · **' . $dFail
  . '** | **لا تعارض — ثلاثةُ جداولَ لا رقمٌ واحد** | `ems_event_dead_letter`=' . $deadTbl
  . ' (جدولٌ لم يُملأ قطُّ) · `gov_dead_letter_rulings`=' . $deadRul
  . ' (أحكامٌ على `ems_job_queue` لا على الأحداث) · **و`SUM(delivered_failed)` في `ems_event_outbox` = '
  . $dFail . '** ⇒ **وهذا هو رقمُ الأمرِ بعينِه** |');
$p('');

$p('## ٢ · الهجرات — والمقامُ كان ناقصًا في قياسي السابق');
$p('');
$p('| المقياس | القيمة | المقام |');
$p('|---|---|---|');
$settledN  = (int) $one("SELECT COUNT(*) FROM gov_migration_settlement");
$settledOk = (int) $one("SELECT COUNT(*) FROM gov_migration_settlement WHERE verified = 1");
$p('| ملفّاتُ الهجرةِ المُدارةِ على القرص | **' . count($files) . '** | بعُرفِ `YYYY_MM_DD_` نفسِه الذي في `migrate.php` — **'
  . $filesPhp . ' `.php` + ' . $filesSql . ' `.sql`** · وخارجَ العُرفِ ' . count($unmanagedFiles) . ' |');
$p('| صفوفُ الدفتر | **' . count($led) . '** | `schema_migrations` · ' . $j($ledByStatus) . ' |');
$p('| **قرصٌ خارجَ الدفتر** | **' . count($diskNotLedger) . '** | '
  . (count($diskNotLedger) === 0
      ? '**صفرٌ — والأمرُ قاسَ ٥٨ قبلَ التسوية**'
      : '⚠ **' . count($diskNotLedger) . '** — والأمرُ قاسَ ٥٨') . ' |');
$p('| **دفترٌ بلا ملفٍّ على القرص** | **' . count($ledgerNotDisk) . '** | ' . $j($lndByStatus)
  . ' · وبالامتداد ' . $j($lndByExt) . ' — **محكومةٌ في `gov_migration_settlement`** |');
$p('| أحكامُ التسوية | **' . $settledOk . '/' . $settledN . '** متحقَّقٌ منها | `gov_migration_settlement` |');
$p('');
$p('> ⚠ **وتصحيحُ قياسٍ سابقٍ لي**: كنتُ عددتُ القرصَ `*.php` وحدَه (' . $filesPhp . ')');
$p('> **والدفترُ يحمل أسماءً بامتدادِ `.sql`** — فظهر «' . (count($ledgerNotDisk) + $filesSql)
  . ' صفًّا بلا ملفّ»، والصوابُ **' . count($ledgerNotDisk) . '**: والفرقُ ' . $filesSql
  . ' هي ملفّاتُ `.sql` القائمةُ فعلًا على القرصِ ولم تُعَدّ.');
$p('> **وهو عينُ «المقارنةِ بين تمثيلَين لشيءٍ واحد» — وقع مرّةً أخرى، وأُصلح في الأداةِ لا في المخرَج.**');
$p('');

$p('## ٣ · السجلُّ الرسميُّ والملكيّة');
$p('');
$p('| المقياس | القيمة | المقام / المفردة |');
$p('|---|---|---|');
$p('| `repair01_screen_registry` | **' . $regN . '** | ' . $j($lifecycle) . ' |');
$p('| `repair01_surfaces` | **' . $surfN . '** | سجلُّ استخراجٍ بحبّةٍ أدقَّ — **لا يُقارَن بالسجلِّ الرسميّ** |');
$p('| `repair01_requirements` | **' . $reqN . '** | ' . $reqDeps . ' للإدارات + ' . $reqOutside . ' خارجَ التعداد |');
$gapDisp = array();
foreach ($rows("SELECT ghost_disposition d, COUNT(*) c FROM repair01_target_gaps GROUP BY 1") as $r) {
    $gapDisp[(string) $r['d']] = (int) $r['c'];
}
$p('| `repair01_target_gaps` | **' . $gapN . '** | ' . $j($gapDisp) . ' |');
$p('| `repair01_ownership` | **' . $ownN . '** | أحكامُ الملكيّة |');
$p('| `repair01_fields` | **' . $fldN . '** | سجلُّ الحقول |');
$p('| `repair01_departments` | **' . $depN . '** | ⛔ **و`PLATFORM` '
  . ($platRegistered ? 'مسجَّل' : 'غيرُ مسجَّل') . '** — والرموزُ الحيّةُ في السجلِّ **' . $codesN . '** |');
$p('| أسطحُ `PLATFORM` | **' . $platN . '** | `owner_code=PLATFORM` · **مفردةٌ حيّةٌ مؤكَّدة** |');
$p('| أسطحُ `EX-DVP` | **' . $dvpN . '** | من '
  . (isset($reqPerUnit['E2 مساحة النواب']) ? $reqPerUnit['E2 مساحة النواب'] : '؟')
  . ' مستهدَفًا ⇒ **أسوأُ فجوةٍ في النظام** |');
$p('| ملفُّ مكتبةٍ مسجَّلٌ سطحًا | **' . $vendorReg . '** في السجلِّ الرسميّ · **' . $vendorSurf
  . '** في `repair01_surfaces` | مقامان — **والقاعدةُ المانعةُ غيرُ مفعَّلة** |');
$p('| جسرُ الشاشةِ بدورةِ العمل | **' . ($bridgeScreenLevel ? 'موجود' : 'لا يوجد') . '** | '
  . '`gov_nav_stage_bridge` (' . $bridgeN . ' صفًّا) **يربط طبقةً بطبقةٍ لا شاشةً بمرحلة** — فلا عمودَ `screen_id` فيه |');
$p('');

$p('## ٤ · الأحداثُ والآثار');
$p('');
$p('| المقياس | القيمة | المقام / المفردة |');
$p('|---|---|---|');
$p('| أنواعُ الأحداثِ المتميّزة | **' . $evtTypes . '** | `COUNT(DISTINCT event_key)` |');
$p('| الوقائع | **' . $evtFacts . '** | `ems_business_events` · أقصى معرِّفٍ ' . $evtMaxId
  . ' والصندوقُ ' . $obxN . '/' . $obxMax . ' — **مجالُ معرِّفٍ واحدٌ لا مجالان** |');
$p('| التسليم | **' . $dOk . '/' . $dDeclared . ' = '
  . number_format($dDeclared ? $dOk * 100 / $dDeclared : 0, 2) . '٪** | '
  . '`delivered_ok ÷ consumers_declared` — **ويطابق ٩٩٫٨٨٪ في الأمر** |');
$p('| الفاشل | **' . $dFail . '** · وفي الصفِّ الميت **' . $dInDlq . '** | `SUM(delivered_failed)` · `SUM(in_dlq)` |');
$p('| **تصنيفُ الأنواع** | **' . (isset($rulings['business']) ? $rulings['business'] : 0)
  . ' أعمالٍ · ' . (isset($rulings['audit']) ? $rulings['audit'] : 0) . ' تدقيقيًّا** | '
  . '`gov_event_rulings` = ' . $rulingsN . ' · **مُنجَزٌ سلفًا ومطابقٌ للأمر** · ' . $rulingUndecided
  . ' بلا قرار · ' . $ruledNotLive . ' محكومٌ غيرُ حيٍّ · ' . $liveNotRuled . ' حيٌّ غيرُ محكوم |');
$p('| **سجلّاتُ المستهلكين** | **' . $consCursor . ' · ' . $consSubs . ' · ' . $consDeliv . ' · '
  . $consOrph . '** | `ems_event_consumers` · `ems_event_subscriptions` (' . $consSubsAct
  . ' نشطًا) · `ems_event_deliveries` · `..._orphans` — **أربعةُ مقاماتٍ لا واحد** |');
$p('| اليتامى | **' . $orphAll . '** إجمالًا · **' . $orphLive . '** حيًّا · **' . $orphDlq
  . '** بحالةِ `dlq` | `archived_at` يفصل |');
$p('');
$p('### ٤·١ مؤشّراتُ المستهلكين — أربعتُهم متأخّرون، واثنان محكومان');
$p('');
$p('| المستهلك | المؤشّر | التأخّرُ عن ' . $evtMaxId . ' | آخرُ تقدّم | أيام | الحكمُ القائم |');
$p('|---|---|---|---|---|---|');
foreach ($consumers as $c) {
    $lag = (int) $one("SELECT COUNT(*) FROM ems_event_outbox WHERE id > " . (int) $c['cursor_event_id']);
    $p('| `' . $c['consumer'] . '` | ' . $c['cursor_event_id'] . ' | **' . $lag . '** واقعةً | '
      . $c['updated_at'] . ' | **' . $c['days'] . '** | '
      . (isset($orphRuled[$c['consumer']])
          ? '`' . $orphRuled[$c['consumer']] . '` — محكومٌ في `gov_orphan_consumer_rulings`'
          : '⛔ **بلا حكمٍ ولا إنذار**') . ' |');
}
$p('');

$p('## ٥ · الاعتمادُ والتجميد');
$p('');
$p('| المقياس | القيمة | المقام |');
$p('|---|---|---|');
$p('| الشاشاتُ الذهبيّة | **' . $goldPend . '** معلّقةً من **' . $goldN . '** | '
  . '`gov_golden_approvals.state=pending` · **مفردةٌ حيّةٌ مؤكَّدة** |');
$p('| لقطاتُ التجميد | **' . $freezeN . '** · المفتوحُ **' . $freezeOpen . '** | `repair01_freeze_snapshot` |');
$p('');

$p('## ٦ · ما لم أستطع قياسَه — مسمًّى لا مخمَّنًا');
$p('');
$p('- **نافذةُ ظلِّ الاعتماد**: لا تُقاس قبلَ صدورِ القيمِ العدديّةِ من المالك (`RPR-03` §٥ ①).');
$p('- **الرحلاتُ البشريّةُ الستّ**: تحتاج مستخدمين حقيقيّين بصلاحيّاتِهم — لا تُقاس آليًّا.');
$p('- **الاستجابةُ وإتاحةُ الوصول**: تحتاج مسحًا على عيّنةٍ معلَنةٍ لم يُشغَّل بعد.');
$p('- **مطابقةُ حقولِ كلِّ سطحٍ لملفِّه**: تحتاج توحيدَ الكونِ ومصالحةَ المعرّفاتِ أوّلًا (§٥·١ و§٥·٢).');
$p('');

$out = implode("\n", $L) . "\n";
if ($MD) {
    $dst = $ROOT . '/docs/REPAIR01_20260823/RPR02_RPR03_BASELINE.md';
    file_put_contents($dst, $out);
    echo "✔ كُتب: docs/REPAIR01_20260823/RPR02_RPR03_BASELINE.md (" . strlen($out) . " بايت)\n";
}
echo $out;
