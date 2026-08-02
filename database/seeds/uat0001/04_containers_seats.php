<?php
/**
 * UAT-0001 · بذرة ④ — الحاوياتُ والمقاعدُ والحصص.
 *
 * الهرمُ أربعةُ مستويات: رئيسية (التزامُ نوعِ معدةٍ في عقد) ← مورد (حصةُ مورّدٍ من
 * الالتزام) ← معدة (الوحدةُ التعاقدية · المقعد) ← مشغّل.
 *
 * قاعدتان تفرضهما القاعدةُ نفسُها وأحترمُهما:
 *   · `ck_container_parent`: «رئيسية» بلا أبٍ، وما دونها بأبٍ إلزامًا.
 *   · `ck_container_alloc/consumed`: المخصَّصُ والمستهلَكُ ≤ السقف — **فالسقفُ يُحسب
 *     من الأكبر بين المخطط والمنفَّذ**، إذ المنفَّذُ واقعةٌ لا تُنكر.
 *
 * المصادر: ب04 (الالتزامات) · م10 (161 حصة) · ب05 (450 وحدة تعاقدية).
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

$mapContract = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_contracts.json'), true);
$mapProject  = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_projects.json'), true);
$mapSup      = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_suppliers.json'), true);
$mapEquip    = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_equipment.json'), true);

$MODEL_UNIT = ['إيجار بالساعة' => 'hour', 'مقاولات النقل' => 'ton', 'تخريم بالمتر' => 'meter', 'النقل بالنقلة' => 'trip'];
$WORK_MODEL = ['إيجار بالساعة' => 1, 'مقاولات النقل' => 2, 'تخريم بالمتر' => 3, 'النقل بالنقلة' => 4];

$ctrInfo = [];   // مفتاح العقد => [id, project_id, start, end]
foreach ($mapContract as $key => $cid) {
    $c = uat_one("SELECT id, project_id, actual_start, actual_end FROM contracts WHERE id=?", [$cid]);
    if ($c) $ctrInfo[$key] = $c;
}

// ── ① جمعُ الحقائق قبل الكتابة (السقفُ يُحسب من الأبناء) ─────────────────────

$seats = [];        // key عقد|نوع|مورد => [صفوف ب05]
foreach (uat_json('ب__ب05_الوحدات_التعاقدية') as $r) {
    $ck = trim($r['مفتاح العقد'] ?? '');
    if (!isset($ctrInfo[$ck])) continue;
    $r['_type'] = trim($r['نوع المعدة'] ?? '') ?: 'غير محدد';
    $r['_sup']  = trim($r['المورد'] ?? '');
    $r['_hrs']  = uat_num($r['الساعات المنفذة'] ?? '', 0);
    $r['_plan'] = uat_num($r['إجمالي الساعات المخططة'] ?? '', 0);
    $seats[$ck][$r['_type']][$r['_sup']][] = $r;
}

$shares = [];       // مفتاح العقد => نوع => مورد => صفُّ م10
foreach (uat_json('م__م10_حصص_الموردين_من_العقود') as $r) {
    $ck = trim($r['مفتاح العقد'] ?? '');
    if (!isset($ctrInfo[$ck])) continue;
    $shares[$ck][trim($r['نوع المعدة'] ?? '') ?: 'غير محدد'][trim($r['المورد'] ?? '')] = $r;
}

$oblig = [];        // مفتاح العقد => نوع => صفُّ ب04
foreach (uat_json('ب__ب04_التغطية_التعاقدية') as $r) {
    $ck = trim($r['مفتاح العقد'] ?? '');
    if (!isset($ctrInfo[$ck])) continue;
    $oblig[$ck][trim($r['نوع المعدة'] ?? '') ?: 'غير محدد'] = $r;
}

// ── ② الكتابةُ من الجذر إلى الورقة ───────────────────────────────────────────

$typeIds = [];
foreach ($db->query("SELECT id,type FROM equipments_types") as $t) $typeIds[$t['type']] = (int) $t['id'];

$seq = (int) ($db->query("SELECT COUNT(*) c FROM op_containers WHERE container_no LIKE 'CNT-UAT-%'")->fetch_assoc()['c']);
$nextNo = function () use (&$seq) { $seq++; return sprintf('CNT-UAT-%05d', $seq); };

$mapSeat = [];   // مفتاح الوحدة => container_id

foreach ($ctrInfo as $ck => $c) {
    $cid   = (int) $c['id'];
    $pid   = (int) $c['project_id'];
    $from  = $c['actual_start'];
    $to    = $c['actual_end'];
    $model = explode('|', $ck)[0];
    $unit  = $MODEL_UNIT[$model] ?? 'hour';
    $wm    = $WORK_MODEL[$model] ?? 1;

    foreach ($seats[$ck] ?? [] as $type => $bySup) {
        // ── سقفُ الالتزام: المخطَّطُ من ب04 أو مجموعُ المنفَّذ إن كان أكبر
        $ob        = $oblig[$ck][$type] ?? [];
        $execTotal = 0.0;
        foreach ($bySup as $rows) foreach ($rows as $r) $execTotal += $r['_hrs'];
        $planTotal = uat_num($ob['إجمالي ساعات العقد'] ?? '', 0)
                   ?: (uat_num($ob['الساعات الشهرية للوحدة'] ?? '', 0) * uat_num($ob['عدد أشهر العقد'] ?? '', 0) * uat_num($ob['الوحدات المتعاقد عليها'] ?? '', 1));
        $cap = max($planTotal, $execTotal, 1);

        $mainKey = 'MAIN|' . $ck . '|' . $type;
        $mainId  = uat_one("SELECT id FROM op_containers WHERE company_id=? AND origin_note=? LIMIT 1", [$CO, $mainKey]);
        if ($mainId) { $mainId = (int) $mainId['id']; }
        else {
            $mainId = uat_insert('op_containers', [
                'company_id'  => $CO, 'container_no' => $nextNo(), 'level' => 'رئيسية', 'parent_id' => null,
                'contract_id' => $cid, 'project_id' => $pid, 'unit_type' => $unit, 'work_model' => $wm,
                'cap_qty' => $cap, 'allocated_qty' => 0, 'consumed_qty' => 0, 
                'seat_equipment_type_id' => $typeIds[$type] ?? null,
                'valid_from' => $from, 'valid_to' => $to, 'state' => 'نشطة',
                'origin' => 'عقد', 'origin_note' => $mainKey,
                'is_deleted' => 0, 'created_by' => $actor,
            ]);
            uat_log('op_containers', 'رئيسية');
        }

        $allocMain = 0.0; $consMain = 0.0;

        foreach ($bySup as $supName => $rows) {
            $sid = $mapSup['byName'][$supName] ?? null;
            $shr = $shares[$ck][$type][$supName] ?? [];
            $execSup = 0.0;
            foreach ($rows as $r) $execSup += $r['_hrs'];
            $capSup = max(uat_num($shr['ساعات الحصة الإجمالية'] ?? '', 0), $execSup, 1);
            $capSup = min($capSup, $cap);           // الحصةُ لا تتجاوز الالتزام — قاعدةُ Σ ≤ السقف

            $supKey = 'SUP|' . $ck . '|' . $type . '|' . $supName;
            $row = uat_one("SELECT id FROM op_containers WHERE company_id=? AND origin_note=? LIMIT 1", [$CO, $supKey]);
            if ($row) { $supId = (int) $row['id']; }
            else {
                $supId = uat_insert('op_containers', [
                    'company_id' => $CO, 'container_no' => $nextNo(), 'level' => 'مورد', 'parent_id' => $mainId,
                    'contract_id' => $cid, 'project_id' => $pid, 'unit_type' => $unit, 'work_model' => $wm,
                    'cap_qty' => $capSup, 'allocated_qty' => 0, 'consumed_qty' => min($execSup, $capSup),
                    
                    'supplier_id' => $sid, 'seat_equipment_type_id' => $typeIds[$type] ?? null,
                    'valid_from' => $from, 'valid_to' => $to, 'state' => 'نشطة',
                    'origin' => 'مشتقّة', 'origin_note' => $supKey,
                    'is_deleted' => 0, 'created_by' => $actor,
                ]);
                uat_log('op_containers', 'مورد');
            }
            $allocMain += $capSup; $consMain += min($execSup, $capSup);

            // ── المقاعد: الوحدةُ التعاقدية وعاءٌ ثابتٌ تتعاقب عليه المعدات ──────
            $allocSup = 0.0;
            foreach ($rows as $r) {
                $seatNo = uat_int($r['رقم الوحدة في العقد'] ?? '', 0);
                if (!$seatNo) continue;
                $ukey = trim($r['مفتاح الوحدة'] ?? '');
                $ex = uat_one("SELECT id FROM op_containers WHERE company_id=? AND contract_id=? AND seat_no=? LIMIT 1", [$CO, $cid, $seatNo]);
                if ($ex) { $mapSeat[$ukey] = (int) $ex['id']; continue; }

                $capSeat = max($r['_plan'], $r['_hrs'], 1);
                $capSeat = min($capSeat, $capSup);
                $eqCode  = trim($r['المعدة الحالية'] ?? '');
                $sid2    = $mapEquip[$eqCode] ?? null;

                $id = uat_insert('op_containers', [
                    'company_id' => $CO, 'container_no' => $nextNo(), 'level' => 'معدة', 'parent_id' => $supId,
                    'contract_id' => $cid, 'project_id' => $pid, 'unit_type' => $unit, 'work_model' => $wm,
                    'cap_qty' => $capSeat, 'allocated_qty' => 0, 'consumed_qty' => min($r['_hrs'], $capSeat),
                    
                    'supplier_id' => $sid, 'equipment_id' => $sid2,
                    'seat_no' => $seatNo,
                    'seat_kind' => (mb_strpos($r['حالة التغطية'] ?? '', 'احتياط') !== false) ? 'احتياطي' : 'أساسي',
                    'seat_equipment_type_id' => $typeIds[$type] ?? null,
                    'contract_hours_monthly' => uat_int($r['الساعات الشهرية المخططة'] ?? '', 0),
                    'valid_from' => uat_date($r['أول تشغيل'] ?? '') ?: $from,
                    'valid_to'   => uat_date($r['آخر تشغيل'] ?? '') ?: $to,
                    'state'      => 'نشطة',
                    'origin'     => 'مشتقّة',
                    'origin_note' => mb_substr('SEAT|' . $ukey . ' · معدات: ' . ($r['المعدات'] ?? ''), 0, 255),
                    'is_deleted' => 0, 'created_by' => $actor,
                ]);
                $mapSeat[$ukey] = $id;
                $allocSup += $capSeat;
                uat_log('op_containers', 'مقعد');
            }
            if ($allocSup > 0) {
                $db->query("UPDATE op_containers SET allocated_qty = LEAST($allocSup, cap_qty) WHERE id = $supId");
            }
        }

        // remaining_qty عمودٌ مولَّد — يُحسب في القاعدة ولا يُكتب
        $db->query("UPDATE op_containers SET allocated_qty = LEAST($allocMain, cap_qty), consumed_qty = LEAST($consMain, cap_qty) WHERE id = $mainId");
    }
}

file_put_contents(UAT_IMPORT_DIR . '/_map_seats.json', json_encode($mapSeat, JSON_UNESCAPED_UNICODE));

uat_print_report('البذرة ④ · الحاويات والمقاعد');
foreach ($db->query("SELECT level, COUNT(*) n, ROUND(SUM(cap_qty)) cap, ROUND(SUM(consumed_qty)) used FROM op_containers WHERE company_id=$CO GROUP BY level") as $r) {
    printf("   %-10s %5d حاوية · السقف %12s · المستهلك %12s\n", $r['level'], $r['n'], number_format((float) $r['cap']), number_format((float) $r['used']));
}
