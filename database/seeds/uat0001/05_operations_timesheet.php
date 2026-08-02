<?php
/**
 * UAT-0001 · بذرة ⑤ — التشغيل: أوامرُ التشغيل والتايم شيت والوحدات وسجلُّ الزمن.
 *
 * المصدرُ الحقيقي: ب07 (1,771 سجلًّا شهريًّا · 86,712 يومَ تشغيل · 427,257 ساعة).
 * والاجتهادُ الوحيد هو **التفكيكُ اليوميّ**: الشهرُ الحقيقيُّ يُوزَّع على أيامه
 * بتفاوتٍ واقعيٍّ (لا خطٍّ مستقيم) مع حفظ المجموع الشهريِّ حرفيًّا.
 *
 * «التوقفُ لا يكون نصًّا حرًّا» — فكلُّ ساعةِ تعطلٍ تُسند إلى حالةٍ وطرفٍ متحمِّل
 * في `unit_time_log`، وهي التي تُغذّي الأحكامَ الثلاثة لاحقًا.
 */
require __DIR__ . '/_lib.php';
set_time_limit(0);

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

$mapSeat  = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_seats.json'), true);
$mapEquip = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_equipment.json'), true);
$mapOper  = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_operators.json'), true);

// ── ① أوامرُ التشغيل: صفٌّ لكل مقعد (الوحدة التعاقدية) ───────────────────────
$opsBySeat = [];
foreach (uat_json('ب__ب05_الوحدات_التعاقدية') as $r) {
    $ukey = trim($r['مفتاح الوحدة'] ?? '');
    if (!isset($mapSeat[$ukey])) continue;
    $seat = uat_one("SELECT id,contract_id,project_id,equipment_id,supplier_id,seat_equipment_type_id,valid_from,valid_to FROM op_containers WHERE id=?", [$mapSeat[$ukey]]);
    if (!$seat) continue;

    $ex = uat_one("SELECT id FROM operations WHERE company_id=? AND reason=? LIMIT 1", [$CO, 'UAT-SEAT|' . $ukey]);
    if ($ex) { $opsBySeat[$ukey] = (int) $ex['id']; continue; }

    $hrs = uat_num($r['الساعات المنفذة'] ?? '', 0);
    $id = uat_insert('operations', [
        'company_id'         => $CO,
        'equipment'          => (string) ($seat['equipment_id'] ?? 0),
        'equipment_type'     => (string) ($seat['seat_equipment_type_id'] ?? 0),
        'equipment_category' => (mb_strpos($r['حالة التغطية'] ?? '', 'احتياط') !== false) ? 'احتياطي' : 'أساسي',
        'project_id'         => (string) $seat['project_id'],
        'contract_id'        => (string) $seat['contract_id'],
        'supplier_id'        => (string) ($seat['supplier_id'] ?? 0),
        'start'              => (string) ($seat['valid_from'] ?? ''),
        'end'                => (string) ($seat['valid_to'] ?? ''),
        'reason'             => 'UAT-SEAT|' . $ukey,
        'days'               => (string) uat_int($r['عدد الأشهر'] ?? '', 0),
        'total_equipment_hours' => $hrs,
        'shift_hours'        => 10,
        'target_daily_hours' => 20,
        'shift_type'         => 'B',
        'status'             => 1,
        'op_state'           => 'تعمل',
        'equipment_health'   => 'سليمة',
    ]);
    $opsBySeat[$ukey] = $id;
    uat_log('operations', 'أمر تشغيل');
}

// ── ② خريطةُ المشغّل على المعدة · ش04 (933 ارتباطًا) ─────────────────────────
$opAssign = [];   // كود المعدة => [[from,to,employee_id], …]
foreach (uat_json('ش__ش04_تكليف_المشغل_على_المعدة') as $r) {
    $eq = trim($r['الآلية'] ?? '');
    $no = trim($r['رقم السائق'] ?? '');
    if ($eq === '' || !isset($mapOper[$no])) continue;
    $opAssign[$eq][] = [uat_date($r['من'] ?? ''), uat_date($r['إلى'] ?? ''), $mapOper[$no]];
}
$pickOperator = function ($eqCode, $date) use ($opAssign) {
    foreach ($opAssign[$eqCode] ?? [] as [$f, $t, $emp]) {
        if ((!$f || $date >= $f) && (!$t || $date <= $t)) return $emp;
    }
    return $opAssign[$eqCode][0][2] ?? null;
};

// ── ③ التفكيكُ اليوميّ ───────────────────────────────────────────────────────
$UNIT_OF = ['إيجار بالساعة' => 'hour', 'مقاولات النقل' => 'ton', 'تخريم بالمتر' => 'meter', 'النقل بالنقلة' => 'trip'];

/** أوزانٌ غيرُ منتظمةٍ حتميةٌ (لا عشوائيةَ تكسر إعادةَ التشغيل) تجمع 1. */
function uat_weights($n, $salt)
{
    $w = []; $sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $h = crc32($salt . '|' . $i);
        $v = 0.55 + (($h % 1000) / 1000) * 0.9;      // بين 0.55 و1.45
        $w[] = $v; $sum += $v;
    }
    return array_map(fn($v) => $v / $sum, $w);
}

$STOPS = [
    'فاقد غير منفذ'        => ['client_stop',    'client',   1, 0, 0],
    'تعطل صيانة'          => ['tech_breakdown', 'supplier', 0, 0, 0],
    'أجازات وعطل'         => ['planned_stop',   'planned',  0, 0, 0],
    'تأخير موارد بشرية'   => ['operator_stop',  'company',  0, 1, 0],
    'تعطل اعتمادية'       => ['supplier_stop',  'supplier', 0, 0, 0],
    'أسباب أخرى'          => ['unlogged',       'none',     0, 0, 0],
];

$entryNo = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(entry_no),'UNT-000000') m FROM unit_entries WHERE company_id=$CO")->fetch_assoc()['m']));

$stTs  = $db->prepare("INSERT INTO timesheet (company_id,operator,employee_id,shift,`date`,shift_hours,executed_hours,bucket_hours,standby_hours,total_work_hours,hr_fault,maintenance_fault,marketing_fault,approval_fault,other_fault_hours,total_fault_hours,operator_hours,tons_count,trips_count,meters_count,`type`,user_id,time_notes,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'عادي',?,?,1)");
$stUe  = $db->prepare("INSERT INTO unit_entries (company_id,entry_no,entry_date,project_id,contract_id,operational_site_id,equipment_id,operator_employee_id,supplier_entity_id,unit_type,qty,record_basis,capacity_flag,qty_billable,shift,source_ref,note,state,revision_no,current_round,cap_seat_id,entered_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'contract',0,1,?,'UAT-B07',?,?,0,1,?,?,NOW(),NOW())");
$stUtl = $db->prepare("INSERT INTO unit_time_log (company_id,log_date,shift,project_id,equipment_id,operator_employee_id,supplier_entity_id,hours,ops_state,cause_note,resp_party,billable,supplier_countable,operator_countable,objection_state,entry_id,entered_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'none',?,?,NOW())");

$siteOf = [];

// إعادةُ التوليد: `--reset` تكنس ما وُلِّد سابقًا بالوسم (وحدها الطريقةُ الآمنة
// بعد توقفٍ في المنتصف — فالنصفُ المولَّدُ لا يُبنى فوقه).
if (in_array('--reset', $argv, true)) {
    $db->query("DELETE utl FROM unit_time_log utl JOIN unit_entries ue ON ue.id = utl.entry_id WHERE ue.company_id=$CO AND ue.source_ref='UAT-B07'");
    $db->query("DELETE FROM unit_entries WHERE company_id=$CO AND source_ref='UAT-B07'");
    $db->query("DELETE FROM timesheet WHERE company_id=$CO AND time_notes='" . UAT_TAG . "'");
    echo "   ↺ كُنِست البيانات المولَّدة سابقًا بالوسم.\n";
}

$done = uat_count('unit_entries', "company_id=$CO AND source_ref='UAT-B07'");
if ($done > 0) { echo "   ⚠ سبق التوليد ($done قيدَ وحدة) — يُتخطى لتفادي المضاعفة (استعمل --reset لإعادته).\n"; }
else {
    $db->begin_transaction();
    $n = 0;
    foreach (uat_json('ب__ب07_الأداء_الشهري_للوحدة') as $row) {
        $ukey = trim($row['مفتاح الحاوية'] ?? '');
        if (!isset($mapSeat[$ukey]) || !isset($opsBySeat[$ukey])) continue;
        $seat = uat_one("SELECT id,contract_id,project_id,equipment_id,supplier_id,unit_type FROM op_containers WHERE id=?", [$mapSeat[$ukey]]);
        if (!$seat) continue;

        $month = trim($row['الشهر'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) continue;
        $dim   = (int) date('t', strtotime($month . '-01'));
        $days  = max(1, min((int) uat_int($row['أيام التشغيل'] ?? '', 1), $dim));

        $exec  = uat_num($row['الساعات المنفذة'] ?? '', 0);
        $stand = uat_num($row['ساعات الاستعداد'] ?? '', 0);
        $tons  = uat_num($row['أطنان منجزة'] ?? '', 0);
        $trips = uat_num($row['نقلات منجزة'] ?? '', 0);
        $mtrs  = uat_num($row['أمتار منجزة'] ?? '', 0);

        $unit  = $seat['unit_type'] ?: 'hour';
        $prodQ = ['hour' => $exec, 'ton' => $tons, 'trip' => $trips, 'meter' => $mtrs][$unit] ?? $exec;

        $w = uat_weights($days, $ukey . $month);
        $opsId = $opsBySeat[$ukey];
        $eqId  = (int) ($seat['equipment_id'] ?? 0);
        $eqCode = array_search($eqId, $mapEquip, true) ?: '';

        if (!isset($siteOf[$seat['contract_id']])) {
            $s = uat_one("SELECT site_id FROM contract_operational_sites WHERE contract_id=? AND is_primary=1 LIMIT 1", [$seat['contract_id']]);
            $siteOf[$seat['contract_id']] = $s ? (int) $s['site_id'] : null;
        }
        $siteId = $siteOf[$seat['contract_id']];

        // الحالة: الأشهرُ القديمةُ محوَّلةٌ، والثلاثةُ الأخيرةُ في مراحلَ مختلفة
        $age   = (int) ((strtotime(date('Y-m-01')) - strtotime($month . '-01')) / 2592000);
        $state = $age > 3 ? 'converted' : ($age > 1 ? 'sales_approved' : ($age > 0 ? 'site_approved' : 'submitted'));

        for ($i = 0; $i < $days; $i++) {
            $day   = date('Y-m-d', strtotime($month . '-01 +' . (int) floor($i * $dim / $days) . ' day'));
            $hrs   = round($exec * $w[$i], 2);
            $sby   = round($stand * $w[$i], 2);
            $qty   = round($prodQ * $w[$i], 2);
            $shiftEn = (($i + (int) $seat['id']) % 2) ? 'night' : 'day';
            $shiftAr = $shiftEn === 'day' ? 'صباحية' : 'مسائية';
            $emp   = $pickOperator($eqCode, $day);

            // ── التعطل: يقع في ربع الأيام لا في كلها ──────────────────────────
            $stops = [];
            if ($i % 4 === 0) {
                $share = min(4, (int) ceil($days / 4));
                foreach ($STOPS as $col => $def) {
                    $v = uat_num($row[$col] ?? '', 0);
                    if ($v > 0) $stops[$col] = round($v / $share, 2);
                }
            }
            $faultTotal = array_sum($stops);

            $shiftBase = 10.0;
            $tot   = $hrs + $sby;
            $fHr   = $stops['تأخير موارد بشرية'] ?? 0.0;
            $fMnt  = $stops['تعطل صيانة'] ?? 0.0;
            $fMkt  = 0.0;
            $fApp  = $stops['تعطل اعتمادية'] ?? 0.0;
            $fOth  = ($stops['أسباب أخرى'] ?? 0.0) + ($stops['فاقد غير منفذ'] ?? 0.0) + ($stops['أجازات وعطل'] ?? 0.0);
            $tonsD = $unit === 'ton'   ? $qty : 0.0;
            $tripD = $unit === 'trip'  ? (int) $qty : 0;
            $mtrD  = $unit === 'meter' ? $qty : 0.0;
            $tag   = UAT_TAG;
            $empTs = $emp ?: 0;          // العمودُ NOT NULL — الصفرُ يعني «بلا مشغّلٍ مسجَّل»
            $stTs->bind_param('issssddddddddddddddiis',
                $CO, $opsId, $empTs, $shiftAr, $day, $shiftBase, $hrs, $hrs, $sby, $tot,
                $fHr, $fMnt, $fMkt, $fApp, $fOth, $faultTotal, $hrs, $tonsD, $tripD, $mtrD, $actor, $tag);
            $stTs->execute();

            $entryNo++;
            $eno    = sprintf('UNT-%06d', $entryNo);
            $eqIdN  = $eqId ?: null;
            $pidN   = (int) $seat['project_id'];
            $cidN   = (int) $seat['contract_id'];
            $supN   = $seat['supplier_id'] ?: null;
            $seatN  = (int) $seat['id'];
            $note   = 'من ب07 · ' . $ukey . ' · ' . $month;
            $stUe->bind_param('issiiiiiisdsssii', $CO, $eno, $day, $pidN, $cidN,
                $siteId, $eqIdN, $emp, $supN, $unit, $qty, $shiftEn, $note, $state, $seatN, $actor);
            $stUe->execute();
            $eid = $db->insert_id;

            $mk = function ($hours, $opsState, $cause, $party, $bil, $supC, $opC)
                  use ($stUtl, $CO, $day, $shiftEn, $pidN, $eqIdN, $emp, $supN, $eid, $actor) {
                $h = $hours; $s = $opsState; $c = $cause; $p = $party;
                $b = $bil; $sc = $supC; $oc = $opC;
                $stUtl->bind_param('isssiiidsssiiiii', $CO, $day, $shiftEn, $pidN, $eqIdN, $emp,
                    $supN, $h, $s, $c, $p, $b, $sc, $oc, $eid, $actor);
                $stUtl->execute();
            };

            if ($hrs > 0) $mk($hrs, 'actual_work', null, 'none', 1, 1, 1);
            if ($sby > 0) $mk($sby, 'standby', 'استعدادٌ بأمر العميل', 'client', 1, 1, 1);
            foreach ($stops as $col => $v) {
                [$os2, $party2, $bil2, $opc2, $spc2] = $STOPS[$col];
                $mk($v, $os2, $col, $party2, $bil2, $spc2, $opc2);
            }

            if (++$n % 3000 === 0) { $db->commit(); $db->begin_transaction(); echo "   … $n يومًا\n"; }
        }
        uat_log('ب07', 'سجل شهري مفكَّك');
    }
    $db->commit();
    uat_log('timesheet', 'يوم', $n);
}

uat_print_report('البذرة ⑤ · التشغيل والتايم شيت');
printf("   أوامرُ التشغيل: %d · صفوفُ التايم شيت: %d · قيودُ الوحدات: %d · سجلُّ الزمن: %d\n",
    uat_count('operations', "company_id=$CO"), uat_count('timesheet', "company_id=$CO"),
    uat_count('unit_entries', "company_id=$CO"), uat_count('unit_time_log', "company_id=$CO"));
