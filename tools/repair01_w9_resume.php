<?php
/**
 * tools/repair01_w9_resume.php — استئنافُ ما أُجِّل بـ`DEC-OPEN-15`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُشغَّل حين يُجيب المالكُ** عن السؤالِ المحفوظِ في
 *   `docs/REPAIR01_20260823/open/DEC-OPEN-15.md`. ويفعل أربعةً بالترتيب:
 *   ① يبذر **سياساتِ الفئاتِ** من جدولِ الافتراضاتِ في ملفِّ الجواب.
 *   ② يحلُّ السياسةَ إلى أعمدةِ كلِّ صنفٍ (‏فئةٌ ⇐ صنفٌ ⇐ جذر).
 *   ③ يبذر حالةَ **الرصيدِ الموروثِ** لكلِّ صنفٍ له رصيدٌ بلا تتبّع.
 *   ④ يستهلك بنودَ التأجيلِ **بإثباتٍ مقيسٍ لا بدعوى**، ويغلق الحاجب.
 *
 * ◆ **ولا فئةَ مُخمَّنةٌ هنا**: الأداةُ تقرأ **جدولَ الافتراضاتِ** في ملفِّ
 *   الجواب. وبلا ملفٍّ **لا تكتب شيئًا** وتخرج برمزٍ غيرِ صفر.
 *
 * ◆ **صيغةُ الجدولِ المقروء** — صفٌّ لكلِّ فئةٍ بتسعةِ حقولٍ مفصولةٍ بـ`|`:
 *   `الفئة | الدفعة | التسلسلي | التصنيع | الصلاحية | الضمان | الانفاذ | الصرف | التاهيل`
 *   والقيمُ `OFF/OPTIONAL/REQUIRED` للخمسِ الأولى، و`WARNING/APPROVAL_REQUIRED/HARD_BLOCK`
 *   للإنفاذ، و`FIFO/FEFO/MANUAL` للصرف، و`ENABLED/DISABLED` للتأهيل.
 *
 * ⛔ **ولا يُغلَق التأجيلُ إلّا بإثباتٍ مقيس**: البندُ يُستهلَك حين يصير
 *   `probe_sql` **غيرَ صفريّ** — لا حين يُقال إنّه نُفِّذ.
 *
 * التشغيل: php tools/repair01_w9_resume.php [--answer=<ملف>] [--report]
 * الخروج : 0 استُؤنف كاملًا · 1 لا ملفَّ جوابٍ أو لم يكتمل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w9_scan.php';
require_once $ROOT . '/app/Services/Warehouse/TrackingPolicyService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\Warehouse\TrackingPolicyService as TPS;

$REPORT = in_array('--report', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w9_one($conn, $sql); };

$answer = '';
foreach ($argv as $a) { if (strpos($a, '--answer=') === 0) { $answer = substr($a, 9); } }
if ($answer === '') { $answer = $ROOT . '/docs/REPAIR01_20260823/open/DEC-OPEN-15.answer.md'; }
if (!is_file($answer)) {
    echo "✘ لا ملفَّ جوابٍ في: $answer\n";
    echo "  السؤالُ ما زال مفتوحًا — انظرْ docs/REPAIR01_20260823/open/DEC-OPEN-15.md\n";
    echo "  ⛔ ولا تُبذَر سياسةٌ بلا جوابِ المالك.\n";
    exit(1);
}

echo "══ استئنافُ ما أُجِّل بـDEC-OPEN-15 ══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n\n" : "\n");

$company = (int) $one("SELECT company_id FROM proc_item WHERE COALESCE(is_deleted,0)=0
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { echo "✘ لا كيانَ ذا أصناف\n"; exit(1); }
$G = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, 0, '', true));
$TODAY = (string) $one("SELECT CURDATE()");

/* ═══ ⓪ مزامنةُ دفترِ التأجيلِ مع مصدرِه — والتغييرُ مُعلَنٌ لا صامت ═══════
   ⚠ **جوابُ المالكِ استبدل الشكلَ فاستبدلت استعلاماتُ الإثبات**: القديمةُ تقيس
     `proc_item_track_rule` (‏عَلَمٌ ثنائيّ) والحقيقةُ صارت في `proc_track_policy`
     (‏ثلاثيٌّ بمستويَين). وإبقاءُ القديمِ يجعل البندَ ينتظر إلى الأبدِ على
     جدولٍ لم يعد يحمل الجواب — **عمًى لا تشدُّد**. والجديدُ صفرٌ قبل الجوابِ
     وغيرُ صفرٍ بعده، فهو إثباتٌ لا تخفيف. والفروقُ تُطبَع صراحةً هنا. */
echo "⓪ مزامنةُ دفترِ التأجيلِ مع مصدرِه ─────────────────────────────\n";
$syncN = 0;
foreach (repair01_w9_deferred_rows() as $d) {
    $cur = (string) $one("SELECT probe_sql FROM repair01_w9_deferred
                           WHERE defer_key = '" . $esc($d['defer_key']) . "'");
    if ($cur === (string) $d['probe_sql']) { continue; }
    echo '  ⇄ ' . $d['defer_key'] . " — استعلامُ الإثباتِ يُستبدَل\n";
    echo '      قديم: ' . mb_substr($cur, 0, 88) . "\n";
    echo '      جديد: ' . mb_substr((string) $d['probe_sql'], 0, 88) . "\n";
    if (!$REPORT) {
        $conn->query("UPDATE repair01_w9_deferred
                         SET part_built   = '" . $esc($d['part_built']) . "',
                             part_waiting = '" . $esc($d['part_waiting']) . "',
                             resume_step  = '" . $esc($d['resume_step']) . "',
                             probe_sql    = '" . $esc($d['probe_sql']) . "'
                       WHERE defer_key = '" . $esc($d['defer_key']) . "'");
    }
    $syncN++;
}
printf("  بنودٌ زُومنت %d\n\n", $syncN);

/* ═══ ① بذرُ سياساتِ الفئاتِ من جدولِ الجواب ═════════════════════════════ */
echo "① سياساتُ الفئاتِ من جدولِ الجواب ─────────────────────────────\n";
$LV = array('OFF', 'OPTIONAL', 'REQUIRED');
$EN = array('WARNING', 'APPROVAL_REQUIRED', 'HARD_BLOCK');
$IS = array('FIFO', 'FEFO', 'MANUAL');
$RQ = array('ENABLED', 'DISABLED');

$live = array();
$r = $conn->query("SELECT DISTINCT category FROM proc_item
                    WHERE COALESCE(is_deleted,0)=0 AND COALESCE(category,'') <> ''");
while ($r && $x = $r->fetch_row()) { $live[trim($x[0])] = true; }

$seeded = 0; $skipped = array(); $unknown = array();
foreach (file($answer, FILE_IGNORE_NEW_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || strpos($ln, '|') === false) { continue; }
    $f = array_map('trim', array_filter(explode('|', $ln), function ($v) { return trim($v) !== ''; }));
    $f = array_values($f);
    if (count($f) !== 9) { continue; }
    list($cat, $lot, $ser, $mfg, $exp, $war, $enf, $iss, $req) = $f;
    if (!in_array($lot, $LV, true) || !in_array($ser, $LV, true) || !in_array($mfg, $LV, true)
        || !in_array($exp, $LV, true) || !in_array($war, $LV, true)
        || !in_array($enf, $EN, true) || !in_array($iss, $IS, true) || !in_array($req, $RQ, true)) {
        continue;   /* سطرُ ترويسةٍ أو نصٌّ لا صفُّ سياسة */
    }
    if (!isset($live[$cat])) { $unknown[] = $cat; continue; }

    /* ⛔ **`FEFO` توجب تتبّعَ الصلاحية** — والقيدُ يمنعه في المخطَّطِ أيضًا */
    if ($iss === 'FEFO' && $exp === 'OFF') {
        $skipped[] = $cat . ' (صرفٌ بالصلاحيةِ وتتبّعُها معطَّل)'; continue;
    }
    $strict = ($lot === 'REQUIRED' || $ser === 'REQUIRED' || $mfg === 'REQUIRED'
               || $exp === 'REQUIRED' || $war === 'REQUIRED');
    $strictWhy = $strict ? 'رفعٌ الى الالزام بجواب المالك في DEC-OPEN-15 لهذه الفئة بعينها' : '';
    $auth = ($enf === 'APPROVAL_REQUIRED') ? 'المسؤول الفني المختص بحسب نوع الصنف' : '';
    $why = 'افتراض الفئة من جواب المالك 2026-08-26 — وضع الانتقال يبدا مرنا ويتدرج';

    /* ⚠ **نسخةٌ جديدةٌ تُقفل سابقتَها** — وإلّا سرَت نسختانِ في يومٍ واحدٍ فصار
         الحلُّ يعتمد ترتيبَ الصفوف. وقع فعلًا: تشغيلانِ متتاليانِ أنشآ اثنَي
         عشرَ صفًّا لستِّ فئاتٍ كلُّها سارية، فضبطَه `W9-26`.
       ◆ **والإقفالُ لا حذف**: السابقةُ تبقى بسجلِّها لأنَّ حركةً وقعت في
         سريانِها تُحاسَب بها — «لا نطبّق القاعدةَ الجديدةَ بأثرٍ رجعيّ». */
    $same = (string) $one("SELECT id FROM proc_track_policy
                            WHERE scope_kind='CATEGORY' AND scope_key='" . $esc($cat) . "'
                              AND lot='" . $esc($lot) . "' AND serial='" . $esc($ser) . "'
                              AND mfg_date='" . $esc($mfg) . "' AND expiry='" . $esc($exp) . "'
                              AND warranty='" . $esc($war) . "' AND expiry_enforce='" . $esc($enf) . "'
                              AND issue_policy='" . $esc($iss) . "' AND requalify='" . $esc($req) . "'
                              AND (effective_to IS NULL OR effective_to >= CURDATE())
                            LIMIT 1");
    if ($same !== '') {
        printf("  ↷ %-14s سياسةٌ مطابقةٌ ساريةٌ سلفًا — لا نسخةَ جديدة\n", $cat);
        $seeded++; continue;
    }
    $ver = (int) $one("SELECT COALESCE(MAX(version),0)+1 FROM proc_track_policy
                        WHERE scope_kind='CATEGORY' AND scope_key='" . $esc($cat) . "'");
    if (!$REPORT && $ver > 1) {
        $conn->query("UPDATE proc_track_policy
                         SET effective_to = DATE_SUB('" . $esc($TODAY) . "', INTERVAL 1 DAY)
                       WHERE scope_kind='CATEGORY' AND scope_key='" . $esc($cat) . "'
                         AND effective_to IS NULL");
    }
    if ($REPORT) {
        printf("  ↷ %-14s lot=%-8s ser=%-8s mfg=%-8s exp=%-8s war=%-8s %s %s %s\n",
            $cat, $lot, $ser, $mfg, $exp, $war, $enf, $iss, $req);
        $seeded++; continue;
    }
    $ok = $conn->query("INSERT INTO proc_track_policy
        (company_id, scope_kind, scope_key, version, effective_from, effective_to,
         lot, serial, mfg_date, expiry, warranty, expiry_enforce, issue_policy, requalify,
         override_authority, why, strict_why, decision_ref, changed_by, approved_by)
        VALUES (NULL,'CATEGORY','" . $esc($cat) . "'," . $ver . ",'" . $esc($TODAY) . "',NULL,
                '" . $esc($lot) . "','" . $esc($ser) . "','" . $esc($mfg) . "','" . $esc($exp) . "',
                '" . $esc($war) . "','" . $esc($enf) . "','" . $esc($iss) . "','" . $esc($req) . "',
                '" . $esc($auth) . "','" . $esc($why) . "','" . $esc($strictWhy) . "','DEC-OPEN-15',0,0)");
    if ($ok) {
        printf("  ✔ %-14s lot=%-8s ser=%-8s mfg=%-8s exp=%-8s war=%-8s %s %s %s · نسخة %d\n",
            $cat, $lot, $ser, $mfg, $exp, $war, $enf, $iss, $req, $ver);
        $seeded++;
    } else {
        echo '  ✘ ' . $cat . ' — ' . $conn->error . "\n";
        $skipped[] = $cat;
    }
}
printf("  فئاتٌ مبذورة %d · مردودةٌ %d%s · خارجَ الدليلِ الحيِّ %d%s\n\n",
    $seeded, count($skipped), $skipped ? ' ⇐ ' . implode('، ', array_slice($skipped, 0, 2)) : '',
    count($unknown), $unknown ? ' ⇐ ' . implode('، ', array_slice(array_unique($unknown), 0, 3)) : '');
if ($seeded === 0) { echo "✘ لا سياسةَ واحدةً قُرئت من ملفِّ الجواب — لا استئناف\n"; exit(1); }

/* ═══ ② حلُّ السياسةِ إلى أعمدةِ كلِّ صنف ══════════════════════════════ */
echo "② حلُّ السياسةِ على الأصناف ───────────────────────────────────\n";
/* ⚠ **الحلُّ يمرُّ على كلِّ كيانٍ لا على كيانِ السياقِ وحدَه**: `proc_item`
     جدولُ مستأجِرٍ والبوّابةُ تعزله، فحلقةٌ ببوّابةِ كيانٍ واحدٍ تترك أصنافَ
     البقيّةِ بلا سياسة — وقعت فعلًا: عشرةٌ من أحدٍ وعشرين. */
$resolved = 0; $byLevel = array();
if (!$REPORT) {
    $cos = array();
    $rc = $conn->query("SELECT DISTINCT company_id FROM proc_item WHERE COALESCE(is_deleted,0)=0");
    while ($rc && $x = $rc->fetch_row()) { $cos[] = (int) $x[0]; }
    foreach ($cos as $co) {
        $Gc = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($co, 0, '', true));
        foreach ($Gc->select('proc_item', array('limit' => 5000)) as $it) {
            $p = TPS::materialize($Gc, (int) $it['id'], $TODAY);
            $resolved++;
            $k = $p['scope'] . ':' . $p['lot'] . '/' . $p['serial'] . '/' . $p['expiry'];
            $byLevel[$k] = isset($byLevel[$k]) ? $byLevel[$k] + 1 : 1;
        }
    }
    printf("  كياناتٌ مُرَّ عليها %d\n", count($cos));
}
$reqN = (int) $one("SELECT COUNT(*) FROM proc_item
                     WHERE track_lot_level='REQUIRED' OR track_serial_level='REQUIRED'
                        OR track_mfg_level='REQUIRED' OR track_expiry_level='REQUIRED'
                        OR track_warranty_level='REQUIRED'");
$optN = (int) $one("SELECT COUNT(*) FROM proc_item
                     WHERE track_lot_level='OPTIONAL' OR track_serial_level='OPTIONAL'
                        OR track_mfg_level='OPTIONAL' OR track_expiry_level='OPTIONAL'
                        OR track_warranty_level='OPTIONAL'");
$noPol = (int) $one("SELECT COUNT(*) FROM proc_item WHERE COALESCE(policy_scope,'') IN ('','NONE')");
printf("  أصنافٌ حُلَّت %d · بخاصيّةٍ اختياريّةٍ %d · بخاصيّةٍ إلزاميّةٍ %d · بلا سياسةٍ %d\n",
    $resolved, $optN, $reqN, $noPol);
foreach ($byLevel as $k => $n) { printf("    %-34s %d\n", $k, $n); }
echo "\n";

/* ═══ ③ الرصيدُ الموروثُ — لا يُخترَع له تتبّع ═══════════════════════════ */
echo "③ الرصيدُ الموروثُ غيرُ المتتبَّع ──────────────────────────────\n";
$legacyN = 0; $legacyQty = 0.0;
if (!$REPORT) {
    $r = $conn->query("SELECT s.id, s.item_id, s.warehouse_id, s.qty
                         FROM proc_stock_state s
                        WHERE s.state_key = 'GOOD' AND s.qty > 0");
    $rows = array();
    while ($r && $x = $r->fetch_assoc()) { $rows[] = $x; }
    foreach ($rows as $x) {
        $it = $G->selectOne('proc_item', array('where' => array('id' => (int) $x['item_id'])));
        if (!$it) { continue; }
        $tracked = ((string) $it['track_lot_level'] !== 'OFF' || (string) $it['track_serial_level'] !== 'OFF');
        if (!$tracked) { continue; }
        $lotQty = 0.0;
        foreach ($G->select('proc_lot', array('where' => array(
            'item_id' => (int) $x['item_id'], 'warehouse_id' => (int) $x['warehouse_id']))) as $l) {
            $lotQty += (float) $l['qty_available'];
        }
        $legacy = (float) $x['qty'] - $lotQty;
        if ($legacy <= 0.0001) { continue; }
        $ex = $G->selectOne('proc_stock_state', array('where' => array(
            'item_id' => (int) $x['item_id'], 'warehouse_id' => (int) $x['warehouse_id'],
            'state_key' => 'LEGACY_UNTRACKED')));
        $data = array('item_id' => (int) $x['item_id'], 'warehouse_id' => (int) $x['warehouse_id'],
            'state_key' => 'LEGACY_UNTRACKED', 'qty' => $legacy,
            'derive_rule' => 'الرصيد الصالح ناقص ما تغطيه دفعات مسجلة — موروث لا يخترع له تتبع');
        if ($ex) { $G->update('proc_stock_state', $data, array('id' => (int) $ex['id'])); }
        else { $G->insert('proc_stock_state', $data); }
        $legacyN++; $legacyQty += $legacy;
    }
}
printf("  أسطرُ رصيدٍ موروثٍ %d · كميّتُه %s\n\n", $legacyN, number_format($legacyQty, 3));

/* ═══ ④ استهلاكُ التأجيلِ وإغلاقُ الحاجب ════════════════════════════════ */
echo "④ استهلاكُ بنودِ التأجيل ──────────────────────────────────────\n";
$consumed = 0; $still = 0;
$pend = array();
$r = $conn->query("SELECT defer_key, probe_sql FROM repair01_w9_deferred WHERE consumed = 0");
while ($r && $x = $r->fetch_assoc()) { $pend[] = $x; }
foreach ($pend as $x) {
    $v = repair01_w9_one($conn, (string) $x['probe_sql']);
    if ($v !== null && (int) $v > 0) {
        if (!$REPORT) {
            $conn->query("UPDATE repair01_w9_deferred SET consumed = 1, consumed_at = NOW()
                           WHERE defer_key = '" . $esc($x['defer_key']) . "'");
        }
        echo '  ✔ ' . $x['defer_key'] . " — الإثباتُ صار غيرَ صفريٍّ ($v) فاستُهلك\n";
        $consumed++;
    } else {
        echo '  ⛔ ' . $x['defer_key'] . " — الإثباتُ ما زال صفرًا فلا يُستهلَك\n";
        $still++;
    }
}
printf("  استُهلك %d · باقٍ %d\n\n", $consumed, $still);

if ($still === 0 && !$REPORT) {
    echo "⑤ إغلاقُ الحاجب ──────────────────────────────────────────────\n";
    /* ⚠ **محوران لا محور** (‏على نمطِ `DEC-OPEN-12`: المحاورُ تُفصل ولا تُدمَج):
         · `blocking_level` **حالةٌ**: أيحجب الآن؟ — و`G0-03` يشترط `NONE`
           لكلِّ معتمَد، فالمُجابُ لا يحجب.
         · `blocker_type` **تصنيفٌ دائم**: أكان سؤالًا بنيويًّا أم عتبةً؟ —
           و`G0-11` يشترط أن يحمله كلُّ `DEC-OPEN` أبدًا، فالجوابُ لا يمحو
           أنَّ السؤالَ كان بنيويًّا.
         ⛔ ورفعُ الاثنَين معًا يُسقط `G0-11` — وقع فعلًا فأُصلح.
         ⚠ **ومحورٌ ثالثٌ كان منسيًّا**: `owner_decision` — **نصُّ الحكمِ نفسُه**.
           فقد كان الصفُّ يصير `APPROVED` وحقلُ حكمِه `—`، والمخزنُ يقول
           «معتمَدٌ» ولا يقول **بماذا**. و`DEC-OPEN-03` و`DEC-OPEN-12` يحملان
           نصَّ مالكِهما (649 و3917 حرفًا) لأنّهما جاءا من الاستيعاب، وهذا
           جاء من المحادثةِ فلم يكتبه أحد. ⛔ **والمخزنُ حكمٌ والوثيقةُ إسقاط**
           — فلا يُترَك الحكمُ في ملفٍّ على القرصِ وحدَه. */
    $ruling = 'النظام يدعم كل مستويات التتبع من البداية، لكن تفعيلها والزاميتها يكونان قابلين '
        . 'للضبط حسب الفئة والصنف ومرحلة نضج البيانات. **Capability Rich · Configuration Flexible '
        . '· Operationally Non Blocking**. '
        . '◆ **ثلاثُ درجاتٍ لا علمٌ ثنائيّ**: OFF لا يُطلَب · OPTIONAL يُدخَل إن توفّر ولا يمنع · '
        . 'REQUIRED لا تكتمل العمليّةُ دونه. ◆ **وثماني خصائصَ لا ثلاث**: الدفعة · الرقم التسلسلي · '
        . 'تاريخ التصنيع · الصلاحية · الضمان · إنفاذ الصلاحية (WARNING / APPROVAL_REQUIRED / HARD_BLOCK) '
        . '· سياسة الصرف (FIFO / FEFO / MANUAL) · إعادة التأهيل (ENABLED / DISABLED). وتاريخُ التصنيعِ '
        . 'وتاريخُ الصلاحيةِ حقلانِ مستقلّانِ قد يوجد أحدُهما دون الآخر. '
        . '◆ **ومستويانِ لا مستوى**: الفئةُ تعطي الافتراضَ والصنفُ يخصّصه، والتخصيصُ يغلب الافتراض. '
        . 'والفئاتُ الستُّ الحيّةُ اتّجاهُها OPTIONAL و WARNING و DISABLED. ⛔ ولا صنفَ يُرفَع إلى '
        . 'REQUIRED في هذه المرحلةِ إلّا بقرارِ صنفٍ مفرد. '
        . '⛔ **والاختياريُّ لا يمنع**: لا أريد أن تتحوّل خصائصُ التتبّعِ إلى متطلَّباتٍ تمنع الاستلامَ '
        . 'أو الصرفَ أو التحويلَ أو الجردَ لمجرّد أنَّ بعضَ البياناتِ التاريخيّةِ أو التشغيليّةِ غيرُ '
        . 'مكتملة. والنقصُ يُسجَّل قيدَ جودةٍ لا حاجبَ عمل. '
        . '◆ **والمتوفّرُ يُستفاد منه بالكامل** فورَ إدخالِه. ◆ **وسلسلةُ ارتدادٍ بلا توقّف**: FEFO إن '
        . 'توفّرت الصلاحيةُ ثمَّ FIFO إن توفّرت تواريخُ الاستلامِ ثمَّ بالكميّة. و FEFO في هذه المرحلةِ '
        . 'اقتراحٌ لا إلزام. '
        . '⛔ **والتجاوزُ بسلطةٍ لا باسم**: لا أريد اسمَ شخصٍ Hardcoded — الصلاحيةُ تُحدَّد بالسياسةِ '
        . 'وسلطةِ الاعتمادِ بحسبِ نوعِ الصنف. **وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عندِه؛ هو ينفّذ فقط.** '
        . '◆ **والموروثُ لا يُخترَع له تتبّع**: لا تمسحِ التاريخَ عند تفعيلِ التتبّع — الرصيدُ القديمُ '
        . 'يبقى LEGACY_UNTRACKED والجديدُ SERIALIZED، ونفسُ الصنفِ قد يحملهما معًا. '
        . '◆ **ولا أثرَ رجعيًّا**: لا نريد تطبيقَ القاعدةِ الجديدةِ بأثرٍ رجعيٍّ على حركةٍ حدثت قبل '
        . 'سنوات — فلكلِّ سياسةٍ نسخةٌ وتاريخُ سريانٍ ومن غيّرها ومن اعتمدها. '
        . '◆ **والمرونةُ في الإعداداتِ لا في الكود**: تغييرُ سياسةِ صنفٍ قرارُ إدارةٍ لا تعديلُ برنامج. '
        . '◆ **ووضعُ الانتقالِ ليس عذرًا دائمًا**: تُقاس نسبُ اكتمالِ البياناتِ وتُعرَض جودةً لا حواجب. '
        . '◆ **ودورةُ إعادةِ التأهيل**: انتهاءٌ أو اقترابٌ ثمَّ حجرٌ اختياريٌّ ثمَّ فحصٌ ثمَّ مستندٌ فنيٌّ '
        . 'ثمَّ تاريخٌ جديدٌ إن اعتُمد ثمَّ عودةٌ للمتاح — و DISABLED لصنفٍ لا تستعمله الشركة. '
        . '⛔ **وبلا إعادةِ بناءِ نظامِ المخزونِ من الصفر.** '
        . '(النصُّ الكاملُ بجداولِه في docs/REPAIR01_20260823/open/DEC-OPEN-15.answer.md)';
    $conn->query("UPDATE repair01_decisions
                     SET status           = 'APPROVED',
                         blocking_level   = 'NONE',
                         owner_decision   = '" . $conn->real_escape_string($ruling) . "',
                         approved_at      = CURDATE(),
                         src_ref          = 'جواب المالك في المحادثة 2026-08-26 › DEC-OPEN-15.answer.md'
                   WHERE decision_id = 'DEC-OPEN-15'");
    printf("  ✔ DEC-OPEN-15 معتمَدٌ ودرجةُ حجبِه رُفعت · وصنفُه البنيويُّ باقٍ تصنيفًا\n");
    printf("  ✔ ونصُّ الحكمِ كُتب في المخزنِ (%d حرفًا) — لا في الوثيقةِ وحدَها\n\n", mb_strlen($ruling));
}

echo "───────────────────────────────────────────────────────────────\n";
echo "الخطوةُ التالية: php tools/repair01_w9_gate.php\n";
echo ($still === 0
    ? "الحكم: استُؤنف كاملًا ✔ — والمرحلةُ تصير قابلةَ الإغلاق\n"
    : "الحكم: استئنافٌ ناقصٌ — $still بندًا ما زال ينتظر ✘\n");
exit($still === 0 ? 0 : 1);
