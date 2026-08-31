<?php
/**
 * tools/rpr02_state_author.php — تأليفُ آلاتِ الحالةِ الناقصة (البند ⑬)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` ⑬ · `RPR-02` #٤: أسطحُ معاملاتٍ حقيقيّةٍ كياناتُها بلا آلةِ
 * حالةٍ مؤلَّفة — والتأليفُ حكمُ أعمالٍ **يُؤرَّض بالمقيسِ لا يُخترع**:
 *
 * ◆ ثلاثُ عائلاتٍ بثلاثةِ أُسسِ تأريض:
 *   `E_ENUM`    الكيانُ له عمودُ حالةٍ ENUM — **ترتيبُ المعجمِ في المخطَّطِ هو
 *               دورةُ الحياةِ التي أعلنها مُصمِّمُه**: سلسلةٌ خطّيّةٌ من أوّلِه،
 *               والقيمُ الطرفيّةُ (cancelled · revoked · superseded · closed ·
 *               dead · dlq · rejected · reversed) مخارجُ من الحالاتِ الحيّةِ
 *               قبلَها لا حلقاتٌ فيها.
 *   `E_SCR`     عائلةُ سجلّاتِ `scr_*` — مفرداتُها الحيّةُ المقيسةُ واحدة:
 *               مسودة ← قيد المراجعة ← معتمد · والإلغاءُ من المراجعة ·
 *               والإيقافُ من المعتمَدِ وعودتُه بقرار.
 *   `E_RECORD`  كيانٌ بلا عمودِ حالة — **سجلُّ وقائعَ لا يُحرَّر**: يُنشأ
 *               فيبقى، وتصحيحُه بقيدٍ لاحقٍ لا بتحرير (أعمدةُ الحوكمةِ
 *               `reversed_by`/`reversal_of` حيث وُجدت).
 * ◆ مالكُ الانتقالِ من مالكِ السطحِ في السجلِّ · وبوّابةُ الاعتمادِ حيث توجد
 *   حالةُ اعتمادٍ تُذكر بسلَّمِ الإدارةِ (`gov_ladders`) لا برقمٍ مُخترَع.
 * ◆ المخرَج: `repair01_fc_states` بمخطَّطِ جداولِ الموجاتِ نفسِه —
 *   و`rpr02_state_model_bind.php` يقرؤه كموجةٍ (fc).
 *
 * التراجع: php tools/rpr02_state_author.php --rollback  (يُسقط الجدول)
 * التشغيل: php tools/rpr02_state_author.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);
$RB    = in_array('--rollback', $argv, true);

if ($RB) {
    $conn->query('DROP TABLE IF EXISTS repair01_fc_states');
    exit("  ✔ أُسقط repair01_fc_states\n");
}

/* الجدول بمخطط الموجات نفسه — عبر اتصال المرحل لان ems_app بلا CREATE */
require_once $ROOT . '/includes/env.php';
$mh = ems_env('DB_HOST'); $mp = 3306;
if (strpos($mh, ':') !== false) { list($mh, $mp) = explode(':', $mh); $mp = (int) $mp; }
$mu = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$mw = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
mysqli_report(MYSQLI_REPORT_OFF);
$mc = new mysqli($mh, $mu, $mw, ems_env('DB_NAME'), $mp);
if ($mc->connect_errno) { exit("تعذّر اتصال المرحّل\n"); }
$mc->set_charset('utf8mb4');
$mc->query("CREATE TABLE IF NOT EXISTS repair01_fc_states LIKE repair01_w15_states");
if ($mc->error) { exit("✘ إنشاء الجدول: {$mc->error}\n"); }

/* ── الآلات القائمة (كل الموجات + وثائق W03..W05) ─────────────────────── */
$models = array();
foreach (array('w6','w7','w8','w9','w10','w11','w12','w13','w14','w15','fc') as $w) {
    $q = @$conn->query("SELECT DISTINCT entity FROM repair01_{$w}_states");
    while ($q && ($z = $q->fetch_row())) { $models[strtolower(trim($z[0]))] = 1; }
}
foreach (array('W03','W04','W05') as $w) {
    $src = (string) @file_get_contents($ROOT . "/docs/REPAIR01_20260823/plan/{$w}_STATE_MACHINES.md");
    if (preg_match_all('~^##[^\n]*?`([a-z_][a-z0-9_]*)\.[a-z_][a-z0-9_]*`~mu', $src, $m)) {
        foreach ($m[1] as $k) { $models[strtolower($k)] = 1; }
    }
}
$hasModel = function ($ent) use ($models) {
    return isset($models[$ent]) || isset($models[preg_replace('~e?s$~', '', $ent)]) || isset($models[$ent . 's']);
};

/* ── كيانات الاسطح المعاملة بلا آلة ───────────────────────────────────── */
$r = $conn->query("SELECT DISTINCT s.grain_entity ent, s.owner_code FROM repair01_screen_registry s
                    WHERE s.grain_fact_scope='OWN_FACT' AND s.grain_cardinality IN ('ROW','LINE')
                      AND s.lifecycle LIKE 'LIVE%' AND s.grain_entity <> ''");
$ents = array();
while ($x = $r->fetch_assoc()) {
    $k = strtolower(trim($x['ent']));
    if (!isset($ents[$k])) { $ents[$k] = $x['owner_code']; }
}
$names = array();
$r = $conn->query('SELECT canonical_code, name_ar FROM repair01_departments');
while ($x = $r->fetch_assoc()) { $names[$x['canonical_code']] = $x['name_ar']; }
$names['PLTF'] = 'قدرة منصية مشتركة';

$TERMINAL = array('cancelled','revoked','superseded','closed','closed_cancelled','closed_done',
                  'dead','dlq','rejected','reversed','terminated','expired','liquidation','admin_closed','withdrawn');

$ins = $conn->prepare("INSERT INTO repair01_fc_states
    (entity, from_state, to_state, allowed, owner_role, precondition, official_doc, approval_gate,
     reopen_rule, correct_rule, forbid_why, src_ref)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
$put = function ($ent, $f, $t, $al, $own, $pre, $doc, $gate, $reopen, $correct, $why, $src) use ($ins, $APPLY) {
    if (!$APPLY) { return; }
    $ins->bind_param('sssissssssss', $ent, $f, $t, $al, $own, $pre, $doc, $gate, $reopen, $correct, $why, $src);
    if (!$ins->execute()) { echo "    ✘ $ent $f→$t: " . $ins->error . "\n"; }
};

echo "═══ البند ⑬ — تأليفُ آلاتِ الحالة" . ($APPLY ? '' : ' · DRY') . " ═══\n";
$made = array('E_ENUM' => 0, 'E_SCR' => 0, 'E_RECORD' => 0);
foreach ($ents as $ent => $ownCode) {
    if ($hasModel($ent)) { continue; }
    $ownerName = isset($names[$ownCode]) ? $names[$ownCode] : $ownCode;
    $exists = @$conn->query("SELECT 1 FROM `$ent` LIMIT 0");
    if (!$exists) { echo "  ⛔ $ent — لا جدولَ له، لا يُؤلَّف لما لا وجودَ له\n"; continue; }

    /* عمود الحالة */
    $stateCol = ''; $enumVals = array(); $freeVals = array();
    $q = $conn->query("SHOW COLUMNS FROM `$ent`");
    while ($q && ($col = $q->fetch_assoc())) {
        if (!preg_match('~^(state|status|.*_state|.*_status)$~', $col['Field'])) { continue; }
        if ($col['Field'] === 'from_state' || $col['Field'] === 'to_state') { continue; }
        $stateCol = $col['Field'];
        if (stripos($col['Type'], 'enum(') === 0 && preg_match_all("~'((?:[^'\\\\]|\\\\.)*)'~u", substr($col['Type'], 5), $mm)) {
            $enumVals = $mm[1];
        }
        break;
    }
    $isScr = (strpos($ent, 'scr_') === 0);
    $src = 'FINAL_CLOSE-13 · مؤرض من ' . ($enumVals ? 'ENUM المخطط `' . $ent . '.' . $stateCol . '`'
          : ($isScr ? 'مفردات عائلة scr_ الحية' : 'خلو المخطط من عمود حالة — سجل وقائع'));

    if ($isScr) {
        /* عائلة scr_: المفردات الحية الواحدة */
        $g = 'اعتماد بسلم الادارة (gov_ladders) بيد غير يد المسودة';
        $put($ent, '—', 'مسودة', 1, $ownerName, 'اكتمال الحقول الالزامية', 'سجل ' . $ent, 'لا اعتماد على الانشاء', 'لا اعادة فتح قبل التسجيل', 'تعديل المسودة بيد منشئها', '', $src);
        $put($ent, 'مسودة', 'قيد المراجعة', 1, $ownerName, 'اكتمال المسودة وشواهدها', 'طلب مراجعة', 'لا اعتماد على الرفع', '—', 'الرد الى المسودة بملاحظة', '', $src);
        $put($ent, 'قيد المراجعة', 'معتمد', 1, $ownerName, 'استيفاء المراجعة', 'محضر اعتماد', $g, 'اعادة الفتح بقرار المعتمد نفسه موثقا', 'التصحيح بنسخة لاحقة لا بتحرير المعتمد', '', $src);
        $put($ent, 'قيد المراجعة', 'ملغي', 1, $ownerName, 'سبب الغاء مكتوب', 'مذكرة الغاء', 'لا اعتماد على الالغاء قبل الاعتماد', 'لا اعادة فتح للملغي — ينشا جديد', '—', '', $src);
        $put($ent, 'معتمد', 'موقوف', 1, $ownerName, 'سبب ايقاف مكتوب', 'قرار ايقاف', $g, 'العودة بقرار معاكس موثق', '—', '', $src);
        $put($ent, 'موقوف', 'معتمد', 1, $ownerName, 'زوال سبب الايقاف', 'قرار اعادة تفعيل', $g, '—', '—', '', $src);
        $put($ent, 'معتمد', 'مسودة', 0, $ownerName, '', '', '', '', '', 'المعتمد لا يعود مسودة — التصحيح بنسخة لاحقة معتمدة', $src);
        $made['E_SCR']++;
    } elseif ($enumVals) {
        $live = array(); $term = array();
        foreach ($enumVals as $v) {
            if (in_array(strtolower($v), $TERMINAL, true)) { $term[] = $v; } else { $live[] = $v; }
        }
        if (!$live) { $live = $enumVals; $term = array(); }
        $put($ent, '—', $live[0], 1, $ownerName, 'اكتمال حقول الانشاء الالزامية', 'سجل ' . $ent, 'لا اعتماد على الانشاء', 'لا اعادة فتح قبل التسجيل', 'تعديل بيد المنشئ قبل التقدم', '', $src);
        for ($i = 0; $i + 1 < count($live); $i++) {
            $gate = preg_match('~approv|post|publish|settle|recogniz~i', $live[$i + 1])
                  ? 'اعتماد بسلم الادارة (gov_ladders) بيد غير يد المتقدم' : 'وفق ضوابط المرحلة';
            $put($ent, $live[$i], $live[$i + 1], 1, $ownerName,
                 'استيفاء شروط «' . $live[$i] . '» المسجلة', 'قيد انتقال ' . $ent, $gate,
                 'الرجوع خطوة بقرار موثق حيث لم يقع اثر مالي', 'التصحيح بحركة لاحقة لا بتحرير', '', $src);
        }
        foreach ($term as $t) {
            foreach ($live as $f) {
                $put($ent, $f, $t, 1, $ownerName, 'سبب «' . $t . '» مكتوب بمستنده', 'قرار ' . $t, 'بيد من يملك الاغلاق لا بيد المنشئ', 'لا اعادة فتح للطرفي الا بقرار مالك موثق', 'التعويض بحركة عكسية لا بتحرير', '', $src);
            }
            $put($ent, $t, $live[0], 0, $ownerName, '', '', '', '', '', 'الحالة الطرفية «' . $t . '» لا تعود حية — ينشا سجل جديد', $src);
        }
        $made['E_ENUM']++;
    } else {
        $put($ent, '—', 'recorded', 1, $ownerName, 'اكتمال حقول الواقعة الالزامية', 'سجل ' . $ent, 'لا اعتماد على القيد — سجل وقائع', 'لا اعادة فتح — السجل لا يحرر', 'التصحيح بقيد لاحق او حركة عاكسة (reversal_of) لا بتحرير', '', $src);
        $put($ent, 'recorded', '—', 0, $ownerName, '', '', '', '', '', 'سجل وقائع لا يحرر ولا يحذف — التصحيح بقيد لاحق', $src);
        $made['E_RECORD']++;
    }
}
echo "  أُلِّف: ENUM={$made['E_ENUM']} · scr={$made['E_SCR']} · سجل وقائع={$made['E_RECORD']}\n";
if ($APPLY) {
    $n = (int) $conn->query('SELECT COUNT(*) c FROM repair01_fc_states')->fetch_assoc()['c'];
    $ne = (int) $conn->query('SELECT COUNT(DISTINCT entity) c FROM repair01_fc_states')->fetch_assoc()['c'];
    echo "  ✔ repair01_fc_states: $n انتقالًا لـ$ne كيانًا\n";
}
