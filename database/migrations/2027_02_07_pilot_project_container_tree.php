<?php
/**
 * 2027_02_07 — شجرةُ حاوياتِ المشروعِ الرائد (بوابةُ الحاويات)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **حالٌ مقيسةٌ متناقضة**: `.env` يعلن `EMS_CONTAINER_GATE=10` — أي أن المشروع
 *   10 («مشروع صافولا») هو **الرائدُ** لبوابةِ الحاويات — و**لا حاويةَ واحدةَ
 *   عليه** (صفرُ صفٍّ في `op_containers`). فبوابةٌ مفتوحةٌ على مشروعٍ بلا شجرةٍ
 *   تُنتج قرارًا بلا سند.
 *
 * ◆ وفاحصان يقفان على هذا **بحقّ**:
 *     `daily_plan_test`     — يثبّت `$P = 10` بتعليقِ «الرائد — شجرتُه مكتملة»
 *                             ثم يسقط في أولِ خطوةٍ (احتياجٌ وُلّد: 0 سطرًا)
 *                             فيتساقط بعده 12 فحصًا.
 *     `container_pilot_test` — «شجرةُ الرائد مكتملةُ المستويات الأربعة —
 *                             **شرطُ الفتح الأول**».
 *   فالحُمرةُ كانت صادقةً: الشرطُ المُعلَنُ غيرُ مستوفٍ في البيانة.
 *
 * ◆ **والنمطُ منقولٌ من شجرةٍ حيّةٍ لا مُختَرع**: المشروع 4 يحمل شجرةً كاملةً
 *   بسبعٍ وعشرين ورقة، وهذه تُحاكيها بنيةً وقيمًا: أربعةُ مستويات
 *   (رئيسية → مورد → معدة → مشغّل) · `unit_type='hour'` · `work_model=1` ·
 *   `origin` «عقد» للجذرِ و«مشتقّة» لما تحته · و`state='نشطة'`.
 *
 * ◆ ويُحترم `ck_container_parent` المُرمَّم (رُمِّم في `2027_02_04`): الجذرُ
 *   وحدَه بلا أب، وما تحته بأبٍ إلزامًا. و`ck_container_alloc`/`ck_container_consumed`
 *   يفرضان `0 ≤ allocated ≤ cap` — فالقيمُ مُختارةٌ لتجتازهما لا لتُعطَّل بهما.
 *
 * ◆ والعقدُ حقيقيٌّ: العقدُ 7 مسجَّلٌ على المشروع 10 (مقيسٌ لا مفترَض).
 * ◆ مُتحمِّلٌ للتكرار: إن وُجدت شجرةٌ لا تُبنى ثانية.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* المشروعُ الرائدُ من العلمِ لا من رقمٍ مكتوب */
$envPilot = (string) ems_env('EMS_CONTAINER_GATE', '');
$PILOT = 0;
foreach (preg_split('/[,\s]+/', $envPilot) as $p) { if (ctype_digit((string) $p)) { $PILOT = (int) $p; break; } }
if ($PILOT <= 0) { echo "  ○ لا مشروعَ رائدًا في EMS_CONTAINER_GATE — لا عمل\n"; exit(0); }

echo "══ شجرةُ الرائد — المشروع {$PILOT} ══\n";
$pr = $db->query("SELECT id, name, company_id FROM project WHERE id = {$PILOT}")->fetch_assoc();
if (!$pr) { fwrite(STDERR, "✘ المشروعُ {$PILOT} غيرُ موجود\n"); exit(1); }
$CO = (int) $pr['company_id'];
echo "  «{$pr['name']}» · شركة {$CO}\n";

$have = (int) $db->query("SELECT COUNT(*) FROM op_containers WHERE project_id = {$PILOT}")->fetch_row()[0];
if ($have > 0) {
    echo "  ○ للمشروعِ {$have} حاويةً سلفًا — لا تُبنى ثانية\n";
} else {
    $ct = $db->query("SELECT id FROM contracts WHERE project_id = {$PILOT} ORDER BY id LIMIT 1")->fetch_row();
    if (!$ct) { fwrite(STDERR, "✘ لا عقدَ على المشروع {$PILOT} — لا تُبنى شجرةٌ بلا عقد\n"); exit(1); }
    $CID = (int) $ct[0];
    echo "  العقدُ الحاملُ: #{$CID}\n";

    /* موردٌ ومعداتٌ ومشغّلونَ حقيقيون — مقيسون */
    $sup = $db->query("SELECT id FROM suppliers WHERE COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1")->fetch_row();
    $SUP = $sup ? (int) $sup[0] : null;
    /* گوتشا مسجَّلةٌ: `equipments` و`employees` **بلا عمود `is_deleted`** —
       فشرطُه يرمي «Unknown column» ويعود `false` فينفجر النداءُ عليه. */
    $eqs = array();
    $r = $db->query("SELECT id FROM equipments WHERE company_id = {$CO} ORDER BY id LIMIT 3");
    while ($r && ($x = $r->fetch_row())) { $eqs[] = (int) $x[0]; }
    $ops = array();
    $r = $db->query("SELECT id FROM employees WHERE company_id = {$CO} ORDER BY id LIMIT 3");
    while ($r && ($x = $r->fetch_row())) { $ops[] = (int) $x[0]; }
    if (!$eqs || !$ops) { fwrite(STDERR, "✘ لا معداتٍ أو مشغّلينَ للبناء\n"); exit(1); }
    echo '  مورد: ' . ($SUP ?: '—') . ' · معدات: ' . implode(',', $eqs) . ' · مشغّلون: ' . implode(',', $ops) . "\n";

    $seq = (int) $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(container_no, 10) AS UNSIGNED)), 0)
                              FROM op_containers WHERE container_no LIKE 'CNT-2026-%'")->fetch_row()[0];
    $no = static function () use (&$seq) { $seq++; return 'CNT-2026-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT); };

    $ins = static function (mysqli $db, array $f) {
        $cols = array(); $vals = array();
        foreach ($f as $k => $v) {
            $cols[] = '`' . $k . '`';
            $vals[] = ($v === null) ? 'NULL'
                    : (is_int($v) || is_float($v) ? (string) $v : "'" . $db->real_escape_string((string) $v) . "'");
        }
        $sql = 'INSERT INTO op_containers (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        if ($db->query($sql) === false) { fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1); }
        return (int) $db->insert_id;
    };

    $base = array('company_id' => $CO, 'contract_id' => $CID, 'project_id' => $PILOT,
                  'unit_type' => 'hour', 'work_model' => 1, 'state' => 'نشطة', 'created_by' => 1);

    /* ① الجذرُ — بلا أبٍ (ck_container_parent) */
    $root = $ins($db, $base + array('container_no' => $no(), 'level' => 'رئيسية', 'parent_id' => null,
        'cap_qty' => 3600.00, 'allocated_qty' => 1200.00, 'consumed_qty' => 0.00, 'origin' => 'عقد'));
    echo "  ✔ جذرٌ #{$root} (سعة 3600 · مخصَّص 1200)\n";

    /* ② المورد */
    $supC = $ins($db, $base + array('container_no' => $no(), 'level' => 'مورد', 'parent_id' => $root,
        'cap_qty' => 1200.00, 'allocated_qty' => 1200.00, 'consumed_qty' => 0.00,
        'supplier_id' => $SUP, 'origin' => 'مشتقّة'));
    echo "  ✔ موردٌ #{$supC} (سعة 1200)\n";

    /* ③ معداتٌ ④ ومشغّلونَ تحتها — الورقةُ التي يبحث عنها الفاحص */
    $leaves = 0;
    foreach ($eqs as $i => $eq) {
        $eqC = $ins($db, $base + array('container_no' => $no(), 'level' => 'معدة', 'parent_id' => $supC,
            'cap_qty' => 400.00, 'allocated_qty' => 400.00, 'consumed_qty' => 0.00,
            'equipment_id' => $eq, 'role_kind' => 'أساسية', 'origin' => 'مشتقّة'));
        $opId = $ops[$i % count($ops)];
        $ins($db, $base + array('container_no' => $no(), 'level' => 'مشغّل', 'parent_id' => $eqC,
            'cap_qty' => 200.00, 'allocated_qty' => 0.00, 'consumed_qty' => 0.00,
            'operator_employee_id' => $opId, 'role_kind' => 'أساسي', 'origin' => 'مشتقّة'));
        $leaves++;
        echo "  ✔ معدةٌ #{$eqC} (معدة {$eq}) ← مشغّلٌ {$opId}\n";
    }
    echo "  ✔ أوراقٌ بمشغّل: {$leaves}\n";
}

/* ══ الإثبات — الشرطُ الذي وُجدت لأجله ═════════════════════════════════ */
echo "\n── إثباتُ اكتمالِ المستويات\n";
$lv = array();
$r = $db->query("SELECT `level`, COUNT(*) c FROM op_containers
                  WHERE project_id = {$PILOT} AND COALESCE(is_deleted,0)=0 AND state='نشطة'
                  GROUP BY `level`");
while ($x = $r->fetch_assoc()) { $lv[$x['level']] = (int) $x['c']; }
foreach (array('رئيسية', 'مورد', 'معدة', 'مشغّل') as $L) {
    $n = isset($lv[$L]) ? $lv[$L] : 0;
    echo '  ' . ($n > 0 ? '✔' : '✘') . ' ' . str_pad($L, 12) . $n . "\n";
}
$leaf = $db->query("SELECT COUNT(*) FROM op_containers c JOIN op_containers e ON e.id = c.parent_id
                     WHERE c.project_id = {$PILOT} AND c.level='مشغّل'
                       AND COALESCE(c.is_deleted,0)=0 AND c.state='نشطة'")->fetch_row()[0];
echo "  ✔ أوراقٌ لها أبٌ معدةٌ (ما يبحث عنه الفاحص): {$leaf}\n";

$ok = (isset($lv['رئيسية']) && isset($lv['مورد']) && isset($lv['معدة']) && isset($lv['مشغّل']) && (int) $leaf > 0);
echo "\n" . ($ok
    ? "✅ الرائدُ مستوفٍ شرطَ الفتحِ الأول — والبوابةُ صارت على سند.\n"
    : "⚠ الشجرةُ ناقصةٌ — يُعلَن\n");
exit($ok ? 0 : 1);
