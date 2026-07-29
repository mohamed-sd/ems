<?php
/**
 * database/seeds/con02_seed.php
 * ═══════════════════════════════════════════════════════════════════════════
 * بذرُ تفعيل CON-02 — **بياناتُ تجربةٍ لا بنودُ عقد**.
 *
 * ⚠️ ⚠️  اقرأ هذا قبل أن تبني على أي رقمٍ ينتج عن هذا البذر  ⚠️ ⚠️
 * ───────────────────────────────────────────────────────────────────────────
 * ملءُ مصفوفة الالتزامات **كتابةُ بنودِ عقد**. والنظامُ لا يعرف ما نصّ عليه
 * العقدُ الموقَّعُ ورقًا، وكاتبُ هذا الملفِّ لا يعرفه كذلك. فكلُّ صفٍّ هنا
 * **بيانةُ تجربةٍ موسومةٌ قابلةٌ للحذف بأمرٍ واحد** — لا بندُ عقدٍ نافذ.
 *
 * وعلى الخصوص: **بندُ الوقود على العقد 5 مبذورٌ على «العميل» فرضَ تجربةٍ
 * معلنًا** بقرار المالك (2026-07-28: «لا أعرف — ابذره فرضَ تجربةٍ معلنًا»).
 * فكلُّ فرقٍ ماليٍّ ينتج عنه معناه: «هذا ما سيحدث **لو** كان الوقودُ على
 * العميل» — لا «هذا هو الصحيحُ ماليًّا».
 *
 * ── ما يُبذر ──────────────────────────────────────────────────────────────
 *   ① مصفوفةُ الالتزامات للعقود التسعة (9 × 9 = 81 صفًّا) — ق-2 تقضي بأن
 *      العقدَ بلا مصفوفةٍ مُجازةٍ يُرفض بـ423، فبذرُ العقد الرائد وحدَه كان
 *      سيجمّد الثمانيةَ الباقية فورًا. فتُبذر كلُّها: الرائدُ بمصفوفةٍ تُظهر
 *      الفرق، والباقيةُ **محافظةٌ** (كلُّ البنود company/non_billable) وهي
 *      مطابقةٌ للسلوك الحالي تمامًا فلا يتغيّر لها شيء.
 *   ② التزامٌ شهريٌّ مرساةٌ لقواعد الجزاء على عقد العرض.
 *   ③ قاعدتا جزاء: shortfall_pct (نسبةٌ وسقف) و readiness_min.
 *   ④ نسبتا الاحتجاز واستهلاك الدفعة على عقد العرض.
 *
 * ── الوسمُ والرجوع ────────────────────────────────────────────────────────
 * `contract_obligations` بلا عمود ملاحظات، فالوسمُ **بيانُ جردٍ** يُكتب في
 * `database/seeds/con02_seed_manifest.json`: كلُّ معرّفٍ مبذورٍ بجدوله، وكلُّ
 * قيمةٍ سابقةٍ لكل تحديث. والتراجعُ يقرؤه فيحذف ما بذره **وحدَه**.
 * وما له عمودُ نصٍّ يحمل الوسمَ ظاهرًا أيضًا (حزامٌ وحمّالة).
 *
 *   الرجوع:  php database/seeds/con02_rollback.php
 *
 * ── القواعد المرعية ───────────────────────────────────────────────────────
 *   • بياناتٌ لا مخطط — ولا سطرَ DDL واحدًا هنا.
 *   • كلُّ كتابةٍ عبر بوابة المستأجر (TenantDb) داخل runInTransaction.
 *   • كلُّ صفٍّ يُقرأ بعد كتابته ويُقارن بما دخل (گوتشا ابتلاع ENUM الصامت:
 *     MySQL تبتلع القيمةَ الخاطئة إلى '' بلا خطأ).
 *
 * التشغيل: php database/seeds/con02_seed.php [--contract=5]
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__, 2) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

// ── سياقُ الفاعل: المبيعات (12) تملأ · والإجازةُ تُبذر مباشرةً بحالة approved
//    لأن هذا بذرُ تهيئةٍ لا محاكاةُ دورةِ اعتماد (ق-18 تُختبر في الحزم).
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'con02 seed');

$COMPANY   = 4;
$SEED_TAG  = 'CON02-SEED-20260728';
$MANIFEST  = __DIR__ . '/con02_seed_manifest.json';

// عقدُ العرض: يُمرَّر بـ--contract، وافتراضُه 5 (قرارُ المالك 2026-07-28).
$PILOT = 5;
foreach ($argv as $a) {
    if (strpos($a, '--contract=') === 0) { $PILOT = (int) substr($a, 11); }
}

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();

function say($m)  { fwrite(STDOUT, $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── " . $m . "\n"); }
function die_with($m) { fwrite(STDERR, "\n✘ " . $m . "\n"); exit(1); }

if (file_exists($MANIFEST)) {
    die_with("بيانُ جردٍ قائمٌ سلفًا: {$MANIFEST}\n"
           . "  فالبذرُ منفَّذٌ. للإعادة: php database/seeds/con02_rollback.php ثم أعِد التشغيل.");
}

$manifest = array(
    'seed_tag'    => $SEED_TAG,
    'company_id'  => $COMPANY,
    'pilot'       => $PILOT,
    'seeded_at'   => gmdate('Y-m-d H:i:s'),
    'inserted'    => array(),   // table => [ids]
    'updated'     => array(),   // table => [ ['id'=>, 'old'=>[col=>val]] ]
);
function track_insert(&$mf, $table, $id) { $mf['inserted'][$table][] = (int) $id; }
function track_update(&$mf, $table, $id, array $old) {
    $mf['updated'][$table][] = array('id' => (int) $id, 'old' => $old);
}

// ═══════════════════════════════════════════════════════════════════════════
// ⓪ خطُّ الأساس — يُثبت قبل أي كتابة
// ═══════════════════════════════════════════════════════════════════════════
$COUNTERS = array('contract_obligations', 'contract_penalty_rules',
                  'contract_penalty_assessments', 'claims', 'claim_lines',
                  'contract_commitments', 'unit_entries', 'unit_time_log',
                  'fin_financial_events', 'fin_event_links',
                  // ⚠️ `fin_dues` في العدّادات عمدًا: أثرُ `supplier_due` وتمريرُ
                  //    جزاء المورد (ق-16) يكتبان فيه، وغيابُه عن العدّ أخفى
                  //    صفَّين يتيمَين في أول تجربةِ تراجع. ما لا يُعدّ لا يُرى.
                  'fin_dues');
function counters($conn, array $tables) {
    $out = array();
    foreach ($tables as $t) {
        $r = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
        $out[$t] = $r ? (int) $r->fetch_assoc()['c'] : -1;
    }
    return $out;
}
$baseline = counters($conn, $COUNTERS);
$manifest['baseline'] = $baseline;

head('خطُّ الأساس قبل البذر');
foreach ($baseline as $t => $n) { say(sprintf('   %-32s %s', $t, $n)); }

// ═══════════════════════════════════════════════════════════════════════════
// ① مصفوفةُ الالتزامات — العقودُ التسعة
// ═══════════════════════════════════════════════════════════════════════════

/** بنودُ §4 التسعة — نفسُ ترتيب AttributionService::TYPES (عقدٌ مع المخطط). */
$TYPES = array('fuel', 'access_road', 'loading_equipment', 'equipment_readiness',
               'operators', 'permits_safety', 'utilities', 'catering_camp', 'force_majeure');

/**
 * المصفوفةُ المحافظة — مطابقةٌ للسلوك الحالي تمامًا:
 *   كلُّ بندٍ على «الشركة» بأثر «غيرُ مفوتر» ⇒ billable=0 لكل زمن توقف،
 *   وهو ما يفعله النظامُ اليومَ بسياسة الساعة الافتراضية.
 *   و«الظروفُ القاهرة» على `none` — قرارُ المالك ② في هجرة الأساس: `company`
 *   كانت ستقرأ «الشركةُ ملتزمةٌ بمنع الفيضان» فتُحمِّلها التبعة.
 */
function conservative_matrix(array $types) {
    $m = array();
    foreach ($types as $t) {
        $m[$t] = array('obligor' => ($t === 'force_majeure') ? 'none' : 'company',
                       'effect'  => 'non_billable');
    }
    return $m;
}

/**
 * مصفوفةُ عقد العرض — **الوقودُ على العميل فرضَ تجربةٍ معلنًا**.
 * وهو **البندُ الوحيد** الذي يفارق المحافظة، فكلُّ فرقٍ ماليٍّ يُقاس يُعزى
 * إليه وحدَه بلا التباس.
 */
function pilot_matrix(array $types) {
    $m = conservative_matrix($types);
    $m['fuel'] = array('obligor' => 'client', 'effect' => 'billable_standby');
    return $m;
}

$contracts = $gate->scopedQuery(
    array('scope' => array('c' => 'contracts')),
    "SELECT c.id, c.actual_start, c.actual_end, c.price_currency_contract AS cur
       FROM contracts c WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0) = 0
      ORDER BY c.id", array());

if (empty($contracts)) { die_with('لا عقودَ في نطاق الشركة ' . $COMPANY); }

$pilotFound = false;
foreach ($contracts as $c) { if ((int) $c['id'] === $PILOT) { $pilotFound = true; } }
if (!$pilotFound) { die_with("عقدُ العرض #{$PILOT} ليس في نطاق الشركة {$COMPANY}"); }

head('① مصفوفةُ الالتزامات — ' . count($contracts) . ' عقودٍ × 9 بنود');

$obligationRows = array();   // للعرض بعد القراءة العكسية
$gate->runInTransaction(function ($g) use ($contracts, $TYPES, $PILOT, &$manifest, &$obligationRows) {
    foreach ($contracts as $c) {
        $cid = (int) $c['id'];
        $start = trim((string) $c['actual_start']);
        if ($start === '' || $start === '0000-00-00') {
            // لا يُخترع تاريخُ سريان: بلا بدءٍ مسجَّلٍ لا نعرف من متى يسري البند
            throw new \RuntimeException("العقد #{$cid} بلا actual_start — لا يُخترع تاريخُ سريان");
        }
        $matrix = ($cid === $PILOT) ? pilot_matrix($TYPES) : conservative_matrix($TYPES);
        foreach ($TYPES as $t) {
            $id = (int) $g->insert('contract_obligations', array(
                'client_contract_id' => $cid,
                'obligation_type'    => $t,
                'obligor'            => $matrix[$t]['obligor'],
                'effect_on_billing'  => $matrix[$t]['effect'],
                'valid_from'         => $start,     // يغطي وقائعَ العقد كلَّها
                'valid_to'           => null,       // مفتوحٌ — فلا واقعةَ تسقط خارجَه
                'approval_state'     => 'approved', // المسودةُ لا تسري (ق-18)
                'created_by'         => 1,
            ));
            if ($id <= 0) { throw new \RuntimeException("فشل إدراج بند {$t} للعقد #{$cid}"); }
            track_insert($manifest, 'contract_obligations', $id);
            $obligationRows[] = array('id' => $id, 'contract' => $cid, 'type' => $t,
                                      'obligor' => $matrix[$t]['obligor'],
                                      'effect' => $matrix[$t]['effect'], 'from' => $start);
        }
    }
}, 'con02 seed: obligations');

say('   أُدرج ' . count($obligationRows) . ' صفًّا.');

// ═══════════════════════════════════════════════════════════════════════════
// ② التزامٌ شهريٌّ مرساةٌ لقواعد الجزاء
// ═══════════════════════════════════════════════════════════════════════════
// **لماذا التزامٌ جديد؟** قواعدُ الجزاء تحتاج بندًا ملتزَمًا مرساةً
// (PenaltyService::computeRule ترفض قاعدةً بلا بند: «لا أساسَ للقياس»)،
// و**دوريتُه** هي ما يقيس عليه السقفُ والأساس. وعقدُ العرض بلا التزامٍ شهريٍّ
// اليوم. وهذا الصفُّ **بيانةُ تجربةٍ موسومةٌ صراحةً** لا بندُ عقدٍ موقَّع.
head('② التزامٌ شهريٌّ مرساةٌ (بيانةُ تجربة)');

// **لماذا «يوميّ» لا «شهريّ»؟** ق-11 تمنع الاحتسابَ النسبيّ: التزامٌ شهريٌّ
// لا تكتمل دوريتُه إلا بشهرٍ تقويميٍّ كامل، وبيانُ العقد اليومَ يومُ عملٍ
// واحدٌ محوَّل. فالتزامٌ شهريٌّ كبيرٌ يجعل غرامةَ العجز تبتلع المستخلصَ كلَّه
// (الأساسُ يصير سالبًا فتسقط بنودُ الاستقطاع صامتةً — claim_helpers §②).
// و«إتاحةٌ يوميةٌ ٨ ساعات» بيانةُ تجربةٍ متسقةٌ مع بنية الورديات وتكتمل يوميًّا.
$DAILY_HOURS = 8.0;
$commitmentId = 0;
$gate->runInTransaction(function ($g) use ($PILOT, $SEED_TAG, $DAILY_HOURS, &$commitmentId, &$manifest) {
    $commitmentId = (int) $g->insert('contract_commitments', array(
        'commitment_code' => 'SEED-CMT-01',
        'party_scope'     => 'client',
        'contract_ref'    => $PILOT,
        'commitment_type' => 'daily_availability_hours',
        'unit_type'       => 'hour',
        'qty'             => $DAILY_HOURS,
        'period'          => 'daily',
        'obliged_party'   => 'company',       // ق-15: لا غرامةَ إلا حين نكون نحن المقصّرين
        'shortfall_rule'  => 'penalty',
        'surplus_rule'    => 'same_price',
        'note'            => '[' . $SEED_TAG . '] بيانةُ تجربة — لا بندَ عقدٍ موقَّع',
        'created_by'      => 1,
    ));
    if ($commitmentId <= 0) { throw new \RuntimeException('فشل إدراج الالتزام المرساة'); }
    track_insert($manifest, 'contract_commitments', $commitmentId);
}, 'con02 seed: commitment');

say("   الالتزام #{$commitmentId}: {$DAILY_HOURS} ساعاتِ إتاحةٍ يوميًّا · الملتزم company · مرساةُ القواعد.");

// ═══════════════════════════════════════════════════════════════════════════
// ③ قاعدتا الجزاء — أرقامُ تجربةٍ معلنة (قرارُ المالك 2026-07-28)
// ═══════════════════════════════════════════════════════════════════════════
head('③ قواعدُ الجزاء (أرقامُ تجربةٍ معلنة)');

$pilotRow = null;
foreach ($contracts as $c) { if ((int) $c['id'] === $PILOT) { $pilotRow = $c; } }
$pilotStart = trim((string) $pilotRow['actual_start']);

$RULES = array(
    array(
        'rule_kind'        => 'shortfall_pct',
        'rate'             => 2.500,   // نسبةٌ من قيمة الفارق
        'cap_percent'      => 10.00,   // سقفٌ من قيمة البند الملتزَم في الفترة (ق-12)
        'min_readiness_pct'=> null,
        'note'             => '[' . $SEED_TAG . '] غرامةُ عجزٍ 2.5٪ بسقف 10٪ — رقمُ تجربةٍ لا بندُ عقد',
    ),
    array(
        'rule_kind'        => 'readiness_min',
        'rate'             => 2.000,   // نسبةٌ من قيمة الفترة
        'cap_percent'      => 10.00,
        'min_readiness_pct'=> 85.00,   // عتبةُ الجاهزية
        'note'             => '[' . $SEED_TAG . '] غرامةُ جاهزيةٍ دون 85٪ بنسبة 2٪ وسقف 10٪ — رقمُ تجربة',
    ),
);

$ruleIds = array();
$gate->runInTransaction(function ($g) use ($RULES, $PILOT, $commitmentId, $pilotStart, &$ruleIds, &$manifest) {
    foreach ($RULES as $r) {
        $id = (int) $g->insert('contract_penalty_rules', array(
            'client_contract_id' => $PILOT,
            'rule_kind'          => $r['rule_kind'],
            'commitment_ref'     => $commitmentId,
            'rate'               => $r['rate'],
            'min_readiness_pct'  => $r['min_readiness_pct'],
            'cap_percent'        => $r['cap_percent'],
            'periodicity'        => 'daily',     // ق-11: الدوريةُ اليوميةُ تكتمل بيومها
            'valid_from'         => $pilotStart,
            'valid_to'           => null,
            'note'               => $r['note'],
            'created_by'         => 1,
        ));
        if ($id <= 0) { throw new \RuntimeException('فشل إدراج قاعدة ' . $r['rule_kind']); }
        track_insert($manifest, 'contract_penalty_rules', $id);
        $ruleIds[$r['rule_kind']] = $id;
    }
}, 'con02 seed: penalty rules');

foreach ($ruleIds as $k => $v) { say("   القاعدة #{$v}: {$k}"); }

// ═══════════════════════════════════════════════════════════════════════════
// ④ نسبتا الاحتجاز واستهلاك الدفعة (ق-19)
// ═══════════════════════════════════════════════════════════════════════════
head('④ نسبتا الاستقطاع على عقد العرض (أرقامُ تجربةٍ معلنة)');

$RETENTION = 5.00;    // ضمانُ حسن التنفيذ المحتجزُ من كل مستخلص
$ADVANCE   = 10.00;   // استهلاكُ الدفعة المقدمة من كل مستخلص

$old = $gate->selectOne('contracts', array(
    'columns' => array('id', 'retention_pct', 'advance_recovery_pct'),
    'where'   => array('id' => $PILOT),
));
if (!$old) { die_with("تعذّرت قراءةُ العقد #{$PILOT}"); }

// ⚠️ حارسُ «لا تكتب فوق حكمٍ ماليٍّ قائم»
if ($old['retention_pct'] !== null || $old['advance_recovery_pct'] !== null) {
    die_with("العقد #{$PILOT} فيه نسبتا استقطاعٍ مسجَّلتان سلفًا "
           . "(retention={$old['retention_pct']} · advance={$old['advance_recovery_pct']}).\n"
           . "  البذرُ لا يكتب فوق حكمٍ ماليٍّ قائم — أوقفتُ ولم أكتب شيئًا.");
}

$gate->runInTransaction(function ($g) use ($PILOT, $RETENTION, $ADVANCE, $old, &$manifest) {
    $g->update('contracts', array(
        'retention_pct'        => $RETENTION,
        'advance_recovery_pct' => $ADVANCE,
    ), array('id' => $PILOT));
    track_update($manifest, 'contracts', $PILOT, array(
        'retention_pct'        => $old['retention_pct'],
        'advance_recovery_pct' => $old['advance_recovery_pct'],
    ));
}, 'con02 seed: retention pcts');

say("   الاحتجاز {$RETENTION}٪ · استهلاكُ الدفعة {$ADVANCE}٪ (كانتا NULL).");

// ═══════════════════════════════════════════════════════════════════════════
// ⑤ القراءةُ العكسية — گوتشا ابتلاع ENUM الصامت
// ═══════════════════════════════════════════════════════════════════════════
// MySQL تبتلع القيمةَ الخاطئة إلى '' بلا خطأ. فكلُّ صفٍّ يُقرأ ويُقارن حرفيًّا
// بما دخل، وأيُّ فارقٍ يُسقط البذرَ كلَّه.
head('⑤ القراءةُ العكسية — كلُّ صفٍّ يُقارن بما دخل');

$mismatch = array();

$back = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
    "SELECT o.id, o.client_contract_id, o.obligation_type, o.obligor,
            o.effect_on_billing, o.valid_from, o.valid_to, o.approval_state
       FROM contract_obligations o WHERE {TENANT_SCOPE} AND o.is_deleted = 0
      ORDER BY o.id", array());
$byId = array();
foreach ($back as $b) { $byId[(int) $b['id']] = $b; }

foreach ($obligationRows as $w) {
    $g = isset($byId[$w['id']]) ? $byId[$w['id']] : null;
    if (!$g) { $mismatch[] = "الصف #{$w['id']} لم يُقرأ بعد كتابته"; continue; }
    if ((string) $g['obligation_type'] !== $w['type']) {
        $mismatch[] = "#{$w['id']} obligation_type: كُتب «{$w['type']}» وعاد «{$g['obligation_type']}»";
    }
    if ((string) $g['obligor'] !== $w['obligor']) {
        $mismatch[] = "#{$w['id']} obligor: كُتب «{$w['obligor']}» وعاد «{$g['obligor']}»";
    }
    if ((string) $g['effect_on_billing'] !== $w['effect']) {
        $mismatch[] = "#{$w['id']} effect_on_billing: كُتب «{$w['effect']}» وعاد «{$g['effect_on_billing']}»";
    }
    if ((string) $g['approval_state'] !== 'approved') {
        $mismatch[] = "#{$w['id']} approval_state: كُتب «approved» وعاد «{$g['approval_state']}»";
    }
    if ((int) $g['client_contract_id'] !== $w['contract']) {
        $mismatch[] = "#{$w['id']} client_contract_id مختلف";
    }
    if (substr((string) $g['valid_from'], 0, 10) !== substr($w['from'], 0, 10)) {
        $mismatch[] = "#{$w['id']} valid_from: كُتب «{$w['from']}» وعاد «{$g['valid_from']}»";
    }
}

$cmBack = $gate->selectOne('contract_commitments', array('where' => array('id' => $commitmentId)));
if (!$cmBack) { $mismatch[] = 'الالتزامُ المرساة لم يُقرأ'; }
else {
    if ((string) $cmBack['commitment_type'] !== 'daily_availability_hours') { $mismatch[] = 'commitment_type ابتُلع: «' . $cmBack['commitment_type'] . '»'; }
    if ((string) $cmBack['unit_type'] !== 'hour')             { $mismatch[] = 'unit_type ابتُلع: «' . $cmBack['unit_type'] . '»'; }
    if ((string) $cmBack['period'] !== 'daily')               { $mismatch[] = 'period ابتُلع: «' . $cmBack['period'] . '»'; }
    if ((string) $cmBack['obliged_party'] !== 'company')      { $mismatch[] = 'obliged_party ابتُلع: «' . $cmBack['obliged_party'] . '»'; }
    if ((string) $cmBack['shortfall_rule'] !== 'penalty')     { $mismatch[] = 'shortfall_rule ابتُلع: «' . $cmBack['shortfall_rule'] . '»'; }
}

foreach ($ruleIds as $kind => $rid) {
    $rb = $gate->selectOne('contract_penalty_rules', array('where' => array('id' => $rid)));
    if (!$rb) { $mismatch[] = "القاعدة #{$rid} لم تُقرأ"; continue; }
    if ((string) $rb['rule_kind'] !== $kind) { $mismatch[] = "#{$rid} rule_kind: كُتب «{$kind}» وعاد «{$rb['rule_kind']}»"; }
    if ((string) $rb['periodicity'] !== 'daily') { $mismatch[] = "#{$rid} periodicity ابتُلع: «{$rb['periodicity']}»"; }
    if ((int) $rb['commitment_ref'] !== $commitmentId) { $mismatch[] = "#{$rid} commitment_ref مختلف"; }
}

$cb = $gate->selectOne('contracts', array(
    'columns' => array('id', 'retention_pct', 'advance_recovery_pct'),
    'where'   => array('id' => $PILOT),
));
if (abs((float) $cb['retention_pct'] - $RETENTION) > 0.001)      { $mismatch[] = 'retention_pct لم تُكتب'; }
if (abs((float) $cb['advance_recovery_pct'] - $ADVANCE) > 0.001) { $mismatch[] = 'advance_recovery_pct لم تُكتب'; }

if (!empty($mismatch)) {
    say('');
    foreach ($mismatch as $m) { say('   ✘ ' . $m); }
    die_with(count($mismatch) . ' فارقًا بين ما كُتب وما عاد — البذرُ غيرُ موثوق.'
           . "\n  شغّل php database/seeds/con02_rollback.php --force لكنسه.");
}
say('   ✔ ' . (count($obligationRows) + 1 + count($ruleIds) + 1) . ' صفًّا: كلُّها عادت كما دخلت (لا ابتلاعَ ENUM).');

// ═══════════════════════════════════════════════════════════════════════════
// ⑥ بيانُ الجرد
// ═══════════════════════════════════════════════════════════════════════════
$manifest['after'] = counters($conn, $COUNTERS);
if (!is_dir(dirname($MANIFEST))) { mkdir(dirname($MANIFEST), 0775, true); }
file_put_contents($MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

head('⑥ بيانُ الجرد');
say('   ' . $MANIFEST);
foreach ($manifest['inserted'] as $t => $ids) { say(sprintf('   %-32s %s صفًّا', $t, count($ids))); }
foreach ($manifest['updated'] as $t => $rows) { say(sprintf('   %-32s %s تحديثًا (بقيمها السابقة)', $t, count($rows))); }

// ═══════════════════════════════════════════════════════════════════════════
// ⑦ مصفوفةُ عقد العرض كاملةً
// ═══════════════════════════════════════════════════════════════════════════
$AR = array(
    'fuel' => 'الوقود', 'access_road' => 'طريقُ الوصول', 'loading_equipment' => 'معداتُ التحميل',
    'equipment_readiness' => 'جاهزيةُ المعدة', 'operators' => 'المشغّلون',
    'permits_safety' => 'التصاريحُ والسلامة', 'utilities' => 'المرافق',
    'catering_camp' => 'الإعاشةُ والسكن', 'force_majeure' => 'الظروفُ القاهرة',
);
$AR_OBLIGOR = array('client' => 'العميل', 'company' => 'الشركة', 'supplier' => 'المورد',
                    'operator' => 'المشغّل', 'none' => 'لا أحد');
$AR_EFFECT = array('billable_standby' => 'استعدادٌ مفوتر', 'non_billable' => 'غيرُ مفوتر',
                   'per_clause' => 'اقرأ البند');

head("⑦ مصفوفةُ عقد العرض #{$PILOT} كاملةً");
say('');
say('   ┌─────────────────────┬────────────┬──────────────────┬──────────────┐');
say('   │ البند               │ الملتزم    │ أثرُ الفوترة     │ يسري من      │');
say('   ├─────────────────────┼────────────┼──────────────────┼──────────────┤');
foreach ($obligationRows as $w) {
    if ($w['contract'] !== $PILOT) { continue; }
    $star = ($w['type'] === 'fuel') ? ' ★' : '';
    printf("   │ %-18s │ %-9s │ %-15s │ %-11s │%s\n",
        $AR[$w['type']], $AR_OBLIGOR[$w['obligor']], $AR_EFFECT[$w['effect']], $w['from'], $star);
}
say('   └─────────────────────┴────────────┴──────────────────┴──────────────┘');
say('   ★ = البندُ الوحيدُ المفارقُ للمحافظة — **فرضُ تجربةٍ معلن**، وكلُّ فرقٍ ماليٍّ يُعزى إليه وحدَه.');

say('');
say('   والعقودُ الثمانيةُ الباقيةُ محافظةٌ تمامًا (كلُّ البنود «الشركة»/«غيرُ مفوتر»)');
say('   — وهو ما يفعله النظامُ اليوم، فلا يتغيّر لها شيء.');

head('تمّ البذرُ · والعَلَمُ ما زال off');
say('   الخطوةُ التالية: اقلب EMS_ATTRIBUTION_MATRIX=on في .env ثم شغّل الحزم.');
say('   الرجوع في أي لحظة: php database/seeds/con02_rollback.php');
say('');
