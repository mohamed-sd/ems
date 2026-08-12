<?php
/**
 * tools/unit_cycle_reseed.php — تفريغُ دورةِ الوحدةِ وإعادةُ بذرِها بما يمرُّ بالمحطات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قرارُ مالكٍ مؤرَّخ (2026-08-11):** «احذف كلَّ بياناتِ الدورةِ وأعِد بذرَها»
 *   — بعد عرضِ الأثرِ عليه مقيسًا. وهو استثناءٌ **صريحٌ ومحدودُ النطاق** من قاعدةِ
 *   «لا حذفَ» (CS-08)، سببُه أن البياناتِ **بذرُ عرضٍ** (48,379 من 48,525 أُنشئت
 *   في يومٍ واحد) لم تمرَّ بمحطاتِ الاعتمادِ التي بُنيت بعدها.
 *
 * ◆ **والنطاقُ مقيسٌ لا مُفترَض.** خريطةُ التبعيةِ كشفت أن ثلاثةً مما يبدو «من
 *   الدورة» **ليس منها**، فاستُثنيت صراحةً:
 *     · `unit_party_awards` (13,468) — `source_kind='timesheet'` مشتقٌّ من جدولِ
 *       `timesheet` القديمِ لا من `unit_entries`، وأُنشئ **قبل** هذه البيانات.
 *     · `fin_financial_events` (5,257) — **5,204 منها `source_module='movement'`**.
 *     · `claims` (298) — **285 منها `invoiced`**: فواتيرُ صادرةٌ عليها
 *       `tax_invoices` بمفتاحٍ أجنبيّ. **وحذفُ فاتورةٍ صادرةٍ ليس تنظيفَ بذرة.**
 *   ◆ فإن أراد المالكُ تفريغَ هذه أيضًا فذاك **قرارٌ ثانٍ** لا يُستنتج من الأول.
 *
 * ◆ ولا يُحذف حرفٌ قبل **نسخةٍ احتياطيةٍ مُتحقَّقٍ من حجمِها** — والفشلُ في النسخِ
 *   يوقف كلَّ شيء.
 *
 * التشغيل:
 *   php tools/unit_cycle_reseed.php --backup        # النسخُ وحدَه
 *   php tools/unit_cycle_reseed.php --wipe          # حذفُ النطاقِ المقيس
 *   php tools/unit_cycle_reseed.php --seed=10000    # البذرُ بما يمرُّ بالمحطات
 *   php tools/unit_cycle_reseed.php --all           # الثلاثةُ بالترتيب
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$doBackup = false; $doWipe = false; $seedN = 0;
foreach ($argv as $a) {
    if ($a === '--backup') { $doBackup = true; }
    if ($a === '--wipe')   { $doWipe = true; }
    if ($a === '--all')    { $doBackup = true; $doWipe = true; $seedN = 10000; }
    if (strpos($a, '--seed=') === 0) { $seedN = (int) substr($a, 7); }
}
if (!$doBackup && !$doWipe && $seedN === 0) { exit("مرّر --backup أو --wipe أو --seed=N أو --all\n"); }

/** النطاقُ المقيسُ — ترتيبُ الحذفِ يتبع اتجاهَ المفاتيحِ: الابنُ قبلَ الأب. */
$SCOPE = array('unit_capacity_flags', 'unit_approvals', 'unit_time_log', 'unit_entries');
$CO = 4;

function q1($db, $sql) { $r = $db->query($sql); return $r ? $r->fetch_row() : null; }
function cnt($db, $t) { $r = q1($db, "SELECT COUNT(*) FROM `{$t}`"); return $r ? (int) $r[0] : -1; }

echo "══════════════════════════════════════════════════════════════════════\n";
echo " دورةُ الوحدة: تفريغٌ وإعادةُ بذر — " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";
echo "\nالنطاقُ المقيس: " . implode(' · ', $SCOPE) . "\n";
echo "المستثنى صراحةً: unit_party_awards · fin_financial_events · claims · claim_lines\n";
echo "  (مصادرُها غيرُ `unit_entries` — وفيها 285 فاتورةً صادرة)\n\n";
foreach ($SCOPE as $t) { echo '  ' . str_pad($t, 24) . cnt($db, $t) . " صفًّا\n"; }

/* ══ ① النسخُ الاحتياطيّ ═══════════════════════════════════════════════ */
if ($doBackup) {
    echo "\n① النسخُ الاحتياطيّ\n";
    $dir = $ROOT . '/storage/backups/unit_cycle_' . date('Ymd_His');
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) { fwrite(STDERR, "تعذّر إنشاءُ مجلدِ النسخ\n"); exit(1); }
    $totalRows = 0;
    foreach ($SCOPE as $t) {
        $file = $dir . '/' . $t . '.sql';
        $fh = @fopen($file, 'w');
        if (!$fh) { fwrite(STDERR, "تعذّر فتحُ {$file}\n"); exit(1); }
        $cr = q1($db, "SHOW CREATE TABLE `{$t}`");
        fwrite($fh, "-- {$t}\nDROP TABLE IF EXISTS `{$t}`;\n" . $cr[1] . ";\n\n");
        $rs = $db->query("SELECT * FROM `{$t}`", MYSQLI_USE_RESULT);
        $n = 0; $buf = array();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $vals = array();
            foreach ($row as $v) { $vals[] = ($v === null) ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'"; }
            $buf[] = '(' . implode(',', $vals) . ')';
            $n++;
            if (count($buf) >= 500) {
                fwrite($fh, "INSERT INTO `{$t}` VALUES\n" . implode(",\n", $buf) . ";\n");
                $buf = array();
            }
        }
        if ($rs) { $rs->free(); }
        if ($buf) { fwrite($fh, "INSERT INTO `{$t}` VALUES\n" . implode(",\n", $buf) . ";\n"); }
        fclose($fh);
        $sz = filesize($file);
        $totalRows += $n;
        echo '    ✔ ' . str_pad($t, 24) . $n . ' صفًّا · ' . round($sz / 1024) . " ك\n";
        if ($n > 0 && $sz < 100) { fwrite(STDERR, "نسخةٌ فارغةٌ لجدولٍ غيرِ فارغ — يتوقف\n"); exit(1); }
    }
    echo '    المجلد: ' . $dir . "\n";
    echo '    مجموعُ الصفوفِ المنسوخة: ' . $totalRows . "\n";
    file_put_contents($dir . '/MANIFEST.txt',
        "نسخةُ دورةِ الوحدةِ قبلَ التفريغ\n" . date('c') . "\nصفوف: {$totalRows}\n"
      . 'جداول: ' . implode(', ', $SCOPE) . "\n"
      . "الاستعادة: mysql -u<user> -p <db> < كلُّ ملفٍّ بترتيبِ: unit_entries ثم البقية\n");
    if ($totalRows < 1000) { fwrite(STDERR, "عددُ الصفوفِ المنسوخةِ أقلُّ من المتوقع — يتوقف\n"); exit(1); }
}

/* ══ ② التفريغ ════════════════════════════════════════════════════════ */
if ($doWipe) {
    echo "\n② التفريغُ (بترتيبِ المفاتيح)\n";
    $bk = glob($ROOT . '/storage/backups/unit_cycle_*');
    if (!$bk) { fwrite(STDERR, "لا نسخةَ احتياطيةً — لا يُحذف شيء\n"); exit(1); }
    echo '    نسخةٌ موجودةٌ: ' . basename(end($bk)) . "\n";
    foreach ($SCOPE as $t) {
        $before = cnt($db, $t);
        if (!$db->query("DELETE FROM `{$t}`")) { fwrite(STDERR, "حذف {$t}: " . $db->error . "\n"); exit(1); }
        $after = cnt($db, $t);
        echo '    ✔ ' . str_pad($t, 24) . $before . ' ← ' . $after . "\n";
    }
    /* الآثارُ المشتقةُ من مدخلاتٍ لم تعد موجودة */
    $orph = q1($db, "SELECT COUNT(*) FROM unit_effects WHERE source_unit_id IS NOT NULL
                      AND NOT EXISTS (SELECT 1 FROM unit_entries e WHERE e.id = unit_effects.source_unit_id)");
    echo '    · آثارٌ صارت بلا مدخلٍ مصدر: ' . ($orph ? $orph[0] : '?') . " (تُعلَن ولا تُمسّ — بذرُ عرضٍ لأثرَي primary/financial)\n";
}

/* ══ ③ البذرُ بما يمرُّ بالمحطات ══════════════════════════════════════ */
if ($seedN > 0) {
    echo "\n③ البذرُ: {$seedN} مدخلًا يمرُّ بالمحطات\n";
    /* مراجعُ حيةٌ من القاعدة — لا أرقامَ مخترعة */
    $pick = static function ($db, $sql) {
        $out = array(); $rs = $db->query($sql);
        while ($rs && ($x = $rs->fetch_row())) { $out[] = (int) $x[0]; }
        return $out;
    };
    $eq = $pick($db, "SELECT id FROM equipments WHERE company_id={$CO} LIMIT 40");
    $op = $pick($db, "SELECT id FROM employees WHERE company_id={$CO} LIMIT 40");
    $ct = $pick($db, "SELECT id FROM contracts WHERE company_id={$CO} LIMIT 20");
    /* ◆ الجدولُ اسمُه `project` **مفردًا** لا `projects` — افترضتُ الجمعَ فأخفق
         البذرُ بعد التفريغ. والاسمُ يُقاس من `information_schema` لا يُخمَّن. */
    $pr = $pick($db, "SELECT id FROM project WHERE company_id={$CO} LIMIT 20");
    if (!$pr) { $pr = $pick($db, 'SELECT id FROM project LIMIT 20'); }
    if (!$eq || !$op || !$pr) {
        fwrite(STDERR, "لا معداتٍ أو مشغّلين أو مشاريع — لا يُبذر على فراغ\n"); exit(1);
    }
    echo '    مراجعُ حية: معدات=' . count($eq) . ' · مشغّلون=' . count($op)
       . ' · عقود=' . count($ct) . ' · مشاريع=' . count($pr) . "\n";

    /* توزيعُ الحالاتِ — يمثّل دورةً حيةً لا كتلةً واحدة */
    $DIST = array('draft' => 8, 'submitted' => 12, 'site_approved' => 15, 'parties_review' => 15,
                  'parties_approved' => 15, 'sales_approved' => 15, 'converted' => 20);
    /* المحطاتُ المقطوعةُ لكلِّ حالة — الاعتمادُ يُسجَّل صفًّا في unit_approvals */
    $STAGES = array(
        'draft' => array(), 'submitted' => array(),
        'site_approved'    => array('site'),
        'parties_review'   => array('site'),
        'parties_approved' => array('site', 'supplier', 'operator'),
        'sales_approved'   => array('site', 'supplier', 'operator', 'sales'),
        'converted'        => array('site', 'supplier', 'operator', 'sales', 'finance'),
    );
    $cols = array(); $rs = $db->query('SHOW COLUMNS FROM unit_entries');
    while ($rs && ($x = $rs->fetch_assoc())) { $cols[$x['Field']] = $x; }
    $has = static function ($c) use ($cols) { return isset($cols[$c]); };

    $states = array();
    foreach ($DIST as $s => $pct) { for ($i = 0; $i < (int) round($seedN * $pct / 100); $i++) { $states[] = $s; } }
    while (count($states) < $seedN) { $states[] = 'converted'; }
    $states = array_slice($states, 0, $seedN);

    /* ◆ الأعمدةُ الإلزاميةُ مقيسةٌ من المخطَّطِ لا مفترَضة:
         `entry_no` · `entry_date` · `project_id` · `unit_type` · `qty` — وأولُ
         صياغةٍ كتبت `work_date` و`operator_id` فأخفقت، لأني افترضتُ الأسماءَ. */
    $ins = 0; $appr = 0; $logs = 0;
    $batch = array(); $day0 = strtotime('2026-06-01');
    $stamp = date('ymdHis');
    for ($i = 0; $i < $seedN; $i++) {
        $st = $states[$i];
        $d = date('Y-m-d', $day0 + (($i % 60) * 86400));
        $e = $eq[$i % count($eq)];
        $o = $op[$i % count($op)];
        $p = $pr[$i % count($pr)];
        $c = $ct ? $ct[$i % count($ct)] : null;
        $qty = number_format(4 + ($i % 8) + (($i % 4) / 4), 2, '.', '');
        $no = 'UE-' . $stamp . '-' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
        $sh = ($i % 5 === 0) ? 'night' : 'day';
        $batch[] = "({$CO}, '{$no}', '{$d}', {$p}, " . ($c === null ? 'NULL' : $c)
                 . ", {$e}, {$o}, 'hour', {$qty}, '{$st}', '{$sh}')";
        if (count($batch) >= 500 || $i === $seedN - 1) {
            $sql = 'INSERT INTO unit_entries
                    (company_id, entry_no, entry_date, project_id, contract_id,
                     equipment_id, operator_employee_id, unit_type, qty, state, shift)
                    VALUES ' . implode(',', $batch);
            if (!$db->query($sql)) {
                fwrite(STDERR, 'إدراجُ المدخلات: ' . $db->error . "\n");
                exit(1);
            }
            $ins += count($batch);
            $batch = array();
        }
    }
    echo "    ✔ مدخلاتٌ: {$ins}\n";

    /* اعتماداتُ المحطاتِ لكلِّ مدخلٍ بحسبِ حالتِه */
    /* ◆ **يدٌ لكلِّ محطةٍ تخالف أختَها** — تطبيقًا لحكمِ المواصفةِ `TS-07-ب`
         و«لا يدَ تمشي خطوتين». والفاعلون من أدوارِهم الحقيقية. */
    $rs = $db->query("SELECT id, state FROM unit_entries WHERE company_id={$CO} ORDER BY id DESC LIMIT {$seedN}");
    $rows = array();
    while ($rs && ($x = $rs->fetch_assoc())) { $rows[] = $x; }
    $ACT = array('site' => 4, 'supplier' => 2, 'operator' => 27, 'sales' => 12, 'finance' => 18);
    $flush = static function ($db, &$batch, &$appr) {
        if (!$batch) { return; }
        if (!$db->query('INSERT INTO unit_approvals (company_id, entry_id, stage, decision, actor_id) VALUES '
                        . implode(',', $batch))) {
            fwrite(STDERR, 'اعتمادات: ' . $db->error . "\n");
            exit(1);
        }
        $appr += count($batch);
        $batch = array();
    };
    $batch = array();
    foreach ($rows as $r) {
        foreach ($STAGES[$r['state']] as $sg) {
            $by = isset($ACT[$sg]) ? $ACT[$sg] : 1;
            $batch[] = "({$CO}, {$r['id']}, '{$sg}', 'approved', {$by})";
        }
        if (count($batch) >= 500) { $flush($db, $batch, $appr); }
    }
    $flush($db, $batch, $appr);
    echo "    ✔ اعتماداتُ محطاتٍ: {$appr}\n";

    /* ══ سجلُّ الوقتِ التفصيليّ — **بإسنادٍ كامل** ═══════════════════════════
         ◆ **عيبٌ مقيسٌ في الصياغةِ الأولى**: بذرت ثلاثَ حالاتٍ تشغيليةٍ فقط
           (`actual_work`·`standby`·`tech_breakdown`) و**كلَّ الأسطرِ بـ
           `resp_party = 'none'`** و`supplier_entity_id = NULL`. فصارت عشرةُ
           آلافِ سطرٍ بلا مسؤولٍ عن توقفٍ — وعائلةٌ من الفواحصِ تسقط لأنها
           تقيس فراغًا، و`INJ-0019` (إسنادُ التوقفات) لا يُثبَت ولا يُنقَض.
           وحارسُ `E-07` (`EMS_RESP_PARTY_STRICT=enforce`) يرفض توقفًا بمسؤولٍ
           `none` — فكان `tech_breakdown` بلا مسؤولٍ **مخالفًا للحكمِ الحيّ**.

         ◆ **والإسنادُ ليس عشوائيًّا**: كلُّ حالةِ توقفٍ تحمل **مسؤولَها
           المنطقيَّ** و**نوعَ التزامِه**، ومَن مسؤولُه الموردُ يحمل **كيانَ
           موردٍ حقيقيًّا** — وإلا كان الإسنادُ اسمًا بلا طرف.

         ◆ ولا يُبذر `plan_period_id`: **لا سجلَّ فتراتٍ في القاعدة**
           (`contract_plan_periods` غيرُ موجود ولا مفتاحَ أجنبيًّا للعمود)،
           فبذرُ رقمٍ فيه اختراعُ مرجعٍ لا وجودَ له. يُعلَن ديْنًا. */
    $OPS = array(
        /* ops_state          resp_party      obligation_type        */
        array('actual_work',        'company', null),
        array('actual_work',        'company', null),
        array('actual_work',        'company', null),
        array('standby',            'client',  'access_road'),
        array('tech_breakdown',     'company', 'equipment_readiness'),
        array('supplier_stop',      'supplier', 'fuel'),
        array('client_stop',        'client',  'loading_equipment'),
        array('operator_stop',      'operator', 'operators'),
        array('fuel_logistics_stop', 'supplier', 'fuel'),
        array('planned_stop',       'planned', 'permits_safety'),
        array('force_majeure',      'force_majeure', 'force_majeure'),
    );
    /* كياناتُ موردينَ حقيقيةٌ — مقيسةٌ لا مفترَضة */
    $SUP = array();
    $rsS = $db->query('SELECT id FROM suppliers WHERE COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 6');
    while ($rsS && ($x = $rsS->fetch_row())) { $SUP[] = (int) $x[0]; }
    if (!$SUP) { $SUP = array(1); }

    $batchL = array();
    $flushL = static function ($db, &$b, &$logs) {
        if (!$b) { return; }
        if (!$db->query('INSERT INTO unit_time_log
                (company_id, log_date, shift, project_id, equipment_id, operator_employee_id,
                 hours, ops_state, resp_party, obligation_type, supplier_entity_id,
                 decided_by, decided_at, entry_id) VALUES ' . implode(',', $b))) {
            fwrite(STDERR, 'سجلُّ الوقت: ' . $db->error . "\n");
            exit(1);
        }
        $logs += count($b);
        $b = array();
    };
    $rs = $db->query("SELECT id, entry_date, shift, project_id, equipment_id, operator_employee_id, qty
                        FROM unit_entries WHERE company_id={$CO} ORDER BY id DESC LIMIT {$seedN}");
    $k = 0;
    $DECIDER = 4;   // مديرُ الموقع — يدُ الإسنادِ في محطتِه
    while ($rs && ($x = $rs->fetch_assoc())) {
        list($ops, $resp, $obl) = $OPS[$k % count($OPS)];
        $h = number_format((float) $x['qty'], 2, '.', '');
        $sh = $x['shift'] !== null ? "'" . $x['shift'] . "'" : 'NULL';
        $oe = $x['operator_employee_id'] !== null ? (int) $x['operator_employee_id'] : 'NULL';
        $oblSql = ($obl === null) ? 'NULL' : "'" . $obl . "'";
        /* المسؤولُ «مورد» يلزمه **كيانُ مورد** — وإلا كان الإسنادُ اسمًا بلا طرف */
        $supSql = ($resp === 'supplier') ? (string) $SUP[$k % count($SUP)] : 'NULL';
        /* واليدُ والوقتُ يُدوَّنان لكلِّ توقفٍ مُسنَد — لا إسنادَ بلا مَن ومتى */
        $isStop = ($ops !== 'actual_work' && $ops !== 'standby');
        $decBy  = $isStop ? (string) $DECIDER : 'NULL';
        $decAt  = $isStop ? "'" . $x['entry_date'] . " 18:00:00'" : 'NULL';
        $batchL[] = "({$CO}, '{$x['entry_date']}', {$sh}, {$x['project_id']}, {$x['equipment_id']}, {$oe}, "
                  . "{$h}, '{$ops}', '{$resp}', {$oblSql}, {$supSql}, {$decBy}, {$decAt}, {$x['id']})";
        if (count($batchL) >= 500) { $flushL($db, $batchL, $logs); }
        $k++;
    }
    $flushL($db, $batchL, $logs);
    echo "    ✔ سطورُ وقتٍ: {$logs}\n";
}

/* ══ الإثبات ══════════════════════════════════════════════════════════ */
echo "\n④ الإثبات\n";
foreach ($SCOPE as $t) { echo '    ' . str_pad($t, 24) . cnt($db, $t) . "\n"; }
$rs = $db->query('SELECT state, COUNT(*) FROM unit_entries GROUP BY state ORDER BY 2 DESC');
echo "    توزيعُ الحالات:\n";
while ($rs && ($x = $rs->fetch_row())) { echo '      ' . str_pad($x[0], 20) . $x[1] . "\n"; }
$rs = $db->query('SELECT stage, COUNT(*) FROM unit_approvals GROUP BY stage');
echo "    اعتماداتٌ بالمحطة:\n";
while ($rs && ($x = $rs->fetch_row())) { echo '      ' . str_pad($x[0], 20) . $x[1] . "\n"; }
$n = q1($db, "SELECT COUNT(*) FROM unit_entries e WHERE e.state <> 'draft' AND e.state <> 'submitted'
               AND NOT EXISTS (SELECT 1 FROM unit_approvals a WHERE a.entry_id = e.id)");
echo '    ◆ مدخلاتٌ تجاوزت المسودةَ بلا أيِّ اعتمادِ محطة: ' . ($n ? $n[0] : '?') . "\n";

/* ══ إثباتُ الإسناد — الحكمُ الذي وُجدت هذه البذرةُ لأجله ═════════════════ */
echo "\n⑤ إثباتُ الإسناد\n";
$rs = $db->query("SELECT ops_state, resp_party, COUNT(*) c, COUNT(supplier_entity_id) sup,
                         COUNT(decided_by) dec_by
                    FROM unit_time_log GROUP BY ops_state, resp_party ORDER BY c DESC");
while ($rs && ($x = $rs->fetch_assoc())) {
    echo '    ' . str_pad($x['ops_state'], 22) . str_pad($x['resp_party'], 16)
       . 'عدد: ' . str_pad($x['c'], 7) . 'بمورد: ' . str_pad($x['sup'], 7)
       . 'بيدٍ: ' . $x['dec_by'] . "\n";
}
$bad = q1($db, "SELECT COUNT(*) FROM unit_time_log
                 WHERE ops_state IN ('tech_breakdown','supplier_stop','operator_stop','client_stop',
                                     'fuel_logistics_stop','planned_stop','force_majeure')
                   AND (resp_party IS NULL OR resp_party = 'none')");
echo '    ◆ توقفاتٌ بلا مسؤولٍ (E-07 يرفضها): ' . ($bad ? $bad[0] : '?') . "\n";
$noSup = q1($db, "SELECT COUNT(*) FROM unit_time_log
                   WHERE resp_party = 'supplier' AND supplier_entity_id IS NULL");
echo '    ◆ مسؤوليةُ موردٍ بلا كيانِ مورد: ' . ($noSup ? $noSup[0] : '?') . "\n";
$noHand = q1($db, "SELECT COUNT(*) FROM unit_time_log
                    WHERE ops_state <> 'actual_work' AND ops_state <> 'standby'
                      AND (decided_by IS NULL OR decided_at IS NULL)");
echo '    ◆ توقفٌ مُسنَدٌ بلا يدٍ أو وقت: ' . ($noHand ? $noHand[0] : '?') . "\n";
echo "\n" . str_repeat('═', 70) . "\n";
exit(0);
