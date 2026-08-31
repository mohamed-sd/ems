<?php
/**
 * tests/injfix01_bus_stall_alarm_proof.php
 *   شاهدُ إنذارِ تعثُّرِ المستهلك — INJ-FIX-01 · الموجة أ ① · GAP-07
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما يُثبِته** — نصُّ المالك: «وابنِ إنذارَ توقفٍ فعليًّا **بتوقفٍ مقصود**»
 *   وشرطُ الاكتمالِ ١٣: «إنذارُ توقفٍ يغطيها **مجرَّبٌ بإيقافٍ مقصود**».
 *   فلا يكفي أن يُكتب الإنذار — يجب أن يُوقَف مستهلكٌ عمدًا ويُرى الإنذارُ يُطلق.
 *
 * ◆ **ولا يُعبَث بمستهلكٍ حيّ**: التوقفُ المقصودُ يقع على **مستهلكٍ اصطناعيٍّ**
 *   يُنشأ لهذه الجولةِ ويُكنس بعدها، لا على `finance` ولا `finance_routing`.
 *   فإثباتُ الإنذارِ لا يجوز أن يكون هو نفسُه العطبَ الذي يُنذَر عنه.
 *
 * ◆ **والكنسُ بالعائلةِ لا بالجولة**: الوسمُ `injfix_stall_probe%` يُكنس كلُّه
 *   في البدءِ والختام — فجولةٌ سابقةٌ انقطعت لا تُعمي هذه عن نفسِها.
 *
 * ◆ **وأربعُ حالاتٍ تُقاس لا واحدة**: الكشفُ · الإطلاقُ · عدمُ الإغراق ·
 *   والسكوتُ عن السليم. فإنذارٌ يُطلق دائمًا لا يُنذر عن شيء.
 *
 * التشغيل: php tests/injfix01_bus_stall_alarm_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Core/EventDispatcher.php';

$PROBE  = 'injfix_stall_probe';
$FAMILY = 'injfix_stall_probe';          // بادئةُ العائلةِ للكنس
$pass = 0; $fail = 0;

function ok($cond, $label, &$pass, &$fail, $detail = '')
{
    if ($cond) { $pass++; echo "  ✔ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
    else       { $fail++; echo "  ✘ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

/** كنسُ العائلةِ كلِّها — يُنادى في البدءِ والختام. */
function sweep(mysqli $c, $family)
{
    $like = $c->real_escape_string($family);
    $c->query("DELETE FROM `ems_event_consumers` WHERE `consumer` LIKE '{$like}%'");
    $c->query("DELETE FROM `ems_event_deliveries` WHERE `consumer` LIKE '{$like}%'");
    $c->query("DELETE FROM `ems_event_dead_letter` WHERE `consumer` LIKE '{$like}%'");
    $c->query("DELETE FROM `fin_notifications`    WHERE `title` LIKE '[BUS-STALL:{$like}%'");
}

echo "════ شاهدُ إنذارِ تعثُّرِ المستهلك — GAP-07 ════\n";
sweep($conn, $FAMILY);

/* ── لقطةُ الحالةِ قبلَ أيِّ مساس — لتُقارَن بها لقطةُ الختام ─────────────── */
$before = array();
$r = $conn->query("SELECT consumer, enabled, cursor_event_id, updated_at FROM `ems_event_consumers` ORDER BY consumer");
while ($r && $x = $r->fetch_assoc()) { $before[$x['consumer']] = $x; }

/* ══ ① التوقفُ المقصود ═══════════════════════════════════════════════════════
   مستهلكٌ اصطناعيٌّ **له معالجٌ مسجَّل** (فليس يتيمًا) ومؤشرُه خلفَ الواقع
   و`updated_at` أقدمُ من المهلة ⇒ يجب أن يُصنَّف `stalled`. */
$stallSecs = \App\Core\EventDispatcher::CONSUMER_STALL_SECONDS;
$oldStamp  = date('Y-m-d H:i:s', time() - ($stallSecs + 600));

$st = $conn->prepare("INSERT INTO `ems_event_consumers` (`consumer`,`enabled`,`cursor_event_id`,`updated_at`) VALUES (?, 1, 0, ?)");
$st->bind_param('ss', $PROBE, $oldStamp);
$inserted = $st->execute();
$st->close();
ok($inserted, 'أُنشئ مستهلكٌ اصطناعيٌّ موقوفٌ عمدًا', $pass, $fail, "cursor=0 · آخرُ تقدُّمٍ قبلَ " . ($stallSecs + 600) . "s");
if (!$inserted) { sweep($conn, $FAMILY); exit(1); }

/* ── الموزّعُ بمعالجٍ مسجَّلٍ للمِجَسِّ ومعالجاتِ الإنتاج ──────────────────────
   ◆ **المحاكاةُ تُطابق تسجيلَ الإنتاجِ كاملًا** (CLOSURE_SYSTEM · SPRINT-02):
     النسخةُ السابقةُ سجّلت معالجَين ونسيت `fx` بعدما وُصل في الإنتاج
     (FINAL_CLOSE ⑨) — **فقرأت الملتحقَ يتيمًا وأنذرت عنه خطأً**، وتناقض
     الفحصان ③ و⑥ في الشاهدِ نفسِه. فاليتيمُ يُثبَت بمِجَسٍّ اصطناعيٍّ أدناه
     لا بمستهلكِ إنتاجٍ تتقادم عنه العُدّة. */
$d = new \App\Core\EventDispatcher($conn);
$noop = function () { return true; };
$d->register($PROBE, $noop, 0);
$d->register('finance', $noop, 0);
require_once $ROOT . '/app/Services/Finance/RoutingConsumer.php';
$d->register(\App\Services\Finance\RoutingConsumer::NAME, $noop, 0);
require_once $ROOT . '/app/Services/Bus/Consumers/FxRealizationConsumer.php';
$d->register(\App\Services\Bus\Consumers\FxRealizationConsumer::NAME, $noop, 0);

/* ── ② الكشف ─────────────────────────────────────────────────────────────── */
$found = array();
foreach ($d->stalledConsumers() as $s) { $found[$s['consumer']] = $s; }

ok(isset($found[$PROBE]), 'كُشف المستهلكُ الموقوف', $pass, $fail);
ok(isset($found[$PROBE]) && $found[$PROBE]['kind'] === 'stalled',
   'صُنِّف «متعثرًا» لا «يتيمًا» — لأن له معالجًا مسجَّلًا', $pass, $fail,
   isset($found[$PROBE]) ? 'kind=' . $found[$PROBE]['kind'] : 'غائب');

/* ── ③ السكوتُ عن السليم — وإلا فالإنذارُ لا يميّز ───────────────────────────
   ◆ **والشرطُ يُقاس على اللحاقِ لا على الاسم**: النسخةُ الأولى أكّدت أن
     `finance` و`finance_routing` لا يُنذَر عنهما بالاسم. ثم أخلّت جولةُ اختبارٍ
     أخرى بوقائعَ في `fin_financial_events` فتأخّر المؤشرُ فعلًا — **فرسب البندُ
     والإنذارُ صادق**. فالشرطُ الصحيحُ: كلُّ مستهلكٍ **مؤشرُه ملتحقٌ (تأخُّر=0)**
     لا يُنذَر عنه. وهذا ثابتٌ لا يتعلق بحالةِ البيئة. */
$caughtUp = array(); $flaggedCaughtUp = array();
$rc = $conn->query("SELECT consumer, cursor_event_id FROM `ems_event_consumers` WHERE enabled = 1");
while ($rc && $x = $rc->fetch_assoc()) {
    $cur = (int) $x['cursor_event_id'];
    $lg = $conn->query("SELECT COUNT(*) FROM `fin_financial_events`
                         WHERE id > {$cur} AND event_key IS NOT NULL AND COALESCE(is_deleted,0) = 0");
    if ($lg && (int) $lg->fetch_row()[0] === 0) {
        $caughtUp[] = $x['consumer'];
        if (isset($found[$x['consumer']])) { $flaggedCaughtUp[] = $x['consumer']; }
    }
}
ok(count($flaggedCaughtUp) === 0,
   'صفرُ إنذارٍ عن مستهلكٍ مؤشرُه ملتحق', $pass, $fail,
   'الملتحقون: ' . (count($caughtUp) ? implode(' · ', $caughtUp) : 'لا أحد')
   . (count($flaggedCaughtUp) ? ' · أُنذر خطأً: ' . implode(' · ', $flaggedCaughtUp) : ''));

/* ── ④ الإطلاق ───────────────────────────────────────────────────────────── */
$raised = $d->alertStalledConsumers();
ok($raised > 0, 'أُطلق الإنذار', $pass, $fail, "عددُ الإنذارات={$raised}");

$q = $conn->query("SELECT `title`, `link`, `target_level` FROM `fin_notifications`
                    WHERE `title` LIKE '[BUS-STALL:{$PROBE}]%' ORDER BY `id` DESC LIMIT 1");
$note = $q ? $q->fetch_assoc() : null;
ok($note !== null, 'الإنذارُ مكتوبٌ في قناةِ الإشعاراتِ نفسِها (لا قناةَ ثانية)', $pass, $fail);
if ($note) {
    echo "      ↳ «" . $note['title'] . "»\n";
    ok($note['link'] === 'admin/bus_monitor.php', 'الإنذارُ يقود إلى مؤشرِ الناقل', $pass, $fail, $note['link']);
    ok(strpos($note['title'], 'متأخر') !== false, 'الإنذارُ يحمل مقدارَ التأخُّرِ لا مجرَّدَ الاسم', $pass, $fail);
}

/* ── ⑤ عدمُ الإغراق: نداءٌ ثانٍ داخلَ الساعةِ لا يُكرّر ────────────────────── */
$again = $d->alertStalledConsumers();
$q = $conn->query("SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '[BUS-STALL:{$PROBE}]%'");
$total = $q ? (int) $q->fetch_row()[0] : -1;
ok($total === 1, 'نداءٌ ثانٍ داخلَ الساعةِ لم يُكرّر الإنذار', $pass, $fail, "الإجمالي={$total} · الجولةُ الثانية={$again}");

/* ── ⑥ كشفُ اليتيمِ — بمِجَسٍّ اصطناعيٍّ لا بمستهلكِ إنتاج ──────────────────
   ◆ كان الشاهدُ يُثبت اليتمَ بـ`fx` الحيِّ — **وقد وُصل `fx` بمعالجِه في
     الإنتاجِ (FINAL_CLOSE ⑨) فصار الشاهدُ يقيس تقادمَ عُدّتِه هو**. فاليتيمُ
     يُصنع صنعًا: صفٌّ مُفعَّلٌ بلا معالجٍ مسجَّلٍ في هذا الموزّع — ويُكنس
     بعائلةِ المِجَسِّ نفسِها. */
$ORPHAN_PROBE = $PROBE . '_orphan';
$st = $conn->prepare("INSERT INTO `ems_event_consumers` (`consumer`,`enabled`,`cursor_event_id`,`updated_at`) VALUES (?, 1, 0, ?)");
$st->bind_param('ss', $ORPHAN_PROBE, $oldStamp);
$st->execute();
$st->close();
$orphans = array();
foreach ($d->stalledConsumers() as $s) { if ($s['kind'] === 'orphan') { $orphans[] = $s['consumer']; } }
ok(in_array($ORPHAN_PROBE, $orphans, true), 'كُشف اليتيمُ — صفٌّ مُفعَّلٌ بلا معالجٍ مسجَّل', $pass, $fail,
   'اليتامى: ' . (count($orphans) ? implode(' · ', $orphans) : 'لا شيء'));

/* ── الكنسُ واستيثاقُ عدمِ الأثر ──────────────────────────────────────────── */
sweep($conn, $FAMILY);

$after = array();
$r = $conn->query("SELECT consumer, enabled, cursor_event_id, updated_at FROM `ems_event_consumers` ORDER BY consumer");
while ($r && $x = $r->fetch_assoc()) { $after[$x['consumer']] = $x; }

$diff = array();
foreach ($before as $k => $v) {
    if (!isset($after[$k])) { $diff[] = "فُقد {$k}"; continue; }
    foreach (array('enabled', 'cursor_event_id', 'updated_at') as $col) {
        if ((string) $after[$k][$col] !== (string) $v[$col]) { $diff[] = "{$k}.{$col}: {$v[$col]} ⇐ {$after[$k][$col]}"; }
    }
}
foreach ($after as $k => $v) { if (!isset($before[$k])) { $diff[] = "بقيَ زائدًا {$k}"; } }
ok(count($diff) === 0, 'صفرُ أثرٍ على المستهلكين الحقيقيين بعدَ الجولة', $pass, $fail,
   count($diff) ? implode(' · ', $diff) : 'الحالةُ كما كانت حرفًا');

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-07', true, 'إنذارُ توقفِ المستهلكِ يعمل وعتبتُه تتسع لدوريةِ المهمّةِ الحقيقية', $fail);

exit($fail === 0 ? 0 : 1);
