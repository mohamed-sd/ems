<?php
/**
 * tools/repair01_w16_apply.php — اشتقاقُ دفاترِ المرحلةِ السادسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأداةُ تشتقُّ والبوّابةُ تُعيد القياس** — ولا تقرأ البوّابةُ ما تكتبه هذه.
 *
 * ◆ **ولا نسبةَ مجمَّعةً واحدة** (‏البندُ ٦٤): تسعةُ محاورَ لكلِّ نطاق، وكلُّ صفٍّ
 *   يحمل **بسطَه ومقامَه واسمَ مقامِه**. والنطاقُ الذي لا أداةَ لمحورِه يُكتب
 *   `NOT_MEASURED` بسببِه ⛔ **ولا يُكتب صفرًا** — والقاعدةُ نفسُها تردُّ خلافَ ذلك.
 *
 * ◆ **والقبولُ البشريُّ يُسجَّل محطّاتٍ مُنتظِرةً لا ناجحة** (‏البندُ ٦٣): تُكتب
 *   الرحلةُ وأدوارُها وخاناتُ أشخاصِها الثلاثةِ ومساراتُها السالبة، **ويبقى
 *   العبورُ فعلَ إنسانٍ حقيقيّ**. ⛔ ولا تكتب هذه الأداةُ `PASSED` أبدًا.
 *
 * التشغيل:
 *   php tools/repair01_w16_apply.php            ← يشتقُّ ويقيس
 *   php tools/repair01_w16_apply.php --issue    ← يُعيد قياسَ الطبقاتِ ويُصدر الأساس
 *   php tools/repair01_w16_apply.php --revert    ← يفرّغ دفاترَ المرحلة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w16_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ISSUE  = in_array('--issue', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w16_one($conn, $sql); };
$run = function ($sql) use ($conn) {
    if (!$conn->query($sql)) { exit("✘ SQL: {$conn->error}\n   " . mb_substr($sql, 0, 220) . "\n"); }
};

if ($REVERT) {
    foreach (array('repair01_w16_scorecard', 'repair01_w16_layers', 'repair01_w16_axes',
                   'repair01_w16_tabs', 'repair01_w16_uat', 'repair01_w16_challenge',
                   'repair01_w16_baseline', 'repair01_w16_decisions', 'repair01_w16_deferred',
                   'repair01_w16_fixes') as $t) {
        $conn->query("DELETE FROM `$t`");
    }
    exit("✔ فُرِّغت دفاترُ W16 — والدفاترُ باقيةٌ والهجرةُ تنزعها\n");
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① المحاورُ التسعة
   ═══════════════════════════════════════════════════════════════════════════ */
if (!$ISSUE) {
    $conn->query("DELETE FROM repair01_w16_axes");
    foreach (repair01_w16_axis_defs() as $a) {
        $run("INSERT INTO repair01_w16_axes (axis_key, axis_no, axis_name_ar, num_rule, den_rule, instrument)
              VALUES ('" . $esc($a[0]) . "', " . (int) $a[1] . ", '" . $esc($a[2]) . "',
                      '" . $esc($a[3]) . "', '" . $esc($a[4]) . "', '" . $esc($a[5]) . "')");
    }
    echo "✔ المحاورُ التسعةُ سُجِّلت\n";
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② الطبقاتُ الثمانيةُ — البندُ ٥٦
   ═══════════════════════════════════════════════════════════════════════════ */
$WAVE_UNION = "SELECT requirement_id FROM repair01_w3_scope
   UNION SELECT requirement_id FROM repair01_w4_scope
   UNION SELECT requirement_id FROM repair01_w5_scope
   UNION SELECT requirement_id FROM repair01_w7_scope
   UNION SELECT requirement_id FROM repair01_w8_scope
   UNION SELECT requirement_id FROM repair01_w9_scope
   UNION SELECT requirement_id FROM repair01_w11_scope
   UNION SELECT requirement_id FROM repair01_w12_scope
   UNION SELECT requirement_id FROM repair01_w13_scope
   UNION SELECT requirement_id FROM repair01_w14_scope
   UNION SELECT requirement_id FROM repair01_w15_scope";

$LAYERS = array(
array(1, 'OWNER_DECISIONS', 'مصالحة قرارات المالك', 'الخطة 56-1',
  'قرارات لا حاجب بنيوي مفتوحا فيها ولا اعتماد افترضه النظام',
  "SELECT (SELECT COUNT(*) FROM repair01_decisions d
             WHERE NOT (d.blocker_type = 'STRUCTURAL' AND d.status <> 'APPROVED')
               AND NOT EXISTS (SELECT 1 FROM repair01_decision_audit a
                                WHERE a.decision_id COLLATE utf8mb4_unicode_ci
                                    = d.decision_id COLLATE utf8mb4_unicode_ci
                                  AND a.verdict <> 'VALID_APPROVAL')) num,
          (SELECT COUNT(*) FROM repair01_decisions) den"),

array(2, 'CONSTITUTION', 'الدستور المؤسسي', 'الخطة 56-2',
  'صفوف جسر المسميات التي لها حكم ورمز معياري قائم في سجل الادارات',
  "SELECT (SELECT COUNT(*) FROM repair01_dept_crosswalk c
             JOIN repair01_departments d ON d.canonical_code = c.canonical_code
            WHERE c.verdict <> '') num,
          (SELECT COUNT(*) FROM repair01_dept_crosswalk) den"),

array(3, 'LEADERSHIP', 'القيادة', 'الخطة 56-3',
  'اسطح القيادة والمساحة الشخصية بمعرف وحكم ملكية ومسمى - والمستهدف غير المبني لا يعرض مسمى',
  "SELECT (SELECT COUNT(*) FROM repair01_screen_registry
            WHERE owner_code IN ('EX-CEO','EX-DVP','WS-MY')
              AND screen_id <> '' AND ownership_verdict <> ''
              AND (lifecycle = 'GHOST_TARGET' OR canonical_label_ar <> '')) num,
          (SELECT COUNT(*) FROM repair01_screen_registry
            WHERE owner_code IN ('EX-CEO','EX-DVP','WS-MY')) den"),

array(4, 'WAVES', 'ملفات الموجات', 'الخطة 56-4',
  'متطلبات الموجات التي دخلت دفتر موجتها',
  "SELECT (SELECT COUNT(DISTINCT r.requirement_id) FROM repair01_requirements r
            WHERE r.requirement_id IN ($WAVE_UNION)) num,
          (SELECT COUNT(*) FROM repair01_requirements) den"),

array(5, 'ENTERPRISE_REGISTRIES', 'السجلات المؤسسية', 'الخطة 56-5',
  'صفوف السجل المعياري بمعرف وحكم ملكية ومسمى - والمستهدف غير المبني لا يعرض مسمى',
  "SELECT (SELECT COUNT(*) FROM repair01_screen_registry
            WHERE screen_id <> '' AND ownership_verdict <> ''
              AND (lifecycle = 'GHOST_TARGET' OR canonical_label_ar <> '')) num,
          (SELECT COUNT(*) FROM repair01_screen_registry) den"),

array(6, 'SYSTEM_RECONCILIATION', 'المصالحة مع النظام', 'الخطة 56-6',
  'اسطح المصالحة التي لها حكم مصالحة ورمز ادارة معياري',
  "SELECT (SELECT COUNT(*) FROM repair01_surfaces
            WHERE recon_verdict <> '' AND canonical_code <> '') num,
          (SELECT COUNT(*) FROM repair01_surfaces) den"),

array(7, 'INDEPENDENT_REVIEW', 'المراجعة الثانية المستقلة', 'الخطة 56-7 و 50',
  'قواعد التحدي التي لم تصدر REDESIGN',
  "SELECT (SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity <> 'REDESIGN') num,
          (SELECT COUNT(*) FROM repair01_w16_challenge) den"),

array(8, 'LEADERSHIP_REVIEW', 'مراجعة القيادة', 'الخطة 56-8 و 53',
  'اسطح مكتب الرئيس والنواب التي لا تملك معاملة ادارة',
  "SELECT (SELECT COUNT(*) FROM repair01_screen_registry
            WHERE owner_code IN ('EX-CEO','EX-DVP')
              AND ownership_verdict <> 'DOMAIN_SOURCE' AND surface_kind <> 'SOURCE') num,
          (SELECT COUNT(*) FROM repair01_screen_registry
            WHERE owner_code IN ('EX-CEO','EX-DVP')) den"),
);

$conn->query("DELETE FROM repair01_w16_layers");
$lPass = 0;
foreach ($LAYERS as $L) {
    list($no, $key, $name, $ref, $denName, $sql) = $L;
    $r = @$conn->query($sql);
    $num = -1; $den = -1; $verdict = 'NOT_MEASURED'; $why = 'الاستعلام لم يعمل';
    if ($r && ($x = $r->fetch_assoc())) {
        $num = (int) $x['num']; $den = (int) $x['den'];
        /* ⛔ ومقامٌ خاوٍ ليس نجاحًا: طبقةٌ بلا مقامٍ لا تُثبت شيئًا */
        if ($den <= 0) { $verdict = 'NOT_MEASURED'; $why = 'مقام خاو - لا شيء قيس'; }
        elseif ($num === $den) { $verdict = 'PASS'; $why = 'المقيس يساوي المقام'; $lPass++; }
        else { $verdict = 'FAIL'; $why = 'ينقص ' . ($den - $num) . ' من ' . $den; }
    }
    $run("INSERT INTO repair01_w16_layers
          (layer_no, layer_key, layer_name_ar, clause_ref, measure_sql, den_name,
           measured_num, measured_den, verdict, why, measured_at)
          VALUES ($no, '" . $esc($key) . "', '" . $esc($name) . "', '" . $esc($ref) . "',
                  '" . $esc($sql) . "', '" . $esc($denName) . "',
                  $num, $den, '" . $esc($verdict) . "', '" . $esc($why) . "', NOW())");
}
printf("✔ الطبقاتُ الثمانيةُ قيست — عبرت %d/8\n", $lPass);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ حكمُ التبويباتِ السبعةِ والخمسين — الدَّينُ الموروثُ DC-13
   ═══════════════════════════════════════════════════════════════════════════ */
if (!$ISSUE) {
    /* ◆ **الحكمُ يُقرأ من `parent_rule` الذي كتبته أداةُ الحكمِ فرادى**، ويُحوَّل
       إلى **تصرُّفٍ مسجَّل**. ⛔ والتصرُّفُ ليس تنفيذًا: الدمجُ تغييرُ ملاحةٍ حيٍّ
       بقرارِ مالك، **ومعيارُ خروجِ الصنفِ حكمٌ مسجَّلٌ لكلِّ تبويبٍ على حدة**. */
    $MAP = array(
        'MERGE_READY'            => array('MERGE_INTO_PARENT',  ''),
        'MERGE_SAFE'             => array('MERGE_INTO_PARENT',  ''),
        'PARENT_DOUBTFUL'        => array('PARENT_RAISED',      ''),
        'KEEP_ITEM'              => array('KEEP_ITEM',          ''),
        'HIDDEN_WITH_LIVE_GRANT' => array('GRANT_GAP_TO_OWNER', 'W16-P-01'),
        'NO_PARENT'              => array('PARENT_RAISED',      ''),
        'NO_GRANT'               => array('PARENT_RAISED',      ''),
    );
    $conn->query("DELETE FROM repair01_w16_tabs");
    $n = 0; $unjudged = 0;
    $q = $conn->query("SELECT t.screen_file, t.owner_code, t.parent_rule, p.screen_file AS pfile
                         FROM repair01_screen_registry t
                         LEFT JOIN repair01_screen_registry p ON p.screen_id = t.parent_screen_id
                        WHERE t.ownership_verdict = 'TAB_CHILD' AND t.on_disk = 1");
    while ($q && ($x = $q->fetch_assoc())) {
        $rule = (string) $x['parent_rule'];
        $v = ''; $why = '';
        if (strpos($rule, ':') !== false) {
            list($v, $why) = array_map('trim', explode(':', $rule, 2));
        }
        if (!isset($MAP[$v])) { $unjudged++; continue; }
        list($disp, $ownerRef) = $MAP[$v];
        $run("INSERT INTO repair01_w16_tabs
              (screen_file, dept_code, parent_file, judged_verdict, disposition,
               roles_seeing, why, decided_by, decided_at, owner_ref)
              VALUES ('" . $esc($x['screen_file']) . "', '" . $esc($x['owner_code']) . "',
                      '" . $esc((string) $x['pfile']) . "', '" . $esc($v) . "',
                      '" . $esc($disp) . "', '',
                      '" . $esc(mb_substr($why, 0, 580)) . "',
                      'repair01_w16_apply', NOW(), '" . $esc($ownerRef) . "')");
        $n++;
    }
    printf("✔ التبويباتُ: سُجِّل حكمُ %d · بلا حكمٍ من الأداة %d\n", $n, $unjudged);
    if ($unjudged > 0) {
        echo "  ⚠ شغّلْ أوّلًا: php tools/repair01_edc_tabs.php --apply\n";
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④ محطّاتُ رحلةِ الموظّفِ الحقيقيّ — تُسجَّل مُنتظِرةً ⛔ لا ناجحة
   ═══════════════════════════════════════════════════════════════════════════ */
if (!$ISSUE) {
    $UAT = array(
    /* id, journey, no, station, domain, role, slot, negative */
    array('W16-U-01', 'REQ_TO_EFFECT',  1, 'يطلق صاحب الحساب طلب اجازة من مساحة عملي', 'WS-MY',  'صاحب الحساب', 'P1', 0),
    array('W16-U-02', 'REQ_TO_EFFECT',  2, 'يرى صاحب الحساب طلبه في طلباتي بحالته الاولى', 'WS-MY', 'صاحب الحساب', 'P1', 0),
    array('W16-U-03', 'REQ_TO_EFFECT',  3, 'يستقبل مسؤول القوى الطلب في سجل ادارته', 'DEP-13', 'مسؤول القوى التشغيلية', 'P2', 0),
    array('W16-U-04', 'REQ_TO_EFFECT',  4, 'يعتمد مسؤول القوى بسلطته المسجلة', 'DEP-13', 'مسؤول القوى التشغيلية', 'P2', 0),
    array('W16-U-05', 'REQ_TO_EFFECT',  5, 'تقيد الموارد البشرية الاثر في سجل الانسان', 'DEP-07', 'اخصائي الموارد البشرية', 'P3', 0),
    array('W16-U-06', 'REQ_TO_EFFECT',  6, 'يرى صاحب الحساب الحالة النهائية ولا يعدلها', 'WS-MY', 'صاحب الحساب', 'P1', 0),
    array('W16-U-07', 'REQ_TO_EFFECT',  7, 'يحاول صاحب الحساب اعتماد طلبه بنفسه فيرد', 'DEP-13', 'صاحب الحساب', 'P1', 1),
    array('W16-U-08', 'REQ_TO_EFFECT',  8, 'يحاول المسؤول اعتمادا فوق سقف سلطته فيرد', 'DEP-13', 'مسؤول القوى التشغيلية', 'P2', 1),
    array('W16-U-09', 'REQ_TO_EFFECT',  9, 'تحاول الموارد البشرية الاعتماد بتفويض منته فيرد', 'DEP-08', 'حامل تفويض منته', 'P3', 1),
    array('W16-U-10', 'MNT_CYCLE',      1, 'يفتح مشغل الموقع بلاغ عطل من شاشته', 'DEP-12', 'مشغل الموقع', 'P1', 0),
    array('W16-U-11', 'MNT_CYCLE',      2, 'يفتح فني الصيانة امر عمل بحالته', 'DEP-14', 'فني الصيانة', 'P2', 0),
    array('W16-U-12', 'MNT_CYCLE',      3, 'يستلم مسؤول التشغيل ويقفل بشهادة عودة معتمدة', 'DEP-11', 'مسؤول التشغيل', 'P3', 0),
    array('W16-U-13', 'MNT_CYCLE',      4, 'تحاول الصيانة اعادة الاصل بلا شهادة معتمدة فترد', 'DEP-14', 'فني الصيانة', 'P2', 1),
    );
    $conn->query("DELETE FROM repair01_w16_uat");
    foreach ($UAT as $u) {
        $run("INSERT INTO repair01_w16_uat
              (station_id, journey_key, station_no, station_ar, domain_code, required_role,
               person_slot, is_negative, status)
              VALUES ('" . $esc($u[0]) . "', '" . $esc($u[1]) . "', " . (int) $u[2] . ",
                      '" . $esc($u[3]) . "', '" . $esc($u[4]) . "', '" . $esc($u[5]) . "',
                      '" . $esc($u[6]) . "', " . (int) $u[7] . ", 'PENDING')");
    }
    printf("✔ محطّاتُ الرحلةِ البشريّة: %d مُنتظِرةً · ناجحةٌ 0 ⛔ ولا تكتبها أداة\n", count($UAT));
}

/* ═══════════════════════════════════════════════════════════════════════════
   ④-ب دفاترُ القرارِ والتأجيلِ والإصلاح
   ═══════════════════════════════════════════════════════════════════════════ */
if (!$ISSUE) {
    $D = array(
    array('W16-D-01', 'هل تمر المجموعة الخاوية خضراء في حواجب هذه المرحلة؟',
      'لا تمر - والخلاء يسقط الحاجب ما لم يعلن في هذا القرار بعينه',
      'مجموعة خاوية تخضر الحاجب على تطابق لا شيء - فالحارس مبني من البداية ومعلن هنا مرة واحدة، والقاعدة نفسها chk_w16_sc_den ترد الصف غير المقيس المكتوب صفرا', 0),
    array('W16-D-02', 'اين تعاد كتابة المصنفات الاثني عشر من المخزن؟',
      'اسقاطا في مجلد الاصدار baseline_v1 ⛔ لا فوق المصنفات المجمدة',
      'المصنفات الثلاثة عشر مجمدة بتجزئة sha256 في repair01_source_files والحاجب G0-01 يعيد تجزئتها في كل تشغيل - فالكتابة فوقها تمحو دليل الدخول وتسقط اساس الحملة كلها. والاكسل صيغة دخول لا صيغة عمل، والمخزن هو المصدر - فالمصنفات المعادة اسقاط من المخزن يحمل احكام الموجات الست عشرة، والمجمد يبقى شاهدا على ما دخل', 13),
    array('W16-D-03', 'مقياس DC-13 كان يعد التبويبات نفسها ومعيار خروجه قرار مسجل لكل تبويب - ايهما يتبع؟',
      'يتبع معيار الخروج المنصوص - والاستعلام يصلح ليقيس التبويب بلا قرار مسجل',
      'الاستعلام القديم رقمه سبعة وخمسون ابدا ولا يبلغ صفرا الا بحذف تبويب وهو نقيض حكم المالك - وهذا تشديد لا تليين: القديم لم يكن يرتفع ابدا والجديد يرتفع لحظة يفقد تبويب حكمه. ⛔ ولا دمج نفذ ولا بند اخفي', 57),
    array('W16-D-04', 'صنف دين مقياسه مسح كود لا استعلام قاعدة - كيف يسجل؟',
      'المقياس صورتان: measure_sql او measure_tool - وكلاهما مسجل يعاد تشغيله',
      'DC-18 كان يقاس بفرع مخبوء في اداة التصنيف وحدها فلا يستطيع احد اعادة قياسه من السجل وصفره صفر من مقام مجهول - والجرد الحاجب لم يسال اثم صنف بلا مقياس فمر', 18),
    array('W16-D-05', 'باي حالة يصدر الاساس المؤسسي؟',
      'ISSUED_AWAITING_OWNER - والثمانية عبرت والمراجعة المستقلة بلا REDESIGN',
      'الخطة تسمي الناتج OWNER APPROVED، والاعتماد قرار مالك لا نتيجة اداة كما نص البند العاشر: هذه تثبت استيفاء الشروط لا غير. والقيد في القاعدة chk_w16_bl_owner يرد OWNER_APPROVED بلا مرجع ختم - فالاداة لا تستطيع ان تنتحل الختم حتى لو اردنا', 8),
    array('W16-D-06', 'محور البيانات من التسعة - كيف ينشر ولا اداة لقياسه؟',
      'ينشر NOT_MEASURED بسببه المكتوب ⛔ ولا يكتب صفرا ولا يحذف من التسعة',
      'حذفه يجعلها ثمانية فينكسر نص البند الرابع والستين، وكتابته صفرا ادعاء بمقام مجهول. والقاعدة chk_w16_sc_den تلزم ان يحمل غير المقيس سببه وان يكون بسطه ومقامه سالبا واحدا لا صفرا', 22),
    array('W16-D-07', 'PLATFORM ليست ادارة معيارية وتملك اثني عشر سطحا حيا - اتنشر لها المقامات؟',
      'تنشر - نطاق نشر ثان وعشرون معلن بصفته لا مطروح صامتا',
      'اسقاطها من اللوحة يخفي اثني عشر سطحا حيا من كل مقام ينشر، ومقام يستثني ما لا يعجبه ليس مقاما. فتنشر بصفتها المكتوبة: ليست ادارة من السبع عشرة', 12),
    );
    $conn->query("DELETE FROM repair01_w16_decisions");
    foreach ($D as $d) {
        $run("INSERT INTO repair01_w16_decisions (decision_id, question, answer, rationale, scope_rows, decided_at, src_ref)
              VALUES ('" . $esc($d[0]) . "', '" . $esc($d[1]) . "', '" . $esc($d[2]) . "',
                      '" . $esc($d[3]) . "', " . (int) $d[4] . ", CURDATE(), 'RPR-W16')");
    }

    $P = array(
    array('W16-P-01', 'اثنان وعشرون تبويبا مخفية من الملاحة والمنح عليها حي - اتسحب المنح ام يعاد اظهارها؟',
      'كلاهما توسيع او تضييق وصول حي - ولا يقرره مبرمج',
      'اقفال الفجوة نهائيا لهذه الاثنين والعشرين',
      'حكم كل واحد منها مسجل فرادى بسببه وعدد ادواره في repair01_w16_tabs - والدين صار محكوما لا مجهولا', 'OWNER_DECISION'),
    array('W16-P-02', 'ختم المالك على ENTERPRISE-TARGET-BASELINE-v1.0',
      'الاعتماد قرار مالك لا نتيجة اداة - والبند العاشر ينص عليه',
      'انتقال حالة الاصدار من ISSUED_AWAITING_OWNER الى OWNER_APPROVED',
      'الثمانية قيست وعبرت والمراجعة المستقلة سجلت باثنتي عشرة قاعدة ومقاماتها والمصالحة والجرد قائمان - فما ينقص الختم وحده', 'OWNER_DECISION'),
    array('W16-P-03', 'رحلة موظف حقيقي بثلاثة اشخاص مختلفين ومسار سالب بشري',
      'البند الثالث والستون: موظف فعلي يمر الرحلة ولا بذور بيانات فقط',
      'محور القبول البشري ومحور القبول النهائي في كل نطاق',
      'ثلاث عشرة محطة مسجلة برحلتيها وادوارها وخانات اشخاصها الثلاث واربعة مسارات سالبة - والقاعدة chk_w16_uat_real ترد اعلان النجاح بلا فاعل حقيقي وزمن ودليل ⛔ فلا تستطيع اداة ان تخضره', 'HUMAN_ACT'),
    array('W16-P-04', 'جاهزية البيانات - المحور السادس من التسعة',
      'قياسها فحص محتوى حي لكل نطاق لم تبنه هذه الحملة',
      'محور البيانات في اثنين وعشرين نطاقا',
      'ينشر NOT_MEASURED بسببه المكتوب في كل نطاق ⛔ ولا يكتب صفرا - والمحور باق في التسعة لا يحذف', 'TOOL_MISSING'),
    array('W16-P-05', 'ثلاثة وتسعون قرارا معتمدا بلا مرجع جواب مكتوب في عمود المرجع',
      'المرجع عمود مستقل عن approved_by وهو مملوء ستة وتسعين من ستة وتسعين',
      'لا شيء - ولا حاجب يقف عليه',
      'الرقم كان يقرا صفرا لان البوابة تسال عمودا لا وجود له، والان يعلن بحقيقته ثلاثة وتسعين - والاعلان اول شرط المعالجة', 'OWNER_DECISION'),
    array('W16-P-06', 'نسبة التغطية الالية الى نطاق: لا دفتر رحلة يحمل معرف متطلب',
      'محور الاختبار من التسعة يقاس بحبة المتطلب ودفاتر الرحلات مقيدة بالكيان والشوط',
      'محور الاختبار في اثنين وعشرين نطاقا',
      'ينشر NOT_MEASURED بسببه ويطبع في سببه المقيس الحقيقي - والعلاج عمود معرف متطلب في دفاتر الرحلات وهو تعديل على ثلاثة عشر دفترا مغلقا لا تقرره مرحلة تستفيد منه', 'TOOL_MISSING'),
    );
    $conn->query("DELETE FROM repair01_w16_deferred");
    foreach ($P as $p) {
        $run("INSERT INTO repair01_w16_deferred (deferred_id, question, why_needed, blocked_what, built_anyway, kind, raised_at, src_ref)
              VALUES ('" . $esc($p[0]) . "', '" . $esc($p[1]) . "', '" . $esc($p[2]) . "',
                      '" . $esc($p[3]) . "', '" . $esc($p[4]) . "', '" . $esc($p[5]) . "', CURDATE(), 'RPR-W16')");
    }

    $X = array(
    array('W16-F-01', 'مقياس DC-13 كان يعد غير ما يشترطه معيار خروجه',
      'قاعدة التحدي CH-09 حين سالت: كم تبويبا بلا قرار مسجل؟',
      'استبدل الاستعلام بما يقيس المعيار المنصوص: تبويب لا صف له في دفتر حكم المرحلة - وهو تشديد لا تليين لان القديم لم يكن يرتفع ابدا',
      'قبل 57 ثابتا لا يبلغ صفرا الا بحذف تبويب · بعد 0 من مقام 57 وكل تبويب بحكمه وسببه'),
    array('W16-F-02', 'بوابة W15 كانت تسال عمودا لا وجود له فتطبع صفرا اطمئنانا كاذبا',
      'قاعدة التحدي CH-04 بمطابقة كل عمود مذكور على information_schema',
      'owner_answer_ref لا وجود له - والاسم الحي owner_decision_reference · والرقم ليس شرط عبور فالبوابة بقيت 32 من 32 والمطبوع صار حقيقيا',
      'قبل: معتمد بلا مرجع جواب 0 · بعد: 93 من 96 - والفرق ليس اصلاح بيانات بل اصلاح مقياس'),
    array('W16-F-03', 'DC-18 كان يقاس بفرع مخبوء في اداة التصنيف لا بمقياس مسجل',
      'قاعدة التحدي CH-02: صنف دين صفره غير قابل للقياس',
      'اضيف عمود measure_tool للسجل وسجل فيه امر مسح الكود، وحذف الفرع المخبوء فصارت اداة التصنيف تشغل ما في السجل لكل صنف بلا استثناء',
      'قبل: measure_sql فارغ ولا مقياس بديل مسجل · بعد: 0 من مقام مقروء باداة مسجلة تعاد'),
    array('W16-F-04', 'محرك التحدي كان يخضر على مقام خاو',
      'قراءة مخرجه: CH-10 اظهرت 0 من 0 قبل تسجيل المحطات',
      'مقام صفر صار يعامل خرقا معلنا بنصه لا براءة - فقاعدة لم تفحص شيئا لا تكتب ACCEPT',
      'قبل: ACCEPT على 0 من 0 · بعد: الشدة المكتوبة للقاعدة مع نص مقام خاو'),
    array('W16-F-05', 'قاعدة الشاهد السالب كانت تعرف صورة واحدة فرسبت W13.5 ظلما',
      'قراءة مخرج CH-11: رسبت w135 وشاهدها السالب ثلاثة ملفات في tests',
      'صارت تقبل الصورتين: اداة كسر وارجاع في tools او شواهد سالبة في tests - وقاعدة تعرف صورة واحدة ترسب البريء بجهلها هي',
      'قبل: 2 من 18 مرحلة راسبة منها w135 · بعد: تقاس الصورتان معا'),
    array('W16-F-06', 'قيد chk_w16_sc_den كان يقبل ما يمنعه نصه: صف مقيس بمقام خاو',
      'الفحص السلبي - الحزام الثاني: كتابة 0 من 0 بحال MEASURED قبلت',
      'كتب den >= 0 والمقصود den > 0 - فصف مقيس بمقام صفر كان يمر وهو عين ما ينهى عنه نص القيد · اعيد بناؤه على الجدول القائم بلا مس صف واحد',
      'قبل: INSERT بمقام 0 وحال MEASURED قبلت · بعد: تسقط بالقيد نفسه'),
    array('W16-F-07', 'الحزام الرابع كان يتهم محرك التحدي بعجز لم يقع',
      'قراءة مخرجه: عاجز عن REDESIGN مع ان الزرع نفسه رد بـchk_w135_why',
      'صار الزرع يتحقق منه قبل ان يسال المحرك، ويحمل verdict_rule الذي يشترطه قيد W13.5 - وزرع رد صامتا يقرا عجزا في المفحوص',
      'قبل: عاجز والزرع لم يقع · بعد: REDESIGN على خرق حقيقي والبوابات الست عشرة خضراء في الحالين'),
    array('W16-F-08', 'عمود version كان اضيق من اسم الاصدار فبتره صامتا',
      'محاولة حذف صفوف الاصدار المكررة بمطابقة الاسم الكامل - فلم تطابق شيئا',
      'VARCHAR(24) واسم الاصدار احد وثلاثون حرفا - فقرئ الاسم صحيحا وهو خطا ولم يشك احد · وسع العمود وحذف المبتور واضيف الى الحاجب W16-21 مطابقة حرفية للاسم',
      'قبل: version = ENTERPRISE-TARGET-BASELI وثلاثة صفوف اصدار متكدسة · بعد: الاسم كامل وصف واحد والحاجب يسقط على البتر'),
    array('W16-F-09', 'محور الاختبار كان يكتب صفرا من مقام مجهول',
      'قراءة اللوحة: TEST = 0 من 429 في كل نطاق - وهو صفر يقرا لا تغطية الية وهو كذب',
      'لا دفتر رحلة من الثلاثة عشر يحمل معرف متطلب فالربط غير موجود اصلا - والصفر كان من غياب الاداة لا من غياب التغطية · صار المحور NOT_MEASURED بسببه ويطبع في سببه المقيس الحقيقي: المحطات العابرة من المحطات كلها',
      'قبل: 0 من 429 في اثنين وعشرين نطاقا · بعد: غير مقيس معلن مع 67054 محطة عابرة من 67368 غير منسوبة'),
    array('W16-F-10', 'قاعدة التحدي CH-02 كانت اضيق من دعواها',
      'قراءة مخرجها بعد اصلاح DC-18: ظلت ترسب صنفا صار له مقياس',
      'عنوانها غير قابل للقياس وكانت تعد غياب الاستعلام وحده - فترسب صنفا مقياسه اداة مسح مسجلة تشغل · صارت تعد الصورتين معا وهو وفاء لنص القاعدة لا تليين لها',
      'قبل: 1 من 18 والصنف قابل للقياس فعلا · بعد: 0 من 18 والحاجب W16-19 يقيس الصورتين ايضا'),
    array('W16-F-11', 'جرد الاغلاق كان يختم تقريره بلقطة لم يقس فيها',
      'مقارنة ختم التقارير الثلاثة: المصالحة والانحدار ختما 47577ee7 والجرد ختم 02224261',
      'كان ياخذ اخر لقطة بـfrozen_at - ولقطتان في اليوم نفسه احداهما 14:55 والاخرى 04:11 فترتيب الوقت يقدم الاقدم · صار ياخذ النافذة المفتوحة التي يشتغل داخلها، وان لم تكن ثمة نافذة اعلن ذلك نصا ولم ينسب التقرير الى لقطة مغلقة صامتا',
      'قبل: تقرير يزعم لقطة لم يقس فيها وهو نص ما يمنعه البند 13 · بعد: الثلاثة تختم لقطة واحدة بعينها'),
    );
    $conn->query("DELETE FROM repair01_w16_fixes");
    foreach ($X as $x) {
        $run("INSERT INTO repair01_w16_fixes (fix_id, title, found_by, what, evidence, fixed_at)
              VALUES ('" . $esc($x[0]) . "', '" . $esc($x[1]) . "', '" . $esc($x[2]) . "',
                      '" . $esc($x[3]) . "', '" . $esc($x[4]) . "', CURDATE())");
    }
    printf("✔ الدفاتر: قراراتٌ %d · مؤجَّلٌ %d · إصلاحاتٌ %d\n", count($D), count($P), count($X));
}

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ لوحةُ المقاماتِ التسعةِ لكلِّ نطاق
   ═══════════════════════════════════════════════════════════════════════════ */
$DOM  = repair01_w16_domains();
$ANCH = repair01_w16_requirement_anchors($conn);
$JRNY = repair01_w16_journey_requirements($conn);
$JT   = repair01_w16_journey_totals($conn);

/* السجلُّ المعياريُّ في الذاكرةِ مرّةً */
$reg = array();
$q = $conn->query("SELECT screen_id, owner_code, lifecycle, canonical_label_ar, ownership_verdict,
                          visibility_class, route, state_model_ref
                     FROM repair01_screen_registry");
while ($q && ($x = $q->fetch_assoc())) { $reg[$x['screen_id']] = $x; }

/* الحقولُ بمتطلَّبِها */
$fieldOf = array();
$q = $conn->query("SELECT DISTINCT requirement_id FROM repair01_fields WHERE requirement_id <> ''");
while ($q && ($x = $q->fetch_row())) { $fieldOf[$x[0]] = true; }

/* الأحداثُ بنطاقِها */
$evTot = array(); $evRec = array(); $evUnmapped = 0;
$q = $conn->query("SELECT source_unit, contract_status FROM repair01_events");
while ($q && ($x = $q->fetch_assoc())) {
    $c = repair01_w16_unit_code($x['source_unit']);
    if ($c === '') { $evUnmapped++; continue; }
    $evTot[$c] = (isset($evTot[$c]) ? $evTot[$c] : 0) + 1;
    if ($x['contract_status'] === 'RECORDED') { $evRec[$c] = (isset($evRec[$c]) ? $evRec[$c] : 0) + 1; }
}

/* محطّاتُ القبولِ البشريِّ بنطاقِها */
$uatTot = array(); $uatOk = array();
$q = $conn->query("SELECT domain_code, status FROM repair01_w16_uat");
while ($q && ($x = $q->fetch_assoc())) {
    $d = $x['domain_code'];
    $uatTot[$d] = (isset($uatTot[$d]) ? $uatTot[$d] : 0) + 1;
    if ($x['status'] === 'PASSED') { $uatOk[$d] = (isset($uatOk[$d]) ? $uatOk[$d] : 0) + 1; }
}

/* انتقالاتُ الحالةِ غير المنسوبةِ — تُعلَن بعددِها ولا تُحسب */
$stateEnt = count(repair01_w16_state_entities($conn));

$conn->query("DELETE FROM repair01_w16_scorecard");
$rowsWritten = 0; $notMeasured = 0;
$put = function ($dom, $axis, $num, $den, $denName, $note) use ($run, $esc, &$rowsWritten, &$notMeasured) {
    if ($den <= 0) {
        $run("INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note, measured_at)
              VALUES ('" . $esc($dom) . "', '" . $esc($axis) . "', -1, -1, '', 'NOT_MEASURED',
                      '" . $esc($note !== '' ? $note : 'لا مقام لهذا النطاق في هذا المحور') . "', NOW())");
        $notMeasured++;
    } else {
        $run("INSERT INTO repair01_w16_scorecard (domain_code, axis_key, num, den, den_name, verdict, note, measured_at)
              VALUES ('" . $esc($dom) . "', '" . $esc($axis) . "', " . (int) $num . ", " . (int) $den . ",
                      '" . $esc($denName) . "', 'MEASURED', '" . $esc($note) . "', NOW())");
    }
    $rowsWritten++;
};

foreach ($DOM as $d) {
    /* متطلَّباتُ النطاقِ ومِرساتُها */
    $reqIds = array();
    foreach ($ANCH as $rid => $a) { if ($a['code'] === $d) { $reqIds[$rid] = $a; } }
    $reqN = count($reqIds);

    $structOk = 0; $wfOk = 0; $fieldOk = 0; $testOk = 0; $acceptOk = 0;
    foreach ($reqIds as $rid => $a) {
        $sid = $a['anchor'];
        $hasReg = ($sid !== '' && isset($reg[$sid]));
        $st = $hasReg && $reg[$sid]['ownership_verdict'] !== '';
        if ($st) { $structOk++; }
        if ($hasReg && (string) $reg[$sid]['state_model_ref'] !== '') { $wfOk++; }
        $fl = isset($fieldOf[$rid]);
        if ($fl) { $fieldOk++; }
        $ts = isset($JRNY[$rid]);
        if ($ts) { $testOk++; }
        /* القبولُ النهائيُّ يلزمه القبولُ البشريُّ — وهو صفرٌ ما لم يعبر إنسان */
        $hu = isset($uatOk[$d]) && $uatOk[$d] > 0;
        if ($st && $fl && $ts && $hu) { $acceptOk++; }
    }

    /* أسطحُ النطاقِ الحيّة */
    $live = 0; $navOk = 0;
    foreach ($reg as $sid => $x) {
        if ($x['owner_code'] !== $d) { continue; }
        if ($x['lifecycle'] !== 'LIVE_REGISTERED' && $x['lifecycle'] !== 'LIVE_UNREGISTERED') { continue; }
        $live++;
        if ($x['canonical_label_ar'] !== '' && $x['visibility_class'] !== 'NOT_BUILT' && $x['route'] !== '') { $navOk++; }
    }

    $put($d, 'STRUCTURAL', $structOk, $reqN, 'متطلبات النطاق في سجل المتطلبات', '');
    $put($d, 'NAVIGATION', $navOk, $live, 'اسطح النطاق الحية في السجل المعياري', '');
    $put($d, 'FIELD', $fieldOk, $reqN, 'متطلبات النطاق في سجل المتطلبات', '');
    $put($d, 'WORKFLOW', $wfOk, $reqN, 'متطلبات النطاق في سجل المتطلبات',
         'وانتقالات الموجات غير المنسوبة الى نطاق معلنة بعددها: ' . $stateEnt . ' كيانا في دفاتر الموجات');
    $put($d, 'INTEGRATION', isset($evRec[$d]) ? $evRec[$d] : 0, isset($evTot[$d]) ? $evTot[$d] : 0,
         'احداث النطاق في سجل الاحداث',
         'واحداث بلا وحدة قابلة للنسبة معلنة بعددها: ' . $evUnmapped);
    $put($d, 'DATA', -1, 0, '', 'غير مقيس باعلان - لا اداة لجاهزية البيانات في هذه الحملة');
    /* ⛔ **ومحورُ الاختبارِ لا أداةَ له بحبّةِ المتطلَّب**: ولا دفترَ رحلةٍ من
       الثلاثةَ عشرَ يحمل مُعرِّفَ متطلَّبٍ — فالمحطّاتُ مقيَّدةٌ بالكيانِ والشوطِ لا
       بالمتطلَّب. **وكتابةُ صفرٍ هنا صفرٌ من مقامٍ مجهول** يُقرأ «لا تغطيةَ آليّة»
       وهو كذب: التغطيةُ قائمةٌ بمحطّاتِها **ولكنّها لا تُنسَب إلى نطاق**.
       ⇒ فتُعلَن غيرَ مقيسةٍ **ويُطبَع المقيسُ الحقيقيُّ في سببِها**. */
    $put($d, 'TEST', -1, 0, '',
         'غير مقيس بحبة المتطلب: لا دفتر رحلة من ' . $JT['books'] . ' يحمل معرف متطلب - '
         . 'والتغطية الالية قائمة: ' . $JT['passed'] . ' محطة عابرة من ' . $JT['stations']
         . ' ولكنها لا تنسب الى نطاق');
    $put($d, 'HUMAN_UAT', isset($uatOk[$d]) ? $uatOk[$d] : 0, isset($uatTot[$d]) ? $uatTot[$d] : 0,
         'محطات رحلة الاثبات المسجلة لهذا النطاق',
         isset($uatTot[$d]) ? '' : 'لا محطة مسجلة لهذا النطاق في رحلة الاثبات');
    $put($d, 'ACCEPTANCE', $acceptOk, $reqN, 'متطلبات النطاق في سجل المتطلبات',
         'صفر بحكم اقترانه: القبول البشري صفر في كل نطاق والاقتران بصفر صفر - '
         . 'ولا يتوقف هذا الصفر على محور الاختبار غير المقيس');
}
printf("✔ لوحةُ المقامات: %d صفًّا (%d نطاقًا × 9) · منها غيرُ مقيسٍ بإعلان %d\n",
       $rowsWritten, count($DOM), $notMeasured);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ إصدارُ الأساس — بعد المراجعةِ المستقلّةِ لا قبلها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($ISSUE) {
    $chN   = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge");
    if ($chN === 0) { exit("⛔ لا مراجعةَ مستقلّةً مسجَّلة — شغّلْ repair01_w16_challenge.php --apply أوّلًا\n"); }
    $red = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'REDESIGN'");
    $con = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'CONCERN'");
    $lp  = (int) $one("SELECT COUNT(*) FROM repair01_w16_layers WHERE verdict = 'PASS'");
    $lt  = (int) $one("SELECT COUNT(*) FROM repair01_w16_layers");
    $chV = $red > 0 ? 'REDESIGN' : ($con > 0 ? 'CONCERN' : 'ACCEPT');

    $snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1");
    $commit = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD 2>&1'));
    if ($snap === '' || $commit === '') { exit("⛔ لا لقطةَ مختومةٌ أو لا التزامَ مقروء — الأساسُ لا يُصدَر بلا بصمة\n"); }

    /* ⛔ **والحالةُ تُشتقُّ من المقيسِ لا تُختار**: الثمانيةُ كلُّها + صفرُ REDESIGN
       ⇒ يُصدَر منتظِرًا ختمَ المالك. وإلّا فحالتُه `REDESIGN` ⛔ ولا يُصدَر أخضرَ. */
    $state = ($lp === $lt && $lt > 0 && $red === 0) ? 'ISSUED_AWAITING_OWNER' : 'REDESIGN';
    $why = ($state === 'ISSUED_AWAITING_OWNER')
        ? 'الثمانية عبرت والمراجعة المستقلة بلا REDESIGN - والاعتماد ختم مالك لا نتيجة اداة · والقبول البشري محور تاسع لم يعبره انسان بعد فلا يعلن مقبولا'
        : 'لا يصدر الاساس: طبقات عابرة ' . $lp . ' من ' . $lt . ' واحكام REDESIGN ' . $red;

    /* ◆ **إصدارٌ واحدٌ للنسخةِ الواحدة**: إعادةُ التشغيلِ تُحدِّث ولا تكدّس صفوفًا
       يتفرّق بعضُها عن بعض. ⛔ **وما ختمه المالكُ لا يُمَسّ** — والحذفُ يستثنيه. */
    $conn->query("DELETE FROM repair01_w16_baseline
                   WHERE version = 'ENTERPRISE-TARGET-BASELINE-v1.0' AND state <> 'OWNER_APPROVED'");
    $bid = 'ETB-' . substr($commit, 0, 8) . '-' . date('Ymd-His');
    $run("INSERT INTO repair01_w16_baseline
          (baseline_id, version, state, snapshot_id, commit_hash, layers_pass, layers_total,
           challenge_verdict, redesign_count, concern_count, owner_ref, issued_at, why)
          VALUES ('" . $esc($bid) . "', 'ENTERPRISE-TARGET-BASELINE-v1.0', '" . $esc($state) . "',
                  '" . $esc($snap) . "', '" . $esc($commit) . "', $lp, $lt,
                  '" . $esc($chV) . "', $red, $con, '', NOW(), '" . $esc($why) . "')");
    printf("✔ سجلُّ الإصدار: %s · الحالة %s · الطبقات %d/%d · المراجعة %s (REDESIGN %d · CONCERN %d)\n",
           $bid, $state, $lp, $lt, $chV, $red, $con);
}

echo "\n✔ تمّ\n";
